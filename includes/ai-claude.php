<?php
/**
 * INTELLIGENCE ARTIFICIELLE CLAUDE — le vrai cerveau des robots.
 *
 * Jusqu'ici, tous les « robots » (analyse d'emails, génération de réponses,
 * classement) tournaient par MOTS-CLÉS. Ici on branche la vraie API Claude
 * (Anthropic) pour :
 *   - AMÉLIORER une réponse déjà rédigée (ton, fluidité) SANS inventer un fait ;
 *   - CLASSER un email reçu (interlocuteur, urgence, résumé).
 *
 * Sécurité / cadre du projet :
 *   - La clé API reste sur LE SERVEUR (option WordPress), jamais dans le code
 *     ni dans un dépôt. C'est le compte Anthropic du GA qui est facturé.
 *   - Cloisonnement : on n'envoie à Claude QUE le texte du dossier en cours,
 *     jamais d'agrégat entre dossiers.
 *   - « Ne rien inventer » : les prompts INTERDISENT d'ajouter un nom, un
 *     chiffre, une date ou un fait qui n'est pas déjà dans le texte fourni.
 *   - Si la clé manque ou l'appel échoue → on retombe TOUJOURS sur le texte
 *     assemblé en PHP. L'app ne casse jamais.
 *
 * Pas de SDK : WordPress n'embarque pas le SDK Anthropic PHP et on déploie par
 * git sans Composer → on appelle l'API en HTTP direct via wp_remote_post
 * (approche native WordPress).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_AI_ENDPOINT = 'https://api.anthropic.com/v1/messages';
const LFI_NCT_AI_VERSION  = '2023-06-01';

/** La clé API Claude (stockée sur le serveur). '' si non configurée. */
function lfi_nct_ai_key() {
    return trim((string) get_option('lfi_nct_claude_api_key', ''));
}

/** Le modèle choisi (Sonnet 5 par défaut : meilleur rapport qualité/prix). */
function lfi_nct_ai_model() {
    $m = trim((string) get_option('lfi_nct_claude_model', ''));
    $ok = ['claude-haiku-4-5', 'claude-sonnet-5', 'claude-opus-4-8'];
    return in_array($m, $ok, true) ? $m : 'claude-sonnet-5';
}

/** L'IA est-elle utilisable ? (clé présente + activée). */
function lfi_nct_ai_enabled() {
    return lfi_nct_ai_key() !== '' && get_option('lfi_nct_claude_enabled', '1') === '1';
}

/** Libellés lisibles des modèles pour l'admin. */
function lfi_nct_ai_models() {
    return [
        'claude-haiku-4-5' => 'Haiku 4.5 — le moins cher (~2–3 €/mois)',
        'claude-sonnet-5'  => 'Sonnet 5 — recommandé (~5 €/mois)',
        'claude-opus-4-8'  => 'Opus 4.8 — le plus fin (~12 €/mois)',
    ];
}

/**
 * Appel bas niveau à l'API Messages de Claude.
 * @param string $system    Consignes système (cadre, interdits).
 * @param string $user      Le message utilisateur (le texte à traiter).
 * @param int    $max_tokens Plafond de la réponse.
 * @return string|null  Le texte de Claude, ou null si indisponible/échec.
 */
