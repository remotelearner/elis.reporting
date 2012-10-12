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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot .'/blocks/php_report/lib/filtering.php');

/**
 * Class that handles the calculation of SQL filters for both interactive and configured filters
 */
class php_report_config_capable_filtering extends php_report_default_capable_filtering {

    var $reportname = '';
    var $secondary_filterings = array();

    function php_report_config_capable_filtering($fields = null,
                 $baseurl = null, $extraparams = null, $id = 0,
                 $reportname = '', $secondary_filterings = array()) {

        parent::__construct($fields, $baseurl, $extraparams, $id, $reportname, $secondary_filterings);
        $this->reportname = $reportname;
        $this->secondary_filterings = $secondary_filterings;
    }

    /**
     * Returns extra icon filters
     * @param string $extra sql
     * @return string
     */
    function get_sql_filter($extra = '', $exceptions = array(),
                            $allow_interactive_filters = false,
                            $allow_configured_filters = true,
                            $secondary_filtering_key = '',
                            $config_filter = false) {
        global $SESSION;
        $found = false;
        $per_filter_data = array();
        $sqls = array();
        $params = array();
        $user_preferences = php_report_filtering_get_user_preferences($this->reportname);

        foreach ($user_preferences as $key => $value) {
            $parts = explode('/', $key);
            //is preference php-report related?
            if (strpos($parts[0], 'php_report_') === 0) {
                $element_name = $parts[1];

                if (strpos($element_name, '_') !== FALSE) {
                    $parts = explode('_', $element_name);
                    $group_name = $parts[0];
                } else {
                    $group_name = $element_name;
                }
                if (!isset($per_filter_data[$group_name])) {
                    $per_filter_data[$group_name] = array();
                }
                $per_filter_data[$group_name][$element_name] = $value;
            }
        }

        //Using user preferences, create list of icons to send back
        $result = '';

        foreach ($this->_fields as $shortname => $field) {
            // Using exceptions array from report to not add any config type filters to filter
            if (in_array($shortname,(array)$exceptions)) {
                continue;
            }
            if (isset($per_filter_data[$shortname]) && $found !== true) {
                $formatted_data = $field->check_data((object)$per_filter_data[$shortname]);
                if ($formatted_data != false) {
                    if ($config_filter == true) {
                        $result = $formatted_data['value'];
                        $found = true;
                    } else {
                        $newsql = $field->get_sql_filter($formatted_data);
                        if ($newsql !== null && !empty($newsql[0])) {
                            $sqls[] = $newsql[0];
                            $params += $newsql[1];
                        }
                    }
                }
            }
        }

        //combine SQL conditions
        if (!empty($sqls)) {
            $sql_piece = implode(' AND ', $sqls);
            if ($result === '') {
                $result = $sql_piece;
            } else {
                $result .= ' AND ' . $sql_piece;
            }
        }

        return array($result, $params); // TBD
    }
}

