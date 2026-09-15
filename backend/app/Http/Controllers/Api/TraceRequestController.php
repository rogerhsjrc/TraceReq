<?php

namespace App\Http\Controllers\Api;

use App\Application\Requests\CreateRequest;
use App\Application\Requests\CreateRequestData;
use App\Application\Requests\ListRequests;
use App\Application\Requests\ViewRequest;
use App\Application\Requests\SubmitRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTraceRequestRequest;
use App\Http\Resources\RequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TraceRequestController extends Controller
{
    public function index(ListRequests $listRequests): AnonymousResourceCollection
    {
        return RequestResource::collection($listRequests());
    }

    public function store(
        StoreTraceRequestRequest $request,
        CreateRequest $createRequest,
    ): JsonResponse {
        $validated = $request->validated();
        $traceRequest = $createRequest(new CreateRequestData(
            title: $validated['title'],
            description: $validated['description'],
            requestedAmount: $validated['requested_amount'],
            currencyCode: $validated['currency_code'],
        ));

        return (new RequestResource($traceRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id, ViewRequest $viewRequest): RequestResource
    {
        return new RequestResource($viewRequest($id));
    }

    public function submit(
        string $id,
        SubmitRequest $submitRequest
    ): RequestResource
    {
        return new RequestResource($submitRequest($id));
    }
}
