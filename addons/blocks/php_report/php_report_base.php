<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once($CFG->dirroot . '/blocks/php_report/lib/filtering.php');

/**
 * Base class for the various report types
 */
abstract class php_report {

    //defined export formats
    static $EXPORT_FORMAT_HTML  = 'html';
    static $EXPORT_FORMAT_PDF   = 'pdf';
    static $EXPORT_FORMAT_CSV   = 'csv';
    static $EXPORT_FORMAT_EXCEL = 'excel';
    static $EXPORT_FORMAT_LABEL = 'label';

    // progress bar indicator for pdf format
    static $EXPORT_FORMAT_PDF_BAR = 'BAR|';

    // report categories
    const CATEGORY_ADMIN = 'admin_reports';
    const CATEGORY_CURRICULUM = 'curriculum_reports';
    const CATEGORY_COURSE = 'course_reports';
    const CATEGORY_CLASS = 'class_reports';
    const CATEGORY_CLUSTER = 'cluster_reports';
    const CATEGORY_PARTICIPATION = 'participation_reports';
    const CATEGORY_USER = 'user_reports';
    const CATEGORY_OUTCOMES = 'outcomes_reports';

    const EXECUTION_MODE_INTERACTIVE = 0;
    const EXECUTION_MODE_SCHEDULED = 1;

    //identifier for the current report
    var $id;
    //id of the Moodle user we are considering the report to be run by
    var $userid;
    //contenxt in which the report is being run
    var $execution_mode;

    //capability that should be checked for permissions, based on execution mode
    var $access_capability;

    /**
     * PHP report constructor
     *
     * @param  string    $id              Unique identifier for this report instance
     * @param  int|NULL  $userid          Id of the Moodle user who this report is being
     *                                    for
     * @param  int       $execution_mode  The mode in which this report is being executed
     */
    function php_report($id, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
        global $USER;

        $this->id = $id;

        if ($userid === NULL) {
            //default to the current user
            $this->userid = $USER->id;
        } else {
            //manually specified
            $this->userid = $userid;
        }

        $this->execution_mode = $execution_mode;

        //calcuate the capability needed to access this report based on the execution mode
        $this->access_capability = $this->get_access_capability();
    }

