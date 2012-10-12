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
 * @subpackage pm-blocks-phpreport-course_completion_by_cluster
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');

/**
 * Custom validation for checkbox elements
 *
 * @param   object   $element  The element we are validating
 * @param   string   $value    The submitted value
 * @param   array    $extra    Any extra information needed
 *
 * @return  boolean            true if ok, or false to trigger formslib
 *                             validation error
 */
function course_completion_check_custom_rule($element, $value, $extra) {
    $key = $extra[0];
    $values = $extra[1];
    $set_ok = false;

    foreach ($values->_options['choices'] as $name => $choice) {
        $item = $key .'_'. $name;
        if (optional_param($item, 0, PARAM_INT) == 1) {
            $set_ok = true;
        }
    }
    return $set_ok ? true : false;
}

class course_completion_by_cluster_report extends table_report {

    var $last_cluster_hierarchy = array();

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_CLUSTER;
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
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculum.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumstudent.class.php');

        //needed for options filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/checkboxes.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
        require_once($CFG->dirroot .'/elis/program/lib/filtering/clustertree.php');

        //make sure we have access to the context library
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');
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

        //cluster tree
        $enable_tree_label = get_string('enable_tree', 'rlreport_course_completion_by_cluster');
        $enable_dropdown_label = get_string('enable_dropdown', 'rlreport_course_completion_by_cluster');
        $help_label = get_string('tree_help', 'rlreport_course_completion_by_cluster');

        $clustertree_help = array('course_completion_by_cluster', $help_label,
                                  'rlreport_course_completion_by_cluster');

        $clustertree_options = array(
                'dropdown_button_text' => $enable_tree_label,
                'tree_button_text'     => $enable_dropdown_label,
                'report_id'            => $this->id,
                'report_shortname'     => 'course_completion_by_cluster',
                'help'                 => $clustertree_help);

        //completionstatus checkboxes
        $complete_key = STUSTATUS_PASSED .','. STUSTATUS_FAILED;
        $incomplete_key = STUSTATUS_NOTCOMPLETE .',NULL';

        $completed_courses_label = get_string('show_completed_courses', 'rlreport_course_completion_by_cluster');
        $incomplete_courses_label = get_string('show_incomplete_courses', 'rlreport_course_completion_by_cluster');
        $heading_label = get_string('completionstatus_options_heading', 'rlreport_course_completion_by_cluster');

        $choices = array($complete_key   => $completed_courses_label,
                         $incomplete_key => $incomplete_courses_label);

        $checked = array($complete_key, $incomplete_key);

        $completionstatus_options = array('choices'    => $choices,
                                          'checked'    => $checked,
                                          'numeric'    => true,
                                          'nullvalue'  => 'NULL',
                                          'isrequired' => true,
                                          'heading'    => $heading_label);

        //columns checkboxes
        $curriculum_label = get_string('column_option_curriculum', 'rlreport_course_completion_by_cluster');
        $status_label = get_string('column_option_status', 'rlreport_course_completion_by_cluster');
        $completion_label = get_string('column_option_completion', 'rlreport_course_completion_by_cluster');
        $heading_label = get_string('columns_options_heading', 'rlreport_course_completion_by_cluster');

        $choices = array('curriculum' => $curriculum_label,
                         'status'     => $status_label,
                         'completion' => $completion_label);

        $checked = array('curriculum', 'status', 'completion');

        $columns_options = array('choices'    => $choices,
                                 'checked'    => $checked,
                                 'allowempty' => true,
                                 'heading'    => $heading_label,
                                 'nofilter'   => true);

        //cluster role dropdown
        $clusterrole_choices = array();
        if ($roles = get_all_roles()) {
            foreach ($roles as $role) {
                $clusterrole_choices[$role->id] = $role->name;
            }
        }

        $clusterrole_options = array('choices'  => $clusterrole_choices,
                                     'numeric'  => true,
                                     'nofilter' => true);