function lfi_nct_ai_call($system, $user, $max_tokens = 1024) {
    $key = lfi_nct_ai_key();
    if ($key === '') return null;

    $body = [
        'model'      => lfi_nct_ai_model(),
        'max_tokens' => (int) $max_tokens,
        'system'     => (string) $system,
        'messages'   => [['role' => 'user', 'content' => (string) $user]],
    ];

    $resp = wp_remote_post(LFI_NCT_AI_ENDPOINT, [
        'timeout' => 45,
        'headers' => [
            'x-api-key'         => $key,
            'anthropic-version' => LFI_NCT_AI_VERSION,
            'content-type'      => 'application/json',
        ],
        'body' => wp_json_encode($body),
    ]);

    if (is_wp_error($resp)) {
        update_option('lfi_nct_claude_last_error', 'Réseau : ' . $resp->get_error_message(), false);
        return null;
    }
    $code = (int) wp_remote_retrieve_response_code($resp);
    $raw  = (string) wp_remote_retrieve_body($resp);
    $data = json_decode($raw, true);

    if ($code !== 200 || !is_array($data)) {
        $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $code);
        update_option('lfi_nct_claude_last_error', 'API (' . $code . ') : ' . $msg, false);
        return null;
    }

    /* Concatène les blocs texte de la réponse. */
    $out = '';
    if (!empty($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $blk) {
            if (($blk['type'] ?? '') === 'text') $out .= (string) ($blk['text'] ?? '');
        }
    }
    $out = trim($out);
    if ($out === '') { update_option('lfi_nct_claude_last_error', 'Réponse vide.', false); return null; }

    /* Compteur mensuel + trace de succès (transparence, pas de facturation ici). */
    lfi_nct_ai_bump_usage($data['usage'] ?? []);
    update_option('lfi_nct_claude_last_error', '', false);
    update_option('lfi_nct_claude_last_ok', current_time('mysql'), false);
    return $out;
}

/** Compteur approximatif d'appels + tokens du mois (affiché à l'admin). */
function lfi_nct_ai_bump_usage($usage) {
    $mo = wp_date('Y-m');
    $u  = get_option('lfi_nct_claude_usage', []);
    if (!is_array($u) || ($u['month'] ?? '') !== $mo) $u = ['month' => $mo, 'calls' => 0, 'in' => 0, 'out' => 0];
    $u['calls']++;
    $u['in']  += (int) ($usage['input_tokens'] ?? 0);
    $u['out'] += (int) ($usage['output_tokens'] ?? 0);
    update_option('lfi_nct_claude_usage', $u, false);
}

/* ============================================================== *
 *  1) AMÉLIORER une réponse déjà rédigée (sans rien inventer)     *
 * ============================================================== */
/**
 * Réécrit le corps d'une réponse pour la rendre plus fluide et adaptée au ton,
 * SANS ajouter aucun fait. La signature est traitée à part (jamais touchée).
 * @param string $draft  Le corps assemblé en PHP (SANS la signature).
 * @param array  $recu   L'email reçu (['objet','corps']) — contexte de ton.
 * @param bool   $ferme  Registre ferme (mise en demeure) vs amiable.
 * @return string  Le corps amélioré, ou $draft inchangé si l'IA est off/échoue.
 */
function lfi_nct_ai_polish_reply($draft, $recu, $ferme = false) {
    if (!lfi_nct_ai_enabled()) return $draft;
    $draft = (string) $draft;
    if (trim($draft) === '') return $draft;

    $ton = $ferme
        ? "ferme, factuel, sans agressivité (on répond à une pression par du droit, pas par de l'émotion)"
        : "courtois, coopératif, orienté résolution amiable";

    $system =
        "Tu es le secrétariat d'une association qui accompagne un locataire face à son bailleur social (Nantes Métropole Habitat). "
        . "Tu RÉÉCRIS un courrier déjà rédigé pour le rendre plus clair, fluide et au ton " . $ton . ". "
        . "RÈGLES ABSOLUES, sans exception :\n"
        . "1. N'INVENTE RIEN : aucun fait, nom, date, chiffre, article de loi ou engagement qui ne figure pas déjà dans le texte fourni.\n"
        . "2. Ne SUPPRIME aucune demande, aucun délai, aucune référence légale présents.\n"
        . "3. N'ajoute PAS de formule de politesse finale ni de signature (elles sont gérées ailleurs).\n"
        . "4. Garde le vouvoiement, en français, et le point de vue de l'association qui écrit AU NOM du locataire.\n"
        . "5. Réponds UNIQUEMENT par le corps réécrit, rien d'autre (pas de commentaire, pas de balises).";

    $ctx = '';
    if (!empty($recu['objet']) || !empty($recu['corps'])) {
        $ctx = "Pour information, voici le message reçu auquel ce courrier répond (NE PAS le recopier, juste pour adapter le ton) :\n"
            . "Objet : " . (string) ($recu['objet'] ?? '') . "\n"
            . mb_substr((string) ($recu['corps'] ?? ''), 0, 2500) . "\n\n---\n\n";
    }
    $user = $ctx . "Voici le courrier à réécrire (améliore la forme, garde tout le fond) :\n\n" . $draft;

    $out = lfi_nct_ai_call($system, $user, 1500);
    if ($out === null) return $draft;

    /* Garde-fou : si Claude renvoie une signature/politesse malgré tout, on
       coupe à la première formule de clôture pour ne pas doubler. */
    $out = preg_replace('/\n+\s*(cordialement|bien cordialement|salutations|sinc[eè]rement)\b.*$/isu', '', $out);
    $out = trim($out);
    return $out !== '' ? $out : $draft;
}

