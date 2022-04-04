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
//defined('MOODLE_INTERNAL') || die();
global $CFG;
global $DB;
require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/tablelib.php');

$current_tab = 'breakdown';

////////////////////////////////////////////////////////
//get the params
////////////////////////////////////////////////////////
$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', false, PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);
$perpage = optional_param('perpage', PEERASSESS_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$showall = optional_param('showall', false, PARAM_INT);  // should we show all users
$download = optional_param('download', '', PARAM_ALPHA); //allow download

////////////////////////////////////////////////////////
//get the objects
////////////////////////////////////////////////////////

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');

$baseurl = new moodle_url('/mod/peerassess/breakdown_per_group.php', array('id' => $cm->id));
$PAGE->set_url(new moodle_url($baseurl, array('userid' => $userid)));

$context = context_module::instance($cm->id);

require_login($course, true, $cm);
$peerassess = $PAGE->activityrecord;

require_capability('mod/peerassess:viewreports', $context);

// Print the page header.
navigation_node::override_active_url($baseurl);
$PAGE->set_heading($course->fullname);
$PAGE->set_title($peerassess->name);
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($peerassess->name));

require('tabs.php');

/// Print the main part of the page
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////

////////////////////////////////////////////////////////
/// Print the users with no responses
////////////////////////////////////////////////////////
//get the effective groupmode of this course and module
if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode =  $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}
$groupselect = groups_print_activity_menu($cm, $baseurl->out(), true);
$mygroupid = groups_get_activity_group($cm);

//get students in conjunction with groupmode
if ($groupmode > 0) {
    if ($mygroupid > 0) {
        $usedgroupid = $mygroupid;
    } else {
        $usedgroupid = false;
    }
} else {
    $usedgroupid = false;
}

// preparing the table for output
$breakdownbaseurl = new moodle_url('/mod/peerassess/breakdown_per_group.php');
$breakdownbaseurl->params(array('id'=>$id, 'showall'=>$showall));

//Getting the item name
$peerassess = $PAGE->activityrecord;
$itemNames = get_item_name($peerassess);

$tablecolumns = array('userpic', 'fullname');
$tableheaders = array(get_string('userpic'), get_string('fullnameuser'));

$table = new flexible_table('peerassess-breakdownpergroup'.$course->id);

$selectedGroup = $DB->get_field('groups', 'name', array('id' => $usedgroupid
), $strictness=IGNORE_MISSING);

if ($table->is_downloading($download, 'Breakdown_Of_'.$selectedGroup,
                    'Breakdown_Of_'.$selectedGroup)){
                        //Remove user pic when download
                        array_shift($tablecolumns);
                        array_shift($tableheaders);
                    }

foreach ($itemNames as $itemName) {
    $tablecolumns[] = $itemName;
    $tableheaders[] = $itemName;
}

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($breakdownbaseurl->out());

$table->sortable(true, 'lastname', SORT_DESC);
$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'showentrytable');
$table->set_attribute('class', 'generaltable generalbox');
$table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));

$table->no_sorting('select');
$table->no_sorting('status');

$table->setup();

if ($table->get_sql_sort()) {
    $sort = $table->get_sql_sort();
} else {
    $sort = '';
}

$matchcount = peerassess_count_incomplete_users($cm, $usedgroupid) + peerassess_count_complete_users($cm, $usedgroupid);
$table->initialbars(false);

if ($showall) {
    $startpage = false;
    $pagecount = false;
} else {
    $table->pagesize($perpage, $matchcount);
    $startpage = $table->get_page_start();
    $pagecount = $table->get_page_size();
}

// Return students record including if they started or not the peerassess.
$students = peerassess_get_all_users_records($cm, $usedgroupid, $sort, $startpage, $pagecount, true);
//####### viewreports-start
//print the list of students
echo $OUTPUT->heading(get_string('members_in_current_group', 'peerassess', $matchcount), 4);
echo isset($groupselect) ? $groupselect : '';
echo '<div class="clearer"></div>';

if (empty($students)) {
    echo $OUTPUT->notification(get_string('noexistingparticipants', 'enrol'));
} else {

    foreach ($students as $student) {
        //userpicture and link to the profilepage
        $profileurl = $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course='.$course->id;
        $profilelink = '<strong><a href="'.$profileurl.'">'.fullname($student).'</a></strong>';
        $data = array($OUTPUT->user_picture($student, array('courseid' => $course->id)), $profilelink);

        //Get and print completed student's response
        $completeds = peerassess_get_user_responses($peerassess, $student->id);
        if(empty($completeds)){
            for($i = 0; $i < count($itemNames); $i++){
                $data[] = get_string('not_started', 'peerassess');
            }
        }else{
            foreach ($completeds as $completed) {
                $data[] = $completed;
            }
        }

        $table->add_data($data);
    }
    $table->finish_output();

    $allurl = new moodle_url($breakdownbaseurl);

    if ($showall) {
        $allurl->param('showall', 0);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showperpage', '', PEERASSESS_DEFAULT_PAGE_COUNT)),
                                    array(), 'showall');

    } else if ($matchcount > 0 && $perpage < $matchcount) {
        $allurl->param('showall', 1);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showall', '', $matchcount)), array(), 'showall');
    }

}

function get_item_name($peerassess){
    global $DB;

    $sql = "SELECT pi.name
                FROM {peerassess_item} pi";
    $itemNames = $DB->get_fieldset_sql($sql, array('peerassess'=> $peerassess->id));
    return $itemNames;


}

function peerassess_get_user_responses($peerassess, $studentid) {
    global $DB;

    $params = array(PEERASSESS_ANONYMOUS_NO, $studentid, $peerassess->id);


    $sql = 'SELECT psv.value
                FROM {peerassess_completed} psc, {peerassess_value} psv, {peerassess_item} psi
                WHERE anonymous_response = ? AND psc.userid = ? AND psc.peerassess = ?
                        AND psi.id = psv.item AND psc.id = psv.completed
                        ';

    return $DB->get_fieldset_sql($sql, $params);




}

// Finish the page.
echo $OUTPUT->footer();



