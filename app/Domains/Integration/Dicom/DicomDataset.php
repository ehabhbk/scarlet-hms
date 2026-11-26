<?php

namespace App\Domains\Integration\Dicom;

/**
 * Minimal DICOM dataset reader for C-STORE reports.
 *
 * Supports Implicit VR Little Endian and Explicit VR Little Endian, including
 * undefined-length sequences and item delimiters used by structured reports.
 */
class DicomDataset
{
    public const IMPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2';
    public const EXPLICIT_VR_LITTLE_ENDIAN = '1.2.840.10008.1.2.1';

    /**
     * @return array<int, array{group: int, element: int, vr: string, value: string}>
     */
    public static function parse(string $data, string $transferSyntax = self::IMPLICIT_VR_LITTLE_ENDIAN): array
    {
        $explicit = $transferSyntax !== self::IMPLICIT_VR_LITTLE_ENDIAN;

        return self::parseElements($data, $explicit, 0, strlen($data), 0);
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    public static function value(array $elements, int $group, int $element): ?string
    {
        foreach ($elements as $item) {
            if ($item['group'] === $group && $item['element'] === $element && $item['value'] !== '') {
                return $item['value'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     * @return array<int, string>
     */
    public static function textValues(array $elements, int $group = 0x0040, int $element = 0xA160): array
    {
        $values = [];
        foreach ($elements as $item) {
            if ($item['group'] === $group && $item['element'] === $element && $item['value'] !== '') {
                $values[] = $item['value'];
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    public static function unsignedShort(array $elements, int $group, int $element): ?int
    {
        $raw = self::rawValue($elements, $group, $element);

        return $raw === null || strlen($raw) < 2 ? null : unpack('v', substr($raw, 0, 2))[1];
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    private static function rawValue(array $elements, int $group, int $element): ?string
    {
        foreach ($elements as $item) {
            if ($item['group'] === $group && $item['element'] === $element) {
                return $item['value'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{group: int, element: int, vr: string, value: string}>
     */
    private static function parseElements(string $data, bool $explicit, int $offset, int $end, int $depth): array
    {
        $elements = [];

        while ($offset + 8 <= $end && $depth < 12) {
            $group = unpack('v', substr($data, $offset, 2))[1];
            $element = unpack('v', substr($data, $offset + 2, 2))[1];
            $offset += 4;

            if ($group === 0xFFFE && in_array($element, [0xE000, 0xE00D, 0xE0DD], true)) {
                $length = unpack('V', substr($data, $offset, 4))[1];
                $offset += 4;

                if ($element === 0xE000) {
                    if ($length === 0xFFFFFFFF) {
                        $itemEnd = self::findDelimiter($data, $offset, $end, 0xE00D);
                        $next = min($itemEnd + 8, $end);
                    } else {
                        $itemEnd = min($offset + $length, $end);
                        $next = $itemEnd;
                    }

                    $elements = array_merge($elements, self::parseElements($data, $explicit, $offset, $itemEnd, $depth + 1));
                    $offset = $next;
                } else {
                    $offset += $length;
                }

                continue;
            }

            if ($explicit) {
                $vr = substr($data, $offset, 2);
                $offset += 2;

                if (in_array($vr, ['OB', 'OW', 'OF', 'SQ', 'UT', 'UN'], true)) {
                    $offset += 2;
                    if ($offset + 4 > $end) {
                        break;
                    }
                    $length = unpack('V', substr($data, $offset, 4))[1];
                    $offset += 4;
                } else {
                    if ($offset + 2 > $end) {
                        break;
                    }
                    $length = unpack('v', substr($data, $offset, 2))[1];
                    $offset += 2;
                }
            } else {
                $vr = self::vrFor($group, $element);
                if ($offset + 4 > $end) {
                    break;
                }
                $length = unpack('V', substr($data, $offset, 4))[1];
                $offset += 4;
            }

            $undefined = $length === 0xFFFFFFFF;

            if ($undefined) {
                $delimiter = ($vr === 'SQ' || $group !== 0xFFFE) ? 0xE0DD : 0xE00D;
                $valueEnd = self::findDelimiter($data, $offset, $end, $delimiter);
                $next = min($valueEnd + 8, $end);
            } else {
                $valueEnd = min($offset + $length, $end);
                $next = $valueEnd;
            }

            if ($length < 0 || $valueEnd < $offset) {
                break;
            }

            $raw = substr($data, $offset, $valueEnd - $offset);

            if ($vr === 'SQ' || $undefined) {
                $elements = array_merge($elements, self::parseElements($raw, $explicit, 0, strlen($raw), $depth + 1));
            } else {
                $elements[] = [
                    'group' => $group,
                    'element' => $element,
                    'vr' => $vr,
                    'value' => self::decode($raw, $vr),
                ];
            }

            $offset = $next;
        }

        return $elements;
    }

    private static function findDelimiter(string $data, int $offset, int $end, int $element): int
    {
        for ($i = $offset; $i + 4 <= $end; $i += 2) {
            $group = unpack('v', substr($data, $i, 2))[1];
            $found = unpack('v', substr($data, $i + 2, 2))[1];

            if ($group === 0xFFFE && $found === $element) {
                return $i;
            }
        }

        return $end;
    }

    private static function decode(string $raw, string $vr): string
    {
        if ($vr === 'US') {
            return $raw;
        }

        return rtrim($raw, "\0 ");
    }

    private static function vrFor(int $group, int $element): string
    {
        return match (true) {
            $group === 0x0000 => 'US',
            in_array([$group, $element], [
                [0x0008, 0x0050], [0x0008, 0x0060], [0x0008, 0x1030], [0x0008, 0x103E],
                [0x0010, 0x0020], [0x0020, 0x000D], [0x0020, 0x000E],
                [0x0040, 0xA160], [0x0040, 0xA130],
            ], true) => 'LO',
            default => 'UN',
        };
    }
}
