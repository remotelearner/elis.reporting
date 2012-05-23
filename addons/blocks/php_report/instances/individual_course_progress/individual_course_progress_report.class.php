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
 * @subpackage pm-blocks-phpreport-individual_course_progress
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');
require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php');

class individual_course_progress_report extends table_report {
    var $custom_joins = array();
    var $lang_file = 'rlreport_individual_course_progress';

    var $field_default = array();

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
        //needs the contexts code
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        //needed for constants that define db tables
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/userset.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');

        //needed to get the filtering libraries
        require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
        require_once($CFG->dirroot .'/elis/program/lib/filtering/custom_field_multiselect_values.php');

        //needed for the permissions-checking logic on custom fields
        require_once($CFG->dirroot .'/blocks/php_report/sharedlib.php');
        require_once($CFG->dirroot .'/elis/program/lib/deprecatedlib.php');
    }

    function get_header_entries($export_format) {
        global $DB;

        $header_array = array();

        $cm_user_id = php_report_filtering_get_active_filter_values(
                          $this->get_report_shortname(), 'userid', $this->filter);

        $cluster_names = array();
        // Find all the clusters this user is in
        if (!empty($cm_user_id[0]['value'])) {
            $userid = (int)$cm_user_id[0]['value'];
            $user_obj = new user($userid);
            $user_obj->load();

            $sql = "SELECT DISTINCT clst.name
                    FROM {".userset::TABLE."} clst
                    JOIN {".clusterassignment::TABLE."} usrclst
                      ON clst.id = usrclst.clusterid
                    WHERE usrclst.userid = ?
                    ORDER BY clst.name";
            $params = array($userid);
            if ($clusters = $DB->get_recordset_sql($sql, $params)) {
                foreach ($clusters as $cluster) {
                    $cluster_names[] = $cluster->name;
                }
            }
        }

        if (!empty($user_obj)) {
            $header_obj = new stdClass;
            $header_obj->label = get_string('header_student', $this->lang_file).':';
            $header_obj->value = fullname($user_obj->to_object());
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            $header_obj = new stdClass;
            $header_obj->label = get_string('header_id', $this->lang_file) .':';
            $header_obj->value = $user_obj->idnumber;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            $header_obj = new stdClass;
            $header_obj->label = get_string('header_email', $this->lang_file) .':';
            $header_obj->value = $user_obj->email;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            $header_obj = new stdClass;
            $header_obj->label = get_string('header_reg_date', $this->lang_file) .':';
            $header_obj->value = $this->userdate($user_obj->timecreated);
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            $header_obj = new stdClass;
            $header_obj->label = get_string('header_cluster', $this->lang_file) .':';
            $header_obj->value = (count($cluster_names) > 0)
                               ? implode(', ', $cluster_names)
                               : get_string('not_available', $this->lang_file);
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            $header_obj = new stdClass;
            $header_obj->label = get_string('header_date', $this->lang_file) .':';
            $header_obj->value = $this->userdate(time());
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;
        }

        return $header_array;
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

        $filters = array();
        $users = array();

        if ($init_data) {
            $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
            $user_objects = usermanagement_get_users_recordset('name', 'ASC', 0, 0, '', $contexts);

            // If in interactive mode, user should have access to at least their own info
            if ($this->execution_mode == php_report::EXECUTION_MODE_INTERACTIVE) {
                $cm_user_id = cm_get_crlmuserid($this->userid);
                $user_object = new user($cm_user_id);
                $users[$user_object->id] = fullname($user_object) .' ('. $user_object->idnumber .')';
            }

            if (!empty($user_objects)) {
                // Create a list of users this user has permissions to view
                foreach ($user_objects as $user_object) {
                    $users[$user_object->id] = $user_object->name .' ('.
                                               $user_object->idnumber .')';
                }
                $user_objects->close();
            }
        }

        $filters[] = new generalized_filter_entry('userid', 'crlmuser', 'id',
                             get_string('filter_user', $this->lang_file),
                             false, 'simpleselect',
                             array('choices' => $users,
                                   'numeric' => true,
                                   'noany'   => true,
                             'help' => array('individual_course_progress_user',
                                             get_string('displayname', $this->lang_file),
                                             $this->lang_file)
                                                       )
                         );

        $field_list = array();
        // Add block id to field list array
        $field_list['block_instance'] = $this->id;
        $field_list['reportname'] = $this->get_report_shortname();
        $field_list['field_exceptions'] = array('_elis_course_pretest',
                                                '_elis_course_posttest');
        // Need help text
        $field_list['help'] = array('individual_course_progress_field',
                                    get_string('displayname', $this->lang_file),
                                    $this->lang_file);

        $filters[] = new generalized_filter_entry('field'. $this->id, 'field'. $this->id, 'id', get_string('selectcustomfields', $this->lang_file), false, 'custom_field_multiselect_values', $field_list);

        return $filters;
    }

    /**
     * Specifies which values for the log 'module' field count as resources
     * when tracking resource views
     *
     * @return  array  The list of appropriate field values
     */
    function get_resource_modules() {
        global $CFG, $SITE;

        //course API
        require_once($CFG->dirroot.'/course/lib.php');

        //retrieve information about all modules on the site
        get_all_mods($SITE->id, $mods, $modnames, $modnamesplural, $modnamesused);

        //make sure to always count 'resource' for legacy reasons
        $result = array('resource');

        foreach($modnames as $modname => $modnamestr) {
            //make sure the module is valid
	        $libfile = "$CFG->dirroot/mod/$modname/lib.php";
            if (!file_exists($libfile)) {
                continue;
            }

            //check to see if the module is considered a resource in a "legacy" way
            include_once($libfile);
            $gettypesfunc =  $modname.'_get_types';

            if (function_exists($gettypesfunc)) {
   	            //look through supported "types" for resource
                if ($types = $gettypesfunc()) {
                    foreach($types as $type) {
                        if ($type->modclass == MOD_CLASS_RESOURCE) {
                            $result[] = $modname;
                            break;
                        }
                    }
                }
            } else {
                //determine if the the module supports resource functionality
                $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
                if ($archetype == MOD_ARCHETYPE_RESOURCE) {
                    $result[] = $modname;
                }
            }
        }

        return $result;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various fields involving user info, clusters, class enrolment, and module information)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        $columns = array();
        $columns[] = new table_report_column('crs.name',
                                             get_string('column_course', $this->lang_file),
                                             'csscourse', 'left', true);

        $columns[] = new table_report_column('cls.idnumber',
                                             get_string('column_class_id', $this->lang_file),
                                             'cssclass', 'left', true);

       $filter_params = php_report_filtering_get_active_filter_values(
                            $this->get_report_shortname(), 'field'. $this->get_report_shortname());

       $filter_params = $filter_params[0]['value'];
       $filter_params = $filter_params ? explode(',', $filter_params) : array();

        // Loop through these additional parameters - new columns, will  have to eventually pass the table etc...
        if (isset($filter_params) && is_array($filter_params)) {
            // Working with custom course fields - get all course fields
            $context = context_level_base::get_custom_context_level('course', 'elis_program');
            $fields = field::get_for_context_level($context)->to_array();

            foreach ($filter_params as $custom_course_id) {
                $custom_course_field = new field($custom_course_id);

                // Obtain custom field default values IFF set
                if (($default_value = $custom_course_field->get_default())
                    !== false) {
                    // save in array { record_field => default_value }
                    $this->field_default['custom_data_'. $custom_course_id] =
                              $default_value;
                }

                //Find matching course field
                $course_field_title = $fields[$custom_course_id]->name;

                //Now, create a join statement for each custom course field and add it to the sql query
                $data_table = $custom_course_field->data_table();

                //field used to identify course id in custom field subquery
                $course_id_field = "ctxt_instanceid_{$custom_course_id}";

                //make sure the user can view fields for the current course
                $view_field_capability = generalized_filter_custom_field_multiselect_values::field_capability($custom_course_field->owners);
                $view_field_contexts = get_contexts_by_capability_for_user('course', $view_field_capability, $this->userid);

                //$view_field_filter = $view_field_contexts->sql_filter_for_context_level('ctxt.instanceid', 'course');
                $filter_obj = $view_field_contexts->get_filter('instanceid', 'course');
                $filter_sql = $filter_obj->get_sql(false, 'ctxt', SQL_PARAMS_NAMED);
                $view_field_filter = 'TRUE';
                $params = array();
                if (isset($filter_sql['where'])) {
                    $view_field_filter = $filter_sql['where'];
                    $params = $filter_sql['where_parameters'];
                }

                // Create a custom join to be used later for the completed sql query
                $this->custom_joins[] = array("
                LEFT JOIN (SELECT d.data as custom_data_{$custom_course_id}, ctxt.instanceid as ctxt_instanceid_{$custom_course_id}
                          FROM {context} ctxt
                          JOIN {". $data_table ."} d
                            ON d.contextid = ctxt.id AND d.fieldid = {$custom_course_id}
                          WHERE ctxt.contextlevel = {$context}
                            AND {$view_field_filter}) custom_{$custom_course_id}
                       ON cls.courseid = custom_{$custom_course_id}.{$course_id_field}", $params);

                $columns[] = new table_report_column('custom_'. $custom_course_id .'.custom_data_'. $custom_course_id,
                                     $fields[$custom_course_id]->name,
                                     'csscustom_course_field', 'left', true);
            }
        }

        // completion elements completed/total
        $columns[] = new table_report_horizontal_bar_column(
                             '(SELECT COUNT(*) FROM {'. coursecompletion::TABLE .'} comp
                                 JOIN {'. pmclass::TABLE .'} cls2
                                   ON cls2.courseid = comp.courseid
                                 JOIN {'. student::TABLE .'} stu
                                   ON stu.classid = cls2.id
                                 JOIN {'. student_grade::TABLE .'} clsgr
                                   ON clsgr.classid = cls2.id
                                  AND clsgr.userid = stu.userid
                                  AND clsgr.locked = 1
                                  AND clsgr.grade >= comp.completion_grade
                                  AND clsgr.completionid = comp.id
                                WHERE cls2.id = cls.id
                                  AND stu.userid = crlmuser.id
                              ) AS stucompletedprogress',
                             get_string('bar_column_progress', $this->lang_file),
                             'progress_bar',
                             '(SELECT COUNT(*) FROM {'. coursecompletion::TABLE .'} comp
                                 JOIN {'. pmclass::TABLE .'} cls2
                                   ON cls2.courseid = comp.courseid
                                WHERE cls2.id = cls.id
                              ) AS numprogress',
                              'center', '$p')
