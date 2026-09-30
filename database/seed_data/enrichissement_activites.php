<?php
/**
 * Objectif, resultat(s) attendu(s) et acteurs institutionnels de chaque activite du plan de travail.
 * Cle = titre exact de l'activite. « acteurs » = cles du referentiel acteurs.php.
 * Ces valeurs ne sont appliquees qu'aux champs encore vides (les modifications de l'equipe sont conservees).
 */
return [
    "Atelier de lancement officiel du projet PACT Budgétaire" => [
        'objectif' => "Présenter officiellement le projet PACT Budgétaire aux institutions, à la société civile et aux médias, et obtenir leur adhésion.",
        'resultats' => "Projet connu et approprié par les parties prenantes ; partenaires institutionnels identifiés et engagés ; calendrier de mise en œuvre partagé.",
        'acteurs' => ['ue', 'dgbf', 'mfb', 'an', 'cdc', 'ctogp'],
    ],
    "Première réunion du Comité de Pilotage (COPIL)" => [
        'objectif' => "Installer le dispositif de gouvernance du projet et valider les orientations stratégiques et le plan de travail.",
        'resultats' => "COPIL installé ; CAEF formellement intégrée ; décisions et recommandations consignées dans un compte rendu.",
        'acteurs' => ['ue', 'dgbf', 'an', 'caef', 'pprc'],
    ],
    "Deuxième réunion du Comité de Pilotage (COPIL)" => [
        'objectif' => "Faire le point sur l'exécution de l'Année 2, arbitrer les difficultés et valider les ajustements du plan de travail.",
        'resultats' => "Décisions de pilotage prises ; blocages levés (mémorandum Cour des Comptes, cadre DGBF) ; calendrier révisé validé.",
        'acteurs' => ['ue', 'dgbf', 'mfb', 'an', 'caef', 'pprc', 'cellule_ue'],
    ],
    "Atelier annuel de revue et notes d'apprentissage" => [
        'objectif' => "Capitaliser les acquis et les leçons de l'année écoulée pour ajuster la stratégie du projet.",
        'resultats' => "Notes d'apprentissage produites ; recommandations d'amélioration intégrées au plan de travail suivant.",
        'acteurs' => ['ue', 'dgbf', 'pprc', 'ctogp'],
    ],
    "Réunion de suivi semestrielle du projet" => [
        'objectif' => "Assurer le suivi régulier de l'avancement du projet avec le bailleur et les partenaires.",
        'resultats' => "Point d'avancement partagé ; risques et actions correctives documentés.",
        'acteurs' => ['ue'],
    ],
    "Atelier de présentation de la méthodologie de cartographie des OSC" => [
        'objectif' => "Faire valider par les acteurs concernés la méthodologie de cartographie des OSC intervenant sur les finances publiques.",
        'resultats' => "Méthodologie présentée et validée ; OSC informées et mobilisées pour la collecte.",
        'acteurs' => ['ue', 'dgbf', 'pprc'],
    ],
    "Atelier de présentation des résultats de la cartographie des OSC" => [
        'objectif' => "Restituer les résultats de la cartographie des OSC sectorielles et identifier les besoins de renforcement.",
        'resultats' => "Répertoire des OSC disponible ; besoins de capacités identifiés ; base du réseau COST Budget consolidée.",
        'acteurs' => ['ue', 'dgbf', 'pprc'],
    ],
    "Atelier de redynamisation et de formation du réseau COST Budget" => [
        'objectif' => "Relancer le réseau COST Budget et renforcer les capacités de ses OSC membres sur le suivi budgétaire.",
        'resultats' => "30 OSC du réseau cartographiées et redynamisées ; feuille de route du réseau adoptée.",
        'acteurs' => ['dgbf', 'pprc'],
    ],
    "Déploiement du cycle de formation des OSC / médias (5 modules — réseau COST Budget)" => [
        'objectif' => "Former les OSC et les médias à la lecture, à l'analyse et au suivi du budget de l'État.",
        'resultats' => "300 acteurs formés (cible) sur 5 modules ; OSC et journalistes capables d'analyser les documents budgétaires.",
        'acteurs' => ['dgbf', 'mfb', 'pprc', 'rti', 'lifetv'],
    ],
    "Ateliers d'échange MFB / MFFE sur la budgétisation sensible au genre (BSG)" => [
        'objectif' => "Promouvoir la prise en compte du genre dans le budget de l'État par un dialogue entre le MFB, le MFFE et la société civile.",
        'resultats' => "Point focal MFFE officiellement désigné ; pistes d'intégration de la BSG dans le cycle budgétaire convenues.",
        'acteurs' => ['mfb', 'dgbf', 'mffe'],
    ],
    "Formation des OSC faîtières de groupes vulnérables (inclusion budgétaire)" => [
        'objectif' => "Outiller les OSC de groupes vulnérables pour faire valoir leurs besoins dans le processus budgétaire.",
        'resultats' => "OSC faîtières formées à la BSG et à l'inclusion budgétaire ; guide de formation BSG finalisé et utilisé.",
        'acteurs' => ['mffe', 'mfb', 'dgbf'],
    ],
    "Diffusion des 12 capsules vidéo budgétaires" => [
        'objectif' => "Vulgariser le budget de l'État auprès du grand public par des contenus audiovisuels pédagogiques.",
        'resultats' => "12 capsules diffusées sur les médias et réseaux sociaux ; audience mesurée.",
        'acteurs' => ['rti', 'lifetv', 'dgbf', 'ue'],
    ],
    "Conventions médias (RTI, Life TV) et émissions sur les finances publiques" => [
        'objectif' => "Institutionnaliser la couverture médiatique des finances publiques par des conventions avec les diffuseurs.",
        'resultats' => "Conventions signées (RTI, Life TV) ; émissions régulières sur les finances publiques ; dépendance à un diffuseur unique réduite.",
        'acteurs' => ['rti', 'lifetv'],
    ],
    "Finalisation et dissémination du référentiel de publication des informations budgétaires" => [
        'objectif' => "Doter l'administration d'un référentiel définissant les informations budgétaires à publier et le calendrier de publication.",
        'resultats' => "Référentiel finalisé et transmis à la DGBF ; dissémination auprès des administrations et de la société civile.",
        'acteurs' => ['dgbf', 'mfb', 'ue', 'pprc'],
    ],
    "Ateliers de partage des bonnes pratiques avec le Comité technique OGP" => [
        'objectif' => "Créer des synergies avec le Partenariat pour un Gouvernement Ouvert et partager les bonnes pratiques de transparence budgétaire.",
        'resultats' => "Bonnes pratiques partagées ; engagements budgétaires du PAN OGP alignés avec le projet.",
        'acteurs' => ['ctogp', 'pprc', 'dgbf'],
    ],
    "Atelier d'élaboration des critères de sélection des écoles et universités (concours transparence budgétaire)" => [
        'objectif' => "Définir de façon concertée les critères de participation au concours national de la transparence budgétaire.",
        'resultats' => "Critères de sélection validés avec les ministères de l'éducation ; règlement du concours prêt.",
        'acteurs' => ['men', 'mesrs', 'dgbf'],
    ],
    "Caravane d'information dans les écoles et universités" => [
        'objectif' => "Sensibiliser les jeunes à la transparence budgétaire et les mobiliser pour le concours.",
        'resultats' => "Établissements visités ; jeunes sensibilisés ; inscriptions au concours enregistrées.",
        'acteurs' => ['men', 'mesrs'],
    ],
    "Atelier de lancement du concours national de la transparence budgétaire" => [
        'objectif' => "Lancer officiellement le concours national auprès des établissements et du public.",
        'resultats' => "Concours lancé ; note conceptuelle et plan de communication mis en œuvre ; établissements candidats mobilisés.",
        'acteurs' => ['men', 'mesrs', 'dgbf', 'ue'],
    ],
    "Mise en place du Prix annuel de la transparence budgétaire" => [
        'objectif' => "Instituer un prix annuel récompensant l'innovation et l'engagement en faveur de la transparence budgétaire.",
        'resultats' => "Prix institué (règlement, jury, critères) ; condition préalable à la cérémonie du Hackathon Budget Ouvert remplie.",
        'acteurs' => ['dgbf', 'men', 'mesrs', 'ue'],
    ],
    "Cérémonie annuelle du Prix « Hackathon Budget Ouvert »" => [
        'objectif' => "Récompenser publiquement les lauréats et valoriser les solutions innovantes pour un budget ouvert.",
        'resultats' => "Cérémonie tenue ; lauréats primés ; visibilité médiatique du projet et de l'UE.",
        'acteurs' => ['dgbf', 'men', 'mesrs', 'ue', 'rti'],
    ],
    "Cadre formalisé d'échanges DGBF / société civile" => [
        'objectif' => "Établir un cadre permanent de dialogue entre la DGBF et la société civile sur le budget.",
        'resultats' => "TDR du cadre validés ; cadre officiellement installé ; réunions régulières programmées.",
        'acteurs' => ['dgbf', 'mfb', 'ue'],
    ],
    "Session annuelle d'échanges sur les orientations du DPBEP" => [
        'objectif' => "Permettre à la société civile de contribuer aux orientations du DPBEP avant les arbitrages du PLF.",
        'resultats' => "Propositions des OSC transmises à la DGBF avant les arbitrages du PLF 2027 ; compte rendu de session.",
        'acteurs' => ['dgbf', 'mfb', 'ue'],
    ],
    "Sessions trimestrielles sur les rapports d'exécution du budget (RTEB)" => [
        'objectif' => "Faire présenter et commenter les rapports trimestriels d'exécution du budget par la DGBF devant la société civile.",
        'resultats' => "3 sessions par an tenues ; analyses et recommandations des OSC recueillies ; RTEB mieux compris.",
        'acteurs' => ['dgbf', 'mfb', 'ue'],
    ],
    "Convention de partenariat Assemblée nationale / société civile" => [
        'objectif' => "Formaliser la collaboration entre l'Assemblée nationale (CAEF) et la société civile sur le contrôle budgétaire.",
        'resultats' => "Convention signée ; mécanisme de consultation des OSC par la CAEF établi.",
        'acteurs' => ['an', 'caef', 'ue'],
    ],
    "Participation de la société civile au débat d'orientation budgétaire (DOB)" => [
        'objectif' => "Porter les propositions des OSC sectorielles dans le débat d'orientation budgétaire au Parlement.",
        'resultats' => "Contributions des OSC transmises et présentées ; propositions reprises dans le DOB.",
        'acteurs' => ['an', 'caef', 'dgbf'],
    ],
    "Auditions thématiques sur les rapports trimestriels d'exécution du budget (RTEB) à l'Assemblée nationale" => [
        'objectif' => "Renforcer le contrôle parlementaire de l'exécution du budget avec l'éclairage de la société civile.",
        'resultats' => "Auditions thématiques organisées à la CAEF ; recommandations parlementaires formulées.",
        'acteurs' => ['an', 'caef', 'dgbf'],
    ],
    "Mémorandum / plateforme de collaboration Cour des Comptes - société civile" => [
        'objectif' => "Instituer une collaboration formelle entre la Cour des Comptes et la société civile.",
        'resultats' => "Mémorandum transmis, validé puis signé ; plateforme de collaboration opérationnelle.",
        'acteurs' => ['cdc', 'cellule_ue', 'ue'],
    ],
    "Journées portes-ouvertes de la Cour des Comptes" => [
        'objectif' => "Rapprocher la Cour des Comptes des citoyens, des OSC et des médias.",
        'resultats' => "Journées portes-ouvertes tenues ; meilleure connaissance des missions et des rapports de la Cour.",
        'acteurs' => ['cdc', 'rti', 'lifetv'],
    ],
    "Formation de la société civile et des médias à l'exploitation des rapports d'audit de la Cour des Comptes" => [
        'objectif' => "Renforcer la capacité des OSC et des médias à exploiter les rapports d'audit pour le suivi et le plaidoyer.",
        'resultats' => "5 modules déployés ; premier cycle de formation réalisé ; acteurs formés utilisant les rapports d'audit.",
        'acteurs' => ['cdc', 'ue'],
    ],
    "Ateliers de plaidoyer pour la prise en compte des propositions de la société civile dans le calendrier des audits" => [
        'objectif' => "Faire intégrer les préoccupations de la société civile dans la programmation des audits.",
        'resultats' => "Propositions des OSC remises à la Cour des Comptes ; prise en compte documentée dans le calendrier des audits.",
        'acteurs' => ['cdc'],
    ],
    "Plaidoyer pour l'exhaustivité du rapport d'audit de la Cour des Comptes" => [
        'objectif' => "Plaider pour que les rapports d'audit couvrent l'ensemble des opérations et soient publiés.",
        'resultats' => "Note de plaidoyer adossée aux recommandations de l'EBO 2025 remise aux décideurs.",
        'acteurs' => ['cdc', 'an', 'caef'],
    ],
    "Atelier de plaidoyer pour renforcer l'indépendance et le contrôle de la Cour des Comptes" => [
        'objectif' => "Contribuer au renforcement de l'indépendance et des capacités de contrôle de la Cour des Comptes.",
        'resultats' => "Recommandations d'amélioration formulées et portées auprès des institutions concernées.",
        'acteurs' => ['cdc', 'an', 'caef', 'ue'],
    ],
    "Atelier de lancement officiel des résultats de l'Enquête sur le Budget Ouvert (EBO) 2025" => [
        'objectif' => "Présenter les résultats de l'EBO 2025 de la Côte d'Ivoire et les recommandations pour améliorer la transparence budgétaire.",
        'resultats' => "Résultats EBO 2025 diffusés ; recommandations appropriées par l'administration ; alimentation des indicateurs de l'Effet 1.",
        'acteurs' => ['ue', 'dgbf', 'mfb', 'an', 'cdc', 'pprc', 'ctogp'],
    ],
    "Finalisation du compte rendu de la rencontre avec la Cour des Comptes du 10/07/2026" => [
        'objectif' => "Documenter fidèlement les échanges avec la Cour des Comptes pour assurer le suivi des engagements pris.",
        'resultats' => "Compte rendu finalisé, validé et partagé ; engagements et actions de suivi identifiés.",
        'acteurs' => ['cdc'],
    ],
    "Note de partenariat Social Justice / Cour des Comptes" => [
        'objectif' => "Définir les modalités de partenariat entre Social Justice et la Cour des Comptes.",
        'resultats' => "Note de partenariat finalisée et transmise à la Cour des Comptes.",
        'acteurs' => ['cdc', 'cellule_ue', 'ue'],
    ],
    "Diagnostic organisationnel de Social Justice" => [
        'objectif' => "Évaluer les forces et les faiblesses organisationnelles de Social Justice pour orienter son renforcement.",
        'resultats' => "Rapport de diagnostic produit ; plan de renforcement issu des recommandations.",
        'acteurs' => ['ue'],
    ],
    "Formation de Social Justice sur le SYSBNL" => [
        'objectif' => "Renforcer la conformité comptable de Social Justice au référentiel SYSBNL.",
        'resultats' => "Équipe formée ; cabinet d'audit et de conseil recruté ; pratiques comptables alignées sur le SYSBNL.",
        'acteurs' => ['ue'],
    ],
    "Mise à niveau du manuel de procédures et de l'équipe Social Justice" => [
        'objectif' => "Actualiser le manuel de procédures et former l'équipe à son application.",
        'resultats' => "Manuel finalisé selon les recommandations du diagnostic ; équipe formée.",
        'acteurs' => ['ue'],
    ],
    "Mise à niveau de l'équipe SJ sur les techniques de rédaction des rapports" => [
        'objectif' => "Améliorer la qualité de la rédaction des rapports destinés au bailleur et aux partenaires.",
        'resultats' => "Capacités évaluées ; module de formation dispensé ; post-test de l'équipe projet réalisé.",
        'acteurs' => ['ue'],
    ],
];
