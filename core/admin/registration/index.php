<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    moodle
 * @subpackage registration
 * @author     Jerome Mouneyrac <jerome@mouneyrac.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * On this page the administrator select if he wants to register on Moodle.org or
 * a specific hub
 */

require('../../config.php');

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/forms.php');
require_once($CFG->dirroot . '/course/publish/lib.php');
require_once($CFG->dirroot . "/webservice/xmlrpc/lib.php");

admin_externalpage_setup('registrationindex');

$renderer = $PAGE->get_renderer('core', 'register');

$unregistration = optional_param('unregistration', 0, PARAM_INT);
$cleanregdata = optional_param('cleanregdata', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_INT);
$huburl = optional_param('huburl', '', PARAM_URL);
$cancel = optional_param('cancel', null, PARAM_ALPHA);

$registrationmanager = new registration_manager();
$publicationmanager = new course_publish_manager();
$errormessage = '';
if (empty($cancel) and $unregistration and $confirm and confirm_sesskey()) {

    $hub = $registrationmanager->get_registeredhub($huburl);

    //unpublish course and unregister the site by web service
    if (!$cleanregdata) {

        //check if we need to unpublish courses
        //enrollable courses
        $unpublishalladvertisedcourses = optional_param('unpublishalladvertisedcourses', 0, PARAM_INT);
        $hubcourseids = array();
        if ($unpublishalladvertisedcourses) {
            $enrollablecourses = $publicationmanager->get_publications($huburl, null, 1);
            if (!empty($enrollablecourses)) {
                foreach ($enrollablecourses as $enrollablecourse) {
                    $hubcourseids[] = $enrollablecourse->hubcourseid;
                }
            }
        }
        //downloadable courses
        $unpublishalluploadedcourses = optional_param('unpublishalluploadedcourses', 0, PARAM_INT);
        if ($unpublishalluploadedcourses) {
            $downloadablecourses = $publicationmanager->get_publications($huburl, null, 0);
            if (!empty($downloadablecourses)) {
                foreach ($downloadablecourses as $downloadablecourse) {
                    $hubcourseids[] = $downloadablecourse->hubcourseid;
                }
            }
        }

        //unpublish the courses by web service
        if (!empty($hubcourseids)) {
            $function = 'hub_unregister_courses';
            $params = array('courseids' => $hubcourseids);
            $serverurl = $huburl . "/local/hub/webservice/webservices.php";
            $xmlrpcclient = new webservice_xmlrpc_client($serverurl, $hub->token);
            try {
                $result = $xmlrpcclient->call($function, $params);
                //delete the published courses
                if (!empty($enrollablecourses)) {
                    $publicationmanager->delete_hub_publications($huburl, 1);
                }
                if (!empty($downloadablecourses)) {
                    $publicationmanager->delete_hub_publications($huburl, 0);
                }
            } catch (Exception $e) {
                $errormessage = $e->getMessage();
                $errormessage .= html_writer::empty_tag('br') .
                        get_string('errorunpublishcourses', 'hub');
                $confirm = false;
                $cleanregdata = 1;
            }
        }
    }

    //course unpublish went ok, unregister the site now
    if ($confirm) {
        $function = 'hub_unregister_site';
        $params = array();
        $serverurl = $huburl . "/local/hub/webservice/webservices.php";
        $xmlrpcclient = new webservice_xmlrpc_client($serverurl, $hub->token);
        try {
            $result = $xmlrpcclient->call($function, $params);
        } catch (Exception $e) {
            if (!$cleanregdata) {
                $errormessage = $e->getMessage();
                $confirm = false;
                $cleanregdata = 1;
            }
        }
    }

    //check that we are still processing the unregistration,
    //it could have been unset if an exception were previsouly catched
    if ($confirm) {
        $registrationmanager->delete_registeredhub($huburl);
    }
}

echo $OUTPUT->header();

//do not check sesskey if confirm = false because this script is linked into email message
if (!empty($errormessage)) {
    echo $OUTPUT->notification(get_string('unregistrationerror', 'hub', $errormessage));
}
if (empty($cancel) and $unregistration and !$confirm) {
    $hub = $registrationmanager->get_registeredhub($huburl);
    echo $OUTPUT->heading(get_string('unregisterfrom', 'hub', $hub->hubname), 3, 'main');
    if ($cleanregdata) {
        $siteunregistrationform = new site_clean_registration_data_form('',
                        array('huburl' => $huburl, 'hubname' => $hub->hubname));
    } else {
        $siteunregistrationform = new site_unregistration_form('',
                        array('huburl' => $huburl, 'hubname' => $hub->hubname));
    }
    $siteunregistrationform->display();
} else {
    $registeredonmoodleorg = false;
    $moodleorghub = $registrationmanager->get_registeredhub(HUB_MOODLEORGHUBURL);
    if (!empty($moodleorghub)) {
        $registeredonmoodleorg = true;
    }

    echo $OUTPUT->heading(get_string('registeron', 'hub'), 3, 'main');
    echo $renderer->registrationselector($registeredonmoodleorg);

    if (extension_loaded('xmlrpc')) {
        $hubs = $registrationmanager->get_registered_on_hubs();
        if (!empty($hubs)) {
            echo $OUTPUT->heading(get_string('registeredon', 'hub'), 3, 'main');
            echo $renderer->registeredonhublisting($hubs);
        }
    } else { //display notice about xmlrpc
        $xmlrpcnotification = $OUTPUT->doc_link('admin/environment/php_extension/xmlrpc', '');
        $xmlrpcnotification .= get_string('xmlrpcdisabledregistration', 'hub');
        echo $OUTPUT->notification($xmlrpcnotification);
    }
}
echo $OUTPUT->footer();
