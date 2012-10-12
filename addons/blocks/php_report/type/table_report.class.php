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
 * TO DO: enable wrapping of table headers
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
 * Class that represents standard tabular reports
 */
abstract class table_report extends php_report {

    var $id;             // INT - Table identifier.
    var $title;          // STRING - The title for this report.
    var $css_identifier; // STRING - Identifier used to form unique CSS class.
    var $type;           // STRING - The type of this report.
    var $table;          // OBJECT - The table object.
    var $columns;        // ARRAY - An array of strings.
    var $headers;        // ARRAY - An array of strings.
    var $align;          // ARRAY - An array of strings.
    var $sortable;       // ARRAY - An array of bools.
    var $wrap;           // ARRAY - An array of bools.
    var $header_wrap;    // ARRAY - An array of bools.
    var $defsort;        // STRING - A column to sort by default.
    var $defdir;         // STRING - The direction to sort the default column by.
    var $data;           // ARRAY - An array of table data.
    var $numrecs;        // INT - The total number of results found.
    var $baseurl;        // STRING - The base URL pointing to this report.
    var $pageurl;        // STRING - The paging URL for this report.
    var $sort;           // STRING - The column to sort by.
    var $dir;            // STRING - The direction of sorting.
    var $page;           // INT - The page number being displayed.
    var $perpage;        // INT - The number of rows per page.
    var $fileformats;    // ARRAY - An array of strings for valid file formats.

    //variables for horizontal bar
    var $ishorizontalbar;     //flags column as a bar
    var $totalcolumn;         //total column name, or a numeric value
    var $displaystring;       //what we want to display on the bar, or empty if nothing
    var $displaypercentsign;  //whether to display percent sign
    var $horizontalbarwidth;  //horizontal bar width
    var $horizontalbarheight; //horizontal bar height

    //header icon
    var $headericon;

    //grouping for reports with multiple sections
    var $groupings;

    //summary / aggregation row displayed at the end of a report
    var $summary_row;

    //listing of which export formats are supported
    var $column_export_formats;

    //object that represent summary information that can show up on a row-by-row basis
    var $column_based_summary_row;

    //constants used for keys when identifying paging-related information
    const offset = 0;
    const limit = 1;

    //constant used to define a token we are replacing with filter sql
    const PARAMETER_TOKEN = "''php_report_parameters''";

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
    function table_report($id, $userid = NULL, $execution_mode = php_report::EXECUTION_MODE_INTERACTIVE) {
        //set up variables in parent class
        parent::__construct($id, $userid, $execution_mode);

        $this->table           = new stdClass;
        $this->columns         = array();
        $this->headers         = array();
        $this->css_identifiers = array();
        $this->align           = array();
        $this->sortable        = array();
        $this->wrap            = array();
        $this->header_wrap     = array();
        $this->defsort         = '';
        $this->defdir          = '';
        $this->data            = array();
        $this->numrecs         = 0;
        $this->baseurl         = '';
        $this->sort            = '';
        $this->dir             = '';
        $this->page            = 0;
        $this->perpage         = 0;
        $this->fileformats     = array();
        $this->effective_url   = '';

        //horizontal bar stuff
        $this->ishorizontalbar = array();
        $this->totalcolumn = array();
        $this->displaystring = array();
        $this->displaypercentsign = array();
        $this->horizontalbarwidth = array();
        $this->horizontalbarheight = array();

        //mapping of column ids to the formats in which they will export
        $this->columnexportformats = array();

        //SQL statement that represents an aggregation on a per-column basis
        $this->aggregation_sql = array();
    }


/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DATA FUNCTIONS:                                                //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Set the array of report columns.
     *
     * @param   string        $id                   The column ID.
     * @param   string        $name                 The textual name displayed for the column header.
     * @param   string        $css_identifier       A unique identifier to represent this element in CSS
     * @param   string        $align                Column alignment ('left', 'center' or 'right').
     * @param   boolean       $sortable             Whether the column is sortable or not.
     * @param   boolean       $wrap                 If set to true the column will automatically wrap.
     * @param   boolean       $header_wrap          If set to true the column header will automatically wrap.
     * @param   string array  $columnexportformats  The list of export formats under which this column will display
     * @param   string        $aggregation_sql      SQL statement that returns a single measure representing a final sum for this column
     * @return  boolean                             True on success, False otherwise.
     */
    function add_column($id, $name, $css_identifier, $align = 'left', $sortable = false, $wrap = true, $header_wrap = true, $columnexportformats = NULL, $aggregation_sql = '') {
        if ($align != 'left' && $align != 'center' && $align != 'right') {
            $align = 'left';
        }

        $this->headers[$id]  = $name;
        $this->css_identifiers[$id] = $css_identifier;
        $this->align[$id]    = $align;
        $this->sortable[$id] = $sortable;
        $this->wrap[$id]     = $wrap;
        $this->header_wrap[$id] = $header_wrap;

        //horizontal bar stuff
        $this->ishorizontalbar[$id] = false;
        $this->totalcolumn[$id] = '';
        $this->displaystring[$id] = '';
        $this->displaypercentsign[$id] = '';
        $this->horizontalbarwidth[$id] = '';
        $this->horizontalbarheight[$id] = '';

        //formats in which this column will display
        if ($columnexportformats !== NULL) {
            $this->columnexportformats[$id] = $columnexportformats;
        } else {
            //use these as a default
            $this->columnexportformats[$id] = array(php_report::$EXPORT_FORMAT_CSV,
                                                    php_report::$EXPORT_FORMAT_EXCEL,
                                                    php_report::$EXPORT_FORMAT_HTML,
                                                    php_report::$EXPORT_FORMAT_PDF);
        }
        $this->aggregation_sql[$id] = $aggregation_sql;
    }

    /**
     * Adds a horizontal bar as an element in the tabular report
     * @param  int      $id                    The name of the database value column
     * @param  string   $name                  The title text
     * @param  string   $css_identifier        A unique identifier to represent this element in CSS
     * @param  string   $total_column          The name of the database total column
     * @param  string   $align                 The column alignment
     * @param  string   $display_string        Templatable display string
     * @param  boolean  $display_percent_sign  Whether to display the percent sign when appropriate
     * @param  int      $width                 Width of the bar
     * @param  int      $height                Height of the bar
     */
    function add_horizontal_bar_column($id, $name, $css_identifier, $total_column, $align = 'left', $display_string = '',
                                       $display_percent_sign = true,
                                       $width=100, $height=20) {
        if ($align != 'left' && $align != 'center' && $align != 'right') {
            $align = 'left';
        }

        $this->headers[$id] = $name;
        $this->css_identifiers[$id] = $css_identifier;
        $this->align[$id] = $align;
        $this->sortable[$id] = false;
        $this->wrap[$id] = true;
        $this->header_wrap[$id] = true;

        //horizontal bar stuff
        $this->ishorizontalbar[$id] = true;
        $this->totalcolumn[$id] = $total_column;
        $this->displaystring[$id] = $display_string;
        $this->displaypercentsign[$id] = $display_percent_sign;
        $this->horizontalbarwidth[$id] = $width;
        $this->horizontalbarheight[$id] = $height;

        //bar graphs only supported in PDF export format
        $this->columnexportformats[$id] = array(php_report::$EXPORT_FORMAT_PDF,
                                                php_report::$EXPORT_FORMAT_HTML);
    }

    /**
     * Set the title of this report (only really used in a PDF download)
     */
    function set_title($title) {
        $this->title = $title;
    }

    /**
     * Creates an object containing a table and column field to
     * represent an SQL field
     *
     * @param   string    $column  Full column reference, possibly including table, field, as and alias
     *
     * @return  stdClass           Object containing a table and column
     */
    private function create_column_object($column) {
        $result = new stdClass;

        $lowercase_column = strtolower(trim($column));

        $as_position = strpos($lowercase_column, ' as ');
        if ($as_position == false) {
            $result->alias = '';
            $rest = $column;
        } else {
            $result->alias = trim(substr($column, $as_position + strlen(' as ')));
            $rest = trim(substr($column, 0, $as_position));
        }

        $dot_position = strpos($rest, '.');
        $parts = explode('.', $rest);
        if (count($parts) == 1) {
            $result->table = '';
            $result->column = $parts[0];
        } else {
            $result->table = $parts[0];
            $result->column = $parts[1];
        }

        return $result;
    }

