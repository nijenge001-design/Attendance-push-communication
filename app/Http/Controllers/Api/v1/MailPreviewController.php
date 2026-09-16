<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Mail\ResetPasswordMail;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Http\Response;

class MailPreviewController extends Controller
{
    /**
     * GET /api/v1/mail/preview/{template}  (APP_DEBUG only)
     *
     * Templates: reset-password, password-changed, welcome
     */
    public function show(string $template): Response
    {
        if (! config('app.debug')) {
            abort(404);
        }

        $user = new User([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'username' => 'jane.doe',
            'role' => 'operator',
        ]);

        $mailable = match ($template) {
            'reset-password' => ResetPasswordMail::for($user, 'preview-token'),
            'password-changed' => PasswordChangedMail::for($user),
            'welcome' => WelcomeUserMail::for($user),
            default => abort(404),
        };

        return response($mailable->render())->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
