<?php

namespace App\Filament\Aussendienst\Resources\Einsaetze\Schemas;

use App\Models\Vorgang;
use App\Support\Priority;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Everything needed on site, nothing else.
 *
 * Contact details of the reporting person are deliberately not shown here -
 * the field team does not need them, and the fewer places personal data is
 * displayed the better.
 */
class EinsatzInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make()
                ->columns(2)
                ->schema([
                    TextEntry::make('local_nr')->label('Nummer')->weight('bold'),
                    TextEntry::make('status.name')
                        ->label('Status')
                        ->badge()
                        ->color(fn (Vorgang $record) => $record->status?->color ?? 'gray'),
                    TextEntry::make('priority')
                        ->label('Priorität')
                        ->badge()
                        ->state(fn (Vorgang $record) => $record->effectivePriority())
                        ->color(fn (string $state) => Priority::color($state)),
                    TextEntry::make('due_date')
                        ->label('Frist')
                        ->date('d.m.Y')
                        ->placeholder('keine'),
                ]),

            Section::make('Was gemeldet wurde')
                ->columns(2)
                ->schema([
                    TextEntry::make('reported_species_raw')->label('Art')->placeholder('unbekannt'),
                    TextEntry::make('size_category')->label('Größe')->placeholder('–'),
                    TextEntry::make('location_summary')->label('Standorttyp')->placeholder('–'),
                    TextEntry::make('submitted_at')->label('Gemeldet am')->dateTime('d.m.Y'),
                    TextEntry::make('citizen_note')
                        ->label('Hinweis der meldenden Person')
                        ->placeholder('keiner')
                        ->columnSpanFull(),
                    TextEntry::make('editor_risk')
                        ->label('Gefährdungshinweise')
                        ->badge()
                        ->color('warning')
                        ->placeholder('keine')
                        ->columnSpanFull(),
                ]),

            Section::make('Wo')
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('geo_label')->label('Ort')->placeholder('nicht ermittelt'),
                        TextEntry::make('latitude')
                            ->label('Koordinaten')
                            ->state(fn (Vorgang $r) => $r->latitude && $r->longitude
                                ? number_format($r->latitude, 6).', '.number_format($r->longitude, 6)
                                : null)
                            ->placeholder('keine')
                            ->copyable()
                            ->helperText('Antippen zum Kopieren – lässt sich in die Navigations-App einfügen.'),
                    ]),
                    View::make('filament.entries.vorgang-map')->columnSpanFull(),
                ]),

            Section::make('Fotos')
                ->collapsible()
                ->schema([
                    View::make('filament.entries.vorgang-fotos')->columnSpanFull(),
                ]),

            Section::make('Ergebnis der Kontrolle')
                ->schema([
                    Grid::make(2)->schema([
                        IconEntry::make('visited')->label('Vor Ort gewesen')->boolean(),
                        IconEntry::make('confirmed')->label('Vorkommen bestätigt')->boolean(),
                        TextEntry::make('visit_date')->label('Besuchsdatum')->date('d.m.Y')->placeholder('–'),
                        TextEntry::make('action_needed')->label('Handlungsbedarf')->badge()->placeholder('offen'),
                    ]),
                    TextEntry::make('measure')->label('Maßnahme')->placeholder('noch keine')->columnSpanFull(),
                ]),
        ]);
    }
}
