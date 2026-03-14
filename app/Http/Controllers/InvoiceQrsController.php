<?php

namespace App\Http\Controllers;

use App\InvoiceQrs;
use App\Utils\Util;
use Illuminate\Http\Request;
use Validator;

class InvoiceQrsController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        return view('invoice_qrs.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'nullable|mimes:jpeg,jpg,png|max:1000', // Assuming a maximum size of 1000KB
            ]);
    
            $input = $request->only(['name', 'image']);
    
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
    
            // Handle Image Upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/img');
                $file->move($path, $filename);
                $input['image'] = $filename; // Store the filename in the database
            }
    
            InvoiceQrs::create($input);
            $output = ['success' => 1,
                'msg' => __('invoice.layout_added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
    
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    
        return redirect('invoice-schemes')->with('status', $output);
    }
    

    /**
     * Display the specified resource.
     *
     * @param  \App\InvoiceQrs  $invoiceQrs
     * @return \Illuminate\Http\Response
     */
    public function show(InvoiceQrs $invoiceQrs)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\InvoiceQrs  $invoiceQrs
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $invoice_layout = InvoiceQrs::findOrFail($id);
        return view('invoice_qrs.edit')
                ->with(compact('invoice_layout'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\InvoiceQrs  $invoiceQrs
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('invoice_settings.access')) {
            abort(403, 'Unauthorized action.');
        }
    
        try {
            // Validate the incoming data
            $validator = Validator::make($request->all(), [
                'image' => 'nullable|mimes:jpeg,jpg,png|max:1000', // Adjust size validation as necessary
            ]);
    
            $business_id = $request->session()->get('user.business_id');
            $input = $request->only(['name']);
    
            // Handle Image Upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/img');
                $file->move($path, $filename);
                $input['image'] = $filename; // Store the filename in the database
    
                // Update the database record with new file name
                // Optionally delete old file if needed
                $existingImage = InvoiceQrs::where('id', $id)->where('business_id', $business_id)->first(['image']);
                if ($existingImage && file_exists($path . '/' . $existingImage->image)) {
                    @unlink($path . '/' . $existingImage->image); // Delete old image file
                }
            }
    
            // Update the record in the database
            InvoiceQrs::where('id', $id)->where('business_id', $business_id)->update($input);
            
            $output = ['success' => 1,
                'msg' => __('invoice.layout_updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
    
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }
    
        return redirect('invoice-schemes')->with('status', $output);
    }
}
