<?php

namespace App\Filament\Widgets;

use App\Models\TicketHour;
use Filament\Widgets\BarChartWidget;

class UserTimeLogged extends BarChartWidget
{
    protected static ?string $heading = 'Chart';
    protected static ?int $sort = 5;
    protected static ?string $maxHeight = '300px';
    protected int|string|array $columnSpan = [
        'sm' => 1,
        'md' => 6,
        'lg' => 3
    ];

    public static function canView(): bool
    {
        return auth()->user()->can('List tickets');
    }

    protected function getHeading(): string
    {
        return __('Time logged by users');
    }

    protected function getData(): array
    {
        $hours = TicketHour::query()
            ->with('user')
            ->whereHas('ticket', fn($query) => $query->visibleTo(auth()->user()))
            ->selectRaw('user_id, SUM(value) as total_logged_hours')
            ->groupBy('user_id')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => __('Total time logged (hours)'),
                    'data' => $hours->pluck('total_logged_hours')->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, .6)'
                    ],
                    'borderColor' => [
                        'rgba(54, 162, 235, .8)'
                    ],
                ],
            ],
            'labels' => $hours->map(fn($item) => $item->user?->name)->toArray(),
        ];
    }
}
