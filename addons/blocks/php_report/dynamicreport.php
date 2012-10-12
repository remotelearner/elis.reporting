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
require_once('php_report_base.php');

//needed to satisfy the base page type
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_pagelayout('embedded'); // TBV
$PAGE->set_pagetype('elis'); // TBV

$report_shortname = required_param('id', PARAM_CLEAN);

//indicates a page change
$page = optional_param('page', 0, PARAM_INT);
// current sort order
$sort = optional_param('sort', '', PARAM_CLEAN);
$dir  = optional_param('dir', '', PARAM_CLEAN); // TBD: 'ASC' ?

$instance = php_report::get_default_instance($report_shortname);
$instance->main($sort, $dir, $page, 20, '', $report_shortname);

