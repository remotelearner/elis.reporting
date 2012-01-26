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

require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

/**
 * Class that represents "icon" reports that contain aggregated numbers
 */
abstract class icon_report extends php_report {

    private $numcolumns;
    private $iconwidth;

    /**
     * Display the appropriate icons, text, and numbers based on the data defined
     * within this instance
     *
     * @param  int|string  $id  The appropriate block instance id
     */
    function display_icons($id) {

        //default number of columns
        $this->numcolumns = 2;

        $this->iconwidth = "100%";

        $result = '';

        //header link for configuring default parameters
        $result .= $this->get_config_header();

        //display available and active filters if applicable
        echo $this->get_interactive_filter_display();

        $result .= $this->print_header_entries();

        /**
         * Main display logic
         */
        $column_position = 0;

        $result .= html_writer::start_tag('div');

        //use slightly less space due to IE7 width issues
        $percent_width = floor(100 / $this->numcolumns) - 1;

        foreach($this->data as $key => $datum) {

            //this is used to make sure everything lines up in a nice grid
            $clear = $column_position == 0 ? 'left' : 'none';

            $result .= html_writer::start_tag('div', array('style' => 'width: '.$percent_width.'%; float:left; clear: '.$clear.';',
                                                           'class' => 'php_report_icon_top_level_div'));
            $result .= html_writer::start_tag('div', array('style' => 'width: 20%; float: left;',
                                                           'class' => 'php_report_icon_main_div php_report_icon_image_div'));
            $result .= html_writer::empty_tag('img', array('src' => $datum->get_icon_url(),
                                                           'style' => 'width: '.$this->iconwidth,
                                                           'class' => $key.'_image'));
            $result .= html_writer::end_tag('div');
            $result .= html_writer::tag('div', '&nbsp;', array('style' => 'width: 5%; float:left;'));
            $result .= html_writer::tag('div', $datum->display, array('style' => 'width: 45%; float: left;',
                                                                      'class' => $key.'_label php_report_icon_main_div php_report_icon_label_div'));
            $result .= html_writer::tag('div', '&nbsp;', array('style' => 'width: 5%; float: left;'));
            $result .= html_writer::tag('div', $datum->format_number(), array('style' => 'width: 20%; float: left; text-align: right;',
                                                                              'class' => $key.'_value php_report_icon_main_div php_report_icon_value_div'));
            $result .= html_writer::tag('div', '&nbsp', array('style' => 'width: 5%; float:left;'));
            $result .= html_writer::end_tag('div');

            $column_position = ($column_position + 1) % $this->numcolumns;
        }

        if($column_position > 0) {
            for($i = $column_position; $i < $this->numcolumns; $i++) {
                $result .= html_writer::tag('div', '&nbsp;', array('style' => 'width: '.$percent_width.'%;float: left'));
            }
        }

        $result .= html_writer::end_tag('div');
        $result .= html_writer::empty_tag('br', array('style' => 'clear:both'));

        return $result;

    }

    /**
     * Specifies the list of filters that should NOT be applied to this field
     *
     * @param   string        $data_shortname  Shortname of the icon we are checking filters for
     *
     * @return  string array                   The list of shortnames of filters that should be ignored
     */
    function get_filter_exceptions($data_shortname) {
        return array();
    }

    /**
     * Returns the value of the specified field based on the
     * declaration of the get_{$key} method in the implementing class
     *
     * @param   string  $key  The shortname of the data item
     *
     * @return  mixed         The data item, or FALSE if it can't be obtained this way
     */
    function get_data_item($key) {
        $function_name = 'get_' . $key;

        if (method_exists($this, $function_name)) {
            $item = $this->$function_name();
            return $item;
        } else {
            return FALSE;
        }
    }

