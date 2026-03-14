<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: #aaeef3;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .summary-wrapper {
            width: 760px;
            margin: 0 auto;
            background-color: #aaeef3;
        }

        /* ── Header ── */
        .header-card {
            background-color: #26c6da;
            border-radius: 10px;
            padding: 18px 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .header-icon {
            background-color: #00bcd4;
            border-radius: 8px;
            width: 16px;
            height: 40px;
            margin-right: 18px;
            flex-shrink: 0;
        }
        .header-info h2 { font-weight: bold; font-size: 24px; color: #000; margin-bottom: 4px; }
        .header-info p  { color: #333; font-size: 15px; line-height: 1.5; }

        /* ── Cards ── */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .stat-title { color: #000; font-size: 20px; font-weight: bold; margin-bottom: 14px; }
        .stat-value  { font-size: 34px; color: #333; font-family: 'Times New Roman', serif; margin-bottom: 10px; }
        .stat-remaining { color: #f44336; font-size: 14px; font-weight: bold; }

        /* ── Top row: 2 columns side-by-side ── */
        .top-row { display: flex; gap: 16px; margin-bottom: 0; }
        .col-left  { width: 42%; display: flex; flex-direction: column; gap: 16px; }
        .col-right { width: 58%; }
        .col-right .stat-card { height: 100%; margin-bottom: 0; }
        .col-left  .stat-card { margin-bottom: 0; }

        /* ── Today Vs Yesterday ── */
        .target-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }
        .change-box {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        /* ── Pure CSS Pie Chart ── */
        .pie-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 0 6px;
        }
        .pie-label-top {
            font-size: 13px;
            color: #4caf50;
            font-weight: bold;
            align-self: flex-start;
            margin-bottom: 10px;
        }
        .pie-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .pie-label-bottom {
            font-size: 13px;
            color: #f44336;
            font-weight: bold;
            align-self: flex-end;
            margin-top: 10px;
        }
        .pie-legend {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #555;
            margin-top: 14px;
        }
        .legend-box {
            display: inline-block;
            width: 14px; height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }
        .bg-green { background-color: #4caf50; }
        .bg-red   { background-color: #f44336; }
        .text-green { color: #4caf50; }
        .text-red   { color: #f44336; }

        /* ── Full-width table section ── */
        .section-table { margin-top: 16px; }

        .custom-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .custom-table th {
            background-color: #c8e6c9; color: #2e7d32;
            padding: 12px 8px; font-size: 14px;
            text-align: center; border: 1px solid #eee;
            white-space: nowrap;
        }
        .custom-table td {
            padding: 12px 8px; text-align: center;
            border: 1px solid #eee; font-size: 14px;
            font-weight: bold; color: #333;
        }
        .custom-table tfoot td { background-color: #fff9c4; font-weight: bold; }
        hr { border: 0; border-top: 1px solid #ddd; margin: 12px 0; }
    </style>
</head>
<body>
<div class="summary-wrapper">

    {{-- ── Header ── --}}
    <div class="header-card">
        <div class="header-icon"></div>
        <div class="header-info">
            <h2>Daily Sale Visit Summary</h2>
            <p>Date: {{ \Carbon\Carbon::parse($today)->format('n/j/Y') }}<br>Business : {{ $business_name }}</p>
        </div>
    </div>

    {{-- ── Top 2-column row ── --}}
    <div class="top-row">

        {{-- Left column: Total Sale Visit + Today Vs Yesterday --}}
        <div class="col-left">
            <div class="stat-card">
                <div class="stat-title">Total Sale Visit</div>
                <div class="stat-value">
                    {{ $todays_visits_count }} / {{ $total_target }}
                    <span style="font-size:26px; color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">({{ $overall_variance }}%)</span>
                </div>
                <div class="stat-remaining" style="color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">
                    {{ abs($overall_remaining) }} {{ $overall_remaining <= 0 ? 'remaining' : 'over target' }}
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-title">Today Vs Yesterday</div>
                <div class="target-row">
                    <span>Today's Visit</span>
                    <span>
                        <span style="color:#333;">({{ $todays_visits_count }} / {{ $total_target }})</span>
                        <span class="text-red"> {{ $overall_variance }}%</span>
                    </span>
                </div>
                <div class="target-row">
                    <span>Yesterday's Visit</span>
                    <span>
                        <span style="color:#333;">({{ $yesterdays_visits_count }} / {{ $yesterday_target }})</span>
                        <span class="text-red"> {{ $yesterday_variance }}%</span>
                    </span>
                </div>
                <hr>
                <div class="target-row">
                    <span>Days-Over-Days</span>
                    <span class="change-box" style="
                        color: {{ $dod_change >= 0 ? '#4caf50' : '#e53935' }};
                        background: {{ $dod_change >= 0 ? '#e8f5e9' : '#ffebee' }};">
                        {{ $dod_change >= 0 ? '▲' : '▼' }}
                        {{ $dod_change > 0 ? '+' : '' }}{{ $dod_change }}%
                    </span>
                </div>
            </div>
        </div>

        {{-- Right column: Own vs Other Product (CSS pie) --}}
        <div class="col-right">
            <div class="stat-card" style="display:flex;flex-direction:column;align-items:center;">
                <div class="stat-title" style="text-align:center;width:100%;">Own vs other Product</div>

                <div class="pie-wrap" style="width:100%;">
                    <div class="pie-label-top">Own product {{ $overall_own_pct }}%</div>

                    @php
                        $ownDeg = round($overall_own_pct * 3.6);  // % -> degrees
                    @endphp
                    <div class="pie-circle" style="background: conic-gradient(
                        #4caf50 0deg {{ $ownDeg }}deg,
                        #f44336 {{ $ownDeg }}deg 360deg
                    );"></div>

                    <div class="pie-label-bottom">Other Product {{ $overall_other_pct }}%</div>
                </div>

                <div class="pie-legend">
                    <span class="legend-box bg-green"></span><span class="text-green">Own product</span>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="legend-box bg-red"></span><span class="text-red">Other Product</span>
                </div>
            </div>
        </div>

    </div>{{-- end top-row --}}

    {{-- ── Sale Visit Report Table (full width) ── --}}
    <div class="section-table">
        <div class="stat-card" style="margin-bottom:0;">
            <div class="stat-title">Sale Visit Report</div>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th style="text-align:left;">SE's Name</th>
                        <th>Actual/Target Visit</th>
                        <th>Remain Target</th>
                        <th>Own vs Other Product</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales_report as $rep_id => $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td style="text-align:left;"><em>{{ $row['name'] }}</em></td>
                        <td>
                            {{ $row['qty_visit'] }} / {{ $row['target'] }}
                            (<span class="{{ $row['variance'] >= 50 ? 'text-green' : 'text-red' }}">{{ $row['variance'] }}%</span>)
                        </td>
                        <td><span class="{{ $row['remaining'] >= 0 ? 'text-green' : 'text-red' }}">{{ $row['remaining'] }}</span></td>
                        <td>
                            <span class="text-green">{{ $row['own_pct'] }}%</span>
                            <span style="color:#aaa;"> / </span>
                            <span class="text-red">{{ $row['other_pct'] }}%</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5">No visits recorded for today.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:center;">Total</td>
                        <td>
                            {{ $todays_visits_count }} / {{ $total_target }}
                            (<span class="text-red">{{ $overall_variance }}%</span>)
                        </td>
                        <td><span class="text-red">{{ $overall_remaining }}</span></td>
                        <td>
                            <span class="text-green">{{ $overall_own_pct }}%</span>
                            <span style="color:#aaa;"> / </span>
                            <span class="text-red">{{ $overall_other_pct }}%</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>
</body>
</html>