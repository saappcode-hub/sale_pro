<?php

namespace App\Http\Controllers;

use App\Currency;
use App\MiddleCurrency;
use Datatables;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        \DB::beginTransaction(); // Start transaction to ensure data integrity
        // Assuming user_id should be the ID of the authenticated user
        $store_id = request()->session()->get('user.business_id');
        // $store_id = auth()->user()->id;
       
        // Fetch currencies and their related MiddleCurrency records if they exist
        $categories = Currency::select('id', 'country', 'currency', 'code', 'symbol')
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->with(['middleCurrencies' => function ($query) use ($store_id) {
                $query->where('store_id', $store_id);
            }])
            ->get();
          
        // // Map the exchange rate or default to 0 if not present
        $categories->transform(function ($category) {
            $exchange_rate = $category->middleCurrencies->first() ? $category->middleCurrencies->first()->exchange_rate : 0;
            return [
                'id' => $category->id,
                'country' => $category->country,
                'currency' => $category->currency,
                'code' => $category->code,
                'symbol' => $category->symbol,
                'exchange_rate' => $exchange_rate
            ];
        });
        
        // // Pass the data to the view
        return view('currency.index', compact('categories'));
    }     

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('currency.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        \DB::beginTransaction(); // Start transaction to ensure data integrity
        try {
            $input = $request->only(['country', 'currency', 'code', 'symbol']);
            
            // Create a new Currency
            $currency = Currency::create($input);
    
            // Check if the Currency was successfully created
            if ($currency) {
                // Assuming user_id should be the ID of the authenticated user
                $user_id = request()->session()->get('user.business_id');
                // $user_id = auth()->user()->id;
    
                // Prepare the data for MiddleCurrency
                $middleCurrencyData = [
                    'store_id' => $user_id,
                    'currency_id' => $currency->id,
                    'exchange_rate' => $request->input('exchange_rate'),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
    
                // Create MiddleCurrency record
                MiddleCurrency::create($middleCurrencyData);
    
                \DB::commit(); // Commit the transaction
    
                $output = [
                    'success' => true,
                    'msg' => __('added_success'),
                    'refresh' => true // Add flag to trigger page refresh
                ];
            } else {
                // Handle the case where Currency wasn't created
                \DB::rollBack();
                $output = [
                    'success' => false,
                    'msg' => __('messages.unable_to_create_currency'),
                ];
            }
        } catch (\Exception $e) {
            \DB::rollBack(); // Rollback transaction on error
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
    
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    
        return $output;
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('currencies_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $currency = Currency::find($id);

        // Get the business_id (store_id) from the session
        $business_id = request()->session()->get('user.business_id');

        // Fetch the exchange rate from middle_currencies table
        $middle_currency = MiddleCurrency::where('store_id', $business_id)
            ->where('currency_id', $id)
            ->first();
        
        // Set exchange rate to the value from middle_currencies or 0 if not found
        $exchange_rate = $middle_currency ? $middle_currency->exchange_rate : 0;
        
        return view('currency.edit')->with(compact('currency', 'exchange_rate'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            // Get only the needed input for updating currency
            $input = $request->only(['country', 'currency', 'code', 'symbol', 'exchange_rate']);
    
            // Update the currency entry and retrieve the instance
            $currency = Currency::updateOrCreate(
                ['id' => $id],  // Check by 'id'
                $input          // Update or insert data
            );
    
            // Retrieve user_id from authenticated user
            $user_id = request()->session()->get('user.business_id');
            // $user_id = auth()->user()->id;
    
            // Get the exchange rate directly from the request input
            $exchange_rate = $request->input('exchange_rate');

            // Prepare the data for middle currency
            $middle_currency_data = [
                'store_id' => $user_id,
                'currency_id' => $currency->id,
                'exchange_rate' => $exchange_rate,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Update or create middle currency record
            MiddleCurrency::updateOrCreate(
                ['store_id' => $user_id, 'currency_id' => $currency->id],
                $middle_currency_data
            );
    
            // Return success message with refresh flag
            $output = [
                'success' => true,
                'msg' => __('invoice.updated_success'),
                'refresh' => true // Add flag to trigger page refresh
            ];
    
        } catch (\Exception $e) {
            // Log the exception details
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
    
            // Return error message
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    
        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $Currency = Currency::find($id);
                $Currency->delete();
                $output = ['success' => true,
                    'msg' => __('Currency deleted_success'),
                ];
               
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}