<!DOCTYPE html>
<html lang="en">
@php
    $statuses = [
        'achieved' => ['Target hit', '#065f46', '#d1fae5'],
        'in_progress' => ['In progress', '#1e40af', '#dbeafe'],
        'not_started' => ['Not started', '#475569', '#f1f5f9'],
        'on_track' => ['On track', '#065f46', '#ecfdf5'],
        'behind' => ['Behind', '#92400e', '#fef3c7'],
        'at_risk' => ['At risk', '#991b1b', '#fee2e2'],
    ];
    $medals = [1 => '#f59e0b', 2 => '#94a3b8', 3 => '#b45309'];
    $summary = $board['summary'];
    $span = $board['period'];
    $isToday = $span === 'today';
    $tiles = array_values(array_filter([
        $summary['leads_today']['target'] > 0 ? ['Leads today', $summary['leads_today']] : null,
        ! $isToday && $summary['leads_period']['target'] > 0 ? ['Leads this '.$span, $summary['leads_period']] : null,
        $summary['appointments_month']['target'] > 0 ? ['Appointments this month', $summary['appointments_month']] : null,
        $summary['sales_month']['target'] > 0 ? ['Sales this month', $summary['sales_month']] : null,
    ]));
@endphp
<head>
    <meta charset="utf-8"/>
    <title>Sales Leaderboard — {{ $board['range']['label'] }}</title>
    <style>
        @page { size: A4; margin: 12mm 11mm 12mm 11mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.4; }

        .top-bar { height: 5px; background: #2563eb; margin-bottom: 12px; }
        .logo-section { text-align: center; margin-bottom: 12px; }
        .logo-img { max-height: 54px; max-width: 200px; }
        .logo-fallback { font-size: 20px; font-weight: bold; color: #1e3a8a; }
        .pdf-flow-reset { clear: both; height: 0; font-size: 0; line-height: 0; }

        .hero { background: #1d4ed8; border-radius: 10px; padding: 16px 18px; color: #ffffff; margin-bottom: 12px; }
        .hero-table { width: 100%; border-collapse: collapse; }
        .hero-title { font-size: 22px; font-weight: bold; letter-spacing: 0.3px; }
        .hero-sub { font-size: 10px; color: #dbeafe; margin-top: 2px; }
        .hero-right { text-align: right; vertical-align: middle; }
        .hero-count { font-size: 24px; font-weight: bold; }
        .hero-count-label { font-size: 8px; color: #dbeafe; text-transform: uppercase; letter-spacing: 0.6px; }

        .tiles { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 12px; }
        .tiles td { vertical-align: top; padding: 0 5px; }
        .tiles td.first { padding-left: 0; }
        .tiles td.last { padding-right: 0; }
        .tile { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; background: #f8fafc; }
        .tile-label { font-size: 8px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .tile-value { font-size: 18px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .tile-of { font-size: 10px; font-weight: normal; color: #64748b; }
        .tile-pct { font-size: 9px; color: #1d4ed8; font-weight: bold; margin-top: 3px; }

        .track { height: 6px; background: #e2e8f0; border-radius: 3px; margin-top: 4px; }
        .fill { height: 6px; background: #2563eb; border-radius: 3px; }
        .fill-alt { background: #60a5fa; }
        .fill-deep { background: #1e40af; }

        .board { width: 100%; border-collapse: collapse; }
        .board th { background: #1e3a8a; color: #ffffff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; padding: 7px 8px; }
        .board th.center, .board td.center { text-align: center; }
        .board td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 10px; }
        .board tr { page-break-inside: avoid; }
        .board tr.podium td { background: #eff6ff; }
        .rank { display: inline-block; width: 20px; padding: 3px 0 4px; line-height: 1; border-radius: 10px; text-align: center; font-weight: bold; font-size: 10px; background: #e2e8f0; color: #334155; }
        .name { font-weight: bold; font-size: 11px; }
        .role { font-size: 8px; color: #64748b; }
        .figure { font-weight: bold; }
        .of { color: #64748b; }
        .pct { font-size: 8px; color: #475569; float: right; }
        .none { color: #94a3b8; }
        .overall { font-size: 13px; font-weight: bold; color: #1d4ed8; }
        .status { display: inline-block; padding: 3px 8px; border-radius: 9px; font-size: 8px; font-weight: bold; }

        .empty { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 26px; text-align: center; color: #64748b; }
        .foot { margin-top: 12px; font-size: 8px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    @include('partials.pdf_branding_header')

    <div class="hero">
        <table class="hero-table">
            <tr>
                <td>
                    <div class="hero-title">Sales Leaderboard</div>
                    <div class="hero-sub">
                        @if($isToday)
                            {{ $board['today_label'] }} &nbsp;·&nbsp; as of {{ $generatedAt }}
                        @else
                            {{ ucfirst($span) }} of {{ $board['range']['label'] }} &nbsp;·&nbsp; as of {{ $board['today_label'] }}, {{ $generatedAt }}
                        @endif
                    </div>
                </td>
                <td class="hero-right">
                    <div class="hero-count">{{ $summary['members'] }}</div>
                    <div class="hero-count-label">on the board</div>
                </td>
            </tr>
        </table>
    </div>

    @if($tiles)
        <table class="tiles">
            <tr>
                @foreach($tiles as $i => [$label, $figure])
                    <td class="{{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}" style="width: {{ round(100 / count($tiles), 2) }}%;">
                        <div class="tile">
                            <div class="tile-label">{{ $label }}</div>
                            <div class="tile-value">{{ $figure['done'] }} <span class="tile-of">/ {{ $figure['target'] }}</span></div>
                            <div class="track"><div class="fill" style="width: {{ min(100, $figure['percent']) }}%;"></div></div>
                            <div class="tile-pct">{{ $figure['percent'] }}% of target</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @if($board['rows'])
        <table class="board">
            <thead>
            <tr>
                <th class="center" style="width: 6%;">#</th>
                <th style="width: 21%;">Name</th>
                <th style="width: 17%;">Leads · {{ $span }}</th>
                <th style="width: 18%;">Appointments · month</th>
                <th style="width: 17%;">Sales · month</th>
                <th class="center" style="width: 8%;">Overall</th>
                <th class="center" style="width: 13%;">Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($board['rows'] as $row)
                @php [$statusLabel, $statusText, $statusFill] = $statuses[$row['status']] ?? $statuses['in_progress']; @endphp
                <tr class="{{ $row['rank'] <= 3 ? 'podium' : '' }}">
                    <td class="center">
                        <span class="rank" @isset($medals[$row['rank']]) style="background: {{ $medals[$row['rank']] }}; color: #ffffff;" @endisset>{{ $row['rank'] }}</span>
                    </td>
                    <td>
                        <div class="name">{{ $row['name'] }}</div>
                        @if($row['role'])<div class="role">{{ $row['role'] }}</div>@endif
                    </td>
                    @foreach(['leads' => '', 'appointments' => 'fill-alt', 'sales' => 'fill-deep'] as $key => $fillClass)
                        <td>
                            @if($row[$key])
                                <span class="pct">{{ $row[$key]['percent'] }}%</span>
                                <span class="figure">{{ $row[$key]['done'] }}</span> <span class="of">/ {{ $row[$key]['target'] }}</span>
                                <div class="track"><div class="fill {{ $fillClass }}" style="width: {{ min(100, $row[$key]['percent']) }}%;"></div></div>
                            @else
                                <span class="none">No target</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="center"><span class="overall">{{ $row['progress'] }}%</span></td>
                    <td class="center">
                        <span class="status" style="color: {{ $statusText }}; background: {{ $statusFill }};">{{ $statusLabel }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">Nobody has a target set for {{ $board['month_label'] }} yet.</div>
    @endif

    <div class="foot">
        Only people with a target set for {{ $board['month_label'] }} are listed. Leads are counted Monday to Saturday against each person's daily target.
    </div>
</body>
</html>
