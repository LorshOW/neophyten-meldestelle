<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Pages;

use App\Filament\Admin\Resources\Vorgaenge\VorgangResource;
use App\Models\WorkflowStatus;
use App\Filament\Admin\Actions\KoboSyncAction;
use App\Services\Export\VorgangCsvExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVorgaenge extends ListRecords
{
    protected static string $resource = VorgangResource::class;

    protected static ?string $title = 'Vorgänge';

    protected function getHeaderActions(): array
    {
        return [
            KoboSyncAction::make(),

            Action::make('export')
                ->label('CSV-Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                // Exports exactly what the current filters and tab show, in the
                // column order the previous monitor application produced.
                ->action(fn () => app(VorgangCsvExporter::class)->stream($this->getFilteredTableQuery())),
        ];
    }

    /** One tab per workflow status, mirroring the monitor's status filter. */
    public function getTabs(): array
    {
        $tabs = [
            'alle' => Tab::make('Alle'),
        ];

        foreach (WorkflowStatus::active()->ordered()->get() as $status) {
            $tabs[$status->key] = Tab::make($status->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status_id', $status->getKey()))
                ->badge(fn () => $status->vorgaenge()->count() ?: null)
                ->badgeColor($status->color);
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string
    {
        return 'alle';
    }
}
