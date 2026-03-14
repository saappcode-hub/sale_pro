<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\CashRegister;
use App\Category;
use App\Charts\CommonChart;
use App\Contact;
use App\CustomerGroup;
use App\ExpenseCategory;
use App\Product;
use App\PurchaseLine;
use App\Restaurant\ResTable;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\TransactionSellLinesPurchaseLines;
use App\Unit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use Datatables;
use DB;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Contracts\DataTable;

class ReportController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $transactionUtil;

    protected $productUtil;

    protected $moduleUtil;

    protected $businessUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil, ModuleUtil $moduleUtil, BusinessUtil $businessUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
        $this->businessUtil = $businessUtil;
    }

    public function getStockBySellingPrice(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $location_id = $request->get('location_id');

        $day_before_start_date = \Carbon::createFromFormat('Y-m-d', $start_date)->subDay()->format('Y-m-d');

        $opening_stock_by_sp = $this->transactionUtil->getOpeningClosingStock($business_id, $day_before_start_date, $location_id, true, true);

        $closing_stock_by_sp = $this->transactionUtil->getOpeningClosingStock($business_id, $end_date, $location_id, false, true);

        return [
            'opening_stock_by_sp' => $opening_stock_by_sp,
            'closing_stock_by_sp' => $closing_stock_by_sp,
        ];
    }

    /**
     * Shows profit\loss of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getProfitLoss(Request $request)
    {
        if (! auth()->user()->can('profit_loss_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $location_id = $request->get('location_id');

            $data = $this->transactionUtil->getProfitLossDetails($business_id, $location_id, $start_date, $end_date);

            // $data['closing_stock'] = $data['closing_stock'] - $data['total_sell_return'];

            return view('report.partials.profit_loss_details', compact('data'))->render();
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.profit_loss', compact('business_locations'));
    }

    /**
     * Shows product report of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getPurchaseSell(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            $location_id = $request->get('location_id');

            $purchase_details = $this->transactionUtil->getPurchaseTotals($business_id, $start_date, $end_date, $location_id);

            $sell_details = $this->transactionUtil->getSellTotals(
                $business_id,
                $start_date,
                $end_date,
                $location_id
            );

            $transaction_types = [
                'purchase_return', 'sell_return',
            ];

            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start_date,
                $end_date,
                $location_id
            );

            $total_purchase_return_inc_tax = $transaction_totals['total_purchase_return_inc_tax'];
            $total_sell_return_inc_tax = $transaction_totals['total_sell_return_inc_tax'];

            $difference = [
                'total' => $sell_details['total_sell_inc_tax'] - $total_sell_return_inc_tax - ($purchase_details['total_purchase_inc_tax'] - $total_purchase_return_inc_tax),
                'due' => $sell_details['invoice_due'] - $purchase_details['purchase_due'],
            ];

            return ['purchase' => $purchase_details,
                'sell' => $sell_details,
                'total_purchase_return' => $total_purchase_return_inc_tax,
                'total_sell_return' => $total_sell_return_inc_tax,
                'difference' => $difference,
            ];
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.purchase_sell')
                    ->with(compact('business_locations'));
    }

    /**
     * Shows report for Supplier
     *
     * @return \Illuminate\Http\Response
     */
    public function getCustomerSuppliers(Request $request)
    {
        if (! auth()->user()->can('contacts_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $contacts = Contact::where('contacts.business_id', $business_id)
                ->join('transactions AS t', 'contacts.id', '=', 't.contact_id')
                ->active()
                ->groupBy('contacts.id')
                ->select(
                    DB::raw("SUM(IF(t.type = 'purchase', final_total, 0)) as total_purchase"),
                    DB::raw("SUM(IF(t.type = 'purchase_return', final_total, 0)) as total_purchase_return"),
                    DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', final_total, 0)) as total_invoice"),
                    DB::raw("SUM(IF(t.type = 'purchase', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_paid"),
                    DB::raw("SUM(IF(t.type = 'sell' AND t.status = 'final', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as invoice_received"),
                    DB::raw("SUM(IF(t.type = 'sell_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as sell_return_paid"),
                    DB::raw("SUM(IF(t.type = 'purchase_return', (SELECT SUM(amount) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as purchase_return_received"),
                    DB::raw("SUM(IF(t.type = 'sell_return', final_total, 0)) as total_sell_return"),
                    DB::raw("SUM(IF(t.type = 'opening_balance', final_total, 0)) as opening_balance"),
                    DB::raw("SUM(IF(t.type = 'opening_balance', (SELECT SUM(IF(is_return = 1,-1*amount,amount)) FROM transaction_payments WHERE transaction_payments.transaction_id=t.id), 0)) as opening_balance_paid"),
                    DB::raw("SUM(IF(t.type = 'ledger_discount' AND sub_type='sell_discount', final_total, 0)) as total_ledger_discount_sell"),
                    DB::raw("SUM(IF(t.type = 'ledger_discount' AND sub_type='purchase_discount', final_total, 0)) as total_ledger_discount_purchase"),
                    'contacts.supplier_business_name',
                    'contacts.name',
                    'contacts.id',
                    'contacts.type as contact_type'
                );
            $permitted_locations = auth()->user()->permitted_locations();

            if ($permitted_locations != 'all') {
                $contacts->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($request->input('customer_group_id'))) {
                $contacts->where('contacts.customer_group_id', $request->input('customer_group_id'));
            }

            if (! empty($request->input('location_id'))) {
                $contacts->where('t.location_id', $request->input('location_id'));
            }

            if (! empty($request->input('contact_id'))) {
                $contacts->where('t.contact_id', $request->input('contact_id'));
            }

            if (! empty($request->input('contact_type'))) {
                $contacts->whereIn('contacts.type', [$request->input('contact_type'), 'both']);
            }

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $contacts->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            return Datatables::of($contacts)
                ->editColumn('name', function ($row) {
                    $name = $row->name;
                    if (! empty($row->supplier_business_name)) {
                        $name .= ', '.$row->supplier_business_name;
                    }

                    return '<a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'" target="_blank" class="no-print">'.
                            $name.
                        '</a>';
                })
                ->editColumn(
                    'total_purchase',
                    '<span class="total_purchase" data-orig-value="{{$total_purchase}}">@format_currency($total_purchase)</span>'
                )
                ->editColumn(
                    'total_purchase_return',
                    '<span class="total_purchase_return" data-orig-value="{{$total_purchase_return}}">@format_currency($total_purchase_return)</span>'
                )
                ->editColumn(
                    'total_sell_return',
                    '<span class="total_sell_return" data-orig-value="{{$total_sell_return}}">@format_currency($total_sell_return)</span>'
                )
                ->editColumn(
                    'total_invoice',
                    '<span class="total_invoice" data-orig-value="{{$total_invoice}}">@format_currency($total_invoice)</span>'
                )

                ->addColumn('due', function ($row) {
                    $total_ledger_discount_purchase = $row->total_ledger_discount_purchase ?? 0;
                    $total_ledger_discount_sell = $total_ledger_discount_sell ?? 0;
                    $due = ($row->total_invoice - $row->invoice_received - $total_ledger_discount_sell) - ($row->total_purchase - $row->purchase_paid - $total_ledger_discount_purchase) - ($row->total_sell_return - $row->sell_return_paid) + ($row->total_purchase_return - $row->purchase_return_received);

                    if ($row->contact_type == 'supplier') {
                        $due -= $row->opening_balance - $row->opening_balance_paid;
                    } else {
                        $due += $row->opening_balance - $row->opening_balance_paid;
                    }

                    $due_formatted = $this->transactionUtil->num_f($due, true);

                    return '<span class="total_due" data-orig-value="'.$due.'">'.$due_formatted.'</span>';
                })
                ->addColumn(
                    'opening_balance_due',
                    '<span class="opening_balance_due" data-orig-value="{{$opening_balance - $opening_balance_paid}}">@format_currency($opening_balance - $opening_balance_paid)</span>'
                )
                ->removeColumn('supplier_business_name')
                ->removeColumn('invoice_received')
                ->removeColumn('purchase_paid')
                ->removeColumn('id')
                ->filterColumn('name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->rawColumns(['total_purchase', 'total_invoice', 'due', 'name', 'total_purchase_return', 'total_sell_return', 'opening_balance_due'])
                ->make(true);
        }

        $customer_group = CustomerGroup::forDropdown($business_id, false, true);
        $types = [
            '' => __('lang_v1.all'),
            'customer' => __('report.customer'),
            'supplier' => __('report.supplier'),
        ];

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $contact_dropdown = Contact::contactDropdown($business_id, false, false);

        return view('report.contact')
        ->with(compact('customer_group', 'types', 'business_locations', 'contact_dropdown'));
    }

    /**
     * Shows product stock report
     *
     * @return \Illuminate\Http\Response
     */
    public function getStockReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $selling_price_groups = SellingPriceGroup::where('business_id', $business_id)
                                                ->get();
        $allowed_selling_price_group = false;
        foreach ($selling_price_groups as $selling_price_group) {
            if (auth()->user()->can('selling_price_group.'.$selling_price_group->id)) {
                $allowed_selling_price_group = true;
                break;
            }
        }
        if ($this->moduleUtil->isModuleInstalled('Manufacturing') && (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'manufacturing_module'))) {
            $show_manufacturing_data = 1;
        } else {
            $show_manufacturing_data = 0;
        }
        if ($request->ajax()) {
            $filters = request()->only(['location_id', 'category_id', 'sub_category_id', 'brand_id', 'unit_id', 'tax_id', 'type',
                'only_mfg_products', 'active_state',  'not_for_selling', 'repair_model_id', 'product_id', 'active_state', ]);

            $filters['not_for_selling'] = isset($filters['not_for_selling']) && $filters['not_for_selling'] == 'true' ? 1 : 0;

            $filters['show_manufacturing_data'] = $show_manufacturing_data;

            //Return the details in ajax call
            $for = request()->input('for') == 'view_product' ? 'view_product' : 'datatables';

            $products = $this->productUtil->getProductStockDetails($business_id, $filters, $for);
            //To show stock details on view product modal
            if ($for == 'view_product' && ! empty(request()->input('product_id'))) {
                $product_stock_details = $products;

                return view('product.partials.product_stock_details')->with(compact('product_stock_details'));
            }

            $datatable = Datatables::of($products)
                ->editColumn('stock', function ($row) {
                    if ($row->enable_stock) {
                        $stock = $row->stock ? $row->stock : 0;

                        return  '<span class="current_stock" data-orig-value="'.(float) $stock.'" data-unit="'.$row->unit.'"> '.$this->transactionUtil->num_f($stock, false, null, true).'</span>'.' '.$row->unit;
                    } else {
                        return '--';
                    }
                })
                ->editColumn('product', function ($row) {
                    $name = $row->product;

                    return $name;
                })
                ->addColumn('action', function ($row) {
                    return '<a class="btn btn-info btn-xs" href="'.action([\App\Http\Controllers\ProductController::class, 'productStockHistory'], [$row->product_id]).
                    '?location_id='.$row->location_id.'&variation_id='.$row->variation_id.
                    '"><i class="fas fa-history"></i> '.__('lang_v1.product_stock_history').'</a>';
                })
                ->addColumn('variation', function ($row) {
                    $variation = '';
                    if ($row->type == 'variable') {
                        $variation .= $row->product_variation.'-'.$row->variation_name;
                    }

                    return $variation;
                })
               ->addColumn('quantity', function ($row) {
                    if ($row->enable_stock) {
                        $base_unit_multiplier = $row->base_unit_multiplier ?? 1; // Number of smaller units in one purchase unit
                        $stock = $row->stock ?? 0;
                        if ($row->purchase_unit !== null) {
                            if ($stock < $base_unit_multiplier && $stock > 0) {
                            
                                // If stock is less than one purchase unit, display it in terms of the smaller unit with two decimal places
                                return number_format($stock, 2) . " {$row->unit}";
                            }
                    
                            // Calculate the number of whole purchase units and remaining smaller units
                            $whole_units = intdiv($stock, $base_unit_multiplier); // Whole purchase units
                            $remaining_units = $stock % $base_unit_multiplier; // Remaining smaller units
                    
                            // Case 1: If there's no remainder, display only whole purchase units
                            if ($remaining_units === 0) {
                                return "{$whole_units} {$row->purchase_unit_name}";
                            }
                    
                            // Case 2: Display both whole units in purchase unit and remaining units in smaller unit
                            return "{$whole_units} {$row->purchase_unit_name} {$remaining_units} {$row->unit}";
                        }else {
                            // Display current stock with the base unit name if purchase_unit is null
                            return number_format($stock, 2) . " {$row->unit}";
                        }
                    } else {
                        return '--';
                    }
                })   
                ->editColumn('total_sold', function ($row) {
                    $total_sold = 0;
                    if ($row->total_sold) {
                        $total_sold = (float) $row->total_sold;
                    }

                    return '<span data-is_quantity="true" class="total_sold" data-orig-value="'.$total_sold.'" data-unit="'.$row->unit.'" >'.$this->transactionUtil->num_f($total_sold, false, null, true).'</span> '.$row->unit;
                })
                ->editColumn('total_transfered', function ($row) {
                    $total_transfered = 0;
                    if ($row->total_transfered) {
                        $total_transfered = (float) $row->total_transfered;
                    }

                    return '<span class="total_transfered" data-orig-value="'.$total_transfered.'" data-unit="'.$row->unit.'" >'.$this->transactionUtil->num_f($total_transfered, false, null, true).'</span> '.$row->unit;
                })

                ->editColumn('total_adjusted', function ($row) {
                    $total_adjusted = 0;
                    if ($row->total_adjusted) {
                        $total_adjusted = (float) $row->total_adjusted;
                    }

                    return '<span class="total_adjusted" data-orig-value="'.$total_adjusted.'" data-unit="'.$row->unit.'" >'.$this->transactionUtil->num_f($total_adjusted, false, null, true).'</span> '.$row->unit;
                })
                ->editColumn('unit_price', function ($row) use ($allowed_selling_price_group) {
                    $html = '';
                    if (auth()->user()->can('access_default_selling_price')) {
                        $html .= $this->transactionUtil->num_f($row->unit_price, true);
                    }

                    if ($allowed_selling_price_group) {
                        $html .= ' <button type="button" class="btn btn-primary btn-xs btn-modal no-print" data-container=".view_modal" data-href="'.action([\App\Http\Controllers\ProductController::class, 'viewGroupPrice'], [$row->product_id]).'">'.__('lang_v1.view_group_prices').'</button>';
                    }

                    return $html;
                })
                ->editColumn('stock_price', function ($row) {
                    $html = '<span class="total_stock_price" data-orig-value="'
                        .$row->stock_price.'">'.
                        $this->transactionUtil->num_f($row->stock_price, true).'</span>';

                    return $html;
                })
                ->editColumn('stock_value_by_sale_price', function ($row) {
                    $stock = $row->stock ? $row->stock : 0;
                    $unit_selling_price = (float) $row->group_price > 0 ? $row->group_price : $row->unit_price;
                    $stock_price = $stock * $unit_selling_price;

                    return  '<span class="stock_value_by_sale_price" data-orig-value="'.(float) $stock_price.'" > '.$this->transactionUtil->num_f($stock_price, true).'</span>';
                })
                ->addColumn('potential_profit', function ($row) {
                    $stock = $row->stock ? $row->stock : 0;
                    $unit_selling_price = (float) $row->group_price > 0 ? $row->group_price : $row->unit_price;
                    $stock_price_by_sp = $stock * $unit_selling_price;
                    $potential_profit = (float) $stock_price_by_sp - (float) $row->stock_price;

                    return  '<span class="potential_profit" data-orig-value="'.(float) $potential_profit.'" > '.$this->transactionUtil->num_f($potential_profit, true).'</span>';
                })
                ->setRowClass(function ($row) {
                    return $row->enable_stock && $row->stock <= $row->alert_quantity ? 'bg-danger' : '';
                })
                ->filterColumn('variation', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(pv.name, ''), '-', COALESCE(variations.name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('enable_stock')
                ->removeColumn('unit')
                ->removeColumn('id');

            $raw_columns = ['unit_price', 'total_transfered', 'total_sold',
                'total_adjusted', 'stock', 'stock_price', 'stock_value_by_sale_price',
                'potential_profit', 'action', ];

            if ($show_manufacturing_data) {
                $datatable->editColumn('total_mfg_stock', function ($row) {
                    $total_mfg_stock = 0;
                    if ($row->total_mfg_stock) {
                        $total_mfg_stock = (float) $row->total_mfg_stock;
                    }

                    return '<span data-is_quantity="true" class="total_mfg_stock"  data-orig-value="'.$total_mfg_stock.'" data-unit="'.$row->unit.'" >'.$this->transactionUtil->num_f($total_mfg_stock, false, null, true).'</span> '.$row->unit;
                });
                $raw_columns[] = 'total_mfg_stock';
            }

            return $datatable->rawColumns($raw_columns)->make(true);
        }

        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::where('business_id', $business_id)
                            ->pluck('short_name', 'id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.stock_report')
            ->with(compact('categories', 'brands', 'units', 'business_locations', 'show_manufacturing_data'));
    }

    /**
     * Shows product stock details
     *
     * @return \Illuminate\Http\Response
     */
    public function getStockDetails(Request $request)
    {
        //Return the details in ajax call
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');
            $product_id = $request->input('product_id');
            $query = Product::leftjoin('units as u', 'products.unit_id', '=', 'u.id')
                ->join('variations as v', 'products.id', '=', 'v.product_id')
                ->join('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
                ->leftjoin('variation_location_details as vld', 'v.id', '=', 'vld.variation_id')
                ->where('products.business_id', $business_id)
                ->where('products.id', $product_id)
                ->whereNull('v.deleted_at');

            $permitted_locations = auth()->user()->permitted_locations();
            $location_filter = '';
            if ($permitted_locations != 'all') {
                $query->whereIn('vld.location_id', $permitted_locations);
                $locations_imploded = implode(', ', $permitted_locations);
                $location_filter .= "AND transactions.location_id IN ($locations_imploded) ";
            }

            if (! empty($request->input('location_id'))) {
                $location_id = $request->input('location_id');

                $query->where('vld.location_id', $location_id);

                $location_filter .= "AND transactions.location_id=$location_id";
            }

            $product_details = $query->select(
                'products.name as product',
                'u.short_name as unit',
                'pv.name as product_variation',
                'v.name as variation',
                'v.sub_sku as sub_sku',
                'v.sell_price_inc_tax',
                DB::raw('SUM(vld.qty_available) as stock'),
                DB::raw("(SELECT SUM(IF(transactions.type='sell', TSL.quantity - TSL.quantity_returned, -1* TPL.quantity) ) FROM transactions 
                        LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id

                        LEFT JOIN purchase_lines AS TPL ON transactions.id=TPL.transaction_id

                        WHERE transactions.status='final' AND transactions.type='sell' $location_filter 
                        AND (TSL.variation_id=v.id OR TPL.variation_id=v.id)) as total_sold"),
                DB::raw("(SELECT SUM(IF(transactions.type='sell_transfer', TSL.quantity, 0) ) FROM transactions 
                        LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id
                        WHERE transactions.status='final' AND transactions.type='sell_transfer' $location_filter 
                        AND (TSL.variation_id=v.id)) as total_transfered"),
                DB::raw("(SELECT SUM(IF(transactions.type='stock_adjustment', SAL.quantity, 0) ) FROM transactions 
                        LEFT JOIN stock_adjustment_lines AS SAL ON transactions.id=SAL.transaction_id
                        WHERE transactions.status='received' AND transactions.type='stock_adjustment' $location_filter 
                        AND (SAL.variation_id=v.id)) as total_adjusted")
                // DB::raw("(SELECT SUM(quantity) FROM transaction_sell_lines LEFT JOIN transactions ON transaction_sell_lines.transaction_id=transactions.id WHERE transactions.status='final' $location_filter AND
                //     transaction_sell_lines.variation_id=v.id) as total_sold")
            )
                        ->groupBy('v.id')
                        ->get();

            return view('report.stock_details')
                        ->with(compact('product_details'));
        }
    }

    /**
     * Shows tax report of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getTaxDetails(Request $request)
    {
        if (! auth()->user()->can('tax_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');
            $taxes = TaxRate::forBusiness($business_id);
            $type = $request->input('type');

            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            $sells = Transaction::leftJoin('tax_rates as tr', 'transactions.tax_id', '=', 'tr.id')
                            ->leftJoin('contacts as c', 'transactions.contact_id', '=', 'c.id')
                ->where('transactions.business_id', $business_id)
                ->with(['payment_lines'])
                ->select('c.name as contact_name',
                        'c.supplier_business_name',
                        'c.tax_number',
                        'transactions.ref_no',
                        'transactions.invoice_no',
                        'transactions.transaction_date',
                        'transactions.total_before_tax',
                        'transactions.tax_id',
                        'transactions.tax_amount',
                        'transactions.id',
                        'transactions.type',
                        'transactions.discount_type',
                        'transactions.discount_amount'
                    );
            if ($type == 'sell') {
                $sells->where('transactions.type', 'sell')
                    ->where('transactions.status', 'final')
                    ->where(function ($query) {
                        $query->whereHas('sell_lines', function ($q) {
                            $q->whereNotNull('transaction_sell_lines.tax_id');
                        })->orWhereNotNull('transactions.tax_id');
                    })
                    ->with(['sell_lines' => function ($q) {
                        $q->whereNotNull('transaction_sell_lines.tax_id');
                    }, 'sell_lines.line_tax']);
            }
            if ($type == 'purchase') {
                $sells->where('transactions.type', 'purchase')
                    ->where('transactions.status', 'received')
                    ->where(function ($query) {
                        $query->whereHas('purchase_lines', function ($q) {
                            $q->whereNotNull('purchase_lines.tax_id');
                        })->orWhereNotNull('transactions.tax_id');
                    })
                    ->with(['purchase_lines' => function ($q) {
                        $q->whereNotNull('purchase_lines.tax_id');
                    }, 'purchase_lines.line_tax']);
            }

            if ($type == 'expense') {
                $sells->where('transactions.type', 'expense')
                        ->whereNotNull('transactions.tax_id');
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (request()->has('contact_id')) {
                $contact_id = request()->get('contact_id');
                if (! empty($contact_id)) {
                    $sells->where('transactions.contact_id', $contact_id);
                }
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transactions.transaction_date', '>=', $start)
                                ->whereDate('transactions.transaction_date', '<=', $end);
            }
            $datatable = Datatables::of($sells);
            $raw_cols = ['total_before_tax', 'discount_amount', 'contact_name', 'payment_methods'];
            $group_taxes_array = TaxRate::groupTaxes($business_id);
            $group_taxes = [];
            foreach ($group_taxes_array as $group_tax) {
                foreach ($group_tax['sub_taxes'] as $sub_tax) {
                    $group_taxes[$group_tax->id]['sub_taxes'][$sub_tax->id] = $sub_tax;
                }
            }
            foreach ($taxes as $tax) {
                $col = 'tax_'.$tax['id'];
                $raw_cols[] = $col;
                $datatable->addColumn($col, function ($row) use ($tax, $type, $col, $group_taxes) {
                    $tax_amount = 0;
                    if ($type == 'sell') {
                        foreach ($row->sell_lines as $sell_line) {
                            if ($sell_line->tax_id == $tax['id']) {
                                $tax_amount += ($sell_line->item_tax * ($sell_line->quantity - $sell_line->quantity_returned));
                            }

                            //break group tax
                            if ($sell_line->line_tax->is_tax_group == 1 && array_key_exists($tax['id'], $group_taxes[$sell_line->tax_id]['sub_taxes'])) {
                                $group_tax_details = $this->transactionUtil->groupTaxDetails($sell_line->line_tax, $sell_line->item_tax);

                                $sub_tax_share = 0;
                                foreach ($group_tax_details as $sub_tax_details) {
                                    if ($sub_tax_details['id'] == $tax['id']) {
                                        $sub_tax_share = $sub_tax_details['calculated_tax'];
                                    }
                                }

                                $tax_amount += ($sub_tax_share * ($sell_line->quantity - $sell_line->quantity_returned));
                            }
                        }
                    } elseif ($type == 'purchase') {
                        foreach ($row->purchase_lines as $purchase_line) {
                            if ($purchase_line->tax_id == $tax['id']) {
                                $tax_amount += ($purchase_line->item_tax * ($purchase_line->quantity - $purchase_line->quantity_returned));
                            }

                            //break group tax
                            if ($purchase_line->line_tax->is_tax_group == 1 && array_key_exists($tax['id'], $group_taxes[$purchase_line->tax_id]['sub_taxes'])) {
                                $group_tax_details = $this->transactionUtil->groupTaxDetails($purchase_line->line_tax, $purchase_line->item_tax);

                                $sub_tax_share = 0;
                                foreach ($group_tax_details as $sub_tax_details) {
                                    if ($sub_tax_details['id'] == $tax['id']) {
                                        $sub_tax_share = $sub_tax_details['calculated_tax'];
                                    }
                                }

                                $tax_amount += ($sub_tax_share * ($purchase_line->quantity - $purchase_line->quantity_returned));
                            }
                        }
                    }
                    if ($row->tax_id == $tax['id']) {
                        $tax_amount += $row->tax_amount;
                    }

                    //break group tax
                    if (! empty($group_taxes[$row->tax_id]) && array_key_exists($tax['id'], $group_taxes[$row->tax_id]['sub_taxes'])) {
                        $group_tax_details = $this->transactionUtil->groupTaxDetails($row->tax_id, $row->tax_amount);

                        $sub_tax_share = 0;
                        foreach ($group_tax_details as $sub_tax_details) {
                            if ($sub_tax_details['id'] == $tax['id']) {
                                $sub_tax_share = $sub_tax_details['calculated_tax'];
                            }
                        }

                        $tax_amount += $sub_tax_share;
                    }

                    if ($tax_amount > 0) {
                        return '<span class="display_currency '.$col.'" data-currency_symbol="true" data-orig-value="'.$tax_amount.'">'.$tax_amount.'</span>';
                    } else {
                        return '';
                    }
                });
            }

            $datatable->editColumn(
                    'total_before_tax',
                    function ($row) {
                        return '<span class="total_before_tax" 
                        data-orig-value="'.$row->total_before_tax.'">'.
                        $this->transactionUtil->num_f($row->total_before_tax, true).'</span>';
                    }
                )->editColumn('discount_amount', function ($row) {
                    $d = '';
                    if ($row->discount_amount !== 0) {
                        $symbol = $row->discount_type != 'percentage';
                        $d .= $this->transactionUtil->num_f($row->discount_amount, $symbol);

                        if ($row->discount_type == 'percentage') {
                            $d .= '%';
                        }
                    }

                    return $d;
                })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$contact_name}}')
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]];
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                });

            return $datatable->rawColumns($raw_cols)
                            ->make(true);
        }
    }

    /**
     * Shows tax report of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getTaxReport(Request $request)
    {
        if (! auth()->user()->can('tax_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $location_id = $request->get('location_id');
            $contact_id = $request->get('contact_id');

            $input_tax_details = $this->transactionUtil->getInputTax($business_id, $start_date, $end_date, $location_id, $contact_id);

            $output_tax_details = $this->transactionUtil->getOutputTax($business_id, $start_date, $end_date, $location_id, $contact_id);

            $expense_tax_details = $this->transactionUtil->getExpenseTax($business_id, $start_date, $end_date, $location_id, $contact_id);

            $module_output_taxes = $this->moduleUtil->getModuleData('getModuleOutputTax', ['start_date' => $start_date, 'end_date' => $end_date]);

            $total_module_output_tax = 0;
            foreach ($module_output_taxes as $key => $module_output_tax) {
                $total_module_output_tax += $module_output_tax;
            }

            $total_output_tax = $output_tax_details['total_tax'] + $total_module_output_tax;

            $tax_diff = $total_output_tax - $input_tax_details['total_tax'] - $expense_tax_details['total_tax'];

            return [
                'tax_diff' => $tax_diff,
            ];
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $taxes = TaxRate::forBusiness($business_id);

        $tax_report_tabs = $this->moduleUtil->getModuleData('getTaxReportViewTabs');

        $contact_dropdown = Contact::contactDropdown($business_id, false, false);

        return view('report.tax_report')
            ->with(compact('business_locations', 'taxes', 'tax_report_tabs', 'contact_dropdown'));
    }

    // Daily Sales & Payment Report
    public function DailySalesPaymentReport(Request $request)
{
    $business_id = $request->session()->get('user.business_id');

    // Get business locations for dropdown
    $business_locations = BusinessLocation::forDropdown($business_id, true);

    // Get selected location and date range from request
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');

    // Initialize payment methods arrays
    $payment_methods = [];
    $payment_method_labels = [];

    // Only fetch payment methods if location is provided
    if ($location_id) {
        $location = BusinessLocation::find($location_id);
        if ($location && $location->default_payment_accounts) {
            $default_payment_accounts = json_decode($location->default_payment_accounts, true) ?? [];
            
            // Get enabled payment methods
            $enabled_methods = [];
            foreach ($default_payment_accounts as $method => $config) {
                if (isset($config['is_enabled']) && $config['is_enabled'] == '1') {
                    $enabled_methods[] = $method;
                }
            }

            // Fetch custom labels from business table
            $business = Business::where('id', $business_id)->first();
            $custom_labels = [];
            $payment_custom_labels = [];
            
            if ($business && $business->custom_labels) {
                $custom_labels = json_decode($business->custom_labels, true) ?? [];
                $payment_custom_labels = $custom_labels['payments'] ?? [];
                
                // Debug: Log the custom labels being retrieved
                \Log::info('Custom labels retrieved:', [
                    'business_id' => $business_id,
                    'custom_labels' => $custom_labels,
                    'payment_custom_labels' => $payment_custom_labels
                ]);
            }

            // Map enabled methods with custom labels
            foreach ($enabled_methods as $method) {
                $payment_methods[] = $method;
                
                // Handle custom payment methods
                if (strpos($method, 'custom_pay_') === 0) {
                    $custom_label = $payment_custom_labels[$method] ?? null;
                    
                    // Debug: Log the label mapping
                    \Log::info('Mapping custom payment method:', [
                        'method' => $method,
                        'custom_label' => $custom_label,
                        'payment_custom_labels' => $payment_custom_labels
                    ]);
                    
                    if ($custom_label) {
                        $payment_method_labels[$method] = $custom_label;
                    } else {
                        $payment_method_labels[$method] = ucfirst(str_replace('_', ' ', $method));
                    }
                } else {
                    // Handle specific payment method: cash_ring_percentage
                    if ($method === 'cash_ring_percentage') {
                        $payment_method_labels[$method] = 'Cash ring(%)';
                    } else {
                        // Handle other standard payment methods with better formatting
                        $payment_method_labels[$method] = ucfirst(str_replace('_', ' ', $method));
                    }
                }
            }
        }
    }

    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');

        // Return empty structure if filters not applied
        if (!$should_apply_filters) {
            return DataTables::of(collect([]))
                ->with([
                    'payment_methods' => $payment_methods, 
                    'payment_method_labels' => $payment_method_labels,
                    'footer_totals' => []
                ])
                ->make(true);
        }

        // Validate required parameters
        if (!$location_id) {
            return response()->json(['error' => 'Location is required'], 400);
        }

        if (!$date_range) {
            return response()->json(['error' => 'Date range is required'], 400);
        }

        // Parse date range with improved error handling
        $start_date = null;
        $end_date = null;
        
        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;
                
                foreach ($date_formats as $format) {
                    try {
                        $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                        $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                if (!$parsed) {
                    \Log::error('Date parsing failed for range: ' . $date_range);
                    return response()->json(['error' => 'Invalid date format'], 400);
                }
            }
        }

        // Build the main query with optimized joins
        $query = Transaction::where('transactions.business_id', $business_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->when($location_id, function ($q) use ($location_id) {
                return $q->where('transactions.location_id', $location_id);
            })
            ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                return $q->whereBetween('transactions.transaction_date', [$start_date, $end_date]);
            })
            ->select(
                DB::raw('DATE(transactions.transaction_date) as date'),
                DB::raw('SUM(transactions.final_total) as total_sale_amount'),
                DB::raw('COUNT(DISTINCT transactions.id) as total_invoice')
            )
            ->groupBy(DB::raw('DATE(transactions.transaction_date)'))
            ->orderBy('date', 'desc');

        $results = $query->get();

        // Calculate footer totals
        $footer_totals = $this->calculateFooterTotals($business_id, $location_id, $start_date, $end_date, $payment_methods);

        // Build final results with payment method totals
        $finalResults = [];
        
        foreach ($results as $result) {
            $row = [
                'date' => $result->date,
                'total_sale_amount' => $this->transactionUtil->num_f($result->total_sale_amount, true),
                'total_invoice' => $result->total_invoice,
            ];

            // Calculate due amount more efficiently
            $dueAmount = $this->calculateDueAmount($business_id, $location_id, $result->date);
            $row['due'] = $this->transactionUtil->num_f(max(0, $dueAmount), true);

            // Get payment totals for each enabled payment method
            if (!empty($payment_methods)) {
                $paymentTotals = $this->getPaymentTotals($business_id, $location_id, $result->date, $payment_methods);
                
                foreach ($payment_methods as $method) {
                    $total = $paymentTotals[$method] ?? 0;
                    $row[$method] = $this->transactionUtil->num_f($total, true);
                }
            }

            $finalResults[] = $row;
        }

        return DataTables::of(collect($finalResults))
            ->editColumn('date', function ($row) {
                return \Carbon\Carbon::parse($row['date'])->format('Y-m-d');
            })
            ->rawColumns(array_merge(['total_sale_amount', 'due'], $payment_methods))
            ->with([
                'payment_methods' => $payment_methods, 
                'payment_method_labels' => $payment_method_labels,
                'footer_totals' => $footer_totals
            ])
            ->make(true);
    }

    // Set default date range
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    return view('report.daily_sales_payment_report')
        ->with(compact('business_locations', 'payment_methods', 'location_id', 'date_range', 'payment_method_labels'));
}

/**
 * Calculate footer totals for all columns
 */
private function calculateFooterTotals($business_id, $location_id, $start_date, $end_date, $payment_methods)
{
    // Calculate totals for main columns
    $mainTotals = Transaction::where('transactions.business_id', $business_id)
        ->where('transactions.type', 'sell')
        ->where('transactions.status', 'final')
        ->when($location_id, function ($q) use ($location_id) {
            return $q->where('transactions.location_id', $location_id);
        })
        ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
            return $q->whereBetween('transactions.transaction_date', [$start_date, $end_date]);
        })
        ->selectRaw('
            SUM(transactions.final_total) as total_sale_amount,
            COUNT(DISTINCT transactions.id) as total_invoice
        ')
        ->first();

    // Calculate total due amount
    $totalDue = Transaction::where('transactions.business_id', $business_id)
        ->where('transactions.type', 'sell')
        ->where('transactions.status', 'final')
        ->when($location_id, function ($q) use ($location_id) {
            return $q->where('transactions.location_id', $location_id);
        })
        ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
            return $q->whereBetween('transactions.transaction_date', [$start_date, $end_date]);
        })
        ->sum(DB::raw('transactions.final_total - COALESCE((SELECT SUM(amount) FROM transaction_payments WHERE transaction_id = transactions.id), 0)'));

    // Calculate payment method totals
    $paymentTotals = [];
    if (!empty($payment_methods)) {
        $paymentQuery = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->when($location_id, function ($q) use ($location_id) {
                return $q->where('t.location_id', $location_id);
            })
            ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                return $q->whereBetween('t.transaction_date', [$start_date, $end_date]);
            })
            ->whereIn('tp.method', $payment_methods)
            ->select('tp.method', DB::raw('SUM(tp.amount) as total'))
            ->groupBy('tp.method')
            ->get();

        foreach ($paymentQuery as $result) {
            $paymentTotals[$result->method] = $result->total;
        }

        // Ensure all payment methods have a total (even if 0)
        foreach ($payment_methods as $method) {
            if (!isset($paymentTotals[$method])) {
                $paymentTotals[$method] = 0;
            }
        }
    }

    return [
        'total_sale_amount' => $mainTotals->total_sale_amount ?? 0,
        'total_invoice' => $mainTotals->total_invoice ?? 0,
        'due' => max(0, $totalDue),
        'payment_methods' => $paymentTotals
    ];
}

/**
 * Calculate due amount for a specific date
 */
private function calculateDueAmount($business_id, $location_id, $date)
{
    $query = Transaction::where('transactions.business_id', $business_id)
        ->where('transactions.type', 'sell')
        ->where('transactions.status', 'final')
        ->whereRaw('DATE(transactions.transaction_date) = ?', [$date])
        ->when($location_id, function ($q) use ($location_id) {
            return $q->where('transactions.location_id', $location_id);
        });

    return $query->sum(DB::raw('transactions.final_total - COALESCE((SELECT SUM(amount) FROM transaction_payments WHERE transaction_id = transactions.id), 0)'));
}

/**
 * Get payment totals for all methods in a single query
 */
private function getPaymentTotals($business_id, $location_id, $date, $payment_methods)
{
    $query = DB::table('transaction_payments as tp')
        ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
        ->where('t.business_id', $business_id)
        ->where('t.type', 'sell')
        ->where('t.status', 'final')
        ->whereRaw('DATE(t.transaction_date) = ?', [$date])
        ->when($location_id, function ($q) use ($location_id) {
            return $q->where('t.location_id', $location_id);
        })
        ->whereIn('tp.method', $payment_methods)
        ->select('tp.method', DB::raw('SUM(tp.amount) as total'))
        ->groupBy('tp.method');

    $results = $query->get();
    
    // Convert to associative array
    $totals = [];
    foreach ($results as $result) {
        $totals[$result->method] = $result->total;
    }
    
    return $totals;
}

public function SalesReportSummary(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $business_locations = BusinessLocation::forDropdown($business_id, true);
    // Get selected location and date range from request
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');
    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');
        // Return empty structure if filters not applied or missing required fields
        if (!$should_apply_filters || !$location_id || !$date_range) {
            return DataTables::of(collect([]))->make(true);
        }

        // Parse date range
        $start_date = null;
        $end_date = null;
        
        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;
                
                foreach ($date_formats as $format) {
                    try {
                        $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                        $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                if (!$parsed) {
                    \Log::error('Date parsing failed for range: ' . $date_range);
                    return response()->json(['error' => 'Invalid date format'], 400);
                }
            }
        }

        // Get merged sales and reward data with unit conversion
        $results = $this->getSalesReportSummaryData($business_id, $location_id, $start_date, $end_date);
        $total_sales_qty_formatted = $this->getFormattedTotalQuantity($results, 'sales_qty');
        $total_reward_out_qty_formatted = $this->getFormattedTotalQuantity($results, 'reward_out_qty');
        $payment_data = $this->getPaymentTotalsForDateRange($business_id, $location_id, $start_date, $end_date);

        return DataTables::of(collect($results))
            ->addColumn('raw_sales_qty', function($row) { // keep raw columns for JS amount calculation
                return $row['sales_qty'] ?? 0;
            })
            ->addColumn('raw_sales_amount', function($row) {
                return $row['sales_amount'] ?? 0;
            })
            ->addColumn('raw_reward_out_qty', function($row) {
                return $row['reward_out_qty'] ?? 0;
            })
            ->addColumn('raw_reward_out_amount', function($row) {
                return $row['reward_out_amount'] ?? 0;
            })
            ->editColumn('sales_qty', function ($row) {
                return $this->formatQuantityWithUnit($row['sales_qty'], $row['base_unit_multiplier'], $row['purchase_unit_name'], $row['base_unit_name']);
            })
            ->editColumn('sales_amount', function ($row) {
                return $row['sales_amount'] > 0 ? '$' . number_format($row['sales_amount'], 2) : '$0';
            })
            ->editColumn('reward_out_qty', function ($row) {
                return $this->formatQuantityWithUnit($row['reward_out_qty'], $row['base_unit_multiplier'], $row['purchase_unit_name'], $row['base_unit_name']);
            })
            ->editColumn('reward_out_amount', function ($row) {
                return $row['reward_out_amount'] > 0 ? '$' . number_format($row['reward_out_amount'], 2) : '$0';
            })
            ->rawColumns(['sales_qty', 'sales_amount', 'reward_out_qty', 'reward_out_amount'])
            ->with([
                'total_sales_qty_formatted' => $total_sales_qty_formatted,
                'total_reward_out_qty_formatted' => $total_reward_out_qty_formatted,
                'payment_totals' => $payment_data['totals'] ?? [],
                'payment_labels' => $payment_data['labels'] ?? [],
                'payment_methods' => $payment_data['methods'] ?? [],
                'due_amount' => $payment_data['due_amount'] ?? 0
            ])
            ->make(true);
    }

    // Set default date range to current month
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') .
        ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    return view('report.sales_report_summary')
        ->with(compact('business_locations', 'location_id', 'date_range'));
}

