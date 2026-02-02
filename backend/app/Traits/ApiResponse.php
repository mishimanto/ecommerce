<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function error($message = 'Error occurred', $errors = null, $statusCode = 400)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    protected function created($data = null, $message = 'Resource created successfully')
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent($message = 'No content')
    {
        return $this->success(null, $message, 204);
    }

    protected function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, null, 401);
    }

    protected function forbidden($message = 'Forbidden')
    {
        return $this->error($message, null, 403);
    }

    protected function notFound($message = 'Resource not found')
    {
        return $this->error($message, null, 404);
    }

    protected function validationError($errors, $message = 'Validation failed')
    {
        return $this->error($message, $errors, 422);
    }

    protected function serverError($message = 'Internal server error')
    {
        return $this->error($message, null, 500);
    }
}