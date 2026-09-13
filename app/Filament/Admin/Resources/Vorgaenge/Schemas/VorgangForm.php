<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Schemas;

use App\Models\User;
use App\Models\Vorgang;
use App\Support\ActionNeeded;
use App\Support\Priority;
use App\Support\RiskAssessment;
use App\Support\Roles;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;

/**
 * What the office may change.
 *
 * Everything the citizen submitted stays read-only: this is a record of a
 * report, not a form to rewrite it. The status is deliberately absent - it
 * moves only through WorkflowService via the "Status ändern" action.
 */
class VorgangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /**
     * Shared by the edit page and the "Details" slide-over in the table, so
     * both always offer exactly the same fields.
     *
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [

            // Shown first, because the photos are what the assessment below is
            // based on. Only rendered for an existing record - on "create"
            // there is nothing to show yet.
            Section::make('Fotos')
                ->icon('heroicon-o-photo')
                ->schema([
                    View::make('filament.entries.vorgang-fotos')->columnSpanFull(),
                ])
                ->visible(fn (?Vorgang $record) => $record !== null),

            // The location the citizen reported, so the assessor does not have
            // to switch to the "Ansehen" view to see where the find is.
            Section::make('Standort')
                ->icon('heroicon-o-map-pin')
                ->collapsible()
                ->schema([
                    View::make('filament.entries.vorgang-map')->columnSpanFull(),
                ])
                ->visible(fn (?Vorgang $record) => $record !== null
                    && ($record->latitude !== null || $record->geojson !== null)),

            Section::make('Bewertung durch die Verwaltung')
                ->description('Die Priorität ergibt sich automatisch aus dieser Bewertung.')
                ->columns(2)
                ->schema([
                    CheckboxList::make('editor_risk')
                        ->label('Gefährdung / Schutz')
                        ->options(RiskAssessment::selectOptions())
                        ->live()
                        ->columnSpan(1),

                    Placeholder::make('computed_priority')
                        ->label('Automatische Einstufung')
                        ->content(fn (Get $get) => RiskAssessment::computePriority($get('editor_risk') ?? [])),

                    Select::make('priority_override')
                        ->label('Priorität überschreiben')
                        ->options(Priority::options())
                        ->placeholder('Automatische Einstufung verwenden')
                        ->helperText('Nur setzen, wenn die automatische Einstufung im Einzelfall nicht passt.'),
                ]),

            Section::make('Bearbeitung')
                ->columns(2)
                ->schema([
                    Select::make('assigned_to_id')
                        ->label('Bearbeiter:in (Außendienst)')
                        ->options(fn () => User::active()
                            ->withRole(Roles::AUSSENDIENST)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('nicht zugewiesen'),

                    DatePicker::make('due_date')
                        ->label('Frist')
                        ->displayFormat('d.m.Y')
                        ->native(false),

                    Checkbox::make('confirmed')->label('Vorkommen bestätigt'),
                    Checkbox::make('visited')->label('Vor Ort besucht'),

                    DatePicker::make('visit_date')
                        ->label('Besuchsdatum')
                        ->displayFormat('d.m.Y')
                        ->native(false),

                    Select::make('action_needed')
                        ->label('Handlungsbedarf')
                        ->options(ActionNeeded::options())
                        ->placeholder('noch offen'),

                    Select::make('species_id')
                        ->label('Bestätigte Art')
                        ->relationship('species', 'name_de')
                        ->searchable()
                        ->preload()
                        ->placeholder('wie gemeldet'),
                ]),

            Section::make('Notizen')
                ->schema([
                    Textarea::make('measure')
                        ->label('Maßnahme')
                        ->placeholder('z. B. Bekämpfung geplant für ...')
                        ->rows(3),

                    Textarea::make('editor_note')
                        ->label('Interne Notiz')
                        ->rows(3),
                ]),

            Section::make('Meldung (unveränderlich)')
                ->description('Angaben der meldenden Person - nur zur Ansicht.')
                ->collapsed()
                ->columns(2)
                ->schema([
                    Placeholder::make('reported_species_raw')
                        ->label('Gemeldete Art')
                        ->content(fn (?Vorgang $record) => $record?->reported_species_raw ?: 'Keine Angabe'),
                    Placeholder::make('size_category')
                        ->label('Größe')
                        ->content(fn (?Vorgang $record) => $record?->size_category ?: '–'),
                    Placeholder::make('location_summary')
                        ->label('Standorttyp')
                        ->content(fn (?Vorgang $record) => $record?->location_summary ?: '–'),
                    Placeholder::make('citizen_note')
                        ->label('Anmerkung')
                        ->content(fn (?Vorgang $record) => $record?->citizen_note ?: 'keine')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
