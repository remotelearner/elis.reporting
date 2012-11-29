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

class php_report_export_pdf extends php_report_export {

    //constants for page sizing
    const page_width = 11.0;

    const marginx = 0.75;
    const marginy = 0.75;

    //amount of space between columns (prevents data values
    //from touching one another)
    const horizontal_buffer = 0.2;

    /**
     * Create a new instance of a PDF report export
     *
     * @param  php_report  $report  A reference to the report being exported
     */
    function php_report_export_pdf(&$report) {
        $this->report =& $report;
    }

    /**
     * -------------------------------------
     * Basic work with the PDF file / object
     * -------------------------------------
     */

    /**
     * Sets up a new PDF object with the necessary settings
     *
     * @return  FPDF            A new PDF object
     */
    protected function initialize_pdf() {
        global $CFG;
        require_once($CFG->dirroot. '/blocks/php_report/lib/tcpdf/tcpdf.php');

        $newpdf = new TCPDF('L', 'in', 'letter');

        //prevent the library from automatically outputting
        //header or footer bars
        $newpdf->SetPrintHeader(false);
        $newpdf->SetPrintFooter(false);

        $newpdf->setMargins(self::marginx, self::marginy);
        $newpdf->SetFont('freesans', '', 9);
        $newpdf->AddPage();
        $newpdf->SetFont('freesans', '', 16);
        $newpdf->MultiCell(0, 0.2, $this->report->title, 0, 'C');
        $newpdf->Ln(0.2);
        $newpdf->SetFont('freesans', '', 8);
        $newpdf->SetFillColor(225, 225, 225);

        return $newpdf;
    }

    /**
     * Performs the raw output of the PDF file
     *
     * @param  FPDF    $newpdf        The PDF object to write to file
     * @param  string  $storage_path  Path to save the file to, or NULL if sending to browser
     * @param  string  $filename      Filename to use if sending the file to the browser (including extension)
     */
    protected function output_pdf_file(&$newpdf, $storage_path, $filename) {
        if ($storage_path === NULL) {
            $newpdf->Output($filename, 'I');
        } else {
            $newpdf->Output($storage_path, 'F');
        }
    }

    /**
     * ---------------------------
     * Sizing-related calculations
     * ---------------------------
     */
    // multi-explode to support many delimiters
    function m_explode($delims, $str) {
        $re = array();
        $init = 0;
        while (1) {
            $tok = ($init++) ? strtok($delims) : strtok($str, $delims);
            if ($tok === false) {
                break;
            }
            $re[] = $tok;
        }
        return $re;
    }

     /**
      * Calculates the minimum space needed to render the largest token
      * from the provided text
      */
    protected function get_min_string_width($newpdf, $text) {
        //need at least the buffer size
        $result = self::horizontal_buffer;

        //go through tokens and find the largest one
        //$parts = explode(' ', $text);
        $parts = $this->m_explode(" _-\t\n", $text);
        foreach ($parts as $part) {

            //update result if necessary
            $width = self::horizontal_buffer + $newpdf->GetStringWidth($part);
            if ($width > $result) {
                $result = $width;
            }
        }

        return $result;
    }

    /**
     * Calcualtes initial width settings for columns based entirely on
     * the header row
     *
     * @param   php_report           $report      A reference to the report being exported
     * @param   int array reference  $min_widths  Update with minimum column widths based on longest token
     *
     * @return  int array                         Mapping of column ids to widths, in pixels
     */
    protected function calculate_pdf_column_header_widths(&$newpdf, &$min_widths) {
        $widths  = array();
        foreach ($this->report->headers as $id => $header) {
            //make sure this column is supported in the current format
            if (in_array(php_report::$EXPORT_FORMAT_PDF, $this->report->columnexportformats[$id])) {
                //initial width calculation for this column based only on the header
                $widths[$id] = $newpdf->GetStringWidth($header) + self::horizontal_buffer;
                //determine the minimum width based on the longest token size
                $min_widths[$id] = $this->get_min_string_width($newpdf, $header);
            }
        }

        return $widths;
    }

