<?php

namespace App\Filament\Pages;

use App\Modules\Reporting\Services\ReportingService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Company-wide product performance: units, revenue and margin.
 *
 * The only product endpoint that existed required an agent_id, returned an
 * empty result without one, and did not aggregate - so "what are our top
 * products?" had no answer anywhere in the product.
 */
class ProductPerformance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Product performance';

    protected string $view = 'filament.pages.product-performance';

    public ?string $from = null;

    public ?string $to = null;

    /** @var array<string, mixed> */
    public array $report = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && ($user->isRole('Admin') || $user->isRole('System Admin') || $user->isRole('Manager'));
    }

    public function mount(): void
    {
        $this->from ??= now()->startOfYear()->toDateString();
        $this->to ??= now()->toDateString();

        $this->loadReport();
    }

    public function updated(): void
    {
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->report = app(ReportingService::class)->getProductPerformance([
            'from' => $this->from,
            'to' => $this->to,
            'limit' => 50,
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $unattributed = $this->report['unattributed_invoice_lines'] ?? 0;

        if ($unattributed > 0) {
            // Say so rather than let the totals imply full coverage.
            return "{$unattributed} invoice line(s) are not linked to a catalogue product and are excluded.";
        }

        return 'Units, revenue and margin across won deals and invoices, de-duplicated.';
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action('loadReport'),
        ];
    }
}
