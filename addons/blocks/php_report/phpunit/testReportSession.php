<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    blocks
 * @subpackage php_report
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(dirname(__FILE__).'/../lib/filtering.php');

class testReportSession extends PHPUnit_Framework_TestCase {

    // Verify that previous session data was successfully removed
    public function testReportSessions() {
        global $SESSION;

        $oldsessiondata = array();
        $oldsessiondata['php_report_registrants_by_course/showdr_sck'] = 1;
        $oldsessiondata['php_report_registrants_by_course/showdr_sdt'] = 1336622400;
        $oldsessiondata['php_report_registrants_by_course/showdr_edt'] = 1436622400;
        $oldsessiondata['php_report_registrants_by_course/showdr_eck'] = 1;

        // Create data representing an old session
        foreach ($oldsessiondata as $key => $val) {
            $SESSION->php_report_default_params[$key] = $val;
        }

        $newsessiondata = array();
        // Create data representing a new session
        $newsessiondata['php_report_registrants_by_course/showdr_sck'] = 1;
        $newsessiondata['php_report_registrants_by_course/showdr_sdt'] = 1336622401;
        $newsessiondata['php_report_registrants_by_course/showdr_edt'] = 1436622401;
        php_report_filtering_set_user_preferences($newsessiondata, true, 'registrants_by_course');

        // Verify that the function php_report_filtering_set_user_preferences has removed the previous session data
        $this->assertEquals(array_values($SESSION->php_report_default_params), array_values($newsessiondata));
    }

}

?>
