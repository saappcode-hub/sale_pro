<style>
    .is-invalid {
        border: 2px solid red;
    }
    .list-group {
        max-height: 200px;
        overflow-y: auto;
        position: absolute;
        width: 100%;
    }
    .form-row {
        display: flex;
        gap: 15px;
    }
    .form-row .form-group {
        flex: 1;
    }
    .section-header {
        font-size: 18px;
        font-weight: bold;
        margin: 20px 0 15px 0;
        color: #333;
    }
</style>

<div class="modal-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h5 class="modal-title" id="modalLabel" style="font-size: 24px; flex-grow: 1;">{{ __('Edit Reward') }}</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 24px; margin-top: 0; margin-bottom: 0;">
        <span aria-hidden="true">&times;</span>
    </button>
</div>

<div class="modal-body">
    <form id="editRewardForm" method="POST" action="{{ route('rewards_exchange.update', $rewardExchange->id) }}">
        @csrf
        @method('PUT')

        <!-- Hidden field to store the type of reward (customers or suppliers) -->
        <input type="hidden" id="rewardType" name="type" value="{{ $rewardExchange->type }}">

        <!-- Redemption Details Section (only shown if type is "customers") -->
        @if($rewardExchange->type === 'customers')
            <div class="section-header">Redemption Details</div>
            
            <!-- Product for Sale - now labeled as Redemption Service -->
            <div class="form-group" id="productForSaleGroup">
                <label for="productForSale">{{ __('Redemption Service') }} *</label>
                <input type="text" class="form-control product-input" id="productForSale" value="{{ $rewardExchange->product_for_sale_name }}" autocomplete="off" readonly>
                <input type="hidden" id="productForSaleId" name="product_for_sale" value="{{ $rewardExchange->product_for_sale }}">
                <ul id="productForSaleDropdown" class="list-group" style="display: none;"></ul>
            </div>

            <!-- Exchange Product and Exchange Quantity in same row -->
            <div class="form-row">
                <div class="form-group">
                    <label for="exchangeProduct">{{ __('Ring pull Product') }} *</label>
                    <input type="text" class="form-control product-input" id="exchangeProduct" value="{{ $rewardExchange->exchange_product_name }}" autocomplete="off">
                    <input type="hidden" id="exchangeProductId" name="exchange_product" value="{{ $rewardExchange->exchange_product }}">
                    <ul id="exchangeProductDropdown" class="list-group" style="display: none;"></ul>
                </div>

                <div class="form-group">
                    <label for="exchangeQuantity">{{ __('Ring pull Quantity') }} *</label>
                    <input type="number" class="form-control" id="exchangeQuantity" name="exchange_quantity" value="{{ $rewardExchange->exchange_quantity }}" required>
                </div>
            </div>

            <!-- Amount - now labeled as Price -->
            <div class="form-group">
                <label for="amount">{{ __('Price') }} *</label>
                <input type="text" class="form-control" id="amount" name="amount" value="{{ $rewardExchange->amount }}" required>
            </div>
        @else
            <!-- For suppliers - show Ring pull Product and Ring pull Quantity directly -->
            <div class="form-row">
                <div class="form-group">
                    <label for="exchangeProduct">{{ __('Ring pull Product') }} *</label>
                    <input type="text" class="form-control product-input" id="exchangeProduct" value="{{ $rewardExchange->exchange_product_name }}" autocomplete="off">
                    <input type="hidden" id="exchangeProductId" name="exchange_product" value="{{ $rewardExchange->exchange_product }}">
                    <ul id="exchangeProductDropdown" class="list-group" style="display: none;"></ul>
                </div>

                <div class="form-group">
                    <label for="exchangeQuantity">{{ __('Ring pull Quantity') }} *</label>
                    <input type="number" class="form-control" id="exchangeQuantity" name="exchange_quantity" value="{{ $rewardExchange->exchange_quantity }}" required>
                </div>
            </div>

            <!-- Amount - now labeled as Price -->
            <div class="form-group">
                <label for="amount">{{ __('Price') }} *</label>
                <input type="text" class="form-control" id="amount" name="amount" value="{{ $rewardExchange->amount }}" required>
            </div>
        @endif

        <!-- Reward Details Section -->
        <div class="section-header">Reward Details</div>

        <!-- Receive Product and Receive Quantity in same row -->
        <div class="form-row">
            <div class="form-group">
                <label for="receiveProduct">{{ __('Redeem Product') }} *</label>
                <input type="text" class="form-control product-input" id="receiveProduct" value="{{ $rewardExchange->receive_product_name }}" autocomplete="off" required>
                <input type="hidden" id="receiveProductId" name="receive_product" value="{{ $rewardExchange->receive_product }}">
                <ul id="receiveProductDropdown" class="list-group" style="display: none;"></ul>
            </div>

            <div class="form-group">
                <label for="receiveQuantity">{{ __('Redeem Quantity') }} *</label>
                <input type="number" class="form-control" id="receiveQuantity" name="receive_quantity" value="{{ $rewardExchange->receive_quantity }}" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            {{ __('Update Reward') }}
        </button>
    </form>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        // Check reward type and hide product for sale field if type is suppliers
        var rewardType = $('#rewardType').val();
        if (rewardType === 'suppliers') {
            $('#productForSaleGroup').hide(); // Hide the group
        }
    });
</script>