<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([])]
#[Hidden([])]
class Role extends DomainModel
{
    protected $table = 'roles';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'slug' => RoleSlug::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')->using(UserRole::class)->as('assignment')->withPivot(['id', 'assigned_by', 'assigned_at']);
    }
}
