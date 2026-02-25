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

        $signingString = "$method $target
host: $host
date: $date";

        if ($body !== null) {
            $bodyHash = hash('sha256', $body);
            $signingString .= "
x-content-sha256: $bodyHash
content-type: application/json";
        }

        $signature = $this->sign($signingString);

        $headers = [
            "Date: $date",
            "Host: $host",
            "Authorization: Signature version="1",keyId="$this->tenancyId/$this->userId/$this->keyFingerprint",algorithm="rsa-sha256",signature="$signature"",
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
