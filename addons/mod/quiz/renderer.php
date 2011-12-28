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
 * Defines the renderer for the quiz module.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The renderer for the quiz module.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_renderer extends plugin_renderer_base {
    /**
     * Builds the review page
     *
     * @param quiz_attempt $attemptobj an instance of quiz_attempt.
     * @param array $slots an array of intgers relating to questions.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_page(quiz_attempt $attemptobj, $slots, $page, $showall,
                                $lastpage, mod_quiz_display_options $displayoptions,
                                $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
                $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
                $attemptobj);

        $output .= $this->review_next_navigation($attemptobj, $page, $lastpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param quiz_attempt $attemptobj an instance of quiz_attempt.
     * @param int $slot which question to display.
     * @param int $seq which step of the question attempt to show. null = latest.
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_question_page(quiz_attempt $attemptobj, $slot, $seq,
            mod_quiz_display_options $displayoptions, $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, 0);

        if (!is_null($seq)) {
            $output .= $attemptobj->render_question_at_step($slot, $seq, true);
        } else {
            $output .= $attemptobj->render_question($slot, true);
        }

        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param string $message Why the review is not allowed.
     * @return string html to output.
     */
    public function review_question_not_allowed($message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->notification($message);
        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Filters the summarydata array.
     *
     * @param array $summarydata contains row data for table
     * @param int $page the current page number
     * @return $summarydata containing filtered row data
     */
    protected function filter_summary_table($summarydata, $page) {
        if ($page == 0) {
            return $summarydata;
        }

        // Only show some of summary table on subsequent pages.
        foreach ($summarydata as $key => $rowdata) {
            if (!in_array($key, array('user', 'attemptlist'))) {
                unset($summarydata[$key]);
            }
        }

        return $summarydata;
    }

    /**
     * Outputs the table containing data from summary data array
     *
     * @param array $summarydata contains row data for table
     * @param int $page contains the current page number
     */
    public function review_summary_table($summarydata, $page) {
        $summarydata = $this->filter_summary_table($summarydata, $page);
        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', array(
                'class' => 'generaltable generalbox quizreviewsummary'));
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }

            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }

            $output .= html_writer::tag('tr',
                html_writer::tag('th', $title, array('class' => 'cell', 'scope' => 'row')) .
                        html_writer::tag('td', $content, array('class' => 'cell'))
            );
        }

        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Renders each question
     *
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param bool $reviewing
     * @param array $slots array of intgers relating to questions
     * @param int $page current page number
     * @param bool $showall if true shows attempt on single page
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options
     */
    public function questions(quiz_attempt $attemptobj, $reviewing, $slots, $page, $showall,
                              mod_quiz_display_options $displayoptions) {
        $output = '';
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, $reviewing,
                    $attemptobj->review_url($slot, $page, $showall));
        }
        return $output;
    }

    /**
     * Renders the main bit of the review page.
     *
     * @param array $summarydata contain row data for table
     * @param int $page current page number
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options
     * @param $content contains each question
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param bool $showall if true display attempt on one page
     */
    public function review_form($page, $showall, $displayoptions, $content, $attemptobj) {
        if ($displayoptions->flags != question_display_options::EDITABLE) {
            return $content;
        }

        $this->page->requires->js_init_call('M.mod_quiz.init_review_form', null, false,
                quiz_get_js_module());

        $output = '';
        $output .= html_writer::start_tag('form', array('action' => $attemptobj->review_url(null,
                $page, $showall), 'method' => 'post', 'class' => 'questionflagsaveform'));
        $output .= html_writer::start_tag('div');
        $output .= $content;
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                'class' => 'questionflagsavebutton', 'name' => 'savingflags',
                'value' => get_string('saveflags', 'question')));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Returns either a liink or button
     *
     * @param $url contains a url for the review link
     */
    public function finish_review_link($url) {
        if ($this->page->pagelayout == 'popup') {
            // In a 'secure' popup window.
            $this->page->requires->js_init_call('M.mod_quiz.secure_window.init_close_button',
                    array($url), quiz_get_js_module());
            return html_writer::empty_tag('input', array('type' => 'button',
                    'value' => get_string('finishreview', 'quiz'),
                    'id' => 'secureclosebutton'));
        } else {
            return html_writer::link($url, get_string('finishreview', 'quiz'));
        }
    }

    /**
     * Creates a next page arrow or the finishing link
     *
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param int $page the current page
     * @param bool $lastpage if true current page is the last page
     */
    public function review_next_navigation(quiz_attempt $attemptobj, $page, $lastpage) {
        if ($lastpage) {
            $nav = $this->finish_review_link($attemptobj->view_url());
        } else {
            $nav = link_arrow_right(get_string('next'), $attemptobj->review_url(null, $page + 1));
        }
        return html_writer::tag('div', $nav, array('class' => 'submitbtns'));
    }

    /**
     * Return the HTML of the quiz timer.
     * @return string HTML content.
     */
    public function countdown_timer() {
        return html_writer::tag('div', get_string('timeleft', 'quiz') . ' ' .
                html_writer::tag('span', '', array('id' => 'quiz-time-left')),
                array('id' => 'quiz-timer'));
    }

    /**
     * Create a preview link
     *
     * @param $url contains a url to the given page
     */
    public function restart_preview_button($url) {
        return $this->single_button($url, get_string('startnewpreview', 'quiz'));
    }

    /**
     * Outputs the navigation block panel
     *
     * @param quiz_nav_panel_base $panel instance of quiz_nav_panel_base
     */
    public function navigation_panel(quiz_nav_panel_base $panel) {

        $output = '';
        $userpicture = $panel->user_picture();
        if ($userpicture) {
            $output .= html_writer::tag('div', $this->render($userpicture),
                    array('id' => 'user-picture', 'class' => 'clearfix'));
        }
        $output .= $panel->render_before_button_bits($this);

        $output .= html_writer::start_tag('div', array('class' => 'qn_buttons'));
        foreach ($panel->get_question_buttons() as $button) {
            $output .= $this->render($button);
        }
        $output .= html_writer::end_tag('div');

        $output .= html_writer::tag('div', $panel->render_end_bits($this),
                array('class' => 'othernav'));

        $this->page->requires->js_init_call('M.mod_quiz.nav.init', null, false,
                quiz_get_js_module());

        return $output;
    }

    /**
     * Returns the quizzes navigation button
     *
     * @param quiz_nav_question_button $button
     */
    protected function render_quiz_nav_question_button(quiz_nav_question_button $button) {
        $classes = array('qnbutton', $button->stateclass);
        $attributes = array();

        if ($button->currentpage) {
            $classes[] = 'thispage';
            $attributes[] = get_string('onthispage', 'quiz');
        }

        // Flagged?
        if ($button->flagged) {
            $classes[] = 'flagged';
            $flaglabel = get_string('flagged', 'question');
        } else {
            $flaglabel = '';
        }
        $attributes[] = html_writer::tag('span', $flaglabel, array('class' => 'flagstate'));

        if (is_numeric($button->number)) {
            $qnostring = 'questionnonav';
        } else {
            $qnostring = 'questionnonavinfo';
        }

        $a = new stdClass();
        $a->number = $button->number;
        $a->attributes = implode(' ', $attributes);

        return html_writer::link($button->url,
                html_writer::tag('span', '', array('class' => 'thispageholder')) .
                html_writer::tag('span', '', array('class' => 'trafficlight')) .
                get_string($qnostring, 'quiz', $a),
                array('class' => implode(' ', $classes), 'id' => $button->id,
                        'title' => $button->statestring));
    }

    /**
     * outputs the link the other attempts.
     *
     * @param mod_quiz_links_to_other_attempts $links
     */
    protected function render_mod_quiz_links_to_other_attempts(
            mod_quiz_links_to_other_attempts $links) {
        $attemptlinks = array();
        foreach ($links->links as $attempt => $url) {
            if ($url) {
                $attemptlinks[] = html_writer::link($url, $attempt);
            } else {
                $attemptlinks[] = html_writer::tag('strong', $attempt);
            }
        }
        return implode(', ', $attemptlinks);
    }

    /**
     * Attempt Page
     *
     * @param quiz_attempt $attemptobj Instance of quiz_attempt
     * @param int $page Current page number
     * @param quiz_access_manager $accessmanager Instance of quiz_access_manager
     * @param array $messages An array of messages
     * @param array $slots Contains an array of integers that relate to questions
     * @param int $id The ID of an attempt
     * @param int $nextpage The number of the next page
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
            $nextpage) {
        $output = '';
        $output .= $this->header();
        $output .= $this->quiz_notices($messages);
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Returns any notices.
     *
     * @param array $messages
     */
    public function quiz_notices($messages) {
        if (!$messages) {
            return '';
        }
        return $this->box($this->heading(get_string('accessnoticesheader', 'quiz'), 3) .
                $this->access_messages($messages), 'quizaccessnotices');
    }

    /**
     * Ouputs the form for making an attempt
     *
     * @param quiz_attempt $attemptobj
     * @param int $page Current page number
     * @param array $slots Array of integers relating to questions
     * @param int $id ID of the attempt
     * @param int $nextpage Next page number
     */
    public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
        $output = '';

        //Start Form
        $output .= html_writer::start_tag('form',
                array('action' => $attemptobj->processattempt_url(), 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'));
        $output .= html_writer::start_tag('div');

        // Print all the questions
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, false,
                    $attemptobj->attempt_url($slot, $page));
        }

        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'next',
                'value' => get_string('next')));
        $output .= html_writer::end_tag('div');

        // Some hidden fields to trach what is going on.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
                'value' => $attemptobj->get_attemptid()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'thispage',
                'value' => $page, 'id' => 'followingpage'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'nextpage',
                'value' => $nextpage));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
                'value' => '0', 'id' => 'timeup'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
                'value' => '', 'id' => 'scrollpos'));

        // Add a hidden field with questionids. Do this at the end of the form, so
        // if you navigate before the form has finished loading, it does not wipe all
        // the student's answers.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
                'value' => implode(',', $slots)));

        //Finish form
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @param bool $return if true, return a string, instead of outputting.
     *
     * @return mixed, if $return is true, return the string that would have been output, otherwise
     * return null.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message) . "\n";
        }
        return $output;
    }

    /*
     * Summary Page
     */
    /**
     * Create the summary page
     *
     * @param quiz_attempt $attemptobj
     * @param mod_quiz_display_options $displayoptions
     */
    public function summary_page($attemptobj, $displayoptions) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_quiz_name()));
        $output .= $this->heading(get_string('summaryofattempt', 'quiz'), 3);
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= $this->summary_page_controls($attemptobj);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param quiz_attempt $attemptobj
     * @param mod_quiz_display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizsummaryofattempt boxaligncenter';
        $table->head = array(get_string('question', 'quiz'), get_string('status', 'quiz'));
        $table->align = array('left', 'left');
        $table->size = array('', '');
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $table->data = array();

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }
            $flag = '';
            if ($attemptobj->is_question_flagged($slot)) {
                $flag = html_writer::empty_tag('img', array('src' => $this->pix_url('i/flagged'),
                        'alt' => get_string('flagged', 'question'), 'class' => 'questionflag'));
            }
            $row = array(html_writer::link($attemptobj->attempt_url($slot),
                    $attemptobj->get_question_number($slot) . $flag),
                    $attemptobj->get_question_status($slot, $displayoptions->correctness));
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = $attemptobj->get_question_state_class(
                    $slot, $displayoptions->correctness);
        }

        // Print the summary table.
        $output = html_writer::table($table);

        return $output;
    }

    /**
     * Creates any controls a the page should have.
     *
     * @param quiz_attempt $attemptobj
     */
    public function summary_page_controls($attemptobj) {
        $output = '';
        // countdown timer
        $output .= $this->countdown_timer();

        // Finish attempt button.
        $options = array(
            'attempt' => $attemptobj->get_attemptid(),
            'finishattempt' => 1,
            'timeup' => 0,
            'slots' => '',
            'sesskey' => sesskey(),
        );

        $button = new single_button(
                new moodle_url($attemptobj->processattempt_url(), $options),
                get_string('submitallandfinish', 'quiz'));
        $button->id = 'responseform';
        $button->add_action(new confirm_action(get_string('confirmclose', 'quiz'), null,
                get_string('submitallandfinish', 'quiz')));

        $output .= $this->container($this->container($this->render($button),
                'controls'), 'submitbtns mdl-align');

        return $output;
    }

    /*
     * View Page
     */
    /**
     * Generates the view page
     *
     * @param int $course The id of the course
     * @param array $quiz Array conting quiz data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     * @param array $infomessages information about this quiz
     * @param mod_quiz_view_object $viewobj
     * @param string $buttontext text for the start/continue attempt button, if
     *      it should be shown.
     * @param array $infomessages further information about why the student cannot
     *      attempt this quiz now, if appicable this quiz
     */
    public function view_page($course, $quiz, $cm, $context, $infomessages, $viewobj,
            $buttontext, $preventmessages) {
        $output = '';
        $output .= $this->view_information($course, $quiz, $cm, $context, $infomessages);
        $output .= $this->view_table($quiz, $context, $viewobj);
        $output .= $this->view_best_score($viewobj);
        $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
        $output .= $this->view_attempt_button($course, $quiz, $cm, $context, $viewobj,
                $buttontext, $preventmessages);
        return $output;
    }

    /**
     * Outputs an error message for any guests accessing the quiz
     *
     * @param int $course The course ID
     * @param array $quiz Array contingin quiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_guest($course, $quiz, $cm, $context, $messages) {
        $output = '';
        $output .= $this->view_information($course, $quiz, $cm, $context, $messages);
        $guestno = html_writer::tag('p', get_string('guestsno', 'quiz'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $output .= $this->confirm($guestno."\n\n".$liketologin."\n", get_login_url(),
                get_referer(false));
        return $output;
    }

    /**
     * Outputs and error message for anyone who is not enrolle don the course
     *
     * @param int $course The course ID
     * @param array $quiz Array contingin quiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_notenrolled($course, $quiz, $cm, $context, $messages) {
        global $CFG;
        $output = '';
        $output .= $this->view_information($course, $quiz, $cm, $context, $messages);
        $youneedtoenrol = html_writer::tag('p', get_string('youneedtoenrol', 'quiz'));
        $button = html_writer::tag('p',
                $this->continue_button($CFG->wwwroot . '/course/view.php?id=' . $course->id));
        $output .= $this->box($youneedtoenrol."\n\n".$button."\n", 'generalbox', 'notice');
        return $output;
    }

    /**
     * Output the page information
     *
     * @param int $course The course ID
     * @param array $quiz Array contingin quiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_information($course, $quiz, $cm, $context, $messages) {
        global $CFG;
        $output = '';
        // Print quiz name and description
        $output .= $this->heading(format_string($quiz->name));
        if (trim(strip_tags($quiz->intro))) {
            $output .= $this->box(format_module_intro('quiz', $quiz, $cm->id), 'generalbox',
                    'intro');
        }

        $output .= $this->box($this->access_messages($messages), 'quizinfo');

        // Show number of attempts summary to those who can view reports.
        if (has_capability('mod/quiz:viewreports', $context)) {
            if ($strattemptnum = $this->quiz_attempt_summary_link_to_reports($quiz, $cm,
                    $context)) {
                $output .= html_writer::tag('div', $strattemptnum,
                        array('class' => 'quizattemptcounts'));
            }
        }
        return $output;
    }

    /**
     * Generates the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'quiz'));
    }

    /**
     * Generates the table of data
     *
     * @param array $quiz Array contining quiz data
     * @param int $context The page context ID
     * @param mod_quiz_view_object $viewobj
     */
    public function view_table($quiz, $context, $viewobj) {
        $output = '';
        if (!$viewobj->attempts) {
            return $output;
        }
        $output .= $this->view_table_heading();

        // Prepare table header
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('timecompleted', 'quiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quiz') . ' / ' .
                    quiz_format_grade($quiz, $quiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('grade') . ' / ' .
                    quiz_format_grade($quiz, $quiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        if (isset($quiz->showtimetaken)) {
            $table->head[] = get_string('timetaken', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt
        foreach ($viewobj->attempts as $attempt) {
            $attemptoptions = quiz_get_review_options($quiz, $attempt, $context);
            $row = array();

            // Add the attempt number, making it a link, if appropriate.
            if ($viewobj->attemptcolumn) {
                if ($attempt->preview) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attempt->attempt;
                }
            }

            // prepare strings for time taken and date completed
            $timetaken = '';
            $datecompleted = '';
            if ($attempt->timefinish > 0) {
                // attempt has finished
                $timetaken = format_time($attempt->timefinish - $attempt->timestart);
                $datecompleted = userdate($attempt->timefinish);
            } else if (!$quiz->timeclose || $viewobj->timenow < $quiz->timeclose) {
                // The attempt is still in progress.
                $timetaken = format_time($viewobj->timenow - $attempt->timestart);
                $datecompleted = get_string('inprogress', 'quiz');
            } else {
                $timetaken = format_time($quiz->timeclose - $attempt->timestart);
                $datecompleted = userdate($quiz->timeclose);
            }
            $row[] = $datecompleted;

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attempt->timefinish > 0) {
                    $row[] = quiz_format_grade($quiz, $attempt->sumgrades);
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attempt->timefinish > 0) {
                    $formattedgrade = quiz_format_grade($quiz, $attemptgrade);
                    // highlight the highest grade if appropriate
                    if ($viewobj->overallstats && !$attempt->preview
                            && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                            && $attemptgrade == $viewobj->mygrade
                            && $quiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attempt->attempt] = 'bestrow';
                    }

                    $row[] = $formattedgrade;
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attempt,
                        $viewobj->canpreview, $attemptoptions);
            }

            if ($viewobj->feedbackcolumn && $attempt->timefinish > 0) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if (isset($quiz->showtimetaken)) {
                $row[] = $timetaken;
            }

            if ($attempt->preview) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attempt->attempt] = $row;
            }
        } // End of loop over attempts.
        $output .= html_writer::table($table);

        return $output;
    }

    /**
     * Prints the students best score
     *
     * @param mod_quiz_view_object $viewobj
     */
    public function view_best_score($viewobj) {
        $output = '';
        // Print information about the student's best score for this quiz if possible.
        if (!$viewobj->moreattempts) {
            $output .= $this->heading(get_string('nomoreattempts', 'quiz'));
        }
        return $output;
    }

    /**
     * Generates data pertaining to quiz results
     *
     * @param array $quiz Array containing quiz data
     * @param int $context The page context ID
     * @param int $cm The Course Module Id
     * @param mod_quiz_view_object $viewobj
     */
    public function view_result_info($quiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = quiz_get_grading_option_name($quiz->grademethod);
                $a->mygrade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->quizgrade = quiz_format_grade($quiz, $quiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'quiz', $a), 2, 'main');
            } else {
                $a = new stdClass();
                $a->grade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                $a = get_string('outofshort', 'quiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'quiz', $a), 2,
                        'main');
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'),
                    array('class' => 'overriddennotice'))."\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'quiz'), 3, 'main');
            $resultinfo .= '<p class="quizteacherfeedback">'.$viewobj->gradebookfeedback.
                    "</p>\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'quiz'), 3, 'main');
            $resultinfo .= html_writer::tag('p',
                    quiz_feedback_for_grade($viewobj->mygrade, $quiz, $context),
                    array('class' => 'quizgradefeedback'))."\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }

    /**
     * Generates the view attempt button
     *
     * @param int $course The course ID
     * @param array $quiz Array containging quiz date
     * @param int $cm The Course Module ID
     * @param int $context The page Context ID
     * @param mod_quiz_view_object $viewobj
     * @param string $buttontext
     */
    public function view_attempt_button($course, $quiz, $cm, $context, $viewobj,
            $buttontext, $preventmessages) {
        $output = '';
        // Determine if we should be showing a start/continue attempt button,
        // or a button to go back to the course page.
        $output .= $this->box_start('quizattempt');

        // Now actually print the appropriate button.
        if (!quiz_clean_layout($quiz->questions, true)) {
            $output .= quiz_no_questions_message($quiz, $cm, $context);
        }

        if ($preventmessages) {
            $output .= $this->access_messages($preventmessages);
        }

        if ($buttontext) {
            $output .= $viewobj->accessmanager->print_start_attempt_button($viewobj->canpreview,
                    $buttontext, $viewobj->unfinished);
        } else if ($buttontext === '') {
            $output .= $this->single_button(new moodle_url('/course/view.php',
                    array('id' => $course->id)), get_string('backtocourse', 'quiz'), 'get',
                    array('class' => 'continuebutton'));
        }
        $output .= $this->box_end();

        return $output;
    }

    /**
     * Returns the same as {@link quiz_num_attempt_summary()} but wrapped in a link
     * to the quiz reports.
     *
     * @param object $quiz the quiz object. Only $quiz->id is used at the moment.
     * @param object $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid
     * fields are used at the moment.
     * @param object $context the quiz context.
     * @param bool $returnzero if false (default), when no attempts have been made '' is returned
     * instead of 'Attempts: 0'.
     * @param int $currentgroup if there is a concept of current group where this method is being
     * called
     *         (e.g. a report) pass it in here. Default 0 which means no current group.
     * @return string HTML fragment for the link.
     */
    public function quiz_attempt_summary_link_to_reports($quiz, $cm, $context,
                                                          $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = quiz_num_attempt_summary($quiz, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
        $url = new moodle_url('/mod/quiz/report.php', array(
                'id' => $cm->id, 'mode' => quiz_report_default_report($context)));
        return html_writer::link($url, $summary);
    }
}

