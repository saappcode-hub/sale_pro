{{-- resources/views/manage_user/_zone_assignment.blade.php --}}
{{-- NOTE: This partial has NO <script> tag. --}}
{{-- The JS must be placed inside @section('javascript') in the parent blade. --}}

<div class="row">
  <div class="col-md-12">
    @component('components.widget', ['title' => 'Customer Access Locations (Zones)'])

        <div class="row">
            <div class="col-md-12">
                <p class="text-muted">
                    Users will only see customers located in the Provinces, Districts, or Communes assigned below.
                </p>
            </div>
        </div>

        <div class="row" id="zone_selector_row">

            <div class="col-md-3">
                <div class="form-group">
                    <label>Province:</label>
                    <select id="zone_province_id" class="form-control" style="width:100%;">
                        <option value="" selected disabled>Select Province</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name_en }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    <label>District:</label>
                    <select id="zone_district_id" class="form-control" style="width:100%;" disabled>
                        <option value="" selected disabled>Select District</option>
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    <label>Commune:</label>
                    <select id="zone_commune_id" class="form-control" style="width:100%;" disabled>
                        <option value="" selected disabled>Select Commune</option>
                    </select>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group">
                    <label>&nbsp;</label><br>
                    <button type="button" id="zone_add_btn" class="btn btn-info">
                        <i class="fa fa-plus"></i> Add Location
                    </button>
                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-md-12">
                <strong>Assigned Locations:</strong>
                <table class="table table-bordered table-striped table-condensed" id="zone_assigned_table" style="margin-top:8px;">
                    <thead>
                        <tr>
                            <th>Province</th>
                            <th>District</th>
                            <th>Commune</th>
                            <th style="width:90px; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="zone_assigned_tbody">
                        @forelse($user_zones as $zone)
                            <tr>
                                <td>{{ $zone->province->name_en ?? '-' }}<input type="hidden" name="zone_province_ids[]" value="{{ $zone->province_id }}"></td>
                                <td>{{ $zone->district->name_en ?? 'All Districts' }}<input type="hidden" name="zone_district_ids[]" value="{{ $zone->district_id ?? '' }}"></td>
                                <td>{{ $zone->commune->name_en ?? 'All Communes' }}<input type="hidden" name="zone_commune_ids[]" value="{{ $zone->commune_id ?? '' }}"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-xs zone_remove_btn">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    </tbody>
                </table>
                <p id="zone_empty_msg" class="text-muted" @if($user_zones->count() > 0) style="display:none;" @endif>
                    No locations assigned yet. Use the selectors above to add.
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="zone_access_all" name="zone_access_all" value="1" {{ !empty($user) && $user->zone_access_all == 1 ? 'checked' : '' }}>
                        &nbsp; Allow access to ALL Locations (No restrictions)
                    </label>
                </div>
            </div>
        </div>

    @endcomponent
  </div>
</div>