    /**
     * Updates an existing listing of PDF column widths based on the core report data
     *
     * @param  FPDF reference       $newpdf        A reference to the PDF being created
     * @param  string               $query         The main report query
     * @param  array                $params        SQL query parameters
     * @param  int array reference  $widths        Current values for column widths
     * @param  int array reference  $heights       Current values for row heights
     * @param  boolean reference    $found_record  Set to TRUE if report has at least one row of data
     * @param  int array reference  $min_widths    Update with minimum column widths based on longest token
     */
    protected function update_pdf_column_sizes_from_data(&$newpdf, $query, $params, &$widths, &$heights, &$found_record, &$min_widths) {
        global $DB;

        $row = 0;

        //iterate through the results to calculate column widths,
        //using a recordset so we don't run out of memory
        if ($recordset = $DB->get_recordset_sql($query, $params)) {
            foreach ($recordset as $datum) {
                $found_record = true;

                //perform transformation as defined in the implementing report
                $datum = $this->report->transform_record($datum, php_report::$EXPORT_FORMAT_PDF);

                $this->update_pdf_sizings($newpdf, $datum, $widths, $heights, $row, $min_widths);

                $row++;
            }
        }

        return $row;
    }

    /**
     * Updates the PDF sizing information based on the current row of data
     *
     * @param  FPDF                 $newpdf      The PDF object we are creating
     * @param  stdClass             $datum       The current row of data
     * @param  array                $widths      Mapping of columns to widths
     * @param  array                $heights     Mapping of rows to heights
     * @param  id                   $row         Index of current row
     * @param  int array reference  $min_widths  Update with minimum column widths based on longest token
     */
    protected function update_pdf_sizings($newpdf, $datum, &$widths, &$heights, $row, &$min_widths) {
        if (!isset($heights[$row])) {
            $heights[$row] = 0;
        }

        //perform the calculation column-by-column
        foreach ($this->report->headers as $id => $header) {
            if (in_array(php_report::$EXPORT_FORMAT_PDF, $this->report->columnexportformats[$id])) {

                $effective_id = $this->report->get_object_index($id);

                if (isset($datum->$effective_id)) {
                    $width = $newpdf->GetStringWidth(trim(strip_tags($datum->$effective_id))) + self::horizontal_buffer;

                    //update the width if applicable
                    if ($width > $widths[$id]) {
                        $lines = ceil($width / $widths[$id]);
                        $widths[$id] = $width;
                    } else {
                        $lines = 1;
                    }

                    $height = $lines * 0.2;

                    //update the height if applicable
                    if ($height > $heights[$row]) {
                        $heights[$row] = $height;
                    }

                    //update min width if appropriate
                    $min_width = $this->get_min_string_width($newpdf, trim(strip_tags($datum->$effective_id)));
                    if ($min_width > $min_widths[$id]) {
                        $min_widths[$id] = $min_width;
                    }
                }
            }
        }
    }

    /**
     * Normalize column widths
     *
     * @param  float array  $widths      Array of column widths, in pixels
     * @param  int array    $min_widths  Minimum width required for each column
     */
    protected function normalize_widths(&$widths, $min_widths) {
        //this is the basic W3C Autolayout algorithm

        //calculate total width minus min width
        $W = self::page_width - 2 * self::marginx;
        foreach ($min_widths as $min_width) {
            $W -= $min_width;
        }

        //calculate max width minus min width
        $D = 0;
        foreach ($widths as $width) {
            $D += $width;
        }
        foreach ($min_widths as $min_width) {
            $D -= $min_width;
        }
        // ELIS-3367: prevent division by zero error
        if (!$D) {
            $D = 0.1; // TBD
        }

        //calculate the difference for each column
        $d = array();
        foreach (array_keys($widths) as $i) {
            $d[$i] = $widths[$i] - $min_widths[$i];
        }

        //final calculation
        foreach (array_keys($widths) as $i) {
            $widths[$i] = $min_widths[$i] + $d[$i] * $W / $D;
        }
    }

    /**
     * ------------------------
     * Main rendering functions
     * ------------------------
     */

