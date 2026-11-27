<?php

namespace App\Console\Commands;

use App\Domains\Integration\Parsers\Hl7Parser;
use App\Domains\Integration\Services\DeviceFrameExtractor;
use App\Domains\Integration\Services\DeviceMessageIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListenDeviceMessages extends Command
{
    protected $signature = 'devices:listen
        {--host= : Bind address, defaults to config/devices.php}
        {--lab-port= : Lab HL7/ASTM TCP port, set 0 to disable}
        {--radiology-port= : Radiology/ultrasound HL7/ASTM TCP port, set 0 to disable}
        {--quiet-acks : Do not write ACK frames back to devices}';

    protected $description = 'Listen for HL7/ASTM/FHIR/JSON/XML device results and store them for human review';

    private readonly DeviceMessageIngestService $ingest;

    public function __construct(DeviceMessageIngestService $ingest)
    {
        parent::__construct();

        $this->ingest = $ingest;
    }

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: config('devices.listener.host', '0.0.0.0'));
        $ports = [
            'lab' => (int) ($this->option('lab-port') !== null ? $this->option('lab-port') : config('devices.listener.lab_port', 2575)),
            'radiology' => (int) ($this->option('radiology-port') !== null ? $this->option('radiology-port') : config('devices.listener.radiology_port', 2576)),
        ];

        $servers = [];
        foreach ($ports as $kind => $port) {
            if ($port <= 0) {
                continue;
            }

            $server = $this->openServer($host, $port);
            $servers[(int) $server] = ['resource' => $server, 'kind' => $kind, 'port' => $port];
            $this->info(sprintf('%s device listener started on %s:%d', ucfirst($kind), $host, $port));
            Log::info('Device listener started', ['kind' => $kind, 'host' => $host, 'port' => $port]);
        }

        if ($servers === []) {
            $this->error('Both listener ports are disabled.');

            return self::FAILURE;
        }

        /** @var array<int, array{resource: resource, kind: string, buffer: string, host: string}> $clients */
        $clients = [];

        while (true) {
            $read = array_map(static fn (array $server) => $server['resource'], $servers);
            foreach ($clients as $client) {
                $read[] = $client['resource'];
            }

            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 1);

            if ($ready === false) {
                $this->error('stream_select failed, stopping listener.');

                return self::FAILURE;
            }

            foreach ($read as $stream) {
                $id = (int) $stream;

                if (isset($servers[$id])) {
                    $this->acceptClient($servers[$id], $clients);

                    continue;
                }

                if (! isset($clients[$id])) {
                    continue;
                }

                if (! $this->readClient($clients[$id])) {
                    unset($clients[$id]);
                }
            }
        }
    }

    /** @return resource */
    private function openServer(string $host, int $port)
    {
        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $error);

        if ($server === false) {
            throw new RuntimeException("Could not bind {$host}:{$port}: {$error} ({$errno})");
        }

        stream_set_blocking($server, false);

        return $server;
    }

    /**
     * @param  array{resource: resource, kind: string, port: int}  $server
     * @param  array<int, array{resource: resource, kind: string, buffer: string, host: string}>  $clients
     */
    private function acceptClient(array $server, array &$clients): void
    {
        $client = @stream_socket_accept($server['resource'], 0, $peer);

        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        $host = $this->peerHost((string) $peer);
        $id = (int) $client;

        $clients[$id] = [
            'resource' => $client,
            'kind' => $server['kind'],
            'buffer' => '',
            'host' => $host,
        ];

        $this->info(sprintf('Connected: %s device %s', $server['kind'], $peer));
    }

    /**
     * @param  array{resource: resource, kind: string, buffer: string, host: string}  $client
     */
    private function readClient(array &$client): bool
    {
        $chunk = @fread($client['resource'], 8192);

        if ($chunk === false) {
            return false;
        }

        if ($chunk !== '') {
            $client['buffer'] .= $chunk;

            foreach ($this->extractMessages($client['buffer'], $client['kind']) as $raw) {
                $this->processMessage($client, $raw);
            }
        }

        if (feof($client['resource'])) {
            if (trim($client['buffer']) !== '') {
                $this->processMessage($client, $client['buffer']);
                $client['buffer'] = '';
            }

            fclose($client['resource']);

            return false;
        }

        return true;
    }

    /** @return array<int, string> */
    private function extractMessages(string &$buffer, string $kind): array
    {
        return (new DeviceFrameExtractor())->extract($buffer);
    }

    /**
     * @param  array{resource: resource, kind: string, buffer: string, host: string}  $client
     */
    private function processMessage(array $client, string $raw): void
    {
        try {
            $context = ['remote_host' => $client['host']];
            $message = $client['kind'] === 'lab'
                ? $this->ingest->ingestLab($raw, $context)
                : $this->ingest->ingestRadiology($raw, $context);

            $this->info(sprintf(
                '%s message #%d => %s',
                ucfirst($client['kind']),
                $message->id,
                $message->status,
            ));

            if (! $this->option('quiet-acks')) {
                @fwrite($client['resource'], $this->ack($raw, true));
            }
        } catch (Throwable $exception) {
            $this->error(sprintf('%s import failed: %s', ucfirst($client['kind']), $exception->getMessage()));
            Log::error('Device TCP import failed', [
                'kind' => $client['kind'],
                'host' => $client['host'],
                'error' => $exception->getMessage(),
            ]);

            if (! $this->option('quiet-acks')) {
                @fwrite($client['resource'], $this->ack($raw, false));
            }
        }
    }

    private function ack(string $raw, bool $ok): string
    {
        if (! str_contains($raw, 'MSH|')) {
            return $ok ? "\x06" : "\x15";
        }

        $messageId = (new Hl7Parser())->parse($raw)['message_id'] ?? null;
        $controlId = $messageId ?: 'ACK-'.Str::upper(Str::random(8));
        $code = $ok ? 'AA' : 'AE';
        $ack = implode("\r", [
            'MSH|^~\\&|SCARLET|HMS|DEVICE|LAB|'.now()->format('YmdHis').'||ACK|'.$controlId.'|P|2.5',
            'MSA|'.$code.'|'.$controlId,
        ])."\r";

        return "\x0B".$ack."\x1C\r";
    }

    private function peerHost(string $peer): string
    {
        $parts = explode(':', $peer);

        if (count($parts) <= 2) {
            return $parts[0] ?? '';
        }

        return implode(':', array_slice($parts, 0, -1));
    }
}
