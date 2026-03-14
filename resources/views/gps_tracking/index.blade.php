@extends('layouts.app')

@section('title', 'GPS Tracking')

@section('content')
<section class="content-header">
    <h1>GPS Tracking</h1>
</section>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<style>
    /* 1. Equal Height and Map Styling */
    #map { height: 100%; width: 100%; border: 1px solid #ddd; }
    .map-container { height: 100%; width: 100%; position: relative; }
    
    .gps-container {
        display: flex;
        gap: 10px;
        height: 650px;
    }
    
    .gps-list-50, 
    .gps-map-50 {
        flex: 1;
        max-width: 50%;
        height: 100%;
    }

    .gps-list-50 {
        display: flex;
        flex-direction: column;
        height: 100%; 
    }

    .gps-list-50 .box {
        display: flex;
        flex-direction: column;
        height: 100%;
        margin-bottom: 0;
    }

    .gps-list-50 .box-header {
        flex-shrink: 0;
        border-bottom: 1px solid #ddd;
    }

    .gps-list-50 .box-body {
        flex: 1; 
        overflow: hidden; 
        padding: 15px !important; 
    }
    
    .dataTables_wrapper {
        display: flex;
        flex-direction: column;
        height: 100%; 
    }

    .dataTables_wrapper .top {
        flex-shrink: 0;
        padding: 0 0 10px 0 !important;
        border-bottom: 1px solid #ddd; 
        display: flex; 
        align-items: center;
    }

    .dataTables_wrapper .dt-buttons {
        margin-left: auto; 
        margin-right: auto;
        float: none !important;
    }
    
    .dataTables_wrapper .dataTables_filter {
        margin-left: 20px !important; 
        margin-right: 0 !important;
        float: none !important;
    }

    .dataTables_wrapper .dataTables_length {
        float: none !important;
        margin-right: 15px;
    }
    
    .dataTables_scroll {
        flex: 1; 
        overflow-y: auto;
        overflow-x: hidden;
    }

    .dataTables_wrapper .dataTables_scrollBody {
        margin-bottom: 0 !important;
    }
    
    .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    .dataTables_scrollHeadInner table {
        width: 100% !important;
    }
    
    .dataTables_wrapper .bottom {
        flex-shrink: 0;
        padding: 10px 0 !important;
        border-top: 1px solid #ddd;
        margin-top: auto;
        display: flex; 
        justify-content: flex-end; 
        align-items: center;
    }

    .dataTables_wrapper .dataTables_info {
        font-size: 12px !important;
        padding: 0 !important;
        margin: 0 !important;
        display: inline-block;
        margin-right: auto !important; 
    }

    .dataTables_wrapper .dataTables_paginate {
        margin: 0 !important;
        padding: 0 !important;
        display: inline-block;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 1px 4px !important; 
        font-size: 11px !important;
        min-width: 15px; 
        line-height: 1.2;
    }
    
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        padding: 3px 5px !important;
        font-size: 12px !important;
        height: auto !important;
    }

    .dataTables_wrapper .dt-buttons .btn {
        padding: 3px 8px !important;
        font-size: 11px !important;
        margin-right: 5px;
    }

    .table {
        margin-bottom: 0 !important;
    }

    .gps-map-50 {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .gps-map-50 .box {
        height: 100%;
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
    }

    .gps-map-50 .box-header {
        flex-shrink: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .gps-map-50 .box-body {
        height: 100%;
        padding: 0 !important;
        flex: 1;
        overflow: hidden;
    }

    /* Fullscreen Map Styling */
    .gps-map-50.fullscreen-active {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        max-width: 100%;
        z-index: 9999;
        flex: none;
        gap: 0;
    }

    .gps-map-50.fullscreen-active .box {
        border-radius: 0;
    }

    .gps-map-50.fullscreen-active .box-header {
        background-color: #f5f5f5;
        border-bottom: 2px solid #ddd;
        padding: 10px 15px;
    }

    .fullscreen-btn {
        padding: 5px 10px;
        font-size: 12px;
        cursor: pointer;
    }
</style>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Filters</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>User:</label>
                                @if(isset($users) && is_array($users) && count($users) > 0)
                                    <select id="filter_user_id" class="form-control select2" style="width:100%">
                                        <option value="">-- Select User --</option>
                                        @foreach($users as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="alert alert-warning">No users available</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Date Range:</label>
                                <input type="text" id="filter_date_range" class="form-control" placeholder="Select date range" readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button id="apply_filters" class="btn btn-primary btn-block">
                                    <i class="fa fa-filter"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="gps-container">
                <div class="gps-list-50">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Visit Log <span id="check_in_count" class="badge badge-primary">0</span></h3>
                        </div>
                        <div class="box-body" style="padding: 0;">
                            <table id="gps_points_table" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>            
                                        <th>Contact Name</th>    
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="gps-map-50">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                Map View - 
                                <span style="color: #28a745; font-weight: bold;">🟢 START</span> to 
                                <span style="color: #dc3545; font-weight: bold;">🔴 STOP</span>
                                <span style="color: #3388ff; font-weight: bold; margin-left: 20px;">🔵 OUTLET</span> 
                            </h3>
                            <button type="button" class="btn btn-sm btn-default fullscreen-btn" id="fullscreen_map" title="Fullscreen Map">
                                <i class="fa fa-expand"></i> Fullscreen
                            </button>
                        </div>
                        <div class="box-body">
                            <div class="map-container">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css" />
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/daterangepicker/3.1/daterangepicker.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/daterangepicker/3.1/daterangepicker.min.js"></script>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<style>
    /* Highlight style for clicked row */
    #gps_points_table tbody tr.selected-row {
        background-color: #b3d7ff !important;
    }
    #gps_points_table tbody tr {
        cursor: pointer; /* Show pointer to indicate clickable */
    }
</style>

<script type="text/javascript">
// Global variables
var map;
var startMarker = null;
var endMarker = null;
var polylines = [];
var arrowMarkers = [];
var allPoints = [];
var hasAppliedFilter = false;
var mapBounds = L.latLngBounds([]);
var salesMarkers = []; // Array to store outlet markers

const CHUNK_SIZE = 500;

// --- INITIALIZE MAP ---
function initializeMap() {
    if (map) { map.remove(); }
    map = L.map('map').setView([11.5564, 104.8855], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
}

initializeMap();

$(document).ready(function() {
    console.log("Initializing GPS Tracking with Row Selection");

    // --- DATATABLE ---
    var table = $('#gps_points_table').DataTable({
        processing: true,
        serverSide: true,
        paging: true,
        pageLength: 10,
        lengthChange: true,
        searching: true,
        info: true,
        deferLoading: 0,
        scrollY: '100%',     
        scrollCollapse: true, 
        ajax: {
            url: '{{ route("gps-tracking.index") }}',
            data: function (d) {
                if (!hasAppliedFilter) return false;
                
                var dateRange = $('#filter_date_range').val();
                var startDate = '', endDate = '';

                if (dateRange && dateRange.trim() !== '') {
                    var dates = dateRange.split(' - ');
                    if (dates.length === 2) {
                        startDate = dates[0].trim();
                        endDate = dates[1].trim();
                    }
                }
                d.user_id = $('#filter_user_id').val() || '';
                d.start_date = startDate;
                d.end_date = endDate;
            }
        },
        columns: [
            { data: 'date', name: 'transactions_visit.transaction_date' },
            { data: 'contact_name', name: 'contacts.name' },
            { data: 'username', name: 'users.username' },
            // HIDDEN COLUMN FOR ID
            { data: 'contact_id', name: 'transactions_visit.contact_id', visible: false } 
        ],
        columnDefs: [
            {
                targets: [0, 1, 2],
                render: function(data) { return '<small>' + (data || '') + '</small>'; }
            }
        ],
        dom: '<"top"lBf>rt<"bottom"ip>', 
        buttons: [
            { extend: 'csv', text: 'Export to CSV' },
            { extend: 'excel', text: 'Export to Excel' }
        ]
    });

    // --- ROW CLICK EVENT (THE NEW FEATURE) ---
    $('#gps_points_table tbody').on('click', 'tr', function () {
        var data = table.row(this).data();
        
        // Highlight Row
        if ($(this).hasClass('selected-row')) {
            $(this).removeClass('selected-row');
        } else {
            $('#gps_points_table tbody tr.selected-row').removeClass('selected-row');
            $(this).addClass('selected-row');
        }

        // Focus Map
        if (data && data.contact_id) {
            focusOnOutlet(data.contact_id);
        }
    });

    // --- FILTER CONFIG (RESTORED FULL OPTIONS) ---
    $('#filter_date_range').daterangepicker({
        locale: { format: 'YYYY-MM-DD' },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')], // Added back
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, function (start, end) {
        $('#filter_date_range').val(start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
    });

    $('#apply_filters').on('click', function() {
        var userId = $('#filter_user_id').val();
        if (!userId) { alert("Please select a User."); return; }
        
        var dateRange = $('#filter_date_range').val();
        var startDate = '', endDate = '';
        if (dateRange) {
            var dates = dateRange.split(' - ');
            startDate = dates[0].trim(); endDate = dates[1].trim();
        } else { alert("Select date range."); return; }
        
        hasAppliedFilter = true;
        
        // 1. Load Table
        table.ajax.reload();

        // 2. Load Map Route
        $('#check_in_count').text('Loading Map...');
        $.ajax({
            url: '{{ route("gps-tracking.index") }}',
            data: {
                user_id: userId, start_date: startDate, end_date: endDate,
                get_map_data: true, draw: 1, length: 20000
            },
            success: function(response) {
                clearMapData();
                if (response && response.data && response.data.length > 0) {
                    allPoints = response.data;
                    updateMapWithChunking();
                    $('#check_in_count').text(response.data.length + " GPS Points");
                } else {
                    $('#check_in_count').text('0 Points');
                }
                
                // 3. Load Markers (After map clear)
                setTimeout(function() {
                    loadSalesVisitMarkers(userId, startDate, endDate);
                }, 500);
            }
        });
    });

    // --- FULLSCREEN ---
    $('#fullscreen_map').on('click', function() {
        $('.gps-map-50').toggleClass('fullscreen-active');
        $('body').css('overflow', $('.gps-map-50').hasClass('fullscreen-active') ? 'hidden' : 'auto');
        setTimeout(function() { map.invalidateSize(); }, 300);
    });
});

// --- HELPER: FOCUS ON OUTLET ---
function focusOnOutlet(contactId) {
    if (!contactId || salesMarkers.length === 0) return;

    // Find marker with matching contact_id
    var targetMarker = salesMarkers.find(m => m.options.contactId == contactId);

    if (targetMarker) {
        // Zoom to marker and open popup
        map.flyTo(targetMarker.getLatLng(), 17, {
            animate: true,
            duration: 1.5
        });
        targetMarker.openPopup();
    } else {
        console.log("Marker not found for Contact ID: " + contactId);
    }
}

// --- DATA LOADING FUNCTIONS ---

function loadSalesVisitMarkers(userId, startDate, endDate) {
    $.ajax({
        url: '{{ url("gps-tracking-visit-history") }}',
        data: { user_id: userId, start_date: startDate, end_date: endDate },
        success: function(response) {
            addSalesVisitMarkersToMap(response.data);
        }
    });
}

function addSalesVisitMarkersToMap(visitData) {
    if (!visitData || visitData.length === 0) return;

    const groupedContacts = {};
    visitData.forEach(visit => {
        if (!visit.contact_id || !visit.latitude || !visit.longitude) return;
        const cid = visit.contact_id;
        if (!groupedContacts[cid]) {
            groupedContacts[cid] = {
                contact_name: visit.contact_name,
                contact_id: cid,
                latitude: visit.latitude,
                longitude: visit.longitude,
                statuses: []
            };
        }
        groupedContacts[cid].statuses.push(visit.visit_status);
    });

    Object.keys(groupedContacts).forEach(cid => {
        const contact = groupedContacts[cid];

        const visitIcon = L.icon({
            iconUrl: 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 48"%3E%3Cpath fill="%233388ff" d="M16 0C7.163 0 0 7.163 0 16c0 12 16 32 16 32s16-20 16-32c0-8.837-7.163-16-16-16z"/%3E%3Ccircle cx="16" cy="16" r="7" fill="white"/%3E%3C/svg%3E',
            iconSize: [28, 42], iconAnchor: [14, 42], popupAnchor: [0, -42]
        });

        const marker = L.marker([contact.latitude, contact.longitude], {
            icon: visitIcon,
            zIndexOffset: 800,
            contactId: contact.contact_id // <--- IMPORTANT: Store ID for matching
        }).addTo(map);

        const total = contact.statuses.length;
        const completedCount = contact.statuses.filter(s => s === 'Completed' || s === 'completed').length;
        const missedCount = contact.statuses.filter(s => s === 'Missed' || s === 'missed').length;

        const popupContent = `
            <div style="font-size: 12px; padding: 5px; font-family: Arial; background-color: #e3f2fd; border-radius: 4px;">
                <strong style="color: #3388ff; font-size: 13px;">宵 CUSTOMER VISIT</strong><br>
                <strong>Name:</strong> ${contact.contact_name}<br>
                <strong>Total Visits:</strong> ${total}<br>
                <span style="color: green;"><strong>Completed:</strong> ${completedCount}</span><br>
                <span style="color: red;"><strong>Missed:</strong> ${missedCount}</span>
            </div>
        `;

        marker.bindPopup(popupContent);
        salesMarkers.push(marker);
        mapBounds.extend(L.latLng(contact.latitude, contact.longitude));
    });

    if (mapBounds.isValid()) map.fitBounds(mapBounds, { padding: [50, 50] });
}

// --- ROUTING FUNCTIONS ---

function prepareCoordinates(points) {
    const validPoints = [];
    const osrmChunks = [];
    
    points.forEach((p) => {
        let lat, lng;
        if (p.lat !== undefined && p.lng !== undefined) {
            lat = parseFloat(p.lat); lng = parseFloat(p.lng);
        } else if (p.location) {
            const coords = p.location.split(',');
            if (coords.length === 2) { lat = parseFloat(coords[0].trim()); lng = parseFloat(coords[1].trim()); }
        }
        
        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            validPoints.push({ lat: lat, lng: lng, time: p.time, username: p.username });
            mapBounds.extend(L.latLng(lat, lng));
        }
    });

    if (validPoints.length > 0) drawStartMarker(validPoints[0]);
    if (validPoints.length > 1) drawStopMarker(validPoints[validPoints.length - 1]);

    for (let i = 0; i < validPoints.length; i += CHUNK_SIZE) {
        const chunk = validPoints.slice(i, i + CHUNK_SIZE);
        const osrmFormatChunk = chunk.map(p => `${p.lng},${p.lat}`);
        if (osrmFormatChunk.length >= 2) osrmChunks.push(osrmFormatChunk);
    }
    return osrmChunks;
}

function drawStartMarker(point) {
    if (startMarker) map.removeLayer(startMarker);
    const greenIcon = L.icon({
        iconUrl: 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 48"%3E%3Cpath fill="%2328a745" d="M16 0C7.163 0 0 7.163 0 16c0 12 16 32 16 32s16-20 16-32c0-8.837-7.163-16-16-16z"/%3E%3Ccircle cx="16" cy="16" r="7" fill="white"/%3E%3C/svg%3E',
        iconSize: [32, 48], iconAnchor: [16, 48], popupAnchor: [0, -48]
    });
    startMarker = L.marker([point.lat, point.lng], { icon: greenIcon, zIndexOffset: 1000 }).addTo(map)
        .bindPopup(`<strong>START:</strong> ${point.time}`);
}

function drawStopMarker(point) {
    if (endMarker) map.removeLayer(endMarker);
    const redIcon = L.icon({
        iconUrl: 'data:image/svg+xml;charset=utf-8,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 48"%3E%3Cpath fill="%23dc3545" d="M16 0C7.163 0 0 7.163 0 16c0 12 16 32 16 32s16-20 16-32c0-8.837-7.163-16-16-16z"/%3E%3Ccircle cx="16" cy="16" r="7" fill="white"/%3E%3C/svg%3E',
        iconSize: [32, 48], iconAnchor: [16, 48], popupAnchor: [0, -48]
    });
    endMarker = L.marker([point.lat, point.lng], { icon: redIcon, zIndexOffset: 999 }).addTo(map)
        .bindPopup(`<strong>STOP:</strong> ${point.time}`);
}

async function updateMapWithChunking() {
    clearMapData();
    if (!allPoints || allPoints.length < 2) return;

    const chunks = prepareCoordinates(allPoints);
    let combinedRoute = [];

    for (let i = 0; i < chunks.length; i++) {
        const coordinatesString = chunks[i].join(';');
        const proxyUrl = `/api/osrm-match?coordinates=${coordinatesString}&overview=full&geometries=geojson&steps=false`;
        
        try {
            const response = await fetch(proxyUrl).then(res => res.json());
            if (response.code === 'Ok' && response.matchings.length > 0) {
                const routeCoordinates = response.matchings[0].geometry.coordinates.map(c => [c[1], c[0]]);
                combinedRoute = combinedRoute.concat(routeCoordinates);
            }
        } catch (error) { console.error("OSRM Error", error); }
    }

    if (combinedRoute.length > 1) {
        const polyline = L.polyline(combinedRoute, { color: '#3388ff', weight: 4, opacity: 0.8 }).addTo(map);
        polylines.push(polyline);
    }
    if (mapBounds.isValid()) map.fitBounds(mapBounds, { padding: [50, 50] });
}

function clearMapData() {
    polylines.forEach(p => map.removeLayer(p)); polylines = [];
    salesMarkers.forEach(m => map.removeLayer(m)); salesMarkers = [];
    if (startMarker) { map.removeLayer(startMarker); startMarker = null; }
    if (endMarker) { map.removeLayer(endMarker); endMarker = null; }
    mapBounds = L.latLngBounds([]);
}
</script>
@endsection