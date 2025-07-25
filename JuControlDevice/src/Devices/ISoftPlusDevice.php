<?php

declare(strict_types=1);

namespace JuControlDevice\Devices;

use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Exceptions\JuControlException;

class ISoftPlusDevice extends AbstractDevice
{
    public function connect(): bool
    {
        try {
            // First disconnect any existing connection
            $this->sendJudoCommand('disconnect', [
                'parameter' => DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS,
                'serial number' => $this->serialNumber,
            ]);

            // Connect to device
            $response = $this->sendJudoCommand('connect', [
                'parameter' => DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS,
                'serial number' => $this->serialNumber,
            ]);
            
            $this->validateResponse($response);
            $this->isConnected = true;
            
            return true;
        } catch (\Exception $e) {
            throw JuControlException::apiRequestFailed('connect', $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        if ($this->isConnected) {
            try {
                $this->sendJudoCommand('disconnect', [
                    'parameter' => DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS,
                    'serial number' => $this->serialNumber,
                ]);
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
        }
        
        $this->isConnected = false;
        $this->deviceData = [];
    }

    public function refreshData(): array
    {
        if (!$this->isConnected) {
            throw new \RuntimeException('Device not connected');
        }

        try {
            $response = $this->sendJudoCommand('combined data');
            $this->validateResponse($response);

            if ($response['wtuType'] !== DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS) {
                throw JuControlException::wrongDeviceType(
                    DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS,
                    $response['wtuType']
                );
            }

            $this->deviceData = $response;
            
            return $this->parseDeviceData($response);
        } catch (\Exception $e) {
            throw JuControlException::apiRequestFailed('refresh data', $e->getMessage());
        }
    }

    public function sendCommand(string $command, array $parameters = []): bool
    {
        try {
            $response = $this->sendJudoCommand($command, $parameters);
            $this->validateResponse($response);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getDeviceType(): string
    {
        return DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS;
    }

    private function parseDeviceData(array $device): array
    {
        return [
            'serialNumber' => $device['serial number'],
            'status' => 'online', // i-soft plus is always online when connected
            'deviceType' => 'i-soft plus',
            'softwareVersion' => $device['data']['software version'] ?? '',
            'hardwareVersion' => $device['data']['hardware version'] ?? '',
            'inputHardness' => (float)($device['data']['Tableread 0'][10] ?? 0),
            'targetHardness' => (int)($device['data']['residual hardness'] ?? 0),
            'currentFlow' => $this->combine8BitTo16Bit(
                $device['data']['Tableread 2'][11] ?? 0,
                $device['data']['Tableread 2'][10] ?? 0
            ),
            'regeneration' => (bool)($device['data']['Tableread 1'][0] ?? false),
            'totalRegeneration' => $this->combine8BitTo16Bit(
                $device['data']['Tableread 1'][31] ?? 0,
                $device['data']['Tableread 1'][30] ?? 0
            ),
            'totalWater' => (int)explode(' ', trim($device['data']['water total'] ?? '0'))[0],
            'saltData' => $this->parseSaltData($device['data']['GET_SALT_Volume'] ?? ''),
            'waterStop' => (bool)($device['data']['Tableread 2'][0] ?? false),
            'installationDate' => (int)($device['data']['init date'] ?? 0),
            'serviceDate' => $this->parseServiceDate($device),
            'waterStopSettings' => $this->parseWaterStopSettings($device['data']),
        ];
    }

    private function combine8BitTo16Bit(int $high, int $low): int
    {
        return bindec(decbin($high) . decbin($low));
    }

    private function parseSaltData(string $saltData): array
    {
        if (strpos($saltData, ' ') === false) {
            return ['level' => 0, 'percent' => 0, 'days' => 0];
        }

        $parts = explode(' ', $saltData);
        $level = (int)($parts[0] ?? 0) / 1000;
        
        return [
            'level' => round($level),
            'percent' => min((int)(2 * $level), 100),
            'days' => (int)($parts[1] ?? 0),
        ];
    }

    private function parseServiceDate(array $device): int
    {
        if (($device['data']['service date'] ?? '0') !== '0') {
            return 0; // Unexpected value
        }

        // Get service date from contract endpoint
        try {
            $response = $this->sendJudoCommand('service date', ['group' => 'contract']);
            $lastService = (int)($response['data'] ?? 0);
            
            $baseDate = $lastService === 0 ? 
                (int)($device['data']['init date'] ?? 0) : 
                $lastService;
                
            return strtotime('+1 year', $baseDate);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function parseWaterStopSettings(array $data): array
    {
        return [
            'maxPeriodOfUse' => $this->combine8BitTo16Bit(
                $data['Tableread 2'][17] ?? 0,
                $data['Tableread 2'][16] ?? 0
            ),
            'maxQuantity' => $this->combine8BitTo16Bit(
                $data['Tableread 2'][15] ?? 0,
                $data['Tableread 2'][14] ?? 0
            ),
            'maxWaterFlow' => $this->combine8BitTo16Bit(
                $data['Tableread 2'][13] ?? 0,
                $data['Tableread 2'][12] ?? 0
            ),
        ];
    }
}