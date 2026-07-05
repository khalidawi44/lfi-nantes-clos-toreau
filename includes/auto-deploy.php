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
}
