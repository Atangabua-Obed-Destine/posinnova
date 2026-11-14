<?php

namespace App\Http\Controllers;

use App\LabTest;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Laboratory test catalogue (hospital module). Modal-based CRUD mirroring the
 * department catalogue.
 */
class LabTestController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    private function checkHospitalEnabled()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $tests = LabTest::where('business_id', $business_id)
                ->select(['id', 'name', 'code', 'category', 'normal_range', 'unit', 'fee', 'turnaround_hours', 'is_active']);

            return DataTables::of($tests)
                ->editColumn('fee', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">'.$row->fee.'</span>';
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active
                        ? '<span class="label label-success">'.__('lang_v1.active').'</span>'
                        : '<span class="label label-danger">'.__('lang_v1.inactive').'</span>';
                })
                ->editColumn('turnaround_hours', function ($row) {
                    return $row->turnaround_hours ? $row->turnaround_hours.'h' : '-';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="'.action([\App\Http\Controllers\LabTestController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_lab_test_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>';
                    $html .= ' <button data-href="'.action([\App\Http\Controllers\LabTestController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_lab_test_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';

                    return $html;
                })
                ->rawColumns(['fee', 'is_active', 'action'])
                ->make(true);
        }

        return view('lab.test_catalogue');
    }

    public function create()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        return view('lab.test_create');
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'code' => 'nullable|string|max:50',
                'category' => 'nullable|string|max:100',
                'normal_range' => 'nullable|string|max:191',
                'unit' => 'nullable|string|max:50',
                'fee' => 'nullable|numeric|min:0',
                'turnaround_hours' => 'nullable|integer|min:0',
                'result_options' => 'nullable|string|max:500',
            ]);

            $validated['business_id'] = $business_id;
            $validated['created_by'] = $request->session()->get('user.id');
            $validated['fee'] = $request->filled('fee') ? $request->input('fee') : 0;
            $validated['is_active'] = 1;

            LabTest::create($validated);

            $output = ['success' => true, 'msg' => __('lang_v1.lab_test_added_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function edit($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $lab_test = LabTest::where('business_id', $business_id)->findOrFail($id);

            return view('lab.test_edit')->with(compact('lab_test'));
        }
    }

    public function update(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $lab_test = LabTest::where('business_id', $business_id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'code' => 'nullable|string|max:50',
                'category' => 'nullable|string|max:100',
                'normal_range' => 'nullable|string|max:191',
                'unit' => 'nullable|string|max:50',
                'fee' => 'nullable|numeric|min:0',
                'turnaround_hours' => 'nullable|integer|min:0',
                'result_options' => 'nullable|string|max:500',
            ]);

            $lab_test->fill([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'category' => $validated['category'] ?? null,
                'normal_range' => $validated['normal_range'] ?? null,
                'unit' => $validated['unit'] ?? null,
                'fee' => $request->filled('fee') ? $request->input('fee') : 0,
                'turnaround_hours' => $request->filled('turnaround_hours') ? $request->input('turnaround_hours') : null,
                'result_options' => $validated['result_options'] ?? null,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);
            $lab_test->save();

            $output = ['success' => true, 'msg' => __('lang_v1.lab_test_updated_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function destroy($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_test.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $lab_test = LabTest::where('business_id', $business_id)->findOrFail($id);

            //Block delete if referenced by any lab request line.
            $in_use = \App\LabRequestLine::where('lab_test_id', $id)->exists();
            if ($in_use) {
                return ['success' => false, 'msg' => __('lang_v1.lab_test_in_use_cannot_delete')];
            }

            $lab_test->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.lab_test_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }
}
