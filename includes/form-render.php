<?php
if (!defined('ABSPATH')) exit;

function lfi_nct_render_form() {
    ob_start();
    ?>
    <form method="POST" id="lfi-nct-form" class="lfi-survey">
        <?php wp_nonce_field('lfi_nct_submit_nonce', 'lfi_nct_nonce'); ?>

        <div class="lfi-progress"><div class="lfi-progress-bar" style="width:14%"></div></div>

        <div class="lfi-step active" data-step="1">
            <?php lfi_nct_section_1_logement(); ?>
        </div>

        <!-- Sections 2-8 à venir dans le prochain push -->

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
    <h2>Section 1 — Votre logement</h2>
    <p class="lfi-section-desc">Quelques infos générales sur votre logement.</p>

    <label class="lfi-field">
        <span class="lfi-label">Adresse de l'immeuble <span class="req">*</span></span>
        <span class="lfi-help">Indiquez la rue et le numéro (sans étage ni numéro d'appartement).</span>
        <input type="text" name="adresse" required placeholder="Ex : 12 rue Saint-Aignan">
    </label>

    <div class="lfi-info-box">
        <strong>Bailleur :</strong> Nantes Métropole Habitat (NMH)
    </div>

    <label class="lfi-field">
        <span class="lfi-label">Étage <span class="req">*</span></span>
        <span class="lfi-help">Si appartement. Indiquez « RDC » pour rez-de-chaussée.</span>
        <input type="text" name="etage" required placeholder="Ex : 3, RDC, sous-sol">
    </label>

    <label class="lfi-field">
        <span class="lfi-label">Année d'arrivée dans le logement <span class="req">*</span></span>
        <input type="number" name="annee_arrivee" required min="1950" max="2030" placeholder="Ex : 2018">
    </label>
    <?php
}