<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Product;

class ProductSaleVisitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $business_id = $request->session()->get('user.business_id');
        
            // Base query for active products
            $query = Product::where('products.business_id', $business_id)
                ->where('products.is_inactive', 0)
                ->orderBy('products.sku', 'desc')
                ->select([
                    'products.id',
                    'products.name as product_name',
                    'products.sku',
                    'products.product_sale_visit'
                ]);
                
            if ($request->filled('product_sale_visit_filter')) {
                $filterValue = $request->product_sale_visit_filter;

                if ($filterValue == '1') {
                    // Filter for products that ARE for sale visit.
                    $query->where('products.product_sale_visit', 1);
                } elseif ($filterValue == '0') {
                    // Filter for products that are NOT for sale visit.
                    // This correctly checks for NULL values, as there are no '0's in the column.
                    $query->whereNull('products.product_sale_visit');
                }
            }
            
            return DataTables::of($query)
                ->addColumn('mass_select', function ($row) {
                    return '<input type="checkbox" class="row-select" value="'.$row->id.'">';
                })
                ->addColumn('product_name', function ($row) {
                    return $row->product_name;
                })
                ->addColumn('sku', function ($row) {
                    return $row->sku;
                })
                ->addColumn('product_sale_visit', function ($row) {
                    if ($row->product_sale_visit == 1) {
                        return '<span class="label label-success">Yes</span>';
                    } else {
                        return '<span class="label label-default">No</span>';
                    }
                })
                ->rawColumns(['mass_select', 'product_sale_visit'])
                ->make(true);
        }
        return view('product_sale_visit.index');
    }

    /**
     * Update the product_sale_visit status for selected products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateSaleVisit(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $selected_products = $request->input('selected_products', []);
            $product_sale_visit = $request->input('product_sale_visit'); // '1' or null from JS

            if (empty($selected_products)) {
                return response()->json([
                    'success' => false,
                    'msg' => 'No products selected.'
                ]);
            }

            if (is_string($selected_products)) {
                $selected_products = explode(',', $selected_products);
            }

            // Set value to 1 or null. This ensures no '0' is ever saved.
            $visit_value = ($product_sale_visit == 1) ? 1 : null;

            $updated_count = Product::where('business_id', $business_id)
                ->whereIn('id', $selected_products)
                ->update([
                    'product_sale_visit' => $visit_value
                ]);

            $action_text = ($product_sale_visit == 1) ? 'set as Product For Sale Visit' : 'removed from Product For Sale Visit';
            
            return response()->json([
                'success' => true,
                'msg' => "Successfully {$action_text} for {$updated_count} products."
            ]);

        } catch (\Exception $e) {
            \Log::error('Product Sale Visit update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'msg' => 'An error occurred while updating products.'
            ]);
        }
    }
}