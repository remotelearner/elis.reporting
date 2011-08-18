<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot . '/blocks/php_report/type/gas_gauge_table_report.class.php');

class class_completion_gas_gauge_report extends gas_gauge_table_report {
    var $lang_file = 'rlreport_class_completion_gas_gauge';

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_CLASS;
    }

    /**
     * Specifies whether the current report is available
     * (a.k.a. any the CM system is installed)
     *
     * @return  boolean  True if the report is available, otherwise false
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
     * (only called after is_available returns true)
     */
    function require_dependencies() {
        global $CFG;

        //needed to define CURMAN_DIRLOCATION
        require_once($CFG->dirroot . '/curriculum/config.php');
        //needed for constants that define db tables
        require_once($CFG->dirroot . '/curriculum/lib/user.class.php');
        require_once($CFG->dirroot . '/curriculum/lib/cmclass.class.php');
        //needed for completion status constants
        require_once($CFG->dirroot . '/curriculum/lib/student.class.php');
        //needed to get the complete status id values
        require_once($CFG->dirroot . '/curriculum/usermanagementpage.class.php');
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the number of pages
     *
     * @return  string  The sql query that specifies the number of pages
     *                  as its only field
     */
    function get_num_pages_sql() {
        global $CURMAN;

        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('id', 'class');

        return "SELECT COUNT(*)
                FROM {$CURMAN->db->prefix_table(CLSTABLE)}
                WHERE {$permissions_filter}";
    }

    /**
     * Generates a SQL query to determine the tooltip text on mouseover for page bar links
     *
     * @return  string  The sql query that specifies the text to use in the tooltip
     */
    function get_page_tooltip_sql() {
        global $CURMAN;

        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('id', 'class');

        $sql = "SELECT idnumber
                FROM {$CURMAN->db->prefix_table(CLSTABLE)}
                WHERE {$permissions_filter}";

        return $sql;
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
        global $CURMAN;

        //make sure context libraries are loaded
        $this->require_dependencies();

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        //make sure we only count courses within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('id', 'class');

        return "SELECT id
                FROM {$CURMAN->db->prefix_table(CLSTABLE)}
                WHERE {$permissions_filter}";
    }

    /**
     * Specifies an ORDER BY clause added to the page value SQL statement
     * to guarantee a consistent page ordering
     *
     * @return  string  The appropriate ORDER BY clause
     */
    function get_page_value_order_by() {
        //pages should be ordered by course name
        return "ORDER BY idnumber";
    }

    /**
     * Method that specifies the report's columns
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        //query that calculates number completed as a percentage
        $percent_complete_sql = $this->get_report_sql('COUNT(ccg.id) / COUNT(cc.id) * 100');
        //query that calculates total number completed
        $num_complete_sql = $this->get_report_sql('COUNT(ccg.id)');
        //query that calculates average final course grade
        $avg_score_sql = $this->get_report_sql('AVG(gg.finalgrade)');

        return array(new table_report_column('stu.completestatusid AS completestatus',
                                             get_string('column_completestatus', $this->lang_file),
                                             'completestatus'
                                            ),
                     new table_report_column('u.firstname',
                                             get_string('column_fullname', $this->lang_file),
                                             'fullname'
                                            ),
                     new table_report_column('0 AS percentcomplete',
                                             get_string('column_percentcomplete', $this->lang_file),
                                             'percentcomplete', 'right', false, true, true,
                                             NULL,
                                             $percent_complete_sql
                                            ),
                     new table_report_column('COUNT(ccg.id) AS numcompleted',
                                             get_string('column_numcompleted', $this->lang_file),
                                             'numcompleted', 'left', false, true, true,
                                             NULL,
                                             $num_complete_sql
                                            ),
                     new table_report_column('gg.finalgrade AS score',
                                             get_string('column_score', $this->lang_file),
                                             'score', 'right', false, true, true,
                                             NULL,
                                             $avg_score_sql
                                            )
                    );
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
        $inactive_options = array('choices' => array(get_string('filter_inactive_yes', $this->lang_file) => array(0, 1),
                                                     get_string('filter_inactive_no',  $this->lang_file) => array(0)),
                                  'numeric' => true,
                                  'help' => array('class_completion_gas_gauge_inactive_help',
                                                  get_string('filter_inactive', $this->lang_file),
                                                  'block_php_report')
                                 );

        return array(new generalized_filter_entry('inactive', 'u', 'inactive',
                                                  get_string('filter_inactive', $this->lang_file),
                                                  false, 'setselect', $inactive_options));
    }

    /**
     * Specifies available report filters for the page value
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_page_value_filters() {
        global $CFG;

        $filter_array = array();

        $filter_array[] = new generalized_filter_entry('class', '', 'id',
                                                       get_string('filter_class', $this->lang_file),
                                                       false, 'courseclassselect',
                                                       array('default'=>NULL,
                                                             'report_path'=>$CFG->wwwroot.'/blocks/php_report/instances/class_completion_gas_gauge/',
                                                             'help' => array('class_completion_gas_gauge_class_help',
                                                                             get_string('filter_class', $this->lang_file),
                                                                             'block_php_report')
                                                             )
                                                      );

        return $filter_array;
    }

    /**
     * Method to be implemented, which should return
     * the report's main SQL statement
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  string            The report's main sql statement
     */
    function get_report_sql($columns) {
        global $CURMAN;

        //calculates the condition imposed by the current top-level page
        $page_value_condition = $this->get_page_value_condition('cls.id');

        return "SELECT {$columns}, u.lastname, COUNT(cc.id) AS numcompletionelements, u.id AS cmuserid, gi.grademax
                FROM {$CURMAN->db->prefix_table(USRTABLE)} u
                JOIN {$CURMAN->db->prefix_table(STUTABLE)} stu
                    ON u.id = stu.userid
                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                    ON cls.id = stu.classid
                JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs
                    ON cls.courseid = crs.id
           LEFT JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} cc
                    ON cc.courseid = crs.id
           LEFT JOIN {$CURMAN->db->prefix_table(CLSGRTABLE)} ccg
                    ON ccg.completionid = cc.id
                    AND cls.id = ccg.classid
                    AND stu.userid = ccg.userid
                    AND ccg.locked = 1
           LEFT JOIN {$CURMAN->db->prefix_table(CLSMOODLETABLE)} clsmdl
                    ON cls.id = clsmdl.classid
           LEFT JOIN {$CURMAN->db->prefix_table('course')} mdlcrs
                    ON clsmdl.moodlecourseid = mdlcrs.id
           LEFT JOIN {$CURMAN->db->prefix_table('grade_items')} gi
                    ON mdlcrs.id = gi.courseid
                    AND gi.itemtype = 'course'
           LEFT JOIN {$CURMAN->db->prefix_table('user')} mdlu
                    ON u.idnumber = mdlu.idnumber
           LEFT JOIN {$CURMAN->db->prefix_table('grade_grades')} gg
                    ON mdlu.id = gg.userid
                    AND gi.id = gg.itemid
               WHERE {$page_value_condition}";
    }

    /**
     * Takes a record and transforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {

        //clear out the class info if there is only one class enrolment for the user and course
        if (isset($record->numclasses) && $record->numclasses == 1) {
            $record->classidnumber = '';
        }

        $status = student::$completestatusid_values[$record->completestatus];
        $record->completestatus = get_string($status, 'block_curr_admin');

        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            //convert user name to their full name and link to the CM user page for that user
            $userpage = new usermanagementpage(array('id' => $record->cmuserid, 'action' => 'view'));
            $record->firstname = '<span class="external_report_link"><a href="' . $userpage->get_url() . '" target="_blank">' . fullname($record) . '</a></span>';
        } else {
            $record->firstname = fullname($record);
        }

        //calculate the percentage of completion elements satisfied
        if ($record->numcompletionelements == 0) {
            $record->percentcomplete = get_string('na', $this->lang_file);
        } else {
            $record->percentcomplete = $record->numcompleted / $record->numcompletionelements * 100;
            $record->percentcomplete = number_format($record->percentcomplete, 1);
        }

        //display logic for the count of completion elements
        //$record->numcompleted = $record->numcompleted . ' / ' . $record->numcompletionelements;
        $record->numcompleted = get_string('completed_tally', $this->lang_file, $record);

        //format the Moodle gradebook course score
        if ($record->score === NULL || empty($record->grademax)) {
            $record->score = get_string('na', $this->lang_file);
        } else {
            $record->score = number_format($record->score/$record->grademax*100, 1);
        }

        return $record;
    }

    /**
     * Takes a summary row record and transforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @return stdClass  The reformatted record
     */
    function transform_column_summary_record($record) {
        //calculate the percentage of completion elements satisfied
        if ($record->percentcomplete == NULL) {
            $record->percentcomplete = get_string('na', $this->lang_file);
        } else {
            $record->percentcomplete = number_format($record->percentcomplete, 1);
        }

        //format the Moodle gradebook course score
        if ($record->score === NULL) {
            $record->score = get_string('na', $this->lang_file);
        } else {
            $record->score = number_format($record->score, 1);
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
                u.firstname';
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
        global $CURMAN;

        //common sql fragment
        $base_sql = "SELECT COUNT(DISTINCT u.id)
                     FROM {$CURMAN->db->prefix_table(USRTABLE)} u
                     JOIN {$CURMAN->db->prefix_table(STUTABLE)} stu
                        ON u.id = stu.userid
                     JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                        ON cls.id = stu.classid
                     JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs
                        ON cls.courseid = crs.id
                     WHERE cls.id = {$this->gas_gauge_page_value}";

        //take the inactive setting into account
        $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters());
        if(!empty($sql_filter)) {
            $base_sql .= " AND ({$sql_filter})";
        }

        //number of completed users
        $completed_sql = "{$base_sql}
                          AND stu.completestatusid = " . STUSTATUS_PASSED;

        $completed_field = $this->get_field_sql($completed_sql);

        //total number of users
        $total_sql = "{$base_sql}";

        $total_field = $this->get_field_sql($total_sql);

        //avoid dividing by zero
        if (empty($total_field)) {
            return 0;
        }

        //return the percentage of distinct users who are completed
        return $completed_field / $total_field * 100;
    }

    /**
     * Calculates the maximum value of the gas gauge
     *
     * @param   mixed  $key  The unique key that represents the current page you are on,
     *                       as specified by get_page_value
     *
     * @return  int          The maximum value of the gas gauge
     */
    function get_gas_gauge_max_value($key) {
        global $CURMAN;

        $base_sql = "SELECT *
                     FROM
                     {$CURMAN->db->prefix_table(USRTABLE)} u
                     JOIN {$CURMAN->db->prefix_table(STUTABLE)} stu
                       ON u.id = stu.userid
                     JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                       ON cls.id = stu.classid
                     JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs
                       ON cls.courseid = crs.id
                     WHERE cls.id = {$this->gas_gauge_page_value}";

        //take the inactive setting into account
        $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters());
        if(!empty($sql_filter)) {
            $base_sql .= " AND ({$sql_filter})";
        }

        if (record_exists_sql($base_sql)) {
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
     * @return  string array  The values to display at the top of the report
     */
    function get_gas_gauge_header_info() {
        $class_name = '';
        if ($class_record = get_record(CLSTABLE, 'id', $this->gas_gauge_page_value)) {
            $class_name = $class_record->idnumber;
        }

        //class description, including class name
        $class_description = get_string('class_description', $this->lang_file, $class_name);
        //general progress status message

        if ($this->gas_gauge_value === NULL) {
            $display_value = get_string('na', $this->lang_file);
        } else {
            $display_value = number_format($this->gas_gauge_value, 1);
        }

        $class_progress = get_string('class_progress', $this->lang_file, $display_value);

        return array($class_description,
                     $class_progress);
    }

    /**
     * Specifies the string used to label the gas-gauge-level pages
     *
     * @return  string  A string to display, or the empty string to use the default label
     */
    function get_gas_gauge_page_label() {
        //override the default label with a class-related label
        return get_string('class_paging_label', $this->lang_file);
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
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
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
