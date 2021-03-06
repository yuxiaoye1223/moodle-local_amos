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
 * Displays and manages submitted contributions
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once($CFG->dirroot . '/comment/lib.php');

$id     = optional_param('id', null, PARAM_INT);
$assign = optional_param('assign', null, PARAM_INT);
$resign = optional_param('resign', null, PARAM_INT);
$apply  = optional_param('apply', null, PARAM_INT);
$review = optional_param('review', null, PARAM_INT);
$accept = optional_param('accept', null, PARAM_INT);
$reject = optional_param('reject', null, PARAM_INT);
$closed = optional_param('closed', false, PARAM_BOOL);  // show resolved contributions, too

require_login(SITEID, false);
require_capability('local/amos:stash', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/contrib.php');
if ($closed) {
    $PAGE->url->param('closed', $closed);
}
if (!is_null($id)) {
    $PAGE->url->param('id', $id);
}
navigation_node::override_active_url(new moodle_url('/local/amos/contrib.php'));
$PAGE->set_title('AMOS ' . get_string('contributions', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('contributions', 'local_amos'));
//$PAGE->requires->yui_module('moodle-local_amos-contrib', 'M.local_amos.init_contrib');
//$PAGE->requires->strings_for_js(array('confirmaction'), 'local_amos');

if ($assign) {
    require_capability('local/amos:commit', get_system_context());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $assign), '*', MUST_EXIST);

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->assignee = $USER->id;
    $contribution->timemodified = time();
    $DB->update_record('amos_contributions', $contribution);
    redirect(new moodle_url($PAGE->url, array('id' => $assign)));
}

if ($resign) {
    require_capability('local/amos:commit', get_system_context());
    require_sesskey();

    $contribution = $DB->get_record('amos_contributions', array('id' => $resign, 'assignee' => $USER->id), '*', MUST_EXIST);

    $contribution->assignee = null;
    $contribution->timemodified = time();
    $DB->update_record('amos_contributions', $contribution);
    redirect(new moodle_url($PAGE->url, array('id' => $resign)));
}

if ($apply) {
    require_capability('local/amos:stage', get_system_context());
    require_sesskey();

    $contribution = $DB->get_record('amos_contributions', array('id' => $apply), '*', MUST_EXIST);

    if ($contribution->authorid !== $USER->id) {
        $author       = $DB->get_record('user', array('id' => $contribution->authorid));
        $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
        $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
        foreach ($maintenances as $maintained) {
            if ($maintained->lang === 'X') {
                $maintainerof = 'all';
                break;
            }
            $maintainerof[] = $maintained->lang;
        }

        if ($maintainerof !== 'all') {
            if (!in_array($contribution->lang, $maintainerof)) {
                print_error('contributionaccessdenied', 'local_amos');
            }
        }
    } else {
        $author = $USER;
    }

    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();

    $a = new stdClass();
    $a->id = $contribution->id;
    $a->author = fullname($author);
    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage', 'local_amos', $a);

    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($review) {
    require_capability('local/amos:commit', get_system_context());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $review), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));
    $amosbot      = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->assignee = $USER->id;
    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_REVIEW;
    $DB->update_record('amos_contributions', $contribution);

    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();

    // inform the contributor
    $a              = new stdClass();
    $a->assignee    = fullname($USER);
    $a->id          = $contribution->id;
    $a->subject     = $contribution->subject;
    $url            = new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id));
    $a->url         = $url->out();
    $emailsubject   = get_string('emailreviewsubject', 'local_amos');
    $emailbody      = get_string('emailreviewbody', 'local_amos', $a);
    email_to_user($author, $amosbot, $emailsubject, $emailbody);

    $a = new stdClass();
    $a->id = $contribution->id;
    $a->author = fullname($author);
    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage', 'local_amos', $a);

    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($accept) {
    require_capability('local/amos:commit', get_system_context());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $accept, 'assignee' => $USER->id), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));
    $amosbot      = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_ACCEPTED;
    $DB->update_record('amos_contributions', $contribution);

    // inform the contributor
    $a              = new stdClass();
    $a->assignee    = fullname($USER);
    $a->id          = $contribution->id;
    $a->subject     = $contribution->subject;
    $url            = new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id));
    $a->url         = $url->out();
    $emailsubject   = get_string('emailacceptsubject', 'local_amos');
    $emailbody      = get_string('emailacceptbody', 'local_amos', $a);
    email_to_user($author, $amosbot, $emailsubject, $emailbody);

    redirect(new moodle_url($PAGE->url, array('id' => $accept)));
}

