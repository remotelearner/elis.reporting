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

require_once($CFG->dirroot . '/blocks/php_report/type/table_report.class.php');

class class_roster_report extends table_report {
    var $lang_file = 'rlreport_class_roster';

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

        //needed for options filters
        require_once($CFG->dirroot . '/curriculum/lib/filtering/lib.php');
        require_once($CFG->dirroot . '/curriculum/lib/filtering/clusterselect.php');
        require_once($CFG->dirroot . '/curriculum/lib/filtering/clustertext.php');
        require_once($CFG->dirroot . '/curriculum/lib/filtering/simpleselect.php');

        //needed for constants that define db tables
        require_once($CFG->dirroot . '/curriculum/lib/cmclass.class.php');
        require_once($CFG->dirroot . '/curriculum/lib/user.class.php');
        require_once($CFG->dirroot . '/curriculum/lib/student.class.php');
        require_once($CFG->dirroot . '/curriculum/lib/instructor.class.php');
        require_once($CFG->dirroot . '/curriculum/usermanagementpage.class.php');
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
        global $CFG;

        $filter_array = array();

        // Fetch array of allowed classes
        $classes_array = array();
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        $cmclasses = cmclass_get_listing('crsname', 'ASC', 0, 0, '', '', 0, false, $contexts);
        foreach ($cmclasses as $cmclass) {
            $classes_array[$cmclass->id] = $cmclass->crsname . ' - ' . $cmclass->idnumber;
        }

        $filter_array[] = new generalized_filter_entry('classid', 'cls', 'id',
                                                       get_string('filter_course_class', $this->lang_file),
                                                       false, 'courseclassselect',
                                                       array('default'=>NULL,
                                                             'isrequired'=>true,
                                                             'report_path'=>$CFG->wwwroot.'/blocks/php_report/instances/class_roster/',
                                                             'help' => array('class_roster_courseclass_help',
                                                                             get_string('filter_course_class', $this->lang_file),
                                                                             'block_php_report')
                                                             )
                                                      );

        return $filter_array;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        return array(new table_report_column('usr.lastname AS r_student',
                                             get_string('column_student', $this->lang_file),
                                             'cssstudent', 'left', true),
                     new table_report_column('usr.email AS r_email',
                                             get_string('column_email', $this->lang_file),
                                             'cssemail', 'left', true)
                    );
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  A string representing sorting by user id
     */
    function get_default_sort_field() {
        return 'r_student';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  A string representing a descending sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    function get_header_entries() {
        global $CFG, $CURMAN;

        $header_array = array();

        // Add a course/class name if available
        $classid = 0;
        $cls_setting = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                     'classid',$this->filter);

        if (!empty($cls_setting[0]['value'])) {
            $classid = $cls_setting[0]['value'];
            $cmclass = new cmclass($classid);

            // Course name
            $header_obj = new stdClass;
            $header_obj->label = get_string('header_course',$this->lang_file).':';
            $header_obj->value = $cmclass->course->name;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            // Class name
            $header_obj = new stdClass;
            $header_obj->label = get_string('header_class',$this->lang_file).':';
            $header_obj->value = $cmclass->idnumber;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;
        }

        // If we are displaying a class, show date range and instructors
        if (!empty($classid)) {
            // Add dates if available
            if (!empty($cmclass)) {
                $startdate = $cmclass->startdate;
                $enddate = $cmclass->enddate;

                // Add start date if available
                if (!empty($startdate)) {
                    $header_obj = new stdClass;
                    $header_obj->label = get_string('header_start_date',$this->lang_file).':';
                    $header_obj->value = $this->userdate($startdate, get_string('strftimedaydate'));
                    $header_obj->css_identifier = '';
                    $header_array[] = $header_obj;
                }

                // Add end date if available
                if (!empty($enddate)) {
                    $header_obj = new stdClass;
                    $header_obj->label = get_string('header_end_date',$this->lang_file).':';
                    $header_obj->value = $this->userdate($enddate, get_string('strftimedaydate'));
                    $header_obj->css_identifier = '';
                    $header_array[] = $header_obj;
                }
            }

            // Add instructor names
            $instructor_records = instructor::get_instructors($classid);
            if (!empty($instructor_records)) {
                $instructors = '';
                foreach ($instructor_records as $record) {
                    $userpage = new usermanagementpage(array('id' => $record->id, 'action' => 'view'));
                    $instructors .= '<span class="external_report_link"><a href="' . $userpage->get_url()
                                  . '">' . fullname($record) . '</a></span><br />';
                }

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_instructors',$this->lang_file).':';
                $header_obj->value = ($instructors=='') ? 'Not Available' : $instructors;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;
            }
        }

        return $header_array;
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
        $user = new user;
        $user->firstname = $record->firstname;
        $user->lastname = $record->r_student;
        $fullname = fullname($user);

        $userpage = new usermanagementpage(array('id' => $record->cmuserid, 'action' => 'view'));
        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $record->r_student = '<span class="external_report_link"><a href="' . $userpage->get_url()
                               . '">' . $fullname . '</a></span>';
        } else {
            $record->r_student = $fullname;
        }

        return $record;
    }

    /**
     * Specifies an SQL statement that will produce the required report
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  string            The report's main sql statement
     */
    function get_report_sql($columns) {
        global $CURMAN;

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);

        //make sure we only count classes within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('cls.id', 'class');

        $sql = "SELECT {$columns}, usr.firstname AS firstname, usr.id AS cmuserid
                FROM {$CURMAN->db->prefix_table(STUTABLE)} clsenr
                JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr
                    ON usr.id=clsenr.userid
                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                    ON cls.id=clsenr.classid
           LEFT JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs
                    ON crs.id=cls.courseid
                    WHERE {$permissions_filter}
               ";

        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $sql .= ' AND usr.inactive = 0';
        }

        return $sql;
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     * and php_report:view capability
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //Check for report view capability
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one class context
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colours used in the background when
     * displaying report header entries
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_header_colour() {
        return array(242, 242, 242);
    }

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(217, 217, 217);
    }

    /**
     * Specifies the RGB components of one or more colours used in an
     * alternating fashion as report row backgrounds
     *
     * @return  array array  Array containing arrays of red, green, and blue components
     *                       (one array for each alternating colour)
     */
    function get_row_colours() {
        return array(array(242, 242, 242));
    }

    /**
     * Add custom requirement rules to filter elements
     *
     * @param   object $mform  The mform object for the filter page
     * @param   string $key    The filter field key
     * @param   object $fields The filter field values object
     *
     * @return  object $mform  The modified mform object for the filter page
     */
    function apply_filter_required_rule($mform, $key, $fields) {
        if ($mform->elementExists($key.'_grp')) {
            $mform->addRule($key.'_grp', get_string('required'), 'required', null, 'client');
            $mform->addGroupRule($key.'_grp', array($key=>array(array(get_string('required'), 'required', null, 'client'))));
        }

        return $mform;
    }
}

