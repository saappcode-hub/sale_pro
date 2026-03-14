<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\CambodiaCommune;
use App\CambodiaDistrict;
use App\CambodiaProvince;
use App\Contact;
use App\Transaction;
use App\TransactionVisit;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Yajra\DataTables\Facades\DataTables;

class SalesOrderVisitController extends Controller
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

    $query = TransactionVisit::query()
        ->select([
            'transactions_visit.id',
            'transactions_visit.transaction_date as date',
            'transactions_visit.visit_no',
            'transactions_visit.contact_id',
            'transactions_visit.location_id',
            'transactions_visit.create_by',
            'transactions_visit.own_product',
            'transactions_visit.other_product',
            'transactions_visit.visit_status',
            'transactions_visit.checkin_distance',
            'transactions_visit.sale_rep', // ADD THIS LINE
            'contacts.contact_id as contact_contact_id',
            'contacts.name as contact_name',
            'contacts.mobile as contact_mobile',
            'business_locations.name as location_name',
            'users.username as username',
            'cambodia_provinces.name_en as province_name',
            'cambodia_districts.name_en as district_name',
            'cambodia_communes.name_en as commune_name',
        ])
        ->leftJoin('contacts', 'transactions_visit.contact_id', '=', 'contacts.id')
        ->leftJoin('contacts_map', 'contacts.id', '=', 'contacts_map.contact_id')
        ->leftJoin('business_locations', 'transactions_visit.location_id', '=', 'business_locations.id')
        ->leftJoin('users', 'transactions_visit.create_by', '=', 'users.id')
        ->leftJoin('cambodia_provinces', 'contacts_map.province_id', '=', 'cambodia_provinces.id')
        ->leftJoin('cambodia_districts', 'contacts_map.district_id', '=', 'cambodia_districts.id')
        ->leftJoin('cambodia_communes', 'contacts_map.commune_id', '=', 'cambodia_communes.id')
        ->where('transactions_visit.business_id', $business_id);

    $this->applyFilters($query, $request);

    return $query->orderBy('transactions_visit.transaction_date', 'desc');
}

private function applyFilters($query, Request $request)
{
    $filters = [
        'location_id' => 'transactions_visit.location_id',
        'contact_id' => 'transactions_visit.contact_id',
        'user_id' => 'transactions_visit.create_by',
        'province_id' => 'contacts_map.province_id',
        'district_id' => 'contacts_map.district_id',
        'commune_id' => 'contacts_map.commune_id',
    ];

    foreach ($filters as $requestKey => $column) {
        $value = $request->input($requestKey);
        if ($value && $value !== 'all') {
            $query->where($column, $value);
        }
    }

    $start = $request->input('start_date');
    $end = $request->input('end_date');
    if ($start && $end) {
        $query->whereBetween('transactions_visit.transaction_date', [$start . ' 00:00:00', $end . ' 23:59:59']);
    }

    // Handle DataTables search
    $searchValue = $request->input('search.value');
    if (!empty($searchValue)) {
        $searchValue = strtolower($searchValue);
        $query->where(function ($q) use ($searchValue) {
            $q->whereRaw('LOWER(transactions_visit.transaction_date) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.visit_no) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(contacts.name) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(contacts.mobile) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(business_locations.name) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(users.username) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.sale_rep) LIKE ?', ["%{$searchValue}%"]) // ADD THIS LINE
              ->orWhereRaw('LOWER(cambodia_provinces.name_en) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(cambodia_districts.name_en) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(cambodia_communes.name_en) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.own_product) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.other_product) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.visit_status) LIKE ?', ["%{$searchValue}%"])
              ->orWhereRaw('LOWER(transactions_visit.checkin_distance) LIKE ?', ["%{$searchValue}%"]);
        });
    }
}