    /**
     * Returns the SQL used to calculate the value of the supplied field
     * based on the declaration of the get_{$key}_sql method in the implementing class
     *
     * @param   string   $key          Key that identifies the field we are calculating
     * @param   boolean  $use_filters  Flag that can be updated to prevent use of filters
     *                                 for this field
     *
     * @return  array                  SQL string, or FALSE if not implemented by subclass,
     *                                 and applicable query filter data
     */
    function get_data_item_sql($key, &$use_filters) {
        $function_name = 'get_' . $key . '_sql';

        if (method_exists($this, $function_name)) {
            list($sql, $params) = $this->$function_name($use_filters);
            return array($sql, $params);
        } else {
            return array(false, array());
        }
    }

    /**
     * This is where an icon report's data should be calculated and set in $this->data
     *
     * $this->data should contain an array for each icon, containing the display name,
     * the numerical value, and the path to the icon file
     */
    function calculate_data() {
        global $DB;

        $this->data = $this->get_default_data();

        foreach ($this->data as $key => $value) {

            //first step - try getting the item value directly
            $data_item = $this->get_data_item($key);

            if ($data_item !== FALSE) {
                $this->data[$key]->value = $data_item;
            } else {
                //backup plan - use SQL query to get the item value

                $use_filters = true;
                list($sql, $params) = $this->get_data_item_sql($key, $use_filters);

                if ($sql !== FALSE) {
                    //parse SQL for a WHERE clause

                    $has_where_clause = php_report::sql_has_where_clause($sql);

                    $conditional_symbol = 'WHERE';

                    if($has_where_clause) {
                        $conditional_symbol = 'AND';
                    }

                    //apply filters if applicable
                    if(!empty($this->filter) && !empty($use_filters)) {
                        list($additional_sql, $additional_params) = $this->filter->get_sql_filter('', $this->get_filter_exceptions($key), $this->allow_interactive_filters(), $this->allow_configured_filters());
                        if(!empty($additional_sql)) {
                            $sql .= " {$conditional_symbol} ({$additional_sql})";
                            $params = array_merge($params, $additional_params);
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
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $download = '', $id = 0) {
        global $CFG;

        $this->display_header();

        $this->init_all($id);

        //calculate actual report data
        $this->calculate_data();

        echo $this->display_icons($id);

        $this->display_footer();
    }

}

/**
 * Class that represents a single icon entry on this type of report
 */
class icon_report_entry {
    var $display;
    var $value;
    var $icon;
    var $num_decimals = 0;
    var $is_percent = false;

    /**
     * Icon report entry constructor
     *
     * @param  string   $display       Displayname of the field
     * @param  int      $value         Value to display
     * @param  string   $icon          Filename of icon, not including extension
     * @param  int      $num_decimals  Number of decimals to display
     * @param  boolean  $is_percent    True if value is a percentage, false otherwise
     */
    function icon_report_entry($display, $value=0, $icon, $num_decimals = 0, $is_percent = false) {
        $this->display = $display;
        $this->value = $value;
        $this->icon = $icon;
        $this->num_decimals = $num_decimals;
    }

    /**
     * Formats a number to be displayed in the report based on the value stored in this object
     *
     * @return  string  The formatted number
     */
    function format_number() {
        if(is_numeric($this->value) === false) {
            //$this->value = 0;
            return $this->value;
        }
        //round to two decimals and add thousands comma separator
        return number_format($this->value, $this->num_decimals);
    }

    /**
     * Returns the URL for this item's icon based on the "icon" value
     * set in the constructor
     *
     * @return  string  URL path of the image
     */
    function get_icon_url() {
        global $CFG;

        return $CFG->wwwroot . '/blocks/php_report/pix/' . $this->icon . '.jpg';
    }
}

/**
 * Class for special "percent" type of entry
 */
class icon_report_entry_percent extends icon_report_entry {

    /**
     * Formats a number to be displayed in the report, overriding
     * base functionality
     *
     * @return  string  The formatted number
     */
    function format_number() {
        //add the precent sign
        return parent::format_number($this->value, $this->num_decimals) . '%';
    }
}
