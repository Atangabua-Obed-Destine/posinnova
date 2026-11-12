<?php

namespace App\Http\Controllers;

use App\ClinicWhatsappAccount;
use App\Utils\ClinicWhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp webhook (public, no login, no CSRF: /webhook/*).
 *
 *  GET  Meta checks the address once, when the clinic saves it in the Meta app:
 *       it sends hub.mode=subscribe, hub.verify_token and hub.challenge, and
 *       expects the challenge back when the token is the clinic's.
 *  POST every event (delivery, patient replies, template approvals), signed
 *       with the app secret (X-Hub-Signature-256). Unsigned or wrongly signed
 *       events are refused.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        // PHP turns "hub.mode" into "hub_mode": read the raw query string to be sure
        parse_str((string) $request->server('QUERY_STRING'), $q);
        $mode = $request->query('hub_mode', $q['hub_mode'] ?? ($q['hub.mode'] ?? null));
        $token = $request->query('hub_verify_token', $q['hub_verify_token'] ?? ($q['hub.verify_token'] ?? null));
        $challenge = $request->query('hub_challenge', $q['hub_challenge'] ?? ($q['hub.challenge'] ?? ''));

        $acc = $mode === 'subscribe' && is_string($token) && $token !== ''
            ? ClinicWhatsappAccount::where('verify_token', $token)->first() : null;
        if (! $acc) {
            return response('Forbidden', 403);
        }
        $acc->webhook_verified_at = now();
        $acc->save();

        return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request)
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('Bad request', 400);
        }
        $acc = ClinicWhatsApp::accountForPayload($payload);
        if (! $acc) {
            return response('OK', 200); // not ours: say OK so Meta does not keep retrying
        }
        if (! ClinicWhatsApp::signatureValid($acc, $raw, $request->header('X-Hub-Signature-256'))) {
            Log::warning('WhatsApp webhook: bad or missing signature', ['business_id' => $acc->business_id]);

            return response('Forbidden', 403);
        }
        try {
            ClinicWhatsApp::handleWebhook($acc, $payload);
        } catch (\Throwable $e) {
            // never make Meta retry a whole batch because of one odd event
            Log::error('WhatsApp webhook: '.$e->getMessage(), ['business_id' => $acc->business_id, 'line' => $e->getFile().':'.$e->getLine()]);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
