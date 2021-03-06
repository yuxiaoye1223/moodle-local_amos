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

/**
 * Main AMOS translation page
 *
 * Displays strings filter and the translation table. Data submitted from the
 * whole translation table are handled by savebulk.php which should redirect
 * back here.
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

require_login(SITEID, false);
require_capability('local/amos:stage', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/view.php');
$PAGE->set_title('AMOS ' . get_string('translatortool', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('translatortool', 'local_amos'));
if (empty($CFG->googleapikey)) {
    $PAGE->requires->js(new moodle_url('http://www.google.com/jsapi'));
} else {
    $PAGE->requires->js(new moodle_url('http://www.google.com/jsapi', array('key'=>$CFG->googleapikey)));
}
$PAGE->requires->js_init_call('M.local_amos.init_translator', array(), true);
$PAGE->requires->yui_module('moodle-local_amos-timeline', 'M.local_amos.init_timeline');

$output = $PAGE->get_renderer('local_amos');

// create a renderable object that represents the filter form
$filter = new local_amos_filter($PAGE->url);
// save the filter settings into the sesssion
$fdata = $filter->get_data();
foreach ($fdata as $setting => $value) {
    $USER->{'local_amos_' . $setting} = serialize($value);
}
$filter->set_permalink($PAGE->url, $fdata);

// just make sure that USER contains sesskey
$sesskey = sesskey();
// create a renderable object that represent the translation table
$translator = new local_amos_translator($filter, $USER);

/// Output starts here
echo $output->header();
echo $output->render($filter);
echo $output->render($translator);
echo $output->box('', 'googlebranding', 'googlebranding');
echo $output->footer();
