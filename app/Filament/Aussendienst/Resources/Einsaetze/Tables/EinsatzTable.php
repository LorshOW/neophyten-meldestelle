<?php

namespace App\Filament\Aussendienst\Resources\Einsaetze\Tables;

use App\Models\Vorgang;
use App\Support\Priority;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read on a phone, outdoors, often one-handed - so this is a stack of cards
 * rather than a wide table.
 */
class EinsatzTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('local_nr')
                    ->label('Nummer')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('reported_species_raw')
                    ->label('Art')
                    ->placeholder('Art unbekannt')
                    ->wrap(),

                TextColumn::make('geo_label')
                    ->label('Ort')
                    ->placeholder('Ort nicht ermittelt')
                    ->wrap(),

                TextColumn::make('priority')
                    ->label('Priorität')
                    ->badge()
                    ->state(fn (Vorgang $record) => $record->effectivePriority())
                    ->color(fn (string $state) => Priority::color($state)),

                TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Vorgang $record) => $record->status?->color ?? 'gray'),

                TextColumn::make('size_category')
                    ->label('Größe')
                    ->placeholder('–')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('open')
                    ->label('Nur offene Einsätze')
                    ->query(fn (Builder $query) => $query->open())
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make()->label('Öffnen'),
            ])
            ->emptyStateHeading('Keine Einsätze')
            ->emptyStateDescription('Sobald dir ein Vorgang zugewiesen wird, erscheint er hier.')
            ->paginated([10, 25]);
    }
}
