<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Dashboard\Services\DashboardService;
use App\Support\Http\ApiResponse;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function commissions(Request $request): JsonResponse
    {
        $rows = DB::table('invoices')
            ->join('encounters', 'encounters.id', '=', 'invoices.encounter_id')
            ->join('users', 'users.id', '=', 'encounters.doctor_id')
            ->select('users.id', 'users.name', DB::raw('SUM(invoices.paid) as revenue'), DB::raw('COUNT(invoices.id) as invoices'))
            ->where('invoices.status', '!=', 'cancelled')
            ->groupBy('users.id', 'users.name')
            ->get();

        return ApiResponse::success($rows);
    }

    public function export(Request $request)
    {
        $format = $request->string('format', 'csv')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $summary = $this->dashboardService->summary($request->user(), $branchId);

        $appointmentCounts = DB::table('appointments')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->when($request->string('date')->toString(), fn ($q, $d) => $q->whereDate('scheduled_at', $d))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $rows = [];
        $rows[] = ['Scarlet HMS Report'];
        $rows[] = ['Generated at', now()->toDateTimeString()];
        $rows[] = [];
        $rows[] = ['Metric', 'Value'];
        $rows[] = ['Total patients', $summary['stats']['total_patients'] ?? 0];
        $rows[] = ['New patients today', $summary['stats']['new_patients_today'] ?? 0];
        $rows[] = ['Appointments today', $summary['stats']['appointments_today'] ?? 0];
        $rows[] = ['Checked in today', $summary['stats']['checked_in_today'] ?? 0];
        $rows[] = ['Completed appointments', $summary['stats']['completed_appointments'] ?? 0];
        $rows[] = ['Waiting now', $summary['stats']['waiting_now'] ?? 0];
        $rows[] = ['In consultation', $summary['stats']['in_consultation'] ?? 0];
        $rows[] = ['Open encounters', $summary['stats']['open_encounters'] ?? 0];
        $rows[] = [];
        $rows[] = ['Appointment status', 'Count'];
        foreach ($appointmentCounts as $status => $count) {
            $rows[] = [$status, $count];
        }
        $rows[] = [];
        $rows[] = ['Department', 'Patient count'];
        foreach ($summary['department_load'] as $row) {
            $rows[] = [$row['name_ar'].' / '.$row['name_en'], $row['count']];
        }

        if ($format === 'pdf') {
            $html = '<h1>Scarlet HMS Report</h1><table border="1" cellpadding="4"><tr><th>Metric</th><th>Value</th></tr>';
            foreach (array_slice($rows, 3, 9) as $row) {
                if (count($row) === 2) {
                    $html .= '<tr><td>'.e($row[0]).'</td><td>'.e($row[1]).'</td></tr>';
                }
            }
            $html .= '</table>';

            $pdf = Pdf::loadHTML($html);

            return $pdf->download('scarlet-report-'.now()->format('Y-m-d').'.pdf');
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row))."\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="scarlet-report-'.now()->format('Y-m-d').'.csv"',
        ]);
    }
}
