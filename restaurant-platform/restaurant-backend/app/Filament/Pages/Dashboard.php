<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\DashboardPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The one shared "اختيار فترة زمنية" (time period) selector every
 * period-aware widget reads via `Filament\Widgets\Concerns\
 * InteractsWithPageFilters` (`$this->pageFilters['period']`) — see
 * docs/ADMIN_DASHBOARD.md. Widgets that show "right now" operational state
 * (pending/preparing/late counts, the latest-orders table) deliberately
 * don't read this filter at all; only reporting/financial widgets do.
 *
 * `HasFiltersForm` persists the selection in the user's session between
 * page loads (Filament's own default) — a manager checking the dashboard
 * every morning doesn't need to re-pick "last 7 days" each time.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Period')
                    ->options(DashboardPeriod::class)
                    ->default(DashboardPeriod::Today)
                    ->selectablePlaceholder(false)
                    ->live(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportOrders')
                ->label('Export orders (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->url(fn (): string => route('admin.orders.export', [
                    'period' => $this->pageFilters['period'] ?? DashboardPeriod::Today->value,
                ]))
                ->openUrlInNewTab(),
        ];
    }
}
