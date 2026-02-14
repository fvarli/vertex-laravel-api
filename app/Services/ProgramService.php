<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class ProgramService
{
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
