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
 * System Public Profile.
 *
 * This script allows the site administrator to edit the default site
 * profile.
 *
 * @package    moodlecore
 * @subpackage my
 * @copyright  2010 Remote-Learner.net
 * @author     Hubert Chathi <hubert@remote-learner.net>
 * @author     Olav Jordan <olav.jordan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$edit   = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off

require_login();

$context = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/my:configsyspages', $context);
$PAGE->set_blocks_editing_capability('moodle/my:configsyspages');
$header = "$SITE->shortname: ".get_string('publicprofile')." (".get_string('myprofile', 'admin').")";

// Start setting up the page
$params = array();
$PAGE->set_url('/user/profilesys.php', $params);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('user-profile');
$PAGE->set_context($context);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->blocks->add_region('content');

// TODO: Make the page be selected properly in the Settings block

// Get the Public Profile page info.  Should always return something unless the database is broken.
if (!$currentpage = my_get_page(null, MY_PAGE_PUBLIC)) {
    print_error('publicprofilesetup');
}
$PAGE->set_subpage($currentpage->id);


// Toggle the editing state and switches
if ($PAGE->user_allowed_editing()) {
    if ($edit !== null) {             // Editing state was specified
        $USER->editing = $edit;       // Change editing state
    } else {                          // Editing state is in session
        if (!empty($USER->editing)) {
            $edit = 1;
        } else {
            $edit = 0;
        }
    }

    // Add button for editing page
    $params['edit'] = !$edit;

    if (empty($edit)) {
        $editstring = get_string('updatemymoodleon');
    } else {
        $editstring = get_string('updatemymoodleoff');
    }

    $url = new moodle_url("$CFG->wwwroot/user/profilesys.php", $params);
    $button = $OUTPUT->single_button($url, $editstring);
    $PAGE->set_button($button);

} else {
    $USER->editing = $edit = 0;
}

echo $OUTPUT->header();

echo $OUTPUT->blocks_for_region('content');

echo $OUTPUT->footer();
