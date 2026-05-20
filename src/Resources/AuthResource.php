<?php

declare(strict_types=1);

namespace TangentoPay\Resources;

use TangentoPay\HttpClient;
use TangentoPay\Models\AuthToken;
use TangentoPay\Models\LoginChallenge;

class AuthResource
{
    public function __construct(private readonly HttpClient $http) {}

    public function login(string $email, string $password): LoginChallenge
    {
        $data = $this->http->post('/auth/login', ['email' => $email, 'password' => $password]);
        return LoginChallenge::fromArray((array) $data);
    }

    public function verifyOtp(string $email, string $otp): AuthToken
    {
        $data = $this->http->post('/auth/verify-otp', ['email' => $email, 'otp' => $otp]);
        return AuthToken::fromArray((array) $data);
    }

    /** @return array<string, mixed> */
    public function me(): array
    {
        return (array) $this->http->get('/auth/me');
    }

    public function logout(): void
    {
        $this->http->post('/auth/logout');
    }

    public function changePassword(string $currentPassword, string $newPassword): void
    {
        $this->http->post('/auth/change-password', [
            'current_password' => $currentPassword,
            'new_password'     => $newPassword,
        ]);
    }
}
