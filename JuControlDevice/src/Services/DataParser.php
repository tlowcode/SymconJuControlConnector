<?php

declare(strict_types=1);

namespace JuControlDevice\Services;

class DataParser
{
    public function parseDeviceData(array $rawData, string $deviceType): array
    {
        $parser = $this->getParserForDeviceType($deviceType);
        return $parser->parse($rawData);
    }

    public function parseVersionInfo(string $data, bool $isSoftware = true): string
    {
        if (strlen($data) < 4) {
            return '';
        }

        $offset = $isSoftware ? 2 : 0;
        $minor = intval(substr($data, $offset, 2), 16);
        $major = intval(substr($data, $offset + 2, 2), 16);

        if ($minor > 0 && $minor < 10) {
            $minor = str_pad((string)$minor, 2, '0', STR_PAD_LEFT);
        }

        return "{$major}.{$minor}";
    }

    public function parseSaltData(string $data): array
    {
        if (strpos($data, ':') === false) {
            return ['level' => 0, 'percent' => 0, 'days' => 0];
        }

        $parts = explode(':', $data);
        $level = (int)($parts[0] ?? 0) / 1000; // Convert to kg
        $percent = (int)(2 * $level);
        $days = (int)($parts[1] ?? 0);

        return [
            'level' => round($level),
            'percent' => min($percent, 100),
            'days' => $days,
        ];
    }

    public function parseEndianValue(string $hex, string $format = 'N'): string
    {
        $value = intval($hex, 16);
        $packed = pack('L', $value);
        $unpacked = unpack($format, $packed);
        
        return sprintf('%08x', $unpacked[1]);
    }

    public function combine8BitTo16Bit(int $low, int $high): int
    {
        return bindec(decbin($high) . decbin($low));
    }

    private function getParserForDeviceType(string $deviceType): DeviceParserInterface
    {
        switch ($deviceType) {
            case DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS:
                return new ISoftSafePlusParser();
            case DeviceConstants::DEVICE_TYPE_I_SOFT_PLUS:
                return new ISoftPlusParser();
            default:
                throw new \InvalidArgumentException("Unsupported device type: {$deviceType}");
        }
    }
}

interface DeviceParserInterface
{
    public function parse(array $rawData): array;
}