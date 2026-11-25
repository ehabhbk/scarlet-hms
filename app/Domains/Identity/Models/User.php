<?php

namespace App\Domains\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'locale',
        'theme',
        'branch_id',
        'department_id',
        'job_title',
        'avatar_path',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_default');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\App\Domains\Scheduling\Models\Appointment::class, 'doctor_id');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(\App\Domains\Encounters\Models\Encounter::class, 'doctor_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains('name', 'super_admin');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;

        return $this->roles->whereIn('name', $roles)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionNames()->contains($permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * @return array<int, int>
     */
    public function accessibleBranchIds(): array
    {
        $ids = $this->branches()->pluck('branches.id')->all();

        if ($this->branch_id) {
            $ids[] = $this->branch_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function defaultBranch(): ?Branch
    {
        $branch = $this->branches()->wherePivot('is_default', true)->first();

        return $branch ?? $this->branch;
    }

    public function primaryRoleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    }
}
