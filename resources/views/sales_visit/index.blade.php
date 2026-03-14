@extends('layouts.app')

@section('title', __('Sale Tracking'))

@section('content')
<section class="content-header">
    <h1>{{ __('Sale Tracking') }}</h1>
</section>
<style>
    .nav-tabs {
        border-bottom: none;
    }

    .nav-tabs .nav-link:hover {
        color: #0056b3;
    }

    .nav-tabs .nav-link {
        color: black;
    }
    .boxtie .box .box-body {
        padding: 0px 15px;
    }

    .row {
        margin-right: -0px;
        margin-left: -0px;
    }

    #map { height: 500px; width: 100%; display: none; } /* Ensure map has proper dimensions */
    .visit-summary { display: none; background: #fff; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; }

    /* Ensure parent containers have proper dimensions for the map */
    .visit-history-container, .map-container-70 {
        height: 100%;
        width: 100%;
        min-height: 600px; /* Ensure minimum height for map visibility */
        position: relative; /* Ensure proper positioning */
        overflow: visible; /* Prevent overflow issues */
    }

    /* Custom widths for 30/70 split */
    .visit-summary-30 {
        flex: 0 0 30%;
        max-width: 30%;
    }
    .map-container-70 {
        flex: 0 0 70%;
        max-width: 70%;
    }

    /* Ensure tab content has enough height */
    .tab-content {
        height: 100%;
        min-height: 600px;
    }

    /* Debug styling for map container */
    #map.debug {
        border: 2px solid red; /* Visual debug for map element */
    }
    /* Hide the default Google Maps InfoWindow close (X) button */
    .gm-style-iw button.gm-ui-hover-effect {
        display: none !important;
    }
    .badge-completed {
        background-color: #28a745; /* green */
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
        margin-right: 5px;
    }

    .badge-missed {
        background-color: #dc3545; /* red */
        color: #fff;
        padding: 3px 6px;
        border-radius: 4px;
    }
