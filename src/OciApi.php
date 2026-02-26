<?php

namespace App;

class OciApi
{
    private Signer $signer;
    private string $baseUrl;
    private string $identityUrl;

    public function __construct(OciConfig $config)
    {
        $this->signer = new Signer(
            $config->tenancyId,
            $config->ociUserId,
            $config->keyFingerPrint,
            $config->privateKeyFilename
        );
        // URL para Compute API (IAAS)
        $this->baseUrl = "https://iaas.{$config->region}.oraclecloud.com";
        // URL para Identity API (para availability domains)
        $this->identityUrl = "https://identity.{$config->region}.oraclecloud.com";
    }

    public function getInstances(OciConfig $config): array
    {
        $url = "{$this->baseUrl}/20160918/instances?compartmentId={$config->tenancyId}";
        $headers = $this->signer->getHeaders('GET', $url);
        
        $response = HttpClient::getResponse($url, $headers);

        if ($response['code'] === 200) {
            return json_decode($response['body'], true) ?? [];
        }

        echo "Error getting instances: HTTP {$response['code']}\n";
        return [];
    }

    public function getAvailabilityDomains(OciConfig $config): array
    {
        // CORREGIDO: Usar identityUrl en lugar de baseUrl
        $url = "{$this->identityUrl}/20160918/availabilityDomains?compartmentId={$config->tenancyId}";
        $headers = $this->signer->getHeaders('GET', $url);
        
        $response = HttpClient::getResponse($url, $headers);

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
