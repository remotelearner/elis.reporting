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
 * @package    php_report
 * @subpackage user_class_completion
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');
require_once($CFG->dirroot .'/blocks/php_report/lib/filtering.php');

/**
 * User class completion report
 *
 * @author Tyler Bannister <tyler.bannister@remote-learner.net>
 * @author Brent Boghosian <brent.boghosian@remote-learner.net>
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 */
class user_class_completion_report extends table_report {

    public $last_cluster_hierarchy = array();
    public $reportname   = 'user_class_completion';
    public $languagefile = 'rlreport_user_class_completion';

    // List of fields to be displayed for the curriculumclass filter
    protected $_curriculumclass_fields = array(
        'curriculum' => array('name'             => 'fld_curriculum'),
        'course'     => array('name'             => 'fld_course'),
        'class'      => array('idnumber'         => 'fld_class',
                              //'environmentid'    => 'fld_environment',
                              // Environment changed to custom field in ELIS 2.x
                              'startdate'        => 'fld_startdate',
                              'completestatusid' => 'fld_classstatus')
        );

    // List of fields to be displayed for the userprofilematch filter
    protected $_user_fields = array('up' =>
        array(
            'fullname',
            'lastname',
            'firstname',
            'idnumber',
            'email',
            'city',
            'country',
            'username',
            'language',
            'inactive' //, 'customfields',
        )//, 'custom' => array('customfields')
    );

    protected $_show_curricula = null;
    protected $_customfieldids = array();
    //store default custom field values where appropriate
    protected $_defaultfieldvalues = array();
    //store custom field values where appropriate
    protected $_fielddatatypes = array();
    //store what contexts fields correspond to
    protected $_fielddatacontexts = array();
    // store datetime custom fields: fieldid => inctime
    protected $_datetimefields = array();

    protected $student_id_num = '';

