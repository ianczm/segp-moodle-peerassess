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
 * Allows users to select their group members from a dropdown list
 *
 * @copyright SEGP Group 10A
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package mod_peerassess
 */

require_once($CFG->dirroot.'/mod/peerassess/item/peerassess_item_form_class.php');

class peerassess_memberselect_form extends peerassess_item_form {
    protected $type = "memberselect";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', 'Member Select');

        // This question is set to be required by default
        $mform->addElement('hidden', 'required', 0);
        $mform->setType('required', PARAM_INT);
        $mform->setDefault('required', 1);

        $mform->addElement('text',
                            'name',
                            'Member Select Title',
                            array('size' => PEERASSESS_ITEM_NAME_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        // Note to lecturer that this question would be converted into a drop down menu
        $mform->addElement('static', 'hint', '', 'This question will be replaced by a drop down menu showing each student\'s respective group members.', 'peerassess');

        parent::definition();
        $this->set_data($item);

    }

    public function set_data($item) {
        $info = $this->_customdata['info'];

        $item->horizontal = $info->horizontal;

        $item->subtype = $info->subtype;

        $itemvalues = str_replace(PEERASSESS_MEMBERSELECT_LINE_SEP, "\n", $info->presentation);
        $itemvalues = str_replace("\n\n", "\n", $itemvalues);
        $item->values = $itemvalues;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }

        $presentation = str_replace("\n", PEERASSESS_MEMBERSELECT_LINE_SEP, trim($item->values));
        if (!isset($item->subtype)) {
            $subtype = 'd';
        } else {
            $subtype = substr($item->subtype, 0, 1);
        }
        if (isset($item->horizontal) AND $item->horizontal == 1 AND $subtype != 'd') {
            $presentation .= PEERASSESS_MEMBERSELECT_ADJUST_SEP.'1';
        }
        if (!isset($item->hidenoselect)) {
            $item->hidenoselect = 1;
        }
        if (!isset($item->ignoreempty)) {
            $item->ignoreempty = 0;
        }

        $item->presentation = $subtype.PEERASSESS_MEMBERSELECT_TYPE_SEP.$presentation;
        return $item;
    }
}