    /**
     * Calculates the display name for this report from the appropriate language string
     *
     * @return  string  The report's displayname
     */
    function get_display_name() {

        $classname = get_class($this);
        $folder_name = 'rlreport_' . substr($classname, 0, strlen($classname) - strlen('_report'));

        //use the displayname string from the specific report instance
        return get_string('displayname', $folder_name);
    }

    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_ADMIN;
    }

    /**
     * Determines whether the current user can view this report
     *
     * @return  boolean         True if permitted, otherwise false
     */
    function can_view_report() {

        //no default restrictions, implement restrictions in report instance class
        return true;
    }

    /**
     * Generates the HTML code for the header entries, based on the report's definition
     * of header entries as defined by "get_header_entries"
     *
     * @return  string  The appropriate HTML code
     */
    function print_header_entries() {

        $result = '';

        if($entries = $this->get_header_entries()) {
            $result .= '<div class="php_report_header_entries">';
            foreach($entries as $value) {

                $label_class = "php_report_header_{$value->css_identifier} php_report_header_label";
                $value_class = "php_report_header_{$value->css_identifier} php_report_header_value";

                $result .= '<div class="' . $label_class . '">' . $value->label . '</div>';
                $result .= '<div class="' . $value_class . '">' . $value->value . '</div>';

            }
            $result .= '</div>';
        }

        return $result;
    }

    /**
     * This method provides a way for reports to define entries to show at
     * top as summary data (default to none, but allow implementations to override)
     *
     * @return  array  A mapping of display names to values
     */
    function get_header_entries() {
        return array();
    }

    /**
     * Determines whether an SQL query has an active WHERE clause
     * at the outermost level of nesting
     *
     * @param   string    $sql  The SQL query in question
     *
     * @return  boolean         True if it query has a WHERE clause, otherwise false
     */
    static function sql_has_where_clause($sql) {
        $position = array();

        $lowercase_sql = strtolower($sql);

        //calculate position of key symbols
        $symbols = array('(',
                         ')',
                         'where');

        foreach ($symbols as $symbol) {
            $start_pos = 0;

            while (($pos = strpos($lowercase_sql, $symbol, $start_pos)) !== FALSE) {
                $position[$pos] = $symbol;
                $start_pos = $pos + 1;
            }
        }

        //sort based on position
        ksort($position);

        //maintain bracket balance
        $balance = 0;

        foreach ($position as $token) {
            switch ($token) {
                case '(':
                    $balance++;
                    break;
                case ')':
                    $balance--;
                    break;
                case 'where':
                    if ($balance == 0) {
                        //WHERE found at outermost level
                        return true;
                    }
                    break;
                default:
                    break;
            }
        }

        //never found an appropriate WHERE token
        return false;
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
        //no filters by default
        return array();
    }

    /**
     * Specifies whether this report definition has one or more
     * filters defined for it
     *
     * @return  boolean  true if one more more filters are defined, or false if none are
     */
    function has_filters() {
        //default to true, since almost all reports will have some filtering options
        return true;
    }

    /**
     * Sets up the secondary filterings based on the report definitions
     *
     * @param   string  $url               The URL used to dynamically reload this report
     * @param   string  $id                This report's unique identifier
     * @param   string  $report_shortname  The shortname of the configured report
     *
     * @return  array                      Mapping of pre-set filtering keys to filtering objects
     */
    function get_secondary_filterings($url, $id, $report_shortname) {
        return array();
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
            $this->filter = new php_report_default_capable_filtering($filters, $dynamic_report_filter_url, null, $id, $this->get_report_shortname());
        }
    }

    /**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        //default to no export (override in report class)
        return array();
    }

    /**
     * Specifies the list of identifiers representing the list of possible export formats
     * (based on the functionality of the download method in the specific report type, currently
     *  only implemented in table reports)
     *
     * @return  string array  List of allowable formats
     */
    static function get_allowable_export_formats() {
        //currently, only PDF and CSV export formats are fully implemented
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
        //by default, make the report available,
        //override in child class if necessary to restrict
        return true;
    }

    /**
     * Require any code that this report needs
     * (only called after is_available returns true)
     */
    function require_dependencies() {
        //nothing needed by default - this is a report-specific hook
    }

    /**
     * Returns the current filters parameter values for a specified filter name
     * TODO: remove this once nobody is using it anymore
     *
     * @param   string $filter_name The filter name
     * @return  array List of filter parameter values for given filter name, false if no results
     */
    function get_active_filter_values($filter_name) {
        global $SESSION;

        mtrace('<br><font color=red>NOTICE: $this->get_active_filter_values() is now deprecated; use php_report_filtering_get_active_filter_values($this->get_report_shortname(),$filter_name) instead (sorry... lol)</font><br>');

        $result = array();

        /* DOES NOT WORK ANYMORE
        if(!empty($this->id)) {
            $filtering_array =& $SESSION->user_index_filtering[$this->id];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        if (isset($filtering_array[$filter_name])) {
            $result = $filtering_array[$filter_name];
        }
        */

        $reportid = 'php_report_' . $this->get_report_shortname();

        if (isset($SESSION->php_report_default_params)) {
            $params = $SESSION->php_report_default_params;

            foreach ($params as $key=>$val) {
                if ($key == $reportid.'/'.$filter_name) {
                    $result[] = array('value'=>$val);
                }
            }
        }

        if (!empty($result)) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Specifies the config header for this report
     *
     * @return  string  The HTML content of the config header
     */
    function get_config_header() {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');

        if (!isloggedin() || isguestuser()) {
            //user is not properly logged in
            return '';
        }

        //determine if we should show the configure filter link
        $show_config_filters_link = true;

        //NOTE: using has_filters instead of get_filters so reports can
        //specify this info without having to construct filters
        if (!$this->has_filters()) {
            //no filters are defined for this report
            $show_config_filters_link = false;
        }

        if (!$this->allow_configured_filters()) {
            //configured filters are not allowed for this report
            $show_config_filters_link = false;
        }

        //determine if we should display the scheduling link
        $test_permissions_instance = php_report::get_default_instance($this->get_report_shortname(), NULL, php_report::EXECUTION_MODE_SCHEDULED);
        $show_schedule_report_link = $test_permissions_instance !== FALSE;

        //also assert that at least one export format is available
        $export_formats = $this->get_export_formats();
        if (count($export_formats) == 0) {
            $show_schedule_report_link = false;
        }

        if (!$show_config_filters_link && !$show_schedule_report_link) {
            //no filter link or schedule link to show
            return '';
        }

        $result = '<div class="php_report_config_header">';

        if ($show_config_filters_link) {
            //link for configuring parameters
            $alt_text = get_string('config_params', 'block_php_report');
            //link to parameter screen with cancel button
            $config_params_url = $CFG->wwwroot . '/blocks/php_report/config_params.php?id=' . $this->id . '&showcancel=1';
            $result .= '<a href="' . $config_params_url . '">' .
                       '<img src="' . $CFG->wwwroot . '/blocks/php_report/pix/configuration.png" border="0" width="16" height="16" ' .
                       'alt="' . $alt_text . '" title="' . $alt_text . '">' .
                       '</a>&nbsp;&nbsp;';
        }

        //loop through the possible export formats and add the export links if
        //they are supported by the report
        $export_formats = $this->get_export_formats();
        $allowable_export_formats = php_report::get_allowable_export_formats();
        foreach ($allowable_export_formats as $allowable_export_format) {
            if (in_array($allowable_export_format, $export_formats)) {
                $alt_text = get_string('export_link_' . $allowable_export_format, 'block_php_report');
                // add hatch character at end of url to force loading in a new page (checked in associate.class.js)
                $export_url = $CFG->wwwroot . '/blocks/php_report/download.php?id=' . $this->id . '&format=' . $allowable_export_format . '#';
                $icon = mimeinfo('icon', "foo.$allowable_export_format");
                $result .= '<a href="' . $export_url . '">' .
                           '<img src="' . $CFG->pixpath . '/f/' . $icon . '" border="0" width="16" height="16" ' .
                           'alt="' . $alt_text . '" title="' . $alt_text . '">' .
                           '</a>&nbsp;&nbsp;';
            }
        }

        if ($show_schedule_report_link) {
            //link for scheduling reports

            $report_shortname = $this->get_report_shortname();

            //url to link to
            $schedule_report_url = '/blocks/php_report/schedule.php?report=' . $report_shortname . '&action=listinstancejobs&createifnone=1';

            $result .= '<span class="external_report_link php_report_schedule_this_link">'.
                        get_string('schedule_this_report', 'block_php_report') .
                        '&nbsp;
                        <a href="#" onclick="openpopup(\'' . $schedule_report_url . '\', \'php_report_param_popup\', \'menubar=0,location=0,scrollbars,status,resizable,width=1600,height=600\')">
                        <img src="' . $CFG->wwwroot . '/blocks/php_report/pix/schedule.png"/>
                        </a>
                        </span>';
        }
        $result .= '</div>';

        return $result;
    }

    /**
     * Specifies whether the report can be configured to use interactive filters
     * (per-user, on the fly)
     *
     * @return  boolean  TRUE if enabled, otherwise FALSE
     */
    function allow_interactive_filters() {
        return FALSE;
    }

    /**
     * Specifies whether the report can be configured to use default values
     * (per-user)
     *
     * @return  boolean  TRUE if enabled, otherwise FALSE
     */
    function allow_configured_filters() {
        return TRUE;
    }

    /**
     * Returns the HTML representing the interactive filter display,
     * which shows available and active filters
     *
     * @return  string  The HTML content of the display
     */
    function get_interactive_filter_display() {
        $result = '';

        if (isset($this->filter) && $this->allow_interactive_filters()) {
            $result .= $this->filter->display_add(true);
            $result .= $this->filter->display_active(true);
        }

        return $result;
    }

    /**
     * Specifies which filters, if any, are required in order for this report
     * to be run
     *
     * @param   int           $execution_mode  Mode in which the report is currently being executed
     *                                         (should be one of the EXECUTION_MODE_... constants)
     *
     * @return  string array                   Listing of filter shortnames representing required filters,
     *                                         from either the main report filters or secondary filters
     */
    function get_required_filters($execution_mode) {
        //nothing is required by default, implement in report instance
        return array();
    }

    /**
     * Specifies the shortname for this report
     *
     * @return  string  Report shortname
     */
    function get_report_shortname() {
        $classname = get_class($this);
        $reportname = substr($classname, 0, strlen($classname) - strlen('_report'));

        return $reportname;
    }

    /**
     * Converts a report shortname to the file path
     * containing the report definition
     *
     * @param   string  shortname  The shortname of the report
     *                             (same as instance folder name)
     *
     * @return  string             The absolute path of the file
     *                             containing the report definition
     */
    static function get_report_filename($shortname) {
        global $CFG;

        //path to the report instance folder
        $base_path = $CFG->dirroot .'/blocks/php_report/instances/';

        //name of php file containing report definition
        $class_filename = $shortname . '_report.class.php';

        //full file path
        $full_path = $base_path . $shortname . '/' . $class_filename;

        return $full_path;
    }

    /**
     * Provides a default report instance used to display a report
     * (report is not a pre-configured instance)
     *
     * @param   string              $shortname       Shortname of the report to be obtained
     * @param   int|NULL            $userid          Id of the Moodle user who this report is being
     *                                               for
     * @param  int                  $execution_mode  The mode in which this report is being executed
     *
     * @return  php_report|boolean                   A new report instance of the specified type, or FALSE
     *                                               if user doesn't have sufficient permissions
     */
    static function get_default_instance($shortname, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
        global $CFG;

        //get the name of the file containing the report definition
        $filename = php_report::get_report_filename($shortname);

        if (!file_exists($filename)) {
            //could not find the report definition
            return FALSE;
        }

        //load report definition
        require_once($filename);

        //create report instance
        $classname = $shortname . '_report';

        //report "id" is actually the shortname because we are not
        //within a block instance
        $instance = new $classname($shortname, $userid, $execution_mode);

        if (!$instance->is_available()) {
            //necessary components for running this report are not installed
            return FALSE;
        }

        if (!$instance->can_view_report()) {
            //user doesn't have sufficient permissions
            return FALSE;
        }

        $instance->require_dependencies();

        return $instance;
    }

    /**
     * Temporarily allocates extra resources needed to export reports
     */
    static function allocate_extra_resources() {
        global $CFG;

        //disable the time limit for this executing script
        @set_time_limit(0);

        //up the memory limit
        if (empty($CFG->extramemorylimit)) {
            raise_memory_limit('128M');
        } else {
            raise_memory_limit($CFG->extramemorylimit);
        }
    }

    /**
     * API call for exporting a report execution
     *
     * @param   string    $shortname       Shortname of the report to be executed (should be
     *                                     one of the folder names found in the "instances" directory)
     * @param   string    $format          The export format (one of the EXPORT_FORMAT_... constants)
     * @param   string    $filename        Name of the file to write to (including extension)
     * @param   stdClass  $parameter_data  Submitted parameter form data (in format directly returned by get_data)
     * @param   int|NULL  $userid          Id of the Moodle user who this report is being
     *                                     for
     * @param   int       $execution_mode  The mode in which this report is being executed
     *
     * @return  boolean                    true on success, otherwise false
     */
    static function export_default_instance($shortname, $format, $filename, $parameter_data, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
        //allocate some extra resources
        php_report::allocate_extra_resources();

        //get a default instance
        $report_instance = php_report::get_default_instance($shortname, $userid, $execution_mode);

        if ($report_instance == false) {
            //user no longer has access, so signal failure
            return false;
        }

        $allowable_formats = $report_instance->get_export_formats();
        if (!in_array($format, $allowable_formats)) {
            //specified format is not allowed
            return;
        }

        //make sure all dependencies are loaded
        $report_instance->require_dependencies();
        //initialize all necessary data
        $report_instance->init_all($shortname, $parameter_data);
        //run the export
        $report_instance->download($format, $report_instance->get_complete_sql_query(FALSE), $filename);

        return true;
    }

    /**
     * Specifies the capability needed to execute the current report
     *
     * @return  string  The capability string that should be used for permissions checking
     */
    function get_access_capability() {
       if ($this->execution_mode == php_report::EXECUTION_MODE_INTERACTIVE) {
           //running for the UI
           return 'block/php_report:view';
       } else {
           //running a scheduled report
           return 'block/php_report:schedule';
       }
    }

    /**
     * Returns a float or a string which denotes the user's timezone
     * A float value means that a simple offset from GMT is used, while a string (it will be the name of a timezone in the database)
     * means that for this timezone there are also DST rules to be taken into account
     * Checks various settings and picks the most dominant of those which have a value
     * (this is essentially a copy of the core Moodle function, but allows user to be specified)
     *
     * @uses $CFG
     * @param object $user_record The user who is running the report
     * @param float $tz If this value is provided and not equal to 99, it will be returned as is and no other settings will be checked
     * @return mixed
     */
    static function get_user_timezone($user_record, $tz = 99) {
        global $CFG;

        $timezones = array(
            $tz,
            isset($CFG->forcetimezone) ? $CFG->forcetimezone : 99,
            isset($user_record->timezone) ? $user_record->timezone : 99,
            isset($CFG->timezone) ? $CFG->timezone : 99,
            );

        $tz = 99;

        while(($tz === '' || $tz == 99 || $tz === NULL) && $next = each($timezones)) {
            $tz = $next['value'];
        }

        return is_numeric($tz) ? (float) $tz : $tz;
    }

    /**
     * from /lib/moodlelib.php::userdate()
     * Returns a formatted string that represents a date in user time
     *
     * @param int    $date timestamp in GMT
     * @param string $format strftime format
     * @param bool   $fixday If true then the leading zero from %d is removed.
     *               If false (default) then the leading zero is mantained.
     * @return string
     */
    function userdate($date, $format='', $fixday = false) { // TBD: false???
        $timezone = 99;
        if ($user_record = get_record('user', 'id', $this->userid)) {
            //determine the user's timezone
            $timezone = php_report::get_user_timezone($user_record, $user_record->timezone);
        }

        //perform the formatting
        return userdate($date, $format, $timezone, $fixday);
    }

    /**
     * Methods related to UI / output
     */

    /**
     * Method to determine if we should first display 'Configure parameters' filter form
     *
     * @return   boolean  true if should redirect to filter form, false otherwise.
     */
    function shouldredirecttofilter() {
        if (!$this->has_filters()) {
            return false;
        }

        if ($data = data_submitted()) {
            //a forum submit action happened, so render the report
            return false;
        }

        if (!empty($_GET)) {
            //determine how many URL parameters were passed in
            $array_get = (array)$_GET;
            $num_elements = count($array_get);

            if (isset($array_get['report'])) {
                //don't count the report shortname
                $num_elements--;
            }

            if ($num_elements > 0) {
                //a non-innocuous URL parameter was passed in,
                //so render the report
                return false;
            }
        }

        return true;
    }

    /**
     * Outputs the HTML content needed at the beginning of a report,
     * including handling the filter form display if necessary
     */
    function display_header() {
        global $CFG, $USER;
        
        $report_shortname = $this->get_report_shortname();

        //these JS files are needed for async reporting requests
        require_js($CFG->wwwroot . '/blocks/php_report/reportblock.js');
        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection'));

        //outermost report div
        $output = '<div id="php_report_block" class="php_report_block">';
        //div for containing report body
        $output .= '<div class="php_report_body" id="php_report_body_' . $this->id . '">';

        //for displaying the loading animation
        $output .= '<div id="throbber"></div>';
        
        if ($this->shouldredirecttofilter()) {
            //display parameter / filter UI

            $this->require_dependencies();
            
            ob_start();
            $_GET['id'] = $this->get_report_shortname();
            $_GET['mode'] = 'bare';
            $_GET['url'] = "{$CFG->wwwroot}/blocks/php_report/config_params.php";

            include(dirname(__FILE__) .'/config_params.php');
            
            $output .= ob_get_contents();
            ob_end_clean();
            
            //end of php_report_body div
            $output .= '</div>';
            //end of php_report_block div
            $output .= '</div>';
            
            echo $output;
            die;
        }
        
        //Set up an appropriate stylesheet
        $folder_name = $this->get_report_shortname();

        //Calculate the URL of the stylesheet
        $stylesheet_web_path = $CFG->wwwroot . '/blocks/php_report/instances/' . $folder_name . '/styles.css';
        //Calculate the file path of the stylesheet for an existence check
        $stylesheet_file_path = $CFG->dirroot . substr($stylesheet_web_path, strlen($CFG->wwwroot));

        //Reference stylsheet if it exists
        if(file_exists($stylesheet_file_path)) {
            $output .= '<style>@import url("' . $stylesheet_web_path . '");</style>';
        }

        //Report specific div
        $output .= '<div class="' . $folder_name . ' ' . $this->get_report_content_css_classes(get_class($this)) . '">';
        $output .= '<br/>';
        
        echo $output;
    }

    /**
     * Uses a base classname to create a CSS class for the current report
     */
    static function get_report_content_css_classes($base_classname) {
        $css_classes = array();
        
        $current = $base_classname;
        
        //keep grabbing the parent class until you run out of classes
        while ($current !== false) {
            $css_classes[] = $current;
            $current = get_parent_class($current);
        }
        
        //combine all classnames into a string
        return implode(' ', $css_classes);
    }
    
    function display_footer() {
        global $CFG;

        //Add refresh button and info about when last run
        $output = $this->get_lastload_display($this->get_report_shortname());

        //end of report / custom CSS div
        $output .= '</div>';
        //end of php_report_body div
        $output .= '</div>';
        //end of php_report_block div
        $output .= '</div>';
        
        echo $output;
    }

    /**
     * Calculates and returns a message to display that
     * informs users on how recent their data is
     *
     * @param   int|string     $id  The current report block id
     *
     * @return  string         HTML for a form with the display text and a reset button
     */
    function get_lastload_display($id = 0) {
        global $CFG, $USER;

        $format = '%A, %B %e, %l:%M:%S %P';

        $element_id = 'refresh_report';
        if(!empty($id)) {
            $element_id .= '_' . $id;
        }

        $timezone = 99;
        if(isset($USER->timezone)) {
            $timezone = $USER->timezone;
        }
        $lastload = time();
        $a = userdate($lastload, $format, $timezone);

        return '<form id="' . $element_id . '" action="' . $CFG->wwwroot . '/blocks/php_report/dynamicreport.php" ' .
               'onsubmit="start_throbber(); return true;" >' .
               '<input type="hidden" id="id" name="id" value="' . $id . '" />' .
               '<p align="center" class="php_report_caching_info">' . get_string('infocurrent', 'block_php_report', $a) . '<br/>' .
               '<input id="' . $element_id . '" type="submit" value="Refresh"/>' . '</p>' .
               '</form>';
    }
}

