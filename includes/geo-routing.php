<?php
/**
 * GÉO-ROUTAGE — router une enquête vers le bon GA selon l'adresse.
 *
 * Modèle de périmètre (deux natures, au choix par GA) :
 *   · COMMUNE ENTIÈRE  → le GA couvre toute une ville. Ex. Bouguenais, Vallet,
 *     Orvault : n'importe quelle adresse de la commune tombe dans ce GA.
 *   · RAYON (km)       → le GA couvre un quartier autour d'un centre. Ex. Clos
 *     Toreau : un rayon serré sur les immeubles HLM du quartier.
 *
 * Quand quelqu'un remplit l'enquête depuis le site et saisit son adresse :
 *   1. on géocode l'adresse (→ lat/lng + commune) ;
 *   2. on la rattache au GA dont le périmètre la couvre (commune ou rayon) ;
 *   3. si aucun GA ne couvre ET que la personne veut de l'aide → on peut créer
 *      automatiquement un GA « toute la commune » (Phase 3) ;
 *   4. si la personne veut être recontactée → file « à contacter » + notif admin.
 *
 * Phase 2 : profil de terrain par GA (HLM urbain / rural privé…) + suggestions
 * d'outils par les membres. Aucune donnée inventée.
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------- *
 *  Normalisation d'un nom de commune (accents, casse, tirets)     *
 * -------------------------------------------------------------- */
function lfi_nct_geo_norm($s) {
    $s = (string) $s;
    if (function_exists('remove_accents')) $s = remove_accents($s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

/* -------------------------------------------------------------- *
 *  Géocodage détaillé : adresse → [lat, lng, commune]             *
 * -------------------------------------------------------------- */
/**
 * Géocode une adresse française.
 * $hint_commune : commune du·de la militant·e qui a saisi (pour lever
 * l'ambiguïté quand l'adresse ne précise ni ville ni code postal — ex.
 * « 6 rue de Saint-Jean-de-Luz » qui existe dans plusieurs communes).
 * On utilise d'abord la Base Adresse Nationale (autoritaire pour la France),
 * puis Nominatim en repli.
 */
function lfi_nct_geo_geocode_detailed($address, $hint_commune = '') {
    $address = trim((string) $address);
    if ($address === '') return null;
    $has_cp = (bool) preg_match('/\b\d{5}\b/', $address);
    $hint_commune = trim((string) $hint_commune);
    /* Sans code postal ET sans mention de la commune → on ajoute l'indice. */
    $q = $address;
    if (!$has_cp && $hint_commune !== '' && stripos($address, $hint_commune) === false) {
        $q = $address . ', ' . $hint_commune;
    }

    /* 1) Base Adresse Nationale (data.gouv.fr) — la meilleure pour la France. */
    $ban_url = 'https://api-adresse.data.gouv.fr/search/?' . http_build_query(['q' => $q, 'limit' => 1, 'autocomplete' => 0]);
    $r = wp_remote_get($ban_url, ['timeout' => 12, 'headers' => ['User-Agent' => 'LFI-Nantes-Clos-Toreau/1.0', 'Accept' => 'application/json']]);
    if (!is_wp_error($r)) {
        $b = json_decode(wp_remote_retrieve_body($r), true);
        $f = $b['features'][0] ?? null;
        if (is_array($f) && !empty($f['geometry']['coordinates'][0]) && isset($f['geometry']['coordinates'][1])) {
            $p = $f['properties'] ?? [];
            return [
                'lat'     => (float) $f['geometry']['coordinates'][1],
                'lng'     => (float) $f['geometry']['coordinates'][0],
                'commune' => (string) ($p['city'] ?? $p['municipality'] ?? ''),
                'score'   => (float) ($p['score'] ?? 0),
            ];
        }
    }

    /* 2) Repli : Nominatim. */
    $url  = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $q . ', France', 'format' => 'json', 'addressdetails' => 1, 'limit' => 1, 'countrycodes' => 'fr',
    ]);
    $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => [
        'User-Agent' => 'LFI-Nantes-Clos-Toreau-Survey/1.0 (https://lfi-nantes-clostoreau.fr)',
        'Accept'     => 'application/json',
    ]]);
    if (is_wp_error($resp)) return null;
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body) || empty($body[0]) || !isset($body[0]['lat'], $body[0]['lon'])) return null;
    $a = $body[0]['address'] ?? [];
    $commune = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? $a['suburb'] ?? '';
    return ['lat' => (float) $body[0]['lat'], 'lng' => (float) $body[0]['lon'], 'commune' => (string) $commune, 'score' => 0.0];
}

