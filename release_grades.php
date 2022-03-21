<?php
// This file is part of a 3rd party created module for Moodle - http://moodle.org/
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
 * Release all final grades.
 *
 * @package    mod_peerassess
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);
$groupid = required_param('groupid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

$PAGE->set_url('/mod/peerassess/release_grades.php', ['id' => $cm->id, 'groupid' => $groupid]);

$peerassess = $DB->get_record('peerassess', ['id' => $cm->instance], '*', MUST_EXIST);

if ($groupid > 0) {
    $sql = 'peerassessid = :peerassessid AND groupid = :groupid AND COALESCE(timegraded) > 0 AND released = 0';
    $submissions = $DB->get_records_select('peerassess_submission', $sql, [
        'peerassessid' => $peerassess->id,
        'groupid' => $groupid
    ]);
} else {
    $sql = 'peerassessid = :peerassessid AND COALESCE(timegraded) > 0 AND released = 0';
    $submissions = $DB->get_records_select('peerassess_submission', $sql, ['peerassessid' => $peerassess->id]);
}

foreach ($submissions as $submission) {

    // Release the submission.
    $submission->released = time();
    $submission->releasedby = $USER->id;
    $DB->update_record('peerassess_submission', $submission);

    // Trigger the event.
    $params = [
        'objectid' => $submission->id,
        'context' => $context,
        'other' => [
            'groupid' => $submission->groupid
        ]
    ];
    $event = \mod_peerassess\event\grades_released::create($params);
    $event->add_record_snapshot('peerassess_submission', $submission);
    $event->trigger();
}

// Trigger the gradebook update.
// peerassess_update_grades($peerassess);

redirect(new moodle_url('view.php', ['id' => $cm->id]));
