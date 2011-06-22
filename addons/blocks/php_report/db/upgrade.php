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
 * @subpackage php_reports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

function xmldb_block_php_report_upgrade($oldversion = 0) {
    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2011040600) {

    /// Define table php_report_schedule to be created
        $table = new XMLDBTable('php_report_schedule');

    /// Adding fields to table php_report_schedule
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('report', XMLDB_TYPE_CHAR, '63', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('config', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table php_report_schedule
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Adding indexes to table php_report_schedule
        $table->addIndexInfo('report_idx', XMLDB_INDEX_NOTUNIQUE, array('report'));

    /// Launch create table for php_report_schedule
        $result = $result && create_table($table);


    /// Define field userid to be added to php_report_schedule
        $table = new XMLDBTable('php_report_schedule');
        $field = new XMLDBField('userid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'id');

    /// Launch add field userid
        $result = $result && add_field($table, $field);

    /// Define index userid_idx (not unique) to be added to php_report_schedule
        $table = new XMLDBTable('php_report_schedule');
        $index = new XMLDBIndex('userid_idx');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('userid'));

    /// Launch add index userid_idx
        $result = $result && add_index($table, $index);
    }
    return $result;
}