    /**
     * Determines whether a particular column is sortable
     *
     * @param   string        $column   Column we are testing
     * @param   string array  $headers  Available headers, or use this class's
     *                                  if null
     *
     * @return  boolean                 True if sortable, otherwise false
     */
    private function header_is_sortable($column, $headers = null) {
        $current_column = $this->create_column_object($column);

        if (!empty($headers)) {
            $headers = $headers;
        } else {
            $headers = $this->headers;
        }

        foreach ($headers as $key => $value) {
            $header_column = $this->create_column_object($key);

            /*
             * Logic for matching column to a header
             */
            if (!empty($current_column->alias) && $current_column->alias == $header_column->alias) {
                //aliases match
                return true;
            } else if (!empty($current_column->table) &&
                      $current_column->table == $header_column->table &&
                      $current_column->column == $header_column->column) {
                //tables and columns match
                return true;
            } else if ((empty($current_column->table) || empty($header_column->table)) &&
                      !empty($current_column->column) &&
                      $current_column->column == $header_column->column) {
                //columns match with tables not specified on both sides
                return true;

            } else if (!empty($header_column->alias) &&
                      empty($current_column->alias) &&
                      $header_column->alias == $current_column->column) {
                //definition's alias is referred to as a column in sorting
                return true;
            }
        }

        return false;
    }

    /**
     * Set a column to default sorting.
     *
     * @param string $column The column ID.
     * @param string $dir    The sort direction (ASC, DESC).
     */
    function set_default_sort($column, $dir = 'ASC') {
        if (!$this->header_is_sortable($column)) {
            return false;
        }

        if ($dir != 'ASC' && $dir != 'DESC') {
            $dir = 'ASC';
        }

        $this->defsort = $column;
        $this->defdir  = $dir;

        //remote everything before AS where applicable
        $defsort_lowercase = strtolower($this->defsort);
        $as_position = strpos($defsort_lowercase, ' as ');
        if ($as_position !== false) {
            $this->defsort = substr($this->defsort, $as_position + strlen(' as '));
        }

        //this will change sorting to the default sort order
        //if no sort order is specified
        $this->resolve_sort_preferences();

        return true;
    }

    /**
     * Specifies string of sort columns and direction to
     * order by if no other sorting is taking place (either because
     * manual sorting is disallowed or is not currently being used)
     *
     * @return  string  String specifying columns, and directions if necessary
     */
    function get_static_sort_columns() {
        return '';
    }

    /**
     * Define the base URL for this report.
     *
     * @param string $url The base URL.
     * @return none
     */
    function set_baseurl($url) {
        $this->baseurl = $url;
    }


    /**
     * Define the paging URL for this report.
     *
     * @param string $url The paging URL.
     * @return none
     */
    function set_pageurl($url) {
        $this->pageurl = $url;
    }



/////////////////////////////////////////////////////////////////////
//                                                                 //
//  DISPLAY FUNCTIONS:                                             //
//                                                                 //
/////////////////////////////////////////////////////////////////////

    /**
     * Calculates an HTML paragraph containing the header icon based on the icon set in the constructor
     *
     * @return  string  The paragraph's HTML code
     */
    function print_header_logo() {
        if (!empty($this->headericon)) {
            return '<p><img src="' . $this->headericon . '" style="width: 20%" class="php_report_icon"/></p>';
        }
        return '';
    }

    /**
     * Initializes information used to track groupings as we progress thorugh the report
     *
     * @return  stdClass  Object containing all the necessary information about the current
     *                    status of groupings
     */
    function initialize_groupings() {
        $result = new stdClass;
        $result->grouping_last = array();
        $result->grouping_current = array();
        $result->grouping_first = array();

        if (!empty($this->groupings)) {
            foreach ($this->groupings as $index=>$grouping) {
                //Set up a mask to not display any fields that are part of any column grouping break
                $this->groupings[$index]->mask = array();
                foreach ($this->headers as $key=>$value) {
                    if (in_array($key,$this->groupings[$index]->col_element)) {
                        $this->groupings[$index]->mask[$key] = 1;
                    } else {
                        $this->groupings[$index]->mask[$key] = 0;
                    }
                }

                //nothing is processed, so there is no "last grouping"
                $result->grouping_last[$grouping->field] = false;
                //nothing is processed, so there is no "current grouping"
                $result->grouping_current[$grouping->field] = false;
                //signal that this field has not been used in a grouping yet
                $result->grouping_first[$grouping->field] = true;
            }
        }

        return $result;
    }

    /**
     * Display the table with data.
     *
     * @param  int|string  $id  Id of php report element, if applicable
     */
    function display($id = 0) {
        global $CFG;

        $output = '';

        if ($id !== 0) {
            $this->effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $his->effective_url = $this->baseurl;
        }

        $output .= $this->print_header_logo();

        $output .= $this->print_header_entries();

        //no data section
        if (empty($this->data)) {
            $output .= '<p align="center">' . get_string('no_report_data', 'block_php_report') . '</p>';
            return $output;
        }

        $this->print_report_title();
        $this->generate_column_headers(php_report::$EXPORT_FORMAT_HTML);
        // Multiple level grouping initialization
        $grouping_object = $this->initialize_groupings();

        $this->table->spanrow = array();
        $this->table->firstcolumn = array();
        $this->table->rowclass = array();

        //also looping through $this->summary
        $count = 0;

        foreach ($this->data as $datum) {
            //loop through the summary array to find group and column summaries
            $this_summary = $this->summary[$count];
            $count++;

            // This will also potentially generate table->data[]
            $this->update_groupings($datum, $grouping_object->grouping_last, $grouping_object->grouping_current, $grouping_object->grouping_first);

                        //Initialize spanrow, firstcolumn and rowclass
            $this->table->spanrow[] = false;
            $this->table->firstcolumn[] = false;
            //check for summary row here and either set to summary type or regular table row
            if ($this_summary != null) {
                $this->table->rowclass[] = 'php_report_'.$this_summary;
            } else {
                $this->table->rowclass[] = 'php_report_table_row';
            }


            // Get a row of data
            $this->table->data[] = $this->get_row_content($datum, false,
                                              php_report::$EXPORT_FORMAT_HTML);
        }

        $this->table->width = '100%';

        //theming information
        $this->table->class = 'php_report_table'; //removed generaltable

        //collect the table's HTML
        $output .= $this->print_table($this->table, true);

        return $output;
    }

    /**
     * Generate report headers
     *
     * @param $exportformat  the desired export format, null (default) for any.
     */
    function generate_column_headers($exportformat = null) {
        global $CFG, $OUTPUT;

        foreach ($this->headers as $column => $header) {
            if ($exportformat && !in_array($exportformat,
                                           $this->columnexportformats[$column])) {
                continue;
            }
            $this->table->headercolumncss[] = empty($this->css_identifiers[$column]) ? 'php_report_header_entry' : "php_report_header_entry php_report_column_{$this->css_identifiers[$column]}";
            $this->table->columncss[]       = empty($this->css_identifiers[$column]) ? 'php_report_body_entry' :   "php_report_body_entry   php_report_column_{$this->css_identifiers[$column]}";

            if ($this->sortable[$column]) {

                //remove everything before AS where applicable
                $lowercase_column = strtolower($column);
                $as_position = strpos($lowercase_column, ' as ');
                if ($as_position === FALSE) {
                    $effective_column = $column;
                } else {
                    $effective_column = substr($column, $as_position + strlen(' as '));
                }

                //handle sort orders
                if (!$this->header_is_sortable($this->sort, array($column => $header))) {
                    $columnicon = "";
                    $columndir = "ASC";
                } else {
                    $columndir  = $this->dir == "ASC" ? "DESC":"ASC";
                    $columnicon = $this->dir == "ASC" ? "t/down":"t/up";
                    $columnicon = " <img src=\"".$OUTPUT->pix_url($columnicon)."'\" alt=\"\" />";
                }
                $args = '&amp;sort='. $column .'&amp;dir='. $columndir;
                if (isset($this->page)) {
                    $args .= '&amp;page='. $this->page;
                }
                if (!empty($this->perpage)) {
                    $args .= '&amp;perpage='. $this->perpage;
                }
                $column_text = '<a href="'. $this->effective_url . $args .'">'. $header .'</a>'. $columnicon;
            } else {
                $column_text = $header;
            }

            $this->table->head[]        = $column_text;
            $this->table->align[]       = $this->align[$column];
            $this->table->wrap[]        = $this->wrap[$column];
            $this->table->header_wrap[] = $this->header_wrap[$column];
        }
    }

