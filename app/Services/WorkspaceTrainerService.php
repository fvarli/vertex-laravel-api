<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Enums\WorkspaceRole;
use App\Models\Appointment;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkspaceTrainerService
{
    public function overview(int $workspaceId, bool $includeInactive = false, ?string $search = null): array
    {
        $todayStart = CarbonImmutable::now()->startOfDay();
        $todayEnd = CarbonImmutable::now()->endOfDay();
        $nextWeekEnd = CarbonImmutable::now()->addDays(7)->endOfDay();

        $studentCounts = Student::query()
            ->selectRaw('trainer_user_id, COUNT(*) as student_count')
            ->where('workspace_id', $workspaceId)
            ->groupBy('trainer_user_id');

        $todayAppointmentCounts = Appointment::query()
            ->selectRaw('trainer_user_id, COUNT(*) as today_appointments')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->groupBy('trainer_user_id');

        $upcomingAppointmentCounts = Appointment::query()
            ->selectRaw('trainer_user_id, COUNT(*) as upcoming_7d_appointments')
            ->where('workspace_id', $workspaceId)
            ->whereBetween('starts_at', [$todayStart, $nextWeekEnd])
            ->whereIn('status', [Appointment::STATUS_PLANNED, Appointment::STATUS_DONE])
            ->groupBy('trainer_user_id');

        $trainers = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.surname',
                'users.email',
                'users.phone',
                'users.is_active',
                'users.created_at',
                'users.updated_at',
                'workspace_user.is_active as membership_is_active',
                DB::raw('COALESCE(student_counts.student_count, 0) as student_count'),
                DB::raw('COALESCE(today_appointment_counts.today_appointments, 0) as today_appointments'),
                DB::raw('COALESCE(upcoming_appointment_counts.upcoming_7d_appointments, 0) as upcoming_7d_appointments'),
            ])
            ->join('workspace_user', function ($join) use ($workspaceId) {
                $join->on('workspace_user.user_id', '=', 'users.id')
                    ->where('workspace_user.workspace_id', $workspaceId)
                    ->where('workspace_user.role', WorkspaceRole::Trainer->value);
            })
            ->leftJoinSub($studentCounts, 'student_counts', 'student_counts.trainer_user_id', '=', 'users.id')
            ->leftJoinSub($todayAppointmentCounts, 'today_appointment_counts', 'today_appointment_counts.trainer_user_id', '=', 'users.id')
            ->leftJoinSub($upcomingAppointmentCounts, 'upcoming_appointment_counts', 'upcoming_appointment_counts.trainer_user_id', '=', 'users.id')
            ->when(! $includeInactive, fn ($query) => $query->where('workspace_user.is_active', true))
            ->when(
                is_string($search) && $search !== '',
                function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.surname', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%")
                            ->orWhere('users.phone', 'like', "%{$search}%");
                    });
                }
            )
            ->orderByDesc('student_count')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();

        $trainerCount = $trainers->count();
        $activeTrainerCount = $trainers->where('membership_is_active', true)->count();
        $totalStudents = (int) $trainers->sum(fn ($trainer) => (int) $trainer->student_count);
        $totalTodayAppointments = (int) $trainers->sum(fn ($trainer) => (int) $trainer->today_appointments);
        $totalUpcomingAppointments = (int) $trainers->sum(fn ($trainer) => (int) $trainer->upcoming_7d_appointments);

        return [
            'trainers' => $trainers,
            'summary' => [
                'total_trainers' => $trainerCount,
                'active_trainers' => $activeTrainerCount,
                'total_students' => $totalStudents,
                'today_appointments' => $totalTodayAppointments,
                'upcoming_7d_appointments' => $totalUpcomingAppointments,
                'avg_students_per_trainer' => $trainerCount > 0
                    ? round($totalStudents / $trainerCount, 1)
                    : null,
            ],
        ];
    }

    public function create(int $workspaceId, array $data): User
    {
        return DB::transaction(function () use ($workspaceId, $data) {
            $workspace = Workspace::query()->findOrFail($workspaceId);

            $user = User::query()->create([
                'name' => trim((string) $data['name']),
                'surname' => trim((string) $data['surname']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
                'password' => (string) $data['password'],
                'is_active' => true,
                'system_role' => SystemRole::WorkspaceUser->value,
                'email_verified_at' => now(),
                'active_workspace_id' => $workspaceId,
            ]);

            $workspace->users()->syncWithoutDetaching([
                $user->id => ['role' => WorkspaceRole::Trainer->value, 'is_active' => true],
            ]);

            $trainerRole = Role::query()->where('name', 'trainer')->first();
            if ($trainerRole) {
                $user->roles()->syncWithoutDetaching([
                    $trainerRole->id => ['workspace_id' => $workspaceId],
                ]);
            }

            return $user->fresh();
        });
    }
}
