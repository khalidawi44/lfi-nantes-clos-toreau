<?php
/**
 * Espace AVOCAT — interface dédiée aux avocat·es partenaires du GA
 * (Me Valet, Me Goache).
 *
 * Différence fondamentale avec l'espace élu·e (partenaire.php) : l'avocat·e est
 * DU CÔTÉ du locataire et tenu·e au secret professionnel. Il/elle a donc accès
 * au DÉTAIL COMPLET des dossiers qui lui sont CONFIÉS (constat, désordres,
 * chronologie, préjudice chiffré, pièces, note structurée) — mais UNIQUEMENT
 * ceux-là. Aucun accès à l'enquête terrain globale ni aux autres locataires.
 *
 * Simplifier la vie de l'avocat·e :
 *   - un seul écran = la liste de SES dossiers confiés, prêts à plaider ;
 *   - pour chaque dossier : la note structurée (faits, droit, demandes,
 *     délais NMH) + les pièces téléchargeables + une ligne directe avec le GA.
 * Simplifier la vie de Fabrice :
 *   - un bouton « ⚖️ Confier à un avocat » sur le dossier → l'avocat·e reçoit
 *     tout, par lien magique (rien à taper) ;
 *   - les questions de l'avocat·e remontent en alerte sur le tableau de bord.
 */
if (!defined('ABSPATH')) exit;

if (!defined('LFI_NCT_ROLE_AVOCAT')) define('LFI_NCT_ROLE_AVOCAT', 'lfi_nct_avocat');

/* -------------------------------------------------------------- *
 *  Rôle                                                           *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_avocat_ensure_role', 6);
function lfi_nct_avocat_ensure_role() {
    if (!get_role(LFI_NCT_ROLE_AVOCAT)) {
        add_role(LFI_NCT_ROLE_AVOCAT, 'Avocat·e partenaire du GA', ['read' => true]);
    }
}
function lfi_nct_user_role_avocat($uid = 0) {
    $u = $uid ? get_userdata($uid) : wp_get_current_user();
    return $u && in_array(LFI_NCT_ROLE_AVOCAT, (array) $u->roles, true);
}
/** Qui peut CONFIER un dossier / gérer les avocats : tout admin de GA. */
function lfi_nct_avocat_can() {
    return function_exists('lfi_nct_can_admin_ga') ? lfi_nct_can_admin_ga() : current_user_can('manage_options');
}
function lfi_nct_avocat_list() {
    return get_users(['role' => LFI_NCT_ROLE_AVOCAT, 'orderby' => 'display_name']);
}

/**
 * Trouve un·e avocat·e par nom (ou email), sinon le/la CRÉE. Renvoie l'uid.
 * Sert à l'import auto d'un dossier .md : « je trouve Me X → je la crée et je la
 * rattache au dossier ».
 */
