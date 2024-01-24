<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
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
 * List all zoom meetings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/mod_form.php');
require_once($CFG->libdir . '/moodlelib.php');

$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if (empty($id)) {
    throw new \moodle_exception('unspecifycourseid', 'error');
}

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
$ccontext = context_course::instance($course->id);
$pageurl = new moodle_url('/mod/zoom/ownreport.php', ['id' => $course->id]);
$pageheading = get_string('viewownreportlink', 'mod_zoom');
\mod_customcert\page_helper::page_setup($pageurl, $ccontext, "$course->shortname: $pageheading");

$PAGE->set_title("$course->shortname: $pageheading");
$PAGE->set_heading($course->fullname);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

// Check capability.
require_login($course);
require_capability('mod/zoom:viewownreport', $ccontext);

$isTeacher = is_siteadmin() || count(array_intersect(
    array_map(fn($el) => $el->shortname, get_user_roles($ccontext, $USER->id, false)),
    ['editingteacher', 'teacher']
)) > 0;

if (empty($userid)) {
    $userid = $USER->id;
} else {
    // Check capability.
    require_capability('mod/zoom:addinstance', $ccontext);
    $user = $DB->get_record('user', ['id' => $userid]);
    $pageheading .= ' '.strtolower(get_string('for')) . ' ' . fullname($user);
}

$maskparticipantdata = get_config('zoom', 'maskparticipantdata');
// If participant data is masked then display a message stating as such and be done with it.
if ($maskparticipantdata) {
    zoom_fatal_error(
        'participantdatanotavailable_help',
        'mod_zoom',
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

// get own report data, one array element per course meeting
$data = get_zoom_report_data($course->id, 'duration', false, $userid);

// Display page contents if there is some data.
if (empty($data['meetings'])) {
    echo $OUTPUT->header();
    notice(get_string('nozoomsfound', 'mod_zoom'), new moodle_url('/course/view.php', ['id' => $course->id]));
} else {
    $PAGE->requires->css('/mod/zoom/css/webmeeting.css');
    echo $OUTPUT->header();
    echo $OUTPUT->heading($pageheading);

    // true if first accordion element must be expanded
    $expandFirst = false;
    $detailsHtml = [];
    $totalDurations = [
        'meetings' => 0,
        'user' => 0,
        'provided' => 0,
    ];

    if (!empty($data['reportlastupdate'])) {
        echo html_writer::tag(
            'h3',
            get_string(
                'ownreportlastupdate',
                'mod_zoom',
                userdate($data['reportlastupdate'], get_string('strftimedate', 'langconfig'))
            )
        );
    }

    foreach($data['meetings'] as $meetingData) {
        // output a table foreach course meeting.
        $sessions = $meetingData['sessions'];

        $totalDurations['meetings'] += $meetingData['meeting']->duration;
        $totalDurations['user'] += min([$meetingData['meeting']->duration, $meetingData['users'][$userid]->mergedDuration]);
        $totalDurations['provided'] += ($meetingData['meeting']->start_time <= time() ? $meetingData['meeting']->duration : 0);
        $userDuration = min([$meetingData['meeting']->duration, $meetingData['users'][$userid]->mergedDuration]) ?? 0;

        $divAttrs = (!$isTeacher || empty($sessions) || $userDuration <=0) ? [] : [
            'data-toggle' => 'collapse',
            'data-target' => '#tab-' . $meetingData['meeting']->id,
            'aria-expanded' => $expandFirst ? 'true' : 'false',
            'aria-controls' => 'tab-' . $meetingData['meeting']->id,
        ];
        $divClass = (!$isTeacher || empty($sessions) || $userDuration <=0) ? '' : ' accordion-heading';
        $cardHeaderAttrs = (!$isTeacher || empty($sessions) || $userDuration <=0) ? null : [
            'class' => 'accordion-btn',
        ];

        $detailsHtml[$meetingData['meeting']->id] = html_writer::div(
            html_writer::tag(
                'h4',
                userdate($meetingData['meeting']->start_time, get_string('strftimedatefullshort', 'langconfig')) .
                ': ' .
                $meetingData['meeting']->name,
                $cardHeaderAttrs
            ),
            'card-header' . $divClass,
            $divAttrs
        );

        if (empty($sessions)) {
            $detailsHtml[$meetingData['meeting']->id] .= html_writer::div(
                get_string('nomeetinginstances', 'mod_zoom').' '.
                get_string('expectedmeetingduration', 'mod_zoom', secondsToHMS($meetingData['meeting']->duration)),
                'card-footer'
            );
        } else {
            $table = new html_table();
            $table->head = [
                get_string('jointime', 'mod_zoom'),
                get_string('leavetime', 'mod_zoom'),
                strtok(get_string('duration', 'mod_zoom'), ' ') . ' (hh:mm:ss)', // first word of string
            ];
            foreach ($sessions as $uuid => $session) {
                // participant will be current user only
                $participants = $session->participants;
                foreach ($participants as $p) {
                    if ($p->status == 'in_meeting') {
                        $row = [];

                        // Join/leave times.
                        $row[] = userdate($p->join_time, get_string('strftimedatetimeshortaccurate', 'langconfig'));
                        $row[] = userdate($p->leave_time, get_string('strftimedatetimeshortaccurate', 'langconfig'));

                        // Real Duration, not as if it was computed by other mod_zoom components.
                        $duration = $p->leave_time - $p->join_time;
                        // convert duration to hh:mm:ss
                        $secs = $duration % 60;
                        $hrs = $duration / 60;
                        $mins = $hrs % 60;
                        $hrs = $hrs / 60;
                        $row[] = secondsToHMS($duration);

                        $table->data[] = $row;
                    }
                }
            }

            $detailsHtml[$meetingData['meeting']->id] .= html_writer::div(
                html_writer::table($table),
                'card-body collapse' . ($expandFirst ? ' show' : ''),
                [
                    'id' => 'tab-' . $meetingData['meeting']->id,
                ]
            );

            $percentDuration = min([1, $meetingData['users'][$userid]->mergedDuration / $meetingData['meeting']->duration]);
            $a = (object) [
                'userDuration' => secondsToHMS($userDuration),
                'meetingDuration' => secondsToHMS($meetingData['meeting']->duration),
                'percentDuration' => round(100 * $percentDuration ,1) . '%',
                'percentClass' => $percentDuration >= 0.8 ? 'pass' : 'fail',
            ];

            $detailsHtml[$meetingData['meeting']->id] .= html_writer::div(
                get_string('ownreportmeetingfooter', 'mod_zoom', $a),
                'card-footer'
            );
        }
        $expandFirst = false;
    }

    $a = (object) [
        'total' => secondsToHMS(ZOOM_DEFAULT_COURSE_DURATION),
        'provided' => secondsToHMS($totalDurations['provided']),
        'max_abscence' => secondsToHMS(ZOOM_MAX_ALLOWED_ABSENCE),
        'user' => secondsToHMS($totalDurations['user']),
        'user_absence' => secondsToHMS($totalDurations['provided'] - $totalDurations['user']),
    ];

    echo html_writer::div(get_string('ownreportdatawarning', 'mod_zoom'), 'alert alert-warning');
    echo html_writer::div(get_string('ownreportsummary', 'zoom', $a), 'alert alert-success');

    // output details info, with cards and tables
    foreach ($detailsHtml as $detail) {
        echo html_writer::div($detail, 'ownreport-el-container card');
    }
}
echo $OUTPUT->footer();
