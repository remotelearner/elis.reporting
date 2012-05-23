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
 * @subpackage pm-blocks-phpreport-class_roster
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

/**
 * Generate a JSON data set containing all the classes belonging to the specified course
 */

require_once(dirname(__FILE__) .'/../../../../config.php');

require_once($CFG->dirroot .'/blocks/php_report/instances/class_roster/class_roster_report.class.php');
require_once($CFG->dirroot .'/elis/program/lib/setup.php');
require_once($CFG->dirroot .'/elis/program/lib/contexts.php');
require_once($CFG->dirroot .'/elis/program/lib/data/pmclass.class.php');

global $USER;

if (!isloggedin() || isguestuser()) {
    mtrace("ERROR: must be logged in!");
    exit;
}

$ids = optional_param('id', 0, PARAM_INT);
if (!$ids) {
    $ids = array();
}
if (!is_array($ids)) {
    $ids = array($ids);
}

$choices_array = array(array('', get_string('selectclass', 'rlreport_class_roster')));

if (count($ids) > 0) {
    $contexts = get_contexts_by_capability_for_user('class', 'block/php_report:view', $USER->id);
    if ($records = pmclass_get_listing('idnumber', 'ASC', 0, 0, '', '', $ids, false, $contexts)) {
        foreach ($records as $record) {
            $choices_array[] = array($record->id, $record->idnumber);
        }
    }
}

echo json_encode($choices_array);