/* -------------------------------------------------------------- *
 *  Config de périmètre par GA (option lfi_nct_ga_perimetres)      *
 *  [slug => ['type'=>'commune'|'rayon', 'commune'=>..., 'rayon'=>km]] *
 * -------------------------------------------------------------- */
function lfi_nct_geo_perimetres() {
    $p = get_option('lfi_nct_ga_perimetres', []);
    return is_array($p) ? $p : [];
}
/** Commune « par défaut » d'un GA, déduite de sa def (ville / secteur / nom). */
function lfi_nct_geo_ga_commune_default($slug, $def = null) {
    if ($slug === 'clos-toreau') return 'Nantes';
    if (is_array($def)) {
        foreach (['ville', 'secteur', 'geo_hint', 'nom'] as $k) {
            $v = trim((string) ($def[$k] ?? ''));
            if ($v !== '') return preg_replace('/^(ga|groupe d[\'’]action)\s+(lfi\s+)?/i', '', $v);
        }
    }
    return '';
}
function lfi_nct_geo_perimetre($slug, $def = null) {
    $slug = ($slug === '' ? 'clos-toreau' : (string) $slug);
    $all = lfi_nct_geo_perimetres();
    if (isset($all[$slug]) && is_array($all[$slug])) {
        $p = $all[$slug];
        $p['type']    = ($p['type'] ?? 'commune') === 'rayon' ? 'rayon' : 'commune';
        $p['commune'] = (string) ($p['commune'] ?? lfi_nct_geo_ga_commune_default($slug, $def));
        $p['rayon']   = (float) ($p['rayon'] ?? 3.0);
        return $p;
    }
    /* Défauts : Clos Toreau = quartier (rayon serré) ; les autres = commune. */
    if ($slug === 'clos-toreau') return ['type' => 'rayon', 'commune' => 'Nantes', 'rayon' => 1.2];
    return ['type' => 'commune', 'commune' => lfi_nct_geo_ga_commune_default($slug, $def), 'rayon' => 3.0];
}
function lfi_nct_geo_perimetre_set($slug, $type, $commune, $rayon) {
    $slug = ($slug === '' ? 'clos-toreau' : sanitize_title($slug));
    $all = lfi_nct_geo_perimetres();
    $all[$slug] = [
        'type'    => ($type === 'rayon' ? 'rayon' : 'commune'),
        'commune' => sanitize_text_field((string) $commune),
        'rayon'   => max(0.2, min(60, (float) $rayon)),
    ];
    update_option('lfi_nct_ga_perimetres', $all, false);
}

