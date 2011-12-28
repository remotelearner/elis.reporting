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
 * Defines the renderer for the deferred feedback with certainty based marking
 * behaviour.
 *
 * @package    qbehaviour
 * @subpackage deferredcbm
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Renderer for outputting parts of a question belonging to the deferred
 * feedback with certainty based marking behaviour.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_deferredcbm_renderer extends qbehaviour_renderer {
    protected function certainly_choices($controlname, $selected, $readonly) {
        $attributes = array(
            'type' => 'radio',
            'name' => $controlname,
        );
        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }

        $choices = '';
        foreach (question_cbm::$certainties as $certainty) {
            $id = $controlname . $certainty;
            $attributes['id'] = $id;
            $attributes['value'] = $certainty;
            if ($selected == $certainty) {
                $attributes['checked'] = 'checked';
            } else {
                unset($attributes['checked']);
            }
            $choices .= ' ' . html_writer::empty_tag('input', $attributes) . ' ' .
                    html_writer::tag('label', question_cbm::get_string($certainty),
                            array('for' => $id));
        }
        return $choices;
    }

    public function controls(question_attempt $qa, question_display_options $options) {
        return html_writer::tag('div', get_string('howcertainareyou', 'qbehaviour_deferredcbm',
                $this->certainly_choices($qa->get_behaviour_field_name('certainty'),
                $qa->get_last_behaviour_var('certainty'), $options->readonly)),
                array('class' => 'certaintychoices'));
    }

    public function feedback(question_attempt $qa, question_display_options $options) {
        if (!$options->feedback) {
            return '';
        }

        if ($qa->get_state() == question_state::$gaveup || $qa->get_state() ==
                question_state::$mangaveup) {
            return '';
        }

        $feedback = '';
        if (!$qa->get_last_behaviour_var('certainty') &&
                $qa->get_last_behaviour_var('_assumedcertainty')) {
            $feedback .= html_writer::tag('p',
                    get_string('assumingcertainty', 'qbehaviour_deferredcbm',
                    question_cbm::get_string($qa->get_last_behaviour_var('_assumedcertainty'))));
        }

        if ($options->marks >= question_display_options::MARK_AND_MAX) {
            $a->rawmark = format_float($qa->get_last_behaviour_var('_rawfraction') *
                    $qa->get_max_mark(), $options->markdp);
            $a->mark = $qa->format_mark($options->markdp);
            $feedback .= html_writer::tag('p',
                    get_string('markadjustment', 'qbehaviour_deferredcbm', $a));
        }

        return $feedback;
    }
}