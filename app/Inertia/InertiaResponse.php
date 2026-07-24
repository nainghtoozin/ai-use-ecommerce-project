<?php

namespace App\Inertia;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as BaseResponse;

class InertiaResponse extends BaseResponse
{
    protected function resolveFlashData(Request $request): array
    {
        return ['flash' => Inertia::pullFlashed($request)];
    }
}
