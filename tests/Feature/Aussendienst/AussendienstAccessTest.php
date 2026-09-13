<?php

namespace Tests\Feature\Aussendienst;

use App\Models\User;
use App\Models\Vorgang;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The field panel must never expose a case that is not assigned to the signed-in
 * user, and the panels must stay separated by role.
 */
class AussendienstAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    public function test_a_field_user_may_view_a_case_assigned_to_them(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);
        $vorgang = Vorgang::factory()->create(['assigned_to_id' => $field->getKey()]);

        $this->assertTrue($field->can('view', $vorgang));
    }

    public function test_a_field_user_may_not_view_someone_elses_case(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);
        $other = $this->userWith(Roles::AUSSENDIENST);
        $vorgang = Vorgang::factory()->create(['assigned_to_id' => $other->getKey()]);

        $this->assertFalse($field->can('view', $vorgang));
    }

    public function test_a_field_user_may_not_view_an_unassigned_case(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);
        $vorgang = Vorgang::factory()->create(['assigned_to_id' => null]);

        $this->assertFalse($field->can('view', $vorgang));
    }

    public function test_the_office_sees_every_case(): void
    {
        $office = $this->userWith(Roles::INNENDIENST);
        $vorgang = Vorgang::factory()->create(['assigned_to_id' => null]);

        $this->assertTrue($office->can('view', $vorgang));
    }

    public function test_a_field_user_cannot_enter_the_admin_panel(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);

        $this->assertFalse($field->canAccessPanel(filament()->getPanel('admin')));
        $this->assertTrue($field->canAccessPanel(filament()->getPanel('aussendienst')));
    }

    public function test_an_office_user_cannot_enter_the_field_panel(): void
    {
        $office = $this->userWith(Roles::INNENDIENST);

        $this->assertTrue($office->canAccessPanel(filament()->getPanel('admin')));
        $this->assertFalse($office->canAccessPanel(filament()->getPanel('aussendienst')));
    }

    public function test_a_deactivated_user_is_locked_out_of_both_panels(): void
    {
        $user = $this->userWith(Roles::ADMIN);
        $user->update(['is_active' => false]);

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
        $this->assertFalse($user->canAccessPanel(filament()->getPanel('aussendienst')));
    }

    public function test_a_field_user_may_not_reassign_or_delete(): void
    {
        $field = $this->userWith(Roles::AUSSENDIENST);
        $vorgang = Vorgang::factory()->create(['assigned_to_id' => $field->getKey()]);

        $this->assertFalse($field->can('assign', $vorgang));
        $this->assertFalse($field->can('delete', $vorgang));
        $this->assertFalse($field->can('update', $vorgang));

        // But they may do their actual job.
        $this->assertTrue($field->can('inspect', $vorgang));
        $this->assertTrue($field->can('uploadAttachment', $vorgang));
        $this->assertTrue($field->can('addNote', $vorgang));
    }

    public function test_a_super_admin_passes_every_gate(): void
    {
        $super = $this->userWith(Roles::SUPER_ADMIN);
        $vorgang = Vorgang::factory()->create();

        $this->assertTrue($super->can('view', $vorgang));
        $this->assertTrue($super->can('assign', $vorgang));
        $this->assertTrue($super->can('delete', $vorgang));
    }
}
