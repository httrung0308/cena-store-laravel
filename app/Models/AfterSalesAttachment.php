<?php

namespace App\Models;

use App\Enums\AttachmentDisk;
use App\Enums\AttachmentMimeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([])]
#[Hidden(['path', 'disk', 'sha256'])]
class AfterSalesAttachment extends DomainModel
{
    protected $table = 'after_sales_attachments';

    protected $attributes = [
        'disk' => 'private',
    ];

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'after_sales_request_id' => 'integer',
            'uploaded_by' => 'integer',
            'disk' => AttachmentDisk::class,
            'mime_type' => AttachmentMimeType::class,
            'size_bytes' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function afterSalesRequest(): BelongsTo
    {
        return $this->belongsTo(AfterSalesRequest::class, 'after_sales_request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
