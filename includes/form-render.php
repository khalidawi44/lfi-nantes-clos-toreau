<?php
if (!defined('ABSPATH')) exit;

function lfi_nct_render_form() {
    ob_start();
    ?>
    <form method="POST" id="lfi-nct-form" class="lfi-survey">
        <?php wp_nonce_field('lfi_nct_submit_nonce', 'lfi_nct_nonce'); ?>

        <div class="lfi-progress"><div class="lfi-progress-bar" style="width:14%"></div></div>

        <div class="lfi-step active" data-step="1"><?php lfi_nct_section_1_logement(); ?></div>
        <div class="lfi-step" data-step="2"><?php lfi_nct_section_2_insectes(); ?></div>
        <div class="lfi-step" data-step="3"><?php lfi_nct_section_3_humidite(); ?></div>
        <div class="lfi-step" data-step="4"><?php lfi_nct_section_4_thermique(); ?></div>
        <div class="lfi-step" data-step="5"><?php lfi_nct_section_6_demarches(); ?></div>
        <div class="lfi-step" data-step="6"><?php lfi_nct_section_7_demande(); ?></div>
        <div class="lfi-step" data-step="7"><?php lfi_nct_section_8_contact(); ?></div>

        <div class="lfi-nav">
            <button type="button" class="lfi-prev" disabled>← Précédent</button>
            <button type="button" class="lfi-next">Suivant →</button>
            <button type="submit" name="lfi_nct_submit" value="1" class="lfi-submit" style="display:none;">Envoyer l'enquête</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

function lfi_nct_section_1_logement() {
    ?>
    <h2>Section 1 — Le logement</h2>
    <p class="lfi-section-desc">Quelques infos générales sur le logement enquêté.</p>

    <label class="lfi-field">
        <span class="lfi-label">Adresse de l'immeuble <span class="req">*</span></span>
        <span class="lfi-help">Indiquez la rue et le numéro (sans étage ni numéro d'appartement).</span>
        <input type="text" name="adresse" required placeholder="Ex : 12 rue Saint-Aignan">
    </label>

    <div class="lfi-info-box"><strong>Bailleur :</strong> Nantes Métropole Habitat (NMH)</div>

    <label class="lfi-field">
        <span class="lfi-label">Étage <span class="req">*</span></span>
        <span class="lfi-help">Indiquez « RDC » pour rez-de-chaussée.</span>
        <input type="text" name="etage" required placeholder="Ex : 3, RDC, sous-sol">
    </label>

    <label class="lfi-field">
        <span class="lfi-label">Année d'arrivée dans le logement <span class="req">*</span></span>
        <input type="number" name="annee_arrivee" required min="1950" max="2030" placeholder="Ex : 2018">
    </label>
    <?php
}

function lfi_nct_section_2_insectes() {
    ?>
    <h2>Section 2 — Insectes et nuisibles</h2>
    <p class="lfi-section-desc">Présence d'insectes ou de nuisibles dans le logement.</p>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Présence d'insectes ou de nuisibles <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="insectes_presence" value="oui" required> Oui</label>
        <label class="lfi-radio"><input type="radio" name="insectes_presence" value="non" required> Non</label>
    </fieldset>

    <div data-show-if="insectes_presence:oui">
        <fieldset class="lfi-field">
            <legend class="lfi-label">Si oui, lesquels ? (cocher tout ce qui s'applique)</legend>
            <label class="lfi-check"><input type="checkbox" name="insectes_types[]" value="cafards"> Cafards</label>
            <label class="lfi-check"><input type="checkbox" name="insectes_types[]" value="punaises_lit"> Punaises de lit</label>
            <label class="lfi-check"><input type="checkbox" name="insectes_types[]" value="rongeurs"> Rongeurs (souris, rats)</label>
            <label class="lfi-check"><input type="checkbox" name="insectes_types[]" value="fourmis"> Fourmis</label>
            <label class="lfi-check"><input type="checkbox" name="insectes_types[]" value="autres"> Autres</label>
            <input type="text" name="insectes_types_autres" placeholder="Si autres, préciser" class="lfi-other-input">
        </fieldset>

        <fieldset class="lfi-field">
            <legend class="lfi-label">Depuis quand ?</legend>
            <label class="lfi-radio"><input type="radio" name="insectes_depuis" value="moins_6mois"> Moins de 6 mois</label>
            <label class="lfi-radio"><input type="radio" name="insectes_depuis" value="6_12mois"> 6 à 12 mois</label>
            <label class="lfi-radio"><input type="radio" name="insectes_depuis" value="plus_1an"> Plus d'un an</label>
        </fieldset>

        <fieldset class="lfi-field">
            <legend class="lfi-label">Gravité ressentie</legend>
            <span class="lfi-help">1 = supportable, 5 = invivable</span>
            <div class="lfi-scale">
                <label class="lfi-radio-btn"><input type="radio" name="insectes_gravite" value="1"> 1</label>
                <label class="lfi-radio-btn"><input type="radio" name="insectes_gravite" value="2"> 2</label>
                <label class="lfi-radio-btn"><input type="radio" name="insectes_gravite" value="3"> 3</label>
                <label class="lfi-radio-btn"><input type="radio" name="insectes_gravite" value="4"> 4</label>
                <label class="lfi-radio-btn"><input type="radio" name="insectes_gravite" value="5"> 5</label>
            </div>
        </fieldset>
    </div>
    <?php
}

function lfi_nct_section_3_humidite() {
    ?>
    <h2>Section 3 — Humidité</h2>
    <p class="lfi-section-desc">L'humidité est un critère majeur de logement indécent (décret du 30 janvier 2002). Toute trace, tache, odeur ou condensation persistante compte.</p>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Présence d'humidité visible ou ressentie <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="humidite_presence" value="oui_visible" required> Oui, traces ou taches visibles</label>
        <label class="lfi-radio"><input type="radio" name="humidite_presence" value="oui_ressentie" required> Oui, ressentie sans traces (mur froid, condensation, odeur)</label>
        <label class="lfi-radio"><input type="radio" name="humidite_presence" value="oui_suspicion" required> Oui, suspicion mais pas certain·e</label>
        <label class="lfi-radio"><input type="radio" name="humidite_presence" value="non" required> Non, aucun signe</label>
    </fieldset>

    <div data-show-if="humidite_presence:oui_visible|oui_ressentie|oui_suspicion">
        <fieldset class="lfi-field">
            <legend class="lfi-label">Où voyez-vous précisément l'humidité ?</legend>
            <span class="lfi-help">La localisation permet d'identifier l'origine (remontée capillaire, ventilation, fuite, condensation, infiltration).</span>
            <?php
            $loc_humidite = [
                'salon_mur_ext_bas' => 'Salon — mur extérieur en bas (suspicion remontée capillaire)',
                'salon_mur_ext_haut' => 'Salon — mur extérieur en haut (suspicion défaut ventilation)',
                'salon_plafond' => 'Salon — plafond (suspicion fuite logement au-dessus ou toiture)',
                'salon_fenetres' => 'Salon — autour des fenêtres (suspicion menuiserie défectueuse)',
                'salon_derriere_meubles' => 'Salon — derrière meubles contre mur extérieur',
                'chambre_mur_ext_bas' => 'Chambre — mur extérieur en bas',
                'chambre_mur_ext_haut' => 'Chambre — mur extérieur en haut',
                'chambre_plafond' => 'Chambre — plafond',
                'chambre_fenetres' => 'Chambre — autour des fenêtres',
                'chambre_derriere_meubles' => 'Chambre — derrière tête de lit ou meubles',
                'cuisine_sous_evier' => 'Cuisine — sous l\'évier ou autour des canalisations',
                'cuisine_vmc' => 'Cuisine — autour de la VMC ou hotte',
                'cuisine_plafond' => 'Cuisine — plafond',
                'cuisine_derriere_meubles' => 'Cuisine — derrière meubles ou électroménagers',
                'sdb_joints' => 'Salle de bain — joints du carrelage (douche, baignoire)',
                'sdb_plafond' => 'Salle de bain — plafond (suspicion VMC HS)',
                'sdb_baignoire' => 'Salle de bain — autour baignoire ou receveur',
                'sdb_lavabo' => 'Salle de bain — derrière lavabo ou WC',
                'wc_separe' => 'WC séparés — mur ou sol',
                'couloir_plafond' => 'Couloir / entrée — plafond',
                'couloir_porte' => 'Couloir / entrée — mur près de la porte palière',
                'cave' => 'Cave, cellier ou box',
                'combles' => 'Combles ou grenier',
                'garage' => 'Garage attenant',
            ];
            foreach ($loc_humidite as $val => $label) {
                echo '<label class="lfi-check"><input type="checkbox" name="humidite_loc[]" value="' . esc_attr($val) . '"> ' . esc_html($label) . '</label>';
            }
            ?>
            <label class="lfi-check"><input type="checkbox" name="humidite_loc[]" value="autres"> Autres</label>
            <input type="text" name="humidite_loc_autres" placeholder="Si autres, préciser" class="lfi-other-input">
        </fieldset>

        <fieldset class="lfi-field">
            <legend class="lfi-label">Gravité de l'humidité observée</legend>
            <label class="lfi-radio"><input type="radio" name="humidite_gravite" value="1"> 1 — À peine visible : taches discrètes, pas de gêne</label>
            <label class="lfi-radio"><input type="radio" name="humidite_gravite" value="2"> 2 — Visible mais limité : zone < 0,5 m², stable</label>
            <label class="lfi-radio"><input type="radio" name="humidite_gravite" value="3"> 3 — Modérée : zone 0,5-2 m², extension lente, gêne occasionnelle</label>
            <label class="lfi-radio"><input type="radio" name="humidite_gravite" value="4"> 4 — Sévère : zone > 2 m², moisissures abondantes, gêne quotidienne</label>
            <label class="lfi-radio"><input type="radio" name="humidite_gravite" value="5"> 5 — Insalubre : impact santé, plusieurs pièces, dégradations majeures</label>
        </fieldset>

        <fieldset class="lfi-field">
            <legend class="lfi-label">Conséquences observées chez vous</legend>
            <span class="lfi-help">Les effets sur la santé sont juridiquement très importants pour caractériser un logement indécent.</span>
            <?php
            $cons_humidite = [
                'moisissures_noires' => 'Moisissures noires visibles',
                'moisissures_vertes' => 'Moisissures vertes ou blanches',
                'salpetre' => 'Salpêtre (dépôt blanchâtre poudreux)',
                'peinture_cloque' => 'Peinture qui cloque ou s\'écaille',
                'papier_peint_decolle' => 'Papier peint qui se décolle',
                'platre_effrite' => 'Plâtre qui s\'effrite ou se détache',
                'carrelage_descelle' => 'Carrelage descellé',
                'parquet_gondole' => 'Parquet gondolé ou cassé',
                'bois_pourri' => 'Bois pourri (encadrements, plinthes, portes)',
                'odeur_renferme' => 'Odeur de renfermé permanente',
                'condensation_vitres' => 'Condensation matinale persistante sur les vitres',
                'mur_froid' => 'Mur froid au toucher en permanence',
                'linge_humide' => 'Linge qui sèche mal ou prend une odeur',
                'sante_respi' => 'Asthme, allergies ou toux chroniques apparus ou aggravés',
                'sante_tete' => 'Maux de tête fréquents',
                'degradation_biens' => 'Dégradation de meubles ou affaires',
                'surconso_chauffage' => 'Surconsommation de chauffage pour compenser le froid humide',
            ];
            foreach ($cons_humidite as $val => $label) {
                echo '<label class="lfi-check"><input type="checkbox" name="humidite_consequences[]" value="' . esc_attr($val) . '"> ' . esc_html($label) . '</label>';
            }
            ?>
            <label class="lfi-check"><input type="checkbox" name="humidite_consequences[]" value="autres"> Autres</label>
            <input type="text" name="humidite_consequences_autres" placeholder="Si autres, préciser" class="lfi-other-input">
        </fieldset>
    </div>
    <?php
}

function lfi_nct_section_4_thermique() {
    ?>
    <h2>Section 4 — Thermique : chauffage, chaleur, froid, isolation</h2>
    <p class="lfi-section-desc">À Clos Toreau, le chauffage au sol collectif est géré par NMH. Beaucoup de locataires utilisent un appoint personnel pour compenser — c'est un préjudice financier directement imputable à NMH.</p>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Type de chauffage du logement <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="thermique_type" value="sol_collectif_nmh" required> Sol chauffant collectif géré par NMH (la norme à Clos Toreau)</label>
        <label class="lfi-radio"><input type="radio" name="thermique_type" value="sol_avec_appoint" required> Sol chauffant collectif + chauffage d'appoint personnel</label>
        <label class="lfi-radio"><input type="radio" name="thermique_type" value="individuel_gaz" required> Chauffage individuel gaz</label>
        <label class="lfi-radio"><input type="radio" name="thermique_type" value="individuel_elec" required> Chauffage individuel électrique</label>
        <label class="lfi-radio"><input type="radio" name="thermique_type" value="aucun" required> Pas de chauffage fonctionnel</label>
    </fieldset>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Le chauffage NMH permet-il d'atteindre 19°C en hiver, sans appoint ? <span class="req">*</span></legend>
        <span class="lfi-help">19°C en pièce de vie = seuil légal de logement décent.</span>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="oui_toujours" required> Oui, toujours</label>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="oui_limite" required> Oui mais limite (jamais plus que 19°C)</label>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="partiel" required> Partiellement (certaines pièces seulement)</label>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="non_18" required> Non, jamais plus de 17-18°C sans appoint</label>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="non_16" required> Non, souvent en dessous de 16°C sans appoint</label>
        <label class="lfi-radio"><input type="radio" name="thermique_adequation" value="non_panne" required> Non, le chauffage NMH ne fonctionne pas du tout</label>
    </fieldset>

    <label class="lfi-field">
        <span class="lfi-label">Température moyenne mesurée chez vous en hiver, sans appoint (en °C)</span>
        <span class="lfi-help">Si possible, mesure en pièce de vie en milieu de journée par temps froid. Sinon estimation. Vide si impossible.</span>
        <input type="number" name="thermique_temperature" min="0" max="30" placeholder="Ex : 17">
    </label>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Utilisez-vous un chauffage d'appoint personnel ? <span class="req">*</span></legend>
        <span class="lfi-help">⭐ Stratégique : preuve directe que NMH ne tient pas son obligation.</span>
        <label class="lfi-radio"><input type="radio" name="thermique_appoint" value="oui_permanent" required> Oui, en permanence pendant la saison froide</label>
        <label class="lfi-radio"><input type="radio" name="thermique_appoint" value="oui_quotidien" required> Oui, plusieurs heures/jour pendant la saison froide</label>
        <label class="lfi-radio"><input type="radio" name="thermique_appoint" value="oui_ponctuel" required> Oui, ponctuellement (vagues de froid, pannes)</label>
        <label class="lfi-radio"><input type="radio" name="thermique_appoint" value="oui_passe" required> J'en ai eu un mais je ne l'utilise plus</label>
        <label class="lfi-radio"><input type="radio" name="thermique_appoint" value="non" required> Non, jamais eu besoin</label>
    </fieldset>

    <div data-show-if="thermique_appoint:oui_permanent|oui_quotidien|oui_ponctuel|oui_passe">
        <fieldset class="lfi-field">
            <legend class="lfi-label">Quel(s) type(s) d'appoint utilisez-vous ?</legend>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="radiateur_convecteur"> Radiateur électrique convecteur</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="radiateur_inertie"> Radiateur à inertie ou bain d'huile</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="soufflant"> Radiateur soufflant</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="gaz_portatif"> Chauffage à gaz portatif (bouteille)</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="petrole"> Chauffage à pétrole</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="clim_reversible"> Climatisation réversible</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="couvertures_chauffantes"> Couvertures ou coussins chauffants</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="bouillottes"> Bouillottes</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_appoint_types[]" value="autres"> Autres</label>
            <input type="text" name="thermique_appoint_types_autres" placeholder="Si autres, préciser" class="lfi-other-input">
        </fieldset>
    </div>

    <div data-show-if="thermique_appoint:oui_permanent|oui_quotidien|oui_ponctuel">
        <fieldset class="lfi-field">
            <legend class="lfi-label">Surcoût mensuel de l'appoint en hiver</legend>
            <span class="lfi-help">Préjudice financier imputable à NMH.</span>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="moins_20"> Moins de 20 € / mois</label>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="20_50"> 20 à 50 € / mois</label>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="50_100"> 50 à 100 € / mois</label>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="100_150"> 100 à 150 € / mois</label>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="plus_150"> Plus de 150 € / mois</label>
            <label class="lfi-radio"><input type="radio" name="thermique_appoint_cout" value="nsp"> Je ne sais pas</label>
        </fieldset>
    </div>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Confort en été <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="ete_confort" value="confortable" required> Confortable</label>
        <label class="lfi-radio"><input type="radio" name="ete_confort" value="trop_chaud_canicule" required> Trop chaud par moments (canicule), supportable</label>
        <label class="lfi-radio"><input type="radio" name="ete_confort" value="trop_chaud_souvent" required> Trop chaud souvent en été, gêne quotidienne</label>
        <label class="lfi-radio"><input type="radio" name="ete_confort" value="insupportable" required> Insupportable (intérieur > 30°C, troubles du sommeil)</label>
    </fieldset>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Sentez-vous des infiltrations d'air (froid en hiver, chaud en été) ? <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="thermique_infiltration" value="oui_importantes" required> Oui, importantes</label>
        <label class="lfi-radio"><input type="radio" name="thermique_infiltration" value="oui_moderees" required> Oui, modérées</label>
        <label class="lfi-radio"><input type="radio" name="thermique_infiltration" value="non" required> Non</label>
    </fieldset>

    <div data-show-if="thermique_infiltration:oui_importantes|oui_moderees">
        <fieldset class="lfi-field">
            <legend class="lfi-label">D'où viennent ces infiltrations ?</legend>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="fenetres"> Fenêtres</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="porte_entree"> Porte d'entrée</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="vmc"> Bouches de VMC</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="prises"> Prises électriques / interrupteurs</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="coffrets_volets"> Coffrets de volets roulants</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="plinthes"> Plinthes / jonction sol-mur</label>
            <label class="lfi-check"><input type="checkbox" name="thermique_infiltration_origine[]" value="fissures"> Trous ou fissures dans les murs</label>
        </fieldset>
    </div>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Qualité ressentie de l'isolation thermique <span class="req">*</span></legend>
        <span class="lfi-help">1 = nulle (mur froid, courants d'air), 5 = bonne</span>
        <div class="lfi-scale">
            <label class="lfi-radio-btn"><input type="radio" name="thermique_isolation" value="1" required> 1</label>
            <label class="lfi-radio-btn"><input type="radio" name="thermique_isolation" value="2" required> 2</label>
            <label class="lfi-radio-btn"><input type="radio" name="thermique_isolation" value="3" required> 3</label>
            <label class="lfi-radio-btn"><input type="radio" name="thermique_isolation" value="4" required> 4</label>
            <label class="lfi-radio-btn"><input type="radio" name="thermique_isolation" value="5" required> 5</label>
        </div>
    </fieldset>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Avez-vous signalé les problèmes thermiques à NMH ? <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="thermique_signale_nmh" value="signale_resolu" required> Oui, NMH a fait des travaux qui ont résolu</label>
        <label class="lfi-radio"><input type="radio" name="thermique_signale_nmh" value="signale_insuffisant" required> Oui, travaux faits mais insuffisants</label>
        <label class="lfi-radio"><input type="radio" name="thermique_signale_nmh" value="signale_pas_travaux" required> Oui, NMH a répondu mais pas de travaux</label>
        <label class="lfi-radio"><input type="radio" name="thermique_signale_nmh" value="signale_pas_reponse" required> Oui, sans réponse de NMH</label>
        <label class="lfi-radio"><input type="radio" name="thermique_signale_nmh" value="non_signale" required> Non, jamais signalé</label>
    </fieldset>
    <?php
}

function lfi_nct_section_6_demarches() {
    ?>
    <h2>Section 6 — Démarches déjà entreprises</h2>
    <p class="lfi-section-desc">⭐ Section stratégique pour le dossier collectif. Vos démarches passées et votre intérêt à rejoindre une action collective sont des éléments clés.</p>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Avez-vous signalé un ou plusieurs des problèmes ci-dessus à Nantes Métropole Habitat ? <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="demarches_signale" value="oui" required> Oui</label>
        <label class="lfi-radio"><input type="radio" name="demarches_signale" value="non" required> Non</label>
        <label class="lfi-radio"><input type="radio" name="demarches_signale" value="partiel" required> Partiellement (certains problèmes signalés, pas tous)</label>
    </fieldset>

    <div data-show-if="demarches_signale:oui|partiel">
        <label class="lfi-field">
            <span class="lfi-label">Qu'est-ce que NMH a répondu ?</span>
            <span class="lfi-help">Texte libre. Soyez précis : promesses non tenues, refus, silence, travaux faits mais insuffisants, etc.</span>
            <textarea name="demarches_reponse_nmh" rows="4" placeholder="Ex : ils ont envoyé un technicien il y a 2 ans qui a constaté l'humidité, m'a promis des travaux, je n'ai jamais eu de nouvelles malgré 3 relances par mail."></textarea>
        </label>
    </div>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Avez-vous (ou avez-vous eu) une procédure judiciaire contre NMH ? <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="demarches_procedure" value="oui_en_cours" required> Oui, en cours</label>
        <label class="lfi-radio"><input type="radio" name="demarches_procedure" value="oui_passee" required> Oui, passée (jugée)</label>
        <label class="lfi-radio"><input type="radio" name="demarches_procedure" value="non" required> Non</label>
    </fieldset>

    <div data-show-if="demarches_procedure:oui_en_cours|oui_passee">
        <label class="lfi-field">
            <span class="lfi-label">Précisions sur la procédure</span>
            <textarea name="demarches_procedure_precisions" rows="3" placeholder="Ex : conciliation déposée en avril 2025 pour humidité chronique, en attente d'audience."></textarea>
        </label>
    </div>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Seriez-vous intéressé·e par regrouper votre dossier avec un collectif d'habitants ? <span class="req">*</span></legend>
        <span class="lfi-help">Action collective contre le bailleur, plus de poids juridique et politique.</span>
        <label class="lfi-radio"><input type="radio" name="demarches_collectif" value="oui" required> Oui</label>
        <label class="lfi-radio"><input type="radio" name="demarches_collectif" value="a_voir" required> À voir, recontactez-moi pour en parler</label>
        <label class="lfi-radio"><input type="radio" name="demarches_collectif" value="non" required> Non</label>
    </fieldset>
    <?php
}

function lfi_nct_section_7_demande() {
    ?>
    <h2>Section 7 — Demande du locataire</h2>
    <p class="lfi-section-desc">Texte libre. Optionnel mais précieux pour comprendre les attentes des habitants.</p>

    <label class="lfi-field">
        <span class="lfi-label">Qu'est-ce qui améliorerait votre cadre de vie ici ?</span>
        <span class="lfi-help">Travaux souhaités, équipements manquants, services attendus, etc.</span>
        <textarea name="demande_locataire" rows="6" placeholder="Ex : refaire l'isolation, changer les fenêtres, installer une vraie VMC, plus de propreté dans les parties communes, sécurité du quartier..."></textarea>
    </label>
    <?php
}

function lfi_nct_section_8_contact() {
    ?>
    <h2>Section 8 — Contact (optionnel)</h2>
    <p class="lfi-section-desc">Si la personne enquêtée souhaite être recontactée par LFI Nantes Sud Clos Toreau pour suivi du dossier.</p>

    <fieldset class="lfi-field">
        <legend class="lfi-label">Souhaite être recontacté·e par LFI Nantes Sud Clos Toreau ? <span class="req">*</span></legend>
        <label class="lfi-radio"><input type="radio" name="contact_recontact" value="1" required> Oui</label>
        <label class="lfi-radio"><input type="radio" name="contact_recontact" value="0" required> Non</label>
    </fieldset>

    <div data-show-if="contact_recontact:1">
        <label class="lfi-field">
            <span class="lfi-label">Prénom <span class="req">*</span></span>
            <input type="text" name="contact_prenom" placeholder="Prénom">
        </label>
        <label class="lfi-field">
            <span class="lfi-label">Nom</span>
            <input type="text" name="contact_nom" placeholder="Nom">
        </label>
        <label class="lfi-field">
            <span class="lfi-label">Téléphone</span>
            <span class="lfi-help">Au moins l'un des deux : tél ou email.</span>
            <input type="tel" name="contact_tel" placeholder="06...">
        </label>
        <label class="lfi-field">
            <span class="lfi-label">Email</span>
            <input type="email" name="contact_email" placeholder="ex@email.com">
        </label>
        <label class="lfi-field">
            <span class="lfi-label">Numéro d'appartement</span>
            <span class="lfi-help">Pour pouvoir vous retrouver précisément. Reste interne au GA.</span>
            <input type="text" name="contact_appartement" placeholder="Ex : 305">
        </label>

        <div class="lfi-info-box">
            🔒 <strong>RGPD</strong> : ces infos sont strictement internes au GA LFI Nantes Sud Clos Toreau, jamais transmises à un tiers. Vous pouvez demander leur suppression à tout moment.
        </div>
    </div>
    <?php
}