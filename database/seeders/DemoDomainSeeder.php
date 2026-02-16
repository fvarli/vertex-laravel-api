<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AppointmentSeries;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AppointmentSeriesService;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoDomainSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->where('name', 'Vertex Demo Workspace')->firstOrFail();
        $trainer = User::query()->where('email', 'trainer@vertex.local')->firstOrFail();
        $anchor = Carbon::now('UTC')->startOfDay();
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);

        $this->resetWorkspaceDomainData($workspace->id);
        $students = $this->seedStudents($workspace->id, $trainer->id);
        $this->seedPrograms($workspace->id, $trainer->id, $students, $weekStart);
        $this->seedRecurringSchedule($workspace, $trainer->id, $students, $anchor);
        $this->seedOneOffAppointments($workspace, $trainer->id, $students, $anchor);
    }

    private function resetWorkspaceDomainData(int $workspaceId): void
    {
        AppointmentReminder::query()->where('workspace_id', $workspaceId)->delete();
        Appointment::query()->where('workspace_id', $workspaceId)->delete();
        AppointmentSeries::query()->where('workspace_id', $workspaceId)->delete();
        Program::query()->where('workspace_id', $workspaceId)->delete();
        Student::query()->where('workspace_id', $workspaceId)->delete();
    }

    /**
     * @return Collection<int, Student>
     */
    private function seedStudents(int $workspaceId, int $trainerId): Collection
    {
        return collect([
            ['full_name' => 'Ali Veli', 'phone' => '+905550000001', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Ayse Kaya', 'phone' => '+905550000002', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Mehmet Demir', 'phone' => '+905550000003', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Fatma Yildiz', 'phone' => '+905550000004', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Can Oz', 'phone' => '+905550000005', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Deniz Acar', 'phone' => '+905550000006', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Selin Cakir', 'phone' => '+905550000009', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Burak Arda', 'phone' => '+905550000010', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Ece Nur', 'phone' => '+905550000011', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Kerem Ak', 'phone' => '+905550000012', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Merve Tas', 'phone' => '+905550000007', 'status' => Student::STATUS_PASSIVE],
            ['full_name' => 'Emre Kurt', 'phone' => '+905550000008', 'status' => Student::STATUS_PASSIVE],
        ])->map(function (array $data) use ($workspaceId, $trainerId) {
            return Student::query()->create([
                'workspace_id' => $workspaceId,
                'trainer_user_id' => $trainerId,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'status' => $data['status'],
                'notes' => 'Demo seeded student',
            ]);
        });
    }

    private function seedPrograms(int $workspaceId, int $trainerId, Collection $students, Carbon $weekStart): void
    {
        $statuses = [
            Program::STATUS_ACTIVE,
            Program::STATUS_DRAFT,
            Program::STATUS_ARCHIVED,
            Program::STATUS_ACTIVE,
            Program::STATUS_DRAFT,
        ];

        foreach ($students->take(5) as $index => $student) {
            $program = Program::query()->create([
                'workspace_id' => $workspaceId,
                'student_id' => $student->id,
                'trainer_user_id' => $trainerId,
                'title' => 'Weekly Strength Plan '.($index + 1),
                'goal' => 'Improve consistency and progressive overload',
                'week_start_date' => $weekStart->toDateString(),
                'status' => $statuses[$index] ?? Program::STATUS_DRAFT,
            ]);

            $program->items()->createMany([
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'Squat', 'sets' => 4, 'reps' => 8, 'rest_seconds' => 90],
                ['day_of_week' => 3, 'order_no' => 1, 'exercise' => 'Bench Press', 'sets' => 4, 'reps' => 8, 'rest_seconds' => 90],
                ['day_of_week' => 5, 'order_no' => 1, 'exercise' => 'Deadlift', 'sets' => 3, 'reps' => 5, 'rest_seconds' => 120],
                ['day_of_week' => 6, 'order_no' => 1, 'exercise' => 'Core Circuit', 'sets' => 3, 'reps' => 12, 'rest_seconds' => 60],
            ]);
        }
    }

    private function seedRecurringSchedule(Workspace $workspace, int $trainerId, Collection $students, Carbon $anchor): void
    {
        /** @var AppointmentSeriesService $seriesService */
        $seriesService = app(AppointmentSeriesService::class);
        $activeStudents = $students->where('status', Student::STATUS_ACTIVE)->values();
        $windowDays = 14;

        $seriesService->create(
            workspaceId: $workspace->id,
            trainerUserId: $trainerId,
            studentId: (int) $activeStudents[0]->id,
            data: [
                'student_id' => (int) $activeStudents[0]->id,
                'title' => 'Morning Strength Series',
                'location' => 'Vertex Studio Room 1',
                'start_date' => $anchor->toDateString(),
                'starts_at_time' => '09:00:00',
                'ends_at_time' => '10:00:00',
                'recurrence_rule' => [
                    'freq' => 'weekly',
                    'interval' => 1,
                    'count' => $this->countWeekdayOccurrences($anchor, [1, 3, 5], $windowDays),
                    'byweekday' => [1, 3, 5],
                ],
            ],
            workspaceReminderPolicy: $workspace->reminder_policy,
        );

        $seriesService->create(
            workspaceId: $workspace->id,
            trainerUserId: $trainerId,
            studentId: (int) $activeStudents[1]->id,
            data: [
                'student_id' => (int) $activeStudents[1]->id,
                'title' => 'Conditioning Series',
                'location' => 'Vertex Studio Room 2',
                'start_date' => $anchor->toDateString(),
                'starts_at_time' => '11:00:00',
                'ends_at_time' => '12:00:00',
                'recurrence_rule' => [
                    'freq' => 'weekly',
                    'interval' => 1,
                    'count' => $this->countWeekdayOccurrences($anchor, [2, 4], $windowDays),
                    'byweekday' => [2, 4],
                ],
            ],
            workspaceReminderPolicy: $workspace->reminder_policy,
        );

        $seriesService->create(
            workspaceId: $workspace->id,
            trainerUserId: $trainerId,
            studentId: (int) $activeStudents[2]->id,
            data: [
                'student_id' => (int) $activeStudents[2]->id,
                'title' => 'Weekend Mobility Series',
                'location' => 'Vertex Mobility Zone',
                'start_date' => $anchor->toDateString(),
                'starts_at_time' => '10:00:00',
                'ends_at_time' => '11:00:00',
                'recurrence_rule' => [
                    'freq' => 'weekly',
                    'interval' => 1,
                    'count' => $this->countWeekdayOccurrences($anchor, [6], $windowDays),
                    'byweekday' => [6],
                ],
            ],
            workspaceReminderPolicy: $workspace->reminder_policy,
        );
    }

    private function seedOneOffAppointments(Workspace $workspace, int $trainerId, Collection $students, Carbon $anchor): void
    {
        /** @var AppointmentService $appointmentService */
        $appointmentService = app(AppointmentService::class);
        $activeStudents = $students->where('status', Student::STATUS_ACTIVE)->values();
        $activeCount = $activeStudents->count();

        for ($index = 0; $index < 14; $index++) {
            $date = $anchor->copy()->addDays($index);
            $student = $activeStudents[$index % $activeCount];

            $primaryStart = $date->copy()->setHour(14)->setMinute(0)->setSecond(0);
            $appointmentService->create(
                workspaceId: $workspace->id,
                trainerUserId: $trainerId,
                studentId: (int) $student->id,
                data: [
                    'starts_at' => $primaryStart->toDateTimeString(),
                    'ends_at' => $primaryStart->copy()->addHour()->toDateTimeString(),
                    'location' => 'Vertex Studio Room 1',
                    'notes' => 'Demo one-off session',
                ],
            );

            if ($date->isWeekday()) {
                $secondaryStudent = $activeStudents[($index + 3) % $activeCount];
                $secondaryStart = $date->copy()->setHour(16)->setMinute(0)->setSecond(0);

                $appointmentService->create(
                    workspaceId: $workspace->id,
                    trainerUserId: $trainerId,
                    studentId: (int) $secondaryStudent->id,
                    data: [
                        'starts_at' => $secondaryStart->toDateTimeString(),
                        'ends_at' => $secondaryStart->copy()->addHour()->toDateTimeString(),
                        'location' => 'Vertex Studio Room 2',
                        'notes' => 'Demo one-off follow-up',
                    ],
                );
            }
        }
    }

    /**
     * @param  list<int>  $weekdayIsoList
     */
    private function countWeekdayOccurrences(Carbon $anchor, array $weekdayIsoList, int $windowDays): int
    {
        $allowed = collect($weekdayIsoList)->map(fn (int $day) => max(1, min(7, $day)))->all();
        $count = 0;

        for ($index = 0; $index < $windowDays; $index++) {
            $candidate = $anchor->copy()->addDays($index);
            if (in_array($candidate->dayOfWeekIso, $allowed, true)) {
                $count++;
            }
        }

        return max(1, $count);
    }
}
