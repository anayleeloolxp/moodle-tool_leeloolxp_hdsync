<?php
// This file is part of
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
 * Admin settings and defaults
 *
 * @package    tool_leeloolxp_hdsync
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author Leeloo LXP <info@leeloolxp.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');
global $CFG;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

require_login();
admin_externalpage_setup('toolleeloolxp_hdsync');

global $SESSION;

$postcourses = optional_param('courses', null, PARAM_RAW);

$leeloolicensekey = get_config('tool_leeloolxp_hdsync', 'leeloolicensekey');

$leeloobase = 'https://leeloolxp.com/api/moodle_departments_plugin/';

if ($postcourses) {
    foreach ($postcourses as $postcourseid => $postcourse) {
        $role = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $context = get_context_instance(CONTEXT_COURSE, $postcourseid);
        $teachers = json_encode(get_role_users($role->id, $context));

        if ($postcourse == 0) {
            $leeloodept = $DB->get_record_sql("SELECT deptid FROM {tool_leeloolxp_hdsync} WHERE courseid = ?", [$postcourseid]);

            $deptid = $leeloodept->deptid;
            $course = $DB->get_record_sql("SELECT fullname FROM {course} where id = ?", [$postcourseid]);

            $post = [
                'license_key' => base64_encode($leeloolicensekey),
                'action' => base64_encode('update'),
                'deptid' => base64_encode($deptid),
                'coursename' => base64_encode($course->fullname),
                'status' => base64_encode('0'),
                'teachers' => ($teachers),
            ];

            $url = $leeloobase . 'sync_department.php';
            $curl = new curl;
            $options = array(
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => false,
                'CURLOPT_POST' => count($post),
                'CURLOPT_POSTFIELDS' => $post,
            );

            $response = $curl->post($url, $post, $options);

            $infoleeloo = json_decode($response);
            if ($infoleeloo->status == 'true') {
                $DB->execute("UPDATE {tool_leeloolxp_hdsync} SET enabled = 0 WHERE courseid = ?", [$postcourseid]);
            }
        }

        if ($postcourse == 1) {
            $hdourse = $DB->get_record_sql("SELECT COUNT(*) countcourse FROM {tool_leeloolxp_hdsync} WHERE courseid = ?", [$postcourseid]);

            if ($hdourse->countcourse == 0) {
                $course = $DB->get_record_sql("SELECT fullname FROM {course} where id = ?", [$postcourseid]);

                $post = [
                    'license_key' => base64_encode($leeloolicensekey),
                    'action' => base64_encode('insert'),
                    'courseid' => base64_encode($postcourseid),
                    'coursename' => base64_encode($course->fullname),
                    'teachers' => ($teachers),
                ];

                $url = $leeloobase . 'sync_department.php';
                $curl = new curl;
                $options = array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_POST' => count($post),
                    'CURLOPT_POSTFIELDS' => $post,
                );

                $response = $curl->post($url, $post, $options);

                $infoleeloo = json_decode($response);
                if ($infoleeloo->status == 'true') {
                    $deptid = $infoleeloo->data->id;
                    $DB->execute("INSERT INTO {tool_leeloolxp_hdsync} (courseid, deptid, enabled)VALUES (?, ?, ?)", [$postcourseid, $deptid, 1]);
                }
            } else {

                $leeloodept = $DB->get_record_sql("SELECT deptid FROM {tool_leeloolxp_hdsync} WHERE courseid = ?", [$postcourseid]);

                $deptid = $leeloodept->deptid;
                $course = $DB->get_record_sql("SELECT fullname FROM {course} where id = ?", [$postcourseid]);

                $post = [
                    'license_key' => base64_encode($leeloolicensekey),
                    'action' => base64_encode('update'),
                    'deptid' => base64_encode($deptid),
                    'coursename' => base64_encode($course->fullname),
                    'status' => base64_encode('1'),
                    'teachers' => ($teachers),
                ];

                $url = $leeloobase . 'sync_department.php';
                $curl = new curl;
                $options = array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_POST' => count($post),
                    'CURLOPT_POSTFIELDS' => $post,
                );

                $response = $curl->post($url, $post, $options);

                $infoleeloo = json_decode($response);
                if ($infoleeloo->status == 'true') {
                    $DB->execute("UPDATE {tool_leeloolxp_hdsync} SET enabled = ? WHERE courseid = ?", [1, $postcourseid]);
                }
            }
        }
    }
}

$courses = $DB->get_records_sql("SELECT c.id,c.fullname,wd.enabled FROM {course} c LEFT JOIN {tool_leeloolxp_hdsync} wd ON c.id = wd.courseid ORDER BY c.id ASC");

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('leeloolxp_hdsync', 'tool_leeloolxp_hdsync'), 'leeloolxp_hdsync', 'tool_leeloolxp_hdsync');
if (!empty($error)) {
    echo $OUTPUT->container($error, 'leeloolxp_hdsync_myformerror');
}

if (!empty($courses)) {
    echo '<form method="post"><ul>';
    foreach ($courses as $course) {
        $courseid = $course->id;
        $coursefullname = $course->fullname;
        $courseenabled = $course->enabled;
        if ($courseenabled == 1) {
            $checkboxchecked = 'checked';
        } else {
            $checkboxchecked = '';
        }
        echo '<li>';
        echo "<input type='hidden' value='0' name='courses[$courseid]'>";
        echo "<input $checkboxchecked id='course_$courseid' type='checkbox' name='courses[$courseid]' value='1'>";
        echo "<label for='course_$courseid'>$coursefullname</label>";
        echo '</li>';
    }
    echo '</ul><button type="submit" value="Save and Create Departments">Submit</button></form>';
}

echo $OUTPUT->footer();
