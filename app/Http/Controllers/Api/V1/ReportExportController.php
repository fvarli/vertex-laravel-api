<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Report\ListReportRequest;
use App\Http\Requests\Api\V1\Report\TrainerPerformanceReportRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportExportController extends BaseController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportExportService $exportService,
    ) {}

    public function appointments(ListReportRequest $request)
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);
        $data = $this->reportService->appointments($workspaceId, $trainerId, $from, $to, $groupBy);
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportService->toPdf('exports.report-appointments', [
                'report' => $data,
                'title' => 'Appointments Report',
            ], 'appointments-report.pdf');
        }

        return $this->exportService->toCsv(
            $this->exportService->flattenAppointments($data),
            $this->exportService->appointmentColumns(),
            'appointments-report.csv',
        );
    }

    public function students(ListReportRequest $request)
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);
        $data = $this->reportService->students($workspaceId, $trainerId, $from, $to, $groupBy);
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportService->toPdf('exports.report-students', [
                'report' => $data,
                'title' => 'Students Report',
            ], 'students-report.pdf');
        }

        return $this->exportService->toCsv(
            $this->exportService->flattenStudents($data),
            $this->exportService->studentColumns(),
            'students-report.csv',
        );
    }

    public function programs(ListReportRequest $request)
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);
        $data = $this->reportService->programs($workspaceId, $trainerId, $from, $to, $groupBy);
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportService->toPdf('exports.report-programs', [
                'report' => $data,
                'title' => 'Programs Report',
            ], 'programs-report.pdf');
        }

        return $this->exportService->toCsv(
            $this->exportService->flattenPrograms($data),
            $this->exportService->programColumns(),
            'programs-report.csv',
        );
    }

    public function reminders(ListReportRequest $request)
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);
        $data = $this->reportService->reminders($workspaceId, $trainerId, $from, $to, $groupBy);
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportService->toPdf('exports.report-reminders', [
                'report' => $data,
                'title' => 'Reminders Report',
            ], 'reminders-report.pdf');
        }

        return $this->exportService->toCsv(
            $this->exportService->flattenReminders($data),
            $this->exportService->reminderColumns(),
            'reminders-report.csv',
        );
    }

    public function trainerPerformance(TrainerPerformanceReportRequest $request)
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $validated = $request->validated();
        $from = isset($validated['date_from'])
            ? CarbonImmutable::parse($validated['date_from'])->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
        $to = isset($validated['date_to'])
            ? CarbonImmutable::parse($validated['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $data = $this->reportService->trainerPerformance($workspaceId, $from, $to);
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            return $this->exportService->toPdf('exports.report-trainer-performance', [
                'report' => $data,
                'title' => 'Trainer Performance Report',
            ], 'trainer-performance-report.pdf');
        }

        return $this->exportService->toCsv(
            $this->exportService->flattenTrainerPerformance($data),
            $this->exportService->trainerPerformanceColumns(),
            'trainer-performance-report.csv',
        );
    }

    private function resolveContext(Request $request): array
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $validated = $request->validated();

        $from = isset($validated['date_from'])
            ? CarbonImmutable::parse($validated['date_from'])->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
        $to = isset($validated['date_to'])
            ? CarbonImmutable::parse($validated['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $groupBy = (string) ($validated['group_by'] ?? 'day');

        $trainerId = null;
        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
            $trainerId = (int) $request->user()->id;
        } elseif (isset($validated['trainer_id'])) {
            $trainerId = (int) $validated['trainer_id'];
        }

        return [$workspaceId, $trainerId, $from, $to, $groupBy];
    }
}
