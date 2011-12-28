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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_elis_core_upgrade($oldversion=0) {
    global $CFG, $THEME, $DB, $OUTPUT;

    $dbman = $DB->get_manager();
    $result = true;

    if ($result && $oldversion < 2011063000) {

        // Define table elis_scheduled_tasks to be created
        $table = new xmldb_table('elis_scheduled_tasks');

        // Adding fields to table elis_scheduled_tasks
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '166', null, XMLDB_NOTNULL, null, null);
        $table->add_field('taskname', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('callfile', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('callfunction', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastruntime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextruntime', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('blocking', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('minute', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('hour', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('month', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('dayofweek', XMLDB_TYPE_CHAR, '25', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timezone', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '99');
        $table->add_field('runsremaining', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('customized', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table elis_scheduled_tasks
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_scheduled_tasks
        $table->add_index('plugin_idx', XMLDB_INDEX_NOTUNIQUE, array('plugin', 'taskname'));
        $table->add_index('nextruntime_idx', XMLDB_INDEX_NOTUNIQUE, array('nextruntime'));

        // Conditionally launch create table for elis_scheduled_tasks
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_workflow_instances to be created
        $table = new xmldb_table('elis_workflow_instances');

        // Adding fields to table elis_workflow_instances
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '127', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subtype', XMLDB_TYPE_CHAR, '127', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_workflow_instances
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_workflow_instances
        $table->add_index('usertype_idx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'type', 'subtype'));

        // Conditionally launch create table for elis_workflow_instances
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011063000, 'elis', 'core');
    }

    if ($result && $oldversion < 2011080100) {
        // create tables that were created by block curr_admin (if upgrading a
        // non-CM site)

        // Define table context_levels to be created
        $table = new xmldb_table('context_levels');

        // Adding fields to table context_levels
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table context_levels
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table context_levels
        $table->add_index('name', XMLDB_INDEX_NOTUNIQUE, array('name'));
        $table->add_index('component', XMLDB_INDEX_NOTUNIQUE, array('component'));

        // Conditionally launch create table for context_levels
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field to be created
        $table = new xmldb_table('elis_field');

        // Adding fields to table elis_field
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('datatype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('multivalued', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('forceunique', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null);

        // Adding keys to table elis_field
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field
        $table->add_index('shortname_idx', XMLDB_INDEX_NOTUNIQUE, array('shortname'));

        // Conditionally launch create table for elis_field
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_categories to be created
        $table = new xmldb_table('elis_field_categories');

        // Adding fields to table elis_field_categories
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys to table elis_field_categories
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for elis_field_categories
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_contextlevels to be created
        $table = new xmldb_table('elis_field_contextlevels');

        // Adding fields to table elis_field_contextlevels
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_contextlevels
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for elis_field_contextlevels
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_category_contexts to be created
        $table = new xmldb_table('elis_field_category_contexts');

        // Adding fields to table elis_field_category_contexts
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('contextlevel', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);

        // Adding keys to table elis_field_category_contexts
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_category_contexts
        $table->add_index('contextlevel_idx', XMLDB_INDEX_NOTUNIQUE, array('contextlevel'));
        $table->add_index('category_idx', XMLDB_INDEX_NOTUNIQUE, array('categoryid'));

        // Conditionally launch create table for elis_field_category_contexts
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_text to be created
        $table = new xmldb_table('elis_field_data_text');

        // Adding fields to table elis_field_data_text
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_text
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_text
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_text
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_int to be created
        $table = new xmldb_table('elis_field_data_int');

        // Adding fields to table elis_field_data_int
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_int
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_int
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_int
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_num to be created
        $table = new xmldb_table('elis_field_data_num');

        // Adding fields to table elis_field_data_num
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_NUMBER, '15, 5', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_num
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_num
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_num
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_data_char to be created
        $table = new xmldb_table('elis_field_data_char');

        // Adding fields to table elis_field_data_char
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table elis_field_data_char
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_data_char
        $table->add_index('context_idx', XMLDB_INDEX_NOTUNIQUE, array('contextid'));
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_data_char
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table elis_field_owner to be created
        $table = new xmldb_table('elis_field_owner');

        // Adding fields to table elis_field_owner
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null);
        $table->add_field('plugin', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('exclude', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0');
        $table->add_field('params', XMLDB_TYPE_TEXT, 'big', null, null, null, null);

        // Adding keys to table elis_field_owner
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table elis_field_owner
        $table->add_index('field_idx', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));

        // Conditionally launch create table for elis_field_owner
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011080100, 'elis', 'core');
    }

    if ($result && $oldversion < 2011080200) {

        // Define index sortorder_ix (not unique) to be dropped form elis_field
        // (so that we can change the default value)
        $table = new xmldb_table('elis_field');
        $index = new xmldb_index('sortorder_ix', XMLDB_INDEX_NOTUNIQUE, array('sortorder'));

        // Conditionally launch drop index shortname_idx
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Changing the default of field sortorder on table elis_field to 0
        $table = new xmldb_table('elis_field');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'categoryid');

        // Launch change of default for field sortorder
        $dbman->change_field_default($table, $field);

        // Changing the default of field sortorder on table elis_field_categories to 0
        $table = new xmldb_table('elis_field_categories');
        $field = new xmldb_field('sortorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'name');

        // Launch change of default for field sortorder
        $dbman->change_field_default($table, $field);

        // Changing the default of field forceunique on table elis_field to 0
        $table = new xmldb_table('elis_field');
        $field = new xmldb_field('forceunique', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'multivalued');

        // Launch change of default for field forceunique
        $dbman->change_field_default($table, $field);

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011080200, 'elis', 'core');
    }

    if ($result && $oldversion < 2011083000) {
        // Remove elis_info blocks
        $eiblock = $DB->get_record('block', array('name' => 'elis_info'));
        if ($eiblock) {
            // elis_info block exists
            $eiinstance = $DB->get_record('block_instances', array('blockname' => 'elis_info'));

            if ($eiinstance) {
                // elis_info instances exist, delete them ...
                $DB->delete_records('block_positions', array('blockinstanceid' => $eiinstance->id));

                // remove instance
                $DB->delete_records('block_instances', array('blockname' => 'elis_info'));
            }

            // remove any old instances
            $DB->delete_records('block_instance_old', array('blockid' => $eiblock->id));
            // remove any old pinned blocks
            $DB->delete_records('block_pinned_old', array('blockid' => $eiblock->id));
            // remove block record
            $DB->delete_records('block', array('id' => $eiblock->id));

            if (file_exists($CFG->dirroot .'/blocks/elis_info')) {
                echo $OUTPUT->notification("Warning: {$CFG->dirroot}/blocks/elis_info directory still exists - please remove it!");
            }
        }

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011083000, 'elis', 'core');
    }

    if ($result && $oldversion < 2011091401) {
        $table = new xmldb_table('elis_scheduled_tasks');

        // change precisions of cron fields from varchar(25) to varchar(50)
        $field = new xmldb_field('minute', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('hour', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('day', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('month', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        $field = new xmldb_field('dayofweek', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $dbman->change_field_precision($table, $field);

        // core savepoint reached
        upgrade_plugin_savepoint(true, 2011091401, 'elis', 'core');
    }

    return $result;
}