    /**
     * Renders the dispay name of the current report at the top of the PDF
     *
     * @param  FPDF reference  $newpdf  The PDF being created
     */
    function render_report_name(&$newpdf) {
        //stash the old font size
        $font_size = $newpdf->getFontSizePt();

        //determine the name to display
        $display_name = $this->report->get_display_name();

        //determine render height
        $test_pdf = $this->initialize_pdf();
        $initial_y_offset = $test_pdf->GetY();
        $test_pdf->Cell(self::page_width - self::marginx - self::marginy, 0.4, $display_name);
        $test_pdf->Ln();
        $final_y_offset = $test_pdf->GetY();

        //render background
        $colour = $this->report->get_display_name_colour();
        $newpdf->SetFillColor($colour[0], $colour[1], $colour[2]);
        $newpdf->Rect(self::marginx, $initial_y_offset, self::page_width - self::marginx - self::marginx, $final_y_offset - $initial_y_offset, 'F');

        //increase font size
        $newpdf->SetFontSize(16);
        //render the report's display name
        $newpdf->Cell(self::page_width - self::marginx - self::marginy, 0.4, $display_name);

        //restore font size
        $newpdf->SetFontSize($font_size);

        //add space
        $newpdf->Ln(0.5);
    }

    /**
     * Retrieves the summary row object to be displayed within the PDF
     *
     * @return  stdClass  The summary row
     */
    protected function get_pdf_column_based_summary_row() {
        //use the column structure to create a summary record
        $column_based_summary_row = $this->report->column_based_summary_row->get_row_object(array_keys($this->report->headers));
        //use the special hook to perform any data manipulation needed
        $column_based_summary_row = $this->report->transform_column_summary_record($column_based_summary_row);

        return $column_based_summary_row;
    }

    /**
     * Renders key => value heading pairs in the PDF
     *
     * @param  FPDF reference  $newpdf  The PDF being created
     */
    protected function render_pdf_headers(&$newpdf) {
        $header_entries = $this->report->get_header_entries(php_report::$EXPORT_FORMAT_PDF);

        //add headers to the output
        if (!empty($header_entries)) {
            $header_label_width = self::horizontal_buffer;
            $header_value_width = self::horizontal_buffer;

            //calculate the maximum widths
            foreach ($header_entries as $header_entry) {
                $temp_header_label_width = $newpdf->GetStringWidth(trim(strip_tags($header_entry->label))) + self::horizontal_buffer;

                //update max label width if applicable
                if ($temp_header_label_width > $header_label_width) {
                    $header_label_width = $temp_header_label_width;
                }

                $temp_header_value_width = $newpdf->GetStringWidth(trim(strip_tags(str_replace("<br/>", "\n", $header_entry->value)))) + self::horizontal_buffer;

                //update max value width if applicable
                if ($temp_header_value_width > $header_value_width) {
                    $header_value_width = $temp_header_value_width;
                }
            }

            //create a test pdf to test the height of the rendered entry
            $test_pdf = $this->initialize_pdf();
            $initial_y_offset = $test_pdf->GetY();
            foreach ($header_entries as $header_entry) {
                $test_pdf->Cell($header_label_width, 0.2, trim(strip_tags($header_entry->label)));
                $test_pdf->MultiCell($header_value_width, 0.2, trim(strip_tags(str_replace("<br/>", "\n", $header_entry->value))));
            }
            $test_pdf->Ln();
            $final_y_offset = $test_pdf->GetY();

            //render the background
            $colour = $this->report->get_header_colour();
            $newpdf->SetFillColor($colour[0], $colour[1], $colour[2]);
            $newpdf->Rect(self::marginx, $newpdf->GetY(), self::page_width - self::marginx - self::marginy, $final_y_offset - $initial_y_offset, 'F');

            //render the entries
            foreach ($header_entries as $header_entry) {
                $newpdf->Cell($header_label_width, 0.2, trim(strip_tags($header_entry->label)));
                $newpdf->MultiCell($header_value_width, 0.2, trim(strip_tags(str_replace("<br/>", "\n", $header_entry->value))));
            }
            $newpdf->Ln();
        }
    }

