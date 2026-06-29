<?php
/**
 * Module Appels NMH — journal d'appels + rapports d'incident
 *
 * Chaque appel passé à Nantes Métropole Habitat (02 40 67 07 37) est
 * tracé :
 *   - date / heure / durée (minutes)
 *   - interlocuteur (ou « a refusé de donner son identité »)
 *   - objet de l'appel
 *   - incidents constatés (tutoiement, raccroché au nez, refus de
 *     rappeler, agressivité, refus d'identité…)
 *   - transcription / notes (dictée vocale possible)
 *
 * Et permet :
 *   - de générer un RAPPORT D'INCIDENT formel (imprimable + email)
 *   - de FACTURER le temps d'appel (1,50 €/min par défaut) en créant
 *     automatiquement une intervention dans la brigade
 *
 * STRICTEMENT PER-USER (owner_user_id).
 */
if (!defined('ABSPATH')) exit;

const LFI_NCT_APPEL_DBVER_KEY = 'lfi_nct_appel_db_ver';
const LFI_NCT_APPEL_DBVER_VAL = '2';

/* Numéro NMH + tarif par défaut */
function lfi_nct_nmh_phone()        { return (string) get_option('lfi_nct_nmh_phone', '02 40 67 07 37'); }
function lfi_nct_appel_tarif_min()  { return (float)  get_option('lfi_nct_appel_tarif_min', 1.50); }

/* ============================================================== *
 *  DB Setup                                                        *
 * ============================================================== */
add_action('init', 'lfi_nct_appel_db_setup', 8);
function lfi_nct_appel_db_setup() {
    if (get_option(LFI_NCT_APPEL_DBVER_KEY) === LFI_NCT_APPEL_DBVER_VAL) return;
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $t = $wpdb->prefix . 'lfi_nct_appels_nmh';
    dbDelta("CREATE TABLE $t (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        owner_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_user_id BIGINT UNSIGNED DEFAULT NULL,
        tenant_label VARCHAR(200) DEFAULT '',
        date_appel DATETIME DEFAULT NULL,
        duree_minutes DECIMAL(6,2) DEFAULT 0,
        interlocuteur VARCHAR(160) DEFAULT '',
        objet VARCHAR(255) DEFAULT '',
        incidents TEXT,
        notes TEXT,
        audio_attachment_id BIGINT UNSIGNED DEFAULT NULL,
        facture INT DEFAULT 0,
        intervention_id BIGINT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY owner_user_id (owner_user_id),
        KEY tenant_user_id (tenant_user_id)
    ) $charset;");

    if (get_option('lfi_nct_nmh_phone') === false)       update_option('lfi_nct_nmh_phone', '02 40 67 07 37', false);
    if (get_option('lfi_nct_appel_tarif_min') === false) update_option('lfi_nct_appel_tarif_min', 1.50, false);

    update_option(LFI_NCT_APPEL_DBVER_KEY, LFI_NCT_APPEL_DBVER_VAL, false);
}

/* Liste des types d'incidents prédéfinis */
function lfi_nct_appel_incidents_labels() {
    return [
        'tutoiement'        => '🗣 Tutoiement / manque de respect',
        'refus_identite'    => '🕵 Refus de donner son identité',
        'raccroche'         => '📴 A raccroché au nez',
        'refus_rappeler'    => '🚫 Refuse de me rappeler',
        'refus_appeler'     => '☎️ Refuse de passer l\'appel',
        'agressivite'       => '😡 Agressivité / ton menaçant',
        'attente_excessive' => '⏳ Attente excessive avant réponse',
        'promesse_non_tenue'=> '🤥 Promesse non tenue (rappel, intervention…)',
        'transfert_perdu'   => '🔀 Transfert vers un service injoignable',
        'deni'              => '🙈 Déni du problème signalé',
    ];
}

function lfi_nct_appel_owner_id() {
    return function_exists('lfi_nct_brigade_owner_id') ? lfi_nct_brigade_owner_id() : (int) get_current_user_id();
}

function lfi_nct_appel_get($id) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_appels_nmh';
    $owner = (int) lfi_nct_appel_owner_id();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d AND owner_user_id = %d", (int) $id, $owner));
}

/* ============================================================== *
 *  VUE : Journal des appels                                        *
 * ============================================================== */
