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
 * @subpackage pm-blocks-phpreport
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//need to do this because this file is included from different paths
require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->libdir . '/pChart.1.27d/pChart/pChart.class');
require_once($CFG->libdir . '/pChart.1.27d/pChart/pData.class');  

define('PHP_REPORT_GAS_GAUGE_PI', 3.1415);
define('PHP_REPORT_GAS_GAUGE_POINTS_PER_PI', 2000);
define('PHP_REPORT_GAS_GAUGE_LABEL_FONT_SIZE', 12);
define('PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_WIDTH', 0.02);
define('PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_TOP_POSITION', 0.1);

/**
 * Converts a polar coordinate to an absolute position on the
 * chart being rendered
 * 
 * @param    float     $theta   The point's angle, from positive x-axis and moving clockwise
 * @param    float     $radius  The mangnitude of the polar radius
 * 
 * @return   stdClass           An object containing the necessary x and y coordinates
 */
function php_report_gas_gauge_polar_to_cartesian($theta, $radius) {
    $result = new stdClass;
    
    //polar coordinates start at the positive x axis and move counterclockwise
    //but our chart starts at the negative x and moves clockwise
    $effective_theta = PHP_REPORT_GAS_GAUGE_PI - $theta;
    
    //translate by the size of the radius, since, we are centered at the bottom-center
    $result->x = $radius + $radius * cos($effective_theta); 
    //translate, reverse y coordinates of radius component since y values increase going downward
    //on the plot
    $result->y = $radius - $radius * sin($effective_theta);
    
    return $result;
}

/**
 * Manually create pie slices for each of the sections
 * 
 * @param  pChart reference  $chart         The chart to draw the image onto
 * @param  float             $radius        Radius of the gas gauge
 * @param  int               $num_sections  The number of pie slices to render
 */
function php_report_gas_gauge_create_pie(&$chart, $radius, $num_sections) {
    $pie_chart_theta = PHP_REPORT_GAS_GAUGE_PI;

    //create a slice for each section
    for ($i = 0; $i < $num_sections; $i++) {
        $sample_size = round(PHP_REPORT_GAS_GAUGE_POINTS_PER_PI / $num_sections);
    
        //always have a point at the bottom-center of the screen
        $pie_slice_points = array($radius, $radius);
    
        //angle at which the slice starts
        $base_theta = PHP_REPORT_GAS_GAUGE_PI / $num_sections * $i;
    
        //go through the sample points for this section (including endpoints)
        for ($j = 0; $j <= $sample_size; $j++) {
            //angle representing the current incremental sample point
            $current_pie_slice_theta = $base_theta + PHP_REPORT_GAS_GAUGE_PI / ($num_sections * $sample_size) * $j;
        
            $sample_pos = php_report_gas_gauge_polar_to_cartesian($current_pie_slice_theta, $radius);
            
            //x-value along the outside of the circle
            $pie_slice_points[] = $sample_pos->x;
            //y-value along the outside of the circle
            $pie_slice_points[] = $sample_pos->y;
        }
    
        //use the built-in chart palette
        $bg = imagecolorallocate($chart->Picture, $chart->Palette[$i]['R'], $chart->Palette[$i]['G'], $chart->Palette[$i]['B']);
        //write the slice to the chart
        imagefilledpolygon($chart->Picture, $pie_slice_points, $sample_size, $bg);
    }   
}

/**
 * Draw text labels for each of the sections
 * 
 * @param  pChart reference  $chart         The chart we are adding labels to
 * @param  float             $radius        The radius of the gas gauge, in pixels
 * @param  float             $total         The maximum value on the gas gauge
 * @param  int               $num_sections  Number of pie slices in the semi-circle gauge
 * @param  string            $font          Path to the file containing our desired font
 */
function php_report_gas_gauge_draw_labels(&$chart, $radius, $total, $num_sections, $font) {
    $chart->setFontProperties($font, PHP_REPORT_GAS_GAUGE_LABEL_FONT_SIZE);

    for ($i = 0; $i <= $num_sections; $i++) {
        //our display value is the percentage of the total, based on position on the circle
        $display_value = round($i / $num_sections * $total);
        //angle is a portion of the half-circle
        $label_theta = $i / $num_sections * PHP_REPORT_GAS_GAUGE_PI;
    
        //exact point on the circle
        $label_pos = php_report_gas_gauge_polar_to_cartesian($label_theta, $radius);
    
        $alignment = NULL;
        if ($i == 0) {
            //bump the first label above the bottom of the chart area
            $alignment = ALIGN_BOTTOM_LEFT;
        } else if ($i == $num_sections) {
            //bump the last label above the bottom of the chart area
            $alignment = ALIGN_BOTTOM_RIGHT;
        } else if ($i < $num_sections / 2) {
            //in the left half, so align the top-left corner onto the circle
            $alignment = ALIGN_TOP_LEFT;
        } else if ($i == $num_sections / 2) {
            //exactly in the middle, so align to the top
            $alignment = ALIGN_TOP_CENTER;
        } else {
            //in the right half, so align the top-right corner onto the circle
            $alignment = ALIGN_TOP_RIGHT;
        }
    
        //determine the dimensions of the text to be rendered
        $label_size = imageftbbox(PHP_REPORT_GAS_GAUGE_LABEL_FONT_SIZE, 0, $font, $display_value);
    
        //lower-left to lower-right
        $label_width = abs($label_size[1] = $label_size[3]);
        //lower-left to upper-left
        $label_height = abs($label_size[1] - $label_size[7]);
    
        //render the text
        $chart->drawTextBox($label_pos->x, $label_pos->y, $label_pos->x + $label_width, $label_pos->y - $label_height, $display_value, 0, 0, 0, 0, $alignment);
    }
}

