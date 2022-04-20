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
 * prints an analysed excel-spreadsheet of the peerassess
 *
 * @copyright Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */
global $CFG;
global $DB;
require_once("../../config.php");
require_once("lib.php");
require_once("$CFG->libdir/excellib.class.php");

$id = required_param('id', PARAM_INT); // Course module id.
$courseid = optional_param('courseid', '0', PARAM_INT);
$userid = optional_param('userid', false, PARAM_INT);


$url = new moodle_url('/mod/peerassess/breakdown_to_excel.php', array('id' => $id));
if ($courseid) {
    $url->param('courseid', $courseid);
}
$PAGE->set_url($url);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/peerassess:viewreports', $context);

$peerassess = $PAGE->activityrecord;

// Buffering any output. This prevents some output before the excel-header will be send.
ob_start();
ob_end_clean();

// Get the questions (item-names).
$peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $course->id);
if (!$items = $peerassessstructure->get_items(true)) {
    print_error('no_items_available_yet', 'peerassess', $cm->url);
}

//Get the effective groupmode of this course and module
if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode =  $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

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

$groupName = $DB->get_field('groups', 'name', array('id'=>$mygroupid, 'courseid'=>$course->id), $strictness=IGNORE_MISSING);
// Creating a workbook.
$filename = "Breakdown_" .clean_filename($groupName). ".xls";
$workbook = new MoodleExcelWorkbook($filename);

// Creating the worksheet.
error_reporting(0);
$worksheet1 = $workbook->add_worksheet();
error_reporting($CFG->debug);
$worksheet1->hide_gridlines();
$worksheet1->set_column(0, 0, 10);
$worksheet1->set_column(1, 1, 30);
$worksheet1->set_column(2, 20, 15);

// Creating the needed formats.
$xlsformats = new stdClass();
$xlsformats->head1 = $workbook->add_format(['bold' => 1, 'size' => 12]);
$xlsformats->head2 = $workbook->add_format(['align' => 'left', 'bold' => 1, 'bottum' => 2]);
$xlsformats->default = $workbook->add_format(['align' => 'left', 'v_align' => 'top']);
$xlsformats->value_bold = $workbook->add_format(['align' => 'left', 'bold' => 1, 'v_align' => 'top']);
$xlsformats->procent = $workbook->add_format(['align' => 'left', 'bold' => 1, 'v_align' => 'top', 'num_format' => '#,##0.00%']);

// Writing the table header.
$rowoffset1 = 0;
$worksheet1->write_string($rowoffset1, 0, userdate(time()), $xlsformats->head1);

$rowoffset1 ++;
$worksheet1->write_string($rowoffset1, 0,'Student\'s name', $xlsformats->head1);

//Writing item name.
$itemNames = get_item_name($peerassess);
$i = 1;
foreach ($itemNames as $itemName) {
    $worksheet1->write_string($rowoffset1, $i,$itemName, $xlsformats->head1);
    $i++;
}

$worksheet1->write_string($rowoffset1, $i,'Peerfactor', $xlsformats->head1);
$i++;

//Get each assignment'grade
$assignmentGrades = $DB->get_fieldset_sql('SELECT psa.assignmentid
                                                FROM  {peerassess_assignments} psa
                                                WHERE psa.peerassessid = '.$peerassess->id
                                                ,array('peerassessid'=>$peerassess->id));
foreach($assignmentGrades as $assignmentGrade){
    $worksheet1->write_string($rowoffset1, $i,'Assignment'.$assignmentGrade.'grade', $xlsformats->head1);
    $i++;

}


$students = peerassess_get_all_users_records($cm, $usedgroupid, '', false, false, true);
if (empty($students)) {
    $rowoffset1++;
    $worksheet1->write_string($rowoffset1, 0,get_string('noexistingparticipants', 'enrol'), $xlsformats->head1);
} else {

    foreach ($students as $student) {
        $rowoffset1++;
        $j = 0;
        $worksheet1->write_string($rowoffset1, $j,fullname($student), $xlsformats->head1);
        $j++;

        $totalrecords = peerassess_get_user_responses($peerassess, $student->id);
        if(empty($totalrecords)){
            for($k = $j; $k < count($itemNames); $k++){
                $worksheet1->write_string($rowoffset1, $k,'', $xlsformats->head1);
            }
        }else{
            foreach ($totalrecords as $completed) {
                $worksheet1->write_string($rowoffset1, $j,$completed, $xlsformats->head1);
                ++$j;
            }
        }

        $peerfactor = $DB->get_field('peerassess_peerfactors','peerfactor', array(
            'userid' => $student->id, 'peerassessid' => $peerassess->id));
            $worksheet1->write_string($rowoffset1, $j,$peerfactor, $xlsformats->head1);
            $j++;

        //data for assignments grade
        if(empty($assignmentresults = $DB->get_fieldset_sql('SELECT pfg.finalgradewithpa
                                                                FROM  {peerassess_finalgrades} pfg
                                                                WHERE pfg.peerassessid = '.$peerassess->id.' AND pfg.userid = '.$student->id
                                                                ,array('peerassessid'=>$peerassess->id)))){
            foreach($assignmentGrades as $assignmentgrade){
                $worksheet1->write_string($rowoffset1, $j,'', $xlsformats->head1);
                $j++;
            }
        }else{
            foreach($assignmentresults as $assignmentresult){
                $worksheet1->write_string($rowoffset1, $j,$assignmentresult, $xlsformats->head1);
                $j++;
            }
        }

    }
}

function get_item_name($peerassess){
    global $DB;

    $sql = "SELECT pi.name
                FROM {peerassess_item} pi
                WHERE pi.peerassess = $peerassess->id AND pi.typ != 'memberselect'";
    $itemNames = $DB->get_fieldset_sql($sql, array('peerassess'=> $peerassess->id));
    return $itemNames;


}

function peerassess_get_user_responses($peerassess, $studentid) {
    global $DB;

    $selectedUser = get_selected_user($peerassess, $studentid);
    $selectedRecord = get_user_completedId($peerassess, $studentid);
    $total = array();
    foreach($selectedRecord as $record){
        $params = array($record, $selectedUser);
        $sql = 'SELECT psv.value
                    FROM {peerassess_value} psv
                    WHERE psv.completed = ? AND psv.item != ?';

        $recordFound = $DB->get_fieldset_sql($sql, $params);

        $total += $recordFound;
    }

    return $total;

}

function get_selected_user($peerassess, $studentid) {
    global $DB;

    $params = array($peerassess->id);

    $sql = 'SELECT psi.id
                FROM {peerassess_item} psi
                WHERE psi.peerassess = ? AND psi.typ = "memberselect"';

    return $DB->get_field_sql($sql, $params, $strictness=IGNORE_MISSING);

}

function get_user_completedId($peerassess, $studentid) {
    global $DB;

    $params = array($peerassess->id);

    $sql = 'SELECT psv.completed
                FROM {peerassess_item} psi, {peerassess_value} psv
                WHERE psi.peerassess = ? AND psi.typ = "memberselect"
                    AND psv.item = psi.id AND psv.value =' . $studentid;

    return $DB->get_fieldset_sql($sql, $params);

}

$workbook->close();