    /**
     * Adds a grouping row to the table belonging to this report
     *
     * @param  mixed    $data         Row contents
     * @param  boolean  $spanrow      If TRUE, spanning row without forcing column header directly after it
     * @param  boolean  $firstcolumn  If TRUE, spanning row, force column header directly after it
     * @param  string   $rowclass     CSS class to apply to the table row
     */
    function add_grouping_table_row($data, $spanrow, $firstcolumn, $rowclass, $currentkey) {
        $this->table->data[] = $data;
        $this->table->spanrow[] = $spanrow;
        $this->table->firstcolumn[] = $firstcolumn;
        //add a class for the current group level
        $this->table->rowclass[] = $rowclass.get_string('group_class_id', 'block_php_report').$currentkey;
    }

    /**
     * Determines the topmost grouping that has changed and returns a key that identifies it
     *
     * @param   array     $grouping_first  Mapping of column identifiers to the value true if they've
     *                                     not been processed yet, or false if they have
     * @param   stdClass  $datum           The current record being processed, in a raw (unformatted) form
     * @param   array     $grouping_last   Mapping of column identifiers to the value representing them
     *                                     in the last grouping change
     *
     * @return  string                     Value by which the topmost grouping is identified
     */
    function get_grouping_topmost_key($grouping_first, $datum, $grouping_last) {
        //index to store the a reference to the topmost grouping entry that needs to be displayed
        $topmost_key = NULL;

        foreach ($this->groupings as $index => $grouping) {
            //grab the id used to refer to the data we are keying on for this particular grouping
            $grouping_effective_id = $grouping->id;

            //check if the corresponding data has changed
            if ($grouping_first[$grouping->field] || $datum->$grouping_effective_id != $grouping_last[$grouping->field]) {
                $topmost_key = $index;
                //found one, so we are done
                break;
            }
        }

        return $topmost_key;
    }

    /**
     * Removes irrelevant column entries from a record representing a
     * column-based header entry
     *
     * @param   stdClass  $datum        The current report record
     * @param   stdClass  $grouping     Object containing the specification of the current grouping
     *                                  being evaluated
     * @param   stdClass  $datum_group  The object representing the grouping row, to be updated with any
     *                                  necessary changes
     *
     * @return  boolean                 true if a qualifying grouping entry was found,
     *                                  othwerwise false
     */
    function clean_header_entry(&$datum, $grouping, &$datum_group) {
        $grouping_row = false;

        // Loop through each data key
        foreach ($datum as $key=>$datum_value) {
            //If we have any col_elements, create an array of elements that match the short names
            //datum_group will only include the elements it is grouping on
            if (is_array($grouping->col_element) && !empty($grouping->col_element)) {
                $grouping_row = true;

                //loop through col_element array to collect info about
                //the defined columns
                foreach ($grouping->col_element as $col_element) {
                    $simple_col_element[] = $this->get_object_index($col_element);
                }

                //clear out group fields that are not relevant
                if (!in_array($key, $simple_col_element)) {
                    $datum_group->$key = '';
                }
            }
        }

        return $grouping_row;
    }

    /**
     * Updates the state of all the information used to track current grouping statuses
     *
     * @param  stdClass  $grouping          Object containing information about the grouping
     *                                      being evaluated
     * @param  array     $grouping_first    Mapping of column identifiers to the value true if they've
     *                                      not been processed yet, or false if they have
     * @param  array     $grouping_current  Mapping of column identifiers to the value representing them
     *                                      in the current grouping state
     * @param  array     $grouping_last     Mapping of column identifiers to the value representing them
     *                                      in the last grouping change
     */
    function update_groupings_after_iteration($grouping, &$grouping_first, &$grouping_current, &$grouping_last) {
        //no longer on the first grouping
        $grouping_first[$grouping->field] = false;
        //store the last grouping for future comparison
        $grouping_last[$grouping->field] = $grouping_current[$grouping->field];
    }

    /**
     * Updates the current grouping element based on report data
     *
     * @param  stdClass  $grouping          Object containing information about the grouping
     *                                      being evaluated
     * @param  stdClass  $datum             The current (unformatted) row of report data
     * @param  stdClass  $grouping_current  Mapping of column identifiers to the value representing them
     *                                      in the current grouping state
     */
    function update_current_grouping($grouping, $datum, &$grouping_current) {
        //field to key on
        $grouping_effective_id = $grouping->id;
        if (!empty($datum->$grouping_effective_id)) {
            //set the current record value in the grouping
            $grouping_current[$grouping->field] = $datum->$grouping_effective_id;
        } else {
            $grouping_current[$grouping->field] = false;
        }
    }

    /**
     * Performs all calculations / actions to process additional information based on report
     * groupings
     *
     * @param   stdClass  $datum              The current (unformatted) row of report data
     * @param   array     $grouping_last      Mapping of column identifiers to the value representing them
     *                                        in the last grouping change
     * @param   array     $gropuing_current   Mapping of column identifiers to the value representing them
     *                                        in the current grouping state
     * @param   array     $grouping_first     Mapping of column identifiers to the value true if they've
     *                                        not been processed yet, or false if they have
     * @return  none
     */
    function update_groupings(&$datum, &$grouping_last, &$grouping_current, &$grouping_first) {

        //make sure groupings are set up
        if (!empty($this->groupings) && ! (is_array($datum) && (strtolower($datum[0]) == 'hr'))) {

            //index to store the a reference to the topmost grouping entry that needs to be displayed
            $topmost_key = $this->get_grouping_topmost_key($grouping_first, $datum, $grouping_last);


            //make sure something actually changed
            if ($topmost_key !== NULL) {


                //keep track of what level we are on, starting with topmost_key and incrementing it
                //after each add_grouping_table_row call
                //$current_key = $topmost_key;

                //go through only the headers that actually matter
                for ($index = $topmost_key; $index < count($this->groupings); $index++) {
                    $grouping = $this->groupings[$index];

                    //set the information in the current grouping based on our report row
                    $this->update_current_grouping($grouping, $datum, $grouping_current);

                    // Handle grouping changes
                    if ($grouping->position == 'below') {
                        //Make a copy of this row datum to be modified for printing group header inline
                        $datum_group = clone($datum);

                        //remove any unnecessary entries from the header row
                        $grouping_row = $this->clean_header_entry($datum, $grouping, $datum_group);

                        if ($grouping_row) {
                            //"Below" position with per-column data
                            $datum_group_copy = clone($datum_group);
                            $datum_group_copy = $this->transform_grouping_header_record($datum_group_copy, $datum, php_report::$EXPORT_FORMAT_HTML);
                            $grouping_display_text = $this->get_row_content($datum_group_copy, $grouping_row, php_report::$EXPORT_FORMAT_HTML);
                            $this->add_grouping_table_row($grouping_display_text, false, false, 'php_report_table_row',$index);
                            //$current_key++;
                        } else {
                            //"Below" position without per-column data
                            $headers = $this->transform_grouping_header_label($grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_HTML);

                            //add all headers to the table output
                            if (count($headers) > 0) {
                                foreach ($headers as $header) {
                                    $grouping_display_text = array('<span style="font-weight: bold">' . $header . '</span>');
                                    $this->add_grouping_table_row($grouping_display_text, true, false, '',$index);
                                    //$current_key++;
                                }
                            }
                        }

                    } else {
                        //"Above" position without per-column data (single label and value)
                        $headers = $this->transform_grouping_header_label($grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_HTML);

                        //add all headers to the table output
                        if (count($headers) > 0) {
                            foreach ($headers as $header) {
                                $grouping_display_text = array('<span style="font-weight: bold">' . $header . '</span>');
                                $this->add_grouping_table_row($grouping_display_text, false, true, '',$index);
                                //$current_key++;
                            }
                        }

                    }

                    //move on to the next entry
                    $this->update_groupings_after_iteration($grouping, $grouping_first, $grouping_current, $grouping_last);
                }
            }
        }
    }

    /**
     * Transforms a heading element displayed above the columns into a listing of such heading elements
     *
     * @param   string array           $grouping_current  Mapping of field names to current values in the grouping
     * @param   table_report_grouping  $grouping          Object containing all info about the current level of grouping
     *                                                    being handled
     * @param   stdClass               $datum             The most recent record encountered
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  string array                              Set of text entries to display
     */
    function transform_grouping_header_label($grouping_current, $grouping, $datum, $export_format) {
        //show the report-specific label and the current grouping value
        return array($this->add_grouping_header($grouping->label,
                                $grouping_current[$grouping->field],
                                $export_format));
    }

