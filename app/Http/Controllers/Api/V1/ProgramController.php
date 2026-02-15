<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Program\ListProgramRequest;
use App\Http\Requests\Api\V1\Program\StoreProgramRequest;
use App\Http\Requests\Api\V1\Program\UpdateProgramRequest;
use App\Http\Requests\Api\V1\Program\UpdateProgramStatusRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Models\Student;
use App\Services\DomainAuditService;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;

class ProgramController extends BaseController
{
    private const AUDIT_FIELDS = ['title', 'status', 'week_start_date', 'trainer_user_id', 'student_id'];

    public function __construct(
        private readonly ProgramService $programService,
        private readonly DomainAuditService $auditService,
    ) {}

    /**
     * List programs for a student in active workspace context.
     */
    public function index(ListProgramRequest $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 100);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $sort = (string) ($validated['sort'] ?? 'id');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $programs = Program::query()
            ->with('items')
            ->where('student_id', $student->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('goal', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return $this->sendResponse(ProgramResource::collection($programs)->response()->getData(true));
    }

    /**
     * Create a weekly training program for a student.
     */
    public function store(StoreProgramRequest $request, Student $student): JsonResponse
    {
        $this->authorize('view', $student);

        $program = $this->programService->create(
            student: $student,
            trainerUserId: $student->trainer_user_id,
            data: $request->validated(),
        );

        $this->auditService->record(
            request: $request,
            event: 'program.created',
            auditable: $program,
            after: $program->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new ProgramResource($program), __('api.program.created'), 201);
    }

    /**
     * Show a single program with ordered items.
     */
    public function show(Program $program): JsonResponse
    {
        $this->authorize('view', $program);

        return $this->sendResponse(new ProgramResource($program->load('items')));
    }

    /**
     * Update a program and optional item list.
     */
    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $this->authorize('update', $program);

        $before = $program->toArray();
        $program = $this->programService->update($program, $request->validated());

        $this->auditService->record(
            request: $request,
            event: 'program.updated',
            auditable: $program,
            before: $before,
            after: $program->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new ProgramResource($program), __('api.program.updated'));
    }

    /**
     * Update program lifecycle status.
     */
    public function updateStatus(UpdateProgramStatusRequest $request, Program $program): JsonResponse
    {
        $this->authorize('setStatus', $program);

        $before = $program->toArray();
        $program = $this->programService->updateStatus($program, $request->validated('status'));

        $this->auditService->record(
            request: $request,
            event: 'program.status_updated',
            auditable: $program,
            before: $before,
            after: $program->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new ProgramResource($program), __('api.program.status_updated'));
    }
}
