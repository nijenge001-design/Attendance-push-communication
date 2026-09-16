<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Laravel 11+/13 style – application is created via bootstrap/app.php
    // If your skeleton still uses CreatesApplication, uncomment the trait:
    // use CreatesApplication;
}
