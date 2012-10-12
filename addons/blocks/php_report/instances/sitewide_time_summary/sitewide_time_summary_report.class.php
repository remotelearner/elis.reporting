<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
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
 * @subpackage pm-blocks-phpreport-sitewide_time_summary
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */
require_once($CFG->dirroot .'/blocks/php_report/type/table_report.class.php');

define('STSR_DATE_FORMAT', get_string('date_format', 'rlreport_sitewide_time_summary'));
define('SECS_PER_DAY', 60 * 60 * 24);

class sitewide_time_summary_report extends table_report {

    /**
     * Constants for unique filter ids
     */
    const clusterfilterid = 'clustf';
    const datefilterid = 'datef';
    const segfilterid = 'segf';
    const upfilterid = 'upm';
    const defaultseg = 'noseg';

    /**
     * Language file
     */
    var $langfile = 'rlreport_sitewide_time_summary';

    /**
     * Date filter start and end dates
     * populated using: get_filter_values()
     */
    var $startdate = 0;
    var $enddate = 0;

    /**
     * Segment Report filter setting
     * populated using: get_filter_values()
     */
    var $segment = sitewide_time_summary_report::defaultseg;

    /**
     * Array of fieldnames and aliases of report columns
     */
    var $columnfields;

    /**
     * Summary field
     */
    var $summaryfield;

    /**
     * Require GROUP BY for time fcns YEAR, MONTH, WEEK
     * set in get_grouping_fields(); and add_groupby_clause();
     * used/returned in get_report_sql_groups()
     */
    var $groupby = '';

    /**
     * Used to flag when grouptotal changes and requires reset (TBD)
     * OR modify table_report to somehow clear grouptotal after displayed?
     */
    var $groupflag = null;

    /**
     * Running total for report grouping
     */
    var $grouptotal = 0;

    /**
     * Array of most frequent group depending on report segment setting
     */
    var $lastgrp = array('noseg'  => 'user_id',
                         'years'  => 'year',
                         'months' => 'month',
                         'weeks'  => 'week' );

    // Saved transform record to compare
    var $xformrec = null;

    /**
     * Required user profile fields (keys)
     * Note: can override default labels with values (leave empty for default)
     * Eg. 'lastname' =>  'Surname', ...
     */
    var $_fields = array(
        'up' => array(
            'fullname',
            'lastname',
            'firstname',
            'idnumber',
            'email',
            'city',
            'country',
            'username',
            'lang',
            'confirmed',
            //'crsrole',
            //'crscat',
            //'sysrole',
            'firstaccess',
            'lastaccess',
            'lastlogin',
            'timemodified',
            'auth'
        )
    );

