<?php
/**
 * MULTI-GA — cloisonnement des données par groupe d'action.
 *
 * Principe : toutes les requêtes « brigade » passent par un IDENTIFIANT
 * PROPRIÉTAIRE unique (lfi_nct_brigade_owner_id). On le rend ici « conscient
 * du GA » : chaque GA a un compte PIVOT ; tous les membres d'un GA partagent
 * les données de ce pivot et ne voient QUE ça. Toi (super-admin) tu vois ton
 * espace et tu peux « voir comme » n'importe quel GA pour les piloter / agréger.
 *
 * SÉCURITÉ : tant qu'aucun pivot/affectation n'est configuré, le comportement
 * est STRICTEMENT identique à aujourd'hui (repli sur l'identifiant courant).
 */
if (!defined('ABSPATH')) exit;

/** Super-admin (toi) : voit tout, peut basculer de GA. */
function lfi_nct_super_admin() {
    return current_user_can('manage_options');
}

/** Pivots des GA : [slug => user_id]. */
function lfi_nct_ga_pivots() {
    $p = get_option('lfi_nct_ga_pivots', []);
    return is_array($p) ? $p : [];
}
function lfi_nct_ga_pivot_uid($slug) {
    $p = lfi_nct_ga_pivots();
    return isset($p[$slug]) ? (int) $p[$slug] : 0;
}

/** Binôme paritaire d'admins par GA : [slug => ['f' => uid, 'h' => uid]]. */
function lfi_nct_ga_admins() {
    $a = get_option('lfi_nct_ga_admins', []);
    return is_array($a) ? $a : [];
}
function lfi_nct_ga_admin_pair($slug) {
    $a = lfi_nct_ga_admins();
    return ['f' => (int) ($a[$slug]['f'] ?? 0), 'h' => (int) ($a[$slug]['h'] ?? 0)];
}

/** GA d'un utilisateur (membre) — user_meta lfi_nct_ga. */
function lfi_nct_user_ga($uid = null) {
    $uid = $uid ?: get_current_user_id();
    return (string) get_user_meta((int) $uid, 'lfi_nct_ga', true);
}

/** GA actuellement « regardé » par le super-admin ('' ou '__all__' = son espace). */
function lfi_nct_view_ga() {
    if (!lfi_nct_super_admin()) return '';
    return (string) get_user_meta(get_current_user_id(), 'lfi_nct_view_ga', true);
}

/**
 * Propriétaire « fantôme » propre à un GA sans pivot : un identifiant haut et
 * UNIQUE par slug (au-dessus de tout vrai user_id). Ainsi l'espace d'un GA non
 * configuré est vide en lecture, et si on y saisit des données elles restent à
 * CE GA (pas de mélange entre deux GA non configurés).
 */
function lfi_nct_ga_phantom_owner($slug) {
    return 1000000000 + (abs(crc32((string) $slug)) % 900000000);
}

/**
 * Résout l'identifiant propriétaire en tenant compte du GA.
 *
 * IMPORTANT : quand le super-admin « regarde » un autre GA, il ne doit JAMAIS
 * voir ses propres données. Si ce GA n'a pas encore de compte pivot (binôme non
 * désigné), on renvoie un propriétaire fantôme → son espace est VIDE, au lieu de
 * retomber sur les données du super-admin.
 */
function lfi_nct_ga_owner_resolve($base) {
    $base = (int) $base;
    if (lfi_nct_super_admin()) {
        $vg = lfi_nct_view_ga();
        if ($vg !== '' && $vg !== '__all__') {
            $p = lfi_nct_ga_pivot_uid($vg);
            return $p ? (int) $p : lfi_nct_ga_phantom_owner($vg); // autre GA → pivot, sinon espace vide propre à ce GA
        }
        return $base; // « tout » / mon espace → mes données
    }
    $ga = lfi_nct_user_ga($base);
    if ($ga !== '') {
        $p = lfi_nct_ga_pivot_uid($ga);
        if ($p) return $p;
    }
    return $base;
}

/**
 * Clause SQL pour cloisonner par PROPRIÉTAIRE (agenda RDV, etc.).
 * Sur l'espace home (Clos Toreau / mon espace), on inclut aussi les
 * enregistrements historiques sans propriétaire (NULL/0) pour ne rien perdre.
 */
