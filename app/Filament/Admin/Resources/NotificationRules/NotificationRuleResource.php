<?php

namespace App\Filament\Admin\Resources\NotificationRules;

use App\Filament\Admin\Resources\NotificationRules\Pages;
use App\Models\NotificationRule;
use App\Models\WorkflowStatus;
use App\Support\Permissions;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Connects a workflow event to a template and a recipient - this is what makes
 * the automatic emails configurable instead of hardcoded.
 */
class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationRule::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Kommunikation';

    protected static ?string $slug = 'benachrichtigungen';

    protected static ?string $modelLabel = 'Benachrichtigungsregel';

    protected static ?string $pluralModelLabel = 'Benachrichtigungen';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::NOTIFICATION_RULE_MANAGE) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Bezeichnung')->required()->columnSpanFull(),

            Select::make('trigger_event')
                ->label('Auslöser')
                ->options(NotificationRule::eventOptions())
                ->required()
                ->live(),

            Select::make('status_id')
                ->label('Nur bei diesem Status')
                ->options(fn () => WorkflowStatus::ordered()->pluck('name', 'id'))
                ->placeholder('Bei jedem Status')
                ->visible(fn (Get $get) => $get('trigger_event') === NotificationRule::EVENT_STATUS_CHANGED)
                ->helperText('Ohne Auswahl wird bei JEDER Statusänderung gemailt.'),

            Select::make('email_template_id')
                ->label('E-Mail-Vorlage')
                ->relationship('template', 'name')
                ->required()
                ->preload(),

            Select::make('recipient_type')
                ->label('Empfänger')
                ->options(NotificationRule::recipientOptions())
                ->required()
                ->live(),

            Select::make('recipient_value')
                ->label('Rolle')
                ->options(Roles::labels())
                ->required()
                ->visible(fn (Get $get) => $get('recipient_type') === NotificationRule::RECIPIENT_ROLE),

            TextInput::make('recipient_value')
                ->label('E-Mail-Adresse(n)')
                ->required()
                ->helperText('Mehrere durch Komma trennen.')
                ->visible(fn (Get $get) => $get('recipient_type') === NotificationRule::RECIPIENT_FIXED),

            Toggle::make('is_active')->label('Aktiv')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Bezeichnung')->searchable()->wrap(),
                TextColumn::make('trigger_event')
                    ->label('Auslöser')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => NotificationRule::eventOptions()[$state] ?? $state),
                TextColumn::make('status.name')->label('Status')->badge()->placeholder('jeder'),
                TextColumn::make('recipient_type')
                    ->label('Empfänger')
                    ->formatStateUsing(fn (string $state) => NotificationRule::recipientOptions()[$state] ?? $state),
                TextColumn::make('template.name')->label('Vorlage'),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()->label('Löschen'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationRules::route('/'),
            'create' => Pages\CreateNotificationRule::route('/neu'),
            'edit' => Pages\EditNotificationRule::route('/{record}/bearbeiten'),
        ];
    }
}
