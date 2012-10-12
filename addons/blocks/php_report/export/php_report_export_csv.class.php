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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot . '/blocks/php_report/export/php_report_export.class.php');

class php_report_export_csv extends php_report_export {

    /**
     * Create a new instance of a CSV report export
     *
     * @param  php_report  $report  A reference to the report being exported
     */
    function php_report_export_csv(&$report) {
        $this->report =& $report;
    }

    /**
     * ----------------------
     * CSV formatting methods
     * ----------------------
     */

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
     * ---------------------
     * File-handling methods
     * ---------------------
     */

    /**
     * Open a CSV file for writing
     *
     * @param   string|NULL    $storage_path  Path pointing to the destination file,
     *                                        or NULL if writing to the browser
     * @param   string         $filename      Filename to use if sending to the browser
     *                                        (includes extension)
     *
     * @return  resource|NULL                 Resource handle, or NULL if sending to the browser
     */
    function open_file($storage_path, $filename) {
        $handle = NULL;

        if ($storage_path === NULL) {
            header("Content-Transfer-Encoding: ascii");
            header("Content-Disposition: attachment; filename=$filename");
            header("Content-Type: text/comma-separated-values");
        } else {
            $handle = fopen($storage_path, 'w');
        }

        return $handle;
    }

    /**
     * Writes a CSV line to a file / the browser
     *
     * @param  string|NULL    $storage_path  Path pointing to the destination file,
     *                                       or NULL if writing to the browser
     * @param  resource|NULL  $handle        Resource handle for the opened file, or NULL
     *                                       if sending to the browser      
     * @param  string array   $row           The line to output, as an array of values (already escaped)
     */
    function write_to_file($storage_path, $handle, $row) {
       if ($storage_path === NULL) {
            echo implode(',', $row) . "\n";
        } else {
            fwrite($handle, implode(',', $row) . "\n");
        }
    }

    /**
     * Closes a file / the session of writing out to the browser
     *
     * @param  string|NULL    $storage_path
     * @param  resource|NULL  $handle
     */
    function close_file($storage_path, $handle) {
        if ($storage_path === NULL) {
            //nothing to do
        } else {
            fclose($handle);
        }
    }

    /**
     * --------------------------------
     * Helper methods for data handling
     * --------------------------------
     */

    /**
     * Appends columns to the provided row of data based on the given grouping
     * column header entries
     *
     * @param  string array  $headers  List of all entries that would normally be used as
     *                                 grouping headers for the current grouping entry
     * @param  string array  $row      The current row of column header entries
     */
    function append_grouping_columns_to_header($headers, &$row) {
        if (!empty($headers)) {
            if ($this->report->group_repeated_csv_headers()) {
                //use the first label as the header because they should all be the same
                //for this particular filter
                $first_header = reset($headers);
                $row[] = $this->csv_escape_string(strip_tags($first_header));
            } else {
                //not combining them, so just spit them all out
                foreach ($headers as $header) {
                    $row[] = $this->csv_escape_string(strip_tags($header));
                }
            }
        }
    }

    /**
     * Appends columns to the provided row of data based on the given grouping
     * data entries
     *
     * @param  string array  $headers  List of all entries that would normally be used as
     *                                 grouping headers for the current grouping entry
     * @param  string array  $row      The current row of data entries, which will end up being
     *                                 a combination of grouping headers and data from report rows
     */
    function append_grouping_columns_to_data($headers, &$row) {
        if (!empty($headers)) {
            if ($this->report->group_repeated_csv_headers()) {
                //possibly multiple entries for the same grouping, so combine
                //then into a single column
                $combined_header = implode(' / ', $headers);
                $row[] = $this->csv_escape_string(strip_tags($combined_header));
            } else {
                //not combining them, so just spit them all out
                foreach ($headers as $header) {
                    $row[] = $this->csv_escape_string(strip_tags($header));
                }
            }
        }
    }

    /**
     * -------------------------------
     * Parent-class API implementation
     * -------------------------------
     */

