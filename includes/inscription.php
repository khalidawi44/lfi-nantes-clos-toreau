<?php
/**
 * Module Inscription publique — landing page pour les visiteur·euses
 * non connecté·es qui arrivent par lien direct (SMS, email, partage).
 *
 * Deux parcours :
 *  1. Locataire : crée un compte tenant + une réponse d'enquête liée
 *  2. Membre d'un GA LFI : crée un compte ga_member avec validation
 *     admin (statut pending) pour éviter les comptes pirates
 *
 * La liste des Groupes d'Action LFI grandit toute seule : chaque
 * nouveau nom saisi est ajouté à l'option lfi_nct_ga_list pour
 * apparaître en autocomplétion aux suivants.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_GA_LIST_OPTION = 'lfi_nct_ga_list';

/* ============================================================== *
 *  Liste des Groupes d'Action LFI (auto-remplie + grandissante)    *
 * ============================================================== */

function lfi_nct_inscription_default_gas() {
    return [
        'GA LFI Nantes Sud Clos Toreau',
        'GA LFI Nantes Université',
        'GA LFI Nantes Centre-Nord',
        'GA LFI Nantes Bottière-Pin-Sec',
        'GA LFI Nantes Bellevue',
        'GA LFI Nantes Malakoff',
        'GA LFI Saint-Herblain',
        'GA LFI Rezé',
        'GA LFI Vertou',
        'GA LFI Bouguenais',
        'GA LFI Couëron',
        'GA LFI Saint-Sébastien-sur-Loire',
        'GA LFI Sainte-Luce-sur-Loire',
        'GA LFI Carquefou',
        'GA LFI Orvault',
        'GA LFI La Chapelle-sur-Erdre',
        'GA LFI Pornic',
        'GA LFI Châteaubriant',
        'GA LFI Saint-Nazaire',
        'GA LFI Ancenis',
    ];
}

function lfi_nct_inscription_get_ga_list() {
    $list = get_option(LFI_NCT_GA_LIST_OPTION, null);
    if (!is_array($list) || empty($list)) {
        $list = lfi_nct_inscription_default_gas();
        update_option(LFI_NCT_GA_LIST_OPTION, $list, false);
    }
    return $list;
}

/** Ajoute un GA à la liste si pas déjà présent (case-insensitive). */
function lfi_nct_inscription_add_ga_if_new($name) {
    $name = trim((string) $name);
    if ($name === '' || mb_strlen($name) > 120) return false;
    $list = lfi_nct_inscription_get_ga_list();
    foreach ($list as $existing) {
        if (mb_strtolower(trim($existing)) === mb_strtolower($name)) return false;
    }
    /* Normalise : ajoute « GA LFI » devant si manque */
    if (stripos($name, 'LFI') === false && stripos($name, 'France Insoumise') === false) {
        $name = 'GA LFI ' . ucfirst($name);
    }
    $list[] = $name;
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    update_option(LFI_NCT_GA_LIST_OPTION, $list, false);
    return true;
}

/* ============================================================== *
 *  Page d'accueil inscription (landing)                            *
 * ============================================================== */

