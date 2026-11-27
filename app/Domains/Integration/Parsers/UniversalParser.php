<?php

namespace App\Domains\Integration\Parsers;

/**
 * Detects and normalises the common wire formats used by hospital devices.
 *
 * The output is always the same canonical shape, so lab, radiology and bedside
 * devices can share one ingestion pipeline:
 *
 * protocol, message_id, patient_mrn, order_number, tests[].{code,name,parameters[].{code,name,value,unit,flag,reference_range}}
 *
 * Nothing in this class writes to the database or approves a result.
 */
class UniversalParser
{
    private readonly Hl7Parser $hl7;

    private readonly AstmParser $astm;

    public function __construct()
    {
        $this->hl7 = new Hl7Parser();
        $this->astm = new AstmParser();
    }

    public function detect(string $raw): string
    {
        $trimmed = ltrim($raw, "\x0B\x02 \t\r\n");

        if (str_contains($raw, 'MSH|') || str_contains($raw, 'MSH^~\\&')) {
            return 'HL7';
        }

        if (preg_match('/(?:^|[\r\n])H\|/', $trimmed) || str_contains($raw, "\x02H|")) {
            return 'ASTM';
        }

        if (str_contains($raw, '<')) {
            return str_contains(strtolower($raw), 'hl7')
                || str_contains(strtolower($raw), 'cda')
                || str_contains(strtolower($raw), 'clinicaldocument')
                ? 'CDA'
                : 'XML';
        }

        $first = ltrim($trimmed, "\x02\x03");

        if ($first !== '' && in_array($first[0], ['{', '['], true)) {
            return str_contains($raw, '"resourceType"') ? 'FHIR' : 'JSON';
        }

        if (preg_match('/(?:^|[\r\n])\s*[A-Za-z_][A-Za-z0-9_]*\s*[:=]/', $trimmed)) {
            return 'KEY_VALUE';
        }

        if (substr_count($trimmed, ',') >= 2 || substr_count($trimmed, "\t") >= 2) {
            return 'CSV';
        }

        return 'PLAIN';
    }

    /** @return array<string, mixed> */
    public function parse(string $raw, ?string $protocol = null): array
    {
        $protocol = strtoupper(trim((string) ($protocol ?: $this->detect($raw))));
        if ($protocol === 'AUTO' || $protocol === '') {
            $protocol = $this->detect($raw);
        }

        return match ($protocol) {
            'HL7' => $this->hl7->parse($raw),
            'ASTM' => $this->astm->parse($raw),
            'FHIR' => $this->parseFhir($raw),
            'JSON' => $this->parseJson($raw),
            'XML', 'CDA' => $this->parseXml($raw, $protocol),
            'CSV' => $this->parseDelimited($raw, ','),
            'KEY_VALUE' => $this->parseKeyValue($raw),
            default => $this->parsePlain($raw),
        };
    }

    /** @return array<string, mixed> */
    private function parseFhir(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $this->emptyResult('FHIR');
        }

        $resources = ($decoded['resourceType'] ?? null) === 'Bundle'
            ? collect($decoded['entry'] ?? [])->pluck('resource')->filter()->all()
            : [$decoded];

        $tests = [];
        $messageId = null;
        $mrn = null;
        $orderNumber = null;

        foreach ($resources as $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $type = (string) ($resource['resourceType'] ?? '');
            $messageId = $messageId ?: ($resource['id'] ?? null);

            if ($type === 'Patient') {
                $mrn = $mrn ?: $this->fhirMrn($resource);
                continue;
            }

            if ($type === 'ServiceRequest') {
                $orderNumber = $orderNumber ?: ($resource['identifier'][0]['value'] ?? null);
                continue;
            }

            if ($type !== 'Observation') {
                continue;
            }

            $mrn = $mrn ?: $this->fhirMrn($resource);
            $orderNumber = $orderNumber ?: ($resource['basedOn'][0]['identifier']['value'] ?? null);
            $code = $this->firstCoding($resource['code'] ?? []);
            $value = $resource['valueQuantity'] ?? null;

            if (is_array($resource['component'] ?? null)) {
                $parameters = [];
                foreach ($resource['component'] as $component) {
                    $quantity = $component['valueQuantity'] ?? [];
                    $parameterCode = $this->firstCoding($component['code'] ?? []);
                    $parameters[] = [
                        'code' => $parameterCode,
                        'name' => $component['code']['text'] ?? $parameterCode,
                        'value' => $quantity['value'] ?? ($component['valueString'] ?? null),
                        'unit' => $quantity['unit'] ?? null,
                        'flag' => $component['interpretation'][0]['coding'][0]['code'] ?? null,
                        'reference_range' => $quantity['referenceRange'][0]['text'] ?? null,
                    ];
                }
            } else {
                $parameters = [[
                    'code' => $code,
                    'name' => $resource['code']['text'] ?? $code,
                    'value' => is_array($value) ? ($value['value'] ?? ($resource['valueString'] ?? null)) : ($resource['valueString'] ?? null),
                    'unit' => is_array($value) ? ($value['unit'] ?? null) : null,
                    'flag' => $resource['interpretation'][0]['coding'][0]['code'] ?? null,
                    'reference_range' => is_array($value) ? ($value['referenceRange'][0]['text'] ?? null) : null,
                ]];
            }

            $tests[] = [
                'code' => $code,
                'name' => $resource['code']['text'] ?? $code,
                'parameters' => $parameters,
            ];
        }

