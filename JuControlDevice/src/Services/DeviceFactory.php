<?php

declare(strict_types=1);

namespace JuControlDevice\Services;

use JuControlDevice\Contracts\DeviceInterface;
use JuControlDevice\Contracts\ApiClientInterface;
use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Devices\ISoftSafePlusDevice;
use JuControlDevice\Devices\ISoftPlusDevice;

class DeviceFactory
{
    private ApiClientInterface $knmClient;
    private ApiClientInterface $judoClient;

    public function __construct(ApiClientInterface $knmClient, ApiClientInterface $judoClient)
    {
        $this->knmClient = $knmClient;
        $this->judoClient = $judoClient;
    }

    public function createDevice(string $deviceType, string $serialNumber): DeviceInterface
    {
        return match ($deviceType) {
            DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS => new ISoftSafePlusDevice(
                $this->knmClient,
                $this->judoClient,
                $serialNumber
            ),
            DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS => new ISoftPlusDevice(
                $this->knmClient,
                $this->judoClient,
                $serialNumber
            ),
            default => throw new \InvalidArgumentException("Unsupported device type: {$deviceType}"),
        };
    }

    public function getSupportedDeviceTypes(): array
    {
        return [
            DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS,
            DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS,
        ];
    }
}