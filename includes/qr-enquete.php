<?php
/**
 * QR CODE « FAIRE PASSER UNE ENQUÊTE » + auto-inscription par téléphone.
 *
 *  Parcours quand on scanne le QR (page publique /app/?vue=rejoindre) :
 *   1) On demande le NUMÉRO DE TÉLÉPHONE.
 *   2) On vérifie la base :
 *      - le numéro correspond à un compte ENQUÊTEUR (membre GA) → connexion
 *        AUTOMATIQUE + redirection directe vers l'enquête.
 *      - sinon → petit formulaire (prénom, nom, choix du GA) → « Enregistrer »
 *        crée le compte enquêteur, connecte, et redirige vers l'enquête.
 *
 *  Sécurité : la connexion auto par simple numéro n'est ouverte QU'AUX comptes
 *  « membre de GA » (enquêteurs). Un numéro qui correspond à un compte admin ou
 *  locataire n'ouvre PAS de session (on invite à se connecter normalement) —
 *  pour ne pas exposer un dossier locataire ni un accès admin.
 */
if (!defined('ABSPATH')) exit;

/** Retrouve un compte par numéro de téléphone (comparaison sur les 9 derniers
 *  chiffres → insensible au format 06.. / +336.. / espaces). */
function lfi_nct_find_user_by_phone($phone) {
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (strlen($digits) < 6) return null;
    $tail = substr($digits, -9);
    $ids = get_users(['meta_key' => 'lfi_nct_tel', 'fields' => ['ID'], 'number' => 5000]);
    foreach ($ids as $row) {
        $t = preg_replace('/\D/', '', (string) get_user_meta($row->ID, 'lfi_nct_tel', true));
        if ($t !== '' && substr($t, -9) === $tail) return get_userdata($row->ID);
    }
    return null;
}

/** GA par défaut du QR = le GA « maison » (Clos Toreau) : ainsi une enquête
 *  passée via le QR est bien rattachée à Clos Toreau, sauf choix explicite. */
function lfi_nct_qr_default_ga() {
    $gas = function_exists('lfi_nct_public_gas_list') ? lfi_nct_public_gas_list() : [];
    foreach ($gas as $g) {
        if (stripos((string) ($g['nom'] ?? ''), 'clos toreau') !== false
            || stripos((string) ($g['commune'] ?? ''), 'clos toreau') !== false) {
            return function_exists('lfi_nct_ga_slug') ? lfi_nct_ga_slug($g['nom']) : sanitize_title($g['nom']);
        }
    }
    $cga = function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : '';
    return $cga !== '' ? $cga : 'clos-toreau';
}

/** Fonctions / qualités possibles au moment de l'inscription via le QR.
 *  Clé = valeur stockée ; valeur = libellé affiché. '' = par défaut. */
function lfi_nct_fonctions_list() {
    return [
        ''            => '🧑‍🤝‍🧑 Habitant·e / militant·e',
        'cm'          => '🏛️ Élu·e — conseil municipal',
        'adjoint'     => '🏛️ Élu·e — adjoint·e au maire',
        'metropole'   => '🏙️ Élu·e — conseil métropolitain',
        'departement' => '🏢 Élu·e — conseil départemental',
        'region'      => '🌍 Élu·e — conseil régional',
        'depute'      => '🇫🇷 Élu·e — député·e',
        'senateur'    => '🏛️ Élu·e — sénateur·rice',
        'europe'      => '🇪🇺 Élu·e — député·e européen·ne',
        'candidat'    => '📣 Candidat·e (élection à venir)',
        'autre_elu'   => '⭐ Autre fonction / mandat',
    ];
}
/** Libellé lisible d'une fonction stockée (repli sur militant·e). */
function lfi_nct_fonction_label($key) {
    $l = lfi_nct_fonctions_list();
    return $l[$key] ?? ($l[''] ?? '');
}
/** Cette fonction est-elle celle d'un·e élu·e / candidat·e (→ espace
 *  argumentaire) plutôt qu'un·e enquêteur·rice militant·e (→ enquête) ? */
