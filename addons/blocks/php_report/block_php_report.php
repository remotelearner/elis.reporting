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
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

// Uncomment next line to allow PHP reports in a block
// define('ALLOW_PHPREPORT_BLOCKS', 1);

/**
 * Base class for all the main report types, such as
 * tabular reports and graphs
 */
class block_php_report extends block_base {

    /**
     * Initialize the report block settings
     */
    function init() {
        $this->title = get_string('title', 'block_php_report');

        $this->version = 2011042800;
        $this->revision = '1.9.0';
    }

    /**
     * Calculates the HTML output used to display the block
     *
     * @return  stdClass  An object containing the header, body, and footer contents
     */
    function get_content() {
      if (defined('ALLOW_PHPREPORT_BLOCKS')) {
        global $SESSION, $CFG;

        //needed for AJAX calls
        require_js(array('yui_yahoo',
                         'yui_dom',
                         'yui_event',
                         'yui_connection',
                         "{$CFG->wwwroot}/curriculum/js/associate.class.js"));

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        //Make sure all required settings are configured
        if(!isset($this->config->titledisplayed) ||
           empty($this->config->reportinstance) ||
           !isset($this->config->cachetime) ||
           empty($this->config->reportwidth)) {
            $this->content->text .= '<span class="configuration_msg">' . get_string('notconfigured', 'block_php_report') . '</span>';
            return $this->content;
        }

        $instance_classname = substr($this->config->reportinstance, 0, strlen($this->config->reportinstance) - strlen('.class.php'));
        $folder_name = substr($instance_classname, 0, strlen($instance_classname) - strlen('_report'));

        require_once($CFG->dirroot . '/blocks/php_report/instances/' . $folder_name . '/' . $this->config->reportinstance);

        //permissions checking
        $test_instance = new $instance_classname($this->instance->id);

        if(!$test_instance->is_available()) {
            $this->content->text .= '<span class="configuration_msg">' . get_string('notconfigured', 'block_php_report') . '</span>';
            return $this->content;
        }

        if(!$test_instance->can_view_report()) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/blocks/php_report/php_report_block.class.php');

        //Create and display the report
        $effective_pagesize = !empty($this->config->pagesize) ? $this->config->pagesize : 20;
        $reportblock = new php_report_block($this->instance->id, !empty($this->config->expandedbydefault), $this->config->titledisplayed,
                                            $this->config->cachetime, $effective_pagesize, $instance_classname, $this->config->reportwidth);
        $reportblock->require_dependencies();

        // Display second header for theme issues
        //Accessibility: validation, can't have <div> inside <h2>, use <span>.
        $title = '<div class="subTitle" id="subTitle" name="subTitle">';

        //Accessibility: added 'alt' text for the +- icon.
        //Theme the buttons using, Admin - Miscellaneous - smartpix.
        $strshow = addslashes_js(get_string('showblocka', 'access', strip_tags($this->title)));
        $strhide = addslashes_js(get_string('hideblocka', 'access', strip_tags($this->title)));

        // Set up defaults for Javascript rollover script toggleClassname

        $newImagename = $CFG->pixpath.'/t/switch_minus.gif';
        $newClassname = 'rollup-report';
        $newId = 'report-image';
        $newTitle = $strhide;
        $defaultImagename = $CFG->pixpath.'/t/switch_plus.gif';
        $defaultClassname = 'rolldown-report';
        $defaultId = 'report-image';
        $defaultTitle = $strshow;


        $image_filename = $newImagename;
        $span_id = $newId;
        $classname = $newClassname;
        $showhide = $newTitle;

        //determine which mode we are in
        // Check if session isset and then check the expanded_by_default parameter
        if(!isset($SESSION->php_reports[$this->instance->id])) {
            if (empty($this->config->expandedbydefault)) {
                $image_filename = $defaultImagename;
                $span_id = $defaultId;
                $classname = $defaultClassname;
                $showhide = $defaultTitle;
            }
        } else if(empty($SESSION->php_reports[$this->instance->id]->visible)) {
            $image_filename = $defaultImagename;
            $span_id = $defaultId;
            $classname = $defaultClassname;
            $showhide = $defaultTitle;
        }

        $title .= '<input type="submit" value="" src="'.$image_filename . '" '.
                'id="togglehide_inst'.$this->instance->id.'" '.
                'onclick="toggleClassname(\'' . $CFG->wwwroot . '/blocks/php_report/\', this, '.
                '\''.$newClassname.'\',\''.$defaultClassname.'\','.
                '\'' . $newImagename . '\',\'' . $defaultImagename . '\','.
                '\'' . $newTitle . '\',\'' . $defaultTitle . '\','.
                '\'' . $this->instance->id . '\');return false;" '.
                'class="show-hide-report-image '.$classname.'" />';

        //get the title text
        $title_text = get_string('defaulttitle', 'block_php_report');
        if(!empty($this->config->titledisplayed)) {
            $title_text = '<span class="php_report_title_text">' . $this->config->titledisplayed . '</span>';
        }

        //Accesssibility: added H2 (was in, weblib.php: print_side_block)
        $title .= $title_text;

        $title .= '</div>';
        $this->content->text = $title;
        // End of second header

        $this->content->text .= "<script>
                                my_handler = new associate_link_handler('{$CFG->wwwroot}/blocks/php_report/dynamicreport.php',
                                'php_report_body_{$this->instance->id}');
                                </script>";

        //this is where the actual report output is calculated
        $this->content->text .= $reportblock->display();
        return $this->content;
      } else { // not defined('ALLOW_PHPREPORT_BLOCKS')
        return NULL;
      }
    }

