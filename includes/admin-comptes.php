<?php
/**
 * Pages comptes NATIVES wp-admin
 *
 *  Vue de gestion des comptes (locataires + membres GA) qui reste
 *  ENTIÈREMENT dans le shell wp-admin (look WordPress natif), au lieu
 *  de rediriger vers /app/.
 *
 *  Séparation claire :
 *   - Coté site/app (/app/?vue=...) : usage terrain mobile, PWA, voix
 *   - Coté wp-admin (admin.php?page=...) : gestion de bureau, look WP
 *
 *  Toutes les actions (édition, suppression, reset password, édition
 *  enquête) sont gérées en POST sur la même URL wp-admin et reviennent
 *  sur la même page avec une notice de succès.
 */
if (!defined('ABSPATH')) exit;

/* ============================================================== *
 *  Enregistrement des sous-pages dans le menu "LFI Clos Toreau"    *
 * ============================================================== */
add_action('admin_menu', 'lfi_nct_register_admin_comptes_pages', 9);
function lfi_nct_register_admin_comptes_pages() {
    if (!current_user_can('manage_options')) return;

    add_submenu_page(
        'lfi-nct-hub',
        'Comptes locataires',
        '🏠 Locataires',
        'manage_options',
        'lfi-nct-comptes-loc',
        'lfi_nct_admin_render_comptes_loc'
    );
    add_submenu_page(
        'lfi-nct-hub',
        'Comptes membres GA',
        '👥 Membres GA',
        'manage_options',
        'lfi-nct-comptes-ga',
        'lfi_nct_admin_render_comptes_ga'
    );
}

/* ============================================================== *
 *  Helpers communs                                                  *
 * ============================================================== */
function lfi_nct_admin_comptes_tabs($current) {
    $url_loc = admin_url('admin.php?page=lfi-nct-comptes-loc');
    $url_ga  = admin_url('admin.php?page=lfi-nct-comptes-ga');
    ?>
    <h2 class="nav-tab-wrapper">
        <a class="nav-tab <?php echo $current === 'loc' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($url_loc); ?>">🏠 Locataires</a>
        <a class="nav-tab <?php echo $current === 'ga' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($url_ga); ?>">👥 Membres GA</a>
    </h2>
    <?php
}

/* ============================================================== *
 *  VUE : Liste + édition Comptes Locataires (wp-admin natif)        *
 * ============================================================== */
