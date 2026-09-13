<?php

namespace App\Filament\Admin\Resources\Species;

use App\Filament\Admin\Resources\Species\Pages;
use App\Models\Species;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Neophyten master data, seeded from the species list of the public reporting
 * app so both sides stay in step.
 */
class SpeciesResource extends Resource
{
    protected static ?string $model = Species::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?string $slug = 'pflanzenarten';

    protected static ?string $modelLabel = 'Pflanzenart';

    protected static ?string $pluralModelLabel = 'Pflanzenarten';

    protected static ?string $recordTitleAttribute = 'name_de';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::SPECIES_MANAGE) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name_de')->label('Deutscher Name')->required(),
                    TextInput::make('name_latin')->label('Wissenschaftlicher Name'),
                    TextInput::make('wiki_title')
                        ->label('Wikipedia-Artikel')
                        ->helperText('Exakter Titel auf de.wikipedia.org.'),
                    TextInput::make('habitat')->label('Typischer Standort'),
                    Textarea::make('warning_text')
                        ->label('Warnhinweis')
                        ->rows(2)
                        ->helperText('z. B. Verbrennungsgefahr bei Hautkontakt.')
                        ->columnSpanFull(),
                    TextInput::make('image_url')->label('Bild-URL')->url()->columnSpanFull(),
                    TextInput::make('kobo_value')
                        ->label('Wert im Kobo-Formular')
                        ->helperText('Antwortwert, unter dem diese Art im Meldeformular ankommt.'),
                    TextInput::make('sort_order')->label('Reihenfolge')->numeric()->default(0)->required(),
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
                ImageColumn::make('image_url')->label('')->circular(),
                TextColumn::make('name_de')->label('Name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('name_latin')->label('Wissenschaftlich')->searchable()->color('gray'),
                TextColumn::make('habitat')->label('Standort')->limit(40)->toggleable(),
                TextColumn::make('vorgaenge_count')
                    ->label('Meldungen')
                    ->counts('vorgaenge')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('warning_text')
                    ->label('Warnung')
                    ->boolean()
                    ->getStateUsing(fn (Species $record) => filled($record->warning_text))
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-minus')
                    ->color(fn (bool $state) => $state ? 'warning' : 'gray'),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()
                    ->label('Löschen')
                    ->visible(fn (Species $record) => $record->vorgaenge()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpecies::route('/'),
            'create' => Pages\CreateSpecies::route('/neu'),
            'edit' => Pages\EditSpecies::route('/{record}/bearbeiten'),
        ];
    }
}
