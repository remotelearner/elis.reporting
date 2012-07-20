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
 * @subpackage pm-blocks-phpreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/program/lib/setup.php');

//need access to report base class for execution mode constants
require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');

require_once($CFG->libdir .'/formslib.php');

//needed for base filter form
require_once($CFG->dirroot .'/user/filters/user_filter_forms.php');

//needed for filters
require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');

/**
 * Includes the necessary filtering classes required for all reports to work
 */
function php_report_filtering_require_dependencies() {
    global $CFG;

    //go through the files in the /elis/core/ filtering directory
    if (file_exists($CFG->dirroot .'/elis/core/lib/filtering') &&
        $handle = opendir($CFG->dirroot .'/elis/core/lib//filtering')) {
        while (false !== ($file = readdir($handle))) {
            //load filter definition if it's a PHP file
            if(strrpos($file, '.php') == strlen($file) - strlen('.php')) {
                require_once($CFG->dirroot .'/elis/core/lib/filtering/'. $file);
            }
        }
    }

    //go through the files in the /elis/program/ filtering directory
    if (file_exists($CFG->dirroot .'/elis/program/lib//filtering') &&
        $handle = opendir($CFG->dirroot .'/elis/program/lib//filtering')) {
        while (false !== ($file = readdir($handle))) {
            if ($file == 'clustertree_load_menu.php') {
                continue;
            }
            //load filter definition if it's a PHP file
            if(strrpos($file, '.php') == strlen($file) - strlen('.php')) {
                require_once($CFG->dirroot .'/elis/program/lib/filtering/'. $file);
            }
        }
    }
}

/**
 * Updates parameter form fields based on defined preferences
 *
 * @uses $SESSION
 * @param  string      $report_name     "Shortname" of the report
 * @param  moodleform  $parameter_form  The from whose fields we are updating
 */
function php_report_filtering_update_form($report_name, &$parameter_form) {
    global $SESSION;

    //go through the current user's preferences (persistent ONLY)
    if ($existing_preferences = php_report_filtering_get_user_preferences($report_name)) {
        foreach ($existing_preferences as $key => $value) {

            //if the preference is php-report-related, set the form field
            $prefix = 'php_report_'. $report_name . '/';
            if (strpos($key, $prefix) === 0) {
                //preference contains the form field name and UI value
                $field_name = substr($key, strlen($prefix));
                if (is_array($value)) {
                    $value = serialize($value);
                }
                $parameter_form->set_data(array($field_name => $value), true);
            }

        }
    }
}

/**
 * Organizes submitted data by fields on the filter form for easier categorization
 *
 * @param   object    $filter_object  The filtering for the report we are considering
 * @param   stdClass  $form_data      The submitted form data, containing filter field info
 *
 * @return  array                     Submitted data organized by field, then by GUI element
 */
function php_report_filtering_get_per_filter_data($filter_object, $form_data) {
    $per_filter_data = array();

    //go through the submitted fields and their values
    foreach ($form_data as $submit_shortname => $submit_value) {
        //go through the fields defined for the filters
        foreach ($filter_object->_fields as $field_shortname => $field_value) {
            //map submit keys to field shortnames
            if (strpos($submit_shortname, $field_shortname) === 0) {
                if (!isset($per_filter_data[$field_shortname])) {
                    //initialize storage for this field
                    $per_filter_data[$field_shortname] = array();
                }
                //store submitted value by field, then by GUI element
                $per_filter_data[$field_shortname][$submit_shortname] = $submit_value;
            }
        }
    }

    //add additional data from secondary filters
    if (!empty($filter_object->secondary_filterings)) {
        foreach ($filter_object->secondary_filterings as $secondary_filtering) {
            //recurse for this filter
            $temp_result = php_report_filtering_get_per_filter_data($secondary_filtering, $form_data);

            //merge data into result
            if (!empty($temp_result)) {
                foreach ($temp_result as $key => $value) {
                    //each filter should have only been shown once (see form for details)
                    if (!isset($per_filter_data[$key])) {
                        $per_filter_data[$key] = $value;
                    }
                }
            }
        }
    }

    return $per_filter_data;
}

/**
 * Signal that the supplied report has parameter defaults overridden
 *
 * @param  string  $report_shortname  The report whose defaults weare considering
 */