    /**
     * Segmentation column options
     */
    var $wkdays = array('sun', 'mon', 'tues', 'wed', 'thurs', 'fri', 'sat');
    var $weeks = array('week1', 'week2', 'week3', 'week4');
    var $months = array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul',
                        'aug', 'sep', 'oct', 'nov', 'dece');
                  // Note: dec is SQL reserved!
    /**
     * Gets the report category.
     *
     * @return string The report's category (should be one of the CATEGORY_*
     * constants defined above).
     */
    function get_category() {
        return self::CATEGORY_ADMIN;
    }

    /**
     * Specifies whether the current report is available
     *
     * @uses $CFG
     * @uses $DB
     * @param none
     * @return  boolean  true if the report is available, otherwise false
     */
    function is_available() {
        global $CFG, $DB;

        // TBD: we need the /elis/program/ directories
        if (!file_exists($CFG->dirroot .'/elis/program/lib/setup.php')) {
            return false;
        }

        //we also need the curr_admin block
        if (!$DB->record_exists('block', array('name' => 'curr_admin'))) {
            return false;
        }

        //everything needed is present
        return true;
    }

    /**
     * Require any code that this report needs
     *
     * @uses $CFG
     * @param none
     * @return none
     */
    function require_dependencies() {
        global $CFG;

        require_once($CFG->dirroot .'/elis/program/lib/setup.php');

        //needed for options filters
        require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/date.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/radiobuttons.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/simpleselect.php');
        require_once($CFG->dirroot .'/elis/core/lib/filtering/userprofilematch.php');
        require_once($CFG->dirroot .'/elis/program/lib/filtering/clusterselect.php');

        require_once($CFG->dirroot .'/elis/program/lib/data/student.class.php');
        require_once($CFG->dirroot .'/elis/program/lib/data/user.class.php');
    }

    /**
     * Display name of report
     *
     * @uses none
     * @param none
     * @return string - the display name of the report
     */
    function get_display_name() {
        return get_string('displayname', $this->langfile);
    }

    /**
     * Specifies a header icon image
     *
     * @uses $CFG
     * @param none
     * @return  string - Full path to JPEG header logo
     */
    function get_preferred_header_icon() {
        global $CFG;
        return $CFG->wwwroot .'/blocks/php_reports/pix/sitewide_time_summary_report_logo.jpg';
    }

    /**
     * Specifies a field to sort by default
     *
     * @uses none
     * @param none
     * @return string - sort field
     */
    function get_default_sort_field() {
        return 'u.lastname AS lname';
    }

    /**
     * Specifies a default sort direction for the default sort field
     *
     * @uses none
     * @param none
     * @return string - sort order
     */
    function get_default_sort_order() {
        return 'ASC';
    }

    /**
     * Retrieves start and end settings from active filter (if exists)
     * and populates class properties: startdate and enddate
     *
     * @uses none
     * @param none
     * @return none
     */
    function get_filter_values() {

        $start_enabled =  php_report_filtering_get_active_filter_values(
                              $this->get_report_shortname(),
                              sitewide_time_summary_report::datefilterid . '_sck',
                              $this->filter);
        $start = 0;
        if (!empty($start_enabled) && is_array($start_enabled)
            && !empty($start_enabled[0]['value'])) {
            $start = php_report_filtering_get_active_filter_values(
                         $this->get_report_shortname(),
                         sitewide_time_summary_report::datefilterid . '_sdt',
                         $this->filter);
        }

        $end_enabled = php_report_filtering_get_active_filter_values(
                           $this->get_report_shortname(),
                           sitewide_time_summary_report::datefilterid . '_eck',
                           $this->filter);
        $end = 0;
        if (!empty($end_enabled) && is_array($end_enabled)
            && !empty($end_enabled[0]['value'])) {
            $end = php_report_filtering_get_active_filter_values(
                       $this->get_report_shortname(),
                       sitewide_time_summary_report::datefilterid . '_edt',
                       $this->filter);
        }

        $this->startdate = (!empty($start) && is_array($start))
                           ? $start[0]['value'] : 0;
        $this->enddate = (!empty($end) && is_array($end))
                         ? $end[0]['value'] : 0;

        //$this->err_dump($datefilter, 'get_filter_values(); $datefilter');
        //error_log("sitewide_time_summary::get_filter_values() ... startdate={$this->startdate} enddate={$this->enddate}");

        // Get segment filter settings
        $segfilter = php_report_filtering_get_active_filter_values(
                         $this->get_report_shortname(),
                         sitewide_time_summary_report::segfilterid,
                         $this->filter);
        //$this->err_dump($segfilter, '$segfilter');
        $this->segment = sitewide_time_summary_report::defaultseg;
        if (!empty($segfilter) && is_array($segfilter)) {
            $this->segment = $segfilter[0]['value'];
        }

    }

    /**
     * Specifies the report title
     *
     * @uses none
     * @param $export_format  The desired export format for the headers
     * @return array - header entires
     */
    function get_header_entries($export_format) {
        // Get date filter parameters req'd for header title
        $this->get_filter_values();
        $sdate = $this->userdate($this->startdate, STSR_DATE_FORMAT);
        $edate = !empty($this->enddate)
                 ? $this->userdate($this->enddate, STSR_DATE_FORMAT)
                 : get_string('present', $this->langfile);

        $header_obj = new stdClass;
        $header_obj->label = get_string('report_heading', $this->langfile);
        $header_obj->value = "{$sdate} - {$edate}";
        $header_obj->css_identifier = '';
        return array($header_obj);
    }

    /*
     * Add report title to report
     */
    function print_report_title() {
        /* Don't need a report title for this report */
    }

    /**
     * Specifies available report filters
     * (empty by default but can be implemented by child class)
     *
     * @param   boolean  $init_data  If true, signal the report to load the
     *                               actual content of the filter objects
     * @uses    $DB
     * @return  array                The list of available filters
     */
    function get_filters($init_data = true) {
        global $DB;
        // Create all requested User Profile field filters
        $upfilter =
            new generalized_filter_userprofilematch(
                sitewide_time_summary_report::upfilterid,
                get_string('filter_user_match', $this->langfile),
                array(
                    'choices'     => $this->_fields,
                    'notadvanced' => array('fullname'),
                    //'langfile'   => 'filters',
                    'extra'       => true // include all extra profile fields
                )
            );

        $filters = $upfilter->get_filters();

        //  Cluster filter options
        $clusters = $DB->get_records('crlm_cluster', null, 'name ASC', 'id,name');
        if (!empty($clusters)) {
            // Merge cluster filter IFF values exist
            $filters = array_merge($filters,
                 array(
                     new generalized_filter_entry(
                         sitewide_time_summary_report::clusterfilterid,
                         'crlmu', 'id',
                         get_string('filter_cluster', $this->langfile), false,
                         'clusterselect', array('default' => null)
                     )
                 )
             );
        }

        // Segment filter options
        $segchoices = array(
             'weeks' => get_string('seg_weeks', $this->langfile),
            'months' => get_string('seg_months', $this->langfile),
             'years' => get_string('seg_years', $this->langfile),
             'noseg' => get_string('seg_none', $this->langfile)
        );
        return array_merge($filters,
            array(
                // Start/End dates for report
                new generalized_filter_entry(
                    sitewide_time_summary_report::datefilterid, '', '',
                    get_string('filter_date_range', $this->langfile),
                    false, 'date'),
                // Segment report options - radio buttons
                new generalized_filter_entry(
                    sitewide_time_summary_report::segfilterid,
                    '', '', // table alias & DB field intentionally blank!
                    get_string('segmentreportby', $this->langfile),
                    false, 'radiobuttons',
                    array('choices' => $segchoices,
                          'checked' => sitewide_time_summary_report::defaultseg,
                          'default' => sitewide_time_summary_report::defaultseg,
                          // 'help' => array(),
                          'heading' => get_string('segmentreportby',
                                                  $this->langfile),
                          'footer'  => '<br/>' // TBD
                    )
                )
            )
        );
    }

    /**
     * Add a day to a UNIX time_t timestamp (seconds since Epoch: 1970-01-01)
     */
    function add_day($time) {
        return $time + SECS_PER_DAY;
    }

    /**
     * Add time to get to the NEXT day (00:00) of a UNIX time_t timestamp
     */
    function next_day($time) {
        $hr = date('G', $time); // 0 - 24
        $mn = date('i', $time); // 00 - 59
        $sec = date('s', $time); // 00 - 59
        return $this->add_day($time - ($hr * 60 * 60) - ($mn * 60) - $sec);
    }

    /**
     * Add a week to a UNIX time_t timestamp
     */
    function add_week($time) {
        return $time + (7 * SECS_PER_DAY);
    }

    /**
     * Add a month to a UNIX time_t timestamp
     */
    function add_month($time) {
        $year = date('Y', $time);
        $month = date('n', $time); // 1 to 12
        $month_days = days_in_month($month, $year);
        return $time + ($month_days * SECS_PER_DAY);
    }

    /**
     * Add time to get to the NEXT month (1st day) of a UNIX time_t timestamp
     */
    function next_month($time) {
        $day = date('j', $time) - 1; // 0 - 30
        $hr = date('G', $time); // 0 - 24
        $mn = date('i', $time); // 00 - 59
        $sec = date('s', $time); // 00 - 59
        return $this->add_month($time - ($day * SECS_PER_DAY) - ($hr * 60 * 60) - ($mn * 60) - $sec);
    }

    /**
     * Format time fields for display in report
     *
     * @param   string  $fmt   The format string
     * @param   int     $secs  The UNIX timestamp to be formatted
     * @return  string         The formatted time record
     */
    function format_time($fmt, $secs) {
        return sprintf($fmt, $secs/3600, ($secs % 3600)/60);
    }

    /**
     * Overload method to initialize report groupings
     * used here to also init report data!
     *
     * @return parent::initialize_groupings();
     */
    function initialize_groupings() {
        $this->get_filter_values(); // populate require class properties
        $this->grouptotal = 0;
        return parent::initialize_groupings();
    }

    /**
     * Takes a record and transform it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The current report record
     * @param   string    $export_format  The format being used to render the report
     * @uses    $DB
     * @return  stdClass                  The reformatted record
     */
    function transform_record($record, $export_format) {
        global $DB;
        //$this->err_dump($record, 'transform_record($record, '. $export_format .')');

        // Total time column from seconds to hours[:minutes]
        if ($this->segment == 'noseg') {
            $record->gcslabel = ($export_format == php_report::$EXPORT_FORMAT_PDF)
                                ? "\xc2\xa0\xc2\xa0\xc2\xa0\xc2\xa0\xc2\xa0\xc2\xa0\xc2\xa0" : '';
            $totaltime = 0;
            if (!empty($record->mcourse_id)) {
                $sql = "SELECT SUM(duration) as sum
                          FROM {etl_user_activity}
                         WHERE courseid = ?
                           AND userid = ?
                           AND hour >= ?";
                $params = array($record->mcourse_id,
                                $record->user_id,
                                $this->startdate);
                if (!empty($this->enddate)) {
                    $sql .= " AND hour <= ?";
                    $params[] = $this->enddate;
                }
                //print_object($sql);
                $trecs = $DB->get_records_sql($sql, $params);
                if (!empty($trecs)) {
                    //$this->err_dump($trecs, '$trecs');
                    foreach ($trecs as $rec) {
                        if (!empty($rec->sum)) {
                            $totaltime += $rec->sum;
                        }
                    }
                }
            }
        } else if ($this->segment == 'years') {
            $totaltime = 0;
            if (!empty($record->mcourse_id)) {
                $startdate = make_timestamp($record->year);
                foreach($this->months as $month) {
                    $enddate = $this->add_month($startdate); // TBD: $this->next_month($startdate);
                    $sql = "SELECT SUM(duration) as sum
                              FROM {etl_user_activity}
                             WHERE courseid = ? AND userid = ?
                               AND hour >= ? AND hour < ?";
                    //print_object($sql);
                    $trecs = $DB->get_records_sql($sql,
                                 array($record->mcourse_id,
                                       $record->user_id,
                                       $startdate,
                                       $enddate));
                    $curtime = 0;
                    if (!empty($trecs)) {
                        //$this->err_dump($trecs, '$trecs');
                        foreach ($trecs as $rec) {
                            if (!empty($rec->sum)) {
                                $curtime += $rec->sum;
                            }
                        }
                    }
                    $record->{$month} = $this->format_time(
                                            get_string('time_format',
                                                   $this->langfile), $curtime);
                    $totaltime += $curtime;
                    $startdate = $enddate;
                }
            }
        } else if ($this->segment == 'months') {
            $totaltime = 0;
            if (!empty($record->mcourse_id)) {
                $startdate = make_timestamp($record->year, $record->month);
                $nextmonth = $this->add_month($startdate);
                foreach($this->weeks as $wk) {
                    $enddate = ($wk == 'week4') ? $nextmonth // extra days in month
                                                : $this->add_week($startdate);
                    $sql = "SELECT SUM(duration) as sum
                              FROM {etl_user_activity}
                             WHERE courseid = ? AND userid = ?
                               AND hour >= ? AND hour < ?";
                    //print_object($sql);
                    $trecs = $DB->get_records_sql($sql,
                                  array($record->mcourse_id,
                                        $record->user_id,
                                        $startdate,
                                        $enddate));
                    $curtime = 0;
                    if (!empty($trecs)) {
                        //$this->err_dump($trecs, '$trecs');
                        foreach ($trecs as $rec) {
                            if (!empty($rec->sum)) {
                                $curtime += $rec->sum;
                            }
                        }
                    }
                    $totaltime += $curtime;
                    $record->{$wk} = $this->format_time(
                                     get_string('time_format', $this->langfile),
                                     $curtime);
                    $startdate = $enddate;
                }
                if (!empty($record->month)) {
                    $sparam = new stdClass;
                    $sparam->int_month = $record->month;
                    $sparam->str_month = get_string($this->months[$record->month - 1],
                                            $this->langfile);
                    $record->month = get_string('month_format', $this->langfile,
                                        $sparam);
                }
            }
        } else { // 'weeks'
            $totaltime = 0;
            if (!empty($record->mcourse_id)) {
                $startdate = make_timestamp($record->year);
                while (date('w', $startdate) != 0) { // off a day depending on TZ
                    // week must start on a sunday
                    $startdate = $this->add_day($startdate); // TBD: $this->next_day($startdate);
                }
                for ($i = 1; $i < $record->week; ++$i) {
                    $startdate = $this->add_week($startdate);
                }
                foreach($this->wkdays as $day) {
                    $enddate = $this->add_day($startdate); // TBD: $this->next_day($startdate);
                    $sql = "SELECT SUM(duration) as sum
                              FROM {etl_user_activity}
                             WHERE courseid = ? AND userid = ?
                               AND hour >= ? AND hour < ?";
                    //print_object($sql);
                    $trecs = $DB->get_records_sql($sql,
                                      array($record->mcourse_id,
                                            $record->user_id,
                                            $startdate,
                                            $enddate));
                    $curtime = 0;
                    if (!empty($trecs)) {
                        //$this->err_dump($trecs, '$trecs');
                        foreach ($trecs as $rec) {
                            if (!empty($rec->sum)) {
                                $curtime += $rec->sum;
                            }
                        }
                    }
                    $totaltime += $curtime;
                    $record->{$day} = $this->format_time(get_string('time_format',
                                                             $this->langfile),
                                                         $curtime);
                    $startdate = $enddate;
                }
            }
        }

        if (empty($record->year)) {
            $record->year = get_string('no_time_logged', $this->langfile,
                                strtolower(get_string('seg_years', $this->langfile)));
        }
        if (empty($record->year) || empty($record->month)) {
            $record->month = get_string('no_time_logged', $this->langfile,
                                 strtolower(get_string('seg_months', $this->langfile)));
        }
        if (empty($record->year) || empty($record->week)) {
            $record->week = get_string('no_time_logged', $this->langfile,
                                strtolower(get_string('seg_weeks', $this->langfile)));
        }

        if ($this->xformrec != $record) {
            // required since table_report::get_data()
            // calls transform_record() twice for 1st record!
            $this->grouptotal += $totaltime;
            $this->xformrec = clone($record);
        }

        $totaltime_fmt = ($export_format == php_report::$EXPORT_FORMAT_CSV)
                         ? 'time_format' : 'totaltime_format';
        $record->totaltime = $this->format_time(get_string($totaltime_fmt,
                                                           $this->langfile),
                                                $totaltime);

        // Below must reassign AFTER time since above requires u.id (user_id)
        $record->user_id = fullname($record);

        return $record;
    }

    /**
     *  Override parent method to indicate this report has group column summary
     */
    function requires_group_column_summary() {
        return true;
    }

    /**
     * Takes a summary row record and transoforms it into an appropriate format
     * This method is set up as a hook to be implented by actual report class
     *
     * @param   stdClass  $record         The last report record
     * @param   stdClass  $nextrecord     The next report record
     * @param   string    $export_format  The format being used to render the report
     * @return stdClass  The reformatted record
     */
    function transform_group_column_summary($record, $nextrecord, $export_format) {
        //$this->err_dump($record, 'transform_group_column_summary($record, $nextrecord, '. $export_format .')');

        $record->coursename = '';
        $record->classid = '';
        $prevfield = '';
        // clear all not required columns - saving 2nd last for label
        switch($this->segment) {
            case 'noseg':
                $prevfield = 'gcslabel'; // 'classid'
                break;
            case 'years':
                foreach($this->months as $month) {
                    $record->{$month} = '';
                    $prevfield = $month;
                }
                break;
            case 'months':
                foreach($this->weeks as $wk) {
                    $record->{$wk} = '';
                    $prevfield = $wk;
                }
                if (!empty($record->month)) {
                    $sparam = new stdClass;
                    $sparam->int_month = $record->month;
                    $sparam->str_month = get_string($this->months[$record->month-1],
                                                    $this->langfile);
                    $record->month = get_string('month_format', $this->langfile,
                                                $sparam);
                }
                break;
            case 'weeks':
                foreach($this->wkdays as $day) {
                    $record->{$day} = '';
                    $prevfield = $day;
                }
                break;
            default:
                error_log("sitewide_time_summmary_report::transform_group_column_summary() - illegal segment: {$this->segment}");

        }
        if (empty($record->year)) {
            $record->year = get_string('no_time_logged', $this->langfile,
                                strtolower(get_string('seg_years', $this->langfile)));
        }
        if (empty($record->year) || empty($record->month)) {
            $record->month = get_string('no_time_logged', $this->langfile,
                                 strtolower(get_string('seg_months', $this->langfile)));
        }
        if (empty($record->year) || empty($record->week)) {
            $record->week = get_string('no_time_logged', $this->langfile,
                                strtolower(get_string('seg_weeks', $this->langfile)));
        }

        if (!empty($prevfield)) {
            $record->{$prevfield} = get_string('grouping_totaltime',
                                               $this->langfile);
        }
        $totaltime_fmt = ($export_format == php_report::$EXPORT_FORMAT_CSV)
                         ? 'time_format' : 'totaltime_format';
        $record->totaltime = $this->format_time(get_string($totaltime_fmt,
                                                           $this->langfile),
                                                $this->grouptotal);
        $this->grouptotal = 0; // reset for next time

        $record->user_id = fullname($record);

        return $record;
    }

    /**
     * Specifies an SQL statement that will retrieve users and their cluster assignments
     *
     * @param   array   $columns  The list of columns automatically calculated
     *                            by get_select_columns()
     * @return  array   The report's main sql statement, as well as the
     *                  applicable SQL parameters
     */
    function get_report_sql($columns) {
        $this->get_filter_values(); // populate class filter values

        //obtain all course contexts where this user can view reports
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        // TBV: $contexts = pm_context_set::for_user_with_capability(???)

        //make sure we only count courses within those contexts
        //$permissions_filter = $contexts->sql_filter_for_context_level('crlmu.id', 'user');
        $filter_obj = $contexts->get_filter('id', 'user');
        $filter_sql = $filter_obj->get_sql(false, 'crlmu', SQL_PARAMS_NAMED);
        $params = array();
        $permissions_filter = 'TRUE';
        if (isset($filter_sql['where'])) {
            $permissions_filter = $filter_sql['where'];
            $params = $filter_sql['where_parameters'];
        }
        //error_log("SWTS::get_report_sql(): permissions_filter = {$permissions_filter}");

        $sql = "SELECT $columns, u.firstname as firstname, u.lastname as lastname, clsm.moodlecourseid AS mcourse_id
            FROM {user} u
            JOIN {crlm_user} crlmu ON u.idnumber = crlmu.idnumber
            JOIN {crlm_class_enrolment} clsenr ON crlmu.id = clsenr.userid
            JOIN {crlm_class} cls ON cls.id = clsenr.classid";

        // Check that the class is open during report dates
        if (!empty($this->startdate)) {
            $sql .= " AND (cls.enddate = 0 OR {$this->startdate} <= cls.enddate)";
        }
        if (!empty($this->enddate)) {
            $sql .= " AND (cls.startdate = 0 OR {$this->enddate} >= cls.startdate)";
        }

        $sql .= "
                 JOIN {crlm_course} crs ON cls.courseid = crs.id
            LEFT JOIN {crlm_class_moodle} clsm ON cls.id = clsm.classid";

        if ($this->segment != 'noseg') {
            $sql .= "
            LEFT JOIN {etl_user_activity} etlua
              ON etlua.courseid = clsm.moodlecourseid AND etlua.userid = u.id ";
            if (!empty($this->startdate)) {
                $sql .= "AND etlua.hour >= {$this->startdate} ";
            }
            if (!empty($this->enddate)) {
                $sql .= "AND etlua.hour < {$this->enddate}";
            }
        }
        $sql .= "
           WHERE {$permissions_filter} ";

        if (empty(elis::$config->elis_program->legacy_show_inactive_users)) {
            $sql .= ' AND crlmu.inactive = 0';
        }

        //error_log("sitewide_time_summary_report.php::get_report_sql($columns); sql={$sql}");
        return array($sql, $params);
    }

    /**
     *  Required columns for Report
     *
     * @uses none
     * @param none
     * @return array of table report columns
     */
    function get_columns() {
        $this->get_filter_values(); // populate class filter values
        $this->columnfields = array();
        $columns = array();
        $columns[] = new table_report_column('crs.name AS coursename', get_string('column_coursename', $this->langfile), 'studentname', 'left', true);
        $this->columnfields[] = 'crs.name AS coursename';
        $columns[] = new table_report_column('cls.idnumber AS classid', get_string('column_classid', $this->langfile), 'idnumber', 'left', true);
        $this->columnfields[] = 'cls.idnumber AS classid';
        if ($this->segment == 'weeks') {
            foreach ($this->wkdays as $day) {
                $columns[] = new table_report_column("SUM(etlua.duration) AS $day", get_string($day, $this->langfile), $day, 'center', false);
                $this->columnfields[] = "SUM(etlua.duration) AS $day";
            }
        } else if ($this->segment == 'months') {
            foreach ($this->weeks as $wk) {
                $columns[] = new table_report_column("SUM(etlua.duration) AS $wk", get_string($wk, $this->langfile), $wk, 'center', false);
                $this->columnfields[] = "SUM(etlua.duration) AS $wk";
            }
        } else if ($this->segment == 'years') {
            foreach ($this->months as $month) {
                $columns[] = new table_report_column("SUM(etlua.duration) AS $month", get_string($month, $this->langfile), $month, 'center', false);
                $this->columnfields[] = "SUM(etlua.duration) AS $month";
            }
        } else {
            // dummy field for group column summary label
            $columns[] = new table_report_column("0 AS gcslabel", '', 'gsclabel', 'right', false, true, true, array(php_report::$EXPORT_FORMAT_HTML, php_report::$EXPORT_FORMAT_PDF));
            $this->columnfields[] = "0 AS gcslabel";
        }

        $columns[] = new table_report_column('0 AS totaltime',
                         get_string('column_totaltime', $this->langfile),
                         'totaltime', 'left', false);
        $this->columnfields[] = '0 AS totaltime';

        return $columns;
    }

    /**
     * Method to add GROUP BY clauses to report
     */
    function add_groupby_clause($grpby) {
        if (!empty($this->groupby)) {
            $this->groupby .= ', ';
        }
        $this->groupby .= $grpby;
    }

    /**
     * Method that specifies fields to group the results by
     * (header displayed when these fields change)
     *
     * @uses $CFG
     * @param none
     * @return array - List of objects containing grouping id, field names,
     *                display labels and sort order
     */
    function get_grouping_fields() {
        global $CFG;

        $this->get_filter_values();

        $timestampfield = 'etlua.hour';

        define('MYSQL_WEEK_MODE', 0); // TBD: WEEK param#2 mode = 0, 2 ???

        // Define required SQL functions for various DB families
        $sqlfcns = array(
            'mysql' => array('year'  => 'YEAR(FROM_UNIXTIME(%s))',
                             'month' => 'MONTH(FROM_UNIXTIME(%s))',
                             'week'  => 'WEEK(FROM_UNIXTIME(%s), %d)'),
            'postgres' => array('year'
                                => "date_part('year', to_timestamp(%s))",
                                'month'
                                => "date_part('month' to_timestamp(%s))",
                                'week'
                                => "date_part('week', to_timestamp(%s))"),
        );
        $dbfamily = $CFG->dbfamily;
        if (!array_key_exists($dbfamily, $sqlfcns)) {
            print_error('dbtype_not_supported', $this->langfile, '', $dbfamily);
        }

        $this->get_filter_values(); // require filter/report segment value
        $grpfields = array();

        $grpfields[] = new table_report_grouping('user_id','u.id',
                           get_string('grouping_studentname', $this->langfile),
                           'ASC', array(), 'above', 'u.lastname ASC');
        $grpfields[] = new table_report_grouping('user_idnumber','u.idnumber',
                           get_string('grouping_idnumber', $this->langfile),
                           'ASC');

        // Set initial GROUP BY fields (TBD)
        $this->groupby = 'crs.name, u.id, clsm.moodlecourseid, clsm.classid';
        if ($this->segment != 'noseg') {
            // Year required for year, months and weeks segment settings!
            $timefield = sprintf($sqlfcns[$dbfamily]['year'],
                                 $timestampfield);
            $grpfields[] = new table_report_grouping('year', $timefield,
                               get_string('grouping_year', $this->langfile),
                               'ASC');
            $this->add_groupby_clause($timefield);
        }

        if ($this->segment == 'months') {
            // Add month grouping
            $timefield = sprintf($sqlfcns[$dbfamily]['month'],
                                 $timestampfield);
            $grpfields[] = new table_report_grouping('month', $timefield,
                               get_string('grouping_month', $this->langfile),
                               'ASC');
            $this->add_groupby_clause($timefield);
        } else if ($this->segment == 'weeks') {
            // Add week-of-year grouping
            $timefield = sprintf($sqlfcns[$dbfamily]['week'],
                                 $timestampfield, MYSQL_WEEK_MODE);
            $grpfields[] = new table_report_grouping('week', $timefield,
                               get_string('grouping_week', $this->langfile),
                               'ASC');
            $this->add_groupby_clause($timefield);
        }

        $this->summaryfield = ($this->segment == 'noseg')
                               ? 'u.id' // WAS 'u.id'
                               : $timefield // 'cls.id'
                              ;
        return $grpfields;
    }

    /**
     * Required GROUP BY clauses
     */
    function get_report_sql_groups() {
        return $this->groupby;
    }

    /**
     * Determines whether the current user can view this report,
     * based on being logged in and php_report:view capability
     *
     * @param none
     * @return  boolean - true if permitted, otherwise false
     */
    function can_view_report() {
        //Check for report view capability
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        //make sure context libraries are loaded
        $this->require_dependencies();

        //make sure the current user can view reports in at least one context
        $contexts = get_contexts_by_capability_for_user('user', $this->access_capability, $this->userid);
        return !$contexts->is_empty();
    }

    // Debug helper function
    function err_dump($obj, $name = '') {
        ob_start();
        var_dump($obj);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log('err_dump:: '.$name." = {$tmp}");
    }

    /**
     * API functions for defining background colours
     */

    /**
     * Specifies the RGB components of the colour used for all column
     * headers on this report (currently used in PDF export only)
     *
     * @return  int array  Array containing the red, green, and blue components in that order
     */
    function get_column_header_colour() {
        return array(169, 245, 173);
    }

    /**
     * Specifies the RGB components of one or more colours used in an
     * alternating fashion as report row backgrounds
     *
     * @return  array array  Array containing arrays of red, green, and blue components
     *                       (one array for each alternating colour)
     */
    function get_row_colours() {
        return array(array(255, 255, 255));
    }

    /**
     * Specifies the RGB components of one or more colours used as backgrounds
     * in grouping headers
     *
     * @return  array array  Array containing arrays of red, green and blue components
     *                       (one array for each grouping level, going top-down,
     *                       last colour is repeated if there are more groups than colours)
     */
    function get_grouping_row_colours() {
        return array(array(217, 217, 217));
    }
}

