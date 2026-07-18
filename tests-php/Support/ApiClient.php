<?php
declare(strict_types=1);

// Petit client HTTP (curl) vers le serveur PHP intégré démarré par
// tests-php/bootstrap.php — appelle les VRAIS fichiers api/*.php, comme le
// ferait js/server-api.js en prod.
final class ApiClient
{
    public static function post(string $path, array $body = [], ?string $token = null): ApiResponse
    {
        return self::request('POST', $path, $body, $token);
    }

    public static function get(string $path, ?string $token = null): ApiResponse
    {
        return self::request('GET', $path, null, $token);
    }

    private static function request(string $method, string $path, ?array $body, ?string $token): ApiResponse
    {
        $ch = curl_init(TEST_API_BASE . $path);
        $headers = ['Content-Type: application/json'];
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // filet de sécurité : jamais de hang silencieux si le serveur de test se bloque
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('Requête API échouée : ' . curl_error($ch));
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$raw, true);
        return new ApiResponse($status, is_array($decoded) ? $decoded : [], (string)$raw);
    }
}

final class ApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $json,
        public readonly string $raw,
    ) {}

    public function ok(): bool
    {
        return ($this->json['ok'] ?? null) === true;
    }
}
