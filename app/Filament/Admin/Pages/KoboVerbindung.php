<?php

namespace App\Filament\Admin\Pages;

use App\Services\Kobo\KoboDiagnostics;
use App\Services\Kobo\KoboSyncService;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Shows whether KoboToolbox is actually delivering, and lets the office pull
 * the data over the API when it is not.
 */
class KoboVerbindung extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'kobo-verbindung';

    protected static ?string $title = 'Kobo-Verbindung';

    protected static ?string $navigationLabel = 'Kobo-Verbindung';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.kobo-verbindung';

    /** @var list<array{key: string, label: string, status: string, detail: string, hint: ?string}> */
    public array $checks = [];

    /** @var array{remote: ?int, local: int, missing: ?int, error: ?string} */
    public array $status = [];

    /** @var array{expected: int, stored: int, missing: int} */
    public array $attachments = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permissions::KOBO_VIEW_RAW) ?? false;
    }

    public function mount(): void
    {
        $this->refreshState();
    }

    public function refreshState(): void
    {
        $this->checks = KoboDiagnostics::fromConfig()->run();
        $this->status = app(KoboSyncService::class)->status();
        $this->attachments = app(KoboSyncService::class)->attachmentStatus();
    }

    public function webhookUrl(): string
    {
        return KoboDiagnostics::fromConfig()->webhookUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Aus KoboToolbox synchronisieren')
                ->icon('heroicon-o-arrow-down-on-square')
                ->color('primary')
                ->modalHeading('Meldungen aus KoboToolbox holen')
                ->modalDescription('Holt alle Meldungen, die hier noch nicht vorliegen. '
                    .'Bereits vorhandene werden übersprungen – ein zweiter Durchlauf schadet nicht.')
                ->modalSubmitActionLabel('Jetzt synchronisieren')
                ->schema([
                    DatePicker::make('since')
                        ->label('Nur Meldungen ab')
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        ->helperText('Leer lassen, um alle Meldungen zu prüfen.'),

                    Toggle::make('process')
                        ->label('Direkt zu Vorgängen verarbeiten')
                        ->default(true)
                        ->helperText('Aus bleibt es bei den Rohdaten.'),
                ])
                ->action(function (array $data): void {
                    $result = app(KoboSyncService::class)->sync(
                        since: $data['since'] ?? null,
                        process: (bool) ($data['process'] ?? true),
                        // Processed immediately: the office should see the new
                        // Vorgänge at once, even if no queue worker is running.
                        queue: false,
                    );

                    $this->refreshState();

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
                ->visible(fn () => app(KoboSyncService::class)->isConfigured()),

            Action::make('fetch_photos')
                ->label('Fehlende Fotos laden')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Fotos aus KoboToolbox laden')
                ->modalDescription(fn () => ($this->attachments['missing'] ?? 0).' Foto(s) fehlen noch. '
                    .'Der Download läuft direkt und kann bei vielen Bildern einen Moment dauern.')
                ->modalSubmitActionLabel('Jetzt laden')
                ->visible(fn () => ($this->attachments['missing'] ?? 0) > 0)
                ->action(function (): void {
                    // Limited per run so a single request cannot run for ever;
                    // pressing the button again continues where it stopped.
                    $result = app(KoboSyncService::class)->fetchMissingAttachments(maxSubmissions: 25);

                    $this->refreshState();

                    Notification::make()
                        ->success()
                        ->title($result['downloaded'].' Foto(s) geladen')
                        ->body($result['failed'] > 0
                            ? $result['failed'].' Meldung(en) konnten nicht geladen werden – siehe Protokoll.'
                            : ($this->attachments['missing'] > 0
                                ? 'Es fehlen noch '.$this->attachments['missing'].' Fotos – Knopf erneut drücken.'
                                : 'Alle Fotos sind jetzt übertragen.'))
                        ->send();
                }),

            Action::make('recheck')
                ->label('Erneut prüfen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $this->refreshState();

                    Notification::make()->success()->title('Prüfung aktualisiert')->send();
                }),
        ];
    }
}
