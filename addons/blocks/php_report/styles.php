/*
 Show / hide functionality
 */

.block_php_report .content .subTitle {
  font-size:1.0em;
  font-weight: bolder;
}

.block_php_report .show-hide-report-image {
  float:left;
  height:11px;
  width:11px;
  margin-top:0.25em;
}

.block_php_report .rollup-report {
  background:url(../../pix/t/switch_minus.gif) no-repeat;
  cursor:pointer;
  width: 11x;
  height: 11px;
  border: none;
  margin-top:0.25em;

}

.block_php_report .rolldown-report {
  background:url(../../pix/t/switch_plus.gif) no-repeat;
  cursor:pointer;
  width: 11px;
  height: 11px;
  border: none;
  margin-top:0.25em;
}

/*
 General report heading classes
 */

.php_report_block .php_report_config_header {
  clear: both;
}

.php_report_block .php_report_config_header .php_report_schedule_this_link {
  float: right;
}

.php_report_block .php_report_export_formats {
  text-align: right;
  font-weight: bold;
}

.php_report_block .php_report_header_entries {
  width:100%;
  float: left;
}

.php_report_block .php_report_header_label {
  width: 30%;
  float: left;
  font-weight: bold;
  clear: both;
}

.php_report_block .php_report_header_value {
  width: 70%;
  float: left;
}

/*
 General report classes
 */

.php_report_table tr td {
   padding: 5px;
   margin: 5px;
   border: 0;
 }

.php_report_table tr .column_header,
.php_report_table th {
  background-image: none;
  background-color: #8DB3E2;
  padding: 5px;
}

/* This will make all the backgrounds white VERY important to keep! */
.php_report_table .r0 {
  background-image: none;
  background-color: white;
}

.php_report_table .r1 {
  background-image: none;
  background-color: #F2F2F2;
}

.php_report_table .group0 {
  background-image: none;
  background-color: #FFFFFF;
}
.php_report_table .group1 {
  background-image: none;
  background-color: #FFFFFF;
}
.php_report_table .group2 {
  background-image: none;
  background-color: #FFFFFF;
}
.php_report_table .group3 {
  background-image: none;
  background-color: #FFFFFF;
}
.php_report_table .group4 {
  background-image: none;
  background-color: #FFFFFF;
}

.php_report_body {
  background-image: none;
  background-color: white;
}

.php_report_block .php_report_title {
  background-color: #DBE5F1;
  padding: .5%;
  width: 99%;
  font-weight: bold;
}

.php_report_table tr .column_header a:link,
.php_report_table tr .column_header a:visited,
.php_report_table tr .column_header a:hover,
.php_report_table tr .column_header a:active,
.php_report_table th a:link,
.php_report_table th a:visited,
.php_report_table th a:hover,
.php_report_table th a:active {
  color: #000000;
}
.php_report_block  tr .php_report_table_row {
  padding: 5px;
}

.php_report_block .php_report_group_summary {
  background-color: #17365D;
  color: #FFFFFF;
  padding: 5px;
}
.php_report_block .php_report_column_summary {
  background-color: #FFFFFF;
  padding: 5px;
}
/*
 Tabular-specific styling
 */

.php_report_block .rlreport_summary_field_displayname {
  font-weight: bold;
}

.php_report_block .rlreport_summary_field_value {
  font-weight: bold;
}

/*
 Report Instance styling
 */

/*
 Curricula styling
 */

.php_report_block .curricula .php_report_title {
  background-color: #B8CCE4;
}

 .curricula .php_report_table tr .column_header,
 .curricula .php_report_table th {
  background-color: #A9F5AD;
}

/*
 Gas gauge-specific styling
 */

.php_report_block .gas_gauge_table_report {
  text-align: center;
}

.php_report_block .php_report_gas_gauge_header_entry {
  font-size: 1.3em;
}

.php_report_block .gas_gauge_table_report .php_report_config_header {
  text-align: left;
}

.php_report_block .gas_gauge_table_report .php_report_title {
  background-color: #FFFFFF;
}

.gas_gauge_table_report tr .column_header,
.gas_gauge_table_report th {
  background-color: #F6F5F5;
}

.gas_gauge_table_report .php_report_table .r1 {
  background-color: #FFFFFF;
}

.gas_gauge_table_report .php_report_column_summary tr,
.gas_gauge_table_report .php_report_column_summary td.cell {
  border-top: 2px solid #000000;
}


/*
 Course Completion By Cluster styling
 */

.php_report_block .course_completion_by_cluster .php_report_title {
  background-color: #DBE5F1;
}

.course_completion_by_cluster .php_report_table tr .column_header,
.course_completion_by_cluster .php_report_table th {
  background-color: #A9F5AD;
}

.course_completion_by_cluster .php_report_table .group0 {
  background-color: #DBE5F1;
}
.course_completion_by_cluster .php_report_table .group1 {
  background-color: #B4BBEE;
}
.course_completion_by_cluster .php_report_table .group2 tr,
.course_completion_by_cluster .php_report_table .group2 td {
  border-bottom: 2px solid #000000;
}