function lfi_nct_app_view_appels_nmh() {
    if (!lfi_nct_can_use_brigade()) return;
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_appels_nmh';
    $owner = (int) lfi_nct_appel_owner_id();

    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE owner_user_id = %d ORDER BY date_appel DESC, id DESC LIMIT 200", $owner)) ?: [];

    $phone = lfi_nct_nmh_phone();
    $phone_tel = preg_replace('/[^\d+]/', '', $phone);

    /* Stats */
    $n_incidents = 0; $total_min = 0;
    foreach ($rows as $r) {
        $inc = json_decode($r->incidents ?? '[]', true) ?: [];
        if (!empty($inc)) $n_incidents++;
        $total_min += (float) $r->duree_minutes;
    }

    lfi_nct_app_screen_open('☎️ Appels NMH', count($rows) . ' appel(s) · ' . $n_incidents . ' avec incident');

    if (!empty($_GET['saved']))    lfi_nct_app_flash('✅ Appel enregistré.');
    if (!empty($_GET['billed']))   lfi_nct_app_flash('🧾 Appel facturé — intervention créée dans la brigade.');
    if (!empty($_GET['deleted']))  lfi_nct_app_flash('🗑 Appel supprimé.');

    /* Gros bouton appeler */
    echo '<div style="background:linear-gradient(135deg,#0066a3,#004d7a);color:#fff;border-radius:12px;padding:18px;margin-bottom:14px;text-align:center">';
    echo '<div style="font-weight:800;font-size:1.05em;margin-bottom:4px">Nantes Métropole Habitat</div>';
    echo '<div style="opacity:.9;margin-bottom:12px">' . esc_html($phone) . '</div>';
    echo '<a href="tel:' . esc_attr($phone_tel) . '" style="background:#fff;color:#0066a3;padding:14px 28px;border-radius:10px;text-decoration:none;font-weight:800;font-size:1.1em;display:inline-block">📞 Appeler maintenant</a>';
    echo '<div style="opacity:.85;font-size:.85em;margin-top:12px">⚠️ Pendant l\'appel, note l\'heure de début. Juste après, clique « + Enregistrer l\'appel » pour consigner durée, interlocuteur et incidents.</div>';
    echo '</div>';

    echo '<div class="lfi-app-bulk-row">';
    echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('appel-nmh-add')) . '">+ Enregistrer un appel</a>';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('appel-guide')) . '">📖 Comment enregistrer mes appels</a>';
    echo '</div>';

    if (empty($rows)) {
        echo '<div class="lfi-app-empty">Aucun appel enregistré pour le moment.</div>';
        lfi_nct_app_screen_close();
        return;
    }

    $inc_labels = lfi_nct_appel_incidents_labels();
    echo '<ul class="lfi-app-list">';
    foreach ($rows as $r) {
        $inc = json_decode($r->incidents ?? '[]', true) ?: [];
        $has_inc = !empty($inc);
        echo '<li class="lfi-app-card" style="border-left:4px solid ' . ($has_inc ? '#a30b25' : '#0066a3') . '">';
        echo '<div class="head"><div class="who">☎️ ' . esc_html($r->date_appel ? wp_date('j M Y · H:i', strtotime($r->date_appel)) : 'Appel') . '</div>';
        if ($r->facture) echo '<div class="badge" style="background:#186a3b;color:#fff">🧾 Facturé</div>';
        elseif ($has_inc) echo '<div class="badge" style="background:#a30b25;color:#fff">⚠ Incident</div>';
        echo '</div>';
        if (!empty($r->audio_attachment_id)) echo '<div class="meta"><span class="meta-chip" style="background:#e8f0ff;color:#0066a3">🎙 Enregistrement joint</span></div>';
        echo '<div class="meta">';
        if ($r->duree_minutes > 0) echo '<span class="meta-chip">⏱ ' . esc_html(rtrim(rtrim(number_format($r->duree_minutes, 2, ',', ' '), '0'), ',')) . ' min</span>';
        if ($r->interlocuteur)     echo '<span class="meta-chip">👤 ' . esc_html($r->interlocuteur) . '</span>';
        if ($r->tenant_label)      echo '<span class="meta-chip">🏠 ' . esc_html($r->tenant_label) . '</span>';
        if ($r->duree_minutes > 0) {
            $montant = (float) $r->duree_minutes * lfi_nct_appel_tarif_min();
            echo '<span class="meta-chip"><strong>' . number_format($montant, 2, ',', ' ') . ' €</strong></span>';
        }
        echo '</div>';
        if ($r->objet) echo '<div class="com"><strong>' . esc_html($r->objet) . '</strong></div>';
        if ($has_inc) {
            echo '<div class="meta" style="margin-top:6px">';
            foreach ($inc as $k) {
                if (isset($inc_labels[$k])) echo '<span class="meta-chip" style="background:#fff3f5;color:#a30b25">' . esc_html($inc_labels[$k]) . '</span>';
            }
            echo '</div>';
        }
        echo '<div class="row-actions">';
        echo '<a class="btn-primary" href="' . esc_url(lfi_nct_app_url('appel-nmh-edit', ['id' => $r->id])) . '">✏️ Éditer</a>';
        if ($has_inc) echo '<a class="btn-primary" style="background:#a30b25" href="' . esc_url(lfi_nct_app_url('appel-nmh-rapport', ['id' => $r->id])) . '" target="_blank">📄 Rapport d\'incident</a>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Formulaire ajout / édition d'un appel                           *
 * ============================================================== */
function lfi_nct_app_view_appel_nmh_add()  { if (!lfi_nct_can_use_brigade()) return; lfi_nct_appel_form(null); }
function lfi_nct_app_view_appel_nmh_edit() { if (!lfi_nct_can_use_brigade()) return; lfi_nct_appel_form(lfi_nct_appel_get((int) ($_GET['id'] ?? 0))); }

function lfi_nct_appel_form($row) {
    global $wpdb;
    $t = $wpdb->prefix . 'lfi_nct_appels_nmh';
    $is_edit = !empty($row);
    $owner = (int) lfi_nct_appel_owner_id();

    /* Suppression */
    if ($is_edit && !empty($_POST['lfi_appel_delete']) && check_admin_referer('lfi_appel_delete')) {
        $wpdb->delete($t, ['id' => $row->id, 'owner_user_id' => $owner]);
        wp_safe_redirect(lfi_nct_app_url('appels-nmh', ['deleted' => 1]));
        exit;
    }

    /* Facturation : crée une intervention "Appel NMH" */
    if ($is_edit && !empty($_POST['lfi_appel_facturer']) && check_admin_referer('lfi_appel_facturer')) {
        $duree_min = (float) $row->duree_minutes;
        if ($duree_min > 0) {
            $prix = round($duree_min * lfi_nct_appel_tarif_min(), 2);
            $ti = $wpdb->prefix . 'lfi_nct_interventions';
            $wpdb->insert($ti, [
                'owner_user_id' => $owner,
                'tenant_user_id'=> $row->tenant_user_id ?: null,
                'tenant_nom'    => $row->tenant_label ?: 'Appel NMH',
                'bailleur'      => 'Nantes Métropole Habitat',
                'date_intervention' => $row->date_appel ? date('Y-m-d', strtotime($row->date_appel)) : current_time('Y-m-d'),
                'type_travaux'  => 'Temps d\'appel NMH',
                'type_travaux_key' => '',
                'description'   => 'Temps passé au téléphone avec Nantes Métropole Habitat le ' . ($row->date_appel ? wp_date('j/m/Y à H:i', strtotime($row->date_appel)) : '') . ' — durée ' . rtrim(rtrim(number_format($duree_min, 2, ',', ' '), '0'), ',') . ' min'
                                   . ($row->objet ? '. Objet : ' . $row->objet : '')
                                   . ($row->interlocuteur ? '. Interlocuteur : ' . $row->interlocuteur : '') . '.',
                'tarif_mode'    => 'tache',
                'prix_tache'    => $prix,
                'duree_heures'  => round($duree_min / 60, 2),
                'tarif_horaire' => 0,
                'cout_materiaux'=> 0,
                'total_ht'      => $prix,
                'statut'        => 'realise',
                'notes'         => 'Généré automatiquement depuis le journal des appels NMH (appel #' . $row->id . ', tarif ' . number_format(lfi_nct_appel_tarif_min(), 2, ',', ' ') . ' €/min).',
            ]);
            $new_iid = (int) $wpdb->insert_id;
            $wpdb->update($t, ['facture' => 1, 'intervention_id' => $new_iid], ['id' => $row->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('intervention-edit', ['id' => $new_iid, 'billed' => 1]));
            exit;
        }
    }

    /* Save */
    if (!empty($_POST['lfi_appel_save']) && check_admin_referer('lfi_appel_save')) {
        $incidents = array_keys(array_filter((array) ($_POST['incidents'] ?? [])));
        $tuid = (int) ($_POST['tenant_user_id'] ?? 0);
        $tlabel = sanitize_text_field(wp_unslash($_POST['tenant_label'] ?? ''));
        if ($tuid && !$tlabel) {
            $tu = get_userdata($tuid);
            if ($tu) $tlabel = $tu->display_name;
        }
        $data = [
            'tenant_user_id' => $tuid ?: null,
            'tenant_label'   => $tlabel,
            'date_appel'     => sanitize_text_field(wp_unslash($_POST['date_appel'] ?? '')) ?: current_time('mysql'),
            'duree_minutes'  => (float) ($_POST['duree_minutes'] ?? 0),
            'interlocuteur'  => sanitize_text_field(wp_unslash($_POST['interlocuteur'] ?? '')),
            'objet'          => sanitize_text_field(wp_unslash($_POST['objet'] ?? '')),
            'incidents'      => wp_json_encode($incidents),
            'notes'          => sanitize_textarea_field(wp_unslash($_POST['notes'] ?? '')),
        ];
        /* Upload éventuel d'un enregistrement audio de l'appel */
        $audio_id = null;
        if (!empty($_FILES['enregistrement_audio']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $att = media_handle_upload('enregistrement_audio', 0);
            if (!is_wp_error($att)) {
                $audio_id = (int) $att;
                update_post_meta($audio_id, '_lfi_appel_audio', 1);
            }
        }
        if ($audio_id) $data['audio_attachment_id'] = $audio_id;

        if ($is_edit) {
            $wpdb->update($t, $data, ['id' => $row->id, 'owner_user_id' => $owner]);
            wp_safe_redirect(lfi_nct_app_url('appel-nmh-edit', ['id' => $row->id, 'saved' => 1]));
        } else {
            $data['owner_user_id'] = $owner;
            $wpdb->insert($t, $data);
            $new_id = (int) $wpdb->insert_id;
            wp_safe_redirect(lfi_nct_app_url('appel-nmh-edit', ['id' => $new_id, 'saved' => 1]));
        }
        exit;
    }

    /* Pré-remplissage tenant depuis URL */
    if (!$is_edit && !empty($_GET['tenant_uid'])) {
        $tu = get_userdata((int) $_GET['tenant_uid']);
        if ($tu) $row = (object) ['tenant_user_id' => $tu->ID, 'tenant_label' => $tu->display_name];
    }

    $r = $row ?: (object) [
        'tenant_user_id' => '', 'tenant_label' => '', 'date_appel' => current_time('Y-m-d\TH:i'),
        'duree_minutes' => '', 'interlocuteur' => '', 'objet' => '', 'incidents' => '[]', 'notes' => '',
        'facture' => 0, 'id' => 0,
    ];
    $cur_inc = json_decode($r->incidents ?? '[]', true) ?: [];
    $date_val = $r->date_appel ? date('Y-m-d\TH:i', strtotime($r->date_appel)) : current_time('Y-m-d\TH:i');

    lfi_nct_app_screen_open($is_edit ? '✏️ Appel #' . $row->id : '+ Enregistrer un appel', 'Durée · interlocuteur · incidents');

    if (!empty($_GET['saved'])) lfi_nct_app_flash('✅ Appel enregistré.');

    echo '<form method="post" class="lfi-app-form" enctype="multipart/form-data">';
    wp_nonce_field('lfi_appel_save');
    echo '<input type="hidden" name="lfi_appel_save" value="1">';

    /* Locataire concerné (optionnel) */
    $tenants = get_users(['role' => LFI_NCT_ROLE_TENANT, 'fields' => ['ID', 'display_name'], 'number' => 500, 'orderby' => 'display_name', 'order' => 'ASC']);
    echo '<label>Locataire concerné (optionnel)<select name="tenant_user_id">';
    echo '<option value="">— Aucun / général —</option>';
    foreach ($tenants as $tu) {
        echo '<option value="' . (int) $tu->ID . '" ' . selected((int) $r->tenant_user_id, $tu->ID, false) . '>' . esc_html($tu->display_name) . '</option>';
    }
    echo '</select></label>';
    echo '<input type="hidden" name="tenant_label" value="' . esc_attr($r->tenant_label) . '">';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
    echo '<label>Date et heure de l\'appel<input type="datetime-local" name="date_appel" value="' . esc_attr($date_val) . '"></label>';
    echo '<label>Durée (minutes)<input type="number" name="duree_minutes" step="0.5" min="0" value="' . esc_attr($r->duree_minutes) . '" placeholder="ex: 12"></label>';
    echo '</div>';

    $montant_preview = ((float) $r->duree_minutes) * lfi_nct_appel_tarif_min();
    echo '<div class="lfi-app-help">💶 Coût facturable : <strong id="lfi-appel-montant">' . number_format($montant_preview, 2, ',', ' ') . ' €</strong> (' . number_format(lfi_nct_appel_tarif_min(), 2, ',', ' ') . ' €/min)</div>';

    echo '<label>Interlocuteur<input type="text" name="interlocuteur" value="' . esc_attr($r->interlocuteur) . '" placeholder="Nom du conseiller, ou « a refusé de donner son identité »"></label>';
    echo '<label>Objet de l\'appel<input type="text" name="objet" value="' . esc_attr($r->objet) . '" placeholder="ex: relance travaux Mme Fadila"></label>';

    /* Incidents */
    echo '<h3 style="margin:16px 0 4px;color:#a30b25">⚠ Incidents constatés</h3>';
    echo '<div class="lfi-app-help"><small>Coche tout ce qui s\'est mal passé. Ça génère un rapport d\'incident officiel.</small></div>';
    echo '<div style="display:grid;grid-template-columns:1fr;gap:4px;background:#fff;padding:10px;border-radius:8px">';
    foreach (lfi_nct_appel_incidents_labels() as $k => $lbl) {
        $check = in_array($k, $cur_inc, true) ? 'checked' : '';
        echo '<label style="display:flex;align-items:center;gap:8px;margin:0;padding:6px 0;cursor:pointer">';
        echo '<input type="checkbox" name="incidents[' . esc_attr($k) . ']" value="1" ' . $check . '>';
        echo '<span>' . esc_html($lbl) . '</span></label>';
    }
    echo '</div>';

    echo '<label>📝 Transcription / notes de l\'appel<textarea name="notes" id="lfi-appel-notes" rows="6" placeholder="Ce qui a été dit, mot pour mot si possible. Cite les propos exacts s\'il y a eu manque de respect.">' . esc_textarea($r->notes) . '</textarea></label>';
    echo '<div class="lfi-voice-zone" data-target="lfi-appel-notes" data-label="Dicter le compte-rendu"></div>';

    /* === ENREGISTREMENT AUDIO === */
    echo '<h3 style="margin:16px 0 4px;color:#0066a3">🎙 Enregistrement de l\'appel (preuve)</h3>';

    /* Lecteur si un enregistrement existe déjà */
    $audio_id = (int) ($r->audio_attachment_id ?? 0);
    if ($audio_id) {
        $audio_url = wp_get_attachment_url($audio_id);
        $mime = get_post_mime_type($audio_id);
        if ($audio_url) {
            echo '<div style="background:#e8f0ff;border-radius:8px;padding:12px;margin:6px 0">';
            echo '<div style="font-weight:700;color:#0066a3;margin-bottom:6px">✅ Enregistrement joint :</div>';
            if ($mime && strpos($mime, 'video') === 0) {
                echo '<video controls preload="none" style="width:100%;max-height:360px;border-radius:6px"><source src="' . esc_url($audio_url) . '" type="' . esc_attr($mime) . '">Ton navigateur ne peut pas lire la vidéo.</video>';
            } else {
                echo '<audio controls preload="none" style="width:100%"><source src="' . esc_url($audio_url) . '">Ton navigateur ne peut pas lire l\'audio.</audio>';
            }
            echo '<div style="margin-top:6px"><a href="' . esc_url($audio_url) . '" download style="font-size:.85em;color:#0066a3">⬇️ Télécharger le fichier</a></div>';
            echo '</div>';
        }
    }

    echo '<div style="background:#fff8e6;border-left:4px solid #bd8600;padding:12px 14px;border-radius:8px;margin:6px 0;font-size:.88em;line-height:1.5">';
    echo '<strong>📲 Comment enregistrer un appel sur iPhone :</strong><br><br>';
    echo '<strong>Méthode 1 — iOS 18 et + (le plus simple) :</strong> pendant l\'appel, appuie sur le bouton <strong>Enregistrer</strong> en haut à gauche. iPhone enregistre + transcrit (un message prévient ton interlocuteur, c\'est légal). À la fin, l\'enregistrement est dans l\'app <strong>Notes</strong>. Récupère le fichier audio et joins-le ci-dessous.<br><br>';
    echo '<strong>Méthode 2 — universelle :</strong> mets l\'appel sur <strong>haut-parleur</strong>, et sur un 2e appareil (ou via l\'app <strong>Dictaphone</strong>/Voice Memos en arrière-plan) enregistre la conversation. Exporte le fichier .m4a et joins-le ci-dessous.<br><br>';
    echo '<em>⚖️ En France, tu as le droit d\'enregistrer une conversation à laquelle tu participes pour t\'en servir comme preuve. Préviens idéalement ton interlocuteur (« cet appel est enregistré »).</em>';
    echo '</div>';

    echo '<label>🎙 Joindre l\'enregistrement (audio OU vidéo d\'écran)<input type="file" name="enregistrement_audio" accept="audio/*,video/*"></label>';
    echo '<div class="lfi-app-help"><small>Audio (m4a, mp3…) ou vidéo de capture d\'écran (mov, mp4). Le fichier devient une pièce du dossier, lisible directement ici.</small></div>';

    echo '<button type="submit" class="btn-primary big">' . ($is_edit ? '💾 Enregistrer' : '+ Enregistrer l\'appel') . '</button>';
    echo '</form>';

    /* Actions sur un appel existant */
    if ($is_edit) {
        echo '<div style="margin-top:20px;padding-top:16px;border-top:2px dashed #eee">';
        echo '<h3 style="margin:0 0 10px">🔧 Actions</h3>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';

        if (!empty($cur_inc)) {
            echo '<a class="btn-primary" style="background:#a30b25" href="' . esc_url(lfi_nct_app_url('appel-nmh-rapport', ['id' => $row->id])) . '" target="_blank">📄 Rapport d\'incident (imprimable)</a>';
        }

        if (!$r->facture && (float) $r->duree_minutes > 0) {
            echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Créer une intervention facturable pour cet appel (' . esc_js(number_format((float)$r->duree_minutes * lfi_nct_appel_tarif_min(), 2, ',', ' ')) . ' €) ?\')">';
            wp_nonce_field('lfi_appel_facturer');
            echo '<input type="hidden" name="lfi_appel_facturer" value="1">';
            echo '<button type="submit" class="btn-primary">🧾 Facturer cet appel (' . number_format((float)$r->duree_minutes * lfi_nct_appel_tarif_min(), 2, ',', ' ') . ' €)</button>';
            echo '</form>';
        } elseif ($r->facture) {
            echo '<span class="btn-ghost" style="opacity:.7">✓ Déjà facturé</span>';
        }

        echo '<form method="post" style="margin:0" onsubmit="return confirm(\'Supprimer cet appel ?\')">';
        wp_nonce_field('lfi_appel_delete');
        echo '<input type="hidden" name="lfi_appel_delete" value="1">';
        echo '<button type="submit" style="background:#fff;color:#a30b25;border:1.5px solid #a30b25;padding:8px 14px;border-radius:8px;font-weight:700;cursor:pointer">🗑 Supprimer</button>';
        echo '</form>';

        echo '</div></div>';
    }

    /* JS : recalcul live du montant + voice */
    ?>
    <script>
    (function () {
        var d = document.querySelector('[name=duree_minutes]');
        var out = document.getElementById('lfi-appel-montant');
        var tarif = <?php echo json_encode(lfi_nct_appel_tarif_min()); ?>;
        if (d && out) {
            d.addEventListener('input', function () {
                var m = (parseFloat(d.value) || 0) * tarif;
                out.textContent = m.toFixed(2).replace('.', ',') + ' €';
            });
        }
    })();
    </script>
    <?php
    if (function_exists('lfi_nct_render_voice_helper')) lfi_nct_render_voice_helper();

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Guide : comment enregistrer ses appels                          *
 * ============================================================== */
function lfi_nct_app_view_appel_guide() {
    if (!lfi_nct_can_use_brigade()) return;
    lfi_nct_app_screen_open('📖 Enregistrer mes appels', 'La marche à suivre, étape par étape');

    echo '<div class="lfi-app-help" style="background:#fff3f5;border-left:4px solid #c8102e">';
    echo '⚠️ <strong>Important :</strong> aucune app installée depuis ce site ne peut enregistrer un appel — Apple et Google le bloquent pour des raisons de sécurité. Mais ton iPhone sait déjà le faire tout seul. Voici comment.';
    echo '</div>';

    /* Méthode 1 — iOS 18 natif */
    echo '<div style="background:#fff;border:2px solid #186a3b;border-radius:12px;padding:16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#186a3b;font-size:1.05em;margin-bottom:6px">✅ MÉTHODE RECOMMANDÉE — iPhone (iOS 18 ou plus récent)</div>';
    echo '<div style="font-size:.92em;line-height:1.6;color:#333">';
    echo '<strong>Gratuit, intégré, et ça transcrit automatiquement le texte.</strong><br><br>';
    echo '1️⃣ Lance l\'appel à NMH normalement<br>';
    echo '2️⃣ En haut à gauche de l\'écran d\'appel, appuie sur le bouton <strong>« Enregistrer »</strong> (icône avec des ondes)<br>';
    echo '3️⃣ Un message annonce à NMH que l\'appel est enregistré (c\'est ce qui le rend légal)<br>';
    echo '4️⃣ À la fin de l\'appel, l\'enregistrement <strong>+ sa transcription écrite</strong> sont rangés dans l\'app <strong>Notes</strong> de l\'iPhone<br>';
    echo '5️⃣ Ouvre Notes → tu peux <strong>copier la transcription</strong> (à coller ici) et <strong>partager le fichier audio</strong> (à joindre à l\'appel)<br>';
    echo '</div>';
    echo '<div style="background:#e8f5ea;border-radius:8px;padding:10px;margin-top:10px;font-size:.88em">💡 Si tu ne vois pas le bouton Enregistrer : va dans <strong>Réglages → Apps → Téléphone</strong> et vérifie que l\'enregistrement d\'appel est activé. Disponible en France depuis iOS 18.2.</div>';
    echo '</div>';

    /* Méthode capture d'écran vidéo + micro */
    echo '<div style="background:#fff;border:2px solid #0066a3;border-radius:12px;padding:16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#0066a3;font-size:1.05em;margin-bottom:6px">🎬 MÉTHODE CAPTURE VIDÉO D\'ÉCRAN (marche sur tous les iPhone)</div>';
    echo '<div style="font-size:.92em;line-height:1.6;color:#333">';
    echo 'L\'enregistrement d\'écran capture la vidéo <strong>+ les 2 voix</strong> (toi et NMH). Mais attention, par défaut <strong>ton micro est coupé</strong> — il faut l\'activer une fois :<br><br>';
    echo '1️⃣ Ouvre le <strong>Centre de contrôle</strong> (glisse depuis le coin haut-droit)<br>';
    echo '2️⃣ <strong>Appui LONG</strong> (reste appuyé) sur le bouton d\'enregistrement d\'écran (le rond ⏺)<br>';
    echo '3️⃣ Appuie sur l\'icône <strong>🎤 Microphone</strong> en bas → elle devient <strong style="color:#c8102e">ROUGE</strong> (= micro activé)<br>';
    echo '4️⃣ Appuie sur <strong>Démarrer l\'enregistrement</strong>, puis lance ton appel sur haut-parleur<br>';
    echo '5️⃣ À la fin, la vidéo est dans <strong>Photos</strong> → tu peux la joindre à l\'appel ici<br>';
    echo '</div>';
    echo '<div style="background:#fff8e6;border-radius:8px;padding:10px;margin-top:10px;font-size:.88em">💡 Le réglage du micro reste mémorisé : tu ne le fais qu\'une fois. Si ta voix n\'est pas enregistrée, c\'est que le micro 🎤 était gris (coupé) au lieu de rouge.</div>';
    echo '</div>';

    /* Méthode 2 — app dédiée */
    echo '<div style="background:#fff;border:1.5px solid #ddd;border-radius:12px;padding:16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#0066a3;font-size:1.05em;margin-bottom:6px">📲 SI TON IPHONE EST PLUS ANCIEN — app dédiée</div>';
    echo '<div style="font-size:.92em;line-height:1.6;color:#333">';
    echo 'Les apps qui marchent vraiment utilisent une « conférence à 3 » : elles ajoutent une ligne qui enregistre. Les plus fiables :<br><br>';
    echo '• <strong>TapeACall</strong> (App Store) — la référence, ~10 €/an<br>';
    echo '• <strong>Rev Call Recorder</strong> (App Store) — gratuit, transcription payante<br><br>';
    echo 'Après l\'appel, l\'app te donne un fichier audio → tu le joins à l\'appel ici.';
    echo '</div>';
    echo '</div>';

    /* Méthode 3 — universelle */
    echo '<div style="background:#fff;border:1.5px solid #ddd;border-radius:12px;padding:16px;margin:14px 0">';
    echo '<div style="font-weight:800;color:#bd8600;font-size:1.05em;margin-bottom:6px">🔊 SOLUTION DE SECOURS — sans rien installer</div>';
    echo '<div style="font-size:.92em;line-height:1.6;color:#333">';
    echo '1️⃣ Mets l\'appel sur <strong>haut-parleur</strong><br>';
    echo '2️⃣ Sur un <strong>2e téléphone</strong> (ou celui d\'un voisin), lance l\'app <strong>Dictaphone</strong> et enregistre<br>';
    echo '3️⃣ Exporte le fichier et joins-le à l\'appel ici<br>';
    echo '</div>';
    echo '</div>';

    echo '<div class="lfi-app-help" style="background:#e8f0ff;border-left:4px solid #0066a3">';
    echo '⚖️ <strong>C\'est légal :</strong> en France, tu as le droit d\'enregistrer une conversation à laquelle tu participes, pour t\'en servir comme preuve. L\'idéal est de prévenir (« cet appel est enregistré ») — ce que fait l\'iPhone automatiquement.';
    echo '</div>';

    echo '<div style="margin-top:16px"><a class="btn-primary big" href="' . esc_url(lfi_nct_app_url('appel-nmh-add')) . '">+ Enregistrer un appel maintenant</a></div>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  Rapport d'incident (imprimable + email)                         *
 * ============================================================== */
function lfi_nct_app_view_appel_nmh_rapport() {
    if (!lfi_nct_can_use_brigade()) return;
    $row = lfi_nct_appel_get((int) ($_GET['id'] ?? 0));
    if (!$row) { wp_die('Appel introuvable'); }

    $presta = function_exists('lfi_nct_fact_prestataire') ? lfi_nct_fact_prestataire() : [];
    $bailleur = function_exists('lfi_nct_fact_bailleur') ? lfi_nct_fact_bailleur() : ['nom' => 'Nantes Métropole Habitat'];
    $inc = json_decode($row->incidents ?? '[]', true) ?: [];
    $inc_labels = lfi_nct_appel_incidents_labels();

    lfi_nct_app_screen_open('📄 Rapport d\'incident', 'Appel #' . $row->id);
    if (function_exists('lfi_nct_rec_doc_styles')) lfi_nct_rec_doc_styles();

    echo '<div class="lfi-rec-doc">';

    echo '<h1>Rapport d\'incident — relation téléphonique</h1>';

    echo '<div class="expediteur">';
    echo '<strong>Groupe d\'Action La France Insoumise<br>Nantes Sud — Clos Toreau</strong><br>';
    if (!empty($presta['nom']))     echo 'Représenté par : ' . esc_html($presta['nom']) . '<br>';
    if (!empty($presta['email']))   echo 'Mél. : ' . esc_html($presta['email']) . '<br>';
    if (!empty($presta['tel']))     echo 'Tél. : ' . esc_html($presta['tel']);
    echo '</div>';

    echo '<div class="destinataire">';
    echo '<strong>' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . '</strong><br>';
    echo 'Direction de la relation aux locataires<br>';
    if (!empty($bailleur['agence_nom']))     echo esc_html($bailleur['agence_nom']) . '<br>';
    if (!empty($bailleur['agence_contact'])) echo esc_html($bailleur['agence_contact']);
    echo '</div>';

    echo '<div class="lieu-date">À Nantes, le ' . esc_html(wp_date('j F Y')) . '</div>';

    echo '<p class="objet">Objet : Signalement d\'un incident survenu lors d\'un appel téléphonique au ' . esc_html(lfi_nct_nmh_phone()) . '</p>';

    echo '<h2>Circonstances de l\'appel</h2>';
    echo '<table class="detail">';
    if ($row->date_appel)    echo '<tr><td>Date et heure</td><td class="num">' . esc_html(wp_date('j F Y à H\hi', strtotime($row->date_appel))) . '</td></tr>';
    if ($row->duree_minutes) echo '<tr><td>Durée de l\'appel</td><td class="num">' . esc_html(rtrim(rtrim(number_format($row->duree_minutes, 2, ',', ' '), '0'), ',')) . ' minutes</td></tr>';
    echo '<tr><td>Numéro appelé</td><td class="num">' . esc_html(lfi_nct_nmh_phone()) . '</td></tr>';
    echo '<tr><td>Interlocuteur</td><td class="num">' . esc_html($row->interlocuteur ?: 'A refusé de communiquer son identité') . '</td></tr>';
    if ($row->objet)         echo '<tr><td>Objet de l\'appel</td><td class="num">' . esc_html($row->objet) . '</td></tr>';
    if ($row->tenant_label)  echo '<tr><td>Locataire concerné</td><td class="num">' . esc_html($row->tenant_label) . '</td></tr>';
    echo '</table>';

    echo '<h2>Incidents constatés</h2>';
    if (empty($inc)) {
        echo '<p>Aucun incident catégorisé.</p>';
    } else {
        echo '<div class="citations"><ul>';
        foreach ($inc as $k) {
            if (isset($inc_labels[$k])) {
                /* Retire l'emoji du label pour le rapport formel */
                $clean = trim(preg_replace('/^[^\p{L}]+/u', '', $inc_labels[$k]));
                echo '<li>' . esc_html($clean) . '</li>';
            }
        }
        echo '</ul></div>';
    }

    if ($row->notes) {
        echo '<h2>Compte-rendu détaillé</h2>';
        echo '<p>' . nl2br(esc_html($row->notes)) . '</p>';
    }

    if (!empty($row->audio_attachment_id)) {
        echo '<h2>Pièce justificative</h2>';
        echo '<p>Un <strong>enregistrement audio de la conversation</strong> a été conservé et peut être communiqué sur demande, ou produit à l\'appui de toute procédure de médiation ou contentieuse.</p>';
    }

    echo '<h2>Rappel des obligations</h2>';
    echo '<p>En tant qu\'organisme de logement social investi d\'une mission de service public, ' . esc_html($bailleur['nom'] ?? 'Nantes Métropole Habitat') . ' est tenu à une obligation de <strong>traitement respectueux et diligent</strong> des sollicitations de ses locataires et de leurs représentants mandatés.</p>';
    echo '<p>Les comportements constatés ci-dessus — notamment le défaut de courtoisie, le refus d\'identification de l\'agent, ou le refus de traiter la demande — sont contraires à la <strong>charte de qualité de service</strong> du logement social et aux principes de la relation de service public.</p>';

    echo '<h2>Demande</h2>';
    echo '<div class="citations">';
    echo '<p>Je vous demande, par le présent rapport :</p>';
    echo '<ul>';
    echo '<li>de prendre acte de l\'incident signalé ;</li>';
    echo '<li>de me communiquer l\'identité de l\'agent concerné et les suites données ;</li>';
    echo '<li>de garantir un traitement respectueux et effectif des prochaines sollicitations ;</li>';
    echo '<li>de me rappeler sous 48 heures au sujet du dossier évoqué.</li>';
    echo '</ul>';
    echo '</div>';

    echo '<p>À défaut, ce rapport viendra alimenter le dossier transmis aux instances de médiation (médiateur du logement, Défenseur des droits) et, le cas échéant, à l\'appui des procédures contentieuses en cours.</p>';

    echo '<p>Je vous prie d\'agréer, Madame, Monsieur, l\'expression de mes salutations distinguées.</p>';

    echo '<div class="signature">' . esc_html($presta['nom'] ?? 'Le Groupe d\'Action LFI') . '</div>';

    echo '</div>';
    lfi_nct_app_screen_close(false);
}
