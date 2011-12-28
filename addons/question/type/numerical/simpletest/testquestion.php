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
 * Unit tests for the numerical question definition class.
 *
 * @package    qtype
 * @subpackage numerical
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');


/**
 * Unit tests for the numerical question definition class.
 *
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_question_test extends UnitTestCase {
    public function test_is_complete_response() {
        $question = test_question_maker::make_question('numerical');

        $this->assertFalse($question->is_complete_response(array()));
        $this->assertTrue($question->is_complete_response(array('answer' => '0')));
        $this->assertTrue($question->is_complete_response(array('answer' => 0)));
        $this->assertFalse($question->is_complete_response(array('answer' => 'test')));
    }

    public function test_is_gradable_response() {
        $question = test_question_maker::make_question('numerical');

        $this->assertFalse($question->is_gradable_response(array()));
        $this->assertTrue($question->is_gradable_response(array('answer' => '0')));
        $this->assertTrue($question->is_gradable_response(array('answer' => 0)));
        $this->assertTrue($question->is_gradable_response(array('answer' => 'test')));
    }

    public function test_grading() {
        $question = test_question_maker::make_question('numerical');

        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '1.0')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '3.14')));
    }

    public function test_grading_with_units() {
        $question = test_question_maker::make_question('numerical');
        $question->unitgradingtype = qtype_numerical::UNITOPTIONAL;
        $question->ap = new qtype_numerical_answer_processor(
                array('m' => 1, 'cm' => 100), false, '.', ',');

        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '3.14 frogs')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '3.14')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '3.14 m')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '314cm')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '314000000x10^-8m')));
    }

    public function test_grading_with_units_graded() {
        $question = test_question_maker::make_question('numerical');
        $question->unitgradingtype = qtype_numerical::UNITGRADED;
        $question->ap = new qtype_numerical_answer_processor(
                array('m' => 1, 'cm' => 100), false, '.', ',');

        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '3.14 frogs')));
        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '3.14')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '3.14 m')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '314cm')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '314000000x10^-8m')));
        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '3.14 cm')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '314 m')));
    }

    public function test_grading_unit() {
        $question = test_question_maker::make_question('numerical', 'unit');

        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '2', 'unit' => 'm')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '2', 'unit' => 'cm')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '2', 'unit' => '')));

        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '1.25', 'unit' => 'm')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '125', 'unit' => 'cm')));
        $this->assertEqual(array(0.5, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '1.25', 'unit' => '')));

        $this->assertEqual(array(0.5, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '1.23', 'unit' => 'm')));
        $this->assertEqual(array(0.5, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '123', 'unit' => 'cm')));
        $this->assertEqual(array(0.25, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '1.23', 'unit' => '')));
    }

    public function test_grading_currency() {
        $question = test_question_maker::make_question('numerical', 'currency');

        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '$1332')));
        $this->assertEqual(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => '$ 1332')));
        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => 'frog 1332')));
        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => '1332')));
        $this->assertEqual(array(0.8, question_state::$gradedpartial),
                $question->grade_response(array('answer' => ' 1332')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '1332 $')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '1332 frogs')));
        $this->assertEqual(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => '$1')));
    }

    public function test_get_correct_response() {
        $question = test_question_maker::make_question('numerical');

        $this->assertEqual(array('answer' => '3.14'),
                $question->get_correct_response());
    }

    public function test_get_correct_response_units() {
        $question = test_question_maker::make_question('numerical', 'unit');

        $this->assertEqual(array('answer' => '1.25', 'unit' => 'm'),
                $question->get_correct_response());
    }

    public function test_get_correct_response_currency() {
        $question = test_question_maker::make_question('numerical', 'currency');

        $this->assertEqual(array('answer' => '$ 1332'),
                $question->get_correct_response());
    }

    public function test_get_question_summary() {
        $num = test_question_maker::make_question('numerical');
        $qsummary = $num->get_question_summary();
        $this->assertEqual('What is pi to two d.p.?', $qsummary);
    }

    public function test_summarise_response() {
        $num = test_question_maker::make_question('numerical');
        $this->assertEqual('3.1', $num->summarise_response(array('answer' => '3.1')));
    }

    public function test_summarise_response_unit() {
        $num = test_question_maker::make_question('numerical', 'unit');
        $this->assertEqual('3.1', $num->summarise_response(array('answer' => '3.1')));
        $this->assertEqual('3.1m', $num->summarise_response(array('answer' => '3.1m')));
        $this->assertEqual('3.1 cm', $num->summarise_response(array('answer' => '3.1 cm')));
    }

    public function test_summarise_response_currency() {
        $num = test_question_maker::make_question('numerical', 'currency');
        $this->assertEqual('100', $num->summarise_response(array('answer' => '100')));
        $this->assertEqual('$100', $num->summarise_response(array('answer' => '$100')));
        $this->assertEqual('$ 100', $num->summarise_response(array('answer' => '$ 100')));
        $this->assertEqual('100 frogs', $num->summarise_response(array('answer' => '100 frogs')));
    }

    public function test_classify_response() {
        $num = test_question_maker::make_question('numerical');
        $num->start_attempt(new question_attempt_step(), 1);

        $this->assertEqual(array(
                new question_classified_response(15, '3.1', 0.0)),
                $num->classify_response(array('answer' => '3.1')));
        $this->assertEqual(array(
                new question_classified_response(17, '42', 0.0)),
                $num->classify_response(array('answer' => '42')));
        $this->assertEqual(array(
                new question_classified_response(13, '3.14', 1.0)),
                $num->classify_response(array('answer' => '3.14')));
        $this->assertEqual(array(
                question_classified_response::no_response()),
                $num->classify_response(array('answer' => '')));
    }

    public function test_classify_response_unit() {
        $num = test_question_maker::make_question('numerical', 'unit');
        $num->start_attempt(new question_attempt_step(), 1);

        $this->assertEqual(array(
                new question_classified_response(13, '1.25', 0.5)),
                $num->classify_response(array('answer' => '1.25', 'unit' => '')));
        $this->assertEqual(array(
                new question_classified_response(13, '1.25 m', 1.0)),
                $num->classify_response(array('answer' => '1.25', 'unit' => 'm')));
        $this->assertEqual(array(
                new question_classified_response(13, '125 cm', 1.0)),
                $num->classify_response(array('answer' => '125', 'unit' => 'cm')));
        $this->assertEqual(array(
                new question_classified_response(14, '123 cm', 0.5)),
                $num->classify_response(array('answer' => '123', 'unit' => 'cm')));
        $this->assertEqual(array(
                new question_classified_response(14, '1.27 m', 0.5)),
                $num->classify_response(array('answer' => '1.27', 'unit' => 'm')));
        $this->assertEqual(array(
                new question_classified_response(17, '3.0 m', 0)),
                $num->classify_response(array('answer' => '3.0', 'unit' => 'm')));
        $this->assertEqual(array(
                question_classified_response::no_response()),
                $num->classify_response(array('answer' => '')));
    }

    public function test_classify_response_currency() {
        $num = test_question_maker::make_question('numerical', 'currency');
        $num->start_attempt(new question_attempt_step(), 1);

        $this->assertEqual(array(
                new question_classified_response(14, '$100', 0)),
                $num->classify_response(array('answer' => '$100')));
        $this->assertEqual(array(
                new question_classified_response(13, '1 332', 0.8)),
                $num->classify_response(array('answer' => '1 332')));
    }
}