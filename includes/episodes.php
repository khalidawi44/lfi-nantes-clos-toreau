<?php
/**
 * ÉPISODES / INCIDENTS — dossiers d'incident SÉPARÉS et CLOISONNÉS.
 *
 *  Un même locataire peut vivre PLUSIEURS troubles distincts dans le temps
 *  (ex. Fabrice Doucet : infestation 2020, infestation 2024, infestation 2025).
 *  Chacun est un « épisode » = un dossier d'incident à part, avec SON PROPRE
 *  parcours (urgence → amiable → « clore le dossier urgence »).
 *
 *  Le dossier JURIDIQUE, lui, reste global et ouvert : on additionne le
 *  préjudice de tous les épisodes pour demander une indemnité GLOBALE (voir
 *  prejudice.php / dossiers). Un épisode « urgence close » alimente quand même
 *  le juridique tant que celui-ci n'est pas clôturé à la main.
 *
 *  ── Architecture (sûre, non destructive) ──
 *  Le parcours de l'épisode ACTIF est stocké dans le meta historique
 *  `lfi_nct_suivi_steps` : tout le code existant (parcours, suivi locataire,
 *  contributions, victoires, relogement) continue de fonctionner tel quel.
 *  Les autres épisodes gardent leur parcours « en réserve » dans
 *  `lfi_nct_episodes`. Changer d'épisode = ENREGISTRER l'actif puis CHARGER le
 *  demandé (atomique : aucune perte de données).
 */
if (!defined('ABSPATH')) exit;

/* Types d'incident proposés (icône + libellé). */
function lfi_nct_episode_types() {
    return [
        'infestation' => ['🐛', 'Infestation (punaises, cafards, nuisibles…)'],
        'fuite'       => ['💧', 'Fuite / dégât des eaux'],
        'moisissure'  => ['🦠', 'Moisissures / humidité'],
        'chauffage'   => ['🔥', 'Chauffage / eau chaude'],
        'electricite' => ['⚡', 'Électricité / sécurité'],
        'menuiserie'  => ['🚪', 'Fenêtres / portes / menuiseries'],
        'parties'     => ['🏢', 'Parties communes / ascenseur'],
        'autre'       => ['🏠', 'Autre trouble'],
    ];
}

function lfi_nct_episodes_get($uid) {
    $v = get_user_meta((int) $uid, 'lfi_nct_episodes', true);
    return is_array($v) ? $v : [];
}
function lfi_nct_episodes_save($uid, $list) {
    update_user_meta((int) $uid, 'lfi_nct_episodes', array_values($list));
}
function lfi_nct_episode_active_id($uid) {
    return (int) get_user_meta((int) $uid, 'lfi_nct_active_ep', true);
}
function lfi_nct_episode_new_id() {
    return (int) (round(microtime(true) * 1000) % 1000000000);
}

/** Étapes d'un NOUVEL épisode : parcours-type (urgence → amiable) + clôture. */
function lfi_nct_episode_seed_steps() {
    $steps = [];
    if (function_exists('lfi_nct_dossier_parcours_template')) {
        foreach (lfi_nct_dossier_parcours_template() as $tpl) {
            $steps[] = ['text' => $tpl['text'], 'who' => $tpl['who'], 'auto' => !empty($tpl['auto']), 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
        }
    }
    $steps[] = ['text' => "✅ Clore le dossier URGENCE (le trouble immédiat est réglé)", 'who' => 'admin', 'done' => false, 'echeance' => '', 'created' => current_time('Y-m-d')];
    return $steps;
}

/** Garantit qu'au moins UN épisode existe (migration douce depuis le parcours
 *  plat historique). Renvoie la liste. Idempotent. */
function lfi_nct_episodes_ensure($uid) {
    $uid = (int) $uid;
    $list = lfi_nct_episodes_get($uid);
    if (!empty($list)) return $list;
    $steps = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($steps)) $steps = [];
    $id = lfi_nct_episode_new_id();
    $list = [[
        'id' => $id, 'titre' => 'Dossier principal', 'type' => '', 'piece' => '',
        'ouvert' => current_time('Y-m-d'), 'clos_urgence' => false, 'clos_date' => '',
        'steps' => $steps, 'prejudice' => [],
    ]];
    lfi_nct_episodes_save($uid, $list);
    update_user_meta($uid, 'lfi_nct_active_ep', $id);
    return $list;
}

/** Persiste le parcours plat courant dans l'épisode ACTIF (snapshot à jour). */
function lfi_nct_episode_save_active($uid) {
    $uid = (int) $uid;
    $active = lfi_nct_episode_active_id($uid);
    if (!$active) return;
    $list = lfi_nct_episodes_get($uid);
    $cur = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($cur)) $cur = [];
    $found = false;
    foreach ($list as $i => $e) {
        if ((int) ($e['id'] ?? 0) === $active) { $list[$i]['steps'] = $cur; $found = true; break; }
    }
    if ($found) lfi_nct_episodes_save($uid, $list);
}

