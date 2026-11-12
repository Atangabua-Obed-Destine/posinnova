{{-- Guide de mise en place WhatsApp (français). Chaque valeur à saisir chez Meta a un bouton Copier. --}}
@php
    $tpl_samples = ['Marie', \App\Business::where('id', $acc->business_id)->value('name') ?: 'Clinique', 'Dr Paul', 'Friday 13 November 2026', '10:00'];
    $tpl_samples_fr = ['Marie', $tpl_samples[1], 'Dr Paul', 'vendredi 13 novembre 2026', '10:00'];
@endphp

<div class="wa-tip" style="font-size:14px;">
    <strong>À quoi ça sert.</strong> Une fois en place, PosInnova envoie seul des messages WhatsApp aux patients : une confirmation quand un
    rendez-vous est pris, un rappel la veille (avec deux boutons, « Je viendrai » et « Je ne peux pas venir »), et un message quand un
    rendez-vous est déplacé. Les réponses des patients arrivent dans <span class="wa-menu">Rendez-vous → Réponses WhatsApp</span>.
    Seuls les patients qui ont accepté reçoivent ces messages, et chacun peut les arrêter en répondant STOP.
</div>

<h3>Avant de commencer : ce qu'il vous faut</h3>
<ul>
    <li><strong>Un numéro de téléphone pour les messages de la clinique.</strong> Le plus simple : <strong>une nouvelle carte SIM réservée à cet usage</strong>.
        Elle doit pouvoir recevoir un SMS ou un appel (Meta y envoie un code une fois).
        <div class="wa-warn">Le numéro ne doit <strong>pas</strong> déjà être utilisé dans l'application WhatsApp ou WhatsApp Business.
            Si c'est le cas, ouvrez d'abord WhatsApp sur ce téléphone → <span class="wa-menu">Paramètres → Compte → Supprimer mon compte</span>
            (les discussions WhatsApp de ce téléphone sont perdues). C'est pourquoi une nouvelle SIM est plus simple.</div></li>
    <li><strong>Un compte Facebook</strong> (le vôtre) pour vous connecter aux sites de Meta.</li>
    <li>Le <strong>nom officiel, l'adresse et l'e-mail</strong> de la clinique.</li>
    <li>Une <strong>carte bancaire Visa ou Mastercard</strong>. Meta facture chaque message, environ 2 à 3 FCFA par message vers un numéro camerounais.</li>
    <li>Environ <strong>une heure</strong>, puis un peu d'attente pendant que Meta valide le nom de la clinique et les textes (en général quelques minutes à un jour).</li>
    <li>Un ordinateur, et cette page ouverte dans un autre onglet : vous allez copier des valeurs d'un côté à l'autre.</li>
</ul>
<div class="wa-tip">Meta renomme parfois ses menus. Si un nom ci-dessous ne correspond pas exactement, cherchez le plus proche : les étapes restent les mêmes.</div>

<h3>Partie 1 : Compte Meta et application (environ 20 minutes)</h3>

<h4 id="g1">Étape 1 : Créer le portefeuille d'entreprise de la clinique</h4>
<ol>
    <li>Allez sur <a href="https://business.facebook.com" target="_blank" rel="noopener">business.facebook.com</a> et connectez-vous avec votre compte Facebook.</li>
    <li>Cliquez sur <span class="wa-menu">Créer un compte</span> (ou « Créer un portefeuille business »).</li>
    <li>Saisissez le nom de la clinique, votre nom et l'e-mail de la clinique, puis confirmez l'e-mail envoyé par Meta.</li>
</ol>
<div class="wa-tip">La clinique a déjà un portefeuille d'entreprise (pour sa page Facebook, par exemple) ? Utilisez-le et passez cette étape.</div>

