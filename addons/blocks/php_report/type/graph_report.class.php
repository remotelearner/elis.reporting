<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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

require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');

/**
 * Class that represents vertical bar graph reports
 */
abstract class graph_report extends php_report {

    var $data;

    /**
     * Print a bar graph based on the data defined within this report instance
     *
     * @param  int|string  $id  The appropriate block instance id
     *
     */
    function print_bars($id = 0) {
        global $CFG;

        //prep the data as a URL parameter
        $data = urlencode(base64_encode(serialize($this->data)));

        //header link for configuring default parameters
        echo $this->get_config_header();
        
        //show available and active filters if applicable
        echo $this->get_interactive_filter_display();
        
        //dynamically generated graph image
        echo '<img src="' . $CFG->wwwroot . '/blocks/php_report/bargraph_output.php?data=' . $data . '&id=' . $id . '">';
    }

    /**
     * This method should return an associative array containing with
     * keys uniquely identifying each data series and values containing
     * the display name for that series
     *
     * @return  array  Unique identifiers mapped to series display names
     */
    abstract function calculate_series_list();

    /**
     * Determines the x-values for a particular series
     * 
     * @param   string         $series_key  The key that identifies a series
     * 
     * @return  numeric array               The ordered list of x-values
     */
    abstract function calculate_series_points($series_key);
    
    /**
     * Specifies the SQL statement used to obtain the value of one bar in a series
     * 
     * @param   string   $series_key  The key that identifies a series
     * @param   numeric  $point_key   The x-coordinate whose bar height we are calculating
     * 
     * @return  string                The SQL query to run to calculate the bar height
     *                                (query should return a single value)
     */
    abstract function get_series_point_data_sql($series_key, $point_key);
    
    /**
     * Calculates the data for a specified data series
     *
     * @param   unknown  $key  A key that uniquely identifies the series, as
     *                         specified by calculate_series_list
     *
     * @return  array          The numerical data for the specified series
     */
    function calculate_series($series_key) {
        
        $data = array();
        
        $points = $this->calculate_series_points($series_key);
        
        foreach ($points as $point) {
            $sql = $this->get_series_point_data_sql($series_key, $point);
            
            $has_where_clause = php_report::sql_has_where_clause($sql);
        
            $conditional_symbol = 'WHERE';
        
            if($has_where_clause) {
                $conditional_symbol = 'AND';
            }
        
            if(!empty($this->filter)) {
                $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters());
                if(!empty($sql_filter)) {
                    $sql .= " {$conditional_symbol} ({$sql_filter})";
                }
            }
            
            if ($point_data = get_field_sql($sql)) {
                $data[] = $point_data;
            } else {
                $data[] = 0;
            }
        }
        
        return $data;
    }

    /**
     * Calculates x-axis labels for the different points in a series
     *
     * @param   int    $series_size  The number of points in the longest series
     *
     * @return  array                The list of labels
     */
    abstract function calculate_labels($series_size);
    
    /**
     * This is where a graph's data should be calculated and set in $this->data
     *
     * This method calls the appropriate helpers that are overridden by implementation
     * classes in order to determine the appropriate
     */
    function calculate_data() {
        $this->data = array();
        $this->data['series'] = array();

        $series_size = 0;

        $series_list = $this->calculate_series_list();
        foreach($series_list as $key => $value) {
            $this->data['series'][$value] = $this->calculate_series($key);

            if($series_size < count($this->data['series'][$value])) {
                $series_size = count($this->data['series'][$value]);
            }
        }

        $this->data['labels'] = $this->calculate_labels($series_size);
    }

    /**
     * Initializes all data needed before executing this report
     *
     * @param  int|string     $id              The report identifier
     * @param  stdClass|NULL  $parameter_data  Parameter data manually being set
     */
    function init_all($id, $parameter_data = NULL) {
        //set up filters
        $this->init_filter($id);

        //calculate the graph data
        $this->calculate_data();
    }

    /**
     * Mainline function for running the report
     * 
     * @param  string      $sort      Field we are currently sorting by
     * @param  string      $dir       Sort order (should be '', 'asc', or 'desc')
     * @param  int         $page      Current page we are on (0-indexed)
     * @param  int         $perpage   Number of records per page
     * @param  string      $download  Download format (not currently implemented here)
     * @param  int|string  $id        Report instance id (aka block id)
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 20, $download = '', $id = 0) {
        global $CFG;

        //initialize all necessary data
        $this->init_all($id);

        //dispaly header entries
        echo $this->print_header_entries();

        //display the main graph
        $this->print_bars($id);
    }

}

?>