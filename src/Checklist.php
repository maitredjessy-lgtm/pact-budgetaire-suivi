<?php
/**
 * Documents et actions requis pour realiser une activite, selon son type.
 * Les echeances sont calculees par rapport a la date de l'activite (decalage en jours).
 */
class Checklist
{
    private const DOCUMENTS = [
        'atelier' => [
            ['tdr', 'Termes de référence (TDR)', -30],
            ['courrier', "Courriers d'invitation (OSC, institutions, médias)", -14],
            ['autre', 'Agenda / programme validé', -10],
            ['note', 'Éléments de langage / discours d\'ouverture', -3],
            ['autre', 'Liste de présence', 0],
            ['communique', 'Communiqué de presse', 1],
            ['rapport', "Rapport d'activité", 14],
        ],
        'formation' => [
            ['tdr', 'Termes de référence (TDR) de la formation', -30],
            ['courrier', "Courriers d'invitation des participants", -14],
            ['autre', 'Modules et supports de formation', -7],
            ['autre', 'Liste de présence', 0],
            ['autre', 'Questionnaires pré/post-test et évaluation', 0],
            ['rapport', 'Rapport de formation', 14],
        ],
        'reunion' => [
            ['courrier', "Courrier de demande d'audience / séance de travail", -21],
            ['note', 'Éléments de langage / note de présentation', -3],
            ['autre', 'Liste de présence', 0],
            ['compte_rendu', 'Compte rendu de réunion', 5],
        ],
        'plaidoyer' => [
            ['note', 'Note de plaidoyer', -21],
            ['courrier', 'Courrier aux institutions ciblées', -14],
            ['compte_rendu', 'Compte rendu des échanges', 7],
            ['rapport', 'Rapport de l\'action de plaidoyer', 14],
        ],
        'communication' => [
            ['note', 'Plan de communication / scripts', -21],
            ['courrier', 'Courriers aux médias partenaires', -14],
            ['communique', 'Communiqué de presse', 0],
            ['rapport', "Rapport d'activité et revue de presse", 14],
        ],
        'etude' => [
            ['tdr', "TDR de l'étude / de la mission", -45],
            ['rapport', "Rapport provisoire", -7],
            ['rapport', 'Rapport final validé', 14],
            ['note', 'Note de synthèse', 21],
        ],
        'autre' => [
            ['tdr', 'Termes de référence (TDR)', -21],
            ['rapport', "Rapport d'activité", 14],
        ],
    ];

    private const TACHES = [
        'atelier' => [
            ["Faire valider les TDR par IBP / la Délégation de l'UE", -25],
            ['Réserver la salle et la logistique (restauration, sonorisation)', -15],
            ['Envoyer les invitations', -14],
            ['Relancer et confirmer les participants', -3],
            ['Préparer la visibilité UE (banderole, kakémono, photos)', -2],
            ['Collecter les listes de présence et justificatifs financiers', 2],
        ],
        'formation' => [
            ['Mobiliser les formateurs / personnes ressources', -21],
            ['Réserver la salle et la logistique', -15],
            ['Envoyer les invitations', -14],
            ['Confirmer les participants (quotas femmes / jeunes / PH)', -3],
            ['Analyser les évaluations des participants', 5],
        ],
        'reunion' => [
            ['Obtenir la date de la séance auprès du secrétariat', -10],
            ['Préparer la délégation et les supports', -2],
            ['Diffuser le compte rendu aux participants', 7],
        ],
        'plaidoyer' => [
            ['Identifier les cibles et alliés', -30],
            ['Organiser les rencontres de plaidoyer', -7],
            ['Suivre les engagements obtenus', 30],
        ],
        'communication' => [
            ['Valider les contenus avec IBP / UE (visibilité)', -10],
            ['Diffuser sur les réseaux sociaux et médias', 0],
            ['Collecter les statistiques de diffusion', 14],
        ],
        'etude' => [
            ['Recruter le consultant / la personne ressource', -40],
            ["Organiser l'atelier de validation", 7],
        ],
        'autre' => [],
    ];

    public static function proposer(array $activite): array
    {
        $type = isset(self::DOCUMENTS[$activite['type_activite'] ?? '']) ? $activite['type_activite'] : 'autre';
        // Date de reference : prochaine date connue de l'activite ; si elle est passee
        // (activite recurrente ou en retard), on replanifie a J+21 pour rester realiste.
        $today = date('Y-m-d');
        $ref = null;
        foreach ([$activite['date_debut'] ?? null, $activite['date_echeance'] ?? null] as $d) {
            if ($d && $d >= $today) {
                $ref = $d;
                break;
            }
        }
        $ref ??= date('Y-m-d', strtotime('+21 days'));
        $date = function (int $decalage) use ($ref, $today) {
            return max($today, date('Y-m-d', strtotime("{$ref} {$decalage} days")));
        };
        return [
            'type' => $type,
            'documents' => array_map(fn ($d) => ['type' => $d[0], 'titre' => $d[1], 'date_echeance' => $date($d[2])], self::DOCUMENTS[$type]),
            'taches' => array_map(fn ($t) => ['titre' => $t[0], 'echeance' => $date($t[1])], self::TACHES[$type]),
        ];
    }
}
