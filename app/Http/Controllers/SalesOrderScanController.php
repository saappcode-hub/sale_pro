<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\CambodiaCommune;
use App\CambodiaDistrict;
use App\CambodiaProvince;
use App\Contact;
use App\Transaction;
use App\TransactionScan;
use App\TransactionVisit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderScanController extends Controller
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

    private function getDataForTable(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->user_id;
        $status = $request->status;
        $start = $request->start_date;
        $end = $request->end_date;
    
        $query = TransactionScan::where('business_id', $business_id)
            ->with('sales_person');
    
        if ($user_id && $user_id != 'all') {
            $query->where('created_by', $user_id);
        }
    
        if (!empty($status) && $status != 'all') {
            $query->whereIn('status', explode(',', $status)); // Assuming status can be either 'completed' or 'partial'
        }
    
        if (!empty($start) && !empty($end)) {
            $query->whereDate('created_at', '>=', $start)
                  ->whereDate('created_at', '<=', $end);
        }
    
        return $query->select([
                'id',
                'created_at as created_at',
                'ref_no as ref_no',
                'sale_ref as sale_ref',
                'updated_at as updated_at',
                'created_by as created_by',
                'status as status'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = $this->getDataForTable($request);
            return Datatables::of($data)
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                    $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SalesOrderScanController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';

                    $html .= '</ul></div>';
                
                    return $html;
                })
                ->editColumn('created_at', function($row) {
                    return date('d-m-Y H:i', strtotime($row->created_at));
                })
                ->addColumn('ref_no', function($row) {
                    return $row->ref_no;
                })
                ->addColumn('sale_ref', function($row) {
                    return $row->sale_ref;
                })
                ->addColumn('updated_at', function($row) {
                    return $row->updated_at;
                })
                ->addColumn('username', function($row) {
                    return $row->sales_person ? $row->sales_person->username : '';
                })
                ->addColumn('status', function($row) {
                    return $row->status;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    
        $business_id = $request->session()->get('user.business_id');

        // Users
        $users = User::where('business_id', $business_id)->pluck('username', 'id');
        $users->prepend(__('lang_v1.all'), '');

        return view('sale_order_scan.index', compact('users'));
    }

    public function show($id)
    {
        $transactionScan = TransactionScan::with([
            'createdByUser',
            'updatedByUser',
            'TransactionSellLineScan.product'
        ])->findOrFail($id);
    
        return view('sale_order_scan.show', compact('transactionScan'));
    }
    
}
