<?php

require_once($CFG->dirroot . '/blocks/php_report/type/icon_report.class.php');

class brendantesticon_report extends icon_report {

    /**
     * Defines the sample system-oriented measures for this report
     * 
     * @return   icon_report_entry array  A mapping of field names to report entries 
     */
    function get_default_data() {
        $data = array();
        
        $data['num_users']       = new icon_report_entry(get_string('column_num_users', 'rlreport_brendantesticon'),
                                                         0,
                                                         'logo');

        $data['num_courses']     = new icon_report_entry(get_string('column_num_courses', 'rlreport_brendantesticon'),
                                                         0,
                                                         'logo');

        $data['num_log_entries'] = new icon_report_entry(get_string('column_num_log_entries', 'rlreport_brendantesticon'),
                                                         0,
                                                         'logo');

        $data['num_quiz_entries'] = new icon_report_entry(get_string('column_num_quiz_entries', 'rlreport_brendantesticon'),
                                                          0,
                                                          'logo');
                                                          
        return $data;                                                          
    }

    /**
     * Specifies the SQL query to obtain the number of users on this site
     * 
     * @param   boolean  $use_filters  Flag that can be set to false to ignore filters
     * 
     * @return  string                 The calculated SQL query
     */
    function get_num_users_sql(&$use_filters) {
        global $CFG;
        
        return "SELECT COUNT(*)
                FROM
                {$CFG->prefix}user";
    }

    /**
     * Specifies the SQL query to obtain the number of courses on this site
     * 
     * @param   boolean  $use_filters  Flag that can be set to false to ignore filters
     * 
     * @return  string                 The calculated SQL query
     */
    function get_num_courses_sql(&$use_filters) {
        global $CFG;
        
        return "SELECT COUNT(*)
                FROM
                {$CFG->prefix}course";
    }
    
    /**
     * Specifies the SQL query to obtain the number of log entries on this site
     * 
     * @param   boolean  $use_filters  Flag that can be set to false to ignore filters
     * 
     * @return  string                 The calculated SQL query
     */
    function get_num_log_entries_sql(&$use_filters) {
        global $CFG;
        
        return "SELECT COUNT(*)
                FROM
                {$CFG->prefix}log";
    }
    
    /**
     * Specifies the SQL query to obtain the number of quizzes on this site
     * 
     * @param   boolean  $use_filters  Flag that can be set to false to ignore filters
     * 
     * @return  string                 The calculated SQL query
     */
    function get_num_quiz_entries_sql(&$use_filters) {
        global $CFG;
        
        return "SELECT COUNT(*)
                FROM
                {$CFG->prefix}quiz";
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

    /**
     * Specifies whether this report definition has one or more
     * filters defined for it
     *
     * @return  boolean  true if one more more filters are defined, or false if none are
     */
    function has_filters() {
        return false;
    }
}

?>