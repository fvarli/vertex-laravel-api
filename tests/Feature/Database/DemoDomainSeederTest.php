<?php

namespace Tests\Feature\Database;

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AppointmentSeries;
use App\Models\Program;
use App\Models\Student;
use App\Models\Workspace;
use Database\Seeders\DemoDomainSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoDomainSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_domain_seeder_builds_dynamic_two_week_data_with_series_and_one_off_appointments(): void
    {
        $this->seed([
            DemoUsersSeeder::class,
            DemoWorkspaceSeeder::class,
            DemoDomainSeeder::class,
        ]);

        $workspace = Workspace::query()->where('name', 'Vertex Demo Workspace')->firstOrFail();
        $windowStart = Carbon::now('UTC')->startOfDay();
        $windowEnd = $windowStart->copy()->addDays(13)->endOfDay();

        $this->assertSame(12, Student::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(5, Program::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(3, AppointmentSeries::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(36, Appointment::query()->where('workspace_id', $workspace->id)->count());
        $this->assertSame(72, AppointmentReminder::query()->where('workspace_id', $workspace->id)->count());

        $allInWindow = Appointment::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->count();

        $this->assertSame(36, $allInWindow);
        $this->assertSame(12, Appointment::query()->where('workspace_id', $workspace->id)->whereNotNull('series_id')->count());
        $this->assertSame(24, Appointment::query()->where('workspace_id', $workspace->id)->whereNull('series_id')->count());

        $this->assertGreaterThan(
            0,
            AppointmentReminder::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', AppointmentReminder::STATUS_MISSED)
                ->count()
        );
        $this->assertGreaterThan(
            0,
            AppointmentReminder::query()
                ->where('workspace_id', $workspace->id)
                ->where('status', AppointmentReminder::STATUS_PENDING)
                ->count()
        );
    }

    public function test_demo_domain_seeder_rebuilds_without_data_growth_on_repeated_runs(): void
    {
        $this->seed([
            DemoUsersSeeder::class,
            DemoWorkspaceSeeder::class,
            DemoDomainSeeder::class,
        ]);

        $workspace = Workspace::query()->where('name', 'Vertex Demo Workspace')->firstOrFail();
        $firstSnapshot = $this->counts($workspace->id);

        $this->seed([DemoDomainSeeder::class]);

        $this->assertSame($firstSnapshot, $this->counts($workspace->id));
    }

    /**
     * @return array{students:int,programs:int,series:int,appointments:int,reminders:int}
     */
    private function counts(int $workspaceId): array
    {
        return [
            'students' => Student::query()->where('workspace_id', $workspaceId)->count(),
            'programs' => Program::query()->where('workspace_id', $workspaceId)->count(),
            'series' => AppointmentSeries::query()->where('workspace_id', $workspaceId)->count(),
            'appointments' => Appointment::query()->where('workspace_id', $workspaceId)->count(),
            'reminders' => AppointmentReminder::query()->where('workspace_id', $workspaceId)->count(),
        ];
    }
}
