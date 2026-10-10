<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class);
    }

    /**
     * @return HasMany<LoginChallenge, $this>
     */
    public function loginChallenges(): HasMany
    {
        return $this->hasMany(LoginChallenge::class);
    }

    /**
     * Scope the query to active users.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The slug of the user's (primary) role.
     */
    public function roleSlug(): ?string
    {
        return $this->roles->first()?->slug;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * All permission slugs granted through the user's roles.
     *
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->sort()
            ->values()
            ->toBase();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissionSlugs()->contains($slug);
    }

    /**
     * Determine whether the given section is assigned to the user.
     */
    public function belongsToSection(Section|int $section): bool
    {
        $sectionId = $section instanceof Section ? $section->getKey() : $section;

        return $this->sections->contains('id', $sectionId);
    }

    /**
     * The phone number with all but the country code, the first and the last three digits masked,
     * e.g. "+40 744 556 213" becomes "+40 7•• ••• 213".
     */
    public function maskedPhone(): ?string
    {
        if (blank($this->phone)) {
            return null;
        }

        $phone = trim($this->phone);
        $totalDigits = preg_match_all('/\d/', $phone);
        $countryCodeDigits = str_starts_with($phone, '+') && str_contains($phone, ' ')
            ? preg_match_all('/\d/', strstr($phone, ' ', true))
            : 0;
        $visibleLeading = $countryCodeDigits + 1;
        $visibleTrailing = min(3, max(0, $totalDigits - $visibleLeading));

        $digitIndex = 0;

        return preg_replace_callback('/\d/', function (array $match) use (&$digitIndex, $visibleLeading, $visibleTrailing, $totalDigits): string {
            $index = $digitIndex++;

            return $index < $visibleLeading || $index >= $totalDigits - $visibleTrailing ? $match[0] : '•';
        }, $phone);
    }
}