function php_report_filtering_flag_report_as_overridden($report_shortname) {
    global $SESSION;

    if (!isset($SESSION->php_report_default_override)) {
        //set up the array storing which reports have their defaults
        //temporarily overridden
        $SESSION->php_report_default_override = array();
    }

    if (!isset($SESSION->php_report_default_override[$report_shortname])) {
        //flag this report as being overridden
        $SESSION->php_report_default_override[$report_shortname] = 1;
    }
}

/**
 * Takes submitted parameter form information and determines preferences to add and remove
 *
 * @param   object  $filter_object    The filtering object for the report in question
 * @param   array   $per_filter_data  Field data mapped by field, then GUI element
 * @param   array   $to_delete        Update in-place with preference field keys to delete
 *
 * @return  array                     Preferences field keys and values to save
 */
function php_report_filtering_resolve_submitted_preferences($filter_object, $per_filter_data, &$to_delete, $report_shortname) {
    $preferences = array();
    $to_delete = array();

    //look through the filter's defined fields
    foreach ($filter_object->_fields as $field_shortname => $field) {

        //determine if this data is set
        if (isset($per_filter_data[$field_shortname])) {

            //this validates that the field is completely submitted and valid
            if ($field->check_data((object)$per_filter_data[$field_shortname])) {
                //if working with an array of options and some need to be unset, get the full list
                $all_options = array();
                if (isset($field->_options['choices'])) {
                    foreach($field->_options['choices'] as $key => $value) {
                        $all_options[$field_shortname.'_'.$key] = $value;
                    }
                }

                //store all related values as preferences
                foreach ($per_filter_data[$field_shortname] as $key => $value) {
                    if (is_string($value) && strpos($value, 'a:') === 0) {
                        $value = unserialize($value);
                    }
                    $preferences['php_report_' . $report_shortname . '/' . $key] = $value;
                    if (array_key_exists($key,$all_options)) {
                        unset($all_options[$key]);
                    }
                }
                // If there are any options left, they need to be added to the to_delete array
                if (is_array($all_options)) {
                    foreach ($all_options as $key => $value) {
                        if (!in_array($value,$preferences)) {
                            $to_delete[] = 'php_report_' . $report_shortname . '/' . $key;
                        }
                    }
                }
            } else {
                //invalid field data, so clear out all related fields
                $to_delete[] = 'php_report_' . $report_shortname . '/' . $field_shortname;
            }
        } else {
            //no data set, so clear out all related fields
            $to_delete[] = 'php_report_' . $report_shortname . '/' . $field_shortname;
        }
    }

    if (!empty($filter_object->secondary_filterings)) {
        foreach ($filter_object->secondary_filterings as $key => $secondary_filtering) {
            //determine which entries the secondary filtering wants to add or delete
            $child_to_delete = array();
            $child_preferences = php_report_filtering_resolve_submitted_preferences($secondary_filtering, $per_filter_data, $child_to_delete, $report_shortname);

            //merge results in
            $preferences = array_merge($preferences, $child_preferences);
            $to_delete = array_merge($to_delete, $child_to_delete);
        }
    }

    return $preferences;
}

/**
 * Specifies the pool of preferences from which default parameters should currently
 * be pulled
 *
 * @param   string   $report_shortname  Shortname of the report whose preferences we are obtaining
 *
 * @return  array                       Mapping of parameter form fields to values
 */
function php_report_filtering_get_user_preferences($report_shortname) {
    global $SESSION;

    // Start with database values
    $user_prefs = get_user_preferences();

    // Check for URL parameters and add them to session info
    if (!empty($_GET['report']) && $_GET['report'] == $report_shortname) {
        $reportid = 'php_report_'. $report_shortname;
        if (!isset($SESSION->php_report_default_params)) {
            $SESSION->php_report_default_params = array();
        }
        $urlparams = array();
        foreach ($_GET as $key => $val) {
            if ($key != 'report') {
                if (($grppos = strpos($key, ':')) !== false) {
                    // moodle urls no longer support arrays, new ':' syntax
                    $idx = substr($key, $grppos + 1);
                    $key = substr($key, 0, $grppos);
                    if (!isset($urlparams[$key])) {
                        $urlparams[$key] = array($idx => $val);
                    } else {
                        $urlparams[$key] = array_merge($urlparams[$key],
                                                       array($idx => $val));
                    }
                } else {
                    $urlparams[$key] = $val;
                }
            }
        }
        foreach ($urlparams as $key => $val) {
           /*
            ob_start();
            var_dump($val);
            $tmp = ob_get_contents();
            ob_end_clean();
            error_log("/blocks/php_report/lib/filtering.php::php_report_filtering_get_user_preferences($report_shortname) over-riding filter values with URL param: {$key} = {$tmp}");
           */
            $SESSION->php_report_default_params[$reportid.'/'.$key] = $val;
            //TBD: Must serialize arrays before storing in user preferences
            //     and unserialize when reading back values!
            //set_user_preferences(array($reportid.'/'.$key => $val)); // let's save URL params as persistent to avoid random session craziness issues

            //flag this report as having custom parameters set up
            php_report_filtering_flag_report_as_overridden($report_shortname);
        }
        $user_prefs = array_merge($user_prefs, $SESSION->php_report_default_params);
    } else if (!empty($SESSION->php_report_default_override[$report_shortname])) {
        // using temporary overrides (which contain last known state of form
        $user_prefs = $SESSION->php_report_default_params;
    }

    return $user_prefs;
}

