<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Contact</title>
    <style>
        button {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 35px;
            color: black;
            text-shadow: 0 0 5px rgba(0,0,0,0.5);
            outline: none;
        }
        .map-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
<div class="row">
    <div class="col-sm-3">
        @include('contact.contact_basic_info')
    </div>
    <div class="col-sm-3">
        @include('contact.contact_more_info')
    </div>
    @if($contact->type != 'customer')
        <div class="col-sm-3">
            @include('contact.contact_tax_info')
        </div>
    @endif
    <div class="col-sm-6">
        @php
            // Get default shipping address for this contact
            $default_shipping_address = \App\ShippingAddress::where('contact_id', $contact->id)
                ->where('is_default', 1)
                ->first();
            
            $coordinates = null;
            
            if ($default_shipping_address) {
                // Prioritize using the latlong field if it's available
                if (!empty($default_shipping_address->latlong)) {
                    $coordinates = $default_shipping_address->latlong;
                } 
                // If latlong is not available, try to extract coordinates from the map URL
                elseif (!empty($default_shipping_address->map)) {
                    $map_url = $default_shipping_address->map;
                    if (preg_match('/3d([-0-9.]+)!4d([-0-9.]+)/', $map_url, $matches)) {
                        $coordinates = $matches[1] . ',' . $matches[2];
                    } elseif (preg_match('/@([-0-9.]+),([-0-9.]+)/', $map_url, $matches)) {
                        $coordinates = $matches[1] . ',' . $matches[2];
                    } elseif (preg_match('/maps\/search\/([0-9.+-]+),([0-9.+-]+)/', $map_url, $matches)) {
                        $coordinates = $matches[1] . ',' . $matches[2];
                    }
                }
            }
        @endphp
        
        @if($coordinates)
            <div class="map-container" id="shipping_map_container">
                <iframe
                    id="gmap_canvas"
                    src="https://www.google.com/maps?q={{ urlencode($coordinates) }}&hl=en;z=14&output=embed"
                    width="100%"
                    height="100%"
                    frameborder="0"
                    style="border:0"
                    allowfullscreen>
                </iframe>
                <button onclick="toggleFullscreen(this)" style="position: absolute; top: 10px; right: 10px; z-index: 10;">⛶</button>
            </div>
        @else
            <p>No map data available.</p>
        @endif
    </div>

    @if( $contact->type == 'supplier' || $contact->type == 'both')
    <div class="clearfix"></div>
        <div class="col-sm-12">
            @if(($contact->total_purchase - $contact->purchase_paid) > 0)
                <a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$contact->id])}}?type=purchase" class="pay_purchase_due btn btn-primary btn-sm pull-right"><i class="fas fa-money-bill-alt" aria-hidden="true"></i> @lang("contact.pay_due_amount")</a>
            @endif
        </div>
    @endif
    <div class="col-sm-12" style="margin-top: 20px;">
        <button type="button" class="btn btn-primary btn-sm pull-right" data-toggle="modal" data-target="#add_discount_modal">@lang('lang_v1.add_discount')</button>
    </div>
</div>

<script>
function toggleFullscreen(elem) {
    var mapContainer = elem.closest('.map-container');
    if (!document.fullscreenElement) {
        if (mapContainer.requestFullscreen) {
            mapContainer.requestFullscreen();
        } else if (mapContainer.mozRequestFullScreen) { /* Firefox */
            mapContainer.mozRequestFullScreen();
        } else if (mapContainer.webkitRequestFullscreen) { /* Chrome, Safari & Opera */
            mapContainer.webkitRequestFullscreen();
        } else if (mapContainer.msRequestFullscreen) { /* IE/Edge */
            mapContainer.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.mozCancelFullScreen) { /* Firefox */
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) { /* Chrome, Safari and Opera */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE/Edge */
            document.msExitFullscreen();
        }
    }
}
</script>
</body>
</html>