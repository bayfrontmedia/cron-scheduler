<?php

/**
 * @package cron-scheduler
 * @link https://github.com/bayfrontmedia/cron-scheduler
 * @author John Robinson <john@bayfrontmedia.com>
 * @copyright 2020 Bayfront Media
 */

namespace Bayfront\CronScheduler;

use Bayfront\StringHelpers\Str;
use Cron\CronExpression;
use DateTimeInterface;
use Exception;

class Cron
{

    protected $lock_file_path; // Path to directory in which to save lock files.

    protected $output_file; // File to save output.

    /**
     * Cron constructor.
     *
     * @param string|null $lock_file_path (Storage path for lock files)
     * @param string|null $output_file (File to save output from jobs)
     *
     * @throws FilesystemException
     */

    public function __construct(string $lock_file_path = NULL, string $output_file = NULL)
    {

        if (NULL !== $lock_file_path) {

            $lock_file_path = rtrim($lock_file_path, '/');

            if (!is_writable($lock_file_path)) {
                throw new FilesystemException('Lock file path is not writable: ' . $lock_file_path);
            }

        }

        $this->lock_file_path = $lock_file_path;

        $this->output_file = $output_file;

    }

    protected $jobs = []; // Scheduled cron jobs

    /**
     * Return scheduled cron jobs.
     *
     * @return array
     */

    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Get previous date a given job was scheduled to run.
     *
     * @param string $label
     * @param string $date_format (Date format to return)
     *
     * See: https://www.php.net/manual/en/datetime.format.php
     *
     * @return string
     *
     * @throws LabelExistsException
     * @throws SyntaxException
     */

    public function getPreviousDate(string $label, string $date_format = 'Y-m-d H:i:s')
    {

        if (!isset($this->getJobs()[$label]['at'])) {
            throw new LabelExistsException('Unable to get previous date (' . $label . '): Label not found');
        }

        try {
            return CronExpression::factory($this->getJobs()[$label]['at'])->getPreviousRunDate()->format($date_format);

        } catch (Exception $e) {
            throw new SyntaxException('CronExpression error: ' . $e->getMessage(), 0, $e);
        }

    }

    /**
     * Get next date a given job is scheduled to run.
     *
     * See: https://www.php.net/manual/en/datetime.format.php
     *
     * @param string $label
     * @param string $date_format (Date format to return)
     *
     * @return string
     *
     * @throws LabelExistsException
     * @throws SyntaxException
     */

    public function getNextDate(string $label, string $date_format = 'Y-m-d H:i:s')
    {

        if (!isset($this->getJobs()[$label]['at'])) {
            throw new LabelExistsException('Unable to get next date (' . $label . '): Label not found');
        }

        try {
            return CronExpression::factory($this->getJobs()[$label]['at'])->getNextRunDate()->format($date_format);

        } catch (Exception $e) {
            throw new SyntaxException('CronExpression error: ' . $e->getMessage(), 0, $e);
        }

    }

    /**
     * Return full path of lock file filename.
     *
     * @param string $file
     *
     * @return string
     */

    private function _getFullFilename(string $file): string
    {
        return $this->lock_file_path . '/cron-' . Str::kebabCase($file) . '.lock';
    }

    /**
     * Creates lock file.
     *
     * @param string $file
     *
     * @return void
     *
     * @throws FilesystemException
     */

    private function _createLockFile(string $file): void
    {

        $dir = rtrim(str_replace(basename($this->_getFullFilename($file)), '', $this->_getFullFilename($file)), '/');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $create = file_put_contents($this->_getFullFilename($file), '');

        if (false === $create) {

            throw new FilesystemException('Unable to create lock file');

        }

        chmod($this->_getFullFilename($file), 0664);

    }

    /**
     * Removes lock file.
     *
     * @param string $file
     *
     * @throws FilesystemException
     */

    private function _removeLockFile(string $file): void
    {

        $remove = unlink($this->_getFullFilename($file));

        if (false === $remove) {

            throw new FilesystemException('Unable to remove lock file');

        }

    }

    /**
     * Checks if lock file exists.
     *
     * @param string $file
     *
     * @return bool
     */

    private function _lockFileExists(string $file): bool
    {
        return file_exists($this->_getFullFilename($file));
    }

    /**
     * Checks if job is due.
     *
     * @param string|DateTimeInterface
     * @param string $at
     *
     * @return bool
     */

