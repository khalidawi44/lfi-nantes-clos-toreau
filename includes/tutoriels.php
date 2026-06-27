<?php
/**
 * Bibliothèque de tutoriels — résolution des problèmes signalés à l'enquête.
 *
 * Pour chaque type de problème : outils, matériaux, coût estimé,
 * procédure pas à pas, précautions santé, quand appeler un pro.
 *
 * Double usage :
 *  - admin / brigade Fabrice Doucet : guide d'intervention
 *  - locataire : auto-dépannage (consultable depuis la page droits)
 */
if (!defined('ABSPATH')) exit;

/**
 * Renvoie le tableau des tutoriels indexés par slug.
 * Structure : ['slug' => ['titre' => ..., 'icone' => ..., 'sections' => [...]]]
 */
function lfi_nct_tutoriels_all() {
    return [
        'humidite' => [
            'icone' => '🌫',
            'titre' => 'Moisissures & humidité',
            'sous'  => 'Solution low-cost, santé d\'abord',
            'sections' => [
                'urgence' => [
                    'titre' => '🚨 D\'abord, protéger la santé',
                    'contenu' => '<ul>'
                        . '<li><strong>Ne jamais gratter à sec :</strong> les spores se dispersent et s\'inhalent.</li>'
                        . '<li>Sortir les jeunes enfants, asthmatiques, personnes immunodéprimées de la pièce.</li>'
                        . '<li><strong>Aérer fenêtre grand ouverte</strong> 10 min avant de commencer, et après le traitement (1 h).</li>'
                        . '<li>Mettre <strong>masque FFP2 ou FFP3</strong>, gants nitrile, lunettes.</li>'
                        . '</ul>',
                ],
                'outils' => [
                    'titre' => '🧰 Outils nécessaires',
                    'contenu' => '<ul>'
                        . '<li>Pulvérisateur (5-10 €)</li>'
                        . '<li>Brosse à poils durs (3 €)</li>'
                        . '<li>Éponge, chiffons, raclette</li>'
                        . '<li>Aspirateur avec <strong>filtre HEPA</strong> (sinon ça redisperse) — empruntable, sinon louer (20 €/jour)</li>'
                        . '<li>EPI : masque FFP2/FFP3, gants nitrile, lunettes (10 €)</li>'
                        . '<li>Échelle si plafond</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 Matériaux et budget',
                    'contenu' => '<ul>'
                        . '<li><strong>Vinaigre blanc 14°</strong> : 2 €/L (efficace seul si surface < 1 m²)</li>'
                        . '<li>Bicarbonate de soude : 3 € le kilo</li>'
                        . '<li>Antifongique (peroxyde stabilisé, type Cristalin) : 8-12 €/L pour surfaces > 1 m²</li>'
                        . '<li>Peinture anti-moisissures (Tollens, V33, Dulux) : 15-25 €/L, 1 L = 10 m²</li>'
                        . '<li>Rouleau + bac + ruban de masquage : 10 €</li>'
                        . '</ul>'
                        . '<p><strong>Coût total pour 1 m² : 30 à 50 € en autonomie.</strong></p>',
                ],
                'procedure' => [
                    'titre' => '👷 Procédure pas à pas',
                    'contenu' => '<ol>'
                        . '<li><strong>Préparer la pièce :</strong> protéger sol et mobilier avec bâche, dégager la zone.</li>'
                        . '<li>Aspirer délicatement les spores visibles (filtre HEPA, sac jetable ensuite).</li>'
                        . '<li>Pulvériser <strong>vinaigre blanc pur</strong> sur toute la zone, déborder 30 cm autour.</li>'
                        . '<li>Laisser agir <strong>30 min minimum</strong>. (Surface > 1 m² : pulvériser antifongique.)</li>'
                        . '<li>Frotter à la brosse en spirale (sans appuyer fort).</li>'
                        . '<li>Rincer à l\'eau claire, éponger.</li>'
                        . '<li><strong>Sécher complètement</strong> (24-48 h, ventilateur si possible). Étape cruciale : repeindre sur humide = revient sous un mois.</li>'
                        . '<li>Appliquer une <strong>sous-couche bloquante</strong> (Julien Anti-tache, 5 €/L) sur les zones tachées.</li>'
                        . '<li>Repeindre avec peinture anti-moisissures, 2 couches espacées de 4 h.</li>'
                        . '<li>Aérer la pièce 1 h après application.</li>'
                        . '</ol>',
                ],
                'cause' => [
                    'titre' => '🔍 Traiter la CAUSE (sinon ça revient)',
                    'contenu' => '<p>La moisissure revient toujours si on ne traite pas la source :</p>'
                        . '<ul>'
                        . '<li><strong>Condensation</strong> (90 % des cas) : VMC à nettoyer/réparer (locataire) ou installer (bailleur). Vérifier que les grilles d\'aération ne sont pas bouchées.</li>'
                        . '<li><strong>Infiltration extérieure</strong> : étanchéité toiture/façade — c\'est au bailleur. Photo + LRAR + générer la lettre depuis l\'app.</li>'
                        . '<li><strong>Remontée capillaire</strong> (mur du bas humide en permanence) : traitement injection — bailleur uniquement.</li>'
                        . '<li><strong>Pont thermique</strong> (mur froid contre extérieur) : doubler avec liège ou polystyrène extrudé (XPS) — peut être DIY.</li>'
                        . '</ul>'
                        . '<p><strong>Astuces quotidiennes</strong> : aérer 10 min matin et soir, couvercle sur les casseroles, hotte cuisine, sortir le linge mouillé, chauffer 18-19 °C en continu (mieux que coups de chauffe).</p>',
                ],
                'pro' => [
                    'titre' => '⚠ Quand appeler un pro',
                    'contenu' => '<ul>'
                        . '<li>Surface infestée > 3 m²</li>'
                        . '<li>Moisissures noires épaisses (<em>Stachybotrys</em>) — toxiques</li>'
                        . '<li>Personnes asthmatiques ou bébés dans le logement</li>'
                        . '<li>Cause structurelle (fuite cachée, mauvaise isolation) → c\'est au bailleur, pas à toi</li>'
                        . '</ul>',
                ],
            ],
        ],

        'insectes' => [
            'icone' => '🐜',
            'titre' => 'Nuisibles : cafards, punaises, rats',
            'sous'  => 'Méthodes peu chères, à enchaîner sur 3 semaines',
            'sections' => [
                'cafards' => [
                    'titre' => '🪳 CAFARDS / BLATTES',
                    'contenu' => '<p><strong>Outils :</strong> aspirateur HEPA, pulvérisateur, lampe de poche, pinceau fin pour gel, gants.</p>'
                        . '<p><strong>Matériaux et budget :</strong></p>'
                        . '<ul>'
                        . '<li><strong>Gel appât (Goliath, Maxforce IC ou Avert) :</strong> 8-15 € la seringue, le plus efficace. Petits points de 3-5 mm tous les 30 cm sur les passages connus.</li>'
                        . '<li>Acide borique en poudre + sucre (50/50) : 4 €, classique mais lent.</li>'
                        . '<li>Pièges collants (5 € le lot) pour suivi de population.</li>'
                        . '</ul>'
                        . '<p><strong>Procédure :</strong></p>'
                        . '<ol>'
                        . '<li>Bouchez fentes (silicone) : sous évier, derrière plinthes, autour des tuyaux.</li>'
                        . '<li>Nettoyez à fond : pas de miettes, vaisselle propre, poubelle fermée.</li>'
                        . '<li>Appliquez le gel le soir, dans les recoins sombres, près des points d\'eau.</li>'
                        . '<li>Pas de bombe insecticide en même temps que le gel : ils évitent les zones traitées.</li>'
                        . '<li>Renouvelez à J+15 et J+30 (cycle de reproduction).</li>'
                        . '</ol>'
                        . '<p><strong>Coût total :</strong> 20-30 € pour un appartement standard.</p>'
                ],
                'punaises' => [
                    'titre' => '🛏 PUNAISES DE LIT',
                    'contenu' => '<p><strong>Outils :</strong> aspirateur HEPA + sac jetable, défroisseur vapeur ou sèche-cheveux (>60 °C), sacs étanches.</p>'
                        . '<p><strong>Matériaux et budget :</strong></p>'
                        . '<ul>'
                        . '<li><strong>Terre de Diatomée alimentaire</strong> (Naturasil ou Kapo) : 8-12 €/kg. Très efficace, sans chimie.</li>'
                        . '<li>Insecticide pyréthrines (FMC ou Subito) : 10 €.</li>'
                        . '<li>Housse anti-punaises pour matelas et oreillers : 30-50 €.</li>'
                        . '</ul>'
                        . '<p><strong>Procédure (à enchaîner sans interruption) :</strong></p>'
                        . '<ol>'
                        . '<li><strong>Inspection visuelle</strong> avec lampe : coutures de matelas, sommier, tête de lit, plinthes, fissures, prises électriques.</li>'
                        . '<li><strong>Aspirateur HEPA</strong> partout, sac jetable mis en sachet hermétique immédiatement (poubelle dehors).</li>'
                        . '<li><strong>Lavage à 60 °C</strong> minimum de tout le linge, draps, vêtements. Ce qu\'on ne peut pas laver : congélateur à -18 °C pendant 72 h.</li>'
                        . '<li><strong>Vapeur à >60 °C</strong> sur matelas, sommier, plinthes (passes lentes, 30 cm/sec).</li>'
                        . '<li><strong>Terre de Diatomée</strong> en fine poudre dans les fissures, sous le lit, le long des plinthes. Effet lent (10-15 j) mais sûr.</li>'
                        . '<li>Pulvériser insecticide dans les recoins, JAMAIS sur le matelas où vous dormez.</li>'
                        . '<li><strong>Housses anti-punaises</strong> sur matelas + oreillers, gardées 1 an min (œufs).</li>'
                        . '<li>Renouveler aspirateur + vapeur tous les 7 jours pendant 3 semaines.</li>'
                        . '</ol>'
                        . '<p><strong>⚠ Erreurs à éviter :</strong> ne JAMAIS donner ou jeter de meubles infestés (propagation). Ne pas mélanger les insecticides. En HLM, c\'est <strong>au bailleur</strong> de traiter (loi ELAN 2018).</p>'
                ],
                'rats' => [
                    'titre' => '🐀 RATS / SOURIS',
                    'contenu' => '<p><strong>Outils :</strong> gants en cuir, pince à long manche pour ramasser, lampe.</p>'
                        . '<p><strong>Matériaux et budget :</strong></p>'
                        . '<ul>'
                        . '<li><strong>Tapettes mécaniques</strong> (Victor, Pic ou Kness) : 5-10 € la pièce. Méthode la plus humaine si bien réglée.</li>'
                        . '<li>Pièges à capture vivante (15-25 €) si on veut relâcher.</li>'
                        . '<li>Raticide en bloc avec station sécurisée (Difenacoum ou Brodifacoum) : 15-25 €. Attention enfants/animaux.</li>'
                        . '<li>Laine d\'acier + silicone pour boucher les trous d\'entrée : 10 €.</li>'
                        . '</ul>'
                        . '<p><strong>Procédure :</strong></p>'
                        . '<ol>'
                        . '<li>Inspection : trouver les points d\'entrée (trous de souris : taille pièce de 2 €, rat : taille balle de tennis).</li>'
                        . '<li>Boucher avec laine d\'acier + silicone (les rongeurs ne rongent pas l\'acier).</li>'
                        . '<li>Poser pièges sur les trajets repérés (le long des murs).</li>'
                        . '<li>Appâter avec beurre de cacahuète (plus efficace que le fromage), chocolat, ou nourriture pour chat humide.</li>'
                        . '<li>Vérifier tous les jours.</li>'
                        . '</ol>'
                        . '<p><strong>Précautions :</strong> ne pas toucher rongeur ou crottes à mains nues (leptospirose, hantavirus). Bien laver les surfaces à l\'eau de Javel après nettoyage.</p>'
                ],
            ],
        ],

        'chauffage' => [
            'icone' => '🥶',
            'titre' => 'Chauffage insuffisant / froid',
            'sous'  => 'Calfeutrage low-cost, isoler la chaleur',
            'sections' => [
                'outils' => [
                    'titre' => '🧰 Outils',
                    'contenu' => '<ul>'
                        . '<li>Cutter, règle métallique</li>'
                        . '<li>Mètre ruban</li>'
                        . '<li>Thermomètre intérieur (5 €)</li>'
                        . '<li>Pistolet à mastic si calfeutrage en cordon</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 Matériaux et budget',
                    'contenu' => '<ul>'
                        . '<li><strong>Joints adhésifs mousse</strong> pour fenêtres et portes : 5-10 € (10 m)</li>'
                        . '<li><strong>Boudin de porte</strong> brosse : 10-15 €</li>'
                        . '<li><strong>Film de survitrage</strong> rétractable (Tesa) : 15-20 € pour 2 fenêtres. Très efficace.</li>'
                        . '<li><strong>Réflecteur de chaleur</strong> aluminium derrière radiateurs : 10-15 €. Renvoie 90 % de la chaleur perdue au mur.</li>'
                        . '<li>Rideaux thermiques épais : 30-50 € (occasion en brocante : 10 €)</li>'
                        . '<li>Tapis épais au sol carrelé : 20-40 €</li>'
                        . '</ul>'
                        . '<p><strong>Budget total bouclier-froid : 80-150 € pour un T3.</strong> ROI en 1 hiver via la facture d\'énergie.</p>',
                ],
                'procedure' => [
                    'titre' => '👷 Procédure',
                    'contenu' => '<ol>'
                        . '<li><strong>Repérer les courants d\'air :</strong> bougie allumée près des fenêtres et portes — si la flamme bouge, c\'est là.</li>'
                        . '<li><strong>Calfeutrer les fenêtres :</strong> nettoyer le cadre, sécher, coller joint mousse sur partie mobile.</li>'
                        . '<li><strong>Boudin sous porte d\'entrée.</strong></li>'
                        . '<li><strong>Survitrage film rétractable</strong> sur fenêtres simples : poser scotch double face, dérouler film, chauffer au sèche-cheveux (devient transparent et tendu).</li>'
                        . '<li><strong>Réflecteur derrière radiateur :</strong> couper aux dimensions, fixer au double face. Côté brillant vers le radiateur.</li>'
                        . '<li>Fermer les volets / rideaux la nuit, ouvrir le jour si soleil.</li>'
                        . '<li>Régler le thermostat à 18-19 °C en continu (mieux que 22 °C par à-coups).</li>'
                        . '</ol>'
                        . '<p><strong>Légalement :</strong> le bailleur doit garantir 18 °C dans les pièces principales en hiver (décret 2002-120, art. 3). Si malgré tes efforts tu n\'y arrives pas → générer la lettre depuis l\'app.</p>',
                ],
            ],
        ],

        'degats_eaux' => [
            'icone' => '💧',
            'titre' => 'Fuites & dégâts des eaux',
            'sous'  => 'Stopper, contenir, documenter',
            'sections' => [
                'urgence' => [
                    'titre' => '🚨 Urgence : stopper la fuite',
                    'contenu' => '<ol>'
                        . '<li><strong>Couper l\'eau au robinet général</strong> (sous évier ou compteur).</li>'
                        . '<li><strong>Couper l\'électricité</strong> si la fuite atteint des prises ou luminaires.</li>'
                        . '<li>Disposer bassines, serpillères, contenants pour limiter les dégâts au sol et aux étages inférieurs.</li>'
                        . '<li><strong>Prévenir le voisin du dessous</strong> si dégât potentiel.</li>'
                        . '<li>Photos datées avant et pendant le nettoyage (preuve assurance).</li>'
                        . '</ol>',
                ],
                'outils' => [
                    'titre' => '🧰 Outils pour réparer soi-même',
                    'contenu' => '<ul>'
                        . '<li>Clé à molette ou clé plate selon raccord</li>'
                        . '<li>Pince multiprise</li>'
                        . '<li>Tournevis cruciforme</li>'
                        . '<li>Téflon (filasse ou ruban PTFE) : 2 €</li>'
                        . '</ul>',
                ],
                'materiaux' => [
                    'titre' => '🧪 Matériaux courants',
                    'contenu' => '<ul>'
                        . '<li><strong>Joints en caoutchouc</strong> de robinet (3-5 € le sachet)</li>'
                        . '<li><strong>Ruban étanchéité urgence</strong> (Tesa Bond ou Loctite) : 8-12 €. Tient sous pression.</li>'
                        . '<li><strong>Mastic colle</strong> sanitaire (Pattex Repair Express) : 8 €. Durcit en 5 min.</li>'
                        . '<li>Flexible neuf de robinet si fuite à un raccord : 5-10 €</li>'
                        . '</ul>',
                ],
                'procedure' => [
                    'titre' => '👷 Réparation courante (fuite robinet)',
                    'contenu' => '<ol>'
                        . '<li>Couper l\'arrivée d\'eau spécifique (robinet sous évier).</li>'
                        . '<li>Démonter le flexible défectueux (clé à molette).</li>'
                        . '<li>Remplacer par un flexible neuf (8 €).</li>'
                        . '<li>Mettre du téflon sur le pas de vis, serrer modérément.</li>'
                        . '<li>Rouvrir l\'eau doucement, vérifier l\'étanchéité.</li>'
                        . '</ol>'
                        . '<p><strong>Fuite importante ou cachée</strong> (mur, plafond) : c\'est au bailleur. Documenter, LRAR, déclaration assurance habitation.</p>',
                ],
            ],
        ],

        'eau_chaude' => [
            'icone' => '🚿',
            'titre' => 'Coupures d\'eau chaude',
            'sous'  => 'En attente, tracer pour le dossier',
            'sections' => [
                'attente' => [
                    'titre' => '⏳ En attendant le retour',
                    'contenu' => '<ul>'
                        . '<li><strong>Bouilloire électrique</strong> pour la vaisselle (5-15 €).</li>'
                        . '<li>Casseroles d\'eau chaude pour douche au gant en cas de besoin (10 L = 1 douche rapide).</li>'
                        . '<li>Lingettes nettoyantes pour bébés et adultes (3-5 €).</li>'
                        . '<li>Si possible, douche chez voisin ou famille pour les jours longs.</li>'
                        . '<li>Si chauffe-eau électrique individuel : vérifier disjoncteur, thermostat.</li>'
                        . '</ul>',
                ],
                'tracer' => [
                    'titre' => '📋 Tracer chaque coupure (essentiel pour la procédure)',
                    'contenu' => '<p>Notez à chaque épisode dans un cahier ou dans le téléphone :</p>'
                        . '<ul>'
                        . '<li>Date et heure de début</li>'
                        . '<li>Date et heure de fin</li>'
                        . '<li>Durée totale en heures</li>'
                        . '<li>Action faite (appel bailleur ? courrier ? Réponse ?)</li>'
                        . '<li>Témoin / voisin concerné</li>'
                        . '<li>Conséquence (douche manquée, enfants malades, etc.)</li>'
                        . '</ul>'
                        . '<p><strong>Ce journal est votre preuve juridique.</strong> Le GA centralise les preuves de plusieurs locataires pour une <strong>action collective</strong> contre Nantes Métropole Habitat.</p>'
                        . '<p>Au-delà de 48 h sans eau chaude : LRAR systématique au bailleur, demande de réduction de loyer.</p>',
                ],
            ],
        ],

        'electricite' => [
            'icone' => '⚡',
            'titre' => 'Problèmes électriques',
            'sous'  => 'Sécurité d\'abord, le moindre doute = pro',
            'sections' => [
                'urgence' => [
                    'titre' => '🚨 Sécurité d\'abord',
                    'contenu' => '<ul>'
                        . '<li>Disjoncteur saute en continu : ne PAS réenclencher en force. Identifier la cause.</li>'
                        . '<li>Odeur de brûlé près d\'une prise : couper l\'électricité au disjoncteur général, prévenir bailleur.</li>'
                        . '<li>Prise qui pétille / fait des étincelles : danger immédiat, couper et appeler.</li>'
                        . '<li>Eau près d\'une installation électrique : ne PAS toucher, couper le courant en premier.</li>'
                        . '</ul>',
                ],
                'diy' => [
                    'titre' => '🧰 Ce que le locataire peut faire',
                    'contenu' => '<p><strong>Réparations locatives possibles :</strong></p>'
                        . '<ul>'
                        . '<li>Remplacement d\'ampoules</li>'
                        . '<li>Remplacement d\'un cache-prise cassé (3 €) : couper le disjoncteur, dévisser, remplacer.</li>'
                        . '<li>Remplacement d\'un interrupteur simple (8 €) : idem, attention bien noter les fils avant.</li>'
                        . '<li>Remise en route après court-circuit ponctuel (débrancher la cause).</li>'
                        . '</ul>'
                        . '<p><strong>À la charge du bailleur :</strong></p>'
                        . '<ul>'
                        . '<li>Tableau électrique non conforme</li>'
                        . '<li>Mise à la terre absente</li>'
                        . '<li>Câblage vétuste, prises sans terre</li>'
                        . '<li>Absence de différentiel</li>'
                        . '<li>Toute installation non conforme NF C 15-100</li>'
                        . '</ul>',
                ],
            ],
        ],

        'bruit' => [
            'icone' => '🔊',
            'titre' => 'Nuisances sonores',
            'sous'  => 'Isolation passive + dialogue',
            'sections' => [
                'materiaux' => [
                    'titre' => '🧪 Matériaux d\'isolation phonique low-cost',
                    'contenu' => '<ul>'
                        . '<li><strong>Joints fenêtres acoustiques</strong> (caoutchouc EPDM) : 15 € (10 m). Casse jusqu\'à 5 dB.</li>'
                        . '<li><strong>Rideaux phoniques épais</strong> molleton : 30-50 €. Très efficace pour bruits aériens.</li>'
                        . '<li>Tapis épais : amortit chocs entre étages.</li>'
                        . '<li>Liège mural (5 mm) à coller : 15 €/m². Casse 18 dB.</li>'
                        . '<li>Bouchons d\'oreille mousse (3 €) pour la nuit immédiate.</li>'
                        . '</ul>',
                ],
                'demarches' => [
                    'titre' => '📋 Démarches',
                    'contenu' => '<ol>'
                        . '<li><strong>Médiation directe :</strong> un mot poli sous la porte, ou échange en personne. Marche dans 60 % des cas.</li>'
                        . '<li><strong>Médiation de quartier :</strong> point conseil quartier mairie de Nantes (gratuit).</li>'
                        . '<li><strong>Tapage nocturne</strong> (22h-7h) : police 17 ou 112. C\'est une contravention.</li>'
                        . '<li><strong>Bruit récurrent diurne</strong> : signalement écrit au bailleur (responsable de la jouissance paisible), avec dates et heures.</li>'
                        . '<li><strong>Constat d\'huissier</strong> : 200-400 €, à utiliser en dernier recours pour preuve devant tribunal.</li>'
                        . '</ol>',
                ],
            ],
        ],

        'parties_communes' => [
            'icone' => '🚪',
            'titre' => 'Parties communes dégradées',
            'sous'  => 'Action collective + LRAR',
            'sections' => [
                'demarches' => [
                    'titre' => '👥 Action collective recommandée',
                    'contenu' => '<ol>'
                        . '<li><strong>Photos datées</strong> de chaque dégradation (boîte aux lettres défoncée, hall sale, ascenseur en panne, parking détérioré, etc.). Dans l\'app : envoyez-nous vos photos.</li>'
                        . '<li><strong>Pétition collective</strong> entre voisins du même immeuble. Plus on est nombreux, plus c\'est efficace.</li>'
                        . '<li><strong>LRAR collective</strong> au bailleur avec liste précise + délai 2 mois + photos.</li>'
                        . '<li>Sans réponse : saisir la <strong>Commission Départementale de Conciliation</strong>.</li>'
                        . '<li>Si insalubre : signalement au <strong>SCHS de la mairie de Nantes</strong>.</li>'
                        . '</ol>'
                        . '<p>Les parties communes sont <strong>à la charge exclusive du bailleur</strong>. Pas besoin de DIY ici — c\'est sa responsabilité, gardez votre énergie pour la pression collective.</p>',
                ],
            ],
        ],

        'securite' => [
            'icone' => '🚨',
            'titre' => 'Insécurité / sentiment d\'insécurité',
            'sous'  => 'Renforcement passif + collectif',
            'sections' => [
                'porte' => [
                    'titre' => '🚪 Sécuriser sa porte',
                    'contenu' => '<ul>'
                        . '<li><strong>Verrou supplémentaire en applique</strong> (Vachette ou ISEO) : 30-60 €. Pose simple, perçage 3 trous.</li>'
                        . '<li><strong>Entrebâilleur</strong> (chaînette renforcée) : 10-15 €. Permet d\'ouvrir sans laisser entrer.</li>'
                        . '<li><strong>Œilleton grand angle</strong> : 5-10 €. Voir qui sonne avant d\'ouvrir.</li>'
                        . '<li>Cale-porte pour la nuit : 5 €.</li>'
                        . '<li>Caméra wifi sonnette (Tapo, Aqara) : 30-60 €. Voir + parler à distance.</li>'
                        . '</ul>',
                ],
                'collectif' => [
                    'titre' => '👥 Action collective',
                    'contenu' => '<ul>'
                        . '<li>Demande de réparation portes communes, interphones, gâches électriques au bailleur (LRAR).</li>'
                        . '<li>Demande d\'éclairage des parties communes et abords.</li>'
                        . '<li>Mobilisation : pétition voisinage, soutien GA.</li>'
                        . '<li>Inscription au registre des plaintes auprès de la mairie / police municipale.</li>'
                        . '</ul>',
                ],
            ],
        ],

        'ascenseur' => [
            'icone' => '🛗',
            'titre' => 'Ascenseur en panne',
            'sous'  => 'C\'est au bailleur. Documenter le préjudice.',
            'sections' => [
                'documenter' => [
                    'titre' => '📋 Documenter pour la pression collective',
                    'contenu' => '<ul>'
                        . '<li>Date de mise en panne</li>'
                        . '<li>Durée totale de l\'arrêt</li>'
                        . '<li>Étage et nombre de personnes à mobilité réduite touchées</li>'
                        . '<li>Photos de l\'affichage hors-service</li>'
                        . '<li>Témoignages (course chez le médecin annulée, enfant porté, etc.)</li>'
                        . '</ul>'
                        . '<p><strong>Recours :</strong> le contrat de maintenance impose des délais maximums. Au-delà : LRAR au bailleur avec demande de réduction de charges. Si discrimination de fait sur les personnes à mobilité réduite : saisine du Défenseur des droits.</p>',
                ],
            ],
        ],
    ];
}

