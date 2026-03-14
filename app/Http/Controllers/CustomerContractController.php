<?php

namespace App\Http\Controllers;

use App\CustomerContract;
use App\TransactionSellLine;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;

class CustomerContractController extends Controller
{
    protected $moduleUtil;
    protected $transactionUtil;

    public function __construct(ModuleUtil $moduleUtil, TransactionUtil $transactionUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('customer.view') && !auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }

        return view('customer_contract.index');
    }

    /**
     * Get all contracts for DataTables
     */
    public function getAllContracts()
    {
        if (!auth()->user()->can('customer.view') && !auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Logic similar to ContactController but joining 'contacts' and removing specific contact_id filter
        $query = CustomerContract::join('contract_products', 'customer_contracts.id', '=', 'contract_products.contract_id')
            ->join('products', 'contract_products.product_id', '=', 'products.id')
            ->join('users', 'customer_contracts.created_by', '=', 'users.id')
            ->join('contacts', 'customer_contracts.contact_id', '=', 'contacts.id') // Join Contacts table
            ->where('customer_contracts.business_id', $business_id)
            ->whereNull('contract_products.parent_sell_line_id')
            ->select([
                'customer_contracts.id as contract_id',
                'customer_contracts.business_id',
                'customer_contracts.contact_id',
                'customer_contracts.reference_no',
                'customer_contracts.contract_name',
                'customer_contracts.start_date',
                'customer_contracts.end_date',
                'customer_contracts.total_contract_value',
                'products.name as product_name', // Product Target
                'contract_products.target_quantity',
                'contract_products.product_id',
                'users.first_name as added_by',
                'contacts.name as contact_name' // New Column
            ]);

        return DataTables::of($query)
            // No Action Column
            
            ->addColumn('period', function ($row) {
                $start = \Carbon\Carbon::parse($row->start_date)->format('Y-m-d');
                $end = !empty($row->end_date) ? \Carbon\Carbon::parse($row->end_date)->format('Y-m-d') : '...';
                return $start . ' <i class="fas fa-arrow-right" style="font-size:10px;"></i> ' . $end;
            })
            ->addColumn('progress', function ($row) {
                // Logic preserved from ContactController
                $target = $row->target_quantity;
                if ($target == 0) return '0/0 (0%)';

                $totalSold = TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $row->business_id)
                    ->where('transactions.contact_id', $row->contact_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.status', 'final')
                    ->whereBetween('transactions.transaction_date', [$row->start_date, $row->end_date])
                    ->where('transaction_sell_lines.product_id', $row->product_id)
                    ->whereNull('transaction_sell_lines.parent_sell_line_id')
                    ->sum('transaction_sell_lines.quantity');

                $percentage = ($totalSold / $target) * 100;
                
                return number_format($totalSold, 0) . '/' . number_format($target, 0) . ' (' . number_format($percentage, 0) . '%)';
            })
            ->addColumn('status', function ($row) {
                $today = \Carbon\Carbon::now()->format('Y-m-d');
                $startDate = \Carbon\Carbon::parse($row->start_date)->format('Y-m-d');
                $endDate = !empty($row->end_date) ? \Carbon\Carbon::parse($row->end_date)->format('Y-m-d') : null;
                
                if ($endDate && $endDate < $today) {
                    return '<span class="label label-danger">EXPIRED</span>';
                } elseif ($startDate > $today) {
                    return '<span class="label label-warning">PENDING</span>';
                } else {
                    return '<span class="label label-success">ACTIVE</span>';
                }
            })
            ->editColumn('total_contract_value', function ($row) {
                return '<span class="display_currency" data-currency_symbol=true>' . 
                $this->transactionUtil->num_f($row->total_contract_value, true) . '</span>';
            })
            ->rawColumns(['total_contract_value', 'status', 'period', 'progress'])
            ->make(true);
    }

    public function showRelatedSales($id)
    {
        if (!auth()->user()->can('customer.view') && !auth()->user()->can('sell.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // 1. Fetch Contract WITH 'media'
        $contract = \App\CustomerContract::with(['products', 'media']) // Added 'media' here
            ->where('business_id', $business_id)
            ->findOrFail($id);

        // 2. Status Logic
        $today = \Carbon\Carbon::now()->format('Y-m-d');
        $startDate = \Carbon\Carbon::parse($contract->start_date)->format('Y-m-d');
        $endDate = !empty($contract->end_date) ? \Carbon\Carbon::parse($contract->end_date)->format('Y-m-d') : null;

        if (!empty($endDate) && $endDate < $today) {
            $contract->status_label = 'Expired';
            $contract->status_class = 'label-danger';
        } elseif ($startDate > $today) {
            $contract->status_label = 'Pending';
            $contract->status_class = 'label-warning';
        } else {
            $contract->status_label = 'Active';
            $contract->status_class = 'label-success';
        }

        // 3. Get Related Sales
        $contractProductIds = $contract->products->pluck('product_id')->toArray();

        $relatedSales = \App\Transaction::join('transaction_sell_lines', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
            ->leftJoin('business_locations', 'transactions.location_id', '=', 'business_locations.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.contact_id', $contract->contact_id)
            ->where('transactions.type', 'sell')
            ->where('transactions.status', 'final')
            ->whereBetween('transactions.transaction_date', [$contract->start_date, $contract->end_date])
            ->whereIn('transaction_sell_lines.product_id', $contractProductIds)
            ->select(
                'transactions.id',
                'transactions.transaction_date',
                'transactions.invoice_no',
                'transactions.final_total',
                'business_locations.name as location_name'
            )
            ->distinct()
            ->orderBy('transactions.transaction_date', 'desc')
            ->get();

        return view('customer_contract.related_sales_modal', compact('contract', 'relatedSales'));
    }
}