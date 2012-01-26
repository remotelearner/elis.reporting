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

//define('DEBUG_BROWSER_EMBEDDED_JS', 1);

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/blocks/php_report/parameter_form.class.php');
require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
require_once($CFG->libdir .'/formslib.php');
require_once($CFG->dirroot .'/blocks/php_report/lib/filtering.php');

//not using require_login here because permissions are determined
//by the reports themselves

//report shortname
$report_shortname = required_param('id', PARAM_CLEAN);
//optional action url for form
$action = optional_param('url', null, PARAM_CLEAN);

$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
if (empty($this)) {
    // Called from AJAX, set emebeddded to force formslib formcounter to 999
    $PAGE->set_pagelayout('embedded');
}

//require dependencies for filters
php_report_filtering_require_dependencies();

//key report data
$instance = php_report::get_default_instance($report_shortname);
//NOTE: this is slow because it populates filter values
$filters = $instance->get_filters();

//obtain any necessary information regarding secondary filterings
$dynamic_report_filter_url = $CFG->wwwroot .'/blocks/php_report/dynamicreport.php?id='. $report_shortname;
$secondary_filterings = $instance->get_secondary_filterings(
                                       $dynamic_report_filter_url,
                                       $report_shortname, $report_shortname);

//when set, show the cancel button
$showcancel = optional_param('showcancel', 0, PARAM_INT);

//create the form, whose contents depend on on the current report's available filters
if (!empty($filters)) {
    //report has filters
    $dynamic_report_filter_url = $CFG->wwwroot.'/blocks/php_report/dynamicreport.php?id='.$report_shortname;
    $filter_object = new php_report_default_capable_filtering($filters,
                             $dynamic_report_filter_url, null, $report_shortname, $report_shortname, $secondary_filterings);
    $params = array('filterobject' => $filter_object,
                    'showcancel' => $showcancel);
    $parameter_form = new parameter_form($action, $params);
} else {
    //report does not have filters
    $params = array('showcancel' => $showcancel);
    $parameter_form = new parameter_form($action, $params);
}

//send report id to the form
$parameter_form->set_data(array('id' => $report_shortname, 'showcancel' => $showcancel));

//update form with current settings
php_report_filtering_update_form($report_shortname, $parameter_form);

//determine if we are resetting the form
$reset_form = optional_param('reset_form', '', PARAM_CLEAN);

if (!empty($reset_form)) {
    //reset case - do not use get_data because it performs validation
    $data = data_submitted();

    //store form settings as report-specific user preferences
    php_report_filtering_reset_form($data, $filter_object, $report_shortname, $parameter_form);
} else if ($data = $parameter_form->get_data()) {
    //NOTE: this has to be checked after get_data in this case
    //because get_data calls definition_after_data, which adds the cancel button
    if ($parameter_form->is_cancelled()) {
        //just re-display the report
        $instance->main('', '', 0, 20, '', $report_shortname);
        die;
    } else if (isset($data->save_defaults)) {
        //store form settings as report-specific user preferences
        php_report_filtering_save_preferences($data, $filter_object, $report_shortname);
    } else if (isset($data->show_report)) {
        //store temporary preferences
        php_report_filtering_save_preferences($data, $filter_object, $report_shortname, true);

        //re-display the report
        $instance->main('', '', 0, 20, '', $report_shortname);
        die;
    }
}

/**
 * Displaying the form
 */
$parameter_form->display();
if (empty($this)) {
    // called from AJAX ...
    $jscode = '
<script type="text/javascript">
//<![CDATA[
M.form.dependencyManager = null;
//]]>
</script>
'
              . $PAGE->requires->get_end_code();

    if (defined('DEBUG_BROWSER_EMBEDDED_JS')) {
        // Enable to test if browsers runs this Javascript code!
        $jscode .=  '
<script type="text/javascript">
//<![CDATA[
    alert("In outer form javascript re-init code");
//]]>
</script>
';
    }
    // Must re-init help_icons onclick and form dependencies ...
    echo $jscode;
    //error_log('config_params.php (AJAX): '. $jscode);
}
