<?php

namespace App\Filament\Admin\Resources\EmailTemplates;

use App\Filament\Admin\Resources\EmailTemplates\Pages;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use App\Services\Mail\TemplateRenderer;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Kommunikation';

    protected static ?string $slug = 'email-vorlagen';

    protected static ?string $modelLabel = 'E-Mail-Vorlage';

    protected static ?string $pluralModelLabel = 'E-Mail-Vorlagen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::EMAIL_TEMPLATE_MANAGE) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Bezeichnung')->required(),
                    TextInput::make('key')
                        ->label('Schlüssel')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),
                    TextInput::make('description')->label('Beschreibung')->columnSpanFull(),
                    TextInput::make('subject')->label('Betreff')->required()->columnSpanFull(),
                    Toggle::make('is_active')->label('Aktiv')->default(true),
                ]),

            Section::make('Inhalt')
                ->description('Platzhalter in doppelten geschweiften Klammern werden beim Versand ersetzt.')
                ->schema([
                    Text::make(fn (?EmailTemplate $record) => 'Verfügbare Platzhalter: '
                        .collect($record?->available_placeholders ?? [
                            'melde_id', 'nummer', 'status', 'art', 'ort',
                            'vorname', 'gemeldet_am', 'bearbeiter', 'ergebnis', 'app_name',
                        ])->map(fn ($p) => '{{ '.$p.' }}')->implode('  ')),

                    RichEditor::make('body_html')
                        ->label('Text')
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Bezeichnung')->searchable()->sortable(),
                TextColumn::make('key')->label('Schlüssel')->color('gray'),
                TextColumn::make('subject')->label('Betreff')->limit(50),
                TextColumn::make('notification_rules_count')
                    ->label('Regeln')
                    ->counts('notificationRules')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),

                Action::make('preview_send')
                    ->label('Testmail')
                    ->icon('heroicon-o-paper-airplane')
                    ->schema([
                        TextInput::make('recipient')
                            ->label('An')
                            ->email()
                            ->required()
                            ->default(fn () => auth()->user()?->email),
                    ])
                    ->action(function (EmailTemplate $record, array $data): void {
                        // Rendered without a Vorgang: placeholders resolve to
                        // empty so the recipient sees the template's own wording.
                        $rendered = app(TemplateRenderer::class)->render($record);

                        try {
                            Mail::to($data['recipient'])->send(
                                new TemplatedMail($rendered['subject'], $rendered['body'])
                            );
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Versand fehlgeschlagen')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Testmail versendet')
                            ->body('An '.$data['recipient'])
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/neu'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/bearbeiten'),
        ];
    }
}
