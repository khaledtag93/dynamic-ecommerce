<?php

namespace App\Models;

use App\Models\GrowthCustomerScore;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_as',
    ];

    protected $hidden = [
        'password',
        'role_as',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role_as' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }


    public function growthScore()
    {
        return $this->hasOne(GrowthCustomerScore::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions()
    {
        return Permission::query()
            ->whereHas('roles.users', fn ($query) => $query->where('users.id', $this->id));
    }

    public function isLegacyAdmin(): bool
    {
        return (int) $this->role_as === 1;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isLegacyAdmin() && ($this->roles()->doesntExist() || $this->hasRole('super_admin'));
    }

    public function hasRole(string $slug): bool
    {
        if ($slug === 'super_admin' && $this->isLegacyAdmin() && $this->roles()->doesntExist()) {
            return true;
        }

        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isLegacyAdmin() && ! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return true;
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function primaryRoleName(): string
    {
        if ($this->roles()->exists()) {
            return (string) optional($this->roles()->orderBy('roles.id')->first())->name;
        }

        return $this->isLegacyAdmin() ? 'Super Admin' : 'Customer';
    }
}
