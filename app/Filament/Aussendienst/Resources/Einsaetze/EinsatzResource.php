<?php

namespace App\Filament\Aussendienst\Resources\Einsaetze;

use App\Filament\Aussendienst\Resources\Einsaetze\Pages;
use App\Filament\Aussendienst\Resources\Einsaetze\Schemas\EinsatzInfolist;
use App\Filament\Aussendienst\Resources\Einsaetze\Tables\EinsatzTable;
use App\Models\Vorgang;
use App\Support\Permissions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Außendienst view of a Vorgang.
 *
 * Scoped to the signed-in user's own cases. The scope is a convenience for the
 * list; VorgangPolicy is what actually stops someone opening a case that is not
 * theirs by typing its id into the address bar.
 */
class EinsatzResource extends Resource
{
    protected static ?string $model = Vorgang::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $slug = 'einsaetze';

    protected static ?string $modelLabel = 'Einsatz';

    protected static ?string $pluralModelLabel = 'Meine Einsätze';

    protected static ?string $navigationLabel = 'Meine Einsätze';

    protected static ?string $recordTitleAttribute = 'local_nr';

    public static function infolist(Schema $schema): Schema
    {
        return EinsatzInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EinsatzTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['status', 'species']);

        // Admins keep full visibility so they can check the field view;
        // everyone else sees only their own assignments.
        if (auth()->user()?->can(Permissions::VORGANG_VIEW_ANY)) {
            return $query;
        }

        return $query->where('assigned_to_id', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEinsaetze::route('/'),
            'view' => Pages\ViewEinsatz::route('/{record}'),
        ];
    }
}
