<?php
/**
 * Formulaire d'enquête porte-à-porte (version courte).
 * 8 questions max, conditionnel : si pas de problème, on saute aux coordonnées.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_render_form() {
    ob_start(); ?>
    <form method="POST" id="lfi-nct-form" class="lfi-survey lfi-survey-simple">
        <?php wp_nonce_field('lfi_nct_submit_nonce', 'lfi_nct_nonce'); ?>

        <div class="lfi-print-bar">
            <button type="button" class="lfi-btn-print" onclick="window.print()">🖨️ Imprimer / Photocopier (version papier)</button>
        </div>

        <h2>Enquête porte-à-porte — logement</h2>
        <p class="lfi-section-desc">Quelques questions rapides pour identifier les problèmes et organiser une suite si besoin.</p>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">📍 Logement visité</legend>
            <label class="lfi-field">
                <span class="lfi-label">Immeuble / adresse <span class="req">*</span></span>
                <input type="text" name="adresse" required placeholder="Ex : 12 rue de Biarritz" list="lfi-nct-known-adr" autocomplete="off">
                <?php echo function_exists('lfi_nct_addresses_datalist') ? lfi_nct_addresses_datalist('lfi-nct-known-adr') : ''; ?>
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Étage <span class="req">*</span></span>
                <input type="text" name="etage" required placeholder="Ex : 3">
            </label>
            <label class="lfi-field">
                <span class="lfi-label">Numéro d'appartement</span>
                <input type="text" name="appartement" placeholder="Ex : 32">
            </label>
        </fieldset>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">Y a-t-il des problèmes dans ce logement ? <span class="req">*</span></legend>
            <label class="lfi-radio"><input type="radio" name="problemes_presence" value="oui" required> Oui</label>
            <label class="lfi-radio"><input type="radio" name="problemes_presence" value="non"> Non</label>
        </fieldset>

        <div id="lfi-bloc-problemes" hidden>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Lesquels ? (cochez tout ce qui s'applique)</legend>
                <?php
                $types = [
                    'degats_eaux'      => '💧 Dégâts des eaux / fuites / infiltrations',
                    'humidite'         => '🌫️ Humidité / moisissures',
                    'insectes'         => '🐜 Insectes / nuisibles (cafards, punaises, rats…)',
                    'chauffage'        => '🥶 Chauffage insuffisant / panne',
                    'electricite'      => '⚡ Problèmes électriques',
                    'ascenseur'        => '🛗 Ascenseur en panne / défaillant',
                    'parties_communes' => '🚪 Parties communes dégradées',
                    'bruit'            => '🔊 Nuisances sonores / voisinage',
                    'securite'         => '🚨 Insécurité (entrées, parties communes…)',
                ];
                foreach ($types as $k => $label): ?>
                    <label class="lfi-check"><input type="checkbox" name="problemes_types[]" value="<?php echo esc_attr($k); ?>"> <?php echo $label; ?></label>
                <?php endforeach; ?>
                <label class="lfi-check"><input type="checkbox" name="problemes_types[]" value="autre"> Autre :</label>
                <input type="text" name="problemes_types_autre" class="lfi-other-input" placeholder="précisez">
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Depuis combien de temps ?</legend>
                <?php
                $durees = [
                    'moins_1_mois' => "Moins d'un mois",
                    '1_6_mois'     => '1 à 6 mois',
                    '6_12_mois'    => '6 à 12 mois',
                    '1_5_ans'      => "Plus d'un an",
                    'plus_5_ans'   => 'Plus de 5 ans',
                ];
                foreach ($durees as $k => $label): ?>
                    <label class="lfi-radio"><input type="radio" name="problemes_duree" value="<?php echo esc_attr($k); ?>"> <?php echo esc_html($label); ?></label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Est-ce récurrent ?</legend>
                <label class="lfi-radio"><input type="radio" name="problemes_recurrent" value="permanent"> Oui, en permanence</label>
                <label class="lfi-radio"><input type="radio" name="problemes_recurrent" value="parfois"> Oui, ça revient régulièrement</label>
                <label class="lfi-radio"><input type="radio" name="problemes_recurrent" value="ponctuel"> Non, c'est ponctuel</label>
            </fieldset>

            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Gravité ressentie <span class="req">*</span></legend>
                <p class="lfi-help">1 = mineur · 10 = insupportable / critique</p>
                <div class="lfi-scale">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                        <label class="lfi-radio-btn"><input type="radio" name="problemes_gravite" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                    <?php endfor; ?>
                </div>
            </fieldset>
        </div>

        <fieldset class="lfi-fieldset">
            <legend class="lfi-legend">Accepteriez-vous qu'on revienne ?</legend>
            <p class="lfi-help">On peut revenir constater sur place, vous accompagner pour faire pression sur Nantes Habitat, et vous aider juridiquement pour que le problème soit réglé.</p>
            <label class="lfi-radio"><input type="radio" name="revenir_ok" value="oui"> Oui, je suis intéressé·e</label>
            <label class="lfi-radio"><input type="radio" name="revenir_ok" value="non"> Non, merci</label>
        </fieldset>

        <div id="lfi-bloc-contact" hidden>
            <fieldset class="lfi-fieldset">
                <legend class="lfi-legend">Vos coordonnées pour qu'on prenne RDV</legend>
                <label class="lfi-field"><span class="lfi-label">Prénom</span><input type="text" name="contact_prenom"></label>
                <label class="lfi-field"><span class="lfi-label">Nom</span><input type="text" name="contact_nom"></label>
                <label class="lfi-field"><span class="lfi-label">Téléphone</span><input type="tel" name="contact_tel" placeholder="06 12 34 56 78"></label>
                <label class="lfi-field"><span class="lfi-label">Email</span><input type="email" name="contact_email" placeholder="vous@email.fr"></label>
                <p class="lfi-help">Téléphone <strong>ou</strong> email — au moins l'un des deux pour qu'on puisse vous recontacter.</p>
            </fieldset>
        </div>

        <div class="lfi-info-box">
            🔒 <strong>RGPD</strong> : ces infos sont strictement internes au Groupe d'Action LFI Nantes Sud Clos Toreau, jamais transmises à un tiers. Vous pouvez demander leur suppression à tout moment.
        </div>

        <p>
            <button type="submit" name="lfi_nct_submit" class="lfi-btn lfi-btn-lg lfi-submit">✓ Enregistrer l'enquête</button>
        </p>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * Résumé imprimable de la réponse qui vient d'être envoyée.
 */
