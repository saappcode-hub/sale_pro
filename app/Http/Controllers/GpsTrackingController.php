<?php

namespace App\Http\Controllers;

use App\GpsTrip;
use App\GpsPoint;
use App\TransactionVisit;
use App\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class GpsTrackingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getDataForTable(Request $request)
    {
        $business_id = auth()->user()->business_id;

        $query = GpsPoint::query()
            ->select([
                'gps_points.id',
                'gps_points.gps_time as time',
                'gps_points.location',
                'gps_points.trip_id',
                'users.username',
                'gps_trips.trip_date'
            ])
            ->join('users', 'gps_points.user_id', '=', 'users.id')
            ->join('gps_trips', 'gps_points.trip_id', '=', 'gps_trips.id')
            ->where('gps_trips.business_id', $business_id);

        $this->applyFilters($query, $request);

        return $query->orderBy('gps_points.gps_time', 'asc');
    }

    private function applyFilters($query, Request $request)
    {
        $business_id = auth()->user()->business_id;
        
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        // LOG FOR DEBUG
        \Log::info("GPS FILTER:", [
            'user' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        if ($userId && $userId !== '' && $userId !== 'all') {
            $query->where('gps_points.user_id', $userId);
        }

        // ⭐ FIXED DATE FILTER (REAL DATETIME BETWEEN)
        if ($startDate && $endDate) {
            $query->whereBetween('gps_points.gps_time', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        // SEARCH
        $searchValue = $request->input('search.value');
        if (!empty($searchValue)) {
            $searchValue = strtolower($searchValue);
            $query->where(function ($q) use ($searchValue) {
                $q->whereRaw('LOWER(gps_points.gps_time) LIKE ?', ["%{$searchValue}%"])
                  ->orWhereRaw('LOWER(gps_points.location) LIKE ?', ["%{$searchValue}%"])
                  ->orWhereRaw('LOWER(users.username) LIKE ?', ["%{$searchValue}%"]);
            });
        }
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $business_id = auth()->user()->business_id;

            // --- 1. REQUEST FOR MAP (GPS Route) ---
            // RESTORED ORIGINAL QUERY LOGIC WITH GPS_TRIPS JOIN
            if ($request->has('get_map_data')) {
                $query = GpsPoint::query()
                    ->select([
                        'gps_points.gps_time as time',
                        'gps_points.location',
                        'users.username',
                        'gps_points.trip_id'
                    ])
                    ->join('users', 'gps_points.user_id', '=', 'users.id')
                    ->join('gps_trips', 'gps_points.trip_id', '=', 'gps_trips.id') // <--- RESTORED THIS JOIN
                    ->where('gps_trips.business_id', $business_id);                 // <--- CHECK BUSINESS ID ON TRIP

                // Apply Filters (Same as original)
                if ($request->user_id && $request->user_id != 'all') {
                    $query->where('gps_points.user_id', $request->user_id);
                }
                if ($request->start_date && $request->end_date) {
                    $query->whereBetween('gps_points.gps_time', [
                        $request->start_date . ' 00:00:00',
                        $request->end_date . ' 23:59:59'
                    ]);
                }

                // Return clean JSON for the map
                return response()->json(['data' => $query->orderBy('gps_points.gps_time', 'asc')->get()]);
            }

            // --- 2. REQUEST FOR TABLE (Transaction Visits) ---
            $visitQuery = TransactionVisit::query()
                ->select([
                    'transactions_visit.transaction_date as date',
                    'contacts.name as contact_name',
                    'users.username',
                    'transactions_visit.visit_status',
                    'transactions_visit.contact_id' // <--- ADD THIS LINE
                ])
                ->leftJoin('contacts', 'transactions_visit.contact_id', '=', 'contacts.id')
                ->leftJoin('users', 'transactions_visit.create_by', '=', 'users.id')
                ->where('transactions_visit.business_id', $business_id);

            // Apply Filters for Table
            if ($request->user_id && $request->user_id != 'all') {
                $visitQuery->where('transactions_visit.create_by', $request->user_id);
            }
            if ($request->start_date && $request->end_date) {
                $visitQuery->whereBetween('transactions_visit.transaction_date', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            return DataTables::of($visitQuery)
                ->editColumn('date', function ($row) {
                    return $row->date; 
                })
                ->editColumn('contact_name', function ($row) {
                    return $row->contact_name ?? 'N/A';
                })
                ->make(true);
        }

        // --- 3. PAGE VIEW ---
        $business_id = auth()->user()->business_id;
        $usersList = User::where('business_id', $business_id)
            ->where('user_type', 'user')
            ->select('id', 'username', 'first_name', 'last_name')
            ->orderBy('username')
            ->get();

        $users = [];
        foreach ($usersList as $user) {
            $users[$user->id] = $user->first_name . ' ' . $user->last_name . ' (' . $user->username . ')';
        }

        return view('gps_tracking.index', compact('users'));
    }

    public function show($id)
    {
        $business_id = auth()->user()->business_id;

        $trip = GpsTrip::where('id', $id)
            ->where('business_id', $business_id)
            ->with(['gpsPoints', 'user'])
            ->firstOrFail();

        $gpsPoints = GpsPoint::where('trip_id', $id)
            ->orderBy('gps_time', 'asc')
            ->get()
            ->map(function ($point) {
                $coords = explode(',', $point->location);
                return [
                    'latitude' => (float) trim($coords[0]),
                    'longitude' => (float) trim($coords[1]),
                    'time' => $point->gps_time,
                ];
            });

        return view('gps_tracking.show', compact('trip', 'gpsPoints'));
    }

    public function getShowData($trip_id, Request $request)
    {
        if ($request->ajax()) {
            $business_id = auth()->user()->business_id;

            $tripExists = GpsTrip::where('id', $trip_id)
                ->where('business_id', $business_id)
                ->exists();

            if (!$tripExists) {
                return response()->json(['data' => []], 403);
            }

            $query = GpsPoint::where('trip_id', $trip_id)
                ->orderBy('gps_time', 'asc');

            return DataTables::of($query)
                ->editColumn('gps_time', function ($row) {
                    return $row->gps_time ? date('H:i:s', strtotime($row->gps_time)) : '';
                })
                ->addColumn('latitude', function ($row) {
                    $coords = explode(',', $row->location);
                    return trim($coords[0] ?? '');
                })
                ->addColumn('longitude', function ($row) {
                    $coords = explode(',', $row->location);
                    return trim($coords[1] ?? '');
                })
                ->addColumn('location_string', function ($row) {
                    return $row->location ?? '';
                })
                ->addColumn('action', function ($row) {
                    $coords = explode(',', $row->location);
                    $latitude = trim($coords[0] ?? '');
                    $longitude = trim($coords[1] ?? '');
                    $time = date('H:i:s', strtotime($row->gps_time));

                    return '
                        <button class="btn btn-xs btn-info view-point-on-map" 
                                data-lat="' . $latitude . '" 
                                data-lng="' . $longitude . '"
                                data-time="' . $time . '">
                            <i class="fa fa-map-marker"></i> Show
                        </button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return response()->json(['data' => []]);
    }

    public function getVisitHistory(Request $request)
    {
        $userId    = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $business_id = auth()->user()->business_id;

        $query = TransactionVisit::with(['contact.contactMaps'])
                ->where('business_id', $business_id);

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
                'visit_status'=> ucfirst($visit->visit_status),
                'date'        => $visit->transaction_date,
                'contact_name'=> optional($visit->contact)->name,
                'contact_id'  => optional($visit->contact)->id
            ];
        }

        // Overall totals
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
}
