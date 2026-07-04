<?php
/**
 * GÉNÉRATEUR DE RÉPONSE (self-service membres du GA).
 *
 * Objectif : déléguer. Le membre qui gère un locataire reçoit le mail de NMH,
 * va voir le locataire, recueille SA décision, puis génère ici un email complet
 * à envoyer à NMH — SANS passer par l'assistant central ni par Fabrice.
 *
 * Le corps est assemblé en PHP à partir de :
 *  - le mail reçu (archivé dans le dossier),
 *  - la DÉCISION du locataire (relogement, accepte/refuse/exige travaux,
 *    constat, indemnisation, autre) + les précisions du locataire,
 *  - le désamorçage pénal (module penal.php) si le mail reçu contient une
 *    intimidation ou un contournement.
 * La lecture psy reste INTERNE (aide à la décision), elle n'apparaît pas dans
 * l'email envoyé. La réponse générée atterrit dans « À envoyer », signée au nom
 * du membre (son interlocuteur unique), prête à partir depuis SA boîte.
 */
if (!defined('ABSPATH')) exit;

/** Peut gérer ce dossier : admin (voit tout) ou référent attribué. */
function lfi_nct_dossier_can_manage($row) {
    if (!$row) return false;
    if (function_exists('lfi_nct_dossier_sees_all') && lfi_nct_dossier_sees_all()) return true;
    return (int) ($row->referent_user_id ?? 0) === (int) get_current_user_id();
}

/** Décisions possibles du locataire → libellé + type de réponse. */
function lfi_nct_reply_intentions() {
    return [
        'relogement'     => '🏠 Il/elle veut être relogé·e (déménager)',
        'accepte_travaux'=> '✅ Il/elle accepte les travaux proposés',
        'exige_travaux'  => '🔧 Il/elle exige les travaux (mise en demeure)',
        'refuse_travaux' => '🚫 Il/elle refuse la proposition de NMH',
        'constat'        => '📋 Demander une visite / un constat contradictoire',
        'indemnisation'  => '💶 Demander réparation du préjudice',
        'autre'          => '✍️ Autre (je précise ci-dessous)',
    ];
}

/**
 * Assemble le corps de l'email de réponse.
 * @param object $row       Dossier locataire.
 * @param array  $recu      L'email reçu (['de','objet','corps','date']).
 * @param string $intention Clé de lfi_nct_reply_intentions().
 * @param string $precisions Ce que le locataire a dit (texte libre).
 * @param string $signataire Nom affiché du membre signataire.
 * @return string
 */
