<?php

namespace App\Http\Resources\Customer;

use App\Http\Resources\StoreResource;
use App\Models\User;
use Illuminate\Http\Request;

abstract class OwnedResource extends StoreResource
{
    /** @return array<string, mixed> */
    final public function toArray(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && ! $user->trashed() && $this->ownerId() === $user->id, 404);

        return $this->fields($request);
    }

    abstract protected function ownerId(): ?int;

    /** @return array<string, mixed> */
    abstract protected function fields(Request $request): array;
}
