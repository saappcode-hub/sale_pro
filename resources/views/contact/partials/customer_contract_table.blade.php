<div class="row">
    <div class="col-md-12">
        <div class="text-right" style="margin-bottom: 10px;">
            <button type="button" class="btn btn-primary customer_contract_btn" 
                data-href="{{ action([\App\Http\Controllers\ContactController::class, 'getCustomerContractForm'], [$contact->id]) }}">
                <i class="fa fa-plus"></i> Add
            </button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" 
                id="customer_contract_table" 
                width="100%"
                data-url="{{ action([\App\Http\Controllers\ContactController::class, 'getCustomerContracts'], [$contact->id]) }}">
                <thead>
                    <tr>
                        <th style="width: 50px;">Action</th>
                        <th>Ref. No.</th>
                        <th>Contract Name</th>
                        <th>Period</th>
                        <th>Product Targets</th> 
                        <th>Progress</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Added By</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
    /* Ensures content is in the middle of the cell vertically */
    #customer_contract_table td {
        vertical-align: middle !important;
    }
</style>