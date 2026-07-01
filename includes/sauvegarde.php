<?php
/**
 * SAUVEGARDE / EXPORT — télécharge toutes les données du site dans une
 * arborescence claire, cloisonnée par groupe d'action, avec un SYSTÈME DE
 * POINT FIXE : on peut ne télécharger que les NOUVEAUTÉS depuis la dernière
 * sauvegarde (sans tout re-télécharger).
 *
 * Le ZIP se dézippe directement dans le dossier « LFI » (mêmes noms de
 * dossiers que la structure fournie). Réservé au super-admin.
 */
if (!defined('ABSPATH')) exit;

/** Date du dernier point fixe (format mysql), ou '' si jamais fait. */
function lfi_nct_backup_checkpoint() {
    return (string) get_option('lfi_nct_backup_checkpoint', '');
}

/** Propriétaire (uid) des dossiers locataires d'un GA (comme à la création). */
function lfi_nct_backup_ga_owner($slug) {
    if ($slug !== '' && $slug !== 'clos-toreau') {
        if (function_exists('lfi_nct_ga_pivot_uid')) { $p = (int) lfi_nct_ga_pivot_uid($slug); if ($p) return $p; }
        if (function_exists('lfi_nct_ga_admin_uids')) { $u = lfi_nct_ga_admin_uids($slug); if (!empty($u)) return (int) $u[0]; }
        if (function_exists('lfi_nct_ga_phantom_owner')) return (int) lfi_nct_ga_phantom_owner($slug);
        return 0;
    }
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => ['ID']]);
    return !empty($admins) ? (int) (is_object($admins[0]) ? $admins[0]->ID : $admins[0]) : 1;
}

/** Nom de dossier propre et sans accents pour un GA. */
function lfi_nct_backup_ga_folder($slug, $nom) {
    if ($slug === '' || $slug === 'clos-toreau') return 'GA_Clos-Toreau';
    $clean = function_exists('remove_accents') ? remove_accents((string) $nom) : (string) $nom;
    $clean = preg_replace('/^(GA|Groupe d.?Action)\s+(de\s+|du\s+|des\s+|d.?)?/i', '', $clean);
    $clean = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $clean), '-');
    if ($clean === '') $clean = preg_replace('/[^A-Za-z0-9]+/', '-', $slug);
    return 'GA_' . $clean;
}

/** Sous-dossiers standard d'un GA (identiques à la structure « LFI »). */
function lfi_nct_backup_ga_subdirs() {
    return [
        '01_Membres_actifs',
        '02_Enquetes_logement/Reponses',
        '02_Enquetes_logement/Photos_horodatees',
        '03_Locataires_accompagnes/_Locataire_MODELE_a-dupliquer/Dossier_juridique',
        '03_Locataires_accompagnes/_Locataire_MODELE_a-dupliquer/Photos',
        '03_Locataires_accompagnes/_Locataire_MODELE_a-dupliquer/Courriers_NMH_mise-en-demeure_LRAR',
        '03_Locataires_accompagnes/_Locataire_MODELE_a-dupliquer/Certificats_medicaux',
        '03_Locataires_accompagnes/_Locataire_MODELE_a-dupliquer/Note_pour_avocat',
        '04_Evenements/Flyers_et_QR',
        '04_Evenements/Inscriptions',
        '05_Travaux_brigade',
        '06_Statistiques_et_cartes',
        '07_Prefecture_anonyme_par_batiment',
        '08_Reussites',
    ];
}

