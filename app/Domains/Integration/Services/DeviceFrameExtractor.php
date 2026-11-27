<?php

namespace App\Domains\Integration\Services;

/**
 * Splits a raw TCP byte stream into complete device messages.
 *
 * Supports MLLP (VT/FS), ASTM (STX/ETX/EOT), newline-delimited JSON/XML and
 * legacy HL7/ASTM streams that are terminated only by a newline.
 */
class DeviceFrameExtractor
{
    /** @return array<int, string> */
    public function extract(string &$buffer): array
    {
        $frames = [];
        $buffer = ltrim($buffer, "\r\n");

        while ($buffer !== '') {
            if ($frame = $this->takeMllp($buffer)) {
                $frames[] = $frame;
                $buffer = ltrim($buffer, "\r\n");

                continue;
            }

            if ($frame = $this->takeAstm($buffer)) {
                $frames[] = $frame;
                $buffer = ltrim($buffer, "\r\n");

                continue;
            }

            if ($frame = $this->takeDelimitedDocument($buffer)) {
                $frames[] = $frame;
                $buffer = ltrim($buffer, "\r\n");

                continue;
            }

            break;
        }

        return $frames;
    }

    private function takeMllp(string &$buffer): ?string
    {
        $start = strpos($buffer, "\x0B");
        if ($start === false) {
            return null;
        }

        if ($start > 0) {
            $buffer = substr($buffer, $start);

            return $this->takeMllp($buffer);
        }

        $end = strpos($buffer, "\x1C", 1);
        if ($end === false) {
            return null;
        }

        $frame = substr($buffer, 1, $end - 1);
        $buffer = substr($buffer, $end + 1);
        $frame = str_replace("\x1C", '', $frame);

        return trim($frame) === '' ? null : $frame;
    }

    private function takeAstm(string &$buffer): ?string
    {
        $start = strpos($buffer, "\x02");
        if ($start === false) {
            return null;
        }

        if ($start > 0) {
            $buffer = substr($buffer, $start);

            return $this->takeAstm($buffer);
        }

        $end = strpbrk($buffer, "\x03\x17");
        if ($end === false) {
            return null;
        }

        $position = strpos($buffer, $end[0]);
        $frame = substr($buffer, 0, $position + 1);
        $buffer = substr($buffer, $position + 1);

        return trim($frame, "\x02\x03\x17\r\n") === '' ? null : $frame;
    }

    private function takeDelimitedDocument(string &$buffer): ?string
    {
        $trimmed = ltrim($buffer, "\x02");

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $depth = 0;
            $inString = false;
            $escaped = false;

            for ($i = 0; $i < strlen($trimmed); $i++) {
                $char = $trimmed[$i];

                if ($escaped) {
                    $escaped = false;

                    continue;
                }
                if ($char === '\\' && $inString) {
                    $escaped = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = ! $inString;

                    continue;
                }
                if ($inString) {
                    continue;
                }
                if ($char === '{' || $char === '[') {
                    $depth++;

                    continue;
                }
                if ($char === '}' || $char === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $frame = substr($trimmed, 0, $i + 1);
                        $buffer = substr($buffer, strlen($frame) + (strlen($buffer) - strlen($trimmed)));

                        return $frame;
                    }
                }
            }

            return null;
        }

        if (str_contains($trimmed, '<') && preg_match('/<\/[A-Za-z0-9:_-]+\s*>\s*$/', $trimmed)) {
            $frame = $trimmed;
            $buffer = '';

            return $frame;
        }

        if (str_ends_with($buffer, "\r") || str_ends_with($buffer, "\n")) {
            if (str_contains($buffer, 'MSH|') || preg_match('/(?:^|[\r\n])[HPO RL]\b/', $buffer) || str_contains($buffer, '=')) {
                $frame = $buffer;
                $buffer = '';

                return trim($frame);
            }
        }

        return null;
    }
}
