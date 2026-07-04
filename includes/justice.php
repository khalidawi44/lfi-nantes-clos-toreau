<?php
/**
 * BOT JUSTICE — monter les dossiers amiables & judiciaires.
 *
 * Deux briques :
 *  1) VERSER DES PIÈCES : joindre au dossier les documents à transmettre à
 *     Nantes Métropole Habitat, à la Commission de conciliation ou au tribunal
 *     (PDF, photos). Rangées par destination. Visibles par l'admin du GA et par
 *     l'avocat·e à qui le dossier est confié.
 *  2) COMMISSION DÉPARTEMENTALE DE CONCILIATION (CDC) : générer la saisine
 *     pré-remplie + la liste des pièces + les liens officiels (démarche en
 *     ligne 44, Histologe, service-public). Gratuit, paritaire, avis sous 2 mois.
 *
 * Sources publiques (procédure) :
 *  - service-public.gouv.fr/particuliers/vosdroits/F1216
 *  - loire-atlantique.gouv.fr — saisine en ligne depuis le 01/01/2025
 *  - secrétariat CDC 44 : 02 72 20 63 04
 * Aucun chiffre de préjudice n'est inventé : la lettre renvoie au dossier.
 */
if (!defined('ABSPATH')) exit;

/** Destinations possibles d'une pièce versée. */
function lfi_nct_justice_destinations() {
    return [
        'interne' => '🔒 Interne au GA',
        'nmh'     => '🏢 À transmettre à Nantes Métropole Habitat',
        'cdc'     => '⚖️ Pour la Commission de conciliation',
        'tj'      => '🏛️ Pour le Tribunal Judiciaire',
    ];
}

/** Pièces versées à un dossier (attachments taggés au locataire). */
function lfi_nct_justice_pieces($tenant_uid) {
    return get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 200,
        'orderby'        => 'date', 'order' => 'DESC',
        'meta_query'     => [
            ['key' => '_lfi_dossier_piece', 'value' => '1'],
            ['key' => '_lfi_tenant_user_id', 'value' => (int) $tenant_uid],
        ],
    ]);
}

/** Upload d'une ou plusieurs pièces (PDF / images), taggées destination. */
function lfi_nct_justice_handle_upload($tenant_uid) {
    if (empty($_FILES['justice_pieces']['name'][0])) return 0;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $dest = sanitize_key($_POST['piece_dest'] ?? 'interne');
    if (!array_key_exists($dest, lfi_nct_justice_destinations())) $dest = 'interne';
    $f = $_FILES['justice_pieces'];
    $count = is_array($f['name']) ? count($f['name']) : 0;
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'];
    $done = 0;
    for ($i = 0; $i < $count; $i++) {
        if (empty($f['name'][$i]) || !empty($f['error'][$i])) continue;
        $type = (string) ($f['type'][$i] ?? '');
        if ($type && !in_array($type, $allowed, true) && strpos($type, 'image/') !== 0) continue;
        $_FILES['lfi_justice_one'] = ['name' => $f['name'][$i], 'type' => $type, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        $aid = media_handle_upload('lfi_justice_one', 0);
        if (!is_wp_error($aid)) {
            update_post_meta($aid, '_lfi_dossier_piece', '1');
            update_post_meta($aid, '_lfi_tenant_user_id', (int) $tenant_uid);
            update_post_meta($aid, '_lfi_dossier_piece_dest', $dest);
            $done++;
        }
    }
    unset($_FILES['lfi_justice_one']);
    return $done;
}

/** Boîte « verser des pièces » + liste, réutilisable sur le dossier. */
function lfi_nct_justice_pieces_box($u, $editable = true) {
    $dests  = lfi_nct_justice_destinations();
    $pieces = lfi_nct_justice_pieces($u->ID);
    echo '<details style="margin-top:10px;background:#fff;border-radius:10px;border:1px solid #eee;overflow:hidden">';
    echo '<summary style="cursor:pointer;padding:10px 12px;font-weight:800;color:#0066a3;list-style:none;display:flex;justify-content:space-between;align-items:center"><span>📎 Pièces à transmettre (' . count($pieces) . ')</span><span>▾</span></summary>';
    echo '<div style="padding:2px 12px 12px">';
    if ($editable) {
        echo '<form method="post" enctype="multipart/form-data" style="margin:6px 0;padding:10px;background:#f7fafd;border-radius:8px">';
        wp_nonce_field('lfi_justice_piece');
        echo '<input type="hidden" name="lfi_justice_piece" value="1">';
        echo '<label style="margin:0">Destination<select name="piece_dest" style="width:100%;margin-top:4px">';
        foreach ($dests as $k => $l) echo '<option value="' . esc_attr($k) . '">' . esc_html($l) . '</option>';
        echo '</select></label>';
        echo '<label style="margin:6px 0 0;display:block">Fichiers (PDF, photos)<input type="file" name="justice_pieces[]" accept="application/pdf,image/*" multiple style="margin-top:4px"></label>';
        echo '<button type="submit" class="btn-primary" style="background:#0066a3;margin-top:8px">📎 Verser au dossier</button>';
        echo '</form>';
    }
    if (empty($pieces)) {
        echo '<div class="lfi-app-help">Aucune pièce versée. Ajoute ici les documents à transmettre (bail, état des lieux, courriers, photos, certificats…).</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach ($pieces as $p) {
            $dest = (string) get_post_meta($p->ID, '_lfi_dossier_piece_dest', true);
            $url  = wp_get_attachment_url($p->ID);
            $mime = get_post_mime_type($p->ID);
            $ico  = ($mime === 'application/pdf') ? '📄' : '🖼';
            echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
            echo '<div class="head"><div class="who">' . $ico . ' ' . esc_html(get_the_title($p->ID) ?: 'Pièce') . '</div>';
            if (isset($dests[$dest])) echo '<div class="badge">' . esc_html($dests[$dest]) . '</div>';
            echo '</div>';
            echo '<div class="row-actions" style="margin-top:6px"><a class="btn-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener">📂 Ouvrir</a>';
            if ($editable) {
                $del = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_justice_piece_del&aid=' . (int) $p->ID . '&uid=' . (int) $u->ID), 'lfi_justice_del_' . (int) $p->ID);
                echo ' <a class="btn-ghost" href="' . esc_url($del) . '" onclick="return confirm(\'Retirer cette pièce ?\')">🗑 Retirer</a>';
            }
            echo '</div></li>';
        }
        echo '</ul>';
    }
    echo '</div></details>';
}

