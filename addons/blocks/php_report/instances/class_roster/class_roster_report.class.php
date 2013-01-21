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
 * @subpackage pm-blocks-phpreport-class_roster
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');

class class_roster_report extends table_report {
    var $lang_file = 'rlreport_class_roster';

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_CLASS;
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
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/course.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');

        //needed for options filters
        require_once($CFG->dirroot .'/elis/program/lib/filtering/courseclassselect.php');

        require_once($CFG->dirroot .'/elis/program/userpage.class.php');
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

        // Fetch array of allowed classes
        $classes_array = array();
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
        $cmclasses = pmclass_get_listing('crsname', 'ASC', 0, 0, '', '', 0, false, $contexts);
        foreach ($cmclasses as $cmclass) {
            $classes_array[$cmclass->id] = $cmclass->crsname .' - '. $cmclass->idnumber;
        }
        unset($cmclasses);

        $class_filter = new generalized_filter_entry('classid', 'cls', 'id',
                                  get_string('filter_course_class', $this->lang_file),
                                  false, 'courseclassselect',
                                  array('default'     => NULL,
                                        'isrequired'  => true,
                                        'report_path' => $CFG->wwwroot .'/blocks/php_report/instances/class_roster/',
                                        'help' => array('class_roster_courseclass',
                                                        get_string('filter_course_class', $this->lang_file),
                                                        $this->lang_file)
                                       )
                              );

        //columns checkboxes
        $userset_label = get_string('column_option_cluster', $this->lang_file);
        $idnumber_label = get_string('column_option_idnumber', $this->lang_file);
        $username_label = get_string('column_option_username', $this->lang_file);
        $mi_label = get_string('column_option_mi', $this->lang_file);
        $email2_label = get_string('column_option_email2', $this->lang_file);
        $address_label = get_string('column_option_address', $this->lang_file);
        $address2_label = get_string('column_option_address2', $this->lang_file);
        $city_label = get_string('column_option_city', $this->lang_file);
        $state_label = get_string('column_option_state', $this->lang_file);
        $postalcode_label = get_string('column_option_postalcode', $this->lang_file);
        $country_label = get_string('column_option_country', $this->lang_file);
        $phone_label = get_string('column_option_phone', $this->lang_file);
        $phone2_label = get_string('column_option_phone2', $this->lang_file);
        $fax_label = get_string('column_option_fax', $this->lang_file);
        $birthdate_label = get_string('column_option_birthdate', $this->lang_file);
        $gender_label = get_string('column_option_gender', $this->lang_file);
        $language_label = get_string('column_option_language', $this->lang_file);
        $transfercredits_label = get_string('column_option_transfercredits', $this->lang_file);
        $comments_label = get_string('column_option_comments', $this->lang_file);
        $notes_label = get_string('column_option_notes', $this->lang_file);
        $inactive_label = get_string('column_option_inactive', $this->lang_file);
        $heading_label = get_string('columns_options_heading', $this->lang_file);

        $choices = array('cluster'         => $userset_label,
                         'idnumber'        => $idnumber_label,
                         'username'        => $username_label,
                         'mi'              => $mi_label,
                         'email2'          => $email2_label,
                         'address'         => $address_label,
                         'address2'        => $address2_label,
                         'city'            => $city_label,
                         'state'           => $state_label,
                         'postalcode'             => $postalcode_label,
                         'country'         => $country_label,
                         'phone'           => $phone_label,
                         'phone2'          => $phone2_label,
                         'fax'             => $fax_label,
                         'birthdate'       => $birthdate_label,
                         'gender'          => $gender_label,
                         'language'        => $language_label,
                         'transfercredits' => $transfercredits_label,
                         'comments'        => $comments_label,
                         'notes'           => $notes_label);
        if (elis::$config->elis_program->legacy_show_inactive_users) {
            $choices['inactive'] = $inactive_label;
        }

        $checked = array();
        $advanced = array('cluster',
                         'idnumber',
                         'username',
                         'mi',
                         'email2',
                         'address',
                         'address2',
                         'city',
                         'state',
                         'postalcode',
                         'country',
                         'phone',
                         'phone2',
                         'fax',
                         'birthdate',
                         'gender',
                         'language',
                         'transfercredits',
                         'comments',
                         'notes');
        if (elis::$config->elis_program->legacy_show_inactive_users) {
            $advanced[] = 'inactive';
        }

        $columns_options = array('choices'    => $choices,
                                 'checked'    => $checked,
                                 'advanced'   => $advanced,
                                 'allowempty' => true,
                                 'heading'    => $heading_label,
                                 'nofilter'   => true,
                                 'help' => array('class_roster_options',
                                                        get_string('filter_options', $this->lang_file),
                                                        $this->lang_file));

