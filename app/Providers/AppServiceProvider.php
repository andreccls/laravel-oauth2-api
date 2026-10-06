<?php

namespace App\Providers;

use App\Support\Principal;
use App\Support\Scopes;
use Carbon\CarbonInterval;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Device-code grant is not part of this project's scope (YAGNI): no table, no routes.
        Passport::$deviceCodeGrantEnabled = false;
    }

    public function boot(): void
    {
        // Lazy loading throws outside production: an N+1 becomes a failing test, not a slow endpoint.
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->configurePassport();
        $this->configureRateLimiting();
        $this->configureOpenApi();
    }

    /** Scramble is a dev dependency: the spec is generated at dev time (`make openapi`) and committed. */
    private function configureOpenApi(): void
    {
        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::oauth2()->flows(function ($flows) {
                    $scopes = fn (OAuthFlow $flow) => collect(Scopes::ALL)->each(fn ($d, $s) => $flow->addScope($s, $d));
                    $flows->authorizationCode((new OAuthFlow)->authorizationUrl(url('/oauth/authorize'))->tokenUrl(url('/oauth/token'))->refreshUrl(url('/oauth/token')));
                    $flows->clientCredentials((new OAuthFlow)->tokenUrl(url('/oauth/token')));
                    $scopes($flows->authorizationCode);
                    $scopes($flows->clientCredentials);
                }));
            });
        }
    }

    private function configurePassport(): void
    {
        Passport::tokensCan(Scopes::ALL);
        Passport::tokensExpireIn(CarbonInterval::minutes((int) config('api.access_token_ttl_minutes')));
        Passport::refreshTokensExpireIn(CarbonInterval::days((int) config('api.refresh_token_ttl_days')));

        // Headless consent screen: the "view" is JSON that a first-party front end renders.
        Passport::authorizationView(fn (array $params) => response()->json([
            'client' => ['id' => $params['client']->getKey(), 'name' => $params['client']->name],
            'scopes' => array_map(fn ($s) => ['id' => $s->id, 'description' => $s->description], $params['scopes']),
            'auth_token' => $params['authToken'],
            'state' => $params['request']->input('state'),
            'approve' => ['method' => 'POST', 'url' => url('/oauth/authorize'), 'fields' => ['state', 'client_id', 'auth_token']],
            'deny' => ['method' => 'DELETE', 'url' => url('/oauth/authorize'), 'fields' => ['state', 'client_id', 'auth_token']],
        ]));
    }

    private function configureRateLimiting(): void
    {
        // Per OAuth client (not per token: minting new tokens must not reset the budget).
        RateLimiter::for('api', function (Request $request) {
            $principal = $request->attributes->get(Principal::ATTRIBUTE);

            return Limit::perMinute((int) config('api.rate_limit'))->by($principal instanceof Principal ? $principal->clientId : (string) $request->ip());
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
