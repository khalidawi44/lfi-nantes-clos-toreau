<?php
/**
 * VOLET RELOGEMENT / DÉMÉNAGEMENT — l'étape « déménagement » du parcours.
 *
 * Le relogement ne dépend PAS que du bailleur (NMH). Le mécanisme réel :
 *  1) DEMANDE UNIQUE de logement social (demandelogement44.fr / SNE) : une seule
 *     demande vaut pour TOUS les bailleurs. Locataire déjà logé qui veut partir
 *     = demande de MUTATION sur cette même plateforme.
 *  2) ACTION LOGEMENT (surtout salariés du privé) : canal + services mobilité.
 *  3) DALO (Droit Au Logement Opposable) = le LEVIER D'URGENCE : la commission
 *     de médiation peut déclarer le ménage « prioritaire et urgent » ; le préfet
 *     a alors 6 mois pour proposer un logement (contingent préfectoral).
 *
 * Sources officielles (vérifiées) : loire-atlantique.gouv.fr (DALO),
 * service-public.gouv.fr (F18005), demandelogement44.fr.
 * Règle : rien d'inventé — on ne met QUE des infos vérifiées.
 */
if (!defined('ABSPATH')) exit;

/** Contacts officiels VÉRIFIÉS (Loire-Atlantique). */
function lfi_nct_relogement_contacts() {
    return [
        'demande_unique' => [
            'nom'  => 'Demande de logement social (plateforme unique)',
            'url'  => 'https://www.demandelogement44.fr',
            'note' => 'Une seule demande pour TOUS les bailleurs. Déjà logé et veut partir = demande de mutation. À renouveler chaque année.',
        ],
        'action_logement' => [
            'nom'  => 'Action Logement',
            'url'  => 'https://www.actionlogement.fr',
            'note' => 'Surtout pour les salarié·es du privé : services mobilité, aide au relogement, garantie Visale.',
        ],
        'dalo' => [
            'nom'   => 'Commission de médiation DALO — Loire-Atlantique',
            'org'   => 'DDETS – Service Public de la Rue au Logement',
            'adr'   => '12 bd Vincent Gâche, CS 44278, 44203 Nantes Cedex 2',
            'tel'   => '02 72 20 63 00',
            'tel_h' => 'mardi & jeudi, 9h30–12h',
            'email' => 'ddets-commission-mediation@loire-atlantique.gouv.fr',
            'saisine' => 'https://www.loire-atlantique.gouv.fr/Actions-de-l-Etat/Politiques-sociales-et-du-logement/Acces-au-logement/Droit-au-Logement-Opposable-en-Loire-Atlantique-DALO',
            'fiche' => 'https://www.service-public.gouv.fr/particuliers/vosdroits/F18005',
        ],
    ];
}