/**
 * Sets user preferences, in temporary or presistent storage
 *
 * @param  array    $preferences  Preference mapping to save
 * @param  boolean  $temporary    If true, save in temporary storage, otherwise save in persistent storage
 */
function php_report_filtering_set_user_preferences($preferences, $temporary, $report_name) {
    global $SESSION, $_SESSION;

    // Ugly form dependency check and preference modification code
    // Note: this can be removed once MDL-27045 is resolved and 'checkbox' in elis/core/lib/filtering/date.php can be changed to 'advcheckbox'
    //       (or until someone can implement a cleaner way to handle this)

    //see if the current report is cached
    if (isset($_SESSION['SESSION']->php_reports[$report_name])) {
        $report_container = $_SESSION['SESSION']->php_reports[$report_name];

        //obtain the filter's main form;
        if (isset($report_container->inner_report->filter->_addform)) {
            $filter_form = $report_container->inner_report->filter->_addform;

            //grab the list required fields
            $dependencies = $filter_form->get_dependencies();

            foreach ($dependencies as $dependency => $data) {
                $check_pref = 'php_report_' . $report_name . '/' . $dependency;
                // If there is no related preference, it is probably because it's an unchecked checkbox so let's set it to 0
                if (!isset($preferences[$check_pref])) {
                    $preferences[$check_pref] = 0;
                }
            }
        }
    }
    // End ugly form dependency check and preference modification
    if (!$temporary) {
        //standard API call for persistent storage
        set_user_preferences($preferences);
        return;
    }

    //temporary storage
    if (!isset($SESSION->php_report_default_params)) {
        //make sure storage is set up
        $SESSION->php_report_default_params = array();
    }

    if (!empty($preferences)) {

        // Old session data from a given report needs to be removed
        $params = $SESSION->php_report_default_params;
        $prefix = 'php_report_' . $report_name . '/';
        foreach ($params as $key => $val) {
            if (strpos($key, $prefix) === 0) {
                $field_name = substr($key, strlen($prefix));
                unset($SESSION->php_report_default_params[$key]);
            }
        }

        // Add the new data from a given report to the session
        foreach ($preferences as $key => $value) {
            $SESSION->php_report_default_params[$key] = $value;
        }
    }
}

/**
 * Unsets marked preferences
 *
 * @param  $to_delete  array    Array of preference key prefixes
 * @param  $temporary  boolean  If true, unset from temporary preferences, otherwise unset from
 *                              persistent preferences
 */
function php_report_filtering_unset_invalid_user_preferences($to_delete, $temporary) {
    global $SESSION, $DB;

    if ($temporary) {
        //delete temporary preferences that are not valid
        if (!empty($to_delete) && !empty($SESSION->php_report_default_params)) {
            foreach ($to_delete as $item) {
                foreach ($SESSION->php_report_default_params as $key => $value) {
                    //prefix matches, so clear
                    if ($key == $item || strpos($key, $item . '_') === 0) {
                        unset($SESSION->php_report_default_params[$key]);
                    }
                }
            }
        }
    } else {
        //delete persistent preferences that are not valid
        if (!empty($to_delete)) {

            //query sql conditions
            $conditions = array();
            //query parameters
            $params = array();

            //set up conditions for any valid prefix-matching
            foreach ($to_delete as $key => $item) {
                //= '[item]' case
                $conditions[] = "name = :substringequalityitem".$key;
                $params['substringequalityitem'.$key] = $item;

                //ILIKE '[item]_%' case
                $conditions[] = $DB->sql_like('name', ':substringlikeitem'.$key, false);
                $params['substringlikeitem'.$key] = $item;
            }

            //try to be efficient in cases with a lot of data
            $where = implode(' OR ', $conditions);

            //find matching preferences and unset them
            if ($recordset = $DB->get_recordset_select('user_preferences', $where, $params)) {
                foreach ($recordset as $record) {
                    //user proper API function to handle stored session info
                    unset_user_preference($record->name);
                }
            }
        }
    }
}

