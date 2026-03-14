<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\CashRingBalance;
use App\Product;
use App\RewardsExchange;
use App\RingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class RewardExchangeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getDataForTable(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        // Query the RewardsExchange table with necessary columns and join with the products table
        $query = RewardsExchange::where('rewards_exchange.business_id', $business_id)
            ->leftJoin('products as sale_products', 'rewards_exchange.product_for_sale', '=', 'sale_products.id')
            ->leftJoin('products as exchange_products', 'rewards_exchange.exchange_product', '=', 'exchange_products.id')
            ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
            ->select([
                'rewards_exchange.id',
                'rewards_exchange.business_id',
                'sale_products.name as product_for_sale',
                'exchange_products.name as exchange_product',
                'rewards_exchange.exchange_quantity',
                'rewards_exchange.amount',
                'receive_products.name as receive_product',
                'rewards_exchange.receive_quantity',
                'rewards_exchange.created_at'
            ]);

        return $query;
    }

    public function edit($id)
    {
        $business_id = session()->get('user.business_id');

        // Query to get the reward exchange along with product names for product_for_sale, exchange_product, and receive_product, and type
        $rewardExchange = RewardsExchange::where('rewards_exchange.id', $id)
            ->where('rewards_exchange.business_id', $business_id)
            ->leftJoin('products as sale_products', 'rewards_exchange.product_for_sale', '=', 'sale_products.id')
            ->leftJoin('products as exchange_products', 'rewards_exchange.exchange_product', '=', 'exchange_products.id')
            ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
            ->select([
                'rewards_exchange.id',
                'rewards_exchange.type', // Fetch the type (customers/suppliers)
                'sale_products.name as product_for_sale_name',
                'exchange_products.name as exchange_product_name',
                'rewards_exchange.exchange_quantity',
                'rewards_exchange.amount',
                'receive_products.name as receive_product_name',
                'rewards_exchange.receive_quantity',
                'rewards_exchange.product_for_sale',
                'rewards_exchange.exchange_product',
                'rewards_exchange.receive_product'
            ])
            ->firstOrFail();

        // Return the view with the reward exchange data
        return view('rewards_exchange.edit', compact('rewardExchange'));
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');
            $type = $request->get('type', 'customers'); // Default to 'customers' if not provided

            // Validate the 'type' parameter
            if (!in_array($type, ['customers', 'suppliers'])) {
                return response()->json(['error' => 'Invalid reward type specified.'], 400);
            }

            // Query the RewardsExchange table with necessary columns and filter by type
            $data = RewardsExchange::where('rewards_exchange.business_id', $business_id)
                ->where('rewards_exchange.type', $type) // Filter by type: 'customers' or 'suppliers'
                ->whereNull('rewards_exchange.deleted_at') // Check if deleted_at is NULL
                ->leftJoin('products as sale_products', 'rewards_exchange.product_for_sale', '=', 'sale_products.id')
                ->leftJoin('products as exchange_products', 'rewards_exchange.exchange_product', '=', 'exchange_products.id')
                ->leftJoin('products as receive_products', 'rewards_exchange.receive_product', '=', 'receive_products.id')
                ->select([
                    'rewards_exchange.id',
                    'rewards_exchange.business_id',
                    'rewards_exchange.type',
                    'sale_products.name as product_for_sale',
                    'exchange_products.name as exchange_product',
                    'rewards_exchange.exchange_quantity',
                    'rewards_exchange.amount',
                    'receive_products.name as receive_product',
                    'rewards_exchange.receive_quantity',
                    'rewards_exchange.created_at'
                ]);

                // Apply search filter for exchange_product
                if (!empty($request->search['value'])) {
                    $searchValue = $request->search['value'];
                    $data->where(function ($query) use ($searchValue) {
                        $query->where('exchange_products.name', 'LIKE', "%$searchValue%");
                    });
                }
                
            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $editUrl = route('rewards_exchange.edit', $row->id);
                    $csrfToken = csrf_token(); // Generate CSRF token

                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs"
                                        data-toggle="dropdown" aria-expanded="false">
                                        ' . __('messages.actions') . '
                                        <span class="caret"></span>
                                        <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    <li>
                                        <a href="' . $editUrl . '" class="edit-reward" data-id="' . $row->id . '">
                                            <i class="fa fa-edit"></i> ' . __('messages.edit') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="delete-reward-exchange" data-href="' . route('rewards_exchange.destroy', $row->id) . '" data-csrf="' . csrf_token() . '">
                                            <i class="fa fa-trash"></i> ' . __('messages.delete') . '
                                        </a>
                                    </li>
                                </ul>
                            </div>';
                })
                ->addColumn('product_for_sale', function ($row) {
                    return $row->product_for_sale;
                })
                ->addColumn('exchange_product', function ($row) {
                    return $row->exchange_product;
                })
                ->addColumn('exchange_quantity', function ($row) {
                    return $row->exchange_quantity;
                })
                ->addColumn('amount', function ($row) {
                    return $row->amount;
                })
                ->addColumn('receive_product', function ($row) {
                    return $row->receive_product;
                })
                ->addColumn('receive_quantity', function ($row) {
                    return $row->receive_quantity;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $business_id = $request->session()->get('user.business_id');
        $business = Business::find($business_id);

        return view('rewards_exchange.index', compact('business'));
    }

    public function saveExchangeRate(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            
            $request->validate([
                'exchange_rate' => 'required|numeric|min:0'
            ]);

            // Update the exchange_rate column in the business table
            Business::where('id', $business_id)->update([
                'exchange_rate' => $request->exchange_rate
            ]);

            return response()->json([
                'success' => true, 
                'msg' => 'Exchange rate updated successfully'
            ]);

        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'msg' => __('messages.something_went_wrong')
            ]);
        }
    }

    public function create(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $type = $request->get('type', 'customers'); // Get 'type' from request or default to 'customers'
        return view('rewards_exchange.create', compact('type'));
    }

    public function destroy($id, Request $request)
    {
        if ($request->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');

                // Find the reward exchange by ID and business ID
                $rewardExchange = RewardsExchange::where('id', $id)
                    ->where('business_id', $business_id)
                    ->firstOrFail();

                // Soft delete: Update the 'deleted_at' column with the current timestamp
                $rewardExchange->update([
                    'deleted_at' => now()
                ]);

                return response()->json(['success' => true, 'message' => 'Reward exchange deleted successfully.']);
            } catch (\Exception $e) {
                \Log::error('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
                return response()->json(['success' => false, 'message' => __('messages.something_went_wrong')]);
            }
        }
    }

    public function update(Request $request, $id)
    {
        // Get the business ID and user ID from the session
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id'); // Get user ID for potential tracking/logging if needed

        // Validate the 'type' parameter
        $request->validate([
            'type' => 'required|in:customers,suppliers',
            'exchange_product' => 'required|exists:products,id',
            'receive_product' => 'required|exists:products,id',
            'exchange_quantity' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:1',
            'receive_quantity' => 'required|integer|min:1',
        ]);

        // Find the reward exchange by ID and business ID
        $rewardExchange = RewardsExchange::where('id', $id)
            ->where('business_id', $business_id)
            ->firstOrFail();

        // Update the reward exchange record
        $rewardExchange->update([
            'product_for_sale' => $request->type === 'suppliers' ? null : $request->product_for_sale,
            'exchange_product' => $request->exchange_product,
            'exchange_quantity' => $request->exchange_quantity,
            'amount' => $request->amount,
            'receive_product' => $request->receive_product,
            'receive_quantity' => $request->receive_quantity,
            'type' => $request->type,
            'updated_at' => now(), // Update the timestamp
        ]);

        return redirect()->route('rewards_exchange.index', ['type' => $request->type])
            ->with('success', 'Reward exchange updated successfully.');
    }

    public function searchProduct(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $query = trim($request->get('query'));

        // Use case-insensitive matching for name and SKU
        $products = Product::where('business_id', $business_id)
            ->where(function ($q) use ($query) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->get(['id', 'name', 'sku']);

        return response()->json($products);
    }

    public function checkProduct(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $request->product_id;

        $exists = RewardsExchange::where('business_id', $business_id)
            ->where('product_for_sale', $product_id)
            ->whereNull('deleted_at')
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    public function store(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $request->validate([
            'type' => 'required|in:customers,suppliers',
            'exchange_product' => 'required|exists:products,id',
            'receive_product' => 'required|exists:products,id',
            'exchange_quantity' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:1',
            'receive_quantity' => 'required|integer|min:1',
        ]);

        $productForSale = $request->type === 'suppliers' ? null : $request->product_for_sale;

        RewardsExchange::create([
            'business_id' => $business_id,
            'product_for_sale' => $productForSale,
            'exchange_product' => $request->exchange_product,
            'exchange_quantity' => $request->exchange_quantity,
            'amount' => $request->amount,
            'receive_product' => $request->receive_product,
            'receive_quantity' => $request->receive_quantity,
            'type' => $request->type,
            'created_by' => $user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Redirect to the correct tab based on the 'type'
        return redirect()->route('rewards_exchange.index', ['type' => $request->type])
            ->with('success', 'Reward exchange created successfully.');
    }

    public function ringUnits(Request $request)
    {
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');

            // Fetch RingUnit records grouped by product_id, ordered by id in descending order
            $ringUnits = RingUnit::where('ring_unit.business_id', $business_id)
                ->with('product') // Eager load the product relationship
                ->orderBy('id', 'desc') // Order by id in descending order
                ->get()
                ->groupBy('product_id'); // Group by product_id in PHP

            // Transform the data to match the expected format
            $data = $ringUnits->map(function ($group, $product_id) {
                $firstRingUnit = $group->first(); // Get the first record for the ID and product name
                $values = $group->pluck('value')->sort()->implode(','); // Sort values and concatenate with a comma

                // Check if the product_id exists in transaction_sell_ring_balance
                $hasTransactions = DB::table('transaction_sell_ring_balance')
                    ->where('product_id', $product_id)
                    ->exists();

                return [
                    'id' => $firstRingUnit->id, // Use the first record's ID
                    'ring_name' => $firstRingUnit->product ? $firstRingUnit->product->name : 'N/A', // Product name
                    'values' => $values, // Concatenated values
                    'can_delete' => !$hasTransactions, // Flag to determine if delete button should be shown
                ];
            })->sortByDesc('id')->values(); // Sort by id in descending order, reset keys

            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $editUrl = route('ring-units.edit', $row['id']);
                    $deleteUrl = route('ring-units.destroy', $row['id']);
                    
                    // Start with the edit button
                    $actionButtons = '<div class="btn-group">
                                        <button type="button" class="btn btn-info dropdown-toggle btn-xs"
                                                data-toggle="dropdown" aria-expanded="false">
                                                ' . __('messages.actions') . '
                                                <span class="caret"></span>
                                                <span class="sr-only">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                            <li>
                                                <a href="' . $editUrl . '" class="edit-ring-unit" data-id="' . $row['id'] . '">
                                                    <i class="fa fa-edit"></i> ' . __('messages.edit') . '
                                                </a>
                                            </li>';

                    // Only add the delete button if can_delete is true
                    if ($row['can_delete']) {
                        $actionButtons .= '<li>
                                            <a href="#" class="delete-ring-unit" data-href="' . $deleteUrl . '">
                                                <i class="fa fa-trash"></i> ' . __('messages.delete') . '
                                            </a>
                                        </li>';
                    }

                    $actionButtons .= '</ul></div>';

                    return $actionButtons;
                })
                ->addColumn('ring_name', function ($row) {
                    return $row['ring_name'];
                })
                ->addColumn('values', function ($row) {
                    return $row['values'];
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('rewards_exchange.index');
    }

    public function destroyRingUnits($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');

            // Fetch the RingUnit record to get the product_id
            $ringUnit = RingUnit::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();

            $product_id = $ringUnit->product_id;

            // Check if the product_id exists in transaction_sell_ring_balance
            $hasTransactions = DB::table('transaction_sell_ring_balance')
                ->where('product_id', $product_id)
                ->exists();

            if ($hasTransactions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete this ring unit because it is associated with transactions.'
                ], 403);
            }

            // Delete all RingUnit records for the product_id
            RingUnit::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ring unit deleted successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the ring unit.'
            ], 500);
        }
    }

    public function editRingUnits($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
    
            // Fetch the ring unit for the given ID
            $ringUnits = RingUnit::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();
    
            $product_id = $ringUnits->product_id;
    
            // Fetch all ring units for the product
            $ringUnitValues = RingUnit::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->select('id', 'value')
                ->get()
                ->toArray();
    
            // Check if each ring unit is used in transaction_sell_ring_balance_ring_units
            foreach ($ringUnitValues as &$ringUnit) {
                $isUsed = DB::table('transaction_sell_ring_balance_ring_units')
                    ->where('ring_units_id', $ringUnit['id'])
                    ->exists();
    
                // Add a flag to indicate if the ring unit value should be read-only
                $ringUnit['is_readonly'] = $isUsed || $ringUnit['value'] == 1; // Also make value = 1 read-only as per existing logic
            }
            unset($ringUnit); // Unset the reference to avoid issues
    
            // Fetch the product details
            $product = Product::where('id', $product_id)
                ->select('id', 'name', 'sku')
                ->first();
    
            $ringUnitData = [
                'id' => $ringUnits->id, // The ID of the first ring unit (for the form action)
                'product_id' => $product_id,
                'ring_name' => $product->name . ' (' . $product->sku . ')',
                'ring_values' => $ringUnitValues, // Array of ring units with id, value, and is_readonly flag
            ];
    
            return view('rewards_exchange.add_ring_units', compact('ringUnitData'));
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return back()->with('status', [
                'success' => 0,
                'msg' => __('messages.something_went_wrong')
            ]);
        }
    }

    public function updateRingUnits($id, Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'ring_name' => 'required|exists:products,id',
                'ring_values' => 'required|array',
                'ring_values.*' => 'required|numeric|min:0',
                'ring_unit_ids' => 'required|array',
                'ring_unit_ids.*' => 'nullable|exists:ring_unit,id',
            ]);

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');
            $input = $request->only(['ring_name', 'ring_values', 'ring_unit_ids']);

            // Begin a transaction to ensure data consistency
            \DB::beginTransaction();

            // Fetch the first ring unit to determine the product_id
            $ringUnit = RingUnit::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();

            $product_id = $ringUnit->product_id;

            // Fetch existing ring units for this product
            $existingRingUnits = RingUnit::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->get()
                ->keyBy('id');

            // Current datetime for updated_at and new created_at
            $now = now();

            // Process each submitted ring value
            foreach ($input['ring_values'] as $index => $value) {
                $ringUnitId = $input['ring_unit_ids'][$index];

                if ($ringUnitId) {
                    // Update existing ring unit
                    $existingRingUnit = $existingRingUnits[$ringUnitId] ?? null;
                    if ($existingRingUnit && $existingRingUnit->value != $value) {
                        $existingRingUnit->update([
                            'value' => $value,
                            'updated_at' => $now
                        ]);
                    }
                } else {
                    // Create new ring unit
                    RingUnit::create([
                        'business_id' => $business_id,
                        'product_id' => $input['ring_name'],
                        'value' => $value,
                        'created_by' => $user_id,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }
            }

            // Commit the transaction
            \DB::commit();

            // Set success message in session and redirect
            $output = [
                'success' => 1,
                'msg' => __('Ring units updated successfully!')
            ];

            return redirect()->route('rewards_exchange.index', ['type' => 'ring_units'])
                ->with('status', $output);

        } catch (\Exception $e) {
            // Rollback the transaction on error
            \DB::rollBack();

            // Log the error for debugging
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            // Set failure message in session and redirect
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong')
            ];

            return redirect()->route('rewards_exchange.index', ['type' => 'ring_units'])
                ->with('status', $output);
        }
    }
    
    public function createRingUnits(Request $request)
    {
        return view('rewards_exchange.add_ring_units');
    }

    public function searchProductRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $query = trim($request->get('q')); // Trim whitespace from the query
    
        if (empty($query)) {
            return response()->json([]); // Return empty array if query is empty
        }
    
        $products = Product::where('business_id', $business_id)
            ->where('not_for_selling', 1)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->select('id', 'name', 'sku')
            ->limit(10)
            ->get();
    
        return response()->json($products);
    }

    public function searchProductRingCash(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $query = trim($request->get('q'));

        if (empty($query)) {
            return response()->json([]);
        }

        $products = Product::where('business_id', $business_id)
            ->where('not_for_selling', 1)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('sku', 'LIKE', "%{$query}%");
            })
            ->whereNotExists(function ($subQuery) use ($business_id) {
                $subQuery->select(DB::raw(1))
                        ->from('cash_ring_balance')
                        ->whereColumn('cash_ring_balance.product_id', 'products.id')
                        ->where('cash_ring_balance.business_id', $business_id);
            })
            ->select('id', 'name', 'sku')
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    public function checkProductRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $product_id = $request->query('product_id');

        $exists = RingUnit::where('business_id', $business_id)
            ->where('product_id', $product_id)
            ->exists();

        return response()->json([
            'exists' => $exists
        ]);
    }

    public function storeRingUnits(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'ring_name' => 'required|exists:products,id',
                'ring_values' => 'required|array',
                'ring_values.*' => 'required|numeric|min:0',
            ]);

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');
            $input = $request->only(['ring_name', 'ring_values']);

            // Check if a RingUnit record already exists for this product_id and business_id
            $existingRingUnit = RingUnit::where('business_id', $business_id)
                ->where('product_id', $input['ring_name'])
                ->exists();

            if ($existingRingUnit) {
                $output = [
                    'success' => 0,
                    'msg' => __('A Ring Unit for this product already exists!')
                ];

                return redirect()->route('rewards_exchange.index', ['type' => 'ring_units'])
                    ->with('status', $output);
            }

            // Begin a transaction to ensure data consistency
            \DB::beginTransaction();

            // Current datetime for created_at and updated_at
            $now = now();

            // Save each ring value as a separate RingUnit record
            foreach ($input['ring_values'] as $value) {
                RingUnit::create([
                    'business_id' => $business_id,
                    'product_id' => $input['ring_name'],
                    'value' => $value,
                    'created_by' => $user_id,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }

            // Commit the transaction
            \DB::commit();

            // Set success message in session and redirect
            $output = [
                'success' => 1,
                'msg' => __('Ring units added successfully!')
            ];

            return redirect()->route('rewards_exchange.index', ['type' => 'ring_units'])
                ->with('status', $output);

        } catch (\Exception $e) {
            // Rollback the transaction on error
            \DB::rollBack();

            // Log the error for debugging
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

            // Set failure message in session and redirect
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong')
            ];

            return redirect()->route('rewards_exchange.index', ['type' => 'ring_units'])
                ->with('status', $output);
        }
    }


     public function cashRingIndex(Request $request)
    {
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');

            // Fetch CashRingBalance records
            $cashRings = CashRingBalance::where('cash_ring_balance.business_id', $business_id)
                ->with(['product', 'brand']) // Eager load relationships
                ->get();

            // Group by product_id to combine records for the same product
            $groupedData = $cashRings->groupBy('product_id');

            // Transform the data to match the table format
            $data = $groupedData->map(function ($records, $productId) {
                $firstRecord = $records->first(); // Get the first record for basic info
                
                // Separate USD and RIEL records
                $usdRecords = $records->where('type_currency', 1);
                $rielRecords = $records->where('type_currency', 2);

                // Combine values with commas for display
                $unitValueUsd = $usdRecords->pluck('unit_value')->filter()->implode(',');
                $redemptionValueUsd = $usdRecords->pluck('redemption_value')->filter()->implode(',');
                $unitValueRiel = $rielRecords->pluck('unit_value')->filter()->implode(',');
                $redemptionValueRiel = $rielRecords->pluck('redemption_value')->filter()->implode(',');

                return [
                    'id' => $firstRecord->id,
                    'brand_name' => $firstRecord->brand ? $firstRecord->brand->name : 'N/A',
                    'ring_name' => $firstRecord->product ? $firstRecord->product->name : 'N/A',
                    'unit_value_usd' => !empty($unitValueUsd) ? $unitValueUsd : '-',
                    'redemption_value_usd' => !empty($redemptionValueUsd) ? $redemptionValueUsd : '-',
                    'unit_value_riel' => !empty($unitValueRiel) ? $unitValueRiel : '-',
                    'redemption_value_riel' => !empty($redemptionValueRiel) ? $redemptionValueRiel : '-',
                ];
            })->sortByDesc('id')->values();

            return DataTables::of($data)
                ->addColumn('action', function ($row) {
                    $editUrl = route('cash-ring.edit', $row['id']);
                    $deleteUrl = route('cash-ring.destroy', $row['id']);
                    return '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" data-toggle="dropdown" aria-expanded="false">
                                    ' . __('messages.actions') . '
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    <li>
                                        <a href="' . $editUrl . '" class="edit-cash-ring" data-id="' . $row['id'] . '">
                                            <i class="fa fa-edit"></i> ' . __('messages.edit') . '
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" class="delete-cash-ring" data-href="' . $deleteUrl . '">
                                            <i class="fa fa-trash"></i> ' . __('messages.delete') . '
                                        </a>
                                    </li>
                                </ul>
                            </div>';
                })
                ->addColumn('brand_name', function ($row) {
                    return $row['brand_name'];
                })
                ->addColumn('ring_name', function ($row) {
                    return $row['ring_name'];
                })
                ->addColumn('unit_value_usd', function ($row) {
                    return $row['unit_value_usd'];
                })
                ->addColumn('redemption_value_usd', function ($row) {
                    return $row['redemption_value_usd'];
                })
                ->addColumn('unit_value_riel', function ($row) {
                    return $row['unit_value_riel'];
                })
                ->addColumn('redemption_value_riel', function ($row) {
                    return $row['redemption_value_riel'];
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('rewards_exchange.index');
    }

    public function createCashRing(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $brands = Brands::where('business_id', $business_id)->pluck('name', 'id')->toArray();
        return view('rewards_exchange.add_cash_ring', compact('brands'));
    }

    // Fixed storeCashRing method in RewardExchangeController.php
    public function storeCashRing(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            // UPDATED: Basic validation - brand_id is now nullable
            $request->validate([
                'brand_id' => 'nullable|exists:brands,id', // Changed from 'required' to 'nullable'
                'product_id' => 'required|exists:products,id',
                'usd_currency_data' => 'nullable|array',
                'usd_currency_data.*.unit_value' => 'nullable|numeric|min:0',
                'usd_currency_data.*.redemption_value' => 'nullable|numeric|min:0',
                'riel_currency_data' => 'nullable|array',
                'riel_currency_data.*.unit_value' => 'nullable|numeric|min:0',
                'riel_currency_data.*.redemption_value' => 'nullable|numeric|min:0',
            ]);

            // Check if product already exists in cash_ring_balance
            $existingCashRing = CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $request->product_id)
                ->exists();

            if ($existingCashRing) {
                return redirect()->back()
                    ->withErrors(['product_id' => 'A Cash Ring for this product already exists!'])
                    ->withInput();
            }

            $input = $request->only(['brand_id', 'product_id', 'usd_currency_data', 'riel_currency_data']);
            $now = now();

            // Debug: Log input data
            \Log::info('Cash Ring Store Input:', $input);

            // Combine USD and RIEL data for processing
            $validData = [];
            $validationErrors = [];
            
            foreach (['usd_currency_data' => 'USD', 'riel_currency_data' => 'RIEL'] as $key => $currency) {
                if (!isset($input[$key]) || !is_array($input[$key])) {
                    continue;
                }
                foreach ($input[$key] as $index => $data) {
                    // Handle empty values properly
                    $unitValue = isset($data['unit_value']) && $data['unit_value'] !== '' ? floatval($data['unit_value']) : 0;
                    $redemptionValue = isset($data['redemption_value']) && $data['redemption_value'] !== '' ? floatval($data['redemption_value']) : 0;

                    // Skip rows where both values are 0 or empty
                    if ($unitValue <= 0 && $redemptionValue <= 0) {
                        continue;
                    }

                    // REMOVED: Unit value vs redemption value validation
                    // Users can now enter any values without restriction

                    $validData[] = [
                        'type_currency' => $currency,
                        'unit_value' => $unitValue,
                        'redemption_value' => $redemptionValue,
                    ];
                }
            }

            // Check for validation errors
            if (!empty($validationErrors)) {
                return redirect()->back()
                    ->withErrors($validationErrors)
                    ->withInput();
            }

            // Check if we have at least one valid row
            if (empty($validData)) {
                return redirect()->back()
                    ->withErrors(['error' => 'At least one row must have a Unit Value or Redemption Value greater than 0.'])
                    ->withInput();
            }

            \DB::beginTransaction();

            // Debug: Log the data being processed
            \Log::info('Cash Ring Valid Data:', $validData);

            foreach ($validData as $data) {
                // Map type_currency: 1 for USD, 2 for RIEL
                $typeCurrency = $data['type_currency'] === 'USD' ? 1 : 2;

                $cashRingRecord = [
                    'business_id' => $business_id,
                    'brand_id' => $input['brand_id'], // This can now be null
                    'product_id' => $input['product_id'],
                    'type_currency' => $typeCurrency,
                    'unit_value' => $data['unit_value'],
                    'redemption_value' => $data['redemption_value'],
                    'created_by' => $user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                \Log::info('Creating CashRingBalance record:', $cashRingRecord);

                $created = CashRingBalance::create($cashRingRecord);
                
                \Log::info('CashRingBalance created with ID: ' . $created->id);
            }

            \DB::commit();

            \Log::info('Cash Ring creation completed successfully');

            return redirect()->route('rewards_exchange.index', ['type' => 'cash_ring'])
                ->with('success', 'Cash ring added successfully!');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation Exception:', $e->errors());
            // Handle validation errors specifically
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
                
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::emergency('Cash Ring Store Error - File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Something went wrong while creating the cash ring: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function editCashRing($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            
            // Get the first cash ring record to determine the product_id
            $firstCashRing = CashRingBalance::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();
            
            $product_id = $firstCashRing->product_id;
            
            // Get all cash ring records for this product
            $cashRings = CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->with(['product', 'brand'])
                ->get();
            
            if ($cashRings->isEmpty()) {
                return redirect()->route('rewards_exchange.index', ['type' => 'cash_ring'])
                    ->with('error', 'Cash ring not found.');
            }
            
            // Get brands for the dropdown
            $brands = Brands::where('business_id', $business_id)->pluck('name', 'id')->toArray();
            
            // Prepare data for the view
            $cashRingData = [
                'id' => $id,
                'product_id' => $product_id,
                'brand_id' => $firstCashRing->brand_id,
                'product_name' => $firstCashRing->product ? $firstCashRing->product->name : 'N/A',
                'product_sku' => $firstCashRing->product ? $firstCashRing->product->sku : 'N/A',
                'brand_name' => $firstCashRing->brand ? $firstCashRing->brand->name : 'N/A',
                'usd_records' => $cashRings->where('type_currency', 1)->values(),
                'riel_records' => $cashRings->where('type_currency', 2)->values(),
            ];
            
            return view('rewards_exchange.add_cash_ring', compact('brands', 'cashRingData'));
            
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return redirect()->route('rewards_exchange.index', ['type' => 'cash_ring'])
                ->with('error', 'Something went wrong while loading the cash ring data.');
        }
    }

    public function updateCashRing(Request $request, $id)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            // Get the first cash ring record to determine the product_id
            $firstCashRing = CashRingBalance::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();
            
            $product_id = $firstCashRing->product_id;
            
            $input = $request->only(['brand_id', 'product_id', 'usd_currency_data', 'riel_currency_data']);
            $now = now();

            // Debug: Log input data
            \Log::info('Cash Ring Update Input:', $input);

            // UPDATED: Basic validation - brand_id is now nullable
            $request->validate([
                'brand_id' => 'nullable|exists:brands,id', // Changed from 'required' to 'nullable'
                'product_id' => 'required|exists:products,id',
                'usd_currency_data' => 'nullable|array',
                'usd_currency_data.*.unit_value' => 'nullable|numeric|min:0',
                'usd_currency_data.*.redemption_value' => 'nullable|numeric|min:0',
                'usd_currency_data.*.id' => 'nullable|exists:cash_ring_balance,id',
                'riel_currency_data' => 'nullable|array',
                'riel_currency_data.*.unit_value' => 'nullable|numeric|min:0',
                'riel_currency_data.*.redemption_value' => 'nullable|numeric|min:0',
                'riel_currency_data.*.id' => 'nullable|exists:cash_ring_balance,id',
            ]);

            // Combine USD and RIEL data for processing
            $validData = [];
            $validationErrors = [];
            $submittedIds = []; // Track IDs that are submitted
            
            foreach (['usd_currency_data' => 'USD', 'riel_currency_data' => 'RIEL'] as $key => $currency) {
                if (!isset($input[$key]) || !is_array($input[$key])) {
                    continue;
                }
                foreach ($input[$key] as $index => $data) {
                    // Handle empty values properly
                    $unitValue = isset($data['unit_value']) && $data['unit_value'] !== '' ? floatval($data['unit_value']) : 0;
                    $redemptionValue = isset($data['redemption_value']) && $data['redemption_value'] !== '' ? floatval($data['redemption_value']) : 0;
                    $recordId = !empty($data['id']) ? $data['id'] : null;

                    // Track submitted IDs
                    if ($recordId) {
                        $submittedIds[] = $recordId;
                    }

                    // Skip rows where both values are 0 or empty
                    if ($unitValue <= 0 && $redemptionValue <= 0) {
                        continue;
                    }

                    // REMOVED: Unit value vs redemption value validation
                    // Users can now enter any values without restriction

                    $validData[] = [
                        'id' => $recordId,
                        'type_currency' => $currency,
                        'unit_value' => $unitValue,
                        'redemption_value' => $redemptionValue,
                    ];
                }
            }

            // Check for validation errors
            if (!empty($validationErrors)) {
                \Log::error('Cash Ring Update Validation Errors:', $validationErrors);
                return redirect()->back()
                    ->withErrors($validationErrors)
                    ->withInput();
            }

            // Check if we have at least one valid row
            if (empty($validData)) {
                return redirect()->back()
                    ->withErrors(['error' => 'At least one row must have a Unit Value or Redemption Value greater than 0.'])
                    ->withInput();
            }

            \DB::beginTransaction();

            // Debug: Log the data being processed
            \Log::info('Cash Ring Update Valid Data:', $validData);

            // Fetch existing cash ring records for this product
            $existingRecords = CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->get()
                ->keyBy('id');

            \Log::info('Existing Cash Ring Records:', $existingRecords->pluck('id')->toArray());
            \Log::info('Submitted IDs:', $submittedIds);

            // Delete records that were not submitted (removed from form)
            $recordsToDelete = $existingRecords->whereNotIn('id', $submittedIds);
            foreach ($recordsToDelete as $record) {
                \Log::info('Deleting record ID: ' . $record->id);
                $record->delete();
            }

            // Process each valid data entry
            foreach ($validData as $data) {
                // Map type_currency: 1 for USD, 2 for RIEL
                $typeCurrency = $data['type_currency'] === 'USD' ? 1 : 2;

                if ($data['id'] && isset($existingRecords[$data['id']])) {
                    // Update existing record
                    \Log::info('Updating existing record ID: ' . $data['id']);
                    
                    $updateData = [
                        'brand_id' => $input['brand_id'], // This can now be null
                        'product_id' => $input['product_id'],
                        'type_currency' => $typeCurrency,
                        'unit_value' => $data['unit_value'],
                        'redemption_value' => $data['redemption_value'],
                        'updated_at' => $now,
                    ];
                    
                    \Log::info('Update data:', $updateData);
                    
                    $existingRecords[$data['id']]->update($updateData);
                    
                    \Log::info('Record updated successfully');
                } else {
                    // Insert new record
                    \Log::info('Creating new record for currency: ' . $data['type_currency']);
                    
                    $createData = [
                        'business_id' => $business_id,
                        'brand_id' => $input['brand_id'], // This can now be null
                        'product_id' => $input['product_id'],
                        'type_currency' => $typeCurrency,
                        'unit_value' => $data['unit_value'],
                        'redemption_value' => $data['redemption_value'],
                        'created_by' => $user_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    
                    \Log::info('Create data:', $createData);
                    
                    $created = CashRingBalance::create($createData);
                    
                    \Log::info('New record created with ID: ' . $created->id);
                }
            }

            \DB::commit();

            \Log::info('Cash Ring update completed successfully');

            return redirect()->route('rewards_exchange.index', ['type' => 'cash_ring'])
                ->with('success', 'Cash ring updated successfully.');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Cash Ring Update Validation Exception:', $e->errors());
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
                
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::emergency('Cash Ring Update Error - File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Something went wrong while updating the cash ring: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroyCashRing($id, Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');

            // Fetch the CashRingBalance record to get the product_id
            $cashRing = CashRingBalance::where('business_id', $business_id)
                ->where('id', $id)
                ->firstOrFail();

            $product_id = $cashRing->product_id;

            // Delete all CashRingBalance records for the product_id and business_id
            CashRingBalance::where('business_id', $business_id)
                ->where('product_id', $product_id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cash ring deleted successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the cash ring.'
            ], 500);
        }
    }
}
