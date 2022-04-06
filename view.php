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
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/peerassess/lib.php');

$id = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', false, PARAM_INT);

$current_tab = 'view';

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
require_course_login($course, true, $cm);
$peerassess = $PAGE->activityrecord;

$peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $courseid);

$context = context_module::instance($cm->id);

if ($course->id == SITEID) {
    $PAGE->set_pagelayout('incourse');
}
$PAGE->set_url('/mod/peerassess/view.php', array('id' => $cm->id));
$PAGE->set_title($peerassess->name);
$PAGE->set_heading($course->fullname);

// Check access to the given courseid.
if ($courseid AND $courseid != SITEID) {
    require_course_login(get_course($courseid)); // This overwrites the object $COURSE .
}

// Check whether the peerassess is mapped to the given courseid.
if (!has_capability('mod/peerassess:edititems', $context) &&
        !$peerassesscompletion->check_course_is_mapped()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('cannotaccess', 'mod_peerassess'));
    echo $OUTPUT->footer();
    exit;
}

// Trigger module viewed event.
$peerassesscompletion->trigger_module_viewed();

/// Print the page header
echo $OUTPUT->header();

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

$previewimg = $OUTPUT->pix_icon('t/preview', get_string('preview'));
$previewlnk = new moodle_url('/mod/peerassess/print.php', array('id' => $id));
if ($courseid) {
    $previewlnk->param('courseid', $courseid);
}
$preview = html_writer::link($previewlnk, $previewimg);

echo $OUTPUT->heading(format_string($peerassess->name) . $preview);

// Render the activity information.
$completiondetails = \core_completion\cm_completion_details::get_instance($cm, $USER->id);
$activitydates = \core\activity_dates::get_dates_for_module($cm, $USER->id);
echo $OUTPUT->activity_information($cm, $completiondetails, $activitydates);

// Print the tabs.
require('tabs.php');

// Show description.
echo $OUTPUT->box_start('generalbox peerassess_description');
$options = (object)array('noclean' => true);
echo format_module_intro('peerassess', $peerassess, $cm->id);
echo $OUTPUT->box_end();

// Get flag of grade released status
// $finalgradesreleased = get_grades_release_status();

