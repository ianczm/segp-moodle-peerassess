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

function pa_get_question_count($peerassessid, $DB) {
	$question_count = $DB->get_record_sql("SELECT COUNT(i.id) AS 'question_count'
			FROM {peerassess_item} AS i
			WHERE i.peerassess = ?
			AND i.typ = 'multichoice'", [
				$peerassessid
			])->question_count;
    
    return $question_count;
}

function pa_get_question_max_score($peerassessid, $DB) {
	$presentations = $DB->get_records_sql("SELECT i.id, i.presentation
			FROM {peerassess_item} AS i
			WHERE i.peerassess = ?
			AND i.typ = 'multichoice'", [
				$peerassessid
			]);

	// get the last character of each presentation string as an integer
	// e.g. input element is "r>>>>>1 |2 |3 |4 |5"
	// then output element is 5
	$question_scores = array_map(function ($presentation) {return intval($presentation->presentation[-1]);}, $presentations);

    // print_object($question_scores);

	// add up the max score of each question found to get QuestionMaxScore
	return array_sum($question_scores);
}


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

function pa_get_num_received($peerassessid, $userid, $DB) {
    $num_received = $DB->get_record_sql("SELECT COUNT(DISTINCT v.completed) AS 'received_count'
            FROM {peerassess_value} AS v
            WHERE v.item = (
                SELECT i.id
                FROM {peerassess_item} AS i
                WHERE i.typ = 'memberselect'
                AND i.peerassess = ?
            )
            AND v.value = ?;", [
                $peerassessid,
                $userid
    ]);

    return $num_received->received_count;
}

function pa_get_scores_from_userid($peerassessid, $userid, $DB) {
    $pascores = $DB->get_records_sql("SELECT ROW_NUMBER() OVER() AS 'pa_id',
                i.id AS 'itemid',
                v.value AS 'pa_score'
            FROM {peerassess_value} AS v
            INNER JOIN {peerassess_item} AS i
                ON v.item = i.id
            WHERE v.completed IN (
                SELECT v.completed
                FROM {peerassess_value} AS v
                INNER JOIN {peerassess_item} AS i
                    ON v.item = i.id
                WHERE i.peerassess = ?
                AND i.typ = 'memberselect'
                AND v.value = ?
            )
            AND i.typ != 'memberselect';", [
                $peerassessid,
                $userid
    ]);

    $pascores = array_map(function ($item) { return $item->pa_score; }, $pascores);

    print_object($pascores);
    return $pascores;
}

function pa_calculate_all ($userids, $pascores, $peerassessid, $groupmark) {
    
    $totalscores = [];
    $interscores = [];
    $numsubmitted = 0;
    global $DB;

    $tablefg = 'mdl_peerassess_finalgrades';
    $tablepa = 'mdl_peerassess_peerfactor';

    // Calculate the sum of the peer scores for each student

    $userids = pa_get_userids($peerassessid, $DB);

    foreach ($userids as $memberid) {

        // returns an array of scores for each question for the recipient (memberid)
        $pascores = pa_get_scores_from_userid($peerassessid, $memberid, $DB);

        // create an array that stores the sum of pa scores for each recipient
        if (!isset($totalscores)) {
            $totalscores =[];
        }
        
        // returns the sum of pa scores or each recipient and stores it inside totalscores
        $totalscores[$memberid] = array_sum($pascores);
    }

    $maxscore = pa_get_question_max_score($peerassessid, $DB);
    $questioncount = pa_get_question_count($peerassessid, $DB);

    // // Calculate the fracscores and ensure the scores are submitted correctly
    // foreach ($userids as $memberid) {
    //     $gradesgiven = $totalscores[$memberid];
    //     $total = array_sum($gradesgiven);

    //     $fracscores[$memberid] = array_reduce(array_keys($gradesgiven), function($carry, $peerassessid) use ($total, $gradesgiven) {
    //         $grade = $gradesgiven[$peerassesssid];
    //         $carry[$peerassessid] = $total > 0 ? $grade / $total : 0;
    //         return $carry;
    //     }, []);

    //     $numsubmitted += !empty($fracscores[$memberid]) ? 1 : 0;
        
    // }

    // function pa_get_rmax () {
    //     $mform = $this->_form;
    //     $mform->addElement('number', 'pamaxrange', get_string('pamaxrange'));
    //     $mform->setType('pamaxrange', PARAM_NOTAGS);
    //     $mform->setDefault('pamaxrange', 'Please enter peer factor maximum range');
    // }

    $averagescores = array_map(function($totalscore, $memberid) use ($peerassessid, $DB) {
        $numreceived = pa_get_num_received($peerassessid, $memberid, $DB);
        return ($totalscore / $numreceived);
    }, $totalscores, $userids);

    $smax = max($averagescores);
    $smin = min($averagescores);
    
    $maxscore = pa_get_question_max_score($peerassessid, $DB);
    $questioncount = pa_get_question_count($peerassessid, $DB);

    //effectiverange (Smax - Smin) / questions * (interval input by lecturer)
    $rmax = 0.2;
    $effectiverange = (($smax - $smin) / ($maxscore - $questioncount) )* $rmax;

    print_object($totalscores);
    print_object($averagescores);
    print_object($effectiverange);

        // foreach ($userids as $memberid) {
            
        // }

        
//    // Initializing every student score at 0
//     $studentscores = array_reduce($userids, function($carry, $memberid) {
//         $carry[$memberid] = 0;
//         return $carry;
//     }, []);

//     // Inspect every student's score and add all the scores
//     foreach ($fracscores as $gradesgiven) {
//         foreach ($gradesgiven as $memberid => $fraction) {
//             $studentscores[$memberid] += $fraction;
//         }
//     }

//     // Applying peer factor
//     $nummembers =  count($userids);
//     $peerfactor = $numsubmitted > 0 ? $nummembers / $numsubmitted : 1;
//     $studentscores = array_map(function($grade) use ($peerfactor) {
//         return $grade * $peerfactor;
//     }, $studentscores);

//     print_object($studentscores);
//     return($studentscores);
    //$tablepa = $DB->insert_record($tablepa, $userids, $peerassessid, $peerfactor);

    // // Calculating the student's final grade with pa
    // $finalgradewithpa = array_map(function($score) use ($groupmark) {
    //     return max(0, min(100, $score * $groupmark));
    // }, $studentscores);


    //$tablefg = $DB->insert_record($tablefg, $userids, 'itemid', $finalgradewithpa, $peerassessid);

}
//End the page

pa_calculate_all($userids, $pascores, $peerassessid, $groupmark);

echo $OUTPUT->footer();