</style>
<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id', __('purchase.business_location') . ':') !!}
                {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_location_id']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_contact_id', __('Customer') . ':') !!}
                {!! Form::select('sell_list_filter_contact_id', $contact, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_contact_id']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_user_id', __('report.user') . ':') !!}
                {!! Form::select('sell_list_filter_user_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_province_id', __('Province') . ':') !!}
                {!! Form::select('sell_list_filter_province_id', $cambodia_province, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_province_id']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_district_id', __('District') . ':') !!}
                {!! Form::select('sell_list_filter_district_id', $cambodia_district, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_district_id']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_commune_id', __('Commune') . ':') !!}
                {!! Form::select('sell_list_filter_commune_id', $cambodia_commune, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'sell_list_filter_commune_id']) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 text-right">
            <button id="apply_filters" class="btn btn-primary" style="background-color: #007bff;">{{ __('Apply Filters') }}</button>
        </div>
    </div>
    @endcomponent

    <div class="row boxtie">
        @component('components.widget', ['class' => 'box-primary'])
            <ul class="nav nav-tabs" role="tablist" style="padding-bottom: 10px;">
                <li class="nav-item">
                    <a class="nav-link active" id="list-visit-tab" data-toggle="tab" href="#list-visit" role="tab" aria-controls="list-visit" aria-selected="true">
                        {{ __('List Visit') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="visit-history-tab" data-toggle="tab" href="#visit-history" role="tab" aria-controls="visit-history" aria-selected="false">
                        {{ __('Visit History') }}
                    </a>
                </li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="list-visit" role="tabpanel" aria-labelledby="list-visit-tab">
                    <div class="col-md-12" id="list_visit_content">
                        <table class="table table-bordered table-striped" id="sales_order_table">
                            <thead>
                                <tr>
                                    <th>@lang('messages.action')</th>
                                    <th>Date</th>
                                    <th>Contact ID</th>
                                    <th>Contact Name</th>
                                    <th>Contact Mobile</th>
                                    <th>Location</th>
                                    <th>User</th>
                                    <th>SaleRep</th>  <!-- ADD THIS LINE -->
                                    <th>Province</th>
                                    <th>District</th>
                                    <th>Commune</th>
                                    <th>Own Product</th>
                                    <th>Other Product</th>
                                    <th>Visit Status</th>
                                    <th>Checkin Distance</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr style="background-color: #D2D6DE;">
                                    <th colspan="11" style="color: black;">Total:</th>
                                    <th id="totalOwnProduct"></th>
                                    <th id="totalOtherProduct"></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="visit-history" role="tabpanel" aria-labelledby="visit-history-tab">
                    <div class="visit-history-container row">
                        <div class="visit-summary visit-summary-30 col-md-12 col-lg-3" style="border: none;">
                            <h4>Visit Summary</h4>
                            <p>Total Visit : <span id="totalVisits">0</span></p>
                            <p>Status:
                                <span class="badge-completed">Completed: <span id="completedVisits">0</span></span>
                                <span class="badge-missed">Missed: <span id="missedVisits">0</span></span>
                            </p>
                        </div>
                        <div class="map-container map-container-70 col-md-12 col-lg-9" id="map" class="debug"></div>
                    </div>
                </div>
            </div>
        @endcomponent
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyANih0M4xrv2kECs4P1CBV8b8oxqwbOr88"></script>
<script type="text/javascript">
$(document).ready(function() {
    var table = $('#sales_order_table').DataTable({
        processing: true,
        serverSide: true,
        scrollY: "75vh",
        scrollX: true,
        scrollCollapse: true,
        pageLength: 25,
        ajax: {
            url: '{{ url("sales-order-visit") }}',
            data: function (d) {
                d.location_id = $('#sell_list_filter_location_id').val() || '';
                d.contact_id = $('#sell_list_filter_contact_id').val() || '';
                d.user_id = $('#sell_list_filter_user_id').val() || '';

                var $dateInput = $('#sell_list_filter_date_range');
                var drp = $dateInput.data('daterangepicker');
                if ($dateInput.val() && drp) {
                    d.start_date = drp.startDate.format('YYYY-MM-DD');
                    d.end_date = drp.endDate.format('YYYY-MM-DD');
                } else {
                    d.start_date = '';
                    d.end_date = '';
                }

                d.province_id = $('#sell_list_filter_province_id').val() || '';
                d.district_id = $('#sell_list_filter_district_id').val() || '';
                d.commune_id = $('#sell_list_filter_commune_id').val() || '';
            },
            beforeSend: function(xhr) {
                const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                if (token) {
                    xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(token[1]));
                }
            },
            error: function(xhr, error, thrown) {
                if (xhr.status === 401) {
                    console.error('Authentication failed: Please log in.', xhr.responseText);
                    alert('You must be logged in to access this data. Please log in and try again.');
                } else {
                    console.error('Error loading DataTable:', xhr.status, xhr.responseText);
                    alert('An error occurred while loading the DataTable. Please check the console for details.');
                }
            }
        },
        columns: [
            { data: 'action', name: 'action', orderable: false, searchable: false },
            { data: 'date', name: 'transactions_visit.transaction_date' },
            { data: 'visit_no', name: 'transactions_visit.visit_no' },
            { data: 'contact_name', name: 'contacts.name' },
            { data: 'contact_mobile', name: 'contacts.mobile' },
            { data: 'location_name', name: 'business_locations.name' },
            { data: 'username', name: 'users.username' },
            { data: 'sale_rep', name: 'transactions_visit.sale_rep' }, // ADD THIS LINE
            { data: 'province_name', name: 'cambodia_provinces.name_en' },
            { data: 'district_name', name: 'cambodia_districts.name_en' },
            { data: 'commune_name', name: 'cambodia_communes.name_en' },
            { data: 'own_product', name: 'transactions_visit.own_product' },
            { data: 'other_product', name: 'transactions_visit.other_product' },
            { data: 'visit_status', name: 'transactions_visit.visit_status' },
            { data: 'checkin_distance', name: 'transactions_visit.checkin_distance' }
        ],
        "footerCallback": function (row, data, start, end, display) {
            var totalOwnProduct = 0;
            var totalOtherProduct = 0;

            data.forEach(function (item) {
                var ownProductValue = item.own_product ? parseInt(item.own_product.match(/\d+/)) || 0 : 0;
                var otherProductValue = item.other_product ? parseInt(item.other_product.match(/\d+/)) || 0 : 0;

                totalOwnProduct += ownProductValue;
                totalOtherProduct += otherProductValue;
            });

            var total = totalOwnProduct + totalOtherProduct;
            var percentageOwn = total > 0 ? ((totalOwnProduct / total) * 100).toFixed(2) : 0;
            var percentageOther = total > 0 ? ((totalOtherProduct / total) * 100).toFixed(2) : 0;

            var formattedOwnProduct = `${totalOwnProduct} (${percentageOwn}%)`;
            var formattedOtherProduct = `${totalOtherProduct} (${percentageOther}%)`;

            var footer = $(this.api().table().footer());
            var ownProductElement = footer.find('#totalOwnProduct');
            var otherProductElement = footer.find('#totalOtherProduct');

            ownProductElement.text(formattedOwnProduct);
            otherProductElement.text(formattedOtherProduct);

            if (percentageOwn <= 49.99) {
                ownProductElement.css("color", "red");
            } else if (percentageOwn >= 50.01) {
                ownProductElement.css("color", "green");
            } else {
                ownProductElement.css("color", "black");
            }

            if (percentageOther <= 49.99) {
                otherProductElement.css("color", "red");
            } else if (percentageOther >= 50.01) {
                otherProductElement.css("color", "green");
            } else {
                otherProductElement.css("color", "black");
            }

            var totalVisits = data.length;
            var completedVisits = data.filter(item => item.visit_status === 'Completed').length;
            var missedVisits = data.filter(item => item.visit_status === 'Missed').length;

            if ($('#list-visit').hasClass('active')) {
                $('#totalVisits').text(totalVisits);
                $('#completedVisits').text(completedVisits);
                $('#missedVisits').text(missedVisits);
            }
        },
        "initComplete": function(settings, json) {
            table.columns.adjust().draw();
        },
        "language": {
            "emptyTable": "No data available in table",
            "loadingRecords": "Loading..."
        }
    });

    $('#list-visit-tab').tab('show');
    $('#list-visit').addClass('show active');
    $('#list_visit_content').show();
    $('#map').hide();
    $('.visit-summary').hide();

    function toggleFilters(tab) {
        const filtersToDisable = [
            '#sell_list_filter_contact_id',
            '#sell_list_filter_location_id',
            '#sell_list_filter_province_id',
            '#sell_list_filter_district_id',
            '#sell_list_filter_commune_id'
        ];

        if (tab === '#list-visit') {
            $('#apply_filters').prop('disabled', true);
            filtersToDisable.forEach(selector => $(selector).prop('disabled', false).trigger('change'));
        } else if (tab === '#visit-history') {
            $('#apply_filters').prop('disabled', false);
            filtersToDisable.forEach(selector => $(selector).prop('disabled', true));
        }
    }

    toggleFilters('#list-visit');

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        $('#sell_list_filter_user_id').val('').trigger('change');

        var drp = $('#sell_list_filter_date_range').data('daterangepicker');
        if (drp) {
            drp.setStartDate(moment().startOf('month'));
            drp.setEndDate(moment().endOf('month'));
            $('#sell_list_filter_date_range').val(
                drp.startDate.format(moment_date_format) + ' ~ ' + drp.endDate.format(moment_date_format)
            );
        }

        var target = $(e.target).attr("href");
        
        if (target === "#list-visit") {
            $('#list_visit_content').show();
            $('#map').hide();
            $('.visit-summary').hide();
            table.columns.adjust().draw();
        } else if (target === "#visit-history") {
            $('#list_visit_content').hide();
            $('.visit-summary').show();
            $('#map').show();
            initializeEmptyMap();
            $('#totalVisits').text(0);
            $('#completedVisits').text(0);
            $('#missedVisits').text(0);
        }

        toggleFilters(target);
    });

    setTimeout(function() {
        table.columns.adjust().draw();
    }, 200);

    $('#sell_list_filter_location_id, #sell_list_filter_contact_id, #sell_list_filter_province_id, #sell_list_filter_district_id, #sell_list_filter_commune_id, #sell_list_filter_user_id').change(function() {
        if (!$(this).prop('disabled')) {
            table.ajax.reload(null, false);
        }
    });

    $('#apply_filters').on('click', function() {
        if ($('#visit-history').hasClass('active')) {
            var userId = $('#sell_list_filter_user_id').val() || '';
            var $dateInput = $('#sell_list_filter_date_range');
            var drp = $dateInput.data('daterangepicker');
            var start_date, end_date;
            if ($dateInput.val() && drp) {
                start_date = drp.startDate.format('YYYY-MM-DD');
                end_date = drp.endDate.format('YYYY-MM-DD');
            } else {
                start_date = '';
                end_date = '';
            }

            $.ajax({
                url: '{{ url("sales-order-visit-history") }}',
                data: {
                    user_id: userId,
                    start_date: start_date,
                    end_date: end_date
                },
                success: function(response) {
                    $('#totalVisits').text(response.total_visits || 0);
                    $('#completedVisits').text(response.completed_visits || 0);
                    $('#missedVisits').text(response.missed_visits || 0);
                    initializeMapWithData(response.data);
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching visit history data:', xhr.status, xhr.responseText);
                }
            });
        }
    });

    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            if ($('#list-visit').hasClass('active')) {
                table.ajax.reload();
            }
        }
    );

    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        if ($('#list-visit').hasClass('active')) {
            table.ajax.reload();
        }
    });

    function initializeMapWithData(data) {
        if (!window.map) {
            window.map = new google.maps.Map(document.getElementById('map'), {
                center: { lat: 11.544873, lng: 104.892167 },
                zoom: 12
            });
            window.map.markers = [];
        }

        if (window.map.markers) {
            window.map.markers.forEach(marker => marker.setMap(null));
            window.map.markers = [];
        }

        const infoWindow = new google.maps.InfoWindow();

        window.map.addListener('click', function() {
            infoWindow.close();
        });

        const groupedContacts = {};
        data.forEach(visit => {
            if (!visit.contact_id) return;

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

            if (visit.latitude && visit.longitude) {
                groupedContacts[cid].latitude = visit.latitude;
                groupedContacts[cid].longitude = visit.longitude;
            }

            groupedContacts[cid].statuses.push(visit.visit_status);
        });

        Object.keys(groupedContacts).forEach(cid => {
            const contact = groupedContacts[cid];

            if (!contact.latitude || !contact.longitude) return;

            const total = contact.statuses.length;
            const completedCount = contact.statuses.filter(s => s === 'Completed').length;
            const missedCount = contact.statuses.filter(s => s === 'Missed').length;

            const iconUrl = '/public/img/Customer.svg';

            const marker = new google.maps.Marker({
                position: {
                    lat: parseFloat(contact.latitude),
                    lng: parseFloat(contact.longitude)
                },
                map: window.map,
                title: contact.contact_name || 'Unknown',
                icon: iconUrl
            });

            const infoHtml = `
            <div style="font-size: 20px; margin: 0; padding: 0; line-height: 1.4;">
                <strong>Name:</strong> ${contact.contact_name}<br>
                <strong>Number of Visits:</strong> ${total} 
                (<span style="color:green;">Completed: ${completedCount}</span>,
                <span style="color:red;">Missed: ${missedCount}</span>)
            </div>
            `;

            marker.addListener('click', function() {
                infoWindow.setContent(infoHtml);
                infoWindow.open(window.map, marker);
            });

            window.map.markers.push(marker);
        });

        if (window.map.markers.length > 0) {
            const bounds = new google.maps.LatLngBounds();
            window.map.markers.forEach(marker => bounds.extend(marker.getPosition()));
            window.map.fitBounds(bounds);
        }
    }

    function initializeEmptyMap() {
        console.log('Initializing empty map...');
        if (!document.getElementById('map')) {
            console.error('Map element #map not found in DOM');
            return;
        }

        if (!window.map || typeof window.map.setCenter !== 'function') {
            try {
                window.map = new google.maps.Map(document.getElementById('map'), {
                    center: { lat: 11.544873, lng: 104.892167 },
                    zoom: 12
                });
                window.map.markers = [];
            } catch (error) {
                console.error('Error initializing Google Map:', error);
                return;
            }
        } else {
            if (window.map.markers) {
                window.map.markers.forEach(marker => marker.setMap(null));
                window.map.markers = [];
            }
            window.map.setCenter({ lat: 11.544873, lng: 104.892167 });
            window.map.setZoom(12);
        }

        if ($('#map').is(':visible')) {
            console.log('Map is visible, triggering resize...');
            google.maps.event.trigger(window.map, 'resize');
            setTimeout(function() {
                google.maps.event.trigger(window.map, 'resize');
                window.map.setCenter({ lat: 11.544873, lng: 104.892167 });
            }, 400);
        } else {
            console.log('Map is hidden, forcing display and resize...');
            $('#map').css({
                'display': 'block',
                'visibility': 'visible',
                'position': 'relative',
                'z-index': '1'
            });
            google.maps.event.trigger(window.map, 'resize');
            setTimeout(function() {
                google.maps.event.trigger(window.map, 'resize');
                window.map.setCenter({ lat: 11.544873, lng: 104.892167 });
            }, 400);
        }
    }

    if ($('#visit-history').hasClass('active')) {
        setTimeout(function() {
            initializeEmptyMap();
            $('#totalVisits').text(0);
            $('#completedVisits').text(0);
            $('#missedVisits').text(0);
        }, 500);
    }
});
</script>
@endsection