function lfi_nct_generate_reply_body($row, $recu, $intention, $precisions, $signataire) {
    $nom = trim($row->tenant_prenom . ' ' . $row->tenant_nom);
    $when = !empty($recu['date']) ? (' du ' . wp_date('j M Y', strtotime($recu['date']))) : '';
    $p = trim((string) $precisions);

    $intro = "Madame, Monsieur,\n\n"
        . "En accompagnement de " . $nom . ", à sa demande et en qualité d'interlocuteur unique, je reviens vers vous à la suite de votre message" . $when . ".\n";

    switch ($intention) {
        case 'relogement':
            /* IMPORTANT : on ne cite QUE des faits documentés. Aucune mention
               d'une situation médicale — sauf si un certificat figure réellement
               au dossier (ajouté par la clause conditionnelle plus bas). */
            $coeur = "Après échange avec la personne que j'accompagne, sa décision est claire : elle demande son RELOGEMENT dans un logement décent et adapté à sa situation.\n"
                . "Je vous demande de m'indiquer, sous 8 jours, les possibilités de mutation correspondant à sa composition familiale, ainsi que la procédure à suivre. "
                . "La présence de désordres affectant la décence (art. 1719 du Code civil, décret n° 2002-120) fonde une demande de relogement prioritaire.";
            break;
        case 'accepte_travaux':
            $coeur = "Après échange avec la personne que j'accompagne, elle ACCEPTE la réalisation des travaux.\n"
                . "Je vous demande de me communiquer, sous 8 jours, un calendrier d'intervention daté et le nom de l'entreprise. "
                . "Tout accès au logement se fera par mon intermédiaire et en ma présence : merci de convenir des dates avec moi.";
            break;
        case 'exige_travaux':
            $coeur = "Après échange avec la personne que j'accompagne, elle EXIGE la réalisation des travaux nécessaires pour mettre fin aux désordres constatés.\n"
                . "En application de votre obligation de délivrance d'un logement décent (art. 1719 du Code civil, art. 6 de la loi du 6 juillet 1989, décret n° 2002-120), je vous demande de me communiquer, sous 8 jours, un calendrier d'intervention daté. "
                . "À défaut, je saisirai le Service d'Hygiène (SCHS) aux fins de constat.";
            break;
        case 'refuse_travaux':
            $coeur = "Après échange avec la personne que j'accompagne, elle NE retient PAS votre proposition en l'état"
                . ($p !== '' ? ", pour la raison suivante : " . $p . "." : ".") . "\n"
                . "Je reste ouvert à une résolution amiable et vous propose d'en rediscuter sur la base d'un constat contradictoire, en ma présence.";
            break;
        case 'constat':
            $coeur = "Je vous demande d'organiser une VISITE CONTRADICTOIRE des désordres, à laquelle je serai présent avec la personne que j'accompagne, en qualité d'interlocuteur unique.\n"
                . "Merci de me proposer une date sous 8 jours.";
            break;
        case 'indemnisation':
            $coeur = "Les désordres subis ont causé un trouble de jouissance ouvrant droit à réparation.\n"
                . "Après échange avec la personne que j'accompagne, je vous demande, outre la remise en état, une indemnisation (ou une réduction de loyer) au titre du préjudice subi. Un chiffrage détaillé vous sera communiqué.";
            break;
        default: /* autre */
            $coeur = $p !== '' ? $p : "Après échange avec la personne que j'accompagne, je reviens vers vous sur ce dossier.";
            break;
    }

    /* Précisions du locataire (si pas déjà intégrées). */
    if ($p !== '' && !in_array($intention, ['refuse_travaux', 'autre'], true)) {
        $coeur .= "\n\nPrécisions communiquées par la personne accompagnée : " . $p;
    }

    /* Désamorçage pénal éventuel, à partir du mail reçu. */
    $penal = function_exists('lfi_nct_penal_paragraphe') ? lfi_nct_penal_paragraphe((string) ($recu['corps'] ?? '')) : '';

    /* Au BAILLEUR : signature de l'ASSOCIATION mandatée (jamais LFI — cadre légal). */
    $signature = function_exists('lfi_nct_email_signature')
        ? lfi_nct_email_signature('nmh', $signataire, $nom)
        : "\n\nCordialement,\n" . $signataire . "\nUnion des Quartiers Libres — au nom et pour le compte de " . $nom . ".";

    $body = $intro . "\n" . $coeur . "\n";
    if ($penal !== '') $body .= "\n" . $penal . "\n";
    $body .= "\nJe privilégie une résolution amiable et rapide. Je reste à votre disposition." . $signature;
    return $body;
}

/* ============================================================== *
 *  MANDAT : on n'écrit JAMAIS à NMH au nom d'un locataire sans     *
 *  mandat. Le mandat = adhésion signée au dossier (signature dans  *
 *  l'enquête liée) OU coche manuelle « adhésion signée » (papier). *
 *  Sans mandat : le bouton de génération n'apparaît même pas.      *
 * ============================================================== */
function lfi_nct_dossier_has_mandate($row) {
    if (!$row) return false;
    $man = get_option('lfi_nct_dossier_mandat', []);
    if (is_array($man) && !empty($man[(int) $row->id])) return true; /* signé sur papier */
    global $wpdb;
    $tuid = (int) ($row->tenant_user_id ?? 0);
    if (!$tuid) return false;
    $rid = (int) get_user_meta($tuid, 'lfi_nct_response_id', true);
    if (!$rid) return false;
    $resp = $wpdb->get_row($wpdb->prepare("SELECT data FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
    if (!$resp) return false;
    $data = json_decode((string) $resp->data, true);
    $adh  = is_array($data) ? ($data['adhesion'] ?? null) : null;
    return is_array($adh) && (!empty($adh['signed']) || !empty($adh['signature_id']));
}
function lfi_nct_dossier_mandat_manual($id) {
    $man = get_option('lfi_nct_dossier_mandat', []);
    return is_array($man) && !empty($man[(int) $id]);
}
function lfi_nct_dossier_mandat_set($id, $val) {
    $man = get_option('lfi_nct_dossier_mandat', []); if (!is_array($man)) $man = [];
    if ($val) $man[(int) $id] = 1; else unset($man[(int) $id]);
    update_option('lfi_nct_dossier_mandat', $man, false);
}
/* Coche/décoche « adhésion signée (mandat) ». */
add_action('admin_post_lfi_nct_mandat', 'lfi_nct_mandat_handler');
function lfi_nct_mandat_handler() {
    if (!is_user_logged_in()) wp_die('non');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id && check_admin_referer('lfi_nct_mandat_' . $id)) {
        global $wpdb; $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
        if ($row && (!function_exists('lfi_nct_dossier_can_manage') || lfi_nct_dossier_can_manage($row))) {
            lfi_nct_dossier_mandat_set($id, !lfi_nct_dossier_mandat_manual($id));
        }
    }
    wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id]) . '#sec-reponses'); exit;
}

