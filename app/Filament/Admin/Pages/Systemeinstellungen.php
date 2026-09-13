<?php

namespace App\Filament\Admin\Pages;

use App\Mail\TemplatedMail;
use App\Models\Setting;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * SMTP and KoboToolbox credentials, editable without touching the .env.
 *
 * Secrets are stored encrypted and never sent back to the browser; an empty
 * password field means "keep what is stored".
 */
class Systemeinstellungen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'einstellungen';

    protected static ?string $title = 'Systemeinstellungen';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.systemeinstellungen';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Secrets are write-only: stored, never echoed back. */
    private const SECRET_KEYS = ['mail.password', 'kobo.api_token', 'kobo.webhook_secret'];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permissions::SETTINGS_MANAGE) ?? false;
    }

    public function mount(): void
    {
        $stored = Setting::all_values();

        foreach (self::SECRET_KEYS as $key) {
            unset($stored[$key]);
        }

        $this->form->fill($stored);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('E-Mail-Versand (SMTP)')
                    ->description('Überschreibt die Werte aus der .env, sobald ein Host eingetragen ist.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('mail.host')->label('Server')->placeholder('smtp.example.org'),
                        TextInput::make('mail.port')->label('Port')->numeric()->placeholder('587'),
                        TextInput::make('mail.username')->label('Benutzername'),
                        TextInput::make('mail.password')
                            ->label('Passwort')
                            ->password()
                            ->revealable()
                            ->placeholder('unverändert lassen')
                            ->helperText('Leer lassen, um das gespeicherte Passwort zu behalten.'),
                        TextInput::make('mail.scheme')->label('Verschlüsselung')->placeholder('tls'),
                        TextInput::make('mail.from_address')->label('Absenderadresse')->email(),
                        TextInput::make('mail.from_name')->label('Absendername')->columnSpanFull(),
                    ]),

                Section::make('KoboToolbox')
                    ->columns(2)
                    ->schema([
                        TextInput::make('kobo.asset_uid')
                            ->label('Asset-UID')
                            ->placeholder(config('kobo.asset_uid')),
                        TextInput::make('kobo.api_token')
                            ->label('API-Token')
                            ->password()
                            ->revealable()
                            ->placeholder('unverändert lassen')
                            ->helperText('Nötig, um Fotos aus KoboToolbox zu laden.'),
                        TextInput::make('kobo.webhook_secret')
                            ->label('Webhook-Geheimnis')
                            ->password()
                            ->revealable()
                            ->placeholder('unverändert lassen')
                            ->helperText('Muss als Header im Kobo-REST-Service hinterlegt sein.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Aufbewahrung')
                    ->schema([
                        TextInput::make('meldestelle.retention_days')
                            ->label('Abgeschlossene Vorgänge löschen nach (Tagen)')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leer lassen, um nichts automatisch zu löschen.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $isSecret = in_array($key, self::SECRET_KEYS, true);

            // An empty secret means "leave the stored one alone".
            if ($isSecret && blank($value)) {
                continue;
            }

            Setting::put(
                key: $key,
                value: $value,
                group: explode('.', $key)[0],
                encrypted: $isSecret,
            );
        }

        Notification::make()->success()->title('Einstellungen gespeichert')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Speichern')->action('save'),

            Action::make('test_mail')
                ->label('Testmail senden')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->schema([
                    TextInput::make('recipient')
                        ->label('An')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email),
                ])
                ->action(function (array $data): void {
                    try {
                        Mail::to($data['recipient'])->send(new TemplatedMail(
                            'Testmail der Meldestelle',
                            '<p>Wenn du diese Nachricht siehst, funktioniert der E-Mail-Versand.</p>',
                        ));
                    } catch (Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Versand fehlgeschlagen')
                            ->body($e->getMessage())
                            ->send();

                        throw new Halt;
                    }

                    Notification::make()->success()->title('Testmail versendet')->send();
                }),
        ];
    }
}
