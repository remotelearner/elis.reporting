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
 * @subpackage pm-blocks-phpreport-individual_user
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');
require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php');

class individual_user_report extends table_report {
    var $lang_file    = 'rlreport_individual_user';
    var $nopermission = false;  // Set to TRUE if the user has no permission to view the request report

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_USER;
    }

    /**
     * Specifies whether the current report is available
     *
     * @uses    $CFG
     * @uses    $DB
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

        require_once($CFG->dirroot.'/elis/program/lib/setup.php');

        //needed for constants that define db tables
        require_once($CFG->dirroot.'/elis/program/lib/lib.php'); // usermanagement
        require_once($CFG->dirroot.'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/userset.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/curriculum.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/curriculumstudent.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot.'/elis/program/lib/data/pmclass.class.php');

        //needed to include for filters
        require_once($CFG->dirroot.'/elis/program/lib/filtering/autocomplete_eliswithcustomfields.php');
        require_once($CFG->dirroot.'/elis/core/lib/filtering/simpleselect.php');

    }

    function get_header_entries($export_format) {
        global $CFG;

        $header_array = array();

        if ($this->nopermission) {
            return $header_array;
        }

        $userid = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                'userid',$this->filter);

        if (!empty($userid[0]['value']) && is_numeric($userid[0]['value'])) {
            $user_obj = new user($userid[0]['value']);

            if (!empty($user_obj)) {
                $header_obj = new stdClass;
                $header_obj->label = get_string('header_user_id',$this->lang_file).':';
                $header_obj->value = $user_obj->idnumber;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_firstname',$this->lang_file).':';
                $header_obj->value = $user_obj->firstname;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_lastname',$this->lang_file).':';
                $header_obj->value = $user_obj->lastname;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_email',$this->lang_file).':';
                $header_obj->value = $user_obj->email;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_reg_date',$this->lang_file).':';
                $header_obj->value = $this->userdate($user_obj->timecreated);
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;
            }
        }

        return $header_array;
    }

    /**
     * Gets the chosen userid from the filter information
     *
     * @return int the user id
     */
    function get_chosen_userid() {
        $chosen_userid = '';

        $report_filters = php_report_filtering_get_user_preferences($this->get_report_shortname());
        if (!empty($report_filters) && is_array($report_filters)) {
            foreach ($report_filters as $filter => $val) {
                if ($filter === 'php_report_'.$this->get_report_shortname().'/'.'userid') {
                    $chosen_userid = $val;
                }
            }
        }

        if (!empty($chosen_userid) && is_numeric($chosen_userid)) {
            return $chosen_userid;
        } else {
            return false;
        }
    }

    /**
     * Gets the permissions restriction for the current user
     *
     * @param string $dbfield the field to restrict on
     * @param boolean $true_if_user If true, always allow the user to view their own info
     * @return string The SQL fragment to be used as a permissions restriction
     */
    public function get_user_permissions_filter($dbfield, $true_if_user = true) {
        global $USER;

        $cm_user_id   = cm_get_crlmuserid($USER->id);
        $filter_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(), 'userid',$this->filter);
        $filter_user_id = (isset($filter_array[0]['value'])) ? $filter_array[0]['value'] : 0;

        if ($filter_user_id == $cm_user_id && $this->execution_mode == php_report::EXECUTION_MODE_INTERACTIVE && $true_if_user === true) {
            // always allow the user to see their own report but not necessarily schedule it
            $permissions_filter = 'TRUE';
        } else {
            // obtain all course contexts where this user can view reports
            $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
            $permissions_filter = $contexts->get_filter($dbfield, 'user');
        }

        return $permissions_filter;
    }

    /**
     * Specifies available report filters
     * (empty by default but can be implemented by child class)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     * @uses $CFG
     * @uses $USER
     * @return  array                The list of available filters
     */
    function get_filters($init_data = true) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/elis/program/accesslib.php');

        $filters = array();

        $autocomplete_opts = array(
            'report' => $this->get_report_shortname(),
            'ui' => 'inline',
            'contextlevel' => CONTEXT_ELIS_USER,
            'instance_fields' => array(
                'idnumber' => get_string('filter_autocomplete_idnumber', $this->lang_file),
                'firstname' => get_string('filter_autocomplete_firstname', $this->lang_file),
                'lastname' => get_string('filter_autocomplete_lastname', $this->lang_file),
                'username' => get_string('filter_autocomplete_username', $this->lang_file)
            ),
            'custom_fields' => '*',
            'label_template' => '[[firstname]] [[lastname]]',
            'configurable' => true,
            'required' => true,
            'help' => array(
                'individual_user_report',
                get_string('displayname', $this->lang_file),
                'rlreport_individual_user'
            )
        );

        $permissions_filter = $this->get_user_permissions_filter('id', false);
        $autocomplete_opts['selection_enabled'] = (!isset($permissions_filter->select) || $permissions_filter->select != 'FALSE') ? true : false;
        $autocomplete_opts['restriction_sql'] = $permissions_filter;

        $last_user = $this->get_chosen_userid();
        $cm_user_id = empty($last_user) ? cm_get_crlmuserid($USER->id) : $last_user;
        if ($cm_user_id && ($cmuser = new user($cm_user_id))) {
            $cmuser->load();
            $autocomplete_opts['defaults'] = array(
                    'label' => fullname($cmuser->to_object()),
                    'id' => $cm_user_id
                );
        }

        $filters[] = new generalized_filter_entry(
                'userid', 'usr', 'id',
                get_string('fld_fullname','elis_core'),
                false,
                'autocomplete_eliswithcustomfields',
                $autocomplete_opts
        );

        return $filters;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various fields involving user info, clusters, class enrolment, and module information)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        $columns = array();

        $columns[] = new table_report_column('crscomp.idnumber AS element',
                                             get_string('column_completion_element', $this->lang_file),
                                             'csselement', 'left', true
                                            );
        $columns[] = new table_report_column('grd.grade AS score',
                                             get_string('column_score', $this->lang_file),
                                             'cssscore', 'left', true
                                            );
        return $columns;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  String that represents a field indicating whether a cluster assignment exists
     */
    function get_default_sort_field() {
        return 'crscomp.idnumber';
    }

    /**
     * Method that specifies a field to group the results by (header displayed when this field changes)
     *
     * @return  string  String that represents a descending sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return "";
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
                      new table_report_grouping('course_idnumber','crs.idnumber',
                                                get_string('grouping_course', $this->lang_file).': ',
                                                'ASC'),
                      new table_report_grouping('class_idnumber','cls.idnumber',
                                                get_string('grouping_class', $this->lang_file).': ',
                                                'ASC')
                     );
     }

    /**
     * Transforms a heading element displayed above the columns into a listing of such heading elements
     *
     * @param   string array           $grouping_current  Mapping of field names to current values in the grouping
     * @param   table_report_grouping  $grouping          Object containing all info about the current level of grouping
     *                                                    being handled
     * @param   stdClass               $datum             The most recent record encountered
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  string array                              Set of text entries to display
     */
     function transform_grouping_header_label($grouping_current, $grouping, $datum, $export_format) {

         if ($grouping->id == 'course_idnumber') {
             $result = array();
         } else if ($grouping->id == 'class_idnumber') {
             $date_completed = (empty($datum->date_completed))
                             ? '-'
                             : $this->userdate($datum->date_completed, get_string('strftimedaydate'));
             $status = $datum->status;
             if ($status == STUSTATUS_NOTCOMPLETE) {
                 $status = get_string('transform_incomplete',$this->lang_file);
                 // if user is not complete, ignore the credits and date
                 // completed fields
                 $datum->credits = 0;
                 $datum->date_completed = 0;
                 $date_completed = get_string('not_complete',$this->lang_file);
             } else {
                 $status = get_string('transform_complete',$this->lang_file);
             }
             $expires = (empty($datum->expires))
                      ? get_string('not_available',$this->lang_file)
                      : $this->userdate($datum->expires, get_string('strftimedaydate'));
             $result = array();
             $result[] = $this->add_grouping_header(
                                 get_string('grouping_course', $this->lang_file) . ': ',
                                 $datum->course_idnumber, $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_course_name', $this->lang_file) . ': ',
                                 $datum->course_name, $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_credits', $this->lang_file) . ': ',
                                 $datum->credits, $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_grade', $this->lang_file) . ': ',
                                 pm_display_grade($datum->grade), $export_format);
             $result[] = $this->add_grouping_header($grouping->label,
                                 $grouping_current[$grouping->field],
                                 $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_date_completed', $this->lang_file) . ': ',
                                 $date_completed, $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_status', $this->lang_file) . ': ',
                                 $status, $export_format);
             $result[] = $this->add_grouping_header(
                                 get_string('transform_expires', $this->lang_file) . ': ',
                                 $expires, $export_format);
         } else {
             $result = array($this->add_grouping_header($grouping->label,
                                        $grouping_current[$grouping->field],
                                        $export_format));
         }

         return $result;
     }

    /**
     *  Override parent method to indicate this report has group column summary
     */
    function requires_group_column_summary() {
        return true;
    }

    /**
     * Takes a summary row record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     * @return stdClass  The reformatted record
     */
    function transform_group_column_summary($lastrecord, $nextrecord, $export_format) {
        $last_curr = $lastrecord->curriculum_name;
        $next_curr = (!empty($nextrecord->curriculum_name)) ? $nextrecord->curriculum_name : '';

        if ($last_curr != $next_curr) {
            $acqcnt = (!empty($lastrecord->acqcnt)) ? $lastrecord->acqcnt : 0;
            $reqcnt = (!empty($lastrecord->reqcnt)) ? $lastrecord->reqcnt : 0;

            $lastrecord->element = get_string('footer_has_earned',
                                              $this->lang_file,
                                              $lastrecord->firstname . ' ' . $lastrecord->lastname) . ' ';
            $lastrecord->score = get_string('footer_credits_of',
                                            $this->lang_file,
                                            $acqcnt) . ' ';
            $lastrecord->score .= get_string('footer_credits_for',
                                            $this->lang_file,
                                            $reqcnt) . ': ';
            $lastrecord->curriculum_name = ($lastrecord->curriculum_name == '')
                                         ? get_string('na', $this->lang_file)
                                         : $lastrecord->curriculum_name;
            $lastrecord->score .= $lastrecord->curriculum_name;

            return $lastrecord;
        } else {
            return null;
        }
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignment info, class enrolments,
     * and resource info
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement with optional params
     */
    function get_report_sql($columns) {
        global $USER;

        $cm_user_id = cm_get_crlmuserid($USER->id);

        $filter_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(), 'userid', $this->filter);
        // ELIS-4699: so not == to invalid cm/pm userid
        $filter_user_id = (isset($filter_array[0]['value'])) ? $filter_array[0]['value'] : -1;

        $params = array('p_completestatus' => STUSTATUS_PASSED);
        $permissions_filter = 'TRUE ';

        // ELIS-3993 -- Do not display any results if no user ID was supplied by the filter
        if ($filter_user_id == -1) {
            $permissions_filter = ' FALSE';
        } else if ($filter_user_id != $cm_user_id || $this->execution_mode != php_report::EXECUTION_MODE_INTERACTIVE) {
            // obtain all course contexts where this user can view reports
            $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
            $filter_obj = $contexts->get_filter('id', 'user');
            $filter_sql = $filter_obj->get_sql(false, 'usr', SQL_PARAMS_NAMED);

            if (isset($filter_sql['where'])) {
                if ($filter_sql['where'] == 'FALSE') {
                    // This user does not have permission to view the requested data
                    $this->nopermission = true;
                    $permissions_filter = 'FALSE';
                } else {
                    $permissions_filter = $filter_sql['where'];
                    $params            += $filter_sql['where_parameters'];
                }
            }
        }

        // Figure out the number of completed credits for the curriculum
        $numcomplete_subquery = "SELECT sum(innerclsenr.credits)
                                 FROM {". student::TABLE ."} innerclsenr
                                 JOIN {". pmclass::TABLE ."} innercls ON innercls.id = innerclsenr.classid
                                 JOIN {". course::TABLE ."} innercrs ON innercls.courseid = innercrs.id
                                 JOIN {". curriculumcourse::TABLE ."} innercurcrs
                                     ON innercurcrs.courseid = innercrs.id
                                 WHERE innerclsenr.userid = usr.id
                                     AND innercurcrs.curriculumid = cur.id
                                     AND innerclsenr.completestatusid = :p_completestatus";

        // Main query
        $sql = "SELECT {$columns},
                    cur.id IS NULL AS isnull,
                    crs.name AS course_name,
                    clsenr.credits AS credits,
                    clsenr.grade AS grade,
                    clsenr.completetime AS date_completed,
                    clsenr.completestatusid AS status,
                    curass.timeexpired AS expires,
                    usr.firstname AS firstname,
                    usr.lastname AS lastname,
                    cur.reqcredits AS reqcnt,
                    ({$numcomplete_subquery}) AS acqcnt
                FROM {". course::TABLE ."} crs
                JOIN {". pmclass::TABLE ."} cls
                    ON cls.courseid=crs.id
                JOIN {". student::TABLE ."} clsenr
                    ON clsenr.classid=cls.id
                JOIN {". user::TABLE ."} usr
                    ON usr.id = clsenr.userid
           LEFT JOIN ({". curriculumstudent::TABLE ."} curass
                      JOIN {". curriculum::TABLE ."} cur
                          ON cur.id = curass.curriculumid
                      JOIN {". curriculumcourse::TABLE ."} curcrs
                          ON curcrs.curriculumid = cur.id)
                    ON curass.userid = usr.id
                    AND curcrs.courseid = crs.id
           LEFT JOIN {". coursecompletion::TABLE ."} crscomp
                    ON crscomp.courseid = crs.id
           LEFT JOIN {". GRDTABLE ."} grd
                    ON grd.classid = cls.id
                    AND grd.userid = usr.id
                    AND grd.completionid = crscomp.id
                    AND grd.locked = 1
                    WHERE {$permissions_filter}
               ";

        return array($sql, $params);
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
        static $init_column_width = 0;
        $record->curriculum_name = ($record->curriculum_name == '')
                                 ? get_string('na', $this->lang_file)
                                 : $record->curriculum_name;

        $record->element = (empty($record->element))
                         ? get_string('none', $this->lang_file)
                         : $record->element;

        $record->score = (empty($record->score))
                       ? get_string('na', $this->lang_file)
                       : pm_display_grade($record->score) .
                         (($export_format == php_report::$EXPORT_FORMAT_CSV)
                         ? '' :  get_string('percent_symbol', $this->lang_file));
        if (!$init_column_width && $export_format == php_report::$EXPORT_FORMAT_PDF) {
            // ELIS-2890: make column wide enough for column summary row width
            $init_column_width = 1;
            $record->score .= str_repeat("\xc2\xa0", 160);
        }
        return $record;
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

        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            $this->require_dependencies();

            //when scheduling, make sure the current user has the scheduling capability for SOME user
            $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
            return !$contexts->is_empty();
        }

        // Since user is logged in they should always be able to see their own courses/classes
        return true;
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
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(array(242, 242, 242),
                     array(242, 242, 242),
                     array(242, 242, 242));
    }

    /**
     * Specifies the RGB components of a colour to use as a background in one-per-group summary rows
     *
     * @return  int array  Array containing the red, gree, and blue components in that order
     */
    function get_grouping_summary_row_colour() {
        return array(242, 242, 242);
    }
}

