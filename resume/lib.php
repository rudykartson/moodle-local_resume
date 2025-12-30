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
 * @package     local_resume
 * @copyright   2025 Rudraksh Batra <batra.rudraksh@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

// Inject course-level Resume/Start button at the top of the course page.
function local_resume_before_footer() {
    global $PAGE, $COURSE, $USER;

    if (!isloggedin() || isguestuser() || !isset($COURSE->id)) {
        return;
    }

    $supportedpagetypes = [
        'course-view-topics',
        'course-view-weeks',
        'course-view',
        'course-view-singleactivity'
    ];

    if (!in_array($PAGE->pagetype, $supportedpagetypes)) {
        return;
    }

    // Check if course-level resume button is enabled.
    $enabled = get_config('local_resume', 'enablecourse');
    if ($enabled) {
        $html = local_resume_render_resume_button($COURSE->id);
        echo html_writer::div($html, 'local-resume-course-top', ['style' => 'margin: 20px 0']);
    }

    // Section-level resume buttons handled by JS (see below).
}

// Inject JS for section resume buttons
function local_resume_extend_navigation_course($navigation, $course, $context) {
    global $PAGE;
    $enabled = get_config('local_resume', 'enablesection');
    if ($enabled && $PAGE->pagelayout === 'course') {
    // if ($PAGE->pagelayout === 'course') {
        // $PAGE->requires->js('/local/resume/sectionbuttons.js');
        $PAGE->requires->js_call_amd('local_resume/sectionbuttons', 'init');
    }
}

// Renders a Resume/Start button based on course/section progress
function local_resume_render_resume_button($courseid, $sectionid = null) {
    global $USER;

    require_once(__DIR__ . '/classes/local_resume.php');

    $customresumetext     = get_config('local_resume', 'customresumetext') ?: get_string('resume', 'local_resume');
    $customstarttext      = get_config('local_resume', 'customstarttext') ?: get_string('start', 'local_resume');
    $customalldonemsg     = get_config('local_resume', 'customalldonemsg') ?: get_string('alldone', 'local_resume');
    $customstartagaintext = get_config('local_resume', 'customstartagaintext') ?: get_string('startagain', 'local_resume');
    $hideifcomplete       = get_config('local_resume', 'hideifcomplete');

    // 🧠 Check if there are any visible activities in this course/section
    if (!\local_resume\local_resume::has_visible_activities($courseid, $sectionid)) {
        return ''; // ❌ Don't show button if no activities
    }

    $iscomplete = \local_resume\local_resume::all_complete($courseid, $USER->id, $sectionid);

    $label = '';
    $disabled = '';
    $url = null;

    // Determine URL and label
    if ($sectionid) {
        $lastaccess = \local_resume\local_resume::get_last_accessed_activity_url_in_section($courseid, $USER->id, $sectionid);
        if ($iscomplete) {
            $label = $customstartagaintext;
            $url = \local_resume\local_resume::get_first_activity_url($courseid, $sectionid);
        } elseif ($lastaccess) {
            $label = $customresumetext;
            $url = $lastaccess;
        } else {
            $label = $customstarttext;
            $url = \local_resume\local_resume::get_first_activity_url($courseid, $sectionid);
        }
    } else {
        $lastaccess = \local_resume\local_resume::get_last_accessed_activity_url($courseid, $USER->id);
        if ($iscomplete) {
            $label = $customstartagaintext;
            $url = \local_resume\local_resume::get_first_activity_url($courseid, null);
        } elseif ($lastaccess) {
            $label = $customresumetext;
            $url = $lastaccess;
        } else {
            $label = $customstarttext;
            $url = \local_resume\local_resume::get_first_activity_url($courseid, null);
        }
    }

    if (!$url) return ''; // Extra safety

    // ✅ Prepare form
    $inputs = html_writer::empty_tag('input', [
        'type' => 'hidden', 'name' => 'id', 'value' => $url->get_param('id')
    ]);
    $inputs .= html_writer::empty_tag('input', [
        'type' => 'hidden', 'name' => 'module', 'value' => $url->get_path()
    ]);
    $inputs .= html_writer::empty_tag('input', [
        'type' => 'hidden', 'name' => 'courseid', 'value' => $courseid
    ]);
    if ($sectionid) {
        $inputs .= html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => 'sectionid', 'value' => $sectionid
        ]);
    }
    $inputs .= html_writer::empty_tag('input', [
        'type' => 'submit', 'class' => 'btn btn-primary resume-btn', 'value' => $label
    ]);

    return html_writer::tag('form', $inputs, [
        'action' => new moodle_url('/local/resume/resume.php'),
        'method' => 'get',
        'style' => 'margin:1em 0;display:inline-block;'
    ]);
}
