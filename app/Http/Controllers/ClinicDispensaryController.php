<?php

namespace App\Http\Controllers;

use App\Consultation;
use App\ConsultationPrescription;
use App\Transaction;
use App\Utils\Dispensing;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The dispensary (clinic phase 3): prescriptions from finished consultations
 * that still have lines waiting. "Dispense" opens the POS with what is still
 * waiting; the sale records what was actually given, line by line. A line the
 * dispensary will not fill (out of stock, declined, substituted) is closed with
 * a reason so it leaves the list; it can be reopened.
 */
class ClinicDispensaryController extends Controller
{
    const CLOSE_REASONS = ['out_of_stock', 'patient_declined', 'substituted', 'bought_elsewhere', 'other'];

    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    private function authorize_dispensary()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('prescription.dispense')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->authorize_dispensary();
        $business_id = $request->session()->get('user.business_id');

        $show = $request->input('show') == 'done' ? 'done' : 'waiting';
        $waiting_sql = Dispensing::waitingCountSql();

        $query = Consultation::forBusiness($business_id)
            ->where('consultations.status', 'final')
            ->join('contacts as c', 'c.id', '=', 'consultations.contact_id')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'consultations.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'consultations.consulted_by')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('consultation_prescriptions as x')->whereColumn('x.consultation_id', 'consultations.id');
            })
            ->select(
                'consultations.id', 'consultations.contact_id', 'consultations.visit_id', 'consultations.ref_no', 'consultations.created_at', 'consultations.updated_at',
                'c.name as patient_name', 'c.mobile', 'pd.file_number',
                DB::raw("TRIM(CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as doctor_name"),
                DB::raw('(SELECT COUNT(*) FROM consultation_prescriptions y WHERE y.consultation_id = consultations.id) as line_count'),
                DB::raw($waiting_sql.' as waiting_count'),
                DB::raw("(SELECT COUNT(*) FROM transactions t WHERE t.consultation_id = consultations.id AND t.type = 'sell' AND t.status = 'final') as sale_count")
            );

        if ($show == 'waiting') {
            // oldest waiting first; nothing older than the look-back window
            $days = (int) $request->input('days', 30);
            $query->whereRaw($waiting_sql.' > 0')
                ->where('consultations.updated_at', '>=', now()->subDays(max(1, min($days, 365))))
                ->orderBy('consultations.updated_at');
        } else {
            $query->whereRaw($waiting_sql.' = 0')
                ->where('consultations.updated_at', '>=', now()->subDays(7))
                ->orderByDesc('consultations.updated_at');
        }

        $rows = $query->limit(200)->get();

        $data = compact('rows', 'show');
        if ($request->ajax() || $request->boolean('partial')) {
            return view('clinic.dispensary.partials.list', $data);
        }

        return view('clinic.dispensary.index', $data);
    }

    public function show($id)
    {
        $this->authorize_dispensary();
        $business_id = request()->session()->get('user.business_id');

        $consultation = Consultation::forBusiness($business_id)
            ->with(['patient.patientDetails', 'consultedBy', 'prescriptions.product', 'prescriptions.variation', 'prescriptions.closedBy'])
            ->findOrFail($id);
        Dispensing::attach($consultation->prescriptions);

        $sales = Transaction::where('business_id', $business_id)
            ->where('consultation_id', $consultation->id)
            ->where('type', 'sell')
            ->orderBy('transaction_date')
            ->get(['id', 'invoice_no', 'transaction_date', 'status', 'payment_status', 'final_total', 'sub_status']);

        $reasons = collect(self::CLOSE_REASONS)->mapWithKeys(fn ($r) => [$r => __('clinic.rx_reason_'.$r)])->all();
        $waiting = $consultation->prescriptions->sum(fn ($rx) => $rx->outstanding_qty > 0 ? 1 : 0);

        return view('clinic.dispensary.show', compact('consultation', 'sales', 'reasons', 'waiting'));
    }

    /**
     * Close a waiting line the dispensary will not fill.
     */
    public function close(Request $request, $rx_id)
    {
        $this->authorize_dispensary();
        $rx = $this->findLine($rx_id);

        $reason = $request->input('reason');
        if (! in_array($reason, self::CLOSE_REASONS)) {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.rx_reason_required')]);
        }
        $note = trim((string) $request->input('note'));
        if ($reason == 'other' && $note === '') {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.rx_reason_note_required')]);
        }

        Dispensing::attach([$rx]);
        if ($rx->closed_at || $rx->outstanding_qty <= 0) {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.rx_not_waiting')]);
        }

        $rx->closed_at = now();
        $rx->closed_by = auth()->user()->id;
        $rx->close_reason = mb_substr($reason.($note !== '' ? ': '.$note : ''), 0, 191);
        $rx->save();
        Dispensing::refreshFlags($rx->consultation_id, auth()->user()->id);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.rx_closed')]);
    }

    public function reopen($rx_id)
    {
        $this->authorize_dispensary();
        $rx = $this->findLine($rx_id);

        if ($rx->closed_at) {
            $rx->closed_at = null;
            $rx->closed_by = null;
            $rx->close_reason = null;
            $rx->save();
            Dispensing::refreshFlags($rx->consultation_id, auth()->user()->id);
        }

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.rx_reopened')]);
    }

    private function findLine($rx_id)
    {
        $business_id = request()->session()->get('user.business_id');

        return ConsultationPrescription::whereHas('consultation', function ($q) use ($business_id) {
            $q->where('business_id', $business_id);
        })->findOrFail($rx_id);
    }
}
