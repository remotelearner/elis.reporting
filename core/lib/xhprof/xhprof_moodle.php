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
 * @package    core
 * @subpackage profiling
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// need some stuff from xhprof
require_once($CFG->libdir . '/xhprof/xhprof_lib/utils/xhprof_lib.php');
require_once($CFG->libdir . '/xhprof/xhprof_lib/utils/xhprof_runs.php');
// need some stuff from moodle
require_once($CFG->libdir.'/tablelib.php');

// TODO: Change the implementation below to proper profiling class

/**
 * Returns if profiling is running, optionally setting it
 */
function profiling_is_running($value = null) {
    static $running = null;

    if (!is_null($value)) {
        $running = (bool)$value;
    }

    return $running;
}

/**
 * Returns if profiling has been saved, optionally setting it
 */
function profiling_is_saved($value = null) {
    static $saved = null;

    if (!is_null($value)) {
        $saved = (bool)$value;
    }

    return $saved;
}

/**
 * Start profiling observing all the configuration
 */
function profiling_start() {
    global $CFG, $SESSION, $SCRIPT;

    // If profiling isn't available, nothing to start
    if (!extension_loaded('xhprof') || !function_exists('xhprof_enable')) {
        return false;
    }

    // If profiling isn't enabled, nothing to start
    if (empty($CFG->profilingenabled) && empty($CFG->earlyprofilingenabled)) {
        return false;
    }

    // If profiling is already running or saved, nothing to start
    if (profiling_is_running() || profiling_is_saved()) {
        return false;
    }

    // Set script (from global if available, else our own)
    $script = !empty($SCRIPT) ? $SCRIPT : profiling_get_script();

    // Get PGC variables
    $check = 'PROFILEME';
    $profileme = isset($_POST[$check]) || isset($_GET[$check]) || isset($_COOKIE[$check]) ? true : false;
    $profileme = $profileme && !empty($CFG->profilingallowme);
    $check = 'DONTPROFILEME';
    $dontprofileme = isset($_POST[$check]) || isset($_GET[$check]) || isset($_COOKIE[$check]) ? true : false;
    $dontprofileme = $dontprofileme && !empty($CFG->profilingallowme);
    $check = 'PROFILEALL';
    $profileall = isset($_POST[$check]) || isset($_GET[$check]) || isset($_COOKIE[$check]) ? true : false;
    $profileall = $profileall && !empty($CFG->profilingallowall);
    $check = 'PROFILEALLSTOP';
    $profileallstop = isset($_POST[$check]) || isset($_GET[$check]) || isset($_COOKIE[$check]) ? true : false;
    $profileallstop = $profileallstop && !empty($CFG->profilingallowall);

    // DONTPROFILEME detected, nothing to start
    if ($dontprofileme) {
        return false;
    }

    // PROFILEALLSTOP detected, clean the mark in seesion and continue
    if ($profileallstop && !empty($SESSION)) {
        unset($SESSION->profileall);
    }

    // PROFILEALL detected, set the mark in session and continue
    if ($profileall && !empty($SESSION)) {
        $SESSION->profileall = true;

    // SESSION->profileall detected, set $profileall
    } else if (!empty($SESSION->profileall)) {
        $profileall = true;
    }

    // Evaluate automatic (random) profiling if necessary
    $profileauto = false;
    if (!empty($CFG->profilingautofrec)) {
        $profileauto = (mt_rand(1, $CFG->profilingautofrec) === 1);
    }

    // See if the $script matches any of the included patterns
    $included = empty($CFG->profilingincluded) ? '' : $CFG->profilingincluded;
    $profileincluded = profiling_string_matches($script, $included);

    // See if the $script matches any of the excluded patterns
    $excluded = empty($CFG->profilingexcluded) ? '' : $CFG->profilingexcluded;
    $profileexcluded = profiling_string_matches($script, $excluded);

    // Decide if profile auto must happen (observe matchings)
    $profileauto = $profileauto && $profileincluded && !$profileexcluded;

    // Decide if profile by match must happen (only if profileauto is disabled)
    $profilematch = $profileincluded && !$profileexcluded && empty($CFG->profilingautofrec);

    // If not auto, me, all, match have been detected, nothing to do
    if (!$profileauto && !$profileme && !$profileall && !$profilematch) {
        return false;
    }

    // Arrived here, the script is going to be profiled, let's do it
    $ignore = array('call_user_func', 'call_user_func_array');
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY, array('ignored_functions' =>  $ignore));
    profiling_is_running(true);

    // Started, return true
    return true;
}