private function getPaymentTotalsForDateRange($business_id, $location_id, $start_date, $end_date)
{
    // Get enabled payment methods for the location
    $location = BusinessLocation::find($location_id);
    $payment_methods = [];
    
    if ($location && $location->default_payment_accounts) {
        $default_payment_accounts = json_decode($location->default_payment_accounts, true) ?? [];
        
        foreach ($default_payment_accounts as $method => $config) {
            if (isset($config['is_enabled']) && $config['is_enabled'] == '1') {
                $payment_methods[] = $method;
            }
        }
    }
    
    if (empty($payment_methods)) {
        return [
            'totals' => [],
            'labels' => [],
            'methods' => [],
            'due_amount' => 0
        ];
    }
    
    // Get payment totals
    $paymentQuery = DB::table('transaction_payments as tp')
        ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
        ->where('t.business_id', $business_id)
        ->where('t.location_id', $location_id)
        ->where('t.type', 'sell')
        ->where('t.status', 'final')
        ->whereNull('t.deleted_at')
        ->whereBetween('t.transaction_date', [$start_date, $end_date])
        ->whereIn('tp.method', $payment_methods)
        ->select('tp.method', DB::raw('SUM(tp.amount) as total'))
        ->groupBy('tp.method')
        ->get();
    
    $totals = [];
    foreach ($paymentQuery as $result) {
        $totals[$result->method] = $result->total;
    }
    
    // Calculate Due amount (same logic as Daily Sales Payment Report)
    $due_amount = DB::table('transactions')
        ->where('business_id', $business_id)
        ->where('location_id', $location_id)
        ->where('type', 'sell')
        ->where('status', 'final')
        ->whereNull('deleted_at')
        ->whereBetween('transaction_date', [$start_date, $end_date])
        ->sum(DB::raw('final_total - COALESCE((SELECT SUM(amount) FROM transaction_payments WHERE transaction_id = transactions.id), 0)'));
    
    // Get payment method labels
    $business = Business::where('id', $business_id)->first();
    $payment_labels = [];
    
    if ($business && $business->custom_labels) {
        $custom_labels = json_decode($business->custom_labels, true) ?? [];
        $payment_custom_labels = $custom_labels['payments'] ?? [];
        
        foreach ($payment_methods as $method) {
            if (strpos($method, 'custom_pay_') === 0) {
                $payment_labels[$method] = $payment_custom_labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
            } elseif ($method === 'cash_ring_percentage') {
                $payment_labels[$method] = 'Cash ring(%)';
            } else {
                $payment_labels[$method] = ucfirst(str_replace('_', ' ', $method));
            }
        }
    }
    
    return [
        'totals' => $totals,
        'labels' => $payment_labels,
        'methods' => $payment_methods,
        'due_amount' => max(0, $due_amount) // Ensure non-negative
    ];
}

private function getFormattedTotalQuantity($results, $column_name)
{
    if (empty($results)) {
        return '0';
    }

    $total_quantity = array_sum(array_column($results, $column_name));

    if ($total_quantity == 0) {
        return '0';
    }

    // Check if all products in the result set share the same unit configuration
    $first_item = $results[0];
    $base_multiplier = $first_item['base_unit_multiplier'];
    $purchase_unit = $first_item['purchase_unit_name'];
    $base_unit = $first_item['base_unit_name'];
    $is_consistent = true;

    foreach ($results as $item) {
        // Skip items with no quantity for this check
        if ($item[$column_name] == 0) {
            continue;
        }
        if ($item['base_unit_multiplier'] != $base_multiplier ||
            $item['purchase_unit_name'] != $purchase_unit ||
            $item['base_unit_name'] != $base_unit) {
            $is_consistent = false;
            break;
        }
    }

    // If units are consistent across all products, format the total using the existing function
    if ($is_consistent) {
        return $this->formatQuantityWithUnit($total_quantity, $base_multiplier, $purchase_unit, $base_unit);
    } else {
        // Otherwise, return the raw total sum, as units are mixed and cannot be formatted consistently.
        return number_format($total_quantity);
    }
}

private function getSalesReportSummaryData($business_id, $location_id, $start_date, $end_date)
{
    try {
        // Get all products (same as Stock Movement)
        $products = DB::table('products')
            ->where('business_id', $business_id)
            ->where('type', '!=', 'combo')
            ->where('enable_stock', 1)
            ->pluck('id');

        $results = [];
        
        foreach ($products as $product_id) {
            // Get sales quantity from sell transactions
            $sales_qty = $this->getSalesQty($business_id, $location_id, $product_id, $start_date, $end_date);
            
            // Get sales amount from parent sell lines
            $sales_amount = $this->getSalesAmount($business_id, $location_id, $product_id, $start_date, $end_date);
            
            // Get reward out quantity from stock_reward_exchange_new
            $reward_out_qty = $this->getRewardOutQty($business_id, $location_id, $product_id, $start_date, $end_date);
            
            // Get reward out amount from rewards_exchange and transaction_sell_lines
            $reward_out_amount = $this->getRewardOutAmount($business_id, $location_id, $product_id, $start_date, $end_date);
            
            // Get product details including unit information
            $product_details = $this->getProductDetails($product_id, $location_id);
            
            // Only include if has activity
            if ($sales_qty > 0 || $reward_out_qty > 0) {
                $results[] = [
                    'product_name' => $product_details['product_name'],
                    'sales_qty' => $sales_qty,
                    'sales_amount' => $sales_amount,
                    'reward_out_qty' => $reward_out_qty,
                    'reward_out_amount' => $reward_out_amount,
                    'base_unit_name' => $product_details['base_unit_name'],
                    'purchase_unit_name' => $product_details['purchase_unit_name'],
                    'base_unit_multiplier' => $product_details['base_unit_multiplier']
                ];
            }
        }

        // Sort by product name
        usort($results, function($a, $b) {
            return strcmp($a['product_name'], $b['product_name']);
        });

        return $results;

    } catch (\Exception $e) {
        \Log::error('Error in getSalesReportSummaryData: ' . $e->getMessage());
        return [];
    }
}

