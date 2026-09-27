<?php
/**
 * Redaction assistee de documents, en trois temps :
 *  1. references : l'IA s'inspire des documents reels deja rediges par l'equipe (meme type, meme activite)
 *     pour reprendre le style, les formules et la mise en forme de Social Justice ;
 *  2. redaction  : les longs documents sont rediges section par section (plus complets, sans troncature) ;
 *  3. relecture  : une seconde passe verifie le respect du modele, corrige le francais et retire
 *     toute information inventee.
 */
class Redaction
{
    private const TYPES_PROCHES = [
        'rapport' => ['rapport', 'compte_rendu'],
        'compte_rendu' => ['compte_rendu', 'rapport'],
        'courrier' => ['courrier'],
        'tdr' => ['tdr'],
        'note' => ['note'],
        'communique' => ['communique', 'note'],
        'autre' => ['autre', 'note'],
    ];
    private const MOTS_VIDES = ['les', 'des', 'une', 'pour', 'dans', 'avec', 'sur', 'par', 'aux', 'du', 'de', 'la', 'le', 'et', 'en', 'au', 'projet', 'pact', 'budgetaire', 'budgétaire', 'social', 'justice'];

    /**
     * Choisit jusqu'a $max documents reels du projet servant de reference de style.
     * @return array<int, array{id:int, titre:string, extrait:string}>
     */
    public static function references(PDO $pdo, array $modele, ?array $activite, int $max = 2): array
    {
        $types = self::TYPES_PROCHES[$modele['type']] ?? [$modele['type']];
        $marques = implode(',', array_fill(0, count($types), '?'));
        $s = $pdo->prepare("SELECT id, titre, type, statut, activite_id, LENGTH(contenu) AS longueur, updated_at
            FROM documents WHERE type IN ({$marques}) AND contenu IS NOT NULL AND LENGTH(contenu) > 600");
        $s->execute($types);
        $candidats = $s->fetchAll();
        if (!$candidats) {
            return [];
        }

        $motsCibles = self::mots(($activite['titre'] ?? '') . ' ' . $modele['nom']);
        foreach ($candidats as &$c) {
            $score = 0;
            if ($c['type'] === $modele['type']) {
                $score += 4;
            }
            if ($activite && (int) $c['activite_id'] === (int) $activite['id']) {
                $score += 6;
            }
            if (in_array($c['statut'], ['valide', 'envoye'], true)) {
                $score += 3;
            }
            $score += 2 * count(array_intersect($motsCibles, self::mots($c['titre'])));
            // Documents tres courts ou tres longs : moins representatifs
            $score += ($c['longueur'] > 1500 && $c['longueur'] < 40000) ? 2 : 0;
            $c['score'] = $score;
        }
        unset($c);
        usort($candidats, fn ($a, $b) => $b['score'] <=> $a['score']);

        $refs = [];
        $vus = [];
        $lire = $pdo->prepare('SELECT contenu FROM documents WHERE id = ?');
        foreach ($candidats as $c) {
            if (count($refs) >= $max) {
                break;
            }
            // Evite deux exemples quasi identiques (ex. meme courrier envoye a plusieurs OSC)
            $empreinte = mb_substr(implode(' ', self::mots($c['titre'])), 0, 40);
            if (isset($vus[$empreinte])) {
                continue;
            }
            $vus[$empreinte] = true;
            $lire->execute([$c['id']]);
            $contenu = (string) $lire->fetchColumn();
            $refs[] = ['id' => (int) $c['id'], 'titre' => $c['titre'], 'extrait' => mb_substr($contenu, 0, 6000)];
        }
        return $refs;
    }

    private static function mots(string $texte): array
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($texte), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($mots, fn ($m) => mb_strlen($m) > 3 && !in_array($m, self::MOTS_VIDES, true))));
    }

    public static function blocReferences(array $refs): string
    {
        if (!$refs) {
            return '';
        }
        $t = "EXEMPLES DE DOCUMENTS RÉELS DE SOCIAL JUSTICE (références de STYLE uniquement) :\n"
            . "Imite leur ton, leurs formules, leur niveau de détail et leur mise en forme. "
            . "N'en recopie PAS les faits, dates, chiffres ou noms : ils concernent d'autres activités.\n";
        foreach ($refs as $i => $r) {
            $t .= "\n<<<EXEMPLE " . ($i + 1) . " — « {$r['titre']} »\n{$r['extrait']}\nEXEMPLE>>>\n";
        }
        return $t . "\n";
    }

    /**
     * Decoupe la structure du modele en un preambule et des sections de niveau 2 (« ## »).
     * @return array{0:string, 1:string[]}
     */
    public static function sections(string $structure): array
    {
        $parties = preg_split('/^(?=##\s)/mu', $structure);
        $preambule = '';
        if ($parties && !preg_match('/^##\s/u', $parties[0])) {
            $preambule = array_shift($parties);
        }
        return [$preambule, array_values(array_filter($parties, fn ($p) => trim($p) !== ''))];
    }

    /**
     * Redige le document. Au-dela de 5 sections, il est redige par groupes de 3 sections
     * pour obtenir un contenu complet et developpe.
     */
    public static function rediger(string $systeme, string $consigneGenerale, string $structure, string $type): string
    {
        [$preambule, $sections] = self::sections($structure);
        $maxTokens = 8192;

        if (count($sections) <= 5 || !in_array($type, ['rapport', 'tdr', 'compte_rendu', 'note'], true)) {
            $prompt = $consigneGenerale
                . "STRUCTURE DU MODÈLE À RESPECTER (garde tous les titres, l'ordre des sections et les tableaux ; remplace les consignes entre crochets ou en italique par du contenu rédigé) :\n"
                . "<<<MODELE\n{$structure}\nMODELE>>>\n\nProduis uniquement le document final en Markdown, sans commentaire avant ou après.";
            return self::nettoyer(AI::generate($systeme, [['role' => 'user', 'content' => $prompt]], 0.5, $maxTokens));
        }

        $groupes = array_chunk($sections, 3);
        $document = '';
        foreach ($groupes as $i => $groupe) {
            $aRediger = ($i === 0 ? $preambule : '') . implode('', $groupe);
            $titres = implode(' ; ', array_map(fn ($s) => trim(strtok($s, "\n"), "# \t"), $groupe));
            $prompt = $consigneGenerale
                . "STRUCTURE COMPLÈTE DU DOCUMENT (pour vue d'ensemble) :\n<<<MODELE\n{$structure}\nMODELE>>>\n\n"
                . ($document !== '' ? "DÉBUT DÉJÀ RÉDIGÉ (ne pas répéter, assurer la continuité) — fin du texte :\n<<<DEJA\n" . mb_substr($document, -3500) . "\nDEJA>>>\n\n" : '')
                . "RÉDIGE MAINTENANT UNIQUEMENT " . ($i === 0 && trim($preambule) !== '' ? "l'en-tête du document puis " : '') . "les sections suivantes : {$titres}.\n"
                . "Partie du modèle à rédiger :\n<<<PARTIE\n{$aRediger}\nPARTIE>>>\n\n"
                . "Garde exactement les titres de ces sections. Développe chaque section de façon complète et professionnelle. "
                . "Produis uniquement ce texte en Markdown, sans commentaire.";
            $document .= ($document !== '' ? "\n\n" : '') . self::nettoyer(AI::generate($systeme, [['role' => 'user', 'content' => $prompt]], 0.5, $maxTokens));
        }
        return $document;
    }

    /**
     * Relecture critique : conformite au modele, francais, ton, suppression des informations inventees.
     * Si la version relue parait tronquee, le brouillon d'origine est conserve.
     */
    public static function relire(string $systeme, string $brouillon, string $structure, string $consignes, string $sources): string
    {
        if (mb_strlen($brouillon) > 45000) {
            return $brouillon;
        }
        $prompt = "Tu es relecteur-qualité exigeant (chargé de suivi-évaluation et de communication institutionnelle). Relis et corrige ce brouillon.\n\n"
            . "Vérifie et corrige :\n"
            . "1. Toutes les sections du modèle sont présentes, dans l'ordre, avec leurs titres ; aucune consigne du modèle n'est restée telle quelle.\n"
            . "2. Aucun chiffre, date, nom de personne ou citation n'est inventé : tout ce qui n'apparaît pas dans les SOURCES ci-dessous doit être remplacé par [À compléter : précision attendue].\n"
            . "3. Orthographe, grammaire, accords, typographie française (espaces avant : ; ! ?, guillemets « »).\n"
            . "4. Ton institutionnel adapté au bailleur (Union européenne) et aux partenaires ivoiriens ; phrases claires, pas de répétitions.\n"
            . "5. Consignes propres au modèle : {$consignes}\n\n"
            . "SOURCES AUTORISÉES (seules informations factuelles utilisables) :\n<<<SOURCES\n{$sources}\nSOURCES>>>\n\n"
            . "MODÈLE :\n<<<MODELE\n{$structure}\nMODELE>>>\n\n"
            . "BROUILLON À CORRIGER :\n<<<BROUILLON\n{$brouillon}\nBROUILLON>>>\n\n"
            . "Renvoie UNIQUEMENT le document complet corrigé, en Markdown, sans commentaire ni liste des corrections.";
        try {
            $relu = self::nettoyer(AI::generate($systeme, [['role' => 'user', 'content' => $prompt]], 0.2, 16384));
        } catch (RuntimeException $e) {
            return $brouillon; // relecture facultative : on garde le brouillon si elle echoue (quota, delai)
        }
        return mb_strlen($relu) >= 0.7 * mb_strlen($brouillon) ? $relu : $brouillon;
    }

    /** Liste les informations restant a completer, avec un peu de contexte */
    public static function aCompleter(string $texte): array
    {
        preg_match_all('/\[(?:À|A|à) (?:compléter|préciser)[^\]]*\]/u', $texte, $m);
        return array_values(array_unique($m[0]));
    }

    private static function nettoyer(string $texte): string
    {
        return trim(preg_replace('/^```(?:markdown)?\s*|\s*```$/u', '', trim($texte)));
    }
}
