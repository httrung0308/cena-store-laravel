<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class AfterSalesAttachmentResource extends AdminResource
{
    /** @return array<string, mixed> */
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->id,
            'after_sales_request_id' => $this->after_sales_request_id,
            'uploaded_by' => $this->uploaded_by,
            'mime_type' => $this->mime_type?->value,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
