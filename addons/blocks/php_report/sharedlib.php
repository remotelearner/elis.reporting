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
 * @subpackage php_reports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Specifies the mapping of tree category shortnames to display names
 *
 * @return  array  Mapping of tree category shortnames to display names,
 *                 in the order they should appear
 */
function block_php_report_get_category_mapping() {
    //categories, in a pre-determined order
    return array(php_report::CATEGORY_CURRICULUM    => get_string('curriculum_reports',    'block_php_report'),
                 php_report::CATEGORY_COURSE        => get_string('course_reports',        'block_php_report'),
                 php_report::CATEGORY_CLASS         => get_string('class_reports',         'block_php_report'),
                 php_report::CATEGORY_CLUSTER       => get_string('cluster_reports',       'block_php_report'),
                 php_report::CATEGORY_PARTICIPATION => get_string('participation_reports', 'block_php_report'),
                 php_report::CATEGORY_USER          => get_string('user_reports',          'block_php_report'),
                 php_report::CATEGORY_ADMIN         => get_string('admin_reports',         'block_php_report'),
                 php_report::CATEGORY_OUTCOMES      => get_string('outcomes_reports',      'block_php_report'));
}

/**
 * Converts a PHP report schedule id to an ELIS task name
 *
 * @param   int     $scheduleid  The PHP report schedule id
 *
 * @return  string               A taskname that would identify any associated
 *                               ELIS scheduled tasks
 */
function block_php_report_get_taskname_from_id($scheduleid) {
    return 'scheduled_' . $scheduleid;
}

/**
 * Converts a PHP report schedule column to an associated ELIS
 * task name, for use dynamically in an SQL statement
 *
 * @param   string  $sql_column  A column name that represents the PHP report
 *                               schedule id
 *
 * @return  string               A satement providing a calculated SQL attribute that will
 *                               match the ELIS taskname in the database
 */
function block_php_report_get_taskname_from_column($sql_column) {
    return sql_concat("'scheduled_'", $sql_column);
}

/**
 * Obtains a recordset containing information about all scheduled PHP
 * report jobs for a particular report (to be used only from the scheduling UI since
 * this depends on the current user)
 *
 * @param   string     $report_shortname  Shortname of the report whose jobs we are looking up
 * @param   string     $fields            String containing the comma-separated list of fiedls
 *                                        to select, or NULL to use the default fields
 *
 * @return  recordset                     A recordset referring to the related records
 */
function block_php_report_get_report_jobs_recordset($report_shortname, $fields = NULL) {
    global $CFG, $USER;

    //need this concatenation to connect ELIS and PHP scheduling info
    $concat = block_php_report_get_taskname_from_column('schedule.id');

    if ($fields === NULL) {
        //default set of database fields to select
        $fields = "user.firstname,
                   user.lastname,
                   task.lastruntime,
                   task.nextruntime,
                   schedule.config,
                   schedule.id AS scheduleid";
    }

    //main query, involving ELIS and PHP schedling info
    $sql = "SELECT {$fields}
            FROM
            {$CFG->prefix}elis_scheduled_tasks task
            JOIN {$CFG->prefix}php_report_schedule schedule
              ON task.taskname = {$concat}
            JOIN {$CFG->prefix}user user
              ON schedule.userid = user.id
            WHERE schedule.report = '{$report_shortname}'";

    if (!has_capability('block/php_report:manageschedules', get_context_instance(CONTEXT_SYSTEM))) {
        //user does not have the necessary capability for viewing all scheduled instances,
        //so limit to their own
        $sql .= ' AND user.id = ' . $USER->id;
    }

    return get_recordset_sql($sql);
}

/**
 * Calculates a new label for a copy of an existing PHP report schedule
 * based on the existing schedule's name
 *
 * @param   string  $parent_label  The label from the original schedule instance
 *
 * @return  string                 The label for the new schedule instance
 */
