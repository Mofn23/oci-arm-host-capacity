<?php

namespace App;

class OciConfig
{
    public string $region;
    public string $ociUserId;
    public string $tenancyId;
    public string $keyFingerPrint;
    public string $privateKeyFilename;
    public $availabilityDomains;
    public string $subnetId;
    public string $imageId;
    public int $ocpus;
    public int $memoryInGBs;
    public ?string $bootVolumeId = null;
    public ?string $bootVolumeSizeInGBs = null;
    public ?string $sourceDetails = null;

    public function __construct(
        string $region,
        string $ociUserId,
        string $tenancyId,
        string $keyFingerPrint,
        string $privateKeyFilename,
        $availabilityDomains,
        string $subnetId,
        string $imageId,
        int $ocups,
        int $memoryInGBs
    ) {
        $this->region = $region;
        $this->ociUserId = $ociUserId;
        $this->tenancyId = $tenancyId;
        $this->keyFingerPrint = $keyFingerPrint;
        $this->privateKeyFilename = $privateKeyFilename;
        $this->availabilityDomains = $availabilityDomains;
        $this->subnetId = $subnetId;
        $this->imageId = $imageId;
        $this->ocpus = $ocups;
        $this->memoryInGBs = $memoryInGBs;
    }

    public function setBootVolumeId(string $bootVolumeId): void
    {
        $this->bootVolumeId = $bootVolumeId;
    }

    public function getSourceDetails(): string
    {
        if (isset($this->sourceDetails)) {
            return $this->sourceDetails;
        }

        $sourceDetails = [
            'sourceType' => 'image',
            'imageId' => $this->imageId,
        ];

        if (!empty($this->bootVolumeSizeInGBs)) {
            $sourceDetails['bootVolumeSizeInGBs'] = (int) $this->bootVolumeSizeInGBs;
        }

        return json_encode($sourceDetails);
    }

    public function setBootVolumeSizeInGBs(string $bootVolumeSizeInGBs): void
    {
        $this->bootVolumeSizeInGBs = $bootVolumeSizeInGBs;
    }

    public function setSourceDetails(string $sourceDetails): void
    {
        $this->sourceDetails = $sourceDetails;
    }
}
