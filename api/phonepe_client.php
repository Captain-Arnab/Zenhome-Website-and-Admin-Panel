<?php
/**
 * Shared PhonePe v2 Standard Checkout client.
 * Load this after autoload and dotenv. Uses .env: PHONEPE_CLIENT_ID, PHONEPE_CLIENT_VERSION,
 * PHONEPE_CLIENT_SECRET, PHONEPE_ENV, PHONEPE_REDIRECT_URL.
 *
 * PHONEPE_ENV: PRODUCTION (default when unset), UAT, STAGE; SANDBOX is an
 * alias of UAT (PhonePe's test environment). Any other value throws instead
 * of silently charging in production.
 */

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\Env;

if (!function_exists('phonepe_env')) {
    function phonepe_env(): string {
        $envName = strtoupper(trim((string) ($_ENV['PHONEPE_ENV'] ?? getenv('PHONEPE_ENV') ?: '')));
        return match ($envName) {
            '', 'PRODUCTION', 'PROD' => Env::PRODUCTION,
            'UAT', 'SANDBOX'         => Env::UAT,
            'STAGE'                  => Env::STAGE,
            default => throw new RuntimeException('PHONEPE_ENV must be PRODUCTION, UAT, SANDBOX or STAGE.'),
        };
    }
}

if (!function_exists('get_phonepe_client')) {
    function get_phonepe_client(): StandardCheckoutClient {
        $clientId     = $_ENV['PHONEPE_CLIENT_ID'] ?? '';
        $clientVer    = (int) ($_ENV['PHONEPE_CLIENT_VERSION'] ?? 1);
        $clientSecret = $_ENV['PHONEPE_CLIENT_SECRET'] ?? '';
        return StandardCheckoutClient::getInstance($clientId, $clientVer, $clientSecret, phonepe_env());
    }
}