/* RETIRER le mandat : efface la coche manuelle ET la signature d'adhésion
   éventuelle dans l'enquête (cas d'un clic par erreur). */
add_action('admin_post_lfi_nct_mandat_remove', 'lfi_nct_mandat_remove_handler');
function lfi_nct_mandat_remove_handler() {
    if (!is_user_logged_in()) wp_die('non');
    $id = (int) ($_GET['id'] ?? 0);
    if ($id && check_admin_referer('lfi_nct_mandat_rm_' . $id)) {
        global $wpdb; $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id));
        if ($row && (!function_exists('lfi_nct_dossier_can_manage') || lfi_nct_dossier_can_manage($row))) {
            /* 1) coche manuelle. */
            lfi_nct_dossier_mandat_set($id, false);
            /* 2) signature d'adhésion dans l'enquête liée. */
            $tuid = (int) ($row->tenant_user_id ?? 0);
            $rid = $tuid ? (int) get_user_meta($tuid, 'lfi_nct_response_id', true) : 0;
            if ($rid) {
                $resp = $wpdb->get_row($wpdb->prepare("SELECT data FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
                $data = $resp ? json_decode((string) $resp->data, true) : null;
                if (is_array($data) && isset($data['adhesion'])) {
                    if (!empty($data['adhesion']['signature_id'])) { $sid = (int) $data['adhesion']['signature_id']; if ($sid) wp_delete_attachment($sid, true); }
                    $data['adhesion']['signed'] = false;
                    unset($data['adhesion']['signature_id'], $data['adhesion']['signature']);
                    $wpdb->update("{$wpdb->prefix}lfi_nct_responses", ['data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE)], ['id' => $rid]);
                }
            }
        }
    }
    wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id, 'mandat_removed' => 1]) . '#sec-reponses'); exit;
}

/* ============================================================== *
 *  ÉCRAN : Générer la réponse (dans un dossier)                  *
 * ============================================================== */
