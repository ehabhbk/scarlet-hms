<?php

namespace App\Console\Commands;

use App\Domains\Integration\Models\ClinicalDevice;
use App\Domains\Integration\Services\DevicePollerService;
use App\Domains\Laboratory\Models\LabDevice;
use App\Domains\Radiology\Models\RadiologyDevice;
use Illuminate\Console\Command;
use Throwable;

class PollDevices extends Command
{
    protected $signature = 'devices:poll
        {--device= : Poll one device by its code}
        {--type= : lab, radiology or clinical}
        {--daemon : Keep polling forever using each device interval}
        {--interval= : Override the polling interval in seconds}';

    protected $description = 'Actively query configured devices and import their replies for human review';

    public function __construct(private readonly DevicePollerService $poller)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $type = strtolower((string) ($this->option('type') ?: ''));

        do {
            $count = $this->runOnce((string) $this->option('device'), $type);

            if (! $this->option('daemon')) {
                return $count >= 0 ? self::SUCCESS : self::FAILURE;
            }

            $interval = (int) ($this->option('interval') ?: config('devices.poller.daemon_interval', 30));
            sleep(max($interval, 5));
        } while (true);
    }

    private function runOnce(string $code, string $type): int
    {
        $targets = [];

        foreach ([
            'lab' => LabDevice::class,
            'radiology' => RadiologyDevice::class,
            'clinical' => ClinicalDevice::class,
        ] as $kind => $model) {
            if ($type !== '' && $type !== $kind) {
                continue;
            }

            $query = $model::query();
            if ($code !== '') {
                $query->where('code', $code);
            } else {
                $query->where('poll_enabled', true)->where('status', 'active');
            }

            foreach ($query->get() as $device) {
                $targets[] = ['kind' => $kind, 'device' => $device];
            }
        }

        if ($targets === []) {
            $this->warn('No pollable devices found. Enable poll_enabled on a device first.');

            return 0;
        }

        $failed = 0;
        foreach ($targets as $target) {
            /** @var \Illuminate\Database\Eloquent\Model $device */
            $device = $target['device'];

            try {
                $result = $this->poller->poll($device, $target['kind']);
                $message = sprintf(
                    '[%s] %s: %s | frames=%d imported=%d',
                    $target['kind'],
                    $device->code,
                    $result['ok'] ? 'OK' : 'FAIL',
                    $result['frames'],
                    $result['imported'],
                );

                $result['ok'] ? $this->info($message) : $this->error($message.' | '.$result['error']);

                if (! $result['ok']) {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error(sprintf('[%s] %s: %s', $target['kind'], $device->code, $exception->getMessage()));
            }
        }

        return $failed;
    }
}
