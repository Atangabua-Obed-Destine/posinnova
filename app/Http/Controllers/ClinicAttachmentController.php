<?php

namespace App\Http\Controllers;

use App\ClinicAttachment;
use App\Utils\ClinicRequestPayment;
use Illuminate\Http\Request;

/**
 * PosInnova clinic: photos, scans, PDFs and videos on lab and imaging requests, and on consultations
 * (endoscope / camera pictures) - see App\ClinicAttachment. A consultation not saved yet takes files
 * under a draft token (id 0); they are given to the consultation when it is saved.
 */
class ClinicAttachmentController extends Controller
{
    private function hospitalOn()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    /** Who may add files to a request: the lab / imaging staff entering results, and the doctors. */
    public static function canUpload($type)
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }

        if ($type == 'consultation') {
            return $u->can('consultation.create');
        }

        return $u->can($type == 'lab_request' ? 'lab_result.enter' : 'imaging_result.enter') || $u->can('consultation.create');
    }

    private static function canView($type)
    {
        $u = auth()->user();
        if ($type == 'consultation') {
            return $u->can('consultation.view_own') || $u->can('consultation.view_all') || $u->can('consultation.create');
        }
        $perms = $type == 'lab_request' ? ['lab_request.view', 'lab_result.view', 'lab_result.enter'] : ['imaging_request.view', 'imaging_result.view', 'imaging_result.enter'];
        $perms = array_merge($perms, ['consultation.view_own', 'consultation.view_all']);
        foreach ($perms as $p) {
            if ($u->can($p)) {
                return true;
            }
        }

        return false;
    }

    private function owner($type, $id)
    {
        $class = ClinicAttachment::TYPES[$type] ?? null;
        abort_unless($class, 404);
        $business_id = request()->session()->get('user.business_id');

        $owner = $class::where('business_id', $business_id)->findOrFail($id);
        if ($type == 'consultation' && ! auth()->user()->can('consultation.view_all')) {
            abort_unless($owner->consulted_by == auth()->id(), 403, 'Unauthorized action.');
        }

        return $owner;
    }

    /** A consultation's draft token: letters and digits only. */
    private static function token($t)
    {
        $t = (string) $t;

        return preg_match('/^[A-Za-z0-9]{20,40}$/', $t) ? $t : null;
    }

    /** The list of files, as the partial shows it. */
    private function listHtml($type, $owner, $token = null, $visit_id = null)
    {
        return view('clinic.partials.attachments', ['type' => $type, 'owner' => $owner ? $owner->load('attachments.uploadedBy') : null,
            'draft_token' => $token, 'visit_id' => $visit_id, 'inner' => true])->render();
    }

    public function store(Request $request, $type, $id)
    {
        $this->hospitalOn();
        abort_unless(self::canUpload($type), 403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');
        $token = null;
        $visit = null;
        if ($type == 'consultation' && (int) $id === 0) {
            // a consultation not saved yet: kept under its token until it is
            $token = self::token($request->input('token'));
            abort_unless($token, 422);
            $visit = \App\Visit::forBusiness($business_id)->findOrFail((int) $request->input('visit_id'));
            $owner = null;
        } else {
            $owner = $this->owner($type, $id);
        }

        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:'.ClinicAttachment::maxVideoKb().'|mimes:'.implode(',', ClinicAttachment::EXTENSIONS),
            'caption' => 'nullable|string|max:191',
        ], [
            'files.*.mimes' => __('clinic.att_bad_type'),
            'files.*.max' => __('clinic.att_too_big', ['mb' => round(ClinicAttachment::maxVideoKb() / 1024)]),
        ]);

        if (! is_dir(ClinicAttachment::dir())) {
            mkdir(ClinicAttachment::dir(), 0775, true);
        }
        $saved = [];
        $too_big = 0;
        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension());
            if (! in_array($ext, ClinicAttachment::EXTENSIONS)) {
                continue;
            }
            // photos and PDFs stay small; only videos may be big
            if (! ClinicAttachment::isVideoExtension($ext) && $file->getSize() > ClinicAttachment::MAX_KB * 1024) {
                $too_big++;

                continue;
            }
            $name = $type.'_'.($owner ? $owner->id : 'd').'_'.bin2hex(random_bytes(12)).'.'.$ext;
            $mime = $file->getMimeType();
            $size = $file->getSize();
            $file->move(ClinicAttachment::dir(), $name);
            $saved[] = ClinicAttachment::create([
                'business_id' => $business_id,
                'attachable_type' => $type,
                'attachable_id' => $owner ? $owner->id : 0,
                'draft_token' => $token,
                'contact_id' => $owner ? $owner->contact_id : $visit->contact_id,
                'file_name' => $name,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'mime' => $mime,
                'size' => $size,
                'caption' => $request->input('caption') ?: null,
                'uploaded_by' => auth()->id(),
            ]);
        }

        $msg = trans_choice('clinic.att_uploaded', count($saved), ['n' => count($saved)]);
        if ($too_big) {
            $msg .= ' '.__('clinic.att_too_big', ['mb' => ClinicAttachment::MAX_KB / 1024]);
        }
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => count($saved) > 0, 'msg' => $msg,
                'html' => $this->listHtml($type, $owner, $token, $visit ? $visit->id : null)]);
        }

        return redirect()->back()->with('status', ['success' => count($saved) > 0, 'msg' => $msg]);
    }

    /** The file itself, shown in the browser (or downloaded with ?download=1). */
    public function show($id)
    {
        $this->hospitalOn();
        $business_id = request()->session()->get('user.business_id');
        $att = ClinicAttachment::where('business_id', $business_id)->findOrFail($id);
        abort_unless(self::canView($att->attachable_type), 403, 'Unauthorized action.');
        if ($att->draft_token) {
            abort_unless($att->uploaded_by == auth()->id(), 403, 'Unauthorized action.');
        } elseif ($att->attachable_type == 'consultation' && ! auth()->user()->can('consultation.view_all')) {
            // their own consultations, or (writing one) the patient's earlier ones
            $by = \App\Consultation::where('id', $att->attachable_id)->value('consulted_by');
            abort_unless($by == auth()->id() || auth()->user()->can('consultation.create'), 403, 'Unauthorized action.');
        }

        return $this->send($att);
    }

    public function destroy(Request $request, $id)
    {
        $this->hospitalOn();
        $business_id = $request->session()->get('user.business_id');
        $att = ClinicAttachment::where('business_id', $business_id)->findOrFail($id);
        abort_unless(self::canUpload($att->attachable_type), 403, 'Unauthorized action.');
        $token = $att->draft_token;
        if ($token) {
            abort_unless($att->uploaded_by == auth()->id(), 403, 'Unauthorized action.');
            $owner = null;
        } else {
            $owner = $att->owner();
            if ($att->attachable_type == 'consultation' && $owner && ! auth()->user()->can('consultation.view_all')) {
                abort_unless($owner->consulted_by == auth()->id(), 403, 'Unauthorized action.');
            }
        }
        if (is_file($att->path())) {
            @unlink($att->path());
        }
        $att->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'msg' => __('clinic.att_deleted'),
                'html' => ($owner || $token) ? $this->listHtml($att->attachable_type, $owner, $token, $request->input('visit_id')) : '']);
        }

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.att_deleted')]);
    }

    /** Patient portal: only the patient's own files, and only once the request is paid. */
    public function portalShow($id)
    {
        $patient = auth()->guard('patient')->user();
        abort_unless($patient, 403);
        $att = ClinicAttachment::where('business_id', $patient->business_id)->where('contact_id', $patient->id)
            ->whereIn('attachable_type', ['lab_request', 'imaging_request'])->whereNull('draft_token')->findOrFail($id);
        $owner = $att->owner();
        abort_unless($owner && (int) $owner->contact_id === (int) $patient->id, 404);
        abort_unless(ClinicRequestPayment::releasedToPatient(ClinicRequestPayment::of($owner)), 403);

        return $this->send($att);
    }

    /**
     * Files added while writing a new consultation go to it once it is saved.
     */
    public static function claimDraft($token, \App\Consultation $consultation)
    {
        $token = self::token($token);
        if (! $token) {
            return;
        }
        ClinicAttachment::where('business_id', $consultation->business_id)->where('attachable_type', 'consultation')
            ->where('attachable_id', 0)->where('draft_token', $token)->where('uploaded_by', auth()->id())
            ->update(['attachable_id' => $consultation->id, 'contact_id' => $consultation->contact_id, 'draft_token' => null]);
    }

    private function send(ClinicAttachment $att)
    {
        abort_unless(is_file($att->path()), 404);
        $safe = str_replace(['"', "\r", "\n"], '', $att->original_name);
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response()->file($att->path(), [
            'Content-Type' => $att->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.$safe.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
