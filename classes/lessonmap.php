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
 * @package    local_webuntis
 * @copyright  2021 Zentrum für Lernmanagement (www.lernmanagement.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_webuntis;

defined('MOODLE_INTERNAL') || die;

class lessonmap {
    private static $cacheidentifier;
    private static $debug;
    private static $isloaded = false;
    private static $lessonmaps;

    /**
     * Load a lessonmap.
     * @param lesson the lesson identifier. -1 loads from cache.
     */
    public static function __load($lesson_id = -1) {
        global $debug; self::$debug = $debug;
        global $DB;

        if ($lesson_id == -1) {
            $lesson_id = \local_webuntis\locallib::cache_get('session', 'lesson_id');
        } else {
            \local_webuntis\locallib::cache_set('session', 'lesson_id', $lesson_id);
        }
        $params = [
            'tenant_id' => \local_webuntis\tenant::get_tenant_id(),
            'lesson_id' => $lesson_id,
        ];
        if (empty(self::$cacheidentifier)) {
            self::$cacheidentifier = "lessonmaps_{$params['tenant_id']}_{$params['lesson_id']}";
        }

        self::$lessonmaps = \local_webuntis\locallib::cache_get('session', self::$cacheidentifier);
        if (empty(self::$lessonmaps) || count(self::$lessonmaps) == 0) {
            self::$lessonmaps = array_values($DB->get_records('local_webuntis_coursemap', $params));
            \local_webuntis\locallib::cache_set('session', self::$cacheidentifier, self::$lessonmaps);
        }
        if (self::$debug) {
            echo "Found lessonmap\n";
            echo "<pre>" . print_r(self::$lessonmaps, 1) . "</pre>\n";
        }
        self::$isloaded = true;
    }

    /**
     * Check if user can edit this lessonmap.
     */
    public static function can_edit() {
        self::is_loaded();

        $editroles = [ 'Administrator' ];
        if (self::get_lesson_id() > 0) {
            $editroles[] = 'Teacher';
        }
        return (in_array(\local_webuntis\usermap::get_remoteuserrole(), $editroles));
    }

    /**
     * Add or remove a course from map.
     */
    public static function change_map($courseid) {
        self::is_loaded();
        global $DB;

        $dbparams = array(
            'tenant_id' => \local_webuntis\tenant::get_tenant_id(),
            'lesson_id' => self::get_lesson_id(),
            'courseid' => $courseid
        );
        if (empty($dbparams['tenant_id']) || empty($dbparams['lesson_id'])) return;
        if ($courseid < 0) {
            // We want to remove it.
            $dbparams['courseid'] = $dbparams['courseid'] * -1;
            $DB->delete_records('local_webuntis_coursemap', $dbparams);
            for ($a = count(self::$lessonmaps) -1; $a >= 0; $a--) {
                if (self::$lessonmaps[$a]->courseid == $dbparams['courseid']) {
                    unset(self::$lessonmaps[$a]);
                }
            }
        } else {
            $chk = $DB->get_record('local_webuntis_coursemap', $dbparams);
            if (empty($chk->id)) {
                $dbparams['id'] = $DB->insert_record('local_webuntis_coursemap', $dbparams);
            } else {
                $dbparams['id'] = $chk->id;
            }
            $found = false;
            for ($a = count(self::$lessonmaps) -1; $a >= 0; $a--) {
                if (self::$lessonmaps[$a]->courseid == $dbparams['courseid']) {
                    $found = true;
                }
            }
            if (!$found) {
                self::$lessonmaps[] = (object) $dbparams;
            }
        }
        self::$lessonmaps = array_values(self::$lessonmaps);
        \local_webuntis\locallib::cache_set('session', self::$cacheidentifier, self::$lessonmaps);
    }

    /**
     * Get the cacheidentifier.
     */
    public static function get_cacheidentifier() {
        self::is_loaded();
        return self::$cacheidentifier;
    }

    /**
     * Get the amount of courses in this map.
     */
    public static function get_count() {
        self::is_loaded();
        return count(self::$lessonmaps);
    }

    public static function get_courses() {
        self::is_loaded();
        $courses = array();
        for ($a = 0; $a < count(self::$lessonmaps); $a++) {
            $courseid = self::$lessonmaps[$a]->courseid;

            $course = \get_course($courseid);
            $course = new \core_course_list_element($course);
            $courses[$course->fullname] = (object) array(
                'courseimage' => \local_webuntis\locallib::get_courseimage($courseid),
                'fullname' => $course->fullname,
                'id' => $courseid,
                'shortname' => $course->shortname,
            );
        }
        ksort($courses);

        return array_values($courses);
    }

    public static function get_edit_url() {
        if (self::can_edit()) {
            $params = [
                //'lesson'     => self::get_lesson(),
                //'noredirect' => 1,
                //'tenant_id'  => \local_webuntis\tenant::get_tenant_id(),
            ];
            $editurl = new \moodle_url('/local/webuntis/landingedit.php', $params);
            return $editurl;
        }
        return '';
    }

    /**
     * Get the lesson information from cache.
     */
    public static function get_lesson_id() {
        self::is_loaded();
        return \local_webuntis\locallib::cache_get('session', 'lesson_id');
    }

    /**
     * Ensure object was loaded.
     */
    public static function is_loaded() {
        if (!self::$isloaded) self::__load();
    }

    /**
     * Check whether or not a course is selected in this mapping.
     * @param courseid
     */
    public static function is_selected($courseid) {
        self::is_loaded();
        foreach (self::$lessonmaps as $lessonmap) {
            if ($lessonmap->courseid == $courseid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Redirect user to appropriate target.
     */
    public static function redirect() {
        global $USER;
        if (!self::$isloaded) self::__load();
        $lessonmaps = self::$lessonmaps;

        if (\local_webuntis\usermap::get_userid() != $USER->id || isguestuser() || !isloggedin()) {
            return;
        }

        // @todo check enrolment of user in all mapped lessons.

        if (!empty($lessonmaps) && count($lessonmaps) > 1) {
            // Redirect to selection list.
            $url = new \moodle_url('/local/webuntis/landing.php', array());
            \redirect($url);
        }
        if (!empty($lessonmaps) && !empty($lessonmaps[0]->courseid)) {
            $url = new \moodle_url('/course/view.php', array('id' => $lessonmaps[0]->courseid));
            if (\local_webuntis\lessonmap::can_edit()) {
                $editurl = \local_webuntis\lessonmap::get_edit_url();
                $strparams = array('editurl' => $editurl->__toString());
                \redirect($url, get_string('redirect_edit_landingpage', 'local_webuntis', $strparams), 0, \core\output\notification::NOTIFY_INFO);
            } else {
                \redirect($url);
            }
        }
    }


}