<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova clinic: a photo, scan or PDF on a lab request or an imaging request.
 *
 * Files live in storage/app/clinic_attachments (never under public/): they are served by
 * ClinicAttachmentController to staff allowed to see the request, and to the patient in the portal once
 * the request is paid (the same rule as the results).
 */
class ClinicAttachment extends Model
{
    protected $guarded = ['id'];

    /** attachable_type => model */
    const TYPES = ['lab_request' => LabRequest::class, 'imaging_request' => ImagingRequest::class];

    /** What can be uploaded: photos and PDFs. */
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    const MAX_KB = 10240;

    public static function dir()
    {
        return storage_path('app/clinic_attachments');
    }

    public function path()
    {
        return self::dir().DIRECTORY_SEPARATOR.$this->file_name;
    }

    public function isImage()
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isPdf()
    {
        return $this->mime === 'application/pdf';
    }

    /** Staff address. */
    public function url($download = false)
    {
        return action([\App\Http\Controllers\ClinicAttachmentController::class, 'show'], [$this->id]).($download ? '?download=1' : '');
    }

    /** Patient-portal address. */
    public function portalUrl()
    {
        return route('portal.attachment', [$this->id]);
    }

    public function owner()
    {
        $class = self::TYPES[$this->attachable_type] ?? null;

        return $class ? $class::find($this->attachable_id) : null;
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sizeLabel()
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : max(1, round($kb)).' KB';
    }
}
