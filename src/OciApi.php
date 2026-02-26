<?php

namespace App;

class OciApi
{
    private Signer $signer;
    private string $baseUrl;

    public function __construct(OciConfig $config)
    {
        $this->signer = new Signer(
            $config->tenancyId,
            $config->ociUserId,
            $config->keyFingerPrint,
            $config->privateKeyFilename
        );
        // Asegurar que la URL base es correcta
        $this->baseUrl = "https://iaas.{$config->region}.oraclecloud.com";
        echo "DEBUG - Base URL: {$this->baseUrl}\n";
        echo "DEBUG - Region: {$config->region}\n";
        echo "DEBUG - Tenancy ID: {$config->tenancyId}\n";
    }

    public function getInstances(OciConfig $config): array
    {
        $url = "{$this->baseUrl}/20160918/instances?compartmentId={$config->tenancyId}";
        echo "DEBUG - Instances URL: $url\n";
        $headers = $this->signer->getHeaders('GET', $url);
        echo "DEBUG - Headers: " . print_r($headers, true) . "\n";
        
        $response = HttpClient::getResponse($url, $headers);
        echo "DEBUG - Response code: {$response['code']}\n";
        echo "DEBUG - Response body: " . substr($response['body'], 0, 200) . "\n";

        if ($response['code'] === 200) {
            return json_decode($response['body'], true) ?? [];
        }

        echo "Error getting instances: HTTP {$response['code']}\n";
        return [];
    }

    public function getAvailabilityDomains(OciConfig $config): array
    {
        $url = "{$this->baseUrl}/20160918/availabilityDomains?compartmentId={$config->tenancyId}";
        echo "DEBUG - AvailabilityDomains URL: $url\n";
        $headers = $this->signer->getHeaders('GET', $url);
        
        $response = HttpClient::getResponse($url, $headers);
        echo "DEBUG - Response code: {$response['code']}\n";
        echo "DEBUG - Response body: " . substr($response['body'], 0, 500) . "\n";

        if ($response['code'] === 200) {
            $domains = json_decode($response['body'], true);
            return array_map(fn($d) => $d['name'], $domains);
        }

        echo "Error getting availability domains: HTTP {$response['code']}\n";
        return [];
    }

    public function checkExistingInstances(OciConfig $config, array $instances, string $shape, int $maxInstances): ?string
    {
        $count = 0;
        foreach ($instances as $instance) {
            if ($instance['shape'] === $shape && $instance['lifecycleState'] === 'RUNNING') {
                $count++;
            }
        }

        if ($count >= $maxInstances) {
            return "Already have $count instance(s) of shape $shape running. Max allowed: $maxInstances";
        }

        return null;
    }

    public function createInstance(OciConfig $config, string $shape, string $sshKey, string $availabilityDomain): array
    {
        $url = "{$this->baseUrl}/20160918/instances";

        $payload = [
            'compartmentId' => $config->tenancyId,
            'availabilityDomain' => $availabilityDomain,
            'shape' => $shape,
            'displayName' => 'oci-arm-' . date('Y-m-d-H-i-s'),
            'sourceDetails' => json_decode($config->getSourceDetails(), true),
            'createVnicDetails' => [
                'subnetId' => $config->subnetId,
                'assignPublicIp' => true
            ],
            'metadata' => [
                'ssh_authorized_keys' => $sshKey
            ],
            'shapeConfig' => [
                'ocpus' => $config->ocpus,
                'memoryInGBs' => $config->memoryInGBs
            ]
        ];

        $body = json_encode($payload);
        $headers = $this->signer->getHeaders('POST', $url, $body);

        $response = HttpClient::getResponse($url, $headers, 'POST', $body);

        if ($response['code'] === 200) {
            return json_decode($response['body'], true);
        }

        $errorData = json_decode($response['body'], true);
        $errorMessage = $errorData['message'] ?? $response['body'];

        throw new \Exception($errorMessage, $response['code']);
    }
}
