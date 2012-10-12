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
 * @subpackage php_reports
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

//needed for moodleform definition
require_once(dirname(__FILE__).'/../../../config.php');

//the file we are rendering
$image_file = "{$CFG->wwwroot}/blocks/php_report/pix/throbber_loading.gif";

echo "
function start_throbber( ) {
    throb = null;
    if (document.all) { // windows IE
        //alert('using document.all[]');
        throb = document.all['throbber'];
    }
    if (!throb && !(throb = document.getElementById('throbber'))) {
        //alert('throbber div not found!');
        if (!(reportdiv = document.getElementById('php_report_block')) ||
            !(reportkids = reportdiv.getElementsByTagName('div')) ||
            !(nextelem = reportkids[0].getElementsByTagName('*'))
        ) {
            //alert('inner element(s) not found!');
            return;
        }
        throb = document.createElement('DIV');
        throb.name = 'throbber';
        reportkids[0].insertBefore(throb, nextelem[0]);
    }
    window.scrollTo(0,0);
    throb.innerHTML = '<center><img src=\"{$image_file}\" /></center>';
}
";