if ($reject) {
    require_capability('local/amos:commit', get_system_context());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $reject, 'assignee' => $USER->id), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));
    $amosbot      = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_REJECTED;
    $DB->update_record('amos_contributions', $contribution);

    // inform the contributor
    $a              = new stdClass();
    $a->assignee    = fullname($USER);
    $a->id          = $contribution->id;
    $a->subject     = $contribution->subject;
    $url            = new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id));
    $a->url         = $url->out();
    $emailsubject   = get_string('emailrejectsubject', 'local_amos');
    $emailbody      = get_string('emailrejectbody', 'local_amos', $a);
    email_to_user($author, $amosbot, $emailsubject, $emailbody);

    redirect(new moodle_url($PAGE->url, array('id' => $reject)));
}

$output = $PAGE->get_renderer('local_amos');
comment::init();

// Output starts here
echo $output->header();

// Particular contribution record
if ($id) {

    if (has_capability('local/amos:commit', get_system_context())) {
        $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
        $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
        foreach ($maintenances as $maintained) {
            if ($maintained->lang === 'X') {
                $maintainerof = 'all';
                break;
            }
            $maintainerof[] = $maintained->lang;
        }
    } else {
        $maintainerof = false;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $id), '*', MUST_EXIST);

    if ($contribution->authorid !== $USER->id) {
        require_capability('local/amos:commit', get_system_context());
        if ($maintainerof !== 'all') {
            if (!in_array($contribution->lang, $maintainerof)) {
                print_error('contributionaccessdenied', 'local_amos');
            }
        }
        $author = $DB->get_record('user', array('id' => $contribution->authorid));
    } else {
        $author = $USER;
    }

    // get the contributed components and rebase them to see what would happen
    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = new mlang_stage();
    $stash->apply($stage);
    list($origstrings, $origlanguages, $origcomponents) = mlang_stage::analyze($stage);
    $stage->rebase();
    list($rebasedstrings, $rebasedlanguages, $rebasedcomponents) = mlang_stage::analyze($stage);

    if ($stage->has_component()) {

    } else {
        // nothing left after rebase
    }

    $contribinfo                = new local_amos_contribution($contribution, $author);
    $contribinfo->language      = implode(', ', array_filter(array_map('trim', explode('/', $origlanguages))));
    $contribinfo->components    = implode(', ', array_filter(array_map('trim', explode('/', $origcomponents))));
    $contribinfo->strings       = $origstrings;
    $contribinfo->stringsreb    = $rebasedstrings;

    echo $output->render($contribinfo);

    echo html_writer::start_tag('div', array('class' => 'contribactions'));
    if ($maintainerof) {
        if ($contribution->status == local_amos_contribution::STATE_NEW) {
            echo $output->single_button(new moodle_url($PAGE->url, array('review' => $id)), get_string('contribstartreview', 'local_amos'),
                    'post', array('class' => 'singlebutton review'));
        }
        if ($contribution->assignee == $USER->id) {
            echo $output->single_button(new moodle_url($PAGE->url, array('resign' => $id)), get_string('contribresign', 'local_amos'),
                    'post', array('class' => 'singlebutton resign'));
        } else {
            echo $output->single_button(new moodle_url($PAGE->url, array('assign' => $id)), get_string('contribassigntome', 'local_amos'),
                    'post', array('class' => 'singlebutton assign'));
        }
    }
    echo $output->single_button(new moodle_url($PAGE->url, array('apply' => $id)), get_string('contribapply', 'local_amos'),
            'post', array('class' => 'singlebutton apply'));
    if ($contribution->assignee == $USER->id and $contribution->status > local_amos_contribution::STATE_NEW) {
        if ($contribution->status != local_amos_contribution::STATE_ACCEPTED) {
            echo $output->single_button(new moodle_url($PAGE->url, array('accept' => $id)), get_string('contribaccept', 'local_amos'),
                    'post', array('class' => 'singlebutton accept'));
        }
        if ($contribution->status != local_amos_contribution::STATE_REJECTED) {
            echo $output->single_button(new moodle_url($PAGE->url, array('reject' => $id)), get_string('contribreject', 'local_amos'),
                    'post', array('class' => 'singlebutton reject'));
        }
    }
    echo $output->help_icon('contribactions', 'local_amos');
    echo html_writer::end_tag('div');

    if (!empty($CFG->usecomments)) {
        $options = new stdClass();
        $options->context = get_system_context();
        $options->area    = 'amos_contribution';
        $options->itemid  = $contribution->id;
        $options->showcount = true;
        $options->component = 'local_amos';
        $commentmanager = new comment($options);
        echo $output->container($commentmanager->output(), 'commentswrapper');
    }

    echo $output->footer();
    exit;
}

