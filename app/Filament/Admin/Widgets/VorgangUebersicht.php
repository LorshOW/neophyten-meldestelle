<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Support\Priority;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers the reference monitor showed above its table.
 */
class VorgangUebersicht extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Vorgang::count();
        $new = Vorgang::whereHas('status', fn ($q) => $q->where('is_initial', true))->count();
        $areas = Vorgang::where('geometry_type', 'polygon')->count();
        $high = Vorgang::withEffectivePriority(Priority::HIGH)->count();
        $unassigned = Vorgang::open()->unassigned()->count();

        return [
            Stat::make('Meldungen gesamt', $total)
                ->description($unassigned.' offen und nicht zugewiesen')
                ->color('gray'),

            Stat::make('Noch neu', $new)
                ->description('warten auf die erste Prüfung')
                ->color($new > 0 ? 'warning' : 'success'),

            Stat::make('Als Fläche erfasst', $areas)
                ->description('mit eingezeichnetem Polygon')
                ->color('info'),

            Stat::make('Hohe Priorität', $high)
                ->description('Gefährdung von Menschen oder Tieren')
                ->color($high > 0 ? 'danger' : 'gray'),
        ];
    }
}
