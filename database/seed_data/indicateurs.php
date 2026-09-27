<?php
/**
 * Indicateurs du cadre logique PACT Budgetaire
 * (source : Canevas Rapport/Cadre logique_e3d_logframe_fr_April29_IBPed.docx).
 * Les valeurs de reference / actuelles inconnues sont laissees vides : a renseigner dans la plateforme.
 */
return [
    ['code' => 'I.1', 'niveau' => 'impact', 'libelle' => "Score de redevabilité et de transparence (IIAG scorecard – Côte d'Ivoire)", 'unite' => 'score', 'source_verification' => 'Indice Ibrahim de la gouvernance africaine (IIAG)'],
    ['code' => '1.1', 'niveau' => 'effet', 'libelle' => "Score du contrôle budgétaire dans l'Enquête sur le Budget Ouvert (EBO 2025-2027)", 'unite' => 'score /100', 'source_verification' => 'Rapports EBO (IBP)'],
    ['code' => '1.2', 'niveau' => 'effet', 'libelle' => "Score de la participation citoyenne dans l'EBO (2025-2027)", 'unite' => 'score /100', 'source_verification' => 'Rapports EBO (IBP)'],
    ['code' => '1.3', 'niveau' => 'effet', 'libelle' => 'Score de transparence budgétaire (EBO)', 'unite' => 'score /100', 'source_verification' => 'Rapports EBO (IBP)'],
    ['code' => '1.1.1', 'niveau' => 'produit', 'libelle' => 'Pourcentage des OSC formées (2025-2028)', 'unite' => '%', 'cible' => 75, 'source_verification' => 'Listes de présence, rapports de formation'],
    ['code' => '1.1.2', 'niveau' => 'produit', 'libelle' => "Nombre de bénéficiaires formés en techniques d'analyse et de plaidoyer budgétaires (≥50 % femmes, ≥30 % jeunes, ≥15 % personnes handicapées)", 'unite' => 'personnes', 'valeur_reference' => 0, 'cible' => 300, 'valeur_actuelle' => 30, 'source_verification' => 'Listes de présence, rapports de formation', 'commentaire' => '30 acteurs formés au 30/06/2026 (réseau COST Budget).'],
    ['code' => '2.1.1', 'niveau' => 'produit', 'libelle' => "% d'OSC bénéficiaires participant aux cadres de discussion sur le budget (DOB, auditions parlementaires, sessions RTEB…)", 'unite' => '%', 'cible' => 50, 'source_verification' => 'Comptes rendus, listes de présence'],
    ['code' => '2.1.2', 'niveau' => 'produit', 'libelle' => "Nombre d'initiatives citoyennes pour améliorer l'accès à l'information, la participation et la reddition des comptes", 'unite' => 'initiatives', 'source_verification' => 'Rapports du concours, projets scolaires'],
    ['code' => '3.1.1', 'niveau' => 'produit', 'libelle' => 'Nombre de revues parlementaires avec participation de la société civile', 'unite' => 'revues', 'source_verification' => "Comptes rendus de l'Assemblée nationale"],
    ['code' => '3.1.2', 'niveau' => 'produit', 'libelle' => 'Nombre de rapports publiés', 'unite' => 'rapports', 'source_verification' => 'Publications du projet'],
    ['code' => '3.2.1', 'niveau' => 'produit', 'libelle' => "Nombre d'OSC bénéficiaires participant au calendrier d'audit et de contrôle budgétaire", 'unite' => 'OSC', 'source_verification' => 'Cour des Comptes, mémorandum de collaboration'],
    ['code' => '3.2.3', 'niveau' => 'produit', 'libelle' => 'Nombre de sessions de dialogue et de redevabilité entre OSC, acteurs institutionnels et institutions de contrôle', 'unite' => 'sessions', 'source_verification' => 'Rapports de sessions, listes de présence'],
];