/* ---- Distance de Haversine (km) ------------------------------ */
function lfi_nct_geo_haversine($la1, $lo1, $la2, $lo2) {
    $R = 6371.0;
    $dLa = deg2rad($la2 - $la1); $dLo = deg2rad($lo2 - $lo1);
    $a = sin($dLa / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dLo / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/* ---- Tous les GA (slug, nom, def, centre) -------------------- */
function lfi_nct_geo_all_ga() {
    $out = [];
    $home = lfi_nct_ga_geo('clos-toreau');
    $out[] = ['slug' => 'clos-toreau', 'nom' => lfi_nct_ga_nom('clos-toreau'), 'def' => null, 'centre' => $home['centre'] ?? null];
    if (function_exists('lfi_nct_groupes')) {
        foreach (lfi_nct_groupes(true) as $g) {
            $slug = (string) ($g['slug'] ?? '');
            if ($slug === '' || $slug === 'clos-toreau') continue;
            $geo = lfi_nct_ga_geo($slug);
            $out[] = ['slug' => $slug, 'nom' => ($g['nom'] ?? $slug), 'def' => $g, 'centre' => $geo['centre'] ?? null];
        }
    }
    return $out;
}

/**
 * Rattache une adresse géocodée au meilleur GA.
 * Une correspondance de COMMUNE prime ; sinon le RAYON le plus proche.
 * Renvoie ['slug','nom','how'=>'commune'|'rayon','dist'] ou null.
 */
function lfi_nct_geo_match($lat, $lng, $commune) {
    $normC = lfi_nct_geo_norm($commune);
    $best_commune = null; $best_rayon = null;
    foreach (lfi_nct_geo_all_ga() as $g) {
        $p = lfi_nct_geo_perimetre($g['slug'], $g['def']);
        if ($p['type'] === 'commune') {
            if ($normC !== '' && lfi_nct_geo_norm($p['commune']) === $normC) {
                $best_commune = ['slug' => $g['slug'], 'nom' => $g['nom'], 'how' => 'commune', 'dist' => 0];
            }
        } else { /* rayon */
            if ($g['centre'] && $lat !== null && $lng !== null) {
                $d = lfi_nct_geo_haversine((float) $lat, (float) $lng, (float) $g['centre'][0], (float) $g['centre'][1]);
                if ($d <= $p['rayon'] && ($best_rayon === null || $d < $best_rayon['dist'])) {
                    $best_rayon = ['slug' => $g['slug'], 'nom' => $g['nom'], 'how' => 'rayon', 'dist' => $d];
                }
            }
        }
    }
    return $best_commune ?: $best_rayon;
}

/* ============================================================== *
 *  Phase 3 : créer automatiquement un GA « toute la commune »    *
 * ============================================================== */
/** Auto-création activable (défaut : ON). */
function lfi_nct_geo_autocreate_on() {
    $v = get_option('lfi_nct_geo_autocreate', '1');
    return $v !== '0';
}
function lfi_nct_geo_autocreate_ga($commune, $lat, $lng) {
    $commune = trim((string) $commune);
    if ($commune === '') return '';
    $slug = sanitize_title($commune);
    if ($slug === '') return '';
    $custom = function_exists('lfi_nct_groupes_custom') ? lfi_nct_groupes_custom() : [];
    if (isset($custom[$slug])) return $slug; /* déjà créé */
    $custom[$slug] = [
        'slug'     => $slug,
        'nom'      => 'GA LFI ' . $commune,
        'secteur'  => $commune,
        'ville'    => $commune,
        'travaux'  => 1,
        'centre'   => [(float) $lat, (float) $lng],
        'geo_hint' => $commune,
        'custom'   => 1,
        'auto'     => 1, /* créé automatiquement, à valider par le super-admin */
    ];
    update_option('lfi_nct_ga_custom', $custom, false);
    /* Périmètre = toute la commune. */
    lfi_nct_geo_perimetre_set($slug, 'commune', $commune, 3.0);
    return $slug;
}

/* ============================================================== *
 *  NETTOYAGE (une fois) : répare les enquêtes mal routées vers un  *
 *  GA créé automatiquement à partir d'une adresse AMBIGUË (sans    *
 *  code postal). On les rattache au GA de celui·celle qui a saisi, *
 *  et on supprime les GA auto désormais vides.                     *
 * ============================================================== */
add_action('init', 'lfi_nct_geo_cleanup_bad_autoga', 15);
function lfi_nct_geo_cleanup_bad_autoga() {
    if (get_option('lfi_nct_geo_cleanup_autoga_v1')) return;
    $custom = function_exists('lfi_nct_groupes_custom') ? lfi_nct_groupes_custom() : get_option('lfi_nct_ga_custom', []);
    if (!is_array($custom)) $custom = [];
    $auto = [];
    foreach ($custom as $slug => $g) if (!empty($g['auto'])) $auto[] = $slug;
    if (empty($auto)) { update_option('lfi_nct_geo_cleanup_autoga_v1', 1, false); return; }

    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $in = implode(',', array_fill(0, count($auto), '%s'));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT id, adresse, militant_user_id, ga FROM $t WHERE ga IN ($in) AND deleted_at IS NULL", $auto)) ?: [];
    $queue = get_option('lfi_nct_geo_contacts', []); if (!is_array($queue)) $queue = [];
    foreach ($rows as $r) {
        if (preg_match('/\b\d{5}\b/', (string) $r->adresse)) continue; /* adresse explicite → on respecte */
        $mg = trim((string) get_user_meta((int) $r->militant_user_id, 'lfi_nct_ga', true));
        if ($mg === '') $mg = 'clos-toreau';
        if ($mg === (string) $r->ga) continue;
        $wpdb->update($t, ['ga' => $mg], ['id' => (int) $r->id]);
        foreach ($queue as &$q) { if ((int) ($q['sub_id'] ?? 0) === (int) $r->id) { $q['ga'] = $mg; $q['couvert'] = 1; $q['auto'] = 0; } }
        unset($q);
    }
    update_option('lfi_nct_geo_contacts', $queue, false);

    /* Retire les GA auto devenus vides (aucune enquête, aucun membre). */
    $mem = $wpdb->prefix . 'lfi_nct_membres';
    $changed = false;
    $per = get_option('lfi_nct_ga_perimetres', []); if (!is_array($per)) $per = [];
    foreach ($auto as $slug) {
        $nr = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE ga = %s AND deleted_at IS NULL", $slug));
        $nm = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $mem WHERE ga = %s", $slug));
        if ($nr === 0 && $nm === 0) {
            unset($custom[$slug]); if (isset($per[$slug])) unset($per[$slug]); $changed = true;
        }
    }
    if ($changed) { update_option('lfi_nct_ga_custom', $custom, false); update_option('lfi_nct_ga_perimetres', $per, false); }
    update_option('lfi_nct_geo_cleanup_autoga_v1', 1, false);
}

/* ============================================================== *
 *  HOOK : une enquête vient d'être enregistrée → on la route      *
 * ============================================================== */
add_action('lfi_nct_submission_created', 'lfi_nct_geo_route_submission', 10, 2);
function lfi_nct_geo_route_submission($sub_id, $data = []) {
    global $wpdb;
    $sub_id = (int) $sub_id;
    if (!$sub_id) return;
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $sub_id));
    if (!$row || trim((string) $row->adresse) === '') return;
    /* Doublon détecté (validation prioritaire) → ne pas router ni mettre en
       file « à contacter » tant que le référent n'a pas tranché. */
    if (function_exists('lfi_nct_dup_is_flagged') && lfi_nct_dup_is_flagged($sub_id)) return;

    /* ANCRAGE : le·la militant·e qui a saisi enquête forcément DANS SA ZONE.
       On s'en sert pour (a) lever l'ambiguïté du géocodage et (b) éviter de
       créer un GA lointain à partir d'une adresse partielle. */
    $mil_ga = trim((string) get_user_meta((int) $row->militant_user_id, 'lfi_nct_ga', true));
    if ($mil_ga === '' ) $mil_ga = 'clos-toreau';
    $mil_perim = lfi_nct_geo_perimetre($mil_ga);
    $mil_commune = (string) ($mil_perim['commune'] ?? '');

    /* On privilégie la VILLE saisie dans le formulaire (donnée de l'utilisateur) ;
       à défaut, la commune du·de la militant·e. */
    $data_arr = json_decode((string) $row->data, true);
    $survey_ville = is_array($data_arr) ? trim((string) ($data_arr['ville'] ?? '')) : '';
    $hint = $survey_ville !== '' ? $survey_ville : $mil_commune;

    $geo = lfi_nct_geo_geocode_detailed($row->adresse, $hint);
    if (!$geo) return; /* non géocodable → on ne force rien */
    $wpdb->update($table, ['lat' => $geo['lat'], 'lng' => $geo['lng']], ['id' => $sub_id]);

    $match = lfi_nct_geo_match($geo['lat'], $geo['lng'], $geo['commune']);

    /* L'adresse nomme-t-elle explicitement une AUTRE commune que celle du·de la
       militant·e ? (code postal présent ET commune géocodée différente). */
    $has_cp = (bool) preg_match('/\b\d{5}\b/', (string) $row->adresse);
    $norm = function ($s) { return function_exists('remove_accents') ? strtolower(trim(remove_accents((string) $s))) : strtolower(trim((string) $s)); };
    $diff_commune = ($geo['commune'] !== '' && $mil_commune !== '' && $norm($geo['commune']) !== $norm($mil_commune));

    $auto = false;
    if (!$match) {
        /* On NE crée un GA lointain QUE si l'adresse désigne clairement une autre
           commune (code postal + commune différente). Sinon (adresse partielle,
           ambiguë), on rattache au GA de celui·celle qui a saisi — pas d'invention. */
        if ($has_cp && $diff_commune && (int) $row->contact_recontact === 1 && $geo['commune'] !== '' && lfi_nct_geo_autocreate_on()) {
            $slug = lfi_nct_geo_autocreate_ga($geo['commune'], $geo['lat'], $geo['lng']);
            if ($slug !== '') { $match = ['slug' => $slug, 'nom' => 'GA LFI ' . $geo['commune'], 'how' => 'commune', 'dist' => 0]; $auto = true; }
        }
        if (!$match) {
            /* Filet : on rattache au GA du·de la militant·e (sa connexion). */
            $match = ['slug' => $mil_ga, 'nom' => (function_exists('lfi_nct_ga_nom') ? lfi_nct_ga_nom($mil_ga) : $mil_ga), 'how' => 'militant', 'dist' => 0];
        }
    }
    if ($match) $wpdb->update($table, ['ga' => $match['slug']], ['id' => $sub_id]);

    if ((int) $row->contact_recontact === 1) {
        /* La personne veut de l'aide → on lui crée SON compte locataire tout de
           suite, rattaché à SON enquête. Ainsi elle apparaît partout (dossiers,
           « lier un compte »…) sans ressaisie, et on peut lui partager son espace. */
        $tenant_uid = function_exists('lfi_nct_ep_ensure_tenant') ? (int) lfi_nct_ep_ensure_tenant($row) : 0;
        /* On ne fixe le GA du compte QUE s'il n'en a pas encore — JAMAIS on ne
           déplace un compte locataire existant (sinon il « disparaît » de son GA). */
        if ($tenant_uid && $match) {
            $cur_ga = (string) get_user_meta($tenant_uid, 'lfi_nct_ga', true);
            if ($cur_ga === '') update_user_meta($tenant_uid, 'lfi_nct_ga', $match['slug']);
        }
        /* RÈGLE : qui veut être contacté → compte + dossier juridique, liés,
           avec les réponses de l'enquête. On rattache le dossier au bon GA. */
        if ($tenant_uid && function_exists('lfi_nct_ep_create_dossier')) {
            $owner = function_exists('lfi_nct_ga_owner_for_slug') ? lfi_nct_ga_owner_for_slug($match ? $match['slug'] : '') : 0;
            $souhaits = '';
            $data_r = json_decode((string) $row->data, true);
            if (is_array($data_r) && !empty($data_r['objectif'])) $souhaits = 'Objectif : ' . $data_r['objectif'];
            lfi_nct_ep_create_dossier($row, $tenant_uid, '', $souhaits, $owner);
        }

        lfi_nct_geo_queue_contact([
            'sub_id'  => $sub_id,
            'tenant_uid' => $tenant_uid,
            'ga'      => $match ? $match['slug'] : '',
            'commune' => $geo['commune'],
            'nom'     => trim((string) $row->contact_prenom . ' ' . (string) $row->contact_nom),
            'prenom'  => (string) $row->contact_prenom,
            'tel'     => (string) $row->contact_tel,
            'email'   => (string) $row->contact_email,
            'adresse' => (string) $row->adresse,
            'date'    => current_time('mysql'),
            'couvert' => $match ? 1 : 0,
            'auto'    => $auto ? 1 : 0,
        ]);
    }
}