    //constant for the "not started" completion status
    const STUSTATUS_NOTSTARTED = 9999;

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_* constants)
     */
    function get_category() {
        return self::CATEGORY_USER;
    }

    /**
     * Specifies whether the current report is available
     * (a.k.a. any the CM system is installed)
     *
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
     *
     * (only called after is_available returns true)
     *
     * @uses $CFG
     */
    function require_dependencies() {
        global $CFG;

        // Needed to reference curriculum entities
        require_once($CFG->dirroot .'/elis/program/lib/setup.php');

        // Needed for constants that define db tables
        require_once($CFG->dirroot .'/elis/program/lib/data/classmoodlecourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculum.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumcourse.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/curriculumstudent.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');

        // Make sure we have access to the context library
        require_once($CFG->dirroot .'/elis/program/lib/contexts.php');

        // Make sure we have access to the filters
        require_once($CFG->dirroot .'/elis/program/lib/filtering/elisuserprofile.php');
        require_once($CFG->dirroot .'/elis/program/lib/filtering/curriculumclass.php');

        // Reference the "child" report
        require_once($CFG->dirroot .'/blocks/php_report/instances/user_class_completion_details/user_class_completion_details_report.class.php');

        // Needed to instantiate links
        require_once($CFG->dirroot .'/elis/program/curriculumpage.class.php');
        require_once($CFG->dirroot .'/elis/program/coursepage.class.php');
        require_once($CFG->dirroot .'/elis/program/pmclasspage.class.php');
        require_once($CFG->dirroot .'/elis/program/userpage.class.php');

        // Needed for custom field handling
        require_once($CFG->dirroot .'/elis/core/lib/data/customfield.class.php');
    }

    function get_langfile() {
        return(!empty($this->parent_langfile) ? $this->parent_langfile
                                              : $this->languagefile);
    }

    /**
     * Function to get_string from parent user_class_completion
     * when string requested by user_class_completion_details report.
     *
     * @param  $sid  the string identifier
     * @return the requested string
     */
    function get_string($sid) {
        return get_string($sid, $this->get_langfile());
    }

    /**
     * Specifies available report filters
     * (allow for filtering on various user and cluster-related fields)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     * @return  generalized_filter_entry array  The list of available filters
     * @uses $CFG
     */
    function get_filters($init_data = true) {
        global $CFG, $USER;
        require_once($CFG->dirroot.'/elis/program/accesslib.php');

        //cluster tree
        $enable_tree_label     = $this->get_string('enable_tree');
        $enable_dropdown_label = $this->get_string('enable_dropdown');
        $help_label            = $this->get_string('cluster_help');

        $clustertree_help = array('user_class_completion_cluster',
                                  $help_label, $this->get_langfile());

        $clustertree_options = array(
                'dropdown_button_text'   => $enable_tree_label,
                'tree_button_text'       => $enable_dropdown_label,
                'report_id'              => $this->id,
                'report_shortname'       => 'user_class_completion',
                'help'                   => $clustertree_help,
                'fieldset'               => false,
                'filter_on_user_records' => true
        );

        //completionstatus checkboxes
        $complete_key = STUSTATUS_PASSED.','.STUSTATUS_FAILED;
        $incomplete_key = STUSTATUS_NOTCOMPLETE.',NULL';

        $completed_courses_label = $this->get_string('show_completed_classes');
        $incomplete_courses_label = $this->get_string('show_incomplete_classes');
        $csheading_label = $this->get_string('completionstatus_options_heading');

        $choices = array(
                $complete_key => $completed_courses_label,
                $incomplete_key => $incomplete_courses_label);

        $cs_checked = array(
                $complete_key, $incomplete_key);

        $completionstatus_options = array(
                'choices' => $choices,
                'checked' => $cs_checked,
                'numeric' => true,
                'nullvalue' => 'NULL',
                'isrequired'=> false,
                'heading' => $csheading_label);

        //columns checkboxes
        $curriculum_label = $this->get_string('report_field_curriculum');
        $status_label     = $this->get_string('report_field_status');
        $completion_label = $this->get_string('report_field_completion');
        $heading_label    = $this->get_string('report_fields_heading');

        $choices = array(
                'curriculum' => $curriculum_label,
                'status' => $status_label,
                'completion' => $completion_label);

        $checked = array(
                'curriculum', 'status', 'completion');

        $columns_options = array(
                'choices' => $choices,
                'checked' => $checked,
                'allowempty' => true,
                'heading' => $heading_label);

        //setup for the completion status dropdown
        $completionstatus_choices = array(
                STUSTATUS_PASSED => get_string('status_passed', $this->languagefile),
                STUSTATUS_FAILED => get_string('status_failed', $this->languagefile),
                STUSTATUS_NOTCOMPLETE => get_string('status_notcomplete', $this->languagefile),
                user_class_completion_details_report::STUSTATUS_NOTSTARTED => get_string('status_notstarted', $this->languagefile)
                                     );

        $completionstatus_options = array(
                'choices'   => $completionstatus_choices,
                'numeric'   => true,
                'nofilter'  => true,
                'checked'   => $cs_checked, // TBD: from above
                'nullvalue' => 'NULL',      // TBD: from above
                'isrequired'=> false,       // TBD: from above
                'heading'   => $csheading_label, // TBD: from above
                'help'      => array('user_class_completion_completionstatus',
                                get_string('completion_status', 'elis_program'),
                                $this->languagefile));

        $userfilter =
            new generalized_filter_elisuserprofile(
                'filter-up-',
                $this->get_string('filter_user_match'),
                array(
                    'choices'     => $this->_user_fields,
                    'notadvanced' => array('fullname'),
                    'extra'       => true, // include all extra profile fields
                    'help'        => array(
                        'up' => array(
                            'fullname'  => array('user_class_completion_fullname', get_string('fullname'), $this->languagefile),
                            'lastname'  => array('user_class_completion_lastname', get_string('lastname'), $this->languagefile),
                            'firstname' => array('user_class_completion_firstname', get_string('firstname'), $this->languagefile),
                            'idnumber'  => array('user_class_completion_idnumber', get_string('idnumber'), $this->languagefile),
                            'email'     => array('user_class_completion_email', get_string('email'), $this->languagefile),
                            'city'      => array('user_class_completion_city', get_string('city'), $this->languagefile),
                            'country'   => array('user_class_completion_country', get_string('country'), $this->languagefile),
                            'username'  => array('user_class_completion_username', get_string('username'), $this->languagefile),
                            'language'  => array('user_class_completion_language', get_string('language'), $this->languagefile),
                            'inactive'  => array('user_class_completion_inactive', get_string('inactive'), $this->languagefile),
                        ),
                    ),
                    'outerfield'  => array(
                        'up' => 'u.id',
                    )
                )
            );

        $classfilter =
            new generalized_filter_curriculumclass(
                'filter-ccc-',
                $this->get_string('filter_curriculumclass'),
                array(
                    'choices'     => $this->_curriculumclass_fields,
                    'notadvanced' => array(
                        'curriculum' => array('name'),
                        'course'     => array('name'),
                        'class'      => array('idnumber'),
                    ),
                    'extra'       => true, // include all extra profile fields
                    'help'        => array(
                        'curriculum' => array(
                            'name' => array('user_class_completion_curriculum',
                                            get_string('curriculum', 'elis_program'),
                                            $this->languagefile),
                        ),
                        'course' => array(
                            'name' => array('user_class_completion_course',
                                            get_string('course', 'elis_program'),
                                            $this->languagefile)
                        ),
                        'class' => array(
                            'idnumber'          => array('user_class_completion_class',
                                                         get_string('class', 'elis_program'),
                                                         $this->languagefile),
                            'environmentid'     => array('user_class_completion_environment',
                                                         get_string('environment', 'elis_program'),
                                                         $this->languagefile),
                            'startdate'         => array('user_class_completion_startdate',
                                                         get_string('class_startdate', 'elis_program'),
                                                         $this->languagefile),
                        ),
                    ),
                    'wrapper'     => array(
                        'curriculum' => '',
                        'course'     => ' INNER JOIN {crlm_class_enrolment} clse
                                                  ON clse.classid = cls.id',
                        'class'      => ' INNER JOIN {crlm_class_enrolment} clse
                                                  ON clse.classid = cls.id',
                     ),
                    'innerfield'  => array(
                        'curriculum' => 'cca.userid',
                        'course'     => 'clse.userid',
                        'class'      => 'clse.userid',
                     ),
                    'outerfield' => array(
                        'curriculum' => 'u.id',
                        'course'     => 'u.id',
                        'class'      => 'u.id',
                     ),
                )
            );

        $titleoptions = array(
            'help' => array('user_class_completion_reporttitle',
                            $this->get_string('report_title'),
                            $this->get_langfile()),
        );

        $summarycoloptions = array(
            'title' => 'summary_report_fields',
            'name'  => 'summary_fields',
            'lang'  => $this->languagefile,
            'help'  => 'user_class_completion_summarydatafields',
            'fields' => array(
                'userfields' => array(
                    'user_address' => 'address',
                    'user_city'    => 'city',
                    'user_state'   => 'state',
                    'user_email'   => 'email',
                    'custom'       => array('user' => 'all'),
                ),
                'curriculumfields' => array(
                    'cur_name'          => 'curriculum',
                    'cur_timecompleted' => 'curriculumcompletiondate',
                    'cur_timeexpired'   => 'curriculumexpirationdate',
                    'cur_certificate'   => 'certificatenumber',
                    'custom'            => array('curriculum' => 'all'),
                ),
            ),
        );

        $detailheadoptions = array(
            'title' => 'detail_head_fields',
            'name'  => 'detail_headers',
            'lang'  => $this->languagefile,
            'help'  => 'user_class_completion_detailheaderfields',
            'fields' => array(
                'userfields' => array(
                    'user_address' => 'address',
                    'user_city'    => 'city',
                    'user_state'   => 'state',
                    'user_email'   => 'email',
                    'custom'       => array('user' => 'all'),
                ),
            ),
        );

        $detailcoloptions = array(
            'title' => 'detail_col_fields',
            'name'  => 'detail_fields',
            'lang'  => $this->languagefile,
            'help'  => 'user_class_completion_detaildatafields',
            'fields' => array(
                'curriculumfields' => array(
                    'cur_name'          => 'curriculum',
                    'custom'            => array('curriculum' => 'all'),
                ),
                'coursefields' => array(
                    'custom'       => array('course' => 'all'),
                ),
                'classfields' => array(
                    'custom'       => array('class' => 'all'),
                ),
                'otherfields' => array(
                    'class_role'   => 'classrole',
                ),
            ),
        );

        $filters = array();

        $autocomplete_opts = array(
            'report' => $this->get_report_shortname(),
            'ui' => 'inline',
            'contextlevel' => CONTEXT_ELIS_USER,
            'instance_fields' => array(
                'idnumber' => get_string('filter_autocomplete_idnumber', $this->get_langfile()),
                'firstname' => get_string('filter_autocomplete_firstname', $this->get_langfile()),
                'lastname' => get_string('filter_autocomplete_lastname', $this->get_langfile())
            ),
            'custom_fields' => '*',
            'label_template' => '[[firstname]] [[lastname]]',
            'configurable' => true,
            'selection_enabled' => true,
        );

        $filters[] = new generalized_filter_entry(
                'filter-autoc',
                '',
                "CONCAT(u.firstname,' ',u.lastname)",
                get_string('fld_fullname','elis_core'),
                false,
                'autocomplete_eliswithcustomfields',
                $autocomplete_opts
        );

        $filters = $filters + $userfilter->get_filters();

        $filters[] = new generalized_filter_entry('filter-uid', 'u', 'id',
                             $this->get_string('filter_cluster'),
                             false, 'clustertree', $clustertree_options);

        $filters[] = new generalized_filter_entry('filter-completerange','stu','completetime', $this->get_string('completed_range'),false,'date');


        $schemas = array(
                $classfilter,
                array('completionstatus', 'filter_completionstatus', false,
                      'simpleselect', $completionstatus_options),
                array('title', 'title', false, 'display_text', $titleoptions),
                array('summarycolumns', 'summary_columns', false, 'display_table', $summarycoloptions),
                array('detailheaders', 'detail_headers', false, 'display_table', $detailheadoptions),
                array('detailcolumns', 'detail_columns', false, 'display_table', $detailcoloptions)
        );

        foreach ($schemas as $schema) {
            if (is_array($schema)) {
                if ($schema[1] == '') {
                    $label = get_string($schema[0]);
                } else {
                    $label = $this->get_string($schema[1]);
                }
                $filters[] = new generalized_filter_entry('filter-'. $schema[0],
                                    '', $schema[0], $label, $schema[2],
                                    $schema[3], $schema[4]);
            } else {
                $filters =  array_merge($filters, $schema->get_filters());
            }
        }

        //return all filters
        return $filters;
    }

    /**
     * Returns an array of report columns related to the specified CM custom fields
     * @param array  $cols  List of columns/fields we are displaying
     * @param string $type  The custom field type: curriculum, course, class ...
     * @return array Collection of appropriate table report columns
     */
    function get_custom_field_columns($cols, $type) {
        global $DB;

        $columns = array();

        if (!empty($cols)) {
            foreach ($cols as $field => $active) {
                if ($active && (substr($field, 0, 7) == 'custom_')) {
                    $fieldid = substr($field, 7);
                    //store the context level that's represented by this field
                    //$level = context_level_base::get_custom_context_level($type, 'elis_program');
                    // ELIS-4089: Moodle 2.2 custom contexts
                    $level = context_elis_helper::get_level_from_name($type);
                    if (!$DB->record_exists('elis_field_contextlevels',
                                 array('fieldid'      => $fieldid,
                                       'contextlevel' => $level))) {
                        continue;
                    }

                    $this->_fielddatacontexts[$fieldid] = $type;
                    $this->_customfieldids[] = $fieldid;
                    $name = $DB->get_field('elis_field', 'name', array('id' => $fieldid));
                    $column = new table_report_column('customfielddata_'.$fieldid.'.data AS customfielddata_'.$fieldid, $name, 'field_'.$fieldid);
                    $columns[] = $column;

                    //store custom field information we need later
                    $field = new field($fieldid);
                    if ($default_records = $DB->get_records_select($field->data_table(), "contextid IS NULL AND fieldid = ?", array($field->id))) {
                        foreach ($default_records as $default_record) {
                            $this->_defaultfieldvalues[$fieldid] = $default_record->data;
                            //note: if we need to support multi-select fields, this will need to be
                            //further generalized
                            break;
                        }
                    }

                    //store the data type
                    $this->_fielddatatypes[$fieldid] = $field->datatype;

                    // ELIS-5862/ELIS-7409: keep track of datetime fields
                    if (isset($field->owners['manual']) &&
                        ($manual = new field_owner($field->owners['manual']))
                        && $manual->param_control == 'datetime') {
                        $this->_datetimefields[$fieldid] = !empty($manual->param_inctime);
                    }
                }
            }
        }

        return $columns;
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
            'user_address'      => array('name' => 'address',        'id' => 'u.address'),
            'user_city'         => array('name' => 'city',           'id' => 'u.city'),
            'user_state'        => array('name' => 'state',          'id' => 'u.state'),
            'user_email'        => array('name' => 'email',          'id' => 'u.email'),
            'cur_name'          => array('name' => 'curriculumname', 'id' => 'cur.name'),
            'cur_timecompleted' => array('name' => 'timecompleted',  'id' => 'curstu.timecompleted'),
            'cur_timeexpired'   => array('name' => 'timeexpired',    'id' => 'curstu.timeexpired'),
            'cur_certificate'   => array('name' => 'certificatenum', 'id' => 'curstu.certificatecode'),
        );

        $filters = php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(), 'filter-summarycolumns',
                       $this->filter);
        $cols = $filters[0]['value'];

        // User fullname
        $name_heading = get_string('column_user_name', $this->languagefile);
        $name_column = new table_report_column('u.lastname', $name_heading, 'user_name');
        $result[] = $name_column;

        // User idnumber
        $idnumber_heading = get_string('column_idnumber', $this->languagefile);
        $idnumber_column = new table_report_column('u.idnumber AS useridnumber', $idnumber_heading, 'idnumber');
        $result[] = $idnumber_column;

        // Add summary profile fields
        $summary_custom_field_columns = $this->get_custom_field_columns($cols, 'user');
        $result = array_merge($result, $summary_custom_field_columns);

        foreach ($optionalcols as $key => $col) {
            if (! empty($cols[$key])) {
                $heading = get_string('column_'. $col['name'], $this->languagefile);
                $column  = new table_report_column($col['id'], $heading, $col['name']);
                $result[] = $column;
            }
        }

        // Add custom curriculum fields
        $summary_custom_field_columns = $this->get_custom_field_columns($cols, 'curriculum');
        $result = array_merge($result, $summary_custom_field_columns);

        // Placeholder for displaying number of credits
        $numcredits_heading = get_string('column_numcredits', $this->languagefile);
        $numcredits_column = new table_report_column('0 AS displaynumcredits', $numcredits_heading, 'numcredits');
        $result[] = $numcredits_column;

        // Details Column
        $details_column = new table_report_column('u.id', '', 'details');
        $result[] = $details_column;

        return $result;
    }

    /**
     * Returns an SQL condition fragment needed to filter class/course
     *
     * @return string The appropriate SQL fragment
     */
    function get_course_class_condition_sql() {
        $report_filter = isset($this->parentname) ? $this->parentname
                                                  : $this->reportname;

        // Check for class filter
        $filters = php_report_filtering_get_active_filter_values($report_filter,
                        'filter-ccc-class-idnumber', $this->filter);
        if (!empty($filters[0]['value']) && is_array($filters[0]['value'])) {
            $classes = implode(',', $filters[0]['value']);
           /*
            ob_start();
            var_dump($classes);
            $tmp = ob_get_contents();
            ob_end_clean();
            error_log("UCCR::get_course_class_condition_sql(): classes => $tmp");
           */
            return " AND cls.id IN ({$classes}) ";
        } else {
            // Check for course filter
            $filters = php_report_filtering_get_active_filter_values(
                           $report_filter, 'filter-ccc-course-name',
                           $this->filter);
            if (!empty($filters[0]['value']) && is_array($filters[0]['value'])) {
                $courses = implode(',', $filters[0]['value']);
               /*
                ob_start();
                var_dump($courses);
                $tmp = ob_get_contents();
                ob_end_clean();
                error_log("UCCR::get_course_class_condition_sql(): courses => $tmp");
               */
                return " AND cls.courseid IN ({$courses}) ";
            }
        }

        return '';
    }

    /**
     * Returns an SQL fragment needed to join the class table (if needed)
     *
     * @param  string $on_or_where  Either string 'ON' or 'WHERE' depending
     * @return string The appropriate SQL fragment
     */
    function get_class_join_sql($on_or_where = 'ON') {
        $on_or_where = strtoupper($on_or_where);
        $join = ($on_or_where != 'ON') ? ' WHERE TRUE ' : '';
        $ccc_sql = $this->get_course_class_condition_sql();
        if (!empty($ccc_sql)) {
            $join = 'JOIN {crlm_class_enrolment} cce';
            if ($on_or_where == 'ON') {
                $join .= ' ON u.id = cce.userid';
            }
            $join .= ' JOIN {'. pmclass::TABLE .'} cls'
                    ." ON cce.classid = cls.id {$ccc_sql}";
            if ($on_or_where != 'ON') {
                $join .= ' WHERE u.id = cce.userid';
            }
        }
        return $join;
    }

    /**
     * Returns an SQL fragment needed to connect a context entity table to its
     * CM custom fields data in the CM system
     *
     * @param string $contextlevel Shortname of the context level we are looking for fields
     *                             related to
     * @param array $fieldids List of ids of field records we are displaying
     * @param string $instancefield Database field representing the context id
     * @return string The appropriate SQL fragment
     */
    function get_custom_field_sql($fieldids, $instancefields) {
        global $DB;

        $where    = $DB->get_in_or_equal($fieldids);
        $contexts = $DB->get_records_select(field_contextlevel::TABLE, 'fieldid '.$where[0], $where[1]);

        $fragment = array();

     /* *** debug ***
        ob_start();
        var_dump($instancefields);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log("UCCR::get_custom_field_sql(fieldids, instancefields = {$tmp})");
     */

        // Get the legacy context names mapped to the context level values
        $contextlevelnames = array_flip(context_elis_helper::get_legacy_levels());

        $contextlevel = '';
        $contextname  = '';
        if (!empty($contexts)) {
            //add a join for each profile field
            foreach ($contexts as $context) {
                $ctxname     = $contextlevelnames[$context->contextlevel];

                if (!in_array($ctxname, array_keys($instancefields))) {
                    // Not a context level we care about
                    continue;
                }

                if ($contextlevel != $context->contextlevel) {
                    $contextlevel = $context->contextlevel;
                    $contextname = $ctxname.'_'; // TBD
                    $instancefield = $instancefields[$ctxname];
                    //have one ot more profile field we're joining, so join the context table at the top level
                    $fragment[$ctxname] =
                             " LEFT JOIN {context} {$contextname}context
                                      ON {$instancefield} = {$contextname}context.instanceid
                                     AND {$contextname}context.contextlevel = {$contextlevel}";
                }
                $field = new field($context->fieldid);
                $identifier = "customfielddata_{$context->fieldid}";
                $fragment[] = ' LEFT JOIN {'. $field->data_table() ."} {$identifier}
                                       ON {$contextname}context.id = {$identifier}.contextid
                                      AND {$identifier}.fieldid = {$context->fieldid}";
            }
        }

        $result = implode("\n", $fragment);
//         error_log("UCCR::get_custom_field_sql() => {$result}");
        return $result;
    }

    /**
     * Get Elements Passed SQL
     *
     * Generates the SQL fragment used to determine whether a student has passed parts of a class
     *
     * @return string SQL fragment for checking passed elements
     */
   function get_elements_sql() {
        //sql fragment related to completion elements used both in the main query and in distinguishing between
        //"in progress" and "not started" users
        $sql = 'FROM {'. coursecompletion::TABLE .'} course_completion
                JOIN {'. student_grade::TABLE .'} class_graded
                  ON course_completion.id = class_graded.completionid
                 AND class_graded.locked = 1
                 AND class_graded.grade >= course_completion.completion_grade
                WHERE stu.classid = class_graded.classid
                 AND u.id = class_graded.userid';

        return $sql;
   }

    /**
     * Get Access SQL
     *
     * Generates the SQL fragment to determine whether a student has accessed the class
     *
     * @return string SQL fragment for checking access and passed elements.
     */
    function get_access_sql() {
        //condition that specifies whether a user has accessed an associated Moodle course
        $access = 'EXISTS (SELECT *
                             FROM {'. classmoodlecourse::TABLE .'} clsmdl
                             JOIN {log} log
                               ON clsmdl.moodlecourseid = log.course
                             JOIN {user} mdlu
                               ON log.userid = mdlu.id
                            '. $this->get_class_join_sql('WHERE') .
                            ' AND stu.classid = clsmdl.classid
                              AND u.idnumber = mdlu.idnumber)';

        $elements_passed = $this->get_elements_sql();

        //condition that specifies whether a user has passed one or more completion elements in the
        //current class
        $sql = "({$access} OR EXISTS (SELECT * {$elements_passed}))";
        return $sql;
     }

    /**
     * Get Class Status SQL
     *
     * Generates the SQL fragment to check for a particular class status
     *
     * @return string SQL fragment for checking the class status
     */
    function get_class_status_sql($status) {
        $sql = '';
        $access_sql = $this->get_access_sql();

        if (isset($status)) {
            switch ($status) {
                case STUSTATUS_PASSED:
                case STUSTATUS_FAILED:
                    // Can just use the actual status id
                    $sql = " AND stu.completestatusid = {$status}";
                    break;
                case STUSTATUS_NOTCOMPLETE:
                    // Calculate the aggregate condition
                    $sql = " AND stu.completestatusid = {$status} AND {$access_sql}";
                    break;
                case self::STUSTATUS_NOTSTARTED:
                    // Calculate the aggregate condition
                    $sql = ' AND stu.completestatusid = '. STUSTATUS_NOTCOMPLETE ." AND NOT {$access_sql}";
                    break;
            }
        }
        return $sql;
    }

    /**
     * Calculates an SQL condition that asserts that the class startdate is within a
     * provided range based on this report's non-standard date filter
     *
     * @param string $report_shortname The shortname of the report we are checking for
     *                                 (use the current report if not specified)
     * @return string The appropriate SQL condition
     */
    function get_class_startdate_condition($report_shortname = NULL) {
        //set up the shortname from the current report, if necessary
        if ($report_shortname === NULL) {
            $report_shortname = $this->get_report_shortname();
        }

        $conditions = array();

        //try to obtain the start date condition
        $param_structure = php_report_filtering_get_active_filter_values($report_shortname, 'filter-ccc-class-startdate_sck', $this->filter);
        if (!empty($param_structure[0]['value'])) {
            $param_structure = php_report_filtering_get_active_filter_values($report_shortname, 'filter-ccc-class-startdate_sdt', $this->filter);
            $param_value = $param_structure[0]['value'];
            $conditions[] = "cls.startdate >= {$param_value}";
        }

        //try to obtain the end date condition
        $param_structure = php_report_filtering_get_active_filter_values($report_shortname, 'filter-ccc-class-startdate_eck', $this->filter);
        if (!empty($param_structure[0]['value'])) {
            $param_structure = php_report_filtering_get_active_filter_values($report_shortname, 'filter-ccc-class-startdate_edt', $this->filter);
            $param_value = $param_structure[0]['value'];
            $conditions[] = "cls.startdate <= {$param_value}";
        }

        //combine and return all conditions
        $condition = '';
        if (!empty($conditions)) {
            $condition = implode(' AND ', $conditions);
        } else {
            $condition = 'TRUE';
        }

        return $condition;
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
        // Obtain an sql clause that filters out users you shouldn't see
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
        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter3 = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter3 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }

        $instancefields = array('user' => 'u.id', 'curriculum' => 'cur.id',
                                'course' => 'cls.courseid', 'class' => 'cls.id');

        $field_joins = '';
        // Add joins related to CM custom user fields
        if (!empty($this->_customfieldids)) {
            $field_joins = $this->get_custom_field_sql($this->_customfieldids, $instancefields);
        }

        // Add joins related to CM custom user fields
        $class_join = $this->get_class_join_sql();

        // Check if we should include the curricula table (and associated joins)
        $showcurricula = $this->check_curricula();

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
                //determine the custom field id
                $fieldid = (int)substr($key, strlen('filter-ccc-curriculum-customfield-'));
                if ($showcurricula) {
                    //determine the field id and update with the appropriate condition
                    $this->filter->_fields[$key]->_extraconditions = 'AND u.id = cca.userid
                                                                      AND cur.id = cca.curriculumid';
                }

                //determine if a session value is set for this field
                if ($test_customfield = php_report_filtering_get_active_filter_values($this->get_report_shortname(), $key, $this->filter)) {
                    //signal that we are filtering by curriculum customfields
                    $filtering_cur_customfield = true;
                }
            }
        }

        //dynamically hande the class start / end date condition
        $startdate_condition = $this->get_class_startdate_condition();

        $status_sql = '';
        $status_join = '';
        $status_where = '';
        // Check if we need to check status
        if ($value = php_report_filtering_get_active_filter_values($this->get_report_shortname(), 'filter-completionstatus', $this->filter)) {
            $status = $value[0]['value'];
        }
        if (isset($status)) {
            $status_sql = ' AND EXISTS
                                (SELECT stu.userid
                                   FROM {'. student::TABLE .'} stu
                                '. $this->get_class_join_sql('WHERE')
                                 .' AND stu.userid = u.id
                                '. $this->get_class_status_sql($status) .')';
            $status_join = ' JOIN {'. student::TABLE .'} stu
                             ON stu.userid = u.id ';
            if (!empty($class_join)) {
                $status_join .= 'AND stu.classid = cls.id ';
            }
            $status_where = $this->get_class_status_sql($status);
        } else {
            $status = STUSTATUS_PASSED;
        }
        //error_log("UCCR::get_report_sql(): status = {$status}");

        $ccc_sql = $this->get_course_class_condition_sql();

        if ($showcurricula) {
            //obtain a filter clause that will not include anything related to cluster tree
            //which is sorted out via the main query and connecting the subqueries based on user id
            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            //subquery to retreive the number of credits
            $subquery = '(SELECT SUM(stu.credits)
                            FROM {'. student::TABLE .'} stu
                            JOIN {'. pmclass::TABLE .'} cls
                              ON stu.classid = cls.id
                       LEFT JOIN ({'. curriculumcourse::TABLE .'} curcrs
                                  JOIN {'. curriculumstudent::TABLE .'} curstu
                                    ON curcrs.curriculumid = curstu.curriculumid)
                              ON cls.courseid = curcrs.courseid
                             AND stu.userid = curstu.userid
                    -- LEFT JOIN {'. curriculum::TABLE ."} cur
                    --        ON curcrs.curriculumid = cur.id
                           WHERE curstu.id IS NULL
                             AND stu.userid = u.id
                             AND stu.completestatusid = {$status}
                             AND {$startdate_condition}
                          AND {$filter_clause})";

            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            //subquery to determine if one or more credit has been awarded
            $exists_query = 'SELECT * FROM {'. student::TABLE .'} stu
                               JOIN {'. pmclass::TABLE .'} cls
                                 ON stu.classid = cls.id
                          LEFT JOIN ({'. curriculumcourse::TABLE .'} curcrs
                                     JOIN {'. curriculumstudent::TABLE .'} curstu
                                       ON curcrs.curriculumid = curstu.curriculumid)
                                 ON cls.courseid = curcrs.courseid
                                AND stu.userid = curstu.userid
                       -- LEFT JOIN {'. curriculum::TABLE ."} cur
                       --        ON curcrs.curriculumid = cur.id
                              WHERE curstu.id IS NULL
                                AND stu.userid = u.id
                                AND {$startdate_condition}
                                AND {$filter_clause}";

            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            //subquery to determine if the current user has no class enrolments
            $stu_not_exists_query = 'SELECT * FROM {'. student::TABLE .'} stu
                                       JOIN {'. pmclass::TABLE ."} cls
                                         ON stu.classid = cls.id
                                      WHERE u.id = stu.userid
                                        AND {$startdate_condition}
                                        AND {$filter_clause}";

            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            //subquery to determine if the current user has no curriculum assignments
            $curr_not_exists_query = 'SELECT *
                                        FROM {'. curriculumstudent::TABLE .'} curstu
                                        JOIN {'. student::TABLE .'} stu
                                          ON curstu.userid = stu.userid
                                        JOIN {'. pmclass::TABLE .'} cls
                                          ON stu.classid = cls.id
                                     -- JOIN {'. curriculum::TABLE ."} cur
                                     --   ON curstu.curriculumid = cur.id
                                       WHERE u.id = curstu.userid
                                         AND {$filter_clause}";

            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            $credits_query = 'SELECT SUM(stu.credits)
                                FROM {'. student::TABLE .'} stu
                                JOIN {'. pmclass::TABLE .'} cls
                                  ON stu.classid = cls.id
                                JOIN {'. curriculumcourse::TABLE .'} curcrs
                                  ON cls.courseid = curcrs.courseid
                             -- JOIN {'. curriculum::TABLE ."} cur
                             --   ON curcrs.curriculumid = cur.id
                               WHERE stu.completestatusid = {$status}
                                 AND stu.userid = u.id
                                 AND cur.id = curcrs.curriculumid
                                 AND {$startdate_condition}
                                 AND {$filter_clause}";

            //showing curriculum columns, so determine curriculum associations
            //and tie total credits to the current curriculum
            $sql = "SELECT DISTINCT {$columns}, u.firstname, ({$credits_query}) AS numcredits, curstu.completed,
                           -- fields for links
                           cur.id AS curid,
                           -- fields for sorting
                           u.id AS userid, 1 AS preservecurname
                      FROM {". user::TABLE .'} u
                      JOIN {'. curriculumstudent::TABLE .'} curstu
                        ON u.id = curstu.userid
                      JOIN {'. curriculum::TABLE ."} cur
                        ON curstu.curriculumid = cur.id
                    {$class_join}
                    {$status_join}
                    {$field_joins}
                     WHERE ". table_report::PARAMETER_TOKEN ."
                    {$status_where}
                    {$status_sql}
                       AND {$permissions_filter1}

                    UNION ALL

                    SELECT DISTINCT {$columns}, u.firstname, ({$subquery}) AS numcredits, curstu.completed,
                           -- fields for links
                           cur.id AS curid,
                           -- fields for sorting
                           u.id AS userid,
                    EXISTS (SELECT * FROM {". student::TABLE .'} stu
                              JOIN {'. pmclass::TABLE ."} cls
                                ON stu.classid = cls.id {$ccc_sql}
                               AND {$startdate_condition}
                             WHERE u.id = stu.userid) AS preservecurname
                      FROM {". user::TABLE .'} u
                 LEFT JOIN {'. curriculumstudent::TABLE .'} curstu
                        ON curstu.id = 0
                 LEFT JOIN {'. curriculum::TABLE ."} cur
                        ON cur.id = 0
                    {$class_join}
                    {$status_join}
                    {$field_joins}
                     WHERE (EXISTS ({$exists_query})
                        OR (NOT EXISTS ({$stu_not_exists_query})
                            AND NOT EXISTS ({$curr_not_exists_query})
                           ))
                    {$status_where}
                    {$status_sql}
                       AND ". table_report::PARAMETER_TOKEN ."
                       AND {$permissions_filter2}";
        } else {
            list($filter_clause, $filter_params) = $this->get_filter_condition('', false);
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);

            //not showing curriculum columns
            if ($filtering_cur_customfield) {
                //filtering by curriculum customfield, so we need to join the curriculum ids between the sum
                //subquery and the filter condition

                //obtain a filter clause that will not include anything related to cluster tree
                //which is sorted out via the main query and connecting the subqueries based on user id
                $filter_clause = str_replace('u.id = cca.userid', 'u.id = cca.userid AND curcrs.curriculumid = cca.curriculumid', $filter_clause);

                $credits_query = 'SELECT SUM(stu.credits)
                                    FROM {'. student::TABLE .'} stu
                                    JOIN {'. pmclass::TABLE .'} cls
                                      ON stu.classid = cls.id
                                    JOIN {'. curriculumcourse::TABLE .'} curcrs
                                      ON cls.courseid = curcrs.courseid
                                 -- JOIN {'. curriculum::TABLE ."} cur
                                 --   ON cur.id = curcrs.curriculumid
                                   WHERE stu.completestatusid = {$status}
                                     AND stu.userid = u.id
                                 --  AND cur.id = curcrs.curriculumid
                                     AND {$startdate_condition}
                                     AND {$filter_clause}";
            } else {
                //obtain a filter clause that will not include anything related to cluster tree
                //which is sorted out via the main query and connecting the subqueries based on user id

                //non nonstandard filters / special cases
                $credits_query = 'SELECT SUM(stu.credits)
                                    FROM {'. student::TABLE .'} stu
                                    JOIN {'. pmclass::TABLE ."} cls
                                      ON stu.classid = cls.id
                                   WHERE stu.completestatusid = {$status}
                                     AND u.id = stu.userid
                                     AND {$startdate_condition}
                                     AND {$filter_clause}";
            }

            //not showing curricula, so ignore curriculum associations and
            //show global total for credits
            $sql = "SELECT DISTINCT {$columns}, u.firstname, ({$credits_query}) AS numcredits,
                           -- fields for links
                           0 AS curid,
                           -- fields for sorting
                           u.id AS userid
                      FROM {". user::TABLE ."} u
                    {$class_join}
                    {$status_join}
                    {$field_joins}
                     WHERE ". table_report::PARAMETER_TOKEN ."
                    {$status_where}
                    {$status_sql}
                       AND {$permissions_filter3}";
        }
        //error_log("UCCR::get_report_sql(): filter_clause = {$filter_clause}, sql = {$sql}");
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
     * Check if curricula values are displayed
     *
     * @uses $DB
     * @return bool True means there are curricula values to display.
     */
    function check_curricula() {
        global $DB;
        $customfields = array();

        if ($this->_show_curricula === null) {
            // Check for output columns
            $filters = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           'filter-summarycolumns', $this->filter);
            $cols = $filters[0]['value'];
            // Check if any curricula fields are displayed.
            foreach ($cols as $col => $value) {
                if ((substr($col, 0, 4) === 'cur_') && $value) {
                    $this->_show_curricula = true;
                    break;
                } else if ((substr($col, 0, 7) === 'custom_') && $value) {
                    $customfields[substr($col, 7)] = 1;
                }
            }
        }

        if ($this->_show_curricula === null) {
            $level = CONTEXT_ELIS_PROGRAM;
            // Check for custom fields to display
            $curriculumfields = $DB->get_records('elis_field_contextlevels',
                                         array('contextlevel' => $level));
            foreach ($curriculumfields as $field) {
                if (array_key_exists($field->fieldid, $customfields)) {
                    $this->_show_curricula = true;
                    break;
                }
            }
        }

        if ($this->_show_curricula === null) {
            // Check for filters
            $filters = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           'filter-ccc-curriculum-name', $this->filter);
            if (!empty($filters[0]['value'])) {
                $this->_show_curricula = true;
            }
        }

        if ($this->_show_curricula === null) {
            $this->_show_curricula = false;
        }

        return $this->_show_curricula;
    }

    /**
     * Specifies a URL fragment representing passing of parameters related to a specific
     * parameter group
     *
     * @param string $groupname The name of the appropriate group
     * @return string The url parameter fragment, in the form "key1=value1&..."
     */
    function get_param_url_string($groupname) {
        $result = '';
        //obtained the structure containing the data for this group
        $report_shortname = $this->get_report_shortname();
        $group_params_structure = php_report_filtering_get_active_filter_values(
                                      $report_shortname, $groupname,
                                      $this->filter);

        //convert to a flat associative array
        $group_params = $group_params_structure[0]['value'];

        //add all appropriate values to the string
        foreach ($group_params as $key => $value) {
            if (!empty($result)) {
                $result .= '&';
            }
            //use syntax for group element reference
            //$effective_key = "{$groupname}[{$key}]";
            $effective_key = "{$groupname}:{$key}";
            //url encode values just in case
            $effective_value = urlencode($value);
            $result .= "{$effective_key}={$effective_value}";
        }

        return $result;
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

        $showcurricula = $this->check_curricula();

        if (isset($record->id)) {
            //add a default curriculum name if appropriate
            if ($showcurricula && empty($record->name)) {
                if (!empty($record->preservecurname)) {
                    //actually corresponds to class enrolments
                    $record->name = get_string('noncurriculumcourses', $this->languagefile);
                } else {
                    //doesn't correspond to anything
                    $record->name = get_string('na', $this->languagefile);
                }
            }

            //link curriculum name to its "view" page if the the current record has a curriculum
            if ($export_format == table_report::$EXPORT_FORMAT_HTML && !empty($record->curid)) {
                $page = new curriculumpage(array('id' => $record->curid,
                                                 'action' => 'view'));
                if ($page->can_do()) {
                    $url = $page->url;
                    $record->name = '<span class="external_report_link">'.
                       "<a href=\"{$url}\" target=\"_blank\">{$record->name}</a></span>";
                }
            }

            //base url
            $url = "{$CFG->wwwroot}/blocks/php_report/render_report_page.php";

            //params being passed via url
            $url_params = array('report'      => 'user_class_completion_details',
                                'filter-up-idnumber'    => urlencode($record->useridnumber),
                                'filter-up-idnumber_op' => generalized_filter_text::$OPERATOR_IS_EQUAL_TO);

            //used to track whether to add a ? or a &
            $first = true;
            foreach ($url_params as $key => $value) {
                //append the parameter to the url
                if ($first) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $url .= "$key=$value";
                //signal the use of & on subsequent iterations
                $first = false;
            }

            //add parameters related to all these groups to the report
            $groupnames = array('filter-detailheaders',
                                'filter-detailcolumns');
            foreach ($groupnames as $groupname) {
                if ($first) {
                    $url .= '?';
                } else {
                    $url .= '&';
                }
                $url .= $this->get_param_url_string($groupname);
            }

            //extra attributes we are including in the anchor tag
            $tag_attributes = array(//'class'  => 'external_report_link',
                                    'target' => '_blank');

            //build the additional attributes
            $attribute_string = '';
            foreach ($tag_attributes as $key => $value) {
                $attribute_string .= " $key=$value";
            }

            //details label for the link.  First check to see if this is the first instance of the
            // student's record.  If so then show the details link.
            $link_text = '';
            if (0 != strcmp($this->student_id_num, $record->useridnumber)) {
                $link_text = get_string('details', $this->languagefile);
                $this->student_id_num = $record->useridnumber;
            }

            if ($export_format == table_report::$EXPORT_FORMAT_HTML) {
                //replace the empty column with the appropriate link
                $record->id = '<span class="external_report_link"><a href="'.
                              $url .'"'. $attribute_string .'>'. $link_text
                              .'</a></span>';
            } else {
                //in an export, so don't show link
                $record->id = '';
            }
        }

        //show link to user's profile based on capability to view the student management capability
        $fullname = fullname($record);

        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $userpage = new userpage(array('id' => $record->userid, 'action' => 'view'));
            if ($userpage->can_do()) {
                $record->lastname = '<span class="external_report_link"><a href="'
                                   . $userpage->url .'" target="_blank">'
                                   . $fullname .'</a></span>';
            } else {
                $record->lastname = $fullname;
            }
        } else {
            $record->lastname = $fullname;
        }

        //convert times to appropriate format
        if (!empty($record->timecompleted) && !empty($record->completed)) {
            $record->timecompleted = $this->format_date($record->timecompleted);
        } else {
            $record->timecompleted = get_string('na', $this->languagefile);
        }

        if (!empty($record->timeexpired) && !empty($record->completed)) {
             $record->timeexpired = $this->format_date($record->timeexpired);
        } else {
            $record->timeexpired = get_string('na', $this->languagefile);
        }

        //N/A for cretificate number if a valid one is not set
        if (empty($record->certificatecode) || empty($record->completed)) {
            $record->certificatecode = get_string('na', $this->languagefile);
        }

        //copy result of complex query into simple field
        if (!empty($record->numcredits)) {
            //use the provided value
            $record->displaynumcredits = $this->format_credits($record->numcredits);
        } else {
            //default to zero
            $record->displaynumcredits = $this->format_credits(0);
        }

        //handle custom field default values and display logic
        $this->transform_custom_field_data($record);

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

        //dependencies
        $this->require_dependencies();

        //make sure the current user has the appropriate scheduling capability for SOME user
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
        return array(array(219, 229, 241));
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

        if ($mform->elementExists($key.'_grp')) {
            $mform->addRule($key.'_grp', get_string('required'), 'required', null, 'client');
            $mform->registerRule('custom_rule','function','user_course_completion_check_custom_rule');
            $mform->addRule($key.'_grp', get_string('required'), 'custom_rule', array($key,$fields));
        }

        return $mform;
    }

    /**
     * Formats a timestamp as desired based on a language string and the user's time zone
     *
     * @param  int    $timestamp  The timestamp to format
     * @uses   $DB
     * @return string             The formatted date
     */
    function format_date($timestamp) {
        global $DB;
        //handle special cases
        if ($timestamp === '0') {
            //the zero timestamp
            return get_string('na', $this->languagefile);
        } else if (empty($timestamp)) {
            //NULL column
            return '';
        }

        //determine the format
        $format = get_string('date_format', $this->languagefile);

        //figure out the correct timezone
        $timezone = 99;

        if ($user_record = $DB->get_record('user', array('id' => $this->userid))) {
            //determine the user's timezone
            $timezone = php_report::get_user_timezone($user_record, $user_record->timezone);
        }

        //perform the formatting
        return userdate($timestamp, $format, $timezone, false);
    }

    function get_grouping_fields() {
        $user_grouping = new table_report_grouping('totals', '0', '', '',
                                                   array(), 'above', '1');
        return array($user_grouping);
    }

    function transform_grouping_header_label($grouping_current, $grouping, $datum, $export_format) {
        global $DB;

        $params = array();
        $labels = array();

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
        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter3 = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter3 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter4 = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter4 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }
        $filter_sql = $filter_obj->get_sql(false, 'u', SQL_PARAMS_NAMED);
        $permissions_filter5 = 'TRUE';
        if (isset($filter_sql['where'])) { // TBD
            $permissions_filter5 = $filter_sql['where'];
            $params = array_merge($params, $filter_sql['where_parameters']);
        }

        $class_join = $this->get_class_join_sql();

        //dynamically hande the class start / end date condition
        $startdate_condition = $this->get_class_startdate_condition();

        $status_clause = '';
        $status_sql    = '';
        $status_join   = '';
        $status_where  = '';
        // Check if we need to check status
        if ($value = php_report_filtering_get_active_filter_values(
                         $this->get_report_shortname(),
                         'filter-completionstatus', $this->filter)) {
            $status = $value[0]['value'];
        }
        if (isset($status)) {
            $status_clause = $this->get_class_status_sql($status);
            $status_sql = ' AND EXISTS (SELECT stu.userid
                                          FROM {'. student::TABLE .'} stu
                                     '. $this->get_class_join_sql('WHERE')
                                      ." AND stu.userid = u.id
                                        {$status_clause})";
            $status_join = ' JOIN {'. student::TABLE .'} stu
                             ON stu.userid = u.id ';
            if (!empty($class_join)) {
                $status_join .= 'AND stu.classid = cls.id ';
            }
            $status_where = $this->get_class_status_sql($status);
        } else {
            $status = STUSTATUS_PASSED;
        }

        $ccc_sql = $this->get_course_class_condition_sql();

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

        /**
         * Calculate the total number of distinct users in the report
         */
        if ($this->_show_curricula) {
            //we are showing curricula, so we need to figure out which users have
            //appropriate credits

            //subquery to retreive the number of credits
            $subquery = '(SELECT SUM(stu.credits)
                            FROM {'. student::TABLE .'} stu
                            JOIN {'. pmclass::TABLE ."} cls
                              ON stu.classid = cls.id {$ccc_sql}
                       LEFT JOIN ({". curriculumcourse::TABLE .'} curcrs
                                  JOIN {'. curriculumstudent::TABLE .'} curstu
                                    ON curcrs.curriculumid = curstu.curriculumid)
                              ON cls.courseid = curcrs.courseid
                             AND stu.userid = curstu.userid
                           WHERE curstu.id IS NULL
                             AND stu.completestatusid = '. STUSTATUS_PASSED ."
                             AND {$startdate_condition}
                             AND stu.userid = u.id)";

            //subquery to determine if one or more credit has been awarded
            $exists_query = 'SELECT * FROM {'. student::TABLE .'} stu
                               JOIN {'. pmclass::TABLE ."} cls
                                 ON stu.classid = cls.id {$ccc_sql}
                          LEFT JOIN ({". curriculumcourse::TABLE .'} curcrs
                                     JOIN {'. curriculumstudent::TABLE ."} curstu
                                       ON curcrs.curriculumid = curstu.curriculumid)
                                 ON cls.courseid = curcrs.courseid
                                AND stu.userid = curstu.userid
                              WHERE curstu.id IS NULL
                                AND {$startdate_condition}
                                AND stu.userid = u.id";

            //subquery to determine if the current user has no class enrolments
            $stu_not_exists_query = 'SELECT * FROM {'. student::TABLE .'} stu
                                       JOIN {'. pmclass::TABLE ."} cls
                                         ON stu.classid = cls.id {$ccc_sql}
                                      WHERE u.id = stu.userid
                                        AND {$startdate_condition} ";

            //subquery to determine if the current user has no curriculum assignments
            $curr_not_exists_query = 'SELECT *
                                        FROM {'. curriculumstudent::TABLE .'} curstu
                                       WHERE u.id = curstu.userid';

            $sql = 'SELECT COUNT(DISTINCT u.id)
                      FROM (SELECT u.id FROM {'. user::TABLE .'} u
                              JOIN {'. curriculumstudent::TABLE .'} curstu
                                ON u.id = curstu.userid
                              JOIN {'. curriculum::TABLE ."} cur
                                ON curstu.curriculumid = cur.id
                            {$class_join}
                            {$status_join}
                             WHERE ". table_report::PARAMETER_TOKEN ."
                            {$status_where}
                            {$status_sql}
                               AND {$permissions_filter1}

                            UNION ALL

                            SELECT u.id FROM {". user::TABLE .'} u
                         LEFT JOIN {'. curriculumstudent::TABLE .'} curstu
                                ON curstu.id = 0
                         LEFT JOIN {'. curriculum::TABLE ."} cur
                                ON cur.id = 0
                            {$class_join}
                            {$status_join}
                             WHERE (EXISTS ({$exists_query}) OR
                                    (NOT EXISTS ({$stu_not_exists_query})
                                     AND NOT EXISTS ({$curr_not_exists_query})
                                   ))
                            {$status_where}
                               AND ". table_report::PARAMETER_TOKEN ."
                            {$status_sql}
                               AND {$permissions_filter2}
                     ) u";
        } else {
            //we are not showing curricula, so this is a simple count of users
            //based on filters
            $sql = 'SELECT COUNT(DISTINCT u.id) FROM {'. user::TABLE ."} u
                    {$class_join}
                    {$status_join}
                     WHERE ". table_report::PARAMETER_TOKEN ."
                    {$status_where}
                    {$status_sql}
                    AND {$permissions_filter1}";
        }

        //add filtering to query
        list($sql, $params) = $this->get_complete_sql_query(false, $sql, $params);
        //obtain the actual number
        $num_users = $DB->get_field_sql($sql, $params);
        //create the header item
        if ($export_format == table_report::$EXPORT_FORMAT_HTML) {
            $text_label = get_string('grouping_learners', $this->languagefile);
        } else {
            $text_label = get_string('grouping_learners_csv', $this->languagefile);
        }
        $labels[] = $this->add_grouping_header($text_label, $num_users, $export_format);

        /**
         * Calculate the total number of distinct credits in the report
         */
        if ($this->_show_curricula || $filtering_cur_customfield) {
            //we are showing curricula, so we need to account for curriculum and
            //non-curriculum cases
            //calculate the appropriate filter caluse
            list($filter_clause, $filter_params) = $this->get_filter_condition('');
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }
            $params = array_merge($params, $filter_params);
            if (!$this->_show_curricula && $filtering_cur_customfield) {
                //special case: filtering by customfields when showing no curriculum info
                $filter_clause = str_replace('u.id = cca.userid', 'u.id = cca.userid AND curcrs.curriculumid = cca.curriculumid', $filter_clause);
            }

            $curriculum_sql = 'SELECT SUM(credits) FROM (
                                   SELECT DISTINCT u.id AS userid, cls.id AS classid, stu.credits
                                     FROM {'. user::TABLE .'} u
                                     JOIN {'. student::TABLE ."} stu
                                       ON u.id = stu.userid {$status_clause}
                                     JOIN {". pmclass::TABLE .'} cls
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
                                      AND {$permissions_filter3}
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
                                        JOIN {". pmclass::TABLE ."} cls
                                          ON stu.classid = cls.id {$ccc_sql}
                                   LEFT JOIN ({". curriculumcourse::TABLE .'} curcrs
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
                                         AND {$permissions_filter4}
                                         AND {$startdate_condition}";
                //add filtering to the query
                list($noncurriculum_sql, $noncurriculum_params) = $this->get_complete_sql_query(false, $noncurriculum_sql, $params);
                //obtain the actual number
                $noncurriculum_num = $DB->get_field_sql($noncurriculum_sql, $noncurriculum_params);
            }

            //total credits is the sum of curriculum and non-curriculum credits
            $num_credits = (float)$curriculum_num + (float)$noncurriculum_num;
        } else {
            //we are not showing curricula, so this is simply an aggregation over class
            //enrolments, based on filters
            $sql = 'SELECT SUM(stu.credits) FROM {'. user::TABLE .'} u
                      JOIN {'. student::TABLE ."} stu
                        ON u.id = stu.userid {$status_clause}
                      JOIN {". pmclass::TABLE ."} cls
                        ON stu.classid = cls.id {$ccc_sql}
                     WHERE stu.completestatusid = ". STUSTATUS_PASSED .'
                       AND '. table_report::PARAMETER_TOKEN ."
                       AND {$permissions_filter5}
                       AND {$startdate_condition}";
            list($sql, $params) = $this->get_complete_sql_query(false, $sql, $params);
            $num_credits = $DB->get_field_sql($sql, $params);
        }

        //show number of credits to two decimals
        $num_credits = $this->format_credits($num_credits);

        //create the header item
        $text_label = get_string('grouping_credits', $this->languagefile);
        $labels[] = $this->add_grouping_header($text_label, $num_credits, $export_format);

        return $labels;
    }

    /**
     * Converts a number of credits to the appropriate display format
	 * @param int $num_credits The value to be formatted
	 * @return string The formatted version of the provided value
	 */
    function format_credits($num_credits) {
        //show two decimals, with no thousands separator
        return number_format($num_credits, 2, '.', '');
    }

    /**
     * Get Header Entries
     *
     * @return array The headers for the report.
     */
    function get_header_entries($export_format) {
        $header_array = array();
        $shortname = $this->get_report_shortname();

        $dateformat = get_string('strftimedate');
        $filters    = php_report_filtering_get_active_filter_values($shortname, 'filter-title', $this->filter);
        $title      = $filters[0]['value'];

        $header_obj = new stdClass;
        $header_obj->label = '';
        $header_obj->value = '';
        if (!empty($title)) {

            $site = get_site();
            $site_fullname = $site->fullname;

            $startdate = get_string('past', $this->languagefile);
            $filters   = php_report_filtering_get_active_filter_values($shortname, 'filter-ccc-class_startdate_sck', $this->filter);

            if (! empty($filters[0]['value'])) {
                $filters = php_report_filtering_get_active_filter_values($shortname, 'filter-ccc-class_startdate_sdt', $this->filter);

                if (is_array($filters)) {
                    $startdate = userdate($filters[0]['value'], $dateformat);
                }
            }

            $enddate  = get_string('present', $this->languagefile);
            $filters  = php_report_filtering_get_active_filter_values($shortname, 'filter-ccc-class_startdate_eck', $this->filter);

            if (! empty($filters[0]['value'])) {
                $filters = php_report_filtering_get_active_filter_values($shortname, 'filter-ccc-class_startdate_edt', $this->filter);

                if (is_array($filters)) {
                    $enddate = userdate($filters[0]['value'], $dateformat);
                }
            }

            $title = str_replace('%%site%%', $site_fullname, $title);
            $title = str_replace('%%startdate%%', $startdate, $title);
            $title = str_replace('%%enddate%%', $enddate, $title);
            $header_obj->value = $title;
        }
        $header_obj->css_identifier = 'custom_title';
        $header_obj->value = nl2br($header_obj->value);
        $header_array[] = $header_obj;

        return $header_array;
    }

    /**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        return array(php_report::$EXPORT_FORMAT_CSV);
    }

    /**
     * Constructs an appropriate order by clause for the main query
     *
     * @return  string  The appropriate order by clause
     */
    function get_order_by_clause() {
        //always want to start by sorting on user info
        $result = " ORDER BY lastname, firstname, userid";

        //determine whether we're showing the curriculum name
        $filters = php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(), 'filter-summarycolumns',
                       $this->filter);
        $cols = $filters[0]['value'];

        if ($cols['cur_name']) {
            //also sort on curriculum name if displayed
            $result .= ", curid IS NULL, name";
        }
        return $result;
    }

    /**
     * Takes a record representing a report row and transforms its custom field data,
     * including adding defaults and handling any specific rendering work
     */
    function transform_custom_field_data($record) {
        $excluded_fieldids = array();

        //set up default custom field values if appropriate
        foreach ($this->_defaultfieldvalues as $fieldid => $defaultvalue) {
            $key = "customfielddata_{$fieldid}";
            if (is_null($record->$key)) {
                $record->$key = $defaultvalue;
            }
        }

        //make sure we don't display curriculum custom field default for non-curriculum entries
        foreach ($this->_fielddatatypes as $fieldid => $datatype) {
            if (empty($record->curid) && $this->_fielddatacontexts[$fieldid] == 'curriculum') {
                $key = "customfielddata_{$fieldid}";
                $record->$key = get_string('na', $this->languagefile);
                $excluded_fieldids[] = $fieldid;
            }
        }

        //handle checkbox yes/no display if needed
        //ELIS-5862: add transformation of datetime custom fields too
        foreach ($this->_fielddatatypes as $fieldid => $datatype) {
            //make sure we don't set override N/A for curriculum fields in non-curriculum records
            if (!in_array($fieldid, $excluded_fieldids)) {

                $key = "customfielddata_{$fieldid}";
                if ($datatype == 'bool') {
                    $record->$key = !empty($record->$key) ? get_string('yes') : get_string('no');
                } else if (array_key_exists($fieldid, $this->_datetimefields)) {
                    $record->$key = $this->userdate($record->$key,
                                        get_string(
                                            $this->_datetimefields[$fieldid]
                                            ? 'customfield_datetime_format'
                                            : 'customfield_date_format',
                                            $this->languagefile));
                }
            }
        }
    }

    /**
     * Initialize the filter object
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     *
     */
    function init_filter($id, $init_data = true) {
        global $CFG;
        if (!isset($this->filter) && $filters = $this->get_filters($init_data)) {
            $dynamic_report_filter_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
            $this->filter = new clustertree_optional_filtering($filters, $dynamic_report_filter_url, null, $id, $this->get_report_shortname());
        }
    }

    /**
     * Calculates the entirety of the SQL condition created by report filters
     * for the current report instance being execute, including the leading AND or WHERE token
     * (modified from base class to allow for disabling of the clustertree filter in subqueries
     *  for performance reasons)
     *
     * @param   string   $conditional_symbol   the leading token (should be AND or WHERE)
     * @param   boolean  $include_clustertree  true if we want to apply the clustertree filter, othwerwise false
     *
     * @return  string                         the appropriate SQL condition
     */
    function get_filter_condition($conditional_symbol, $include_clustertree = true) {
        $result = '';
        $sql_params = array();
        //error checking
        if(!empty($this->filter)) {
            //run the calculation

            //pass along whether we're wanting to include the clustertree filter (modified from base class)
            list($sql_filter, $sql_params) =
                $this->filter->get_sql_filter('', array(),
                           $this->allow_interactive_filters(),
                           $this->allow_configured_filters(), '',
                           $include_clustertree);
            if(!empty($sql_filter)) {
                //one or more filters are active
                $result .= " {$conditional_symbol} ({$sql_filter})";
            }
        }
        return array($result, $sql_params);
    }
}

/**
 * Class that allows us to disable the use of the clustertree filter for performance reasons
 */
class clustertree_optional_filtering extends php_report_default_capable_filtering {
    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param boolean $include_clustertree true if we want to include the clustertree filter, otherwise false
     * @return string
     */
    function get_sql_filter($extra='', $exceptions = array(),
                            $allow_interactive_filters = false,
                            $allow_configured_filters = true,
                            $secondary_filtering_key = '',
                            $include_clustertree = true) {

        if (isset($this->secondary_filterings[$secondary_filtering_key])) {
            //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() isset(this->secondary_filterings['{$secondary_filtering_key}'])");
            //if this is not the primary filtering, use a secondary one
            return $this->secondary_filterings[$secondary_filtering_key]->get_sql_filter($extra, $exceptions, $allow_interactive_filters, $allow_configured_filters);
        }

        $params = array();

        //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() - allow_interactive_filters = {$allow_interactive_filters}");
        //interactive filters, if applicable
        if ($allow_interactive_filters) {
            list($result, $params) = parent::get_sql_filter($extra, $exceptions);
        } else {
            $result = '';
        }

        //if configured filters are not enabled for this report, just use interactive filtering,
        //if applicable
        if (!$allow_configured_filters) {
            //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() - !allow_configured_filters => returning: array({$result}, params);");
            return array($result, $params);
        }

        $sqls = array();

        //obtain the pool of attributes to pull preferences from
        $per_filter_data = $this->get_preferences();
        //print_object($per_filter_data);

        //grab the SQL filters
        foreach ($this->_fields as $shortname => $field) {
            //added condition: allow for disabling of the clustertree filter type
            if (!($field instanceof generalized_filter_clustertree) ||
                $include_clustertree) {
                //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() -> {$shortname}");
                if (isset($per_filter_data[$shortname])) {
                    $formatted_data = $field->check_data((object)$per_filter_data[$shortname]);
                    if ($formatted_data != false) {
                        $newsql = $field->get_sql_filter($formatted_data);
                        if (!empty($newsql) && !empty($newsql[0])) {
                            $sqls[] = $newsql[0];
                            if (!empty($newsql[1]) && is_array($newsql[1])) {
                                $params = array_merge($params, $newsql[1]);
                            }
                        }
                    } else {
                        //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() - formatted_data == FALSE");
                    }
                } else {
                    //error_log("/blocks/php_report/instances/UCC::clustertree_optional_filtering::get_sql_filter() NOT isset(per_filter_data[{$shortname}])");
                }
            }
        }

        //combine SQL conditions
        if (!empty($sqls)) {
            $sql_piece = implode(' AND ', $sqls);
            if ($result == '') {
                $result = $sql_piece;
            } else {
                $result .= ' AND '. $sql_piece;
            }
        }

        return array($result, $params);
    }
}