/**
 * Stop profiling, gathering results and storing them
 */
function profiling_stop() {
    global $CFG, $DB, $SCRIPT;

    // If profiling isn't available, nothing to stop
    if (!extension_loaded('xhprof') || !function_exists('xhprof_enable')) {
        return false;
    }

    // If profiling isn't enabled, nothing to stop
    if (empty($CFG->profilingenabled) && empty($CFG->earlyprofilingenabled)) {
        return false;
    }

    // If profiling is not running or is already saved, nothing to stop
    if (!profiling_is_running() || profiling_is_saved()) {
        return false;
    }

    // Set script (from global if available, else our own)
    $script = !empty($SCRIPT) ? $SCRIPT : profiling_get_script();

    // Arrived here, profiling is running, stop and save everything
    profiling_is_running(false);
    $data = xhprof_disable();

    // We only save the run after ensuring the DB table exists
    // (this prevents problems with profiling runs enabled in
    // config.php before Moodle is installed. Rare but...
    $tables = $DB->get_tables();
    if (!in_array('profiling', $tables)) {
        return false;
    }

    $run = new moodle_xhprofrun();
    $run->prepare_run($script);
    $runid = $run->save_run($data, null);
    profiling_is_saved(true);

    // Prune old runs
    profiling_prune_old_runs($runid);

    // Finished, return true
    return true;
}

