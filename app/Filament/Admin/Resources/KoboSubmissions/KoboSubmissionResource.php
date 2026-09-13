<?php

namespace App\Filament\Admin\Resources\KoboSubmissions;

use App\Filament\Admin\Resources\KoboSubmissions\Pages\ListKoboSubmissions;
use App\Filament\Admin\Resources\KoboSubmissions\Pages\ViewKoboSubmission;
use App\Jobs\ProcessKoboSubmission;
use App\Models\KoboSubmission;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only window onto the raw KoboToolbox payloads.
 *
 * This is the audit trail for everything the application derived. Nothing here
 * is editable - the only write action is re-running the mapping.
 */
class KoboSubmissionResource extends Resource
{
    protected static ?string $model = KoboSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'kobo-rohdaten';

    protected static ?string $modelLabel = 'Kobo-Rohdatensatz';

    protected static ?string $pluralModelLabel = 'Kobo-Rohdaten';

    protected static ?string $navigationLabel = 'Kobo-Rohdaten';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::KOBO_VIEW_RAW) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Empfang')
                ->columns(3)
                ->schema([
                    TextEntry::make('kobo_id')->label('Kobo-ID'),
                    TextEntry::make('asset_uid')->label('Formular (Asset)'),
                    TextEntry::make('kobo_uuid')->label('UUID')->copyable(),
                    TextEntry::make('received_at')->label('Empfangen')->dateTime('d.m.Y H:i:s'),
                    TextEntry::make('processed_at')->label('Verarbeitet')->dateTime('d.m.Y H:i:s')->placeholder('–'),
                    TextEntry::make('processing_status')
                        ->label('Verarbeitung')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            KoboSubmission::STATUS_PROCESSED => 'success',
                            KoboSubmission::STATUS_FAILED => 'danger',
                            KoboSubmission::STATUS_SKIPPED => 'gray',
                            default => 'warning',
                        }),
                    TextEntry::make('vorgang.local_nr')->label('Vorgang')->placeholder('keiner'),
                    TextEntry::make('processing_attempts')->label('Versuche'),
                    TextEntry::make('processing_error')
                        ->label('Fehler')
                        ->placeholder('keiner')
                        ->columnSpanFull(),
                ]),

            Section::make('Originaldaten')
                ->description('Unverändert so, wie KoboToolbox sie gesendet hat.')
                ->collapsible()
                ->schema([
                    KeyValueEntry::make('payload')
                        ->label('')
                        ->keyLabel('Feld')
                        ->valueLabel('Wert')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('received_at', 'desc')
            ->columns([
                TextColumn::make('kobo_id')->label('Kobo-ID')->searchable()->sortable(),
                TextColumn::make('received_at')->label('Empfangen')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('processing_status')
                    ->label('Verarbeitung')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        KoboSubmission::STATUS_PROCESSED => 'success',
                        KoboSubmission::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('vorgang.local_nr')->label('Vorgang')->placeholder('–')->searchable(),
                TextColumn::make('processing_error')->label('Fehler')->limit(60)->placeholder('–')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('processing_status')
                    ->label('Verarbeitung')
                    ->options([
                        KoboSubmission::STATUS_PENDING => 'Offen',
                        KoboSubmission::STATUS_PROCESSED => 'Verarbeitet',
                        KoboSubmission::STATUS_FAILED => 'Fehlgeschlagen',
                        KoboSubmission::STATUS_SKIPPED => 'Übersprungen',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ansehen'),

                Action::make('reprocess')
                    ->label('Neu verarbeiten')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Erzeugt einen Vorgang aus diesem Rohdatensatz, sofern noch keiner existiert.')
                    ->visible(fn (KoboSubmission $record) => (auth()->user()?->can(Permissions::KOBO_REPROCESS) ?? false)
                        && $record->vorgang_id === null)
                    ->action(function (KoboSubmission $record): void {
                        ProcessKoboSubmission::dispatch($record->getKey());

                        Notification::make()
                            ->success()
                            ->title('Verarbeitung eingeplant')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKoboSubmissions::route('/'),
            'view' => ViewKoboSubmission::route('/{record}'),
        ];
    }
}