function lfi_nct_admin_render_comptes_loc() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;

    /* === ACTIONS POST === */
    if (!empty($_POST['lfi_nct_action']) && check_admin_referer('lfi_nct_comptes_loc')) {
        $action = sanitize_key($_POST['lfi_nct_action']);
        $uid    = (int) ($_POST['uid'] ?? 0);
        $u      = $uid ? get_userdata($uid) : null;
        if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
            add_settings_error('lfi_nct', 'no_user', 'Compte introuvable ou pas un locataire.', 'error');
        } else {
            switch ($action) {
                case 'save':
                    $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
                    $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
                    $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
                    $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
                    wp_update_user([
                        'ID'           => $uid,
                        'first_name'   => $prenom,
                        'last_name'    => $nom,
                        'user_email'   => $email ?: $u->user_email,
                        'display_name' => trim($prenom . ' ' . $nom) ?: $u->display_name,
                    ]);
                    update_user_meta($uid, 'lfi_nct_tel', $tel);

                    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
                    if ($rid && !empty($_POST['edit_probleme'])) {
                        $resp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
                        if ($resp) {
                            $data = json_decode($resp->data ?? '', true) ?: [];
                            $data['problemes_types']       = array_values(array_filter((array) ($_POST['problemes_types'] ?? [])));
                            $data['problemes_types_autre'] = sanitize_text_field(wp_unslash($_POST['problemes_types_autre'] ?? ''));
                            $data['problemes_gravite']    = max(0, min(10, (int) ($_POST['problemes_gravite'] ?? 0)));
                            $data['problemes_duree']      = sanitize_text_field(wp_unslash($_POST['problemes_duree'] ?? ''));
                            $upd = ['data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE)];
                            $adresse_in = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
                            $etage_in   = sanitize_text_field(wp_unslash($_POST['etage']   ?? ''));
                            if ($adresse_in !== '') $upd['adresse'] = function_exists('lfi_nct_normalize_address') ? lfi_nct_normalize_address($adresse_in) : $adresse_in;
                            if ($etage_in !== '')   $upd['etage']   = $etage_in;
                            $wpdb->update($wpdb->prefix . 'lfi_nct_responses', $upd, ['id' => $rid]);
                        }
                    }
                    add_settings_error('lfi_nct', 'saved', '✅ Compte locataire mis à jour.', 'updated');
                    /* Redirige vers l'édition du même user pour confirmer */
                    wp_safe_redirect(add_query_arg(['page' => 'lfi-nct-comptes-loc', 'edit' => $uid, 'saved' => 1], admin_url('admin.php')));
                    exit;

                case 'delete':
                    require_once ABSPATH . 'wp-admin/includes/user.php';
                    wp_delete_user($uid);
                    add_settings_error('lfi_nct', 'deleted', '🗑 Compte supprimé.', 'updated');
                    wp_safe_redirect(add_query_arg(['page' => 'lfi-nct-comptes-loc', 'deleted' => 1], admin_url('admin.php')));
                    exit;

                case 'reset_pwd':
                    $pwd = function_exists('lfi_nct_app_make_password') ? lfi_nct_app_make_password() : wp_generate_password(10);
                    wp_set_password($pwd, $uid);
                    set_transient('lfi_nct_admin_new_pwd', ['uid' => $uid, 'pwd' => $pwd], 600);
                    wp_safe_redirect(add_query_arg(['page' => 'lfi-nct-comptes-loc', 'edit' => $uid, 'pwd' => 1], admin_url('admin.php')));
                    exit;
            }
        }
    }

    /* === MODE ÉDITION (un compte précis) === */
    $edit_uid = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    if ($edit_uid) {
        lfi_nct_admin_render_compte_loc_edit($edit_uid);
        return;
    }

    /* === MODE LISTE === */
    /* Recherche + tri */
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $sort   = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'recent';
    $orderby = 'registered'; $order = 'DESC';
    if ($sort === 'alpha') { $orderby = 'display_name'; $order = 'ASC'; }

    $args = [
        'role'    => LFI_NCT_ROLE_TENANT,
        'fields'  => ['ID', 'user_login', 'display_name', 'user_email'],
        'number'  => 500, 'orderby' => $orderby, 'order' => $order,
    ];
    if ($search !== '') {
        $args['search'] = '*' . esc_attr($search) . '*';
        $args['search_columns'] = ['display_name', 'user_login', 'user_email', 'user_nicename'];
    }
    $users = get_users($args);
    if ($sort === 'avec_enq' || $sort === 'sans_enq') {
        $users = array_values(array_filter($users, function ($u) use ($sort) {
            $has = (bool) get_user_meta($u->ID, 'lfi_nct_response_id', true);
            return $sort === 'avec_enq' ? $has : !$has;
        }));
    }

    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px">
            <span style="background:#c8102e;color:#fff;width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:900">Φ</span>
            Comptes locataires
        </h1>

        <?php lfi_nct_admin_comptes_tabs('loc'); ?>

        <?php settings_errors('lfi_nct'); ?>
        <?php if (!empty($_GET['deleted'])): ?>
            <div class="notice notice-success is-dismissible"><p>🗑 Compte locataire supprimé.</p></div>
        <?php endif; ?>

        <p style="margin-top:14px"><?php echo (int) count($users); ?> locataire(s) affiché(s)</p>

        <form method="get" style="margin:10px 0">
            <input type="hidden" name="page" value="lfi-nct-comptes-loc">
            <p class="search-box" style="float:none;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <label class="screen-reader-text" for="s">Rechercher</label>
                <input type="search" id="s" name="s" value="<?php echo esc_attr($search); ?>" placeholder="🔎 Nom, login, email…" style="flex:1;min-width:200px">
                <select name="orderby">
                    <?php foreach ([
                        'recent'    => '📅 Plus récents',
                        'alpha'     => '🔤 Alphabétique (A→Z)',
                        'avec_enq'  => '📋 Avec enquête liée',
                        'sans_enq'  => '⚠ Sans enquête liée',
                    ] as $k => $lbl): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($sort, $k); ?>><?php echo esc_html($lbl); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filtrer">
                <?php if ($search !== '' || $sort !== 'recent'): ?>
                    <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=lfi-nct-comptes-loc')); ?>">✕ Réinitialiser</a>
                <?php endif; ?>
            </p>
        </form>

        <?php if (empty($users)): ?>
            <div class="notice notice-info"><p>Aucun locataire trouvé pour ce filtre.</p></div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nom / Login</th>
                        <th>Coordonnées</th>
                        <th>Adresse</th>
                        <th>Problème principal</th>
                        <th style="width:200px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
                    $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
                    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
                    $problem = ($resp && function_exists('lfi_nct_app_enq_problem')) ? lfi_nct_app_enq_problem($resp) : null;
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($u->display_name ?: $u->user_login); ?></strong><br>
                            <small style="color:#666">@<?php echo esc_html($u->user_login); ?></small>
                            <?php $ufonc = (string) get_user_meta($u->ID, 'lfi_nct_fonction', true);
                            if ($ufonc !== '' && function_exists('lfi_nct_fonction_label')): ?>
                                <br><span style="background:#eef4ff;color:#0b3d91;padding:1px 6px;border-radius:3px;font-size:.85em;font-weight:700"><?php echo esc_html(lfi_nct_fonction_label($ufonc)); ?></span>
                            <?php endif; ?>
                            <?php $uref = (string) get_user_meta($u->ID, 'lfi_nct_referent_immeuble', true);
                            if ($uref !== ''): ?>
                                <br><span style="background:#eef7ee;color:#186a3b;padding:1px 6px;border-radius:3px;font-size:.85em;font-weight:700">🏢 Référent·e : <?php echo esc_html($uref); ?></span>
                            <?php endif; ?>
                            <?php if (!$rid): ?>
                                <br><span style="background:#fff8e6;color:#bd8600;padding:1px 6px;border-radius:3px;font-size:.85em">⚠ Sans enquête</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u->user_email): ?>📧 <a href="mailto:<?php echo esc_attr($u->user_email); ?>"><?php echo esc_html($u->user_email); ?></a><br><?php endif; ?>
                            <?php if ($tel): ?>📞 <a href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($tel); ?></a><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($resp && $resp->adresse): ?>
                                📍 <?php echo esc_html($resp->adresse); ?>
                                <?php if ($resp->etage): ?><br><small>étage <?php echo esc_html($resp->etage); ?></small><?php endif; ?>
                            <?php else: ?>
                                <span style="color:#aaa">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($problem):
                                $main = $problem['main'];
                                echo '<span style="background:#fff3f5;color:#a30b25;padding:2px 8px;border-radius:4px">' . $main[0] . ' ' . esc_html($main[1]);
                                if ($problem['gravite']) echo ' · ' . (int) $problem['gravite'] . '/10';
                                echo '</span>';
                            else: ?>
                                <span style="color:#aaa">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page' => 'lfi-nct-comptes-loc', 'edit' => $u->ID], admin_url('admin.php'))); ?>">✏️ Éditer</a>
                            <?php
                            $dj_row = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($u->ID) : null;
                            $dj_href = $dj_row
                                ? home_url('/app/?vue=dossier-juridique-edit&id=' . (int) $dj_row->id)
                                : home_url('/app/?vue=dossier-juridique-add&tenant_uid=' . (int) $u->ID);
                            $dj_text = $dj_row ? '📁 Ouvrir le dossier' : '📁 Dossier juridique';
                            ?>
                            <a class="button" style="background:#fff3f5;color:#a30b25;border-color:#a30b25" href="<?php echo esc_url($dj_href); ?>" target="_blank" title="Accéder au dossier juridique de ce locataire"><?php echo $dj_text; ?></a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer définitivement le compte de <?php echo esc_js($u->display_name ?: $u->user_login); ?> ?');">
                                <?php wp_nonce_field('lfi_nct_comptes_loc'); ?>
                                <input type="hidden" name="lfi_nct_action" value="delete">
                                <input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
                                <button type="submit" class="button button-link-delete" style="color:#a30b25">🗑</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/* ============================================================== *
 *  Édition d'un locataire (page dédiée dans wp-admin)              *
 * ============================================================== */