// Incoming contributions
if (has_capability('local/amos:commit', get_system_context())) {
    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    if (empty($maintainerof)) {
        $contributions = array();

    } else {
        $params = array();

        if (is_array($maintainerof)) {
            list($langsql, $langparams) = $DB->get_in_or_equal($maintainerof);
            $params = array_merge($params, $langparams);
        } else {
            $langsql = "";
        }

        $sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified,
                       s.components, s.strings,
                       ".user_picture::fields('a', null, 'authorid', 'author').",
                       ".user_picture::fields('m', null, 'assigneeid', 'assignee')."
                  FROM {amos_contributions} c
                  JOIN {amos_stashes} s ON (c.stashid = s.id)
                  JOIN {user} a ON c.authorid = a.id
             LEFT JOIN {user} m ON c.assignee = m.id";

        if ($closed) {
            $sql .= " WHERE c.status >= 0"; // true
        }  else {
            $sql .= " WHERE c.status < 20"; // do not show resolved contributions
        }

        if ($langsql) {
            $sql .= " AND c.lang $langsql";
        }

        // In review first, then New and then Accepted and Rejected together, then order by date
        $sql .= " ORDER BY CASE WHEN c.status = 10 THEN 1
                                WHEN c.status = 0  THEN 2
                                ELSE 3
                           END,
                           COALESCE (c.timemodified, c.timecreated) DESC";

        $contributions = $DB->get_records_sql($sql, $params);
    }

    if (empty($contributions)) {
        echo $output->heading(get_string('contribincomingnone', 'local_amos'));

    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable contributionlist incoming';
        $table->head = array(
            get_string('contribid', 'local_amos'),
            get_string('contribstatus', 'local_amos'),
            get_string('contribauthor', 'local_amos'),
            get_string('contribsubject', 'local_amos'),
            get_string('contribtimemodified', 'local_amos'),
            get_string('contribassignee', 'local_amos'),
            get_string('language', 'local_amos'),
            get_string('strings', 'local_amos')
        );
        $table->colclasses = array('id', 'status', 'author', 'subject', 'timemodified', 'assignee', 'language', 'strings');

        foreach ($contributions as $contribution) {
            $url = new moodle_url($PAGE->url, array('id' => $contribution->id));
            $cells   = array();
            $cells[] = new html_table_cell(html_writer::link($url, '#'.$contribution->id));
            $status  = get_string('contribstatus'.$contribution->status, 'local_amos');
            $cells[] = new html_table_cell(html_writer::link($url, $status));
            $author  = new user_picture(user_picture::unalias($contribution, null, 'authorid', 'author'));
            $author->size = 16;
            $cells[] = new html_table_cell($output->render($author) . s(fullname($author->user)));
            $cells[] = new html_table_cell(html_writer::link($url, s($contribution->subject)));
            $time    = is_null($contribution->timemodified) ? $contribution->timecreated : $contribution->timemodified;
            $cells[] = new html_table_cell(userdate($time, get_string('strftimedaydatetime', 'langconfig')));
            if (is_null($contribution->assigneeid)) {
                $assignee = get_string('contribassigneenone', 'local_amos');
            } else {
                $assignee = new user_picture(user_picture::unalias($contribution, null, 'assigneeid', 'assignee'));
                $assignee->size = 16;
                $assignee = $output->render($assignee) . s(fullname($assignee->user));
            }
            $cells[] = new html_table_cell($assignee);
            $cells[] = new html_table_cell(s($contribution->lang));
            $cells[] = new html_table_cell(s($contribution->strings));
            $row = new html_table_row($cells);
            $row->attributes['class'] = 'status'.$contribution->status;
            $table->data[] = $row;
        }

        echo $output->heading(get_string('contribincomingsome', 'local_amos', count($contributions)));
        echo html_writer::table($table);
    }
}

