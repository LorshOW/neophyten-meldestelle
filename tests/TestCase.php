<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the real KoboToolbox or Nominatim. A test that
        // needs an HTTP response fakes it explicitly.
        Http::preventStrayRequests();
    }

    /** Loads a Kobo payload fixture as an array. */
    protected function koboFixture(string $name): array
    {
        $path = base_path("tests/Fixtures/{$name}.json");

        return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Seeds roles, workflow, species and templates - the app's baseline data. */
    protected function seedBaseline(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\WorkflowSeeder::class);
        $this->seed(\Database\Seeders\SpeciesSeeder::class);
        $this->seed(\Database\Seeders\EmailTemplateSeeder::class);
    }

    /** A one-pixel JPEG, enough to stand in for a Kobo photo download. */
    protected function fakeJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        );
    }
}