//show some infos to the peerassess
if (has_capability('mod/peerassess:edititems', $context)) {

    echo $OUTPUT->heading(get_string('overview', 'peerassess'), 3);

    //get the groupid
    $groupselect = groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/peerassess/view.php?id='.$cm->id, true);
    $mygroupid = groups_get_activity_group($cm);

    echo $groupselect.'<div class="clearer">&nbsp;</div>';
    $summary = new mod_peerassess\output\summary($peerassesscompletion, $mygroupid);
    echo $OUTPUT->render_from_template('mod_peerassess/summary', $summary->export_for_template($OUTPUT));

    if ($pageaftersubmit = $peerassesscompletion->page_after_submit()) {
        echo $OUTPUT->heading(get_string("page_after_submit", "peerassess"), 3);
        echo $OUTPUT->box($pageaftersubmit, 'generalbox peerassess_after_submit');
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter');
    // Button to release final grades for all students
    if($finalgradesreleased == false){
        $releasegradesurl = new moodle_url('/mod/peerassess/release_grades.php', ['id' => $cm->id]);
        echo html_writer::div(html_writer::link($releasegradesurl, get_string("releaseallgradesforallgroups", 'peerassess'), array('class' => 'btn btn-secondary')));
    }
    // Final grades are already released
    else {
        echo $OUTPUT->notification(get_string('finalgradeshasbeenreleased', 'peerassess'));
    }
    echo $OUTPUT->box_end();
}

if (!has_capability('mod/peerassess:viewreports', $context) &&
        $peerassesscompletion->can_view_analysis()) {
    $analysisurl = new moodle_url('/mod/peerassess/analysis.php', array('id' => $id));
    echo '<div class="mdl-align"><a href="'.$analysisurl->out().'">';
    echo get_string('completed_peerassesss', 'peerassess').'</a>';
    echo '</div>';
}

if (has_capability('mod/peerassess:mapcourse', $context) && $peerassess->course == SITEID) {
    echo $OUTPUT->box_start('generalbox peerassess_mapped_courses');
    echo $OUTPUT->heading(get_string("mappedcourses", "peerassess"), 3);
    echo '<p>' . get_string('mapcourse_help', 'peerassess') . '</p>';
    $mapurl = new moodle_url('/mod/peerassess/mapcourse.php', array('id' => $id));
    echo '<p class="mdl-align">' . html_writer::link($mapurl, get_string('mapcourses', 'peerassess')) . '</p>';
    echo $OUTPUT->box_end();
}

if ($peerassesscompletion->can_complete()) {
    echo $OUTPUT->box_start('generalbox boxaligncenter');
    if (!$peerassesscompletion->is_open()) {
        // Peerassess is not yet open or is already closed.
        echo $OUTPUT->notification(get_string('peerassess_is_not_open', 'peerassess'));
        echo $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    } else if ($peerassesscompletion->can_submit()) {
        // Get remaining groupmates to assess

        $toassess_sql = "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as 'name'
                FROM {user} as u, {groups_members} as gm
                WHERE gm.groupid = (
                    SELECT gm.groupid
                    FROM {user} as u, {groups_members} as gm
                    WHERE u.id = ?
                    AND gm.userid = u.id
                    )
                AND gm.userid = u.id
                AND gm.userid != ?
                AND u.id NOT IN (
                    SELECT v.value
                    FROM {peerassess_value} as v
                    WHERE v.completed IN (
                        SELECT c.id as completedid
                        FROM {peerassess_completed} as c
                        WHERE c.peerassess = ?
                        AND c.userid = ?
                    )
                    AND v.item IN (
                        SELECT i.id
                        FROM {peerassess_item} as i
                        WHERE i.peerassess = ?
                        AND i.typ = 'memberselect'
                    )
                );";
        $toassess_db = $DB->get_records_sql($toassess_sql, [
            $USER->id,
            $USER->id,
            $peerassess->id,
            $USER->id,
            $peerassess->id
        ]);

        $toassess = array_map(function ($item) { return $item->name; }, $toassess_db);

        // Display user dashboard table
        echo "<div>";
        echo "<table class='generaltable'>";
        echo "<tr>";
        echo "<td width='30%'><b>Remaining groupmates to assess</b></td>";
        echo "<td>" . join("<br>", $toassess) . "</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td width='30%'><b>Groupmates who have not submitted</b></td>";
        echo "<td>" . "Waiting for Jun Yi" . "</td>";
        echo "</tr>";
        echo "</table>";
        echo "</div>";

        // Display a link to complete peerassess or resume.
        $completeurl = new moodle_url('/mod/peerassess/complete.php',
                ['id' => $id, 'courseid' => $courseid]);
        if ($startpage = $peerassesscompletion->get_resume_page()) {
            $completeurl->param('gopage', $startpage);
            $label = get_string('continue_the_form', 'peerassess');
        } else {
            $label = get_string('complete_the_form', 'peerassess');
        }
        echo html_writer::div(html_writer::link($completeurl, $label, array('class' => 'btn btn-secondary')), 'complete-peerassess');
    } else {
        // [!] (Ideally all) Peerassess was already submitted.
        echo $OUTPUT->notification(get_string('this_peerassess_is_already_submitted', 'peerassess'));
        // [!] Refactoring needed
        // Display user dashboard table
        echo "<div>";
        echo "<table class='generaltable'>";
        echo "<tr>";
        echo "<td width='30%'><b>Remaining groupmates to assess</b></td>";
        echo "<td>No group members left to assess.</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td width='30%'><b>Groupmates who have not submitted</b></td>";
        echo "<td>" . "Waiting for Jun Yi" . "</td>";
        echo "</tr>";
        echo "</table>";
        echo "</div>";
        // Release status of final grades to students.
        if ($finalgradesreleased == true){
            echo get_string('your_final_grade_is', 'peerassess', $finalgrades);
        }
        else {
            echo get_string('finalgradeshasnotbeenreleased', 'peerassess');
        }
        $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    }
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

