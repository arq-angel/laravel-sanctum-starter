<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Response;

trait ApiControllerTraits
{
    public function debuggable(): bool
    {
        return true;
    }

    public function isLocalEnvironment(): bool
    {
        // Debug info is included only in development environments
//        return app()->environment('local');

        return true;
    }

    public function paginationLimit(): int
    {
        return 25;
    }

    public function getAccessTokenExpiryTime(): int
    {
        return 60 * 60; // 60 minutes
    }

    public function successResponse($data = null, $message = null, $code = Response::HTTP_OK)
    {
        return response()->json([
            'isSuccess' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public function errorResponse($message = 'An error occurred', $errors = null, $debug = null, $code = Response::HTTP_BAD_REQUEST)
    {
        $returnData = [
            'isSuccess' => false,
            'message' => $message,
            'errors' => $errors,
        ];

        if ($debug) $returnData['debug'] = $debug;

        return response()->json([
            $returnData
        ], $code);
    }

}
