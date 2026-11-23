<?php

namespace App\Utils;

use App\Consultation;
use App\ConsultationCorrection;
use App\ConsultationExam;
use App\ConsultationPrescription;
use App\Department;

/**
 * PosInnova clinic: correcting a FINISHED consultation, with a trace.
 *
 * A finished consultation is a medical record: it is never rewritten in silence. A correction
 * (ConsultationController@saveCorrection) changes the notes, vitals, examination form or the
 * prescription lines not yet given, asks why, and keeps the consultation as it was just before:
 *   snapshot()  the picture of a consultation: its fields, prescription and examination form
 *   diff()      the parts that differ between two pictures (PARTS)
 *   text()      one part of a picture, as words (for the "before / after" page)
 * Drawings replaced by a correction keep their files (the picture refers to them).
 */
class ConsultationCorrections
{
    /** part => the consultation fields it covers */
    const PARTS = [
        'type' => ['consultation_type', 'specialty'],
        'complaint' => ['chief_complaint'],
        'history' => ['history_of_complaint'],
        'vitals' => ['vitals_weight', 'vitals_height', 'vitals_bp_systolic', 'vitals_bp_diastolic', 'vitals_temperature', 'vitals_pulse', 'vitals_resp_rate', 'vitals_spo2'],
        'exam_notes' => ['examination_notes'],
        'diagnosis' => ['diagnosis'],
        'differential' => ['differential_diagnosis'],
        'plan' => ['management_plan'],
        'review' => ['review_date', 'review_notes'],
        'referral' => ['referral_to', 'admission_requested'],
    ];

    /** Every part, in the order of the form. */
    const ORDER = ['type', 'complaint', 'history', 'vitals', 'exam', 'exam_notes', 'diagnosis', 'differential', 'prescription', 'plan', 'review', 'referral'];

    const RX_FIELDS = ['strength', 'route', 'dosage', 'frequency', 'duration', 'notes'];

    /** The consultation as it is in the database now. */
    public static function snapshot(Consultation $consultation)
    {
        $c = Consultation::where('id', $consultation->id)->first();
        $fields = [];
        foreach (self::PARTS as $names) {
            foreach ($names as $f) {
                $v = $c->getAttribute($f);
                if ($f == 'review_date') {
                    $v = $v ? $v->format('Y-m-d') : null;
                } elseif ($f == 'admission_requested') {
                    $v = $v ? 1 : 0;
                } elseif (str_starts_with($f, 'vitals_')) {
                    $v = ($v === null || $v === '') ? null : (float) $v;
                } elseif (is_string($v)) {
                    $v = trim(str_replace(["\r\n", "\r"], "\n", $v));
                    $v = $v === '' ? null : $v;
                }
                $fields[$f] = $v;
            }
        }
        $fields['referral_name'] = $c->referral_to ? Department::where('id', $c->referral_to)->value('name') : null;

        $rx = [];
        foreach (ConsultationPrescription::where('consultation_id', $c->id)->with(['product', 'variation'])->orderBy('id')->get() as $line) {
            $row = ['id' => $line->id, 'name' => $line->displayName(), 'free_text' => $line->isFreeText(), 'quantity' => (float) $line->quantity,
                'outside' => $line->closeReasonKey() == 'prescribed_outside'];
            foreach (self::RX_FIELDS as $f) {
                $row[$f] = ($line->{$f} === null || trim((string) $line->{$f}) === '') ? null : trim(str_replace(["\r\n", "\r"], "\n", (string) $line->{$f}));
            }
            $rx[] = $row;
        }

        $exam = ConsultationExam::where('consultation_id', $c->id)->first();

        return [
            'fields' => $fields,
            'rx' => $rx,
            'exam' => $exam ? ['template_id' => (int) $exam->template_id, 'template_name' => $exam->template_name, 'blocks' => $exam->blocks ?: [], 'values' => $exam->values ?: []] : null,
        ];
    }

