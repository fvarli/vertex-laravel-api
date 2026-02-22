<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Report\ListReportRequest;
use App\Http\Requests\Api\V1\Report\StudentRetentionReportRequest;
use App\Http\Requests\Api\V1\Report\TrainerPerformanceReportRequest;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseController
{
    public function __construct(private readonly ReportService $reportService) {}

    public function appointments(ListReportRequest $request): JsonResponse
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);

        return $this->sendResponse(
            $this->reportService->appointments($workspaceId, $trainerId, $from, $to, $groupBy)
        );
    }

    public function students(ListReportRequest $request): JsonResponse
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);

        return $this->sendResponse(
            $this->reportService->students($workspaceId, $trainerId, $from, $to, $groupBy)
        );
    }

    public function programs(ListReportRequest $request): JsonResponse
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);

        return $this->sendResponse(
            $this->reportService->programs($workspaceId, $trainerId, $from, $to, $groupBy)
        );
    }

    public function reminders(ListReportRequest $request): JsonResponse
    {
        [$workspaceId, $trainerId, $from, $to, $groupBy] = $this->resolveContext($request);

        return $this->sendResponse(
            $this->reportService->reminders($workspaceId, $trainerId, $from, $to, $groupBy)
        );
    }

    public function trainerPerformance(TrainerPerformanceReportRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== 'owner_admin') {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $validated = $request->validated();

        $from = isset($validated['date_from'])
            ? CarbonImmutable::parse($validated['date_from'])->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
        $to = isset($validated['date_to'])
            ? CarbonImmutable::parse($validated['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return $this->sendResponse(
            $this->reportService->trainerPerformance($workspaceId, $from, $to)
        );
    }

    public function studentRetention(StudentRetentionReportRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');

        if ($workspaceRole !== 'owner_admin') {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        $validated = $request->validated();

        $from = isset($validated['date_from'])
            ? CarbonImmutable::parse($validated['date_from'])->startOfDay()
            : CarbonImmutable::now()->subDays(30)->startOfDay();
        $to = isset($validated['date_to'])
            ? CarbonImmutable::parse($validated['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return $this->sendResponse(
            $this->reportService->studentRetention($workspaceId, $from, $to)
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
        if ($workspaceRole !== 'owner_admin') {
            $trainerId = (int) $request->user()->id;
        } elseif (isset($validated['trainer_id'])) {
            $trainerId = (int) $validated['trainer_id'];
        }

        return [$workspaceId, $trainerId, $from, $to, $groupBy];
    }
}