/**
 * Takes submitted filter form data and stores the results as user preferences
 *
 * @param  stdClass   $form_data      The submitted form data
 * @param  object     $filter_object  Set of filters for the report in question
 * @param  string     $report_name    "Shortname" of the report
 */
function php_report_filtering_save_preferences($form_data, $filter_object, $report_name, $temporary = false) {
    global $SESSION;

    if ($temporary) {
        //temporary settings
        php_report_filtering_flag_report_as_overridden($report_name);
    }

    //step 1: group the submitted data by potential form element based on naming
    $per_filter_data = php_report_filtering_get_per_filter_data($filter_object, $form_data);

    //step 2: validate data
    $to_delete = array();
    $preferences = php_report_filtering_resolve_submitted_preferences($filter_object, $per_filter_data, $to_delete, $report_name);

    //step 3: set and unset appropriate data
    php_report_filtering_set_user_preferences($preferences, $temporary, $report_name);
    php_report_filtering_unset_invalid_user_preferences($to_delete, $temporary);

}

/**
 * Resets form filters
 *
 * @param  stdClass   $form_data      The submitted form data
 * @param  object     $filter_object  Set of filters for the report in question
 * @param  string     $report_name    "Shortname" of the report
 */
function php_report_filtering_reset_form($form_data, $filter_object, $report_name, &$parameter_form) {
    global $SESSION, $USER;

    if (isset($SESSION->php_report_default_override[$report_name])) {
        // turn off temporary settings
        unset($SESSION->php_report_default_override[$report_name]);
    }
    // Initialize default parameters
    $SESSION->php_report_default_params = array();

    // Get current filters and reset them
    $reset_array = array();
    $per_filter_data = php_report_filtering_get_per_filter_data($filter_object, $form_data);

    //loop through the filters, and get them to specify their defaults
    foreach ($per_filter_data as $filter_name => $filter_data) {
        //obtain the instance
        // Checking with isset() should not be necessary. This is a workaround.
        if (!empty($filter_object->_fields[$filter_name])) { // TBD: isset() ?
            //corresponds to a normal filter
            $filter_instance = $filter_object->_fields[$filter_name];
            //append the default values to our list
            $default_values = $filter_instance->get_default_values($filter_data);
            $reset_array = array_merge($reset_array, $default_values);
        } else {
            //check secondary filterings
            foreach ($filter_object->secondary_filterings as $secondary) {
                if (!empty($secondary->_fields[$filter_name])) {
                    $filter_instance = $secondary->_fields[$filter_name];
                    //append the default values to our list
                    $default_values = $filter_instance->get_default_values($filter_data);
                    $reset_array = array_merge($reset_array, $default_values);
                }
            }
        }
    }

    // start with getting the database preferences
    $existing_preferences = get_user_preferences();
    if ($existing_preferences) {
        foreach ($existing_preferences as $key => $value) {
            //if the preference is php-report-related, set the form field
            $prefix = 'php_report_'. $report_name . '/';
            if (strpos($key, $prefix) === 0) {
                //preference contains the form field name and UI value
                $field_name = substr($key, strlen($prefix));
                $reset_array[$field_name] = $value;
                if ($field_name == 'field'.$report_name) {
                    // initialize fieldidlist and fieldnamelist to cover the multi-select issue
                    $reset_array['fieldidlist'.$report_name]='';
                    $reset_array['fieldnamelist'.$report_name]='';
                }
            }

        }
    }

    if (is_array($reset_array)) {
        // initialize parameters
        $parameter_form->set_constants($reset_array);
    }
}

/**
 * Class that handles the calculation of SQL filters for both interactive and configured filters
 */
class php_report_default_capable_filtering extends generalized_filtering {

    var $reportname = '';
    var $secondary_filterings = array();
    //stores whether we're in interactive or scheduling mode
    var $execution_mode = null;

