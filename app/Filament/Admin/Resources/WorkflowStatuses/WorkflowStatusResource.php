<?php

namespace App\Filament\Admin\Resources\WorkflowStatuses;

use App\Filament\Admin\Resources\WorkflowStatuses\Pages;
use App\Models\WorkflowStatus;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Workflow stages are editable data - renaming or adding one needs no release.
 */
class WorkflowStatusResource extends Resource
{
    protected static ?string $model = WorkflowStatus::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'workflow-status';

    protected static ?string $modelLabel = 'Status';

    protected static ?string $pluralModelLabel = 'Workflow-Status';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::WORKFLOW_MANAGE) ?? false;
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
                        ->helperText('Technischer Name. Wird in Regeln und Auswertungen verwendet.')
                        // Changing a key breaks anything that references it.
                        ->disabled(fn (string $operation) => $operation === 'edit')
                        ->dehydrated(),
                    Textarea::make('description')->label('Beschreibung')->rows(2)->columnSpanFull(),
                    Select::make('color')
                        ->label('Farbe')
                        ->options([
                            'gray' => 'Grau', 'primary' => 'Grün', 'info' => 'Blau',
                            'warning' => 'Orange', 'danger' => 'Rot', 'success' => 'Erfolg',
                        ])
                        ->default('gray')
                        ->required(),
                    TextInput::make('sort_order')->label('Reihenfolge')->numeric()->default(0)->required(),
                ]),

            Section::make('Verhalten')
                ->columns(2)
                ->schema([
                    Toggle::make('is_initial')
                        ->label('Startstatus')
                        ->helperText('Neue Meldungen landen hier. Nur einmal vergeben.'),
                    Toggle::make('is_terminal')
                        ->label('Abschlussstatus')
                        ->helperText('Der Vorgang gilt damit als erledigt.'),
                    Toggle::make('requires_assignee')
                        ->label('Zuweisung erforderlich')
                        ->helperText('Der Übergang wird blockiert, solange niemand zugewiesen ist.'),
                    Toggle::make('requires_inspection')
                        ->label('Abgeschlossene Kontrolle erforderlich'),
                    Toggle::make('visible_to_aussendienst')->label('Für Außendienst sichtbar'),
                    Toggle::make('is_active')->label('Aktiv')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Bezeichnung')
                    ->badge()
                    ->color(fn (WorkflowStatus $record) => $record->color),
                TextColumn::make('key')->label('Schlüssel')->color('gray'),
                TextColumn::make('vorgaenge_count')->label('Vorgänge')->counts('vorgaenge')->badge()->color('gray'),
                IconColumn::make('is_initial')->label('Start')->boolean(),
                IconColumn::make('is_terminal')->label('Abschluss')->boolean(),
                IconColumn::make('requires_assignee')->label('Zuweisung nötig')->boolean()->toggleable(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()
                    ->label('Löschen')
                    // A status still in use cannot be removed - the foreign key
                    // on vorgaenge.status_id would reject it anyway.
                    ->visible(fn (WorkflowStatus $record) => $record->vorgaenge()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowStatuses::route('/'),
            'create' => Pages\CreateWorkflowStatus::route('/neu'),
            'edit' => Pages\EditWorkflowStatus::route('/{record}/bearbeiten'),
        ];
    }
}
