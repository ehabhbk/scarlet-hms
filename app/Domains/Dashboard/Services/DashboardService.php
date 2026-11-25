<?php

namespace App\Domains\Dashboard\Services;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\Department;
use App\Domains\Identity\Models\User;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\QueueTicket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ?int $branchId = null): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfDay();
        $end = $today->endOfDay();
        $branchId = $branchId ?? $user->branch_id;

        $patientsQuery = Patient::query()->whereNull('deleted_at');
        $appointmentsQuery = Appointment::query()->whereDate('scheduled_at', $today);
        $encountersQuery = Encounter::query()->whereBetween('started_at', [$start, $end]);
        $queueQuery = QueueTicket::query()->whereDate('queue_date', $today);

        if ($branchId) {
            $appointmentsQuery->where('branch_id', $branchId);
            $encountersQuery->where('branch_id', $branchId);
            $queueQuery->where('branch_id', $branchId);
        }

        $queueCounts = (clone $queueQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $appointmentCounts = (clone $appointmentsQuery)
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $recentPatients = Patient::query()
            ->with('registrationBranch:id,name_ar,name_en')
            ->latest()
            ->limit(6)
            ->get();

        $todayAppointments = (clone $appointmentsQuery)
            ->with([
                'patient:id,mrn,first_name_ar,last_name_ar,first_name_en,last_name_en,date_of_birth,gender,phone',
                'doctor:id,name',
                'department:id,name_ar,name_en',
                'queueTicket',
            ])
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $weeklyRegistrations = $this->weeklyRegistrations($branchId);
        $departmentLoad = $this->departmentLoad($branchId, $start, $end);

        $openEncounters = Encounter::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->open()
            ->count();

        $myAppointments = Appointment::query()
            ->whereDate('scheduled_at', $today)
            ->where('doctor_id', $user->id)
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_COMPLETED])
            ->count();

        return [
            'generated_at' => now()->toIso8601String(),
            'branch_id' => $branchId,
            'stats' => [
                'total_patients' => (clone $patientsQuery)->count(),
                'new_patients_today' => (clone $patientsQuery)->whereBetween('created_at', [$start, $end])->count(),
                'appointments_today' => (clone $appointmentsQuery)->count(),
                'checked_in_today' => (int) ($appointmentCounts[Appointment::STATUS_CHECKED_IN] ?? 0),
                'completed_appointments' => (int) ($appointmentCounts[Appointment::STATUS_COMPLETED] ?? 0),
                'waiting_now' => (int) ($queueCounts[QueueTicket::STATUS_WAITING] ?? 0),
                'called_now' => (int) ($queueCounts[QueueTicket::STATUS_CALLED] ?? 0),
                'in_consultation' => (int) ($queueCounts[QueueTicket::STATUS_IN_PROGRESS] ?? 0),
                'open_encounters' => $openEncounters,
                'my_appointments_today' => $myAppointments,
            ],
            'queue_counts' => $queueCounts,
            'appointment_counts' => $appointmentCounts,
            'recent_patients' => $recentPatients,
            'today_appointments' => $todayAppointments,
            'weekly_registrations' => $weeklyRegistrations,
            'department_load' => $departmentLoad,
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function weeklyRegistrations(?int $branchId): array
    {
        $start = CarbonImmutable::today()->subDays(6)->startOfDay();
        $end = CarbonImmutable::today()->endOfDay();

        $registrations = Patient::query()
            ->when($branchId, fn ($q) => $q->where('registration_branch_id', $branchId))
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (Patient $patient) => $patient->created_at->toDateString());

        return collect(range(6, 0))->map(function (int $daysAgo) use ($registrations) {
            $date = CarbonImmutable::today()->subDays($daysAgo);

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'count' => (int) ($registrations[$date->toDateString()] ?? collect())->count(),
            ];
        })->all();
    }

    /**
     * @return array<int, array{department_id: int, name_ar: string, name_en: string, count: int}>
     */
    private function departmentLoad(?int $branchId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $counts = Encounter::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('started_at', [$start, $end])
            ->select('department_id', DB::raw('count(*) as aggregate'))
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        return Department::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('id', $counts->keys())
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn (Department $department) => [
                'department_id' => $department->id,
                'name_ar' => $department->name_ar,
                'name_en' => $department->name_en,
                'count' => (int) ($counts[$department->id] ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
