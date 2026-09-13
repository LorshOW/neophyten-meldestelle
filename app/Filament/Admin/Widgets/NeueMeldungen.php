<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Vorgaenge\VorgangResource;
use App\Models\Vorgang;
use App\Support\Priority;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the office should look at next: open, unassigned, most urgent first.
 */
class NeueMeldungen extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Zu bearbeiten')
            ->description('Offene Vorgänge ohne Zuweisung, dringendste zuerst.')
            ->query(
                Vorgang::query()
                    ->open()
                    ->unassigned()
                    ->with(['status', 'species'])
                    // CASE rather than MySQL's FIELD(), so the same query also
                    // runs on SQLite in the test suite.
                    ->orderByRaw(
                        "CASE COALESCE(NULLIF(priority_override, ''), priority)"
                        ." WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END",
                        [Priority::HIGH, Priority::SOON]
                    )
                    ->orderByDesc('submitted_at')
            )
            ->columns([
                TextColumn::make('local_nr')->label('Nr.')->weight('bold'),
                TextColumn::make('submitted_at')->label('Datum')->date('d.m.Y'),
                TextColumn::make('reported_species_raw')->label('Art')->placeholder('unbekannt')->wrap(),
                TextColumn::make('geo_label')->label('Ort')->placeholder('–'),
                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge()
                    ->state(fn (Vorgang $record) => $record->effectivePriority())
                    ->color(fn (string $state) => Priority::color($state)),
                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Vorgang $record) => $record->status?->color ?? 'gray'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Öffnen')
                    ->url(fn (Vorgang $record) => VorgangResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nichts offen')
            ->emptyStateDescription('Alle Vorgänge sind zugewiesen oder abgeschlossen.');
    }
}
