<?php

namespace App\Domains\Integration\Services;

class DeviceConnectionTester
{
    /**
     * Attempt a short TCP connection to a configured device endpoint.
     *
     * @return array{ok: bool, message: string}
     */
    public function test(?string $host, ?int $port): array
    {
        if (! $host || ! $port) {
            return ['ok' => false, 'message' => 'لم يُحدد عنوان IP والمنفذ لهذا الجهاز.'];
        }

        $errno = 0;
        $error = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $error,
            2,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            $reason = $error !== '' ? $error : 'لا يستجيب الجهاز';

            return [
                'ok' => false,
                'message' => sprintf('تعذر الاتصال بالجهاز على %s:%d — %s', $host, $port, $reason),
            ];
        }

        fclose($socket);

        return ['ok' => true, 'message' => 'اتصال TCP ناجح مع الجهاز.'];
    }
}