// Submitted contributions
$sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified,
               s.components, s.strings,
               ".user_picture::fields('m', null, 'assigneeid', 'assignee')."
          FROM {amos_contributions} c
          JOIN {amos_stashes} s ON (c.stashid = s.id)
     LEFT JOIN {user} m ON c.assignee = m.id
         WHERE c.authorid = ?";

if (!$closed) {
    $sql .= " AND c.status < 20"; // do not show resolved contributions
}

// In review first, then New and then Accepted and Rejected together, then order by date
$sql .= " ORDER BY CASE WHEN c.status = 10 THEN 1
                        WHEN c.status = 0  THEN 2
                        ELSE 3
                   END,
                   COALESCE (c.timemodified, c.timecreated) DESC";

$contributions = $DB->get_records_sql($sql, array($USER->id));

if (empty($contributions)) {
    echo $output->heading(get_string('contribsubmittednone', 'local_amos'));

} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable contributionlist submitted';
    $table->head = array(
        get_string('contribid', 'local_amos'),
        get_string('contribstatus', 'local_amos'),
        get_string('contribsubject', 'local_amos'),
        get_string('contribtimemodified', 'local_amos'),
        get_string('contribassignee', 'local_amos'),
        get_string('language', 'local_amos'),
        get_string('strings', 'local_amos')
    );
    $table->colclasses = array('id', 'status', 'subject', 'timemodified', 'assignee', 'language', 'strings');

    foreach ($contributions as $contribution) {
        $url = new moodle_url($PAGE->url, array('id' => $contribution->id));
        $cells   = array();
        $cells[] = new html_table_cell(html_writer::link($url, '#'.$contribution->id));
        $status  = get_string('contribstatus'.$contribution->status, 'local_amos');
        $cells[] = new html_table_cell(html_writer::link($url, $status));
        $cells[] = new html_table_cell(html_writer::link($url, s($contribution->subject)));
        $time    = is_null($contribution->timemodified) ? $contribution->timecreated : $contribution->timemodified;
        $cells[] = new html_table_cell(userdate($time, get_string('strftimedaydatetime', 'langconfig')));
        if (is_null($contribution->assigneeid)) {
            $assignee = get_string('contribassigneenone', 'local_amos');
        } else {
            $assignee = new user_picture(user_picture::unalias($contribution, null, 'assigneeid', 'assignee'));
            $assignee->size = 16;
            $assignee = $output->render($assignee) . s(fullname($assignee->user));
        }
        $cells[] = new html_table_cell($assignee);
        $cells[] = new html_table_cell(s($contribution->lang));
        $cells[] = new html_table_cell(s($contribution->strings));
        $row = new html_table_row($cells);
        $row->attributes['class'] = 'status'.$contribution->status;
        $table->data[] = $row;
    }

    echo $output->heading(get_string('contribsubmittedsome', 'local_amos', count($contributions)));
    echo html_writer::table($table);

}

if ($closed) {
    echo $output->single_button(new moodle_url($PAGE->url, array('closed' => false)),
        get_string('contribclosedno', 'local_amos'), 'get', array('class' => 'singlebutton showclosed'));
} else {
    echo $output->single_button(new moodle_url($PAGE->url, array('closed' => true)),
        get_string('contribclosedyes', 'local_amos'), 'get', array('class' => 'singlebutton showclosed'));
}

echo $output->footer();
