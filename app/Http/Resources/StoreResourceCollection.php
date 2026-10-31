<?php

namespace App\Http\Resources;

use App\Support\RequestIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StoreResourceCollection extends AnonymousResourceCollection
{
    /** @return array<string, mixed> */
    public function with(Request $request): array
    {
        return ['success' => true, 'message' => 'Thành công.', 'meta' => ['request_id' => RequestIdentity::get($request)]];
    }
}
