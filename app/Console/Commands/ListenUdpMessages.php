<?php

namespace App\Console\Commands;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ListenUdpMessages extends Command
{
    protected $signature = 'devices:listen-udp
        {--host= : Bind address, defaults to config/devices.php}
        {--port= : UDP port, defaults to 2578}
        {--kind= : lab, radiology or clinical (default clinical)}';

    protected $description = 'Receive UDP/Syslog device feeds from bedside and network devices';

    public function __construct(private readonly DeviceMessageIngestService $ingest)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: config('devices.listener.host', '0.0.0.0'));
        $port = (int) ($this->option('port') ?: config('devices.listener.udp_port', 2578));
        $kind = strtolower((string) ($this->option('kind') ?: config('devices.listener.udp_kind', 'clinical')));
        $kind = in_array($kind, ['lab', 'radiology', 'clinical'], true) ? $kind : 'clinical';

        $socket = @stream_socket_server("udp://{$host}:{$port}", $errno, $error);
        if ($socket === false) {
            throw new RuntimeException("Could not bind UDP {$host}:{$port}: {$error} ({$errno})");
        }

        $this->info(sprintf('UDP device listener started on %s:%d (%s)', $host, $port, $kind));
        Log::info('UDP device listener started', ['host' => $host, 'port' => $port, 'kind' => $kind]);

        while (true) {
            $read = [$socket];
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 1);

            if ($ready === false) {
                $this->error('stream_select failed, stopping UDP listener.');

                return self::FAILURE;
            }

            if ($ready === 0) {
                continue;
            }

            $raw = @stream_socket_recvfrom($socket, 65535, 0, $peer);
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            try {
                $context = ['remote_host' => $this->peerHost((string) $peer)];
                $message = match ($kind) {
                    'lab' => $this->ingest->ingestLab($raw, $context),
                    'radiology' => $this->ingest->ingestRadiology($raw, $context),
                    default => $this->ingest->ingestClinical($raw, $context),
                };
                $this->info(sprintf('%s UDP message #%d => %s', ucfirst($kind), $message->id, $message->status));
            } catch (Throwable $exception) {
                $this->error('UDP import failed: '.$exception->getMessage());
                Log::error('Device UDP import failed', ['kind' => $kind, 'error' => $exception->getMessage()]);
            }
        }
    }

    private function peerHost(string $peer): string
    {
        $parts = explode(':', $peer);

        return count($parts) <= 2 ? ($parts[0] ?? '') : implode(':', array_slice($parts, 0, -1));
    }
}