function lfi_nct_app_view_generer_reponse() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id)) : null;
    if (!$row || !lfi_nct_dossier_can_manage($row)) { wp_safe_redirect(lfi_nct_app_url('dossiers-juridiques')); exit; }
    /* VERROU MANDAT : pas d'accès à la génération sans adhésion signée. */
    if (!lfi_nct_dossier_has_mandate($row)) { wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id, 'nomandat' => 1]) . '#sec-reponses'); exit; }

    $notes = json_decode($row->notes ?? '', true);
    $recus = (is_array($notes) && !empty($notes['email_recu'])) ? array_values($notes['email_recu']) : [];
    $ri = isset($_GET['r']) ? (int) $_GET['r'] : (count($recus) - 1);
    if ($ri < 0 || $ri >= count($recus)) $ri = count($recus) - 1;
    $recu = ($ri >= 0 && isset($recus[$ri])) ? $recus[$ri] : ['de' => '', 'objet' => '', 'corps' => '', 'date' => ''];

    /* Génération : on assemble et on dépose dans « À envoyer ». */
    if (!empty($_POST['lfi_gen_reply']) && check_admin_referer('lfi_gen_reply')) {
        /* VERROU MANDAT : pas d'email à NMH sans adhésion signée au dossier. */
        if (!lfi_nct_dossier_has_mandate($row)) {
            wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id, 'nomandat' => 1]) . '#sec-reponses'); exit;
        }
        $intention = sanitize_key($_POST['intention'] ?? 'autre');
        if (!isset(lfi_nct_reply_intentions()[$intention])) $intention = 'autre';
        $precisions = sanitize_textarea_field(wp_unslash($_POST['precisions'] ?? ''));
        $to = sanitize_email(wp_unslash($_POST['to'] ?? ''));
        if ($to === '' && !empty($recu['de']) && preg_match('/[\w.\-+]+@[\w.\-]+/', (string) $recu['de'], $m)) $to = $m[0];
        $subject = (string) ($recu['objet'] ?? '');
        if ($subject === '') $subject = 'Suivi du dossier logement';
        if (stripos($subject, 're:') !== 0) $subject = 'Re: ' . $subject;

        $u = wp_get_current_user();
        $signataire = $u->display_name ?: $u->user_login;
        $body = lfi_nct_generate_reply_body($row, $recu, $intention, $precisions, $signataire);

        $notes2 = json_decode($row->notes ?? '', true);
        if (!is_array($notes2)) $notes2 = [];
        $notes2['replies'] = isset($notes2['replies']) && is_array($notes2['replies']) ? $notes2['replies'] : [];
        $notes2['replies'][] = [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'objet'   => 'Décision locataire : ' . (lfi_nct_reply_intentions()[$intention] ?? $intention),
            'date'    => wp_date('Y-m-d'),
            'src'     => 'ga-membre',
        ];
        $wpdb->update($t, ['notes' => wp_json_encode($notes2), 'updated_at' => current_time('mysql')], ['id' => $id]);
        /* Frais d'accompagnement : un courrier préparé = un frais engagé,
           capitalisé pour le préjudice (via l'avocat) — JAMAIS facturé à NMH. */
        if (function_exists('lfi_nct_frais_log')) {
            lfi_nct_frais_log($id, 'courrier', 'Courrier d\'accompagnement — ' . (lfi_nct_reply_intentions()[$intention] ?? $intention), null, 'auto');
        }
        /* On revient DANS LE DOSSIER de cette personne (compartimenté), pas dans
           la liste globale « À envoyer » où tout se mélange. */
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-edit', ['id' => $id, 'repok' => 1]) . '#sec-reponses');
        exit;
    }

    $nom = trim($row->tenant_prenom . ' ' . $row->tenant_nom) ?: ('Dossier #' . $row->id);
    lfi_nct_app_screen_open('✍️ Générer la réponse', 'Pour ' . $nom . ' — le locataire a décidé, on génère l\'email');

    echo '<div class="lfi-app-help">Tu as vu le locataire ? Choisis ce qu\'il/elle veut faire, ajoute ce qu\'il/elle a dit, et l\'email complet à NMH se génère tout seul dans « À envoyer ». Tu n\'as plus qu\'à l\'ouvrir et l\'envoyer depuis ta boîte.</div>';

    /* L'email reçu auquel on répond. */
    if (!empty($recu['corps']) || !empty($recu['objet'])) {
        echo '<div class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">📩 Message reçu' . (!empty($recu['de']) ? ' — ' . esc_html($recu['de']) : '') . '</div></div>';
        if (!empty($recu['objet'])) echo '<div class="com"><strong>Objet :</strong> ' . esc_html($recu['objet']) . '</div>';
        echo '<details style="margin-top:6px"><summary style="cursor:pointer;color:#0066a3">📖 Lire le message reçu</summary>'
           . '<div class="com" style="white-space:pre-wrap;background:#f7f7f7;border-radius:6px;padding:10px;margin-top:6px">' . esc_html((string) ($recu['corps'] ?? '')) . '</div></details>';
        /* Lecture psy INTERNE (aide à la décision, pas dans l'email). */
        if (function_exists('lfi_nct_psy_analyse')) {
            $rep = lfi_nct_psy_analyse((string) ($recu['corps'] ?? ''), 'institution');
            echo '<div class="com" style="color:#4b2e83;font-size:.9em"><strong>🧠 Lecture (interne) :</strong> ' . esc_html($rep['label'] . ' — ' . $rep['ton']) . '</div>';
        }
        if (function_exists('lfi_nct_penal_labels')) {
            $flags = lfi_nct_penal_labels((string) ($recu['corps'] ?? ''));
            if ($flags) echo '<div class="com" style="color:#c8102e;font-size:.9em"><strong>🛡️ Volet pénal détecté :</strong> ' . esc_html(implode(' · ', $flags)) . ' → sera désamorcé dans la réponse.</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="lfi-app-help">Aucun message reçu archivé dans ce dossier : tu peux quand même générer une réponse, en précisant le destinataire ci-dessous.</div>';
    }

    /* Formulaire : décision du locataire. */
    $to_pref = '';
    if (!empty($recu['de']) && preg_match('/[\w.\-+]+@[\w.\-]+/', (string) $recu['de'], $m)) $to_pref = $m[0];
    echo '<form method="post" class="lfi-app-card" style="border-left:4px solid #186a3b">';
    echo wp_nonce_field('lfi_gen_reply', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_gen_reply" value="1">';
    echo '<div class="head"><div class="who">Qu\'a décidé le locataire ?</div></div>';
    echo '<div style="margin:6px 0"><select name="intention" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px">';
    foreach (lfi_nct_reply_intentions() as $k => $lbl) echo '<option value="' . esc_attr($k) . '">' . esc_html($lbl) . '</option>';
    echo '</select></div>';
    echo '<div style="margin:6px 0"><label>Ce que le locataire a dit (précisions)<br><textarea name="precisions" rows="3" placeholder="ex : il accepte les travaux mais veut être prévenu 48h avant" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></textarea></label></div>';
    echo '<div style="margin:6px 0"><label>Destinataire (email NMH)<br><input type="email" name="to" value="' . esc_attr($to_pref) . '" placeholder="prenom.nom@nmh.fr" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px"></label></div>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#186a3b">⚙️ Générer l\'email (dans « À envoyer »)</button></div>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
