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
 * Allows the admin to manage question behaviours.
 *
 * @package    moodlecore
 * @subpackage questionengine
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

// Check permissions.
require_login();
$systemcontext = get_context_instance(CONTEXT_SYSTEM);
require_capability('moodle/question:config', $systemcontext);

admin_externalpage_setup('manageqbehaviours');
$thispageurl = new moodle_url('/admin/qbehaviours.php');

$behaviours = get_plugin_list('qbehaviour');

// Get some data we will need - question counts and which types are needed.
$counts = $DB->get_records_sql_menu("
        SELECT behaviour, COUNT(1)
        FROM {question_attempts} GROUP BY behaviour");
$needed = array();
$archetypal = array();
foreach ($behaviours as $behaviour => $notused) {
    if (!array_key_exists($behaviour, $counts)) {
        $counts[$behaviour] = 0;
    }
    $needed[$behaviour] = $counts[$behaviour] > 0;
    $archetypal[$behaviour] = question_engine::is_behaviour_archetypal($behaviour);
}

foreach ($behaviours as $behaviour => $notused) {
    foreach (question_engine::get_behaviour_required_behaviours($behaviour) as $reqbehaviour) {
        $needed[$reqbehaviour] = true;
    }
}
foreach ($counts as $behaviour => $count) {
    if (!array_key_exists($behaviour, $behaviours)) {
        $counts['missingtype'] += $count;
    }
}

// Work of the correct sort order.
$config = get_config('question');
$sortedbehaviours = array();
foreach ($behaviours as $behaviour => $notused) {
    $sortedbehaviours[$behaviour] = question_engine::get_behaviour_name($behaviour);
}
if (!empty($config->behavioursortorder)) {
    $sortedbehaviours = question_engine::sort_behaviours($sortedbehaviours,
            $config->behavioursortorder, '');
}

if (!empty($config->disabledbehaviours)) {
    $disabledbehaviours = explode(',', $config->disabledbehaviours);
} else {
    $disabledbehaviours = array();
}

// Process actions ============================================================

// Disable.
if (($disable = optional_param('disable', '', PARAM_SAFEDIR)) && confirm_sesskey()) {
    if (!isset($behaviours[$disable])) {
        print_error('unknownbehaviour', 'question', $thispageurl, $disable);
    }

    if (array_search($disable, $disabledbehaviours) === false) {
        $disabledbehaviours[] = $disable;
        set_config('disabledbehaviours', implode(',', $disabledbehaviours), 'question');
    }
    redirect($thispageurl);
}

// Enable.
if (($enable = optional_param('enable', '', PARAM_SAFEDIR)) && confirm_sesskey()) {
    if (!isset($behaviours[$enable])) {
        print_error('unknownbehaviour', 'question', $thispageurl, $enable);
    }

    if (!$archetypal[$enable]) {
        print_error('cannotenablebehaviour', 'question', $thispageurl, $enable);
    }

    if (($key = array_search($enable, $disabledbehaviours)) !== false) {
        unset($disabledbehaviours[$key]);
        set_config('disabledbehaviours', implode(',', $disabledbehaviours), 'question');
    }
    redirect($thispageurl);
}

// Move up in order.
if (($up = optional_param('up', '', PARAM_SAFEDIR)) && confirm_sesskey()) {
    if (!isset($behaviours[$up])) {
        print_error('unknownbehaviour', 'question', $thispageurl, $up);
    }

    // This function works fine for behaviours, as well as qtypes.
    $neworder = question_reorder_qtypes($sortedbehaviours, $up, -1);
    set_config('behavioursortorder', implode(',', $neworder), 'question');
    redirect($thispageurl);
}

// Move down in order.
if (($down = optional_param('down', '', PARAM_SAFEDIR)) && confirm_sesskey()) {
    if (!isset($behaviours[$down])) {
        print_error('unknownbehaviour', 'question', $thispageurl, $down);
    }

    // This function works fine for behaviours, as well as qtypes.
    $neworder = question_reorder_qtypes($sortedbehaviours, $down, +1);
    set_config('behavioursortorder', implode(',', $neworder), 'question');
    redirect($thispageurl);
}

// Delete.
if (($delete = optional_param('delete', '', PARAM_SAFEDIR)) && confirm_sesskey()) {
    // Check it is OK to delete this question type.
    if ($delete == 'missing') {
        print_error('cannotdeletemissingbehaviour', 'question', $thispageurl);
    }

    if (!isset($behaviours[$delete])) {
        print_error('unknownbehaviour', 'question', $thispageurl, $delete);
    }

    $behaviourname = $sortedbehaviours[$delete];
    if ($counts[$delete] > 0) {
        print_error('cannotdeletebehaviourinuse', 'question', $thispageurl, $behaviourname);
    }
    if ($needed[$delete] > 0) {
        print_error('cannotdeleteneededbehaviour', 'question', $thispageurl, $behaviourname);
    }

    // If not yet confirmed, display a confirmation message.
    if (!optional_param('confirm', '', PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('deletebehaviourareyousure', 'question', $behaviourname));
        echo $OUTPUT->confirm(
                get_string('deletebehaviourareyousuremessage', 'question', $behaviourname),
                new moodle_url($thispageurl, array('delete' => $delete, 'confirm' => 1)),
                $thispageurl);
        echo $OUTPUT->footer();
        exit;
    }

    // Do the deletion.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletingbehaviour', 'question', $behaviourname));

    // Delete any configuration records.
    if (!unset_all_config_for_plugin('qbehaviour_' . $delete)) {
        echo $OUTPUT->notification(get_string('errordeletingconfig', 'admin', 'qbehaviour_' . $delete));
    }
    if (($key = array_search($delete, $disabledbehaviours)) !== false) {
        unset($disabledbehaviours[$key]);
        set_config('disabledbehaviours', implode(',', $disabledbehaviours), 'question');
    }
    $behaviourorder = explode(',', $config->behavioursortorder);
    if (($key = array_search($delete, $behaviourorder)) !== false) {
        unset($behaviourorder[$key]);
        set_config('behavioursortorder', implode(',', $behaviourorder), 'question');
    }

    // Then the tables themselves
    drop_plugin_tables($delete, get_plugin_directory('qbehaviour', $delete) . '/db/install.xml', false);

    // Remove event handlers and dequeue pending events
    events_uninstall('qbehaviour_' . $delete);

    $a->behaviour = $behaviourname;
    $a->directory = get_plugin_directory('qbehaviour', $delete);
    echo $OUTPUT->box(get_string('qbehaviourdeletefiles', 'question', $a), 'generalbox', 'notice');
    echo $OUTPUT->continue_button($thispageurl);
    echo $OUTPUT->footer();
    exit;
}

// End of process actions ==================================================

// Print the page heading.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageqbehaviours', 'admin'));

// Set up the table.
$table = new flexible_table('qbehaviouradmintable');
$table->define_baseurl($thispageurl);
$table->define_columns(array('behaviour', 'numqas', 'version', 'requires',
        'available', 'delete'));
$table->define_headers(array(get_string('behaviour', 'question'), get_string('numqas', 'question'),
        get_string('version'), get_string('requires', 'admin'),
        get_string('availableq', 'question'), get_string('delete')));
$table->set_attribute('id', 'qbehaviours');
$table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide');
$table->setup();

// Add a row for each question type.
foreach ($sortedbehaviours as $behaviour => $behaviourname) {
    $row = array();

    // Question icon and name.
    $row[] = $behaviourname;

    // Count
    $row[] = $counts[$behaviour];

    // Question version number.
    $version = get_config('qbehaviour_' . $behaviour, 'version');
    if ($version) {
        $row[] = $version;
    } else {
        $row[] = html_writer::tag('span', get_string('nodatabase', 'admin'), array('class' => 'disabled'));
    }

    // Other question types required by this one.
    $requiredbehaviours = question_engine::get_behaviour_required_behaviours($behaviour);
    if (!empty($requiredbehaviours)) {
        $strrequiredbehaviours = array();
        foreach ($requiredbehaviours as $required) {
            $strrequiredbehaviours[] = $sortedbehaviours[$required];
        }
        $row[] = implode(', ', $strrequiredbehaviours);
    } else {
        $row[] = '';
    }

    // Are people allowed to create new questions of this type?
    $rowclass = '';
    if ($archetypal[$behaviour]) {
        $enabled = array_search($behaviour, $disabledbehaviours) === false;
        $icons = question_behaviour_enable_disable_icons($behaviour, $enabled);
        if (!$enabled) {
            $rowclass = 'dimmed_text';
        }
    } else {
        $icons = $OUTPUT->spacer() . ' ';
    }

    // Move icons.
    $icons .= question_behaviour_icon_html('up', $behaviour, 't/up', get_string('up'), null);
    $icons .= question_behaviour_icon_html('down', $behaviour, 't/down', get_string('down'), null);
    $row[] = $icons;

    // Delete link, if available.
    if ($needed[$behaviour]) {
        $row[] = '';
    } else {
        $row[] = html_writer::link(new moodle_url($thispageurl,
                array('delete' => $behaviour, 'sesskey' => sesskey())), get_string('delete'),
                array('title' => get_string('uninstallbehaviour', 'question')));
    }

    $table->add_data($row, $rowclass);
}

$table->finish_output();

echo $OUTPUT->footer();

function question_behaviour_enable_disable_icons($behaviour, $enabled) {
    if ($enabled) {
        return question_behaviour_icon_html('disable', $behaviour, 'i/hide',
                get_string('enabled', 'question'), get_string('disable'));
    } else {
        return question_behaviour_icon_html('enable', $behaviour, 'i/show',
                get_string('disabled', 'question'), get_string('enable'));
    }
}

function question_behaviour_icon_html($action, $behaviour, $icon, $alt, $tip) {
    global $OUTPUT;
    return $OUTPUT->action_icon(new moodle_url('/admin/qbehaviours.php',
            array($action => $behaviour, 'sesskey' => sesskey())),
            new pix_icon($icon, $alt, 'moodle', array('title' => '')),
            null, array('title' => $tip)) . ' ';
}

