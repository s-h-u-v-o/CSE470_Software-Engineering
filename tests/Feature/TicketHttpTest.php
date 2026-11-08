<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F16/F18 through HTTP: the controller delegates and server-side rules still apply. */
class TicketHttpTest extends TestCase
{
    use RefreshDatabase;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $category = AssetCategory::create(['name' => 'ECG']);
        $this->asset = Asset::create([
            'asset_category_id' => $category->id, 'name' => 'ECG 01',
            'serial_number' => 'ECG-HTTP-1', 'lifecycle_state' => 'commissioned',
        ]);
    }

    public function test_report_triage_and_view_ticket_over_http(): void
    {
        $reporter = $this->user(Role::DEPARTMENT_USER);
        $engineer = $this->user(Role::ENGINEER);

        $this->post(route('tickets.store'), [
            'asset_id' => $this->asset->id, 'description' => 'Lead fault', 'severity' => 'critical',
            'acting_user_id' => $reporter->id,
        ])->assertRedirect();
        $ticket = Ticket::sole();

        $this->post(route('tickets.triage', $ticket), ['severity' => 'major', 'acting_user_id' => $engineer->id])
            ->assertSessionHas('success');
        $this->assertSame(['triaged', 'major'], [$ticket->fresh()->state, $ticket->fresh()->severity]);

        $this->get(route('tickets.index'))->assertOk()->assertSee('ECG 01');
        $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('Response (reported')->assertSee('triaged');
    }

    public function test_role_rules_apply_even_when_the_form_is_submitted_directly(): void
    {
        $reporter = $this->user(Role::DEPARTMENT_USER);
        $ticket = Ticket::create([
            'asset_id' => $this->asset->id, 'reported_by' => $reporter->id, 'state' => 'assigned',
            'severity' => 'minor', 'description' => 'x', 'occurred_at' => now(), 'recorded_at' => now(),
        ]);

        $this->post(route('tickets.reclassify', $ticket), [
            'severity' => 'critical', 'reason' => 'trying', 'acting_user_id' => $reporter->id,
        ])->assertSessionHas('error');

        $this->assertSame('minor', $ticket->fresh()->severity);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }
}
