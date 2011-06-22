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

require_once('../../config.php');
require_once($CFG->dirroot . '/lib/pChart.1.27d/pChart/pChart.class');
require_once($CFG->dirroot . '/lib/pChart.1.27d/pChart/pData.class');

/**
 * Determines an appropriate height for the legend
 *
 * @param  $dataset   Our dataset containing the values we need to check for sizes
 * @param  $font      A reference to the font file used
 * @param  $fontsize  The fontsize used
 *
 * @return            The vertical height of the text, plus a small margin
 */
function get_legend_height($dataset, $font, $fontsize) {
    $description = $dataset->GetDataDescription();

    $total_value = 0;

    foreach($description["Description"] as $key => $value) {
        $position = imageftbbox($fontsize, 0, $font, $value);
        $textheight  = $position[1] - $position[7];
        $total_value += abs($textheight);
    }

    return $total_value + 20;
}

//retrieve the graph's data
$data = required_param('data', PARAM_TEXT);
$data = unserialize(base64_decode($data));

$id = optional_param('id', 0, PARAM_INT);

$width = 400;
$height = 400;
if(!empty($id) and $block_instance = get_record('block_instance', 'id', $id)) {
    $config_data = unserialize(base64_decode($block_instance->configdata));
    if(!empty($config_data->reportwidth)) {
        $width = $config_data->reportwidth;
    }
    if(!empty($config_data->graphheight)) {
        $height = $config_data->graphheight;
    }
}

// Dataset definition
$dataset = new pData();

//default string value causes problems
$dataset->Data = array();

foreach($data['series'] as $key => $series) {
    $dataset->AddPoint($series, $key);
}

$dataset->AddAllSeries();

foreach($data['series'] as $key => $series) {
    $dataset->SetSerieName($key, $key);
}

//dynamically determine the legend width
$legend_height = get_legend_height($dataset, $CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf", 8);

//create the chart
$chart = new pChart($width, $height);
$chart->Palette = array();
$chart->loadColorPalette($CFG->dirroot . "/lib/pChart.1.27d/Sample/softtones.txt");

//pChart does not correctly wrap colours, so we need to repeat them
$original_count = count($chart->Palette);
for($i = count($chart->Palette); $i < count($data['series']); $i++) {
    $chart->Palette[$i] = $chart->Palette[$i % $original_count];
}

//set up the main chart area
$chart->setFontProperties($CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf", 8);
$chart->setGraphArea(30, 30 + $legend_height, $width - 10, $height - 30);
$chart->drawGraphArea(255, 255, 255, TRUE);

$labelled_data = $dataset->GetData();

//set x-axis labels
$i = 0;
$new_labelled_data = array();
foreach($labelled_data as $key => $value) {
    $new_labelled_data[$key] = $value;
    $data_description = $dataset->GetDataDescription();
    //this seems to be the correct way to set an x-axis label
    $new_labelled_data[$key][$data_description["Position"]] = $data['labels'][$i];
    $i++;
}

//draw the scale and the grid
$chart->drawScale($new_labelled_data, $dataset->GetDataDescription(), SCALE_NORMAL, 150, 150, 150, TRUE, 0, 2, TRUE);
$chart->drawGrid(4, TRUE, 230, 230, 230, 50);

//add the bars
$chart->setFontProperties($CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf",6);
$chart->drawBarGraph($dataset->GetData(), $dataset->GetDataDescription(), TRUE);

//add the legend
$chart->setFontProperties($CFG->dirroot . "/lib/pChart.1.27d/Fonts/tahoma.ttf", 8);
$chart->drawLegend(0, 0, $dataset->GetDataDescription(), 255, 255, 255);
$chart->Stroke();

?>