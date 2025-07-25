<?php

declare(strict_types=1);

namespace JuControlDevice\Contracts;

interface DeviceInterface
{
    public function connect(): bool;
    public function disconnect(): void;
    public function refreshData(): array;
    public function sendCommand(string $command, array $parameters = []): bool;
    public function getDeviceType(): string;
    public function getSerialNumber(): string;
    public function isOnline(): bool;
}