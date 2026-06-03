<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        //Get the product
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
            //Get Opening Stock Transactions for the product if exists
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

                    //Show only remaining quantity for editing opening stock.
                    $purchase_lines[$purchase_line->variation_id][$k]['quantity'] = $purchase_line->quantity_remaining;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_price'] = $purchase_line->purchase_price;
                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_id'] = $purchase_line->id;
                    $purchase_lines[$purchase_line->variation_id][$k]['exp_date'] = $purchase_line->exp_date;
                    $purchase_lines[$purchase_line->variation_id][$k]['lot_number'] = $purchase_line->lot_number;
                    $purchase_lines[$purchase_line->variation_id][$k]['transaction_date'] = $this->productUtil->format_date($transaction->transaction_date, true);

                    $purchase_lines[$purchase_line->variation_id][$k]['purchase_line_note'] = $transaction->additional_notes;
                    $purchase_lines[$purchase_line->variation_id][$k]['location_id'] = $transaction->location_id;
                    $purchase_lines[$purchase_line->variation_id][$k]['secondary_unit_quantity'] = $purchase_line->secondary_unit_quantity;
                    $purchase_lines[$purchase_line->variation_id][$k]['sub_unit_id'] = $purchase_line->sub_unit_id;
                }
            }

            foreach ($purchase_lines as $v_id => $pls) {
                foreach ($pls as $pl) {
                    $purchases[$pl['location_id']][$v_id][] = $pl;
                }
            }

            $locations = BusinessLocation::forDropdown($business_id);

            //Unset locations where product is not available
            $available_locations = $product->product_locations->pluck('id')->toArray();
            foreach ($locations as $key => $value) {
                if (! in_array($key, $available_locations)) {
                    unset($locations[$key]);
                }
            }

            $enable_expiry = request()->session()->get('business.enable_product_expiry');
            $enable_lot = request()->session()->get('business.enable_lot_number');

            //PosInnova: provide sub-units (base + related) for opening stock entry in cartons/etc.
            $sub_units = $this->productUtil->getSubUnits($business_id, $product->unit_id, true, $product->id);

            //Convert stored base-unit qty back to the originally entered (sub-)unit qty for editing.
            foreach ($purchases as $loc_id => $variations_pl) {
                foreach ($variations_pl as $vid => $rows) {
                    foreach ($rows as $idx => $row) {
                        $sub_id = $row['sub_unit_id'] ?? null;
                        if (!empty($sub_id) && isset($sub_units[$sub_id]) && $sub_units[$sub_id]['multiplier'] > 0) {
                            $purchases[$loc_id][$vid][$idx]['quantity'] = $row['quantity'] / $sub_units[$sub_id]['multiplier'];
                        }
                    }
                }
            }

            if (request()->ajax()) {
                return view('opening_stock.ajax_add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot',
                        'sub_units'
                    ));
            }

            return view('opening_stock.add')
                    ->with(compact(
                        'product',
                        'locations',
                        'purchases',
                        'enable_expiry',
                        'enable_lot',
                        'sub_units'
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
        if (! auth()->user()->can('product.opening_stock')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $opening_stocks = $request->input('stocks');
            $product_id = $request->input('product_id');

            $business_id = $request->session()->get('user.business_id');
            $user_id = $request->session()->get('user.id');

            $product = Product::where('business_id', $business_id)
                                ->where('id', $product_id)
                                ->with(['variations', 'product_tax'])
                                ->first();

            $locations = BusinessLocation::forDropdown($business_id)->toArray();

            //PosInnova: build sub-unit multipliers map so qty entered in cartons/etc converts to base units.
            $sub_units_map = [];
            if (!empty($product) && !empty($product->unit_id)) {
                $sub_units_map = $this->productUtil->getSubUnits($business_id, $product->unit_id, true, $product->id);
            }

            if (! empty($product) && $product->enable_stock == 1) {
                //Get product tax
                $tax_percent = ! empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
                $tax_id = ! empty($product->product_tax->id) ? $product->product_tax->id : null;

                //Get start date for financial year.
                $transaction_date = request()->session()->get('financial_year.start');
                $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

                DB::beginTransaction();

                //$key_os is the location_id
                foreach ($opening_stocks as $location_id => $value) {
                    $new_purchase_lines = [];
                    $edit_purchase_lines = [];
                    $new_transaction_data = [];
                    $edit_transaction_data = [];
                    //Check if valid location
                    if (array_key_exists($location_id, $locations)) {
                        foreach ($value as $vid => $purchase_lines_data) {
                            //create purchase_lines array
                            foreach ($purchase_lines_data as $k => $pl) {
                                $purchase_price = $this->productUtil->num_uf(trim($pl['purchase_price']));
                                $qty_remaining = $this->productUtil->num_uf(trim($pl['quantity']));
                                $secondary_unit_quantity = isset($pl['secondary_unit_quantity']) ? $this->productUtil->num_uf(trim($pl['secondary_unit_quantity'])) : 0;

                                //PosInnova: convert qty & price entered in selected sub-unit to base-unit values.
                                $sub_unit_id = !empty($pl['sub_unit_id']) ? (int) $pl['sub_unit_id'] : null;
                                $multiplier = 1;
                                if (!empty($sub_unit_id) && isset($sub_units_map[$sub_unit_id])) {
                                    $multiplier = $sub_units_map[$sub_unit_id]['multiplier'];
                                }
                                if ($multiplier != 1) {
                                    $qty_remaining = $qty_remaining * $multiplier;
                                    $purchase_price = $purchase_price / $multiplier;
                                }

                                $item_tax = $this->productUtil->calc_percentage($purchase_price, $tax_percent);
                                $purchase_price_inc_tax = $purchase_price + $item_tax;

                                $exp_date = null;
                                if (! empty($pl['exp_date'])) {
                                    $exp_date = $this->productUtil->uf_date($pl['exp_date']);
                                }

                                $lot_number = null;
                                if (! empty($pl['lot_number'])) {
                                    $lot_number = $pl['lot_number'];
                                }

                                $purchase_line_note = ! empty($pl['purchase_line_note']) ? $pl['purchase_line_note'] : null;
                                $transaction_date = ! empty($pl['transaction_date']) ? $this->productUtil->uf_date($pl['transaction_date'], true) : $transaction_date;

                                $purchase_line = null;

                                if (isset($pl['purchase_line_id'])) {
                                    $purchase_line = PurchaseLine::findOrFail($pl['purchase_line_id']);
                                    //Quantity = remaining + used
                                    $qty_remaining = $qty_remaining + $purchase_line->quantity_used;

                                    if ($qty_remaining != 0) {
                                        //Calculate transaction total
                                        $old_qty = $purchase_line->quantity;

                                        $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, $old_qty, null, false);
                                    }
                                } else {
                                    if ($qty_remaining != 0) {

                                        //create newly added purchase lines
                                        $purchase_line = new PurchaseLine();
                                        $purchase_line->product_id = $product->id;
                                        $purchase_line->variation_id = $vid;

                                        $this->productUtil->updateProductQuantity($location_id, $product->id, $vid, $qty_remaining, 0, null, false);
                                    }
                                }
                                if (! is_null($purchase_line)) {
                                    $purchase_line->item_tax = $item_tax;
                                    $purchase_line->tax_id = $tax_id;
                                    $purchase_line->quantity = $qty_remaining;
                                    $purchase_line->pp_without_discount = $purchase_price;
                                    $purchase_line->purchase_price = $purchase_price;
                                    $purchase_line->purchase_price_inc_tax = $purchase_price_inc_tax;
                                    $purchase_line->exp_date = $exp_date;
                                    $purchase_line->lot_number = $lot_number;
                                    $purchase_line->secondary_unit_quantity = $secondary_unit_quantity;
                                    $purchase_line->sub_unit_id = $sub_unit_id;
                                }

                                if (! empty($purchase_line->transaction_id)) {
                                    $edit_purchase_lines[$purchase_line->transaction_id][] = $purchase_line;

                                    $purchase_line->save();

                                    $edit_transaction_data[$purchase_line->transaction_id] = [
                                        'transaction_date' => $transaction_date,
                                        'additional_notes' => $purchase_line_note,
                                    ];
                                } else {
                                    $new_purchase_lines[] = $purchase_line;
                                    $new_transaction_data[] = [
                                        'transaction_date' => $transaction_date,
                                        'additional_notes' => $purchase_line_note,
                                    ];
                                }
                            }
                        }

                        //edit existing transactions & purchase lines
                        $updated_transaction_ids = [];
                        if (! empty($edit_purchase_lines)) {
                            foreach ($edit_purchase_lines as $t_id => $purchase_lines) {
                                $purchase_total = 0;
                                $updated_purchase_line_ids = [];
                                foreach ($purchase_lines as $purchase_line) {
                                    $purchase_total = $purchase_line->purchase_price_inc_tax * $purchase_line->quantity;
                                    $updated_purchase_line_ids[] = $purchase_line->id;
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
                                //unset deleted purchase lines
                                $delete_purchase_line_ids = [];
                                $delete_purchase_lines = null;
                                $delete_purchase_lines = PurchaseLine::where('transaction_id', $transaction->id)
                                            ->whereNotIn('id', $updated_purchase_line_ids)
                                            ->get();

                                if ($delete_purchase_lines->count()) {
                                    foreach ($delete_purchase_lines as $delete_purchase_line) {
                                        $delete_purchase_line_ids[] = $delete_purchase_line->id;

                                        //decrease deleted only if previous status was received
                                        $this->productUtil->decreaseProductQuantity(
                                            $delete_purchase_line->product_id,
                                            $delete_purchase_line->variation_id,
                                            $transaction->location_id,
                                            $delete_purchase_line->quantity
                                        );
                                    }
                                    //Delete deleted purchase lines
                                    PurchaseLine::where('transaction_id', $transaction->id)
                                                ->whereIn('id', $delete_purchase_line_ids)
                                                ->delete();
                                }

                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $transaction, $delete_purchase_lines);

                                //Adjust stock over selling if found
                                $this->productUtil->adjustStockOverSelling($transaction);
                            }
                        }

                        //Delete transaction if all purchase line quantity is 0 (Only if transaction exists)
                        $delete_transactions = Transaction::where('type', 'opening_stock')
                            ->where('business_id', $business_id)
                            ->where('opening_stock_product_id', $product->id)
                            ->where('location_id', $location_id)
                            ->with(['purchase_lines'])
                            ->whereNotIn('id', $updated_transaction_ids)
                            ->get();

                        if (count($delete_transactions) > 0) {
                            foreach ($delete_transactions as $delete_transaction) {
                                $delete_purchase_lines = $delete_transaction->purchase_lines;

                                foreach ($delete_purchase_lines as $delete_purchase_line) {
                                    $this->productUtil->decreaseProductQuantity($product->id, $delete_purchase_line->variation_id, $location_id, $delete_purchase_line->quantity);
                                    $delete_purchase_line->delete();
                                }

                                //Update mapping of purchase & Sell.
                                $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase('received', $delete_transaction, $delete_purchase_lines);

                                $delete_transaction->delete();
                            }
                        }

                        //create transaction & purchase lines
                        if (! empty($new_purchase_lines)) {
                            foreach ($new_purchase_lines as $key => $new_purchase_line) {
                                if (empty($new_purchase_line)) {
                                    continue;
                                }
                                $transaction = Transaction::create(
                                    [
                                        'type' => 'opening_stock',
                                        'opening_stock_product_id' => $product->id,
                                        'status' => 'received',
                                        'business_id' => $business_id,
                                        'transaction_date' => $new_transaction_data[$key]['transaction_date'],
                                        'additional_notes' => $new_transaction_data[$key]['additional_notes'],
                                        'total_before_tax' => $new_purchase_line->purchase_price_inc_tax,
                                        'location_id' => $location_id,
                                        'final_total' => $new_purchase_line->purchase_price_inc_tax * $new_purchase_line->quantity,
                                        'payment_status' => 'paid',
                                        'created_by' => $user_id,
                                    ]
                                );

                                $transaction->purchase_lines()->saveMany([$new_purchase_line]);

                                //Adjust stock over selling if found
                                $this->productUtil->adjustStockOverSelling($transaction);
                            }
                        }
                    }
                }

                DB::commit();
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.opening_stock_added_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
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