/* ---- File des inscriptions « à contacter » ------------------- */
function lfi_nct_geo_queue_contact($item) {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) $q = [];
    foreach ($q as $e) if ((int) ($e['sub_id'] ?? 0) === (int) $item['sub_id']) return;
    $q[] = $item;
    if (count($q) > 300) $q = array_slice($q, -300);
    update_option('lfi_nct_geo_contacts', $q, false);
}
function lfi_nct_geo_contacts_pending() {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) return [];
    $pending = array_filter($q, function ($e) { return empty($e['done']); });
    /* Cloisonnement : chacun ne voit QUE les inscriptions de SON GA (celui
       actuellement affiché). Une inscription affectée à un autre GA va à cet
       autre GA, pas à moi. */
    $scope = function_exists('lfi_nct_scope_ga_slug') ? (string) lfi_nct_scope_ga_slug() : '';
    if ($scope === '') $scope = 'clos-toreau';
    $pending = array_filter($pending, function ($e) use ($scope) {
        $g = (string) ($e['ga'] ?? ''); if ($g === '') $g = 'clos-toreau';
        return $g === $scope;
    });
    return array_values($pending);
}
function lfi_nct_geo_contact_done($sub_id) {
    $q = get_option('lfi_nct_geo_contacts', []);
    if (!is_array($q)) return;
    foreach ($q as $i => $e) if ((int) ($e['sub_id'] ?? 0) === (int) $sub_id) $q[$i]['done'] = 1;
    update_option('lfi_nct_geo_contacts', $q, false);
}

