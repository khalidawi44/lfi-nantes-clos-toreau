<?php
/**
 * IMPORT AUTOMATIQUE DES EMAILS — boîte collectrice.
 *
 * Toute la correspondance des dossiers locataires transite par UNE boîte Gmail
 * partagée (par défaut nantessudclostoreau@gmail.com). Un petit script Google
 * Apps Script lit cette boîte toutes les X minutes et POST chaque email ici.
 * Le site TRIE par adresses : NMH (bailleur), le membre du GA (expéditeur), et
 * le LOCATAIRE — et range l'email dans le BON dossier locataire. Ainsi le membre
 * du GA a tout le suivi, sans que personne n'accède à sa boîte perso.
 *
 * Sécurité : la clé d'intégration (lfi_nct_ingest_key) protège le point d'entrée.
 * Confidentialité : rien n'est public ; l'email est rangé dans le dossier
 * cloisonné du GA.
 */
if (!defined('ABSPATH')) exit;

/** Un texte ressemble-t-il à du base64 (corps d'email non décodé) ? */
function lfi_nct_email_looks_base64($s) {
    $c = preg_replace('/\s+/', '', (string) $s);
    if (strlen($c) < 24) return false;
    if (!preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $c)) return false;
    /* Le texte français normal contient des espaces/accents → pas ce motif. */
    return true;
}
/** Décode un corps d'email encodé (base64 ou quoted-printable) pour l'afficher
 *  en clair. Heuristique sûre : on ne décode que si le résultat est du texte
 *  lisible (UTF-8 / Latin-1) — sinon on renvoie l'original. */
function lfi_nct_email_decode_body($raw) {
    $raw = (string) $raw;
    if ($raw === '') return $raw;
    $t = trim($raw);
    /* 1) Base64 pur. */
    if (lfi_nct_email_looks_base64($t)) {
        $dec = base64_decode(preg_replace('/\s+/', '', $t), true);
        if ($dec !== false && $dec !== '') {
            $txt = mb_check_encoding($dec, 'UTF-8') ? $dec : @mb_convert_encoding($dec, 'UTF-8', 'ISO-8859-1,Windows-1252');
            if (is_string($txt) && $txt !== '') {
                $ctrl = preg_match_all('/[\x00-\x08\x0E-\x1F]/', $txt);
                if ($ctrl < strlen($txt) * 0.02) return $txt; /* peu de caractères de contrôle → OK */
            }
        }
    }
    /* 2) Quoted-printable. */
    if (preg_match('/=[0-9A-Fa-f]{2}/', $t) || preg_match('/=\r?\n/', $t)) {
        $dec = quoted_printable_decode($t);
        if ($dec !== '' && $dec !== $t) {
            return mb_check_encoding($dec, 'UTF-8') ? $dec : (string) @mb_convert_encoding($dec, 'UTF-8', 'ISO-8859-1,Windows-1252');
        }
    }
    return $raw;
}

/** Adresse de la boîte collectrice (configurable). */
function lfi_nct_inbox_collector() {
    return (string) get_option('lfi_nct_inbox_collector', 'nantessudclostoreau@gmail.com');
}
/** Domaines reconnus comme « bailleur social » (NMH + tous les bailleurs du GA). */
function lfi_nct_inbox_nmh_domains() {
    $d = get_option('lfi_nct_inbox_nmh_domains', '');
    $list = $d !== '' ? array_map('trim', explode(',', strtolower($d)))
                      : ['nantesmetropolehabitat.fr', 'nanteshabitat.fr', 'nmhabitat.fr', 'nmh.fr'];
    /* + les domaines des bailleurs configurés (Nantaise, Atlantique, CDC…). */
    if (function_exists('lfi_nct_bailleurs_domains')) {
        $list = array_merge($list, lfi_nct_bailleurs_domains());
    }
    return array_values(array_unique(array_filter($list)));
}

/** Extrait les adresses email d'une chaîne « Nom <a@b>, c@d ». */
function lfi_nct_inbox_emails($str) {
    $out = [];
    if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $str, $m)) {
        foreach ($m[0] as $e) $out[] = strtolower($e);
    }
    return array_values(array_unique($out));
}
function lfi_nct_inbox_is_nmh($addresses) {
    $doms = lfi_nct_inbox_nmh_domains();
    foreach ((array) $addresses as $a) {
        $dom = substr(strrchr($a, '@'), 1);
        if ($dom && in_array($dom, $doms, true)) return true;
    }
    return false;
}

/** Index (caché) des locataires : emails connus + nom + adresse, pour le tri. */
function lfi_nct_inbox_tenant_index() {
    $cache = get_transient('lfi_nct_inbox_tenant_index');
    if (is_array($cache)) return $cache;
    global $wpdb;
    $idx = [];
    $role = defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant';
    $users = get_users(['role' => $role, 'fields' => ['ID', 'user_email', 'display_name'], 'number' => 2000]);
    foreach ($users as $u) {
        $emails = [];
        $ue = strtolower(trim((string) $u->user_email));
        if ($ue && is_email($ue) && !preg_match('/@(tenant|partenaire|avocat)\./', $ue)) $emails[] = $ue;
        /* Emails APPRIS (rattachés à la main → le robot s'en souvient). */
        $learned = get_user_meta($u->ID, 'lfi_nct_known_emails', true);
        if (is_array($learned)) foreach ($learned as $le) { $le = strtolower(trim((string) $le)); if ($le && is_email($le)) $emails[] = $le; }
        $nom = strtolower(trim((string) $u->display_name));
        $last = ''; $adrraw = ''; $adrkey = '';
        $rid = (int) get_user_meta($u->ID, 'lfi_nct_response_id', true);
        if ($rid) {
            $r = $wpdb->get_row($wpdb->prepare("SELECT contact_email, contact_prenom, contact_nom, adresse FROM {$wpdb->prefix}lfi_nct_responses WHERE id = %d", $rid));
            if ($r) {
                $ce = strtolower(trim((string) $r->contact_email));
                if ($ce && is_email($ce)) $emails[] = $ce;
                $n2 = strtolower(trim($r->contact_prenom . ' ' . $r->contact_nom));
                if ($n2 !== '') $nom = $n2;
                $last = strtolower(trim((string) $r->contact_nom));
                if (!empty($r->adresse)) {
                    $adrraw = strtolower(trim($r->adresse));
                    $adrkey = function_exists('lfi_nct_address_canonical_key') ? lfi_nct_address_canonical_key($r->adresse) : $adrraw;
                }
            }
        }
        /* Nom de famille = dernier mot du nom affiché si pas d'enquête. */
        if ($last === '' && $nom !== '') { $parts = preg_split('/\s+/', $nom); $last = end($parts); }
        $idx[] = [
            'uid'    => (int) $u->ID,
            'emails' => array_values(array_unique($emails)),
            'namekey'=> $nom,
            'last'   => $last,
            'adrraw' => $adrraw,
            'adrkey' => $adrkey,
        ];
    }
    set_transient('lfi_nct_inbox_tenant_index', $idx, 300);
    return $idx;
}

/**
 * Le « robot de tri » : trouve le locataire concerné par la correspondance.
 * 1) adresse email connue (compte, enquête, ou APPRISE) ;
 * 2) adresse postale citée ;
 * 3) NOM DE FAMILLE cité (le nom du locataire est presque toujours dans l'email)
 *    — uniquement s'il ne correspond qu'à UN seul locataire (sinon ambigu) ;
 * 4) nom complet cité.
 */
