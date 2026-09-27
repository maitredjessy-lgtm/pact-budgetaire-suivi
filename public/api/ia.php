<?php
/**
 * Point d'entree unique de l'IA (POST, ?action=...) :
 *  - chat         : assistant conversationnel avec le contexte du projet
 *  - generer      : genere un document a partir d'un modele + activite + notes de l'utilisateur
 *  - reecrire     : ameliore / developpe / resume / corrige / traduit un passage
 *  - suggestions  : propose documents et actions requis pour une activite (JSON)
 *  - evaluation   : note d'analyse evaluative a partir des indicateurs et activites
 */
require_once __DIR__ . '/_bootstrap.php';
$user = Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Méthode non supportée', 405);
}

// Diagnostic de la configuration IA (administrateurs) : ne revele jamais la cle complete
if (($_GET['action'] ?? '') === 'test') {
    Auth::requireAdmin();
    $infos = ['fournisseur' => AI::provider(), 'modele' => AI::model(), 'cle' => AI::apercuCle()];
    try {
        $r = AI::generate('Réponds uniquement par le mot OK.', [['role' => 'user', 'content' => 'Test de connexion']], 0, 1024);
        Response::json($infos + ['ok' => true, 'message' => 'Connexion à l\'IA réussie (réponse : ' . mb_substr($r, 0, 30) . ')']);
    } catch (RuntimeException $e) {
        Response::json($infos + ['ok' => false, 'message' => $e->getMessage()]);
    }
}
if (!AI::isConfigured()) {
    Response::error("L'assistant IA n'est pas encore configuré. Un administrateur doit ajouter la variable d'environnement AI_API_KEY (clé gratuite Google Gemini : https://aistudio.google.com/apikey) dans les paramètres du service Render.", 503);
}

$action = $_GET['action'] ?? 'chat';
$b = Response::body();

const REGLES = "Règles impératives :\n"
    . "- Réponds toujours en français soigné, adapté au contexte institutionnel ivoirien et aux exigences des bailleurs (Union européenne).\n"
    . "- N'invente JAMAIS de chiffres, de noms de personnes, de dates ou de citations : si une information manque, écris [À compléter].\n"
    . "- Utilise le format Markdown : titres (#, ##, ###), listes à puces (-), tableaux (| a | b |), **gras**. Pas de blocs de code.\n";

$systemeBase = "Tu es l'assistant de rédaction et de suivi-évaluation de l'équipe du projet PACT BUDGÉTAIRE (ONG Social Justice, Côte d'Ivoire). "
    . "Tu es expert en gestion de projets financés par l'Union européenne, en finances publiques, transparence budgétaire (Enquête sur le Budget Ouvert), "
    . "suivi-évaluation (cadre logique, critères CAD-OCDE) et en rédaction administrative (TDR, rapports, courriers, comptes rendus).\n" . REGLES;

function journaliser(PDO $pdo, array $user, string $action, string $q, string $r): void
{
    $pdo->prepare('INSERT INTO ai_log (user_id, action, question, reponse) VALUES (?,?,?,?)')
        ->execute([$user['id'], $action, mb_substr($q, 0, 4000), mb_substr($r, 0, 20000)]);
}