function lfi_nct_owner_clause($col = 'owner_user_id') {
    $owner = function_exists('lfi_nct_brigade_owner_id') ? (int) lfi_nct_brigade_owner_id() : (int) get_current_user_id();
    $slug  = lfi_nct_scope_ga_slug();
    $is_home = ($slug === '' || $slug === 'clos-toreau');
    if ($is_home) return " AND ($col = $owner OR $col IS NULL OR $col = 0)";
    return " AND $col = $owner";
}

/** Admins supplémentaires d'un GA (promus par un admin du GA) : [slug => [uid,…]]. */
function lfi_nct_ga_extra_admins($slug = null) {
    $all = get_option('lfi_nct_ga_xadmins', []);
    if (!is_array($all)) $all = [];
    if ($slug === null) return $all;
    return array_map('intval', (array) ($all[$slug] ?? []));
}

/** Liste complète des uid admins d'un GA : binôme paritaire + admins promus. */
function lfi_nct_ga_admin_uids($slug) {
    $uids = [];
    if (function_exists('lfi_nct_ga_admin_pair')) {
        $pair = lfi_nct_ga_admin_pair($slug);
        if (!empty($pair['f'])) $uids[] = (int) $pair['f'];
        if (!empty($pair['h'])) $uids[] = (int) $pair['h'];
    }
    foreach (lfi_nct_ga_extra_admins($slug) as $u) $uids[] = (int) $u;
    return array_values(array_unique(array_filter($uids)));
}

/** Peut gérer SON GA : super-admin, membre du binôme, ou admin promu du GA. */
function lfi_nct_is_ga_admin() {
    if (current_user_can('manage_options')) return true;
    $ga = lfi_nct_user_ga();
    if ($ga === '') return false;
    return in_array((int) get_current_user_id(), lfi_nct_ga_admin_uids($ga), true);
}

/**
 * Peut piloter l'espace en cours comme un admin (super-admin OU admin du GA).
 * Sert à ouvrir aux binômes l'ensemble des outils, données cloisonnées.
 */
function lfi_nct_can_admin_ga() {
    return lfi_nct_is_ga_admin();
}

/** Vrai si l'utilisateur $uid appartient au GA actuellement en vigueur. */
function lfi_nct_uid_in_scope($uid) {
    $slug = lfi_nct_scope_ga_slug();
    if ($slug === '') return true; // vue « tout » : tout est permis
    return (string) get_user_meta((int) $uid, 'lfi_nct_ga', true) === $slug;
}

/** GA effectivement « en vigueur » ('' = tout, pour le super-admin sans bascule). */
function lfi_nct_scope_ga_slug() {
    if (lfi_nct_super_admin()) {
        $vg = lfi_nct_view_ga();
        return ($vg !== '' && $vg !== '__all__') ? $vg : '';
    }
    return lfi_nct_user_ga();
}

/** Identifiants des militants d'un GA (membres + compte pivot). */
function lfi_nct_ga_member_ids($slug) {
    if ($slug === '') return null;
    $ids = [];
    $piv = lfi_nct_ga_pivot_uid($slug);
    if ($piv) $ids[] = (int) $piv;
    $us = get_users(['meta_key' => 'lfi_nct_ga', 'meta_value' => $slug, 'fields' => ['ID'], 'number' => 1000]);
    foreach ((array) $us as $u) $ids[] = (int) (is_object($u) ? $u->ID : $u);
    return array_values(array_unique(array_filter($ids)));
}

/**
 * Fragment SQL pour cloisonner les réponses d'enquête par GA.
 * '' (super-admin, vue « tout ») → aucun filtre. GA sans membre → ne montre rien.
 * Les identifiants sont des entiers assainis : concaténation SQL sûre.
 */
function lfi_nct_responses_scope_clause($col = 'militant_user_id') {
    $slug = lfi_nct_scope_ga_slug();
    if ($slug === '') return '';
    $ids = lfi_nct_ga_member_ids($slug);
    if (empty($ids)) return ' AND 1=0';
    return ' AND ' . $col . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
}

/**
 * Ajoute le filtre GA à un get_users.
 *  - Sur l'espace d'un autre GA : uniquement ses membres (meta = slug).
 *  - Sur l'espace home (Clos Toreau) : on EXCLUT les membres rattachés à un
 *    autre GA (sinon le super-admin verrait, dans SON espace, les membres
 *    qu'il a créés pour les autres GA). On garde ceux sans rattachement,
 *    vides, ou explicitement « clos-toreau ».
 */