        return [
            'protocol' => 'FHIR',
            'message_id' => $messageId,
            'message_type' => 'Observation',
            'patient_mrn' => $mrn,
            'order_number' => $orderNumber,
            'tests' => $tests,
        ];
    }

    /** @return array<string, mixed> */
    private function parseJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $this->emptyResult('JSON');
        }

        $rows = isset($decoded[0]) ? $decoded : [$decoded];
        $tests = [];
        $mrn = null;
        $orderNumber = null;
        $messageId = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $messageId = $messageId ?: ($row['message_id'] ?? $row['id'] ?? null);
            $mrn = $mrn ?: ($row['patient_mrn'] ?? $row['mrn'] ?? $row['patient']['mrn'] ?? null);
            $orderNumber = $orderNumber ?: ($row['sample'] ?? $row['sample_no'] ?? $row['accession'] ?? $row['order_number'] ?? null);

            $rawTests = $row['tests'] ?? (isset($row['test']) ? [[
                'code' => $row['test'],
                'name' => $row['test_name'] ?? null,
                'values' => $row['values'] ?? $row['results'] ?? [],
            ]] : []);

            foreach ($rawTests as $test) {
                if (! is_array($test)) {
                    continue;
                }

                $parameters = [];
                $values = $test['values'] ?? $test['results'] ?? $test['parameters'] ?? [];

                if (is_array($values) && array_is_list($values)) {
                    foreach ($values as $value) {
                        if (! is_array($value)) {
                            continue;
                        }
                        $parameters[] = [
                            'code' => $value['code'] ?? $value['parameter'] ?? null,
                            'name' => $value['name'] ?? null,
                            'value' => $value['value'] ?? null,
                            'unit' => $value['unit'] ?? null,
                            'flag' => $value['flag'] ?? null,
                            'reference_range' => $value['reference_range'] ?? null,
                        ];
                    }
                } elseif (is_array($values)) {
                    foreach ($values as $code => $value) {
                        $parameters[] = [
                            'code' => (string) $code,
                            'name' => null,
                            'value' => is_array($value) ? ($value['value'] ?? null) : $value,
                            'unit' => is_array($value) ? ($value['unit'] ?? null) : null,
                            'flag' => is_array($value) ? ($value['flag'] ?? null) : null,
                            'reference_range' => is_array($value) ? ($value['reference_range'] ?? null) : null,
                        ];
                    }
                }

                $tests[] = [
                    'code' => $test['code'] ?? $test['test'] ?? null,
                    'name' => $test['name'] ?? null,
                    'parameters' => $parameters,
                ];
            }
        }

        return [
            'protocol' => 'JSON',
            'message_id' => $messageId,
            'message_type' => 'RESULT',
            'patient_mrn' => $mrn,
            'order_number' => $orderNumber,
            'tests' => $tests,
        ];
    }

    /** @return array<string, mixed> */
    private function parseXml(string $raw, string $protocol): array
    {
        $sanitised = preg_replace('/<\?xml.*?\?>/is', '', $raw) ?? $raw;
        preg_match_all('/<(?:[A-Za-z0-9]+:)?(?:code|testCode|observationCode)\b[^>]*\b(?:code|value)="([^"]+)"/i', $sanitised, $codeMatches);
        preg_match_all('/<(?:[A-Za-z0-9]+:)?value\b[^>]*\bvalue="([^"]+)"[^>]*>/i', $sanitised, $valueMatches);
        preg_match('/<(?:[A-Za-z0-9]+:)?(?:id|identifier)\b[^>]*\b(?:root|value)="([^"]+)"/i', $sanitised, $idMatch);

        $codes = $codeMatches[1] ?? [];
        $values = $valueMatches[1] ?? [];
        $parameters = [];

        foreach ($codes as $index => $code) {
            if (in_array(strtolower((string) $code), [' Observation', 'result'], true)) {
                continue;
            }
            $parameters[] = [
                'code' => $code,
                'name' => null,
                'value' => $values[$index] ?? null,
                'unit' => null,
                'flag' => null,
                'reference_range' => null,
            ];
        }

        $orderNumber = null;
        if (preg_match('/(?:sample|accession|specimen)["\s:=<>]*([\w-]+)/i', $raw, $match)) {
            $orderNumber = $match[1] ?? null;
        }

        return [
            'protocol' => $protocol,
            'message_id' => $idMatch[1] ?? null,
            'message_type' => $protocol,
            'patient_mrn' => null,
            'order_number' => $orderNumber,
            'tests' => $parameters === [] ? [] : [[
                'code' => $codes[0] ?? null,
                'name' => null,
                'parameters' => $parameters,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function parseDelimited(string $raw, string $delimiter): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $rows = array_values(array_filter(array_map(
            fn (string $line): array => str_getcsv($line, $delimiter),
            $lines,
        ), fn (array $row): bool => count(array_filter($row, fn ($cell) => filled($cell))) > 0));

        if ($rows === []) {
            return $this->emptyResult('CSV');
        }

        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($rows));
        $parameters = [];
        $orderNumber = null;
        $mrn = null;

        foreach ($rows as $row) {
            $record = array_combine($header, array_pad($row, count($header), null)) ?: [];
            $orderNumber = $orderNumber ?: ($record['sample'] ?? $record['accession'] ?? $record['order_number'] ?? null);
            $mrn = $mrn ?: ($record['mrn'] ?? $record['patient_mrn'] ?? null);
            $parameters[] = [
                'code' => $record['code'] ?? $record['parameter'] ?? null,
                'name' => $record['name'] ?? null,
                'value' => $record['value'] ?? null,
                'unit' => $record['unit'] ?? null,
                'flag' => $record['flag'] ?? null,
                'reference_range' => $record['range'] ?? $record['reference_range'] ?? null,
            ];
        }

        return [
            'protocol' => 'CSV',
            'message_id' => null,
            'message_type' => 'RESULT',
            'patient_mrn' => $mrn,
            'order_number' => $orderNumber,
            'tests' => $parameters === [] ? [] : [[
                'code' => 'RESULT',
                'name' => null,
                'parameters' => $parameters,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function parseKeyValue(string $raw): array
    {
        $pairs = [];
        foreach (preg_split('/\r\n|\r|\n|;/', $raw) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z_][\w\s.-]*)\s*[:=]\s*(.*?)\s*$/', $line, $match)) {
                $pairs[strtolower(str_replace(' ', '_', $match[1]))] = $match[2];
            }
        }

        $values = [];
        foreach ($pairs as $key => $value) {
            if (in_array($key, ['sample', 'sample_no', 'accession', 'order_number', 'mrn', 'patient_mrn', 'test', 'code'], true)) {
                continue;
            }
            if ($value !== '') {
                $values[] = [
                    'code' => strtoupper($key),
                    'name' => null,
                    'value' => $value,
                    'unit' => null,
                    'flag' => null,
                    'reference_range' => null,
                ];
            }
        }

        return [
            'protocol' => 'KEY_VALUE',
            'message_id' => $pairs['id'] ?? null,
            'message_type' => 'RESULT',
            'patient_mrn' => $pairs['patient_mrn'] ?? $pairs['mrn'] ?? null,
            'order_number' => $pairs['sample'] ?? $pairs['sample_no'] ?? $pairs['accession'] ?? $pairs['order_number'] ?? null,
            'tests' => [['code' => strtoupper((string) ($pairs['test'] ?? 'RESULT')), 'name' => null, 'parameters' => $values]],
        ];
    }

    /** @return array<string, mixed> */
    private function parsePlain(string $raw): array
    {
        $merged = $this->parseKeyValue($raw);
        if ($merged['tests'][0]['parameters'] ?? [] !== []) {
            return $merged;
        }

        $parameters = [];
        foreach (preg_split('/\r\n|\r|\n|;|,/', $raw) ?: [] as $part) {
            if (preg_match('/\b([A-Za-z][A-Za-z0-9_-]{1,15})\b[\s:=]+([-+]?\d+(?:\.\d+)?)/', $part, $match)) {
                $parameters[] = [
                    'code' => strtoupper($match[1]),
                    'name' => null,
                    'value' => $match[2],
                    'unit' => null,
                    'flag' => null,
                    'reference_range' => null,
                ];
            }
        }

        $orderNumber = null;
        if (preg_match('/\b(?:sample|specimen|accession)\s*(?:no\.?|number)?\s*[:#=]?\s*(\d+)/i', $raw, $match)) {
            $orderNumber = $match[1];
        }

        return [
            'protocol' => 'PLAIN',
            'message_id' => null,
            'message_type' => 'RESULT',
            'patient_mrn' => null,
            'order_number' => $orderNumber,
            'tests' => $parameters === [] ? [] : [[
                'code' => 'RESULT',
                'name' => null,
                'parameters' => $parameters,
            ]],
        ];
    }

    /** @param  array<string, mixed>  $resource */
    private function fhirMrn(array $resource): ?string
    {
        $identifier = $resource['identifier'][0] ?? ($resource['patient']['identifier'][0] ?? null);

        return is_array($identifier) ? ($identifier['value'] ?? null) : null;
    }

    /** @param  array<string, mixed>|mixed  $coding */
    private function firstCoding(mixed $coding): ?string
    {
        if (! is_array($coding)) {
            return null;
        }

        return $coding['coding'][0]['code'] ?? ($coding['text'] ?? null);
    }

    /** @return array<string, mixed> */
    private function emptyResult(string $protocol): array
    {
        return [
            'protocol' => $protocol,
            'message_id' => null,
            'message_type' => null,
            'patient_mrn' => null,
            'order_number' => null,
            'tests' => [],
        ];
    }
}