    private function _jobIsDue($current_time, string $at): bool
    {
        return CronExpression::factory($at)->isDue($current_time);
    }

    /**
     * Returns the last added key to the jobs array.
     *
     * @return string|null
     */

    private function _thisJobKey(): ?string
    {

        // PHP >= 7.3 can use array_key_last

        if (empty($this->jobs)) {
            return NULL;
        }

        end($this->jobs);

        $key = key($this->jobs);

        reset($this->jobs);

        return $key;

    }

    /**
     * Formats a hh:mm time format to array
     *
     * @param string $time
     *
     * @return array
     *
     * @throws SyntaxException
     */

    private function _timeToArray(string $time): array
    {

        $time = explode(':', $time, 2);

        if (isset($time[1])) {

            $return = [
                'hour' => ltrim($time[0], '0'),
                'minute' => ltrim($time[1], '0')
            ];

            foreach ($return as $k => $v) {

                /*
                 * ltrim() would have removed both zeros for 00,
                 * but one must be kept.
                 */

                if ($v == '') {

                    $return[$k] = 0;

                }

            }

            return $return;

        }

        throw new SyntaxException('Invalid time format');

    }

    /**
     * Validates correct format of cron range.
     *
     * @param string|int $value
     * @param int $min
     * @param int $max
     *
     * @return string|int
     *
     * @throws SyntaxException
     */

    private function _validateCronRange($value, int $min, int $max)
    {
        if ($value === '*') {
            return '*';
        }

        if (!is_numeric($value) || !($value >= $min && $value <= $max)) {

            throw new SyntaxException('Invalid cron value (' . $value . ')- value must be either "*" or between ' . $min . ' and ' . $max);

        }

        return $value;

    }

    /**
     * Validates correct format of cron values
     *
     * @param string|int $minute ("*" or numeric value)
     * @param string|int $hour ("*" or numeric value)
     * @param string|int $day ("*" or numeric value)
     * @param string|int $month ("*" or numeric value)
     * @param string|int $weekday ("*" or numeric value)
     *
     * @return array
     *
     * @throws SyntaxException
     */

    private function _validateCronSequence($minute = '*', $hour = '*', $day = '*', $month = '*', $weekday = '*'): array
    {

        return [
            'minute' => $this->_validateCronRange($minute, 0, 59),
            'hour' => $this->_validateCronRange($hour, 0, 23),
            'day' => $this->_validateCronRange($day, 1, 31),
            'month' => $this->_validateCronRange($month, 1, 12),
            'weekday' => $this->_validateCronRange($weekday, 0, 6),
        ];

    }

    /**
     * Saves job output to file.
     *
     * @param mixed $output
     * @param string $file
     *
     * @return void
     *
     * @throws FilesystemException
     */

    private function _saveOutputToFile($output, string $file): void
    {

        $dir = rtrim(str_replace(basename($file), '', $file), '/');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $save = file_put_contents($file, $output, FILE_APPEND);

        if (false === $save) {

            throw new FilesystemException('Unable to save output to file (' . $file . ')');

        }

        chmod($file, 0664);

    }

    /**
     * Runs all queued jobs that are due.
     *
     * @param string|DateTimeInterface $current_time (Override current time by passing a DateTime instance with a
     *     defined time)
     *
     * @return array (Array of data relating to the completed jobs)
     *
     * @throws FilesystemException
     */

