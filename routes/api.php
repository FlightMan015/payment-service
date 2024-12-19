<?php

declare(strict_types=1);

use App\Api\Controllers\AccountController;
use App\Api\Controllers\CreditCardController;
use App\Api\Controllers\InvoiceController;
use App\Api\Controllers\JobController;
use App\Api\Controllers\PaymentController;
use App\Api\Controllers\PaymentMethodController;
use App\Api\Controllers\ProcessEligibleRefundsController;
use App\Api\Controllers\ProcessPaymentsController;
use App\Api\Controllers\ProcessPaymentsSyncReportController;
use App\Api\Controllers\ProcessScheduledPaymentsController;
use App\Api\Controllers\ScheduledPaymentController;
use App\Api\Controllers\SubscriptionController;
use App\Api\Controllers\TokenexGatewayController;
use App\Api\Controllers\WorldpayGatewayController;
use App\Api\Middleware\AuthenticateFailedJobsHandlingApiKey;
use App\Api\Middleware\AuthenticatePaymentProcessingApiKey;
use App\Http\Middleware\PestRoutesOfficeIdHeader;
use Customer\Api\Controllers\PaymentController as OldPaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// old endpoints that will be marked as deprecated
Route::post('/customers/{id}/payments', [OldPaymentController::class, 'create'])->middleware(PestRoutesOfficeIdHeader::class)->name('createPayment');

// new endpoints
Route::middleware(AuthenticatePaymentProcessingApiKey::class)->group(static function () {
    Route::prefix('accounts')->as('accounts.')->group(static function () {
        Route::patch('{accountId}/autopay-status', [AccountController::class, 'updateAutopayStatus'])->name('update-autopay-status');
        Route::get('{accountId}/primary-payment-method', [AccountController::class, 'primaryPaymentMethod'])->name('get-primary-payment-method');
        Route::get('{accountId}/autopay-payment-method', [AccountController::class, 'autopayPaymentMethod'])->name('get-autopay-payment-method');
    });
    Route::prefix('subscriptions')->as('subscriptions.')->group(static function () {
        Route::patch('{subscriptionId}/autopay-status', [SubscriptionController::class, 'updateAutopayStatus'])->name('update-autopay-status');
    });

    Route::prefix('payments')->as('payments.')->group(static function () {
        Route::get('', [PaymentController::class, 'get'])->name('get');
        Route::post('', [PaymentController::class, 'create'])->name('create');

        Route::post('authorization', [PaymentController::class, 'authorize'])->name('authorize');
        Route::post('authorization-and-capture', [PaymentController::class, 'authorizeAndCapture'])->name('authorize-and-capture');

        Route::get('{paymentId}', [PaymentController::class, 'find'])->name('find');
        Route::patch('{paymentId}', [PaymentController::class, 'update'])->name('update');
        Route::post('{paymentId}/capture', [PaymentController::class, 'capture'])->name('capture');
        Route::post('{paymentId}/electronic-refund', [PaymentController::class, 'electronicRefund'])->name('electronic-refund');
        Route::post('{paymentId}/manual-refund', [PaymentController::class, 'manualRefund'])->name('manual-refund');
        Route::post('{paymentId}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
        Route::post('{paymentId}/terminate', [PaymentController::class, 'terminate'])->name('terminate');
        Route::post('{paymentId}/process-suspended', [PaymentController::class, 'processSuspended'])->name('process');

        Route::post('failed-refunds-report', [PaymentController::class, 'failedRefundsReport'])->name('failed-refunds-report');
        Route::get('{paymentId}/transactions', [PaymentController::class, 'getTransactions'])->name('getTransactions');
        Route::get('{paymentId}/transactions/{transactionId}', [PaymentController::class, 'findTransaction'])->name('findTransaction');
    });

    Route::prefix('scheduled-payments')->as('scheduled-payments.')->group(static function () {
        Route::post('', [ScheduledPaymentController::class, 'create'])->name('create');
        Route::post('{scheduledPaymentId}/cancel', [ScheduledPaymentController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('payment-methods')->as('payment-methods.')->group(static function () {
        Route::get('', [PaymentMethodController::class, 'get'])->name('get');
        Route::get('{paymentMethodId}', [PaymentMethodController::class, 'find'])->name('find');
        Route::post('', [PaymentMethodController::class, 'create'])->name('create');
        Route::patch('{paymentMethodId}', [PaymentMethodController::class, 'update'])->name('update');
        Route::post('{paymentMethodId}/validation', [PaymentMethodController::class, 'validate'])->name('validate');
        Route::delete('{paymentMethodId}', [PaymentMethodController::class, 'delete'])->name('delete');
    });

    Route::post('process-payments', ProcessPaymentsController::class)->name('processPayments');
    Route::post('process-scheduled-payments', ProcessScheduledPaymentsController::class)->name('processScheduledPayments');
    Route::post('process-eligible-refunds', ProcessEligibleRefundsController::class)->name('processEligibleRefunds');

    Route::prefix('data-sync')->as('data-sync.')->group(static function () {
        Route::get('payments-report', ProcessPaymentsSyncReportController::class)->name('payments-report');
    });

    Route::prefix('credit-cards')->as('credit-cards.')->group(static function () {
        Route::post('validation', [CreditCardController::class, 'validate'])->name('validate');
    });

    Route::prefix('gateways')->as('gateways.')->group(static function () {
        Route::prefix('tokenex')->as('tokenex.')->group(static function () {
            Route::post('authentication-keys', [TokenexGatewayController::class, 'generateAuthenticationKey'])->name('generate-authentication-key');
            Route::post('update-accounts', [TokenexGatewayController::class, 'updateAccounts'])->name('update-accounts');
        });
        Route::prefix('worldpay')->as('worldpay.')->group(static function () {
            Route::post('populate-expiration-data', [WorldpayGatewayController::class, 'populateExpirationData'])->name('populate-expiration-data');
            Route::post('check-ach-status', [WorldpayGatewayController::class, 'checkAchStatus']);
        });
    });

    Route::prefix('invoices')->as('invoices.')->group(static function () {
        Route::get('', [InvoiceController::class, 'get'])->name('get');
        Route::get('{invoiceId}', [InvoiceController::class, 'find'])->name('find');
    });

    // TODO: remove this after jobs logs issues are investigated
    Route::post('test/jobs-logs/{jobsQuantity?}', [JobController::class, 'testLogs']);
});

Route::prefix('failed-jobs')->middleware(AuthenticateFailedJobsHandlingApiKey::class)->group(static function () {
    Route::get('', [JobController::class, 'get'])->name('get');
    Route::post('retry', [JobController::class, 'retryFailedJob'])->name('retryFailedJob');
    Route::get('queues', [JobController::class, 'availableQueues'])->name('queues');
});
