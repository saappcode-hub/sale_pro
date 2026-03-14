<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\CurrentStockBackup;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpeningStockController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $transactionUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function add($product_id)
    {
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        // Get the product
        $product = Product::where('business_id', $business_id)
                            ->where('id', $product_id)
                            ->with(['variations',
                                'variations.product_variation',
                                'unit',
                                'product_locations',
                                'second_unit',
                            ])
                            ->first();
        if (! empty($product) && $product->enable_stock == 1) {
            // Get all Opening Stock Transactions for the product, including the first
            $transactions = Transaction::where('business_id', $business_id)
                                ->where('opening_stock_product_id', $product_id)
                                ->where('type', 'opening_stock')
                                ->with(['purchase_lines'])
                                ->get();

            $purchases = [];
            $purchase_lines = [];
            foreach ($transactions as $transaction) {
                foreach ($transaction->purchase_lines as $purchase_line) {
                    if (! empty($purchase_lines[$purchase_line->variation_id])) {
                        $k = count($purchase_lines[$purchase_line->variation_id]);
                    } else {
                        $k = 0;
                        $purchase_lines[$purchase_line->variation_id] = [];
                    }

                    // Show only remaining quantity for editing opening stock.
                    $purchase_lines[$purchase_line->variation_id][$k]['quantity'] = $purchase_line->quantity_remaining;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_price'] = $purchase_line->purchase_price;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_id'] = $purchase_line->id;
                    $purchase_lines[$purchase_line->variation_id][$k]['exp_date'] = $purchase_line->exp_date;
                    $purchase_lines[$purchase_line->variation_id][$k]['lot_number'] = $purchase_line->lot_number;
                    $purchase_lines[$purchase_line->variation_id][$k]['transaction_date'] = $this->productUtil->format_date($transaction->transaction_date, true);

                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_note'] = $transaction->additional_notes;
                    $purchase_lines[$purchase_line->variation_id][$k]['location_id'] = $transaction->location_id;
                    $purchase_lines[$purchase_line->variation_id][$k]['secondary_unit_quantity'] = $purchase_line->secondary_unit_quantity;
                }
            }

            foreach ($purchase_lines as $v_id => $pls) {
                foreach ($pls as $pl) {
                    $purchases[$pl['location_id']][$v_id][] = $pl;
                }
            }

            $locations = BusinessLocation::forDropdown($business_id);

            // Unset locations where product is not available
            $available_locations = $product->product_locations->pluck('id')->toArray();
            foreach ($locations as $key => $value) {
                if (! in_array($key, $available_locations)) {
                    unset($locations[$key]);
                }
            }

            $enable_expiry = request()->session()->get('business.enable_product_expiry');
            $enable_lot = request()->session()->get('business.enable_lot_number');

            if (request()->ajax()) {
                return view('opening_stock.ajax_add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot'
                    ));
            }

            return view('opening_stock.add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot'
                    ));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request)
    {
        if (!auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            Log::info('Starting save method for opening stock', [
                'user_id' => $request->session()->get('user.id'),
                'business_id' => $request->session()->get('user.business_id'),
            ]);

            $opening_stocks = $request->input('stocks');
            $product_id = $request->input('product_id');

            Log::info('Received opening stock data', [
                'product_id' => $product_id,
                'opening_stocks' => $opening_stocks,
            ]);

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $product = Product::where('business_id', $business_id)
                            ->where('id', $product_id)
                            ->with(['variations', 'product_tax'])
                            ->first();

            if (!$product) {
                throw new \Exception('Product not found for business_id: ' . $business_id . ', product_id: ' . $product_id);
            }

            $locations = BusinessLocation::forDropdown($business_id)->toArray();

            if (!empty($product) && $product->enable_stock == 1) {
                $tax_percent = !empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
                $tax_id = !empty($product->product_tax->id) ? $product->product_tax->id : null;

                $transaction_date = request()->session()->get('financial_year.start');
                $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

                Log::info('Starting transaction for product', [
                    'product_id' => $product->id,
                    'transaction_date' => $transaction_date,
                ]);

                DB::beginTransaction();

                // Get the first transaction to exclude it from updates
                $first_transaction = Transaction::where('type', 'opening_stock')
                    ->where('business_id', $business_id)
                    ->where('opening_stock_product_id', $product->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                foreach ($opening_stocks as $location_id => $value) {
                    Log::info('Processing location', [
                        'location_id' => $location_id,
                        'value' => $value,
                    ]);

                    $new_purchase_lines = [];
                    $edit_purchase_lines = [];
                    $new_transaction_data = [];
                    $edit_transaction_data = [];

                    if (array_key_exists($location_id, $locations)) {
                        foreach ($value as $vid => $purchase_lines_data) {
                            Log::info('Processing variation', [
                                'variation_id' => $vid,
                                'purchase_lines_data' => $purchase_lines_data,
                            ]);

                            foreach ($purchase_lines_data as $k => $pl) {
                                Log::info('Processing purchase line', [
                                    'index' => $k,
                                    'purchase_line_data' => $pl,
                                ]);

                                $purchase_line = null;

                                if (isset($pl['purchase_line_id'])) {
                                    Log::info('Checking existing purchase line', [
                                        'purchase_line_id' => $pl['purchase_line_id'],
                                    ]);

                                    $purchase_line = PurchaseLine::findOrFail($pl['purchase_line_id']);

                                    // Skip if the purchase line belongs to the first transaction
                                    if ($first_transaction && $purchase_line->transaction_id == $first_transaction->id) {
                                        Log::info('Skipping purchase line from first transaction', [
                                            'purchase_line_id' => $pl['purchase_line_id'],
                                            'transaction_id' => $purchase_line->transaction_id,
                                        ]);
                                        continue;
                                    }

                                    $qty_remaining = isset($pl['quantity']) ? $this->productUtil->num_uf(trim($pl['quantity'])) : $purchase_line->quantity;
                                    $purchase_price = isset($pl['purchase_price']) ? $this->productUtil->num_uf(trim($pl['purchase_price'])) : $purchase_line->purchase_price;
                                    $item_tax = $this->productUtil->calc_percentage($purchase_price, $tax_percent);
                                    $purchase_price_inc_tax = $purchase_price + $item_tax;
                                    $secondary_unit_quantity = isset($pl['secondary_unit_quantity']) ? $this->productUtil->num_uf(trim($pl['secondary_unit_quantity'])) : $purchase_line->secondary_unit_quantity;

                                    $exp_date = isset($pl['exp_date']) && !empty($pl['exp_date']) ? $this->productUtil->uf_date($pl['exp_date']) : $purchase_line->exp_date;
                                    $lot_number = isset($pl['lot_number']) && !empty($pl['lot_number']) ? $pl['lot_number'] : $purchase_line->lot_number;
                                    $transaction_date = isset($pl['transaction_date']) && !empty($pl['transaction_date']) ? $this->productUtil->uf_date($pl['transaction_date'], true) : $purchase_line->transaction->transaction_date;

                                    if ($qty_remaining != $purchase_line->quantity) {
                                        if ($qty_remaining != 0) {
                                            $old_qty = $purchase_line->quantity;
                                            $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, $old_qty, null, false);
                                        } else {
                                            $this->productUtil->decreaseProductQuantity($product->id, $vid, $location_id, $purchase_line->quantity);
                                        }
                                    }

                                    $purchase_line->quantity = $qty_remaining;
                                    $purchase_line->purchase_price = $purchase_price;
                                    $purchase_line->purchase_price_inc_tax = $purchase_price_inc_tax;
                                    $purchase_line->exp_date = $exp_date;
                                    $purchase_line->lot_number = $lot_number;
                                    $purchase_line->secondary_unit_quantity = $secondary_unit_quantity;

                                    $edit_purchase_lines[$purchase_line->transaction_id][] = $purchase_line;
                                    $purchase_line->save();
                                    $edit_transaction_data[$purchase_line->transaction_id] = [
                                        'transaction_date' => $transaction_date,
                                        'additional_notes' => isset($pl['purchase_line_note']) ? $pl['purchase_line_note'] : $purchase_line->transaction->additional_notes,
                                    ];
                                } else {
                                    $qty_remaining = $this->productUtil->num_uf(trim($pl['quantity']));
                                    if ($qty_remaining != 0) {
                                        Log::info('Creating new purchase line', [
                                            'quantity' => $qty_remaining,
                                        ]);

                                        $purchase_price = $this->productUtil->num_uf(trim($pl['purchase_price']));
                                        $item_tax = $this->productUtil->calc_percentage($purchase_price, $tax_percent);
                                        $purchase_price_inc_tax = $purchase_price + $item_tax;
                                        $secondary_unit_quantity = isset($pl['secondary_unit_quantity']) ? $this->productUtil->num_uf(trim($pl['secondary_unit_quantity'])) : 0;

                                        $exp_date = isset($pl['exp_date']) && !empty($pl['exp_date']) ? $this->productUtil->uf_date($pl['exp_date']) : null;
                                        $lot_number = isset($pl['lot_number']) && !empty($pl['lot_number']) ? $pl['lot_number'] : null;
                                        $transaction_date = isset($pl['transaction_date']) && !empty($pl['transaction_date']) ? $this->productUtil->uf_date($pl['transaction_date'], true) : $transaction_date;

                                        $purchase_line = new PurchaseLine();
                                        $purchase_line->product_id = $product->id;
                                        $purchase_line->variation_id = $vid;
                                        $purchase_line->item_tax = $item_tax;
                                        $purchase_line->tax_id = $tax_id;
                                        $purchase_line->quantity = $qty_remaining;
                                        $purchase_line->pp_without_discount = $purchase_price;
                                        $purchase_line->purchase_price = $purchase_price;
                                        $purchase_line->purchase_price_inc_tax = $purchase_price_inc_tax;
                                        $purchase_line->exp_date = $exp_date;
                                        $purchase_line->lot_number = $lot_number;
                                        $purchase_line->secondary_unit_quantity = $secondary_unit_quantity;

                                        $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, 0, null, false);

                                        $new_purchase_lines[] = $purchase_line;
                                        $new_transaction_data[] = [
                                            'transaction_date' => $transaction_date,
                                            'additional_notes' => isset($pl['purchase_line_note']) ? $pl['purchase_line_note'] : null,
                                        ];
                                    } else {
                                        Log::info('Purchase line quantity is 0, skipping');
                                    }
                                }
                            }
                        }

                        $updated_transaction_ids = [];
                        if (!empty($edit_purchase_lines)) {
                            Log::info('Processing edit purchase lines', [
                                'edit_purchase_lines' => array_keys($edit_purchase_lines),
                            ]);

                            foreach ($edit_purchase_lines as $t_id => $purchase_lines) {
                                $purchase_total = 0;
                                $updated_purchase_line_ids = [];
                                $stock_lines = [];

                                foreach ($purchase_lines as $purchase_line) {
                                    $quantity = $purchase_line->quantity;

                                    $previous_opening_stock = CurrentStockBackup::where('business_id', $business_id)
                                        ->where('location_id', $location_id)
                                        ->where('product_id', $purchase_line->product_id)
                                        ->where('transaction_id', $t_id)
                                        ->where('t_type', 'opening_stock')
                                        ->orderBy('transaction_date', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    $current_stock = 0;

                                    if ($previous_opening_stock) {
                                        $last_stock_before_previous = CurrentStockBackup::where('business_id', $business_id)
                                            ->where('location_id', $location_id)
                                            ->where('product_id', $purchase_line->product_id)
                                            ->where('id', '<', $previous_opening_stock->id)
                                            ->orderBy('transaction_date', 'desc')
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        $current_stock = $last_stock_before_previous ? $last_stock_before_previous->new_qty : 0;

                                        $reversal_stock_line = [
                                            'business_id' => $business_id,
                                            'location_id' => $location_id,
                                            'product_id' => $purchase_line->product_id,
                                            'variation_id' => $purchase_line->variation_id,
                                            'transaction_id' => $t_id,
                                            't_type' => 'edit_opening_stock_reversal',
                                            'qty_change' => $previous_opening_stock->qty_change,
                                            'new_qty' => $current_stock,
                                            'transaction_date' => now(),
                                        ];

                                        $stock_lines[] = new CurrentStockBackup($reversal_stock_line);
                                    }

                                    $new_qty = $current_stock + $quantity;

                                    $stock_line = [
                                        'business_id' => $business_id,
                                        'location_id' => $location_id,
                                        'product_id' => $purchase_line->product_id,
                                        'variation_id' => $purchase_line->variation_id,
                                        'transaction_id' => $t_id,
                                        't_type' => 'opening_stock',
                                        'qty_change' => $quantity,
                                        'new_qty' => $new_qty,
                                        'transaction_date' => now(),
                                    ];

                                    $stock_lines[] = new CurrentStockBackup($stock_line);

                                    $purchase_total += $purchase_line->purchase_price_inc_tax * $purchase_line->quantity;
                                    $updated_purchase_line_ids[] = $purchase_line->id;
                                }

                                if (!empty($stock_lines)) {
                                    CurrentStockBackup::insert(
                                        array_map(function ($line) {
                                            return $line->toArray();
                                        }, $stock_lines)
                                    );
                                }

                                $transaction = Transaction::where('type', 'opening_stock')
                                    ->where('business_id', $business_id)
                                    ->where('location_id', $location_id)
                                    ->find($t_id);

                                $transaction->total_before_tax = $purchase_total;
                                $transaction->final_total = $purchase_total;
                                $transaction->transaction_date = $edit_transaction_data[$transaction->id]['transaction_date'];
                                $transaction->additional_notes = $edit_transaction_data[$transaction->id]['additional_notes'];
                                $transaction->update();

                                $updated_transaction_ids[] = $transaction->id;

                                $delete_purchase_line_ids = [];
                                $delete_purchase_lines = PurchaseLine::where('transaction_id', $transaction->id)
                                            ->whereNotIn('id', $updated_purchase_line_ids)
                                            ->get();

                                if ($delete_purchase_lines->count()) {
                                    $stock_lines = [];
                                    foreach ($delete_purchase_lines as $delete_purchase_line) {
                                        $delete_purchase_line_ids[] = $delete_purchase_line->id;

                                        $this->productUtil->decreaseProductQuantity(
                                            $delete_purchase_line->product_id,
                                            $delete_purchase_line->variation_id,
                                            $transaction->location_id,
                                            $delete_purchase_line->quantity
                                        );

                                        $previous_opening_stock = CurrentStockBackup::where('business_id', $business_id)
                                            ->where('location_id', $location_id)
                                            ->where('product_id', $delete_purchase_line->product_id)
                                            ->where('transaction_id', $transaction->id)
                                            ->where('t_type', 'opening_stock')
                                            ->orderBy('transaction_date', 'desc')
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        if ($previous_opening_stock) {
                                            $last_stock_before_previous = CurrentStockBackup::where('business_id', $business_id)
                                                ->where('location_id', $location_id)
                                                ->where('product_id', $delete_purchase_line->product_id)
                                                ->where('id', '<', $previous_opening_stock->id)
                                                ->orderBy('transaction_date', 'desc')
                                                ->orderBy('id', 'desc')
                                                ->first();

                                            $current_stock = $last_stock_before_previous ? $last_stock_before_previous->new_qty : 0;

                                            $delete_stock_line = [
                                                'business_id' => $business_id,
                                                'location_id' => $location_id,
                                                'product_id' => $delete_purchase_line->product_id,
                                                'variation_id' => $delete_purchase_line->variation_id,
                                                'transaction_id' => $transaction->id,
                                                't_type' => 'delete_opening_stock',
                                                'qty_change' => $previous_opening_stock->qty_change,
                                                'new_qty' => $current_stock,
                                                'transaction_date' => now(),
                                            ];

                                            $stock_lines[] = new CurrentStockBackup($delete_stock_line);
                                        }
                                    }

                                    if (!empty($stock_lines)) {
                                        CurrentStockBackup::insert(
                                            array_map(function ($line) {
                                                return $line->toArray();
                                            }, $stock_lines)
                                        );
                                    }

                                    PurchaseLine::where('transaction_id', $transaction->id)
                                                ->whereIn('id', $delete_purchase_line_ids)
                                                ->delete();
                                }

                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $transaction, $delete_purchase_lines);
                                $this->productUtil->adjustStockOverSelling($transaction);
                            }
                        }

                        $delete_transactions = Transaction::where('type', 'opening_stock')
                            ->where('business_id', $business_id)
                            ->where('opening_stock_product_id', $product->id)
                            ->where('location_id', $location_id)
                            ->with(['purchase_lines'])
                            ->whereNotIn('id', $updated_transaction_ids);

                        if ($first_transaction) {
                            $delete_transactions->where('id', '!=', $first_transaction->id);
                        }

                        $delete_transactions = $delete_transactions->get();

                        if (count($delete_transactions) > 0) {
                            foreach ($delete_transactions as $delete_transaction) {
                                $delete_purchase_lines = $delete_transaction->purchase_lines;
                                $stock_lines = [];

                                foreach ($delete_purchase_lines as $delete_purchase_line) {
                                    $this->productUtil->decreaseProductQuantity($product->id, $delete_purchase_line->variation_id, $location_id, $delete_purchase_line->quantity);

                                    $previous_opening_stock = CurrentStockBackup::where('business_id', $business_id)
                                        ->where('location_id', $location_id)
                                        ->where('product_id', $delete_purchase_line->product_id)
                                        ->where('transaction_id', $delete_transaction->id)
                                        ->where('t_type', 'opening_stock')
                                        ->orderBy('transaction_date', 'desc')
                                        ->orderBy('id', 'desc')
                                        ->first();

                                    if ($previous_opening_stock) {
                                        $last_stock_before_previous = CurrentStockBackup::where('business_id', $business_id)
                                            ->where('location_id', $location_id)
                                            ->where('product_id', $delete_purchase_line->product_id)
                                            ->where('id', '<', $previous_opening_stock->id)
                                            ->orderBy('transaction_date', 'desc')
                                            ->orderBy('id', 'desc')
                                            ->first();

                                        $current_stock = $last_stock_before_previous ? $last_stock_before_previous->new_qty : 0;

                                        $delete_stock_line = [
                                            'business_id' => $business_id,
                                            'location_id' => $location_id,
                                            'product_id' => $delete_purchase_line->product_id,
                                            'variation_id' => $delete_purchase_line->variation_id,
                                            'transaction_id' => $delete_transaction->id,
                                            't_type' => 'delete_opening_stock',
                                            'qty_change' => $previous_opening_stock->qty_change,
                                            'new_qty' => $current_stock,
                                            'transaction_date' => now(),
                                        ];

                                        $stock_lines[] = new CurrentStockBackup($delete_stock_line);
                                    }

                                    $delete_purchase_line->delete();
                                }

                                if (!empty($stock_lines)) {
                                    CurrentStockBackup::insert(
                                        array_map(function ($line) {
                                            return $line->toArray();
                                        }, $stock_lines)
                                    );
                                }

                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $delete_transaction, $delete_purchase_lines);
                                $delete_transaction->delete();
                            }
                        }

                        if (!empty($new_purchase_lines)) {
                            Log::info('Processing new purchase lines', [
                                'new_purchase_lines_count' => count($new_purchase_lines),
                                'new_purchase_lines' => $new_purchase_lines,
                            ]);

                            // Filter out null entries from new_purchase_lines
                            $new_purchase_lines = array_filter($new_purchase_lines, function ($line) {
                                return !is_null($line);
                            });

                            Log::info('Filtered new purchase lines', [
                                'filtered_count' => count($new_purchase_lines),
                            ]);

                            if (empty($new_purchase_lines)) {
                                Log::warning('No valid new purchase lines to process after filtering');
                                continue;
                            }

                            $transaction = Transaction::create([
                                'type' => 'opening_stock',
                                'opening_stock_product_id' => $product->id,
                                'status' => 'received',
                                'business_id' => $business_id,
                                'transaction_date' => $transaction_date,
                                'additional_notes' => !empty($new_transaction_data[0]['additional_notes']) ? $new_transaction_data[0]['additional_notes'] : null,
                                'total_before_tax' => 0,
                                'location_id' => $location_id,
                                'final_total' => 0,
                                'payment_status' => 'paid',
                                'created_by' => $user_id,
                            ]);

                            Log::info('Created new transaction', [
                                'transaction_id' => $transaction->id,
                            ]);

                            $purchase_total = 0;
                            foreach ($new_purchase_lines as $index => $new_purchase_line) {
                                Log::info('Saving new purchase line', [
                                    'index' => $index,
                                    'purchase_line' => [
                                        'product_id' => $new_purchase_line->product_id,
                                        'variation_id' => $new_purchase_line->variation_id,
                                        'quantity' => $new_purchase_line->quantity,
                                    ],
                                ]);

                                $transaction->purchase_lines()->save($new_purchase_line);

                                $quantity = $new_purchase_line->quantity;

                                $latest_stock = CurrentStockBackup::where('business_id', $business_id)
                                    ->where('location_id', $location_id)
                                    ->where('product_id', $new_purchase_line->product_id)
                                    ->orderBy('transaction_date', 'desc')
                                    ->orderBy('id', 'desc')
                                    ->first();

                                $current_stock = $latest_stock ? $latest_stock->new_qty : 0;
                                $new_qty = $current_stock + $quantity;

                                $stock_line = [
                                    'business_id' => $business_id,
                                    'location_id' => $location_id,
                                    'product_id' => $new_purchase_line->product_id,
                                    'variation_id' => $new_purchase_line->variation_id,
                                    'transaction_id' => $transaction->id,
                                    't_type' => 'opening_stock',
                                    'qty_change' => $quantity,
                                    'new_qty' => $new_qty,
                                    'transaction_date' => now(),
                                ];

                                Log::info('Creating CurrentStockBackup entry', [
                                    'stock_line' => $stock_line,
                                ]);

                                CurrentStockBackup::create($stock_line);

                                $purchase_total += $new_purchase_line->purchase_price_inc_tax * $new_purchase_line->quantity;
                            }

                            $transaction->total_before_tax = $purchase_total;
                            $transaction->final_total = $purchase_total;
                            $transaction->save();

                            Log::info('Updated transaction totals', [
                                'transaction_id' => $transaction->id,
                                'total_before_tax' => $purchase_total,
                                'final_total' => $purchase_total,
                            ]);

                            $this->productUtil->adjustStockOverSelling($transaction);
                        } else {
                            Log::info('No new purchase lines to process for this location');
                        }
                    } else {
                        Log::warning('Invalid location_id', [
                            'location_id' => $location_id,
                        ]);
                    }
                }

                DB::commit();
                Log::info('Transaction committed successfully');
            } else {
                Log::warning('Product not found or stock not enabled', [
                    'product_id' => $product_id,
                    'enable_stock' => $product ? $product->enable_stock : null,
                ]);
            }

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.opening_stock_added_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::emergency('Error in save method', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $output = [
                'success' => 0,
                'msg' => $e->getMessage(),
            ];
            return back()->with('status', $output);
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect('products')->with('status', $output);
    }
}