/*
 Sitewide Course Completion styling
 */

.php_report_block .sitewide_course_completion .php_report_title {
  background-color: #DBE5F1;
}

.sitewide_course_completion .php_report_table tr .column_header,
.sitewide_course_completion .php_report_table th {
  background-color: #A9F5AD;
}

.sitewide_course_completion .php_report_table .group0 {
  background-color: #548DD4;
}
.sitewide_course_completion .php_report_table .group1 {
  background-color: #8DB3E2;
}
.sitewide_course_completion .php_report_table .group2 {
  background-color: #C6D9F1;
}

/*
 Course Usage Summary styling
 */

.php_report_block .course_usage_summary .php_report_header_entries {
  background-color: #CCCCCC;
}

.course_usage_summary .php_report_icon_top_level_div {
  border: 2px solid #000000;
}
/*
 Course Progress Summary styling
 */

.php_report_block .course_progress_summary .php_report_table {
  background-color: #DBE5F1;
}

.php_report_block .course_progress_summary .php_report_header_entries {
  background-color: #F2F2F2;
}

.course_progress_summary .php_report_table tr .column_header,
.course_progress_summary .php_report_table th {
  background-color: #D9D9D9;
}

.course_progress_summary .php_report_table .r0 {
  background-color: #F2F2F2;
}
.course_progress_summary .php_report_table .r1 {
  background-color: #F2F2F2;
}
.course_progress_summary .php_report_table tr,
.course_progress_summary .php_report_table th,
.course_progress_summary .php_report_table td {
  border: 2px solid #000000;
}
/*
 Class Roster styling
 */

.php_report_block .class_roster .php_report_table {
  background-color: #DBE5F1;
}

.php_report_block .class_roster .php_report_header_entries {
  background-color: #F2F2F2;
}

.class_roster .php_report_table tr .column_header,
.class_roster .php_report_table th {
  background-color: #D9D9D9;
}

.class_roster .php_report_table .r0 {
  background-color: #F2F2F2;
}
.class_roster .php_report_table .r1 {
  background-color: #F2F2F2;
}

/*
 Individual User styling
 */

.php_report_block .individual_user .php_report_table {
  background-color: #DBE5F1;
}

.php_report_block .individual_user .php_report_header_entries {
  background-color: #F2F2F2;
}

.individual_user .php_report_table tr .column_header,
.individual_user .php_report_table th {
  background-color: #D9D9D9;
}

.individual_user .php_report_table tr.r0,
.individual_user .php_report_table th.r0,
.individual_user .php_report_table td.cell,
.individual_user .php_report_table tr.r1,
.individual_user .php_report_table th.r1,
.individual_user .php_report_table th.c0,
.individual_user .php_report_table th.c1{
  border: 2px solid #000000;
}
.individual_user .php_report_table .r0 {
  background-color: #F2F2F2;
}
.individual_user .php_report_table .r1 {
  background-color: #F2F2F2;
}

.individual_user .group0 {
  background-color: #404040;
  color: #FFFFFF;
}


.individual_user .php_report_table .php_report_group_summary tr,
.individual_user .php_report_table .php_report_group_summary td.cell {
  background-color: #17365D;
}

/*
 Individual Course Progress styling
 */

.php_report_block .individual_course_progress .php_report_table {
  background-color: #DBE5F1;
}

.php_report_block .individual_course_progress .php_report_header_entries {
  background-color: #F2F2F2;
}

.individual_course_progress .php_report_table tr .column_header,
.individual_course_progress .php_report_table th {
  background-color: #D9D9D9;
}

.individual_course_progress .php_report_table .r0 {
  background-color: #F2F2F2;
}
.individual_course_progress .php_report_table .r1 {
  background-color: #F2F2F2;
}

.individual_course_progress .group0 {
  background-color: #B6DDE8;
}

/*
 New Registrants by student styling
 */

.php_report_block .registrants_by_student .php_report_header_entries {
  background-color: #DBE5F1;
}

.registrants_by_student .php_report_table tr .column_header,
.registrants_by_student .php_report_table th {
  background-color: #A9F5AD;
}

.registrants_by_student .group0 {
  background-color: #8DB3E2;
}
.registrants_by_student .group1 {
  background-color: #C6D9F1;
}
.registrants_by_student .php_report_table tr.php_report_table_row,
.registrants_by_student .php_report_table th.c0,
.registrants_by_student .php_report_table th.c1,
.registrants_by_student .php_report_table th.c2,
.registrants_by_student .php_report_table td.cell,
.registrants_by_student .php_report_table tr.group0 td,
.registrants_by_student .php_report_table tr.group1 td {
  border: 2px solid #000000;
}
/*
 New Registrants by course styling
 */

