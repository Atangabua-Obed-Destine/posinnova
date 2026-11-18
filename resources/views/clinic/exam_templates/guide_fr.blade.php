{{-- Guide des fiches d'examen, en français. $img(nom) = l'adresse de la capture d'écran (écrans en anglais). --}}

<div class="box box-solid"><div class="box-body">
    <p style="margin-top:0;">Une <strong>fiche d'examen</strong> est la feuille d'examen de la spécialité, dans la consultation : des images sur lesquelles
        le médecin dessine (oreilles, tympans, nez, yeux…) et des signes qu'il coche d'un clic (par exemple « Perforation centrale » à droite,
        « Intact » à gauche). Elle remplace la feuille papier : tout est enregistré avec la consultation, imprimé sur le compte rendu, et
        réaffiché à la visite suivante du patient.</p>
    <p style="margin-bottom:0;">Deux fiches sont prêtes : <strong>ORL</strong> (oreilles, nez, bouche et gorge, cou, larynx) et
        <strong>Ophtalmologie</strong> (acuité visuelle, tension oculaire, segment antérieur, fond d'œil). La clinique peut les modifier et créer
        les siennes pour n'importe quelle spécialité.</p>
    <p class="text-muted" style="margin:8px 0 0;font-size:12.5px;"><i class="fas fa-info-circle"></i> Les captures d'écran de ce guide sont en anglais ; les boutons ont les mêmes places en français.</p>
</div></div>

{{-- ======================================================== CONFIGURATION ======================================================== --}}
<div class="box box-solid" id="gd_setup"><div class="box-body">
    <h2><i class="fas fa-cog"></i> Partie 1 — Configurer les fiches <small class="text-muted" style="font-size:13px;">(la personne qui gère la clinique)</small></h2>
    <p>Seuls les utilisateurs autorisés à gérer les paramètres de la clinique voient cette partie du menu. Les médecins n'en ont pas besoin : ils utilisent simplement les fiches.</p>

    <h3>1. Ouvrir la liste des fiches</h3>
    <ol class="gd-steps">
        <li>Dans le menu, ouvrez <span class="gd-b">Configuration de la clinique</span> puis <span class="gd-b">Fiches d'examen</span>.</li>
        <li>La première fois, les fiches <strong>ORL</strong> et <strong>Ophtalmologie</strong> sont déjà là. Chaque ligne indique les spécialités qui ouvrent la fiche, son contenu, et si elle est utilisée.</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('01_list') }}" alt="La liste des fiches d'examen" loading="lazy"><figcaption>La liste : Modifier, Dupliquer, Ne plus l'utiliser, Supprimer.</figcaption></figure>
    <table class="gd-t">
        <tr><th style="width:30%;">Bouton</th><th>Ce qu'il fait</th></tr>
        <tr><td><span class="gd-b">Nouvelle fiche</span></td><td>Commence une fiche vide.</td></tr>
        <tr><td><span class="gd-b">Ajouter une fiche toute prête</span></td><td>Ajoute une nouvelle copie de la fiche ORL ou Ophtalmologie (utile si vous l'avez supprimée, ou pour une deuxième version).</td></tr>
        <tr><td><span class="gd-b">Modifier</span></td><td>Ouvre la fiche dans l'éditeur.</td></tr>
        <tr><td><span class="gd-b">Dupliquer</span></td><td>Fait une copie à modifier (par exemple ORL → « ORL enfant »). La copie n'est pas utilisée tant que vous ne cochez pas <em>Utilisée</em>.</td></tr>
        <tr><td><span class="gd-b">Ne plus l'utiliser</span> / <span class="gd-b">L'utiliser</span></td><td>Cache la fiche aux médecins, ou la remontre. Rien n'est perdu.</td></tr>
        <tr><td><i class="fas fa-trash text-danger"></i></td><td>Supprime la fiche. Les consultations déjà faites gardent ce qui y a été enregistré.</td></tr>
    </table>

    <h3>2. L'éditeur : ce que vous voyez</h3>
    <figure class="gd-shot"><img src="{{ $img('02_builder') }}" alt="L'éditeur de fiche" loading="lazy"><figcaption>À gauche : les blocs à ajouter. En haut : le nom et les spécialités. Dessous : la fiche, bloc par bloc, dans l'ordre.</figcaption></figure>
    <ul>
        <li><strong>Nom de la fiche</strong> — ce que le médecin voit dans la liste, et le titre du compte rendu imprimé (par exemple « Examen ORL »).</li>
        <li><strong>S'ouvre pour les spécialités</strong> — quand le médecin choisit une de ces spécialités dans la consultation, la fiche s'ouvre d'elle-même.
            Choisissez-les dans la liste, ou tapez-en une nouvelle puis Entrée. Écrivez-la <em>exactement</em> comme dans la liste des spécialités des médecins.</li>
        <li><strong>Utilisée</strong> — décochez pour cacher la fiche aux médecins.</li>
        <li><span class="gd-b"><i class="fas fa-eye"></i> Aperçu</span> montre la fiche exactement comme le médecin la verra (on peut même dessiner et cliquer dedans).</li>
        <li><span class="gd-b"><i class="fas fa-save"></i> Sauvegarder</span> — <strong>rien n'est gardé tant que vous ne cliquez pas sur Sauvegarder.</strong> Si vous quittez la page avec des changements non sauvegardés, le navigateur vous prévient.</li>
    </ul>

    <h3>3. Les sortes de blocs</h3>
    <table class="gd-t">
        <tr><th style="width:24%;">Bloc</th><th style="width:38%;">À utiliser pour</th><th>Exemple</th></tr>
        <tr><td><i class="fas fa-heading"></i> <strong>Titre de section</strong></td><td>Commencer une nouvelle partie de la fiche.</td><td>Oreilles, Nez, Larynx</td></tr>
        <tr><td><i class="fas fa-pencil-alt"></i> <strong>Dessin</strong></td><td>Une image sur laquelle le médecin dessine (ou une page blanche).</td><td>Tympan droit, Nez, Fond d'œil</td></tr>
        <tr><td><i class="fas fa-dot-circle"></i> <strong>Une seule réponse</strong></td><td>Une question à une seule réponse possible, choisie d'un clic.</td><td>Rinne : Positif / Négatif</td></tr>
        <tr><td><i class="fas fa-check-square"></i> <strong>Plusieurs réponses</strong></td><td>Une liste de signes : le médecin coche tous ceux qui s'appliquent.</td><td>Tympan : Perforé, Terne, Rétracté…</td></tr>
        <tr><td><i class="fas fa-i-cursor"></i> <strong>Réponse courte</strong></td><td>Quelques mots tapés par le médecin.</td><td>Rapport cup/disc : 0,3</td></tr>
        <tr><td><i class="fas fa-align-left"></i> <strong>Notes</strong></td><td>Texte libre, sur plusieurs lignes.</td><td>Notes oreilles</td></tr>
        <tr><td><i class="fas fa-hashtag"></i> <strong>Nombre</strong></td><td>Une mesure, avec son unité.</td><td>Tension oculaire : 18 mmHg</td></tr>
    </table>

    <h3>4. Ajouter, déplacer, modifier et retirer des blocs</h3>
    <ol class="gd-steps">
        <li><strong>Ajouter un bloc :</strong> glissez-le depuis la gauche dans la fiche, à l'endroit voulu. Ou <strong>cliquez</strong> simplement dessus : il est ajouté à la fin de la fiche. Ses réglages s'ouvrent tout de suite.</li>
        <li><strong>Déplacer un bloc :</strong> tenez-le avec la souris et glissez-le à sa nouvelle place. Les autres se décalent.</li>
        <li><strong>Modifier un bloc :</strong> double-cliquez dessus, ou cliquez sur son crayon <i class="fas fa-pen"></i>.</li>
        <li><strong>Copier un bloc :</strong> cliquez sur <i class="fas fa-copy"></i>. La copie apparaît juste après (pratique pour « Droite » puis « Gauche »).</li>
        <li><strong>Retirer un bloc :</strong> cliquez sur <i class="fas fa-trash"></i> et confirmez.</li>
        <li>Cliquez sur <span class="gd-b">Sauvegarder</span>.</li>
    </ol>

    <h3>5. Les réglages d'un bloc</h3>
    <div class="gd-two">
        <figure class="gd-shot"><img src="{{ $img('03_block_settings') }}" alt="Les réglages d'une question" loading="lazy"><figcaption>Les réglages d'une question à plusieurs réponses.</figcaption></figure>
        <div>
            <ul>
                <li><strong>Titre</strong> — la question, telle que le médecin la lit.</li>
                <li><strong>Réponses</strong> — <em>Une seule réponse</em> ou <em>Plusieurs réponses</em>. On peut changer à tout moment.</li>
                <li><strong>Réponses à cliquer (une par ligne)</strong> — tapez chaque réponse sur sa propre ligne. L'ordre ici est l'ordre à l'écran.</li>
                <li><strong>Le médecin peut aussi taper une autre réponse</strong> — ajoute une petite case « Autre… » après les réponses, pour ce qui n'est pas dans la liste.</li>
                <li><strong>Largeur sur la page</strong> — la place que prend le bloc sur la ligne (voir plus bas).</li>
                <li><strong>Demander la droite et la gauche séparément</strong> — la question apparaît deux fois, côte à côte, marquée <span class="label" style="background:#fee2e2;color:#991b1b;">Droite</span> et <span class="label" style="background:#dbeafe;color:#1e40af;">Gauche</span>.</li>
                <li><strong>Unité</strong> (nombres seulement) — par exemple mmHg, cm, dB.</li>
                <li><strong>Ligne d'aide</strong> — un rappel facultatif affiché sous la question.</li>
            </ul>
            <p>Cliquez sur <span class="gd-b">OK</span>, puis <span class="gd-b">Sauvegarder</span> la fiche.</p>
        </div>
    </div>

    <h3>6. Les images des dessins</h3>
    <div class="gd-two">
        <figure class="gd-shot"><img src="{{ $img('04_drawing_settings') }}" alt="Choisir l'image d'un dessin" loading="lazy"><figcaption>Choisissez une image, une page blanche, ou ajoutez la vôtre.</figcaption></figure>
        <div>
            <ol class="gd-steps">
                <li>Ouvrez les réglages d'un bloc <strong>Dessin</strong>.</li>
                <li>Cliquez sur l'image voulue. Le système contient les dessins ORL de la fiche papier de la clinique (oreilles, tympans, nez, bouche, cou, larynx) et des dessins des yeux.
                    <strong>Page blanche</strong> donne une page vide pour dessiner.</li>
                <li>Pour utiliser votre propre image, cliquez sur <span class="gd-b"><i class="fas fa-upload"></i> Ajouter une image</span> et choisissez un fichier PNG ou JPG (5 Mo maximum). Elle est ajoutée aux images et choisie.</li>
                <li>Cliquez sur <span class="gd-b">OK</span>, puis <span class="gd-b">Sauvegarder</span>.</li>
            </ol>
            <div class="gd-tip"><i class="fas fa-lightbulb"></i> <strong>Bonnes images :</strong> traits noirs sur fond blanc, sans couleurs ni ombres, comme un dessin sur papier. Un scan ou une photo d'un dessin convient s'il est net et droit.</div>
        </div>
    </div>

    <h3>7. Mettre des blocs côte à côte (largeur)</h3>
    <p>Les blocs remplissent la ligne de gauche à droite, puis passent à la ligne suivante. Utilisez la largeur pour les mettre côte à côte :</p>
    <table class="gd-t">
        <tr><th style="width:34%;">Vous voulez</th><th>Choisissez</th></tr>
        <tr><td>Quatre images sur une ligne (deux oreilles et deux tympans)</td><td>chaque Dessin sur <strong>Un quart</strong></td></tr>
        <tr><td>Les images droite et gauche côte à côte (yeux, fonds d'œil)</td><td>chacune sur <strong>Moitié</strong></td></tr>
        <tr><td>Une image avec sa première question à côté</td><td>Dessin <strong>Un tiers</strong> + question <strong>Deux tiers</strong></td></tr>
        <tr><td>Une longue liste de réponses</td><td><strong>Toute la largeur</strong></td></tr>
    </table>
    <p>Sur un téléphone, les blocs se placent d'eux-mêmes les uns sous les autres.</p>

    <h3>8. Créer une fiche pour une autre spécialité (exemple : gynécologie)</h3>
    <ol class="gd-steps">
        <li>Cliquez sur <span class="gd-b">Nouvelle fiche</span> (ou <span class="gd-b">Dupliquer</span> une fiche proche de ce que vous voulez).</li>
        <li>Tapez le <strong>nom</strong>, par exemple « Examen gynécologique ».</li>
        <li>Dans <strong>S'ouvre pour les spécialités</strong>, choisissez « Gynécologie-obstétrique ».</li>
        <li>Ajoutez un <strong>Titre de section</strong> (« Abdomen »), puis les blocs de cette partie, puis la section suivante, et ainsi de suite.</li>
        <li>Cliquez sur <span class="gd-b">Aperçu</span> pour vérifier, puis <span class="gd-b">Sauvegarder</span>.</li>
        <li>Ouvrez une consultation, choisissez la spécialité : la nouvelle fiche apparaît.</li>
    </ol>
    <div class="gd-warn"><i class="fas fa-shield-alt"></i> <strong>Modifier une fiche est sans risque.</strong> Chaque consultation garde sa propre copie de la fiche telle qu'elle était quand le médecin l'a remplie. Modifier ou supprimer une fiche plus tard ne change jamais les consultations déjà enregistrées.</div>
</div></div>

{{-- ======================================================== MÉDECIN ======================================================== --}}
<div class="box box-solid" id="gd_doctor"><div class="box-body">
    <h2><i class="fas fa-user-md"></i> Partie 2 — Utiliser une fiche pendant la consultation <small class="text-muted" style="font-size:13px;">(médecins)</small></h2>

    <h3>1. Ouvrir la fiche</h3>
    <ol class="gd-steps">
        <li>Commencez la consultation comme d'habitude : <span class="gd-b">Appeler</span> dans la File du médecin, ou <span class="gd-b">Nouvelle consultation</span> sur la visite.</li>
        <li>Mettez le <strong>Type de consultation</strong> sur <em>Spécialiste</em> et choisissez la <strong>Spécialité</strong> (par exemple <em>ORL</em>).</li>
        <li>La <strong>Fiche d'examen</strong> s'ouvre d'elle-même sous les signes vitaux.</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('05_specialty') }}" alt="Choisir la spécialité" loading="lazy"></figure>
    <p>Vous pouvez aussi choisir vous-même n'importe quelle fiche dans la liste en haut à droite du cadre <strong>Fiche d'examen</strong> (ou <em>Pas de fiche d'examen</em>).
        Si vous changez de fiche après avoir dessiné ou coché, le système demande d'abord, car ce qui a été fait sur la première fiche serait perdu.</p>
    <figure class="gd-shot"><img src="{{ $img('06_ent_form') }}" alt="La fiche ORL dans la consultation" loading="lazy"><figcaption>La fiche ORL : les dessins avec leurs outils, les signes pour la droite et la gauche.</figcaption></figure>

    <h3>2. Dessiner</h3>
    <div class="gd-two">
        <figure class="gd-shot" style="flex:0 0 260px;"><img src="{{ $img('07_tools') }}" alt="Un dessin et ses outils" loading="lazy"></figure>
        <div>
            <p>Dessinez directement sur l'image avec la <strong>souris</strong> (bouton gauche enfoncé), le <strong>doigt</strong> sur un écran tactile ou une tablette, ou un <strong>stylet</strong> (appuyer plus fort donne un trait plus épais). Les outils sous chaque image :</p>
            <table class="gd-t">
                <tr><td style="width:90px;"><span style="color:#dc2626;">●</span> <span style="color:#2563eb;">●</span> ●</td><td>Stylo : rouge, bleu ou noir</td></tr>
                <tr><td><i class="fas fa-eraser"></i></td><td>Gomme : efface une partie de ce qui a été dessiné (jamais l'image elle-même)</td></tr>
                <tr><td><i class="fas fa-font"></i></td><td>Écrire un mot : cliquez où va le mot, tapez-le, OK (ex. « nodule »)</td></tr>
                <tr><td><i class="fas fa-grip-lines"></i></td><td>Trait épais oui / non</td></tr>
                <tr><td><i class="fas fa-undo"></i></td><td>Annuler le dernier trait</td></tr>
                <tr><td><i class="fas fa-trash-alt"></i></td><td>Tout effacer sur cette image (demande d'abord)</td></tr>
                <tr><td><i class="fas fa-expand"></i></td><td>Plein écran : l'image devient grande, plus facile au doigt ou au stylet. Cliquez à nouveau, ou appuyez sur <kbd>Échap</kbd>, pour revenir.</td></tr>
            </table>
            <p>Un petit <i class="fas fa-pen" style="color:#4f46e5;"></i> apparaît à côté du titre d'une image qui porte un dessin.</p>
        </div>
    </div>

    <h3>3. Les signes en un clic</h3>
    <ul>
        <li>Cliquez sur une réponse pour la choisir : elle devient bleue avec un ✓. Cliquez à nouveau pour l'enlever.</li>
        <li><span class="label" style="background:#fee2e2;color:#991b1b;">Droite</span> et <span class="label" style="background:#dbeafe;color:#1e40af;">Gauche</span> se remplissent séparément.</li>
        <li>Pas dans la liste ? Tapez-la dans la case <strong>Autre…</strong> à la fin des réponses.</li>
        <li>Les notes et les nombres se tapent normalement.</li>
    </ul>

    <h3>4. Les photos (endoscope, caméra, téléphone)</h3>
    <figure class="gd-shot"><img src="{{ $img('08_photos') }}" alt="Ajouter des photos" loading="lazy"></figure>
    <ul>
        <li><span class="gd-b"><i class="fas fa-upload"></i> Ajouter photos / PDF</span> — choisissez des photos déjà sur l'ordinateur ou le téléphone (plusieurs à la fois). Photos et PDF seulement, 10 Mo maximum chacun.</li>
        <li><span class="gd-b"><i class="fas fa-camera"></i> Caméra / endoscope</span> — prend la photo directement depuis une caméra ou un endoscope branché sur l'ordinateur :
            le navigateur demande une fois l'autorisation d'utiliser la caméra (cliquez sur <em>Autoriser</em>) ; choisissez l'endoscope dans la liste <em>Caméra</em> ; cliquez sur <span class="gd-b">Prendre une photo</span> pour chaque image ; fermez la fenêtre à la fin.</li>
        <li>Chaque photo est enregistrée tout de suite, même avant que la consultation soit enregistrée. Les photos sont privées : seul le personnel de la clinique peut les ouvrir.</li>
    </ul>

    <h3>5. Enregistrer</h3>
    <ul>
        <li><span class="gd-b">Enregistrer et reprendre plus tard</span> — garde tout (dessins, réponses, photos) pendant que vous attendez le laboratoire ou l'imagerie. Quand vous rouvrez le patient (<span class="gd-b">Continuer</span> dans la file ou <span class="gd-b">Reprendre la consultation</span>), la fiche revient comme vous l'avez laissée, et vous pouvez continuer à dessiner.</li>
        <li><span class="gd-b">Finaliser la consultation</span> — la consultation est terminée.</li>
    </ul>
    <div class="gd-tip"><i class="fas fa-history"></i> <strong>Visite suivante :</strong> dans <em>Visites précédentes</em> en haut de la consultation, le médecin voit les dessins des consultations précédentes, pour comparer (par exemple : la perforation est-elle plus petite ?).</div>
</div></div>

{{-- ======================================================== IMPRESSION ======================================================== --}}
<div class="box box-solid" id="gd_print"><div class="box-body">
    <h2><i class="fas fa-print"></i> Partie 3 — Le compte rendu imprimé</h2>
    <ol class="gd-steps">
        <li>Ouvrez la consultation (après l'avoir enregistrée) et cliquez sur <span class="gd-b"><i class="fas fa-file-medical"></i> Imprimer la consultation</span>.</li>
        <li>Le compte rendu s'ouvre dans un nouvel onglet, présenté comme la fiche papier : le nom et le logo de la clinique, le patient (âge, sexe, profession, résidence, situation matrimoniale, téléphone), le motif, l'histoire de la maladie, les signes vitaux, la fiche d'examen avec les dessins et les signes, les photos, les diagnostics, les examens, le tableau des médicaments, la conduite à tenir et la date de contrôle.</li>
        <li>Cliquez sur <span class="gd-b">Imprimer</span> en haut (ou enregistrez-le en PDF depuis la fenêtre d'impression).</li>
    </ol>
    <figure class="gd-shot"><img src="{{ $img('09_print_button') }}" alt="Les boutons de la page de consultation" loading="lazy"></figure>
    <figure class="gd-shot"><img src="{{ $img('10_report') }}" alt="Le compte rendu imprimé" loading="lazy"><figcaption>Le haut d'un compte rendu ORL imprimé.</figcaption></figure>
    <p>Le nom et le logo de la clinique viennent de <span class="gd-b">Paramètres</span> → <span class="gd-b">Paramètres d'entreprise</span>. Changez-les là et tous les comptes rendus suivent.
        La profession et la situation matrimoniale du patient se remplissent dans le dossier du patient (<em>Patients</em> → <em>Modifier</em>).</p>
</div></div>

{{-- ======================================================== QUESTIONS ======================================================== --}}
<div class="box box-solid" id="gd_faq"><div class="box-body">
    <h2><i class="fas fa-question-circle"></i> Questions fréquentes</h2>
    <details><summary>La fiche ne s'est pas ouverte d'elle-même quand j'ai choisi la spécialité.</summary>
        <p>La spécialité choisie n'est pas dans <em>S'ouvre pour les spécialités</em> de la fiche. Choisissez la fiche vous-même dans la liste du cadre Fiche d'examen, ou (Configuration de la clinique) ajoutez cette spécialité à la fiche et sauvegardez. Vérifiez aussi que la fiche est <em>Utilisée</em>.</p></details>
    <details><summary>Le Type de consultation est « Général » : puis-je utiliser une fiche ?</summary>
        <p>Oui. Choisissez la fiche vous-même dans la liste en haut du cadre Fiche d'examen.</p></details>
    <details><summary>« Aucune caméra trouvée », ou la caméra ne s'ouvre pas.</summary>
        <p>Vérifiez que la caméra ou l'endoscope est branché (USB ou carte d'acquisition), puis cliquez à nouveau sur Caméra / endoscope. Si l'autorisation de la caméra a été refusée, cliquez sur l'icône de caméra dans la barre d'adresse et choisissez <em>Autoriser</em>. Puis choisissez le bon appareil dans la liste Caméra.</p></details>
    <details><summary>Peut-on ajouter des vidéos ?</summary>
        <p>Non. Seules les photos et les fichiers PDF peuvent être ajoutés.</p></details>
    <details><summary>Je me suis trompé sur un dessin.</summary>
        <p>Cliquez sur <i class="fas fa-undo"></i> pour annuler le dernier trait, utilisez la gomme <i class="fas fa-eraser"></i>, ou <i class="fas fa-trash-alt"></i> pour tout effacer sur cette image. Tant que la consultation n'est pas finalisée, on peut la modifier en la reprenant.</p></details>
    <details><summary>J'ai modifié une fiche. Les anciennes consultations changent-elles ?</summary>
        <p>Non. Chaque consultation garde la fiche telle qu'elle était quand elle a été remplie.</p></details>
    <details><summary>Qui peut configurer les fiches ?</summary>
        <p>Les utilisateurs dont le rôle a l'autorisation de gérer les paramètres de la clinique (en général l'administrateur). Les médecins ont seulement besoin de l'autorisation d'écrire des consultations.</p></details>
    <details><summary>Le médecin peut-il dessiner sur un téléphone ou une tablette ?</summary>
        <p>Oui, au doigt ou au stylet. Utilisez le bouton plein écran <i class="fas fa-expand"></i> pour une image plus grande.</p></details>
</div></div>
