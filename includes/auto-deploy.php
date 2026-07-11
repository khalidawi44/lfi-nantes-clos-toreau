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

    /* ─ RASSEMBLEMENT AUTOMATIQUE des enquêtes sous Clos Toreau : toutes les
       enquêtes du quartier (rue d'Hendaye, Biarritz, Saint-Jean-de-Luz…) sont
       ramenées au GA maison, quel que soit le slug erroné reçu à la saisie.
       Automatique, sans bouton. Idempotent (une fois). */
    if (get_option('lfi_nct_enq_consolidate_clos_v1') !== '1') {
        global $wpdb;
        $rtable = $wpdb->prefix . 'lfi_nct_responses';
        $wpdb->query("UPDATE $rtable SET ga = 'clos-toreau' WHERE deleted_at IS NULL AND ga IS NOT NULL AND ga <> '' AND ga <> 'clos-toreau'");
        update_option('lfi_nct_enq_consolidate_clos_v1', '1', false);
    }

    /* ─ RÉPARATION QR : les enquêtes saisies par des enquêteur·rices inscrit·es
       via le QR avaient reçu comme GA le HASH md5 du nom « Clos Toreau » au lieu
       du slug canonique 'clos-toreau' → elles étaient enregistrées mais INVISIBLES
       dans la vue Clos Toreau. On re-tague ces enquêtes + les comptes concernés
       vers 'clos-toreau'. Idempotent. */
    if (get_option('lfi_nct_qr_ga_repair_v2') !== '1') {
        global $wpdb;
        $hashes = [];
        if (function_exists('lfi_nct_public_gas_list') && function_exists('lfi_nct_ga_slug')) {
            foreach (lfi_nct_public_gas_list() as $g) {
                if (stripos((string) ($g['nom'] ?? ''), 'clos toreau') !== false) $hashes[] = lfi_nct_ga_slug($g['nom']);
            }
        }
        $hashes = array_values(array_unique(array_filter($hashes)));
        if ($hashes) {
            $rtable = $wpdb->prefix . 'lfi_nct_responses';
            foreach ($hashes as $h) {
                /* Enquêtes → Clos Toreau. */
                $wpdb->update($rtable, ['ga' => 'clos-toreau'], ['ga' => $h]);
                /* Comptes (enquêteur·rices) dont le GA = ce hash → 'clos-toreau'. */
                $us = get_users(['meta_key' => 'lfi_nct_ga', 'meta_value' => $h, 'fields' => ['ID'], 'number' => 5000]);
                foreach ($us as $uu) update_user_meta((int) $uu->ID, 'lfi_nct_ga', 'clos-toreau');
            }
        }
        update_option('lfi_nct_qr_ga_repair_v2', '1', false);
    }

    /* ─ RÉPARATION des LIENS de dossiers juridiques corrompus : un dossier dont
       le compte lié (tenant_user_id) CONTREDIT le nom du dossier (ex. dossier de
       « Marie Croyère » pointant vers le compte de « Fabrice Doucet ») est
       réaligné : on relie au bon compte s'il existe (nom identique), sinon on
       DÉLIE (tenant_user_id = 0) — le dossier garde son nom, mais ne s'affiche
       plus sous la mauvaise personne. Sauvegarde avant modif. Idempotent. */
    if (get_option('lfi_nct_dossier_link_repair_v1') !== '1'
        && function_exists('lfi_nct_dossier_owner_id') && function_exists('lfi_nct_names_agree')) {
        global $wpdb;
        $owner = (int) lfi_nct_dossier_owner_id();
        $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, tenant_user_id, tenant_prenom, tenant_nom FROM $t WHERE owner_user_id = %d", $owner)) ?: [];
        /* Index des comptes locataires par jeu de tokens de nom (pour relier). */
        $tenants = (defined('LFI_NCT_ROLE_TENANT')) ? get_users(['role' => LFI_NCT_ROLE_TENANT, 'number' => 1000, 'fields' => ['ID', 'display_name', 'first_name', 'last_name']]) : [];
        $by_name = [];
        foreach ($tenants as $tu) {
            $full = trim((string) $tu->last_name . ' ' . (string) $tu->first_name);
            if ($full === '') $full = (string) $tu->display_name;
            $toks = lfi_nct_name_norm_tokens($full); sort($toks);
            $key = implode(' ', $toks);
            if ($key === '') continue;
            $by_name[$key][] = (int) $tu->ID;
        }
        $backup = [];
        foreach ($rows as $r) {
            $link = (int) $r->tenant_user_id;
            if (!$link) continue;
            $lu = get_userdata($link);
            $row_name = trim((string) $r->tenant_prenom . ' ' . (string) $r->tenant_nom);
            if ($row_name === '' || !$lu) continue;
            $link_name = trim((string) $lu->last_name . ' ' . (string) $lu->first_name);
            if ($link_name === '') $link_name = (string) $lu->display_name;
            if (lfi_nct_names_agree($link_name, $row_name)) continue; /* lien cohérent → on ne touche pas */
            /* Contradiction : on tente de relier au compte du BON nom (unique). */
            $toks = lfi_nct_name_norm_tokens($row_name); sort($toks);
            $key = implode(' ', $toks);
            $new = (isset($by_name[$key]) && count($by_name[$key]) === 1) ? (int) $by_name[$key][0] : 0;
            $backup[] = ['id' => (int) $r->id, 'old' => $link, 'new' => $new, 'row_name' => $row_name, 'link_name' => $link_name];
            $wpdb->update($t, ['tenant_user_id' => $new ?: null], ['id' => (int) $r->id]);
        }
        if ($backup) update_option('lfi_nct_dossier_link_repair_backup_v1', $backup, false);
        update_option('lfi_nct_dossier_link_repair_v1', '1', false);
    }

    /* ─ ANNUAIRE PRESSE : catégorisation + contacts vérifiés (validés par
       l'utilisateur). On (1) range les contacts existants par catégorie, on
       (2) corrige « Marie Vitou » → « Marie Vitoux », puis on (3) ajoute les
       contacts officiels VÉRIFIÉS (sources publiques). Anti-doublon par nom.
       RÈGLE : uniquement des comptes officiels vérifiés ; les handles non
       confirmés restent vides (« à compléter »). Idempotent. */
    if (get_option('lfi_nct_presse_contacts_cat_v1') !== '1'
        && function_exists('lfi_nct_presse_contacts_get') && function_exists('lfi_nct_presse_contacts_save')) {
        $list = lfi_nct_presse_contacts_get();
        /* (1)(2) migration : cat par défaut + responsables + correction de nom. */
        foreach ($list as $i => $c) {
            $nom = mb_strtolower((string) ($c['nom'] ?? ''));
            if (empty($c['cat'])) {
                if (strpos($nom, 'vitou') !== false || strpos($nom, 'bassal') !== false) $list[$i]['cat'] = 'responsable';
                else $list[$i]['cat'] = 'media';
            }
            if (strpos($nom, 'marie vitou') !== false && stripos((string) $c['nom'], 'vitoux') === false) {
                $list[$i]['nom'] = 'Marie Vitoux';
                if (empty($c['fonction']) || stripos((string) $c['fonction'], 'à vérifier') !== false) $list[$i]['fonction'] = 'Adjointe quartier Nantes Sud (à vérifier)';
            }
            if (strpos($nom, 'bassal') !== false && (empty($c['fonction']) || stripos((string) $c['fonction'], 'à vérifier') !== false)) {
                $list[$i]['fonction'] = 'Présidente du CA de NMH (à vérifier)';
            }
        }
        /* (3) ajouts vérifiés (avec sources) — que si le nom n'existe pas déjà. */
        $has = function ($list, $needle) {
            foreach ($list as $c) { if (stripos((string) ($c['nom'] ?? ''), $needle) !== false) return true; }
            return false;
        };
        $add = [];
        $mk = function ($nom, $fonction, $cat, $fields) {
            return array_merge(['id' => (abs(crc32($nom . 'seedv1')) % 900000000) + 100000000,
                'nom' => $nom, 'fonction' => $fonction, 'cat' => $cat, 'site' => '', 'email' => '',
                'twitter' => '', 'instagram' => '', 'facebook' => '', 'tel' => '', 'note' => ''], $fields);
        };
        if (!$has($list, 'johanna rolland')) $add[] = $mk('Johanna Rolland', 'Maire de Nantes · prés. Nantes Métropole', 'responsable', ['instagram' => 'https://www.instagram.com/johanna_rolland/', 'twitter' => 'https://twitter.com/Johanna_Rolland', 'facebook' => 'https://www.facebook.com/p/Johanna-Rolland-100044598602061/', 'note' => 'Comptes officiels vérifiés']);
        if (!$has($list, 'mairie de nantes')) $add[] = $mk('Mairie de Nantes', 'Contact institutionnel', 'responsable', ['email' => 'contact@mairie-nantes.fr', 'tel' => '02 40 41 90 00', 'site' => 'https://metropole.nantes.fr', 'note' => '29 rue de Strasbourg, 44000 Nantes']);
        if (!$has($list, 'kerbrat')) $add[] = $mk('Andy Kerbrat', 'Député LFI Loire-Atlantique (2e circo)', 'soutien', ['twitter' => 'https://x.com/andykerbrat', 'instagram' => 'https://www.instagram.com/andy.kerbrat/', 'facebook' => 'https://www.facebook.com/AndyKerbrat2024/', 'note' => 'A liké le communiqué — soutien']);
        if (!$has($list, 'aucant')) $add[] = $mk('William Aucant', 'Cons. régional · tête de liste LFI Nantes 2026', 'soutien', ['site' => 'https://www.paysdelaloire.fr/mon-conseil-regional/linstitution/les-elus/william-aucant', 'note' => 'A liké le communiqué — handles perso à confirmer']);
        if (!$has($list, 'rezé insoumise') && !$has($list, 'reze insoumise')) $add[] = $mk('Rezé insoumise', 'GA LFI Rezé (a partagé)', 'soutien', ['twitter' => 'https://x.com/RezeInsoumise', 'site' => 'https://linktr.ee/rezeinsoumise', 'note' => 'Linktree : Insta / FB / TikTok']);
        if (!$has($list, 'nantes insoumise') && !$has($list, 'france insoumise nantes')) $add[] = $mk('Nantes insoumise (LFI 44)', 'Groupes LFI de Nantes (a partagé)', 'soutien', ['site' => 'https://www.nantesinsoumise.fr/', 'note' => 'Réseaux à compléter']);
        if (!$has($list, 'saint-sébastien') && !$has($list, 'saint-sebastien')) $add[] = $mk('LFI Saint-Sébastien', 'GA LFI Saint-Sébastien (a partagé)', 'soutien', ['note' => 'Réseaux à compléter']);
        if ($add) $list = array_merge($list, $add);
        lfi_nct_presse_contacts_save($list);
        update_option('lfi_nct_presse_contacts_cat_v1', '1', false);
    }

    /* ─ COMMUNIQUÉ DE PRESSE officiel (Clos Toreau) — importé en BROUILLON,
       transcrit fidèlement depuis la photo transmise (page 1). La suite de la
       liste des demandes + la signature/contact n'étaient PAS lisibles sur la
       photo → marquées « à compléter » (règle : ne rien inventer). Idempotent. */
    if (get_option('lfi_nct_presse_seed_cp1_v1') !== '1'
        && defined('LFI_NCT_PRESSE_CPT') && post_type_exists(LFI_NCT_PRESSE_CPT)
        && function_exists('wp_insert_post')) {
        $titre = "Communiqué de presse : au Clos Toreau des locataires dénoncent des logements indignes, La France insoumise interpelle Nantes Métropole Habitat";
        /* Anti-doublon : on ne recrée pas s'il existe déjà un communiqué avec ce titre. */
        $exists = get_posts(['post_type' => LFI_NCT_PRESSE_CPT, 'post_status' => 'any', 'numberposts' => 1, 'title' => $titre, 'fields' => 'ids']);
        if (empty($exists)) {
            $chapo = "Au Clos Toreau (Nantes Sud), les premières réponses à l'enquête menée par les militant·es insoumis·es révèlent des logements indignes : moisissures, rats, coupures d'eau chaude, punaises de lit. La France insoumise interpelle Nantes Métropole Habitat, la Ville et la Métropole.";
            $corps  = "Moisissures, peintures qui se détachent du plafond, punaises de lit (pendant plusieurs années parfois), blattes, coupures d'eau chaude récurrentes, pannes d'ascenseur (jusqu'à une semaine), odeurs d'égout qui remontent jusqu'au 10ème étage, rats (des pièges à rats sont disposés tous les deux mètres au pied des immeubles)... Les premières réponses à l'enquête menée par les militant·es insoumis·es du Clos Toreau sont édifiantes. Elles témoignent de l'abandon de l'État qui ne donne pas les moyens d'investir dans la rénovation de ces bâtiments du début des années 1970. Les canalisations ne sont pas remplacées et le \"bricolage\" n'apporte aucune solution pérenne. Les murs en béton ne sont isolés ni à l'intérieur ni à l'extérieur : c'est l'humidité l'hiver et tout au long de l'année, et la chaleur l'été. Une mère s'inquiète pour sa fille de 3 ans qui souffre de problèmes respiratoires en raison de la présence de moisissures. Certificat médical à l'appui. Nous dénonçons la politique du logement désastreuse qui se poursuit encore : le ministre du logement Vincent Jeanbrun vient de repousser la rénovation énergétique de cinq ans et compte la financer par des hausses de loyer ! L'État étrangle financièrement le logement social. Mais Nantes Métropole Habitat, la Ville et la Métropole ne peuvent pas se réfugier derrière cette seule responsabilité nationale quand des familles vivent aujourd'hui avec des moisissures, des rats et des coupures d'eau chaude.\n\n";
            $corps .= "Les locataires du Clos Toreau s'acquittent de leur loyer et paient des charges : ils sont floués. Les alertes à Nantes Métropole Habitat restent vaines. On leur demande de se débrouiller — quand on ne les rend pas responsables de la situation ! Les habitant·es du Clos Toreau sont traité·es comme des citoyen·nes de seconde zone.\n\n";
            $corps .= "Nous demandons donc :\n";
            $corps .= "• un diagnostic sanitaire et technique complet des immeubles concernés ;\n";
            $corps .= "• un calendrier public de travaux, précis et opposable ;\n";
            $corps .= "\n[⚠️ À COMPLÉTER : la suite de la liste des demandes et la signature / contact presse n'étaient pas lisibles sur la photo transmise. Colle le texte complet ici avant de publier — rien n'a été inventé.]";
            $pid = wp_insert_post([
                'post_type'    => LFI_NCT_PRESSE_CPT,
                'post_title'   => $titre,
                'post_excerpt' => $chapo,
                'post_content' => $corps,
                'post_status'  => 'draft', /* BROUILLON : à compléter + relire avant publication */
            ]);
            if ($pid && !is_wp_error($pid)) {
                update_post_meta($pid, '_lfi_presse_emetteur', "Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau");
            }
        }
        update_option('lfi_nct_presse_seed_cp1_v1', '1', false);
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

    /* ─ Remise à ZÉRO des victoires/réussites + on COUPE la ré-injection des
       réussites intégrées (sinon le compteur revenait tout seul à 2). */
    if (get_option('lfi_nct_victoires_zero_v2') !== '1') {
        update_option('lfi_nct_reussites_seed_off', '1', false); /* stop le seed */
        update_option('lfi_nct_victoires', [], false);
        update_option('lfi_nct_reussites', [], false);
        update_option('lfi_nct_victoires_zero_v2', '1', false);
    }

    /* ─ RATTRAPAGE enquête → dossier : toute enquête « je veux être recontacté·e »
       doit avoir un COMPTE + un DOSSIER (amiable + juridique) LIÉS. Après la
       purge, on les recrée depuis les enquêtes (idempotent, anti-doublon). */
    if (get_option('lfi_nct_recontact_backfill_v1') !== '1'
        && function_exists('lfi_nct_ep_ensure_tenant') && function_exists('lfi_nct_ep_create_dossier')) {
        global $wpdb;
        $tr = $wpdb->prefix . 'lfi_nct_responses';
        $tdl = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $rows = $wpdb->get_results("SELECT * FROM $tr WHERE deleted_at IS NULL AND contact_recontact = 1 AND (contact_prenom <> '' OR contact_nom <> '') LIMIT 1000") ?: [];
        foreach ($rows as $row) {
            $ex = get_users(['meta_key' => 'lfi_nct_response_id', 'meta_value' => (int) $row->id, 'number' => 1, 'fields' => ['ID']]);
            $tuid = !empty($ex) ? (int) (is_object($ex[0]) ? $ex[0]->ID : $ex[0]) : 0;
            if (!$tuid) $tuid = (int) lfi_nct_ep_ensure_tenant($row);
            if (!$tuid) continue;
            if ((string) get_user_meta($tuid, 'lfi_nct_ga', true) === '' && trim((string) $row->ga) !== '') update_user_meta($tuid, 'lfi_nct_ga', (string) $row->ga);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $tdl WHERE tenant_user_id = %d LIMIT 1", $tuid));
            if (!$exists) {
                $souhaits = ''; $d = json_decode((string) $row->data, true);
                if (is_array($d)) {
                    $o = !empty($d['objectifs']) && is_array($d['objectifs']) ? $d['objectifs'] : (!empty($d['objectif']) ? [(string) $d['objectif']] : []);
                    if ($o) $souhaits = 'Objectif : ' . implode(', ', $o);
                }
                $owner = function_exists('lfi_nct_ga_owner_for_slug') ? lfi_nct_ga_owner_for_slug((string) $row->ga) : 0;
                lfi_nct_ep_create_dossier($row, $tuid, '', $souhaits, $owner);
            }
        }
        update_option('lfi_nct_recontact_backfill_v1', '1', false);
    }

    /* ─ Fabrice Doucet : 3 dossiers d'incident « punaises de lit » (récidives =
       MÊME affaire → même dossier juridique, préjudice cumulé). Ouverts (c'est
       LUI qui clôt chaque urgence → coupe). Dates MODIFIABLES (il précisera).
       On ne clôt rien et on n'invente aucune date (seule l'année 2020 est
       connue pour le 1er). Idempotent. */
    if (get_option('lfi_nct_fabrice_3ep_punaises_v1') !== '1' && function_exists('lfi_nct_episode_seed_steps')) {
        $fuid = 0;
        foreach (get_users(['search' => '*Doucet*', 'search_columns' => ['display_name', 'user_login', 'user_nicename'], 'number' => 20, 'fields' => ['ID', 'display_name']]) as $usr) {
            $dn = mb_strtolower((string) $usr->display_name);
            if (strpos($dn, 'doucet') !== false && strpos($dn, 'fabrice') !== false) { $fuid = (int) $usr->ID; break; }
        }
        if ($fuid) {
            $grp = (int) (round(microtime(true) * 1000) % 1000000000);
            $mk = function ($titre, $date) use ($grp) {
                static $i = 0; $i++;
                return [
                    'id' => (int) (round(microtime(true) * 1000) % 1000000000) + $i,
                    'titre' => $titre, 'type' => 'punaises', 'piece' => '',
                    'ouvert' => $date, 'clos_urgence' => false, 'clos_date' => '',
                    'groupe' => $grp,
                    'steps' => lfi_nct_episode_seed_steps(), 'prejudice' => [],
                ];
            };
            $eps = [
                $mk('Infestation punaises de lit — 2020', '2020-01-01'),
                $mk('Infestation punaises de lit — 2ᵉ (date à préciser)', ''),
                $mk('Infestation punaises de lit — 3ᵉ / dernière (date à préciser)', ''),
            ];
            update_user_meta($fuid, 'lfi_nct_episodes', $eps);
            update_user_meta($fuid, 'lfi_nct_active_ep', (int) $eps[0]['id']);
            update_user_meta($fuid, 'lfi_nct_suivi_steps', array_values($eps[0]['steps']));
            update_option('lfi_nct_fabrice_3ep_punaises_v1', '1', false);
        }
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
