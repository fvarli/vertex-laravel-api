<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Student\ListStudentRequest;
use App\Http\Requests\Api\V1\Student\ListStudentTimelineRequest;
use App\Http\Requests\Api\V1\Student\StoreStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentRequest;
use App\Http\Requests\Api\V1\Student\UpdateStudentStatusRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\DomainAuditService;
use App\Services\StudentService;
use App\Services\StudentTimelineService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends BaseController
{
    private const AUDIT_FIELDS = ['full_name', 'phone', 'status', 'trainer_user_id'];

    public function __construct(
        private readonly DomainAuditService $auditService,
        private readonly StudentTimelineService $timelineService,
        private readonly StudentService $studentService,
        private readonly WorkspaceContextService $workspaceContext,
    ) {}

    /**
     * List students in active workspace with role-based scope and filters.
     */
    public function index(ListStudentRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole !== WorkspaceRole::OwnerAdmin->value ? $request->user()->id : null;

        $students = $this->studentService->list($workspaceId, $trainerUserId, $request->validated());

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

        if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value && isset($data['trainer_user_id'])) {
            return $this->sendError(__('api.forbidden'), [], 403);
        }

        if ($workspaceRole === WorkspaceRole::OwnerAdmin->value && isset($data['trainer_user_id'])) {
            $this->workspaceContext->assertTrainerInWorkspace((int) $data['trainer_user_id'], $workspaceId);
            $trainerUserId = (int) $data['trainer_user_id'];
        }

        $student = $this->studentService->create($workspaceId, $trainerUserId, $data);

        $this->auditService->record(
            request: $request,
            event: 'student.created',
            auditable: $student,
            after: $student->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

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

        $before = $student->toArray();
        $data = $request->validated();
        $workspaceRole = $request->attributes->get('workspace_role');

        if (isset($data['trainer_user_id'])) {
            if ($workspaceRole !== WorkspaceRole::OwnerAdmin->value) {
                return $this->sendError(__('api.forbidden'), [], 403);
            }

            $this->workspaceContext->assertTrainerInWorkspace((int) $data['trainer_user_id'], $student->workspace_id);
        }

        $freshStudent = $this->studentService->update($student, $data);

        $this->auditService->record(
            request: $request,
            event: 'student.updated',
            auditable: $freshStudent,
            before: $before,
            after: $freshStudent->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new StudentResource($freshStudent), __('api.student.updated'));
    }

    /**
     * Update student status between active and passive.
     */
    public function updateStatus(UpdateStudentStatusRequest $request, Student $student): JsonResponse
    {
        $this->authorize('setStatus', $student);

        $before = $student->toArray();
        $freshStudent = $this->studentService->updateStatus($student, $request->validated('status'));

        $this->auditService->record(
            request: $request,
            event: 'student.status_updated',
            auditable: $freshStudent,
            before: $before,
            after: $freshStudent->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new StudentResource($freshStudent), __('api.student.status_updated'));
    }

    /**
     * Show recent student timeline events from programs and appointments.
     */
    public function timeline(ListStudentTimelineRequest $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $timeline = $this->timelineService->list(
            student: $student,
            limit: (int) ($request->validated('limit') ?? 30),
        );

        return $this->sendResponse([
            'student_id' => $student->id,
            'items' => $timeline,
        ]);
    }
}
