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

require_once($CFG->dirroot.'/mod/peerassess/item/peerassess_item_form_class.php');

class peerassess_multichoice_form extends peerassess_item_form {
    protected $type = "multichoice";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'peerassess'));

        $mform->addElement('advcheckbox', 'required', get_string('required', 'peerassess'), '' , null , array(0, 1));

        $mform->addElement('text',
                            'name',
                            get_string('item_name', 'peerassess'),
                            array('size' => peerassess_ITEM_NAME_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        $mform->addElement('text',
                            'label',
                            get_string('item_label', 'peerassess'),
                            array('size' => peerassess_ITEM_LABEL_TEXTBOX_SIZE,
                                  'maxlength' => 255));

        $mform->addElement('select',
                            'subtype',
                            get_string('multichoicetype', 'peerassess').'&nbsp;',
                            array('r'=>get_string('radio', 'peerassess'),
                                  'c'=>get_string('check', 'peerassess'),
                                  'd'=>get_string('dropdown', 'peerassess')));

        $mform->addElement('select',
                            'horizontal',
                            get_string('adjustment', 'peerassess').'&nbsp;',
                            array(0 => get_string('vertical', 'peerassess'),
                                  1 => get_string('horizontal', 'peerassess')));
        $mform->hideIf('horizontal', 'subtype', 'eq', 'd');

        $mform->addElement('selectyesno',
                           'hidenoselect',
                           get_string('hide_no_select_option', 'peerassess'));
        $mform->hideIf('hidenoselect', 'subtype', 'ne', 'r');

        $mform->addElement('selectyesno',
                           'ignoreempty',
                           get_string('do_not_analyse_empty_submits', 'peerassess'));

        $mform->addElement('textarea', 'values', get_string('multichoice_values', 'peerassess'),
            'wrap="virtual" rows="10" cols="65"');

        $mform->addElement('static', 'hint', '', get_string('use_one_line_for_each_value', 'peerassess'));

        parent::definition();
        $this->set_data($item);

    }

    public function set_data($item) {
        $info = $this->_customdata['info'];

        $item->horizontal = $info->horizontal;

        $item->subtype = $info->subtype;

        $itemvalues = str_replace(peerassess_MULTICHOICE_LINE_SEP, "\n", $info->presentation);
        $itemvalues = str_replace("\n\n", "\n", $itemvalues);
        $item->values = $itemvalues;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }

        $presentation = str_replace("\n", peerassess_MULTICHOICE_LINE_SEP, trim($item->values));
        if (!isset($item->subtype)) {
            $subtype = 'r';
        } else {
            $subtype = substr($item->subtype, 0, 1);
        }
        if (isset($item->horizontal) AND $item->horizontal == 1 AND $subtype != 'd') {
            $presentation .= peerassess_MULTICHOICE_ADJUST_SEP.'1';
        }
        if (!isset($item->hidenoselect)) {
            $item->hidenoselect = 1;
        }
        if (!isset($item->ignoreempty)) {
            $item->ignoreempty = 0;
        }

        $item->presentation = $subtype.peerassess_MULTICHOICE_TYPE_SEP.$presentation;
        return $item;
    }
}
