<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2011 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage programmanager
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */


/**
 * NOTE: This file only tests all ELIS custom context calls made by ELIS reports. It should not be used to verify
 * other report operations or data accuracy.
 */


require_once(dirname(__FILE__) . '/../../../elis/core/test_config.php');
global $CFG;
require_once($CFG->dirroot . '/elis/program/lib/setup.php');
require_once(elis::lib('testlib.php'));
require_once(elispm::lib('data/user.class.php'));
require_once(elispm::lib('data/userset.class.php'));
require_once(elis::lib('data/customfield.class.php'));
require_once('PHPUnit/Extensions/Database/DataSet/CsvDataSet.php');
ini_set('error_reporting',1);
ini_set('display_errors',1);

class reportsTest extends elis_database_test {
    protected $backupGlobalsBlacklist = array('DB');

	protected static function get_overlay_tables() {
		return array(
            user::TABLE => 'elis_program',
            'context'      => 'moodle',
            'course'       => 'moodle',
            userset::TABLE => 'elis_program',
            field_category::TABLE => 'elis_core',
            field_category_contextlevel::TABLE => 'elis_core',
            field::TABLE => 'elis_core',
            field_contextlevel::TABLE => 'elis_core',
            field_data_char::TABLE => 'elis_core',
            field_data_int::TABLE => 'elis_core',
        );
	}

    protected function load_userset_csv_data() {
        $dataset = new PHPUnit_Extensions_Database_DataSet_CsvDataSet();
        $dataset->addTable(userset::TABLE, elis::component_file('program', 'phpunit/userset.csv'));
        $dataset = new PHPUnit_Extensions_Database_DataSet_ReplacementDataSet($dataset);
        $dataset->addSubStrReplacement('\n', "\n");
        load_phpunit_data_set($dataset, true, self::$overlaydb);
    }

    protected function load_orig_site_course_data() {
        $site = self::$origdb->get_record('course', array('id' => SITEID));
        self::$overlaydb->import_record('course', $site);
    }

    public function testCourseUsageSummary() {
        global $CFG;
        require_once(dirname(__FILE__).'/../instances/course_usage_summary/course_usage_summary_report.class.php');
        $report = new course_usage_summary_report('test_course_summary');
        $filters = $report->get_filters();
        if (!empty($report->checkboxes_filter->options['choices'])) {
            foreach ($report->checkboxes_filter->options['choices'] as $choice => $desc) {
                $result = $report->get_average_test_score($choice);
                $resultIsNumeric = is_numeric(substr($result,0,-1));
                $this->assertTrue($resultIsNumeric);
            }
        }
    }

    public function testCourseCompletionByCluster() {
        global $CFG;
        $this->load_userset_csv_data();
        require_once(dirname(__FILE__).'/../instances/course_completion_by_cluster/course_completion_by_cluster_report.class.php');
        $report = new course_completion_by_cluster_report('test_course_completion_by_cluster');

        //test context in get_report_sql
        $columns = $report->get_select_columns();
        $sql = $report->get_report_sql($columns);
        $this->assertArrayHasKey(0,$sql);
        $this->assertNotEmpty($sql[0]);
        $this->assertArrayHasKey(1,$sql);
        $this->assertNotEmpty($sql[1]);

        //test context in transform_grouping_header_label
        $grouping = new stdClass;
        $grouping->field = 'cluster.id';
        $grouping->label = 'Test';
        $grouping_current['cluster.id'] = 1;
        $datum = new stdClass;
        $datum->cluster = 1;
        $result = $report->transform_grouping_header_label($grouping_current, $grouping, $datum, php_report::$EXPORT_FORMAT_LABEL);
        $this->assertNotEmpty($result);
    }

    public function testCourseProgressSummary() {
        global $CFG;
        require_once(dirname(__FILE__).'/../instances/course_progress_summary/course_progress_summary_report.class.php');
        $report = new course_progress_summary_report('test_course_progress_summary');

        //test context in get_columns
        $columns = $report->get_columns();
        $this->assertNotEmpty($columns);
    }

    public function testIndividualCourseProgress() {
        global $CFG;
        $this->load_orig_site_course_data();
        require_once(dirname(__FILE__).'/../instances/individual_course_progress/individual_course_progress_report.class.php');
        $report = new individual_course_progress_report('test_individual_course_progress');

        //test context in get_columns
        $columns = $report->get_columns();
        $this->assertNotEmpty($columns);

        //test context in get_max_test_score_sql
        $fields = array('_elis_course_pretest', '_elis_course_posttest');
        foreach ($fields as $field) {
            $report->get_max_test_score_sql($field);
        }
    }
}