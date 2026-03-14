@extends('layouts.app')

@section('title', __('Telegram Setting'))

@section('content')

<!-- Content Header -->
<section class="content-header no-print">
    <h1>Telegram Setting</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @component('components.widget', ['class' => 'box-primary', 'title' => __('All Telegram Bots')])
        @slot('tool')
            <div class="box-tools">
                <button class="btn btn-block btn-primary" id="btnAddSchedule">
                    <i class="fa fa-plus"></i> Add
                </button>
            </div>
        @endslot

        <div class="table-responsive">
            <table id="telegramScheduleTable" class="table table-bordered table-striped ajax_view" style="width:100%">
                <thead>
                    <tr>
                        <th>Chat / Channel ID</th>
                        <th>Send Time</th>
                        <th>Send On Days</th>
                        <th>Type of Report</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    @endcomponent
</section>

{{-- ═══════════════════════════ MODAL ═══════════════════════════ --}}
<div class="modal fade" id="telegramScheduleModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width:640px;">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;">

            <div class="modal-header" style="border:none;padding:20px 24px 16px;">
                <button type="button" class="close" data-dismiss="modal" style="font-size:20px;opacity:.5;">&times;</button>
                <h4 class="modal-title" style="font-weight:700;font-size:18px;">
                    🤖 <span id="modalTitleText">Bot Connection Setup</span>
                </h4>
            </div>

            <div class="modal-body" style="padding:0 24px 8px;">
                <input type="hidden" id="schedule_id">

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight:600;">Chat / Channel ID <span class="text-danger">*</span></label>
                            <input type="text" id="f_chat_id" class="form-control"
                                placeholder="e.g. -100123456789" style="border-radius:6px;">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label style="font-weight:600;">Send Time <span class="text-danger">*</span></label>
                            <input type="hidden" id="f_schedule_time">
                            <div style="display:flex; gap:6px; align-items:center;">
                                <select id="f_time_hour" class="form-control" style="border-radius:6px; flex:1; padding-left:6px;">
                                    <option value="">HH</option>
                                    @for($h = 1; $h <= 12; $h++)
                                        <option value="{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                                    @endfor
                                </select>
                                <span style="font-size:18px;font-weight:700;color:#555;">:</span>
                                <select id="f_time_min" class="form-control" style="border-radius:6px; flex:1; padding-left:6px;">
                                    <option value="">MM</option>
                                    @foreach(['00','05','10','15','20','25','30','35','40','45','50','55'] as $min)
                                        <option value="{{ $min }}">{{ $min }}</option>
                                    @endforeach
                                </select>
                                <select id="f_time_ampm" class="form-control" style="border-radius:6px; flex:1; padding-left:6px;">
                                    <option value="AM">AM</option>
                                    <option value="PM">PM</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <hr style="border-top:1px solid #e5e7eb;margin:14px 0;">

                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#6b7280;margin-bottom:10px;">
                    Send On Days <span class="text-danger">*</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px 24px;margin-top:6px;">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                    <label style="display:flex;align-items:center;gap:8px;font-size:15px;font-weight:500;cursor:pointer;">
                        <input type="checkbox" class="day-cb" value="{{ $d }}" style="width:18px;height:18px;cursor:pointer;accent-color:#1a73e8;"> {{ $d }}
                    </label>
                    @endforeach
                </div>

                <hr style="border-top:1px solid #e5e7eb;margin:18px 0 14px;">

                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#6b7280;margin-bottom:10px;">
                    Type of Report <span class="text-danger">*</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
                    <label style="display:flex;align-items:center;gap:10px;font-size:15px;font-weight:500;cursor:pointer;">
                        <input type="checkbox" class="report-type-cb" value="sales_visit" style="width:18px;height:18px;cursor:pointer;accent-color:#1a73e8;"> Sales Visit Report
                    </label>
                </div>

                <div style="height:10px;"></div>
            </div>

            <div class="modal-footer" style="border:none;padding:12px 24px 20px;background:white;">
                <button type="button" class="btn btn-default" data-dismiss="modal"
                    style="padding:8px 22px;border-radius:6px;font-weight:600;">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveSchedule"
                    style="padding:8px 24px;border-radius:6px;font-weight:700;">
                    <span id="saveBtnText">+ Create</span>
                </button>
            </div>

        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
