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

//determine refresh conditions

//report instance id can be a block instance id
//or a general report shortname
$id = required_param('id', PARAM_CLEAN);
$page = required_param('page', PARAM_INT);
$sort = required_param('sort', PARAM_CLEAN);
$dir = required_param('dir', PARAM_CLEAN);

//make sure appropriate classes are loaded
php_report_block::require_dependencies();

//force reload
$SESSION->php_reports[$id]->lastload = 0;

//execute report
echo $SESSION->php_reports[$id]->execute($page == -1 ? 0 : $page, $id,
                                         $CFG->wwwroot . '/blocks/php_report/dynamicreport.php?id=' . $id,
                                         $sort, $dir, '');

?>