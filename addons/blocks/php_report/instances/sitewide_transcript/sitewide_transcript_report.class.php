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
 * @subpackage pm-blocks-phpreport-sitewide_transcript
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');

define('STR_DATE_FORMAT', get_string('date_format', 'rlreport_sitewide_transcript'));

class sitewide_transcript_report extends table_report {

    /**
     * Constants for unique filter ids
     */
    const filterid = 'upm';

    /**
     * Language file
     */
    var $langfile = 'rlreport_sitewide_transcript';

    /**
     * Required user profile fields (keys)
     * Note: can override default labels with values (leave empty for default)
     * Eg. 'lastname' =>  'Surname', ...
     */
    var $_fields = array(
        'up' => array(
            'fullname',
            'lastname',
            'firstname',
            'idnumber',
            'email',
            'city',
            'country',
            'username',
            'lang',
            'confirmed',
            //'crsrole',
            //'crscat',
            //'sysrole',
            'firstaccess',
            'lastaccess',
            'lastlogin',
            'timemodified',
            'auth'
        )
    );

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
     *
     * @uses $CFG
     * @uses $DB
     * @param none
     * @return  boolean  true if the report is available, otherwise false
     */
    function is_available() {
        global $CFG, $DB;

        // TBD: we need the /elis/program/ directories
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
     *
     * @uses $CFG
     * @param none
     * @return none
     */
    function require_dependencies() {
        global $CFG;

        require_once($CFG->dirroot .'/elis/program/lib/setup.php');

        //needed for options filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofilematch.php');

        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');

        require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php');
    }

    /**
     * Display name of report
     *
     * @uses none
     * @param none
     * @return string - the display name of the report
     */
    function get_display_name() {
        return get_string('displayname', $this->langfile);
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
        return $CFG->wwwroot .'/blocks/php_report/pix/sitewide_transcript_report_logo.jpg';
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
     * Specifies the report title
     *
     * @uses none
     * @param   $export_format  The desired export format for the headers
     * @return array - header entires
     */
    function get_header_entries($export_format) {
        $header_obj = new stdClass;
        $header_obj->label = get_string('report_heading', $this->langfile);
        $header_obj->value = ''; // TBD
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

        // Create all requested User Profile field filters
        $upfilter =
            new generalized_filter_userprofilematch(
                sitewide_transcript_report::filterid,
                get_string('filter_user_match', $this->langfile),
                array(
                    'choices'     => $this->_fields,
                    'notadvanced' => array('fullname'),
                    //'langfile'  => 'filters',
                    'extra'       => true // include all extra profile fields
                )
            );
        return $upfilter->get_filters();
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
        $record->user_id = fullname($record);
        // start-end dates
        $sdate = cm_timestamp_to_date($record->startdate, STR_DATE_FORMAT);
        $edate = !empty($record->enddate)
                 ? cm_timestamp_to_date($record->enddate, STR_DATE_FORMAT)
                 : get_string('present', $this->langfile);
        $record->startdate = (empty($record->startdate) && empty($record->enddate))
                             ? get_string('na', $this->langfile)
                             : "{$sdate} - {$edate}";
        // completed date
        $record->completed = ($record->status != STUSTATUS_NOTCOMPLETE && !empty($record->completed))
                             ? cm_timestamp_to_date($record->completed, STR_DATE_FORMAT)
                             : get_string('incomplete', $this->langfile);
        // status mapping
        $statusmap = array(
                         STUSTATUS_NOTCOMPLETE => 'na', // 'incomplete' ?
                         STUSTATUS_FAILED      => 'status_failed',
                         STUSTATUS_PASSED      => 'status_passed'
                     );
        $record->status = get_string($statusmap[$record->status],
                                     $this->langfile);

        if (!empty($record->grade)) {
            $record->grade = pm_display_grade($record->grade);
            if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
                $record->grade .= get_string('percent_symbol', $this->langfile);
            }
        } else {
            $record->grade = get_string('na', $this->langfile);
        }

        return $record;
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignments
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement, as well as the
     *                  applicable SQL parameters
     */
    function get_report_sql($columns) {
        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        // TBV: $contexts = pm_context_set::for_user_with_capability(???)

        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('crlmu.id', 'user');
        $filter_obj = $contexts->get_filter('id', 'user');
        $filter_sql = $filter_obj->get_sql(false, 'crlmu', SQL_PARAMS_NAMED);
        $where = array();
        $params = array();
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = $filter_sql['where_parameters'];
        }
        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'crlmu.inactive = 0';
        }

        // Also require Moodle-Only query w/o CM tables!
        $sql = "SELECT DISTINCT {$columns}, u.firstname as firstname, u.lastname as lastname, crlmclass.enddate AS enddate
           FROM {user} u
           JOIN {crlm_user} crlmu ON u.idnumber = crlmu.idnumber
           JOIN {crlm_class_enrolment} clsenr ON crlmu.id = clsenr.userid
           JOIN {crlm_class} crlmclass ON crlmclass.id = clsenr.classid
           JOIN {crlm_course} crs ON crlmclass.courseid = crs.id
          "
      //." LEFT JOIN {crlm_class_graded} ccg ON crlmu.id = ccg.userid"
        ;

        if (!empty($where)) {
            $sql .= 'WHERE '. implode(' AND ', $where);
        }
        //error_log("sitewide_transcript_report.php::get_report_sql($columns); sql={$sql}");

        return array($sql, $params);
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
            new table_report_column('crs.name AS coursename', get_string('column_coursename', $this->langfile), 'studentname', 'left', true),
            new table_report_column('crlmclass.idnumber AS classid', get_string('column_classid', $this->langfile), 'idnumber', 'left', true),
            new table_report_column('crlmclass.startdate AS startdate', get_string('column_dates_offered', $this->langfile), 'dates_offered', 'left', true),
            new table_report_column('clsenr.grade AS grade', get_string('column_grade', $this->langfile), 'grade', 'left', true),
            new table_report_column('clsenr.completestatusid AS status', get_string('column_status', $this->langfile), 'status', 'left', true),
            new table_report_column('clsenr.credits AS credits', get_string('column_credits', $this->langfile), 'credits', 'left', true),
            new table_report_column('clsenr.completetime AS completed', get_string('column_completed', $this->langfile), 'completed', 'left', true)
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
                    new table_report_grouping('user_id','u.id',
                        get_string('grouping_studentname', $this->langfile),
                        'ASC', array(), 'above', 'u.lastname ASC'),
                    new table_report_grouping('user_idnumber','u.idnumber',
                        get_string('grouping_idnumber', $this->langfile), 'ASC')
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
     * Specifies the RGB components of one or more colours used in an
     * alternating fashion as report row backgrounds
     *
     * @return  array array  Array containing arrays of red, green, and blue components
     *                       (one array for each alternating colour)
     */
    function get_row_colours() {
        return array(array(255, 255, 255));
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
        return array(array(217, 217, 217));
    }
}

