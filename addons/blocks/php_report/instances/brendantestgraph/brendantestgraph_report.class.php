<?php

require_once($CFG->dirroot . '/blocks/php_report/type/graph_report.class.php');

class brendantestgraph_report extends graph_report {

    /**
     * Returns a sample series list mapping integers to people's names
     *
     * @return  array  Unique identifiers mapped to series display names
     */
    function calculate_series_list() {
        return array(1  => 'John With a Very Long Name',
                     2  => 'Bob',
                     3  => 'Jim',
                     4  => 'Tom');
    }

    /**
     * Calculates a sample linear progression of x-values
     * 
     * @param   string         $series_key  The key that identifies a series
     * 
     * @return  numeric array               The ordered list of x-values
     */
    function calculate_series_points($series_key) {
        return array(0, 1, 2, 3 ,4);
    }

    /**
     * Specifies a simple SQL statement that will return the value based on a linear progression
     * 
     * @param   string   $series_key  The key that identifies a series
     * @param   numeric  $point_key   The x-coordinate whose bar height we are calculating
     * 
     * @return  string                The SQL query to run to calculate the bar height
     *                                (query should return a single value)
     */
    function get_series_point_data_sql($series_key, $point_key) {
        global $CFG;
        
        $value = $series_key * $point_key;
        
        return "SELECT {$value}
                {$CFG->prefix}log";
    }

    /**
     * Calculates x-axis labels representing day 1 to day n
     *
     * @param   int    $series_size  The number of points in the longest series
     *
     * @return  array                The list of labels
     */
    function calculate_labels($series_size) {
        $result = array();

        //"Day 1" to "Day n"
        for($i = 1; $i <= $series_size; $i++) {
            $result[] = get_string('day', 'rlreport_brendantestgraph') . $i;
        }

        return $result;
    }

    /**
     * Specifies whether the current report is available
     * (a.k.a. any other components it requires are installed)
     *
     * @return  boolean  True if the report is available, otherwise false
     */
    function is_available() {
        //this is just a sample, so disable it
        return FALSE;
    }

    /**
     * Specifies whether this report definition has one or more
     * filters defined for it
     *
     * @return  boolean  true if one more more filters are defined, or false if none are
     */
    function has_filters() {
        return false;
    }
}

?>