class mod_quiz_links_to_other_attempts implements renderable {
    /**
     * @var array string attempt number => url, or null for the current attempt.
     */
    public $links = array();
}

class mod_quiz_view_object {
    /**
     * @var array $attempt contains all the user's attempts at this quiz.
     */
    public $attempts;
    /**
     * @var object $accessmanager contains various access rules.
     */
    public $accessmanager;
    /**
     * @var int $canattempt determins capability for attempting a quiz.
     */
    public $canattempt;
    /**
     * @var int $canpreview determins capability for previewing a quiz.
     */
    public $canpreview;
    /**
     * @var int $canreviewmine determins capability for reviwing own quiz.
     */
    public $canreviewmine;
    /**
     * @var int $attemptcolumn contains the number of attempts done.
     */
    public $attemptcolumn;
    /**
     * @var int $gradecolumn contains the grades of any attempts.
     */
    public $gradecolumn;
    /**
     * @var int $markcolumn contains the marks of any attempt.
     */
    public $markcolumn;
    /**
     * @var int $overallstats contains all marks for any attempt.
     */
    public $overallstats;
    /**
     * @var string $feedbackcolumn contains any feedback for and attempt.
     */
    public $feedbackcolumn;
    /**
     * @var string $timenow contains a timestamp in string format.
     */
    public $timenow;
    /**
     * @var int $numattempts contains the total number of attempts.
     */
    public $numattempts;
    /**
     * @var int $mygrade contains the current users final grade for a quiz.
     */
    public $mygrade;
    /**
     * @var int $moreattempts total attempts left.
     */
    public $moreattempts;
    /**
     * @var int $mygradeoverridden contains an overriden grade.
     */
    public $mygradeoverridden;
    /**
     * @var string $gradebookfeedback contains any feedback for a gradebook.
     */
    public $gradebookfeedback;
    /**
     * @var int $unfinished contains 1 if an attempt is unfinished.
     */
    public $unfinished;
    /**
     * @var int $lastfinishedattempt contains a pointer to the last attempt in the attempts array.
     */
    public $lastfinishedattempt;
}
