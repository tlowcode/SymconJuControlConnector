<?php

declare(strict_types=1);

namespace JuControlDevice\Exceptions;

use Exception;

class JuControlException extends Exception
{
    public static function authenticationFailed(string $message = 'Authentication failed'): self
    {
        return new self($message, 201);
    }

    public static function wrongDeviceType(string $expected, string $actual): self
    {
        return new self("Wrong device type. Expected: {$expected}, Got: {$actual}", 202);
    }

    public static function deviceNotOnline(string $serialNumber): self
    {
        return new self("Device {$serialNumber} is not online", 203);
    }

    public static function deviceNotFound(string $serialNumber): self
    {
        return new self("Device {$serialNumber} not found", 204);
    }

    public static function informationIncomplete(string $message = 'Required information is incomplete'): self
    {
        return new self($message, 205);
    }

    public static function apiRequestFailed(string $url, string $message = ''): self
    {
        return new self("API request to {$url} failed: {$message}");
    }
}