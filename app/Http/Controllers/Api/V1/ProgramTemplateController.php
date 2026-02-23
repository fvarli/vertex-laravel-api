<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Program\ListProgramTemplateRequest;
use App\Http\Requests\Api\V1\Program\StoreProgramTemplateRequest;
use App\Http\Requests\Api\V1\Program\UpdateProgramTemplateRequest;
use App\Http\Resources\ProgramTemplateResource;
use App\Models\ProgramTemplate;
use App\Services\DomainAuditService;
use App\Services\ProgramService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;

class ProgramTemplateController extends BaseController
{
    private const AUDIT_FIELDS = ['name', 'title', 'goal', 'trainer_user_id'];

    public function __construct(
        private readonly ProgramService $programService,
        private readonly DomainAuditService $auditService,
        private readonly WorkspaceContextService $workspaceContext,
    ) {}

    public function index(ListProgramTemplateRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $trainerUserId = $workspaceRole !== WorkspaceRole::OwnerAdmin->value ? $request->user()->id : null;

        $templates = $this->programService->listTemplates($workspaceId, $trainerUserId, $request->validated());

        return $this->sendResponse(ProgramTemplateResource::collection($templates)->response()->getData(true));
    }

    public function store(StoreProgramTemplateRequest $request): JsonResponse
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

        $template = $this->programService->createTemplate($workspaceId, $trainerUserId, $data);

        $this->auditService->record(
            request: $request,
            event: 'program_template.created',
            auditable: $template,
            after: $template->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new ProgramTemplateResource($template), __('api.program.template_created'), 201);
    }

    public function show(ProgramTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        return $this->sendResponse(new ProgramTemplateResource($template->load('items')));
    }

    public function update(UpdateProgramTemplateRequest $request, ProgramTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $before = $template->toArray();
        $template = $this->programService->updateTemplate($template, $request->validated());

        $this->auditService->record(
            request: $request,
            event: 'program_template.updated',
            auditable: $template,
            before: $before,
            after: $template->toArray(),
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse(new ProgramTemplateResource($template), __('api.program.template_updated'));
    }

    public function destroy(\Illuminate\Http\Request $request, ProgramTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        $before = $template->toArray();
        $template->delete();

        $this->auditService->record(
            request: $request,
            event: 'program_template.deleted',
            auditable: $template,
            before: $before,
            after: [],
            allowedFields: self::AUDIT_FIELDS,
        );

        return $this->sendResponse([], __('api.program.template_deleted'));
    }
}
