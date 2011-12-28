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
 * This page prints a summary of a quiz attempt before it is submitted.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$attemptid = required_param('attempt', PARAM_INT); // The attempt to summarise.

$PAGE->set_url('/mod/quiz/summary.php', array('attempt' => $attemptid));

$attemptobj = quiz_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// If this is not our own attempt, display an error.
if ($attemptobj->get_userid() != $USER->id) {
    print_error('notyourattempt', 'quiz', $attemptobj->view_url());
}

// Check capabilites.
if (!$attemptobj->is_preview_user()) {
    $attemptobj->require_capability('mod/quiz:attempt');
}

// If the attempt is already closed, redirect them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url());
}

if ($attemptobj->is_preview_user()) {
    navigation_node::override_active_url($attemptobj->start_attempt_url());
}

// Check access.
$accessmanager = $attemptobj->get_access_manager(time());
$messages = $accessmanager->prevent_access();
$output = $PAGE->get_renderer('mod_quiz');
if (!$attemptobj->is_preview_user() && $messages) {
    print_error('attempterror', 'quiz', $attemptobj->view_url(),
            $output->access_messages($messages));
}
$accessmanager->do_password_check($attemptobj->is_preview_user());

$displayoptions = $attemptobj->get_display_options(false);

// Log this page view.
add_to_log($attemptobj->get_courseid(), 'quiz', 'view summary',
        'summary.php?attempt=' . $attemptobj->get_attemptid(),
        $attemptobj->get_quizid(), $attemptobj->get_cmid());

// Print the page header
if (empty($attemptobj->get_quiz()->showblocks)) {
    $PAGE->blocks->show_only_fake_blocks();
}

if ($accessmanager->securewindow_required($attemptobj->is_preview_user())) {
    $accessmanager->setup_secure_page($attemptobj->get_course()->shortname . ': ' .
            format_string($attemptobj->get_quiz_name()), '');
} else if ($accessmanager->safebrowser_required($attemptobj->is_preview_user())) {
    $PAGE->set_title($attemptobj->get_course()->shortname . ': ' .
            format_string($attemptobj->get_quiz_name()));
    $PAGE->set_heading($attemptobj->get_course()->fullname);
    $PAGE->set_cacheable(false);
} else {
    $PAGE->navbar->add(get_string('summaryofattempt', 'quiz'));
    $PAGE->set_title(format_string($attemptobj->get_quiz_name()));
    $PAGE->set_heading($attemptobj->get_course()->fullname);
}

// Print heading.

$accessmanager->show_attempt_timer_if_needed($attemptobj->get_attempt(), time());
echo $output->summary_page($attemptobj, $displayoptions);
