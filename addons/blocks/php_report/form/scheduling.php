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
 * @subpackage php_reports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once ($CFG->libdir.'/formslib.php');
require_once ($CFG->dirroot.'/blocks/php_report/sharedlib.php');
require_once ($CFG->dirroot.'/blocks/php_report/parameter_form.class.php');
require_once ($CFG->dirroot.'/elis/core/lib/form/timeselector.php');

// Javascript files required to convert enter from form cancel to next step
function require_js_files() {
    global $PAGE;
    $PAGE->requires->js('/lib/javascript-static.js'); // addonload()
    $PAGE->requires->js('/blocks/php_report/js/entertonext.js');
}

class scheduling_form_step_label extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        if (isset($workflow->id)) {
            $mform->addElement('hidden', '_wfid', $workflow->id);
        } else {
            $mform->addElement('hidden', 'report', $page->required_param('report', PARAM_FILE));
        }

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_LABEL);
        $mform->addElement('hidden', 'action', 'save');

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('label_description', 'block_php_report')).'</h2>');

        $mform->addElement('text', 'label', get_string('label', 'block_php_report'));
        $mform->setType('label', PARAM_CLEAN);
        $mform->addRule('label', get_string('required_field', 'block_php_report', get_string('label', 'block_php_report')), 'required', null, 'server');
        // ^ELIS-3316: server validation so cancel/submit will work

        $options=array('cols'=>'30');
        $mform->addElement('textarea', 'description', get_string('description'),$options);

        workflowpage::add_navigation_buttons($mform);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_schedule extends moodleform {
    function definition() {
        global $PAGE;
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;
        // Get the workflow data for the timezone to keep the time_selector in line
        $workflowdata = $workflow->unserialize_data(array());

        // Include required yui javascript needed for AJAX calls
        $PAGE->requires->yui2_lib(array('yahoo',
                                        'dom'));

        $mform->addElement('html','');
        // Add javascript function to toggle the simple/recurring calendar elements
        // Also add a listener to show/hide the simple/calendar elements on page load
        $mform->addElement('html', '<script type="text/javascript">
            function switchCalendar() {
                var showHide = document.getElementsByName("recurrencetype");
                var simple = document.getElementById("simplerecurrencegroup");
                var calendar = document.getElementById("calendarrecurrencegroup");
                if (showHide["0"].checked) {
                    if(showHide["0"].value == \'calendar\')
                    {
                        simple.className = "accesshide scheduling_action_schedule_fieldset";
                        calendar.className = "clearfix scheduling_action_schedule_fieldset";
                    }
                    else
                    {
                        simple.className = "clearfix scheduling_action_schedule_fieldset";
                        calendar.className = "accesshide scheduling_action_schedule_fieldset";
                    }
                }
                else
                    if(showHide["0"].value == \'simple\')
                    {
                        simple.className = "accesshide scheduling_action_schedule_fieldset";
                        calendar.className = "clearfix scheduling_action_schedule_fieldset";
                    }
                    else
                    {
                        simple.className = "clearfix scheduling_action_schedule_fieldset";
                        calendar.className = "accesshide scheduling_action_schedule_fieldset";
                    }
            }
        function initCalendar() {
            YAHOO.util.Event.addListener(window, "load", switchCalendar());
        }
        YAHOO.util.Event.onDOMReady(initCalendar);
        </script>');

        $mform->addElement('hidden', '_wfid', $workflow->id);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_SCHEDULE);
        $mform->addElement('hidden', 'action', 'save');

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('recurrence_description', 'block_php_report')).'</h2>');

        $choices = get_list_of_timezones();
        $choices['99'] = get_string('serverlocaltime');
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', 99);

        $group = array();
        $group[] = $mform->createElement('radio', 'starttype', '', get_string('now', 'block_php_report'), 0);
        // Add onclick action to toggle the calendar
        $mform->_attributes['onclick'] = 'switchCalendar();';
        $group[] = $mform->createElement('radio', 'starttype', '', get_string('time_on', 'block_php_report'), 1);
        // Add onclick action to toggle the calendar
        $mform->_attributes['onclick'] = 'switchCalendar();';
        //Set date options: timezone = 0 so it doesn't adjust the time ...
        $date_options = array('timezone' => 0, 'optional' => false, 'startyear' => userdate(time(), '%Y', -13, false), 'stopyear' => 2038, 'applydst' => false);
        $group[] = $mform->createElement('date_selector', 'startdate', '', $date_options);
        $mform->addGroup($group, 'starttype', get_string('start', 'block_php_report'), '', false);
        $mform->setDefault('starttype', 0);
        $mform->addRule('starttype', get_string('required_field', 'block_php_report', get_string('start', 'block_php_report')), 'required', null, 'client');
        $mform->disabledIf('startdate', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[day]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[month]', 'starttype', 'neq', 1);
        $mform->disabledIf('startdate[year]', 'starttype', 'neq', 1);

        $group = array();
        $group[] = $mform->createElement('radio', 'recurrencetype', '', get_string('simple_recurrence', 'block_php_report'), scheduling_workflow::RECURRENCE_SIMPLE);
        $group[] = $mform->createElement('radio', 'recurrencetype', '', get_string('calendar_recurrence', 'block_php_report'), scheduling_workflow::RECURRENCE_CALENDAR);
        $mform->addGroup($group, 'recurrencetype', get_string('recurrence', 'block_php_report'), '', false);
        $mform->setDefault('recurrencetype', scheduling_workflow::RECURRENCE_SIMPLE);
        $mform->addRule('recurrencetype', get_string('required_field', 'block_php_report', get_string('recurrence', 'block_php_report')), 'required', null, 'client');

        $mform->addElement('header', 'simplerecurrencegroup', get_string('simple_recurrence_settings', 'block_php_report'));
        $group = array();
        $group[] = $mform->createElement('radio', 'runtype', '', get_string('indefinitely', 'block_php_report'), 0);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'runtype', '', get_string('until', 'block_php_report'), 1);
        $group[] = $mform->createElement('date_selector', 'enddate', '', $date_options);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'runtype', '', '', 2);
        $group[] = $mform->createElement('text', 'runsremaining', '', array('size' => 2));
        $group[] = $mform->createElement('static', '', '', get_string('times', 'block_php_report'));
        $group[] = $mform->createElement('static', '', '', '<br />&nbsp;&nbsp;&nbsp;&nbsp;'.get_string('every', 'block_php_report'));
        $group[] = $mform->createElement('text', 'frequency', '', array('size' => 2));
        $options = array(
            scheduling_workflow::FREQ_HOUR  => get_string('freq_hours', 'block_php_report'),
            scheduling_workflow::FREQ_DAY   => get_string('freq_days', 'block_php_report'),
            scheduling_workflow::FREQ_MONTH => get_string('freq_months', 'block_php_report'),
        );
        $group[] = $mform->createElement('select', 'frequencytype', '', $options);
        $mform->addGroup($group, 'runtype', get_string('runtype', 'block_php_report'), '', false);
        $mform->disabledIf('enddate', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[day]', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[month]', 'runtype', 'neq', 1);
        $mform->disabledIf('enddate[year]', 'runtype', 'neq', 1);
        $mform->disabledIf('runsremaining', 'runtype', 'neq', 2);
        $mform->setType('runsremaining', PARAM_INT);
        $mform->setDefault('runsremaining', 1);
        $mform->disabledIf('frequency', 'runtype', 'neq', 2);
        $mform->disabledIf('frequencytype', 'runtype', 'neq', 2);
        $mform->setType('frequency', PARAM_INT);
        $mform->setDefault('frequency', 1);
        $mform->setDefault('frequencytype', scheduling_workflow::FREQ_DAY);

        $mform->addElement('header', 'calendarrecurrencegroup', get_string('calendar_recurrence_settings', 'block_php_report'));
        $mform->addElement('date_selector', 'calenddate', get_string('enddate', 'block_php_report'), array('optional' => true, 'timezone' => 0, 'applydst' => false));

        //Set timezone so it doesn't adjust the time
        $tsoptions = array('timezone' => '0', 'year' => 1971,
                           'applydst' => false);
        $mform->addElement('time_selector', 'time', get_string('time'), $tsoptions);

        $group = array();
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('everyday', 'block_php_report'), 0);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('weekdays', 'block_php_report'), 1);
        $group[] = $mform->createElement('static', '', '', '<br />&nbsp;&nbsp;&nbsp;&nbsp;');
        $group[] = $mform->createElement('checkbox', 'dayofweek[1]', '', get_string('mon', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[2]', '', get_string('tue', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[3]', '', get_string('wed', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[4]', '', get_string('thu', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[5]', '', get_string('fri', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[6]', '', get_string('sat', 'calendar'));
        $group[] = $mform->createElement('checkbox', 'dayofweek[7]', '', get_string('sun', 'calendar'));
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('radio', 'caldaystype', '', get_string('monthdays', 'block_php_report'), 2);
        $group[] = $mform->createElement('text', 'monthdays', '', array('size' => 6));

        $mform->addGroup($group, 'daytype', get_string('days', 'block_php_report'), '', false);
        $mform->disabledIf('dayofweek[1]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[2]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[3]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[4]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[5]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[6]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('dayofweek[7]', 'caldaystype', 'neq', 1);
        $mform->disabledIf('monthdays', 'caldaystype', 'neq', 2);

        $group = array();
        $group[] = $mform->createElement('advcheckbox', 'allmonths', '', get_string('all'), 1);
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('checkbox', 'month[1]', '', strftime('%b', mktime(0, 0, 0, 1, 1)));
        $group[] = $mform->createElement('checkbox', 'month[2]', '', strftime('%b', mktime(0, 0, 0, 2, 1)));
        $group[] = $mform->createElement('checkbox', 'month[3]', '', strftime('%b', mktime(0, 0, 0, 3, 1)));
        $group[] = $mform->createElement('checkbox', 'month[4]', '', strftime('%b', mktime(0, 0, 0, 4, 1)));
        $group[] = $mform->createElement('checkbox', 'month[5]', '', strftime('%b', mktime(0, 0, 0, 5, 1)));
        $group[] = $mform->createElement('checkbox', 'month[6]', '', strftime('%b', mktime(0, 0, 0, 6, 1)));
        $group[] = $mform->createElement('static', '', '', '<br />');
        $group[] = $mform->createElement('checkbox', 'month[7]', '', strftime('%b', mktime(0, 0, 0, 7, 1)));
        $group[] = $mform->createElement('checkbox', 'month[8]', '', strftime('%b', mktime(0, 0, 0, 8, 1)));
        $group[] = $mform->createElement('checkbox', 'month[9]', '', strftime('%b', mktime(0, 0, 0, 9, 1)));
        $group[] = $mform->createElement('checkbox', 'month[10]', '', strftime('%b', mktime(0, 0, 0, 10, 1)));
        $group[] = $mform->createElement('checkbox', 'month[11]', '', strftime('%b', mktime(0, 0, 0, 11, 1)));
        $group[] = $mform->createElement('checkbox', 'month[12]', '', strftime('%b', mktime(0, 0, 0, 12, 1)));
        $mform->addGroup($group, 'month', get_string('months', 'block_php_report'), '', false);
        for ($i = 1; $i <= 12; ++$i) {
            $mform->disabledIf("month[{$i}]", 'allmonths', 'checked');
        }

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_LABEL);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_parameters extends parameter_form {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        if (isset($this->_customdata['workflow'])) {
            $workflow = $this->_customdata['workflow'];
        } else {
            $page = $this->_customdata['page'];
            $workflow = $page->workflow;
        }

        $mform->addElement('hidden', '_wfid', $workflow->id);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_PARAMETERS);
        $mform->addElement('hidden', 'action', 'save');

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('parameters_description', 'block_php_report')).'</h2>');

        $mform->addElement('header', 'newfilter', get_string('newfilter','filters'));
    }

    function definition_after_data() {
        $mform =& $this->_form;

        if (isset($this->_customdata['filterobject'])) {
            //filter object was passed, because the report uses filters
            $filter_object = $this->_customdata['filterobject'];

            //handle adding of UI fields for secondary filterings
            if (!empty($filter_object->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $filtering) {
                    $this->add_filtering_elements($filtering);
                }
            }

            //add all filter form elements to this form for the main filtering
            $this->add_filtering_elements($filter_object);

            //add any required field rules
            foreach ($filter_object->_fields as $key=>$fields) {
                if (!empty($fields->_isrequired)) {
                    $required_rule_method = $filter_object->reportname . '_report::apply_filter_required_rule';

                    if (is_callable($required_rule_method)) {
                        // We have a custom requirement rule so let's use it
                        $mform = call_user_func_array($required_rule_method, array($mform,$key,$fields));
                    } elseif ($mform->elementExists($key)) {
                        // Basic requirement rule
                        $mform->addRule($key, get_string('required'), 'required', null, 'client');
                    }
                }
            }
        } else {
            //report does not use filters
            $mform->addElement('static', 'noparams', '', get_string('label_no_parameters', 'block_php_report'));
        }
        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_SCHEDULE);
    }

    /**
     * Performs the server side validation
     * @access    public
     * @since     1.0
     * @return    boolean   true if no error found
     */
    function validate() {
        return $this->_form->validate();
    }

    /**
     * Obtains the errors currently associated with this form
     *
     * @return  array  The errors
     */
    function get_errors() {
        if (empty($this->_form->_errors)) {
            return array();
        }

        return $this->_form->_errors;
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

/**
 * Form that displays available export formats during scheduling,
 * based on the applicable report definition
 */
class scheduling_form_step_format extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        $mform->addElement('hidden', '_wfid', $workflow->id);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_FORMAT);
        $mform->addElement('hidden', 'action', 'save');

        $mform->addElement('html', '<h2>'.htmlspecialchars(get_string('format_description', 'block_php_report')).'</h2>');

        $radioarray = array();

        //obtain the actual report instance so we can check its export formats
        $data = unserialize($workflow->data);
        $report_shortname = $data['report'];
        $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);

        //global list of all available export formats
        $allowable_export_formats = php_report::get_allowable_export_formats();
        //export formats supported by the report we are scheduling
        $export_formats = $report_instance->get_export_formats();

        //add an export option for each applicable format
        if ($report_instance->can_view_report()) {
            foreach ($allowable_export_formats as $allowable_export_format) {
                if (in_array($allowable_export_format, $export_formats)) {
                    $radioarray[] = $mform->createElement('radio', 'format', '', get_string($allowable_export_format, 'block_php_report'), $allowable_export_format);
                }
            }
        }

        $mform->addGroup($radioarray, 'format', get_string('format'), '', false);
        $mform->addRule('format', get_string('required_field', 'block_php_report', get_string('format')), 'required', null, 'client');
        $mform->setDefault('format', 'csv');

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_PARAMETERS);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_recipients extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;

        $page = $this->_customdata;
        $workflow = $page->workflow;

        $mform->addElement('hidden', '_wfid', $workflow->id);

        $mform->addElement('hidden', '_step', scheduling_workflow::STEP_RECIPIENTS);
        $mform->addElement('hidden', 'action', 'save');

        $mform->addElement('html', '<h2>'. htmlspecialchars(get_string('recipients_description', 'block_php_report')). '</h2>');
        $mform->addElement('html', '<label for="recipients"><b>'. htmlspecialchars(get_string('enter_emails', 'block_php_report')) .'</b><br/><i>'. htmlspecialchars(get_string('email_instructions', 'block_php_report')) .'</i></label>');

        $mform->addElement('text', 'recipients', get_string('recipientslist'), array('size' => 60));
        $mform->addRule('recipients', get_string('required_field', 'block_php_report', get_string('recipientslist')), 'required', null, 'server');
        // ^ELIS-3316: server validation so cancel/submit will work

        $mform->addElement('htmleditor', 'message', '<div class="php_report_bold_header">'.htmlspecialchars(get_string('message', 'block_php_report')).'</div>');

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_FORMAT);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

