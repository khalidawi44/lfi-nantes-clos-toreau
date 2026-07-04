<?php
/**
 * BAILLEURS SOCIAUX — annuaire par Groupe d'Action.
 *
 * Chaque GA n'a pas le même bailleur : au Clos Toreau c'est Nantes Métropole
 * Habitat, à Rezé c'est La Nantaise d'Habitations, etc. Un même GA peut aussi
 * suivre PLUSIEURS bailleurs (nouveau quartier, autre parc).
 *
 * - Un annuaire « connu » (pré-rempli, données publiques recherchées) que
 *   n'importe quel GA peut adopter en un clic.
 * - Le GA ajoute / retire / édite ses bailleurs et SON interlocuteur (agence
 *   locale, nom, email, téléphone).
 * - Les domaines email des bailleurs alimentent l'import automatique (tri des
 *   correspondances) ; le bailleur est proposé en liste déroulante dans
 *   l'enquête (le locataire / l'enquêteur choisit).
 *
 * Sources publiques (annuaire) : nmh.fr, nantaise-habitations.fr,
 * atlantique-habitations.fr, cdc-habitat.com.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_bailleurs_all() {
    $b = get_option('lfi_nct_bailleurs', []);
    return is_array($b) ? $b : [];
}
function lfi_nct_bailleurs_save($list) {
    update_option('lfi_nct_bailleurs', array_values($list), false);
    delete_transient('lfi_nct_inbox_tenant_index');
}
function lfi_nct_bailleur_get($id) {
    foreach (lfi_nct_bailleurs_all() as $b) if ((int) ($b['id'] ?? 0) === (int) $id) return $b;
    return null;
}

/** Annuaire « connu » (données publiques) — la base du bouton « rechercher ». */
function lfi_nct_bailleurs_directory() {
    return [
        [
            'nom' => 'Nantes Métropole Habitat', 'sigle' => 'NMH',
            'domaines' => 'nmh.fr, nantesmetropolehabitat.fr',
            'adresse' => '26 place Rosa Parks – BP 83618, 44036 Nantes Cedex 1',
            'site' => 'https://www.nmh.fr', 'tel' => '02 40 89 94 50',
        ],
        [
            'nom' => 'La Nantaise d\'Habitations', 'sigle' => 'LNH',
            'domaines' => 'nantaise-habitations.fr',
            'adresse' => 'L\'Atrium – 1 allée des Hélices – BP 50209, 44202 Nantes Cedex 2',
            'site' => 'https://www.nantaise-habitations.fr', 'tel' => '02 40 89 94 50',
        ],
        [
            'nom' => 'Atlantique Habitations', 'sigle' => 'AH',
            'domaines' => 'atlantique-habitations.fr',
            'adresse' => '10 bd Charles Gautier, 44800 Saint-Herblain',
            'site' => 'https://www.atlantique-habitations.fr', 'tel' => '02 40 89 94 50',
        ],
        [
            'nom' => 'CDC Habitat', 'sigle' => 'CDC',
            'domaines' => 'cdc-habitat.fr, cdc-habitat.com',
            'adresse' => '1 rue des Sassafras – BP 90105, 44301 Nantes Cedex 3',
            'site' => 'https://www.cdc-habitat.com', 'tel' => '02 40 89 94 50',
        ],
    ];
}

/** Seed unique : les bailleurs connus (globaux) + le bailleur du Clos Toreau. */
add_action('init', 'lfi_nct_bailleurs_seed', 21);
function lfi_nct_bailleurs_seed() {
    if (get_option('lfi_nct_bailleurs_seed_v1')) return;
    $list = lfi_nct_bailleurs_all();
    $have = [];
    foreach ($list as $b) $have[strtolower($b['nom'] ?? '')] = 1;
    $i = 0;
    foreach (lfi_nct_bailleurs_directory() as $d) {
        if (isset($have[strtolower($d['nom'])])) continue;
        $entry = [
            'id'       => (int) round(microtime(true) * 1000) + $i++,
            'nom'      => $d['nom'],
            'sigle'    => $d['sigle'],
            'domaines' => $d['domaines'],
            'adresse'  => $d['adresse'],
            'site'     => $d['site'],
            'tel'      => $d['tel'],
            'ga'       => '',   /* global : adoptable par tout GA */
            'interlocuteur' => ['nom' => '', 'email' => '', 'tel' => '', 'agence' => ''],
            'active'   => true,
        ];
        /* Clos Toreau : interlocuteur connu = Yvon Moreno, agence Goudy. */
        if ($d['sigle'] === 'NMH') {
            $entry['ga'] = 'clos-toreau';
            $entry['interlocuteur'] = ['nom' => 'Yvon Moreno', 'email' => '', 'tel' => '', 'agence' => 'Agence locale Goudy'];
        }
        $list[] = $entry;
    }
    lfi_nct_bailleurs_save($list);
    update_option('lfi_nct_bailleurs_seed_v1', 1, false);
}