function dateFr(?string $iso): string
{
    if (!$iso) {
        return '[date à préciser]';
    }
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($iso);
    return (int) date('j', $t) . (date('j', $t) === '1' ? 'er' : '') . ' ' . $mois[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

try {
    switch ($action) {
        case 'chat':
            $messages = array_values(array_filter(
                array_slice($b['messages'] ?? [], -12),
                fn ($m) => in_array($m['role'] ?? '', ['user', 'assistant'], true) && trim($m['content'] ?? '') !== ''
            ));
            if (!$messages || end($messages)['role'] !== 'user') {
                Response::error('Message requis', 422);
            }
            $systeme = $systemeBase
                . "Tu aides l'équipe à piloter le projet : état d'avancement, retards, priorités, personnes responsables, documents à produire, préparation des activités. "
                . "Sois concis et actionnable. Cite les codes d'activités.\n\n=== DONNÉES ACTUELLES DU PROJET ===\n" . Contexte::projet($pdo) . "\n=== FIN ===";
            $reponse = AI::generate($systeme, $messages, 0.4, 4096);
            journaliser($pdo, $user, 'chat', end($messages)['content'], $reponse);
            Response::json(['reponse' => $reponse]);
            // no break

        case 'generer':
            $modele = null;
            if (!empty($b['modele_id'])) {
                $s = $pdo->prepare('SELECT * FROM modeles WHERE id = ?');
                $s->execute([(int) $b['modele_id']]);
                $modele = $s->fetch() ?: null;
            }
            if (!$modele) {
                Response::error('Choisissez un modèle', 422);
            }
            $activite = !empty($b['activite_id']) ? Contexte::activite($pdo, (int) $b['activite_id']) : null;

            // Remplissage automatique des {{variables}} connues
            $vars = [
                'date_du_jour' => dateFr(date('Y-m-d')),
                'redacteur' => $user['nom'],
                'periode' => '[période à préciser]',
                'reference' => 'SJ/PACT-UE/[N°]',
                'destinataire' => '[Destinataire]',
                'formule_appel' => 'Monsieur/Madame',
                'objet' => $activite['titre'] ?? '[Objet]',
            ];
            if ($activite) {
                $vars += [
                    'titre_activite' => $activite['titre'],
                    'code_activite' => $activite['code'] ?: '[N°]',
                    'composante' => $activite['composante'] ?: '[composante]',
                    'lieu' => $activite['lieu'] ?: 'Abidjan',
                    'date_activite' => dateFr($activite['date_debut'] ?: $activite['date_echeance']),
                    'responsable' => $activite['responsable_nom'] ?: '[responsable]',
                ];
            }
            foreach (($b['variables'] ?? []) as $k => $v) {
                if (is_string($v) && trim($v) !== '') {
                    $vars[$k] = trim($v);
                }
            }
            $structure = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/u', fn ($m) => $vars[$m[1]] ?? '[' . str_replace('_', ' ', $m[1]) . ']', $modele['contenu']);

            $prompt = "Rédige le document complet « {$modele['nom']} » (type : {$modele['type']}).\n\n"
                . "STRUCTURE DU MODÈLE À RESPECTER (garde tous les titres, l'ordre des sections et les tableaux ; remplace les consignes entre crochets ou en italique par du contenu rédigé) :\n"
                . "<<<MODELE\n{$structure}\nMODELE>>>\n\n"
                . "CONSIGNES DE RÉDACTION PROPRES À CE MODÈLE : " . ($modele['consignes'] ?: 'aucune') . "\n\n";
            if ($activite) {
                $prompt .= "ACTIVITÉ CONCERNÉE :\n" . Contexte::activiteTexte($activite) . "\n\n";
            }
            if (trim($b['infos'] ?? '') !== '') {
                $prompt .= "INFORMATIONS FOURNIES PAR L'ÉQUIPE (source principale, à exploiter fidèlement) :\n" . trim($b['infos']) . "\n\n";
            }
            if (trim($b['instructions'] ?? '') !== '') {
                $prompt .= "INSTRUCTIONS SUPPLÉMENTAIRES : " . trim($b['instructions']) . "\n\n";
            }
            $prompt .= "Produis uniquement le document final en Markdown, sans commentaire avant ou après.";

            $systeme = $systemeBase . "\n=== CONTEXTE GÉNÉRAL DU PROJET ===\n" . Contexte::projet($pdo, $modele['type'] === 'rapport') . "\n=== FIN ===";
            $texte = AI::generate($systeme, [['role' => 'user', 'content' => $prompt]], 0.5, 8192);
            $texte = preg_replace('/^```(?:markdown)?\s*|\s*```$/u', '', trim($texte));
            journaliser($pdo, $user, 'generer', $modele['nom'] . ' / ' . ($activite['titre'] ?? '-'), $texte);
            Response::json(['contenu' => $texte, 'titre_suggere' => $modele['nom'] . ($activite ? ' — ' . $activite['titre'] : '')]);
            // no break

        case 'reecrire':
            $texte = trim($b['texte'] ?? '');
            if ($texte === '') {
                Response::error('Texte requis', 422);
            }
            $consignes = [
                'ameliorer' => "Améliore ce texte : style plus clair, professionnel et fluide, sans changer le sens ni ajouter d'informations.",
                'developper' => "Développe ce texte de manière plus détaillée et argumentée (environ deux fois plus long), en restant factuel et sans inventer de chiffres.",
                'resumer' => 'Résume ce texte de façon concise en conservant les informations essentielles.',
                'corriger' => "Corrige uniquement l'orthographe, la grammaire, la ponctuation et la typographie française. Ne change rien d'autre.",
                'formel' => 'Reformule ce texte dans un registre administratif et diplomatique adapté à un courrier officiel adressé à une institution.',
                'anglais' => 'Traduis ce texte en anglais professionnel (contexte bailleur Union européenne), en conservant la mise en forme Markdown.',
                'puces' => 'Transforme ce texte en liste à puces claire et structurée.',
            ];
            $consigne = $consignes[$b['mode'] ?? ''] ?? trim($b['consigne'] ?? '');
            if ($consigne === '') {
                Response::error('Consigne requise', 422);
            }
            $prompt = $consigne . "\nRenvoie uniquement le texte résultant, en Markdown, sans commentaire.\n\n<<<TEXTE\n{$texte}\nTEXTE>>>";
            if (trim($b['contexte'] ?? '') !== '') {
                $prompt = "Contexte : ce passage fait partie du document « " . trim($b['contexte']) . " ».\n" . $prompt;
            }
            $r = AI::generate($systemeBase, [['role' => 'user', 'content' => $prompt]], 0.3, 8192);
            journaliser($pdo, $user, 'reecrire:' . ($b['mode'] ?? 'libre'), mb_substr($texte, 0, 500), $r);
            Response::json(['texte' => preg_replace('/^```(?:markdown)?\s*|\s*```$/u', '', trim($r))]);
            // no break

        case 'suggestions':
            $activite = Contexte::activite($pdo, (int) ($b['activite_id'] ?? 0)) ?? Response::error('Activité introuvable', 404);
            $prompt = "Pour réaliser l'activité suivante dans le respect des règles de l'Union européenne (visibilité, justificatifs, redevabilité), "
                . "liste les documents à produire et les actions à mener qui ne sont PAS déjà listés parmi les documents et tâches existants.\n\n"
                . Contexte::activiteTexte($activite) . "\n\nDate du jour : " . date('Y-m-d') . "\n"
                . "Réponds UNIQUEMENT avec un JSON valide de la forme : "
                . '{"documents":[{"type":"tdr|courrier|rapport|compte_rendu|note|communique|autre","titre":"...","date_echeance":"AAAA-MM-JJ ou null","pourquoi":"..."}],"taches":[{"titre":"...","echeance":"AAAA-MM-JJ ou null"}],"conseils":"2-3 phrases"}';
            $r = AI::generate($systemeBase, [['role' => 'user', 'content' => $prompt]], 0.3, 4096);
            if (!preg_match('/\{.*\}/su', $r, $m) || !is_array($json = json_decode($m[0], true))) {
                Response::error("L'IA n'a pas renvoyé de suggestions exploitables, réessayez.", 502);
            }
            journaliser($pdo, $user, 'suggestions', $activite['titre'], $m[0]);
            Response::json($json);
            // no break

        case 'evaluation':
            $prompt = "Réalise une analyse de suivi-évaluation du projet à la date du jour" . (trim($b['periode'] ?? '') ? " pour la période : {$b['periode']}" : '') . ".\n"
                . "Structure attendue : 1) Synthèse (5 lignes) ; 2) Performance d'exécution (taux de réalisation, retards critiques avec codes et responsables) ; "
                . "3) Progrès vers les indicateurs du cadre logique (tableau avec taux de réalisation quand calculable) ; "
                . "4) Appréciation selon les critères CAD-OCDE (pertinence, cohérence, efficacité, efficience, impact, durabilité) ; "
                . "5) Risques et goulots d'étranglement ; 6) Recommandations priorisées (tableau : recommandation, responsable, échéance)."
                . (trim($b['focus'] ?? '') ? "\nPoint d'attention demandé : {$b['focus']}" : '');
            $systeme = $systemeBase . "\n=== DONNÉES DU PROJET ===\n" . Contexte::projet($pdo, true) . "\n=== FIN ===";
            $r = AI::generate($systeme, [['role' => 'user', 'content' => $prompt]], 0.4, 8192);
            journaliser($pdo, $user, 'evaluation', $b['periode'] ?? '', $r);
            Response::json(['contenu' => $r]);
            // no break

        default:
            Response::error('Action IA inconnue', 400);
    }
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 502);
}
