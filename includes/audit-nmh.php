<?php
/**
 * AUDIT NMH — « Où va mon loyer ? »
 *
 * Argumentaire financier sur Nantes Métropole Habitat, construit sur des
 * chiffres PUBLICS et SOURCÉS (jamais un loyer individuel, toujours des
 * ratios moyens) :
 *   · Délibération NMH — CA du 26/06/2025 (comptes annuels 2024, certifiés
 *     FIDUCIAL AUDIT) ;
 *   · Rapport de la Chambre régionale des comptes des Pays de la Loire —
 *     ROD n°2025-134, publié le 17 décembre 2025 (président Luc Héritier) ;
 *   · Réponse officielle de NMH au ROD (Thomas Quéro / Marc Patay, 24/10/2025).
 *
 * Trois versions pratiques du même argumentaire : pour les élus (armer
 * William), pour les membres du GA (au porte-à-porte), pour le national.
 * Analyse en trois regards : mathématicien, sociologue, historien.
 *
 * Ligne rouge (rappelée en tête) : la priorité reste de sortir les gens de
 * leurs conditions le plus vite possible. Faits secs, jamais d'insulte. On
 * expose l'écart entre le discours et les preuves — on n'attaque personne.
 */
if (!defined('ABSPATH')) exit;

/** Petit utilitaire d'affichage d'une carte titrée. */
function lfi_nct_audit_card($html, $accent = '#c8102e') {
    echo '<div class="lfi-app-card" style="border-left:4px solid ' . esc_attr($accent) . '"><div class="com">' . $html . '</div></div>';
}

/* ============================================================== *
 *  VUE : Audit NMH « Où va mon loyer ? »                         *
 * ============================================================== */
