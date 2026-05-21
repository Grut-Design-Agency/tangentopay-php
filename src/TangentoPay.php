<?php

declare(strict_types=1);

namespace TangentoPay;

/**
 * Convenience helpers — shortcuts so common tasks don't require multiple lines.
 */
class TangentoPay
{
    public const VERSION = '0.2.5';

    /**
     * Perform the two-step login + OTP flow and return a ready-to-use MerchantClient.
     *
     * @param array{baseUrl?: string, timeoutS?: int} $options
     */
    public static function login(
        string $email,
        string $password,
        string $otp,
        array $options = [],
    ): MerchantClient {
        $bootstrap = new MerchantClient($options);
        $bootstrap->auth->login($email, $password);
        $token = $bootstrap->auth->verifyOtp($email, $otp);

        return new MerchantClient(array_merge($options, ['apiToken' => $token->accessToken]));
    }
}