    /**
     * Export a report in this format
     *
     * @param  string  $query         Final form of the main report query
     * @param  string  $params        SQL query params
     * @param  string  $storage_path  Path on the file system to save the output to,
     *                                or NULL if sending to browser
     * @param  $filename              Filename to use when sending to browser
     */
    function export($query, $params, $storage_path, $filename) {
        global $DB;

        $init = false;
        $filename .= '.csv';

        //open the file
        $csv_file_handle = $this->open_file($storage_path, $filename);

        $header_entries = $this->report->get_header_entries(php_report::$EXPORT_FORMAT_CSV);
        if (!empty($header_entries)) {
            //write out the header entries
            foreach ($header_entries as $header) {
                $this->write_to_file($storage_path, $csv_file_handle,
                    array($this->csv_escape_string(strip_tags($header->label)) .
                    ','. $this->csv_escape_string(strip_tags($header->value))));
            }
            // TBD: add empty row?
            $this->write_to_file($storage_path, $csv_file_handle, array(''));
        }

        $row = array();
        $grouping_object = $this->report->initialize_groupings();

        //iterate through the result records
        if ($recordset = $DB->get_recordset_sql($query, $params)) {
            foreach ($recordset as $datum) {
                if (!is_object($datum)) {
                    continue;
                }

                if (!$init) {
                    $init = true;
                    if (!empty($this->report->groupings)) {
                        // output grouping labels as column headings
                        foreach ($this->report->groupings as $grouping) {
                            $this->report->update_current_grouping($grouping,
                                                                   $datum,
                                           $grouping_object->grouping_current);
                            //var_dump($grouping);
                            //TBD: if (in_array(php_report::$EXPORT_FORMAT_CSV, $this->report->groupexportformats[$grouping->id]))
                            {
                                if ($grouping->position == 'below') {
                                    $datum_group = clone($datum);
                                    $grouping_row = $this->report->clean_header_entry($datum, $grouping, $datum_group);
                                    if ($grouping_row) {
                                      /* ****
                                        //"Below" position with per-column data
                                        $datum_group_copy = clone($datum_group);
                                        $datum_group_copy = $this->report->transform_grouping_header_record($datum_group_copy, $datum, php_report::$EXPORT_FORMAT_CSV);
                                        $row[] = "[GROUPING ROW]"; // TBD
                                      **** */
                                    } else {
                                        $headers = $this->report->transform_grouping_header_label($grouping_object->grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_LABEL);
                                        $this->append_grouping_columns_to_header($headers, $row);
                                    }
                                } else { // position above
                                    $headers = $this->report->transform_grouping_header_label($grouping_object->grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_LABEL);
                                    $this->append_grouping_columns_to_header($headers, $row);
                                }
                            }
                        }
                    }

                    foreach ($this->report->headers as $id => $header) {
                        //make sure this column is exportable
                        if (in_array(php_report::$EXPORT_FORMAT_CSV, $this->report->columnexportformats[$id])) {
                            $row[] = $this->csv_escape_string(strip_tags($header));
                        }
                    }

                    //write out the header row
                    $this->write_to_file($storage_path, $csv_file_handle, $row);
                } // end: !$init

                //apply any necessary transformation
                $datum = $this->report->transform_record($datum, php_report::$EXPORT_FORMAT_CSV);

                $row = array();

                //iterate through groupings
                if (!empty($this->report->groupings)) {
                    foreach ($this->report->groupings as $grouping) {
                        $this->report->update_current_grouping($grouping,
                                                               $datum,
                                           $grouping_object->grouping_current);
                        //TBD: if (in_array(php_report::$EXPORT_FORMAT_CSV, $this->report->groupexportformats[$grouping->id]))
                        {
                            if ($grouping->position == 'below') {
                                $datum_group = clone($datum);
                                $grouping_row = $this->report->clean_header_entry($datum, $grouping, $datum_group);
                                if ($grouping_row) {
                                    //"Below" position with per-column data
                                    //$datum_group_copy = clone($datum_group);
                                    $datum = $this->report->transform_grouping_header_record($datum, $datum, php_report::$EXPORT_FORMAT_CSV);
                                    //$row[] = "[GROUPING ROW]"; // TBD
                                } else {
                                    $headers = $this->report->transform_grouping_header_label($grouping_object->grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_CSV);
                                    $this->append_grouping_columns_to_data($headers, $row);
                                }
                            } else { // position above
                                $headers = $this->report->transform_grouping_header_label($grouping_object->grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_CSV);
                                $this->append_grouping_columns_to_data($headers, $row);
                            }
                        }
                    }
                }

                //iterate through columns
                foreach ($this->report->headers as $id => $unused) {
                    //make sure this column is exportable
                    if (in_array(php_report::$EXPORT_FORMAT_CSV, $this->report->columnexportformats[$id])) {
                        $effective_id = $this->report->get_object_index($id);

                        //retrieve actual data
                        if (isset($datum->$effective_id)) {
                            $row[] = $this->csv_escape_string($datum->$effective_id);
                        } else {
                            $row[] = '""';
                        }
                    }
                }

                //write out the data row
                $this->write_to_file($storage_path, $csv_file_handle, $row);
            }
        }

        //close the stream
        $this->close_file($storage_path, $csv_file_handle);
    }

}

