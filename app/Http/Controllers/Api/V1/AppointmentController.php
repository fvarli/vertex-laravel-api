<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AppointmentConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Appointment\ListAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentWhatsappStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentCreatedNotification;
use App\Services\AppointmentSeriesService;
use App\Services\AppointmentService;
use App\Services\DomainAuditService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;

class AppointmentController extends BaseController
{
    private const AUDIT_FIELDS = ['series_id', 'series_occurrence_date', 'is_series_exception', 'series_edit_scope_applied', 'student_id', 'trainer_user_id', 'starts_at', 'ends_at', 'status', 'whatsapp_status', 'whatsapp_marked_at', 'whatsapp_marked_by_user_id', 'location'];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentSeriesService $appointmentSeriesService,
        private readonly DomainAuditService $auditService,
        private readonly WorkspaceContextService $workspaceContext,
    ) {}

    /**
     * List appointments in active workspace with date and status filters.
     */
    public function index(ListAppointmentRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole !== 'owner_admin' ? $request->user()->id : null;

        $appointments = $this->appointmentService->list($workspaceId, $trainerUserId, $request->validated());

        return $this->sendResponse(AppointmentResource::collection($appointments)->response()->getData(true));
    }

    /**
     * Create a new appointment with overlap conflict protection.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $data = $request->validated();

        $trainerId = $request->user()->id;

        if ($workspaceRole !== 'owner_admin' && isset($data['trainer_user_id'])) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ($workspaceRole === 'owner_admin' && isset($data['trainer_user_id'])) {
            $this->workspaceContext->assertTrainerInWorkspace((int) $data['trainer_user_id'], $workspaceId);
            $trainerId = (int) $data['trainer_user_id'];
        }

        $student = $this->workspaceContext->assertStudentInWorkspace((int) $data['student_id'], $workspaceId);

        if ($workspaceRole !== 'owner_admin' && (int) $student->trainer_user_id !== (int) $trainerId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'student_id' => [__('api.workspace.membership_required')],
            ]);
        }

        try {
            $appointment = $this->appointmentService->create(
                workspaceId: $workspaceId,
                trainerUserId: $trainerId,
                studentId: (int) $data['student_id'],
                data: $data,
            );
        } catch (AppointmentConflictException $e) {
            return $this->sendError($e->getMessage(), [
                'code' => ['time_slot_conflict'],
            ], 422);
        }

        $this->auditService->record(
            request: $request,
            event: 'appointment.created',
            auditable: $appointment,
            after: $appointment->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        $trainer = User::query()->find($appointment->trainer_user_id);
        if ($trainer) {
            $appointment->loadMissing('student');
            $trainer->notify(new AppointmentCreatedNotification($appointment));
        }

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.created'), 201);
    }

    /**
     * Show a single appointment.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return $this->sendResponse(new AppointmentResource($appointment->load(['student', 'trainer', 'reminders'])));
    }

    /**
     * Update appointment fields with conflict protection.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $before = $appointment->toArray();
        $data = $request->validated();
        $workspaceRole = $request->attributes->get('workspace_role');
        $editScope = (string) ($data['edit_scope'] ?? 'single');
        unset($data['edit_scope']);

        if (isset($data['trainer_user_id'])) {
            if ($workspaceRole !== 'owner_admin') {
                return $this->sendError(__('api.forbidden'), [], 403);
            }

            $this->workspaceContext->assertTrainerInWorkspace((int) $data['trainer_user_id'], $appointment->workspace_id);
        }

        if (isset($data['student_id'])) {
            $student = $this->workspaceContext->assertStudentInWorkspace((int) $data['student_id'], $appointment->workspace_id);

            $trainerIdForAccess = (int) ($data['trainer_user_id'] ?? $appointment->trainer_user_id);

            if ($workspaceRole !== 'owner_admin' && (int) $student->trainer_user_id !== $trainerIdForAccess) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'student_id' => [__('api.workspace.membership_required')],
                ]);
            }
        }

        try {
            if ($appointment->series_id && in_array($editScope, ['future', 'all'], true)) {
                $series = $appointment->series;

                if ($series) {
                    $seriesData = [];
                    if (array_key_exists('location', $data)) {
                        $seriesData['location'] = $data['location'];
                    }
                    if (array_key_exists('notes', $data)) {
                        $seriesData['title'] = $data['notes'];
                    }
                    if (array_key_exists('starts_at', $data)) {
                        $startsAt = \Carbon\Carbon::parse($data['starts_at'])->utc();
                        $seriesData['start_date'] = $startsAt->toDateString();
                        $seriesData['starts_at_time'] = $startsAt->format('H:i:s');
                    }
                    if (array_key_exists('ends_at', $data)) {
                        $endsAt = \Carbon\Carbon::parse($data['ends_at'])->utc();
                        $seriesData['ends_at_time'] = $endsAt->format('H:i:s');
                    }

                    $this->appointmentSeriesService->updateSeries(
                        series: $series,
                        data: $seriesData,
                        editScope: $editScope,
                        workspaceReminderPolicy: $request->user()?->activeWorkspace?->reminder_policy,
                    );

                    $occurrenceDate = isset($data['starts_at'])
                        ? \Carbon\Carbon::parse($data['starts_at'])->toDateString()
                        : $appointment->series_occurrence_date?->toDateString();

                    if ($occurrenceDate) {
                        $replacement = Appointment::query()
                            ->where('series_id', $series->id)
                            ->whereDate('series_occurrence_date', $occurrenceDate)
                            ->first();

                        if ($replacement) {
                            $appointment = $replacement;
                        }
                    }

                    if (! $appointment->exists) {
                        $appointment = Appointment::query()->findOrFail($appointment->id);
                    }
                }
            }

            $appointment = $this->appointmentService->update($appointment, $data);
        } catch (AppointmentConflictException $e) {
            return $this->sendError($e->getMessage(), [
                'code' => ['time_slot_conflict'],
            ], 422);
        }

        $this->auditService->record(
            request: $request,
            event: 'appointment.updated',
            auditable: $appointment,
            before: $before,
            after: $appointment->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.updated'));
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('setStatus', $appointment);

        $before = $appointment->toArray();
        $newStatus = $request->validated('status');
        $appointment = $this->appointmentService->updateStatus($appointment, $newStatus);

        $this->auditService->record(
            request: $request,
            event: 'appointment.status_updated',
            auditable: $appointment,
            before: $before,
            after: $appointment->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        if ($newStatus === Appointment::STATUS_CANCELLED) {
            $trainer = User::query()->find($appointment->trainer_user_id);
            if ($trainer) {
                $appointment->loadMissing('student');
                $trainer->notify(new AppointmentCancelledNotification($appointment));
            }
        }

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.status_updated'));
    }

    /**
     * Update manual WhatsApp message tracking status for an appointment.
     */
    public function updateWhatsappStatus(UpdateAppointmentWhatsappStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $before = $appointment->toArray();
        $status = $request->validated('whatsapp_status');
        $isSent = $status === Appointment::WHATSAPP_STATUS_SENT;

        $appointment->update([
            'whatsapp_status' => $status,
            'whatsapp_marked_at' => $isSent ? now()->utc() : null,
            'whatsapp_marked_by_user_id' => $isSent ? $request->user()->id : null,
        ]);

        $appointment = $appointment->refresh()->load(['student', 'trainer']);

        $this->auditService->record(
            request: $request,
            event: 'appointment.whatsapp_status_updated',
            auditable: $appointment,
            before: $before,
            after: $appointment->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.whatsapp_status_updated'));
    }
}