function lfi_nct_fonction_is_elu($key) {
    return $key !== '' && isset(lfi_nct_fonctions_list()[$key]);
}
/** Destination après connexion selon la fonction du compte : les élu·es
 *  arrivent sur l'argumentaire (stats + loyer), les autres sur l'enquête. */
function lfi_nct_qr_destination_for_user($uid) {
    $f = (string) get_user_meta((int) $uid, 'lfi_nct_fonction', true);
    return lfi_nct_fonction_is_elu($f) ? 'argumentaire-elue' : 'enquete';
}

/** Peut-on ouvrir une session automatiquement pour ce compte via le QR ?
 *  Uniquement les membres de GA (enquêteurs), jamais admin ni locataire seul. */
function lfi_nct_qr_can_autologin($u) {
    if (!$u) return false;
    if (user_can($u, 'manage_options')) return false;
    $roles = (array) $u->roles;
    $is_member = defined('LFI_NCT_ROLE_GA') && in_array(LFI_NCT_ROLE_GA, $roles, true);
    return $is_member;
}

/* -------------------------------------------------------------- *
 *  Handler (template_redirect, AVANT tout affichage) : pose le    *
 *  cookie d'auth puis redirige. Clés POST uniques → sûr partout.  *
 * -------------------------------------------------------------- */
add_action('template_redirect', 'lfi_nct_rejoindre_handle', 1);
function lfi_nct_rejoindre_handle() {
    /* Étape 1 : vérification du numéro. */
    if (!empty($_POST['lfi_rejoindre_phone'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lfi_rejoindre')) {
            wp_safe_redirect(lfi_nct_app_url('rejoindre')); exit;
        }
        $tel = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
        $ga  = sanitize_title(wp_unslash($_POST['ga'] ?? ''));
        $digits = preg_replace('/\D/', '', $tel);
        if (strlen($digits) < 6) { wp_safe_redirect(lfi_nct_app_url('rejoindre', ['err' => 'tel', 'ga' => $ga])); exit; }
        $u = lfi_nct_find_user_by_phone($digits);
        if ($u && lfi_nct_qr_can_autologin($u)) {
            wp_clear_auth_cookie(); wp_set_current_user($u->ID); wp_set_auth_cookie($u->ID, true);
            wp_safe_redirect(lfi_nct_app_url(lfi_nct_qr_destination_for_user($u->ID), ['bienvenue' => 1])); exit;
        }
        if ($u) { /* compte existant mais sensible (admin/locataire) → connexion normale. */
            wp_safe_redirect(lfi_nct_app_url('rejoindre', ['exists' => 1])); exit;
        }
        /* Pas de compte → formulaire d'inscription, numéro pré-rempli. */
        wp_safe_redirect(lfi_nct_app_url('rejoindre', ['step' => 'register', 'tel' => $digits, 'ga' => $ga])); exit;
    }

    /* Étape 2 : inscription express (prénom, nom, GA) → compte enquêteur. */
    if (!empty($_POST['lfi_rejoindre_register'])) {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'lfi_rejoindre')) {
            wp_safe_redirect(lfi_nct_app_url('rejoindre')); exit;
        }
        $prenom = sanitize_text_field(wp_unslash($_POST['prenom'] ?? ''));
        $nom    = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        $ga     = sanitize_title(wp_unslash($_POST['ga'] ?? ''));
        $tel    = preg_replace('/\D/', '', (string) ($_POST['tel'] ?? ''));
        $fonction = sanitize_key($_POST['fonction'] ?? '');
        if (!isset(lfi_nct_fonctions_list()[$fonction])) $fonction = '';
        if ($prenom === '' && $nom === '') { wp_safe_redirect(lfi_nct_app_url('rejoindre', ['step' => 'register', 'tel' => $tel, 'ga' => $ga, 'err' => 'nom'])); exit; }

        /* Anti-doublon : si le numéro existe déjà, on ne recrée pas. */
        $exist = $tel ? lfi_nct_find_user_by_phone($tel) : null;
        if ($exist && lfi_nct_qr_can_autologin($exist)) {
            wp_clear_auth_cookie(); wp_set_current_user($exist->ID); wp_set_auth_cookie($exist->ID, true);
            wp_safe_redirect(lfi_nct_app_url(lfi_nct_qr_destination_for_user($exist->ID), ['bienvenue' => 1])); exit;
        }

        $login = lfi_nct_app_make_username($prenom ?: 'enqueteur', $nom ?: $tel);
        $email = 'enq-' . ($tel ?: wp_generate_password(6, false)) . '@enqueteur.lfi-nct.local';
        $i = 0; while (email_exists($email)) { $i++; $email = 'enq-' . ($tel ?: 'x') . '-' . $i . '@enqueteur.lfi-nct.local'; if ($i > 50) break; }
        $uid = wp_insert_user([
            'user_login'   => $login,
            'user_pass'    => lfi_nct_app_make_password(),
            'user_email'   => $email,
            'first_name'   => $prenom,
            'last_name'    => $nom,
            'display_name' => trim($prenom . ' ' . $nom) ?: $login,
            'role'         => defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member',
        ]);
        if (is_wp_error($uid) || !$uid) { wp_safe_redirect(lfi_nct_app_url('rejoindre', ['step' => 'register', 'tel' => $tel, 'ga' => $ga, 'err' => 'create'])); exit; }
        if ($tel) update_user_meta($uid, 'lfi_nct_tel', $tel);
        /* GA choisi ; repli sur le GA « maison » (Clos Toreau) si non fourni. */
        if ($ga === '') $ga = lfi_nct_qr_default_ga();
        if ($ga !== '') update_user_meta($uid, 'lfi_nct_ga', $ga);
        if ($fonction !== '') update_user_meta($uid, 'lfi_nct_fonction', $fonction);
        update_user_meta($uid, 'lfi_nct_self_enqueteur', current_time('mysql'));

        wp_clear_auth_cookie(); wp_set_current_user($uid); wp_set_auth_cookie($uid, true);
        wp_safe_redirect(lfi_nct_app_url(lfi_nct_qr_destination_for_user($uid), ['bienvenue' => 1])); exit;
    }
}

