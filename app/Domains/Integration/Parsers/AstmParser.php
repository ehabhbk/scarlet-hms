<?php

namespace App\Domains\Integration\Parsers;

class AstmParser
{
    /**
     * Parse ASTM E1381/E1394 records, optionally wrapped in frames.
     *
     * @return array{message_id: ?string, message_type: ?string, patient_mrn: ?string, order_number: ?string, tests: array<int, array<string, mixed>>}
     */
    public function parse(string $raw): array
    {
        $records = $this->extractRecords($raw);

        $messageId = null;
        $sendingFacility = null;
        $patientMrn = null;
        $tests = [];
        $currentTest = null;

        foreach ($records as $record) {
            $parts = explode('|', $record);
            $type = array_shift($parts);

            if ($type === 'H') {
                $messageId = collect($parts)->reverse()->first(fn ($value) => filled($value));
                $sender = collect($parts)->first(fn ($value) => filled($value) && str_contains((string) $value, '^'));
                $sendingFacility = is_string($sender) ? explode('^', $sender)[0] : ($parts[4] ?? null);
                continue;
            }

            if ($type === 'P') {
                $patientMrn = $parts[3] ?? $parts[2] ?? null;
                continue;
            }

            if ($type === 'O') {
                if ($currentTest !== null) {
                    $tests[] = $currentTest;
                }

                $specimen = $parts[1] ?? null;
                $currentTest = [
                    'order_number' => $specimen,
                    'code' => $parts[3] ?? $parts[4] ?? null,
                    'name' => null,
                    'parameters' => [],
                ];
                continue;
            }

            if ($type === 'R' && $currentTest !== null) {
                $identifier = $parts[1] ?? null;
                $code = $identifier;
                $name = null;
                if ($identifier !== null && str_contains($identifier, '^')) {
                    [$code, $name] = array_pad(explode('^', $identifier, 2), 2, null);
                }

                $currentTest['parameters'][] = [
                    'code' => $code,
                    'name' => $name,
                    'value' => $parts[2] ?? null,
                    'unit' => $parts[3] ?? null,
                    'reference_range' => $parts[4] ?? null,
                    'flag' => $parts[5] ?? null,
                ];
            }
        }

        if ($currentTest !== null) {
            $tests[] = $currentTest;
        }

        return [
            'message_id' => $messageId,
            'message_type' => 'ASTM',
            'sending_application' => $sendingFacility,
            'sending_facility' => $sendingFacility,
            'patient_mrn' => $patientMrn,
            'order_number' => $tests[0]['order_number'] ?? null,
            'tests' => $tests,
        ];
    }

    /** @return array<int, string> */
    private function extractRecords(string $raw): array
    {
        $records = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, "\x02")) {
                $line = substr($line, 1);
            }

            $end = strpbrk($line, "\x03\x17");
            if ($end !== false) {
                $line = substr($line, 0, strpos($line, $end[0]));
            }

            $records[] = $line;
        }

        return $records;
    }
}
