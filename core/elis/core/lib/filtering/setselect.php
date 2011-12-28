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
 * @package    elis-core
 * @subpackage filtering
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2011 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/elis/core/lib/filtering/lib.php');
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Generic filter based on a list of values.
 */
class generalized_filter_setselect extends generalized_filter_type {
    /**
     * options for the list values
     */
    var $_options;

    var $_field;

    var $_numeric;

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     * @param array $options select options
     */
    function generalized_filter_setselect($uniqueid, $alias, $name, $label, $advanced, $field, $options = array()) {
        parent::generalized_filter_type($uniqueid, $alias, $name, $label, $advanced,
                                        !empty($options['help'])
                                        ? $options['help']
                                        : array('simpleselect', $label, 'elis_core'));
        $this->_field   = $field;
        $this->_options = $options['choices'];
        $this->_numeric = $options['numeric'];
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        $choices = array();
        foreach ($this->_options as $key => $value) {
            $choices[serialize($value)] = $key;
        }

        $choices = array('' => get_string('anyvalue', 'filters')) + $choices;

        $mform->addElement('select', $this->_uniqueid, $this->_label, $choices);
        $mform->addHelpButton($this->_uniqueid, $this->_filterhelp[0], $this->_filterhelp[2] /* , $this->_filterhelp[1] */);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_uniqueid);
        }
    }

     /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field = $this->_uniqueid;

        if (array_key_exists($field, $formdata) and $formdata->$field !== '') {
            $value = unserialize($formdata->$field);
            return array('value' => (array)$value);
        }

        return false;
    }

    function get_report_parameters($data) {
        return array('value'   => $data['value'],
                     'numeric' => $this->_numeric);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        $full_fieldname = $this->get_full_fieldname();
        if (empty($full_fieldname)) {
            return null;
        }

        $values = $data['value'];
        if (empty($values)) {
            return 'FALSE';
        }

        if (empty($this->_numeric)) {
            $values = clone($values);
            foreach ($values as $key => $value) {
                $values[$key] = "'{$value}'";
            }
        }

        $string_list = implode(', ', $values);

        return array("{$full_fieldname} IN ({$string_list})", array());
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $a        = new object();
        $a->label = $this->_label;
        $a->value = '';
        foreach ($this->_options as $key => $value) {
            if ($value == $data['value']) {
                $a->value = "'" . $key . "'";
            }
        }
        $a->operator = get_string('isequalto','filters');

        return get_string('selectlabel', 'filters', $a);
    }

}

