<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ProductManagementAccess
{
    /** Lock the actor and current role before aggregate/variant locks. */
    public static function lock(User $actor): User
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Actor locking requires a transaction.');
        }
        $current = User::whereKey($actor->id)->lockForUpdate()->first();
        abort_unless($current && $current->is_active, 403);
        $assignment = UserRole::where('user_id', $current->id)->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'staff']))->lockForUpdate()->first();
        abort_unless($assignment, 403);

        return $current;
    }
}
