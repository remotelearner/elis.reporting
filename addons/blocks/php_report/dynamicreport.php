<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once('../../config.php');
require_once('php_report_block.class.php');

//report instance id can be a block instance id
//or a general report shortname
$id = required_param('id', PARAM_CLEAN);

//indicates a page change
$page = optional_param('page', -1, PARAM_INT);
//paging specific to the gas gauge type report
$gas_gauge_page = optional_param('gas_gauge_page', -1, PARAM_INT);
$sort = optional_param('sort', '', PARAM_CLEAN);
$dir = optional_param('dir', '', PARAM_CLEAN);
$filterchange = optional_param('filterchange', '', PARAM_CLEAN);

//This loads all the appropriate report-type classes
php_report_block::require_dependencies();

//set sorting to ignore things before the AS when applicable
if(!empty($sort)) {
    $lowercase_sort = strtolower($sort);
    $as_position = strpos($lowercase_sort, ' as ');
    if($as_position !== FALSE) {
        $sort = substr($sort, $as_position + strlen(' as '));
    }
    $SESSION->php_reports[$id]->currentsort = $sort;
    $SESSION->php_reports[$id]->currentdir = $dir;
}

if($page == -1 && $gas_gauge_page == -1 && ($sort == '' || $dir == '') && empty($filterchange)) {
    //Toggle visibility
    $SESSION->php_reports[$id]->visible = !$SESSION->php_reports[$id]->visible;
} else {
    //Clear the cache because we are changing pages or sorting
    $SESSION->php_reports[$id]->lastload = 0;
}

//Reset the page when we change filters
if(!empty($filterchange)) {
    $SESSION->php_reports[$id]->currentpage = 0;
}

//Update the page in memory
if($page != -1) {
    $SESSION->php_reports[$id]->currentpage = $page;
}

$additional_options = array();

//Update the gas gauge page in memory
if($gas_gauge_page != -1) {
    $SESSION->php_reports[$id]->gas_gauge_page = $gas_gauge_page;
    $additional_options['gas_gauge_page'] = $gas_gauge_page;
    
    //if this is specifically a change in the gas gauge page,
    //reset the current tabular page to the first one
    if ($page == -1) {
        $SESSION->php_reports[$id]->currentpage = 0;
    }
}

//Actually display the report if appropriate
if($SESSION->php_reports[$id]->visible) {
    echo $SESSION->php_reports[$id]->execute($SESSION->php_reports[$id]->currentpage,
                                             $id,
                                             $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id,
                                             $SESSION->php_reports[$id]->currentsort,
                                             $SESSION->php_reports[$id]->currentdir,
                                             $filterchange,
                                             $additional_options);
} else {
    echo '';
}

?>