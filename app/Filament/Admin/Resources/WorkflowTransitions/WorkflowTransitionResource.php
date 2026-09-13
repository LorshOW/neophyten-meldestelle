<?php

namespace App\Filament\Admin\Resources\WorkflowTransitions;

use App\Filament\Admin\Resources\WorkflowTransitions\Pages;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Which status may follow which, and who is allowed to make the move.
 */
class WorkflowTransitionResource extends Resource
{
    protected static ?string $model = WorkflowTransition::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'workflow-uebergaenge';

    protected static ?string $modelLabel = 'Übergang';

    protected static ?string $pluralModelLabel = 'Workflow-Übergänge';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::WORKFLOW_MANAGE) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('from_status_id')
                ->label('Von Status')
                ->options(fn () => WorkflowStatus::ordered()->pluck('name', 'id'))
                ->placeholder('Aus jedem Status heraus')
                ->helperText('Leer lassen, damit der Übergang überall verfügbar ist.'),

            Select::make('to_status_id')
                ->label('Nach Status')
                ->options(fn () => WorkflowStatus::ordered()->pluck('name', 'id'))
                ->required(),

            TextInput::make('label')
                ->label('Beschriftung der Schaltfläche')
                ->required()
                ->helperText('z. B. „In Prüfung nehmen“'),

            Select::make('required_permission')
                ->label('Erforderliche Berechtigung')
                ->options([
                    Permissions::VORGANG_TRANSITION => 'Status ändern (Innendienst)',
                    Permissions::VORGANG_TRANSITION_FIELD => 'Status ändern (Außendienst)',
                ])
                ->placeholder('Keine besondere Berechtigung'),

            Toggle::make('requires_note')
                ->label('Begründung verpflichtend')
                ->helperText('Der Übergang wird ohne Notiz abgelehnt.'),

            TextInput::make('sort_order')->label('Reihenfolge')->numeric()->default(0)->required(),

            Toggle::make('is_active')->label('Aktiv')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('fromStatus.name')
                    ->label('Von')
                    ->badge()
                    ->color(fn (WorkflowTransition $record) => $record->fromStatus?->color ?? 'gray')
                    ->placeholder('jeder Status'),
                TextColumn::make('toStatus.name')
                    ->label('Nach')
                    ->badge()
                    ->color(fn (WorkflowTransition $record) => $record->toStatus?->color ?? 'gray'),
                TextColumn::make('label')->label('Beschriftung')->searchable(),
                TextColumn::make('required_permission')
                    ->label('Berechtigung')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Permissions::VORGANG_TRANSITION => 'Innendienst',
                        Permissions::VORGANG_TRANSITION_FIELD => 'Außendienst',
                        default => 'alle',
                    })
                    ->badge()
                    ->color('gray'),
                IconColumn::make('requires_note')->label('Notiz nötig')->boolean(),
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
            'index' => Pages\ListWorkflowTransitions::route('/'),
            'create' => Pages\CreateWorkflowTransition::route('/neu'),
            'edit' => Pages\EditWorkflowTransition::route('/{record}/bearbeiten'),
        ];
    }
}
