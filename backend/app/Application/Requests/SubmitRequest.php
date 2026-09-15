<?php

namespace App\Application\Requests;

use App\Models\TraceRequest;
use App\Domain\Requests\RequestStatus;

final class SubmitRequest{
    public function __invoke(string $id): TraceRequest
    {
        $traceRequest = TraceRequest::findOrFail($id);

        //If request has already submitted just return it 
        if($traceRequest->status === RequestStatus::Submitted){
            return $traceRequest;
        }

        $traceRequest->status = RequestStatus::Submitted;
        $traceRequest->save();

        return $traceRequest;
    }
}