function lfi_nct_geo_admin_notice() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) return;
    $n = count(lfi_nct_geo_contacts_pending());
    if ($n < 1) return;
    echo '<a href="' . esc_url(lfi_nct_app_url('geo-contacts')) . '" style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:9px 13px;background:#fff3cd;border:1px solid #d39e00;border-radius:10px;text-decoration:none;color:#8a6d1f;font-weight:800">'
       . '<span style="font-size:1.1em">🔔</span><span>' . (int) $n . ' inscription' . ($n > 1 ? 's' : '') . ' à contacter (via le site)</span>'
       . '<span style="margin-left:auto;font-size:.85em;opacity:.8">Voir →</span></a>';
}

/* ============================================================== *
 *  Phase 2 : profil de terrain par GA + modules recommandés      *
 * ============================================================== */
function lfi_nct_geo_profil_registry() {
    return [
        'hlm_urbain' => ['🏙️', 'HLM urbain', 'Quartier de logements sociaux, bailleur public (ex. Clos Toreau).',
            ['enquete', 'dossiers', 'appels_nmh', 'prefecture', 'travaux']],
        'prive_rural' => ['🌾', 'Rural / bailleurs privés', 'Communes rurales, propriétaires privés — leviers différents (conciliation, décence).',
            ['enquete', 'dossiers', 'aide_jurid', 'tutoriels']],
        'mixte' => ['🏘️', 'Mixte', 'À la fois logement social et privé.',
            ['enquete', 'dossiers', 'appels_nmh', 'aide_jurid']],
        'autre' => ['✨', 'Autre priorité', 'Le terrain a d\'autres besoins (services publics, énergie…) — à préciser via les suggestions.',
            ['enquete', 'dossiers']],
    ];
}
function lfi_nct_geo_profil($slug) {
    $slug = ($slug === '' ? 'clos-toreau' : (string) $slug);
    $p = get_option('lfi_nct_ga_profils', []);
    if (is_array($p) && !empty($p[$slug])) return (string) $p[$slug];
    return ($slug === 'clos-toreau') ? 'hlm_urbain' : 'mixte';
}
function lfi_nct_geo_profil_set($slug, $profil) {
    $reg = lfi_nct_geo_profil_registry();
    if (!isset($reg[$profil])) return;
    $slug = ($slug === '' ? 'clos-toreau' : sanitize_title($slug));
    $p = get_option('lfi_nct_ga_profils', []);
    if (!is_array($p)) $p = [];
    $p[$slug] = $profil;
    update_option('lfi_nct_ga_profils', $p, false);
}