function lfi_nct_inbox_find_tenant($addresses, $text) {
    $addresses = array_map('strtolower', (array) $addresses);
    $idx = lfi_nct_inbox_tenant_index();
    /* 1) Match par ADRESSE EMAIL (le plus fiable). */
    foreach ($idx as $t) {
        foreach ($t['emails'] as $e) if (in_array($e, $addresses, true)) return $t['uid'];
    }
    $tl = ' ' . mb_strtolower($text) . ' ';
    /* 2) Adresse postale citée. */
    foreach ($idx as $t) {
        if ($t['adrraw'] !== '' && mb_strlen($t['adrraw']) >= 8 && strpos($tl, $t['adrraw']) !== false) return $t['uid'];
    }
    /* 3) NOM DE FAMILLE cité — seulement si unique (pas d'homonyme). */
    $by_last = [];
    foreach ($idx as $t) { if ($t['last'] !== '' && mb_strlen($t['last']) >= 4) $by_last[$t['last']][] = $t['uid']; }
    foreach ($by_last as $last => $uids) {
        if (count(array_unique($uids)) !== 1) continue; /* homonymes → on ne devine pas */
        if (preg_match('/\b' . preg_quote($last, '/') . '\b/u', $tl)) return (int) $uids[0];
    }
    /* 4) Nom complet cité. */
    foreach ($idx as $t) {
        if ($t['namekey'] !== '' && mb_strlen($t['namekey']) >= 6 && strpos($tl, ' ' . $t['namekey'] . ' ') !== false) return $t['uid'];
    }
    return 0;
}

/** Est-ce l'adresse email d'un membre du GA (pour ne pas l'apprendre comme locataire) ? */
function lfi_nct_inbox_is_member_email($addr) {
    $addr = strtolower(trim($addr));
    if ($addr === '') return false;
    $u = get_user_by('email', $addr);
    if (!$u) return false;
    $roles = (array) $u->roles;
    return in_array(defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member', $roles, true) || user_can($u, 'manage_options');
}

/** Apprentissage : mémorise sur la fiche du locataire les adresses « tierces »
 *  d'un email qu'on lui a rattaché (ni NMH, ni collectrice, ni membre du GA). */
function lfi_nct_inbox_learn($tenant_uid, $addresses) {
    $known = get_user_meta($tenant_uid, 'lfi_nct_known_emails', true);
    if (!is_array($known)) $known = [];
    $collector = strtolower(lfi_nct_inbox_collector());
    $changed = false;
    foreach ((array) $addresses as $a) {
        $a = strtolower(trim($a));
        if ($a === '' || !is_email($a)) continue;
        if ($a === $collector || lfi_nct_inbox_is_nmh([$a]) || lfi_nct_inbox_is_member_email($a)) continue;
        if (!in_array($a, $known, true)) { $known[] = $a; $changed = true; }
    }
    if ($changed) { update_user_meta($tenant_uid, 'lfi_nct_known_emails', array_values($known)); delete_transient('lfi_nct_inbox_tenant_index'); }
}

/** File d'attente « à rattacher » (emails non reconnus). */
function lfi_nct_inbox_unmatched() {
    $u = get_option('lfi_nct_inbox_unmatched', []);
    return is_array($u) ? $u : [];
}
function lfi_nct_inbox_unmatched_save($list) {
    /* on borne à 200 pour ne pas gonfler l'option. */
    update_option('lfi_nct_inbox_unmatched', array_slice(array_values($list), -200), false);
}

/* Nettoyage AUTOMATIQUE des doublons déjà importés, UNE seule fois après ce
   déploiement (sur tous les dossiers + la file « à rattacher »). Idempotent. */
add_action('init', 'lfi_nct_inbox_dedup_once', 20);
function lfi_nct_inbox_dedup_once() {
    if (get_option('lfi_nct_dedup_auto_v2') === '1') return;
    if (!function_exists('lfi_nct_inbox_dedup_existing')) return;
    try { lfi_nct_inbox_dedup_existing(); } catch (\Throwable $e) {}
    update_option('lfi_nct_dedup_auto_v2', '1', false);
}

/**
 * IDENTIFIE L'INTERLOCUTEUR d'un email d'après son adresse (et le libellé « De »).
 * Sert à SÉPARER les fils par personne (M. Moreno=NMH, M. Lejeune=Service Hygiène,
 * avocat, préfecture…). Renvoie ['email','label','key','ico'].
 */
function lfi_nct_interlocuteur($str) {
    $s = (string) $str;
    $email = '';
    if (preg_match('/[\w.\-+]+@[\w.\-]+\.[\w.\-]+/', $s, $m)) $email = strtolower($m[0]);
    $dom = $email ? (string) substr(strrchr($email, '@'), 1) : '';
    $hay = strtolower($s);
    if (strpos($hay, 'nmh') !== false || strpos($hay, 'nantesmetropolehabitat') !== false || strpos($hay, 'moreno') !== false)
        return ['email' => $email, 'label' => 'NMH (Nantes Métropole Habitat)', 'key' => 'nmh', 'ico' => '🏢'];
    if (strpos($hay, 'schs') !== false || strpos($hay, 'hygiene') !== false || strpos($hay, 'hygiène') !== false
        || strpos($hay, 'sihs') !== false || strpos($hay, 'lejeune') !== false || strpos($hay, 'ars') !== false || strpos($hay, 'sante') !== false)
        return ['email' => $email, 'label' => 'Service Hygiène / Santé', 'key' => 'schs', 'ico' => '🩺'];
    if (strpos($hay, 'avocat') !== false || strpos($hay, 'supiot') !== false || strpos($hay, 'justice') !== false || strpos($hay, 'barreau') !== false)
        return ['email' => $email, 'label' => 'Avocat·e', 'key' => 'avocat', 'ico' => '⚖️'];
    if (strpos($hay, 'prefecture') !== false || strpos($hay, 'prefet') !== false || strpos($hay, 'gouv.fr') !== false)
        return ['email' => $email, 'label' => 'Préfecture / État', 'key' => 'etat', 'ico' => '🏛️'];
    if ($email !== '') return ['email' => $email, 'label' => $email, 'key' => $email, 'ico' => '✉️'];
    return ['email' => '', 'label' => 'Autre', 'key' => 'autre', 'ico' => '✉️'];
}

/* -------------------------------------------------------------- *
 *  ANTI-DOUBLON GLOBAL — un email n'est importé QU'UNE fois,      *
 *  quel que soit le chemin (push Apps Script « inbox » ou pêche   *
 *  IMAP « mailcheck »). Tant que tu ne l'ouvres pas dans Gmail,   *
 *  il reste « non lu » et serait re-pêché → ce garde l'empêche.   *
 *                                                                 *
 *  Clé = Message-ID si présent ; sinon EMPREINTE de contenu       *
 *  (expéditeur + objet + jour + début du corps) → couvre les      *
 *  emails sans Message-ID (ex. « Email envoyé » d'un membre).     *
 * -------------------------------------------------------------- */
function lfi_nct_inbox_dedup_key($from, $subject, $date = '', $body = '', $message_id = '') {
    $mid = trim((string) $message_id);
    if ($mid !== '') return 'mid:' . $mid;
    $norm = function ($s) { return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $s))); };
    /* On borne le corps ET on ignore l'heure (garde le jour) : deux re-pêches du
       même email doivent produire la MÊME empreinte. */
    $day  = substr(preg_replace('/[^0-9]/', '', (string) $date), 0, 8); // AAAAMMJJ approx.
    $sig  = $norm($from) . '|' . $norm($subject) . '|' . $day . '|' . mb_substr($norm($body), 0, 160);
    return 'h:' . md5($sig);
}
/**
 * A-t-on déjà vu cette clé ? Si non, on la marque et on renvoie false
 * (= « nouveau, traite-le »). Si oui, renvoie true (= « doublon, ignore »).
 * Atomique : teste-et-marque en une fois.
 */
