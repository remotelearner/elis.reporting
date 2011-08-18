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

require_once($CFG->dirroot . '/blocks/php_report/type/table_report.class.php');

class course_progress_summary_report extends table_report {
    var $custom_joins = array();
    /**
     * Date filter start and end dates
     * populated using: get_datefilter_values()
     */
    var $startdate = 0;
    var $enddate = 0;
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

        //needed for constants that define db tables
        require_once($CFG->dirroot.'/curriculum/config.php');
        require_once(CURMAN_DIRLOCATION . '/lib/usercluster.class.php');
        require_once(CURMAN_DIRLOCATION . '/lib/user.class.php');
        require_once(CURMAN_DIRLOCATION . '/lib/student.class.php');
        require_once(CURMAN_DIRLOCATION . '/lib/cmclass.class.php');


        //needed to get the filtering libraries
        require_once(CURMAN_DIRLOCATION . '/lib/filtering/lib.php');
        require_once(CURMAN_DIRLOCATION . '/lib/filtering/custom_field_multiselect.php');
        require_once(CURMAN_DIRLOCATION . '/lib/filtering/custom_field_multiselect_data.php');
        require_once(CURMAN_DIRLOCATION . '/lib/filtering/custom_field_multiselect_values.php');

        //needed for the permissions-checking logic on custom fields
        require_once($CFG->dirroot.'/blocks/php_report/sharedlib.php');
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
        global $CFG, $CURMAN, $SESSION;

        $cms = array();
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        $cms_objects = curriculum_get_listing('name', 'ASC', 0, 0, '', '', $contexts);
        if (!empty($cms_objects)) {
            foreach ($cms_objects as $curriculum) {
                $cms[$curriculum->id] = $curriculum->name;
            }
        }

        $curricula_options = array('choices' => $cms,
                                 'numeric' => false);

        // Needs to pull from saved prefs
        $user_preferences = php_report_filtering_get_user_preferences($this->get_report_shortname());
        $report_index = 'php_report_'.$this->get_report_shortname().'/field'.$this->id;

        if (isset($user_preferences[$report_index])) {
            $default_fieldid_list = unserialize(base64_decode($user_preferences[$report_index]));
        } else {
            $default_fieldid_list = array();
        }
        $field_list = array('fieldids' => $default_fieldid_list);

        // Add block id to field list array
        $field_list['block_instance'] = $this->id;
        $field_list['reportname'] = $this->get_report_shortname();
        // Need help text
        $field_list['help'] = array('course_progress_summary_help',
                                    get_string('filter_custom_fields', 'rlreport_course_progress_summary'),
                                    'block_php_report');


        $filter_entries = array();
        $this->curricula = new generalized_filter_entry('curr', 'curcrs', 'curriculumid', get_string('filter_curricula', 'rlreport_course_progress_summary'), false, 'selectany', $curricula_options);
        $filter_entries[] = $this->curricula;

        $filter_entries[] = new generalized_filter_entry('cluster', 'enrol', 'userid', get_string('filter_cluster', 'rlreport_course_progress_summary'), false, 'clusterselect', array('default' => null));
        $this->multiselect_filter = new generalized_filter_entry('field'.$this->id, 'field'.$this->id, 'id', get_string('filter_field', 'rlreport_course_progress_summary'), false, 'custom_field_multiselect_values', $field_list);
        $filter_entries[] = $this->multiselect_filter;
        $filter_entries[] = new generalized_filter_entry('enrol', 'enrol', 'enrolmenttime', get_string('filter_course_date', 'rlreport_course_progress_summary'), false, 'date');
        $filter_entries[] = new generalized_filter_entry('enrol', 'enrol', 'enrolmenttime', get_string('filter_course_date', 'rlreport_course_progress_summary'), false, 'date');