function block_php_report_get_copy_label($parent_label) {
    //first guess at to our copy number
    $i = 1;
    $done = false;

    while (!$done) {
        //get the proposed label
        $a = new stdClass;
        $a->label = $parent_label;
        $a->index = $i;
        $label = get_string('task_copy_label', 'block_php_report', $a);

        //look for records containing the proposed namy anywhere in their config data
        //(may include false-positives but very unlikely)
        if ($records = get_recordset_select('php_report_schedule', 'config ' . sql_ilike() . " '%{$label}%'")) {
            //track whether an exact match was found
            $found = false;
            
            //go through all possible matches
            while ($record = rs_fetch_next_record($records)) {
                //perform an exact comparison
                $config = unserialize($record->config);
                if ($config['label'] == $label) {
                    //exact match
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                //all cases were false, positive, so accept
                $done = true;
            } else {
                //exact match, so increment and try again
                $i++;
            }
        } else {
            //no config contained the proposed label, so accept
            $done = true;
        }
    }

    return $label;
}

/**
 * Creates a copy of the specified report schedule instance in the database
 *
 * @param   int      $id  The record id of the PHP report schedule to copy
 *
 * @return  boolean       true on success, otherwise false
 */
function block_php_report_copy_schedule_instance($id) {
    if ($php_report_record = get_record('php_report_schedule', 'id', $id)) {

        $config = unserialize($php_report_record->config);
        //update modified time to right now
        $config['timemodified'] = time();
        //modify the label to prevent duplicates
        $config['label'] = block_php_report_get_copy_label($config['label']);
        $php_report_record->config = serialize($config);
        $php_report_record->id = insert_record('php_report_schedule', addslashes_recursive($php_report_record));

        //handle associated ELIS schedule records
        $taskname = block_php_report_get_taskname_from_id($id);
        if ($elis_records = get_recordset('elis_scheduled_tasks', 'taskname', $taskname)) {
            while ($elis_record = rs_fetch_next_record($elis_records)) {
                //make sure this points back to the new PHP report schedule instance
                $elis_record->taskname = block_php_report_get_taskname_from_id($php_report_record->id);
                //new instance, so it's never run before
                $elis_record->lastruntime = 0;
                insert_record('elis_scheduled_tasks', addslashes_recursive($elis_record));
            }
            return true;
        }
    }

    //not found, so signal failure
    return false;
}

/**
 * Deletes a PHP report schedule instance and all associated
 * scheduled ELIS tasks
 *
 * @param   int      $id  The record id of the PHP report schedule to delete
 *
 * @return  boolean       true on success, otherwise false
 */
function block_php_report_delete_schedule_instance($id) {
    //make sure the record is valid
    if (record_exists('php_report_schedule', 'id', $id)) {
        //delete all associated ELIS scheduled tasks
        $taskname = block_php_report_get_taskname_from_id($id);
        delete_records('elis_scheduled_tasks', 'taskname', $taskname);
        //delete the task itself
        delete_records('php_report_schedule', 'id', $id);
        return true;
    }

    //couldn't find the record, so signal failure
    return false;
}

/**
 * Obtains a listing of report display names, grouped by category
 *
 * @param   boolean       $require_exportable  If true, only include reports that are considered to be exportable
 *                                             in the context of scheduling
 * @param  int|NULL       $userid              Id of the Moodle user who this report is being
 *                                             for
 * @param  int            $execution_mode      The mode in which this report is being executed
 *
 * @return  string array                       Mapping of category shortname to mappings of
 *                                             report shortnames to display names (category entries
 *                                             will exist but be empty if no reports are in that category)
 */
function block_php_report_get_names_by_category($require_exportable = false, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
    global $CFG;

    $category_members = array();

    //get a listing of the different categories
    $categories = block_php_report_get_category_mapping();

    //initialize a bucket to store a category's reports where applicable
    foreach ($categories as $category_key => $category_display) {
        $category_members[$category_key] = array();
    }

    //go through the directories
    if ($handle = opendir($CFG->dirroot . '/blocks/php_report/instances')) {

        while (false !== ($report_shortname = readdir($handle))) {
            //get the report instance (this inherently checks permissions and report availability)
            if($instance = php_report::get_default_instance($report_shortname, $userid, $execution_mode)) {

                //determine if the export action is available in the context of scheduling
                $export_available = true;

                //check permissions and make sure access is not explicitly disallowed in the current execution mode
                if (!$instance->can_view_report()) {
                    $export_available = false;
                }

                //make sure there is at least one available export format
                $export_formats = $instance->get_export_formats();
                if (count($export_formats) == 0) {
                    $export_available = false;
                }

                if (!$require_exportable || $export_available) {
                    $category_shortname = $instance->get_category();
                    $report_shortname = $instance->get_report_shortname();
                    $category_members[$category_shortname][$report_shortname] = $instance->get_display_name();
                } 
            }
        }

        //sort reports by display name within each category
        foreach ($category_members as $category_shortname => $bucket) {
            sort($bucket);
            $category_members[$category_shortname];
        }
    }

    return $category_members;
}

/**
 * Specifies the capability we need to check for a particular custom field to be
 * checked for course-level custom field access in a report
 *
 * @param   array           $owners                   shortname-indexed collection of all field owners
 * @param   string          $default_view_capability  capability to use if default setting was selected
 *                                                    on the manual field owner
 *
 * @return  string|boolean                            returns the capability we need to check, or false
 *                                                    on failure
 */
function block_php_report_field_capability($owners, $default_view_capability = 'block/curr_admin:course:view') {

    if (isset($owners['manual'])) {
        //the manual owner contains the permissions info
        $manual_owner = $owners['manual'];
        $params = unserialize($manual_owner->params);

        if (isset($params['view_capability'])) {
            //found the view capability settings
            $view_capability = $params['view_capability'];
            if ($view_capability === '') {
                //default flag, so use the specified default
                $view_capability = $default_view_capability;
            }

            return $view_capability;
        }
    }

    //data error
    return false;
}

/**
 * Specifies whether a course-level custom field is accessible to the 
 * current user in at least once course context
 *
 * @param   array    $owners  shortname-indexed collection of all field owners
 *
 * @return  boolean           true if accessible, otherwise false
 */
function block_php_report_field_accessible($owners) {
    global $USER, $CFG;

    require_once($CFG->dirroot.'/curriculum/lib/contexts.php');

    if ($view_capability = block_php_report_field_capability($owners)) {
        //make sure the user has the view capability in some course
        $contexts = get_contexts_by_capability_for_user('course', $view_capability, $USER->id);
        return !$contexts->is_empty();
    } else {
        //data error
        return false;
    }
}

/**
 * This function adjusts a GMT timestamp to timezone
 * @param $timestamp
 * @param $timezone
 * @param mixed $dstdate default null uses $timestamp (param1) for dst calc
 *              false disables dst offset,
 *              otherwise dstdate value used in place of timestamp for dst calc
 * @return int  timestamp (secs since epoch) in timezone
 */
function from_gmt($timestamp, $timezone = 99, $dstdate = null) {
    $tz = get_user_timezone_offset($timezone);
    $ts = (abs($tz) > 13) ? $timestamp : ($timestamp + ($tz * HOURSECS));
    if ($dstdate === null) {
        $dstdate = $timestamp;
    }
    $dstoffset = null;
    if (!empty($dstdate) && ($timezone == 99 || !is_numeric($timezone))) {
        $dstdate = (abs($tz) > 13) ? $dstdate : ($dstdate + ($tz * HOURSECS));
        $strtimezone = is_numeric($timezone) ? NULL : $timezone;
        $dstoffset = dst_offset_on($dstdate, $strtimezone);
        $ts += $dstoffset; // TBD or -= see: to_gmt()
    }
    //debug_error_log("/blocks/php_report/shardlib.php::from_gmt({$timestamp}, {$timezone}): tz = {$tz} dstdate = {$dstdate} dstoffset = {$dstoffset} => {$ts}");
    return $ts;
}

/**
 * This function converts a timestamp in timezone to GMT (UTC)
 * @param $timestamp
 * @param $timezone
 * @param mixed $dstdate default null uses $timestamp (param1) for dst calc
 *              false disables dst offset,
 *              otherwise dstdate value used in place of timestamp for dst calc
 * @return int  adjusted timestamp (secs since epoch)
 */
function to_gmt($timestamp, $timezone = 99, $dstdate = null) {
    if ($dstdate == null) {
        $dstdate = $timestamp;
    }
    $ts = $timestamp;
    $dstoffset = null;
    if (!empty($dstdate) && ($timezone == 99 || !is_numeric($timezone))) {
        $strtimezone = is_numeric($timezone) ? NULL : $timezone;
        $dstoffset = dst_offset_on($dstdate, $strtimezone);
        $ts -= $dstoffset; // or += see to_gmt()
    }
    $tz = get_user_timezone_offset($timezone);
    $ts = (abs($tz) > 13) ? $ts : ($ts - ($tz * HOURSECS));
    //debug_error_log("/blocks/php_report/sharedlib.php::to_gmt({$timestamp}, {$timezone}): tz = {$tz} dstdate = {$dstdate} dstoffset = {$dstoffset} => $ts");
    return $ts;
}

/**
 * Function to check debug level for DEBUG_DEVELOPER
 * and output string to web server error log file.
 */
function debug_error_log($str) {
    if (debugging('', DEBUG_DEVELOPER)) {
        error_log($str);
    }
}

?>
