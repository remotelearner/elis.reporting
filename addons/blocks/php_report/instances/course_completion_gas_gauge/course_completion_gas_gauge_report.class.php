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
 * @subpackage pm-blocks-phpreport-course_completion_gas_gauge
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/gas_gauge_table_report.class.php');

class course_completion_gas_gauge_report extends gas_gauge_table_report {

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_COURSE;
    }

    /**
     * Specifies whether the current report is available
     *
     * @uses $CFG
     * @uses $DB
     * @return  boolean  True if the report is available, otherwise false
     */
    function is_available() {
        global $CFG, $DB;

        //we need the /elis/program/ directory
        if (!file_exists($CFG->dirroot .'/elis/program/lib/setup.php')) {
            return false;
        }

        //we also need the curr_admin block
        if (!$DB->record_exists('block', array('name' => 'curr_admin'))) {
            return false;
        }

        //everything needed is present
        return true;
    }

    /**
     * Require any code that this report needs
     * (only called after is_available returns true)
     */
    function require_dependencies() {
        global $CFG;

        require_once($CFG->dirroot .'/elis/program/lib/setup.php');

        //needed for constants that define db tables
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php'); // status id values
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');
        require_once($CFG->dirroot .'/elis/program/userpage.class.php');

        //needed to include for filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/setselect.php');
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the number of pages
     *
     * @return  array   The sql query that specifies the number of pages
     *                  as its only field with optional params
     */
    function get_num_pages_sql() {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('id', 'course');

        $sql = 'SELECT COUNT(*)
                FROM {'. course::TABLE .'}
               ';

        $filter_obj = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_obj->get_sql(false, null, SQL_PARAMS_NAMED);
        $params = array();
        if (isset($filter_sql['where'])) {
            $sql .= 'WHERE ('. $filter_sql['where'] .')';
            $params = $filter_sql['where_parameters'];
        }

        return array($sql, $params);
    }

    /**
     * Generates a SQL query to determine the tooltip text on mouseover for page bar links
     *
     * @return  array   The sql query that specifies the name
     *                  as its only field with optional params
     */
    function get_page_tooltip_sql() {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('id', 'course');

        $sql = 'SELECT name
                FROM {'. course::TABLE .'}
               ';

        $filter_obj = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_obj->get_sql(false, null, SQL_PARAMS_NAMED);
        $params = array();
        if (isset($filter_sql['where'])) {
            $sql .= 'WHERE ('. $filter_sql['where'] .')';
            $params = $filter_sql['where_parameters'];
        }

        return array($sql, $params);
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the value used to uniquely identify the page you are currently on
     *
     * @param   int     $i  A number from 1 to n, where n is the number
     *                      of pages (as specified by get_num_page_values)
     *
     * @return  string      The sql query that specifies the value as its only field
     */
    function get_page_value_sql($i) {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('id', 'course');

        $sql = 'SELECT id
                FROM {'. course::TABLE .'}
               ';

        $filter_obj = $contexts->get_filter('id', 'course');
        $filter_sql = $filter_obj->get_sql(false, null, SQL_PARAMS_NAMED);
        $params = array();
        if (isset($filter_sql['where'])) {
            $sql .= 'WHERE ('. $filter_sql['where'] .')';
            $params = $filter_sql['where_parameters'];
        }

        return array($sql, $params);
    }

    /**
     * Specifies an ORDER BY clause added to the page value SQL statement
     * to guarantee a consistent page ordering
     *
     * @return  string  The appropriate ORDER BY clause
     */
    function get_page_value_order_by() {
        //pages should be ordered by course name
        return "ORDER BY name";
    }

    /**
     * Method that specifies the report's columns
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        //query that calculates number completed as a percentage
        $percent_complete_sql = $this->get_report_sql('COUNT(ccg.id) / COUNT(cc.id) * 100', true);
        //query that calculates total number completed
        $num_complete_sql = $this->get_report_sql('COUNT(ccg.id)', true);
        //query that calculates average final course grade
        $avg_score_sql = $this->get_report_sql('AVG(stu.grade)', true);

        //CM class idnumber
        $class_heading = get_string('column_class', 'rlreport_course_completion_gas_gauge');
        $class_column = new table_report_column('cls.idnumber AS classidnumber', $class_heading, 'classidnumber');

        //class enrolment status
        $completion_heading = get_string('column_completestatus', 'rlreport_course_completion_gas_gauge');
        $completion_column = new table_report_column('stu.completestatusid AS completestatus',
                                                     $completion_heading, 'completestatus');

        //user fullname
        $name_heading = get_string('column_fullname', 'rlreport_course_completion_gas_gauge');
        $name_column = new table_report_column('u.firstname', $name_heading, 'fullname', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_PDF, php_report::$EXPORT_FORMAT_HTML));
        $lastname_heading = get_string('column_lastname', 'rlreport_course_completion_gas_gauge');
        $lastname_column = new table_report_column('u.lastname', $lastname_heading, 'lastname', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));
        $firstname_heading = get_string('column_firstname', 'rlreport_course_completion_gas_gauge');
        $firstname_column = new table_report_column('u.firstname as userfirstname', $firstname_heading, 'firstname', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));

        //percent of completion elements complete
        $percentcomplete_heading = get_string('column_percentcomplete', 'rlreport_course_completion_gas_gauge');
        $percentcomplete_column = new table_report_column('0 AS percentcomplete', $percentcomplete_heading, 'percentcomplete',
                                                          'right', false, true, true, NULL, $percent_complete_sql);

        //number of completion elements complete
        $numcomplete_heading = get_string('column_numcompleted', 'rlreport_course_completion_gas_gauge');
        $numcomplete_column = new table_report_column('COUNT(ccg.id) AS numcompleted', $numcomplete_heading, 'numcompleted',
                                                      'left', false, true, true, NULL, $num_complete_sql);

        //moodle gradebook grade
        $grade_heading = get_string('column_score', 'rlreport_course_completion_gas_gauge');
        $grade_column = new table_report_column('gg.finalgrade AS score', $grade_heading, 'score', 'right',
                                                false, true, true, NULL, $avg_score_sql);

        //collection of all columns
        return array(
                $class_column,
                $completion_column,
                $name_column,      // HTML, PDF
                $lastname_column,  // CSV/EXCEL
                $firstname_column, // CSV/EXCEL
                $percentcomplete_column,
                $numcomplete_column,
                $grade_column);
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
        //filter by user inactive status

        $inactive_yes_label = get_string('filter_inactive_yes', 'rlreport_course_completion_gas_gauge');
        $inactive_no_label = get_string('filter_inactive_no',  'rlreport_course_completion_gas_gauge');
        $choices = array(
                $inactive_yes_label => array(0, 1),
                $inactive_no_label => array(0));
        $inactive_options = array('choices' => $choices,
                                  'default'  => array(0),
                                  //'anyvalue' => array(0, 1),
                                  'numeric' => true);
        // Need help text
        $inactive_options['help'] = array('course_completion_gas_gauge_inactive',
                                    get_string('filter_inactive_help', 'rlreport_course_completion_gas_gauge'),
                                    'rlreport_course_completion_gas_gauge');

        $inactive_label = get_string('filter_inactive', 'rlreport_course_completion_gas_gauge');
        return array(new generalized_filter_entry('inactive', 'u', 'inactive', $inactive_label, false,
                                                  'setselect', $inactive_options));
    }

    /**
     * Specifies available report filters for the page value
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_page_value_filters() {
        //filter by CM course the current user has access to

        $this->require_dependencies();
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);

        $course_choices = array();
        if ($listing = course_get_listing('crs.name', 'ASC', 0, 0, '', '', $contexts)) {
            foreach ($listing as $element) {
                $course_choices[$element->id] = $element->name;
            }
        }
        $course_options  = array('choices' => $course_choices,
                                 'numeric' => true);

        $course_label = get_string('filter_course', 'rlreport_course_completion_gas_gauge');
        return array(new generalized_filter_entry('course', '', 'id', $course_label, false, 'simpleselect', $course_options));
    }

    /**
     * Method to be implemented, which should return
     * the report's main SQL statement
     *
     * @param   array  $columns   The list of columns automatically calculated
     *                            by get_select_columns()
     * @param   bool   $addfilter if true manually adds filter SQL to query,
     *                            false (default) doesn't include filter SQL.
     * @return  array  The report's main sql statement with optional params
     */
    function get_report_sql($columns, $addfilter = false) {
        //calculates the condition imposed by the current top-level page
        $page_value_condition = $this->get_page_value_condition('crs.id');

        $lastname = 'u.lastname';
        if (stripos($columns, $lastname) === FALSE) {
            $columns .= ", {$lastname}";
        }
        $sql = "SELECT {$columns}, COUNT(cc.id) AS numcompletionelements,
                u.id AS cmuserid, gi.grademax, stu.grade AS elisgrade
                FROM {". user::TABLE .'} u
                JOIN {'. student::TABLE .'} stu
                  ON u.id = stu.userid
                JOIN {'. pmclass::TABLE .'} cls
                  ON cls.id = stu.classid
                JOIN {'. course::TABLE .'} crs
                  ON cls.courseid = crs.id
                LEFT JOIN {'. coursecompletion::TABLE .'} cc
                  ON cc.courseid = crs.id
                LEFT JOIN {'. student_grade::TABLE .'} ccg
                  ON ccg.completionid = cc.id
                  AND cls.id = ccg.classid
                  AND stu.userid = ccg.userid
                  AND ccg.locked = 1
                LEFT JOIN {'. classmoodlecourse::TABLE ."} clsmdl
                  ON cls.id = clsmdl.classid
                LEFT JOIN {course} mdlcrs
                  ON clsmdl.moodlecourseid = mdlcrs.id
                LEFT JOIN {grade_items} gi
                  ON mdlcrs.id = gi.courseid
                  AND gi.itemtype = 'course'
                LEFT JOIN {user} mdlu
                  ON u.idnumber = mdlu.idnumber
                LEFT JOIN {grade_grades} gg
                  ON mdlu.id = gg.userid
                  AND gi.id = gg.itemid
                ";

        $params = array();
        if (!empty($page_value_condition[0])) {
            $sql .= 'WHERE '. $page_value_condition[0];
            $params = $page_value_condition[1];
        }

        if ($addfilter) {
            $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters());
            if (!empty($sql_filter[0])) {
                $op = (stripos($sql, 'WHERE') === false) ? 'WHERE' : 'AND';
                $sql .= " {$op} ({$sql_filter[0]})";
                $params = array_merge($params, $sql_filter[1]);
            }
        }
        //error_log("course_completion_gas_guage::get_report_ssql({$columns}, {$addfilter}); SQL => {$sql}");

        return array($sql, $params);
    }

    /**
     * Takes a record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {
        if ($record->completestatus == STUSTATUS_PASSED) {
            $record->completestatus = get_string('status_complete', 'rlreport_course_completion_gas_gauge');
        } else {
            $record->completestatus = get_string('status_incomplete', 'rlreport_course_completion_gas_gauge');
        }

        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            //convert user name to their full name and link to the CM user page for that user
            $userpage = new userpage(array('id' => $record->cmuserid, 'action' => 'view'));
            $record->firstname = '<span class="external_report_link"><a href="'. $userpage->url .'" target="_blank">'. fullname($record) .'</a></span>';
        } else if ($export_format != php_report::$EXPORT_FORMAT_CSV &&
                   $export_format != php_report::$EXPORT_FORMAT_EXCEL) {
            $record->firstname = fullname($record);
        }

        //calculate the percentage of completion elements satisfied
        if ($record->numcompletionelements == 0) {
            $record->percentcomplete = get_string('na', 'rlreport_course_completion_gas_gauge');
        } else {
            $record->percentcomplete = $record->numcompleted / $record->numcompletionelements * 100;
            $record->percentcomplete = number_format($record->percentcomplete, 1);
        }

        //display logic for the count of completion elements
        //$record->numcompleted = $record->numcompleted . ' / ' . $record->numcompletionelements;
        $record->numcompleted = get_string('completed_tally', 'rlreport_course_completion_gas_gauge', $record);

        //format the Moodle gradebook course score
        // ELIS-4916(ELIS-4439): now using ELIS grade!
        //if ($record->score === NULL || empty($record->grademax)) {
        if (!empty($record->elisgrade)) {
            $record->score = pm_display_grade($record->elisgrade);
            if (is_numeric($record->score) && $export_format != php_report::$EXPORT_FORMAT_CSV) {
                $record->score .= get_string('percent_symbol', 'rlreport_course_completion_gas_gauge');
            }
        } else {
            $record->score = get_string('na', 'rlreport_course_completion_gas_gauge');
        }

        return $record;
    }

    /**
     * Takes a summary row record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @return stdClass  The reformatted record
     */
    function transform_column_summary_record($record) {
        //calculate the percentage of completion elements satisfied
        if ($record->percentcomplete == NULL) {
            $record->percentcomplete = get_string('na', 'rlreport_course_completion_gas_gauge');
        } else {
            $record->percentcomplete = number_format($record->percentcomplete, 1);
        }

        //format the Moodle gradebook course score
        if ($record->score === NULL) {
            $record->score = get_string('na', 'rlreport_course_completion_gas_gauge');
        } else {
            $record->score = pm_display_grade($record->score);
            // TBD: NO $export_format passed to append '%'
        }

        return $record;
    }

    /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return "crs.id,
                cls.id,
                stu.userid";
    }

    /**
     * Specifies string of sort columns and direction to
     * order by if no other sorting is taking place (either because
     * manual sorting is disallowed or is not currently being used)
     *
     * @return  string  String specifying columns, and directions if necessary
     */
    function get_static_sort_columns() {
        //sort by lastname and firstname to keep user's enrolments together
        return 'u.lastname,
                u.firstname,
                u.id,
                classidnumber';
    }

    /**
     * Calculates the current value of the gas gauge
     *
     * @param   mixed  $key  The unique key that represents the current page you are on,
     *                       as specified by get_page_value
     *
     * @return  int          The current value of the gas gauge
     */
    function get_gas_gauge_value($key) {
        //common sql fragment
        $base_sql = 'SELECT COUNT(DISTINCT u.id)
                     FROM {'. user::TABLE .'} u
                     JOIN {'. student::TABLE .'} stu
                       ON u.id = stu.userid
                     JOIN {'. pmclass::TABLE .'} cls
                       ON cls.id = stu.classid
                     JOIN {'. course::TABLE .'} crs
                       ON cls.courseid = crs.id
                     WHERE crs.id = :crsid';

        $params = array('crsid' => $this->gas_gauge_page_value);

        //take the inactive setting into account
        $sql_filter = $this->filter->get_sql_filter('', array(),
                                  $this->allow_interactive_filters(),
                                  $this->allow_configured_filters());
        if (!empty($sql_filter[0])) {
            $base_sql .= " AND ({$sql_filter[0]})";
            $params += $sql_filter[1];
        }

        //number of completed users
        $completed_sql = "{$base_sql}
                          AND stu.completestatusid = " . STUSTATUS_PASSED;
        $completed_field = $this->get_field_sql($completed_sql, $params);

        //total number of users
        $total_sql = "{$base_sql}";
        $this->total_field = $this->get_field_sql($total_sql, $params);
        //avoid dividing by zero
        if (empty($this->total_field)) {
            return 0;
        }

        //return the percentage of distinct users who are completed
        return $completed_field / $this->total_field * 100;
    }

    /**
     * Calculates the maximum value of the gas gauge
     *
     * @param   mixed  $key  The unique key that represents the current page you are on,
     *                       as specified by get_page_value
     * @uses    $DB
     * @return  int          The maximum value of the gas gauge
     */
    function get_gas_gauge_max_value($key) {
        global $DB;
        $base_sql = 'SELECT * FROM {'. user::TABLE .'} u
                     JOIN {'. student::TABLE .'} stu
                       ON u.id = stu.userid
                     JOIN {'. pmclass::TABLE .'} cls
                       ON cls.id = stu.classid
                     JOIN {'. course::TABLE .'} crs
                       ON cls.courseid = crs.id
                     WHERE crs.id = :crsid';

        $params = array('crsid' => $this->gas_gauge_page_value);

        //take the inactive setting into account
        $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(),
                                                    $this->allow_configured_filters());
        if (!empty($sql_filter[0])) {
            $base_sql .= " AND ({$sql_filter[0]})";
            $params += $sql_filter[1];
        }

        if ($DB->record_exists_sql($base_sql, $params)) {
            //gas gauge actually has data, so use 100 as the maximum value
            return $this->static_max_value;
        } else {
            //no data, so don't display the gas gauge
            return 0;
        }
    }

    /**
     * Specifies the information displayed near the gas gauge at the top
     * of the report
     *
     * @uses    $DB
     * @return  string array  The values to display at the top of the report
     */
    function get_gas_gauge_header_info() {
        global $DB;
        $course_name = '';
        if ($course_record = $DB->get_record(course::TABLE, array('id' => $this->gas_gauge_page_value))) {
            $course_name = $course_record->name;
        }

        //course description, including course name
        $course_description = get_string('course_description', 'rlreport_course_completion_gas_gauge', $course_name);

        if (empty($this->total_field)) {
            $course_progress = get_string('no_enrolments', 'rlreport_course_completion_gas_gauge');
        } else {
            //general progress status message
            if ($this->gas_gauge_value === NULL) {
                $display_value = get_string('na', 'rlreport_course_completion_gas_gauge');
            } else {
                $display_value = number_format($this->gas_gauge_value, 1);
            }
            $course_progress = get_string('course_progress', 'rlreport_course_completion_gas_gauge', $display_value);
        }

        return array($course_description, $course_progress);
    }

    /**
     * Specifies the string used to label the gas-gauge-level pages
     *
     * @return  string  A string to display, or the empty string to use the default label
     */
    function get_gas_gauge_page_label() {
        //override the default label with a course-related label
        return get_string('course_paging_label', 'rlreport_course_completion_gas_gauge');
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one course context
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    /**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        return array(php_report::$EXPORT_FORMAT_PDF,
                     php_report::$EXPORT_FORMAT_CSV);
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RBG components of the colour used in the background when
     * displaying the report display name
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_display_name_colour() {
        return array(255, 255, 255);
    }

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(246, 245, 245);
    }

    /**
     * Specifies the RGB components of one or more colours used in an
     * alternating fashion as report row backgrounds
     *
     * @return  array array  Array containing arrays of red, green, and blue components
     *                       (one array for each alternating colour)
     */
    function get_row_colours() {
        return array(array(255, 255, 255));
    }
}

