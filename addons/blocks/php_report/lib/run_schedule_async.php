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

require_once('../../../config.php');
require_once($CFG->dirroot . '/blocks/php_report/runschedule.php');
require_once($CFG->dirroot . '/blocks/php_report/sharedlib.php');

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

//database record id of the schedule to run
$scheduleid = required_param('scheduleid', PARAM_INT);
//represents the schedule name / label
$schedulename = required_param('schedulename', PARAM_ALPHAEXT);
//how far we currently are in processing the jobs (1 to n)
$current = required_param('current', PARAM_INT);
//total number of jobs
$total = required_param('total', PARAM_INT);

//determine the ELIS scheduled task name from the PHP report schedule id
$taskname = block_php_report_get_taskname_from_id($scheduleid);

//used to update the UI with errors that have taken place
$error_string = '';

//obtain information about the scheduled PHP report task, if possible
if ($report_schedule = php_report_schedule_get_instance($taskname)) {
    //run the export
    $export_result = php_report_schedule_export_instance($report_schedule);

    if (!$export_result) {
        //export failure, so return a helpful error string
        $error_string = get_string('schedule_user_access_error', 'block_php_report', $schedulename);
    }
}

//spit out information regarding the current position
$a->current = $current;
$a->total = $total;

//send back status and error
$output = array(get_string('popup_status', 'block_php_report', $a),
                $error_string);

//send back the array as JSON data
echo json_encode($output);
