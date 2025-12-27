<?php

namespace Intrfce\LaravelReportable\Tests\Fixtures\Reportables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Intrfce\LaravelReportable\Reportable;
use Intrfce\LaravelReportable\Tests\Fixtures\Models\User;

class UserReport extends Reportable
{
    public function query(): QueryBuilder|EloquentBuilder
    {
        return User::query()->select(['id', 'name', 'email', 'status', 'role']);
    }

    public function filename(): string
    {
        return 'users.csv';
    }

    public function mapHeaders(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Full Name',
            'email' => 'Email Address',
            'status' => 'Status',
            'role' => 'Role',
        ];
    }
}
