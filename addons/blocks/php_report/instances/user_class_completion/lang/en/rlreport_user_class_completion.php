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
 * @package    php_report
 * @subpackage user_class_completion
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

$string['displayname'] = 'User Class Completion Report';

$string['address'] = 'Address';
$string['certificatenumber'] = 'Certificate Number';
$string['city'] = 'City/Town';
$string['classfields'] = 'Class Fields';
$string['classrole'] = 'Class Assignment';
$string['cluster_help'] = 'Userset Help';
$string['column_address'] = 'Street Address';
$string['column_certificatenum'] = 'Certificate #';
$string['column_city'] = 'City';
$string['column_curriculumname'] = 'Program';
$string['column_details'] = 'Details';
$string['column_email'] = 'E-mail Address';
$string['column_idnumber'] = 'ID Number';
$string['column_numcredits'] = 'Total Course Credits Earned';
$string['column_state'] = 'State/Province';
$string['column_timecompleted'] = 'Program Completion Date (MM/DD/YYYY)';
$string['column_timeexpired'] = 'Program Expiration Date';
$string['column_user_name'] = 'Name';
$string['completed_range'] = 'Course Completion Date';
$string['completionstatus_options_heading'] = 'Class Completion status';
$string['coursefields'] = 'Course Fields';
$string['curriculum'] = 'Program';
$string['curriculumcompletiondate'] = 'Program Completion Date';
$string['curriculumexpirationdate'] = 'Program Expiration Date';
$string['curriculumfields'] = 'Program Fields';
$string['customfield_date_format'] = '%d %B %Y';
$string['customfield_datetime_format'] = '%d %B %Y, %I:%M %p';
$string['date_format'] = '%m/%d/%Y';
$string['detail_col_fields'] = 'Details Report Data Fields';
$string['detail_columns'] = 'Optional Details Report Data Fields';
$string['detail_head_fields'] = 'Details Report Header Fields';
$string['detail_headers'] = 'Optional Details Report Header Fields';
$string['details'] = 'Details';
$string['display'] = 'Display';
$string['email'] = 'Email';
$string['enable_dropdown'] = 'Enable Dropdown';
$string['enable_tree'] = 'Enable Tree';
$string['filter_autocomplete_idnumber'] = 'IDNumber';
$string['filter_autocomplete_firstname'] = 'First Name';
$string['filter_autocomplete_lastname'] = 'Last Name';
$string['filter_cluster'] = 'Userset';
$string['filter_clusterrole'] = 'Userset role';
$string['filter_completionstatus'] = 'Class Completion Status';
$string['filter_curriculumclass'] = ''; // TBD
$string['filter_user_match'] = 'User Profile';
$string['fld_curriculum'] = 'Program';
$string['grouping_credits'] = 'Total Earned Credits: ';
$string['grouping_learners'] = 'Total Learners: ';
$string['grouping_learners_csv'] = 'Total Learners / Total Earned Credits';
$string['na'] = 'N/A';
$string['noncurriculumcourses'] = 'Non-Program Courses';
$string['otherfields'] = 'Other Fields';
$string['past'] = 'Past';
$string['present'] = 'Present';
$string['report_field_completion'] = 'Completion';
$string['report_field_curriculum'] = 'Program';
$string['report_field_status'] = 'Status';
$string['report_fields_heading'] = 'Report fields';
$string['report_title'] = 'Report Title Help';
$string['show_completed_classes'] = 'Show completed classes';
$string['show_incomplete_classes'] = 'Show incomplete classes';
$string['state'] = 'State';
$string['status_failed'] = 'Failed';
$string['status_notcomplete'] = 'In Progress';
$string['status_notstarted'] = 'Not Started';
$string['status_passed'] = 'Passed';
$string['summary_columns'] = 'Optional Summary Report Data Fields';
$string['summary_report_fields'] = 'Summary Report Fields';
$string['title'] = 'Report Title';
$string['userfields'] = 'User Fields';

$string['user_class_completion_city'] = 'City';
$string['user_class_completion_city_help'] = '<p>This field represents the city of users to be included in this report.</p>';

$string['user_class_completion_class'] = 'Class Instance';
$string['user_class_completion_class_help'] = '<p>This field represents the class instance of users to be included in this report.</p>';

