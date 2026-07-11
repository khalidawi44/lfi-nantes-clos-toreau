<?php
/**
 * PRESTATAIRES EXTÉRIEURS — carnet d'entreprises mobilisables sur les dossiers
 * (désinsectisation, plomberie, électricité…). Ex. : Sapiens pour les blattes
 * et punaises de lit. Réutilisable : on l'enregistre une fois, on le retrouve
 * partout, on le rattache aux dossiers concernés.
 *
 * Stockage : option `lfi_nct_prestataires` (éditable en 1 écran, sans base
 * dédiée). Cloisonnement : réservé aux admins / brigade du GA.
 */
if (!defined('ABSPATH')) exit;

/** Spécialités proposées (emoji + libellé). */
function lfi_nct_prestataire_specialites() {
    return [
        'desinsectisation' => '🐛 Désinsectisation (punaises, blattes, rats)',
        'deratisation'     => '🐀 Dératisation',
        'plomberie'        => '🔧 Plomberie',
        'electricite'      => '⚡ Électricité',
        'chauffage'        => '🔥 Chauffage',
        'serrurerie'       => '🔑 Serrurerie',
        'nettoyage'        => '🧹 Nettoyage / assainissement',
        'maconnerie'       => '🧱 Maçonnerie / travaux',
        'autre'            => '🛠 Autre',
    ];
}

function lfi_nct_prestataires_get() {
    $v = get_option('lfi_nct_prestataires', null);
    if ($v === null) { $v = lfi_nct_prestataires_seed(); update_option('lfi_nct_prestataires', $v, false); }
    return is_array($v) ? $v : [];
}
function lfi_nct_prestataires_save($list) {
    update_option('lfi_nct_prestataires', array_values($list), false);
}
/** Amorce : Sapiens (désinsectisation), contact Adrien. */
function lfi_nct_prestataires_seed() {
    return [[
        'id'          => (abs(crc32('sapiens')) % 900000000) + 100000000,
        'nom'         => 'Sapiens',
        'specialite'  => 'desinsectisation',
        'contact_nom' => 'Adrien',
        'tel'         => '02 28 01 12 16',
        'email'       => '',
        'note'        => 'Responsable : Adrien. Blattes + punaises de lit. Orthographe à vérifier.',
    ]];
}

/* ============================================================== *
 *  VUE ADMIN : carnet des prestataires (liste + ajout/édition).   *
 * ============================================================== */
