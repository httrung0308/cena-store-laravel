<?php

namespace Database\Factories;

use App\Enums\AttachmentMimeType;
use App\Models\AfterSalesAttachment;
use App\Models\AfterSalesRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AfterSalesAttachment> */
class AfterSalesAttachmentFactory extends Factory
{
    protected $model = AfterSalesAttachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'after_sales_request_id' => AfterSalesRequest::factory(),
            'uploaded_by' => User::factory(),
            'path' => 'rma/'.Str::uuid().'.jpg',
            'mime_type' => AttachmentMimeType::ImageJpeg,
            'size_bytes' => 1024,
            'sha256' => hash('sha256', (string) Str::uuid()),
            'created_at' => now(),
        ];
    }
}