    /**
     * Add an addtional heading element to be displayed above the columns ...
     * to be called in: transform_grouping_header_label()
     * eg.
     * $myresult[] = $this->add_grouping_header($label, $data, $export_format);
     *
     * @param   string    $label          the heading label
     * @param   string    $data           the data for heading label
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  string    the requested header depending on $export_format
     */
    function add_grouping_header($label, $data, $export_format) {
        //show the report-specific label and the current grouping value
        if ($export_format == php_report::$EXPORT_FORMAT_LABEL) {
            return rtrim($label, ': ');
        }

        if ($export_format == php_report::$EXPORT_FORMAT_CSV) {
            return $data;
        }

        return $label . $data;
    }

    /**
     * Transforms a column-based header entry into the form required by the report
     *
     * @param   stdClass  $element        The record representing the current grouping row
     *                                    (including only fields that are part of that grouping row)
     * @param   stdClass  $datum          The record representing the current report row
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The current grouping row, in its final state
     */
    function transform_grouping_header_record($element, $datum, $export_format) {
        return $element;
    }

    /**
     * Generates a row of data
     * @params  $datum  array of current data for this row
     * @return  $row    array of output
     */
    function get_row_content($datum, $grouping_row=false, $export_format=null) {
        global $CFG;

        $row = array();

        if (is_array($datum) && (strtolower($datum[0]) == 'hr')) {
            $row = 'hr';
        } else {
            foreach ($this->headers as $id => $header) {
                if ($export_format && !in_array($export_format,
                                                $this->columnexportformats[$id])) {
                    continue;
                }

                //remove everything before AS where applicable
                $effective_id = $this->get_object_index($id);

                //hide any masked fields
                $found = false;
                if (is_array($this->groupings)) {
                    foreach ($this->groupings as $index=>$value) {
                        if ($this->groupings[$index]->mask[$id]) {
                            $found = true;
                        }
                    }
                }
                if ($found && $grouping_row == false) {
                    $row[$effective_id] = '';
                    continue;
                }

                if (!empty($this->ishorizontalbar[$id])) {
                    //horizontal bar image
                    if (is_null($datum->$effective_id)) {
                        $row[$effective_id] = '';
                    } else {
                        if (is_numeric($this->totalcolumn[$id])) {
                            $total_value = $this->totalcolumn[$id];
                        } else {
                            $total_value_column = $this->get_object_index($this->totalcolumn[$id]);
                            $total_value = $datum->{$total_value_column};
                        }
                        $parameters = array('value=' . $datum->$effective_id,
                                            'total=' . $total_value,
                                            'displaytext=' . urlencode($this->displaystring[$id]));
                        if (!empty($this->displaypercentsign)) {
                            $parameters[] = 'displaypercentsign=' . $this->displaypercentsign[$id];
                        }
                        if (!empty($this->horizontalbarwidth[$id])) {
                            $parameters[] = 'width=' . $this->horizontalbarwidth[$id];
                        }
                        if (!empty($this->horizontalbarheight[$id])) {
                            $parameters[] = 'height=' . $this->horizontalbarheight[$id];
                        }
                        if ($export_format == php_report::$EXPORT_FORMAT_PDF) {
                            $row[$effective_id] = php_report::$EXPORT_FORMAT_PDF_BAR . implode('&', $parameters);
                        } else {
                            $row[$effective_id] = '<img src="' . $CFG->wwwroot . '/blocks/php_report/horizontalbar.php?' . implode('&', $parameters) . '">';
                        }
                    }
                } else {
                    //standard tabular data
                    if (isset($datum->$effective_id)) {
                        $row[$effective_id] = $datum->$effective_id;
                    } else {
                        $row[$effective_id] = '';
                    }
                }
            }
        }
        return $row;
    }
    /**
     * Converts a column name to an object property
     *
     * @param   $id  string  The id to convert
     * @return       string  The converted property name
     */
    static function get_object_index($id) {
        $lowercase_id = strtolower($id);
        $as_position = strpos($lowercase_id, ' as ');
        if ($as_position === FALSE) {
            $effective_id = $id;

            //only use column name portion
            $dot_position = strpos($effective_id, '.');
            if ($dot_position !== FALSE) {
                $effective_id = substr($effective_id, $dot_position + strlen('.'));
            }
        } else {
            $effective_id = substr($id, $as_position + strlen(' as '));
        }

        return $effective_id;
    }

    /*
     * Add report title to report
     */
    function print_report_title() {
        echo '<div class="php_report_title">'.php_report::get_display_name().'</div>';
    }

    /**
     * Add relevant header info to the pdf output
     *
     * @param  FPDF  $newpdf  The report pdf we are creating
     */
    function print_pdf_header($newpdf) {
        //do nothing, but allow the child class to override this
    }

    /**
     * Normalize column widths
     *
     * @param  float array  $widths       Array of column widths, in pixels
     * @param  int          $total_width  Total width available
     */
    function normalize_widths(&$widths, $total_width) {
        //number of columns in the table
        $num_columns = count($widths);

        //calculate the sum of column widths
        $width_sum = 0;
        foreach ($widths as $width) {
            $width_sum += $width;
        }

        //we want to truncate anything larger than what the average column widths
        //to prevent overly wide columns from "squishing" the layout
        $max_width = $width_sum / $num_columns;

        //this will contain the total width after truncation
        $new_width_sum = $width_sum;

        foreach ($widths as $i => $width) {
            if ($width > $max_width) {
                //too wide, so truncate

                //reduce total width by the difference
                $new_width_sum -= ($width - $max_width);
                //truncate this element to the maximum allowable value
                $widths[$i] = $max_width;
            }
        }

        foreach ($widths as $i => $width) {
            //scale widths so that they fill the page
            $widths[$i] = $width * $total_width / $new_width_sum;
        }
    }

    /**
     * Get the data needed for a downloadable version of the report (all data,
     * no paging necessary) and format it accordingly for the download file
     * type.
     *
     * NOTE: It is expected that the valid format types will be overridden in
     * an extended report class as the array is empty by default.
     *
     * @param  string  $format  A valid format type.
     * @param  string  $query   The report query, with no limit clauses
     * @param  array   $params  SQL query parameters
     */
    function download($format, $query, $params, $storage_path = NULL) {
        global $CFG;

        $output = '';

        $filename = !empty($this->title) ? $this->title : 'report_download';

        switch ($format) {
            case php_report::$EXPORT_FORMAT_CSV:
                require_once($CFG->dirroot . '/blocks/php_report/export/php_report_export_csv.class.php');
                $export = new php_report_export_csv($this);
                $export->export($query, $params, $storage_path, $filename);
                break;

            case php_report::$EXPORT_FORMAT_EXCEL:
                require_once($CFG->dirroot . '/blocks/php_report/export/php_report_export_excel.class.php');
                $export = new php_report_export_excel($this);
                $export->export($query, $params, $storage_path, $filename);
                break;

            case php_report::$EXPORT_FORMAT_PDF:
                require_once($CFG->dirroot . '/blocks/php_report/export/php_report_export_pdf.class.php');
                $export = new php_report_export_pdf($this);
                $export->export($query, $params, $storage_path, $filename);
                break;

            default:
                return $output;
                break;
        }
    }


    /**
     * Makes a string safe for CSV output.
     *
     * Replaces unsafe characters with whitespace and escapes
     * double-quotes within a column value.
     *
     * @param   string   $input  The input string.
     *
     * @return  string           A CSV export 'safe' string.
     */
    function csv_escape_string($input) {
        $input = ereg_replace("[\r\n\t]", ' ', $input);
        $input = ereg_replace('"', '""', $input);
        $input = '"' . $input . '"';

        return $input;
    }

    /**
     * Print the paging headers for the table.
     *
     * @param   int|string  $id  Id of php report element, if applicable
     *
     * @return  string           HTML output for display.
     */
    function print_header($id = 0) {
        global $CFG, $OUTPUT;

        $output = '';

        $args = '';

        $export_formats = $this->get_export_formats();
        $allowable_export_formats = php_report::get_allowable_export_formats();

        if ($id !== 0) {
            $effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $effective_url = $this->baseurl;
        }

        $effective_url = "{$effective_url}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                         "perpage={$this->perpage}&amp;";
        $this->print_paging_bar($this->numrecs, $this->page, $this->perpage, $effective_url);

        //show available and active filters, if applicable
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
        global $CFG, $OUTPUT;

        $args = '';
        $output = '';

        if ($id !== 0) {
            $effective_url = $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id;
        } else {
            $effective_url = $this->baseurl;
        }

        $effective_url = "{$effective_url}&amp;sort={$this->sort}&amp;dir={$this->dir}&amp;" .
                         "perpage={$this->perpage}&amp;";
        $this->print_paging_bar($this->numrecs, $this->page, $this->perpage, $effective_url);
        echo '<br/>'; // TBD: spacing

        return $output;
    }

