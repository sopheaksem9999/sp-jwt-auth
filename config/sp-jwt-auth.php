<?php

declare(strict_types=1);

return [
    'guard' => env('SP_JWT_GUARD', 'api'),
    'driver' => env('SP_JWT_DRIVER', 'sp-jwt'),
    'user_provider' => env('SP_JWT_USER_PROVIDER', 'users'),

    'issuer' => env('SP_JWT_ISSUER', env('APP_URL')),
    'audience' => env('SP_JWT_AUDIENCE'),
    'algorithm' => env('SP_JWT_ALGORITHM', 'RS256'),

    'access_ttl_minutes' => (int) env('SP_JWT_ACCESS_TTL_MINUTES', 15),
    'refresh_ttl_days' => (int) env('SP_JWT_REFRESH_TTL_DAYS', 60),
    'clock_skew_seconds' => (int) env('SP_JWT_CLOCK_SKEW_SECONDS', 60),

    'rotate_refresh_tokens' => true,
    'reuse_detection' => env('SP_JWT_REUSE_DETECTION', 'revoke_session'),

    'keys' => [
        'active_kid' => env('SP_JWT_ACTIVE_KID', env('SP_JWT_KEY_ID')),
        'previous_kids' => array_values(array_filter(explode(',', (string) env('SP_JWT_PREVIOUS_KIDS', '')))),
        'compromised_kids' => array_values(array_filter(explode(',', (string) env('SP_JWT_COMPROMISED_KIDS', '')))),
        'jwks_enabled' => filter_var(env('SP_JWT_JWKS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'jwks_route' => env('SP_JWT_JWKS_ROUTE', '/.well-known/sp-jwt-auth/jwks.json'),
        'rotation_grace_days' => (int) env('SP_JWT_KEY_ROTATION_GRACE_DAYS', 30),
        'items' => [],
    ],

    'hash_keys' => [
        'active_id' => env('SP_JWT_HASH_KEY_ID', 'default'),
        'items' => [
            'default' => [
                'state' => 'active',
                'key' => env('SP_JWT_REFRESH_HASH_KEY'),
            ],
        ],
    ],

    'mfa' => [
        'enabled' => filter_var(env('SP_JWT_MFA_ENABLED', false), FILTER_VALIDATE_BOOL),
        'challenge_ttl_minutes' => (int) env('SP_JWT_MFA_CHALLENGE_TTL_MINUTES', 5),
        'otp' => [
            'enabled' => filter_var(env('SP_JWT_OTP_ENABLED', false), FILTER_VALIDATE_BOOL),
            'ttl_minutes' => (int) env('SP_JWT_OTP_TTL_MINUTES', 5),
            'digits' => (int) env('SP_JWT_OTP_DIGITS', 6),
            'max_attempts' => (int) env('SP_JWT_OTP_MAX_ATTEMPTS', 5),
            'resend_cooldown_seconds' => (int) env('SP_JWT_OTP_RESEND_COOLDOWN_SECONDS', 60),
        ],
    ],

    'email_verification' => [
        'enabled' => filter_var(env('SP_JWT_EMAIL_VERIFICATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'ttl_minutes' => (int) env('SP_JWT_EMAIL_VERIFICATION_TTL_MINUTES', 60),
    ],

    'password_reset' => [
        'enabled' => filter_var(env('SP_JWT_PASSWORD_RESET_ENABLED', false), FILTER_VALIDATE_BOOL),
        'ttl_minutes' => (int) env('SP_JWT_PASSWORD_RESET_TTL_MINUTES', 60),
        'max_attempts' => (int) env('SP_JWT_PASSWORD_RESET_MAX_ATTEMPTS', 5),
    ],

    'api_keys' => [
        'enabled' => filter_var(env('SP_JWT_API_KEYS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'prefix' => env('SP_JWT_API_KEY_PREFIX', 'spak'),
        'environment' => env('SP_JWT_API_KEY_ENVIRONMENT', 'live'),
        'default_ttl_days' => env('SP_JWT_API_KEY_DEFAULT_TTL_DAYS'),
        'max_ttl_days' => (int) env('SP_JWT_API_KEY_MAX_TTL_DAYS', 365),
        'allow_never_expires' => filter_var(env('SP_JWT_API_KEY_ALLOW_NEVER_EXPIRES', false), FILTER_VALIDATE_BOOL),
        'public_id_length' => 16,
        'secret_bytes' => 32,
    ],

    'external_identities' => [
        'enabled' => filter_var(env('SP_JWT_EXTERNAL_IDENTITIES_ENABLED', false), FILTER_VALIDATE_BOOL),
        'store_provider_tokens' => filter_var(env('SP_JWT_EXTERNAL_STORE_PROVIDER_TOKENS', false), FILTER_VALIDATE_BOOL),
        'encrypt_provider_tokens' => filter_var(env('SP_JWT_EXTERNAL_ENCRYPT_PROVIDER_TOKENS', true), FILTER_VALIDATE_BOOL),
        'providers' => [],
    ],

    'oauth_server' => [
        'enabled' => filter_var(env('SP_JWT_OAUTH_SERVER_ENABLED', false), FILTER_VALIDATE_BOOL),
        'route_prefix' => env('SP_JWT_OAUTH_ROUTE_PREFIX', 'oauth'),
        'access_token_format' => env('SP_JWT_OAUTH_ACCESS_TOKEN_FORMAT', 'opaque'),
        'access_token_ttl_minutes' => (int) env('SP_JWT_OAUTH_ACCESS_TOKEN_TTL_MINUTES', 60),
        'refresh_token_ttl_days' => (int) env('SP_JWT_OAUTH_REFRESH_TOKEN_TTL_DAYS', 30),
        'auth_code_ttl_minutes' => (int) env('SP_JWT_OAUTH_AUTH_CODE_TTL_MINUTES', 10),
        'require_pkce_for_public_clients' => filter_var(env('SP_JWT_OAUTH_REQUIRE_PKCE_FOR_PUBLIC_CLIENTS', true), FILTER_VALIDATE_BOOL),
        'issuer' => env('SP_JWT_OAUTH_ISSUER', env('SP_JWT_ISSUER', env('APP_URL'))),
        'scopes' => [],
    ],

    'optional_modules' => [
        'account_security' => false,
        'api_keys' => false,
        'external_identity' => false,
        'oauth_server' => false,
    ],
];
