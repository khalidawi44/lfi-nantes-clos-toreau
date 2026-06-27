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
                'diagnostic' => [
                    'titre' => '🔬 1. Diagnostic (ne pas sauter cette étape)',
                    'contenu' => '<p>Avant tout traitement, identifier la cause sinon ça revient en 6 mois.</p>'
                        . '<ul>'
                        . '<li><strong>Hygromètre digital LCD</strong> 8 € chez Brico Dépôt (rayon thermostats). RH > 70 % = condensation. RH normale + zone localisée mouillée = infiltration.</li>'
                        . '<li><strong>Identification visuelle :</strong><ul>'
                        . '<li>Taches <strong>noires épaisses, gluantes</strong> = <em>Stachybotrys chartarum</em> — toxique, EPI total obligatoire.</li>'
                        . '<li>Vert/bleu poudreux = <em>Penicillium / Aspergillus</em> — moins dangereux.</li>'
                        . '<li>Blanc duveteux = <em>Mucor</em> — superficiel, bénin.</li>'
                        . '</ul></li>'
                        . '<li><strong>Test au couteau :</strong> si la plaque s\'effrite ou que le couteau s\'enfonce, le plâtre est pourri à cœur, démolition obligatoire avec marge de 30 cm autour de la zone atteinte.</li>'
                        . '<li><strong>Cause structurelle ou non ?</strong> Mettre un sac plastique scotché sur la zone 24 h. Si gouttes d\'eau dessus = condensation (problème VMC/isolation). Si gouttes dessous = infiltration extérieure → <strong>c\'est au bailleur, devis pas concerné</strong>.</li>'
                        . '</ul>',
                ],
                'outils' => [
                    'titre' => '🧰 2. Outils nécessaires (investissement brigade)',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Outil</th><th style="padding:6px">Prix</th><th style="padding:6px">Où acheter</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Aspirateur HEPA H13 atelier (Karcher WD3 + filtre HEPA)</td><td style="text-align:center">85 €</td><td style="text-align:center"><strong>Brico Dépôt</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Pulvérisateur 5 L Hozelock</td><td style="text-align:center">12 €</td><td style="text-align:center"><strong>Brico Dépôt</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Couteaux à enduire 10 + 25 + 40 cm (lot pro)</td><td style="text-align:center">15 €</td><td style="text-align:center">ManoMano Pro</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Scie égoïne pour plaques de plâtre</td><td style="text-align:center">6 €</td><td style="text-align:center"><strong>Brico Dépôt</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Cale à poncer + abrasifs 80 / 120 / 240</td><td style="text-align:center">8 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Visseuse Bosch GSR 12V (entrée gamme pro)</td><td style="text-align:center">79 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Mètre laser Leica Disto D1</td><td style="text-align:center">59 €</td><td style="text-align:center">ManoMano Pro</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Hygromètre digital</td><td style="text-align:center">8 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px"><strong>EPI : masque FFP3 (lot 5), gants nitrile, lunettes, combinaison Tyvek jetable</strong></td><td style="text-align:center">25 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>Investissement initial brigade : ~ 300 €</strong>, amorti dès la 2e intervention facturée.</p>',
                ],
                'materiaux' => [
                    'titre' => '🧪 3. Matériaux par intervention (pour 4 m²)',
                    'contenu' => '<table style="width:100%;border-collapse:collapse;font-size:.9em">'
                        . '<tr><th style="text-align:left;padding:6px;border-bottom:2px solid #c8102e">Produit</th><th style="padding:6px">Prix</th><th style="padding:6px">Source la moins chère</th></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee"><strong>Biocide pro Algiclair Pro</strong> (peroxyde stabilisé, sans javel — pas de chlore résiduel) 1 L</td><td style="text-align:center">14 €</td><td style="text-align:center"><strong>ManoMano Pro</strong></td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Plaque BA13 hydro (verte, anti-humidité) 1,20 × 2,50 m × 2</td><td style="text-align:center">17 €</td><td style="text-align:center"><strong>Brico Dépôt</strong> (le moins cher)</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Vis Spax TT 35 mm boîte 200</td><td style="text-align:center">6 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Bande à joint papier 90 m (Knauf)</td><td style="text-align:center">5 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Enduit à joint Toupret 30 min (sac 5 kg)</td><td style="text-align:center">12 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Sous-couche bloquante <strong>Julien Stop Tâches</strong> 1 L (8 m²)</td><td style="text-align:center">8 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Peinture finition <strong>Dulux Valentine Cuisines & Bains</strong> 1 L (biocide intégré, 8 m²/L)</td><td style="text-align:center">18 €</td><td style="text-align:center">Castorama / promo Brico Privé</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Joint silicone sanitaire Sikaflex 11FC</td><td style="text-align:center">6 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">Bâche polyane 4 × 5 m épaisse + sacs gravats x 5</td><td style="text-align:center">15 €</td><td style="text-align:center">Brico Dépôt</td></tr>'
                        . '<tr><td style="padding:6px;border-bottom:1px solid #eee">EPI à usage unique pour ce chantier (1 combi, 2 masques)</td><td style="text-align:center">8 €</td><td style="text-align:center">Amazon</td></tr>'
                        . '<tr style="background:#fff3f5"><td style="padding:8px"><strong>TOTAL matériaux 4 m²</strong></td><td style="text-align:center"><strong>109 €</strong></td><td></td></tr>'
                        . '</table>'
                        . '<p style="margin-top:8px"><strong>💰 Facturé NMH</strong> à Mme Fadiga : 4 h × 40 €/h + 109 € matériaux = <strong>269 € HT</strong>. Marge nette ≈ 160 €.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 4. Procédure pro complète (4 h sur place)',
                    'contenu' => '<p><strong>PHASE 1 — Préparation chantier (20 min)</strong></p>'
                        . '<ol>'
                        . '<li>Sortir le mobilier ou bâcher 3 m autour de la zone (polyane).</li>'
                        . '<li>Mettre la VMC à l\'arrêt pendant l\'intervention (pour ne pas disperser les spores dans tout l\'immeuble).</li>'
                        . '<li>EPI : combinaison Tyvek + masque FFP3 + gants + lunettes.</li>'
                        . '<li>Aspirateur HEPA branché, embout fin prêt à côté de la zone.</li>'
                        . '<li>Photos AVANT (preuve facture + dossier locataire dans l\'app).</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 2 — Dépose propre (1 h pour 4 m²)</strong></p>'
                        . '<ol>'
                        . '<li>Délimiter la zone à démolir : tracer un rectangle <strong>avec 30 cm de marge</strong> autour des taches visibles (le mycélium s\'étend toujours plus loin que le visible).</li>'
                        . '<li>Couper au cutter le long du tracé sur 1 cm de profondeur, puis à la scie égoïne sur toute l\'épaisseur (13 mm).</li>'
                        . '<li><strong>Démonter par plaques entières</strong> en travaillant vers le bas (gravité) — aspirateur HEPA en marche dans la main libre.</li>'
                        . '<li>Sacs gravats à dévisser sur place, fermés au scotch armé. Sortir au plus vite par le palier (jamais dans la poubelle commune — c\'est de l\'amiante potentielle dans le vieux bâti, à porter à la déchetterie en sac fermé).</li>'
                        . '<li>Brosser l\'ossature métallique (rails) restante au pulvérisateur d\'Algiclair Pro. Laisser ruisseler.</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 3 — Décontamination support (30 min + 4 h de pause)</strong></p>'
                        . '<ol>'
                        . '<li>Pulvériser <strong>Algiclair Pro pur</strong> sur tout le support (mur béton ou ossature) ET sur 30 cm autour de la zone démolie.</li>'
                        . '<li>Laisser agir 4 h — moment idéal pour aller chercher les plaques BA13 hydro ou pour passer à un autre chantier.</li>'
                        . '<li>Pendant ce temps, <strong>vérifier la VMC :</strong> tester aspiration avec feuille de papier qui doit coller. Si VMC HS → mention sur le rapport, c\'est au bailleur de la remplacer (mais on facture quand même la pose du placo).</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 4 — Pose plaque BA13 hydro (1 h pour 4 m²)</strong></p>'
                        . '<ol>'
                        . '<li>Mesurer puis tracer au cordeau les dimensions.</li>'
                        . '<li>Couper la plaque au cutter : trois passages côté carton → casser net → entaille du carton de l\'autre face. <strong>Pas besoin de scie pour les coupes droites.</strong></li>'
                        . '<li>Visser sur ossature avec vis Spax TT 35 mm, <strong>1 vis tous les 25 cm</strong> sur les rails, sans dépasser ni creuser le carton.</li>'
                        . '<li>Joints décalés entre plaques (jamais en croix).</li>'
                        . '<li>Bande à joint papier humidifiée, posée sur enduit frais à la spatule. Lisser fort.</li>'
                        . '<li>Enduit Toupret 30 min en 2 passes : <strong>1ère noyée</strong> dans la bande, 2ème <strong>lissée large</strong> (40 cm de chaque côté du joint).</li>'
                        . '<li>Ponçage : grain 120 puis 240 quand sec. Aspirer la poussière.</li>'
                        . '</ol>'
                        . '<p><strong>PHASE 5 — Finition (30 min + séchage 24 h, à reprendre J+1)</strong></p>'
                        . '<ol>'
                        . '<li>Sous-couche Julien Stop Tâches au rouleau, 1 couche.</li>'
                        . '<li>Le lendemain : 2 couches de Dulux Valentine Cuisines & Bains espacées de 4 h.</li>'
                        . '<li>Joint silicone Sikaflex sur les raccords plinthe / plafond.</li>'
                        . '<li>Photos APRÈS (preuve facture).</li>'
                        . '</ol>',
                ],
                'securite' => [
                    'titre' => '🦺 5. Sécurité (vraie, pas mesurette)',
                    'contenu' => '<ul>'
                        . '<li><strong>Combinaison Tyvek + masque FFP3 obligatoires</strong> dès que le mycélium est visible. Le carton de plâtre infesté libère des millions de spores par geste.</li>'
                        . '<li>Ne JAMAIS travailler à sec sur du mycélium <strong>noir épais</strong> (Stachybotrys) — il y a un risque de mycotoxine.</li>'
                        . '<li><strong>Évacuer la pièce les locataires sensibles</strong> (enfants < 6 ans, asthmatiques, immunodéprimés) le temps de l\'intervention + 4 h après.</li>'
                        . '<li><strong>Ne pas mélanger</strong> Algiclair Pro (peroxyde) et eau de Javel. La réaction libère du chlore gazeux toxique. Toujours travailler avec un seul biocide.</li>'
                        . '<li>VMC arrêtée pendant l\'intervention, redémarrée 1 h après — sinon les spores se baladent dans tout l\'immeuble.</li>'
                        . '<li>EPI pas réutilisable pour ce type de chantier : à jeter en sac fermé.</li>'
                        . '</ul>',
                ],
                'cause' => [
                    'titre' => '🔍 6. Traiter la CAUSE (sinon retour en 6 mois)',
                    'contenu' => '<ul>'
                        . '<li><strong>VMC bouchée / HS</strong> (90 % des cas en HLM Clos Toreau) : le démontage et nettoyage de la grille intérieure (gants, brosse à dents, vinaigre blanc) est <strong>locatif</strong> et peut se facturer en supplément 50 €. Remplacement complet du moteur = bailleur.</li>'
                        . '<li><strong>Grille d\'aération bouchée</strong> dans les pièces humides : nettoyage idem.</li>'
                        . '<li><strong>Infiltration extérieure</strong> : pas dans ton scope, mention dans le rapport pour LRAR au bailleur (lettre déjà dans l\'app).</li>'
                        . '<li><strong>Remontée capillaire</strong> (bas de mur humide en permanence) : traitement injection — bailleur uniquement (3 000-5 000 €).</li>'
                        . '<li><strong>Pont thermique</strong> : doublage liège ou polystyrène extrudé XPS — peut être facturé en supplément 80 €/m² posé.</li>'
                        . '</ul>',
                ],
                'sources' => [
                    'titre' => '🛒 7. Où acheter au moins cher (récap)',
                    'contenu' => '<ul>'
                        . '<li><strong>Brico Dépôt</strong> Saint-Herblain : plaques de plâtre, visserie, enduits, peintures de base — 30 % moins cher que Castorama.</li>'
                        . '<li><strong>ManoMano Pro</strong> (compte gratuit, pas besoin de SIRET) : Algiclair, outils pro, livraison.</li>'
                        . '<li><strong>Amazon</strong> : EPI, petits outils ponctuels (visseuse, lasermètre).</li>'
                        . '<li><strong>Brico Privé</strong> : ventes flash sur peintures pro (jusqu\'à -50 %).</li>'
                        . '<li><strong>Pestclic.com</strong> : biocides pro accessibles aux particuliers.</li>'
                        . '<li><strong>Leboncoin</strong> : matériel d\'occasion (compresseur, gros outil) si tu en achètes un.</li>'
                        . '<li><strong>Eurometal Nantes</strong> (zone industrielle) : visserie en gros, tarifs pro même sans SIRET.</li>'
                        . '<li><strong>Déchetterie de Saint-Aignan-de-Grand-Lieu</strong> : dépôt gratuit des plaques infestées (présenter justificatif de domicile à Nantes Métropole).</li>'
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
    if (!current_user_can('manage_options')) return;
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
    if (!current_user_can('manage_options')) return;
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

    foreach ($t['sections'] as $key => $sec) {
        echo '<div style="background:#ffffff;color:#1a1a1a;border-radius:12px;padding:18px 20px;margin:0 0 14px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #eee;display:block">';
        echo '<h3 style="margin:0 0 12px;font-size:1.1em;color:#c8102e !important;font-weight:800;line-height:1.3">' . esc_html($sec['titre']) . '</h3>';
        echo '<div style="color:#1a1a1a !important;font-size:.95em;line-height:1.55">';
        /* On force le rendu en wrappant tout le contenu HTML brut */
        echo $sec['contenu'];
        echo '</div>';
        echo '</div>';
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
    </style>
    <?php

    lfi_nct_app_screen_close(false);
}
