<?php

namespace App\Models;

use App\Enums\RoleSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['full_name', 'email', 'phone', 'avatar_path'])]
#[Hidden(['password', 'auth_version', 'remember_token', 'roles', 'role_assignments', 'password_reset_challenge'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $attributes = [
        'is_active' => '1',
        'auth_version' => '1',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'auth_version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function assignedByUserRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'assigned_by');
    }

    public function passwordResetChallenge(): HasOne
    {
        return $this->hasOne(PasswordResetChallenge::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function createdByStockImports(): HasMany
    {
        return $this->hasMany(StockImport::class, 'created_by');
    }

    public function postedByStockImports(): HasMany
    {
        return $this->hasMany(StockImport::class, 'posted_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'actor_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'actor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'confirmed_by');
    }

    public function requestedByRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'requested_by');
    }

    public function approvedByRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'approved_by');
    }

    public function paidByRefunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'paid_by');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'created_by');
    }

    public function receivedByShipmentReturnItems(): HasMany
    {
        return $this->hasMany(ShipmentReturnItem::class, 'received_by');
    }

    public function inspectedByShipmentReturnItems(): HasMany
    {
        return $this->hasMany(ShipmentReturnItem::class, 'inspected_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function repliedByReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'replied_by');
    }

    public function dispositionByAfterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'disposition_by');
    }

    public function handlerAfterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'handled_by');
    }

    public function repairExpenseApprovedByAfterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class, 'repair_expense_approved_by');
    }

    public function afterSalesHistories(): HasMany
    {
        return $this->hasMany(AfterSalesHistory::class, 'actor_id');
    }

    public function afterSalesAttachments(): HasMany
    {
        return $this->hasMany(AfterSalesAttachment::class, 'uploaded_by');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->using(UserRole::class)->as('assignment')->withPivot(['id', 'assigned_by', 'assigned_at']);
    }

    public function hasRole(RoleSlug $role): bool
    {
        return $this->roles()->where('slug', $role->value)->exists();
    }
}