    /**
     * Render the text component of a column header, not including colouring
     * or any special formatting
     *
     * @param   FPDF   $newpdf   The PDF being created
     * @param   array  $widths   Final values for column widths (maps header ids to pixels width)
     *
     * @return  int              The height of the rendered row, in inches
     */
    function render_pdf_column_header_text(&$newpdf, $widths) {
        //enable bolding of output
        $this->set_pdf_bold_status($newpdf, true);

        //need to track Y offsets so we can draw gridlines later on
        $initial_y_offset = $newpdf->GetY();
        $final_y_offset = $initial_y_offset;

        //render the column headers
        foreach ($this->report->headers as $id => $header) {
            if (in_array(php_report::$EXPORT_FORMAT_PDF, $this->report->columnexportformats[$id])) {
                $text = $header;

                $cell_align = 'C';

                //convert alignment to format used by pdf library
                switch (strtolower($this->report->align[$id])) {
                    case 'left':
                        $cell_align = 'L';
                        break;
                    case 'center':
                        $cell_align = 'C';
                        break;
                    case 'right':
                        $cell_align = 'R';
                        break;
                    default:
                        $cell_align = 'C';
                        break;
                }

                //render and calculate maximum height
                $x_before = $newpdf->GetX();
                $y_before = $newpdf->GetY();
                $newpdf->MultiCell($widths[$id], 0.2, "$text", 0, $cell_align, 0);

                if ($newpdf->GetY() > $final_y_offset) {
                    $final_y_offset = $newpdf->GetY();
                }

                $newpdf->SetXY($x_before + $widths[$id], $y_before);
            }
        }

        $newpdf->SetY($final_y_offset);

        //disable bolding of output
        $this->set_pdf_bold_status($newpdf, false);

        return $final_y_offset - $initial_y_offset;
    }

    /**
     * Renders tabular column headers in the PDF being created
     *
     * @param  FPDF reference  $newpdf   The PDF object being created
     * @param  int array       $widths   Final values for column widths (maps header ids to pixels width)
     */
    protected function render_pdf_column_headers(&$newpdf, $widths) {
        $test_pdf = $this->initialize_pdf();
        $height = $this->render_pdf_column_header_text($test_pdf, $widths);

        $initial_y_offset = $newpdf->GetY();
        $final_y_offset = $initial_y_offset + $height;

        $x_value = self::marginx;

        //set the background clour to the per-report value
        $colour = $this->report->get_column_header_colour();
        $newpdf->SetFillColor($colour[0], $colour[1], $colour[2]);

        foreach ($widths as $width) {
            $x_value += $width;
        }

        //manually break the page if the height exceeds the remaining space
        if (($newpdf->GetY() + $height) > ($newpdf->getPageHeight() - $newpdf->getBreakMargin())) {
            //obtain the margins
            $margins = $newpdf->getMargins();

            //reset the vertical positioning
            $initial_y_offset = $margins['top'];
            $final_y_offset = $initial_y_offset + $height;

            //flush the page
            $newpdf->AddPage();
        }

        $newpdf->Rect(self::marginx, $initial_y_offset, $x_value - self::marginx, $final_y_offset - $initial_y_offset, 'F');

        $this->render_pdf_column_header_text($newpdf, $widths);
    }

    /**
     * Sets the font status to bolded or unbolded in a PDF
     *
     * @param  FPDF     $newpdf  The PDF object being created
     * @param  boolean  $bold    true to enable bolding, or false to disable it
     */
    function set_pdf_bold_status(&$newpdf, $bold) {
        if ($bold) {
            $format_attribute = 'B';
        } else {
            $format_attribute = '';
        }

        //update the font format while keeping font family and size unchanged
        $newpdf->SetFont($newpdf->getFontFamily(), $format_attribute, 0);
    }

    /**
     * Sets the font style on the provided PDF object to the provided style
     * without changing the font or size
     *
     * @param  FPDF    $newpdf  The PDF object being created
     * @param  string  $style   The appropriate style string
     */
    function set_font_style(&$newpdf, $style) {
        $newpdf->SetFont($newpdf->getFontFamily(), $style, 0);
    }

    /**
     * Adds a grouping row to the table belonging to this report
     *
     * @param  mixed    $data         Row contents
     * @param  boolean  $spanrow      If TRUE, spanning row without forcing column header directly after it
     * @param  boolean  $firstcolumn  If TRUE, spanning row, force column header directly after it
     * @param  string   $rowclass     CSS class to apply to the table row
     * @param  int      $level        Which grouping level we are currently at (0-indexed)
     */
    function add_grouping_table_row($data, $spanrow, $firstcolumn, $rowclass, &$newpdf, $widths, $level) {

        //set the row's color based on the report definition
        $colours = $this->report->get_grouping_row_colours();
        if ($level < count($colours)) {
            $colour = $colours[$level];
        } else {
            //ran out of colours, so use the last one
            $colour = $colours[count($colours) - 1];
        }

        //enable bolding status
        $this->set_pdf_bold_status($newpdf, true);

        if ($spanrow || $firstcolumn) {
            //single text entry case
            $newpdf->SetFillColor($colour[0], $colour[1], $colour[2]);
            $newpdf->Cell(self::page_width - 2 * self::marginx, 0.2, $data[0], 0, 0, 'L', 1);
            $newpdf->Ln();
        } else {
            //column-based entry case
            $this->render_pdf_entry($newpdf, (object)$data, $widths, 0.2, $colour);
        }

        //disable bolding status
        $this->set_pdf_bold_status($newpdf, false);
    }