function lfi_nct_inbox_seen_mark($key) {
    $key = (string) $key;
    if ($key === '' || $key === 'mid:' || $key === 'h:') return false; // clé vide → on ne bloque pas
    $seen = get_option('lfi_nct_inbox_seen_global', []);
    if (!is_array($seen)) $seen = [];
    if (in_array($key, $seen, true)) return true;         // déjà vu → doublon
    $seen[] = $key;
    if (count($seen) > 1500) $seen = array_slice($seen, -1500);
    update_option('lfi_nct_inbox_seen_global', $seen, false);
    return false;                                          // nouveau
}

/**
 * NETTOYAGE des doublons DÉJÀ importés (avant la pose du garde) :
 *  - dans la file « à rattacher » ;
 *  - dans chaque dossier (emails reçus / envoyés en double).
 * On garde la 1re occurrence de chaque empreinte. Renvoie les compteurs retirés.
 */
function lfi_nct_inbox_dedup_existing() {
    global $wpdb;
    $removed = ['queue' => 0, 'dossiers' => 0];

    /* File « à rattacher ». */
    $q = lfi_nct_inbox_unmatched();
    $seen = []; $clean = [];
    foreach ($q as $e) {
        $k = $e['dedup'] ?? lfi_nct_inbox_dedup_key($e['from'] ?? '', $e['objet'] ?? '', $e['date'] ?? '', $e['body'] ?? '', $e['message_id'] ?? '');
        if (isset($seen[$k])) { $removed['queue']++; continue; }
        $seen[$k] = 1; $clean[] = $e;
    }
    if ($removed['queue'] > 0) lfi_nct_inbox_unmatched_save($clean);

    /* Dossiers : email_recu / email_log. */
    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $rows = $wpdb->get_results("SELECT id, notes FROM $t ORDER BY id DESC LIMIT 500") ?: [];
    foreach ($rows as $r) {
        $logs = json_decode($r->notes ?? '', true);
        if (!is_array($logs)) continue;
        $changed = false;
        foreach (['email_recu', 'email_log'] as $k) {
            if (empty($logs[$k]) || !is_array($logs[$k])) continue;
            $s = []; $keep = [];
            foreach ($logs[$k] as $e) {
                $dk = lfi_nct_inbox_dedup_key($e['de'] ?? '', $e['objet'] ?? '', $e['date'] ?? '', $e['corps'] ?? '', '');
                if (isset($s[$dk])) { $removed['dossiers']++; $changed = true; continue; }
                $s[$dk] = 1; $keep[] = $e;
            }
            $logs[$k] = $keep;
        }
        if ($changed) {
            $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE)], ['id' => (int) $r->id]);
        }
    }
    return $removed;
}

/* -------------------------------------------------------------- *
 *  LISTE NOIRE (« boîte noire ») — expéditeurs à ne JAMAIS        *
 *  importer : newsletters, no-reply, alertes automatiques…       *
 *  On stocke des ADRESSES exactes ou des DOMAINES.               *
 * -------------------------------------------------------------- */
function lfi_nct_inbox_blocklist() {
    $b = get_option('lfi_nct_inbox_blocklist', []);
    return is_array($b) ? $b : [];
}
function lfi_nct_inbox_blocklist_save($list) {
    $clean = [];
    foreach ((array) $list as $a) { $a = strtolower(trim($a)); if ($a !== '') $clean[$a] = 1; }
    update_option('lfi_nct_inbox_blocklist', array_keys($clean), false);
}
/** Une de ces adresses est-elle en liste noire (par adresse OU par domaine) ? */
function lfi_nct_inbox_is_blocklisted($addresses) {
    $bl = lfi_nct_inbox_blocklist();
    if (!$bl) return false;
    foreach ((array) $addresses as $a) {
        $a = strtolower(trim($a));
        if ($a === '') continue;
        if (in_array($a, $bl, true)) return true;
        $at = strrchr($a, '@');
        $dom = $at ? substr($at, 1) : '';
        if ($dom !== '' && in_array($dom, $bl, true)) return true;
    }
    return false;
}

/** Met en liste noire les expéditeurs des entrées de file données, et les retire. */
function lfi_nct_inbox_block_queue_ids($ids) {
    $ids = array_map('intval', (array) $ids);
    if (!$ids) return 0;
    $q = lfi_nct_inbox_unmatched();
    $senders = [];
    foreach ($q as $e) {
        if (!in_array((int) ($e['id'] ?? 0), $ids, true)) continue;
        $a = lfi_nct_inbox_emails($e['from'] ?? '');
        if (!empty($a[0])) $senders[strtolower($a[0])] = 1;
    }
    if (!$senders) return 0;
    $bl = lfi_nct_inbox_blocklist();
    foreach (array_keys($senders) as $s) $bl[] = $s;
    lfi_nct_inbox_blocklist_save($bl);
    $q = array_values(array_filter($q, function ($e) use ($senders) {
        foreach (array_map('strtolower', lfi_nct_inbox_emails($e['from'] ?? '')) as $x) if (isset($senders[$x])) return false;
        return true;
    }));
    lfi_nct_inbox_unmatched_save($q);
    return count($senders);
}

/**
 * Extrait l'identité du NOUVEAU MEMBRE d'un email de la file.
 *  - Action Populaire : le membre est NOMMÉ dans l'objet/corps (« … nouveau
 *    membre par message, Roman.P ! ») → on prend ce nom, PAS l'expéditeur.
 *  - Sinon : c'est l'expéditeur lui-même (nom + email de l'en-tête « De »).
 * Renvoie ['name'=>…, 'email'=>…] (email éventuellement vide).
 */
function lfi_nct_inbox_extract_new_member($e) {
    $from    = strtolower((string) ($e['from'] ?? ''));
    $subject = (string) ($e['objet'] ?? '');
    $body    = (string) ($e['body'] ?? ($e['extrait'] ?? ''));

    /* Notification Action Populaire : le nom est dans le texte, pas l'expéditeur. */
    if (strpos($from, 'actionpopulaire.fr') !== false || stripos($subject, 'nouveau membre') !== false) {
        $name = '';
        /* « …nouveau membre par message, Roman.P ! » / « …membre : Roman P » */
        if (preg_match('/nouveau membre[^,:]*[,:]\s*(.+?)\s*[!.\s]*$/ui', $subject, $m)) $name = trim($m[1]);
        elseif (preg_match('/membre[^,:]*[,:]\s*([\p{L}][\p{L}\-\.\s]{1,40}?)\s*[!.\s]*$/u', $subject, $m)) $name = trim($m[1]);
        if ($name === '' && preg_match('/\b([\p{Lu}][\p{L}\-]+\.?\s*[\p{Lu}]\.?)\b/u', $subject, $m)) $name = trim($m[1]);
        if ($name !== '') return ['name' => str_replace('.', ' ', $name), 'email' => ''];
    }

    /* Défaut : l'expéditeur. */
    $email = '';
    if (preg_match('/[\w.\-+]+@[\w.\-]+\.[a-z]{2,}/i', (string) ($e['from'] ?? ''), $m)) $email = strtolower($m[0]);
    $name = '';
    if (preg_match('/^\s*"?([^"<]+?)"?\s*</u', (string) ($e['from'] ?? ''), $mm)) $name = trim($mm[1]);
    if ($name === '' && $email !== '') $name = ucfirst(strtok($email, '@'));
    return ['name' => $name, 'email' => $email];
}

