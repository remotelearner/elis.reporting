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
 * @subpackage pm-blocks-phpreports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/blocks/php_report/lib/filtering.php');

//report instance id can be a block instance id
//or a general report shortname
$id = required_param('id', PARAM_CLEAN);
//selected export format
$format = required_param('format', PARAM_CLEAN);

//needed to satisfy base page requirements
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

//load filter classes
php_report_filtering_require_dependencies();

$report = php_report::get_default_instance($id);

//permissions checking
if ($report->can_view_report()) {
    //NOTE: this is fast because it will not populate filter values
    $report->init_all($id);
    //require any necessary report-specific dependencies
    $report->require_dependencies();

    //make sure we have enough resources to export our report
    php_report::allocate_extra_resources();

    //obtain the query and parameter values
    list($sql, $params) = $report->get_complete_sql_query(false);

    //initiate download using sql query without paging
    $report->download($format, $sql, $params);
}

