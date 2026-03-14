$(document).ready(function() {
    $(document).on('change', '.purchase_quantity', function() {
        update_table_total($(this).closest('table'));
    });
    $(document).on('change', '.unit_price', function() {
        update_table_total($(this).closest('table'));
    });

    $('.os_exp_date').datepicker({
        autoclose: true,
        format: datepicker_date_format,
    });

    $(document).on('click', '.add_stock_row', function() {
        var tr = $(this).data('row-html');
        var key = parseInt($(this).data('sub-key'));
        tr = tr.replace(/\__subkey__/g, key);
        $(this).data('sub-key', key + 1);

        var $newRow = $(tr).insertAfter($(this).closest('tr'));
        $newRow.find('.os_exp_date').datepicker({
            autoclose: true,
            format: datepicker_date_format,
        });
        $newRow.find('.os_date').datetimepicker({
            format: moment_date_format + ' ' + moment_time_format,
            ignoreReadonly: true,
            widgetPositioning: {
                horizontal: 'right',
                vertical: 'bottom'
            }
        });
        update_table_total($(this).closest('table')); // Update totals after adding new row
    });

    $(document).on('click', '.add-opening-stock', function(e) {
        e.preventDefault();
        $.ajax({
            url: $(this).data('href'),
            dataType: 'html',
            success: function(result) {
                $('#opening_stock_modal')
                    .html(result)
                    .modal('show');
            },
        });
    });
});

// Re-initialize date pickers on modal opening
$('#opening_stock_modal').on('shown.bs.modal', function(e) {
    $('#opening_stock_modal .os_exp_date').datepicker({
        autoclose: true,
        format: datepicker_date_format,
    });
    $('#opening_stock_modal .os_date').datetimepicker({
        format: moment_date_format + ' ' + moment_time_format,
        ignoreReadonly: true,
        widgetPositioning: {
            horizontal: 'right',
            vertical: 'bottom'
        }
    });
});

$(document).on('click', 'button#add_opening_stock_btn', function(e) {
    e.preventDefault();
    var btn = $(this);
    var form = $('form#add_opening_stock_form');
    var data = form.serialize();

    // Manually disable the button
    btn.prop('disabled', true);

    $.ajax({
        method: 'POST',
        url: form.attr('action'),
        dataType: 'json',
        data: data,
        success: function(result) {
            if (result.success == true) {
                $('#opening_stock_modal').modal('hide');
                toastr.success(result.msg);
                location.reload(); // Reload to reflect changes
            } else {
                toastr.error(result.msg);
            }
        },
        complete: function() {
            // Manually re-enable the button
            btn.prop('disabled', false);
        }
    });
    return false;
});

function update_table_total(table) {
    var total_subtotal = 0;
    table.find('tbody tr').each(function() {
        var qty = __read_number($(this).find('.purchase_quantity'));
        var unit_price = __read_number($(this).find('.unit_price'));
        var row_subtotal = qty * unit_price;
        $(this)
            .find('.row_subtotal_before_tax')
            .text(__number_f(row_subtotal));
        total_subtotal += row_subtotal;
    });
    table.find('tfoot tr #total_subtotal').text(__currency_trans_from_en(total_subtotal, true));
    table.find('tfoot tr #total_subtotal_hidden').val(total_subtotal);
}