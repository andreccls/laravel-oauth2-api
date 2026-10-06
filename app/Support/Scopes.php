<?php

namespace App\Support;

/** The only place that declares OAuth2 scopes. Registered in AppServiceProvider, enforced by routes/api.php. */
final class Scopes
{
    public const TASKS_READ = 'tasks:read';

    public const TASKS_WRITE = 'tasks:write';

    /** @var array<string, string> scope => description shown on the consent screen */
    public const ALL = [
        self::TASKS_READ => 'Read your tasks',
        self::TASKS_WRITE => 'Create, change and delete your tasks',
    ];
}
