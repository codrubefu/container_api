<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['slug', 'description'])]
class Permission extends Model
{
    /**
     * Every permission known to the application, keyed by slug.
     *
     * @var array<string, string>
     */
    public const DEFINITIONS = [
        'projects.view' => 'Vizualizare proiecte',
        'projects.create' => 'Creare proiecte',
        'projects.close' => 'Închidere proiecte',
        'project_items.manage' => 'Adăugare/ștergere materiale și alocare secții',
        'project_items.work' => 'Actualizare status și cantitate reală (doar pe secțiile proprii)',
        'comments.create' => 'Adăugare comentarii',
        'sections.view' => 'Vizualizare secții',
        'users.view' => 'Vizualizare utilizatori',
        'users.manage' => 'Administrare utilizatori',
        'roles.manage' => 'Administrare roluri și permisiuni',
    ];

    /**
     * Determine whether the given ability is one of the application's permission slugs.
     */
    public static function isKnown(string $ability): bool
    {
        return array_key_exists($ability, self::DEFINITIONS);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
