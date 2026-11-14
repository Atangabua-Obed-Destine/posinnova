<?php

namespace App\Http\Controllers;

use App\Utils\ClinicOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: the doctors' lists (see App\Utils\ClinicOptions).
 *  - quickAdd: a doctor adds a missing entry from the consultation form (or the lab / imaging pages)
 *  - index / store / update / destroy / move: Clinic setup > Doctors' lists
 */
class ClinicOptionController extends Controller
{
    private function hospitalOn()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    private function authorizeManage()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('clinic_settings.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    /** Anyone writing clinical notes may add a missing entry. */
    public function quickAdd(Request $request)
    {
        $this->hospitalOn();
        $u = auth()->user();
        if (! ($u->can('consultation.create') || $u->can('clinic_settings.manage') || $u->can('lab_result.enter') || $u->can('imaging_result.enter'))) {
            abort(403, 'Unauthorized action.');
        }
        $key = $request->input('list');
        if (! ClinicOptions::isList($key)) {
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')], 422);
        }
        $business_id = $request->session()->get('user.business_id');
        $value = ClinicOptions::add($business_id, $key, $request->input('value'), $u->id);
        if ($value === null) {
            return response()->json(['success' => false, 'msg' => __('clinic.dl_value_required')], 422);
        }

        return response()->json(['success' => true, 'value' => $value, 'msg' => __('clinic.dl_added', ['value' => $value])]);
    }

    public function index(Request $request)
    {
        $this->authorizeManage();
        $business_id = $request->session()->get('user.business_id');
        $active = ClinicOptions::isList($request->input('list')) ? $request->input('list') : array_key_first(ClinicOptions::LISTS);
        $lists = [];
        foreach (ClinicOptions::LISTS as $key => $label) {
            $lists[$key] = ['label' => __($label), 'rows' => ClinicOptions::rows($business_id, $key)];
        }

        return view('clinic.doctor_lists', compact('lists', 'active'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $key = $request->input('list');
        abort_unless(ClinicOptions::isList($key), 404);
        $business_id = $request->session()->get('user.business_id');
        $added = 0;
        // one entry per line: a whole list can be pasted at once
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('values')) as $v) {
            if (ClinicOptions::clean($v) !== '' && ClinicOptions::add($business_id, $key, $v, auth()->id()) !== null) {
                $added++;
            }
        }

        return redirect()->action([self::class, 'index'], ['list' => $key])
            ->with('status', ['success' => $added > 0, 'msg' => $added > 0 ? __('clinic.dl_saved') : __('clinic.dl_value_required')]);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeManage();
        $row = $this->row($id);
        $value = ClinicOptions::clean($request->input('value'));
        if ($value === '') {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.dl_value_required')]);
        }
        DB::table('clinic_options')->where('id', $row->id)->update(['value' => $value, 'updated_at' => now()]);

        return redirect()->action([self::class, 'index'], ['list' => $row->list_key])->with('status', ['success' => true, 'msg' => __('clinic.dl_saved')]);
    }

    public function destroy($id)
    {
        $this->authorizeManage();
        $row = $this->row($id);
        DB::table('clinic_options')->where('id', $row->id)->delete();

        return redirect()->action([self::class, 'index'], ['list' => $row->list_key])->with('status', ['success' => true, 'msg' => __('clinic.dl_removed')]);
    }

    /** Up / down one place. */
    public function move(Request $request, $id)
    {
        $this->authorizeManage();
        $row = $this->row($id);
        $rows = ClinicOptions::rows($row->business_id, $row->list_key)->pluck('id')->all();
        $i = array_search($row->id, $rows);
        $j = $request->input('dir') == 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($rows[$j])) {
            [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
            DB::transaction(function () use ($rows) {
                foreach ($rows as $pos => $rid) {
                    DB::table('clinic_options')->where('id', $rid)->update(['sort_order' => $pos + 1]);
                }
            });
        }

        return redirect()->action([self::class, 'index'], ['list' => $row->list_key]);
    }

    private function row($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $row = DB::table('clinic_options')->where('business_id', $business_id)->where('id', $id)->where('list_key', '!=', '_seeded')->first();
        abort_unless($row, 404);

        return $row;
    }
}
