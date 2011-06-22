<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Class that contains an encapsulated instance of a report unit
 */
class php_report_block {

    var $id;
    var $report;
    var $display_by_default;
    var $visible;
    var $loaded;
    var $lastloaduser;
    var $name;
    var $cachetime;
    var $pagesize;
    var $inner_report;
    var $cache;
    var $lastload;
    var $classname;
    var $reportwidth;
    var $currentsort;
    var $currentdir;
    var $currentpage;

    public static $NO_CACHE = -1;
    public static $ETERNAL_CACHE = -2;

    /**
     * Report block constructor
     *
     * @param  int|string  $id                  The block id
     * @param  boolean     $display_by_default  Whether to display the block by default
     * @param  string      $name                Report display name
     * @param  int         $cachetime           Cache time in seconds
     * @param  int         $pagesize            Number of records per page (tabular reports)
     * @param  string      $classname           The report instance's classname
     * @param  int         $reportwidth         The configured report width
     */
    function php_report_block($id, $display_by_default, $name, $cachetime, $pagesize, $classname, $reportwidth) {
        $this->id = $id;
        $this->display_by_default = $display_by_default;
        $this->visible = $this->display_by_default;
        $this->lastrun = 0;
        $this->loaded = $this->visible;
        $this->name = $name;
        $this->cachetime = $cachetime;
        $this->lastloaduser = 0;
        $this->pagesize = $pagesize;
        $this->classname = $classname;
        $this->reportwidth = $reportwidth;
        $this->currentsort = '';
        $this->currentdir = '';
        $this->currentpage = 0;
    }

    /**
     * Sets up data from caching, if necessary
     */
    public function initialize() {
        global $SESSION;

        //Make sure the session data struction is set up
        if(empty($SESSION->php_reports)) {
            $SESSION->php_reports = array();
        }

        if(empty($SESSION->php_reports[$this->id])) {
            //no data is cached
            $SESSION->php_reports[$this->id] =& $this;
        } else {
            //need to do this so that the classes are correctly loaded
            php_report_block::require_dependencies();

            //reset our data based on an ongoing session
            $existing_data =& $SESSION->php_reports[$this->id];

            $this->id = $existing_data->id;
            $this->cache = $existing_data->cache;
            $this->lastload = $existing_data->lastload;
            $this->lastloaduser = $existing_data->lastloaduser;

            $this->inner_report = $existing_data->inner_report;
            $this->display_by_default = $existing_data->display_by_default;
            $this->visible = $existing_data->visible;
            $this->lastrun = $existing_data->lastrun;
            $this->loaded = $existing_data->loaded;
            $this->currentsort = $existing_data->currentsort;
            $this->currentdir = $existing_data->currentdir;
            $this->currentpage = $existing_data->currentpage;
        }

    }

