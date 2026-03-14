<?php

namespace App\Http\Controllers;

use App\AccountTransaction;
use App\BusinessLocation;
use App\CurrentStockBackup;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class PurchaseReturnController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ProductUtil $productUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $purchases_returns = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                    ->join(
                        'business_locations AS BS',
                        'transactions.location_id',
                        '=',
                        'BS.id'
                    )
                    ->leftJoin(
                        'transactions AS T',
                        'transactions.return_parent_id',
                        '=',
                        'T.id'
                    )
                    ->leftJoin(
                        'transaction_payments AS TP',
                        'transactions.id',
                        '=',
                        'TP.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase_return')
                    ->select(
                        'transactions.id',
                        'transactions.transaction_date',
                        'transactions.ref_no',
                        'contacts.name',
                        'contacts.supplier_business_name',
                        'transactions.status',
                        'transactions.payment_status',
                        'transactions.final_total',
                        'transactions.return_parent_id',
                        'BS.name as location_name',
                        'T.ref_no as parent_purchase',
                        DB::raw('SUM(TP.amount) as amount_paid')
                    )
                    ->groupBy('transactions.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $purchases_returns->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->location_id)) {
                $purchases_returns->where('transactions.location_id', request()->location_id);
            }

            if (! empty(request()->supplier_id)) {
                $supplier_id = request()->supplier_id;
                $purchases_returns->where('contacts.id', $supplier_id);
            }
            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $purchases_returns->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            return Datatables::of($purchases_returns)
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" role="menu">';
                    if (! empty($row->return_parent_id)) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseReturnController::class, 'add'], $row->return_parent_id).'" ><i class="glyphicon glyphicon-edit"></i>'.
                                __('messages.edit').
                                '</a></li>';
                    } else {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\CombinedPurchaseReturnController::class, 'edit'], $row->id).'" ><i class="glyphicon glyphicon-edit"></i>'.
                                __('messages.edit').
                                '</a></li>';
                    }

                    if ($row->payment_status != 'paid') {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i>'.__('purchase.add_payment').'</a></li>';
                    }

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).'" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i>'.__('purchase.view_payments').'</a></li>';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseReturnController::class, 'destroy'], $row->id).'" class="delete_purchase_return" ><i class="fa fa-trash"></i>'.
                                __('messages.delete').
                                '</a></li>';
                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->removeColumn('return_parent_id')
                ->editColumn(
                    'final_total',
                    '<span class="display_currency final_total" data-currency_symbol="true" data-orig-value="{{$final_total}}">{{$final_total}}</span>'
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('name', function ($row) {
                    $name = ! empty($row->name) ? $row->name : '';

                    return $name.' '.$row->supplier_business_name;
                })
                ->editColumn(
                    'payment_status',
                    '<a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'show\'], [$id])}}" class="view_payment_modal payment-status payment-status-label" data-orig-value="{{$payment_status}}" data-status-name="@if($payment_status != "paid"){{__(\'lang_v1.\' . $payment_status)}}@else{{__("lang_v1.received")}}@endif"><span class="label @payment_status($payment_status)">@if($payment_status != "paid"){{__(\'lang_v1.\' . $payment_status)}} @else {{__("lang_v1.received")}} @endif
                        </span></a>'
                )
                ->editColumn('parent_purchase', function ($row) {
                    $html = '';
                    if (! empty($row->parent_purchase)) {
                        $html = '<a href="#" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->return_parent_id]).'" class="btn-modal" data-container=".view_modal">'.$row->parent_purchase.'</a>';
                    }

                    return $html;
                })
                ->addColumn('payment_due', function ($row) {
                    $due = $row->final_total - $row->amount_paid;

                    return '<span class="display_currency payment_due" data-currency_symbol="true" data-orig-value="'.$due.'">'.$due.'</sapn>';
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('purchase.view')) {
                            $return_id = ! empty($row->return_parent_id) ? $row->return_parent_id : $row->id;

                            return  action([\App\Http\Controllers\PurchaseReturnController::class, 'show'], [$return_id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['final_total', 'action', 'payment_status', 'parent_purchase', 'payment_due'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('purchase_return.index')->with(compact('business_locations'));
    }

    /**
     * Show the form for purchase return.
     *
     * @return \Illuminate\Http\Response
     */
    public function add($id)
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');

        $purchase = Transaction::where('business_id', $business_id)
                        ->where('type', 'purchase')
                        ->with(['purchase_lines', 'contact', 'tax', 'return_parent', 'purchase_lines.sub_unit', 'purchase_lines.product', 'purchase_lines.product.unit'])
                        ->find($id);

        foreach ($purchase->purchase_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                $purchase->purchase_lines[$key] = $formated_purchase_line;
            }
        }

        foreach ($purchase->purchase_lines as $key => $value) {
            $qty_available = $value->quantity - $value->quantity_sold - $value->quantity_adjusted;

            $purchase->purchase_lines[$key]->formatted_qty_available = $this->transactionUtil->num_f($qty_available);
        }

        return view('purchase_return.add')
                    ->with(compact('purchase'));
    }

    /**
     * Saves Purchase returns in the database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $purchase = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase')
                ->with(['purchase_lines', 'purchase_lines.sub_unit'])
                ->findOrFail($request->input('transaction_id'));

            $return_quantities = $request->input('returns');
            $return_total = 0;

            DB::beginTransaction();

            // Collect stock backup lines
            $stock_lines = [];
            $reversal_stock_lines = [];

            // Check if editing an existing purchase return
            $return_transaction = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase_return')
                ->where('return_parent_id', $purchase->id)
                ->first();

            $is_edit = !empty($return_transaction);

            // Step 1: Process reversals for edit purchase return (if quantities changed)
            if ($is_edit) {
                foreach ($purchase->purchase_lines as $purchase_line) {
                    $old_return_qty = $purchase_line->quantity_returned;
                    $return_quantity = !empty($return_quantities[$purchase_line->id]) ? $this->productUtil->num_uf($return_quantities[$purchase_line->id]) : 0;

                    $multiplier = 1;
                    if (!empty($purchase_line->sub_unit->base_unit_multiplier)) {
                        $multiplier = $purchase_line->sub_unit->base_unit_multiplier;
                        $return_quantity = $return_quantity * $multiplier;
                    }

                    // Stock backup for edit_purchase_return_reversal (only for changed quantities)
                    if ($old_return_qty > 0 && $old_return_qty != $return_quantity) {
                        // Fetch the original return quantity from create_purchase_return for product_id and transaction_id
                        $last_return = CurrentStockBackup::where('business_id', $business_id)
                            ->where('product_id', $purchase_line->product_id)
                            ->where('location_id', $purchase->location_id)
                            ->where('transaction_id', $return_transaction->id)
                            ->where('t_type', 'create_purchase_return')
                            ->orderBy('transaction_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        if ($last_return) {
                            $old_quantity = $last_return->qty_change;
                            // Fetch the latest new_qty for the product_id and location_id
                            $latest_stock = CurrentStockBackup::where('business_id', $business_id)
                                ->where('product_id', $purchase_line->product_id)
                                ->where('location_id', $purchase->location_id)
                                ->orderBy('transaction_date', 'desc')
                                ->orderBy('id', 'desc')
                                ->first();

                            $current_stock = $latest_stock ? $latest_stock->new_qty : 0;
                            $new_qty = $current_stock + $old_quantity;

                            // Prepare stock backup line for edit_purchase_return_reversal
                            $reversal_stock_line = [
                                'business_id' => $business_id,
                                'location_id' => $purchase->location_id,
                                'product_id' => $purchase_line->product_id,
                                'variation_id' => $purchase_line->variation_id,
                                'transaction_id' => $return_transaction->id,
                                't_type' => 'edit_purchase_return_reversal',
                                'qty_change' => $old_quantity,
                                'new_qty' => $new_qty,
                                'transaction_date' => now(),
                            ];
                            $reversal_stock_lines[] = new CurrentStockBackup($reversal_stock_line);
                        }
                    }
                }

                // Save reversal stock backup lines first
                if (!empty($reversal_stock_lines)) {
                    CurrentStockBackup::insert(
                        array_map(function ($line) {
                            return $line->toArray();
                        }, $reversal_stock_lines)
                    );
                }
            }

            // Step 2: Process purchase lines and create_purchase_return
            foreach ($purchase->purchase_lines as $purchase_line) {
                $old_return_qty = $purchase_line->quantity_returned;
                $return_quantity = !empty($return_quantities[$purchase_line->id]) ? $this->productUtil->num_uf($return_quantities[$purchase_line->id]) : 0;

                $multiplier = 1;
                if (!empty($purchase_line->sub_unit->base_unit_multiplier)) {
                    $multiplier = $purchase_line->sub_unit->base_unit_multiplier;
                    $return_quantity = $return_quantity * $multiplier;
                }

                $purchase_line->quantity_returned = $return_quantity;
                $purchase_line->save();
                $return_total += $purchase_line->purchase_price_inc_tax * $purchase_line->quantity_returned;

                // Decrease quantity in variation location details
                if ($old_return_qty != $purchase_line->quantity_returned) {
                    $this->productUtil->decreaseProductQuantity(
                        $purchase_line->product_id,
                        $purchase_line->variation_id,
                        $purchase->location_id,
                        $purchase_line->quantity_returned,
                        $old_return_qty
                    );
                }

                // Stock backup for create_purchase_return
                if ($return_quantity > 0) {
                    // Fetch the latest new_qty for the product_id and location_id
                    $latest_stock = CurrentStockBackup::where('business_id', $business_id)
                        ->where('product_id', $purchase_line->product_id)
                        ->where('location_id', $purchase->location_id)
                        ->orderBy('transaction_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->first();

                    $current_stock = $latest_stock ? $latest_stock->new_qty : 0;
                    $new_qty = $current_stock - $return_quantity;

                    // Prepare stock backup line for create_purchase_return
                    $stock_line = [
                        'business_id' => $business_id,
                        'location_id' => $purchase->location_id,
                        'product_id' => $purchase_line->product_id,
                        'variation_id' => $purchase_line->variation_id,
                        'transaction_id' => $return_transaction ? $return_transaction->id : 0, // Will update after creating return_transaction
                        't_type' => 'create_purchase_return',
                        'qty_change' => $return_quantity,
                        'new_qty' => $new_qty,
                        'transaction_date' => now(),
                    ];
                    $stock_lines[] = new CurrentStockBackup($stock_line);
                }
            }

            $return_total_inc_tax = $return_total + $request->input('tax_amount');

            $return_transaction_data = [
                'total_before_tax' => $return_total,
                'final_total' => $return_total_inc_tax,
                'tax_amount' => $request->input('tax_amount'),
                'tax_id' => $purchase->tax_id,
            ];

            if (empty($request->input('ref_no'))) {
                // Update reference count
                $ref_count = $this->transactionUtil->setAndGetReferenceCount('purchase_return');
                $return_transaction_data['ref_no'] = $this->transactionUtil->generateReferenceNumber('purchase_return', $ref_count);
            }

            if ($is_edit) {
                $return_transaction_before = $return_transaction->replicate();
                $return_transaction->update($return_transaction_data);
                $this->transactionUtil->activityLog($return_transaction, 'edited', $return_transaction_before);
            } else {
                $return_transaction_data['business_id'] = $business_id;
                $return_transaction_data['location_id'] = $purchase->location_id;
                $return_transaction_data['type'] = 'purchase_return';
                $return_transaction_data['status'] = 'final';
                $return_transaction_data['contact_id'] = $purchase->contact_id;
                $return_transaction_data['transaction_date'] = now();
                $return_transaction_data['created_by'] = request()->session()->get('user.id');
                $return_transaction_data['return_parent_id'] = $purchase->id;

                $return_transaction = Transaction::create($return_transaction_data);
                $this->transactionUtil->activityLog($return_transaction, 'added');
            }

            // Update stock backup transaction_id for create_purchase_return
            if (!empty($stock_lines)) {
                foreach ($stock_lines as $stock_line) {
                    $stock_line->transaction_id = $return_transaction->id;
                }
                CurrentStockBackup::insert(
                    array_map(function ($line) {
                        return $line->toArray();
                    }, $stock_lines)
                );
            }

            // Update payment status
            $this->transactionUtil->updatePaymentStatus($return_transaction->id, $return_transaction->final_total);

            DB::commit();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.purchase_return_added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('purchase-return')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('purchase.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $purchase = Transaction::where('business_id', $business_id)
                        ->with(['return_parent', 'return_parent.tax', 'purchase_lines', 'contact', 'tax', 'purchase_lines.sub_unit', 'purchase_lines.product', 'purchase_lines.product.unit'])
                        ->find($id);

        foreach ($purchase->purchase_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_purchase_line = $this->productUtil->changePurchaseLineUnit($value, $business_id);
                $purchase->purchase_lines[$key] = $formated_purchase_line;
            }
        }

        $purchase_taxes = [];
        if (! empty($purchase->return_parent->tax)) {
            if ($purchase->return_parent->tax->is_tax_group) {
                $purchase_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($purchase->return_parent->tax, $purchase->return_parent->tax_amount));
            } else {
                $purchase_taxes[$purchase->return_parent->tax->name] = $purchase->return_parent->tax_amount;
            }
        }

        //For combined purchase return return_parent is empty
        if (empty($purchase->return_parent) && ! empty($purchase->tax)) {
            if ($purchase->tax->is_tax_group) {
                $purchase_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($purchase->tax, $purchase->tax_amount));
            } else {
                $purchase_taxes[$purchase->tax->name] = $purchase->tax_amount;
            }
        }
        $return = ! empty($purchase->return_parent) ? $purchase->return_parent : $purchase;
        $activities = Activity::forSubject($return)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('purchase_return.show')
                ->with(compact('purchase', 'purchase_taxes', 'activities'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('purchase.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if (request()->ajax()) {
                $business_id = request()->session()->get('user.business_id');

                $purchase_return = Transaction::where('id', $id)
                    ->where('business_id', $business_id)
                    ->where('type', 'purchase_return')
                    ->with(['purchase_lines'])
                    ->first();

                DB::beginTransaction();

                // Initialize stock backup lines
                $stock_lines = [];

                // Handle stock backup for delete_purchase_return
                if ($purchase_return) {
                    // Fetch all create_purchase_return records for this transaction_id
                    $return_records = CurrentStockBackup::where('business_id', $business_id)
                        ->where('transaction_id', $purchase_return->id)
                        ->where('t_type', 'create_purchase_return')
                        ->get()
                        ->groupBy('product_id');

                    foreach ($return_records as $product_id => $records) {
                        // Use the latest record for the product_id
                        $last_return = $records->sortByDesc('transaction_date')->sortByDesc('id')->first();
                        $return_quantity = $last_return->qty_change;

                        // Fetch the latest new_qty for the product_id and location_id
                        $latest_stock = CurrentStockBackup::where('business_id', $business_id)
                            ->where('product_id', $product_id)
                            ->where('location_id', $purchase_return->location_id)
                            ->orderBy('transaction_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        $current_stock = $latest_stock ? $latest_stock->new_qty : 0;
                        $new_qty = $current_stock + $return_quantity;

                        // Prepare stock backup line for delete_purchase_return
                        $stock_line = [
                            'business_id' => $business_id,
                            'location_id' => $purchase_return->location_id,
                            'product_id' => $product_id,
                            'variation_id' => $last_return->variation_id,
                            'transaction_id' => $purchase_return->id,
                            't_type' => 'delete_purchase_return',
                            'qty_change' => $return_quantity,
                            'new_qty' => $new_qty,
                            'transaction_date' => now(),
                        ];
                        $stock_lines[] = new CurrentStockBackup($stock_line);
                    }

                    // Save delete_purchase_return stock backup lines
                    if (!empty($stock_lines)) {
                        CurrentStockBackup::insert(
                            array_map(function ($line) {
                                return $line->toArray();
                            }, $stock_lines)
                        );
                    }
                }

                // Update stock and purchase lines
                if (empty($purchase_return->return_parent_id)) {
                    $delete_purchase_lines = $purchase_return->purchase_lines;
                    $delete_purchase_line_ids = [];
                    foreach ($delete_purchase_lines as $purchase_line) {
                        $delete_purchase_line_ids[] = $purchase_line->id;
                        $this->productUtil->updateProductQuantity(
                            $purchase_return->location_id,
                            $purchase_line->product_id,
                            $purchase_line->variation_id,
                            $purchase_line->quantity_returned,
                            0,
                            null,
                            false
                        );
                    }
                    PurchaseLine::where('transaction_id', $purchase_return->id)
                        ->whereIn('id', $delete_purchase_line_ids)
                        ->delete();
                } else {
                    $parent_purchase = Transaction::where('id', $purchase_return->return_parent_id)
                        ->where('business_id', $business_id)
                        ->where('type', 'purchase')
                        ->with(['purchase_lines'])
                        ->first();

                    $updated_purchase_lines = $parent_purchase->purchase_lines;
                    foreach ($updated_purchase_lines as $purchase_line) {
                        $this->productUtil->updateProductQuantity(
                            $parent_purchase->location_id,
                            $purchase_line->product_id,
                            $purchase_line->variation_id,
                            $purchase_line->quantity_returned,
                            0,
                            null,
                            false
                        );
                        $purchase_line->quantity_returned = 0;
                        $purchase_line->save();
                    }
                }

                // Delete Transaction
                $purchase_return->delete();

                // Delete account transactions
                AccountTransaction::where('transaction_id', $id)->delete();

                DB::commit();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