$(function () {

    // ── DataTable ─────────────────────────────────────────────────
    var table = $('#telegramScheduleTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("telegram-setting.index") }}',
        columns: [
            { data: 'chat_id',                name: 'chat_id' },
            { data: 'schedule_time_formatted', name: 'schedule_time_formatted', orderable: false },
            { data: 'send_days_formatted',     name: 'send_days_formatted',     orderable: false },
            { data: 'report_types_formatted',  name: 'report_types_formatted',  orderable: false },
            { data: 'status',                  name: 'status',                  orderable: false, searchable: false },
            { data: 'action',                  name: 'action',                  orderable: false, searchable: false, width: '130px' },
        ],
    });

    // ── Reset modal ───────────────────────────────────────────────
    function resetModal() {
        $('#schedule_id').val('');
        $('#f_chat_id').val('');
        $('#f_schedule_time').val('');
        $('#f_time_hour').val('');
        $('#f_time_min').val('');
        $('#f_time_ampm').val('AM');
        $('.day-cb').prop('checked', false);
        $('.report-type-cb').prop('checked', false);
        $('#modalTitleText').text('Bot Connection Setup');
        $('#saveBtnText').text('+ Create');
    }

    // ── Open ADD modal ────────────────────────────────────────────
    $('#btnAddSchedule').on('click', function () {
        resetModal();
        $('#telegramScheduleModal').modal('show');
    });

    // ── Open EDIT modal ───────────────────────────────────────────
    $(document).on('click', '.btn-edit', function () {
        var id = $(this).data('id');
        resetModal();

        $.get('{{ url("telegram-setting") }}/' + id, function (res) {
            if (!res.success) { toastr.error('Failed to load record.'); return; }
            var d = res.data;

            $('#schedule_id').val(d.id);
            $('#f_chat_id').val(d.chat_id);

            if (d.schedule_time) {
                // Convert HH:MM (24h) -> hour/min/ampm dropdowns
                var parts = d.schedule_time.substring(0, 5).split(':');
                var h24 = parseInt(parts[0], 10);
                var min = parts[1];
                var ampm = h24 >= 12 ? 'PM' : 'AM';
                var h12 = h24 % 12 || 12;
                $('#f_time_hour').val(String(h12).padStart(2, '0'));
                $('#f_time_min').val(min);
                $('#f_time_ampm').val(ampm);
            }
            if (d.send_days_array && d.send_days_array.length) {
                d.send_days_array.forEach(function (day) {
                    $('.day-cb[value="' + day + '"]').prop('checked', true);
                });
            }
            if (d.report_types_array && d.report_types_array.length) {
                d.report_types_array.forEach(function (type) {
                    $('.report-type-cb[value="' + type + '"]').prop('checked', true);
                });
            }

            $('#modalTitleText').text('Edit Bot Schedule');
            $('#saveBtnText').text('Save Changes');
            $('#telegramScheduleModal').modal('show');
        });
    });

    // ── Save (Create / Update) ────────────────────────────────────
    $('#btnSaveSchedule').on('click', function () {
        var id           = $('#schedule_id').val();
        var chat_id      = $('#f_chat_id').val().trim();
        var report_types = [];
        var send_days    = [];

        // Build 24h time from dropdowns
        var tHour = $('#f_time_hour').val();
        var tMin  = $('#f_time_min').val();
        var tAmpm = $('#f_time_ampm').val();
        var time_val = '';
        if (tHour && tMin) {
            var h24 = parseInt(tHour, 10);
            if (tAmpm === 'PM' && h24 !== 12) h24 += 12;
            if (tAmpm === 'AM' && h24 === 12) h24 = 0;
            time_val = String(h24).padStart(2, '0') + ':' + tMin;
        }

        $('.report-type-cb:checked').each(function () { report_types.push($(this).val()); });
        $('.day-cb:checked').each(function ()          { send_days.push($(this).val()); });

        if (!chat_id)             { toastr.error('Chat / Channel ID is required.'); return; }
        if (!time_val)            { toastr.error('Send Time is required.'); return; }
        if (!send_days.length)    { toastr.error('Please select at least one day.'); return; }
        if (!report_types.length) { toastr.error('Please select at least one report type.'); return; }

        var url    = id ? '{{ url("telegram-setting") }}/' + id : '{{ route("telegram-setting.store") }}';
        var method = id ? 'PUT' : 'POST';

        var formData = { _token: '{{ csrf_token() }}', chat_id: chat_id, schedule_time: time_val };
        $.each(send_days,    function (i, v) { formData['send_days['    + i + ']'] = v; });
        $.each(report_types, function (i, v) { formData['report_types[' + i + ']'] = v; });

        $.ajax({
            url: url, method: method, data: formData,
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#telegramScheduleModal').modal('hide');
                    table.ajax.reload();
                } else {
                    toastr.error('Something went wrong.');
                }
            },
            error: function (xhr) {
                var errors = xhr.responseJSON && xhr.responseJSON.errors;
                if (errors) { $.each(errors, function (k, v) { toastr.error(v[0]); }); }
                else        { toastr.error('Server error. Please try again.'); }
            }
        });
    });

    // ── Delete ────────────────────────────────────────────────────
    $(document).on('click', '.btn-delete', function () {
        if (!confirm('Are you sure you want to delete this schedule?')) return;
        var id = $(this).data('id');
        $.ajax({
            url: '{{ url("telegram-setting") }}/' + id,
            method: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) { toastr.success(res.message); table.ajax.reload(); }
                else             { toastr.error('Failed to delete.'); }
            }
        });
    });

    // ── Toggle Status ─────────────────────────────────────────────
    $(document).on('change', '.toggle-status', function () {
        var id  = $(this).data('id');
        var $cb = $(this);
        $.post('{{ url("telegram-setting") }}/' + id + '/toggle-status',
            { _token: '{{ csrf_token() }}' },
            function (res) {
                if (res.success) { toastr.success('Status updated.'); }
                else             { toastr.error('Failed.'); $cb.prop('checked', !$cb.prop('checked')); }
            }
        );
    });

});
</script>
@endsection