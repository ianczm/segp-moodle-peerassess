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
 * print the form to add or edit a peerassess-instance
 *
 * @author Andreas Grabs
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

//It must be included from a Moodle page
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peerassess_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $COURSE;

        $editoroptions = peerassess_get_editor_options();

        $mform    =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        $mform->addElement('text', 'name', get_string('name', 'peerassess'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        $this->standard_intro_elements(get_string('description', 'peerassess'));
        
        // [!] Refactor --> get list of assignments in course
        $assignments_sql = 'SELECT
                                -- cm.id AS cmid,
                                -- cm.course,
                                -- m.id AS mid,
                                -- m.name,
                                a.id AS aid,
                                a.name AS assignment_name
                            FROM {course_modules} cm
                                INNER JOIN {modules} m
                                ON cm.module = m.id
                                INNER JOIN {assign} a
                                ON cm.instance = a.id
                            WHERE m.name = "assign"
                                AND cm.course = ?';

        $assignments_list = $DB->get_records_sql_menu($assignments_sql, array($COURSE->id));

        $select = $mform->addElement('select', 'assignments', "Assignments", $assignments_list, array("size" => 8, "style" => 'width: 50%'));
        $select->setMultiple(true);
        // $mform->addHelpButton('assignments', 'assignments', 'peerassess');

        // Note to lecturer that this question would be converted into a drop down menu
        $mform->addElement('static', 'hint', '', 'Ctrl+Click to select the assignment(s) you would like to apply the peer factor on.', 'peerassess');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('availability'));

        $mform->addElement('date_time_selector', 'timeopen', get_string('peerassessopen', 'peerassess'),
            array('optional' => true));

        $mform->addElement('date_time_selector', 'timeclose', get_string('peerassessclose', 'peerassess'),
            array('optional' => true));

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'peerassesshdr', get_string('questionandsubmission', 'peerassess'));

        $options=array();
        $options[1]  = get_string('anonymous', 'peerassess');
        $options[2]  = get_string('non_anonymous', 'peerassess');
        $mform->addElement('select',
                           'anonymous',
                           get_string('anonymous_edit', 'peerassess'),
                           $options);

        // check if there is existing responses to this peerassess
        if (is_numeric($this->_instance) AND
                    $this->_instance AND
                    $peerassess = $DB->get_record("peerassess", array("id"=>$this->_instance))) {

            $completed_peerassess_count = peerassess_get_completeds_group_count($peerassess);
        } else {
            $completed_peerassess_count = false;
        }

        if ($completed_peerassess_count) {
            $multiple_submit_value = $peerassess->multiple_submit ? get_string('yes') : get_string('no');
            $mform->addElement('text',
                               'multiple_submit_static',
                               get_string('multiplesubmit', 'peerassess'),
                               array('size'=>'4',
                                    'disabled'=>'disabled',
                                    'value'=>$multiple_submit_value));
            $mform->setType('multiple_submit_static', PARAM_RAW);

            $mform->addElement('hidden', 'multiple_submit', '');
            $mform->setType('multiple_submit', PARAM_INT);
            $mform->addHelpButton('multiple_submit_static', 'multiplesubmit', 'peerassess');
        } else {
            $mform->addElement('selectyesno',
                               'multiple_submit',
                               get_string('multiplesubmit', 'peerassess'));

            $mform->addHelpButton('multiple_submit', 'multiplesubmit', 'peerassess');
        }

        $mform->addElement('selectyesno', 'email_notification', get_string('email_notification', 'peerassess'));
        $mform->addHelpButton('email_notification', 'email_notification', 'peerassess');

        $mform->addElement('selectyesno', 'autonumbering', get_string('autonumbering', 'peerassess'));
        $mform->addHelpButton('autonumbering', 'autonumbering', 'peerassess');

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'aftersubmithdr', get_string('after_submit', 'peerassess'));

        $mform->addElement('selectyesno', 'publish_stats', get_string('show_analysepage_after_submit', 'peerassess'));

        $mform->addElement('editor',
                           'page_after_submit_editor',
                           get_string("page_after_submit", "peerassess"),
                           null,
                           $editoroptions);

        $mform->setType('page_after_submit_editor', PARAM_RAW);

        $mform->addElement('text',
                           'site_after_submit',
                           get_string('url_for_continue', 'peerassess'),
                           array('size'=>'64', 'maxlength'=>'255'));

        $mform->setType('site_after_submit', PARAM_TEXT);
        $mform->addHelpButton('site_after_submit', 'url_for_continue', 'peerassess');
        //-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {

        $editoroptions = peerassess_get_editor_options();

        if ($this->current->instance) {
            // editing an existing peerassess - let us prepare the added editor elements (intro done automatically)
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit');
            $default_values['page_after_submit_editor']['text'] =
                                    file_prepare_draft_area($draftitemid, $this->context->id,
                                    'mod_peerassess', 'page_after_submit', false,
                                    $editoroptions,
                                    $default_values['page_after_submit']);

            $default_values['page_after_submit_editor']['format'] = $default_values['page_after_submitformat'];
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
        } else {
            // adding a new peerassess instance
            $draftitemid = file_get_submitted_draft_itemid('page_after_submit_editor');

            // no context yet, itemid not used
            file_prepare_draft_area($draftitemid, null, 'mod_peerassess', 'page_after_submit', false);
            $default_values['page_after_submit_editor']['text'] = '';
            $default_values['page_after_submit_editor']['format'] = editors_get_preferred_format();
            $default_values['page_after_submit_editor']['itemid'] = $draftitemid;
        }

    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        if (isset($data->page_after_submit_editor)) {
            $data->page_after_submitformat = $data->page_after_submit_editor['format'];
            $data->page_after_submit = $data->page_after_submit_editor['text'];

            if (!empty($data->completionunlocked)) {
                // Turn off completion settings if the checkboxes aren't ticked
                $autocompletion = !empty($data->completion) &&
                    $data->completion == COMPLETION_TRACKING_AUTOMATIC;
                if (!$autocompletion || empty($data->completionsubmit)) {
                    $data->completionsubmit=0;
                }
            }
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] && $data['timeclose'] &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'peerassess');
        }
        return $errors;
    }

    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox',
                           'completionsubmit',
                           '',
                           get_string('completionsubmit', 'peerassess'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}
