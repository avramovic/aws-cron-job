<?php namespace Avram\AwsCronJob\Commands;

use Avram\AwsCronJob\Ec2InstanceInfo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

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
     * Create a new command instance.
     *
     * @param Schedule $schedule
     */
    public function __construct(Schedule $schedule)
    {
        parent::__construct($schedule);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $skip = explode(',', config('awscronjob.skip_environments', 'local'));
        $skip = array_map('trim', $skip);
        $appEnv = config('app.env', 'local');
        if (in_array($appEnv, $skip)) {
            $this->line('Local environment detected! Will run scheduled tasks!');
            $this->runSchedules();
            exit(0);
        }

        $ec2 = new Ec2InstanceInfo(config('awscronjob.connection', []));

        if (config('awscronjob.cache_enabled') && Cache::has('aws-cronjob-ec2-instances')) {
            $activeInstances = Cache::get('aws-cronjob-ec2-instances');
        } else {
            try {
                $activeInstances = $ec2->allInstanceIds(config('awscronjob.aws_environment', 'production'));
                if (!empty($activeInstances)) {
                    Cache::put('aws-cronjob-ec2-instances', $activeInstances, config('awscronjob.cache_time', 5));
                }
            } catch (Exception $ex) {
                $activeInstances = [];
                Log::error($ex->getMessage());
            }
        }

        if (empty($activeInstances)) {
            $this->line('No EC2 instances returned. Error is logged (if any).');
            $this->runIfEnabled();
        }

        if (config('awscronjob.cache_enabled') && Cache::store('file')->has('aws-cronjob-ec2-instance-id')) {
            $thisInstance = Cache::store('file')->get('aws-cronjob-ec2-instance-id');
        } else {
            try {
                $thisInstance = $ec2->thisInstanceId();
                if (!empty($thisInstance)) {
                    Cache::store('file')->forever('aws-cronjob-ec2-instance-id', $thisInstance);
                }
            } catch (Exception $ex) {
                $thisInstance = null;
                Log::error($ex->getMessage());
            }
        }

        if (empty($thisInstance)) {
            $this->line('Could not retrieve this instance ID. Error is logged.');
            $this->runIfEnabled();
        }

        if ($thisInstance == $activeInstances[0]) {
            $this->info('This is a leader instance, running scheduled tasks!');
            $this->runSchedules();
            exit(0);
        }

        $this->error('This instance is not a leader, not running scheduled tasks!');
    }

    protected function runIfEnabled($thenStop = true)
    {
        if (config('awscronjob.run_on_errors', true)) {
            $this->line('Will run scheduled tasks (per config).');
            $this->runSchedules();
            if ($thenStop) {
                exit(0);
            }
        } else {
            $this->error('Won\'t run scheduled tasks (per config).');
        }
    }

    protected function runSchedules()
    {
        if (method_exists($this, 'fire')) {
            return $this->fire();
        }

        parent::handle();
    }
}
