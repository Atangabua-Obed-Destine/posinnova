<?php

namespace App\Utils;

use App\ClinicExamTemplate;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: examination forms per specialty (ENT, eye...).
 *
 * A template is an ordered list of blocks, built by drag and drop under Clinic setup > Examination forms:
 *   heading   a section title ("Ear", "Nose"...)
 *   drawing   a picture the doctor draws on (mouse, finger, pen); or a blank page
 *   choice    one answer, picked with one click (e.g. Rinne: positive / negative)
 *   multi     several answers, one click each (e.g. eardrum: perforated, dull...)
 *   text      a short answer;  notes  a longer one;  number  a measure with its unit
 * choice / multi / text / notes / number can be asked "for the right and the left" (sides), and
 * choice / multi can take an answer that is not in the list (other).
 *
 * When a consultation uses a template, a copy of its blocks is kept with the consultation
 * (consultation_exams), so changing the template later never changes what was recorded.
 */
class ClinicExamTemplates
{
    const TYPES = ['heading', 'drawing', 'choice', 'multi', 'text', 'notes', 'number'];

    /** width => columns out of 12 */
    const WIDTHS = ['full' => 12, 'three_quarters' => 9, 'two_thirds' => 8, 'half' => 6, 'third' => 4, 'quarter' => 3];

    const SIDES = ['R', 'L'];

    const MAX_BLOCKS = 200;

    /** Pictures that come with the system (public/img/clinic_diagrams). */
    const LIBRARY = [
        'img/clinic_diagrams/ent_ear_right.png' => 'clinic.ex_img_ear_right',
        'img/clinic_diagrams/ent_eardrum_right.png' => 'clinic.ex_img_eardrum_right',
        'img/clinic_diagrams/ent_eardrum_left.png' => 'clinic.ex_img_eardrum_left',
        'img/clinic_diagrams/ent_ear_left.png' => 'clinic.ex_img_ear_left',
        'img/clinic_diagrams/ent_nose.png' => 'clinic.ex_img_nose',
        'img/clinic_diagrams/ent_oral_cavity.png' => 'clinic.ex_img_oral_cavity',
        'img/clinic_diagrams/ent_neck.png' => 'clinic.ex_img_neck',
        'img/clinic_diagrams/ent_larynx.png' => 'clinic.ex_img_larynx',
        'img/clinic_diagrams/eye_front_right.svg' => 'clinic.ex_img_eye_right',
        'img/clinic_diagrams/eye_front_left.svg' => 'clinic.ex_img_eye_left',
        'img/clinic_diagrams/eye_fundus_right.svg' => 'clinic.ex_img_fundus_right',
        'img/clinic_diagrams/eye_fundus_left.svg' => 'clinic.ex_img_fundus_left',
    ];

    /** Pictures a clinic uploads for its forms (public: they are drawings, not patient data). */
    public static function uploadDir($business_id)
    {
        return public_path('uploads/clinic_diagrams/'.(int) $business_id);
    }

