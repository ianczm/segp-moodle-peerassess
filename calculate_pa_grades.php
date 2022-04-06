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


$PAGE->set_url(new moodle_url('/mod/peerassess/calculate_pa_grades.php'));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title('Grades: PA Calculation');

echo $OUTPUT->header();

echo '<h1>Calculating PA Grades</h1>';

echo $OUTPUT->footer();


/*global $DB;

$PAGE ->set_url(new moodle_url())

function get_name() {
    return get_string('assignment_submissions:grade', 'assignment');
}

function calculate ($grades, $groupmark, $paweighting =1, $selfgrade = false) {
    $id = array_keys($grades);
    $totalscores = [];
    $fracscores = [];
    $numsubmitted = 0;

    // Calculate the sum of the scores
    foreach ($id as $memberid) {
        foreach ($finalgrade as $graderid => $gradesgiven) {
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

    // Calculate the fractional scores and ensure the scores are submitted correctly
    foreach ($id as $memberid) {
        $gradesgiven = $totalscores [$memberid];
        $total = array_sum($gradesgiven);

        $fracscores[$memberid] = array_reduce(array_keys($gradesgiven), function($carry, $peerid) use ($total, $gradesgiven) {
            $grade = $gradesgiven[$peerid];
            $carry[$peerid] = $total > 0 ? $grade / $total : 0;
            return $carry;
        }, []);

        $numsubmitted += !empty($fracscores[$memberid]) ? 1 : 0;
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

    // Applying fudge factore
    $nummembers = count($id);
    $fudgefactor = $numsubmitted > 0 ? $nummembers / $numsubmitted : 1;
    $finalgradepa = array_map(function($grade) use ($fudgefactor) {
        return $grade * $fudgefactor;
    }, $finalgradepa);

    // Calculating the student's preliminary grade
    $prelimgrades = array_map(function($score) use ($groupmark) {
        return max(0, min(100, $score * $groupmak));
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

    return new \mod_peerassess\calculate_pa_grades($fracscores, $finalgradepa, $prelimgrades, $grade);
}
