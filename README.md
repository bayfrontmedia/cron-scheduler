## Cron Scheduler

A flexible framework agnostic cron job scheduler using human-readable expressions.

- [License](#license)
- [Author](#author)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)

## License

This project is open source and available under the [MIT License](LICENSE).

## Author

John Robinson, [Bayfront Media](https://www.bayfrontmedia.com)

## Requirements

* PHP >= 7.1.0

## Installation

```
composer require bayfrontmedia/cron-scheduler
```

## Usage

### Start using Cron Scheduler

First, create a file to be used to schedule jobs, for example `cron.php`. The file can be named whatever you like.

Then, add a new entry to your crontab to run the file every minute:

```
* * * * * path/to/php/bin path/to/cron.php 1>> /dev/null 2>&1
```

Now, your server will check the file every minute, and Cron Scheduler will only run the jobs that are due, according to their schedule.

### Creating an instance

**NOTE:** All exceptions thrown by Cron Scheduler extend `Bayfront\CronScheduler\CronException`, so you can choose to catch exceptions as narrowly or broadly as you like.

The constructor accepts two parameters as strings: `$lock_file_path` and `$output_file`.

To prevent overlapping jobs, Cron Scheduler creates temporary "lock" files. 
These files are created for each job once it begins, and deleted once it completes.
Jobs will be skipped when a lock file exists, even if it is due to run.
If `$lock_file_path === NULL`, lock files will never be created, and all jobs will be allowed to overlap.

When an `$output_file` is specified, all output of jobs that run will be saved to this file, unless a custom file is specified specifically for that job (see [output](#output)). 

The constructor may throw a `Bayfront\CronScheduler\FilesystemException` exception.

**Example `cron.php`:**

```
use Bayfront\CronScheduler\Cron;
use Bayfront\CronScheduler\FilesystemException;

try {

    $cron = new Cron('path/to/temp/dir', 'path/to/output/file.txt');

} catch (FilesystemException $e) {
    die($e->getMessage());
}
```

### Public methods

- [getJobs](#getjobs)
- [getPreviousDate](#getpreviousdate)
- [getNextDate](#getnextdate)
- [run](#run)
- [raw](#raw)
- [php](#php)
- [call](#call)
- [always](#always)
- [output](#output)
- [when](#when)

**Job schedule**

- [at](#at)
- [everyMinutes](#everyminutes)
- [hourly](#hourly)
- [everyHours](#everyhours)
- [daily](#daily)
- [weekly](#weekly)
- [monthly](#monthly)
- [everyMonths](#everymonths)
- [annually](#annually)
- [sunday](#sunday)
- [monday](#monday)
- [tuesday](#tuesday)
- [wednesday](#wednesday)
- [thursday](#thursday)
- [friday](#friday)
- [saturday](#saturday)
- [january](#january)
- [february](#february)
- [march](#march)
- [april](#april)
- [may](#may)
- [june](#june)
- [july](#july)
- [august](#august)
- [september](#september)
- [october](#october)
- [november](#november)
- [december](#december)


<hr />

### getJobs

**Description:**

Return scheduled cron jobs.

**Parameters:**

- (None)

**Returns:**

- (array)

**Example:**

```
print_r($cron->getJobs());
```

<hr />

### getPreviousDate

**Description:**

Get previous date a given job was scheduled to run.

**Parameters:**

- `$label` (string)
- `$date_format = 'Y-m-d H:i:s'` (string): Date format to return

See: [https://www.php.net/manual/en/datetime.format.php](https://www.php.net/manual/en/datetime.format.php)

**Returns:**

- (string)

**Throws:**

- `Bayfront\CronScheduler\LabelExistsException`
- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {
    echo $cron->getPreviousDate('job-name');
    
} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### getNextDate

**Description:**

Get next date a given job is scheduled to run.

**Parameters:**

- `$label` (string)
- `$date_format = 'Y-m-d H:i:s'` (string): Date format to return

See: [https://www.php.net/manual/en/datetime.format.php](https://www.php.net/manual/en/datetime.format.php)

**Returns:**

- (string)

**Throws:**

- `Bayfront\CronScheduler\LabelExistsException`
- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {
    echo $cron->getNextDate('job-name');
    
} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### run

**Description:**

Runs all queued jobs that are due.

**Parameters:**

- `$current_time = 'now'` (`DateTimeInterface`): Override current time by passing a `DateTime` instance with a given time.

**Returns:**

- (array): Array of data relating to the completed jobs

**Throws:**

- `Bayfront\CronScheduler\FilesystemException`

The array that is returned will contain the following keys:

- `jobs`: Array of jobs that ran, each with their own `start`, `end`, `elapsed` and `output` keys.
- `start`: Timestamp of when the jobs started.
- `end`: Timestamp of when the jobs ended.
- `elapsed`: Total seconds elapsed to complete jobs.
- `count`: Number of jobs that ran.

**Throws:**

- `Bayfront\CronScheduler\FilesystemException`

**Example:**

```
try {

    $result = $cron->run();

} catch (FilesystemException $e) {
    die($e->getMessage());
}
```

Another example, this time overriding (or, "spoofing") the current time:

```
$current_time = new DateTime('2020-09-08 10:55:00');

try {

    $result = $cron->run($current_time)

} catch (FilesystemException $e) {
    die($e->getMessage());
}
```

<hr />

### raw

**Description:**

Adds a raw command as a job.

By default, the job will run every minute.

**Parameters:**

- `$label` (string): Unique label to assign to this job
- `$command` (string)

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\LabelExistsException`

**Example:**

```
try {

    $cron->raw('job-name', 'php -v');

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### php

**Description:**

Adds a php file as a job.

By default, the job will run every minute.

**Parameters:**

- `$label` (string): Unique label to assign to this job
- `$file` (string): Full path to file

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\LabelExistsException`

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php');

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### call

**Description:**

Adds a callable function as a job.

**NOTE:** Functions should `return`, not `echo` output.

By default, the job will run every minute.

**Parameters:**

- `$label` (string): Unique label to assign to this job
- `$callable` (callable)
- `$params = []` (array): Parameters to pass to the callable

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\LabelExistsException`

**Example:**

```
try {

    $cron->call('job-name', function ($name) {
        return "Hello, " . $name . "\n\n";
    }, [
        'name' => 'John'
    ]);

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### always

**Description:**

Always run job, even if previous execution is still in progress.

This prevents a lock file from being created for this job.

**Parameters:**

- (None)

**Returns:**

- (self)

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->always();

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### output

**Description:**

Save the job output to a given file.

This will override `$output_file`, if specified in the constructor.

**Parameters:**

- `$output_file` (string)

**Returns:**

- (self)

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->output('path/to/save/output.txt');

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### when

**Description:**

Add a condition for the job to run, even if it is due.

The job will only run if the return value of `$callable` is `TRUE`.

**Parameters:**

- `$callable` (callable)
- `$params = []` (array)

**Returns:**

- (self)

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->when(function ($return) {
        
        return $return;
       
    }, [
        'return' => true
    ]);

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### at

**Description:**

Schedule job to run using a valid cron expression.

**Parameters:**

- `$at` (string)

**Returns:**

- (self)

**Example:**

```
try {

    // 10:00pm Monday-Friday

    $cron->php('job-name', 'path/to/php/file.php')->at('0 22 * * 1-5');

} catch (LabelExistsException $e) {
    die($e->getMessage());
}
```

<hr />

### everyMinutes

**Description:**

Schedule job to run every x number of minutes.

**Parameters:**

- `$minutes = 1` (int)

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->everyMinutes(5);

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### hourly

**Description:**

Schedule job to run on a given minute of every hour.

**Parameters:**

- `$minute = 0` (int)

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->hourly(15);

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### everyHours

**Description:**

Schedule job to run on the hour every x number of hours.

**Parameters:**

- `$hours = 1` (int)

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->everyHours(2);

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### daily

**Description:**

Schedule job to run at a given time of every day.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    $cron->php('job-name', 'path/to/php/file.php')->daily('16:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### weekly

**Description:**

Schedule job to run on a given weekday and time of every week.

**Parameters:**

- `$weekday = 0` (int): 0-6 as Sunday-Saturday
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 4:30pm every Friday

    $cron->php('job-name', 'path/to/php/file.php')->weekly(5, '16:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### monthly

**Description:**

Schedule job to run on a given day and time of every month.

**Parameters:**

- `$day = 1` (int): 1-31 as day of the month
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight on the 15th day of every month

    $cron->php('job-name', 'path/to/php/file.php')->monthly(15);

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### everyMonths

**Description:**

Schedule job to run at a given time on the first day every x number of months.

**Parameters:**

- `$months = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 9am on the first day every of every third month

    $cron->php('job-name', 'path/to/php/file.php')->everyMonths(3, '9:00');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### annually

**Description:**

Schedule job to run on a given month, day and time each year.

**Parameters:**

- `$month = 1` (int)
- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every June 20th

    $cron->php('job-name', 'path/to/php/file.php')->annually(6, 20);

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### sunday

**Description:**

Schedule job to run at a given time every Sunday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Sunday

    $cron->php('job-name', 'path/to/php/file.php')->sunday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### monday

**Description:**

Schedule job to run at a given time every Monday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Monday

    $cron->php('job-name', 'path/to/php/file.php')->monday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### tuesday

**Description:**

Schedule job to run at a given time every Tuesday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Tuesday

    $cron->php('job-name', 'path/to/php/file.php')->tuesday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### wednesday

**Description:**

Schedule job to run at a given time every Wednesday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Wednesday

    $cron->php('job-name', 'path/to/php/file.php')->wednesday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### thursday

**Description:**

Schedule job to run at a given time every Thursday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Thursday

    $cron->php('job-name', 'path/to/php/file.php')->thursday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### friday

**Description:**

Schedule job to run at a given time every Friday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Friday

    $cron->php('job-name', 'path/to/php/file.php')->friday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### saturday

**Description:**

Schedule job to run at a given time every Saturday.

**Parameters:**

- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // Midnight every Saturday

    $cron->php('job-name', 'path/to/php/file.php')->saturday();

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### january

**Description:**

Schedule job to run on a given day and time each January.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every January 10th

    $cron->php('job-name', 'path/to/php/file.php')->january(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### february

**Description:**

Schedule job to run on a given day and time each February.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every February 10th

    $cron->php('job-name', 'path/to/php/file.php')->february(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### march

**Description:**

Schedule job to run on a given day and time each March.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every March 10th

    $cron->php('job-name', 'path/to/php/file.php')->march(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### april

**Description:**

Schedule job to run on a given day and time each April.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every April 10th

    $cron->php('job-name', 'path/to/php/file.php')->april(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### may

**Description:**

Schedule job to run on a given day and time each May.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every May 10th

    $cron->php('job-name', 'path/to/php/file.php')->may(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### june

**Description:**

Schedule job to run on a given day and time each June.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every June 10th

    $cron->php('job-name', 'path/to/php/file.php')->june(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### july

**Description:**

Schedule job to run on a given day and time each July.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every July 10th

    $cron->php('job-name', 'path/to/php/file.php')->july(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### august

**Description:**

Schedule job to run on a given day and time each August.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every August 10th

    $cron->php('job-name', 'path/to/php/file.php')->august(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### september

**Description:**

Schedule job to run on a given day and time each September.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every September 10th

    $cron->php('job-name', 'path/to/php/file.php')->sepember(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### october

**Description:**

Schedule job to run on a given day and time each October.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every October 10th

    $cron->php('job-name', 'path/to/php/file.php')->october(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### november

**Description:**

Schedule job to run on a given day and time each November.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every November 10th

    $cron->php('job-name', 'path/to/php/file.php')->november(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```

<hr />

### december

**Description:**

Schedule job to run on a given day and time each December.

**Parameters:**

- `$day = 1` (int)
- `$time = '00:00'` (string): Time in 24-hour format without leading zeros

**Returns:**

- (self)

**Throws:**

- `Bayfront\CronScheduler\SyntaxException`

**Example:**

```
try {

    // 11:30pm every December 10th

    $cron->php('job-name', 'path/to/php/file.php')->december(10, '23:30');

} catch (CronException $e) {
    die($e->getMessage());
}
```