    /**
     * Constructor for a collection of filters that allows persistence through preferences
     *
     * @param  array   $fields                The individual filters being contained
     * @param  string  $baseurl               The base URL for form submission
     * @param  array   $extraparams           Any additional information needed
     * @param  mixed   $id                    Unique identifier for the containing report
     * @param  string  $reportname            Shortname of the containing report
     * @param  array   $secondary_filterings  Additional filters used for nonstandard actions
     */
    function php_report_default_capable_filtering($fields=null, $baseurl=null, $extraparams=null, $id=0, $reportname='', $secondary_filterings = array()) {
        parent::__construct($fields, $baseurl, $extraparams, $id);

        $this->reportname = $reportname;
        $this->secondary_filterings = $secondary_filterings;

        $this->set_execution_mode(php_report::EXECUTION_MODE_INTERACTIVE);
    }

    /**
     * Sets the execution mode for this filtering collection and all contained filters
     *
     * @param  int  $execution_mode  The constant representing the execution mode
     */
    function set_execution_mode($execution_mode) {
        $this->execution_mode = $execution_mode;

        //propagate to actual filters
        $this->update_child_execution_modes();
    }

    /**
     * Propagates the execution mode from this collection into all its elements
     */
    function update_child_execution_modes() {
        //just call this once to save on performance
        $execution_mode = $this->get_execution_mode();

        foreach ($this->_fields as $key => $field) {
            $field->set_execution_mode($execution_mode);
        }
    }

    /**
     * Specifies the current execution mode for this collection of filters
     *
     * @return  int  A constants representing the current execution mode
     */
    function get_execution_mode() {
        return $this->execution_mode;
    }

    /**
     * Specifies a pool of attributes to pull preferences from, organized by filter
     *
     * @return  array  Mapping of parameter shortname to a mapping of its UI element names to values
     */
    protected function get_preferences() {
        if (!empty($this->preferences_source_data)) {
            //using a pre-defined pool of preferences for API-related reasons,
            //so just convert them to the necessary format
            //error_log("/blocks/php_report/lib/filtering.php::get_preferences() ... !empty(this->preferences_source_data) -> returning");
            return php_report_filtering_get_per_filter_data($this, $this->preferences_source_data);
        }

        $per_filter_data = array();
        //obtain the current selection of preferences, based on stored
        //preferences and current active values
        $user_preferences = php_report_filtering_get_user_preferences($this->reportname);

        //go through and group accordingly
        foreach ($user_preferences as $key => $value) {
            //error_log("/blocks/php_report/lib/filtering.php::get_preferences(); {$key} => {$value}");

            $parts = explode('/', $key);
            //is preference php-report related?

            //prefix used to make sure we're in the right report
            $preference_prefix = 'php_report_' . $this->reportname;

            //is preference related to this report?
            //match exactly in case one report name prefixes another
            if ($parts[0] == $preference_prefix) {
                // TBD ^ WAS: if (strpos($parts[0], $preference_prefix) === 0)
                $element_name = $parts[1];
                //calculate the group name
                if (strpos($element_name, '_') !== FALSE) {
                    //multi-element group
                    $parts = explode('_', $element_name);
                    $group_name = $parts[0];
                } else {
                    //single-element group
                    $group_name = $element_name;
                }
                if (!isset($per_filter_data[$group_name])) {
                    //set up the array
                    $per_filter_data[$group_name] = array();
                }
                //append the data to the current group
                $per_filter_data[$group_name][$element_name] = $value;
            }
        }

        return $per_filter_data;
    }

