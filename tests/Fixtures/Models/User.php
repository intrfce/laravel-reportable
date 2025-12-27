<?php

namespace Intrfce\LaravelReportable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $guarded = [];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