.php_report_block .registrants_by_course .php_report_header_entries {
  background-color: #DBE5F1;
}

.registrants_by_course .php_report_table tr .column_header,
.registrants_by_course .php_report_table th {
  background-color: #A9F5AD;
}

.registrants_by_course .php_report_table .group0 {
  background-color: #FFFFFF;
}
.registrants_by_course .php_report_table .group1 {
  background-color: #8DB3E2;
}
.registrants_by_course .php_report_table .group2 {
  background-color: #C6D9F1;
}
.registrants_by_course .php_report_table tr,
.registrants_by_course .php_report_table th,
.registrants_by_course .php_report_table td {
  border: 2px solid #000000;
}

/*
 Non-starter styling
 */

.php_report_block .nonstarter .php_report_header_entries {
  background-color: #DBE5F1;
}

.nonstarter .php_report_table tr .column_header,
.nonstarter .php_report_table th {
  background-color: #A9F5AD;
}

.nonstarter .php_report_table .group0 {
  background-color: #FFFFFF;
}
.nonstarter .php_report_table .group1 {
  background-color: #8DB3E2;
}
.nonstarter .php_report_table .group2 {
  background-color: #C6D9F1;
}
.nonstarter .php_report_table tr,
.nonstarter .php_report_table th,
.nonstarter .php_report_table td {
  border: 2px solid #000000;
}

/*
 Sitewide Transcript styling
 */

.php_report_block .sitewide_transcript .php_report_header_entries {
  background-color: #DBE5F1;
}

.sitewide_transcript .php_report_table tr .column_header,
.sitewide_transcript .php_report_table th {
  background-color: #A9F5AD;
}

.sitewide_transcript .php_report_table .group0 {
  background-color: #D9D9D9;
}
.sitewide_transcript .php_report_table .group1 {
  background-color: #FFFFFF;
}
.sitewide_transcript .php_report_table .r0 {
  background-color: #FFFFFF;
}
.sitewide_transcript .php_report_table .r1 {
  background-color: #FFFFFF;
}
.sitewide_transcript .php_report_table tr.php_report_table_row,
.sitewide_transcript .php_report_table th.c0,
.sitewide_transcript .php_report_table th.c1,
.sitewide_transcript .php_report_table th.c2,
.sitewide_transcript .php_report_table td.cell,
.sitewide_transcript .php_report_table tr.group0 td,
.sitewide_transcript .php_report_table tr.group1 td {
  border: 2px solid #000000;
}

/*
 Sitewide Time Summary styling
 */

.php_report_block .sitewide_time_summary .php_report_header_entries,
.php_report_block .sitewide_time_summary .php_report_header_entries .php_report_header_label,
.php_report_block .sitewide_time_summary .php_report_header_entries .php_report_header_value {
  background-color: #DBE5F1;
}

.sitewide_time_summary .php_report_table tr .column_header,
.sitewide_time_summary .php_report_table th {
  background-color: #A9F5AD;
}

.sitewide_time_summary .php_report_table .group0 {
  background-color: #D9D9D9;
}
.sitewide_time_summary .php_report_table .group1 {
  background-color: #FFFFFF;
}
.sitewide_time_summary .php_report_table .group2 {
  background-color: #17365D;
  color: #FFFFFF;
}
.sitewide_time_summary .php_report_table .r0 {
  background-color: #FFFFFF;
}
.sitewide_time_summary .php_report_table .r1 {
  background-color: #FFFFFF;
}
.sitewide_time_summary .php_report_table .php_report_group_summary tr,
.sitewide_time_summary .php_report_table .php_report_group_summary td.cell {
  background-color: #FFFFFF;
  color: #000000;
}
.sitewide_time_summary .php_report_table tr,
.sitewide_time_summary .php_report_table th,
.sitewide_time_summary .php_report_table td {
  border: 2px solid #000000;
}

/*
 Styling for the scheduling interface
 */

 .php_report_scheduling_list_category {
   font-weight: bold;
   font-style: italic;
 }

 .php_report_bold_header {
   font-weight: bold;
 }

 .php_report_italic_header {
   font-style: italic;
 }

 .php_report_item_header {
   display: block;
   float: left;
   margin: 5px 0 0 5px;
   padding: 0;
   text-align: left;
   width: 80%;
 }
/*
 Styling within the "run jobs now" scheduling popup (based on lesson CSS)
 */

.php_report_schedule_progress_bar {
    padding: 20px;
}

.php_report_schedule_progress_bar_table {
    width: 80%;
    padding: 0px;
    margin: 0px;
}

.php_report_schedule_progress_bar_completed {
    background-color: green;
    padding: 0px;
    margin: 0px;
}

.php_report_schedule_progress_bar_todo {
    background-color: red;
    text-align: left;
    padding: 0px;
    margin: 0px;
}

.php_report_schedule_progress_bar_token {
    background-color: #000000;
    height: 20px;
    width: 5px;
    padding: 0px;
    margin: 0px;
}
