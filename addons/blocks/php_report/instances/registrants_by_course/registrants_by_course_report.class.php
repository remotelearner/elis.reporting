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

class registrants_by_course_report extends table_report {
    var $lang_file = 'rlreport_registrants_by_course';

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
        require_once($CFG->dirroot . '/curriculum/lib/student.class.php');
    }

    function get_header_entries() {
        $header_array = array();

        $show_after = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                    'showdr_sck',$this->filter);
        $date_range_after = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                          'showdr_sdt',$this->filter);
        $show_before = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                     'showdr_eck',$this->filter);
        $date_range_before = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                           'showdr_edt',$this->filter);

        // Add "after dates if available
        if (!empty($show_after[0]['value'])) {
            $after = (!empty($date_range_after[0]['value']))
                   ? $this->userdate($date_range_after[0]['value'], get_string('strftimedaydate'))
                   : get_string('anytime',$this->lang_file);
        } else {
            $after = get_string('anytime',$this->lang_file);
        }

        // Add "after dates if available
        if (!empty($show_before[0]['value'])) {
            $before = (!empty($date_range_before[0]['value']))
                    ? $this->userdate($date_range_before[0]['value'], get_string('strftimedaydate'))
                    : get_string('anytime',$this->lang_file);
        } else {
            $before = get_string('anytime',$this->lang_file);
        }

        // If both dates are the same, only show one
        if ($before == $after) {
            $before = '';
            $splitter = '';
        } else {
            $splitter = ' - ';
        }

        $header_obj = new stdClass;
        $header_obj->label = get_string('header_date_range',$this->lang_file).': ';
        $header_obj->value = $after . $splitter . $before;
        $header_obj->css_identifier = '';
        $header_array[] = $header_obj;

        return $header_array;
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
        $cms = array();
        $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        $cms_objects = curriculum_get_listing_recordset('name', 'ASC', 0, 0, '', '', $contexts);
        if (!empty($cms_objects)) {
            while ($curriculum = rs_fetch_next_record($cms_objects)) {
                $cms[$curriculum->id] = $curriculum->name;
            }
        }

        return array(new generalized_filter_entry('showc', 'cur', 'id',
                                                  get_string('filter_curriculum', $this->lang_file),
                                                  false, 'simpleselect',
                                                  array('choices' => $cms, 'numeric' => true,
                                                        'anyvalue' => get_string('allcurricula',$this->lang_file),
                                                        'help' => array('registrants_by_course_curriculum_help',
                                                                  get_string('filter_curriculum', $this->lang_file),
                                                                  'block_php_report')
                                                       )
                                                 ),
                     new generalized_filter_entry('showdr', 'clsenr', 'enrolmenttime',
                                                  get_string('filter_date_range', $this->lang_file),
                                                  false, 'date',
                                                  array('help' => array('registrants_by_course_date_help',
                                                                        get_string('filter_date_range', $this->lang_file),
                                                                        'block_php_report')
                                                       )
                                                 )
                    );
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        $columns = array(new table_report_column('usr.lastname AS r_student',
                                                 get_string('column_student', $this->lang_file),
                                                 'cssstudent', 'left', true),
                         new table_report_column('usr.idnumber AS r_idnumber',
                                                 get_string('column_id', $this->lang_file),
                                                 'cssidnumber', 'left', true),
                         new table_report_column('clsenr.enrolmenttime AS r_startdate',
                                                 get_string('column_start_date', $this->lang_file),
                                                 'cssstartdate', 'left', true)
                        );

        return $columns;
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

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @return  array List of objects containing grouping id, field names, display labels and sort order
     */
     function get_grouping_fields() {
         return array(new table_report_grouping('curriculum_name','cur.name',
                                                get_string('grouping_curriculum', $this->lang_file).': ',
                                                'ASC',array(),'above','isnull ASC,cur.name ASC'),
                      new table_report_grouping('course_name','crs.name',
                                                get_string('grouping_course', $this->lang_file).': ',
                                                'ASC'),
                      new table_report_grouping('class_name','cls.idnumber',
                                                get_string('grouping_class', $this->lang_file).': ',
                                                'ASC')
                     );
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

        $record->r_student = fullname($user);
        $record->curriculum_name = ($record->curriculum_name == '')
                                   ? get_string('na', $this->lang_file)
                                   : $record->curriculum_name;
        $record->r_startdate = ($record->r_startdate == 0)
                               ? get_string('na', $this->lang_file)
                               : $this->userdate($record->r_startdate, get_string('strftimedaydate'));

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
        global $CFG, $CURMAN;

        //obtain all curriculum contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        $permissions_filter = $contexts->sql_filter_for_context_level('cur.id', 'curriculum');

        // Main query
        $sql = "SELECT DISTINCT {$columns},
                    cur.id IS NULL AS isnull,
                    usr.firstname AS firstname
                FROM {$CURMAN->db->prefix_table(CRSTABLE)} crs
                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                    ON cls.courseid=crs.id
                JOIN {$CURMAN->db->prefix_table(STUTABLE)} clsenr
                    ON clsenr.classid=cls.id
                JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr
                    ON usr.id = clsenr.userid
           LEFT JOIN {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs
                    ON curcrs.courseid = crs.id
           LEFT JOIN {$CURMAN->db->prefix_table(CURTABLE)} cur
                    ON cur.id = curcrs.curriculumid
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

        //make sure the current user can view reports in at least one course context
        $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
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