        return $filter_entries;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various fields involving user info, clusters, class enrolment, and module information)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        global $CURMAN, $SESSION;

        //add custom fields here, first the Course name, then custom fields, then progress and % students passing
        $columns = array();
        $columns[] = new table_report_column('crs.name', get_string('column_course', 'rlreport_course_progress_summary'), 'course', 'left', true);

        $filter_params = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'field'.$this->get_report_shortname(),$this->filter);

        // Unserialize value of filter params to get field ids array
        $filter_params = unserialize(base64_decode($filter_params[0]['value']));

        // Loop through these additional parameters - new columns, will  have to eventually pass the table etc...
        if (isset($filter_params) && is_array($filter_params)) {
            // Working with custom course fields - get all course fields
            $context = context_level_base::get_custom_context_level('course', 'block_curr_admin');
            $fields = field::get_for_context_level($context);

            foreach ($filter_params as $custom_course_id) {
                $custom_course_field = new field($custom_course_id);
                //Find matching course field
                $course_field_title = $fields[$custom_course_id]->name;

                //Now, create a join statement for each custom course field and add it to the sql query
                $data_table = $CURMAN->db->prefix_table($custom_course_field->data_table());

                //field used to identify course id in custom field subquery
                $course_id_field = "ctxt_instanceid_{$custom_course_id}";

                //make sure the user can view fields for the current course
                $view_field_capability = block_php_report_field_capability($custom_course_field->owners);
                $view_field_contexts = get_contexts_by_capability_for_user('course', $view_field_capability, $this->userid);
                $view_field_filter = $view_field_contexts->sql_filter_for_context_level('ctxt.instanceid', 'course');

                // Create a custom join to be used later for the completed sql query
                $this->custom_joins[] = " LEFT JOIN (SELECT d.data as custom_data_{$custom_course_id}, ctxt.instanceid as ctxt_instanceid_{$custom_course_id}
                FROM {$CURMAN->db->prefix_table('context')} ctxt
                      JOIN {$data_table} d ON d.contextid = ctxt.id
                      AND d.fieldid = {$custom_course_id}
                      WHERE
                      ctxt.contextlevel = {$context}
                      AND {$view_field_filter}) custom_{$custom_course_id}
                      ON cls.courseid = custom_{$custom_course_id}.{$course_id_field}";

                $columns[] = new table_report_column('custom_'.$custom_course_id.'.custom_data_'.$custom_course_id,
                                                     $fields[$custom_course_id]->name,
                                                     'custom_course_field',
                                                     'left');
            }
        }

        //add progress bar and students passing
        $columns[] = new table_report_horizontal_bar_column('COUNT(DISTINCT clsgr.id) AS stucompletedprogress',
                                                             get_string('bar_column_progress',
                                                            'rlreport_course_progress_summary'),
                                                            'progress_bar',
                                                            'COUNT(DISTINCT '.sql_concat('comp.id', "'_'", 'enrol.id').') AS numprogress',
                                                            'center',
                                                            '$e');
        $columns[] = new table_report_column('SUM(CASE WHEN enrol.completestatusid=2 THEN 1 ELSE 0 END) AS studentspassing',
                                              get_string('column_percent_passing', 'rlreport_course_progress_summary'),
                                             'percent_passing',
                                             'left');

        return $columns;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  String that represents the sort field
     */
    function get_default_sort_field() {
        return 'crs.name';
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
        return 'crs.id';
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignment info, class enrolments,
     * and resource info
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  string            The report's main sql statement
     */
    function get_report_sql($columns) {
        global $CFG, $CURMAN;

        require_once(CURMAN_DIRLOCATION . '/lib/student.class.php');
        $incomplete_status = STUSTATUS_NOTCOMPLETE;
        $passed_status = STUSTATUS_PASSED;

        // Check for any permissions code to be added
        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);

        //make sure we only include curricula within those contexts
        $permissions_filter = $contexts->sql_filter_for_context_level('crs.id', 'course');

        // Retrieve curriculum filter for sub query
        $curr_filter_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'curr',$this->filter);
        $curr_filter = '';

        // TODO: add 'Courses not in a curriculum' to curricula drop-down
        // And that will change the queries (WHERE NOT EXISTS) ... hmmmm...

        // No filtering returns a value of '0'
        if ($curr_filter_array) {
            if ($curr_filter_array[0]['value'] !== '0') {
                $curr_filter = " AND curcrs2.curriculumid = ".$curr_filter_array[0]['value'];
            } else {
                $curr_filter = " AND curcrs2.curriculumid IS NOT NULL";
            }
        }

        //main query
        $sql = "SELECT {$columns},
                       COUNT(enrol.id) AS numstudents,
                       crs.id AS courseid
                FROM {$CURMAN->db->prefix_table(CRSTABLE)} crs
                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                  ON cls.courseid = crs.id
                JOIN {$CURMAN->db->prefix_table(STUTABLE)} enrol
                  ON enrol.classid = cls.id
                JOIN {$CURMAN->db->prefix_TABLE(USRTABLE)} crlmu
                  ON enrol.userid = crlmu.id
                LEFT JOIN {$CURMAN->db->prefix_table(CRSCOMPTABLE)} comp
                  ON comp.courseid = crs.id
                LEFT JOIN {$CURMAN->db->prefix_table(CLSGRTABLE)} clsgr
                  ON clsgr.classid = cls.id
                 AND clsgr.userid = enrol.userid
                 AND clsgr.locked = 1
                 AND clsgr.grade >= comp.completion_grade
                 AND clsgr.completionid = comp.id
                      ";

        // Add any custom joins for custom fields at this point
        if (isset($this->custom_joins) && is_array($this->custom_joins)) {
            foreach ($this->custom_joins as $custom_join) {
                $sql .= $custom_join;
            }
        }

        // Add where sub-query and curricula filter
         //                   JOIN {$CURMAN->db->prefix_table(CURTABLE)} curr
         //                      ON currcrs.curricululmid = curr.id
        $where = array();
        if ($curr_filter != '') {
            $sql .= " JOIN {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs
                        ON curcrs.courseid = crs.id";

            $where[] = "EXISTS (
                             SELECT *
                             FROM {$CURMAN->db->prefix_table(STUTABLE)} enrol2
                                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls2
                                  ON cls2.id = enrol2.classid
                                JOIN {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs2
                                  ON curcrs2.courseid = cls2.courseid
                                JOIN {$CURMAN->db->prefix_table(CURASSTABLE)} curass
                                  ON curcrs2.curriculumid = curass.curriculumid
                                 AND curass.userid = enrol2.userid
                                WHERE enrol.id = enrol2.id
                                {$curr_filter})";
        }
        if ($permissions_filter != '') {
            $where[] = $permissions_filter;
        }
        if (empty($CURMAN->config->legacy_show_inactive_users)) {
            $where[] = 'crlmu.inactive = 0';
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return $sql;
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


        $record->associatedcluster = empty($record->associatedcluster) ? get_string('no', 'rlreport_course_progress_summary') :
                                                                         get_string('yes', 'rlreport_course_progress_summary');

        //make sure this is set to something so that the horizontal bar graph doesn't disappear
        if(empty($record->stucompletedprogress)) {
            $record->stucompletedprogress = 0;
        }

        if(isset($record->studentspassing)&& isset($record->numstudents) && $record->numstudents>0) {
            $record->studentspassing = round(($record->studentspassing/$record->numstudents)*100).'%';
        }

        $a = new stdClass;
        if(isset($record->stucompletedprogress)) {
            $a->value = $record->stucompletedprogress;
            $a->total = $record->numprogress;
        } else {
            $a->value = 0;
            $a->total = 0;
        }


        // Check for unset fields for N/A display of progress or students passing
        if(!isset($record->studentspassing)) {
            $record->studentspassing = get_string('na','rlreport_course_progress_summary');
        }

        return $record;
    }

    /**
     * Retrieves start and end settings from active filter (if exists)
     * and populates class properties: startdate and enddate
     *
     * @uses none
     * @param none
     * @return none
     */
    function get_datefilter_values() {
        $start_enabled =  php_report_filtering_get_active_filter_values(
                             $this->get_report_shortname(),
                             'enrol' . '_sck',$this->filter);
        $start = (!empty($start_enabled) && is_array($start_enabled)
                  && !empty($start_enabled[0]['value']))
                 ? php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(),
                       'enrol' . '_sdt',$this->filter)
                 : 0;

        $end_enabled = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           'enrol' . '_eck',$this->filter);
        $end = (!empty($end_enabled) && is_array($end_enabled)
                && !empty($end_enabled[0]['value']))
               ? php_report_filtering_get_active_filter_values(
                     $this->get_report_shortname(),
                     'enrol' . '_edt',$this->filter)
               : 0;


        $this->startdate = (!empty($start) && is_array($start))
                           ? $start[0]['value'] : 0;
        $this->enddate = (!empty($end) && is_array($end))
                           ? $end[0]['value'] : 0;
    }

    /**
     * Specifies header summary data
     * representing curricula, date range, cluster and number of courses in report
     *
     * @return  array  A mapping of display names to values
     */
    function get_header_entries() {
        //need to get start_date and end_date from report interface
        $cluster_display = '';
        $curricula_display = '';
        $course_count = $this->numrecs;

        if ($selected_cluster = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'cluster',$this->filter)) {
            $count = 0;
            // Check for NOT - cluster_op == 2
            $cluster_op = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'cluster_op',$this->filter);
 			if ($cluster_op['0']['value'] == '2') {
                $cluster_display .= get_string('header_not', 'rlreport_course_progress_summary');
            }

            foreach ($selected_cluster as $cluster) {
                if($cluster_new = get_record(CLSTTABLE, 'id', $cluster['value'])) {
                    if ($count > 0) {
                        $cluster_display .= ' AND ';
                    }
                    $count++;
                    if (!empty($cluster_new->display)) {
                        $cluster_display .= $cluster_new->display;
                    } else {
                        $cluster_display .= $cluster_new->name;
                    }

                }
            }
        } else {
            $cluster_display = get_string('header_all','rlreport_course_progress_summary');
        }

        if ($selected_curricula = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'curr',$this->filter)) {

            $count = 0;
            foreach ($selected_curricula as $curricula) {
                if ($curricula['value'] == '0') {
                    $curricula_display = get_string('header_all_assigned','rlreport_course_progress_summary');
                } else if($curricula = get_record('crlm_curriculum', 'id', $curricula['value'])) {
                    if ($count > 0) {
                        $curricula_display .= ' AND ';
                    }
                    $count++;
                    $curricula_display .= $curricula->name;
                }
            }
        } else {
            $curricula_display = get_string('header_all','rlreport_course_progress_summary');
        }

        // Get date filter parameters req'd for header title
        $this->get_datefilter_values();
        $sdate = !empty($this->startdate)
                 ? $this->userdate($this->startdate, get_string('date_format', 'rlreport_course_progress_summary'))
                 : get_string('present', 'rlreport_course_progress_summary');
        $edate = !empty($this->enddate)
                 ? $this->userdate($this->enddate, get_string('date_format', 'rlreport_course_progress_summary'))
                 : get_string('present', 'rlreport_course_progress_summary');

        if (empty($this->startdate) && empty($this->enddate)) {
            $date_range_display = get_string('header_all', 'rlreport_course_progress_summary');
        } else {
            $date_range_display = "{$sdate} - {$edate}";
        }

        return array(new php_report_header_entry(get_string('header_curricula', 'rlreport_course_progress_summary'), $curricula_display,  'curricula'),
                     new php_report_header_entry(get_string('header_date_range', 'rlreport_course_progress_summary'), $date_range_display, 'date'),
                     new php_report_header_entry(get_string('header_organization', 'rlreport_course_progress_summary'), $cluster_display , 'organization'),
                     new php_report_header_entry(get_string('header_course_count', 'rlreport_course_progress_summary'), $course_count, 'course_count'));
    }

    /**
     * Determines whether the current user can view this report
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one curriculum context
        $contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);

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
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(array(182, 221, 232));
    }
}

?>