/**
 * Adds an arrow representing a normalized value on the supplied chart
 * 
 * @param  pChart reference  $chart   The chart to add the arrow to
 * @param  float             $radius  The radius of the supplied chart
 * @param  float             $value   The value represented by the arrow
 * @param  float             $total   The maximum value allowable on the chart
 */
function php_report_gas_gauge_draw_arrow(&$chart, $radius, $value, $total) {
    //calculate angle based on proportion
    $line_theta = $total ? ($value / $total * PHP_REPORT_GAS_GAUGE_PI) : 0;
    $effective_theta = $line_theta - PHP_REPORT_GAS_GAUGE_PI / 2;
    
    //calculate absolute coordinates
    $line_pos = php_report_gas_gauge_polar_to_cartesian($line_theta, $radius);

    //relative width of the base of the arrow
    $arrow_width = PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_WIDTH * $radius;
    
    //our untranslated sample data
    //starting from bottom-left and going counter-clockwise to prevent crossings
    $data = array(//bottom-left of the arrow
                  $radius - $arrow_width, $radius,
                  //bottom-right of the arrow
                  $radius + $arrow_width, $radius,
                  //
                  $radius + $arrow_width, PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_TOP_POSITION * $radius,
                  //right point of the arrow
                  $radius - 2 * $arrow_width, PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_TOP_POSITION * $radius,
                  //arrow tip
                  $radius, 0,
                  $radius + 2 * $arrow_width, PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_TOP_POSITION * $radius,
                  //left point of the arrow
                  $radius - $arrow_width, PHP_REPORT_GAS_GAUGE_RELATIVE_ARROW_TOP_POSITION * $radius);
                  
    $final_data = array();                  

    //number of sample points
    $num_points = floor(count($data) / 2);
    
    for ($i = 0; $i < $num_points; $i++) {
        //translate cooridinates so that we are rotating about (0, 0)
        $shifted_x = $data[2 * $i] - $radius;
        $shifted_y = $data[2 * $i + 1] - $radius;

        //apply the rotation
        $new_x = $shifted_x * cos($effective_theta) - $shifted_y * sin($effective_theta);
        $new_y = $shifted_x * sin($effective_theta) + $shifted_y * cos($effective_theta);
        
        //shift back to original coordinate system
        $new_x += $radius;
        $new_y += $radius;

        //append point to our final data
        $final_data[] = $new_x;
        $final_data[] = $new_y;
    }

    //allocate black for the arrow
    $bg = imagecolorallocate($chart->Picture, 0, 0, 0);
    //plot the final arrow polygon on the chart's image
    imagefilledpolygon($chart->Picture, $final_data, $num_points, $bg);
}

/**
 * Parameters may be specified via passed-through variables or URL parameters,
 * depending on how this script is used
 */

//allow numeric values to have decimal points
if (isset($passthru_value)) {
    $value = $passthru_value;
} else {
    $value = required_param('value', PARAM_CLEAN);
}

if (isset($passthru_total)) {
    $total = $passthru_total;
} else {
    $total = required_param('total', PARAM_NUMBER);
}

//graph size
if (isset($passthru_radius)) {
    $radius = $passthru_radius;
} else {
    $radius = required_param('radius', PARAM_INT);
}

//RGB color palette
if (isset($passthru_palette)) {
    $palette = $passthru_palette;
} else {
    $palette = required_param('palette', PARAM_TEXT);
    $palette = unserialize(base64_decode($palette));
}

$persist = 0;
if (!empty($passthru_persist)) {
    $persist = 1;
}

$num_sections = count($palette);

//make sure we actually have a numerical value
if ($value === '') {
    exit;
} else {
    $value = (float)$value;
}

$font = $CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf";

//creat our main chart
$chart = new pChart(2 * $radius, $radius);
$chart->setGraphArea(0, 0, 2 * $radius, $radius);

//set the RPG color palette from the parameter
foreach ($palette as $i => $rgb) {
    $chart->setColorPalette($i, $rgb[0], $rgb[1], $rgb[2]);
}

//draw pie slices
php_report_gas_gauge_create_pie($chart, $radius, $num_sections);
//label pie slices
php_report_gas_gauge_draw_labels($chart, $radius, $total, $num_sections, $font);
//arrow that measures progress
php_report_gas_gauge_draw_arrow($chart, $radius, $value, $total);

if ($persist == 0) {
    //render the chart to the screen
    $chart->Stroke();
} else {
    //store the chart in a PNG file
    imagepng($chart->Picture, $passthru_filename);
}