private function getSalesQty($business_id, $location_id, $product_id, $start_date, $end_date)
{
    return DB::table('transactions')
        ->join('transaction_sell_lines', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
        ->where('transactions.business_id', $business_id)
        ->where('transactions.location_id', $location_id)
        ->where('transactions.type', 'sell')
        ->where('transactions.status', 'final')
        ->where('transaction_sell_lines.product_id', $product_id)
        ->whereNull('transactions.deleted_at')
        ->whereBetween('transactions.transaction_date', [$start_date, $end_date])
        ->sum('transaction_sell_lines.quantity') ?? 0;
}

private function getSalesAmount($business_id, $location_id, $product_id, $start_date, $end_date)
{
    return DB::table('transactions')
        ->join('transaction_sell_lines as child', 'transactions.id', '=', 'child.transaction_id')
        ->leftJoin('transaction_sell_lines as parent', 'child.parent_sell_line_id', '=', 'parent.id')
        ->where('transactions.business_id', $business_id)
        ->where('transactions.location_id', $location_id)
        ->where('transactions.type', 'sell')
        ->where('transactions.status', 'final')
        ->where('child.product_id', $product_id)
        ->whereNull('transactions.deleted_at')
        ->whereBetween('transactions.transaction_date', [$start_date, $end_date])
        ->sum(DB::raw('
            CASE 
                WHEN child.parent_sell_line_id IS NOT NULL 
                THEN parent.unit_price * parent.quantity 
                ELSE child.unit_price * child.quantity 
            END
        ')) ?? 0;
}

private function getRewardOutAmount($business_id, $location_id, $product_id, $start_date, $end_date)
{
    // Get reward transactions for this product
    $reward_transactions = DB::table('transactions')
        ->join('transactions_ring_balance', 'transactions.id', '=', 'transactions_ring_balance.transaction_id')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->where('transactions.business_id', $business_id)
        ->where('transactions.location_id', $location_id)
        ->where('transactions.type', 'reward_exchange')
        ->where('transactions_ring_balance.type', 'reward_out')
        ->where('transaction_sell_ring_balance.product_id', $product_id)
        ->whereNull('transaction_sell_ring_balance.cash_ring')
        ->whereNull('transactions.deleted_at')
        ->whereNull('transactions_ring_balance.deleted_at')
        ->whereBetween('transactions.transaction_date', [$start_date, $end_date])
        ->select('transactions.ref_sale_invoice', 'transaction_sell_ring_balance.quantity')
        ->get();

    $total_amount = 0;

    foreach ($reward_transactions as $reward_tx) {
        if ($reward_tx->ref_sale_invoice) {
            // Get the product_for_sale for this exchange_product
            $product_for_sale = DB::table('rewards_exchange')
                ->where('business_id', $business_id)
                ->where('exchange_product', $product_id)
                ->value('product_for_sale');

            if ($product_for_sale) {
                // Find the original sell transaction amount using ref_sale_invoice
                $original_sell_amount = DB::table('transactions')
                    ->join('transaction_sell_lines', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
                    ->where('transactions.invoice_no', $reward_tx->ref_sale_invoice)
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'sell') 
                    ->where('transaction_sell_lines.product_id', $product_for_sale)
                    ->whereNull('transactions.deleted_at')
                    ->sum(DB::raw('transaction_sell_lines.unit_price * transaction_sell_lines.quantity'));
                
                $total_amount += ($original_sell_amount ?? 0);
            }
        }
    }

    return $total_amount;
}

private function getRewardOutQty($business_id, $location_id, $product_id, $start_date, $end_date)
{
    // Get the receive_product from rewards_exchange table (formula table)
    $receive_product = DB::table('rewards_exchange')
        ->where('business_id', $business_id)
        ->where('exchange_product', $product_id)
        ->value('receive_product');

    if (!$receive_product) {
        return 0;
    }

    // Get reward_exchange transactions for this location and date range
    $transaction_ids = DB::table('transactions')
        ->where('business_id', $business_id)
        ->where('location_id', $location_id)
        ->where('type', 'reward_exchange')
        ->whereNull('deleted_at')
        ->whereBetween('transaction_date', [$start_date, $end_date])
        ->pluck('id');

    if ($transaction_ids->isEmpty()) {
        return 0;
    }

    // Get the positive quantity from stock_reward_exchange_new for these transactions
    $total_quantity = DB::table('stock_reward_exchange_new')
        ->whereIn('transaction_id', $transaction_ids)
        ->where('product_id', $receive_product)
        ->sum(DB::raw('ABS(quantity)')); // Convert negative to positive

    return $total_quantity ?? 0;
}

// NEW METHOD: Get product details including unit information (like Stock Movement)
private function getProductDetails($product_id, $location_id)
{
    $query = DB::table('products as p')
        ->leftJoin('units as u1', 'p.unit_id', '=', 'u1.id')
        ->leftJoin('units as u2', 'p.purchase_unit', '=', 'u2.id')
        ->where('p.id', $product_id);

    $result = $query->select(
        'p.name as product_name',
        'u1.actual_name as base_unit_name',
        'u2.actual_name as purchase_unit_name',
        DB::raw('COALESCE((SELECT u.base_unit_multiplier FROM units u WHERE u.id = p.purchase_unit AND u.base_unit_id = p.unit_id), 1) as base_unit_multiplier')
    )->first();

    return $result ? [
        'product_name' => $result->product_name,
        'base_unit_name' => $result->base_unit_name ?? '',
        'purchase_unit_name' => $result->purchase_unit_name ?? '',
        'base_unit_multiplier' => $result->base_unit_multiplier
    ] : [
        'product_name' => 'Unknown Product',
        'base_unit_name' => '',
        'purchase_unit_name' => '',
        'base_unit_multiplier' => 1
    ];
}

// NEW METHOD: Format quantity with units (same as Stock Movement)
private function formatQuantityWithUnit($quantity, $base_unit_multiplier, $purchase_unit_name, $base_unit_name)
{
    if ($quantity == 0) {
        return '0';
    }

    // If no units defined, just show number
    if (empty($purchase_unit_name) && empty($base_unit_name)) {
        return number_format($quantity);
    }

    // If base_unit_multiplier is 1 or not set, show simple format
    if ($base_unit_multiplier <= 1) {
        $unit_name = $purchase_unit_name ?: $base_unit_name ?: 'units';
        return number_format($quantity) . ' ' . $unit_name;
    }

    // Calculate major and minor units (like Stock Movement)
    $major_units = floor($quantity / $base_unit_multiplier);
    $minor_units = $quantity % $base_unit_multiplier;

    $result = '';
    
    if ($major_units > 0) {
        $result = number_format($major_units) . ' ' . ($purchase_unit_name ?: 'Case24');
    }
    
    if ($minor_units > 0) {
        if ($result) {
            $result .= ' ';
        }
        $result .= number_format($minor_units) . ' ' . ($base_unit_name ?: 'Can');
    }

    return $result ?: '0';
}

    public function DailyPaymentReport(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        // Get business locations for dropdown
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        // Get selected location and date range from request
        $location_id = $request->input('sell_list_filter_location_id');
        $date_range = $request->input('sell_list_filter_date_range');

        // Initialize payment methods arrays
        $payment_methods = [];
        $payment_method_labels = [];

        // Only fetch payment methods if location is provided
        if ($location_id) {
            $location = BusinessLocation::find($location_id);
            if ($location && $location->default_payment_accounts) {
                $default_payment_accounts = json_decode($location->default_payment_accounts, true) ?? [];
                
                // Get enabled payment methods
                $enabled_methods = [];
                foreach ($default_payment_accounts as $method => $config) {
                    if (isset($config['is_enabled']) && $config['is_enabled'] == '1') {
                        $enabled_methods[] = $method;
                    }
                }

                // Fetch custom labels from business table
                $business = Business::where('id', $business_id)->first();
                $custom_labels = [];
                $payment_custom_labels = [];
                
                if ($business && $business->custom_labels) {
                    $custom_labels = json_decode($business->custom_labels, true) ?? [];
                    $payment_custom_labels = $custom_labels['payments'] ?? [];
                    
                    // Debug: Log the custom labels being retrieved
                    \Log::info('Custom labels retrieved:', [
                        'business_id' => $business_id,
                        'custom_labels' => $custom_labels,
                        'payment_custom_labels' => $payment_custom_labels
                    ]);
                }

                // Map enabled methods with custom labels
                foreach ($enabled_methods as $method) {
                    $payment_methods[] = $method;
                    
                    // Handle custom payment methods
                    if (strpos($method, 'custom_pay_') === 0) {
                        $custom_label = $payment_custom_labels[$method] ?? null;
                        
                        // Debug: Log the label mapping
                        \Log::info('Mapping custom payment method:', [
                            'method' => $method,
                            'custom_label' => $custom_label,
                            'payment_custom_labels' => $payment_custom_labels
                        ]);
                        
                        if ($custom_label) {
                            $payment_method_labels[$method] = $custom_label;
                        } else {
                            $payment_method_labels[$method] = ucfirst(str_replace('_', ' ', $method));
                        }
                    } else {
                        // Handle specific payment method: cash_ring_percentage
                        if ($method === 'cash_ring_percentage') {
                            $payment_method_labels[$method] = 'Cash ring(%)';
                        } else {
                            // Handle other standard payment methods with better formatting
                            $payment_method_labels[$method] = ucfirst(str_replace('_', ' ', $method));
                        }
                    }
                }
            }
        }

        // Handle AJAX requests
        if ($request->ajax() || $request->input('ajax')) {
            $apply_filters = $request->input('apply_filters');
            $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');

            // Return empty structure if filters not applied
            if (!$should_apply_filters) {
                return DataTables::of(collect([]))
                    ->with([
                        'payment_methods' => $payment_methods, 
                        'payment_method_labels' => $payment_method_labels,
                        'footer_totals' => []
                    ])
                    ->make(true);
            }

            // Validate required parameters
            if (!$location_id) {
                return response()->json(['error' => 'Location is required'], 400);
            }

            if (!$date_range) {
                return response()->json(['error' => 'Date range is required'], 400);
            }

            // Parse date range with improved error handling
            $start_date = null;
            $end_date = null;
            
            if ($date_range) {
                $dates = explode(' ~ ', $date_range);
                if (count($dates) == 2) {
                    $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                    $parsed = false;
                    
                    foreach ($date_formats as $format) {
                        try {
                            $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                            $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                            $parsed = true;
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                    
                    if (!$parsed) {
                        \Log::error('Date parsing failed for range: ' . $date_range);
                        return response()->json(['error' => 'Invalid date format'], 400);
                    }
                }
            }

            // UPDATED: Modified query to use amount column for all payment methods including cash_ring_percentage
            $query = DB::table('transaction_payments as tp')
                ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereNotNull('tp.paid_on')
                ->when($location_id, function ($q) use ($location_id) {
                    return $q->where('t.location_id', $location_id);
                })
                ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                    return $q->whereBetween('tp.paid_on', [$start_date, $end_date]);
                })
                ->select(
                    DB::raw('DATE(tp.paid_on) as date'),
                    // UPDATED: Use amount column for all payment methods
                    DB::raw('(
                        SUM(CASE WHEN tp.is_return = 0 THEN tp.amount ELSE 0 END) - 
                        SUM(CASE WHEN tp.is_return = 1 THEN tp.amount ELSE 0 END)
                    ) as total_paid'),
                    // Count distinct transaction IDs
                    DB::raw('COUNT(DISTINCT tp.transaction_id) as total_invoice'),
                    // UPDATED: Use amount column for backdated calculations
                    DB::raw('(
                        SUM(CASE 
                            WHEN tp.is_return = 0 AND DATE(tp.paid_on) != DATE(t.transaction_date) THEN tp.amount
                            ELSE 0 
                        END) - 
                        SUM(CASE 
                            WHEN tp.is_return = 1 AND DATE(tp.paid_on) != DATE(t.transaction_date) THEN tp.amount
                            ELSE 0 
                        END)
                    ) as backdated_paid')
                )
                ->groupBy(DB::raw('DATE(tp.paid_on)'))
                ->orderBy('date', 'desc');

            $results = $query->get();

            // Calculate footer totals using the separate method
            $footer_totals = $this->calculatePaymentFooterTotals($business_id, $location_id, $start_date, $end_date, $payment_methods);

            // Build final results with payment method totals
            $finalResults = [];
            
            foreach ($results as $result) {
                $row = [
                    'date' => $result->date,
                    'total_paid' => $this->transactionUtil->num_f($result->total_paid, true),
                    'total_invoice' => $result->total_invoice,
                    'backdated_paid' => $this->transactionUtil->num_f($result->backdated_paid, true),
                ];

                // Get payment totals for each enabled payment method on this date
                if (!empty($payment_methods)) {
                    $paymentTotals = $this->getPaymentTotalsByPaidOn($business_id, $location_id, $result->date, $payment_methods);
                    
                    foreach ($payment_methods as $method) {
                        $total = $paymentTotals[$method] ?? 0;
                        $row[$method] = $this->transactionUtil->num_f($total, true);
                    }
                }

                $finalResults[] = $row;
            }

            return DataTables::of(collect($finalResults))
                ->editColumn('date', function ($row) {
                    return \Carbon\Carbon::parse($row['date'])->format('Y-m-d');
                })
                ->rawColumns(array_merge(['total_paid', 'backdated_paid'], $payment_methods))
                ->with([
                    'payment_methods' => $payment_methods, 
                    'payment_method_labels' => $payment_method_labels,
                    'footer_totals' => $footer_totals
                ])
                ->make(true);
        }

        // Set default date range
        if (!$date_range) {
            $date_range = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
        }

        return view('report.daily_payment_report')
            ->with(compact('business_locations', 'payment_methods', 'location_id', 'date_range', 'payment_method_labels'));
    }

    /**
     * Calculate footer totals for Daily Payment Report (OPTIMIZED VERSION)
     * Uses a single query to get all totals including all payment methods
     */
    private function calculatePaymentFooterTotals($business_id, $location_id, $start_date, $end_date, $payment_methods)
    {
        // Build the select fields for main totals
        $selectFields = [
            DB::raw('(SUM(CASE WHEN tp.is_return = 0 THEN tp.amount ELSE 0 END) - 
                     SUM(CASE WHEN tp.is_return = 1 THEN tp.amount ELSE 0 END)) as total_paid'),
            DB::raw('COUNT(DISTINCT tp.transaction_id) as total_invoice'),
            DB::raw('(SUM(CASE 
                        WHEN tp.is_return = 0 AND DATE(tp.paid_on) != DATE(t.transaction_date) THEN tp.amount
                        ELSE 0 
                    END) - 
                    SUM(CASE 
                        WHEN tp.is_return = 1 AND DATE(tp.paid_on) != DATE(t.transaction_date) THEN tp.amount
                        ELSE 0 
                    END)) as backdated_paid')
        ];

        // Add payment method totals to the same query (all in ONE database call)
        if (!empty($payment_methods)) {
            foreach ($payment_methods as $method) {
                // Sanitize method name for use as column alias
                $methodSafe = str_replace(['-', '.', ' '], '_', $method);
                $selectFields[] = DB::raw("SUM(CASE 
                    WHEN tp.method = " . DB::getPdo()->quote($method) . " AND tp.is_return = 0 THEN tp.amount 
                    WHEN tp.method = " . DB::getPdo()->quote($method) . " AND tp.is_return = 1 THEN -tp.amount 
                    ELSE 0 
                END) as method_" . $methodSafe);
            }
        }

        // Execute single query to get all totals
        $totals = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNotNull('tp.paid_on')
            ->when($location_id, function ($q) use ($location_id) {
                return $q->where('t.location_id', $location_id);
            })
            ->when($start_date && $end_date, function ($q) use ($start_date, $end_date) {
                return $q->whereBetween('tp.paid_on', [$start_date, $end_date]);
            })
            ->select($selectFields)
            ->first();

        // Extract payment method totals from the result
        $paymentTotals = [];
        if (!empty($payment_methods) && $totals) {
            foreach ($payment_methods as $method) {
                $methodSafe = 'method_' . str_replace(['-', '.', ' '], '_', $method);
                $paymentTotals[$method] = $totals->$methodSafe ?? 0;
            }
        }

        return [
            'total_paid' => $totals->total_paid ?? 0,
            'total_invoice' => $totals->total_invoice ?? 0,
            'backdated_paid' => $totals->backdated_paid ?? 0,
            'payment_methods' => $paymentTotals
        ];
    }

    public function DailyRingTopUp(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    $business_locations = BusinessLocation::forDropdown($business_id, true);

    // Get selected location, date range, and tab from request
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');
    $active_tab = $request->input('active_tab', 'top_up'); // Default to 'top_up', can be 'ring_report' or 'cash_ring_report'

    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');

        // Return empty structure if filters not applied or missing required fields
        if (!$should_apply_filters || !$location_id || !$date_range) {
            return DataTables::of(collect([]))
                ->with([
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'totalTransactionSum' => 0
                ])
                ->make(true);
        }

        // Parse date range with improved error handling
        $start_date = null;
        $end_date = null;
        
        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;
                
                foreach ($date_formats as $format) {
                    try {
                        $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                        $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                if (!$parsed) {
                    \Log::error('Date parsing failed for range: ' . $date_range);
                    return response()->json(['error' => 'Invalid date format'], 400);
                }
            }
        }

        // Handle different tabs
        if ($active_tab === 'ring_report') {
            // Fetch ring report data
            $results = $this->getRingReportData($business_id, $location_id, $start_date, $end_date);
            
            return DataTables::of(collect($results))
                ->editColumn('Beginning Stock (Warehouse)', function ($row) {
                    return $row['Beginning Stock (Warehouse)'];
                })
                ->editColumn('Ring In', function ($row) {
                    return $row['Ring In'];
                })
                ->editColumn('Send to Factory', function ($row) {
                    return $row['Send to Factory'];
                })
                ->editColumn('IN-Warehouse', function ($row) {
                    return $row['IN-Warehouse'];
                })
                ->editColumn('Open Ring(Supplier)', function ($row) {
                    return $row['Open Ring(Supplier)'];
                })
                ->editColumn('Total Ring at Factory', function ($row) {
                    return $row['Total Ring at Factory'];
                })
                ->with([
                    'recordsTotal' => count($results),
                    'recordsFiltered' => count($results)
                ])
                ->rawColumns(['Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'])
                ->make(true);
        } elseif ($active_tab === 'cash_ring_report') {
            // NEW: Fetch cash ring report data
            $results = $this->getCashRingReportData($business_id, $location_id, $start_date, $end_date);
            
            return DataTables::of(collect($results))
                ->editColumn('Beginning Stock (Warehouse)', function ($row) {
                    return $row['Beginning Stock (Warehouse)'];
                })
                ->editColumn('Ring In', function ($row) {
                    return $row['Ring In'];
                })
                ->editColumn('Send to Factory', function ($row) {
                    return $row['Send to Factory'];
                })
                ->editColumn('IN-Warehouse', function ($row) {
                    return $row['IN-Warehouse'];
                })
                ->editColumn('Open Ring(Supplier)', function ($row) {
                    return $row['Open Ring(Supplier)'];
                })
                ->editColumn('Total Ring at Factory', function ($row) {
                    return $row['Total Ring at Factory'];
                })
                ->with([
                    'recordsTotal' => count($results),
                    'recordsFiltered' => count($results)
                ])
                ->rawColumns(['Beginning Stock (Warehouse)', 'Ring In', 'Send to Factory', 'IN-Warehouse', 'Open Ring(Supplier)', 'Total Ring at Factory'])
                ->make(true);
        } else {
            // Original Daily Ring Top Up logic
            // Get total transaction count from transactions_ring_balance table
            $totalTransactionSum = DB::table('transactions_ring_balance as trb')
                ->where('trb.business_id', $business_id)
                ->where('trb.location_id', $location_id)
                ->where('trb.type', 'top_up_ring_balance')
                ->whereBetween('trb.transaction_date', [$start_date, $end_date])
                ->whereNull('trb.deleted_by')
                ->whereNull('trb.deleted_at')
                ->count();

            // Fetch ring top-up data
            $results = $this->getRingTopUpData($business_id, $location_id, $start_date, $end_date);

            return DataTables::of(collect($results))
                ->editColumn('total_quantity', function ($row) {
                    return $row['total_quantity'];
                })
                ->with([
                    'totalTransactionSum' => $totalTransactionSum
                ])
                ->rawColumns(['unit', 'total_quantity'])
                ->make(true);
        }
    }

    // Set default date range
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    return view('report.daily_ring_top_up')
        ->with(compact('business_locations', 'location_id', 'date_range', 'active_tab'));
}

private function getCashRingReportData($business_id, $location_id, $start_date, $end_date)
{
    try {
        // Get all cash ring products from cash_ring_balance (grouped by product_id to avoid duplicates)
        $cash_rings = DB::table('cash_ring_balance')
            ->join('products', 'cash_ring_balance.product_id', '=', 'products.id')
            ->where('cash_ring_balance.business_id', $business_id)
            ->select('products.id as product_id', 'products.name as ring_name')
            ->groupBy('products.id', 'products.name')
            ->get();

        $cash_ring_data = [];

        foreach ($cash_rings as $ring) {
            // Calculate Beginning Stock (previous day's ending balance)
            $day_before_start = $start_date->copy()->subDay()->endOfDay();
            
            // Get cumulative Cash Ring In up to day before start date (grouped by unit_value)
            $cash_ring_in_before_details = DB::table('transactions_ring_balance')
                ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
                ->join('transaction_cash_ring_balance', 'transaction_sell_ring_balance.id', '=', 'transaction_cash_ring_balance.transaction_sell_ring_balance_id')
                ->join('cash_ring_balance', 'transaction_cash_ring_balance.cash_ring_balance_id', '=', 'cash_ring_balance.id')
                ->where('transactions_ring_balance.business_id', $business_id)
                ->where('transactions_ring_balance.location_id', $location_id)
                ->whereIn('transactions_ring_balance.type', ['top_up_ring_balance', 'adjustment'])
                ->where('transactions_ring_balance.status', 'completed')
                ->where('transaction_sell_ring_balance.product_id', $ring->product_id)
                ->where('transaction_sell_ring_balance.cash_ring', 1)
                ->where('transactions_ring_balance.transaction_date', '<=', $day_before_start)
                ->whereNull('transactions_ring_balance.deleted_at')
                ->select(
                    'cash_ring_balance.type_currency',
                    'cash_ring_balance.unit_value',
                    DB::raw('SUM(transaction_cash_ring_balance.quantity) as total_qty')
                )
                ->groupBy('cash_ring_balance.type_currency', 'cash_ring_balance.unit_value')
                ->get();

            // Get cumulative Send to Factory up to day before start date (grouped by unit_value)
            $send_to_factory_before_details = DB::table('transactions_supplier_cash_ring as tscr')
                ->join('transactions_supplier_cash_ring_detail as tscrd', 'tscr.id', '=', 'tscrd.transactions_supplier_cash_ring_id')
                ->join('cash_ring_balance as crb', 'tscrd.cash_ring_balance_id', '=', 'crb.id')
                ->where('tscr.business_id', $business_id)
                ->where('tscr.location_id', $location_id)
                ->where('tscr.type', 'supplier_cash_ring')
                ->whereIn('tscr.status', ['send', 'claim'])
                ->where('tscrd.product_id', $ring->product_id)
                ->where('tscr.transaction_date', '<=', $day_before_start)
                ->select(
                    'crb.type_currency',
                    'crb.unit_value',
                    DB::raw('SUM(tscrd.quantity) as total_qty')
                )
                ->groupBy('crb.type_currency', 'crb.unit_value')
                ->get();

            // Cash Ring In - within date range (grouped by unit_value)
            $cash_ring_in_details = DB::table('transactions_ring_balance')
                ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
                ->join('transaction_cash_ring_balance', 'transaction_sell_ring_balance.id', '=', 'transaction_cash_ring_balance.transaction_sell_ring_balance_id')
                ->join('cash_ring_balance', 'transaction_cash_ring_balance.cash_ring_balance_id', '=', 'cash_ring_balance.id')
                ->where('transactions_ring_balance.business_id', $business_id)
                ->where('transactions_ring_balance.location_id', $location_id)
                ->whereIn('transactions_ring_balance.type', ['top_up_ring_balance', 'adjustment'])
                ->where('transactions_ring_balance.status', 'completed')
                ->where('transaction_sell_ring_balance.product_id', $ring->product_id)
                ->where('transaction_sell_ring_balance.cash_ring', 1)
                ->whereBetween('transactions_ring_balance.transaction_date', [$start_date, $end_date])
                ->whereNull('transactions_ring_balance.deleted_at')
                ->select(
                    'cash_ring_balance.type_currency',
                    'cash_ring_balance.unit_value',
                    DB::raw('SUM(transaction_cash_ring_balance.quantity) as total_qty')
                )
                ->groupBy('cash_ring_balance.type_currency', 'cash_ring_balance.unit_value')
                ->get();

            // Send to Factory - within date range (grouped by unit_value)
            $send_to_factory_details = DB::table('transactions_supplier_cash_ring as tscr')
                ->join('transactions_supplier_cash_ring_detail as tscrd', 'tscr.id', '=', 'tscrd.transactions_supplier_cash_ring_id')
                ->join('cash_ring_balance as crb', 'tscrd.cash_ring_balance_id', '=', 'crb.id')
                ->where('tscr.business_id', $business_id)
                ->where('tscr.location_id', $location_id)
                ->where('tscr.type', 'supplier_cash_ring')
                ->whereIn('tscr.status', ['send', 'claim'])
                ->where('tscrd.product_id', $ring->product_id)
                ->whereBetween('tscr.transaction_date', [$start_date, $end_date])
                ->select(
                    'crb.type_currency',
                    'crb.unit_value',
                    DB::raw('SUM(tscrd.quantity) as total_qty')
                )
                ->groupBy('crb.type_currency', 'crb.unit_value')
                ->get();

            // Open Ring (Supplier) - within date range (grouped by unit_value)
            $open_ring_supplier_details = DB::table('transactions_supplier_cash_ring as tscr')
                ->join('transactions_supplier_cash_ring_detail as tscrd', 'tscr.id', '=', 'tscrd.transactions_supplier_cash_ring_id')
                ->join('cash_ring_balance as crb', 'tscrd.cash_ring_balance_id', '=', 'crb.id')
                ->where('tscr.business_id', $business_id)
                ->where('tscr.location_id', $location_id)
                ->where('tscr.type', 'supplier_cash_ring')
                ->where('tscr.status', 'claim')
                ->where('tscrd.product_id', $ring->product_id)
                ->whereBetween('tscr.transaction_date', [$start_date, $end_date])
                ->select(
                    'crb.type_currency',
                    'crb.unit_value',
                    DB::raw('SUM(tscrd.quantity) as total_qty')
                )
                ->groupBy('crb.type_currency', 'crb.unit_value')
                ->get();

            // Helper function to format unit details
            $formatUnitDetails = function($details) {
                $unitDetails = [];
                
                foreach ($details as $detail) {
                    $unit_value_display = $detail->unit_value == floor($detail->unit_value) ? 
                        number_format($detail->unit_value, 0) : 
                        rtrim(rtrim(number_format($detail->unit_value, 2), '0'), '.');
                    $currency_symbol = $detail->type_currency == 1 ? '$' : '៛';
                    $unitDetail = $unit_value_display . $currency_symbol . ' = ' . $detail->total_qty;
                    $unitDetails[] = $unitDetail;
                }
                
                return implode('<br>', $unitDetails);
            };

            // Helper function to calculate net unit details (for Beginning Stock and IN-Warehouse)
            $calculateNetUnits = function($add_details, $subtract_details) use ($formatUnitDetails) {
                // Combine all units and calculate net quantities
                $netUnits = [];
                
                // Add positive quantities
                foreach ($add_details as $detail) {
                    $key = $detail->type_currency . '_' . $detail->unit_value;
                    if (!isset($netUnits[$key])) {
                        $netUnits[$key] = [
                            'type_currency' => $detail->type_currency,
                            'unit_value' => $detail->unit_value,
                            'total_qty' => 0
                        ];
                    }
                    $netUnits[$key]['total_qty'] += $detail->total_qty;
                }
                
                // Subtract negative quantities
                foreach ($subtract_details as $detail) {
                    $key = $detail->type_currency . '_' . $detail->unit_value;
                    if (!isset($netUnits[$key])) {
                        $netUnits[$key] = [
                            'type_currency' => $detail->type_currency,
                            'unit_value' => $detail->unit_value,
                            'total_qty' => 0
                        ];
                    }
                    $netUnits[$key]['total_qty'] -= $detail->total_qty;
                }
                
                // Filter out zero quantities and convert to object format
                $filteredUnits = [];
                foreach ($netUnits as $unit) {
                    if ($unit['total_qty'] != 0) {
                        $filteredUnits[] = (object) $unit;
                    }
                }
                
                return $formatUnitDetails($filteredUnits);
            };

            // Calculate Beginning Stock units (Ring In Before - Send Before)
            $beginning_stock_display = $calculateNetUnits($cash_ring_in_before_details, $send_to_factory_before_details);
            
            // Calculate IN-Warehouse units (Beginning + Ring In - Send to Factory)
            $combined_positive = array_merge($cash_ring_in_before_details->toArray(), $cash_ring_in_details->toArray());
            $combined_negative = array_merge($send_to_factory_before_details->toArray(), $send_to_factory_details->toArray());
            $in_warehouse_display = $calculateNetUnits($combined_positive, $combined_negative);
            
            // Calculate Total Ring at Factory units (Send to Factory - Open Ring)
            $total_ring_at_factory_display = $calculateNetUnits($send_to_factory_details, $open_ring_supplier_details);

            $cash_ring_data[] = [
                'Ring Name' => $ring->ring_name,
                'Beginning Stock (Warehouse)' => !empty($beginning_stock_display) ? $beginning_stock_display : '-',
                'Ring In' => !empty($cash_ring_in_details) ? $formatUnitDetails($cash_ring_in_details) : '-',
                'Send to Factory' => !empty($send_to_factory_details) ? $formatUnitDetails($send_to_factory_details) : '-',
                'IN-Warehouse' => !empty($in_warehouse_display) ? $in_warehouse_display : '-',
                'Open Ring(Supplier)' => !empty($open_ring_supplier_details) ? $formatUnitDetails($open_ring_supplier_details) : '-',
                'Total Ring at Factory' => !empty($total_ring_at_factory_display) ? $total_ring_at_factory_display : '-'
            ];
        }

        return $cash_ring_data;

    } catch (\Exception $e) {
        \Log::error('Error in getCashRingReportData: ' . $e->getMessage());
        return [];
    }
}

private function getRingReportData($business_id, $location_id, $start_date, $end_date)
{
    try {
        $cutoff_date = \Carbon\Carbon::create(2025, 6, 29, 23, 59, 59);
        $july_first  = \Carbon\Carbon::create(2025, 7, 1, 0, 0, 0);

        // Get all ring products from rewards_exchange (distinct products only)
        $rings = DB::table('rewards_exchange')
            ->join('products', 'rewards_exchange.exchange_product', '=', 'products.id')
            ->where('rewards_exchange.business_id', $business_id)
            ->where('rewards_exchange.type', 'customers')
            ->whereNull('rewards_exchange.deleted_at')
            ->select('products.id as product_id', 'products.name as ring_name')
            ->groupBy('products.id', 'products.name')
            ->get();

        // Fetch ALL data once (bulk fetch instead of loop queries)
        $all_data = $this->fetchAllReportData(
            $business_id,
            $location_id,
            $rings,
            $start_date,
            $end_date,
            $cutoff_date
        );

        $ring_data = [];

        foreach ($rings as $ring) {
            $product_id = $ring->product_id;

            // Get cached IN-Warehouse values
            $in_warehouse_cache = $all_data['warehouse_cache'][$product_id] ?? [];

            // Get cached Factory values
            $factory_cache = $all_data['factory_cache'][$product_id] ?? [];

            // Opening stock info
            $opening_stock          = $all_data['opening_stock'][$product_id]['range']   ?? 0;
            $opening_stock_special  = $all_data['opening_stock'][$product_id]['special'] ?? 0;
            $opening_stock_before   = $all_data['opening_stock'][$product_id]['before']  ?? 0;

            $filter_includes_day_one = ($start_date <= $july_first && $end_date >= $july_first);

            // BEGINNING STOCK (same logic as old code)
            $day_before_start     = $start_date->copy()->subDay();
            $day_before_start_key = $day_before_start->format('Y-m-d');

            $beginning_stock = 0;
            if (isset($in_warehouse_cache[$day_before_start_key])) {
                $beginning_stock = $in_warehouse_cache[$day_before_start_key];
            } elseif ($day_before_start > $cutoff_date) {
                $beginning_stock = 0;
            } else {
                $beginning_stock = $opening_stock_before;
                if ($filter_includes_day_one) {
                    $beginning_stock += $opening_stock_special;
                }
            }

            // Preloaded totals
            $ring_in         = $all_data['ring_in'][$product_id]          ?? 0;
            $ring_in_supplier = $all_data['ring_in_supplier'][$product_id] ?? 0; // used inside factory cache
            $send_to_factory = $all_data['send_to_factory'][$product_id]  ?? 0;
            $display_send_to_factory = $all_data['display_send_to_factory'][$product_id] ?? 0;
            $open_ring_supplier = $all_data['open_ring_supplier'][$product_id] ?? 0;

            // Total Ring at Factory from cache (end date)
            $end_date_key          = $end_date->format('Y-m-d');
            $total_ring_at_factory = $factory_cache[$end_date_key] ?? 0;

            // IN-Warehouse (same formula as old code)
            $in_warehouse = $beginning_stock + $ring_in - $send_to_factory + $opening_stock;
            if ($filter_includes_day_one) {
                $in_warehouse += $opening_stock_special;
            }

            // Beginning Stock (Factory) = previous day's total + today's send_to_factory
            $day_before_start_key          = $start_date->copy()->subDay()->format('Y-m-d');
            $previous_total_ring_at_factory = $factory_cache[$day_before_start_key] ?? 0;
            $beginning_stock_factory        = $previous_total_ring_at_factory + $send_to_factory;

            $ring_data[] = [
                'Ring Name'                  => $ring->ring_name,
                'Beginning Stock (Warehouse)' => number_format($beginning_stock, 0),
                'Ring In'                    => number_format($ring_in, 0),
                'Send to Factory'            => number_format($display_send_to_factory, 0),
                'IN-Warehouse'               => number_format($in_warehouse, 0),
                'Beginning Stock (Factory)'  => number_format($beginning_stock_factory, 0),
                'Open Ring(Supplier)'        => number_format($open_ring_supplier, 0),
                'Total Ring at Factory'      => number_format($total_ring_at_factory, 0),
            ];
        }

        return $ring_data;
    } catch (\Exception $e) {
        \Log::error('Error in getRingReportData: ' . $e->getMessage());
        return [];
    }
}

private function fetchAllReportData($business_id, $location_id, $rings, $start_date, $end_date, $cutoff_date)
{
    $july_first  = \Carbon\Carbon::create(2025, 7, 1, 0, 0, 0);
    $july_second = \Carbon\Carbon::create(2025, 7, 2, 0, 0, 0);
    $filter_includes_day_one = ($start_date <= $july_first && $end_date >= $july_first);

    $product_ids = $rings->pluck('product_id')->toArray();

    $data = [
        'warehouse_cache'         => [],
        'factory_cache'           => [],
        'opening_stock'           => [],
        'ring_in'                 => [],
        'ring_in_supplier'        => [],
        'send_to_factory'         => [],
        'display_send_to_factory' => [],
        'open_ring_supplier'      => [],
    ];

    // Opening stock (all products)
    $opening_stocks = DB::table('transactions')
        ->join('purchase_lines', 'transactions.id', '=', 'purchase_lines.transaction_id')
        ->where('transactions.business_id', $business_id)
        ->where('transactions.location_id', $location_id)
        ->where('transactions.type', 'opening_stock')
        ->whereIn('purchase_lines.product_id', $product_ids)
        ->whereNull('transactions.deleted_at')
        ->select('purchase_lines.product_id', 'transactions.transaction_date', 'purchase_lines.quantity')
        ->get();

    foreach ($product_ids as $product_id) {
        $data['opening_stock'][$product_id]['range'] = $opening_stocks
            ->where('product_id', $product_id)
            ->whereBetween('transaction_date', [$start_date, $end_date])
            ->sum('quantity');

        if ($filter_includes_day_one) {
            $special = DB::table('transactions')
                ->join('purchase_lines', 'transactions.id', '=', 'purchase_lines.transaction_id')
                ->where('transactions.business_id', $business_id)
                ->where('transactions.location_id', $location_id)
                ->where('transactions.type', 'opening_stock')
                ->whereIn('transactions.id', [49877, 49876, 49875])
                ->where('purchase_lines.product_id', $product_id)
                ->whereNull('transactions.deleted_at')
                ->sum('purchase_lines.quantity');
            $data['opening_stock'][$product_id]['special'] = $special;
        }

        $data['opening_stock'][$product_id]['before'] = $opening_stocks
            ->where('product_id', $product_id)
            ->where('transaction_date', '<', $start_date)
            ->filter(function ($item) {
                $date = \Carbon\Carbon::parse($item->transaction_date);
                return !$date->between(
                    \Carbon\Carbon::create(2025, 6, 1, 0, 0, 0),
                    \Carbon\Carbon::create(2025, 6, 29, 23, 59, 59)
                );
            })
            ->sum('quantity');
    }

    // Ring in (customer)
    $ring_ins = DB::table('transactions_ring_balance')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->join('contacts', 'transactions_ring_balance.contact_id', '=', 'contacts.id')
        ->where('transactions_ring_balance.business_id', $business_id)
        ->where('transactions_ring_balance.location_id', $location_id)
        ->where('transactions_ring_balance.type', 'top_up_ring_balance')
        ->where('transactions_ring_balance.status', 'completed')
        ->whereIn('transaction_sell_ring_balance.product_id', $product_ids)
        ->where('transactions_ring_balance.transaction_date', '>', $cutoff_date)
        ->whereBetween('transactions_ring_balance.transaction_date', [$start_date, $end_date])
        ->whereNull('transactions_ring_balance.deleted_at')
        ->where('contacts.type', 'customer')
        ->where(function ($query) {
            $query->whereNull('transaction_sell_ring_balance.cash_ring')
                ->orWhere('transaction_sell_ring_balance.cash_ring', '');
        })
        ->select('transaction_sell_ring_balance.product_id', 'transaction_sell_ring_balance.quantity')
        ->get();

    foreach ($product_ids as $product_id) {
        $data['ring_in'][$product_id] = $ring_ins
            ->where('product_id', $product_id)
            ->sum('quantity');
    }

    // Ring in supplier
    $ring_in_suppliers = DB::table('transactions_ring_balance')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->join('contacts', 'transactions_ring_balance.contact_id', '=', 'contacts.id')
        ->where('transactions_ring_balance.business_id', $business_id)
        ->where('transactions_ring_balance.location_id', $location_id)
        ->where('transactions_ring_balance.type', 'top_up_ring_balance')
        ->where('transactions_ring_balance.status', 'completed')
        ->whereIn('transaction_sell_ring_balance.product_id', $product_ids)
        ->where('transactions_ring_balance.transaction_date', '>', $cutoff_date)
        ->whereBetween('transactions_ring_balance.transaction_date', [$start_date, $end_date])
        ->whereNull('transactions_ring_balance.deleted_at')
        ->where('contacts.type', 'supplier')
        ->where(function ($query) {
            $query->whereNull('transaction_sell_ring_balance.cash_ring')
                ->orWhere('transaction_sell_ring_balance.cash_ring', '');
        })
        ->select('transaction_sell_ring_balance.product_id', 'transaction_sell_ring_balance.quantity')
        ->get();

    foreach ($product_ids as $product_id) {
        $data['ring_in_supplier'][$product_id] = $ring_in_suppliers
            ->where('product_id', $product_id)
            ->sum('quantity');
    }

    // ----------------------------------------------------------------
    // FIX: Send to factory
    // + whereNull('t.deleted_at')
    // + MIN(id) dedup — supplier_reward_in has GENUINE duplicates:
    //   2 identical rows per (transaction_id, product_id, contact_id)
    //   created by a bug in the supplier_exchange store method.
    // ----------------------------------------------------------------
    $send_factories = DB::table('stock_reward_exchange_new as sren')
        ->join('transactions as t', 'sren.transaction_id', '=', 't.id')
        ->where('t.business_id', $business_id)
        ->where('t.location_id', $location_id)
        ->where('t.type', 'supplier_exchange')
        ->where('t.sub_type', 'send')
        ->whereIn('sren.product_id', $product_ids)
        ->where('t.transaction_date', '>', $cutoff_date)
        ->whereBetween('t.transaction_date', [$start_date, $end_date])
        ->whereNull('t.deleted_at')
        ->whereNotNull('sren.contact_id')
        ->where('sren.type', 'supplier_reward_in')
        ->whereIn('sren.id', function ($sub) {
            $sub->selectRaw('MIN(id)')
                ->from('stock_reward_exchange_new')
                ->where('type', 'supplier_reward_in')
                ->whereNotNull('contact_id')
                ->groupBy('transaction_id', 'product_id', 'contact_id');
        })
        ->select('sren.product_id', 'sren.quantity')
        ->get();

    foreach ($product_ids as $product_id) {
        $data['send_to_factory'][$product_id] = abs(
            $send_factories->where('product_id', $product_id)->sum('quantity')
        );
    }

    // ----------------------------------------------------------------
    // FIX: Display send to factory (excluding July 1) — same fixes
    // ----------------------------------------------------------------
    $display_start_date = $start_date;
    if ($start_date->isSameDay($july_first)) {
        $display_start_date = $july_second;
    }

    if ($display_start_date <= $end_date) {
        $display_factories = DB::table('stock_reward_exchange_new as sren')
            ->join('transactions as t', 'sren.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.location_id', $location_id)
            ->where('t.type', 'supplier_exchange')
            ->where('t.sub_type', 'send')
            ->whereIn('sren.product_id', $product_ids)
            ->where('t.transaction_date', '>', $cutoff_date)
            ->whereBetween('t.transaction_date', [$display_start_date, $end_date])
            ->whereNull('t.deleted_at')
            ->whereNotNull('sren.contact_id')
            ->where('sren.type', 'supplier_reward_in')
            ->whereIn('sren.id', function ($sub) {
                $sub->selectRaw('MIN(id)')
                    ->from('stock_reward_exchange_new')
                    ->where('type', 'supplier_reward_in')
                    ->whereNotNull('contact_id')
                    ->groupBy('transaction_id', 'product_id', 'contact_id');
            })
            ->select('sren.product_id', 'sren.quantity')
            ->get();

        foreach ($product_ids as $product_id) {
            $data['display_send_to_factory'][$product_id] = abs(
                $display_factories->where('product_id', $product_id)->sum('quantity')
            );
        }
    } else {
        foreach ($product_ids as $product_id) {
            $data['display_send_to_factory'][$product_id] = 0;
        }
    }

    // ----------------------------------------------------------------
    // FIX: Open ring supplier
    // Use stock_reward_exchange_new type=supplier_receive_out directly.
    // NO MIN(id) dedup here — each row is LEGITIMATE (one per sell_line).
    // The original ×2 bug was caused solely by the rewards_exchange JOIN
    // which matched 2 entries per exchange_product. Removing that JOIN
    // is sufficient; no further dedup needed.
    // ----------------------------------------------------------------
    $open_rings = DB::table('stock_reward_exchange_new as sren')
        ->join('transactions as tr_receive', 'sren.transaction_id', '=', 'tr_receive.id')
        ->where('tr_receive.business_id', $business_id)
        ->where('tr_receive.location_id', $location_id)
        ->where('tr_receive.type', 'supplier_exchange_receive')
        ->where('tr_receive.status', 'completed')
        ->whereBetween('tr_receive.transaction_date', [$start_date, $end_date])
        ->whereNull('tr_receive.deleted_at')
        ->where('sren.type', 'supplier_receive_out')
        ->whereIn('sren.product_id', $product_ids)
        ->select(
            'sren.product_id as exchange_product',
            DB::raw('ABS(sren.quantity) as calc_quantity')
        )
        ->get();

    foreach ($product_ids as $product_id) {
        $data['open_ring_supplier'][$product_id] = $open_rings
            ->where('exchange_product', $product_id)
            ->sum('calc_quantity');
    }

    // Build caches
    $data['warehouse_cache'] = $this->buildInWarehouseCacheOptimized(
        $business_id,
        $location_id,
        $product_ids,
        $cutoff_date,
        $end_date,
        $opening_stocks
    );

    $data['factory_cache'] = $this->buildFactoryCacheOptimized(
        $business_id,
        $location_id,
        $product_ids,
        $cutoff_date,
        $end_date
    );

    return $data;
}

private function buildInWarehouseCacheOptimized($business_id, $location_id, $product_ids, $cutoff_date, $end_date, $opening_stocks_base)
{
    $cache      = [];
    $july_first = \Carbon\Carbon::create(2025, 7, 1, 0, 0, 0);

    foreach ($product_ids as $product_id) {
        $cache[$product_id] = [];
    }

    // Daily ring in (customer)
    $daily_ring_ins = DB::table('transactions_ring_balance')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->join('contacts', 'transactions_ring_balance.contact_id', '=', 'contacts.id')
        ->where('transactions_ring_balance.business_id', $business_id)
        ->where('transactions_ring_balance.location_id', $location_id)
        ->where('transactions_ring_balance.type', 'top_up_ring_balance')
        ->where('transactions_ring_balance.status', 'completed')
        ->whereIn('transaction_sell_ring_balance.product_id', $product_ids)
        ->where('transactions_ring_balance.transaction_date', '>', $cutoff_date)
        ->whereBetween(
            'transactions_ring_balance.transaction_date',
            [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
        )
        ->whereNull('transactions_ring_balance.deleted_at')
        ->where('contacts.type', 'customer')
        ->where(function ($query) {
            $query->whereNull('transaction_sell_ring_balance.cash_ring')
                ->orWhere('transaction_sell_ring_balance.cash_ring', '');
        })
        ->select(
            'transaction_sell_ring_balance.product_id',
            'transactions_ring_balance.transaction_date',
            'transaction_sell_ring_balance.quantity'
        )
        ->get();

    // ----------------------------------------------------------------
    // FIX: Daily send to factory — whereNull deleted_at + MIN(id) dedup
    // ----------------------------------------------------------------
    $daily_send_factories = DB::table('stock_reward_exchange_new as sren')
        ->join('transactions as t', 'sren.transaction_id', '=', 't.id')
        ->where('t.business_id', $business_id)
        ->where('t.location_id', $location_id)
        ->where('t.type', 'supplier_exchange')
        ->where('t.sub_type', 'send')
        ->whereIn('sren.product_id', $product_ids)
        ->where('t.transaction_date', '>', $cutoff_date)
        ->whereBetween(
            't.transaction_date',
            [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
        )
        ->whereNull('t.deleted_at')
        ->whereNotNull('sren.contact_id')
        ->where('sren.type', 'supplier_reward_in')
        ->whereIn('sren.id', function ($sub) {
            $sub->selectRaw('MIN(id)')
                ->from('stock_reward_exchange_new')
                ->where('type', 'supplier_reward_in')
                ->whereNotNull('contact_id')
                ->groupBy('transaction_id', 'product_id', 'contact_id');
        })
        ->select(
            'sren.product_id',
            't.transaction_date',
            'sren.quantity'
        )
        ->get();

    // Daily opening stock
    $daily_opening_stocks = $opening_stocks_base->whereBetween(
        'transaction_date',
        [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
    );

    // Beginning stock before cutoff for each product
    $opening_stock_before = [];
    foreach ($product_ids as $product_id) {
        $opening_stock_before[$product_id] = $opening_stocks_base
            ->where('product_id', $product_id)
            ->where('transaction_date', '<', $cutoff_date->copy()->addSecond()->startOfDay())
            ->filter(function ($item) {
                $date = \Carbon\Carbon::parse($item->transaction_date);
                return !$date->between(
                    \Carbon\Carbon::create(2025, 6, 1, 0, 0, 0),
                    \Carbon\Carbon::create(2025, 6, 29, 23, 59, 59)
                );
            })
            ->sum('quantity');
    }

    // Build cache day by day
    $current_date = $cutoff_date->copy()->addDay();
    while ($current_date <= $end_date) {
        $date_key = $current_date->format('Y-m-d');

        foreach ($product_ids as $product_id) {
            $prev_date_key        = $current_date->copy()->subDay()->format('Y-m-d');
            $current_in_warehouse = $cache[$product_id][$prev_date_key] ?? $opening_stock_before[$product_id];

            $ring_in = $daily_ring_ins
                ->where('product_id', $product_id)
                ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                ->sum('quantity');

            $send_to_factory = 0;
            if (!$current_date->isSameDay($july_first)) {
                $send_to_factory = abs(
                    $daily_send_factories
                        ->where('product_id', $product_id)
                        ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                        ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                        ->sum('quantity')
                );
            }

            $opening_stock_today = $daily_opening_stocks
                ->where('product_id', $product_id)
                ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                ->sum('quantity');

            $current_in_warehouse = $current_in_warehouse + $ring_in - $send_to_factory + $opening_stock_today;
            $cache[$product_id][$date_key] = $current_in_warehouse;
        }

        $current_date->addDay();
    }

    return $cache;
}


private function buildFactoryCacheOptimized($business_id, $location_id, $product_ids, $cutoff_date, $end_date)
{
    $cache = [];

    foreach ($product_ids as $product_id) {
        $cache[$product_id] = [];
    }

    // ----------------------------------------------------------------
    // FIX: Daily send to factory — whereNull deleted_at + MIN(id) dedup
    // ----------------------------------------------------------------
    $daily_send_factories = DB::table('stock_reward_exchange_new as sren')
        ->join('transactions as t', 'sren.transaction_id', '=', 't.id')
        ->where('t.business_id', $business_id)
        ->where('t.location_id', $location_id)
        ->where('t.type', 'supplier_exchange')
        ->where('t.sub_type', 'send')
        ->whereIn('sren.product_id', $product_ids)
        ->where('t.transaction_date', '>', $cutoff_date)
        ->whereBetween(
            't.transaction_date',
            [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
        )
        ->whereNull('t.deleted_at')
        ->whereNotNull('sren.contact_id')
        ->where('sren.type', 'supplier_reward_in')
        ->whereIn('sren.id', function ($sub) {
            $sub->selectRaw('MIN(id)')
                ->from('stock_reward_exchange_new')
                ->where('type', 'supplier_reward_in')
                ->whereNotNull('contact_id')
                ->groupBy('transaction_id', 'product_id', 'contact_id');
        })
        ->select(
            'sren.product_id',
            't.transaction_date',
            'sren.quantity'
        )
        ->get();

    // Daily ring in supplier
    $daily_ring_in_suppliers = DB::table('transactions_ring_balance')
        ->join('transaction_sell_ring_balance', 'transactions_ring_balance.id', '=', 'transaction_sell_ring_balance.transactions_ring_balance_id')
        ->join('contacts', 'transactions_ring_balance.contact_id', '=', 'contacts.id')
        ->where('transactions_ring_balance.business_id', $business_id)
        ->where('transactions_ring_balance.location_id', $location_id)
        ->where('transactions_ring_balance.type', 'top_up_ring_balance')
        ->where('transactions_ring_balance.status', 'completed')
        ->whereIn('transaction_sell_ring_balance.product_id', $product_ids)
        ->where('transactions_ring_balance.transaction_date', '>', $cutoff_date)
        ->whereBetween(
            'transactions_ring_balance.transaction_date',
            [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
        )
        ->whereNull('transactions_ring_balance.deleted_at')
        ->where('contacts.type', 'supplier')
        ->where(function ($query) {
            $query->whereNull('transaction_sell_ring_balance.cash_ring')
                ->orWhere('transaction_sell_ring_balance.cash_ring', '');
        })
        ->select(
            'transaction_sell_ring_balance.product_id',
            'transactions_ring_balance.transaction_date',
            'transaction_sell_ring_balance.quantity'
        )
        ->get();

    // ----------------------------------------------------------------
    // FIX: Daily open ring supplier
    // Use supplier_receive_out — NO MIN(id) dedup.
    // Each row is legitimate (one per sell_line per transaction).
    // Original ×2 was caused by rewards_exchange JOIN only.
    // ----------------------------------------------------------------
    $daily_open_rings = DB::table('stock_reward_exchange_new as sren')
        ->join('transactions as tr_receive', 'sren.transaction_id', '=', 'tr_receive.id')
        ->where('tr_receive.business_id', $business_id)
        ->where('tr_receive.location_id', $location_id)
        ->where('tr_receive.type', 'supplier_exchange_receive')
        ->where('tr_receive.status', 'completed')
        ->whereBetween(
            'tr_receive.transaction_date',
            [$cutoff_date->copy()->addDay()->startOfDay(), $end_date]
        )
        ->whereNull('tr_receive.deleted_at')
        ->where('sren.type', 'supplier_receive_out')
        ->whereIn('sren.product_id', $product_ids)
        ->select(
            'sren.product_id as exchange_product',
            'tr_receive.transaction_date',
            DB::raw('ABS(sren.quantity) as calc_quantity')
        )
        ->get();

    // Build cache day by day
    $current_date = $cutoff_date->copy()->addDay();
    while ($current_date <= $end_date) {
        $date_key = $current_date->format('Y-m-d');

        foreach ($product_ids as $product_id) {
            $prev_date_key = $current_date->copy()->subDay()->format('Y-m-d');
            $current_total = $cache[$product_id][$prev_date_key] ?? 0;

            $send_to_factory = abs(
                $daily_send_factories
                    ->where('product_id', $product_id)
                    ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                    ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                    ->sum('quantity')
            );

            $ring_in_supplier = $daily_ring_in_suppliers
                ->where('product_id', $product_id)
                ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                ->sum('quantity');

            $open_ring_supplier = $daily_open_rings
                ->where('exchange_product', $product_id)
                ->where('transaction_date', '>=', $current_date->copy()->startOfDay())
                ->where('transaction_date', '<=', $current_date->copy()->endOfDay())
                ->sum('calc_quantity');

            $current_total = $current_total + $send_to_factory + $ring_in_supplier - $open_ring_supplier;
            $cache[$product_id][$date_key] = $current_total;
        }

        $current_date->addDay();
    }

    return $cache;
}

private function getRingTopUpData($business_id, $location_id, $start_date, $end_date)
{
    // Base query for ring transactions with deleted conditions and type filter
    $baseQuery = DB::table('transactions_ring_balance as trb')
        ->join('transaction_sell_ring_balance as tsrb', 'trb.id', '=', 'tsrb.transactions_ring_balance_id')
        ->join('products as p', 'tsrb.product_id', '=', 'p.id')
        ->where('trb.business_id', $business_id)
        ->where('trb.location_id', $location_id)
        ->where('trb.type', 'top_up_ring_balance')
        ->where('p.business_id', $business_id)
        ->where('p.type', 'single') // Assuming 'single' type for ring products
        ->whereBetween('trb.transaction_date', [$start_date, $end_date])
        ->whereNull('trb.deleted_by')
        ->whereNull('trb.deleted_at');

    // Get Normal Ring data (cash_ring IS NULL)
    $normalRingQuery = clone $baseQuery;
    $normalRings = $normalRingQuery
        ->whereNull('tsrb.cash_ring')
        ->select(
            'p.name as ring_name',
            'trb.location_id',
            DB::raw('COUNT(DISTINCT trb.id) as total_transaction'),
            DB::raw('SUM(tsrb.quantity) as total_quantity'),
            DB::raw("'-' as unit"),
            DB::raw("'Normal' as ring_type")
        )
        ->groupBy('p.id', 'p.name', 'trb.location_id')
        ->get();

    // Get Cash Ring data (cash_ring = 1) - First get transaction counts per product
    $cashRingTransactionCounts = DB::table('transactions_ring_balance as trb')
        ->join('transaction_sell_ring_balance as tsrb', 'trb.id', '=', 'tsrb.transactions_ring_balance_id')
        ->join('products as p', 'tsrb.product_id', '=', 'p.id')
        ->where('trb.business_id', $business_id)
        ->where('trb.location_id', $location_id)
        ->where('trb.type', 'top_up_ring_balance')
        ->where('p.business_id', $business_id)
        ->where('p.type', 'single')
        ->whereBetween('trb.transaction_date', [$start_date, $end_date])
        ->whereNull('trb.deleted_by')
        ->whereNull('trb.deleted_at')
        ->where('tsrb.cash_ring', 1)
        ->select(
            'p.name as ring_name',
            'trb.location_id',
            DB::raw('COUNT(DISTINCT trb.id) as total_transaction')
        )
        ->groupBy('p.id', 'p.name', 'trb.location_id')
        ->get()
        ->keyBy(function($item) {
            return $item->ring_name . '_' . $item->location_id;
        });

    // ==================== SIMPLIFIED FIX ====================
    // Get Cash Ring data with exchange rate - but fetch raw data first
    $cashRingQuery = clone $baseQuery;
    $cashRingRawData = $cashRingQuery
        ->where('tsrb.cash_ring', 1)
        ->join('transaction_cash_ring_balance as tcrb', 'tsrb.id', '=', 'tcrb.transaction_sell_ring_balance_id')
        ->join('cash_ring_balance as crb', 'tcrb.cash_ring_balance_id', '=', 'crb.id')
        ->select(
            'p.id as product_id',
            'p.name as ring_name',
            'trb.location_id',
            'trb.exchange_rate',  // Get exchange rate
            'crb.type_currency',
            'crb.unit_value',
            'tcrb.quantity'
        )
        ->get();
    // ========================================================

    // Process results
    $finalResults = [];

    // Process Normal Rings
    foreach ($normalRings as $ring) {
        $location = BusinessLocation::find($ring->location_id);
        $finalResults[] = [
            'ring_name' => $ring->ring_name . ' (Can)', 
            'total_transaction' => $ring->total_transaction,
            'location' => $location ? $location->name : 'Unknown',
            'unit' => $ring->unit,
            'total_quantity' => number_format($ring->total_quantity, 0) . ' Can'
        ];
    }

    // ==================== PROCESS CASH RINGS MANUALLY ====================
    // Process Cash Rings and consolidate units
    $cashRingGroups = [];
    foreach ($cashRingRawData as $ring) {
        $key = $ring->ring_name . '_' . $ring->location_id;
        
        if (!isset($cashRingGroups[$key])) {
            $transactionCount = isset($cashRingTransactionCounts[$key]) ? $cashRingTransactionCounts[$key]->total_transaction : 0;
            $location = BusinessLocation::find($ring->location_id);
            
            $cashRingGroups[$key] = [
                'ring_name' => $ring->ring_name,
                'location_id' => $ring->location_id,
                'total_transaction' => $transactionCount,
                'unit_details' => [],
                'combined_dollar_value' => 0
            ];
        }

        // ==================== USE EXCHANGE RATE ====================
        $exchangeRate = (!empty($ring->exchange_rate) && $ring->exchange_rate > 0) ? $ring->exchange_rate : 4000;
        
        // Calculate value with exchange rate
        $total_value = $ring->quantity * $ring->unit_value;
        $dollar_value = ($ring->type_currency == 1) ? $total_value : ($total_value / $exchangeRate);
        
        $cashRingGroups[$key]['combined_dollar_value'] += $dollar_value;
        
        // Track unit details for display
        $unit_value_display = $ring->unit_value == floor($ring->unit_value) ? 
            number_format($ring->unit_value, 0) : 
            rtrim(rtrim(number_format($ring->unit_value, 3), '0'), '.');
        $currency_symbol = $ring->type_currency == 1 ? '$' : '៛';
        $unitKey = $unit_value_display . $currency_symbol;
        
        if (!isset($cashRingGroups[$key]['unit_details'][$unitKey])) {
            $cashRingGroups[$key]['unit_details'][$unitKey] = 0;
        }
        $cashRingGroups[$key]['unit_details'][$unitKey] += $ring->quantity;
        // ===========================================================
    }

    // Add processed cash rings to final results
    foreach ($cashRingGroups as $group) {
        $location = BusinessLocation::find($group['location_id']);
        
        // Format unit details
        $unitDisplayParts = [];
        foreach ($group['unit_details'] as $unit => $qty) {
            $unitDisplayParts[] = $unit . ' = ' . $qty;
        }
        $unitDisplay = implode('<br>', $unitDisplayParts);

        $finalResults[] = [
            'ring_name' => $group['ring_name'] . ' (Cash)',
            'total_transaction' => $group['total_transaction'],
            'location' => $location ? $location->name : 'Unknown',
            'unit' => $unitDisplay,
            'total_quantity' => '$' . number_format($group['combined_dollar_value'], 3)
        ];
    }
    // =====================================================================

    return $finalResults;
}

    public function ReportExportCenter(Request $request)
{
    $business_id = $request->session()->get('user.business_id');

    // Get business locations for dropdown
    $business_locations = BusinessLocation::forDropdown($business_id, true);
    
    // Get suppliers for dropdown
    $suppliers = Contact::suppliersDropdown($business_id, false);

    // Get selected filters from request
    $location_id = $request->input('sell_list_filter_location_id');
    $supplier_id = $request->input('supplier_id');
    $date_range = $request->input('sell_list_filter_date_range');

    // Handle AJAX requests for filter application AND data export
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $get_export_data = $request->input('get_export_data');
        $report_type = $request->input('report_type', 'sales'); // 'sales' or 'purchase'
        
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');
        $should_get_export_data = ($get_export_data === true || $get_export_data === 'true' || $get_export_data === '1');

        // Validate required parameters
        if ((!$should_apply_filters && !$should_get_export_data) || !$location_id || !$date_range) {
            return response()->json([
                'success' => false,
                'message' => 'Please select location and date range first.'
            ]);
        }

        // Parse date range
        $dates = explode(' ~ ', $date_range);
        if (count($dates) != 2) {
            return response()->json(['success' => false, 'message' => 'Invalid date range format']);
        }

        try {
            $start_date = null;
            $end_date = null;
            
            $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
            $parsed = false;
            
            foreach ($date_formats as $format) {
                try {
                    $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                    $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                    $parsed = true;
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            if (!$parsed) {
                \Log::error('Date parsing failed for range: ' . $date_range);
                return response()->json(['success' => false, 'message' => 'Invalid date format']);
            }
            
            // Get data based on report type
            if ($report_type === 'purchase') {
                $data = $this->getPurchaseReportData($business_id, $location_id, $start_date, $end_date, $supplier_id);
                $report_name = 'Purchase Report';
                $filename = 'purchase_report_' . $this->getLocationName($location_id) . '_' . $start_date->format('Y-m-d') . '_to_' . $end_date->format('Y-m-d');
            } else {
                $data = $this->getSalesByZoneData($business_id, $location_id, $start_date, $end_date);
                $report_name = 'Sales by Zone';
                $filename = 'sales_by_zone_' . $this->getLocationName($location_id) . '_' . $start_date->format('Y-m-d') . '_to_' . $end_date->format('Y-m-d');
            }
            
            $recordCount = count($data);
            
            // Return data for export
            if ($should_get_export_data) {
                if (empty($data)) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'No data found for the selected criteria.'
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'filename' => $filename,
                    'message' => 'Data ready for export'
                ]);
            }
            
            // Return success response for filter application
            if ($should_apply_filters) {
                return response()->json([
                    'success' => true,
                    'message' => $recordCount > 0 ? 
                        "Filters applied successfully for {$report_name}. Found {$recordCount} records ready for export." : 
                        "Filters applied successfully for {$report_name}. No data found for selected criteria.",
                    'recordsTotal' => $recordCount
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error in ReportExportCenter: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()]);
        }
    }

    // Set default date range
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->startOfYear()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    return view('report.report_export_center')
        ->with(compact('business_locations', 'suppliers', 'location_id', 'date_range'));
}

private function getPurchaseReportData($business_id, $location_id, $start_date, $end_date, $supplier_id = null)
{
    try {
        // Get all purchase types
        $purchase_types = DB::table('purchase_types')
            ->where('business_id', $business_id)
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Get all products with data in the range
        $products = DB::table('purchase_lines as pl')
            ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
            ->join('products as p', 'pl.product_id', '=', 'p.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'purchase')
            ->whereIn('t.status', ['received', 'pending'])
            ->whereNull('t.deleted_at')
            ->where('t.location_id', $location_id)
            ->whereBetween('t.transaction_date', [$start_date, $end_date])
            ->when(!empty($supplier_id), function($q) use ($supplier_id) {
                return $q->where('t.contact_id', $supplier_id);
            })
            ->select('p.id', 'p.name')
            ->distinct()
            ->orderBy('p.name')
            ->get();

        // Get all unique dates
        $dates = DB::table('transactions as t')
            ->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'purchase')
            ->whereIn('t.status', ['received', 'pending'])
            ->whereNull('t.deleted_at')
            ->where('t.location_id', $location_id)
            ->whereBetween('t.transaction_date', [$start_date, $end_date])
            ->when(!empty($supplier_id), function($q) use ($supplier_id) {
                return $q->where('t.contact_id', $supplier_id);
            })
            ->select(DB::raw('DATE(t.transaction_date) as purchase_date'))
            ->distinct()
            ->orderBy('purchase_date')
            ->pluck('purchase_date')
            ->toArray();

        // Get all suppliers
        $suppliers_data = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'purchase')
            ->whereIn('t.status', ['received', 'pending'])
            ->whereNull('t.deleted_at')
            ->where('t.location_id', $location_id)
            ->whereBetween('t.transaction_date', [$start_date, $end_date])
            ->when(!empty($supplier_id), function($q) use ($supplier_id) {
                return $q->where('t.contact_id', $supplier_id);
            })
            ->select('c.id', 'c.name', 'c.supplier_business_name')
            ->distinct()
            ->orderBy('c.name')
            ->get();

        $results = [];

        // Process each supplier separately
        foreach ($suppliers_data as $supplier) {
            // Supplier Header Row
            $supplier_display = !empty($supplier->supplier_business_name) 
                ? $supplier->supplier_business_name 
                : $supplier->name;
            
            $supplier_row = ['Date' => 'SUPPLIER: ' . $supplier_display];
            $results[] = $supplier_row;

            // Product Header Row (First level - merged headers)
            $product_header = ['Date' => 'Date'];
            foreach ($products as $product) {
                // Add product name, it will be merged across purchase types
                $product_header[$product->id . '_header'] = $product->name;
            }
            $results[] = $product_header;

            // Purchase Type Header Row (Second level - sub headers)
            $type_header = ['Date' => ''];
            foreach ($products as $product) {
                foreach ($purchase_types as $type_id => $type) {
                    $column_key = 'p_' . $product->id . '_t_' . $type_id;
                    $type_header[$column_key] = $type->name;
                }
            }
            $results[] = $type_header;

            // Data rows for each date
            foreach ($dates as $date) {
                $row = ['Date' => \Carbon\Carbon::parse($date)->format('Y-m-d')];

                // Get data for this supplier and date
                $date_data = DB::table('purchase_lines as pl')
                    ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                    ->join('products as p', 'pl.product_id', '=', 'p.id')
                    ->leftJoin('units as u', 'pl.sub_unit_id', '=', 'u.id') // JOIN UNITS TABLE
                    ->where('t.business_id', $business_id)
                    ->where('t.type', 'purchase')
                    ->whereIn('t.status', ['received', 'pending'])
                    ->whereNull('t.deleted_at')
                    ->where('t.location_id', $location_id)
                    ->where('t.contact_id', $supplier->id)
                    ->whereDate('t.transaction_date', $date)
                    ->select('p.id', 'pl.purchase_type_id', 
                        // CALCULATE CONVERTED QUANTITY
                        DB::raw('SUM((pl.quantity - COALESCE(pl.quantity_returned, 0)) / COALESCE(u.base_unit_multiplier, 1)) as total_qty'))
                    ->groupBy('p.id', 'pl.purchase_type_id')
                    ->get();

                // Create lookup array
                $date_data_array = [];
                foreach ($date_data as $item) {
                    // Use number_format to handle decimals if unit conversion results in non-integers, or intval if you strictly want integers
                    $date_data_array[$item->id][$item->purchase_type_id] = $item->total_qty;
                }

                // Fill columns
                foreach ($products as $product) {
                    foreach ($purchase_types as $type_id => $type) {
                        $qty = $date_data_array[$product->id][$type_id] ?? 0;
                        $column_key = 'p_' . $product->id . '_t_' . $type_id;
                        // Format display (remove trailing zeros if decimal)
                        $row[$column_key] = (string) (float)$qty; 
                    }
                }

                $results[] = $row;
            }

            // Total row
            $total_row = ['Date' => 'Total'];
            foreach ($products as $product) {
                foreach ($purchase_types as $type_id => $type) {
                    $total_qty = DB::table('purchase_lines as pl')
                        ->join('transactions as t', 'pl.transaction_id', '=', 't.id')
                        ->leftJoin('units as u', 'pl.sub_unit_id', '=', 'u.id') // JOIN UNITS TABLE
                        ->where('t.business_id', $business_id)
                        ->where('t.type', 'purchase')
                        ->whereIn('t.status', ['received', 'pending'])
                        ->whereNull('t.deleted_at')
                        ->where('t.location_id', $location_id)
                        ->where('t.contact_id', $supplier->id)
                        ->where('pl.product_id', $product->id)
                        ->where('pl.purchase_type_id', $type_id)
                        ->whereBetween('t.transaction_date', [$start_date, $end_date])
                        // CALCULATE CONVERTED TOTAL QUANTITY
                        ->sum(DB::raw('(pl.quantity - COALESCE(pl.quantity_returned, 0)) / COALESCE(u.base_unit_multiplier, 1)'));
                    
                    $column_key = 'p_' . $product->id . '_t_' . $type_id;
                    $total_row[$column_key] = (string) (float)$total_qty;
                }
            }
            $results[] = $total_row;

            // Add blank row between suppliers
            $results[] = ['Date' => ''];
        }

        return $results;

    } catch (\Exception $e) {
        \Log::error('Error in getPurchaseReportData: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        return [];
    }
}

private function getLocationName($location_id)
{
    $location_name = BusinessLocation::find($location_id)->name ?? 'location';
    return preg_replace('/[^A-Za-z0-9_\-]/', '_', $location_name);
}

// Keep existing getSalesByZoneData function unchanged
private function getSalesByZoneData($business_id, $location_id, $start_date, $end_date)
{
    try {
        // Raw SQL query with dynamic business_id and location_id
        $query = "
            WITH zone_product_data AS (
                SELECT
                    CASE
                        WHEN cm.commune_id = 941 THEN 'Sangkat Krung Thnong'
                        WHEN cm.commune_id = 944 THEN 'Sangkat Khmuonh'
                        WHEN cm.commune_id = 945 THEN 'Sangkat PhnomPenh Thmei'
                        WHEN cm.commune_id = 940 THEN 'Sangkat Kouk Kleang'
                        WHEN cm.commune_id = 942 THEN 'Sangkat Ou Baek K\'am'
                        WHEN cm.commune_id = 943 THEN 'Sangkat Tuek Thla'
                        ELSE CONCAT('Zone ', cm.commune_id)
                    END AS zone_name,
                    p.name AS product_name,
                    SUM(tsl.quantity) AS quantity
                FROM contacts c
                INNER JOIN contacts_map cm ON c.id = cm.contact_id
                INNER JOIN transactions t ON c.id = t.contact_id
                INNER JOIN transaction_sell_lines tsl ON t.id = tsl.transaction_id
                INNER JOIN products p ON tsl.product_id = p.id
                WHERE c.business_id = ?
                    AND cm.commune_id IN (941, 944, 945, 940, 942, 943)
                    AND t.business_id = ?
                    AND t.location_id = ?
                    AND t.type = 'sell'
                    AND t.status = 'final'
                    AND t.transaction_date BETWEEN ? AND ?
                    AND tsl.parent_sell_line_id IS NULL
                    AND c.deleted_at IS NULL
                    AND cm.deleted_at IS NULL
                    AND t.deleted_at IS NULL
                GROUP BY cm.commune_id, p.id, p.name
            ),
            all_products AS (
                SELECT DISTINCT product_name FROM zone_product_data
            )
            SELECT
                ap.product_name AS 'SALE BY ZONE',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat Kouk Kleang' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat Kouk Kleang',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat Khmuonh' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat Khmuonh',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat PhnomPenh Thmei' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat PhnomPenh Thmei',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat Krung Thnong' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat Krung Thnong',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat Ou Baek K\'am' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat Ou Baek K\'am',
                COALESCE(MAX(CASE WHEN zpd.zone_name = 'Sangkat Tuek Thla' THEN zpd.quantity ELSE 0 END), 0) AS 'Sangkat Tuek Thla',
                COALESCE(SUM(zpd.quantity), 0) AS 'TOTAL'
            FROM all_products ap
            LEFT JOIN zone_product_data zpd ON ap.product_name = zpd.product_name
            GROUP BY ap.product_name
            ORDER BY ap.product_name
        ";
        
        $start_date_str = $start_date->format('Y-m-d H:i:s');
        $end_date_str = $end_date->format('Y-m-d H:i:s');
        $results = DB::select($query, [
            $business_id,
            $business_id,
            $location_id,
            $start_date_str,
            $end_date_str
        ]);
        
        // Convert results to array and format numbers to remove decimals
        return array_map(function($item) {
            $array = (array) $item;
            
            // List of numeric columns that should be formatted as integers
            $numericColumns = [
                'Sangkat Kouk Kleang',
                'Sangkat Khmuonh', 
                'Sangkat PhnomPenh Thmei',
                'Sangkat Krung Thnong',
                'Sangkat Ou Baek K\'am',
                'Sangkat Tuek Thla',
                'TOTAL'
            ];
            
            // Format each numeric column to remove decimals
            foreach ($numericColumns as $column) {
                if (isset($array[$column])) {
                    // Convert to integer to remove decimals, then back to string
                    $array[$column] = (string) intval(floatval($array[$column]));
                }
            }
            
            return $array;
        }, $results);
        
    } catch (\Exception $e) {
        \Log::error('Error in getSalesByZoneData: ' . $e->getMessage());
        return [];
    }
}
    
 private function getSalesDetailData(
    $business_id,
    $location_id,
    $start_date,
    $end_date,
    $variation_id = null,
    $customer_id = null,
    $customer_group_id = null,
    $category_id = null,
    $delivery_person_id = null,
    $search_term = null
    )
    {
    // Main query with joins for variations, product variations, and categories/brands
    $query = DB::table('transactions as t')
        ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
        ->join('products as p', 'tsl.product_id', '=', 'p.id')
        ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
        ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
        ->leftJoin('users as u', 't.delivery_person', '=', 'u.id')
        // --- ADDED JOINS FOR UNITS ---
        ->leftJoin('units as unit', 'p.unit_id', '=', 'unit.id')
        ->leftJoin('units as sub_unit', 'tsl.sub_unit_id', '=', 'sub_unit.id')
        // -----------------------------
        ->leftJoin('transaction_payments as tp', function($join) {
            $join->on('t.id', '=', 'tp.transaction_id')
                    ->whereRaw('tp.id = (SELECT MIN(id) FROM transaction_payments WHERE transaction_id = t.id)');
        })
        ->where('t.business_id', $business_id)
        ->where('t.type', 'sell')
        ->whereBetween('t.transaction_date', [$start_date, $end_date])
        ->whereNull('t.deleted_at')
        ->whereNull('tsl.parent_sell_line_id')
        ->where(function($query) {
            $query->whereNull('tsl.children_type')
                    ->orWhere('tsl.children_type', '');
        });

    // Final search block that includes Order No logic
    if (!empty($search_term)) {
        $query->where(function($q) use ($search_term, $business_id) {
            $q->where('p.name', 'like', "%{$search_term}%")                  // Product Name
                ->orWhere('v.sub_sku', 'like', "%{$search_term}%")             // SKU
                ->orWhere('c.name', 'like', "%{$search_term}%")               // Customer Name
                ->orWhere('c.supplier_business_name', 'like', "%{$search_term}%") // Customer Business Name
                ->orWhere('c.contact_id', 'like', "%{$search_term}%")         // Contact ID
                ->orWhere('t.invoice_no', 'like', "%{$search_term}%")        // Invoice No
                // Subquery to find transactions linked to a matching sales order number
                ->orWhereExists(function ($subquery) use ($search_term, $business_id) {
                    $subquery->select(DB::raw(1))
                        ->from('transactions as so')
                        ->where('so.type', 'sales_order')
                        ->where('so.business_id', $business_id)
                        ->where('so.invoice_no', 'like', "%{$search_term}%")
                        ->whereRaw("t.sales_order_ids LIKE CONCAT('%\"', so.id, '\"%')");
                });
        });
    }

    // Apply filters
    if (!empty($location_id)) {
        $query->where('t.location_id', $location_id);
    }
    if (!empty($variation_id)) {
        $query->where('tsl.variation_id', $variation_id);
    }
    if (!empty($customer_id)) {
        $query->where('t.contact_id', $customer_id);
    }
    if (!empty($customer_group_id)) {
        $query->leftjoin('customer_groups AS CG2', 'c.customer_group_id', '=', 'CG2.id')
            ->where('CG2.id', $customer_group_id);
    }
    if (!empty($category_id)) {
        $query->where('p.category_id', $category_id);
    }
    if (!empty($delivery_person_id)) {
        $query->where('t.delivery_person', $delivery_person_id);
    }

    $results = $query->select([
            't.id as transaction_id',
            't.transaction_date',
            DB::raw('COALESCE(c.name, c.supplier_business_name, "Walk-in Customer") as customer_name'),
            'c.contact_id',
            't.sales_order_ids',
            't.invoice_no',
            'p.name as product_name',
            'p.type as product_type',
            'pv.name as product_variation',
            'v.name as variation_name',
            'v.sub_sku as sku',
            'tsl.quantity',
            'tsl.unit_price',
            // --- ADDED SELECTS FOR UNITS ---
            'unit.short_name as base_unit_name',
            'sub_unit.short_name as sub_unit_name',
            // -------------------------------
            DB::raw('CAST(t.final_total AS DECIMAL(22,4)) as transaction_final_total'),
            't.payment_status',
            't.additional_notes',
            'tp.note as payment_note',
            DB::raw('COALESCE(u.first_name, "-") as delivery_person_name'),
            DB::raw('ROW_NUMBER() OVER (PARTITION BY t.id ORDER BY tsl.id) as row_num')
        ])
        ->orderBy('t.transaction_date', 'desc')
        ->orderBy('t.invoice_no', 'asc')
        ->orderBy('tsl.id', 'asc')
        ->get();

    if ($results->isEmpty()) {
        return [];
    }

    // Get sales order info logic
    $allSalesOrderIds = [];
    foreach ($results as $result) {
        if (!empty($result->sales_order_ids)) {
            $salesOrderIds = [];
            $decoded = json_decode($result->sales_order_ids, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $salesOrderIds = $decoded;
            } else {
                $salesOrderIds = explode(',', str_replace(['[', ']', '"'], '', $result->sales_order_ids));
            }
            foreach ($salesOrderIds as $orderId) {
                $orderId = trim($orderId);
                if (!empty($orderId) && is_numeric($orderId)) {
                    $allSalesOrderIds[] = $orderId;
                }
            }
        }
    }

    $salesOrderInvoices = [];
    if (!empty($allSalesOrderIds)) {
        $salesOrders = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->whereIn('id', array_unique($allSalesOrderIds))
            ->whereNull('deleted_at')
            ->pluck('invoice_no', 'id')
            ->toArray();
        $salesOrderInvoices = $salesOrders;
    }

    $getOrderNumbers = function($salesOrderIds) use ($salesOrderInvoices) {
        if (empty($salesOrderIds)) { return '-'; }
        $orderNumbers = [];
        $decoded = json_decode($salesOrderIds, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $salesOrderIdsList = $decoded;
        } else {
            $salesOrderIdsList = explode(',', str_replace(['[', ']', '"'], '', $salesOrderIds));
        }
        foreach ($salesOrderIdsList as $orderId) {
            $orderId = trim($orderId);
            if (!empty($orderId) && is_numeric($orderId) && isset($salesOrderInvoices[$orderId])) {
                $orderNumbers[] = $salesOrderInvoices[$orderId];
            }
        }
        return !empty($orderNumbers) ? implode(', ', $orderNumbers) : '-';
    };

    // Group results
    $transactionGroups = [];

    foreach ($results as $result) {
        $transactionId = $result->transaction_id;

        $product_name = $result->product_name;
        if ($result->product_type == 'variable') {
            $product_name .= ' - '.$result->product_variation.' - '.$result->variation_name;
        }

        $remark_parts = [];
        if (!empty($result->additional_notes)) {
            $remark_parts[] = "Sale Note: " . trim($result->additional_notes);
        }
        if (!empty($result->payment_note)) {
            $remark_parts[] = "Payment Note: " . trim($result->payment_note);
        }
        $combined_remark = !empty($remark_parts) ? implode("\n", $remark_parts) : '-';

        $quantity = floatval($result->quantity ?: 0);
        $unit_price = floatval($result->unit_price ?: 0);
        $line_total = $quantity * $unit_price;

        // --- DETERMINE UNIT ---
        $unit_name = !empty($result->sub_unit_name) ? $result->sub_unit_name : $result->base_unit_name;
        // ----------------------

        $productLine = [
            'product_name' => $product_name,
            'sku' => $result->sku ?: '-',
            'quantity' => $quantity,
            'unit' => $unit_name ?: '', // Added unit here
            'unit_price' => $unit_price,
            'line_total' => $line_total,
            'row_num' => $result->row_num
        ];

        if (!isset($transactionGroups[$transactionId])) {
            $orderNumbers = $getOrderNumbers($result->sales_order_ids);
            $transactionGroups[$transactionId] = [
                'transaction_id' => $result->transaction_id,
                'transaction_date' => $result->transaction_date,
                'customer_name' => $result->customer_name ?: 'Walk-in Customer',
                'contact_id' => $result->contact_id ?: '-',
                'order_no' => $orderNumbers,
                'invoice_no' => $result->invoice_no,
                'transaction_final_total' => floatval($result->transaction_final_total ?: 0),
                'payment_status' => ucfirst($result->payment_status ?: 'due'),
                'delivery_person_name' => $result->delivery_person_name ?: '-',
                'remark' => $combined_remark,
                'product_lines' => [],
                'has_product_filter' => !empty($variation_id)
            ];
        }

        $transactionGroups[$transactionId]['product_lines'][] = $productLine;
    }

    $finalResults = [];
    foreach ($transactionGroups as $transaction) {
        $productCount = count($transaction['product_lines']);
        foreach ($transaction['product_lines'] as $index => $productLine) {
            $isFirstRow = ($index === 0);
            $finalResults[] = [
                'transaction_id' => $transaction['transaction_id'],
                'transaction_date' => $transaction['transaction_date'],
                'customer_name' => $transaction['customer_name'],
                'contact_id' => $transaction['contact_id'],
                'order_no' => $transaction['order_no'],
                'invoice_no' => $transaction['invoice_no'],
                'product_name' => $productLine['product_name'],
                'sku' => $productLine['sku'],
                'quantity' => $productLine['quantity'],
                'unit' => $productLine['unit'], // Added unit here
                'unit_price' => $productLine['unit_price'],
                'line_total' => $productLine['line_total'],
                'transaction_final_total' => $transaction['transaction_final_total'],
                'final_total' => !empty($variation_id) ? $productLine['line_total'] : ($isFirstRow ? $transaction['transaction_final_total'] : 0),
                'payment_status' => $transaction['payment_status'],
                'delivery_person_name' => $transaction['delivery_person_name'],
                'remark' => $transaction['remark'],
                'is_first_row' => $isFirstRow,
                'row_num' => $productLine['row_num'],
                'product_count' => $productCount,
                'has_product_filter' => $transaction['has_product_filter']
            ];
        }
    }

    return $finalResults;
    }

public function SaleDetailReport(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    
    // Fix for Locations: Use '+' to preserve ID keys instead of merge()
    $business_locations = BusinessLocation::forDropdown($business_id, true);
    $delivery_persons = User::where('business_id', $business_id)
        ->where('status', 'active')
        // 1. Calculate full_name in the database using SELECT
        ->select(
            'id', 
            DB::raw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name")
        )
        ->get()
        // 2. Pluck the calculated 'full_name' alias
        ->pluck('full_name', 'id')
        ->toArray();

    // Get selected location and date range
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');

    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');

        if (!$should_apply_filters) {
            return DataTables::of(collect([]))
                ->with(['recordsTotal' => 0, 'recordsFiltered' => 0])
                ->make(true);
        }

        // Date parsing logic
        $start_date = null;
        $end_date = null;

        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;
                foreach ($date_formats as $format) {
                    try {
                        $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                        $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) { continue; }
                }
            }
        } else {
            $start_date = \Carbon\Carbon::now()->startOfDay();
            $end_date = \Carbon\Carbon::now()->endOfDay();
        }

        $variation_id = $request->get('variation_id', null);
        $customer_id = $request->get('customer_id', null);
        $customer_group_id = $request->get('customer_group_id', null);
        $category_id = $request->get('category_id', null);
        $delivery_person_id = $request->get('delivery_person_id', null);
        $search_term = $request->input('search.value', $request->input('dt_search', null));

        $results = $this->getSalesDetailData(
            $business_id, $location_id, $start_date, $end_date, 
            $variation_id, $customer_id, $customer_group_id, 
            $category_id, $delivery_person_id, $search_term
        );

        $transactionIds = collect($results)->pluck('transaction_id')->unique();
        $transactionCount = $transactionIds->count();

        return DataTables::of(collect($results))
            ->editColumn('transaction_date', function ($row) {
                return \Carbon\Carbon::parse($row['transaction_date'])->format('Y-m-d H:i:s');
            })
            ->editColumn('unit_price', function ($row) {
                return number_format($row['unit_price'], 2, '.', '');
            })
            ->editColumn('quantity', function ($row) {
                return number_format($row['quantity'], 2, '.', '');
            })
            ->editColumn('final_total', function ($row) {
                return number_format($row['line_total'], 2, '.', '');
            })
            ->addColumn('order_no', function ($row) {
                return $row['order_no'];
            })
            ->addColumn('rowspan_data', function ($row) {
                return $row['product_count'];
            })
            ->with([
                'recordsTotal' => $transactionCount,
                'recordsFiltered' => $transactionCount
            ])
            ->rawColumns(['transaction_date', 'product_name', 'sku', 'quantity', 'unit_price', 'final_total', 'order_no', 'remark'])
            ->make(true);
    }

    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    $customers = Contact::customersDropdown($business_id);
    $categories = Category::forDropdown($business_id, 'product');
    $customer_group = CustomerGroup::forDropdown($business_id, false, true);

    return view('report.sale_detail_report')
        ->with(compact('business_locations', 'location_id', 'date_range', 'customers', 'categories', 'delivery_persons', 'customer_group'));
}

private function getSalesOrderDetailData(
    $business_id,
    $location_id,
    $start_date,
    $end_date,
    $variation_id = null,
    $customer_id = null,
    $customer_group_id = null,
    $user_id = null,
    $search_term = null
) {
    $query = DB::table('transactions as t')
        ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
        ->join('products as p', 'tsl.product_id', '=', 'p.id')
        ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
        ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
        ->leftJoin('users as u', 't.created_by', '=', 'u.id')
        // --- NEW: Join for Delivery Person ---
        ->leftJoin('users as dp', 't.delivery_person', '=', 'dp.id')
        // -------------------------------------
        ->leftJoin('units as unit', 'p.unit_id', '=', 'unit.id')
        ->leftJoin('units as sub_unit', 'tsl.sub_unit_id', '=', 'sub_unit.id')
        ->leftJoin('transaction_payments as tp', function ($join) {
            $join->on('t.id', '=', 'tp.transaction_id')
                ->whereRaw('tp.id = (SELECT MIN(id) FROM transaction_payments WHERE transaction_id = t.id)');
        })
        ->where('t.business_id', $business_id)
        ->where('t.type', 'sales_order')
        ->whereBetween('t.transaction_date', [$start_date, $end_date])
        ->whereNull('t.deleted_at')
        ->whereNull('tsl.parent_sell_line_id')
        ->where(function ($query) {
            $query->whereNull('tsl.children_type')
                ->orWhere('tsl.children_type', '');
        });

    // Search block
    if (!empty($search_term)) {
        $query->where(function ($q) use ($search_term) {
            $q->where('p.name', 'like', "%{$search_term}%")
                ->orWhere('v.sub_sku', 'like', "%{$search_term}%")
                ->orWhere('c.name', 'like', "%{$search_term}%")
                ->orWhere('c.supplier_business_name', 'like', "%{$search_term}%")
                ->orWhere('c.contact_id', 'like', "%{$search_term}%")
                ->orWhere('t.invoice_no', 'like', "%{$search_term}%");
        });
    }

    // Apply filters
    if (!empty($location_id)) {
        $query->where('t.location_id', $location_id);
    }
    if (!empty($variation_id)) {
        $query->where('tsl.variation_id', $variation_id);
    }
    if (!empty($customer_id)) {
        $query->where('t.contact_id', $customer_id);
    }
    if (!empty($customer_group_id)) {
        $query->leftjoin('customer_groups AS CG2', 'c.customer_group_id', '=', 'CG2.id')
            ->where('CG2.id', $customer_group_id);
    }
    if (!empty($user_id)) {
        $query->where('t.created_by', $user_id);
    }

    $results = $query->select([
        't.id as transaction_id',
        't.transaction_date',
        't.created_at',
        DB::raw('COALESCE(c.name, c.supplier_business_name, "Walk-inCustomer") as customer_name'),
        'c.contact_id',
        't.invoice_no',
        'p.name as product_name',
        'p.type as product_type',
        'pv.name as product_variation',
        'v.name as variation_name',
        'v.sub_sku as sku',
        'tsl.quantity',
        'tsl.unit_price',
        'unit.short_name as base_unit_name',
        'sub_unit.short_name as sub_unit_name',
        DB::raw('CAST(t.final_total AS DECIMAL(22,4)) as transaction_final_total'),
        't.status',
        't.payment_status',
        't.additional_notes',
        'tp.note as payment_note',
        'u.first_name as created_by_user',
        // --- NEW: Select Delivery Person Name ---
        DB::raw("CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, '')) as delivery_person_name"),
        // ----------------------------------------
        DB::raw('ROW_NUMBER() OVER (PARTITION BY t.id ORDER BY tsl.id) as row_num')
    ])
        ->orderBy('t.transaction_date', 'desc')
        ->orderBy('t.invoice_no', 'asc')
        ->orderBy('tsl.id', 'asc')
        ->get();

    if ($results->isEmpty()) {
        return [];
    }

    $transactionGroups = [];

    foreach ($results as $result) {
        $transactionId = $result->transaction_id;

        $product_name = $result->product_name;
        if ($result->product_type == 'variable') {
            $product_name .= ' - ' . $result->product_variation . ' - ' . $result->variation_name;
        }

        $remark_parts = [];
        if (!empty($result->additional_notes)) {
            $remark_parts[] = "Sale Order Note: " . trim($result->additional_notes);
        }
        if (!empty($result->payment_note)) {
            $remark_parts[] = "Payment Note: " . trim($result->payment_note);
        }
        $combined_remark = !empty($remark_parts) ? implode("\n", $remark_parts) : '-';

        $quantity = floatval($result->quantity ?: 0);
        $unit_price = floatval($result->unit_price ?: 0);
        $line_total = $quantity * $unit_price;

        $unit_name = !empty($result->sub_unit_name) ? $result->sub_unit_name : $result->base_unit_name;

        $productLine = [
            'product_name' => $product_name,
            'sku' => $result->sku ?: '-',
            'quantity' => $quantity,
            'unit' => $unit_name ?: '',
            'unit_price' => $unit_price,
            'line_total' => $line_total,
            'row_num' => $result->row_num
        ];

        if (!isset($transactionGroups[$transactionId])) {
            $transactionGroups[$transactionId] = [
                'transaction_id' => $result->transaction_id,
                'transaction_date' => $result->transaction_date,
                'customer_name' => $result->customer_name ?: 'Walk-in Customer',
                'contact_id' => $result->contact_id ?: '-',
                'invoice_no' => $result->invoice_no,
                'transaction_final_total' => floatval($result->transaction_final_total ?: 0),
                'status' => ucfirst($result->status ?: '-'),
                'payment_status' => ucfirst($result->payment_status ?: 'due'),
                'remark' => $combined_remark,
                'created_by_user' => $result->created_by_user ?: 'N/A',
                // --- NEW: Add Delivery Person to Group ---
                'delivery_person_name' => $result->delivery_person_name ?: '-',
                // -----------------------------------------
                'created_at' => $result->created_at,
                'product_lines' => [],
                'has_product_filter' => !empty($variation_id)
            ];
        }

        $transactionGroups[$transactionId]['product_lines'][] = $productLine;
    }

    $finalResults = [];
    foreach ($transactionGroups as $transaction) {
        $productCount = count($transaction['product_lines']);
        foreach ($transaction['product_lines'] as $index => $productLine) {
            $isFirstRow = ($index === 0);
            $finalResults[] = [
                'transaction_id' => $transaction['transaction_id'],
                'transaction_date' => $transaction['transaction_date'],
                'customer_name' => $transaction['customer_name'],
                'contact_id' => $transaction['contact_id'],
                'invoice_no' => $transaction['invoice_no'],
                'product_name' => $productLine['product_name'],
                'sku' => $productLine['sku'],
                'quantity' => $productLine['quantity'],
                'unit' => $productLine['unit'],
                'unit_price' => $productLine['unit_price'],
                'line_total' => $productLine['line_total'],
                'transaction_final_total' => $transaction['transaction_final_total'],
                'final_total' => !empty($variation_id) ? $productLine['line_total'] : ($isFirstRow ? $transaction['transaction_final_total'] : 0),
                'status' => $transaction['status'],
                'payment_status' => $transaction['payment_status'],
                'remark' => $transaction['remark'],
                'created_by_user' => $transaction['created_by_user'],
                // --- NEW: Pass Delivery Person to Final Result ---
                'delivery_person_name' => $transaction['delivery_person_name'],
                // -------------------------------------------------
                'is_first_row' => $isFirstRow,
                'row_num' => $productLine['row_num'],
                'product_count' => $productCount,
                'has_product_filter' => $transaction['has_product_filter']
            ];
        }
    }

    return $finalResults;
}

public function SalesOrderDetailReport(Request $request)
{
    $business_id = $request->session()->get('user.business_id');

    // Add "All" option to business locations dropdown
    $business_locations = BusinessLocation::forDropdown(
        $business_id,
        true
    );
    $business_locations = collect([
        '' =>
            __('All')
    ])->merge($business_locations)->toArray();

    // Get selected location and date range from request
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');

    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true ||
            $apply_filters === 'true' || $apply_filters === '1');

        // Return empty structure if filters not applied
        if (!$should_apply_filters) {
            return DataTables::of(collect([]))
                ->with([
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0
                ])
                ->make(true);
        }

        // Parse date range with improved error handling
        $start_date = null;
        $end_date = null;

        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;

                foreach ($date_formats as $format) {
                    try {
                        $start_date =
                            \Carbon\Carbon::createFromFormat(
                                $format,
                                trim($dates[0])
                            )->startOfDay();
                        $end_date =
                            \Carbon\Carbon::createFromFormat(
                                $format,
                                trim($dates[1])
                            )->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!$parsed) {
                    \Log::error('Date parsing failed for range: ' .
                        $date_range);
                    return response()->json([
                        'error' => 'Invalid date format'
                    ], 400);
                }
            }
        } else {
            // Default to current date if no date range provided
            $start_date = \Carbon\Carbon::now()->startOfDay();
            $end_date = \Carbon\Carbon::now()->endOfDay();
        }

        // Get additional filter parameters
        $variation_id = $request->get('variation_id', null);
        $customer_id = $request->get('customer_id', null);
        $customer_group_id = $request->get(
            'customer_group_id',
            null
        );
        // Removed fetching category_id and brand_id
        $user_id = $request->get('user_id', null); // New: Get user_id filter
        $search_term = $request->input(
            'search.value',
            $request->input('dt_search', null)
        );

        // Fetch sales order detail data with filters
        $results = $this->getSalesOrderDetailData(
            $business_id,
            $location_id,
            $start_date,
            $end_date,
            $variation_id,
            $customer_id,
            $customer_group_id,
            $user_id, // Passed user_id
            $search_term
        );

        // Count unique transactions for proper pagination
        $transactionIds =
            collect($results)->pluck('transaction_id')->unique();
        $transactionCount = $transactionIds->count();

        // Create DataTables response with transaction-based counting
        return DataTables::of(collect($results))
            ->editColumn('transaction_date', function ($row) {
                return
                    \Carbon\Carbon::parse($row['transaction_date'])->format('Y-m-d H:i:s');
            })
            ->editColumn('unit_price', function ($row) {
                return number_format($row['unit_price'], 2, '.', '');
            })
            ->editColumn('quantity', function ($row) {
                return number_format($row['quantity'], 2, '.', '');
            })
            ->editColumn('final_total', function ($row) {
                return number_format($row['line_total'], 2, '.', '');
            })
            ->addColumn('created_by_user', function ($row) {
                return $row['created_by_user'];
            })
            ->addColumn('rowspan_data', function ($row) {
                return $row['product_count'];
            })
            ->with([
                'recordsTotal' => $transactionCount,
                'recordsFiltered' => $transactionCount
            ])
            ->rawColumns([
                'transaction_date',
                'product_name',
                'sku',
                'quantity',
                'unit_price',
                'final_total',
                'created_by_user',
                'remark'
            ])
            ->make(true);
    }

    // Set default date range to current date only
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->format('Y-m-d') . ' ~ ' .
            \Carbon\Carbon::now()->format('Y-m-d');
    }

    // Get dropdown data for filters
    $customers = Contact::customersDropdown($business_id);
    // Removed categories and brands retrieval
    $customer_group = CustomerGroup::forDropdown(
        $business_id,
        false,
        true
    );
    // New: Fetch users for the filter (assuming User::forDropdown exists)
    $users = User::forDropdown($business_id, false);

    return view('report.sales_order_detail_report')
        // Passed $users instead of $categories and $brands
        ->with(compact(
            'business_locations',
            'location_id',
            'date_range',
            'customers',
            'customer_group',
            'users'
        ));
}

