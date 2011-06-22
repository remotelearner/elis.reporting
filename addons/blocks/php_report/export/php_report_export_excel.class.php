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
 * TO DO: enable wrapping of table headers
 *
 * @package    elis
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot . '/blocks/php_report/export/php_report_export.class.php');

class php_report_export_excel extends php_report_export {

    /**
     * Create a new instance of an Excel report export
     *
     * @param  php_report  $report  A reference to the report being exported
     */
    function php_report_export_excel(&$report) {
        $this->report =& $report;
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
     * @param  string  $storage_path  Path on the file system to save the output to,
     *                                or NULL if sending to browser
     * @param  $filename              Filename to use when sending to browser
     */
    function export($query, $storage_path, $filename) {
        global $CFG;
        require_once($CFG->libdir . '/excellib.class.php');

        $filename .= '.xls';

    /// Creating a workbook
        $workbook = new MoodleExcelWorkbook('-');

    /// Sending HTTP headers
        $workbook->send($filename);

    /// Creating the first worksheet
        $sheettitle  = get_string('studentprogress', 'reportstudentprogress');
        $myxls      =& $workbook->add_worksheet($sheettitle);

    /// Format types
        $format =& $workbook->add_format();
        $format->set_bold(0);
        $formatbc =& $workbook->add_format();
        $formatbc->set_bold(1);
        $formatbc->set_align('center');
        $formatb =& $workbook->add_format();
        $formatb->set_bold(1);
        $formaty =& $workbook->add_format();
        $formaty->set_bg_color('yellow');
        $formatc =& $workbook->add_format();
        $formatc->set_align('center');
        $formatr =& $workbook->add_format();
        $formatr->set_bold(1);
        $formatr->set_color('red');
        $formatr->set_align('center');
        $formatg =& $workbook->add_format();
        $formatg->set_bold(1);
        $formatg->set_color('green');
        $formatg->set_align('center');

        $rownum = 0;
        $colnum = 0;

        foreach ($this->report->headers as $header) {
            $myxls->write($rownum, $colnum++, $header, $formatbc);
        }

        foreach ($this->report->data as $datum) {
            if (!is_object($datum)) {
                continue;
            }

            $rownum++;
            $colnum = 0;

            foreach ($this->headers as $id => $header) {
                if (isset($datum->$id)) {
                    $myxls->write($rownum, $colnum++, $datum->$id, $format);
                } else {
                    $myxls->write($rownum, $colnum++, '', $format);
                }
            }
        }

         $workbook->close();
    }

}

?>