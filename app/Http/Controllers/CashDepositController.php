<?php

namespace App\Http\Controllers;

use App\TransactionPayment;
use App\DepositHistory;
use App\Utils\TransactionUtil;
use App\BusinessLocation;
use App\Contact;
use App\Account;
use App\Media;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;

class CashDepositController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Permission Check: View
        if (!auth()->user()->can('cash_deposit.view') && !auth()->user()->can('cash_deposit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if ($request->ajax()) {
            // 1. Base Query: Sell Transactions + Cash Payments
            $query = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->leftJoin('business_locations as bl', 't.location_id', '=', 'bl.id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('transaction_payments.method', 'cash')
                ->select([
                    'transaction_payments.id as payment_id',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.paid_on',
                    'transaction_payments.amount',
                    't.invoice_no',
                    't.id as transaction_id',
                    'c.name as customer_name',
                    'bl.name as location_name'
                ]);

            // 2. Filters
            if (!empty($request->start_date) && !empty($request->end_date)) {
                $query->whereBetween(DB::raw('date(transaction_payments.paid_on)'), [$request->start_date, $request->end_date]);
            }
            if (!empty($request->location_id)) {
                $query->where('t.location_id', $request->location_id);
            }
            if (!empty($request->customer_id)) {
                $query->where('t.contact_id', $request->customer_id);
            }

            // 3. Deposit Status Logic
            $query->addSelect(DB::raw("
                (SELECT COUNT(*) 
                 FROM deposit_history 
                 WHERE JSON_CONTAINS(deposit_history.transaction_payment_ids, CONCAT('\"', transaction_payments.id, '\"')) 
                 AND deposit_history.business_id = $business_id
                 AND deposit_history.deleted_at IS NULL
                ) as is_deposited
            "));

            if ($request->has('deposit_status') && $request->deposit_status != '') {
                if ($request->deposit_status == 'deposited') {
                    $query->having('is_deposited', '>', 0);
                } elseif ($request->deposit_status == 'pending') {
                    $query->having('is_deposited', '=', 0);
                }
            }

            return Datatables::of($query)
                ->addColumn('action', function ($row) {
                    if ($row->is_deposited == 0) {
                        if (auth()->user()->can('cash_deposit.create')) {
                            return '<input type="checkbox" class="deposit_check" value="'.$row->payment_id.'" data-amount="'.$row->amount.'" data-invoice="'.$row->invoice_no.'">';
                        }
                    }
                    return ''; 
                })
                ->editColumn('paid_on', function ($row) {
                    return $this->transactionUtil->format_date($row->paid_on, true);
                })
                // --- FIX: Use .invoice_modal instead of .view_modal to prevent duplicates ---
                ->editColumn('invoice_no', function ($row) {
                    return '<button type="button" class="btn btn-link btn-modal" data-href="' . action([\App\Http\Controllers\SellController::class, 'show'], [$row->transaction_id]) . '" data-container=".invoice_modal" style="padding:0;">' . $row->invoice_no . '</button>';
                })
                ->editColumn('amount', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">' . 
                           $this->transactionUtil->num_f($row->amount) . '</span>';
                })
                ->addColumn('deposit_status', function ($row) {
                    if ($row->is_deposited > 0) {
                        return '<span class="label label-success">Completed</span>';
                    } else {
                        return '<span class="label label-warning">Pending</span>';
                    }
                })
                ->rawColumns(['action', 'amount', 'deposit_status', 'invoice_no'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $customers = Contact::customersDropdown($business_id, false);
        $bank_accounts = Account::where('business_id', $business_id)->pluck('name', 'id');

        return view('cash_deposit.index', compact('business_locations', 'customers', 'bank_accounts'));
    }

    /**
     * Store a newly created deposit.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('cash_deposit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        try {
            $request->validate([
                'bank_account_id' => 'required|exists:accounts,id',
                'ref_no' => 'nullable|string|max:191',
                'deposit_datetime' => 'required|date',
                'payment_ids' => 'required|string',
                'total_amount' => 'required'
            ]);

            $payment_ids = explode(',', $request->payment_ids);

            DB::beginTransaction();

            $payments = TransactionPayment::whereIn('id', $payment_ids)
                ->whereHas('transaction', function($q) use ($business_id) {
                    $q->where('business_id', $business_id)->where('type', 'sell');
                })
                ->where('method', 'cash')
                ->get();

            if ($payments->count() !== count($payment_ids)) {
                throw new \Exception('Invalid payment selection');
            }

            foreach ($payments as $payment) {
                $existingDeposit = DepositHistory::where('business_id', $business_id)
                    ->whereRaw("JSON_CONTAINS(transaction_payment_ids, '\"$payment->id\"')")
                    ->whereNull('deleted_at')
                    ->exists();
                
                if ($existingDeposit) {
                    throw new \Exception('Payment #' . $payment->payment_ref_no . ' has already been deposited');
                }
            }

            $actual_total = $payments->sum('amount');

            $ref_no = $request->ref_no;
            if (empty($ref_no)) {
                $lastDeposit = DepositHistory::where('business_id', $business_id)->orderBy('id', 'desc')->first();
                $nextNumber = ($lastDeposit && is_numeric($lastDeposit->ref_no)) ? intval($lastDeposit->ref_no) + 1 : 1;
                $ref_no = str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            }

            $deposit = DepositHistory::create([
                'business_id' => $business_id,
                'bank_account_id' => $request->bank_account_id,
                'ref_no' => $ref_no,
                'deposit_datetime' => $request->deposit_datetime,
                'amount' => $actual_total,
                'status' => 'posted',
                'transaction_payment_ids' => $payment_ids,
                'created_by' => $user_id
            ]);

            if (!empty($request->input('deposit_slip_names'))) {
                $file_names = explode(',', $request->input('deposit_slip_names'));
                $media_objects = [];
                foreach ($file_names as $file_name) {
                    $media_objects[] = new Media([
                        'business_id' => $business_id,
                        'file_name' => trim($file_name),
                        'uploaded_by' => $user_id,
                        'model_media_type' => 'deposit_slip',
                        'description' => 'Deposit slip for reference: ' . $ref_no
                    ]);
                }
                if (!empty($media_objects)) {
                    $deposit->media()->saveMany($media_objects);
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'msg' => 'Deposit created successfully with reference: ' . $ref_no]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function uploadDepositSlip(Request $request)
    {
        if (!auth()->user()->can('cash_deposit.create') && !auth()->user()->can('cash_deposit.edit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if ($request->hasFile('file')) {
                $uploaded_file = Media::uploadFile($request->file('file'));
                if (!empty($uploaded_file)) {
                    return response()->json(['success' => true, 'file_name' => $uploaded_file, 'msg' => 'File uploaded successfully']);
                }
            }
            return response()->json(['success' => false, 'msg' => 'Upload failed'], 400);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get deposit history for DataTables
     */
    public function getDepositHistory(Request $request)
    {
        if (!$request->ajax()) return response()->json(['error' => 'Invalid request'], 400);

        if (!auth()->user()->can('cash_deposit.view')) {
            return response()->json(['data' => []]); 
        }

        $business_id = $request->session()->get('user.business_id');

        $query = DepositHistory::with(['bank_account', 'media'])
            ->where('business_id', $business_id)
            ->select(['id', 'ref_no', 'deposit_datetime', 'bank_account_id', 'amount', 'status', 'transaction_payment_ids', 'created_at']);

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $query->whereBetween(DB::raw('date(deposit_datetime)'), [$request->start_date, $request->end_date]);
        }

        return Datatables::of($query)
            ->addColumn('action', function ($row) {
                $html = '';
                $can_edit = auth()->user()->can('cash_deposit.edit');
                $can_delete = auth()->user()->can('cash_deposit.delete');

                if ($can_edit || $can_delete) {
                    $html .= '<div class="btn-group">
                        <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">'.
                            __('messages.actions'). '<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                    
                    if ($can_edit) {
                        $html .= '<li><a href="#" data-href="' . action([\App\Http\Controllers\CashDepositController::class, 'edit'], [$row->id]) . '" class="edit_deposit_btn"><i class="glyphicon glyphicon-edit"></i> ' . __('messages.edit') . '</a></li>';
                    }
                    
                    if ($can_delete) {
                        $html .= '<li><a href="#" data-href="' . action([\App\Http\Controllers\CashDepositController::class, 'destroy'], [$row->id]) . '" class="delete_deposit_btn"><i class="glyphicon glyphicon-trash"></i> ' . __('messages.delete') . '</a></li>';
                    }
                    
                    $html .= '</ul></div>';
                }
                return $html;
            })
            // --- NEW COLUMN: Payment Date Button ---
            ->addColumn('payment_dates', function ($row) {
                $count = count($row->transaction_payment_ids ?? []);
                return '<button type="button" class="btn btn-xs btn-default view-payment-details" data-id="' . $row->id . '" title="View Payment Dates">
                            <i class="fa fa-calendar"></i> ' . $count . ' Payments
                        </button>';
            })
            ->addColumn('bank', function ($row) { return $row->bank_account ? $row->bank_account->name : 'N/A'; })
            ->addColumn('invoices', function ($row) {
                $payment_ids = $row->transaction_payment_ids;
                if (empty($payment_ids)) return '-';
                
                $invoices_data = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
                    ->whereIn('transaction_payments.id', $payment_ids)
                    ->select('t.invoice_no', 't.id as transaction_id')
                    ->get();

                if ($invoices_data->isEmpty()) return '-';

                $invoice_links = [];
                foreach($invoices_data as $inv) {
                    $invoice_links[] = '<button type="button" class="btn btn-link btn-modal" data-href="' . action([\App\Http\Controllers\SellController::class, 'show'], [$inv->transaction_id]) . '" data-container=".invoice_modal" style="padding:0; margin-right: 5px;">' . $inv->invoice_no . '</button>';
                }

                return implode(', ', $invoice_links);
            })
            ->editColumn('deposit_datetime', function ($row) { return $this->transactionUtil->format_date($row->deposit_datetime, true); })
            ->editColumn('amount', function ($row) { return '<span class="display_currency" data-currency_symbol="true">' . $this->transactionUtil->num_f($row->amount, true) . '</span>'; })
            ->addColumn('slip', function ($row) {
                if ($row->media && $row->media->count() > 0) {
                    return '<button type="button" class="btn btn-xs btn-info view-attachments" data-deposit-id="' . $row->id . '"><i class="fa fa-paperclip"></i> ' . $row->media->count() . '</button>';
                }
                return '-';
            })
            ->addColumn('status_label', function ($row) { return $row->status == 'posted' ? '<span class="label label-success">Posted</span>' : '<span class="label label-warning">Pending</span>'; })
            ->rawColumns(['action', 'payment_dates', 'invoices', 'amount', 'slip', 'status_label'])
            ->make(true);
    }

    public function getPaymentDetails(Request $request, $id)
    {
        if (!auth()->user()->can('cash_deposit.view')) {
            abort(403);
        }

        $business_id = $request->session()->get('user.business_id');
        $deposit = DepositHistory::where('business_id', $business_id)->findOrFail($id);
        
        $payment_ids = $deposit->transaction_payment_ids;

        if (empty($payment_ids)) {
            return response()->json(['data' => []]);
        }

        $payments = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->whereIn('transaction_payments.id', $payment_ids)
            ->select([
                'transaction_payments.paid_on',
                'transaction_payments.payment_ref_no',
                'transaction_payments.amount',
                't.invoice_no',
                'c.name as customer_name'
            ])
            ->orderBy('transaction_payments.paid_on', 'desc')
            ->get();

        $data = $payments->map(function($p) {
            return [
                'date' => $this->transactionUtil->format_date($p->paid_on, true),
                'ref_no' => $p->payment_ref_no,
                'invoice' => $p->invoice_no,
                'customer' => $p->customer_name,
                'amount' => '<span class="display_currency" data-currency_symbol="true">' . $this->transactionUtil->num_f($p->amount) . '</span>'
            ];
        });

        return response()->json(['data' => $data, 'deposit_ref' => $deposit->ref_no]);
    }

    public function searchPendingPayments(Request $request)
    {
        if (!auth()->user()->can('cash_deposit.create') && !auth()->user()->can('cash_deposit.edit')) {
             return response()->json([]);
        }

        $term = $request->input('q');
        $business_id = $request->session()->get('user.business_id');

        if (empty($term)) {
            return response()->json([]);
        }

        $query = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('transaction_payments.method', 'cash')
            ->where(function ($q) use ($term) {
                $q->where('transaction_payments.payment_ref_no', 'like', "%{$term}%")
                  ->orWhere('t.invoice_no', 'like', "%{$term}%")
                  ->orWhere('c.name', 'like', "%{$term}%");
            });

        $query->whereRaw("NOT EXISTS (
            SELECT 1 FROM deposit_history 
            WHERE JSON_CONTAINS(deposit_history.transaction_payment_ids, CONCAT('\"', transaction_payments.id, '\"')) 
            AND deposit_history.business_id = ? 
            AND deposit_history.deleted_at IS NULL
        )", [$business_id]);

        $payments = $query->select(
            'transaction_payments.id',
            'transaction_payments.amount',
            'transaction_payments.payment_ref_no',
            't.invoice_no',
            'c.name as customer_name'
        )->limit(20)->get();

        $results = [];
        foreach ($payments as $payment) {
            $results[] = [
                'id' => $payment->id,
                'text' => $payment->payment_ref_no . ' (' . $payment->invoice_no . ' - ' . $payment->customer_name . ')',
                'amount' => $payment->amount,
                'payment_ref_no' => $payment->payment_ref_no,
                'invoice_no' => $payment->invoice_no,
                'customer_name' => $payment->customer_name
            ];
        }

        return response()->json($results);
    }

    public function edit($id)
    {
        if (!request()->ajax() || !auth()->user()->can('cash_deposit.edit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $deposit = DepositHistory::with(['media'])->where('business_id', $business_id)->findOrFail($id);
        $bank_accounts = Account::where('business_id', $business_id)->pluck('name', 'id');

        $payment_ids = $deposit->transaction_payment_ids ?? [];
        $payments = [];
        if (!empty($payment_ids)) {
            $payments = TransactionPayment::join('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
                ->join('contacts as c', 't.contact_id', '=', 'c.id')
                ->whereIn('transaction_payments.id', $payment_ids)
                ->select(['transaction_payments.id as payment_id', 'transaction_payments.amount', 'transaction_payments.payment_ref_no', 't.invoice_no', 'c.name as customer_name'])
                ->get();
        }

        return view('cash_deposit.edit', compact('deposit', 'bank_accounts', 'payments'));
    }

    public function update(Request $request, $id)
    {
        if (!request()->ajax() || !auth()->user()->can('cash_deposit.edit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $deposit = DepositHistory::where('business_id', $business_id)->findOrFail($id);

            $input = $request->validate([
                'bank_account_id' => 'required|exists:accounts,id',
                'deposit_datetime' => 'required|date',
                'ref_no' => 'nullable|string',
                'payment_ids' => 'required|string'
            ]);

            DB::beginTransaction();

            $payment_ids_array = array_filter(explode(',', $input['payment_ids']));
            if (empty($payment_ids_array)) throw new \Exception('A deposit must have at least one invoice.');

            $existing_in_this_deposit = $deposit->transaction_payment_ids ?? [];
            
            foreach($payment_ids_array as $pid) {
                if (!in_array($pid, $existing_in_this_deposit)) {
                    $isDuplicate = DepositHistory::where('business_id', $business_id)
                        ->where('id', '!=', $id)
                        ->whereRaw("JSON_CONTAINS(transaction_payment_ids, '\"$pid\"')")
                        ->whereNull('deleted_at')
                        ->exists();
                    
                    if ($isDuplicate) {
                        $p = TransactionPayment::find($pid);
                        throw new \Exception('Payment Ref: ' . ($p ? $p->payment_ref_no : $pid) . ' is already in another deposit.');
                    }
                }
            }

            $new_total = TransactionPayment::whereIn('id', $payment_ids_array)->where('method', 'cash')->sum('amount');

            $deposit->update([
                'bank_account_id' => $input['bank_account_id'],
                'deposit_datetime' => $input['deposit_datetime'],
                'ref_no' => $input['ref_no'],
                'transaction_payment_ids' => $payment_ids_array,
                'amount' => $new_total
            ]);

            if (!empty($request->input('deposit_slip_names'))) {
                $file_names = explode(',', $request->input('deposit_slip_names'));
                $media_objects = [];
                foreach ($file_names as $file_name) {
                    $media_objects[] = new Media([
                        'business_id' => $business_id, 'file_name' => trim($file_name), 'uploaded_by' => request()->session()->get('user.id'),
                        'model_media_type' => 'deposit_slip', 'description' => 'Deposit slip for reference: ' . $deposit->ref_no
                    ]);
                }
                if (!empty($media_objects)) $deposit->media()->saveMany($media_objects);
            }

            DB::commit();
            return response()->json(['success' => true, 'msg' => __('messages.updated_success')]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        if (!request()->ajax() || !auth()->user()->can('cash_deposit.delete')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $deposit = DepositHistory::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);
            $deposit->delete();
            return response()->json(['success' => true, 'msg' => __('messages.deleted_success')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function deleteDepositMedia($id)
    {
        if (!auth()->user()->can('cash_deposit.edit')) {
            return response()->json(['success' => false, 'msg' => 'Unauthorized']);
        }
        try {
            Media::deleteMedia(request()->session()->get('user.business_id'), $id);
            return response()->json(['success' => true, 'msg' => __('lang_v1.file_deleted_successfully')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function getDepositAttachments(Request $request, $id)
    {
        if (!auth()->user()->can('cash_deposit.view')) {
            abort(403);
        }

        $deposit = DepositHistory::with('media')->where('business_id', $request->session()->get('user.business_id'))->where('id', $id)->first();
        if (!$deposit) return response()->json(['success' => false], 404);
        
        $attachments = $deposit->media->map(function($m) {
            return [
                'id' => $m->id, 'file_name' => $m->file_name, 'display_name' => $m->display_name ?? $m->file_name,
                'url' => $m->display_url ?? url('uploads/documents/' . $m->file_name),
                'is_image' => in_array(strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']),
                'description' => $m->description
            ];
        });
        return response()->json(['success' => true, 'attachments' => $attachments, 'ref_no' => $deposit->ref_no]);
    }
}