function lfi_nct_app_view_audit_nmh() {
    lfi_nct_app_screen_open('💶 Où va mon loyer ?', 'Audit NMH — chiffres publics, sourcés. Ratios moyens, jamais un loyer individuel.');

    /* Cap non négociable (comme les écrans stratégie). */
    echo '<div class="lfi-app-card" style="border:2px solid #186a3b;background:#eef7ee"><div class="com"><strong>🎯 Cap :</strong> cet argumentaire <strong>arme</strong> le dialogue (élus, presse, national). Il ne passe <strong>jamais</strong> avant un relogement urgent. Faits secs, jamais d\'insulte : on montre l\'écart entre le discours et les preuves.</div></div>';

    /* -------- L'alerte, en une phrase -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">L\'essentiel en une ligne</h3>';
    lfi_nct_audit_card('Sur un loyer moyen de <strong>330 €/mois</strong>, seulement <strong>21 €</strong> (6 %) reviennent à l\'entretien du logement. Le reste part surtout aux <strong>banques</strong> (33 %), à l\'<strong>État</strong> via la taxe foncière (16 %) et au <strong>fonctionnement</strong> de NMH (22 %). Source : comptes 2024 (CA du 26/06/2025) + rapport CRC ROD n°2025-134.');

    /* -------- Chiffres-clés -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Les chiffres-clés (sourcés)</h3>';
    echo '<div class="lfi-app-card"><div class="com">';
    echo '<ul style="margin:0;padding-left:18px;line-height:1.8">';
    echo '<li><strong>+23 000 logements</strong> gérés — 40 % du parc social de la métropole.</li>';
    echo '<li><strong>181 M€</strong> de produits en 2024 · <strong>~939 M€</strong> d\'encours de dette.</li>';
    echo '<li>Résultat net : <strong>−90 % en 5 ans</strong> (9,1 M€ en 2019 → 966 285 € en 2024).</li>';
    echo '<li>Dette : <strong>+64 M€ nets par an</strong> (88,9 M€ empruntés, 24,5 M€ remboursés en 2024).</li>';
    echo '<li>Gros entretien : <strong>21 €/mois/logement</strong> seulement.</li>';
    echo '</ul>';
    echo '<div style="margin-top:8px"><small>Gouvernance : président Thomas Quéro (également adjoint à l\'urbanisme, Ville de Nantes) · DG Marc Patay. La CRC pointe un <em>risque de conflit d\'intérêts</em> (recommandation n°2).</small></div>';
    echo '</div></div>';

    /* -------- Le tableau « où va le loyer » -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Où va un loyer de 330 €/mois ?</h3>';
    echo '<div class="lfi-app-card"><div class="com">';
    echo '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.95em">';
    echo '<thead><tr style="text-align:left;border-bottom:2px solid #c8102e">'
       . '<th style="padding:6px 4px">Poste</th><th style="padding:6px 4px;text-align:right">€/mois</th><th style="padding:6px 4px;text-align:right">%</th></tr></thead><tbody>';
    foreach ([
        ['Intérêts bancaires (emprunts NMH)', '109 €', '33 %', true],
        ['Salaires du personnel NMH',          '73 €',  '22 %', false],
        ['Taxes foncières (TFPB, → État)',     '53 €',  '16 %', false],
        ['Autres (assurances, amortissements…)','62 €', '19 %', false],
        ['Gros entretien de votre logement',   '21 €',  '6 %',  'green'],
        ['Entretien courant parties communes', '12 €',  '4 %',  false],
    ] as $r) {
        $bg = $r[3] === true ? 'background:#fbe9ec;font-weight:700' : ($r[3] === 'green' ? 'background:#eef7ee;font-weight:700' : '');
        echo '<tr style="border-bottom:1px solid #eee;' . $bg . '">'
           . '<td style="padding:6px 4px">' . esc_html($r[0]) . '</td>'
           . '<td style="padding:6px 4px;text-align:right">' . esc_html($r[1]) . '</td>'
           . '<td style="padding:6px 4px;text-align:right">' . esc_html($r[2]) . '</td></tr>';
    }
    echo '<tr style="border-top:2px solid #c8102e;font-weight:800"><td style="padding:6px 4px">TOTAL</td><td style="padding:6px 4px;text-align:right">330 €</td><td style="padding:6px 4px;text-align:right">100 %</td></tr>';
    echo '</tbody></table></div>';
    echo '<div style="margin-top:8px"><small>Charges 2024 (comptes certifiés) ramenées à 330 € : dotations amortissements 40 % du loyer, charges financières 33 %, gros entretien 6 %. <em>Ratios moyens — pas la décomposition d\'un loyer individuel.</em></small></div>';
    echo '</div></div>';

    /* -------- Les trois regards (bots) -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">L\'analyse en trois regards</h3>';

    lfi_nct_audit_card(
        '<strong>🧮 Le mathématicien.</strong> 21 €/mois × 600 logements × 12 = <strong>~151 000 €/an</strong> de gros entretien pour Clos Toreau. Une réhabilitation par des artisans locaux coûte ~14 M€ : à ce rythme, elle serait financée en <strong>~93 ans</strong>. Les immeubles ont <strong>53 ans</strong> (construits 1971-1973). Autre écart mesurable : le même chantier confié à de grands groupes nationaux coûte ~32 M€ (53 300 €/logement) contre ~13,9 M€ chez les artisans nantais (23 100 €/logement) — soit <strong>~18 M€ de surcoût</strong> (×2,3).',
        '#2c3e91');

    lfi_nct_audit_card(
        '<strong>👥 Le sociologue.</strong> À Clos Toreau, <strong>67 % des ménages</strong> déclarent 2 problèmes ou plus dans leur logement (enquête voisinage 2026), gravité moyenne 6,9/10. Le quartier est <strong>hors NPNRU</strong> : il ne touche <strong>aucune</strong> subvention ANRU, quand des quartiers comparables bénéficient de centaines de millions. Un sous-investissement chronique dans le bâti se paie en santé, en dignité et en assignation sociale des habitants — une inégalité de traitement <strong>documentable</strong>.',
        '#186a3b');

    lfi_nct_audit_card(
        '<strong>🏛️ L\'historien.</strong> Ces tours (R+7/R+8) datent du grand programme des années 1970. Depuis, le financement du logement social a basculé des <em>aides à la pierre</em> vers la <strong>dette</strong> : NMH emprunte pour construire et rembourse sur les loyers. Le désengagement de l\'État aggrave l\'étau — la <strong>Réduction de Loyer de Solidarité</strong> et divers prélèvements ont amputé la capacité d\'autofinancement (impact direct estimé à <strong>−4,5 M€</strong> sur la CAF 2024). Résultat : la charge de la dette grignote ce qui devrait aller au bâti.',
        '#8a6d1f');

    /* -------- Argumentaire, 3 versions -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">L\'argumentaire — 3 versions</h3>';
    echo '<div class="lfi-app-help">Le même fond, adapté à qui vous parlez. Cliquez pour déplier.</div>';

    /* Version élus (William) */
    echo '<details class="lfi-app-card" style="border-left:4px solid #2c3e91"><summary style="cursor:pointer;font-weight:800">🏛️ Pour les élus (armer William)</summary><div class="com" style="margin-top:8px">';
    echo '<p>Une <strong>question factuelle et ouverte</strong> au conseil, sans citer de loyer individuel ni l\'enquête :</p>';
    echo '<blockquote style="border-left:3px solid #ccc;margin:8px 0;padding-left:12px;font-style:italic">« Le rapport de la Chambre régionale des comptes (ROD n°2025-134) relève que le gros entretien du parc représente environ 6 % du loyer, quand la charge de la dette en absorbe un tiers. Pour un quartier <strong>hors NPNRU</strong> comme Clos Toreau (600 logements, bâti de 1971), pouvez-vous communiquer au conseil le <strong>Plan Pluriannuel de Travaux</strong> et le calendrier d\'investissement prévu ? »</blockquote>';
    echo '<p><strong>Pourquoi ça marche :</strong> ce sont ses propres institutions (CRC + PPT) qui répondent. Pas d\'accusation → pas de contre-attaque possible. L\'écart parle seul.</p>';
    echo '</div></details>';

    /* Version membres GA (porte-à-porte) */
    echo '<details class="lfi-app-card" style="border-left:4px solid #186a3b"><summary style="cursor:pointer;font-weight:800">🚪 Pour le porte-à-porte (membres du GA)</summary><div class="com" style="margin-top:8px">';
    echo '<p>Phrase simple, sans jargon, <strong>sans culpabiliser le locataire</strong> :</p>';
    echo '<blockquote style="border-left:3px solid #ccc;margin:8px 0;padding-left:12px;font-style:italic">« Vous savez, sur 330 € de loyer, à peine 21 € reviennent vraiment à l\'entretien de votre logement. Ce n\'est pas normal que ça se dégrade — et ce n\'est pas de votre faute. On documente ça avec tout le quartier. »</blockquote>';
    echo '<p><strong>À faire :</strong> écouter d\'abord, noter le problème, proposer l\'enquête. <strong>À éviter :</strong> promettre un résultat, donner un chiffre inventé, attaquer NMH devant la personne (ça l\'inquiète pour son bail). On rassure, on documente, on oriente.</p>';
    echo '</div></details>';

    /* Version national */
    echo '<details class="lfi-app-card" style="border-left:4px solid #8a6d1f"><summary style="cursor:pointer;font-weight:800">🇫🇷 Pour le national (élément de langage)</summary><div class="com" style="margin-top:8px">';
    echo '<p>Un cas <strong>réplicable</strong>, appuyé sur de la donnée agrégée :</p>';
    echo '<blockquote style="border-left:3px solid #ccc;margin:8px 0;padding-left:12px;font-style:italic">« À Nantes, la Chambre régionale des comptes constate qu\'un bailleur social investit 6 % du loyer dans l\'entretien pendant que la dette en absorbe un tiers. Ce n\'est pas de la mauvaise gestion isolée : c\'est le résultat du <strong>désengagement de l\'État</strong> (Réduction de Loyer de Solidarité, prélèvements) qui force les bailleurs à emprunter pour entretenir. Les locataires paient l\'addition d\'un choix national. »</blockquote>';
    echo '<p><strong>Le levier :</strong> la méthode (enquête terrain + audit sourcé) se déploie dans n\'importe quel GA. C\'est du concret qui remonte, pas un slogan.</p>';
    echo '</div></details>';

    /* -------- 4 leviers d'action -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">4 leviers d\'action</h3>';
    echo '<ul class="lfi-app-list">';
    foreach ([
        ['1 · Juridique immédiat', 'Demander par <strong>CADA</strong> le Plan Pluriannuel de Travaux (PPT) de Clos Toreau. Absence ou vacuité du document = manquement exploitable devant le Tribunal judiciaire de Nantes.'],
        ['2 · Au CA de NMH', 'Les représentants locataires au CA (<strong>INDECOSA-CGT + CLCV</strong>) peuvent déposer une <strong>question écrite</strong> exigeant le bilan d\'investissement détaillé par quartier, focus Clos Toreau.'],
        ['3 · Politique / presse', 'Enquête voisinage (67 % / 2+ problèmes / 6,9-10) + cet audit = <strong>dossier de presse complet</strong>. Clos Toreau <strong>hors NPNRU</strong> = inégalité de traitement documentable (d\'autres quartiers touchent l\'ANRU).'],
        ['4 · Levier institutionnel', '<strong>21 € d\'entretien sur 330 € de loyer</strong> : argument central pour interpeller la présidence du CA et Nantes Métropole. La CRC elle-même alerte sur l\'insuffisance des investissements dans le parc existant.'],
    ] as $it) {
        echo '<li class="lfi-app-card" style="border-left:4px solid #c8102e"><div class="head"><div class="who">' . esc_html($it[0]) . '</div></div><div class="com">' . wp_kses_post($it[1]) . '</div></li>';
    }
    echo '</ul>';

    /* -------- Garde-fous + sources -------- */
    echo '<h3 style="margin:16px 0 6px;color:#c8102e">Garde-fous</h3>';
    echo '<div class="lfi-app-card"><div class="com"><strong>Ratios moyens uniquement</strong> — jamais la décomposition d\'un loyer individuel. · <strong>Faits secs</strong> — pas d\'adjectif, pas d\'insulte : « menteuse / incompétente » se retourne contre nous. · <strong>RGPD</strong> — aucun nom, aucune situation individuelle sans accord écrit. · On expose <strong>l\'écart</strong> entre le discours et les preuves, on n\'attaque pas les personnes.</div></div>';

    echo '<div class="lfi-app-help" style="margin-top:10px;background:#f7f7f7"><small>📚 <strong>Sources :</strong> Délibération NMH — CA du 26/06/2025 (comptes annuels 2024, certifiés FIDUCIAL AUDIT) · Rapport CRC Pays de la Loire ROD n°2025-134 (17 décembre 2025, président Luc Héritier) · Réponse officielle NMH au ROD (Thomas Quéro / Marc Patay, 24 octobre 2025) · Convention partenariat NM-NMH 2023-2032 · Enquête voisinage Nantes Sud 2026.</small></div>';

    lfi_nct_app_screen_close();
}
