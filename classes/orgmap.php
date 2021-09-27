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

namespace local_webuntis;

defined('MOODLE_INTERNAL') || die;

class orgmap {
    private $orgmap;

    public function __construct() {
        global $DB, $TENANT;

        if (empty($TENANT->get_tenant_id())) {
            return;
        }

        if (empty($TENANT->tenantdata->orgmap)) {
            self::load_orgmap();
        }
    }

    /**
     * Convert webuntis role to eduvidual-role.
     * @param webuntisrole
     */
    public static function convert_role($webuntisrole) {
        $roles = [ 'student', 'parent', 'teacher', 'administrator' ];
        $role = $webuntisrole;
        if (!in_array($role, $roles)) {
            $role = 'student';
        }
        if ($role == 'administrator') {
            $role = 'Manager';
        } else {
            $role = ucfirst($role);
        }
        return $role;
    }

    public function get_orgmap() {
        global $TENANT;
        return $TENANT->tenantdata->orgmap;
    }

    /**
     * Check if at least on orgmap allows autoenrol.
     */
    public function has_autoenrol() {
        global $TENANT;
        foreach ($this->get_orgmap() as $orgmap) {
            if (!empty($orgmap->autoenrol)) {
                return true;
            }
        }
        return false;
    }

    private static function load_orgmap() {
        global $DB, $TENANT;
        $params = [ 'tenant_id' => $TENANT->get_tenant_id()];
        $TENANT->tenantdata->orgmap = array_values($DB->get_records('local_webuntis_orgmap', $params));
        $TENANT->to_cache();
    }

    public static function load_from_eduvidual() {
        global $DB, $TENANT;
        if (!\local_webuntis\locallib::uses_eduvidual() || empty($TENANT->get_tenant_id())) {
            return;
        }

        $orgs = \local_eduvidual\locallib::get_organisations('Manager', false);
        foreach ($orgs as $org) {
            $params = [ 'orgid' => $org->orgid, 'tenant_id' => $TENANT->get_tenant_id()];
            $orgmap = $DB->get_record('local_webuntis_orgmap', $params);
            if (empty($orgmap->id)) {
                $orgmap = (object)[
                    'autoenrol' => 0,
                    'orgid' => $org->orgid,
                    'tenant_id' => $TENANT->get_tenant_id(),
                ];
                $orgmap->id = $DB->insert_record('local_webuntis_orgmap', $orgmap);
            }
        }
        self::load_orgmap();
    }

    public static function map_role($user) {
        global $DB, $TENANT;
        if (empty($user->identifier) || !\local_webuntis\locallib::uses_eduvidual() || empty($TENANT->get_tenant_id())) {
            return;
        }

        $params = [
            'tenant_id' => $TENANT->get_tenant_id(),
            'remoteuserid' => $user->identifier,
        ];
        $usermap = $DB->get_record('local_webuntis_usermap', $params);
        if (empty($usermap->userid)) {
            return;
        }

        self::map_role_usermap($usermap);
    }
    /**
     * Map role based on usermap.
     * @param usermap
     */
    public static function map_role_usermap($usermap) {
        global $TENANT;
        if (empty($usermap->role) || !\local_webuntis\locallib::uses_eduvidual() || empty($TENANT->get_tenant_id())) {
            return;
        }

        $role = self::convert_role($usermap->role);
        foreach ($TENANT->tenantdata->orgmap as $orgmap) {
            if (!empty($orgmap->autoenrol)) {
                \local_eduvidual\lib_enrol::role_set($usermap->userid, $orgmap->orgid, $role);
            }
        }
    }
}