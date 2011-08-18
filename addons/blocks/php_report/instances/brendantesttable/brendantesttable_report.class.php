<?php

require_once($CFG->dirroot . '/blocks/php_report/type/table_report.class.php');
require_once($CFG->dirroot . '/curriculum/lib/filtering/lib.php');
require_once($CFG->dirroot . '/curriculum/lib/filtering/clusterselect.php');
require_once($CFG->dirroot . '/curriculum/lib/filtering/clustertext.php');
require_once($CFG->dirroot . '/curriculum/lib/filtering/simpleselect.php');

class brendantesttable_report extends table_report {

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
        return array(new generalized_filter_entry('timecreated', 'u', 'firstaccess', get_string('filter_time_created', 'rlreport_brendantesttable'), false, 'date'),
                     new generalized_filter_entry('auth', 'u', 'auth', get_string('filter_auth', 'rlreport_brendantesttable'), false, 'equalityselect', array('choices' => array('manual' => 'manual', 'other' => 'other'), 'default' => null, 'numeric' => 0)),
                     new generalized_filter_entry('profileselect', 'u', 'id', get_string('filter_profile_field', 'rlreport_brendantesttable'), false, 'profileselect', array('profilefieldname' => 'awesomefield', 'default' => null)),
                     new generalized_filter_entry('confirmed', 'u', 'confirmed', get_string('filter_confirmed', 'rlreport_brendantesttable'), false, 'simpleselect', array('choices' => array(0 => 'Not Confirmed', 1 => 'Confirmed'), 'numeric' => false)),
                     new generalized_filter_entry('firstname', 'u', 'firstname', get_string('filter_firstname', 'rlreport_brendantesttable'), false, 'text'),
                     new generalized_filter_entry('deleted', 'u', 'deleted', get_string('filter_deleted', 'rlreport_brendantesttable'), false, 'yesno', array('numeric' => true))
                    );
    }

    /**
     * Method that specifies the report's columns
     * (specifies various user-oriented fields)
     * 
     * @return  table_report_column array  The list of report columns
     */
    function get_columns() {
        return array(new table_report_column('u.id AS uid', get_string('column_userid', 'rlreport_brendantesttable'), 'userid', 'left', true),
                     new table_report_column('u.firstname', get_string('column_firstname', 'rlreport_brendantesttable'), 'firstname', 'left', true),
                     new table_report_column('u.lastname', get_string('column_lastname', 'rlreport_brendantesttable'), 'lastname', 'left', true),
                     new table_report_horizontal_bar_column('u.deleted AS testdeleted', get_string('column_bar_userid', 'rlreport_brendantesttable'), 'bar_userid', 'u.id AS totaluid', 'left', '$p'),
                     new table_report_horizontal_bar_column('u.deleted', get_string('column_bar_another', 'rlreport_brendantesttable'), 'bar_another', 'u.timemodified AS tm', 'left', '$p'));
    }

    /**
     * Specifies a field to sort by default
     * 
     * @return  string  A string representing sorting by user id
     */
    function get_default_sort_field() {
        return 'u.id AS uid';
    }

    /**
     * Specifies a default sort direction for the default sort field
     * 
     * @return  string  A string representing a descending sort order
     */
    function get_default_sort_order() {
        return 'DESC';
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignments
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  string            The report's main sql statement
     */
    function get_report_sql($columns) {
        global $CFG;

        return "SELECT {$columns} FROM
                {$CFG->prefix}user u";
    }
    
    /**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        //just a test report, so allow various export formats
        return array(php_report::$EXPORT_FORMAT_PDF,
                     php_report::$EXPORT_FORMAT_CSV);
    }

    /**
     * Specifies whether the current report is available
     * (a.k.a. any other components it requires are installed)
     *
     * @return  boolean  True if the report is available, otherwise false
     */
    function is_available() {
        //this is just a sample, so disable it
        return FALSE;
    }
}

?>