    /**
     * Performs all calculations / actions to process additional information based on report
     * groupings
     *
     * @param   stdClass  $datum                 The current (unformatted) row of report data
     * @param   array     $grouping_last         Mapping of column identifiers to the value representing them
     *                                           in the last grouping change
     * @param   array     $gropuing_current      Mapping of column identifiers to the value representing them
     *                                           in the current grouping state
     * @param   array     $grouping_first        Mapping of column identifiers to the value true if they've
     *                                           not been processed yet, or false if they have
     * @param   FPDF      $newpdf                The PDF object being created
     * @param   array     $widths                Final mapping of column ids to pixel widths
     * @param   boolean   $need_columns_header   Variable to update with status regarding whether we need to display
     *                                           column headers before our next row of data
     * @param   stdClass  $next_datum            The next unprocessed row that we be used in the report data, or false
     *                                           if none
     * @param   boolean   $reset_column_colours  Set to true to signal that column colour state should be reset
     *
     * @return  stdClass                         Summary record to display after next row of data, or false if none
     */
    function update_groupings(&$datum, &$grouping_last, &$grouping_current, &$grouping_first, &$newpdf, $widths, &$need_columns_header, $next_datum, &$reset_column_colours) {
        $result = false;

        //make sure groupings are set up
        if (!empty($this->report->groupings) && ! (is_array($datum) && (strtolower($datum[0]) == 'hr'))) {

            //index to store the a reference to the topmost grouping entry that needs to be displayed
            $topmost_key = $this->report->get_grouping_topmost_key($grouping_first, $datum, $grouping_last);

            //make sure something actually changed
            if ($topmost_key !== NULL) {
                $reset_column_colours = true;

                //go through only the headers that actually matter
                for ($index = $topmost_key; $index < count($this->report->groupings); $index++) {
                    $grouping = $this->report->groupings[$index];

                    //set the information in the current grouping based on our report row
                    $this->report->update_current_grouping($grouping, $datum, $grouping_current);
                    // Handle grouping changes
                    if ($grouping->position == 'below') {
                        //Make a copy of this row datum to be modified for printing group header inline
                        $datum_group = clone($datum);

                        //remove any unnecessary entries from the header row
                        $grouping_row = $this->report->clean_header_entry($datum, $grouping, $datum_group);

                        //be sure to display a column header before the below-column-header header
                        if ($need_columns_header) {
                            $this->render_pdf_column_headers($newpdf, $widths);
                            $need_columns_header = false;
                        }

                        if ($grouping_row) {
                            //"Below" position with per-column data
                            $datum_group_copy = clone($datum_group);
                            $datum_group_copy = $this->report->transform_grouping_header_record($datum_group_copy, $datum, php_report::$EXPORT_FORMAT_PDF);
                            $grouping_display_text = $this->report->get_row_content($datum_group_copy, $grouping_row);
                            $this->add_grouping_table_row($grouping_display_text, false, false, 'php_report_table_row', $newpdf, $widths, $index);
                        } else {
                            //"Below" position without per-column data
                            $headers = $this->report->transform_grouping_header_label($grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_PDF);

                            //add all headers to the table output
                            if (count($headers) > 0) {
                                foreach ($headers as $header) {
                                    $grouping_display_text = array($header);
                                    $this->add_grouping_table_row($grouping_display_text, true, false, 'php_report_table_divider', $newpdf, $widths, $index);
                                }
                            }
                        }

                    } else {
                        //signal that we need to display column headers before the next header
                        //that is not of this type / before the next report data entry
                        $need_columns_header = true;

                        //"Above" position without per-column data (single label and value)
                        $headers = $this->report->transform_grouping_header_label($grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_PDF);

                        //add all headers to the table output
                        if (count($headers) > 0) {
                            foreach ($headers as $header) {
                                $grouping_display_text = array($header);
                                $this->add_grouping_table_row($grouping_display_text, false, true, 'php_report_table_divider', $newpdf, $widths, $index);
                            }
                        }

                    }

                    //move on to the next entry
                    $this->report->update_groupings_after_iteration($grouping, $grouping_first, $grouping_current, $grouping_last);
                }
            }
        }

        //be sure to display a column header before the report data if necessary
        if ($need_columns_header) {
            $this->render_pdf_column_headers($newpdf, $widths);
            $need_columns_header = false;
        }

        return $result;
    }

