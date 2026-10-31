<?php

namespace App\Http\Resources;

use App\Support\RequestIdentity;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class StoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function with(Request $request): array
    {
        return ['success' => true, 'message' => 'Thành công.', 'meta' => ['request_id' => RequestIdentity::get($request)]];
    }

    protected static function newCollection(mixed $resource): AnonymousResourceCollection
    {
        return new StoreResourceCollection($resource, static::class);
    }

    /** @return array<string, mixed> */
    public function resolveResourceData(Request $request): array
    {
        $data = parent::resolveResourceData($request);
        if ($this->resource instanceof Model) {
            foreach (['status', 'order_status', 'payment_status', 'state'] as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $this->resource->getAttribute($field);
                    if ($value instanceof BackedEnum && method_exists($value, 'label')) {
                        $data[$field.'_label'] = $value->label();
                        if (in_array($field, ['order_status', 'state'], true)) {
                            $data['status_label'] = $value->label();
                        }
                    }
                }
            }
        }

        return $data;
    }
}
