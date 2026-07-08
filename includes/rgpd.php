<?php
/**
 * CADRE RGPD — rendre légale la conservation des « listes ».
 *
 * Point soulevé par Me Gouache : une simple « liste de locataires » n'a pas
 * de base légale. Le mécanisme : on ne tient PAS une liste de locataires,
 * mais des FICHIERS QUALIFIÉS, chacun avec une base légale RGPD :
 *   - Fichier des adhérent·es / futur·es adhérent·es (association) — consentement.
 *   - Fichier clients / prospects (Fabrice Doucet, micro-entreprise) — contrat / précontrat.
 *   - Enquête logement (agrégée / anonyme) — consentement au recontact.
 *
 * Ce module fournit : la doctrine en clair, le Registre des traitements
 * (RGPD art. 30), la Politique de confidentialité, et le Droit à l'effacement.
 * La validation juridique finale revient à l'avocat.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_RGPD_DBVER = 'lfi_nct_rgpd_db_ver';

/* Ajoute une « qualité » aux membres pour qualifier légalement le fichier. */
add_action('init', 'lfi_nct_rgpd_db_setup', 6);
function lfi_nct_rgpd_db_setup() {
    if (get_option(LFI_NCT_RGPD_DBVER) === '1') return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_membres';
    if ($wpdb->get_var("SHOW TABLES LIKE '$t'") === $t) {
        $has = $wpdb->get_var("SHOW COLUMNS FROM $t LIKE 'qualite'");
        if (!$has) $wpdb->query("ALTER TABLE $t ADD COLUMN qualite VARCHAR(30) DEFAULT 'adherent'");
    }
    update_option(LFI_NCT_RGPD_DBVER, '1', false);
}

function lfi_nct_rgpd_can() { return current_user_can('manage_options'); }

function lfi_nct_rgpd_qualites() {
    return [
        'adherent'       => 'Adhérent·e (association)',
        'futur_adherent' => 'Futur·e adhérent·e / sympathisant·e',
        'client'         => 'Client·e (travaux — Fabrice Doucet)',
        'prospect'       => 'Prospect (devis en cours)',
    ];
}

/* ============================================================== *
 *  VUE : Cadre RGPD (doctrine + accès aux documents + effacement) *
 * ============================================================== */