    /**
     * Renders the core tabular data of the report
     *
     * @param   FPDF reference  $newpdf   The PDF object being created
     * @param   string          $query    The main report query
     * @param   array           $params   SQL query params
     * @param   int array       $widths   Final mapping of column ids to pixel widths
     * @param   int array       $heights  Final mapping of rows to pixel heights
     *
     * @return  int                       Number of rows processed
     */
    protected function render_pdf_core_data(&$newpdf, $query, $params, $widths, $heights, &$need_columns_header) {
        global $DB;

        $row = 0;

        $grouping_object = $this->report->initialize_groupings();

        //iterate through the core report data
        if ($recordset = $DB->get_recordset_sql($query, $params)) {

            //need to track both so we can detect grouping changes
            $datum = $recordset->current();;
            $next_datum = false;

            //tracks the state of alternating background colours
            $column_colour_state = 0;

            while ($datum !== false) {
                $cur_datum = clone($datum); // copy BEFORE transform_record()

                //pre-emptively fetch the next record for grouping changes
                $recordset->next();
                //fetch the current record
                $next_datum = $recordset->current();
                if (!$recordset->valid()) {
                    //make sure the current record is a valid one
                    $next_datum = false;
                }

                $reset_column_colours = false;

                $datum = $this->report->transform_record($datum, php_report::$EXPORT_FORMAT_PDF);
                //get the per-group column summary item, if applicable
                $column_summary_item = $this->update_groupings($datum, $grouping_object->grouping_last, $grouping_object->grouping_current, $grouping_object->grouping_first, $newpdf, $widths, $need_columns_header, $next_datum, $reset_column_colours);

                if ($reset_column_colours) {
                    //grouping change, so reset background colour state
                    $column_colour_state = 0;
                }

                //render main data entry
                $datum = $this->report->get_row_content($datum, false, php_report::$EXPORT_FORMAT_PDF);

                //render the entry, taking into account the current state of the background colour
                $colours = $this->report->get_row_colours();
                $colour = $colours[$column_colour_state];
                $this->render_pdf_entry($newpdf, (object)$datum, $widths, $heights[$row], $colour);

                if ($this->report->requires_group_column_summary()) {
                    $grouping_change = $next_datum === false || $this->report->any_group_will_change($cur_datum, $next_datum);

                    if ($grouping_change) {
                        //last record or grouping change

                        //get the summary record
                        $grpcolsum = $this->report->transform_group_column_summary($cur_datum, $next_datum, php_report::$EXPORT_FORMAT_PDF);

                        if (!empty($grpcolsum)) {
                            //summary record is valid, so signal to display it after the next record
                            $column_colour_state = 0; // TBD
                            $this->render_pdf_entry($newpdf, $grpcolsum, $widths, 0.3, $this->report->get_grouping_summary_row_colour());
                        }
                    }
                }

                $row++;

                //update the state of the background colour
                $column_colour_state = ($column_colour_state + 1) % count($colours);

                //already tried to fetch the next record, so use it
                $datum = $next_datum;
            }
        }

        return $row;
    }

