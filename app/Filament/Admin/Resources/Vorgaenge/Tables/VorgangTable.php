<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Tables;

use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Services\Workflow\WorkflowService;
use App\Support\Permissions;
use App\Support\Priority;
use App\Support\Roles;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * The case list, column for column as in the reference monitor application
 * (invasive-pflanzen-monitor.html, <thead> and renderTable()):
 *
 *   Auswahl · Details · Nr. · Datum · Art · Ort · Standort · Typ · Größe ·
 *   Priorität · Status · Notiz (intern) · Karte
 *
 * Priorität, Status and Notiz stay editable directly in the row, exactly as in
 * the prototype - with one difference: the status dropdown does not write the
 * column. It goes through WorkflowService, so guards, permissions and the audit
 * trail cannot be bypassed by an inline edit.
 */
class VorgangTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('local_nr')
                    ->label('Nr.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('submitted_at')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('reported_species_raw')
                    ->label('Art')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->wrap(),

                TextColumn::make('geo_label')
                    ->label('Ort')
                    ->searchable(['geo_plz', 'geo_ort', 'geo_kreis'])
                    ->sortable()
                    // Mirrors the monitor's "wird ermittelt…" hint.
                    ->placeholder(fn (Vorgang $record) => $record->latitude !== null
                        ? 'wird ermittelt …'
                        : '–'),

                TextColumn::make('location_summary')
                    ->label('Standort')
                    ->searchable()
                    ->sortable()
                    ->placeholder('–')
                    ->wrap()
                    ->limit(60),

                TextColumn::make('geometry_type')
                    ->label('Typ')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'polygon' => 'Fläche',
                        'point' => 'Punkt',
                        default => 'Unbekannt',
                    })
                    ->color(fn (?string $state) => $state === 'polygon' ? 'info' : 'gray'),

                TextColumn::make('size_category')
                    ->label('Größe')
                    ->sortable()
                    ->placeholder('–')
                    ->wrap()
                    ->limit(45),

                SelectColumn::make('priority_override')
                    ->label('Priorität')
                    ->options(Priority::options())
                    // Empty = use the value computed from the risk assessment.
                    ->placeholder(fn (Vorgang $record) => $record->priority)
                    ->selectablePlaceholder()
                    ->disabled(fn () => ! (auth()->user()?->can(Permissions::VORGANG_UPDATE) ?? false))
                    ->sortable(),

                SelectColumn::make('status_id')
                    ->label('Status')
                    ->options(fn (Vorgang $record) => self::statusOptions($record))
                    ->selectablePlaceholder(false)
                    ->disabled(fn (Vorgang $record) => self::availableTransitions($record)->isEmpty())
                    ->updateStateUsing(fn (Vorgang $record, $state) => self::applyStatus($record, (int) $state))
                    ->sortable(),

                TextInputColumn::make('editor_note')
                    ->label('Notiz (intern)')
                    ->placeholder('Notiz')
                    ->disabled(fn () => ! (auth()->user()?->can(Permissions::VORGANG_UPDATE) ?? false))
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status_id')
                    ->label('Status')
                    ->options(fn () => WorkflowStatus::ordered()->pluck('name', 'id'))
                    ->multiple(),

                SelectFilter::make('priority')
                    ->label('Priorität')
                    ->options(Priority::options())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->withEffectivePriority($data['value'])
                        : $query),

                SelectFilter::make('species_id')
                    ->label('Art')
                    ->relationship('species', 'name_de')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('geo_kreis')
                    ->label('Kreis')
                    ->options(fn () => Vorgang::query()
                        ->whereNotNull('geo_kreis')
                        ->distinct()
                        ->orderBy('geo_kreis')
                        ->pluck('geo_kreis', 'geo_kreis')),

                SelectFilter::make('geometry_type')
                    ->label('Typ')
                    ->options(['point' => 'Punkt', 'polygon' => 'Fläche']),

                SelectFilter::make('assigned_to_id')
                    ->label('Bearbeitung')
                    ->relationship('assignedTo', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('unassigned')
                    ->label('Zuweisung')
                    ->placeholder('Alle')
                    ->trueLabel('Ohne Zuweisung')
                    ->falseLabel('Mit Zuweisung')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('assigned_to_id'),
                        false: fn (Builder $q) => $q->whereNotNull('assigned_to_id'),
                    ),

                // "Erledigte ausblenden" from the reference monitor.
                Filter::make('hide_done')
                    ->label('Abgeschlossene ausblenden')
                    ->query(fn (Builder $query) => $query->open())
                    ->default(),
            ])
            ->recordActions([
                self::detailsAction(),
                self::mapAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::assignBulkAction(),
                    DeleteBulkAction::make()
                        ->label('Löschen')
                        ->visible(fn () => auth()->user()?->can(Permissions::VORGANG_DELETE) ?? false),
                ]),
            ])
            ->emptyStateHeading('Keine Vorgänge')
            ->emptyStateDescription('Neue Meldungen erscheinen hier automatisch, sobald KoboToolbox sie sendet.');
    }

    // -----------------------------------------------------------------
    // Status
    // -----------------------------------------------------------------

    private static function availableTransitions(Vorgang $record)
    {
        return app(WorkflowService::class)->availableTransitions($record, auth()->user());
    }

    /**
     * The current status plus every status the user may move to - so the
     * dropdown shows where the case stands without offering a forbidden move.
     *
     * @return array<int, string>
     */
    private static function statusOptions(Vorgang $record): array
    {
        $options = $record->status_id !== null
            ? [$record->status_id => $record->status?->name ?? '—']
            : [];

        foreach (self::availableTransitions($record) as $transition) {
            $options[$transition->to_status_id] = $transition->toStatus?->name ?? $transition->label;
        }

        return $options;
    }

    private static function applyStatus(Vorgang $record, int $statusId): void
    {
        if ($statusId === $record->status_id) {
            return;
        }

        $target = WorkflowStatus::find($statusId);

        if ($target === null) {
            return;
        }

        try {
            app(WorkflowService::class)->transition($record, $target, auth()->user());
        } catch (Throwable $e) {
            // A transition that needs a note cannot be done from the row -
            // point the user at the action that can collect one.
            Notification::make()
                ->warning()
                ->title('Statusänderung nicht möglich')
                ->body($e->getMessage().' Nutze „Details“, um eine Begründung anzugeben.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Status geändert')
            ->body("Vorgang {$record->reference()} steht jetzt auf „{$target->name}“.")
            ->send();
    }

    // -----------------------------------------------------------------
    // Aktionen
    // -----------------------------------------------------------------

    /** The expandable detail row of the prototype, as a modal. */
    private static function detailsAction(): Action
    {
        return Action::make('details')
            ->label('Details')
            ->icon('heroicon-o-chevron-down')
            ->color('gray')
            ->slideOver()
            ->modalHeading(fn (Vorgang $record) => 'Vorgang '.$record->reference())
            ->modalWidth('3xl')
            ->fillForm(fn (Vorgang $record) => $record->only([
                'editor_risk', 'priority_override', 'confirmed', 'visited',
                'visit_date', 'action_needed', 'measure', 'editor_note',
                'assigned_to_id', 'species_id', 'due_date',
            ]))
            ->schema(fn () => \App\Filament\Admin\Resources\Vorgaenge\Schemas\VorgangForm::components())
            ->action(function (Vorgang $record, array $data): void {
                $record->fill($data)->save();

                Notification::make()->success()->title('Gespeichert')->send();
            })
            ->modalSubmitActionLabel('Speichern')
            ->visible(fn (Vorgang $record) => auth()->user()?->can('update', $record) ?? false);
    }

    /** The prototype's "auf Karte" button. */
    private static function mapAction(): Action
    {
        return Action::make('map')
            ->label('auf Karte')
            ->icon('heroicon-o-map-pin')
            ->color('gray')
            ->modalHeading(fn (Vorgang $record) => 'Fundort '.$record->reference())
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen')
            ->schema([
                \Filament\Schemas\Components\View::make('filament.entries.vorgang-map')
                    ->columnSpanFull(),
            ])
            ->visible(fn (Vorgang $record) => $record->latitude !== null || $record->geojson !== null);
    }

    private static function assignBulkAction(): BulkAction
    {
        return BulkAction::make('assign')
            ->label('Außendienst zuweisen')
            ->icon('heroicon-o-user-plus')
            ->visible(fn () => auth()->user()?->can(Permissions::VORGANG_ASSIGN) ?? false)
            ->schema([
                Select::make('user_id')
                    ->label('Bearbeiter:in')
                    ->required()
                    ->options(fn () => User::active()
                        ->withRole(Roles::AUSSENDIENST)
                        ->orderBy('name')
                        ->pluck('name', 'id')),

                Textarea::make('note')->label('Hinweis (optional)')->rows(2),
            ])
            ->action(function (Collection $records, array $data): void {
                $assignee = User::find($data['user_id']);

                if ($assignee === null) {
                    return;
                }

                $workflow = app(WorkflowService::class);

                foreach ($records as $record) {
                    $workflow->assign($record, $assignee, auth()->user());
                }

                Notification::make()
                    ->success()
                    ->title('Zugewiesen')
                    ->body("{$records->count()} Vorgang/Vorgänge an {$assignee->name} übergeben.")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
