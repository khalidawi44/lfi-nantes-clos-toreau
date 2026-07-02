# MÉTHODE DE CHIFFRAGE — PRÉJUDICE PUNAISES DE LIT (bailleur social)

> Référence permanente pour le module `prejudice.php` (calculateur des 10 postes).
> Source : dossier pilote DOUCET c/ Nantes Métropole Habitat (NMH), juin 2026.
> Chiffrages obtenus : **151 388 €** (amiable) / **400 000 – 690 000 €** (au fond).
> Objet : documenter la méthode pour la reproduire sur d'autres dossiers locataires.

## 1. Principe
Chiffrage NON subjectif : combinaison de **3 sources externes croisées** →
défendable devant un juge, crédible face au bailleur.
- **A. Expertise scientifique** (ANSES 2019/2020 : 890 €/traitement, 11 % foyers/5 ans,
  83 M€/an ; INSERM ; rapport parlementaire nov. 2023 ; Muséum d'Histoire Naturelle
  de Nantes — F. Meurgey, expertise 17/07/2025).
- **B. Référentiels barémiques** (Mornet 2024, nomenclature Dintilhac, tables CA de Nantes, ONIAM).
- **C. Jurisprudence** (Cass. 3e civ. 11/05/2011 n° 10-30.328 ; Cass. 3e civ. 08/06/2017
  n° 16-16.958 ; TJ Bobigny 15/03/2022 = 45 k€ ; CA Paris 12/01/2023 = 120 k€ enfant handicapé ;
  TJ Marseille 2024 = relogement + 80 k€).

Double objectif : **amiable** = chiffre plancher solide qui incite à transiger ;
**fond** = fourchette large (basse→haute) pour que l'avocat sollicite le haut et négocie vers la moyenne.

## 2. Les 10 postes (formules)
1. **Préjudice corporel** — Mornet + Dintilhac × ANSES. souffrances(cotation/7) + DFT(taux×durée×valeur_jour)
   + agrément(0–60 k) + esthétique(cotation/7). Modulateurs : ×1,2 photos ; ×1,3 arrêts>5 j ;
   ×1,5 ALD/MDPH ; ×1,4 dermatose chronique. DOUCET = 141 077 € (base non modulée en amiable).
2. **Temps perdu / démarches** — années × 833 € × modulateur intensité. DOUCET = 5 000 €.
3. **Jouissance rétroactif** — loyer × mois × coef dégradation (0,20 modérée / 0,40 sévère /
   0,70 majeure / 1,00 privation totale). Plafond amiable 5 000 € ; fond jusqu'à 50 000 €. DOUCET = 4 838 €.
4. **Literie/textiles détruits** — sur factures, sinon barème (lit adulte 450, enfant 250, sommier 200,
   matelas simple 300 / double 500, textiles 100/pers, vêtements 200/pers) ; dépréciation 10 %/an, plancher 30 %.
   DOUCET = 696,97 € (factures).
5. **Produits / frais annexes** — factures, sinon forfait 100 €/an. DOUCET = 198,51 €.
6. **Moral familial aggravé** — membres × 5 000 € × mineur(×1,5) × MDPH/ALD(×2,5) × départ(×1,8).
   Réservé au fond. DOUCET fond = 50 000–80 000 €.
7. **Scolaire des enfants** — enfants scolarisés × 5 000 € × arrêt(×2) × décrochage(×3 à ×5) × redoublement(×2).
   Fond. DOUCET = 25 000–50 000 €.
8. **Médical spécifique** — anxiété/insomnie 3–8 k ; dermatose chronique 5–15 k ; aggravation ALD/MDPH
   jusqu'à 100 k ; précancéreux (Barrett) jusqu'à 100 k. Fond. DOUCET = 50 000–100 000 €.
9. **Diffamation** (le cas échéant) — courriels × 5 000 € × public(×2) × récidive(×1,5).
   Attention : communauté d'intérêts entre destinataires = non publique (contravention). DOUCET = 10–30 k.
10. **Contrainte à signature** (art. 1143 C. civ. — violence économique) — engagements × 20 000 €
    × systémique(×2 à ×2,5) + montant récupérable. DOUCET = 20–50 k.

## 3. Sortie double
- **Amiable** = postes 1 à 5 (les plus solides/documentés), postes 6–10 réservés. DOUCET = 151 388 €.
- **Fond** = les 10 postes + astreinte. DOUCET = 400 000–690 000 €.

## 4. Transposition (paramètres locataire)
durée_exposition, personnes_foyer, adultes, enfants_mineurs, enfants_MDPH, personnes_ALD,
loyer_mensuel, coef_dégradation, pièces disponibles (photos horodatées, certificats, arrêts,
factures, courriels diffamatoires, engagements sous contrainte, PV hygiène).

## 5. Pièces qui renforcent
Baseline : photos horodatées (EXIF), constat huissier / PV SCHS, certificats médicaux, factures.
À obtenir : PV SCHS (mairie), expertise entomologique (Muséum), mises en demeure mairie→bailleur,
arrêts de travail, attestations voisins. Amplificateurs : MDPH, ALD, décrochage scolaire attesté,
correspondance diffamatoire, engagements signés sous contrainte.

## 6. Règles d'implémentation
- Amiable : ne mobiliser que les postes solides (ajouter du contestable dilue).
- Fond : tous les postes, hiérarchisés (solides d'abord), en **fourchette** (min–max).
- Chaque poste du rapport : montant + formule + source + pièces + contestation prévisible + réponse.
- Actualisation annuelle des barèmes (Mornet, jurisprudence).
- Tous les montants sont des **ordres de grandeur à valider avec l'avocat** — pas un avis juridique.
