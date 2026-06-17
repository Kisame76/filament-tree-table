<?php

declare(strict_types=1);

namespace Kisame76\FilamentTreeTable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    protected $guarded = [];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