    /**
     * Calculates the contents of this reporting unit
     *
     * @return  string  The desired output
     */
    function display() {
        global $CFG, $SESSION, $USER;

        $this->initialize();

        //these JS files are needed for async reporting requests
        require_js($CFG->wwwroot . '/blocks/php_report/reportblock.js');
        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection'));

        $output = '';

        require_once($CFG->dirroot . '/blocks/php_report/type/icon_report.class.php');

        $folder_name = substr($this->classname, 0, strlen($this->classname) - strlen('_report'));
        require_once($CFG->dirroot . '/blocks/php_report/instances/' . $folder_name . '/' . $this->classname . '.class.php');
        $test_instance = new $this->classname($this->id);

        $style_tag = '';

        if(!empty($this->reportwidth)) {
            $style_tag = 'style="width: ' . $this->reportwidth . 'px"';
        }

        $output .= '<div id="php_report_block" class="php_report_block" ' . $style_tag . '>';

        //div for containing report body
        $output .= '<div class="php_report_body" id="php_report_body_' . $this->id . '">';

        /**
         * Reset the state if the user has changed
         */
        $effective_userid = 0;

        if (isloggedin() && !isguest()) {
            $effective_userid = $USER->id;
        }

        if($this->lastloaduser != $effective_userid) {
            $this->reset_state();
        }

        //display report if appropriate
        if($this->visible) {
            $output .= $this->execute($this->currentpage, $this->id, $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $this->id, $this->currentsort, $this->currentdir);
            $this->loaded = true;
            $SESSION->php_reports[$this->id]->loaded = true;
        }

        $output .= '</div>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Method to determine if we should first display 'Configure parameters' filter form
     *
     * @return   boolean  true if should redirect to filter form, false otherwise.
     */
    function shouldredirecttofilter() {

        $report_shortname = substr($this->classname, 0, strlen($this->classname) - strlen('_report'));
        if ($test_instance = php_report::get_default_instance($report_shortname)) {
            if (!$test_instance->has_filters()) {
                //no filters are defined, so render the report
                return false;
            }
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
     * This method is set up to handle including any dependencies required by reports
     * stored in session data
     *
     * @param  int|string  $id  The specific report instance we are dealing with
     */
    static function require_dependencies($id = 0) {
        global $CFG, $SESSION;

        //This prevents deserialization from breaking
        if($handle = opendir($CFG->dirroot . '/blocks/php_report/type')) {
            while (false !== ($file = readdir($handle))) {
                if(strrpos($file, '.class.php') == strlen($file) - strlen('.class.php')) {
                    require_once($CFG->dirroot . '/blocks/php_report/type/' . $file);
                }
            }
        }

        if ($id !== 0) {
            if (is_numeric($id) && ($block_instance = get_record('block_instance', 'id', $id))) {
                //load the class for this particular report based on config data
                $config_data = unserialize(base64_decode($block_instance->configdata));

                $folder_name = substr($config_data->reportinstance, 0, strlen($config_data->reportinstance) - strlen('_report.class.php'));
                require_once($CFG->dirroot . '/blocks/php_report/instances/' . $folder_name . '/' . $config_data->reportinstance);
            } else {
                //"generic" report, so id will refer to the report name
                $folder_name = $id;
                $report_file = $folder_name . '_report.class.php';
                require_once($CFG->dirroot . '/blocks/php_report/instances/' . $folder_name . '/' . $report_file);
            }
        }

        //This will instantiate the appropriate class loaders
        if(isset($SESSION->php_reports)) {
            $SESSION->php_reports = unserialize(serialize($SESSION->php_reports));
        }
        //Require any additional dependancies
        if ($id !== 0) {
            if ($SESSION->php_reports[$id]->inner_report->is_available()) {
                $SESSION->php_reports[$id]->inner_report->require_dependencies();
                $SESSION->php_reports = unserialize(serialize($SESSION->php_reports));
            }
        }
    }

    /**
     * Outputs the header title for this report unit
     */
    function display_header() {
        echo $this->name;
    }

    /**
     * Loads a report from the appropriate class
     *
     * @param  $page                Which page we are on
     * @param  $id                  The report id
     * @param  $baseurl             Base URL used to reference script to obtain data
     * @param  $sort                Field to sort on
     * @param  $dir                 Sorting direction
     * @param  $filterchange        Empty if the current action is not a filter change, otherwise not empty
     * @param  $additional_options  Mapping of any additional options to be set on the report
     *
     */
    function load($page, $id, $baseurl='', $sort='', $dir='', $filterchange='', $additional_options = array()) {
        global $CFG, $SESSION, $USER;

        //Create the report object

        require_once($CFG->dirroot . '/curriculum/config.php');

        $folder_name = substr($this->classname, 0, strlen($this->classname) - strlen('_report'));
        require_once($CFG->dirroot .'/blocks/php_report/instances/' . $folder_name . '/' . $this->classname . '.class.php');

        $this->inner_report = new $this->classname($this->id);
        $this->inner_report->require_dependencies();

        //set any additional options, handling type-specific possibilities as
        //generically as possible
        if (!empty($additional_options)) {
            foreach ($additional_options as $key => $value) {
                $this->inner_report->$key = $value;
            }
        }

        //Load the actual report contents
        ob_start();
        $this->inner_report->main($sort, $dir, $page, $this->pagesize, '', $id);
        $this->cache = ob_get_contents();
        ob_end_clean();

        //Cache the report
        $SESSION->php_reports[$id]->cache = $this->cache;

        //Reset last loaded time for caching reasons
        $this->lastload = time();
        $SESSION->php_reports[$id]->lastload = $this->lastload;

        //store the user id last used to load report data
        if (isloggedin() && !isguest()) {
            $this->lastloaduser = $USER->id;
        } else {
            $this->lastloaduser = 0;
        }

        //update filters in the session based on changes found during reloading the report
        $SESSION->php_reports[$id]->inner_report->filter =& $this->inner_report->filter;
    }

    /**
     * Uses a base classname to create a CSS class for the current report
     */
    static function get_report_content_css_classes($base_classname) {
        $css_classes = array();
        
        $current = $base_classname;
        
        //keep grabbing the parent class until you run out of classes
        while ($current !== FALSE) {
            $css_classes[] = $current;
            $current = get_parent_class($current);
        }
        
        //combine all classnames into a string
        return implode(' ', $css_classes);
    }
    
     /**
     * Method that handles executing the report unit, taking
     * caching into account
     *
     * @param  $page                Which page we are on
     * @param  $id                  The report id
     * @param  $baseurl             Base URL used to reference script to obtain data
     * @param  $sort                Field to sort on
     * @param  $dir                 Sorting direction
     * @param  $filterchange        Empty if the current action is not a filter change, otherwise not empty
     * @param  $additional_options  Mapping of any additional options to be set on the report
     * @uses $CFG
     * @uses $SESSION
     * @uses $_GET
     * @return string  the report or 'Configure parameters' form data.
     *
     */
    function execute($page, $id, $baseurl='', $sort='', $dir='', $filterchange='', $additional_options = array()) {
        global $CFG;

        $output = '<div id="throbber"></div>';
        if ($this->shouldredirecttofilter()) {
            global $SESSION;
            //MUST create the report object as $this->inner_report is required!
            require_once($CFG->dirroot . '/curriculum/config.php');
            $folder_name = substr($this->classname, 0, strlen($this->classname) - strlen('_report'));
            require_once($CFG->dirroot .'/blocks/php_report/instances/' . $folder_name . '/' . $this->classname . '.class.php');

            $this->inner_report = new $this->classname($id);
            $this->inner_report->require_dependencies();
            $this->inner_report->init_all($id);
            $this->lastload = 0; // TBD

            //set the object back in the session because filters may have changed
            $SESSION->php_reports[$this->id] = &$this;

            ob_start();
            $_GET['id'] = $id;
            $_GET['mode'] = 'bare';
            $_GET['url'] = "{$CFG->wwwroot}/blocks/php_report/config_params.php";
            include(dirname(__FILE__) .'/config_params.php');
            $output .= ob_get_contents();
            ob_end_clean();
            if (empty($this->cache)) {
                $this->cache = '';
            }
            return $output;
        } else if ($this->requires_reload()) { // Account for report caching options
            $this->load($page, $id, $baseurl, $sort, $dir, $filterchange, $additional_options);
        }

        //Set up an appropriate stylesheet
        $folder_name = substr($this->classname, 0, strlen($this->classname) - strlen('_report'));

        //Calculate the URL of the stylesheet
        $stylesheet_web_path = $CFG->wwwroot . '/blocks/php_report/instances/' . $folder_name . '/styles.css';
        //Calculate the file path of the stylesheet for an existence check
        $stylesheet_file_path = $CFG->dirroot . substr($stylesheet_web_path, strlen($CFG->wwwroot));

        //Reference stylsheet if it exists
        if(file_exists($stylesheet_file_path)) {
            $output .= '<style>@import url("' . $stylesheet_web_path . '");</style>';
        }

        //Report specific div
        $output .= '<div class="' . $folder_name . ' ' . $this->get_report_content_css_classes($this->classname) . '">';
        $output .= '<br/>';

        //Add report output
        $output .= $this->cache;

        //Add refresh button
        $output .= $this->get_lastload_display($id);

        $output .= '</div>';

        return $output;
    }

    /**
     * Determines whether the current report should be reloaded
     * based on caching logic
     *
     * @return  boolean  Whether the report should be reloaded
     */
    function requires_reload() {
        return $this->lastload == 0 ||
               $this->cachetime == php_report_block::$NO_CACHE ||
               $this->cachetime != php_report_block::$ETERNAL_CACHE &&
               ($this->lastload == 0 || (time() - $this->lastload) > $this->cachetime);
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
        global $CFG, $USER, $SESSION;

        $format = '%A, %B %e, %l:%M:%S %P';

        $element_id = 'refresh_report';
        if(!empty($id)) {
            $element_id .= '_' . $id;
        }

        $timezone = 99;
        if(isset($USER->timezone)) {
            $timezone = $USER->timezone;
        }
        $a = userdate($this->lastload, $format, $timezone);
        return '<form id="' . $element_id . '" action="' . $CFG->wwwroot . '/blocks/php_report/refresh.php" ' .
               'onsubmit="start_throbber(); return true;" >' .
               '<input type="hidden" id="id" name="id" value="' . $id . '" />' .
               '<input type="hidden" id="page" name="page" value="' . $SESSION->php_reports[$id]->currentpage . '" />' .
               '<input type="hidden" id="sort" name="sort" value="' . $SESSION->php_reports[$id]->currentsort . '" />' .
               '<input type="hidden" id="dir" name="dir" value="' . $SESSION->php_reports[$id]->currentdir . '" />' .
               '<p align="center" class="php_report_caching_info">' . get_string('infocurrent', 'block_php_report', $a) . '<br/>' .
               '<input id="' . $element_id . '" type="submit" value="Refresh"/>' . '</p>' .
               '</form>';
    }

    /**
     * Resets report state to ensure that re-executing will yield fresh data
     */
    function reset_state() {
        //force reload
        $this->lastload = 0;
        //first page
        $this->currentpage = 0;
        //default sorting
        $this->currentsort = '';
        $this->currentdir = '';
    }

}

?>
