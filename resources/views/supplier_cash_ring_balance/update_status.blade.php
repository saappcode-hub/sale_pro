<div class="modal-dialog modal-sm" role="document" style="width: 500px;">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">Update Status</h4>
        </div>
        <form id="update-status-form" method="POST" action="{{ route('supplier-cash-ring-balance.update-status', $transaction->id) }}">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="form-group">
                    <label for="current_status">Current Status:</label>
                    <input type="text" class="form-control" value="{{ ucfirst($transaction->status) }}" readonly>
                </div>
                
                <div class="form-group">
                    <label for="status">New Status: <span class="text-danger">*</span></label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="">Select Status</option>
                        @if(in_array('send', $allowedStatuses))
                            <option value="send">Send</option>
                        @endif
                        @if(in_array('claim', $allowedStatuses))
                            <option value="claim">Claim</option>
                        @endif
                    </select>
                    @if(empty($allowedStatuses))
                        <small class="text-muted">No status changes allowed for current status.</small>
                    @endif
                </div>
                

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                @if(!empty($allowedStatuses))
                    <button type="submit" class="btn btn-primary" id="update-btn">Update</button>
                @endif
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#update-status-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#update-btn');
        var originalText = submitBtn.text();
        
        // Disable submit button
        submitBtn.prop('disabled', true).text('Updating...');
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    // Show success message
                    toastr.success(response.message);
                    
                    // Close modal
                    $('.view_modal').modal('hide');
                    
                    // Reload DataTable
                    if (typeof supplier_cash_ring_table !== 'undefined') {
                        supplier_cash_ring_table.ajax.reload(null, false);
                    } else {
                        // Fallback: reload page
                        location.reload();
                    }
                } else {
                    toastr.error(response.message || 'Update failed');
                }
            },
            error: function(xhr) {
                var errorMessage = 'Update failed';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
            },
            complete: function() {
                // Re-enable submit button
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>