function lfi_nct_render_submission_summary($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) return '';
    $table = $wpdb->prefix . 'lfi_nct_responses';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) return '';
    $data = $row->data ? json_decode($row->data, true) : [];
    if (!is_array($data)) $data = [];

    $fresh_url = esc_url(remove_query_arg(['_wp_http_referer']));

    $type_labels = [
        'degats_eaux'      => 'Dégâts des eaux / infiltrations',
        'humidite'         => 'Humidité / moisissures',
        'insectes'         => 'Insectes / nuisibles',
        'chauffage'        => 'Chauffage insuffisant',
        'electricite'      => 'Problèmes électriques',
        'ascenseur'        => 'Ascenseur',
        'parties_communes' => 'Parties communes',
        'bruit'            => 'Nuisances sonores',
        'securite'         => 'Insécurité',
        'autre'            => 'Autre',
    ];
    $duree_labels = [
        'moins_1_mois' => "Moins d'un mois",
        '1_6_mois'     => '1 à 6 mois',
        '6_12_mois'    => '6 à 12 mois',
        '1_5_ans'      => "Plus d'un an",
        'plus_5_ans'   => 'Plus de 5 ans',
    ];
    $rec_labels = [
        'permanent' => 'En permanence',
        'parfois'   => 'Régulièrement',
        'ponctuel'  => 'Ponctuel',
    ];

    $presence    = $data['problemes_presence'] ?? '';
    $types       = (array) ($data['problemes_types'] ?? []);
    $types_autre = $data['problemes_types_autre'] ?? '';
    $duree       = $data['problemes_duree'] ?? '';
    $rec         = $data['problemes_recurrent'] ?? '';
    $gravite     = (int) ($data['problemes_gravite'] ?? 0);
    $revenir     = $data['revenir_ok'] ?? '';
    $appt        = $data['appartement'] ?? '';

    ob_start(); ?>
    <div class="lfi-survey lfi-submission">
        <div class="lfi-print-bar">
            <button type="button" class="lfi-btn-print" onclick="window.print()">🖨️ Imprimer ma réponse</button>
            <a href="<?php echo $fresh_url; ?>" class="lfi-btn-print" style="margin-left:.5em;text-decoration:none">📝 Saisir une nouvelle enquête</a>
        </div>

        <h2>Réponse enregistrée — enquête n°<?php echo (int) $row->id; ?></h2>
        <p class="lfi-help">Enregistrée le <?php echo esc_html($row->submitted_at); ?>.</p>

        <h3>📍 Logement</h3>
        <ul class="lfi-summary-list">
            <li><strong>Adresse :</strong> <?php echo esc_html($row->adresse); ?></li>
            <li><strong>Étage :</strong> <?php echo esc_html($row->etage); ?></li>
            <?php if ($appt !== ''): ?><li><strong>Appartement :</strong> <?php echo esc_html($appt); ?></li><?php endif; ?>
        </ul>

        <h3>Problèmes</h3>
        <p><strong><?php
            if ($presence === 'oui') echo '⚠️ Oui';
            elseif ($presence === 'non') echo '✅ Aucun';
            else echo '—';
        ?></strong></p>

        <?php if ($presence === 'oui'): ?>
            <ul class="lfi-summary-list">
                <?php if ($types): ?>
                    <li><strong>Types :</strong>
                        <?php
                        $labels = [];
                        foreach ($types as $t) $labels[] = $type_labels[$t] ?? $t;
                        echo esc_html(implode(' · ', $labels));
                        if ($types_autre !== '') echo ' (autre : ' . esc_html($types_autre) . ')';
                        ?>
                    </li>
                <?php endif; ?>
                <?php if ($duree !== ''): ?><li><strong>Durée :</strong> <?php echo esc_html($duree_labels[$duree] ?? $duree); ?></li><?php endif; ?>
                <?php if ($rec !== ''): ?><li><strong>Récurrence :</strong> <?php echo esc_html($rec_labels[$rec] ?? $rec); ?></li><?php endif; ?>
                <?php if ($gravite > 0): ?><li><strong>Gravité ressentie :</strong> <?php echo $gravite; ?> / 10</li><?php endif; ?>
            </ul>
        <?php endif; ?>

        <h3>Suivi</h3>
        <p><strong>Souhaite être recontacté·e :</strong> <?php echo $revenir === 'oui' ? '✅ Oui' : '❌ Non'; ?></p>
        <?php if ($revenir === 'oui'): ?>
            <ul class="lfi-summary-list">
                <?php if ($row->contact_prenom !== ''): ?><li><strong>Prénom :</strong> <?php echo esc_html($row->contact_prenom); ?></li><?php endif; ?>
                <?php if ($row->contact_nom !== ''): ?><li><strong>Nom :</strong> <?php echo esc_html($row->contact_nom); ?></li><?php endif; ?>
                <?php if ($row->contact_tel !== ''): ?><li><strong>Téléphone :</strong> <?php echo esc_html($row->contact_tel); ?></li><?php endif; ?>
                <?php if ($row->contact_email !== ''): ?><li><strong>Email :</strong> <?php echo esc_html($row->contact_email); ?></li><?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
