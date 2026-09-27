<?php
/**
 * Acces aux modeles d'IA, toujours cote serveur (la cle n'est jamais envoyee au navigateur).
 *
 * AI_PROVIDER=gemini (defaut) : Google Gemini, offre gratuite (cle sur https://aistudio.google.com/apikey)
 * AI_PROVIDER=openai          : toute API compatible OpenAI, par ex. des modeles open source :
 *                               - Groq (gratuit, Llama / Mistral / Qwen)   AI_BASE_URL=https://api.groq.com/openai/v1
 *                               - OpenRouter (modeles ":free")            AI_BASE_URL=https://openrouter.ai/api/v1
 *                               - Ollama auto-heberge                      AI_BASE_URL=http://mon-serveur:11434/v1
 */
class AI
{
    public static function provider(): string
    {
        return strtolower(getenv('AI_PROVIDER') ?: 'gemini');
    }

    public static function model(): string
    {
        if (getenv('AI_MODEL')) {
            return getenv('AI_MODEL');
        }
        return self::provider() === 'gemini' ? 'gemini-2.5-flash' : 'llama-3.3-70b-versatile';
    }

    private static function key(): string
    {
        return getenv('AI_API_KEY') ?: (getenv('GEMINI_API_KEY') ?: '');
    }

    public static function isConfigured(): bool
    {
        // Un serveur Ollama local peut fonctionner sans cle
        return self::key() !== '' || (self::provider() === 'openai' && getenv('AI_BASE_URL'));
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
        return self::provider() === 'openai'
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
        $base = rtrim(getenv('AI_BASE_URL') ?: 'https://api.groq.com/openai/v1', '/');
        $payload = [
            'model' => self::model(),
            'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
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
            }
            throw new RuntimeException("Erreur du service IA ({$code}) : {$msg}");
        }
        return $data;
    }
}
