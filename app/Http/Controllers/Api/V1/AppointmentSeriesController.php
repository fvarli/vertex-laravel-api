<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\AppointmentSeries\ListAppointmentSeriesRequest;
use App\Http\Requests\Api\V1\AppointmentSeries\StoreAppointmentSeriesRequest;
use App\Http\Requests\Api\V1\AppointmentSeries\UpdateAppointmentSeriesRequest;
use App\Http\Requests\Api\V1\AppointmentSeries\UpdateAppointmentSeriesStatusRequest;
use App\Http\Resources\AppointmentSeriesResource;
use App\Models\AppointmentSeries;
use App\Models\Student;
use App\Models\User;
use App\Services\AppointmentSeriesService;
use App\Services\DomainAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AppointmentSeriesController extends BaseController
{
    private const AUDIT_FIELDS = ['student_id', 'trainer_user_id', 'title', 'location', 'recurrence_rule', 'start_date', 'starts_at_time', 'ends_at_time', 'status'];

    public function __construct(
        private readonly AppointmentSeriesService $appointmentSeriesService,
        private readonly DomainAuditService $auditService,
    ) {}

    public function index(ListAppointmentSeriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $status = (string) ($validated['status'] ?? 'all');

        $series = AppointmentSeries::query()
            ->where('workspace_id', $workspaceId)
            ->when($workspaceRole !== 'owner_admin', fn ($query) => $query->where('trainer_user_id', $user->id))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(isset($validated['trainer_id']), fn ($query) => $query->where('trainer_user_id', (int) $validated['trainer_id']))
            ->when(isset($validated['student_id']), fn ($query) => $query->where('student_id', (int) $validated['student_id']))
            ->when(isset($validated['from']), fn ($query) => $query->whereDate('start_date', '>=', $validated['from']))
            ->when(isset($validated['to']), fn ($query) => $query->whereDate('start_date', '<=', $validated['to']))
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->sendResponse(AppointmentSeriesResource::collection($series)->response()->getData(true));
    }

    public function store(StoreAppointmentSeriesRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = (string) $request->attributes->get('workspace_role');
        $data = $request->validated();
        $trainerId = $request->user()->id;

        if ($workspaceRole !== 'owner_admin' && isset($data['trainer_user_id'])) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ($workspaceRole === 'owner_admin' && isset($data['trainer_user_id'])) {
            $isWorkspaceTrainer = User::query()
                ->whereKey((int) $data['trainer_user_id'])
                ->whereHas('workspaces', fn ($query) => $query
                    ->where('workspaces.id', $workspaceId)
                    ->where('workspace_user.is_active', true))
                ->exists();

            if (! $isWorkspaceTrainer) {
                throw ValidationException::withMessages([
                    'trainer_user_id' => [__('api.workspace.membership_required')],
                ]);
            }

            $trainerId = (int) $data['trainer_user_id'];
        }

        $student = Student::query()
            ->whereKey((int) $data['student_id'])
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'student_id' => [__('api.workspace.membership_required')],
            ]);
        }

        if ($workspaceRole !== 'owner_admin' && (int) $student->trainer_user_id !== (int) $trainerId) {
            throw ValidationException::withMessages([
                'student_id' => [__('api.workspace.membership_required')],
            ]);
        }

        $result = $this->appointmentSeriesService->create(
            workspaceId: $workspaceId,
            trainerUserId: $trainerId,
            studentId: (int) $student->id,
            data: $data,
            workspaceReminderPolicy: $request->user()?->activeWorkspace?->reminder_policy,
        );

        $this->auditService->record(
            request: $request,
            event: 'appointment.series_created',
            auditable: $result['series'],
            after: $result['series']->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse([
            'series' => new AppointmentSeriesResource($result['series']),
            'generated_count' => $result['generated_count'],
            'skipped_conflicts_count' => $result['skipped_conflicts_count'],
        ], __('api.appointment.series_created'), 201);
    }

    public function show(AppointmentSeries $series): JsonResponse
    {
        $this->authorizeSeriesAccess($series);

        return $this->sendResponse(new AppointmentSeriesResource($series));
    }

    public function update(UpdateAppointmentSeriesRequest $request, AppointmentSeries $series): JsonResponse
    {
        $this->authorizeSeriesAccess($series);
        $before = $series->toArray();

        $result = $this->appointmentSeriesService->updateSeries(
            series: $series,
            data: $request->validated(),
            editScope: (string) $request->validated('edit_scope'),
            workspaceReminderPolicy: $request->user()?->activeWorkspace?->reminder_policy,
        );

        $this->auditService->record(
            request: $request,
            event: 'appointment.series_updated',
            auditable: $result['series'],
            before: $before,
            after: $result['series']->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse([
            'series' => new AppointmentSeriesResource($result['series']),
            'generated_count' => $result['generated_count'],
            'skipped_conflicts_count' => $result['skipped_conflicts_count'],
        ], __('api.appointment.series_updated'));
    }

    public function updateStatus(UpdateAppointmentSeriesStatusRequest $request, AppointmentSeries $series): JsonResponse
    {
        $this->authorizeSeriesAccess($series);
        $before = $series->toArray();

        $series->update([
            'status' => $request->validated('status'),
        ]);

        $series = $series->refresh();

        $this->auditService->record(
            request: $request,
            event: 'appointment.series_status_updated',
            auditable: $series,
            before: $before,
            after: $series->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentSeriesResource($series), __('api.appointment.series_status_updated'));
    }

    private function authorizeSeriesAccess(AppointmentSeries $series): void
    {
        $workspaceId = (int) request()->attributes->get('workspace_id');
        $workspaceRole = (string) request()->attributes->get('workspace_role');
        $user = request()->user();

        if ($series->workspace_id !== $workspaceId) {
            abort(403, __('api.forbidden'));
        }

        if ($workspaceRole !== 'owner_admin' && (int) $series->trainer_user_id !== (int) $user->id) {
            abort(403, __('api.forbidden'));
        }
    }
}
