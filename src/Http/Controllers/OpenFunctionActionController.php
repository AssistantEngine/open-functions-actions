<?php

namespace AssistantEngine\OpenFunctions\Actions\Http\Controllers;

use AssistantEngine\OpenFunctions\Core\Contracts\AbstractOpenFunction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

abstract class OpenFunctionActionController
{
    /**
     * Handle action calls with an identifier and function name.
     *
     * Expected route: POST /actions/{identifier}/{functionName}
     *
     * @param Request $request
     * @param string  $identifier
     * @param string  $functionName
     * @return JsonResponse
     */
    public function handleAction(Request $request, string $identifier, string $functionName): JsonResponse
    {
        $openFunctionInstance = $this->resolveOpenFunction($identifier);

        // Ensure the method exists in the instance.
        if (!method_exists($openFunctionInstance, $functionName)) {
            return Response::json([
                'error' => "Function '{$functionName}' not found for identifier '{$identifier}'."
            ], 404);
        }

        // Retrieve parameters from the request.
        // Assuming JSON body is sent.
        $input = $request->json()->all();

        $result = $openFunctionInstance->callMethod($functionName, $input);

        return Response::json($result->toArray());
    }

    abstract function resolveOpenFunction(string $identifier = 'default'): AbstractOpenFunction;
}
