<?php

namespace Tests\Feature\Kobo;

use App\Services\Kobo\KoboSubmissionMapper;
use App\Support\Priority;
use App\Support\RiskAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KoboSubmissionMapperTest extends TestCase
{
    use RefreshDatabase;

    private KoboSubmissionMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
        $this->mapper = app(KoboSubmissionMapper::class);
    }

    public function test_it_maps_a_point_submission(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-point'));

        $this->assertSame('point', $mapped['geometry_type']);
        $this->assertSame(50.668773, $mapped['latitude']);
        $this->assertSame(12.94529, $mapped['longitude']);
        $this->assertSame('Point', $mapped['geojson']['type']);
        $this->assertSame('NEO-MTFKZC5V-G6W06', $mapped['melde_id']);
        $this->assertFalse($mapped['captured_offline']);
    }

    public function test_it_maps_a_polygon_submission_and_keeps_the_geometry(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-polygon'));

        $this->assertSame('polygon', $mapped['geometry_type']);
        $this->assertSame('Polygon', $mapped['geojson']['type']);
        $this->assertCount(5, $mapped['geojson']['coordinates'][0]);
        $this->assertSame('Kanadische Goldrute', $mapped['reported_species_raw']);
        $this->assertNotNull($mapped['species_id'], 'Die Art sollte auf einen Stammdatensatz zeigen.');
    }

    public function test_an_offline_submission_falls_back_to_the_gps_question(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-offline'));

        // No karten_* fields at all - the map screen was skipped.
        $this->assertNull($mapped['melde_id']);
        $this->assertTrue($mapped['captured_offline']);

        $this->assertSame(50.669036, $mapped['latitude']);
        $this->assertSame(12.94505, $mapped['longitude']);
        $this->assertSame('point', $mapped['geometry_type']);
    }

    public function test_it_derives_priority_hoch_from_a_human_risk(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-polygon'));

        $this->assertTrue($mapped['risk_flags'][RiskAssessment::HUMAN]);
        $this->assertContains(RiskAssessment::HUMAN, $mapped['editor_risk']);
        $this->assertSame(Priority::HIGH, $mapped['priority']);
    }

    public function test_it_derives_priority_zeitnah_from_a_protected_nature_risk(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-offline'));

        $this->assertTrue($mapped['risk_flags'][RiskAssessment::SENSITIVE_NATURE]);
        $this->assertSame(Priority::SOON, $mapped['priority']);
    }

    public function test_it_derives_priority_normal_when_no_risk_is_flagged(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-phone-contact'));

        $this->assertSame(Priority::NORMAL, $mapped['priority']);
        $this->assertSame([], $mapped['editor_risk']);
    }

    public function test_it_splits_an_email_out_of_the_single_contact_field(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-polygon'));

        $this->assertSame('oliver.wenzek@gmail.com', $mapped['reporter_email']);
        $this->assertNull($mapped['reporter_phone']);
        $this->assertTrue($mapped['reporter_contact_consent'], '"Ja, gerne" ist eine Zustimmung.');
    }

    public function test_it_recognises_a_phone_number_in_the_contact_field(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-phone-contact'));

        $this->assertNull($mapped['reporter_email']);
        $this->assertSame('01741525118', $mapped['reporter_phone']);
        $this->assertSame('01741525118', $mapped['reporter_contact_raw']);
    }

    public function test_a_refused_contact_is_not_consent(): void
    {
        $mapped = $this->mapper->map($this->koboFixture('kobo-submission-point'));

        $this->assertFalse($mapped['reporter_contact_consent']);
    }

    public function test_it_survives_a_completely_empty_payload(): void
    {
        $mapped = $this->mapper->map([]);

        $this->assertNull($mapped['melde_id']);
        $this->assertNull($mapped['latitude']);
        $this->assertNull($mapped['geometry_type']);
        $this->assertSame(Priority::NORMAL, $mapped['priority']);
        $this->assertTrue($mapped['captured_offline']);
    }

    public function test_it_ignores_broken_geojson_without_failing(): void
    {
        $payload = $this->koboFixture('kobo-submission-point');
        $payload['karten_geojson'] = '{not json at all';

        $mapped = $this->mapper->map($payload);

        $this->assertNull($mapped['geojson']);
        // The coordinates still arrive through karten_lat/karten_lon.
        $this->assertSame('point', $mapped['geometry_type']);
        $this->assertSame(50.668773, $mapped['latitude']);
    }
}
