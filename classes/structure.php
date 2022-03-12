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
 * Contains class mod_peerassess_structure
 *
 * @package   mod_peerassess
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Stores and manipulates the structure of the peerassess or template (items, pages, etc.)
 *
 * @package   mod_peerassess
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerassess_structure {
    /** @var stdClass record from 'peerassess' table.
     * Reliably has fields: id, course, timeopen, timeclose, anonymous, completionsubmit.
     * For full object or to access any other field use $this->get_peerassess()
     */
    protected $peerassess;
    /** @var cm_info */
    protected $cm;
    /** @var int course where the peerassess is filled. For peerassesss that are NOT on the front page this is 0 */
    protected $courseid = 0;
    /** @var int */
    protected $templateid;
    /** @var array */
    protected $allitems;
    /** @var array */
    protected $allcourses;
    /** @var int */
    protected $userid;

    /**
     * Constructor
     *
     * @param stdClass $peerassess peerassess object, in case of the template
     *     this is the current peerassess the template is accessed from
     * @param stdClass|cm_info $cm course module object corresponding to the $peerassess
     *     (at least one of $peerassess or $cm is required)
     * @param int $courseid current course (for site peerassesss only)
     * @param int $templateid template id if this class represents the template structure
     * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
     */
    public function __construct($peerassess, $cm, $courseid = 0, $templateid = null, $userid = 0) {
        global $USER;

        if ((empty($peerassess->id) || empty($peerassess->course)) && (empty($cm->instance) || empty($cm->course))) {
            throw new coding_exception('Either $peerassess or $cm must be passed to constructor');
        }
        $this->peerassess = $peerassess ?: (object)['id' => $cm->instance, 'course' => $cm->course];
        $this->cm = ($cm && $cm instanceof cm_info) ? $cm :
            get_fast_modinfo($this->peerassess->course)->instances['peerassess'][$this->peerassess->id];
        $this->templateid = $templateid;
        $this->courseid = ($this->peerassess->course == SITEID) ? $courseid : 0;

        if (empty($userid)) {
            $this->userid = $USER->id;
        } else {
            $this->userid = $userid;
        }

        if (!$peerassess) {
            // If peerassess object was not specified, populate object with fields required for the most of methods.
            // These fields were added to course module cache in peerassess_get_coursemodule_info().
            // Full instance record can be retrieved by calling mod_peerassess_structure::get_peerassess().
            $customdata = ($this->cm->customdata ?: []) + ['timeopen' => 0, 'timeclose' => 0, 'anonymous' => 0];
            $this->peerassess->timeopen = $customdata['timeopen'];
            $this->peerassess->timeclose = $customdata['timeclose'];
            $this->peerassess->anonymous = $customdata['anonymous'];
            $this->peerassess->completionsubmit = empty($this->cm->customdata['customcompletionrules']['completionsubmit']) ? 0 : 1;
        }
    }

    /**
     * Current peerassess
     * @return stdClass
     */
    public function get_peerassess() {
        global $DB;
        if (!isset($this->peerassess->publish_stats) || !isset($this->peerassess->name)) {
            // Make sure the full object is retrieved.
            $this->peerassess = $DB->get_record('peerassess', ['id' => $this->peerassess->id], '*', MUST_EXIST);
        }
        return $this->peerassess;
    }

    /**
     * Current course module
     * @return stdClass
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Id of the current course (for site peerassesss only)
     * @return stdClass
     */
    public function get_courseid() {
        return $this->courseid;
    }

    /**
     * Template id
     * @return int
     */
    public function get_templateid() {
        return $this->templateid;
    }

    /**
     * Is this peerassess open (check timeopen and timeclose)
     * @return bool
     */
    public function is_open() {
        $checktime = time();
        return (!$this->peerassess->timeopen || $this->peerassess->timeopen <= $checktime) &&
            (!$this->peerassess->timeclose || $this->peerassess->timeclose >= $checktime);
    }

    /**
     * Get all items in this peerassess or this template
     * @param bool $hasvalueonly only count items with a value.
     * @return array of objects from peerassess_item with an additional attribute 'itemnr'
     */
    public function get_items($hasvalueonly = false) {
        global $DB;
        if ($this->allitems === null) {
            if ($this->templateid) {
                $this->allitems = $DB->get_records('peerassess_item', ['template' => $this->templateid], 'position');
            } else {
                $this->allitems = $DB->get_records('peerassess_item', ['peerassess' => $this->peerassess->id], 'position');
            }
            $idx = 1;
            foreach ($this->allitems as $id => $item) {
                $this->allitems[$id]->itemnr = $item->hasvalue ? ($idx++) : null;
            }
        }
        if ($hasvalueonly && $this->allitems) {
            return array_filter($this->allitems, function($item) {
                return $item->hasvalue;
            });
        }
        return $this->allitems;
    }

    /**
     * Is the items list empty?
     * @return bool
     */
    public function is_empty() {
        $items = $this->get_items();
        $displayeditems = array_filter($items, function($item) {
            return $item->typ !== 'pagebreak';
        });
        return !$displayeditems;
    }

    /**
     * Is this peerassess anonymous?
     * @return bool
     */
    public function is_anonymous() {
        return $this->peerassess->anonymous == PEERASSESS_ANONYMOUS_YES;
    }

    /**
     * Returns the formatted text of the page after submit or null if it is not set
     *
     * @return string|null
     */
    public function page_after_submit() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $pageaftersubmit = $this->get_peerassess()->page_after_submit;
        if (empty($pageaftersubmit)) {
            return null;
        }
        $pageaftersubmitformat = $this->get_peerassess()->page_after_submitformat;

        $context = context_module::instance($this->get_cm()->id);
        $output = file_rewrite_pluginfile_urls($pageaftersubmit,
                'pluginfile.php', $context->id, 'mod_peerassess', 'page_after_submit', 0);

        return format_text($output, $pageaftersubmitformat, array('overflowdiv' => true));
    }

    /**
     * Checks if current user is able to view peerassess on this course.
     *
     * @return bool
     */
    public function can_view_analysis() {
        global $USER;

        $context = context_module::instance($this->cm->id);
        if (has_capability('mod/peerassess:viewreports', $context, $this->userid)) {
            return true;
        }

        if (intval($this->get_peerassess()->publish_stats) != 1 ||
                !has_capability('mod/peerassess:viewanalysepage', $context, $this->userid)) {
            return false;
        }

        if ((!isloggedin() && $USER->id == $this->userid) || isguestuser($this->userid)) {
            // There is no tracking for the guests, assume that they can view analysis if condition above is satisfied.
            return $this->peerassess->course == SITEID;
        }

        return $this->is_already_submitted(true);
    }

    /**
     * check for multiple_submit = false.
     * if the peerassess is global so the courseid must be given
     *
     * @param bool $anycourseid if true checks if this peerassess was submitted in any course, otherwise checks $this->courseid .
     *     Applicable to frontpage peerassesss only
     * @return bool true if the peerassess already is submitted otherwise false
     */
    public function is_already_submitted($anycourseid = false) {
        global $DB, $USER;

        if ((!isloggedin() && $USER->id == $this->userid) || isguestuser($this->userid)) {
            return false;
        }

        $params = array('userid' => $this->userid, 'peerassess' => $this->peerassess->id);
        if (!$anycourseid && $this->courseid) {
            $params['courseid'] = $this->courseid;
        }
        return $DB->record_exists('peerassess_completed', $params);
    }

    /**
     * Check whether the peerassess is mapped to the given courseid.
     */
    public function check_course_is_mapped() {
        global $DB;
        if ($this->peerassess->course != SITEID) {
            return true;
        }
        if ($DB->get_records('peerassess_sitecourse_map', array('peerassessid' => $this->peerassess->id))) {
            $params = array('peerassessid' => $this->peerassess->id, 'courseid' => $this->courseid);
            if (!$DB->get_record('peerassess_sitecourse_map', $params)) {
                return false;
            }
        }
        // No mapping means any course is mapped.
        return true;
    }

    /**
     * If there are any new responses to the anonymous peerassess, re-shuffle all
     * responses and assign response number to each of them.
     */
    public function shuffle_anonym_responses() {
        global $DB;
        $params = array('peerassess' => $this->peerassess->id,
            'random_response' => 0,
            'anonymous_response' => PEERASSESS_ANONYMOUS_YES);

        if ($DB->count_records('peerassess_completed', $params, 'random_response')) {
            // Get all of the anonymous records, go through them and assign a response id.
            unset($params['random_response']);
            $peerassesscompleteds = $DB->get_records('peerassess_completed', $params, 'id');
            shuffle($peerassesscompleteds);
            $num = 1;
            foreach ($peerassesscompleteds as $compl) {
                $compl->random_response = $num++;
                $DB->update_record('peerassess_completed', $compl);
            }
        }
    }

    /**
     * Counts records from {peerassess_completed} table for a given peerassess
     *
     * If $groupid or $this->courseid is set, the records are filtered by the group/course
     *
     * @param int $groupid
     * @return mixed array of found completeds otherwise false
     */
    public function count_completed_responses($groupid = 0) {
        global $DB;
        if (intval($groupid) > 0) {
            $query = "SELECT COUNT(DISTINCT fbc.id)
                        FROM {peerassess_completed} fbc, {groups_members} gm
                        WHERE fbc.peerassess = :peerassess
                            AND gm.groupid = :groupid
                            AND fbc.userid = gm.userid";
        } else if ($this->courseid) {
            $query = "SELECT COUNT(fbc.id)
                        FROM {peerassess_completed} fbc
                        WHERE fbc.peerassess = :peerassess
                            AND fbc.courseid = :courseid";
        } else {
            $query = "SELECT COUNT(fbc.id) FROM {peerassess_completed} fbc WHERE fbc.peerassess = :peerassess";
        }
        $params = ['peerassess' => $this->peerassess->id, 'groupid' => $groupid, 'courseid' => $this->courseid];
        return $DB->get_field_sql($query, $params);
    }

    /**
     * For the frontpage peerassess returns the list of courses with at least one completed peerassess
     *
     * @return array id=>name pairs of courses
     */
    public function get_completed_courses() {
        global $DB;

        if ($this->get_peerassess()->course != SITEID) {
            return [];
        }

        if ($this->allcourses !== null) {
            return $this->allcourses;
        }

        $courseselect = "SELECT fbc.courseid
            FROM {peerassess_completed} fbc
            WHERE fbc.peerassess = :peerassessid";

        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');

        $sql = 'SELECT c.id, c.shortname, c.fullname, c.idnumber, c.visible, '. $ctxselect. '
                FROM {course} c
                JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                WHERE c.id IN ('. $courseselect.') ORDER BY c.sortorder';
        $list = $DB->get_records_sql($sql, ['contextcourse' => CONTEXT_COURSE, 'peerassessid' => $this->get_peerassess()->id]);

        $this->allcourses = array();
        foreach ($list as $course) {
            context_helper::preload_from_record($course);
            if (!$course->visible &&
                !has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id), $this->userid)) {
                // Do not return courses that current user can not see.
                continue;
            }
            $label = get_course_display_name_for_list($course);
            $this->allcourses[$course->id] = $label;
        }
        return $this->allcourses;
    }
}