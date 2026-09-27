<?php
/**
 * Acces aux modeles d'IA, toujours cote serveur (la cle n'est jamais envoyee au navigateur).
 *
 * Le fournisseur est deduit du prefixe de AI_API_KEY : « AIza » Gemini, « gsk_ » Groq (Llama open source),
 * « sk-or- » OpenRouter. Sinon :
 * AI_PROVIDER=gemini (defaut) : Google Gemini, offre gratuite (cle sur https://aistudio.google.com/apikey)
 * AI_PROVIDER=openai          : toute API compatible OpenAI, par ex. des modeles open source :
 *                               - Groq (gratuit, Llama / Mistral / Qwen)   AI_BASE_URL=https://api.groq.com/openai/v1
 *                               - OpenRouter (modeles ":free")            AI_BASE_URL=https://openrouter.ai/api/v1
 *                               - Ollama auto-heberge                      AI_BASE_URL=http://mon-serveur:11434/v1
 */
class AI
{
    /**
     * Fournisseurs reconnus automatiquement d apres le prefixe de la cle :
     * il suffit de coller la cle dans AI_API_KEY, sans autre reglage.
     */
    private const FOURNISSEURS = [
        'groq' => ['prefixe' => 'gsk_', 'url' => 'https://api.groq.com/openai/v1', 'modele' => 'llama-3.3-70b-versatile'],
        'openrouter' => ['prefixe' => 'sk-or-', 'url' => 'https://openrouter.ai/api/v1', 'modele' => 'meta-llama/llama-3.3-70b-instruct:free'],
    ];

    public static function provider(): string
    {
        foreach (self::FOURNISSEURS as $nom => $f) {
            if (str_starts_with(self::key(), $f['prefixe'])) {
                return $nom;
            }
        }
        $p = strtolower(getenv('AI_PROVIDER') ?: 'gemini');
        return $p === 'gemini' ? 'gemini' : 'openai';
    }

    public static function model(): string
    {
        $p = self::provider();
        $m = getenv('AI_MODEL') ?: '';
        // Un modele Gemini configure n'a pas de sens chez un autre fournisseur (et inversement)
        if ($m !== '' && ($p === 'gemini') === str_starts_with($m, 'gemini')) {
            return $m;
        }
        return $p === 'gemini' ? 'gemini-2.5-flash' : (self::FOURNISSEURS[$p]['modele'] ?? 'llama-3.3-70b-versatile');
    }

    private static function baseUrl(): string
    {
        return rtrim(self::FOURNISSEURS[self::provider()]['url'] ?? (getenv('AI_BASE_URL') ?: 'https://api.groq.com/openai/v1'), '/');
    }

    private static function key(): string
    {
        $cle = getenv('AI_API_KEY') ?: (getenv('GEMINI_API_KEY') ?: '');
        // Erreurs de copier-coller frequentes : espaces, retours a la ligne, guillemets, "AI_API_KEY=" colle avec
        $cle = trim(preg_replace('/^\s*(AI_API_KEY|GEMINI_API_KEY)\s*=\s*/i', '', $cle), " \t\n\r\0\x0B\"'");
        return $cle;
    }

    /** Apercu non sensible de la cle pour le diagnostic : 4 premiers caracteres + longueur */
    public static function apercuCle(): string
    {
        $k = self::key();
        return $k === '' ? '(vide)' : substr($k, 0, 4) . '… (' . strlen($k) . ' caractères)';
    }

    /** Diagnostic lisible si la cle n'a manifestement pas le bon format */
    private static function verifierFormatCle(): void
    {
        if (self::provider() === 'gemini' && self::key() !== '' && !str_starts_with(self::key(), 'AIza')) {
            throw new RuntimeException("La valeur de AI_API_KEY dans Render n'est pas une clé reconnue. Clés acceptées : Gemini (commence par « AIza »), Groq (« gsk_ ») ou OpenRouter (« sk-or- »).");
        }
    }

    public static function isConfigured(): bool
    {
        // Un serveur Ollama local peut fonctionner sans cle
        return self::key() !== '' || (self::provider() === 'openai' && (bool) getenv('AI_BASE_URL'));
    }

    /**
     * @param string $system   instructions systeme
     * @param array  $messages [['role' => 'user'|'assistant', 'content' => '...'], ...]
     */
    public static function generate(string $system, array $messages, float $temperature = 0.4, int $maxTokens = 8192): string
    {
        if (!self::isConfigured()) {
            throw new RuntimeException("L'IA n'est pas configurée : ajoutez la variable d'environnement AI_API_KEY (clé Gemini gratuite sur https://aistudio.google.com/apikey).");
        }
        self::verifierFormatCle();
        return self::provider() !== 'gemini'
            ? self::openai($system, $messages, $temperature, $maxTokens)
            : self::gemini($system, $messages, $temperature, $maxTokens);
    }

    private static function gemini(string $system, array $messages, float $temperature, int $maxTokens): string
    {
        $contents = array_map(fn ($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
        ];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(self::model()) . ':generateContent';
        $data = self::post($url, $payload, ['x-goog-api-key: ' . self::key()]);

        $parts = $data['candidates'][0]['content']['parts'] ?? null;
        if (!$parts) {
            $raison = $data['candidates'][0]['finishReason'] ?? ($data['promptFeedback']['blockReason'] ?? 'inconnue');
            throw new RuntimeException("Réponse IA vide (raison : {$raison}).");
        }
        return trim(implode('', array_map(fn ($p) => $p['text'] ?? '', $parts)));
    }

    private static function openai(string $system, array $messages, float $temperature, int $maxTokens): string
    {
        $base = self::baseUrl();
        $payload = [
            'model' => self::model(),
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'temperature' => $temperature,
            // Offre gratuite Groq : environ 12 000 jetons par minute, requete + reponse comprises
            'max_tokens' => self::provider() === 'groq' ? min($maxTokens, 5000) : $maxTokens,
        ];
        $headers = self::key() !== '' ? ['Authorization: Bearer ' . self::key()] : [];
        $data = self::post($base . '/chat/completions', $payload, $headers);
        $text = $data['choices'][0]['message']['content'] ?? null;
        if ($text === null) {
            throw new RuntimeException('Réponse IA vide.');
        }
        return trim($text);
    }

    private static function post(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Service IA injoignable : ' . $err);
        }
        $data = json_decode($body, true) ?? [];
        if ($code >= 400) {
            $msg = $data['error']['message'] ?? substr($body, 0, 300);
            if ($code === 429) {
                $msg = "Quota gratuit momentanément atteint, réessayez dans une minute. ({$msg})";
            } elseif (stripos($msg, 'API key not valid') !== false || stripos($msg, 'API_KEY_INVALID') !== false || $code === 401) {
                $msg = "Le fournisseur d'IA refuse la clé AI_API_KEY enregistrée dans Render (clé invalide, supprimée ou mal copiée). Créez une nouvelle clé (Gemini : https://aistudio.google.com/apikey — Groq : https://console.groq.com/keys) et remplacez la valeur dans Render > Environment.";
            } elseif ($code === 403) {
                $msg = "Accès refusé par Google : la clé n'autorise pas l'API Gemini (créez-la depuis AI Studio plutôt que depuis Google Cloud). ({$msg})";
            }
            throw new RuntimeException("Erreur du service IA ({$code}) : {$msg}");
        }
        return $data;
    }
}
