<?php

namespace App\Utils;

use App\Business;
use App\ClinicDoctorFee;
use App\ClinicPackage;

/**
 * PosInnova clinic: what a consultation costs with a given doctor.
 *
 * The clinic's price is the default: the package's price, or - a clinic without
 * packages - the business consultation fee. A doctor's own fee, when set,
 * replaces it for that doctor's patients (clinic_doctor_fees). An empty fee
 * means "the clinic's price".
 */
class ClinicFees
{
    /** The clinic's own price: the package's, or the business consultation fee. */
    public static function clinicPrice($business_id, ?ClinicPackage $package = null)
    {
        return $package ? (float) $package->price : (float) (Business::where('id', $business_id)->value('consultation_fee') ?: 0);
    }

    /** The doctor's own price, or null when they have none. */
    public static function doctorPrice($business_id, $doctor_id, ?ClinicPackage $package = null)
    {
        if (empty($doctor_id)) {
            return null;
        }
        $price = ClinicDoctorFee::forBusiness($business_id)->where('user_id', $doctor_id)
            ->where('package_key', $package ? $package->id : 0)->value('price');

        return $price === null ? null : (float) $price;
    }

    /** What the patient pays: the doctor's price when set, else the clinic's. */
    public static function price($business_id, $doctor_id, ?ClinicPackage $package = null)
    {
        $own = self::doctorPrice($business_id, $doctor_id, $package);

        return $own !== null ? $own : self::clinicPrice($business_id, $package);
    }

    /**
     * Every doctor's own prices: [doctor_id => [package_key => price]] (only the
     * ones set). For the check-in screen and the fees page.
     */
    public static function overrides($business_id)
    {
        $out = [];
        foreach (ClinicDoctorFee::forBusiness($business_id)->get() as $f) {
            $out[$f->user_id][(int) $f->package_key] = (float) $f->price;
        }

        return $out;
    }

    /** Sets (or, with null, clears) a doctor's price. Returns true when something changed. */
    public static function set($business_id, $doctor_id, $package_key, $price, $user_id = null)
    {
        $row = ClinicDoctorFee::forBusiness($business_id)->where('user_id', $doctor_id)->where('package_key', (int) $package_key)->first();
        if ($price === null) {
            if ($row) {
                $row->delete();

                return true;
            }

            return false;
        }
        if ($row && abs((float) $row->price - (float) $price) < 0.00005) {
            return false;
        }
        ClinicDoctorFee::updateOrCreate(
            ['business_id' => $business_id, 'user_id' => $doctor_id, 'package_key' => (int) $package_key],
            ['price' => $price, 'updated_by' => $user_id]
        );

        return true;
    }
}
