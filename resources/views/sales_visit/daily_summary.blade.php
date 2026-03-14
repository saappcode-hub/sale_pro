@extends('layouts.app')

@section('title', __('Daily Sale Visit Summary'))

@section('content')
<style>
    .summary-wrapper { background-color: #aaeef3; padding: 20px; min-height: 100vh; font-family: 'Arial', sans-serif; }
    .header-card { background-color: #26c6da; color: white; border-radius: 10px; padding: 15px 25px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
    .header-icon { background-color: #00bcd4; border-radius: 8px; padding: 15px; font-size: 24px; margin-right: 20px; }
    .header-info h2 { margin: 0; font-weight: bold; font-size: 22px; color: #000; }
    .header-info p { margin: 5px 0 0 0; color: #333; font-size: 14px; }
    .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; height: calc(100% - 20px); }
    .stat-title { color: #333; font-size: 20px; font-weight: bold; margin-bottom: 15px; }
    .stat-value { font-size: 32px; color: #333; font-family: 'Times New Roman', serif; }
    .stat-remaining { color: #f44336; font-size: 14px; margin-top: 10px; font-weight: bold; }
    .target-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: bold; color: #333; }
    .target-row .red-text { color: #f44336; }
    .target-row .dark-text { color: #333; font-weight: bold;}
    .change-box { padding: 3px 8px; border-radius: 4px; font-size: 14px; font-weight: bold; }
    .chart-container { position: relative; height: 180px; width: 100%; display: flex; justify-content: center; }
    .legend-box { display: inline-block; width: 14px; height: 14px; margin-right: 5px; vertical-align: middle; }
    .bg-green { background-color: #4caf50; }
    .bg-red { background-color: #f44336; }
    .text-green { color: #4caf50 !important; }
    .text-red { color: #f44336 !important; }

    .custom-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .custom-table th { background-color: #c8e6c9; color: #2e7d32; padding: 12px; font-size: 14px; text-align: center; border: 1px solid #eee; white-space: nowrap; }
    .custom-table td { padding: 12px; text-align: center; border: 1px solid #eee; font-size: 14px; font-weight: bold; }
    .custom-table tfoot td { background-color: #fff9c4; font-weight: bold; }
</style>

<div class="summary-wrapper">
    <div class="header-card">
        <div style="display: flex; align-items: center;">
            <div class="header-icon">
                <i class="fa fa-file-text-o"></i>
            </div>
            <div class="header-info">
                <h2>Daily Sale Visit Summary</h2>
                <p>Date: {{ \Carbon\Carbon::parse($today)->format('n/j/Y') }}<br>Business : {{ $business_name }}</p>
            </div>
        </div>
        <div>
            <button id="btnSendTelegram" class="btn btn-primary" style="background-color: #0088cc; border-color: #0088cc; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                <i class="fa fa-telegram"></i> Send to Telegram
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="stat-card">
                <div class="stat-title">Total Sale Visit</div>
                <div class="stat-value">
                    {{ $todays_visits_count }} / {{ $total_target }} 
                    <span style="font-size:28px; color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">({{ $overall_variance }}%)</span>
                </div>
                <div class="stat-remaining" style="color:{{ $overall_variance >= 80 ? '#4caf50' : '#f44336' }};">{{ abs($overall_remaining) }} {{ $overall_remaining <= 0 ? 'remaining' : 'over target' }}</div>
            </div>

            <div class="stat-card" style="margin-bottom: 0;">
                <div class="stat-title">Today Vs Yesterday</div>
                <div class="target-row">
                    <span>Today's Visit</span>
                    <span><span class="dark-text">({{ $todays_visits_count }} / {{ $total_target }})</span> <span class="red-text">{{ $overall_variance }}%</span></span>
                </div>
                <div class="target-row">
                    <span>Yesterday's Visit</span>
                    <span><span class="dark-text">({{ $yesterdays_visits_count }} / {{ $yesterday_target }})</span> <span class="red-text">{{ $yesterday_variance }}%</span></span>
                </div>
                <hr style="margin: 10px 0;">
                <div class="target-row" style="align-items: center;">
                    <span>Days-Over-Days</span>
                    <span class="change-box" style="color: {{ $dod_change >= 0 ? '#4caf50' : '#e53935' }}; background: {{ $dod_change >= 0 ? '#e8f5e9' : '#ffebee' }}">
                        <i class="fa {{ $dod_change >= 0 ? 'fa-caret-up' : 'fa-caret-down' }}"></i> 
                        {{ $dod_change > 0 ? '+' : '' }}{{ $dod_change }}%
                    </span>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="stat-card" style="margin-bottom: 0;">
                <div class="stat-title text-center" style="margin-bottom: 10px;">Own vs other Product</div>
                
                <div style="max-width: 320px; margin: 0 auto; padding-top: 20px;">
                    <div style="text-align: left; padding-left: 0; font-size: 13px; color: #4caf50; font-weight: bold; margin-bottom: 5px;">
                        Own product {{ $overall_own_pct }}%
                    </div>

                    <div class="chart-container">
                        <canvas id="productDonutChart"></canvas>
                    </div>
                    
                    <div style="text-align: right; padding-right: 0; font-size: 13px; color: #f44336; font-weight: bold; margin-top: 5px; margin-bottom: 15px;">
                        Other Product {{ $overall_other_pct }}%
                    </div>
                </div>

                <div class="text-center" style="font-size: 14px; font-weight: bold; color: #555;">
                    <span class="legend-box bg-green"></span> <span class="text-green">Own product</span> &nbsp;&nbsp;&nbsp;&nbsp;
                    <span class="legend-box bg-red"></span> <span class="text-red">Other Product</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 20px;">
        <div class="col-md-12">
            <div class="stat-card">
                <div class="stat-title" style="color: #000; font-size: 22px;">Sale Visit Report</div>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th style="text-align: left;">SE's Name</th>
                                <th>Actual/Target Visit</th>
                                <th>Remain Target</th>
                                <th>Own vs Other Product</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sales_report as $rep_id => $row)
                            <tr>
                                <td style="color: #333;">{{ $loop->iteration }}</td>
                                <td style="text-align: left; color: #333;"><em>{{ $row['name'] }}</em></td>
                                
                                <td style="color: #333;">
                                    {{ $row['qty_visit'] }} / {{ $row['target'] }} 
                                    (<span class="{{ $row['variance'] >= 50 ? 'text-green' : 'text-red' }}">{{ $row['variance'] }}%</span>)
                                </td>
                                
                                <td>
                                    <span class="{{ $row['remaining'] == 0 ? 'text-green' : 'text-red' }}">{{ $row['remaining'] }}</span>
                                </td>
                                
                                <td>
                                    <span class="text-green">{{ $row['own_pct'] }}%</span> 
                                    <span style="color: #aaa;">/</span> 
                                    <span class="text-red">{{ $row['other_pct'] }}%</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5">No visits recorded for today.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align: center; color: #333;">Total</td>
                                <td style="color: #333;">
                                    {{ $todays_visits_count }} / {{ $total_target }} 
                                    (<span class="text-red">{{ $overall_variance }}%</span>)
                                </td>
                                <td>
                                    <span class="text-red">{{ $overall_remaining }}</span>
                                </td>
                                <td>
                                    <span class="text-green">{{ $overall_own_pct }}%</span> 
                                    <span style="color: #aaa;">/</span> 
                                    <span class="text-red">{{ $overall_other_pct }}%</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    $(document).ready(function() {
        // 1. Initialize Chart
        var ctx = document.getElementById('productDonutChart').getContext('2d');
        var myDonutChart = new Chart(ctx, {
            type: 'pie', 
            data: {
                labels: ['Own Product', 'Other Product'],
                datasets: [{
                    // Use dynamic variables from Blade
                    data: [{{ $overall_own_pct }}, {{ $overall_other_pct }}],
                    backgroundColor: ['#4caf50', '#f44336'],
                    borderWidth: 1,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false, // Prevents animation so html2canvas captures it correctly
                plugins: {
                    legend: {
                        display: false 
                    }
                }
            }
        });

        // 2. Handle Telegram Send Button
        $('#btnSendTelegram').click(function(e) {
            e.preventDefault();
            var $btn = $(this);
            var originalText = $btn.html();
            
            // Show loading state
            $btn.html('<i class="fa fa-spinner fa-spin"></i> Sending...').prop('disabled', true);

            // Capture the wrapper using html2canvas
            html2canvas(document.querySelector(".summary-wrapper"), {
                scale: 2, // High quality image
                useCORS: true,
                onclone: function(document_clone) {
                    // --- SNAPSHOT FORMATTING (Mobile Layout) ---
                    var cloneWrapper = document_clone.querySelector(".summary-wrapper");
                    
                    // 1. Set a clean Tablet width 
                    cloneWrapper.style.width = '768px'; 
                    cloneWrapper.style.padding = '20px';
                    cloneWrapper.style.margin = '0';
                    cloneWrapper.style.minHeight = 'auto';
                    
                    // 2. Hide the Telegram button in the snapshot
                    var btn = cloneWrapper.querySelector('#btnSendTelegram');
                    if (btn) btn.style.display = 'none';

                    // 3. Stack the top columns neatly
                    var cols = cloneWrapper.querySelectorAll('.col-md-5, .col-md-7');
                    cols.forEach(function(col) {
                        col.style.flex = '0 0 100%';
                        col.style.maxWidth = '100%';
                        col.style.padding = '0 15px'; 
                        col.style.marginBottom = '20px'; 
                    });
                    
                    var tableCol = cloneWrapper.querySelector('.col-md-12');
                    if(tableCol) {
                        tableCol.style.padding = '0 15px';
                    }

                    // 4. Clean up Fonts (Reverting to normal/elegant sizes)
                    // Header
                    var headerH2 = cloneWrapper.querySelector('.header-info h2');
                    if (headerH2) headerH2.style.fontSize = '26px';
                    var headerP = cloneWrapper.querySelector('.header-info p');
                    if (headerP) headerP.style.fontSize = '16px';

                    // Cards & Numbers
                    var titles = cloneWrapper.querySelectorAll('.stat-title');
                    titles.forEach(t => { t.style.fontSize = '22px'; t.style.marginBottom = '15px'; });
                    
                    var values = cloneWrapper.querySelectorAll('.stat-value');
                    values.forEach(v => v.style.fontSize = '36px');
                    
                    var targets = cloneWrapper.querySelectorAll('.target-row');
                    targets.forEach(t => { 
                        t.style.fontSize = '16px'; 
                        t.style.display = 'flex';
                        t.style.alignItems = 'center'; 
                        t.style.marginBottom = '8px';
                    });

                    // Adjust Chart Container
                    var chartContainer = cloneWrapper.querySelector('.chart-container');
                    if (chartContainer) chartContainer.style.height = '240px';

                    // Clean Table
                    var ths = cloneWrapper.querySelectorAll('.custom-table th');
                    ths.forEach(th => { 
                        th.style.fontSize = '15px'; 
                        th.style.padding = '12px 5px'; 
                        th.style.whiteSpace = 'nowrap'; 
                    });
                    
                    var tds = cloneWrapper.querySelectorAll('.custom-table td');
                    tds.forEach(td => { 
                        td.style.fontSize = '15px'; 
                        td.style.padding = '12px 5px'; 
                    });
                }
            }).then(canvas => {
                // Convert canvas to Base64 image
                var base64image = canvas.toDataURL("image/png");

                // Send via AJAX to Laravel Controller
                $.ajax({
                    url: '{{ route("daily-sale-visit-summary.send-telegram") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        image: base64image
                    },
                    success: function(response) {
                        if(response.success) {
                            toastr.success(response.message);
                        } else {
                            toastr.error('Failed: ' + response.message);
                        }
                    },
                    error: function() {
                        toastr.error('An error occurred while sending to Telegram.');
                    },
                    complete: function() {
                        // Reset button state
                        $btn.html(originalText).prop('disabled', false);
                    }
                });
            }).catch(err => {
                $btn.html(originalText).prop('disabled', false);
                toastr.error('Failed to capture snapshot: ' + err.message);
            });
        });
    });
</script>
@endsection