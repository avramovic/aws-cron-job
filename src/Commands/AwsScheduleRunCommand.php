<?php namespace Avram\AwsCronJob\Commands;

use Avram\AwsCronJob\Ec2InstanceInfo;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AwsScheduleRunCommand extends ScheduleRunCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'aws:schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands, but only on a single EC2 instance';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher)
    {
        if ($this->shouldRunEnvironment()) {
            $this->line('Local environment detected! Will run scheduled tasks!');
            $this->runSchedules($schedule, $dispatcher);
            exit(0);
        }

        $this->ec2 = new Ec2InstanceInfo(config('awscronjob.connection', []));

        $activeInstances = $this->getInstancesList();

        if (empty($activeInstances)) {
            $this->line('No EC2 instances returned. Error is logged (if any).');
            $this->runIfEnabled();
        }

        $thisInstance = $this->getThisInstanceId();

        if (empty($thisInstance)) {
            $this->line('Could not retrieve this instance ID. Error is logged.');
            $this->runIfEnabled();
        }

        if ($thisInstance == $activeInstances[0]) {
            $this->info('This is a leader instance, running scheduled tasks!');
            $this->runSchedules($schedule, $dispatcher);
            exit(0);
        }

        $this->error('This instance is not a leader, not running scheduled tasks!');
    }

    protected function runIfEnabled($thenStop = true)
    {
        if (config('awscronjob.run_on_errors', true)) {
            $this->line('Will run scheduled tasks (per config).');
            $this->runSchedules($schedule, $dispatcher);
            if ($thenStop) {
                exit(0);
            }
        } else {
            $this->error('Won\'t run scheduled tasks (per config).');
        }
    }

    protected function runSchedules(Schedule $schedule, Dispatcher $dispatcher)
    {
        if (method_exists($this, 'fire')) {
            return $this->fire();
        }

        parent::handle($schedule, $dispatcher);
    }

    protected function getInstancesList()
    {
        if (config('awscronjob.cache_enabled') && Cache::has('aws-cronjob-ec2-instances')) {
            return Cache::get('aws-cronjob-ec2-instances');
        }

        try {
            $activeInstances = $this->ec2->allInstanceIds(config('awscronjob.aws_environment', 'production'));
            if (!empty($activeInstances)) {
                Cache::put('aws-cronjob-ec2-instances', $activeInstances, config('awscronjob.cache_time', 5));
            }
        } catch (Exception $ex) {
            $activeInstances = [];
            Log::error($ex->getMessage());
        }

        return $activeInstances;
    }

    protected function getThisInstanceId()
    {
        if (config('awscronjob.cache_enabled') && Cache::store('file')->has('aws-cronjob-ec2-instance-id')) {
            return Cache::store('file')->get('aws-cronjob-ec2-instance-id');
        }

        try {
            $thisInstance = $this->ec2->thisInstanceId();
            if (!empty($thisInstance)) {
                Cache::store('file')->forever('aws-cronjob-ec2-instance-id', $thisInstance);
            }
        } catch (Exception $ex) {
            $thisInstance = null;
            Log::error($ex->getMessage());
        }

        return $thisInstance;
    }

    protected function shouldRunEnvironment()
    {
        $skip = explode(',', config('awscronjob.skip_environments', 'local'));
        $skip = array_map('trim', $skip);
        $appEnv = config('app.env', 'local');

        return in_array($appEnv, $skip);
    }
}
