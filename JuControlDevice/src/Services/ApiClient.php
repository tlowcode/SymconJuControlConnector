<?php

declare(strict_types=1);

namespace JuControlDevice\Services;

use JuControlDevice\Contracts\ApiClientInterface;
use JuControlDevice\Exceptions\JuControlException;
use JuControlDevice\Config\DeviceConstants;

class ApiClient implements ApiClientInterface
{
    private ?string $token = null;
    private string $baseUrl;
    private array $defaultHeaders;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = [
            'User-Agent' => 'JuControl-Connector/2.0',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ];
    }

    public function authenticate(string $username, string $password): bool
    {
        $data = [
            'group' => 'register',
            'command' => 'login',
            'name' => 'login',
            'user' => $username,
            'role' => 'customer',
        ];

        // Different password handling for different servers
        if ($this->baseUrl === DeviceConstants::SERVER_KNM) {
            $data['password'] = md5($password);
            $data['nohash'] = $password;
        } else {
            $data['password'] = $password;
        }

        try {
            $response = $this->sendRequest('/', $data);
            
            if (isset($response['status']) && $response['status'] === 'ok' && isset($response['token'])) {
                $this->token = $response['token'];
                return true;
            }
            
            throw JuControlException::authenticationFailed('Invalid credentials or server response');
        } catch (\Exception $e) {
            throw JuControlException::authenticationFailed($e->getMessage());
        }
    }

    public function sendRequest(string $url, array $data = []): array
    {
        $fullUrl = $this->baseUrl . $url;
        
        if ($this->token && !isset($data['token'])) {
            $data['token'] = $this->token;
        }

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_POST => !empty($data),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => DeviceConstants::API_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => DeviceConstants::CONNECTION_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw JuControlException::apiRequestFailed($fullUrl, $error ?: 'Unknown curl error');
        }

        if ($httpCode !== 200) {
            throw JuControlException::apiRequestFailed($fullUrl, "HTTP {$httpCode}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw JuControlException::apiRequestFailed($fullUrl, 'Invalid JSON response');
        }

        return $decoded;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function isAuthenticated(): bool
    {
        return $this->token !== null;
    }

    private function buildHeaders(): array
    {
        $headers = [];
        foreach ($this->defaultHeaders as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }
        return $headers;
    }
}