    /**
     * Initialize the report's columns based on the columns as defined in the child class
     */
    function init_columns() {
        //got columns from API
        if ($columns = $this->get_columns()) {
            //actually add them to the report
            foreach ($columns as $column) {
                $column->add_to_report($this);
            }
        }
    }

    /**
     * Initialize the report's default sort column and order based on the implementation
     * in the child class
     */
    //initialize sorting preferences
    function init_sort() {
        //set the default sort column and order based on the child class's implementation
        $this->set_default_sort($this->get_default_sort_field(), $this->get_default_sort_order());
    }

    /**
     * Initialize report groupings based on the child class implementation
     */
    function init_groupings() {
        // Multiple level grouping
        $grouping_fields = $this->get_grouping_fields();
        if (!empty($grouping_fields)) {
            $this->set_groupings($grouping_fields);
        }
    }

    /**
     * Initialize various types of summary entries based on the child class implementation
     */
    function init_summary() {
        $summary_field = $this->get_summary_field();
        if (!empty($summary_field)) {
            //simple one-field summary type
            $summary_field_displayname = $this->get_summary_field_displayname();
            $this->set_summary_row(new table_report_summary_row($summary_field, $summary_field_displayname));
        }

        //multi-column summary entry
        //create an object that stores the listing of all necessary columns and their
        //aggregate SQL statements
        $this->column_based_summary_row = new table_report_column_based_summary_row($this->aggregation_sql);
    }

