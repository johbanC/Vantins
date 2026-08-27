<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApplicationStats extends BaseWidget
{
    protected function getStats(): array
    {
        $counts = Application::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $label = fn (string $s) => __('panel.status.'.$s);

        return [
            Stat::make(__('panel.stats.total'), Application::count())
                ->color('primary'),
            Stat::make($label('created'), $counts['created'] ?? 0)
                ->color('gray'),
            Stat::make($label('signed'), $counts['signed'] ?? 0)
                ->color('info'),
            Stat::make($label('in_review'), $counts['in_review'] ?? 0)
                ->color('warning'),
            Stat::make($label('quoted'), $counts['quoted'] ?? 0)
                ->color('primary'),
            Stat::make($label('issued'), $counts['issued'] ?? 0)
                ->color('success'),
        ];
    }
}
