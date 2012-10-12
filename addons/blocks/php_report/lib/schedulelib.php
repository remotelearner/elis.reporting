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

require_once $CFG->dirroot.'/elis/core/lib/setup.php';
require_once $CFG->dirroot.'/elis/core/lib/tasklib.php';
require_once $CFG->dirroot.'/elis/core/lib/workflow.class.php';
require_once $CFG->dirroot.'/elis/core/lib/workflowpage.class.php';
require_once $CFG->dirroot.'/blocks/php_report/php_report_base.php';
require_once $CFG->dirroot.'/blocks/php_report/form/scheduling.php';
require_once $CFG->dirroot.'/blocks/php_report/sharedlib.php';

/**
 * Report scheduling workflow data.  Data is an array with the following keys:
 * report: the shortname of the report to run
 * label: the report schedule label
 * description: the description of the report schedule
 * timezone: the time zone of the schedule
 * startdate: the date to start (null for now)
 * recurrencetype: (the recurrence type 'simple' or 'calendar')
 * schedule: schedule data (array), depending on the recurrence type
 *   if simple, keys are:
 *     enddate: date to run until (or null)
 *     runsremaining: number of runs (or null)
 *     frequency: how often to run (if runsremaning is non-null)
 *     frequencytype: hour/day/month (if runsremaning is non-null)
 *   if calendar, keys are:
 *     enddate: date to run until (or null)
 *     hour:
 *     minute:
 *     dayofweek:
 *     day:
 *     month:
 * parameters: the serialized report parameters
 * format: the output format ('pdf' or 'csv')
 * recipients: email addresses of scheduled report recipients
 * message: message to include in email
 */
class scheduling_workflow extends workflow {
    const STEP_LABEL = 'label';
    const STEP_SCHEDULE = 'schedule';
    const STEP_PARAMETERS = 'parameters';
    const STEP_FORMAT = 'format';
    const STEP_RECIPIENTS = 'recipients';

    const RECURRENCE_SIMPLE = 'simple';
    const RECURRENCE_CALENDAR = 'calendar';

    const FREQ_HOUR = 'hour';
    const FREQ_DAY = 'day';
    const FREQ_MONTH = 'month';