/** Objectif du locataire (depuis l'enquête liée) : 'relogement', 'travaux'… */
function lfi_nct_tenant_objectif($uid) {
    global $wpdb;
    $rid = (int) get_user_meta((int) $uid, 'lfi_nct_response_id', true);
    if (!$rid) return '';
    $r = $wpdb->get_row($wpdb->prepare("SELECT data FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
    $d = $r ? json_decode((string) $r->data, true) : null;
    return is_array($d) ? (string) ($d['objectif'] ?? '') : '';
}

/* ============================================================== *
 *  VUE : étape Relogement d'un locataire                          *
 * ============================================================== */
function lfi_nct_app_view_relogement() {
    global $wpdb;
    $uid = (int) ($_GET['uid'] ?? 0);
    $is_admin = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
    $is_avocat = function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat()
        && function_exists('lfi_nct_avocat_of_tenant') && lfi_nct_avocat_of_tenant($uid) === get_current_user_id();
    if (!$is_admin && !$is_avocat) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $u = $uid ? get_userdata($uid) : null;
    if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) { wp_safe_redirect(lfi_nct_app_url('dossiers')); exit; }

    $c = lfi_nct_relogement_contacts();
    $d  = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($uid) : null;
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $problem = ($resp && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($resp) : null;
    $desordres = ($problem && !empty($problem['chips'])) ? implode(', ', array_map(function ($x) { return $x[1]; }, $problem['chips'])) : '';
    $name = trim(($d->tenant_prenom ?? '') . ' ' . ($d->tenant_nom ?? '')) ?: $u->display_name;
    $adresse = $d->tenant_adresse ?? ($resp->adresse ?? '');
    $enfants = '';
    if ($resp && $resp->data) { $dd = json_decode($resp->data, true); if (is_array($dd)) $enfants = (string) ($dd['enfants'] ?? ''); }

    lfi_nct_app_screen_open('🏠 Relogement / déménagement', $name);
    echo '<div style="margin-bottom:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">← Retour au dossier</a></div>';
    echo '<div class="lfi-app-help">Le relogement ne dépend pas que du bailleur. On active <strong>3 leviers en parallèle</strong> : la demande unique de logement social, Action Logement, et surtout le <strong>DALO</strong> qui oblige le préfet à reloger en urgence.</div>';

    /* 1) Demande unique / mutation */
    echo '<div class="lfi-app-card" style="border-left:4px solid #0066a3">';
    echo '<div class="head"><div class="who">1️⃣ Demande unique de logement social (+ mutation)</div></div>';
    echo '<div class="com" style="line-height:1.5">Une <strong>seule demande</strong> vaut pour <strong>tous les bailleurs</strong> du département. S\'il est déjà logé et veut partir, c\'est une <strong>demande de mutation</strong> sur la même plateforme. À <strong>renouveler chaque année</strong>, et à mettre à jour avec le motif (logement indécent…).</div>';
    echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" style="background:#0066a3" href="' . esc_url($c['demande_unique']['url']) . '" target="_blank" rel="noopener">🖥️ demandelogement44.fr</a>';
    echo '<a class="btn-ghost" href="' . esc_url($c['action_logement']['url']) . '" target="_blank" rel="noopener">🏢 Action Logement (salarié·es)</a></div>';
    echo '</div>';

    /* 2) DALO — l'urgence */
    $dalo = $c['dalo'];
    echo '<div class="lfi-app-card" style="border:2px solid #c8102e;background:#fff8f9;margin-top:12px">';
    echo '<div class="head"><div class="who">2️⃣ DALO — le levier d\'URGENCE ⚡</div></div>';
    echo '<div class="com" style="line-height:1.55">';
    echo '<p style="margin:6px 0">La <strong>commission de médiation DALO</strong> peut déclarer le ménage <strong>prioritaire et à reloger d\'urgence</strong>. Après une décision favorable, <strong>le préfet a 6 mois pour proposer un logement</strong> (via son contingent). À défaut, recours au <strong>Tribunal Administratif</strong> (dans les 2 mois).</p>';
    echo '<p style="margin:6px 0"><strong>Sans délai d\'attente</strong> (urgence reconnue) notamment si : logement <strong>indécent / insalubre / dangereux</strong>, <strong>suroccupation</strong>, avec <strong>enfant mineur</strong> ou <strong>handicap</strong> ; menace d\'expulsion ; hébergement en structure &gt; 6 mois. Sinon, « délai anormalement long » = <strong>30 mois</strong> sur Nantes Métropole.</p>';
    echo '</div>';
    echo '<div style="background:#fff;border-radius:8px;padding:10px 12px;font-size:.9em">';
    echo '<div style="font-weight:800;color:#c8102e">📮 Où saisir / qui contacter</div>';
    echo '<div>' . esc_html($dalo['org']) . '<br>' . esc_html($dalo['adr']) . '</div>';
    echo '<div style="margin-top:4px"><a href="tel:' . esc_attr(preg_replace('/\s+/', '', $dalo['tel'])) . '">📞 ' . esc_html($dalo['tel']) . '</a> <span style="color:#888">(' . esc_html($dalo['tel_h']) . ')</span></div>';
    echo '<div><a href="mailto:' . esc_attr($dalo['email']) . '">✉️ ' . esc_html($dalo['email']) . '</a></div>';
    echo '</div>';
    echo '<div class="row-actions" style="margin-top:8px;flex-wrap:wrap"><a class="btn-primary" style="background:#c8102e" href="' . esc_url($dalo['saisine']) . '" target="_blank" rel="noopener">📝 Saisir la commission DALO (en ligne / Cerfa)</a>';
    echo '<a class="btn-ghost" href="' . esc_url($dalo['fiche']) . '" target="_blank" rel="noopener">📖 Fiche service-public (DALO)</a></div>';
    echo '</div>';

    /* 3) Lettre de saisine DALO pré-remplie (à relire — rien d'inventé). */
    $moi = wp_get_current_user(); $moi_nom = $moi->display_name ?: $moi->user_login;
    $today = function_exists('wp_date') ? wp_date('j F Y') : date('d/m/Y');
    $L  = "Objet : Demande de reconnaissance au titre du DALO — relogement d'urgence\n\n";
    $L .= "Madame, Monsieur,\n\n";
    $L .= "Au nom et pour le compte de " . $name . ", locataire du logement situé " . ($adresse ?: '[adresse]') . " (bailleur : Nantes Métropole Habitat), et qui nous a mandatés, je sollicite la reconnaissance du caractère prioritaire et urgent de sa demande de relogement.\n\n";
    $L .= "Situation :\n";
    $L .= "- Le logement présente des désordres" . ($desordres ? " : " . $desordres . "." : " rendant les conditions de vie indignes.") . "\n";
    if ($enfants !== '' && (int) $enfants > 0) $L .= "- Le foyer compte " . (int) $enfants . " enfant(s) mineur(s).\n";
    $L .= "- Malgré les démarches auprès du bailleur, la situation n'a pas été réglée (caractère indécent / insalubre au sens du décret n° 2002-120).\n\n";
    $L .= "Une demande de logement social est déposée / à jour sur la plateforme unique (demandelogement44.fr).\n\n";
    $L .= "Je joins les pièces justificatives (photos datées, courriers, certificats, etc.) et reste à votre disposition.\n\n";
    $L .= "Fait le " . $today . ".\n" . $moi_nom . "\n";
    $L .= "Union des Quartiers Libres — au nom et pour le compte de " . $name . ".";
    echo '<h3 style="margin:16px 0 6px;color:#186a3b">✍️ Lettre de saisine DALO (pré-remplie — à relire)</h3>';
    echo '<div class="lfi-app-help"><small>Relis et complète les crochets [ ]. Aucun chiffre inventé : la situation reprend le dossier. À déposer via la démarche en ligne ou par courrier à l\'adresse ci-dessus.</small></div>';
    echo '<textarea readonly onclick="this.select()" style="width:100%;height:300px;margin-top:6px;font-size:.85em;padding:10px;border:1px solid #ccc;border-radius:8px;line-height:1.5">' . esc_textarea($L) . '</textarea>';

    lfi_nct_app_screen_close();
}

/** Étapes de parcours spécifiques « relogement » (greffées si objectif = relogement). */
function lfi_nct_relogement_steps() {
    return [
        ['who' => 'admin', 'text' => "🏠 Déposer / mettre à jour la demande unique (demandelogement44.fr) + mutation"],
        ['who' => 'admin', 'text' => "🏠 Saisir la commission DALO (relogement d'urgence) au nom du locataire"],
        ['who' => 'admin', 'text' => "🏠 Relancer : le préfet a 6 mois pour proposer un logement (contingent préfectoral)"],
    ];
}

/** Greffe (idempotent) les étapes relogement si l'objectif du locataire est le relogement. */
function lfi_nct_relogement_ensure_steps($uid) {
    if (lfi_nct_tenant_objectif($uid) !== 'relogement') return;
    $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];
    $existing = array_map(function ($s) { return $s['text'] ?? ''; }, $steps);
    $changed = false;
    foreach (lfi_nct_relogement_steps() as $tpl) {
        if (!in_array($tpl['text'], $existing, true)) {
            $steps[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
            $existing[] = $tpl['text'];
            $changed = true;
        }
    }
    if ($changed) update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($steps));
}
