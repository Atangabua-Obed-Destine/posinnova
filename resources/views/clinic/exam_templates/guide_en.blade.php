{{-- Guide of the examination forms, in English. $img(name) = the screenshot's address. --}}

<div class="box box-solid"><div class="box-body">
    <p style="margin-top:0;">An <strong>examination form</strong> is the specialty's own exam sheet, inside the consultation: pictures the doctor
        draws on (ears, eardrums, nose, eyes…) and findings he ticks with one click (for example "Central perforation" on the right,
        "Intact" on the left). It replaces the paper sheet: everything is saved with the consultation, printed on the report, and shown
        again at the patient's next visit.</p>
    <p style="margin-bottom:0;">Two forms come ready: <strong>ENT</strong> (ears, nose, mouth and throat, neck, larynx) and <strong>Eye</strong>
        (vision, eye pressure, front of the eye, back of the eye). The clinic can change them and make its own for any specialty.</p>
</div></div>

{{-- ======================================================== SET-UP ======================================================== --}}
<div class="box box-solid" id="gd_setup"><div class="box-body">
    <h2><i class="fas fa-cog"></i> Part 1 — Setting up the forms <small class="text-muted" style="font-size:13px;">(the person who manages the clinic)</small></h2>
    <p>Only users allowed to manage the clinic settings see this part of the menu. Doctors do not need it: they just use the forms.</p>

    <h3>1. Open the list of forms</h3>
    <ol class="gd-steps">
        <li>In the menu, open <span class="gd-b">Clinic setup</span> then <span class="gd-b">Examination forms</span>.</li>
        <li>The first time, the <strong>ENT</strong> and <strong>Eye</strong> forms are already there. Each line shows the specialties that open the form, what it contains, and whether it is in use.</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('01_list') }}" alt="The list of examination forms" loading="lazy"><figcaption>The list: Edit, Duplicate, Stop using, Delete.</figcaption></figure>
    <table class="gd-t">
        <tr><th style="width:28%;">Button</th><th>What it does</th></tr>
        <tr><td><span class="gd-b">New form</span></td><td>Starts an empty form.</td></tr>
        <tr><td><span class="gd-b">Add a ready-made form</span></td><td>Adds a new copy of the ENT or Eye form (useful if you deleted it, or want a second version).</td></tr>
        <tr><td><span class="gd-b">Edit</span></td><td>Opens the form in the builder.</td></tr>
        <tr><td><span class="gd-b">Duplicate</span></td><td>Makes a copy to change (for example ENT → "ENT child"). The copy is not in use until you tick <em>In use</em>.</td></tr>
        <tr><td><span class="gd-b">Stop using</span> / <span class="gd-b">Use it</span></td><td>Hides the form from doctors, or shows it again. Nothing is lost.</td></tr>
        <tr><td><i class="fas fa-trash text-danger"></i></td><td>Deletes the form. Consultations already done keep what was recorded on it.</td></tr>
    </table>

    <h3>2. The builder: what you see</h3>
    <figure class="gd-shot"><img src="{{ $img('02_builder') }}" alt="The form builder" loading="lazy"><figcaption>Left: the blocks you can add. Top: the name and the specialties. Below: the form, block by block, in order.</figcaption></figure>
    <ul>
        <li><strong>Name of the form</strong> — what the doctor sees in the list, and the title of the printed report (for example "ENT clinical assessment").</li>
        <li><strong>Opens for the specialties</strong> — when the doctor chooses one of these specialties in the consultation, this form opens by itself.
            Pick them in the list, or type a new one and press Enter. Write it <em>exactly</em> as it is in the doctors' Specialty list.</li>
        <li><strong>In use</strong> — untick to hide the form from doctors.</li>
        <li><span class="gd-b"><i class="fas fa-eye"></i> Preview</span> shows the form exactly as the doctor will see it (you can even draw and click in it).</li>
        <li><span class="gd-b"><i class="fas fa-save"></i> Save</span> — <strong>nothing is kept until you click Save.</strong> If you try to leave with changes not saved, the browser warns you.</li>
    </ul>

    <h3>3. The kinds of blocks</h3>
    <table class="gd-t">
        <tr><th style="width:22%;">Block</th><th style="width:38%;">Use it for</th><th>Example</th></tr>
        <tr><td><i class="fas fa-heading"></i> <strong>Section title</strong></td><td>Starting a new part of the form.</td><td>Ear, Nose, Larynx</td></tr>
        <tr><td><i class="fas fa-pencil-alt"></i> <strong>Drawing</strong></td><td>A picture the doctor draws on (or a blank page).</td><td>Right eardrum, Nose, Fundus</td></tr>
        <tr><td><i class="fas fa-dot-circle"></i> <strong>One answer</strong></td><td>A question with only one possible answer, picked with one click.</td><td>Rinne: Positive / Negative</td></tr>
        <tr><td><i class="fas fa-check-square"></i> <strong>Several answers</strong></td><td>A list of findings: the doctor ticks all that apply.</td><td>Eardrum: Perforated, Dull, Retracted…</td></tr>
        <tr><td><i class="fas fa-i-cursor"></i> <strong>Short answer</strong></td><td>A few words typed by the doctor.</td><td>Cup-to-disc ratio: 0.3</td></tr>
        <tr><td><i class="fas fa-align-left"></i> <strong>Notes</strong></td><td>Free text, several lines.</td><td>Ear notes</td></tr>
        <tr><td><i class="fas fa-hashtag"></i> <strong>Number</strong></td><td>A measure, with its unit.</td><td>Eye pressure: 18 mmHg</td></tr>
    </table>

    <h3>4. Adding, moving, changing and removing blocks</h3>
    <ol class="gd-steps">
        <li><strong>Add a block:</strong> drag it from the left into the form, where you want it. Or simply <strong>click</strong> it: its settings open straight away, and under <strong>Where it goes</strong> you choose the section or the block it comes after (it is at the end until you choose).</li>
        <li><strong>Move a block:</strong> hold it with the mouse and drag it to its new place. The others make room. Without dragging (easier on a phone): click its <i class="fas fa-pen"></i> pencil and choose <strong>Where it goes</strong>. A section title moves with all its blocks. On a phone or tablet you can also keep your finger on the block a moment (it lifts slightly), then drag it; a quick swipe just scrolls the page.</li>
        <li><strong>Change a block:</strong> double-click it, or click its <i class="fas fa-pen"></i> pencil.</li>
        <li><strong>Copy a block:</strong> click <i class="fas fa-copy"></i>. The copy appears just after it (handy for "Right" then "Left").</li>
        <li><strong>Remove a block:</strong> click <i class="fas fa-trash"></i> and confirm.</li>
        <li>Click <span class="gd-b">Save</span>.</li>
    </ol>

    <h3>5. A block's settings</h3>
    <div class="gd-two">
        <figure class="gd-shot"><img src="{{ $img('03_block_settings') }}" alt="The settings of a question" loading="lazy"><figcaption>The settings of a question with several answers.</figcaption></figure>
        <div>
            <ul>
                <li><strong>Title</strong> — the question, as the doctor reads it.</li>
                <li><strong>Answers</strong> — <em>One answer</em> or <em>Several answers</em>. You can switch at any time.</li>
                <li><strong>Answers to click (one per line)</strong> — type each answer on its own line. The order here is the order on screen.</li>
                <li><strong>The doctor can also type another answer</strong> — adds a small "Other…" box after the answers, for anything not in the list.</li>
                <li><strong>Width on the page</strong> — how much of the line the block takes (see below).</li>
                <li><strong>Ask for the right and the left separately</strong> — the question appears twice, side by side, marked <span class="label" style="background:#fee2e2;color:#991b1b;">Right</span> and <span class="label" style="background:#dbeafe;color:#1e40af;">Left</span>.</li>
                <li><strong>Unit</strong> (numbers only) — for example mmHg, cm, dB.</li>
                <li><strong>Help line</strong> — an optional reminder shown under the question.</li>
            </ul>
            <p>Click <span class="gd-b">OK</span>, then <span class="gd-b">Save</span> the form.</p>
        </div>
    </div>

    <h3>6. Pictures for the drawings</h3>
    <div class="gd-two">
        <figure class="gd-shot"><img src="{{ $img('04_drawing_settings') }}" alt="Choosing the picture of a drawing" loading="lazy"><figcaption>Choose a picture, a blank page, or add your own.</figcaption></figure>
        <div>
            <ol class="gd-steps">
                <li>Open the settings of a <strong>Drawing</strong> block.</li>
                <li>Click the picture you want. The system comes with the ENT drawings of the clinic's paper form (ears, eardrums, nose, mouth, neck, larynx) and eye drawings.
                    <strong>Blank page</strong> gives an empty page to draw on.</li>
                <li>To use your own picture, click <span class="gd-b"><i class="fas fa-upload"></i> Add a picture</span> and choose a PNG or JPG file (up to 5 MB). It is added to the pictures and chosen.</li>
                <li>Click <span class="gd-b">OK</span>, then <span class="gd-b">Save</span>.</li>
            </ol>
            <div class="gd-tip"><i class="fas fa-lightbulb"></i> <strong>Good pictures:</strong> black lines on a white background, without colours or shadows, like a drawing on paper. A scan or a photo of a drawing works if it is clear and straight.</div>
        </div>
    </div>

    <h3>7. Placing blocks side by side (width)</h3>
    <p>Blocks fill the line from left to right, then go to the next line. Use the width to put them side by side:</p>
    <table class="gd-t">
        <tr><th style="width:30%;">You want</th><th>Choose</th></tr>
        <tr><td>Four pictures in one row (two ears and two eardrums)</td><td>each Drawing <strong>One quarter</strong></td></tr>
        <tr><td>Right and left pictures next to each other (eyes, fundi)</td><td>each <strong>Half</strong></td></tr>
        <tr><td>A picture with its first question beside it</td><td>Drawing <strong>One third</strong> + question <strong>Two thirds</strong></td></tr>
        <tr><td>A long list of answers</td><td><strong>Whole width</strong></td></tr>
    </table>
    <p>On a phone, the blocks go one under the other by themselves.</p>

    <h3>8. Making a form for another specialty (example: gynaecology)</h3>
    <ol class="gd-steps">
        <li>Click <span class="gd-b">New form</span> (or <span class="gd-b">Duplicate</span> a form that is close to what you want).</li>
        <li>Type the <strong>name</strong>, for example "Gynaecological examination".</li>
        <li>In <strong>Opens for the specialties</strong>, choose "Gynaecology &amp; obstetrics".</li>
        <li>Add a <strong>Section title</strong> ("Abdomen"), then the blocks of that part, then the next section, and so on.</li>
        <li>Click <span class="gd-b">Preview</span> to check, then <span class="gd-b">Save</span>.</li>
        <li>Open a consultation, choose the specialty: the new form appears.</li>
    </ol>
    <div class="gd-warn"><i class="fas fa-shield-alt"></i> <strong>Changing a form is safe.</strong> Each consultation keeps its own copy of the form as it was when the doctor filled it in. Changing or deleting a form later never changes consultations already recorded.</div>
