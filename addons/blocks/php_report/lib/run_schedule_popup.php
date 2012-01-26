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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once('../../../config.php');

print_header();

$scheduleids = required_param('scheduleids', PARAM_CLEAN);

//javascript dependencies
require_js($CFG->wwwroot . '/blocks/php_report/lib.js');
require_js(array('yui_yahoo', 'yui_event', 'yui_connection', 'yui_json'));

//display instructions
print_box(get_string('popup_run_instructions', 'block_php_report'));

//field to fill in with information on the current job that is running
echo '<div id="php_report_schedule_current_job">
      </div>';

//import necessary CSS
$stylesheet_web_path = $CFG->wwwroot . '/blocks/php_report/styles.php';
echo '<style>@import url("' . $stylesheet_web_path . '");</style>';

//progress bar UI, to be manipulated
echo '<div class="php_report_schedule_progress_bar" align="center">
        <table class="php_report_schedule_progress_bar_table">
          <tr>
            <td id="php_report_schedule_progress_bar_completed" style="width:0%;" class="php_report_schedule_progress_bar_completed">
            </td>
            <td class="php_report_schedule_progress_bar_todo">
              <div class="php_report_schedule_progress_bar_token">
              </div>
            </td>
          </tr>
        </table>
      </div>';

//field to fill in with progress updates in the form "x of n"
echo '<div id="php_report_schedule_progress">
      </div>';

//field to fill in with errors
echo '<div id="php_report_schedule_errors">
      </div>';

//retrieve the labels of scheduled tasks
$schedulenames = array();
//store all the schedule ids that are valid
$valid_scheduleids = array();

$test_scheduleids = json_decode($scheduleids);

//error checking
if (!is_array($test_scheduleids) || count($test_scheduleids) == 0) {
    print_error('popup_jobs_error', 'block_php_report');
}

//create a page for permissions checking
require_once $CFG->dirroot . '/blocks/php_report/lib/schedulelib.php';
$page = new scheduling_page();

foreach ($test_scheduleids as $scheduleid) {
    //retrieve the schedule name
    $config = get_field('php_report_schedule', 'config', 'id', $scheduleid);
    $config = unserialize($config);

    //check permissions
    if ($page->can_do_schedule_action($scheduleid, $config['report'])) {
        $valid_scheduleids[] = $scheduleid;
        // urlencode config label
        $schedulenames[] = urlencode($config['label']);
    }
}

//re-encode only the valid schedule ids
$scheduleids = json_encode($valid_scheduleids);

//calculate the labels to display 
$runninglabel = get_string('popup_running_label', 'block_php_report');
$donerunninglabel = get_string('popup_done_running_label', 'block_php_report');

$a = new stdClass;
$a->current = 0;
$a->total = count($test_scheduleids);
$progress_text = get_string('popup_status', 'block_php_report', $a);

//trigger the scheduled jobs to run as soon as this page is loaded
echo '<script type="text/javascript">
      YAHOO.util.Event.onDOMReady(function() {
          php_report_schedule_run_jobs("' . $CFG->wwwroot . '", ' . $scheduleids . ', '
          . json_encode($schedulenames) . ', 0, "' . $runninglabel . '", "' . $donerunninglabel . '", "' . $progress_text . '");
      });
      </script>';

//temporarily change the config global to prevent the documentation link
//from showing up
$backup_CFG = fullclone($CFG);
$CFG->docroot = '';
//remove as much info from the footer as possible
print_footer('none');
$CFG = $backup_CFG;

?>