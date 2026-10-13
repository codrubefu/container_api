<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Database\SqlServer2014SchemaGrammar;
use App\Models\Permission;
use App\Models\User;
use App\Services\Sms\LogSmsSender;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });

        Connection::resolverFor('sqlsrv', function ($pdo, string $database, string $prefix, array $config): SqlServerConnection {
            $connection = new SqlServerConnection($pdo, $database, $prefix, $config);
            $connection->setSchemaGrammar(new SqlServer2014SchemaGrammar($connection));

            return $connection;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureApiDocumentation();
    }

    /**
     * Permission slugs (e.g. `users.manage`) are resolved from the user's roles, so they work with
     * `can:` middleware, `$this->authorize()` and `Gate::allows()`. Any other ability falls through
     * to the policies, which can add finer checks (e.g. section ownership).
     */
    private function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return Permission::isKnown($ability) ? $user->hasPermission($ability) : null;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth.login', function (Request $request): array {
            $email = Str::transliterate(Str::lower($request->string('email')->toString()));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth.challenge', function (Request $request): array {
            return [
                Limit::perMinute(10)->by('challenge:'.$request->string('challenge_id').'|'.$request->ip()),
                Limit::perMinute(30)->by('challenge-ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Documents Sanctum token auth so the docs UI can send `Authorization: Bearer <token>`.
     */
    private function configureApiDocumentation(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }
}
