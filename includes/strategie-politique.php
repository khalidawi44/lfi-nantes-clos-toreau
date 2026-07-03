<?php
/**
 * STRATÉGIE POLITIQUE — municipale & nationale. SUPER-ADMIN UNIQUEMENT.
 *
 * Tableaux de bord stratégiques PRIVÉS (Fabrice) : jamais visibles par les
 * membres ni les autres GA. Contenu sensible → gated super-admin.
 *
 * Ligne rouge rappelée partout : la PRIORITÉ absolue est de sortir les gens de
 * leurs conditions le plus vite possible ; l'urgent reste l'urgent ; aucune
 * manœuvre politique ne passe avant un relogement urgent. Le reste (municipal,
 * national, députation) est EN AVAL de ça. Et : faits secs, jamais d'insulte.
 */
if (!defined('ABSPATH')) exit;

/** Accès : super-admin sur son espace (pas un admin de GA). */
function lfi_nct_stratpol_can() {
    if (!current_user_can('manage_options')) return false;
    if (function_exists('lfi_nct_scope_ga_slug')) {
        $s = lfi_nct_scope_ga_slug();
        return ($s === '' || $s === 'clos-toreau');
    }
    return true;
}

function lfi_nct_stratpol_priority_banner() {
    echo '<div class="lfi-app-card" style="border:2px solid #186a3b;background:#eef7ee">'
       . '<div class="com"><strong>🎯 Cap non négociable :</strong> sortir les gens de leurs conditions <strong>le plus vite possible</strong>. L\'urgent reste l\'urgent. <strong>Aucune</strong> manœuvre politique ne retarde un relogement urgent. Le municipal, le national, la députation : <strong>en aval</strong> de ça, jamais avant.</div></div>';
}

/* ============================================================== *
 *  VUE : Stratégie MUNICIPALE (William + plan cadré)             *
 * ============================================================== */