function profiling_prune_old_runs($exception = 0) {
    global $CFG, $DB;

    // Setting to 0 = no prune
    if (empty($CFG->profilinglifetime)) {
        return;
    }

    $cuttime = time() - ($CFG->profilinglifetime * 60);
    $params = array('cuttime' => $cuttime, 'exception' => $exception);

    $DB->delete_records_select('profiling', 'runreference = 0 AND
                                             timecreated < :cuttime AND
                                             runid != :exception', $params);
}

/**
 * Returns the path to the php script being requested
 *
 * Note this function is a partial copy of initialise_fullme() and
 * setup_get_remote_url(), in charge of setting $FULLME, $SCRIPT and
 * friends. To be used by early profiling runs in situations where
 * $SCRIPT isn't defined yet
 *
 * @return string absolute path (wwwroot based) of the script being executed
 */
function profiling_get_script() {
    global $CFG;

    $wwwroot = parse_url($CFG->wwwroot);

    if (!isset($wwwroot['path'])) {
        $wwwroot['path'] = '';
    }
    $wwwroot['path'] .= '/';

    $path = $_SERVER['SCRIPT_NAME'];

    if (strpos($path, $wwwroot['path']) === 0) {
        return substr($path, strlen($wwwroot['path']) - 1);
    }
    return '';
}

function profiling_urls($report, $runid, $runid2 = null) {
    global $CFG;

    $url = '';
    switch ($report) {
        case 'run':
            $url = $CFG->wwwroot . '/lib/xhprof/xhprof_html/index.php?run=' . $runid;
            break;
        case 'diff':
            $url = $CFG->wwwroot . '/lib/xhprof/xhprof_html/index.php?run1=' . $runid . '&amp;run2=' . $runid2;
            break;
        case 'graph':
            $url = $CFG->wwwroot . '/lib/xhprof/xhprof_html/callgraph.php?run=' . $runid;
            break;
    }
    return $url;
}

function profiling_print_run($run, $prevrunid = null) {
    global $CFG, $OUTPUT;

    $output = '';

    // Prepare the runreference/runcomment form
    $checked = $run->runreference ? ' checked=checked' : '';
    $referenceform = "<form id=\"profiling_runreference\" action=\"index.php\" method=\"GET\">" .
                     "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\"/>".
                     "<input type=\"hidden\" name=\"runid\" value=\"$run->runid\"/>".
                     "<input type=\"hidden\" name=\"listurl\" value=\"$run->url\"/>".
                     "<input type=\"checkbox\" name=\"runreference\" value=\"1\"$checked/>&nbsp;".
                     "<input type=\"text\" name=\"runcomment\" value=\"$run->runcomment\"/>&nbsp;".
                     "<input type=\"submit\" value=\"" . get_string('savechanges') ."\"/>".
                     "</form>";

    $table = new html_table();
    $table->align = array('right', 'left');
    $table->tablealign = 'center';
    $table->attributes['class'] = 'profilingruntable';
    $table->colclasses = array('label', 'value');
    $table->data = array(
       array(get_string('runid', 'report_profiling'), $run->runid),
       array(get_string('url'), $run->url),
       array(get_string('date'), userdate($run->timecreated, '%d %B %Y, %H:%M')),
       array(get_string('executiontime', 'report_profiling'), format_float($run->totalexecutiontime / 1000, 3) . ' ms'),
       array(get_string('cputime', 'report_profiling'), format_float($run->totalcputime / 1000, 3) . ' ms'),
       array(get_string('calls', 'report_profiling'), $run->totalcalls),
       array(get_string('memory', 'report_profiling'), format_float($run->totalmemory / 1024, 0) . ' KB'),
       array(get_string('markreferencerun', 'report_profiling'), $referenceform));
    $output = $OUTPUT->box(html_writer::table($table), 'generalbox boxwidthwide boxaligncenter profilingrunbox', 'profiling_summary', true);
    // Add link to details
    $strviewdetails = get_string('viewdetails', 'report_profiling');
    $url = profiling_urls('run', $run->runid);
    $output.=$OUTPUT->heading('<a href="' . $url . '" onclick="javascript:window.open(' . "'" . $url . "'" . ');' .
                              'return false;"' . ' title="">' . $strviewdetails . '</a>', 3, 'main profilinglink');
    // If there is one previous run marked as reference, add link to diff
    if ($prevrunid) {
        $strviewdiff = get_string('viewdiff', 'report_profiling');
        $url = 'index.php?runid=' . $run->runid . '&amp;runid2=' . $prevrunid . '&amp;listurl=' . urlencode($run->url);
        $output.=$OUTPUT->heading('<a href="' . $url . '" title="">' . $strviewdiff . '</a>', 3, 'main profilinglink');
    }

    return $output;
}

function profiling_print_rundiff($run1, $run2) {
    global $CFG, $OUTPUT;

    $output = '';

    // Prepare the reference/comment information
    $referencetext1 = ($run1->runreference ? get_string('yes') : get_string('no')) .
                      ($run1->runcomment ? ' - ' . s($run1->runcomment) : '');
    $referencetext2 = ($run2->runreference ? get_string('yes') : get_string('no')) .
                      ($run2->runcomment ? ' - ' . s($run2->runcomment) : '');

    // Calculate global differences
    $diffexecutiontime = profiling_get_difference($run1->totalexecutiontime, $run2->totalexecutiontime, 'ms', 1000);
    $diffcputime       = profiling_get_difference($run1->totalcputime, $run2->totalcputime, 'ms', 1000);
    $diffcalls         = profiling_get_difference($run1->totalcalls, $run2->totalcalls);
    $diffmemory        = profiling_get_difference($run1->totalmemory, $run2->totalmemory, 'KB', 1024);

    $table = new html_table();
    $table->align = array('right', 'left', 'left', 'left');
    $table->tablealign = 'center';
    $table->attributes['class'] = 'profilingruntable';
    $table->colclasses = array('label', 'value1', 'value2');
    $table->data = array(
       array(get_string('runid', 'report_profiling'),
           '<a href="index.php?runid=' . $run1->runid . '&listurl=' . urlencode($run1->url) . '" title="">' . $run1->runid . '</a>',
           '<a href="index.php?runid=' . $run2->runid . '&listurl=' . urlencode($run2->url) . '" title="">' . $run2->runid . '</a>'),
       array(get_string('url'), $run1->url, $run2->url),
       array(get_string('date'), userdate($run1->timecreated, '%d %B %Y, %H:%M'),
           userdate($run2->timecreated, '%d %B %Y, %H:%M')),
       array(get_string('executiontime', 'report_profiling'),
           format_float($run1->totalexecutiontime / 1000, 3) . ' ms',
           format_float($run2->totalexecutiontime / 1000, 3) . ' ms ' . $diffexecutiontime),
       array(get_string('cputime', 'report_profiling'),
           format_float($run1->totalcputime / 1000, 3) . ' ms',
           format_float($run2->totalcputime / 1000, 3) . ' ms ' . $diffcputime),
       array(get_string('calls', 'report_profiling'), $run1->totalcalls, $run2->totalcalls . ' ' . $diffcalls),
       array(get_string('memory', 'report_profiling'),
           format_float($run1->totalmemory / 1024, 0) . ' KB',
           format_float($run2->totalmemory / 1024, 0) . ' KB ' . $diffmemory),
       array(get_string('referencerun', 'report_profiling'), $referencetext1, $referencetext2));
    $output = $OUTPUT->box(html_writer::table($table), 'generalbox boxwidthwide boxaligncenter profilingrunbox', 'profiling_summary', true);
    // Add link to details
    $strviewdetails = get_string('viewdiffdetails', 'report_profiling');
    $url = profiling_urls('diff', $run1->runid, $run2->runid);
    //$url =  $CFG->wwwroot . '/admin/report/profiling/index.php?run=' . $run->runid;
    $output.=$OUTPUT->heading('<a href="' . $url . '" onclick="javascript:window.open(' . "'" . $url . "'" . ');' .
                              'return false;"' . ' title="">' . $strviewdetails . '</a>', 3, 'main profilinglink');
    return $output;
}

/**
 * Helper function that returns the HTML fragment to
 * be displayed on listing mode, it includes actions
 * like deletion/export/import...
 */
function profiling_list_controls($listurl) {
    global $CFG, $OUTPUT;

    $output = '';

    return $output;
}

/**
 * Helper function that looks for matchings of one string
 * against an array of * wildchar patterns
 */
function profiling_string_matches($string, $patterns) {
    $patterns = explode(',', $patterns);
    foreach ($patterns as $pattern) {
        // Trim and prepare pattern
        $pattern = str_replace('\*', '.*', preg_quote(trim($pattern), '~'));
        // Don't process empty patterns
        if (empty($pattern)) {
            continue;
        }
        if (preg_match('~' . $pattern . '~', $string)) {
            return true;
        }
    }
    return false;
}

/**
 * Helper function that, given to floats, returns their numerical
 * and percentual differences, propertly formated and cssstyled
 */
function profiling_get_difference($number1, $number2, $units = '', $factor = 1, $numdec = 2) {
    $numdiff = $number2 - $number1;
    $perdiff = 0;
    if ($number1 != $number2) {
        $perdiff = $number1 != 0 ? ($number2 * 100 / $number1) - 100 : 0;
    }
    $sign      = $number2 > $number1 ? '+' : '';
    $delta     = abs($perdiff) > 0.25 ? '&Delta;' : '&asymp;';
    $spanclass = $number2 > $number1 ? 'worse' : ($number1 > $number2 ? 'better' : 'same');
    $importantclass= abs($perdiff) > 1 ? ' profiling_important' : '';
    $startspan = '<span class="profiling_' . $spanclass . $importantclass . '">';
    $endspan   = '</span>';
    $fnumdiff = $sign . format_float($numdiff / $factor, $numdec);
    $fperdiff = $sign . format_float($perdiff, $numdec);
    return $startspan . $delta . ' ' . $fnumdiff . ' ' . $units . ' (' . $fperdiff . '%)' . $endspan;
}

/**
 * Custom implementation of iXHProfRuns
 *
 * This class is one implementation of the iXHProfRuns interface, in charge
 * of storing and retrieve profiling run data to/from DB (profiling table)
 *
 * The interface only defines two methods to be defined: get_run() and
 * save_run() we'll be implementing some more in order to keep all the
 * rest of information in our runs properly handled.
 */
class moodle_xhprofrun implements iXHProfRuns {

    protected $runid = null;
    protected $url = null;
    protected $totalexecutiontime = 0;
    protected $totalcputime = 0;
    protected $totalcalls = 0;
    protected $totalmemory = 0;
    protected $timecreated = 0;

    public function __construct() {
        $this->timecreated = time();
    }

    /**
     * Given one runid and one type, return the run data
     * and some extra info in run_desc from DB
     *
     * Note that $type is completely ignored
     */
    public function get_run($run_id, $type, &$run_desc) {
        global $DB;

        $rec = $DB->get_record('profiling', array('runid' => $run_id), '*', MUST_EXIST);

        $this->runid = $rec->runid;
        $this->url = $rec->url;
        $this->totalexecutiontime = $rec->totalexecutiontime;
        $this->totalcputime = $rec->totalcputime;
        $this->totalcalls = $rec->totalcalls;
        $this->totalmemory = $rec->totalmemory;
        $this->timecreated = $rec->timecreated;

        $run_desc = $this->url . ($rec->runreference ? ' (R) ' : ' ') . ' - ' . s($rec->runcomment);

        return unserialize(base64_decode($rec->data));
    }

    /**
     * Given some run data, one type and, optionally, one runid
     * store the information in DB
     *
     * Note that $type is completely ignored
     */
    public function save_run($xhprof_data, $type, $run_id = null) {
        global $DB;

        if (is_null($this->url)) {
            xhprof_error("Warning: You must use the prepare_run() method before saving it");
        }

        // Calculate runid if needed
        $this->runid = is_null($run_id) ? md5($this->url . '-' . uniqid()) : $run_id;

        // Calculate totals
        $this->totalexecutiontime = $xhprof_data['main()']['wt'];
        $this->totalcputime = $xhprof_data['main()']['cpu'];
        $this->totalcalls = array_reduce($xhprof_data, array($this, 'sum_calls'));
        $this->totalmemory = $xhprof_data['main()']['mu'];

        // Prepare data
        $rec = new stdClass();
        $rec->runid = $this->runid;
        $rec->url = $this->url;
        $rec->data = base64_encode(serialize($xhprof_data));
        $rec->totalexecutiontime = $this->totalexecutiontime;
        $rec->totalcputime = $this->totalcputime;
        $rec->totalcalls = $this->totalcalls;
        $rec->totalmemory = $this->totalmemory;
        $rec->timecreated = $this->timecreated;

        $DB->insert_record('profiling', $rec);
        return $this->runid;
    }

    public function prepare_run($url) {
        $this->url = $url;
    }

    // Private API starts here

    protected function sum_calls($sum, $data) {
        return $sum + $data['ct'];
    }
}

/**
 * Simple subclass of {@link table_sql} that provides
 * some custom formatters for various columns, in order
 * to make the main profiles list nicer
 */
class xhprof_table_sql extends table_sql {

    protected $listurlmode = false;

    /**
     * Get row classes to be applied based on row contents
     */
    function get_row_class($row) {
        return $row->runreference ? 'referencerun' : ''; // apply class to reference runs
    }

    /**
     * Define it the table is in listurlmode or not, output will
     * be different based on that
     */
    function set_listurlmode($listurlmode) {
        $this->listurlmode = $listurlmode;
    }

    /**
     * Format URL, so it points to last run for that url
     */
    protected function col_url($row) {
        global $OUTPUT;

        // Build the link to latest run for the script
        $scripturl = new moodle_url('/admin/report/profiling/index.php', array('script' => $row->url, 'listurl' => $row->url));
        $scriptaction = $OUTPUT->action_link($scripturl, $row->url);

        // Decide, based on $this->listurlmode which actions to show
        if ($this->listurlmode) {
            $detailsaction = '';
        } else {
            // Build link icon to script details (pix + url + actionlink)
            $detailsimg = $OUTPUT->pix_icon('t/right', get_string('profilingfocusscript', 'report_profiling', $row->url));
            $detailsurl = new moodle_url('/admin/report/profiling/index.php', array('listurl' => $row->url));
            $detailsaction = $OUTPUT->action_link($detailsurl, $detailsimg);
        }

        return $scriptaction . '&nbsp;' . $detailsaction;
    }

    /**
     * Format profiling date, human and pointing to run
     */
    protected function col_timecreated($row) {
        global $OUTPUT;
        $fdate = userdate($row->timecreated, '%d %b %Y, %H:%M');
        $url = new moodle_url('/admin/report/profiling/index.php', array('runid' => $row->runid, 'listurl' => $row->url));
        return $OUTPUT->action_link($url, $fdate);
    }

    /**
     * Format execution time
     */
    protected function col_totalexecutiontime($row) {
        return format_float($row->totalexecutiontime / 1000, 3) . ' ms';
    }

    /**
     * Format cpu time
     */
    protected function col_totalcputime($row) {
        return format_float($row->totalcputime / 1000, 3) . ' ms';
    }

    /**
     * Format memory
     */
    protected function col_totalmemory($row) {
        return format_float($row->totalmemory / 1024, 3) . ' KB';
    }
}