    /**
     * Renders the core content of a PDF row without doing extra work, such
     * as colourings
     *
     * @param   FPDF reference  $newpdf  The PDF object we are creating
     * @param   stdClass        $datum   The current row of data
     * @param   int array       $widths  Mapping of columns to widths
     *
     * @return  int                      The height of the rendered row, in inches
     */
    protected function render_pdf_entry_content(&$newpdf, $datum, $widths) {
        //store the initial vertical position
        $initial_y = $newpdf->getY();
        $max_y_offset = 0;

        foreach ($this->report->headers as $id => $header) {
            if (in_array(php_report::$EXPORT_FORMAT_PDF, $this->report->columnexportformats[$id])) {
                $text = '';

                $effective_id = $this->report->get_object_index($id);

                if (isset($datum->$effective_id)) {
                    $text = trim(strip_tags($datum->$effective_id));
                }

                $cell_align = 'C';

                //convert alignment to format used by pdf library
                switch (strtolower($this->report->align[$id])) {
                    case 'left':
                        $cell_align = 'L';
                        break;
                    case 'center':
                        $cell_align = 'C';
                        break;
                    case 'right':
                        $cell_align = 'R';
                        break;
                    default:
                        $cell_align = 'C';
                        break;
                }

                //render the cell
                $initial_x = $newpdf->GetX();
                if (substr($text,0,strlen(php_report::$EXPORT_FORMAT_PDF_BAR)) == php_report::$EXPORT_FORMAT_PDF_BAR) {
                    // parse out the parameters from the text value if we have a special "progress bar" case
                    list($bar, $bar_params) = explode('|', $text, 2);
                    $bar_params_array = explode('&', $bar_params);
                    $bar = array();
                    foreach ($bar_params_array as $bar_line) {
                        list($bar_key,$bar_value) = explode('=', $bar_line, 2);
                        $bar[$bar_key] = $bar_value;
                    }

                    // determine the full width of the bar
                    $full_width = $widths[$id];

                    // determine the background filler width of the bar
                    if (round($bar['total']) > 0) {
                        $display_percentage = floor(($bar['value'] / $bar['total']) * 100);
                        $fill_width = $widths[$id] * ($bar['value'] / $bar['total']);
                    } else {
                        $display_percentage = 0;
                        $fill_width = 0;
                    }
                    $fill_width = ($fill_width > $widths[$id]) ? $widths[$id] : $fill_width;

                    //$percent_symbol = get_string('percent_symbol', 'block_php_report');
                    //$display_text = (!empty($bar['displaypercentsign'])) ? $display_percentage . $percent_symbol : $display_percentage;

                    // determine the bar overlay text
                    if($bar['total'] == 0) {
                        $fraction = 0;
                    } else {
                        $fraction = $bar['value'] / $bar['total'];
                    }
                    $decoded_text = urldecode($bar['displaytext']);
                    $display_text = $this->progress_bar_get_text($bar['value'], $bar['total'], $fraction, $decoded_text, $bar['displaypercentsign']);

                    // draw the progress bar
                    $newpdf->SetDrawColor(0,0,0);
                    $newpdf->SetFillColor(255,255,255);
                    $newpdf->Rect($initial_x,$initial_y+0.04,$full_width,0.16,'FD');
                    if ($fill_width > 0) {
                        $newpdf->SetFillColor(0,255,0);
                        $newpdf->Rect($initial_x,$initial_y+0.04,$fill_width,0.155,'F');
                    }

                    // draw the overlay text
                    $newpdf->MultiCell($widths[$id], 0.25, $display_text, 0, $cell_align, 0);
                } else {
                    // draw the text
                    $newpdf->MultiCell($widths[$id], 0.2, $text, 0, $cell_align, 0);
                }

                $y_offset = $newpdf->GetY() - $initial_y;
                if ($y_offset > $max_y_offset) {
                    $max_y_offset = $y_offset;
                }

                $newpdf->SetXY($initial_x + $widths[$id], $initial_y);
            }
        }

        $newpdf->Ln($max_y_offset);

        //return the number of inches of vertical offset introduced
        return $max_y_offset;
    }

    /**
     * Renders one row of data to the PDF
     *
     * @param   FPDF        newpdf   The PDF object we are creating
     * @param   stdClass    datum    The current row of data
     * @param   array       widths   Mapping of columns to widths
     * @param   int         height   Height of the current row
     * @param   array|NULL  colour   R, G, and B values for a specific colour, or NULL if not applicable
     *
     * @return  int                  The height of the rendered row, in inches
     */
    protected function render_pdf_entry($newpdf, $datum, $widths, $height, $colour = NULL) {
        //do a test render to calculate background height
        $test_pdf = $this->initialize_pdf();

        //set the test PDF to being bolded (or any other style) if our
        //main PDF is currently outputting bold text
        $style = $newpdf->getFontStyle();
        $this->set_font_style($test_pdf, $style);

        $result = $this->render_pdf_entry_content($test_pdf, $datum, $widths);

        //manually break the page if the height exceeds the remaining space
        if (($newpdf->GetY() + $result) > ($newpdf->getPageHeight() - $newpdf->getBreakMargin())) {
            //flush the page
            $newpdf->AddPage();
        }

        if ($colour !== NULL) {
            //a specific colour is specified for this row

            //draw the background
            $newpdf->SetFillColor($colour[0], $colour[1], $colour[2]);
            $newpdf->Rect(self::marginx, $newpdf->getY(), self::page_width - 2 * self::marginx, $result, 'F');
        }

        //draw the actual content
        return $this->render_pdf_entry_content($newpdf, $datum, $widths);
    }

