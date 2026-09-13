<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Lets administrators configure SMTP in the application instead of the .env.
 *
 * Values stored in `settings` override config/mail at boot. Anything not set
 * there falls back to the .env, so a fresh installation keeps working before an
 * admin has entered anything.
 */
class MailConfigurationProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Never touch the database during install/migrate.
        if (app()->runningUnitTests() || ! $this->settingsTableExists()) {
            return;
        }

        try {
            $settings = Setting::all_values();
        } catch (Throwable) {
            return;
        }

        if (blank($settings['mail.host'] ?? null)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['mail.host'],
            'mail.mailers.smtp.port' => (int) ($settings['mail.port'] ?? 587),
            'mail.mailers.smtp.username' => $settings['mail.username'] ?? null,
            'mail.mailers.smtp.password' => $settings['mail.password'] ?? null,
            'mail.mailers.smtp.scheme' => $settings['mail.scheme'] ?? null,
            'mail.from.address' => $settings['mail.from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $settings['mail.from_name'] ?? config('mail.from.name'),
        ]);
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
