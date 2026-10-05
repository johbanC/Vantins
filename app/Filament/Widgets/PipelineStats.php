<?php

namespace App\Filament\Widgets;

use App\Models\Quote;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Current quotes by pipeline stage. DEMO records never count here: they are not real business. */
class PipelineStats extends BaseWidget
{
    protected function getHeading(): ?string
    {
        return __('panel.quote.pipeline_title');
    }

    protected function getDescription(): ?string
    {
        return __('panel.quote.pipeline_hint');
    }

    protected function getStats(): array
    {
        $counts = Quote::query()
            ->visibleTo(auth()->user())
            ->current()
            ->where('is_demo', false)
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $stat = fn (string $label, int $total, string $color) => Stat::make($label, $total)->color($color);

        return [
            ...collect(Quote::STAGES)->map(fn (string $stage) => $stat(
                __('panel.quote.stages.'.$stage),
                (int) ($counts[$stage] ?? 0),
                $stage === 'sold' ? 'success' : 'primary',
            ))->all(),
            $stat(__('panel.quote.stages.lost'), (int) collect(Quote::LOSS_STAGES)->sum(fn ($s) => $counts[$s] ?? 0), 'danger'),
        ];
    }
}
