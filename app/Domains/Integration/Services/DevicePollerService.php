<?php

namespace App\Domains\Integration\Services;

use App\Domains\Integration\Models\ClinicalDevice;
use App\Domains\Integration\Models\DevicePollLog;
use App\Domains\Integration\Parsers\UniversalParser;
use App\Domains\Laboratory\Models\LabDevice;
use App\Domains\Radiology\Models\RadiologyDevice;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Active (host-query) integration. Connects to a device as a TCP client,
 * optionally sends a query command (ENQ, HL7 QRY^A19, vendor command), reads
 * the reply frames and hands them to the normal ingest pipeline.
 *
 * It never writes results directly and never approves anything.
 */
class DevicePollerService
{
    private readonly DeviceFrameExtractor $frames;

    public function __construct(
        private readonly DeviceMessageIngestService $ingest,
        private readonly UniversalParser $parser,
    ) {
        $this->frames = new DeviceFrameExtractor();
    }

    /**
     * @param  array<string, string|int|null>  $context  order_number, study_code, patient_mrn for per-order queries
     * @return array{ok: bool, frames: int, imported: int, error: ?string, protocol: ?string, query_sent: ?string}
     */
    public function poll(Model $device, string $type, array $context = []): array
    {
        $outcome = [
            'ok' => false,
            'frames' => 0,
            'imported' => 0,
            'error' => null,
            'protocol' => null,
            'query_sent' => null,
        ];
        $type = in_array($type, ['lab', 'radiology', 'clinical'], true) ? $type : 'clinical';

        if (! filled($device->host) || ! $device->port) {
            $outcome['error'] = 'لم يُحدد عنوان الجهاز والمنفذ.';
            $this->record($device, $type, $outcome);

            return $outcome;
        }

        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $device->host, $device->port),
            $errno,
            $error,
            (int) config('devices.poller.connect_timeout', 3),
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            $outcome['error'] = $error !== '' ? $error : 'تعذر الاتصال بالجهاز';
            $this->record($device, $type, $outcome);

            return $outcome;
        }

        stream_set_blocking($socket, false);

        try {
            $query = $this->applyContext(
                $this->decodeQuery($device->poll_query ?? null, $device->poll_query_encoding ?? 'literal'),
                $context,
            );
            $outcome['query_sent'] = $query !== '' ? $query : null;
            if ($query !== '') {
                @fwrite($socket, $query);
            }

            $buffer = '';
            $deadline = microtime(true) + (int) config('devices.poller.read_timeout', 8);
            $lastData = microtime(true);
            $idle = max((float) config('devices.poller.idle_seconds', 0.6), 0.1);

            while (microtime(true) < $deadline) {
                $read = [$socket];
                $write = null;
                $except = null;
                $ready = @stream_select($read, $write, $except, 0, 200000);

                if ($ready === false) {
                    break;
                }

                if ($ready > 0) {
                    $chunk = @fread($socket, 8192);
                    if ($chunk === false || ($chunk === '' && feof($socket))) {
                        break;
                    }
                    if ($chunk !== '') {
                        $buffer .= $chunk;
                        $lastData = microtime(true);

                        foreach ($this->frames->extract($buffer) as $frame) {
                            $this->processFrame($frame, $device, $type, $outcome);
                        }
                    }
                } elseif ((microtime(true) - $lastData) >= $idle && $buffer === '') {
                    break;
                }
            }

            if (trim($buffer, "\x02\x03\x17\r\n") !== '') {
                $this->processFrame($buffer, $device, $type, $outcome);
            }

            $outcome['ok'] = true;
        } catch (Throwable $exception) {
            $outcome['error'] = $exception->getMessage();
        } finally {
            @fclose($socket);
        }

        $this->record($device, $type, $outcome);

        return $outcome;
    }

    private function processFrame(string $frame, Model $device, string $type, array &$outcome): void
    {
        $context = [
            'device_id' => $device->id,
            'device_code' => $device->code,
            'protocol' => $device->protocol && $device->protocol !== 'AUTO' ? $device->protocol : null,
            'remote_host' => $device->host,
            'remote_port' => $device->port,
        ];

        $message = match (true) {
            $device instanceof LabDevice && $type === 'lab' => $this->ingest->ingestLab($frame, $context),
            $device instanceof RadiologyDevice && $type === 'radiology' => $this->ingest->ingestRadiology($frame, $context),
            default => $this->ingest->ingestClinical($frame, $context),
        };

        $outcome['frames']++;
        $outcome['protocol'] = $message->protocol ?? $this->parser->detect($frame);

        if (in_array($message->status ?? null, ['imported', 'received'], true)) {
            $outcome['imported']++;
        }
    }

    /**
     * Replace safe placeholders in the decoded device query:
     * {order}, {order_number}, {study}, {study_code}, {mrn}, {patient_mrn},
     * {accession}. Braced with either one or two braces.
     *
     * @param  array<string, string|int|null>  $context
     */
    private function applyContext(string $query, array $context): string
    {
        if ($query === '' || $context === []) {
            return $query;
        }

        $values = [
            'order' => $context['order_number'] ?? $context['order'] ?? null,
            'order_number' => $context['order_number'] ?? $context['order'] ?? null,
            'study' => $context['study_code'] ?? $context['study'] ?? null,
            'study_code' => $context['study_code'] ?? $context['study'] ?? null,
            'mrn' => $context['patient_mrn'] ?? $context['mrn'] ?? null,
            'patient_mrn' => $context['patient_mrn'] ?? $context['mrn'] ?? null,
            'accession' => $context['order_number'] ?? $context['accession'] ?? null,
        ];

        return preg_replace_callback(
            '/\{\{?\s*(order_number|order|study_code|study|patient_mrn|mrn|accession)\s*\}?\}/i',
            static function (array $matches) use ($values): string {
                $value = $values[strtolower($matches[1])] ?? null;
                // Vendor queries are line/field based; never let an order number
                // inject HL7 delimiters or control characters.
                return $value === null ? $matches[0] : preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $value) ?? '';
            },
            $query,
        ) ?? $query;
    }

    private function decodeQuery(?string $query, ?string $encoding): string
    {
        if (! filled($query)) {
            return '';
        }

        $encoding = strtolower((string) ($encoding ?: 'literal'));

        return match ($encoding) {
            'hex' => (string) hex2bin(preg_replace('/[^0-9A-Fa-f]/', '', $query) ?: ''),
            'base64' => (string) base64_decode($query, true),
            default => stripcslashes($query),
        };
    }

    /** @param  array{ok: bool, frames: int, imported: int, error: ?string, protocol: ?string}  $outcome */
    private function record(Model $device, string $type, array $outcome): void
    {
        $device->forceFill([
            'last_poll_at' => now(),
            'last_error' => $outcome['error'],
        ])->save();

        if ($outcome['protocol']) {
            $device->forceFill(['protocol_detected' => $outcome['protocol']])->save();
        }

        DevicePollLog::query()->create([
            'device_type' => $type,
            'device_id' => $device->id,
            'status' => $outcome['error'] ? 'error' : ($outcome['frames'] ? 'ok' : 'empty'),
            'frames_count' => $outcome['frames'],
            'imported_count' => $outcome['imported'],
            'error' => $outcome['error'],
            'polled_at' => now(),
        ]);
    }
}