/** Pose l'arborescence COMPLÈTE (même vide) pour que « tout » soit dans le ZIP. */
function lfi_nct_backup_add_scaffold($zip, $gas) {
    $top = [
        '00_SITE_ET_ASSOCIATION/Association_Union_des_Quartiers_Libres/Statuts_et_identite',
        '00_SITE_ET_ASSOCIATION/Association_Union_des_Quartiers_Libres/Adhesions',
        '00_SITE_ET_ASSOCIATION/Association_Union_des_Quartiers_Libres/Comptes_rendus_reunions',
        '00_SITE_ET_ASSOCIATION/Modeles_courriers',
        '00_SITE_ET_ASSOCIATION/Charte_et_RGPD',
        '00_SITE_ET_ASSOCIATION/Sauvegardes_site_web',
        '01_VOLET_NATIONAL_deputes/Statistiques_nationales',
        '01_VOLET_NATIONAL_deputes/Elements_de_langage',
        '01_VOLET_NATIONAL_deputes/Etudes_et_donnees_scientifiques/Finances_Nantes_Metropole_Habitat',
        '01_VOLET_NATIONAL_deputes/Dossiers_deputes',
        '02_VOLET_MUNICIPAL_elus_locaux/Statistiques_cumulees',
        '02_VOLET_MUNICIPAL_elus_locaux/Cartes_generales',
        '02_VOLET_MUNICIPAL_elus_locaux/Dossiers_elus_municipaux',
    ];
    foreach ($top as $d) { $zip->addEmptyDir($d); $zip->addFromString($d . '/_a-remplir.txt', "Depose ici tes fichiers.\n"); }
    foreach ($gas as $g) {
        $base = '03_GROUPES_D_ACTION/' . lfi_nct_backup_ga_folder($g['slug'], $g['nom']);
        foreach (lfi_nct_backup_ga_subdirs() as $sd) {
            $zip->addEmptyDir($base . '/' . $sd);
            $zip->addFromString($base . '/' . $sd . '/_a-remplir.txt', "Depose ici tes fichiers.\n");
        }
    }
}

/** Construit une chaîne CSV (UTF-8 + BOM Excel) depuis un tableau de lignes. */
function lfi_nct_backup_csv($header, $rows) {
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, $header);
    foreach ($rows as $r) fputcsv($fh, $r);
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return "\xEF\xBB\xBF" . $csv; // BOM → accents corrects dans Excel
}

/* ============================================================== *
 *  Remplit le ZIP avec toutes les données (ou les nouveautés)     *
 * ============================================================== */
