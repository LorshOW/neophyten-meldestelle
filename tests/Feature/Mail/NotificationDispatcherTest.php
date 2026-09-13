<?php

namespace Tests\Feature\Mail;

use App\Events\VorgangCreated;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Models\OutgoingEmail;
use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Services\Workflow\WorkflowService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
        Mail::fake();
    }

    public function test_a_new_case_triggers_a_confirmation_to_a_consenting_reporter(): void
    {
        $vorgang = Vorgang::factory()->create([
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
        ]);

        VorgangCreated::dispatch($vorgang);

        $log = OutgoingEmail::where('vorgang_id', $vorgang->getKey())->sole();

        $this->assertSame('melder@example.test', $log->recipient);
        $this->assertStringContainsString($vorgang->melde_id, $log->subject);

        Mail::assertSent(TemplatedMail::class);
    }

    public function test_no_mail_goes_to_a_reporter_who_refused_contact(): void
    {
        $vorgang = Vorgang::factory()->withoutConsent()->create([
            'reporter_email' => 'melder@example.test',
        ]);

        VorgangCreated::dispatch($vorgang);

        $this->assertSame(0, OutgoingEmail::count());
        Mail::assertNothingSent();
    }

    public function test_no_mail_goes_out_when_no_address_was_left(): void
    {
        $vorgang = Vorgang::factory()->withoutContact()->create();

        VorgangCreated::dispatch($vorgang);

        $this->assertSame(0, OutgoingEmail::count());
    }

    public function test_assigning_a_case_notifies_the_assignee(): void
    {
        $field = User::factory()->create(['email' => 'feld@example.test']);
        $field->syncRoles([Roles::AUSSENDIENST]);

        $vorgang = Vorgang::factory()->withoutContact()->create();

        app(WorkflowService::class)->assign($vorgang, $field);

        $log = OutgoingEmail::sole();

        $this->assertSame('feld@example.test', $log->recipient);
        $this->assertStringContainsString($vorgang->melde_id, $log->subject);
    }

    public function test_closing_a_case_notifies_a_consenting_reporter(): void
    {
        $office = User::factory()->create();
        $office->syncRoles([Roles::INNENDIENST]);

        $vorgang = Vorgang::factory()->create([
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
        ]);

        OutgoingEmail::query()->delete();

        app(WorkflowService::class)->transition(
            $vorgang,
            WorkflowStatus::where('key', 'keinhandlungsbedarf')->sole(),
            $office,
            'Nicht mehr vorhanden.',
        );

        $log = OutgoingEmail::sole();

        $this->assertSame('melder@example.test', $log->recipient);
        $this->assertStringContainsString('abgeschlossen', $log->subject);
    }

    public function test_placeholders_are_replaced_and_never_leak_template_syntax(): void
    {
        $vorgang = Vorgang::factory()->create([
            'reporter_name' => 'Jonas',
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
            'geo_label' => '09419 | Thum | Erzgebirgskreis',
        ]);

        VorgangCreated::dispatch($vorgang);

        $log = OutgoingEmail::sole();

        $this->assertStringContainsString('Jonas', $log->body_html);
        $this->assertStringContainsString('09419 | Thum', $log->body_html);
        $this->assertStringNotContainsString('{{', $log->body_html);
        $this->assertStringNotContainsString('}}', $log->body_html);
    }

    public function test_a_template_cannot_execute_php_through_placeholders(): void
    {
        // An administrator with a compromised or careless template must not be
        // able to turn the editor into code execution.
        EmailTemplate::where('key', 'eingang_bestaetigung')->update([
            'body_html' => 'Hallo {{ vorname }} - {{ 7*7 }} - @php echo "x"; @endphp',
        ]);

        $vorgang = Vorgang::factory()->create([
            'reporter_name' => 'Jonas',
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
        ]);

        VorgangCreated::dispatch($vorgang);

        $body = OutgoingEmail::sole()->body_html;

        $this->assertStringContainsString('Hallo Jonas', $body);
        $this->assertStringNotContainsString('49', $body, 'Ausdrücke dürfen nicht ausgewertet werden.');
        $this->assertStringContainsString('@php', $body, 'Blade-Direktiven bleiben Text.');
    }

    public function test_reporter_supplied_html_is_escaped_in_the_body(): void
    {
        $vorgang = Vorgang::factory()->create([
            'reporter_name' => '<script>alert(1)</script>',
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
        ]);

        VorgangCreated::dispatch($vorgang);

        $body = OutgoingEmail::sole()->body_html;

        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_an_inactive_rule_sends_nothing(): void
    {
        NotificationRule::query()->update(['is_active' => false]);

        $vorgang = Vorgang::factory()->create([
            'reporter_email' => 'melder@example.test',
            'reporter_contact_consent' => true,
        ]);

        VorgangCreated::dispatch($vorgang);

        $this->assertSame(0, OutgoingEmail::count());
    }
}