        //cluster tree / dropdown filter
        $cluster_heading = get_string('filter_cluster', 'rlreport_course_completion_by_cluster');

        //add a bit of spacing
        $br = html_writer::empty_tag('br');
        $cluster_heading .= $br.$br;

        //include a descriptive message about how to use the tree view
        $cluster_heading .= get_string('filter_cluster_description', 'rlreport_course_completion_by_cluster');

        $cluster_filter = new generalized_filter_entry('cluster', '', 'clusterid', $cluster_heading,
                                                       false, 'clustertree', $clustertree_options);

        //completion checkboxes
        $completion_heading = get_string('completionstatus_options_heading',
                                         'rlreport_course_completion_by_cluster');
        $completion_filter = new generalized_filter_entry('completionstatus', '', 'enrol.completestatusid',
                                                          $completion_heading, false, 'checkboxes',
                                                          $completionstatus_options);

        //columns checkboxes
        $columns_heading = get_string('columns_options_heading', 'rlreport_course_completion_by_cluster');
        $columns_filter = new generalized_filter_entry('columns', '', '', $columns_heading, false,
                                                       'checkboxes',   $columns_options);

        //clusterrole dropdown
        $clusterrole_heading = get_string('filter_clusterrole', 'rlreport_course_completion_by_cluster');
        $clusterrole_filter = new generalized_filter_entry('clusterrole', '', '', $clusterrole_heading,
                                                           false, 'simpleselect', $clusterrole_options);

