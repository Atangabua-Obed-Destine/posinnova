<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova clinic: a photo, scan, PDF or video on a lab request, an imaging request or a consultation
 * (endoscope / camera pictures). A file added to a consultation not saved yet carries a draft_token
 * (attachable_id 0) until the consultation is saved.
 *
 * Files live in storage/app/clinic_attachments (never under public/): they are served by
 * ClinicAttachmentController to staff allowed to see the request, and to the patient in the portal once
 * the request is paid (the same rule as the results).
 */
class ClinicAttachment extends Model
{
    protected $guarded = ['id'];

    /** attachable_type => model */
    const TYPES = ['lab_request' => LabRequest::class, 'imaging_request' => ImagingRequest::class, 'consultation' => Consultation::class];

    /** What can be uploaded: photos and PDFs, and short videos (endoscopy). */
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4', 'webm', 'mov', 'm4v'];

    const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    /** Photos and PDFs. */
    const MAX_KB = 10240;

    /** Videos: up to 200 MB, or less when the server takes less. */
    public static function maxVideoKb()
    {
        $bytes = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '-1' || $v === '0') {
                return PHP_INT_MAX;
            }
            $n = (float) $v;
            switch (strtolower(substr($v, -1))) {
                case 'g': $n *= 1024;
                    // no break
                case 'm': $n *= 1024;
                    // no break
                case 'k': $n *= 1024;
            }

            return (int) $n;
        };
        $limit = min($bytes(ini_get('upload_max_filesize')), $bytes(ini_get('post_max_size')), 200 * 1024 * 1024);

        return max(self::MAX_KB, (int) floor($limit / 1024));
    }

    public static function isVideoExtension($ext)
    {
        return in_array(strtolower((string) $ext), self::VIDEO_EXTENSIONS);
    }

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

    public function isVideo()
    {
        return str_starts_with((string) $this->mime, 'video/') || self::isVideoExtension(pathinfo($this->file_name, PATHINFO_EXTENSION));
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

        return ($class && $this->attachable_id) ? $class::find($this->attachable_id) : null;
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
