<?php
/**
 * Module Recouvrement — forcer NMH à payer malgré le refus
 *
 * Chaîne juridique complète, générée automatiquement à partir d'une
 * facture impayée :
 *
 *   1. Mise en demeure 1   — LRAR, délai 15 jours, articles L.441-10,
 *                            1719 CC, 6 loi 89-462
 *   2. Mise en demeure 2   — pénalités calculées + indemnité forfaitaire
 *                            40 €, annonce action judiciaire
 *   3. Saisine CDC         — Commission Départementale de Conciliation
 *                            (gratuit, obligatoire avant tribunal)
 *   4. Assignation TJ      — Tribunal Judiciaire de Nantes,
 *                            procédure orale sans avocat si < 10 000 €
 *   5. Plainte SCHS/ARS    — si insalubrité, arrêté préfectoral force
 *                            le bailleur à payer
 *
 * Stratégie : la brigade fait les travaux URGENTS (article 1724 CC,
 * jurisprudence constante : substitution du locataire au bailleur
 * carent), facture, et si refus on déroule la chaîne. À chaque étape,
 * un document officiel est généré, imprimable, daté.
 *
 * RÉSERVÉ À L'ADMIN.
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_REC_DBVER_KEY = 'lfi_nct_rec_db_ver';
const LFI_NCT_REC_DBVER_VAL = '2';

/* ============================================================== *
 *  DB Setup                                                        *
 * ============================================================== */
add_action('init', 'lfi_nct_recouvrement_db_setup', 7);
function lfi_nct_recouvrement_db_setup() {
    if (get_option(LFI_NCT_REC_DBVER_KEY) === LFI_NCT_REC_DBVER_VAL) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'lfi_nct_recouvrements';
    dbDelta("CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id BIGINT UNSIGNED DEFAULT NULL,
        facture_numero VARCHAR(40) DEFAULT '',
        intervention_id BIGINT UNSIGNED DEFAULT NULL,
        statut VARCHAR(20) DEFAULT 'nouveau',
        montant_initial DECIMAL(10,2) DEFAULT 0,
        med1_date DATE DEFAULT NULL,
        med2_date DATE DEFAULT NULL,
        cdc_date DATE DEFAULT NULL,
        tj_date DATE DEFAULT NULL,
        schs_date DATE DEFAULT NULL,
        paye_date DATE DEFAULT NULL,
        motif_urgence TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY facture_numero (facture_numero),
        KEY owner_user_id (owner_user_id),
        KEY statut (statut)
    ) $charset;");

    /* Migration : attribue les anciens recouvrements au premier admin */
    $admins = get_users(['role' => 'administrator', 'fields' => ['ID'], 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
    if (!empty($admins)) {
        $wpdb->query("UPDATE $t SET owner_user_id = " . (int) $admins[0]->ID . " WHERE owner_user_id IS NULL");
    }

    update_option(LFI_NCT_REC_DBVER_KEY, LFI_NCT_REC_DBVER_VAL, false);
}

/* ============================================================== *
 *  CATALOGUE DES TRAVAUX — qui paye selon la loi française         *
 *                                                                   *
 *  Trois catégories :                                               *
 *   - bailleur  : obligation légale du bailleur, REMBOURSABLE       *
 *                 (art. 1719 / 1724 CC, loi 89-462 art. 6, décret   *
 *                 2002-120 décence)                                  *
 *   - gris     : appréciation du juge selon la cause (usage normal  *
 *                 vs défaut bâtiment). À argumenter.                 *
 *   - locataire : décret 87-712 du 26/08/1987 (réparations          *
 *                 locatives). NE JAMAIS facturer NMH.                *
 *                                                                   *
 *  La liste ci-dessous est issue de la jurisprudence et des textes  *
 *  applicables au logement social. Source : Cour de cassation 3e    *
 *  civ., ADIL 44, Code de la santé publique, loi ELAN 2018.         *
 * ============================================================== */
function lfi_nct_travaux_catalogue() {
    return [
        'bailleur' => [
            'label'       => '🏛 OBLIGATION BAILLEUR — remboursable',
            'remboursable'=> true,
            'risque'      => 'faible',
            'couleur'     => '#186a3b',
            'fondement'   => 'Articles 1719 et 1724 du Code civil · Article 6 de la loi n° 89-462 · Décret n° 2002-120 (décence)',
            'types'       => [
                'humidite_moisissures_struct' => 'Moisissures de surface (défaut bâtiment / VMC HS)',
                'punaises_lit'                => 'Punaises de lit (loi ELAN 2018, L.302-16-1 CCH)',
                'rats_parties_communes'       => 'Rats venant des parties communes',
            ],
        ],
        'gris' => [
            'label'       => '⚠ APPRÉCIATION DU JUGE — à argumenter',
            'remboursable'=> null,
            'risque'      => 'moyen',
            'couleur'     => '#bd8600',
            'fondement'   => 'À justifier au cas par cas : cause structurelle vs usage normal. La photo et la datation des signalements préalables sont décisives.',
            'types'       => [
                'joints_fenetres_calfeutrage' => 'Joints fenêtres / calfeutrage thermique',
                'joints_silicone_sdb'         => 'Joint silicone SDB (si dû à humidité bailleur)',
                'reboucher_fissure_origine'   => 'Rebouchage fissure d\'origine structurelle',
                'peinture_apres_degat'        => 'Peinture après dégât bailleur (suite fuite, etc.)',
                'cafards_blattes_immeuble'    => 'Cafards (si infestation immeuble entier)',
                'placo_petit_morceau_degat'   => 'Reprise placo après dégât eau bailleur',
            ],
        ],
        'locataire' => [
            'label'       => '🚫 RÉPARATIONS LOCATIVES — NE PAS facturer NMH',
            'remboursable'=> false,
            'risque'      => 'eleve',
            'couleur'     => '#a30b25',
            'fondement'   => 'Décret n° 87-712 du 26 août 1987 (réparations locatives à la charge du locataire)',
            'types'       => [
                'robinet_goutte_usure'        => 'Robinet qui goutte (usure normale)',
                'chasse_eau_entretien'        => 'Chasse d\'eau (entretien courant)',
                'plinthe_decoll_usage'        => 'Plinthe décollée par usage',
                'porte_frotte_reglage'        => 'Porte qui frotte (réglage)',
                'siphon_evier_deboucher'      => 'Déboucher siphon évier (entretien)',
                'ampoules_fusibles'           => 'Ampoules, fusibles',
                'papier_peint_deco'           => 'Papier peint décoratif (choix locataire)',
                'peinture_deco_couleur'       => 'Peinture décorative (changement couleur)',
                'vmc_grille_nettoyage'        => 'Nettoyage grille VMC',
                'vitre_cassee_locataire'      => 'Vitre cassée par accident locataire',
                'joints_silicone_usure'       => 'Joint silicone par usure normale (sans humidité)',
                'trou_cheville_perso'         => 'Reboucher trou de cheville personnel',
                'petit_platre_perso'          => 'Petit plâtre / rebouchage cosmétique',
            ],
        ],
    ];
}

/* ============================================================== *
 *  CATALOGUE DES TARIFS — prix marché 2026 par type de tâche       *
 *                                                                   *
 *  Sources : Daleco (Nantes), Solitec, Rentokil, ISS Pest, Nuisibles*
 *  Urgence, devis Habitat Nantais, Castorama Pro, ManoMano Pro.    *
 *                                                                   *
 *  Pour chaque type :                                               *
 *   - bas    : prix mini d'une boîte sérieuse                       *
 *   - juste  : prix médian, recommandé pour la facture              *
 *   - haut   : prix d'une grosse boîte (Rentokil...) — plafond légal*
 *   - source : nom des comparateurs / boîtes consultées             *
 *                                                                   *
 *  Tu factures à la TÂCHE par défaut (le tarif horaire devient le   *
 *  fallback seulement). C'est plus défendable au tribunal car c'est *
 *  un prix de marché objectif.                                      *
 * ============================================================== */
function lfi_nct_tarif_taches_catalogue() {
    return [
        /* === DÉSINSECTISATION / DÉRATISATION (réalisable avec EPI + protocole) === */
        'punaises_lit' => [
            'bas' => 250, 'juste' => 350, 'haut' => 600,
            'unite' => 'forfait logement',
            'source' => 'Daleco Nantes 280€ · Solitec 320-450€ · Rentokil 550€',
            'detail' => 'Protocole 2 passages (J0 + J+14) : vapeur + Subito + terre de Diatomée + housse Allerzip',
        ],
        'cafards_blattes_immeuble' => [
            'bas' => 150, 'juste' => 220, 'haut' => 380,
            'unite' => 'forfait appartement',
            'source' => 'Daleco 180€ · Solitec 160-280€ · Hygiène 3D 200€ · Rentokil 350€',
            'detail' => 'Gel Goliath + IGR Sinergel + suivi J+15 et J+30 (protocole IPM)',
        ],
        'rats_parties_communes' => [
            'bas' => 100, 'juste' => 160, 'haut' => 280,
            'unite' => 'forfait par appartement',
            'source' => 'Daleco 140€ · Solitec 130-220€ · Aprolyse 160€',
            'detail' => 'Bouchage entrées laine d\'acier + pose tapettes/raticide + vérification J+7',
        ],

        /* === HUMIDITÉ / MOISISSURES DE SURFACE (réalisable avec EPI) === */
        'humidite_moisissures_struct' => [
            'bas' => 220, 'juste' => 320, 'haut' => 550,
            'unite' => 'forfait 4 m²',
            'source' => 'Habitat Nantais 280-450€ · Plâtrerie Atlantique 320€',
            'detail' => 'Démolition placo infesté + biocide Algiclair + repose BA13 hydro + enduit + 2 couches peinture',
        ],

        /* === CALFEUTRAGE / JOINTS (très accessible) === */
        'joints_fenetres_calfeutrage' => [
            'bas' => 80, 'juste' => 140, 'haut' => 220,
            'unite' => 'forfait logement complet',
            'source' => 'Calfeutrage Pro 100€ · Habitat Nantais 120-180€',
            'detail' => 'Joints EPDM Tesa Moll toutes fenêtres + film survitrage + boudin porte d\'entrée',
        ],
        'joints_silicone_sdb' => [
            'bas' => 60, 'juste' => 90, 'haut' => 140,
            'unite' => 'par pièce humide',
            'source' => 'SAV Habitat 70€ · Devispro 80-120€',
            'detail' => 'Dépose ancien joint + dégraissage + Sikaflex sanitaire + lissage propre',
        ],

        /* === PETIT PLÂTRE / REPRISE PLACO (avec tuto) === */
        'placo_petit_morceau_degat' => [
            'bas' => 60, 'juste' => 100, 'haut' => 180,
            'unite' => 'par zone < 50 cm',
            'source' => 'Plâtrerie Atlantique 80€ · Habitat Nantais 100-150€',
            'detail' => 'Tasseau + chute BA13 + bande à joint + enduit + ponçage + peinture',
        ],
        'reboucher_fissure_origine' => [
            'bas' => 80, 'juste' => 120, 'haut' => 200,
            'unite' => 'par fissure',
            'source' => 'Plâtrerie Atlantique 100€ · UnArtisan 90-150€',
            'detail' => 'Ouverture en V + enduit souple Toupret + peinture raccord',
        ],

        /* === PEINTURE (accessible, gros impact visuel) === */
        'peinture_apres_degat' => [
            'bas' => 25, 'juste' => 35, 'haut' => 55,
            'unite' => 'par m² (2 couches)',
            'source' => 'Peinture Pro 30€/m² · IziBat 28-45€/m²',
            'detail' => 'Sous-couche bloquante Julien + 2 couches peinture qualité Dulux',
        ],
    ];
}

/* Retourne la suggestion de prix pour un type donné, ou null */
function lfi_nct_tarif_for_type($type_key) {
    $cat = lfi_nct_tarif_taches_catalogue();
    return $cat[$type_key] ?? null;
}

/* Trouve la catégorie d'un type donné. Retourne null si non classé. */
function lfi_nct_travaux_classify($type_key) {
    if (!$type_key) return null;
    foreach (lfi_nct_travaux_catalogue() as $cat_key => $cat) {
        if (isset($cat['types'][$type_key])) {
            return [
                'cat_key'      => $cat_key,
                'cat_label'    => $cat['label'],
                'remboursable' => $cat['remboursable'],
                'risque'       => $cat['risque'],
                'couleur'      => $cat['couleur'],
                'fondement'    => $cat['fondement'],
                'type_label'   => $cat['types'][$type_key],
            ];
        }
    }
    return null;
}

/* ============================================================== *
 *  DÉTECTION PROBLÈME D'IMMEUBLE — argument juridique massif       *
 *                                                                   *
 *  Scanne les réponses d'enquête à la MÊME adresse et cherche le    *
 *  même type de problème. Si N >= 2 → c'est plus du locataire       *
 *  isolé, c'est un défaut structurel à la charge bailleur.          *
 *                                                                   *
 *  Mapping type_travaux_key → mot-clé enquête (problemes_types).    *
 * ============================================================== */
function lfi_nct_rec_enquete_keyword_for($type_key) {
    $map = [
        'punaises_lit'                 => 'insectes',
        'cafards_blattes_immeuble'     => 'insectes',
        'rats_parties_communes'        => 'insectes',
        'humidite_moisissures_struct'  => 'humidite',
        'joints_fenetres_calfeutrage'  => 'humidite',
        'joints_silicone_sdb'          => 'humidite',
        'placo_petit_morceau_degat'    => 'degats_eaux',
        'peinture_apres_degat'         => 'degats_eaux',
    ];
    return $map[$type_key] ?? null;
}

/* Extrait le numéro de bâtiment depuis une adresse, ou '' si absent.
   « 12 rue d'Hendaye » → « 12 », « 14bis avenue de Provence » → « 14bis ». */
function lfi_nct_rec_building_number($adresse) {
    if (!$adresse) return '';
    if (preg_match('/^\s*(\d+\s*(?:bis|ter|quater)?)/iu', (string) $adresse, $m)) {
        return strtolower(preg_replace('/\s+/', '', $m[1]));
    }
    return '';
}

/* Scanne les réponses d'enquête pour la même rue (clé canonique) avec
   le même type de problème, et ventile en :
     - meme_immeuble : entrées au même numéro
     - immeubles_voisins : autres numéros, mêmes rue
   Données ANONYMES : on ne renvoie QUE étage + gravité, JAMAIS de nom
   (RGPD : les noms ne doivent jamais apparaître dans les arguments
   juridiques publiés à NMH / au tribunal). */
function lfi_nct_rec_collective_signal($adresse, $type_key) {
    $empty = [
        'meme_immeuble'      => [],
        'immeubles_voisins'  => [],
        'numeros_voisins'    => [],   // ex: ['10', '14', '16']
        'rue_label'          => '',   // libellé de rue affichable
    ];
    if (!$adresse || !$type_key) return $empty;
    $kw = lfi_nct_rec_enquete_keyword_for($type_key);
    if (!$kw) return $empty;

    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_responses';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT adresse, etage, data FROM $t
         WHERE deleted_at IS NULL AND adresse IS NOT NULL LIMIT 800"
    ));
    if (!$rows) return $empty;

    $target_street = function_exists('lfi_nct_address_canonical_key')
        ? lfi_nct_address_canonical_key($adresse)
        : strtolower(trim($adresse));
    $target_num = lfi_nct_rec_building_number($adresse);

    $rue_label = function_exists('lfi_nct_address_canonical_display')
        ? trim(preg_replace('/^\s*\d+\s*(bis|ter|quater)?\s*/iu', '', (string) lfi_nct_address_canonical_display($adresse)))
        : preg_replace('/^\s*\d+\s*(bis|ter|quater)?\s*/iu', '', (string) $adresse);

    $meme = [];
    $voisins = [];
    $nums_voisins = [];
    foreach ($rows as $r) {
        $rk = function_exists('lfi_nct_address_canonical_key')
            ? lfi_nct_address_canonical_key($r->adresse)
            : strtolower(trim($r->adresse));
        if ($rk !== $target_street) continue;

        $data = json_decode($r->data ?? '', true);
        if (!is_array($data)) continue;
        $types = (array) ($data['problemes_types'] ?? []);
        if (!in_array($kw, $types, true)) continue;

        $rnum = lfi_nct_rec_building_number($r->adresse);
        $entry = (object) [
            'etage'   => (string) ($r->etage ?? ''),
            'gravite' => (int) ($data['problemes_gravite'] ?? 0),
        ];

        if ($target_num && $rnum === $target_num) {
            $meme[] = $entry;
        } elseif ($target_num && $rnum && $rnum !== $target_num) {
            $voisins[] = $entry;
            if (!in_array($rnum, $nums_voisins, true)) $nums_voisins[] = $rnum;
        } else {
            /* Pas de numéro identifié (rare) : on compte côté « voisins » */
            $voisins[] = $entry;
        }
    }
    sort($nums_voisins, SORT_NATURAL);
    return [
        'meme_immeuble'     => $meme,
        'immeubles_voisins' => $voisins,
        'numeros_voisins'   => $nums_voisins,
        'rue_label'         => trim($rue_label),
    ];
}