/* ============================================================== *
 *  2) CLASSER un email reçu (interlocuteur, urgence, résumé)      *
 * ============================================================== */
/**
 * Analyse un email et renvoie un petit tableau structuré. Fallback mots-clés
 * si l'IA est indisponible.
 * @return array ['interlocuteur','urgence','resume','action'] (chaînes courtes).
 */
function lfi_nct_ai_classify_email($de, $objet, $corps) {
    $kw = function_exists('lfi_nct_interlocuteur') ? lfi_nct_interlocuteur($de . ' ' . $objet . ' ' . $corps) : ['key' => 'autre'];
    $kw_key = is_array($kw) ? (string) ($kw['key'] ?? 'autre') : 'autre';
    /* La clé mots-clés peut être un email (interlocuteur inconnu) → « autre ». */
    if (!in_array($kw_key, ['nmh', 'schs', 'avocat', 'etat', 'locataire'], true)) $kw_key = 'autre';
    $fallback = [
        'interlocuteur' => $kw_key,
        'urgence'       => 'normale',
        'resume'        => '',
        'action'        => '',
        'source'        => 'mots-cles',
    ];
    if (!lfi_nct_ai_enabled()) return $fallback;

    $system =
        "Tu classes un email reçu par une association qui accompagne des locataires face au bailleur Nantes Métropole Habitat (NMH). "
        . "Tu réponds UNIQUEMENT par un objet JSON valide, sans texte autour, avec EXACTEMENT ces clés :\n"
        . '{"interlocuteur":"nmh|schs|avocat|etat|locataire|autre","urgence":"haute|normale|basse","resume":"une phrase factuelle","action":"une action recommandée en quelques mots"}' . "\n"
        . "N'invente aucun fait : résume seulement ce qui est écrit. « schs » = service hygiène/santé de la mairie. « etat » = préfecture/administration. Sois bref.";

    $user = "De : " . $de . "\nObjet : " . $objet . "\n\n" . mb_substr((string) $corps, 0, 4000);

    $out = lfi_nct_ai_call($system, $user, 400);
    if ($out === null) return $fallback;

    /* Extrait le premier bloc JSON. */
    if (preg_match('/\{.*\}/s', $out, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) {
            $inter = in_array(($j['interlocuteur'] ?? ''), ['nmh', 'schs', 'avocat', 'etat', 'locataire', 'autre'], true) ? $j['interlocuteur'] : $fallback['interlocuteur'];
            return [
                'interlocuteur' => $inter,
                'urgence'       => in_array(($j['urgence'] ?? ''), ['haute', 'normale', 'basse'], true) ? $j['urgence'] : 'normale',
                'resume'        => sanitize_text_field((string) ($j['resume'] ?? '')),
                'action'        => sanitize_text_field((string) ($j['action'] ?? '')),
                'source'        => 'claude',
            ];
        }
    }
    return $fallback;
}

/* ============================================================== *
 *  3) DÉCORTIQUER un fichier .md → chronologie date par date      *
 * ============================================================== */
/**
 * Lit un dossier rédigé en Markdown et en extrait la CHRONOLOGIE (chaque
 * événement daté). Utilise la vraie IA si la clé est là, sinon un repli par
 * expressions régulières. « Ne rien inventer » : on n'extrait QUE ce qui est
 * écrit.
 * @return array  Liste [['date' => '...', 'event' => '...'], ...] (ordre chrono).
 */