    /**
     * Populates the contents of the PDF object being created
     *
     * @param  FPDF reference  $newpdf   Reference to the PDF object being created
     * @param  string          $query    The main report query
     * @param  array           $params   SQL query params
     */
    protected function render_pdf_instance(&$newpdf, $query, $params) {
        //print the report name
        $this->render_report_name($newpdf);

        //print an appropriate header
        $this->report->print_pdf_header($newpdf);

        $heights = array();
        $hmap    = array();
        $rownum  = 0;

    /// PASS 1 - Calculate sizes.
        $min_widths = array();
        $widths = $this->calculate_pdf_column_header_widths($newpdf, $min_widths);

        $column_based_summary_row = $this->get_pdf_column_based_summary_row();

        //determine whether a record is found
        $found_record = false;
        $row = $this->update_pdf_column_sizes_from_data($newpdf, $query, $params, $widths, $heights, $found_record, $min_widths);

        if (!$found_record) {
            //no data, so close out the pdf
            //ELISAT-368: output the headers
            $this->render_pdf_headers($newpdf);

            //line break
            $newpdf->Ln(0.2);
            //no data message
            $newpdf->MultiCell(self::page_width - 2 * self::marginx, 0.2, get_string('no_report_data', 'block_php_report'), 0, 'C');

            return;
        }

        //update column widths based on columnar footer data if appropriate
        if($column_based_summary_row !== null) {
            $this->update_pdf_sizings($newpdf, $column_based_summary_row, $widths, $heights, $row, $min_widths);
        }

        //Normalize the widths of the table columns
        $this->normalize_widths($widths, $min_widths);

        //setting margins does not automatically reposition
        $newpdf->SetX(self::marginx);

        $this->render_pdf_headers($newpdf);

        //used to track if we need to display column headers
        //after a heading entry
        $need_columns_header = false;

        if (empty($this->report->groupings)) {
            //display column headers now because we know we have at least one record
            $this->render_pdf_column_headers($newpdf, $widths);
        } else {
            //flag that we need to display headers when we're done with the first set of
            //above-column-header header entries
            $need_columns_header = true;
        }

        $row = $this->render_pdf_core_data($newpdf, $query, $params, $widths, $heights, $need_columns_header);

        if ($column_based_summary_row !== NULL) {
            //use the report-defined background colour
            $colour = $this->report->get_column_based_summary_colour();
            $this->render_pdf_entry($newpdf, $column_based_summary_row, $widths, $heights[$row], $colour);
        }
    }

    protected function progress_bar_get_text($value, $total, $fraction, $text, $display_percent_sign) {
        $text = str_replace('$v', $value, $text);
        $text = str_replace('$t', $total, $text);

        $percentage_shown = round(100 * $fraction);
        if(!empty($display_percent_sign)) {
            $percentage_shown .= get_string('percent_symbol', 'block_php_report');
        }

        $text = str_replace('$p', $total == 0 ? get_string('na', 'block_php_report') : $percentage_shown, $text);

        $text = str_replace('$e', $total == 0 ? get_string('na', 'block_php_report') : '', $text);

        return $text;
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
     * @param  array   $params        SQL query params
     * @param  string  $storage_path  Path on the file system to save the output to,
     *                                or NULL if sending to browser
     * @param  $filename              Filename to use when sending to browser
     */
    function export($query, $params, $storage_path, $filename) {
        global $CFG;

        $filename .= '.pdf';

        $newpdf = $this->initialize_pdf();

        $this->render_pdf_instance($newpdf, $query, $params);

        $this->output_pdf_file($newpdf, $storage_path, $filename);
    }

}