    /**
     * Determine if header will be shown
     * @return boolean
     */
    function hide_header() {
        return !defined('ALLOW_PHPREPORT_BLOCKS');
    }

    /**
     * Determine where the block is allowed
     * @return array
     */
    function applicable_formats() {
        return(defined('ALLOW_PHPREPORT_BLOCKS') ? parent::applicable_formats()
                                                 : array('all' => false,
                                                         'nowhere' => true));
    }

    /**
     * Specifies whether individual blocks can be specifically configured
     *
     * @return  boolean  Return true to enable instance config
     */
    function instance_allow_config() {
        return defined('ALLOW_PHPREPORT_BLOCKS');
    }

    /**
     * Specifies whether multiple instances of this block can be added to one page
     *
     * @return  boolean  Return true to allow multiple block instances
     */
    function instance_allow_multiple() {
        return defined('ALLOW_PHPREPORT_BLOCKS'); // TBD: false?
    }

    /**
     * Saves the configuration data after the config form is submitted
     *
     * @param  stdClass  $data  An object containing the necessary config properties
     */
    function instance_config_save($data) {
        global $SESSION, $CFG;

        require_once($CFG->dirroot . '/blocks/php_report/php_report_block.class.php');

        //report caching
        if(empty($data->cachetime_always)) {
            $data->cachetime_hours = intval($data->cachetime_hours);
            $data->cachetime_minutes = intval($data->cachetime_minutes);
            $data->cachetime_seconds = intval($data->cachetime_seconds);

            $data->cachetime = HOURSECS * $data->cachetime_hours +
                               MINSECS * $data->cachetime_minutes +
                               $data->cachetime_seconds;
            if($data->cachetime == 0) {
                $data->cachetime = php_report_block::$NO_CACHE;
            }
        } else {
            $data->cachetime = php_report_block::$ETERNAL_CACHE;
        }

        unset($data->cachetime_hours);
        unset($data->cachetime_minutes);
        unset($data->cachetime_seconds);

        //Make sure the pagesize is valid, or unset it
        $data->pagesize = intval($data->pagesize);
        if($data->pagesize <= 0) {
            $data->pagesize = '20';
        }

        //Make sure the tabular report width is valid, or unset it
        $data->reportwidth = intval($data->reportwidth);
        if($data->reportwidth <= 0) {
            unset($data->reportwidth);
        }

        //Make sure the number of columns is valid, or reset it
        $data->numcolumns = intval($data->numcolumns);
        if($data->numcolumns <= 0) {
            $data->numcolumns = 2;
        }

        //Make sure the icon width is valid, or unset it
        $data->iconwidth = intval($data->iconwidth);
        if($data->iconwidth <= 0) {
            unset($data->iconwidth);
        }

        //graph height
        $data->graphheight = intval($data->graphheight);
        if($data->graphheight <= 0) {
            $data->graphheight = 400;
        }

        //Clear the cache to prevent weirdness
        if(!empty($SESSION->php_reports[$this->instance->id])) {
            unset($SESSION->php_reports[$this->instance->id]);
        }

        return parent::instance_config_save($data);
    }