/** Change d'épisode actif : ENREGISTRE l'actif, puis CHARGE l'épisode demandé.
 *  Aucune perte : save avant load. No-op si déjà actif. */
function lfi_nct_episode_switch($uid, $to) {
    $uid = (int) $uid; $to = (int) $to;
    $list = lfi_nct_episodes_ensure($uid);
    $active = lfi_nct_episode_active_id($uid);
    if ($to === $active) return true;
    $cur = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($cur)) $cur = [];
    $target = null;
    foreach ($list as $i => $e) {
        if ((int) ($e['id'] ?? 0) === $active) $list[$i]['steps'] = $cur;      /* save actif */
        if ((int) ($e['id'] ?? 0) === $to)     $target = $e;
    }
    if ($target === null) return false;
    lfi_nct_episodes_save($uid, $list);
    update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($target['steps'] ?? []));
    update_user_meta($uid, 'lfi_nct_active_ep', $to);
    return true;
}

/** Crée un nouvel épisode (dossier d'incident séparé) et le rend ACTIF. */
function lfi_nct_episode_create($uid, $titre, $type = '', $piece = '') {
    $uid = (int) $uid;
    $list = lfi_nct_episodes_ensure($uid);
    /* Enregistrer l'épisode actif avant de basculer. */
    $active = lfi_nct_episode_active_id($uid);
    $cur = get_user_meta($uid, 'lfi_nct_suivi_steps', true);
    if (!is_array($cur)) $cur = [];
    foreach ($list as $i => $e) { if ((int) ($e['id'] ?? 0) === $active) $list[$i]['steps'] = $cur; }
    $steps = lfi_nct_episode_seed_steps();
    $id = lfi_nct_episode_new_id();
    $list[] = [
        'id' => $id, 'titre' => ($titre !== '' ? $titre : 'Nouvel incident'), 'type' => $type, 'piece' => $piece,
        'ouvert' => current_time('Y-m-d'), 'clos_urgence' => false, 'clos_date' => '',
        'steps' => $steps, 'prejudice' => [],
    ];
    lfi_nct_episodes_save($uid, $list);
    update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($steps));
    update_user_meta($uid, 'lfi_nct_active_ep', $id);
    return $id;
}

/** Renomme / retype un épisode. */
function lfi_nct_episode_update($uid, $id, $fields) {
    $list = lfi_nct_episodes_get($uid);
    foreach ($list as $i => $e) {
        if ((int) ($e['id'] ?? 0) === (int) $id) {
            foreach (['titre', 'type', 'piece'] as $k) if (isset($fields[$k])) $list[$i][$k] = $fields[$k];
            lfi_nct_episodes_save($uid, $list); return true;
        }
    }
    return false;
}

/** Clôt / rouvre le volet URGENCE d'un épisode (le juridique reste global). */
function lfi_nct_episode_set_clos_urgence($uid, $id, $clos) {
    $list = lfi_nct_episodes_get($uid);
    foreach ($list as $i => $e) {
        if ((int) ($e['id'] ?? 0) === (int) $id) {
            $list[$i]['clos_urgence'] = (bool) $clos;
            $list[$i]['clos_date'] = $clos ? current_time('Y-m-d') : '';
            lfi_nct_episodes_save($uid, $list); return true;
        }
    }
    return false;
}

/** Supprime un épisode (règle : tout est supprimable). On ne supprime jamais le
 *  dernier épisode restant. Si on supprime l'actif, on bascule sur un autre. */
function lfi_nct_episode_delete($uid, $id) {
    $uid = (int) $uid; $id = (int) $id;
    $list = lfi_nct_episodes_get($uid);
    if (count($list) <= 1) return false;
    $active = lfi_nct_episode_active_id($uid);
    $list = array_values(array_filter($list, function ($e) use ($id) { return (int) ($e['id'] ?? 0) !== $id; }));
    lfi_nct_episodes_save($uid, $list);
    if ($active === $id) {
        $first = (int) ($list[0]['id'] ?? 0);
        update_user_meta($uid, 'lfi_nct_active_ep', $first);
        update_user_meta($uid, 'lfi_nct_suivi_steps', array_values($list[0]['steps'] ?? []));
    }
    return true;
}

/** Avancement d'un épisode (faites / total, hors étapes sautées). */
function lfi_nct_episode_progress($e) {
    $steps = (isset($e['steps']) && is_array($e['steps'])) ? $e['steps'] : [];
    $steps = array_filter($steps, function ($s) { return empty($s['skipped']); });
    $total = count($steps); $done = 0;
    foreach ($steps as $s) if (!empty($s['done'])) $done++;
    return ['done' => $done, 'total' => $total, 'pct' => (int) round($done * 100 / max(1, $total))];
}

/** Nombre de besoins locataire en attente sur tout un épisode. */
function lfi_nct_episode_besoins_pending($e) {
    $n = 0;
    foreach ((array) ($e['steps'] ?? []) as $s) {
        if (function_exists('lfi_nct_suivi_besoins_pending')) $n += lfi_nct_suivi_besoins_pending($s);
    }
    return $n;
}
