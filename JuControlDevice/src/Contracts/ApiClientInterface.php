<?php

declare(strict_types=1);

namespace JuControlDevice\Contracts;

interface ApiClientInterface
{
    public function authenticate(string $username, string $password): bool;
    public function sendRequest(string $url, array $data = []): array;
    public function getToken(): ?string;
    public function isAuthenticated(): bool;
}