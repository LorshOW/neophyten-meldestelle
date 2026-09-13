<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Schemas;

use App\Models\Vorgang;
use App\Support\Priority;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The case file: what the citizen reported, what the office made of it, and
 * everything that has happened since.
 */
class VorgangInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
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
                    TextEntry::make('assignedTo.name')->label('Bearbeitung')->placeholder('nicht zugewiesen'),
                ]),

            Tabs::make()->columnSpanFull()->tabs([

                Tabs\Tab::make('Meldung')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('reported_species_raw')->label('Gemeldete Art')->placeholder('Keine Angabe'),
                            TextEntry::make('knows_species')->label('Artkenntnis')->placeholder('–'),
                            TextEntry::make('size_category')->label('Größe des Bestandes')->placeholder('–'),
                            TextEntry::make('location_summary')->label('Standorttyp')->placeholder('–'),
                            TextEntry::make('submitted_at')->label('Gemeldet am')->dateTime('d.m.Y H:i'),
                            TextEntry::make('melde_id')->label('Melde-ID der Bürger:in')->placeholder('– (offline erfasst)'),
                        ]),
                        TextEntry::make('citizen_note')
                            ->label('Anmerkung der meldenden Person')
                            ->placeholder('keine Anmerkung')
                            ->columnSpanFull(),
                        TextEntry::make('risk_flags')
                            ->label('Gefährdungsangaben der meldenden Person')
                            ->state(fn (Vorgang $record) => self::activeRisks($record->risk_flags))
                            ->badge()
                            ->placeholder('keine Angabe')
                            ->columnSpanFull(),
                    ]),

                Tabs\Tab::make('Karte')
                    ->icon('heroicon-o-map')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('geometry_type')
                                ->label('Erfassungsart')
                                ->formatStateUsing(fn (?string $state) => match ($state) {
                                    'polygon' => 'Fläche',
                                    'point' => 'Punkt',
                                    default => 'Unbekannt',
                                }),
                            TextEntry::make('geo_label')->label('Ort')->placeholder('noch nicht ermittelt'),
                            TextEntry::make('latitude')
                                ->label('Koordinaten')
                                ->state(fn (Vorgang $r) => $r->latitude && $r->longitude
                                    ? number_format($r->latitude, 6).', '.number_format($r->longitude, 6)
                                    : null)
                                ->placeholder('keine Koordinaten')
                                ->copyable(),
                        ]),
                        View::make('filament.entries.vorgang-map')->columnSpanFull(),
                    ]),

                Tabs\Tab::make('Fotos')
                    ->icon('heroicon-o-photo')
                    ->badge(fn (Vorgang $record) => $record->attachments()->count() ?: null)
                    ->schema([
                        View::make('filament.entries.vorgang-fotos')->columnSpanFull(),
                    ]),

                Tabs\Tab::make('Bewertung')
                    ->icon('heroicon-o-shield-exclamation')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('editor_risk')
                                ->label('Bewertung der Verwaltung')
                                ->badge()
                                ->placeholder('noch nicht bewertet'),
                            TextEntry::make('priority')
                                ->label('Automatische Einstufung')
                                ->badge()
                                ->color(fn (?string $state) => Priority::color((string) $state))
                                ->helperText('Ergibt sich aus der Bewertung links.'),
                            IconEntry::make('confirmed')->label('Bestätigt')->boolean(),
                            IconEntry::make('visited')->label('Vor Ort besucht')->boolean(),
                            TextEntry::make('visit_date')->label('Besuchsdatum')->date('d.m.Y')->placeholder('–'),
                            TextEntry::make('action_needed')->label('Handlungsbedarf')->badge()->placeholder('offen'),
                        ]),
                        TextEntry::make('measure')->label('Maßnahme')->placeholder('keine')->columnSpanFull(),
                        TextEntry::make('editor_note')->label('Interne Notiz')->placeholder('keine')->columnSpanFull(),
                    ]),

                Tabs\Tab::make('Kontakt')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Grid::make(2)->schema([
                            IconEntry::make('reporter_contact_consent')
                                ->label('Kontaktaufnahme erlaubt')
                                ->boolean(),
                            TextEntry::make('reporter_name')->label('Ansprache')->placeholder('–'),
                            TextEntry::make('reporter_email')->label('E-Mail')->placeholder('–')->copyable(),
                            TextEntry::make('reporter_phone')->label('Telefon')->placeholder('–')->copyable(),
                        ]),
                        TextEntry::make('reporter_contact_raw')
                            ->label('Originalangabe aus dem Formular')
                            ->placeholder('keine Kontaktangabe')
                            ->columnSpanFull(),
                    ])
                    // Contact data is only shown where it may be used at all.
                    ->visible(fn (Vorgang $record) => $record->reporter_contact_consent
                        || filled($record->reporter_contact_raw)),
            ]),
        ]);
    }

    /**
     * @param  array<string, bool>|null  $flags
     * @return list<string>
     */
    private static function activeRisks(?array $flags): array
    {
        return array_values(array_keys(array_filter($flags ?? [])));
    }
}
