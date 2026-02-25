<?php

namespace App;

class Signer
{
    private string $tenancyId;
    private string $userId;
    private string $keyFingerprint;
    private string $privateKeyPath;

    public function __construct(
        string $tenancyId,
        string $userId,
        string $keyFingerprint,
        string $privateKeyPath
    ) {
        $this->tenancyId = $tenancyId;
        $this->userId = $userId;
        $this->keyFingerprint = $keyFingerprint;
        $this->privateKeyPath = $privateKeyPath;
    }

    public function getHeaders(string $method, string $uri, ?string $body = null): array
    {
        $date = gmdate('D, d M Y H:i:s T');
        $host = parse_url($uri, PHP_URL_HOST);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($uri, PHP_URL_QUERY) ?: '';
        
        $target = $path;
        if ($query) {
            $target .= '?' . $query;
        }
        
        $signingString = "$method $target\nhost: $host\ndate: $date";
        
        $bodyHash = null;
        if ($body !== null) {
            $bodyHash = hash('sha256', $body);
            $signingString .= "\nx-content-sha256: $bodyHash\ncontent-type: application/json";
        }
        
        $signature = $this->sign($signingString);
        
        // Construir header de autorización con comillas escapadas
        $authHeader = sprintf(
            'Authorization: Signature version="1",keyId="%s/%s/%s",algorithm="rsa-sha256",signature="%s"',
            $this->tenancyId,
            $this->userId,
            $this->keyFingerprint,
            $signature
        );
        
        $headers = [
            "Date: $date",
            "Host: $host",
            $authHeader,
            'Content-Type: application/json'
        ];
        
        if ($body !== null) {
            $headers[] = "x-content-sha256: $bodyHash";
        }
        
        return $headers;
    }

    private function sign(string $data): string
    {
        $privateKey = file_get_contents($this->privateKeyPath);
        if (!$privateKey) {
            throw new \Exception("Cannot read private key from: $this->privateKeyPath");
        }
        
        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new \Exception("Invalid private key");
        }
        
        $signature = '';
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        
        return base64_encode($signature);
    }
}
