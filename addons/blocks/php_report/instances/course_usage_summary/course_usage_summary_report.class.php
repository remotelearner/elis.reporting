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
 * @subpackage pm-blocks-phpreport-course_usage_summary
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/icon_config_report.class.php');

class course_usage_summary_report extends icon_config_report {

    /**
     * Date filter start and end dates
     * populated using: get_datefilter_values()
     */
    var $startdate = 0;
    var $enddate = 0;
    var $filter_statement = '';

    // TBD
    var $permissions_filter = null;
    var $permissions_array = array();

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
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumstudent.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');

        //needed for options filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/checkboxes.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/date.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/selectany.php');

    }

    /**
     * Specifies available report filters
     * (empty by default but can be implemented by child class)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     * @uses    $DB
     * @return  array                The list of available filters
     */
    function get_filters($init_data = true) {
        global $DB;
        //Get allowed curriculum list by capability
        $cms = array();
        $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        $cms_objects = curriculum_get_listing_recordset('name', 'ASC', 0, 0, '', '', $contexts);
        if (!empty($cms_objects)) {
            foreach ($cms_objects as $curriculum) {
                $cms[$curriculum->id] = $curriculum->name;
            }
            $cms_objects->close();
        }

        $curricula_options = array('choices' => $cms, 'numeric' => false);

        //Create optional icon checkbox elements
        // 15 character name max length - with a 2 character field name - this is for any checkboxes filter
        $option_choices = array(
                         'tot_assignments' => get_string('option_tot_assignments', 'rlreport_course_usage_summary'),
                         'tot_crs_rscs' => get_string('option_tot_crs_rscs', 'rlreport_course_usage_summary'),
                         'tot_disc_posts' => get_string('option_tot_disc_posts', 'rlreport_course_usage_summary'),
                         'tot_quizzes' => get_string('option_tot_quizzes', 'rlreport_course_usage_summary'),
                         'avg_crs_grd' => get_string('option_avg_crs_grd', 'rlreport_course_usage_summary'),
                         'avg_hours_crs' => get_string('option_avg_hours_crs', 'rlreport_course_usage_summary'),
                         'avg_pretest' => get_string('option_avg_pretest', 'rlreport_course_usage_summary'),
                         'avg_posttest' => get_string('option_avg_posttest', 'rlreport_course_usage_summary')
                         );

        $option_defaults = array(
                         'tot_assignments', 'tot_crs_rscs', 'tot_disc_posts', 'tot_quizzes',
                         'avg_crs_grd', 'avg_hours_crs', 'avg_pretest', 'avg_posttest'
                         );

        $option_options = array('choices'  => $option_choices,
                                'checked'  => $option_defaults,
                                'nofilter' => true, // TBV
                                'heading'  => get_string('filter_options_header', 'rlreport_course_usage_summary'),
                                'footer'   => ''
                            );

        $filter_entries = array();
        $filter_entries[] = new generalized_filter_entry('cc', 'cc', 'id', get_string('filter_program', 'rlreport_course_usage_summary'), false, 'selectany', $curricula_options);
        $this->checkboxes_filter = new generalized_filter_entry('oe', 'oe', 'id', '', false, 'checkboxes', $option_options);
        $filter_entries[] = $this->checkboxes_filter;
        $filter_entries[] = new generalized_filter_entry('enrol', 'enrol', 'enrolmenttime', get_string('filter_course_date', 'rlreport_course_usage_summary'), false, 'date');
        $filter_entries[] = new generalized_filter_entry('enrol', 'enrol', 'enrolmenttime', get_string('filter_course_date', 'rlreport_course_usage_summary'), false, 'date');

        return $filter_entries;
    }

    /**
     * Specifies the list of filters that should NOT be applied to this field
     *
     * @param   string        $data_shortname  Shortname of the icon we are checking filters for
     *
     * @return  string array                   The list of shortnames of filters that should be ignored
     */
    function get_filter_exceptions($data_shortname=null) {
        return array("oe");
    }

    /**
     * Defines the course-oriented measures in the report
     *
     * @return   icon_report_entry array  A mapping of field names to report entries
     */
    function get_default_data() {
        //Set up report filter values for this report
        // as we are not using the usual get_sql_filter
        $this->get_filter_date_values();
        $this->get_filter_values();

        //Initialise the view permissions
        unset($this->needs_permission);
        unset($this->permissions_filter); // TBV

        $data = array();

        //number of courses that are incomplete or in progress
        $data['tot_crs_prog'] = new icon_report_entry(
                                        get_string('column_tot_crs_prog', 'rlreport_course_usage_summary'),
                                        0, 'in_progress');

        //number of courses that are completed or passed
        $data['tot_crs_comp'] = new icon_report_entry(
                                        get_string('column_tot_crs_comp', 'rlreport_course_usage_summary'),
                                        0, 'completed');

        //count of resource accessed
        $data['tot_crs_rscs'] = new icon_report_entry(
                                        get_string('column_tot_crs_rscs', 'rlreport_course_usage_summary'),
                                        0, 'resources');

        //count of discussion posts
        $data['tot_disc_posts'] = new icon_report_entry(
                                          get_string('column_tot_disc_posts', 'rlreport_course_usage_summary'),
                                          0, 'discussions');

        //count of quizzes graded *NEW
        $data['tot_quizzes'] = new icon_report_entry(
                                       get_string('column_tot_quizzes', 'rlreport_course_usage_summary'),
                                       0, 'num_quizzes');

        //count of assignments graded *NEW
        $data['tot_assignments'] = new icon_report_entry(
                                           get_string('column_tot_assignments', 'rlreport_course_usage_summary'),
                                           0, 'num_assignments');

        //average pretest score
        $data['avg_pretest']  = new icon_report_entry(
                                        get_string('column_avg_pretest', 'rlreport_course_usage_summary'),
                                        0, 'ave_pretest');

        //average posttest score
        $data['avg_posttest'] = new icon_report_entry(
                                        get_string('column_avg_posttest', 'rlreport_course_usage_summary'),
                                        0, 'ave_posttest');

        //average hours spent in courses *NEW
        $data['avg_hours_crs'] = new icon_report_entry(
                                         get_string('column_avg_hours_crs', 'rlreport_course_usage_summary'),
                                         0, 'avg_hours');

        //average course grades *NEW
        $data['avg_crs_grd']  = new icon_report_entry(
                                        get_string('column_avg_crs_grd', 'rlreport_course_usage_summary'),
                                        0, 'ave_grade');

        //count of students *NEW
        $data['num_students'] = new icon_report_entry(
                                        get_string('column_tot_students', 'rlreport_course_usage_summary'),
                                        0, 'num_students');

        return $data;
    }

    /**
     * Obtain the count of in-progress courses/enrolments
     *
     * @return  string                 The count of in-progress courses
     */
    function get_tot_crs_prog() {
        return $this->get_num_courses(STUSTATUS_NOTCOMPLETE);
    }

    /**
     * Obtain the count of completed courses/enrolments
     *
     * @return  string                 The count of completed courses
     */
    function get_tot_crs_comp() {
        return $this->get_num_courses(STUSTATUS_PASSED);
    }

    /**
     * Returns the number of courses in whatever status was specified by the status parameter
     *
     * @param   int     status  The path to the dynamic report-handling ajax script
     * @uses    $DB
     * @return  string         the number of courses (enrolments) for the specified status
     */
    function get_num_courses($status) {
        global $DB;
        $params = array();
        //gets the enrolments by status
        $sql = 'SELECT COUNT(enrol.id) FROM {'. student::TABLE .'} enrol
               ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'JOIN {'. user::TABLE .'} usr
                     ON usr.id = enrol.userid
                    ';
        }

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }


        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= '
                     WHERE EXISTS (
                        SELECT *
                        FROM {'. student::TABLE .'} enrol2
                        JOIN {'. pmclass::TABLE .'} class
                          ON class.id = enrol2.classid
                        JOIN {'. curriculumcourse::TABLE .'} curcrs
                          ON curcrs.courseid = class.courseid
                        JOIN {'. curriculumstudent::TABLE ."} curass
                          ON curcrs.curriculumid = curass.curriculumid
                         AND curass.userid = enrol2.userid
                        WHERE enrol.id = enrol2.id
                        {$this->filter_statement}
                        {$permissions_filter}
                     )
                     AND enrol.completestatusid = {$status}";
        } else {
            $sql .= "
                     WHERE enrol.completestatusid = {$status}";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= '
                     AND usr.inactive = 0';
        }

        $num_courses = 0;
        if ($num_courses_record = $DB->get_field_sql($sql, $params)) {
            $num_courses = $num_courses_record;
        }
        return $num_courses;
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
     * Returns the count of CMS course resources accessed
     *
     * @uses    $DB
     * @return  string                 The count of CMS course resources accessed
     */
    function get_tot_crs_rscs() {
        global $DB;
        $params = array();
        //TODO: verify that this works for flexpage access where:
        //      viewing a resource on a flexpage counts the same as viewing it by clicking a link.

        //create an IN clause identifying modules that are considered resources
        //todo: use get_in_or_equal
        $modules = $this->get_resource_modules();
        $in = "IN ('".implode("', '", $modules)."')";

        $siteid = SITEID;

        //main query
        $sql = 'SELECT COUNT(DISTINCT log.id) AS numresources
                  FROM {log} log
                  JOIN {user} mdl_usr
                    ON log.userid = mdl_usr.id
                  JOIN {'. user::TABLE .'} usr
                    ON mdl_usr.idnumber = usr.idnumber
                  JOIN {'. classmoodlecourse::TABLE .'} clsmdl
                    ON log.course = clsmdl.moodlecourseid
                  JOIN {'. student::TABLE .'} enrol
                    ON enrol.classid = clsmdl.classid
                   AND enrol.userid = usr.id
               ';

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = ' AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        // Only add the where statement if filters were used
        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= 'WHERE EXISTS (
                        SELECT *
                        FROM {'. student::TABLE .'} enrol2
                        JOIN {'. pmclass::TABLE .'} class
                          ON class.id = enrol2.classid
                        JOIN {'. curriculumcourse::TABLE .'} curcrs
                          ON curcrs.courseid = class.courseid
                        JOIN {'. curriculumstudent::TABLE ."} curass
                          ON curcrs.curriculumid = curass.curriculumid
                         AND curass.userid = enrol2.userid
                        WHERE enrol2.id = enrol.id
                         {$this->filter_statement}
                         {$permissions_filter}
                     )
                     AND log.course != {$siteid}
                     AND log.module {$in}
                     AND log.action = 'view'
                    ";
        } else {
            $sql .= "WHERE log.course != {$siteid}
                       AND log.module {$in}
                       AND log.action = 'view'
                    ";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0';
        }

        $resource = 0;
        if ($resource_record = $DB->get_field_sql($sql, $params)) {
            $resource = $resource_record;
        }
        return $resource;
    }

    /**
     * Calculates the number of discussion posts
     *
     * @uses    $DB
     * @return  string                 Number of discussion posts
     */
    function get_tot_disc_posts() {
        global $DB;
        $params = array();

        //main query
        $sql = 'SELECT COUNT(DISTINCT post.id) AS numposts
                    FROM {'. student::TABLE .'} enrol
                    JOIN {'. classmoodlecourse::TABLE .'} clsmdl
                      ON enrol.classid = clsmdl.classid
                    JOIN {forum_discussions} disc
                      ON disc.course = clsmdl.moodlecourseid
                    JOIN {forum_posts} post
                      ON disc.id = post.discussion
                ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'JOIN {'. user::TABLE .'} usr
                       ON usr.id = enrol.userid
                    ';
        }

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = ' AND '.$this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        $where = array();
        if ($this->filter_statement || $this->need_permissions()) {
            $where[] = 'EXISTS (
                          SELECT *
                          FROM {'. student::TABLE .'} enrol2
                          JOIN {'. pmclass::TABLE .'} class
                            ON class.id = enrol2.classid
                          JOIN {'. curriculumcourse::TABLE .'} curcrs
                            ON curcrs.courseid = class.courseid
                          JOIN {'. curriculumstudent::TABLE ."} curass
                            ON curcrs.curriculumid = curass.curriculumid
                         WHERE curass.userid = enrol2.userid
                           AND enrol2.id = enrol.id
                           {$this->filter_statement}
                           {$permissions_filter}
                        ) ";
        }
        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $tot_disc_posts = 0;
        if ($tot_disc_posts_record = $DB->get_field_sql($sql, $params)) {
            $tot_disc_posts = $tot_disc_posts_record;
        }
        return $tot_disc_posts;
    }

    /**
     * Specifies the data representing the average pretest score per user/enrolment
     *
     * @return  numeric  The calculated value
     */
    function get_avg_pretest() {
        return $this->get_average_test_score('_elis_course_pretest');
    }

    /**
     * Specifies the data representing the average posttest score per user/enrolment
     *
     * @return  numeric  The calculated value
     */
    function get_avg_posttest() {
        return $this->get_average_test_score('_elis_course_posttest');
    }

    /**
     * Specifies the data representing the average test score per user
     *
     * @param   string   $field_shortname     field short name to be used in get_field request
     *
     * @return  numeric                       The calculated value
     */
    function get_average_test_score($field_shortname) {
        global $DB;
        $params = array();

        $result = get_string('na', 'rlreport_course_usage_summary');

        $avg_crs_grd = 0;

        //Get the field id of the field shortname to use in the data table
        if ($field_id = $DB->get_field('elis_field', 'id', array('shortname' => $field_shortname))) {

            $field = new field($field_id);
            $data_table = $field->data_table();

            //main query
            $sql = 'SELECT AVG(clsgrd.grade) AS score
                     FROM {'. $data_table ."} d
                     JOIN {context} ctxt
                       ON d.contextid = ctxt.id
                      AND ctxt.contextlevel = ".CONTEXT_ELIS_COURSE."
                     JOIN {". coursecompletion::TABLE .'} comp
                       ON d.data = comp.idnumber
                     JOIN {'. pmclass::TABLE .'} class
                       ON class.courseid = ctxt.instanceid
                     JOIN {'. student_grade::TABLE .'} clsgrd
                       ON clsgrd.classid = class.id
                          AND clsgrd.locked = 1
                          AND clsgrd.completionid = comp.id
                     JOIN {'. student::TABLE .'} enrol
                       ON enrol.classid = class.id
                          AND enrol.userid = clsgrd.userid
                    ';

            if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
                $sql .= 'JOIN {'. user::TABLE .'} usr
                           ON usr.id = enrol.userid
                        ';
            }

            //get permissions sql bit
            if ($this->need_permissions()) {
                $permissions_filter = 'AND '. $this->get_permissions($params);
            } else {
                $permissions_filter = '';
            }

            if ($this->filter_statement || $this->need_permissions()) {
                $sql .= '
                         WHERE EXISTS (SELECT *
                            FROM {'. student::TABLE .'} enrol2
                            JOIN {'. pmclass::TABLE .'} class
                              ON class.id = enrol2.classid
                            JOIN {'. curriculumcourse::TABLE .'} curcrs
                              ON curcrs.courseid = class.courseid
                            JOIN {'. curriculumstudent::TABLE ."} curass
                              ON curcrs.curriculumid = curass.curriculumid
                             AND curass.userid = enrol2.userid
                            WHERE enrol.id = enrol2.id
                              AND d.fieldid = {$field_id}
                             {$this->filter_statement}
                             {$permissions_filter}
                         ) ";
            } else {
                $sql .= "
                         WHERE d.fieldid = {$field_id}
                        ";
            }

            if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
                $sql .= 'AND usr.inactive = 0';
            }

            if ($avg_crs_grd_record = $DB->get_record_sql($sql, $params)) {
                $avg_crs_grd = $avg_crs_grd_record->score;
            }
        }

        //Format as a percentage
        return round($avg_crs_grd) .'%';
    }

    /**
     * Specifies the data representing the total quizzes graded
     *
     * @uses    $DB
     * @return  numeric                       The calculated value
     */
    function get_tot_quizzes() {
        global $DB;
        $params = array();

        //main query
        $sql = 'SELECT COUNT(gg.id) as tot_quizzes
                FROM {course} course
                JOIN {grade_items} gi
                  ON gi.courseid = course.id
                JOIN {grade_grades} gg
                  ON gg.itemid = gi.id
                JOIN {user} mdl_usr
                  on gg.userid = mdl_usr.id
                JOIN {'. user::TABLE .'} usr
                  on mdl_usr.idnumber = usr.idnumber
                JOIN {'. classmoodlecourse::TABLE .'} cls_mdl
                  ON cls_mdl.moodlecourseid = course.id
                JOIN {'. student::TABLE .'} enrol
                  ON enrol.classid = cls_mdl.classid
                 AND enrol.userid = usr.id
              ';

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= 'WHERE EXISTS (SELECT *
                         FROM {'. student::TABLE .'} enrol2
                         JOIN {'. pmclass::TABLE .'} class
                           ON enrol2.classid = class.id
                         JOIN {'. curriculumcourse::TABLE .'} curcrs
                           ON curcrs.courseid = class.courseid
                         JOIN {'. curriculumstudent::TABLE ."} curass
                           ON enrol2.userid = curass.userid
                          AND curcrs.curriculumid = curass.curriculumid
                        WHERE enrol.id = enrol2.id
                          {$this->filter_statement}
                          {$permissions_filter}
                     )
                     AND gi.itemtype = 'mod'
                     AND gi.itemmodule = 'quiz'
                     AND gg.finalgrade IS NOT NULL
                    ";
        } else {
            $sql .= "WHERE gi.itemtype = 'mod'
                       AND gi.itemmodule = 'quiz'
                       AND gg.finalgrade IS NOT NULL
                    ";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0';
        }

        $tot_quizzes = 0;
        if ($tot_quizzes_record = $DB->get_field_sql($sql, $params)) {
            $tot_quizzes = $tot_quizzes_record;
        }
        return $tot_quizzes;
    }

    /**
     * Specifies the data representing the total assignments graded
     *
     * @uses    $DB
     * @return  numeric                       The calculated value
     */
    function get_tot_assignments() {
        global $DB;
        $params = array();

        //main query
        $sql = 'SELECT COUNT(gg.id) as tot_assignments
                 FROM {course} course
                 JOIN {grade_items} gi
                   ON gi.courseid = course.id
                 JOIN {grade_grades} gg
                   ON gg.itemid = gi.id
                 JOIN {user} mdl_usr
                   ON gg.userid = mdl_usr.id
                 JOIN {'. user::TABLE .'} usr
                   ON mdl_usr.idnumber = usr.idnumber
                 JOIN {'. classmoodlecourse::TABLE .'} cls_mdl
                   ON cls_mdl.moodlecourseid = course.id
                 JOIN {'. student::TABLE .'} enrol
                   ON enrol.classid = cls_mdl.classid
                  AND enrol.userid = usr.id
               ';

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= 'WHERE EXISTS (SELECT *
                         FROM {'. student::TABLE .'} enrol2
                         JOIN {'. pmclass::TABLE .'} class
                           ON enrol2.classid = class.id
                         JOIN {'. curriculumcourse::TABLE .'} curcrs
                           ON curcrs.courseid = class.courseid
                         JOIN {'. curriculumstudent::TABLE ."} curass
                           ON enrol2.userid = curass.userid
                          AND curcrs.curriculumid = curass.curriculumid
                        WHERE enrol2.id = enrol.id
                          {$this->filter_statement}
                          {$permissions_filter}
                     )
                     AND gi.itemtype = 'mod'
                     AND gi.itemmodule = 'assignment'
                     AND gg.finalgrade IS NOT NULL
                    ";
        } else {
            $sql .= "WHERE gi.itemtype = 'mod'
                       AND gi.itemmodule = 'assignment'
                       AND gg.finalgrade IS NOT NULL
                    ";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0';
        }

        $tot_assignments = 0;
        if ($tot_assignments_record = $DB->get_field_sql($sql, $params)) {
            $tot_assignments = $tot_assignments_record;
        }
        return $tot_assignments;
    }

     /**
     * Determines the number of seconds of course-based user activity
     *
     * @return  numeric  The total number of seconds spent on the site in courses
     */
    function get_total_course_time() {
        global $DB;
        $params = array();
        $siteid = SITEID;

        //main query
        $sql = 'SELECT SUM(activity.duration) AS numsecs
                  FROM {etl_user_activity} activity
                  JOIN {user} mdl_usr
                    ON activity.userid = mdl_usr.id
                  JOIN {'. user::TABLE .'} usr
                    ON mdl_usr.idnumber = usr.idnumber
                  JOIN {'. classmoodlecourse::TABLE .'} cls_mdl
                    ON cls_mdl.moodlecourseid = activity.courseid
                  JOIN {'. student::TABLE .'} enrol
                    ON enrol.classid = cls_mdl.classid
                   AND enrol.userid = usr.id
               ';

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= 'WHERE EXISTS (SELECT *
                        FROM {'. student::TABLE .'} enrol2
                        JOIN {'. pmclass::TABLE .'} class
                          ON enrol2.classid = class.id
                        JOIN {'. curriculumcourse::TABLE .'} curcrs
                          ON curcrs.courseid = class.courseid
                        JOIN {'. curriculumstudent::TABLE ."} curass
                          ON enrol2.userid = curass.userid
                         AND curcrs.curriculumid = curass.curriculumid
                        WHERE enrol2.id = enrol.id
                         {$this->filter_statement}
                         {$permissions_filter}
                      )
                      AND activity.courseid != {$siteid}
                    ";
        } else {
            $sql .= "WHERE activity.courseid != {$siteid}
                    ";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0';
        }

        $total_course_time = 0;
        if ($total_course_time_record = $DB->get_record_sql($sql, $params)) {
            $total_course_time = $total_course_time_record->numsecs;
        }
        return $total_course_time;
    }

     /*
     * Gets the average course hours (total course time over total enrolments)
     *
     * @return string average course time in hours and minutes
     */
    function get_avg_hours_crs() {
        $hoursecs = HOURSECS;
        $minsecs = MINSECS;
        $total_course_time = $this->get_total_course_time();
        $total_enrolments = $this->get_num_students();

        //Calculate number of seconds per enrolment
        if ($total_enrolments > 0) {
            $average_seconds = round($total_course_time / $total_enrolments, 8);
        } else {
            $average_seconds = 0;
        }

        // Find integer hours
        $average_hours = floor($average_seconds / $hoursecs);

        //Convert the seconds into minutes and subract the number of hours (in minutes)
        $average_minutes = round($average_seconds/$minsecs,8)-($average_hours*$minsecs);

        //Format the output string - hours and minutes at least 2 characters
        $hours = str_pad((int)$average_hours, 2, "0", STR_PAD_LEFT);
        $minutes = str_pad((int)$average_minutes, 2, "0", STR_PAD_LEFT);

        $newtime = $hours .':'. $minutes;
        return $newtime;
    }

    /**
     * Specifies the data representing the average course grade
     * Using filter within the subquery, so returning a number
     *
     * @uses    $DB
     * @return  numeric                       The calculated value
     */
    function get_avg_crs_grd() {
        global $DB;
        $params = array();

        //main query
        $sql = 'SELECT AVG(enrol.grade) as avg_grade
                  FROM {'. student::TABLE .'} enrol
               ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'JOIN {'. user::TABLE .'} usr
                       ON usr.id = enrol.userid
                    ';
        }

        //get permissions sql bit
        if ($this->need_permissions()) {
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        if ($this->filter_statement || $this->need_permissions()) {
            $sql .= 'WHERE EXISTS (SELECT *
                        FROM {'. student::TABLE .'} enrol2
                        JOIN {'. pmclass::TABLE .'} class
                          ON enrol2.classid = class.id
                        JOIN {'. curriculumcourse::TABLE .'} curcrs
                          ON class.courseid = curcrs.courseid
                        JOIN {'. curriculumstudent::TABLE ."} curass
                          ON curass.userid = enrol2.userid
                         AND curass.curriculumid = curcrs.curriculumid
                       WHERE enrol.id = enrol2.id
                         {$this->filter_statement}
                         {$permissions_filter}
                      )
                      AND enrol.locked = '1'
                    ";
        } else {
            $sql .= "WHERE enrol.locked = '1'
                    ";
        }

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'AND usr.inactive = 0';
        }

        $avg_crs_grd = 0;
        if ($avg_crs_grd_record = $DB->get_record_sql($sql, $params)) {
            $avg_crs_grd = $avg_crs_grd_record->avg_grade;
        }

        //Format as a percentage
        return round($avg_crs_grd) .'%';
    }

    /**
     * Specifies the data representing the total number of students in classes,
     * actually number of enrolments in classes LINKED TO MOODLE COURSE!
     *
     * @uses    $DB
     * @return  numeric                       The calculated value
     */
    function get_num_students() {
        global $DB;
        $params = array();

        //main query
        $sql = 'SELECT COUNT(enrol.id) as num_students
                  FROM {'. student::TABLE .'} enrol
                  JOIN {'. classmoodlecourse::TABLE .'} clsm
                    ON enrol.classid = clsm.classid
               ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= 'JOIN {'. user::TABLE .'} usr
                       ON usr.id = enrol.userid
                    ';
        }

        //get permissions sql bit
        if ($this->need_permissions()) {
            //$sql .= " JOIN {$CURMAN->db->prefix_table(CURASSTABLE)} curass
            //            ON enrol.userid = curass.userid";
            //also pass the field and table to use
            //probably different for other queries
            $permissions_filter = 'AND '. $this->get_permissions($params);
        } else {
            $permissions_filter = '';
        }

        $where = array();
        if ($this->filter_statement || $this->need_permissions()) {
            $where[] = 'EXISTS (SELECT *
                            FROM {'. student::TABLE .'} enrol2
                            JOIN {'. pmclass::TABLE .'} class
                              ON class.id = enrol2.classid
                            JOIN {'. curriculumcourse::TABLE .'} curcrs
                              ON curcrs.courseid = class.courseid
                            JOIN {'. curriculumstudent::TABLE ."} curass
                              ON curass.userid = enrol2.userid
                             AND curass.curriculumid = curcrs.curriculumid
                           WHERE enrol2.id = enrol.id
                             {$this->filter_statement}
                             {$permissions_filter}
                        ) ";
        }
        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }
        if (!empty($where)) {
            $sql .= 'WHERE '. implode(' AND ', $where);
        }

        $num_students = 0;
        if ($num_students_record = $DB->get_record_sql($sql, $params)) {
            $num_students = $num_students_record->num_students;
        }
        return $num_students;
    }

    /*
     * Retrieve the curriculum filter value and generate a filter statement to be included in a WHERE statement
     *
     * @return boolean  true
     */
    function get_filter_values() {
        //Fetch selected curricula from filter
        $curricula = php_report_filtering_get_active_filter_values(
                         $this->get_report_shortname(), 'cc');

        $selected_curricula = '';
        //Set up empty filter statement
        $this->filter_statement = '';

        //Append partial AND statement for selected curricula
        if (!empty($curricula) && is_array($curricula)) {
            // Check for special ALL case
            if (is_numeric($curricula[0]['value']) && $curricula[0]['value'] == 0) {
                $this->filter_statement .= "
                         AND curcrs.curriculumid IS NOT NULL";
            } else {
                $count = 0;
                foreach ($curricula as $key => $value) {
                    $selected_curricula .= $value['value'];
                    if ($count > 0) {
                        $selected_curricula .= ', ';
                    }
                    $count++;
                }
                $this->filter_statement .= "
                         AND curcrs.curriculumid IN ({$selected_curricula})";
            }
        }

        //Append date filter pieces if required to filter statement
        if (!empty($this->startdate)) {
            $this->filter_statement .= "
                         AND enrol.enrolmenttime >= {$this->startdate}";
        }

        if (!empty($this->enddate)) {
            $this->filter_statement .= "
                         AND enrol.enrolmenttime <= {$this->enddate}";
        }

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

        $start_enabled =  php_report_filtering_get_active_filter_values(
                             $this->get_report_shortname(),
                             'enrol' . '_sck');
        $start = (!empty($start_enabled) && is_array($start_enabled)
                  && !empty($start_enabled[0]['value']))
                 ? php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(),
                       'enrol' . '_sdt')
                 : 0;

        $end_enabled = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           'enrol' . '_eck');
        $end = (!empty($end_enabled) && is_array($end_enabled)
                && !empty($end_enabled[0]['value']))
               ? php_report_filtering_get_active_filter_values(
                     $this->get_report_shortname(),
                     'enrol' . '_edt')
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
     * @param   $export_format  The desired export format for the headers
     * @return  array  A mapping of display names to values
     */
    function get_header_entries($export_format) {
        //Get a start and end date to display on the header entry
        $sdate = !empty($this->startdate)
                 ? $this->userdate($this->startdate, get_string('date_format', 'rlreport_course_usage_summary'))
                 : get_string('present', 'rlreport_course_usage_summary');

        $edate = !empty($this->enddate)
                 ? $this->userdate($this->enddate, get_string('date_format', 'rlreport_course_usage_summary'))
                 : get_string('present', 'rlreport_course_usage_summary');

        $header_obj = new stdClass;
        $header_obj->label = get_string('report_heading', 'rlreport_course_usage_summary');
        if (empty($this->startdate) && empty($this->enddate)) {
            $header_obj->value = '';
        } else {
            $header_obj->value = "{$sdate} - {$edate}";
        }
        $header_obj->css_identifier = '';

        return array($header_obj);
    }

    /**
     * Generate a sql query fragment to add to each query for the curricula permissions
     * iff the curricula filter is any or all
     */
    function need_permissions() {

        // Check to see if set and return it if true
        if (isset($this->needs_permission)) {
            return $this->needs_permission;
        }

        // Only generate if any/all were selected from the filter, in this case check for 0
        $search_string = get_string('curriculumidin','rlreport_course_usage_summary');
        if (!stristr($this->filter_statement, $search_string) &&
            !has_capability($this->access_capability,get_context_instance(CONTEXT_SYSTEM), $this->userid)) {
            $this->needs_permission = true;
            return true;
        } else {
            $this->needs_permission = false;
            return false;
        }


    }
    /**
     * Generate a sql query fragment to add to each query for the curricula permissions
     * iff the curricula filter is any or all
     * @param  array   parameter array to append to with any filter parameters
     */
    function get_permissions( &$params ) {
        // Check to see if set and return it if true
        if (isset($this->permissions_filter)) {
            $params += $this->permissions_array;
            return $this->permissions_filter;
        }

        // Only generate if any/all were selected from the filter, in this case check for 0
        $search_string = get_string('curriculumidin', 'rlreport_course_usage_summary');
        if (!stristr($this->filter_statement, $search_string)) {
            //obtain all course contexts where this user can view reports
            $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);

            //make sure we only include curricula within those contexts
            //$this->permissions_filter = $contexts->sql_filter_for_context_level('curass.curriculumid', 'curriculum');
            $filter_obj = $contexts->get_filter('curriculumid', 'curriculum');
            $filter_sql = $filter_obj->get_sql(false, 'curass', SQL_PARAMS_NAMED);

            $this->permissions_filter = null;
            $this->permissions_array = array();
            if (isset($filter_sql['where'])) {
                $this->permissions_filter = $filter_sql['where'];
                $this->permissions_array = $filter_sql['where_parameters'];
                $params += $this->permissions_array;
            }
        }
        return $this->permissions_filter;
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

        //make sure the current user can view reports in at least one curriculum context
        $contexts = get_contexts_by_capability_for_user('curriculum', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

}

