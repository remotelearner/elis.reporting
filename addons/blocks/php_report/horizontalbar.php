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
 * @package    elis
 * @subpackage curriculummanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once('../../config.php');
require_once($CFG->libdir . '/pChart.1.27d/pChart/pChart.class');

//default colors
$color_defaults = array('r' => 0,
                        'g' => 255,
                        'b' => 0);

$text_color_defaults = array('r' => 0,
                             'g' => 0,
                             'b' => 0);

$background_color_defaults = array('r' => 255,
                                   'g' => 255,
                                   'b' => 255);

$border_color_defaults = array('r' => 0,
                               'g' => 0,
                               'b' => 0);

//key data
$value = required_param('value', PARAM_NUMBER);
$total = required_param('total', PARAM_NUMBER);

//our templated display text
$display_text = optional_param('displaytext', '', PARAM_TEXT);
$display_percent_sign = optional_param('displaypercentsign', 0, PARAM_INT);

//colors
$color = optional_param('color', '0,255,0', PARAM_TEXT);
$text_color = optional_param('text_color', '0,0,0', PARAM_TEXT);
$background_color = optional_param('background_color', '255,255,255', PARAM_TEXT);
$border_color = optional_param('border_color', '0,0,0', PARAM_TEXT);

//sizing
$width = optional_param('width', 100, PARAM_INT);
$height = optional_param('height', 20, PARAM_INT);

/**
 * Converts a comma-separated rgb list to an array
 *
 * @param   string  $color_string  The comma-separated rgb list
 * @param   array   $default       Default to use in case of error
 *
 * @return  array                  The apropriate rgb list
 */
function get_color($color_string, $default) {
    $color_parts = explode(',', $color_string);
    if(count($color_parts) != 3) {
        return $default;
    }

    $component_array = array('r', 'g', 'b');

    $result = array();
    foreach($color_parts as $id => $color_part) {
        if(trim($color_part) != (int)trim($color_part)) {
            return $default;
        }
        $result[$component_array[$id]] = (int)trim($color_part);
    }

    return $result;

}

/**
 * Draws a rectangle on the provided chart
 *
 * @param  pChart   $chart        The chart to draw on
 * @param  int      $x1           Leftmost x value
 * @param  int      $y1           Topmost y value
 * @param  int      $x2           Rightmost x value
 * @param  int      $y2           Topmost y value
 * @param  array    $color_array  The colors to use
 * @param  boolean  $filled       Whether to fill the area

 */
function draw_rectangle(&$chart, $x1, $y1, $x2, $y2, $color_array, $filled = true) {
    if($filled) {
        $chart->drawFilledRectangle($x1, $y1, $x2, $y2, $color_array['r'], $color_array['g'], $color_array['b']);
    } else {
        $chart->drawRectangle($x1, $y1, $x2, $y2, $color_array['r'], $color_array['g'], $color_array['b']);
    }
}

/**
 *  Determines the text to display
 *
 *  @param  numeric  $value     The data's value
 *  @param  numeric  $total     The maximum value
 *  @param  nuemric  $fraction  The fraction used to draw the bar
 *  @param  string   $text      The templated message
 */
function get_text($value, $total, $fraction, $text, $display_percent_sign) {
    $text = str_replace('$v', $value, $text);
    $text = str_replace('$t', $total, $text);

    $percentage_shown = round(100 * $fraction);
    if(!empty($display_percent_sign)) {
        $percentage_shown .= get_string('percent_symbol', 'block_php_report');
    }

    $text = str_replace('$p', $total == 0 ? get_string('na', 'block_php_report') : $percentage_shown, $text);

    $text = str_replace('$e', $total == 0 ? get_string('na', 'block_php_report') : '', $text);

    return $text;
}

//determine the colors to use
$color_array = get_color($color, $color_defaults);
$text_color_array = get_color($text_color, $text_color_defaults);
$background_color_array = get_color($background_color, $background_color_defaults);
$border_color_array = get_color($border_color, $border_color_defaults);

//calculate how full the bar should be
if($total == 0) {
    $fraction = 0;
} else {
    $fraction = $value / $total;
}

//set up the chart
$chart = new pChart($width + 2, $height + 2);
$chart->setGraphArea(0, 0, $width + 1, $height + 1);

//draw the static background
draw_rectangle($chart, 0, 0, $width + 1, $height + 1, $background_color_array);

//draw the bar for our actual value
if($fraction > 0) {
    draw_rectangle($chart, 0, 0, 1 + round($fraction * $width), $height + 1, $color_array);
}

//draw the border
draw_rectangle($chart, 0, 0, $width + 1, $height + 1, $border_color_array, false);

//draw the text

$display_text = get_text($value, $total, $fraction, $display_text, $display_percent_sign);

if(strlen($display_text) > 0) {
    $chart->setFontProperties($CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf", 10);
    $chart->drawTextBox(0, 0, $width + 1, $height + 1, $display_text, 0,
                        $text_color_array['r'], $text_color_array['g'], $text_color_array['b'], ALIGN_CENTER);
}

//display the chart image
$chart->Stroke();
