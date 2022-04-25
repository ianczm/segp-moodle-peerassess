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
 * the first page to view the peerassess
 *
 * @author SEGP Group 10A
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/peerassess/lib.php');

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('peerassess', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cmid);

$PAGE->set_url('/mod/peerassess/calculate_pa_grades_form_view.php', array('id' => $cm->id));
$PAGE->set_title('Peer Factor Configuration');
$PAGE->set_heading('Peer Factor Configuration');

require_course_login($course, true, $cm);
$peerassess = $PAGE->activityrecord;

// Check whether the peerassess is mapped to the given courseid.
if (!has_capability('mod/peerassess:edititems', $context) &&
        !$peerassesscompletion->check_course_is_mapped()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cannotaccess', 'mod_peerassess'));
    echo $OUTPUT->footer();
    exit;
}

// Print main page

echo $OUTPUT->header();
echo $OUTPUT->heading("Peer Factor Configuration");

echo "Please enter a value for rmax: ";

print_object($peerassess->id);

echo $OUTPUT->footer();