/* Calcule la catégorie majoritaire des interventions liées à une facture */
function lfi_nct_rec_categorie_facture($numero) {
    $interventions = lfi_nct_rec_interventions_by_facture($numero);
    $counts = ['bailleur' => 0, 'gris' => 0, 'locataire' => 0, 'non_classe' => 0];
    $total_eur = ['bailleur' => 0.0, 'gris' => 0.0, 'locataire' => 0.0, 'non_classe' => 0.0];
    foreach ($interventions as $i) {
        $cat = lfi_nct_travaux_classify($i->type_travaux_key ?? '');
        $key = $cat ? $cat['cat_key'] : 'non_classe';
        $counts[$key]++;
        $total_eur[$key] += (float) $i->total_ht;
    }
    return ['counts' => $counts, 'eur' => $total_eur];
}

/* ============================================================== *
 *  Helpers                                                          *
 * ============================================================== */

/* Taux d'intérêt légal moyen pratiqué (1er semestre 2026) — à
   actualiser si la Banque de France publie un nouveau taux.
   Source : https://www.banque-france.fr/fr/statistiques/taux-interet-legal */
function lfi_nct_rec_taux_interet_legal() {
    return (float) get_option('lfi_nct_rec_taux_interet', 6.65);
}

/* Pénalités L.441-10 Code de commerce : 3 × taux légal */
function lfi_nct_rec_taux_penalites() {
    return lfi_nct_rec_taux_interet_legal() * 3;
}

/* Indemnité forfaitaire de recouvrement (décret 2012-1115) */
function lfi_nct_rec_indemnite_forfaitaire() { return 40.00; }

/* Calcul des pénalités au jour J, sur N jours de retard */
function lfi_nct_rec_calc_penalites($montant_ht, $jours_retard) {
    if ($jours_retard <= 0) return 0;
    $taux_annuel = lfi_nct_rec_taux_penalites() / 100;
    return round($montant_ht * $taux_annuel * ($jours_retard / 365), 2);
}

function lfi_nct_rec_format_eur($n) {
    return number_format((float) $n, 2, ',', ' ') . ' €';
}

function lfi_nct_rec_format_date($d) {
    if (!$d) return '—';
    return wp_date('j F Y', strtotime($d));
}

/* Retourne le recouvrement associé à une facture du user courant, ou null */
function lfi_nct_rec_get_by_facture($numero) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_recouvrements';
    $owner = function_exists('lfi_nct_fact_owner_id') ? (int) lfi_nct_fact_owner_id() : (int) get_current_user_id();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE facture_numero = %s AND owner_user_id = %d", $numero, $owner));
}

/* Retourne les interventions liées à une facture du user courant */
function lfi_nct_rec_interventions_by_facture($numero) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_interventions';
    $owner = function_exists('lfi_nct_fact_owner_id') ? (int) lfi_nct_fact_owner_id() : (int) get_current_user_id();
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE facture_numero = %s AND owner_user_id = %d ORDER BY date_intervention", $numero, $owner)) ?: [];
}

/* ============================================================== *
 *  VUE : Tableau de bord recouvrements                              *
 * ============================================================== */
