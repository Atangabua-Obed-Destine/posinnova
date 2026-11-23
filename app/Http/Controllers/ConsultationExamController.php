<?php

namespace App\Http\Controllers;

use App\ClinicExamTemplate;
use App\Consultation;
use App\ConsultationExam;
use App\Utils\ClinicExamTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: the examination form (ENT, eye...) inside a consultation.
 *  - form:    the empty form of a template, loaded into the consultation when the doctor picks it
 *  - drawing: what the doctor drew on a picture (staff only)
 *  - save:    called by ConsultationController when the consultation is saved
 */
class ConsultationExamController extends Controller
{
    private function hospitalOn()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    public function form(Request $request)
    {
        $this->hospitalOn();
        abort_unless(auth()->user()->can('consultation.create'), 403, 'Unauthorized action.');
        $business_id = $request->session()->get('user.business_id');
        $template = ClinicExamTemplate::where('business_id', $business_id)->findOrFail((int) $request->input('template_id'));

        return view('consultation.exam.form', ['blocks' => $template->blocks ?: [], 'values' => [], 'exam' => null]);
    }

    public function drawing($id, $block)
    {
        $this->hospitalOn();
        $u = auth()->user();
        abort_unless($u->can('consultation.view_own') || $u->can('consultation.view_all') || $u->can('consultation.create'), 403, 'Unauthorized action.');
        $exam = ConsultationExam::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);
        if (! $u->can('consultation.view_all')) {
            $by = Consultation::where('id', $exam->consultation_id)->value('consulted_by');
            // a doctor seeing only their own consultations still sees the patient's earlier drawings while consulting
            abort_unless($by == $u->id || $u->can('consultation.create'), 403, 'Unauthorized action.');
        }
        $f = $exam->drawing($block);
        abort_unless($f, 404);

