<?php

declare(strict_types=1);

namespace JuControlDevice\Devices;

use JuControlDevice\Contracts\DeviceInterface;
use JuControlDevice\Contracts\ApiClientInterface;
use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Exceptions\JuControlException;

abstract class AbstractDevice implements DeviceInterface
{
    protected ApiClientInterface $knmClient;
    protected ApiClientInterface $judoClient;
    protected string $serialNumber;
    protected array $deviceData = [];
    protected bool $isConnected = false;

    public function __construct(
        ApiClientInterface $knmClient,
        ApiClientInterface $judoClient,
        string $serialNumber
    ) {
        $this->knmClient = $knmClient;
        $this->judoClient = $judoClient;
        $this->serialNumber = $serialNumber;
    }

    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    public function isOnline(): bool
    {
        return $this->isConnected && 
               isset($this->deviceData['status']) && 
               $this->deviceData['status'] === 'online';
    }

    protected function validateResponse(array $response): void
    {
        if (!isset($response['status']) || $response['status'] !== 'ok') {
            $message = $response['message'] ?? 'Unknown API error';
            throw JuControlException::apiRequestFailed('API call', $message);
        }
    }

    protected function sendKnmCommand(string $command, array $parameters = []): array
    {
        $data = array_merge([
            'group' => 'register',
            'command' => $command,
            'serialnumber' => $this->serialNumber,
        ], $parameters);

        return $this->knmClient->sendRequest('/', $data);
    }

    protected function sendJudoCommand(string $command, array $parameters = []): array
    {
        $data = array_merge([
            'group' => 'device',
            'command' => $command,
        ], $parameters);

        return $this->judoClient->sendRequest('/', $data);
    }
}