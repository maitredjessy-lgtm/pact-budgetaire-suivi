<?php
/**
 * Modeles de documents du projet PACT Budgetaire, reconstruits a partir des canevas
 * reels du dossier projet (Canevas Rapport/, TDR/, COURRIER/, RAPPORTS/).
 * Les {{variables}} sont remplies automatiquement depuis l'activite liee ;
 * l'IA complete le reste a partir des informations fournies par l'utilisateur.
 */

$entete = <<<MD
**PROJET « PACT BUDGÉTAIRE »** — Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire
Financé par l'Union européenne — Mis en œuvre par Social Justice et IBP Sénégal
MD;

return [
    [
        'type' => 'rapport',
        'nom' => "Rapport d'activité (canevas PACT Budgétaire)",
        'description' => "Canevas officiel de rapport d'une activité (atelier, formation, séminaire) : fiche synoptique, objectifs, déroulement, communication, recommandations, annexes.",
        'source' => 'Canevas Rapport/Modele_Rapport Activité PACT BUGETAIRE.docx',
        'consignes' => "Rédiger au passé, style administratif et factuel. Replacer l'activité dans son contexte (composante du plan de travail). Pour une formation, rapporter jour par jour. Mettre en valeur les résultats obtenus par rapport aux résultats attendus des TDR, les messages clés et témoignages (en encadré/citation), les recommandations et prochaines étapes. Ne jamais inventer de chiffres : laisser [À compléter] si l'information manque.",
        'contenu' => $entete . <<<MD


# RAPPORT D'ACTIVITÉ
## {{titre_activite}}

**Lieu et date de l'activité :** {{lieu}}, {{date_activite}}
**Numéro de l'activité dans l'action :** {{code_activite}}

## FICHE SYNOPTIQUE DE L'ACTIVITÉ

| Rubrique | Détail |
|---|---|
| Activité (titre et numéro) | {{titre_activite}} ({{code_activite}}) |
| Indicateurs visés (cadre logique) | [À compléter] |
| Participants | Hommes : … / Femmes : … / Jeunes : … |
| Lieu | {{lieu}} |
| Durée (nombre de jours) | [À compléter] |
| Parties prenantes | [À compléter] |
| Principaux résultats obtenus | [À compléter] |
| Principales recommandations | [À compléter] |

## 1. Introduction
Contexte de l'activité, nom, date, lieu, lien avec la composante « {{composante}} » du plan de travail, profil des intervenants et facilitateurs.

## 2. Rappel des objectifs et résultats attendus
### 2.1 Objectifs de l'activité
Objectif général et objectifs spécifiques (repris des TDR).
### 2.2 Résultats produits par l'activité
Indicateur dédié, résultats immédiats atteints (prévus dans les TDR ou non prévus).

## 3. Méthodologie
Méthodologie utilisée, choix des cibles, format de l'activité.

## 4. Déroulement de l'activité
Étapes, interventions (jour par jour pour une formation), résultats obtenus par résultat attendu, éléments marquants, témoignages et messages clés.

## 5. Communication
Retours des bénéficiaires, actions de visibilité, couverture médiatique, lien vers la médiathèque.

## 6. Conclusion et recommandations
Conclusions, recommandations formulées, prochaines étapes, leçons apprises.

## 7. Annexes
- Termes de référence
- Agenda validé
- Feuille de présence et fiche de presse (scannées)
- Photos illustratives
- Supports de présentation
- Recueil des citations des participants
MD,
    ],
    [
        'type' => 'rapport',
        'nom' => 'Rapport narratif intermédiaire UE (Annexe VI)',
        'description' => "Format officiel de l'Union européenne pour le rapport narratif intermédiaire : description, résumé de l'action, résultats (impact, effets, produits), activités, cadre logique à jour.",
        'source' => 'Canevas Rapport/Modèle de rapport narratif intermediaire UE CI.docx',
        'consignes' => "Respecter strictement la structure de l'Annexe VI de l'UE. Répondre à chaque question pour la période de référence. En cas de contre-performance, expliquer les raisons et les mesures correctives. S'appuyer sur les données du projet (activités réalisées, indicateurs) fournies en contexte. Laisser [À compléter] pour toute donnée manquante.",
        'contenu' => <<<MD
# ANNEXE VI — RAPPORT NARRATIF INTERMÉDIAIRE

**Intitulé de l'action :** PACT BUDGÉTAIRE — Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire
**Période de référence :** {{periode}}

## Liste des acronymes
| Sigle | Signification |
|---|---|
| CAEF | Commission des Affaires Économiques et Financières |
| DGBF | Direction Générale du Budget et des Finances |
| EBO | Enquête sur le Budget Ouvert |
| IBP | International Budget Partnership |
| OSC | Organisations de la Société Civile |
| RTEB | Rapports Trimestriels d'Exécution du Budget |
| SJ | Social Justice |

## 1. Description
1.1 Nom du coordonnateur du contrat de subvention : Social Justice
1.2 Nom et fonction de la personne de contact : [À compléter]
1.3 Bénéficiaire(s) et entité(s) affiliée(s) : Social Justice, IBP Sénégal
1.4 Intitulé de l'action : PACT BUDGÉTAIRE
1.5 Numéro du contrat : [À compléter]
1.6 Période de référence : {{periode}}
1.7 Pays cible : Côte d'Ivoire
1.8 Bénéficiaires finaux et groupes cibles (nombre de femmes et d'hommes) : [À compléter]

## 2. Résumé de l'action
Aperçu global de la mise en œuvre pendant la période de référence (½ page maximum).

## 3. Résultats et activités
### A. Résultats (impact, effets et produits)
#### Impact
#### Effet 1
#### Produit 1.1
### B. Activités
Pour chaque activité : état d'avancement, résultats, difficultés, mesures correctives.

## 4. Matrice de cadre logique à jour
| Chaîne des résultats | Indicateur | Référence | Cible | Valeur actuelle | Source |
|---|---|---|---|---|---|

## 5. Difficultés rencontrées et mesures correctives

## 6. Visibilité et communication

## 7. Plan de travail pour la période suivante
MD,
    ],
    [
        'type' => 'rapport',
        'nom' => 'Rapport narratif final UE (Annexe VI)',
        'description' => "Format UE du rapport narratif final couvrant toute la durée de l'action.",
        'source' => 'Canevas Rapport/Modèle de rapport  final PACT BUGETAIRE.docx',
        'consignes' => "Structure Annexe VI UE, rapport final : couvrir toute la durée du projet, mettre l'accent sur l'impact, la durabilité et les leçons apprises. Ne pas inventer de données.",
        'contenu' => <<<MD
# ANNEXE VI — RAPPORT NARRATIF FINAL

**Intitulé de l'action :** PACT BUDGÉTAIRE
**Date de commencement et date de fin de l'action :** {{periode}}

## 1. Description
1.1 Coordonnateur : Social Justice
1.2 Personne de contact : [À compléter]
1.3 Bénéficiaires et entités affiliées : Social Justice, IBP Sénégal
1.5 Numéro du contrat : [À compléter]
1.7 Pays cible : Côte d'Ivoire
1.8 Bénéficiaires finaux et groupes cibles : [À compléter]

## 2. Résumé de l'action

## 3. Résultats et activités
### A. Résultats (impact, effets et produits)
### B. Activités

## 4. Matrice de cadre logique finale
| Chaîne des résultats | Indicateur | Référence | Cible | Valeur finale | Source |
|---|---|---|---|---|---|

## 5. Durabilité et pérennisation des acquis

## 6. Leçons apprises et bonnes pratiques

## 7. Visibilité de l'Union européenne
MD,
    ],
    [
        'type' => 'rapport',
        'nom' => 'Bilan trimestriel / semestriel',
        'description' => 'Bilan périodique de mise en œuvre : activités réalisées, en cours, non réalisées, difficultés, perspectives.',
        'source' => 'RAPPORTS/BILAN_TRIMESTRIEL_PACT_BUDGETAIRE_Mars-Mai_2026.docx',
        'consignes' => "Utiliser les données du projet fournies (activités, statuts, échéances). Présenter des tableaux clairs. Expliquer les retards et proposer un plan de rattrapage réaliste.",
        'contenu' => $entete . <<<MD


# BILAN DE MISE EN ŒUVRE — {{periode}}

## 1. Synthèse
Taux de réalisation global, faits marquants de la période.

## 2. Activités réalisées
| Code | Activité | Date | Résultats clés |
|---|---|---|---|

## 3. Activités en cours
| Code | Activité | Avancement | Échéance | Responsable |
|---|---|---|---|---|

## 4. Activités non réalisées ou reportées
| Code | Activité | Raisons | Nouvelle échéance |
|---|---|---|---|

## 5. Difficultés rencontrées et mesures correctives

## 6. Perspectives et priorités de la période suivante
MD,
    ],
    [
        'type' => 'compte_rendu',
        'nom' => "Compte rendu de réunion / d'audience",
        'description' => "Compte rendu d'une réunion de travail ou d'une audience institutionnelle (Cour des Comptes, DGBF, CAEF, UE…).",
        'source' => 'RAPPORTS/Compte rendu rencontre Cour des Comptes - juillet 2026.docx',
        'consignes' => "Style synthétique et neutre. Distinguer clairement positions exprimées, décisions prises et actions à mener (tableau Action / Responsable / Échéance).",
        'contenu' => <<<MD
# COMPTE RENDU DE RÉUNION
## {{titre_activite}}
Social Justice / International Budget Partnership (IBP) — Projet PACT Budgétaire

| | |
|---|---|
| Objet | {{objet}} |
| Date | {{date_activite}} |
| Lieu | {{lieu}} |
| Participants | [À compléter] |

## I. Contexte et objectifs

## II. Thématiques discutées

## III. Décisions et engagements

## IV. Implications pour le projet PACT Budgétaire

## V. Prochaines étapes
| Action | Responsable(s) | Échéance |
|---|---|---|

## VI. Commentaires / informations complémentaires

Rédigé par : {{redacteur}} — Date : {{date_du_jour}}
MD,
    ],
    [
        'type' => 'tdr',
        'nom' => "TDR d'atelier / de formation",
        'description' => "Termes de référence d'un atelier (contexte, objectifs, résultats attendus, méthodologie, participants, programme, livrables, suivi-évaluation).",
        'source' => 'TDR/TDR ATELIER DE REDINAMISATION DE COST BUDGET.docx',
        'consignes' => "Rédiger un contexte et une justification solides reliés aux enjeux de transparence budgétaire en Côte d'Ivoire (EBO, cycle budgétaire, rôle des OSC). Objectifs SMART, résultats attendus mesurables, programme horaire réaliste avec pauses, liste de livrables.",
        'contenu' => <<<MD
# TERMES DE RÉFÉRENCE (TDR)
## {{titre_activite}}
Dans le cadre du projet PACT Budgétaire — Activité {{code_activite}}

## 1. Contexte et justification

## 2. Objectif général

## 3. Objectifs spécifiques

## 4. Résultats attendus

## 5. Méthodologie

## 6. Durée et lieu
- Durée : [À préciser]
- Date : {{date_activite}}
- Lieu : {{lieu}}

## 7. Participants
| Structure | Nombre de participants |
|---|---|

## 8. Programme provisoire
| Horaire | Activités | Intervenants |
|---|---|---|

## 9. Livrables

## 10. Suivi et évaluation

## 11. Budget indicatif
| Rubrique | Quantité | Coût unitaire (FCFA) | Total (FCFA) |
|---|---|---|---|
MD,
    ],
    [
        'type' => 'tdr',
        'nom' => "TDR de session d'échanges institutionnelle",
        'description' => "TDR d'une session d'échanges entre la société civile et une institution (DGBF, Assemblée nationale, Cour des Comptes) — ex. sessions RTEB, DPBEP.",
        'source' => 'TDR/TDR_SousActivite_2.3.3_Premiere_Session_RTEB_revise.docx',
        'consignes' => "Ton institutionnel et diplomatique. Articuler avec les indicateurs de l'Enquête sur le Budget Ouvert (participation du public, contrôle). Préciser le rôle de chaque acteur et le mécanisme de retour d'information.",
        'contenu' => $entete . <<<MD


# TERMES DE RÉFÉRENCE
## {{titre_activite}}
Sous-activité {{code_activite}} — Lieu : {{lieu}} — Date : {{date_activite}}

## 1. Contexte et justification

## 2. Thématique et ancrage stratégique
### 2.1 Thématique commune
### 2.2 Articulation avec les indicateurs de l'Enquête sur le Budget Ouvert

## 3. Objectifs
### 3.1 Objectif général
### 3.2 Objectifs spécifiques

## 4. Résultats attendus

## 5. Participants

## 6. Méthodologie

## 7. Agenda indicatif
| Heure | Activités | Personnes ressources |
|---|---|---|

## 8. Livrables attendus

## 9. Calendrier et documents de référence

## 10. Responsabilités et portage
MD,
    ],
    [
        'type' => 'courrier',
        'nom' => "Courrier d'invitation (OSC, médias, partenaires)",
        'description' => "Lettre d'invitation à un atelier ou une cérémonie du projet.",
        'source' => 'COURRIER/OSC/Courrier FENOCI.docx',
        'consignes' => "Lettre administrative ivoirienne : référence, lieu et date, destinataire, objet, formule d'appel adaptée au titre du destinataire, corps en 3-4 paragraphes (contexte du projet, objet de l'invitation avec date/heure/lieu, contacts), formule de politesse. Contacts : 0769634062 — socialjustice.ci@gmail.com.",
        'contenu' => <<<MD
REF Nº : {{reference}}

Abidjan, le {{date_du_jour}}

À {{destinataire}}

**OBJET : Invitation — {{titre_activite}}**

{{formule_appel}},

Dans le cadre de l'appui à la consolidation de la transparence budgétaire en Côte d'Ivoire, l'ONG SOCIAL JUSTICE met en œuvre, en partenariat avec IBP Sénégal, le projet « Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire » (PACT BUDGÉTAIRE), financé par l'Union européenne.

[Paragraphe présentant l'activité, sa date, son heure et son lieu : {{date_activite}}, {{lieu}}]

Nous sommes joignables par téléphone au 0769634062 et par e-mail à l'adresse socialjustice.ci@gmail.com pour tout besoin d'information complémentaire.

Dans l'attente d'une suite favorable, nous vous prions d'agréer, {{formule_appel}}, l'expression de notre parfaite considération.

P/O LA SECRÉTAIRE GÉNÉRALE
MD,
    ],
    [
        'type' => 'courrier',
        'nom' => "Courrier de demande d'audience / séance de travail",
        'description' => "Lettre à une institution (CAEF, DGBF, Cour des Comptes, ministère) pour solliciter une audience ou une séance de travail.",
        'source' => 'COURRIER/STRUCTURE ETATIQUE ET PARTENAIRES/Courrier_CAEF_Demande_Seance_Travail_PACT_BUDGETAIRE.docx',
        'consignes' => "Ton très protocolaire et diplomatique. Rappeler les échanges antérieurs, proposer des points d'ordre du jour en tirets, se mettre à disposition de l'agenda du destinataire. Formule finale : 'l'assurance de notre très haute considération'.",
        'contenu' => <<<MD
REF Nº : {{reference}}

Abidjan, le {{date_du_jour}}

À {{destinataire}}

**OBJET : {{objet}}**

{{formule_appel}},

Dans le cadre de la mise en œuvre du projet « PACT BUDGÉTAIRE » – Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire – financé par l'Union européenne et mis en œuvre par l'ONG Social Justice en partenariat avec IBP Sénégal, [contexte et rappel des échanges antérieurs].

La séance de travail que nous appelons de nos vœux pourrait, sous réserve de votre appréciation, s'articuler autour des points suivants :
- [point 1]
- [point 2]
- [point 3]

Soucieux de nous conformer à vos contraintes d'agenda, nous nous tenons à votre entière disposition pour toute date qu'il vous plaira de fixer.

Nos services demeurent joignables au 0769634062 ainsi qu'à l'adresse socialjustice.ci@gmail.com.

Tout en vous renouvelant l'expression de notre gratitude, nous vous prions d'agréer, {{formule_appel}}, l'assurance de notre très haute considération.

P/O LA SECRÉTAIRE GÉNÉRALE
MD,
    ],
    [
        'type' => 'communique',
        'nom' => 'Communiqué de presse',
        'description' => "Communiqué de presse annonçant ou restituant une activité du projet.",
        'source' => 'Communiqué de presse_EBO CIV 2025_recadré.docx',
        'consignes' => "Titre accrocheur, chapeau qui répond à qui/quoi/quand/où/pourquoi, 3-4 paragraphes, une citation d'un responsable, encadré 'À propos' de Social Justice, IBP et du projet. Mentionner le financement de l'Union européenne.",
        'contenu' => <<<MD
# COMMUNIQUÉ DE PRESSE
Abidjan, le {{date_du_jour}}

## {{titre_activite}}

**[Chapeau : qui, quoi, quand, où, pourquoi]**

[Corps du communiqué]

> « [Citation d'un responsable] »

### À propos du projet PACT BUDGÉTAIRE
Le projet « Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire », financé par l'Union européenne, est mis en œuvre par Social Justice et IBP Sénégal.

**Contact presse :** Social Justice — 0769634062 — socialjustice.ci@gmail.com
MD,
    ],
    [
        'type' => 'note',
        'nom' => 'Note de synthèse / note de plaidoyer',
        'description' => 'Note courte (2-3 pages) de synthèse ou de plaidoyer budgétaire à destination des décideurs.',
        'source' => 'synthese_plaidoyer_budgetaire_3pages.docx',
        'consignes' => "Style percutant et argumenté, orienté décideurs. Constats chiffrés (uniquement si fournis), analyse, recommandations numérotées et actionnables, avec destinataire de chaque recommandation.",
        'contenu' => <<<MD
# NOTE DE {{objet}}
Projet PACT Budgétaire — {{date_du_jour}}

## Résumé

## 1. Contexte

## 2. Constats clés

## 3. Analyse

## 4. Recommandations
| # | Recommandation | Destinataire | Horizon |
|---|---|---|---|

## 5. Conclusion
MD,
    ],
    [
        'type' => 'rapport',
        'nom' => 'Rapport de suivi-évaluation (indicateurs)',
        'description' => "Rapport d'évaluation de la performance : état des indicateurs du cadre logique, taux de réalisation, analyse des écarts, recommandations.",
        'source' => 'SUIVI ET EVALUATION .xlsx / Cadre logique',
        'consignes' => "S'appuyer exclusivement sur les indicateurs et activités fournis en contexte. Calculer les taux de réalisation (valeur actuelle / cible). Analyser les écarts et proposer des mesures correctives. Appliquer les critères d'évaluation CAD-OCDE (pertinence, cohérence, efficacité, efficience, impact, durabilité).",
        'contenu' => $entete . <<<MD


# RAPPORT DE SUIVI-ÉVALUATION — {{periode}}

## 1. Résumé exécutif

## 2. État d'exécution des activités
| Statut | Nombre | % |
|---|---|---|

## 3. État des indicateurs du cadre logique
| Niveau | Indicateur | Référence | Cible | Valeur actuelle | Taux de réalisation |
|---|---|---|---|---|---|

## 4. Analyse selon les critères CAD-OCDE
### 4.1 Pertinence
### 4.2 Cohérence
### 4.3 Efficacité
### 4.4 Efficience
### 4.5 Impact (tendances)
### 4.6 Durabilité

## 5. Écarts, risques et mesures correctives

## 6. Recommandations
MD,
    ],
];
