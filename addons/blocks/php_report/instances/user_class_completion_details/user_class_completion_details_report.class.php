<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    php_reports
 * @subpackage user_class_completion_details
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot .'/blocks/php_report/instances/user_class_completion/user_class_completion_report.class.php');

/**
 * User class completion details report
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */
class user_class_completion_details_report extends user_class_completion_report {
    public $reportname   = 'user_class_completion_details';
    public $parentname   = 'user_class_completion';

    //todo: remove these and use parameter data to figure out which fields to show
    //class/curriculum-specific fields (table body)
    protected $_show_classrole = true;

    //user-specific header (report headers)
    protected $_userfieldids = array();

    public $languagefile = 'rlreport_user_class_completion_details';
    public $parent_langfile = 'rlreport_user_class_completion';

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_* constants)
     */
    function get_category() {
        return self::CATEGORY_NODISPLAY;
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        $result = array();
        $optionalcols = array(
            'cur_name'   => array('name' => 'curriculumname', 'id' => 'cur.name'),
            'class_role' => array('name' => 'classrole',      'id' => '\'\' AS classrole'),
        );

        //make sure the session is updated with URL parameters
        php_report_filtering_get_user_preferences($this->get_report_shortname());

        $filters = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                       'filter-detailcolumns', $this->filter);
        $cols = $filters[0]['value'];

        // Curriculum name
        if ($cols['cur_name']) {
            $curriculumname_heading = get_string('column_curriculumname', $this->languagefile);
            $curriculumname_column = new table_report_column('cur.name AS curriculumname', $curriculumname_heading, 'curriculumname');
            $result[] = $curriculumname_column;
            unset($optionalcols['cur_name']);
        }

        // Add curriculum custom fields
        $curriculum_custom_field_columns = $this->get_custom_field_columns($cols, 'curriculum');
        $result = array_merge($result, $curriculum_custom_field_columns);

        // Course name
        $coursename_heading = get_string('column_coursename', $this->languagefile);
        $coursename_column = new table_report_column('crs.name AS coursename', $coursename_heading, 'coursename');
        $result[] = $coursename_column;
        // Add Course custom fields
        $course_custom_field_columns = $this->get_custom_field_columns($cols, 'course');
        $result = array_merge($result, $course_custom_field_columns);

        // Class idnumber
        $classidnumber_heading = get_string('column_classidnumber', $this->languagefile);
        $classidnumber_column = new table_report_column('cls.idnumber AS classidnumber', $classidnumber_heading, 'classidnumber');
        $result[] = $classidnumber_column;
        // Add Class custom fields
        $class_custom_field_columns = $this->get_custom_field_columns($cols, 'class');
        $result = array_merge($result, $class_custom_field_columns);

        // Environment name
        $environment_heading = get_string('column_environment', $this->languagefile);
        $environment_column = new table_report_column('env.name AS envname', $environment_heading, 'environment');
        $result[] = $environment_column;

        // Class startdate
        $classstartdate_heading = get_string('column_classstartdate', $this->languagefile);
        $classstartdate_column = new table_report_column('cls.startdate', $classstartdate_heading, 'classstartdate');
        $result[] = $classstartdate_column;

        // Completion elements
        $completionelements_heading = get_string('column_completionelements', $this->languagefile);
        $completionelements_column = new table_report_column('\'\' AS elementsdisplayed', $completionelements_heading, 'completionelements');
        $result[] = $completionelements_column;

        // Completion status
        $completion_heading = get_string('column_completion', $this->languagefile);
        $completion_column = new table_report_column('0 AS completionstatus', $completion_heading, 'completion');
        $result[] = $completion_column;

        // Completion date
        $completiondate_heading = get_string('column_completiondate', $this->languagefile);
        $completiondate_column = new table_report_column('completetime', $completiondate_heading, 'completiondate');
        $result[] = $completiondate_column;

        // Credits
        $credits_heading = get_string('column_credits', $this->languagefile);
        $credits_column = new table_report_column('0 AS creditsdisplayed', $credits_heading, 'credits');
        $result[] = $credits_column;

        foreach ($optionalcols as $key => $col) {
            if (! empty($cols[$key])) {
                $heading = get_string('column_'. $col['name'], $this->languagefile);
                $column  = new table_report_column($col['id'], $heading, $col['name']);
                $result[] = $column;
            }
        }

        return $result;
    }

    /**
     * Require any code that this report needs
     *
     * (only called after is_available returns true)
     *
     * @uses $CFG
     */
    function require_dependencies() {
        global $CFG;

        // Needed to reference curriculum entities
        require_once($CFG->dirroot .'/elis/program/lib/setup.php');
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        // Needed for constants that define db tables
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/instructor.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculum.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumstudent.class.php');

        // ELIS pages required for links
        require_once($CFG->dirroot .'/elis/program/coursepage.class.php');
        require_once($CFG->dirroot .'/elis/program/curriculumpage.class.php');
        require_once($CFG->dirroot .'/elis/program/pmclasspage.class.php');

        parent::require_dependencies();
    }


    /**
     * Specifies an SQL statement that will produce the required report
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  string            The report's main sql statement
     */
    function get_report_sql($columns) {
        $params = array();

        //dependencies
        //$this->require_dependencies();

        //obtain an sql clause that filters out users you shouldn't see
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        //$permissions_filter = $contexts->sql_filter_for_context_level('u.id', 'user');
        $filter_obj = $contexts->get_filter('id', 'user');

        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter1 = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter1 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter2 = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter2 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }

        $curriculum_custom_field_join = '';
        $class_custom_field_join = '';
        //determine the join SQL for all types of relevant custom fields
        if (!empty($this->_customfieldids)) {
            // For the program portion, only care about program-level fields
            $instancefields = array(
                'curriculum' => 'cur.id',
            );

            $curriculum_custom_field_join = $this->get_custom_field_sql($this->_customfieldids, $instancefields);

            // For the class instance portion, we consider class instance and
            // course description custom fields
            $instancefields = array(
                'class'  => 'cls.id',
                'course' => 'crs.id'
            );

            $class_custom_field_join = $this->get_custom_field_sql($this->_customfieldids, $instancefields);
        }

        //handle class enrolment status
        $parent_shortname = $this->parentname;
        if ($status_param = php_report_filtering_get_active_filter_values($parent_shortname, 'filter-completionstatus', $this->filter)) {
            $status = $status_param[0]['value'];
        }

        //sql fragments for the student and instructor
        $student_status_sql = '';
        $instructor_status_sql = '';

        //store in variables for ease of use in SQL fragments
        $passed_status = STUSTATUS_PASSED;
        $failed_status = STUSTATUS_FAILED;
        $notcomplete_status = STUSTATUS_NOTCOMPLETE;
        $notstarted_status = self::STUSTATUS_NOTSTARTED;

        $access_sql = $this->get_access_sql();

        if (isset($status)) {
            $student_status_sql = $this->get_class_status_sql($status);

            //never include instructors when some status is selected
            $instructor_status_sql = 'AND FALSE';
        }

        //case statement for figuring out the real status
        $status_case = "CASE WHEN stu.completestatusid = {$passed_status}
                               THEN {$passed_status}
                             WHEN stu.completestatusid = {$failed_status}
                               THEN {$failed_status}
                             WHEN {$access_sql}
                               THEN {$notcomplete_status}
                             ELSE
                             {$notstarted_status}
                        END AS completestatusid";

        //dynamically handle the class start/end date condition
        $startdate_condition = $this->get_class_startdate_condition($parent_shortname);

        // Check if the report has curriclum standard/custom fields included
        $filters = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                       'filter-detailcolumns', $this->filter);

        $has_fields = $this->custom_fields_included($filters);

        $curriculum_select  = '';
        $curriculum_join    = '';

        if ($has_fields) {
            $curriculum_select = ' cur.id AS curid,';
            $curriculum_join   = ' LEFT JOIN (
                                      {'. curriculumcourse::TABLE .'} curcrs
                                      JOIN {'. curriculum::TABLE ."} cur
                                        ON curcrs.curriculumid = cur.id
                                      {$curriculum_custom_field_join}
                                      JOIN {". curriculumstudent::TABLE ."} curass
                                        ON curass.curriculumid = cur.id)
                                   ON crs.id = curcrs.courseid
                                  AND curass.userid = u.id
                                  {$class_custom_field_join} ";
        }

        //obtain SQL conditions related to the parent report's curriculum-course-class filter
        list($ccc_conditions1, $ccc_params) = $this->get_ccc_conditions($has_fields);
        if (!empty($ccc_conditions1)) {
            $params = array_merge($params, $ccc_params);
        }
        list($ccc_conditions2, $ccc_params) = $this->get_ccc_conditions($has_fields);
        if (!empty($ccc_conditions2)) {
            $params = array_merge($params, $ccc_params);
        }

        //set up SQL fragments that will enforce showing only users in particular curricula
        //if necessary, based on filters
        $student_curriculum_join = '';
        $instructor_curriculum_where_condition = '';

        if ($this->is_filtering_by_curricula()) {
            ///force curriculum assignment if showing curricula
            //(otherwise handled by get_ccc_conditions with an EXISTS clause)
            if ($has_fields) {
                //note: get_ccc_conditions will provide an appropriate condition on the curriculum id
                //in this case (when curriculum information is displayed)
                $student_curriculum_join = 'JOIN {'. curriculumstudent::TABLE .'} curstu
                                              ON cur.id = curstu.curriculumid
                                             AND u.id = curstu.userid';
            }

            $instructor_curriculum_where_condition = 'AND FALSE';
        }

        $total_elements_sql = 'SELECT count(*)
                                 FROM {'. coursecompletion::TABLE .'} course_completion
                                WHERE crs.id = course_completion.courseid';

        $elements_passed_sql = 'SELECT COUNT(*) '. $this->get_elements_sql();

        //this query uses the wildcard for parameter replacement so that filters apply to both parts
        //of the union
        $sql = "SELECT {$columns},
                       {$status_case},
                       stu.credits,
                       ({$elements_passed_sql}) AS elementspassed,
                       ({$total_elements_sql}) as totalelements,
                       0 AS isinstructor,
                       -- fields for headers
                       u.lastname,
                       u.firstname,
                       u.address,
                       u.address2,
                       u.city,
                       u.state,
                       u.email,
                       -- fields for page links
                       {$curriculum_select}
                       crs.id AS courseid,
                       cls.id AS classid
                  FROM {". user::TABLE .'} u
                  JOIN {'. student::TABLE .'} stu
                    ON u.id = stu.userid
                  JOIN {'. pmclass::TABLE .'} cls
                    ON stu.classid = cls.id
                  JOIN {'. course::TABLE ."} crs
                    ON cls.courseid = crs.id
             LEFT JOIN {crlm_environment} env
                    ON crs.environmentid = env.id
                 {$curriculum_join}
                 {$student_curriculum_join}
                 WHERE ". table_report::PARAMETER_TOKEN ."
                   AND {$permissions_filter1}
                 {$student_status_sql}
                   AND {$startdate_condition}
                   AND {$ccc_conditions1}

                UNION

                SELECT {$columns},
                       0 AS completestatusid,
                       0 AS credits,
                       0 AS elementspassed,
                       0 AS totalelements,
                       1 AS isinstructor,
                       -- fields for headers
                       u.lastname,
                       u.firstname,
                       u.address,
                       u.address2,
                       u.city,
                       u.state,
                       u.email,
                       -- fields for page links
                       {$curriculum_select}
                       crs.id AS courseid,
                       cls.id AS classid
                  FROM {". user::TABLE .'} u
                  JOIN {'. instructor::TABLE .'} inst
                    ON u.id = inst.userid
                  JOIN {'. pmclass::TABLE .'} cls
                    ON inst.classid = cls.id
                  JOIN {'. course::TABLE ."} crs
                    ON cls.courseid = crs.id
             LEFT JOIN {crlm_environment} env
                    ON crs.environmentid = env.id
                 {$curriculum_join}
                 WHERE ". table_report::PARAMETER_TOKEN ."
                   AND {$permissions_filter2}
                 {$instructor_status_sql}
                   AND {$startdate_condition}
                   AND {$ccc_conditions2}
                 {$instructor_curriculum_where_condition}";

        return array($sql, $params);
    }

    /**
     * Specifies whether we're currently filtering on curricula via the curriculum-
     * course-class filter
     * @return boolean true if filtering by curriculum id, false otherwise
     */
    function is_filtering_by_curricula() {
        $values = php_report_filtering_get_active_filter_values($this->parentname, 'filter-ccc-curriculum-name', $this->filter);
        return !empty($values[0]['value']);
    }

    /**
     * Obtains the parameter data corresponding to a particular curriculum customfield
     * @param $id The curriculum customfield id
     * @return object An object with the appropriate fields set
     */
    function get_curr_customfield_data($id) {
        global $SESSION;

        $data = new stdClass;
        $elementid = 'php_report_user_class_completion/filter-ccc-curriculum-customfield-'.$id;
        foreach ($SESSION->php_report_default_params as $key => $value) {
            if ($key == $elementid || strpos($key, "{$elementid}_") === 0) {
                $pos = strpos($key, '/');
                $newkey = substr($key, $pos+1);
                $data->$key = $value;
            }
        }

        return $data;
    }

    /**
     * Obtains a complete filter object representing a a curriculum customfield
     * @param $showing_curricula True if we are showing curriculum information on the report,
     *                           false otherwise
     * @param $curriculumfield   The object representing the appropriate curriculum field
     * @param $id                The curriculum field id
     */
    function get_curr_customfield_filter($showing_curricula, $curriculumfield, $id) {
        global $CFG;

        //obtain field information
        $elementid = 'php_report_user_class_completion/filter-ccc-curriculum-customfield-'.$id;
        $field = new field($curriculumfield);
        $owners = $field->owners;
        $manual = $owners['manual'];
        $params = unserialize($manual->params);

        //options initialization
        $options = array();
        $yesno = array(1 => get_string('yes'), 0 => get_string('no'));

        //set up options based on the UI control type
        switch ($params['control']) {
            case 'datetime': // TBD: handle options for datetime fields
                // start year, stop year, timezone ...
                break;

            case 'checkbox':
                $options['choices'] = $yesno;
                break;

            case 'menu':
                if (empty($params['options_source'])) {
                    if (! empty($params['options'])) {
                        $choices = explode("\n", $params['options']);
                        foreach ($choices as $key => $choice) {
                            $options['choices'] = trim($choice);
                        }
                    } else {
                        $options['choices'] = $yesno;
                    }
                }
                break;

            case 'text':
                // fall-thru case!
            case 'textarea':
                // no options required for text fields
                break;

            default:
                // nothing to do here
                break;
        }

        //set up query parameters
        $options['datatype'] = $curriculumfield->datatype;
        $options['subqueryprefix'] = 'EXISTS';
        $options['fieldid'] = $curriculumfield->id;
        if ($showing_curricula) {
            $options['extraconditions'] = 'AND crs.id = ccc.courseid
                                           AND cur.id = ccc.curriculumid';
        } else {
            $options['extraconditions'] = 'AND crs.id = ccc.courseid';
        }

        //curriculum customfield filter condition, linking users to to curricula based on
        //curriculum assignments and curriculum-course associations
        $options['wrapper'] = ' INNER JOIN {'. curriculumcourse::TABLE .'} ccc
                                        ON c.instanceid = ccc.curriculumid';
        //tell the filter that we're operating on the curriculum level
        $options['contextlevel'] = CONTEXT_ELIS_PROGRAM;

        //attempt to retrieve the appropriate filter object
        $filter_object = false;

        if ($params['control'] == 'text' || $params['control'] == 'textarea') {
            $filter_object = new generalized_filter_custom_field_text($elementid, 'cur', 'id', '', false, 'u.id', $options);
        } else if ($params['control'] == 'checkbox' || $params['control'] == 'menu') {
            $filter_object = new generalized_filter_custom_field_select($elementid, 'cur', 'id', '', false, 'u.id', $options);
        }

        return $filter_object;
    }

    /**
     * Retrieves a complete SQL condition related to a particular curriculum custom field
     *
     * @param $showing_curricula True if we are showing curriculum information on the report,
     *                           false otherwise
     * @param $id                The custom field id
     * @param $curriculumfield   The custom field object
     * @return mixed             Either an array of the appropriate SQL & params
     *                           or false
     */
    function get_curr_customfield_condition($showing_curricula, $id, $curriculumfield) {
        //set up data for the current field
        $data = $this->get_curr_customfield_data($id);

        //obtain the SQL fragment if possible
        $condition = false;
        if ($filter_object = $this->get_curr_customfield_filter($showing_curricula, $curriculumfield, $id)) {
            if ($data = $filter_object->check_data($data)) {
                $sql = $filter_object->get_sql_filter($data);
                if (!empty($sql) && !empty($sql[0])) {
                    $condition = $sql;
                }
            }
        }

        return $condition; // this is either false OR an array!
    }

    /**
     * Retrieves the complete set of SQL conditions related to curriculum custom fields
     *
     * @param boolean $showing_curricula True if we are showing curriculum information on the
     *                                   report, false otherwise
     * @return array The complete collection of conditions related to curriculum custom fields & params
     */
    function get_curr_customfield_conditions($showing_curricula) {
        $params = array();
        //our return value
        $conditions = array();

        //set up the context level for dealing with curriculum custom fields
        $ctxtlvl = CONTEXT_ELIS_PROGRAM;
        $curriculumfields = field::get_for_context_level($ctxtlvl);

        //iterate through all curriculum custom fields
        foreach ($curriculumfields as $id => $curriculumfield) {
            if ($condition = $this->get_curr_customfield_condition($showing_curricula, $id, $curriculumfield)) {
                $conditions[] = $condition[0];
                $params = array_merge($params, $condition[1]);
            }
        }

        return array($conditions, $params);
    }

    /**
     * Obtains an SQL string containing all conditions relevant to the curriculum-
     * course-class filter ANDed together
     *
     * @param boolean $showing_curricula true if the report is showing any curriculum
     *                                   field, otherwise false
     *
     * @return string The master SQL condition related to the curriculum-course-class
     *                filter
     */
    function get_ccc_conditions($showing_curricula) {
        $params = array();
        //maps filter shortnames to database field ids
        $filter_to_field_map = array('filter-ccc-course-name'    => 'crs.id',
                                     'filter-ccc-class-idnumber' => 'cls.id'
                                    );

        //go through each filter and obtain the simple condition
        $conditions = array();
        foreach ($filter_to_field_map as $filtername => $dbfield) {
            if ($condition = $this->get_ccc_select_clause($filtername, $dbfield)) {
                $conditions[] = $condition;
            }
        }

        //determine an appropriate condition related to curricula
        if ($showing_curricula) {
            //if we are showing curricula, we can just use a simple IN clause
            if ($condition = $this->get_ccc_select_clause('filter-ccc-curriculum-name', 'cur.id')) {
                $conditions[] = $condition;
            }
        } else {
            //we are not showing anything related to curricula, so use an exists clause
            if ($curriculum_exists_clause = $this->get_curriculum_exists_clause()) {
                $conditions[] = $curriculum_exists_clause;
            }
        }

        //merge in any conditions related to curriculum customfields
        list($curr_customfield_conditions,
             $curr_customfield_params) = $this->get_curr_customfield_conditions($showing_curricula);
        $conditions = array_merge($conditions, $curr_customfield_conditions);
        $params = array_merge($params, $curr_customfield_params);

        //combine into a master condition
        $ccc_conditions = 'TRUE';
        if (!empty($conditions)) {
            $ccc_conditions = implode(' AND ', $conditions);
        }

        return array($ccc_conditions, $params);
    }

    /**
     * Obtains an SQL piece corresponding to a single component of the curriculum-course-
     * class filter
     *
     * @param $filtername The shortname of the appropriate element
     * @param $dbfield The database field we are filtering on
     * @return string The simple SQL condition, or false if none
     */
    function get_ccc_select_clause($filtername, $dbfield) {
        //obtain raw values from the session / schedule
        $values = php_report_filtering_get_active_filter_values($this->parentname, $filtername, $this->filter);

        if (!empty($values[0]['value'])) {
            //values found, so create simple in clause
            $ids = $values[0]['value'];
            return "{$dbfield} IN (". implode(',', $ids) .')';
        } else {
            //no values found
            return false;
        }
    }

    /**
     * Returns an exists clause that asserts that the current combination of
     * user and course are both associated to an identical curriculm that we're fltering on
     *
     * @return string The approrpriate EXISTS clause, or false if filter is inactive
     */
    function get_curriculum_exists_clause() {
		global $CURMAN;

        //check for the appropriate submitted parameter value(s)
        $values = php_report_filtering_get_active_filter_values($this->parentname, 'filter-ccc-curriculum-name', $this->filter);

        if (!empty($values[0]['value'])) {
            //we are filtering on curricula, so return the appropriat eexists clause
            $cur_id_list = implode(',', $values[0]['value']);

            //we want to make sure the current row's course is assigned to one of the
            //appropriate curricula and also that the current user is a member of that
            //same curriculum
            return 'EXISTS (SELECT *
                              FROM {'. curriculumstudent::TABLE .'} curstu
                              JOIN {'. curriculumcourse::TABLE ."} curcrs
                                ON curstu.curriculumid = curcrs.curriculumid
                             WHERE u.id = curstu.userid
                               AND crs.id = curcrs.courseid
                               AND curcrs.curriculumid IN ({$cur_id_list}))";
        }

        //not filtering
        return false;
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

        //add entity links
        if ($export_format == table_report::$EXPORT_FORMAT_HTML) {
            //link curriculum name to its "view" page if the the current record has a curriculum
            if (!empty($record->curid) && isset($record->curriculumname)) {
                $page = new curriculumpage(array('id'     => $record->curid,
                                                 'action' => 'view'));
                if ($page->can_do()) {
                    $record->curriculumname = '<span class="external_report_link"><a href="'.
                        $page->url .'" target="_blank">'. $record->curriculumname
                        .'</a></span>';
                }
            } else {
                //non-curriculum course, so use status string with no link
                $record->curriculumname = get_string('noncurriculumcourse', $this->languagefile);
            }

            //link course name to its "view" page
            $page = new coursepage(array('id'     => $record->courseid,
                                         'action' => 'view'));
            if ($page->can_do()) {
                $record->coursename = '<span class="external_report_link"><a href="'.
                    $page->url .'" target="_blank">'. $record->coursename
                    .'</a></span>';
            }

            //link class name to its "view" page
            $page = new pmclasspage(array('id' => $record->classid,
                                          'action' => 'view'));
            if ($page->can_do()) {
                $record->classidnumber = '<span class="external_report_link"><a href="'.
                    $page->url .'" target="_blank">'. $record->classidnumber
                    .'</a></span>';
            }
        }

        //show environment as N/A if not set
        if (empty($record->envname)) {
            $record->envname = get_string('na', $this->languagefile);
        }

        //format the start date
        $record->startdate = $this->format_date($record->startdate);

        //show number of passed and total number of completion elements
        $record->elementsdisplayed = get_string('compelements_passed', $this->languagefile, $record);

        //convert status id to a display string
        if ($record->completestatusid == STUSTATUS_PASSED) {
            $record->completionstatus = get_string('status_passed', $this->languagefile);
        } else if ($record->completestatusid == STUSTATUS_FAILED) {
            $record->completionstatus = get_string('status_failed', $this->languagefile);
        } else if ($record->completestatusid == STUSTATUS_NOTCOMPLETE) {
            $record->completionstatus = get_string('status_notcomplete', $this->languagefile);
        } else {
            $record->completionstatus = get_string('status_notstarted', $this->languagefile);
        }

        //if not passed, shouldn't have any credits
        if ($record->completestatusid != STUSTATUS_PASSED) {
            $record->credits = 0;
        }

        //copy result of complex sub-query into simple field
        $record->creditsdisplayed = $this->format_credits($record->credits);

        //format the completion time
        if ($record->completestatusid == STUSTATUS_NOTCOMPLETE) {
            //not complete, so don't show a completion time
            $record->completetime = '0';
        }

        //only show a completion time if passed
        if ($record->completestatusid == STUSTATUS_PASSED ||
            $record->completestatusid == STUSTATUS_FAILED) {
            $record->completetime = $this->format_date($record->completetime);
        } else {
            $record->completetime = get_string('na', $this->languagefile);
        }

        //display whether the current record is a student or an instructor assignment
        if ($record->isinstructor) {
            $record->classrole = get_string('instructor', $this->languagefile);
        } else {
            $record->classrole = get_string('student', $this->languagefile);
        }

        //handle custom field default values and display logic
        $this->transform_custom_field_data($record);

        return $record;
    }

    function get_grouping_fields() {
        $user_grouping = new table_report_grouping('userid', 'u.id', '', 'ASC',
                                 array(), 'above', 'lastname, firstname, userid');
        return array($user_grouping);
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
        //TBD: dependencies for custom fields
        $labels = array();

        //user fullname
        if ($export_format == table_report::$EXPORT_FORMAT_HTML) {
            //label for this grouping element
            $text_label = get_string('grouping_name', $this->languagefile);
        } else {
            //label for all groupings
            $text_label = get_string('grouping_name_csv', $this->languagefile);
        }
        $fullname_text = fullname($datum);
        $labels[] = $this->add_grouping_header($text_label, $fullname_text, $export_format);

        $filters = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                       'filter-detailheaders', $this->filter);

        $headers = $filters[0]['value'];
        $this->_userfieldids = array();
        if (!empty($headers)) {
            foreach ($headers as $field => $active) {
                if ($active && (substr($field, 0, 7) == 'custom_')) {
                    $fieldid = substr($field, 7);
                    $this->_userfieldids[] = $fieldid;
                }
            }
        }

        //configured user custom fields
        if (!empty($this->_userfieldids)) {
            //only need to obtain the user information once
            $user = context_elis_user::instance($datum->userid);

            //add a row for each field
            foreach ($this->_userfieldids as $userfieldid) {
                $field = new field($userfieldid);

                //needed to store just the actual data value
                $rawdata = array();

                if ($customdata = field_data::get_for_context_and_field($user, $field)) {
                    //could potentially have multiple values
                    foreach ($customdata as $customdatum) {
                        if ($field->datatype == 'bool') {
                            //special display handling for boolean values
                            $rawdata[] = !empty($customdatum->data) ? get_string('yes') : get_string('no');
                        } else if (isset($field->owners['manual']) &&
                                   ($manual = new field_owner($field->owners['manual'])) &&
                                   $manual->param_control == 'datetime') {
                            //special display handling for datetime fields
                            $rawdata[] = $this->userdate($customdatum->data,
                                             get_string(
                                                 !empty($manual->param_inctime)
                                                 ? 'customfield_datetime_format'
                                                 : 'customfield_date_format',
                                                 $this->languagefile));
                        } else {
                            $rawdata[] = $customdatum->data;
                        }
                    }
                }

                $labels[] = $this->add_grouping_header($field->name.': ', implode(', ', $rawdata), $export_format);
            }
        }

        //user address
        if (!empty($headers['user_address'])) {
            $text_label = get_string('grouping_address', $this->languagefile);
            $address_text = get_string('grouping_address_format', $this->languagefile, $datum);
            $labels[] = $this->add_grouping_header($text_label, $address_text, $export_format);
        }

        //user city / town
        if (!empty($headers['user_city'])) {
            $text_label = get_string('grouping_city', $this->languagefile);
            $labels[] = $this->add_grouping_header($text_label, $datum->city, $export_format);
        }

        //user state / province
        if (!empty($headers['user_state'])) {
            $text_label = get_string('grouping_state', $this->languagefile);
            $labels[] = $this->add_grouping_header($text_label, $datum->state, $export_format);
        }

        //user email address
        if (!empty($headers['user_email'])) {
            $text_label = get_string('grouping_email', $this->languagefile);
            $labels[] = $this->add_grouping_header($text_label, $datum->email, $export_format);
        }

        // Get the credit total
        $num_credits = $this->get_total_credits($grouping_current, $grouping, $datum, $export_format);
        //create the header item
        $text_label = get_string('grouping_credits', $this->languagefile);
        $labels[] = $this->add_grouping_header($text_label, $num_credits, $export_format);

        return $labels;
    }

    /**
     * Calculate the total credits for the current user
     * @param   string array          $grouping_current  Mapping of field names to current values in the grouping
     * @param   table_report_grouping $grouping          Object containing all info about the current
     *                                                   level of grouping being handled
     * @param   stdClass              $datum             The most recent record encountered
     * @param   string                $export_format     The format being used to render the report
     * @uses    $DB
     * @return  string                The format total of credits
     */
    function get_total_credits($grouping_current, $grouping, $datum, $export_format) {
        global $DB;
        $params = array();
        //obtain an sql clause that filters out users you shouldn't see
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        //$permissions_filter = $contexts->sql_filter_for_context_level('u.id', 'user');
        $filter_obj = $contexts->get_filter('id', 'user');

        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }

        //dynamically hande the class start / end date condition
        $startdate_condition = $this->get_class_startdate_condition();

        $ccc_sql = $this->get_course_class_condition_sql();

        $status_clause = '';
        $status_sql    = '';
        // Check if we need to check status
        if ($value = php_report_filtering_get_active_filter_values($this->parentname, 'filter-completionstatus', $this->filter)) {
            $status = $value[0]['value'];
        }
        if (isset($status)) {
            $status_clause = $this->get_class_status_sql($status);
            $status_sql    = ' AND EXISTS
                             (SELECT stu.userid
                                FROM {'. student::TABLE .'} stu'
                           . $this->get_class_join_sql('WHERE')
                           .' AND stu.userid = u.id'
                           . $status_clause .')';
        } else {
            $status = STUSTATUS_PASSED;
        }

        // Determine if we are filtering by one or more custom field values
        $filtering_cur_customfield = false;

        /**
         * This section handles updating all curriculum custom fields with an additional condition,
         * attaching the filter condition to the outer curriculum in the case where we are displaying
         * some curriculum field, such as name or a custom field datum
         */
        //find a curriculum customfield filters
        foreach ($this->filter->_fields as $key => $value) {
            if (strpos($key, 'filter-ccc-curriculum-customfield-') === 0) {
                //determine if a session value is set for this field
                if ($test_customfield = php_report_filtering_get_active_filter_values($this->get_report_shortname(), $key, $this->filter)) {
                    //signal that we are filtering by curriculum customfields
                    $filtering_cur_customfield = true;
                    break;
                }
            }
        }

        //calculate the appropriate filter clause - moved up from within next if block
        list($filter_clause, $filter_params) = $this->get_filter_condition('');
        if (empty($filter_clause)) {
            $filter_clause = 'TRUE';
        } else {
            $params = array_merge($params, $filter_params);
        }
        //error_log("UCCDR::get_total_credits() - filter_clause = {$filter_clause}");

        /**
         * Calculate the total number of distinct credits in the report
         */
        if ($this->_show_curricula || $filtering_cur_customfield) {
            //we are showing curricula, so we need to account for curriculum and
            //non-curriculum cases

            if (!$this->_show_curricula && $filtering_cur_customfield) {
                //special case: filtering by customfields when showing no curriculum info
                $filter_clause = str_replace('u.id = cca.userid', 'u.id = cca.userid AND curcrs.curriculumid = cca.curriculumid', $filter_clause);
            }

            $curriculum_sql = 'SELECT SUM(credits) FROM (
                                   SELECT DISTINCT u.id AS userid, cls.id AS classid, stu.credits
                                     FROM {'. user::TABLE .'} u
                                     JOIN {'. student::TABLE .'} stu
                                       ON u.id = stu.userid {$status_clause}
                                     JOIN {'. pmclass::TABLE .'} cls
                                       ON stu.classid = cls.id
                                     JOIN {'. curriculumcourse::TABLE .'} curcrs
                                       ON cls.courseid = curcrs.courseid
                                     JOIN {'. curriculumstudent::TABLE .'} curstu
                                       ON u.id = curstu.userid
                                     JOIN {'. curriculum::TABLE .'} cur
                                       ON curstu.curriculumid = cur.id
                                      AND curcrs.curriculumid = curstu.curriculumid
                                    WHERE stu.completestatusid = '. STUSTATUS_PASSED ."
                                      AND {$filter_clause}
                                     {$ccc_sql}
                                     AND {$permissions_filter}
                                     AND {$startdate_condition}) c";

            //obtain the actual number
            $curriculum_num = $DB->get_field_sql($curriculum_sql, $params);

            if ($filtering_cur_customfield) {
                //if we are filtering by curriculum customfields, there will never be any non-curriculum results
                $noncurriculum_num = 0;
            } else {
                $noncurriculum_sql = 'SELECT SUM(stu.credits)
                                        FROM {'. user::TABLE .'} u
                                        JOIN {'. student::TABLE ."} stu
                                          ON u.id = stu.userid {$status_clause}
                                        JOIN {". pmclass::TABLE .'} cls
                                          ON stu.classid = cls.id
                                   LEFT JOIN ({'. curriculumcourse::TABLE .'} curcrs
                                              JOIN {'. curriculumstudent::TABLE .'} curstu
                                                ON curcrs.curriculumid = curstu.curriculumid)
                                          ON cls.courseid = curcrs.courseid
                                         AND stu.userid = curstu.userid
                                   LEFT JOIN {'. curriculum::TABLE .'} cur
                                          ON cur.id = 0
                                       WHERE curstu.id IS NULL
                                         AND stu.userid = u.id
                                         AND stu.completestatusid = '. STUSTATUS_PASSED .'
                                         AND '. table_report::PARAMETER_TOKEN ."
                                        AND {$filter_clause}
                                        {$ccc_sql}
                                        AND {$permissions_filter}
                                        AND {$startdate_condition}";
                //add filtering to the query
                $noncurriculum_sql = $this->get_complete_sql_query(false, $noncurriculum_sql, $params);
                if (!empty($noncurriculum_sql) && !empty($noncurriculum_sql[0])) {
                    $params = array_merge($params, $noncurriculum_sql[1]);
                }
                //obtain the actual number
                $noncurriculum_num = $DB->get_field_sql($noncurriculum_sql[0], $params);
            }

            //total credits is the sum of curriculum and non-curriculum credits
            $num_credits = (float)$curriculum_num + (float)$noncurriculum_num;
        } else {
            //we are not showing curricula, so this is simply an aggregation over class
            //enrolments, based on filters
            $sql = 'SELECT SUM(stu.credits)
                      FROM {'. user::TABLE .'} u
                      JOIN {'. student::TABLE ."} stu
                        ON u.id = stu.userid {$status_clause}
                      JOIN {". pmclass::TABLE .'} cls
                        ON stu.classid = cls.id
                     WHERE stu.completestatusid = '. STUSTATUS_PASSED .'
                       AND '. table_report::PARAMETER_TOKEN ."
                       AND {$filter_clause}
                     {$ccc_sql}
                       AND {$permissions_filter}
                       AND {$startdate_condition}";

            $sql = $this->get_complete_sql_query(false, $sql, $params);
            if (!empty($sql) && !empty($sql[0])) {
                $params = array_merge($params, $sql[1]);
            }
            $num_credits = $DB->get_field_sql($sql[0], $params);
        }

        //show number of credits to two decimals
        $num_credits = $this->format_credits($num_credits);
        return $num_credits;
    }


    /**
     * Specifies the config header for this report
     *
     * @return  string  The HTML content of the config header
     * @uses $CFG
     */
    function get_config_url() {
        global $CFG;
        return $CFG->wwwroot .'/blocks/php_report/render_report_page?report='. $this->parentname .'" target="_top';
    }

    /**
     * Constructs an appropriate order by clause for the main query
     *
     * @return  string  The appropriate order by clause
     */
    function get_order_by_clause() {
        //determine whether we're showing the curriculum name
        $filters = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                       'filter-detailcolumns', $this->filter);
        $cols = $filters[0]['value'];
        if ($cols['cur_name']) {
            //showing curriuclumname, so sort by curriculum, course, class
            return ' ORDER BY curid IS NULL, curriculumname, coursename, classidnumber';
        }
        //not showing curriculum name, so just sort by course, class
        return ' ORDER BY coursename, classidnumber';
    }

    /**
     * Determines whether the current user can view this report, based on being logged in
     * and php_report:view capability
     *
     * @return  boolean  True if permitted, otherwise false
     */
    function can_view_report() {
        if ($this->execution_mode == php_report::EXECUTION_MODE_SCHEDULED) {
            //prevent this report from being scheduled
            return false;
        }

        return parent::can_view_report();
    }

    /**
     * Specifies whether this report definition has one or more
     * filters defined for it
     *
     * @return  boolean  true if one more more filters are defined, or false if none are
     */
    function has_filters() {
        //this report has no configurable filters
        return false;
    }

    /**
     * Determines if standard and custom curriculum fields are included in the
     * details report.  This function is needed in order to resolve the issues
     * described in EOPSS-70
     *
     * @param array array of fields included with the report
     * @uses   $DB
     * @return boolean - false if no custom curriculum fields are included
     */
    protected function custom_fields_included($filters) {
        global $DB;

        $levels = array();
        $no_custom_fields = true;
        foreach (array('curriculum', 'course', 'class') as $entity) {
            //$level = context_level_base::get_custom_context_level($entity, 'elis_program');
            // ELIS-4089: Moodle 2.2 custom contexts
            $level = context_elis_helper::get_level_from_name($entity);
            if ($level) {
                $levels[] = $level;
                $no_custom_fields = false;
            } else {
                error_log("UCCDR::custom_fields_included(); NO custom field context level for '{$entity}'!");
            }
        }
        if ($no_custom_fields) {
            return false;
        }

        foreach ($filters as $key => $filter) {
            foreach ($filter['value'] as $field_alias => $filter_value) {
                // If the filter_value is 0 then we don't need to worry about it
                if ($filter_value) {
                    if (false !== ($pos = strpos($field_alias, '_'))) {
                        // Determine whether this field has a 'custom' prefix
                        $custom_field_name = substr($field_alias, 0, $pos);
                        // Get the custom field id
                        if (0 == strcmp($custom_field_name, 'custom')) {
                            // Only get the last part of the field name as it contains the custom field id
                            $custom_field_id = substr($field_alias, $pos + 1);

                            //TBD: Check the custom field
                            if ($DB->record_exists_select('elis_field_contextlevels',
                                       "fieldid = {$custom_field_id} AND contextlevel IN (". implode(', ', $levels) .')')) {
                                //error_log("UCCDR::custom_fields_included(): fieldid {$custom_field_id} exists for an ELIS contextlevel!");
                                return true;
                            }
                        } else if (0 == strcmp($field_alias, 'cur_name')) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

}

