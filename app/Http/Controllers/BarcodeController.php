<?php

namespace App\Http\Controllers;

use App\Barcode;
use Datatables;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $barcodes = Barcode::where('business_id', $business_id)
                        ->select(['name', 'description', 'id', 'is_default']);

            return Datatables::of($barcodes)
                ->addColumn(
                    'action',
                    '<a href="{{action(\'App\Http\Controllers\BarcodeController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a>
                        &nbsp;
                        <button type="button" data-href="{{action(\'App\Http\Controllers\BarcodeController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_barcode_button" @if($is_default) disabled @endif><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>&nbsp;
                        @if($is_default)
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent" disabled><i class="fa fa-check-square-o" aria-hidden="true"></i> @lang("barcode.default")</button>
                        @else
                            <button class="btn btn-xs btn-info set_default" data-href="{{action(\'App\Http\Controllers\BarcodeController@setDefault\', [$id])}}">@lang("barcode.set_as_default")</button>
                        @endif
                        '
                )
                ->editColumn('name', function ($row) {
                    if ($row->is_default == 1) {
                        return $row->name.' &nbsp; <span class="label label-success">'.__('barcode.default').'</span>';
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('is_default')
                ->rawColumns([0, 2])
                ->make(false);
        }

        return view('barcode.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        return view('barcode.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'width', 'height', 'top_margin',
                'left_margin', 'row_distance', 'col_distance',
                'stickers_in_one_row', 'paper_width', 'barcode_width_percent', 'barcode_height_percent', ]);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;

            if (! empty($request->input('is_default'))) {
                //get_default
                $default = Barcode::where('business_id', $business_id)
                                ->where('is_default', 1)
                                ->update(['is_default' => 0]);
                $input['is_default'] = 1;
            }
            if (! empty($request->input('is_continuous'))) {
                $input['is_continuous'] = 1;
                $input['stickers_in_one_sheet'] = 28;
            } else {
                $input['stickers_in_one_sheet'] = $request->input('stickers_in_one_sheet');
                $input['paper_height'] = $request->input('paper_height');
            }

            $barcode = Barcode::create($input);
            $output = ['success' => 1,
                'msg' => __('barcode.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('barcodes')->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Barcode  $barcode
     * @return \Illuminate\Http\Response
     */
    public function show(Barcode $barcode)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $barcode = Barcode::where('business_id', $business_id)->find($id);

        return view('barcode.edit')
            ->with(compact('barcode'));
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
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'width', 'height', 'top_margin',
                'left_margin', 'row_distance', 'col_distance',
                'stickers_in_one_row', 'paper_width', 'barcode_width_percent', 'barcode_height_percent', ]);

            if (! empty($request->input('is_continuous'))) {
                $input['is_continuous'] = 1;
                $input['stickers_in_one_sheet'] = 28;
                $input['paper_height'] = 0;
            } else {
                $input['is_continuous'] = 0;
                $input['stickers_in_one_sheet'] = $request->input('stickers_in_one_sheet');
                $input['paper_height'] = $request->input('paper_height');
            }

            $barcode = Barcode::where('id', $id)->update($input);

            $output = ['success' => 1,
                'msg' => __('barcode.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('barcodes')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $barcode = Barcode::find($id);
                if ($barcode->is_default != 1) {
                    $barcode->delete();
                    $output = ['success' => true,
                        'msg' => __('barcode.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('messages.something_went_wrong'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Sets barcode setting as default
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function setDefault($id)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                //get_default
                $business_id = request()->session()->get('user.business_id');
                $default = Barcode::where('business_id', $business_id)
                                ->where('is_default', 1)
                                 ->update(['is_default' => 0]);

                $barcode = Barcode::find($id);
                $barcode->is_default = 1;
                $barcode->save();

                $output = ['success' => true,
                    'msg' => __('barcode.default_set_success'),
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

    /**
     * Preview the barcode setting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request)
    {
        if (! auth()->user()->can('barcode_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'width', 'height', 'top_margin',
                'left_margin', 'row_distance', 'col_distance',
                'stickers_in_one_row', 'paper_width', 'barcode_width_percent', 'barcode_height_percent', 'is_continuous', 'stickers_in_one_sheet', 'paper_height']);
            
            $barcode_details = (object) $input;

            if (! empty($request->input('is_continuous'))) {
                $barcode_details->is_continuous = 1;
                $barcode_details->stickers_in_one_sheet = 28;
                $barcode_details->paper_height = 0;
            } else {
                $barcode_details->is_continuous = 0;
            }

            $barcode_details->stickers_in_one_sheet = $barcode_details->is_continuous ? $barcode_details->stickers_in_one_row : $barcode_details->stickers_in_one_sheet;
            $barcode_details->paper_height = $barcode_details->is_continuous ? $barcode_details->height : $barcode_details->paper_height;
            if ($barcode_details->stickers_in_one_row == 1) {
                $barcode_details->col_distance = 0;
                $barcode_details->row_distance = 0;
            }

            $business_name = $request->session()->get('business.name');

            $dummy_product = new \stdClass();
            $dummy_product->product_actual_name = 'Example Product';
            $dummy_product->lot_number = 'LOT-1234';
            $dummy_product->is_dummy = 0;
            $dummy_product->product_variation_name = 'Size';
            $dummy_product->variation_name = 'M';
            $dummy_product->sell_price_inc_tax = 120.00;
            $dummy_product->default_sell_price = 100.00;
            $dummy_product->exp_date = date('Y-m-d', strtotime('+1 year'));
            $dummy_product->packing_date = date('Y-m-d');
            $dummy_product->sub_sku = 'SKU-001';
            $dummy_product->barcode_type = 'C128';

            $page_products = [];
            for ($i = 0; $i < $barcode_details->stickers_in_one_sheet; $i++) {
                $page_products[] = clone $dummy_product;
            }

            $margin_top = $barcode_details->is_continuous ? 0 : $barcode_details->top_margin * 1;
            $margin_left = $barcode_details->is_continuous ? 0 : $barcode_details->left_margin * 1;
            $paper_width = $barcode_details->paper_width * 1;
            $paper_height = $barcode_details->paper_height * 1;

            $factor = (($barcode_details->width / $barcode_details->height)) / ($barcode_details->is_continuous ? 2 : 4);

            $print = [
                'business_name' => 0, 'business_name_size' => 15,
                'name' => 1, 'name_size' => 15,
                'price' => 0, 'price_size' => 15, 'price_type' => 'inclusive',
                'variations' => 0, 'variations_size' => 15,
                'exp_date' => 0, 'exp_date_size' => 12,
                'packing_date' => 0, 'packing_date_size' => 12,
                'lot_number' => 0, 'lot_number_size' => 12
            ];

            $is_first = true;
            $is_last = true;

            $output = view('labels.partials.preview_2')
                        ->with(compact('print', 'page_products', 'business_name', 'barcode_details', 'margin_top', 'margin_left', 'paper_width', 'paper_height', 'is_first', 'is_last', 'factor'))->render();
            print_r($output);
            print_r('<script>window.print()</script>');
            exit;

        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            die(__('lang_v1.barcode_label_error'));
        }
    }
}