</div></div>

{{-- ======================================================== DOCTOR ======================================================== --}}
<div class="box box-solid" id="gd_doctor"><div class="box-body">
    <h2><i class="fas fa-user-md"></i> Part 2 — Using a form during the consultation <small class="text-muted" style="font-size:13px;">(doctors)</small></h2>

    <h3>1. Open the form</h3>
    <ol class="gd-steps">
        <li>Start the consultation as usual: <span class="gd-b">Call in</span> in the Doctor queue, or <span class="gd-b">New Consultation</span> on the visit.</li>
        <li>Set <strong>Consultation type</strong> to <em>Specialist</em> and choose the <strong>Specialty</strong> (for example <em>ENT (ear, nose &amp; throat)</em>).</li>
        <li>The <strong>Examination form</strong> opens by itself under the vitals.</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('05_specialty') }}" alt="Choosing the specialty" loading="lazy"></figure>
    <p>You can also choose any form yourself in the list at the top right of the <strong>Examination form</strong> box (or <em>No examination form</em>).
        If you change the form after drawing or ticking something, the system asks first, because what was done on the first form would be lost.</p>
    <figure class="gd-shot"><img src="{{ $img('06_ent_form') }}" alt="The ENT form in the consultation" loading="lazy"><figcaption>The ENT form: drawings with their tools, findings for the right and the left.</figcaption></figure>

    <h3>2. Drawing</h3>
    <div class="gd-two">
        <figure class="gd-shot" style="flex:0 0 260px;"><img src="{{ $img('07_tools') }}" alt="A drawing and its tools" loading="lazy"></figure>
        <div>
            <p>Draw straight on the picture with the <strong>mouse</strong> (hold the left button), a <strong>finger</strong> on a touch screen or tablet, or a <strong>pen / stylus</strong> (pressing harder gives a thicker line). The tools under each picture:</p>
            <table class="gd-t">
                <tr><td style="width:90px;"><span style="color:#dc2626;">●</span> <span style="color:#2563eb;">●</span> ●</td><td>Pen: red, blue or black</td></tr>
                <tr><td><i class="fas fa-eraser"></i></td><td>Eraser: rub out part of what was drawn (never the picture itself)</td></tr>
                <tr><td><i class="fas fa-font"></i></td><td>Write a word: click where the word goes, type it, OK (e.g. "nodule")</td></tr>
                <tr><td><i class="fas fa-grip-lines"></i></td><td>Thick line on / off</td></tr>
                <tr><td><i class="fas fa-undo"></i></td><td>Undo the last line</td></tr>
                <tr><td><i class="fas fa-trash-alt"></i></td><td>Rub out everything drawn on this picture (asks first)</td></tr>
                <tr><td><i class="fas fa-expand"></i></td><td>Full screen: the picture becomes big, easier with a finger or a pen. Click it again, or press <kbd>Esc</kbd>, to come back.</td></tr>
            </table>
            <p>A small <i class="fas fa-pen" style="color:#4f46e5;"></i> appears next to the title of a picture that has a drawing.</p>
        </div>
    </div>

    <h3>3. Findings with one click</h3>
    <ul>
        <li>Click an answer to choose it: it turns blue with a ✓. Click it again to take it back.</li>
        <li><span class="label" style="background:#fee2e2;color:#991b1b;">Right</span> and <span class="label" style="background:#dbeafe;color:#1e40af;">Left</span> are answered separately.</li>
        <li>Not in the list? Type it in the <strong>Other…</strong> box at the end of the answers.</li>
        <li>Notes and numbers are typed as usual.</li>
    </ul>

    <h3>4. Photos (endoscope, camera, phone)</h3>
    <figure class="gd-shot"><img src="{{ $img('08_photos') }}" alt="Adding photos" loading="lazy"></figure>
    <ul>
        <li><span class="gd-b"><i class="fas fa-upload"></i> Add photos / PDF</span> — choose photos already on the computer or the phone (several at once). Photos and PDF only, up to 10 MB each.</li>
        <li><span class="gd-b"><i class="fas fa-camera"></i> Camera / endoscope</span> — takes the photo straight from a camera or an endoscope connected to the computer:
            the browser asks once to allow the camera (click <em>Allow</em>); choose the endoscope in the <em>Camera</em> list; click <span class="gd-b">Take a photo</span> for each picture; close the window when done.</li>
        <li>Each photo is saved at once, even before the consultation is saved. Photos are private: only the clinic's staff can open them.</li>
    </ul>

    <h3>5. Saving</h3>
    <ul>
        <li><span class="gd-b">Save and continue later</span> — keeps everything (drawings, answers, photos) while you wait for the lab or imaging. When you open the patient again (<span class="gd-b">Continue</span> in the queue or <span class="gd-b">Continue consultation</span>), the form comes back as you left it, and you can keep drawing.</li>
        <li><span class="gd-b">Finalize Consultation</span> — the consultation is finished.</li>
    </ul>
    <div class="gd-tip"><i class="fas fa-history"></i> <strong>Next visit:</strong> in <em>Previous visits</em> at the top of the consultation, the doctor sees the drawings of the earlier consultations, to compare (for example, is the perforation smaller?).</div>