<h4 id="g2">Étape 2 : Créer l'application</h4>
<ol>
    <li>Allez sur <a href="https://developers.facebook.com" target="_blank" rel="noopener">developers.facebook.com</a> → <span class="wa-menu">Commencer</span>
        (la première fois, Meta vous demande de vous inscrire comme développeur et de confirmer votre numéro par SMS).</li>
    <li>Cliquez sur <span class="wa-menu">Mes apps</span> → <span class="wa-menu">Créer une app</span>.</li>
    <li>Nom de l'app : par exemple <code>{{ \App\Business::where('id', $acc->business_id)->value('name') }} messages</code>. E-mail de contact : celui de la clinique.</li>
    <li>Quand Meta demande à quoi sert l'app (le « cas d'utilisation »), choisissez <span class="wa-menu">Communiquer avec les clients via WhatsApp</span>.
        Si vous ne voyez que des types d'app, choisissez <span class="wa-menu">Entreprise</span>.</li>
    <li>Choisissez le portefeuille de l'étape 1, puis <span class="wa-menu">Créer l'app</span>.</li>
</ol>

<h4 id="g3">Étape 3 : Copier les informations du compte dans PosInnova</h4>
<ol>
    <li>Dans l'app, ouvrez <span class="wa-menu">WhatsApp → Configuration de l'API</span> (« API Setup ») dans le menu de gauche.
        Meta vous donne un <strong>numéro de test gratuit</strong> pour essayer.</li>
    <li>Cliquez sur <span class="wa-menu">Générer un jeton d'accès</span> et acceptez. Copiez le long jeton (il commence par EAA...).</li>
    <li>Sur la même page, copiez l'<strong>identifiant du numéro de téléphone</strong> (« Phone number ID », sous « De ») et l'<strong>identifiant du compte WhatsApp Business</strong>.</li>
    <li>Dans PosInnova, onglet <a href="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'index'], ['tab' => 'setup']) }}#wa_details">Mise en place et réglages</a> :
        collez-les dans <em>Identifiant du numéro</em>, <em>Identifiant du compte WhatsApp Business</em> et <em>Jeton d'accès</em> → <span class="wa-menu">Enregistrer</span>.</li>
    <li>Cliquez sur <span class="wa-menu">Tester la connexion</span>. Le numéro de test doit apparaître avec une coche verte.</li>
</ol>
<div class="wa-tip">Ce premier jeton ne dure qu'environ un jour. C'est suffisant pour les essais ; l'étape 8 le remplace par un jeton permanent.</div>

<h4 id="g4">Étape 4 : Vous envoyer un premier message</h4>
<ol>
    <li>Toujours dans <span class="wa-menu">WhatsApp → Configuration de l'API</span>, sous « À », cliquez sur <span class="wa-menu">Gérer la liste des numéros</span>
        et ajoutez <strong>votre propre numéro WhatsApp</strong>. Meta vous envoie un code sur WhatsApp : saisissez-le.
        (Avec le numéro de test, Meta ne permet d'écrire qu'aux 5 numéros ajoutés ici.)</li>
    <li>Dans PosInnova, étape « Envoyer un message de test » : saisissez votre numéro, gardez <code>hello_world</code>, et cliquez sur <span class="wa-menu">Envoyer le test</span>.</li>
    <li>Vous devez recevoir « Hello World » sur WhatsApp en quelques secondes. Bravo : la connexion fonctionne.</li>
</ol>

<h3>Partie 2 : Le vrai numéro de la clinique et une clé permanente (environ 20 minutes)</h3>

<h4 id="g5">Étape 5 : Ajouter le numéro de la clinique</h4>
<ol>
    <li>Dans <span class="wa-menu">WhatsApp → Configuration de l'API</span>, cliquez sur <span class="wa-menu">Ajouter un numéro de téléphone</span>.</li>
    <li><strong>Nom d'affichage</strong> : le nom de la clinique tel que les patients le verront. Il doit correspondre au vrai nom de la clinique (Meta le vérifie).</li>
    <li>Catégorie : <span class="wa-menu">Médical et santé</span>. Puis saisissez le numéro et choisissez SMS ou appel pour recevoir le code. Saisissez le code.</li>
</ol>
<div class="wa-tip">Meta vérifie le nom d'affichage, en général dans la journée. Vous pouvez continuer les étapes suivantes en attendant.</div>

