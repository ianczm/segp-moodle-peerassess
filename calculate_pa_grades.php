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

$id = required_param('id', PARAM_INT);
$grades = optional_param('grades', false, PARAM_INT);
$assignmentid = optional_param('assignmentid', false, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
$finalgradewithpaurl = new moodle_url('/mod/peerassess/calculate_pa_grades.php', ['id' => $cm->id]);

$PAGE->set_url(new moodle_url($finalgradewithpaurl, array('id' => $id, 'grades' => $grades,
        'assignmentid' => $assignmentid)));

$context = context_module::instance($cm->id);
require_login($course, true, $cm);
$peerassess = $PAGE->activityrecord;

require_capability('mod/peerassess:viewreports', $context);





function get_name() {
    return get_string('assignment_submissions:grade', 'assignment');
}

function calculate ($grades, $peerassessmark, $paweighting =1) {
    
    $id = array_keys($grades);
    $totalscores = [];
    $fracscores = [];
    $numsubmitted = 0;
    global $DB;

    $id = $DB->get_records_sql('SELECT userid FROM mdl_assign_grades');
    $grades = $DB->get_records_sql('SELECT grade FROM mdl_assign_grades');
    $tablefg = 'mdl_peerassess_finalgrades';
    $tablepa = 'mdl_peerassess_peerfactor';

    // Calculate the sum of the scores
    foreach ($id as $memberid) {
        foreach ($grades as $graderid => $gradesgiven) {
            if (!isset($totalscores[$graderid])) {
                $totalscores[$graderid] =[];
            }

            if (isset($gradesgiven[$memberid])) {
                $sum = array_reduce($gradesgiven[$memberid], function($carry, $item){
                    $carry += $item;
                    return $carry;
                });

                $totalscores[$graderid][$memberid] = $sum;
            }
        }
    }

    // Calculate the peer scores and ensure the scores are submitted correctly
    foreach ($id as $memberid) {
        $gradesgiven = $totalscores [$memberid];
        $total = array_sum($gradesgiven);

        $fracscores[$memberid] = array_reduce(array_keys($gradesgiven), function($carry, $peerid) use ($total, $gradesgiven) {
            $grade = $gradesgiven[$peerid];
            $carry[$peerid] = $total > 0 ? $grade / $total : 0;
            return $carry;
        }, []);

        $numsubmitted += !empty($fracscores[$memberid]) ? 1 : 0;
        $respa = $DB->insert_record($tablepa,'$userid', 'peerassessid', 'peerfactor');

    }

    // Initializing every student score at 0
    $finalgradepa = array_reduce($id, function($carry, $memberid) {
        $carry[$memberid] = 0;
        return $carry;
    }, []);

    // Inspect every student's score and add all the scores
    foreach ($fracscores as $gradesgiven) {
        foreach ($gradesgiven as $memberid => $fraction) {
            $finalgradepa[$memberid] += $fraction;
        }
    }

    // Applying peer factor
    $nummembers = count($id);
    $peerfactor = $numsubmitted > 0 ? $nummembers / $numsubmitted : 1;
    $finalgradepa = array_map(function($grade) use ($peerfactor) {
        return $grade * $peerfactor;
    }, $finalgradepa);

    // Calculating the student's preliminary grade
    $prelimgrades = array_map(function($score) use ($groupmark) {
        return max(0, min(100, $score * $groupmark));
    }, $finalgradepa);

    // Calculate all the grades again
    $grades = array_reduce ($id, function ($carry, $memberid) use ($finalgradepa, $groupmark, $paweighting) {
        $score = $finalgradepa[$memberid];

        $adjustedgroupmark = $groupmark * $paweighting;
        $automaticgrade = $groupmark - $adjustedgroupmark;
        $grade = max(0, min(100, $automaticgrade + ($score * $adjustedgroupmark)));

        $carry[$memberid] = $grade;
        return $carry;
    }, []);

    $resfg = $DB->insert_record($tablefg,'$userid', '$itemid', 'finalgradewithpa', 'peerassessid');
    return new \mod_peerassess\calculate_pa_grades($fracscores, $finalgradepa, $prelimgrades, $grade);
}