<?php

namespace App\Filament\Admin\Widgets;

use App\Models\WorkflowStatus;
use Filament\Widgets\ChartWidget;

class StatusVerteilung extends ChartWidget
{
    protected ?string $heading = 'Vorgänge nach Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $statuses = WorkflowStatus::active()->ordered()->withCount('vorgaenge')->get();

        return [
            'datasets' => [[
                'label' => 'Vorgänge',
                'data' => $statuses->pluck('vorgaenge_count')->all(),
                'backgroundColor' => $statuses->map(fn (WorkflowStatus $s) => match ($s->color) {
                    'success' => '#3E7D4F',
                    'warning' => '#B8842E',
                    'danger' => '#C4413C',
                    'info' => '#2F7A93',
                    'primary' => '#2f6d3e',
                    default => '#5B6B84',
                })->all(),
            ]],
            'labels' => $statuses->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
