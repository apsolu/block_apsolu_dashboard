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
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO: fichier à supprimer.

require __DIR__.'/../../config.php';

require_login();

$is_manager = $DB->get_record('role_assignments', array('contextid' => 1, 'roleid' => 1, 'userid' => $USER->id));

if (!$is_manager) {
    $is_manager = is_siteadmin();
}

if (!$is_manager) {
    redirect($CFG->wwwroot.'/blocks/apsolu_dashboard/extractions.php?manager=1', get_string('invalidaccess', 'error'), null, \core\output\notification::NOTIFY_ERROR);
    exit(1);
}

$file = $CFG->dataroot.'/apsolu/local_apsolu_courses/extraction_statistiques.csv';
if (is_file($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($file).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit(0);
}

redirect($CFG->wwwroot.'/blocks/apsolu_dashboard/extractions.php?manager=1', get_string('filenotfound', 'error'), null, \core\output\notification::NOTIFY_ERROR);
