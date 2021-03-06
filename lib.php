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
 * AMOS interface library
 *
 * @package   amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Puts AMOS into the global navigation tree.
 *
 * @param global_navigation $navigation
 */
function amos_extends_navigation(global_navigation $navigation) {
    $amos = $navigation->add('AMOS', new moodle_url('/local/amos/'));
    if (has_capability('local/amos:stage', get_system_context())) {
        $amos->add(get_string('translatortool', 'local_amos'), new moodle_url('/local/amos/view.php'));
        $amos->add(get_string('stage', 'local_amos'), new moodle_url('/local/amos/stage.php'));
    }
    if (has_capability('local/amos:stash', get_system_context())) {
        $amos->add(get_string('stashes', 'local_amos'), new moodle_url('/local/amos/stash.php'));
        $amos->add(get_string('contributions', 'local_amos'), new moodle_url('/local/amos/contrib.php'));
    }
    $amos->add(get_string('log', 'local_amos'), new moodle_url('/local/amos/log.php'));
    if (has_capability('local/amos:manage', get_system_context())) {
        $admin = $amos->add(get_string('administration'));
        $admin->add(get_string('maintainers', 'local_amos'), new moodle_url('/local/amos/admin/translators.php'));
        $admin->add(get_string('newlanguage', 'local_amos'), new moodle_url('/local/amos/admin/newlanguage.php'));
    }
}