<h4 id="g6">Étape 6 : Utiliser le vrai numéro dans PosInnova</h4>
<ol>
    <li>De retour dans <span class="wa-menu">WhatsApp → Configuration de l'API</span>, choisissez le numéro de la clinique dans la liste « De » et copiez son
        <strong>identifiant du numéro</strong> (il est différent de celui du numéro de test).</li>
    <li>Dans PosInnova, remplacez l'identifiant du numéro par celui-ci → <span class="wa-menu">Enregistrer</span> → <span class="wa-menu">Tester la connexion</span>.
        La liste affiche maintenant le numéro de la clinique.</li>
</ol>

<h4 id="g7">Étape 7 : Enregistrer le numéro pour l'envoi</h4>
<ol>
    <li>Choisissez un <strong>code PIN à 6 chiffres</strong> et notez-le en lieu sûr (il protège le numéro : Meta le demande si le numéro est un jour déplacé).</li>
    <li>Dans PosInnova, étape « Numéro enregistré pour l'envoi » : saisissez le PIN → <span class="wa-menu">Enregistrer le numéro</span>.</li>
</ol>
<div class="wa-tip">Si PosInnova indique que le numéro a déjà un PIN, utilisez ce PIN ; si personne ne le connaît, changez-le chez Meta :
    <span class="wa-menu">WhatsApp Manager → Numéros de téléphone → (le numéro) → Vérification en deux étapes</span>.</div>

