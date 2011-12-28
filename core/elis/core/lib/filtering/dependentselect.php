<?php //$Id$
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
 * @author     Remote-Learner.net Inc
 * @author     Brent Boghosian <brent.boghosian@remote-learner.net>
 * @version    $Id$
 * @package    elis-core
 * @subpackage filtering
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/elis/core/lib/filtering/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_dependentselect extends generalized_filter_type {

    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    var $_default;

    var $_numeric;

    var $_report_path;

    var $_isrequired;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     * @param mixed $default option
     */
    function generalized_filter_dependentselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        global $CFG;

        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                                        !empty($options['help'])
                                        ? $options['help']
                                        : array('simpleselect', $label, 'elis_core'));
        $this->_field   = $field;

        $choices = $options['choices'];

        $this->_options = $choices;
        $this->_default = $options['default'];
        $this->_numeric = $options['numeric'];
        $this->_report_path = $options['report_path'];
        $this->_isrequired = !empty($options['isrequired']) ? $options['isrequired'] : false;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     * @uses  $PAGE
     */
    function setupForm(&$mform) {
        global $PAGE;
        $PAGE->requires->yui2_lib(array('yahoo', 'dom', 'event',
                                        'connection', 'json'));
        $PAGE->requires->js('/elis/core/js/dependentselect.js');

        $options_array = $this->get_main_options();

        $js = "dependentselect_updateoptions('{$this->_uniqueid}','{$this->_report_path}');";

        $objs = array();
        $objs[] =& $mform->createElement('select', $this->_uniqueid.'_parent', null, $options_array,
                                         array('onChange' => $js));
        $objs[] =& $mform->createElement('select', $this->_uniqueid, null, $this->_options);
        $grp =& $mform->addElement('group', $this->_uniqueid.'_grp', $this->_label, $objs, '<br/>', false);

        $mform->addHelpButton($this->_uniqueid.'_grp', $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */); // TBV

        if (!is_null($this->_default)) {
            $mform->setDefault($this->_uniqueid, $this->_default);
        }
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid.'_grp');
        }

        // Always refresh the child pulldown
        $mform->addElement('html','<script>'.$js.'</script>');

    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_uniqueid;

        if (array_key_exists($field, $formdata)) {
            return array('value' => (string)$formdata->$field);
        }

        return false;
    }

    function get_report_parameters($data) {
        $return_value = array('value'   => $data['value'],
                              'numeric' => $this->_numeric);
        return $return_value;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array      the filtering condition with optional params or
                          null if the filter is disabled
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $param_name = 'ex_dependselect'. $counter++;
        $value = addslashes($data['value']);
        return array("{$full_fieldname} = :{$param_name}",
                     array($param_name => $value));
    }

    /**
     * Override this method to return the main pulldown option
     * @return array List of options keyed on id
     */
    function get_main_options() {
        return array('0' => 'Select...');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        if (!empty($this->_options)) {
            foreach ($this->_options as $key => $value) {
                if ($key == $data['value']) {
                    return "{$this->_label}: {$value}";
                }
            }
            return "{$this->_label}: {$data['value']}";
        }
        return "{$this->_label}: ". get_string('off'); // TBD: 'none'
    }

}

