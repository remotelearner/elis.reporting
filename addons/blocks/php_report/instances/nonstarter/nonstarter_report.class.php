<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage cm-blocks-phpreport-nonstarter
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');

define('NSR_DATE_FORMAT', get_string('date_format', 'rlreport_nonstarter'));

class nonstarter_report extends table_report {

    /**
     * Constants for unique filter ids
     */
    const datefilterid = 'datef';

    /**
     * Date filter start and end dates
     * populated using: get_filter_values()
     */
    var $startdate = 0;
    var $enddate = 0;

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_ADMIN;
    }

    /**
     * Specifies whether the current report is available
     * (a.k.a. any the CM system is installed)
     *
     * @uses $CFG
     * @param none
     * @return  boolean  true if the report is available, otherwise false
     */
    function is_available() {
        global $CFG;

        //we need the curriculum directory
        if (!file_exists($CFG->dirroot.'/curriculum/config.php')) {
            return false;
        }

        //we also need the curr_admin block
        if (!record_exists('block', 'name', 'curr_admin')) {
            return false;
        }

        //everything needed is present
        return true;
    }

    /**
     * Require any code that this report needs
     *
     * @uses $CFG
     * @param none
     * @return none
     */
    function require_dependencies() {
        global $CFG;

        //needed to define CURMAN_DIRLOCATION
        require_once($CFG->dirroot . '/curriculum/config.php');

        //needed for options filters
        require_once($CFG->dirroot . '/curriculum/lib/filtering/lib.php');
        require_once($CFG->dirroot . '/curriculum/lib/filtering/date.php');

        require_once($CFG->dirroot .'/curriculum/lib/student.class.php');
        require_once($CFG->dirroot .'/curriculum/lib/user.class.php');
    }

    /**
     * Display name of report
     *
     * @uses none
     * @param none
     * @return string - the display name of the report
     */
    function get_display_name() {
        return get_string('displayname', 'rlreport_nonstarter');
    }

    /**
     * Specifies a header icon image
     *
     * @uses $CFG
     * @param none
     * @return  string - Full path to JPEG header logo
     */
    function get_preferred_header_icon() {
        global $CFG;
        return $CFG->wwwroot . '/blocks/php_report/pix/nonstarter_report_logo.jpg';
    }

    /**
     * Specifies a field to sort by default
     *
     * @uses none
     * @param none
     * @return string - sort field
     */
    function get_default_sort_field() {
        return 'u.lastname AS lastname';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @uses none
     * @param none
     * @return string - sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    /**
     * Retrieves start and end settings from active filter (if exists)
     * and populates class properties: startdate and enddate
     *
     * @uses none
     * @param none
     * @return none
     */
    function get_filter_values() {

        $start_enabled =  php_report_filtering_get_active_filter_values(
                              $this->get_report_shortname(),
                              nonstarter_report::datefilterid . '_sck',
                              $this->filter);
        $start = 0;
        if (!empty($start_enabled) && is_array($start_enabled)
            && !empty($start_enabled[0]['value'])) {
            $start = php_report_filtering_get_active_filter_values(
                         $this->get_report_shortname(),
                         nonstarter_report::datefilterid . '_sdt',
                         $this->filter);
        }

        $end_enabled = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           nonstarter_report::datefilterid . '_eck',
                           $this->filter);
        $end = 0;
        if (!empty($end_enabled) && is_array($end_enabled)
            && !empty($end_enabled[0]['value'])) {
            $end = php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(),
                       nonstarter_report::datefilterid . '_edt',
                       $this->filter);
        }

        $this->startdate = (!empty($start) && is_array($start))
                           ? $start[0]['value'] : 0;
        $this->enddate = (!empty($end) && is_array($end))
                         ? $end[0]['value'] : 0;

        //$this->err_dump($start, '$datefilter(2)_sdt');
        //$this->err_dump($end, '$datefilter(2)_edt');
        //error_log("nonstarter::get_filter_values() ... startdate={$this->startdate} enddate={$this->enddate}");
    }

    /**
     * Specifies the report title
     *
     * @uses none
     * @param none
     * @return array - header entires
     */
    function get_header_entries() {
        // Get date filter parameters req'd for header title
        $this->get_filter_values();
        $sdate = $this->userdate($this->startdate, NSR_DATE_FORMAT);
        $edate = !empty($this->enddate)
                 ? $this->userdate($this->enddate, NSR_DATE_FORMAT)
                 : get_string('present', 'rlreport_nonstarter');
        $header_obj = new stdClass;
        $header_obj->label = get_string('report_heading', 'rlreport_nonstarter');
        $header_obj->value = "{$sdate} - {$edate}";
        $header_obj->css_identifier = '';
        return array($header_obj);
    }

    /*
     * Add report title to report
     */
    function print_report_title() {
        /* Don't need a report title for this report */
    }

    /**
     * Specifies available report filters
     * (empty by default but can be implemented by child class)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     *
     * @return  array                The list of available filters
     */
    function get_filters($init_data = true) {
        $filterhelp = array('nonstarter_report_help',
                            get_string('nonstarter_report_help', 'rlreport_nonstarter'),
                            'block_php_report');
        return( array(
            // date range filter init
            // NOTE: $tablealias(param2) & $fieldname(param3) intentionally
            //       set to ''(empty string) - used internally;
            //       do _not_ want date values appended to main SQL query!
            // see also: curriculum/lib/filtering/date.php::get_sql_filter($data)
            // it must return null if get_full_fieldname() returns empty string

            new generalized_filter_entry(nonstarter_report::datefilterid, '',
                '', get_string('filter_date_range', 'rlreport_nonstarter'),
                false, 'date', array('help' => $filterhelp))
            )
        );
    }

    /**
     * Takes a record and transform it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {
        $record->lastname = fullname($record);
        return $record;
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignments
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @uses $CFG
     * @return  string  The report's main sql statement
     */
    function get_report_sql($columns) {
        global $CFG; //, $CURMAN;

        // Get date filter values req'd in query
        $this->get_filter_values();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);

        //make sure we only count courses within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('crlmusr.id', 'user');
        //error_log("Non-starter::get_report_sql(): permissions_filter = {$permissions_filter}");

        // Only want users with not-complete status
        $stustatus = STUSTATUS_NOTCOMPLETE;

        // Also require Moodle-Only query w/o CM tables!
        $sql = "SELECT {$columns}, u.firstname as firstname
           FROM {$CFG->prefix}crlm_curriculum cur
           JOIN {$CFG->prefix}crlm_curriculum_course curcrs ON curcrs.curriculumid = cur.id
           JOIN {$CFG->prefix}crlm_course crs ON crs.id = curcrs.courseid
           JOIN {$CFG->prefix}crlm_class cls
                ON cls.courseid = crs.id
          ";
        // Check that the class is open during report dates
        if (!empty($this->startdate)) {
            $sql .= "      AND (cls.enddate = 0 OR {$this->startdate} <= cls.enddate)
          ";
        }
        if (!empty($this->enddate)) {
            $sql .= "      AND (cls.startdate = 0 OR {$this->enddate} >= cls.startdate)
          ";
        }
        $sql .= " LEFT JOIN {$CFG->prefix}crlm_class_moodle clsm ON cls.id = clsm.classid
           JOIN {$CFG->prefix}crlm_class_enrolment clsenr
                ON clsenr.classid = cls.id
                AND clsenr.completestatusid = {$stustatus}
           JOIN {$CFG->prefix}crlm_user crlmusr ON clsenr.userid = crlmusr.id
           JOIN {$CFG->prefix}user u ON u.idnumber = crlmusr.idnumber
           AND NOT EXISTS
               (SELECT * FROM {$CFG->prefix}crlm_class_graded ccg
                  JOIN {$CFG->prefix}crlm_course_completion ccc
                    ON ccc.id = ccg.completionid
                       AND ccg.grade >= ccc.completion_grade
                 WHERE ccg.userid = crlmusr.id AND ccg.classid = cls.id
                       AND ccg.locked = 1 ";
        if (!empty($this->startdate)) {
            $sql .= "AND ccg.timegraded >= {$this->startdate} ";
        }
        if (!empty($this->enddate)) {
            $sql .= "AND ccg.timegraded <= {$this->enddate} ";
        }
        $sql .= ")";

        /** *****
            Optimization was too remove following etl code block
            since it should be based on the mdl_log table data;
            but, results were different with multiple sets of data !?!?!?
        ** ******/
        // Exclude users with etl_user_activity for course
        $sql .= "
           AND NOT EXISTS
               (SELECT * FROM {$CFG->prefix}etl_user_activity
                 WHERE courseid = clsm.moodlecourseid AND userid = u.id ";
        if (!empty($this->startdate)) {
            $sql .= "AND hour >= {$this->startdate} ";
        }
        if (!empty($this->enddate)) {
            $sql .= "AND hour <= {$this->enddate} ";
        }
        $sql .= ')';

        // Exclude users with log entries for Moodle course
        // TBD: or is this just for Moodle Only version of report?
        $sql .= "
           AND NOT EXISTS
               (SELECT * FROM {$CFG->prefix}log
                 WHERE u.id = userid AND module = 'course'
                       AND course = clsm.moodlecourseid ";
        if (!empty($this->startdate)) {
            $sql .= "AND time >= {$this->startdate} ";
        }
        if (!empty($this->enddate)) {
            $sql .= "AND time <= {$this->enddate} ";
        }
        $sql .= ")
           WHERE {$permissions_filter} ";

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $sql .= ' AND crlmusr.inactive = 0';
        }

        //error_log("nonstarter_report.php::get_report_sql($columns); sql={$sql}");
        return $sql;
    }

    /**
     *  Required columns for Report
     *
     * @uses none
     * @param none
     * @return array of table report columns
     */
    function get_columns() {
        return array(
            new table_report_column('u.lastname AS lastname', get_string('column_studentname', 'rlreport_nonstarter'), 'studentname', 'left', true),
            new table_report_column('u.idnumber AS idnumber', get_string('column_idnumber', 'rlreport_nonstarter'), 'idnumber', 'left', true)
        );
    }

    /**
     * Method that specifies fields to group the results by
     * (header displayed when these fields change)
     *
     * @uses none
     * @param none
     * @return array - List of objects containing grouping id, field names,
     *                display labels and sort order
     */
     function get_grouping_fields() {
         return array(
                    new table_report_grouping('curriculum_name','cur.name',
                        get_string('grouping_curriculum', 'rlreport_nonstarter'), 'ASC', array(), 'above', 'cur.priority ASC, cur.name ASC'),
                    new table_report_grouping('course_name','crs.name',
                        get_string('grouping_coursename', 'rlreport_nonstarter'), 'ASC'),
                    new table_report_grouping('class_name','cls.idnumber',
                        get_string('grouping_classid', 'rlreport_nonstarter'), 'ASC')
                );
     }

    /**
     * Determines whether the current user can view this report,
     * based on being logged in and php_report:view capability
     *
     * @param none
     * @return  boolean - true if permitted, otherwise false
     */
    function can_view_report() {
        //Check for report view capability
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one context
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    // Debug helper function
    function err_dump($obj, $name = '') {
        ob_start();
        var_dump($obj);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log('err_dump:: '.$name." = {$tmp}");
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(169, 245, 173);
    }

    /**
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(array(217, 217, 217),
                     array(141, 179, 226),
                     array(198, 217, 241));
    }
}

?>
