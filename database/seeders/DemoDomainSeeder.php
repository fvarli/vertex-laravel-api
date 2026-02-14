<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDomainSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::query()->where('name', 'Vertex Demo Workspace')->firstOrFail();
        $trainer = User::query()->where('email', 'trainer@vertex.local')->firstOrFail();

        Student::query()->where('workspace_id', $workspace->id)->delete();

        $students = collect([
            ['full_name' => 'Ali Veli', 'phone' => '+905550000001', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Ayse Kaya', 'phone' => '+905550000002', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Mehmet Demir', 'phone' => '+905550000003', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Fatma Yildiz', 'phone' => '+905550000004', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Can Oz', 'phone' => '+905550000005', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Deniz Acar', 'phone' => '+905550000006', 'status' => Student::STATUS_ACTIVE],
            ['full_name' => 'Merve Tas', 'phone' => '+905550000007', 'status' => Student::STATUS_PASSIVE],
            ['full_name' => 'Emre Kurt', 'phone' => '+905550000008', 'status' => Student::STATUS_PASSIVE],
        ])->map(function (array $data) use ($workspace, $trainer) {
            return Student::query()->create([
                'workspace_id' => $workspace->id,
                'trainer_user_id' => $trainer->id,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'status' => $data['status'],
                'notes' => 'Demo seeded student',
            ]);
        });

        Program::query()->where('workspace_id', $workspace->id)->delete();

        $weekStart = Carbon::now('UTC')->startOfWeek();

        foreach ($students->take(3) as $index => $student) {
            $program = Program::query()->create([
                'workspace_id' => $workspace->id,
                'student_id' => $student->id,
                'trainer_user_id' => $trainer->id,
                'title' => 'Weekly Strength Plan '.($index + 1),
                'goal' => 'Improve consistency and progressive overload',
                'week_start_date' => $weekStart->toDateString(),
                'status' => $index === 0 ? Program::STATUS_ACTIVE : Program::STATUS_DRAFT,
            ]);

            $program->items()->createMany([
                ['day_of_week' => 1, 'order_no' => 1, 'exercise' => 'Squat', 'sets' => 4, 'reps' => 8, 'rest_seconds' => 90],
                ['day_of_week' => 3, 'order_no' => 1, 'exercise' => 'Bench Press', 'sets' => 4, 'reps' => 8, 'rest_seconds' => 90],
                ['day_of_week' => 5, 'order_no' => 1, 'exercise' => 'Deadlift', 'sets' => 3, 'reps' => 5, 'rest_seconds' => 120],
            ]);
        }

        Appointment::query()->where('workspace_id', $workspace->id)->delete();

        foreach ($students->take(6) as $index => $student) {
            $start = Carbon::now('UTC')->addDays($index + 1)->setHour(10 + ($index % 3))->setMinute(0)->setSecond(0);

            Appointment::query()->create([
                'workspace_id' => $workspace->id,
                'trainer_user_id' => $trainer->id,
                'student_id' => $student->id,
                'starts_at' => $start,
                'ends_at' => (clone $start)->addHour(),
                'status' => Appointment::STATUS_PLANNED,
                'location' => 'Vertex Studio Room '.(($index % 2) + 1),
                'notes' => 'Demo seeded appointment',
            ]);
        }
    }
}
