<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

trait BuildsMailData
{
    protected static function frontendUrl(string $path = '/'): string
    {
        $base = rtrim((string) config('api.frontend_url', config('app.url')), '/');

        return $base.'/'.ltrim($path, '/');
    }

    protected static function loginUrl(): string
    {
        return self::frontendUrl((string) config('api.login_path', '/login'));
    }

    protected static function firstName(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? 'there' : explode(' ', $name)[0];
    }
}
