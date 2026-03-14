<style>
  .photo-container {
    overflow-x: auto;
    white-space: nowrap;
  }

  .photo-container img {
    display: inline-block;
    vertical-align: top;
  }

  #map-show {
    height: 300px; /* Match photo height */
    width: 100%;
  }
</style>

<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">×</span>
      </button>
      <h4 class="modal-title" id="modalTitle">@lang('Visit Details') (<b>Visit No:</b> {{ $visit->visit_no }})</h4>
    </div>

    <div class="modal-body">
      <!-- Visit Details -->
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ date('d-m-Y H:i:s', strtotime($visit->transaction_date)) }}</p>
        </div>
      </div>

      <div class="row">
        <div class="col-xs-4">
          <b>Visit ID:</b> #{{ $visit->visit_no }}
        </div>
        <div class="col-xs-4">
          <b>Customer Name:</b> {{ $visit->contact->name }}
        </div>
        <div class="col-xs-4">
          <b>User:</b> {{ $visit->sales_person->username }}
        </div>
      </div>
      <div style="height: 5px;"></div>
      <div class="row">
        <div class="col-xs-4">
          <b>Checkin Distance:</b> {{ $visit->checkin_distance }}
        </div>
        <div class="col-xs-4">
          <b>Address:</b> {{ $visit->contact->contactMap->address ?? 'N/A' }}
        </div>
        <div class="col-xs-4">
          <b>Status:</b> {{ $visit->visit_status }}
        </div>
      </div>
      <div style="height: 5px;"></div>
      <div class="row">
        <div class="col-xs-4"></div>
        <div class="col-xs-4"></div>
        <div class="col-xs-4">
          <b>Noted:</b> {{ $visit->transaction_note }}
        </div>
      </div>

      <!-- Own Products Table -->
      <h5 style="font-size: 17px;">Own Products:</h5>
      <table class="table table-striped">
        <thead>
          <tr style="background-color: #2DCE89; color: white;">
            <th style="padding-right: 5px;">#</th>
            <th>Product</th>
            <th>Quantity</th>
          </tr>
        </thead>
        <tbody>
          @php $own_total = 0; @endphp
          @foreach($own_products as $index => $product)
            <tr style="background-color: #D2D6DE; color: black;">
              <td style="padding-right: 5px;">{{ $index + 1 }}</td>
              <td>{{ $product['name'] }}</td>
              <td>{{ $product['quantity'] }}</td>
            </tr>
          @endforeach
          @php
            preg_match('/\((\d+(\.\d+)?)%\)/', $visit->own_product, $matches);
            $own_percentage = $matches[1] ?? 0;
            $own_color = $own_percentage >= 50 ? '#49C856' : 'red';
          @endphp
          <tr style="background-color: #D2D6DE;">
            <td colspan="2" style="text-align: right; color: black;"><strong>Sub total:</strong></td>
            <td><strong style="color: {{$own_color}};"><b>{{$visit->own_product}}</b></strong></td>
          </tr>
        </tbody>
      </table>

      <!-- Other Products Table -->
      <h5 style="font-size: 17px;">Other Products:</h5>
      <table class="table table-striped">
        <thead>
          <tr style="background-color: #2DCE89; color: white;">
            <th style="padding-right: 5px;">#</th>
            <th>Product</th>
            <th>Quantity</th>
          </tr>
        </thead>
        <tbody>
          @php $other_total = 0; @endphp
          @foreach($other_products as $index => $product)
            <tr style="background-color: #D2D6DE; color: black;">
              <td style="padding-right: 5px;">{{ $index + 1 }}</td>
              <td>{{ $product['name'] }}</td>
              <td>{{ $product['quantity'] }}</td>
            </tr>
          @endforeach
          @php
            preg_match('/\((\d+(\.\d+)?)%\)/', $visit->other_product, $matches);
            $other_percentage = $matches[1] ?? 0;
            $other_color = $other_percentage >= 50 ? '#49C856' : 'red';
          @endphp
          <tr style="background-color: #D2D6DE;">
            <td colspan="2" style="text-align: right; color: black;"><strong>Sub total:</strong></td>
            <td><strong style="color: {{$other_color}};"><b>{{$visit->other_product}}</b></strong></td>
          </tr>
        </tbody>
      </table>

      <div class="row">
        <div class="col-xs-6">
          <h5>Photos:</h5>
          <div class="photo-container">
            @foreach ($images as $image)
              <img src="{{ asset($image) }}" style="height: 300px; margin-right: 10px;">
            @endforeach
          </div>
        </div>
        <!-- Map Section with 50% width -->
        <div class="col-xs-6">
          <h5>Map:</h5>
          <div id="map-show"></div> <!-- Map container -->
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-default no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>