function lfi_nct_app_view_recouvrements() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
    $ti = $wpdb->prefix . 'lfi_nct_interventions';

    $owner = (int) lfi_nct_fact_owner_id();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tr WHERE owner_user_id = %d ORDER BY updated_at DESC LIMIT 200", $owner)) ?: [];

    /* Factures impayées sans recouvrement encore ouvert — bornées au user */
    $impayes = $wpdb->get_results($wpdb->prepare("
        SELECT facture_numero, MIN(facture_date) AS facture_date, SUM(total_ht) AS total_ht,
               GROUP_CONCAT(DISTINCT tenant_nom SEPARATOR ', ') AS noms
        FROM $ti
        WHERE owner_user_id = %d
          AND statut = 'facture'
          AND facture_numero IS NOT NULL
          AND facture_numero NOT IN (SELECT facture_numero FROM $tr WHERE owner_user_id = %d AND statut != 'paye')
        GROUP BY facture_numero
        ORDER BY facture_date
    ", $owner, $owner)) ?: [];

    lfi_nct_app_screen_open('⚖️ Recouvrement NMH', 'Forcer le bailleur à payer — chaîne juridique automatisée');

    echo '<div class="lfi-app-help" style="background:#fff3f5;border-left:4px solid #c8102e">';
    echo '<strong>⚠ Cadre juridique à respecter ABSOLUMENT.</strong><br><br>';
    echo 'NMH n\'a rien commandé, donc tu ne peux pas leur facturer directement sans précaution. Deux voies légales :<br><br>';
    echo '<strong>VOIE SÛRE — recommandée.</strong> Le LOCATAIRE est ton client. Il signe un <strong>mandat / devis</strong> qui te confie l\'intervention (les locataires ont le droit de faire les travaux urgents que le bailleur n\'a pas faits — articles 1724 et 6 loi 89-462). Tu factures au locataire, qui paye OU consigne. Puis il te subroge dans ses droits contre NMH (article 1346 CC) : tu peux ensuite réclamer remboursement à NMH en son nom. C\'est blindé juridiquement.<br><br>';
    echo '<strong>VOIE RISQUÉE — gestion d\'affaires (article 1301 CC).</strong> Tu interviens sans mandat, "utilement" pour NMH. Tu factures directement NMH. C\'est légalement possible si tu prouves : (1) urgence sanitaire avérée, (2) signalements préalables du locataire au bailleur restés sans réponse, (3) impossibilité d\'attendre. Mais le tribunal peut te débouter en disant "tu n\'avais pas qualité". Plus dur à gagner.<br><br>';
    echo '<strong>👉 Génère TOUJOURS le mandat du locataire AVANT l\'intervention.</strong> C\'est gratuit, ça prend 2 min, et ça sécurise tout. Ensuite la chaîne juridique se déroule sans accroc.';
    echo '</div>';

    /* Factures impayées prêtes à passer en recouvrement */
    if (!empty($impayes)) {
        echo '<h3 style="margin-top:18px;color:#c8102e">📥 Factures impayées (recouvrement à lancer)</h3>';
        echo '<ul class="lfi-app-list">';
        foreach ($impayes as $imp) {
            $retard = (int) ((strtotime(current_time('Y-m-d')) - strtotime($imp->facture_date)) / 86400) - 30;
            $cat = lfi_nct_rec_categorie_facture($imp->facture_numero);
            $n_locataire = $cat['counts']['locataire'];
            $n_gris      = $cat['counts']['gris'];
            $n_bailleur  = $cat['counts']['bailleur'];
            $n_non_class = $cat['counts']['non_classe'];
            $bloque      = ($n_locataire > 0 && $n_bailleur === 0 && $n_gris === 0);

            $border = $bloque ? '#a30b25' : ($n_locataire > 0 || $n_non_class > 0 ? '#bd8600' : '#186a3b');
            echo '<li class="lfi-app-card" style="border-left:4px solid ' . $border . '">';
            echo '<div class="head"><div class="who">🧾 ' . esc_html($imp->facture_numero) . '</div>';
            if ($retard > 0) echo '<div class="badge" style="background:#a30b25;color:#fff">⚠ ' . $retard . ' j de retard</div>';
            echo '</div>';
            echo '<div class="meta">';
            echo '<span class="meta-chip">📅 ' . esc_html(wp_date('j M Y', strtotime($imp->facture_date))) . '</span>';
            echo '<span class="meta-chip">👥 ' . esc_html($imp->noms) . '</span>';
            echo '<span class="meta-chip"><strong>' . lfi_nct_rec_format_eur($imp->total_ht) . '</strong></span>';
            if ($n_bailleur)  echo '<span class="meta-chip" style="background:#e8f5ea;color:#186a3b">🏛 ' . $n_bailleur . ' bailleur</span>';
            if ($n_gris)      echo '<span class="meta-chip" style="background:#fff8e6;color:#bd8600">⚠ ' . $n_gris . ' gris</span>';
            if ($n_locataire) echo '<span class="meta-chip" style="background:#fff3f5;color:#a30b25">🚫 ' . $n_locataire . ' locataire</span>';
            if ($n_non_class) echo '<span class="meta-chip" style="background:#eee;color:#666">? ' . $n_non_class . ' non classé</span>';
            echo '</div>';

            if ($bloque) {
                echo '<div style="background:#fff3f5;color:#a30b25;padding:10px 12px;border-radius:6px;margin-top:8px;font-size:.9em">';
                echo '🚫 <strong>Recouvrement bloqué.</strong> Cette facture ne contient que des réparations à la charge du locataire (décret 87-712). NMH ne paiera pas, le tribunal vous déboutera. Facturez le locataire directement.';
                echo '</div>';
            } elseif ($n_locataire > 0) {
                echo '<div style="background:#fff8e6;color:#bd8600;padding:10px 12px;border-radius:6px;margin-top:8px;font-size:.9em">';
                echo '⚠ <strong>Attention :</strong> ' . $n_locataire . ' intervention(s) sont à la charge du locataire (décret 87-712). Le recouvrement ne portera que sur les travaux bailleur / gris. Édite l\'intervention « locataire » et facture-la séparément au locataire.';
                echo '</div>';
            } elseif ($n_non_class > 0) {
                echo '<div style="background:#eee;color:#666;padding:10px 12px;border-radius:6px;margin-top:8px;font-size:.9em">';
                echo '? <strong>' . $n_non_class . ' intervention(s) non classée(s)</strong> (anciennes données, type libre). Édite-les pour choisir le type exact dans le catalogue, sinon ton dossier sera fragile au tribunal.';
                echo '</div>';
            }

            echo '<div class="row-actions">';
            if (!$bloque) {
                echo '<form method="post" style="display:inline">';
                wp_nonce_field('lfi_rec_open');
                echo '<input type="hidden" name="lfi_rec_open" value="1">';
                echo '<input type="hidden" name="facture_numero" value="' . esc_attr($imp->facture_numero) . '">';
                echo '<input type="hidden" name="montant" value="' . esc_attr($imp->total_ht) . '">';
                echo '<button type="submit" class="btn-primary">⚖️ Ouvrir un dossier de recouvrement</button>';
                echo '</form>';
            }
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('facture', ['numero' => $imp->facture_numero])) . '">🧾 Voir facture</a>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /* Handler ouverture de dossier */
    if (!empty($_POST['lfi_rec_open']) && check_admin_referer('lfi_rec_open')) {
        $num = sanitize_text_field(wp_unslash($_POST['facture_numero'] ?? ''));
        $montant = (float) ($_POST['montant'] ?? 0);
        if ($num && !lfi_nct_rec_get_by_facture($num)) {
            $wpdb->insert($tr, [
                'owner_user_id'  => $owner,
                'facture_numero' => $num,
                'statut'         => 'nouveau',
                'montant_initial'=> $montant,
            ]);
            $id = (int) $wpdb->insert_id;
            wp_safe_redirect(lfi_nct_app_url('recouvrement-dossier', ['id' => $id]));
            exit;
        }
    }

    /* Liste des recouvrements en cours */
    if (!empty($rows)) {
        echo '<h3 style="margin-top:24px;color:#c8102e">📂 Dossiers en cours</h3>';
        $statuts = [
            'nouveau' => ['📥', 'Nouveau',         '#bd8600'],
            'med1'    => ['📨', 'Mise en demeure', '#c8102e'],
            'med2'    => ['📨', 'Relance + pénalités', '#a30b25'],
            'cdc'     => ['⚖️', 'CDC saisie',      '#7a0000'],
            'tj'      => ['🏛', 'Tribunal saisi',  '#5a0000'],
            'schs'    => ['🏥', 'Plainte SCHS',    '#0066a3'],
            'paye'    => ['💰', 'Payé',            '#186a3b'],
            'abandonne' => ['✕', 'Abandonné',     '#777'],
        ];
        echo '<ul class="lfi-app-list">';
        foreach ($rows as $r) {
            $lbl = $statuts[$r->statut] ?? ['?', $r->statut, '#888'];
            echo '<li class="lfi-app-card">';
            echo '<div class="head"><div class="who">⚖️ ' . esc_html($r->facture_numero) . '</div>';
            echo '<div class="badge" style="background:' . esc_attr($lbl[2]) . ';color:#fff">' . $lbl[0] . ' ' . esc_html($lbl[1]) . '</div>';
            echo '</div>';
            echo '<div class="meta">';
            echo '<span class="meta-chip"><strong>' . lfi_nct_rec_format_eur($r->montant_initial) . '</strong></span>';
            if ($r->med1_date) echo '<span class="meta-chip">MED1 : ' . esc_html(wp_date('j M', strtotime($r->med1_date))) . '</span>';
            if ($r->med2_date) echo '<span class="meta-chip">MED2 : ' . esc_html(wp_date('j M', strtotime($r->med2_date))) . '</span>';
            if ($r->cdc_date)  echo '<span class="meta-chip">CDC : ' . esc_html(wp_date('j M', strtotime($r->cdc_date))) . '</span>';
            if ($r->tj_date)   echo '<span class="meta-chip">TJ : ' . esc_html(wp_date('j M', strtotime($r->tj_date))) . '</span>';
            echo '</div>';
            echo '<div class="row-actions">';
            echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('recouvrement-dossier', ['id' => $r->id])) . '">📂 Ouvrir le dossier</a>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    if (empty($impayes) && empty($rows)) {
        echo '<div class="lfi-app-empty">Aucune facture impayée pour le moment. Quand NMH refuse de payer une facture, elle apparaît ici.</div>';
    }

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Dossier d'un recouvrement (timeline + actions)             *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_dossier() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
    $owner = (int) lfi_nct_fact_owner_id();
    $id = (int) ($_GET['id'] ?? 0);
    /* Borne owner — un GA ne peut PAS voir le dossier d'un autre */
    $rec = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tr WHERE id = %d AND owner_user_id = %d", $id, $owner)) : null;
    if (!$rec) {
        lfi_nct_app_screen_open('Dossier introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('recouvrements')) . '">← Retour</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }

    /* Actions de progression d'étape */
    foreach (['med1', 'med2', 'cdc', 'tj', 'schs'] as $etape) {
        if (!empty($_POST['lfi_rec_step_' . $etape]) && check_admin_referer('lfi_rec_step_' . $etape)) {
            $wpdb->update($tr, [
                $etape . '_date' => current_time('Y-m-d'),
                'statut' => $etape,
            ], ['id' => $rec->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('recouvrement-dossier', ['id' => $rec->id, 'step' => $etape]));
            exit;
        }
    }
    if (!empty($_POST['lfi_rec_paye']) && check_admin_referer('lfi_rec_paye')) {
        $wpdb->update($tr, ['statut' => 'paye', 'paye_date' => current_time('Y-m-d')], ['id' => $rec->id, 'owner_user_id' => $owner]);
        /* Met aussi la facture en payée — borné owner */
        $wpdb->update($wpdb->prefix . 'lfi_nct_interventions',
            ['statut' => 'paye', 'paye_date' => current_time('Y-m-d')],
            ['facture_numero' => $rec->facture_numero, 'owner_user_id' => $owner]
        );
        wp_safe_redirect(lfi_nct_app_url('recouvrement-dossier', ['id' => $rec->id, 'paid' => 1]));
        exit;
    }

    /* Sauvegarde motif d'urgence + notes */
    if (!empty($_POST['lfi_rec_notes']) && check_admin_referer('lfi_rec_notes')) {
        $wpdb->update($tr, [
            'motif_urgence' => sanitize_textarea_field(wp_unslash($_POST['motif_urgence'] ?? '')),
            'notes'         => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ], ['id' => $rec->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('recouvrement-dossier', ['id' => $rec->id, 'saved' => 1]));
        exit;
    }
    $rec = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tr WHERE id = %d AND owner_user_id = %d", $id, $owner)); // refresh

    $interventions = lfi_nct_rec_interventions_by_facture($rec->facture_numero);
    $facture_date = !empty($interventions) ? $interventions[0]->facture_date : null;
    $jours_retard = $facture_date ? max(0, (int) ((strtotime(current_time('Y-m-d')) - strtotime($facture_date)) / 86400) - 30) : 0;
    $penalites = lfi_nct_rec_calc_penalites($rec->montant_initial, $jours_retard);
    $du_total = $rec->montant_initial + $penalites + lfi_nct_rec_indemnite_forfaitaire();

    lfi_nct_app_screen_open('⚖️ Dossier ' . $rec->facture_numero, lfi_nct_rec_format_eur($du_total) . ' dû (capital + pénalités)');

    if (!empty($_GET['step']))  lfi_nct_app_flash('✅ Étape enregistrée. Génère le document avec le bouton ci-dessous.');
    if (!empty($_GET['paid']))  lfi_nct_app_flash('💰 Recouvrement clôturé. NMH a payé.');
    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Dossier mis à jour.');

    /* Bandeau résumé chiffres */
    echo '<div class="lfi-app-stats-grid">';
    echo '<div class="stat"><div class="ico">💵</div><div class="n">' . lfi_nct_rec_format_eur($rec->montant_initial) . '</div><div class="l">Capital</div></div>';
    echo '<div class="stat"><div class="ico">📈</div><div class="n">' . lfi_nct_rec_format_eur($penalites) . '</div><div class="l">Pénalités (' . $jours_retard . ' j)</div></div>';
    echo '<div class="stat"><div class="ico">💼</div><div class="n">40 €</div><div class="l">Indemnité forfaitaire</div></div>';
    echo '<div class="stat"><div class="ico">🎯</div><div class="n">' . lfi_nct_rec_format_eur($du_total) . '</div><div class="l">TOTAL DÛ</div></div>';
    echo '</div>';

    /* Classification juridique des travaux concernés */
    $cat = lfi_nct_rec_categorie_facture($rec->facture_numero);
    echo '<div style="margin-top:14px;background:#fff;border-radius:10px;padding:14px 16px;border:1px solid #eee">';
    echo '<h4 style="margin:0 0 8px;color:#c8102e">🏛 Classification juridique des travaux de cette facture</h4>';
    echo '<table style="width:100%;font-size:.9em;border-collapse:collapse">';
    $catalogue = lfi_nct_travaux_catalogue();
    foreach (['bailleur', 'gris', 'locataire'] as $k) {
        if ($cat['counts'][$k] === 0) continue;
        $c = $catalogue[$k];
        echo '<tr><td style="padding:6px 0;border-bottom:1px solid #f0f0f0">';
        echo '<strong style="color:' . esc_attr($c['couleur']) . '">' . esc_html($c['label']) . '</strong><br>';
        echo '<small style="color:#666">' . esc_html($c['fondement']) . '</small>';
        echo '</td>';
        echo '<td style="text-align:right;padding:6px 0;border-bottom:1px solid #f0f0f0">';
        echo $cat['counts'][$k] . ' interv. · <strong>' . lfi_nct_rec_format_eur($cat['eur'][$k]) . '</strong>';
        echo '</td></tr>';
    }
    if ($cat['counts']['non_classe'] > 0) {
        echo '<tr><td style="padding:6px 0;color:#666"><em>Non classé (anciennes données)</em></td>';
        echo '<td style="text-align:right;color:#666">' . $cat['counts']['non_classe'] . ' interv. · ' . lfi_nct_rec_format_eur($cat['eur']['non_classe']) . '</td></tr>';
    }
    echo '</table>';
    if ($cat['counts']['locataire'] > 0) {
        echo '<div style="background:#fff3f5;color:#a30b25;padding:10px;border-radius:6px;margin-top:10px;font-size:.9em">⚠ <strong>' . lfi_nct_rec_format_eur($cat['eur']['locataire']) . '</strong> de cette facture sont des réparations locatives (décret 87-712). Le tribunal réduira ta demande d\'autant. Tu peux retirer ces lignes ou les facturer au locataire séparément.</div>';
    }
    echo '</div>';

    /* Motif d'urgence (pour les documents juridiques) */
    echo '<form method="post" class="lfi-app-form" style="margin-top:14px">';
    wp_nonce_field('lfi_rec_notes');
    echo '<input type="hidden" name="lfi_rec_notes" value="1">';
    echo '<label><strong>Motif d\'urgence ayant justifié l\'intervention</strong>'
        . '<textarea name="motif_urgence" rows="4" placeholder="Ex: Moisissures envahissantes dans la chambre de l\'enfant, médecin traitant ayant constaté aggravation asthmatique. VMC HS depuis 18 mois signalée par 3 LRAR au bailleur restées sans réponse. Logement devenu indécent au sens du décret 2002-120.">' . esc_textarea($rec->motif_urgence ?? '') . '</textarea>'
        . '<small>Sera repris dans la mise en demeure et l\'assignation. Cite le décret 2002-120 et les démarches préalables faites par le locataire.</small></label>';
    echo '<label><strong>Notes internes (non publiées)</strong><textarea name="notes" rows="2">' . esc_textarea($rec->notes ?? '') . '</textarea></label>';
    echo '<button class="btn-primary" type="submit">💾 Enregistrer</button>';
    echo '</form>';

    /* Timeline des étapes */
    echo '<h3 style="margin-top:24px;color:#c8102e">📋 Chaîne juridique — déroule étape par étape</h3>';

    $etapes = [
        'mandat' => [
            'titre' => '0. Mandat du locataire (à signer AVANT l\'intervention)',
            'desc'  => 'Document essentiel pour la légalité. Le locataire t\'autorise à intervenir en son nom, en application de l\'article 1724 CC, et te subroge dans ses droits contre NMH (article 1346 CC). Sans ça, la facture est attaquable.',
            'date'  => null, // pas de DB column, c'est juste un doc à générer
            'doc'   => 'recouvrement-doc-mandat',
            'icone' => '✍️',
        ],
        'med1' => [
            'titre' => '1. Mise en demeure (LRAR)',
            'desc'  => 'Lettre recommandée avec AR au bailleur, délai 15 jours. Articles 1719 CC, 6 loi 89-462, L.441-10 C. com. C\'est le préalable légal obligatoire.',
            'date'  => $rec->med1_date,
            'doc'   => 'recouvrement-doc-med1',
            'icone' => '📨',
        ],
        'med2' => [
            'titre' => '2. Mise en demeure de relance',
            'desc'  => 'Délai 15 jours, ajout des pénalités calculées (3× taux légal) + indemnité forfaitaire 40 €. Annonce de la saisine CDC à défaut.',
            'date'  => $rec->med2_date,
            'doc'   => 'recouvrement-doc-med2',
            'icone' => '📨',
        ],
        'cdc' => [
            'titre' => '3. Saisine Commission de Conciliation',
            'desc'  => 'Procédure GRATUITE, OBLIGATOIRE avant le tribunal pour les litiges locatifs (article 20 loi 89-462). Avis sous 2 mois.',
            'date'  => $rec->cdc_date,
            'doc'   => 'recouvrement-doc-cdc',
            'icone' => '⚖️',
        ],
        'tj' => [
            'titre' => '4. Assignation Tribunal Judiciaire',
            'desc'  => 'TJ de Nantes, procédure orale sans avocat obligatoire (< 10 000 €). Demande : remboursement intégral + pénalités + article 700 + trouble de jouissance.',
            'date'  => $rec->tj_date,
            'doc'   => 'recouvrement-doc-tj',
            'icone' => '🏛',
        ],
        'schs' => [
            'titre' => '5. Plainte SCHS / ARS (parallèle si insalubrité)',
            'desc'  => 'Service Communal d\'Hygiène et Santé de Nantes. Si insalubrité avérée, arrêté préfectoral oblige NMH à payer les travaux conservatoires.',
            'date'  => $rec->schs_date,
            'doc'   => 'recouvrement-doc-schs',
            'icone' => '🏥',
        ],
    ];

    echo '<ul class="lfi-app-list">';
    foreach ($etapes as $key => $e) {
        $done = !empty($e['date']);
        $is_mandat = ($key === 'mandat');
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($done || $is_mandat ? '#186a3b' : '#c8102e') . '">';
        echo '<div class="head"><div class="who">' . $e['icone'] . ' ' . esc_html($e['titre']) . '</div>';
        if ($done) echo '<div class="badge" style="background:#186a3b;color:#fff">✓ ' . esc_html(wp_date('j M Y', strtotime($e['date']))) . '</div>';
        echo '</div>';
        echo '<div class="com">' . esc_html($e['desc']) . '</div>';
        echo '<div class="row-actions">';
        if (!$done && !$is_mandat) {
            echo '<form method="post" style="display:inline">';
            wp_nonce_field('lfi_rec_step_' . $key);
            echo '<input type="hidden" name="lfi_rec_step_' . $key . '" value="1">';
            echo '<button class="btn-primary" type="submit">📤 Marquer cette étape comme envoyée</button>';
            echo '</form>';
        }
        if ($done || $key === 'med1' || $is_mandat) {
            echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url($e['doc'], ['id' => $rec->id])) . '" target="_blank">📄 Générer le document (imprimable)</a>';
        }
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    /* Bouton "payé" */
    if ($rec->statut !== 'paye') {
        echo '<form method="post" style="margin-top:18px">';
        wp_nonce_field('lfi_rec_paye');
        echo '<input type="hidden" name="lfi_rec_paye" value="1">';
        echo '<button type="submit" class="btn-primary big" onclick="return confirm(\'Confirmer : NMH a payé la totalité ?\')">💰 NMH a payé — clôturer le dossier</button>';
        echo '</form>';
    }

    echo '<div style="margin-top:18px"><a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('recouvrements')) . '">← Tous les recouvrements</a></div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  Helpers de génération de documents (header commun)               *
 * ============================================================== */
function lfi_nct_rec_doc_open($titre_doc) {
    global $wpdb;
    $rid = (int) ($_GET['id'] ?? 0);
    if (!$rid) wp_die('Dossier manquant');
    $tr = $wpdb->prefix . 'lfi_nct_recouvrements';
    $owner = (int) lfi_nct_fact_owner_id();
    $rec = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tr WHERE id = %d AND owner_user_id = %d", $rid, $owner));
    if (!$rec) wp_die('Dossier introuvable');

    $presta = lfi_nct_fact_prestataire();
    $bailleur = lfi_nct_fact_bailleur();
    $interventions = lfi_nct_rec_interventions_by_facture($rec->facture_numero);
    $facture_date = !empty($interventions) ? $interventions[0]->facture_date : null;
    $jours_retard = $facture_date ? max(0, (int) ((strtotime(current_time('Y-m-d')) - strtotime($facture_date)) / 86400) - 30) : 0;
    $penalites = lfi_nct_rec_calc_penalites($rec->montant_initial, $jours_retard);
    $du_total = $rec->montant_initial + $penalites + lfi_nct_rec_indemnite_forfaitaire();

    lfi_nct_app_screen_open($titre_doc, $rec->facture_numero);

    return [
        'rec'           => $rec,
        'presta'        => $presta,
        'bailleur'      => $bailleur,
        'interventions' => $interventions,
        'facture_date'  => $facture_date,
        'jours_retard'  => $jours_retard,
        'penalites'     => $penalites,
        'du_total'      => $du_total,
    ];
}

function lfi_nct_rec_doc_styles() {
    ?>
    <style>
    .lfi-rec-doc {
        background: #fff; padding: 30px 40px; border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,.1); color: #1a1a1a;
        font-family: "Times New Roman", Georgia, serif; line-height: 1.5;
        font-size: 12pt; max-width: 800px; margin: 0 auto;
    }
    .lfi-rec-doc h1 { font-size: 1.4em; color: #000; text-align: center; margin: 18px 0 28px; text-transform: uppercase; letter-spacing: 1px; }
    .lfi-rec-doc h2 { font-size: 1.1em; color: #c8102e; margin: 20px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
    .lfi-rec-doc .expediteur, .lfi-rec-doc .destinataire { margin-bottom: 16px; }
    .lfi-rec-doc .expediteur { text-align: left; }
    .lfi-rec-doc .destinataire { text-align: right; margin-left: 50%; }
    .lfi-rec-doc .lieu-date { text-align: right; margin: 20px 0; font-style: italic; }
    .lfi-rec-doc .objet { font-weight: bold; margin: 22px 0 14px; }
    .lfi-rec-doc .lrar { font-weight: bold; color: #c8102e; text-transform: uppercase; }
    .lfi-rec-doc p { margin: 10px 0; text-align: justify; }
    .lfi-rec-doc .citations { background: #f8f8f8; padding: 12px 16px; border-left: 4px solid #c8102e; font-size: .9em; margin: 14px 0; }
    .lfi-rec-doc .signature { margin-top: 60px; text-align: right; }
    .lfi-rec-doc table.detail { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: .9em; }
    .lfi-rec-doc table.detail td { border-bottom: 1px solid #ddd; padding: 6px 8px; }
    .lfi-rec-doc table.detail .num { text-align: right; }
    .lfi-rec-doc table.detail tr.total td { background: #fff8e6; font-weight: bold; font-size: 1.1em; border-top: 2px solid #c8102e; }
    .lfi-rec-doc .pj { margin-top: 28px; padding-top: 14px; border-top: 1px solid #ddd; font-size: .9em; }
    .lfi-print-bar { text-align: center; margin: 18px 0; }
    .lfi-print-bar button { background: #c8102e; color: #fff; border: 0; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; }
    @media print {
        .lfi-app-navbar, .lfi-quickbar, .no-print, .row-actions, .lfi-print-bar { display: none !important; }
        body { background: #fff !important; padding: 0 !important; }
        .lfi-rec-doc { box-shadow: none; border: 0; padding: 0; max-width: 100%; }
        .lfi-app, .lfi-app-screen, .lfi-app-screen-body { padding: 0 !important; margin: 0 !important; }
    }
    </style>
    <div class="lfi-print-bar no-print"><button onclick="window.print()">🖨 Imprimer ce document</button></div>
    <?php
}

function lfi_nct_rec_doc_header($presta, $bailleur, $lrar = true) {
    echo '<div class="expediteur">';
    echo '<strong>' . esc_html($presta['nom'] ?? '—') . '</strong><br>';
    if (!empty($presta['adresse']))  echo esc_html($presta['adresse']) . '<br>';
    if (!empty($presta['cp_ville'])) echo esc_html($presta['cp_ville']) . '<br>';
    if (!empty($presta['tel']))      echo 'Tél. : ' . esc_html($presta['tel']) . '<br>';
    if (!empty($presta['email']))    echo 'Mél. : ' . esc_html($presta['email']) . '<br>';
    if (!empty($presta['siret']))    echo 'SIRET : ' . esc_html($presta['siret']);
    echo '</div>';

    echo '<div class="destinataire">';
    echo '<strong>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</strong><br>';
    if (!empty($bailleur['adresse']))  echo esc_html($bailleur['adresse']) . '<br>';
    if (!empty($bailleur['cp_ville'])) echo esc_html($bailleur['cp_ville']);
    echo '</div>';

    if ($lrar) echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';

    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';
}

/* ============================================================== *
 *  DOC 0 : Mandat du locataire — clé de voûte juridique             *
 *                                                                   *
 *  Document à signer par le locataire AVANT toute intervention.     *
 *  Il :                                                              *
 *   - mandate Fabrice (article 1984 CC) pour réaliser les travaux   *
 *     conservatoires d'urgence,                                      *
 *   - active la substitution prévue par l'article 1724 CC,           *
 *   - subroge Fabrice dans les droits du locataire contre NMH       *
 *     (article 1346 CC),                                             *
 *   - autorise Fabrice à agir en recouvrement au nom du locataire.   *
 *                                                                   *
 *  Avec ce mandat signé, la facture (au locataire) est légale, et   *
 *  Fabrice peut réclamer remboursement à NMH au nom du locataire.   *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_mandat() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('✍️ Mandat du locataire');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    $i = !empty($interventions) ? $interventions[0] : null;
    $locataire_nom    = $i ? trim($i->tenant_prenom . ' ' . $i->tenant_nom) : '[Nom du locataire]';
    $locataire_adr    = $i && $i->tenant_adresse ? $i->tenant_adresse : '[Adresse du logement]';
    $locataire_etage  = $i && $i->tenant_etage ? ' (étage ' . $i->tenant_etage . ')' : '';

    echo '<div class="lfi-rec-doc">';

    echo '<h1>Mandat de substitution & subrogation</h1>';
    echo '<p style="text-align:center;font-style:italic;margin-bottom:30px">Travaux conservatoires d\'urgence — Logement locatif social</p>';

    echo '<h2>Entre les soussignés :</h2>';

    echo '<p><strong>Le Mandant :</strong><br>';
    echo $locataire_nom . ',<br>';
    echo 'demeurant ' . $locataire_adr . $locataire_etage . ', à Nantes (44200),<br>';
    echo 'locataire de Nantes Métropole Habitat,<br>';
    echo '<em>ci-après dénommé « le Locataire »</em></p>';

    echo '<p><strong>Le Mandataire :</strong><br>';
    echo '<strong>' . esc_html($presta['nom'] ?? '—') . '</strong>,<br>';
    if (!empty($presta['adresse'])) echo esc_html($presta['adresse']) . ', ';
    if (!empty($presta['cp_ville'])) echo esc_html($presta['cp_ville']) . ',<br>';
    echo 'exerçant en qualité d\'auto-entrepreneur du bâtiment';
    if (!empty($presta['siret'])) echo ', n° SIRET ' . esc_html($presta['siret']);
    echo ',<br>';
    echo '<em>ci-après dénommé « le Mandataire »</em></p>';

    echo '<h2>Préambule</h2>';

    echo '<p>Le logement loué par le Locataire à Nantes Métropole Habitat présente, à la date des présentes, des désordres graves et urgents — décrits ci-après — qui mettent en péril la santé, la sécurité ou la jouissance paisible des occupants.</p>';

    if (!empty($rec->motif_urgence)) {
        echo '<p><strong>Désordres constatés :</strong></p>';
        echo '<p>' . nl2br(esc_html($rec->motif_urgence)) . '</p>';
    } else {
        echo '<p><strong>Désordres constatés :</strong> [à compléter — décrire la nature exacte du problème (moisissures, VMC HS, fuite, etc.) et son impact sanitaire].</p>';
    }

    echo '<p>Le Locataire a signalé ces désordres au bailleur à plusieurs reprises, sans qu\'aucune intervention efficace ne soit réalisée dans des délais compatibles avec la sauvegarde de sa santé et de ses biens.</p>';

    echo '<p>Le Locataire, en application des dispositions ci-dessous, choisit de faire réaliser les travaux conservatoires nécessaires par le Mandataire, à la charge financière in fine du bailleur.</p>';

    echo '<h2>Fondements juridiques</h2>';

    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Article 1719 du Code civil</strong> : obligation du bailleur de délivrer un logement décent et d\'en assurer l\'entretien ;</li>';
    echo '<li><strong>Article 1724 du Code civil</strong> : les réparations urgentes incombent au bailleur ;</li>';
    echo '<li><strong>Article 6 de la loi n° 89-462 du 6 juillet 1989</strong> : obligation d\'entretien du bailleur ;</li>';
    echo '<li><strong>Décret n° 2002-120 du 30 janvier 2002</strong> : critères du logement décent ;</li>';
    echo '<li><strong>Articles 1984 et suivants du Code civil</strong> : contrat de mandat ;</li>';
    echo '<li><strong>Article 1346 du Code civil</strong> : subrogation conventionnelle.</li>';
    echo '</ul>';
    echo '<p style="margin:8px 0 0"><strong>Jurisprudence constante</strong> de la Cour de cassation reconnaissant au locataire le droit de se substituer au bailleur défaillant et d\'obtenir remboursement des travaux urgents (Cass. civ. 3e, 24 mai 2000, n° 98-19.357 ; Cass. civ. 3e, 17 septembre 2015, n° 14-12.949).</p>';
    echo '</div>';

    echo '<h2>Article 1 — Mandat</h2>';
    echo '<p>Le Locataire donne pouvoir au Mandataire de réaliser, dans son logement et pour son compte, les travaux conservatoires d\'urgence nécessaires à la remise en conformité du logement avec les critères de décence, conformément au devis qui lui a été remis et qu\'il accepte expressément.</p>';

    echo '<h2>Article 2 — Prix et règlement</h2>';
    echo '<p>Le Locataire s\'engage à régler au Mandataire le prix des travaux, conformément à la facture émise à l\'issue de l\'intervention.</p>';
    echo '<p><em>Modalités de règlement convenues :</em></p>';
    echo '<ul style="margin-left:20px">';
    echo '<li>☐ Règlement comptant à réception de facture</li>';
    echo '<li>☐ Règlement par consignation à la Caisse des Dépôts et Consignations, dans l\'attente du remboursement par le bailleur</li>';
    echo '<li>☐ Règlement échelonné en ____ mensualités à compter du ____________</li>';
    echo '<li>☐ Règlement différé : à réception du remboursement par le bailleur</li>';
    echo '</ul>';

    echo '<h2>Article 3 — Substitution au bailleur</h2>';
    echo '<p>En application de l\'article 1724 du Code civil et de la jurisprudence rappelée ci-dessus, les sommes ainsi avancées par le Locataire constituent une créance de celui-ci contre Nantes Métropole Habitat, bailleur défaillant à son obligation d\'entretien.</p>';

    echo '<h2>Article 4 — Subrogation au profit du Mandataire</h2>';
    echo '<p>Le Locataire <strong>subroge expressément le Mandataire dans tous ses droits, actions et recours</strong> contre Nantes Métropole Habitat, à hauteur du montant des travaux et accessoires (pénalités, indemnité forfaitaire, frais de procédure), en application de l\'<strong>article 1346 du Code civil</strong>.</p>';
    echo '<p>En conséquence, le Mandataire est autorisé, au nom du Locataire et pour son propre compte :</p>';
    echo '<ul>';
    echo '<li>à mettre en demeure Nantes Métropole Habitat de procéder au remboursement ;</li>';
    echo '<li>à saisir la Commission Départementale de Conciliation de Loire-Atlantique ;</li>';
    echo '<li>à saisir le Tribunal Judiciaire de Nantes pour obtenir condamnation du bailleur ;</li>';
    echo '<li>à saisir le Service Communal d\'Hygiène et Santé (SCHS) de la Ville de Nantes et l\'Agence Régionale de Santé en cas d\'insalubrité ;</li>';
    echo '<li>à recevoir, encaisser et donner quittance des sommes remboursées par le bailleur, à concurrence de sa créance.</li>';
    echo '</ul>';

    echo '<h2>Article 5 — Bonne foi et information</h2>';
    echo '<p>Le Locataire et le Mandataire agissent de bonne foi. Le Mandataire s\'engage à informer le Locataire à chaque étape de la procédure, et à lui remettre copie de l\'intégralité des courriers, décisions et règlements obtenus.</p>';

    echo '<h2>Article 6 — Loi applicable et juridiction</h2>';
    echo '<p>Le présent mandat est soumis au droit français. En cas de différend entre les parties, le Tribunal Judiciaire de Nantes sera seul compétent.</p>';

    echo '<div style="margin-top:40px;display:flex;gap:40px;justify-content:space-between">';
    echo '<div style="flex:1">';
    echo '<p><strong>Pour le Locataire (mandant) :</strong></p>';
    echo '<p>Fait à Nantes, le _________________ 20___</p>';
    echo '<p style="margin-top:30px;border-top:1px solid #999;padding-top:6px;width:80%"><em>Lu et approuvé. Bon pour mandat et subrogation à hauteur du montant des travaux.</em></p>';
    echo '<p style="margin-top:30px"><strong>Signature :</strong></p>';
    echo '<div style="height:50px"></div>';
    echo '</div>';

    echo '<div style="flex:1">';
    echo '<p><strong>Pour le Mandataire :</strong></p>';
    echo '<p>Fait à Nantes, le _________________ 20___</p>';
    echo '<p style="margin-top:30px;border-top:1px solid #999;padding-top:6px;width:80%"><em>Bon pour acceptation du mandat.</em></p>';
    echo '<p style="margin-top:30px"><strong>Signature :</strong></p>';
    echo '<div style="height:50px"></div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="pj"><strong>Annexe :</strong> Devis détaillé des travaux à réaliser, accepté par le Locataire.</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  DOC 1 : Mise en demeure initiale (15 jours)                      *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_med1() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('📨 Mise en demeure');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';
    lfi_nct_rec_doc_header($presta, $bailleur);

    echo '<p class="objet">Objet : Mise en demeure de paiement de la facture n° ' . esc_html($rec->facture_numero) . ' — ' . lfi_nct_rec_format_eur($rec->montant_initial) . '</p>';

    echo '<p>Madame, Monsieur,</p>';

    echo '<p>Le ' . esc_html(lfi_nct_rec_format_date($facture_date)) . ', je vous ai adressé la facture référencée en objet, d\'un montant de <strong>' . lfi_nct_rec_format_eur($rec->montant_initial) . '</strong>, en règlement des prestations réalisées en urgence sanitaire dans les logements suivants, dont vous êtes le bailleur :</p>';

    echo '<table class="detail">';
    foreach ($interventions as $i) {
        $logement = trim($i->tenant_prenom . ' ' . $i->tenant_nom);
        if ($i->tenant_adresse) $logement .= ', ' . $i->tenant_adresse;
        if ($i->tenant_etage) $logement .= ' (étage ' . $i->tenant_etage . ')';
        echo '<tr><td>' . esc_html(wp_date('d/m/Y', strtotime($i->date_intervention))) . '</td>';
        echo '<td>' . esc_html($logement) . '</td>';
        echo '<td><em>' . esc_html($i->type_travaux) . '</em></td>';
        echo '<td class="num">' . lfi_nct_rec_format_eur($i->total_ht) . '</td></tr>';
    }
    echo '</table>';

    if (!empty($rec->motif_urgence)) {
        echo '<p><strong>Contexte et justification de l\'urgence :</strong></p>';
        echo '<p>' . nl2br(esc_html($rec->motif_urgence)) . '</p>';
    }

    echo '<p>Conformément aux articles <strong>1719 du Code civil</strong> et <strong>6 de la loi n° 89-462 du 6 juillet 1989</strong>, le bailleur est tenu de délivrer au locataire un logement décent et d\'en assurer l\'entretien permettant un usage normal. Le <strong>décret n° 2002-120 du 30 janvier 2002</strong> précise les caractéristiques d\'un logement décent.</p>';

    echo '<p>Les locataires concernés se trouvant dans des situations d\'urgence sanitaire avérée — non traitées par vos services malgré leurs signalements préalables — j\'ai été contraint, en application du <strong>principe jurisprudentiel constant de la substitution du locataire au bailleur défaillant</strong> (Cass. civ. 3e, 24 mai 2000, n° 98-19.357 ; jurisprudence confirmée constamment depuis), de réaliser les travaux conservatoires d\'urgence dont le coût est à votre charge en application de l\'<strong>article 1724 du Code civil</strong>.</p>';

    echo '<p>À ce jour, le délai contractuel de paiement de <strong>30 jours</strong>, mentionné sur la facture, est expiré sans qu\'aucun règlement n\'ait été enregistré.</p>';

    echo '<p>En conséquence, par la présente lettre recommandée avec accusé de réception, je vous mets en demeure, en application des <strong>articles 1217, 1226 et 1231-1 du Code civil</strong>, de procéder au règlement intégral de la somme de <strong>' . lfi_nct_rec_format_eur($rec->montant_initial) . '</strong> sous un délai de <strong>QUINZE (15) JOURS</strong> à compter de la réception de la présente.</p>';

    echo '<div class="citations">';
    echo '<strong>À défaut de règlement dans ce délai, je serai contraint de :</strong>';
    echo '<ul>';
    echo '<li>Calculer et exiger les pénalités de retard au taux légal majoré (trois fois le taux d\'intérêt légal, <strong>article L.441-10 du Code de commerce</strong>) ;</li>';
    echo '<li>Réclamer l\'indemnité forfaitaire pour frais de recouvrement de <strong>40 €</strong> (décret n° 2012-1115 du 2 octobre 2012) ;</li>';
    echo '<li>Saisir la <strong>Commission Départementale de Conciliation</strong> en application de l\'article 20 de la loi du 6 juillet 1989 ;</li>';
    echo '<li>Engager une procédure devant le <strong>Tribunal Judiciaire de Nantes</strong>, avec demande au titre de l\'article 700 du Code de procédure civile ;</li>';
    echo '<li>Saisir le <strong>Service Communal d\'Hygiène et Santé</strong> de Nantes Métropole pour faire constater l\'insalubrité, et, le cas échéant, l\'<strong>Agence Régionale de Santé</strong>.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p>Je reste à votre disposition pour tout échange permettant un règlement amiable de ce litige.</p>';

    echo '<p>Veuillez agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.</p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    echo '— Copie de la facture n° ' . esc_html($rec->facture_numero) . '<br>';
    echo '— Justificatifs des interventions (photos, devis matériaux)<br>';
    echo '— Le cas échéant : copie des signalements LRAR préalables des locataires</div>';

    echo '</div>';

    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  DOC 2 : Mise en demeure de relance (avec pénalités calculées)    *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_med2() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('📨 Mise en demeure — Relance');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';
    lfi_nct_rec_doc_header($presta, $bailleur);

    echo '<p class="objet">Objet : SECONDE mise en demeure — facture n° ' . esc_html($rec->facture_numero) . ' — actualisation avec pénalités</p>';

    echo '<p>Madame, Monsieur,</p>';

    echo '<p>Par lettre recommandée avec accusé de réception en date du <strong>' . esc_html(lfi_nct_rec_format_date($rec->med1_date)) . '</strong>, je vous ai mis en demeure de régler la facture n° ' . esc_html($rec->facture_numero) . ' d\'un montant initial de ' . lfi_nct_rec_format_eur($rec->montant_initial) . '.</p>';

    echo '<p>À ce jour, malgré l\'expiration du délai de quinze (15) jours imparti, <strong>aucun règlement n\'est intervenu</strong>.</p>';

    echo '<p>En conséquence, et conformément aux annonces de ma précédente mise en demeure, je suis contraint d\'actualiser le montant des sommes qui me sont dues :</p>';

    echo '<table class="detail">';
    echo '<tr><td>Capital — facture n° ' . esc_html($rec->facture_numero) . '</td><td class="num">' . lfi_nct_rec_format_eur($rec->montant_initial) . '</td></tr>';
    echo '<tr><td>Pénalités de retard (art. L.441-10 C. com.) — ' . $jours_retard . ' jours × ' . number_format(lfi_nct_rec_taux_penalites(), 2, ',', ' ') . ' % annuel</td><td class="num">' . lfi_nct_rec_format_eur($penalites) . '</td></tr>';
    echo '<tr><td>Indemnité forfaitaire pour frais de recouvrement (décret 2012-1115)</td><td class="num">' . lfi_nct_rec_format_eur(lfi_nct_rec_indemnite_forfaitaire()) . '</td></tr>';
    echo '<tr class="total"><td>TOTAL DÛ AU ' . esc_html(wp_date('j F Y')) . '</td><td class="num">' . lfi_nct_rec_format_eur($du_total) . '</td></tr>';
    echo '</table>';

    echo '<p>Par la présente, en application des <strong>articles 1226, 1231-1, 1231-6 du Code civil</strong> et <strong>L.441-10 du Code de commerce</strong>, je vous mets formellement en demeure, sous un ultime délai de <strong>QUINZE (15) JOURS</strong> à compter de la réception de la présente, de procéder au règlement intégral de la somme actualisée de <strong>' . lfi_nct_rec_format_eur($du_total) . '</strong>.</p>';

    echo '<div class="citations">';
    echo '<strong>À défaut de règlement intégral dans ce dernier délai, je saisirai :</strong>';
    echo '<ol>';
    echo '<li>La <strong>Commission Départementale de Conciliation</strong> (CDC) de Loire-Atlantique, procédure préalable et obligatoire, en application de l\'<strong>article 20 de la loi n° 89-462 du 6 juillet 1989</strong> ;</li>';
    echo '<li>Le <strong>Tribunal Judiciaire de Nantes</strong>, compétent en matière de litiges locatifs, pour obtenir :<ul>';
    echo '<li>l\'exécution forcée de votre obligation contractuelle (article 1217 CC) ;</li>';
    echo '<li>le paiement de la somme principale et des pénalités ;</li>';
    echo '<li>des dommages-intérêts pour trouble de jouissance subi par les locataires (article 1719 CC) ;</li>';
    echo '<li>l\'application de l\'<strong>article 700 du Code de procédure civile</strong> au titre des frais irrépétibles.</li></ul></li>';
    echo '</ol>';
    echo '</div>';

    echo '<p>Le coût de ces procédures, ainsi que tous les frais et débours qu\'elles génèreront, seront mis à votre charge.</p>';

    echo '<p>Cette ultime relance vous offre la possibilité d\'éviter une procédure contentieuse.</p>';

    echo '<p>Veuillez agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.</p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  DOC 3 : Saisine CDC (Commission Départementale de Conciliation) *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_cdc() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('⚖️ Saisine Commission de Conciliation');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';

    echo '<div class="expediteur">';
    echo '<strong>' . esc_html($presta['nom'] ?? '—') . '</strong><br>';
    if (!empty($presta['adresse']))  echo esc_html($presta['adresse']) . '<br>';
    if (!empty($presta['cp_ville'])) echo esc_html($presta['cp_ville']) . '<br>';
    if (!empty($presta['tel']))      echo 'Tél. : ' . esc_html($presta['tel']) . '<br>';
    if (!empty($presta['email']))    echo 'Mél. : ' . esc_html($presta['email']);
    echo '</div>';

    echo '<div class="destinataire">';
    echo '<strong>Commission Départementale de Conciliation<br>de Loire-Atlantique</strong><br>';
    echo 'Direction Départementale des Territoires et de la Mer<br>';
    echo '10 boulevard Gaston Doumergue<br>';
    echo '44262 NANTES CEDEX 2';
    echo '</div>';

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<h1>Saisine de la Commission Départementale de Conciliation</h1>';

    echo '<p class="objet">Objet : Litige entre ' . esc_html($presta['nom'] ?? '') . ' et Nantes Métropole Habitat — défaut de paiement de prestations exécutées en substitution du bailleur défaillant</p>';

    echo '<h2>Identification du saisissant</h2>';
    echo '<p>' . esc_html($presta['nom'] ?? '') . ', auto-entrepreneur, ';
    if (!empty($presta['siret'])) echo 'immatriculé sous le n° SIRET ' . esc_html($presta['siret']) . ', ';
    echo 'demeurant ' . esc_html($presta['adresse'] ?? '') . ' ' . esc_html($presta['cp_ville'] ?? '') . '.</p>';

    echo '<h2>Identification du bailleur mis en cause</h2>';
    echo '<p><strong>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</strong>, office public de l\'habitat, ';
    if (!empty($bailleur['adresse'])) echo 'sis ' . esc_html($bailleur['adresse']) . ' ' . esc_html($bailleur['cp_ville']) . '.';
    echo '</p>';

    echo '<h2>Faits et objet du litige</h2>';

    echo '<p>Madame la Présidente, Mesdames, Messieurs les membres de la Commission,</p>';

    echo '<p>J\'ai l\'honneur de saisir votre Commission, conformément à l\'<strong>article 20 de la loi n° 89-462 du 6 juillet 1989</strong>, du litige qui m\'oppose à Nantes Métropole Habitat et qui concerne le défaut de paiement de prestations que j\'ai été contraint d\'exécuter en substitution de ce bailleur défaillant à l\'égard de ses obligations légales envers ses locataires.</p>';

    echo '<p>En ma qualité d\'auto-entrepreneur du secteur du bâtiment, je suis intervenu en urgence sanitaire dans les logements suivants, dont Nantes Métropole Habitat est le bailleur :</p>';

    echo '<table class="detail">';
    echo '<tr><td><strong>Date</strong></td><td><strong>Locataire / Logement</strong></td><td><strong>Nature</strong></td><td class="num"><strong>Montant</strong></td></tr>';
    foreach ($interventions as $i) {
        $logement = trim($i->tenant_prenom . ' ' . $i->tenant_nom);
        if ($i->tenant_adresse) $logement .= ', ' . $i->tenant_adresse;
        if ($i->tenant_etage) $logement .= ' (ét. ' . $i->tenant_etage . ')';
        echo '<tr><td>' . esc_html(wp_date('d/m/Y', strtotime($i->date_intervention))) . '</td>';
        echo '<td>' . esc_html($logement) . '</td>';
        echo '<td><em>' . esc_html($i->type_travaux) . '</em></td>';
        echo '<td class="num">' . lfi_nct_rec_format_eur($i->total_ht) . '</td></tr>';
    }
    echo '</table>';

    if (!empty($rec->motif_urgence)) {
        echo '<p><strong>Contexte d\'urgence sanitaire :</strong></p>';
        echo '<p>' . nl2br(esc_html($rec->motif_urgence)) . '</p>';
    }

    echo '<h2>Fondements juridiques de la demande</h2>';

    echo '<p>Le bailleur Nantes Métropole Habitat manque à ses obligations légales fixées par :</p>';

    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>Article 1719 du Code civil</strong> : obligation du bailleur de délivrer un logement décent et d\'en assurer l\'entretien en état de servir à l\'usage prévu par le contrat ;</li>';
    echo '<li><strong>Article 6 de la loi n° 89-462</strong> : obligation d\'entretien et de délivrance d\'un logement en bon état d\'usage et de réparation ;</li>';
    echo '<li><strong>Décret n° 2002-120 du 30 janvier 2002</strong> : critères du logement décent ;</li>';
    echo '<li><strong>Article 1724 du Code civil</strong> : les réparations urgentes incombent au bailleur ;</li>';
    echo '<li><strong>Article 20-1 de la loi n° 89-462</strong> : recours du locataire en cas de non-décence.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p>La <strong>jurisprudence constante</strong> (Cass. civ. 3e, 24 mai 2000, n° 98-19.357 ; et plus récemment Cass. civ. 3e, 17 septembre 2015, n° 14-12.949) reconnaît au locataire — et, par voie de subrogation contractuelle, au tiers intervenu à sa demande en cas d\'urgence — le <strong>droit de se substituer au bailleur carent pour réaliser les travaux conservatoires d\'urgence</strong>, et d\'en obtenir le remboursement intégral.</p>';

    echo '<h2>Démarches préalables effectuées</h2>';

    echo '<table class="detail">';
    echo '<tr><td>Émission de la facture</td><td class="num">' . esc_html(lfi_nct_rec_format_date($facture_date)) . '</td></tr>';
    echo '<tr><td>Mise en demeure n° 1 (LRAR)</td><td class="num">' . esc_html(lfi_nct_rec_format_date($rec->med1_date)) . '</td></tr>';
    if ($rec->med2_date) {
        echo '<tr><td>Mise en demeure de relance (LRAR)</td><td class="num">' . esc_html(lfi_nct_rec_format_date($rec->med2_date)) . '</td></tr>';
    }
    echo '<tr><td>Délai de paiement contractuel</td><td class="num">30 jours</td></tr>';
    echo '<tr><td>Jours de retard à ce jour</td><td class="num">' . $jours_retard . ' jours</td></tr>';
    echo '</table>';

    echo '<h2>Demandes</h2>';

    echo '<p>En conséquence, et avant toute saisine du Tribunal Judiciaire de Nantes, je saisis votre Commission afin :</p>';

    echo '<ul>';
    echo '<li>de constater le manquement de Nantes Métropole Habitat à ses obligations légales ;</li>';
    echo '<li>de favoriser un règlement amiable du litige ;</li>';
    echo '<li>d\'établir un avis motivé portant sur le bien-fondé de ma créance, d\'un montant total au jour de la présente de <strong>' . lfi_nct_rec_format_eur($du_total) . '</strong>, capital, pénalités et indemnité forfaitaire comprises.</li>';
    echo '</ul>';

    echo '<p>Je me tiens à la disposition de votre Commission pour toute audition complémentaire.</p>';

    echo '<p>Je vous prie d\'agréer, Madame la Présidente, Mesdames, Messieurs, l\'expression de mes salutations respectueuses.</p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    echo '— Copie de la facture n° ' . esc_html($rec->facture_numero) . '<br>';
    echo '— Copie des mises en demeure (avec AR)<br>';
    echo '— Justificatifs photographiques des interventions<br>';
    echo '— Devis et factures des matériaux<br>';
    echo '— Signalements préalables des locataires au bailleur (si disponibles)</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  DOC 4 : Assignation Tribunal Judiciaire                          *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_tj() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('🏛 Requête Tribunal Judiciaire');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';

    echo '<h1>REQUÊTE AUX FINS DE PAIEMENT<br><small>Tribunal Judiciaire de Nantes — Procédure orale</small></h1>';

    echo '<p><strong>Tribunal Judiciaire de Nantes</strong><br>';
    echo 'Quai François Mitterrand<br>';
    echo '44921 Nantes Cedex 9</p>';

    echo '<h2>POUR :</h2>';
    echo '<p>' . esc_html($presta['nom'] ?? '') . ', ';
    if (!empty($presta['siret'])) echo 'auto-entrepreneur, n° SIRET ' . esc_html($presta['siret']) . ', ';
    echo 'demeurant ' . esc_html($presta['adresse'] ?? '') . ', ' . esc_html($presta['cp_ville'] ?? '') . ',</p>';
    echo '<p style="margin-left:30px"><em>Demandeur, agissant en personne, sans avocat (procédure orale, article R.211-3-23 COJ et 761 CPC, montant inférieur à 10 000 €).</em></p>';

    echo '<h2>CONTRE :</h2>';
    echo '<p><strong>Nantes Métropole Habitat</strong>, office public de l\'habitat, ';
    if (!empty($bailleur['siret'])) echo 'SIRET ' . esc_html($bailleur['siret']) . ', ';
    echo 'dont le siège est ' . esc_html($bailleur['adresse'] ?? '8 rue de la Tour d\'Auvergne') . ', ' . esc_html($bailleur['cp_ville'] ?? '44000 Nantes') . ',</p>';
    echo '<p style="margin-left:30px"><em>Défendeur.</em></p>';

    echo '<h2>I — RAPPEL DES FAITS</h2>';

    echo '<p>Entre le ' . esc_html(lfi_nct_rec_format_date($interventions[0]->date_intervention ?? null)) . ' et le ' . esc_html(lfi_nct_rec_format_date(end($interventions)->date_intervention ?? null)) . ', le requérant est intervenu en urgence sanitaire dans des logements appartenant au défendeur, à la demande directe et expresse des locataires, ces derniers étant exposés à un risque sanitaire imminent que le bailleur, dûment alerté, s\'était abstenu de traiter.</p>';

    if (!empty($rec->motif_urgence)) {
        echo '<p><strong>Nature de l\'urgence sanitaire :</strong></p>';
        echo '<p>' . nl2br(esc_html($rec->motif_urgence)) . '</p>';
    }

    echo '<p>Les prestations détaillées suivantes ont été facturées au défendeur le ' . esc_html(lfi_nct_rec_format_date($facture_date)) . ', pour un total de ' . lfi_nct_rec_format_eur($rec->montant_initial) . ' :</p>';

    echo '<table class="detail">';
    foreach ($interventions as $i) {
        $logement = trim($i->tenant_prenom . ' ' . $i->tenant_nom);
        if ($i->tenant_adresse) $logement .= ', ' . $i->tenant_adresse;
        echo '<tr><td>' . esc_html(wp_date('d/m/Y', strtotime($i->date_intervention))) . '</td>';
        echo '<td>' . esc_html($logement) . '</td>';
        echo '<td><em>' . esc_html($i->type_travaux) . '</em></td>';
        echo '<td class="num">' . lfi_nct_rec_format_eur($i->total_ht) . '</td></tr>';
    }
    echo '</table>';

    echo '<p>Le défendeur n\'a procédé à aucun règlement, malgré :</p>';
    echo '<ul>';
    echo '<li>une mise en demeure adressée par lettre recommandée avec accusé de réception le ' . esc_html(lfi_nct_rec_format_date($rec->med1_date)) . ' ;</li>';
    if ($rec->med2_date) echo '<li>une seconde mise en demeure de relance, accompagnée du décompte des pénalités, le ' . esc_html(lfi_nct_rec_format_date($rec->med2_date)) . ' ;</li>';
    if ($rec->cdc_date)  echo '<li>la saisine de la Commission Départementale de Conciliation de Loire-Atlantique le ' . esc_html(lfi_nct_rec_format_date($rec->cdc_date)) . ', en application de l\'article 20 de la loi du 6 juillet 1989.</li>';
    echo '</ul>';

    echo '<h2>II — DISCUSSION EN DROIT</h2>';

    echo '<p><strong>1) Sur l\'obligation du bailleur de délivrer et d\'entretenir un logement décent.</strong></p>';
    echo '<p>L\'<strong>article 1719 du Code civil</strong> impose au bailleur, à titre d\'obligation principale, de délivrer au preneur un logement décent et d\'en assurer l\'entretien permettant un usage normal.</p>';
    echo '<p>L\'<strong>article 6 de la loi du 6 juillet 1989</strong> et le <strong>décret n° 2002-120 du 30 janvier 2002</strong> précisent les caractéristiques techniques de la décence et imposent au bailleur l\'obligation continue d\'entretien.</p>';

    echo '<p><strong>2) Sur la substitution du tiers au bailleur défaillant en cas d\'urgence sanitaire.</strong></p>';
    echo '<p>L\'<strong>article 1724 du Code civil</strong> dispose que les réparations urgentes incombent au bailleur.</p>';
    echo '<p>La <strong>jurisprudence de la Cour de cassation</strong> reconnaît au locataire, et par subrogation contractuelle au tiers intervenu à sa demande, le droit de se substituer au bailleur défaillant pour faire réaliser les travaux conservatoires urgents, et d\'en obtenir le remboursement (notamment Cass. civ. 3e, 24 mai 2000, n° 98-19.357 ; Cass. civ. 3e, 17 septembre 2015, n° 14-12.949).</p>';

    echo '<p><strong>3) Sur le défaut de paiement et la responsabilité contractuelle du défendeur.</strong></p>';
    echo '<p>Les <strong>articles 1217, 1231-1 et 1231-6 du Code civil</strong> permettent au créancier de l\'obligation inexécutée d\'en poursuivre l\'exécution forcée et d\'obtenir des dommages-intérêts.</p>';
    echo '<p>L\'<strong>article L.441-10 du Code de commerce</strong> impose, pour toute facture impayée à terme, le paiement de pénalités de retard au taux de trois fois le taux d\'intérêt légal en vigueur.</p>';
    echo '<p>Le <strong>décret n° 2012-1115 du 2 octobre 2012</strong> fixe à 40 € l\'indemnité forfaitaire pour frais de recouvrement.</p>';

    echo '<h2>III — DEMANDES</h2>';

    echo '<p>Au regard de ce qui précède, il est demandé au Tribunal Judiciaire de Nantes de bien vouloir :</p>';

    echo '<div class="citations">';
    echo '<ul>';
    echo '<li><strong>CONSTATER</strong> le manquement de Nantes Métropole Habitat à ses obligations contractuelles et légales ;</li>';
    echo '<li><strong>CONDAMNER</strong> Nantes Métropole Habitat à verser au requérant la somme principale de <strong>' . lfi_nct_rec_format_eur($rec->montant_initial) . '</strong> au titre du paiement de la facture n° ' . esc_html($rec->facture_numero) . ' ;</li>';
    echo '<li><strong>CONDAMNER</strong> Nantes Métropole Habitat au paiement de la somme de <strong>' . lfi_nct_rec_format_eur($penalites) . '</strong> au titre des pénalités de retard à ce jour, à parfaire jusqu\'au paiement effectif (article L.441-10 C. com.) ;</li>';
    echo '<li><strong>CONDAMNER</strong> Nantes Métropole Habitat au paiement de la somme de <strong>40 €</strong> au titre de l\'indemnité forfaitaire pour frais de recouvrement (décret 2012-1115) ;</li>';
    echo '<li><strong>CONDAMNER</strong> Nantes Métropole Habitat à la somme de <strong>1 500 €</strong> au titre de l\'<strong>article 700 du Code de procédure civile</strong> ;</li>';
    echo '<li><strong>CONDAMNER</strong> Nantes Métropole Habitat aux entiers dépens de l\'instance ;</li>';
    echo '<li><strong>ORDONNER</strong> l\'exécution provisoire du jugement à intervenir.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p><strong>SOUS TOUTES RÉSERVES</strong></p>';

    echo '<div class="lieu-date">Fait à Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';
    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '<br><em>Demandeur</em></div>';

    echo '<div class="pj"><strong>BORDEREAU DE PIÈCES :</strong>';
    echo '<ol>';
    echo '<li>Facture n° ' . esc_html($rec->facture_numero) . '</li>';
    echo '<li>Justificatifs photographiques des interventions (avant / pendant / après)</li>';
    echo '<li>Devis et factures d\'achat des matériaux</li>';
    echo '<li>Mise en demeure n° 1 du ' . esc_html(lfi_nct_rec_format_date($rec->med1_date)) . ' + accusé de réception</li>';
    if ($rec->med2_date) echo '<li>Mise en demeure de relance du ' . esc_html(lfi_nct_rec_format_date($rec->med2_date)) . ' + AR</li>';
    if ($rec->cdc_date)  echo '<li>Saisine CDC du ' . esc_html(lfi_nct_rec_format_date($rec->cdc_date)) . ' + accusé / avis de la commission</li>';
    echo '<li>Signalements préalables des locataires au bailleur</li>';
    echo '<li>Décompte détaillé des pénalités au jour de l\'audience</li>';
    echo '</ol></div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}

/* ============================================================== *
 *  DOC 5 : Plainte SCHS / ARS pour insalubrité                      *
 * ============================================================== */
function lfi_nct_app_view_recouvrement_doc_schs() {
    if (!lfi_nct_can_use_brigade()) return;
    $ctx = lfi_nct_rec_doc_open('🏥 Plainte SCHS / ARS');
    extract($ctx);
    lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';

    echo '<div class="expediteur">';
    echo '<strong>' . esc_html($presta['nom'] ?? '—') . '</strong><br>';
    if (!empty($presta['adresse']))  echo esc_html($presta['adresse']) . '<br>';
    if (!empty($presta['cp_ville'])) echo esc_html($presta['cp_ville']) . '<br>';
    if (!empty($presta['tel']))      echo 'Tél. : ' . esc_html($presta['tel']) . '<br>';
    if (!empty($presta['email']))    echo 'Mél. : ' . esc_html($presta['email']);
    echo '</div>';

    echo '<div class="destinataire">';
    echo '<strong>Service Communal d\'Hygiène et Santé (SCHS)<br>de la Ville de Nantes</strong><br>';
    echo 'Direction Santé Publique<br>';
    echo 'Centre Municipal de Santé<br>';
    echo '1 rue de Bouillé<br>';
    echo '44000 NANTES<br><br>';
    echo '<em>Copie : Agence Régionale de Santé Pays de la Loire,<br>17 boulevard Gaston Doumergue, 44262 Nantes Cedex 2</em>';
    echo '</div>';

    echo '<p class="lrar">Lettre recommandée avec accusé de réception</p>';
    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<h1>Signalement d\'insalubrité — demande d\'enquête</h1>';

    echo '<p class="objet">Objet : Signalement d\'insalubrité en logement social — demande d\'inspection et de mise en œuvre des procédures prévues aux articles L.1331-22 et suivants du Code de la santé publique</p>';

    echo '<p>Madame, Monsieur,</p>';

    echo '<p>En ma qualité d\'intervenant professionnel du secteur du bâtiment, je suis amené à constater régulièrement, lors d\'interventions d\'urgence sanitaire chez les locataires de <strong>Nantes Métropole Habitat</strong> situés sur le quartier du Clos Toreau (Nantes Sud), des situations objectives d\'insalubrité que je souhaite, par la présente, porter à la connaissance de vos services.</p>';

    echo '<p>Les logements concernés par les interventions répertoriées ci-après présentent des manquements graves et persistants aux critères de la décence définis par le <strong>décret n° 2002-120 du 30 janvier 2002</strong>, et constituent vraisemblablement des situations d\'insalubrité au sens des <strong>articles L.1331-22 et suivants du Code de la santé publique</strong>, après refonte par l\'ordonnance n° 2020-1144 du 16 septembre 2020 :</p>';

    echo '<table class="detail">';
    foreach ($interventions as $i) {
        $logement = trim($i->tenant_prenom . ' ' . $i->tenant_nom);
        if ($i->tenant_adresse) $logement .= ', ' . $i->tenant_adresse;
        if ($i->tenant_etage) $logement .= ' (ét. ' . $i->tenant_etage . ')';
        echo '<tr><td>' . esc_html(wp_date('d/m/Y', strtotime($i->date_intervention))) . '</td>';
        echo '<td>' . esc_html($logement) . '</td>';
        echo '<td><em>' . esc_html($i->type_travaux) . '</em></td></tr>';
    }
    echo '</table>';

    if (!empty($rec->motif_urgence)) {
        echo '<p><strong>Nature des risques sanitaires constatés :</strong></p>';
        echo '<p>' . nl2br(esc_html($rec->motif_urgence)) . '</p>';
    }

    echo '<h2>Carence avérée du bailleur</h2>';

    echo '<p>Les locataires concernés ont, pour la plupart, signalé ces situations à Nantes Métropole Habitat à de multiples reprises, sans réponse adéquate. Le bailleur a, par ailleurs, refusé le règlement des prestations conservatoires d\'urgence que j\'ai été contraint de réaliser en substitution (facture n° ' . esc_html($rec->facture_numero) . ', d\'un montant de ' . lfi_nct_rec_format_eur($rec->montant_initial) . ', demeurée impayée à ce jour malgré mises en demeure).</p>';

    echo '<h2>Demandes</h2>';

    echo '<p>Je sollicite respectueusement de vos services :</p>';
    echo '<ul>';
    echo '<li>la <strong>diligence d\'enquêtes d\'insalubrité</strong> sur les logements ci-dessus visés, par les agents assermentés du SCHS ;</li>';
    echo '<li>le cas échéant, la <strong>saisine du préfet</strong> aux fins d\'arrêté d\'insalubrité (article L.1331-22 et suivants CSP) ;</li>';
    echo '<li>la <strong>mise en demeure du bailleur</strong> de procéder aux travaux de remise en conformité ;</li>';
    echo '<li>la <strong>transmission</strong> du présent signalement à l\'Agence Régionale de Santé Pays de la Loire pour suivi sanitaire (notamment risques saturnisme, mycotoxines, allergènes respiratoires).</li>';
    echo '</ul>';

    echo '<p>Je me tiens à la disposition de vos services pour toute audition, expertise contradictoire ou transmission de pièces complémentaires (photographies datées, devis, échantillons éventuels).</p>';

    echo '<p>Je vous prie de croire, Madame, Monsieur, à l\'assurance de ma considération distinguée.</p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? '') . '</div>';

    echo '<div class="pj"><strong>Pièces jointes :</strong><br>';
    echo '— Photographies datées des désordres constatés<br>';
    echo '— Comptes-rendus d\'intervention<br>';
    echo '— Copie de la facture n° ' . esc_html($rec->facture_numero) . ' impayée<br>';
    echo '— Coordonnées des locataires concernés (à transmettre sur demande sécurisée des services)</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}