</div></div>

{{-- ======================================================== PRINT ======================================================== --}}
<div class="box box-solid" id="gd_print"><div class="box-body">
    <h2><i class="fas fa-print"></i> Part 3 — The printed report</h2>
    <ol class="gd-steps">
        <li>Open the consultation (after saving it) and click <span class="gd-b"><i class="fas fa-file-medical"></i> Print the consultation</span>.</li>
        <li>The report opens in a new tab, laid out like the paper form: the clinic's name and logo, the patient (age, gender, occupation, residence, marital status, telephone), complaint, history, vitals, the examination form with the drawings and findings, photos, diagnoses, tests, the drug table, the plan and the review date.</li>
        <li>Click <span class="gd-b">Print</span> at the top (or save it as PDF from the print window).</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('09_print_button') }}" alt="The buttons of the consultation page" loading="lazy"></figure>
    <figure class="gd-shot"><img src="{{ $img('10_report') }}" alt="The printed report" loading="lazy"><figcaption>The top of a printed ENT report.</figcaption></figure>
    <p>The clinic's name and logo come from <span class="gd-b">Settings</span> → <span class="gd-b">Business Settings</span>. Change them there and every report follows.
        The patient's occupation and marital status are filled in on the patient's file (<em>Patients</em> → <em>Edit</em>).</p>