    /**
     * Initialize the report header (icon)
     */
    function init_header() {
        global $CFG;

        //check the API for a defined icon
        if ($header_icon = $this->get_preferred_header_icon()) {

            //convert to file path to check existence
            $header_icon_file = $header_icon;
            if (strpos($header_icon_file, $CFG->wwwroot) == 0) {
                $header_icon_file = $CFG->dirroot . substr($header_icon_file, strlen($CFG->wwwroot));
            }

            if (file_exists($header_icon_file)) {
                //file exists, so OK to use
                $this->set_header_icon($header_icon);
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

        //use the provided data to set gas-gauge parameters
        if ($parameter_data !== NULL) {
            $this->filter->set_preferences_source_data($parameter_data);
        }

        //initialize report to use defined columns
        $this->init_columns();

        //initialize default sorting
        $this->init_sort();

        //initialize defined groupings
        $this->init_groupings();

        //initialize summary information
        $this->init_summary();

        //initialize report header icon
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
     * Method that specifies the report's columns
     *
     * @return  table_report_column array  The list of report columns
     */
    abstract function get_columns();

    /**
     * Method that specifies fields to group the results by (header displayed when these fields change)
     *
     * @return  array List of associative arrays containing grouping field names, display labels and sort direction
     */
     function get_grouping_fields() {
         return array();
     }

    /**
     * Specifies a field to sort by default
     *
     * @return  string  The name of the report field to sort on by default, or '' if none
     */
    function get_default_sort_field() {
        return '';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @return  string  Sort direction for default sort column, or '' if not used
     */
    function get_default_sort_order() {
        return '';
    }

    /**
     * Specifies a header icon image
     *
     * @return  string  Full path to header icon (should be using pixpath)
     */
    function get_preferred_header_icon() {
        return '';
    }

    /**
     * Print the paging footer for the table.
     *
     * @return string Column section of query based on the columns already
     *                       registered via add_column
     */
    function get_select_columns() {

        $list = array();

        foreach ($this->headers as $key => $value) {
            $list[] = $key;
        }

        //add fields we need for totals for horizontal bar graphs
        foreach ($this->ishorizontalbar as $key => $value) {
            if (!empty($value) && !is_numeric($this->totalcolumn[$key])) {
                $list[] = $this->totalcolumn[$key];
            }
        }

        //add field for the grand total if applicable
        if (!empty($this->summary_row->field)) {
            $list[] = $this->summary_row->field;
        }

        //add fields for multi-level grouping
        if (!empty($this->groupings)) {
            foreach ($this->groupings as $grouping) {
                $list[] = $grouping->field . " as " . $grouping->id;
            }
        }

        return implode(',', $list);
    }

    /**
     * Calculates the entirety of the SQL condition created by report filters
     * for the current report instance being execute, including the leading AND or WHERE token
     *
     * @param   string  $conditional_symbol  the leading token (should be AND or WHERE)
     *
     * @return  array                        the appropriate SQL condition, and the sql
     *                                       filter information
     */
    function get_filter_condition($conditional_symbol) {
        $sql = '';
        $params = array();

        //error checking
        if (!empty($this->filter)) {
            //run the calculation
            list($additional_sql, $additional_params) = $this->filter->get_sql_filter('', array(), $this->allow_interactive_filters(), $this->allow_configured_filters());
            if (!empty($additional_sql)) {
                //one or more filters are active
                $sql .= " {$conditional_symbol} ({$additional_sql})";
                $params = array_merge($params, $additional_params);
            }
        }

        return array($sql, $params);
    }

    /**
     * Constructs an appropriate order by clause for the main query
     *
     * @return  string  The appropriate order by clause
     */
    function get_order_by_clause() {
        $result = '';

        // Multiple level groupings
        if (!empty($this->groupings)) {
            $order_array = array();
            foreach ($this->groupings as $index=>$grouping) {
                if ($grouping->sortfield !== NULL) {
                    $order_array[] = $grouping->sortfield;
                } else {
                    $order_array[] = $grouping->field.' '.$grouping->order;
                }
            }
            $result = " ORDER BY ".implode(',',$order_array);
        }

        if (!empty($this->sort) && !empty($this->dir)) {
            $sort = $this->sort;
            $lowercase_sort = strtolower($sort);
            $as_position = strpos($lowercase_sort, ' as ');
            if ($as_position !== false) {
                $sort = substr($sort, $as_position + strlen(' as '));
            }
            if (!empty($result)) {
                $result .= ", {$sort} {$this->dir}";
            } else {
                $result = " ORDER BY {$sort} {$this->dir}";
            }
        }

        if ($static_sort = $this->get_static_sort_columns()) {
            if (empty($result)) {
                $result = " ORDER BY {$static_sort}";
            }
        }

        return $result;
    }

    /**
     * Specifies information about the paging structure of this report instance
     *
     * @return  array  An array containing the paging values used
     */
    function get_paging_information() {
        //default to the default paging values
        $result = array(table_report::offset => NULL,
                        table_report::limit => NULL);

        if (isset($this->page) && isset($this->perpage)) {
            ///rendering on a page, so use limits

            //calculate offset
            $offset = $this->page * $this->perpage;

            //final result
            $result = array(table_report::offset => $offset,
                            table_report::limit => $this->perpage);
        }
        return $result;
    }

    /**
     * Sets sort preferences to the defaults when necessary
     */
    function resolve_sort_preferences() {
        if (empty($this->sort) && !empty($this->defsort)) {
            $this->sort = $this->defsort;
            $this->dir = $this->defdir;
        }
    }

    /**
     * Set the number of records in the result set
     * based on a report query
     *
     * @param   string  $sql            The report sql without a limit clause added
     * @param   array   $params         SQL query params to apply
     * @param   string  $wrapper_alias  A unique alias for the results from $sql
     *
     * @return  int                     The number of records in the result set
     */
    function set_num_recs($sql, $params, $wrapper_alias = 'count_items') {
        global $DB;

        $count_sql = "SELECT COUNT(*) FROM (
                      {$sql}) {$wrapper_alias}";
        $this->numrecs = $DB->count_records_sql($count_sql, $params);
    }

    /**
     * Calculates the entire SQL query, including changes made
     * by the report engine
     *
     * @param   boolean  $use_limit  true if the paging-based limit clause should be included, otherwise false
     * @param   string   $sql        Fixed sql to replace parameters with - if null, obtain from report definition
     * @param   array    $params     the SQL query parameters
     * @return  array                The SQL query, and the appropriate sql filter information
     */
    function get_complete_sql_query($use_limit = true, $sql = null, $params = array()) {
        $columns = $this->get_select_columns();

        //used to track whether we're in the main report flow or not
        $in_main_report_flow = false;

        //query from the report implementation
        if ($sql === null) {
            list($sql, $params) = $this->get_report_sql($columns);
            $in_main_report_flow = true;
        }

        //determine if the special wildcard for adding filter sql is included
        $parameter_token_pos = strpos($sql, table_report::PARAMETER_TOKEN);
        if ($parameter_token_pos === false) {
            //no wildcard, so add filter sql to the end

            //determine if we need an add or where clause
            $has_where_clause = php_report::sql_has_where_clause($sql);

            $conditional_symbol = 'WHERE';
            if ($has_where_clause) {
                $conditional_symbol = 'AND';
            }

            //add filter sql
            list($additional_sql, $additional_params) = $this->get_filter_condition($conditional_symbol);
            $sql .= $additional_sql;
            $params = array_merge($params, $additional_params);
        } else {
            //wildcard, so do a find and replace
            //get the filter clause without adding WHERE or AND - it's up to the
            //report to include those in this case because parsing pieces of queries
            //is complex and error-prone
            list($filter_clause, $filter_params) = $this->get_filter_condition('');
            if (empty($filter_clause)) {
                $filter_clause = 'TRUE';
            }

            //replace the wildcard with the filter clause
            $sql = str_replace(table_report::PARAMETER_TOKEN, $filter_clause, $sql);
            // Check for duplicate named parameters
            foreach ($filter_params as $key => $value) {
                if (substr_count($sql, ":{$key}") > 1) {
                    $cnt = 0;
                    $sql_parts = explode(":{$key}", $sql);
                    foreach($sql_parts as $sql_part) {
                        if ($cnt++) {
                            $newkey = ($cnt == 1) ? $key : "{$key}_{$cnt}";
                            $new_sql .= ":{$newkey}{$sql_part}";
                            $filter_params[$newkey] = $value;
                        } else {
                            $new_sql = $sql_part;
                        }
                    }
                    $sql = $new_sql;
                }
            }
            $params = array_merge($params, $filter_params);
        }

        if ($in_main_report_flow) {
            //grouping
            $groups = $this->get_report_sql_groups();
            if (!empty($groups)) {
                $sql .= " GROUP BY {$groups}";
            }

            $this->set_num_recs($sql, $params);

            //ordering
            $sql .= $this->get_order_by_clause();
        }
        return array($sql, $params);
    }

    /**
     * Compare records to see if any grouping has changed
     * @return  boolean   true if any grouping has changed,
     *                    false otherwise.
     */
    function any_group_will_change($currecord, $nextrecord) {
        if (!empty($this->groupings)) {
            foreach ($this->groupings as $grouping) {
                if ($currecord->{$grouping->id} != $nextrecord->{$grouping->id})
                {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Sets the report data, applying filters and sorting
     *
     */
    function get_data() {
        global $CFG, $DB;

        list($sql, $params) = $this->get_complete_sql_query();

        $first_object = null;

        //obtain the report's pagining information
        $paging_info = $this->get_paging_information();
        $offset = $paging_info[table_report::offset];
        $limit = $paging_info[table_report::limit];

        if ($report_results = $DB->get_recordset_sql($sql, $params, $offset, $limit) and
           $report_results->valid()) {
            $this->data = array();
            $this->summary = array();
            $report_result = $report_results->current();
            while($report_result) {
                $last_result = clone($report_result); // save before transform!
                //clone the object because transform_record will alter it in-place
                $this->data[] = $this->transform_record(clone($report_result), php_report::$EXPORT_FORMAT_HTML);
                $this->summary[] = null;
                if (is_null($first_object)) {
                    $first_object = $this->transform_record($report_result, php_report::$EXPORT_FORMAT_HTML);
                }

                //move on to the next record
                $report_results->next();
                //fetch the current record
                $report_result = $report_results->current();
                if (!$report_results->valid()) {
                    //make sure the current record is a valid one
                    $report_result = false;
                }

                if ($this->requires_group_column_summary()) {
                    if ((!$report_result ||
                         $this->any_group_will_change($last_result,
                                                     $report_result)) &&
                        ($grpcolsum = $this->transform_group_column_summary(
                                          $last_result, $report_result,
                                          php_report::$EXPORT_FORMAT_HTML))) {
                        $this->data[] = $grpcolsum;
                        $this->summary[] = 'group_summary';
                    }
                }
            }
        } else {
            $this->data = array();
            $this->summary = array();
        }

        $max_page = ceil($this->numrecs / $this->perpage) - 1;
        if ($this->page == $max_page) {

            //use the column structure to create a summary record
            $column_based_summary_row = $this->column_based_summary_row->get_row_object(array_keys($this->headers));
            //use the special hook to perform any data manipulation needed
            $column_based_summary_row = $this->transform_column_summary_record($column_based_summary_row);

            //add the summary row, if applicable
            if ($column_based_summary_row !== null) {
                $this->data[] = $column_based_summary_row;
                $this->summary[] = 'column_summary';
            }

            $position = 0;
            $row_object = new stdClass;

            $summary_field = $this->get_summary_field();
            $summary_field = $this->get_object_index($summary_field);

            if (!empty($summary_field) && !is_null($first_object)) {

                foreach ($this->headers as $id => $header) {
                    //remove everything before AS where applicable
                    $effective_id = $this->get_object_index($id);

                    if ($position == 0) {
                        $row_object->{$effective_id} = '<span class="rlreport_summary_field_displayname">' . $this->get_summary_field_displayname() . '</span>';
                    } else if ($position == 1) {
                        $row_object->{$effective_id} = '<span class="rlreport_summary_field_value">' . $first_object->{$summary_field} . '</span>';
                    } else {
                        $row_object->{$effective_id} = null;
                    }

                    $position++;
                }

                $this->data[] = $row_object;
                $this->summary[] = null;

            }

        }

    }

    /**
     * Method to be implemented, which should return
     * the report's main SQL statement
     *
     * @param   array  $columns  The list of columns automatically calculated
     *                           by get_select_columns()
     * @return  array            The report's main sql statement, as well as the
     *                           applicable SQL parameters
     */
    abstract function get_report_sql($columns);

    /**
     * Specifies the fields to group by in the report
     * (needed so we can wedge filter conditions in after the main query)
     *
     * @return  string  Comma-separated list of columns to group by,
     *                  or '' if no grouping should be used
     */
    function get_report_sql_groups() {
        return '';
    }

    /**
     * Specifies a field whose value is used as an end-of-report
     * summary row (requires main query to aggregate in this field)
     *
     * @return  string  Field name
     */
    function get_summary_field() {
        return '';
    }

    /**
     * If a summary field is enabled, this method is used to specify
     * the descriptive label for the calulated aggregate value
     *
     * @return  string  The label's display text
     */
    function get_summary_field_displayname() {
        return '';
    }

    /**
     * Sets up the paging and sorting
     *
     * @param  int     $page     The page to display (zero-based)
     * @param  int     $perpage  Number of records to display per page
     * @param  string  $sort     The column to sort on
     * @param  string  $dir      The sorting direction (typically ASC or DESC if sorting)
     */
    function set_paging_and_sorting($page, $perpage, $sort, $dir) {
        $this->page = $page;
        $this->perpage = $perpage;
        $this->sort = $sort;
        $this->dir = $dir;
    }

    /**
     * Generates and renders the report, including the header and footer
     *
     * @param  int|string  $id  The id of the report
     */
    function render_report($id = 0) {
        $this->get_data();

        //header link for configuring default parameters
        echo $this->get_config_header();
        echo $this->print_header($id);
        echo $this->display($id);
        echo $this->print_footer($id);
    }

    /**
     * Takes a record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {
        return $record;
    }

    /**
     * Takes a summary row record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @return stdClass  The reformatted record
     */
    function transform_column_summary_record($record) {
        return $record;
    }

    /**
     * This method is set up as a hook to be implented by actual report class
     * if report requires group column summary rows added (i.e. totals)
     * @return boolean  true if this report requires group column summary row(s)
     *                  false otherwise.
     */
    function requires_group_column_summary() {
        return false;
    }

    /**
     * Specifies whether header entries calculated for the same grouping level
     * and the same report row should be combined into a single column in CSV exports
     *
     * @return  boolean  true if enabled, otherwise false
     *
     */
    function group_repeated_csv_headers() {
        //disable by default
        return false;
    }

    /**
     * Takes a record and transforms it into an appropriate format for
     * group column summary row (i.e. column totals, etc.)
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $lastrecord     The last report record
     * @param   stdClass  $nextrecord     The next report record
     * @param   string    $export_format  The format being used to render the report
     *
     * @return  stdClass                  The reformatted record
     *                                    return null to suppress row output
     */
    function transform_group_column_summary($lastrecord, $nextrecord, $export_format) {
        return null;
    }

    /**
     * Sets a header icon to display in the report
     *
     * @param  string  $icon_url  The URL to display
     */
    function set_header_icon($icon_url) {
        $this->headericon = $icon_url;
    }

    function set_groupings($groupings) {
        $this->groupings = $groupings;
    }

    function set_summary_row($summary_row) {
        $this->summary_row = $summary_row;
    }

/**
 * Print a nicely formatted table.
 * Note: This function is copied from core Moodle and contains modifications by RL
 *
 * @param array $table is an object with several properties.
 * <ul>
 *     <li>$table->head - An array of heading names.
 *     <li>$table->align - An array of column alignments
 *     <li>$table->size  - An array of column sizes
 *     <li>$table->wrap - An array of "nowrap"s or nothing
 *     <li>$table->data[] - An array of arrays containing the data.
 *     <li>$table->width  - A percentage of the page
 *     <li>$table->tablealign  - Align the whole table
 *     <li>$table->class - class attribute to put on the table
 *     <li>$table->id - id attribute to put on the table.
 *     <li>$table->rowclass[] - classes to add to particular rows.
 *     <li>$table->summary - Description of the contents for screen readers.
 *     <li>$table->firstcolumn - If set, only use first column
 *     <li>$table->spanrow - If set, span entire row
 *     <li>$table->headercolumncss - CSS for specific columns in header
 *     <li>$table->columncss - CSS for specific columns in data
 * </ul>
 * @param bool $return whether to return an output string or echo now
 * @return boolean or $string
 * @todo Finish documenting this function
 */
function print_table($table, $return=false) {
    $output = '';

    if (isset($table->align)) {
        foreach ($table->align as $key => $aa) {
            if ($aa) {
                $align[$key] = ' text-align:'. fix_align_rtl($aa) .';';  // Fix for RTL languages
            } else {
                $align[$key] = '';
            }
        }
    }

    if (isset($table->size)) {
        foreach ($table->size as $key => $ss) {
            if ($ss) {
                $size[$key] = ' width:'. $ss .';';
            } else {
                $size[$key] = '';
            }
        }
    }
    if (isset($table->wrap)) {
        foreach ($table->wrap as $key => $ww) {
            if ($ww) {
                $wrap[$key] = '';
            } else {
                $wrap[$key] = ' white-space:nowrap;';
            }
        }
    }

    if (isset($table->header_wrap)) {
        foreach ($table->header_wrap as $key => $ww) {
            if ($ww) {
                $header_wrap[$key] = '';
            } else {
                $header_wrap[$key] = '; white-space:nowrap;';
            }
        }
    }

    if (empty($table->width)) {
        $table->width = '80%';
    }

    if (empty($table->tablealign)) {
        $table->tablealign = 'center';
    }

    if (empty($table->class)) {
        $table->class = ''; //removed generaltable
    }

    $tableid = empty($table->id) ? '' : 'id="'.$table->id.'"';

    $output .= '<table width="'.$table->width.'" ';
    if (!empty($table->summary)) {
        $output .= " summary=\"$table->summary\"";
    }
    $output .= " class=\"$table->class boxalign$table->tablealign\" $tableid>\n";

    $countcols = 0;

    // Toggle for whether a column header should be shown
    $need_columns_header = false;

    if (!empty($table->head)) {
        $countcols = count($table->head);
        $columns_header_output = '<tr class="column_header">'."\n";
        $keys=array_keys($table->head);
        $lastkey = end($keys);
        foreach ($table->head as $key => $heading) {

            if (!isset($size[$key])) {
                $size[$key] = '';
            }
            if (!isset($align[$key])) {
                $align[$key] = '';
            }
            if ($key == $lastkey) {
                $extraclass = ' lastcol';
            } else {
                $extraclass = '';
            }

            if (!isset($header_wrap[$key])) {
                $header_wrap[$key] = '';
            }
            $columns_header_output .= '<th style="vertical-align:top;'. $align[$key].$size[$key].$header_wrap[$key] . '" class="c'.$key.$extraclass.  ' ' . $table->headercolumncss[$key] . '" scope="col">'. $heading .'</th>';
        }
        $columns_header_output .= '</tr>'."\n";

        // Only display column header if no groupings defined
        if (empty($this->groupings)) {
            $output .= $columns_header_output;
        } else {
            $need_columns_header = true;
        }
    }

    if (!empty($table->data)) {
        $oddeven = 1;
        $keys=array_keys($table->data);
        $lastrowkey = end($keys);
        foreach ($table->data as $key => $row) {
            //this needs to be row and grouping specific
            $oddeven = $oddeven ? 0 : 1;
            if (!isset($table->rowclass[$key])) {
                $table->rowclass[$key] = '';
            }
            if ($key == $lastrowkey) {
                $table->rowclass[$key] .= ' lastrow';
            }

            if ($row == 'hr' and $countcols) {
                $output .= '<tr class="r'.$oddeven.' '.$table->rowclass[$key].'">'."\n";
                $output .= '<td colspan="'. $countcols .'"></td>';
            } else if (!empty($table->spanrow[$key])) {
                //reset oddeven
                $oddeven = 1;
                $output .= '<tr class="'.$table->rowclass[$key].'">'."\n";
                $output .= '<td colspan="'. $countcols .'">' . $row[0] . '</td>';
            } else if (!empty($table->firstcolumn[$key])) {
                //reset oddeven
                $oddeven = 1;
                $output .= '<tr class="'.$table->rowclass[$key].'">'."\n";
                $output .= '<td colspan="'. $countcols .'">' . $row[0] . '</td>';
                $need_columns_header = true;
            } else {
                // Handle display of column headers
                if ($need_columns_header) {
                    $output .= $columns_header_output;
                    $need_columns_header = false;
                }

                //check to see if this row is a hidden grouping break
                if (stripos($table->rowclass[$key], get_string('group_class_id', 'block_php_report')) !== FALSE) {
                    //reset oddeven
                    $oddeven = 1;
                    $output .= '<tr class="'.$table->rowclass[$key].'">'."\n";
                } else
                //check to see if this row is a hidden summary break
                if (stripos($table->rowclass[$key], 'summary') !== FALSE) {
                    //reset oddeven
                    $oddeven = 1;
                    $output .= '<tr class="'.$table->rowclass[$key].'">'."\n";
                } else { /// it's a normal row of data
                    $output .= '<tr class="r'.$oddeven.' '.$table->rowclass[$key].'">'."\n";
                }

                $keys2=array_keys($row);
                $lastkey = end($keys2);

                $i = 0;

                foreach ($row as $key => $item) {
                    if (!isset($size[$key])) {
                        $size[$key] = '';
                    }
                    if (!isset($align[$key])) {
                        $align[$key] = '';
                    }
                    if (!isset($wrap[$key])) {
                        $wrap[$key] = '';
                    }
                    if ($key == $lastkey) {
                      $extraclass = ' lastcol';
                    } else {
                      $extraclass = '';
                    }

                    //Handle non-numerically indexed data
                    $num_key = array_search($key, array_keys($row));
                    $output .= '<td style="'. $align[$num_key].$size[$num_key].$wrap[$num_key] .'" class="cell c'.$key.$extraclass. ' ' . $table->columncss[$i] . '">'. $item .'</td>';

                    $i++;

                }
            }
            $output .= '</tr>'."\n";
        }
    }
    $output .= '</table>'."\n";

    if ($return) {
        return $output;
    }

    echo $output;
    return true;
}

    /**
     * Specifies the particular export formats that are supported by this report
     *
     * @return  string array  List of applicable export identifiers that correspond to
     *                        possiblities as specified by get_allowable_export_formats
     */
    function get_export_formats() {
        return array(php_report::$EXPORT_FORMAT_PDF,
                     php_report::$EXPORT_FORMAT_CSV);
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colour used in the background when
     * displaying the report display name
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_display_name_colour() {
        return array(219, 229, 241);
    }

    /**
     * Specifies the RGB components of the colours used in the background when
     * displaying report header entries
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_header_colour() {
        return array(255, 255, 255);
    }

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(141, 179, 226);
    }

    /**
     * Specifies the RGB components of one or more colours used in an
     * alternating fashion as report row backgrounds
     *
     * @return  array array  Array containing arrays of red, green, and blue components
     *                       (one array for each alternating colour)
     */
    function get_row_colours() {
        return array(array(255, 255, 255),
                     array(242, 242, 242));
    }

    /**
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(array(255, 255, 255));
    }

    /**
     * Specifies the RGB components of a colour to use as a background in one-per-report summary rows
     * that have per-column data
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_based_summary_colour() {
        return array(255, 255, 255);
    }

    /**
     * Specifies the RGB components of a colour to use as a background in one-per-group summary rows
     *
     * @return  int array  Array containing the red, gree, and blue components in that order
     */
    function get_grouping_summary_row_colour() {
        return array(255, 255, 255);
    }

    /**
     * Calculates and returns a message to display that
     * informs users on how recent their data is
     *
     * @param   int|string     $id  The current report block id
     *
     * @return  string         HTML for a form with the display text and a reset button
     */
    function get_lastload_display($id = 0) {
        global $CFG, $USER;

        $format = '%A, %B %e, %l:%M:%S %P';

        $element_id = 'refresh_report';
        if (!empty($id)) {
            $element_id .= '_' . $id;
        }

        $timezone = 99;
        if (isset($USER->timezone)) {
            $timezone = $USER->timezone;
        }
        $lastload = time();
        $a = userdate($lastload, $format, $timezone);

        return '<form id="'. $element_id .'" action="'. $CFG->wwwroot .'/blocks/php_report/dynamicreport.php" '.
               'onsubmit="start_throbber(); return true;" >'.
               '<input type="hidden" id="id" name="id" value="'. $id .'" />' .
               '<input type="hidden" id="page" name="page" value="'. $this->page .'" />'.
               '<input type="hidden" id="sort" name="sort" value="'. $this->sort .'" />'.
               '<input type="hidden" id="dir" name="dir" value="'. $this->dir .'" />'.
               '<p align="center" class="php_report_caching_info">'. get_string('infocurrent', 'block_php_report', $a) .'<br/>'.
               '<input id="'. $element_id .'" type="submit" value="Refresh"/></p>'.
               '</form>';
    }

}

/**
 * Class for storing a method for grouping the report data into different sections
 *
 */
class table_report_grouping {

    var $id;
    var $field;
    var $label;
    var $order;
    var $col_element;
    var $position;

    /**
     * Constructor for our grouping
     *
     * @param  string  $id           The grouping id
     * @param  string  $field        The field we are keying on for our grouping
     * @param  string  $label        The label shown in grouping row
     * @param  string  $order        The order in which to sort the field if no sort order is specified
     * @param  array   $col_element  Array of column element(s) to be displayed on group break
     * @param  string  $position     The position of the grouping, above or below headers/data
     * @param  string  $sortfield    If not NULL, overrides the sorting specified by the "$field" parameter
     */
    function table_report_grouping($id, $field, $label, $order, $col_element=array(), $position='above', $sortfield = NULL) {
        $this->id = $id;
        $this->field = $field;
        $this->label = $label;
        $this->order = $order;
        $this->col_element = $col_element;
        $this->position = $position;
        $this->sortfield = $sortfield;
    }

}

/**
 * Class representing an end-of-report summary row
 */
class table_report_summary_row {
    var $field;
    var $field_displayname;

    /**
     * Summary row constructor
     *
     * @param  string  $field              The field to pull the aggregation from
     * @param  string  $field_displayname  Display label
     */
    function table_report_summary_row($field, $field_displayname) {
        $this->field = $field;
        $this->field_displayname = $field_displayname;
    }
}

/**
 * Class representing a summary row containing column-by-column information
 */
class table_report_column_based_summary_row {
    var $mapping = array();

    /**
     * Column based summary row constructor
     *
     * @param  array  $mapping  Associative array mapping columns to SQL statements
     */
    function table_report_column_based_summary_row($mapping) {
        foreach ($mapping as $key => $value) {
            if (!empty($value)) {
                $this->mapping[$key] = $value;
            }
        }
    }

    /**
     * Returns an object representing the summary row
     *
     * @param   array  $fields  Associative array mapping columns to SQL queries
     *
     * @return  mixed           An object containing the necessary fields, or NULL
     *                          if no summary row is defined
     */
    function get_row_object($fields) {
        global $DB;
        $found = false;
        $result = new stdClass;
        foreach ($fields as $field) {
            $effective_field = table_report::get_object_index($field);
            if (array_key_exists($field, $this->mapping)) {
                $result->$effective_field = $DB->get_field_sql($this->mapping[$field][0], $this->mapping[$field][1]);
                $found = true;
            } else {
                //empty field value
                $result->$effective_field = '';
            }
        }

        if ($found) {
            return $result;
        } else {
            //no columns have summaries
            return NULL;
        }
    }

}

/**
 * Class representing a column in a tabular report
 */
class table_report_column {
    var $id;
    var $name;
    var $align = 'left';
    var $sortable = false;
    var $wrap = true;
    var $header_wrap = true;

    /**
     * Regular column element
     * @param  int           $id                   The name of the database value column
     * @param  string        $name                 The title text
     * @param  string        $css_identifier       A unique identifier to represent this element in CSS
     * @param  string        $align                The column alignment
     * @param  boolean       $sortable             Whether the column is sortable
     * @param  boolean       $wrap                 Whether the column wraps when necessary
     * @param  boolean       $header_wrap          Whether the column header wraps when necessary
     * @param  string array  $columnexportformats  The formats in which this column should be displayed
    * @param   string        $aggregation_sql      SQL statement that returns a single measure representing a final sum for this column
     */
    function table_report_column($id, $name, $css_identifier, $align = 'left', $sortable = false, $wrap = true, $header_wrap = true, $columnexportformats = NULL, $aggregation_sql = '') {
        $this->id = $id;
        $this->name = $name;
        $this->css_identifier = $css_identifier;
        $this->align = $align;
        $this->sortable = $sortable;
        $this->wrap = $wrap;
        $this->header_wrap = $header_wrap;
        if ($columnexportformats !== NULL) {
            $this->columnexportformats = $columnexportformats;
        } else {
            //sane defaults
            $this->columnexportformats = array(php_report::$EXPORT_FORMAT_CSV,
                                               php_report::$EXPORT_FORMAT_EXCEL,
                                               php_report::$EXPORT_FORMAT_HTML,
                                               php_report::$EXPORT_FORMAT_PDF);
        }
        $this->aggregation_sql = $aggregation_sql;
    }

    /**
     * Adds this column to an existing report
     *
     * @param  table_report  $report  The report to add this column to
     */
    function add_to_report(&$report) {
        $report->add_column($this->id, $this->name, $this->css_identifier, $this->align, $this->sortable, $this->wrap, $this->header_wrap, $this->columnexportformats, $this->aggregation_sql);
    }
}

/**
 * Class used to represent a horizontal bar with various display options
 */
class table_report_horizontal_bar_column extends table_report_column {
    var $total_column;
    var $display_string = '';
    var $display_percent_sign = true;
    var $width = 100;
    var $height = 20;

    /**
     * Horizontal bar element
     * @param  int      $id                    The name of the database value column
     * @param  string   $name                  The title text
     * @param  string   $css_identifier        A unique identifier to represent this element in CSS
     * @param  string   $total_column          The name of the database total column
     * @param  string   $align                 The column alignment
     * @param  string   $display_string        Templatable display string
     * @param  boolean  $display_percent_sign  Whether to display the percent sign when appropriate
     * @param  int      $width                 Width of the bar
     * @param  int      $height                Height of the bar
     */
    function table_report_horizontal_bar_column($id, $name, $css_identifier, $total_column, $align = 'left', $display_string = '',
                                                $display_percent_sign = true, $width = 100, $height = 20) {
        parent::__construct($id, $name, $css_identifier, $align);

        $this->total_column = $total_column;
        $this->display_string = $display_string;
        $this->display_percent_sign = $display_percent_sign;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Adds this column to an existing report
     *
     * @param  table_report  $report  The report to add this column to
     */
    function add_to_report(&$report) {
        $report->add_horizontal_bar_column($this->id, $this->name, $this->css_identifier, $this->total_column, $this->align, $this->display_string, $this->display_percent_sign, $this->width, $this->height);
    }
}

