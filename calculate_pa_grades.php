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

$cmid = required_param('id', PARAM_INT);
$grades = optional_param('grades', false, PARAM_INT);
$assignmentid = optional_param('assignmentid', false, PARAM_INT);

$peerassessid = $DB->get_record_sql("SELECT cm.instance
        FROM {course_modules} AS cm
        WHERE cm.id = ? ;", [
            $cmid
        ])->instance;

$finalgradewithpaurl = new moodle_url('/mod/peerassess/calculate_pa_grades.php');
$PAGE->set_url(new moodle_url($finalgradewithpaurl));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('PA Calculation');

echo $OUTPUT->header();

function pa_get_userids($peerassessid, $DB) {
    $userids = $DB->get_records_sql("SELECT DISTINCT v.value AS 'userid'
        FROM {peerassess_value} AS v
        WHERE v.item = (
            SELECT i.id
            FROM {peerassess_item} AS i
            WHERE i.typ = 'memberselect'
            AND i.peerassess = ?
        );", [
            $peerassessid
        ]);

    $userids = array_map(function ($item) { return $item->userid; }, $userids);



    return $userids;
}

function pa_get_scores_from_userid($peerassessid, $userid, $DB) {
    $pascores = $DB->get_records_sql("SELECT ROW_NUMBER() OVER() AS 'question',
                v.value AS 'pa_score'
            FROM {peerassess_value} AS v
            INNER JOIN {peerassess_item} AS i
                ON v.item = i.id
            WHERE v.completed = (
                SELECT v.completed
                FROM {peerassess_value} AS v
                INNER JOIN {peerassess_item} AS i
                    ON v.item = i.id
                WHERE i.peerassess = ?
                AND v.value = ?
            )
            AND i.typ != 'memberselect';", [
                $peerassessid,
                $userid
            ]);

    $pascores = array_map(function ($item) { return $item->pa_score; }, $pascores);

    
    
    return $pascores;
}

function pa_calculate ($userids, $pascores, $peerassessid) {
    
    $totalscores = [];
    $fracscores = [];
    $numsubmitted = 0;
    global $DB;

    $tablefg = 'mdl_peerassess_finalgrades';
    $tablepa = 'mdl_peerassess_peerfactor';

    // Calculate the sum of the peer scores

    $userids = pa_get_userids($peerassessid, $DB);

    foreach ($userids as $memberid) {
        $pascores = pa_get_scores_from_userid($peerassessid, $memberid, $DB);

        foreach ($pascores as $graderid => $gradesgiven) { 
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
    foreach ($userids as $memberid) {
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
        return($fracscores);
        
//     // Initializing every student score at 0
//     $finalgradepa = array_reduce($id, function($carry, $memberid) {
//         $carry[$memberid] = 0;
//         return $carry;
//     }, []);

//     // Inspect every student's score and add all the scores
//     foreach ($fracscores as $gradesgiven) {
//         foreach ($gradesgiven as $memberid => $fraction) {
//             $finalgradepa[$memberid] += $fraction;
//         }
//     }

//     // Applying peer factor
//     $nummembers = count($id);
//     $peerfactor = $numsubmitted > 0 ? $nummembers / $numsubmitted : 1;
//     $finalgradepa = array_map(function($grade) use ($peerfactor) {
//         return $grade * $peerfactor;
//     }, $finalgradepa);

//     // Calculating the student's preliminary grade
//     $prelimgrades = array_map(function($score) use ($groupmark) {
//         return max(0, min(100, $score * $groupmak));
//     }, $finalgradepa);


//     $resfg = $DB->insert_record($tablefg,'userid', 'itemid', 'finalgradewithpa', 'peerassessid');
//     return new \mod_peerassess\calculate_pa_grades($fracscores, $finalgradepa, $prelimgrades, $grade);
// }

    print_object($totalscores);
    return $totalscores;
}
//End the page

pa_calculate($userids, $pascores, $peerassessid);

echo $OUTPUT->footer();
