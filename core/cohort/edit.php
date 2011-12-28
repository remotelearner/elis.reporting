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
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    core
 * @subpackage cohort
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/cohort/lib.php');
require($CFG->dirroot.'/cohort/edit_form.php');

$id        = optional_param('id', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$category = null;
if ($id) {
    $cohort = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
    $context = get_context_instance_by_id($cohort->contextid, MUST_EXIST);
} else {
    $context = get_context_instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
        print_error('invalidcontext');
    }
    $cohort = new stdClass();
    $cohort->id          = 0;
    $cohort->contextid   = $context->id;
    $cohort->name        = '';
    $cohort->description = '';
}

require_capability('moodle/cohort:manage', $context);

$returnurl = new moodle_url('/cohort/index.php', array('contextid'=>$context->id));

if (!empty($cohort->component)) {
    // we can not manually edit cohorts that were created by external systems, sorry
    redirect($returnurl);
}

$PAGE->set_context($context);
$PAGE->set_url('/cohort/edit.php', array('contextid'=>$context->id, 'id'=>$cohort->id));
$PAGE->set_context($context);

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    $PAGE->navbar->add($category->name, new moodle_url('/course/index.php', array('categoryedit'=>'1')));
}
$PAGE->navbar->add(get_string('cohorts', 'cohort'), new moodle_url('/cohort/', array('contextid'=>$context->id)));

if ($delete and $cohort->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        cohort_delete_cohort($cohort);
        redirect($returnurl);
    }
    $strheading = get_string('delcohort', 'cohort');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/cohort/edit.php', array('id'=>$cohort->id, 'delete'=>1, 'confirm'=>1,'sesskey'=>sesskey()));
    $message = get_string('delconfirm', 'cohort', format_string($cohort->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

$editoroptions = array('maxfiles'=>0, 'context'=>$context);
if ($cohort->id) {
    // edit existing
    $cohort = file_prepare_standard_editor($cohort, 'description', $editoroptions, $context);
    $strheading = get_string('editcohort', 'cohort');

} else {
    // add new
    $cohort = file_prepare_standard_editor($cohort, 'description', $editoroptions, $context);
    $strheading = get_string('addcohort', 'cohort');
}

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

$editform = new cohort_edit_form(null, array('editoroptions'=>$editoroptions, 'data'=>$cohort));

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {
    $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context);

    if ($data->id) {
        cohort_update_cohort($data);
    } else {
        cohort_add_cohort($data);
    }

    // use new context id, it could have been changed
    redirect(new moodle_url('/cohort/index.php', array('contextid'=>$data->contextid)));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);
echo $editform->display();
echo $OUTPUT->footer();

