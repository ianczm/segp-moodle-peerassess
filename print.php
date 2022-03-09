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
 * print a printview of peerassess-items
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT); // Course where this peerassess is mapped to - used for return link.

$PAGE->set_url('/mod/peerassess/print.php', array('id'=>$id));

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
require_course_login($course, true, $cm);

$peerassess = $PAGE->activityrecord;
$peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $courseid);

$PAGE->set_pagelayout('popup');

// Print the page header.
$strpeerassesss = get_string("modulenameplural", "peerassess");
$strpeerassess  = get_string("modulename", "peerassess");

$peerassess_url = new moodle_url('/mod/peerassess/index.php', array('id'=>$course->id));
$PAGE->navbar->add($strpeerassesss, $peerassess_url);
$PAGE->navbar->add(format_string($peerassess->name));

$PAGE->set_title($peerassess->name);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

// Print the main part of the page.
echo $OUTPUT->heading(format_string($peerassess->name));

$continueurl = new moodle_url('/mod/peerassess/view.php', array('id' => $id));
if ($courseid) {
    $continueurl->param('courseid', $courseid);
}

$form = new mod_peerassess_complete_form(mod_peerassess_complete_form::MODE_PRINT,
        $peerassessstructure, 'peerassess_print_form');
echo $OUTPUT->continue_button($continueurl);
$form->display();
echo $OUTPUT->continue_button($continueurl);

// Finish the page.
echo $OUTPUT->footer();