function lfi_nct_backup_fill_zip($zip, $since) {
    global $wpdb;
    $R = $wpdb->prefix . 'lfi_nct_responses';
    $M = $wpdb->prefix . 'lfi_nct_membres';
    $D = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $R_RSVP = $wpdb->prefix . 'lfi_nct_event_rsvp';

    $has_since = ($since !== '' && $since !== null);
    $media_bytes = 0; $media_cap = 250 * 1024 * 1024; // 250 Mo de photos max

    $add_media = function ($att_id, $zip_path) use ($zip, &$media_bytes, $media_cap) {
        $att_id = (int) $att_id;
        if (!$att_id || $media_bytes > $media_cap) return false;
        $file = get_attached_file($att_id);
        if (!$file || !file_exists($file)) return false;
        $size = (int) @filesize($file);
        if ($media_bytes + $size > $media_cap) return false;
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $zip->addFile($file, $zip_path . '-' . $att_id . ($ext ? '.' . $ext : ''));
        $media_bytes += $size;
        return true;
    };

    /* Liste des GA : « home » (Clos Toreau) + tous les autres. */
    $gas = [['slug' => 'clos-toreau', 'nom' => 'Clos Toreau']];
    if (function_exists('lfi_nct_groupes')) {
        foreach (lfi_nct_groupes(true) as $g) {
            if (($g['slug'] ?? '') === '' || ($g['slug'] ?? '') === 'clos-toreau') continue;
            $gas[] = ['slug' => $g['slug'], 'nom' => $g['nom'] ?? $g['slug']];
        }
    }

    /* Sauvegarde COMPLÈTE : on pose d'abord toute l'arborescence (même vide),
       pour que le ZIP contienne « tout » et se calque sur le dossier LFI. */
    if (!$has_since) lfi_nct_backup_add_scaffold($zip, $gas);

    foreach ($gas as $g) {
        $slug   = $g['slug'];
        $folder = '03_GROUPES_D_ACTION/' . lfi_nct_backup_ga_folder($slug, $g['nom']);
        $is_home = ($slug === 'clos-toreau');
        $ga_where = $is_home ? "(ga = '' OR ga = 'clos-toreau' OR ga IS NULL)" : $wpdb->prepare('ga = %s', $slug);

        /* ---- Enquêtes ---- */
        $sql = "SELECT * FROM $R WHERE deleted_at IS NULL AND $ga_where";
        if ($has_since) $sql .= $wpdb->prepare(' AND submitted_at > %s', $since);
        $sql .= ' ORDER BY submitted_at ASC';
        $rows = $wpdb->get_results($sql) ?: [];
        if ($rows) {
            $csv = [];
            foreach ($rows as $r) {
                $d = json_decode((string) $r->data, true); if (!is_array($d)) $d = [];
                $ref = function_exists('lfi_nct_response_ref') ? lfi_nct_response_ref($r->id, function_exists('lfi_nct_response_ga_of') ? lfi_nct_response_ga_of($r) : '') : $r->id;
                $csv[] = [
                    $ref, $r->submitted_at, $r->adresse, $r->etage,
                    $r->contact_prenom, $r->contact_nom, $r->contact_tel, $r->contact_email,
                    $r->contact_recontact ? 'oui' : 'non',
                    $d['problemes_presence'] ?? '', (int) ($d['problemes_gravite'] ?? 0),
                    implode(' | ', (array) ($d['problemes_types'] ?? [])),
                ];
                /* Photos horodatées → fichiers + liens */
                foreach ((array) ($d['photos'] ?? []) as $ph) {
                    lfi_nct_add_media_photo($add_media, $ph, $folder . '/02_Enquetes_logement/Photos_horodatees/' . $ref);
                }
            }
            $zip->addFromString($folder . '/02_Enquetes_logement/enquetes.csv',
                lfi_nct_backup_csv(['Ref', 'Date', 'Adresse', 'Etage', 'Prenom', 'Nom', 'Tel', 'Email', 'Recontact', 'Problemes?', 'Gravite', 'Types'], $csv));
            $zip->addFromString($folder . '/02_Enquetes_logement/enquetes.json',
                wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        /* ---- Membres actifs ---- */
        $sql = "SELECT prenom, nom, email, tel, statut, adresse, membre_depuis, created_at, updated_at FROM $M WHERE $ga_where";
        if ($has_since) $sql .= $wpdb->prepare(' AND (updated_at > %s OR created_at > %s)', $since, $since);
        $sql .= ' ORDER BY prenom, nom';
        $mrows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        if ($mrows) {
            $csv = [];
            foreach ($mrows as $m) $csv[] = [$m['prenom'], $m['nom'], $m['email'], $m['tel'], $m['statut'], $m['adresse'], $m['membre_depuis']];
            $zip->addFromString($folder . '/01_Membres_actifs/membres.csv',
                lfi_nct_backup_csv(['Prenom', 'Nom', 'Email', 'Tel', 'Statut', 'Adresse', 'Membre depuis'], $csv));
        }

        /* ---- Locataires accompagnés (dossiers) ---- */
        $owner = lfi_nct_backup_ga_owner($slug);
        if ($owner) {
            $sql = $wpdb->prepare("SELECT * FROM $D WHERE owner_user_id = %d", $owner);
            if ($has_since) $sql .= $wpdb->prepare(' AND (updated_at > %s OR created_at > %s)', $since, $since);
            $sql .= ' ORDER BY updated_at DESC';
            $drows = $wpdb->get_results($sql) ?: [];
            if ($drows) {
                $csv = [];
                foreach ($drows as $x) {
                    $name = trim($x->tenant_prenom . ' ' . $x->tenant_nom) ?: ('dossier-' . $x->id);
                    $csv[] = [$x->id, $name, $x->tenant_adresse, $x->tenant_etage, $x->tenant_tel, $x->tenant_email,
                              $x->statut, $x->lrar_travaux_date, $x->schs_date, $x->ars_date, $x->nmh_urgence, $x->updated_at];
                    /* Fiche lisible par locataire */
                    $safe = trim(preg_replace('/[^A-Za-z0-9]+/', '-', (function_exists('remove_accents') ? remove_accents($name) : $name)), '-');
                    $sub = $folder . '/03_Locataires_accompagnes/' . ($safe ?: ('dossier-' . $x->id));
                    $txt = "DOSSIER LOCATAIRE\n=================\n"
                         . "Nom : $name\nAdresse : {$x->tenant_adresse}  (etage {$x->tenant_etage}, appt {$x->tenant_appartement})\n"
                         . "Tel : {$x->tenant_tel}   Email : {$x->tenant_email}\n"
                         . "Statut : {$x->statut}\n\n"
                         . "Constatations :\n" . ($x->constatations ?? '') . "\n\n"
                         . "Demandes :\n" . ($x->demandes ?? '') . "\n\n"
                         . "Certificat medical : {$x->certificat_medecin} (date {$x->certificat_date})\n\n"
                         . "CHRONOMETRE NMH\n---------------\n"
                         . "Mise en demeure (travaux) : {$x->lrar_travaux_date}\n"
                         . "Urgence : {$x->nmh_urgence}\n"
                         . "Relogement LRAR : {$x->lrar_relogement_date}\nSCHS : {$x->schs_date}\nARS : {$x->ars_date}\n";
                    $zip->addFromString($sub . '/Dossier_juridique/dossier.txt', $txt);
                    /* Photos du locataire (attachements taggés) */
                    $atts = get_posts(['post_type' => 'attachment', 'numberposts' => 200, 'post_status' => 'inherit',
                        'meta_key' => '_lfi_tenant_user_id', 'meta_value' => (int) $x->tenant_user_id, 'fields' => 'ids']);
                    foreach ((array) $atts as $aid) $add_media($aid, $sub . '/Photos/photo');
                }
                $zip->addFromString($folder . '/03_Locataires_accompagnes/dossiers.csv',
                    lfi_nct_backup_csv(['ID', 'Nom', 'Adresse', 'Etage', 'Tel', 'Email', 'Statut', 'Mise en demeure', 'SCHS', 'ARS', 'Urgence', 'MAJ'], $csv));
            }
        }
    }

    /* ---- Événements + inscriptions (par GA) ---- */
    $cpts = [];
    if (post_type_exists('ag_evenement')) $cpts[] = 'ag_evenement';
    if (post_type_exists('lfi_evenement')) $cpts[] = 'lfi_evenement';
    if ($cpts) {
        $events = get_posts(['post_type' => $cpts, 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'date', 'order' => 'DESC']);
        $ev_by_ga = [];
        foreach ($events as $p) {
            if ($since && strtotime($p->post_modified) <= strtotime($since)) continue;
            $eslug = (string) get_post_meta($p->ID, '_lfi_evt_ga', true);
            if ($eslug === '' ) $eslug = 'clos-toreau';
            $ev_by_ga[$eslug][] = $p;
        }
        foreach ($ev_by_ga as $eslug => $list) {
            $nom = 'Clos Toreau';
            if ($eslug !== 'clos-toreau' && function_exists('lfi_nct_ga_nom')) $nom = lfi_nct_ga_nom($eslug);
            $folder = '03_GROUPES_D_ACTION/' . lfi_nct_backup_ga_folder($eslug, $nom) . '/04_Evenements';
            $csv = []; $insc = [];
            foreach ($list as $p) {
                $date = get_post_meta($p->ID, '_ag_event_date', true);
                $csv[] = [$p->ID, get_the_title($p), $date, get_post_meta($p->ID, '_ag_event_time', true), get_post_meta($p->ID, '_ag_event_place', true), get_permalink($p)];
                $rs = $wpdb->get_results($wpdb->prepare("SELECT prenom, nom, tel, email, avec_qui, created_at FROM $R_RSVP WHERE event_id = %d ORDER BY created_at", $p->ID)) ?: [];
                foreach ($rs as $x) $insc[] = [get_the_title($p), $x->prenom, $x->nom, $x->tel, $x->email, $x->avec_qui, $x->created_at];
            }
            $zip->addFromString($folder . '/evenements.csv', lfi_nct_backup_csv(['ID', 'Titre', 'Date', 'Heure', 'Lieu', 'Lien'], $csv));
            if ($insc) $zip->addFromString($folder . '/inscriptions.csv', lfi_nct_backup_csv(['Evenement', 'Prenom', 'Nom', 'Tel', 'Email', 'Nombre', 'Le'], $insc));
        }
    }

    /* ---- Volet national (toujours inclus, non incrémental) ---- */
    if (function_exists('lfi_nct_national_stats')) {
        $s = lfi_nct_national_stats();
        $lines = "STATISTIQUES NATIONALES\n=======================\n"
               . "Logements enquetes : {$s['total']}\n"
               . "Avec probleme : {$s['pct_problem']} %\n"
               . "Immeubles touches : {$s['immeubles']}\n"
               . "Gravite moyenne : {$s['grav_avg']}/10\n"
               . "Quartiers / GA : {$s['nb_ga']}\n";
        $zip->addFromString('01_VOLET_NATIONAL_deputes/Statistiques_nationales/statistiques.txt', $lines);
        $raw = (string) get_option('lfi_nct_national_args', '');
        if ($raw === '' && function_exists('lfi_nct_national_args_default')) $raw = lfi_nct_national_args_default();
        if (function_exists('lfi_nct_national_vars')) $raw = strtr($raw, lfi_nct_national_vars($s));
        $zip->addFromString('01_VOLET_NATIONAL_deputes/Elements_de_langage/elements_de_langage.txt', $raw);
    }
    if (function_exists('lfi_nct_national_etudes')) {
        $et = lfi_nct_national_etudes();
        if ($et) {
            $csv = [];
            foreach ($et as $e) {
                $url = !empty($e['att']) ? wp_get_attachment_url((int) $e['att']) : ($e['lien'] ?? '');
                $csv[] = [$e['titre'] ?? '', $e['desc'] ?? '', $url, $e['date'] ?? ''];
                if (!empty($e['att'])) $add_media((int) $e['att'], '01_VOLET_NATIONAL_deputes/Etudes_et_donnees_scientifiques/' . sanitize_file_name($e['titre'] ?? 'etude'));
            }
            $zip->addFromString('01_VOLET_NATIONAL_deputes/Etudes_et_donnees_scientifiques/etudes.csv',
                lfi_nct_backup_csv(['Titre', 'Description', 'Lien', 'Date'], $csv));
        }
    }

    return $media_bytes;
}

/** Ajoute une photo d'enquête (structure ['id'=>,'date'=>]) au zip. */
function lfi_nct_add_media_photo($add_media, $ph, $zip_path) {
    $id = is_array($ph) ? (int) ($ph['id'] ?? 0) : (int) $ph;
    if ($id) $add_media($id, $zip_path);
}

/* ============================================================== *
 *  Téléchargement (admin-post) : construit et envoie le ZIP       *
 * ============================================================== */
add_action('admin_post_lfi_nct_backup', 'lfi_nct_backup_download');
function lfi_nct_backup_download() {
    if (!current_user_can('manage_options')) wp_die('Réservé au super-admin.');
    check_admin_referer('lfi_nct_backup');
    if (!class_exists('ZipArchive')) wp_die('ZipArchive non disponible sur ce serveur — préviens ton développeur.');

    $mode  = isset($_GET['mode']) ? sanitize_key($_GET['mode']) : 'full';
    $since = ($mode === 'incr') ? lfi_nct_backup_checkpoint() : '';
    $now   = current_time('mysql');

    @set_time_limit(0);
    if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');

    $tmp = wp_tempnam('lfi-backup');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) wp_die('Impossible de créer l\'archive.');

    /* Manifeste */
    $manifest = "SAUVEGARDE LFI\n==============\n"
        . 'Date : ' . $now . "\n"
        . 'Type : ' . ($mode === 'incr' ? 'Nouveautes depuis le point fixe' : 'Complete') . "\n"
        . ($since ? 'Point fixe precedent : ' . $since . "\n" : '')
        . "\nDezippe ce contenu DANS ton dossier LFI (sur le Bureau).\n"
        . "Les fichiers .csv s'ouvrent avec Excel/LibreOffice ; les .json contiennent tout le detail.\n";
    $zip->addFromString('_SAUVEGARDE_INFOS.txt', $manifest);

    lfi_nct_backup_fill_zip($zip, $since);
    $zip->close();

    /* Avance le point fixe après un export réussi. */
    update_option('lfi_nct_backup_checkpoint', $now, false);

    $fname = 'LFI-sauvegarde-' . ($mode === 'incr' ? 'nouveautes-' : 'complete-') . wp_date('Y-m-d-Hi') . '.zip';
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/* ============================================================== *
 *  Écran de sauvegarde dans l'app (super-admin)                   *
 * ============================================================== */
function lfi_nct_app_view_sauvegarde() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $cp = lfi_nct_backup_checkpoint();

    if (!empty($_POST['lfi_backup_reset']) && check_admin_referer('lfi_backup_reset')) {
        delete_option('lfi_nct_backup_checkpoint');
        wp_safe_redirect(lfi_nct_app_url('sauvegarde', ['reset' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('💾 Sauvegarde & téléchargement', 'Exporte toutes les données dans ton dossier LFI');
    if (!empty($_GET['reset'])) lfi_nct_app_flash('Point fixe réinitialisé : la prochaine sauvegarde sera complète.');

    echo '<div class="lfi-app-help">Télécharge toutes les données du site, rangées dans la même arborescence que ton dossier <strong>LFI</strong> (cloisonné par groupe d\'action). Dézippe le fichier <strong>dans</strong> ton dossier LFI : les données viennent remplir les bons sous-dossiers.</div>';

    $full = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_backup&mode=full'), 'lfi_nct_backup');
    $incr = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_backup&mode=incr'), 'lfi_nct_backup');

    echo '<div style="display:flex;flex-direction:column;gap:10px;margin:14px 0">';
    echo '<a class="btn-primary big" href="' . esc_url($full) . '">⬇️ Sauvegarde COMPLÈTE (tout)</a>';
    if ($cp !== '') {
        echo '<a class="btn-ghost" href="' . esc_url($incr) . '">🔄 Seulement les NOUVEAUTÉS depuis le ' . esc_html(wp_date('j M Y à H:i', strtotime($cp))) . '</a>';
    } else {
        echo '<div class="lfi-app-help"><small>Aucun « point fixe » encore posé : fais d\'abord une sauvegarde complète. Ensuite, tu pourras ne télécharger que les nouveautés.</small></div>';
    }
    echo '</div>';

    echo '<div class="lfi-app-help" style="background:#f7f7f7">';
    echo '<strong>Comment ça marche (le « point fixe »)</strong><br>';
    echo 'À chaque téléchargement, l\'app retient la date. La fois d\'après, « Nouveautés » ne prend que ce qui a changé depuis — tu n\'as pas à tout retélécharger, tu ajoutes seulement les nouveaux fichiers dans ton dossier LFI.';
    echo '</div>';

    if ($cp !== '') {
        echo '<form method="post" style="margin-top:12px">';
        wp_nonce_field('lfi_backup_reset');
        echo '<input type="hidden" name="lfi_backup_reset" value="1">';
        echo '<button type="submit" class="btn-ghost" style="border-color:#c8102e;color:#c8102e">↺ Réinitialiser le point fixe (prochaine = complète)</button>';
        echo '</form>';
    }

    echo '<div class="lfi-app-help" style="margin-top:12px"><small>Contenu : enquêtes (CSV + JSON + photos horodatées), membres actifs, dossiers locataires (fiche + photos), événements & inscriptions, volet national (stats, éléments de langage, études). Photos incluses jusqu\'à 250 Mo par sauvegarde.</small></div>';

    lfi_nct_app_screen_close();
}