/**
 * Class representing name - value pairs to display in the report header
 */
class php_report_header_entry {
    var $label;
    var $value;
    var $css_identifier;

    /**
     * Report header constructor
     *
     * @param  string  $label           Label display text
     * @param  string  $value           Value to display
     * @param  string  $css_identifier  CSS class to use for this header entry
     */
    function php_report_header_entry($label, $value, $css_identifier) {
        $this->label = $label;
        $this->value = $value;
        $this->css_identifier = $css_identifier;
    }
}

/**
 * A report that is actionable on CM users.
 */
interface cm_user_actionable_report {
    /**
     * Get the SQL statement that will return the CM users who are on the
     * report.
     *
     * @param array $columns the columns from the CM user table
     * @param string $sort the column and direction to sort by
     * @return string an SQL statement
     */
    function get_cm_users_sql(array $columns, $sort = 'lastname ASC');
}

/**
 * A report that is actionable on Moodle users.
 */
interface moodle_user_actionable_report {
    /**
     * Get the SQL statement that will return the Moodle users who are on the
     * report.
     *
     * @param array $columns the columns from the CM user table
     * @param string $sort the column and direction to sort by
     * @return string an SQL statement
     */
    function get_moodle_users_sql(array $columns, $sort = 'lastname ASC');
}

?>
