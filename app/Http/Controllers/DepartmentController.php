<?php

namespace App\Http\Controllers;

use App\Department;
use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DepartmentController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index()
    {
        if (! auth()->user()->can('department.view') && ! auth()->user()->can('department.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $departments = Department::where('business_id', $business_id)
                ->select(['id', 'name', 'code', 'hod_name', 'description', 'is_active']);

            return Datatables::of($departments)
                ->editColumn('is_active', function ($row) {
                    return $row->is_active
                        ? '<span class="label label-success">'.__('lang_v1.active').'</span>'
                        : '<span class="label label-danger">'.__('lang_v1.inactive').'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '';
                    if (auth()->user()->can('department.update')) {
                        $html .= '<button data-href="'.action([\App\Http\Controllers\DepartmentController::class, 'edit'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_department_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>';
                    }
                    if (auth()->user()->can('department.delete')) {
                        $html .= ' <button data-href="'.action([\App\Http\Controllers\DepartmentController::class, 'destroy'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_department_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';
                    }
                    return $html;
                })
                ->rawColumns(['is_active', 'action'])
                ->make(true);
        }

        return view('department.index');
    }

    public function create()
    {
        if (! auth()->user()->can('department.create')) {
            abort(403, 'Unauthorized action.');
        }

        $quick_add = ! empty(request()->input('quick_add'));

        $business_id = request()->session()->get('user.business_id');
        $business_locations = \App\BusinessLocation::forDropdown($business_id, true);

        return view('department.create')->with(compact('quick_add', 'business_locations'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->can('department.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $validated = $request->validate([
                'name' => 'required|string|max:191|unique:departments,name,NULL,id,business_id,'.$business_id.',deleted_at,NULL',
                'code' => 'nullable|string|max:50',
                'hod_name' => 'nullable|string|max:191',
                'description' => 'nullable|string',
            ]);

            $validated['business_id'] = $business_id;
            $validated['created_by'] = $request->session()->get('user.id');
            $validated['is_active'] = $request->has('is_active') ? 1 : 1;
            //Hospital module: optional location mapping + department type.
            $validated['location_id'] = $request->input('location_id') ?: null;
            $validated['department_type'] = $request->input('department_type') ?: 'other';

            $department = Department::create($validated);

            $output = [
                'success' => true,
                'data' => $department,
                'msg' => __('lang_v1.department_added_success'),
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => collect($e->errors())->flatten()->first(),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function edit($id)
    {
        if (! auth()->user()->can('department.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $department = Department::where('business_id', $business_id)->findOrFail($id);
            $business_locations = \App\BusinessLocation::forDropdown($business_id, true);

            return view('department.edit')->with(compact('department', 'business_locations'));
        }
    }

    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('department.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $department = Department::where('business_id', $business_id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:191|unique:departments,name,'.$id.',id,business_id,'.$business_id.',deleted_at,NULL',
                'code' => 'nullable|string|max:50',
                'hod_name' => 'nullable|string|max:191',
                'description' => 'nullable|string',
            ]);

            $department->name = $validated['name'];
            $department->code = $validated['code'] ?? null;
            $department->hod_name = $validated['hod_name'] ?? null;
            $department->description = $validated['description'] ?? null;
            $department->is_active = $request->has('is_active') ? 1 : 0;
            //Hospital module: optional location mapping + department type.
            $department->location_id = $request->input('location_id') ?: null;
            $department->department_type = $request->input('department_type') ?: 'other';
            $department->save();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.department_updated_success'),
            ];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => collect($e->errors())->flatten()->first(),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    public function destroy($id)
    {
        if (! auth()->user()->can('department.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->user()->business_id;
            $department = Department::where('business_id', $business_id)->findOrFail($id);

            // Block delete if referenced by any department_issue transaction
            $in_use = Transaction::where('business_id', $business_id)
                ->where('type', 'department_issue')
                ->where('department_id', $id)
                ->exists();

            if ($in_use) {
                return [
                    'success' => false,
                    'msg' => __('lang_v1.department_in_use_cannot_delete'),
                ];
            }

            $department->delete();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.department_deleted_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }
}
