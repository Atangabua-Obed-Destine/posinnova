<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: the lists doctors pick from instead of typing (specialties, complaints, examination
 * findings, diagnoses, and for prescriptions dosage / frequency / duration / instructions).
 *
 * Each business has its own copy in clinic_options. The first time a list is used it is filled with the
 * defaults below, in the language of the person using it (English or French); after that it is the
 * clinic's list: entries can be added on the spot by doctors, renamed or removed under Clinic setup.
 * A list emptied on purpose is not filled again ("_seeded" marker rows).
 */
class ClinicOptions
{
    /** list key => lang key of its title */
    const LISTS = [
        'specialty' => 'clinic.dl_specialty',
        'complaint' => 'clinic.dl_complaint',
        'exam_finding' => 'clinic.dl_exam_finding',
        'diagnosis' => 'clinic.dl_diagnosis',
        'rx_dosage' => 'clinic.dl_rx_dosage',
        'rx_frequency' => 'clinic.dl_rx_frequency',
        'rx_duration' => 'clinic.dl_rx_duration',
        'rx_instruction' => 'clinic.dl_rx_instruction',
    ];

    const DEFAULTS = [
        'en' => [
            'specialty' => ['General medicine', 'Internal medicine', 'Paediatrics', 'Gynaecology & obstetrics', 'General surgery',
                'Orthopaedics & traumatology', 'Ophthalmology', 'ENT (ear, nose & throat)', 'Dermatology', 'Cardiology', 'Neurology',
                'Psychiatry', 'Dentistry', 'Urology', 'Gastroenterology', 'Pulmonology', 'Endocrinology & diabetes', 'Nephrology',
                'Rheumatology', 'Physiotherapy', 'Nutrition & dietetics', 'Radiology'],
            'complaint' => ['Fever', 'Headache', 'Cough', 'Runny nose / catarrh', 'Sore throat', 'Abdominal pain', 'Diarrhoea', 'Vomiting',
                'Nausea', 'Loss of appetite', 'General body pain', 'Weakness / fatigue', 'Chest pain', 'Difficulty breathing', 'Dizziness',
                'Painful urination', 'Back pain', 'Joint pain', 'Skin rash / itching', 'Red / painful eye', 'Ear pain', 'Toothache',
                'Vaginal discharge', 'Lower abdominal pain', 'Palpitations', 'Swollen legs', 'Convulsions', 'Constipation',
                'Difficulty sleeping', 'Weight loss'],
            'exam_finding' => ['Conscious and well oriented', 'Febrile', 'Afebrile', 'Pale conjunctivae', 'Conjunctivae well coloured',
                'No jaundice', 'Jaundice', 'Dehydrated', 'Well hydrated', 'Chest clear', 'Crepitations heard', 'Wheezing',
                'Heart sounds normal', 'Abdomen soft, non-tender', 'Epigastric tenderness', 'Hepatomegaly', 'Splenomegaly',
                'No oedema', 'Pitting oedema of the legs', 'Throat inflamed', 'Enlarged tonsils', 'No palpable lymph nodes',
                'Neurological exam normal'],
            'diagnosis' => ['Malaria (uncomplicated)', 'Severe malaria', 'Typhoid fever', 'Upper respiratory tract infection', 'Pneumonia',
                'Bronchitis', 'Tonsillitis / pharyngitis', 'Otitis media', 'Sinusitis', 'Gastroenteritis', 'Gastritis / peptic ulcer disease',
                'Intestinal worms', 'Amoebiasis', 'Urinary tract infection', 'Sexually transmitted infection', 'Vaginal candidiasis',
                'Hypertension', 'Type 2 diabetes', 'Anaemia', 'Asthma', 'Allergic reaction', 'Skin infection', 'Fungal skin infection',
                'Conjunctivitis', 'Low back pain', 'Arthritis', 'Dental caries', 'Viral infection', 'Malnutrition', 'Normal pregnancy follow-up'],
            'rx_dosage' => ['1 tablet', '2 tablets', '½ tablet', '1 capsule', '2 capsules', '2.5 ml', '5 ml', '10 ml', '15 ml', '1 sachet',
                '1 ampoule', '1 vial', '1 suppository', '1 pessary', '1 drop', '2 drops', '1 puff', '2 puffs', 'Apply thinly'],
            'rx_frequency' => ['Once daily (OD)', 'Twice daily (BD)', '3 times daily (TDS)', '4 times daily (QDS)', 'Every 4 hours',
                'Every 6 hours', 'Every 8 hours', 'Every 12 hours', 'In the morning', 'At night', 'When needed (PRN)',
                'Single dose now (STAT)', 'Once a week'],
            'rx_duration' => ['1 day', '3 days', '5 days', '7 days', '10 days', '14 days', '21 days', '1 month', '3 months',
                'Single dose', 'Until finished', 'Long term'],
            'rx_instruction' => ['After meals', 'Before meals', 'With food', 'On an empty stomach', 'With plenty of water', 'Avoid alcohol',
                'Do not crush', 'Shake well before use', 'Complete the full course', 'May cause drowsiness'],
        ],
        'fr' => [
            'specialty' => ['Médecine générale', 'Médecine interne', 'Pédiatrie', 'Gynécologie-obstétrique', 'Chirurgie générale',
                'Orthopédie-traumatologie', 'Ophtalmologie', 'ORL', 'Dermatologie', 'Cardiologie', 'Neurologie', 'Psychiatrie',
                'Chirurgie dentaire', 'Urologie', 'Gastro-entérologie', 'Pneumologie', 'Endocrinologie-diabétologie', 'Néphrologie',
                'Rhumatologie', 'Kinésithérapie', 'Nutrition-diététique', 'Radiologie'],
            'complaint' => ['Fièvre', 'Céphalées', 'Toux', 'Rhume / écoulement nasal', 'Maux de gorge', 'Douleurs abdominales', 'Diarrhée',
                'Vomissements', 'Nausées', 'Perte d\'appétit', 'Courbatures', 'Asthénie / fatigue', 'Douleur thoracique',
                'Difficulté respiratoire', 'Vertiges', 'Brûlures mictionnelles', 'Lombalgies', 'Douleurs articulaires',
                'Éruption cutanée / prurit', 'Œil rouge / douloureux', 'Otalgie', 'Douleur dentaire', 'Pertes vaginales',
                'Douleurs pelviennes', 'Palpitations', 'Œdème des jambes', 'Convulsions', 'Constipation', 'Insomnie', 'Amaigrissement'],
            'exam_finding' => ['Conscient et bien orienté', 'Fébrile', 'Apyrétique', 'Conjonctives pâles', 'Conjonctives bien colorées',
                'Pas d\'ictère', 'Ictère', 'Déshydraté', 'Bien hydraté', 'Auscultation pulmonaire normale', 'Râles crépitants',
                'Sibilants', 'Bruits du cœur normaux', 'Abdomen souple, indolore', 'Sensibilité épigastrique', 'Hépatomégalie',
                'Splénomégalie', 'Pas d\'œdème', 'Œdème des membres inférieurs prenant le godet', 'Gorge inflammatoire',
                'Amygdales hypertrophiées', 'Pas d\'adénopathie palpable', 'Examen neurologique normal'],
            'diagnosis' => ['Paludisme simple', 'Paludisme grave', 'Fièvre typhoïde', 'Infection des voies respiratoires supérieures',
                'Pneumonie', 'Bronchite', 'Amygdalite / pharyngite', 'Otite moyenne', 'Sinusite', 'Gastro-entérite',
                'Gastrite / ulcère gastroduodénal', 'Parasitose intestinale', 'Amibiase', 'Infection urinaire',
                'Infection sexuellement transmissible', 'Candidose vaginale', 'Hypertension artérielle', 'Diabète de type 2', 'Anémie',
                'Asthme', 'Réaction allergique', 'Infection cutanée', 'Mycose cutanée', 'Conjonctivite', 'Lombalgie', 'Arthrite / arthrose',
                'Carie dentaire', 'Infection virale', 'Malnutrition', 'Suivi de grossesse normale'],
            'rx_dosage' => ['1 comprimé', '2 comprimés', '½ comprimé', '1 gélule', '2 gélules', '2,5 ml', '5 ml', '10 ml', '15 ml', '1 sachet',
                '1 ampoule', '1 flacon', '1 suppositoire', '1 ovule', '1 goutte', '2 gouttes', '1 bouffée', '2 bouffées',
                'Appliquer en couche mince'],
            'rx_frequency' => ['1 fois par jour', '2 fois par jour', '3 fois par jour', '4 fois par jour', 'Toutes les 4 heures',
                'Toutes les 6 heures', 'Toutes les 8 heures', 'Toutes les 12 heures', 'Le matin', 'Le soir au coucher', 'Si besoin',
                'Dose unique immédiate', '1 fois par semaine'],
            'rx_duration' => ['1 jour', '3 jours', '5 jours', '7 jours', '10 jours', '14 jours', '21 jours', '1 mois', '3 mois', 'Dose unique',
                'Jusqu\'à la fin du flacon', 'Au long cours'],
            'rx_instruction' => ['Après les repas', 'Avant les repas', 'Pendant le repas', 'À jeun', 'Avec beaucoup d\'eau', 'Éviter l\'alcool',
                'Ne pas écraser', 'Bien agiter avant emploi', 'Terminer tout le traitement', 'Peut provoquer une somnolence'],
        ],
    ];