    /**
     * Returns sql where statement based on active user filters
     *
     * @param   string  $extra  additional sql to add
     *
     * @return  array           the sql fragment and parameters to apply
     */
    function get_sql_filter($extra='', $exceptions = array(), $allow_interactive_filters = false, $allow_configured_filters = true, $secondary_filtering_key = '') {
        global $SESSION;

        if (isset($this->secondary_filterings[$secondary_filtering_key])) {
            //if this is not the primary filtering, use a secondary one
            return $this->secondary_filterings[$secondary_filtering_key]->get_sql_filter($extra, $exceptions, $allow_interactive_filters, $allow_configured_filters);
        }

        //parameter values
        $params = array();

        //interactive filters, if applicable
        if ($allow_interactive_filters) {
            list($result, $params) = parent::get_sql_filter($extra, $exceptions);
        } else {
            $result = '';
        }

        //if configured filters are not enabled for this report, just use interactive filtering,
        //if applicable
        if (!$allow_configured_filters) {
            return $result;
        }

        $per_filter_data = array();

        //sql fragments
        $sqls = array();

        //obtain the pool of attributes to pull preferences from
        $per_filter_data = $this->get_preferences();
        //print_object($per_filter_data);

        //grab the SQL filters
        foreach ($this->_fields as $shortname => $field) {
            if (isset($per_filter_data[$shortname])) {
                $formatted_data = $field->check_data((object)$per_filter_data[$shortname]);
                if ($formatted_data != false) {
                    //determine the additional sql fragment and parameters
                    list($additional_sql, $additional_params) = $field->get_sql_filter($formatted_data);
                    if ($additional_sql !== null) {
                        $sqls[] = $additional_sql;
                        $params = array_merge($params, $additional_params);
                    }
                }
            }
        }

        //combine SQL conditions
        if (!empty($sqls)) {
            $sql_piece = implode(' AND ', $sqls);
            if ($result == '') {
                $result = $sql_piece;
            } else {
                $result .= ' AND ' . $sql_piece;
            }
        }

        //return the sql fragment and parameters
        return array($result, $params);
    }

    /**
     * Sets a pool of preferences to select filters from (overrides defaults and
     * other parameters set in the session)
     *
     * @param  array  $source_data  Mapping, containing necessary mappings or formslib
     *                              element names to values
     */
    public function set_preferences_source_data($source_data) {
        $this->preferences_source_data = $source_data;
    }
}

/**
 * Returns the current filters parameter values for a specified filter name
 *
 * @param   string $filter_name The filter name
 * @return  array List of filter parameter values for given filter name, false if no results
 */
function php_report_filtering_get_active_filter_values($report_shortname,$filter_name,&$filter=NULL) {
    global $SESSION;

    $result = array();

    $reportid = 'php_report_' . $report_shortname;

    // Checks to see if filter values are set in the filter object
    if (!empty($filter->preferences_source_data)) {
        $params = $filter->preferences_source_data;

        foreach ($params as $key=>$val) {
            if ($key == $filter_name) {
                $result[] = array('value'=>$val);
            }
        }
    } else {
        // No filter values in the filter object
        // Make sure we have URL params set in session info
        if (!isset($SESSION->php_report_default_params)) {
            php_report_filtering_get_user_preferences($report_shortname);
        }

        if (isset($SESSION->php_report_default_params)) {
            $params = $SESSION->php_report_default_params;

            foreach ($params as $key=>$val) {
                if ($key == $reportid.'/'.$filter_name) {
                    $result[] = array('value'=>$val);
                }
            }
        }
    }

    if (!empty($result)) {
        return $result;
    } else {
        return false;
    }
}

class user_multi_active_filter_form extends moodleform {

    function definition() {
        global $SESSION; // this is very hacky :-(

        if(!empty($this->_customdata['id'])) {
            $filtering_array =& $SESSION->user_index_filtering[$this->_customdata['id']];
        } else {
            $filtering_array =& $SESSION->user_filtering;
        }

        $mform       =& $this->_form;
        $fields      = $this->_customdata['fields'];
        $extraparams = $this->_customdata['extraparams'];

        if (!empty($filtering_array)) {
            // add controls for each active filter in the active filters group
            $mform->addElement('header', 'actfilterhdr', get_string('actfilterhdr','filters'));

            foreach ($filtering_array as $fname=>$datas) {
                if (!array_key_exists($fname, $fields)) {
                    continue; // filter not used
                }
                $field = $fields[$fname];
                foreach($datas as $i=>$data) {
                    $description = $field->get_label($data);
                    $mform->addElement('checkbox', 'filter['.$fname.']['.$i.']', null, $description);
                }
            }

            if ($extraparams) {
                foreach ($extraparams as $key=>$value) {
                    $mform->addElement('hidden', $key, $value);
                    $mform->setType($key, PARAM_RAW);
                }
            }

            $objs = array();
            $objs[] = &$mform->createElement('submit', 'removeselected', get_string('removeselected','filters'));
            $objs[] = &$mform->createElement('submit', 'removeall', get_string('removeall','filters'));
            $mform->addElement('group', 'actfiltergrp', '', $objs, ' ', false);
        }
    }
}

