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
 * Prints a particular instance of zoom
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents, 2023 Giorgio Consorti
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$meetingcontent = (bool) optional_param('meetingcontent', false, PARAM_BOOL);
$zoomLeave = (bool) optional_param('zoomLeave', false, PARAM_BOOL);
$showrecreate = false;

/**
 * @var \moodle_page $PAGE
 * @var \core_renderer $OUTPUT
 */
global $PAGE, $OUTPUT, $USER;

require_login();

// Additional access checks in zoom_get_instance_setup().
[$course, $cm, $zoom] = zoom_get_instance_setup();
// Get meeting state from Zoom.
[$inprogress, $available, $finished] = zoom_get_state($zoom);
// Get Zoom user ID of current Moodle user.
$zoomuserid = zoom_get_user_id(false);
// Check if this user is the (real) host.
$userisrealhost = ($zoomuserid === $zoom->host_id);
// Get the alternative hosts of the meeting.
$alternativehosts = zoom_get_alternative_host_array_from_string($zoom->alternative_hosts);
// Check if this user is the host or an alternative host.
$userishost = ($userisrealhost || in_array(zoom_get_api_identifier($USER), $alternativehosts, true));

// html for the template.
$outhtml = '';

if (!$meetingcontent) {
    $config = get_config('zoom');
    $context = context_module::instance($cm->id);
    $iszoommanager = has_capability('mod/zoom:addinstance', $context);

    $showrecreate = false;
    if ($zoom->exists_on_zoom == ZOOM_MEETING_EXPIRED) {
        $showrecreate = true;
    } else {
        try {
            zoom_webservice()->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
        } catch (\mod_zoom\webservice_exception $error) {
            $showrecreate = zoom_is_meeting_gone_error($error);

            if ($showrecreate) {
                // Mark meeting as expired.
                $updatedata = new stdClass();
                $updatedata->id = $zoom->id;
                $updatedata->exists_on_zoom = ZOOM_MEETING_EXPIRED;
                $DB->update_record('zoom', $updatedata);

                $zoom->exists_on_zoom = ZOOM_MEETING_EXPIRED;
            }
        } catch (moodle_exception $error) {
            // Ignore other exceptions.
            debugging($error->getMessage());
        }
    }

    $PAGE->set_title(format_string($zoom->name));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_url('/mod/zoom/webmeeting.php', ['id' => $cm->id]);
    $PAGE->activityheader->set_attrs([
        "description" => '',
        "hidecompletion" => true
    ]);
}

