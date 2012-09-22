# zf2-cron

[![Build Status](https://secure.travis-ci.org/heartsentwined/zf2-cron.png)](http://travis-ci.org/heartsentwined/zf2-cron)

ZF2 cron module

This module serves as a central cron runner. It allows you to register a cron job in your ZF2 app; these jobs will be aggregated and run together. This allows you to maintain only a single entry in your crontab, while maintaining as many cron jobs as needed in the ZF2 app.

# Installation

[Composer](http://getcomposer.org/):

```json
{
    "require": {
        "heartsentwined/zf2-cron": "2.*"
    }
}
```

Then add `Heartsentwined\Cron` to the `modules` key in `(app root)/config/application.config.*`

Cron module will also hook onto your application's database, through [`DoctrineORMModule`](https://github.com/doctrine/DoctrineORMModule). It will create a single table, `he_cron_job`, and will use the default EntityManager `doctrine.entitymanager.orm_default`. If your settings are different, please modify the `doctrine` section of `config/module.config.yml` and instances of `doctrine.entitymanager.orm_default` in `Heartsentwined\Cron\Controller\CronController` as needed.

Finally, you need to update your database schema. The recommended way is through Doctrine's CLI:

```sh
$ vendor/bin/doctrine-module orm:schema-tool:update --force
```

# Features

- All standard cron features (within PHP)
- Extended cron expression to support more complex use cases
- Centralized cron entry point / runner - you only need to maintain one entry in your crontab
- Cron job registration at runtime - you can, for example, register conditional cron jobs
- Cron job identifiers - allow for later modification of cron jobs
- Cron job lock - prevent a single job from being executed multiple times
- Unresponsive script recovery - recover long-running cron jobs and re-queue them automatically
- Logging - All jobs, whether `success`, `running`, `missed`, or `error`, will be logged; error messages and stack traces are also logged for `error` and `missed` cron jobs.

# Config

Copy `config/cron.local.php.dist` to `(app root)/config/autoload/cron.local.php`, and modify the settings.

- `scheduleAhead`: how long ahead to schedule cron jobs. Cron jobs must be scheduled into individual jobs before they can be run, in order to keep track of individual cron job locks, logs, and recovery from individual long-running scripts.
- `scheduleLifetime`: how long before a scheduled job is considered missed. If a job is scheduled, but not run - e.g. the crontab entry is deleted - for longer than `scheduleLifetime`, it will be invalidated and marked as `missed`.
- `maxRunningTime`: maximum running time of each cron job. If jobs are running for longer than `maxRunningTime`, it will be recovered and re-queued for re-run.
- `successLogLifetime`: how long to keep successfully completed cron job logs
- `failureLogLifetime`: how long to keep failed (`missed` / `error`) cron job logs

# Usage

## Add a cron job

Run `Foo::runCron('bar', 'baz')` every 15 minutes, with the identifier `foo`.

```php
use Heartsentwined\Cron\Service\Cron;
Cron::register(
    'foo',
    '*/15 * * * *',
    'Foo::runCron',
    array('bar', 'baz')
);
```

Function signature:
```php
public static function register($code, $frequency, $callback, array $args = array())
```

`$code` is a unique identifier for the job. It allows for later job overwriting, retrieval of Jobs by identifier, filtering, etc.

`$frequency` is any valid cron expression, in addition supporting:
- range: `0-5`
- range + interval: `10-59/5`
- comma-separated combinations of these: `1,4,7,10-20`
- English months: `january`
- English months (abbreviated to three letters): `jan`
- English weekdays: `monday`
- English weekdays (abbreviated to three letters): `mon`
- These text counterparts can be used in all places where their numerical counterparts are allowed, e.g. `jan-jun/2`
- A full example: `0-5,10-59/5 * 2-10,15-25 january-june/2 mon-fri` (every minute between minute 0-5 + every 5th minute between 10-59; every hour; every day between day 2-10 and day 15-25; every 2nd month between January-June; Monday-Friday)

`$callback` is any valid PHP callback.

`$args` (optional) are the arguments to the callback. The cron job will be called with [call_user_func_array](http://php.net/manual/en/function.call-user-func-array.php), so `$args[0]` will be the first argument, `$args[1]` the second, etc.

## Run the cron jobs

In order to actually run the cron jobs, you will need to setup a (real) cron job to trigger the Cron module. It will then run your registered cron jobs at their specified frequencies, retrying and logging as specified.

Recommended: set up a cron job and run the ZF2 app through CLI:

```sh
$ php public/index.php cron
```

Fallback: if the above doesn't work, you can always run it through a browser (or through lynx, wget, etc)

```
http://(zf2 app)/cron
```

Security: this will **not** expose any of your cron data or error outputs to any end-user. The controller will immediately close the connection, send an empty response, and continue running the cron jobs as background.

## Retrieving logs; advanced use cases

You can interact with individual cron jobs through the Doctrine 2 ORM API. The Cron module only defines a single Entity `Heartsentwined\Cron\Entity\Job`:

- `id`: unique identifier for individual cron jobs
- `code`: cron job "batch/family" identifier, as set in `Cron::register()`
- `status`: one of `pending`, `success`, `running`, `missed`, or `error`
- `errorMsg`: stores the error message for `error` cron jobs
- `stackTrace`: stores the stack trace for `error` cron jobs
- `createTime`: (datetime) time when this individual job is created
- `scheduleTime`: (datetime) time when this individual job is scheduled to be run
- `executeTime`: (datetime) time when this job is run (start of execution)
- `finishTime`: (datetime) time when this job has terminated. will be `null` for jobs other than `success`.

Example: retrieve all error messages of the cron job `foo`:

```php
// $em instance of EntityManager
$errorJobs = $em->getRepository('Heartsentwined\Cron\Entity\Job')->findBy(array(
    'code'   => 'foo',
    'status' => \Heartsentwined\Cron\Repository\Job::STATUS_ERROR,
));
foreach ($errorJobs as $job) {
    echo sprintf(
        "cron job, code %s, executed at %s with error \n %s \n\n",
        $job->getCode(), // will always be 'foo' in this example
        $job->getExecuteTime()->format('r'),
        $job->getErrorMsg()
    );
}
```
