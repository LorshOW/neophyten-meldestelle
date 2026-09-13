<?php

namespace App\Filament\Admin\Resources\Vorgaenge;

use App\Filament\Admin\Resources\Vorgaenge\Pages;
use App\Filament\Admin\Resources\Vorgaenge\Schemas\VorgangForm;
use App\Filament\Admin\Resources\Vorgaenge\Schemas\VorgangInfolist;
use App\Filament\Admin\Resources\Vorgaenge\Tables\VorgangTable;
use App\Models\Vorgang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VorgangResource extends Resource
{
    protected static ?string $model = Vorgang::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Vorgänge';

    protected static ?string $modelLabel = 'Vorgang';

    protected static ?string $pluralModelLabel = 'Vorgänge';

    protected static ?string $recordTitleAttribute = 'local_nr';

    protected static ?string $slug = 'vorgaenge';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return VorgangForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VorgangInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VorgangTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['status', 'species', 'assignedTo']);
    }

    public static function getNavigationBadge(): ?string
    {
        // How many cases are still sitting untouched.
        $count = Vorgang::whereHas('status', fn (Builder $q) => $q->where('is_initial', true))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVorgaenge::route('/'),
            'view' => Pages\ViewVorgang::route('/{record}'),
            'edit' => Pages\EditVorgang::route('/{record}/bearbeiten'),
        ];
    }
}
