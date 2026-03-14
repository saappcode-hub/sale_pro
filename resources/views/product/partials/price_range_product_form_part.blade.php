<div class="table-responsive">
    <table class="table table-bordered add-product-price-table table-condensed" id="price_range_table">
        <thead>
            <tr>
                <th colspan="2" style="text-align: left;">Product Price Range</th>
                <th style="text-align: left;">Price</th>
                <th style="text-align: left;">@lang('messages.action')</th>
            </tr>
            <tr>
                <th style="background-color: transparent; color: #525f7f;">Minimum Qty:*</th>
                <th style="background-color: transparent; color: #525f7f;">Maximum Qty:*</th>
                <th style="background-color: transparent; color: #525f7f;">Price Per Piece:*</th>
                <th style="background-color: transparent;"></th> <!-- Action header remains empty for symmetry -->
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><input type="number" name="product_price_range[0][minimum_qty]" class="form-control min-qty" placeholder="Minimum Qty" required/></td>
                <td><input type="number" name="product_price_range[0][maximum_qty]" class="form-control max-qty" placeholder="Maximum Qty" required/><span class="error-msg" style="display:none; color: red;"></span></td>
                <td><input type="text" name="product_price_range[0][price]" class="form-control" placeholder="Price Per Piece" required/></td>
                <td class="text-center">
                    <button type="button" class="btn btn-circle btn-add" style="background-color: blue; color: white;"><i class="fa fa-plus"></i></button>
                    <button type="button" class="btn btn-circle btn-remove" style="background-color: red; color: white;" disabled><i class="fa fa-minus"></i></button>
                </td>
            </tr>
        </tbody>
    </table>
</div>

@section('css')
<style>
    .btn-circle.btn {
        border-radius: 50%;
        width: 30px;
        height: 30px;
        padding: 6px 0px;
        border: none;
        vertical-align: middle;
        text-align: center;
    }
</style>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        var counter = 1; // Start with one row
        updateRemoveButtons();

        $('#price_range_table').on('input', '.min-qty, .max-qty', function() {
            validateQuantities();
        });

        function validateQuantities() {
            let valid = true;
            $('#price_range_table tbody tr').each(function(index, row) {
                const minQty = parseInt($(row).find('.min-qty').val());
                const maxQty = parseInt($(row).find('.max-qty').val());
                const errorMsg = $(row).find('.error-msg');

                if (maxQty <= minQty) {
                    errorMsg.show().text('Maximum Qty must be greater than Minimum Qty');
                    valid = false;
                } else {
                    errorMsg.hide();
                }

                // Check against previous row's max quantity
                if (index > 0) {
                    const prevMaxQty = parseInt($(row).prev().find('.max-qty').val());
                    if (minQty <= prevMaxQty) {
                        errorMsg.show().text('Minimum Qty must be greater than the previous Maximum Qty');
                        valid = false;
                    }
                }
            });
            return valid;
        }

        $('#price_range_table').on('click', '.btn-add', function() {
            if (!validateQuantities() || counter >= 3) {
                return false;
            }

            var newRow = $('<tr>');
            var cols = '';
            cols += '<td><input type="number" class="form-control min-qty" name="product_price_range[' + counter + '][minimum_qty]" placeholder="Minimum Qty" required/></td>';
            cols += '<td><input type="number" class="form-control max-qty" name="product_price_range[' + counter + '][maximum_qty]" placeholder="Maximum Qty" required/><span class="error-msg" style="display:none; color: red;"></span></td>';
            cols += '<td><input type="text" class="form-control" name="product_price_range[' + counter + '][price]" placeholder="Price Per Piece" required/></td>';
            cols += '<td class="text-center"><button type="button" class="btn btn-circle btn-add" style="background-color: blue; color: white;"><i class="fa fa-plus"></i></button> <button type="button" class="btn btn-circle btn-remove" style="background-color: red; color: white;"><i class="fa fa-minus"></i></button></td>';
            newRow.append(cols);
            $(this).closest('tr').after(newRow);
            counter++;
            updateRemoveButtons();
            updateAddButtonStatus(); // Update the Add button status after a row is added
        });

        $('#price_range_table').on('click', '.btn-remove', function() {
            if (counter > 1) {
                $(this).closest('tr').remove();
                counter--;
                updateRemoveButtons();
                validateQuantities(); // Re-validate on removal
                updateAddButtonStatus(); // Update the Add button status after a row is added
            }
        });

        function updateRemoveButtons() {
            $('.btn-remove').prop('disabled', counter <= 1);
        }

        function updateAddButtonStatus() {
            if (counter >= 3) {
                $('.btn-add').prop('disabled', true).css('opacity', '0.5');
            } else {
                $('.btn-add').prop('disabled', false).css('opacity', '0.5');
            }
        }
    });
</script>

@endsection