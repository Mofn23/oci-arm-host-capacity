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
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $host = parse_url($uri, PHP_URL_HOST);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        
        // Construir request-target (path + query)
        $requestTarget = strtolower($method) . ' ' . $path;
        if ($query) {
            $requestTarget .= '?' . $query;
        }
        
        // Headers básicos siempre presentes
        $headers = [
            'date' => $date,
            '(request-target)' => $requestTarget,
            'host' => $host,
        ];
        
        // Para POST/PUT/PATCH añadir headers adicionales
        $signingHeadersNames = ['date', '(request-target)', 'host'];
        
        if ($body !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $contentLength = strlen($body);
            $contentType = 'application/json';
            $bodyHash = base64_encode(hash('sha256', $body, true));
            
            $headers['content-length'] = $contentLength;
            $headers['content-type'] = $contentType;
            $headers['x-content-sha256'] = $bodyHash;
            
            $signingHeadersNames = ['date', '(request-target)', 'host', 'content-length', 'content-type', 'x-content-sha256'];
        }
        
        // Construir signing string
        $signingString = '';
        foreach ($signingHeadersNames as $name) {
            if ($signingString !== '') {
                $signingString .= "\n";
            }
            $signingString .= "$name: " . $headers[$name];
        }
        
        // Firmar
        $signature = $this->sign($signingString);
        
        // Construir Key ID
        $keyId = "{$this->tenancyId}/{$this->userId}/{$this->keyFingerprint}";
        
        // Construir header de autorización
        $headersList = implode(' ', $signingHeadersNames);
        $authHeader = sprintf(
            'Signature version="1",keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
            $headersList,
            $signature
        );
        
        // Preparar headers para HTTP
        $httpHeaders = [
            "Date: $date",
            "Host: $host",
            "Authorization: $authHeader"
        ];
        
        if (isset($headers['content-length'])) {
            $httpHeaders[] = "Content-Length: {$headers['content-length']}";
            $httpHeaders[] = "Content-Type: {$headers['content-type']}";
            $httpHeaders[] = "x-content-sha256: {$headers['x-content-sha256']}";
        }
        
        return $httpHeaders;
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
