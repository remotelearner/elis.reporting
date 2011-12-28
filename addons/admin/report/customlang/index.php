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
 * Performs checkout of the strings into the translation table
 *
 * @package    report
 * @subpackage customlang
 * @copyright  2010 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true); // progress bar is used here

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/'.$CFG->admin.'/report/customlang/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

require_login(SITEID, false);
require_capability('report/customlang:view', get_system_context());

$action  = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', false, PARAM_BOOL);
$lng     = optional_param('lng', '', PARAM_LANG);

admin_externalpage_setup('reportcustomlang');
$langs = get_string_manager()->get_list_of_translations();

// pre-output actions
if ($action === 'checkout') {
    require_sesskey();
    require_capability('report/customlang:edit', get_system_context());
    if (empty($lng)) {
        print_error('missingparameter');
    }

    $PAGE->set_cacheable(false);    // progress bar is used here
    $output = $PAGE->get_renderer('report_customlang');
    echo $output->header();
    echo $output->heading(get_string('pluginname', 'report_customlang'));
    $progressbar = new progress_bar();
    $progressbar->create();         // prints the HTML code of the progress bar

    // we may need a bit of extra execution time and memory here
    @set_time_limit(HOURSECS);
    raise_memory_limit(MEMORY_EXTRA);
    report_customlang_utils::checkout($lng, $progressbar);

    echo $output->continue_button(new moodle_url('/admin/report/customlang/edit.php', array('lng' => $lng)), 'get');
    echo $output->footer();
    exit;
}

if ($action === 'checkin') {
    require_sesskey();
    require_capability('report/customlang:edit', get_system_context());
    if (empty($lng)) {
        print_error('missingparameter');
    }

    if (!$confirm) {
        $output = $PAGE->get_renderer('report_customlang');
        echo $output->header();
        echo $output->heading(get_string('pluginname', 'report_customlang'));
        echo $output->heading($langs[$lng], 3);
        $numofmodified = report_customlang_utils::get_count_of_modified($lng);
        if ($numofmodified != 0) {
            echo $output->heading(get_string('modifiednum', 'report_customlang', $numofmodified), 3);
            echo $output->confirm(get_string('confirmcheckin', 'report_customlang'),
                                  new moodle_url($PAGE->url, array('action'=>'checkin', 'lng'=>$lng, 'confirm'=>1)),
                                  new moodle_url($PAGE->url, array('lng'=>$lng)));
        } else {
            echo $output->heading(get_string('modifiedno', 'report_customlang', $numofmodified), 3);
            echo $output->continue_button(new moodle_url($PAGE->url, array('lng' => $lng)));
        }
        echo $output->footer();
        die();

    } else {
        report_customlang_utils::checkin($lng);
        redirect($PAGE->url);
    }
}

$output = $PAGE->get_renderer('report_customlang');

// output starts here
echo $output->header();
echo $output->heading(get_string('pluginname', 'report_customlang'));

if (empty($lng)) {
    $s = new single_select($PAGE->url, 'lng', $langs);
    $s->label = get_accesshide(get_string('language'));
    $s->class = 'langselector';
    echo $output->box($OUTPUT->render($s), 'langselectorbox');
    echo $OUTPUT->footer();
    exit;
}

echo $output->heading($langs[$lng], 3);

$numofmodified = report_customlang_utils::get_count_of_modified($lng);

if ($numofmodified != 0) {
    echo $output->heading(get_string('modifiednum', 'report_customlang', $numofmodified), 3);
}

$menu = array();
if (has_capability('report/customlang:edit', get_system_context())) {
    $menu['checkout'] = array(
        'title'     => get_string('checkout', 'report_customlang'),
        'url'       => new moodle_url($PAGE->url, array('action' => 'checkout', 'lng' => $lng)),
        'method'    => 'post',
    );
    if ($numofmodified != 0) {
        $menu['checkin'] = array(
            'title'     => get_string('checkin', 'report_customlang'),
            'url'       => new moodle_url($PAGE->url, array('action' => 'checkin', 'lng' => $lng)),
            'method'    => 'post',
        );
    }
}
echo $output->render(new report_customlang_menu($menu));

echo $output->footer();
