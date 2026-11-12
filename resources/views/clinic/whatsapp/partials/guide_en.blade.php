{{-- WhatsApp set-up guide (English). Every value to type into Meta has a Copy button. --}}
@php
    $tpl_samples = ['Marie', \App\Business::where('id', $acc->business_id)->value('name') ?: 'Clinic', 'Dr Paul', 'Friday 13 November 2026', '10:00'];
    $tpl_samples_fr = ['Marie', $tpl_samples[1], 'Dr Paul', 'vendredi 13 novembre 2026', '10:00'];
@endphp

<div class="wa-tip" style="font-size:14px;">
    <strong>What this does.</strong> Once set up, PosInnova sends WhatsApp messages to patients by itself: a confirmation when an
    appointment is booked, a reminder the day before (with two buttons, "I will come" and "I cannot come"), and a message when an
    appointment is moved. Patients' answers come back into <span class="wa-menu">Appointments → WhatsApp replies</span>.
    Messages go only to patients who agreed, and anyone can stop them by replying STOP.
</div>

<h3>Before you start: what you need</h3>
<ul>
    <li><strong>A phone number for the clinic's messages.</strong> The simplest is a <strong>new SIM card used only for this</strong>.
        It must be able to receive an SMS or a call (Meta sends a code to it once).
        <div class="wa-warn">The number must <strong>not</strong> already be used on the WhatsApp or WhatsApp Business app.
            If it is, open WhatsApp on that phone → <span class="wa-menu">Settings → Account → Delete my account</span> first
            (you lose that phone's WhatsApp chats). That is why a new SIM is easier.</div></li>
    <li><strong>A Facebook account</strong> (yours) to sign in to Meta's websites.</li>
    <li>The clinic's <strong>official name, address and email</strong>.</li>
    <li>A <strong>Visa or Mastercard bank card</strong>. Meta charges for each message, roughly 2 to 3 FCFA per message to Cameroon numbers.</li>
    <li>About <strong>one hour</strong>, then some waiting while Meta approves the clinic's name and the message texts (usually a few minutes to a day).</li>
    <li>A computer, and this page open in another tab: you will copy values back and forth.</li>
</ul>
<div class="wa-tip">Meta sometimes renames its menus. If a name below does not match exactly, look for the closest one: the steps stay the same.</div>

<h3>Part 1: Meta account and app (about 20 minutes)</h3>

<h4 id="g1">Step 1: Create the clinic's business portfolio</h4>
<ol>
    <li>Go to <a href="https://business.facebook.com" target="_blank" rel="noopener">business.facebook.com</a> and sign in with your Facebook account.</li>
    <li>Click <span class="wa-menu">Create an account</span> (it may say "Create a business portfolio").</li>
    <li>Enter the clinic's name, your name and the clinic's email, then confirm the email Meta sends.</li>
</ol>
<div class="wa-tip">Already have a business portfolio for the clinic (for its Facebook page, for example)? Use that one and skip this step.</div>

<h4 id="g2">Step 2: Create the app</h4>
<ol>
    <li>Go to <a href="https://developers.facebook.com" target="_blank" rel="noopener">developers.facebook.com</a> → <span class="wa-menu">Get started</span>
        (the first time, Meta asks you to register as a developer and confirm your phone number by SMS).</li>
    <li>Click <span class="wa-menu">My Apps</span> → <span class="wa-menu">Create app</span>.</li>
    <li>App name: for example <code>{{ \App\Business::where('id', $acc->business_id)->value('name') }} messages</code>. Contact email: the clinic's.</li>
    <li>When asked what the app is for (the "use case"), choose <span class="wa-menu">Connect with customers through WhatsApp</span>.
        If you only see app types, choose <span class="wa-menu">Business</span>.</li>
    <li>Choose the business portfolio from step 1, then <span class="wa-menu">Create app</span>.</li>
</ol>

<h4 id="g3">Step 3: Copy the account details into PosInnova</h4>
<ol>
    <li>In the app, open <span class="wa-menu">WhatsApp → API Setup</span> in the left menu.
        Meta gives you a <strong>free test number</strong> to try things out.</li>
    <li>Click <span class="wa-menu">Generate access token</span> and accept. Copy the long token (it starts with EAA...).</li>
    <li>On the same page, copy the <strong>Phone number ID</strong> (under "From") and the <strong>WhatsApp Business Account ID</strong>.</li>
    <li>In PosInnova, tab <a href="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'index'], ['tab' => 'setup']) }}#wa_details">Set-up and settings</a>:
        paste them in <em>Phone number ID</em>, <em>WhatsApp Business Account ID</em> and <em>Access token</em> → <span class="wa-menu">Save</span>.</li>
    <li>Click <span class="wa-menu">Test connection</span>. You should see the test number with a green tick.</li>
</ol>
<div class="wa-tip">This first token only lasts about a day. That is fine for testing; step 8 replaces it with a permanent one.</div>

<h4 id="g4">Step 4: Send yourself a first message</h4>
<ol>
    <li>Still in <span class="wa-menu">WhatsApp → API Setup</span>, under "To", click <span class="wa-menu">Manage phone number list</span>
        and add <strong>your own WhatsApp number</strong>. Meta sends you a code on WhatsApp: type it in.
        (With the test number, Meta only lets you message up to 5 numbers you add here.)</li>
    <li>In PosInnova, in the checklist step "Send a test message", type your number, keep <code>hello_world</code>, and click <span class="wa-menu">Send test</span>.</li>
    <li>You should receive "Hello World" on WhatsApp within a few seconds. Well done: the connection works.</li>
</ol>

<h3>Part 2: The clinic's real number and a permanent key (about 20 minutes)</h3>

<h4 id="g5">Step 5: Add the clinic's number</h4>
<ol>
    <li>In <span class="wa-menu">WhatsApp → API Setup</span>, click <span class="wa-menu">Add phone number</span>.</li>
    <li><strong>Display name</strong>: the clinic's name as patients will see it. It must match the clinic's real name (Meta checks it).</li>
    <li>Category: <span class="wa-menu">Medical and health</span>. Then enter the number and choose SMS or voice call to receive the code. Type the code.</li>
</ol>
<div class="wa-tip">Meta reviews the display name, usually within a day. You can continue with the next steps meanwhile.</div>

<h4 id="g6">Step 6: Use the real number in PosInnova</h4>
<ol>
    <li>Back in <span class="wa-menu">WhatsApp → API Setup</span>, choose the clinic's number in the "From" list and copy its <strong>Phone number ID</strong>
        (it is different from the test number's).</li>
    <li>In PosInnova, replace the Phone number ID with this one → <span class="wa-menu">Save</span> → <span class="wa-menu">Test connection</span>.
        The checklist now shows the clinic's number.</li>
</ol>

<h4 id="g7">Step 7: Register the number for sending</h4>
<ol>
    <li>Choose a <strong>6-digit PIN</strong> and write it down somewhere safe (it protects the number: Meta asks for it if the number is ever moved).</li>
    <li>In PosInnova, checklist step "Number registered for sending": type the PIN → <span class="wa-menu">Register the number</span>.</li>
</ol>
<div class="wa-tip">If PosInnova says the number already has a PIN, use that PIN; if nobody knows it, change it in Meta:
    <span class="wa-menu">WhatsApp Manager → Phone numbers → (the number) → Two-step verification</span>.</div>

<h4 id="g8">Step 8: Make a permanent access token</h4>
<ol>
    <li>Go to <a href="https://business.facebook.com/settings" target="_blank" rel="noopener">business.facebook.com/settings</a>
        (Business settings) → <span class="wa-menu">Users → System users</span> → <span class="wa-menu">Add</span>.</li>
    <li>Name: <code>posinnova</code>. Role: <span class="wa-menu">Admin</span> → <span class="wa-menu">Create system user</span>.</li>
    <li>With the new user selected, click <span class="wa-menu">Assign assets</span>:
        <ul>
            <li><span class="wa-menu">Apps</span> → your app → turn on <strong>Full control</strong>;</li>
            <li><span class="wa-menu">WhatsApp accounts</span> → the clinic's account → turn on <strong>Full control</strong> → <span class="wa-menu">Assign</span>.</li>
        </ul></li>
    <li>Click <span class="wa-menu">Generate new token</span> → choose your app → Token expiration: <strong>Never</strong> →
        tick the permissions <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code> → <span class="wa-menu">Generate token</span>.</li>
    <li><strong>Copy the token now</strong> (Meta shows it only once), paste it in PosInnova's <em>Access token</em> box → <span class="wa-menu">Save</span> → <span class="wa-menu">Test connection</span>.</li>
</ol>
<div class="wa-warn">Keep this token secret: anyone who has it can send messages in the clinic's name. PosInnova stores it encrypted and never shows it again.</div>

<h4 id="g9">Step 9: Add a payment method</h4>
<ol>
    <li>In <a href="https://business.facebook.com/settings" target="_blank" rel="noopener">Business settings</a> → <span class="wa-menu">Billing and payments</span>
        (or <span class="wa-menu">WhatsApp Manager → Settings → Payment methods</span>) → <span class="wa-menu">Add payment method</span>.</li>
    <li>Enter the clinic's Visa or Mastercard. Without it, messages to patients are not sent.</li>
</ol>

<h3>Part 3: Let Meta report back to PosInnova (about 10 minutes)</h3>

<h4 id="g10">Step 10: Copy the app secret</h4>
<ol>
    <li><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> → your app →
        <span class="wa-menu">App settings → Basic</span>.</li>
    <li>Next to <strong>App secret</strong>, click <span class="wa-menu">Show</span> (Meta asks for your Facebook password) and copy it.</li>
    <li>Paste it in PosInnova's <em>App secret</em> box → <span class="wa-menu">Save</span>. PosInnova uses it to check that messages really come from Meta.</li>
</ol>

<h4 id="g11">Step 11: Set the webhook (Meta's link to PosInnova)</h4>
<ol>
    <li>In the app: <span class="wa-menu">WhatsApp → Configuration</span> → in the Webhook section, <span class="wa-menu">Edit</span>.</li>
    <li>Copy these two values into Meta, then click <span class="wa-menu">Verify and save</span>:
        @include('clinic.whatsapp.partials.copy', ['label' => 'Callback URL', 'text' => $webhook_url])
        @include('clinic.whatsapp.partials.copy', ['label' => 'Verify token', 'text' => $acc->verify_token])
        PosInnova's checklist then shows "Meta checked the webhook address".</li>
    <li>Still on that page, under <span class="wa-menu">Webhook fields</span> → <span class="wa-menu">Manage</span>, subscribe to
        <code>messages</code> and <code>message_template_status_update</code>.</li>
</ol>

<h4 id="g12">Step 12: Connect the webhook</h4>
<ol>
    <li>In PosInnova, checklist step "Webhook connected", click <span class="wa-menu">Connect webhook</span>.
        This tells Meta to send the clinic's events (delivery, patients' replies) to PosInnova.</li>
</ol>

<h4 id="g13">Step 13: Put the app live</h4>
<ol>
    <li>In the app: <span class="wa-menu">App settings → Basic</span> → <strong>Privacy policy URL</strong>: paste
        @include('clinic.whatsapp.partials.copy', ['text' => $privacy_url])
        and choose a category (for example "Health") → <span class="wa-menu">Save changes</span>.</li>
    <li>At the top of the app's page, switch <strong>App mode</strong> from <em>Development</em> to <em>Live</em>.
        (In development mode Meta does not pass real patients' replies on.)</li>
</ol>

<h3>Part 4: The three message texts (10 minutes, then Meta's approval)</h3>
<h4 id="g14">Step 14: Create the message templates</h4>
<p>WhatsApp only lets a business start a conversation with a text Meta has approved, called a <em>template</em>. The clinic needs three.
    For each one below:</p>
<ol>
    <li>Open <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener">WhatsApp Manager → Message templates</a>
        → <span class="wa-menu">Create template</span>.</li>
    <li>Category: <strong>Utility</strong>. Name: copy it from below <strong>exactly</strong>. Language: <strong>French</strong>.</li>
    <li>Body: paste the French text. Where Meta asks about variables, choose type <strong>Number</strong> (@{{1}}, @{{2}}...)
        and paste the sample values given below.</li>
    <li>For the reminder only: add the <strong>footer</strong>, and two <strong>Quick reply</strong> buttons, in this order.</li>
    <li><span class="wa-menu">Submit</span>. Then open the same template, <span class="wa-menu">Add language</span> → <strong>English</strong>, and do the same with the English text.</li>
</ol>
<div class="wa-tip">Do not change the wording: an extra promotional sentence (a discount, an offer) gets a template rejected or moved to "Marketing", which costs more.</div>

@foreach (\App\Utils\ClinicWhatsApp::KINDS as $kind)
    <div class="wa-tpl">
        <div class="wa-tpl-head">{{ $loop->iteration }}. {{ __('clinic.wa_kind_' . $kind, [], 'en') }}</div>
        @include('clinic.whatsapp.partials.copy', ['label' => 'Template name', 'text' => $acc->templateFor($kind)])
        <div class="row">
            <div class="col-md-6">
                @include('clinic.whatsapp.partials.copy', ['label' => 'Body: French', 'text' => __('clinic.wa_tpl_' . $kind, [], 'fr'), 'multi' => 1])
                @if ($kind == 'reminder')
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Footer: French', 'text' => __('clinic.wa_tpl_footer', [], 'fr')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Button 1 (Quick reply): French', 'text' => __('clinic.wa_btn_coming', [], 'fr')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Button 2 (Quick reply): French', 'text' => __('clinic.wa_btn_not_coming', [], 'fr')])
                @endif
                <div class="wa-copy-label">Sample values (French)</div>
                <ol style="font-size:13px; margin-bottom:0;">@foreach ($tpl_samples_fr as $s)<li><code>{{ $s }}</code></li>@endforeach</ol>
            </div>
            <div class="col-md-6">
                @include('clinic.whatsapp.partials.copy', ['label' => 'Body: English', 'text' => __('clinic.wa_tpl_' . $kind, [], 'en'), 'multi' => 1])
                @if ($kind == 'reminder')
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Footer: English', 'text' => __('clinic.wa_tpl_footer', [], 'en')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Button 1 (Quick reply): English', 'text' => __('clinic.wa_btn_coming', [], 'en')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Button 2 (Quick reply): English', 'text' => __('clinic.wa_btn_not_coming', [], 'en')])
                @endif
                <div class="wa-copy-label">Sample values (English)</div>
                <ol style="font-size:13px; margin-bottom:0;">@foreach ($tpl_samples as $s)<li><code>{{ $s }}</code></li>@endforeach</ol>
            </div>
        </div>
    </div>
@endforeach
<p>Approval usually takes a few minutes, sometimes up to a day. Then click <span class="wa-menu">Check templates</span> in PosInnova's checklist:
    each should say <span class="label label-success">Approved</span>. Then send yourself a sample reminder from the "Send a test message" step.</p>

<h3>Part 5: Switch it on (5 minutes)</h3>
<h4 id="g15">Step 15: Choose what to send, and switch on</h4>
<ol>
    <li>In <a href="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'index'], ['tab' => 'setup']) }}#wa_details">Set-up and settings</a>:
        tick the messages to send, choose the reminder time (the evening before, for example 18:00) and the default language.</li>
    <li>Tick <strong>Send automatic WhatsApp messages</strong> → <span class="wa-menu">Save</span>. All the checklist ticks should now be green.</li>
</ol>

<h4 id="g16">Step 16: Ask patients</h4>
<ol>
    <li>On each patient's file (<span class="wa-menu">Edit</span>), tick <strong>Agrees to receive appointment messages on WhatsApp</strong>,
        after asking the patient. You can choose French or English for that patient.</li>
    <li>Patients can tick it themselves in the patient portal (<span class="wa-menu">Profile → Edit</span>).</li>
    <li>A patient who replies STOP is unticked automatically and gets no more messages.</li>
</ol>

<h4 id="g17">Step 17: Final check</h4>
<ol>
    <li>Register yourself as a patient (your own number), tick the WhatsApp agreement, and book yourself an appointment for tomorrow.
        You receive the confirmation straight away.</li>
    <li>After the reminder time, you receive the reminder with its two buttons. Tap "I cannot come".</li>
    <li>In <span class="wa-menu">Appointments</span>, the booking shows "Cannot come", and the answer waits in <span class="wa-menu">WhatsApp replies</span>. Everything works.</li>
</ol>

<h3>Day to day (for reception)</h3>
<ul>
    <li><strong>In the diary</strong>, each booking shows its message:
        <i class="fas fa-check" style="color:#94a3b8;"></i> sent, <i class="fas fa-check-double" style="color:#94a3b8;"></i> delivered,
        <i class="fas fa-check-double" style="color:#2563eb;"></i> read, <i class="fas fa-times-circle" style="color:#dc2626;"></i> failed
        (hover over it to see why), and the patient's answer: <span class="label label-success">Will come</span> or <span class="label label-danger">Cannot come</span>.</li>
    <li><strong>WhatsApp replies</strong> (button on the appointment pages) lists what patients wrote. Move or cancel the booking if needed,
        answer them (possible within 24 hours of their message), then click <span class="wa-menu">Done</span>.</li>
    <li>When a message fails, or for a patient who has not agreed, the <span class="wa-menu">WhatsApp</span> button still opens WhatsApp on your phone with the message ready, as before.</li>
</ul>

<h3>If something goes wrong</h3>
<table class="table table-condensed table-bordered" style="font-size:13px;">
    <thead><tr><th style="width:40%;">PosInnova says</th><th>What to do</th></tr></thead>
    <tbody>
        <tr><td>The access token has expired or is not valid</td><td>Make a permanent token (step 8) and paste it again.</td></tr>
        <tr><td>The template does not exist or is not approved in this language</td><td>Check the name is exactly the same (step 14) and that it is approved in both French and English.</td></tr>
        <tr><td>This number is not in the test list</td><td>You are still on Meta's test number: add the number in step 4, or switch to the clinic's number (steps 5 to 7).</td></tr>
        <tr><td>The number is not registered</td><td>Step 7.</td></tr>
        <tr><td>Payment problem</td><td>Step 9: add or update the card.</td></tr>
        <tr><td>The patient's number is not on WhatsApp</td><td>Call or text the patient; check the number on their file.</td></tr>
        <tr><td>"Webhook" stays grey</td><td>Step 11: copy the address and the token again exactly; the site must open in https.</td></tr>
        <tr><td>Replies do not arrive</td><td>Steps 11 to 13: subscribe to "messages", click Connect webhook, and put the app Live.</td></tr>
    </tbody>
</table>
<p class="text-muted">@lang('clinic.wa_new_token_help')
    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'newVerifyToken']) }}" style="display:inline;" onsubmit="return confirm(this.dataset.confirm);" data-confirm="{{ __('clinic.wa_new_token_confirm') }}">@csrf
        <button type="submit" class="btn btn-xs btn-default">@lang('clinic.wa_new_token')</button></form></p>
