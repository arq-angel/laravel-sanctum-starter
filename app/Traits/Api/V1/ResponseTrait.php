<?php

namespace App\Traits\Api\V1;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

trait ResponseTrait
{
    use DebugTrait;

    /**
     * @param bool $isSuccess
     * @param string $message
     * @param array $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public function successResponse(
        bool   $isSuccess = true,
        string $message = 'Operation successful.',
        array  $data = [],
        int    $statusCode = Response::HTTP_OK
    ): JsonResponse
    {
        return response()->json([
            'isSuccess' => $isSuccess,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * @param bool $isSuccess
     * @param string $message
     * @param array $data
     * @param \Throwable|null $exception
     * @param int $statusCode
     * @return JsonResponse
     */
    public function errorResponse(
        bool       $isSuccess = false,
        string     $message = 'Operation unsuccessful!',
        array      $data = [],
        \Throwable $exception = null,
        int        $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse
    {
        // Base response structure provided by the user
        $returnData = [
            'isSuccess' => $isSuccess,
            'message' => $message,
            'data' => $data,
        ];

        // Map exception to a structured response
        // Using the Laravel built in getMessage() and getTrace by confirming the $exception as \Throwable
        // Instead of repeating instanceof \Throwable in multiple conditions, centralize it to avoid redundancy
        if ($exception instanceof \Throwable) {
            $exceptionDetails = $this->mapExceptionToResponse($exception);

            // Update status code and add exception-specific errors or debug info
            $statusCode = $exceptionDetails['status'] ?? $statusCode;

            if (isset($exceptionDetails['errors'])) {
                $returnData['errors'] = $exceptionDetails['errors'];
            }

            // Include debug information if applicable
            if ($this->isLocalEnvironment() || $this->debuggable()) {
                $returnData['debug'] = [
                    'message' => $exceptionDetails['message'],
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTrace(),
                ];

                if ($exception instanceof \Error) {
                    $returnData['debug'] = [
                        'note' => 'This is a PHP runtime error. Check the method or code causing the issue.',
                        ...$returnData['debug'],
                    ];
                }
            }
        }

        return response()->json($returnData, $statusCode);
    }

    /**
     * @param \Throwable $exception
     * @return array
     */
    protected function mapExceptionToResponse(\Throwable $exception): array
    {
        $exceptionData = [
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'message' => 'An unexpected error occurred!',
        ];

        if ($exception instanceof MethodNotAllowedHttpException) {
            $exceptionData = [
                'status' => Response::HTTP_METHOD_NOT_ALLOWED,
                'message' => 'The HTTP method used is not allowed for this route.',
            ];
        } elseif ($exception instanceof ThrottleRequestsException) {
            $exceptionData = [
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'message' => 'Too many requests! Please try again later.',
            ];
        } elseif ($exception instanceof HttpException) {
            $exceptionData = [
                'status' => $exception->getStatusCode(),
                'message' => $exception->getMessage(),
            ];
        } elseif ($exception instanceof ValidationException) {
            $exceptionData = [
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $exception->errors(),
                'message' => 'Validation failed!',
            ];
        } elseif ($exception instanceof ModelNotFoundException) {
            $exceptionData = [
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'Resource not found!',
            ];
        } elseif ($exception instanceof AuthenticationException) {
            $exceptionData = [
                'status' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthenticated access!',
            ];
        } elseif ($exception instanceof AuthorizationException) {
            $exceptionData = [
                'status' => Response::HTTP_FORBIDDEN,
                'message' => 'Unauthorized access!',
            ];
        } elseif ($exception instanceof \Error) {
            $exceptionData = [
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'A server error occurred: ',
            ];
        }

        return $exceptionData;
    }
}
