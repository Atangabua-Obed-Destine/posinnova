<?php

namespace App\Http\Controllers;

use App\ClinicAppointment;
use App\ClinicWhatsappAccount;
use App\ClinicWhatsappMessage;
use App\PatientDetail;
use App\Utils\ClinicWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * WhatsApp for the clinic: the set-up page (step-by-step guide, details,
 * tests, options, message log) for clinic_settings.manage, and the replies
 * inbox (patients' answers) for whoever manages appointments.
 */
class ClinicWhatsAppController extends Controller
{
    private function hospitalOn()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    private function authorizeSetup()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('clinic_settings.manage')) {
            abort(403, 'Unauthorized action.');
        }

        return ClinicWhatsappAccount::forBusiness(request()->session()->get('user.business_id'));
    }

    private function authorizeInbox()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('appointment.manage') && ! auth()->user()->can('clinic_settings.manage')) {
            abort(403, 'Unauthorized action.');
        }

        return request()->session()->get('user.business_id');
    }

    private function back($tab = 'setup')
    {
        return redirect()->action([self::class, 'index'], ['tab' => $tab]);
    }

    /** The set-up checklist: [key => [done, title, detail]]. */
    public static function checklist(ClinicWhatsappAccount $acc)
    {
        $test = ClinicWhatsappMessage::forBusiness($acc->business_id)->where('direction', 'out')->where('kind', 'test')->orderByDesc('id')->first();

        return [
            'details' => ! empty($acc->phone_number_id) && ! empty($acc->waba_id) && ! empty($acc->access_token),
            'connection' => (bool) $acc->connection_ok_at,
            'test' => $test && in_array($test->status, ['delivered', 'read']),
            'registered' => (bool) $acc->registered_at,
            'secret' => ! empty($acc->app_secret),
            'webhook' => (bool) $acc->webhook_verified_at && ((bool) $acc->webhook_subscribed_at || (bool) $acc->last_webhook_at),
            'templates' => ClinicWhatsApp::templatesReady($acc),
            'enabled' => $acc->ready(),
        ];
    }

    public function index(Request $request)
    {
        $acc = $this->authorizeSetup();
        $business_id = $acc->business_id;
        $checks = self::checklist($acc);
        $log = ClinicWhatsappMessage::forBusiness($business_id)->with(['patient', 'appointment'])->orderByDesc('id')->limit(200)->get();
        $last_test = ClinicWhatsappMessage::forBusiness($business_id)->where('kind', 'test')->orderByDesc('id')->first();
        $consenting = PatientDetail::where('business_id', $business_id)->where('whatsapp_opt_in', 1)->count();
        $patients = PatientDetail::where('business_id', $business_id)->count();
        $tab = in_array($request->input('tab'), ['setup', 'guide', 'log']) ? $request->input('tab') : (array_sum($checks) == count($checks) ? 'setup' : 'guide');
        $webhook_url = url('webhook/whatsapp');
        $privacy_url = url('privacy');

        return view('clinic.whatsapp.index', compact('acc', 'checks', 'log', 'last_test', 'consenting', 'patients', 'tab', 'webhook_url', 'privacy_url'));
    }

    public function save(Request $request)
    {
        $acc = $this->authorizeSetup();
        $request->validate([
            'phone_number_id' => 'nullable|regex:/^\d{5,30}$/',
            'waba_id' => 'nullable|regex:/^\d{5,30}$/',
            'access_token' => 'nullable|string|max:1000',
            'app_secret' => 'nullable|string|max:200',
            'api_version' => ['nullable', 'regex:/^v\d{2}\.\d$/'],
            'language' => 'required|in:fr,en',
            'reminder_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'tpl_confirmation' => ['required', 'regex:/^[a-z0-9_]{1,100}$/'],
            'tpl_reminder' => ['required', 'regex:/^[a-z0-9_]{1,100}$/'],
            'tpl_moved' => ['required', 'regex:/^[a-z0-9_]{1,100}$/'],
        ], [
            'phone_number_id.regex' => __('clinic.wa_v_digits'), 'waba_id.regex' => __('clinic.wa_v_digits'),
            'tpl_confirmation.regex' => __('clinic.wa_v_tpl'), 'tpl_reminder.regex' => __('clinic.wa_v_tpl'), 'tpl_moved.regex' => __('clinic.wa_v_tpl'),
        ]);

        $old_number = $acc->phone_number_id;
        $acc->phone_number_id = $request->input('phone_number_id') ?: null;
        $acc->waba_id = $request->input('waba_id') ?: null;
        // the secrets are never shown again: an empty box keeps what is saved
        if ($request->filled('access_token')) {
            $acc->access_token = trim($request->input('access_token'));
            $acc->connection_ok_at = null;
        }
        if ($request->filled('app_secret')) {
            $acc->app_secret = trim($request->input('app_secret'));
        }
        $acc->api_version = $request->input('api_version') ?: 'v25.0';
        $acc->language = $request->input('language');
        $acc->reminder_time = $request->input('reminder_time');
        foreach (['send_confirmation', 'send_reminder', 'send_moved', 'reminder_buttons'] as $f) {
            $acc->{$f} = $request->boolean($f);
        }
        foreach (['tpl_confirmation', 'tpl_reminder', 'tpl_moved'] as $f) {
            $acc->{$f} = $request->input($f);
        }
        if ($old_number !== $acc->phone_number_id) {
            // another number: its checks start again
            $acc->connection_ok_at = $acc->registered_at = null;
            $acc->display_phone = $acc->verified_name = $acc->connection_error = null;
        }
        $msg = __('clinic.wa_saved');
        $want_on = $request->boolean('enabled');
        if ($want_on && ! $acc->hasCredentials()) {
            $want_on = false;
            $msg = __('clinic.wa_cannot_enable');
        }
        $acc->enabled = $want_on;
        $acc->updated_by = auth()->id();
        $acc->save();
        app(\App\Utils\Util::class)->activityLog($acc, 'whatsapp_settings_saved', null, ['enabled' => $acc->enabled]);

        return $this->back('setup')->with('status', ['success' => $msg == __('clinic.wa_saved'), 'msg' => $msg]);
    }

    public function testConnection()
    {
        $acc = $this->authorizeSetup();
        if (! $acc->hasCredentials()) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        [$ok, $res] = ClinicWhatsApp::testConnection($acc);

        return $this->back()->with('status', $ok
            ? ['success' => true, 'msg' => __('clinic.wa_connection_ok', ['number' => $acc->display_phone ?: '-', 'name' => $acc->verified_name ?: '-'])]
            : ['success' => false, 'msg' => __('clinic.wa_connection_failed', ['error' => $res])]);
    }

    public function register(Request $request)
    {
        $acc = $this->authorizeSetup();
        $request->validate(['pin' => ['required', 'regex:/^\d{6}$/']], ['pin.regex' => __('clinic.wa_v_pin')]);
        if (! $acc->hasCredentials()) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        [$ok, $error] = ClinicWhatsApp::registerNumber($acc, $request->input('pin'));

        return $this->back()->with('status', $ok ? ['success' => true, 'msg' => __('clinic.wa_registered')] : ['success' => false, 'msg' => __('clinic.wa_action_failed', ['error' => $error])]);
    }

    public function subscribe()
    {
        $acc = $this->authorizeSetup();
        if (! $acc->hasCredentials() || empty($acc->waba_id)) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        [$ok, $error] = ClinicWhatsApp::subscribeWebhook($acc);

        return $this->back()->with('status', $ok ? ['success' => true, 'msg' => __('clinic.wa_subscribed')] : ['success' => false, 'msg' => __('clinic.wa_action_failed', ['error' => $error])]);
    }

    public function checkTemplates()
    {
        $acc = $this->authorizeSetup();
        if (! $acc->hasCredentials() || empty($acc->waba_id)) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        [$ok, $res] = ClinicWhatsApp::checkTemplates($acc);

        return $this->back()->with('status', $ok
            ? ['success' => true, 'msg' => ClinicWhatsApp::templatesReady($acc) ? __('clinic.wa_templates_ok') : __('clinic.wa_templates_not_yet')]
            : ['success' => false, 'msg' => __('clinic.wa_action_failed', ['error' => $res])]);
    }

    /** A test message: Meta's "hello_world" (works on the free test number) or a sample of a real template. */
    public function sendTest(Request $request)
    {
        $acc = $this->authorizeSetup();
        $request->validate(['to' => 'required|string|max:30', 'which' => 'required|in:hello_world,confirmation,reminder,moved']);
        if (! $acc->hasCredentials()) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        $to = ClinicWhatsApp::waNumber($acc->business_id, $request->input('to'));
        if (! $to) {
            return $this->back()->withInput()->with('status', ['success' => false, 'msg' => __('clinic.wa_bad_number')]);
        }
        $which = $request->input('which');
        $meta = ['kind' => 'test', 'created_by' => auth()->id()];
        if ($which == 'hello_world') {
            $msg = ClinicWhatsApp::sendTemplate($acc, $to, 'hello_world', 'en_US', [], $meta + ['body' => 'Hello World (Meta test template)']);
        } else {
            $lang = $acc->language;
            $params = [__('clinic.wa_sample_1', [], $lang), \App\Business::where('id', $acc->business_id)->value('name') ?: 'Clinic',
                __('clinic.wa_sample_3', [], $lang), __('clinic.wa_sample_4', [], $lang), '10:00'];
            $buttons = $which == 'reminder' && $acc->reminder_buttons ? ['TEST:YES', 'TEST:NO'] : null;
            $msg = ClinicWhatsApp::sendTemplate($acc, $to, $acc->templateFor($which), $lang, $params, $meta + ['body' => ClinicWhatsApp::render($which, $lang, $params)], $buttons);
        }

        return $this->back()->withInput()->with('status', $msg->status == 'failed'
            ? ['success' => false, 'msg' => __('clinic.wa_test_failed', ['error' => $msg->error_message])]
            : ['success' => true, 'msg' => __('clinic.wa_test_sent', ['to' => '+'.$to])]);
    }

    public function newVerifyToken()
    {
        $acc = $this->authorizeSetup();
        $acc->verify_token = Str::random(32);
        $acc->webhook_verified_at = null;
        $acc->save();

        return $this->back('guide')->with('status', ['success' => true, 'msg' => __('clinic.wa_token_renewed')]);
    }

    /* ---------------- replies inbox ---------------- */

    public function inbox(Request $request)
    {
        $business_id = $this->authorizeInbox();
        $show = $request->input('show') == 'all' ? 'all' : 'open';
        $q = ClinicWhatsappMessage::forBusiness($business_id)->where('direction', 'in')->with(['patient.patientDetails', 'appointment.doctor']);
        if ($show == 'open') {
            $q->where('status', 'received');
        }
        $replies = $q->orderByDesc('id')->limit(200)->get();
        // what was said to each patient before, to read a reply in context
        $sent = ClinicWhatsappMessage::forBusiness($business_id)->where('direction', 'out')
            ->whereIn('wamid', $replies->pluck('context_wamid')->filter()->all())->get()->keyBy('wamid');
        $acc = ClinicWhatsApp::account($business_id);
        $open_count = ClinicWhatsappMessage::forBusiness($business_id)->unhandled()->count();

        return view('clinic.whatsapp.inbox', compact('replies', 'sent', 'show', 'acc', 'open_count'));
    }

    public function handled($id)
    {
        $business_id = $this->authorizeInbox();
        $m = ClinicWhatsappMessage::forBusiness($business_id)->where('direction', 'in')->findOrFail($id);
        $m->update(['status' => 'handled', 'handled_at' => now(), 'handled_by' => auth()->id()]);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.wa_marked_handled')]);
    }

    /** A typed answer to a patient (Meta allows free text within 24 hours of their last message). */
    public function reply(Request $request, $id)
    {
        $business_id = $this->authorizeInbox();
        $request->validate(['text' => 'required|string|max:1000']);
        $m = ClinicWhatsappMessage::forBusiness($business_id)->where('direction', 'in')->findOrFail($id);
        $acc = ClinicWhatsApp::account($business_id);
        if (! $acc || ! $acc->hasCredentials()) {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_need_details')]);
        }
        if (! ClinicWhatsApp::canReply($business_id, $m->wa_number)) {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_reply_too_late')]);
        }
        $out = ClinicWhatsApp::sendText($acc, $m->wa_number, trim($request->input('text')), [
            'kind' => 'text', 'contact_id' => $m->contact_id, 'appointment_id' => $m->appointment_id, 'created_by' => auth()->id(),
        ]);
        if ($out->status == 'failed') {
            return redirect()->back()->with('status', ['success' => false, 'msg' => __('clinic.wa_test_failed', ['error' => $out->error_message])]);
        }
        $m->update(['status' => 'handled', 'handled_at' => now(), 'handled_by' => auth()->id()]);

        return redirect()->back()->with('status', ['success' => true, 'msg' => __('clinic.wa_reply_sent')]);
    }

    /** Public privacy notice (Meta asks for one before the app can go live). */
    public function privacy(Request $request)
    {
        if (in_array($request->input('lang'), ['en', 'fr'])) {
            app()->setLocale($request->input('lang'));
        }
        // the clinic whose WhatsApp is set up (one clinic per server here); else the first business
        $business_id = ClinicWhatsappAccount::whereNotNull('phone_number_id')->value('business_id');
        $business = $business_id ? \App\Business::find($business_id) : \App\Business::orderBy('id')->first();

        return view('clinic.whatsapp.privacy', compact('business'));
    }
}