function lfi_nct_app_view_prestataires() {
    if (!is_user_logged_in()) { wp_safe_redirect(lfi_nct_app_url()); exit; }
    $can = current_user_can('manage_options') || (function_exists('lfi_nct_can_admin_ga') && lfi_nct_can_admin_ga());
    if (!$can) { wp_safe_redirect(lfi_nct_app_url()); exit; }

    $specs = lfi_nct_prestataire_specialites();

    /* Enregistrement (ajout / édition). */
    if (!empty($_POST['lfi_presta_save']) && check_admin_referer('lfi_presta')) {
        $list = lfi_nct_prestataires_get();
        $pid = (int) ($_POST['pid'] ?? 0);
        $spec = sanitize_key($_POST['specialite'] ?? 'autre');
        if (!isset($specs[$spec])) $spec = 'autre';
        $row = [
            'id'          => $pid ?: ((abs(crc32(microtime(true) . mt_rand())) % 900000000) + 100000000),
            'nom'         => sanitize_text_field(wp_unslash($_POST['nom'] ?? '')),
            'specialite'  => $spec,
            'contact_nom' => sanitize_text_field(wp_unslash($_POST['contact_nom'] ?? '')),
            'tel'         => sanitize_text_field(wp_unslash($_POST['tel'] ?? '')),
            'email'       => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'note'        => sanitize_text_field(wp_unslash($_POST['note'] ?? '')),
        ];
        if ($row['nom'] !== '') {
            $found = false;
            foreach ($list as $i => $c) { if ($pid && (int) ($c['id'] ?? 0) === $pid) { $list[$i] = $row; $found = true; break; } }
            if (!$found) $list[] = $row;
            lfi_nct_prestataires_save($list);
        }
        wp_safe_redirect(lfi_nct_app_url('prestataires', ['ok' => 1])); exit;
    }
    if (!empty($_POST['lfi_presta_del']) && check_admin_referer('lfi_presta')) {
        $pid = (int) ($_POST['pid'] ?? 0);
        $list = array_values(array_filter(lfi_nct_prestataires_get(), function ($c) use ($pid) { return (int) ($c['id'] ?? 0) !== $pid; }));
        lfi_nct_prestataires_save($list);
        wp_safe_redirect(lfi_nct_app_url('prestataires', ['del' => 1])); exit;
    }

    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $list = lfi_nct_prestataires_get();
    $editing = null;
    foreach ($list as $c) if ((int) ($c['id'] ?? 0) === $edit_id) $editing = $c;

    lfi_nct_app_screen_open('🧰 Prestataires extérieurs', 'Entreprises mobilisables sur les dossiers');
    if (!empty($_GET['ok'])) lfi_nct_app_flash('✅ Prestataire enregistré.');
    if (!empty($_GET['del'])) lfi_nct_app_flash('🗑 Prestataire supprimé.');
    echo '<div class="lfi-app-help">Le carnet des entreprises qu\'on peut mobiliser (désinsectisation, plomberie…). On l\'enregistre une fois et on le retrouve partout. Ex. : <strong>Sapiens</strong> pour les blattes et punaises de lit.</div>';

    /* Formulaire ajout / édition. */
    $f = $editing ?: ['id' => 0, 'nom' => '', 'specialite' => 'desinsectisation', 'contact_nom' => '', 'tel' => '', 'email' => '', 'note' => ''];
    echo '<form method="post" class="lfi-app-form" style="background:#f6f8fb;border:1px solid #dfe6f0;border-radius:12px;padding:12px">' . wp_nonce_field('lfi_presta', '_wpnonce', true, false);
    echo '<input type="hidden" name="lfi_presta_save" value="1"><input type="hidden" name="pid" value="' . (int) $f['id'] . '">';
    echo '<div style="font-weight:800;color:#0b3d91;margin-bottom:6px">' . ($editing ? '✏️ Modifier' : '➕ Ajouter') . ' un prestataire</div>';
    echo '<label>Entreprise<input type="text" name="nom" value="' . esc_attr($f['nom']) . '" required placeholder="Ex : Sapiens"></label>';
    echo '<label>Spécialité<select name="specialite">';
    foreach ($specs as $sk => $sl) echo '<option value="' . esc_attr($sk) . '"' . selected(($f['specialite'] ?? 'autre'), $sk, false) . '>' . esc_html($sl) . '</option>';
    echo '</select></label>';
    echo '<label>Personne à contacter<input type="text" name="contact_nom" value="' . esc_attr($f['contact_nom']) . '" placeholder="Ex : Adrien (responsable)"></label>';
    echo '<label>Téléphone<input type="tel" name="tel" value="' . esc_attr($f['tel']) . '" placeholder="02 28 01 12 16"></label>';
    echo '<label>Email<input type="email" name="email" value="' . esc_attr($f['email']) . '"></label>';
    echo '<label>Note<input type="text" name="note" value="' . esc_attr($f['note']) . '" placeholder="Précisions (zone, tarif, à vérifier…)"></label>';
    echo '<button type="submit" class="btn-primary" style="background:#0b3d91">💾 Enregistrer</button>';
    if ($editing) echo '<a href="' . esc_url(lfi_nct_app_url('prestataires')) . '" style="display:block;text-align:center;margin-top:6px;color:#666">Annuler l\'édition</a>';
    echo '</form>';

    /* Liste, groupée par spécialité. */
    echo '<h3 style="margin:16px 0 8px;color:#0b3d91">Carnet (' . count($list) . ')</h3>';
    if (empty($list)) {
        echo '<div class="lfi-app-empty">Aucun prestataire pour l\'instant.</div>';
    } else {
        foreach ($specs as $sk => $sl) {
            $grp = array_values(array_filter($list, function ($c) use ($sk) { return (($c['specialite'] ?? 'autre')) === $sk; }));
            if (empty($grp)) continue;
            echo '<div style="font-weight:800;color:#444;margin:14px 0 6px;padding-bottom:4px;border-bottom:2px solid #eee">' . esc_html($sl) . '</div>';
            echo '<div style="display:flex;flex-direction:column;gap:9px">';
            foreach ($grp as $c) {
                echo '<div style="background:#fff;border:1px solid #e6e6e6;border-left:4px solid #186a3b;border-radius:11px;padding:11px 12px">';
                echo '<div style="font-weight:800;color:#1a1a1a">' . esc_html($c['nom']) . '</div>';
                $bits = [];
                if (!empty($c['contact_nom'])) $bits[] = '👤 ' . esc_html($c['contact_nom']);
                if (!empty($c['note'])) echo '<div style="font-size:.85em;color:#666;margin-top:1px">' . esc_html($c['note']) . '</div>';
                if ($bits) echo '<div style="font-size:.86em;color:#555;margin-top:2px">' . implode(' · ', $bits) . '</div>';
                echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">';
                if (!empty($c['tel'])) echo '<a href="tel:' . esc_attr(preg_replace('/\s+/', '', $c['tel'])) . '" style="background:#186a3b;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.85em">📞 ' . esc_html($c['tel']) . '</a>';
                if (!empty($c['email'])) echo '<a href="mailto:' . esc_attr($c['email']) . '" style="background:#0b3d91;color:#fff;padding:6px 11px;border-radius:8px;text-decoration:none;font-weight:700;font-size:.85em">✉️ Email</a>';
                echo '<a href="' . esc_url(lfi_nct_app_url('prestataires', ['edit' => (int) $c['id']])) . '" style="color:#0b3d91;font-weight:700;text-decoration:none;font-size:.85em;align-self:center">✏️ Modifier</a>';
                echo '<form method="post" onsubmit="return confirm(\'Supprimer ce prestataire ?\')" style="margin:0;align-self:center">' . wp_nonce_field('lfi_presta', '_wpnonce', true, false) . '<input type="hidden" name="lfi_presta_del" value="1"><input type="hidden" name="pid" value="' . (int) $c['id'] . '"><button type="submit" class="btn-ghost" style="font-size:.85em;padding:0;color:#c8102e">🗑</button></form>';
                echo '</div></div>';
            }
            echo '</div>';
        }
    }
    lfi_nct_app_screen_close();
}

