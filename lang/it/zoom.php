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
 * English strings for zoom.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['viewownreportlink'] = 'Report videolezioni';
$string['ownreportmeetingfooter'] = 'La lezione è durata <strong>{$a->meetingDuration}</strong>, hai frequentato per <strong>{$a->userDuration}</strong>.'; // (<span class="{$a->percentClass}">{$a->percentDuration}</span> del tempo)';
$string['expectedmeetingduration'] = 'Durata prevista: {$a}';
$string['ownreportsummary'] = '<ul><li>La durata totale prevista delle lezioni è di <strong>{$a->total}</strong>.</li><li>Tempo di assenza massimo permesso: <strong>{$a->max_abscence}</strong></li><li>Al {$a->today} sono state erogate <strong>{$a->provided}</strong> ore.</li><li>Al {$a->reportlastupdate} ne risultano frequentate <strong>{$a->user}</strong>, per un totale di <strong>{$a->user_absence}</strong> ore:minuti di assenza.</li></ul>';
$string['ownreportsummary_sefa'] = '<ul><li>La durata totale prevista delle lezioni è di <strong>{$a->total}</strong>.</li><li>Al {$a->today} sono state erogate <strong>{$a->provided}</strong> ore.</li><li>Al {$a->reportlastupdate} ne risultano frequentate <strong>{$a->user}</strong>.</li></ul>';
$string['ownreportlastupdate'] = 'Dati aggiornati al {$a}';
$string['ownreportdatawarning'] = '<strong>I dati relativi alla frequenza e alle assenze delle video lezioni vengono aggiornati nelle 24 ore successive.</strong><br/>N.B.: La giornata inaugurale non è conteggiata ai fini del calcolo del monte ore.';
$string['ownreportdatawarning_sefa'] = '<strong>I dati relativi alla frequenza delle video lezioni vengono aggiornati nelle 24 ore successive.</strong>';
$string['lastjoin'] = 'Ultimo partecipante';
$string['lastjoin_desc'] = 'Con quanto ritardo un utente può partecipare al meeting (minuti dopo la fine).';