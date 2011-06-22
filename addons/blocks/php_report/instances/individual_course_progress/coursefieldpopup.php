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

require_once ('../../../../config.php');
require_once($CFG->dirroot.'/curriculum/config.php');
require_once($CFG->dirroot.'/curriculum/lib/customfield.class.php');
require_once($CFG->dirroot.'/blocks/php_report/sharedlib.php');

// Get required yui javascript for ajax calls
require_js(array('yui_yahoo',
                 'yui_dom',
                 'yui_event',
                 'yui_connection',
                 "{$CFG->wwwroot}/curriculum/js/associate.class.js",
                 "{$CFG->wwwroot}/curriculum/js/customfields.js"),true);


$lang_file = 'rlreport_individual_course_progress';

$site = get_site();

$block_id = required_param('instance', PARAM_RAW);
$fieldidlist = optional_param('fieldidlist', null,  PARAM_TEXT);
$fieldnamelist = optional_param('fieldnamelist', null,  PARAM_TEXT);

// Get custom course fields by context level
$context = context_level_base::get_custom_context_level('course', 'block_curr_admin');
$fields = field::get_for_context_level($context);
$fields = $fields ? $fields : array();

//Unserialize fieldidlist to check against field list
if (isset($fieldidlist)) {
    $fieldidlist = unserialize(base64_decode($fieldidlist));
}
if (isset($fieldnamelist)) {
    $fieldnamelist = unserialize(base64_decode($fieldnamelist));
}

$categories = field_category::get_for_context_level($context);
$categories = $categories ? $categories : array();

// divide the fields into categories
$fieldsbycategory = array();
foreach ($categories as $category) {
    $fieldsbycategory[$category->name] = array();
}
foreach ($fields as $field) {
    if (is_array($fieldidlist) && in_array($field->id,$fieldidlist)) {
        continue;
    }

    //make sure the current user can access this field in at least one
    //course context
    $owners = field_owner::get_for_field($field);
    if (!block_php_report_field_accessible($owners)) {
        continue;
    }

    $fieldsbycategory[$field->categoryname][] = $field;
}

print_header($site->shortname . ': '.get_string('selectcustomfields',$lang_file));

// show list of available fields
if (empty($fieldsbycategory)) {
    echo '<div>' . get_string('nofieldsfound', $lang_file) . '</div>';
} else {
    echo '<div>' . get_string('customfields', $lang_file) . '</div>';
    $table = null;
    $columns = array(
        'category'    => get_string('category', $lang_file),
        'name'        => get_string('name', $lang_file)
        );

    foreach ($columns as $column => $cdesc) {
        $$column        = $cdesc;
        $table->head[]  = $$column;
        $table->align[] = 'left';
    }

    // Setup table
    $table->width = "95%";
    $curr_category = '';
    foreach ($fieldsbycategory as $category => $fields) {
        $field_count = 0;
        foreach ($fields as $field) {
            $newarr = array();
            // Set up category name to display
            if ($field_count == 0) {
                $newarr[] = $category;
            } else {
                $newarr[] = '&nbsp;';
            }
            $field_count++;

            // Set up course field link to add course field to filter list
            // Custom field name, for now, also includes the category name
            // Exlucde the pretest and posttest because they are already in the report by default
            if ($field->shortname != "_elis_course_pretest" && $field->shortname != "_elis_course_posttest") {
                $newarr[] = '<a href="#" '.make_js_event($block_id,
                                                         $field->id,
                                                         $category.' - '.$field->name,
                                                         $fieldidlist,
                                                         $fieldnamelist)
                                          .' >'.$field->name.'</a>';
                $table->data[] = $newarr;
            }
        }
    }

    print_table($table);
}

?>
<div style="text-align: right"><a href="javascript:window.close()">Close window</a></div>
<?php

print_footer('empty');

/**
 * Generates an onclick event that
 * calls javascript to update the fieldtable with
 * a new custom field
 *
 * @param   string  $block_id       The block id uniquely identifies the field table
 * @param   string  $field_id       The custom field form element id
 * @param   string  $fieldname      The custom field display name (category + name)
 * @param   string  $fieldidlist    List of custom field ids currently included in report
 * @param   string  $fieldnamelist  List of custom field names currently included in report
 *
 * @return  string       HTML onclick event call
 */
function make_js_event($block_id, $field_id,$fieldname,$fieldidlist,$fieldnamelist) {
    global $CFG;

    // This link will call the updateTable javascript function to add a new field
    // It will pass the div or whatever to be updated and the path to the php to be called
    // Set add a field action
    $action = 'add';

    // Encode arrays
    $fieldidlist = urlencode(base64_encode(serialize($fieldidlist)));
    $fieldnamelist = urlencode(base64_encode(serialize($fieldnamelist)));
    $fieldname = urlencode(base64_encode(serialize($fieldname)));

    $js_event = ' onclick="customfields_updateTable(\''.$block_id.'\',\''.$action.'\',\''.
                $CFG->wwwroot.'/blocks/php_report/instances/individual_course_progress/\',\''.
                $field_id.'\',\''.$fieldname.'\',\''.$fieldidlist.'\',\''.$fieldnamelist.'\');return false;"';

    return $js_event;
}
?>