<?php
/**
 * GUIDE D'UTILISATION — expliqué très simplement, étape par étape.
 *
 * Pensé pour quelqu'un qui découvre l'application : chaque chose est décrite
 * comme un chemin (« Tu appuies sur… puis sur… »). Sections repliables.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_app_view_guide() {
    if (!lfi_nct_app_guard_brigade()) return;
    lfi_nct_app_screen_open('📖 Guide d\'utilisation', 'Tout ce que l\'outil sait faire, pas à pas');

    echo '<div class="lfi-app-help" style="background:#e8f5ea;border-left:4px solid #186a3b">Bienvenue 👋 Ce guide explique <strong>tout simplement</strong> ce que tu peux faire. Appuie sur un titre pour l\'ouvrir ou le fermer. Pas besoin de tout lire : va à ce qui t\'intéresse.</div>';

    $h2 = function ($t) { echo '<h2 style="color:#c8102e;border-left:5px solid #c8102e;padding-left:10px;margin:18px 0 6px">' . $t . '</h2>'; };
    $steps = function ($arr) {
        echo '<ol style="padding-left:20px;margin:6px 0">';
        foreach ($arr as $s) echo '<li style="margin:5px 0">' . $s . '</li>';
        echo '</ol>';
    };
    $tip = function ($t) { echo '<div class="lfi-app-help"><small>💡 ' . $t . '</small></div>'; };

    /* 0. Bases */
    $h2('🏁 Pour bien commencer');
    echo '<p>L\'application s\'ouvre sur la page d\'<strong>accueil</strong>. Tu y vois :</p>';
    $steps([
        'En haut, le bandeau rouge <strong>« À faire »</strong> : les choses importantes à ne pas oublier (une réponse à analyser, un courrier à relancer…). Appuie sur <strong>« Ouvrir → »</strong> et l\'app t\'amène directement au bon endroit.',
        'En dessous, des <strong>tuiles</strong> rangées par espace (Groupe d\'action, Enquête, Locataires, Intervention, Association…). Une tuile = un outil. Tu appuies dessus pour l\'ouvrir.',
        'La flèche <strong>←</strong> en haut à gauche te ramène à la page précédente ; la maison <strong>⌂</strong> te ramène à l\'accueil.',
    ]);
    $tip('Astuce : ajoute la page à l\'écran d\'accueil de ton téléphone (menu « Partager » → « Sur l\'écran d\'accueil ») pour l\'avoir comme une vraie appli.');

    /* 1. Nouveau dossier */
    $h2('🧭 Créer le dossier d\'un·e locataire (le plus important)');
    $steps([
        'Accueil → tuile <strong>« 🧭 Nouveau dossier (guidé) »</strong>.',
        '<strong>Étape 1</strong> : tu tapes son prénom, nom, adresse, étage… puis <strong>« Enregistrer et continuer »</strong>.',
        '<strong>Étape 2</strong> : tu coches son objectif (être relogé·e, des travaux, une indemnisation…), les désordres (humidité, nuisibles…), s\'il y a une urgence santé, et ses revenus (pour l\'aide juridictionnelle). Puis <strong>« Voir le plan d\'action »</strong>.',
        '<strong>Étape 3</strong> : l\'app te propose <strong>toute seule</strong> les démarches à faire (courrier au bailleur, service d\'hygiène, préfecture…). Chaque étape a un bouton <strong>« Ouvrir → »</strong> déjà pré-rempli.',
    ]);
    $tip('Tu peux aussi créer un dossier à la main depuis « 🗂 Dossiers & suivi ».');

    /* 2. Visite + photos */
    $h2('📷 Faire le constat avec des photos');
    $steps([
        'Va dans une <strong>intervention</strong> (ou crée-la depuis « 🔧 Interventions »).',
        'Section <strong>« 📷 Photos du constat »</strong> : appuie sur le champ photo, choisis <strong>plusieurs photos d\'un coup</strong> ou prends-les. Elles s\'ajoutent toutes seules.',
        'Tu peux en rajouter à tout moment, et supprimer une photo avec la petite croix rouge.',
    ]);
    $tip('Les photos servent de preuve datée des défaillances (moisissures, fuites…).');

    /* 3. Courriers + email */
    $h2('✉️ Écrire un courrier (bailleur, hygiène…) et l\'envoyer');
    $steps([
        'Dans le dossier du/de la locataire, choisis la lettre (mise en demeure, relogement, service d\'hygiène…).',
        'Appuie sur <strong>« 📨 Ouvrir dans mon Gmail »</strong> : ton application Gmail s\'ouvre avec le message <strong>déjà écrit</strong>. Tu n\'as plus qu\'à appuyer sur <strong>Envoyer</strong>.',
        'L\'email est <strong>automatiquement noté</strong> dans le dossier (« suivi des correspondances »). Si Gmail ne s\'ouvre pas, utilise les liens de secours affichés.',
    ]);
    $tip('La copie de tes envois part toujours vers ton archive ; le bailleur ne voit pas tes correspondances internes.');

    /* 4. Réponse reçue */
    $h2('📥 Quand tu reçois une réponse');
    $steps([
        'Dans le dossier, ouvre <strong>« Enregistrer un email reçu »</strong>, colle le texte reçu, enregistre.',
        'Une alerte <strong>« Analyser la réponse »</strong> apparaît sur l\'accueil. Appuie dessus pour la traiter.',
        'Tu peux aussi me dire « j\'ai reçu un email de NMH pour Mme X » : je l\'enregistre et je pose l\'alerte avec le lien direct.',
    ]);

    /* 5. Préfecture */
    $h2('🏛️ Partager les chiffres à la préfecture (anonyme)');
    $steps([
        'Accueil → tuile <strong>« 🏛️ Préfecture »</strong>.',
        'Tu vois le tableau <strong>anonyme par bâtiment</strong> (jamais de nom ni de n° de porte).',
        'Bouton <strong>« 📄 Rapport anonyme »</strong> → « Imprimer » → « Enregistrer en PDF », puis tu le joins à ton email.',
        'Bouton <strong>« ✉️ Écrire à la préfecture »</strong> : email pré-rempli à la déléguée.',
    ]);

    /* 6. Réussites */
    $h2('🏆 Raconter une victoire (anonyme)');
    $steps([
        'Quand un dossier <strong>aboutit</strong>, un bouton « Créer la fiche réussite » apparaît dans le dossier.',
        'Tu décris la situation, les leviers utilisés, le résultat — <strong>sans aucun nom</strong>.',
        'Coche « Publier » pour l\'afficher sur le site (avec « Contactez-nous »), via le code <code>[lfi_nct_reussites]</code>.',
    ]);

    /* 7. Adhésion */
    $h2('🎫 Faire adhérer un·e locataire');
    $steps([
        'Dans le dossier → <strong>« Générer le bulletin d\'adhésion »</strong> → bouton « 🖨 Imprimer ».',
        'L\'adhésion est <strong>gratuite</strong>. Fais-la signer AVANT d\'accompagner : c\'est ce qui rend ton aide légale.',
    ]);

    /* 8. Modules */
    $h2('🧩 Choisir les outils de ton groupe (admins)');
    $steps([
        'Accueil → tuile <strong>« 🧩 Modules »</strong> (en bas, espace Système).',
        '<strong>Coche</strong> les outils que ton groupe utilise, <strong>décoche</strong> ceux dont tu ne veux pas (ex. le volet travaux).',
        'Enregistre : les outils décochés disparaissent de l\'accueil. Tu peux les remettre quand tu veux.',
    ]);

    /* 9. Comptes / admins */
    $h2('🪪 Ajouter d\'autres administrateurs');
    $steps([
        'Accueil → <strong>« Comptes GA »</strong> : tu crées les accès pour les autres membres.',
        'Plusieurs personnes peuvent être administratrices et gérer les dossiers en même temps.',
    ]);

    /* 10. Aide */
    $h2('🆘 Si quelque chose ne va pas');
    echo '<p>Rien n\'est jamais perdu. Reviens à l\'accueil avec la maison <strong>⌂</strong>, ou recharge la page. Si un écran reste bloqué, ferme et rouvre l\'application. Et tu peux toujours me décrire ce que tu vois, je corrige.</p>';

    /* Sections repliables */
    if (function_exists('lfi_nct_render_section_accordion_js')) lfi_nct_render_section_accordion_js(false);

    lfi_nct_app_screen_close();
}
