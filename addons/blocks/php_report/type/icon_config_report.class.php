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
 * @subpackage pm-blocks-phpreport-icon_config_report
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/blocks/php_report/php_report_base.php');
require_once($CFG->dirroot .'/blocks/php_report/type/icon_report.class.php');
require_once($CFG->dirroot .'/blocks/php_report/lib/config_filter.php');

/**
 * Class that represents "icon" config reports that contain aggregated numbers and allow for selection of included icons
 */
abstract class icon_config_report extends icon_report {

    private $numcolumns;
    private $iconwidth;

    /**
     * This is where an icon report's data should be calculated and set in $this->data
     *
     * $this->data should contain an array for each icon, containing the display name,
     * the numerical value, and the path to the icon file
     *
     * @uses  $DB
     */
    function calculate_data() {
        global $DB;
        $params = array();
        $this->data = $this->get_default_data();

        // Get extra icons... send list of filters but don't include exceptions - as we do need the filter list here
        $exceptions = $this->get_filter_exceptions();
        $rest_of_filters = array();
        foreach ($this->filter->_fields as $key => $value) {
            if (!in_array($key, $exceptions)) {
                $rest_of_filters[] = $key;
            }
        }

        // Pass the rest_of_filters array, but then also let the method know this is a special config filter
        $extra_icons = $this->filter->get_sql_filter('', $rest_of_filters,
                                         $this->allow_interactive_filters(),
                                         $this->allow_configured_filters(),
                                         '', true);

        foreach ($this->data as $key => $value) {
            //If key is in choices array and not in extra_icons
            if (array_key_exists($key, $this->checkboxes_filter->options['choices']) &&
                !strpos($extra_icons[0], $key)) { // ***TBV***
                // then we need to remove it from $this->data ...
                unset($this->data[$key]);
                continue;
            }

            //first step - try getting the item value directly
            $data_item = $this->get_data_item($key);

            if ($data_item !== FALSE) {
                $this->data[$key]->value = $data_item;
            } else {
                //backup plan - use SQL query to get the item value

                $use_filters = true;
                $sql = $this->get_data_item_sql($key, $use_filters);
                if ($sql !== FALSE) {
                    //parse SQL for a WHERE clause
                    $has_where_clause = php_report::sql_has_where_clause($sql);

                    $conditional_symbol = 'WHERE';
                    if ($has_where_clause) {
                        $conditional_symbol = 'AND';
                    }

                    // apply filters if applicable
                    if (!empty($this->filter) && !empty($use_filters)) {
                        // Include filter_exceptions here so that our config type filter is not included in the filters added to the final sql
                        $sql_filter = $this->filter->get_sql_filter('',
                                                         $this->get_filter_exceptions($key),
                                                         $this->allow_interactive_filters(),
                                                         $this->allow_configured_filters());
                        if ($sql_filter != null && !empty($sql_filter[0])) {
                            $sql .= " {$conditional_symbol} ({$sql_filter})";
                            $params += $sql_filter[1];
                        }
                    }
                    //obtain field value
                    if ($field_data = $DB->get_field_sql($sql, $params)) {
                        $this->data[$key]->value = $field_data;
                    }
                }
            }
        }

    }

    function init_filter($id, $init_data = true) {
        global $CFG;

        if (!isset($this->filter) && $filters = $this->get_filters($init_data)) {
            $dynamic_report_filter_url = $CFG->wwwroot .'/blocks/php_report/dynamicreport.php?id='. $id;
            // Need to have a different php_report_default_capable_filtering that is smarter
            $this->filter = new php_report_config_capable_filtering($filters,
                                    $dynamic_report_filter_url, null, $id,
                                    $this->get_report_shortname());
        }
    }

    /**
     * Initializes all data needed before executing this report
     *
     * @param  int|string     $id              The report identifier
     * @param  stdClass|NULL  $parameter_data  Parameter data manually being set
     */
    function init_all($id, $parameter_data = NULL) {
        //initialize filters
        $this->init_filter($id, false);

        //initialize icons to the empty set
        $this->icons = array();
    }

    /**
     * Mainline function for running the report
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 20,
                  $download = '', $id = 0) {

        $this->display_header();

        //set_paging_and_sorting($page, $perpage, $sort, $dir);

        $this->init_all($id);

        //calculate actual report data
        $this->calculate_data();

        echo $this->display_icons($id);

        $this->display_footer();
    }

}