add_action('admin_post_lfi_nct_justice_piece_del', 'lfi_nct_justice_piece_del_handler');
function lfi_nct_justice_piece_del_handler() {
    $aid = (int) ($_GET['aid'] ?? 0);
    $uid = (int) ($_GET['uid'] ?? 0);
    if (!$aid || !check_admin_referer('lfi_justice_del_' . $aid)) wp_die('non');
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if ($can && (int) get_post_meta($aid, '_lfi_dossier_piece', true) && (!function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($uid))) {
        wp_delete_attachment($aid, true);
    }
    wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $uid, 'piece_del' => 1]));
    exit;
}

/* ============================================================== *
 *  VUE : Commission de conciliation — monter la saisine          *
 * ============================================================== */
function lfi_nct_app_view_justice_cdc() {
    global $wpdb;
    $uid = (int) ($_GET['uid'] ?? 0);
    $is_admin = function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
    $is_avocat = function_exists('lfi_nct_user_role_avocat') && lfi_nct_user_role_avocat()
        && function_exists('lfi_nct_avocat_of_tenant') && lfi_nct_avocat_of_tenant($uid) === get_current_user_id();
    if (!$is_admin && !$is_avocat) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $u = $uid ? get_userdata($uid) : null;
    if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Versement de pièces depuis cette page (admin). */
    if ($is_admin && !empty($_POST['lfi_justice_piece']) && check_admin_referer('lfi_justice_piece')) {
        $n = lfi_nct_justice_handle_upload($uid);
        wp_safe_redirect(lfi_nct_app_url('justice-cdc', ['uid' => $uid, 'up' => (int) $n])); exit;
    }

    $d  = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($uid) : null;
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $problem = ($resp && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($resp) : null;

    $name = trim(($d->tenant_prenom ?? '') . ' ' . ($d->tenant_nom ?? '')) ?: $u->display_name;
    $adresse = $d->tenant_adresse ?? ($resp->adresse ?? '');
    $etage = $d->tenant_etage ?? ($resp->etage ?? '');
    $logement = trim($adresse . ($etage ? ' · étage ' . $etage : ''));
    $desordres = ($problem && !empty($problem['chips'])) ? implode(', ', array_map(function ($c) { return $c[1]; }, $problem['chips'])) : '';

    lfi_nct_app_screen_open('⚖️ Commission de conciliation', 'Monter la saisine — ' . $name);
    echo '<div style="margin-bottom:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => $uid])) . '">← Retour au dossier</a></div>';
    if (!empty($_GET['up'])) lfi_nct_app_flash('📎 ' . (int) $_GET['up'] . ' pièce(s) versée(s).');

    /* Comment ça marche */
    echo '<div class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
    echo '<div class="head"><div class="who">ℹ️ La Commission départementale de conciliation (CDC)</div></div>';
    echo '<div class="com" style="line-height:1.55">';
    echo '<p style="margin:6px 0"><strong>Gratuite</strong>, paritaire (autant de représentants des bailleurs que des locataires). Elle cherche un <strong>accord amiable</strong> avant le juge et rend un <strong>avis sous 2 mois</strong>.</p>';
    echo '<p style="margin:6px 0"><strong>Compétente</strong> pour : réparations / entretien, <strong>décence du logement</strong> (décret 2002-120), charges, dépôt de garantie, état des lieux. Pour un logement <strong>social</strong> (NMH), la saisine est <em>facultative</em> mais utile : un désaccord acté ouvre la voie au Tribunal Judiciaire.</p>';
    echo '<p style="margin:6px 0;color:#a30b25"><strong>À noter :</strong> la <strong>non-décence / insalubrité</strong> se signale <em>aussi</em> sur Histologe (elle ne relève pas directement de la CDC). Le locataire continue de payer loyer et charges pendant la procédure.</p>';
    echo '</div>';
    echo '<div class="row-actions" style="margin-top:6px;flex-wrap:wrap">';
    echo '<a class="btn-primary" style="background:#6a1b9a" href="https://demarche.numerique.gouv.fr/commencer/la-commission-departementale-de-conciliation-44" target="_blank" rel="noopener">🖥️ Saisir en ligne (Loire-Atlantique)</a>';
    echo '<a class="btn-ghost" href="https://histologe.beta.gouv.fr/" target="_blank" rel="noopener">🏚️ Signaler sur Histologe</a>';
    echo '<a class="btn-ghost" href="https://www.service-public.gouv.fr/particuliers/vosdroits/F1216" target="_blank" rel="noopener">📖 Fiche service-public</a>';
    echo '</div>';
    echo '<div class="lfi-app-help" style="margin-top:6px"><small>Secrétariat CDC 44 : <strong>02 72 20 63 04</strong> (lun–ven 9h30–12h, mer 14h–16h). Saisine possible aussi par lettre recommandée AR.</small></div>';
    echo '</div>';

    /* Pièces à joindre (checklist) */
    echo '<h3 style="margin:16px 0 6px;color:#0066a3">📋 Pièces à joindre à la saisine</h3>';
    $checklist = [
        'Copie du bail (contrat de location)',
        'État des lieux d\'entrée (et de sortie le cas échéant)',
        'Quittances de loyer récentes',
        'Tous les courriers / emails échangés avec NMH (mises en demeure, réponses)',
        'Photos datées des désordres',
        'Certificats médicaux liés au logement (si applicable)',
        'Constats / signalements SCHS, ARS, Histologe (si applicable)',
    ];
    echo '<ul style="padding:0 0 0 4px;list-style:none;line-height:1.7">';
    foreach ($checklist as $c) echo '<li>☐ ' . esc_html($c) . '</li>';
    echo '</ul>';

    /* Pièces versées + upload */
    lfi_nct_justice_pieces_box($u, $is_admin);

    /* Lettre de saisine pré-remplie */
    $moi = wp_get_current_user();
    $moi_nom = $moi->display_name ?: $moi->user_login;
    $today = function_exists('wp_date') ? wp_date('j F Y') : date('d/m/Y');
    $lettre  = "Objet : Saisine de la Commission départementale de conciliation — litige locatif\n\n";
    $lettre .= "Madame, Monsieur,\n\n";
    $lettre .= "Je soussigné·e " . $name . ", locataire du logement situé " . ($logement ?: '[adresse]') . ", ";
    $lettre .= "bailleur Nantes Métropole Habitat, saisis la Commission départementale de conciliation de Loire-Atlantique.\n\n";
    $lettre .= "Exposé du litige :\n";
    $lettre .= "Le logement présente des désordres persistants" . ($desordres ? " : " . $desordres . "." : ".") . " ";
    $lettre .= "Malgré mes démarches auprès du bailleur, la situation n'a pas été réglée, ce qui porte atteinte à la décence du logement et à ma jouissance paisible des lieux (art. 6 de la loi du 6 juillet 1989 ; art. 1719 et 1721 du Code civil ; décret n° 2002-120).\n\n";
    $lettre .= "Demandes :\n";
    $lettre .= "- la réalisation des travaux nécessaires pour rendre le logement décent ;\n";
    $lettre .= "- la réparation du préjudice subi (trouble de jouissance), dont le détail figure dans mon dossier ;\n";
    $lettre .= "- toute mesure utile que la Commission jugera appropriée.\n\n";
    $lettre .= "Vous trouverez ci-joint les pièces justificatives.\n\n";
    $lettre .= "Je reste à votre disposition pour être entendu·e.\n\n";
    $lettre .= "Fait le " . $today . ".\n" . $name . "\n";
    echo '<h3 style="margin:16px 0 6px;color:#186a3b">✍️ Lettre de saisine (pré-remplie — à relire)</h3>';
    echo '<div class="lfi-app-help"><small>Relis et complète les crochets [ ]. Le montant du préjudice reste dans le dossier — on annonce le préjudice sans livrer tout le détail. Copie ce texte ou colle-le dans la démarche en ligne.</small></div>';
    echo '<textarea readonly onclick="this.select()" style="width:100%;height:320px;margin-top:6px;font-size:.85em;padding:10px;border:1px solid #ccc;border-radius:8px;line-height:1.5">' . esc_textarea($lettre) . '</textarea>';

    /* Jurisprudence à l'appui */
    if (function_exists('lfi_nct_juris_can') && lfi_nct_juris_can()) {
        echo '<div style="margin-top:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('jurisprudence', ['dossier' => $d ? (int) $d->id : 0])) . '">🔎 Jurisprudence à l\'appui (Judilibre)</a></div>';
    }

    lfi_nct_app_screen_close();
}