function lfi_nct_admin_render_compte_loc_edit($uid) {
    global $wpdb;
    $u = get_userdata($uid);
    if (!$u || !in_array(LFI_NCT_ROLE_TENANT, (array) $u->roles, true)) {
        echo '<div class="wrap"><div class="notice notice-error"><p>Compte introuvable.</p></div></div>';
        return;
    }

    $tel = (string) get_user_meta($uid, 'lfi_nct_tel', true);
    $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    $resp = $rid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)) : null;
    $data = $resp ? (json_decode($resp->data ?? '', true) ?: []) : [];

    $cur_types = (array) ($data['problemes_types'] ?? []);
    $cur_autre = (string) ($data['problemes_types_autre'] ?? '');
    $cur_gravite = (int) ($data['problemes_gravite'] ?? 0);
    $cur_duree = (string) ($data['problemes_duree'] ?? '');

    $new_pwd = null;
    if (!empty($_GET['pwd'])) {
        $p = get_transient('lfi_nct_admin_new_pwd');
        if (is_array($p) && (int) $p['uid'] === $uid) $new_pwd = $p['pwd'];
        delete_transient('lfi_nct_admin_new_pwd');
    }

    ?>
    <div class="wrap">
        <h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=lfi-nct-comptes-loc')); ?>" class="page-title-action" style="margin-right:10px">← Retour</a>
            ✏️ Éditer : <?php echo esc_html($u->display_name ?: $u->user_login); ?>
        </h1>

        <?php settings_errors('lfi_nct'); ?>
        <?php if (!empty($_GET['saved'])): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Modifications enregistrées.</p></div>
        <?php endif; ?>
        <?php if ($new_pwd): ?>
            <div class="notice notice-info" style="border-left-color:#c8102e">
                <p style="font-size:1.05em"><strong>🔑 Nouveau mot de passe pour <?php echo esc_html($u->display_name ?: $u->user_login); ?> :</strong></p>
                <p>
                    Login : <code style="font-size:1.1em"><?php echo esc_html($u->user_login); ?></code><br>
                    Mot de passe : <code style="font-size:1.4em;background:#fff8e6;padding:4px 10px;color:#c8102e"><?php echo esc_html($new_pwd); ?></code>
                </p>
                <p><em>⚠ Ce mot de passe ne sera plus affiché. Note-le ou envoie-le maintenant au locataire.</em></p>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('lfi_nct_comptes_loc'); ?>
            <input type="hidden" name="lfi_nct_action" value="save">
            <input type="hidden" name="uid" value="<?php echo (int) $uid; ?>">

            <h2>Identité</h2>
            <table class="form-table">
                <tr><th><label for="prenom">Prénom</label></th>
                    <td><input type="text" id="prenom" name="prenom" class="regular-text" value="<?php echo esc_attr($u->first_name); ?>"></td></tr>
                <tr><th><label for="nom">Nom</label></th>
                    <td><input type="text" id="nom" name="nom" class="regular-text" value="<?php echo esc_attr($u->last_name); ?>"></td></tr>
                <tr><th><label for="email">Email</label></th>
                    <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr($u->user_email); ?>"></td></tr>
                <tr><th><label for="tel">Téléphone</label></th>
                    <td><input type="tel" id="tel" name="tel" class="regular-text" value="<?php echo esc_attr($tel); ?>" placeholder="06 12 34 56 78"></td></tr>
                <tr><th>Login (non modifiable)</th>
                    <td><code><?php echo esc_html($u->user_login); ?></code></td></tr>
            </table>

            <?php if ($resp): ?>
                <input type="hidden" name="edit_probleme" value="1">
                <h2>📋 Logement et problème (enquête #<?php echo $rid; ?>)</h2>
                <table class="form-table">
                    <tr><th><label for="adresse">Adresse</label></th>
                        <td><input type="text" id="adresse" name="adresse" class="large-text" value="<?php echo esc_attr($resp->adresse); ?>"></td></tr>
                    <tr><th><label for="etage">Étage</label></th>
                        <td><input type="text" id="etage" name="etage" class="small-text" value="<?php echo esc_attr($resp->etage); ?>"></td></tr>
                    <tr><th>Types de problèmes</th>
                        <td>
                            <?php
                            $type_labels = [
                                'degats_eaux'      => '💧 Dégâts des eaux',
                                'humidite'         => '🌫 Humidité / moisissures',
                                'insectes'         => '🐜 Nuisibles (cafards, rats…)',
                                'chauffage'        => '🥶 Chauffage défaillant',
                                'electricite'      => '⚡ Électricité défectueuse',
                                'ascenseur'        => '🛗 Ascenseur en panne',
                                'parties_communes' => '🚪 Parties communes dégradées',
                                'bruit'            => '🔊 Nuisances sonores',
                                'securite'         => '🚨 Insécurité',
                            ];
                            foreach ($type_labels as $k => $lbl) {
                                $checked = in_array($k, $cur_types, true) ? 'checked' : '';
                                echo '<label style="display:inline-flex;align-items:center;gap:6px;margin-right:14px;margin-bottom:6px">';
                                echo '<input type="checkbox" name="problemes_types[]" value="' . esc_attr($k) . '" ' . $checked . '>';
                                echo '<span>' . esc_html($lbl) . '</span></label>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr><th><label for="problemes_types_autre">Autre problème (libre)</label></th>
                        <td><input type="text" id="problemes_types_autre" name="problemes_types_autre" class="large-text" value="<?php echo esc_attr($cur_autre); ?>"></td></tr>
                    <tr><th><label for="problemes_gravite">Gravité (0-10)</label></th>
                        <td><input type="number" id="problemes_gravite" name="problemes_gravite" min="0" max="10" value="<?php echo esc_attr($cur_gravite); ?>" class="small-text"></td></tr>
                    <tr><th><label for="problemes_duree">Durée du problème</label></th>
                        <td><input type="text" id="problemes_duree" name="problemes_duree" class="regular-text" value="<?php echo esc_attr($cur_duree); ?>" placeholder="ex: 18 mois"></td></tr>
                </table>
            <?php else: ?>
                <div class="notice notice-warning inline" style="margin:14px 0"><p>⚠ Ce locataire n'a pas d'enquête liée. Les champs "Adresse / Problème" ne sont pas modifiables ici. Crée d'abord une enquête depuis le formulaire public, puis lie-la à ce compte.</p></div>
            <?php endif; ?>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero" style="background:#c8102e;border-color:#a30b25">💾 Enregistrer les modifications</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=lfi-nct-comptes-loc')); ?>" class="button">Annuler</a>
            </p>
        </form>

        <hr style="margin:24px 0">

        <h2>🔧 Actions sur ce compte</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="post" style="margin:0">
                <?php wp_nonce_field('lfi_nct_comptes_loc'); ?>
                <input type="hidden" name="lfi_nct_action" value="reset_pwd">
                <input type="hidden" name="uid" value="<?php echo (int) $uid; ?>">
                <button type="submit" class="button">🔑 Réinitialiser le mot de passe</button>
            </form>
            <form method="post" style="margin:0" onsubmit="return confirm('Supprimer définitivement le compte de <?php echo esc_js($u->display_name ?: $u->user_login); ?> ? Cette action est irréversible.');">
                <?php wp_nonce_field('lfi_nct_comptes_loc'); ?>
                <input type="hidden" name="lfi_nct_action" value="delete">
                <input type="hidden" name="uid" value="<?php echo (int) $uid; ?>">
                <button type="submit" class="button" style="color:#a30b25;border-color:#a30b25">🗑 Supprimer ce compte</button>
            </form>
            <a class="button" href="<?php echo esc_url(home_url('/app/?vue=dossier&uid=' . (int) $uid)); ?>" target="_blank">📂 Voir le dossier complet (app)</a>
            <?php
            $dj_one = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($uid) : null;
            $dj_one_href = $dj_one
                ? home_url('/app/?vue=dossier-juridique-edit&id=' . (int) $dj_one->id)
                : home_url('/app/?vue=dossier-juridique-add&tenant_uid=' . (int) $uid);
            $dj_one_txt = $dj_one ? '📁 Ouvrir le dossier juridique de ce locataire' : '📁 Ouvrir un dossier juridique pour ce locataire';
            ?>
            <a class="button button-primary" style="background:#a30b25;border-color:#7a0000" href="<?php echo esc_url($dj_one_href); ?>" target="_blank"><?php echo $dj_one_txt; ?></a>
            <a class="button" href="<?php echo esc_url(home_url('/app/?vue=intervention-add&tenant_uid=' . (int) $uid)); ?>" target="_blank">🔧 Créer une intervention</a>
        </div>
    </div>
    <?php
}

/* ============================================================== *
 *  VUE : Comptes Membres GA (page liste minimale wp-admin)         *
 *                                                                  *
 *  La gestion riche (import en masse, création) reste dans l'app.  *
 *  Ici on liste pour info + bouton "Ouvrir dans l'app".            *
 * ============================================================== */
function lfi_nct_admin_render_comptes_ga() {
    if (!current_user_can('manage_options')) return;

    $users = get_users([
        'role' => LFI_NCT_ROLE_GA,
        'fields' => ['ID', 'user_login', 'display_name', 'user_email'],
        'number' => 500, 'orderby' => 'display_name', 'order' => 'ASC',
    ]);

    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:10px">
            <span style="background:#c8102e;color:#fff;width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:900">Φ</span>
            Comptes Membres du GA
        </h1>

        <?php lfi_nct_admin_comptes_tabs('ga'); ?>

        <p style="margin-top:14px"><?php echo count($users); ?> membre(s) GA</p>

        <p>
            <a class="button button-primary" href="<?php echo esc_url(home_url('/app/?vue=comptes&tab=ga')); ?>" target="_blank" style="background:#c8102e;border-color:#a30b25">
                🚀 Gérer les comptes GA dans l'application (import en masse, création, mots de passe…)
            </a>
        </p>

        <?php if (empty($users)): ?>
            <div class="notice notice-info"><p>Aucun membre GA pour l\'instant.</p></div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Membre</th><th>Coordonnées</th><th style="width:240px">Action</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u):
                    $tel = (string) get_user_meta($u->ID, 'lfi_nct_tel', true);
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($u->display_name ?: $u->user_login); ?></strong><br>
                            <small style="color:#666">@<?php echo esc_html($u->user_login); ?></small>
                        </td>
                        <td>
                            <?php if ($u->user_email): ?>📧 <?php echo esc_html($u->user_email); ?><br><?php endif; ?>
                            <?php if ($tel): ?>📞 <?php echo esc_html($tel); ?><?php endif; ?>
                        </td>
                        <td>
                            <a class="button" href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . (int) $u->ID)); ?>">Éditer (WP)</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