function lfi_nct_md_extract_chrono($md) {
    $md = trim((string) $md);
    if ($md === '') return [];
    $md = mb_substr($md, 0, 40000); /* borne de coût */

    /* IA SEULE (la version qui triait bien). Le repli regex ne sert QUE si la
       clé Claude est absente / l'appel échoue — jamais fusionné avec l'IA (le
       mélange créait du bruit et des doublons). On dédUplique quand même le
       résultat sur (date + début de phrase) par sécurité. */
    if (lfi_nct_ai_enabled()) {
        $system =
            "Tu extrais la CHRONOLOGIE d'un dossier de défense d'un locataire, rédigé en Markdown. "
            . "Tu réponds UNIQUEMENT par un tableau JSON valide, sans texte autour :\n"
            . '[{"date":"jj/mm/aaaa","event":"une phrase factuelle"}, ...]' . "\n"
            . "RÈGLES : n'extrais QUE des événements réellement datés et écrits dans le texte (jamais inventés) — "
            . "y compris les traitements / interventions / désinsectisations et les emails datés. "
            . "date = telle qu'écrite (jj/mm/aaaa si possible ; sinon mois/année ou l'année seule). "
            . "event = une phrase courte et factuelle. Classe du plus ancien au plus récent. Ignore les passages non datés. "
            . "Ne mets JAMAIS deux fois le même événement (pas de doublon).";
        $out = lfi_nct_ai_call($system, $md, 6000);
        if ($out !== null && preg_match('/\[.*\]/s', $out, $m)) {
            $j = json_decode($m[0], true);
            if (is_array($j)) {
                $res = []; $seen = [];
                foreach ($j as $e) {
                    if (!is_array($e)) continue;
                    $ev = trim((string) ($e['event'] ?? '')); if ($ev === '') continue;
                    $d = trim((string) ($e['date'] ?? ''));
                    $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]/u', '', $d . mb_substr($ev, 0, 45)));
                    if (isset($seen[$key])) continue; $seen[$key] = 1;
                    $res[] = ['date' => $d, 'event' => $ev];
                }
                if ($res) return $res;
            }
        }
    }
    return lfi_nct_md_extract_chrono_regex($md);
}

/**
 * Extrait la SYNTHÈSE non datée d'un dossier .md — surtout le CHIFFRAGE DU
 * PRÉJUDICE (montants, postes, méthode, justification du total), le contexte et
 * les demandes. Renvoie du Markdown, ou '' si rien / IA absente. Ne rien inventer.
 */
function lfi_nct_md_extract_synthese($md) {
    if (!lfi_nct_ai_enabled()) return '';
    $md = mb_substr(trim((string) $md), 0, 40000);
    if ($md === '') return '';
    $system =
        "Tu lis un dossier de défense d'un locataire, rédigé en Markdown. "
        . "Tu RÉSUMES fidèlement les parties IMPORTANTES qui ne sont PAS des dates de chronologie, en priorité :\n"
        . "1. Le CHIFFRAGE DU PRÉJUDICE : chaque poste, chaque montant, la méthode et la JUSTIFICATION du total.\n"
        . "2. Le contexte du logement et les demandes (relogement, indemnisation, travaux).\n"
        . "Réponds en Markdown court et structuré (titres, listes, montants en €). "
        . "N'INVENTE AUCUN chiffre ni fait : reprends UNIQUEMENT ce qui est écrit. "
        . "Si le texte ne contient rien de ce type, réponds EXACTEMENT par : (vide)";
    $out = lfi_nct_ai_call($system, $md, 3000);
    if ($out === null) return '';
    $out = trim($out);
    if ($out === '' || mb_strtolower($out) === '(vide)') return '';
    return $out;
}

/**
 * Repère les ENTITÉS utiles pour monter le dossier juridique dans un .md :
 * l'avocat·e (à créer + rattacher), la référence d'aide juridictionnelle.
 * Renvoie un tableau (vide si IA absente). Ne rien inventer.
 */
