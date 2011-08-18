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
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//needed for moodleform definition
require_once($CFG->libdir . '/formslib.php');

class parameter_form extends moodleform {
    //stores the identifiers of elements added so far to prevent
    //double-adding elements if they appear in multiple filterings
    var $uniqueids = array();

    /**
     * Standard form definition
     */
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'newfilter', get_string('newfilter','filters'));

        //just define the id field - UI fields added in definition_after_data
        $mform->addElement('hidden', 'id');
    }

    /**
     * Adds conditional fields to the form
     */
    function definition_after_data() {
        global $SESSION;

        $mform =& $this->_form;

        //used to persist the display of the cancel button when re-displaying
        $mform->addElement('hidden', 'showcancel');

        if (isset($this->_customdata['filterobject'])) {
            //filter object was passed, because the report uses filters
            $filter_object = $this->_customdata['filterobject'];

            //handle adding of UI fields for secondary filterings
            if (!empty($filter_object->secondary_filterings)) {
                foreach ($filter_object->secondary_filterings as $key => $filtering) {
                    $this->add_filtering_elements($filtering);
                }
            }
            //add all filter form elements to this form for the main filtering
            $this->add_filtering_elements($filter_object);

            //add any required field rules
            foreach ($filter_object->_fields as $key=>$fields) {
                if (!empty($fields->_isrequired)) {
                    $required_rule_method = $filter_object->reportname . '_report::apply_filter_required_rule';

                    if (is_callable($required_rule_method)) {
                        // We have a custom requirement rule so let's use it
                        $mform = call_user_func_array($required_rule_method, array($mform,$key,$fields));
                    } elseif ($mform->elementExists($key)) {
                        // Basic requirement rule
                        $mform->addRule($key, get_string('required'), 'required', null, 'client');
                    }
                }
            }

            //add the necessary buttons
            $elements = array();

            $elements[] = $mform->createElement('submit', 'reset_form', get_string('button_reset_form', 'block_php_report'));
            $elements[] = $mform->createElement('submit', 'save_defaults', get_string('button_save_defaults', 'block_php_report'));

            //determine whether to display the cancel button (not shown on first view of this form)
            if (!empty($this->_customdata['showcancel'])) {
                $elements[] = $mform->createElement('cancel', 'canceltest', get_string('button_cancel', 'block_php_report'));
            }

            $elements[] = $mform->createElement('submit', 'show_report', get_string('button_show_report', 'block_php_report'));
            $mform->addGroup($elements, 'buttonar', '', array(' '), false);
        } else {
            //report does not use filters
            $mform->addElement('static', 'noparams', '', get_string('label_no_parameters', 'block_php_report'));
            $mform->addElement('submit', 'show_report', get_string('button_show_report', 'block_php_report'));
        }
        $mform->addElement('html',"
<script type=\"text/javascript\">
//<![CDATA[
if ((reportdiv = document.getElementById('php_report_block')) &&
    (reportforms = reportdiv.getElementsByTagName('form')) )
{
    for (i = 0; i < reportforms.length; ++i) {
        reportforms[i].onsubmit = function() { start_throbber(); return true; };
    }
}
//]]>
</script>
");

    }

    /**
     * Adds UI fields to this form based on the fields as defined in the
     * provided filtering
     *
     * @param  php_report_default_capable_filtering  $filter_object  The filtering definition
     */
    protected function add_filtering_elements($filter_object) {
        $mform =& $this->_form;
        //iterate through the defined fields
        if (!empty($filter_object->_fields)) {
            foreach ($filter_object->_fields as $field) {
                //add fields this form if they do not already exist
                if (!in_array($field->_uniqueid, $this->uniqueids)) {
                    $field->setupForm($mform);
                    $this->uniqueids[] = $field->_uniqueid;
                }
            }
        }
    }
}

?>
