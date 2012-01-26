/**
 * Generic JavaScript methods for a association/selection page.  Allows
 * multiple items to be selected using checkboxes, and use AJAX to do
 * paging/searching while maintaining the selection.  The selection will be
 * submitted as a form fieled called '_selection', which will be a JSON-encoded
 * array.
 *
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

/**
 * Finds an element with the given name attribute
 *
 * @return  HTMLElement  The first element found with the given name
 */
function get_element_by_name(name) {
    return YAHOO.util.Dom.getElementsBy(function(el) { return el.getAttribute("name") == name; })[0]
}

/**
 * Selects all checkboxes on the appropriate form
 */
function select_all() {
	//select all checkboxes
    table = YAHOO.util.Dom.getElementsBy(function(el) { return true; }, 'table', 'list_display')[0];
    if (table) {
	YAHOO.util.Dom.getElementsBy(function(el) { return true; },
				     'input', table,
				     function(el) {
					 el.checked = true;
					 id = el.name.substr(6);
				     });
    }
    //uncheck the "select all" checkbox
    button = get_element_by_name('selectall');
    button.checked = false;
}

/**
 * Updates the size of the progress bar based on the number of jobs completed
 *
 * @param   int  completed  Number of jobs completed
 * @param   int  total      Total number of jobs being run
 *
 * @return  none
 */
function php_report_schedule_update_progress_bar(completed, total) {
	//calculate the current width based on progress
	var percent = Math.round(completed / total * 100);
	//update the progress bar
	var progress_bar_element = document.getElementById('php_report_schedule_progress_bar_completed');
	progress_bar_element.style.width = percent + "%";
}

/**
 * Updates the UI to show the label of the current job that's running
 *
 * @param   string  text  The text to display
 *
 * @return  none
 */
function php_report_schedule_set_current_job(text) {
	var element = document.getElementById('php_report_schedule_current_job');
    element.innerHTML = text;
}

/**
 * Updates the UI to show number of jobs completed out of total number of jobs
 *
 * @param  string  text  The text to display
 *
 * @return
 */
function php_report_schedule_set_progress(text) {
	var second_element = document.getElementById('php_report_schedule_progress');
    second_element.innerHTML = text;
}

/**
 * Updates the UI to show an errors that may have taken place
 *
 * @param  string  text  The error text to append
 */
function php_report_schedule_append_error(text) {
	if (text != '') {
    	var error_element = document.getElementById('php_report_schedule_errors');
	    if (error_element.innerHTML == '') {
		    error_element.innerHTML = text;
	    } else {
		    error_element.innerHTML += '<br/>' + text;
	    }
	}
}

/**
 * Runs remaining jobs from the provided list in an asynchronous way
 *
 * @param  string  wwwroot           The moodle site webroot, needed to find the appropriate PHP script
 * @param  array   scheduleids       Ids of the PHP report schedules we are running
 * @param  array   schedulenames     Labels from the PHP report schedules we are running
 * @param  int     index             Zero-indexed position of which schedule we are currently running
 * @param  string  runninglabel      Prefix used when displaying the current running job
 * @param  string  donerunninglabel  Text displayed at the top of the form when all jobs are complete
 * @param  string  progresstext      Text displaying completed and total jobs
 *
 * @return  none
 */
function php_report_schedule_run_jobs(wwwroot, scheduleids, schedulenames, index, runninglabel, donerunninglabel, progresstext) {

    //success handler
	var php_report_success = function(o) {
	    //parse the reponse data, consisting of progress text and an error message
		var response_data = YAHOO.lang.JSON.parse(o.responseText);

		php_report_schedule_update_progress_bar(index + 1, scheduleids.length);

        if ((index + 1) < scheduleids.length) {
        	//chain on to the next job
            php_report_schedule_run_jobs(wwwroot, scheduleids, schedulenames, index + 1, runninglabel, donerunninglabel, response_data[0]);
        } else {
        	php_report_schedule_set_current_job(donerunninglabel);

        	php_report_schedule_set_progress(response_data[0]);

            //update errors in the UI
        	php_report_schedule_append_error(response_data[1]);
        }
	}

	//failure handler
    var php_report_failure = function(o) {
        //parse the reponse data, consisting of progress text and an error message
		var response_data = YAHOO.lang.JSON.parse(o.responseText);

    	if ((index + 1) < scheduleids.length) {
    		//chain on to the next job
            php_report_schedule_run_jobs(wwwroot, scheduleids, schedulenames, index + 1, runninglabel, donerunninglabel, response_data[0]);
    	}
    }

	//similar to profile_value.js
	var callback = {
	    success: php_report_success,
	    failure: php_report_failure
	}

	//set up the AJAX request
	var requestURL = wwwroot + "/blocks/php_report/lib/run_schedule_async.php?scheduleid=" +
	                 scheduleids[index] + '&schedulename=' + schedulenames[index] + '&current=' + (index+1) + '&total=' + scheduleids.length;

	php_report_schedule_set_current_job(runninglabel + schedulenames[index]);

	php_report_schedule_set_progress(progresstext);

    YAHOO.util.Connect.asyncRequest('GET', requestURL, callback, null);
}