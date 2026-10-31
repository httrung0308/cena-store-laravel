<?php

namespace App\Http\Resources\Admin;

use App\Enums\RoleSlug;
use App\Http\Resources\StoreResource;
use App\Models\User;
use Illuminate\Http\Request;

abstract class AdminResource extends StoreResource
{
    /** @return array<string, mixed> */
    final public function toArray(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && ! $user->trashed() && $user->hasRole(RoleSlug::Admin), 403);

        return $this->fields($request);
    }

    /** @return array<string, mixed> */
    abstract protected function fields(Request $request): array;
}
