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

class resource_usage_report extends table_report {

    /**
     * Date filter start and end dates
     * populated using: get_datefilter_values()
     */
    var $startdate = 0;
    var $enddate = 0;

    var $can_view;
    var $show_avegrades;
    var $segmented_by;

    /**
     * Specifies whether the current report is available
     * (a.k.a. any the CM system is installed)
     *
     * @return  boolean  True if the report is available, otherwise false
     */
    function is_available() {
        return false;

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
        //require_once($CFG->dirroot . '/curriculum/lib/filtering/lib.php');
        //require_once($CFG->dirroot . '/curriculum/lib/filtering/clusterselect.php');
        //require_once($CFG->dirroot . '/curriculum/lib/filtering/clustertext.php');
        //require_once($CFG->dirroot . '/curriculum/lib/filtering/simpleselect.php');

        //needed for constants that define db tables
        //require_once($CFG->dirroot . '/curriculum/lib/user.class.php');
        //require_once($CFG->dirroot . '/curriculum/lib/student.class.php');
        //needed for constants that define db tables
        require_once(CURMAN_DIRLOCATION . '/lib/track.class.php');

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
        $segchoices = array();
        $filters = array();


        $this->can_view = array();

        //make sure the current user can view reports in at least one curriculum context
        $curr_contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        $course_contexts = get_contexts_by_capability_for_user('course', $this->access_capability, $this->userid);
        $cluster_contexts = get_contexts_by_capability_for_user('cluster', $this->access_capability, $this->userid);

        if (!$curr_contexts->is_empty()) {
            $this->can_view['curriculum'] = TRUE;
        } else {
            $this->can_view['curriculum'] = FALSE;
        }
        if (!$course_contexts->is_empty()) {
            $this->can_view['course'] = TRUE;
        } else {
            $this->can_view['course'] = FALSE;
        }
        if (!$cluster_contexts->is_empty()) {
            $this->can_view['cluster'] = TRUE;
        } else {
            $this->can_view['cluster'] = FALSE;
        }

        // Segment filter options according to capabilities
        if ($this->can_view['course']) {
            $segchoices['course'] =  get_string('course', 'rlreport_resource_usage');
            $segdef = 'course';
        }
        if ($this->can_view['curriculum']) {
            $segchoices['curriculum'] =  get_string('curriculum', 'rlreport_resource_usage');
            $segdef = 'curriculum';
        }
        if ($this->can_view['cluster']) {
            $segchoices['cluster'] =  get_string('cluster', 'rlreport_resource_usage');
            if ($segdef != 'curriculum' && $segdef != 'course') {
                $segdef = 'cluster';
            }
        }


        // Add filter for date range - start and end
        $filters[] = new generalized_filter_entry('daterange', 'cls', 'activitydate', get_string('filter_date_range', 'rlreport_resource_usage'), false, 'date');
        $filters[] = new generalized_filter_entry('daterange', 'cls', 'activitydate', get_string('filter_date_range', 'rlreport_resource_usage'), false, 'date');

        // Add filter for course/curriculum/cluster segmentation
        $filters[] = new generalized_filter_entry('segmentedby', 'status', 'segment',
                                                    get_string('filter_segmented_by', 'rlreport_resource_usage'),
                                                    false,
                                                    'radiobuttons',
                                                    array('choices' => $segchoices,
                                                          'checked' => $segdef,
                                                          'heading' => get_string('filter_segmented_by','rlreport_resource_usage'),
                                                          'footer' => '<br>')
                                                  );

        // Add filter for choice to show average grades
        //$filters[] = new generalized_filter_entry('showaveragegrades', '', '', get_string('filter_average_grades', 'rlreport_resource_usage'), false, 'yesno', array('numeric' => true));

        $filters[] = new generalized_filter_entry('showaveragegrades', '', '', get_string('filter_average_grades', 'rlreport_resource_usage'),
                                                  false, 'radiobuttons',
                                                  array('choices' => array('1'=>get_string('yes', 'rlreport_resource_usage'),
                                                                           '0'=>get_string('no', 'rlreport_resource_usage')),
                                                        'checked' => '1',
                                                        'heading' => get_string('filter_average_grades', 'rlreport_resource_usage')
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

        $columns = array();

        $columns = array(new table_report_column("'ToDo' AS r_activity", get_string('column_activity', 'rlreport_resource_usage'), 'cssactivity', 'left', false),
                         new table_report_column('0 AS r_number', get_string('column_number', 'rlreport_resource_usage'), 'cssnumber', 'left', false)
                        );

        if ($this->show_avegrades) {
            $columns[] = new table_report_column('0 AS r_total', get_string('column_total', 'rlreport_resource_usage'), 'csstotal', 'left', false);
        }


        return $columns;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  A string representing sorting by user id
     */
    function get_default_sort_field() {
        return 'r_activity';
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
         return array(new table_report_grouping('curriculum_name','cur.name',get_string('grouping_curriculum', 'rlreport_resource_usage').': ','ASC'),
                      new table_report_grouping('course_name','crs.name',get_string('grouping_course', 'rlreport_resource_usage').': ','ASC'),
                      new table_report_grouping('class_name','cls.idnumber',get_string('grouping_class', 'rlreport_resource_usage').': ','ASC')
                     );
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
        if (is_numeric($record->r_total)) {
            $totaltime = floor($record->r_total / 3600);
            $record->r_total = ($totaltime == 1) ?
                                $totaltime . '&nbsp;' . get_string('hour', 'rlreport_resource_usage') :
                                $totaltime . '&nbsp;' . get_string('hours', 'rlreport_resource_usage');
        } else {
            $record->r_total = get_string('na','rlreport_resource_usage');
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
        global $CFG,$CURMAN;

        $this->get_filter_values();

        $cm_user_id = cm_get_crlmuserid($this->userid);
        if(empty($cm_user_id)) {
            $cm_user_id = 0;
        }

        // Three different queries depending upon the segmentation filter

        // Main query
        $sql = "SELECT {$columns}, usr.lastname AS lastname, cur.name AS curriculum_name, crs.name AS course_name, cls.idnumber AS class_name
                FROM {$CURMAN->db->prefix_table(CURTABLE)} cur
                JOIN {$CURMAN->db->prefix_table(CURCRSTABLE)} curcrs
                  ON curcrs.curriculumid = cur.id
                JOIN {$CURMAN->db->prefix_table(CRSTABLE)} crs
                  ON crs.id = curcrs.courseid
                JOIN {$CURMAN->db->prefix_table(CLSTABLE)} cls
                  ON cls.courseid=crs.id
                JOIN {$CURMAN->db->prefix_table(STUTABLE)} enrol
                  ON enrol.classid=cls.id
                JOIN {$CURMAN->db->prefix_table(USRTABLE)} usr
                  ON usr.id = clsenr.userid
               ";

        return $sql;
    }

    /*
     * Retrieve the curriculum filter value and generate a filter statement to be included in a WHERE statement
     *
     * @return boolean  true
     */
    function get_filter_values() {

        //Fetch selected curricula from filter
        //$curricula = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'cc');
        //$selected_curricula = '';

        //Fetch boolean for show_avegrades
        $filter_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'showaveragegrades');
        $this->show_avegrades = $filter_array[0]['value'];

        //Fetch segmentation for showsegmentedby
        $filter_array = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'segmentedby');
        $this->segmented_by = $filter_array[0]['value'];

        //Set up empty filter statement
        /*$this->filter_statement = '';

        //Append partial AND statement for selected curricula
        if (!empty($curricula) && is_array($curricula)) {
            // Check for special ALL case
            if (is_numeric($curricula[0]['value']) && $curricula[0]['value'] == 0) {
                $this->filter_statement .= " AND curcrs.curriculumid IS NOT NULL";
            } else {
                $count = 0;
                foreach ($curricula as $key=>$value) {
                    $selected_curricula .= $value['value'];
                    if ($count > 0) {
                        $selected_curricula .= ', ';
                    }
                    $count++;
                }
                $this->filter_statement .= " AND curcrs.curriculumid IN ({$selected_curricula})";
            }
        }

        //Append date filter pieces if required to filter statement
        if(!empty($this->startdate)) {
            $this->filter_statement .= " AND enrol.enrolmenttime >= {$this->startdate}";
        }

        if(!empty($this->enddate)) {
            $this->filter_statement .= " AND enrol.enrolmenttime <= {$this->enddate}";
        }*/



        return true;
    }

    /**
     * Retrieves start and end settings from active filter (if exists)
     * and populates class properties: startdate and enddate
     *
     * @uses none
     * @param none
     * @return none
     */
    function get_filter_date_values() {
        $start = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'enrol' . '_sdt');
        $end = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'enrol' . '_edt');

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

        if ($selected_cluster = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'cluster')) {
            $count = 0;
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

        if ($selected_curricula = php_report_filtering_get_active_filter_values($this->get_report_shortname(),'curr')) {
            //print_object($selected_curricula);
            $count = 0;
            foreach ($selected_curricula as $curricula) {
                if($curricula = get_record('crlm_curriculum', 'id', $curricula['value'])) {
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
        $sdate = !empty($this->startdate)
                 ? $this->userdate($this->startdate, get_string('date_format', 'rlreport_resource_usage'))
                 : get_string('present', 'rlreport_course_progress_summary');
        $edate = !empty($this->enddate)
                 ? $this->userdate($this->enddate, get_string('date_format', 'rlreport_resource_usage'))
                 : get_string('present', 'rlreport_course_progress_summary');

        if (empty($this->startdate) && empty($this->enddate)) {
            $date_range_display = get_string('header_all', 'rlreport_course_progress_summary');;
        } else {
            $date_range_display = "{$sdate} - {$edate}";
        }
        $header_entries = array();
        $curricula_header =
        $header_entries[] = new php_report_header_entry(get_string('header_curricula', 'rlreport_course_progress_summary'),
                                                        $curricula_display,  'curricula');
        $header_entries[] = new php_report_header_entry(get_string('header_date_range', 'rlreport_course_progress_summary'),
                                                        $date_range_display, 'date_range');
        $header_entries[] = new php_report_header_entry(get_string('header_organization', 'rlreport_course_progress_summary'),
                                                        $cluster_display , 'organization');
        $header_entries[] = new php_report_header_entry(get_string('header_course_count', 'rlreport_course_progress_summary'),
                                                        $course_count, 'course_count');
        return $header_entries;
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     * and php_report:view capability
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //make sure context libraries are loaded
        $this->require_dependencies();
        return !($this->can_view['course'] &&
                 $this->can_view['curriculum'] &&
                 $this->can_view['cluster']);
    }

}