    public function get_report_instance() {
        global $CFG;
        $data = $this->unserialize_data(array());
        if (isset($data['report'])) {
            $report_shortname = $data['report'];
            $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);
        return $report_instance;

        } else {
            return null;
        }
    }

    public function get_steps() {
        return array(
            self::STEP_LABEL      => get_string('scheduling_labelstep', 'block_php_report'),
            self::STEP_SCHEDULE   => get_string('scheduling_schedulestep', 'block_php_report'),
            self::STEP_PARAMETERS => get_string('scheduling_parametersstep', 'block_php_report'),
            self::STEP_FORMAT     => get_string('scheduling_formatstep', 'block_php_report'),
            self::STEP_RECIPIENTS => get_string('scheduling_recipientsstep', 'block_php_report'),
            self::STEP_CONFIRM    => get_string('scheduling_confirmstep', 'block_php_report')
        );
    }

    public function get_last_completed_step() {
        $data = $this->unserialize_data(array());
        if (!isset($data['label'])) {
            return null;
        }
        if (!isset($data['recurrencetype'])) {
            return self::STEP_LABEL;
        }
        if (!isset($data['parameters'])) {
            return self::STEP_SCHEDULE;
        }
        if (!isset($data['format'])) {
            return self::STEP_PARAMETERS;
        }
        if (!isset($data['recipients'])) {
            return self::STEP_FORMAT;
        }
        return self::STEP_RECIPIENTS;
    }

    public function save_values_for_step_label($values) {
        if (empty($values->label)) {
            return array('label' => get_string('required'));
        }
        $data = $this->unserialize_data(array());
        if (!isset($this->id)) {
            // only set the report name the first time through
            $data['report'] = $values->report;
        }
        $data['label'] = $values->label;
        if (isset($values->description)) {
            $data['description'] = $values->description;
        }
        $this->data = serialize($data);
        $this->save();
    }

    public function save_values_for_step_schedule($values) {
        $data = $this->unserialize_data(array());
        $data['timezone'] = isset($values->timezone) ? $values->timezone : 99;
        // NOTE: CANNOT test $values->timezone using empty, as 0 is a valid timezone!
        $data['startdate'] = empty($values->startdate) ? null : $values->startdate;
        $data['recurrencetype'] = !empty($values->recurrencetype) && $values->recurrencetype == self::RECURRENCE_CALENDAR ? self::RECURRENCE_CALENDAR : self::RECURRENCE_SIMPLE;

        // common scheduling parameters
        $schedule = array();

        if ($data['recurrencetype'] == self::RECURRENCE_SIMPLE) {
            $schedule['runsremaining'] = empty($values->runsremaining) ? null : $values->runsremaining;
            $schedule['frequency'] = empty($values->frequency) ? 1 : $values->frequency;
            $schedule['frequencytype'] = empty($values->frequencytype) ? self::FREQ_DAY : $values->frequencytype;
            $schedule['enddate'] = empty($values->enddate) ? null : $values->enddate;

        } else {
           /* ***
            ob_start();
            var_dump($values);
            $tmp = ob_get_contents();
            ob_end_clean();
            debug_error_log("save_values_for_step_schedule(values) => {$tmp}");
           *** */
            $errors = array();
            if (empty($values->dayofweek)) {
                $errors['dayofweek'] = get_string('required');
            }
            if (empty($values->day)) {
                $errors['day'] = get_string('required');
            }
            if ($values->caldaystype == '2') {
                $regex = "/^(\\d|[12]\\d|30|31)(,(\\d|[12]\\d|30|31))*$/";
                //$regex = "/^\d+(,\d+)*$/";
                if (!preg_match($regex,$values->monthdays)) {
                    $errors['daytype'] = get_string('validmonthdays','block_php_report');
                }
            }
            if (empty($values->month) && empty($values->allmonths)) {
                $errors['month'] = get_string('required');
            }
            if (!empty($errors)) {
                return $errors;
            }

            //debug_error_log("/blocks/php_report/lib/schedulelib.php::save_values_for_step_schedule(): hour={$values->hour}  min={$values->minute} timezone={$values->timezone}");
            $schedule['hour'] = empty($values->hour) ? 0 : $values->hour;
            $schedule['minute'] = empty($values->minute) ? 0 : $values->minute;
            $schedule['dayofweek'] = $values->dayofweek;
            $schedule['day'] = $values->day;
            if (!empty($values->allmonths) || $values->month == '1,2,3,4,5,6,7,8,9,10,11,12') {
                $schedule['month'] = '*';
            } else {
                $schedule['month'] = $values->month;
            }
            $schedule['enddate'] = empty($values->calenddate) ? null : $values->calenddate;
        }
        $data['schedule'] = $schedule;
        $this->data = serialize($data);
        $this->save();
    }

    public function save_values_for_step_parameters($values) {
        // validate the parameters using the form
        $report = $this->get_report_instance();
        $report->require_dependencies();
        $report->init_filter($report->id);
        $filters = $report->get_filters();
        //Check for report filter
        if (isset($report->filter)) {
            $report_filter = $report->filter;
        } else {
            $report_filter = null;
        }
        $form = new scheduling_form_step_parameters(null, array('workflow' => $this, 'filterobject' => $report_filter));

        $form->set_data($values);
        $form->definition_after_data();
        $form->validate();
        $errors = $form->validation($values, array());
        $form_errors = $form->get_errors();
        if ((is_array($errors) && !empty($errors)) || !empty($form_errors)) {
            if (!is_array($errors)) {
                $errors = array();
            }
            $errors = $errors + $form_errors;
            return $errors;
        }

        // data is validated, so we can save it
        $data = $this->unserialize_data(array());
        $data['parameters'] = $values;
        $this->data = serialize($data);
        $this->save();
    }

    public function save_values_for_step_format($values) {
        if (empty($values->format)) {
            return array('format' => get_string('required'));
        }
        $data = $this->unserialize_data(array());
        $data['format'] = $values->format;
        $this->data = serialize($data);
        $this->save();
    }

    public function save_values_for_step_recipients($values) {
        if (empty($values->recipients)) {
            return array('recipients' => get_string('required'));
        }
        $data = $this->unserialize_data(array());
        $data['recipients'] = $values->recipients;
        $recipients = explode(',', $values->recipients);
        foreach ($recipients as $recipient) {
            if (!validate_email(trim($recipient))) {
                $errors['recipients'] = get_string('validemails','block_php_report');
                return $errors;
            }
        }
        if (isset($values->message)) {
            $data['message'] = $values->message;
        }
        $this->data = serialize($data);
        $this->save();
    }

    public function finish() {
        global $USER, $DB;

        $data = $this->unserialize_data(array());
        if (isset($data['schedule_user_id'])) {
            //userid was specifically persisted from the schedule record
            $userid = $data['schedule_user_id'];
        } else {
            //default to the current user
            $userid = $USER->id;
        }

        // Add timemodified to serialized data
        $data['timemodified'] = time();
        $serialized_data = serialize($data);

        // Save to php_report_schedule - id (auto), userid (Moodle userid), report (shortname), config($data plus time() <= currenttime)
        $schedule = new object();
        $schedule->userid   = $userid;
        $schedule->report   = $data['report'];
        $schedule->config   = $serialized_data;
        if (isset($data['schedule_id'])) {
            $schedule->id = $data['schedule_id'];
            $DB->update_record('php_report_schedule', $schedule);
            // Also find and remove any existing task record(s) for the old schedule
            $taskname     = 'scheduled_'.$schedule->id;
            $DB->delete_records('elis_scheduled_tasks', array('taskname' => $taskname));
        } else {
            $schedule->id = $DB->insert_record('php_report_schedule', $schedule);
        }

        // Save to scheduled_tasks
        $component = 'block/php_report';
        $callfile = '/blocks/php_report/runschedule.php';
        $callfunction = serialize('run_schedule');

        // Calculate nextruntime from schedule
        // Also calculate minute/hour/day/month/dayofweek
        $recurrencetype = $data['recurrencetype'];

        // Simple - hour/minute are from time modified
        if ($recurrencetype == scheduling_workflow::RECURRENCE_SIMPLE) {
            $minute    = (int) strftime('%M',$data['timemodified']);
            $hour      = (int) strftime('%H',$data['timemodified']);
            $day       = '*';
            $month     = '*';
            $dayofweek = '*';
        } else {
        // Calendar
            $minute    = $data['schedule']['minute'];
            $hour      = $data['schedule']['hour'];
            $day       = $data['schedule']['day'];
            $month     = $data['schedule']['month'];
            $dayofweek = $data['schedule']['dayofweek'];
        }

        $runsremaining = empty($data['schedule']['runsremaining']) ? null : $data['schedule']['runsremaining'];
        // thou startdate is checked in runschedule.php, the confirm form
        // would display an incorrect 'will run next at' time (eg. current time)
        // if we don't calculate it - see below ...
        $startdate     = empty($data['startdate']) ? $data['timemodified'] : $data['startdate'];
        $enddate       = empty($data['schedule']['enddate']) ? null : $data['schedule']['enddate'];

        $task = new object();
        $task->plugin        = $component;
        $task->taskname      = 'scheduled_'.$schedule->id;
        $task->callfile      = $callfile;
        $task->callfunction  = $callfunction;
        $task->lastruntime   = 0;
        $task->blocking      = 0;
        $task->minute        = $minute;
        $task->hour          = $hour;
        $task->day           = $day;
        $task->month         = $month;
        $task->dayofweek     = $dayofweek;
        $task->timezone      = $data['timezone'];
        $task->enddate       = ($enddate != null) ? ($enddate + DAYSECS - 1): null;
        debug_error_log("schedulelib.php::finish() startdate = {$data['startdate']}; timemodified = {$data['timemodified']}");
        // NOTE: if startdate not set then it already got set to time()
        //       in get_submitted_values_for_step_schedule()
        //       in which case we DO NOT want to add current time of day!
        //       hence the messy check for: !(from_gmt() % DAYSECS)
        if (!empty($data['startdate']) &&
            ($recurrencetype == scheduling_workflow::RECURRENCE_SIMPLE ||
             $startdate < $data['timemodified']) &&
            !(($orig_start = from_gmt($data['startdate'], $data['timezone'])) % DAYSECS)) {
            // they set a startdate, but, we should add current time of day!
            $time_offset = from_gmt($data['timemodified'], $data['timezone']);
            debug_error_log("schedulelib.php::finish() : time_offset = {$time_offset} - adjusting startdate = {$startdate} ({$orig_start}) => " . to_gmt($orig_start + ($time_offset % DAYSECS), $data['timezone']));
            $startdate = to_gmt($orig_start + ($time_offset % DAYSECS), $data['timezone']);

           /**
            * TBD: if startdate earlier than today ???
            **/
           while ($startdate < $data['timemodified']) {
               $startdate += DAYSECS;
               debug_error_log("schedulelib.php::finish() advancing startdate + day => {$startdate}");
           }
        }

        if ($recurrencetype == scheduling_workflow::RECURRENCE_SIMPLE) {
            $task->nextruntime = $startdate;
        } else {
            $task->nextruntime = cron_next_run_time($startdate - 100, (array)$task);
            // minus [arb. value] above from startdate required to not skip
            // first run! This was probably due to incorrect startdate calc
            // which is now corrected.
        }
        $task->runsremaining = $runsremaining;

        $DB->insert_record('elis_scheduled_tasks', $task);
    }
}