$string['user_class_completion_cluster'] = 'User Set';
$string['user_class_completion_cluster_help'] = '<p>Single user set selection: Use the drop-down and select a user set.</p>
<p>Multiple user set selection: Click Enable Tree and...</p>
<p>To add a user set, check the box beside it.</p>
<p>If there are any child user sets, selecting a parent user set will select all the child user sets.</p>
<p>To unselect a child user set, expand the parent user set\'s tree and uncheck the child user set.</p>';

$string['user_class_completion_completionstatus'] = 'Completion Status';
$string['user_class_completion_completionstatus_help'] = '<p>This field only allows classes which match the selected value in the report.</p>
<p>There are four values for this field:</p>
<ol>
<li>Passed: The user has passed the class.</li>
<li>Failed: The user has failed the class.</li>
<li>In Progress: The user has logged into the class or completed one or more course elements*.</li>
<li>Not Started: The user has never logged into the class and has not completed any course elements*.</li>
</ol>
<p>* Course elements may be marked as completed by course instructors.</p>';

$string['user_class_completion_country'] = 'Country';
$string['user_class_completion_country_help'] = '<p>This field represents the country of users to be included in this report.</p>';

$string['user_class_completion_course'] = 'Course Description';
$string['user_class_completion_course_help'] = '<p>This field represents the course description of users to be included in this report.</p>';

$string['user_class_completion_curriculum'] = 'Program';
$string['user_class_completion_curriculum_help'] = '<p>This field represents the program of users to be included in this report.</p>';

$string['user_class_completion_detaildatafields'] = 'Detail Data Fields';
$string['user_class_completion_detaildatafields_help'] = '<p>This field represents the detail data fields of users to be included in this report.</p>';

$string['user_class_completion_detailheaderfields'] = 'Detail Header Fields';
$string['user_class_completion_detailheaderfields_help'] = '<p>This field represents the detail header fields of users to be included in this report.</p>';

$string['user_class_completion_email'] = 'Email';
$string['user_class_completion_email_help'] = '<p>This field represents the email address of users to be included in this report.</p>';

$string['user_class_completion_environment'] = 'Environment';
$string['user_class_completion_environment_help'] = '<p>This field represents the environment of users to be included in this report.</p>';

$string['user_class_completion_firstname'] = 'First name';
$string['user_class_completion_firstname_help'] = '<p>This field represents the firstname of users to be included in this report.</p>';

$string['user_class_completion_fullname'] = 'Full name';
$string['user_class_completion_fullname_help'] = '<p>This field represents the full name of users to be included in this report, in "Firstname Lastname" format.</p>';

$string['user_class_completion_idnumber'] = 'ID number';
$string['user_class_completion_idnumber_help'] = '<p>This field represents the ID number of users to be included in this report.</p>';

$string['user_class_completion_inactive'] = 'Inactive';
$string['user_class_completion_inactive_help'] = '<p>Select "No" if you want the report to show only active users, "Yes" if you want the report to show only inactive users, and, select "any value" if you want the report to show both ative & inactive users.</p>';

$string['user_class_completion_language'] = 'Language';
$string['user_class_completion_language_help'] = '<p>This field represents the primary language of users to be included in this report.</p>';

$string['user_class_completion_lastname'] = 'Last name';
$string['user_class_completion_lastname_help'] = '<p>This field represents the lastname of users to be included in this report.</p>';

$string['user_class_completion_reporttitle'] = 'Report Title';
$string['user_class_completion_reporttitle_help'] = '<p>The text to be displayed in the title bar that spans the top of the report.</p>
<p>There are some special codes which can be used to dynamically put content in the report:</p>
<ol>
<li>%%site%% - The site name</li>
<li>%%startdate%% - The start day for the report</li>
<li>%%enddate%% - The end day for the report</li>
</ol>';

$string['user_class_completion_startdate'] = 'Start date';
$string['user_class_completion_startdate_help'] = '<p>This field represents the start date of the user\'s class. Only classes that match this setting will be displayed in the report.</p>';

$string['user_class_completion_summarydatafields'] = 'Summary';
$string['user_class_completion_summarydatafields_help'] = '<p>This field represents the summary data fields of users to be included in this report.</p>';

$string['user_class_completion_username'] = 'Username';
$string['user_class_completion_username_help'] = '<p>This field represents the username of users to be included in this report.</p>';

