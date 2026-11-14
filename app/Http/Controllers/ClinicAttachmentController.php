<?php

namespace App\Http\Controllers;

use App\ClinicAttachment;
use App\Utils\ClinicRequestPayment;
use Illuminate\Http\Request;

/**
 * PosInnova clinic: photos, scans and PDFs on lab and imaging requests (see App\ClinicAttachment).
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

        return $u->can($type == 'lab_request' ? 'lab_result.enter' : 'imaging_result.enter') || $u->can('consultation.create');
    }

    private static function canView($type)
    {
        $u = auth()->user();
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

        return $class::where('business_id', $business_id)->findOrFail($id);
    }

    public function store(Request $request, $type, $id)
    {
        $this->hospitalOn();
        abort_unless(self::canUpload($type), 403, 'Unauthorized action.');
        $owner = $this->owner($type, $id);

        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:'.ClinicAttachment::MAX_KB.'|mimes:'.implode(',', ClinicAttachment::EXTENSIONS),
            'caption' => 'nullable|string|max:191',
        ], [
            'files.*.mimes' => __('clinic.att_bad_type'),
            'files.*.max' => __('clinic.att_too_big', ['mb' => ClinicAttachment::MAX_KB / 1024]),
        ]);

        if (! is_dir(ClinicAttachment::dir())) {
            mkdir(ClinicAttachment::dir(), 0775, true);
        }
        $saved = [];
        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension());
            if (! in_array($ext, ClinicAttachment::EXTENSIONS)) {
                continue;
            }
            $name = $type.'_'.$owner->id.'_'.bin2hex(random_bytes(12)).'.'.$ext;
            $mime = $file->getMimeType();
            $size = $file->getSize();
            $file->move(ClinicAttachment::dir(), $name);
            $saved[] = ClinicAttachment::create([
                'business_id' => $owner->business_id,
                'attachable_type' => $type,
                'attachable_id' => $owner->id,
                'contact_id' => $owner->contact_id,
                'file_name' => $name,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'mime' => $mime,
                'size' => $size,
                'caption' => $request->input('caption') ?: null,
                'uploaded_by' => auth()->id(),
            ]);
        }

        $msg = trans_choice('clinic.att_uploaded', count($saved), ['n' => count($saved)]);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => count($saved) > 0, 'msg' => $msg,
                'html' => view('clinic.partials.attachments', ['type' => $type, 'owner' => $owner->load('attachments.uploadedBy'), 'inner' => true])->render()]);
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

        return $this->send($att);
    }

    public function destroy(Request $request, $id)
    {
        $this->hospitalOn();
        $business_id = $request->session()->get('user.business_id');
        $att = ClinicAttachment::where('business_id', $business_id)->findOrFail($id);
        abort_unless(self::canUpload($att->attachable_type), 403, 'Unauthorized action.');
        $owner = $att->owner();
        if (is_file($att->path())) {
            @unlink($att->path());
        }
        $att->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'msg' => __('clinic.att_deleted'),
                'html' => $owner ? view('clinic.partials.attachments', ['type' => $att->attachable_type, 'owner' => $owner->load('attachments.uploadedBy'), 'inner' => true])->render() : '']);
        }

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.att_deleted')]);
    }

    /** Patient portal: only the patient's own files, and only once the request is paid. */
    public function portalShow($id)
    {
        $patient = auth()->guard('patient')->user();
        abort_unless($patient, 403);
        $att = ClinicAttachment::where('business_id', $patient->business_id)->where('contact_id', $patient->id)->findOrFail($id);
        $owner = $att->owner();
        abort_unless($owner && (int) $owner->contact_id === (int) $patient->id, 404);
        abort_unless(ClinicRequestPayment::releasedToPatient(ClinicRequestPayment::of($owner)), 403);

        return $this->send($att);
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