/** Crée un compte MEMBRE DU GA à partir d'un email de la file (nouveau membre
 *  annoncé, ou expéditeur). Renvoie l'uid (existant ou nouveau), ou 0. */
function lfi_nct_inbox_create_member_from_queue($qid) {
    $qid = (int) $qid; if (!$qid) return 0;
    $q = lfi_nct_inbox_unmatched(); $entry = null;
    foreach ($q as $e) if ((int) ($e['id'] ?? 0) === $qid) { $entry = $e; break; }
    if (!$entry) return 0;
    $info  = lfi_nct_inbox_extract_new_member($entry);
    $email = (string) $info['email'];
    $name  = trim((string) $info['name']);
    if ($email === '' && $name === '') return 0;
    if ($email !== '' && ($ex = get_user_by('email', $email))) return (int) $ex->ID; /* déjà un compte */
    if ($name === '' && $email !== '') $name = ucfirst(strtok($email, '@'));
    $parts  = preg_split('/\s+/', trim($name));
    $prenom = $parts[0] ?? '';
    $nom    = trim(implode(' ', array_slice($parts, 1)));
    $login  = function_exists('lfi_nct_app_make_username') ? lfi_nct_app_make_username($prenom, $nom) : sanitize_user(($prenom ?: 'membre') . wp_generate_password(4, false, false));
    $pwd    = function_exists('lfi_nct_app_make_password') ? lfi_nct_app_make_password() : wp_generate_password(14);
    $uid = wp_insert_user([
        'user_login'   => $login, 'user_pass' => $pwd,
        'user_email'   => ($email !== '' && function_exists('lfi_nct_app_clean_email')) ? lfi_nct_app_clean_email($email) : $email,
        'first_name'   => $prenom, 'last_name' => $nom,
        'display_name' => trim($prenom . ' ' . $nom) ?: $login,
        'role'         => defined('LFI_NCT_ROLE_GA') ? LFI_NCT_ROLE_GA : 'lfi_nct_ga_member',
    ]);
    if (is_wp_error($uid)) return 0;
    $cga = function_exists('lfi_nct_creation_ga') ? lfi_nct_creation_ga() : '';
    if ($cga) update_user_meta($uid, 'lfi_nct_ga', $cga);
    return (int) $uid;
}

/**
 * Range un email dans le bon dossier. Renvoie ['matched'=>bool,'dossier_id'=>int].
 */
function lfi_nct_inbox_route($from, $to, $cc, $subject, $body, $date = '', $message_id = '') {
    global $wpdb;
    /* Corps encodé (base64 / quoted-printable) → on le décode dès l'entrée pour
       stocker et afficher du texte lisible partout (file + dossier). */
    $body = lfi_nct_email_decode_body((string) $body);
    $from_a = lfi_nct_inbox_emails($from);
    $to_a   = lfi_nct_inbox_emails($to);
    $cc_a   = lfi_nct_inbox_emails($cc);
    $all    = array_values(array_unique(array_merge($from_a, $to_a, $cc_a)));
    $text   = $subject . "\n" . $body;

    /* Expéditeur en liste noire → on l'ignore totalement (jamais importé, jamais
       mis en file « à rattacher »). */
    if (lfi_nct_inbox_is_blocklisted($from_a)) return ['matched' => false, 'dossier_id' => 0, 'blocked' => true];

    /* ANTI-DOUBLON : déjà importé (même Message-ID, ou même empreinte de contenu
       si pas de Message-ID) → on n'ajoute rien. Résout les re-pêches en boucle
       des emails non ouverts dans Gmail. */
    $dk = lfi_nct_inbox_dedup_key($from, $subject, $date, $body, $message_id);
    if (lfi_nct_inbox_seen_mark($dk)) return ['matched' => false, 'dossier_id' => 0, 'duplicate' => true];

    $tenant_uid = lfi_nct_inbox_find_tenant($all, $text);
    $collector  = strtolower(lfi_nct_inbox_collector());

    /* Le membre du GA = une adresse qui n'est ni NMH, ni le locataire, ni la
       boîte collectrice. On l'affiche pour info. */
    $member = '';
    foreach ($all as $a) {
        if ($a === $collector) continue;
        if (lfi_nct_inbox_is_nmh([$a])) continue;
        $member = $member ?: $a;
    }

    if (!$tenant_uid) {
        $q = lfi_nct_inbox_unmatched();
        /* Garde-fou supplémentaire : ne pas ré-empiler si une entrée identique
           (même clé) est déjà dans la file (doublons hérités d'avant le fix). */
        foreach ($q as $e) {
            $ek = $e['dedup'] ?? lfi_nct_inbox_dedup_key($e['from'] ?? '', $e['objet'] ?? '', $e['date'] ?? '', $e['body'] ?? '', $e['message_id'] ?? '');
            if ($ek === $dk) return ['matched' => false, 'dossier_id' => 0, 'duplicate' => true];
        }
        $q[] = [
            'id' => (int) round(microtime(true) * 1000),
            'from' => $from, 'to' => $to, 'cc' => $cc, 'objet' => $subject,
            'body' => mb_substr((string) $body, 0, 20000),
            'message_id' => $message_id,
            'dedup' => $dk,
            'date' => $date ?: current_time('mysql'),
            'extrait' => mb_substr((string) $body, 0, 200),
        ];
        lfi_nct_inbox_unmatched_save($q);
        return ['matched' => false, 'dossier_id' => 0];
    }

    return lfi_nct_inbox_file($tenant_uid, $from, $to, $cc, $subject, $body, $date, $message_id, $member);
}

/** Range effectivement l'email dans le dossier du locataire donné. */
function lfi_nct_inbox_file($tenant_uid, $from, $to, $cc, $subject, $body, $date = '', $message_id = '', $member = '') {
    global $wpdb;
    $tenant_uid = (int) $tenant_uid;
    $d = function_exists('lfi_nct_dossier_find_for_tenant') ? lfi_nct_dossier_find_for_tenant($tenant_uid) : null;
    if (!$d) return ['matched' => false, 'dossier_id' => 0, 'tenant_uid' => $tenant_uid];

    $t = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
    $logs = json_decode($d->notes ?? '', true);
    if (!is_array($logs)) $logs = ['__notes' => $d->notes ?? ''];

    /* Anti-doublon par Message-ID. */
    $logs['inbox_seen'] = isset($logs['inbox_seen']) && is_array($logs['inbox_seen']) ? $logs['inbox_seen'] : [];
    if ($message_id !== '' && in_array($message_id, $logs['inbox_seen'], true)) {
        return ['matched' => true, 'dossier_id' => (int) $d->id, 'duplicate' => true];
    }
    if ($message_id !== '') { $logs['inbox_seen'][] = $message_id; $logs['inbox_seen'] = array_slice($logs['inbox_seen'], -300); }

    /* Sens : reçu de NMH (from = NMH) OU envoyé à NMH (to/cc = NMH). */
    $recu = lfi_nct_inbox_is_nmh(lfi_nct_inbox_emails($from));
    $entry = [
        'de'     => $from,
        'to'     => $to,
        'objet'  => $subject,
        'corps'  => $body,
        'date'   => $date ?: current_time('Y-m-d H:i'),
        'membre' => $member,
        'src'    => 'inbox',
    ];
    if ($recu) {
        $logs['email_recu'] = isset($logs['email_recu']) && is_array($logs['email_recu']) ? $logs['email_recu'] : [];
        $logs['email_recu'][] = $entry;
    } else {
        $logs['email_log'] = isset($logs['email_log']) && is_array($logs['email_log']) ? $logs['email_log'] : [];
        $logs['email_log'][] = $entry;
    }
    $wpdb->update($t, ['notes' => wp_json_encode($logs, JSON_UNESCAPED_UNICODE), 'updated_at' => current_time('mysql')], ['id' => (int) $d->id]);

    if ($recu && function_exists('lfi_nct_victoire_detect_from_email')) {
        lfi_nct_victoire_detect_from_email($tenant_uid, $subject, $body, (int) $d->id);
    }
    /* Auto-alimentation de la CHRONOLOGIE du dossier (le dossier évolue tout seul). */
    if (function_exists('lfi_nct_chrono_add_email')) {
        lfi_nct_chrono_add_email($tenant_uid, $recu ? 'recu' : 'envoye', $recu ? $from : $to, $subject, $date ?: current_time('Y-m-d'));
    }
    return ['matched' => true, 'dossier_id' => (int) $d->id, 'sens' => $recu ? 'recu' : 'envoye', 'tenant_uid' => $tenant_uid];
}

