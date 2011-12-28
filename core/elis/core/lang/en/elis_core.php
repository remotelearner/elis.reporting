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

$string['allitemsselected'] = 'All items selected';
$string['data_object_construct_invalid_source'] = 'Attempted to construct a data_object from an invalid source';
$string['data_object_validation_not_empty'] = '{$a->tablename} record cannot have empty {$a->field} field';
$string['data_object_validation_unique'] = '{$a->tablename} record must have unique {$a->fields} fields';
$string['date'] = 'Date filter';
$string['date_help'] = '<h1>Date filter</h1>
<p>This filter allows you to filter information from before and/or after selected dates.</p>';
$string['done'] = 'done';
$string['elis'] = 'ELIS';
$string['elisversion'] = '<strong>ELIS Version:</strong> {$a}';
$string['finish'] = 'Finish';
$string['field_category'] = 'Category';
$string['field_name'] = 'Name';

// Default user profile field labels for userprofilematch filter - can override
$string['fld_auth'] = 'Authentication';
$string['fld_auth_help'] = '<h1>Authentication filter</h1>
<p>This filter allows you to filter users\' authentication method based on a drop down list of available authentication methods. This filter does not have any other options.</p>';
$string['fld_city'] = 'City/town';
$string['fld_city_help'] = '<h1>City filter</h1>
<p>This filter allows you to filter users\' city based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only cities that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only cities that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only cities that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only cities that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only cities that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only cities that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_confirmed'] = 'Confirmed';
$string['fld_confirmed_help'] = '<h1>Confirmed filter</h1>
<p>This filter allows you to filter users\' confirmed status based on a drop down list of: any value, yes or no. This filter does not have any other options.</p>';
$string['fld_country'] = 'Country';
$string['fld_country_help'] = '<h1>Country filter</h1>
<p>This filter allows you to filter users\' country based on a drop down list of countries. This filter does not have any other options.</p>';
$string['fld_coursecat'] = 'Course category';
$string['fld_courserole'] = 'Course role';
$string['fld_courserole_help'] = '<h1>Course role filter</h1>
<p>This filter allows you to filter users based the role they have assigned in the course
specified by its shortname from a specified course category (if the shortname textbox is empty,
the category is "any category" and the role is "any role" then the filter is not active).</p>';
$string['fld_email'] = 'Email address';
$string['fld_email_help'] = '<h1>Email address filter</h1>
<p>This filter allows you to filter users\' Email address based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only Email addresses that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only Email addresses that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only Email addresses that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only Email addresses that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only Email addresses that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only Email addresses that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_firstaccess'] = 'First access';
$string['fld_firstaccess_help'] = '<h1>First access filter</h1>
<p>This filter allows you to filter users\' firstaccess time from before and/or after selected dates. Where firstaccess time is the date and time the user first accessed the system.</p>';
$string['fld_firstname'] = 'First name';
$string['fld_firstname_help'] = '<h1>Firstname filter</h1>
<p>This filter allows you to filter users\' firstname based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only firstnames that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only firstnames that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only firstnames that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only firstnames that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only firstnames that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only firstnames that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_fullname'] = 'Full name';
$string['fld_fullname_help'] = '<h1>Fullname filter</h1>
<p>This filter allows you to filter users\' fullname based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only fullnames that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only fullnames that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only fullnames that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only fullnames that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only fullnames that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only fullnames that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_idnumber'] = 'ID number';
$string['fld_idnumber_help'] = '<h1>ID number filter</h1>
<p>This filter allows you to filter users\' ID number based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only ID numbers that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only ID numbers that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only ID numbers that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only ID numbers that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only ID numbers that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only ID numbers that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_lang'] = 'Preferred language';
$string['fld_lang_help'] = '<h1>Language filter</h1>
<p>This filter allows you to filter users\' preferred language based on a drop down list of available languages. This filter does not have any other options.</p>';
$string['fld_lastaccess'] = 'Last access';
$string['fld_lastaccess_help'] = '<h1>Last access filter</h1>
<p>This filter allows you to filter users\' lastaccess time from before and/or after selected dates. Where lastaccess time is the date and time the user last accessed the system.</p>';
$string['fld_lastlogin'] = 'Last login';
$string['fld_lastlogin_help'] = '<h1>Last login filter</h1>
<p>This filter allows you to filter users\' lastlogin time from before and/or after selected dates. Where lastlogin time is the date and time the user last logged into the system.</p>';
$string['fld_lastname'] = 'Last name';
$string['fld_lastname_help'] = '<h1>Lastname filter</h1>
<p>This filter allows you to filter users\' lastname based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only lastnames that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only lastnames that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only lastnames that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only lastnames that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only lastnames that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only lastnames that are equal to the empty string (the text entered is ignored)</li>
</ul>';
$string['fld_systemrole'] = 'System role';
$string['fld_systemrole_help'] = '<h1>Global role filter</h1>
<p>This filter allows you to filter users based the global role they have assigned.</p>';
$string['fld_timemodified'] = 'Last modified';
$string['fld_timemodified_help'] = '<h1>Last modified filter</h1>
<p>This filter allows you to filter on the last time the users\' profile was modified, from before and/or after selected dates. Where last modified is the date and time the users\' profile was last updated.</p>';
$string['fld_username'] = 'Username';
$string['fld_username_help'] = '<h1>Username filter</h1>
<p>This filter allows you to filter users\' username based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only usernames that contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only usernames that do not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only usernames that are equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only usernames that start with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only usernames that end with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only usernames that are equal to the empty string (the text entered is ignored)</li>
</ul>';