if ($zoomLeave) {

    crossOriginIsolation();

    $js[] = html_writer::tag(
        'script',
        'window.parent.postMessage({
            "message" : "zoomLeave",
            "userishost" : ' . ($userishost ? 'true' : 'false') . ',
            "zoomLeave" : "' . (new moodle_url('/mod/zoom/view.php', ['id' => $cm->id]))->out() . '",
            "debugging" : ' . (debugging() ? 'true' : 'false') . ',
        })'
    );

    $htmllang = '';
    $head = '';
    $content = implode(PHP_EOL . "\t", $js);

    ob_start();
    include(__DIR__ . '/zoom-clientview.php');
    $outhtml = ob_get_contents();
    ob_end_clean();
} else if (!$zoomLeave && !$showrecreate && $available && !$finished) {

    crossOriginIsolation();

    if ($meetingcontent) {

        if (function_exists('get_string') && function_exists('get_html_lang')) {
            $htmllang = get_html_lang();
        } else {
            $htmllang = '';
        }

        [$css, $js] = zoom_webmeeting_get_css_js();

        $css[] = html_writer::tag(
            'style',
            'html, body { min-width: 0 !important; }
        #zmmtg-root {
        display: none;
        min-width: 0 !important;}'
        );

        $js[] = html_writer::tag('script', '', [
            'src' => debugging() ?
                new moodle_url('/mod/zoom/amd/src/webmeeting.js') :
                new moodle_url('/mod/zoom/amd/build/webmeeting.min.js'),
        ]);

        $head = implode(PHP_EOL . "\t", $css);
        $content = implode(PHP_EOL . "\t", $js);

        ob_start();
        include(__DIR__ . '/zoom-clientview.php');
        $outhtml = ob_get_contents();
        ob_end_clean();
    } else {

        $PAGE->requires->css('/mod/zoom/css/webmeeting.css');
        if (debugging()) {
            $PAGE->requires->js('/mod/zoom/amd/src/webmeeting.js');
        } else {
            $PAGE->requires->js('/mod/zoom/amd/build/webmeeting.min.js');
        }
        $PAGE->set_secondary_navigation(false);

        $iframe = html_writer::tag(
            'iframe',
            '',
            [
                'onload' => 'this.contentWindow.postMessage({
                    "message": "init",
                    "zoom" : ' . json_encode([
                    'userishost' => $userishost,
                    'signature' => zoom_webmeeting_get_signature($zoom, $userishost),
                    'sdkKey' => $config->meetingsdkid ?? 0,
                    'meeting_id' => $zoom->meeting_id,
                    'password' => $zoom->password,
                    'zak' => ($userishost ? zoom_meeting_get_host_zak() : null),
                    'tk' => ($userishost ? null : get_registrant_tk($zoom)),
                    'leaveUrl' => (new moodle_url('/mod/zoom/webmeeting.php', ['id' => $cm->id, 'zoomLeave' => 1, 'meetingcontent' => 1]))->out(),
                ], JSON_HEX_QUOT) . ',
                    "user" : ' . json_encode([
                    'fullname' => fullname($USER),
                    'email' => $USER->email,
                    'lang' => $USER->lang,
                ], JSON_HEX_QUOT) . ',
                    "zoomSdkVersion" : "' . ZOOM_MEETING_SDK_WEB_VERSION . '",
                    "debugging" : ' . (debugging() ? 'true' : 'false') . ',
                });',
                'src' => new moodle_url('/mod/zoom/webmeeting.php', ['id' => $cm->id, 'meetingcontent' => 1]),
                'id' => 'meetingSDKElement',
                'class' => 'responsive-iframe',
                'width' => '100%',
                'height' => '645',
                'frameBorder' => '0',
                'allowtransparency' => 'true',
                'allowfullscreen' => 'true',
                'allow' => 'camera; microphone;',
                'sandbox' => 'allow-forms allow-scripts allow-same-origin',
            ]
        );
        $outhtml = html_writer::div($iframe, 'iframe-container');
    }
} else if ($showrecreate) {
    // Only show recreate/delete links in the message for users that can edit.
    if ($iszoommanager) {
        $message = get_string('zoomerr_meetingnotfound', 'mod_zoom', zoom_meetingnotfound_param($cm->id));
        $style = \core\output\notification::NOTIFY_ERROR;
    } else {
        $message = get_string('zoomerr_meetingnotfound_info', 'mod_zoom');
        $style = \core\output\notification::NOTIFY_WARNING;
    }
    $outhtml = $OUTPUT->notification($message, $style);
} else {
    // Get unavailability note.
    $unavailabilitynote = zoom_get_unavailability_note($zoom, $finished);
    // Show unavailability note.
    // Ideally, this would use $OUTPUT->notification(), but this renderer adds a close icon to the notification which does not
    // make sense here. So we build the notification manually.
    $link = html_writer::tag('div', $unavailabilitynote, ['class' => 'alert alert-primary']);
    $outhtml =  $OUTPUT->box_start('generalbox text-center') . $link . $OUTPUT->box_end();
}

// Output starts here.
// Print the page header.
echo !$meetingcontent ? $OUTPUT->header() : '';
// Print the page html.
echo $outhtml;
// Finish the page.
echo !$meetingcontent ? $OUTPUT->footer() : '';
