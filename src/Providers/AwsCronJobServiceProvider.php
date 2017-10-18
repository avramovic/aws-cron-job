<?php

namespace Avram\AwsCronJob\Providers;

use Avram\AwsCronJob\Commands\AwsScheduleRunCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AwsCronJobServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            dirname(dirname(__DIR__)).'/config/' => base_path('config/'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    public function registerCommands()
    {
        $this->app->singleton(AwsScheduleRunCommand::class, function($app)
        {
            return new AwsScheduleRunCommand(app(Schedule::class));
        });

        $this->commands(AwsScheduleRunCommand::class);
    }
}