function lfi_nct_avocat_ensure($nom, $email = '', $tel = '', $spec = '') {
    $nom = trim((string) $nom); if ($nom === '') return 0;
    if (function_exists('lfi_nct_avocat_ensure_role')) lfi_nct_avocat_ensure_role();
    /* Déjà présent·e (même nom, insensible à la casse) ? */
    foreach (lfi_nct_avocat_list() as $av) {
        if (mb_strtolower(trim((string) $av->display_name)) === mb_strtolower($nom)) return (int) $av->ID;
    }
    $email = trim((string) $email);
    if ($email !== '' && is_email($email)) { $u = get_user_by('email', $email); if ($u) return (int) $u->ID; }
    /* Création (même logique que le formulaire avocats). */
    $base = 'me.' . sanitize_title($nom); $login = $base; $n = 1;
    while (username_exists($login)) { $login = $base . '.' . (++$n); }
    if ($email === '' || !is_email($email) || email_exists($email)) $email = $login . '@avocat.example';
    $uid = wp_insert_user(['user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password(16), 'display_name' => $nom, 'role' => LFI_NCT_ROLE_AVOCAT]);
    if (is_wp_error($uid)) return 0;
    if (trim((string) $tel) !== '')  update_user_meta($uid, 'lfi_nct_tel', sanitize_text_field($tel));
    if (trim((string) $spec) !== '') update_user_meta($uid, 'lfi_nct_avocat_specialites', sanitize_text_field($spec));
    return (int) $uid;
}

/* -------------------------------------------------------------- *
 *  Assignation : quel·le avocat·e sur quel dossier                *
 *  (stockée en meta du compte locataire).                         *
 * -------------------------------------------------------------- */
function lfi_nct_avocat_of_tenant($tenant_uid) {
    return (int) get_user_meta((int) $tenant_uid, 'lfi_nct_avocat_uid', true);
}
function lfi_nct_avocat_assign_tenant($tenant_uid, $avocat_uid) {
    $tenant_uid = (int) $tenant_uid; $avocat_uid = (int) $avocat_uid;
    if ($avocat_uid) update_user_meta($tenant_uid, 'lfi_nct_avocat_uid', $avocat_uid);
    else             delete_user_meta($tenant_uid, 'lfi_nct_avocat_uid');
}
/** Dossiers (comptes locataires) confiés à un·e avocat·e. */
function lfi_nct_avocat_tenants($avocat_uid) {
    return get_users([
        'role'       => defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant',
        'meta_key'   => 'lfi_nct_avocat_uid',
        'meta_value' => (int) $avocat_uid,
        'number'     => 500,
        'orderby'    => 'display_name',
    ]);
}

/* -------------------------------------------------------------- *
 *  Ligne directe (messages) — par dossier locataire.             *
 * -------------------------------------------------------------- */
function lfi_nct_avocat_thread($tenant_uid) {
    $all = get_option('lfi_nct_avocat_thread', []);
    return (is_array($all) && !empty($all[$tenant_uid]) && is_array($all[$tenant_uid])) ? $all[$tenant_uid] : [];
}
function lfi_nct_avocat_thread_save($tenant_uid, $msgs) {
    $all = get_option('lfi_nct_avocat_thread', []);
    if (!is_array($all)) $all = [];
    $all[$tenant_uid] = array_values($msgs);
    update_option('lfi_nct_avocat_thread', $all, false);
}

/* Alertes pour l'ADMIN : l'avocat·e a écrit / posé une question. */
function lfi_nct_avocat_activity_mark($avocat_uid) {
    $a = get_option('lfi_nct_avocat_activity', []);
    if (!is_array($a)) $a = [];
    $a[$avocat_uid] = (int) ($a[$avocat_uid] ?? 0) + 1;
    update_option('lfi_nct_avocat_activity', $a, false);
}
function lfi_nct_avocat_activity_clear($avocat_uid) {
    $a = get_option('lfi_nct_avocat_activity', []);
    if (is_array($a) && isset($a[$avocat_uid])) { unset($a[$avocat_uid]); update_option('lfi_nct_avocat_activity', $a, false); }
}
/** Notice sur le tableau de bord admin quand un·e avocat·e a écrit. */
function lfi_nct_avocat_admin_notice() {
    if (!lfi_nct_avocat_can()) return;
    $a = get_option('lfi_nct_avocat_activity', []);
    if (!is_array($a) || empty($a)) return;
    foreach ($a as $uid => $n) {
        $n = (int) $n; if ($n < 1) continue;
        $u = get_user_by('id', (int) $uid); if (!$u) continue;
        $url = lfi_nct_app_url('avocat-espace', ['uid' => (int) $uid]);
        echo '<a href="' . esc_url($url) . '" style="text-decoration:none;color:inherit;display:block">';
        echo '<div style="margin:0 0 12px;background:linear-gradient(135deg,#6a1b9a,#8e24aa);color:#fff;border-radius:14px;padding:13px 16px;display:flex;align-items:center;gap:12px">';
        echo '<div style="font-size:1.7em">⚖️</div>';
        echo '<div style="flex:1"><div style="font-weight:900">' . esc_html($u->display_name) . ' (avocat·e) t\'a écrit</div>';
        echo '<div style="font-size:.86em;opacity:.95">💬 ' . $n . ' message' . ($n > 1 ? 's' : '') . ' — clique pour répondre</div></div>';
        echo '<div style="background:rgba(255,255,255,.22);border-radius:20px;padding:6px 12px;font-weight:800;font-size:.85em">Ouvrir →</div>';
        echo '</div></a>';
    }
}

/** Traite l'envoi d'un message (des 2 côtés). $back_url = page de retour. */
function lfi_nct_avocat_handle_msg($tenant_uid, $back_url) {
    if (empty($_POST['lfi_avocat_msg']) || !check_admin_referer('lfi_avocat_msg')) return;
    $msg = sanitize_textarea_field(wp_unslash($_POST['msg'] ?? ''));
    if ($msg === '') { wp_safe_redirect($back_url); exit; }
    $me = wp_get_current_user();
    $by = $me->display_name ?: $me->user_login;
    $is_avocat = lfi_nct_user_role_avocat();
    $msgs = lfi_nct_avocat_thread($tenant_uid);
    $msgs[] = ['by' => $is_avocat ? 'avocat' : 'ga', 'name' => $by, 'msg' => $msg, 'date' => current_time('mysql')];
    lfi_nct_avocat_thread_save($tenant_uid, $msgs);

    if ($is_avocat) {
        lfi_nct_avocat_activity_mark(get_current_user_id());
    } else {
        /* le GA écrit → notifie l'avocat·e par email (avec lien magique). */
        $av = get_user_by('id', lfi_nct_avocat_of_tenant($tenant_uid));
        if ($av && is_email($av->user_email) && stripos($av->user_email, '@avocat.') === false) {
            $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($av->ID, lfi_nct_app_url('espace')) : lfi_nct_app_url();
            wp_mail($av->user_email, 'Message du GA LFI — dossier locataire',
                "Bonjour,\n\nLe Groupe d'Action vous a écrit sur la ligne directe d'un dossier :\n\n« " . $msg . " »\n\nRépondez directement dans votre espace : " . $link);
        }
    }
    wp_safe_redirect(add_query_arg('msg', 1, $back_url)); exit;
}

/** Rend la ligne directe (fil de messages + formulaire). */
function lfi_nct_avocat_render_thread($tenant_uid, $title = '💬 Ligne directe GA ⇄ avocat·e') {
    echo '<h3 style="margin:16px 0 6px;color:#6a1b9a">' . esc_html($title) . '</h3>';
    $msgs = lfi_nct_avocat_thread($tenant_uid);
    echo '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">';
    if (empty($msgs)) {
        echo '<div class="lfi-app-help">Aucun message pour l\'instant. Posez vos questions ici — tout reste privé à ce dossier.</div>';
    } else {
        foreach ($msgs as $m) {
            $mine = ($m['by'] ?? '') === (lfi_nct_user_role_avocat() ? 'avocat' : 'ga');
            $bg = ($m['by'] ?? '') === 'avocat' ? '#f3e9fb' : '#e7f0fb';
            echo '<div style="align-self:' . ($mine ? 'flex-end' : 'flex-start') . ';max-width:85%;background:' . $bg . ';border-radius:12px;padding:8px 11px">';
            echo '<div style="font-size:.72em;color:#888;font-weight:700">' . esc_html($m['name'] ?? '') . ' · ' . esc_html(wp_date('j M · H:i', strtotime($m['date'] ?? 'now'))) . '</div>';
            echo '<div style="margin-top:2px">' . nl2br(esc_html($m['msg'] ?? '')) . '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<form method="post" class="lfi-app-form">' . wp_nonce_field('lfi_avocat_msg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_avocat_msg" value="1"><input type="hidden" name="tenant_uid" value="' . (int) $tenant_uid . '">';
    /* Questions rapides pré-typées — un clic remplit le message. */
    $quick = lfi_nct_user_role_avocat()
        ? ['Une pièce manque, laquelle ?', 'On tente l\'amiable ou on assigne ?', 'Quel est le délai à tenir ?', 'Peux-tu m\'envoyer le mandat signé ?']
        : ['Où en êtes-vous ?', 'Peut-on assigner ?', 'Quelles pièces vous manque-t-il ?', 'Estimation du délai ?'];
    $tid_js = 'q' . (int) $tenant_uid;
    echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px">';
    foreach ($quick as $q) {
        echo '<button type="button" onclick="var t=this.closest(\'form\').querySelector(\'textarea\');t.value=' . esc_attr(wp_json_encode($q)) . ';t.focus();" style="background:#f3e9fb;color:#6a1b9a;border:1px solid #e2d3f0;border-radius:14px;padding:4px 10px;font-size:.8em;cursor:pointer">' . esc_html($q) . '</button>';
    }
    echo '</div>';
    echo '<textarea name="msg" rows="2" placeholder="Écrire un message…"></textarea>';
    echo '<button type="submit" class="btn-primary" style="background:#6a1b9a">Envoyer</button></form>';
}

/* -------------------------------------------------------------- *
 *  Côté ADMIN : confier un dossier à un·e avocat·e (boîte sur le  *
 *  dossier locataire).                                            *
 * -------------------------------------------------------------- */
/** Adresse email du GA (mode de discussion avec les avocats). */
function lfi_nct_ga_contact_email() {
    return (string) get_option('lfi_nct_ga_contact_email', 'nantessudclostoreau@gmail.com');
}
/** Bases légales pertinentes détectées d'après la situation du dossier. */
function lfi_nct_avocat_legal_lines($uid) {
    $text = function_exists('lfi_nct_tenant_situation_text') ? lfi_nct_tenant_situation_text($uid) : '';
    $norm = function ($s) { return function_exists('lfi_nct_situation_norm') ? lfi_nct_situation_norm($s) : mb_strtolower($s); };
    $has = function ($kws) use ($text, $norm) { foreach ((array) $kws as $k) { if (strpos($text, $norm($k)) !== false) return true; } return false; };
    $L = [];
    if ($has(['incendie', 'le feu', 'fumee', 'ascenseur', 'desenfumage', 'sinistre', 'exutoire'])) {
        $L[] = "Sinistre né dans une PARTIE COMMUNE (ascenseur), dont le bailleur est propriétaire et gestionnaire : responsabilité du bailleur — art. 1719 et 1721 du Code civil (délivrance, jouissance paisible, garantie, indemnisation) et logement décent (loi du 6 juillet 1989, art. 6).";
        $L[] = "Le locataire n'a pas à être renvoyé vers sa propre assurance habitation pour un sinistre né dans les parties communes : la charge en incombe au bailleur.";
        $L[] = "Relogement d'urgence : obligation d'hébergement/relogement du bailleur (art. L. 521-3-1 du CCH) et relogement d'office aux frais du bailleur (art. L. 521-3-2 du CCH) — SOUS RÉSERVE qu'un arrêté de mise en sécurité / de traitement de l'insalubrité assorti d'une interdiction (même temporaire) d'habiter ait été pris, ce qu'il conviendra de vérifier voire de provoquer.";
        $L[] = "Volet pénal envisageable si les issues de secours / le désenfumage ont été rendus inaccessibles (mise en danger de la vie d'autrui).";
    }
    /* Faits aggravants d'atteinte à la vie/l'intégrité (évacuation par la fenêtre,
       inhalation de fumées, nourrisson) → volet pénal caractérisé. Ne s'affiche
       que si ces éléments figurent réellement dans le récit de la situation. */
    if ($has(['par la fenetre', 'fenetre du', 'evacu', 'nourrisson', 'bebe de', 'inhal', 'fumees inhalees', 'intoxication', 'ingere des fumees', 'ingestion de fumees'])) {
        $L[] = "Volet PÉNAL caractérisé — mise en danger de la vie d'autrui (art. 223-1 du Code pénal) et, le cas échéant, blessures involontaires : inhalation de fumées et évacuation d'enfants (dont un nourrisson) par la fenêtre d'un étage élevé, recueillis par un voisin au péril de sa propre vie. Éléments à verser au dépôt de plainte.";
    }
    if ($has(['relog', 'heberg', 'hotel', 'expuls'])) $L[] = "Relogement / hébergement à la charge du bailleur — art. L. 521-3-1 et L. 521-3-2 du CCH (sous réserve d'un arrêté avec interdiction d'habiter).";
    if ($has(['vetement', 'effets', 'suie', 'biens', 'meubles'])) $L[] = "Prise en charge / indemnisation des biens endommagés — art. 1721 du Code civil.";
    if ($has(['moisiss', 'humidit', 'insalub', 'decence', 'asthme', 'sante'])) $L[] = "Obligation de délivrer un logement décent (art. 1719 CC ; décret décence) ; saisine possible du SCHS / de l'ARS.";
    if ($has(['punaise', 'blatte', 'cafard', 'nuisible', 'rongeur', 'rat'])) $L[] = "Logement décent / trouble de jouissance : infestation à la charge du bailleur — art. 1719 CC.";
    return array_values(array_unique($L));
}
/** Email d'orientation COMPLET pour l'avocat·e (prépare le dossier, laisse la
 *  libre appréciation). Renvoie [sujet, corps]. */
function lfi_nct_avocat_orientation_email($u) {
    $uid = (int) $u->ID;
    $situ = (string) get_user_meta($uid, 'lfi_nct_situation_note', true);
    $adr = ''; $rid = (int) get_user_meta($uid, 'lfi_nct_response_id', true);
    if (!$rid && function_exists('lfi_nct_user_tenant_response_id')) $rid = (int) lfi_nct_user_tenant_response_id($uid);
    if ($rid) { global $wpdb; $rr = $wpdb->get_row($wpdb->prepare("SELECT adresse FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid)); if ($rr) $adr = (string) $rr->adresse; }
    $moi = wp_get_current_user();
    $mail = lfi_nct_ga_contact_email();
    $subj = "Orientation d'un locataire — " . $u->display_name . ($adr ? " (" . $adr . ")" : "") . " — demande de conseil";
    /* Email SIMPLE : on raconte l'histoire et ce que le locataire veut. Pas
       d'articles de loi (l'avocat·e connaît le droit mieux que nous) ni de liste
       de pièces (elles sont jointes au message par le GA). */
    $b  = "Maître,\n\n";
    $b .= "Le Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau et l'Union des Quartiers Libres vous orientent " . $u->display_name . ", locataire de Nantes Métropole Habitat" . ($adr ? " (" . $adr . ")" : "") . ". Nous agissons à sa demande, sur mandat écrit signé de sa main, et souhaitons qu'il/elle vous rencontre.\n\n";
    $b .= "L'HISTOIRE ET CE QUE LE LOCATAIRE DEMANDE :\n" . ($situ !== '' ? $situ : "[à compléter : racontez toute l'histoire — ce qui est arrivé, ce que NMH a fait ou pas, et ce que le locataire attend]") . "\n\n";
    $b .= "Les pièces utiles (photos, constats) sont jointes à ce message.\n\n";
    $b .= "Merci de nous répondre à cette adresse : " . $mail . ".\n\n";
    $b .= "Bien cordialement,\n" . ($moi->display_name ?: "Le Groupe d'Action") . "\nUnion des Quartiers Libres, avec le Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau";
    return [$subj, $b];
}

/** Box « Orienter un·e avocat·e » — PAR EMAIL uniquement (mode de discussion du
 *  GA). Aucun compte, aucun lien vers l'application : l'avocat·e répond par mail
 *  à l'adresse du GA. On prépare le dossier ; il/elle garde toute latitude. */
function lfi_nct_avocat_assign_box($u) {
    if (!lfi_nct_avocat_can()) return;
    list($subj, $body) = lfi_nct_avocat_orientation_email($u);
    $id = (int) $u->ID;
    echo '<div id="lfi-avocat-action" style="margin-top:10px;padding:11px 13px;background:#f7f0fb;border-radius:10px;border:1px solid #e2d3f0;scroll-margin-top:70px">';
    echo '<div style="font-weight:800;color:#6a1b9a">🧑‍⚖️ Envoyer le dossier à l\'avocat·e</div>';
    echo '<div class="lfi-app-help" style="margin:4px 0"><small>Choisis l\'avocat·e, l\'email tout prêt s\'ouvre (histoire + ce que veut le locataire). Tu joins tes photos.</small></div>';
    /* CHOIX de l'avocat·e dans la LISTE de la base (leur email est enregistré). */
    $avs = function_exists('lfi_nct_avocat_list') ? lfi_nct_avocat_list() : [];
    echo '<select id="lfi-avmail-' . $id . '" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px;margin-bottom:6px">';
    echo '<option value="">— choisir l\'avocat·e —</option>';
    foreach ($avs as $av) {
        $em = (is_email($av->user_email) && stripos($av->user_email, '@avocat.example') === false && stripos($av->user_email, '@enqueteur.') === false) ? $av->user_email : '';
        echo '<option value="' . esc_attr($em) . '">' . esc_html($av->display_name) . ($em ? '' : ' — email à compléter') . '</option>';
    }
    echo '</select>';
    echo '<button type="button" onclick="lfiAvMail(' . $id . ')" class="btn-primary" style="background:#6a1b9a;width:100%">✉️ Ouvrir l\'email tout prêt (Gmail)</button>';
    echo '<div class="lfi-app-help" style="margin:5px 0 0"><small>Un email manque ? Renseigne-le dans <a href="' . esc_url(lfi_nct_app_url('avocats')) . '">⚖️ Avocat·es partenaires</a>.</small></div>';
    echo '<script>var LFI_AVM_' . $id . '={s:' . wp_json_encode($subj) . ',b:' . wp_json_encode($body) . '};function lfiAvMail(id){var d=window["LFI_AVM_"+id];var el=document.getElementById("lfi-avmail-"+id);var raw=el?el.value:"";if(!raw){alert("Choisis l\'avocat·e dans la liste (ou renseigne son email dans « Avocats partenaires »).");return;}var to=encodeURIComponent(raw);var s=encodeURIComponent(d.s),b=encodeURIComponent(d.b);var app="googlegmail:///co?to="+to+"&subject="+s+"&body="+b;var web="https://mail.google.com/mail/?view=cm&fs=1&to="+to+"&su="+s+"&body="+b;var t=Date.now();try{window.location.href=app;}catch(e){}setTimeout(function(){if(Date.now()-t<1600)window.open(web,"_blank");},700);}</script>';
    echo '</div>';
}

add_action('admin_post_lfi_nct_avocat_assign', 'lfi_nct_avocat_assign_handler');
function lfi_nct_avocat_assign_handler() {
    if (!lfi_nct_avocat_can()) wp_die('non autorisé');
    check_admin_referer('lfi_nct_avocat_assign');
    $tid = (int) ($_POST['tenant_uid'] ?? 0);
    $aid = (int) ($_POST['avocat_uid'] ?? 0);
    if ($tid && (!function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($tid))) {
        lfi_nct_avocat_assign_tenant($tid, $aid);
        /* Notifie l'avocat·e par email (lien magique) s'il a un vrai email. */
        if ($aid) {
            $av = get_user_by('id', $aid);
            if ($av && is_email($av->user_email) && stripos($av->user_email, '@avocat.') === false && function_exists('lfi_nct_login_link')) {
                $link = lfi_nct_login_link($aid, lfi_nct_app_url('espace'));
                wp_mail($av->user_email, 'Un dossier vous est confié — LFI Nantes Sud',
                    "Bonjour,\n\nLe Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau vous confie un dossier locataire (note structurée + pièces). Accédez-y directement (rien à taper) : " . $link);
            }
        }
    }
    wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $tid, 'avocat_ok' => 1]));
    exit;
}

/* -------------------------------------------------------------- *
 *  DISPATCH : l'avocat·e « pur » a son espace dédié.              *
 * -------------------------------------------------------------- */
function lfi_nct_avocat_dispatch() {
    if (!lfi_nct_user_role_avocat()) return false;
    /* Un·e avocat·e qui serait aussi admin garde sa console pour le reste. */
    $also_admin = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    $vue = isset($_GET['vue']) ? sanitize_key($_GET['vue']) : '';
    if ($also_admin && $vue !== 'espace' && $vue !== 'dossier-avocat' && $vue !== 'justice-cdc' && $vue !== 'jurisprudence' && $vue !== 'relogement') return false;

    switch ($vue) {
        case 'dossier-avocat': lfi_nct_app_view_dossier_avocat(); break; /* la note (accès contrôlé par assignation) */
        case 'justice-cdc':    lfi_nct_app_view_justice_cdc();    break; /* saisine CDC (accès contrôlé) */
        case 'jurisprudence':  lfi_nct_app_view_jurisprudence();  break; /* Judilibre (scopé à ses dossiers) */
        case 'relogement':     lfi_nct_app_view_relogement();     break; /* DALO / relogement (accès contrôlé) */
        case 'mon-profil':     lfi_nct_app_view_mon_profil();     break;
        case 'installer':      lfi_nct_app_view_installer();      break;
        case 'espace':         /* fallthrough */
        default:               lfi_nct_app_view_avocat_dashboard();
    }
    return true;
}

/** Tableau de bord de l'avocat·e : ses dossiers confiés. */
function lfi_nct_app_view_avocat_dashboard() {
    $me  = wp_get_current_user();
    $aid = get_current_user_id();

    /* Envoi d'un message depuis un dossier (avocat·e → GA). On vérifie que le
       dossier est bien confié à cet·te avocat·e avant d'écrire. */
    if (!empty($_POST['lfi_avocat_msg'])) {
        $tid = (int) ($_POST['tenant_uid'] ?? 0);
        if ($tid && lfi_nct_avocat_of_tenant($tid) === $aid) {
            lfi_nct_avocat_handle_msg($tid, lfi_nct_app_url('espace'));
        }
    }

    lfi_nct_app_screen_open('⚖️ Bonjour Maître', 'Votre espace de travail — simple et confidentiel');

    /* Accès essentiels — épuré : 3 boutons clairs. */
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin:4px 0 10px">';
    echo '<a class="btn-primary" style="background:#6a1b9a;text-align:center" href="#mes-dossiers">📂 Mes dossiers</a>';
    echo '<a class="btn-primary" style="background:#0066a3;text-align:center" href="' . esc_url(lfi_nct_app_url('jurisprudence')) . '">🔎 Jurisprudence (Judilibre)</a>';
    echo '<a class="btn-ghost" style="text-align:center" href="' . esc_url(lfi_nct_app_url('mon-profil')) . '">🔑 Mon mot de passe</a>';
    echo '</div>';

    /* Guide pédagogique — replié (épuré), à lire une fois. */
    echo '<details style="background:#f7f0fb;border:1px solid #e2d3f0;border-radius:12px;overflow:hidden;margin-bottom:12px">';
    echo '<summary style="cursor:pointer;list-style:none;padding:12px 14px;font-weight:800;color:#6a1b9a;display:flex;justify-content:space-between;align-items:center"><span>ℹ️ Comment ça marche (à lire une fois)</span><span>▾</span></summary>';
    echo '<div style="padding:0 14px 14px;line-height:1.55;color:#333">';
    echo '<p style="margin:6px 0"><strong>1) Votre mot de passe.</strong> Vous êtes connecté·e sans rien taper. Choisissez votre mot de passe dans <a href="' . esc_url(lfi_nct_app_url('mon-profil')) . '">🔑 Mon mot de passe</a> — il sera enregistré sur votre appareil, vous n\'aurez plus à le saisir.</p>';
    echo '<p style="margin:6px 0"><strong>2) Vos dossiers.</strong> Ci-dessous, uniquement les dossiers <strong>que le GA vous confie</strong> (rien d\'autre). Chaque dossier contient : la <strong>note structurée</strong> (faits, désordres, chronologie, <strong>préjudice chiffré</strong>, délais, demandes), les <strong>pièces</strong> (classées par date de prise de vue), et le <strong>dossier de conciliation</strong> déjà monté.</p>';
    echo '<p style="margin:6px 0"><strong>3) La jurisprudence (Judilibre).</strong> Pour chaque dossier, les décisions réelles <strong>liées au problème du locataire s\'affichent automatiquement</strong> (bouton « 🔎 Jurisprudence liée »). Vous pouvez aussi lancer vos propres recherches — tout est intégré, avec le lien officiel sous chaque décision.</p>';
    echo '<p style="margin:6px 0"><strong>4) La ligne directe.</strong> Sur chaque dossier, une messagerie privée avec le GA (questions rapides).</p>';
    echo '<p style="margin:6px 0;color:#6a1b9a"><small>🔒 Confidentiel : vous ne voyez que vos dossiers, jamais notre enquête globale ni les autres locataires.</small></p>';
    echo '</div></details>';

    echo '<h3 id="mes-dossiers" style="margin:10px 0 6px;color:#6a1b9a">📂 Vos dossiers confiés</h3>';

    $tenants = lfi_nct_avocat_tenants($aid);
    if (empty($tenants)) {
        echo '<div class="lfi-app-empty">Aucun dossier confié pour l\'instant. Le GA vous en confiera depuis l\'application — vous recevrez un lien direct.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach ($tenants as $t) {
        $note_url = lfi_nct_app_url('dossier-avocat', ['uid' => (int) $t->ID]);
        $unread = 0; /* fil vu côté avocat — simple compteur informatif */
        echo '<li class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
        echo '<div class="head"><div class="who">📂 ' . esc_html($t->display_name) . '</div></div>';
        $dj = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant((int) $t->ID) : null;
        echo '<div class="row-actions" style="margin-top:6px;flex-wrap:wrap">';
        echo '<a class="btn-primary" style="background:#6a1b9a" href="' . esc_url($note_url) . '" target="_blank">📄 Note complète</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('justice-cdc', ['uid' => (int) $t->ID])) . '">⚖️ Dossier conciliation</a>';
        if ($dj) echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('jurisprudence', ['id' => (int) $dj->id])) . '">🔎 Jurisprudence liée</a>';
        echo '</div>';
        /* Pièces versées (lecture seule pour l'avocat·e). */
        if (function_exists('lfi_nct_justice_pieces_box')) lfi_nct_justice_pieces_box($t, false);
        /* Ligne directe repliée par dossier. */
        echo '<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:700;color:#6a1b9a">💬 Ligne directe avec le GA</summary><div style="margin-top:6px">';
        lfi_nct_avocat_render_thread((int) $t->ID, '');
        echo '</div></details>';
        echo '</li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* -------------------------------------------------------------- *
 *  Côté ADMIN : gérer les comptes avocats (liens magiques,       *
 *  email, dossiers confiés).                                      *
 * -------------------------------------------------------------- */
function lfi_nct_app_view_avocats() {
    if (!lfi_nct_avocat_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    /* Enregistrer le vrai email d'un·e avocat·e. */
    if (!empty($_POST['lfi_avocat_email']) && check_admin_referer('lfi_avocat_email')) {
        $aid = (int) ($_POST['avocat_uid'] ?? 0);
        $em  = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if ($aid && is_email($em) && lfi_nct_user_role_avocat($aid)) {
            wp_update_user(['ID' => $aid, 'user_email' => $em]);
        }
        wp_safe_redirect(lfi_nct_app_url('avocats', ['email_ok' => 1])); exit;
    }
    /* CRÉER un·e avocat·e. */
    if (!empty($_POST['lfi_avocat_create']) && check_admin_referer('lfi_avocat_crud')) {
        $nom = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
        if ($nom !== '') {
            $base = 'me.' . sanitize_title($nom);
            $login = $base; $n = 1;
            while (username_exists($login)) { $login = $base . '.' . (++$n); }
            $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            if ($email === '' || !is_email($email) || email_exists($email)) $email = $login . '@avocat.example';
            $uid = wp_insert_user(['user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password(16), 'display_name' => $nom, 'role' => LFI_NCT_ROLE_AVOCAT]);
            if (!is_wp_error($uid)) {
                update_user_meta($uid, 'lfi_nct_tel', sanitize_text_field(wp_unslash($_POST['tel'] ?? '')));
                update_user_meta($uid, 'lfi_nct_localisation', sanitize_text_field(wp_unslash($_POST['localisation'] ?? '')));
                update_user_meta($uid, 'lfi_nct_avocat_specialites', sanitize_text_field(wp_unslash($_POST['specialites'] ?? '')));
            }
        }
        wp_safe_redirect(lfi_nct_app_url('avocats', ['created' => 1])); exit;
    }
    /* ÉDITER la fiche (nom, tél, localisation, email). */
    if (!empty($_POST['lfi_avocat_edit']) && check_admin_referer('lfi_avocat_crud')) {
        $aid = (int) ($_POST['avocat_uid'] ?? 0);
        if ($aid && lfi_nct_user_role_avocat($aid)) {
            $upd = ['ID' => $aid];
            $nom = sanitize_text_field(wp_unslash($_POST['nom'] ?? ''));
            if ($nom !== '') $upd['display_name'] = $nom;
            $em = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            if ($em !== '' && is_email($em)) $upd['user_email'] = $em;
            wp_update_user($upd);
            update_user_meta($aid, 'lfi_nct_tel', sanitize_text_field(wp_unslash($_POST['tel'] ?? '')));
            update_user_meta($aid, 'lfi_nct_localisation', sanitize_text_field(wp_unslash($_POST['localisation'] ?? '')));
            update_user_meta($aid, 'lfi_nct_avocat_specialites', sanitize_text_field(wp_unslash($_POST['specialites'] ?? '')));
        }
        wp_safe_redirect(lfi_nct_app_url('avocats', ['email_ok' => 1])); exit;
    }
    /* SUPPRIMER un·e avocat·e. */
    if (!empty($_POST['lfi_avocat_delete']) && check_admin_referer('lfi_avocat_crud')) {
        $aid = (int) ($_POST['avocat_uid'] ?? 0);
        if ($aid && lfi_nct_user_role_avocat($aid) && !user_can($aid, 'manage_options')) {
            /* on désassigne d'abord les dossiers confiés. */
            foreach (lfi_nct_avocat_tenants($aid) as $t) delete_user_meta($t->ID, 'lfi_nct_avocat_uid');
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($aid);
        }
        wp_safe_redirect(lfi_nct_app_url('avocats', ['deleted' => 1])); exit;
    }
    /* Générer un lien magique (à envoyer par SMS / email / Telegram). */
    $magic = ['uid' => 0, 'link' => ''];
    if (!empty($_POST['lfi_avocat_link']) && check_admin_referer('lfi_avocat_link')) {
        $aid = (int) ($_POST['avocat_uid'] ?? 0);
        if ($aid && lfi_nct_user_role_avocat($aid) && function_exists('lfi_nct_login_link')) {
            $magic = ['uid' => $aid, 'link' => lfi_nct_login_link($aid, lfi_nct_app_url('espace'))];
        }
    }

    lfi_nct_app_screen_open('⚖️ Avocat·es partenaires', 'Confie tes dossiers, garde une ligne directe');
    if (!empty($_GET['email_ok'])) lfi_nct_app_flash('✅ Fiche enregistrée.');
    if (!empty($_GET['created']))  lfi_nct_app_flash('✅ Avocat·e ajouté·e. Renseigne son email puis génère son lien.');
    if (!empty($_GET['deleted']))  lfi_nct_app_flash('Avocat·e supprimé·e.');
    echo '<div class="lfi-app-help">Chaque avocat·e a un espace dédié : la liste des dossiers que tu lui confies, la note structurée + les pièces, et une ligne directe avec toi. <strong>Il/elle ne voit rien d\'autre</strong> (ni l\'enquête terrain, ni les autres locataires).</div>';
    echo '<div style="margin:6px 0 12px"><a class="btn-primary" style="background:#6a1b9a" href="' . esc_url(lfi_nct_app_url('avocat-invites')) . '">📨 Inviter les 2 avocat·es (liens + mail + PDF prêts)</a></div>';

    $avocats = lfi_nct_avocat_list();
    if (empty($avocats)) {
        echo '<div class="lfi-app-empty">Aucun compte avocat·e pour l\'instant. Ajoute-en un ci-dessous.</div>';
    }
    $activity = get_option('lfi_nct_avocat_activity', []);
    foreach ($avocats as $av) {
        $tenants = lfi_nct_avocat_tenants($av->ID);
        $unread  = (int) ($activity[$av->ID] ?? 0);
        $has_mail = is_email($av->user_email) && stripos($av->user_email, '@avocat.') === false;
        echo '<div class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
        echo '<div class="head"><div class="who">⚖️ ' . esc_html($av->display_name) . '</div>';
        if ($unread) echo '<div class="badge" style="background:#6a1b9a;color:#fff">💬 ' . $unread . '</div>';
        echo '</div>';
        $c_tel  = (string) get_user_meta($av->ID, 'lfi_nct_tel', true);
        $c_loc  = (string) get_user_meta($av->ID, 'lfi_nct_localisation', true);
        $c_spec = (string) get_user_meta($av->ID, 'lfi_nct_avocat_specialites', true);
        echo '<div class="meta"><span class="meta-chip">📂 ' . count($tenants) . ' dossier' . (count($tenants) > 1 ? 's' : '') . ' confié' . (count($tenants) > 1 ? 's' : '') . '</span>';
        echo '<span class="meta-chip">' . ($has_mail ? '✉️ ' . esc_html($av->user_email) : '⚠️ email à renseigner') . '</span>';
        if ($c_tel) echo '<a class="meta-chip" href="tel:' . esc_attr(preg_replace('/[^\d+]/', '', $c_tel)) . '">📞 ' . esc_html($c_tel) . '</a>';
        echo '</div>';
        if ($c_loc)  echo '<div class="com" style="font-size:.85em;color:#555">📍 ' . esc_html($c_loc) . '</div>';
        if ($c_spec) echo '<div class="com" style="font-size:.85em;color:#6a1b9a">⚖️ ' . esc_html($c_spec) . '</div>';

        echo '<div class="row-actions" style="margin-top:6px">';
        echo '<a class="btn-primary" style="background:#6a1b9a" href="' . esc_url(lfi_nct_app_url('avocat-espace', ['uid' => (int) $av->ID])) . '">Ouvrir son espace →</a>';
        echo '</div>';

        /* Éditer la fiche (nom, email, tél, localisation) — replié. */
        $tel = (string) get_user_meta($av->ID, 'lfi_nct_tel', true);
        $loc = (string) get_user_meta($av->ID, 'lfi_nct_localisation', true);
        echo '<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:700;color:#6a1b9a">✏️ Éditer la fiche</summary>';
        echo '<form method="post" class="lfi-app-form" style="margin-top:6px">' . wp_nonce_field('lfi_avocat_crud', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_avocat_edit" value="1"><input type="hidden" name="avocat_uid" value="' . (int) $av->ID . '">';
        echo '<label>Nom<input type="text" name="nom" value="' . esc_attr($av->display_name) . '"></label>';
        echo '<label>Email<input type="email" name="email" value="' . ($has_mail ? esc_attr($av->user_email) : '') . '" placeholder="email de l\'avocat·e"></label>';
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
        echo '<label style="margin:0">Téléphone<input type="tel" name="tel" value="' . esc_attr($tel) . '"></label>';
        echo '<label style="margin:0">Localisation / cabinet<input type="text" name="localisation" value="' . esc_attr($loc) . '"></label>';
        echo '</div>';
        echo '<label>Spécialités<input type="text" name="specialites" value="' . esc_attr((string) get_user_meta($av->ID, 'lfi_nct_avocat_specialites', true)) . '" placeholder="Ex : droit du logement, pénal"></label>';
        echo '<button type="submit" class="btn-primary" style="margin-top:6px">💾 Enregistrer la fiche</button></form>';
        echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Supprimer cet·te avocat·e ? Ses dossiers seront désassignés.\')">' . wp_nonce_field('lfi_avocat_crud', '_wpnonce', true, false) . '<input type="hidden" name="lfi_avocat_delete" value="1"><input type="hidden" name="avocat_uid" value="' . (int) $av->ID . '"><button type="submit" class="btn-ghost" style="color:#c8102e;font-size:.85em">🗑 Supprimer</button></form>';
        echo '</details>';

        /* Lien magique */
        echo '<form method="post" style="margin-top:6px">' . wp_nonce_field('lfi_avocat_link', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_avocat_link" value="1"><input type="hidden" name="avocat_uid" value="' . (int) $av->ID . '">';
        echo '<button type="submit" class="btn-primary" style="background:#0066a3">🔗 Générer son lien de connexion (SMS / email / Telegram)</button></form>';
        if ((int) $magic['uid'] === (int) $av->ID && $magic['link'] !== '') {
            $intro = lfi_nct_avocat_invite_text($magic['link']);
            $subj  = 'Votre espace de travail — défense des locataires (LFI Nantes Sud – Clos Toreau)';
            echo '<div class="lfi-app-help" style="margin-top:6px;background:#eef7ee;border-left:4px solid #186a3b"><small>✅ Lien généré (usage unique). Le message complet est <strong>déjà pré-rempli</strong> — clique « Envoyer par email », tu n\'as rien à écrire. Ne régénère pas après l\'envoi.</small></div>';
            echo '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">';
            if ($has_mail) echo lfi_nct_email_buttons_html($av->user_email, $subj, $intro, '✉️ Envoyer via Gmail (pré-rempli)');
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('avocat-invite-pdf', ['uid' => (int) $av->ID, 'link' => rawurlencode($magic['link'])])) . '" target="_blank">📄 Version imprimable (PDF)</a>';
            echo '</div>';
            echo '<textarea readonly onclick="this.select()" style="width:100%;height:220px;margin-top:6px;font-size:.8em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($intro) . '</textarea>';
        }
        echo '</div>';
    }

    /* ➕ Ajouter un·e avocat·e. */
    echo '<h3 style="margin:18px 0 6px">➕ Ajouter un·e avocat·e</h3>';
    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:8px">' . wp_nonce_field('lfi_avocat_crud', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_avocat_create" value="1">';
    echo '<label>Nom (ex : Me Dupont)<input type="text" name="nom" required></label>';
    echo '<label>Email<input type="email" name="email" placeholder="facultatif — tu pourras le mettre après"></label>';
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label style="margin:0">Téléphone<input type="tel" name="tel"></label>';
    echo '<label style="margin:0">Localisation / cabinet<input type="text" name="localisation"></label>';
    echo '</div>';
    echo '<button type="submit" class="btn-primary" style="background:#6a1b9a;margin-top:6px">Ajouter l\'avocat·e</button></form>';

    lfi_nct_app_screen_close();
}

/** Côté ADMIN : l'espace d'UN·E avocat·e (ses dossiers + fils de discussion). */
function lfi_nct_app_view_avocat_espace() {
    if (!lfi_nct_avocat_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $aid = (int) ($_GET['uid'] ?? 0);
    $av  = $aid ? get_userdata($aid) : null;
    if (!$av || !lfi_nct_user_role_avocat($aid)) { wp_safe_redirect(lfi_nct_app_url('avocats')); exit; }

    /* Message côté GA depuis un dossier précis. */
    if (!empty($_POST['lfi_avocat_msg'])) {
        $tid = (int) ($_POST['tenant_uid'] ?? 0);
        lfi_nct_avocat_handle_msg($tid, lfi_nct_app_url('avocat-espace', ['uid' => $aid]));
    }
    lfi_nct_avocat_activity_clear($aid);

    lfi_nct_app_screen_open('⚖️ ' . $av->display_name, 'Espace avocat·e — vu côté GA');
    echo '<div style="margin-bottom:10px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('avocats')) . '">← Tous les avocat·es</a></div>';
    if (!empty($_GET['msg'])) lfi_nct_app_flash('Message envoyé.');

    $tenants = lfi_nct_avocat_tenants($aid);
    if (empty($tenants)) {
        echo '<div class="lfi-app-empty">Aucun dossier confié à ' . esc_html($av->display_name) . '. Depuis un dossier locataire, utilise « ⚖️ Confier à un avocat ».</div>';
        lfi_nct_app_screen_close();
        return;
    }
    foreach ($tenants as $t) {
        echo '<div class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
        echo '<div class="head"><div class="who">📂 ' . esc_html($t->display_name) . '</div></div>';
        echo '<div class="row-actions" style="margin-top:6px">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('dossier', ['uid' => (int) $t->ID])) . '">Ouvrir le dossier</a>';
        echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('dossier-avocat', ['uid' => (int) $t->ID])) . '" target="_blank">📄 Note avocat</a>';
        echo '</div>';
        echo '<div style="margin-top:8px">';
        /* Fil + formulaire (le POST inclut tenant_uid). */
        echo '<form method="post" style="display:none"></form>';
        echo '<h4 style="margin:8px 0 4px;color:#6a1b9a">💬 Ligne directe (dossier ' . esc_html($t->display_name) . ')</h4>';
        $msgs = lfi_nct_avocat_thread((int) $t->ID);
        echo '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px">';
        if (empty($msgs)) echo '<div class="lfi-app-help">Aucun message.</div>';
        foreach ($msgs as $m) {
            $bg = ($m['by'] ?? '') === 'avocat' ? '#f3e9fb' : '#e7f0fb';
            echo '<div style="align-self:' . (($m['by'] ?? '') === 'ga' ? 'flex-end' : 'flex-start') . ';max-width:85%;background:' . $bg . ';border-radius:12px;padding:8px 11px">';
            echo '<div style="font-size:.72em;color:#888;font-weight:700">' . esc_html($m['name'] ?? '') . ' · ' . esc_html(wp_date('j M · H:i', strtotime($m['date'] ?? 'now'))) . '</div>';
            echo '<div style="margin-top:2px">' . nl2br(esc_html($m['msg'] ?? '')) . '</div></div>';
        }
        echo '</div>';
        echo '<form method="post" class="lfi-app-form">' . wp_nonce_field('lfi_avocat_msg', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_avocat_msg" value="1"><input type="hidden" name="tenant_uid" value="' . (int) $t->ID . '">';
        echo '<textarea name="msg" rows="2" placeholder="Répondre à ' . esc_attr($av->display_name) . '…"></textarea>';
        echo '<button type="submit" class="btn-primary" style="background:#6a1b9a">Envoyer</button></form>';
        echo '</div></div>';
    }
    lfi_nct_app_screen_close();
}

/** Texte d'invitation complet (version TEXTE — mailto / SMS), avec le lien. */
function lfi_nct_avocat_invite_text($link) {
    $t  = "Maître,\n\n";
    $t .= "Notre Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau, avec l'association Union des Quartiers Libres, accompagne gratuitement des locataires du parc social face à leur bailleur. Quand un dossier vous est confié, il arrive DÉJÀ monté : constat, chronologie, préjudice chiffré, pièces datées, base légale.\n\n";
    $t .= "== VOTRE RÔLE ==\n";
    $t .= "L'amiable d'abord : nous obtenons souvent les travaux / le relogement en urgence, puis nous négocions l'indemnisation. Si l'amiable échoue, VOUS prenez le relais devant la Commission de conciliation puis le Tribunal Judiciaire, avec un dossier prêt à plaider.\n\n";
    $t .= "== NOS FORCES (le rapport de force derrière chaque dossier) ==\n";
    $t .= "- Élus municipaux partenaires (Conseil municipal, audit de la gestion du bailleur).\n";
    $t .= "- Relais au conseil d'administration de NMH (Christophe Jouin, membre du CA — majorité).\n";
    $t .= "- Interlocuteurs directs à la préfecture (signalements, contingent préfectoral, relogement DALO).\n";
    $t .= "- Relais national : députés LFI (questions écrites, propositions de loi sur le logement).\n";
    $t .= "- SCHS / ARS (insalubrité), presse locale, mobilisation collective.\n";
    $t .= "Le juridique s'inscrit dans une stratégie d'ensemble qui pèse sur le bailleur avant même l'audience.\n\n";
    $t .= "== VOTRE ESPACE DE TRAVAIL (confidentiel) ==\n";
    $t .= "1) Connexion en 1 clic, sans rien taper, puis vous choisissez votre mot de passe.\n";
    $t .= "2) VOS dossiers uniquement : note structurée, pièces classées par date de prise de vue, dossier de conciliation déjà préparé.\n";
    $t .= "3) Jurisprudence Judilibre intégrée : les décisions liées au problème du locataire s'affichent pour vous, avec le lien officiel.\n";
    $t .= "4) Ligne directe avec le Groupe d'Action, dossier par dossier.\n\n";
    $t .= "Votre accès direct :\n" . $link . "\n\n";
    $t .= "Bien cordialement,\n";
    $t .= "Fabrice Doucet — Groupe d'Action LFI Nantes Sud – Clos Toreau · Union des Quartiers Libres";
    return $t;
}

/** Version HTML (logos + mise en forme) — pour l'email envoyé par le site + PDF. */
function lfi_nct_avocat_invite_html($link, $nom = 'Maître') {
    $logos = function_exists('lfi_nct_signature_logos_html') ? lfi_nct_signature_logos_html('avocat', 'center') : '';
    $btn = $link ? '<div style="text-align:center;margin:22px 0"><a href="' . esc_url($link) . '" style="background:#6a1b9a;color:#fff;font-weight:800;padding:14px 28px;border-radius:12px;text-decoration:none;font-size:1.05em">🔓 Accéder à mon espace de travail</a></div><div style="text-align:center;font-size:.8em;color:#888;word-break:break-all">' . esc_html($link) . '</div>' : '';
    ob_start(); ?>
    <div style="font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1a1a1a;max-width:640px;margin:0 auto;line-height:1.55">
      <?php echo $logos; ?>
      <div style="text-align:center;border-bottom:3px solid #6a1b9a;padding-bottom:10px;margin-bottom:18px">
        <div style="font-weight:900;color:#6a1b9a;font-size:1.15em">Défense des locataires — votre espace de travail</div>
        <div style="color:#666;font-size:.9em">Groupe d'Action LFI Nantes Sud – Clos Toreau · Union des Quartiers Libres</div>
      </div>
      <p><strong><?php echo esc_html($nom); ?>,</strong></p>
      <p>Notre Groupe d'Action, avec l'association <strong>Union des Quartiers Libres</strong>, accompagne gratuitement des locataires du parc social face à leur bailleur. Quand un dossier vous est confié, il arrive <strong>déjà monté</strong> : constat, chronologie, préjudice chiffré, pièces datées, base légale.</p>

      <h3 style="color:#c8102e;margin:18px 0 6px">⚖️ Votre rôle</h3>
      <p>L'<strong>amiable d'abord</strong> : nous obtenons souvent les travaux ou le relogement en urgence, puis nous négocions l'indemnisation. <strong>Si l'amiable échoue, vous prenez le relais</strong> devant la <strong>Commission de conciliation</strong> puis le <strong>Tribunal Judiciaire</strong> — avec un dossier prêt à plaider.</p>

      <h3 style="color:#186a3b;margin:18px 0 6px">💪 Nos forces (le rapport de force derrière chaque dossier)</h3>
      <ul style="margin:6px 0;padding-left:20px">
        <li>🏛️ <strong>Élus municipaux</strong> partenaires (Conseil municipal, audit de la gestion du bailleur)</li>
        <li>🏢 <strong>Relais au conseil d'administration de NMH</strong> (Christophe Jouin, membre du CA — majorité)</li>
        <li>🏛️ <strong>Interlocuteurs directs à la préfecture</strong> (signalements, contingent préfectoral, relogement DALO)</li>
        <li>🇫🇷 <strong>Relais national</strong> : députés LFI (questions écrites, propositions de loi sur le logement)</li>
        <li>🏥 <strong>SCHS / ARS</strong> (insalubrité), <strong>presse locale</strong>, <strong>mobilisation collective</strong></li>
      </ul>
      <p style="color:#555">Le juridique s'inscrit dans une <strong>stratégie d'ensemble</strong> qui pèse sur le bailleur avant même l'audience.</p>

      <h3 style="color:#6a1b9a;margin:18px 0 6px">🗂️ Votre espace (confidentiel)</h3>
      <ol style="margin:6px 0;padding-left:20px">
        <li>Connexion en <strong>1 clic</strong>, sans rien taper, puis vous <strong>choisissez votre mot de passe</strong>.</li>
        <li><strong>Vos dossiers uniquement</strong> : note structurée, pièces classées par date de prise de vue, dossier de conciliation déjà préparé.</li>
        <li><strong>Jurisprudence Judilibre intégrée</strong> : les décisions liées au problème du locataire s'affichent pour vous, avec le lien officiel.</li>
        <li><strong>Ligne directe</strong> avec le Groupe d'Action, dossier par dossier.</li>
      </ol>

      <?php echo $btn; ?>

      <p style="margin-top:18px">Bien cordialement,<br>
      <strong>Fabrice Doucet</strong><br>
      Groupe d'Action La France Insoumise Nantes Sud – Clos Toreau<br>
      Union des Quartiers Libres</p>
    </div>
    <?php
    return ob_get_clean();
}

/** Page « invitations » : les DEUX avocats prêts à envoyer (lien + texte + PDF). */
function lfi_nct_app_view_avocat_invites() {
    if (!lfi_nct_avocat_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    lfi_nct_app_screen_open('📨 Inviter les avocat·es', 'Tout est prêt — un envoi par avocat·e');
    if (isset($_GET['sent'])) lfi_nct_app_flash((int) $_GET['sent'] ? '✅ Email (logos + mise en forme) envoyé.' : '⚠️ Envoi impossible (email manquant ou serveur mail). Utilise « version texte » en attendant.', (int) $_GET['sent'] ? 'ok' : 'error');
    echo '<div class="lfi-app-help">Pour chaque avocat·e, 3 options : <strong>📧 email mis en forme</strong> (logos + rôle + forces, envoyé par le site — recommandé), <strong>📄 aperçu/PDF</strong>, ou <strong>✉️ version texte</strong> (depuis ton Gmail). <strong>Ne régénère pas</strong> la page après envoi (ça crée un nouveau lien).</div>';
    $avocats = lfi_nct_avocat_list();
    if (empty($avocats)) { echo '<div class="lfi-app-empty">Aucun·e avocat·e. Ajoute-les depuis « ⚖️ Avocat·es partenaires ».</div>'; lfi_nct_app_screen_close(); return; }
    $space = function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/');
    foreach ($avocats as $av) {
        $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link((int) $av->ID, $space) : $space;
        $txt  = lfi_nct_avocat_invite_text($link);
        $mail = sanitize_email((string) $av->user_email);
        $has_mail = ($mail !== '' && is_email($mail) && stripos($mail, '@avocat.') === false);
        $subj = 'Votre espace de travail — défense des locataires (LFI Nantes Sud – Clos Toreau)';
        echo '<div class="lfi-app-card" style="border-left:4px solid #6a1b9a">';
        echo '<div class="head"><div class="who">⚖️ ' . esc_html($av->display_name) . '</div>';
        echo '<div class="badge">' . ($has_mail ? esc_html($mail) : '⚠️ email à renseigner') . '</div></div>';
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin:6px 0">';
        if ($has_mail) {
            /* Recommandé : envoi HTML depuis le site (logos + mise en forme). */
            $send = wp_nonce_url(admin_url('admin-post.php?action=lfi_nct_avocat_send_html&uid=' . (int) $av->ID), 'lfi_nct_avocat_send_html_' . (int) $av->ID);
            echo '<a class="btn-primary" style="background:#186a3b" href="' . esc_url($send) . '" onclick="return confirm(\'Envoyer l\\\'email mis en forme (logos + rôle + forces) à ' . esc_js($mail) . ' ?\')">📧 Envoyer l\'email (logos + mise en forme)</a>';
        }
        echo '<a class="btn-ghost" style="background:#6a1b9a;color:#fff" href="' . esc_url(lfi_nct_app_url('avocat-invite-pdf', ['uid' => (int) $av->ID, 'link' => rawurlencode($link)])) . '" target="_blank">📄 Aperçu / PDF</a>';
        if ($has_mail) echo lfi_nct_email_buttons_html($mail, $subj, $txt, '✉️ Envoyer via Gmail');
        echo '</div>';
        echo '<div class="lfi-app-help" style="margin:2px 0"><small>🔗 Lien de connexion direct (copie-le pour SMS / Telegram) :</small></div>';
        echo '<textarea readonly onclick="this.select()" style="width:100%;height:44px;font-size:.78em;padding:6px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($link) . '</textarea>';
        echo '<details style="margin-top:6px"><summary style="cursor:pointer;font-weight:700;color:#6a1b9a">📄 Voir le message complet</summary>';
        echo '<textarea readonly onclick="this.select()" style="width:100%;height:220px;margin-top:6px;font-size:.8em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($txt) . '</textarea></details>';
        echo '</div>';
    }
    lfi_nct_app_screen_close();
}

/** Version imprimable (PDF via impression navigateur) de l'invitation. */
function lfi_nct_app_view_avocat_invite_pdf() {
    if (!lfi_nct_avocat_can()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $aid  = (int) ($_GET['uid'] ?? 0);
    $av   = $aid ? get_userdata($aid) : null;
    $link = isset($_GET['link']) ? esc_url_raw(rawurldecode((string) $_GET['link'])) : '';
    if (!$av || !lfi_nct_user_role_avocat($aid)) { wp_safe_redirect(lfi_nct_app_url('avocats')); exit; }
    $html = lfi_nct_avocat_invite_html($link, $av->display_name ?: 'Maître');
    nocache_headers();
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Invitation — <?php echo esc_html($av->display_name); ?></title>
    <style>@media print{.noprint{display:none}} body{margin:24px auto;padding:0 16px}</style>
    </head><body>
    <?php echo $html; ?>
    <div class="noprint" style="text-align:center;margin:24px 0"><button onclick="window.print()" style="background:#6a1b9a;color:#fff;border:0;padding:12px 24px;border-radius:10px;font-weight:800;cursor:pointer;font-size:1em">🖨️ Imprimer / Enregistrer en PDF</button></div>
    </body></html><?php
    exit;
}

/* Envoi de l'email HTML (logos + mise en forme) directement depuis le site. */
add_action('admin_post_lfi_nct_avocat_send_html', 'lfi_nct_avocat_send_html_handler');
function lfi_nct_avocat_send_html_handler() {
    if (!lfi_nct_avocat_can()) wp_die('non');
    $aid = (int) ($_GET['uid'] ?? 0);
    if (!check_admin_referer('lfi_nct_avocat_send_html_' . $aid)) wp_die('non');
    $av = $aid ? get_userdata($aid) : null;
    $ok = 0;
    if ($av && lfi_nct_user_role_avocat($aid) && is_email($av->user_email) && stripos($av->user_email, '@avocat.') === false) {
        $link = function_exists('lfi_nct_login_link') ? lfi_nct_login_link($aid, function_exists('lfi_nct_app_page_url') ? lfi_nct_app_page_url() : home_url('/app/')) : home_url('/app/');
        $html = lfi_nct_avocat_invite_html($link, $av->display_name ?: 'Maître');
        $me = wp_get_current_user();
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (is_email($me->user_email)) $headers[] = 'Reply-To: ' . $me->display_name . ' <' . $me->user_email . '>';
        $ok = wp_mail($av->user_email, 'Votre espace de travail — défense des locataires (LFI Nantes Sud – Clos Toreau)', $html, $headers) ? 1 : 0;
    }
    wp_safe_redirect(lfi_nct_app_url('avocat-invites', ['sent' => $ok, 'uid' => $aid]));
    exit;
}

/* -------------------------------------------------------------- *
 *  SEED : Me Valet & Me Goache (une fois, au déploiement).        *
 * -------------------------------------------------------------- */
add_action('init', 'lfi_nct_avocat_seed', 12);
function lfi_nct_avocat_seed() {
    if (get_option('lfi_nct_avocat_seed_done')) return;
    if (!get_role(LFI_NCT_ROLE_AVOCAT)) return;
    $seeds = [
        'valet'  => ['login' => 'me.valet',  'display' => 'Me Valet',  'last' => 'Valet'],
        'goache' => ['login' => 'me.goache', 'display' => 'Me Goache', 'last' => 'Goache'],
    ];
    foreach ($seeds as $key => $s) {
        $already = get_users(['meta_key' => 'lfi_nct_avocat_seed', 'meta_value' => $key, 'number' => 1, 'fields' => 'ID']);
        if (!empty($already)) continue;
        $login = username_exists($s['login']) ? $s['login'] . '.lfi' : $s['login'];
        $email = $s['login'] . '@avocat.example'; /* provisoire — l'admin met le vrai */
        if (username_exists($login) || email_exists($email)) continue;
        $uid = wp_insert_user([
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(16),
            'display_name' => $s['display'],
            'last_name'    => $s['last'],
            'role'         => LFI_NCT_ROLE_AVOCAT,
        ]);
        if (!is_wp_error($uid)) update_user_meta($uid, 'lfi_nct_avocat_seed', $key);
    }
    update_option('lfi_nct_avocat_seed_done', 1, false);
}

/* HEAL (une fois) : les vraies fiches des deux avocats du barreau de Nantes.
   « Valet » = Me Stéphane VALLÉE (Cabinet 333) ; « Goache » = Me Maxime GOUACHE
   (Cabinet Poquet Gouache). Infos publiques (barreau / cabinets). */
add_action('init', 'lfi_nct_avocat_heal_real_info', 13);
function lfi_nct_avocat_heal_real_info() {
    if (get_option('lfi_nct_avocat_real_info_v1')) return;
    if (!get_role(LFI_NCT_ROLE_AVOCAT)) return;
    $real = [
        'goache' => [
            'display' => 'Me Maxime Gouache', 'first' => 'Maxime', 'last' => 'Gouache',
            'email' => 'secretariat@poquetgouache-avocats.fr', 'tel' => '02 40 69 16 18',
            'localisation' => 'Cabinet Poquet Gouache Avocats — 4 rue Racine, 44000 Nantes',
            'specialites' => 'Droit du logement, contentieux',
        ],
        'valet' => [
            'display' => 'Me Stéphane Vallée', 'first' => 'Stéphane', 'last' => 'Vallée',
            'email' => 'stephane.vallee@avocat.fr', 'tel' => '02 40 20 00 22',
            'localisation' => 'Cabinet d\'Avocats 333 — 14 bd Gabriel Guist\'hau, 44000 Nantes',
            'specialites' => 'Droit du logement, droit pénal, droit des personnes, consommation',
        ],
    ];
    foreach ($real as $key => $r) {
        $found = get_users(['meta_key' => 'lfi_nct_avocat_seed', 'meta_value' => $key, 'number' => 1, 'fields' => 'ID']);
        if (empty($found)) continue;
        $uid = (int) $found[0];
        $upd = ['ID' => $uid, 'display_name' => $r['display'], 'first_name' => $r['first'], 'last_name' => $r['last']];
        /* On ne met le vrai email que si l'actuel est encore le provisoire. */
        $cur = get_userdata($uid);
        if ($cur && (stripos($cur->user_email, '@avocat.example') !== false) && !email_exists($r['email'])) {
            $upd['user_email'] = $r['email'];
        }
        wp_update_user($upd);
        update_user_meta($uid, 'lfi_nct_tel', $r['tel']);
        update_user_meta($uid, 'lfi_nct_localisation', $r['localisation']);
        update_user_meta($uid, 'lfi_nct_avocat_specialites', $r['specialites']);
    }
    update_option('lfi_nct_avocat_real_info_v1', 1, false);
    delete_transient('lfi_nct_inbox_tenant_index');
}

/* CORRECTION (une fois) : l'email de Me Gouache avait été renseigné avec une
   adresse CONSTRUITE non vérifiée (mg@poquetgouache-avocats.fr). L'adresse
   PUBLIQUE vérifiée du cabinet est secretariat@poquetgouache-avocats.fr. On ne
   corrige QUE si l'adresse enregistrée est encore cette valeur inventée (ou le
   provisoire) — jamais ce que l'admin a saisi lui-même. */
add_action('init', 'lfi_nct_avocat_fix_gouache_email', 14);
function lfi_nct_avocat_fix_gouache_email() {
    if (get_option('lfi_nct_avocat_gouache_email_v2')) return;
    if (!get_role(LFI_NCT_ROLE_AVOCAT)) return;
    $found = get_users(['meta_key' => 'lfi_nct_avocat_seed', 'meta_value' => 'goache', 'number' => 1, 'fields' => 'ID']);
    if (!empty($found)) {
        $uid = (int) $found[0];
        $cur = get_userdata($uid);
        $bad = ['mg@poquetgouache-avocats.fr', 'goache@avocat.example', 'me.goache@avocat.example'];
        $good = 'secretariat@poquetgouache-avocats.fr';
        if ($cur && in_array(strtolower((string) $cur->user_email), $bad, true) && !email_exists($good)) {
            wp_update_user(['ID' => $uid, 'user_email' => $good]);
            delete_transient('lfi_nct_inbox_tenant_index');
        }
    }
    update_option('lfi_nct_avocat_gouache_email_v2', 1, false);
}