</div></div>

{{-- ======================================================== FAQ ======================================================== --}}
<div class="box box-solid" id="gd_faq"><div class="box-body">
    <h2><i class="fas fa-question-circle"></i> Common questions</h2>
    <details><summary>The form did not open by itself when I chose the specialty.</summary>
        <p>The specialty chosen is not in the form's <em>Opens for the specialties</em>. Either pick the form yourself in the list of the Examination form box, or (Clinic setup) add that specialty to the form and save. Also check the form is <em>In use</em>.</p></details>
    <details><summary>The Consultation type is "General": can I still use a form?</summary>
        <p>Yes. Choose the form yourself in the list at the top of the Examination form box.</p></details>
    <details><summary>"No camera found", or the camera does not open.</summary>
        <p>Check the camera or the endoscope is plugged in (USB or capture card), then click Camera / endoscope again. If the browser asked to allow the camera and it was refused, click the camera icon in the address bar and choose <em>Allow</em>. Then choose the right device in the Camera list.</p></details>
    <details><summary>Can videos be added?</summary>
        <p>No. Only photos and PDF files can be added.</p></details>
    <details><summary>I made a mistake on a drawing.</summary>
        <p>Click <i class="fas fa-undo"></i> to undo the last line, use the eraser <i class="fas fa-eraser"></i>, or <i class="fas fa-trash-alt"></i> to rub out everything on that picture. Until the consultation is finalized, it can be changed by continuing it.</p></details>
    <details><summary>I changed a form. Do old consultations change?</summary>
        <p>No. Each consultation keeps the form as it was when it was filled in.</p></details>
    <details><summary>Who can set up the forms?</summary>
        <p>Users whose role has the permission to manage the clinic settings (usually the administrator). Doctors only need the permission to write consultations.</p></details>
    <details><summary>Can the doctor draw on a phone or a tablet?</summary>
        <p>Yes, with a finger or a stylus. Use the full screen button <i class="fas fa-expand"></i> for a bigger picture.</p></details>
</div></div>
