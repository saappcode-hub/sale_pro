@extends('layouts.app')

@section('title', __('GPS Trip Details'))

@section('content')
<section class="content-header">
    <h1>
        {{ __('GPS Trip Details') }}
        <small>Trip #{{ $trip->id }}</small>
    </h1>
</section>

<style>
    #trip-detail-map { height: 500px; width: 100%; border: 1px solid #ddd; margin-bottom: 20px; }
    .info-box { background: #f5f5f5; padding: 15px; border-radius: 4px; margin-bottom: 15px; border-left: 4px solid #007bff; }
    .info-label { font-weight: bold; color: #333; margin-top: 10px; }
    .info-value { color: #666; font-size: 14px; }
    .gps-points-table { margin-top: 20px; }
    .point-row { padding: 8px; border-bottom: 1px solid #eee; }
    .point-row:hover { background: #f9f9f9; }
    .badge-success { background: #28a745; color: white; }
    .badge-primary { background: #007bff; color: white; }
    .duration-badge { background: #17a2b8; color: white; padding: 5px 10px; border-radius: 4px; }
</style>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('Trip Information') }}</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('gps-tracking.index') }}" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> {{ __('Back to List') }}
                        </a>
                    </div>
                </div>

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">{{ __('User') }}</div>
                                <div class="info-value">{{ $trip->user->username ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">{{ __('Trip Date') }}</div>
                                <div class="info-value">{{ $trip->trip_date ? date('d-m-Y', strtotime($trip->trip_date)) : 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">{{ __('Total Duration') }}</div>
                                <div class="info-value">
                                    @if($trip->clock_in_time && $trip->clock_out_time)
                                        <span class="duration-badge">
                                            {{ \Carbon\Carbon::parse($trip->clock_out_time)->diff(\Carbon\Carbon::parse($trip->clock_in_time))->format('%H:%I:%S') }}
                                        </span>
                                    @else
                                        <span>-</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <div class="info-label">{{ __('Total GPS Points') }}</div>
                                <div class="info-value"><span class="badge badge-primary" id="table_point_count">{{ $trip->gpsPoints->count() }}</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box">
                                <div class="info-label">{{ __('Clock In Time') }}</div>
                                <div class="info-value">
                                    @if($trip->clock_in_time)
                                        {{ date('H:i:s', strtotime($trip->clock_in_time)) }}
                                    @else
                                        <span class="badge">Not Set</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <div class="info-label">{{ __('Clock Out Time') }}</div>
                                <div class="info-value">
                                    @if($trip->clock_out_time)
                                        {{ date('H:i:s', strtotime($trip->clock_out_time)) }}
                                    @else
                                        <span class="badge">Not Set</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box">
                                <div class="info-label">{{ __('Trip Status') }}</div>
                                <div class="info-value">
                                    @if($trip->clock_in_time && $trip->clock_out_time)
                                        <span class="badge badge-success">{{ __('Completed') }}</span>
                                    @else
                                        <span class="badge" style="background: #ffc107; color: black;">{{ __('In Progress') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">{{ __('Start Location') }}</div>
                                <div class="info-value">
                                    @if($trip->start_location)
                                        <code>{{ $trip->start_location }}</code>
                                    @else
                                        <span class="badge">Not Set</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-box">
                                <div class="info-label">{{ __('End Location') }}</div>
                                <div class="info-value">
                                    @if($trip->end_location)
                                        <code>{{ $trip->end_location }}</code>
                                    @else
                                        <span class="badge">Not Set</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h4>{{ __('Trip Route Map') }}</h4>
                            <div id="trip-detail-map"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('GPS Points Log') }} <span class="badge badge-primary" id="table_point_count">{{ $trip->gpsPoints->count() }}</span></h3>
                </div>

                <div class="box-body table-responsive">
                    <table id="gps_points_table_show" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr style="background-color: #007bff; color: white;">
                                <th style="width: 50px;">{{ __('#') }}</th>
                                <th>{{ __('Time') }}</th>
                                <th>{{ __('Latitude') }}</th>
                                <th>{{ __('Longitude') }}</th>
                                <th>{{ __('Location String') }}</th>
                                <th style="width: 100px;">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />

<script type="text/javascript">
$(document).ready(function() {
    var gpsPoints = @json($gpsPoints);
    var trip = @json($trip);

    // Map Initialization
    var map = L.map('trip-detail-map').setView([11.544873, 104.892167], 12); // Default View
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    var currentPointMarker = null;

    function drawRouteAndMarkers(points) {
        // Redraw route and markers only if needed (initial load)
        if (points.length === 0) return;

        var polylineCoords = points.map(p => [p.latitude, p.longitude]);
        
        L.polyline(polylineCoords, {
            color: '#3388ff',
            weight: 4,
            opacity: 0.8,
            smoothFactor: 1
        }).addTo(map);

        // Add start marker (green)
        L.circleMarker([points[0].latitude, points[0].longitude], {
            radius: 8,
            fillColor: '#00aa00',
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map).bindPopup(`
            <strong>Start Point</strong><br>
            Time: ${points[0].time}<br>
            Coords: ${points[0].latitude}, ${points[0].longitude}
        `);

        // Add end marker (red)
        const endIndex = points.length - 1;
        L.circleMarker([points[endIndex].latitude, points[endIndex].longitude], {
            radius: 8,
            fillColor: '#ff0000',
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map).bindPopup(`
            <strong>End Point</strong><br>
            Time: ${points[endIndex].time}<br>
            Coords: ${points[endIndex].latitude}, ${points[endIndex].longitude}
        `);

        // Fit bounds to show entire route
        const bounds = L.latLngBounds(polylineCoords);
        map.fitBounds(bounds, { padding: [50, 50] });
    }

    // Draw initial route
    drawRouteAndMarkers(gpsPoints);

    // Initialize DataTables for the GPS Points Log
    var table = $('#gps_points_table_show').DataTable({
        processing: true,
        serverSide: true,
        paging: true,
        searching: true,
        info: true,
        pageLength: 10, // FIX: Set default page length to 10
        order: [[1, 'asc']], // Order by Time column by default
        ajax: {
            // This URL calls the new getShowData method in the controller
            url: '{{ route("gps-tracking.show-data", ["trip_id" => $trip->id]) }}',
            type: 'GET',
            dataSrc: function (json) {
                // Update the total point count badge
                $('#table_point_count').text(json.recordsTotal);
                return json.data;
            }
        },
        columns: [
            // Row Index column (#)
            { 
                data: null, 
                name: '#',
                searchable: false, 
                orderable: false,
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 'gps_time', name: 'gps_time' },
            { data: 'latitude', name: 'latitude', searchable: false, orderable: false },
            { data: 'longitude', name: 'longitude', searchable: false, orderable: false },
            { data: 'location_string', name: 'location_string' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: [1, 4], // Time and Location String
                render: function(data) {
                    return '<small><code>' + data + '</code></small>';
                }
            }
        ]
    });
    
    // View individual point on map (Delegate click event to the table body)
    $('#gps_points_table_show').on('click', '.view-point-on-map', function() {
        var lat = $(this).data('lat');
        var lng = $(this).data('lng');
        var time = $(this).data('time');

        // Center map on point
        map.setView([lat, lng], 16);

        // Remove previous temporary marker if it exists
        if (currentPointMarker) {
            map.removeLayer(currentPointMarker);
        }

        // Add new temporary marker (Yellow)
        currentPointMarker = L.circleMarker([lat, lng], {
            radius: 10,
            fillColor: '#ffff00',
            color: '#000',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map).bindPopup(`
            <strong>Point Detail</strong><br>
            Time: ${time}<br>
            Coords: ${lat}, ${lng}
        `).openPopup();

        // Optional: Remove marker after 5 seconds
        setTimeout(function() {
            if (currentPointMarker) {
                map.removeLayer(currentPointMarker);
                currentPointMarker = null;
            }
        }, 5000);
    });
});
</script>
@endsection