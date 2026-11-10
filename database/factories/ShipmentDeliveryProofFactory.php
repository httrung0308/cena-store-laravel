<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentDeliveryProof;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ShipmentDeliveryProof> */
class ShipmentDeliveryProofFactory extends Factory
{
    protected $model = ShipmentDeliveryProof::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'uploaded_by' => User::factory()->staff(),
            'path' => 'shipment-proofs/'.Str::ulid().'.jpg',
            'sha256' => hash('sha256', Str::uuid()->toString()),
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'created_at' => now(),
        ];
    }
}