public function index(Request $request)
{
    if ($request->ajax()) {
        $query = $this->getDataForTable($request);

        return DataTables::of($query)
            ->addColumn('action', function ($row) {
                return '<div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                            data-toggle="dropdown" aria-expanded="false">'.
                            __('messages.actions').
                            '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">
                        <li><a href="#" data-href="'.action([self::class, 'show'], [$row->id]).'" 
                               class="btn-modal" data-container=".view_modal">
                               <i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'
                        </a></li>
                    </ul>
                </div>';
            })
            ->editColumn('date', '{{ $date ? date("d-m-Y H:i", strtotime($date)) : "" }}')
            ->editColumn('visit_no', '{{ $contact_contact_id ?? "" }}')
            ->editColumn('contact_name', '{{ $contact_name ?? "" }}')
            ->editColumn('contact_mobile', '{{ $contact_mobile ?? "" }}')
            ->editColumn('location_name', '{{ $location_name ?? "" }}')
            ->editColumn('username', '{{ $username ?? "" }}')
            ->editColumn('sale_rep', '{{ $sale_rep ?? "" }}') // ADD THIS LINE
            ->editColumn('province_name', '{{ $province_name ?? "" }}')
            ->editColumn('district_name', '{{ $district_name ?? "" }}')
            ->editColumn('commune_name', '{{ $commune_name ?? "" }}')
            ->editColumn('own_product', function ($row) {
                return $this->formatProductWithColor($row->own_product ?? '');
            })
            ->editColumn('other_product', function ($row) {
                return $this->formatProductWithColor($row->other_product ?? '');
            })
            ->editColumn('visit_status', '{{ ucfirst($visit_status ?? "") }}')
            ->editColumn('checkin_distance', function ($row) {
                return $this->formatDistand($row->checkin_distance ?? 0, $row->visit_status ?? '');
            })
            ->rawColumns(['action', 'own_product', 'other_product', 'checkin_distance'])
            ->make(true);
    }

    $business_id = $request->session()->get('user.business_id');

    $business_locations = Cache::remember("business_locations_{$business_id}", 3600, function () use ($business_id) {
        return BusinessLocation::where('business_id', $business_id)
            ->pluck('name', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    $contact = Cache::remember("contacts_{$business_id}", 3600, function () use ($business_id) {
        return Contact::where('business_id', $business_id)
            ->pluck('name', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    $users = Cache::remember("users_{$business_id}", 3600, function () use ($business_id) {
        return User::where('business_id', $business_id)
            ->pluck('username', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    $cambodia_province = Cache::remember('cambodia_provinces', 3600, function () {
        return CambodiaProvince::pluck('name_en', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    $cambodia_district = Cache::remember('cambodia_districts', 3600, function () {
        return CambodiaDistrict::pluck('name_en', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    $cambodia_commune = Cache::remember('cambodia_communes', 3600, function () {
        return CambodiaCommune::pluck('name_en', 'id')
            ->prepend(__('lang_v1.all'), '');
    });

    return view('sales_visit.index', compact(
        'business_locations',
        'contact',
        'users',
        'cambodia_province',
        'cambodia_district',
        'cambodia_commune'
    ));
}

    protected function formatProductWithColor($productData)
    {
        $percentage = $this->getPercentage($productData);
        if ($percentage !== null) {
            $color = $percentage >= 50 ? 'green' : 'red';
            return "<span style='color: $color;'>$productData</span>";
        }
        return $productData;
    }

    protected function getPercentage($productData)
    {
        if (preg_match('/\((\d+(\.\d+)?)%\)/', $productData, $matches)) {
            return (float)$matches[1];
        }
        return null;
    }

    protected function formatDistand($productDistance, $visit_status)
    {
        if ($productDistance !== null) {
            $color = $visit_status == 'completed' ? 'green' : 'red';
            return "<span style='color: $color;'>$productDistance</span>";
        }
        return $productDistance;
    }

    public function show($id)
    {
        $visit = TransactionVisit::with([
            'contact',
            'contact.contactMaps',
            'location',
            'sales_person',
            'TransactionSellLineVisit.product',
            'TransactionSellLineVisitImage'
        ])->findOrFail($id);
    
        // Categorize products into own and other based on kind_product
        $own_products = [];
        $other_products = [];
    
        foreach ($visit->TransactionSellLineVisit as $product) {
            // Append product details including the product name
            $productDetails = [
                'name' => $product->product ? $product->product->name : 'Product not found',
                'quantity' => $product->quantity
            ];
    
            if ($product->kind_product == 0) {
                $own_products[] = $productDetails;
            } elseif ($product->kind_product == 1) {
                $other_products[] = $productDetails;
            }
        }

        $images = [];
        if ($visit->TransactionSellLineVisitImage) {
            foreach ($visit->TransactionSellLineVisitImage as $image) {
                if (isset($image->image)) {
                    // Build S3 URL for sell-visit images
                    $s3ImageUrl = "https://piik-data.sgp1.digitaloceanspaces.com/piik-data/sell-visit/images/" . $image->image;
                    $images[] = $s3ImageUrl;
                }
            }
        }

    
        return view('sales_visit.show', compact('visit', 'own_products', 'other_products','images'));
    }

    public function getVisitHistory(Request $request)
    {
        $userId    = $request->input('user_id');
        $startDate = $request->input('start_date');  // e.g. "2025-02-01"
        $endDate   = $request->input('end_date');    // e.g. "2025-02-28"
        $business_id = $request->session()->get('user.business_id');

        $query = TransactionVisit::with(['contact.contactMaps'])
                ->where('business_id', $business_id); // Added mandatory business_id filter

        // Filter by user
        if (!empty($userId)) {
            $query->where('create_by', $userId);
        }

        // Date range with full day
        if (!empty($startDate) && !empty($endDate)) {
            $startOfDay = $startDate . ' 00:00:01';
            $endOfDay   = $endDate   . ' 23:59:59';
            $query->whereBetween('transaction_date', [$startOfDay, $endOfDay]);
        }

        $visits = $query->get();
    
        // Build data array
        $data = [];
        foreach ($visits as $visit) {
            // If you store lat/long in contactMap->points like "(11.566...,104.874...)"
            // parse them if needed. Otherwise, if you have direct columns, adjust here.
            $points = optional($visit->contact->contactMaps)->points;

            $latitude = null;
            $longitude = null;
            if (!empty($points)) {
                $clean = str_replace(['(', ')'], '', $points);
                $coords = explode(',', $clean);
                if (count($coords) === 2) {
                    $latitude = trim($coords[0]);
                    $longitude = trim($coords[1]);
                }
            }

            $data[] = [
                'id'          => $visit->id,
                'latitude'    => $latitude,
                'longitude'   => $longitude,
                'visit_status'=> ucfirst($visit->visit_status),  // "Completed" / "Missed"
                'date'        => $visit->transaction_date,
                'contact_name'=> optional($visit->contact)->name,
                'contact_id'  => optional($visit->contact)->id
            ];
        }

        // Overall totals (for the top summary)
        $totalVisits     = $visits->count();
        $completedVisits = $visits->where('visit_status', 'completed')->count();
        $missedVisits    = $visits->where('visit_status', 'missed')->count();

        return response()->json([
            'data'             => $data,
            'total_visits'     => $totalVisits,
            'completed_visits' => $completedVisits,
            'missed_visits'    => $missedVisits
        ]);
    }

   public function dailySummary(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $today = \Carbon\Carbon::today();
        $yesterday = \Carbon\Carbon::yesterday();

        // 1. Get Business Name
        $business = \App\Business::find($business_id);
        $business_name = $business ? $business->name : 'Unknown Business';

        // 2. Fetch Today's Visits
        $todays_visits = \App\TransactionVisit::leftJoin('users', 'transactions_visit.create_by', '=', 'users.id')
            ->where('transactions_visit.business_id', $business_id)
            ->whereDate('transactions_visit.transaction_date', $today)
            ->select('transactions_visit.*', 'users.username')
            ->get();

        $todays_visits_count = $todays_visits->count();

        // 3. Build Sales Rep Report
        $target_per_day = 25;
        $sales_report = [];
        $total_own = 0;
        $total_other = 0;

        foreach ($todays_visits as $visit) {
            $rep_id = $visit->create_by;
            $rep_name = $visit->sale_rep ?: ($visit->username ?: 'Unknown'); 

            if (!isset($sales_report[$rep_id])) {
                $sales_report[$rep_id] = [
                    'name' => $rep_name,
                    'target' => $target_per_day,
                    'qty_visit' => 0,
                    'own_product_qty' => 0,
                    'other_product_qty' => 0,
                ];
            }

            $sales_report[$rep_id]['qty_visit'] += 1;

            // Extract the integer quantity from strings like "45 (44.12%)"
            $own_qty = intval($visit->own_product ?? 0);
            $other_qty = intval($visit->other_product ?? 0);

            $sales_report[$rep_id]['own_product_qty'] += $own_qty;
            $sales_report[$rep_id]['other_product_qty'] += $other_qty;

            $total_own += $own_qty;
            $total_other += $other_qty;
        }

        // Calculate Percentages per Rep
        $total_target = count($sales_report) > 0 ? (count($sales_report) * $target_per_day) : $target_per_day;

        foreach ($sales_report as &$report) {
            $report['remaining'] = $report['qty_visit'] - $report['target'];
            $report['variance'] = ($report['target'] > 0) ? round(($report['qty_visit'] / $report['target']) * 100) : 0;
            
            $total_rep_products = $report['own_product_qty'] + $report['other_product_qty'];
            $report['own_pct'] = $total_rep_products > 0 ? round(($report['own_product_qty'] / $total_rep_products) * 100) : 0;
            $report['other_pct'] = $total_rep_products > 0 ? round(($report['other_product_qty'] / $total_rep_products) * 100) : 0;
        }
        
        // 4. Overall Totals for Top Cards and Charts
        $total_products_overall = $total_own + $total_other;
        $overall_own_pct = $total_products_overall > 0 ? round(($total_own / $total_products_overall) * 100) : 0;
        $overall_other_pct = $total_products_overall > 0 ? round(($total_other / $total_products_overall) * 100) : 0;

        $overall_variance = $total_target > 0 ? round(($todays_visits_count / $total_target) * 100) : 0;
        $overall_remaining = $todays_visits_count - $total_target;

        // 5. Fetch Yesterday's Visits for Comparison
        $yesterdays_visits = \App\TransactionVisit::where('business_id', $business_id)
            ->whereDate('transaction_date', $yesterday)
            ->select('create_by')
            ->get();
        
        $yesterdays_visits_count = $yesterdays_visits->count();
        $yesterday_unique_reps = $yesterdays_visits->groupBy('create_by')->count();
        $yesterday_target = $yesterday_unique_reps > 0 ? ($yesterday_unique_reps * $target_per_day) : $target_per_day;
        
        $yesterday_variance = $yesterday_target > 0 ? round(($yesterdays_visits_count / $yesterday_target) * 100) : 0;

        // Calculate Days-Over-Days Change (Difference in variance percentage)
        $dod_change = $overall_variance - $yesterday_variance;

        return view('sales_visit.daily_summary', compact(
            'today',
            'business_name',
            'todays_visits_count',
            'yesterdays_visits_count',
            'dod_change',
            'sales_report',
            'total_target',
            'overall_variance',
            'overall_remaining',
            'yesterday_target',
            'yesterday_variance',
            'overall_own_pct',
            'overall_other_pct'
        ));
    }

    public function sendToTelegram(Request $request)
    {
        try {
            // 1. Get current business_id from session
            $business_id = $request->session()->get('user.business_id');

            // 2. Look up ALL active chat_ids for this business
            //    Filter: only schedules that include "sales_visit" report type
            $schedules = \Illuminate\Support\Facades\DB::table('telegram_schedules')
                ->where('business_id', $business_id)
                ->where('is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('report_types')
                    ->orWhereRaw('JSON_CONTAINS(report_types, \'"sales_visit"\')');
                })
                ->get();

            // 3. Check if setup exists at all. If empty, return error.
            if ($schedules->isEmpty()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'This business is not yet set up with Telegram.'
                ]);
            }

            // 4. Get the base64 image string from the request
            $image_data = $request->input('image');
            $image_parts = explode(";base64,", $image_data);
            $image_base64 = base64_decode($image_parts[1]);

            // 5. Save it to a temporary file
            $fileName = 'daily_summary_' . time() . '.png';
            $tempPath = storage_path('app/public/' . $fileName);
            file_put_contents($tempPath, $image_base64);

            // 6. Prepare Telegram API details
            $botToken = '8737726993:AAEd8C5uWwHu5cYc8YVH4zfpUwUxSWaplSc';
            $caption = "📊 *Daily Sale Visit Summary*\nDate: " . \Carbon\Carbon::today()->format('Y-m-d');
            
            $successCount = 0;
            $errorMessages = [];

            // 7. Loop through EVERY group set up for this business and send the image
            foreach ($schedules as $schedule) {
                // Skip if chat_id is empty
                if (empty($schedule->chat_id)) continue;

                $response = \Illuminate\Support\Facades\Http::attach(
                    'photo', file_get_contents($tempPath), $fileName
                )->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                    'chat_id' => $schedule->chat_id,
                    'caption' => $caption,
                    'parse_mode' => 'Markdown'
                ]);

                if ($response->successful() && $response->json('ok')) {
                    $successCount++;
                } else {
                    $errorMessages[] = $response->json('description', 'Unknown error');
                }
            }

            // 8. Delete the temporary file
            unlink($tempPath);

            // 9. Return response based on how many groups were successfully sent
            if ($successCount > 0) {
                return response()->json([
                    'success' => true, 
                    'message' => "Summary sent to {$successCount} Telegram group(s) successfully!"
                ]);
            } else {
                return response()->json([
                    'success' => false, 
                    'message' => 'Telegram Error: ' . implode(' | ', $errorMessages)
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
