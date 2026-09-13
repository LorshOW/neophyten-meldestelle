<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Roles;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'benutzer';

    protected static ?string $modelLabel = 'Benutzer:in';

    protected static ?string $pluralModelLabel = 'Benutzer';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permissions::USER_MANAGE) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Konto')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(255),
                    TextInput::make('email')
                        ->label('E-Mail')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('phone')->label('Telefon')->tel()->maxLength(50),
                    Toggle::make('is_active')
                        ->label('Aktiv')
                        ->default(true)
                        ->helperText('Inaktive Konten können sich nicht anmelden.'),

                    TextInput::make('password')
                        ->label('Passwort')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                        // Leaving it empty on edit keeps the current password.
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->helperText('Beim Bearbeiten leer lassen, um das Passwort nicht zu ändern.')
                        ->columnSpanFull(),
                ]),

            Section::make('Rolle')
                ->schema([
                    Select::make('roles')
                        ->label('Rollen')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(fn (Role $record) => Roles::labels()[$record->name] ?? $record->name)
                        ->helperText('Innendienst arbeitet in der Verwaltung, Außendienst im mobilen Bereich.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->copyable(),
                TextColumn::make('roles.name')
                    ->label('Rollen')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Roles::labels()[$state] ?? $state),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
                TextColumn::make('assigned_vorgaenge_count')
                    ->label('Offene Einsätze')
                    ->counts(['assignedVorgaenge' => fn ($q) => $q->open()])
                    ->badge()
                    ->color('gray'),
                TextColumn::make('last_login_at')->label('Letzte Anmeldung')->dateTime('d.m.Y H:i')->placeholder('nie'),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rolle')
                    ->relationship('roles', 'name')
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make()->label('Bearbeiten'),
                DeleteAction::make()
                    ->label('Löschen')
                    // Deleting yourself locks you out mid-session.
                    ->visible(fn (User $record) => $record->getKey() !== auth()->id()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/neu'),
            'edit' => Pages\EditUser::route('/{record}/bearbeiten'),
        ];
    }
}