class scheduling_form_step_confirm extends moodleform {
    function definition() {
        require_js_files();
        $mform =& $this->_form;
        $page = $this->_customdata['page'];
        $workflow = $page->workflow;
        $data = $workflow->unserialize_data();

        // Configure the schedule display
        $schedule_string = '';
        $once = '1';

        // Replace timezone with proper text
        $tz = $data['timezone'];
        $timezone = usertimezone($tz);

        debug_error_log("/blocks/php_report/form/scheduling.php::scheduling_form_step_confirm::definition() enddate = {$data['schedule']['enddate']}, timezone = {$tz} ({$timezone})");
        // Set up for simple recurrence
        if ($data['recurrencetype'] == scheduling_workflow::RECURRENCE_SIMPLE) {
            if ($data['schedule']['runsremaining'] == '' && $data['schedule']['enddate'] == '') {
                $schedule_string = get_string('indefinitely', 'block_php_report');
            } elseif ($data['schedule']['enddate'] != ''){
                $enddate = userdate($data['schedule']['enddate'] + DAYSECS - 1, '', $tz);
                // 2nd param to userdate() was: get_string('strftimedaydate')
                $schedule_string = get_string('simple_until', 'block_php_report', $enddate);
            } else {
                // Frequency schedule
                $a = new stdClass;
                $a->num = $data['schedule']['runsremaining'];
                $a->freq = $data['schedule']['frequency'];
                $a->freqtype = $data['schedule']['frequencytype'];
                $schedule_string = get_string('simple_freq', 'block_php_report', $a);

            }
        } else {
            // calendar schedule
            $min = str_pad($data['schedule']['minute'],2,'0',STR_PAD_LEFT);
            $hour = $data['schedule']['hour'];
            $schedule_string .= get_string('at_time','block_php_report',
                                        //   "{$hour}:{$min}");
                                         "{$hour}:{$min} ({$timezone})");
            // days
            if ($data['schedule']['dayofweek'] == '*' && $data['schedule']['day'] == '*') {
                $schedule_string .= get_string('every_day', 'block_php_report');
            } elseif ($data['schedule']['day'] == '*') {
                //Weekdays specified
                $days_array = array ('1' => get_string('mon', 'calendar'),
                                     '2' => get_string('tue', 'calendar'),
                                     '3' => get_string('wed', 'calendar'),
                                     '4' => get_string('thu', 'calendar'),
                                     '5' => get_string('fri', 'calendar'),
                                     '6' => get_string('sat', 'calendar'),
                                     '7' => get_string('sun', 'calendar')
                                     );
                $days_selected_array = explode(',',$data['schedule']['dayofweek']);
                $days_string = '';
                $count = 0;
                foreach ($days_selected_array as $weekday) {
                    if ($count > 0) {
                        $days_string .= ', ';
                    }
                    $days_string .= $days_array[$weekday];
                    $count++;
                }
                //Replace last , with the word and
                $days_string = strrev(preg_replace('/,/',strrev(get_string('and','block_php_report')),strrev($days_string), $once));
                $schedule_string .= get_string('on_days', 'block_php_report',$days_string);
            } else {
                //Specific days of the month
                $days_string = strrev(preg_replace('/,/',strrev(get_string('and','block_php_report')),strrev($data['schedule']['day']), $once));
                $schedule_string .= get_string('month_days', 'block_php_report',$days_string);

            }
            // months
            if ($data['schedule']['month'] == '1,2,3,4,5,6,7,8,9,10,11,12' || $data['schedule']['month'] == '*') {
                $months_string = get_string('every_month', 'block_php_report');
                $data['schedule']['month'] = '1,2,3,4,5,6,7,8,9,10,11,12';
            } else {
                $months_array = array ('1' => strftime('%b', mktime(0, 0, 0, 1, 1)),
                                       '2' => strftime('%b', mktime(0, 0, 0, 2, 1)),
                                       '3' => strftime('%b', mktime(0, 0, 0, 3, 1)),
                                       '4' => strftime('%b', mktime(0, 0, 0, 4, 1)),
                                       '5' => strftime('%b', mktime(0, 0, 0, 5, 1)),
                                       '6' => strftime('%b', mktime(0, 0, 0, 6, 1)),
                                       '7' => strftime('%b', mktime(0, 0, 0, 7, 1)),
                                       '8' => strftime('%b', mktime(0, 0, 0, 8, 1)),
                                       '9' => strftime('%b', mktime(0, 0, 0, 9, 1)),
                                      '10' => strftime('%b', mktime(0, 0, 0, 10, 1)),
                                      '11' => strftime('%b', mktime(0, 0, 0, 11, 1)),
                                      '12' => strftime('%b', mktime(0, 0, 0, 12, 1))
                                       );
                $months_selected_array = explode(',', $data['schedule']['month']);
                $months_string = '';
                $count = 0;
                foreach ($months_selected_array as $month) {
                    if ($count > 0) {
                        $months_string .= ', ';
                    }
                    $months_string .= $months_array[$month];
                    $count++;
                }
                //Replace last , with the word and
                $months_string = strrev(preg_replace('/,/', strrev(get_string('and','block_php_report')), strrev($months_string), $once));
            }
            $schedule_string .= get_string('for_month', 'block_php_report',$months_string);

            //Include end date if selected
            if (isset($data['schedule']['enddate']) && $data['schedule']['enddate'] != '') {
                $schedule_string .= get_string('until_date', 'block_php_report',
                                        userdate($data['schedule']['enddate'] + DAYSECS - 1, '', $tz)); // '' was: get_string('strftimedaydate')
            } else {
                $schedule_string .= get_string('until_indefinitely',
                                               'block_php_report');
            }

        }

        // Create the param_string for selected parameters and their values
        $param_string = '';

        if (isset($this->_customdata['filterobject']) && isset($data['parameters'])) {
            $report = $page->workflow->get_report_instance();

            // Configure the parameter display
            $parameters = $data['parameters'];

            // Use this object to set up our parameter setting
            $filter_object = $this->_customdata['filterobject'];
            $filter_object_fields = $filter_object->_fields;
            // Merge in any secondary filterings here...
            if (isset($this->_customdata['filterobject']->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $secondary_filtering) {
                    $filter_object_fields = array_merge($filter_object_fields, $secondary_filtering->_fields);
                }
            }

            if (!empty($filter_object_fields)) {
                $fields = $filter_object_fields;
                // Create empty filtering array to be used following...
                $filtering_array = array();
                foreach ($parameters as $pname=>$pvalue) {
                    if (strpos($pname, '_') !== FALSE) {
                        //multi-element group
                        $parts = explode('_', $pname);
                        $group_name = $parts[0];
                    } else {
                        $group_name = '';
                    }
                    if (isset($fields[$pname])) {
                        $sub_data = $fields[$pname]->check_data($parameters);
                        if ($sub_data === false) {
                            continue; // nothing new
                        }
                    } elseif (isset($fields[$group_name])) {
                        $sub_data = $fields[$group_name]->check_data($parameters);
                        if ($sub_data === false) {
                            continue; // nothing new
                        }
                        $pname = $group_name;
                    } else {
                       continue;
                    }
                    //update filter object with check_data contents
                    if (!isset($filtering_array[$pname])) {
                        $filtering_array[$pname][] = $sub_data;
                    }
                }
                //Now get the description etc.
                $count = 0;
                foreach ($filtering_array as $fname=>$datas) {
                    if (!array_key_exists($fname, $filter_object_fields)) {
                        continue; // filter not used
                    }
                    $field = $fields[$fname];
                    foreach($datas as $i=>$sub_data) {
                        $description = $field->get_label($sub_data);
                        if ($count > 0) {
                            $param_string .= '<br>';
                        }
                        $param_string .= $description;
                        $count++;
                    }
                }
            }
        }

        if (isset($this->_customdata['secondary_filterings'])) {
            // Use this object to set up our parameter setting
            $filter_object = $this->_customdata['secondary_filterings'];
            //add additional data from secondary filters
            foreach ($filter_object as $secondary_filtering) {
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

        if ($param_string == '') {
            //report does not use filters
            $param_string = get_string('label_no_parameters', 'block_php_report');
        }

        // Set proper date display or NA for end date
        if (isset($data['schedule']['enddate']) && $data['schedule']['enddate'] != null) {
            $enddate = userdate($data['schedule']['enddate'],
                                get_string('strftimedaydate'), $tz);
        } else {
            $enddate = get_string('na','block_php_report');
        }

        $mform->addElement('hidden', '_wfid', $workflow->id);

        $mform->addElement('static', 'label', get_string('label', 'block_php_report').':', htmlspecialchars($data['label']));
        $mform->addElement('static', 'description', get_string('description').':', htmlspecialchars($data['description']));
        $mform->addElement('static', 'timezone', get_string('timezone').':', $timezone);
        $mform->addElement('static', 'startdate', get_string('startdate', 'block_php_report').':', userdate($data['startdate'],get_string('strftimedaydate'), $tz));
        $mform->addElement('static', 'enddate', get_string('enddate', 'block_php_report').':', $enddate);
        $mform->addElement('static', 'recurrencetype', get_string('recurrencetype', 'block_php_report').':', htmlspecialchars($data['recurrencetype']));
        $mform->addElement('static', 'schedule', get_string('schedule').':', $schedule_string);
        $mform->addElement('static', 'parameters', get_string('parameters', 'block_php_report').':', $param_string);
        $mform->addElement('static', 'format', get_string('format').':', get_string($data['format'], 'block_php_report'));
        $mform->addElement('static', 'recipients', get_string('recipientslist').':', htmlspecialchars($data['recipients']));
        $mform->addElement('static', 'message', get_string('message', 'message').':', $data['message']);

        workflowpage::add_navigation_buttons($mform, scheduling_workflow::STEP_RECIPIENTS, workflow::STEP_FINISH);
    }

    /**
     * Set error message for a form element
     *
     * @param     string    $element    Name of form element to set error for
     * @param     string    $message    Error message, if empty then removes the current error message
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setElementError($element, $message = null) {
        $this->_form->setElementError($element, $message);
    }
}

