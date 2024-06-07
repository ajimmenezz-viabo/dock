<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Response;
use App\Exceptions\AuthorizationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return response()->json(['message' => $exception->getMessage() ?? "Not found"], Response::HTTP_NOT_FOUND);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json(['message' => 'Method Not Allowed'], Response::HTTP_METHOD_NOT_ALLOWED);
        }

        if ($exception instanceof ValidationException) {
            return $this->handleValidationException($exception);
        }

        if ($exception instanceof TokenInvalidException) {
            return response()->json(['message' => 'Token is invalid'], 401);
        }

        if ($exception instanceof TokenExpiredException) {
            return response()->json(['message' => 'Token has expired'], 401);
        }

        if ($exception instanceof JWTException) {
            return response()->json(['message' => 'Error while decoding the token'], 401);
        }

        if ($exception instanceof HttpException) {
            return response()->json(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json($exception->getError(), 200);
        }


        return parent::render($request, $exception);
    }

    /**
     * Manage the validation exception
     *
     * @param  ValidationException $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleValidationException(ValidationException $exception)
    {
        $errors = $exception->validator->getMessageBag()->toArray();

        if (env('APP_ENV') !== 'production') {
            return response()->json([
                'message' => 'The given data was invalid. Please check the documentation for more information',
                'dev_errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            return response()->json([
                'message' => 'The given data was invalid. Please check the documentation for more information',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
