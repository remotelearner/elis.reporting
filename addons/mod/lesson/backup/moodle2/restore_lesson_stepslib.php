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
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_lesson_activity_task
 */

/**
 * Structure step to restore one lesson activity
 */
class restore_lesson_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('lesson', '/activity/lesson');
        $paths[] = new restore_path_element('lesson_page', '/activity/lesson/pages/page');
        $paths[] = new restore_path_element('lesson_answer', '/activity/lesson/pages/page/answers/answer');
        if ($userinfo) {
            $paths[] = new restore_path_element('lesson_attempt', '/activity/lesson/pages/page/answers/answer/attempts/attempt');
            $paths[] = new restore_path_element('lesson_grade', '/activity/lesson/grades/grade');
            $paths[] = new restore_path_element('lesson_branch', '/activity/lesson/pages/page/branches/branch');
            $paths[] = new restore_path_element('lesson_highscore', '/activity/lesson/highscores/highscore');
            $paths[] = new restore_path_element('lesson_timer', '/activity/lesson/timers/timer');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_lesson($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->available = $this->apply_date_offset($data->available);
        $data->deadline = $this->apply_date_offset($data->deadline);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // lesson->highscores can come both in data->highscores and
        // data->showhighscores, handle both. MDL-26229
        if (isset($data->showhighscores)) {
            $data->highscores = $data->showhighscores;
            unset($data->showhighscores);
        }

        // insert the lesson record
        $newitemid = $DB->insert_record('lesson', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_lesson_page($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');

        // We'll remap all the prevpageid and nextpageid at the end, once all pages have been created
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('lesson_pages', $data);
        $this->set_mapping('lesson_page', $oldid, $newitemid, true); // Has related fileareas
    }

    protected function process_lesson_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->pageid = $this->get_new_parentid('lesson_page');
        $data->answer = $data->answer_text;
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('lesson_answers', $data);
        $this->set_mapping('lesson_answer', $oldid, $newitemid);
    }

    protected function process_lesson_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->pageid = $this->get_new_parentid('lesson_page');
        $data->answerid = $this->get_new_parentid('lesson_answer');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('lesson_attempts', $data);
    }

    protected function process_lesson_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->completed = $this->apply_date_offset($data->completed);

        $newitemid = $DB->insert_record('lesson_grades', $data);
        $this->set_mapping('lesson_grade', $oldid, $newitemid);
    }

    protected function process_lesson_branch($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->pageid = $this->get_new_parentid('lesson_page');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timeseen = $this->apply_date_offset($data->timeseen);

        $newitemid = $DB->insert_record('lesson_branch', $data);
    }

    protected function process_lesson_highscore($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->gradeid = $this->get_mappingid('lesson_grade', $data->gradeid);

        $newitemid = $DB->insert_record('lesson_high_scores', $data);
    }

    protected function process_lesson_timer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('lesson');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->starttime = $this->apply_date_offset($data->starttime);
        $data->lessontime = $this->apply_date_offset($data->lessontime);

        $newitemid = $DB->insert_record('lesson_timer', $data);
    }

    protected function after_execute() {
        global $DB;

        // Add lesson mediafile, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_lesson', 'mediafile', null);
        // Add lesson page files, by lesson_page itemname
        $this->add_related_files('mod_lesson', 'page_contents', 'lesson_page');

        // Remap all the restored prevpageid and nextpageid now that we have all the pages and their mappings
        $rs = $DB->get_recordset('lesson_pages', array('lessonid' => $this->task->get_activityid()),
                                 '', 'id, prevpageid, nextpageid');
        foreach ($rs as $page) {
            $page->prevpageid = (empty($page->prevpageid)) ? 0 : $this->get_mappingid('lesson_page', $page->prevpageid);
            $page->nextpageid = (empty($page->nextpageid)) ? 0 : $this->get_mappingid('lesson_page', $page->nextpageid);
            $DB->update_record('lesson_pages', $page);
        }
        $rs->close();

        // Remap all the restored 'jumpto' fields now that we have all the pages and their mappings
        $rs = $DB->get_recordset('lesson_answers', array('lessonid' => $this->task->get_activityid()),
                                 '', 'id, jumpto');
        foreach ($rs as $answer) {
            if ($answer->jumpto > 0) {
                $answer->jumpto = $this->get_mappingid('lesson_page', $answer->jumpto);
                $DB->update_record('lesson_answers', $answer);
            }
        }
        $rs->close();

        // TODO: somewhere at the end of the restore... when all the activities have been restored
        // TODO: we need to decode the lesson->activitylink that points to another activity in the course
        // TODO: great functionality that breaks self-contained principles, grrr
    }
}