    public function run($current_time = 'now'): array
    {

        $return = [
            'jobs' => [],
            'start' => time()
        ];

        /*
         * Due jobs will be saved to this array before they are ran.
         * Then, each job on this array will be ran.
         *
         * This prevents issues from occurring where a job may take some time to complete,
         * and by the time it completes, the next job is no longer "due".
         */

        $jobs_to_run = [];

        foreach ($this->jobs as $label => $job) {

            if ((NULL === $this->lock_file_path
                    || !$this->_lockFileExists($label)
                    || isset($job['always']))
                && $this->_jobIsDue($current_time, $job['at'])) { // If the job can be run

                if (isset($job['when']) && isset($job['when_params'])) { // If a conditional callable exists

                    if (true !== call_user_func_array($job['when'], $job['when_params'])) {

                        continue; // Iterate to next job if not true

                    }

                }

                if (NULL !== $this->lock_file_path && !isset($job['always'])) {
                    $this->_createLockFile($label);
                }

                $jobs_to_run[$label] = $job;

            }

        }

        foreach ($jobs_to_run as $label => $job) { // For each due job

            $job_start = time();

            $output = NULL;

            if (isset($job['call']) && isset($job['params'])) { // If a callable

                $output = call_user_func_array($job['call'], $job['params']);

            } else if (isset($job['php'])) {

                $output = shell_exec(escapeshellcmd('php ' . $job['php']));

            } else if (isset($job['raw'])) {

                $output = shell_exec(escapeshellcmd($job['raw']));

            }

            if (NULL !== $this->lock_file_path && !isset($job['always'])) {
                $this->_removeLockFile($label);
            }

            // Save output to file

            if (isset($job['file'])) {

                $file = $job['file'];

            } else if (NULL !== $this->output_file) {

                $file = $this->output_file;

            }

            if (isset($file) && is_string($output) && $output != '') { // If any output to save

                $this->_saveOutputToFile($output, $file);
            }

            $job_end = time();

            $return['jobs'][$label] = [
                'start' => $job_start,
                'end' => $job_end,
                'elapsed' => $job_end - $job_start,
                'output' => $output
            ];

        }

        $run_end = time();

        $return['end'] = $run_end;
        $return['elapsed'] = $run_end - $return['start'];
        $return['count'] = count($jobs_to_run);

        return $return;

    }

    /**
     * Adds a raw command as a job.
     *
     * By default, the job will run every minute.
     *
     * @param string $label (Unique label to assign to this job)
     * @param string $command
     *
     * @return self
     *
     * @throws LabelExistsException
     */

    public function raw(string $label, string $command): self
    {

        $label = Str::kebabCase($label, true);

        if (isset($this->jobs[$label])) {
            throw new LabelExistsException('Unable to add job (' . $label . '): label already exists');
        }

        $this->jobs[$label] = [
            'raw' => $command,
            'at' => '* * * * *'
        ];

        return $this;

    }

    /**
     * Adds a php file as a job.
     *
     * By default, the job will run every minute.
     *
     * @param string $label (Unique label to assign to this job)
     * @param string $file (Full path to file)
     *
     * @return self
     *
     * @throws LabelExistsException
     */

    public function php(string $label, string $file): self
    {

        $label = Str::kebabCase($label, true);

        if (isset($this->jobs[$label])) {
            throw new LabelExistsException('Unable to add job (' . $label . '): label already exists');
        }

        $this->jobs[$label] = [
            'php' => $file,
            'at' => '* * * * *'
        ];

        return $this;

    }

    /**
     * Adds a callable function as a job.
     *
     * NOTE: Functions should return, not echo output.
     *
     * By default, the job will run every minute.
     *
     * @param string $label (Unique label to assign to this job)
     * @param callable $callable
     * @param array $params (Parameters to pass to the callable)
     *
     * @return self
     *
     * @throws LabelExistsException
     */

    public function call(string $label, callable $callable, array $params = []): self
    {

        $label = Str::kebabCase($label, true);

        if (isset($this->jobs[$label])) {
            throw new LabelExistsException('Unable to add job (' . $label . '): label already exists');
        }

        $this->jobs[$label] = [
            'call' => $callable,
            'params' => $params,
            'at' => '* * * * *'
        ];

        return $this;

    }

    /**
     * Always run job, even if previous execution is still in progress.
     *
     * This prevents a lock file from being created for this job.
     *
     * @return self
     */

    public function always(): self
    {

        $this->jobs[$this->_thisJobKey()]['always'] = true;

        return $this;

    }

    /**
     * Save the job output to a given file.
     *
     * This will override $output_file, if specified in the constructor.
     *
     * @param string $output_file
     *
     * @return self
     */

    public function output(string $output_file): self
    {

        $this->jobs[$this->_thisJobKey()]['file'] = $output_file;

        return $this;

    }

    /**
     * Add a condition for the job to run, even if it is due.
     *
     * The job will only run if the return value of $callable is TRUE.
     *
     * @param callable $callable
     * @param array $params
     *
     * @return self
     */

    public function when(callable $callable, array $params = []): self
    {

        $this->jobs[$this->_thisJobKey()]['when'] = $callable;

        $this->jobs[$this->_thisJobKey()]['when_params'] = $params;

        return $this;

    }

    /**
     * Schedule job to run using a valid cron expression.
     *
     * @param string $at
     *
     * @return self
     */

