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
require_once($CFG->libdir.'/tablelib.php');

$current_tab = 'breakdown';

////////////////////////////////////////////////////////
//get the params
////////////////////////////////////////////////////////
$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', false, PARAM_INT);
$showcompleted = optional_param('showcompleted', false, PARAM_INT);
$deleteid = optional_param('delete', null, PARAM_INT);
$courseid = optional_param('courseid', null, PARAM_INT);
$perpage = optional_param('perpage', peerassess_DEFAULT_PAGE_COUNT, PARAM_INT);  // how many per page
$showall = optional_param('showall', false, PARAM_INT);  // should we show all users

////////////////////////////////////////////////////////
//get the objects
////////////////////////////////////////////////////////

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');

$baseurl = new moodle_url('/mod/peerassess/breakdown_per_group.php', array('id' => $cm->id));
$PAGE->set_url(new moodle_url($baseurl, array('userid' => $userid, 'showcompleted' => $showcompleted,
        'delete' => $deleteid,'showall'=>$showall)));

$context = context_module::instance($cm->id);

require_login($course, true, $cm);
$peerassess = $PAGE->activityrecord;

require_capability('mod/peerassess:viewreports', $context);

if ($deleteid) {
    // This is a request to delete a reponse.
    require_capability('mod/peerassess:deletesubmissions', $context);
    require_sesskey();
    $peerassessstructure = new mod_peerassess_completion($peerassess, $cm, 0, true, $deleteid);
    peerassess_delete_completed($peerassessstructure->get_completed(), $peerassess, $cm);
    redirect($baseurl);
} else if ($showcompleted || $userid) {
    // Viewing individual response.
    $peerassessstructure = new mod_peerassess_completion($peerassess, $cm, 0, true, $showcompleted, $userid);
} else {
    // Viewing list of reponses.
    $peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $courseid);
}

$responsestable = new mod_peerassess_responses_table($peerassessstructure);
$anonresponsestable = new mod_peerassess_responses_anon_table($peerassessstructure);

if ($responsestable->is_downloading()) {
    $responsestable->download();
}
if ($anonresponsestable->is_downloading()) {
    $anonresponsestable->download();
}

// Process course select form.
$courseselectform = new mod_peerassess_course_select_form($baseurl, $peerassessstructure, $peerassess->course == SITEID);
if ($data = $courseselectform->get_data()) {
    redirect(new moodle_url($baseurl, ['courseid' => $data->courseid]));
}
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

if ($userid || $showcompleted) {
    // Print the response of the given user.
    $completedrecord = $peerassessstructure->get_completed();

    if ($userid) {
        $usr = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        $responsetitle = userdate($completedrecord->timemodified) . ' (' . fullname($usr) . ')';
    } else {
        $responsetitle = get_string('response_nr', 'peerassess') . ': ' .
                $completedrecord->random_response . ' (' . get_string('anonymous', 'peerassess') . ')';
    }

    echo $OUTPUT->heading($responsetitle, 4);

    $form = new mod_peerassess_complete_form(mod_peerassess_complete_form::MODE_VIEW_RESPONSE,
            $peerassessstructure, 'peerassess_viewresponse_form');
    $form->display();

    list($prevresponseurl, $returnurl, $nextresponseurl) = $userid ?
            $responsestable->get_reponse_navigation_links($completedrecord) :
            $anonresponsestable->get_reponse_navigation_links($completedrecord);

    echo html_writer::start_div('response_navigation');

    $responsenavigation = [
        'col1content' => '',
        'col2content' => html_writer::link($returnurl, get_string('back'), ['class' => 'back_to_list']),
        'col3content' => '',
    ];

    if ($prevresponseurl) {
        $responsenavigation['col1content'] = html_writer::link($prevresponseurl, get_string('prev'), ['class' => 'prev_response']);
    }

    if ($nextresponseurl) {
        $responsenavigation['col3content'] = html_writer::link($nextresponseurl, get_string('next'), ['class' => 'next_response']);
    }

    echo $OUTPUT->render_from_template('core/columns-1to1to1', $responsenavigation);
    echo html_writer::end_div();

} else {
    // Print the list of responses.
    $courseselectform->display();

    // Show non-anonymous responses (always retrieve them even if current peerassess is anonymous).
    $totalrows = $responsestable->get_total_responses_count();
    if (!$peerassessstructure->is_anonymous() || $totalrows) {
        echo $OUTPUT->heading(get_string('non_anonymous_entries', 'peerassess', $totalrows), 4);
        $responsestable->display();
    }

    // Show anonymous responses (always retrieve them even if current peerassess is not anonymous).
    $peerassessstructure->shuffle_anonym_responses();
    $totalrows = $anonresponsestable->get_total_responses_count();
    if ($peerassessstructure->is_anonymous() || $totalrows) {
        echo $OUTPUT->heading(get_string('anonymous_entries', 'peerassess', $totalrows), 4);
        $anonresponsestable->display();
    }

}

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
//$url = new moodle_url('/mod/peerassess/breakdown_per_down.php', array('id'=>$cm->id));
$groupselect = groups_print_activity_menu($cm, $baseurl->out(), true);
$mygroupid = groups_get_activity_group($cm);

// preparing the table for output
//$nonresponbaseurl = new moodle_url('/mod/peerassess/breakdown_per_down.php');
//$nonresponbaseurl->params(array('id'=>$id, 'showall'=>$showall));

//$baseurl->params(array('id'=>$id, 'showall'=>$showall));

$tablecolumns = array('userpic', 'fullname', 'status');
$tableheaders = array(get_string('userpic'), get_string('fullnameuser'), get_string('status'));

$table = new flexible_table('peerassess-nonrespondents-'.$course->id);

$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl);

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

$matchcount = peerassess_count_incomplete_users($cm, $usedgroupid);
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
$students = peerassess_get_incomplete_users($cm, $usedgroupid, $sort, $startpage, $pagecount, true);
//####### viewreports-start
//print the list of students
//echo $OUTPUT->heading(get_string('non_respondents_students', 'peerassess', $matchcount), 4);
//echo isset($groupselect) ? $groupselect : '';
//echo '<div class="clearer"></div>';

if (empty($students)) {
    echo $OUTPUT->notification(get_string('noexistingparticipants', 'enrol'));
} else {

    foreach ($students as $student) {
        //userpicture and link to the profilepage
        $profileurl = $CFG->wwwroot.'/user/view.php?id='.$student->id.'&amp;course='.$course->id;
        $profilelink = '<strong><a href="'.$profileurl.'">'.fullname($student).'</a></strong>';
        $data = array($OUTPUT->user_picture($student, array('courseid' => $course->id)), $profilelink);

        if ($student->peerassessstarted) {
            $data[] = get_string('started', 'peerassess');
        } else {
            $data[] = get_string('not_started', 'peerassess');
        }
        $table->add_data($data);
    }
    $table->print_html();

    $allurl = new moodle_url($baseurl);

    if ($showall) {
        $allurl->param('showall', 0);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showperpage', '', peerassess_DEFAULT_PAGE_COUNT)),
                                    array(), 'showall');

    } else if ($matchcount > 0 && $perpage < $matchcount) {
        $allurl->param('showall', 1);
        echo $OUTPUT->container(html_writer::link($allurl, get_string('showall', '', $matchcount)), array(), 'showall');
    }

}

// Finish the page.
echo $OUTPUT->footer();



