<?php

namespace App\Providers;

use App\Listeners\DisconnectRabbitMq;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickTerminated;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::automaticallyEagerLoadRelationships();
        Gate::guessPolicyNamesUsing( function (string $modelClass){
            return 'App\\Policies\\Api\\v1\\'.class_basename($modelClass).'Policy';
        });
        if (class_exists(RequestTerminated::class)) {
            Event::listen(RequestTerminated::class, DisconnectRabbitMq::class);
            Event::listen(TaskTerminated::class, DisconnectRabbitMq::class);
            Event::listen(TickTerminated::class, DisconnectRabbitMq::class);
        }

//        DevCommands::artisan('serve --host=localhost --port=9000', 'server');
        DevCommands::artisan('octane:start --server=frankenphp --host=0.0.0.0 --port=6060', 'server')->green();
        DevCommands::artisan('pail --timeout=0 -v', 'logs')->blue();

        DevCommands::artisan('device:heartbeat --watch', 'device-heartbeat')->blue();
        DevCommands::artisan('device:commands-watch', 'pending-commands')->blue();
        DevCommands::artisan('device:commands-watch --all --limit=20', 'all-commands')->blue();
        DevCommands::artisan('queue:work rabbitmq --queue=default -v', 'queue')->yellow();
        DevCommands::artisan('reverb:start --host="0.0.0.0" --port=6061 --debug', 'reverb')->orange();

//        DevCommands::node('dev -- --host 0.0.0.0','vite');

//        DevCommands::nodeExec('tailwindcss -i resources/css/app.css -o public/css/app.css --watch', 'tailwind');
    }
}
