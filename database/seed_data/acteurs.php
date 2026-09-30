<?php
/**
 * Referentiel des acteurs institutionnels lies au projet PACT Budgetaire.
 * Les sites ont ete verifies en ligne ; « actualites » est la page (ou le flux) utilisee pour la veille.
 * destinataire / appel : formules protocolaires reprises dans les courriers rediges par l'IA.
 */
return [
    'dgbf' => [
        'sigle' => 'DGBF', 'nom' => 'Direction Générale du Budget et des Finances', 'categorie' => 'Administration financière',
        'role' => "Élabore, exécute et contrôle le budget de l'État ; pilote le DPBEP, le PLF, les rapports d'exécution (RTEB), le référentiel de publication et le portail E-Budget Citoyen. Interlocuteur central du Résultat 2.",
        'site' => 'https://www.dgbf.ci/', 'actualites' => 'https://www.dgbf.ci/actualites/', 'flux' => 'https://www.dgbf.ci/feed/',
        'destinataire' => 'Monsieur le Directeur Général du Budget et des Finances', 'appel' => 'Monsieur le Directeur Général',
    ],
    'mfb' => [
        'sigle' => 'MFB', 'nom' => 'Ministère des Finances et du Budget', 'categorie' => 'Ministère',
        'role' => "Tutelle de la DGBF ; arbitrages budgétaires, politique de transparence et de réforme des finances publiques. Point d'entrée pour les ateliers d'échange sur la budgétisation sensible au genre.",
        'site' => 'https://finances.gouv.ci/', 'actualites' => 'https://finances.gouv.ci/actualites',
        'destinataire' => 'Monsieur le Ministre des Finances et du Budget', 'appel' => 'Monsieur le Ministre',
    ],
    'pprc' => [
        'sigle' => 'PPRC', 'nom' => 'Programme-Pays de Renforcement des Capacités', 'categorie' => 'Programme public',
        'role' => "Structure d'appui à la gouvernance et à la transparence budgétaire (appui de la Banque mondiale via le PAGDS) ; partenaire de synergie pour la participation citoyenne au processus budgétaire et le Partenariat pour un Gouvernement Ouvert.",
        'site' => 'http://www.pprc.gouv.ci/', 'actualites' => 'http://www.pprc.gouv.ci/',
        'destinataire' => 'Monsieur le Coordonnateur du Programme-Pays de Renforcement des Capacités (PPRC)', 'appel' => 'Monsieur le Coordonnateur',
    ],
    'ctogp' => [
        'sigle' => 'CT OGP', 'nom' => 'Comité Technique du Partenariat pour un Gouvernement Ouvert (OGP) Côte d\'Ivoire', 'categorie' => 'Cadre institutionnel',
        'role' => "Cadre paritaire administration / société civile / secteur privé chargé du Plan d'action national OGP (PAN 5, 2024-2026). Relais des engagements de transparence et de participation citoyenne.",
        'site' => 'https://ogp.gouv.ci/', 'actualites' => 'https://ogp.gouv.ci/category/actualite/', 'flux' => 'https://ogp.gouv.ci/feed/',
        'destinataire' => 'Madame/Monsieur le Président du Comité Technique OGP Côte d\'Ivoire', 'appel' => 'Madame/Monsieur le Président',
    ],
    'ue' => [
        'sigle' => 'UE', 'nom' => 'Délégation de l\'Union européenne en Côte d\'Ivoire', 'categorie' => 'Bailleur',
        'role' => "Bailleur du projet : validation des livrables, des rapports narratifs et financiers, règles de visibilité. Soutien institutionnel auprès de la DGBF et de la Cour des Comptes.",
        'site' => 'https://www.eeas.europa.eu/delegations/cote-divoire_fr', 'actualites' => 'https://www.eeas.europa.eu/delegations/cote-divoire_fr',
        'destinataire' => 'Son Excellence Madame/Monsieur l\'Ambassadeur, Chef de la Délégation de l\'Union européenne en Côte d\'Ivoire', 'appel' => 'Excellence',
    ],
    'cellule_ue' => [
        'sigle' => 'Cellule CI-UE', 'nom' => 'Cellule de coordination de la coopération Côte d\'Ivoire – Union européenne', 'categorie' => 'Coordination',
        'role' => "Interface nationale de la coopération avec l'UE ; sa validation conditionne la transmission de certains courriers et mémorandums (ex. mémorandum Cour des Comptes).",
        'site' => '', 'actualites' => '',
        'destinataire' => 'Monsieur le Coordonnateur de la Cellule de coordination Côte d\'Ivoire – Union européenne', 'appel' => 'Monsieur le Coordonnateur',
    ],
    'an' => [
        'sigle' => 'Assemblée nationale', 'nom' => 'Assemblée nationale de Côte d\'Ivoire (Commission des Affaires Économiques et Financières – CAEF)', 'categorie' => 'Institution',
        'role' => "Vote la loi de finances et contrôle l'exécution du budget : débat d'orientation budgétaire (DOB), auditions sur les RTEB. Interlocuteur du Résultat 3 via la CAEF.",
        'site' => 'https://www.assnat.ci/', 'actualites' => 'https://www.assnat.ci/',
        'destinataire' => 'Monsieur le Président de l\'Assemblée nationale', 'appel' => 'Monsieur le Président',
    ],
    'caef' => [
        'sigle' => 'CAEF', 'nom' => 'Commission des Affaires Économiques et Financières de l\'Assemblée nationale', 'categorie' => 'Institution',
        'role' => "Examine le projet de loi de finances, le DPBEP et les rapports d'exécution budgétaire ; membre du COPIL du projet depuis mars 2026.",
        'site' => 'https://www.assnat.ci/', 'actualites' => 'https://www.assnat.ci/',
        'destinataire' => 'Monsieur le Président de la Commission des Affaires Économiques et Financières de l\'Assemblée nationale', 'appel' => 'Monsieur le Président',
    ],
    'cdc' => [
        'sigle' => 'Cour des Comptes', 'nom' => 'Cour des Comptes de Côte d\'Ivoire', 'categorie' => 'Institution supérieure de contrôle',
        'role' => "Juridiction supérieure de contrôle des finances publiques : rapports d'audit, calendrier des audits, indépendance et exhaustivité des rapports. Partenaire du Résultat 3.",
        'site' => 'https://www.courdescomptes.ci/', 'actualites' => 'https://www.courdescomptes.ci/_publications.php',
        'destinataire' => 'Monsieur le Premier Président de la Cour des Comptes', 'appel' => 'Monsieur le Premier Président',
    ],
    'mffe' => [
        'sigle' => 'MFFE', 'nom' => 'Ministère de la Femme, de la Famille et de l\'Enfant', 'categorie' => 'Ministère',
        'role' => "Point focal attendu pour la budgétisation sensible au genre (BSG) ; désignation officielle du point focal encore à obtenir.",
        'site' => 'https://www.famille.gouv.ci/', 'actualites' => 'https://www.famille.gouv.ci/',
        'destinataire' => 'Madame la Ministre de la Femme, de la Famille et de l\'Enfant', 'appel' => 'Madame la Ministre',
    ],
    'men' => [
        'sigle' => 'MENA', 'nom' => 'Ministère de l\'Éducation nationale', 'categorie' => 'Ministère',
        'role' => "Autorisation et relais pour la caravane d'information et le concours national de la transparence budgétaire dans les établissements scolaires.",
        'site' => 'https://www.education.gouv.ci/', 'actualites' => 'https://www.education.gouv.ci/',
        'destinataire' => 'Madame/Monsieur le Ministre de l\'Éducation nationale', 'appel' => 'Madame/Monsieur le Ministre',
    ],
    'mesrs' => [
        'sigle' => 'MESRS', 'nom' => 'Ministère de l\'Enseignement supérieur et de la Recherche scientifique', 'categorie' => 'Ministère',
        'role' => "Autorisation et relais pour la caravane, le concours et le Prix de la transparence budgétaire dans les universités et grandes écoles.",
        'site' => 'https://www.enseignement.gouv.ci/accueil', 'actualites' => 'https://www.enseignement.gouv.ci/accueil',
        'destinataire' => 'Monsieur le Ministre de l\'Enseignement supérieur et de la Recherche scientifique', 'appel' => 'Monsieur le Ministre',
    ],
    'primature' => [
        'sigle' => 'Primature', 'nom' => 'Cabinet du Premier Ministre', 'categorie' => 'Gouvernement',
        'role' => "Coordination de l'action gouvernementale ; relais de haut niveau pour les réformes de gouvernance et de transparence.",
        'site' => 'https://www.primature.ci/', 'actualites' => 'https://www.primature.ci/',
        'destinataire' => 'Monsieur le Premier Ministre, Chef du Gouvernement', 'appel' => 'Monsieur le Premier Ministre',
    ],
    'gouv' => [
        'sigle' => 'Gouvernement', 'nom' => 'Portail officiel du Gouvernement de Côte d\'Ivoire', 'categorie' => 'Gouvernement',
        'role' => "Actualités gouvernementales, conseils des ministres, textes et communications officielles.",
        'site' => 'https://www.gouv.ci/', 'actualites' => 'https://www.gouv.ci/',
        'destinataire' => '', 'appel' => '',
    ],
    'rti' => [
        'sigle' => 'RTI', 'nom' => 'Radiodiffusion Télévision Ivoirienne', 'categorie' => 'Média public',
        'role' => "Diffuseur public : convention média et émissions sur les finances publiques ; diffusion des capsules vidéo budgétaires.",
        'site' => 'https://www.rti.ci/', 'actualites' => 'https://www.rti.ci/',
        'destinataire' => 'Monsieur le Directeur Général de la Radiodiffusion Télévision Ivoirienne (RTI)', 'appel' => 'Monsieur le Directeur Général',
    ],
    'lifetv' => [
        'sigle' => 'Life TV', 'nom' => 'Life TV', 'categorie' => 'Média privé',
        'role' => "Partenaire média pour la diversification de la diffusion (éviter la dépendance à un diffuseur unique).",
        'site' => 'https://lifetv.ci/', 'actualites' => 'https://lifetv.ci/', 'flux' => 'https://lifetv.ci/feed/',
        'destinataire' => 'Monsieur le Directeur Général de Life TV', 'appel' => 'Monsieur le Directeur Général',
    ],
];
