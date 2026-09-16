```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Iclock\Concerns;

use Illuminate\Http\Response;

trait RespondsPlain
{
    protected function plain(string $body = 'OK', int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'no-store',
        ]);
    }
}
```