function lfi_nct_app_view_strategie_municipale() {
    if (!lfi_nct_stratpol_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('🏛️ Stratégie municipale', 'Privé — William, le conseil, l\'audit NMH. Faits secs, jamais d\'insulte.');
    lfi_nct_stratpol_priority_banner();

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Notre position</h3>';
    echo '<div class="lfi-app-card"><div class="com">Tu n\'es <strong>pas</strong> élu — mais tes camarades le sont, et tu leur parles tous les jours (<strong>William Aucant</strong> t\'a demandé une visio). Eux peuvent poser des <strong>questions au conseil municipal</strong> ; toi, tu as les <strong>résultats terrain</strong>. On combine : <strong>tu armes, ils portent.</strong></div></div>';

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Le plan, dans l\'ordre (cadré)</h3>';
    echo '<ul class="lfi-app-list">';
    foreach ([
        ['1. Porte privée d\'abord', 'Dossier <strong>confidentiel et anonyme</strong> à <strong>Christophe Jouin</strong> (CA de NMH) sur les cas <strong>urgents</strong>. S\'ils bougent → les gens sortent vite (cap tenu). S\'ils ne bougent pas → l\'escalade publique devient imparable.'],
        ['2. Armer William', 'Brief <strong>sec, agrégé, anonyme</strong> → une <strong>question ouverte factuelle</strong> au conseil (à J. Rolland), <strong>sans citer</strong> les chiffres ni l\'enquête. Sa réponse parlera pour elle.'],
        ['3. Si silence / réponse creuse', 'Alors <strong>conférence de presse</strong> : des <strong>faits secs</strong> (agrégé, anonyme, quelques témoignages consentants) + une <strong>demande d\'engagements</strong> (plan, calendrier). <strong>Jamais</strong> d\'insulte : l\'écart entre son discours et tes preuves l\'expose tout seul. Plus dévastateur, et ça ne te salit pas.'],
        ['4. L\'audit NMH', 'L\'argumentaire du « pourquoi nos logements sont comme ça » : la <strong>Chambre régionale des comptes</strong> a publié un rapport sur NMH (déc. 2025). On l\'exploite (où va l\'argent : dette, fonctionnement, entretien parent pauvre) — <strong>en ratios moyens, sourcés</strong>, jamais un loyer individuel.'],
    ] as $it) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">' . esc_html($it[0]) . '</div></div><div class="com">' . wp_kses_post($it[1]) . '</div></li>';
    }
    echo '</ul>';

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Garde-fous</h3>';
    echo '<div class="lfi-app-card"><div class="com"><strong>RGPD :</strong> jamais de nom ni de situation individuelle sans accord écrit. — <strong>Sécheresse chirurgicale :</strong> des faits, pas d\'adjectifs (« menteuse/incompétente » = poison qui se retourne). — <strong>Ne pas doubler William :</strong> tu l\'armes, tu ne freelances pas devant lui. — <strong>Ne pas braquer NMH</strong> au point que ça retombe sur les locataires que tu défends.</div></div>';

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Contacts</h3>';
    echo '<div class="lfi-app-card"><div class="com">🧑‍💼 <strong>William Aucant</strong> — élu, allié (visio demandée). · 📈 <strong>Christophe Jouin</strong> — christophe.jouin@mairie-nantes.fr (CA de NMH, escalade amiable).</div></div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Stratégie NATIONALE (remonter + multi-GA + députation)  *
 * ============================================================== */
function lfi_nct_app_view_strategie_nationale() {
    if (!lfi_nct_stratpol_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('🇫🇷 Stratégie nationale', 'Privé — faire remonter, déployer les enquêtes, la députation.');
    lfi_nct_stratpol_priority_banner();

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Faire remonter</h3>';
    echo '<div class="lfi-app-card"><div class="com">La méthode (enquête terrain + audit NMH) est <strong>réplicable partout</strong>. On arme le national avec de la <strong>donnée agrégée</strong> et des <strong>éléments de langage</strong> (le <em>Volet national</em> existe déjà : tableau de bord, arguments, dossier PDF). Objectif : un sujet « logement social » <strong>documenté par le terrain</strong>, pas par des slogans.</div></div>';

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Le vrai problème : le déploiement multi-GA</h3>';
    echo '<div class="lfi-app-card" style="border-left:4px solid #d39e00"><div class="com">L\'app est déployée dans <strong>beaucoup de GA</strong>, mais ils <strong>ne s\'en emparent pas</strong> encore. Or c\'est <strong>toi</strong> qui dois centraliser les résultats. C\'est un problème d\'<strong>adoption</strong>, à traiter comme tel.</div></div>';
    echo '<div class="lfi-app-help">Pistes (à affiner ensemble) :</div>';
    echo '<ul class="lfi-app-list">';
    foreach ([
        '👤 Un référent « enquête logement » par GA — une seule personne responsable, pas « tout le monde ».',
        '🎯 Un objectif simple et chiffré — ex. « 10 portes cette semaine » — mesurable dans l\'app.',
        '🏆 Montrer une preuve qui donne envie — une réussite obtenue ailleurs = « ça marche, faites pareil ».',
        '🚀 Onboarding ultra-simple — un tuto 2 min « faire passer une enquête » (déjà en place), à pousser.',
        '📊 Un classement/□ tableau des GA actifs — l\'émulation entre groupes (le volet réseau existe déjà).',
        '🤝 Un accompagnement direct — tu (ou un binôme) appelles les référents, tu débloques.',
    ] as $li) {
        echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="com">' . wp_kses_post($li) . '</div></li>';
    }
    echo '</ul>';

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Ta trajectoire (députation)</h3>';
    echo '<div class="lfi-app-card"><div class="com"><strong>Ancrage local d\'abord</strong> : présence continue, média, association, résultats concrets. La preuve, <strong>pas la promesse</strong>. Ton meilleur atout électoral, c\'est « celui qui obtient des résultats » — pas les coups d\'éclat. Garde tes <strong>socles indépendants</strong> (asso + média) : négocier avec le national, ne pas être absorbé.</div></div>';

    echo '<div class="lfi-app-help" style="margin-top:10px;background:#f7f7f7"><small>🔒 Écran privé (super-admin). Rien de ceci n\'est visible par les membres ni les autres GA.</small></div>';
    lfi_nct_app_screen_close();
}
