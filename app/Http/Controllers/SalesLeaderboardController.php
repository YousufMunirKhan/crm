<?php

namespace App\Http\Controllers;

use App\Services\SalesLeaderboard;
use App\Support\PdfDocumentBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesLeaderboardController extends Controller
{
    public function __construct(private SalesLeaderboard $leaderboard) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->leaderboard->build($this->period($request), (int) $request->user()->id)
        );
    }

    /**
     * The same board as a page to send round - the figures on the screen and
     * the ones in the group chat come from one place.
     */
    public function pdf(Request $request)
    {
        $board = $this->leaderboard->build($this->period($request));
        $brand = PdfDocumentBranding::package();

        $pdf = Pdf::loadView('leaderboard.pdf', [
            'board' => $board,
            'generatedAt' => now(config('app.display_timezone'))->format('H:i'),
            'logoUrl' => $brand['logoUrl'],
            'settings' => $brand['settings'],
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download("sales-leaderboard-{$board['period']}-{$board['today']}.pdf");
    }

    private function period(Request $request): string
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(SalesLeaderboard::PERIODS)],
        ]);

        return $data['period'] ?? 'today';
    }
}
