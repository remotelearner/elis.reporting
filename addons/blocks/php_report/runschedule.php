<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once $CFG->dirroot.'/blocks/php_report/php_report_base.php';
require_once $CFG->dirroot.'/blocks/php_report/sharedlib.php';

/**
 * Runs and exports, through email, the report instance specified by the
 * provided report schedule
 *
 * @param   stdClass  $report_schedule  The PHP report schedule containing the information
 *                                      about the specific report to be exported
 *
 * @return  boolean                     true on success, otherwise false
 */
function php_report_schedule_export_instance($report_schedule, $now = 0) {
    global $CFG;

    if ($now == 0) {
        $now = gmmktime(); // time();
    }

    $data = unserialize($report_schedule->config);

    // Create report to be emailed to recipient
    $shortname = $report_schedule->report;
    $format = $data['format'];
    // Initialize a temp path name
    $tmppath = '/temp';
    // Create a unique temporary filename to use for this schedule
    $filename = tempnam($CFG->dataroot.$tmppath, 'php_report_');

    $parameterdata = $data['parameters'];

    // Generate the report file
    $result = php_report::export_default_instance($shortname, $format, $filename, $parameterdata, $report_schedule->userid, php_report::EXECUTION_MODE_SCHEDULED);

    if (!$result) {
        //handle failure case
        unlink($filename);
        return false;
    }

    // Attach filename to an email - so get recipient...
    $recipients_array = explode(',',$data['recipients']);
    $from = get_string('noreplyname');
    $subject = get_string('email_subject','block_php_report').$data['label'];
    $messagetext = html_to_text($data['message']);
    $messagehtml = $data['message'];
    $start = strlen($CFG->dataroot);
    $attachment = substr($filename,$start);
    $attachname = $report_schedule->report.$now.'.'.$data['format'];

    // Attach the file to the recipients
    foreach ($recipients_array as $recipient) {
        $user = new stdClass;
        $user->email = trim($recipient);
        $user->mailformat = 1;
        email_to_user($user, $from, $subject, $messagetext, $messagehtml, $attachment, $attachname);
    }

    // Remove the file that was created for this report
    unlink($filename);

    return true;
}

/**
 * Decrements the number of runs remaining for a PHP report schedule, but only if this action
 * is applicable to that task, based on its configuration
 *
 * @param  stdClass  $report_schedule  The PHP report schedule record that specifies the number of
 *                                     remaining runs, if applicable
 *
 * @uses   $DB
 */
function php_report_schedule_decrement_remaining_runs($report_schedule) {
    global $DB;

    $data = unserialize($report_schedule->config);

    $upreport_schedule = new stdClass;
    $upreport_schedule->id = $report_schedule->id;

    // Update runs remaining in the php report record
    if (isset($data['schedule']['runsremaining']) && $data['schedule']['runsremaining'] > 0 ) {
        $data['schedule']['runsremaining'] = $data['schedule']['runsremaining'] - 1;
    } else {
        $data['schedule']['runsremaining'] = null;
    }

    // Serialize the config before saving
    $upreport_schedule->config = serialize($data);
    $DB->update_record('php_report_schedule', $upreport_schedule);
}

/**
 * Sets the next runtime of the specified ELIS schedueld task in the database
 * based on the specified report schedule's timing information
 *
 * @param  string    $taskname         The task name, in the form scheduled_{id}, where id
 *                                     is the PHP report schedule id
 * @param  stdClass  $report_schedule  The PHP report schedule record that specifies the necessary
 *                                     timing information
 *
 * @uses   $DB
 */
