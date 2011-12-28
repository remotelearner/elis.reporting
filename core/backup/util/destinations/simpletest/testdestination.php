<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package moodlecore
 * @subpackage backup-tests
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Prevent direct access to this file
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

// Include all the needed stuff
//require_once($CFG->dirroot . '/backup/util/helper/backup_helper.class.php');

/*
 * dbops tests (all)
 */
class backup_destinations_test extends UnitTestCase {

    public static $includecoverage = array('backup/util/destinations');
    public static $excludecoverage = array('backup/util/destinations/simpletest');

    /*
     * test backup_destination class
     */
    function test_backup_destination() {
    }

    /*
     * test backup_destination_osfs class
     */
    function test_backup_destination_osfs() {
    }
}