private function getSalesDetailDataSale(
    $business_id, 
    $location_id, 
    $start_date, 
    $end_date, 
    $variation_id = null, 
    $customer_id = null, 
    $customer_group_id = null, 
    $category_id = null, 
    $brand_id = null, 
    $search_term = null
)
{
    $query = DB::table('transactions as t')
        ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
        ->join('products as p', 'tsl.product_id', '=', 'p.id')
        ->join('variations as v', 'tsl.variation_id', '=', 'v.id')
        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
        ->leftJoin('contacts as c', 't.contact_id', '=', 'c.id')
        // --- NEW: Join for Delivery Person ---
        ->leftJoin('users as dp', 't.delivery_person', '=', 'dp.id')
        // -------------------------------------
        ->leftJoin('units as unit', 'p.unit_id', '=', 'unit.id')
        ->leftJoin('units as sub_unit', 'tsl.sub_unit_id', '=', 'sub_unit.id')
        ->leftJoin('transaction_payments as tp', function($join) {
            $join->on('t.id', '=', 'tp.transaction_id')
                    ->whereRaw('tp.id = (SELECT MIN(id) FROM transaction_payments WHERE transaction_id = t.id)');
        })
        ->where('t.business_id', $business_id)
        ->where('t.type', 'sell')
        ->whereBetween('t.transaction_date', [$start_date, $end_date])
        ->whereNull('t.deleted_at')
        ->whereNull('tsl.parent_sell_line_id')
        ->where(function($query) {
            $query->whereNull('tsl.children_type')
                    ->orWhere('tsl.children_type', '');
        });
    
    // Search logic
    if (!empty($search_term)) {
        $query->where(function($q) use ($search_term, $business_id) {
            $q->where('p.name', 'like', "%{$search_term}%")
                ->orWhere('v.sub_sku', 'like', "%{$search_term}%")
                ->orWhere('c.name', 'like', "%{$search_term}%")
                ->orWhere('c.supplier_business_name', 'like', "%{$search_term}%")
                ->orWhere('c.contact_id', 'like', "%{$search_term}%")
                ->orWhere('t.invoice_no', 'like', "%{$search_term}%")
                ->orWhereExists(function ($subquery) use ($search_term, $business_id) {
                    $subquery->select(DB::raw(1))
                        ->from('transactions as so')
                        ->where('so.type', 'sales_order')
                        ->where('so.business_id', $business_id)
                        ->where('so.invoice_no', 'like', "%{$search_term}%")
                        ->whereRaw("t.sales_order_ids LIKE CONCAT('%\"', so.id, '\"%')");
                });
        });
    }

    // Apply filters
    if (!empty($location_id)) {
        $query->where('t.location_id', $location_id);
    }
    if (!empty($variation_id)) {
        $query->where('tsl.variation_id', $variation_id);
    }
    if (!empty($customer_id)) {
        $query->where('t.contact_id', $customer_id);
    }
    if (!empty($customer_group_id)) {
        $query->leftjoin('customer_groups AS CG2', 'c.customer_group_id', '=', 'CG2.id')
            ->where('CG2.id', $customer_group_id);
    }
    if (!empty($category_id)) {
        $query->where('p.category_id', $category_id);
    }
    if (!empty($brand_id)) {
        $query->where('p.brand_id', $brand_id);
    }

    $results = $query->select([
            't.id as transaction_id',
            't.transaction_date',
            DB::raw('COALESCE(c.name, c.supplier_business_name, "Walk-in Customer") as customer_name'),
            'c.contact_id',
            't.sales_order_ids',
            't.invoice_no',
            'p.name as product_name',
            'p.type as product_type',
            'pv.name as product_variation',
            'v.name as variation_name',
            'v.sub_sku as sku',
            'tsl.quantity',
            'tsl.unit_price',
            'unit.short_name as base_unit_name',
            'sub_unit.short_name as sub_unit_name',
            DB::raw('CAST(t.final_total AS DECIMAL(22,4)) as transaction_final_total'),
            't.shipping_status',
            't.payment_status',
            't.additional_notes',
            'tp.note as payment_note',
            // --- NEW: Select Delivery Person Name ---
            DB::raw("CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, '')) as delivery_person_name"),
            // ----------------------------------------
            DB::raw('ROW_NUMBER() OVER (PARTITION BY t.id ORDER BY tsl.id) as row_num')
        ])
        ->orderBy('t.transaction_date', 'desc')
        ->orderBy('t.invoice_no', 'asc')
        ->orderBy('tsl.id', 'asc')
        ->get();
        
    if ($results->isEmpty()) {
        return [];
    }

    // Get Sales Order Invoices logic (unchanged)
    $allSalesOrderIds = [];
    foreach ($results as $result) {
        if (!empty($result->sales_order_ids)) {
            $salesOrderIds = [];
            $decoded = json_decode($result->sales_order_ids, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $salesOrderIds = $decoded;
            } else {
                $salesOrderIds = explode(',', str_replace(['[', ']', '"'], '', $result->sales_order_ids));
            }
            foreach ($salesOrderIds as $orderId) {
                $orderId = trim($orderId);
                if (!empty($orderId) && is_numeric($orderId)) {
                    $allSalesOrderIds[] = $orderId;
                }
            }
        }
    }

    $salesOrderInvoices = [];
    if (!empty($allSalesOrderIds)) {
        $salesOrders = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sales_order')
            ->whereIn('id', array_unique($allSalesOrderIds))
            ->whereNull('deleted_at')
            ->pluck('invoice_no', 'id')
            ->toArray();
        $salesOrderInvoices = $salesOrders;
    }

    $getOrderNumbers = function($salesOrderIds) use ($salesOrderInvoices) {
        if (empty($salesOrderIds)) { return '-'; }
        $orderNumbers = [];
        $decoded = json_decode($salesOrderIds, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $salesOrderIdsList = $decoded;
        } else {
            $salesOrderIdsList = explode(',', str_replace(['[', ']', '"'], '', $salesOrderIds));
        }
        foreach ($salesOrderIdsList as $orderId) {
            $orderId = trim($orderId);
            if (!empty($orderId) && is_numeric($orderId) && isset($salesOrderInvoices[$orderId])) {
                $orderNumbers[] = $salesOrderInvoices[$orderId];
            }
        }
        return !empty($orderNumbers) ? implode(', ', $orderNumbers) : '-';
    };

    $transactionGroups = [];
    
    foreach ($results as $result) {
        $transactionId = $result->transaction_id;
        
        $product_name = $result->product_name;
        if ($result->product_type == 'variable') {
            $product_name .= ' - '.$result->product_variation.' - '.$result->variation_name;
        }

        $remark_parts = [];
        if (!empty($result->additional_notes)) {
            $remark_parts[] = "Sale Note: " . trim($result->additional_notes);
        }
        if (!empty($result->payment_note)) {
            $remark_parts[] = "Payment Note: " . trim($result->payment_note);
        }
        $combined_remark = !empty($remark_parts) ? implode("\n", $remark_parts) : '-';

        $quantity = floatval($result->quantity ?: 0);
        $unit_price = floatval($result->unit_price ?: 0);
        $line_total = $quantity * $unit_price;

        $unit_name = !empty($result->sub_unit_name) ? $result->sub_unit_name : $result->base_unit_name;

        $productLine = [
            'product_name' => $product_name,
            'sku' => $result->sku ?: '-',
            'quantity' => $quantity,
            'unit' => $unit_name ?: '',
            'unit_price' => $unit_price,
            'line_total' => $line_total,
            'row_num' => $result->row_num
        ];

        if (!isset($transactionGroups[$transactionId])) {
            $orderNumbers = $getOrderNumbers($result->sales_order_ids);
            $transactionGroups[$transactionId] = [
                'transaction_id' => $result->transaction_id,
                'transaction_date' => $result->transaction_date,
                'customer_name' => $result->customer_name ?: 'Walk-in Customer',
                'contact_id' => $result->contact_id ?: '-',
                'order_no' => $orderNumbers,
                'invoice_no' => $result->invoice_no,
                'transaction_final_total' => floatval($result->transaction_final_total ?: 0),
                'shipping_status' => ucfirst($result->shipping_status ?: '-'),
                'payment_status' => ucfirst($result->payment_status ?: 'due'),
                'remark' => $combined_remark,
                // --- NEW: Add Delivery Person to Group ---
                'delivery_person_name' => $result->delivery_person_name ?: '-',
                // -----------------------------------------
                'product_lines' => [],
                'has_product_filter' => !empty($variation_id)
            ];
        }

        $transactionGroups[$transactionId]['product_lines'][] = $productLine;
    }

    $finalResults = [];
    foreach ($transactionGroups as $transaction) {
        $productCount = count($transaction['product_lines']);
        foreach ($transaction['product_lines'] as $index => $productLine) {
            $isFirstRow = ($index === 0);
            $display_total = !empty($variation_id) ? $productLine['line_total'] : $transaction['transaction_final_total'];
            
            $finalResults[] = [
                'transaction_id' => $transaction['transaction_id'],
                'transaction_date' => $transaction['transaction_date'],
                'customer_name' => $transaction['customer_name'],
                'contact_id' => $transaction['contact_id'],
                'order_no' => $transaction['order_no'],
                'invoice_no' => $transaction['invoice_no'],
                'product_name' => $productLine['product_name'],
                'sku' => $productLine['sku'],
                'quantity' => $productLine['quantity'],
                'unit' => $productLine['unit'],
                'unit_price' => $productLine['unit_price'],
                'line_total' => $productLine['line_total'],
                'transaction_final_total' => $transaction['transaction_final_total'],
                'final_total' => $display_total,
                'shipping_status' => $transaction['shipping_status'],
                'payment_status' => $transaction['payment_status'],
                'remark' => $transaction['remark'],
                // --- NEW: Pass Delivery Person to Final Result ---
                'delivery_person_name' => $transaction['delivery_person_name'],
                // -------------------------------------------------
                'is_first_row' => $isFirstRow,
                'row_num' => $productLine['row_num'],
                'product_count' => $productCount,
                'has_product_filter' => $transaction['has_product_filter']
            ];
        }
    }
    
    return $finalResults;
}