<h4 id="g8">Étape 8 : Créer un jeton d'accès permanent</h4>
<ol>
    <li>Allez sur <a href="https://business.facebook.com/settings" target="_blank" rel="noopener">business.facebook.com/settings</a>
        (Paramètres de l'entreprise) → <span class="wa-menu">Utilisateurs → Utilisateurs système</span> → <span class="wa-menu">Ajouter</span>.</li>
    <li>Nom : <code>posinnova</code>. Rôle : <span class="wa-menu">Admin</span> → <span class="wa-menu">Créer l'utilisateur système</span>.</li>
    <li>Le nouvel utilisateur sélectionné, cliquez sur <span class="wa-menu">Attribuer des éléments</span> :
        <ul>
            <li><span class="wa-menu">Apps</span> → votre app → activez <strong>Contrôle total</strong> ;</li>
            <li><span class="wa-menu">Comptes WhatsApp</span> → le compte de la clinique → activez <strong>Contrôle total</strong> → <span class="wa-menu">Attribuer</span>.</li>
        </ul></li>
    <li>Cliquez sur <span class="wa-menu">Générer un nouveau jeton</span> → choisissez votre app → Expiration : <strong>Jamais</strong> →
        cochez les autorisations <code>whatsapp_business_messaging</code> et <code>whatsapp_business_management</code> → <span class="wa-menu">Générer le jeton</span>.</li>
    <li><strong>Copiez le jeton tout de suite</strong> (Meta ne l'affiche qu'une fois), collez-le dans la case <em>Jeton d'accès</em> de PosInnova → <span class="wa-menu">Enregistrer</span> → <span class="wa-menu">Tester la connexion</span>.</li>
</ol>
<div class="wa-warn">Gardez ce jeton secret : quiconque l'a peut envoyer des messages au nom de la clinique. PosInnova le conserve chiffré et ne l'affiche plus jamais.</div>

<h4 id="g9">Étape 9 : Ajouter un moyen de paiement</h4>
<ol>
    <li>Dans les <a href="https://business.facebook.com/settings" target="_blank" rel="noopener">Paramètres de l'entreprise</a> → <span class="wa-menu">Facturation et paiements</span>
        (ou <span class="wa-menu">WhatsApp Manager → Paramètres → Moyens de paiement</span>) → <span class="wa-menu">Ajouter un moyen de paiement</span>.</li>
    <li>Saisissez la carte Visa ou Mastercard de la clinique. Sans elle, les messages aux patients ne partent pas.</li>
</ol>

<h3>Partie 3 : Permettre à Meta de répondre à PosInnova (environ 10 minutes)</h3>

<h4 id="g10">Étape 10 : Copier la clé secrète de l'app</h4>
<ol>
    <li><a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com/apps</a> → votre app →
        <span class="wa-menu">Paramètres de l'app → Général</span>.</li>
    <li>À côté de <strong>Clé secrète</strong> (« App secret »), cliquez sur <span class="wa-menu">Afficher</span> (Meta demande votre mot de passe Facebook) et copiez-la.</li>
    <li>Collez-la dans la case <em>Clé secrète de l'app</em> de PosInnova → <span class="wa-menu">Enregistrer</span>. PosInnova s'en sert pour vérifier que les messages viennent bien de Meta.</li>
</ol>

<h4 id="g11">Étape 11 : Régler le webhook (le lien de Meta vers PosInnova)</h4>
<ol>
    <li>Dans l'app : <span class="wa-menu">WhatsApp → Configuration</span> → section Webhook, <span class="wa-menu">Modifier</span>.</li>
    <li>Copiez ces deux valeurs chez Meta, puis cliquez sur <span class="wa-menu">Vérifier et enregistrer</span> :
        @include('clinic.whatsapp.partials.copy', ['label' => 'URL de rappel (Callback URL)', 'text' => $webhook_url])
        @include('clinic.whatsapp.partials.copy', ['label' => 'Jeton de vérification (Verify token)', 'text' => $acc->verify_token])
        La liste de PosInnova affiche alors « Meta a vérifié l'adresse du webhook ».</li>
    <li>Sur la même page, sous <span class="wa-menu">Champs du webhook</span> → <span class="wa-menu">Gérer</span>, abonnez-vous à
        <code>messages</code> et <code>message_template_status_update</code>.</li>
</ol>

<h4 id="g12">Étape 12 : Connecter le webhook</h4>
<ol>
    <li>Dans PosInnova, étape « Webhook connecté », cliquez sur <span class="wa-menu">Connecter le webhook</span>.
        Cela demande à Meta d'envoyer les évènements de la clinique (livraison, réponses des patients) à PosInnova.</li>
</ol>

<h4 id="g13">Étape 13 : Mettre l'app en ligne</h4>
<ol>
    <li>Dans l'app : <span class="wa-menu">Paramètres de l'app → Général</span> → <strong>URL de la politique de confidentialité</strong> : collez
        @include('clinic.whatsapp.partials.copy', ['text' => $privacy_url])
        et choisissez une catégorie (par exemple « Santé ») → <span class="wa-menu">Enregistrer les modifications</span>.</li>
    <li>En haut de la page de l'app, passez le <strong>Mode de l'app</strong> de <em>Développement</em> à <em>En ligne</em> (« Live »).
        (En mode développement, Meta ne transmet pas les réponses des vrais patients.)</li>
</ol>

<h3>Partie 4 : Les trois textes de message (10 minutes, puis validation par Meta)</h3>
<h4 id="g14">Étape 14 : Créer les modèles de message</h4>
<p>WhatsApp n'autorise une entreprise à démarrer une conversation qu'avec un texte validé par Meta, appelé <em>modèle</em> (« template »). La clinique en a besoin de trois.
    Pour chacun ci-dessous :</p>
<ol>
    <li>Ouvrez <a href="https://business.facebook.com/wa/manage/message-templates/" target="_blank" rel="noopener">WhatsApp Manager → Modèles de message</a>
        → <span class="wa-menu">Créer un modèle</span>.</li>
    <li>Catégorie : <strong>Utilitaire</strong> (« Utility »). Nom : copiez-le ci-dessous <strong>exactement</strong>. Langue : <strong>Français</strong>.</li>
    <li>Corps : collez le texte français. Quand Meta parle des variables, choisissez le type <strong>Nombre</strong> (@{{1}}, @{{2}}...)
        et collez les exemples de valeurs donnés ci-dessous.</li>
    <li>Pour le rappel seulement : ajoutez le <strong>pied de page</strong>, et deux boutons <strong>Réponse rapide</strong>, dans cet ordre.</li>
    <li><span class="wa-menu">Envoyer</span>. Puis ouvrez le même modèle, <span class="wa-menu">Ajouter une langue</span> → <strong>Anglais</strong>, et faites de même avec le texte anglais.</li>
</ol>
<div class="wa-tip">Ne modifiez pas le texte : une phrase publicitaire en plus (une remise, une offre) fait refuser le modèle ou le classe en « Marketing », plus cher.</div>

@foreach (\App\Utils\ClinicWhatsApp::KINDS as $kind)
    <div class="wa-tpl">
        <div class="wa-tpl-head">{{ $loop->iteration }}. {{ __('clinic.wa_kind_' . $kind, [], 'fr') }}</div>
        @include('clinic.whatsapp.partials.copy', ['label' => 'Nom du modèle', 'text' => $acc->templateFor($kind)])
        <div class="row">
            <div class="col-md-6">
                @include('clinic.whatsapp.partials.copy', ['label' => 'Corps : français', 'text' => __('clinic.wa_tpl_' . $kind, [], 'fr'), 'multi' => 1])
                @if ($kind == 'reminder')
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Pied de page : français', 'text' => __('clinic.wa_tpl_footer', [], 'fr')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Bouton 1 (réponse rapide) : français', 'text' => __('clinic.wa_btn_coming', [], 'fr')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Bouton 2 (réponse rapide) : français', 'text' => __('clinic.wa_btn_not_coming', [], 'fr')])
                @endif
                <div class="wa-copy-label">Exemples de valeurs (français)</div>
                <ol style="font-size:13px; margin-bottom:0;">@foreach ($tpl_samples_fr as $s)<li><code>{{ $s }}</code></li>@endforeach</ol>
            </div>
            <div class="col-md-6">
                @include('clinic.whatsapp.partials.copy', ['label' => 'Corps : anglais', 'text' => __('clinic.wa_tpl_' . $kind, [], 'en'), 'multi' => 1])
                @if ($kind == 'reminder')
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Pied de page : anglais', 'text' => __('clinic.wa_tpl_footer', [], 'en')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Bouton 1 (réponse rapide) : anglais', 'text' => __('clinic.wa_btn_coming', [], 'en')])
                    @include('clinic.whatsapp.partials.copy', ['label' => 'Bouton 2 (réponse rapide) : anglais', 'text' => __('clinic.wa_btn_not_coming', [], 'en')])
                @endif
                <div class="wa-copy-label">Exemples de valeurs (anglais)</div>
                <ol style="font-size:13px; margin-bottom:0;">@foreach ($tpl_samples as $s)<li><code>{{ $s }}</code></li>@endforeach</ol>
            </div>
        </div>
    </div>
