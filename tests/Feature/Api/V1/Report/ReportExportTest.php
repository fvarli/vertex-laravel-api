<?php

namespace Tests\Feature\Api\V1\Report;

use App\Models\Appointment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $trainer;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->ownerAdmin()->create();
        $this->trainer = User::factory()->trainer()->create();
        $this->workspace = Workspace::factory()->create(['owner_user_id' => $this->owner->id]);
        $this->workspace->users()->attach($this->owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $this->workspace->users()->attach($this->trainer->id, ['role' => 'trainer', 'is_active' => true]);
        $this->owner->update(['active_workspace_id' => $this->workspace->id]);

        $student = Student::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'status' => Student::STATUS_ACTIVE,
        ]);

        Appointment::factory()->create([
            'workspace_id' => $this->workspace->id,
            'trainer_user_id' => $this->trainer->id,
            'student_id' => $student->id,
            'starts_at' => '2026-06-10 10:00:00',
            'ends_at' => '2026-06-10 11:00:00',
            'status' => Appointment::STATUS_DONE,
        ]);
    }

    public function test_export_appointments_csv(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/appointments/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('appointments-report.csv');
    }

    public function test_export_appointments_pdf(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/appointments/export?format=pdf&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('appointments-report.pdf');
    }

    public function test_export_students_csv(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/students/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('students-report.csv');
    }

    public function test_export_programs_csv(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/programs/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('programs-report.csv');
    }

    public function test_export_reminders_csv(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/reminders/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('reminders-report.csv');
    }

    public function test_export_trainer_performance_csv(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/trainer-performance/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('trainer-performance-report.csv');
    }

    public function test_export_trainer_performance_pdf(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/trainer-performance/export?format=pdf&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertOk();
        $response->assertDownload('trainer-performance-report.pdf');
    }

    public function test_trainer_cannot_export_trainer_performance(): void
    {
        $this->trainer->update(['active_workspace_id' => $this->workspace->id]);
        Sanctum::actingAs($this->trainer);

        $response = $this->getJson('/api/v1/reports/trainer-performance/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_export(): void
    {
        $response = $this->getJson('/api/v1/reports/appointments/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $response->assertStatus(401);
    }

    public function test_csv_contains_header_row(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->get('/api/v1/reports/appointments/export?format=csv&date_from=2026-06-01&date_to=2026-06-30');

        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));
        $header = str_getcsv($lines[0]);

        $this->assertContains('Period', $header);
        $this->assertContains('Total', $header);
        $this->assertContains('Done', $header);
    }
}
