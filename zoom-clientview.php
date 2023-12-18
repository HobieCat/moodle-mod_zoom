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
 * Moodle Generic plain page
 *
 * This is used for various pages, usually errors, early in the Moodle
 * bootstrap. It can be safetly customized by editing this file directly
 * but it MUST NOT contain any Moodle resources such as theme files generated
 * by php, it can only contain references to static css and images, and as a
 * precaution its recommended that everything is inlined rather than
 * references. This is why this file is located here as it cannot be inside
 * a Moodle theme.
 *
 * @package    core
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignoreFile

defined('MOODLE_INTERNAL') || die();

?>
<!DOCTYPE html>
<html <?php echo $htmllang ?>>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <?php echo $head ?? '' ?>

    <title><?php echo $title ?? '' ?></title>
</head>

<body>
    <?php echo $content ?>

</body>

</html>