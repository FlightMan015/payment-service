<?php

declare(strict_types=1);

namespace App\Providers;

use App\Api\Exceptions\PaymentValidationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const string HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(static function () {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });

        $this->bindRouteParameters();
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', static fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }

    private function bindRouteParameters(): void
    {
        Route::bind(key: 'paymentId', binder: $this->validateThatParameterIsUUID(parameterName: 'payment_id'));
        Route::bind(key: 'paymentMethodId', binder: $this->validateThatParameterIsUUID(parameterName: 'payment_method_id'));
        Route::bind(key: 'invoiceId', binder: $this->validateThatParameterIsUUID(parameterName: 'invoice_id'));
        Route::bind(key: 'transactionId', binder: $this->validateThatParameterIsUUID(parameterName: 'transaction_id'));
        Route::bind(key: 'accountId', binder: $this->validateThatParameterIsUUID(parameterName: 'account_id'));
        Route::bind(key: 'subscriptionId', binder: $this->validateThatParameterIsUUID(parameterName: 'subscription_id'));
    }

    private function validateThatParameterIsUUID(string $parameterName): \Closure
    {
        return static function (string $parameter) use ($parameterName) {
            if (!Str::of($parameter)->isUuid()) {
                $message = __('validation.parameter_invalid_uuid', ['parameter' => Str::of($parameterName)->snake()->replace('_', ' ')->toString()]);
                throw new PaymentValidationException(message: $message);
            }

            return $parameter;
        };
    }
}
