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
 * Zoom handler for event observers.
 *
 * @package   mod_zoom
 * @copyright 2023 Giorgio Consorti
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use core\event\user_loggedout;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class zoom_handler
{
    /**
     * Event processor - user loggedout
     * @param \core\event\user_loggedout $event
     *
     */
    public static function loggedout(user_loggedout $event)
    {
        $logoutUrl = new moodle_url('/login/logout.php', ['sesskey' => $_GET['sesskey']]);
        $htmllang = '';
        $head = '<meta http-equiv="refresh" content="3; url=' . $logoutUrl . '" />';

        $content = "<script>const zoomKeys = window.sessionStorage.getItem('zoomKeys') ?? '';
            if (zoomKeys.length) {
              zoomKeys.split(',').forEach((key) => window.sessionStorage.removeItem(key));
              window.sessionStorage.removeItem('zoomKeys');
            }
            window.location.replace('$logoutUrl');</script>";

        ob_start();
        include(__DIR__ . '/../zoom-clientview.php');
        $outhtml = ob_get_contents();
        ob_end_clean();
        echo $outhtml;
        // the outhtml code will take care of actual logout.
        die();
    }
}
