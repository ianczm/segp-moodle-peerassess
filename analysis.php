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
 * shows an analysed view of peerassess
 *
 * @copyright Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

require_once("../../config.php");
require_once("lib.php");

$current_tab = 'analysis';

$id = required_param('id', PARAM_INT);  // Course module id.

$url = new moodle_url('/mod/peerassess/analysis.php', array('id'=>$id));
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
require_course_login($course, true, $cm);

$peerassess = $PAGE->activityrecord;
$peerassessstructure = new mod_peerassess_structure($peerassess, $cm);

$context = context_module::instance($cm->id);

if (!$peerassessstructure->can_view_analysis()) {
    print_error('error');
}

/// Print the page header

$PAGE->set_heading($course->fullname);
$PAGE->set_title($peerassess->name);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerassess->name));

/// print the tabs
require('tabs.php');


//get the groupid
$mygroupid = groups_get_activity_group($cm, true);
groups_print_activity_menu($cm, $url);

// Button "Export to excel".
if (has_capability('mod/peerassess:viewreports', $context) && $peerassessstructure->get_items()) {
    echo $OUTPUT->container_start('form-buttons');
    $aurl = new moodle_url('/mod/peerassess/analysis_to_excel.php', ['sesskey' => sesskey(), 'id' => $id]);
    echo $OUTPUT->single_button($aurl, get_string('export_to_excel', 'peerassess'));
    echo $OUTPUT->container_end();
}

// Show the summary.
$summary = new mod_peerassess\output\summary($peerassessstructure, $mygroupid);
echo $OUTPUT->render_from_template('mod_peerassess/summary', $summary->export_for_template($OUTPUT));

// Get the items of the peerassess.
$items = $peerassessstructure->get_items(true);

$check_anonymously = true;
if ($mygroupid > 0 AND $peerassess->anonymous == FEEDBACK_ANONYMOUS_YES) {
    $completedcount = $peerassessstructure->count_completed_responses($mygroupid);
    if ($completedcount < FEEDBACK_MIN_ANONYMOUS_COUNT_IN_GROUP) {
        $check_anonymously = false;
    }
}

echo '<div>';
if ($check_anonymously) {
    // Print the items in an analysed form.
    foreach ($items as $item) {
        $itemobj = peerassess_get_item_class($item->typ);
        $printnr = ($peerassess->autonumbering && $item->itemnr) ? ($item->itemnr . '.') : '';
        $itemobj->print_analysed($item, $printnr, $mygroupid);
    }
} else {
    echo $OUTPUT->heading_with_help(get_string('insufficient_responses_for_this_group', 'peerassess'),
                                    'insufficient_responses',
                                    'peerassess', '', '', 3);
}
echo '</div>';

echo $OUTPUT->footer();