@endforeach
<p>La validation prend en général quelques minutes, parfois jusqu'à un jour. Cliquez ensuite sur <span class="wa-menu">Vérifier les modèles</span> dans la liste de PosInnova :
    chacun doit indiquer <span class="label label-success">Validé</span>. Envoyez-vous alors un exemple de rappel depuis l'étape « Envoyer un message de test ».</p>

<h3>Partie 5 : Mettre en marche (5 minutes)</h3>
<h4 id="g15">Étape 15 : Choisir quoi envoyer, et activer</h4>
<ol>
    <li>Dans <a href="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'index'], ['tab' => 'setup']) }}#wa_details">Mise en place et réglages</a> :
        cochez les messages à envoyer, choisissez l'heure du rappel (la veille au soir, par exemple 18:00) et la langue par défaut.</li>
    <li>Cochez <strong>Envoyer les messages WhatsApp automatiques</strong> → <span class="wa-menu">Enregistrer</span>. Toutes les coches de la liste doivent maintenant être vertes.</li>
</ol>

<h4 id="g16">Étape 16 : Demander l'accord des patients</h4>
<ol>
    <li>Sur le dossier de chaque patient (<span class="wa-menu">Modifier</span>), cochez <strong>Accepte de recevoir les messages de rendez-vous sur WhatsApp</strong>,
        après l'avoir demandé au patient. Vous pouvez choisir le français ou l'anglais pour ce patient.</li>
    <li>Les patients peuvent le cocher eux-mêmes sur le portail patient (<span class="wa-menu">Profil → Modifier</span>).</li>
    <li>Un patient qui répond STOP est décoché automatiquement et ne reçoit plus de messages.</li>