function php_report_schedule_set_next_runtime($taskname, $report_schedule) {
    global $DB;

    $data = unserialize($report_schedule->config);

    // If this is a simple report, we need to update the schedule record
    if ($data['recurrencetype'] == 'simple') {
        // Update nextruntime in 'elis_scheduled_tasks' to properly reflect
        // the frequency and frequency type
        $frequency = $data['schedule']['frequency'];
        $freq_type = $data['schedule']['frequencytype'];
        // Get the elis scheduled task last runtime for this taskname
        $schedule = $DB->get_record('elis_scheduled_tasks', array('taskname' => $taskname));

        if (!$schedule) {
            //last run, and elis scheduled task has already been deleted
            return;
        }

        // Create updated elis scheduled task with the next runtime
        $upschedule = new stdClass;
        $upschedule->id = $schedule->id;

        // Calculate the next runtime using the frequency and frequency type
        $sched_change = '+'.$frequency.' '.$freq_type;
        $upschedule->nextruntime = strtotime($sched_change, $schedule->lastruntime);
        // above 2nd parameter to strtotime(): $schedule->lastruntime
        // causes time to drift to when cron was last run, but,
        // $schedule->nextruntime already advanced to next day!
        //echo "php_report_schedule_set_next_runtime($taskname, report_schedule): sched_change = {$sched_change}, schedule->nextruntime = {$schedule->nextruntime}, upschedule->nextruntime = {$upschedule->nextruntime}";

        // Get hour/minute/day/month from this next runtime
        $upschedule->minute    = (int) strftime('%M',$upschedule->nextruntime);
        $upschedule->hour      = (int) strftime('%H',$upschedule->nextruntime);
        $upschedule->day       = (int) strftime('%d',$upschedule->nextruntime);
        $upschedule->month     = (int) strftime('%m',$upschedule->nextruntime);
        // Assuming 1 (Monday) => 7 (Sunday)
        $upschedule->dayofweek = strftime('%u',$upschedule->nextruntime);

        $DB->update_record('elis_scheduled_tasks', $upschedule);
    }
}

/**
 * Removes orphaned PHP report schedule records (i.e. PHP report schedule records
 * that have no associated ELIS scheduled task)
 *
 * @uses  $DB;
 */
function php_report_schedule_delete_unmatching_records() {
    global $DB;

    //need this concatenation to connect ELIS and PHP scheduling info
    $concat = block_php_report_get_taskname_from_column('php_sched.id');

    //query to find the appropriate PHP report task ids
    $sql = "SELECT php_sched.id as id
            FROM {php_report_schedule} php_sched
            LEFT JOIN {elis_scheduled_tasks} elis_sched
                   ON (elis_sched.taskname = {$concat})
                WHERE elis_sched.taskname IS NULL";

    //iterate and delete
    $rs = $DB->get_recordset_sql($sql);
    foreach ($rs as $res) {
        $DB->delete_records('php_report_schedule', array('id' => $res->id));
    }
}

/**
 * Performs the entire scheduled run of the specified task, including emailing
 * the report export, decrementing remaining runs, updating next scheduled run time,
 * and cleanup
 *
 * @param   string   $taskname  The task name, in the form scheduled_{id}, where id
 *                              is the PHP report schedule id
 *
 * @return  boolean             true on success, otherwise false
 */
function run_schedule($taskname) {
    global $CFG;

    $now = gmmktime(); //time();

    $report_schedule = php_report_schedule_get_instance($taskname);

    if ($report_schedule === false) {
        //error getting the report schedule, so return false
        return false;
    }

    // New report schedule for updating purposes
    $data = unserialize($report_schedule->config);

    // Check that we are beyond the startdate before continuing
    if ($data['startdate'] > $now) {
        return true;
    }

    //perform the export
    php_report_schedule_export_instance($report_schedule, $now);

    //decrement the number of remaining runs, if applicable
    php_report_schedule_decrement_remaining_runs($report_schedule);

    //set the next run time in the database if appropriate
    php_report_schedule_set_next_runtime($taskname, $report_schedule);

    //cleanup
    php_report_schedule_delete_unmatching_records();

    return true;
}

/**
 * Get the php report schedule using the schedule id from the taskname field
 *
 * @param  string            $taskname             taskname which has the schedule id at the end
 * @return stdClass|boolean  $php_report_schedule  php report schedule record for the given schedule, or
 *                                                 false on error
 *
 * @uses   $DB
 */
function php_report_schedule_get_instance($taskname) {
    global $DB;

    // Get the associated php_report schedules
    $taskname_array = explode('_',$taskname);
    $schedule_id = $taskname_array[1];
    $php_report_schedule = $DB->get_record('php_report_schedule', array('id' => $schedule_id));
    return $php_report_schedule;
}
