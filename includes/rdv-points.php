<?php
/**
 * POINTS DE RENDEZ-VOUS PAR GA — menu déroulant qui APPREND.
 *
 * Objectif : que le référent tape le moins possible. Chaque GA a ses points de
 * rendez-vous habituels (Clos Toreau = Place du Pays Basque, place centrale du
 * quartier ; un autre GA = tel bar, tel local…). On propose ces points en
 * liste déroulante, on pré-remplit le point principal, et on APPREND les
 * nouveaux au fil des saisies.
 */
if (!defined('ABSPATH')) exit;

/** Points par défaut connus (point central du quartier), par GA. */
function lfi_nct_rdv_defaults() {
    return apply_filters('lfi_nct_rdv_defaults', [
        'clos-toreau' => ['Place du Pays Basque'],
    ]);
}

/** Slug du GA courant (contexte de création). */
function lfi_nct_rdv_ga($ga = '') {
    if ($ga !== '') return $ga;
    $s = function_exists('lfi_nct_creation_ga') ? (string) lfi_nct_creation_ga() : '';
    return $s !== '' ? $s : 'clos-toreau';
}

/** Liste des points de RDV d'un GA (défauts + appris), dédupliquée. */
function lfi_nct_rdv_points($ga = '') {
    $ga = lfi_nct_rdv_ga($ga);
    $defaults = lfi_nct_rdv_defaults();
    $def = !empty($defaults[$ga]) && is_array($defaults[$ga]) ? $defaults[$ga] : [];
    $store = get_option('lfi_nct_ga_rdv_points', []);
    $learned = (is_array($store) && !empty($store[$ga]) && is_array($store[$ga])) ? $store[$ga] : [];
    $all = array_merge($def, $learned);
    $out = []; $seen = [];
    foreach ($all as $p) {
        $p = trim((string) $p);
        if ($p === '') continue;
        $k = mb_strtolower($p);
        if (isset($seen[$k])) continue;
        $seen[$k] = 1; $out[] = $p;
    }
    return $out;
}

/** Point principal d'un GA (à pré-remplir), ou ''. */
function lfi_nct_rdv_primary($ga = '') {
    $pts = lfi_nct_rdv_points($ga);
    return $pts ? $pts[0] : '';
}

/** Apprend un nouveau point de RDV pour le GA (appelé à l'enregistrement). */
function lfi_nct_rdv_learn($point, $ga = '') {
    $point = trim(sanitize_text_field((string) $point));
    if (mb_strlen($point) < 3) return;
    $ga = lfi_nct_rdv_ga($ga);
    /* Ne pas ré-apprendre un point déjà connu (défaut ou appris). */
    foreach (lfi_nct_rdv_points($ga) as $p) if (mb_strtolower($p) === mb_strtolower($point)) return;
    $store = get_option('lfi_nct_ga_rdv_points', []);
    if (!is_array($store)) $store = [];
    $cur = (isset($store[$ga]) && is_array($store[$ga])) ? $store[$ga] : [];
    $cur[] = $point;
    if (count($cur) > 40) $cur = array_slice($cur, -40);
    $store[$ga] = array_values($cur);
    update_option('lfi_nct_ga_rdv_points', $store, false);
}

/** Rend un <datalist> (suggestions) pour un id donné, à partir des points du GA. */
function lfi_nct_rdv_datalist($id, $ga = '') {
    $h = '<datalist id="' . esc_attr($id) . '">';
    foreach (lfi_nct_rdv_points($ga) as $p) $h .= '<option value="' . esc_attr($p) . '"></option>';
    $h .= '</datalist>';
    return $h;
}
