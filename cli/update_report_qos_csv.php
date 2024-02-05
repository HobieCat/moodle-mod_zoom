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
 * CLI script to manually get the meeting report.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents, 2023 Giorgio Consorti
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\oauth2\rest;
use core_calendar\local\event\forms\update;
use Phpml\Math\Matrix;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');
require_once(__DIR__ . '/../locallib.php');
require_once($CFG->libdir . '/clilib.php');

/** @var \moodle_database $DB */

// Now get cli options.
[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'filename' => false,
        'adminfiles' => false,
    ],
    [
        'h' => 'help',
        'file' => 'filename',
        'adm' => 'adminfiles',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['filename']) || empty($options['adminfiles'])) {
    $help = "CLI script to update the meeting report data from a QoS CSV downloaded from the zoom Dashboard.

Options:
-h, --help              Print out this help
-file, --filename        Full path to a downloaded CSV file
-adm, --adminfiles       Use files uploaded to the admins moodle private area

Examples:
\$sudo -u www-data /usr/bin/php mod/zoom/cli/update_report_qos_csv.php --filename=BreakoutRoom_UserQos_933_3240_3193.csv
\$sudo -u www-data /usr/bin/php mod/zoom/cli/update_report_qos_csv.php --adminfiles=1
";
    cli_error($help);
}

// Turn on debugging.
set_debugging(DEBUG_DEVELOPER, true);

if (!empty($options['filename'])) {
    updateReportFromQOSCSV($options['filename']);
} else if (!empty($options['adminfiles'])) {
    $fs = get_file_storage();
    $admins = get_admins();
    foreach ($admins as $i => $admin) {
        mtrace(str_repeat('*', 20). " processing admin: $admin->username (userid: $admin->id)");

        $context = context_user::instance($admin->id);
        $files = array_filter(
            $fs->get_area_files($context->id, 'user', 'private'),
            fn($el) => str_starts_with($el->get_filename(), 'BreakoutRoom') && str_ends_with($el->get_filename(), '.csv')
        );

        if (!empty($files)) {
            /**
             * @var \stored_file $file
             */
            foreach ($files as $file) {
                mtrace("update from " . $file->get_filename());
                try {
                    $results = updateReportFromQOSCSV($file->get_content_file_handle());
                    mtrace(sprintf("done file %s: %d rows total, %d skipped, %d added, %d not added",
                    $file->get_filename(), ...array_values($results)));
                    $file->rename($file->get_filepath(), time() . '_' . $file->get_filename());
                    // $file->delete();
                    mtrace("renamed to " . $file->get_filename());
                } catch (\moodle_exception $e) {
                    mtrace(sprintf("EXCEPTION THROWN in file %s, line %d\nMessage:\n%s\nPLEASE DELETE THE FILE MANUALLY IF NEEDED.",
                        $e->getFile(), $e->getLine(), $e->getMessage()
                    ));
                }
            }
        } else {
            mtrace("$admin->username has no csv filename starting with 'BreakoutRoom'.");
        }

        mtrace(str_repeat('*', 20). " done admin: $admin->username (userid: $admin->id)");
    }
}
cli_writeln('DONE!');
