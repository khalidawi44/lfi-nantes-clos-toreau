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
    if (get_option('lfi_nct_auto_ga_admins_v2') !== '1' && function_exists('lfi_nct_carto_all')) {
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
                if ($email === '' || !is_email($email)) continue; /* pas d'email valide → on ne crée pas (il ne pourrait pas se connecter) */
                $u = get_user_by('email', $email);
                $uid = $u ? (int) $u->ID : 0;
                if (!$uid) {
                    $p = explode(' ', $name, 2); $prenom = $p[0] ?? $name; $nomf = $p[1] ?? '';
                    $login = function_exists('lfi_nct_app_make_username') ? lfi_nct_app_make_username($prenom, $nomf) : sanitize_user(current(explode('@', $email)), true);
                    $pwd   = function_exists('lfi_nct_app_make_password') ? lfi_nct_app_make_password() : wp_generate_password(16);
                    $newuid = wp_insert_user(['user_login' => $login ?: sanitize_user(current(explode('@', $email)) . '-' . wp_rand(100, 999)), 'user_pass' => $pwd, 'user_email' => $email, 'first_name' => $prenom, 'last_name' => $nomf, 'display_name' => $name ?: $login, 'role' => $role]);
                    if (!is_wp_error($newuid)) $uid = (int) $newuid;
                }
                if ($uid) {
                    if ((string) get_user_meta($uid, 'lfi_nct_ga', true) === '') update_user_meta($uid, 'lfi_nct_ga', $slug);
                    update_user_meta($uid, 'lfi_nct_ga_role', 'admin');
                    $adm[] = $uid;
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
        update_option('lfi_nct_auto_ga_admins_v2', '1', false);
    }
}
