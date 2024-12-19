<?php

declare(strict_types=1);

namespace Customer\Api\Controllers;

use App\Constants\HttpHeader;
use App\Instrumentation\Datadog\Instrument;
use Aptive\Component\Http\Exceptions\NotFoundHttpException;
use Aptive\Component\Http\HttpStatus;
use Aptive\Component\JsonApi\Exceptions\ValidationException;
use Aptive\Illuminate\Http\JsonApi\ErrorResponse;
use Customer\Api\Commands\CreatePaymentCommand;
use Customer\Api\Commands\CreatePaymentHandler;
use Customer\Api\Exceptions\AutoPayStatusException;
use Customer\Api\Exceptions\InvalidParametersException;
use Customer\Api\Exceptions\InvalidPaymentHoldDateException;
use Customer\Api\Exceptions\PaymentFailedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentController
{
    public const int AQUIRED_LOCK_SECONDS = 5; // The amount of time the lock will be held for before it is released

    /**
     * @param Request $request
     * @param int $customerId
     * @param CreatePaymentHandler $handler
     *
     * @throws ValidationException
     *
     * @return Response
     */
    public function create(Request $request, int $customerId, CreatePaymentHandler $handler): Response
    {
        $lock = Cache::lock(name: sprintf('%d:payment:create', $customerId), seconds: self::AQUIRED_LOCK_SECONDS);

        if (!$lock->get()) {
            Log::notice('Payment creation request is being throttled.', ['customer_id' => $customerId]);

            return response(
                content: ['message' => sprintf('Another payment is being processed for the customer %d', $customerId)],
                status: Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $command = new CreatePaymentCommand(
            (int)$request->header(HttpHeader::APTIVE_PESTROUTES_OFFICE_ID),
            $customerId,
            $request->get('payoff_outstanding_balance'),
            $request->get('appointment_id'),
            $request->get('amount'),
            $request->header('Origin'),
        );

        try {
            $handler->handle($command);
        } catch (AutoPayStatusException|InvalidPaymentHoldDateException|PaymentFailedException $exception) {
            Log::notice($exception->getMessage(), [
                'customer_id' => $command->customerId,
                'appointment_id' => $command->appointmentId
            ]);

            return ErrorResponse::fromException($request, $exception, HttpStatus::UNPROCESSABLE_ENTITY);
        } catch (InvalidParametersException $exception) {
            Log::notice($exception->getMessage());

            return ErrorResponse::fromException($request, $exception, HttpStatus::BAD_REQUEST);
        } catch (NotFoundHttpException $exception) {
            Log::notice($exception->getMessage());

            return ErrorResponse::fromException($request, $exception, HttpStatus::NOT_FOUND);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage(), [
                'stack_trace' => $exception->getTrace()
            ]);
            Instrument::error($exception);

            return ErrorResponse::fromException($request, $exception, HttpStatus::INTERNAL_SERVER_ERROR);
        } finally {
            $lock->release();
        }

        Log::info(message: 'Payment processed successfully.');

        return response(['success' => 'success'], Response::HTTP_OK);
    }
}
