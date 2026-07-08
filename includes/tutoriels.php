<?php
/**
 * Bibliothèque de tutoriels PRO — guide de la brigade travaux.
 *
 * Vraies solutions, vraies marques, vraies sources d'achat (Brico Dépôt,
 * ManoMano Pro, Pestclic, Amazon), vrais protocoles utilisés par les
 * artisans, vrais coûts amortis pour la brigade.
 *
 * RÉSERVÉ À L'ADMIN (Fabrice Doucet) — pas accessible aux locataires.
 */
if (!defined('ABSPATH')) exit;

function lfi_nct_tutoriels_all() {
    return [

        /* =============================================================== */
        'humidite' => [
            'icone' => '🌫',
            'titre' => 'Moisissures — démolition + repose plâtre',
            'sous'  => 'Le vrai protocole pro, biocide stable, BA13 hydro',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 1. Outils nécessaires',
                    'contenu' => '<ul>'
                        . '<li>Aspirateur HEPA H13 (Karcher WD3 + filtre) — 85 € Brico Dépôt</li>'
                        . '<li>Pulvérisateur 5 L Hozelock — 12 € Brico Dépôt</li>'
                        . '<li>Couteaux à enduire 10 + 25 + 40 cm — 15 € ManoMano Pro</li>'
                        . '<li>Scie égoïne pour plaques de plâtre — 6 € Brico Dépôt</li>'
                        . '<li>Cale à poncer + abrasifs 80 / 120 / 240 — 8 € Brico Dépôt</li>'
                        . '<li>Visseuse Bosch GSR 12V — 79 € Amazon</li>'
                        . '<li>Mètre laser Leica Disto D1 — 59 € ManoMano Pro</li>'
                        . '<li>Hygromètre digital — 8 € Brico Dépôt</li>'
                        . '<li>EPI : masque FFP3, gants nitrile, lunettes, combinaison Tyvek — 25 € Amazon</li>'
                        . '</ul>'
                        . '<p style="margin-top:8px"><strong>Investissement initial brigade : ~ 300 €</strong>, amorti dès la 2e intervention facturée.</p>',
                ],
                'materiaux' => [
                    'titre' => '🧪 2. Matériaux par intervention (pour 4 m²)',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Biocide pro Algiclair Pro</strong> 1 L (peroxyde stabilisé, sans javel)</td><td style="text-align:center">14 €</td><td style="text-align:center"><strong>ManoMano Pro</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Plaque BA13 hydro verte 1,20 × 2,50 m × 2</td><td style="text-align:center">17 €</td><td style="text-align:center"><strong>Brico Dépôt</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Vis Spax TT 35 mm boîte 200</td><td style="text-align:center">6 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Bande à joint papier 90 m (Knauf)</td><td style="text-align:center">5 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Enduit à joint Toupret 30 min (5 kg)</td><td style="text-align:center">12 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Sous-couche bloquante Julien Stop Tâches 1 L</td><td style="text-align:center">8 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Peinture Dulux Valentine Cuisines &amp; Bains 1 L</td><td style="text-align:center">18 €</td><td style="text-align:center">Castorama</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Joint silicone sanitaire Sikaflex 11FC</td><td style="text-align:center">6 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Bâche polyane 4 × 5 m + sacs gravats x 5</td><td style="text-align:center">15 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">EPI à usage unique pour ce chantier</td><td style="text-align:center">8 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL matériaux 4 m²</strong></td><td style="text-align:center"><strong>109 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturé NMH :</strong> 4 h × 40 €/h + 109 € matériaux = <strong>269 € HT</strong>. Marge nette ≈ 160 €.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 3. Procédure pro complète (4 h sur place)',
                    'contenu' => '<p><strong>PHASE 1 — Préparation chantier (20 min)</strong></p>'
                        . '<ol>'
                        . '<li>Sortir le mobilier ou bâcher 3 m autour de la zone (polyane).</li>'
                        . '<li>Mettre la VMC à l\'arrêt pendant l\'intervention.</li>'
                        . '<li>EPI : combinaison Tyvek + masque FFP3 + gants + lunettes.</li>'
                        . '<li>Aspirateur HEPA branché, embout fin prêt.</li>'
                        . '<li>Photos AVANT (preuve facture + dossier locataire).</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 2 — Dépose propre (1 h pour 4 m²)</strong></p>'
                        . '<ol>'
                        . '<li>Délimiter la zone : tracer un rectangle <strong>avec 30 cm de marge</strong> autour des taches visibles.</li>'
                        . '<li>Couper au cutter le long du tracé sur 1 cm, puis scie égoïne sur 13 mm.</li>'
                        . '<li><strong>Démonter par plaques entières</strong>, aspirateur HEPA en marche.</li>'
                        . '<li>Sacs gravats fermés au scotch armé. Sortir au plus vite par le palier.</li>'
                        . '<li>Brosser l\'ossature métallique au pulvérisateur d\'Algiclair Pro.</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 3 — Décontamination support (30 min + 4 h de pause)</strong></p>'
                        . '<ol>'
                        . '<li>Pulvériser <strong>Algiclair Pro pur</strong> sur tout le support + 30 cm autour.</li>'
                        . '<li>Laisser agir 4 h.</li>'
                        . '<li>Pendant ce temps, vérifier la VMC : aspiration avec feuille de papier doit coller. Si HS, mention au rapport.</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 4 — Pose plaque BA13 hydro (1 h pour 4 m²)</strong></p>'
                        . '<ol>'
                        . '<li>Mesurer puis tracer au cordeau.</li>'
                        . '<li>Couper la plaque au cutter : trois passages côté carton, casser net.</li>'
                        . '<li>Visser sur ossature avec vis Spax TT 35 mm, <strong>1 vis tous les 25 cm</strong>.</li>'
                        . '<li>Joints décalés entre plaques.</li>'
                        . '<li>Bande à joint papier humidifiée, posée sur enduit frais.</li>'
                        . '<li>Enduit Toupret 30 min en 2 passes : noyée puis lissée large.</li>'
                        . '<li>Ponçage : grain 120 puis 240. Aspirer la poussière.</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 5 — Finition (30 min + séchage 24 h, à reprendre J+1)</strong></p>'
                        . '<ol>'
                        . '<li>Sous-couche Julien Stop Tâches au rouleau, 1 couche.</li>'
                        . '<li>Le lendemain : 2 couches de Dulux Valentine Cuisines &amp; Bains, espacées de 4 h.</li>'
                        . '<li>Joint silicone Sikaflex sur les raccords plinthe / plafond.</li>'
                        . '<li>Photos APRÈS (preuve facture).</li>'
                        . '</ol>',
                ],
                'securite' => [
                    'titre' => '🦺 4. Sécurité',
                    'contenu' => '<ul>'
                        . '<li><strong>Combinaison Tyvek + masque FFP3 obligatoires</strong> dès que le mycélium est visible.</li>'
                        . '<li>Ne JAMAIS travailler à sec sur du mycélium noir épais (Stachybotrys) — risque de mycotoxine.</li>'
                        . '<li><strong>Évacuer les locataires sensibles</strong> (enfants, asthmatiques, immunodéprimés) le temps de l\'intervention + 4 h après.</li>'
                        . '<li><strong>Ne pas mélanger</strong> Algiclair Pro (peroxyde) et eau de Javel : libère du chlore gazeux toxique.</li>'
                        . '<li>VMC arrêtée pendant l\'intervention, redémarrée 1 h après.</li>'
                        . '<li>EPI à jeter en sac fermé après le chantier.</li>'
                        . '</ul>',
                ],
                'cause' => [
                    'titre' => '🔍 5. Traiter la CAUSE',
                    'contenu' => '<ul>'
                        . '<li><strong>VMC bouchée / HS</strong> (90 % des cas en HLM) : nettoyage de la grille au vinaigre blanc, facturable 50 €. Remplacement moteur = bailleur.</li>'
                        . '<li><strong>Grille d\'aération bouchée</strong> dans les pièces humides : nettoyage idem.</li>'
                        . '<li><strong>Infiltration extérieure</strong> : mention dans le rapport pour LRAR au bailleur.</li>'
                        . '<li><strong>Remontée capillaire</strong> : traitement injection — bailleur uniquement (3 000-5 000 €).</li>'
                        . '<li><strong>Pont thermique</strong> : doublage liège ou polystyrène — facturable 80 €/m² posé.</li>'
                        . '</ul>',
                ],
                'sources' => [
                    'titre' => '🛒 6. Où acheter au moins cher',
                    'contenu' => '<ul>'
                        . '<li><strong>Brico Dépôt</strong> Saint-Herblain : plaques de plâtre, visserie, enduits, peintures — 30 % moins cher que Castorama.</li>'
                        . '<li><strong>ManoMano Pro</strong> (compte gratuit) : Algiclair, outils pro, livraison.</li>'
                        . '<li><strong>Amazon</strong> : EPI, petits outils ponctuels.</li>'
                        . '<li><strong>Brico Privé</strong> : ventes flash sur peintures pro jusqu\'à -50 %.</li>'
                        . '<li><strong>Leboncoin</strong> : matériel d\'occasion.</li>'
                        . '<li><strong>Eurometal Nantes</strong> : visserie en gros, tarifs pro sans SIRET.</li>'
                        . '<li><strong>Déchetterie de Saint-Aignan-de-Grand-Lieu</strong> : dépôt gratuit des plaques infestées.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'cafards' => [
            'icone' => '🪳',
            'titre' => 'Désinsectisation cafards / blattes germaniques',
            'sous'  => 'Protocole IPM pro, gel + IGR, sans pulvé en masse',
            'sections' => [
                'diagnostic' => [
                    'titre' => '🔬 1. Diagnostic et identification',
                    'contenu' => '<p>Espèce dominante au Clos Toreau et HLM Nantes Sud : <strong>blatte germanique (<em>Blattella germanica</em>)</strong>, 1-1,5 cm, brun clair, deux bandes noires sur le pronotum. Prolifère en cuisine/salle de bain (chaleur + humidité).</p>'
                        . '<ul>'
                        . '<li><strong>Pièges collants Trappit</strong> (5 € le lot de 10 sur Amazon) posés 3 jours dans les coins humides + arrière des électroménagers. Compter les pris — c\'est la mesure de pression.</li>'
                        . '<li>< 5 pris/jour = infestation légère, gel suffit.</li>'
                        . '<li>10-20 pris/jour = moyenne, gel + IGR.</li>'
                        . '<li>> 30 pris/jour = forte, protocole complet + retour à J+15.</li>'
                        . '<li><strong>Lampe UV</strong> (3 € sur Amazon) la nuit pour repérer leurs cachettes : ils fluorescent en bleu.</li>'
                        . '</ul>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils',
                    'contenu' => '<ul>'
                        . '<li>Lampe UV 365 nm — 3 € sur Amazon</li>'
                        . '<li>Pulvérisateur 1 L Hozelock — 8 € Brico Dépôt</li>'
                        . '<li>Pinceau fin 2 mm (pour gel dans fissures fines) — 2 €</li>'
                        . '<li>Aspirateur avec embout fin (pour les morts et nymphes)</li>'
                        . '<li>EPI : gants nitrile + masque chirurgical, lunettes</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 3. Produits PRO efficaces',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Goliath Gel Cafards 35 g</strong> (Syngenta, fipronil) — 1 seringue couvre un T3 entier</td><td style="text-align:center">12 €</td><td style="text-align:center"><strong>Pestclic.com</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Maxforce IC</strong> (BASF, imidaclopride) — alternance pour éviter résistance</td><td style="text-align:center">18 €</td><td style="text-align:center">Pestclic.com</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Sinergel IGR</strong> (S-méthoprène, régulateur de croissance) — stérilise les femelles</td><td style="text-align:center">22 €</td><td style="text-align:center">Pestclic.com</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Pièges Trappit (suivi de population)</td><td style="text-align:center">5 €/10</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL kit complet T3</strong></td><td style="text-align:center"><strong>40-60 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturable NMH</strong> : 1 h + 2 h (J+15) × 40 €/h + 50 € matériaux = <strong>170 € HT par appartement</strong>. Une seringue de Goliath traite 3 appartements en réalité (économies d\'échelle).</p>',
                ],
                'procedure' => [
                    'titre' => '👷 4. Protocole IPM (Integrated Pest Management) pro',
                    'contenu' => '<p><strong>JOUR 0 — Première intervention (1 h)</strong></p>'
                        . '<ol>'
                        . '<li><strong>Inspection lampe UV</strong> dans la pénombre : repérer les cachettes (sous évier, derrière frigo, dans les charnières de placards, autour des canalisations, joints carrelage descellés).</li>'
                        . '<li><strong>Aspiration</strong> des morts, nymphes, oothèques (capsules d\'œufs marron clair) avec embout fin.</li>'
                        . '<li><strong>Calfeutrage</strong> des fissures et passages de tuyaux au silicone Sikaflex (6 € la cartouche) — ferme les voies d\'invasion entre appartements.</li>'
                        . '<li><strong>Application gel Goliath</strong> : points de 5 mm de gel <strong>tous les 30 cm</strong> sur les trajets repérés. Privilégier :<ul>'
                        . '<li>Coins arrière du frigo et lave-vaisselle</li>'
                        . '<li>Sous évier, autour du siphon</li>'
                        . '<li>Charnières de placards de cuisine et salle de bain</li>'
                        . '<li>Joints carrelage descellés</li>'
                        . '<li>Trous de passage canalisations</li>'
                        . '</ul></li>'
                        . '<li><strong>Régulateur de croissance Sinergel</strong> en pulvérisation diluée selon notice (1:200) dans les fissures inaccessibles au gel.</li>'
                        . '<li><strong>JAMAIS de bombe insecticide simultanément</strong> — les cafards évitent les zones traitées, ils ne mangent plus le gel, et la résistance se développe.</li>'
                        . '<li>Pièges de suivi posés (3 dans la cuisine, 2 dans la salle de bain).</li>'
                        . '<li>Conseil au locataire : pas de nettoyage des points de gel pendant 30 jours, hygiène stricte (pas de miettes, vaisselle nette, poubelle fermée).</li>'
                        . '</ol>'
                        . '<p><strong>JOUR 7-15 — Suivi (30 min)</strong></p>'
                        . '<ol>'
                        . '<li>Recompter les pièges. Une bonne intervention = baisse de 80 % à J+15.</li>'
                        . '<li>Si baisse < 50 %, changer de molécule (alterner Goliath → Maxforce IC) car résistance probable.</li>'
                        . '</ol>'
                        . '<p><strong>JOUR 30 — Validation finale (30 min)</strong></p>'
                        . '<ol>'
                        . '<li>Pièges renouvelés. < 2 pris/semaine = traitement réussi.</li>'
                        . '<li>Sinon : 3e application gel + considération action collective (probable infestation d\'immeuble entier — c\'est au bailleur en HLM).</li>'
                        . '</ol>',
                ],
                'securite' => [
                    'titre' => '🦺 5. Sécurité',
                    'contenu' => '<ul>'
                        . '<li>Gel fipronil ou imidaclopride : <strong>toxique pour les chats</strong>. Si présence chat, placer le gel hors de portée (en hauteur, derrière les électroménagers).</li>'
                        . '<li>Pas de risque humain en utilisation normale (les points de gel font 5 mg).</li>'
                        . '<li>Pulvérisation Sinergel : aérer 30 min après.</li>'
                        . '<li>Bien se laver les mains après manipulation.</li>'
                        . '<li>Conserver le gel dans son emballage d\'origine pour les enfants — saveur sucrée attractive.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'punaises' => [
            'icone' => '🛏',
            'titre' => 'Désinsectisation punaises de lit',
            'sous'  => 'Vapeur + terre de Diatomée + housse — protocole pro',
            'sections' => [
                'diagnostic' => [
                    'titre' => '🔬 1. Diagnostic — confirmer que ce sont bien des punaises',
                    'contenu' => '<p>Souvent confondues avec puces ou moustiques. Vérifier :</p>'
                        . '<ul>'
                        . '<li><strong>Piqûres groupées en ligne</strong> (3-4 piqûres à la file) — signature.</li>'
                        . '<li><strong>Taches noires</strong> (déjections) sur draps, matelas, plinthes — points de la taille d\'une tête d\'épingle.</li>'
                        . '<li><strong>Taches de sang séché</strong> sur les draps (punaises écrasées).</li>'
                        . '<li><strong>Mues</strong> (peaux translucides) dans les coutures du matelas.</li>'
                        . '<li><strong>Œufs blanchâtres</strong> (1 mm) dans les fissures.</li>'
                        . '<li><strong>Odeur sucrée caractéristique</strong> en cas d\'infestation lourde.</li>'
                        . '</ul>'
                        . '<p><strong>Pièges Polynectar à phéromone</strong> (8 €/4 sur Amazon) pour confirmer + quantifier.</p>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Outil</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Nettoyeur vapeur</strong> 4+ bars (Polti Vaporetto Eco Pro ou Cleanmaxx)</td><td style="text-align:center">75 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Aspirateur HEPA + sacs jetables (5)</td><td style="text-align:center">déjà</td><td></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Pulvérisateur 1 L</td><td style="text-align:center">8 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Lampe LED + miroir télescopique inspection</td><td style="text-align:center">12 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr><td style="padding:6px"><strong>Sacs poubelle pyramide 130 L armés</strong></td><td style="text-align:center">10 €/lot</td><td style="text-align:center">Castorama</td></tr>'
                        . '</table>',
                ],
                'materiaux' => [
                    'titre' => '🧪 3. Produits PRO (par appartement)',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Terre de Diatomée ALIMENTAIRE</strong> (Naturasil, NaturaForce ou Esprit Pratique) — pas la filtre piscine (toxique pour les poumons)</td><td style="text-align:center">12 €/kg</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Subito Punaises de Lit</strong> (Caussade, molécule différente du Pyréthrines courant)</td><td style="text-align:center">14 €</td><td style="text-align:center">Pestclic.com</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Housse anti-punaises Protect-A-Bed Allerzip</strong> matelas 140×190 (LA vraie housse, zip dent fine — pas la version Lidl)</td><td style="text-align:center">35 €</td><td style="text-align:center">Amazon Pro</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Housse oreiller Allerzip × 2</td><td style="text-align:center">15 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL matériaux</strong></td><td style="text-align:center"><strong>76 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturable NMH</strong> : 2 passages × 3 h × 40 €/h + 80 € matériaux = <strong>320 € HT par appartement</strong>.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 4. Protocole 2-passages obligatoires',
                    'contenu' => '<p><strong>JOUR 0 — Première intervention (3 h)</strong></p>'
                        . '<ol>'
                        . '<li><strong>Inspection au cordeau</strong> avec lampe + miroir : coutures matelas, sommier, plinthes, plinthes derrière tête de lit, prises électriques (les ouvrir), cadres de tableaux, fissures de papier peint.</li>'
                        . '<li><strong>Lavage à 60 °C</strong> de tout le linge, draps, vêtements (les œufs meurent à 60 °C). Ce qui ne peut être lavé : congélateur à -18 °C pendant 72 h.</li>'
                        . '<li><strong>Aspirateur HEPA</strong> partout : matelas (passe lente), sommier, fissures, plinthes, derrière les meubles. Sac mis en sachet hermétique IMMÉDIATEMENT (poubelle dehors).</li>'
                        . '<li><strong>Vapeur à 120 °C</strong> (4 bars) sur tout le matelas, sommier, tête de lit, plinthes. Passages lents (30 cm/sec, 1 cm de distance). C\'est la pierre angulaire du traitement — c\'est ce qui tue le plus en une passe.</li>'
                        . '<li><strong>Subito Punaises</strong> en spray dans les fissures, joints, coutures inaccessibles à la vapeur. JAMAIS sur les zones de contact peau (matelas haut, oreillers).</li>'
                        . '<li><strong>Terre de Diatomée</strong> en fine poudre dans : sous le lit, le long des plinthes, derrière les prises (ne pas en mettre dans les prises elles-mêmes). Effet lent (10-15 j) mais imparable — elle déshydrate les bestioles.</li>'
                        . '<li><strong>Housse Allerzip</strong> sur matelas et oreillers, gardée 1 an minimum (durée de vie des œufs).</li>'
                        . '<li><strong>Sortir les sacs</strong> par escalier dans des sacs pyramide armés, scotchés.</li>'
                        . '</ol>'
                        . '<p><strong>JOUR 14 — 2ème intervention (3 h)</strong></p>'
                        . '<ol>'
                        . '<li>Re-vapeur intégrale (les œufs des œufs ont éclos).</li>'
                        . '<li>Re-aspirateur HEPA.</li>'
                        . '<li>Vérifier les pièges Polynectar.</li>'
                        . '<li>Renouveler la Terre de Diatomée si elle a été aspirée par mégarde.</li>'
                        . '</ol>'
                        . '<p><strong>Pourquoi 2 passages obligatoires</strong> : la vapeur et l\'insecticide ne tuent pas tous les œufs (chitine protectrice). Le 2e passage à J+14 attrape la génération éclose entre temps. C\'est la différence entre une intervention pro et un bricolage.</p>',
                ],
                'securite' => [
                    'titre' => '🦺 5. Sécurité et erreurs à éviter',
                    'contenu' => '<ul>'
                        . '<li><strong>Terre de Diatomée :</strong> bien prendre la version ALIMENTAIRE (étiquette). La version filtre piscine est calcinée (cristaux de silice) et dangereuse pour les poumons.</li>'
                        . '<li><strong>Masque pendant l\'épandage</strong> de Diatomée (irritante en poudre).</li>'
                        . '<li><strong>Subito Punaises</strong> : aérer 1 h après pulvérisation.</li>'
                        . '<li><strong>JAMAIS de bombe insecticide en masse</strong> dans la pièce : ça crée de la résistance, ça ne tue que ce qui est visible, et les survivants ressortent renforcés.</li>'
                        . '<li><strong>NE PAS donner ou jeter</strong> de meubles infestés dans les encombrants — c\'est comme ça que ça se propage à tout l\'immeuble. Brûler les pieds, sceller dans sacs, déchetterie.</li>'
                        . '<li>En HLM, la <strong>loi ELAN 2018</strong> impose au bailleur de traiter — facturation NMH parfaitement légitime.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'rats' => [
            'icone' => '🐀',
            'titre' => 'Dératisation rats / souris',
            'sous'  => 'Pose pièges + bouchage entrées — méthode hygiénique',
            'sections' => [
                'diagnostic' => [
                    'titre' => '🔬 1. Identification',
                    'contenu' => '<ul>'
                        . '<li><strong>Crottes :</strong> souris = 3-7 mm, allongées pointues ; rat surmulot = 12-19 mm, en boudin ; rat noir = 8-13 mm, fuselées.</li>'
                        . '<li><strong>Traces d\'usure</strong> dans les angles et plinthes (gras + poil de leur dos).</li>'
                        . '<li><strong>Bruits</strong> nocturnes dans les murs, faux plafonds, vides sanitaires.</li>'
                        . '<li><strong>Trous d\'entrée :</strong> souris = taille pièce 2 €, rat = taille balle de tennis.</li>'
                        . '<li><strong>Lampe UV nocturne</strong> : l\'urine fluorescent en bleu.</li>'
                        . '</ul>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils',
                    'contenu' => '<ul>'
                        . '<li>Gants de cuir épais (manipulation pièges + cadavres) — 12 €</li>'
                        . '<li>Pince à long manche pour ramasser sans toucher — 8 €</li>'
                        . '<li>Lampe frontale puissante — 10 €</li>'
                        . '<li>Sacs solides + spray désinfectant pour nettoyer après — 5 €</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 3. Pièges et bouchage',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Tapette Victor M040</strong> (la classique acier, plus efficace que les nouvelles plastique)</td><td style="text-align:center">3 € pièce</td><td style="text-align:center">Amazon / Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Tapette Kness pour rat</strong> (plus puissante)</td><td style="text-align:center">8 €</td><td style="text-align:center">Pestclic</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Bloc raticide Difenacoum</strong> (Bobby ou Caussade) en station sécurisée verrouillée — choix si pas d\'enfants/animaux</td><td style="text-align:center">20 €</td><td style="text-align:center">Pestclic</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Piège vivant Live Catch</strong> si choix sans tuer</td><td style="text-align:center">15 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Laine d\'acier inox</strong> (les rongeurs ne la rongent pas) — 1 rouleau</td><td style="text-align:center">8 €</td><td style="text-align:center">Castorama</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Silicone Sikaflex 11FC + pistolet</td><td style="text-align:center">14 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL kit appartement</strong></td><td style="text-align:center"><strong>50-60 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturable NMH</strong> : 2 h × 40 € + 60 € matériaux = <strong>140 € HT</strong>.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 4. Procédure',
                    'contenu' => '<ol>'
                        . '<li><strong>Repérage</strong> de tous les trous d\'entrée : tour des canalisations sous évier, derrière lave-vaisselle, plinthes, passages de fils électriques.</li>'
                        . '<li><strong>Bouchage hermétique</strong> avec laine d\'acier inox enfoncée + silicone sur l\'extérieur. La laine empêche le rongeage (ils détestent ça aux dents), le silicone scelle.</li>'
                        . '<li><strong>Pose des tapettes</strong> sur les trajets repérés (toujours <strong>perpendiculaires au mur</strong>, pas en travers — ils longent les murs).</li>'
                        . '<li><strong>Appât :</strong> beurre de cacahuète (de loin le plus efficace), chocolat noir, ou nourriture humide pour chat. <strong>Le fromage est un mythe.</strong></li>'
                        . '<li><strong>Vérification quotidienne</strong> pendant 7 jours.</li>'
                        . '<li>Si raticide chimique : <strong>station verrouillée obligatoire</strong> (jamais en vrac, danger enfants/animaux/oiseaux). Vérifier consommation à J+3 et J+7.</li>'
                        . '<li>Nettoyage des crottes/urines avec gants + eau de Javel diluée (1 verre par litre).</li>'
                        . '</ol>',
                ],
                'securite' => [
                    'titre' => '🦺 5. Sécurité (sérieuse pour les zoonoses)',
                    'contenu' => '<ul>'
                        . '<li><strong>NE JAMAIS toucher</strong> un rongeur, ses crottes ou son urine à mains nues. <strong>Leptospirose</strong> et <strong>hantavirus</strong> sont sérieux (transmissibles à l\'homme, parfois mortels).</li>'
                        . '<li>Gants étanches + masque pour le nettoyage. Bien aérer la pièce 1 h avant d\'aspirer (l\'aérosolisation des particules est le mode de contamination).</li>'
                        . '<li>Sac fermé pour les cadavres → poubelle extérieure, jamais commune.</li>'
                        . '<li>Bien désinfecter à l\'eau de Javel diluée toutes les surfaces.</li>'
                        . '<li>Se laver les mains et bras au savon antiseptique après chaque intervention.</li>'
                        . '<li>Raticide anticoagulant : conserver en lieu inaccessible. En cas d\'ingestion accidentelle (enfant, animal), antidote = vitamine K1 (urgences).</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'chauffage' => [
            'icone' => '🥶',
            'titre' => 'Isolation rapide & calfeutrage',
            'sous'  => 'Survitrage film + réflecteur radiateur + joints — ROI 1 hiver',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 1. Outils (10 min de prep)',
                    'contenu' => '<ul>'
                        . '<li>Sèche-cheveux (pour film survitrage) — déjà du locataire</li>'
                        . '<li>Cutter pro + règle métallique 1 m</li>'
                        . '<li>Mètre laser ou ruban</li>'
                        . '<li>Thermomètre intérieur digital (5 €)</li>'
                        . '<li>Chiffon + alcool ménager pour nettoyer les cadres avant pose</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 2. Matériaux pro pour un T3',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Film survitrage Tesa Insulate</strong> (kit 2 fenêtres) — rétractable au sèche-cheveux, gain 5-7 °C</td><td style="text-align:center">18 €</td><td style="text-align:center">Castorama</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Joints adhésifs mousse EPDM</strong> (Tesa Moll) 10 m × 2 — pour fenêtres et portes</td><td style="text-align:center">12 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Boudin brosse de porte</strong> à visser (pas l\'autocollant qui tient 2 mois)</td><td style="text-align:center">15 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Réflecteur de radiateur Aktion / Thermo</strong> (5 m² aluminisé alvéolé) — renvoie 90 % de la chaleur perdue dans le mur derrière</td><td style="text-align:center">15 €</td><td style="text-align:center">Amazon / Brico Privé</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Adhésif double face pour réflecteur</td><td style="text-align:center">4 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Joint silicone Sikaflex + pistolet (pour combler grosses fentes)</td><td style="text-align:center">10 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL T3 complet</strong></td><td style="text-align:center"><strong>75 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturable NMH</strong> : 3 h × 40 € + 80 € matériaux = <strong>200 € HT</strong>. Le ROI pour le locataire (économies chauffage) couvre la facture en 1 hiver.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 3. Procédure (3 h pour un T3)',
                    'contenu' => '<p><strong>1) Diagnostic des fuites d\'air (15 min)</strong></p>'
                        . '<ul>'
                        . '<li>Bougie ou flamme briquet à 5 cm du cadre de fenêtre/porte. Si la flamme bouge, c\'est là.</li>'
                        . '<li>Noter sur un schéma : cuisine = 3 points, séjour = 2 points, etc.</li>'
                        . '</ul>'
                        . '<p><strong>2) Calfeutrage fenêtres (1 h)</strong></p>'
                        . '<ol>'
                        . '<li>Nettoyer le cadre à l\'alcool, sécher.</li>'
                        . '<li><strong>Joints mousse EPDM</strong> sur la partie mobile (battant) — pas sur le dormant. Couper à la longueur, coller en une fois.</li>'
                        . '<li>Pour les grosses fentes (vieux cadre déformé) : Sikaflex à appliquer en cordon mince, lissé doigt mouillé.</li>'
                        . '<li><strong>Pose film survitrage Tesa</strong> :<ul>'
                        . '<li>Scotch double-face autour du cadre</li>'
                        . '<li>Dérouler le film, coller en partant du haut</li>'
                        . '<li>Couper l\'excédent au cutter</li>'
                        . '<li><strong>Chauffer au sèche-cheveux à 1 cm de distance</strong> en mouvements circulaires : le film se tend et devient transparent</li>'
                        . '<li>Résultat : effet de double-vitrage à 18 € — invisible et démontable en fin d\'hiver</li>'
                        . '</ul></li>'
                        . '</ol>'
                        . '<p><strong>3) Réflecteurs derrière radiateurs (30 min)</strong></p>'
                        . '<ol>'
                        . '<li>Mesurer la surface entre le mur et le radiateur.</li>'
                        . '<li>Couper le réflecteur Aktion aux dimensions.</li>'
                        . '<li>Coller au mur derrière le radiateur avec adhésif double face, <strong>côté brillant vers le radiateur</strong>.</li>'
                        . '<li>Gain mesuré : 7-15 % de chaleur supplémentaire dans la pièce (la chaleur n\'est plus absorbée par le mur).</li>'
                        . '</ol>'
                        . '<p><strong>4) Calfeutrage portes (30 min)</strong></p>'
                        . '<ol>'
                        . '<li>Joints EPDM autour du dormant de la porte d\'entrée (sur les 3 côtés intérieurs).</li>'
                        . '<li><strong>Boudin brosse à visser</strong> sous la porte (côté palier). 4 vis Spax 25 mm.</li>'
                        . '<li>Si l\'écart sous porte est gros : double boudin (un palier + un intérieur).</li>'
                        . '</ol>'
                        . '<p><strong>5) Conseils au locataire (5 min)</strong></p>'
                        . '<ul>'
                        . '<li>Volets fermés la nuit (gain 4 °C), ouverts le jour côté soleil.</li>'
                        . '<li>Rideaux thermiques épais derrière les fenêtres.</li>'
                        . '<li>Chauffer à 18-19 °C en continu (mieux que coups de chauffe).</li>'
                        . '<li>Aérer 5 min matin et soir (jamais plus, sinon perte chauffage).</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'fuites' => [
            'icone' => '💧',
            'titre' => 'Fuites & dégâts des eaux',
            'sous'  => 'Réparation flexible, joint, écoulement bouché',
            'sections' => [
                'urgence' => [
                    'titre' => '🚨 1. Urgence — stopper la fuite',
                    'contenu' => '<ol>'
                        . '<li><strong>Couper l\'eau au robinet général</strong> (sous évier ou compteur d\'appartement).</li>'
                        . '<li><strong>Couper l\'électricité</strong> si la fuite atteint des prises ou luminaires.</li>'
                        . '<li>Disposer bassines, serpillères, contenants.</li>'
                        . '<li><strong>Prévenir le voisin du dessous</strong> immédiatement (responsabilité civile).</li>'
                        . '<li>Photos datées AVANT nettoyage (preuve assurance).</li>'
                        . '</ol>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils',
                    'contenu' => '<ul>'
                        . '<li>Clé à molette 30 cm — 12 € Brico Dépôt</li>'
                        . '<li>Pince multiprise 250 mm — 10 €</li>'
                        . '<li>Clés plates 6 → 22 mm — kit 15 €</li>'
                        . '<li>Furet déboucheur 5 m — 15 €</li>'
                        . '<li>Ventouse pro à manche — 10 €</li>'
                        . '<li>Lampe frontale</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 3. Matériaux courants',
                    'contenu' => '<ul>'
                        . '<li><strong>Ruban PTFE (téflon)</strong> 12 m — 2 € Brico Dépôt</li>'
                        . '<li><strong>Joints en caoutchouc</strong> assortiment 100 pièces — 6 €</li>'
                        . '<li><strong>Flexibles inox 13×20</strong> mâle-femelle 30 cm (le standard évier) — 7 € pièce, en avoir 2 en stock</li>'
                        . '<li><strong>Mastic colle réparation tuyaux</strong> Pattex Power Tape — 14 €. Tient sous 5 bars, prise en 5 min.</li>'
                        . '<li><strong>Joints à lèvre WC</strong> assortis — 8 €</li>'
                        . '<li><strong>Silicone sanitaire Sikaflex SLE</strong> spécial salles d\'eau — 6 €</li>'
                        . '<li><strong>Déboucheur Destop Pro Gel</strong> (acide) ou alternative écolo bicarbonate + vinaigre + eau bouillante — 5 €</li>'
                        . '</ul>',
                ],
                'procedure' => [
                    'titre' => '👷 4. Cas types (30 min à 2 h)',
                    'contenu' => '<p><strong>CAS 1 — Flexible robinet qui fuit (30 min)</strong></p>'
                        . '<ol>'
                        . '<li>Couper l\'arrivée d\'eau sous l\'évier.</li>'
                        . '<li>Démonter le flexible avec clé à molette (côté robinet ET côté tuyau, normalement écrou de 19).</li>'
                        . '<li>Remplacer par flexible neuf (7 €).</li>'
                        . '<li>3 tours de téflon sur le pas de vis, sens horaire.</li>'
                        . '<li>Serrer modérément (le flexible a son joint intégré).</li>'
                        . '<li>Rouvrir l\'eau doucement, vérifier l\'étanchéité.</li>'
                        . '</ol>'
                        . '<p><strong>CAS 2 — Robinet qui goutte (1 h)</strong></p>'
                        . '<ol>'
                        . '<li>Couper l\'arrivée. Vidanger.</li>'
                        . '<li>Démonter le robinet (clé plate ou Allen selon modèle).</li>'
                        . '<li>Remplacer la cartouche céramique (8-15 € selon marque — Hansgrohe, Grohe).</li>'
                        . '<li>OU remplacer les joints toriques (1 € pièce).</li>'
                        . '<li>Remonter, tester.</li>'
                        . '</ol>'
                        . '<p><strong>CAS 3 — Évier bouché (15 min)</strong></p>'
                        . '<ol>'
                        . '<li>Ventouse pendant 1 min, eau dans le bac pour faire joint.</li>'
                        . '<li>Si rien : verser <strong>1/2 verre bicarbonate de soude + 1/2 verre vinaigre blanc</strong>. Mousser. Laisser 10 min.</li>'
                        . '<li>Rincer à l\'eau bouillante (2 L).</li>'
                        . '<li>Si bouchon profond : démonter le siphon (clé à molette), nettoyer manuellement.</li>'
                        . '<li>Si rien : <strong>furet</strong> 5 m, tourner pendant l\'enfoncement, ramener le bouchon.</li>'
                        . '<li>Destop Pro Gel en dernier recours (acide → ne pas mélanger avec d\'autres produits).</li>'
                        . '</ol>'
                        . '<p><strong>CAS 4 — Tuyau qui suinte mais ne peut pas être coupé (urgence locataire)</strong></p>'
                        . '<ol>'
                        . '<li>Nettoyer le tuyau autour de la fuite (alcool).</li>'
                        . '<li>Enrouler <strong>Pattex Power Tape</strong> serré, en débordant 5 cm de chaque côté.</li>'
                        . '<li>Pression à la main 1 min pendant la prise.</li>'
                        . '<li>Solution temporaire — tient quelques semaines en attendant intervention pro de plomberie.</li>'
                        . '</ol>',
                ],
            ],
        ],

        /* =============================================================== */
        'eau_chaude' => [
            'icone' => '🚿',
            'titre' => 'Coupures d\'eau chaude — diagnostic & dossier',
            'sous'  => 'Pas de DIY, mais accompagnement du locataire',
            'sections' => [
                'diagnostic' => [
                    'titre' => '🔬 1. Identifier la cause (5 min)',
                    'contenu' => '<ul>'
                        . '<li><strong>Chauffe-eau électrique individuel</strong> (cumulus) : vérifier le disjoncteur dédié, le thermostat. Si défaut → c\'est au bailleur dans les HLM, mais réparation possible par Fabrice si simple remplacement de résistance/thermostat (facturable).</li>'
                        . '<li><strong>Chaudière collective</strong> (cas le plus fréquent au Clos Toreau) : pas d\'intervention possible côté locataire/Fabrice. Bailleur uniquement.</li>'
                        . '<li><strong>Réducteur de pression ou groupe de sécurité</strong> bloqué : remplacement possible Fabrice (35 € la pièce, 1 h de pose).</li>'
                        . '</ul>',
                ],
                'aide' => [
                    'titre' => '🛟 2. Accompagnement du locataire',
                    'contenu' => '<p>Le rôle de la brigade ici n\'est pas de réparer (compétence bailleur) mais de :</p>'
                        . '<ul>'
                        . '<li><strong>Journal de coupures</strong> : aider à structurer un cahier ou notes téléphone (date début, date fin, durée, action faite, témoin).</li>'
                        . '<li><strong>Photos des justificatifs</strong> : bouilloire, douche au gant, achat de chauffage d\'appoint — preuves de préjudice.</li>'
                        . '<li><strong>Génération de la LRAR</strong> dans l\'app (modèle de lettre déjà prêt).</li>'
                        . '<li><strong>Centralisation au GA</strong> pour action collective (la force du nombre face à NMH).</li>'
                        . '</ul>',
                ],
                'cumulus' => [
                    'titre' => '🔧 3. Si chauffe-eau individuel (intervention possible)',
                    'contenu' => '<p>Pour les rares appartements avec cumulus individuel défaillant :</p>'
                        . '<ul>'
                        . '<li><strong>Thermostat à canne</strong> (la panne la plus fréquente) : 25 € chez Spareka.fr, 30 min de pose.</li>'
                        . '<li><strong>Résistance stéatite</strong> (1200 W ou 2400 W selon cumulus) : 35-50 €. 1 h de pose.</li>'
                        . '<li><strong>Groupe de sécurité</strong> qui goutte en permanence : 35 €, 30 min.</li>'
                        . '<li><strong>Détartrage cumulus</strong> : vidange + acide chlorhydrique dilué — 1 h.</li>'
                        . '</ul>'
                        . '<p><strong>Sources les moins chères pour pièces détachées :</strong></p>'
                        . '<ul>'
                        . '<li><strong>Spareka.fr</strong> (le meilleur site pour pièces blanc + électroménager)</li>'
                        . '<li><strong>Pieces-online.com</strong></li>'
                        . '<li><strong>Amazon</strong> pour résistances génériques (-30 %)</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'electrique' => [
            'icone' => '⚡',
            'titre' => 'Petits travaux électriques',
            'sous'  => 'Interrupteur, prise, luminaire — sécurité d\'abord',
            'sections' => [
                'securite' => [
                    'titre' => '🚨 1. Sécurité avant tout',
                    'contenu' => '<ul>'
                        . '<li><strong>Couper le disjoncteur</strong> du circuit concerné AVANT toute intervention.</li>'
                        . '<li><strong>Vérifier l\'absence de tension</strong> avec un VAT (vérificateur d\'absence de tension) à 8 € chez Brico Dépôt.</li>'
                        . '<li>JAMAIS travailler sur tableau électrique général ou installation principale — c\'est un domaine réservé aux électriciens habilités (norme NF C 15-100).</li>'
                        . '<li>JAMAIS de bricolage si VMC ou éclairage de sécurité — domaine bailleur strict.</li>'
                        . '<li>Schéma : couper, vérifier, noter les fils (photo), démonter, remplacer à l\'identique, rebrancher, tester avant remise sous tension générale.</li>'
                        . '</ul>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils essentiels',
                    'contenu' => '<ul>'
                        . '<li><strong>VAT</strong> (vérificateur tension) — 8 €</li>'
                        . '<li>Pince à dénuder automatique Knipex — 25 €</li>'
                        . '<li>Tournevis isolés 1000 V plat + cruci x 3 tailles — 15 €</li>'
                        . '<li>Pince universelle isolée 1000 V — 18 €</li>'
                        . '<li>Multimètre simple — 15 €</li>'
                        . '<li>Gants isolants classe 0 (1000 V) — 25 €</li>'
                        . '</ul>',
                ],
                'cas' => [
                    'titre' => '🔧 3. Cas types',
                    'contenu' => '<p><strong>Cache-prise cassé</strong> (3 €, 5 min)</p>'
                        . '<ol>'
                        . '<li>Couper le disjoncteur</li>'
                        . '<li>Dévisser la vis centrale, retirer le cache</li>'
                        . '<li>Remplacer (modèle Legrand Mosaic ou Plexo, standards)</li>'
                        . '<li>Revisser</li>'
                        . '</ol>'
                        . '<p><strong>Interrupteur simple ou va-et-vient</strong> (8-12 €, 15 min)</p>'
                        . '<ol>'
                        . '<li>Couper disjoncteur + VAT</li>'
                        . '<li>Démonter l\'ancien : <strong>photo des fils en place</strong> avant débranchement</li>'
                        . '<li>Brancher le nouveau exactement pareil (souvent 2 fils pour simple, 3 pour va-et-vient)</li>'
                        . '<li>Remettre en place, revisser</li>'
                        . '<li>Remettre sous tension, tester</li>'
                        . '</ol>'
                        . '<p><strong>Prise simple ou double</strong> (12-20 €, 20 min)</p>'
                        . '<ol>'
                        . '<li>Idem couper + VAT</li>'
                        . '<li>Photo des fils : Phase (souvent rouge ou marron), Neutre (bleu), Terre (vert/jaune)</li>'
                        . '<li>Brancher à l\'identique : Phase + Neutre dans les bornes plates, Terre dans la borne ronde du milieu</li>'
                        . '<li>Visser, remettre sous tension, tester avec lampe + multimètre</li>'
                        . '</ol>'
                        . '<p><strong>Luminaire plafond</strong> (variable, 30 min)</p>'
                        . '<ol>'
                        . '<li>Couper le disjoncteur de la pièce</li>'
                        . '<li>Démonter l\'ancien luminaire en suivant les vis</li>'
                        . '<li>Noter le branchement (typiquement domino avec Phase, Neutre, et parfois Terre)</li>'
                        . '<li>Si support DCL (boîte d\'attente moderne) : juste clipser le nouveau luminaire</li>'
                        . '<li>Si vieux branchement direct : raccorder via dominos ou Wago, refermer dans la boîte</li>'
                        . '</ol>',
                ],
                'bailleur' => [
                    'titre' => '⚠ Ce qui est AU BAILLEUR (à ne PAS toucher en intervention privée)',
                    'contenu' => '<ul>'
                        . '<li><strong>Tableau électrique principal</strong> (disjoncteurs, différentiel)</li>'
                        . '<li><strong>Mise à la terre</strong> de l\'installation</li>'
                        . '<li><strong>Câblage des murs</strong> (mise sous gaine, remplacement de circuits)</li>'
                        . '<li><strong>Mise aux normes NF C 15-100</strong></li>'
                        . '<li>Tout ce qui touche aux circuits principaux ou aux parties communes</li>'
                        . '</ul>'
                        . '<p>Pour ces interventions : recommander LRAR au bailleur (lettre dans l\'app) + saisir le SCHS si danger.</p>',
                ],
            ],
        ],

        /* =============================================================== */
        'porte' => [
            'icone' => '🚪',
            'titre' => 'Renforcement porte d\'entrée',
            'sous'  => 'Verrou applique + entrebâilleur — sécurité réelle, prix mini',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Outils',
                    'contenu' => '<ul>'
                        . '<li>Visseuse Bosch GSR — déjà</li>'
                        . '<li>Perceuse à colonne portative ou perceuse + niveau à bulle</li>'
                        . '<li>Mèches métal 3, 5, 8, 10 mm</li>'
                        . '<li>Forêts à bois 12 mm (cylindre)</li>'
                        . '<li>Scie cloche 22 mm (cylindre serrure)</li>'
                        . '<li>Lime queue de rat (ajustement)</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 Matériaux et choix',
                    'contenu' => '<ul>'
                        . '<li><strong>Verrou en applique ISEO 803 Picard</strong> A2P*** (le standard pro, pas un Lidl) — 45 € Amazon Pro</li>'
                        . '<li><strong>Verrou à code Vachette Premio</strong> (si on veut éviter les clés) — 60 €</li>'
                        . '<li><strong>Entrebâilleur Vachette Modèle 7</strong> (acier) — 12 €</li>'
                        . '<li><strong>Œilleton grand angle Abus</strong> (200° vrai) — 12 €</li>'
                        . '<li><strong>Cornière anti-effraction Picard</strong> (protège la serrure du pied-de-biche) — 22 €</li>'
                        . '<li>Visserie inox antivol Torx — 8 €</li>'
                        . '</ul>'
                        . '<p><strong>💰 Facturable NMH ou locataire</strong> : 1h30 × 40 € + 80 € matériaux = <strong>140 € HT</strong>. Suivant cas locatif/bailleur (si défaut sécurité c\'est bailleur, sinon c\'est confort locataire).</p>',
                ],
                'procedure' => [
                    'titre' => '👷 Procédure (1h30)',
                    'contenu' => '<p><strong>Verrou applique :</strong></p>'
                        . '<ol>'
                        . '<li>Mesurer la hauteur d\'installation (généralement 1m20 du sol)</li>'
                        . '<li>Présenter le boîtier intérieur, marquer les trous de fixation</li>'
                        . '<li>Marquer le trou de cylindre (visible depuis l\'extérieur)</li>'
                        . '<li>Percer le passage de cylindre (scie cloche 22 mm)</li>'
                        . '<li>Percer les fixations boîtier (mèches selon vis fournies)</li>'
                        . '<li>Présenter, fixer boîtier intérieur avec vis Torx antivol</li>'
                        . '<li>Insérer cylindre Picard depuis l\'extérieur</li>'
                        . '<li>Fixer rosace extérieure (vis depuis l\'intérieur — c\'est l\'astuce sécurité)</li>'
                        . '<li>Fixer la gâche sur le dormant avec cornière anti-effraction</li>'
                        . '<li>Tester ouverture/fermeture, régler si frottement (lime à queue de rat)</li>'
                        . '</ol>'
                        . '<p><strong>Entrebâilleur :</strong></p>'
                        . '<ol>'
                        . '<li>Placer à 1m50 du sol (hors de portée enfant)</li>'
                        . '<li>4 vis Torx 25 mm inox</li>'
                        . '<li>Système doit permettre d\'ouvrir 6-8 cm sans laisser entrer</li>'
                        . '</ol>',
                ],
            ],
        ],

        /* =============================================================== */
        /*  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
        /*  PETITS TUTOS — gestes simples du quotidien                       */
        /*  Pas du chantier pro : du dépannage rapide et accessible          */
        /*  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
        /* =============================================================== */

        'petit_platre' => [
            'icone' => '🪣',
            'titre' => 'Faire son plâtre soi-même au seau',
            'sous'  => 'Le bon mélange à la spatule, prêt en 2 minutes',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Un sac de plâtre à modeler (Sac 5 kg : 6 € Brico Dépôt) ou enduit de rebouchage poudre</li>'
                        . '<li>Un seau souple ou un vieux pot de yaourt pour les petites quantités</li>'
                        . '<li>De l\'eau du robinet froide</li>'
                        . '<li>Une spatule ou un couteau à enduire 10 cm</li>'
                        . '<li>Un vieux chiffon</li>'
                        . '</ul>',
                ],
                'melange' => [
                    'titre' => '🥣 Le mélange (2 min chrono)',
                    'contenu' => '<ol>'
                        . '<li><strong>Verser l\'eau EN PREMIER</strong> dans le seau. Compter 1 dose d\'eau pour 2 doses de plâtre. JAMAIS l\'inverse, sinon grumeaux garantis.</li>'
                        . '<li>Saupoudrer le plâtre par-dessus l\'eau, sans mélanger. Laisser absorber 30 secondes — le plâtre boit l\'eau tout seul.</li>'
                        . '<li>Mélanger doucement à la spatule, en partant du fond, jusqu\'à obtenir une <strong>texture de yaourt épais</strong>.</li>'
                        . '<li>C\'est prêt. Tu as 5 à 10 minutes pour l\'utiliser avant que ça durcisse.</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces de pro',
                    'contenu' => '<ul>'
                        . '<li><strong>Trop liquide ?</strong> Rajouter un peu de plâtre, jamais d\'eau.</li>'
                        . '<li><strong>Trop épais ?</strong> Quelques gouttes d\'eau, mélanger vite.</li>'
                        . '<li><strong>Ralentir la prise</strong> (gagner 15 min de travail) : 1 cuillère à café de vinaigre blanc dans l\'eau.</li>'
                        . '<li><strong>Accélérer la prise</strong> : une pincée de sel.</li>'
                        . '<li><strong>JAMAIS verser le surplus dans l\'évier</strong> — ça bouche tout pour de bon. Laisser sécher dans le seau, casser au marteau, poubelle.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'reboucher_trou' => [
            'icone' => '🩹',
            'titre' => 'Reboucher un trou de cheville',
            'sous'  => 'Trou de vis, de clou, de cheville — invisible en 1 h',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Tube d\'enduit de rebouchage en pâte (Toupret Rebouch\'tout 330 g : 5 € Brico Dépôt)</li>'
                        . '<li>Une spatule fine ou un couteau de cuisine</li>'
                        . '<li>Papier de verre grain 120</li>'
                        . '<li>Un chiffon humide</li>'
                        . '<li>Un peu de peinture de la même couleur que le mur</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La méthode (10 min + séchage)',
                    'contenu' => '<ol>'
                        . '<li><strong>Nettoyer le trou</strong> : enlever poussière et bouts qui dépassent. Souffler dedans, passer le doigt humide.</li>'
                        . '<li><strong>Garnir avec l\'enduit</strong> : noisette sur la spatule, presser dans le trou pour qu\'il rentre jusqu\'au fond. Pas de bulle d\'air.</li>'
                        . '<li><strong>Lisser à plat</strong> avec la spatule, en laissant 1 mm d\'excès qui dépasse (l\'enduit rétrécit un peu en séchant).</li>'
                        . '<li><strong>Laisser sécher 1 h</strong> (24 h pour un gros trou).</li>'
                        . '<li><strong>Poncer doucement</strong> au papier de verre 120, à plat sur le mur.</li>'
                        . '<li><strong>Repeindre par-dessus</strong> avec un petit pinceau, juste le bouchon.</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces',
                    'contenu' => '<ul>'
                        . '<li><strong>Trou de plus d\'1 cm</strong> : remplir d\'abord avec une boulette de papier journal froissé, enduit par-dessus.</li>'
                        . '<li><strong>Retouche invisible</strong> : prendre un échantillon de la peinture chez Castorama, ils la reproduisent (2 € le petit pot).</li>'
                        . '<li>Sur peinture brillante : la retouche se verra toujours un peu — mieux vaut repeindre tout le pan de mur.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'fissure_mur' => [
            'icone' => '⚡',
            'titre' => 'Reboucher une fissure fine',
            'sous'  => 'Microfissure de plâtre ou peinture — 20 min',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Enduit de rebouchage souple (Toupret Fissures rebelles 250 ml : 8 €)</li>'
                        . '<li>Couteau d\'enduire 6 cm</li>'
                        . '<li>Pointe d\'un cutter ou tournevis fin</li>'
                        . '<li>Papier de verre grain 180</li>'
                        . '<li>Aspirateur</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 Méthode (20 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Ouvrir la fissure en V</strong> avec la pointe du cutter ou tournevis : faire un sillon de 2-3 mm de large. C\'est obligatoire pour que l\'enduit accroche.</li>'
                        . '<li><strong>Aspirer</strong> la poussière du sillon.</li>'
                        . '<li><strong>Mouiller</strong> la fissure au doigt (l\'eau aide l\'enduit à pénétrer).</li>'
                        . '<li><strong>Appliquer l\'enduit</strong> en pressant avec le couteau, perpendiculairement à la fissure, pour qu\'il rentre bien dedans.</li>'
                        . '<li><strong>Lisser</strong> dans le sens de la fissure, en débordant de 1 cm de chaque côté.</li>'
                        . '<li><strong>Sécher 12 h</strong>, poncer léger, repeindre.</li>'
                        . '</ol>',
                ],
                'attention' => [
                    'titre' => '⚠ Quand t\'arrêter et appeler',
                    'contenu' => '<ul>'
                        . '<li><strong>Fissure de plus d\'1 mm de large</strong> qui traverse le mur de part en part : c\'est structurel, pas cosmétique. Photos + LRAR au bailleur.</li>'
                        . '<li><strong>Fissure qui s\'agrandit visiblement</strong> en quelques semaines : idem, structurel.</li>'
                        . '<li><strong>Fissure horizontale au-dessus des fenêtres / portes</strong> : tassement, prévenir le bailleur.</li>'
                        . '<li>Pour les microfissures normales de plâtre qui sèche, l\'enduit souple suffit largement.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'decoller_papier' => [
            'icone' => '🧻',
            'titre' => 'Décoller du vieux papier peint',
            'sous'  => 'Sans décolleuse vapeur, juste eau chaude + patience',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Pulvérisateur 1 L (5 € Brico Dépôt)</li>'
                        . '<li>Liquide vaisselle ou décolleur Quelyd (8 €)</li>'
                        . '<li>Eau chaude (pas bouillante)</li>'
                        . '<li>Spatule large 10 cm ou couteau d\'enduire</li>'
                        . '<li>Bâche en plastique pour le sol</li>'
                        . '<li>Sac poubelle solide</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La méthode (1 h par pan de mur)',
                    'contenu' => '<ol>'
                        . '<li><strong>Protéger le sol</strong> avec la bâche, décoller plinthes et prises (couper le courant avant).</li>'
                        . '<li><strong>Mélange magique</strong> dans le pulvé : eau chaude + 2 cuillères à soupe de liquide vaisselle (ou 1 bouchon de Quelyd). Bien secouer.</li>'
                        . '<li><strong>Si papier vinyle</strong> (lavable) : gratter d\'abord la surface au griffoir ou à la spatule pour percer le film plastique. Sinon l\'eau ne pénètre pas.</li>'
                        . '<li><strong>Pulvériser généreusement</strong> par zone d\'1 m². Laisser tremper 5-10 min.</li>'
                        . '<li><strong>Décoller en partant d\'un coin</strong> avec la spatule, à plat sur le mur (pas à 90°, sinon tu marques le plâtre).</li>'
                        . '<li><strong>Repulvériser</strong> les résidus de colle, laisser ramollir, racler.</li>'
                        . '<li>Le mur doit rester un peu humide à la fin — laisser sécher 24 h avant de peindre ou re-tapisser.</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces',
                    'contenu' => '<ul>'
                        . '<li><strong>Papier qui résiste</strong> : passer 2 fois, attendre plus longtemps. Pas la force, sinon tu arraches le plâtre avec.</li>'
                        . '<li><strong>Plusieurs couches</strong> de papier (vieux logements) : décoller couche par couche, c\'est plus rapide.</li>'
                        . '<li><strong>Reste de colle invisible</strong> : passer l\'éponge humide. Si tu peins direct sur les résidus, ça crée des cloques.</li>'
                        . '<li><strong>Trous et arrachages</strong> du plâtre : enduit de rebouchage avant de peindre.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'petite_plaque_platre' => [
            'icone' => '🧱',
            'titre' => 'Poser un petit morceau de placo',
            'sous'  => 'Reboucher un trou de 10-30 cm dans le mur',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Petite chute de plaque BA13 (souvent récupérable gratuitement chez Brico Dépôt à la découpe)</li>'
                        . '<li>Cutter pro + règle métallique</li>'
                        . '<li>Scie égoïne pour plaque de plâtre (6 € Brico Dépôt)</li>'
                        . '<li>Tasseau de bois 30×40 mm (1 € le mètre)</li>'
                        . '<li>Vis Spax TT 35 mm</li>'
                        . '<li>Visseuse</li>'
                        . '<li>Bande à joint papier + enduit (déjà cités plus haut)</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La méthode (1 h + séchage)',
                    'contenu' => '<ol>'
                        . '<li><strong>Régulariser le trou</strong> au cutter pour faire un rectangle aux bords nets. Plus carré = plus facile.</li>'
                        . '<li><strong>Glisser un tasseau</strong> derrière le mur, en travers, dépassant de 5 cm de chaque côté du trou. Le visser au mur existant par 2 vis traversantes (à travers la plaque de plâtre du mur).</li>'
                        . '<li><strong>Découper</strong> la chute de placo au rectangle exact (3 mm de moins que le trou pour faciliter la pose).</li>'
                        . '<li><strong>Visser</strong> le morceau sur le tasseau (3-4 vis Spax 35 mm).</li>'
                        . '<li><strong>Coller la bande à joint</strong> papier humide sur les 4 raccords, noyée dans l\'enduit.</li>'
                        . '<li><strong>Lisser à l\'enduit</strong> en débordant de 5 cm autour. Sécher 12 h.</li>'
                        . '<li><strong>2e passe d\'enduit</strong>, plus large encore (10 cm). Sécher.</li>'
                        . '<li><strong>Poncer 120 puis 240</strong>, repeindre.</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces',
                    'contenu' => '<ul>'
                        . '<li><strong>Pas de tasseau possible</strong> (mur trop fin) ? Découper le morceau de placo plus grand que le trou, le passer en biais derrière, et le tirer contre le mur avec un fil de fer. Maintenir le temps que ça colle au MAP.</li>'
                        . '<li><strong>Trou très petit</strong> (< 5 cm) : pas la peine de plaque, enduit + bande à joint suffit.</li>'
                        . '<li><strong>Pour un raccord invisible</strong> : la 2e passe d\'enduit doit déborder large et être bien lissée à la spatule large.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'joint_silicone' => [
            'icone' => '🛁',
            'titre' => 'Refaire un joint silicone',
            'sous'  => 'Évier, baignoire, douche — propre et net en 45 min',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Cartouche silicone sanitaire Sikaflex 11FC ou Rubson SBR (5 € Brico Dépôt)</li>'
                        . '<li>Pistolet à cartouche (8 € si pas déjà)</li>'
                        . '<li>Cutter ou outil à enlever joint</li>'
                        . '<li>Vinaigre blanc + chiffon</li>'
                        . '<li>Ruban de masquage (Tesa Précision : 6 €)</li>'
                        . '<li>Petite éponge ou doigt mouillé d\'eau savonneuse</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La méthode (45 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Enlever l\'ancien joint</strong> au cutter : couper le long du joint des deux côtés, retirer le ruban de silicone. Bien gratter les résidus.</li>'
                        . '<li><strong>Dégraisser</strong> au vinaigre blanc, sécher avec chiffon. Surface doit être PARFAITEMENT sèche et propre — c\'est 80 % du résultat.</li>'
                        . '<li><strong>Coller du ruban de masquage</strong> de chaque côté du joint, à 5 mm de distance. C\'est le secret pour un joint net.</li>'
                        . '<li><strong>Couper l\'embout</strong> de la cartouche en biseau, ouverture de 5 mm. Pas plus.</li>'
                        . '<li><strong>Tirer le cordon</strong> en une seule fois, à vitesse régulière, en poussant (pas en tirant) le pistolet.</li>'
                        . '<li><strong>Lisser au doigt</strong> mouillé d\'eau savonneuse (eau + 1 goutte de liquide vaisselle dans un bol), en un seul passage continu.</li>'
                        . '<li><strong>Retirer le ruban de masquage IMMÉDIATEMENT</strong>, en tirant vers le haut. Si tu attends, le silicone sèche et tu arraches le joint.</li>'
                        . '<li><strong>Sécher 24 h</strong> avant utilisation (pas de douche, pas d\'eau).</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces',
                    'contenu' => '<ul>'
                        . '<li><strong>Joint qui moisit en 6 mois</strong> : c\'est parce que les sels minéraux de l\'eau s\'infiltrent. Toujours laisser sécher après la douche (squeegee ou serviette).</li>'
                        . '<li><strong>Choisir silicone SANITAIRE</strong> (anti-moisissure), pas le silicone universel — sinon noir en 3 mois.</li>'
                        . '<li><strong>Ne PAS faire</strong> couler du silicone dans l\'évier — boucher avec un papier journal le temps de poser, ça facilite tout.</li>'
                        . '<li><strong>Joint nul refait</strong> : ne pas remettre par-dessus, ça ne tient pas. Tout enlever et recommencer.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'chasse_eau_coule' => [
            'icone' => '🚽',
            'titre' => 'Chasse d\'eau qui coule',
            'sous'  => 'Diagnostic + réparation à 5 €, en 30 min',
            'sections' => [
                'diag' => [
                    'titre' => '🔍 Diagnostic — c\'est lequel des deux ?',
                    'contenu' => '<p>Soulever le couvercle du réservoir. Deux problèmes possibles :</p>'
                        . '<ul>'
                        . '<li><strong>L\'eau coule en permanence dans la cuvette</strong> (mince filet) : c\'est le <strong>clapet de fond</strong> (joint rouge ou noir) qui fuit.</li>'
                        . '<li><strong>L\'eau coule par le trop-plein</strong> dans le réservoir (tu entends un bruit continu d\'eau qui arrive) : c\'est le <strong>flotteur</strong> qui ne ferme plus le robinet.</li>'
                        . '</ul>'
                        . '<p>Test simple : mets quelques gouttes de colorant alimentaire dans le réservoir. Si la cuvette se colore sans tirer la chasse = clapet. Si l\'eau monte au-dessus du tube trop-plein = flotteur.</p>',
                ],
                'clapet' => [
                    'titre' => '🔧 Réparer le clapet (15 min, 5 €)',
                    'contenu' => '<ol>'
                        . '<li><strong>Couper l\'eau</strong> du WC (petit robinet en bas).</li>'
                        . '<li><strong>Tirer la chasse</strong> pour vider le réservoir.</li>'
                        . '<li><strong>Décrocher le clapet</strong> (gros joint en caoutchouc au fond, sur la cloche). Il s\'enlève à la main.</li>'
                        . '<li><strong>Apporter le clapet au magasin</strong> (Brico Dépôt) pour acheter le même (3-5 €). Il existe plein de tailles différentes.</li>'
                        . '<li><strong>Reposer le neuf</strong> à la place de l\'ancien.</li>'
                        . '<li>Rouvrir l\'eau, attendre que le réservoir se remplisse, tester.</li>'
                        . '</ol>',
                ],
                'flotteur' => [
                    'titre' => '🔧 Réparer le flotteur (30 min, 12 €)',
                    'contenu' => '<ol>'
                        . '<li><strong>Couper l\'eau</strong>, tirer la chasse.</li>'
                        . '<li><strong>Démonter le flotteur</strong> (tube vertical avec une boule ou un cylindre) : dévisser l\'écrou en bas du réservoir et l\'écrou de connexion à l\'arrivée d\'eau.</li>'
                        . '<li><strong>Acheter un kit mécanisme universel</strong> Wirquin Quick Fix ou Siamp (12-15 € Brico Dépôt).</li>'
                        . '<li>Le remonter selon la notice (5 min, c\'est plug & play).</li>'
                        . '<li><strong>Régler la hauteur d\'eau</strong> avec le clip sur la tige.</li>'
                        . '<li>Rouvrir, tester.</li>'
                        . '</ol>'
                        . '<p><em>Astuce : changer le flotteur ET le clapet en même temps pour 17 €, plutôt que revenir dans 6 mois.</em></p>',
                ],
            ],
        ],

        /* =============================================================== */
        'deboucher_evier' => [
            'icone' => '🚰',
            'titre' => 'Déboucher un évier ou un lavabo',
            'sous'  => '3 méthodes, du plus simple au plus efficace',
            'sections' => [
                'm1' => [
                    'titre' => '1️⃣ Méthode douce — eau chaude + soude',
                    'contenu' => '<ol>'
                        . '<li>Faire chauffer 1 L d\'eau (pas bouillante).</li>'
                        . '<li>Verser 1 verre de bicarbonate de soude dans le siphon (au fond du trou).</li>'
                        . '<li>Verser 1 verre de vinaigre blanc par-dessus. Ça mousse pendant 5 min.</li>'
                        . '<li>Verser l\'eau chaude. Attendre 15 min.</li>'
                        . '<li>Faire couler l\'eau froide à fond.</li>'
                        . '</ol>'
                        . '<p>Marche pour les bouchons gras / cheveux légers. <strong>Coût : 1 €</strong>.</p>',
                ],
                'm2' => [
                    'titre' => '2️⃣ Méthode efficace — ventouse',
                    'contenu' => '<ol>'
                        . '<li>Boucher le trop-plein de l\'évier avec un chiffon humide (sinon la ventouse ne fait pas de pression).</li>'
                        . '<li>Mettre 5 cm d\'eau dans l\'évier.</li>'
                        . '<li>Plaquer la ventouse sur l\'écoulement, pomper énergiquement 20 fois sans décoller.</li>'
                        . '<li>Décoller d\'un coup. Si ça gargouille = c\'est libéré.</li>'
                        . '<li>Sinon recommencer. 3-4 séries suffisent en général.</li>'
                        . '</ol>'
                        . '<p>Marche pour la majorité des bouchons normaux. <strong>Coût : 6 € la ventouse Brico Dépôt</strong>.</p>',
                ],
                'm3' => [
                    'titre' => '3️⃣ Méthode finale — démonter le siphon',
                    'contenu' => '<ol>'
                        . '<li><strong>Mettre une bassine</strong> sous le siphon (le tube en U sous l\'évier).</li>'
                        . '<li><strong>Dévisser à la main</strong> les écrous du siphon (généralement plastique, pas besoin de clé).</li>'
                        . '<li><strong>Vider et nettoyer</strong> le siphon dans la bassine. C\'est presque toujours là que ça bouche : amas de cheveux, gras, restes alimentaires.</li>'
                        . '<li><strong>Profiter pour passer un furet</strong> 3 m (15 € Brico Dépôt) dans le tuyau d\'évacuation côté mur pour aller plus loin.</li>'
                        . '<li><strong>Remonter</strong> en vérifiant les joints (les changer si écrasés : 1 € pièce).</li>'
                        . '</ol>'
                        . '<p>Marche TOUJOURS. <strong>Éviter les déboucheurs chimiques</strong> (Destop) — ça ronge les joints, ça pue, et ça ne marche qu\'à moitié.</p>',
                ],
            ],
        ],

        /* =============================================================== */
        'accrocher_placo' => [
            'icone' => '🖼',
            'titre' => 'Accrocher du lourd dans du placo',
            'sous'  => 'Étagère, miroir, télé — sans tout faire tomber',
            'sections' => [
                'choix' => [
                    'titre' => '🎯 Choisir la bonne cheville selon le poids',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Charge</th><th style="padding:6px">Cheville à utiliser</th><th style="padding:6px">Prix</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Jusqu\'à 5 kg (cadre)</td><td style="padding:6px;border-bottom:1px solid #eee">Cheville plastique Fischer SX</td><td style="padding:6px;border-bottom:1px solid #eee">3 €/50</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">5-20 kg (étagère)</td><td style="padding:6px;border-bottom:1px solid #eee">Cheville Molly à expansion</td><td style="padding:6px;border-bottom:1px solid #eee">5 €/20</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">20-50 kg (TV, meuble)</td><td style="padding:6px;border-bottom:1px solid #eee">Cheville à bascule</td><td style="padding:6px;border-bottom:1px solid #eee">3 € pièce</td></tr>'
                        . '<tr><td style="padding:6px"><strong>+ de 50 kg</strong></td><td style="padding:6px"><strong>Trouver un montant + tirefond bois</strong></td><td style="padding:6px">2 €</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>Règle d\'or :</strong> au-delà de 30 kg, ne JAMAIS se fier au placo seul. Trouve le montant en métal derrière.</p>',
                ],
                'montant' => [
                    'titre' => '🧭 Trouver un montant derrière le placo',
                    'contenu' => '<ul>'
                        . '<li><strong>Méthode aimant</strong> : un aimant fort (frigo) plaqué au mur. Quand il colle, t\'es sur une vis ou un rail métal du montant.</li>'
                        . '<li><strong>Méthode son</strong> : tapoter le mur avec la jointure des doigts. Son creux = vide entre montants. Son plus sourd = montant juste derrière.</li>'
                        . '<li><strong>Détecteur Bosch GMS 120</strong> : 40 € Amazon, détecte métal + bois + courant. Indispensable si on en met souvent.</li>'
                        . '<li><strong>Règle générale</strong> : les montants sont espacés de <strong>40 ou 60 cm</strong> en métal. Une fois qu\'on en trouve un, le suivant est à 40 ou 60 cm.</li>'
                        . '</ul>',
                ],
                'molly' => [
                    'titre' => '🔩 Poser une Molly (la chevillerie magique)',
                    'contenu' => '<ol>'
                        . '<li><strong>Percer le mur</strong> au diamètre indiqué sur l\'emballage (souvent 10 mm).</li>'
                        . '<li><strong>Insérer la Molly</strong> en l\'enfonçant complètement. La collerette dentée doit être à fleur du mur.</li>'
                        . '<li><strong>Visser la vis</strong> avec la pince à expansion Molly (8 €) ou simple visseuse (lent). La Molly se déploie en parapluie de l\'autre côté du placo.</li>'
                        . '<li><strong>Dévisser la vis</strong>. La Molly reste en place, déployée.</li>'
                        . '<li>Remettre la vis avec l\'objet à fixer. Tient 20 kg facile.</li>'
                        . '</ol>'
                        . '<p><strong>Erreur fréquente</strong> : visser la vis sans pince à expansion → la Molly tourne sur elle-même et ne se déploie pas. Soit la pince, soit prendre la version qui se déploie en visant fort (plus facile).</p>',
                ],
            ],
        ],

        /* =============================================================== */
        'peindre_mur' => [
            'icone' => '🎨',
            'titre' => 'Peindre un mur proprement',
            'sous'  => 'Sans trace, sans coulure, en 1 journée',
            'sections' => [
                'mat' => [
                    'titre' => '🧰 Le matériel (qualité = résultat)',
                    'contenu' => '<ul>'
                        . '<li><strong>Peinture mate ou velours</strong> Tollens, Dulux Valentine ou Ripolin (25 €/2,5 L Castorama) — éviter les premiers prix qui couvrent mal et obligent 3 couches.</li>'
                        . '<li><strong>Rouleau anti-goutte</strong> manche court 18 cm + recharge poils 12 mm (8 €)</li>'
                        . '<li><strong>Pinceau à rechampir</strong> 30 mm (4 €) — pour les coins</li>'
                        . '<li><strong>Bac à peinture</strong> + grille essoreuse (6 €)</li>'
                        . '<li><strong>Ruban de masquage</strong> Tesa Précision (6 €/rouleau)</li>'
                        . '<li><strong>Bâche plastique</strong> ou vieilles cartons pour le sol</li>'
                        . '<li>Une éponge humide + un seau d\'eau</li>'
                        . '</ul>',
                ],
                'prep' => [
                    'titre' => '🧹 Préparation (30 min, mais ESSENTIEL)',
                    'contenu' => '<ol>'
                        . '<li><strong>Dégager le mur</strong> : meubles à 1 m, prises décollées, plinthes protégées.</li>'
                        . '<li><strong>Boucher les trous</strong> à l\'enduit (voir tuto rebouchage), laisser sécher.</li>'
                        . '<li><strong>Poncer léger</strong> les zones bouchées + tout le mur si c\'est lessivé/brillant.</li>'
                        . '<li><strong>Dépoussiérer</strong> à l\'éponge humide. Mur doit être SEC avant peinture.</li>'
                        . '<li><strong>Masquage</strong> : ruban Tesa autour des fenêtres, plinthes, plafond.</li>'
                        . '<li><strong>Sous-couche</strong> obligatoire si mur neuf, très taché ou changement de couleur foncée → claire (Julien Stop Tâches 1 L : 8 €).</li>'
                        . '</ol>',
                ],
                'pose' => [
                    'titre' => '🖌 La pose (2 h pour une chambre)',
                    'contenu' => '<ol>'
                        . '<li><strong>Bien remuer la peinture</strong> 2 min avec un bâton (pas secouer la boîte).</li>'
                        . '<li><strong>Pinceau d\'abord</strong> : dégager les coins, le tour du plafond, les angles. Bandes de 5 cm.</li>'
                        . '<li><strong>Rouleau</strong> ensuite, sans attendre que les bandes au pinceau sèchent.</li>'
                        . '<li><strong>Technique W</strong> : peindre un W d\'1 m × 1 m, puis remplir SANS reprendre de peinture, en croisant horizontalement.</li>'
                        . '<li><strong>Finir TOUJOURS</strong> en passages verticaux du haut vers le bas, à rouleau sec (sans appuyer).</li>'
                        . '<li><strong>NE PAS REVENIR</strong> sur une zone qui commence à sécher (10-15 min) — sinon tu décolles ce que tu viens de faire et tu fais des traces.</li>'
                        . '<li><strong>2e couche</strong> obligatoire après 6 h de séchage, dans le sens perpendiculaire à la 1ère.</li>'
                        . '<li><strong>Décoller le ruban</strong> de masquage tant que la peinture est encore un peu humide, à 45°.</li>'
                        . '</ol>',
                ],
            ],
        ],

        /* =============================================================== */
        'porte_frotte' => [
            'icone' => '🚪',
            'titre' => 'Porte qui frotte au sol',
            'sous'  => 'Diagnostic + 3 solutions selon le cas',
            'sections' => [
                'diag' => [
                    'titre' => '🔍 D\'où ça vient ?',
                    'contenu' => '<p>Une porte qui frotte = un des trois cas :</p>'
                        . '<ul>'
                        . '<li><strong>Charnière (paumelle) tordue ou tombée</strong> : la porte est descendue de 2-3 mm. Le plus fréquent.</li>'
                        . '<li><strong>Bois qui a gonflé</strong> avec l\'humidité (porte de salle de bain, cuisine).</li>'
                        . '<li><strong>Sol surélevé</strong> : nouveau carrelage, parquet épais, tapis.</li>'
                        . '</ul>'
                        . '<p>Ouvrir la porte, regarder où elle frotte (marque visible au sol). Si c\'est <strong>côté poignée</strong> qui descend = charnière. Si c\'est <strong>toute la longueur</strong> = bois gonflé ou sol. Si c\'est <strong>côté charnière</strong> = paumelle tombée.</p>',
                ],
                'charniere' => [
                    'titre' => '🔧 Resserrer les charnières (10 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Mettre une cale sous la porte</strong> (livre, bloc bois) pour soulager le poids.</li>'
                        . '<li><strong>Resserrer toutes les vis</strong> des charnières. Si certaines tournent dans le vide = trou foiré.</li>'
                        . '<li><strong>Trou foiré</strong> : enlever la vis, enfoncer 2-3 allumettes (coupées du côté soufre !) avec un peu de colle à bois dans le trou, attendre 30 min, revisser. La vis trouve à nouveau de la matière.</li>'
                        . '<li><strong>Alternative pro</strong> : changer la vis par une plus longue ou plus grosse.</li>'
                        . '</ol>',
                ],
                'rabot' => [
                    'titre' => '🪚 Raboter la porte (30 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Déposer la porte</strong> : ouvrir, soulever les axes des charnières (avec une cale du côté poignée). Allonger la porte sur 2 tréteaux.</li>'
                        . '<li><strong>Mesurer</strong> ce qui frotte : marquer au crayon une ligne 2 mm au-dessus du bord qui touche.</li>'
                        . '<li><strong>Raboter au rabot électrique</strong> (Bosch PHO 1500 : 60 € Amazon) ou rabot manuel (15 €). Toujours dans le sens du bois.</li>'
                        . '<li><strong>Repeindre la tranche</strong> avec la même peinture (sinon le bois va re-gonfler à la prochaine humidité).</li>'
                        . '<li><strong>Remettre la porte</strong>, vérifier qu\'elle ferme bien.</li>'
                        . '</ol>'
                        . '<p>⚠ Ne pas trop raboter — l\'idéal c\'est 2-3 mm de jeu sous la porte. Plus = courant d\'air.</p>',
                ],
            ],
        ],

        /* =============================================================== */
        'plinthe' => [
            'icone' => '📏',
            'titre' => 'Reposer une plinthe',
            'sous'  => 'Décollée, cassée, ou refaite après peinture',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Ce qu\'il faut',
                    'contenu' => '<ul>'
                        . '<li>Plinthe (récup de la même ou neuve — Brico Dépôt : 4 €/2,5 m en MDF blanc)</li>'
                        . '<li>Colle néoprène en cartouche ou Pattex Fix Pro (5 €) — pas la colle blanche</li>'
                        . '<li>Pistolet à cartouche</li>'
                        . '<li>Scie à dos + boîte à onglet pour les angles (12 €)</li>'
                        . '<li>Mètre, crayon</li>'
                        . '<li>Petite pointe sans tête ou clous de finition + marteau</li>'
                        . '<li>Mastic acrylique blanc pour le raccord (3 €)</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La méthode (45 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Mesurer</strong> la longueur de mur. Marquer la plinthe.</li>'
                        . '<li><strong>Couper droit</strong> aux extrémités contre les murs perpendiculaires. <strong>Couper à 45°</strong> dans les coins (la boîte à onglet rend ça simple).</li>'
                        . '<li><strong>Présenter à blanc</strong> contre le mur, vérifier que les angles ferment bien. Ajuster au papier de verre si nécessaire.</li>'
                        . '<li><strong>Appliquer la colle</strong> en cordon ondulé au dos de la plinthe (pas trop, sinon ça déborde).</li>'
                        . '<li><strong>Plaquer fort</strong> contre le mur, en s\'aidant des plinthes adjacentes. Tenir 30 secondes.</li>'
                        . '<li><strong>Enfoncer 2-3 clous de finition</strong> en haut de la plinthe, juste pour maintenir le temps que la colle prenne (24 h).</li>'
                        . '<li><strong>Mastic acrylique</strong> sur le joint plinthe/mur en haut, pour cacher le petit espace (lisser au doigt mouillé).</li>'
                        . '<li><strong>Repeindre</strong> les pointes et le mastic si nécessaire.</li>'
                        . '</ol>',
                ],
                'astuces' => [
                    'titre' => '💡 Astuces',
                    'contenu' => '<ul>'
                        . '<li><strong>Mur pas droit</strong> (presque toujours) : mastic acrylique en haut, c\'est ce qui rattrape les écarts jusqu\'à 5 mm.</li>'
                        . '<li><strong>Plinthe cassée</strong> en un seul endroit : couper net en biais, raccorder un petit morceau avec colle + mastic. Repeindre.</li>'
                        . '<li><strong>Angle pas à 90°</strong> (mur biscornu) : couper à 22° + 23° par exemple, plutôt que 45° + 45°. Ajuster à blanc avant la colle.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'joint_porte_entree' => [
            'icone' => '🌬',
            'titre' => 'Coller des joints sur la porte d\'entrée',
            'sous'  => 'Stopper les courants d\'air en 20 min',
            'sections' => [
                'mat' => [
                    'titre' => '🧰 Le matériel (10 €)',
                    'contenu' => '<ul>'
                        . '<li>Joints adhésifs en mousse EPDM Tesa Moll 10 m (8 €) — ou silicone D (mieux mais plus cher)</li>'
                        . '<li>Boudin brosse à clouer pour le bas de porte (15 €) — pas l\'autocollant qui tient 2 mois</li>'
                        . '<li>Cutter</li>'
                        . '<li>Chiffon + alcool ménager (ou vinaigre)</li>'
                        . '<li>Mètre</li>'
                        . '</ul>',
                ],
                'etapes' => [
                    'titre' => '👷 La pose (20 min)',
                    'contenu' => '<ol>'
                        . '<li><strong>Nettoyer le dormant</strong> (cadre fixe) à l\'alcool. Sécher.</li>'
                        . '<li><strong>Choisir l\'épaisseur</strong> du joint : passer un doigt dans le jeu porte/cadre. Si tu passes facilement le doigt = joint épais 9 mm. Sinon = joint 5 mm.</li>'
                        . '<li><strong>Mesurer</strong> les 3 côtés intérieurs du dormant (pas le côté charnières — sinon la porte ferme plus).</li>'
                        . '<li><strong>Coller le joint</strong> en partant d\'un coin haut, en pressant bien. Couper net aux coins à 45°.</li>'
                        . '<li><strong>Fermer la porte</strong> et vérifier que ça écrase mais qu\'elle ferme. Si elle ferme plus = joint trop épais, recommencer avec un plus fin.</li>'
                        . '</ol>'
                        . '<p><strong>Bas de porte :</strong></p>'
                        . '<ol>'
                        . '<li>Mesurer la largeur de la porte.</li>'
                        . '<li>Couper le boudin brosse à la longueur.</li>'
                        . '<li>Le clouer (ou visser) côté extérieur, en bas de la porte. Pas côté intérieur sinon il bloque la porte au sol.</li>'
                        . '<li>La brosse doit caresser le sol sans bloquer.</li>'
                        . '</ol>',
                ],
                'test' => [
                    'titre' => '🕯 Tester le résultat',
                    'contenu' => '<ul>'
                        . '<li>Allumer une bougie ou un briquet à 5 cm du joint, sur tout le tour de la porte fermée.</li>'
                        . '<li>Si la flamme bouge encore = il reste une fuite à cet endroit. Renforcer avec un joint plus épais ou un cordon de Sikaflex.</li>'
                        . '<li>Gain attendu : 1 à 3 °C dans l\'entrée + facture chauffage -5 à -10 %.</li>'
                        . '</ul>',
                ],
            ],
        ],

        /* =============================================================== */
        'parties_communes' => [
            'icone' => '🚪',
            'titre' => 'Parties communes — diagnostic + actions',
            'sous'  => 'Bailleur uniquement, mais documenter pour LRAR',
            'sections' => [
                'role' => [
                    'titre' => '⚠ Ton rôle ici',
                    'contenu' => '<p>Les parties communes (hall, escalier, ascenseur, parking, jardins, boîtes aux lettres collectives) sont <strong>exclusivement à la charge du bailleur</strong>. Tu ne factures pas de travaux dessus.</p>'
                        . '<p>En revanche, ton rôle de brigade :</p>'
                        . '<ul>'
                        . '<li><strong>Photographier</strong> et documenter pour le dossier collectif (à charger dans les dossiers locataires de l\'app)</li>'
                        . '<li><strong>Aider à organiser une LRAR collective</strong> entre les voisins concernés</li>'
                        . '<li><strong>Centraliser au GA</strong> pour pression politique</li>'
                        . '</ul>'
                        . '<p><strong>Exception facturable</strong> : si un locataire fait casser un truc commun (ex: boîte aux lettres défoncée), ça peut être lui le responsable — vérifier avant de facturer.</p>',
                ],
            ],
        ],

    ];
}

/* ============================================================== *
 *  VUE : Liste des tutoriels (admin seulement)                     *
 * ============================================================== */
function lfi_nct_app_view_tutoriels() {
    if (!lfi_nct_app_guard_brigade()) return;
    $tutos = lfi_nct_tutoriels_all();

    lfi_nct_app_screen_open('🛠 Tutoriels brigade', count($tutos) . ' guides pros pour les chantiers');

    echo '<div class="lfi-app-help"><strong>Réservé à toi (brigade Fabrice Doucet).</strong> Chaque tuto : diagnostic, outils + prix + sources, matériaux + prix + sources, procédure pas à pas, sécurité, calcul de facturation NMH.</div>';

    echo '<ul class="lfi-app-list">';
    foreach ($tutos as $slug => $t) {
        echo '<li class="lfi-app-card">';
        echo '<a href="' . esc_url(lfi_nct_app_url('tutoriel', ['t' => $slug])) . '" style="text-decoration:none;color:inherit">';
        echo '<div class="head"><div class="who">' . $t['icone'] . ' ' . esc_html($t['titre']) . '</div></div>';
        echo '<div class="lfi-app-help" style="margin:6px 0 0;background:transparent;border:0;padding:0;color:#666"><small>' . esc_html($t['sous']) . '</small></div>';
        echo '<div class="row-actions" style="margin-top:8px"><span class="btn-primary">📖 Lire le guide</span></div>';
        echo '</a></li>';
    }
    echo '</ul>';

    lfi_nct_app_screen_close();
}

/* ============================================================== *
 *  VUE : Un tutoriel détaillé                                      *
 * ============================================================== */
function lfi_nct_app_view_tutoriel() {
    if (!lfi_nct_app_guard_brigade()) return;
    $slug = sanitize_key($_GET['t'] ?? '');
    $tutos = lfi_nct_tutoriels_all();
    if (!isset($tutos[$slug])) {
        lfi_nct_app_screen_open('Tutoriel introuvable');
        echo '<div style="background:#fff;color:#000;padding:20px;border-radius:8px"><p style="color:#000">Tutoriel « ' . esc_html($slug) . ' » introuvable.</p><a href="' . esc_url(lfi_nct_app_url('tutoriels')) . '" style="color:#c8102e;text-decoration:underline">← Retour à la liste</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    $t = $tutos[$slug];

    lfi_nct_app_screen_open($t['icone'] . ' ' . $t['titre'], $t['sous'] ?? '');

    /* Rendu TOUT inline-styled, ZÉRO dépendance CSS externe
       (le thème AG Starter force color: blanc sur certains parents). */
    echo '<div style="background:#fafafa;color:#1a1a1a;padding:8px 0">';

    /* Accordéons natifs <details> — premier ouvert, les autres pliés.
       Le contenu HTML brut reste identique, on change juste le contenant. */
    $first = true;
    foreach ($t['sections'] as $key => $sec) {
        $open_attr = $first ? ' open' : '';
        $first = false;
        echo '<details' . $open_attr . ' style="background:#ffffff;color:#1a1a1a;border-radius:12px;margin:0 0 10px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #eee;overflow:hidden">';
        echo '<summary style="cursor:pointer;padding:14px 18px;font-weight:800;color:#c8102e;font-size:1.05em;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:10px;-webkit-tap-highlight-color:transparent;user-select:none">';
        echo '<span style="flex:1">' . esc_html($sec['titre']) . '</span>';
        echo '<span class="lfi-acc-chevron" style="font-size:1.3em;color:#c8102e;transition:transform .2s">▾</span>';
        echo '</summary>';
        echo '<div style="padding:0 20px 18px;color:#1a1a1a;font-size:.95em;line-height:1.55">';
        echo $sec['contenu'];
        echo '</div>';
        echo '</details>';
    }

    echo '</div>';

    echo '<div style="margin-top:18px;display:flex;gap:8px;flex-wrap:wrap">';
    echo '<a style="background:#fff;color:#c8102e;border:1.5px solid #c8102e;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700" href="' . esc_url(lfi_nct_app_url('tutoriels')) . '">← Tous les tutoriels</a>';
    echo '<a style="background:#c8102e;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700" href="' . esc_url(lfi_nct_app_url('intervention-add')) . '">+ Créer une intervention</a>';
    echo '</div>';

    /* Force le color: noir sur tous les éléments du tutoriel.
       Style inline avec sélecteurs de descendant — bat la cascade du thème. */
    ?>
    <style>
    .lfi-app-screen-body p,
    .lfi-app-screen-body li,
    .lfi-app-screen-body strong,
    .lfi-app-screen-body em,
    .lfi-app-screen-body td,
    .lfi-app-screen-body h3,
    .lfi-app-screen-body div { color: inherit; }
    .lfi-app-screen-body table { border-collapse: collapse; width: 100%; margin: 10px 0; font-size: .9em; }
    .lfi-app-screen-body table th { background: #c8102e !important; color: #fff !important; padding: 8px 6px; text-align: left; font-weight: 700; }
    .lfi-app-screen-body table td { border-bottom: 1px solid #eee; padding: 8px 6px; color: #1a1a1a !important; vertical-align: top; }
    .lfi-app-screen-body table tr:nth-child(even) td { background: #fafafa; }
    .lfi-app-screen-body ul, .lfi-app-screen-body ol { margin: 8px 0; padding-left: 1.4em; }
    .lfi-app-screen-body li { margin-bottom: 5px; color: #1a1a1a !important; }
    .lfi-app-screen-body p { margin: 8px 0; color: #1a1a1a !important; }
    .lfi-app-screen-body strong { color: #1a1a1a !important; font-weight: 700; }
    .lfi-app-screen-body details summary::-webkit-details-marker { display: none; }
    .lfi-app-screen-body details summary::marker { content: ""; }
    .lfi-app-screen-body details[open] .lfi-acc-chevron { transform: rotate(180deg); }
    .lfi-app-screen-body details summary:hover { background: #fdf5f6; }
    </style>
    <?php

    lfi_nct_app_screen_close(false);
}
