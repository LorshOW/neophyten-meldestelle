<?php

namespace App\Filament\Admin\Actions;

use App\Services\Kobo\KoboSyncService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;

/**
 * "Fetch from KoboToolbox" - the manual fallback for whenever the webhook has
 * not delivered. Shared by the case list and the raw-data list so the office
 * finds it where it is working.
 */
class KoboSyncAction
{
    public static function make(string $name = 'kobo_sync'): Action
    {
        return Action::make($name)
            ->label('Aus KoboToolbox holen')
            ->icon('heroicon-o-arrow-down-on-square')
            ->color('gray')
            ->modalHeading('Meldungen aus KoboToolbox holen')
            ->modalDescription('Holt alle Meldungen, die hier noch nicht vorliegen. '
                .'Bereits vorhandene werden übersprungen.')
            ->modalSubmitActionLabel('Jetzt holen')
            ->schema([
                DatePicker::make('since')
                    ->label('Nur Meldungen ab')
                    ->native(false)
                    ->displayFormat('d.m.Y')
                    ->helperText('Leer lassen, um alle Meldungen zu prüfen.'),
            ])
            ->action(function (array $data): void {
                $result = app(KoboSyncService::class)->sync(
                    since: $data['since'] ?? null,
                    process: true,
                    // Processed immediately, so the new Vorgänge are visible at
                    // once even when no queue worker is running.
                    queue: false,
                );

                if ($result->failed()) {
                    Notification::make()
                        ->danger()
                        ->title('Synchronisierung fehlgeschlagen')
                        ->body($result->summary())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($result->imported > 0
                        ? $result->imported.' neue Meldung(en) übernommen'
                        : 'Alles aktuell')
                    ->body($result->summary())
                    ->send();
            })
            ->visible(fn () => app(KoboSyncService::class)->isConfigured());
    }
}
