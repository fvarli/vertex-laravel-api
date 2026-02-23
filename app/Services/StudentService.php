<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentService
{
    public function list(int $workspaceId, ?int $trainerUserId, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = (string) ($filters['direction'] ?? 'desc');

        return Student::query()
            ->where('workspace_id', $workspaceId)
            ->when($trainerUserId, fn ($q) => $q->where('trainer_user_id', $trainerUserId))
            ->when(in_array($status, [Student::STATUS_ACTIVE, Student::STATUS_PASSIVE], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage);
    }

    public function create(int $workspaceId, int $trainerUserId, array $data): Student
    {
        return Student::query()->create([
            'workspace_id' => $workspaceId,
            'trainer_user_id' => $trainerUserId,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'notes' => $data['notes'] ?? null,
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    public function update(Student $student, array $data): Student
    {
        $student->update($data);

        return $student->refresh();
    }

    public function updateStatus(Student $student, string $status): Student
    {
        $student->update(['status' => $status]);

        return $student->refresh();
    }
}
