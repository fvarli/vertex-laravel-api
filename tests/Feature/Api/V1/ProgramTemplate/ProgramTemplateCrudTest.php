<?php

namespace Tests\Feature\Api\V1\ProgramTemplate;

use App\Models\ProgramTemplate;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgramTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_template_detail(): void
    {
        [$owner, $workspace] = $this->seedContext();

        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'strength-base',
            'title' => 'Strength Base Program',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/v1/program-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'strength-base');
    }

    public function test_owner_can_update_template(): void
    {
        [$owner, $workspace] = $this->seedContext();

        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'strength-base',
            'title' => 'Strength Base Program',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson("/api/v1/program-templates/{$template->id}", [
            'name' => 'hypertrophy-v2',
            'title' => 'Hypertrophy Phase Two',
            'goal' => 'Maximize muscle growth',
            'items' => [
                [
                    'day_of_week' => 1,
                    'order_no' => 1,
                    'exercise' => 'Bench Press',
                    'sets' => 4,
                    'reps' => 10,
                    'rest_seconds' => 90,
                    'notes' => 'Controlled tempo',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'hypertrophy-v2')
            ->assertJsonPath('data.title', 'Hypertrophy Phase Two');
    }

    public function test_owner_can_delete_template(): void
    {
        [$owner, $workspace] = $this->seedContext();

        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'strength-base',
            'title' => 'Strength Base Program',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->deleteJson("/api/v1/program-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('program_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_search_filters_templates_by_name(): void
    {
        [$owner, $workspace] = $this->seedContext();

        ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'strength-base',
            'title' => 'Strength Base Program',
        ]);

        ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'cardio-endurance',
            'title' => 'Cardio Endurance Plan',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/program-templates?search=strength');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data.data') ?? $response->json('data'))
            ->pluck('name')
            ->toArray();

        $this->assertContains('strength-base', $names);
        $this->assertNotContains('cardio-endurance', $names);
    }

    public function test_trainer_from_other_workspace_cannot_view_template(): void
    {
        [$owner, $workspace] = $this->seedContext();

        $template = ProgramTemplate::factory()->create([
            'workspace_id' => $workspace->id,
            'trainer_user_id' => $owner->id,
            'name' => 'strength-base',
            'title' => 'Strength Base Program',
        ]);

        $otherOwner = User::factory()->ownerAdmin()->create();
        $otherWorkspace = Workspace::factory()->create(['owner_user_id' => $otherOwner->id]);
        $otherWorkspace->users()->attach($otherOwner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $otherOwner->update(['active_workspace_id' => $otherWorkspace->id]);

        Sanctum::actingAs($otherOwner);

        $response = $this->getJson("/api/v1/program-templates/{$template->id}");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    private function seedContext(): array
    {
        $owner = User::factory()->ownerAdmin()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $owner->id]);
        $workspace->users()->attach($owner->id, ['role' => 'owner_admin', 'is_active' => true]);
        $owner->update(['active_workspace_id' => $workspace->id]);

        return [$owner, $workspace];
    }
}