$string['invalidid'] = 'Invalid ID';
$string['invalidoperator'] = 'Invalid Operator';

$string['noactivities'] = 'No activities found';
$string['nocourseselected'] = 'No course selected';
$string['nofieldsselected'] = 'No fields selected';
$string['nogradeitems'] = 'No grade items found';
$string['noidnumber'] = 'No ID number';

$string['pluginname'] = 'ELIS Core';
$string['preup_ac_check'] = 'Checking for ELIS Alfresco configuration settings';
$string['preup_ac_error'] = 'error migrating configuration settings';
$string['preup_ac_found'] = 'found configuration settings';
$string['preup_ac_success'] = 'migrated configuration settings';
$string['preup_as_check'] = 'Checking if the Alfresco SSO auth plugin is enabled';
$string['preup_as_error'] = 'error switching auth plugin to ELIS Files SSO';
$string['preup_as_found'] = 'found enabled auth plugin';
$string['preup_as_success'] = 'migrated auth plugin settings';
$string['preup_dupfound'] = 'found duplicate records';
$string['preup_ec_check'] = 'Checking for ELIS Alfresco capabilities associated with role';
$string['preup_ec_error'] = 'error migrating capability settings';
$string['preup_ec_found'] = 'found capabilities associated with a role';
$string['preup_ec_success'] = 'migrated capability settings';
$string['preup_error_tablecreate'] = 'error creating grade_letters_temp table';
$string['preup_error_uniquecopy'] = 'error copying unique records';
$string['preup_gl_check'] = 'Checking for duplicate records in grade_letters table';
$string['preup_gl_success'] = 'removed duplicate grade_letters records';
$string['preup_up_check'] = 'Checking for duplicate records in user_preferences table';
$string['preup_up_success'] = 'removed duplicate user_preferences records';
$string['profilefield_help'] = '<h1>Profile filter</h1>
<p>This filter allows you to filter users based on values of profile fields.
The filter can be applied on a single profile field or an all profile fields.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only users for which the specified field contains the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only users for which the specified field does not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only users for which the specified field is equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only users for which the specified field starts with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only users for which the specified field ends with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only users for which the specified field is equal to an empty string, but it is defined (the text entered is ignored)</li>
<li>is not defined - this option allows only users for which the specified field is not defined (the text entered is ignored)</li>
<li>is defined - this option allows only users for which the specified field is defined (the text entered is ignored)</li>
</ul>';
$string['report_filter_all'] = 'Show All';
$string['report_filter_anyvalue'] = 'No filtering';
$string['unknown_action'] = 'Unknown action ({$a})';
$string['select'] = 'Select filter';
$string['select_help'] = '<h1>Select filter</h1>
<p>This filter allows you to filter information based on a drop down list.
The filter has the following options:</p>
<ul>
<li>is any value - this option disables the filter (i.e. all information is accepted by this filter)</li>
<li>is equal to - this option allows only information that is equal to the value selected from the list</li>
<li>is not equal to - this option allows only information that is different from the value selected from the list</li>
</ul>';
$string['set_nonexistent_member'] = 'Attempt to set nonexistent member variable {$a->classname}::${$a->name}';
$string['simpleselect'] = 'Simple select filter';
$string['simpleselect_help'] = '<h1>Simple select filter</h1>
<p>This filter allows you to filter information based on a drop down list. This filter does not have any extra options.</p>';
$string['subplugintype_eliscoreplugins_plural'] = 'General plugins';
$string['subplugintype_elisfields_plural'] = 'Custom field types';
$string['text'] = 'Text filter';
$string['text_help'] = '<h1>Text filter</h1>
<p>This filter allows you to filter information based on a free form text.
The filter has the following options:</p>
<ul>
<li>contains - this option allows only information that contains the text entered (if no text is entered, then the filter is disabled)</li>
<li>doesn\'t contain - this option allows only information that does not contain the text entered (if no text is entered, then the filter is disabled)</li>
<li>is equal to - this option allows only information that is equal to the text entered (if no text is entered, then the filter is disabled)</li>
<li>starts with - this option allows only information that starts with the text entered (if no text is entered, then the filter is disabled)</li>
<li>ends with - this option allows only information that ends with the text entered (if no text is entered, then the filter is disabled)</li>
<li>is empty - this option allows only information that is equal to an empty string (the text entered is ignored)</li>
</ul>';
$string['workflow_cancelled'] = 'Cancelled';
$string['workflow_invalidstep'] = 'Invalid step specified';
$string['write_to_non_overlay_table'] = 'Attempted write to a non-overlay table: {$a}';