        return response()->file(ConsultationExam::drawingDir().DIRECTORY_SEPARATOR.$f, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Saves the examination form sent with the consultation form (exam[...]):
     *   exam[present]      the form's box was on the page (otherwise nothing is touched)
     *   exam[template_id]  the form chosen (empty: none; a form already filled in is then removed)
     *   exam[v][block]     answers; [R] / [L] for a block asked for both sides; [] for several answers
     *   exam[o][block]     an answer typed for "other"
     *   exam[draw][block]  a PNG (data URL) of what was drawn, or "clear"
     * Runs inside the consultation's transaction; replaced drawing files are deleted once it commits
     * (unless $keep_files: a correction to a finished consultation keeps the earlier version readable).
     */
    public static function save(Consultation $consultation, $input, $user_id, $keep_files = false)
    {
        $input = is_array($input) ? $input : [];
        if (empty($input['present'])) {
            return;
        }
        $existing = ConsultationExam::where('consultation_id', $consultation->id)->first();
        $template_id = (int) ($input['template_id'] ?? 0);
        $old_files = [];

        if (! $template_id) {
            if ($existing) {
                $old_files = array_values($existing->values['_draw'] ?? []);
                $existing->delete();
                if (! $keep_files) {
                    self::deleteAfterCommit($old_files);
                }
            }

            return;
        }

        $same = $existing && (int) $existing->template_id === $template_id;
        if ($same) {
            $blocks = $existing->blocks ?: [];
            $name = $existing->template_name;
        } else {
            $template = ClinicExamTemplate::where('business_id', $consultation->business_id)->find($template_id);
            if (! $template) {
                return;
            }
            $blocks = $template->blocks ?: [];
            $name = $template->name;
            if ($existing) {
                $old_files = array_values($existing->values['_draw'] ?? []);
            }
        }

        // "other" answers join the picked ones
        $v = is_array($input['v'] ?? null) ? $input['v'] : [];
        $o = is_array($input['o'] ?? null) ? $input['o'] : [];
        foreach ($blocks as $b) {
            if (! in_array($b['type'], ['choice', 'multi']) || ! isset($o[$b['id']])) {
                continue;
            }
            $sides = ! empty($b['sides']) ? ClinicExamTemplates::SIDES : [null];
            foreach ($sides as $s) {
                $other = $s ? ($o[$b['id']][$s] ?? null) : $o[$b['id']];
                if (! is_scalar($other) || trim((string) $other) === '') {
                    continue;
                }
                $other = trim((string) $other);
                if ($b['type'] == 'choice') {
                    if ($s) {
                        $v[$b['id']] = is_array($v[$b['id']] ?? null) ? $v[$b['id']] : [];
                        $v[$b['id']][$s] = $other;
                    } else {
                        $v[$b['id']] = $other;
                    }
                } else {
                    if ($s) {
                        $v[$b['id']] = is_array($v[$b['id']] ?? null) ? $v[$b['id']] : [];
                        $cur = (array) ($v[$b['id']][$s] ?? []);
                        $v[$b['id']][$s] = array_merge($cur, [$other]);
                    } else {
                        $v[$b['id']] = array_merge((array) ($v[$b['id']] ?? []), [$other]);
                    }
                }
            }
        }
        $values = ClinicExamTemplates::cleanValues($blocks, $v);

        // drawings
        $draw = $same ? ($existing->values['_draw'] ?? []) : [];
        $sent = is_array($input['draw'] ?? null) ? $input['draw'] : [];
        $dir = ConsultationExam::drawingDir();
        foreach ($blocks as $b) {
            if ($b['type'] != 'drawing' || ! isset($sent[$b['id']]) || ! is_string($sent[$b['id']]) || $sent[$b['id']] === '') {
                continue;
            }
            $d = $sent[$b['id']];
            if ($d === 'clear') {
                if (! empty($draw[$b['id']])) {
                    $old_files[] = $draw[$b['id']];
                }
                unset($draw[$b['id']]);

                continue;
            }
            $png = self::decodePng($d);
            if ($png === null) {
                continue;
            }
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $file = 'c'.$consultation->id.'_'.$b['id'].'_'.bin2hex(random_bytes(8)).'.png';
            file_put_contents($dir.DIRECTORY_SEPARATOR.$file, $png);
            if (! empty($draw[$b['id']])) {
                $old_files[] = $draw[$b['id']];
            }
            $draw[$b['id']] = $file;
        }
        if (! empty($draw)) {
            $values['_draw'] = $draw;
        }

        if ($existing && ! $same) {
            $existing->delete();
            $existing = null;
        }
        if ($existing) {
            $existing->values = $values;
            $existing->updated_by = $user_id;
            $existing->save();
        } else {
            ConsultationExam::create([
                'business_id' => $consultation->business_id,
                'consultation_id' => $consultation->id,
                'contact_id' => $consultation->contact_id,
                'template_id' => $template_id,
                'template_name' => $name,
                'blocks' => $blocks,
                'values' => $values,
                'created_by' => $user_id,
                'updated_by' => $user_id,
            ]);
        }
        // $keep_files: a correction to a finished consultation; the version kept still shows its drawings
        if (! $keep_files) {
            self::deleteAfterCommit($old_files);
        }
    }

    /** A PNG sent as a data URL, checked: a real PNG, of a sensible size. */
    private static function decodePng($data)
    {
        if (! str_starts_with($data, 'data:image/png;base64,') || strlen($data) > 8 * 1024 * 1024) {
            return null;
        }
        $bin = base64_decode(substr($data, 22), true);
        if ($bin === false || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $size = @getimagesizefromstring($bin);
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] > 2400 || $size[1] > 2400) {
            return null;
        }

        return $bin;
    }

    private static function deleteAfterCommit(array $files)
    {
        $files = array_filter($files);
        if (empty($files)) {
            return;
        }
        DB::afterCommit(function () use ($files) {
            foreach ($files as $f) {
                $p = ConsultationExam::drawingDir().DIRECTORY_SEPARATOR.basename($f);
                if (is_file($p)) {
                    @unlink($p);
                }
            }
        });
    }
}
