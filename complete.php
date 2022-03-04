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
 * prints the form so the user can fill out the peerassess
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

require_once("../../config.php");
require_once("lib.php");

peerassess_init_peerassess_session();

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);
$gopage = optional_param('gopage', 0, PARAM_INT);
$gopreviouspage = optional_param('gopreviouspage', null, PARAM_RAW);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
$peerassess = $DB->get_record("peerassess", array("id" => $cm->instance), '*', MUST_EXIST);

$urlparams = array('id' => $cm->id, 'gopage' => $gopage, 'courseid' => $courseid);
$PAGE->set_url('/mod/peerassess/complete.php', $urlparams);

require_course_login($course, true, $cm);
$PAGE->set_activity_record($peerassess);

$context = context_module::instance($cm->id);
$peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $courseid);

$courseid = $peerassesscompletion->get_courseid();

// Check whether the peerassess is mapped to the given courseid.
if (!has_capability('mod/peerassess:edititems', $context) &&
        !$peerassesscompletion->check_course_is_mapped()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cannotaccess', 'mod_peerassess'));
    echo $OUTPUT->footer();
    exit;
}

//check whether the given courseid exists
if ($courseid AND $courseid != SITEID) {
    require_course_login(get_course($courseid)); // This overwrites the object $COURSE .
}

if (!$peerassesscompletion->can_complete()) {
    print_error('error');
}

$PAGE->navbar->add(get_string('peerassess:complete', 'peerassess'));
$PAGE->set_heading($course->fullname);
$PAGE->set_title($peerassess->name);
$PAGE->set_pagelayout('incourse');

// Check if the peerassess is open (timeopen, timeclose).
if (!$peerassesscompletion->is_open()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($peerassess->name));
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo $OUTPUT->notification(get_string('peerassess_is_not_open', 'peerassess'));
    echo $OUTPUT->continue_button(course_get_url($courseid ?: $peerassess->course));
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// Mark activity viewed for completion-tracking.
if (isloggedin() && !isguestuser()) {
    $peerassesscompletion->set_module_viewed();
}

// Check if user is prevented from re-submission.
$cansubmit = $peerassesscompletion->can_submit();

// Initialise the form processing peerassess completion.
if (!$peerassesscompletion->is_empty() && $cansubmit) {
    // Process the page via the form.
    $urltogo = $peerassesscompletion->process_page($gopage, $gopreviouspage);

    if ($urltogo !== null) {
        redirect($urltogo);
    }
}

// Print the page header.
$strpeerassesss = get_string("modulenameplural", "peerassess");
$strpeerassess  = get_string("modulename", "peerassess");

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerassess->name));

if ($peerassesscompletion->is_empty()) {
    \core\notification::error(get_string('no_items_available_yet', 'peerassess'));
} else if ($cansubmit) {
    if ($peerassesscompletion->just_completed()) {
        // Display information after the submit.
        if ($peerassess->page_after_submit) {
            echo $OUTPUT->box($peerassesscompletion->page_after_submit(),
                    'generalbox boxaligncenter');
        }
        if ($peerassesscompletion->can_view_analysis()) {
            echo '<p align="center">';
            $analysisurl = new moodle_url('/mod/peerassess/analysis.php', array('id' => $cm->id, 'courseid' => $courseid));
            echo html_writer::link($analysisurl, get_string('completed_peerassesss', 'peerassess'));
            echo '</p>';
        }

        if ($peerassess->site_after_submit) {
            $url = peerassess_encode_target_url($peerassess->site_after_submit);
        } else {
            $url = course_get_url($courseid ?: $course->id);
        }
        echo $OUTPUT->continue_button($url);
    } else {
        // Display the form with the questions.
        echo $peerassesscompletion->render_items();
    }
} else {
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    echo $OUTPUT->notification(get_string('this_peerassess_is_already_submitted', 'peerassess'));
    echo $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