/* ============================================================== *
 *  VUE : Liste des tutoriels                                       *
 *  Accessible aux locataires (auto-dépannage) et à l'admin         *
 * ============================================================== */
function lfi_nct_app_view_tutoriels() {
    $tutos = lfi_nct_tutoriels_all();

    lfi_nct_app_screen_open('🛠 Tutoriels d\'intervention', count($tutos) . ' guides pratiques');

    echo '<div class="lfi-app-help">Pour chaque problème de logement, un guide pratique : outils, matériaux, coût, procédure pas à pas, précautions santé. Cible : intervention locataire ou brigade GA. <strong>Sécurité avant tout</strong> — certaines actions nécessitent un pro.</div>';

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
    $slug = sanitize_key($_GET['t'] ?? '');
    $tutos = lfi_nct_tutoriels_all();
    if (!isset($tutos[$slug])) {
        lfi_nct_app_screen_open('Tutoriel introuvable');
        echo '<div class="lfi-app-empty"><a href="' . esc_url(lfi_nct_app_url('tutoriels')) . '">← Retour à la liste</a></div>';
        lfi_nct_app_screen_close(false);
        return;
    }
    $t = $tutos[$slug];

    lfi_nct_app_screen_open($t['icone'] . ' ' . $t['titre'], $t['sous']);

    echo '<div class="lfi-tutoriel">';
    foreach ($t['sections'] as $key => $sec) {
        echo '<section><h3>' . esc_html($sec['titre']) . '</h3>';
        echo $sec['contenu'];
        echo '</section>';
    }
    echo '</div>';

    echo '<div class="row-actions" style="margin-top:14px">';
    echo '<a class="btn-ghost" href="' . esc_url(lfi_nct_app_url('tutoriels')) . '">← Tous les tutoriels</a>';
    echo '</div>';

    /* CSS du tutoriel */
    ?>
    <style>
    .lfi-tutoriel section { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .lfi-tutoriel section h3 { margin: 0 0 10px; font-size: 1em; color: #c8102e; }
    .lfi-tutoriel section p { margin: 6px 0; line-height: 1.5; font-size: .92em; }
    .lfi-tutoriel section ul, .lfi-tutoriel section ol { margin: 6px 0; padding-left: 1.4em; line-height: 1.6; font-size: .92em; }
    .lfi-tutoriel section li { margin-bottom: 4px; }
    .lfi-tutoriel section strong { color: #1a1a1a; }
    </style>
    <?php

    lfi_nct_app_screen_close(false);
}