/** Petit encart « prestataires » à afficher dans un dossier (lecture seule +
 *  bouton pour gérer le carnet). Cloisonné : admins/brigade uniquement. */
function lfi_nct_prestataires_dossier_box() {
    $list = lfi_nct_prestataires_get();
    if (empty($list)) {
        echo '<div style="margin-top:8px"><a href="' . esc_url(lfi_nct_app_url('prestataires')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none">🧰 Ajouter un prestataire extérieur →</a></div>';
        return;
    }
    $specs = lfi_nct_prestataire_specialites();
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b;margin-top:10px"><summary style="cursor:pointer;font-weight:800;color:#186a3b;list-style:none">🧰 Prestataires extérieurs mobilisables (' . count($list) . ')</summary>';
    echo '<div style="margin-top:8px;display:flex;flex-direction:column;gap:7px">';
    foreach ($list as $c) {
        echo '<div style="background:#fff;border:1px solid #eee;border-radius:9px;padding:8px 11px">';
        echo '<div style="font-weight:700">' . esc_html($c['nom']) . ' <span style="font-weight:400;color:#888;font-size:.85em">· ' . esc_html($specs[$c['specialite']] ?? '') . '</span></div>';
        $line = [];
        if (!empty($c['contact_nom'])) $line[] = '👤 ' . esc_html($c['contact_nom']);
        if (!empty($c['tel'])) $line[] = '<a href="tel:' . esc_attr(preg_replace('/\s+/', '', $c['tel'])) . '" style="color:#186a3b;font-weight:700;text-decoration:none">📞 ' . esc_html($c['tel']) . '</a>';
        if ($line) echo '<div style="font-size:.86em;color:#555;margin-top:2px">' . implode(' · ', $line) . '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '<div style="margin-top:8px"><a href="' . esc_url(lfi_nct_app_url('prestataires')) . '" style="color:#0b3d91;font-weight:700;text-decoration:none;font-size:.9em">🧰 Gérer le carnet des prestataires →</a></div>';
    echo '</details>';
}
