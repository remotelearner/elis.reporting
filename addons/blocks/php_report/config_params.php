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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot . '/blocks/php_report/parameter_form.class.php');
require_once($CFG->dirroot . '/curriculum/lib/filtering/lib.php');
require_once($CFG->dirroot . '/blocks/php_report/php_report_block.class.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/blocks/php_report/lib/filtering.php');

//report instance id
$id = required_param('id', PARAM_CLEAN);
//optional action url for form
$action = optional_param('url', null);

//require dependencies for filters and the report itself
php_report_filtering_require_dependencies();
php_report_block::require_dependencies($id);

//create the form, whose contents depend on on the current report's available filters
if (!empty($SESSION->php_reports[$id]->inner_report->filter)) {
    $filter_object = $SESSION->php_reports[$id]->inner_report->filter;
    //reset form reset value
    $SESSION->php_reports[$id]->inner_report->filter->reset=false;

    $parameter_form = new parameter_form($action, array('filterobject' => $filter_object));
} else {
    $parameter_form = new parameter_form($action);
}

//send report id to the form
$parameter_form->set_data(array('id' => $id));

//get the report name
$report_name = get_class($SESSION->php_reports[$id]->inner_report);
$report_name = substr($report_name, 0, strlen($report_name) - strlen('_report'));

//update form with current settings
php_report_filtering_update_form($report_name, $parameter_form);



if ($data = $parameter_form->get_data()) {
    //NOTE: this has to be checked after get_data in this case
    //because get_data calls definition_after_data, which adds the cancel button
    if ($parameter_form->is_cancelled()) {
        if ($SESSION->php_reports[$id]->lastload) {
            //just re-display the report
            echo $SESSION->php_reports[$id]->display();
            die;
        }
    } else if (isset($data->save_defaults)) {
        //store form settings as report-specific user preferences
        php_report_filtering_save_preferences($data, $filter_object, $report_name);
    } else if (isset($data->reset_form)) {
        //set reset flag to true
        $SESSION->php_reports[$id]->inner_report->filter->reset=true;
        //store form settings as report-specific user preferences
        php_report_filtering_reset_form($data, $filter_object, $report_name, $parameter_form);

    } else if (isset($data->show_report)) {
        //store temporary preferences
        php_report_filtering_save_preferences($data, $filter_object, $report_name, true);

        //reset the state of the report
        $SESSION->php_reports[$id]->reset_state();
        echo $SESSION->php_reports[$id]->display();
        die;
    }
}

/**
 * Displaying the form
 */
$parameter_form->display();

?>