;
        $columns[] = new table_report_column('0 AS completedprogress',
                             get_string('column_progress', $this->lang_file),
                             'cssprogress', 'center', true);

        $columns[] = new table_report_column('cls.startdate',
                             get_string('column_start_date', $this->lang_file),
                             'cssstart_date', 'center', true);

        $columns[] = new table_report_column('cls.enddate',
                             get_string('column_end_date', $this->lang_file),
                             'cssend_date', 'center', true);

        $columns[] = new table_report_column('pretest.score AS pretestscore',
                             get_string('column_pretest_score', $this->lang_file),
                             'csspretest_score', 'center', true);

        $columns[] = new table_report_column('posttest.score AS posttestscore',
                             get_string('column_posttest_score', $this->lang_file),
                             'cssposttest_score', 'center', true);

        // discussion posts
        $columns[] = new table_report_column(
                             '(SELECT COUNT(*) FROM {forum_discussions} disc
                                 JOIN {forum_posts} post
                                   ON post.discussion = disc.id
                                WHERE disc.course = clsmdl.moodlecourseid
                                  AND post.userid = user.id
                              ) AS numposts',
                              get_string('column_discussion_posts', $this->lang_file),
                              'cssdiscussion_posts', 'center', true);

        //create an IN clause identifying modules that are considered resources
        //todo: use get_in_or_equal
        $modules = $this->get_resource_modules();
        $in = "IN ('".implode("', '", $modules)."')";

        // resources accessed
        $columns[] = new table_report_column(
                             "(SELECT COUNT(*) FROM {log} log
                                WHERE log.module {$in}
                                  AND log.action = 'view'
                                  AND log.userid = user.id
                                  AND log.course = clsmdl.moodlecourseid
                              ) AS numresources",
                              get_string('column_resources_accessed', $this->lang_file),
                              'cssresources_accessed', 'center', true);

        return $columns;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  String that represents a field indicating whether a cluster assignment exists
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
        return "enrol.id";
    }

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @return  array List of objects containing grouping id, field names, display labels and sort order
     */
     function get_grouping_fields() {
         return array(new table_report_grouping('enrol_status',
                              'enrol.completestatusid != 0',
                              get_string('grouping_progress', $this->lang_file) .': ',
                              'ASC')
                     );
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
        global $CFG, $USER;

        $params = array();
        $cm_user_id = cm_get_crlmuserid($USER->id);
        $filter_array = php_report_filtering_get_active_filter_values(
                            $this->get_report_shortname(), 'userid',
                            $this->filter);
        $filter_user_id = (isset($filter_array[0]['value']))
                          ? $filter_array[0]['value']
                          : -1; // ELIS-4699: so not == to invalid cm/pm userid

        $permissions_filter = 'TRUE';
        if ($filter_user_id != $cm_user_id || $this->execution_mode != php_report::EXECUTION_MODE_INTERACTIVE) {
            // obtain all course contexts where this user can view reports
            $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
            //$permissions_filter = $contexts->sql_filter_for_context_level('crlmuser.id', 'user');
            $filter_obj = $contexts->get_filter('crlmuser.id', 'user');
            $filter_sql = $filter_obj->get_sql(false, 'crlmuser', SQL_PARAMS_NAMED);
            if (isset($filter_sql['where'])) {
                $permissions_filter = $filter_sql['where'];
                $params = $filter_sql['where_parameters'];
            }
        }

        //tracks progress used by this user
        $total_progress_subquery =
             'SELECT cls.id AS classid, stu.userid as userid, COUNT(*) AS numprogress, COUNT(clsgr.id) AS stucomplete
                FROM {'. coursecompletion::TABLE .'} comp
                JOIN {'. pmclass::TABLE .'} cls
                  ON cls.courseid = comp.courseid
           LEFT JOIN {'. student::TABLE .'} stu
                  ON stu.classid = cls.id
           LEFT JOIN {'. student_grade::TABLE .'} clsgr
                  ON clsgr.classid = cls.id
                 AND clsgr.userid = stu.userid
                 AND clsgr.locked = 1
                 AND clsgr.grade >= comp.completion_grade
                 AND clsgr.completionid = comp.id
            GROUP BY cls.id, stu.userid
             ';

        //gets the pretest score for this user
        $pretest_query = $this->get_max_test_score_sql('_elis_course_pretest');

        //gets the posttest score for this user
        $posttest_query = $this->get_max_test_score_sql('_elis_course_posttest');

        //main query
        $sql = "SELECT {$columns}, crs.id AS courseid,
                       cls.starttimehour AS starttimehour,
                       cls.starttimeminute AS starttimeminute,
                       cls.endtimehour AS endtimehour,
                       cls.endtimeminute AS endtimeminute
                 FROM {". pmclass::TABLE .'} cls
                 JOIN {'. student::TABLE .'} enrol
                   ON enrol.classid = cls.id
                 JOIN {'. user::TABLE .'} crlmuser
                   ON crlmuser.id = enrol.userid
                 JOIN {user} user
                   ON user.idnumber = crlmuser.idnumber
            LEFT JOIN {'. classmoodlecourse::TABLE .'} clsmdl
                   ON clsmdl.classid = cls.id
            LEFT JOIN {'. course::TABLE ."} crs
                   ON crs.id = cls.courseid
            LEFT JOIN ({$pretest_query}) pretest
                   ON pretest.classid = cls.id
                  AND pretest.userid = crlmuser.id
            LEFT JOIN ({$posttest_query}) posttest
                   ON posttest.classid = cls.id
                  AND posttest.userid = crlmuser.id
               ";

        // add custom field joins if they exist
        if (!empty($this->custom_joins)) {
            foreach ($this->custom_joins as $custom_join) {
                $sql .= $custom_join[0];
                $params += $custom_join[1];
            }
        }

        $sql .= "
                WHERE {$permissions_filter}";

        return array($sql, $params);
    }

    /**
 	* Return the maximum test score SQL statement
 	*
 	* @param   string  $field_shortname	 field short name to be used in get_field request
 	* @uses    $DB
 	* @return  string                    The appropriate SQL statement
 	*/
    function get_max_test_score_sql($field_shortname) {
        global $DB;

        $course_context_level = context_level_base::get_custom_context_level('course', 'elis_program');

        if ($field_id = $DB->get_field('elis_field', 'id', array('shortname' => $field_shortname))) {
            $field = new field($field_id);
            $data_table = $field->data_table();

            $sql = 'SELECT MAX(clsgrd.grade) AS score, class.id AS classid,
                        clsgrd.userid AS userid
                    FROM {'. $data_table ."} d
                    JOIN {context} ctxt
                      ON d.contextid = ctxt.id
                     AND ctxt.contextlevel = {$course_context_level}
                    JOIN {". coursecompletion::TABLE .'} comp
                      ON d.data = comp.idnumber
                    JOIN {'. pmclass::TABLE .'} class
                      ON class.courseid = ctxt.instanceid
                    JOIN {'. student_grade::TABLE ."} clsgrd
                      ON clsgrd.classid = class.id
                     AND clsgrd.locked = 1
                     AND clsgrd.completionid = comp.id
                   WHERE d.fieldid = {$field_id}
                GROUP BY class.id, clsgrd.userid
                   ";
        } else {
            $sql = "SELECT NULL AS score, NULL AS classid, NULL as userid
                      FROM {user}";
        }

        return $sql; // TBD: array ???
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

        $record->startdate = ($record->startdate == 0)
                             ? get_string('na', $this->lang_file)
                             : $this->pmclassdate($record, 'start');

        $today = strtotime(date('Y-m-d'));
        if ($record->enddate > $today) {
            $record->enddate = get_string('transform_column_in_progress',
                                          $this->lang_file);
        } else {
            $record->enddate = ($record->enddate == 0)
                               ? get_string('na', $this->lang_file)
                               : $this->pmclassdate($record, 'end');
        }

        //make sure this is set to something so that the horizontal bar graph doesn't disappear
        if (empty($record->stucompletedprogress)) {
            $record->stucompletedprogress = 0;
        }

        $a = new stdClass;
        if (isset($record->stucompletedprogress)) {
            $a->value = $record->stucompletedprogress;
            $a->total = $record->numprogress;
        } else {
            $a->value = 0;
            $a->total = 0;
        }
        $record->completedprogress = get_string('of', $this->lang_file, $a);

        if (empty($record->numresources)) {
           $record->numresources = 0;
        }

        if (!empty($record->pretestscore)) {
            $record->pretestscore = pm_display_grade($record->pretestscore);
            if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
                $record->pretestscore .= get_string('percent_symbol', $this->lang_file);
            }
        } else {
            $record->pretestscore = get_string('no_test_symbol', $this->lang_file);
        }

        if (!empty($record->posttestscore)) {
            $record->posttestscore = pm_display_grade($record->posttestscore);
            if ($export_format != php_report::$EXPORT_FORMAT_CSV) {
                $record->posttestscore .= get_string('percent_symbol', $this->lang_file);
            }
        } else {
            $record->posttestscore = get_string('no_test_symbol', $this->lang_file);
        }

        if (empty($record->numposts)) {
            $record->numposts = 0;
        }

        $record->enrol_status = (empty($record->enrol_status))
                                ? get_string('grouping_course_in_progress', $this->lang_file)
                                : get_string('grouping_course_complete', $this->lang_file);

        // Default values for custom fields IF not set
        foreach ($this->field_default as $key => $value) {
            //error_log("ICPR:transform_record(), checking default for {$key} => {$value}");
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }

        return $record;
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     * and php_report:view capability
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        //Check for report view capability
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        $this->require_dependencies();
        // make sure the current user has the capability for SOME user
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        if (!$contexts->is_empty()) {
            return true;
        }

        // Since user is logged-in AND HAVE VALID PM/CM userid, then they should
        // always be able to see their own courses/classes, but NOT schedule
        if ($this->execution_mode != php_report::EXECUTION_MODE_SCHEDULED
            && cm_get_crlmuserid($this->userid)) {
            return true;
        }

        return false;
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