function lfi_nct_app_view_inscription() {
    /* Si déjà connecté, on affiche un lien (le redirect est géré par
       template_redirect AVANT que les headers soient envoyés). */
    if (is_user_logged_in()) {
        echo '<div class="lfi-app"><div class="lfi-app-flash ok">Vous êtes déjà connecté·e. <a href="' . esc_url(home_url('/app/')) . '">→ Aller à mon espace</a></div></div>';
        return;
    }

    lfi_nct_app_screen_open('✍️ Créer un compte', 'Choisissez votre profil');

    echo '<div class="lfi-app-help">Bienvenue ! Pour utiliser l\'app, créez un compte gratuitement en quelques secondes. Choisissez ci-dessous votre profil :</div>';

    echo '<div class="lfi-inscription-choices">';

    /* Locataire */
    echo '<a class="lfi-inscription-choice" href="' . esc_url(lfi_nct_app_url('inscription-locataire')) . '">';
    echo '<div class="ico">🏠</div>';
    echo '<div class="ti">Je suis locataire</div>';
    echo '<div class="sub">Habitant·e du Clos Toreau ou alentours. Accès : signalement, modèles de lettre, conseils juridiques, photos privées.</div>';
    echo '</a>';

    /* Membre GA */
    echo '<a class="lfi-inscription-choice" href="' . esc_url(lfi_nct_app_url('inscription-ga')) . '">';
    echo '<div class="ico">✊</div>';
    echo '<div class="ti">Je suis membre d\'un Groupe d\'Action LFI</div>';
    echo '<div class="sub">Militant·e LFI dans un GA local (Nantes ou ailleurs). Accès : événements, mobilisation, contacts avec d\'autres militant·es.</div>';
    echo '</a>';

    echo '</div>';

    echo '<div style="text-align:center;margin-top:18px">';
    echo '<a href="' . esc_url(home_url('/app/')) . '" style="color:#c8102e;font-size:.92em">← J\'ai déjà un compte, me connecter</a>';
    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Inscription LOCATAIRE                                            *
 * ============================================================== */

function lfi_nct_app_view_inscription_locataire() {
    if (is_user_logged_in()) {
        echo '<div class="lfi-app"><div class="lfi-app-flash ok">Vous êtes déjà connecté·e. <a href="' . esc_url(home_url('/app/')) . '">→ Aller à mon espace</a></div></div>';
        return;
    }
    global $wpdb;
    $err = null; $credentials = null;

    if (!empty($_POST['lfi_app_inscription_locataire']) && check_admin_referer('lfi_app_inscription_locataire')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
        $adresse = sanitize_text_field(wp_unslash($_POST['adresse'] ?? ''));
        $etage   = sanitize_text_field(wp_unslash($_POST['etage'] ?? ''));
        $appt    = sanitize_text_field(wp_unslash($_POST['appartement'] ?? ''));
        $types   = array_map('sanitize_text_field', (array) ($_POST['problemes_types'] ?? []));
        $autre   = sanitize_text_field(wp_unslash($_POST['problemes_types_autre'] ?? ''));
        $rgpd    = !empty($_POST['rgpd']);

        if (!$rgpd) {
            $err = 'Acceptez les conditions RGPD pour continuer.';
        } elseif ($prenom === '' || ($email === '' && $tel === '')) {
            $err = 'Prénom obligatoire, et au moins un téléphone OU un email.';
        } elseif ($adresse === '') {
            $err = 'L\'adresse est obligatoire.';
        } else {
            /* Normalise l'adresse */
            if (function_exists('lfi_nct_normalize_address')) {
                $adresse = lfi_nct_normalize_address($adresse);
            }
            /* Crée le compte WP */
            $login = lfi_nct_app_make_username($prenom, $nom);
            $pwd   = lfi_nct_app_make_password();
            $clean_email = (is_email($email) && !email_exists($email)) ? $email : '';
            $uid = wp_insert_user([
                'user_login'   => $login, 'user_pass' => $pwd,
                'user_email'   => $clean_email,
                'first_name'   => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role'         => LFI_NCT_ROLE_TENANT,
            ]);
            if (is_wp_error($uid)) {
                $err = 'Erreur création du compte : ' . $uid->get_error_message();
            } else {
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                update_user_meta($uid, 'lfi_nct_self_registered', 1);
                update_user_meta($uid, 'lfi_nct_pending_validation', 1);

                /* Crée une réponse d'enquête liée */
                $data = [
                    'self_registered'      => 1,
                    'problemes_presence'   => empty($types) ? 'non' : 'oui',
                    'problemes_types'      => $types,
                    'problemes_types_autre'=> $autre,
                ];
                if ($autre !== '' && function_exists('lfi_nct_learn_custom_problem')) {
                    $slug = lfi_nct_learn_custom_problem($autre);
                    if ($slug && !in_array($slug, $types, true)) $data['problemes_types'][] = $slug;
                }
                $wpdb->insert($wpdb->prefix . 'lfi_nct_responses', [
                    'militant_user_id'  => 0,
                    'militant_login'    => 'self-' . $login,
                    'submitted_at'      => current_time('mysql'),
                    'adresse'           => $adresse,
                    'etage'             => $etage,
                    'data'              => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
                    'contact_recontact' => 1,
                    'contact_prenom'    => $prenom,
                    'contact_nom'       => $nom,
                    'contact_tel'       => $tel,
                    'contact_email'     => $email,
                ]);
                $resp_id = (int) $wpdb->insert_id;
                if ($resp_id) update_user_meta($uid, 'lfi_nct_response_id', $resp_id);

                /* Auto-login + crédentiels affichés */
                wp_set_auth_cookie($uid, true);
                $credentials = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel];

                /* Notification admin par email */
                $admin_email = get_option('admin_email');
                if ($admin_email) {
                    wp_mail($admin_email, '[LFI Clos Toreau] Nouveau compte locataire auto-créé', "Nouveau locataire inscrit·e :\n\nPrénom/Nom : $prenom $nom\nTél : $tel\nEmail : $email\nAdresse : $adresse\n\nValider depuis : " . admin_url('users.php?role=' . LFI_NCT_ROLE_TENANT));
                }
            }
        }
    }

    lfi_nct_app_screen_open('🏠 Inscription locataire', 'Compte personnel pour le suivi de votre situation logement');

    if ($credentials) {
        lfi_nct_inscription_render_success($credentials, 'locataire');
        lfi_nct_app_screen_close(false);
        return;
    }

    if ($err) lfi_nct_app_flash('❌ ' . $err, 'err');

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_inscription_locataire');
    echo '<input type="hidden" name="lfi_app_inscription_locataire" value="1">';

    echo '<h3 style="margin:0">👤 Vos coordonnées</h3>';
    echo '<label>Prénom *<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Téléphone (au moins l\'un des deux *)<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<label>Email (au moins l\'un des deux *)<input type="email" name="email"></label>';

    echo '<h3 style="margin:18px 0 0">🏠 Votre logement</h3>';
    echo '<label>Adresse *<input type="text" name="adresse" list="lfi-nct-known-streets" autocomplete="off" required placeholder="ex : 12 rue d\'Hendaye"></label>';
    if (function_exists('lfi_nct_streets_datalist')) echo lfi_nct_streets_datalist('lfi-nct-known-streets');
    echo '<label>Étage<input type="text" name="etage"></label>';
    echo '<label>N° appartement<input type="text" name="appartement"></label>';

    echo '<h3 style="margin:18px 0 0">🚨 Problèmes signalés (optionnel)</h3>';
    if (function_exists('lfi_nct_problem_types_all')) {
        echo '<div class="lfi-checkbox-grid">';
        foreach (lfi_nct_problem_types_all() as $k => $lbl) {
            echo '<label class="lfi-app-checkbox-row" style="cursor:pointer"><input type="checkbox" name="problemes_types[]" value="' . esc_attr($k) . '"> <span>' . $lbl . '</span></label>';
        }
        echo '</div>';
        echo '<label>Autre problème (texte libre)<input type="text" name="problemes_types_autre"></label>';
    }

    echo '<label class="lfi-app-checkbox-row" style="cursor:pointer;margin-top:14px">';
    echo '<input type="checkbox" name="rgpd" value="1" required>';
    echo '<span>J\'accepte que mes informations soient utilisées par le Groupe d\'Action LFI Nantes Sud Clos Toreau dans le cadre du suivi logement (RGPD). Elles ne sont jamais transmises à un tiers sans mon accord explicite.</span>';
    echo '</label>';

    echo '<button type="submit" class="btn-primary big">✓ Créer mon compte</button>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>Votre compte sera créé immédiatement. Vous recevrez vos identifiants à l\'écran et pourrez les SMSer à vous-même.</small></div>';
    echo '</form>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Inscription MEMBRE GA                                            *
 * ============================================================== */

function lfi_nct_app_view_inscription_ga() {
    if (is_user_logged_in()) {
        echo '<div class="lfi-app"><div class="lfi-app-flash ok">Vous êtes déjà connecté·e. <a href="' . esc_url(home_url('/app/')) . '">→ Aller à mon espace</a></div></div>';
        return;
    }
    $err = null; $credentials = null;

    if (!empty($_POST['lfi_app_inscription_ga']) && check_admin_referer('lfi_app_inscription_ga')) {
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom']    ?? ''));
        $email  = sanitize_email(wp_unslash($_POST['email']       ?? ''));
        $tel    = sanitize_text_field(wp_unslash($_POST['tel']    ?? ''));
        $ga     = sanitize_text_field(wp_unslash($_POST['ga']     ?? ''));
        $ga_autre = sanitize_text_field(wp_unslash($_POST['ga_autre'] ?? ''));
        $rgpd   = !empty($_POST['rgpd']);

        /* Si « Autre » sélectionné, utilise le texte libre */
        if ($ga === '__autre__' || $ga === '') {
            $ga = $ga_autre;
        }

        if (!$rgpd) {
            $err = 'Acceptez les conditions RGPD pour continuer.';
        } elseif ($prenom === '' || ($email === '' && $tel === '')) {
            $err = 'Prénom obligatoire, et au moins un téléphone OU un email.';
        } elseif ($ga === '') {
            $err = 'Indiquez votre Groupe d\'Action.';
        } else {
            $is_new_ga = lfi_nct_inscription_add_ga_if_new($ga);
            $login = lfi_nct_app_make_username($prenom, $nom);
            $pwd   = lfi_nct_app_make_password();
            $clean_email = (is_email($email) && !email_exists($email)) ? $email : '';
            $uid = wp_insert_user([
                'user_login'   => $login, 'user_pass' => $pwd,
                'user_email'   => $clean_email,
                'first_name'   => $prenom, 'last_name' => $nom,
                'display_name' => trim($prenom . ' ' . $nom) ?: $login,
                'role'         => LFI_NCT_ROLE_GA,
            ]);
            if (is_wp_error($uid)) {
                $err = 'Erreur création du compte : ' . $uid->get_error_message();
            } else {
                if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
                update_user_meta($uid, 'lfi_nct_ga_name', $ga);
                update_user_meta($uid, 'lfi_nct_self_registered', 1);
                update_user_meta($uid, 'lfi_nct_pending_validation', 1);

                wp_set_auth_cookie($uid, true);
                $credentials = ['login' => $login, 'pwd' => $pwd, 'tel' => $tel, 'ga' => $ga, 'is_new_ga' => $is_new_ga];

                $admin_email = get_option('admin_email');
                if ($admin_email) {
                    $new_marker = $is_new_ga ? ' (⚠ NOUVEAU GA AJOUTÉ À LA LISTE — à vérifier)' : '';
                    wp_mail($admin_email, '[LFI Clos Toreau] Nouveau compte GA membre auto-créé', "Nouveau membre GA inscrit·e :\n\nPrénom/Nom : $prenom $nom\nGA : $ga$new_marker\nTél : $tel\nEmail : $email\n\nValider depuis : " . admin_url('users.php?role=' . LFI_NCT_ROLE_GA));
                }
            }
        }
    }

    lfi_nct_app_screen_open('✊ Inscription membre GA LFI', 'Compte militant pour la mobilisation locale');

    if ($credentials) {
        lfi_nct_inscription_render_success($credentials, 'ga');
        lfi_nct_app_screen_close(false);
        return;
    }

    if ($err) lfi_nct_app_flash('❌ ' . $err, 'err');

    $ga_list = lfi_nct_inscription_get_ga_list();

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_inscription_ga');
    echo '<input type="hidden" name="lfi_app_inscription_ga" value="1">';

    echo '<h3 style="margin:0">👤 Vos coordonnées</h3>';
    echo '<label>Prénom *<input type="text" name="prenom" required></label>';
    echo '<label>Nom<input type="text" name="nom"></label>';
    echo '<label>Téléphone (au moins l\'un des deux *)<input type="tel" name="tel" placeholder="06 12 34 56 78"></label>';
    echo '<label>Email (au moins l\'un des deux *)<input type="email" name="email"></label>';

    echo '<h3 style="margin:18px 0 0">✊ Votre Groupe d\'Action</h3>';
    echo '<label>GA LFI dans la liste *<select name="ga" id="ga-select" onchange="document.getElementById(\'ga-autre-row\').style.display=this.value===\'__autre__\'?\'block\':\'none\';">';
    echo '<option value="">— choisir dans la liste —</option>';
    foreach ($ga_list as $g) {
        echo '<option value="' . esc_attr($g) . '">' . esc_html($g) . '</option>';
    }
    echo '<option value="__autre__">➕ Mon GA n\'est pas dans la liste</option>';
    echo '</select></label>';
    echo '<div id="ga-autre-row" style="display:none">';
    echo '<label>Nom exact de votre GA<input type="text" name="ga_autre" placeholder="Ex : GA LFI Nantes Doulon, GA LFI Pont-Saint-Martin"></label>';
    echo '<div class="lfi-app-help"><small>Votre GA sera automatiquement ajouté à la liste pour les prochain·es inscrit·es. Le nom sera vérifié par l\'admin.</small></div>';
    echo '</div>';

    echo '<label class="lfi-app-checkbox-row" style="cursor:pointer;margin-top:14px">';
    echo '<input type="checkbox" name="rgpd" value="1" required>';
    echo '<span>J\'accepte que mes informations soient utilisées pour la coordination militante locale et la diffusion d\'information du GA (RGPD).</span>';
    echo '</label>';

    echo '<button type="submit" class="btn-primary big">✓ Créer mon compte militant</button>';
    echo '<div class="lfi-app-help" style="margin-top:8px"><small>⚠ Votre compte est créé immédiatement avec un statut « à valider ». Un·e admin du GA local le confirmera sous quelques heures pour activer les fonctionnalités complètes.</small></div>';
    echo '</form>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Écran de succès après inscription (avec credentials)            *
 * ============================================================== */

function lfi_nct_inscription_render_success($credentials, $type) {
    $login = $credentials['login']; $pwd = $credentials['pwd']; $tel = $credentials['tel'] ?? '';
    $site_app = home_url('/app/');
    $sms_body = "Bonjour,\n\n"
              . "Mon compte sur l'app du GA LFI Nantes Sud Clos Toreau :\n\n"
              . "🌐 " . $site_app . "\n\n"
              . "🪪 Identifiant : " . $login . "\n\n"
              . "🔑 Mot de passe : " . $pwd . "\n";
    $sms_url = $tel ? 'sms:' . preg_replace('/[^\d+]/', '', $tel) . '?body=' . rawurlencode($sms_body) : '';

    echo '<div class="lfi-app-flash ok">';
    echo '<strong>✅ Votre compte est créé !</strong> Vous êtes connecté·e automatiquement.';
    echo '</div>';

    echo '<div class="lfi-app-card">';
    echo '<div class="head"><div class="who">🔐 Vos identifiants à conserver</div></div>';
    echo '<table style="margin:10px 0;border-collapse:collapse;width:100%">';
    echo '<tr><td style="padding:6px 8px"><small>🌐 URL</small></td><td style="padding:6px 8px"><code>' . esc_html($site_app) . '</code></td></tr>';
    echo '<tr><td style="padding:6px 8px"><small>🪪 Identifiant</small></td><td style="padding:6px 8px"><code style="font-size:1.05em;background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #ddd">' . esc_html($login) . '</code></td></tr>';
    echo '<tr><td style="padding:6px 8px"><small>🔑 Mot de passe</small></td><td style="padding:6px 8px"><code style="font-size:1.05em;background:#fff;padding:2px 6px;border-radius:4px;border:1px solid #ddd;letter-spacing:.05em">' . esc_html($pwd) . '</code></td></tr>';
    echo '</table>';
    echo '<div class="row-actions">';
    if ($sms_url) echo '<a class="btn-primary" href="' . esc_url($sms_url) . '">📱 Me les SMSer</a>';
    echo function_exists('lfi_nct_copy_button')
        ? lfi_nct_copy_button($sms_body, '📋 Copier')
        : '<button type="button" class="btn-ghost" data-copy="' . esc_attr($sms_body) . '" onclick="navigator.clipboard.writeText(this.getAttribute(\'data-copy\'));this.textContent=\'✓ Copié\';">📋 Copier</button>';
    echo '</div>';
    echo '<div style="margin-top:8px"><small>⚠ Notez votre mot de passe maintenant. Vous pourrez le changer dans « Mon profil ».</small></div>';
    echo '</div>';

    if ($type === 'ga' && !empty($credentials['is_new_ga'])) {
        echo '<div class="lfi-app-flash ok" style="margin-top:14px">';
        echo '✨ <strong>Votre GA « ' . esc_html($credentials['ga']) . ' » a été ajouté à la liste</strong>. Il apparaîtra automatiquement aux prochain·es inscrit·es. Un·e admin va vérifier l\'orthographe et l\'existence officielle du groupe.';
        echo '</div>';
    }

    echo '<div class="row-actions" style="margin-top:14px">';
    echo '<a class="btn-primary big" href="' . esc_url(home_url('/app/')) . '">🚀 Accéder à mon espace</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('installer')) . '">📲 Installer l\'app sur mon téléphone</a>';
    echo '</div>';
}

/* ============================================================== *
 *  ADMIN : Gestion de la liste des GA (édition)                    *
 * ============================================================== */

function lfi_nct_app_view_ga_liste() {
    if (!current_user_can('manage_options')) return;

    if (!empty($_POST['lfi_app_ga_list_save']) && check_admin_referer('lfi_app_ga_list_save')) {
        $raw = wp_unslash($_POST['ga_list'] ?? '');
        $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw)));
        $clean = [];
        foreach ($lines as $l) {
            if (mb_strlen($l) > 0 && mb_strlen($l) <= 120) $clean[] = sanitize_text_field($l);
        }
        $clean = array_values(array_unique($clean));
        sort($clean, SORT_NATURAL | SORT_FLAG_CASE);
        update_option(LFI_NCT_GA_LIST_OPTION, $clean, false);
        wp_safe_redirect(lfi_nct_app_url('ga-liste', ['saved' => 1]));
        exit;
    }

    $list = lfi_nct_inscription_get_ga_list();

    lfi_nct_app_screen_open('✊ Liste des Groupes d\'Action', count($list) . ' GA répertorié(s)');

    if (!empty($_GET['saved'])) lfi_nct_app_flash('Liste enregistrée.');

    echo '<div class="lfi-app-help">Liste des Groupes d\'Action LFI qui apparaissent en autocomplétion dans le formulaire d\'inscription. Elle grandit toute seule à chaque nouveau GA saisi par les utilisateur·trices. Vérifie l\'orthographe + l\'existence officielle des nouvelles entrées (sur <a href="https://actionpopulaire.fr/groupes/" target="_blank">actionpopulaire.fr</a>).</div>';

    echo '<form method="post" class="lfi-app-form">';
    wp_nonce_field('lfi_app_ga_list_save');
    echo '<input type="hidden" name="lfi_app_ga_list_save" value="1">';
    echo '<label>Un GA par ligne (édition libre)<textarea name="ga_list" rows="20" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85em">' . esc_textarea(implode("\n", $list)) . '</textarea></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer la liste</button>';
    echo '</form>';

    lfi_nct_app_screen_close();
}
