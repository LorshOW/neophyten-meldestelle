<?php

namespace App\Filament\Admin\Resources\OutgoingEmails;

use App\Filament\Admin\Resources\OutgoingEmails\Pages;
use App\Jobs\SendTemplatedEmail;
use App\Models\OutgoingEmail;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
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
 * Delivery log. Read-only evidence of what was sent to whom and when.
 */
class OutgoingEmailResource extends Resource
{
    protected static ?string $model = OutgoingEmail::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|UnitEnum|null $navigationGroup = 'Kommunikation';

    protected static ?string $slug = 'email-protokoll';

    protected static ?string $modelLabel = 'E-Mail';

    protected static ?string $pluralModelLabel = 'E-Mail-Protokoll';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::EMAIL_LOG_VIEW) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = OutgoingEmail::failed()->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->schema([
                    TextEntry::make('recipient')->label('Empfänger')->copyable(),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            OutgoingEmail::STATUS_SENT => 'success',
                            OutgoingEmail::STATUS_FAILED => 'danger',
                            default => 'warning',
                        }),
                    TextEntry::make('sent_at')->label('Versendet')->dateTime('d.m.Y H:i:s')->placeholder('–'),
                    TextEntry::make('vorgang.local_nr')->label('Vorgang')->placeholder('–'),
                    TextEntry::make('template.name')->label('Vorlage')->placeholder('–'),
                    TextEntry::make('rule.name')->label('Regel')->placeholder('–'),
                    TextEntry::make('subject')->label('Betreff')->columnSpanFull(),
                    TextEntry::make('error')->label('Fehler')->placeholder('keiner')->columnSpanFull(),
                ]),

            Section::make('Inhalt')
                ->collapsible()
                ->schema([
                    TextEntry::make('body_html')->label('')->html()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Zeitpunkt')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('recipient')->label('Empfänger')->searchable(),
                TextColumn::make('subject')->label('Betreff')->limit(45)->searchable(),
                TextColumn::make('vorgang.local_nr')->label('Vorgang')->placeholder('–')->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        OutgoingEmail::STATUS_SENT => 'success',
                        OutgoingEmail::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        OutgoingEmail::STATUS_QUEUED => 'In Warteschlange',
                        OutgoingEmail::STATUS_SENT => 'Versendet',
                        OutgoingEmail::STATUS_FAILED => 'Fehlgeschlagen',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ansehen'),

                Action::make('retry')
                    ->label('Erneut senden')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (OutgoingEmail $record) => $record->status === OutgoingEmail::STATUS_FAILED)
                    ->action(function (OutgoingEmail $record): void {
                        $record->update(['status' => OutgoingEmail::STATUS_QUEUED, 'error' => null]);

                        SendTemplatedEmail::dispatch($record->getKey());

                        Notification::make()->success()->title('Erneut eingeplant')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutgoingEmails::route('/'),
            'view' => Pages\ViewOutgoingEmail::route('/{record}'),
        ];
    }
}
