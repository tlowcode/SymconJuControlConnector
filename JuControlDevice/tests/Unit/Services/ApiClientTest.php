<?php

declare(strict_types=1);

namespace JuControlDevice\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use JuControlDevice\Services\ApiClient;
use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Exceptions\JuControlException;

class ApiClientTest extends TestCase
{
    private ApiClient $client;

    protected function setUp(): void
    {
        $this->client = new ApiClient('https://example.com');
    }

    public function testInitialStateIsNotAuthenticated(): void
    {
        $this->assertFalse($this->client->isAuthenticated());
        $this->assertNull($this->client->getToken());
    }

    public function testAuthenticationFailsWithInvalidCredentials(): void
    {
        $this->expectException(JuControlException::class);
        $this->expectExceptionMessage('Authentication failed');
        
        // This will fail because we're using a mock URL
        $this->client->authenticate('invalid', 'credentials');
    }

    public function testGetTokenReturnsNullWhenNotAuthenticated(): void
    {
        $this->assertNull($this->client->getToken());
    }

    public function testSendRequestWithInvalidUrlThrowsException(): void
    {
        $this->expectException(JuControlException::class);
        
        $this->client->sendRequest('/invalid-endpoint');
    }
}