        //return all filters
        return array($cluster_filter, $completion_filter, $columns_filter, $clusterrole_filter);
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        //determine whether to show the enrolment status column
        $preferences = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(), 'columns_status',
                           $this->filter);

        //default to false because empty group returns non-empty info, causing issues
        //and we will always hit a parameter screen before running the report
        $show_status = true;
        if (isset($preferences['0']['value'])) {
            $show_status = $preferences['0']['value'];
        }

        //determine whether to show the completion element column
        $preferences = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(), 'columns_completion',
                           $this->filter);

        //default to false because empty group returns non-empty info, causing issues
        //and we will always hit a parameter screen before running the report
        $show_completion = true;
        if (isset($preferences['0']['value'])) {
            $show_completion = $preferences['0']['value'];
        }


        //user idnumber
        $idnumber_heading = get_string('column_idnumber', 'rlreport_course_completion_by_cluster');
        $idnumber_column = new table_report_column('user.idnumber AS useridnumber', $idnumber_heading, 'idnumber');

        //user fullname
        $name_heading = get_string('column_user_name', 'rlreport_course_completion_by_cluster');
        $name_column = new table_report_column('user.firstname', $name_heading, 'user_name', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_PDF, php_report::$EXPORT_FORMAT_HTML));
        $lastname_heading = get_string('column_lastname', 'rlreport_course_completion_by_cluster');
        $lastname_column = new table_report_column('user.lastname', $lastname_heading, 'user_name', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));
        $firstname_heading = get_string('column_firstname', 'rlreport_course_completion_by_cluster');
        $firstname_column = new table_report_column('user.firstname AS userfirstname', $firstname_heading, 'user_name', 'left', false, true, true, array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));

        //CM course name
        $course_heading = get_string('column_course', 'rlreport_course_completion_by_cluster');
        $course_column = new table_report_column('course.name AS course_name', $course_heading, 'course');

        //whether the course is required in the curriculum
        $required_heading = get_string('column_required', 'rlreport_course_completion_by_cluster');
        $required_column = new table_report_column('curriculum_course.required', $required_heading, 'required');

        //
        $class_heading = get_string('column_class', 'rlreport_course_completion_by_cluster');
        $class_column = new table_report_column('class.idnumber AS classidnumber', $class_heading, 'class');

        //array of all columns
        $result = array($idnumber_column, $name_column, $lastname_column, $firstname_column, $course_column, $required_column, $class_column);

        //add the enrolment status column if applicable, based on the filter
        if ($show_status) {
            $completed_heading = get_string('column_completed', 'rlreport_course_completion_by_cluster');
            $result[] = new table_report_column('enrol.completestatusid', $completed_heading, 'completed');
        }

        //always show the grade column
        $grade_heading = get_string('column_grade', 'rlreport_course_completion_by_cluster');
        $result[] = new table_report_column('enrol.grade', $grade_heading, 'grade');

        //show number of completion elements completed if applicable, based on the filter
        if ($show_completion) {
            $completionelements_heading = get_string('column_numcomplete', 'rlreport_course_completion_by_cluster');
            $result[] = new table_report_column('COUNT(class_graded.id) AS numcomplete',
                                                $completionelements_heading, 'numcomplete');
        }

        return $result;
    }

    /**
     * Specifies an SQL statement that will produce the required report
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement with optional params
     */
    function get_report_sql($columns) {
        $param_prefix = 'ccbcr_';

        //special version of the select columns used in the non-curriculum case
        $noncurriculum_columns = str_replace('curriculum.id', 'NULL', $columns);

        //extra column needed for the curriculum-specific records
        $extra_curriculum_columns = 'COUNT(course_completion.id) AS numtotal,
                                     enrol.id AS enrolid,
                                     class.id AS classid,
                                     cluster.id AS clusterid,
                                     cluster_context.path AS path,
                                     curriculum.name AS curriculumname,
                                     curriculum.id AS curriculumid,
                                     user.id AS userid,
                                     curriculum_assignment.completed,
                                     enrol.completestatusid AS enrolstatus,
                                     course.name AS coursename,
                                     course.id AS courseid,
                                     enrol.completetime AS enrolcompletetime,
                                     curriculum_assignment.timecompleted AS curriculumcompletetime';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $inactive = ' AND user.inactive = 0';
        } else {
            $inactive = '';
        }

        //extra column needed for the non-curriculum-specific records
        $extra_noncurriculum_columns = str_replace('curriculum.id', 'NULL', $extra_curriculum_columns);
        $extra_noncurriculum_columns = str_replace('curriculum.name', 'NULL', $extra_noncurriculum_columns);

        $params = array();
        $param_cluster_context = $param_prefix .'clust_context';
        $params[$param_cluster_context .'1'] = CONTEXT_ELIS_USERSET;
        $params[$param_cluster_context .'2'] = CONTEXT_ELIS_USERSET;

        //starting point for both cases
        $core_tables_fmt = '{'. user::TABLE .'} user
                        JOIN {'. clusterassignment::TABLE .'} user_cluster
                          ON user.id = user_cluster.userid
                        JOIN {'. userset::TABLE ."} cluster
                          ON user_cluster.clusterid = cluster.id
                        JOIN {context} cluster_context
                          ON cluster.id = cluster_context.instanceid
                          AND cluster_context.contextlevel = :{$param_cluster_context}%d";

        //course completion info used in both cases
        $completion_tables = 'LEFT JOIN {'. coursecompletion::TABLE .'} course_completion
                                ON course.id = course_completion.courseid
                              LEFT JOIN {'. student_grade::TABLE .'} class_graded
                                ON class.id = class_graded.classid
                                AND course_completion.id = class_graded.completionid
                                AND enrol.userid = class_graded.userid
                                AND class_graded.locked = 1
                                AND class_graded.grade >= course_completion.completion_grade';

        //filters for each of two cases, put inside the query for performance reasons
        //(use parent functionality because we are preventing filter application in the usual way)
        $curriculum_filter = parent::get_filter_condition('WHERE');
        $noncurriculum_filter = parent::get_filter_condition('AND');

        //grouping for each of the two cases
        $group_by = "GROUP BY user.id, enrol.id, cluster.id, course.id, curriculum.id";
        $noncurriculum_group_by = "GROUP BY user.id, enrol.id, cluster.id, course.id";

        // status of clustertree filter, drop-down menu
        $usingdd = php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(), 'cluster_usingdropdown',
                       $this->filter);

        //check permissions
        $permissions_filter = '';
        $filter_params = array();
        if ($usingdd === false || empty($usingdd[0]['value'])) {
            //check permissions ONLY IF they selected dropdown: 'any value'
            //TBD: IFF we can disable checkboxes for non-permitted tree clusters
            //     THEN we can remove the second if condition above:
            //     || empty($usingdd[0]['value'])

            $contexts = get_contexts_by_capability_for_user('cluster', $this->access_capability, $this->userid);
            //$permissions_filter = $contexts->sql_filter_for_context_level('clusterid', 'cluster');
            $filter_obj = $contexts->get_filter('clusterid', 'cluster');
            $filter_sql = $filter_obj->get_sql(false, null, SQL_PARAMS_NAMED);
            if (isset($filter_sql['where'])) {
                $permissions_filter = 'WHERE '. $filter_sql['where'];
                $filter_params = $filter_sql['where_parameters'];
            }
        }

        $lastname = 'user.lastname';
        if (stripos($columns, $lastname) === FALSE) {
            $columns .= ", {$lastname}";
        }
        //the master query
        $sql = "SELECT * FROM (
                  SELECT DISTINCT {$columns},
                         {$extra_curriculum_columns}
                  FROM ". sprintf($core_tables_fmt, 1) .'
                  JOIN {'. curriculumstudent::TABLE .'} curriculum_assignment
                    ON user.id = curriculum_assignment.userid
                  JOIN {'. curriculum::TABLE .'} curriculum
                    ON curriculum_assignment.curriculumid = curriculum.id
                  JOIN {'. curriculumcourse::TABLE .'} curriculum_course
                    ON curriculum.id = curriculum_course.curriculumid
                  JOIN {'. course::TABLE .'} course
                    ON curriculum_course.courseid = course.id
                   LEFT JOIN ({'. pmclass::TABLE .'} class
                              JOIN {'. student::TABLE ."} enrol
                              ON class.id = enrol.classid)
                     ON curriculum_assignment.userid = enrol.userid
                     AND course.id = class.courseid
                  {$completion_tables}
                  {$curriculum_filter[0]}
                  {$inactive}
                  {$group_by}

                  UNION

                  SELECT DISTINCT {$noncurriculum_columns},
                         {$extra_noncurriculum_columns}
                  FROM ". sprintf($core_tables_fmt, 2) .'
                  JOIN {'. student::TABLE .'} enrol
                    ON user.id = enrol.userid
                  JOIN {'. pmclass::TABLE .'} class
                    ON enrol.classid = class.id
                  JOIN {'. course::TABLE .'} course
                    ON class.courseid = course.id
                  LEFT JOIN ({'. curriculumcourse::TABLE .'} curriculum_course
                             JOIN {'. curriculumstudent::TABLE ."} curriculum_assignment
                               ON curriculum_course.curriculumid = curriculum_assignment.curriculumid)
                    ON course.id = curriculum_course.courseid
                    AND enrol.userid = curriculum_assignment.userid
                    {$completion_tables}

                    WHERE curriculum_assignment.id IS NULL
                    {$noncurriculum_filter[0]}
                    {$inactive}
                    {$noncurriculum_group_by}
                ) main_data
              {$permissions_filter}";

        $params = array_merge($params, $curriculum_filter[1], $noncurriculum_filter[1], $filter_params);
        return array($sql, $params);
    }

    /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return '';
    }

    /**
     * Takes a record and transforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     * @uses $CFG
     * @return stdClass  The reformatted record
     */
    function transform_record($record, $export_format) {
        global $CFG;

        //is this a required course?
        if ($record->curriculumid === NULL) {
            //not part of a curriculum
            $record->required = get_string('na', 'rlreport_course_completion_by_cluster');
        } else if ($record->required == 1) {
            $record->required = get_string('required_yes', 'rlreport_course_completion_by_cluster');
        } else {
            $record->required = get_string('required_no', 'rlreport_course_completion_by_cluster');
        }


        //make sure we want to display this column
        if (property_exists($record, 'completestatusid')) {
            if ($record->completestatusid === NULL) {
                //not enrolled in the class
                $record->completestatusid = get_string('stustatus_notenrolled', 'rlreport_course_completion_by_cluster');
            } else if ($record->completestatusid == STUSTATUS_NOTCOMPLETE) {
                $record->completestatusid = get_string('stustatus_notcomplete', 'rlreport_course_completion_by_cluster');
            } else if ($record->completestatusid == STUSTATUS_PASSED) {
                //flag the class enrolment as passed and show completion date
                $a = $this->format_date($record->enrolcompletetime);
                $record->completestatusid = get_string('stustatus_passed', 'rlreport_course_completion_by_cluster', $a);
            } else {
                //flag the class enrolment as failed and show completion date
                $a = $this->format_date($record->enrolcompletetime);
                $record->completestatusid = get_string('stustatus_failed', 'rlreport_course_completion_by_cluster', $a);
            }
        }

        //class grade
        if ($record->grade === NULL) {
            //not enrolled
            $record->grade = get_string('na', 'rlreport_course_completion_by_cluster');
        } else {
            //format the grade value
            $record->grade = get_string(
                        ($export_format == php_report::$EXPORT_FORMAT_CSV)
                        ? 'formatted_grade_csv' : 'formatted_grade',
                        'rlreport_course_completion_by_cluster',
                        pm_display_grade($record->grade));
        }

        if (isset($record->numcomplete)) {
            $record->numcomplete = get_string('numcomplete_tally', 'rlreport_course_completion_by_cluster', $record);
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
        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one course context
        $contexts = get_contexts_by_capability_for_user('cluster', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @uses    $DB
     * @return  array List of objects containing grouping id, field names, display labels and sort order
     */
     function get_grouping_fields() {
         global $DB;
         //field that is used to compare one record from the next
         $compare_field = $DB->sql_concat('user.lastname', "'_'", 'user.firstname', "'_'", 'user.id');

         //field used to order for groupings
         $order_field = $DB->sql_concat('lastname', "'_'", 'firstname', "'_'", 'userid');

         $cluster_label = get_string('grouping_cluster', 'rlreport_course_completion_by_cluster');
         $cluster_grouping = new table_report_grouping('cluster', 'cluster.id', $cluster_label, 'ASC',
                                                       array('cluster.name'), 'above', 'path');

         $user_grouping_fields = array('user.idnumber AS useridnumber',
                                       'user.firstname');
         $user_grouping = new table_report_grouping('groupuseridnumber', $compare_field, '', 'ASC',
                                                    $user_grouping_fields, 'below', $order_field);

         //these groupings will always be used
         $result = array($cluster_grouping, $user_grouping);

         //determine whether or not we should use the curriculum grouping
         $preferences = php_report_filtering_get_active_filter_values(
                            $this->get_report_shortname(), 'columns_curriculum',
                            $this->filter);

         $show_curriculum = true;
         if (isset($preferences['0']['value'])) {
             $show_curriculum = $preferences['0']['value'];
         }

         if ($show_curriculum) {
             $curriculum_label = get_string('grouping_curriculum', 'rlreport_course_completion_by_cluster');
             $result[] = new table_report_grouping('groupcurriculumid', 'curriculum.id', $curriculum_label, 'ASC', array(),
                                                   'below', 'curriculumname, curriculumid');
         }

         return $result;
     }

     /**
     * Constructs an appropriate order by clause for the main query
     *
     * @return  string  The appropriate order by clause
     */
    function get_order_by_clause() {
        $result = parent::get_order_by_clause();

        //make sure the result is actually valid
        if ($result != '') {
            //always sort by course in addition to everything else
            $result .= ', coursename, courseid';
        }

        return $result;
    }

    /**
     * Transforms a heading element displayed above the columns into a listing of such heading elements
     *
     * @param   string array           $grouping_current  Mapping of field names to current values in the grouping
     * @param   table_report_grouping  $grouping          Object containing all info about the current level of grouping
     *                                                    being handled
     * @param   stdClass               $datum             The most recent record encountered
     * @param   string    $export_format  The format being used to render the report
     * @uses    $DB
     * @return  string array                              Set of text entries to display
     */
     function transform_grouping_header_label($grouping_current, $grouping, $datum, $export_format) {
         global $DB;
         if ($grouping->field == 'curriculum.id') {
             /**
              * Curriculum grouping - display the curriculum name or a default
              * if none
              */

             //get the curriculum id from the current grouping info
             $curriculumid = $grouping_current['curriculum.id'];

             if (empty($curriculumid)) {
                 //default label
                 return array($this->add_grouping_header($grouping->label,
                                         get_string('non_curriculum_courses',
                                            'rlreport_course_completion_by_cluster'),
                                         $export_format));
             } else {
                 //actually have a curriculum, so display it
                 $curriculum_name = $DB->get_field(curriculum::TABLE, 'name',
                                        array('id' => $curriculumid));

                 $completed_description = '';
                 if ($datum->completed) {
                     //flag the curriculum as complete and show the completion date
                     $a = $this->format_date($datum->curriculumcompletetime);
                     $completed_description = get_string('completed_yes', 'rlreport_course_completion_by_cluster', $a);
                 } else {
                     //flag the curriculum as incomplete
                     $completed_description = get_string('completed_no', 'rlreport_course_completion_by_cluster');
                 }

                 return array($this->add_grouping_header($grouping->label,
                                  $curriculum_name .' '. $completed_description,
                                  $export_format));
             }
         } else if ($grouping->field == 'cluster.id') {
             /**
              * Cluster grouping - display the hierarchy of clusters
              */

             //get the current (new) cluster id
             $clusterid = $grouping_current[$grouping->field];
             $cluster = new userset($clusterid);

             $result = array();

             //build the hierarchy bottom-up based on the supplied cluster
             $current_cluster_hierarchy = array();

             while ($cluster->id != 0) {
                 $current_cluster_hierarchy[] = $cluster->id;
                 $result[] = $this->add_grouping_header($grouping->label,
                                                        $cluster->name,
                                                        $export_format);
                 $cluster = new userset($cluster->parent);
             }

             //really need it top-down for comparison
             $current_cluster_hierarchy = array_reverse($current_cluster_hierarchy);

             //find the first position where the old and new hieararchies differ
             $final_pos = -1;
             for ($i = 0; $i < count($this->last_cluster_hierarchy)
                          && $i < count($current_cluster_hierarchy); $i++) {
                 if ($this->last_cluster_hierarchy[$i] != $current_cluster_hierarchy[$i]) {
                     $final_pos = $i;
                     break;
                 }
             }

             //default to next level down
             if ($final_pos == -1) {
                 $final_pos = max(count($this->last_cluster_hierarchy), count($current_cluster_hierarchy)) - 1;
             }

             //store for next iteration
             $this->last_cluster_hierarchy = $current_cluster_hierarchy;

             $result = array_reverse($result);

             /**
              * Get the listing of cluster leaders
              */
             $preferences = php_report_filtering_get_user_preferences('course_completion_by_cluster');
             if (isset($preferences['php_report_course_completion_by_cluster/clusterrole'])) {

                 //query to retrieve users directly assigned the configured role in the current cluster
                 $sql = 'SELECT u.* FROM {user} u
                         JOIN {role_assignments} ra
                           ON u.id = ra.userid
                           AND ra.roleid = ?
                         JOIN {context} ctxt
                           ON ra.contextid = ctxt.id
                           AND ctxt.instanceid = ?
                           AND ctxt.contextlevel = ?';
                  $params = array($preferences['php_report_course_completion_by_cluster/clusterrole'],
                                  $datum->cluster, CONTEXT_ELIS_USERSET);
                  $display = '';

                 //append all the names together if there are multiple
                 if ($recordset = $DB->get_recordset_sql($sql, $params)) {
                     foreach ($recordset as $record) {
                         if ($display == '') {
                             $display = fullname($record);
                         } else {
                             $display .= ', '. fullname($record);
                         }
                     }
                     $recordset->close();
                 }

                 if ($display == '') {
                     //no names found
                     $display = get_string('na', 'rlreport_course_completion_by_cluster');
                 }

                 //add a header entry
                 $cluster_leader_label = get_string('cluster_leaders', 'rlreport_course_completion_by_cluster').' ';
                 $result[] = $this->add_grouping_header($cluster_leader_label, $display, $export_format);
             }

             //return the labels in top-down order
             return $result;
         } else {
             return array($this->add_grouping_header($grouping->label,
                                     $grouping_current[$grouping->field],
                                     $export_format));
         }
     }

    /**
     * Transforms a column-based header entry into the form required by the report
     *
     * @param   stdClass  $element        The record representing the current grouping row
     *                                    (including only fields that are part of that grouping row)
     * @param   stdClass  $datum          The record representing the current report row
     * @param   string    $export_format  The format being used to render the report
     * @uses    $CFG
     * @return  stdClass                  The current grouping row, in its final state
     */
    function transform_grouping_header_record($element, $datum, $export_format) {
        global $CFG;

        $induser_report_location = "{$CFG->wwwroot}/blocks/php_report/render_report_page.php?report=individual_user&userid={$datum->userid}";

        //make this a link if we're in HTML format
        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $element->useridnumber = '<span class="external_report_link"><a href="'
                                      . $induser_report_location .
                                      '"" target="_blank">'.
                                      $element->useridnumber .'</a></span>';
        }

        if ($export_format != php_report::$EXPORT_FORMAT_CSV &&
            $export_format != php_report::$EXPORT_FORMAT_EXCEL) {
            //use the user's full name
            $element->firstname = fullname($datum);
        }
        //make this a link if we're in HTML format
        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $element->firstname = '<span class="external_report_link"><a href="'
                                  . $induser_report_location .
                                  '"" target="_blank">'.
                                  $element->firstname .'</a></span>';
        }

        return $element;
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
        return array(129, 245, 173);
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
        return array(
                array(219, 229, 241),
                array(180, 187, 238),
                array(255, 255, 255));
    }

    /**
     * Specifies whether header entries calculated for the same grouping level
     * and the same report row should be combined into a single column in CSV exports
     *
     * @return  boolean  true if enabled, otherwise false
     */
    function group_repeated_csv_headers() {
        //allow, since we have a dynamic number of cluster levels
        return true;
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
        $elem = "{$key}_grp";
        if ($mform->elementExists($elem)) {
            $mform->addRule($elem, get_string('required'), 'required', null, 'client');
            $mform->registerRule('custom_rule', 'function', 'course_completion_check_custom_rule');
            $mform->addRule($elem, get_string('required'), 'custom_rule', array($key,$fields));
        }
        return $mform;
    }

    /**
     * Formats a timestamp as desired based on a language string and the user's time zone
     *
     * @param   int     $timestamp  The timestamp to format
     * @return  string              The formatted date
     */
    function format_date($timestamp) {
        //determine the format
        $format = get_string('date_format', 'rlreport_course_completion_by_cluster');
        return $this->userdate($timestamp, $format);
    }

    /**
     * Calculates the entirety of the SQL condition created by report filters
     * for the current report instance being execute, including the leading AND or WHERE token
     *
     * @param   string  $conditional_symbol  the leading token (should be AND or WHERE)
     *
     * @return  array   the appropriate SQL condition with optional params
     */
    function get_filter_condition($conditional_symbol) {
        //explicitly prevent filters from beign automatically applied to the report query
        return array('', array());
    }
}