class scheduling_page extends workflowpage {
    var $data_class = 'scheduling_workflow';

    var $cancel_url = '/blocks/php_report/schedule.php?action=list';

    public function get_page_title_default() {
        global $DB;

        $schedule_id = optional_param('id',null,PARAM_INT);

        // If a schedule id was in the url, then attempt to retrieve it from the php_scheduled_tasks table
        if ($schedule_id != null) {
            $schedule = $DB->get_record('php_report_schedule', array('id' => $schedule_id));
            if (empty($schedule)) {
                // TBD: better error handling
                return '';
            }
            $this->workflow->data = $schedule->config;
            $workflowdata = $this->workflow->unserialize_data(array());
            $workflowdata['schedule_id'] = $schedule_id;
            $workflowdata['schedule_user_id'] = $schedule->userid;
            $this->workflow->data = serialize($workflowdata);
            $this->workflow->save();
        }

        $report = $this->workflow->get_report_instance();
        if (!isset($report->id)) {
            // On label page - no workflow yet!
            $report_id = required_param('report', PARAM_ALPHAEXT);
        } else {
            $report_id = $report->id;
        }

        // Get rest of title
        if (isset($workflowdata['label'])) {
            $labelname = $workflowdata['label'];
        } else {
            //unserialize workflow data to get label
            $workflowdata = $this->workflow->unserialize_data(array());
            if (isset($workflowdata['label'])) {
                $labelname = $workflowdata['label'];
            } else {
                $labelname = '';
            }
        }
        $report_name = get_string('displayname', 'rlreport_'.$report_id);
        return get_string('pagetitle', 'block_php_report',$report_name).' ('.$labelname.')';
    }

    /**
     * Specifies the title used when listing all available reports
     * for scheduling reasons
     *
     * @return  string  The title to display
     */
    function get_page_title_list() {
        return get_string('list_pagetitle', 'block_php_report');
    }

    /**
     * Specifies the title used when listing all jobs for a particular
     * report
     *
     * @return  string  The title to display
     */
    function get_page_title_listinstancejobs() {
        return get_string('listinstancejobs_pagetitle', 'block_php_report');
    }

    /**
     * Specifies the title used when listing all jobs for a particular
     * report and running jobs for selected instances
     *
     * @return  string  The title to display
     */
    function get_page_title_runjobs() {
        return $this->get_page_title_listinstancejobs();
    }

    /**
     * Specifies the title used when listing all jobs for a particular
     * report and copying jobs from selected instances
     *
     * @return  string  The title to display
     */
    function get_page_title_copyjobs() {
        return $this->get_page_title_listinstancejobs();
    }

    /**
     * Specifies the title used when listing all jobs for a particular
     * report
     *
     * @return  string  The title to display
     */
    function get_page_title_deletejobs() {
        return $this->get_page_title_listinstancejobs();
    }

    /**
     * Specifies default navigation links for page
     *
     * @return  array navigation links for build_navigation()
     */
    function build_navbar_default() {
        parent::build_navbar_default();
        $this->navbar->add(get_string('listinstancejobs_pagetitle', 'block_php_report'), null);
    }

    /**
     * Specifies whether the current user may list jobs for a particular report
     *
     * @return  boolean  true if allowed, otherwise false
     */
    function can_do_listinstancejobs() {
        if (has_capability('block/php_report:manageschedules', get_context_instance(CONTEXT_SYSTEM))) {
            //user can manage schedules globally, so allow access
            return true;
        }

        //obtain the report shortname and instance
        $report_shortname = $this->required_param('report', PARAM_ALPHAEXT);
        $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);