function lfi_nct_md_extract_entities($md) {
    if (!lfi_nct_ai_enabled()) return [];
    $md = mb_substr(trim((string) $md), 0, 40000);
    if ($md === '') return [];
    $system =
        "Tu lis un dossier logement (Markdown). Tu repères les ENTITÉS utiles pour monter le dossier juridique. "
        . "Tu réponds UNIQUEMENT par un objet JSON, sans texte autour :\n"
        . '{"avocat":{"nom":"","email":"","tel":"","barreau":""},'
        . '"bailleur":{"nom":"","contact":"","tel":"","email":"","dossier":""},'
        . '"hygiene":{"service":"","contact":"","tel":"","email":"","ref":""},'
        . '"aide_juridictionnelle":""}' . "\n"
        . "N'INVENTE RIEN : si une info n'est pas écrite, laisse la chaîne vide. "
        . "avocat.nom = un vrai nom de personne (ex. « Me Julie Supiot »), JAMAIS une institution ou le bailleur. "
        . "bailleur = l'organisme HLM (ex. « Nantes Métropole Habitat ») + son contact/dossier si écrits. "
        . "hygiene = le service d'hygiène/santé de la mairie (SCHS) + contact/référence de PV si écrits. "
        . "aide_juridictionnelle = la référence/numéro du BAJ si présent.";
    $out = lfi_nct_ai_call($system, $md, 800);
    if ($out === null) return [];
    if (preg_match('/\{.*\}/s', $out, $m)) { $j = json_decode($m[0], true); if (is_array($j)) return $j; }
    return [];
}

/** Repli sans IA : repère les lignes commençant par une date. */
function lfi_nct_md_extract_chrono_regex($md) {
    $res = [];
    $lines = preg_split('/\r\n|\r|\n/', (string) $md);
    $mois = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';
    foreach ($lines as $ln) {
        $ln = trim(preg_replace('/^[\s\-\*#>\|]+/u', '', (string) $ln));
        if ($ln === '') continue;
        $date = ''; $rest = $ln;
        if (preg_match('#^(\d{1,2}[/.]\d{1,2}[/.]\d{2,4})\s*[:\-–—]?\s*(.*)$#u', $ln, $m)) { $date = $m[1]; $rest = $m[2]; }
        elseif (preg_match('#^(\d{4}-\d{2}-\d{2})\s*[:\-–—]?\s*(.*)$#u', $ln, $m)) { $date = $m[1]; $rest = $m[2]; }
        elseif (preg_match('#^(\d{1,2}\s+(?:' . $mois . ')\s+\d{4})\s*[:\-–—]?\s*(.*)$#iu', $ln, $m)) { $date = $m[1]; $rest = $m[2]; }
        elseif (preg_match('#^((?:' . $mois . ')\s+\d{4})\s*[:\-–—]?\s*(.*)$#iu', $ln, $m)) { $date = $m[1]; $rest = $m[2]; }
        /* Année seule en tête (ex. « 2022 : traitement anti-cafards »). */
        elseif (preg_match('#^(20\d{2})\s*[:\-–—]\s*(.+)$#u', $ln, $m)) { $date = $m[1]; $rest = $m[2]; }
        else continue;
        $rest = trim($rest);
        if ($rest !== '') $res[] = ['date' => $date, 'event' => $rest];
    }
    return $res;
}

/* ============================================================== *
 *  Test de connexion (bouton admin)                              *
 * ============================================================== */
/** Renvoie [ok(bool), message(string)] — ping léger de l'API. */
function lfi_nct_ai_ping() {
    if (lfi_nct_ai_key() === '') return [false, 'Aucune clé enregistrée.'];
    $out = lfi_nct_ai_call('Réponds uniquement par le mot: OK', 'Test de connexion.', 16);
    if ($out === null) return [false, (string) get_option('lfi_nct_claude_last_error', 'Échec inconnu.')];
    return [true, 'Connexion à Claude OK (' . lfi_nct_ai_model() . ').'];
}
