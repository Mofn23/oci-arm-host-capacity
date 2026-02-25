<?php

require_once 'vendor/autoload.php';

use App\OciConfig;
use App\OciApi;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Configuración
$config = new OciConfig(
    getenv('OCI_REGION'),
    getenv('OCI_USER_ID'),
    getenv('OCI_TENANCY_ID'),
    getenv('OCI_KEY_FINGERPRINT'),
    getenv('OCI_PRIVATE_KEY_FILENAME'),
    getenv('OCI_AVAILABILITY_DOMAIN') ?: null,
    getenv('OCI_SUBNET_ID'),
    getenv('OCI_IMAGE_ID'),
    (int) getenv('OCI_OCPUS'),
    (int) getenv('OCI_MEMORY_IN_GBS')
);

$shape = getenv('OCI_SHAPE') ?: 'VM.Standard.A1.Flex';
$maxInstances = (int) (getenv('OCI_MAX_INSTANCES') ?: 1);
$sshKey = getenv('OCI_SSH_PUBLIC_KEY');

echo "OCI ARM Host Capacity Checker\n";
echo "Region: {$config->region}\n";
echo "Shape: $shape\n";
echo "OCPUs: {$config->ocpus}, Memory: {$config->memoryInGBs}GB\n";
echo "Max instances: $maxInstances\n";
echo "---\n";

$api = new OciApi($config);

// Verificar instancias existentes
$instances = $api->getInstances($config);
$existingMsg = $api->checkExistingInstances($config, $instances, $shape, $maxInstances);

if ($existingMsg) {
    echo "$existingMsg\n";
    exit(0);
}

// Obtener dominios de disponibilidad
$availabilityDomains = $config->availabilityDomains;
if (empty($availabilityDomains)) {
    echo "Fetching availability domains...\n";
    $availabilityDomains = $api->getAvailabilityDomains($config);
}

if (empty($availabilityDomains)) {
    echo "No availability domains found!\n";
    exit(1);
}

echo "Checking availability domains: " . implode(', ', (array)$availabilityDomains) . "\n";

// Intentar crear en cada dominio
foreach ((array)$availabilityDomains as $ad) {
    echo "\nTrying availability domain: $ad\n";

    try {
        $instance = $api->createInstance($config, $shape, $sshKey, $ad);
        echo "\n✅ SUCCESS! Instance created:\n";
        echo json_encode($instance, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    } catch (\Exception $e) {
        $msg = $e->getMessage();
        echo "❌ Failed: $msg\n";

        // Si es error de capacidad, continuar con siguiente AD
        if (strpos($msg, 'Out of host capacity') !== false) {
            echo "(Out of capacity, trying next domain...)\n";
            sleep(2);
            continue;
        }

        // Otro error, detener
        exit(1);
    }
}

echo "\n⚠️  Out of capacity in all availability domains. Will retry later.\n";
exit(0);
