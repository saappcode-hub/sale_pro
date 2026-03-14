@extends('layouts.app')

@section('title', __( 'user.add_user' ))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
  <h1>@lang( 'user.add_user' )</h1>
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\ManageUserController::class, 'store']), 'method' => 'post', 'id' => 'user_add_form' ]) !!}
  <div class="row">
    <div class="col-md-12">
  @component('components.widget')
      <div class="col-md-2">
        <div class="form-group">
          {!! Form::label('surname', __( 'business.prefix' ) . ':') !!}
            {!! Form::text('surname', null, ['class' => 'form-control', 'placeholder' => __( 'business.prefix_placeholder' ) ]); !!}
        </div>
      </div>
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('first_name', __( 'business.first_name' ) . ':*') !!}
            {!! Form::text('first_name', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'business.first_name' ) ]); !!}
        </div>
      </div>
      <div class="col-md-5">
        <div class="form-group">
          {!! Form::label('last_name', __( 'business.last_name' ) . ':') !!}
            {!! Form::text('last_name', null, ['class' => 'form-control', 'placeholder' => __( 'business.last_name' ) ]); !!}
        </div>
      </div>
      <div class="clearfix"></div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('email', __( 'business.email' ) . ':*') !!}
            {!! Form::text('email', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'business.email' ) ]); !!}
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-group">
          <div class="checkbox">
            <br/>
            <label>
                 {!! Form::checkbox('is_active', 'active', true, ['class' => 'input-icheck status']); !!} {{ __('lang_v1.status_for_user') }}
            </label>
            @show_tooltip(__('lang_v1.tooltip_enable_user_active'))
          </div>
        </div>
      </div>
  @endcomponent
  </div>
  <div class="col-md-12">
    @component('components.widget', ['title' => __('lang_v1.roles_and_permissions')])
      <div class="col-md-4">
        <div class="form-group">
            <div class="checkbox">
              <label>
                {!! Form::checkbox('allow_login', 1, true, 
                [ 'class' => 'input-icheck', 'id' => 'allow_login']); !!} {{ __( 'lang_v1.allow_login' ) }}
              </label>
            </div>
        </div>
      </div>
      <div class="clearfix"></div>
      <div class="user_auth_fields">
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('username', __( 'business.username' ) . ':') !!}
          @if(!empty($username_ext))
            <div class="input-group">
              {!! Form::text('username', null, ['class' => 'form-control', 'placeholder' => __( 'business.username' ) ]); !!}
              <span class="input-group-addon">{{$username_ext}}</span>
            </div>
            <p class="help-block" id="show_username"></p>
          @else
              {!! Form::text('username', null, ['class' => 'form-control', 'placeholder' => __( 'business.username' ) ]); !!}
          @endif
          <p class="help-block">@lang('lang_v1.username_help')</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('password', __( 'business.password' ) . ':*') !!}
            {!! Form::password('password', ['class' => 'form-control', 'required', 'placeholder' => __( 'business.password' ) ]); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('confirm_password', __( 'business.confirm_password' ) . ':*') !!}
            {!! Form::password('confirm_password', ['class' => 'form-control', 'required', 'placeholder' => __( 'business.confirm_password' ) ]); !!}
        </div>
      </div>
    </div>
      <div class="clearfix"></div>
      <div class="col-md-6">
        <div class="form-group">
          {!! Form::label('role', __( 'user.role' ) . ':*') !!} @show_tooltip(__('lang_v1.admin_role_location_permission_help'))
            {!! Form::select('role', $roles, null, ['class' => 'form-control select2']); !!}
        </div>
      </div>
      <div class="clearfix"></div>
      <div class="col-md-3">
          <h4>@lang( 'role.access_locations' ) @show_tooltip(__('tooltip.access_locations_permission'))</h4>
        </div>
        <div class="col-md-9">
          <div class="col-md-12">
            <div class="checkbox">
                <label>
                  {!! Form::checkbox('access_all_locations', 'access_all_locations', true, 
                ['class' => 'input-icheck']); !!} {{ __( 'role.all_locations' ) }} 
                </label>
                @show_tooltip(__('tooltip.all_location_permission'))
            </div>
          </div>
          @foreach($locations as $location)
          <div class="col-md-12">
            <div class="checkbox">
              <label>
                {!! Form::checkbox('location_permissions[]', 'location.' . $location->id, false, 
                [ 'class' => 'input-icheck']); !!} {{ $location->name }} @if(!empty($location->location_id))({{ $location->location_id}}) @endif
              </label>
            </div>
          </div>
          @endforeach
        </div>
    @endcomponent
  </div>

  <div class="col-md-12">
    @component('components.widget', ['title' => __('sale.sells')])
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('cmmsn_percent', __( 'lang_v1.cmmsn_percent' ) . ':') !!} @show_tooltip(__('lang_v1.commsn_percent_help'))
            {!! Form::text('cmmsn_percent', null, ['class' => 'form-control input_number', 'placeholder' => __( 'lang_v1.cmmsn_percent' ) ]); !!}
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-group">
          {!! Form::label('max_sales_discount_percent', __( 'lang_v1.max_sales_discount_percent' ) . ':') !!} @show_tooltip(__('lang_v1.max_sales_discount_percent_help'))
            {!! Form::text('max_sales_discount_percent', null, ['class' => 'form-control input_number', 'placeholder' => __( 'lang_v1.max_sales_discount_percent' ) ]); !!}
        </div>
      </div>
      <div class="clearfix"></div>
      
      <div class="col-md-4">
        <div class="form-group">
            <div class="checkbox">
            <br/>
              <label>
                {!! Form::checkbox('selected_contacts', 1, false, 
                [ 'class' => 'input-icheck', 'id' => 'selected_contacts']); !!} {{ __( 'lang_v1.allow_selected_contacts' ) }}
              </label>
              @show_tooltip(__('lang_v1.allow_selected_contacts_tooltip'))
            </div>
        </div>
      </div>
      <div class="col-sm-4 hide selected_contacts_div">
          <div class="form-group">
              {!! Form::label('user_allowed_contacts', __('lang_v1.selected_contacts') . ':') !!}
              <div class="form-group">
                  {!! Form::select('selected_contact_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'style' => 'width: 100%;', 'id' => 'user_allowed_contacts' ]); !!}
              </div>
          </div>
      </div>
    @endcomponent
  </div>

  </div>

  {{-- Zone Assignment --}}
  @include('manage_user._zone_assignment', [
      'provinces'  => $provinces,
      'user_zones' => $user_zones,
  ])

    @include('user.edit_profile_form_part')

    @if(!empty($form_partials))
      @foreach($form_partials as $partial)
        {!! $partial !!}
      @endforeach
    @endif
  <div class="row">
    <div class="col-md-12 text-center">
      <button type="submit" class="btn btn-primary btn-big" id="submit_user_button">@lang( 'messages.save' )</button>
    </div>
  </div>
{!! Form::close() !!}
  @stop
@section('javascript')
<script type="text/javascript">
  __page_leave_confirmation('#user_add_form');
  $(document).ready(function(){
    $('#selected_contacts').on('ifChecked', function(event){
      $('div.selected_contacts_div').removeClass('hide');
    });
    $('#selected_contacts').on('ifUnchecked', function(event){
      $('div.selected_contacts_div').addClass('hide');
    });

    $('#allow_login').on('ifChecked', function(event){
      $('div.user_auth_fields').removeClass('hide');
    });
    $('#allow_login').on('ifUnchecked', function(event){
      $('div.user_auth_fields').addClass('hide');
    });

    $('#user_allowed_contacts').select2({
        ajax: {
            url: '/contacts/customers',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term, page: params.page, all_contact: true };
            },
            processResults: function(data) { return { results: data }; },
        },
        templateResult: function (data) { 
            var template = '';
            if (data.supplier_business_name) { template += data.supplier_business_name + "<br>"; }
            template += data.text + "<br>" + LANG.mobile + ": " + data.mobile;
            return template;
        },
        minimumInputLength: 1,
        escapeMarkup: function(markup) { return markup; },
    });

    // ══════════════════════════════════════════════════
    // Zone Assignment JS
    // ══════════════════════════════════════════════════
    function zoneResetDropdown(selector, placeholder) {
        $(selector).empty().append('<option value="" selected disabled>' + placeholder + '</option>').attr('disabled', true);
    }

    $('#zone_province_id').change(function() {
        var provinceId = $(this).val();
        zoneResetDropdown('#zone_district_id', 'Select District');
        zoneResetDropdown('#zone_commune_id', 'Select Commune');
        if (!provinceId) return;
        $('#zone_district_id').append('<option>Loading...</option>').attr('disabled', true);
        $.ajax({
            url: '/get-districts/' + provinceId,
            type: 'GET', dataType: 'json',
            success: function(data) {
                $('#zone_district_id').empty().append('<option value="" selected disabled>Select District</option>');
                $.each(data, function(key, value) {
                    $('#zone_district_id').append('<option value="' + key + '">' + value + '</option>');
                });
                $('#zone_district_id').removeAttr('disabled');
            },
            error: function() { alert('Error fetching districts.'); zoneResetDropdown('#zone_district_id', 'Select District'); }
        });
    });

    $('#zone_district_id').change(function() {
        var districtId = $(this).val();
        zoneResetDropdown('#zone_commune_id', 'Select Commune');
        if (!districtId) return;
        $('#zone_commune_id').append('<option>Loading...</option>').attr('disabled', true);
        $.ajax({
            url: '/get-communes/' + districtId,
            type: 'GET', dataType: 'json',
            success: function(data) {
                $('#zone_commune_id').empty().append('<option value="" selected disabled>Select Commune</option>');
                $.each(data, function(key, value) {
                    $('#zone_commune_id').append('<option value="' + key + '">' + value + '</option>');
                });
                $('#zone_commune_id').removeAttr('disabled');
            },
            error: function() { alert('Error fetching communes.'); zoneResetDropdown('#zone_commune_id', 'Select Commune'); }
        });
    });

    $('#zone_add_btn').on('click', function() {
        var provinceId   = $('#zone_province_id').val();
        var provinceName = $('#zone_province_id option:selected').text().trim();
        var districtId   = $('#zone_district_id').val();
        var districtName = districtId ? $('#zone_district_id option:selected').text().trim() : 'All Districts';
        var communeId    = $('#zone_commune_id').val();
        var communeName  = communeId  ? $('#zone_commune_id option:selected').text().trim()  : 'All Communes';
        if (!provinceId) { toastr.warning('Please select at least a Province.'); return; }
        var isDuplicate = false;
        $('#zone_assigned_tbody tr').each(function() {
            if ($(this).find('[name="zone_province_ids[]"]').val() == provinceId &&
                $(this).find('[name="zone_district_ids[]"]').val() == districtId &&
                $(this).find('[name="zone_commune_ids[]"]').val()  == communeId) {
                isDuplicate = true; return false;
            }
        });
        if (isDuplicate) { toastr.warning('This location is already assigned.'); return; }
        $('#zone_assigned_tbody').append(
            '<tr>' +
            '<td>' + provinceName + '<input type="hidden" name="zone_province_ids[]" value="' + provinceId + '"></td>' +
            '<td>' + districtName + '<input type="hidden" name="zone_district_ids[]" value="' + (districtId||'') + '"></td>' +
            '<td>' + communeName  + '<input type="hidden" name="zone_commune_ids[]"  value="' + (communeId||'')  + '"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-danger btn-xs zone_remove_btn"><i class="fa fa-trash"></i> Remove</button></td>' +
            '</tr>'
        );
        $('#zone_empty_msg').hide();
        $('#zone_province_id').val('');
        zoneResetDropdown('#zone_district_id', 'Select District');
        zoneResetDropdown('#zone_commune_id', 'Select Commune');
    });

    $(document).on('click', '.zone_remove_btn', function() {
        $(this).closest('tr').remove();
        if ($('#zone_assigned_tbody tr').length === 0) $('#zone_empty_msg').show();
    });

    // zone_access_all is a flag only — no UI hide/show effect
    // ══════════════════════════════════════════════════

  });

  $('form#user_add_form').validate({
                rules: {
                    first_name: { required: true },
                    email: {
                        email: true,
                        remote: {
                            url: "/business/register/check-email", type: "post",
                            data: { email: function() { return $( "#email" ).val(); } }
                        }
                    },
                    password: { required: true, minlength: 5 },
                    confirm_password: { equalTo: "#password" },
                    username: {
                        minlength: 5,
                        remote: {
                            url: "/business/register/check-username", type: "post",
                            data: {
                                username: function() { return $( "#username" ).val(); },
                                @if(!empty($username_ext)) username_ext: "{{$username_ext}}" @endif
                            }
                        }
                    }
                },
                messages: {
                    password: { minlength: 'Password should be minimum 5 characters' },
                    confirm_password: { equalTo: 'Should be same as password' },
                    username: { remote: 'Invalid username or User already exist' },
                    email: { remote: '{{ __("validation.unique", ["attribute" => __("business.email")]) }}' }
                }
            });
  $('#username').change( function(){
    if($('#show_username').length > 0){
      if($(this).val().trim() != ''){
        $('#show_username').html("{{__('lang_v1.your_username_will_be')}}: <b>" + $(this).val() + "{{$username_ext}}</b>");
      } else {
        $('#show_username').html('');
      }
    }
  });
</script>
@endsection