        //columns checkboxes
        $columns_heading = get_string('columns_options_heading', $this->lang_file);
        $columns_filter = new generalized_filter_entry('columns', '', '', $columns_heading, false,
                                                       'checkboxes',   $columns_options);


        return array($class_filter, $columns_filter);
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     *
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        $student = new table_report_column('usr.lastname AS r_student',
                             get_string('column_student', $this->lang_file),
                             'cssstudent', 'left', true, true, true,
                             array(php_report::$EXPORT_FORMAT_PDF, php_report::$EXPORT_FORMAT_HTML));
        $studentlastname = new table_report_column('usr.lastname AS lastname',
                             get_string('column_student_lastname', $this->lang_file),
                             'cssstudent', 'left', true, true, true,
                             array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));
        $studentfirstname = new table_report_column('usr.firstname AS firstname',
                             get_string('column_student_firstname', $this->lang_file),
                             'cssstudent', 'left', true, true, true,
                             array(php_report::$EXPORT_FORMAT_CSV, php_report::$EXPORT_FORMAT_EXCEL));
        $email = new table_report_column('usr.email AS r_email',
                                             get_string('column_email', $this->lang_file),
                                             'cssemail', 'left', true);
        $result = array($student, $studentlastname, $studentfirstname, $email);
        $advanced = array('cluster',
                         'idnumber',
                         'username',
                         'mi',
                         'email2',
                         'address',
                         'address2',
                         'city',
                         'state',
                         'postalcode',
                         'country',
                         'phone',
                         'phone2',
                         'fax',
                         'birthdate',
                         'gender',
                         'language',
                         'transfercredits',
                         'comments',
                         'notes');
        if (elis::$config->elis_program->legacy_show_inactive_users) {
            $advanced[] = 'inactive';
        }
        foreach($advanced as $column) {
            $value = php_report_filtering_get_active_filter_values($this->get_report_shortname(),
                                                                "columns_".$column,$this->filter);
            if (($value[0]['value'] != 0)) {
                $optional_cols[$column] = $value[0]['value'];
                //add columns
                if ($column == 'cluster') {
                    $result[] = new table_report_column('GROUP_CONCAT(DISTINCT clst.name ORDER BY clst.name ASC SEPARATOR \',\') AS r_clst_name',
                                get_string('column_option_cluster', $this->lang_file),
                                'csscluster', 'left', true);
                } else {
                    $result[] = new table_report_column('usr.'.$column.' AS r_'.$column,
                                get_string('column_option_'.$column, $this->lang_file),
                                'css'.$column, 'left', true);
                }
            }
        }

        return $result;
    }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  A string representing sorting by user id
     */
    function get_default_sort_field() {
        return 'r_student';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  A string representing a descending sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    function get_header_entries($export_format) {
        global $CFG;

        $header_array = array();

        // Add a course/class name if available
        $classid = 0;
        $cls_setting = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(), 'classid',
                           $this->filter);

        $cmclass = null;
        if (!empty($cls_setting[0]['value'])) {
            $classid = $cls_setting[0]['value'];
            $cmclass = new pmclass($classid);

            // Course name
            $header_obj = new stdClass;
            $header_obj->label = get_string('header_course', $this->lang_file).':';
            $header_obj->value = $cmclass->course->name;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;

            // Class name
            $header_obj = new stdClass;
            $header_obj->label = get_string('header_class', $this->lang_file).':';
            $header_obj->value = $cmclass->idnumber;
            $header_obj->css_identifier = '';
            $header_array[] = $header_obj;
        }

        // If we are displaying a class, show date range and instructors
        if (!empty($classid)) {
            // Add dates if available
            if (!empty($cmclass)) {
                $cmclass = $cmclass->to_object(); // TBD: no date data w/o?!?!

                //error_log("class_roster::get_header_entries() dates: {$startdate} ~ {$enddate}");
                // Add start date if available
                if (!empty($cmclass->startdate)) {
                    $header_obj = new stdClass;
                    $header_obj->label = get_string('header_start_date', $this->lang_file) .':';
                    $header_obj->value = $this->pmclassdate($cmclass, 'start');
                    $header_obj->css_identifier = '';
                    $header_array[] = $header_obj;
                }

                // Add end date if available
                if (!empty($cmclass->enddate)) {
                    $header_obj = new stdClass;
                    $header_obj->label = get_string('header_end_date', $this->lang_file) .':';
                    $header_obj->value = $this->pmclassdate($cmclass, 'end');
                    $header_obj->css_identifier = '';
                    $header_array[] = $header_obj;
                }
            }

            // Add instructor names
            $instructor_records = instructor::get_instructors($classid);
            if (!empty($instructor_records)) {
                $instructors = '';
                foreach ($instructor_records as $record) {
                    $userpage = new userpage(array('id' => $record->id, 'action' => 'view'));
                    $instructors .= '<span class="external_report_link"><a href="'
                                    . $userpage->url .'">'. fullname($record)
                                    .'</a></span><br />';
                }

                $header_obj = new stdClass;
                $header_obj->label = get_string('header_instructors',$this->lang_file).':';
                $header_obj->value = ($instructors == '') ? 'Not Available'
                                                          : $instructors;
                $header_obj->css_identifier = '';
                $header_array[] = $header_obj;
            }
        }

        return $header_array;
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
        $user = new stdClass;
        $user->firstname = $record->firstname;
        $user->lastname = $record->r_student;
        $fullname = fullname($user);

        if (property_exists($record,'r_clst_name')) {
            //reformat userset name(s)
            if (empty($record->r_clst_name)) {
                $record->r_clst_name = get_string('na','block_php_report');
            } elseif ($export_format == php_report::$EXPORT_FORMAT_EXCEL ||
                $export_format == php_report::$EXPORT_FORMAT_CSV) {
                $record->r_clst_name = str_replace(',','/',$record->r_clst_name);
            } else {
                $record->r_clst_name = str_replace(',','<br>',$record->r_clst_name);
            }
        }

        if (isset($record->r_country)) {
            //get readable country
            $countries = get_string_manager()->get_list_of_countries();
            if (isset($countries[$record->r_country])) {
                $record->r_country = $countries[$record->r_country];
            }
        }

        if (isset($record->r_language)) {
            //get readable language
            $languages = get_string_manager()->get_list_of_languages();
            if (isset($languages[$record->r_language])) {
                $record->r_language = $languages[$record->r_language];
            }
        }

        if (isset($record->r_birthdate)) {
            //reformat the birthdate
            $record->r_birthdate = date('d F Y', strtotime($record->r_birthdate));
        }

        if (isset($record->r_gender)) {
            //get readable gender
            $record->r_gender = $record->r_gender == 'F' ? get_string('female','elis_program'):get_string('male','elis_program');
        }

        if (isset($record->r_inactive)) {
            //reformat inactive
            $record->r_inactive = $record->r_inactive ? get_string('yes'):get_string('no');
        }

        if ($export_format == php_report::$EXPORT_FORMAT_HTML) {
            $userpage = new userpage(array('id' => $record->cmuserid, 'action' => 'view'));
            $record->r_student = '<span class="external_report_link"><a href="'
                                 . $userpage->url .'">' . $fullname .'</a></span>';
        } else if ($export_format != php_report::$EXPORT_FORMAT_CSV &&
                   $export_format != php_report::$EXPORT_FORMAT_EXCEL) {
            $record->r_student = $fullname;
        }

        return $record;
    }

    /**
     * Specifies an SQL statement that will produce the required report
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement with optional params
     */
    function get_report_sql($columns) {
        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);

        //make sure we only count classes within those contexts
        $filter_obj = $contexts->get_filter('id', 'class');
        $filter_sql = $filter_obj->get_sql(false, 'cls', SQL_PARAMS_NAMED);
        $where = array();
        $params = array();
        if (isset($filter_sql['where'])) {
            $where[] = $filter_sql['where'];
            $params = $filter_sql['where_parameters'];
        }

        $firstname = 'usr.firstname AS firstname';
        if (stripos($columns, $firstname) === FALSE) {
            $columns .= ", {$firstname}";
        }
        $sql = "SELECT {$columns}, usr.id AS cmuserid
                FROM {". student::TABLE .'} clsenr
                JOIN {'. user::TABLE .'} usr
                    ON usr.id = clsenr.userid
           LEFT JOIN {'. clusterassignment::TABLE .'} usrclst
                    ON usr.id = usrclst.userid
           LEFT JOIN {'. userset::TABLE .'} clst
                    ON usrclst.clusterid = clst.id
                JOIN {'. pmclass::TABLE .'} cls
                    ON cls.id = clsenr.classid
           LEFT JOIN {'. course::TABLE .'} crs
                    ON crs.id = cls.courseid

               ';

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $where[] = 'usr.inactive = 0';
        }
        if (!empty($where)) {
            $sql .= 'WHERE '. implode(' AND ', $where);
        }
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
        return 'clsenr.userid';
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

        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one class context
        $contexts = get_contexts_by_capability_for_user('class', $this->access_capability, $this->userid);
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
            $mform->addGroupRule($elem, get_string('required'), 'required', null, 2, 'client'); // TBV
        }
        return $mform;
    }
}