    /** The parts that are not the same in two pictures, in the order of the form. */
    public static function diff(array $before, array $after)
    {
        $changed = [];
        foreach (self::PARTS as $part => $names) {
            foreach ($names as $f) {
                if (($before['fields'][$f] ?? null) !== ($after['fields'][$f] ?? null)) {
                    $changed[] = $part;
                    break;
                }
            }
        }
        if (json_encode($before['rx'] ?? []) !== json_encode($after['rx'] ?? [])) {
            $changed[] = 'prescription';
        }
        $ex = fn ($s) => empty($s['exam']) ? 'null' : json_encode([(int) ($s['exam']['template_id'] ?? 0), $s['exam']['values'] ?? []]);
        if ($ex($before) !== $ex($after)) {
            $changed[] = 'exam';
        }

        return array_values(array_intersect(self::ORDER, $changed));
    }

    public static function label($part)
    {
        return __('clinic.corr_part_'.$part);
    }

    /** The parts of a correction, as words: "Diagnosis, Prescription". */
    public static function labels($parts)
    {
        return implode(', ', array_map(fn ($p) => self::label($p), (array) $parts));
    }

    /** One part of a picture, as text (several lines allowed); '' when there is nothing. */
    public static function text(array $snap, $part)
    {
        $f = $snap['fields'] ?? [];
        $num = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        switch ($part) {
            case 'type':
                return trim(__('lang_v1.consultation_type_'.($f['consultation_type'] ?: 'general')).(! empty($f['specialty']) ? ' · '.$f['specialty'] : ''));
            case 'vitals':
                $out = [];
                foreach (['vitals_weight' => __('lang_v1.weight_kg'), 'vitals_height' => __('lang_v1.height_cm')] as $k => $lbl) {
                    if (($f[$k] ?? null) !== null) {
                        $out[] = $lbl.': '.$num($f[$k]);
                    }
                }
                if (($f['vitals_bp_systolic'] ?? null) !== null || ($f['vitals_bp_diastolic'] ?? null) !== null) {
                    $out[] = __('lang_v1.blood_pressure').': '.($num($f['vitals_bp_systolic'] ?? null) ?? '?').'/'.($num($f['vitals_bp_diastolic'] ?? null) ?? '?');
                }
                foreach (['vitals_temperature' => __('lang_v1.temperature_c'), 'vitals_pulse' => __('lang_v1.pulse_bpm'), 'vitals_resp_rate' => __('clinic.cons_resp_rate'), 'vitals_spo2' => __('lang_v1.spo2')] as $k => $lbl) {
                    if (($f[$k] ?? null) !== null) {
                        $out[] = $lbl.': '.$num($f[$k]);
                    }
                }

                return implode("\n", $out);
            case 'review':
                $d = ! empty($f['review_date']) ? (new Util())->format_date($f['review_date']) : '';

                return trim($d.(! empty($f['review_notes']) ? ($d ? ' · ' : '').$f['review_notes'] : ''));
            case 'referral':
                return implode("\n", array_filter([$f['referral_name'] ?? null, ! empty($f['admission_requested']) ? __('lang_v1.admission_requested') : null]));
            case 'prescription':
                return implode("\n", array_map(fn ($r) => self::rxLine($r), $snap['rx'] ?? []));
            case 'exam':
                return empty($snap['exam']) ? '' : (string) ($snap['exam']['template_name'] ?? '');
            default:
                $name = self::PARTS[$part][0] ?? null;

                return $name ? (string) ($f[$name] ?? '') : '';
        }
    }

    /** A prescription line of a picture, on one line. */
    public static function rxLine(array $r)
    {
        $bits = array_filter([$r['strength'] ?? null, $r['route'] ?? null, $r['dosage'] ?? null, $r['frequency'] ?? null, $r['duration'] ?? null], fn ($v) => $v !== null && $v !== '');
        $qty = rtrim(rtrim(number_format((float) ($r['quantity'] ?? 0), 2, '.', ''), '0'), '.');
        $line = ($r['name'] ?? '').(empty($bits) ? '' : ' — '.implode(', ', $bits)).' · '.__('sale.qty').' '.$qty;
        if (! empty($r['outside'])) {
            $line .= ' · '.__('clinic.rx_buys_outside');
        }
        if (! empty($r['notes'])) {
            $line .= ' ('.str_replace("\n", ' ', $r['notes']).')';
        }

        return $line;
    }

    /** The corrections of a consultation, oldest first, each with its number (->no). */
    public static function of($consultation_id)
    {
        $list = ConsultationCorrection::where('consultation_id', $consultation_id)->with('correctedBy')->orderBy('id')->get();
        foreach ($list as $i => $corr) {
            $corr->no = $i + 1;
        }

        return $list;
    }
}
