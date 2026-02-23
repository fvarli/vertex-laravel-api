<?php

namespace App\Services;

use App\Models\Program;
use App\Models\ProgramTemplate;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ProgramService
{
    public function listTemplates(int $workspaceId, ?int $trainerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = (string) ($filters['direction'] ?? 'desc');

        return ProgramTemplate::query()
            ->with('items')
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId, fn ($q) => $q->where('trainer_user_id', $trainerUserId))
            ->when(isset($filters['trainer_user_id']), fn ($q) => $q->where('trainer_user_id', (int) $filters['trainer_user_id']))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('goal', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function listPrograms(int $studentId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 100);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? 'all');
        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = (string) ($filters['direction'] ?? 'desc');

        return Program::query()
            ->with('items')
            ->where('student_id', $studentId)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('goal', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function create(Student $student, int $trainerUserId, array $data): Program
    {
        $status = $data['status'] ?? Program::STATUS_DRAFT;
        $weekStartDate = $data['week_start_date'];

        $this->assertSingleActivePerWeek(
            studentId: $student->id,
            weekStartDate: $weekStartDate,
            status: $status,
        );

        $program = Program::query()->create([
            'workspace_id' => $student->workspace_id,
            'student_id' => $student->id,
            'trainer_user_id' => $trainerUserId,
            'title' => $data['title'],
            'goal' => $data['goal'] ?? null,
            'week_start_date' => $weekStartDate,
            'status' => $status,
        ]);

        $this->syncItems($program, $data['items'] ?? []);

        return $program->load(['student', 'trainer', 'items']);
    }

    public function update(Program $program, array $data): Program
    {
        $targetStatus = $data['status'] ?? $program->status;
        $targetWeek = $data['week_start_date'] ?? $program->week_start_date?->toDateString();

        $this->assertSingleActivePerWeek(
            studentId: $program->student_id,
            weekStartDate: $targetWeek,
            status: $targetStatus,
            ignoreProgramId: $program->id,
        );

        $program->update([
            'title' => $data['title'] ?? $program->title,
            'goal' => array_key_exists('goal', $data) ? $data['goal'] : $program->goal,
            'week_start_date' => $targetWeek,
            'status' => $targetStatus,
        ]);

        if (array_key_exists('items', $data)) {
            $this->syncItems($program, $data['items'] ?? []);
        }

        return $program->refresh()->load(['student', 'trainer', 'items']);
    }

    public function updateStatus(Program $program, string $status): Program
    {
        $this->assertSingleActivePerWeek(
            studentId: $program->student_id,
            weekStartDate: $program->week_start_date?->toDateString(),
            status: $status,
            ignoreProgramId: $program->id,
        );

        $program->update(['status' => $status]);

        return $program->refresh()->load(['student', 'trainer', 'items']);
    }

    public function createTemplate(int $workspaceId, int $trainerUserId, array $data): ProgramTemplate
    {
        $name = trim((string) $data['name']);

        $nameExists = ProgramTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->where('trainer_user_id', $trainerUserId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($nameExists) {
            throw ValidationException::withMessages([
                'name' => [__('validation.unique', ['attribute' => 'name'])],
            ]);
        }

        $template = ProgramTemplate::query()->create([
            'workspace_id' => $workspaceId,
            'trainer_user_id' => $trainerUserId,
            'name' => $name,
            'title' => trim((string) $data['title']),
            'goal' => $data['goal'] ?? null,
        ]);

        $this->syncTemplateItems($template, $data['items'] ?? []);

        return $template->load('items');
    }

    public function updateTemplate(ProgramTemplate $template, array $data): ProgramTemplate
    {
        if (array_key_exists('name', $data)) {
            $targetName = trim((string) $data['name']);
            $nameExists = ProgramTemplate::query()
                ->where('workspace_id', $template->workspace_id)
                ->where('trainer_user_id', $template->trainer_user_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($targetName)])
                ->whereKeyNot($template->id)
                ->exists();

            if ($nameExists) {
                throw ValidationException::withMessages([
                    'name' => [__('validation.unique', ['attribute' => 'name'])],
                ]);
            }
        }

        $template->update([
            'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $template->name,
            'title' => $data['title'] ?? $template->title,
            'goal' => array_key_exists('goal', $data) ? $data['goal'] : $template->goal,
        ]);

        if (array_key_exists('items', $data)) {
            $this->syncTemplateItems($template, $data['items'] ?? []);
        }

        return $template->refresh()->load('items');
    }

    public function createFromTemplate(Student $student, int $trainerUserId, ProgramTemplate $template, array $data): Program
    {
        $payload = [
            'title' => trim((string) ($data['title'] ?? $template->title)),
            'goal' => array_key_exists('goal', $data) ? $data['goal'] : $template->goal,
            'week_start_date' => $data['week_start_date'],
            'status' => $data['status'] ?? Program::STATUS_DRAFT,
            'items' => $template->items
                ->sortBy(fn ($item) => sprintf('%d-%04d', $item->day_of_week, $item->order_no))
                ->values()
                ->map(function ($item) {
                    return [
                        'day_of_week' => $item->day_of_week,
                        'order_no' => $item->order_no,
                        'exercise' => $item->exercise,
                        'sets' => $item->sets,
                        'reps' => $item->reps,
                        'rest_seconds' => $item->rest_seconds,
                        'notes' => $item->notes,
                    ];
                })
                ->all(),
        ];

        return $this->create($student, $trainerUserId, $payload);
    }

    public function copyWeek(Student $student, int $trainerUserId, array $data): Program
    {
        $sourceWeek = (string) $data['source_week_start_date'];
        $targetWeek = (string) $data['target_week_start_date'];

        $sourceProgram = Program::query()
            ->with('items')
            ->where('student_id', $student->id)
            ->whereDate('week_start_date', $sourceWeek)
            ->orderByDesc('updated_at')
            ->first();

        if (! $sourceProgram) {
            throw ValidationException::withMessages([
                'source_week_start_date' => [__('api.program.copy_source_missing')],
            ]);
        }

        return $this->create($student, $trainerUserId, [
            'title' => $sourceProgram->title,
            'goal' => $sourceProgram->goal,
            'week_start_date' => $targetWeek,
            'status' => $data['status'] ?? Program::STATUS_DRAFT,
            'items' => $sourceProgram->items
                ->sortBy(fn ($item) => sprintf('%d-%04d', $item->day_of_week, $item->order_no))
                ->values()
                ->map(function ($item) {
                    return [
                        'day_of_week' => $item->day_of_week,
                        'order_no' => $item->order_no,
                        'exercise' => $item->exercise,
                        'sets' => $item->sets,
                        'reps' => $item->reps,
                        'rest_seconds' => $item->rest_seconds,
                        'notes' => $item->notes,
                    ];
                })
                ->all(),
        ]);
    }

    private function syncItems(Program $program, array $items): void
    {
        $program->items()->delete();

        foreach ($items as $item) {
            $program->items()->create([
                'day_of_week' => $item['day_of_week'],
                'order_no' => $item['order_no'],
                'exercise' => trim($item['exercise']),
                'sets' => $item['sets'] ?? null,
                'reps' => $item['reps'] ?? null,
                'rest_seconds' => $item['rest_seconds'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function syncTemplateItems(ProgramTemplate $template, array $items): void
    {
        $template->items()->delete();

        foreach ($items as $item) {
            $template->items()->create([
                'day_of_week' => $item['day_of_week'],
                'order_no' => $item['order_no'],
                'exercise' => trim($item['exercise']),
                'sets' => $item['sets'] ?? null,
                'reps' => $item['reps'] ?? null,
                'rest_seconds' => $item['rest_seconds'] ?? null,
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function assertSingleActivePerWeek(int $studentId, string $weekStartDate, string $status, ?int $ignoreProgramId = null): void
    {
        if ($status !== Program::STATUS_ACTIVE) {
            return;
        }

        $exists = Program::query()
            ->where('student_id', $studentId)
            ->whereDate('week_start_date', $weekStartDate)
            ->where('status', Program::STATUS_ACTIVE)
            ->when($ignoreProgramId, fn ($q) => $q->whereKeyNot($ignoreProgramId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => [__('api.program.active_exists_for_week')],
            ]);
        }
    }
}
