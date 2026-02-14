<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AppointmentConflictException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Api\V1\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends BaseController
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    /**
     * List appointments in active workspace with date and status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $appointments = Appointment::query()
            ->where('workspace_id', $workspaceId)
            ->when($workspaceRole !== 'owner_admin', fn ($q) => $q->where('trainer_user_id', $user->id))
            ->when($request->query('from'), fn ($q, $from) => $q->where('starts_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->where('starts_at', '<=', $to))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('trainer_id'), fn ($q, $trainerId) => $q->where('trainer_user_id', $trainerId))
            ->when($request->query('student_id'), fn ($q, $studentId) => $q->where('student_id', $studentId))
            ->latest('starts_at')
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

        try {
            $appointment = $this->appointmentService->create(
                workspaceId: $workspaceId,
                trainerUserId: $trainerId,
                studentId: (int) $data['student_id'],
                data: $data,
            );
        } catch (AppointmentConflictException $e) {
            return $this->sendError($e->getMessage(), [], 409);
        }

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

        $data = $request->validated();

        if (isset($data['trainer_user_id'])) {
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

        try {
            $appointment = $this->appointmentService->update($appointment, $data);
        } catch (AppointmentConflictException $e) {
            return $this->sendError($e->getMessage(), [], 409);
        }

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.updated'));
    }

    /**
     * Update appointment status.
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('setStatus', $appointment);

        $appointment = $this->appointmentService->updateStatus($appointment, $request->validated('status'));

        return $this->sendResponse(new AppointmentResource($appointment), __('api.appointment.status_updated'));
    }
}
