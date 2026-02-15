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
use App\Models\Student;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\DomainAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AppointmentController extends BaseController
{
    private const AUDIT_FIELDS = ['student_id', 'trainer_user_id', 'starts_at', 'ends_at', 'status', 'whatsapp_status', 'whatsapp_marked_at', 'whatsapp_marked_by_user_id', 'location'];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly DomainAuditService $auditService,
    ) {}

    /**
     * List appointments in active workspace with date and status filters.
     */
    public function index(ListAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $search = trim((string) ($validated['search'] ?? ''));
        $from = $validated['from'] ?? $validated['date_from'] ?? null;
        $to = $validated['to'] ?? $validated['date_to'] ?? null;
        $sort = (string) ($validated['sort'] ?? 'starts_at');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $appointments = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->when($workspaceRole !== 'owner_admin', fn ($q) => $q->where('trainer_user_id', $user->id))
            ->when($from, fn ($q, $fromValue) => $q->where('starts_at', '>=', $fromValue))
            ->when($to, fn ($q, $toValue) => $q->where('starts_at', '<=', $toValue))
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['whatsapp_status']) && $validated['whatsapp_status'] !== 'all', fn ($q) => $q->where('whatsapp_status', $validated['whatsapp_status']))
            ->when(isset($validated['trainer_id']), fn ($q) => $q->where('trainer_user_id', (int) $validated['trainer_id']))
            ->when(isset($validated['student_id']), fn ($q) => $q->where('student_id', (int) $validated['student_id']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('location', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search) {
                            $studentQuery->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

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
            $isWorkspaceTrainer = User::query()
                ->whereKey((int) $data['trainer_user_id'])
                ->whereHas('workspaces', fn ($q) => $q
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

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.created'), 201);
    }

    /**
     * Show a single appointment.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return $this->sendResponse(new AppointmentResource($appointment));
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

        if (isset($data['trainer_user_id'])) {
            if ($workspaceRole !== 'owner_admin') {
                return $this->sendError(__('api.forbidden'), [], 403);
            }

            $isWorkspaceTrainer = User::query()
                ->whereKey((int) $data['trainer_user_id'])
                ->whereHas('workspaces', fn ($q) => $q
                    ->where('workspaces.id', $appointment->workspace_id)
                    ->where('workspace_user.is_active', true))
                ->exists();

            if (! $isWorkspaceTrainer) {
                throw ValidationException::withMessages([
                    'trainer_user_id' => [__('api.workspace.membership_required')],
                ]);
            }
        }

        if (isset($data['student_id'])) {
            $student = Student::query()
                ->whereKey((int) $data['student_id'])
                ->where('workspace_id', $appointment->workspace_id)
                ->first();

            if (! $student) {
                throw ValidationException::withMessages([
                    'student_id' => [__('api.workspace.membership_required')],
                ]);
            }

            $trainerIdForAccess = (int) ($data['trainer_user_id'] ?? $appointment->trainer_user_id);

            if ($workspaceRole !== 'owner_admin' && (int) $student->trainer_user_id !== $trainerIdForAccess) {
                throw ValidationException::withMessages([
                    'student_id' => [__('api.workspace.membership_required')],
                ]);
            }
        }

        try {
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
        $appointment = $this->appointmentService->updateStatus($appointment, $request->validated('status'));

        $this->auditService->record(
            request: $request,
            event: 'appointment.status_updated',
            auditable: $appointment,
            before: $before,
            after: $appointment->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

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
