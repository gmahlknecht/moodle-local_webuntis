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
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$userid = optional_param('userid', 0, PARAM_INT);

\local_webuntis\tenant::__load();

if (!empty($userid)) {
    $PAGE->set_context(\context_user::instance($USER->id));
    $PAGE->set_url(new \moodle_url('/local/webuntis/disconnect.php', array('userid' => $userid)));
    $PAGE->set_title(get_string('disconnect:user', 'local_webuntis'));
    $PAGE->set_heading(get_string('disconnect:user', 'local_webuntis'));
    $PAGE->set_pagelayout('standard');

    if ($USER->id != $userid) {
        throw new \moodle_exception('invalidinput', 'local_webuntis', $CFG->wwwroot);
    }
}
// @todo show confirmation dialog prior to action.
$confirmed = optional_param('confirmed', 1, PARAM_INT);

if (empty($confirmed)) {
    echo $OUTPUT->header();


    echo $OUTPUT->footer();
} else {
    if (!empty($userid)) {
        \local_webuntis\usermap::release();
        \local_webuntis\locallib::cache_preserve(true);
        require_logout();
        \local_webuntis\locallib::cache_preserve(false);

        $url = new \moodle_url('/local/webuntis/disconnected.php');
        redirect($url);
    }

}