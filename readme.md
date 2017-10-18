# AWeSome Cron Job

This is a Laravel 5.x package which enables running cron jobs on only one EC2 instance in your ElasticBeanstalk load-balanced AWS setup.

## Problem

In a typical AWS load-balanced environment, all EC2 instances are exactly the same, and all of them will run any configured cron jobs (if configured through `.ebextensions` fodler). This can be fine in some cases, but can result in cron jobs being executed more than once, which can be a problem, for example your system can send same email notifications more than once.

### AWS solutions

AWS recommends using `leader_only` flag for cron jobs in your `.ebextensions` folder, which will set up cron jobs only on a firstly deployed instance. That is fine until the load-balancer kicks in, creates a new non-leader instance and later decides it does not need additional instances and kills one of your instances, probably the longest-running one, which may just be your leader instance; and since `leader_only` is applied during deployment only, you're left without your leader instance and no cron jobs will be run.

There are more solutions to this problem, but most of them require additional setup on the AWS side. Some of them even suggest you to set up another EC2 instance only for cron jobs. This also can be fine in some cases, but I just didn't like it.

### AWS Cron Job

This package provides an easy way to fire cron jobs from a single EC2 instance without changing your AWS setup. It will simply get all currently running instances for a single AWS EB environment from your AWS account, sort them alphabetically and then check if current instance ID is the same as the first one in the list. When instances are changed, the list changes dynamically so you don't have to worry about that!

To boost performance, this package will cache list of your instances for 5 minutes by default, but you can change that. Also, since instance ID is never changed (instances can be changed but their IDs can't, at least not automatically), current instance ID will be cached forever. Everything is cached locally on the same instance (`file` cache driver).

## Install

Simply require it via composer:

    composer require avram/aws-cron-job
    
Laravel 5.5 (and newer) should automatically discover and enable the service provider when you install this package with composer. For older versions you must add service provider to you `config/app.php` manually:

	'providers' => [
	
		// ...
	
		Avram\AwsCronJob\Providers\AwsCronJobServiceProvider::class,
		
	],
    
## Setup

### AWS credentials

This package relies on the AWS PHP SDK, which will automatically pick up your API key and secret key from `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` environment variables, so it is the best to simply define these in your environment. You can, however, hard-code these values (see below). 

### Configuration

This package provides a config file which you **MUST** publish with:

    php artisan vendor:publish --provider="Avram\AwsCronJob\Providers\AwsCronJobServiceProvider"
    
After that you can setup the package in `config/awscronjob.php`:

	<?php
	
	return [
	    'connection'        => [
	        'region'  => env('AWS_REGION', 'ca-central-1'),
	        'version' => env('AWS_API_VERSION', 'latest'),
	//        'credentials' => [
	//            'key'    => env('AWS_ACCESS_KEY_ID','my-access-key-id'),
	//            'secret' => env('AWS_SECRET_ACCESS_KEY','my-secret-access-key'),
	//        ],
	    ],
	    'aws_environment'   => env('AWS_CRON_ENV', 'app-production'),
	    'skip_environments' => env('AWS_CRON_SKIP_APP_ENV', 'local'),
	    'run_on_errors'     => env('AWS_CRON_RUN_ON_ERRORS', true),
	    'cache_time'        => env('AWS_CRON_CACHE_TIME', 5),
	];
	
* `connection` AWS region and API version (credentials will be picked from your env by default)
* `aws_environment` AWS environment name used to filter instances
* `skip_environments` A comma separated list of Laravel app environments to skip and automatically execute cron jobs (default: `local`)
* `run_on_errors` Should it execute tasks if an error in communication with AWS servers happens (default: `true`)
* `cache_time` How long should it cache list of all instanes (in minutes, default: `5`)

Since at least `aws_environment` will be different for each of your environments, it is the best not to mess with the published config file too much, but to set up configuration with the following environment variables directly in your Elasticbeanstalk console:

* `AWS_ACCESS_KEY_ID`
* `AWS_SECRET_ACCESS_KEY`
* `AWS_REGION`
* `AWS_CRON_ENV`
* `AWS_API_VERSION` (optional)
* `AWS_CRON_SKIP_APP_ENV` (optional)
* `AWS_CRON_RUN_ON_ERRORS` (optional)
* `AWS_CRON_CACHE_TIME` (optional)

Note that your AWS IAM user must have `ec2:Describe*` permissions to access list of your instances. Just in case, I gave my user whole `arn:aws:iam::aws:policy/AmazonEC2ReadOnlyAccess`
    
## Usage

Simply, instead of executing your cron job as:

	php artisan schedule:run
	
...change	it to:

	php artisan aws:schedule:run
	
...and that's it! ;)	