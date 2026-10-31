<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RequestIdentity
{
    public static function get(Request $request): string
    {
        if (! $request->attributes->has('store_request_id')) {
            $request->attributes->set('store_request_id', (string) Str::uuid());
        }

        return $request->attributes->get('store_request_id');
    }
}
