<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth;

use Override;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sopheak\JwtAuth\Console\BoostCommand;
use Sopheak\JwtAuth\Console\InstallCommand;
use Sopheak\JwtAuth\Console\JwksCommand;
use Sopheak\JwtAuth\Console\KeysCommand;
use Sopheak\JwtAuth\Console\McpCommand;
use Sopheak\JwtAuth\Console\PruneCommand;
use Sopheak\JwtAuth\Console\SetupCommand;
use Sopheak\JwtAuth\Console\ValidateCommand;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Http\Middleware\AuthenticateOAuthToken;
use Sopheak\JwtAuth\Http\Middleware\AuthenticateApiKey;
use Sopheak\JwtAuth\Http\Middleware\AuthenticateJwt;
use Sopheak\JwtAuth\Http\Middleware\RequireAnyApiKeyScope;
use Sopheak\JwtAuth\Http\Middleware\RequireAnyJwtScope;
use Sopheak\JwtAuth\Http\Middleware\RequireAnyOAuthScope;
use Sopheak\JwtAuth\Http\Middleware\RequireApiKeyScope;
use Sopheak\JwtAuth\Guards\JwtGuard;
use Sopheak\JwtAuth\Http\Middleware\RequireJwtScope;
use Sopheak\JwtAuth\Http\Middleware\RequireOAuthClient;
use Sopheak\JwtAuth\Http\Middleware\RequireOAuthScope;
use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\Services\OAuthConsentRepository;
use Sopheak\JwtAuth\Services\OAuthScopeRepository;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Services\PasswordResetBroker;
use Sopheak\JwtAuth\Signing\ConfigSigningKeyRepository;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Support\HookRegistry;

final class CoreSpJwtAuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sp-jwt-auth.php', 'sp-jwt-auth');

        $this->app->singleton(HookRegistry::class);
        $this->app->singleton(SigningKeyRepository::class, ConfigSigningKeyRepository::class);
        $this->app->singleton(HashKeyRepository::class);
        $this->app->singleton(SecretHasher::class);
        $this->app->singleton(JwksFormatter::class);
        $this->app->singleton(JwtTokenService::class);
        $this->app->singleton(MfaChallengeBroker::class);
        $this->app->singleton(OtpChallengeBroker::class);
        $this->app->singleton(EmailVerificationBroker::class);
        $this->app->singleton(PasswordResetBroker::class);
        $this->app->singleton(ApiKeyService::class);
        $this->app->singleton(ExternalIdentityStore::class);
        $this->app->singleton(OAuthClientRepository::class);
        $this->app->singleton(OAuthConsentRepository::class);
        $this->app->singleton(OAuthScopeRepository::class);
        $this->app->singleton(OAuthServerService::class);
    }

    public function boot(): void
    {
        // Automatically load migrations from the package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/sp-jwt-auth.php' => config_path('sp-jwt-auth.php'),
        ], 'sp-jwt-auth-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sp-jwt-auth-migrations');

        if (config('sp-jwt-auth.first_factor_otp.user_model') !== null) {
            $this->app->bind(FirstFactorUserResolver::class, DefaultFirstFactorUserResolver::class);
        }

        Auth::extend('sp-jwt', function (Application $app, string $name, array $config): JwtGuard {
            $providerName = $config['provider'] ?? config('sp-jwt-auth.user_provider', 'users');
            /** @var AuthManager $auth */
            $auth = $app->make('auth');
            /** @var Request $request */
            $request = $app->make('request');

            return new JwtGuard(
                $app->make(JwtTokenService::class),
                $auth->createUserProvider($providerName),
                $request,
            );
        });

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('sp.jwt', AuthenticateJwt::class);
        $router->aliasMiddleware('sp.jwt.scope', RequireJwtScope::class);
        $router->aliasMiddleware('sp.jwt.any_scope', RequireAnyJwtScope::class);
        $router->aliasMiddleware('sp.api_key', AuthenticateApiKey::class);
        $router->aliasMiddleware('sp.api_key.scope', RequireApiKeyScope::class);
        $router->aliasMiddleware('sp.api_key.any_scope', RequireAnyApiKeyScope::class);
        $router->aliasMiddleware('sp.oauth', AuthenticateOAuthToken::class);
        $router->aliasMiddleware('sp.oauth.scope', RequireOAuthScope::class);
        $router->aliasMiddleware('sp.oauth.any_scope', RequireAnyOAuthScope::class);
        $router->aliasMiddleware('sp.oauth.client', RequireOAuthClient::class);

        if ((bool) config('sp-jwt-auth.keys.jwks_enabled', true)) {
            Route::group([], __DIR__ . '/../routes/jwks.php');
        }

        if ((bool) config('sp-jwt-auth.oauth_server.enabled', false)) {
            Route::group([], __DIR__ . '/../routes/oauth.php');
        }

        $this->registerFirstFactorOtpRateLimiters();
        $this->registerFirstFactorOtpRoutes();

        if ((bool) config('sp-jwt-auth.token_endpoints.enabled', false)) {
            Route::group([], __DIR__ . '/../routes/token.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SetupCommand::class,
                ValidateCommand::class,
                KeysCommand::class,
                JwksCommand::class,
                PruneCommand::class,
                BoostCommand::class,
                McpCommand::class,
            ]);
        }
    }

    private function registerFirstFactorOtpRateLimiters(): void
    {
        RateLimiter::for('sp-jwt-ffotp-request', function (Request $request): array {
            $decay = (int) config('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60);

            $destination = strtolower((string) $request->input('destination', 'unknown'));

            return [
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 5))
                    ->by('sp-jwt-ffotp-destination:' . $destination),
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.request_per_ip', 20))
                    ->by('sp-jwt-ffotp-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('sp-jwt-ffotp-verify', function (Request $request): array {
            $decay = (int) config('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60);

            return [
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.verify_per_ip', 30))
                    ->by('sp-jwt-ffotp-verify-ip:' . $request->ip()),
            ];
        });
    }

    private function registerFirstFactorOtpRoutes(): void
    {
        if (! (bool) config('sp-jwt-auth.first_factor_otp.enabled', false)) {
            return;
        }

        Route::group([], __DIR__ . '/../routes/otp.php');
    }
}
