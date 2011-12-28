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
 * Defines message providers (types of messages being sent)
 *
 * @package    core
 * @subpackage message
 * @copyright  2008 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = array (

/// Notices that an admin might be interested in
    'notices' => array (
         'capability'  => 'moodle/site:config'
    ),

/// Important errors that an admin ought to know about
    'errors' => array (
         'capability'  => 'moodle/site:config'
    ),

    'instantmessage' => array (
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDOFF,
        ),
    ),

    'backup' => array (
        'capability'  => 'moodle/site:config'
    ),

    //course creation request notification
    'courserequested' => array (
        'capability'  => 'moodle/site:approvecourse'
    ),

    //course request approval notification
    'courserequestapproved' => array (
         'capability'  => 'moodle/course:request'
    ),

    //course request rejection notification
    'courserequestrejected' => array (
        'capability'  => 'moodle/course:request'
    )

);