        //false is returned in the case of permissions failure
        return $report_instance !== false;
    }

    /**
     * Helper method that specifies whether an edit action may be taken on the current
     * scheduled report instance
     *
     * @return  boolean  true if allowed, otherwise false
     *
     * @global  $USER
     * @global  $DB
     */
    function can_do_edit() {
        global $USER, $DB;

        if (has_capability('block/php_report:manageschedules', get_context_instance(CONTEXT_SYSTEM))) {
            //user can manage schedules globally, so allow access
            return true;
        }

        $report_shortname = '';

        //try to obtain the report shortname from the report schedule id
        //(applies only during first step of wizard interface)
        $id = $this->optional_param('id', 0, PARAM_INT);
        if ($id !== 0) {
            if ($record = $DB->get_record('php_report_schedule', array('id' => $id))) {
                if ($record->userid != $USER->id) {
                    //disallow access to another user's schedule
                    return false;
                }
                $config = unserialize($record->config);
                if (isset($config['report'])) {
                    $report_shortname = $config['report'];
                }
            } else {
                //wrong id, so disallow
                return false;
            }
        }

        //try to obtain the report shortname from the workflow information
        //(applies only after the first step of the wizard interface)
        if ($report_shortname == '' && isset($this->workflow)) {
            $data = $this->workflow->unserialize_data();
            if ($data !== NULL && isset($data['report'])) {
                $report_shortname = $data['report'];
            }
        }

        if ($report_shortname === '') {
            //report info not found, so disallow
            return false;
        }

        //check permissions via the report
        $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);
        return $report_instance !== false;
    }

    /**
     * Helper function that specifies whether an add action may be taken on the current
     * scheduled report instance
     *
     * @return  boolean  true if allowed, otherwise false
     */
    function can_do_add() {
        //try to obtain the report shortname directly from the url parameter
        //(applies only during the first step of the wizard interface)
        $report_shortname = $this->optional_param('report', '', PARAM_ALPHAEXT);

        //try to obtain the report shortname from the workflow information
        //(applies only after the first step of the wizard interface)
        if ($report_shortname == '' && isset($this->workflow)) {
            $data = $this->workflow->unserialize_data();
            if ($data !== NULL && isset($data['report'])) {
                $report_shortname = $data['report'];
            }
        }

        if ($report_shortname === '') {
            //report info not found, so disallow
            return false;
        }

        //check permissions via the report
        $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);
        return $report_instance !== false;
    }

    /**
     * Specifies whether the current user may schedule instances of the report
     * specified via URL parameter
     *
     * @return  boolean  true if allowed, otherwise false
     */
    public function can_do_default() {
        $id = $this->optional_param('id', 0, PARAM_INT);

        $schedule_id = 0;

        //try to obtain the workflow's report schedule id
        if (isset($this->workflow)) {
            $data = $this->workflow->unserialize_data();
            if ($data !== NULL && isset($data['schedule_id'])) {
                $schedule_id = (int)$data['schedule_id'];
            }
        }

        if ($id !== 0 || $schedule_id !== 0) {
            return $this->can_do_edit();
        } else {
            return $this->can_do_add();
        }
    }

    /**
     * Specifies whether the current user may view the report list for scheduling
     *
     * @return  boolean  true if allowed, otherwise false
     */
    public function can_do_list() {
        global $CFG, $USER;

        if (has_capability('block/php_report:manageschedules', get_context_instance(CONTEXT_SYSTEM))) {
            //user can manage schedules globally, so allow access
            return true;
        }

        //determine if the export action is available in the context of scheduling
        $export_available = false;

        //go through the directories
        if (file_exists($CFG->dirroot . '/blocks/php_report/instances') &&
            $handle = opendir($CFG->dirroot . '/blocks/php_report/instances')) {

            while (false !== ($report_shortname = readdir($handle))) {
                //get the report instance (this inherently checks permissions and report availability)
                if($instance = php_report::get_default_instance($report_shortname, $USER->id, php_report::EXECUTION_MODE_SCHEDULED)) {

                    //check permissions and make sure scheduling is not explicitly disallowed
                    if (!$instance->can_view_report()) {
                        continue;
                    }

                    //make sure there is at least one available export format
                    $export_formats = $instance->get_export_formats();
                    if (count($export_formats) == 0) {
                        continue;
                    }

                    $export_available = true;
                    break;
                }
            }
        }

        return $export_available;
    }

    /**
     * Permissions checking for performing most actions on a specific schedule instance,
     * including running, copying, and deleting
     *
     * @param   int      $scheduleid        id of the PHP report schedule record
     * @param   string   $report_shortname  shortname of report, or empty string to signal use
     *                                      of URL parameters
     *
     * @return  boolean                     true if allowed, otherwise false
     *
     * @uses    $USER
     * @uses    $DB
     */
    function can_do_schedule_action($scheduleid, $report_shortname = '') {
        global $USER, $DB;

        if (has_capability('block/php_report:manageschedules', get_context_instance(CONTEXT_SYSTEM))) {
            //permitted, since allowed globally
            return true;
        }

        //flag for allowing the current schedule instance
        //check for report / schedule-specific permissions

        //make sure the schedule is owned by the current user
        if ($userid = $DB->get_field('php_report_schedule', 'userid', array('id' => $scheduleid))) {
            if ($userid == $USER->id) {

                if ($report_shortname == '') {
                    //get report shortname from URL param, if needed
                    $report_shortname = required_param('report', PARAM_ALPHAEXT);
                }
                //make sure the report is accessable from a scheduling context
                $report_instance = php_report::get_default_instance($report_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED);
                if ($report_instance != false) {
                    //permissions check out, so allow
                    return true;
                }
            }
        }

        //permissions problem, so disallow
        return false;
    }

    /**
     * List the available reports to schedule
     */
    public function display_list() {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/php_report/sharedlib.php');

        $category_members = block_php_report_get_names_by_category(true, NULL, php_report::EXECUTION_MODE_SCHEDULED);

        //set up the basics of our table
        $table = new html_table();
        $table->head = array(get_string('list_report_name_header', 'block_php_report'),
                             get_string('list_schedule_header', 'block_php_report'));
        $table->align = array('left',
                              'left');
        $table->rowclasses = array();
        $table->data = array();

        $categories = block_php_report_get_category_mapping();

        //go through categories and append items
        foreach ($categories as $category_key => $category_display) {
            if (!empty($category_members[$category_key])) {

                //set up a row for the category header
                $table->data[] = array($category_display, '');
                //cass class for specially styling the category header row
                $table->rowclasses[] = 'php_report_scheduling_list_category';

                //go through and add all report entries below the category header
                foreach ($category_members[$category_key] as $member_shortname => $member_display) {
                    //set up a link for executing the report
                    $execute_report_url = $CFG->wwwroot . '/blocks/php_report/render_report_page.php?report=' . $member_shortname;

                    $execute_report_link = php_report::get_default_instance($member_shortname)
                        ? '<a href="' . $execute_report_url . '">' . $member_display . '</a>'
                        : $member_display;

                    //set up a link for scheduling the report
                    $execute_report_url_params = array('action' => 'listinstancejobs',
                                                       'report' => $member_shortname);
                    $execute_report_page = $this->get_new_page($execute_report_url_params);
                    $execute_report_url = $execute_report_page->url;
                    $schedule_report_link = '';
                    if (php_report::get_default_instance($member_shortname, NULL, php_report::EXECUTION_MODE_SCHEDULED)) {
                        $schedule_report_link = '<a href="' . $execute_report_url . '">
                                             <img src="' . $CFG->wwwroot . '/blocks/php_report/pix/schedule.png"/>
                                             </a>';
                    }

                    //append info to the row
                    $table->data[] = array($execute_report_link, $schedule_report_link);
                    $table->rowclasses[] = '';
                }
            }
        }

        if (count($table->data > 0)) {
            echo html_writer::table($table);
        } else {
            //error case
        }
    }

    /**
     * Mainline for running jobs from the "listinstancejobs" view
     * without changing any scheduling information
     *
     * @global  $CFG, $PAGE
     */
    function display_runjobs() {
        global $CFG, $PAGE;

        //need the code that defines the scheduled export behaviour
        require_once($CFG->dirroot . '/blocks/php_report/runschedule.php');

        $scheduleids = array();
        if ($data = data_submitted()) {
            foreach ($data as $key => $value) {
                if (strpos($key, 'schedule_') === 0) {
                    $scheduleid = (int)substr($key, strlen('schedule_'));
                    if ($this->can_do_schedule_action($scheduleid)) {
                        $scheduleids[] = $scheduleid;
                    }
                }
            }
        }

        //re-display the list of scheduled jobs for this report
        $this->display_listinstancejobs();

        if (count($scheduleids) > 0) {
            //include the necessary javascript libraries for the ASYNC request stuff
            $PAGE->requires->yui2_lib(array('yahoo', 'event'));

            //one or more schedules selected, so open the popup to run them
            $js_params = array('url' => '/blocks/php_report/lib/run_schedule_popup.php?scheduleids='.urlencode(json_encode($scheduleids)),
                               'name' => 'runjobsnow',
                               'options' => "\"menubar=0,location=0,scrollbars,status,resizable,width=400,height=500\"");
            $params = array(null, $js_params);
            $PAGE->requires->js_function_call('openpopup', $params, true);
        }
    }

    /**
     * Mainline for deleting jobs from the "listinstancejobs" view
     * (i.e. view of all scheduled jobs for a particular report)
     */
    function do_deletejobs() {
        if ($data = data_submitted()) {
            foreach ($data as $key => $value) {
                if (strpos($key, 'schedule_') === 0) {
                    $scheduleid = (int)substr($key, strlen('schedule_'));
                    if ($this->can_do_schedule_action($scheduleid)) {
                        block_php_report_delete_schedule_instance($scheduleid);
                    }
                }
            }
        }

        //re-display the list of scheduled jobs for this report
        $report = $this->required_param('report', PARAM_ALPHAEXT);
        $tmppage = new scheduling_page(array('report' => $report, 'action' => 'listinstancejobs'));
        redirect($tmppage->url);
    }

    /**
     * Mainline for copying jobs from the "listinstancejobs" view
     * (i.e. view of all scheduled jobs for a particular report)
     */
    function do_copyjobs() {
        if ($data = data_submitted()) {
            foreach ($data as $key => $value) {
                if (strpos($key, 'schedule_') === 0) {
                    $scheduleid = (int)substr($key, strlen('schedule_'));
                    if ($this->can_do_schedule_action($scheduleid)) {
                        block_php_report_copy_schedule_instance($scheduleid);
                    }
                }
            }
        }

        //re-display the list of scheduled jobs for this report
        $report = $this->required_param('report', PARAM_ALPHAEXT);
        $tmppage = new scheduling_page(array('report' => $report, 'action' => 'listinstancejobs'));
        redirect($tmppage->url);
    }

    /**
     * Renders a header entry for when viewing the scheduled instances of
     * a particular report
     *
     * @param  $have_jobs  true if the current report has at leeast one
     *                     scheduled job instance, otherwise false
     *
     * @uses   $OUTPUT
     */
    function render_listinstancejobs_header($have_jobs, $userid = NULL, $execmode = php_report::EXECUTION_MODE_INTERACTIVE) {
        global $OUTPUT;

        //get the current report shortname
        $report = $this->required_param('report', PARAM_ALPHAEXT);

        //get the current report's display name
        $instance = php_report::get_default_instance($report, $userid, $execmode);
        $display_name = $instance->get_display_name();

        //determine the appropriate display strings
        $report_text = '';
        $instructions_text = '';
        if ($have_jobs) {
            $report_text = get_string('listinstancejobs_heading_report', 'block_php_report', $display_name);
            $instructions_text = get_string('listinstancejobs_heading_instructions', 'block_php_report');
        } else {
            $report_text = get_string('listinstancejobs_heading_report_nojobs', 'block_php_report', $display_name);
            $instructions_text = get_string('listinstancejobs_heading_instructions_nojobs', 'block_php_report');
        }

        //display the appropriate display strings
        notify($report_text,       'php_report_bold_header',   'left');
        notify($instructions_text, 'php_report_italic_header', 'left');
        echo $OUTPUT->spacer();
    }

    /**
     * Renders an actuion dropdown for when viewing the scheduled instances of
     * a particular report
     *
     * @param  $have_jobs  true if the current report has at leeast one
     *                     scheduled job instance, otherwise false
     */
    function render_listinstancejobs_actions_dropdown($have_jobs) {
        //disable the UI elements if and only if there are no jobs
        $disabled = !$have_jobs;

        //label
        echo get_string('listinstancejobs_dropdown_label', 'block_php_report');

        //available actions
        $action_options = array('runjobs'    => get_string('listinstancejobs_action_runjobs',    'block_php_report'),
                                'copyjobs'   => get_string('listinstancejobs_action_copyjobs',   'block_php_report'),
                                'deletejobs' => get_string('listinstancejobs_action_deletejobs', 'block_php_report'));
        //render the dropdown, disabled if necessary
        echo html_writer::select($action_options, 'action', '', false, array('disabled' => $disabled));

        //render the submit button in the appropriate state
        if ($disabled) {
            $disabled_attribute = ' disabled="disabled"';
        } else {
            $disabled_attribute = '';
        }
        echo '<input type="submit" value="' . get_string('submit') . '" ' . $disabled_attribute . '/>';
    }

    /**
     * Renders a footer entry for when viewing the scheduled instances of
     * a particular report
     *
     * @param  $have_jobs  true if the current report has at leeast one
     *                     scheduled job instance, otherwise false
     *
     * @uses   $OUTPUT
     */
    function render_listinstancejobs_footer() {
        global $OUTPUT;

        //get the current report shortname
        $report = $this->required_param('report', PARAM_ALPHAEXT);

        echo $OUTPUT->spacer();

        //button for scheduling a new instance
        $url = new moodle_url($this->url, array('action' => 'default', 'report' => $report));
        echo $OUTPUT->single_button($url, get_string('listinstancejobs_new', 'block_php_report'));

        echo '<hr>';
        echo $OUTPUT->spacer();

        //button for listing all reports
        $url = new moodle_url($this->url, array('action' => 'list'));
        echo $OUTPUT->single_button($url, get_string('listinstancejobs_back_label', 'block_php_report'));
    }

    /**
     * Mainline for showing all jobs currently set up for an existing
     * report
     *
     * @uses $CFG
     * @uses $USER
     * @uses $OUTPUT
     * @uses $PAGE
     */
    function display_listinstancejobs() {
        global $CFG, $USER, $OUTPUT, $PAGE;

        //report specified by URL
        $report = $this->required_param('report', PARAM_ALPHAEXT);

        //get the necessary data
        $recordset = block_php_report_get_report_jobs_recordset($report);

        //set up a job if none exist and a special parameter
        //is passed in to signal this functinality
        $createifnone = optional_param('createifnone', 0, PARAM_INT);
        if ($createifnone) {
            if (!$recordset
                or !$recordset->valid()) {

                //set up a job for this report
                $this->display_default();
                return;
            }
        }

        if ($recordset = block_php_report_get_report_jobs_recordset($report) and
            $recordset->valid()) {
            //we actually have scheduled instances for this report

            //display appropriate headers
            $this->render_listinstancejobs_header(true, NULL, php_report::EXECUTION_MODE_SCHEDULED);

            //set up our form
            echo '<form action="' . $this->url . '" method="post">';
            //used by the "select all" functionality to identify a defining div
            echo '<div id="list_display">';
            echo '<input type="hidden" id="report" name="report" value="' . $report . '"/>';

            //table setup
            $table = new html_table();

            //headers, with a "select all" checkbox in the first column
            $PAGE->requires->js('/blocks/php_report/js/lib.js');

            //checkbox for selecting all
            $checkbox_label = get_string('listinstancejobs_header_select', 'block_php_report');
            $checkbox_attributes = array('onclick' => 'select_all()');
            $checkbox = html_writer::checkbox('selectall', '', false, $checkbox_label, $checkbox_attributes);

            $table->head = array($checkbox,
                                 get_string('listinstancejobs_header_label',        'block_php_report'),
                                 get_string('listinstancejobs_header_owner',        'block_php_report'),
                                 get_string('listinstancejobs_header_lastrun',      'block_php_report'),
                                 get_string('listinstancejobs_header_nextrun',      'block_php_report'),
                                 get_string('listinstancejobs_header_lastmodified', 'block_php_report'));

            //left align all columns
            $table->align = array();
            foreach ($table->head as $column_header) {
                $table->align[] = 'left';
            }

            $table->data = array();

            //run through available schedules
            foreach ($recordset as $record) {
                $config_data = unserialize($record->config);
                $tz = $config_data['timezone'];
                //echo "action_listinstancejobs():: {$config_data['label']}: nextruntime = {$record->nextruntime}<br/>";

                //convert the last run time to the appropraite format
                if ($record->lastruntime == 0) {
                    //special case: never run before
                    $lastruntime = get_string('no_last_runtime', 'block_php_report');
                } else {
                    $lastruntime = userdate($record->lastruntime, '', $tz)
                                   . ' (' . usertimezone($tz) .')';
                    debug_error_log("/blocks/php_report/lib/schedulelib.php::action_listinstancejobs(); {$config_data['label']}: record->lastruntime = {$record->lastruntime}, tz = {$tz}");
                }

                // convert 'will run next at' time to appropriate format
                $jobenddate = $config_data['schedule']['enddate'];
                debug_error_log("/blocks/php_report/lib/schedulelib.php::action_listinstancejobs(); {$config_data['label']}: nextruntime = {$record->nextruntime}, jobenddate = {$jobenddate}");
                if (!empty($record->nextruntime) &&
                    (empty($jobenddate)
                    || $record->nextruntime < ($jobenddate + DAYSECS))
                ) {
                    $nextruntime = userdate($record->nextruntime, '', $tz)
                                   . ' (' . usertimezone($tz) .')';
                } else {
                    $nextruntime = get_string('job_completed',
                                              'block_php_report');
                }
                $checkbox = '<input type="checkbox" name="schedule_' . $record->scheduleid . '">';

                //link for editing this particular schedule instance
                $edit_schedule_params = array('id' => $record->scheduleid, 'action' => 'default');
                $edit_schedule_page = $this->get_new_page($edit_schedule_params);
                $edit_schedule_link = '<a href="' . $edit_schedule_page->url . '">' . $config_data['label'] . '</a>';

                //data row
                $table->data[] = array($checkbox,
                                       $edit_schedule_link,
                                       fullname($record),
                                       $lastruntime,
                                       $nextruntime,
                                       userdate($config_data['timemodified']),);
            }

            echo html_writer::table($table);

            echo $OUTPUT->spacer();

            //display the dropdown and button in an enabled state
            $this->render_listinstancejobs_actions_dropdown(true);

            echo '</div>';
            echo '</form>';
        } else {
            //display header info
            $this->render_listinstancejobs_header(false, NULL, php_report::EXECUTION_MODE_SCHEDULED);

            //display the dropdown and button in a disabled state
            $this->render_listinstancejobs_actions_dropdown(false);

            echo $OUTPUT->spacer();
        }

        //general footer
        $this->render_listinstancejobs_footer();
    }

    /**
     * List the schedule for this report
     */
    public function print_summary() {
        // FIXME
    }

    public function display_step_label($errors) {
        $form = new scheduling_form_step_label(null, $this);
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->workflow->unserialize_data(array());
        $data = new stdClass;
        if (isset($workflowdata['label'])) {
            $data->label = $workflowdata['label'];
        }
        if (isset($workflowdata['description'])) {
            $data->description = $workflowdata['description'];
        }
        $form->set_data($data);
        $form->display();
    }

    public function get_submitted_values_for_step_label() {
        $form = new scheduling_form_step_label(null, $this);
        return $form->get_data(false);
    }

    public function display_step_schedule($errors) {
        $form = new scheduling_form_step_schedule(null, $this);
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->workflow->unserialize_data(array());
        $data = new stdClass;

        // Create appropriate values for forms
        $data->timezone = 99;
        if (isset($workflowdata['timezone'])) {
            $data->timezone = $workflowdata['timezone'];
        }
        if (isset($workflowdata['startdate']) &&
            ($workflowdata['startdate'] > time() ||
            !(from_gmt($workflowdata['startdate'], $data->timezone) % DAYSECS))) {
            $data->starttype = 1;
            $data->startdate = $workflowdata['startdate'];
            $data->startdate = from_gmt($data->startdate, $data->timezone);
            debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting startdate from {$workflowdata['startdate']} to {$data->startdate}, {$data->timezone}");
        } else {
            $data->starttype = 0;
        }
        if (isset($workflowdata['recurrencetype'])) {
            $data->recurrencetype = $workflowdata['recurrencetype'];
        }

        // common scheduling parameters
        if (isset($workflowdata['schedule']['runsremaining'])) {
            $data->runsremaining = $workflowdata['schedule']['runsremaining'];
        }
        if (isset($workflowdata['schedule']['frequency'])) {
            $data->frequency = $workflowdata['schedule']['frequency'];
        }
        if (isset($workflowdata['schedule']['frequencytype'])) {
            $data->frequencytype = $workflowdata['schedule']['frequencytype'];
        }
        $hour = isset($workflowdata['schedule']['hour'])
                ? $workflowdata['schedule']['hour'] : 0;
        $min = isset($workflowdata['schedule']['minute'])
               ? $workflowdata['schedule']['minute'] : 0;
        $data->time['hour'] = $hour;
        $data->time['minute'] = $min;

        if (isset($data->recurrencetype)) {
            if ($data->recurrencetype == scheduling_workflow::RECURRENCE_SIMPLE) {
                // Set up runtype, frequencytype, enddate(day, month, year) <= timezone conversion req'd
                if ($workflowdata['schedule']['runsremaining'] == null) {
                    if (!isset($workflowdata['schedule']['enddate'])) {
                        $data->runtype = 0;
                    } else {
                        $data->runtype = 1;
                        $data->enddate = $workflowdata['schedule']['enddate'];
                        $data->enddate = from_gmt($data->enddate, $data->timezone);
                        debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting enddate from {$workflowdata['schedule']['enddate']} to {$data->enddate}, {$data->timezone}");
                    }
                } else {
                    $data->runtype = 2;
                }

            } else {
                // get time/day/month and convert to
                //calenddate, time, caldaystype - 0 (every day), 1(week days), 2(month days)
                //dayofweek 1-7, allmonths (1) and month array
                if (isset($workflowdata['schedule']['enddate'])) {
                    $data->calenddate = $workflowdata['schedule']['enddate'];
                    $data->calenddate = from_gmt($data->calenddate, $data->timezone);
                    debug_error_log("/lib/schedulelib.php::display_step_schedule() adjusting calenddate from {$workflowdata['schedule']['enddate']} to {$data->calenddate}, {$data->timezone}");
                }
                if ($workflowdata['schedule']['dayofweek'] == '*') {
                     if ($workflowdata['schedule']['day'] == '*') {
                        $data->caldaystype = 0;
                     } else {
                        $data->caldaystype = 2;
                        // get the month days
                        $data->monthdays = $workflowdata['schedule']['day'];
                    }
                } else {
                    $data->caldaystype = 1;
                    // get all the days of the week
                    $daysofweek = explode(',', $workflowdata['schedule']['dayofweek']);
                    foreach ($daysofweek as $day) {
                        if ((int) $day) {
                            $data->dayofweek[(int) $day] = 1;
                        }
                    }
                }

                if ($workflowdata['schedule']['month'] == '1,2,3,4,5,6,7,8,9,10,11,12' || $workflowdata['schedule']['month'] == '*') {
                    $data->allmonths = 1;
                    //$workflowdata['schedule']['month'] = '1,2,3,4,5,6,7,8,9,10,11,12';
                } else {
                    $months = explode(',', $workflowdata['schedule']['month']);
                    foreach ($months as $month) {
                        if ((int) $month) {
                            $data->month[(int) $month] = 1;
                        }
                    }
                }

            }
        }
        $form->set_data($data);
        $form->display();
    }

    public function get_submitted_values_for_step_schedule() {
        $form = new scheduling_form_step_schedule(null, $this);
        $data = $form->get_data(false);
        // set the startdate to today if set to start now
        if (!isset($data->starttype) || $data->starttype == 0) {
            $data->startdate = time();
        } else {
            $data->startdate = to_gmt($data->startdate, $data->timezone);
        }
        debug_error_log("get_submitted_values_for_step_schedule(): startdate  = {$data->startdate}");

        // Process simple calendar workflow
        if ($data->recurrencetype != scheduling_workflow::RECURRENCE_CALENDAR) {
            if (!isset($data->runtype) || $data->runtype == 0) {
                $data->enddate = null;
                $data->runsremaining = null;
            } elseif ($data->runtype == 1) {
                $data->runsremaining = null;
                debug_error_log("get_submitted_values_for_step_schedule(): adjusting enddate from $data->enddate to " . to_gmt($data->enddate, $data->timezone));
                $data->enddate = to_gmt($data->enddate, $data->timezone);
            } else {
                $data->enddate = null;
            }
        } else {
            if ($data->calenddate) {
                debug_error_log("get_submitted_values_for_step_schedule(): adjusting calenddate from $data->calenddate to " . to_gmt($data->calenddate, $data->timezone));
                $data->calenddate = to_gmt($data->calenddate, $data->timezone);
                $data->enddate = $data->calenddate;
            } else {
                $data->enddate = null;
            }
            $data->time = isset($data->time) ? $data->time : 0;
            //debug_error_log("get_submitted_values_for_step_schedule(): data->time = {$data->time}");
            $data->hour = floor($data->time / HOURSECS) % 24;
            $data->minute = floor($data->time / MINSECS) % MINSECS;
            if (!isset($data->caldaystype) || $data->caldaystype == 0) {
                $data->dayofweek = '*';
                $data->day = '*';
            } elseif ($data->caldaystype == 1) {
                $dayofweek = empty($data->dayofweek) ? array() : $data->dayofweek;
                $data->dayofweek = array();
                foreach ($dayofweek as $day => $dummy) {
                    $data->dayofweek[] = $day;
                }
                $data->dayofweek = implode(',', $data->dayofweek);
                $data->day = '*';
            } elseif ($data->caldaystype == 2) {
                $data->dayofweek = '*';
                $days = explode(',',$data->monthdays);
                $data->day = array();
                foreach ($days as $day) {
                    if ((int) $day) {
                        $data->day[] = (int) $day;
                    }
                }
                $data->day = implode(',', $data->day);
            }

            $months = empty($data->month) ? array() : $data->month;
            $data->month = array();
            foreach ($months as $month => $dummy) {
                $data->month[] = $month;
            }
            $data->month = implode(',', $data->month);
        }
        return $data;
    }

    public function display_step_parameters($errors) {
        global $CFG;

        //needed for execution mode constants
        require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

        // get the parameters from the report
        $report = $this->workflow->get_report_instance();
        $report->require_dependencies();
        $report->init_filter($report->id);

        //Check for report filter
        if (isset($report->filter)) {
            $report_filter = $report->filter;
            //tell the filters that we're in scheduling mode
            $report_filter->set_execution_mode(php_report::EXECUTION_MODE_SCHEDULED);
        } else {
            $report_filter = null;
        }
        $form = new scheduling_form_step_parameters(null, array('page' => $this, 'filterobject' => $report_filter));
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->workflow->unserialize_data(array());
        // First look in workflowdata
        if (isset($workflowdata['parameters'])) {
            $data = $workflowdata['parameters'];
            $form->set_data($data);
        } else {
        // Next try to find parameters in the session/database
        // Also sets the data onto the form
            php_report_filtering_update_form($report->id, $form,false);
        }

        //needed for Javascipt references
        echo '<div id="php_report_body_' . $report->get_report_shortname() . '">';
        $form->display();
        echo '</div>';
    }

    public function get_submitted_values_for_step_parameters() {
        // get the parameters from the report
        $report = $this->workflow->get_report_instance();
        $report->require_dependencies();
        $report->init_filter($report->id);
        $filters = $report->get_filters();
        //Check for report filter
        if (isset($report->filter)) {
            $report_filter = $report->filter;
        } else {
            $report_filter = null;
        }
        $form = new scheduling_form_step_parameters(null, array('page' => $this, 'filterobject' => $report_filter));

        $data = $form->get_data(false);
        // get rid of irrelevant data
        unset($data->MAX_FILE_SIZE);
        unset($data->_wfid);
        unset($data->_step);
        unset($data->action);
        unset($data->_next_step);

        if (isset($report_filter) && isset($data)) {
            // Remove any false parameters
            $parameters = $data;

            // Use this object to find our parameter settings
            $filter_object = $report_filter;
            $filter_object_fields = $filter_object->_fields;
            // Merge in any secondary filterings here...
            if (isset($filter_object->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $secondary_filtering) {
                    $filter_object_fields = array_merge($filter_object_fields, $secondary_filtering->_fields);
                }
            }

            if (!empty($filter_object_fields)) {
                $fields = $filter_object_fields;
                foreach($filter_object_fields as $fname=>$field) {
                    $sub_data = $field->check_data($parameters);
                    if ($sub_data === false) {
                        // unset any filter that comes back from check_data as false
                        unset($data->$fname);
                    }
                }
            }
        }
        return $data;
    }

    public function display_step_format($errors) {
        $form = new scheduling_form_step_format(null, $this);
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->workflow->unserialize_data(array());
        $data = new stdClass;
        if (isset($workflowdata['format'])) {
            $data->format = $workflowdata['format'];
        }
        //FIXME: multi select parameters are being lost right now
        $form->set_data($data);
        $form->display();
    }

    public function get_submitted_values_for_step_format() {
        $form = new scheduling_form_step_format(null, $this);
        return $form->get_data(false);
    }

    public function display_step_recipients($errors) {
        $form = new scheduling_form_step_recipients(null, $this);
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $workflowdata = $this->workflow->unserialize_data(array());

        $data = new stdClass;
        if (isset($workflowdata['recipients'])) {
            $data->recipients = $workflowdata['recipients'];
        }
        if (isset($workflowdata['message'])) {
            $data->message = $workflowdata['message'];
        }
        $form->set_data($data);
        $form->display();
    }

    public function get_submitted_values_for_step_recipients() {
        $form = new scheduling_form_step_recipients(null, $this);
        return $form->get_data(false);
    }

    public function display_step_confirm($errors) {
        $report = $this->workflow->get_report_instance();
        $report->require_dependencies();
        $report->init_filter($report->id);
        $filters = $report->get_filters();
        //Check for report filter
        if (isset($report->filter)) {
            $report_filter = $report->filter;
        } else {
            $report_filter = null;
        }
        $form = new scheduling_form_step_confirm(null, array('page' => $this,
                                                             'filterobject' => $report_filter));
        if ($errors) {
            foreach ($errors as $element=>$msg) {
                $form->setElementError($element, $msg);
            }
        }
        $form->display();
    }

    public function display_finished() {
        global $CFG;

        // Get report parameter data
        $data = $this->workflow->unserialize_data(array());

        // Get report name
        $report = $this->workflow->get_report_instance();

        echo get_string('successful_schedule', 'block_php_report', $report->get_display_name());

        $url = $CFG->wwwroot.'/blocks/php_report/schedule.php?report='.$data['report'].'&action=listinstancejobs';
        echo get_string('return_to_schedules', 'block_php_report', $url);
        // Close window if window.opener was used to open window
        ?>
<script type="text/javascript">
//<![CDATA[
if (window.opener.location != "") {
	document.write('<div style="text-align: right"><a href="javascript:window.close()">Close window</a></div>');
}
//]]>
</script>
<?php
    }

    /**
     * Return the base URL for the page.  Used by the constructor for calling
     * $this->set_url().  Although the default behaviour is somewhat sane, this
     * method should be overridden by subclasses if the page may be created to
     * represent a page that is not the current page.
     */
    protected function _get_page_url() {
        return '/blocks/php_report/schedule.php';
    }
}
