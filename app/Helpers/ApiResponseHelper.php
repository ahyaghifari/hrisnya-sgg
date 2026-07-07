<?php

namespace App\Helpers;

class ApiResponseHelper
{
    public static function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function successPaginated($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'total' => $data->total(),
                'count' => $data->count(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'total_pages' => $data->lastPage()
            ]
        ], $statusCode);
    }

    public static function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    public static function notFound($message = 'Resource Not Found')
    {
        return self::error($message, 404);
    }

    public static function unauthorized($message = 'Unauthorized')
    {
        return self::error($message, 401);
    }

    public static function forbidden($message = 'Forbidden')
    {
        return self::error($message, 403);
    }

    public static function validationError($errors, $message = 'Validation Error')
    {
        return self::error($message, 422, $errors);
    }

    public static function serverError($message = 'Internal Server Error')
    {
        return self::error($message, 500);
    }
}
