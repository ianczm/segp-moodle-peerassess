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
 * Peerassess external API
 *
 * @package    mod_peerassess
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

use mod_peerassess\external\peerassess_summary_exporter;
use mod_peerassess\external\peerassess_completedtmp_exporter;
use mod_peerassess\external\peerassess_item_exporter;
use mod_peerassess\external\peerassess_valuetmp_exporter;
use mod_peerassess\external\peerassess_value_exporter;
use mod_peerassess\external\peerassess_completed_exporter;

/**
 * Peerassess external functions
 *
 * @package    mod_peerassess
 * @category   external
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_peerassess_external extends external_api {

    /**
     * Describes the parameters for get_peerassesss_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_peerassesss_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of peerassesss in a provided list of courses.
     * If no list is provided all peerassesss that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and peerassesss
     * @since Moodle 3.3
     */
    public static function get_peerassesss_by_courses($courseids = array()) {
        global $PAGE;

        $warnings = array();
        $returnedpeerassesss = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_peerassesss_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);
            $output = $PAGE->get_renderer('core');

            // Get the peerassesss in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $peerassesss = get_all_instances_in_courses("peerassess", $courses);
            foreach ($peerassesss as $peerassess) {

                $context = context_module::instance($peerassess->coursemodule);

                // Remove fields that are not from the peerassess (added by get_all_instances_in_courses).
                unset($peerassess->coursemodule, $peerassess->context, $peerassess->visible, $peerassess->section, $peerassess->groupmode,
                        $peerassess->groupingid);

                // Check permissions.
                if (!has_capability('mod/peerassess:edititems', $context)) {
                    // Don't return the optional properties.
                    $properties = peerassess_summary_exporter::properties_definition();
                    foreach ($properties as $property => $config) {
                        if (!empty($config['optional'])) {
                            unset($peerassess->{$property});
                        }
                    }
                }
                $exporter = new peerassess_summary_exporter($peerassess, array('context' => $context));
                $returnedpeerassesss[] = $exporter->export($output);
            }
        }

        $result = array(
            'peerassesss' => $returnedpeerassesss,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_peerassesss_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_peerassesss_by_courses_returns() {
        return new external_single_structure(
            array(
                'peerassesss' => new external_multiple_structure(
                    peerassess_summary_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a peerassess.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $courseid courseid course where user completes the peerassess (for site peerassesss only)
     * @return array containing the peerassess, peerassess course, context, course module and the course where is being completed.
     * @throws moodle_exception
     * @since  Moodle 3.3
     */
    protected static function validate_peerassess($peerassessid, $courseid = 0) {
        global $DB, $USER;

        // Request and permission validation.
        $peerassess = $DB->get_record('peerassess', array('id' => $peerassessid), '*', MUST_EXIST);
        list($peerassesscourse, $cm) = get_course_and_cm_from_instance($peerassess, 'peerassess');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Set default completion course.
        $completioncourse = (object) array('id' => 0);
        if ($peerassesscourse->id == SITEID && $courseid) {
            $completioncourse = get_course($courseid);
            self::validate_context(context_course::instance($courseid));

            $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $courseid);
            if (!$peerassesscompletion->check_course_is_mapped()) {
                throw new moodle_exception('cannotaccess', 'mod_peerassess');
            }
        }

        return array($peerassess, $peerassesscourse, $cm, $context, $completioncourse);
    }

    /**
     * Utility function for validating access to peerassess.
     *
     * @param  stdClass   $peerassess peerassess object
     * @param  stdClass   $course   course where user completes the peerassess (for site peerassesss only)
     * @param  stdClass   $cm       course module
     * @param  stdClass   $context  context object
     * @throws moodle_exception
     * @return mod_peerassess_completion peerassess completion instance
     * @since  Moodle 3.3
     */
    protected static function validate_peerassess_access($peerassess, $course, $cm, $context, $checksubmit = false) {
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $course->id);

        if (!$peerassesscompletion->can_complete()) {
            throw new required_capability_exception($context, 'mod/peerassess:complete', 'nopermission', '');
        }

        if (!$peerassesscompletion->is_open()) {
            throw new moodle_exception('peerassess_is_not_open', 'peerassess');
        }

        if ($peerassesscompletion->is_empty()) {
            throw new moodle_exception('no_items_available_yet', 'peerassess');
        }

        if ($checksubmit && !$peerassesscompletion->can_submit()) {
            throw new moodle_exception('this_peerassess_is_already_submitted', 'peerassess');
        }
        return $peerassesscompletion;
    }

    /**
     * Describes the parameters for get_peerassess_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_peerassess_access_information_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given peerassess.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_peerassess_access_information($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array(
            'peerassessid' => $peerassessid,
            'courseid' => $courseid,
        );
        $params = self::validate_parameters(self::get_peerassess_access_information_parameters(), $params);

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        $result = array();
        // Capabilities first.
        $result['canviewanalysis'] = $peerassesscompletion->can_view_analysis();
        $result['cancomplete'] = $peerassesscompletion->can_complete();
        $result['cansubmit'] = $peerassesscompletion->can_submit();
        $result['candeletesubmissions'] = has_capability('mod/peerassess:deletesubmissions', $context);
        $result['canviewreports'] = has_capability('mod/peerassess:viewreports', $context);
        $result['canedititems'] = has_capability('mod/peerassess:edititems', $context);

        // Status information.
        $result['isempty'] = $peerassesscompletion->is_empty();
        $result['isopen'] = $peerassesscompletion->is_open();
        $anycourse = ($course->id == SITEID);
        $result['isalreadysubmitted'] = $peerassesscompletion->is_already_submitted($anycourse);
        $result['isanonymous'] = $peerassesscompletion->is_anonymous();

        $result['warnings'] = [];
        return $result;
    }

    /**
     * Describes the get_peerassess_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_peerassess_access_information_returns() {
        return new external_single_structure(
            array(
                'canviewanalysis' => new external_value(PARAM_BOOL, 'Whether the user can view the analysis or not.'),
                'cancomplete' => new external_value(PARAM_BOOL, 'Whether the user can complete the peerassess or not.'),
                'cansubmit' => new external_value(PARAM_BOOL, 'Whether the user can submit the peerassess or not.'),
                'candeletesubmissions' => new external_value(PARAM_BOOL, 'Whether the user can delete submissions or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the peerassess reports or not.'),
                'canedititems' => new external_value(PARAM_BOOL, 'Whether the user can edit peerassess items or not.'),
                'isempty' => new external_value(PARAM_BOOL, 'Whether the peerassess has questions or not.'),
                'isopen' => new external_value(PARAM_BOOL, 'Whether the peerassess has active access time restrictions or not.'),
                'isalreadysubmitted' => new external_value(PARAM_BOOL, 'Whether the peerassess is already submitted or not.'),
                'isanonymous' => new external_value(PARAM_BOOL, 'Whether the peerassess is anonymous or not.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_peerassess.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_peerassess_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'moduleviewed' => new external_value(PARAM_BOOL, 'If we need to mark the module as viewed for completion',
                    VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $peerassessid peerassess instance id
     * @param bool $moduleviewed If we need to mark the module as viewed for completion
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_peerassess($peerassessid, $moduleviewed = false, $courseid = 0) {

        $params = array('peerassessid' => $peerassessid, 'moduleviewed' => $moduleviewed, 'courseid' => $courseid);
        $params = self::validate_parameters(self::view_peerassess_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        // Trigger module viewed event.
        $peerassesscompletion->trigger_module_viewed();
        if ($params['moduleviewed']) {
            if (!$peerassesscompletion->is_open()) {
                throw new moodle_exception('peerassess_is_not_open', 'peerassess');
            }
            // Mark activity viewed for completion-tracking.
            $peerassesscompletion->set_module_viewed();
        }

        $result = array(
            'status' => true,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the view_peerassess return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function view_peerassess_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_current_completed_tmp.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns the temporary completion record for the current user.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_current_completed_tmp($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_current_completed_tmp_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        if ($completed = $peerassesscompletion->get_current_completed_tmp()) {
            $exporter = new peerassess_completedtmp_exporter($completed);
            return array(
                'peerassess' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_started', 'peerassess');
    }

    /**
     * Describes the get_current_completed_tmp return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_current_completed_tmp_returns() {
        return new external_single_structure(
            array(
                'peerassess' => peerassess_completedtmp_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_items_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns the items (questions) in the given peerassess.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and peerassesss
     * @since Moodle 3.3
     */
    public static function get_items($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_items_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);

        $peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $completioncourse->id);
        $returneditems = array();
        if ($items = $peerassessstructure->get_items()) {
            foreach ($items as $item) {
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new peerassess_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
                $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
            }
        }

        $result = array(
            'items' => $returneditems,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    peerassess_item_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for launch_peerassess.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function launch_peerassess_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Starts or continues a peerassess submission
     *
     * @param array $peerassessid peerassess instance id
     * @param int $courseid course where user completes a peerassess (for site peerassesss only).
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function launch_peerassess($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::launch_peerassess_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        // Check we can do a new submission (or continue an existing).
        $peerassesscompletion = self::validate_peerassess_access($peerassess, $completioncourse, $cm, $context, true);

        $gopage = $peerassesscompletion->get_resume_page();
        if ($gopage === null) {
            $gopage = -1; // Last page.
        }

        $result = array(
            'gopage' => $gopage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the launch_peerassess return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function launch_peerassess_returns() {
        return new external_single_structure(
            array(
                'gopage' => new external_value(PARAM_INT, 'The next page to go (-1 if we were already in the last page). 0 for first page.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_items.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_items_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'page' => new external_value(PARAM_INT, 'The page to get starting by 0'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get a single peerassess page items.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $page the page to get starting by 0
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_page_items($peerassessid, $page, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'page' => $page, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_page_items_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);

        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        $page = $params['page'];
        $pages = $peerassesscompletion->get_pages();
        $pageitems = $pages[$page];
        $hasnextpage = $page < count($pages) - 1; // Until we complete this page we can not trust get_next_page().
        $hasprevpage = $page && ($peerassesscompletion->get_previous_page($page, false) !== null);

        $returneditems = array();
        foreach ($pageitems as $item) {
            $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
            unset($item->itemnr);   // Added by the function, not part of the record.
            $exporter = new peerassess_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));
            $returneditems[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'items' => $returneditems,
            'hasprevpage' => $hasprevpage,
            'hasnextpage' => $hasnextpage,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_page_items return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_items_returns() {
        return new external_single_structure(
            array(
                'items' => new external_multiple_structure(
                    peerassess_item_exporter::get_read_structure()
                ),
                'hasprevpage' => new external_value(PARAM_BOOL, 'Whether is a previous page.'),
                'hasnextpage' => new external_value(PARAM_BOOL, 'Whether there are more pages.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_page.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function process_page_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id.'),
                'page' => new external_value(PARAM_INT, 'The page being processed.'),
                'responses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_NOTAGS, 'The response name (usually type[index]_id).'),
                            'value' => new external_value(PARAM_RAW, 'The response value.'),
                        )
                    ), 'The data to be processed.', VALUE_DEFAULT, array()
                ),
                'goprevious' => new external_value(PARAM_BOOL, 'Whether we want to jump to previous page.', VALUE_DEFAULT, false),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Process a jump between pages.
     *
     * @param array $peerassessid peerassess instance id
     * @param array $page the page being processed
     * @param array $responses the responses to be processed
     * @param bool $goprevious whether we want to jump to previous page
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function process_page($peerassessid, $page, $responses = [], $goprevious = false, $courseid = 0) {
        global $USER, $SESSION;

        $params = array('peerassessid' => $peerassessid, 'page' => $page, 'responses' => $responses, 'goprevious' => $goprevious,
            'courseid' => $courseid);
        $params = self::validate_parameters(self::process_page_parameters(), $params);
        $warnings = array();
        $siteaftersubmit = $completionpagecontents = '';

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        // Check we can do a new submission (or continue an existing).
        $peerassesscompletion = self::validate_peerassess_access($peerassess, $completioncourse, $cm, $context, true);

        // Create the $_POST object required by the peerassess question engine.
        $_POST = array();
        foreach ($responses as $response) {
            // First check if we are handling array parameters.
            if (preg_match('/(.+)\[(.+)\]$/', $response['name'], $matches)) {
                $_POST[$matches[1]][$matches[2]] = $response['value'];
            } else {
                $_POST[$response['name']] = $response['value'];
            }
        }
        // Force fields.
        $_POST['id'] = $cm->id;
        $_POST['courseid'] = $courseid;
        $_POST['gopage'] = $params['page'];
        $_POST['_qf__mod_peerassess_complete_form'] = 1;

        // Determine where to go, backwards or forward.
        if (!$params['goprevious']) {
            $_POST['gonextpage'] = 1;   // Even if we are saving values we need this set.
            if ($peerassesscompletion->get_next_page($params['page'], false) === null) {
                $_POST['savevalues'] = 1;   // If there is no next page, it means we are finishing the peerassess.
            }
        }

        // Ignore sesskey (deep in some APIs), the request is already validated.
        $USER->ignoresesskey = true;
        peerassess_init_peerassess_session();
        $SESSION->peerassess->is_started = true;

        $peerassesscompletion->process_page($params['page'], $params['goprevious']);
        $completed = $peerassesscompletion->just_completed();
        if ($completed) {
            $jumpto = 0;
            if ($peerassess->page_after_submit) {
                $completionpagecontents = $peerassesscompletion->page_after_submit();
            }

            if ($peerassess->site_after_submit) {
                $siteaftersubmit = peerassess_encode_target_url($peerassess->site_after_submit);
            }
        } else {
            $jumpto = $peerassesscompletion->get_jumpto();
        }

        $result = array(
            'jumpto' => $jumpto,
            'completed' => $completed,
            'completionpagecontents' => $completionpagecontents,
            'siteaftersubmit' => $siteaftersubmit,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the process_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function process_page_returns() {
        return new external_single_structure(
            array(
                'jumpto' => new external_value(PARAM_INT, 'The page to jump to.'),
                'completed' => new external_value(PARAM_BOOL, 'If the user completed the peerassess.'),
                'completionpagecontents' => new external_value(PARAM_RAW, 'The completion page contents.'),
                'siteaftersubmit' => new external_value(PARAM_RAW, 'The link (could be relative) to show after submit.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_analysis_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves the peerassess analysis.
     *
     * @param array $peerassessid peerassess instance id
     * @param int $groupid group id, 0 means that the function will determine the user group
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_analysis($peerassessid, $groupid = 0, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'groupid' => $groupid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);

        // Check permissions.
        $peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $completioncourse->id);
        if (!$peerassessstructure->can_view_analysis()) {
            throw new required_capability_exception($context, 'mod/peerassess:viewanalysepage', 'nopermission', '');
        }

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        // Summary data.
        $summary = new mod_peerassess\output\summary($peerassessstructure, $groupid);
        $summarydata = $summary->export_for_template($PAGE->get_renderer('core'));

        $checkanonymously = true;
        if ($groupid > 0 AND $peerassess->anonymous == PEERASSESS_ANONYMOUS_YES) {
            $completedcount = $peerassessstructure->count_completed_responses($groupid);
            if ($completedcount < PEERASSESS_MIN_ANONYMOUS_COUNT_IN_GROUP) {
                $checkanonymously = false;
            }
        }

        if ($checkanonymously) {
            // Get the items of the peerassess.
            $items = $peerassessstructure->get_items(true);
            foreach ($items as $item) {
                $itemobj = peerassess_get_item_class($item->typ);
                $itemnumber = empty($item->itemnr) ? null : $item->itemnr;
                unset($item->itemnr);   // Added by the function, not part of the record.
                $exporter = new peerassess_item_exporter($item, array('context' => $context, 'itemnumber' => $itemnumber));

                $itemsdata[] = array(
                    'item' => $exporter->export($PAGE->get_renderer('core')),
                    'data' => $itemobj->get_analysed_for_external($item, $groupid),
                );
            }
        } else {
            $warnings[] = array(
                'item' => 'peerassess',
                'itemid' => $peerassess->id,
                'warningcode' => 'insufficientresponsesforthisgroup',
                'message' => s(get_string('insufficient_responses_for_this_group', 'peerassess'))
            );
        }

        $result = array(
            'completedcount' => $summarydata->completedcount,
            'itemscount' => $summarydata->itemscount,
            'itemsdata' => $itemsdata,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_analysis_returns() {
        return new external_single_structure(
            array(
            'completedcount' => new external_value(PARAM_INT, 'Number of completed submissions.'),
            'itemscount' => new external_value(PARAM_INT, 'Number of items (questions).'),
            'itemsdata' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'item' => peerassess_item_exporter::get_read_structure(),
                        'data' => new external_multiple_structure(
                            new external_value(PARAM_RAW, 'The analysis data (can be json encoded)')
                        ),
                    )
                )
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_unfinished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves responses from the current unfinished attempt.
     *
     * @param array $peerassessid peerassess instance id
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and launch information
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_unfinished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        $responses = array();
        $unfinished = $peerassesscompletion->get_unfinished_responses();
        foreach ($unfinished as $u) {
            $exporter = new peerassess_valuetmp_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_unfinished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_unfinished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                peerassess_valuetmp_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_finished_responses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_finished_responses_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id.'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves responses from the last finished attempt.
     *
     * @param array $peerassessid peerassess instance id
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and the responses
     * @since Moodle 3.3
     */
    public static function get_finished_responses($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_finished_responses_parameters(), $params);
        $warnings = $itemsdata = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        $responses = array();
        // Load and get the responses from the last completed peerassess.
        $peerassesscompletion->find_last_completed();
        $unfinished = $peerassesscompletion->get_finished_responses();
        foreach ($unfinished as $u) {
            $exporter = new peerassess_value_exporter($u);
            $responses[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'responses' => $responses,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_finished_responses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_finished_responses_returns() {
        return new external_single_structure(
            array(
            'responses' => new external_multiple_structure(
                peerassess_value_exporter::get_read_structure()
            ),
            'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_non_respondents.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_non_respondents_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group.',
                                                VALUE_DEFAULT, 0),
                'sort' => new external_value(PARAM_ALPHA, 'Sort param, must be firstname, lastname or lastaccess (default).',
                                                VALUE_DEFAULT, 'lastaccess'),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page.', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves a list of students who didn't submit the peerassess.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group'
     * @param str $sort sort param, must be firstname, lastname or lastaccess (default)
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and users ids
     * @since Moodle 3.3
     */
    public static function get_non_respondents($peerassessid, $groupid = 0, $sort = 'lastaccess', $page = 0, $perpage = 0,
            $courseid = 0) {

        global $CFG;
        require_once($CFG->dirroot . '/mod/peerassess/lib.php');

        $params = array('peerassessid' => $peerassessid, 'groupid' => $groupid, 'sort' => $sort, 'page' => $page,
            'perpage' => $perpage, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_non_respondents_parameters(), $params);
        $warnings = $nonrespondents = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);
        $completioncourseid = $peerassesscompletion->get_courseid();

        if ($peerassess->anonymous != PEERASSESS_ANONYMOUS_NO || $peerassess->course == SITEID) {
            throw new moodle_exception('anonymous', 'peerassess');
        }

        // Check permissions.
        require_capability('mod/peerassess:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        if ($params['sort'] !== 'firstname' && $params['sort'] !== 'lastname' && $params['sort'] !== 'lastaccess') {
            throw new invalid_parameter_exception('Invalid sort param, must be firstname, lastname or lastaccess.');
        }

        // Check if we are page filtering.
        if ($params['perpage'] == 0) {
            $page = $params['page'];
            $perpage = PEERASSESS_DEFAULT_PAGE_COUNT;
        } else {
            $perpage = $params['perpage'];
            $page = $perpage * $params['page'];
        }
        $users = peerassess_get_incomplete_users($cm, $groupid, $params['sort'], $page, $perpage, true);
        foreach ($users as $user) {
            $nonrespondents[] = [
                'courseid' => $completioncourseid,
                'userid'   => $user->id,
                'fullname' => fullname($user),
                'started'  => $user->peerassessstarted
            ];
        }

        $result = array(
            'users' => $nonrespondents,
            'total' => peerassess_count_incomplete_users($cm, $groupid),
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_non_respondents return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_non_respondents_returns() {
        return new external_single_structure(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'The user id'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'started' => new external_value(PARAM_BOOL, 'If the user has started the attempt'),
                        )
                    )
                ),
                'total' => new external_value(PARAM_INT, 'Total number of non respondents'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_responses_analysis.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return the peerassess user responses.
     *
     * @param int $peerassessid peerassess instance id
     * @param int $groupid Group id, 0 means that the function will determine the user group
     * @param int $page the page of records to return
     * @param int $perpage the number of records to return per page
     * @param int $courseid course where user completes the peerassess (for site peerassesss only)
     * @return array of warnings and users attemps and responses
     * @throws moodle_exception
     * @since Moodle 3.3
     */
    public static function get_responses_analysis($peerassessid, $groupid = 0, $page = 0, $perpage = 0, $courseid = 0) {

        $params = array('peerassessid' => $peerassessid, 'groupid' => $groupid, 'page' => $page, 'perpage' => $perpage,
            'courseid' => $courseid);
        $params = self::validate_parameters(self::get_responses_analysis_parameters(), $params);
        $warnings = $itemsdata = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);

        // Check permissions.
        require_capability('mod/peerassess:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        $peerassessstructure = new mod_peerassess_structure($peerassess, $cm, $completioncourse->id);
        $responsestable = new mod_peerassess_responses_table($peerassessstructure, $groupid);
        // Ensure responses number is correct prior returning them.
        $peerassessstructure->shuffle_anonym_responses();
        $anonresponsestable = new mod_peerassess_responses_anon_table($peerassessstructure, $groupid);

        $result = array(
            'attempts'          => $responsestable->export_external_structure($params['page'], $params['perpage']),
            'totalattempts'     => $responsestable->get_total_responses_count(),
            'anonattempts'      => $anonresponsestable->export_external_structure($params['page'], $params['perpage']),
            'totalanonattempts' => $anonresponsestable->get_total_responses_count(),
            'warnings'       => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_responses_analysis return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_responses_analysis_returns() {
        $responsestructure = new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Response id'),
                    'name' => new external_value(PARAM_RAW, 'Response name'),
                    'printval' => new external_value(PARAM_RAW, 'Response ready for output'),
                    'rawval' => new external_value(PARAM_RAW, 'Response raw value'),
                )
            )
        );

        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'userid' => new external_value(PARAM_INT, 'User who responded'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified for the response'),
                            'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalattempts' => new external_value(PARAM_INT, 'Total responses count.'),
                'anonattempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Completed id'),
                            'courseid' => new external_value(PARAM_INT, 'Course id'),
                            'number' => new external_value(PARAM_INT, 'Response number'),
                            'responses' => $responsestructure
                        )
                    )
                ),
                'totalanonattempts' => new external_value(PARAM_INT, 'Total anonymous responses count.'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_last_completed.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_last_completed_parameters() {
        return new external_function_parameters (
            array(
                'peerassessid' => new external_value(PARAM_INT, 'Peerassess instance id'),
                'courseid' => new external_value(PARAM_INT, 'Course where user completes the peerassess (for site peerassesss only).',
                    VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Retrieves the last completion record for the current user.
     *
     * @param int $peerassessid peerassess instance id
     * @return array of warnings and the last completed record
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_last_completed($peerassessid, $courseid = 0) {
        global $PAGE;

        $params = array('peerassessid' => $peerassessid, 'courseid' => $courseid);
        $params = self::validate_parameters(self::get_last_completed_parameters(), $params);
        $warnings = array();

        list($peerassess, $course, $cm, $context, $completioncourse) = self::validate_peerassess($params['peerassessid'],
            $params['courseid']);
        $peerassesscompletion = new mod_peerassess_completion($peerassess, $cm, $completioncourse->id);

        if ($peerassesscompletion->is_anonymous()) {
             throw new moodle_exception('anonymous', 'peerassess');
        }
        if ($completed = $peerassesscompletion->find_last_completed()) {
            $exporter = new peerassess_completed_exporter($completed);
            return array(
                'completed' => $exporter->export($PAGE->get_renderer('core')),
                'warnings' => $warnings,
            );
        }
        throw new moodle_exception('not_completed_yet', 'peerassess');
    }

    /**
     * Describes the get_last_completed return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_last_completed_returns() {
        return new external_single_structure(
            array(
                'completed' => peerassess_completed_exporter::get_read_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }
}
