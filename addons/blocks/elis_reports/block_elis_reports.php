<?php
/**
 * Block for creating links to ELIS reports on JasperServer.
 *
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
 * @subpackage reporting
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

class block_elis_reports extends block_list {
    // map of old (Jasper) report names to new names
    static $reports_map = array(
        //'usersreport' => array('name' => 'Users report'),
        //'curricula' => 'curricula',
        'Course_Completion_By_Cluster_New' => 'course_completion_by_cluster',
        'Course_Completion_Gas_Gauge' => 'class_completion_gas_gauge',
        //'Forum_Participation' => array('name' => 'Forum Participation'),
        'New_Registrants_by_Student' => 'registrants_by_student',
        'New_Registrants_Grouped_by_Course' => 'registrants_by_course',
        'Non-Starter_Report' => 'nonstarter',
        //'Outcomes_New' => array('name' => 'Outcomes Report'),
        'Site_Wide_Course_Completion_Report' => 'sitewide_course_completion',
        'Site_Wide_ELIS_Transcript_Report' => 'sitewide_transcript',
        'sitewide_time_summary' => 'sitewide_time_summary',
        );

    /**
     * Set block's initial variables
     *
     * @var string  title   name of block
     * @var integer version     version number YYYYMMDDVV
     * @var string  release  version this code is to start being used in
     */
    function init () {
        $this->title = get_string('elis_reports', 'block_elis_reports');
        $this->version = 2009031100;
        $this->release = '1.9.0';
    }

    /**
     * Set if this block is configurable
     *
     * @return  boolean true    return true if we are to allow configuration, false otherwise
     */
    function instance_allow_config() {
        return true;
    }

    /**
     * Set if this block can have multiple instances
     *
     * @return  boolean true    return true if we are to allow configuration, false otherwise
     */
    function instance_allow_multiple() {
        return true;
    }

    /**
     * Get the block content
     *
     * @return  object  content items and icons arrays of what is to be displayed in this block
     */
    function get_content() {
        global $CFG, $COURSE, $USER;

        if (!isloggedin() || isguestuser()) {
            //user is not properly logged in
            return '';
        }

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        $siteContext = get_context_instance(CONTEXT_SYSTEM);
        if($COURSE->id == SITEID) {
            $context = $siteContext ;
        } else {
            $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        }

        // make sure the user has the required role
        if (!empty($this->config->role)) {
            $sql = "SELECT r.id, r.name
                      FROM {$CFG->prefix}role r
                      JOIN {$CFG->prefix}role_assignments ra ON ra.roleid = r.id
                      JOIN {$CFG->prefix}user u ON u.id = ra.userid
                     WHERE ra.contextid = {$context->id}
                           AND u.id = {$USER->id}
                           AND ra.roleid = {$this->config->role}";
            if (!record_exists_sql($sql)) {
                $this->content->items = array();
                $this->content->icons = array();
                return $this->content;
            }
        }

        $items = array();
        $icons = array();
        $categories = array();

        if (isset($this->config->reports)) {

            // Require the php_report class
            require_once($CFG->dirroot.'/blocks/php_report/php_report_base.php');

            $params = array();
            // set the parameters that we can get from the environment
            // (currently only the course ID)
            if ($this->instance->pagetype == PAGE_COURSE_VIEW) {
                if ($this->instance->pageid != SITEID) {
                    $params['courseid'] = $this->instance->pageid;
                }
            }

            // TODO: figure out capability for showing scheduling icon
            $isediting = isediting($this->instance->pageid);
            // && has_capability('block/php_report:manageactivities', $context);

            $count = 0;

            // create links to the reports
            foreach ($this->config->reports as $report) {
                if (isset(block_elis_reports::$reports_map[$report->id])) {
                    $report->id = block_elis_reports::$reports_map[$report->id];
                }
                $report_instance = php_report::get_default_instance($report->id);
                //make sure the report shortname is valid
                if ($report_instance !== FALSE) {

                    if ($report_instance->is_available() &&
                        $report_instance->can_view_report()) {
                        $category = $report_instance->get_category();
                        if (!isset($categories[$category])) {
                            $categories[$category] = array();
                        }
                        $name = $report_instance->get_display_name();

                        $report_link = new moodle_url($CFG->wwwroot.'/blocks/php_report/render_report_page.php', $params+$report->params+array('report'=>$report->id));
                        $categories[$category][$count]['item']= '<a href="'.$report_link->out().'">'.$name.'</a>';

                        //create an instance specifically for testing scheduling permissions
                        $test_scheduling_permissions_instance = php_report::get_default_instance($report->id, NULL, php_report::EXECUTION_MODE_SCHEDULED);
                        //get_default instance will return FALSE if we are not allowed access to scheduling
                        $can_schedule = $test_scheduling_permissions_instance !== FALSE;

                        if ($isediting && $can_schedule) {
                            // TODO: add permissions to this url
                            $link = new moodle_url('/blocks/php_report/schedule.php?report=' . $report->id . '&action=listinstancejobs&createifnone=1');

                            $image_link = '<a href="#" alt=\''.get_string('schedule_this_report', 'block_php_report').'\'  title=\''.
                                           get_string('schedule_this_report', 'block_php_report').'\' onclick="openpopup(\'' .
                                           $link->out() . '\', \'php_report_param_popup\', \'menubar=0,location=0,scrollbars,status,resizable,width=1600,height=600\');return false;">
                                            &nbsp;<img src="' . $CFG->wwwroot . '/blocks/php_report/pix/schedule.png"/>
                                            </a>';
                            $categories[$category][$count]['sched_icon'] = $image_link;
                        }

                        $categories[$category][$count]['icon'] = '<img src="' . $CFG->wwwroot . '/blocks/elis_reports/pix/report.png" />';
                        $count++;
                    }
                }
            }
            // Generates items and icons array
            $this->generate_content($categories, $this->content->items, $this->content->icons);
        }

        return $this->content;
    }

    /**
     * To generate content that works with block content
     * extract items and icons with category headers
     *
     * @param   array               categories    array hash by categories
     * @param   array reference     items         menu items to be displayed in block
     * @param   array reference     icons         icons to be displayed alongside the menu items
     */
    function generate_content($categories, &$items, &$icons) {

        foreach ($categories as $category => $reports) {

            $items[] =  '<div class="category">'.get_string($category, 'block_php_report').'</div>';
            $icons[] = '';
            foreach ($reports as $report) {
                // use a table to display this properly
                $table = null;
                //Initialize table
                $table->width = "85%";
                $table->cellpadding = "0";
                $table->cellspacing = "0";
                $table->border = "0";
                $table->class = "block_elis_reports";
                $row = array();
                $row['0'] = $report['item'];
                $size = array('98%','0%');
                if (array_key_exists('sched_icon',$report)) {
                    $row['1'] = $report['sched_icon'];
                    $size = array('85%','13%');
                }
                //$items[] = $report['item'];
                $table->data[] = $row;
                $table->size = $size;
                $items[] = print_table($table,true);
                $icons[] = $report['icon'];
            }
        }
    }

    /**
     * Allow for an alternate title for this block
     *
     * @var object  title   new title if set
     */
    function specialization() {
        if(!empty($this->config->title)) {
            $this->title = $this->config->title;
        }
    }

    /**
     * Save config instance data
     *
     * @param $data
     * @param $pinned
     */
    function instance_config_save($data,$pinned=false) {
        $newdata = new stdClass();
        $newdata->reports = array();
        foreach ($data as $key => $value) {
            if (strcmp($key,'report_'.$value) == 0) {
                // make the requested reports into an array
                $report = new stdClass();
                $report->id = $value;
                $report->params = array();
                $newdata->reports[$value] = $report;
            } else {
                $newdata->$key = $value;
            }
        }
        return parent::instance_config_save($newdata,$pinned);
    }
}

?>
