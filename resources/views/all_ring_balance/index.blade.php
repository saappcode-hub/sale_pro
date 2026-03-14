@extends('layouts.app')

@section('title', __('All Ring'))

@section('content')
<section class="content-header">
    <h1>{{ __('All Ring') }}</h1>
</section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_product_id', 'Product' . ':') !!}
                {!! Form::select('sell_list_filter_product_id', $product_list, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'product_filter']) !!}
            </div>
        </div>
    </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <!-- Tab Navigation -->
                <div class="nav-tabs-custom" style="margin-bottom: 0;">
                    <ul class="nav nav-tabs" style="border-bottom: 1px solid #ddd;">
                        <li class="active">
                            <a href="#ring_tab" data-toggle="tab" aria-expanded="true" data-tab="ring" style="border-radius: 4px 4px 0 0;">Ring</a>
                        </li>
                        <li>
                            <a href="#cash_ring_tab" data-toggle="tab" aria-expanded="false" data-tab="cash_ring" style="border-radius: 4px 4px 0 0;">Cash Ring</a>
                        </li>
                    </ul>
                    <div class="tab-content" style="padding: 20px 24px;">
                        <!-- Ring Tab -->
                        <div class="tab-pane active" id="ring_tab">
                            <table class="table table-bordered table-striped" id="all_ring">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Product Name</th>
                                        <th>Total Ring(Customer)</th>
                                        <th>Ring(Shop)</th>
                                        <th>Ring(Supplier)</th>
                                        <th>Total Ring</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <!-- Cash Ring Tab -->
                        <div class="tab-pane" id="cash_ring_tab">
                            <table class="table table-bordered table-striped" id="cash_ring">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Product Name</th>
                                        <th>Total Cash Ring(QTY)</th>
                                        <th>Total Cash Ring($)</th>
                                        <th>Total Cash Ring(៛)</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    var activeTab = 'ring'; // Default active tab
    var ringTable, cashRingTable;

    // Custom CSS for proper tab styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .nav-tabs-custom > .nav-tabs {
                margin: 0;
                border-bottom-color: #ddd;
            }
            .nav-tabs-custom > .nav-tabs > li {
                border-bottom: 0;
                margin-bottom: -1px;
            }
            .nav-tabs-custom > .nav-tabs > li > a {
                color: #444;
                border-radius: 4px 4px 0 0;
            }
            .nav-tabs-custom > .nav-tabs > li.active {
                border-bottom-color: transparent;
            }
            .nav-tabs-custom > .nav-tabs > li.active > a {
                background-color: #fff;
                border-color: #ddd #ddd transparent;
            }
            .nav-tabs-custom > .nav-tabs > li:not(.active) > a:hover {
                border-color: #eee #eee #ddd;
            }
            .nav-tabs-custom > .tab-content {
                background: #fff;
                padding: 20px 24px;
                border-left: 1px solid #ddd;
                border-bottom: 1px solid #ddd;
                border-right: 1px solid #ddd;
            }
        `)
        .appendTo('head');

    // Initialize Ring DataTable
    function initRingTable() {
        if (ringTable) {
            ringTable.destroy();
        }
        
        ringTable = $('#all_ring').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ url("all-ring") }}',
                data: function (d) {
                    d.product_id = $('#product_filter').val();
                    d.tab = 'ring';
                }
            },
            columns: [
                { data: 'action', name: 'action', searchable: false, orderable: false },
                { data: 'product_name', name: 'product_name' },
                {
                    data: 'stock_ring_balance',
                    name: 'stock_ring_balance',
                    render: function(data, type, row) {
                        return data + ' ' + (row.unit_name || '');
                    }
                },
                {
                    data: 'qty_available',
                    name: 'qty_available',
                    render: function(data, type, row) {
                        return data + ' ' + (row.unit_name || '');
                    }
                },
                {
                    data: 'total_suppliers',
                    name: 'total_suppliers',
                    render: function(data, type, row) {
                        return data + ' ' + (row.unit_name || '');
                    }
                },
                {
                    data: 'total_stock',
                    name: 'total_stock',
                    render: function(data, type, row) {
                        return data + ' ' + (row.unit_name || '');
                    }
                }
            ]
        });
    }

    // Initialize Cash Ring DataTable
    function initCashRingTable() {
        if (cashRingTable) {
            cashRingTable.destroy();
        }
        
        cashRingTable = $('#cash_ring').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ url("all-ring") }}',
                data: function (d) {
                    d.product_id = $('#product_filter').val();
                    d.tab = 'cash_ring';
                }
            },
            columns: [
                { data: 'action', name: 'action', searchable: false, orderable: false },
                { data: 'product_name', name: 'product_name' },
                { data: 'total_cash_ring_qty', name: 'total_cash_ring_qty' },
                { data: 'total_cash_ring_dollar', name: 'total_cash_ring_dollar' },
                { data: 'total_cash_ring_riel', name: 'total_cash_ring_riel' }
            ]
        });
    }

    // Initialize the default tab
    initRingTable();

    // Handle tab switching
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        var tabData = $(e.target).data('tab');
        activeTab = tabData;
        
        if (tabData === 'ring') {
            initRingTable();
        } else if (tabData === 'cash_ring') {
            initCashRingTable();
        }
    });

    // When the product filter changes, reload the active DataTable
    $('#product_filter').on('change', function() {
        if (activeTab === 'ring' && ringTable) {
            ringTable.ajax.reload();
        } else if (activeTab === 'cash_ring' && cashRingTable) {
            cashRingTable.ajax.reload();
        }
    });
});
</script>
@endsection