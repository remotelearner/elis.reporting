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
 * @subpackage pm-blocks-phpreport-curricula
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/php_report/type/table_report.class.php');

class curricula_report extends table_report {

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_CURRICULUM;
    }

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
            //'courserole',
            //'coursecat',
            //'systemrole',
            'firstaccess',
            'lastaccess',
            'lastlogin',
            'timemodified',
            'auth'
        )
    );

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
        require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculum.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumstudent.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');

        //needed to include for filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofilematch.php');
        require_once($CFG->dirroot .'/elis/program/lib/filtering/clusterselect.php');
    }
/**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        // allow various export formats
        return array(php_report::$EXPORT_FORMAT_PDF,
                     php_report::$EXPORT_FORMAT_CSV);
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
        $cms_objects = curriculum_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        if (!empty($cms_objects)) {
            foreach ($cms_objects as $curriculum) {
                $cms[$curriculum->id] = $curriculum->name;
            }
        }

        // Create all requested User Profile field filters
        $upfilter =
            new generalized_filter_userprofilematch(
                'cu',
                get_string('filter_user_match', 'rlreport_curricula'),
                array(
                    'choices'     => $this->_fields,
                    'notadvanced' => array('fullname'),
                    'extra'       => true // include all extra profile fields
                )
            );

        $filters = $upfilter->get_filters();

        $filters = array_merge($filters,
                 array(
                     new generalized_filter_entry('curr', 'curass', 'curriculumid',
                         get_string('filter_program', 'rlreport_curricula'),
                         false, 'selectall', array('choices'  => $cms,
                                                   'multiple' => true)
                     ),
                     new generalized_filter_entry('cluster', 'crlmu', 'id',
                         get_string('filter_cluster', 'rlreport_curricula'),
                         false, 'clusterselect', array('default' => null)
                     )
                 )
             );

        return $filters;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        return array(new table_report_column('crlmu.idnumber AS idnumber',
                                              get_string('column_idnumber', 'rlreport_curricula'),
                                             'idnumber',
                                             'left',
                                              false),
                     new table_report_column("u.lastname AS lastname",
                             get_string('column_name', 'rlreport_curricula'),
                             'student', 'left', false, true, true,
                             array(php_report::$EXPORT_FORMAT_PDF, php_report::$EXPORT_FORMAT_HTML)),
                     new table_report_column("u.lastname AS userlastname",
                             get_string('column_lastname', 'rlreport_curricula'),
                             'student_lastname', 'left', false, true, true,
                             array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL)),
                     new table_report_column("u.firstname AS firstname",
                             get_string('column_firstname', 'rlreport_curricula'),
                             'student_firstname', 'left', false, true, true,
                             array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL)),
                     new table_report_column('cc.name AS curname',
                                              get_string('column_curriculum_name', 'rlreport_curricula'),
                                             'curriculum_name',
                                             'left',
                                              false),
                     new table_report_column('cc.reqcredits AS reqcredits',
                                              get_string('column_credits_required', 'rlreport_curricula'),
                                             'credits_required',
                                             'left',
                                              false),
                     new table_report_column('SUM(cce.credits) AS numcredits',
                                              get_string('column_credits_completed', 'rlreport_curricula'),
                                             'credits_completed',
                                             'left',
                                              false),
                     new table_report_column('crlmu.transfercredits AS transfercredits',
                                              get_string('column_transfer_credits', 'rlreport_curricula'),
                                             'transfer_credits',
                                             'left',
                                              false),
                     new table_report_column('curass.timecompleted AS completiondate',
                                              get_string('column_completed', 'rlreport_curricula'),
                                             'completed',
                                             'left',
                                              false),
                     new table_report_column('curass.timeexpired AS timeexpires',
                                              get_string('column_expires', 'rlreport_curricula'),
                                             'expires',
                                             'left',
                                              false),
                     );
    }

    /**
     * Specifies string of sort columns and direction to
     * order by if no other sorting is taking place (either because
     * manual sorting is disallowed or is not currently being used)
     *
     * @uses    $DB
     * @return  string  String specifying columns, and directions if necessary
     */
    function get_static_sort_columns() {
        global $DB;
        return $DB->sql_concat('u.lastname',"' '",'u.firstname', "' '", 'u.idnumber');
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  A string representing sorting by user id
     */
    function get_default_sort_field() {
        // No column sorting at this time
        //return 'sortname';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  A string representing a descending sort order
     */
    function get_default_sort_order() {
        // No column sorting at this time
        //return 'DESC';
    }

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @uses    $DB
     * @return  array List of objects containing grouping id, field names, display labels and sort order
     */
     function get_grouping_fields() {
         global $DB;
         return array(new table_report_grouping('user_id',
                                                 $DB->sql_concat('u.lastname',"' '",'u.firstname', "' '", 'u.idnumber'),
                                                 get_string('grouping_idnumber', 'rlreport_curricula').': ',
                                                'ASC',
                                                 array("crlmu.idnumber AS idnumber","u.lastname AS lastname"),
                                                'below')
                     );
     }

     /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return 'idnumber,
                cc.id';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  String that represents a descending sort order
     */
    function get_grouping_sort_order() {
        return 'DESC';
    }

    /**
     * Specifies an SQL statement that will retrieve users and curricula information
     * such as credits, completion and expiration information
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @uses    $DB
     * @return  array   The report's main sql statement with optional params
     */
    function get_report_sql($columns) {
        global $DB;

        //obtain all user contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);

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

        $firstname = 'u.firstname AS firstname';
        if (stripos($columns, $firstname) === FALSE) {
            $columns .= ", {$firstname}";
        }
        $report_sql = "SELECT {$columns}, crlmu.id as userid, curass.completed AS completed,
        ". $DB->sql_concat('crlmu.lastname',"' '","COALESCE(crlmu.mi, '')","' '",'crlmu.firstname') .' as sortname
                FROM {'. curriculumstudent::TABLE .'} curass
                       JOIN {'. user::TABLE .'} crlmu
                         ON curass.userid = crlmu.id
                       JOIN {'. curriculum::TABLE .'} cc
                         ON curass.curriculumid = cc.id
                  LEFT JOIN {'. curriculumcourse::TABLE .'} ccc
                         ON ccc.curriculumid = cc.id
                  LEFT JOIN {'. pmclass::TABLE .'} ccl
                         ON ccl.courseid = ccc.courseid
                  LEFT JOIN {'. student::TABLE .'} cce
                         ON (cce.classid = ccl.id) AND (cce.userid = curass.userid)
                       JOIN {user} u
                         ON u.idnumber = crlmu.idnumber
                      ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'crlmu.inactive = 0';
        }
        if (!empty($where)) {
            $report_sql .= 'WHERE '. implode(' AND ', $where);
        }

        return array($report_sql, $params);
    }

    /**
     * Takes a record and transforms it into an appropriate format
     * This method is set up as a hook to be implemented by actual report class
     *
     * @param   object    The data record
     * @param   string    The report type
     * @return  stdClass  The reformatted record
     */
    function transform_record($record, $export_format) {
        global $CFG;

        $new_record = clone($record);

        /**
         * Correct formatting for certain fields
         **/
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        $incomplete_status = STUSTATUS_NOTCOMPLETE;

        if ($record->completed == $incomplete_status) {
            $new_record->completiondate = get_string('not_completed', 'rlreport_curricula');
        } else {
            $new_record->completiondate = $this->userdate($new_record->completiondate, get_string('date_format', 'rlreport_curricula'));
        }

        if ($record->numcredits == NULL) {
            $new_record->numcredits = get_string('na', 'rlreport_curricula');
        }

        if ($record->timeexpires == '0') {
            $new_record->timeexpires = get_string('na', 'rlreport_curricula');
        } else {
            $new_record->timeexpires = $this->userdate($new_record->timeexpires,
                                           get_string('date_format', 'rlreport_curricula'));
        }


        // Currently CSV does not do grouping headings, so convert fullname here

        //if ($export_format == php_report::$EXPORT_FORMAT_CSV) {
        //    $fullname = fullname($new_record);
        //    $new_record->lastname = $fullname;
        //}

        return $new_record;
    }

    /**
     * Transforms a column-based header entry into the form required by the report
     *
     * @param   stdClass  $element        The record representing the current grouping row
     *                                    (including only fields that are part of that grouping row)
     * @param   stdClass  $datum          The record representing the current report row
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The current grouping row, in its final state
     */
    function transform_grouping_header_record($element, $datum, $export_format) {
        global $CFG;

        /**
         * Correct formatting for certain fields
         **/
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');

        /**
         * Set up link to individual user report
         **/
        $single_student_report_url = "{$CFG->wwwroot}/blocks/php_report/render_report_page.php?report=individual_user&userid={$datum->userid}";

        // Use the datum object to get first and last name for fullname
        $fullname = fullname($datum);

        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $element->lastname = "<span class=\"external_report_link\">
                             <a href=\"{$single_student_report_url}\">{$fullname}</a>
                             </span>";
        } else if ($export_format != php_report::$EXPORT_FORMAT_CSV &&
                   $export_format != php_report::$EXPORT_FORMAT_EXCEL) {
            $element->lastname = $fullname;
        }

        return $element;
    }

    /**
     * Determines whether the current user can view this report
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one course context
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colour used in the background when
     * displaying the report display name
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_display_name_colour() {
        return array(184, 201, 228);
    }

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
        return array(array(255, 255, 255),
                     array(217, 217, 217));
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
        return array(array(180, 187, 238));
    }
}

