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
 * @package    mod_peerassess
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_peerassess_activity_task
 */

/**
 * Structure step to restore one peerassess activity
 */
class restore_peerassess_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('peerassess', '/activity/peerassess');
        $paths[] = new restore_path_element('peerassess_item', '/activity/peerassess/items/item');
        if ($userinfo) {
            $paths[] = new restore_path_element('peerassess_completed', '/activity/peerassess/completeds/completed');
            $paths[] = new restore_path_element('peerassess_value', '/activity/peerassess/completeds/completed/values/value');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_peerassess($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        // insert the peerassess record
        $newitemid = $DB->insert_record('peerassess', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_peerassess_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->peerassess = $this->get_new_parentid('peerassess');

        $newitemid = $DB->insert_record('peerassess_item', $data);
        $this->set_mapping('peerassess_item', $oldid, $newitemid, true); // Can have files
    }

    protected function process_peerassess_completed($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->peerassess = $this->get_new_parentid('peerassess');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($this->task->is_samesite() && !empty($data->courseid)) {
            $data->courseid = $data->courseid;
        } else if ($this->get_courseid() == SITEID) {
            $data->courseid = SITEID;
        } else {
            $data->courseid = 0;
        }

        $newitemid = $DB->insert_record('peerassess_completed', $data);
        $this->set_mapping('peerassess_completed', $oldid, $newitemid);
    }

    protected function process_peerassess_value($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->completed = $this->get_new_parentid('peerassess_completed');
        $data->item = $this->get_mappingid('peerassess_item', $data->item);
        if ($this->task->is_samesite() && !empty($data->course_id)) {
            $data->course_id = $data->course_id;
        } else if ($this->get_courseid() == SITEID) {
            $data->course_id = SITEID;
        } else {
            $data->course_id = 0;
        }

        $newitemid = $DB->insert_record('peerassess_value', $data);
        $this->set_mapping('peerassess_value', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;
        // Add peerassess related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_peerassess', 'intro', null);
        $this->add_related_files('mod_peerassess', 'page_after_submit', null);
        $this->add_related_files('mod_peerassess', 'item', 'peerassess_item');

        // Once all items are restored we can set their dependency.
        if ($records = $DB->get_records('peerassess_item', array('peerassess' => $this->task->get_activityid()))) {
            foreach ($records as $record) {
                // Get new id for dependitem if present. This will also reset dependitem if not found.
                $record->dependitem = $this->get_mappingid('peerassess_item', $record->dependitem);
                $DB->update_record('peerassess_item', $record);
            }
        }
    }
}
