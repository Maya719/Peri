<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Builder;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name', 'team_id', 'assigned_users', 'assignment', 'is_default'];

    protected $casts = [
        'assigned_users' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Local scope to filter roles by team_id.
     *
     * @param Builder $query
     * @param mixed|null $adminId
     * @return Builder
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
