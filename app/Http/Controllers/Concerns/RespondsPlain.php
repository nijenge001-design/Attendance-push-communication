<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;

trait RespondsPlain
{
    protected function plain(string $body = 'OK', int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type' => 'text/plain',
            'Pragma' => 'no-cache',
            'Date' =>  gmdate('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => 'no-store',
        ]);
    }
}