function lfi_nct_app_view_rgpd() {
    if (!lfi_nct_rgpd_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    lfi_nct_app_screen_open('🛡️ Cadre RGPD', 'Garder les fichiers dans la légalité (point Me Gouache)');

    echo '<div class="lfi-app-help">Il n\'existe <strong>pas de « liste de locataires »</strong> (illégal, sans base). À la place, des <strong>fichiers qualifiés</strong>, chacun avec une base légale.</div>';

    echo '<h3 style="margin:14px 0 6px;color:#c8102e">📁 Les fichiers qualifiés</h3>';
    echo '<ul class="lfi-app-list">';
    $files = [
        ['🤝 Adhérent·es & futur·es adhérent·es', 'Association Union des Quartiers Libres', 'Base : consentement (adhésion) / intérêt légitime. La personne y est parce qu\'elle a adhéré ou demandé à être accompagnée — pas parce qu\'elle est locataire.'],
        ['🔧 Clients & prospects', 'Fabrice Doucet (micro-entreprise)', 'Base : contrat / mesures précontractuelles (devis). Obligation comptable pour les factures.'],
        ['📋 Enquête logement', 'Groupe d\'Action LFI Nantes Sud – Clos Toreau', 'Base : consentement au recontact ; l\'exploitation publique est anonyme / agrégée.'],
    ];
    foreach ($files as $f) {
        echo '<li class="lfi-app-card"><div class="head"><div class="who">' . esc_html($f[0]) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">' . esc_html($f[1]) . '</span></div>';
        echo '<div class="com">' . esc_html($f[2]) . '</div></li>';
    }
    echo '</ul>';

    /* Répartition des membres par qualité. */
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_membres';
    if ($wpdb->get_var("SHOW COLUMNS FROM $t LIKE 'qualite'")) {
        $gac = function_exists('lfi_nct_membres_ga_clause') ? lfi_nct_membres_ga_clause('ga') : '';
        $rows = $wpdb->get_results("SELECT qualite, COUNT(*) n FROM $t WHERE 1=1" . $gac . " GROUP BY qualite") ?: [];
        if ($rows) {
            $ql = lfi_nct_rgpd_qualites();
            echo '<div class="lfi-app-help" style="background:#f7f7f7"><strong>Ton fichier membres, par qualité :</strong> ';
            $parts = [];
            foreach ($rows as $r) $parts[] = esc_html(($ql[$r->qualite] ?? $r->qualite) . ' : ' . $r->n);
            echo implode(' · ', $parts) . '</div>';
        }
    }

    echo '<h3 style="margin:16px 0 6px;color:#c8102e">📄 Documents de conformité</h3>';
    echo '<div class="row-actions" style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('rgpd-registre')) . '" target="_blank">📑 Registre des traitements (art. 30)</a>';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('rgpd-politique')) . '" target="_blank">🔒 Politique de confidentialité</a>';
    echo '</div>';
    echo '<div class="lfi-app-help"><small>Ces deux documents sont à jour, imprimables, et à faire valider par Me Gouache. À afficher / tenir à disposition.</small></div>';

    /* Droit à l'effacement. */
    echo '<h3 style="margin:18px 0 6px;color:#c8102e">🧹 Droit à l\'effacement</h3>';
    echo '<div class="lfi-app-help">Une personne demande la suppression de ses données ? Cherche-la, puis efface. (Les factures sont conservées 10 ans — obligation comptable — et ne sont pas effacées ici.)</div>';
    echo '<form method="get" class="lfi-app-searchbar" style="margin-bottom:10px">';
    echo '<input type="hidden" name="vue" value="rgpd">';
    echo '<input type="search" name="q" value="' . esc_attr(isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '') . '" placeholder="Nom, email ou téléphone…">';
    echo '<button type="submit">🔎</button>';
    echo '</form>';

    if (!empty($_GET['erased'])) lfi_nct_app_flash('🧹 Données effacées / anonymisées.');

    $q = isset($_GET['q']) ? trim(sanitize_text_field(wp_unslash($_GET['q']))) : '';
    if ($q !== '') lfi_nct_rgpd_render_search($q);

    lfi_nct_app_screen_close();
}

/* Recherche multi-fichiers + effacement. */
function lfi_nct_rgpd_render_search($q) {
    global $wpdb;
    $like = '%' . $wpdb->esc_like($q) . '%';

    $mem = $wpdb->prefix . 'lfi_nct_membres';
    $rep = $wpdb->prefix . 'lfi_nct_responses';
    $dos = $wpdb->prefix . 'lfi_nct_dossiers_locataires';

    $membres = $wpdb->get_results($wpdb->prepare(
        "SELECT id, prenom, nom, email, tel FROM $mem WHERE CONCAT(prenom,' ',nom) LIKE %s OR email LIKE %s OR tel LIKE %s LIMIT 25",
        $like, $like, $like)) ?: [];
    $reps = $wpdb->get_results($wpdb->prepare(
        "SELECT id, contact_prenom, contact_nom, contact_email, contact_tel FROM $rep WHERE deleted_at IS NULL AND (CONCAT(contact_prenom,' ',contact_nom) LIKE %s OR contact_email LIKE %s OR contact_tel LIKE %s) LIMIT 25",
        $like, $like, $like)) ?: [];
    $doss = $wpdb->get_results($wpdb->prepare(
        "SELECT id, tenant_prenom, tenant_nom, tenant_email FROM $dos WHERE CONCAT(tenant_prenom,' ',tenant_nom) LIKE %s OR tenant_email LIKE %s LIMIT 25",
        $like, $like)) ?: [];

    if (!$membres && !$reps && !$doss) {
        echo '<div class="lfi-app-empty">Aucune donnée trouvée pour « ' . esc_html($q) . ' ».</div>';
        return;
    }

    if ($membres) {
        echo '<h4 style="margin:12px 0 4px">🤝 Fichier membres (' . count($membres) . ')</h4><ul class="lfi-app-list">';
        foreach ($membres as $m) {
            echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">' . esc_html(trim($m->prenom . ' ' . $m->nom)) . '</div></div>';
            echo '<div class="meta">' . ($m->email ? '<span class="meta-chip">✉️ ' . esc_html($m->email) . '</span>' : '') . ($m->tel ? '<span class="meta-chip">📞 ' . esc_html($m->tel) . '</span>' : '') . '</div>';
            echo lfi_nct_rgpd_erase_button('membre', (int) $m->id, $q);
            echo '</li>';
        }
        echo '</ul>';
    }
    if ($reps) {
        echo '<h4 style="margin:12px 0 4px">📋 Réponses d\'enquête (' . count($reps) . ')</h4><ul class="lfi-app-list">';
        foreach ($reps as $r) {
            echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">' . esc_html(trim($r->contact_prenom . ' ' . $r->contact_nom) ?: '(anonyme)') . '</div></div>';
            echo '<div class="meta">' . ($r->contact_email ? '<span class="meta-chip">✉️ ' . esc_html($r->contact_email) . '</span>' : '') . ($r->contact_tel ? '<span class="meta-chip">📞 ' . esc_html($r->contact_tel) . '</span>' : '') . '</div>';
            echo lfi_nct_rgpd_erase_button('reponse', (int) $r->id, $q);
            echo '</li>';
        }
        echo '</ul>';
    }
    if ($doss) {
        echo '<h4 style="margin:12px 0 4px">🗂 Dossiers d\'accompagnement (' . count($doss) . ')</h4><ul class="lfi-app-list">';
        foreach ($doss as $d) {
            echo '<li class="lfi-app-card" style="padding:9px 12px"><div class="head"><div class="who">' . esc_html(trim($d->tenant_prenom . ' ' . $d->tenant_nom)) . '</div></div>';
            echo '<div class="lfi-app-help" style="margin:4px 0 0"><small>⚠️ Un dossier peut être lié à des factures (conservation légale 10 ans). Suppression à faire manuellement dans le dossier après vérification.</small></div>';
            echo '</li>';
        }
        echo '</ul>';
    }
}

function lfi_nct_rgpd_erase_button($type, $id, $q) {
    ob_start();
    echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Effacer définitivement ces données ? Action irréversible.\');">';
    wp_nonce_field('lfi_rgpd_erase');
    echo '<input type="hidden" name="lfi_rgpd_erase" value="1">';
    echo '<input type="hidden" name="etype" value="' . esc_attr($type) . '">';
    echo '<input type="hidden" name="eid" value="' . (int) $id . '">';
    echo '<input type="hidden" name="q" value="' . esc_attr($q) . '">';
    echo '<button type="submit" class="btn-ghost" style="color:#c8102e;border-color:#c8102e">🧹 Effacer (droit à l\'oubli)</button>';
    echo '</form>';
    return ob_get_clean();
}

/* Traitement de l'effacement (hook tôt, avant le rendu). */
add_action('init', 'lfi_nct_rgpd_handle_erase', 20);
function lfi_nct_rgpd_handle_erase() {
    if (empty($_POST['lfi_rgpd_erase'])) return;
    if (!lfi_nct_rgpd_can()) return;
    if (!check_admin_referer('lfi_rgpd_erase')) return;
    global $wpdb;
    $type = sanitize_key($_POST['etype'] ?? '');
    $id   = (int) ($_POST['eid'] ?? 0);
    $q    = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
    if ($id) {
        if ($type === 'membre') {
            $wpdb->delete($wpdb->prefix . 'lfi_nct_membres', ['id' => $id]);
        } elseif ($type === 'reponse') {
            /* Anonymisation : on retire les coordonnées et on marque supprimé. */
            $wpdb->update($wpdb->prefix . 'lfi_nct_responses', [
                'contact_prenom' => null, 'contact_nom' => null,
                'contact_email' => null, 'contact_tel' => null,
                'deleted_at' => current_time('mysql'),
            ], ['id' => $id]);
        }
    }
    wp_safe_redirect(lfi_nct_app_url('rgpd', ['q' => $q, 'erased' => 1]));
    exit;
}

/* ============================================================== *
 *  Documents imprimables                                          *
 * ============================================================== */
function lfi_nct_rgpd_doc_open($titre) {
    echo '<div class="lfi-app"><div style="max-width:800px;margin:0 auto;padding:16px">';
    echo '<div class="row-actions" style="margin-bottom:10px"><button onclick="window.print()" class="btn-primary">🖨 Imprimer / PDF</button> <a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('rgpd')) . '">← Retour</a></div>';
    echo '<div style="background:#fff;padding:24px;border:1px solid #ddd;border-radius:8px;line-height:1.55">';
    echo '<h1 style="color:#c8102e;font-size:1.5em;margin:0 0 4px">' . esc_html($titre) . '</h1>';
    echo '<div style="color:#666;font-size:.9em;margin-bottom:14px">Établi le ' . esc_html(wp_date('j F Y')) . ' — à faire valider par le conseil juridique.</div>';
}
function lfi_nct_rgpd_doc_close() { echo '</div></div></div>'; }

function lfi_nct_app_view_rgpd_registre() {
    if (!lfi_nct_rgpd_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_rgpd_doc_open('Registre des activités de traitement (RGPD, art. 30)');
    $traitements = [
        ['1. Fichier des adhérent·es & sympathisant·es',
         'Association Union des Quartiers Libres (président : Fabrice Doucet)',
         'Gérer la vie associative, accompagner les habitant·es, informer.',
         'Consentement (adhésion) ; intérêt légitime (sympathisant·es).',
         'Adhérent·es, futur·es adhérent·es, sympathisant·es.',
         'Identité, coordonnées, groupe d\'action, préférences d\'abonnement.',
         'Référent·es du groupe d\'action (accès cloisonné).',
         'Durée de l\'adhésion + 3 ans ; sympathisant·es : 3 ans après dernier contact.'],
        ['2. Fichier clients / prospects (travaux)',
         'Fabrice Doucet — micro-entreprise',
         'Établir des devis, réaliser des travaux, facturer, relancer.',
         'Exécution du contrat / mesures précontractuelles ; obligation légale (comptable) pour les factures.',
         'Client·es, prospects.',
         'Identité, coordonnées, adresse du logement, interventions, montants.',
         'Fabrice Doucet ; comptable le cas échéant.',
         'Factures : 10 ans (obligation comptable) ; prospects : 3 ans après dernier contact.'],
        ['3. Enquête logement (porte-à-porte)',
         'Groupe d\'Action LFI Nantes Sud – Clos Toreau',
         'Recenser les problèmes de logement, accompagner, plaider (exploitation anonyme / agrégée).',
         'Consentement (au recontact) ; intérêt légitime pour l\'agrégat anonyme.',
         'Habitant·es enquêté·es.',
         'Réponses au questionnaire ; coordonnées uniquement si consentement au recontact.',
         'Référent·es du groupe d\'action.',
         '3 ans, puis anonymisation ; retrait immédiat sur demande.'],
        ['4. Dossiers d\'accompagnement locataires',
         'Association Union des Quartiers Libres',
         'Accompagner une personne dans ses démarches (logement indigne), à sa demande.',
         'Consentement + adhésion ; intérêt légitime.',
         'Locataires accompagné·es.',
         'Constatations, correspondance ; données de santé (certificat) : consentement explicite, accès restreint.',
         'Référent·es du dossier (accès cloisonné).',
         'Durée de l\'accompagnement + archivage limité, puis suppression.'],
    ];
    foreach ($traitements as $tr) {
        echo '<div style="border:1px solid #e3e3e3;border-radius:8px;padding:12px 14px;margin:10px 0">';
        echo '<h2 style="font-size:1.05em;color:#0066a3;margin:0 0 6px">' . esc_html($tr[0]) . '</h2>';
        $labels = ['Responsable de traitement', 'Finalité', 'Base légale', 'Personnes concernées', 'Données', 'Destinataires', 'Durée de conservation'];
        for ($i = 0; $i < count($labels); $i++) {
            echo '<div style="margin:3px 0"><strong>' . esc_html($labels[$i]) . ' :</strong> ' . esc_html($tr[$i + 1]) . '</div>';
        }
        echo '</div>';
    }
    echo '<p style="margin-top:12px"><strong>Mesures de sécurité communes :</strong> accès restreint et cloisonné par groupe d\'action, authentification, hébergement dans l\'Union européenne, pas de cession ni de revente à des tiers, minimisation des données.</p>';
    echo '<p><strong>Exercice des droits :</strong> nantessudclostoreau@gmail.com.</p>';
    lfi_nct_rgpd_doc_close();
}

function lfi_nct_app_view_rgpd_politique() {
    if (!lfi_nct_rgpd_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_rgpd_doc_open('Politique de confidentialité & information des personnes');
    echo '<p><strong>Qui traite vos données ?</strong> Selon le cas : l\'<strong>Association Union des Quartiers Libres</strong> (vie associative, accompagnement), le <strong>Groupe d\'Action LFI Nantes Sud – Clos Toreau</strong> (enquête logement), ou <strong>Fabrice Doucet</strong> (travaux, devis, factures).</p>';
    echo '<p><strong>Pourquoi ?</strong> Vous accompagner dans vos démarches de logement, animer la vie associative et locale, et — pour l\'activité travaux — établir devis et factures. Nous ne tenons <strong>pas</strong> de « liste de locataires » : vous figurez dans nos fichiers parce que vous avez adhéré, demandé à être accompagné·e, accepté d\'être recontacté·e, ou sollicité une prestation.</p>';
    echo '<p><strong>Sur quelle base ?</strong> Votre consentement, l\'exécution d\'un contrat (ou de mesures précontractuelles), l\'intérêt légitime de l\'association, et nos obligations légales (comptabilité).</p>';
    echo '<p><strong>Combien de temps ?</strong> Le temps nécessaire à la finalité (voir le registre) ; les factures sont conservées 10 ans (obligation légale) ; les données d\'enquête sont anonymisées au bout de 3 ans.</p>';
    echo '<p><strong>Données sensibles :</strong> un éventuel certificat médical n\'est utilisé qu\'avec votre <strong>consentement explicite</strong>, pour votre seul accompagnement, avec un accès restreint.</p>';
    echo '<p><strong>Qui y a accès ?</strong> Uniquement les référent·es concerné·es, avec un accès cloisonné. <strong>Aucune donnée nominative n\'est transmise au bailleur</strong> ni revendue.</p>';
    echo '<p><strong>Vos droits :</strong> accès, rectification, effacement (« droit à l\'oubli »), limitation, opposition, portabilité. Pour les exercer : <strong>nantessudclostoreau@gmail.com</strong>. Vous pouvez aussi saisir la CNIL (www.cnil.fr).</p>';
    echo '<p style="color:#666;font-size:.9em">Document d\'information — à faire valider par le conseil juridique et à adapter si les finalités évoluent.</p>';
    lfi_nct_rgpd_doc_close();
}