/** Attribution manuelle d'un email « à rattacher » → le range ET apprend. */
function lfi_nct_inbox_assign($queue_id, $tenant_uid) {
    $queue_id = (int) $queue_id; $tenant_uid = (int) $tenant_uid;
    $q = lfi_nct_inbox_unmatched();
    $email = null; $rest = [];
    foreach ($q as $e) { if ((int) ($e['id'] ?? 0) === $queue_id) $email = $e; else $rest[] = $e; }
    if (!$email || !$tenant_uid) return false;
    $res = lfi_nct_inbox_file($tenant_uid, $email['from'] ?? '', $email['to'] ?? '', $email['cc'] ?? '', $email['objet'] ?? '', $email['body'] ?? '', $email['date'] ?? '', $email['message_id'] ?? '');
    /* Les PIÈCES JOINTES importées en attente suivent l'email chez le locataire :
       on leur pose le bon propriétaire + l'étape auto (photos jamais perdues). */
    if (!empty($email['att_ids']) && is_array($email['att_ids'])) {
        foreach ($email['att_ids'] as $aid) {
            $aid = (int) $aid; if (!$aid || !get_post($aid)) continue;
            update_post_meta($aid, '_lfi_tenant_user_id', $tenant_uid);
            delete_post_meta($aid, '_lfi_inbox_pending');
            if (function_exists('lfi_nct_piece_autostep')) {
                $cat = (string) get_post_meta($aid, '_lfi_piece_cat', true) ?: 'autre';
                $sk = lfi_nct_piece_autostep($tenant_uid, $cat);
                if ($sk !== '') update_post_meta($aid, '_lfi_step', $sk);
            }
        }
    }
    /* Le robot apprend : on mémorise les adresses tierces pour la prochaine fois. */
    $all = array_merge(lfi_nct_inbox_emails($email['from'] ?? ''), lfi_nct_inbox_emails($email['to'] ?? ''), lfi_nct_inbox_emails($email['cc'] ?? ''));
    lfi_nct_inbox_learn($tenant_uid, $all);
    lfi_nct_inbox_unmatched_save($rest);
    return $res;
}

/* ============================================================== *
 *  ROUTE REST : POST /wp-json/lfi-nct/v1/inbox                    *
 *  (protégée par la clé d'intégration lfi_nct_ingest_key)         *
 * ============================================================== */
add_action('rest_api_init', function () {
    register_rest_route('lfi-nct/v1', '/inbox', [
        'methods'             => 'POST',
        'callback'            => 'lfi_nct_inbox_rest',
        'permission_callback' => function ($r) {
            return function_exists('lfi_nct_ingest_rest_auth') ? lfi_nct_ingest_rest_auth($r) : false;
        },
    ]);
});
function lfi_nct_inbox_rest($request) {
    $from = (string) $request->get_param('from');
    $to   = (string) $request->get_param('to');
    $cc   = (string) $request->get_param('cc');
    $subj = sanitize_text_field((string) $request->get_param('subject'));
    $body = (string) $request->get_param('body');
    $body = wp_check_invalid_utf8($body);
    $body = sanitize_textarea_field($body);
    $date = sanitize_text_field((string) $request->get_param('date'));
    $mid  = sanitize_text_field((string) $request->get_param('message_id'));
    if ($from === '' && $to === '') return new WP_REST_Response(['ok' => false, 'error' => 'vide'], 400);
    $res = lfi_nct_inbox_route($from, $to, $cc, $subj, $body, $date, $mid);
    return new WP_REST_Response(['ok' => true] + $res, 200);
}

/* ============================================================== *
 *  VUE ADMIN : configurer l'import + file « à rattacher » + script *
 * ============================================================== */
