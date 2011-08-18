<?php
/**
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot . '/blocks/php_report/php_report_base.php');
require_once($CFG->dirroot . '/blocks/php_report/type/table_report.class.php');

//relative width for use within a block
if (!defined('PHP_REPORT_GAS_GAUGE_RELATIVE_WIDTH')) {
    define('PHP_REPORT_GAS_GAUGE_RELATIVE_WIDTH', 0.6);
}

//maximum width when rendered on its own page
if (!defined('PHP_REPORT_GAS_GAUGE_MAXIMUM_WIDTH')) {
    define('PHP_REPORT_GAS_GAUGE_MAXIMUM_WIDTH', 200);
}

//define constants to represents the possible secondary filters
if (!defined('PHP_REPORT_SECONDARY_FILTERING_PAGE_VALUE')) {
    define('PHP_REPORT_SECONDARY_FILTERING_PAGE_VALUE', 'page_value');
}

if (!defined('PHP_REPORT_SECONDARY_FILTERING_NUM_PAGES')) {
    define('PHP_REPORT_SECONDARY_FILTERING_NUM_PAGES', 'num_pages');
}

if (!defined('PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_VALUE')) {
    define('PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_VALUE', 'gas_gauge_value');
}

if (!defined('PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_MAX_VALUE')) {
    define('PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_MAX_VALUE', 'gas_gauge_max_value');
}

/**
 * Class that represents a tabular report with a gas gauge on top of it
 */
abstract class gas_gauge_table_report extends table_report {
    var $inner_page;

    //numerical index of where we are in terms of top-level pages
    var $gas_gauge_page = 0;

    //total number of top-level pages
    var $num_gas_gauge_pages;

    //value reporesenting current top-level page
    var $gas_gauge_page_value;

    //current value representing needle on gas gauge
    var $gas_gauge_value;
    //current value representing "tank size" on the gas gauge
    var $gas_gauge_max_value;

    //static max value for the gas gauge
    //if no get_gas_gauge_max_value is not properly implemented
    var $static_max_value = 100;

    /**
     * Contructor.
     *
     * @param  string    $id                  An identifier for this report
     * @param  int|NULL  $userid              Id of the Moodle user who this report is being
     *                                        for
     * @param  int       $execution_mode      The mode in which this report is being executed
     *
     * @retrn none
     */
    function gas_gauge_table_report($id, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
        parent::__construct($id, $userid, $execution_mode);

        $this->gas_gauge_page = optional_param('gas_gauge_page', 0, PARAM_INT);
    }

    /**
     * Specifies a WHERE / AND clause that acts on the supplied secondary filter
     *
     * @param   string  $sql         The SQL statement to be filtered
     * @param   string  $filter_key  The key that references the filter being applied
     *
     * @return  string               The WHERE / AND clause performing the filtering, or
     *                               an empty string if none
     */
    private function get_secondary_filter_clause($sql, $filter_key) {
        //calculate our conditional symbol / operator based on the existing query
        $has_where_clause = php_report::sql_has_where_clause($sql);

        $conditional_symbol = 'WHERE';

        if ($has_where_clause) {
            $conditional_symbol = 'AND';
        }

        //filtering
        if (!empty($this->filter)) {
            $sql_filter = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters(), $filter_key);
            if(!empty($sql_filter)) {
                return " {$conditional_symbol} ({$sql_filter})";
            }
        }

