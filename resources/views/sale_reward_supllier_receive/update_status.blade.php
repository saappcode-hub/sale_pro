<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('Update Status')</h4>
        </div>
        <form action="{{ route('sale-reward-supplier-receive.update_status', $transaction->id) }}" method="POST" id="updateStatusForm">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label for="status">@lang('Purchase Status'):</label>
                    <select class="form-control" name="status" id="status" required>
                        <option value="pending" {{ $transaction->status == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ $transaction->status == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">@lang('Update')</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('Close')</button>
            </div>
        </form>
    </div>
</div>
