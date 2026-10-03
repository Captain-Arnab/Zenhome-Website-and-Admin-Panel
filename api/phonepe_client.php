<?php
/**
 * Shared PhonePe v2 Standard Checkout client.
 * Load this after autoload and dotenv. Uses .env: PHONEPE_CLIENT_ID, PHONEPE_CLIENT_VERSION,
 * PHONEPE_CLIENT_SECRET, PHONEPE_ENV (PRODUCTION|STAGE|UAT), PHONEPE_REDIRECT_URL.
 */

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\Env;

if (!function_exists('get_phonepe_client')) {
    function get_phonepe_client(): StandardCheckoutClient {
        $clientId     = $_ENV['PHONEPE_CLIENT_ID'] ?? '';
        $clientVer    = (int) ($_ENV['PHONEPE_CLIENT_VERSION'] ?? 1);
        $clientSecret = $_ENV['PHONEPE_CLIENT_SECRET'] ?? '';
        $envName      = strtoupper($_ENV['PHONEPE_ENV'] ?? 'PRODUCTION');
        $env = match ($envName) {
            'STAGE'      => Env::STAGE,
            'UAT'        => Env::UAT,
            'PRODUCTION' => Env::PRODUCTION,
            default     => Env::PRODUCTION,
        };
        return StandardCheckoutClient::getInstance($clientId, $clientVer, $clientSecret, $env);
    }
}