/* ---- Suggestions d'outils (par les membres, vers l'admin) ---- */
function lfi_nct_geo_suggest_add($texte, $ga, $uid) {
    $texte = sanitize_textarea_field((string) $texte);
    if (trim($texte) === '') return false;
    $l = get_option('lfi_nct_tool_suggestions', []);
    if (!is_array($l)) $l = [];
    $l[] = ['texte' => $texte, 'ga' => (string) $ga, 'uid' => (int) $uid,
        'auteur' => ($uid ? (get_userdata($uid)->display_name ?? 'Membre') : 'Membre'),
        'date' => current_time('mysql')];
    if (count($l) > 500) $l = array_slice($l, -500);
    update_option('lfi_nct_tool_suggestions', $l, false);
    return true;
}
function lfi_nct_geo_suggests() {
    $l = get_option('lfi_nct_tool_suggestions', []);
    return is_array($l) ? $l : [];
}

/** Vue membre : suggérer un outil pour son GA. */
function lfi_nct_app_view_suggerer_outil() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_suggest']) && check_admin_referer('lfi_suggest')) {
        $ga = function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : '';
        lfi_nct_geo_suggest_add($_POST['texte'] ?? '', $ga, get_current_user_id());
        wp_safe_redirect(lfi_nct_app_url('suggerer-outil', ['ok' => 1]));
        exit;
    }
    lfi_nct_app_screen_open('💡 Suggérer un outil', 'Dis-nous ce dont ton terrain a besoin');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Merci ! Ta suggestion est transmise.');
    echo '<div class="lfi-app-help">L\'app n\'est pas que le logement. Si sur ton terrain il manque un outil (énergie, services publics, autre bailleur…), dis-le : l\'administrateur pourra le déployer pour ton GA.</div>';
    echo '<form method="post" class="lfi-app-card" style="border-left:4px solid #4b2e83">';
    echo wp_nonce_field('lfi_suggest', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_suggest" value="1">';
    echo '<label>Ton besoin<br><textarea name="texte" rows="4" required placeholder="ex : ici c\'est surtout des bailleurs privés — il faudrait un modèle de courrier de mise en conformité" style="width:100%;padding:9px;border:1px solid #ccc;border-radius:8px"></textarea></label>';
    echo '<div style="margin-top:8px"><button type="submit" class="btn-primary" style="background:#4b2e83">Envoyer ma suggestion</button></div>';
    echo '</form>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : inscriptions à contacter                          *
 * ============================================================== */
function lfi_nct_app_view_geo_contacts() {
    $can = (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga()) || current_user_can('manage_options');
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    if (!empty($_POST['lfi_geo_done']) && check_admin_referer('lfi_geo_done')) {
        lfi_nct_geo_contact_done((int) $_POST['lfi_geo_done']);
        wp_safe_redirect(lfi_nct_app_url('geo-contacts', ['ok' => 1]));
        exit;
    }
    /* Ouvrir le dossier : on CRÉE (ou retrouve) automatiquement le compte
       locataire depuis l'enquête et on va DIRECTEMENT à son dossier (parcours +
       partage de l'espace). Zéro « lier un compte » à la main. */
    if (!empty($_POST['lfi_geo_open']) && check_admin_referer('lfi_geo_open')) {
        global $wpdb;
        $sid = (int) $_POST['lfi_geo_open'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $sid));
        $tuid = ($row && function_exists('lfi_nct_ep_ensure_tenant')) ? (int) lfi_nct_ep_ensure_tenant($row) : 0;
        if ($tuid) {
            if ($row && trim((string) $row->ga) !== '') update_user_meta($tuid, 'lfi_nct_ga', (string) $row->ga);
            wp_safe_redirect(lfi_nct_app_url('dossier', ['uid' => $tuid])); exit;
        }
        /* Repli : ancien formulaire si le compte n'a pas pu être créé. */
        wp_safe_redirect(lfi_nct_app_url('dossier-juridique-add', ['tenant_nom' => $row->contact_nom ?? '', 'tenant_prenom' => $row->contact_prenom ?? '', 'tenant_adresse' => $row->adresse ?? ''])); exit;
    }
    lfi_nct_app_screen_open('🔔 Inscriptions à contacter', 'Enquêtes du site où la personne veut être recontactée');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Marqué comme traité.');

    $pending = lfi_nct_geo_contacts_pending();
    if (empty($pending)) {
        echo '<div class="lfi-app-help">Aucune inscription en attente. Quand quelqu\'un remplit l\'enquête sur le site et coche « je veux être recontacté·e », il apparaît ici, déjà rattaché à son GA.</div>';
        lfi_nct_app_screen_close();
        return;
    }
    echo '<ul class="lfi-app-list">';
    foreach (array_reverse($pending) as $e) {
        $ga_nom = ($e['ga'] ?? '') !== '' ? lfi_nct_ga_nom($e['ga']) : 'Aucun GA sur cette zone';
        $accent = !empty($e['couvert']) ? '#186a3b' : '#d39e00';
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '">';
        echo '<div class="head"><div class="who">📍 ' . esc_html($e['adresse'] ?: 'Adresse non précisée') . '</div>';
        echo '<div class="when" style="font-size:.78em;color:#888">' . esc_html(wp_date('j M', strtotime($e['date'] ?? ''))) . '</div></div>';
        echo '<div class="meta"><span class="meta-chip">🏳️ ' . esc_html($ga_nom) . '</span>';
        if (!empty($e['auto'])) echo '<span class="meta-chip">🆕 GA créé auto</span>';
        elseif (!empty($e['couvert'])) echo '<span class="meta-chip">✅ dans un périmètre</span>';
        else echo '<span class="meta-chip">⚠️ hors périmètre</span>';
        echo '</div>';
        if (trim((string) ($e['nom'] ?? '')) !== '') echo '<div class="com"><strong>' . esc_html($e['nom']) . '</strong></div>';
        $coords = [];
        if (!empty($e['tel']))   $coords[] = '📞 <a href="tel:' . esc_attr($e['tel']) . '">' . esc_html($e['tel']) . '</a>';
        if (!empty($e['email'])) $coords[] = '✉️ <a href="mailto:' . esc_attr($e['email']) . '">' . esc_html($e['email']) . '</a>';
        if ($coords) echo '<div class="com" style="font-size:.9em">' . implode(' · ', $coords) . '</div>';

        echo '<div class="row-actions" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">';
        /* Un clic : crée le compte locataire + ouvre son dossier (parcours + partage). */
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_geo_open', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_geo_open" value="' . (int) $e['sub_id'] . '">'
           . '<button type="submit" class="btn-primary">📂 Ouvrir le dossier</button></form>';
        echo '<form method="post" style="display:inline">' . wp_nonce_field('lfi_geo_done', '_wpnonce', true, false)
           . '<input type="hidden" name="lfi_geo_done" value="' . (int) $e['sub_id'] . '">'
           . '<button type="submit" class="btn-ghost" style="padding:6px 10px;font-size:.82em">✓ Traité</button></form>';
        echo '</div></li>';
    }
    echo '</ul>';
    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE ADMIN : périmètres + profils + suggestions (super-admin)  *
 * ============================================================== */
function lfi_nct_app_view_geo_perimetres() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_geo_peri_set']) && check_admin_referer('lfi_geo_peri_set')) {
        $slug = sanitize_title($_POST['slug'] ?? '');
        lfi_nct_geo_perimetre_set($slug, $_POST['type'] ?? 'commune', $_POST['commune'] ?? '', $_POST['rayon'] ?? 3);
        if (isset($_POST['profil'])) lfi_nct_geo_profil_set($slug, sanitize_key($_POST['profil']));
        wp_safe_redirect(lfi_nct_app_url('geo-perimetres', ['ok' => 1]));
        exit;
    }
    if (!empty($_POST['lfi_geo_ac_form']) && check_admin_referer('lfi_geo_ac')) {
        update_option('lfi_nct_geo_autocreate', empty($_POST['lfi_geo_autocreate']) ? '0' : '1', false);
        wp_safe_redirect(lfi_nct_app_url('geo-perimetres', ['ok' => 1]));
        exit;
    }

    lfi_nct_app_screen_open('🎯 Périmètres & profils des GA', 'Commune entière ou rayon · type de terrain · suggestions');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Enregistré.');

    echo '<div class="lfi-app-help">Une enquête du site est rattachée au GA qui couvre l\'adresse. <strong>Commune entière</strong> (ex. Bouguenais, Vallet, Orvault) ou <strong>rayon</strong> (quartier dense comme Clos Toreau). Le <strong>profil</strong> indique le type de terrain.</div>';

    $profs = lfi_nct_geo_profil_registry();
    echo '<ul class="lfi-app-list">';
    foreach (lfi_nct_geo_all_ga() as $g) {
        $p  = lfi_nct_geo_perimetre($g['slug'], $g['def']);
        $pr = lfi_nct_geo_profil($g['slug']);
        echo '<li class="lfi-app-card" style="border-left:4px solid #0066a3">';
        echo '<div class="head"><div class="who">🏳️ ' . esc_html($g['nom']) . (!empty($g['def']['auto']) ? ' <span style="font-size:.7em;color:#d39e00">(auto)</span>' : '') . '</div></div>';
        echo '<form method="post" style="margin-top:6px;display:flex;flex-direction:column;gap:7px">';
        echo wp_nonce_field('lfi_geo_peri_set', '_wpnonce', true, false);
        echo '<input type="hidden" name="slug" value="' . esc_attr($g['slug']) . '">';
        echo '<label style="font-size:.9em">Périmètre <select name="type" style="padding:6px;border:1px solid #ccc;border-radius:8px">'
           . '<option value="commune"' . selected($p['type'], 'commune', false) . '>Commune entière</option>'
           . '<option value="rayon"' . selected($p['type'], 'rayon', false) . '>Rayon (quartier)</option></select></label>';
        echo '<label style="font-size:.9em">Commune <input type="text" name="commune" value="' . esc_attr($p['commune']) . '" placeholder="ex : Bouguenais" style="width:100%;padding:7px;border:1px solid #ccc;border-radius:8px"></label>';
        echo '<label style="font-size:.9em">Rayon (si quartier) <input type="number" name="rayon" value="' . esc_attr($p['rayon']) . '" min="0.2" max="60" step="0.1" style="width:90px;padding:7px;border:1px solid #ccc;border-radius:8px"> km</label>';
        echo '<label style="font-size:.9em">Profil de terrain <select name="profil" style="padding:6px;border:1px solid #ccc;border-radius:8px">';
        foreach ($profs as $k => $pf) echo '<option value="' . esc_attr($k) . '"' . selected($pr, $k, false) . '>' . $pf[0] . ' ' . esc_html($pf[1]) . '</option>';
        echo '</select></label>';
        echo '<div><button type="submit" class="btn-ghost" style="padding:6px 12px;font-size:.85em">Enregistrer</button></div>';
        echo '</form></li>';
    }
    echo '</ul>';

    /* Auto-création. */
    echo '<form method="post" class="lfi-app-card" style="border-left:4px solid #d39e00">';
    echo wp_nonce_field('lfi_geo_ac', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_geo_ac_form" value="1">';
    $ac = lfi_nct_geo_autocreate_on();
    echo '<label style="font-weight:700"><input type="checkbox" name="lfi_geo_autocreate" value="1"' . checked($ac, true, false) . ' onchange="this.form.submit()"> Créer automatiquement un GA « toute la commune » quand une demande d\'aide arrive d\'une zone non couverte</label>';
    echo '</form>';

    /* Suggestions des membres. */
    $sugg = lfi_nct_geo_suggests();
    echo '<h3 style="margin:16px 0 6px;color:#4b2e83">💡 Suggestions d\'outils (membres)</h3>';
    if (empty($sugg)) {
        echo '<div class="lfi-app-help">Aucune suggestion pour l\'instant. Les membres peuvent proposer un outil dont leur terrain a besoin.</div>';
    } else {
        echo '<ul class="lfi-app-list">';
        foreach (array_reverse(array_slice($sugg, -40)) as $s) {
            echo '<li class="lfi-app-card" style="border-left:4px solid #4b2e83">';
            echo '<div class="com" style="white-space:pre-wrap">' . esc_html($s['texte']) . '</div>';
            echo '<div class="com" style="font-size:.82em;color:#888">' . esc_html($s['auteur'] ?? 'Membre') . ($s['ga'] ? ' · ' . esc_html(lfi_nct_ga_nom($s['ga'])) : '') . ' · ' . esc_html(wp_date('j M', strtotime($s['date'] ?? ''))) . '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }
    lfi_nct_app_screen_close();
}