function lfi_nct_users_ga_query($args = []) {
    $slug = lfi_nct_scope_ga_slug();
    $mq   = (isset($args['meta_query']) && is_array($args['meta_query'])) ? $args['meta_query'] : [];
    if ($slug !== '') {
        $mq[] = ['key' => 'lfi_nct_ga', 'value' => $slug];
    } else {
        $mq[] = [
            'relation' => 'OR',
            ['key' => 'lfi_nct_ga', 'compare' => 'NOT EXISTS'],
            ['key' => 'lfi_nct_ga', 'value' => '',            'compare' => '='],
            ['key' => 'lfi_nct_ga', 'value' => 'clos-toreau', 'compare' => '='],
        ];
    }
    $args['meta_query'] = $mq;
    return $args;
}

/** GA à attribuer aux comptes/adhérents créés ('' = espace home, pas d'attribution). */
function lfi_nct_creation_ga() {
    return lfi_nct_scope_ga_slug();
}

/**
 * Clause SQL pour cloisonner la table des adhérents par GA (colonne `ga`).
 *  - Autre GA : uniquement ses adhérents.
 *  - Home (Clos Toreau) : on exclut les adhérents rattachés à un autre GA
 *    (ga = '' = adhérents historiques, ou ga = 'clos-toreau').
 */
function lfi_nct_membres_ga_clause($col = 'ga') {
    $slug = lfi_nct_scope_ga_slug();
    if ($slug === '') return " AND ($col = '' OR $col = 'clos-toreau')";
    return " AND $col = '" . esc_sql($slug) . "'";
}

/* ============================================================== *
 *  Bascule de GA (super-admin) : ?vue=voir-ga&ga=slug             *
 * ============================================================== */
function lfi_nct_app_view_voir_ga() {
    if (!lfi_nct_super_admin()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $ga = isset($_GET['ga']) ? sanitize_title(wp_unslash($_GET['ga'])) : '';
    update_user_meta(get_current_user_id(), 'lfi_nct_view_ga', $ga);
    /* Destination optionnelle après bascule (ex. aller direct aux comptes du GA). */
    $then  = isset($_GET['then']) ? sanitize_key(wp_unslash($_GET['then'])) : '';
    $allow = ['comptes-ga', 'membres', 'carte', 'stats-enquete', 'reseau-ga', 'enquetes'];
    $dest  = in_array($then, $allow, true) ? lfi_nct_app_url($then) : lfi_nct_app_url();
    wp_safe_redirect($dest);
    exit;
}

/** Sélecteur de GA en haut de l'accueil (super-admin uniquement). */
function lfi_nct_render_ga_switcher() {
    if (!lfi_nct_super_admin() || !function_exists('lfi_nct_groupes')) return;
    $groupes = lfi_nct_groupes();
    if (count($groupes) < 2) return; // un seul GA : inutile
    $cur = lfi_nct_view_ga();
    echo '<div class="lfi-app-gaswitch" style="background:#fff;border:1.5px solid #c8102e;border-radius:10px;padding:8px 12px;margin:10px 0;font-size:.92em">';
    echo '<label style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><span style="font-weight:700;color:#c8102e">👁 Espace affiché :</span>';
    echo '<select onchange="location.href=this.value" style="flex:1;min-width:180px">';
    $own = ($cur === '' || $cur === '__all__');
    echo '<option value="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => '__all__'])) . '"' . ($own ? ' selected' : '') . '>Mon espace (Clos Toreau)</option>';
    foreach ($groupes as $g) {
        if (!empty($g['actuel'])) continue;
        $sel = ($cur === $g['slug']) ? ' selected' : '';
        $piv = lfi_nct_ga_pivot_uid($g['slug']);
        $label = $g['nom'] . ($piv ? '' : ' — (pivot à configurer)');
        echo '<option value="' . esc_url(lfi_nct_app_url('voir-ga', ['ga' => $g['slug']])) . '"' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label>';
    if ($cur !== '' && $cur !== '__all__') {
        echo '<div style="margin-top:4px;color:#555;font-size:.85em">Tu regardes l\'espace d\'un autre GA. Repasse sur « Mon espace » pour revenir.</div>';
    }
    echo '</div>';
}
