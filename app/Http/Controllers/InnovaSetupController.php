<?php

namespace App\Http\Controllers;

use App\Utils\BusinessUtil;
use App\Utils\InnovaSetup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The last step of an installation (php artisan innova:install prints the link):
 * the client's business and its owner. The link works once, for 7 days.
 */
class InnovaSetupController extends Controller
{
    public function show(string $token)
    {
        if (! InnovaSetup::tokenIsValid($token) || InnovaSetup::hasClientBusiness()) {
            return response()->view('innova_setup.invalid', [], 404);
        }

        $util = new BusinessUtil();

        return view('innova_setup.form', [
            'token' => $token,
            'currencies' => $util->allCurrencies(),
            'timezones' => $util->allTimeZones(),
            'default_currency' => InnovaSetup::defaultCurrencyId(),
            'default_until' => Carbon::today()->addYears((int) config('innova.package.years', 7))->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, string $token)
    {
        if (! InnovaSetup::tokenIsValid($token) || InnovaSetup::hasClientBusiness()) {
            return response()->view('innova_setup.invalid', [], 404);
        }

        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'currency_id' => 'required|integer|exists:currencies,id',
            'time_zone' => ['required', Rule::in(array_keys((new BusinessUtil())->allTimeZones()))],
            'country' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => ['required', 'string', 'min:4', 'max:100', 'regex:/^[A-Za-z0-9._@-]+$/', 'unique:users,username', Rule::notIn([InnovaSetup::username()])],
            'email' => 'nullable|email|max:191|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'language' => ['required', Rule::in(['en', 'fr'])],
            'until' => 'nullable|date|after:today',
        ], [], [
            'business_name' => __('innova_setup.business_name'),
            'currency_id' => __('innova_setup.currency'),
            'time_zone' => __('innova_setup.time_zone'),
            'country' => __('innova_setup.country'),
            'city' => __('innova_setup.city'),
            'first_name' => __('innova_setup.first_name'),
            'last_name' => __('innova_setup.last_name'),
            'username' => __('innova_setup.username'),
            'email' => __('innova_setup.email'),
            'password' => __('innova_setup.password'),
            'until' => __('innova_setup.until'),
        ]);

        try {
            InnovaSetup::createClientBusiness(
                [
                    'name' => $data['business_name'],
                    'currency_id' => $data['currency_id'],
                    'time_zone' => $data['time_zone'],
                    'country' => $data['country'],
                    'city' => $data['city'],
                ],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'username' => $data['username'],
                    'email' => $data['email'] ?? null,
                    'password' => $data['password'],
                    'language' => $data['language'],
                ],
                ! empty($data['until']) ? Carbon::parse($data['until']) : null
            );
        } catch (\Throwable $e) {
            \Log::emergency('I-NNOVA setup: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['business_name' => __('innova_setup.failed')]);
        }

        InnovaSetup::forgetToken();

        return redirect('login')->with('status', [
            'success' => 1,
            'msg' => __('innova_setup.done', ['name' => $data['business_name'], 'username' => $data['username']]),
        ]);
    }
}
