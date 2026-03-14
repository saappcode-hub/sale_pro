@extends('layouts.app')
@section('title', 'Add new invoice QR Code')

@section('content')
<style type="text/css">
    .form-control.file-input {
        border: none;
        padding: 0;
    }
    .image-preview-wrapper {
        position: relative;
        display: inline-block; /* Allows the icon to be positioned over the image */
        max-width: 30%;
    }
    .image-preview {
        width: 100%;
        height: auto;
    }
</style>
@php
  $custom_labels = json_decode(session('business.custom_labels'), true);
@endphp
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Add new invoice QR Code</h1>
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\InvoiceQrsController::class, 'store']), 'method' => 'post', 'id' => 'add_invoice_layout_form', 'files' => true]) !!}
  <div class="box box-solid">
    <div class="box-body">
      <div class="row">
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('name', __('invoice.layout_name') . ':*') !!}
              {!! Form::text('name', null, ['class' => 'form-control', 'required',
              'placeholder' => __('invoice.layout_name')]); !!}
          </div>
        </div>
        <div class="col-sm-6">
          <!-- Image Upload -->
          <div class="form-group">
            {!! Form::label('image', __('Upload Invoice Image') . ':') !!}
            {!! Form::file('image', ['class' => 'form-control file-input']); !!}
            <div class="image-preview-wrapper">
              <img id="image_preview" src="#" alt="Image Preview" class="image-preview"/>
            </div>
            <span class="help-block">@lang('lang_v1.invoice_image_upload_help')</span>
          </div>
          <!-- /Image Upload -->
        </div>
   
  </div>
  <div class="row">
    <div class="col-sm-12 text-center">
      <button type="submit" class="btn btn-primary btn-big">@lang('messages.save')</button>
    </div>
  </div>
  <div style="height: 10px;">
  </div>
  {!! Form::close() !!}
</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">
  __page_leave_confirmation('#add_invoice_layout_form');
    $(document).on('ifChanged', '#show_letter_head', function() {
        letter_head_changed();
    });

    function letter_head_changed() {
        if($('#show_letter_head').is(":checked")) {
            $('.hide-for-letterhead').addClass('hide');
            $('.letter_head_input').removeClass('hide');
        } else {
            $('.hide-for-letterhead').removeClass('hide');
            $('.letter_head_input').addClass('hide');
        }
    }
    $(document).ready(function() {
    $('input[type="file"]').change(function(e){
        var fileInput = this;
        if (fileInput.files && fileInput.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#image_preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(fileInput.files[0]);
        }
    });
  });

  function resetImage() {
      $('input[type="file"]').val(''); // Clears the file input
      $('#image_preview').attr('src', '').hide(); // Hides and clears the image preview
  }
</script>
@endsection