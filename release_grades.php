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
// $finalgradesreleased = optional_param('finalgradesreleased', false, PARAM_BOOL);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'peerassess');
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$PAGE->set_url('/mod/peerassess/release_grades.php', ['id' => $cm->id]);

// Flag that final grades has been released
$finalgradesreleased = true;

// Grade release logic here
// 1. Verify that peer factor calculations have been completed
// 2. Verify that all final grades have already been calculated
// 3. Display final grades on user dashboard
// 4. Record final grades in gradebook

redirect('view.php?id='.$id);
exit;