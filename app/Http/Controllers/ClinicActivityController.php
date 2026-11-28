<?php

namespace App\Http\Controllers;

use App\Utils\ClinicActivity;

/**
 * The floating activity widget's list (GET clinic/activity, polled every few
 * seconds by layouts/partials/activity_widget): booking requests, patients
 * waiting, consultations not finished — whatever applies to the signed-in user.
 */
class ClinicActivityController extends Controller
{
    public function index()
    {
        $data = ClinicActivity::build(auth()->user(), session('user.business_id'));

        return response()->json($data ? ['on' => true] + $data : ['on' => false])
            ->header('Cache-Control', 'no-store');
    }
}