/** Bailleurs disponibles pour un GA = les globaux + ceux du GA. */
function lfi_nct_bailleurs_for_ga($ga = null) {
    if ($ga === null) $ga = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $ga = ($ga === '') ? 'clos-toreau' : $ga;
    $out = [];
    foreach (lfi_nct_bailleurs_all() as $b) {
        if (empty($b['active'])) continue;
        $bga = (string) ($b['ga'] ?? '');
        if ($bga === '' || $bga === $ga) $out[] = $b;
    }
    return $out;
}

/** Tous les domaines email de bailleurs (pour l'import auto). */
function lfi_nct_bailleurs_domains() {
    $doms = [];
    foreach (lfi_nct_bailleurs_all() as $b) {
        foreach (explode(',', strtolower((string) ($b['domaines'] ?? ''))) as $d) {
            $d = trim($d);
            if ($d !== '') $doms[] = $d;
        }
    }
    return array_values(array_unique($doms));
}

/* ============================================================== *
 *  VUE ADMIN : gérer les bailleurs du GA                          *
 * ============================================================== */
function lfi_nct_app_view_bailleurs() {
    if (!(function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options'))) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $scope = function_exists('lfi_nct_scope_ga_slug') ? lfi_nct_scope_ga_slug() : '';
    $ga    = ($scope === '') ? 'clos-toreau' : $scope;

    /* Adopter un bailleur connu (le copier pour ce GA, prêt à personnaliser). */
    if (!empty($_POST['lfi_bailleur_adopt']) && check_admin_referer('lfi_bailleur')) {
        $nom = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        foreach (lfi_nct_bailleurs_directory() as $d) {
            if ($d['nom'] === $nom) {
                $list = lfi_nct_bailleurs_all();
                $list[] = ['id' => (int) round(microtime(true) * 1000), 'nom' => $d['nom'], 'sigle' => $d['sigle'], 'domaines' => $d['domaines'], 'adresse' => $d['adresse'], 'site' => $d['site'], 'tel' => $d['tel'], 'ga' => $ga, 'interlocuteur' => ['nom' => '', 'email' => '', 'tel' => '', 'agence' => ''], 'active' => true];
                lfi_nct_bailleurs_save($list);
                break;
            }
        }
        wp_safe_redirect(lfi_nct_app_url('bailleurs', ['ok' => 1])); exit;
    }
    /* Ajouter un bailleur libre. */
    if (!empty($_POST['lfi_bailleur_add']) && check_admin_referer('lfi_bailleur')) {
        $nom = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        if ($nom !== '') {
            $list = lfi_nct_bailleurs_all();
            $list[] = ['id' => (int) round(microtime(true) * 1000), 'nom' => $nom, 'sigle' => sanitize_text_field(wp_unslash($_POST['sigle'] ?? '')), 'domaines' => sanitize_text_field(wp_unslash($_POST['domaines'] ?? '')), 'adresse' => sanitize_text_field(wp_unslash($_POST['adresse'] ?? '')), 'site' => esc_url_raw(wp_unslash($_POST['site'] ?? '')), 'tel' => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')), 'ga' => $ga, 'interlocuteur' => ['nom' => '', 'email' => '', 'tel' => '', 'agence' => ''], 'active' => true];
            lfi_nct_bailleurs_save($list);
        }
        wp_safe_redirect(lfi_nct_app_url('bailleurs', ['ok' => 1])); exit;
    }
    /* Éditer (infos + interlocuteur) ou supprimer. */
    if (!empty($_POST['lfi_bailleur_save']) && check_admin_referer('lfi_bailleur')) {
        $id = (int) ($_POST['id'] ?? 0);
        $list = lfi_nct_bailleurs_all();
        foreach ($list as $k => $b) {
            if ((int) ($b['id'] ?? 0) !== $id) continue;
            $list[$k]['nom']      = sanitize_text_field(wp_unslash($_POST['nom'] ?? $b['nom']));
            $list[$k]['sigle']    = sanitize_text_field(wp_unslash($_POST['sigle'] ?? ''));
            $list[$k]['domaines'] = sanitize_text_field(wp_unslash($_POST['domaines'] ?? ''));
            $list[$k]['adresse']  = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
            $list[$k]['site']     = esc_url_raw(wp_unslash($_POST['site'] ?? ''));
            $list[$k]['tel']      = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
            $list[$k]['interlocuteur'] = [
                'nom'    => sanitize_text_field(wp_unslash($_POST['int_nom'] ?? '')),
                'email'  => sanitize_email(wp_unslash($_POST['int_email'] ?? '')),
                'tel'    => sanitize_text_field(wp_unslash($_POST['int_tel'] ?? '')),
                'agence' => sanitize_text_field(wp_unslash($_POST['int_agence'] ?? '')),
            ];
            break;
        }
        lfi_nct_bailleurs_save($list);
        wp_safe_redirect(lfi_nct_app_url('bailleurs', ['ok' => 1])); exit;
    }
    if (!empty($_POST['lfi_bailleur_del']) && check_admin_referer('lfi_bailleur')) {
        $id = (int) ($_POST['id'] ?? 0);
        $list = array_values(array_filter(lfi_nct_bailleurs_all(), function ($b) use ($id) { return (int) ($b['id'] ?? 0) !== $id; }));
        lfi_nct_bailleurs_save($list);
        wp_safe_redirect(lfi_nct_app_url('bailleurs', ['ok' => 1])); exit;
    }
    /* Pré-remplir l'interlocuteur / infos depuis l'annuaire connu. */
    if (!empty($_POST['lfi_bailleur_prefill']) && check_admin_referer('lfi_bailleur')) {
        $id = (int) ($_POST['id'] ?? 0);
        $list = lfi_nct_bailleurs_all();
        foreach ($list as $k => $b) {
            if ((int) ($b['id'] ?? 0) !== $id) continue;
            foreach (lfi_nct_bailleurs_directory() as $d) {
                if (stripos($d['nom'], (string) $b['nom']) !== false || stripos((string) $b['nom'], $d['nom']) !== false) {
                    if (empty($list[$k]['domaines'])) $list[$k]['domaines'] = $d['domaines'];
                    if (empty($list[$k]['adresse']))  $list[$k]['adresse']  = $d['adresse'];
                    if (empty($list[$k]['site']))     $list[$k]['site']     = $d['site'];
                    if (empty($list[$k]['tel']))      $list[$k]['tel']      = $d['tel'];
                    break;
                }
            }
            break;
        }
        lfi_nct_bailleurs_save($list);
        wp_safe_redirect(lfi_nct_app_url('bailleurs', ['prefilled' => 1])); exit;
    }

    lfi_nct_app_screen_open('🏢 Bailleurs sociaux', 'Ton (tes) bailleur(s) et ton interlocuteur');
    if (!empty($_GET['ok']))        lfi_nct_app_flash('✅ Enregistré.');
    if (!empty($_GET['prefilled'])) lfi_nct_app_flash('✅ Infos pré-remplies depuis l\'annuaire — complète l\'interlocuteur.');
    echo '<div class="lfi-app-help">Configure ici le(s) bailleur(s) de ton GA et <strong>ton interlocuteur</strong> (agence locale, nom, email, téléphone). Les <strong>domaines email</strong> servent à trier automatiquement la correspondance ; le bailleur est proposé au choix dans l\'enquête.</div>';

    $mine = array_filter(lfi_nct_bailleurs_all(), function ($b) use ($ga) { return (($b['ga'] ?? '') === $ga) || ($b['ga'] ?? '') === ''; });
    /* On sépare : ceux du GA (éditables) et les globaux (à adopter). */
    $own = array_filter($mine, function ($b) use ($ga) { return ($b['ga'] ?? '') === $ga; });
    echo '<h3 style="margin:14px 0 6px">Mes bailleurs</h3>';
    if (empty($own)) {
        echo '<div class="lfi-app-empty">Aucun bailleur configuré. Adopte un bailleur connu ci-dessous ou ajoute-le à la main.</div>';
    }
    foreach ($own as $b) {
        $intr = is_array($b['interlocuteur'] ?? null) ? $b['interlocuteur'] : ['nom' => '', 'email' => '', 'tel' => '', 'agence' => ''];
        echo '<div class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">🏢 ' . esc_html($b['nom']) . ($b['sigle'] ? ' (' . esc_html($b['sigle']) . ')' : '') . '</div></div>';
        echo '<form method="post" class="lfi-app-form" style="margin-top:6px">' . wp_nonce_field('lfi_bailleur', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_bailleur_save" value="1"><input type="hidden" name="id" value="' . (int) $b['id'] . '">';
        echo '<label>Nom<input type="text" name="nom" value="' . esc_attr($b['nom']) . '"></label>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
        echo '<label style="margin:0">Sigle<input type="text" name="sigle" value="' . esc_attr($b['sigle'] ?? '') . '"></label>';
        echo '<label style="margin:0">Téléphone<input type="text" name="tel" value="' . esc_attr($b['tel'] ?? '') . '"></label>';
        echo '</div>';
        echo '<label>Domaines email (séparés par des virgules)<input type="text" name="domaines" value="' . esc_attr($b['domaines'] ?? '') . '"></label>';
        echo '<label>Adresse<input type="text" name="adresse" value="' . esc_attr($b['adresse'] ?? '') . '"></label>';
        echo '<label>Site<input type="url" name="site" value="' . esc_attr($b['site'] ?? '') . '"></label>';
        echo '<div style="margin-top:8px;padding:10px;background:#f2f8fd;border-radius:8px">';
        echo '<div style="font-weight:800;color:#0066a3">📞 Mon interlocuteur</div>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px">';
        echo '<label style="margin:0">Nom<input type="text" name="int_nom" value="' . esc_attr($intr['nom'] ?? '') . '"></label>';
        echo '<label style="margin:0">Agence locale<input type="text" name="int_agence" value="' . esc_attr($intr['agence'] ?? '') . '"></label>';
        echo '<label style="margin:0">Email<input type="email" name="int_email" value="' . esc_attr($intr['email'] ?? '') . '"></label>';
        echo '<label style="margin:0">Téléphone<input type="text" name="int_tel" value="' . esc_attr($intr['tel'] ?? '') . '"></label>';
        echo '</div></div>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><button type="submit" class="btn-primary">💾 Enregistrer</button></form>';
        echo '<form method="post" onsubmit="return true">' . wp_nonce_field('lfi_bailleur', '_wpnonce', true, false) . '<input type="hidden" name="lfi_bailleur_prefill" value="1"><input type="hidden" name="id" value="' . (int) $b['id'] . '"><button type="submit" class="btn-ghost">🔎 Rechercher les infos (annuaire)</button></form>';
        echo '<form method="post" onsubmit="return confirm(\'Retirer ce bailleur ?\')">' . wp_nonce_field('lfi_bailleur', '_wpnonce', true, false) . '<input type="hidden" name="lfi_bailleur_del" value="1"><input type="hidden" name="id" value="' . (int) $b['id'] . '"><button type="submit" class="btn-ghost" style="color:#c8102e">🗑 Retirer</button></form>';
        echo '</div>';
        echo '</div>';
    }

    /* Annuaire connu — adoption en 1 clic. */
    echo '<h3 style="margin:18px 0 6px">📖 Annuaire connu (adopter en 1 clic)</h3>';
    echo '<div class="lfi-app-help"><small>Bailleurs déjà renseignés (données publiques). Adopte celui qui te concerne, puis complète ton interlocuteur.</small></div>';
    $own_names = array_map(function ($b) { return strtolower($b['nom']); }, $own);
    foreach (lfi_nct_bailleurs_directory() as $d) {
        echo '<div class="lfi-app-card"><div class="head"><div class="who">🏢 ' . esc_html($d['nom']) . '</div><div class="badge">' . esc_html($d['sigle']) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">📧 ' . esc_html($d['domaines']) . '</span></div>';
        echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_bailleur', '_wpnonce', true, false) . '<input type="hidden" name="lfi_bailleur_adopt" value="1"><input type="hidden" name="nom" value="' . esc_attr($d['nom']) . '">';
        $has = in_array(strtolower($d['nom']), $own_names, true);
        echo '<button type="submit" class="btn-' . ($has ? 'ghost' : 'primary') . '"' . ($has ? ' disabled' : '') . '>' . ($has ? '✓ déjà adopté' : '➕ Adopter ce bailleur') . '</button></form></div>';
    }

    /* Ajout libre. */
    echo '<h3 style="margin:18px 0 6px">➕ Ajouter un bailleur (autre)</h3>';
    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:8px">' . wp_nonce_field('lfi_bailleur', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_bailleur_add" value="1">';
    echo '<label>Nom du bailleur<input type="text" name="nom" required placeholder="Ex : Habitat 44"></label>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label style="margin:0">Sigle<input type="text" name="sigle"></label>';
    echo '<label style="margin:0">Téléphone<input type="text" name="tel"></label>';
    echo '</div>';
    echo '<label>Domaines email<input type="text" name="domaines" placeholder="ex : habitat44.fr"></label>';
    echo '<label>Adresse<input type="text" name="adresse"></label>';
    echo '<label>Site<input type="url" name="site"></label>';
    echo '<button type="submit" class="btn-primary" style="margin-top:6px">Ajouter</button></form>';

    lfi_nct_app_screen_close();
}