function lfi_nct_app_view_inbox_import() {
    if (!current_user_can('manage_options')) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    if (!empty($_POST['lfi_inbox_cfg']) && check_admin_referer('lfi_inbox_cfg')) {
        update_option('lfi_nct_inbox_collector', sanitize_email(wp_unslash($_POST['collector'] ?? '')) ?: 'nantessudclostoreau@gmail.com', false);
        update_option('lfi_nct_inbox_nmh_domains', sanitize_text_field(wp_unslash($_POST['nmh_domains'] ?? '')), false);
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['saved' => 1])); exit;
    }
    if (!empty($_POST['lfi_inbox_regen']) && check_admin_referer('lfi_inbox_cfg') && function_exists('lfi_nct_ingest_key_regenerate')) {
        lfi_nct_ingest_key_regenerate();
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['regen' => 1])); exit;
    }
    if (!empty($_POST['lfi_inbox_clear']) && check_admin_referer('lfi_inbox_cfg')) {
        /* On supprime aussi les pièces jointes en attente de ces emails. */
        foreach (lfi_nct_inbox_unmatched() as $e) {
            foreach ((array) ($e['att_ids'] ?? []) as $aid) { if ((int) $aid) wp_delete_attachment((int) $aid, true); }
        }
        lfi_nct_inbox_unmatched_save([]);
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['cleared' => 1])); exit;
    }
    if (!empty($_POST['lfi_inbox_dedup']) && check_admin_referer('lfi_inbox_cfg')) {
        $rm = lfi_nct_inbox_dedup_existing();
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['deduped' => (int) $rm['queue'] + (int) $rm['dossiers']])); exit;
    }
    /* Actions sur la file « à rattacher » — UN seul formulaire, plusieurs boutons. */
    if (!empty($_POST['lfi_inbox_queue']) && check_admin_referer('lfi_inbox_assign')) {
        if (!empty($_POST['do_assign'])) {                       /* ranger chez une personne */
            $qid = (int) $_POST['do_assign'];
            $tid = (int) ($_POST['tenant_' . $qid] ?? 0);
            if (!$tid) { wp_safe_redirect(lfi_nct_app_url('inbox-import', ['pickone' => 1])); exit; }
            if ($qid && (!function_exists('lfi_nct_uid_in_scope') || lfi_nct_uid_in_scope($tid))) lfi_nct_inbox_assign($qid, $tid);
            wp_safe_redirect(lfi_nct_app_url('inbox-import', ['assigned' => 1])); exit;
        }
        if (!empty($_POST['do_block'])) {                        /* liste noire (1 expéditeur) */
            lfi_nct_inbox_block_queue_ids([(int) $_POST['do_block']]);
            wp_safe_redirect(lfi_nct_app_url('inbox-import', ['blocked' => 1])); exit;
        }
        if (!empty($_POST['bulk_block'])) {                      /* liste noire (cochés) */
            $n = lfi_nct_inbox_block_queue_ids((array) ($_POST['ids'] ?? []));
            wp_safe_redirect(lfi_nct_app_url('inbox-import', ['blocked' => $n ?: 1])); exit;
        }
        if (!empty($_POST['do_del']) || !empty($_POST['bulk_del'])) { /* supprimer SANS liste noire */
            $del = !empty($_POST['do_del']) ? [(int) $_POST['do_del']] : array_map('intval', (array) ($_POST['ids'] ?? []));
            $q = [];
            foreach (lfi_nct_inbox_unmatched() as $e) {
                if (in_array((int) ($e['id'] ?? 0), $del, true)) {
                    foreach ((array) ($e['att_ids'] ?? []) as $aid) { if ((int) $aid) wp_delete_attachment((int) $aid, true); }
                } else { $q[] = $e; }
            }
            lfi_nct_inbox_unmatched_save(array_values($q));
            wp_safe_redirect(lfi_nct_app_url('inbox-import', ['deleted' => count($del) ?: 1])); exit;
        }
        if (!empty($_POST['do_member'])) {                       /* créer un membre du GA */
            $uid = lfi_nct_inbox_create_member_from_queue((int) $_POST['do_member']);
            if ($uid) { wp_safe_redirect(lfi_nct_app_url('comptes-ga', ['created_uid' => $uid]) . '#m-' . $uid); exit; }
            wp_safe_redirect(lfi_nct_app_url('inbox-import', ['memberr' => 1])); exit;
        }
        wp_safe_redirect(lfi_nct_app_url('inbox-import')); exit;
    }
    /* Ajout / retrait manuel dans la liste noire (adresse ou domaine). */
    if (!empty($_POST['lfi_inbox_block_add']) && check_admin_referer('lfi_inbox_bl')) {
        $addr = strtolower(sanitize_text_field(wp_unslash($_POST['addr'] ?? '')));
        if ($addr !== '') { $bl = lfi_nct_inbox_blocklist(); $bl[] = $addr; lfi_nct_inbox_blocklist_save($bl); }
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['bladd' => 1])); exit;
    }
    if (!empty($_POST['lfi_inbox_unblock']) && check_admin_referer('lfi_inbox_bl')) {
        $addr = strtolower(sanitize_text_field(wp_unslash($_POST['addr'] ?? '')));
        $bl = array_values(array_filter(lfi_nct_inbox_blocklist(), function ($a) use ($addr) { return $a !== $addr; }));
        lfi_nct_inbox_blocklist_save($bl);
        wp_safe_redirect(lfi_nct_app_url('inbox-import', ['blrm' => 1])); exit;
    }

    $collector = lfi_nct_inbox_collector();
    $key       = function_exists('lfi_nct_ingest_key') ? lfi_nct_ingest_key() : '';
    $endpoint  = rest_url('lfi-nct/v1/inbox');
    $doms      = implode(', ', lfi_nct_inbox_nmh_domains());

    lfi_nct_app_screen_open('📥 Import automatique des emails', 'Toute la correspondance NMH, rangée toute seule');
    if (!empty($_GET['saved']))   lfi_nct_app_flash('✅ Réglages enregistrés.');
    if (!empty($_GET['regen']))   lfi_nct_app_flash('🔑 Nouvelle clé générée — remets-la dans le script.');
    if (!empty($_GET['cleared'])) lfi_nct_app_flash('File « à rattacher » vidée.');
    if (!empty($_GET['assigned'])) lfi_nct_app_flash('✅ Email rangé — le robot a mémorisé cette adresse pour la prochaine fois.');
    if (!empty($_GET['blocked'])) lfi_nct_app_flash('🚫 Expéditeur mis en liste noire — ses emails ne seront plus jamais importés.');
    if (!empty($_GET['bladd']))   lfi_nct_app_flash('🚫 Ajouté à la liste noire.');
    if (!empty($_GET['blrm']))    lfi_nct_app_flash('Retiré de la liste noire.');
    if (!empty($_GET['memberr'])) lfi_nct_app_flash('⚠️ Impossible de créer le membre (email de l\'expéditeur manquant ou invalide).', 'error');
    if (!empty($_GET['pickone'])) lfi_nct_app_flash('⚠️ Choisis d\'abord une personne dans la liste avant « Ranger ».', 'error');
    if (!empty($_GET['deleted'])) lfi_nct_app_flash('🗑 ' . (int) $_GET['deleted'] . ' email(s) retiré(s) de la file (sans liste noire).');
    if (isset($_GET['deduped']))  lfi_nct_app_flash('🧹 ' . (int) $_GET['deduped'] . ' doublon(s) retiré(s) (file + dossiers).');

    echo '<div class="lfi-app-help">La boîte <strong>' . esc_html($collector) . '</strong> reçoit tout. Le site la lit et range chaque email dans le <strong>bon dossier locataire</strong>. Ce dont tu te sers au quotidien est <strong>en bas</strong> : la file « À rattacher ».</div>';

    /* Toute la CONFIG technique repliée dans un accordéon (rarement utile). */
    echo '<details style="margin-top:8px"><summary style="cursor:pointer;font-weight:800;color:#0b3d91;padding:8px 0">⚙️ Configuration (réglages, clé, script Apps Script, mémo) — rarement utile</summary>';

    /* Réglages */
    echo '<form method="post" class="lfi-app-form" style="background:#f8f8f8;padding:12px;border-radius:10px;margin-top:8px">' . wp_nonce_field('lfi_inbox_cfg', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_inbox_cfg" value="1">';
    echo '<label>📮 Boîte collectrice<input type="email" name="collector" value="' . esc_attr($collector) . '"></label>';
    echo '<label>🏢 Domaines NMH (séparés par des virgules)<input type="text" name="nmh_domains" value="' . esc_attr($doms) . '"></label>';
    echo '<button type="submit" class="btn-primary">💾 Enregistrer</button></form>';

    /* Anti-doublon : nettoyer les emails déjà importés en double (avant le garde). */
    echo '<div class="lfi-app-help" style="background:#eef7ee;border-left:4px solid #186a3b;margin-top:8px"><small>🛡️ <strong>Anti-doublon actif</strong> : un même email n\'est plus importé deux fois (même s\'il reste « non lu » dans Gmail et re-pêché). Pour effacer les doublons <strong>déjà présents</strong> (avant cette protection), clique ci-dessous.</small>';
    echo '<form method="post" onsubmit="return confirm(\'Retirer les doublons déjà importés (file + dossiers) ?\')" style="margin-top:6px">' . wp_nonce_field('lfi_inbox_cfg', '_wpnonce', true, false) . '<input type="hidden" name="lfi_inbox_dedup" value="1"><button type="submit" class="btn-primary" style="background:#186a3b">🧹 Retirer les doublons déjà importés</button></form></div>';

    /* Connexion (endpoint + clé) */
    echo '<h3 style="margin:16px 0 6px">🔗 Connexion du script</h3>';
    echo '<div class="lfi-app-card"><div class="com" style="font-size:.9em">';
    echo 'Endpoint : <code style="word-break:break-all">' . esc_html($endpoint) . '</code><br>';
    echo 'Clé : <code style="word-break:break-all">' . esc_html($key) . '</code>';
    echo '</div>';
    echo '<form method="post" style="margin-top:6px" onsubmit="return confirm(\'Régénérer la clé ? L\\\'ancienne cessera de fonctionner.\')">' . wp_nonce_field('lfi_inbox_cfg', '_wpnonce', true, false) . '<input type="hidden" name="lfi_inbox_regen" value="1"><button type="submit" class="btn-ghost" style="font-size:.82em">🔑 Régénérer la clé</button></form>';
    echo '</div>';

    /* Le script Apps Script prêt à coller */
    $script = lfi_nct_inbox_apps_script($endpoint, $key);
    echo '<h3 style="margin:16px 0 6px">🤖 Script Google Apps Script (à coller une fois)</h3>';
    echo '<div class="lfi-app-help"><small>Dans le Gmail collecteur : <strong>≡ → Extensions → Apps Script</strong>, colle ce code, puis crée un <strong>déclencheur horaire</strong> (toutes les 5–10 min) sur la fonction <code>lfiImportEmails</code>. Il POSTe chaque email récent vers le site et pose un libellé « lfi-importe » pour ne pas le refaire.</small></div>';
    echo '<textarea readonly onclick="this.select()" style="width:100%;height:260px;font-family:monospace;font-size:.72em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($script) . '</textarea>';

    /* Mémo membre */
    echo '<h3 style="margin:16px 0 6px">✉️ Mémo à envoyer aux membres (filtre Gmail)</h3>';
    $memo = "Pour que ton suivi des locataires soit complet automatiquement :\n"
          . "1. Dans Gmail : Paramètres (roue) → Voir tous les paramètres → Filtres et adresses bloquées → Créer un filtre.\n"
          . "2. Dans « Inclut les mots », mets : nantesmetropolehabitat.fr\n"
          . "3. Créer le filtre → coche « Transférer à » → ajoute : " . $collector . " (à valider une fois).\n"
          . "4. Valide. C'est tout : tes emails avec NMH arrivent tout seuls dans le dossier du locataire concerné. Personne n'accède à ta boîte.";
    echo '<textarea readonly onclick="this.select()" style="width:100%;height:150px;font-size:.85em;padding:8px;border:1px solid #ccc;border-radius:8px">' . esc_textarea($memo) . '</textarea>';

    echo '</details>'; /* fin de la config repliée */

    /* File à rattacher */
    $q = lfi_nct_inbox_unmatched();
    echo '<h3 style="margin:16px 0 6px">🧩 À rattacher (' . count($q) . ')</h3>';
    if (empty($q)) {
        echo '<div class="lfi-app-empty">Aucun email non reconnu. 👍</div>';
    } else {
        echo '<div class="lfi-app-help"><small>Le robot n\'a pas reconnu le locataire. Dis-lui une fois de qui il s\'agit : il range l\'email <strong>et il apprend</strong> (l\'adresse est mémorisée → la prochaine fois, c\'est automatique).</small></div>';
        /* Personnes rattachables = locataires (par rôle) UNION toute personne
           ayant un DOSSIER dans le périmètre. Multi-casquette : un admin / membre
           du GA qui a AUSSI son propre dossier locataire doit apparaître. */
        $people = [];
        if (function_exists('lfi_nct_users_ga_query')) {
            $ta = lfi_nct_users_ga_query(['role' => defined('LFI_NCT_ROLE_TENANT') ? LFI_NCT_ROLE_TENANT : 'lfi_nct_tenant', 'fields' => ['ID', 'display_name'], 'number' => 800, 'orderby' => 'display_name']);
            foreach (get_users($ta) as $tu) $people[(int) $tu->ID] = $tu->display_name;
        }
        global $wpdb;
        $td = $wpdb->prefix . 'lfi_nct_dossiers_locataires';
        $drows = $wpdb->get_results("SELECT DISTINCT tenant_user_id, tenant_prenom, tenant_nom FROM $td WHERE tenant_user_id > 0") ?: [];
        foreach ($drows as $r) {
            $duid = (int) $r->tenant_user_id;
            if (!$duid || isset($people[$duid])) continue;
            if (function_exists('lfi_nct_uid_in_scope') && !lfi_nct_uid_in_scope($duid)) continue; /* cloisonnement */
            $nm = trim($r->tenant_prenom . ' ' . $r->tenant_nom);
            if ($nm === '') { $u = get_userdata($duid); $nm = $u ? $u->display_name : ('Dossier #' . $duid); }
            $people[$duid] = $nm . ' 🗂️';
        }
        asort($people, SORT_NATURAL | SORT_FLAG_CASE);
        $opts_html = '<option value="">— rattacher à un locataire —</option>';
        foreach ($people as $puid => $pnm) $opts_html .= '<option value="' . (int) $puid . '">' . esc_html($pnm) . '</option>';
        /* UN seul formulaire pour toute la file → cases à cocher (liste noire en
           masse) + actions par email (ranger / créer un membre / liste noire). */
        echo '<form method="post">' . wp_nonce_field('lfi_inbox_assign', '_wpnonce', true, false);
        echo '<input type="hidden" name="lfi_inbox_queue" value="1">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin:6px 0 10px;flex-wrap:wrap">';
        echo '<label style="font-size:.85em;color:#555;display:flex;gap:6px;align-items:center"><input type="checkbox" onclick="var c=this.checked;document.querySelectorAll(\'.lfi-ib-ck\').forEach(function(x){x.checked=c})"> tout cocher</label>';
        echo '<div style="display:flex;gap:6px;flex-wrap:wrap">';
        echo '<button type="submit" name="bulk_del" value="1" class="btn-ghost" style="font-size:.82em" onclick="return confirm(\'Supprimer les emails COCHÉS de la file (sans liste noire) ?\')">🗑 Cochés → supprimer</button>';
        echo '<button type="submit" name="bulk_block" value="1" class="btn-ghost" style="font-size:.82em;color:#c8102e;border-color:#f0b6c1" onclick="return confirm(\'Mettre les expéditeurs COCHÉS en liste noire ?\')">🚫 Cochés → liste noire</button>';
        echo '</div></div>';
        echo '<ul class="lfi-app-list">';
        foreach (array_reverse($q) as $e) {
            $qid = (int) ($e['id'] ?? 0);
            echo '<li class="lfi-app-card">';
            echo '<div class="head" style="align-items:center"><label style="display:flex;gap:8px;align-items:center;flex:1;min-width:0"><input type="checkbox" class="lfi-ib-ck" name="ids[]" value="' . $qid . '" style="width:18px;height:18px;flex:0 0 auto"><span class="who" style="overflow:hidden;text-overflow:ellipsis">' . esc_html($e['objet'] ?: '(sans objet)') . '</span></label><div class="when" style="font-size:.78em;color:#888">' . esc_html($e['date'] ?? '') . '</div></div>';
            echo '<div class="meta"><span class="meta-chip">de ' . esc_html($e['from'] ?? '') . '</span>';
            if (!empty($e['to'])) echo '<span class="meta-chip">→ ' . esc_html($e['to']) . '</span>';
            $nph = count((array) ($e['att_ids'] ?? []));
            if ($nph > 0) echo '<span class="meta-chip" style="background:#eef7ee;color:#186a3b;font-weight:700">📎 ' . (int) $nph . ' photo(s)/PDF — suivront le rangement</span>';
            echo '</div>';
            $ib_body = function_exists('lfi_nct_email_decode_body') ? lfi_nct_email_decode_body((string) ($e['body'] ?? '')) : (string) ($e['body'] ?? '');
            $ib_extr = trim((string) ($e['extrait'] ?? ''));
            /* Si l'extrait est du base64 (ou vide), on le reconstruit depuis le corps décodé. */
            if ($ib_extr === '' || (function_exists('lfi_nct_email_looks_base64') && lfi_nct_email_looks_base64($ib_extr))) {
                $ib_extr = mb_substr(trim(preg_replace('/\s+/', ' ', $ib_body)), 0, 160);
            }
            if ($ib_extr !== '') echo '<div class="com" style="color:#666;font-size:.85em">' . esc_html($ib_extr) . '…</div>';
            if ($ib_body !== '') echo '<details style="margin-top:6px"><summary style="cursor:pointer;font-size:.82em;color:#0066a3;font-weight:600">📄 Voir le mail complet</summary><div style="white-space:pre-wrap;font-size:.82em;color:#444;background:#f7f7f9;border-radius:8px;padding:10px;margin-top:6px;max-height:320px;overflow:auto">' . esc_html($ib_body) . '</div></details>';
            /* 🤖 SUGGESTION : le robot re-cherche le locataire sur le corps
               DÉCODÉ (nom cité, ex. « Monsieur DOUCET ») → pré-sélectionne. */
            $guess_uid = 0;
            if (function_exists('lfi_nct_inbox_find_tenant')) {
                $addrs = array_merge(lfi_nct_inbox_emails($e['from'] ?? ''), lfi_nct_inbox_emails($e['to'] ?? ''), lfi_nct_inbox_emails($e['cc'] ?? ''));
                $g = (int) lfi_nct_inbox_find_tenant($addrs, ($e['objet'] ?? '') . ' ' . $ib_body);
                if ($g && isset($people[$g])) $guess_uid = $g;
            }
            $opts_item = '<option value="">— rattacher à un locataire —</option>';
            foreach ($people as $puid => $pnm) $opts_item .= '<option value="' . (int) $puid . '"' . ($puid == $guess_uid ? ' selected' : '') . '>' . esc_html($pnm) . '</option>';
            if ($guess_uid) echo '<div style="font-size:.82em;color:#186a3b;font-weight:700;margin-top:6px">🤖 Le robot pense : <strong>' . esc_html($people[$guess_uid]) . '</strong> — vérifie et clique « Ranger ».</div>';
            echo '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:8px">';
            echo '<select name="tenant_' . $qid . '" style="flex:1;min-width:150px">' . $opts_item . '</select>';
            echo '<button type="submit" name="do_assign" value="' . $qid . '" class="btn-primary" style="background:#186a3b">✅ Ranger</button>';
            echo '<button type="submit" name="do_member" value="' . $qid . '" formtarget="_blank" class="btn-ghost" style="font-size:.83em;color:#4b2e83;border-color:#c9bdf0">➕ Créer un membre</button>';
            echo '<button type="submit" name="do_del" value="' . $qid . '" class="btn-ghost" style="font-size:.82em" title="Supprimer cet email (sans liste noire)" onclick="return confirm(\'Retirer cet email de la file (sans bannir l\\\'expéditeur) ?\')">🗑</button>';
            echo '<button type="submit" name="do_block" value="' . $qid . '" class="btn-ghost" style="font-size:.82em;color:#c8102e;border-color:#f0b6c1" title="Liste noire" onclick="return confirm(\'Mettre cet expéditeur en liste noire ?\')">🚫</button>';
            echo '</div></li>';
        }
        echo '</ul></form>';
        echo '<form method="post" onsubmit="return confirm(\'Vider la file à rattacher ?\')" style="margin-top:8px">' . wp_nonce_field('lfi_inbox_cfg', '_wpnonce', true, false) . '<input type="hidden" name="lfi_inbox_clear" value="1"><button type="submit" class="btn-ghost" style="font-size:.82em">🗑 Vider la file</button></form>';
    }

    /* -------- Liste noire (boîte noire) -------- */
    $bl = lfi_nct_inbox_blocklist();
    echo '<h3 style="margin:18px 0 6px">🚫 Liste noire (' . count($bl) . ')</h3>';
    echo '<div class="lfi-app-help"><small>Adresses ou domaines dont les emails ne sont <strong>jamais</strong> importés (newsletters, no-reply, alertes automatiques…). Mets un domaine entier comme <code>lafranceinsoumise.fr</code> ou une adresse exacte.</small></div>';
    if (!empty($bl)) {
        echo '<ul class="lfi-app-list">';
        foreach ($bl as $addr) {
            echo '<li class="lfi-app-card" style="display:flex;justify-content:space-between;align-items:center;gap:8px"><span class="meta-chip">' . esc_html($addr) . '</span>';
            echo '<form method="post" style="margin:0">' . wp_nonce_field('lfi_inbox_bl', '_wpnonce', true, false) . '<input type="hidden" name="lfi_inbox_unblock" value="1"><input type="hidden" name="addr" value="' . esc_attr($addr) . '"><button type="submit" class="btn-ghost" style="font-size:.78em">Retirer</button></form></li>';
        }
        echo '</ul>';
    }
    echo '<form method="post" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">' . wp_nonce_field('lfi_inbox_bl', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_inbox_block_add" value="1">';
    echo '<input type="text" name="addr" placeholder="adresse@exemple.fr ou domaine.fr" style="flex:1;min-width:200px;padding:9px;border:1.5px solid #ddd;border-radius:8px">';
    echo '<button type="submit" class="btn-ghost" style="font-size:.82em">➕ Ajouter à la liste noire</button></form>';

    lfi_nct_app_screen_close();
}

/** Le code Apps Script (paramétré avec l'endpoint + la clé). */
function lfi_nct_inbox_apps_script($endpoint, $key) {
    $ep = addslashes($endpoint);
    $ky = addslashes($key);
    return <<<JS
// LFI Nantes Sud — import des emails vers le site. Déclencheur horaire → lfiImportEmails
var LFI_ENDPOINT = "$ep";
var LFI_KEY = "$ky";
var LFI_LABEL = "lfi-importe";

function lfiImportEmails() {
  var label = GmailApp.getUserLabelByName(LFI_LABEL) || GmailApp.createLabel(LFI_LABEL);
  // conversations récentes non encore importées
  var threads = GmailApp.search('newer_than:60d -label:' + LFI_LABEL, 0, 100);
  for (var i = 0; i < threads.length; i++) {
    var msgs = threads[i].getMessages();
    if (!msgs.length) { threads[i].addLabel(label); continue; }
    // On envoie TOUTE LA CONVERSATION (du plus ancien au plus récent) en un
    // seul envoi par fil → contexte complet pour retrouver le locataire, et
    // aucun doublon (une entrée par conversation, clé = id du fil).
    var conv = "", froms = {}, tos = {}, last = msgs[msgs.length - 1];
    for (var j = 0; j < msgs.length; j++) {
      var m = msgs[j];
      froms[m.getFrom()] = 1; tos[m.getTo()] = 1;
      conv += "----- " + Utilities.formatDate(m.getDate(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm")
            + " | De : " + m.getFrom() + " | A : " + m.getTo() + " -----\n"
            + m.getPlainBody() + "\n\n";
    }
    try {
      UrlFetchApp.fetch(LFI_ENDPOINT, {
        method: "post",
        contentType: "application/json",
        payload: JSON.stringify({
          key: LFI_KEY,
          from: Object.keys(froms).join(", "),
          to: Object.keys(tos).join(", "),
          cc: last.getCc(),
          subject: last.getSubject(),
          body: conv.substring(0, 20000),
          date: Utilities.formatDate(last.getDate(), Session.getScriptTimeZone(), "yyyy-MM-dd HH:mm"),
          message_id: threads[i].getId()
        }),
        muteHttpExceptions: true
      });
    } catch (e) {}
    threads[i].addLabel(label);
  }
}
JS;
}
