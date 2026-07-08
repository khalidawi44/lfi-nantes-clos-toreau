<?php
/**
 * AUTO-DÉPLOIEMENT — « je te dis, tu fais, tu déploies, rien à cliquer ».
 * Chaque tâche demandée dans la conversation qui était derrière un bouton est
 * exécutée AUTOMATIQUEMENT au déploiement, une seule fois, de façon idempotente
 * (si c'est déjà fait, on ne refait rien). Aucune action manuelle requise.
 */
if (!defined('ABSPATH')) exit;

add_action('init', 'lfi_nct_auto_deploy', 22);
function lfi_nct_auto_deploy() {

    /* 1) Membre Tristan Rayon dans le GA Clos Toreau (créé si absent). */
    if (get_option('lfi_nct_auto_tristan') !== '1') {
        if (!get_user_by('email', 'tristan.rayon@gmail.com') && function_exists('wp_insert_user')) {
            $login = function_exists('lfi_nct_app_make_username') ? lfi_nct_app_make_username('Tristan', 'Rayon') : 'tristan.rayon';
            $pwd   = function_exists('lfi_nct_app_make_password') ? lfi_nct_app_make_password() : wp_generate_password(16);
            $uid = wp_insert_user([
                'user_login'   => $login, 'user_pass' => $pwd,
                'user_email'   => 'tristan.rayon@gmail.com',
                'first_name'   => 'Tristan', 'last_name' => 'Rayon',
                'display_name' => 'Tristan Rayon',
                'role'         => defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member',
            ]);
            if (!is_wp_error($uid)) {
                update_user_meta($uid, 'lfi_nct_tel', '0695228678');
                $cga = function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : '';
                if ($cga) update_user_meta($uid, 'lfi_nct_ga', $cga);
            }
        }
        update_option('lfi_nct_auto_tristan', '1', false);
    }

    /* ─ PURGE TOTALE des DOSSIERS locataires (tous), demandée pour repartir de
       zéro. CADENAS sur les ENQUÊTES (table responses) + les COMPTES + le lien
       enquête↔locataire (meta lfi_nct_response_id) : jamais touchés. On SAUVEGARDE
       tout (structuré) avant de vider → récupérable (sauf fichiers photos, que
       l'on supprime comme demandé). Idempotent (une seule fois). */
    if (get_option('lfi_nct_dossier_full_wipe_v1') !== '1') {
        global $wpdb;
        $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $tr = $wpdb->prefix . 'lfi_nct_responses';

        /* 1) Protéger les PHOTOS D'ENQUÊTE (référencées dans responses.data). */
        $protected = [];
        foreach ((array) $wpdb->get_col("SELECT data FROM $tr WHERE data IS NOT NULL AND data <> ''") as $d) {
            $j = json_decode((string) $d, true);
            if (is_array($j) && !empty($j['photos']) && is_array($j['photos'])) {
                foreach ($j['photos'] as $ph) { $pid = (int) ($ph['id'] ?? 0); if ($pid) $protected[$pid] = 1; }
            }
        }

        /* 2) Sauvegarde (hors fichiers). */
        $backup = [
            'when'          => current_time('mysql'),
            'meta'          => [],
            'dossiers'      => $wpdb->get_results("SELECT * FROM $td", ARRAY_A) ?: [],
            'victoires'     => get_option('lfi_nct_victoires', null),
            'reussites'     => get_option('lfi_nct_reussites', null),
            'degat_signals' => get_option('lfi_nct_degat_signals', null),
        ];

        /* 3) Locataires = rôle tenant + tout tenant_user_id ayant un dossier. */
        $uids = [];
        $role = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
        foreach (get_users(['role' => $role, 'fields' => ['ID'], 'number' => 5000]) as $tu) $uids[(int) $tu->ID] = 1;
        foreach ((array) $wpdb->get_col("SELECT DISTINCT tenant_user_id FROM $td WHERE tenant_user_id > 0") as $duid) $uids[(int) $duid] = 1;

        /* Métas VIDÉES (contenu du dossier). On NE touche PAS : lfi_nct_response_id
           (lien enquête), tel, ga, casquette, jetons, onboarding, objectif. */
        $meta_keys = ['lfi_nct_suivi_steps', 'lfi_nct_episodes', 'lfi_nct_active_ep', 'lfi_nct_dossier_synthese', 'lfi_nct_dossier_interlocuteurs', 'lfi_nct_admin_notes', 'lfi_nct_home_actions_hidden', 'lfi_nct_chrono'];
        foreach (array_keys($uids) as $uid) {
            $uid = (int) $uid; if (!$uid) continue;
            $bm = [];
            foreach ($meta_keys as $mk) { $v = get_user_meta($uid, $mk, true); if ($v !== '' && $v !== []) $bm[$mk] = $v; }
            if ($bm) $backup['meta'][$uid] = $bm;
            foreach ($meta_keys as $mk) delete_user_meta($uid, $mk);
            /* Pièces du dossier — sauf les photos d'enquête protégées. */
            $atts = get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => [['key' => '_lfi_tenant_user_id', 'value' => $uid]]]);
            foreach ((array) $atts as $aid) { $aid = (int) $aid; if (isset($protected[$aid])) continue; wp_delete_attachment($aid, true); }
        }

        /* 4) Dossiers juridiques : suppression totale (sauvegardés ci-dessus). */
        $wpdb->query("DELETE FROM $td");

        /* 5) Victoires / réussites / signalements → zéro. */
        update_option('lfi_nct_victoires', [], false);
        update_option('lfi_nct_reussites', [], false);
        update_option('lfi_nct_degat_signals', [], false);

        update_option('lfi_nct_dossier_wipe_backup_v1', $backup, false);
        update_option('lfi_nct_dossier_full_wipe_v1', '1', false);
    }

    /* ─ Remise à ZÉRO des compteurs de victoires/réussites (après la purge, il
       ne doit plus y en avoir). Idempotent, séparé du wipe pour être sûr. */
    if (get_option('lfi_nct_victoires_zero_v1') !== '1') {
        update_option('lfi_nct_victoires', [], false);
        update_option('lfi_nct_reussites', [], false);
        update_option('lfi_nct_victoires_zero_v1', '1', false);
    }

    /* ─ Événement « Diffusion de tracts » (jeu. 9 juillet 2026, 17h30–19h00,
       Super U Saint-Jacques, 75 Bd Joliot Curie, 44200 Nantes). À ajouter au
       calendrier du GA REZÉ + au calendrier & VOTE du GA CLOS TOREAU, avec
       carte + point de rendez-vous. Idempotent : garde par option + anti-doublon
       (titre + date + GA). Coordonnées géocodées (75 Bd Joliot-Curie). */
    if (get_option('lfi_nct_seed_evt_tracts_20260709_v1') !== '1' && function_exists('wp_insert_post')) {
        $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
        $evt_title = 'Diffusion de tracts';
        $evt_date  = '2026-07-09';
        $evt_time  = '17h30 – 19h00';
        $evt_place = 'Super U Saint-Jacques';
        $evt_city  = '75 Bd Joliot Curie, 44200 Nantes';
        $evt_lat   = '47.1938031';
        $evt_lng   = '-1.5307383';
        $evt_desc  = 'Diffusion de tracts. Rendez-vous devant le Super U Saint-Jacques, 75 Bd Joliot Curie, 44200 Nantes.';

        $make_evt = function ($ga) use ($cpt, $evt_title, $evt_date, $evt_time, $evt_place, $evt_city, $evt_lat, $evt_lng, $evt_desc) {
            /* Anti-doublon : même titre + même date + même GA → on ne recrée pas. */
            $all = get_posts(['post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']);
            foreach ($all as $pid) {
                if (get_the_title($pid) !== $evt_title) continue;
                if ((string) get_post_meta($pid, '_ag_event_date', true) !== $evt_date) continue;
                if ((string) get_post_meta($pid, '_lfi_evt_ga', true) !== $ga) continue;
                return (int) $pid;
            }
            $pid = wp_insert_post(['post_type' => $cpt, 'post_status' => 'publish', 'post_title' => $evt_title, 'post_content' => $evt_desc], true);
            if (is_wp_error($pid) || !$pid) return 0;
            update_post_meta($pid, '_ag_event_date',  $evt_date);
            update_post_meta($pid, '_ag_event_time',  $evt_time);
            update_post_meta($pid, '_ag_event_place', $evt_place);
            update_post_meta($pid, '_ag_event_city',  $evt_city);
            update_post_meta($pid, '_lfi_evt_lat',    $evt_lat);
            update_post_meta($pid, '_lfi_evt_lng',    $evt_lng);
            update_post_meta($pid, '_lfi_evt_ga',     $ga);
            update_post_meta($pid, '_lfi_evt_internal', 1);
            return (int) $pid;
        };

        $reze_id = $make_evt('reze');          /* Calendrier GA Rezé */
        $ct_id   = $make_evt('clos-toreau');   /* Calendrier GA Clos Toreau */

        /* VOTE Clos Toreau : un créneau de mobilisation (tractage) lié à
           l'événement → surface comme décision à voter pour les membres CT. */
        if ($ct_id) {
            global $wpdb;
            $tm = $wpdb->prefix . 'lfi_nct_mobilisation';
            $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tm WHERE event_id = %d AND date_creneau = %s", $ct_id, $evt_date));
            if (!$exists) {
                $wpdb->insert($tm, [
                    'event_id'     => $ct_id,
                    'theme'        => '',
                    'ga'           => 'clos-toreau',
                    'created_by'   => 0,
                    'date_creneau' => $evt_date,
                    'creneau'      => 'soiree',
                    'type'         => 'tractage',
                    'lieu'         => $evt_place . ', ' . $evt_city,
                    'note'         => $evt_time,
                    'participants' => wp_json_encode([]),
                ]);
            }
        }
        if ($reze_id || $ct_id) update_option('lfi_nct_seed_evt_tracts_20260709_v1', '1', false);
    }

    /* ─ NOUVEL événement « Tractage » (mardi 7 juillet 2026, 18h, Super U
       Saint-Jacques, 75 Bd Joliot Curie, 44200 Nantes) pour le GA CLOS TOREAU,
       + créneau soumis au VOTE. Sans toucher aux autres événements. Idempotent
       (garde par option + anti-doublon titre+date+GA). Coordonnées géocodées. */
    if (get_option('lfi_nct_seed_evt_tractage_20260707_v1') !== '1' && function_exists('wp_insert_post')) {
        $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
        $t_title = 'Tractage — Super U Saint-Jacques';
        $t_date  = '2026-07-07';
        $t_time  = '18h';
        $t_place = 'Super U Saint-Jacques';
        $t_city  = '75 Bd Joliot Curie, 44200 Nantes';
        $t_lat   = '47.1938031';
        $t_lng   = '-1.5307383';
        $t_desc  = 'Tractage. Rendez-vous à 18h devant le Super U Saint-Jacques, 75 Bd Joliot Curie, 44200 Nantes.';
        $t_ga    = 'clos-toreau';

        /* Anti-doublon : même titre + date + GA → on ne recrée pas. */
        $t_id = 0;
        foreach (get_posts(['post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']) as $pid) {
            if (get_the_title($pid) !== $t_title) continue;
            if ((string) get_post_meta($pid, '_ag_event_date', true) !== $t_date) continue;
            if ((string) get_post_meta($pid, '_lfi_evt_ga', true) !== $t_ga) continue;
            $t_id = (int) $pid; break;
        }
        if (!$t_id) {
            $pid = wp_insert_post(['post_type' => $cpt, 'post_status' => 'publish', 'post_title' => $t_title, 'post_content' => $t_desc], true);
            if (!is_wp_error($pid) && $pid) {
                update_post_meta($pid, '_ag_event_date',  $t_date);
                update_post_meta($pid, '_ag_event_time',  $t_time);
                update_post_meta($pid, '_ag_event_place', $t_place);
                update_post_meta($pid, '_ag_event_city',  $t_city);
                update_post_meta($pid, '_lfi_evt_lat',    $t_lat);
                update_post_meta($pid, '_lfi_evt_lng',    $t_lng);
                update_post_meta($pid, '_lfi_evt_ga',     $t_ga);
                update_post_meta($pid, '_lfi_evt_internal', 1);
                $t_id = (int) $pid;
            }
        }

        /* Créneau de mobilisation (tractage) lié → surface comme VOTE Clos Toreau. */
        if ($t_id) {
            global $wpdb;
            $tm = $wpdb->prefix . 'lfi_nct_mobilisation';
            $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tm WHERE event_id = %d AND date_creneau = %s", $t_id, $t_date));
            if (!$exists) {
                $wpdb->insert($tm, [
                    'event_id'     => $t_id,
                    'theme'        => '',
                    'ga'           => $t_ga,
                    'created_by'   => 0,
                    'date_creneau' => $t_date,
                    'creneau'      => 'soiree',
                    'type'         => 'tractage',
                    'lieu'         => $t_place . ', ' . $t_city,
                    'note'         => $t_time,
                    'participants' => wp_json_encode([]),
                ]);
            }
            update_option('lfi_nct_seed_evt_tractage_20260707_v1', '1', false);
        }
    }

    /* ─ CORRECTION lieu : l'événement « Diffusion de tracts » (jeudi 9 juillet)
       est AUSSI au Super U Saint-Jacques (pas le point de RDV par défaut du GA).
       On force le lieu + coordonnées + heure 18h (même heure/endroit que le
       mardi) sur les deux GA + le créneau de vote lié. Idempotent. */
    if (get_option('lfi_nct_fix_evt_tracts_lieu_v1') !== '1' && function_exists('get_posts')) {
        $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
        $fx_title = 'Diffusion de tracts';
        $fx_date  = '2026-07-09';
        $fx_place = 'Super U Saint-Jacques';
        $fx_city  = '75 Bd Joliot Curie, 44200 Nantes';
        $fx_lat   = '47.1938031';
        $fx_lng   = '-1.5307383';
        $fx_time  = '18h';
        $done_any = false;
        foreach (get_posts(['post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']) as $pid) {
            if (get_the_title($pid) !== $fx_title) continue;
            if ((string) get_post_meta($pid, '_ag_event_date', true) !== $fx_date) continue;
            update_post_meta($pid, '_ag_event_place', $fx_place);
            update_post_meta($pid, '_ag_event_city',  $fx_city);
            update_post_meta($pid, '_ag_event_time',  $fx_time);
            update_post_meta($pid, '_lfi_evt_lat',    $fx_lat);
            update_post_meta($pid, '_lfi_evt_lng',    $fx_lng);
            $done_any = true;
            /* Créneau(x) de vote liés à cet événement le 9 juillet. */
            global $wpdb;
            $tm = $wpdb->prefix . 'lfi_nct_mobilisation';
            $wpdb->update($tm,
                ['lieu' => $fx_place . ', ' . $fx_city, 'note' => $fx_time, 'creneau' => 'soiree'],
                ['event_id' => (int) $pid, 'date_creneau' => $fx_date]
            );
        }
        if ($done_any) update_option('lfi_nct_fix_evt_tracts_lieu_v1', '1', false);
    }

    /* ─ RATTACHEMENT : les deux tractages (mardi 7 + jeudi 9) sont POUR la
       Kermesse Républicaine du 14 juillet. On pointe leurs créneaux de vote sur
       l'événement Kermesse (→ regroupés « Tractage pour la Kermesse » dans la
       coordination) et on le mentionne dans la description des événements. */
    if (get_option('lfi_nct_link_tractage_kermesse_v1') !== '1' && function_exists('get_page_by_path')) {
        $cpt = post_type_exists('ag_evenement') ? 'ag_evenement' : (post_type_exists('lfi_evenement') ? 'lfi_evenement' : 'post');
        $kerm = defined('LFI_NCT_KERMESSE_SLUG') ? get_page_by_path(LFI_NCT_KERMESSE_SLUG, OBJECT, $cpt) : null;
        $kerm_id = $kerm ? (int) $kerm->ID : 0;
        if ($kerm_id) {
            /* Retrouver mes deux événements tractage (titre + date). */
            $tract_ids = [];
            foreach (get_posts(['post_type' => $cpt, 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids']) as $pid) {
                $ti = get_the_title($pid); $dt = (string) get_post_meta($pid, '_ag_event_date', true);
                if (($ti === 'Tractage — Super U Saint-Jacques' && $dt === '2026-07-07')
                 || ($ti === 'Diffusion de tracts' && $dt === '2026-07-09')) {
                    $tract_ids[] = (int) $pid;
                    /* Mention « pour la Kermesse » dans la description (une fois). */
                    $p = get_post($pid);
                    if ($p && stripos((string) $p->post_content, 'Kermesse') === false) {
                        wp_update_post(['ID' => $pid, 'post_content' => trim((string) $p->post_content . "\n\nCe tractage prépare la Kermesse Républicaine du 14 juillet (Nantes Sud).")]);
                    }
                }
            }
            /* Repointer les créneaux de vote de ces événements vers la Kermesse. */
            if ($tract_ids) {
                global $wpdb;
                $tm = $wpdb->prefix . 'lfi_nct_mobilisation';
                $in = implode(',', array_map('intval', $tract_ids));
                $wpdb->query($wpdb->prepare("UPDATE $tm SET event_id = %d WHERE event_id IN ($in)", $kerm_id));
            }
            update_option('lfi_nct_link_tractage_kermesse_v1', '1', false);
        }
    }

    /* 1-reset) REMISE À ZÉRO du pipeline d'import des emails, demandée avant une
       relance de pêche propre (« tout remettre à zéro »). Vide la boîte de
       collecte + la mémoire anti-doublon + les emails/pièces IMPORTÉS de tous
       les dossiers. Ne touche PAS aux enquêtes, mandats, chronologies
       reconstruites/saisies à la main. S'exécute UNE seule fois. */
    if (get_option('lfi_nct_emails_reset_v1') !== '1' && function_exists('lfi_nct_emails_full_reset')) {
        try { lfi_nct_emails_full_reset(); } catch (\Throwable $e) {}
        update_option('lfi_nct_emails_reset_v1', '1', false);
    }

    /* 1-fabrice-pieces) Vider TOUTES les pièces du dossier de Fabrice Doucet,
       demandé pour repartir d'un fichier .md décortiqué. Une seule fois. */
    if (get_option('lfi_nct_fabrice_pieces_purge_v1') !== '1' && function_exists('lfi_nct_dossier_purge_pieces')) {
        $fu = get_user_by('email', 'fabrice.doucet44@gmail.com') ?: get_user_by('email', 'nantessudclostoreau@gmail.com');
        if (!$fu) {
            $cands = get_users(['search' => '*doucet*', 'search_columns' => ['display_name', 'user_login', 'user_nicename'], 'number' => 1]);
            $fu = $cands ? $cands[0] : null;
        }
        if ($fu) {
            try { lfi_nct_dossier_purge_pieces((int) $fu->ID); } catch (\Throwable $e) {}
            update_option('lfi_nct_fabrice_pieces_purge_v1', '1', false);
        }
    }

    /* 1-emails-dedup) Nettoyer les DOUBLONS d'emails (file + dossiers) et de
       PHOTOS (mêmes fichiers importés plusieurs fois). Une fois. */
    if (get_option('lfi_nct_emails_photos_dedup_v1') !== '1') {
        if (function_exists('lfi_nct_inbox_dedup_existing')) { try { lfi_nct_inbox_dedup_existing(); } catch (\Throwable $e) {} }
        if (function_exists('lfi_nct_pieces_dedupe')) {
            $role_t = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
            /* tous les détenteurs de pièces (via meta), pas seulement rôle tenant. */
            global $wpdb;
            $uids = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_lfi_tenant_user_id' AND meta_value > 0");
            foreach ((array) $uids as $uu) { try { lfi_nct_pieces_dedupe((int) $uu); } catch (\Throwable $e) {} }
        }
        update_option('lfi_nct_emails_photos_dedup_v1', '1', false);
    }

    /* 1-elus-dedup) MÉNAGE : supprime les comptes ÉLU·ES en DOUBLE (même nom) —
       garde le plus ancien (ex. William Aucant recréé par erreur). Une fois. */
    if (get_option('lfi_nct_partner_dedup_v1') !== '1') {
        $role_pa = defined('LFI_NCT_ROLE_PARTNER') ? LFI_NCT_ROLE_PARTNER : 'lfi_nct_partenaire';
        $parts = get_users(['role' => $role_pa, 'fields' => ['ID', 'display_name'], 'orderby' => 'ID', 'order' => 'ASC', 'number' => 500]);
        $byname = [];
        if (!function_exists('wp_delete_user')) require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($parts as $u) {
            $k = mb_strtolower(trim((string) $u->display_name));
            if ($k === '') continue;
            if (isset($byname[$k])) {
                if (function_exists('wp_delete_user')) { try { wp_delete_user((int) $u->ID, (int) $byname[$k]); } catch (\Throwable $e) {} }
            } else { $byname[$k] = (int) $u->ID; }
        }
        update_option('lfi_nct_partner_dedup_v1', '1', false);
    }

    /* 1-elus) COMPTES des élu·es (municipaux, départementaux, députés, national) à
       partir de l'organigramme : chacun devient un compte PARTENAIRE (espace élu·e
       + prévisualisable « Voir en tant que »). Idempotent. */
    if (get_option('lfi_nct_elus_accounts_v2') !== '1' && function_exists('lfi_nct_carto_people_all')) {
        $role_pa = defined('LFI_NCT_ROLE_PARTNER') ? LFI_NCT_ROLE_PARTNER : 'lfi_nct_partenaire';
        foreach (lfi_nct_carto_people_all() as $p) {
            $nom = trim((string) ($p['nom'] ?? '')); if ($nom === '') continue;
            $niveau = mb_strtolower((string) ($p['niveau'] ?? ''));
            $fonction = mb_strtolower((string) ($p['fonction'] ?? ''));
            $is_elu = in_array($niveau, ['national', 'departemental', 'départemental', 'municipal'], true)
                || preg_match('/(.lu|depute|d.put.|conseil|maire|s.nateur|adjoint|coordinateur|parrainage)/u', $fonction);
            if (!$is_elu) continue;
            $key   = sanitize_title($nom);
            $email = trim((string) ($p['email'] ?? ''));
            $uid = 0;
            $ex = get_users(['meta_key' => 'lfi_nct_carto_person_key', 'meta_value' => $key, 'number' => 1, 'fields' => ['ID']]);
            if ($ex) $uid = (int) (is_object($ex[0]) ? $ex[0]->ID : $ex[0]);
            if (!$uid && $email !== '' && is_email($email)) { $u = get_user_by('email', $email); if ($u) $uid = (int) $u->ID; }
            if (!$uid) {
                $parts = explode(' ', $nom, 2); $prenom = $parts[0] ?? ''; $nomf = $parts[1] ?? '';
                $login = function_exists('lfi_nct_app_make_username') ? lfi_nct_app_make_username($prenom, $nomf ?: 'elu') : sanitize_user($key, true);
                if ($login === '' || username_exists($login)) $login = 'elu-' . $key . '-' . wp_rand(100, 999);
                $args = ['user_login' => $login, 'user_pass' => wp_generate_password(16), 'first_name' => $prenom, 'last_name' => $nomf, 'display_name' => $nom, 'role' => $role_pa];
                if ($email !== '' && is_email($email) && !email_exists($email)) $args['user_email'] = $email;
                $newuid = wp_insert_user($args);
                if (!is_wp_error($newuid)) { $uid = (int) $newuid; update_user_meta($uid, 'lfi_nct_carto_person_key', $key); }
            } else {
                if (!get_user_meta($uid, 'lfi_nct_carto_person_key', true)) update_user_meta($uid, 'lfi_nct_carto_person_key', $key);
                if (function_exists('lfi_nct_user_role_partner') && !lfi_nct_user_role_partner($uid)) { $wu = new WP_User($uid); $wu->add_role($role_pa); }
            }
            if ($uid) {
                if ($niveau === 'national') update_user_meta($uid, 'lfi_nct_demo_national', 1);
                if (($p['fonction'] ?? '') !== '') update_user_meta($uid, 'lfi_nct_elu_fonction', (string) $p['fonction']);
            }
        }
        update_option('lfi_nct_elus_accounts_v2', '1', false);
    }

    /* 1-national-dedup) Retirer les DOUBLONS de l'organigramme national (une fois). */
    if (get_option('lfi_nct_carto_people_dedup_v1') !== '1' && function_exists('lfi_nct_carto_people_dedupe')) {
        try { lfi_nct_carto_people_dedupe(); } catch (\Throwable $e) {}
        update_option('lfi_nct_carto_people_dedup_v1', '1', false);
    }

    /* 1-fabrice-wipe) Vider TOTALEMENT le dossier de Fabrice Doucet (pièces +
       chronologie + emails + notes), demandé pour repartir de zéro. Une fois. */
    if (get_option('lfi_nct_fabrice_wipe_v1') !== '1' && function_exists('lfi_nct_dossier_wipe_all')) {
        $fu = get_user_by('email', 'fabrice.doucet44@gmail.com') ?: get_user_by('email', 'nantessudclostoreau@gmail.com');
        if (!$fu) {
            $cands = get_users(['search' => '*doucet*', 'search_columns' => ['display_name', 'user_login', 'user_nicename'], 'number' => 1]);
            $fu = $cands ? $cands[0] : null;
        }
        if ($fu) {
            try { lfi_nct_dossier_wipe_all((int) $fu->ID); } catch (\Throwable $e) {}
            update_option('lfi_nct_fabrice_wipe_v1', '1', false);
        }
    }

    /* 1-logos) Nettoyage des LOGOS/ICÔNES de signature importés par erreur comme
       pièces (une fois). */
    if (get_option('lfi_nct_cleanup_logos_v1') !== '1' && function_exists('lfi_nct_cleanup_email_logos')) {
        try { lfi_nct_cleanup_email_logos(); } catch (\Throwable $e) {}
        update_option('lfi_nct_cleanup_logos_v1', '1', false);
    }

    /* 1bis) Sommet NATIONAL de l'organigramme (Mélenchon + Bompard) — ajoutés
       s'ils manquent, en haut de la pyramide. */
    if (get_option('lfi_nct_auto_national_v2') !== '1' && function_exists('lfi_nct_carto_people_all') && function_exists('lfi_nct_carto_people_save')) {
        $people = lfi_nct_carto_people_all();
        $has = [];
        foreach ($people as $p) $has[mb_strtolower(trim((string) ($p['nom'] ?? '')))] = 1;
        $nat = [
            ['Jean-Luc Mélenchon', 'Fondateur · La France Insoumise', '', 'national'],
            ['Manuel Bompard', 'Coordinateur national de La France Insoumise', 'manuel.bompard@assemblee-nationale.fr', 'national'],
            ['William Aucant', 'Référent Gestion des relations unitaires / Parrainage 2027', '', 'departemental'],
        ];
        $changed = false;
        foreach ($nat as $n) {
            if (isset($has[mb_strtolower($n[0])])) continue;
            $people[] = [
                'id' => (function_exists('lfi_nct_carto_next_id') ? lfi_nct_carto_next_id($people) : count($people) + 1),
                'nom' => $n[0], 'fonction' => $n[1], 'email' => $n[2], 'ap_url' => '',
                'niveau' => $n[3], 'ga_nom' => '', 'ga_membres' => 0,
            ];
            $changed = true;
        }
        if ($changed) lfi_nct_carto_people_save($people);
        update_option('lfi_nct_auto_national_v2', '1', false);
    }

    /* 1ter) Dossier juridique GARANTI pour TOUS les locataires (sinon certains
       n'apparaissent nulle part de façon cohérente et leurs PDF sont vides). */
    if (get_option('lfi_nct_auto_dossiers') !== '1' && function_exists('lfi_nct_dossier_ensure_for_tenant')) {
        $role = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
        $tenants = get_users(['role' => $role, 'number' => 800, 'fields' => ['ID']]);
        foreach ($tenants as $t) { try { lfi_nct_dossier_ensure_for_tenant((int) $t->ID); } catch (\Throwable $e) {} }
        update_option('lfi_nct_auto_dossiers', '1', false);
    }

    /* 2) Reconstruction complète du dossier de Fabrice (enquête #6, dossier
       juridique, mandat, chronologie). On ne pose le drapeau QUE si Fabrice
       existe — sinon on réessaiera au prochain chargement. */
    if (get_option('lfi_nct_auto_fabrice_v2') !== '1' && function_exists('lfi_nct_fabrice_reconstruct')) {
        $fu = get_user_by('email', 'fabrice.doucet44@gmail.com') ?: get_user_by('email', 'nantessudclostoreau@gmail.com');
        if (!$fu) {
            /* Repli : un compte dont le nom affiché contient « Doucet ». */
            $cands = get_users(['search' => '*doucet*', 'search_columns' => ['display_name', 'user_login', 'user_nicename'], 'number' => 1]);
            $fu = $cands ? $cands[0] : null;
        }
        if ($fu) {
            try { lfi_nct_fabrice_reconstruct($fu); } catch (\Throwable $e) {}
            update_option('lfi_nct_auto_fabrice_v2', '1', false);
        }
    }

    /* 3) TOUS LES ADMINS DE GA : à partir de la carto, on enregistre chaque GA
       dans le registre + on crée le compte de ses 2 admins (si un email valide)
       et on les rattache comme binôme. Ils pourront se connecter et gérer LEUR
       GA (page + événements cloisonnés). Idempotent. */
    if (get_option('lfi_nct_auto_ga_admins_v3') !== '1' && function_exists('lfi_nct_carto_all')) {
        $role   = defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member';
        $custom = get_option('lfi_nct_ga_custom', []);  if (!is_array($custom)) $custom = [];
        $pairs  = get_option('lfi_nct_ga_admins', []);  if (!is_array($pairs))  $pairs  = [];
        $pivots = get_option('lfi_nct_ga_pivots', []);  if (!is_array($pivots)) $pivots = [];
        $known  = []; foreach ($custom as $g) { if (!empty($g['slug'])) $known[$g['slug']] = 1; }
        foreach (lfi_nct_carto_all() as $e) {
            $nom = trim((string) ($e['nom'] ?? '')); if ($nom === '') continue;
            if (stripos($nom, 'clos toreau') !== false) continue; /* déjà géré */
            $slug = sanitize_title($nom); if ($slug === '') continue;
            if (!isset($known[$slug])) {
                $custom[] = ['slug' => $slug, 'nom' => $nom, 'secteur' => (string) ($e['commune'] ?? ''), 'custom' => 1];
                $known[$slug] = 1;
            }
            $adm = [];
            foreach ([['contact', 'email'], ['contact2', 'email2']] as $c) {
                $name  = trim((string) ($e[$c[0]] ?? ''));
                $email = trim((string) ($e[$c[1]] ?? ''));
                if ($name === '' && $email === '') continue; /* rien du tout → on saute */
                /* PAS besoin d'email pour être admin (ex. Evan à Rezé). Clé
                   d'idempotence par nom quand il n'y a pas d'email. */
                $akey = $slug . '|' . sanitize_title($name !== '' ? $name : $email);
                $has_mail = ($email !== '' && is_email($email));
                $uid = 0;
                $ex = get_users(['meta_key' => 'lfi_nct_carto_admin_key', 'meta_value' => $akey, 'number' => 1, 'fields' => ['ID']]);
                if ($ex) $uid = (int) (is_object($ex[0]) ? $ex[0]->ID : $ex[0]);
                if (!$uid && $has_mail) { $u = get_user_by('email', $email); if ($u) $uid = (int) $u->ID; }
                if (!$uid) {
                    $p = explode(' ', $name, 2); $prenom = ($p[0] ?? '') ?: ($name ?: 'Admin'); $nomf = $p[1] ?? '';
                    $base  = $has_mail ? current(explode('@', $email)) : sanitize_title($name);
                    $login = function_exists('lfi_nct_app_make_username') ? lfi_nct_app_make_username($prenom, $nomf ?: $slug) : '';
                    if ($login === '' || username_exists($login)) $login = sanitize_user(($base ?: 'ga') . '-' . wp_rand(100, 999), true);
                    if ($login === '' || username_exists($login)) $login = 'ga-' . $slug . '-' . wp_rand(1000, 9999);
                    $pwd = function_exists('lfi_nct_app_make_password') ? lfi_nct_app_make_password() : wp_generate_password(16);
                    $args = ['user_login' => $login, 'user_pass' => $pwd, 'first_name' => $prenom, 'last_name' => $nomf, 'display_name' => ($name ?: $login), 'role' => $role];
                    if ($has_mail) $args['user_email'] = $email; /* sinon email vide = OK sous WP */
                    $newuid = wp_insert_user($args);
                    if (!is_wp_error($newuid)) { $uid = (int) $newuid; update_user_meta($uid, 'lfi_nct_carto_admin_key', $akey); }
                } elseif (!get_user_meta($uid, 'lfi_nct_carto_admin_key', true)) {
                    update_user_meta($uid, 'lfi_nct_carto_admin_key', $akey);
                }
                if ($uid) {
                    if ((string) get_user_meta($uid, 'lfi_nct_ga', true) === '') update_user_meta($uid, 'lfi_nct_ga', $slug);
                    update_user_meta($uid, 'lfi_nct_ga_role', 'admin');
                    if ($name === '' && $email === '') { /* garde-fou */ } else $adm[] = $uid;
                }
            }
            if ($adm) {
                $pairs[$slug] = ['f' => (int) ($adm[0] ?? 0), 'h' => (int) ($adm[1] ?? 0)];
                /* Pivot = 1er admin : ancre l'espace du GA (sinon « administrateur
                   à configurer » dans le sélecteur « 👁 Espace affiché »). */
                if (empty($pivots[$slug])) $pivots[$slug] = (int) $adm[0];
            }
        }
        update_option('lfi_nct_ga_custom', array_values($custom), false);
        update_option('lfi_nct_ga_admins', $pairs, false);
        update_option('lfi_nct_ga_pivots', $pivots, false);
        update_option('lfi_nct_auto_ga_admins_v3', '1', false);
    }
}
