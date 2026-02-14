<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Student\StoreStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentStatusRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentController extends BaseController
{
    /**
     * List students in active workspace with role-based scope and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $students = Student::query()
            ->where('workspace_id', $workspaceId)
            ->when($workspaceRole !== 'owner_admin', fn ($q) => $q->where('trainer_user_id', $user->id))
            ->when(in_array($status, [Student::STATUS_ACTIVE, Student::STATUS_PASSIVE], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage);

        return $this->sendResponse(StudentResource::collection($students)->response()->getData(true));
    }

    /**
     * Create a student in active workspace.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $data = $request->validated();
        $trainerUserId = $request->user()->id;

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

            $trainerUserId = (int) $data['trainer_user_id'];
        }

        $student = Student::query()->create([
            'workspace_id' => $workspaceId,
            'trainer_user_id' => $trainerUserId,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? null,
            'status' => Student::STATUS_ACTIVE,
        ]);

        return $this->sendResponse(new StudentResource($student), __('api.student.created'), 201);
    }

    /**
     * Show a student record if requester has policy access.
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        return $this->sendResponse(new StudentResource($student));
    }

    /**
     * Update student fields if requester has policy access.
     */
    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $data = $request->validated();

        if (isset($data['trainer_user_id'])) {
            $isWorkspaceTrainer = User::query()
                ->whereKey((int) $data['trainer_user_id'])
                ->whereHas('workspaces', fn ($q) => $q
                    ->where('workspaces.id', $student->workspace_id)
                    ->where('workspace_user.is_active', true))
                ->exists();

            if (! $isWorkspaceTrainer) {
                throw ValidationException::withMessages([
                    'trainer_user_id' => [__('api.workspace.membership_required')],
                ]);
            }
        }

        $student->update($data);

        return $this->sendResponse(new StudentResource($student->refresh()), __('api.student.updated'));
    }

    /**
     * Update student status between active and passive.
     */
    public function updateStatus(UpdateStudentStatusRequest $request, Student $student): JsonResponse
    {
        $this->authorize('setStatus', $student);

        $student->update(['status' => $request->validated('status')]);

        return $this->sendResponse(new StudentResource($student->refresh()), __('api.student.status_updated'));
    }
}