    public static function isList($key)
    {
        return array_key_exists($key, self::LISTS);
    }

    /**
     * The values of one list, in order. Fills the list with the defaults the first time.
     */
    public static function values($business_id, $key)
    {
        return self::all($business_id)[$key] ?? [];
    }

    /**
     * Every list of the business: [key => [value, ...]].
     */
    public static function all($business_id)
    {
        self::seed($business_id);

        $rows = DB::table('clinic_options')->where('business_id', $business_id)
            ->orderBy('list_key')->orderBy('sort_order')->orderBy('id')
            ->get(['list_key', 'value']);
        $out = array_fill_keys(array_keys(self::LISTS), []);
        foreach ($rows as $r) {
            if (isset($out[$r->list_key])) {
                $out[$r->list_key][] = $r->value;
            }
        }

        return $out;
    }

    /**
     * Rows of one list (id, value) for the Clinic setup page.
     */
    public static function rows($business_id, $key)
    {
        self::seed($business_id);

        return DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', $key)
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'value']);
    }

    /**
     * Adds a value at the end of a list (nothing when it is already there, whatever the case).
     *
     * @return string the value as stored
     */
    public static function add($business_id, $key, $value, $user_id = null)
    {
        $value = self::clean($value);
        if ($value === '' || ! self::isList($key)) {
            return null;
        }
        self::seed($business_id);
        $existing = DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', $key)
            ->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])->value('value');
        if ($existing !== null) {
            return $existing;
        }
        $max = (int) DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', $key)->max('sort_order');
        DB::table('clinic_options')->insert([
            'business_id' => $business_id, 'list_key' => $key, 'value' => $value, 'sort_order' => $max + 1,
            'created_by' => $user_id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $value;
    }

    public static function clean($value)
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $value)), 0, 191);
    }

    /**
     * Fills the lists never filled before with the defaults, in the current language.
     */
    public static function seed($business_id)
    {
        $seeded = self::seededKeys($business_id);
        $missing = array_diff(array_keys(self::LISTS), $seeded);
        if (empty($missing)) {
            return;
        }

        $lang = str_starts_with((string) app()->getLocale(), 'fr') ? 'fr' : 'en';
        DB::transaction(function () use ($business_id, $missing, $lang) {
            $seeded = self::seededKeys($business_id, true);
            $now = now();
            foreach ($missing as $key) {
                if (in_array($key, $seeded)) {
                    continue;
                }
                $has = DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', $key)->exists();
                if (! $has) {
                    $rows = [];
                    foreach (self::DEFAULTS[$lang][$key] as $i => $v) {
                        $rows[] = ['business_id' => $business_id, 'list_key' => $key, 'value' => $v, 'sort_order' => $i + 1,
                            'created_by' => null, 'created_at' => $now, 'updated_at' => $now];
                    }
                    DB::table('clinic_options')->insert($rows);
                }
                $seeded[] = $key;
            }
            self::storeSeededKeys($business_id, $seeded);
        });
    }

    /** Lists already filled once: kept as "_seeded" rows of the same table (value = list key). */
    private static function seededKeys($business_id, $lock = false)
    {
        $q = DB::table('clinic_options')->where('business_id', $business_id)->where('list_key', '_seeded');
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->pluck('value')->all();
    }

    private static function storeSeededKeys($business_id, array $keys)
    {
        $have = self::seededKeys($business_id);
        foreach (array_diff(array_unique($keys), $have) as $k) {
            DB::table('clinic_options')->insert(['business_id' => $business_id, 'list_key' => '_seeded', 'value' => $k,
                'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
