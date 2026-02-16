<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\V1\Program\ListProgramTemplateRequest;
use App\Http\Requests\Api\V1\Program\StoreProgramTemplateRequest;
use App\Http\Requests\Api\V1\Program\UpdateProgramTemplateRequest;
use App\Http\Resources\ProgramTemplateResource;
use App\Models\ProgramTemplate;
use App\Models\User;
use App\Services\DomainAuditService;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProgramTemplateController extends BaseController
{
    private const AUDIT_FIELDS = ['name', 'title', 'goal', 'trainer_user_id'];

    public function __construct(
        private readonly ProgramService $programService,
        private readonly DomainAuditService $auditService,
    ) {}

    public function index(ListProgramTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $user = $request->user();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $search = trim((string) ($validated['search'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'id');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $templates = ProgramTemplate::query()
            ->with('items')
            ->where('workspace_id', $workspaceId)
            ->when($workspaceRole !== 'owner_admin', fn ($q) => $q->where('trainer_user_id', $user->id))
            ->when(isset($validated['trainer_user_id']), fn ($q) => $q->where('trainer_user_id', (int) $validated['trainer_user_id']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('goal', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        return $this->sendResponse(ProgramTemplateResource::collection($templates)->response()->getData(true));
    }

    public function store(StoreProgramTemplateRequest $request): JsonResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $workspaceRole = $request->attributes->get('workspace_role');
        $data = $request->validated();

        $trainerUserId = $request->user()->id;

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
