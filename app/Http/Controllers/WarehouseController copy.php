<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class WarehouseController extends Controller
{
    protected $transactionUtil;
    protected $businessUtil;
    protected $commonUtil;

    public function __construct(TransactionUtil $transactionUtil, BusinessUtil $businessUtil, Util $commonUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->commonUtil = $commonUtil;
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    private function getDataForTableCustomerRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

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
            ->leftJoin(
                'transactions AS PR',
                'transactions.id',
                '=',
                'PR.return_parent_id'
            )
            ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'purchase')
            ->select(
                'transactions.id',
                'transactions.document',
                'transactions.transaction_date',
                'transactions.ref_no',
                'contacts.id as contact_id',
                'contacts.name',
                'contacts.supplier_business_name',
                'transactions.status',
                'transactions.payment_status',
                'transactions.final_total',
                'BS.name as location_name',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'PR.id as return_transaction_id',
                DB::raw('SUM(TP.amount) as amount_paid'),
                DB::raw('(SELECT SUM(TP2.amount) FROM transaction_payments AS TP2 WHERE TP2.transaction_id=PR.id ) as return_paid'),
                DB::raw('COUNT(PR.id) as return_exists'),
                DB::raw('COALESCE(PR.final_total, 0) as amount_return'),
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
            )
            ->groupBy('transactions.id')
            ->orderBy('transactions.transaction_date', 'desc'); // Sort by transaction_date in descending order

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $purchases->whereDate('transactions.transaction_date', '>=', $start)
                        ->whereDate('transactions.transaction_date', '<=', $end);
        }

        if ($request->has('location_id') && !empty($request->location_id)) {
            $purchases->where('transactions.location_id', $request->location_id);
        }

        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $purchases->where('contacts.id', $request->supplier_id);
        }

        if ($request->has('status') && !empty($request->status)) {
            $purchases->where('transactions.status', $request->status);
        }

        if ($request->has('payment_status') && !empty($request->payment_status)) {
            $purchases->where('transactions.payment_status', $request->payment_status);
        }

        return $purchases;
    }

    // Display the list of customer rings
    public function index(Request $request)
    {
        if ($request->ajax()) {
            // Get individual stock records without summing
            $data = $this->getDataForTableCustomerRing($request);

            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $view = '<li><a href="#" data-href="'.action([\App\Http\Controllers\WarehouseController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    $update_status = '<li><a href="#" class="update-status-btn" data-purchase-id="'.$row->id.'" data-current-status="'.$row->status.'"><i class="fas fa-edit" aria-hidden="true"></i> '.__('Update Status').'</a></li>';
                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">' . $view . $update_status . '</ul>
                            </div>';
                })
                ->addColumn('transaction_date', function ($row) {
                    return $row->transaction_date;
                })
                ->addColumn('ref_no', function ($row) {
                    return $row->ref_no;
                })
                ->addColumn('location_name', function ($row) {
                    return $row->location_name;
                })
                ->addColumn('name', function ($row) {
                    return $row->name;
                })
                ->addColumn('status', function ($row) {
                    $status_class = '';
                    switch ($row->status) {
                        case 'received':
                            $status_class = 'status-completed';
                            break;
                        case 'pending':
                            $status_class = 'status-pending';
                            break;
                        case 'ordered':
                            $status_class = 'status-ordered';
                            break;
                    }
                    return '<span class="label '.$status_class.'">'.__('lang_v1.' . $row->status).'</span>';
                })
                ->addColumn('payment_status', function ($row) {
                    $payment_status_class = '';
                    switch ($row->payment_status) {
                        case 'paid':
                            $payment_status_class = 'label-success';
                            break;
                        case 'due':
                            $payment_status_class = 'label-warning';
                            break;
                        case 'partial':
                            $payment_status_class = 'label-partial';
                            break;
                        case 'overdue':
                            $payment_status_class = 'label-danger';
                            break;
                    }
                    return '<span class="label ' . $payment_status_class . '">'.__('lang_v1.' . $row->payment_status).'</span>';
                })
                ->addColumn('added_by', function ($row) {
                    return $row->added_by;
                })
                ->rawColumns(['action', 'status', 'payment_status'])
                ->make(true);
        }

        $business_id = $request->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);

        return view('warehouse.index', compact('business_locations', 'suppliers'));
    }
    public function show($id)
    {
        $business_id = session()->get('user.business_id');
    
        // Fetch the transaction with related data
        $purchase = Transaction::where('business_id', $business_id)
            ->where('id', $id)
            ->with(
                'contact',
                'purchase_lines',
                'purchase_lines.product',
                'purchase_lines.product.unit',
                'purchase_lines.sub_unit',
                'location',
                'payment_lines',
                'tax'
            )
            ->firstOrFail();
    
        // Check if purchase_order_ids is not null and fetch related transactions
        $order_transactions = [];
        if (!empty($purchase->purchase_order_ids) && is_array($purchase->purchase_order_ids)) {
            $order_transactions = Transaction::whereIn('id', $purchase->purchase_order_ids)
                ->select('id', 'ref_no')
                ->get();
        }
    
        return view('warehouse.show', compact('purchase', 'order_transactions'));
    }       
    
    public function updateStatus(Request $request, $id)
    {
        $business_id = session()->get('user.business_id');
        $purchase = Transaction::where('business_id', $business_id)->where('id', $id)->firstOrFail();
        $purchase->status = $request->input('status');
        $purchase->save();
    
        // Check if the status is "received"
        if ($request->input('status') === 'received') {
            $purchaseLines = $purchase->purchase_lines;
            foreach ($purchaseLines as $line) {
                // Check if entry exists in variation_location_details
                $variationLocationDetail = DB::table('variation_location_details')
                    ->where('product_id', $line->product_id)
                    ->where('variation_id', $line->variation_id)
                    ->where('location_id', $purchase->location_id)
                    ->first();
    
                if ($variationLocationDetail) {
                    // If exists, update qty_available
                    DB::table('variation_location_details')
                        ->where('id', $variationLocationDetail->id)
                        ->update([
                            'qty_available' => DB::raw('qty_available + ' . $line->quantity)
                        ]);
                } else {
                    // If not exists, create new entry
                    $productVariation = DB::table('product_variations')
                        ->where('product_id', $line->product_id)
                        ->first();
    
                    DB::table('variation_location_details')->insert([
                        'product_id' => $line->product_id,
                        'product_variation_id' => $productVariation->id,
                        'variation_id' => $line->variation_id,
                        'location_id' => $purchase->location_id,
                        'qty_available' => $line->quantity,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    
        return response()->json(['success' => true, 'message' => __('Purchase status updated successfully.')]);
    }

}
