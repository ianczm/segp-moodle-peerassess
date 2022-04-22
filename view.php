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


// // Show description.
echo $OUTPUT->box_start('generalbox peerassess_description');

$options = (object)array('noclean' => true);
echo format_module_intro('peerassess', $peerassess, $cm->id);
echo $OUTPUT->box_end();

echo $OUTPUT->heading(get_string('overview', 'peerassess'), 3);

// Get flag of grade released status
// $finalgradesreleased = get_grades_release_status();

//show some infos to the peerassess
if (has_capability('mod/peerassess:edititems', $context)) {

    //get the groupid
    $groupselect = groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/peerassess/view.php?id='.$cm->id, true);
    $mygroupid = groups_get_activity_group($cm);

    //echo $groupselect.'<div class="clearer">&nbsp;</div>';
    $summary = new mod_peerassess\output\summary($peerassesscompletion, $mygroupid);
    echo $OUTPUT->render_from_template('mod_peerassess/summary', $summary->export_for_template($OUTPUT));

    if ($pageaftersubmit = $peerassesscompletion->page_after_submit()) {
        echo $OUTPUT->heading(get_string("page_after_submit", "peerassess"), 3);
        echo $OUTPUT->box($pageaftersubmit, 'generalbox peerassess_after_submit');
    }

    echo $OUTPUT->box_start('generalbox boxaligncenter');
    $finalgradewithpaurl = new moodle_url('/mod/peerassess/calculate_pa_grades.php', ['id' => $cm->id, 'peerassess' => $peerassess->id]);
    echo html_writer::div(html_writer::link($finalgradewithpaurl, get_string("myfinalgradewithpa", 'peerassess'), array('class' => 'btn btn-secondary')));
    echo $OUTPUT->box_end();

    echo $OUTPUT->box_start('generalbox boxaligncenter');
    $releasegradesurl = new moodle_url('/mod/peerassess/release_grades.php', ['id' => $cm->id, 'peerassess' => $peerassess->id]);
    echo html_writer::div(html_writer::link($releasegradesurl, get_string("releaseallgradesforallgroups", 'peerassess'), array('class' => 'btn btn-secondary')));
    echo $OUTPUT->box_end();
}

// Get and format final grades
$showfinalgrades = pa_get_showfinalgrades_flag($peerassess->id, $DB);
$finalgrades = pa_get_user_finalgrades($USER->id, $peerassess->id, $DB);
if (!empty($finalgrades)) {
    // If final grades exist
    $finalgrades = array_map(function($finalgrade) {
        return "<b>" . $finalgrade->name . ":</b> " . number_format($finalgrade->grade, 2);
    }, $finalgrades);
} else {
    // If final grades do not exist
    $finalgrades = ["Peer factor cannot be applied on your grades, please contact the administrator."];
}

// Get remaining groupmates to assess

$toassess = pa_get_members_to_assess($USER->id, $COURSE->id, $peerassess->id, $DB);
$toassess = array_map(function ($item) { return $item->name; }, $toassess);

// Get groupmates who have not completed the peerassess
$remaining = pa_get_non_complete_members($USER->id, $COURSE->id, $peerassess->id, $DB);
$remaining = array_map(function ($item) { return $item->name; }, $remaining);

// Display user dashboard table

echo "<div>";
echo "<table class='generaltable'>";
echo "<tr>";
echo "<td style='width: 30%;'><b>Assignment Grades:</b></td>";
echo "<td>" . ($showfinalgrades ? join("<br>", $finalgrades) : get_string('finalgradeshasnotbeenreleased', 'peerassess')) . "</td>";
echo "<tr>";
echo "<td style='width: 30%;'><b>Remaining groupmates to assess:</b></td>";
echo "<td>" . (empty($toassess) ? "You have completed the peer assessment." : join(",<br>", $toassess)) . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td style='width: 30%;'><b>Groupmates who have not completed peer assessment:</b></td>";
echo "<td>" . (empty($remaining) ? "All groupmates have completed their peer assessment." : join(",<br>", $remaining)) . "</td>";
echo "</tr>";
echo "</table>";
echo "</div>";

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
        $OUTPUT->continue_button(course_get_url($courseid ?: $course->id));
    }
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

function pa_get_showfinalgrades_flag($peerassessid, $DB) {
    $res = $DB->get_record('peerassess', ['id' => $peerassessid], 'showfinalgrades');
    return $res->showfinalgrades;
}

function pa_get_members_to_assess($userid, $courseid, $peerassessid, $DB) {
    $toassess_sql =
        "SELECT u.id, CONCAT(u.firstname, ' ', u.lastname) as 'name'
        FROM {user} as u, {groups_members} as gm
        WHERE gm.groupid = (
            SELECT gm.groupid
            FROM mdl_groups_members AS gm
            INNER JOIN mdl_groups AS g
                ON g.id = gm.groupid
            WHERE gm.userid = ?
            AND g.courseid = ?
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
        $userid,
        $courseid,
        $userid,
        $peerassessid,
        $userid,
        $peerassessid
    ]);

    return $toassess_db;
}

function pa_get_non_complete_members($userid, $courseid, $peerassessid, $DB) {
    $remaining_sql =
        "SELECT
            u.id AS 'userid',
            CONCAT(u.firstname, ' ', u.lastname) AS 'name',
            gm.groupid,
            COALESCE(sc.submission_count, 0) AS 'final_submission_count',
            mc.member_count
        FROM {user} AS u
        INNER JOIN {groups_members} AS gm
            ON u.id = gm.userid
        LEFT OUTER JOIN (
            SELECT c.userid, COUNT(c.userid) AS 'submission_count'
            FROM {peerassess_completed} AS c
            WHERE c.peerassess = ?
            GROUP BY c.userid
        ) AS sc
            ON u.id = sc.userid
        INNER JOIN (
            SELECT gm.groupid, COUNT(gm.groupid) AS 'member_count'
            FROM {groups_members} AS gm
            GROUP BY gm.groupid
        ) AS mc
            ON gm.groupid = mc.groupid
        WHERE gm.groupid = (
            SELECT gm.groupid
            FROM {groups_members} AS gm
            INNER JOIN {groups} AS g
                ON g.id = gm.groupid
            WHERE gm.userid = ?
            AND g.courseid = ?
        )
        AND COALESCE(sc.submission_count, 0) < mc.member_count - 1;";

    $remaining_db = $DB->get_records_sql($remaining_sql, [
        $peerassessid,
        $userid,
        $courseid
    ]);

    return $remaining_db;
}

function pa_get_user_finalgrades($userid, $peerassessid, $DB) {
    $sql =
        "SELECT fg.itemid, a.name, fg.finalgradewithpa AS 'grade'
        FROM moodle.mdl_peerassess_finalgrades AS fg
        INNER JOIN moodle.mdl_assign AS a
            ON fg.itemid = a.id
        WHERE fg.peerassessid = ?
        AND fg.userid = ?;";

    $params = [
        $peerassessid,
        $userid
    ];

    $records = $DB->get_records_sql($sql, $params);

    return $records;
}