<!-- JavaScript Section (No duplicate Google Maps API script here) -->
<script type="text/javascript">
  // Minimal function to initialize the map object only
  function initMapData() {
    var saleLatLong = "{{ $visit->sale_latlong ?? null }}".split(',');
    var saleCoords = {
      lat: parseFloat(saleLatLong[0]),
      lng: parseFloat(saleLatLong[1])
    };
    return new google.maps.Map(document.getElementById('map-show'), {
      zoom: 17,
      center: saleCoords,
      disableDefaultUI: true,
      gestureHandling: 'none',
      zoomControl: false
    });
  }

  // Function to set up markers and additional map features
  function setupMap() {
    var map = initMapData(); // Get the map object

    // Get and split lat/long directly from controller
    var saleLatLong = "{{ $visit->sale_latlong ?? null }}".split(',');
    var contactLatLong = "{{ $visit->contact->contactMap->points ?? null }}".split(',');
    
    // Define coordinates
    var saleCoords = {
      lat: parseFloat(saleLatLong[0]),
      lng: parseFloat(saleLatLong[1])
    };
    var contactCoords = null;
    if (contactLatLong && contactLatLong[0] !== '') {
      contactCoords = {
        lat: parseFloat(contactLatLong[0]),
        lng: parseFloat(contactLatLong[1])
      };
    }

    // Custom icon for contact marker
    var contactIcon = {
      url: '/public/images/Icon.svg',
      scaledSize: new google.maps.Size(45, 45),
      origin: new google.maps.Point(0, 0),
      anchor: new google.maps.Point(15, 15)
    };

    // InfoWindow content
    var saleInfoWindowContent = `
      <div style="font-size: 14px; padding: 5px; margin: 0;">
        <b>Sale Rep: {{ $visit->sales_person->username }}</b>
      </div>
    `;
    var contactInfoWindowContent = `
      <div style="font-size: 14px; padding: 5px; margin: 0;">
        <b>Outlet: {{ $visit->contact->name }}</b>
      </div>
    `;

    // Add sale marker
    var saleMarker = new google.maps.Marker({
      position: saleCoords,
      map: map,
      title: "Sale Rep: {{ $visit->sales_person->username }}"
    });
    var saleInfoWindow = new google.maps.InfoWindow({
      content: saleInfoWindowContent
    });
    saleInfoWindow.open(map, saleMarker);

    // Add contact marker if coordinates exist
    if (contactCoords) {
      var contactMarker = new google.maps.Marker({
        position: contactCoords,
        map: map,
        title: "Outlet: {{ $visit->contact->name }}",
        icon: contactIcon
      });
      var contactInfoWindow = new google.maps.InfoWindow({
        content: contactInfoWindowContent
      });
      contactInfoWindow.open(map, contactMarker);
    }

    // Remove close buttons from InfoWindows
    google.maps.event.addListener(map, 'idle', function() {
      var closeButtons = document.querySelectorAll('.gm-ui-hover-effect');
      closeButtons.forEach(button => {
        button.style.display = 'none';
      });
    });

    // Adjust bounds if both markers exist and differ
    if (contactCoords && (saleCoords.lat !== contactCoords.lat || saleCoords.lng !== contactCoords.lng)) {
      var bounds = new google.maps.LatLngBounds();
      bounds.extend(saleCoords);
      bounds.extend(contactCoords);
      map.fitBounds(bounds);
      google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
        if (map.getZoom() > 17) {
          map.setZoom(17);
        }
      });
    }

    // Trigger resize to ensure proper rendering
    google.maps.event.trigger(map, 'resize');
  }

  // Initialize map when modal is shown
  $(document).ready(function() {
    $('#modalTitle').closest('.modal').on('shown.bs.modal', function() {
      if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
        setupMap(); // Call the setup function instead of initMapData directly
      } else {
        console.error('Google Maps API not loaded yet. Retrying in 500ms...');
        setTimeout(function() {
          if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
            setupMap();
          } else {
            console.error('Google Maps API still not loaded.');
          }
        }, 500);
      }
    });
  });
</script>