/* -------------------------------------------------------------- *
 *  VUE PUBLIQUE : /app/?vue=rejoindre (cible du QR).             *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_rejoindre() {
    /* Déjà connecté en enquêteur/membre → direct à sa destination (élu·e →
       argumentaire, sinon enquête). */
    if (is_user_logged_in() && function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga()) {
        wp_safe_redirect(lfi_nct_app_url(lfi_nct_qr_destination_for_user(get_current_user_id()))); exit;
    }
    $step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'phone';
    $tel  = isset($_GET['tel']) ? preg_replace('/\D/', '', (string) $_GET['tel']) : '';
    $ga   = isset($_GET['ga']) ? sanitize_title(wp_unslash($_GET['ga'])) : '';

    lfi_nct_app_screen_open('📋 Rejoindre & faire passer l\'enquête', 'En 30 secondes, avec ton téléphone');
    if (!empty($_GET['err']) && $_GET['err'] === 'tel') lfi_nct_app_flash('⚠️ Numéro invalide. Réessaie.', 'err');
    if (!empty($_GET['err']) && $_GET['err'] === 'nom') lfi_nct_app_flash('⚠️ Indique au moins ton prénom ou ton nom.', 'err');
    if (!empty($_GET['err']) && $_GET['err'] === 'create') lfi_nct_app_flash('⚠️ Création impossible. Réessaie.', 'err');
    if (!empty($_GET['exists'])) lfi_nct_app_flash('ℹ️ Ce numéro a déjà un compte (admin ou locataire). Connecte-toi via ton espace personnel.');

    if ($step === 'register') {
        echo '<div class="lfi-app-help">Nouveau ? Crée ton accès enquêteur en 10 secondes, puis tu passes directement à l\'enquête.</div>';
        echo '<form method="post" class="lfi-app-form">' . wp_nonce_field('lfi_rejoindre', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_rejoindre_register" value="1">';
        echo '<input type="hidden" name="tel" value="' . esc_attr($tel) . '">';
        echo '<label>Prénom<input type="text" name="prenom" autocomplete="given-name" required></label>';
        echo '<label>Nom<input type="text" name="nom" autocomplete="family-name"></label>';
        echo '<label>Votre fonction / mandat<select name="fonction" id="rj-fonction" onchange="rjFonction()">';
        foreach (lfi_nct_fonctions_list() as $fk => $fl) echo '<option value="' . esc_attr($fk) . '">' . esc_html($fl) . '</option>';
        echo '</select></label>';
        echo '<div id="rj-elu-note" style="display:none;background:#eef4ff;border:1px solid #b9d0f5;border-radius:10px;padding:9px 11px;margin:2px 0 4px;font-size:.9em;color:#26374f">🏛️ En tant qu\'élu·e, vous accédez à un <strong>espace argumentaire</strong> : statistiques anonymes des enquêtes + répartition du loyer + points de langage pour parler aux habitant·es.</div>';
        echo '<label>Ton groupe d\'action<select name="ga">';
        $gas = function_exists('lfi_nct_public_gas_list') ? lfi_nct_public_gas_list() : [];
        $default_ga = $ga ?: lfi_nct_qr_default_ga();
        foreach ($gas as $g) {
            $slug = function_exists('lfi_nct_ga_slug') ? lfi_nct_ga_slug($g['nom']) : sanitize_title($g['nom']);
            $lbl = $g['nom'] . ($g['commune'] ? ' — ' . $g['commune'] : '');
            echo '<option value="' . esc_attr($slug) . '"' . selected($default_ga, $slug, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select></label>';
        if ($tel) echo '<div class="lfi-app-help" style="margin:4px 0"><small>📱 Téléphone : ' . esc_html($tel) . '</small></div>';
        echo '<button type="submit" id="rj-submit" class="btn-primary big" style="background:#c8102e">✅ Enregistrer et faire l\'enquête</button>';
        echo '<div style="text-align:center;margin-top:8px"><a href="' . esc_url(lfi_nct_app_url('rejoindre')) . '" style="color:#666">← changer de numéro</a></div>';
        echo '</form>';
        echo '<script>function rjFonction(){var s=document.getElementById("rj-fonction"),b=document.getElementById("rj-submit"),n=document.getElementById("rj-elu-note");var elu=s&&s.value!=="";if(n)n.style.display=elu?"block":"none";if(b)b.textContent=elu?"✅ Enregistrer et voir mes arguments":"✅ Enregistrer et faire l\'enquête";}rjFonction();</script>';
        lfi_nct_app_screen_close(); return;
    }

    /* Étape 1 : numéro de téléphone. */
    echo '<div class="lfi-app-help">Entre ton <strong>numéro de téléphone</strong>. S\'il est déjà connu, tu es connecté·e automatiquement et tu passes directement à l\'enquête. Sinon, on crée ton accès en 10 secondes.</div>';
    echo '<form method="post" class="lfi-app-form">' . wp_nonce_field('lfi_rejoindre', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_rejoindre_phone" value="1">';
    if ($ga) echo '<input type="hidden" name="ga" value="' . esc_attr($ga) . '">';
    echo '<label>📱 Numéro de téléphone<input type="tel" name="tel" inputmode="tel" autocomplete="tel" placeholder="06 12 34 56 78" value="' . esc_attr($tel) . '" required></label>';
    echo '<button type="submit" class="btn-primary big" style="background:#c8102e">Continuer →</button>';
    echo '</form>';
    echo '<div class="lfi-app-help" style="margin-top:12px"><small>🔒 Ton numéro sert seulement à retrouver (ou créer) ton accès enquêteur.</small></div>';
    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  VUE ADMIN/MEMBRE : le QR code à imprimer / afficher.         *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_qr_enquete() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = current_user_can('manage_options')
        || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga())
        || (function_exists('lfi_nct_user_role_ga') && lfi_nct_user_role_ga());
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $base = lfi_nct_app_url('rejoindre');
    $gas = function_exists('lfi_nct_public_gas_list') ? lfi_nct_public_gas_list() : [];

    lfi_nct_app_screen_open('🔳 QR code — faire passer l\'enquête', 'À imprimer / afficher · scan → enquête');
    echo '<div class="lfi-app-help">Affiche ou imprime ce QR code. Quand quelqu\'un le scanne : on lui demande son <strong>numéro de téléphone</strong>, on le <strong>connecte automatiquement</strong> s\'il a déjà un compte, sinon il crée son accès (prénom, nom, GA) — puis il arrive <strong>directement sur l\'enquête</strong>.</div>';

    /* Sélecteur de GA : pré-sélectionne le GA dans le QR (facultatif). */
    echo '<label>Pré-remplir un groupe d\'action (facultatif)<select id="qr-ga" onchange="lfiQrUpdate()">';
    echo '<option value="">— Laisser choisir au scan —</option>';
    foreach ($gas as $g) {
        $slug = function_exists('lfi_nct_ga_slug') ? lfi_nct_ga_slug($g['nom']) : sanitize_title($g['nom']);
        echo '<option value="' . esc_attr($slug) . '">' . esc_html($g['nom'] . ($g['commune'] ? ' — ' . $g['commune'] : '')) . '</option>';
    }
    echo '</select></label>';

    echo '<div id="qr-print" style="text-align:center;background:#fff;border:1px solid #e6e6e6;border-radius:14px;padding:18px;margin:12px 0">';
    echo '<div style="font-weight:900;color:#c8102e;font-size:1.15em;margin-bottom:4px">📋 Faire passer l\'enquête logement</div>';
    echo '<div style="color:#555;font-size:.9em;margin-bottom:12px">Scanne avec l\'appareil photo de ton téléphone</div>';
    echo '<canvas id="qr-canvas" style="width:280px;height:280px;max-width:80vw"></canvas>';
    echo '<div id="qr-fallback" style="margin-top:8px"></div>';
    echo '<div style="font-size:.8em;color:#888;margin-top:10px;word-break:break-all" id="qr-url">' . esc_html($base) . '</div>';
    echo '</div>';

    echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    echo '<button type="button" onclick="window.print()" class="btn-primary" style="flex:1;background:#0b3d91">🖨️ Imprimer</button>';
    echo '<button type="button" onclick="lfiQrCopy()" class="btn-ghost" style="flex:1">📋 Copier le lien</button>';
    echo '</div>';
    echo '<textarea id="qr-copytext" style="position:absolute;left:-9999px" aria-hidden="true">' . esc_attr($base) . '</textarea>';

    $base_js = wp_json_encode($base);
    ?>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
    var LFI_QR_BASE = <?php echo $base_js; ?>;
    function lfiQrUrl(){
        var ga=document.getElementById('qr-ga'); var v=ga?ga.value:'';
        return LFI_QR_BASE + (v ? (LFI_QR_BASE.indexOf('?')>=0?'&':'?') + 'ga=' + encodeURIComponent(v) : '');
    }
    function lfiQrRender(){
        var url=lfiQrUrl(); var u=document.getElementById('qr-url'); if(u)u.textContent=url;
        var c=document.getElementById('qr-copytext'); if(c)c.value=url;
        if (typeof QRious!=='undefined'){
            try{ new QRious({element:document.getElementById('qr-canvas'), value:url, size:560, level:'M'}); return; }catch(e){}
        }
        /* Repli image si la lib ne charge pas. */
        var fb=document.getElementById('qr-fallback');
        if(fb) fb.innerHTML='<img alt="QR" style="width:280px;max-width:80vw" src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&data='+encodeURIComponent(url)+'">';
    }
    function lfiQrUpdate(){ lfiQrRender(); }
    function lfiQrCopy(){ var t=document.getElementById('qr-copytext'); if(!t)return; t.style.left='0'; t.select(); try{document.execCommand('copy');}catch(e){} try{navigator.clipboard.writeText(t.value);}catch(e){} t.style.left='-9999px'; alert('Lien copié ✔'); }
    (function w(){ if(typeof QRious!=='undefined'||true){ lfiQrRender(); } })();
    setTimeout(lfiQrRender, 600);
    </script>
    <style>@media print{.lfi-app-navbar,.lfi-app-other-shortcuts,.btn-primary,.btn-ghost,.lfi-app-help{display:none!important}#qr-print{border:none}}</style>
    <?php
    lfi_nct_app_screen_close();
}
