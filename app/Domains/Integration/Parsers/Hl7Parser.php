<?php

namespace App\Domains\Integration\Parsers;

class Hl7Parser
{
    /**
     * Parse an HL7 v2 ORU message, with or without MLLP framing.
     *
     * @return array{message_id: ?string, message_type: ?string, patient_mrn: ?string, order_number: ?string, tests: array<int, array<string, mixed>>}
     */
    public function parse(string $raw): array
    {
        $raw = preg_replace('/\x0B|\x1C/', '', $raw) ?? $raw;
        $segments = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];

        $messageId = null;
        $messageType = null;
        $sendingApplication = null;
        $sendingFacility = null;
        $patientMrn = null;
        $tests = [];
        $currentTest = null;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $parts = explode('|', $segment);
            $type = array_shift($parts);

            if ($type === 'MSH') {
                $messageType = $this->component($parts[7] ?? null);
                $messageId = $this->component($parts[8] ?? null);
                $sendingApplication = $this->component($parts[1] ?? null);
                $sendingFacility = $this->component($parts[2] ?? null);
                continue;
            }

            if ($type === 'PID') {
                $patientMrn = $this->component($parts[2] ?? null) ?: $this->component($parts[1] ?? null);
                continue;
            }

            if ($type === 'OBR') {
                if ($currentTest !== null) {
                    $tests[] = $currentTest;
                }

                $service = $parts[3] ?? null;
                $currentTest = [
                    'order_number' => $this->component($parts[1] ?? null) ?: $this->component($parts[2] ?? null),
                    'code' => $this->component($service),
                    'name' => $this->component($service, 2),
                    'parameters' => [],
                ];
                continue;
            }

            if ($type === 'OBX' && $currentTest !== null) {
                $identifier = $parts[2] ?? null;
                $currentTest['parameters'][] = [
                    'code' => $this->component($identifier),
                    'name' => $this->component($identifier, 2),
                    'value' => $parts[4] ?? null,
                    'unit' => $parts[5] ?? null,
                    'reference_range' => $parts[6] ?? null,
                    'flag' => $parts[7] ?? null,
                ];
            }
        }

        if ($currentTest !== null) {
            $tests[] = $currentTest;
        }

        return [
            'message_id' => $messageId,
            'message_type' => $messageType,
            'sending_application' => $sendingApplication,
            'sending_facility' => $sendingFacility,
            'patient_mrn' => $patientMrn,
            'order_number' => $tests[0]['order_number'] ?? null,
            'tests' => $tests,
        ];
    }

    private function component(?string $field, int $index = 1): ?string
    {
        if ($field === null || $field === '') {
            return null;
        }

        $parts = explode('^', $field);
        $value = $parts[$index - 1] ?? null;

        return $value === null || $value === '' ? null : $value;
    }
}