    /**
     * Calculates the perferred block width based on the report width
     *
     * @return  int  The preferred block width, in pixels
     */
    function preferred_width() {
        //this gets called statically from blocklib
        //so this is not guaranteed to work
        if(isset($this) && !empty($this->config->reportwidth)) {
            return $this->config->reportwidth + 10;
        }
        return parent::preferred_width();
    }

    /**
     * Calculates the title HTML
     *
     * @return  string  The HTML of the title bar
     */
    function _title_html() {
        global $CFG, $SESSION;

        $css_class = '';
        if(!empty($this->config->reportinstance)) {
            $css_class = substr($this->config->reportinstance, 0, strlen($this->config->reportinstance) - strlen('_report.class.php'));
        }

        $css_class = 'title' . (!empty($css_class) ? (' title_' . $css_class) : '');

        //Accessibility: validation, can't have <div> inside <h2>, use <span>.
        $title = '<div class="' . $css_class . '">';

        if (!empty($CFG->allowuserblockhiding)) {

            //Accessibility: added 'alt' text for the +- icon.
            //Theme the buttons using, Admin - Miscellaneous - smartpix.
            $strshow = addslashes_js(get_string('showblocka', 'access', strip_tags($this->title)));
            $strhide = addslashes_js(get_string('hideblocka', 'access', strip_tags($this->title)));
            $newTitle = $strhide;
            $defaultTitle = $strshow;

            //determine which mode we are in
            $image_filename = 'switch_minus.gif';
            $showhide = $newTitle;

            //this is needed in case our session contains an instance that has become unavailable
            //via is_available
            require_once($CFG->dirroot . '/blocks/php_report/php_report_block.class.php');
            php_report_block::require_dependencies();

            if(empty($SESSION->php_reports[$this->instance->id]->visible)) {
                $image_filename = 'switch_plus.gif';
                $showhide = $defaultTitle;
            }

            $title .= '<input type="image" src="'.$CFG->pixpath.'/t/' . $image_filename . '" '.
                'id="togglehide_inst'.$this->instance->id.'" '.
                'onclick="toggle_report_block(\'' . $CFG->wwwroot . '/blocks/php_report/\', this,'.
                '\'' . $this->instance->id . '\','.
                '\'' . $newTitle . '\',\'' . $defaultTitle . '\');return false;" '.
                'alt="'.$showhide.'" title="'.$showhide.'" class="hide-show-image" />';
        }

        //get the title text
        $title_text = get_string('defaulttitle', 'block_php_report');
        if(!empty($this->config->titledisplayed)) {
            $title_text = $this->config->titledisplayed;
        }

        //Accesssibility: added H2 (was in, weblib.php: print_side_block)
        $title .= '<h2>'.$title_text.'</h2>';

        if ($this->edit_controls !== NULL) {
            $title .= $this->edit_controls;
        }

        $title .= '</div>';
        return $title;
    }

}

?>
