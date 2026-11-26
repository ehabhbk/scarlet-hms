<?php

namespace App\Console\Commands;

use App\Domains\Integration\Dicom\DicomDataset;
use App\Domains\Integration\Services\DicomStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ListenDicomMessages extends Command
{
    protected $signature = 'devices:listen-dicom
        {--host= : Bind address, defaults to config/devices.php}
        {--port= : DICOM C-STORE port, set 0 to disable}
        {--quiet-acks : Do not send C-STORE responses back to devices}';

    protected $description = 'Accept DICOM C-STORE associations, store objects and import report text automatically';

    private readonly DicomStoreService $dicom;

    public function __construct(DicomStoreService $dicom)
    {
        parent::__construct();

        $this->dicom = $dicom;
    }

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: config('devices.listener.host', '0.0.0.0'));
        $port = (int) ($this->option('port') !== null ? $this->option('port') : config('devices.listener.dicom_port', 2577));

        if ($port <= 0) {
            $this->error('DICOM listener port is disabled.');

            return self::FAILURE;
        }

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $error);
        if ($server === false) {
            throw new RuntimeException("Could not bind DICOM port {$host}:{$port}: {$error} ({$errno})");
        }

        stream_set_blocking($server, false);
        $serverId = (int) $server;
        $this->info(sprintf('DICOM C-STORE listener started on %s:%d (AE: %s)', $host, $port, config('devices.dicom.ae_title')));
        Log::info('DICOM listener started', ['host' => $host, 'port' => $port]);

        /** @var array<int, array{resource: resource, host: string, buffer: string, state: string, context_id: int, transfer_syntax: string, calling_ae: string, command: string, command_complete: bool, dataset: string, dataset_complete: bool}> $clients */
        $clients = [];

        while (true) {
            $read = [$server];
            foreach ($clients as $client) {
                $read[] = $client['resource'];
            }

            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, 1);

            if ($ready === false) {
                $this->error('stream_select failed, stopping DICOM listener.');

                return self::FAILURE;
            }

            foreach ($read as $stream) {
                if ((int) $stream === $serverId) {
                    $this->acceptClient($server, $clients);

                    continue;
                }

                $id = (int) $stream;
                if (! isset($clients[$id])) {
                    continue;
                }

                if (! $this->readClient($clients[$id])) {
                    unset($clients[$id]);
                }
            }
        }
    }

    /**
     * @param  resource  $server
     * @param  array<int, array{resource: resource, host: string, buffer: string, state: string, context_id: int, transfer_syntax: string, calling_ae: string, command: string, command_complete: bool, dataset: string, dataset_complete: bool}>  $clients
     */
    private function acceptClient($server, array &$clients): void
    {
        $client = @stream_socket_accept($server, 0, $peer);

        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        $id = (int) $client;
        $parts = explode(':', (string) $peer);
        $host = count($parts) > 2 ? implode(':', array_slice($parts, 0, -1)) : ($parts[0] ?? '');

        $clients[$id] = [
            'resource' => $client,
            'host' => $host,
            'buffer' => '',
            'state' => 'association',
            'context_id' => 0,
            'transfer_syntax' => DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN,
            'calling_ae' => '',
            'command' => '',
            'command_complete' => false,
            'dataset' => '',
            'dataset_complete' => false,
        ];

        $this->info("DICOM connection from {$peer}");
    }

    /**
     * @param  array{resource: resource, host: string, buffer: string, state: string, context_id: int, transfer_syntax: string, calling_ae: string, command: string, command_complete: bool, dataset: string, dataset_complete: bool}  $client
     */
    private function readClient(array &$client): bool
    {
        $chunk = @fread($client['resource'], 16384);

        if ($chunk === false) {
            return false;
        }

        $client['buffer'] .= $chunk;

        while (strlen($client['buffer']) >= 6) {
            $length = unpack('N', substr($client['buffer'], 2, 4))[1];
            if (strlen($client['buffer']) < 6 + $length) {
                break;
            }

            $pdu = substr($client['buffer'], 0, 6 + $length);
            $client['buffer'] = substr($client['buffer'], 6 + $length);

            if (! $this->handlePdu($client, $pdu)) {
                fclose($client['resource']);

                return false;
            }
        }

        if (feof($client['resource'])) {
            fclose($client['resource']);

            return false;
        }

        return true;
    }

    /**
     * @param  array{resource: resource, host: string, buffer: string, state: string, context_id: int, transfer_syntax: string, calling_ae: string, command: string, command_complete: bool, dataset: string, dataset_complete: bool}  $client
     */
    private function handlePdu(array &$client, string $pdu): bool
    {
        $type = ord($pdu[0]);

        if ($type === 0x01 && $client['state'] === 'association') {
            try {
                $accepted = $this->dicom->acceptAssociation($pdu);
                @fwrite($client['resource'], $accepted['response']);
                $client['state'] = 'data';
                $client['context_id'] = $accepted['context_id'];
                $client['transfer_syntax'] = $accepted['transfer_syntax'];
                $client['calling_ae'] = $accepted['calling_ae'];
                $this->info(sprintf('DICOM association accepted: AE=%s syntax=%s', $accepted['calling_ae'], $accepted['transfer_syntax']));
            } catch (Throwable $exception) {
                $this->error('DICOM association rejected: '.$exception->getMessage());
                Log::warning('DICOM association rejected', ['host' => $client['host'], 'error' => $exception->getMessage()]);

                return false;
            }

            return true;
        }

        if ($type === 0x04 && $client['state'] === 'data') {
            foreach ($this->pdvs($pdu) as $pdv) {
                $this->handlePdv($client, $pdv['context_id'], $pdv['control'], $pdv['data']);
            }

            return true;
        }

        if ($type === 0x05) {
            if (! $this->option('quiet-acks')) {
                @fwrite($client['resource'], $this->dicom->buildReleaseResponse());
            }

            return false;
        }

        if ($type === 0x07) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{resource: resource, host: string, buffer: string, state: string, context_id: int, transfer_syntax: string, calling_ae: string, command: string, command_complete: bool, dataset: string, dataset_complete: bool}  $client
     */
    private function handlePdv(array &$client, int $contextId, int $control, string $data): void
    {
        $last = ($control & 0x01) === 0x01;
        $kind = ($control >> 1) & 0x03;

        if ($kind === 0x00) {
            $client['command'] .= $data;
            if ($last) {
                $client['command_complete'] = true;
            }
        } elseif ($kind === 0x01) {
            $client['dataset'] .= $data;
            if ($last) {
                $client['dataset_complete'] = true;
            }
        }

        if (! $client['command_complete'] || ! $client['dataset_complete']) {
            return;
        }

        $command = DicomDataset::parse($client['command'], DicomDataset::EXPLICIT_VR_LITTLE_ENDIAN);
        $sopUid = DicomDataset::value($command, 0x0000, 0x1000) ?? 'DICOM-'.Str::upper(Str::random(12));
        $status = 0x0000;

        try {
            if ($this->dicom->isStoreRequest($command)) {
                $result = $this->dicom->ingest(
                    $client['dataset'],
                    $client['transfer_syntax'],
                    $client['calling_ae'] ?: $client['host'],
                    $command,
                );

                $this->info(sprintf(
                    'DICOM C-STORE #%d => %s (%s)',
                    $result['message']->id,
                    $result['message']->status,
                    $result['stored_path'],
                ));
            }
        } catch (Throwable $exception) {
            $status = 0xA700;
            $this->error('DICOM C-STORE import failed: '.$exception->getMessage());
            Log::error('DICOM C-STORE import failed', [
                'host' => $client['host'],
                'error' => $exception->getMessage(),
            ]);
        }

        if (! $this->option('quiet-acks')) {
            @fwrite($client['resource'], $this->dicom->buildStoreResponse($contextId, $command, $sopUid, $status));
        }

        $client['command'] = '';
        $client['dataset'] = '';
        $client['command_complete'] = false;
        $client['dataset_complete'] = false;
    }

    /** @return array<int, array{context_id: int, control: int, data: string}> */
    private function pdvs(string $pdu): array
    {
        $payload = substr($pdu, 6);
        $items = [];

        while (strlen($payload) >= 5) {
            $contextId = ord($payload[0]);
            // The 3-byte length covers the control byte plus the fragment, not the context ID.
            $length = unpack('N', "\x00".substr($payload, 1, 3))[1];
            if (strlen($payload) < 4 + $length) {
                break;
            }

            $value = substr($payload, 4, $length);
            $payload = substr($payload, 4 + $length);

            if ($value === '') {
                continue;
            }

            $items[] = [
                'context_id' => $contextId,
                'control' => ord($value[0]),
                'data' => substr($value, 1),
            ];
        }

        return $items;
    }
}