    public function at(string $at): self
    {

        $this->jobs[$this->_thisJobKey()]['at'] = $at;

        return $this;

    }

    /**
     * Schedule job to run every x number of minutes.
     *
     * @param int $minutes
     *
     * @return self
     *
     * @throws SyntaxException
     */


    public function everyMinutes(int $minutes = 1): self
    {

        $c = $this->_validateCronSequence($minutes);

        if ($c['minute'] < 1) {
            $c['minute'] = 1;
        }

        $this->jobs[$this->_thisJobKey()]['at'] = '*/' . $c['minute'] . ' * * * *';

        return $this;

    }

    /**
     * Schedule job to run on a given minute of every hour.
     *
     * @param int $minute
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function hourly(int $minute = 0): self
    {

        $c = $this->_validateCronSequence($minute);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' * * * *';

        return $this;

    }

    /**
     * Schedule job to run on the hour every x number of hours.
     *
     * @param int $hours
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function everyHours(int $hours = 1): self
    {

        $c = $this->_validateCronSequence('*', $hours);

        if ($c['hour'] < 1) {
            $c['hour'] = 1;
        }

        $this->jobs[$this->_thisJobKey()]['at'] = '0 */' . $c['hour'] . ' * * *';

        return $this;

    }

    /**
     * Schedule job to run at a given time of every day.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function daily(string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour']);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' * * *';

        return $this;

    }

    /**
     * Schedule job to run on a given weekday and time of every week.
     *
     * @param int $weekday (0-6 as Sunday-Saturday)
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function weekly(int $weekday = 0, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], '*', '*', $weekday);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' * * ' . $c['weekday'];

        return $this;

    }

    /**
     * Schedule job to run on a given day and time of every month.
     *
     * @param int $day (1-31 as day of the month)
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function monthly(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' * *';

        return $this;

    }

    /**
     * Schedule job to run at a given time on the first day every x number of months.
     *
     * @param int $months
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function everyMonths(int $months = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], '*', $months);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' 1 */' . $c['month'] . ' *';

        return $this;

    }

    /**
     * Schedule job to run on a given month, day and time each year.
     *
     * @param int $month
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function annually(int $month = 1, int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, $month);

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' ' . $c['month'] . ' *';

        return $this;

    }

    /**
     * Schedule job to run at a given time every Sunday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function sunday(string $time = '00:00'): self
    {
        return $this->weekly(0, $time);
    }

    /**
     * Schedule job to run at a given time every Monday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function monday(string $time = '00:00'): self
    {
        return $this->weekly(1, $time);
    }

    /**
     * Schedule job to run at a given time every Tuesday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function tuesday(string $time = '00:00'): self
    {
        return $this->weekly(2, $time);
    }

    /**
     * Schedule job to run at a given time every Wednesday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function wednesday(string $time = '00:00'): self
    {
        return $this->weekly(3, $time);
    }

    /**
     * Schedule job to run at a given time every Thursday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function thursday(string $time = '00:00'): self
    {
        return $this->weekly(4, $time);
    }

    /**
     * Schedule job to run at a given time every Friday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function friday(string $time = '00:00'): self
    {
        return $this->weekly(5, $time);
    }

    /**
     * Schedule job to run at a given time every Saturday.
     *
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function saturday(string $time = '00:00'): self
    {
        return $this->weekly(6, $time);
    }

    /**
     * Schedule job to run on a given day and time each January.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function january(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 1 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each February.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function february(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 2 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each March.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function march(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 3 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each April.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function april(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 4 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each May.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function may(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 5 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each June.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function june(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 6 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each July.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function july(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 7 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each August.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function august(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 8 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each September.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function september(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 9 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each October.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function october(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 10 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each November.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function november(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 11 *';

        return $this;

    }

    /**
     * Schedule job to run on a given day and time each December.
     *
     * @param int $day
     * @param string $time (Time in 24-hour format without leading zeros)
     *
     * @return self
     *
     * @throws SyntaxException
     */

    public function december(int $day = 1, string $time = '00:00'): self
    {

        $time = $this->_timeToArray($time);

        $c = $this->_validateCronSequence($time['minute'], $time['hour'], $day, '*');

        $this->jobs[$this->_thisJobKey()]['at'] = $c['minute'] . ' ' . $c['hour'] . ' ' . $c['day'] . ' 12 *';

        return $this;

    }

}