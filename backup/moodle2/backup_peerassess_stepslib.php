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
 * Define all the backup steps that will be used by the backup_peerassess_activity_task
 */

/**
 * Define the complete peerassess structure for backup, with file and id annotations
 */
class backup_peerassess_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $peerassess = new backup_nested_element('peerassess', array('id'), array(
                                                'name',
                                                'intro',
                                                'introformat',
                                                'anonymous',
                                                'email_notification',
                                                'multiple_submit',
                                                'autonumbering',
                                                'site_after_submit',
                                                'page_after_submit',
                                                'page_after_submitformat',
                                                'publish_stats',
                                                'timeopen',
                                                'timeclose',
                                                'timemodified',
                                                'completionsubmit'));

        $completeds = new backup_nested_element('completeds');

        $completed = new backup_nested_element('completed', array('id'), array(
                                                'userid',
                                                'timemodified',
                                                'random_response',
                                                'anonymous_response',
                                                'courseid'));

        $items = new backup_nested_element('items');

        $item = new backup_nested_element('item', array('id'), array(
                                                'template',
                                                'name',
                                                'label',
                                                'presentation',
                                                'typ',
                                                'hasvalue',
                                                'position',
                                                'required',
                                                'dependitem',
                                                'dependvalue',
                                                'options'));

        $values = new backup_nested_element('values');

        $value = new backup_nested_element('value', array('id'), array(
                                                'item',
                                                'template',
                                                'completed',
                                                'value',
                                                'course_id'));

        // Build the tree
        $peerassess->add_child($items);
        $items->add_child($item);

        $peerassess->add_child($completeds);
        $completeds->add_child($completed);

        $completed->add_child($values);
        $values->add_child($value);

        // Define sources
        $peerassess->set_source_table('peerassess', array('id' => backup::VAR_ACTIVITYID));

        $item->set_source_table('peerassess_item', array('peerassess' => backup::VAR_PARENTID));

        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $completed->set_source_sql('
                SELECT *
                  FROM {peerassess_completed}
                 WHERE peerassess = ?',
                array(backup::VAR_PARENTID));

            $value->set_source_table('peerassess_value', array('completed' => backup::VAR_PARENTID));
        }

        // Define id annotations

        $completed->annotate_ids('user', 'userid');

        // Define file annotations

        $peerassess->annotate_files('mod_peerassess', 'intro', null); // This file area hasn't itemid
        $peerassess->annotate_files('mod_peerassess', 'page_after_submit', null); // This file area hasn't itemid

        $item->annotate_files('mod_peerassess', 'item', 'id');

        // Return the root element (peerassess), wrapped into standard activity structure
        return $this->prepare_activity_structure($peerassess);
    }

}