public function SaleDetailReportForSell(Request $request)
{
    $business_id = $request->session()->get('user.business_id');
    // Add "All" option to business locations dropdown
    $business_locations = BusinessLocation::forDropdown($business_id, true);
    $business_locations = collect(['' => __('All')])->merge($business_locations)->toArray();
    // Get selected location and date range from request
    $location_id = $request->input('sell_list_filter_location_id');
    $date_range = $request->input('sell_list_filter_date_range');
    // Handle AJAX requests
    if ($request->ajax() || $request->input('ajax')) {
        $apply_filters = $request->input('apply_filters');
        $should_apply_filters = ($apply_filters === true || $apply_filters === 'true' || $apply_filters === '1');
        // Return empty structure if filters not applied
        if (!$should_apply_filters) {
            return DataTables::of(collect([]))
                ->with([
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0
                ])
                ->make(true);
        }

        // Parse date range with improved error handling
        $start_date = null;
        $end_date = null;
        
        if ($date_range) {
            $dates = explode(' ~ ', $date_range);
            if (count($dates) == 2) {
                $date_formats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
                $parsed = false;
                
                foreach ($date_formats as $format) {
                    try {
                        $start_date = \Carbon\Carbon::createFromFormat($format, trim($dates[0]))->startOfDay();
                        $end_date = \Carbon\Carbon::createFromFormat($format, trim($dates[1]))->endOfDay();
                        $parsed = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                if (!$parsed) {
                    \Log::error('Date parsing failed for range: ' . $date_range);
                    return response()->json(['error' => 'Invalid date format'], 400);
                }
            }
        } else {
            // Default to current date if no date range provided
            $start_date = \Carbon\Carbon::now()->startOfDay();
            $end_date = \Carbon\Carbon::now()->endOfDay();
        }
        
        // Get additional filter parameters
        $variation_id = $request->get('variation_id', null);
        $customer_id = $request->get('customer_id', null);
        $customer_group_id = $request->get('customer_group_id', null);
        $category_id = $request->get('category_id', null);
        $brand_id = $request->get('brand_id', null);
        
        // FIX: Accept search term from DataTables default OR our custom export parameter
        $search_term = $request->input('search.value', $request->input('dt_search', null));

        // Fetch sales detail data with filters
        $results = $this->getSalesDetailDataSale(
            $business_id, 
            $location_id, 
            $start_date, 
            $end_date, 
            $variation_id, 
            $customer_id, 
            $customer_group_id, 
            $category_id, 
            $brand_id,
            $search_term
        );

        // Count unique transactions for proper pagination
        $transactionIds = collect($results)->pluck('transaction_id')->unique();
        $transactionCount = $transactionIds->count();
        // Create DataTables response with transaction-based counting
        return DataTables::of(collect($results))
            ->editColumn('transaction_date', function ($row) {
                return \Carbon\Carbon::parse($row['transaction_date'])->format('Y-m-d H:i:s');
            })
            ->editColumn('quantity', function ($row) {
                // FIX: Removed thousands separator to prevent JS parsing errors
                return number_format($row['quantity'], 2, '.', '');
            })
            ->addColumn('order_no', function ($row) {
                return $row['order_no']; // Add the order_no column
            })
            ->addColumn('rowspan_data', function ($row) {
                return $row['product_count'];
            })
            ->with([
                'recordsTotal' => $transactionCount, // Count of unique transactions
                'recordsFiltered' => $transactionCount // Count of unique transactions
            ])
            ->rawColumns(['transaction_date', 'product_name', 'sku', 'quantity', 'order_no', 'remark'])
            ->make(true);
    }

    // Set default date range to current date only
    if (!$date_range) {
        $date_range = \Carbon\Carbon::now()->format('Y-m-d') . ' ~ ' . \Carbon\Carbon::now()->format('Y-m-d');
    }

    // Get dropdown data for filters
    $customers = Contact::customersDropdown($business_id);
    $categories = Category::forDropdown($business_id, 'product');
    $brands = Brands::forDropdown($business_id);
    $customer_group = CustomerGroup::forDropdown($business_id, false, true);
    return view('report.sale_detail_for_sales')
        ->with(compact('business_locations', 'location_id', 'date_range', 'customers', 'categories', 'brands', 'customer_group'));
}

public function SaleRevenueAR(Request $request)
{
    $business_id = $request->session()->get('user.business_id');

    // ---------------------------------------------------------------
    // Filter: from_month → to_month
    // Default: last 3 months (e.g. 2026-01 → 2026-03)
    // ---------------------------------------------------------------
    $now            = \Carbon\Carbon::now();
    $toMonthParam   = $request->get('to_month',   $now->format('Y-m'));
    $fromMonthParam = $request->get('from_month', $now->copy()->subMonths(2)->format('Y-m'));

    try {
        $toDate   = \Carbon\Carbon::createFromFormat('Y-m', $toMonthParam)->startOfMonth();
        $fromDate = \Carbon\Carbon::createFromFormat('Y-m', $fromMonthParam)->startOfMonth();
    } catch (\Exception $e) {
        $toDate   = $now->copy()->startOfMonth();
        $fromDate = $now->copy()->subMonths(2)->startOfMonth();
    }

    // Ensure from <= to
    if ($fromDate->gt($toDate)) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }

    // Build list of months newest → oldest  (e.g. ['2026-03','2026-02','2026-01'])
    $monthRange = [];
    $cursor = $toDate->copy();
    while ($cursor->gte($fromDate)) {
        $monthRange[] = $cursor->format('Y-m');
        $cursor->subMonth();
    }

    // ---------------------------------------------------------------
    // Helper: total invoiced for transactions dated in [start, end]
    // ---------------------------------------------------------------
    $getInvoiced = function ($start, $end) use ($business_id) {
        return (float) DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereNull('deleted_at')
            ->whereBetween('transaction_date', [
                $start->toDateTimeString(),
                $end->toDateTimeString(),
            ])
            ->sum('final_total');
    };

    // ---------------------------------------------------------------
    // Helper A: ALL net payments ever for invoices in [invStart, invEnd]
    //           No pay-date restriction → matches sell list "total paid"
    //           Uses SUM(IF(is_return=1,-1*amount,amount)) to match
    //           SellController::getPaymentTotals() exactly.
    // ---------------------------------------------------------------
    $getAllPayments = function ($invStart, $invEnd) use ($business_id) {
        return (float) DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '>=', $invStart->toDateString())
            ->whereDate('t.transaction_date', '<=', $invEnd->toDateString())
            ->value(DB::raw('COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)'));
    };

    // ---------------------------------------------------------------
    // Helper B: net payments for invoices in [invStart, invEnd]
    //           where payment was collected IN [payStart, payEnd]
    //           Used only for past-month A/R recovery rows.
    // ---------------------------------------------------------------
    $getPaymentsInPeriod = function ($invStart, $invEnd, $payStart, $payEnd) use ($business_id) {
        return (float) DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '>=', $invStart->toDateString())
            ->whereDate('t.transaction_date', '<=', $invEnd->toDateString())
            ->whereDate('tp.paid_on', '>=', $payStart->toDateString())
            ->whereDate('tp.paid_on', '<=', $payEnd->toDateString())
            ->value(DB::raw('COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0)'));
    };

    // ---------------------------------------------------------------
    // Loop each month in the range — run the SAME calculation structure
    // as the original single-month version, store result in $monthsData
    // ---------------------------------------------------------------
    $monthsData = [];

    foreach ($monthRange as $monthParam) {

        $refDate = \Carbon\Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth();

        $currentStart = $refDate->copy()->startOfMonth();
        $currentEnd   = $refDate->copy()->endOfMonth();
        $currentKey   = $refDate->format('Y-m');
        $currentLabel = $refDate->format('m/Y');

        // -------------------------------------------------------
        // 1. Sale Revenue = total invoiced in the current month
        // -------------------------------------------------------
        $saleRevenue = $getInvoiced($currentStart, $currentEnd);

        // -------------------------------------------------------
        // 2. Build data dynamically for ALL months that have:
        //      (a) outstanding A/R balance > 0  (payment_status due or partial), OR
        //      (b) payments received in the current month (AR recovered)
        //    Always include the current month itself.
        //
        //    receivedAR[$key]  = payments collected IN THE CURRENT MONTH
        //                        for invoices that belong to month $key
        //
        //    arBalance[$key]   = currently outstanding balance for month $key
        //                        = invoiced($key) − ALL payments to date
        // -------------------------------------------------------
        $arLabels   = [];   // column header labels  (key => 'A/R MM/YYYY')
        $receivedAR = [];   // collected in current month per invoice-month
        $arBalance  = [];   // currently outstanding per invoice-month

        // --- Step A: find all distinct invoice months from DB ---

        // Months that have invoices with outstanding balance (payment_status due or partial)
        $monthsWithAR = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereIn('payment_status', ['due', 'partial'])
            ->whereNull('deleted_at')
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') as ym")
            ->groupBy('ym')
            ->pluck('ym')
            ->toArray();

        // Months that have payments received in the current month (AR recovered from past months)
        $monthsWithReceivedAR = DB::table('transaction_payments as tp')
            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('t.deleted_at')
            ->whereDate('tp.paid_on', '>=', $currentStart->toDateString())
            ->whereDate('tp.paid_on', '<=', $currentEnd->toDateString())
            ->selectRaw("DATE_FORMAT(t.transaction_date, '%Y-%m') as ym")
            ->groupBy('ym')
            ->pluck('ym')
            ->toArray();

        // Merge all relevant months, always include current month, sort newest → oldest
        // IMPORTANT: only include months <= $currentKey — never show future months in a past box
       $allMonths = array_unique(
    array_merge([$currentKey], $monthsWithAR, $monthsWithReceivedAR)
);
        rsort($allMonths); // newest first

        // --- Step B: compute received & balance for each month ---
        foreach ($allMonths as $key) {
            $mDate  = \Carbon\Carbon::createFromFormat('Y-m', $key)->startOfMonth();
            $mStart = $mDate->copy()->startOfMonth();
            $mEnd   = $mDate->copy()->endOfMonth();

            $arLabels[$key] = 'A/R ' . $mDate->format('m/Y');

            $invoiced = $getInvoiced($mStart, $mEnd);

            $totalPaidEver   = $getAllPayments($mStart, $mEnd);
            $arBalance[$key] = max(0.0, $invoiced - $totalPaidEver);

            // Always use paid_on filter: only show payments collected IN the current month
            // (regardless of whether the invoice is from the current month or a past month)
            $receivedAR[$key] = $getPaymentsInPeriod($mStart, $mEnd, $currentStart, $currentEnd);
        }

        // Alias used in the view for the current-month received figure
        $receivedInMonth = $receivedAR[$currentKey];

        // -------------------------------------------------------
        // 3. Totals
        //    Total Revenue = everything collected in the current month
        //                    (current-month invoices + AR recovered from past months)
        //    Total A/R     = sum of all currently outstanding balances
        // -------------------------------------------------------
        $totalRevenue = array_sum($receivedAR);
        $totalAR      = array_sum($arBalance);

        // Store result for this month — same variable names as original
        $monthsData[$currentKey] = compact(
            'refDate',
            'monthParam',
            'currentLabel',
            'currentKey',
            'arLabels',
            'saleRevenue',
            'receivedInMonth',
            'receivedAR',
            'arBalance',
            'totalRevenue',
            'totalAR'
        );
    }

    return view('report.sale_revenue_ar', compact(
        'monthsData',
        'fromMonthParam',
        'toMonthParam'
    ));
}