        return '';
    }

    /* ------------------------------------------------------
     * Functions that deal with gauge-specific paging
     * ------------------------------------------------------ */

    /**
     * Converts a positional page number to a key that identifies the current
     * gas gauge and all tabular data that belongs to it
     *
     * @param   int  $i  A number from 1 to n, where n is the number
     *                   of pages (as specified by get_num_page_values)
     *
     * @return  mixed    A key that uniquely identifies the current "page" in a way
     *                   such that each different gas gauge should have a different key
     */
    function get_page_value($i) {
        if ($sql = $this->get_page_value_sql($i)) {
            //apply page value SQL filter
            $sql .= $this->get_secondary_filter_clause($sql, PHP_REPORT_SECONDARY_FILTERING_PAGE_VALUE);

            $sql .= ' ' .$this->get_page_value_order_by();

            $offset = $this->get_page_value_offset($i);
            return $this->get_field_sql($sql, $offset);
        }

        return NULL;
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the value used to uniquely identify the page you are currently on
     *
     * @param   int     $i  A number from 1 to n, where n is the number
     *                      of pages (as specified by get_num_page_values)
     *
     * @return  string      The sql query that specifies the value as its only field
     */
    function get_page_value_sql($i) {
        return '';
    }

    /**
     * Specifies an ORDER BY clause added to the page value SQL statement
     * to guarantee a consistent page ordering (implement in child class)
     *
     * @return  string  The appropriate ORDER BY clause
     */
    function get_page_value_order_by() {
        return '';
    }

    /**
     * Specifies available report filters for the page value
     * (empty by default but can be implemented by child class)
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_page_value_filters() {
        //no filters by default
        return array();
    }

    /**
     * Calculates the offset to apply to the page value SQL statement
     * so that the right record is fetched
     *
     * @param   int    $i  A number from 1 to n, where n is the number
     *                     of pages (as specified by get_num_page_values)
     *
     * @return  mixed      The offset, if appropriate, or NULL otherwise
     */
    function get_page_value_offset($i) {
        return $i;
    }

    /**
     * Calculates the number of different pages (here, the concept of a page
     * is defined as a gas gauge with some amount of tabular data associated to it)
     *
     * @return  int  The total number of pages
     */
    function get_num_pages() {
        if ($sql = $this->get_num_pages_sql()) {
            //apply num pages SQL filter
            $sql .= $this->get_secondary_filter_clause($sql, PHP_REPORT_SECONDARY_FILTERING_NUM_PAGES);

            return $this->get_field_sql($sql);
        }

        return 0;
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the number of pages
     *
     * @return  string  The sql query that specifies the number of pages
     *                  as its only field
     */
    function get_num_pages_sql() {
        return '';
    }

    /**
     * Specifies available report filters for the number of gas gauge pages
     * (empty by default but can be implemented by child class)
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_num_pages_filters() {
        //no filters by default
        return $this->get_page_value_filters();
    }

    /* ------------------------------------------------------
     * Functions that deal with the gauge data
     * ------------------------------------------------------ */

    /**
     * Calculates the current value of the gas gauge
     *
     * @param   mixed  $key  The unique key that represents the current page you are on,
     *                       as specified by get_page_value
     *
     * @return  int          The current value of the gas gauge
     */
    function get_gas_gauge_value($key) {
        if ($sql = $this->get_gas_gauge_value_sql($key)) {
            //apply gas gauge value SQL filter
            $sql .= $this->get_secondary_filter_clause($sql, PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_VALUE);
            return $this->get_field_sql($sql);
        }

        return 0;
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the current value of the gas gauge
     *
     * @param   mixed   $key  The unique key that represents the current page you are on,
     *                        as specified by get_page_value
     *
     * @return  string        The sql query that specifies the current gas gauge value
     *                        as its only field
     */
    function get_gas_gauge_value_sql($key) {
        return '';
    }

    /**
     * Specifies available report filters for the gas gauge value
     * (empty by default but can be implemented by child class)
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_gas_gauge_value_filters() {
        //no filters by default
        return array();
    }

    /**
     * Calculates the maximum value of the gas gauge
     *
     * @param   mixed  $key  The unique key that represents the current page you are on,
     *                       as specified by get_page_value
     *
     * @return  int          The maximum value of the gas gauge
     */
    function get_gas_gauge_max_value($key) {
        if ($sql = $this->get_gas_gauge_max_value_sql($key)) {
            //apply gas gauge max value SQL filter
            $sql .= $this->get_secondary_filter_clause($sql, PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_MAX_VALUE);

            return $this->get_field_sql($sql);
        }

        //use a sane default
        return $this->static_max_value;
    }

    /**
     * Calculates an SQL query that returns a single field, specifying
     * the maximum value of the gas gauge
     *
     * @param   mixed   $key  The unique key that represents the current page you are on,
     *                        as specified by get_page_value
     *
     * @return  string        The sql query that specifies the maximum gas gauge value
     *                        as its only field
     */
    function get_gas_gauge_max_value_sql($key) {
        return '';
    }

    /**
     * Specifies available report filters for the max gas gauge value
     * (empty by default but can be implemented by child class)
     *
     * @return  generalized_filter_entry array  The list of available filters
     */
    function get_gas_gauge_max_value_filters() {
        //no filters by default
        return $this->get_gas_gauge_value_filters();
    }

    /**
     * Specifies the information displayed near the gas gauge at the top
     * of the report
     *
     * @return  string array  The values to display at the top of the report
     */
    function get_gas_gauge_header_info() {
        return array();
    }

    /**
     * Returns the gas gauge image tag based on the data set in this object
     *
     * @param $id
     */
    function print_gas_gauge($id) {
        global $CFG;

        $radius = 0;
        //track whether config info was found
        $radius_set = FALSE;

        //get radius from the configured report width
        if(!empty($id) and $block_instance = get_record('block_instance', 'id', $id)) {
            $config_data = unserialize(base64_decode($block_instance->configdata));
            if(!empty($config_data->reportwidth)) {
                $radius = round($config_data->reportwidth / 2 * PHP_REPORT_GAS_GAUGE_RELATIVE_WIDTH);
                $radius_set = TRUE;
            }
        }

        if (!$radius_set) {
            //no config info found, so use default size
            $radius = PHP_REPORT_GAS_GAUGE_MAXIMUM_WIDTH;
        }

        if (!empty($radius)) {
            //load up the color palette
            $palette = $this->get_gas_gauge_color_palette();

            //image tag points to a php script that uses the necessary measures are parameters
            return '<img src="' . $CFG->wwwroot . '/blocks/php_report/gas_gauge_output.php?value=' . $this->gas_gauge_value .
                                                                                         '&total=' . $this->gas_gauge_max_value .
                                                                                         '&radius=' . $radius .
                                                                                         '&palette=' . urlencode(base64_encode(serialize($palette))) .
                                                                                         '" class="php_report_gas_gauge_image"/>';
        } else {
            //error, so don't display
            return '';
        }
    }

    /**
     * Print the paging header for the gas gauge
     *
     * @param   int|string     $id  Id of php report element, if applicable
     * @return  string       HTML output for display.
     */
    function print_gas_gauge_header($id = 0) {
        global $CFG;

        $args = '';
        $output = '<div class="clearfix"></div>'; // send out clearfix to make sure next line centers correctly

        //display header entries if applicable
        if ($header_info = $this->get_gas_gauge_header_info()) {
            $i = 1;
            foreach ($header_info as $header_entry) {
                $output .= '<span class="php_report_gas_gauge_header_entry php_report_gas_gauge_header_entry_' . $i . '">' . $header_entry . '</span><br/><hr/>';
                $i++;
            }
        }

        if($id !== 0) {
            $effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $effective_url = $this->baseurl;
        }

        //similar to tabular paging but changes gas gauge page
        $output .= $this->print_paging_bar($this->num_gas_gauge_pages, $this->gas_gauge_page, 1,
                                          "{$effective_url}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                          "perpage={$this->perpage}" . $args . "&amp;", 'gas_gauge_page', false, true, $this->get_gas_gauge_page_label());

        return $output;
    }

    /**
     * Specifies the string used to label the gas-gauge-level pages
     *
     * @return  string  A string to display, or the empty string to use the default label
     */
    function get_gas_gauge_page_label() {
        //use the default, can override in child class
        return '';
    }

    /**
     * Specifies the RGB color palette for gas gauge sections, and
     * implicitly specifies the number of such sections
     *
     * @return  array  An array containing one three-element array
     *                 representing R, G, and B values for each sections
     *                 of the gauge
     */
    function get_gas_gauge_color_palette() {
        //four sections, with colors of red, yellow, yellow and green
        return array(array(255, 0, 0),
                     array(255, 255, 0),
                     array(255, 255, 0),
                     array(0, 255, 0));
    }

    /* ------------------------------------------------------
     * Functions that override the parent class functionality
     * ------------------------------------------------------ */

    /**
     * Sets up the secondary filterings based on the report definitions
     *
     * @param   string  $url               The URL used to dynamically reload this report
     * @param   string  $id                This report's unique identifier
     * @param   string  $report_shortname  The shortname of the configured report
     *
     * @return  array                      Mapping of pre-set filtering keys to filtering objects
     */
    function get_secondary_filterings($url, $id, $report_shortname) {
        $result = array();

        //filtering applied to the current page value
        $result[PHP_REPORT_SECONDARY_FILTERING_PAGE_VALUE] = new php_report_default_capable_filtering($this->get_page_value_filters(), $url, null, $id, $report_shortname);

        //filtering applied to the total number of pages
        $result[PHP_REPORT_SECONDARY_FILTERING_NUM_PAGES] = new php_report_default_capable_filtering($this->get_num_pages_filters(), $url, null, $id, $report_shortname);

        //filtering applied to the current gas gauge value
        $result[PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_VALUE] = new php_report_default_capable_filtering($this->get_gas_gauge_value_filters(), $url, null, $id, $report_shortname);

        //filtering applied to the gas gauge max value
        $result[PHP_REPORT_SECONDARY_FILTERING_GAS_GAUGE_MAX_VALUE] = new php_report_default_capable_filtering($this->get_gas_gauge_max_value_filters(), $url, null, $id, $report_shortname);

        return $result;
    }

    /**
     * Initialize the filter object
     */
    function init_filter($id, $init_data = true) {
        global $CFG;

        if (!isset($this->filter)) {
            //set up our filtering, including references to any secondary filterings involved
            $dynamic_report_filter_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
            $this->filter = new php_report_default_capable_filtering($this->get_filters($init_data), $dynamic_report_filter_url, null, $id, $this->get_report_shortname(), $this->get_secondary_filterings($dynamic_report_filter_url, $id, $this->get_report_shortname()));
        }
    }

    /**
     * Initialize all gas-gauge-related info for this report
     */
    function init_gas_gauge_info() {
        //get the value representing the current page from the paging index of 0 to n-1
        $this->gas_gauge_page_value = $this->get_page_value($this->gas_gauge_page);
        //get total number of pages
        $this->num_gas_gauge_pages = $this->get_num_pages();

        if ($this->num_gas_gauge_pages > 0) {
            //we have top-level pages, so allow us to render the table section
            $this->gas_gauge_value = $this->get_gas_gauge_value($this->gas_gauge_page_value);
            $this->gas_gauge_max_value = $this->get_gas_gauge_max_value($this->gas_gauge_page_value);
        } else {
            //no top-level pages, so we will not render anything
            $this->gas_gauge_page_value = NULL;
            $this->gas_gauge_value = NULL;
            $this->gas_gauge_max_value = NULL;
        }
    }

    /**
     * Initializes all data needed before executing this report
     *
     * @param  int|string     $id              The report identifier
     * @param  stdClass|NULL  $parameter_data  Parameter data manually being set
     */
    function init_all($id, $parameter_data = NULL) {
        //set up filters
        $this->init_filter($id, false);

        //use the provided data to set gas-gauge parameters
        if ($parameter_data !== NULL) {
            $this->filter->set_preferences_source_data($parameter_data);

            //propagate to secondary filters
            if (!empty($this->filter->secondary_filterings) && !empty($this->filter->preferences_source_data)) {
                foreach ($this->filter->secondary_filterings as $key => $value) {
                    $this->filter->secondary_filterings[$key]->set_preferences_source_data($this->filter->preferences_source_data);
                }
            }
        }

        //set up gas gauge info
        $this->init_gas_gauge_info();

        //initialize columns
        $this->init_columns();

        //initialize default sort
        $this->init_sort();

        //initialize groupings
        $this->init_groupings();

        //initialize summary displays
        $this->init_summary();

        //initialize header icon
        $this->init_header();
    }

    /**
     * Main display function.
     *
     * @param  string      $sort      Column to sort on by default
     * @param  string      $dir       Direction to sort on by default ('asc', 'desc', or '')
     * @param  int         $page      Current page (0-indexed)
     * @param  int         $perpage   Records per page
     * @param  string      $download  Format to export in, if applicable
     * @param  int|string  $id        Report / block id
     *
     */
    function main($sort = '', $dir = '', $page = 0, $perpage = 0, $download = '', $id = 0) {
        global $CFG;

        $this->display_header();

        $this->set_paging_and_sorting($page, $perpage, $sort, $dir);

        $this->init_all($id);

        $this->render_report($id);
        
        $this->display_footer();
    }

    /**
     * Generates and renders the report, including the header and footer
     *
     * @param  int|string  $id  The id of the report
     */
    function render_report($id=0) {
        $this->get_data();

        //header link for configuring default parameters
        echo $this->get_config_header();

        //header, including the gas-gauge paging and header text
        echo $this->print_gas_gauge_header($id);
        //actual gas gauge
        echo $this->print_gas_gauge($id);

        //regular tabular report stuff
        echo $this->print_header($id);
        echo $this->display($id);
        echo $this->print_footer($id);
    }

    /**
     * Print the paging headers for the table.
     *
     * @param   int|string  $id  Id of php report element, if applicable
     *
     *
     * @return  string           HTML output for display.
     */
    function print_header($id = 0) {
        global $CFG;

        $output = '';

        $args = '';

        $export_formats = $this->get_export_formats();
        $allowable_export_formats = php_report::get_allowable_export_formats();

        if($id !== 0) {
            $effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $effective_url = $this->baseurl;
        }

        //similar to parent class, but maintains gas gauge page
        $output .= print_paging_bar($this->numrecs, $this->page, $this->perpage,
                                    "{$effective_url}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                    "perpage={$this->perpage}&amp;gas_gauge_page={$this->gas_gauge_page}" . $args . "&amp;", 'page', false, true);

        echo $this->get_interactive_filter_display();

        return $output;
    }

    /**
     * Print the paging footer for the table.
     *
     * @param   int|string  $id  Id of php report element, if applicable
     * @return  string      HTML output for display.
     */
    function print_footer($id = 0) {
        global $CFG;

        $args = '';
        $output = '';

        if($id !== 0) {
            $effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $effective_url = $this->baseurl;
        }

        //similar to parent class, but maintains gas gauge page
        $output .= print_paging_bar($this->numrecs, $this->page, $this->perpage,
                                    "{$effective_url}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                                    "perpage={$this->perpage}&amp;gas_gauge_page={$this->gas_gauge_page}" . $args . "&amp;", 'page', false, true);

        return $output;
    }

    /*
     * Add report title to report
     */
    function print_report_title() {
        /* do nothing here - title is printed elsewhere */
    }

    /**
     * Add relevant header info to the pdf output
     *
     * @param  FPDF  $newpdf  The report pdf we are creating
     */
    public function print_pdf_header($newpdf) {
        global $CFG;

        //initial y position
        $initial_y = $newpdf->getY();

        //determine page with, not including margins
        $effective_page_width = $newpdf->w - $newpdf->lMargin - $newpdf->rMargin;

        //store the original font size
        $old_font_size = $newpdf->FontSizePt;
        //use a large font size for the header info
        $newpdf->setFontSize(12);

        //used to track vertical positioning
        $i = 0;

        //render any appropriate text for each header
        if ($header_info = $this->get_gas_gauge_header_info()) {
            foreach ($header_info as $header_entry) {
                //render across
                $newpdf->Cell($effective_page_width, 0, $header_entry, 0, 0, 'C');

                //draw a line below the text
                $line_top = $initial_y + 0.2 * $i + 0.1;
                $newpdf->Line($newpdf->lMargin, $line_top, $newpdf->lMargin + $effective_page_width, $line_top);

                //add necessary spacing
                $newpdf->Ln(0.2);
                $i++;
            }
        }

        //if the max value is not zero, render the gas gauge
        if ($this->gas_gauge_max_value != 0) {
            //retrieve the color palette as defined by the report
            $palette = $this->get_gas_gauge_color_palette();

            //approximate pixels using points
            $actual_radius = PHP_REPORT_GAS_GAUGE_MAXIMUM_WIDTH / 2 / 72;

            //set up the variables needed by the gas-gauge-generating script

            //current value on the gas gauge
            $passthru_value = $this->gas_gauge_value;

            //maximum value on the gauge
            $passthru_total = $this->gas_gauge_max_value;

            //radius of the gas gauge
            $passthru_radius = PHP_REPORT_GAS_GAUGE_MAXIMUM_WIDTH;

            //colour palette to use (also specifies number of sections)
            $passthru_palette = $palette;

            //indicate that we are persisting the image
            $passthru_persist = 1;

            //filename to save the image to
            $passthru_filename = tempnam($CFG->dataroot . '/temp', 'gas_gauge_');

            //generate the necessary image file
            $gas_gauge_url = $CFG->dirroot . '/blocks/php_report/gas_gauge_output.php';
            require_once($gas_gauge_url);

            //leftmost position of the gas gauge
            $left_position = $newpdf->w / 2 - $actual_radius;

            //vertical offset, based on number of headers
            $top_position = $initial_y + 0.2 * count($header_info) + 0.1;

            //draw the gas gauge and add appropriate vertical space
            $newpdf->Image($passthru_filename, $left_position, $top_position, 2 * $actual_radius, $actual_radius, 'png');
            $newpdf->Ln($actual_radius + 0.2);

            //delete the temporary image file
            unlink($passthru_filename);
        }

        //revert the font size to its initial value
        $newpdf->setFontSize($old_font_size);
    }

    /**
     * Get a single value from a table (allowing for the use of LIMIT clauses).
     *
     * @param   string  $sql         an SQL statement expected to return a single value
     *                               (with a limit clause calculated depending on the database type).
     * @param   int     $limitfrom   return a subset of records, starting at this point (optional).
     *
     * @return  mixed                the specified value, or false if an error occured.
     */
    function get_field_sql($sql, $limitfrom = 0) {
        global $CFG;

        //use the optional starting point
        $rs = get_recordset_sql($sql, $limitfrom, 1);

        if ($rs && $rs->RecordCount() == 1) {
            /// DIRTY HACK to retrieve all the ' ' (1 space) fields converted back
            /// to '' (empty string) for Oracle. It's the only way to work with
            /// all those NOT NULL DEFAULT '' fields until we definetively delete them
            if ($CFG->dbfamily == 'oracle') {
                $value = reset($rs->fields);
                onespace2empty($value);
                return $value;
            }
            /// End of DIRTY HACK
            return reset($rs->fields);
        } else {
            return false;
        }
    }

    /**
     * Prints a single paging bar to provide access to other pages  (usually in a search)
     * (Override core Moodle functionality to add the possibility to change the page label)
     *
     * @param int $totalcount Thetotal number of entries available to be paged through
     * @param int $page The page you are currently viewing
     * @param int $perpage The number of entries that should be shown per page
     * @param mixed $baseurl If this  is a string then it is the url which will be appended with $pagevar, an equals sign and the page number.
     *                          If this is a moodle_url object then the pagevar param will be replaced by the page no, for each page.
     * @param string $pagevar This is the variable name that you use for the page number in your code (ie. 'tablepage', 'blogpage', etc)
     * @param bool $nocurr do not display the current page as a link
     * @param bool $return whether to return an output string or echo now
     * @param string $page_label A label used to override "Page"
     * @return bool or string
     */
    function print_paging_bar($totalcount, $page, $perpage, $baseurl, $pagevar='page',$nocurr=false, $return=false, $page_label='') {
        $maxdisplay = 18;
        $output = '';

        if ($totalcount > $perpage) {
            $tooltip_sql = $this->get_page_tooltip_sql();

            $output .= '<div class="paging">';
            //Start of RL edit
            if (empty($page_label)) {
                //use the default label
                $output .= get_string('page') .':';
            } else {
                //custom label specified
                $output .= $page_label . ':';
            }
            //End of RL edit
            if ($page > 0) {
                $pagenum = $page - 1;
                $alt_title = $this->get_field_sql($tooltip_sql, $pagenum);
                if (!is_a($baseurl, 'moodle_url')){
                    $output .= '&nbsp;(<a class="previous" href="'. $baseurl . $pagevar .'='. $pagenum .'" alt="' . $alt_title . '" title="' . $alt_title . '">'. get_string('previous') .'</a>)&nbsp;';
                } else {
                    $output .= '&nbsp;(<a class="previous" href="'. $baseurl->out(false, array($pagevar => $pagenum)).'" alt="' . $alt_title . '" title="' . $alt_title . '">'. get_string('previous') .'</a>)&nbsp;';
                }
            }
            if ($perpage > 0) {
                $lastpage = ceil($totalcount / $perpage);
            } else {
                $lastpage = 1;
            }
            if ($page > 15) {
                $alt_title = $this->get_field_sql($tooltip_sql, 0);
                $startpage = $page - 10;
                if (!is_a($baseurl, 'moodle_url')){
                    $output .= '&nbsp;<a href="'. $baseurl . $pagevar .'=0" alt="' . $alt_title . '" title="' . $alt_title . '">1</a>&nbsp;...';
                } else {
                    $output .= '&nbsp;<a href="'. $baseurl->out(false, array($pagevar => 0)).'" alt="' . $alt_title . '" title="' . $alt_title . '">1</a>&nbsp;...';
                }
            } else {
                $startpage = 0;
            }
            $currpage = $startpage;
            $displaycount = $displaypage = 0;
            while ($displaycount < $maxdisplay and $currpage < $lastpage) {
                $displaypage = $currpage+1;
                if ($page == $currpage && empty($nocurr)) {
                    $output .= '&nbsp;&nbsp;'. $displaypage;
                } else {
                    $alt_title = $this->get_field_sql($tooltip_sql, $currpage);
                    if (!is_a($baseurl, 'moodle_url')){
                        $output .= '&nbsp;&nbsp;<a href="'. $baseurl . $pagevar .'='. $currpage .'" alt="' . $alt_title . '" title="' . $alt_title . '">'. $displaypage .'</a>';
                    } else {
                        $output .= '&nbsp;&nbsp;<a href="'. $baseurl->out(false, array($pagevar => $currpage)).'" alt="' . $alt_title . '" title="' . $alt_title . '">'. $displaypage .'</a>';
                    }
                }
                $displaycount++;
                $currpage++;
            }
            if ($currpage < $lastpage) {
                $lastpageactual = $lastpage - 1;
                $alt_title = $this->get_field_sql($tooltip_sql, $lastpageactual);
                if (!is_a($baseurl, 'moodle_url')){
                    $output .= '&nbsp;...&nbsp;<a href="'. $baseurl . $pagevar .'='. $lastpageactual .'" alt="' . $alt_title . '" title="' . $alt_title . '">'. $lastpage .'</a>&nbsp;';
                } else {
                    $output .= '&nbsp;...&nbsp;<a href="'. $baseurl->out(false, array($pagevar => $lastpageactual)).'" alt="' . $alt_title . '" title="' . $alt_title . '">'. $lastpage .'</a>&nbsp;';
                }
            }
            $pagenum = $page + 1;
            if ($pagenum != $displaypage) {
                $alt_title = $this->get_field_sql($tooltip_sql, $pagenum);
                if (!is_a($baseurl, 'moodle_url')){
                    $output .= '&nbsp;&nbsp;(<a class="next" href="'. $baseurl . $pagevar .'='. $pagenum .'" alt="' . $alt_title . '" title="' . $alt_title . '">'. get_string('next') .'</a>)';
                } else {
                    $output .= '&nbsp;&nbsp;(<a class="next" href="'. $baseurl->out(false, array($pagevar => $pagenum)) .'" alt="' . $alt_title . '" title="' . $alt_title . '">'. get_string('next') .'</a>)';
                }
            }
            $output .= '</div>';
        }

        if ($return) {
            return $output;
        }

        echo $output;
        return true;
    }

    /**
     * Specifies an SQL condition for matching a field to the current
     * gas gauge page value
     *
     * @param  string   $field    The full fieldname used for filtering in the query
     * @param  boolean  $numeric  If TRUE, treat the specified field as numeric, otherwise
     *                            treat it as a char / text type
     */
    function get_page_value_condition($field, $numeric = TRUE) {
        //handle case where there are no page
        if ($this->gas_gauge_page_value === NULL) {
            return '0 = 1';
        }

        if ($numeric) {
            //numeric field comparison
            return "$field = {$this->gas_gauge_page_value}";
        } else {
            //char / text field comparison
            return "$field = '{$this->gas_gauge_page_value}'";
        }
    }

}

?>
