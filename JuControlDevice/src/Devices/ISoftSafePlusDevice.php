<?php

declare(strict_types=1);

namespace JuControlDevice\Devices;

use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Exceptions\JuControlException;

class ISoftSafePlusDevice extends AbstractDevice
{
    public function connect(): bool
    {
        try {
            $response = $this->sendKnmCommand('get device data');
            $this->validateResponse($response);
            
            $device = $this->findDeviceBySerial($response['data']);
            if (!$device) {
                throw JuControlException::deviceNotFound($this->serialNumber);
            }

            if ($device['data'][0]['dt'] !== DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS) {
                throw JuControlException::wrongDeviceType(
                    DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS,
                    $device['data'][0]['dt']
                );
            }

            if ($device['status'] !== 'online') {
                throw JuControlException::deviceNotOnline($this->serialNumber);
            }

            $this->deviceData = $device;
            $this->isConnected = true;
            
            return true;
        } catch (JuControlException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw JuControlException::apiRequestFailed('connect', $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->isConnected = false;
        $this->deviceData = [];
    }

    public function refreshData(): array
    {
        if (!$this->isConnected) {
            throw new \RuntimeException('Device not connected');
        }

        try {
            $response = $this->sendKnmCommand('get device data');
            $this->validateResponse($response);
            
            $device = $this->findDeviceBySerial($response['data']);
            if (!$device) {
                throw JuControlException::deviceNotFound($this->serialNumber);
            }

            $this->deviceData = $device;
            
            return $this->parseDeviceData($device);
        } catch (\Exception $e) {
            throw JuControlException::apiRequestFailed('refresh data', $e->getMessage());
        }
    }

    public function sendCommand(string $command, array $parameters = []): bool
    {
        try {
            $response = $this->sendKnmCommand($command, $parameters);
            $this->validateResponse($response);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getDeviceType(): string
    {
        return DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS;
    }

    private function findDeviceBySerial(array $devices): ?array
    {
        foreach ($devices as $device) {
            if ($device['serialnumber'] === $this->serialNumber) {
                return $device;
            }
        }
        return null;
    }

    private function parseDeviceData(array $device): array
    {
        $deviceData = $device['data'][0]['data'];
        
        return [
            'serialNumber' => $device['serialnumber'],
            'status' => $device['status'],
            'deviceType' => 'i-soft safe',
            'installationDate' => strtotime($device['installation_date']),
            'ccuVersion' => $device['data'][0]['sv'],
            'softwareVersion' => $this->parseVersion($deviceData[1]['data'] ?? '', true),
            'hardwareVersion' => $this->parseVersion($deviceData[2]['data'] ?? '', false),
            'deviceId' => $this->getInValue($deviceData, 3),
            'inputHardness' => $this->getInValue($deviceData, 790, 26),
            'targetHardness' => $this->getInValue($deviceData, 790, 8),
            'currentFlow' => $this->getInValue($deviceData, 790, 1617),
            'saltData' => $this->parseSaltData($deviceData),
            'waterStop' => $this->parseWaterStopStatus($deviceData),
            'regeneration' => $this->getInValue($deviceData, 791, 0),
            'totalWater' => $this->getInValue($deviceData, 8),
            'totalRegeneration' => $this->getInValue($deviceData, 791, 3031),
            'activeScene' => $this->parseActiveScene($device['waterscene'] ?? 'normal'),
            'batteryInfo' => $this->parseBatteryInfo($deviceData),
            'serviceInfo' => $this->parseServiceInfo($deviceData),
        ];
    }

    private function parseVersion(string $data, bool $isSoftware): string
    {
        if (strlen($data) < 4) return '';
        
        $offset = $isSoftware ? 2 : 0;
        $minor = intval(substr($data, $offset, 2), 16);
        $major = intval(substr($data, $offset + 2, 2), 16);
        
        if ($minor > 0 && $minor < 10) {
            $minor = str_pad((string)$minor, 2, '0', STR_PAD_LEFT);
        }
        
        return "{$major}.{$minor}";
    }

    private function getInValue(array $deviceData, int $index, ?int $subIndex = null)
    {
        $data = $deviceData[$index]['data'] ?? '';
        
        switch ($index) {
            case 3:
                return strlen($data) === 8 ? (string)hexdec($this->formatEndian(substr($data, 0, 8))) : '';
            case 8:
                return strlen($data) === 8 ? hexdec($this->formatEndian(substr($data, 0, 8))) : 0;
            case 790:
                return $this->parseIndex790($data, $subIndex);
            case 791:
                return $this->parseIndex791($data, $subIndex);
            default:
                return '';
        }
    }

    private function parseIndex790(string $data, ?int $subIndex): mixed
    {
        if (strlen($data) !== 66 || $subIndex === null) return '';
        
        $data = explode(':', $data)[1];
        
        switch ($subIndex) {
            case 8:
            case 10:
            case 26:
                return intval(substr($data, $subIndex * 2, 2), 16);
            case 1617:
                return hexdec($this->formatEndian(substr($data, 32, 4) . '0000'));
            default:
                return '';
        }
    }

    private function parseIndex791(string $data, ?int $subIndex): mixed
    {
        if (strlen($data) !== 66 || $subIndex === null) return '';
        
        $data = explode(':', $data)[1];
        
        switch ($subIndex) {
            case 0:
                $flag = intval(substr($data, 0, 2), 16);
                $binary = decbin($flag);
                return $binary !== '' ? $binary[strlen($binary) - 1] : 0;
            case 3031:
                $low = substr($data, 60, 2);
                $high = substr($data, 62, 2);
                return intval($high . $low, 16);
            default:
                return '';
        }
    }

    private function formatEndian(string $endian, string $format = 'N'): string
    {
        $value = intval($endian, 16);
        $packed = pack('L', $value);
        $unpacked = unpack($format, $packed);
        return sprintf('%08x', $unpacked[1]);
    }

    private function parseSaltData(array $deviceData): array
    {
        $saltData = $this->getInValue($deviceData, 94);
        if (strpos($saltData, ':') === false) {
            return ['level' => 0, 'percent' => 0, 'days' => 0];
        }

        $parts = explode(':', $saltData);
        $level = (int)($parts[0] ?? 0) / 1000;
        
        return [
            'level' => round($level),
            'percent' => min((int)(2 * $level), 100),
            'days' => (int)($parts[1] ?? 0),
        ];
    }

    private function parseWaterStopStatus(array $deviceData): bool
    {
        $status = $this->getInValue($deviceData, 792, 0);
        return strlen($status) === 8 ? (bool)$status[0] : false;
    }

    private function parseActiveScene(string $scene): int
    {
        return match ($scene) {
            'shower' => DeviceConstants::SCENE_SHOWER,
            'heaterfilling' => DeviceConstants::SCENE_HEATER,
            'watering' => DeviceConstants::SCENE_WATERING,
            'washing' => DeviceConstants::SCENE_WASHING,
            default => DeviceConstants::SCENE_NORMAL,
        };
    }

    private function parseBatteryInfo(array $deviceData): array
    {
        $emergencyModule = $this->getInValue($deviceData, 790, 2);
        $hasEmergencySupply = strlen($emergencyModule) > 1 ? 
            (bool)$emergencyModule[strlen($emergencyModule) - 2] : false;

        if (!$hasEmergencySupply) {
            return ['hasEmergencySupply' => false];
        }

        $batteryData = $this->getInValue($deviceData, 93);
        $values = explode(':', $batteryData);

        return [
            'hasEmergencySupply' => true,
            'percentage' => (int)($values[0] ?? 0),
            'runtime' => isset($values[3], $values[2], $values[1]) ? 
                sprintf('%d:%02d:%02d', (int)$values[3], (int)$values[2], (int)$values[1]) : '',
        ];
    }

    private function parseServiceInfo(array $deviceData): array
    {
        $serviceData = $this->getInValue($deviceData, 7);
        $parts = explode(':', $serviceData);

        return [
            'nextServiceDays' => (int)($parts[0] ?? 0),
            'totalServices' => (int)($parts[1] ?? 0),
        ];
    }
}