public function ClaimReport(Request $request)
{
    $business_id = 53; 

    // =============================================================================================
    // MASTER CONFIGURATION LIST
    // =============================================================================================
    $report_config = [
        ['id' => 1619, 'name' => 'ស្រីម៉ៅ (អូបែកក្អម)', 'address' => 'ផ្សារ សំណង់', 'phone' => '012915226', 'contact_id_display' => 'CO005'],
        ['id' => 5611, 'name' => 'ហ៊ុយ ហុង', 'address' => 'ផ្លូវ ២៧១', 'phone' => '0889992323', 'contact_id_display' => 'CO1860'],
        ['id' => 6192, 'name' => 'ចែ ខេង', 'address' => 'ត្រពាំងឈូក', 'phone' => '0763172013', 'contact_id_display' => 'CO2003'],
        ['id' => 3378, 'name' => 'ហ៊ា រិទ្ធ', 'address' => 'ត្រពាំងឈូក', 'phone' => '092567565', 'contact_id_display' => 'CO0507'],
        ['id' => 1893, 'name' => 'គួច ស្រីពៅ (អូបែកក្អម)', 'address' => 'St, Betong', 'phone' => '012259845', 'contact_id_display' => 'CO0041'],
        ['id' => 3790, 'name' => 'ម៉ាក់ ស្រីត្តី', 'address' => 'ផ្លូវ ២០០២', 'phone' => '010862656', 'contact_id_display' => 'CO0756'],
        ['id' => 1853, 'name' => 'ហេង ណាវី', 'address' => 'ផ្លូវ ២០០២', 'phone' => '011457416', 'contact_id_display' => 'CO033'],
        ['id' => 1655, 'name' => 'សុផាវី វឌ្ឍនា', 'address' => 'ផ្លូវ ២០០៤', 'phone' => '012525893', 'contact_id_display' => 'CO0023'],
        ['id' => 3109, 'name' => 'ចែ មុំ', 'address' => 'St, 371', 'phone' => '0963078885', 'contact_id_display' => 'CO0313'],
        ['id' => 8230, 'name' => 'អ៊ូច ណាង', 'address' => 'St, 2002', 'phone' => '070810624', 'contact_id_display' => 'CO2114'],
        ['id' => 1812, 'name' => 'ហេង សុភាព', 'address' => 'St, 2002', 'phone' => '010943534', 'contact_id_display' => 'CO0108'],
        ['id' => 2334, 'name' => 'គង់ បញ្ញា', 'address' => 'ផ្លូវបេតុង', 'phone' => '092248911', 'contact_id_display' => 'CO0080'],
        ['id' => 1686, 'name' => 'ហ៊ុយ គុជរស្មី', 'address' => 'ផ្លូវបេតុង', 'phone' => '012640113', 'contact_id_display' => 'CO0043'],
        ['id' => 1724, 'name' => 'សុខឈាន់', 'address' => 'ផ្លូវបេតុង', 'phone' => '010781864', 'contact_id_display' => 'CO0067'],
        ['id' => 3114, 'name' => 'ម៉ាក់ នីង នីង', 'address' => 'St, 371', 'phone' => '092815315', 'contact_id_display' => 'CO0318'],
        ['id' => 4102, 'name' => 'វណ្ណះ', 'address' => 'ផ្លូវបេតុង', 'phone' => '016663022', 'contact_id_display' => 'CO0995'],
        ['id' => 6104, 'name' => 'ម៉ាក់អូនស៊ីង(ឡូវលីនី)', 'address' => 'ផ្លូវបេតុង', 'phone' => '012556169', 'contact_id_display' => 'CO1978'],
        ['id' => 3911, 'name' => 'ម៉ាក់សៀងហៃ', 'address' => 'ផ្លូវបេតុង', 'phone' => '0964922806', 'contact_id_display' => 'CO0847'],
        ['id' => 1834, 'name' => 'ម៉ាក់ ឆេវ៉ុន', 'address' => 'St. 2002', 'phone' => '017955800', 'contact_id_display' => 'CO0127'],
        ['id' => 5739, 'name' => 'សុភ័ក្រ', 'address' => 'St, 2002', 'phone' => '012851221', 'contact_id_display' => 'CO1904'],
        ['id' => 30574, 'name' => 'សុជាតិ', 'address' => 'ត្រពាំងឈូក', 'phone' => '012638030', 'contact_id_display' => 'CO3111'],
        ['id' => 8073, 'name' => 'ម៉ាក់ អាហួយ', 'address' => 'ផ្លូវលំ បឹងឈូក', 'phone' => '012466848', 'contact_id_display' => 'CO2095'],
        ['id' => 3630, 'name' => 'ម៉ាក់ សុខនីម', 'address' => 'ផ្លូវបេតុង', 'phone' => '086418668', 'contact_id_display' => 'CO0660'],
        ['id' => 1869, 'name' => 'ម៉ាក់ លីហ្សា', 'address' => 'St. 11A', 'phone' => '098421345', 'contact_id_display' => 'CO0039'],
        ['id' => 1946, 'name' => 'សុខ សិរីវឌ្ឍនះ', 'address' => 'St, 345', 'phone' => '069838486', 'contact_id_display' => 'CO0193'],
        ['id' => 3731, 'name' => 'ដេប៉ូ ២៧១', 'address' => 'St, 271', 'phone' => '015952015', 'contact_id_display' => 'CO0724'],
        ['id' => 6206, 'name' => 'ចែ ហ័ង', 'address' => '100 ខ្នង', 'phone' => '0976084963', 'contact_id_display' => 'CO2007'],
        ['id' => 5736, 'name' => 'ដេត ស៊ីណា', 'address' => '11A', 'phone' => '015652476', 'contact_id_display' => 'CO1901'],
        ['id' => 2046, 'name' => 'ង៉ែត សុខា', 'address' => 'ផ្លូវបេតុង', 'phone' => '081674515', 'contact_id_display' => 'CO0230'],
        ['id' => 2929, 'name' => 'យូ អាន (ទឹកថ្លា)', 'address' => 'ផ្លូវបេតុង ទឹកថ្លា', 'phone' => '0969799958', 'contact_id_display' => 'CO0225'],
        ['id' => 9491, 'name' => 'ស៊ី ម៉ៅ ទឹកថ្លា', 'address' => '11A', 'phone' => '078759966', 'contact_id_display' => 'CO2267'],
        ['id' => 11456, 'name' => 'ស្រីមុំទឹកថ្លា', 'address' => 'ផ្លូវបេតុង', 'phone' => '087678179', 'contact_id_display' => 'CO2793'],
        ['id' => 6228, 'name' => 'ចែ ស្រីពៅ អូបែកក្អម', 'address' => 'ផ្លូវ ១០១៩', 'phone' => '012410533', 'contact_id_display' => 'CO2013'],
        ['id' => 1642, 'name' => 'ហ៊ា សុភាព (ភ្នំពេញថ្មី)', 'address' => 'ផ្លូវ១០១៩', 'phone' => '078605620', 'contact_id_display' => 'CO012'],
        ['id' => 1776, 'name' => 'អាស្រស់', 'address' => 'St. 1019', 'phone' => '012371815', 'contact_id_display' => 'CO0090'],
        ['id' => null, 'name' => 'នាង សាម៉ុន', 'address' => 'St, 1986', 'phone' => '089260567', 'contact_id_display' => '#N/A'],
        ['id' => 1687, 'name' => 'ហេង ឡុង', 'address' => 'St. 1007', 'phone' => '010531312', 'contact_id_display' => 'CO0044'],
        ['id' => 8008, 'name' => 'ជា សុគៀត (ភ្នំពេញថ្មី)', 'address' => 'ផ្លូវ១៩៦០', 'phone' => '012596900', 'contact_id_display' => 'CO2086'],
        ['id' => 1780, 'name' => 'កុសល ណាវី', 'address' => 'ផ្លូវ ម៉ុងរិទ្ធី', 'phone' => '012525549', 'contact_id_display' => 'CO0094'],
        ['id' => 3293, 'name' => 'ចន្ថា ដាណេ (ភ្នំពេញថ្មី)', 'address' => 'St. 1007', 'phone' => '089282484', 'contact_id_display' => 'CO0445'],
        ['id' => null, 'name' => 'ប៊ុន នី', 'address' => 'St, 598', 'phone' => '010626445', 'contact_id_display' => '#N/A'],
        ['id' => 3812, 'name' => 'ម៉ាក់ សុជាតា', 'address' => 'St, 1019', 'phone' => '070699878', 'contact_id_display' => 'CO0775'],
        ['id' => 1651, 'name' => 'អ៊ុក មុំ', 'address' => 'St, 1015', 'phone' => '0763333263', 'contact_id_display' => 'CO0019'],
        ['id' => 1835, 'name' => 'សំនិត ផល្លីន', 'address' => 'St, 1015', 'phone' => '081855048', 'contact_id_display' => 'CO00128'],
        ['id' => null, 'name' => 'គីម នា', 'address' => 'St, 1007', 'phone' => '098886766', 'contact_id_display' => '#N/A'],
        ['id' => 1778, 'name' => 'ចែ សាត', 'address' => 'St, 1015', 'phone' => '0888338778', 'contact_id_display' => 'CO0092'],
        ['id' => 1925, 'name' => 'សុផានី', 'address' => 'St, 1003', 'phone' => '089260097', 'contact_id_display' => 'CO0182'],
        ['id' => 2204, 'name' => 'កាកា', 'address' => 'ផ្សារក្រមួន', 'phone' => '015220888', 'contact_id_display' => 'CO0064'],
        ['id' => 1695, 'name' => 'អ៊ី ធារី', 'address' => 'St, 1011', 'phone' => '098955225', 'contact_id_display' => 'CO0049'],
        ['id' => 3816, 'name' => 'ហុង លីដា', 'address' => 'ផ្លូវ ១៩២៨', 'phone' => '012300367', 'contact_id_display' => 'CO0776'],
        ['id' => 1744, 'name' => 'វុទ្ធី រ៉ាវី', 'address' => 'បឹងបាយ៉ាប', 'phone' => '016920348', 'contact_id_display' => 'CO0078'],
        ['id' => 3679, 'name' => 'អ៊ី វី', 'address' => 'St, 1007', 'phone' => '0965648477', 'contact_id_display' => 'CO0689'],
        ['id' => 1936, 'name' => 'ចែ លីន', 'address' => 'St. 1928', 'phone' => '0963030540', 'contact_id_display' => 'CO0186'],
        ['id' => 1725, 'name' => 'លីលី 999', 'address' => 'St, 1011', 'phone' => '068691111', 'contact_id_display' => 'CO0068'],
        ['id' => 1693, 'name' => 'មោលី', 'address' => 'St, 1011', 'phone' => '012233848', 'contact_id_display' => 'CO0048'],
        ['id' => 5712, 'name' => 'ចាន់ វី(ចាន់រី)', 'address' => 'St, 1011', 'phone' => '012624684', 'contact_id_display' => 'CO1889'],
        ['id' => 5346, 'name' => 'សុខ ឃាង', 'address' => 'St, 598', 'phone' => '012916716', 'contact_id_display' => 'CO1705'],
        ['id' => 2847, 'name' => 'ទុំ វលក្ខ', 'address' => 'st. 598', 'phone' => '069702001', 'contact_id_display' => 'CO0181'],
        ['id' => null, 'name' => 'ម៉ាក់ សៀវ នី', 'address' => 'St. 1946', 'phone' => '0966890963', 'contact_id_display' => 'CO088'],
        ['id' => 8399, 'name' => 'ជា ស្រេង', 'address' => 'St. 1946', 'phone' => '078998858', 'contact_id_display' => 'CO2126'],
        ['id' => 1771, 'name' => 'ឌី ណា', 'address' => 'St. ផ្សារដើមអំពិល', 'phone' => '0978930556', 'contact_id_display' => 'CO0085'],
        ['id' => 2574, 'name' => 'ម៉ាក់ ថារ៉ាត់', 'address' => 'St, Betong', 'phone' => '070442081', 'contact_id_display' => 'CO0121'],
        ['id' => 2566, 'name' => 'សុខុន', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '012691927', 'contact_id_display' => 'CO0116'],
        ['id' => 8125, 'name' => 'លីហួរ ម៉ាឡា', 'address' => 'St. 1019', 'phone' => '087960323', 'contact_id_display' => 'CO2101'],
        ['id' => 1640, 'name' => 'វិបុល ស្រីអន', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '093666229', 'contact_id_display' => 'CO0010'],
        ['id' => 5768, 'name' => 'ស្រី លក្ខណ៍', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '069599923', 'contact_id_display' => 'CO1912'],
        ['id' => 2223, 'name' => 'ម៉ាក់លីហ្សា(ម៉េងចាន់នី)', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '081991071', 'contact_id_display' => 'CO0066'],
        ['id' => null, 'name' => 'ម៉ាក់ លីហ្សា ២', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0968432663', 'contact_id_display' => '#N/A'],
        ['id' => 8345, 'name' => 'ហុក ឆូ', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0889287974', 'contact_id_display' => 'CO2124'],
        ['id' => 5314, 'name' => 'ហេង រតនា', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '012481486', 'contact_id_display' => 'CO1683'],
        ['id' => 1644, 'name' => 'ចែ ភណ្ឌ័', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '015245045', 'contact_id_display' => 'CO0014'],
        ['id' => 1855, 'name' => 'ថារី ឌីឡែន', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0967772012', 'contact_id_display' => 'CO035'],
        ['id' => 2037, 'name' => 'ចែ ណាវី', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '010991387', 'contact_id_display' => 'CO00047'],
        ['id' => 9212, 'name' => 'ម៉េងហេង', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '016517989', 'contact_id_display' => 'CO2236'],
        ['id' => 2275, 'name' => 'ម៉េង ហៀង', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '012997493', 'contact_id_display' => 'CO073'],
        ['id' => 1688, 'name' => 'លក្ខិណា', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '010567167', 'contact_id_display' => 'CO0045'],
        ['id' => 1985, 'name' => 'សាន កុសល', 'address' => 'St. 1019', 'phone' => '098396266', 'contact_id_display' => 'CO0206'],
        ['id' => 1786, 'name' => 'សូស សុឃីម', 'address' => 'ផ្លូវ ឌីព៉ុក', 'phone' => '098579356', 'contact_id_display' => 'CO0097'],
        ['id' => null, 'name' => 'ឡេង ថារី', 'address' => 'St, Betong', 'phone' => '070782178', 'contact_id_display' => 'CO101'],
        ['id' => 4019, 'name' => 'ផល ចំរើន (ពូ ម៉ាប់)', 'address' => 'St, Betong', 'phone' => '017578412', 'contact_id_display' => 'CO0928'],
        ['id' => 3288, 'name' => 'គៀត គីម ឡា', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0968787422', 'contact_id_display' => 'CO0441'],
        ['id' => 2558, 'name' => 'អឿ ហេង', 'address' => 'St. 1019', 'phone' => '012631112', 'contact_id_display' => 'CO0112'],
        ['id' => 3250, 'name' => 'ជីង ជីង', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '092232211', 'contact_id_display' => 'CO0415'],
        ['id' => 2218, 'name' => 'ម៉ាក់ ណាសា', 'address' => 'ផ្លូវ ឌីព៉ុក', 'phone' => '0963467022', 'contact_id_display' => 'CO0065'],
        ['id' => null, 'name' => 'ឡោ ថានះ', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '012775980', 'contact_id_display' => 'CO115'],
        ['id' => 5307, 'name' => 'ម៉ាក់ លីយ៉ា', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '016310033', 'contact_id_display' => 'CO1676'],
        ['id' => 4120, 'name' => 'គុំ វណ្ណះ', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '011909021', 'contact_id_display' => 'CO1013'],
        ['id' => 2452, 'name' => 'សុភ័ក្រ្ក ស្រីមុំ', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '081270680', 'contact_id_display' => 'CO0100'],
        ['id' => null, 'name' => 'សម្បតិ្ត សុខឃី', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '012808342', 'contact_id_display' => 'CO031'],
        ['id' => 5740, 'name' => 'ពៅ សារ៉ាយ', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '087309443', 'contact_id_display' => 'CO1905'],
        ['id' => 8997, 'name' => 'ឃ្លាំង ៩០', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '0962956488', 'contact_id_display' => 'CO2220'],
        ['id' => 2468, 'name' => 'ចែ ណារីឃ្មួញ', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '015312134', 'contact_id_display' => 'CO0106'],
        ['id' => 1947, 'name' => 'ធា រី (ឆារី)', 'address' => 'ឃ្លាំងរំសេវថ្មី', 'phone' => '0967070287', 'contact_id_display' => 'CO0194'],
        ['id' => 3447, 'name' => 'លាភ មួយហេង', 'address' => 'ផ្លូវ វត្តឃ្មួញ', 'phone' => '099680970', 'contact_id_display' => 'CO0542'],
        ['id' => 2552, 'name' => 'ឃួន ទេវី', 'address' => 'St. 1019', 'phone' => '012264864', 'contact_id_display' => 'CO0110'],
        ['id' => 5313, 'name' => 'ឡោ មួយគ័ង', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0967670056', 'contact_id_display' => 'CO1682'],
        ['id' => 1831, 'name' => 'ចិត្រា សែនសុខ', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0964695554', 'contact_id_display' => 'CO0124'],
        ['id' => 8143, 'name' => 'ចិត្រា ស្រីវីន', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '0965942856', 'contact_id_display' => 'CO2108'],
        ['id' => 7934, 'name' => 'ខៀវ រ៉េម', 'address' => 'St. 1019', 'phone' => '012260065', 'contact_id_display' => 'CO2076'],
        ['id' => 4327, 'name' => 'ចាន់ ឌី', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '015943430', 'contact_id_display' => 'CO1125'],
        ['id' => 1978, 'name' => 'ចែ ពុទ្ឋី', 'address' => 'St. 1019', 'phone' => '0979963303', 'contact_id_display' => 'CO0202'],
        ['id' => 2058, 'name' => 'ស្រី មុំ ៩២', 'address' => 'St. 92', 'phone' => '069209282', 'contact_id_display' => 'CO233'],
        ['id' => 9210, 'name' => 'ចែ ស្រីពេជ្រ', 'address' => 'St, Betong', 'phone' => '070427775', 'contact_id_display' => 'CO2235'],
        ['id' => 8280, 'name' => 'អ៊ី យ៉េត', 'address' => 'ផ្លូវ សែនសុខ', 'phone' => '093335026', 'contact_id_display' => 'CO2117'],
        ['id' => 9511, 'name' => 'សេង ធារី', 'address' => 'បុរីមហាសែនសុខ', 'phone' => '085891111', 'contact_id_display' => 'CO2274'],
        ['id' => 1890, 'name' => 'សេង ប៉េង ឈ័ង', 'address' => 'St. 105K', 'phone' => '099406006', 'contact_id_display' => 'CO0168'],
        ['id' => 4152, 'name' => 'ចែ នី', 'address' => 'St. 2011', 'phone' => '098288224', 'contact_id_display' => 'CO1021'],
        ['id' => 1888, 'name' => 'សេរីរ័ត្ន សែនសុខ (ក្រាំងធ្នង់)', 'address' => 'St. 105K', 'phone' => '086465665', 'contact_id_display' => 'CO0166'],
        ['id' => 1972, 'name' => 'មួយ សាំង', 'address' => 'St. 105K', 'phone' => '017488588', 'contact_id_display' => 'CO044'],
        ['id' => 3928, 'name' => 'ផ្ទះ ៧១', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '070702798', 'contact_id_display' => 'CO0860'],
        ['id' => null, 'name' => 'យូ រតនា', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '0979018555', 'contact_id_display' => 'CO097'],
        ['id' => 2277, 'name' => 'ថន ម៉ារ៉ាទី', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '070841195', 'contact_id_display' => 'CO74'],
        ['id' => 1647, 'name' => 'លាភ ស៊ីណា', 'address' => 'St. 2011', 'phone' => '086665909', 'contact_id_display' => 'CO0017'],
        ['id' => null, 'name' => 'សុខ ម៉ៅ', 'address' => 'St. 2011', 'phone' => '077388659', 'contact_id_display' => '#N/A'],
        ['id' => 1973, 'name' => 'សោភណ្ឌ័ ថាវិន', 'address' => 'St. 2011', 'phone' => '017481807', 'contact_id_display' => 'CO045'],
        ['id' => null, 'name' => 'ពេជ្រ ចិន្តា', 'address' => 'St. 2011', 'phone' => '078616462', 'contact_id_display' => 'CO027'],
        ['id' => 2183, 'name' => 'ប៉ា ប៊ុន ហេង', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '087445000', 'contact_id_display' => 'CO0061'],
        ['id' => 3215, 'name' => 'ម៉ាក់ ជីង ជីង', 'address' => 'St. 2011', 'phone' => '081778799', 'contact_id_display' => 'CO0387'],
        ['id' => 9611, 'name' => 'សេ្រង វិចិត្រ', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '015728728', 'contact_id_display' => 'CO2294'],
        ['id' => 2400, 'name' => 'ណៃ ហ៊ីម', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '093245352', 'contact_id_display' => 'CO0085'],
        ['id' => 31983, 'name' => 'ផ្ទះទឹកកក', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '0967943030', 'contact_id_display' => 'CO3298'],
        ['id' => 1891, 'name' => 'ម៉ាក់ នីសា', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '015627278', 'contact_id_display' => 'CO0169'],
        ['id' => 7937, 'name' => 'ម៉ាក់វួចនា', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '0884265898', 'contact_id_display' => 'CO2077'],
        ['id' => 8453, 'name' => 'ចែណែត ក្រាំងធ្នុង', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '010425485', 'contact_id_display' => 'CO2130'],
        ['id' => 2187, 'name' => 'ឡៃ ហ៊ី', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '0962619805', 'contact_id_display' => 'CO0062'],
        ['id' => 32355, 'name' => 'ឆន សុវណ្ណនី', 'address' => 'St. បុរី ឈូកវ៉ា២', 'phone' => '0964144947', 'contact_id_display' => 'CO3369'],
        ['id' => 5703, 'name' => 'AM Mart', 'address' => 'St. 92', 'phone' => '087771020', 'contact_id_display' => 'CO1883'],
        ['id' => null, 'name' => 'ចែ វី', 'address' => 'St. 92', 'phone' => '010707056', 'contact_id_display' => 'CO222'],
        ['id' => 8600, 'name' => 'ពុទ្ឋិ ស័ក្ខ', 'address' => 'St. Betong', 'phone' => '015333625', 'contact_id_display' => 'CO2143'],
        ['id' => 1862, 'name' => 'មង្គលបុរី', 'address' => 'St. 58', 'phone' => '012916741', 'contact_id_display' => 'CO0150'],
        ['id' => null, 'name' => 'លី ជូ', 'address' => 'St. 2011', 'phone' => '015699442', 'contact_id_display' => 'CO025'],
        ['id' => 1675, 'name' => 'ពៅ ពៅ', 'address' => 'St. 58', 'phone' => '017461089', 'contact_id_display' => 'CO0035'],
        ['id' => 1852, 'name' => 'គីម ហុង (BSMM)', 'address' => 'St. 1928', 'phone' => '092744466', 'contact_id_display' => 'CO032'],
        ['id' => 1641, 'name' => 'គីម ហ្គេច', 'address' => 'St. 1928', 'phone' => '0968555567', 'contact_id_display' => 'CO0011'],
        ['id' => 1849, 'name' => 'ស្រីលក្ខ័ រីករាយ', 'address' => 'St. 1928', 'phone' => '068359359', 'contact_id_display' => 'CO0029'],
        ['id' => 1896, 'name' => 'ចាន់ ណា', 'address' => 'St. 2011', 'phone' => '087636516', 'contact_id_display' => 'CO0173'],
        ['id' => null, 'name' => 'ស៊ុន ថា', 'address' => 'St. 1089', 'phone' => '070702798', 'contact_id_display' => 'CO0860'],
        ['id' => 1900, 'name' => 'ម៉េង លី', 'address' => 'St. 1089', 'phone' => '089799190', 'contact_id_display' => 'CO0177'],
        ['id' => 1898, 'name' => 'លីន ស្រីមុំ', 'address' => 'St. 1089', 'phone' => '098848162', 'contact_id_display' => 'CO0175'],
        ['id' => null, 'name' => 'សុជាតា បឹងបៃតង', 'address' => 'St. 1928', 'phone' => '092659888', 'contact_id_display' => 'CO1902'],
        ['id' => 1816, 'name' => 'ហាក់ សុខា', 'address' => 'St. 2011', 'phone' => '092779984', 'contact_id_display' => 'CO0112'],
        ['id' => null, 'name' => 'ចិក ឈៀង', 'address' => 'St. 2011', 'phone' => '077373900', 'contact_id_display' => 'CO029'],
        ['id' => 3501, 'name' => 'ហង្ស ចំរើន (លីហ្សា)', 'address' => 'St. 2011', 'phone' => '0968482009', 'contact_id_display' => 'CO0577'],
        ['id' => 1833, 'name' => 'ម៉ាក់ អូនស៊ីង(គោកឃ្លាំង)', 'address' => 'St. 1089', 'phone' => '092843944', 'contact_id_display' => 'CO0126'],
        ['id' => 1850, 'name' => 'ស្រ៊ុន សុខា', 'address' => 'St. 1019', 'phone' => '012922909', 'contact_id_display' => 'CO030'],
        ['id' => 1894, 'name' => 'អេងណារ៉េត (គោកឃ្លាំង)', 'address' => 'St. 1019', 'phone' => '077427711', 'contact_id_display' => 'CO0042'],
        ['id' => 5610, 'name' => 'ឡូ ឡា', 'address' => 'St. 72', 'phone' => '012956699', 'contact_id_display' => 'CO1859'],
        ['id' => 6119, 'name' => 'ស្រី មុំ 2011 គោកឃ្លាំង', 'address' => 'St. Betong', 'phone' => '092656646', 'contact_id_display' => 'CO1982'],
        ['id' => 3581, 'name' => 'ម៉ាក់ រតនា(គោកឃ្លាំង)', 'address' => 'St. Betong', 'phone' => '012544000', 'contact_id_display' => 'CO0642'],
        ['id' => 1809, 'name' => 'សុខ លីណា', 'address' => 'St. Betong', 'phone' => '010964982', 'contact_id_display' => 'CO00105'],
        ['id' => 2438, 'name' => 'សុ ជាតា', 'address' => 'St. 2011', 'phone' => '016242900', 'contact_id_display' => 'CO099'],
        ['id' => 3397, 'name' => 'សេង សុខ ហេង(គោកឃ្លាំង)', 'address' => 'St. 2011', 'phone' => '092747873', 'contact_id_display' => 'CO0521'],
        ['id' => 6194, 'name' => 'ហេង ឡាយ', 'address' => 'St. Betong', 'phone' => '016528450', 'contact_id_display' => 'CO2004'],
        ['id' => 4386, 'name' => 'ជូ ស្រី ឡា', 'address' => 'St. Betong', 'phone' => '070584854', 'contact_id_display' => 'CO1160'],
        ['id' => null, 'name' => 'តែ តិច ឡុង', 'address' => 'S. 1928', 'phone' => '011838375', 'contact_id_display' => 'CO055'],
        ['id' => 6148, 'name' => 'ឡេង មុំ(គោកឃ្លាំង)', 'address' => 'St. Betong', 'phone' => '098856066', 'contact_id_display' => 'CO1989'],
        ['id' => 4081, 'name' => 'ហ៊ា ទ្រី', 'address' => 'St. 106', 'phone' => '086333827', 'contact_id_display' => 'CO0975'],
        ['id' => 8066, 'name' => 'ចែ ម៉ូនិត', 'address' => 'St. 72', 'phone' => '016977799', 'contact_id_display' => 'CO2092'],
        ['id' => 5214, 'name' => 'សេង ហ៊ី', 'address' => 'st. 28', 'phone' => '087884896', 'contact_id_display' => 'CO1611'],
        ['id' => 5706, 'name' => 'ចែ ចាន់រ៉ា(គោកឃ្លាំង)', 'address' => 'St. Betong', 'phone' => '077778378', 'contact_id_display' => 'CO1884'],
        ['id' => 1902, 'name' => 'ម៉ាក់អាហុង(គោកឃ្លាំង)', 'address' => 'St. 2011', 'phone' => '011931515', 'contact_id_display' => 'CO179'],
        ['id' => 8275, 'name' => 'លិក លិក', 'address' => 'St. 2011', 'phone' => '0312244240', 'contact_id_display' => 'CO2116'],
        ['id' => 8067, 'name' => 'JLH ( មិច ធារ៉ា )', 'address' => 'ST. 58P', 'phone' => '016826188', 'contact_id_display' => 'CO2093'],
    ];

    if ($request->ajax()) {
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $customer_id = $request->get('customer_id');

        // Get DataTables search parameter
        $search = '';
        if ($request->has('search') && is_array($request->input('search'))) {
            $search = $request->input('search')['value'] ?? '';
        }

        // Get List of DB IDs from the config
        $db_contact_ids = array_filter(array_column($report_config, 'id'));

        // Build the base query
        $query = DB::table('transactions as t')
            ->join('transaction_sell_lines as tsl', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'tsl.product_id', '=', 'p.id')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $business_id)
            ->whereIn('c.id', $db_contact_ids)
            ->where('t.status', 'final')
            ->where('t.type', 'sell')
            ->whereNull('t.deleted_at');

        // Apply date filter
        if (!empty($start_date) && !empty($end_date)) {
            try {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', $start_date)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', $end_date)->endOfDay();
                $query->whereBetween('t.transaction_date', [$start, $end]);
            } catch (\Exception $e) {
                // Invalid date format, continue without date filter
            }
        }

        // Apply customer filter if provided
        if (!empty($customer_id)) {
            $query->where('c.id', $customer_id);
        }

        // Select Sales Data Grouped by Contact ID
        $sales_data = $query->select(
            'c.id as contact_id',

            // GBS Sale
            DB::raw("COALESCE(SUM(CASE WHEN p.sku IN ('9032', '9030') THEN tsl.quantity ELSE 0 END), 0) as gbs_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.sku = '9031' THEN tsl.quantity ELSE 0 END), 0) as gbs_claim"),

            // BSP Sale
            DB::raw("COALESCE(SUM(CASE WHEN p.sku IN ('8638', '8308') THEN tsl.quantity ELSE 0 END), 0) as bsp_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.sku = '8309' THEN tsl.quantity ELSE 0 END), 0) as bsp_claim"),

            // Idol Sale
            DB::raw("COALESCE(SUM(CASE WHEN p.sku IN ('8647', '8310') THEN tsl.quantity ELSE 0 END), 0) as idol_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.sku = '8311' THEN tsl.quantity ELSE 0 END), 0) as idol_claim"),

            // FYO 110ml Sale
            DB::raw("COALESCE(SUM(CASE WHEN p.sku IN ('8860', '8721') THEN tsl.quantity ELSE 0 END), 0) as fyo110_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.sku = '10083' THEN tsl.quantity ELSE 0 END), 0) as fyo110_claim"),

            // FYO 180ml Sale - Replace XXXX, YYYY, ZZZZ with actual SKUs
            DB::raw("COALESCE(SUM(CASE WHEN p.sku IN ('XXXX', 'YYYY') THEN tsl.quantity ELSE 0 END), 0) as fyo180_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.sku = 'ZZZZ' THEN tsl.quantity ELSE 0 END), 0) as fyo180_claim"),

            // Fmix Sale: Fmix sale(8744) + Fmix scheme(9770) | Claim: Fmix claim(10068)
            DB::raw("COALESCE(SUM(CASE WHEN p.id IN (8744, 9770) THEN tsl.quantity ELSE 0 END), 0) as fmix_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.id = 10068 THEN tsl.quantity ELSE 0 END), 0) as fmix_claim"),

            // FA + FG Sale: FA Sale(8724)+FG Sale(8863)+FG Scheme(10066)+FA Scheme(10064) | Claim: FA Claim(10065)+FG Claim(10067)
            DB::raw("COALESCE(SUM(CASE WHEN p.id IN (8724, 8863, 10066, 10064) THEN tsl.quantity ELSE 0 END), 0) as fafg_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.id IN (10065, 10067) THEN tsl.quantity ELSE 0 END), 0) as fafg_claim"),

            // FS+FW+Lychee: F-Lychee Sale(10206)+F-Lychee Scheme(10207)+FS Sale(8723)+FS Scheme(8334)+FW Sale(8722)+FW Scheme(8455) | Claim: FS Claim(10069)+FW Claim(10070)+F-Lychee Claim(10208)
            DB::raw("COALESCE(SUM(CASE WHEN p.id IN (10206, 10207, 8723, 8334, 8722, 8455) THEN tsl.quantity ELSE 0 END), 0) as fsfwlychee_buy"),
            DB::raw("COALESCE(SUM(CASE WHEN p.id IN (10069, 10070, 10208) THEN tsl.quantity ELSE 0 END), 0) as fsfwlychee_claim")
        )
        ->groupBy('c.id')
        ->get()
        ->keyBy('contact_id');

        // Merge Config with Sales Data
        $final_data = [];
        $footer_totals = [
            'footer_gbs_buy' => 0, 'footer_gbs_claim' => 0,
            'footer_bsp_buy' => 0, 'footer_bsp_claim' => 0,
            'footer_idol_buy' => 0, 'footer_idol_claim' => 0,
            'footer_fyo110_buy' => 0, 'footer_fyo110_claim' => 0,
            'footer_fyo180_buy' => 0, 'footer_fyo180_claim' => 0,
            'footer_fmix_buy' => 0, 'footer_fmix_claim' => 0,
            'footer_fafg_buy' => 0, 'footer_fafg_claim' => 0,
            'footer_fsfwlychee_buy' => 0, 'footer_fsfwlychee_claim' => 0,
            'footer_total' => 0
        ];

        foreach ($report_config as $config) {
            // Skip entries without ID if customer filter is active
            if (!empty($customer_id) && empty($config['id'])) {
                continue;
            }

            // Skip entries that don't match the selected customer
            if (!empty($customer_id) && $config['id'] != $customer_id) {
                continue;
            }

            $sales = null;
            if ($config['id'] && isset($sales_data[$config['id']])) {
                $sales = $sales_data[$config['id']];
            }

            $row = [
                'contact_id_pk'      => $config['id'] ?? uniqid(),
                'name_wholesale'     => $config['name'],
                'address'            => $config['address'],
                'phone'              => $config['phone'],
                'contact_id'         => $config['contact_id_display'],
                'gbs_buy'            => $sales ? (int)$sales->gbs_buy : 0,
                'gbs_claim'          => $sales ? (int)$sales->gbs_claim : 0,
                'bsp_buy'            => $sales ? (int)$sales->bsp_buy : 0,
                'bsp_claim'          => $sales ? (int)$sales->bsp_claim : 0,
                'idol_buy'           => $sales ? (int)$sales->idol_buy : 0,
                'idol_claim'         => $sales ? (int)$sales->idol_claim : 0,
                'fyo110_buy'         => $sales ? (int)$sales->fyo110_buy : 0,
                'fyo110_claim'       => $sales ? (int)$sales->fyo110_claim : 0,
                'fyo180_buy'         => $sales ? (int)$sales->fyo180_buy : 0,
                'fyo180_claim'       => $sales ? (int)$sales->fyo180_claim : 0,
                'fmix_buy'           => $sales ? (int)$sales->fmix_buy : 0,
                'fmix_claim'         => $sales ? (int)$sales->fmix_claim : 0,
                'fafg_buy'           => $sales ? (int)$sales->fafg_buy : 0,
                'fafg_claim'         => $sales ? (int)$sales->fafg_claim : 0,
                'fsfwlychee_buy'     => $sales ? (int)$sales->fsfwlychee_buy : 0,
                'fsfwlychee_claim'   => $sales ? (int)$sales->fsfwlychee_claim : 0,
            ];

            // Calculate total_qty
            $row['total_qty'] = $row['gbs_buy'] + $row['gbs_claim'] +
                                $row['bsp_buy'] + $row['bsp_claim'] +
                                $row['idol_buy'] + $row['idol_claim'] +
                                $row['fyo110_buy'] + $row['fyo110_claim'] +
                                $row['fyo180_buy'] + $row['fyo180_claim'] +
                                $row['fmix_buy'] + $row['fmix_claim'] +
                                $row['fafg_buy'] + $row['fafg_claim'] +
                                $row['fsfwlychee_buy'] + $row['fsfwlychee_claim'];

            // Apply Search Filter
            if (!empty($search)) {
                $search_lower = mb_strtolower($search, 'UTF-8');
                $found = false;

                if (mb_strpos(mb_strtolower($row['name_wholesale'], 'UTF-8'), $search_lower) !== false) $found = true;
                if (!$found && mb_strpos(mb_strtolower($row['phone'], 'UTF-8'), $search_lower) !== false) $found = true;
                if (!$found && mb_strpos(mb_strtolower($row['address'], 'UTF-8'), $search_lower) !== false) $found = true;
                if (!$found && mb_strpos(mb_strtolower($row['contact_id'], 'UTF-8'), $search_lower) !== false) $found = true;

                if (!$found) continue;
            }

            // Accumulate footer totals
            $footer_totals['footer_gbs_buy']          += $row['gbs_buy'];
            $footer_totals['footer_gbs_claim']         += $row['gbs_claim'];
            $footer_totals['footer_bsp_buy']           += $row['bsp_buy'];
            $footer_totals['footer_bsp_claim']         += $row['bsp_claim'];
            $footer_totals['footer_idol_buy']          += $row['idol_buy'];
            $footer_totals['footer_idol_claim']        += $row['idol_claim'];
            $footer_totals['footer_fyo110_buy']        += $row['fyo110_buy'];
            $footer_totals['footer_fyo110_claim']      += $row['fyo110_claim'];
            $footer_totals['footer_fyo180_buy']        += $row['fyo180_buy'];
            $footer_totals['footer_fyo180_claim']      += $row['fyo180_claim'];
            $footer_totals['footer_fmix_buy']          += $row['fmix_buy'];
            $footer_totals['footer_fmix_claim']        += $row['fmix_claim'];
            $footer_totals['footer_fafg_buy']          += $row['fafg_buy'];
            $footer_totals['footer_fafg_claim']        += $row['fafg_claim'];
            $footer_totals['footer_fsfwlychee_buy']    += $row['fsfwlychee_buy'];
            $footer_totals['footer_fsfwlychee_claim']  += $row['fsfwlychee_claim'];
            $footer_totals['footer_total']             += $row['total_qty'];

            $final_data[] = $row;
        }

        $recordsTotal    = count($report_config);
        $recordsFiltered = count($final_data);

        return Datatables::of(collect($final_data))
            ->editColumn('gbs_buy',          function($row) { return $this->transactionUtil->num_f($row['gbs_buy']); })
            ->editColumn('gbs_claim',         function($row) { return $this->transactionUtil->num_f($row['gbs_claim']); })
            ->editColumn('bsp_buy',           function($row) { return $this->transactionUtil->num_f($row['bsp_buy']); })
            ->editColumn('bsp_claim',         function($row) { return $this->transactionUtil->num_f($row['bsp_claim']); })
            ->editColumn('idol_buy',          function($row) { return $this->transactionUtil->num_f($row['idol_buy']); })
            ->editColumn('idol_claim',        function($row) { return $this->transactionUtil->num_f($row['idol_claim']); })
            ->editColumn('fyo110_buy',        function($row) { return $this->transactionUtil->num_f($row['fyo110_buy']); })
            ->editColumn('fyo110_claim',      function($row) { return $this->transactionUtil->num_f($row['fyo110_claim']); })
            ->editColumn('fyo180_buy',        function($row) { return $this->transactionUtil->num_f($row['fyo180_buy']); })
            ->editColumn('fyo180_claim',      function($row) { return $this->transactionUtil->num_f($row['fyo180_claim']); })
            ->editColumn('fmix_buy',          function($row) { return $this->transactionUtil->num_f($row['fmix_buy']); })
            ->editColumn('fmix_claim',        function($row) { return $this->transactionUtil->num_f($row['fmix_claim']); })
            ->editColumn('fafg_buy',          function($row) { return $this->transactionUtil->num_f($row['fafg_buy']); })
            ->editColumn('fafg_claim',        function($row) { return $this->transactionUtil->num_f($row['fafg_claim']); })
            ->editColumn('fsfwlychee_buy',    function($row) { return $this->transactionUtil->num_f($row['fsfwlychee_buy']); })
            ->editColumn('fsfwlychee_claim',  function($row) { return $this->transactionUtil->num_f($row['fsfwlychee_claim']); })
            ->editColumn('total_qty', function($row) {
                return '<strong>' . $this->transactionUtil->num_f($row['total_qty']) . '</strong>';
            })
            ->rawColumns(['total_qty'])
            ->with(array_merge(
                array_map(function($val) { return $this->transactionUtil->num_f($val); }, $footer_totals),
                [
                    'recordsTotal'    => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered,
                ]
            ))
            ->skipPaging()
            ->make(true);
    }

    $customers = Contact::where('business_id', $business_id)
                        ->whereIn('id', array_filter(array_column($report_config, 'id')))
                        ->pluck('name', 'id');

    return view('report.claim_report', compact('customers'));
}

    // UPDATED: Helper method to use amount column for all payment methods
    private function getPaymentTotalsByPaidOn($business_id, $location_id, $date, $payment_methods)
    {
        $paymentTotals = [];
        
        foreach ($payment_methods as $method) {
            // UPDATED: Use amount column for ALL payment methods including cash_ring_percentage
            $total = DB::table('transaction_payments as tp')
                ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereNull('t.deleted_at')
                ->where('t.location_id', $location_id)
                ->where('tp.method', $method)
                ->whereDate('tp.paid_on', $date)
                ->whereNotNull('tp.paid_on')
                ->selectRaw('(
                    SUM(CASE WHEN tp.is_return = 0 THEN tp.amount ELSE 0 END) - 
                    SUM(CASE WHEN tp.is_return = 1 THEN tp.amount ELSE 0 END)
                ) as net_amount')
                ->first();
            
            $paymentTotals[$method] = $total ? $total->net_amount : 0;
        }
        
        return $paymentTotals;
    }

    /**
     * Calculate due amount based on payment dates (optional - if you still need this)
     */
    private function calculateDueAmountByPaidOn($business_id, $location_id, $date)
    {
        // Get all transactions for the date
        $transactionTotals = DB::table('transactions as t')
            ->join('transaction_payments as tp', 't.id', '=', 'tp.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereRaw('DATE(tp.paid_on) = ?', [$date])
            ->when($location_id, function ($q) use ($location_id) {
                return $q->where('t.location_id', $location_id);
            })
            ->select(
                't.id',
                't.final_total',
                // UPDATED: Use amount column for all payment methods including cash_ring_percentage
                DB::raw('SUM(CASE 
                    WHEN tp.is_return = 0 THEN tp.amount
                    WHEN tp.is_return = 1 THEN -tp.amount
                    ELSE 0 
                END) as total_paid')
            )
            ->groupBy('t.id', 't.final_total')
            ->get();

        $totalDue = 0;
        foreach ($transactionTotals as $transaction) {
            $due = $transaction->final_total - $transaction->total_paid;
            $totalDue += max(0, $due); // Only add positive due amounts
        }

        return $totalDue;
    }

    /**
     * Shows sell payment report
     *
     * @return \Illuminate\Http\Response
     */
    public function sellPaymentReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
        
        // Check if request comes from daily payment report with specific parameters
        $paid_on_date = $request->get('paid_on');
        $location_filter = $request->get('sell_list_filter_location_id');
        
        if ($request->ajax()) {
            $customer_id = $request->get('supplier_id', null);
            $contact_filter1 = ! empty($customer_id) ? "AND t.contact_id=$customer_id" : '';
            $contact_filter2 = ! empty($customer_id) ? "AND transactions.contact_id=$customer_id" : '';

            $location_id = $request->get('location_id', null);
            $parent_payment_query_part = empty($location_id) ? 'AND transaction_payments.parent_id IS NULL' : '';

            $query = TransactionPayment::leftjoin('transactions as t', function ($join) use ($business_id) {
                $join->on('transaction_payments.transaction_id', '=', 't.id')
                    ->where('t.business_id', $business_id)
                    ->whereIn('t.type', ['sell', 'opening_balance']);
            })
                ->leftjoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftjoin('customer_groups AS CG', 'c.customer_group_id', '=', 'CG.id')
                ->where('transaction_payments.business_id', $business_id)
                ->where(function ($q) use ($business_id, $contact_filter1, $contact_filter2, $parent_payment_query_part) {
                    $q->whereRaw("(transaction_payments.transaction_id IS NOT NULL AND t.type IN ('sell', 'opening_balance') $parent_payment_query_part $contact_filter1)")
                        ->orWhereRaw("EXISTS(SELECT * FROM transaction_payments as tp JOIN transactions ON tp.transaction_id = transactions.id WHERE transactions.type IN ('sell', 'opening_balance') AND transactions.business_id = $business_id AND tp.parent_id=transaction_payments.id $contact_filter2)");
                })
                ->select(
                    DB::raw("IF(transaction_payments.transaction_id IS NULL, 
                                (SELECT c.name FROM transactions as ts
                                JOIN contacts as c ON ts.contact_id=c.id 
                                WHERE ts.id=(
                                        SELECT tps.transaction_id FROM transaction_payments as tps
                                        WHERE tps.parent_id=transaction_payments.id LIMIT 1
                                    )
                                ),
                                (SELECT CONCAT(COALESCE(CONCAT(c.supplier_business_name, '<br>'), ''), c.name) FROM transactions as ts JOIN
                                    contacts as c ON ts.contact_id=c.id
                                    WHERE ts.id=t.id 
                                )
                            ) as customer"),
                    'transaction_payments.amount',
                    'transaction_payments.cash_ring_percentage', // Add cash_ring_percentage field
                    'transaction_payments.is_return',
                    'method',
                    'paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.document',
                    'transaction_payments.transaction_no',
                    't.invoice_no',
                    't.id as transaction_id',
                    't.transaction_date as invoice_create_date', // Add invoice create date
                    't.final_total', // NEW: Add final_total from transactions table
                    'cheque_number',
                    'card_transaction_number',
                    'bank_account_number',
                    'transaction_payments.id as DT_RowId',
                    'CG.name as customer_group'
                )
                ->groupBy('transaction_payments.id');

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            
            // Handle filtering by paid_on date (from daily payment report)
            if (!empty($paid_on_date)) {
                // Convert paid_on date to start and end of day range
                $date_start = \Carbon\Carbon::parse($paid_on_date)->startOfDay();
                $date_end = \Carbon\Carbon::parse($paid_on_date)->endOfDay();
                $query->whereBetween('paid_on', [$date_start, $date_end]);
            } elseif (! empty($start_date) && ! empty($end_date)) {
                // Regular date range filtering
                $query->whereBetween(DB::raw('date(paid_on)'), [$start_date, $end_date]);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($request->get('customer_group_id'))) {
                $query->where('CG.id', $request->get('customer_group_id'));
            }

            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }
            if (! empty($request->has('commission_agent'))) {
                $query->where('t.commission_agent', $request->get('commission_agent'));
            }

            if (! empty($request->get('payment_types'))) {
                $query->where('transaction_payments.method', $request->get('payment_types'));
            }

            return Datatables::of($query)
                ->editColumn('invoice_no', function ($row) {
                    if (! empty($row->transaction_id)) {
                        return '<a data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->invoice_no.'</a>';
                    } else {
                        return '';
                    }
                })
                ->editColumn('invoice_create_date', function ($row) {
                    // Format the invoice create date as MM/DD/YYYY HH:MM
                    if (!empty($row->invoice_create_date)) {
                        return \Carbon\Carbon::parse($row->invoice_create_date)->format('m/d/Y H:i');
                    } else {
                        // If no direct transaction_date, get it from the parent transaction
                        if (empty($row->transaction_id)) {
                            // For child payments, we need to get the transaction date from the parent
                            $parent_transaction = DB::table('transaction_payments as tp')
                                ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
                                ->where('tp.parent_id', $row->DT_RowId)
                                ->select('t.transaction_date')
                                ->first();
                            
                            if ($parent_transaction) {
                                return \Carbon\Carbon::parse($parent_transaction->transaction_date)->format('m/d/Y H:i');
                            }
                        }
                        return '';
                    }
                })
                ->editColumn('paid_on', '{{@format_datetime($paid_on)}}')
                ->editColumn('method', function ($row) use ($payment_types) {
                    $method = ! empty($payment_types[$row->method]) ? $payment_types[$row->method] : '';
                    
                    // Special handling for cash_ring_percentage method
                    if ($row->method == 'cash_ring_percentage') {
                        $method = 'Cash Ring(%)';
                    } elseif ($row->method == 'cheque') {
                        $method .= '<br>('.__('lang_v1.cheque_no').': '.$row->cheque_number.')';
                    } elseif ($row->method == 'card') {
                        $method .= '<br>('.__('lang_v1.card_transaction_no').': '.$row->card_transaction_number.')';
                    } elseif ($row->method == 'bank_transfer') {
                        $method .= '<br>('.__('lang_v1.bank_account_no').': '.$row->bank_account_number.')';
                    } elseif ($row->method == 'custom_pay_1') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_2') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_3') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    }
                    
                    if ($row->is_return == 1) {
                        $method .= '<br><small>('.__('lang_v1.change_return').')</small>';
                    }

                    return $method;
                })
                // NEW: Add total_amount column
                ->addColumn('total_amount', function ($row) {
                    // Get final_total from transaction, handle null case
                    $final_total = $row->final_total ?? 0;
                    
                    // If this is a child payment (no direct transaction_id), get final_total from parent transaction
                    if (empty($row->transaction_id)) {
                        $parent_transaction = DB::table('transaction_payments as tp')
                            ->join('transactions as t', 'tp.transaction_id', '=', 't.id')
                            ->where('tp.parent_id', $row->DT_RowId)
                            ->select('t.final_total')
                            ->first();
                        
                        $final_total = $parent_transaction ? $parent_transaction->final_total : 0;
                    }

                    return '<span class="total-amount" data-orig-value="'.$final_total.'" 
                    >'.$this->transactionUtil->num_f($final_total, true).'</span>';
                })
                ->editColumn('amount', function ($row) {
                    // For cash_ring_percentage method, display the cash_ring_percentage value instead of amount
                    if ($row->method == 'cash_ring_percentage') {
                        $display_amount = $row->is_return == 1 ? -1 * $row->cash_ring_percentage : $row->cash_ring_percentage;
                        $display_amount = $display_amount ?? 0; // Handle null values
                    } else {
                        $display_amount = $row->is_return == 1 ? -1 * $row->amount : $row->amount;
                    }

                    return '<span class="paid-amount" data-orig-value="'.$display_amount.'" 
                    >'.$this->transactionUtil->num_f($display_amount, true).'</span>';
                })
                ->addColumn('amount_percentage', function ($row) {
                    if ($row->method == 'cash_ring_percentage') {
                        // For cash_ring_percentage method: Amount Percentage = amount - cash_ring_percentage
                        $base_amount = $row->is_return == 1 ? -1 * $row->amount : $row->amount;
                        $cash_ring_value = $row->cash_ring_percentage ?? 0;
                        if ($row->is_return == 1) {
                            $cash_ring_value = -1 * $cash_ring_value;
                        }
                        $amount_percentage = $base_amount - $cash_ring_value;
                    } else {
                        // For other methods (cash, bank_transfer, etc.), Amount Percentage = 0
                        $amount_percentage = 0;
                    }

                    return '<span class="amount-percentage" data-orig-value="'.$amount_percentage.'" 
                    >'.$this->transactionUtil->num_f($amount_percentage, true).'</span>';
                })
                ->addColumn('action', '<button type="button" class="btn btn-primary btn-xs view_payment" data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'viewPayment\'], [$DT_RowId]) }}">@lang("messages.view")
                    </button> @if(!empty($document))<a href="{{asset("/uploads/documents/" . $document)}}" class="btn btn-success btn-xs" download=""><i class="fa fa-download"></i> @lang("purchase.download_document")</a>@endif')
                ->rawColumns(['invoice_no', 'total_amount', 'amount', 'amount_percentage', 'method', 'action', 'customer'])
                ->make(true);
        }
        
        $business_locations = BusinessLocation::forDropdown($business_id);
        $customers = Contact::customersDropdown($business_id, false);
        $customer_groups = CustomerGroup::forDropdown($business_id, false, true);

        // Pre-select filters if coming from daily payment report
        $selected_location = $location_filter;
        $selected_date_range = '';
        
        if (!empty($paid_on_date)) {
            // Set date range to the specific date (same start and end date)
            $date_formatted = \Carbon\Carbon::parse($paid_on_date)->format('Y-m-d');
            $selected_date_range = $date_formatted . ' ~ ' . $date_formatted;
        }

        return view('report.sell_payment_report')
            ->with(compact('business_locations', 'customers', 'payment_types', 'customer_groups', 'selected_location', 'selected_date_range', 'paid_on_date'));
    }

    /**
     * Shows trending products
     *
     * @return \Illuminate\Http\Response
     */
    public function getTrendingProducts(Request $request)
    {
        if (! auth()->user()->can('trending_product_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $filters = request()->only(['category', 'sub_category', 'brand', 'unit', 'limit', 'location_id', 'product_type']);

        $date_range = request()->input('date_range');

        if (! empty($date_range)) {
            $date_range_array = explode('~', $date_range);
            $filters['start_date'] = $this->transactionUtil->uf_date(trim($date_range_array[0]));
            $filters['end_date'] = $this->transactionUtil->uf_date(trim($date_range_array[1]));
        }

        $products = $this->productUtil->getTrendingProducts($business_id, $filters);

        $values = [];
        $labels = [];
        foreach ($products as $product) {
            $values[] = (float) $product->total_unit_sold;
            $labels[] = $product->product.' - '.$product->sku.' ('.$product->unit.')';
        }

        $chart = new CommonChart;
        $chart->labels($labels)
            ->dataset(__('report.total_unit_sold'), 'column', $values);

        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::where('business_id', $business_id)
                            ->pluck('short_name', 'id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.trending_products')
                    ->with(compact('chart', 'categories', 'brands', 'units', 'business_locations'));
    }

    public function getTrendingProductsAjax()
    {
        $business_id = request()->session()->get('user.business_id');
    }

    /**
     * Shows expense report of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getExpenseReport(Request $request)
    {
        if (! auth()->user()->can('expense_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $filters = $request->only(['category', 'location_id']);

        $date_range = $request->input('date_range');

        if (! empty($date_range)) {
            $date_range_array = explode('~', $date_range);
            $filters['start_date'] = $this->transactionUtil->uf_date(trim($date_range_array[0]));
            $filters['end_date'] = $this->transactionUtil->uf_date(trim($date_range_array[1]));
        } else {
            $filters['start_date'] = \Carbon::now()->startOfMonth()->format('Y-m-d');
            $filters['end_date'] = \Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $expenses = $this->transactionUtil->getExpenseReport($business_id, $filters);

        $values = [];
        $labels = [];
        foreach ($expenses as $expense) {
            $values[] = (float) $expense->total_expense;
            $labels[] = ! empty($expense->category) ? $expense->category : __('report.others');
        }

        $chart = new CommonChart;
        $chart->labels($labels)
            ->title(__('report.expense_report'))
            ->dataset(__('report.total_expense'), 'column', $values);

        $categories = ExpenseCategory::where('business_id', $business_id)
                            ->pluck('name', 'id');

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.expense_report')
                    ->with(compact('chart', 'categories', 'business_locations', 'expenses'));
    }

    /**
     * Shows stock adjustment report
     *
     * @return \Illuminate\Http\Response
     */
    public function getStockAdjustmentReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $query = Transaction::where('business_id', $business_id)
                            ->where('type', 'stock_adjustment');

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('location_id', $permitted_locations);
            }

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(transaction_date)'), [$start_date, $end_date]);
            }
            $location_id = $request->get('location_id');
            if (! empty($location_id)) {
                $query->where('location_id', $location_id);
            }

            $stock_adjustment_details = $query->select(
                DB::raw('SUM(final_total) as total_amount'),
                DB::raw('SUM(total_amount_recovered) as total_recovered'),
                DB::raw("SUM(IF(adjustment_type = 'normal', final_total, 0)) as total_normal"),
                DB::raw("SUM(IF(adjustment_type = 'abnormal', final_total, 0)) as total_abnormal")
            )->first();

            return $stock_adjustment_details;
        }
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.stock_adjustment_report')
                    ->with(compact('business_locations'));
    }

    /**
     * Shows register report of a business
     *
     * @return \Illuminate\Http\Response
     */
    public function getRegisterReport(Request $request)
    {
        if (! auth()->user()->can('register_report.view')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $registers = CashRegister::leftjoin(
                'cash_register_transactions as ct',
                'ct.cash_register_id',
                '=',
                'cash_registers.id'
            )->join(
                'users as u',
                'u.id',
                '=',
                'cash_registers.user_id'
                )
                ->leftJoin(
                    'business_locations as bl',
                    'bl.id',
                    '=',
                    'cash_registers.location_id'
                )
                ->where('cash_registers.business_id', $business_id)
                ->select(
                    'cash_registers.*',
                    DB::raw(
                        "CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, ''), '<br>', COALESCE(u.email, '')) as user_name"
                    ),
                    'bl.name as location_name',
                    DB::raw("SUM(IF(pay_method='cash', IF(transaction_type='sell', amount, 0), 0)) as total_cash_payment"),
                    DB::raw("SUM(IF(pay_method='cheque', IF(transaction_type='sell', amount, 0), 0)) as total_cheque_payment"),
                    DB::raw("SUM(IF(pay_method='card', IF(transaction_type='sell', amount, 0), 0)) as total_card_payment"),
                    DB::raw("SUM(IF(pay_method='bank_transfer', IF(transaction_type='sell', amount, 0), 0)) as total_bank_transfer_payment"),
                    DB::raw("SUM(IF(pay_method='other', IF(transaction_type='sell', amount, 0), 0)) as total_other_payment"),
                    DB::raw("SUM(IF(pay_method='advance', IF(transaction_type='sell', amount, 0), 0)) as total_advance_payment"),
                    DB::raw("SUM(IF(pay_method='custom_pay_1', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_1"),
                    DB::raw("SUM(IF(pay_method='custom_pay_2', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_2"),
                    DB::raw("SUM(IF(pay_method='custom_pay_3', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_3"),
                    DB::raw("SUM(IF(pay_method='custom_pay_4', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_4"),
                    DB::raw("SUM(IF(pay_method='custom_pay_5', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_5"),
                    DB::raw("SUM(IF(pay_method='custom_pay_6', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_6"),
                    DB::raw("SUM(IF(pay_method='custom_pay_7', IF(transaction_type='sell', amount, 0), 0)) as total_custom_pay_7")
                )->groupBy('cash_registers.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $registers->whereIn('cash_registers.location_id', $permitted_locations);
            }

            if (! empty($request->input('user_id'))) {
                $registers->where('cash_registers.user_id', $request->input('user_id'));
            }
            if (! empty($request->input('status'))) {
                $registers->where('cash_registers.status', $request->input('status'));
            }
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            if (! empty($start_date) && ! empty($end_date)) {
                $registers->whereDate('cash_registers.created_at', '>=', $start_date)
                        ->whereDate('cash_registers.created_at', '<=', $end_date);
            }

            return Datatables::of($registers)
                ->editColumn('total_card_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_card_payment.'" >'.$this->transactionUtil->num_f($row->total_card_payment, true).' ('.$row->total_card_slips.')</span>';
                })
                ->editColumn('total_cheque_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_cheque_payment.'" >'.$this->transactionUtil->num_f($row->total_cheque_payment, true).' ('.$row->total_cheques.')</span>';
                })
                ->editColumn('total_cash_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_cash_payment.'" >'.$this->transactionUtil->num_f($row->total_cash_payment, true).'</span>';
                })
                ->editColumn('total_bank_transfer_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_bank_transfer_payment.'" >'.$this->transactionUtil->num_f($row->total_bank_transfer_payment, true).'</span>';
                })
                ->editColumn('total_other_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_other_payment.'" >'.$this->transactionUtil->num_f($row->total_other_payment, true).'</span>';
                })
                ->editColumn('total_advance_payment', function ($row) {
                    return '<span data-orig-value="'.$row->total_advance_payment.'" >'.$this->transactionUtil->num_f($row->total_advance_payment, true).'</span>';
                })
                ->editColumn('total_custom_pay_1', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_1.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_1, true).'</span>';
                })
                ->editColumn('total_custom_pay_2', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_2.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_2, true).'</span>';
                })
                ->editColumn('total_custom_pay_3', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_3.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_3, true).'</span>';
                })
                ->editColumn('total_custom_pay_4', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_4.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_4, true).'</span>';
                })
                ->editColumn('total_custom_pay_5', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_5.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_5, true).'</span>';
                })
                ->editColumn('total_custom_pay_6', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_6.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_6, true).'</span>';
                })
                ->editColumn('total_custom_pay_7', function ($row) {
                    return '<span data-orig-value="'.$row->total_custom_pay_7.'" >'.$this->transactionUtil->num_f($row->total_custom_pay_7, true).'</span>';
                })
                ->editColumn('closed_at', function ($row) {
                    if ($row->status == 'close') {
                        return $this->productUtil->format_date($row->closed_at, true);
                    } else {
                        return '';
                    }
                })
                ->editColumn('created_at', function ($row) {
                    return $this->productUtil->format_date($row->created_at, true);
                })
                ->addColumn('total', function ($row) {
                    $total = $row->total_card_payment + $row->total_cheque_payment + $row->total_cash_payment + $row->total_bank_transfer_payment + $row->total_other_payment + $row->total_advance_payment + $row->total_custom_pay_1 + $row->total_custom_pay_2 + $row->total_custom_pay_3 + $row->total_custom_pay_4 + $row->total_custom_pay_5 + $row->total_custom_pay_6 + $row->total_custom_pay_7;

                    return '<span data-orig-value="'.$total.'" >'.$this->transactionUtil->num_f($total, true).'</span>';
                })
                ->addColumn('action', '<button type="button" data-href="{{action(\'App\Http\Controllers\CashRegisterController@show\', [$id])}}" class="btn btn-xs btn-info btn-modal" 
                    data-container=".view_register"><i class="fas fa-eye" aria-hidden="true"></i> @lang("messages.view")</button> @if($status != "close" && auth()->user()->can("close_cash_register"))<button type="button" data-href="{{action(\'App\Http\Controllers\CashRegisterController@getCloseRegister\', [$id])}}" class="btn btn-xs btn-danger btn-modal" 
                        data-container=".view_register"><i class="fas fa-window-close"></i> @lang("messages.close")</button> @endif')
                ->filterColumn('user_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, ''), '<br>', COALESCE(u.email, '')) like ?", ["%{$keyword}%"]);
                })
                ->rawColumns(['action', 'user_name', 'total_card_payment', 'total_cheque_payment', 'total_cash_payment', 'total_bank_transfer_payment', 'total_other_payment', 'total_advance_payment', 'total_custom_pay_1', 'total_custom_pay_2', 'total_custom_pay_3', 'total_custom_pay_4', 'total_custom_pay_5', 'total_custom_pay_6', 'total_custom_pay_7', 'total'])
                ->make(true);
        }

        $users = User::forDropdown($business_id, false);
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        return view('report.register_report')
                    ->with(compact('users', 'payment_types'));
    }

    /**
     * Shows sales representative report
     *
     * @return \Illuminate\Http\Response
     */
    public function getSalesRepresentativeReport(Request $request)
    {
        if (! auth()->user()->can('sales_representative.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $users = User::allUsersDropdown($business_id, false);
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        return view('report.sales_representative')
                ->with(compact('users', 'business_locations', 'pos_settings'));
    }

    /**
     * Shows sales representative total expense
     *
     * @return json
     */
    public function getSalesRepresentativeTotalExpense(Request $request)
    {
        if (! auth()->user()->can('sales_representative.view')) {
            abort(403, 'Unauthorized action.');
        }

        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');

            $filters = $request->only(['expense_for', 'location_id', 'start_date', 'end_date']);

            $total_expense = $this->transactionUtil->getExpenseReport($business_id, $filters, 'total');

            return $total_expense;
        }
    }

    /**
     * Shows sales representative total sales
     *
     * @return json
     */
    public function getSalesRepresentativeTotalSell(Request $request)
    {
        if (! auth()->user()->can('sales_representative.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            $location_id = $request->get('location_id');
            $created_by = $request->get('created_by');

            $sell_details = $this->transactionUtil->getSellTotals($business_id, $start_date, $end_date, $location_id, $created_by);

            //Get Sell Return details
            $transaction_types = [
                'sell_return',
            ];
            $sell_return_details = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start_date,
                $end_date,
                $location_id,
                $created_by
            );

            $total_sell_return = ! empty($sell_return_details['total_sell_return_exc_tax']) ? $sell_return_details['total_sell_return_exc_tax'] : 0;
            $total_sell = $sell_details['total_sell_exc_tax'] - $total_sell_return;

            return [
                'total_sell_exc_tax' => $sell_details['total_sell_exc_tax'],
                'total_sell_return_exc_tax' => $total_sell_return,
                'total_sell' => $total_sell,
            ];
        }
    }

    /**
     * Shows sales representative total commission
     *
     * @return json
     */
    public function getSalesRepresentativeTotalCommission(Request $request)
    {
        if (! auth()->user()->can('sales_representative.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            $location_id = $request->get('location_id');
            $commission_agent = $request->get('commission_agent');

            $business_details = $this->businessUtil->getDetails($business_id);
            $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

            $commsn_calculation_type = empty($pos_settings['cmmsn_calculation_type']) || $pos_settings['cmmsn_calculation_type'] == 'invoice_value' ? 'invoice_value' : $pos_settings['cmmsn_calculation_type'];

            $commission_percentage = User::find($commission_agent)->cmmsn_percent;

            if ($commsn_calculation_type == 'payment_received') {
                $payment_details = $this->transactionUtil->getTotalPaymentWithCommission($business_id, $start_date, $end_date, $location_id, $commission_agent);

                //Get Commision
                $total_commission = $commission_percentage * $payment_details['total_payment_with_commission'] / 100;

                return ['total_payment_with_commission' => $payment_details['total_payment_with_commission'] ?? 0,
                    'total_commission' => $total_commission,
                    'commission_percentage' => $commission_percentage,
                ];
            }

            $sell_details = $this->transactionUtil->getTotalSellCommission($business_id, $start_date, $end_date, $location_id, $commission_agent);

            //Get Commision
            $total_commission = $commission_percentage * $sell_details['total_sales_with_commission'] / 100;

            return ['total_sales_with_commission' => $sell_details['total_sales_with_commission'],
                'total_commission' => $total_commission,
                'commission_percentage' => $commission_percentage,
            ];
        }
    }

    /**
     * Shows product stock expiry report
     *
     * @return \Illuminate\Http\Response
     */
    public function getStockExpiryReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //TODO:: Need to display reference number and edit expiry date button

        //Return the details in ajax call
        if ($request->ajax()) {
            $query = PurchaseLine::leftjoin(
                'transactions as t',
                'purchase_lines.transaction_id',
                '=',
                't.id'
            )
                            ->leftjoin(
                                'products as p',
                                'purchase_lines.product_id',
                                '=',
                                'p.id'
                            )
                            ->leftjoin(
                                'variations as v',
                                'purchase_lines.variation_id',
                                '=',
                                'v.id'
                            )
                            ->leftjoin(
                                'product_variations as pv',
                                'v.product_variation_id',
                                '=',
                                'pv.id'
                            )
                            ->leftjoin('business_locations as l', 't.location_id', '=', 'l.id')
                            ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                            ->where('t.business_id', $business_id)
                            //->whereNotNull('p.expiry_period')
                            //->whereNotNull('p.expiry_period_type')
                            //->whereNotNull('exp_date')
                            ->where('p.enable_stock', 1);
            // ->whereRaw('purchase_lines.quantity > purchase_lines.quantity_sold + quantity_adjusted + quantity_returned');

            $permitted_locations = auth()->user()->permitted_locations();

            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($request->input('location_id'))) {
                $location_id = $request->input('location_id');
                $query->where('t.location_id', $location_id)
                        //If filter by location then hide products not available in that location
                        ->join('product_locations as pl', 'pl.product_id', '=', 'p.id')
                        ->where(function ($q) use ($location_id) {
                            $q->where('pl.location_id', $location_id);
                        });
            }

            if (! empty($request->input('category_id'))) {
                $query->where('p.category_id', $request->input('category_id'));
            }
            if (! empty($request->input('sub_category_id'))) {
                $query->where('p.sub_category_id', $request->input('sub_category_id'));
            }
            if (! empty($request->input('brand_id'))) {
                $query->where('p.brand_id', $request->input('brand_id'));
            }
            if (! empty($request->input('unit_id'))) {
                $query->where('p.unit_id', $request->input('unit_id'));
            }
            if (! empty($request->input('exp_date_filter'))) {
                $query->whereDate('exp_date', '<=', $request->input('exp_date_filter'));
            }

            $only_mfg_products = request()->get('only_mfg_products', 0);
            if (! empty($only_mfg_products)) {
                $query->where('t.type', 'production_purchase');
            }

            $report = $query->select(
                'p.name as product',
                'p.sku',
                'p.type as product_type',
                'v.name as variation',
                'v.sub_sku',
                'pv.name as product_variation',
                'l.name as location',
                'mfg_date',
                'exp_date',
                'u.short_name as unit',
                DB::raw('SUM(COALESCE(quantity, 0) - COALESCE(quantity_sold, 0) - COALESCE(quantity_adjusted, 0) - COALESCE(quantity_returned, 0)) as stock_left'),
                't.ref_no',
                't.id as transaction_id',
                'purchase_lines.id as purchase_line_id',
                'purchase_lines.lot_number'
            )
            ->having('stock_left', '>', 0)
            ->groupBy('purchase_lines.variation_id')
            ->groupBy('purchase_lines.exp_date')
            ->groupBy('purchase_lines.lot_number');

            return Datatables::of($report)
                ->editColumn('product', function ($row) {
                    if ($row->product_type == 'variable') {
                        return $row->product.' - '.
                        $row->product_variation.' - '.$row->variation.' ('.$row->sub_sku.')';
                    } else {
                        return $row->product.' ('.$row->sku.')';
                    }
                })
                ->editColumn('mfg_date', function ($row) {
                    if (! empty($row->mfg_date)) {
                        return $this->productUtil->format_date($row->mfg_date);
                    } else {
                        return '--';
                    }
                })
                // ->editColumn('exp_date', function ($row) {
                //     if (!empty($row->exp_date)) {
                //         $carbon_exp = \Carbon::createFromFormat('Y-m-d', $row->exp_date);
                //         $carbon_now = \Carbon::now();
                //         if ($carbon_now->diffInDays($carbon_exp, false) >= 0) {
                //             return $this->productUtil->format_date($row->exp_date) . '<br><small>( <span class="time-to-now">' . $row->exp_date . '</span> )</small>';
                //         } else {
                //             return $this->productUtil->format_date($row->exp_date) . ' &nbsp; <span class="label label-danger no-print">' . __('report.expired') . '</span><span class="print_section">' . __('report.expired') . '</span><br><small>( <span class="time-from-now">' . $row->exp_date . '</span> )</small>';
                //         }
                //     } else {
                //         return '--';
                //     }
                // })
                ->editColumn('ref_no', function ($row) {
                    return '<button type="button" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->transaction_id])
                            .'" class="btn btn-link btn-modal" data-container=".view_modal"  >'.$row->ref_no.'</button>';
                })
                ->editColumn('stock_left', function ($row) {
                    return '<span data-is_quantity="true" class="display_currency stock_left" data-currency_symbol=false data-orig-value="'.$row->stock_left.'" data-unit="'.$row->unit.'" >'.$row->stock_left.'</span> '.$row->unit;
                })
                ->addColumn('edit', function ($row) {
                    $html = '<button type="button" class="btn btn-primary btn-xs stock_expiry_edit_btn" data-transaction_id="'.$row->transaction_id.'" data-purchase_line_id="'.$row->purchase_line_id.'"> <i class="fa fa-edit"></i> '.__('messages.edit').
                    '</button>';

                    if (! empty($row->exp_date)) {
                        $carbon_exp = \Carbon::createFromFormat('Y-m-d', $row->exp_date);
                        $carbon_now = \Carbon::now();
                        if ($carbon_now->diffInDays($carbon_exp, false) < 0) {
                            $html .= ' <button type="button" class="btn btn-warning btn-xs remove_from_stock_btn" data-href="'.action([\App\Http\Controllers\StockAdjustmentController::class, 'removeExpiredStock'], [$row->purchase_line_id]).'"> <i class="fa fa-trash"></i> '.__('lang_v1.remove_from_stock').
                            '</button>';
                        }
                    }

                    return $html;
                })
                ->rawColumns(['exp_date', 'ref_no', 'edit', 'stock_left'])
                ->make(true);
        }

        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::where('business_id', $business_id)
                            ->pluck('short_name', 'id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $view_stock_filter = [
            \Carbon::now()->subDay()->format('Y-m-d') => __('report.expired'),
            \Carbon::now()->addWeek()->format('Y-m-d') => __('report.expiring_in_1_week'),
            \Carbon::now()->addDays(15)->format('Y-m-d') => __('report.expiring_in_15_days'),
            \Carbon::now()->addMonth()->format('Y-m-d') => __('report.expiring_in_1_month'),
            \Carbon::now()->addMonths(3)->format('Y-m-d') => __('report.expiring_in_3_months'),
            \Carbon::now()->addMonths(6)->format('Y-m-d') => __('report.expiring_in_6_months'),
            \Carbon::now()->addYear()->format('Y-m-d') => __('report.expiring_in_1_year'),
        ];

        return view('report.stock_expiry_report')
                ->with(compact('categories', 'brands', 'units', 'business_locations', 'view_stock_filter'));
    }

    /**
     * Shows product stock expiry report
     *
     * @return \Illuminate\Http\Response
     */
    public function getStockExpiryReportEditModal(Request $request, $purchase_line_id)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $purchase_line = PurchaseLine::join(
                'transactions as t',
                'purchase_lines.transaction_id',
                '=',
                't.id'
            )
                                ->join(
                                    'products as p',
                                    'purchase_lines.product_id',
                                    '=',
                                    'p.id'
                                )
                                ->where('purchase_lines.id', $purchase_line_id)
                                ->where('t.business_id', $business_id)
                                ->select(['purchase_lines.*', 'p.name', 't.ref_no'])
                                ->first();

            if (! empty($purchase_line)) {
                if (! empty($purchase_line->exp_date)) {
                    $purchase_line->exp_date = date('m/d/Y', strtotime($purchase_line->exp_date));
                }
            }

            return view('report.partials.stock_expiry_edit_modal')
                ->with(compact('purchase_line'));
        }
    }

    /**
     * Update product stock expiry report
     *
     * @return \Illuminate\Http\Response
     */
    public function updateStockExpiryReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            //Return the details in ajax call
            if ($request->ajax()) {
                DB::beginTransaction();

                $input = $request->only(['purchase_line_id', 'exp_date']);

                $purchase_line = PurchaseLine::join(
                    'transactions as t',
                    'purchase_lines.transaction_id',
                    '=',
                    't.id'
                )
                                    ->join(
                                        'products as p',
                                        'purchase_lines.product_id',
                                        '=',
                                        'p.id'
                                    )
                                    ->where('purchase_lines.id', $input['purchase_line_id'])
                                    ->where('t.business_id', $business_id)
                                    ->select(['purchase_lines.*', 'p.name', 't.ref_no'])
                                    ->first();

                if (! empty($purchase_line) && ! empty($input['exp_date'])) {
                    $purchase_line->exp_date = $this->productUtil->uf_date($input['exp_date']);
                    $purchase_line->save();
                }

                DB::commit();

                $output = ['success' => 1,
                    'msg' => __('lang_v1.updated_succesfully'),
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Shows product stock expiry report
     *
     * @return \Illuminate\Http\Response
     */
    public function getCustomerGroup(Request $request)
    {
        if (! auth()->user()->can('contacts_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $query = Transaction::leftjoin('customer_groups AS CG', 'transactions.customer_group_id', '=', 'CG.id')
                        ->where('transactions.business_id', $business_id)
                        ->where('transactions.type', 'sell')
                        ->where('transactions.status', 'final')
                        ->groupBy('transactions.customer_group_id')
                        ->select(DB::raw('SUM(final_total) as total_sell'), 'CG.name');

            $group_id = $request->get('customer_group_id', null);
            if (! empty($group_id)) {
                $query->where('transactions.customer_group_id', $group_id);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('transactions.location_id', $location_id);
            }

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(transaction_date)'), [$start_date, $end_date]);
            }

            return Datatables::of($query)
                ->editColumn('total_sell', function ($row) {
                    return '<span class="display_currency" data-currency_symbol = true>'.$row->total_sell.'</span>';
                })
                ->rawColumns(['total_sell'])
                ->make(true);
        }

        $customer_group = CustomerGroup::forDropdown($business_id, false, true);
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.customer_group')
            ->with(compact('customer_group', 'business_locations'));
    }

    /**
     * Shows product purchase report
     *
     * @return \Illuminate\Http\Response
     */
    public function getproductPurchaseReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        if ($request->ajax()) {
            $variation_id = $request->get('variation_id', null);
            $query = PurchaseLine::join(
                'transactions as t',
                'purchase_lines.transaction_id',
                '=',
                't.id'
                    )
                    ->join(
                        'variations as v',
                        'purchase_lines.variation_id',
                        '=',
                        'v.id'
                    )
                    ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                    ->join('contacts as c', 't.contact_id', '=', 'c.id')
                    ->join('products as p', 'pv.product_id', '=', 'p.id')
                    ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                    ->where('t.business_id', $business_id)
                    ->where('t.type', 'purchase')
                    ->select(
                        'p.name as product_name',
                        'p.type as product_type',
                        'pv.name as product_variation',
                        'v.name as variation_name',
                        'v.sub_sku',
                        'c.name as supplier',
                        'c.supplier_business_name',
                        't.id as transaction_id',
                        't.ref_no',
                        't.transaction_date as transaction_date',
                        'purchase_lines.purchase_price_inc_tax as unit_purchase_price',
                        DB::raw('(purchase_lines.quantity - purchase_lines.quantity_returned) as purchase_qty'),
                        'purchase_lines.quantity_adjusted',
                        'u.short_name as unit',
                        DB::raw('((purchase_lines.quantity - purchase_lines.quantity_returned - purchase_lines.quantity_adjusted) * purchase_lines.purchase_price_inc_tax) as subtotal')
                    )
                    ->groupBy('purchase_lines.id');
            if (! empty($variation_id)) {
                $query->where('purchase_lines.variation_id', $variation_id);
            }
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(transaction_date)'), [$start_date, $end_date]);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $supplier_id = $request->get('supplier_id', null);
            if (! empty($supplier_id)) {
                $query->where('t.contact_id', $supplier_id);
            }

            $brand_id = $request->get('brand_id', null);
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $product_name = $row->product_name;
                    if ($row->product_type == 'variable') {
                        $product_name .= ' - '.$row->product_variation.' - '.$row->variation_name;
                    }

                    return $product_name;
                })
                 ->editColumn('ref_no', function ($row) {
                     return '<a data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->ref_no.'</a>';
                 })
                 ->editColumn('purchase_qty', function ($row) {
                     return '<span data-is_quantity="true" class="display_currency purchase_qty" data-currency_symbol=false data-orig-value="'.(float) $row->purchase_qty.'" data-unit="'.$row->unit.'" >'.(float) $row->purchase_qty.'</span> '.$row->unit;
                 })
                 ->editColumn('quantity_adjusted', function ($row) {
                     return '<span data-is_quantity="true" class="display_currency quantity_adjusted" data-currency_symbol=false data-orig-value="'.(float) $row->quantity_adjusted.'" data-unit="'.$row->unit.'" >'.(float) $row->quantity_adjusted.'</span> '.$row->unit;
                 })
                 ->editColumn('subtotal', function ($row) {
                     return '<span class="row_subtotal"  
                     data-orig-value="'.$row->subtotal.'">'.
                     $this->transactionUtil->num_f($row->subtotal, true).'</span>';
                 })
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('unit_purchase_price', function ($row) {
                    return $this->transactionUtil->num_f($row->unit_purchase_price, true);
                })
                ->editColumn('supplier', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$supplier}}')
                ->rawColumns(['ref_no', 'unit_purchase_price', 'subtotal', 'purchase_qty', 'quantity_adjusted', 'supplier'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id);
        $brands = Brands::forDropdown($business_id);

        return view('report.product_purchase_report')
            ->with(compact('business_locations', 'suppliers', 'brands'));
    }

    /**
     * Shows product purchase report
     *
     * @return \Illuminate\Http\Response
     */
    public function getproductSellReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $custom_labels = json_decode(session('business.custom_labels'), true);

        $product_custom_field1 = !empty($custom_labels['product']['custom_field_1']) ? $custom_labels['product']['custom_field_1'] : '';
        $product_custom_field2 = !empty($custom_labels['product']['custom_field_2']) ? $custom_labels['product']['custom_field_2'] : '';

        if ($request->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            $variation_id = $request->get('variation_id', null);
            $query = TransactionSellLine::join(
                'transactions as t',
                'transaction_sell_lines.transaction_id',
                '=',
                't.id'
            )
                ->join(
                    'variations as v',
                    'transaction_sell_lines.variation_id',
                    '=',
                    'v.id'
                )
                ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->join('products as p', 'pv.product_id', '=', 'p.id')
                ->leftjoin('tax_rates', 'transaction_sell_lines.tax_id', '=', 'tax_rates.id')
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->with('transaction.payment_lines')
                ->select(
                    'p.name as product_name',
                    'p.type as product_type',
                    'p.product_custom_field1 as product_custom_field1',
                    'p.product_custom_field2 as product_custom_field2',
                    'pv.name as product_variation',
                    'v.name as variation_name',
                    'v.sub_sku',
                    'c.name as customer',
                    'c.supplier_business_name',
                    'c.contact_id',
                    't.id as transaction_id',
                    't.invoice_no',
                    't.transaction_date as transaction_date',
                    'transaction_sell_lines.unit_price_before_discount as unit_price',
                    'transaction_sell_lines.unit_price_inc_tax as unit_sale_price',
                    DB::raw('(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as sell_qty'),
                    'transaction_sell_lines.line_discount_type as discount_type',
                    'transaction_sell_lines.line_discount_amount as discount_amount',
                    'transaction_sell_lines.item_tax',
                    'tax_rates.name as tax',
                    'u.short_name as unit',
                    'transaction_sell_lines.parent_sell_line_id',
                    DB::raw('((transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as subtotal')
                )
                ->groupBy('transaction_sell_lines.id');

            if (! empty($variation_id)) {
                $query->where('transaction_sell_lines.variation_id', $variation_id);
            }
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $customer_id = $request->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('t.contact_id', $customer_id);
            }

            $customer_group_id = $request->get('customer_group_id', null);
            if (! empty($customer_group_id)) {
                $query->leftjoin('customer_groups AS CG', 'c.customer_group_id', '=', 'CG.id')
                ->where('CG.id', $customer_group_id);
            }

            $category_id = $request->get('category_id', null);
            if (! empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }

            $brand_id = $request->get('brand_id', null);
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $product_name = $row->product_name;
                    if ($row->product_type == 'variable') {
                        $product_name .= ' - '.$row->product_variation.' - '.$row->variation_name;
                    }

                    return $product_name;
                })
                 ->editColumn('invoice_no', function ($row) {
                     return '<a data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->invoice_no.'</a>';
                 })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('unit_sale_price', function ($row) {
                    return '<span class="unit_sale_price" data-orig-value="'.$row->unit_sale_price.'">'.
                    $this->transactionUtil->num_f($row->unit_sale_price, true).'</span>';
                })
                ->editColumn('sell_qty', function ($row) {
                    //ignore child sell line of combo product
                    $class = is_null($row->parent_sell_line_id) ? 'sell_qty' : '';

                    return '<span class="'.$class.'"  data-orig-value="'.$row->sell_qty.'" 
                    data-unit="'.$row->unit.'" >'.
                    $this->transactionUtil->num_f($row->sell_qty, false, null, true).'</span> '.$row->unit;
                })
                 ->editColumn('subtotal', function ($row) {
                     //ignore child sell line of combo product
                     $class = is_null($row->parent_sell_line_id) ? 'row_subtotal' : '';

                     return '<span class="'.$class.'"  data-orig-value="'.$row->subtotal.'">'.
                    $this->transactionUtil->num_f($row->subtotal, true).'</span>';
                 })
                ->editColumn('unit_price', function ($row) {
                    return '<span class="unit_price" data-orig-value="'.$row->unit_price.'">'.
                    $this->transactionUtil->num_f($row->unit_price, true).'</span>';
                })
                ->editColumn('discount_amount', '
                    @if($discount_type == "percentage")
                        {{@num_format($discount_amount)}} %
                    @elseif($discount_type == "fixed")
                        {{@num_format($discount_amount)}}
                    @endif
                    ')
                ->editColumn('tax', function ($row) {
                    return $this->transactionUtil->num_f($row->item_tax, true)
                     .'<br>'.'<span data-orig-value="'.$row->item_tax.'" 
                     class="tax" data-unit="'.$row->tax.'"><small>('.$row->tax.')</small></span>';
                })
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->transaction->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$customer}}')
                ->rawColumns(['invoice_no', 'unit_sale_price', 'subtotal', 'sell_qty', 'discount_amount', 'unit_price', 'tax', 'customer', 'payment_methods'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $customers = Contact::customersDropdown($business_id);
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $customer_group = CustomerGroup::forDropdown($business_id, false, true);

        return view('report.product_sell_report')
            ->with(compact('business_locations', 'customers', 'categories', 'brands',
                'customer_group', 'product_custom_field1', 'product_custom_field2'));
    }

    /**
     * Shows product purchase report with purchase details
     *
     * @return \Illuminate\Http\Response
     */
    public function getproductSellReportWithPurchase(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        if ($request->ajax()) {
            $variation_id = $request->get('variation_id', null);
            $query = TransactionSellLine::join(
                'transactions as t',
                'transaction_sell_lines.transaction_id',
                '=',
                't.id'
            )
                ->join(
                    'transaction_sell_lines_purchase_lines as tspl',
                    'transaction_sell_lines.id',
                    '=',
                    'tspl.sell_line_id'
                )
                ->join(
                    'purchase_lines as pl',
                    'tspl.purchase_line_id',
                    '=',
                    'pl.id'
                )
                ->join(
                    'transactions as purchase',
                    'pl.transaction_id',
                    '=',
                    'purchase.id'
                )
                ->leftjoin('contacts as supplier', 'purchase.contact_id', '=', 'supplier.id')
                ->join(
                    'variations as v',
                    'transaction_sell_lines.variation_id',
                    '=',
                    'v.id'
                )
                ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->leftjoin('contacts as c', 't.contact_id', '=', 'c.id')
                ->join('products as p', 'pv.product_id', '=', 'p.id')
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->select(
                    'p.name as product_name',
                    'p.type as product_type',
                    'pv.name as product_variation',
                    'v.name as variation_name',
                    'v.sub_sku',
                    'c.name as customer',
                    'c.supplier_business_name',
                    't.id as transaction_id',
                    't.invoice_no',
                    't.transaction_date as transaction_date',
                    'tspl.quantity as purchase_quantity',
                    'u.short_name as unit',
                    'supplier.name as supplier_name',
                    'purchase.ref_no as ref_no',
                    'purchase.type as purchase_type',
                    'pl.lot_number'
                );

            if (! empty($variation_id)) {
                $query->where('transaction_sell_lines.variation_id', $variation_id);
            }
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $customer_id = $request->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('t.contact_id', $customer_id);
            }
            $customer_group_id = $request->get('customer_group_id', null);
            if (! empty($customer_group_id)) {
                $query->leftjoin('customer_groups AS CG', 'c.customer_group_id', '=', 'CG.id')
                ->where('CG.id', $customer_group_id);
            }

            $category_id = $request->get('category_id', null);
            if (! empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }

            $brand_id = $request->get('brand_id', null);
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $product_name = $row->product_name;
                    if ($row->product_type == 'variable') {
                        $product_name .= ' - '.$row->product_variation.' - '.$row->variation_name;
                    }

                    return $product_name;
                })
                 ->editColumn('invoice_no', function ($row) {
                     return '<a data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->invoice_no.'</a>';
                 })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('unit_sale_price', function ($row) {
                    return '<span class="display_currency" data-currency_symbol = true>'.$row->unit_sale_price.'</span>';
                })
                ->editColumn('purchase_quantity', function ($row) {
                    return '<span data-is_quantity="true" class="display_currency purchase_quantity" data-currency_symbol=false data-orig-value="'.(float) $row->purchase_quantity.'" data-unit="'.$row->unit.'" >'.(float) $row->purchase_quantity.'</span> '.$row->unit;
                })
                ->editColumn('ref_no', '
                    @if($purchase_type == "opening_stock")
                        <i><small class="help-block">(@lang("lang_v1.opening_stock"))</small></i>
                    @else
                        {{$ref_no}}
                    @endif
                    ')
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$customer}}')
                ->rawColumns(['invoice_no', 'purchase_quantity', 'ref_no', 'customer'])
                ->make(true);
        }
    }

    /**
     * Shows product lot report
     *
     * @return \Illuminate\Http\Response
     */
    public function getLotReport(Request $request)
    {
        if (! auth()->user()->can('stock_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Return the details in ajax call
        if ($request->ajax()) {
            $query = Product::where('products.business_id', $business_id)
                    ->leftjoin('units', 'products.unit_id', '=', 'units.id')
                    ->join('variations as v', 'products.id', '=', 'v.product_id')
                    ->join('purchase_lines as pl', 'v.id', '=', 'pl.variation_id')
                    ->leftjoin(
                        'transaction_sell_lines_purchase_lines as tspl',
                        'pl.id',
                        '=',
                        'tspl.purchase_line_id'
                    )
                    ->join('transactions as t', 'pl.transaction_id', '=', 't.id');

            $permitted_locations = auth()->user()->permitted_locations();
            $location_filter = 'WHERE ';

            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);

                $locations_imploded = implode(', ', $permitted_locations);
                $location_filter = " LEFT JOIN transactions as t2 on pls.transaction_id=t2.id WHERE t2.location_id IN ($locations_imploded) AND ";
            }

            if (! empty($request->input('location_id'))) {
                $location_id = $request->input('location_id');
                $query->where('t.location_id', $location_id)
                    //If filter by location then hide products not available in that location
                    ->ForLocation($location_id);

                $location_filter = "LEFT JOIN transactions as t2 on pls.transaction_id=t2.id WHERE t2.location_id=$location_id AND ";
            }

            if (! empty($request->input('category_id'))) {
                $query->where('products.category_id', $request->input('category_id'));
            }

            if (! empty($request->input('sub_category_id'))) {
                $query->where('products.sub_category_id', $request->input('sub_category_id'));
            }

            if (! empty($request->input('brand_id'))) {
                $query->where('products.brand_id', $request->input('brand_id'));
            }

            if (! empty($request->input('unit_id'))) {
                $query->where('products.unit_id', $request->input('unit_id'));
            }

            $only_mfg_products = request()->get('only_mfg_products', 0);
            if (! empty($only_mfg_products)) {
                $query->where('t.type', 'production_purchase');
            }

            $products = $query->select(
                'products.name as product',
                'v.name as variation_name',
                'sub_sku',
                'pl.lot_number',
                'pl.exp_date as exp_date',
                DB::raw("( COALESCE((SELECT SUM(quantity - quantity_returned) from purchase_lines as pls $location_filter variation_id = v.id AND lot_number = pl.lot_number), 0) - 
                    SUM(COALESCE((tspl.quantity - tspl.qty_returned), 0))) as stock"),
                // DB::raw("(SELECT SUM(IF(transactions.type='sell', TSL.quantity, -1* TPL.quantity) ) FROM transactions
                //         LEFT JOIN transaction_sell_lines AS TSL ON transactions.id=TSL.transaction_id

                //         LEFT JOIN purchase_lines AS TPL ON transactions.id=TPL.transaction_id

                //         WHERE transactions.status='final' AND transactions.type IN ('sell', 'sell_return') $location_filter
                //         AND (TSL.product_id=products.id OR TPL.product_id=products.id)) as total_sold"),

                DB::raw('COALESCE(SUM(IF(tspl.sell_line_id IS NULL, 0, (tspl.quantity - tspl.qty_returned)) ), 0) as total_sold'),
                DB::raw('COALESCE(SUM(IF(tspl.stock_adjustment_line_id IS NULL, 0, tspl.quantity ) ), 0) as total_adjusted'),
                'products.type',
                'units.short_name as unit'
            )
            ->whereNotNull('pl.lot_number')
            ->groupBy('v.id')
            ->groupBy('pl.lot_number');

            return Datatables::of($products)
                ->editColumn('stock', function ($row) {
                    $stock = $row->stock ? $row->stock : 0;

                    return '<span data-is_quantity="true" class="display_currency total_stock" data-currency_symbol=false data-orig-value="'.(float) $stock.'" data-unit="'.$row->unit.'" >'.(float) $stock.'</span> '.$row->unit;
                })
                ->editColumn('product', function ($row) {
                    if ($row->variation_name != 'DUMMY') {
                        return $row->product.' ('.$row->variation_name.')';
                    } else {
                        return $row->product;
                    }
                })
                ->editColumn('total_sold', function ($row) {
                    if ($row->total_sold) {
                        return '<span data-is_quantity="true" class="display_currency total_sold" data-currency_symbol=false data-orig-value="'.(float) $row->total_sold.'" data-unit="'.$row->unit.'" >'.(float) $row->total_sold.'</span> '.$row->unit;
                    } else {
                        return '0'.' '.$row->unit;
                    }
                })
                ->editColumn('total_adjusted', function ($row) {
                    if ($row->total_adjusted) {
                        return '<span data-is_quantity="true" class="display_currency total_adjusted" data-currency_symbol=false data-orig-value="'.(float) $row->total_adjusted.'" data-unit="'.$row->unit.'" >'.(float) $row->total_adjusted.'</span> '.$row->unit;
                    } else {
                        return '0'.' '.$row->unit;
                    }
                })
                ->editColumn('exp_date', function ($row) {
                    if (! empty($row->exp_date)) {
                        $carbon_exp = \Carbon::createFromFormat('Y-m-d', $row->exp_date);
                        $carbon_now = \Carbon::now();
                        if ($carbon_now->diffInDays($carbon_exp, false) >= 0) {
                            return $this->productUtil->format_date($row->exp_date).'<br><small>( <span class="time-to-now">'.$row->exp_date.'</span> )</small>';
                        } else {
                            return $this->productUtil->format_date($row->exp_date).' &nbsp; <span class="label label-danger no-print">'.__('report.expired').'</span><span class="print_section">'.__('report.expired').'</span><br><small>( <span class="time-from-now">'.$row->exp_date.'</span> )</small>';
                        }
                    } else {
                        return '--';
                    }
                })
                ->removeColumn('unit')
                ->removeColumn('id')
                ->removeColumn('variation_name')
                ->rawColumns(['exp_date', 'stock', 'total_sold', 'total_adjusted'])
                ->make(true);
        }

        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $units = Unit::where('business_id', $business_id)
                            ->pluck('short_name', 'id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.lot_report')
            ->with(compact('categories', 'brands', 'units', 'business_locations'));
    }

    /**
     * Shows purchase payment report
     *
     * @return \Illuminate\Http\Response
     */
    public function purchasePaymentReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        if ($request->ajax()) {
            $supplier_id = $request->get('supplier_id', null);
            $contact_filter1 = ! empty($supplier_id) ? "AND t.contact_id=$supplier_id" : '';
            $contact_filter2 = ! empty($supplier_id) ? "AND transactions.contact_id=$supplier_id" : '';

            $location_id = $request->get('location_id', null);

            $parent_payment_query_part = empty($location_id) ? 'AND transaction_payments.parent_id IS NULL' : '';

            $query = TransactionPayment::leftjoin('transactions as t', function ($join) use ($business_id) {
                $join->on('transaction_payments.transaction_id', '=', 't.id')
                    ->where('t.business_id', $business_id)
                    ->whereIn('t.type', ['purchase', 'opening_balance']);
            })
                ->where('transaction_payments.business_id', $business_id)
                ->where(function ($q) use ($business_id, $contact_filter1, $contact_filter2, $parent_payment_query_part) {
                    $q->whereRaw("(transaction_payments.transaction_id IS NOT NULL AND t.type IN ('purchase', 'opening_balance')  $parent_payment_query_part $contact_filter1)")
                        ->orWhereRaw("EXISTS(SELECT * FROM transaction_payments as tp JOIN transactions ON tp.transaction_id = transactions.id WHERE transactions.type IN ('purchase', 'opening_balance') AND transactions.business_id = $business_id AND tp.parent_id=transaction_payments.id $contact_filter2)");
                })

                ->select(
                    DB::raw("IF(transaction_payments.transaction_id IS NULL, 
                                (SELECT c.name FROM transactions as ts
                                JOIN contacts as c ON ts.contact_id=c.id 
                                WHERE ts.id=(
                                        SELECT tps.transaction_id FROM transaction_payments as tps
                                        WHERE tps.parent_id=transaction_payments.id LIMIT 1
                                    )
                                ),
                                (SELECT CONCAT(COALESCE(c.supplier_business_name, ''), '<br>', c.name) FROM transactions as ts JOIN
                                    contacts as c ON ts.contact_id=c.id
                                    WHERE ts.id=t.id 
                                )
                            ) as supplier"),
                    'transaction_payments.amount',
                    'method',
                    'paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.document',
                    't.ref_no',
                    't.id as transaction_id',
                    'cheque_number',
                    'card_transaction_number',
                    'bank_account_number',
                    'transaction_no',
                    'transaction_payments.id as DT_RowId'
                )
                ->groupBy('transaction_payments.id');

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(paid_on)'), [$start_date, $end_date]);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            return Datatables::of($query)
                 ->editColumn('ref_no', function ($row) {
                     if (! empty($row->ref_no)) {
                         return '<a data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->ref_no.'</a>';
                     } else {
                         return '';
                     }
                 })
                ->editColumn('paid_on', '{{@format_datetime($paid_on)}}')
                ->editColumn('method', function ($row) use ($payment_types) {
                    $method = ! empty($payment_types[$row->method]) ? $payment_types[$row->method] : '';
                    if ($row->method == 'cheque') {
                        $method .= '<br>('.__('lang_v1.cheque_no').': '.$row->cheque_number.')';
                    } elseif ($row->method == 'card') {
                        $method .= '<br>('.__('lang_v1.card_transaction_no').': '.$row->card_transaction_number.')';
                    } elseif ($row->method == 'bank_transfer') {
                        $method .= '<br>('.__('lang_v1.bank_account_no').': '.$row->bank_account_number.')';
                    } elseif ($row->method == 'custom_pay_1') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_2') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    } elseif ($row->method == 'custom_pay_3') {
                        $method .= '<br>('.__('lang_v1.transaction_no').': '.$row->transaction_no.')';
                    }

                    return $method;
                })
                ->editColumn('amount', function ($row) {
                    return '<span class="paid-amount" data-orig-value="'.$row->amount.'">'.
                    $this->transactionUtil->num_f($row->amount, true).'</span>';
                })
                ->addColumn('action', '<button type="button" class="btn btn-primary btn-xs view_payment" data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'viewPayment\'], [$DT_RowId]) }}">@lang("messages.view")
                    </button> @if(!empty($document))<a href="{{asset("/uploads/documents/" . $document)}}" class="btn btn-success btn-xs" download=""><i class="fa fa-download"></i> @lang("purchase.download_document")</a>@endif')
                ->rawColumns(['ref_no', 'amount', 'method', 'action', 'supplier'])
                ->make(true);
        }
        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);

        return view('report.purchase_payment_report')
            ->with(compact('business_locations', 'suppliers'));
    }

    /**
     * Shows tables report
     *
     * @return \Illuminate\Http\Response
     */
    public function getTableReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $query = ResTable::leftjoin('transactions AS T', 'T.res_table_id', '=', 'res_tables.id')
                        ->where('T.business_id', $business_id)
                        ->where('T.type', 'sell')
                        ->where('T.status', 'final')
                        ->groupBy('res_tables.id')
                        ->select(DB::raw('SUM(final_total) as total_sell'), 'res_tables.name as table');

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('T.location_id', $location_id);
            }

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');

            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereBetween(DB::raw('date(transaction_date)'), [$start_date, $end_date]);
            }

            return Datatables::of($query)
                ->editColumn('total_sell', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">'.$row->total_sell.'</span>';
                })
                ->rawColumns(['total_sell'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('report.table_report')
            ->with(compact('business_locations'));
    }

    /**
     * Shows service staff report
     *
     * @return \Illuminate\Http\Response
     */
    public function getServiceStaffReport(Request $request)
    {
        if (! auth()->user()->can('sales_representative.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $waiters = $this->transactionUtil->serviceStaffDropdown($business_id);

        return view('report.service_staff_report')
            ->with(compact('business_locations', 'waiters'));
    }

    /**
     * Shows product sell report grouped by date
     *
     * @return \Illuminate\Http\Response
     */
    public function getproductSellGroupedReport(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->get('location_id', null);

        $vld_str = '';
        if (! empty($location_id)) {
            $vld_str = "AND vld.location_id=$location_id";
        }

        if ($request->ajax()) {
            $variation_id = $request->get('variation_id', null);
            $query = TransactionSellLine::join(
                'transactions as t',
                'transaction_sell_lines.transaction_id',
                '=',
                't.id'
            )
                ->join(
                    'variations as v',
                    'transaction_sell_lines.variation_id',
                    '=',
                    'v.id'
                )
                ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->join('products as p', 'pv.product_id', '=', 'p.id')
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->select(
                    'p.name as product_name',
                    'p.enable_stock',
                    'p.type as product_type',
                    'pv.name as product_variation',
                    'v.name as variation_name',
                    'v.sub_sku',
                    't.id as transaction_id',
                    't.transaction_date as transaction_date',
                    'transaction_sell_lines.parent_sell_line_id',
                    DB::raw('DATE_FORMAT(t.transaction_date, "%Y-%m-%d") as formated_date'),
                    DB::raw("(SELECT SUM(vld.qty_available) FROM variation_location_details as vld WHERE vld.variation_id=v.id $vld_str) as current_stock"),
                    DB::raw('SUM(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as total_qty_sold'),
                    'u.short_name as unit',
                    DB::raw('SUM((transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as subtotal')
                )
                ->groupBy('v.id')
                ->groupBy('formated_date');

            if (! empty($variation_id)) {
                $query->where('transaction_sell_lines.variation_id', $variation_id);
            }
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $customer_id = $request->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('t.contact_id', $customer_id);
            }

            $customer_group_id = $request->get('customer_group_id', null);
            if (! empty($customer_group_id)) {
                $query->leftjoin('contacts AS c', 't.contact_id', '=', 'c.id')
                    ->leftjoin('customer_groups AS CG', 'c.customer_group_id', '=', 'CG.id')
                ->where('CG.id', $customer_group_id);
            }

            $category_id = $request->get('category_id', null);
            if (! empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }

            $brand_id = $request->get('brand_id', null);
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $product_name = $row->product_name;
                    if ($row->product_type == 'variable') {
                        $product_name .= ' - '.$row->product_variation.' - '.$row->variation_name;
                    }

                    return $product_name;
                })
                ->editColumn('transaction_date', '{{@format_date($formated_date)}}')
                ->editColumn('total_qty_sold', function ($row) {
                    return '<span data-is_quantity="true" class="display_currency sell_qty" data-currency_symbol=false data-orig-value="'.(float) $row->total_qty_sold.'" data-unit="'.$row->unit.'" >'.(float) $row->total_qty_sold.'</span> '.$row->unit;
                })
                ->editColumn('current_stock', function ($row) {
                    if ($row->enable_stock) {
                        return '<span data-is_quantity="true" class="display_currency current_stock" data-currency_symbol=false data-orig-value="'.(float) $row->current_stock.'" data-unit="'.$row->unit.'" >'.(float) $row->current_stock.'</span> '.$row->unit;
                    } else {
                        return '';
                    }
                })
                 ->editColumn('subtotal', function ($row) {
                     $class = is_null($row->parent_sell_line_id) ? 'row_subtotal' : '';

                     return '<span class="'.$class.'" data-orig-value="'.$row->subtotal.'">'.
                     $this->transactionUtil->num_f($row->subtotal, true).'</span>';
                 })

                ->rawColumns(['current_stock', 'subtotal', 'total_qty_sold'])
                ->make(true);
        }
    }

    /**
     * Shows product sell report grouped by date
     *
     * @return \Illuminate\Http\Response
     */
    public function productSellReportBy(Request $request)
    {
        if (! auth()->user()->can('purchase_n_sell_report.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->get('location_id', null);
        $group_by = $request->get('group_by', null);

        $vld_str = '';
        if (! empty($location_id)) {
            $vld_str = "AND vld.location_id=$location_id";
        }

        if ($request->ajax()) {
            $query = TransactionSellLine::join(
                'transactions as t',
                'transaction_sell_lines.transaction_id',
                '=',
                't.id'
            )
                ->leftjoin(
                    'products as p',
                    'transaction_sell_lines.product_id',
                    '=',
                    'p.id'
                )
                ->leftjoin('categories as cat', 'p.category_id', '=', 'cat.id')
                ->leftjoin('brands as b', 'p.brand_id', '=', 'b.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->select(
                    'b.name as brand_name',
                    'cat.name as category_name',
                    DB::raw("(SELECT SUM(vld.qty_available) FROM variation_location_details as vld WHERE vld.variation_id=transaction_sell_lines.variation_id $vld_str) as current_stock"),
                    DB::raw('SUM(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as total_qty_sold'),
                    DB::raw('SUM((transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as subtotal'),
                    'transaction_sell_lines.parent_sell_line_id'
                );

            if ($group_by == 'category') {
                $query->groupBy('cat.id');
            } elseif ($group_by == 'brand') {
                $query->groupBy('b.id');
            }

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $customer_id = $request->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('t.contact_id', $customer_id);
            }

            $customer_group_id = $request->get('customer_group_id', null);
            if (! empty($customer_group_id)) {
                $query->leftjoin('contacts AS c', 't.contact_id', '=', 'c.id')
                    ->leftjoin('customer_groups AS CG', 'c.customer_group_id', '=', 'CG.id')
                ->where('CG.id', $customer_group_id);
            }

            $category_id = $request->get('category_id', null);
            if (! empty($category_id)) {
                $query->where('p.category_id', $category_id);
            }

            $brand_id = $request->get('brand_id', null);
            if (! empty($brand_id)) {
                $query->where('p.brand_id', $brand_id);
            }

            return Datatables::of($query)
                ->editColumn('category_name', '{{$category_name ?? __("lang_v1.uncategorized")}}')
                ->editColumn('brand_name', '{{$brand_name ?? __("lang_v1.no_brand")}}')
                ->editColumn('total_qty_sold', function ($row) {
                    return '<span data-is_quantity="true" class="display_currency sell_qty" data-currency_symbol=false data-orig-value="'.(float) $row->total_qty_sold.'" data-unit="" >'.(float) $row->total_qty_sold.'</span> '.$row->unit;
                })
                ->editColumn('current_stock', function ($row) {
                    return '<span data-is_quantity="true" class="display_currency current_stock" data-currency_symbol=false data-orig-value="'.(float) $row->current_stock.'" data-unit="">'.(float) $row->current_stock.'</span> ';
                })
                 ->editColumn('subtotal', function ($row) {
                     $class = is_null($row->parent_sell_line_id) ? 'row_subtotal' : '';

                     return '<span class="'.$class.'" data-orig-value="'.$row->subtotal.'">'
                    .$this->transactionUtil->num_f($row->subtotal, true).'</span>';
                 })

                ->rawColumns(['current_stock', 'subtotal', 'total_qty_sold', 'category_name'])
                ->make(true);
        }
    }

    /**
     * Shows product stock details and allows to adjust mismatch
     *
     * @return \Illuminate\Http\Response
     */
    public function productStockDetails()
    {
        if (! auth()->user()->can('report.stock_details')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $variation_id = request()->get('variation_id', null);
        $location_id = request()->input('location_id');

        $location = null;
        $stock_details = [];

        if (! empty(request()->input('location_id'))) {
            $location = BusinessLocation::where('business_id', $business_id)
                                        ->where('id', $location_id)
                                        ->first();
            $stock_details = $this->productUtil->getVariationStockMisMatch($business_id, $variation_id, $location_id);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('report.product_stock_details')
            ->with(compact('stock_details', 'business_locations', 'location'));
    }

    /**
     * Adjusts stock availability mismatch if found
     *
     * @return \Illuminate\Http\Response
     */
    public function adjustProductStock()
    {
        if (! auth()->user()->can('report.stock_details')) {
            abort(403, 'Unauthorized action.');
        }

        if (! empty(request()->input('variation_id'))
            && ! empty(request()->input('location_id'))
            && request()->has('stock')) {
            $business_id = request()->session()->get('user.business_id');

            $this->productUtil->fixVariationStockMisMatch($business_id, request()->input('variation_id'), request()->input('location_id'), request()->input('stock'));
        }

        return redirect()->back()->with(['status' => [
            'success' => 1,
            'msg' => __('lang_v1.updated_succesfully'),
        ]]);
    }

    /**
     * Retrieves line orders/sales
     *
     * @return obj
     */
    public function serviceStaffLineOrders()
    {
        $business_id = request()->session()->get('user.business_id');

        $query = TransactionSellLine::leftJoin('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
                ->leftJoin('variations as v', 'transaction_sell_lines.variation_id', '=', 'v.id')
                ->leftJoin('products as p', 'v.product_id', '=', 'p.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->leftJoin('users as ss', 'ss.id', '=', 'transaction_sell_lines.res_service_staff_id')
                ->leftjoin(
                    'business_locations AS bl',
                    't.location_id',
                    '=',
                    'bl.id'
                )
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->whereNotNull('transaction_sell_lines.res_service_staff_id');

        if (! empty(request()->service_staff_id)) {
            $query->where('transaction_sell_lines.res_service_staff_id', request()->service_staff_id);
        }

        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }
        }

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $query->whereDate('t.transaction_date', '>=', $start)
                        ->whereDate('t.transaction_date', '<=', $end);
        }

        $query->select(
            'p.name as product_name',
            'p.type as product_type',
            'v.name as variation_name',
            'pv.name as product_variation_name',
            'u.short_name as unit',
            't.id as transaction_id',
            'bl.name as business_location',
            't.transaction_date',
            't.invoice_no',
            'transaction_sell_lines.quantity',
            'transaction_sell_lines.unit_price_before_discount',
            'transaction_sell_lines.line_discount_type',
            'transaction_sell_lines.line_discount_amount',
            'transaction_sell_lines.item_tax',
            'transaction_sell_lines.unit_price_inc_tax',
            DB::raw('CONCAT(COALESCE(ss.first_name, ""), COALESCE(ss.last_name, "")) as service_staff')
        );

        $datatable = Datatables::of($query)
            ->editColumn('product_name', function ($row) {
                $name = $row->product_name;
                if ($row->product_type == 'variable') {
                    $name .= ' - '.$row->product_variation_name.' - '.$row->variation_name;
                }

                return $name;
            })
            ->editColumn(
                'unit_price_inc_tax',
                '<span class="display_currency unit_price_inc_tax" data-currency_symbol="true" data-orig-value="{{$unit_price_inc_tax}}">{{$unit_price_inc_tax}}</span>'
            )
            ->editColumn(
                'item_tax',
                '<span class="display_currency item_tax" data-currency_symbol="true" data-orig-value="{{$item_tax}}">{{$item_tax}}</span>'
            )
            ->editColumn(
                'quantity',
                '<span class="display_currency quantity" data-unit="{{$unit}}" data-currency_symbol="false" data-orig-value="{{$quantity}}">{{$quantity}}</span> {{$unit}}'
            )
            ->editColumn(
                'unit_price_before_discount',
                '<span class="display_currency unit_price_before_discount" data-currency_symbol="true" data-orig-value="{{$unit_price_before_discount}}">{{$unit_price_before_discount}}</span>'
            )
            ->addColumn(
                'total',
                '<span class="display_currency total" data-currency_symbol="true" data-orig-value="{{$unit_price_inc_tax * $quantity}}">{{$unit_price_inc_tax * $quantity}}</span>'
            )
            ->editColumn(
                'line_discount_amount',
                function ($row) {
                    $discount = ! empty($row->line_discount_amount) ? $row->line_discount_amount : 0;

                    if (! empty($discount) && $row->line_discount_type == 'percentage') {
                        $discount = $row->unit_price_before_discount * ($discount / 100);
                    }

                    return '<span class="display_currency total-discount" data-currency_symbol="true" data-orig-value="'.$discount.'">'.$discount.'</span>';
                }
            )
            ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')

            ->rawColumns(['line_discount_amount', 'unit_price_before_discount', 'item_tax', 'unit_price_inc_tax', 'item_tax', 'quantity', 'total'])
                  ->make(true);

        return $datatable;
    }

    /**
     * Lists profit by product, category, brand, location, invoice and date
     *
     * @return string $by = null
     */
    public function getProfit($by = null)
    {
        $business_id = request()->session()->get('user.business_id');

        $query = TransactionSellLine::join('transactions as sale', 'transaction_sell_lines.transaction_id', '=', 'sale.id')
            ->leftjoin('transaction_sell_lines_purchase_lines as TSPL', 'transaction_sell_lines.id', '=', 'TSPL.sell_line_id')
            ->leftjoin(
                'purchase_lines as PL',
                'TSPL.purchase_line_id',
                '=',
                'PL.id'
            )
            ->where('sale.type', 'sell')
            ->where('sale.status', 'final')
            ->join('products as P', 'transaction_sell_lines.product_id', '=', 'P.id')
            ->where('sale.business_id', $business_id)
            ->where('transaction_sell_lines.children_type', '!=', 'combo');
        //If type combo: find childrens, sale price parent - get PP of childrens
        $query->select(DB::raw('SUM(IF (TSPL.id IS NULL AND P.type="combo", ( 
            SELECT Sum((tspl2.quantity - tspl2.qty_returned) * (tsl.unit_price_inc_tax - pl2.purchase_price_inc_tax)) AS total
                FROM transaction_sell_lines AS tsl
                    JOIN transaction_sell_lines_purchase_lines AS tspl2
                ON tsl.id=tspl2.sell_line_id 
                JOIN purchase_lines AS pl2 
                ON tspl2.purchase_line_id = pl2.id 
                WHERE tsl.parent_sell_line_id = transaction_sell_lines.id), IF(P.enable_stock=0,(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax,   
                (TSPL.quantity - TSPL.qty_returned) * (transaction_sell_lines.unit_price_inc_tax - PL.purchase_price_inc_tax)) )) AS gross_profit')
            );

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('sale.location_id', $permitted_locations);
        }

        if (! empty(request()->location_id)) {
            $query->where('sale.location_id', request()->location_id);
        }

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $query->whereDate('sale.transaction_date', '>=', $start)
                        ->whereDate('sale.transaction_date', '<=', $end);
        }

        if ($by == 'product') {
            $query->join('variations as V', 'transaction_sell_lines.variation_id', '=', 'V.id')
                ->leftJoin('product_variations as PV', 'PV.id', '=', 'V.product_variation_id')
                ->addSelect(DB::raw("IF(P.type='variable', CONCAT(P.name, ' - ', PV.name, ' - ', V.name, ' (', V.sub_sku, ')'), CONCAT(P.name, ' (', P.sku, ')')) as product"))
                ->groupBy('V.id');
        }

        if ($by == 'category') {
            $query->join('variations as V', 'transaction_sell_lines.variation_id', '=', 'V.id')
                ->leftJoin('categories as C', 'C.id', '=', 'P.category_id')
                ->addSelect('C.name as category')
                ->groupBy('C.id');
        }

        if ($by == 'brand') {
            $query->join('variations as V', 'transaction_sell_lines.variation_id', '=', 'V.id')
                ->leftJoin('brands as B', 'B.id', '=', 'P.brand_id')
                ->addSelect('B.name as brand')
                ->groupBy('B.id');
        }

        if ($by == 'location') {
            $query->join('business_locations as L', 'sale.location_id', '=', 'L.id')
                ->addSelect('L.name as location')
                ->groupBy('L.id');
        }

        if ($by == 'invoice') {
            $query->addSelect(
                'sale.invoice_no',
                'sale.id as transaction_id',
                'sale.discount_type',
                'sale.discount_amount',
                'sale.total_before_tax'
            )
                ->groupBy('sale.invoice_no');
        }

        if ($by == 'date') {
            $query->addSelect('sale.transaction_date')
                ->groupBy(DB::raw('DATE(sale.transaction_date)'));
        }

        if ($by == 'day') {
            $results = $query->addSelect(DB::raw('DAYNAME(sale.transaction_date) as day'))
                ->groupBy(DB::raw('DAYOFWEEK(sale.transaction_date)'))
                ->get();

            $profits = [];
            foreach ($results as $result) {
                $profits[strtolower($result->day)] = $result->gross_profit;
            }
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            return view('report.partials.profit_by_day')->with(compact('profits', 'days'));
        }

        if ($by == 'customer') {
            $query->join('contacts as CU', 'sale.contact_id', '=', 'CU.id')
            ->addSelect('CU.name as customer', 'CU.supplier_business_name')
                ->groupBy('sale.contact_id');
        }

        $datatable = Datatables::of($query);

        if (in_array($by, ['invoice'])) {
            $datatable->editColumn('gross_profit', function ($row) {
                $discount = $row->discount_amount;
                if ($row->discount_type == 'percentage') {
                    $discount = ($row->discount_amount * $row->total_before_tax) / 100;
                }

                $profit = $row->gross_profit - $discount;
                $html = '<span class="gross-profit" data-orig-value="'.$profit.'" >'.$this->transactionUtil->num_f($profit, true).'</span>';

                return $html;
            });
        } else {
            $datatable->editColumn(
                'gross_profit',
                function ($row) {
                    return '<span class="gross-profit" data-orig-value="'.$row->gross_profit.'">'.$this->transactionUtil->num_f($row->gross_profit, true).'</span>';
                });
        }

        if ($by == 'category') {
            $datatable->editColumn(
                'category',
                '{{$category ?? __("lang_v1.uncategorized")}}'
            );
        }
        if ($by == 'brand') {
            $datatable->editColumn(
                'brand',
                '{{$brand ?? __("report.others")}}'
            );
        }

        if ($by == 'date') {
            $datatable->editColumn('transaction_date', '{{@format_date($transaction_date)}}');
        }

        if ($by == 'product') {
            $datatable->filterColumn(
                 'product',
                 function ($query, $keyword) {
                     $query->whereRaw("IF(P.type='variable', CONCAT(P.name, ' - ', PV.name, ' - ', V.name, ' (', V.sub_sku, ')'), CONCAT(P.name, ' (', P.sku, ')')) LIKE '%{$keyword}%'");
                 });
        }
        $raw_columns = ['gross_profit'];

        if ($by == 'customer') {
            $datatable->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$customer}}');
            $raw_columns[] = 'customer';
        }

        if ($by == 'invoice') {
            $datatable->editColumn('invoice_no', function ($row) {
                return '<a data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->invoice_no.'</a>';
            });
            $raw_columns[] = 'invoice_no';
        }

        return $datatable->rawColumns($raw_columns)
                  ->make(true);
    }

    /**
     * Shows items report from sell purchase mapping table
     *
     * @return \Illuminate\Http\Response
     */
    public function itemsReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = TransactionSellLinesPurchaseLines::leftJoin('transaction_sell_lines 
                    as SL', 'SL.id', '=', 'transaction_sell_lines_purchase_lines.sell_line_id')
                ->leftJoin('stock_adjustment_lines 
                    as SAL', 'SAL.id', '=', 'transaction_sell_lines_purchase_lines.stock_adjustment_line_id')
                ->leftJoin('transactions as sale', 'SL.transaction_id', '=', 'sale.id')
                ->leftJoin('transactions as stock_adjustment', 'SAL.transaction_id', '=', 'stock_adjustment.id')
                ->join('purchase_lines as PL', 'PL.id', '=', 'transaction_sell_lines_purchase_lines.purchase_line_id')
                ->join('transactions as purchase', 'PL.transaction_id', '=', 'purchase.id')
                ->join('business_locations as bl', 'purchase.location_id', '=', 'bl.id')
                ->join(
                    'variations as v',
                    'PL.variation_id',
                    '=',
                    'v.id'
                    )
                ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                ->join('products as p', 'PL.product_id', '=', 'p.id')
                ->join('units as u', 'p.unit_id', '=', 'u.id')
                ->leftJoin('contacts as suppliers', 'purchase.contact_id', '=', 'suppliers.id')
                ->leftJoin('contacts as customers', 'sale.contact_id', '=', 'customers.id')
                ->where('purchase.business_id', $business_id)
                ->select(
                    'v.sub_sku as sku',
                    'p.type as product_type',
                    'p.name as product_name',
                    'v.name as variation_name',
                    'pv.name as product_variation',
                    'u.short_name as unit',
                    'purchase.transaction_date as purchase_date',
                    'purchase.ref_no as purchase_ref_no',
                    'purchase.type as purchase_type',
                    'purchase.id as purchase_id',
                    'suppliers.name as supplier',
                    'suppliers.supplier_business_name',
                    'PL.purchase_price_inc_tax as purchase_price',
                    'sale.transaction_date as sell_date',
                    'stock_adjustment.transaction_date as stock_adjustment_date',
                    'sale.invoice_no as sale_invoice_no',
                    'stock_adjustment.ref_no as stock_adjustment_ref_no',
                    'customers.name as customer',
                    'customers.supplier_business_name as customer_business_name',
                    'transaction_sell_lines_purchase_lines.quantity as quantity',
                    'SL.unit_price_inc_tax as selling_price',
                    'SAL.unit_price as stock_adjustment_price',
                    'transaction_sell_lines_purchase_lines.stock_adjustment_line_id',
                    'transaction_sell_lines_purchase_lines.sell_line_id',
                    'transaction_sell_lines_purchase_lines.purchase_line_id',
                    'transaction_sell_lines_purchase_lines.qty_returned',
                    'bl.name as location',
                    'SL.sell_line_note',
                    'PL.lot_number'
                );

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('purchase.location_id', $permitted_locations);
            }

            if (! empty(request()->purchase_start) && ! empty(request()->purchase_end)) {
                $start = request()->purchase_start;
                $end = request()->purchase_end;
                $query->whereDate('purchase.transaction_date', '>=', $start)
                            ->whereDate('purchase.transaction_date', '<=', $end);
            }
            if (! empty(request()->sale_start) && ! empty(request()->sale_end)) {
                $start = request()->sale_start;
                $end = request()->sale_end;
                $query->where(function ($q) use ($start, $end) {
                    $q->where(function ($qr) use ($start, $end) {
                        $qr->whereDate('sale.transaction_date', '>=', $start)
                           ->whereDate('sale.transaction_date', '<=', $end);
                    })->orWhere(function ($qr) use ($start, $end) {
                        $qr->whereDate('stock_adjustment.transaction_date', '>=', $start)
                           ->whereDate('stock_adjustment.transaction_date', '<=', $end);
                    });
                });
            }

            $supplier_id = request()->get('supplier_id', null);
            if (! empty($supplier_id)) {
                $query->where('suppliers.id', $supplier_id);
            }

            $customer_id = request()->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('customers.id', $customer_id);
            }

            $location_id = request()->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('purchase.location_id', $location_id);
            }

            $only_mfg_products = request()->get('only_mfg_products', 0);
            if (! empty($only_mfg_products)) {
                $query->where('purchase.type', 'production_purchase');
            }

            return Datatables::of($query)
                ->editColumn('product_name', function ($row) {
                    $product_name = $row->product_name;
                    if ($row->product_type == 'variable') {
                        $product_name .= ' - '.$row->product_variation.' - '.$row->variation_name;
                    }

                    return $product_name;
                })
                ->editColumn('purchase_date', '{{@format_datetime($purchase_date)}}')
                ->editColumn('purchase_ref_no', function ($row) {
                    $html = $row->purchase_type == 'purchase' ? '<a data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->purchase_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->purchase_ref_no.'</a>' : $row->purchase_ref_no;
                    if ($row->purchase_type == 'opening_stock') {
                        $html .= '('.__('lang_v1.opening_stock').')';
                    }

                    return $html;
                })
                ->editColumn('purchase_price', function ($row) {
                    return '<span 
                    class="purchase_price" data-orig-value="'.$row->purchase_price.'">'.
                    $this->transactionUtil->num_f($row->purchase_price, true).'</span>';
                })
                ->editColumn('sell_date', '@if(!empty($sell_line_id)) {{@format_datetime($sell_date)}} @else {{@format_datetime($stock_adjustment_date)}} @endif')

                ->editColumn('sale_invoice_no', function ($row) {
                    $invoice_no = ! empty($row->sell_line_id) ? $row->sale_invoice_no : $row->stock_adjustment_ref_no.'<br><small>('.__('stock_adjustment.stock_adjustment').'</small)>';

                    return $invoice_no;
                })
                ->editColumn('quantity', function ($row) {
                    $html = '<span data-is_quantity="true" class="display_currency quantity" data-currency_symbol=false data-orig-value="'.(float) $row->quantity.'" data-unit="'.$row->unit.'" >'.(float) $row->quantity.'</span> '.$row->unit;

                    if (empty($row->sell_line_id)) {
                        $html .= '<br><small>('.__('stock_adjustment.stock_adjustment').'</small)>';
                    }
                    if ($row->qty_returned > 0) {
                        $html .= '<small><i>(<span data-is_quantity="true" class="display_currency" data-currency_symbol=false>'.(float) $row->quantity.'</span> '.$row->unit.' '.__('lang_v1.returned').')</i></small>';
                    }

                    return $html;
                })
                 ->editColumn('selling_price', function ($row) {
                     $selling_price = ! empty($row->sell_line_id) ? $row->selling_price : $row->stock_adjustment_price;

                     return '<span class="row_selling_price" data-orig-value="'.$selling_price.
                      '">'.$this->transactionUtil->num_f($selling_price, true).'</span>';
                 })

                 ->addColumn('subtotal', function ($row) {
                     $selling_price = ! empty($row->sell_line_id) ? $row->selling_price : $row->stock_adjustment_price;
                     $subtotal = $selling_price * $row->quantity;

                     return '<span class="row_subtotal" data-orig-value="'.$subtotal.'">'.
                     $this->transactionUtil->num_f($subtotal, true).'</span>';
                 })
                 ->editColumn('supplier', '@if(!empty($supplier_business_name))
                 {{$supplier_business_name}},<br> @endif {{$supplier}}')
                 ->editColumn('customer', '@if(!empty($customer_business_name))
                 {{$customer_business_name}},<br> @endif {{$customer}}')
                ->filterColumn('sale_invoice_no', function ($query, $keyword) {
                    $query->where('sale.invoice_no', 'like', ["%{$keyword}%"])
                          ->orWhere('stock_adjustment.ref_no', 'like', ["%{$keyword}%"]);
                })

                ->rawColumns(['subtotal', 'selling_price', 'quantity', 'purchase_price', 'sale_invoice_no', 'purchase_ref_no', 'supplier', 'customer'])
                ->make(true);
        }

        $suppliers = Contact::suppliersDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('report.items_report')->with(compact('suppliers', 'customers', 'business_locations'));
    }

    /**
     * Shows purchase report
     *
     * @return \Illuminate\Http\Response
     */
    public function purchaseReport()
    {
        if ((! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create') && ! auth()->user()->can('view_own_purchase')) || empty(config('constants.show_report_606'))) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');
        if (request()->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
            $purchases = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                    ->join(
                        'business_locations AS BS',
                        'transactions.location_id',
                        '=',
                        'BS.id'
                    )
                    ->leftJoin(
                        'transaction_payments AS TP',
                        'transactions.id',
                        '=',
                        'TP.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase')
                    ->with(['payment_lines'])
                    ->select(
                        'transactions.id',
                        'transactions.ref_no',
                        'contacts.name',
                        'contacts.contact_id',
                        'final_total',
                        'total_before_tax',
                        'discount_amount',
                        'discount_type',
                        'tax_amount',
                        DB::raw('DATE_FORMAT(transaction_date, "%Y/%m") as purchase_year_month'),
                        DB::raw('DATE_FORMAT(transaction_date, "%d") as purchase_day')
                    )
                    ->groupBy('transactions.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $purchases->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->supplier_id)) {
                $purchases->where('contacts.id', request()->supplier_id);
            }
            if (! empty(request()->location_id)) {
                $purchases->where('transactions.location_id', request()->location_id);
            }
            if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $purchases->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $purchases->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            if (! empty(request()->status)) {
                $purchases->where('transactions.status', request()->status);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $purchases->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            if (! auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
                $purchases->where('transactions.created_by', request()->session()->get('user.id'));
            }

            return Datatables::of($purchases)
                ->removeColumn('id')
                ->editColumn(
                    'final_total',
                    '<span class="display_currency final_total" data-currency_symbol="true" data-orig-value="{{$final_total}}">{{$final_total}}</span>'
                )
                ->editColumn(
                    'total_before_tax',
                    '<span class="display_currency total_before_tax" data-currency_symbol="true" data-orig-value="{{$total_before_tax}}">{{$total_before_tax}}</span>'
                )
                ->editColumn(
                    'tax_amount',
                    '<span class="display_currency tax_amount" data-currency_symbol="true" data-orig-value="{{$tax_amount}}">{{$tax_amount}}</span>'
                )
                ->editColumn(
                    'discount_amount',
                    function ($row) {
                        $discount = ! empty($row->discount_amount) ? $row->discount_amount : 0;

                        if (! empty($discount) && $row->discount_type == 'percentage') {
                            $discount = $row->total_before_tax * ($discount / 100);
                        }

                        return '<span class="display_currency total-discount" data-currency_symbol="true" data-orig-value="'.$discount.'">'.$discount.'</span>';
                    }
                )
                ->addColumn('payment_year_month', function ($row) {
                    $year_month = '';
                    if (! empty($row->payment_lines->first())) {
                        $year_month = \Carbon::parse($row->payment_lines->first()->paid_on)->format('Y/m');
                    }

                    return $year_month;
                })
                ->addColumn('payment_day', function ($row) {
                    $payment_day = '';
                    if (! empty($row->payment_lines->first())) {
                        $payment_day = \Carbon::parse($row->payment_lines->first()->paid_on)->format('d');
                    }

                    return $payment_day;
                })
                ->addColumn('payment_method', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]];
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('purchase.view')) {
                            return  action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['final_total', 'total_before_tax', 'tax_amount', 'discount_amount', 'payment_method'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);
        $orderStatuses = $this->productUtil->orderStatuses();

        return view('report.purchase_report')
            ->with(compact('business_locations', 'suppliers', 'orderStatuses'));
    }

    /**
     * Shows sale report
     *
     * @return \Illuminate\Http\Response
     */
    public function saleReport()
    {
        if ((! auth()->user()->can('sell.view') && ! auth()->user()->can('sell.create') && ! auth()->user()->can('direct_sell.access') && ! auth()->user()->can('view_own_sell_only')) || empty(config('constants.show_report_607'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        return view('report.sale_report')
            ->with(compact('business_locations', 'customers'));
    }

    /**
     * Calculates stock values
     *
     * @return array
     */
    public function getStockValue()
    {
        $business_id = request()->session()->get('user.business_id');
        $end_date = \Carbon::now()->format('Y-m-d');
        $location_id = request()->input('location_id');
        $filters = request()->only(['category_id', 'sub_category_id', 'brand_id', 'unit_id']);
        //Get Closing stock
        $closing_stock_by_pp = $this->transactionUtil->getOpeningClosingStock(
            $business_id,
            $end_date,
            $location_id,
            false,
            false,
            $filters
        );
        $closing_stock_by_sp = $this->transactionUtil->getOpeningClosingStock(
            $business_id,
            $end_date,
            $location_id,
            false,
            true,
            $filters
        );
        $potential_profit = $closing_stock_by_sp - $closing_stock_by_pp;
        $profit_margin = empty($closing_stock_by_sp) ? 0 : ($potential_profit / $closing_stock_by_sp) * 100;

        return [
            'closing_stock_by_pp' => $closing_stock_by_pp,
            'closing_stock_by_sp' => $closing_stock_by_sp,
            'potential_profit' => $potential_profit,
            'profit_margin' => $profit_margin,
        ];
    }

    public function activityLog()
    {
        $business_id = request()->session()->get('user.business_id');
        $transaction_types = [
            'contact' => __('report.contact'),
            'user' => __('report.user'),
            'sell' => __('sale.sale'),
            'purchase' => __('lang_v1.purchase'),
            'sales_order' => __('lang_v1.sales_order'),
            'purchase_order' => __('lang_v1.purchase_order'),
            'sell_return' => __('lang_v1.sell_return'),
            'purchase_return' => __('lang_v1.purchase_return'),
            'sell_transfer' => __('lang_v1.stock_transfer'),
            'stock_adjustment' => __('stock_adjustment.stock_adjustment'),
            'expense' => __('lang_v1.expense'),
        ];

        if (request()->ajax()) {
            $activities = Activity::with(['subject'])
                                ->leftjoin('users as u', 'u.id', '=', 'activity_log.causer_id')
                                ->where('activity_log.business_id', $business_id)
                                ->select(
                                    'activity_log.*',
                                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as created_by")
                                );

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $activities->whereDate('activity_log.created_at', '>=', $start)
                            ->whereDate('activity_log.created_at', '<=', $end);
            }

            if (! empty(request()->user_id)) {
                $activities->where('causer_id', request()->user_id);
            }

            $subject_type = request()->subject_type;
            if (! empty($subject_type)) {
                if ($subject_type == 'contact') {
                    $activities->where('subject_type', \App\Contact::class);
                } elseif ($subject_type == 'user') {
                    $activities->where('subject_type', \App\User::class);
                } elseif (in_array($subject_type, ['sell', 'purchase',
                    'sales_order', 'purchase_order', 'sell_return', 'purchase_return', 'sell_transfer', 'expense', 'purchase_order', ])) {
                    $activities->where('subject_type', \App\Transaction::class);
                    $activities->whereHasMorph('subject', Transaction::class, function ($q) use ($subject_type) {
                        $q->where('type', $subject_type);
                    });
                }
            }

            $sell_statuses = Transaction::sell_statuses();
            $sales_order_statuses = Transaction::sales_order_statuses(true);
            $purchase_statuses = $this->transactionUtil->orderStatuses();
            $shipping_statuses = $this->transactionUtil->shipping_statuses();

            $statuses = array_merge($sell_statuses, $sales_order_statuses, $purchase_statuses);

            return Datatables::of($activities)
                            ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                            ->addColumn('subject_type', function ($row) use ($transaction_types) {
                                $subject_type = '';
                                if ($row->subject_type == \App\Contact::class) {
                                    $subject_type = __('contact.contact');
                                } elseif ($row->subject_type == \App\User::class) {
                                    $subject_type = __('report.user');
                                } elseif ($row->subject_type == \App\Transaction::class && ! empty($row->subject->type)) {
                                    $subject_type = isset($transaction_types[$row->subject->type]) ? $transaction_types[$row->subject->type] : '';
                                } elseif (($row->subject_type == \App\TransactionPayment::class)) {
                                    $subject_type = __('lang_v1.payment');
                                }

                                return $subject_type;
                            })
                            ->addColumn('note', function ($row) use ($statuses, $shipping_statuses) {
                                $html = '';
                                if (! empty($row->subject->ref_no)) {
                                    $html .= __('purchase.ref_no').': '.$row->subject->ref_no.'<br>';
                                }
                                if (! empty($row->subject->invoice_no)) {
                                    $html .= __('sale.invoice_no').': '.$row->subject->invoice_no.'<br>';
                                }
                                if ($row->subject_type == \App\Transaction::class && ! empty($row->subject) && in_array($row->subject->type, ['sell', 'purchase'])) {
                                    $html .= view('sale_pos.partials.activity_row', ['activity' => $row, 'statuses' => $statuses, 'shipping_statuses' => $shipping_statuses])->render();
                                } else {
                                    $update_note = $row->getExtraProperty('update_note');
                                    if (! empty($update_note) && ! is_array($update_note)) {
                                        $html .= $update_note;
                                    }
                                }

                                if ($row->description == 'contact_deleted') {
                                    $html .= $row->getExtraProperty('supplier_business_name') ?? '';
                                    $html .= '<br>';
                                }

                                if (! empty($row->getExtraProperty('name'))) {
                                    $html .= __('user.name').': '.$row->getExtraProperty('name').'<br>';
                                }

                                if (! empty($row->getExtraProperty('id'))) {
                                    $html .= 'id: '.$row->getExtraProperty('id').'<br>';
                                }
                                if (! empty($row->getExtraProperty('invoice_no'))) {
                                    $html .= __('sale.invoice_no').': '.$row->getExtraProperty('invoice_no');
                                }

                                if (! empty($row->getExtraProperty('ref_no'))) {
                                    $html .= __('purchase.ref_no').': '.$row->getExtraProperty('ref_no');
                                }

                                return $html;
                            })
                            ->filterColumn('created_by', function ($query, $keyword) {
                                $query->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"]);
                            })
                            ->editColumn('description', function ($row) {
                                return __('lang_v1.'.$row->description);
                            })
                            ->rawColumns(['note'])
                            ->make(true);
        }

        $users = User::allUsersDropdown($business_id, false);

        return view('report.activity_log')->with(compact('users', 'transaction_types'));
    }

    public function gstSalesReport(Request $request)
    {
        if (! auth()->user()->can('tax_report.view') || empty(config('constants.enable_gst_report_india'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                        ->where('is_tax_group', 0)
                        ->select(['id', 'name', 'amount'])
                        ->get()
                        ->toArray();

        if ($request->ajax()) {
            $query = TransactionSellLine::join(
                'transactions as t',
                'transaction_sell_lines.transaction_id',
                '=',
                't.id'
            )
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->join('products as p', 'transaction_sell_lines.product_id', '=', 'p.id')
                ->leftjoin('categories as cat', 'p.category_id', '=', 'cat.id')
                ->leftjoin('tax_rates as tr', 'transaction_sell_lines.tax_id', '=', 'tr.id')
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->select(
                    'c.name as customer',
                    'c.supplier_business_name',
                    'c.contact_id',
                    'c.tax_number',
                    'cat.short_code',
                    't.id as transaction_id',
                    't.invoice_no',
                    't.transaction_date as transaction_date',
                    'transaction_sell_lines.unit_price_before_discount as unit_price',
                    'transaction_sell_lines.unit_price as unit_price_after_discount',
                    DB::raw('(transaction_sell_lines.quantity - transaction_sell_lines.quantity_returned) as sell_qty'),
                    'transaction_sell_lines.line_discount_type as discount_type',
                    'transaction_sell_lines.line_discount_amount as discount_amount',
                    'transaction_sell_lines.item_tax',
                    'tr.amount as tax_percent',
                    'tr.is_tax_group',
                    'transaction_sell_lines.tax_id',
                    'u.short_name as unit',
                    'transaction_sell_lines.parent_sell_line_id',
                    DB::raw('((transaction_sell_lines.quantity- transaction_sell_lines.quantity_returned) * transaction_sell_lines.unit_price_inc_tax) as line_total'),
                )
                ->groupBy('transaction_sell_lines.id');

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $customer_id = $request->get('customer_id', null);
            if (! empty($customer_id)) {
                $query->where('t.contact_id', $customer_id);
            }

            $datatable = Datatables::of($query);

            $raw_cols = ['invoice_no', 'taxable_value', 'discount_amount', 'unit_price', 'tax', 'customer', 'line_total'];
            $group_taxes_array = TaxRate::groupTaxes($business_id);
            $group_taxes = [];
            foreach ($group_taxes_array as $group_tax) {
                foreach ($group_tax['sub_taxes'] as $sub_tax) {
                    $group_taxes[$group_tax->id]['sub_taxes'][$sub_tax->id] = $sub_tax;
                }
            }
            foreach ($taxes as $tax) {
                $col = 'tax_'.$tax['id'];
                $raw_cols[] = $col;
                $datatable->addColumn($col, function ($row) use ($tax, $col, $group_taxes) {
                    $sub_tax_share = 0;
                    if ($row->is_tax_group == 1 && array_key_exists($tax['id'], $group_taxes[$row->tax_id]['sub_taxes'])) {
                        $sub_tax_share = $this->transactionUtil->calc_percentage($row->unit_price_after_discount, $group_taxes[$row->tax_id]['sub_taxes'][$tax['id']]->amount) * $row->sell_qty;
                    }

                    if ($sub_tax_share > 0) {
                        //ignore child sell line of combo product
                        $class = is_null($row->parent_sell_line_id) ? $col : '';

                        return '<span class="'.$class.'" data-orig-value="'.$sub_tax_share.'">'.$this->transactionUtil->num_f($sub_tax_share).'</span>';
                    } else {
                        return '';
                    }
                });
            }

            return $datatable->addColumn('taxable_value', function ($row) {
                $taxable_value = $row->unit_price_after_discount * $row->sell_qty;
                //ignore child sell line of combo product
                $class = is_null($row->parent_sell_line_id) ? 'taxable_value' : '';

                return '<span class="'.$class.'"data-orig-value="'.$taxable_value.'">'.$this->transactionUtil->num_f($taxable_value).'</span>';
            })
                 ->editColumn('invoice_no', function ($row) {
                     return '<a data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->invoice_no.'</a>';
                 })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('sell_qty', function ($row) {
                    return $this->transactionUtil->num_f($row->sell_qty, false, null, true).' '.$row->unit;
                })
                ->editColumn('unit_price', function ($row) {
                    return '<span data-orig-value="'.$row->unit_price.'">'.$this->transactionUtil->num_f($row->unit_price).'</span>';
                })
                ->editColumn('line_total', function ($row) {
                    return '<span data-orig-value="'.$row->line_total.'">'.$this->transactionUtil->num_f($row->line_total).'</span>';
                })
                ->editColumn(
                    'discount_amount',
                    function ($row) {
                        $discount = ! empty($row->discount_amount) ? $row->discount_amount : 0;

                        if (! empty($discount) && $row->discount_type == 'percentage') {
                            $discount = $row->unit_price * ($discount / 100);
                        }

                        return $this->transactionUtil->num_f($discount);
                    }
                )
                ->editColumn('tax_percent', '@if(!empty($tax_percent)){{@num_format($tax_percent)}}% @endif
                    ')
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$customer}}')
                ->rawColumns($raw_cols)
                ->make(true);
        }

        $customers = Contact::customersDropdown($business_id);

        return view('report.gst_sales_report')->with(compact('customers', 'taxes'));
    }

    public function gstPurchaseReport(Request $request)
    {
        if (! auth()->user()->can('tax_report.view') || empty(config('constants.enable_gst_report_india'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                        ->where('is_tax_group', 0)
                        ->select(['id', 'name', 'amount'])
                        ->get()
                        ->toArray();

        if ($request->ajax()) {
            $query = PurchaseLine::join(
                'transactions as t',
                'purchase_lines.transaction_id',
                '=',
                't.id'
            )
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->join('products as p', 'purchase_lines.product_id', '=', 'p.id')
                ->leftjoin('categories as cat', 'p.category_id', '=', 'cat.id')
                ->leftjoin('tax_rates as tr', 'purchase_lines.tax_id', '=', 'tr.id')
                ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'purchase')
                ->where('t.status', 'received')
                ->select(
                    'c.name as supplier',
                    'c.supplier_business_name',
                    'c.contact_id',
                    'c.tax_number',
                    'cat.short_code',
                    't.id as transaction_id',
                    't.ref_no',
                    't.transaction_date as transaction_date',
                    'purchase_lines.pp_without_discount as unit_price',
                    'purchase_lines.purchase_price as unit_price_after_discount',
                    DB::raw('(purchase_lines.quantity - purchase_lines.quantity_returned) as purchase_qty'),
                    'purchase_lines.discount_percent',
                    'purchase_lines.item_tax',
                    'tr.amount as tax_percent',
                    'tr.is_tax_group',
                    'purchase_lines.tax_id',
                    'u.short_name as unit',
                    DB::raw('((purchase_lines.quantity- purchase_lines.quantity_returned) * purchase_lines.purchase_price_inc_tax) as line_total')
                )
                ->groupBy('purchase_lines.id');

            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            if (! empty($start_date) && ! empty($end_date)) {
                $query->where('t.transaction_date', '>=', $start_date)
                    ->where('t.transaction_date', '<=', $end_date);
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('t.location_id', $permitted_locations);
            }

            $location_id = $request->get('location_id', null);
            if (! empty($location_id)) {
                $query->where('t.location_id', $location_id);
            }

            $supplier_id = $request->get('supplier_id', null);
            if (! empty($supplier_id)) {
                $query->where('t.contact_id', $supplier_id);
            }

            $datatable = Datatables::of($query);

            $raw_cols = ['ref_no', 'taxable_value', 'discount_amount', 'unit_price', 'tax', 'supplier', 'line_total'];
            $group_taxes_array = TaxRate::groupTaxes($business_id);
            $group_taxes = [];
            foreach ($group_taxes_array as $group_tax) {
                foreach ($group_tax['sub_taxes'] as $sub_tax) {
                    $group_taxes[$group_tax->id]['sub_taxes'][$sub_tax->id] = $sub_tax;
                }
            }
            foreach ($taxes as $tax) {
                $col = 'tax_'.$tax['id'];
                $raw_cols[] = $col;
                $datatable->addColumn($col, function ($row) use ($tax, $group_taxes) {
                    $sub_tax_share = 0;
                    if ($row->is_tax_group == 1 && array_key_exists($tax['id'], $group_taxes[$row->tax_id]['sub_taxes'])) {
                        $sub_tax_share = $this->transactionUtil->calc_percentage($row->unit_price_after_discount, $group_taxes[$row->tax_id]['sub_taxes'][$tax['id']]->amount) * $row->purchase_qty;
                    }

                    if ($sub_tax_share > 0) {
                        return '<span data-orig-value="'.$sub_tax_share.'">'.$this->transactionUtil->num_f($sub_tax_share).'</span>';
                    } else {
                        return '';
                    }
                });
            }

            return $datatable->addColumn('taxable_value', function ($row) {
                $taxable_value = $row->unit_price_after_discount * $row->purchase_qty;

                return '<span class="taxable_value"data-orig-value="'.$taxable_value.'">'.$this->transactionUtil->num_f($taxable_value).'</span>';
            })
                 ->editColumn('ref_no', function ($row) {
                     return '<a data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->transaction_id])
                            .'" href="#" data-container=".view_modal" class="btn-modal">'.$row->ref_no.'</a>';
                 })
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('purchase_qty', function ($row) {
                    return $this->transactionUtil->num_f($row->purchase_qty, false, null, true).' '.$row->unit;
                })
                ->editColumn('unit_price', function ($row) {
                    return '<span data-orig-value="'.$row->unit_price.'">'.$this->transactionUtil->num_f($row->unit_price).'</span>';
                })
                ->editColumn('line_total', function ($row) {
                    return '<span data-orig-value="'.$row->line_total.'">'.$this->transactionUtil->num_f($row->line_total).'</span>';
                })
                ->addColumn(
                    'discount_amount',
                    function ($row) {
                        $discount = ! empty($row->discount_percent) ? $row->discount_percent : 0;

                        if (! empty($discount)) {
                            $discount = $row->unit_price * ($discount / 100);
                        }

                        return $this->transactionUtil->num_f($discount);
                    }
                )
                ->editColumn('tax_percent', '@if(!empty($tax_percent)){{@num_format($tax_percent)}}% @endif
                    ')
                ->editColumn('supplier', '@if(!empty($supplier_business_name)) {{$supplier_business_name}},<br>@endif {{$supplier}}')
                ->rawColumns($raw_cols)
                ->make(true);
        }

        $suppliers = Contact::suppliersDropdown($business_id);

        return view('report.gst_purchase_report')->with(compact('suppliers', 'taxes'));
    }
}
