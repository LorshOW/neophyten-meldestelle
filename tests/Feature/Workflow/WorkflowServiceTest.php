<?php

namespace Tests\Feature\Workflow;

use App\Exceptions\WorkflowException;
use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Services\Workflow\WorkflowService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
        $this->workflow = app(WorkflowService::class);
    }

    private function statusFor(string $key): WorkflowStatus
    {
        return WorkflowStatus::where('key', $key)->sole();
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    public function test_it_moves_a_case_forward_and_records_the_change(): void
    {
        $vorgang = Vorgang::factory()->create();
        $actor = $this->userWith(Roles::INNENDIENST);

        $this->workflow->transition($vorgang, $this->statusFor('inpruefung'), $actor);

        $this->assertSame('inpruefung', $vorgang->fresh()->status->key);

        $history = $vorgang->statusHistories()->sole();
        $this->assertSame('neu', $history->fromStatus->key);
        $this->assertSame('inpruefung', $history->toStatus->key);
        $this->assertSame($actor->getKey(), $history->user_id);
    }

    public function test_it_blocks_a_transition_that_is_not_defined(): void
    {
        $vorgang = Vorgang::factory()->create();

        // Neu -> Bekämpft is not a defined move.
        $this->expectException(WorkflowException::class);

        $this->workflow->transition($vorgang, $this->statusFor('bekaempft'), $this->userWith(Roles::INNENDIENST));
    }

    public function test_it_blocks_in_bearbeitung_without_an_assignee(): void
    {
        $vorgang = Vorgang::factory()->inStatus('bestaetigt')->create(['assigned_to_id' => null]);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('zugewiesen');

        $this->workflow->transition($vorgang, $this->statusFor('inbearbeitung'), $this->userWith(Roles::INNENDIENST));
    }

    public function test_it_allows_in_bearbeitung_once_assigned(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);
        $vorgang = Vorgang::factory()->inStatus('bestaetigt')->create(['assigned_to_id' => $field->getKey()]);

        $this->workflow->transition($vorgang, $this->statusFor('inbearbeitung'), $this->userWith(Roles::INNENDIENST));

        $this->assertSame('inbearbeitung', $vorgang->fresh()->status->key);
    }

    public function test_it_requires_a_note_where_the_transition_demands_one(): void
    {
        $vorgang = Vorgang::factory()->create();

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Begründung');

        // The wildcard "Kein Handlungsbedarf" transition requires a note.
        $this->workflow->transition($vorgang, $this->statusFor('keinhandlungsbedarf'), $this->userWith(Roles::INNENDIENST));
    }

    public function test_a_note_satisfies_that_requirement(): void
    {
        $vorgang = Vorgang::factory()->create();

        $this->workflow->transition(
            $vorgang,
            $this->statusFor('keinhandlungsbedarf'),
            $this->userWith(Roles::INNENDIENST),
            'Bestand bereits entfernt.',
        );

        $this->assertSame('keinhandlungsbedarf', $vorgang->fresh()->status->key);
    }

    public function test_an_aussendienst_user_may_not_run_an_office_transition(): void
    {
        $vorgang = Vorgang::factory()->create();

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('Berechtigung');

        $this->workflow->transition($vorgang, $this->statusFor('inpruefung'), $this->userWith(Roles::AUSSENDIENST));
    }

    public function test_reaching_a_terminal_status_closes_the_case(): void
    {
        $actor = $this->userWith(Roles::INNENDIENST);
        $vorgang = Vorgang::factory()->create();

        $this->workflow->transition($vorgang, $this->statusFor('keinhandlungsbedarf'), $actor, 'Kein Bestand mehr.');

        $fresh = $vorgang->fresh();

        $this->assertTrue($fresh->isClosed());
        $this->assertNotNull($fresh->closed_at);
        $this->assertSame($actor->getKey(), $fresh->closed_by_id);
    }

    public function test_reopening_a_closed_case_clears_the_closing_stamp(): void
    {
        $actor = $this->userWith(Roles::INNENDIENST);
        $vorgang = Vorgang::factory()->inStatus('keinhandlungsbedarf')->create([
            'closed_at' => now(),
            'closed_by_id' => $actor->getKey(),
        ]);

        $this->workflow->transition($vorgang, $this->statusFor('inpruefung'), $actor, 'Neue Hinweise.');

        $fresh = $vorgang->fresh();

        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->closed_by_id);
    }

    public function test_available_transitions_are_filtered_by_permission(): void
    {
        $vorgang = Vorgang::factory()->create();

        $officeTargets = $this->workflow
            ->availableTransitions($vorgang, $this->userWith(Roles::INNENDIENST))
            ->pluck('toStatus.key');

        $fieldTargets = $this->workflow
            ->availableTransitions($vorgang, $this->userWith(Roles::AUSSENDIENST))
            ->pluck('toStatus.key');

        $this->assertContains('inpruefung', $officeTargets);
        $this->assertNotContains('inpruefung', $fieldTargets);
    }

    public function test_transitioning_into_the_same_status_is_a_no_op(): void
    {
        $vorgang = Vorgang::factory()->create();

        $this->workflow->transition($vorgang, $this->statusFor('neu'), $this->userWith(Roles::INNENDIENST));

        $this->assertSame(0, $vorgang->statusHistories()->count());
    }
}