</ol>

<h4 id="g17">Étape 17 : Vérification finale</h4>
<ol>
    <li>Enregistrez-vous comme patient (votre propre numéro), cochez l'accord WhatsApp, et prenez-vous un rendez-vous pour demain.
        Vous recevez aussitôt la confirmation.</li>
    <li>Après l'heure du rappel, vous recevez le rappel avec ses deux boutons. Appuyez sur « Je ne peux pas venir ».</li>
    <li>Dans <span class="wa-menu">Rendez-vous</span>, le rendez-vous affiche « Ne viendra pas », et la réponse attend dans <span class="wa-menu">Réponses WhatsApp</span>. Tout fonctionne.</li>
</ol>

<h3>Au quotidien (pour l'accueil)</h3>
<ul>
    <li><strong>Dans l'agenda</strong>, chaque rendez-vous affiche son message :
        <i class="fas fa-check" style="color:#94a3b8;"></i> envoyé, <i class="fas fa-check-double" style="color:#94a3b8;"></i> reçu,
        <i class="fas fa-check-double" style="color:#2563eb;"></i> lu, <i class="fas fa-times-circle" style="color:#dc2626;"></i> échec
        (survolez-le pour voir pourquoi), et la réponse du patient : <span class="label label-success">Viendra</span> ou <span class="label label-danger">Ne viendra pas</span>.</li>
    <li><strong>Réponses WhatsApp</strong> (bouton sur les pages des rendez-vous) liste ce que les patients ont écrit. Déplacez ou annulez le rendez-vous si besoin,
        répondez-leur (possible dans les 24 heures qui suivent leur message), puis cliquez sur <span class="wa-menu">Traité</span>.</li>
    <li>Quand un message échoue, ou pour un patient qui n'a pas donné son accord, le bouton <span class="wa-menu">WhatsApp</span> ouvre toujours WhatsApp sur votre téléphone avec le message prêt, comme avant.</li>
</ul>

<h3>En cas de problème</h3>
<table class="table table-condensed table-bordered" style="font-size:13px;">
    <thead><tr><th style="width:40%;">PosInnova indique</th><th>Que faire</th></tr></thead>
    <tbody>
        <tr><td>Le jeton d'accès a expiré ou n'est pas valide</td><td>Créez un jeton permanent (étape 8) et collez-le à nouveau.</td></tr>
        <tr><td>Le modèle n'existe pas ou n'est pas validé dans cette langue</td><td>Vérifiez que le nom est exactement le même (étape 14) et qu'il est validé en français et en anglais.</td></tr>
        <tr><td>Ce numéro n'est pas dans la liste de test</td><td>Vous êtes encore sur le numéro de test de Meta : ajoutez le numéro à l'étape 4, ou passez au numéro de la clinique (étapes 5 à 7).</td></tr>
        <tr><td>Le numéro n'est pas enregistré</td><td>Étape 7.</td></tr>
        <tr><td>Problème de paiement</td><td>Étape 9 : ajoutez ou mettez à jour la carte.</td></tr>
        <tr><td>Le numéro du patient n'est pas sur WhatsApp</td><td>Appelez ou envoyez un SMS au patient ; vérifiez le numéro sur son dossier.</td></tr>
        <tr><td>« Webhook » reste gris</td><td>Étape 11 : recopiez exactement l'adresse et le jeton ; le site doit s'ouvrir en https.</td></tr>
        <tr><td>Les réponses n'arrivent pas</td><td>Étapes 11 à 13 : abonnez-vous à « messages », cliquez sur Connecter le webhook, et mettez l'app en ligne.</td></tr>
    </tbody>
</table>
<p class="text-muted">@lang('clinic.wa_new_token_help')
    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'newVerifyToken']) }}" style="display:inline;" onsubmit="return confirm(this.dataset.confirm);" data-confirm="{{ __('clinic.wa_new_token_confirm') }}">@csrf
        <button type="submit" class="btn btn-xs btn-default">@lang('clinic.wa_new_token')</button></form></p>