    public static function uploadedImages($business_id)
    {
        $dir = self::uploadDir($business_id);
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE) ?: [] as $f) {
            $out[] = 'uploads/clinic_diagrams/'.(int) $business_id.'/'.basename($f);
        }
        sort($out);

        return $out;
    }

    /** A picture a template may use: one of the system's, or one uploaded by this business. */
    public static function validImage($path, $business_id)
    {
        $path = (string) $path;
        if (isset(self::LIBRARY[$path])) {
            return true;
        }
        if (! preg_match('#^uploads/clinic_diagrams/(\d+)/[a-z0-9_\-]+\.(png|jpe?g|webp|gif)$#', $path, $m)) {
            return false;
        }

        return (int) $m[1] === (int) $business_id && is_file(public_path($path));
    }

    private static function clean($v, $max = 191)
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $v)), 0, $max);
    }

    /**
     * Blocks as sent by the builder (or written in the defaults), made safe and complete.
     */
    public static function normalizeBlocks($raw, $business_id)
    {
        $raw = is_array($raw) ? array_values($raw) : [];
        $out = [];
        $ids = [];
        foreach (array_slice($raw, 0, self::MAX_BLOCKS) as $b) {
            if (! is_array($b) || ! in_array($b['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $type = $b['type'];
            $id = (string) ($b['id'] ?? '');
            if (! preg_match('/^[a-z0-9_]{1,40}$/', $id) || isset($ids[$id])) {
                do {
                    $id = 'b'.bin2hex(random_bytes(4));
                } while (isset($ids[$id]));
            }
            $ids[$id] = 1;
            $block = ['id' => $id, 'type' => $type, 'label' => self::clean($b['label'] ?? '')];

            if ($type == 'heading') {
                $block['width'] = 'full';
            } else {
                $default_w = $type == 'drawing' ? 'half' : 'full';
                $block['width'] = isset(self::WIDTHS[$b['width'] ?? '']) ? $b['width'] : $default_w;
            }
            $help = self::clean($b['help'] ?? '');
            if ($help !== '') {
                $block['help'] = $help;
            }
            if (in_array($type, ['choice', 'multi', 'text', 'notes', 'number'])) {
                $block['sides'] = ! empty($b['sides']);
            }
            if (in_array($type, ['choice', 'multi'])) {
                $opts = $b['options'] ?? [];
                if (is_string($opts)) {
                    $opts = preg_split('/\r\n|\r|\n/', $opts);
                }
                $seen = [];
                $block['options'] = [];
                foreach ((array) $opts as $o) {
                    $o = self::clean($o);
                    if ($o === '' || isset($seen[mb_strtolower($o)])) {
                        continue;
                    }
                    $seen[mb_strtolower($o)] = 1;
                    $block['options'][] = $o;
                    if (count($block['options']) >= 80) {
                        break;
                    }
                }
                $block['other'] = ! array_key_exists('other', $b) || ! empty($b['other']);
            }
            if ($type == 'number') {
                $block['unit'] = self::clean($b['unit'] ?? '', 20);
            }
            if ($type == 'drawing') {
                $img = (string) ($b['image'] ?? '');
                $block['image'] = ($img !== '' && self::validImage($img, $business_id)) ? $img : null;
            }
            $out[] = $block;
        }

        return $out;
    }

    /**
     * What the doctor filled in, kept only for the form's own blocks: [block id => value].
     * A block asked for both sides keeps ['R' => ..., 'L' => ...].
     */
    public static function cleanValues(array $blocks, $input)
    {
        $input = is_array($input) ? $input : [];
        $out = [];
        foreach ($blocks as $b) {
            if (in_array($b['type'], ['heading', 'drawing'])) {
                continue;
            }
            $raw = $input[$b['id']] ?? null;
            if (! empty($b['sides'])) {
                $v = [];
                foreach (self::SIDES as $s) {
                    $one = self::cleanOne($b, is_array($raw) ? ($raw[$s] ?? null) : null);
                    if ($one !== null) {
                        $v[$s] = $one;
                    }
                }
                $v = empty($v) ? null : $v;
            } else {
                $v = self::cleanOne($b, $raw);
            }
            if ($v !== null) {
                $out[$b['id']] = $v;
            }
        }

        return $out;
    }

    private static function cleanOne(array $b, $raw)
    {
        if ($b['type'] == 'multi') {
            $vals = [];
            foreach ((array) $raw as $x) {
                if (! is_scalar($x)) {
                    continue;
                }
                $x = self::clean($x);
                if ($x !== '' && ! in_array(mb_strtolower($x), array_map('mb_strtolower', $vals))) {
                    $vals[] = $x;
                }
            }

            return empty($vals) ? null : array_slice($vals, 0, 60);
        }
        if (! is_scalar($raw)) {
            return null;
        }
        if ($b['type'] == 'number') {
            $x = str_replace(',', '.', trim((string) $raw));

            return is_numeric($x) ? mb_substr($x, 0, 20) : null;
        }
        if ($b['type'] == 'notes') {
            $x = trim(str_replace("\r\n", "\n", (string) $raw));

            return $x === '' ? null : mb_substr($x, 0, 5000);
        }
        $x = self::clean($raw, $b['type'] == 'text' ? 500 : 191);

        return $x === '' ? null : $x;
    }

    /** True when a value (one side or both) has something in it. */
    public static function filled($v)
    {
        if (is_array($v)) {
            foreach ($v as $x) {
                if (self::filled($x)) {
                    return true;
                }
            }

            return false;
        }

        return $v !== null && $v !== '';
    }

    /** A value as text: "a, b" for several answers. */
    public static function text($v)
    {
        return is_array($v) ? implode(', ', $v) : (string) $v;
    }

    /* ------------------------------------------------------------------ ready-made forms */

    /** The forms that come with the system: key => lang key of the name. */
    const READY_MADE = ['ent' => 'clinic.ex_ready_ent', 'eye' => 'clinic.ex_ready_eye'];

    /**
     * A ready-made form in the current language: ['name', 'specialties', 'blocks'].
     */
    public static function readyMade($key)
    {
        $fr = str_starts_with((string) app()->getLocale(), 'fr');
        $L = fn ($en, $fr_text) => $fr ? $fr_text : $en;
        $n = 0;
        $B = function ($type, $label, array $extra = []) use (&$n) {
            $n++;

            return array_merge(['id' => 'b'.$n, 'type' => $type, 'label' => $label], $extra);
        };
        $img = fn ($f) => 'img/clinic_diagrams/'.$f;

        if ($key == 'ent') {
            $blocks = [
                $B('heading', $L('Ear', 'Oreilles')),
                $B('drawing', $L('Right ear', 'Oreille droite'), ['width' => 'quarter', 'image' => $img('ent_ear_right.png')]),
                $B('drawing', $L('Right eardrum', 'Tympan droit'), ['width' => 'quarter', 'image' => $img('ent_eardrum_right.png')]),
                $B('drawing', $L('Left eardrum', 'Tympan gauche'), ['width' => 'quarter', 'image' => $img('ent_eardrum_left.png')]),
                $B('drawing', $L('Left ear', 'Oreille gauche'), ['width' => 'quarter', 'image' => $img('ent_ear_left.png')]),
                $B('multi', $L('Pinna (auricle)', 'Pavillon'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Swelling', 'Tenderness', 'Discharge', 'Skin lesion', 'Pre-auricular sinus', 'Deformity'],
                    ['Normal', 'Tuméfaction', 'Douleur', 'Écoulement', 'Lésion cutanée', 'Fistule pré-auriculaire', 'Malformation'])]),
                $B('multi', $L('Ear canal', 'Conduit auditif externe'), ['sides' => true, 'options' => $L(
                    ['Clear', 'Wax', 'Discharge', 'Oedema / swelling', 'Foreign body', 'Fungal debris', 'Furuncle', 'Exostosis'],
                    ['Libre', 'Cérumen', 'Écoulement', 'Œdème', 'Corps étranger', 'Débris mycosiques', 'Furoncle', 'Exostose'])]),
                $B('multi', $L('Eardrum (tympanic membrane)', 'Tympan'), ['sides' => true, 'options' => $L(
                    ['Intact', 'Central perforation', 'Marginal perforation', 'Attic perforation', 'Retracted', 'Bulging', 'Dull', 'Red / congested',
                        'Fluid level / bubbles', 'Grommet in place', 'Scarred (tympanosclerosis)', 'Not seen'],
                    ['Intact', 'Perforation centrale', 'Perforation marginale', 'Perforation attique', 'Rétracté', 'Bombé', 'Terne', 'Congestif',
                        'Niveau liquide / bulles', 'Aérateur en place', 'Tympanosclérose', 'Non vu'])]),
                $B('choice', $L('Rinne test', 'Test de Rinne'), ['sides' => true, 'width' => 'half', 'options' => $L(['Positive', 'Negative'], ['Positif', 'Négatif'])]),
                $B('choice', $L('Weber test', 'Test de Weber'), ['width' => 'half', 'options' => $L(
                    ['Central', 'Lateralised to the right', 'Lateralised to the left'], ['Indifférent', 'Latéralisé à droite', 'Latéralisé à gauche'])]),
                $B('notes', $L('Ear notes', 'Notes oreilles')),

                $B('heading', $L('Nose', 'Nez')),
                $B('drawing', $L('Nose', 'Nez'), ['width' => 'third', 'image' => $img('ent_nose.png')]),
                $B('multi', $L('Septum', 'Cloison nasale'), ['width' => 'two_thirds', 'options' => $L(
                    ['Central', 'Deviated to the right', 'Deviated to the left', 'Spur', 'Perforation', 'Haematoma'],
                    ['Médiane', 'Déviée à droite', 'Déviée à gauche', 'Éperon', 'Perforation', 'Hématome'])]),
                $B('multi', $L('Turbinates', 'Cornets'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Hypertrophied', 'Pale', 'Congested', 'Bluish'], ['Normaux', 'Hypertrophiés', 'Pâles', 'Congestifs', 'Violacés'])]),
                $B('multi', $L('Nasal cavity', 'Fosse nasale'), ['sides' => true, 'options' => $L(
                    ['Clear', 'Mucoid discharge', 'Purulent discharge', 'Blood / clots', 'Crusts', 'Polyps', 'Mass', 'Foreign body'],
                    ['Libre', 'Sécrétions muqueuses', 'Sécrétions purulentes', 'Sang / caillots', 'Croûtes', 'Polypes', 'Masse', 'Corps étranger'])]),
                $B('choice', $L('Nasal airflow', 'Perméabilité nasale'), ['sides' => true, 'options' => $L(['Good', 'Reduced', 'Absent'], ['Bonne', 'Réduite', 'Nulle'])]),
                $B('notes', $L('Nose and sinus notes', 'Notes nez et sinus')),

                $B('heading', $L('Oral cavity and pharynx', 'Cavité buccale et pharynx')),
                $B('drawing', $L('Oral cavity', 'Cavité buccale'), ['width' => 'third', 'image' => $img('ent_oral_cavity.png')]),
                $B('multi', $L('Lips, teeth and gums', 'Lèvres, dents et gencives'), ['width' => 'two_thirds', 'options' => $L(
                    ['Normal', 'Dental caries', 'Gum disease', 'Ulcer', 'Poor oral hygiene'],
                    ['Normal', 'Caries dentaires', 'Gingivite', 'Ulcération', 'Mauvaise hygiène bucco-dentaire'])]),
                $B('multi', $L('Tongue and floor of mouth', 'Langue et plancher buccal'), ['options' => $L(
                    ['Normal', 'Coated', 'Ulcer', 'Mass', 'Tongue-tie'], ['Normal', 'Saburrale', 'Ulcération', 'Masse', 'Frein court'])]),
                $B('choice', $L('Tonsil size', 'Taille des amygdales'), ['sides' => true, 'options' => $L(
                    ['Grade 0 (removed)', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4'], ['Grade 0 (opérées)', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4'])]),
                $B('multi', $L('Tonsils', 'Amygdales'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Congested', 'Exudate', 'Crypts / debris', 'Peritonsillar swelling'],
                    ['Normales', 'Congestives', 'Exsudat', 'Cryptes / caséum', 'Tuméfaction péri-amygdalienne'])]),
                $B('multi', $L('Palate and uvula', 'Palais et luette'), ['options' => $L(
                    ['Normal', 'Deviated uvula', 'Long uvula', 'Cleft', 'Ulcer'], ['Normal', 'Luette déviée', 'Luette longue', 'Fente', 'Ulcération'])]),
                $B('multi', $L('Back of the throat', 'Paroi pharyngée postérieure'), ['options' => $L(
                    ['Normal', 'Congested', 'Granular', 'Post-nasal drip', 'Swelling'], ['Normale', 'Congestive', 'Granuleuse', 'Jetage postérieur', 'Bombement'])]),
                $B('notes', $L('Mouth and throat notes', 'Notes bouche et gorge')),

                $B('heading', $L('Neck', 'Cou')),
                $B('drawing', $L('Neck', 'Cou'), ['width' => 'third', 'image' => $img('ent_neck.png')]),
                $B('multi', $L('Lymph nodes', 'Adénopathies'), ['width' => 'two_thirds', 'sides' => true, 'options' => $L(
                    ['None palpable', 'Level I', 'Level II', 'Level III', 'Level IV', 'Level V', 'Tender', 'Firm', 'Hard', 'Matted', 'Mobile', 'Fixed'],
                    ['Aucune palpable', 'Secteur I', 'Secteur II', 'Secteur III', 'Secteur IV', 'Secteur V', 'Douloureuses', 'Fermes', 'Dures', 'En paquet', 'Mobiles', 'Fixées'])]),
                $B('multi', $L('Thyroid', 'Thyroïde'), ['options' => $L(
                    ['Normal', 'Diffuse enlargement', 'Nodule', 'Moves with swallowing', 'Tender'],
                    ['Normale', 'Goitre diffus', 'Nodule', 'Mobile à la déglutition', 'Douloureuse'])]),
                $B('multi', $L('Other neck findings', 'Autres signes cervicaux'), ['options' => $L(
                    ['None', 'Salivary gland swelling', 'Thyroglossal cyst', 'Branchial cyst', 'Abscess'],
                    ['Aucun', 'Tuméfaction salivaire', 'Kyste du tractus thyréoglosse', 'Kyste branchial', 'Abcès'])]),
                $B('notes', $L('Neck notes', 'Notes cou')),

                $B('heading', $L('Larynx', 'Larynx')),
                $B('drawing', $L('Larynx', 'Larynx'), ['width' => 'third', 'image' => $img('ent_larynx.png')]),
                $B('choice', $L('How it was examined', 'Mode d\'examen'), ['width' => 'two_thirds', 'options' => $L(
                    ['Indirect laryngoscopy (mirror)', 'Flexible nasendoscopy', 'Rigid endoscopy', 'Not done'],
                    ['Laryngoscopie indirecte (miroir)', 'Nasofibroscopie', 'Endoscopie rigide', 'Non fait'])]),
                $B('multi', $L('Epiglottis and supraglottis', 'Épiglotte et étage sus-glottique'), ['options' => $L(
                    ['Normal', 'Oedema', 'Congested', 'Mass'], ['Normal', 'Œdème', 'Congestif', 'Masse'])]),
                $B('choice', $L('Vocal cord movement', 'Mobilité des cordes vocales'), ['sides' => true, 'options' => $L(
                    ['Mobile', 'Reduced', 'Fixed'], ['Mobile', 'Diminuée', 'Fixée'])]),
                $B('multi', $L('Vocal cords', 'Cordes vocales'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Congested', 'Nodule', 'Polyp', 'Cyst', 'Leukoplakia', 'Mass', 'Oedema'],
                    ['Normale', 'Congestive', 'Nodule', 'Polype', 'Kyste', 'Leucoplasie', 'Masse', 'Œdème'])]),
                $B('multi', $L('Voice and airway', 'Voix et respiration'), ['options' => $L(
                    ['Normal', 'Hoarse', 'Breathy', 'Stridor'], ['Normale', 'Dysphonie', 'Voix soufflée', 'Stridor'])]),
                $B('notes', $L('Larynx notes', 'Notes larynx')),
            ];

            return [
                'name' => $L('ENT clinical assessment', 'Examen ORL'),
                'specialties' => ['ENT (ear, nose & throat)', 'ENT', 'ORL', 'Oto-rhino-laryngologie'],
                'blocks' => $blocks,
            ];
        }

        if ($key == 'eye') {
            $va = ['6/6', '6/9', '6/12', '6/18', '6/24', '6/36', '6/60', '3/60', $L('Counts fingers', 'Compte les doigts'),
                $L('Hand movements', 'Voit bouger la main'), $L('Perceives light', 'Perception lumineuse'), $L('No light perception', 'Pas de perception lumineuse')];
            $blocks = [
                $B('heading', $L('Vision and eye pressure', 'Acuité visuelle et tension oculaire')),
                $B('choice', $L('Visual acuity (without glasses)', 'Acuité visuelle sans correction'), ['sides' => true, 'width' => 'half', 'options' => $va]),
                $B('choice', $L('Visual acuity (pinhole / with glasses)', 'Acuité visuelle au trou sténopéique / avec correction'), ['sides' => true, 'width' => 'half', 'options' => $va]),
                $B('number', $L('Eye pressure (IOP)', 'Tension oculaire (PIO)'), ['sides' => true, 'width' => 'half', 'unit' => 'mmHg']),
                $B('text', $L('Refraction / glasses', 'Réfraction / correction'), ['sides' => true, 'width' => 'half']),

                $B('heading', $L('Front of the eye', 'Segment antérieur')),
                $B('drawing', $L('Right eye', 'Œil droit'), ['width' => 'half', 'image' => $img('eye_front_right.svg')]),
                $B('drawing', $L('Left eye', 'Œil gauche'), ['width' => 'half', 'image' => $img('eye_front_left.svg')]),
                $B('multi', $L('Eyelids and lashes', 'Paupières et cils'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Swelling', 'Ptosis', 'Chalazion', 'Stye', 'Blepharitis', 'Entropion', 'Ectropion', 'Trichiasis'],
                    ['Normales', 'Œdème', 'Ptosis', 'Chalazion', 'Orgelet', 'Blépharite', 'Entropion', 'Ectropion', 'Trichiasis'])]),
                $B('multi', $L('Conjunctiva', 'Conjonctive'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Red / injected', 'Discharge', 'Pterygium', 'Pinguecula', 'Subconjunctival haemorrhage', 'Follicles / papillae', 'Pale'],
                    ['Normale', 'Hyperhémie', 'Sécrétions', 'Ptérygion', 'Pinguécula', 'Hémorragie sous-conjonctivale', 'Follicules / papilles', 'Pâle'])]),
                $B('multi', $L('Cornea', 'Cornée'), ['sides' => true, 'options' => $L(
                    ['Clear', 'Scar / opacity', 'Ulcer', 'Foreign body', 'Oedema', 'Abrasion (fluorescein positive)', 'Arcus'],
                    ['Claire', 'Taie / opacité', 'Ulcère', 'Corps étranger', 'Œdème', 'Érosion (fluorescéine +)', 'Arc sénile'])]),
                $B('multi', $L('Anterior chamber', 'Chambre antérieure'), ['sides' => true, 'options' => $L(
                    ['Deep and quiet', 'Shallow', 'Cells / flare', 'Hypopyon', 'Hyphaema'],
                    ['Profonde et calme', 'Étroite', 'Tyndall', 'Hypopion', 'Hyphéma'])]),
                $B('multi', $L('Pupil', 'Pupille'), ['sides' => true, 'options' => $L(
                    ['Round and reactive', 'Sluggish', 'RAPD', 'Irregular', 'Fixed and dilated'],
                    ['Ronde et réactive', 'Paresseuse', 'DPAR', 'Irrégulière', 'Aréactive dilatée'])]),
                $B('multi', $L('Lens', 'Cristallin'), ['sides' => true, 'options' => $L(
                    ['Clear', 'Early cataract', 'Mature cataract', 'Lens implant (IOL)', 'No lens (aphakia)'],
                    ['Clair', 'Cataracte débutante', 'Cataracte mûre', 'Implant (pseudophakie)', 'Aphakie'])]),
                $B('multi', $L('Eye movements', 'Oculomotricité'), ['options' => $L(
                    ['Full', 'Restricted', 'Squint', 'Nystagmus', 'Painful'], ['Normale', 'Limitée', 'Strabisme', 'Nystagmus', 'Douloureuse'])]),

                $B('heading', $L('Back of the eye (fundus)', 'Fond d\'œil')),
                $B('drawing', $L('Right fundus', 'Fond d\'œil droit'), ['width' => 'half', 'image' => $img('eye_fundus_right.svg')]),
                $B('drawing', $L('Left fundus', 'Fond d\'œil gauche'), ['width' => 'half', 'image' => $img('eye_fundus_left.svg')]),
                $B('text', $L('Cup-to-disc ratio', 'Rapport cup/disc'), ['sides' => true, 'width' => 'half']),
                $B('multi', $L('Fundus findings', 'Fond d\'œil'), ['sides' => true, 'options' => $L(
                    ['Normal', 'Glaucomatous cupping', 'Diabetic retinopathy', 'Hypertensive retinopathy', 'Papilloedema', 'Retinal detachment', 'Macular changes', 'Not seen'],
                    ['Normal', 'Excavation glaucomateuse', 'Rétinopathie diabétique', 'Rétinopathie hypertensive', 'Œdème papillaire', 'Décollement de rétine', 'Atteinte maculaire', 'Non vu'])]),
                $B('notes', $L('Eye notes', 'Notes ophtalmologiques')),
            ];

            return [
                'name' => $L('Eye examination', 'Examen ophtalmologique'),
                'specialties' => ['Ophthalmology', 'Ophtalmologie', 'Eye clinic'],
                'blocks' => $blocks,
            ];
        }

        return null;
    }

    /** Adds a ready-made form to the business. */
    public static function addReadyMade($business_id, $key, $user_id = null)
    {
        $def = self::readyMade($key);
        if (! $def) {
            return null;
        }
        $max = (int) ClinicExamTemplate::where('business_id', $business_id)->max('sort_order');

        return ClinicExamTemplate::create([
            'business_id' => $business_id,
            'name' => $def['name'],
            'specialties' => $def['specialties'],
            'blocks' => self::normalizeBlocks($def['blocks'], $business_id),
            'is_active' => 1,
            'sort_order' => $max + 1,
            'created_by' => $user_id,
        ]);
    }

    /**
     * The first time: the ready-made ENT and eye forms are added (once; a clinic that deletes them does
     * not get them back, except from the "add a ready-made form" button).
     */
    public static function seed($business_id)
    {
        $marker = fn () => DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', '_seeded')->where('value', 'exam_templates');
        if ($marker()->exists()) {
            return;
        }
        DB::transaction(function () use ($business_id, $marker) {
            // one at a time: two first visits at once must not add the forms twice
            DB::table('business')->where('id', $business_id)->lockForUpdate()->first();
            if ($marker()->exists()) {
                return;
            }
            if (! ClinicExamTemplate::withTrashed()->where('business_id', $business_id)->exists()) {
                foreach (array_keys(self::READY_MADE) as $key) {
                    self::addReadyMade($business_id, $key);
                }
            }
            DB::table('clinic_options')->insert(['business_id' => $business_id, 'list_key' => '_seeded', 'value' => 'exam_templates',
                'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    /**
     * Active forms of the business for the consultation: [id => ['name', 'specialties' (lower case)]].
     */
    public static function forConsultation($business_id)
    {
        self::seed($business_id);

        return ClinicExamTemplate::where('business_id', $business_id)->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'specialties'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name,
                'specialties' => array_values(array_map(fn ($s) => mb_strtolower(trim($s)), (array) $t->specialties))])
            ->values()->all();
    }
}
