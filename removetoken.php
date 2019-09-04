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
 * Version information for the block_quiz_results plugin.
 *
 * @package    local_o365
 * @copyright  2019 Josh Willcock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once("$CFG->libdir/tablelib.php");
require_once("$CFG->libdir/formslib.php");
require_login();
$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/local/o365/removetoken.php');
$PAGE->set_title('Clear Office 365 Tokens');
$PAGE->set_heading('Clear Office 365 Tokens');
$PAGE->set_pagelayout('admin');
echo $OUTPUT->header();
require_capability('local/o365:manageconnectionunlink', context_system::instance());
class filter_form extends moodleform {
    //Add elements to form
    public function definition() {
        global $CFG;
 
        $mform = $this->_form; // Don't forget the underscore! 
 
        $mform->addElement('text', 'username', get_string('username')); // Add elements to your form
        $mform->setType('username', PARAM_NOTAGS);                   //Set type of element
        //$mform->setDefault('username', 'Please enter email');        //Default value
        $mform->addElement('text', 'oidcusername', 'OIDC Username'); // Add elements to your form
        $mform->setType('oidcusername', PARAM_NOTAGS);                   //Set type of element
        //$mform->setDefault('oidcusername', 'Please enter email');        //Default value
        $this->add_action_buttons(false, 'Search');

    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}

class removetoken_table extends table_sql {
    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     */
    function __construct($uniqueid) {
        parent::__construct($uniqueid);
        // Define the list of columns to show.
        $columns = array('username', 'oidcusername', 'expiry', 'oidcuniqid', 'id');
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = array('Username', 'OIDC Username', 'Expiry', 'o365 Object ID', 'Delete');
        $this->define_headers($headers);
    }

    /**
     * This function is called for each data row to allow processing of the
     * username value.
     *
     * @param object $values Contains object with all the values of record.
     * @return $string Return username with link to profile or username only
     *     when downloading.
     */
    function col_expiry($values) {
        return userdate($values->expiry);
}
    function col_id($values) {
        global $CFG, $USER;
        return \html_writer::link(new moodle_url($CFG->wwwroot.'/local/o365/removetoken.php', ['sesskey' => sesskey(), 'action' => 'delete', 'tokenid' => $values->id]), 'Delete');
    }
}


$action = optional_param('action', '', PARAM_TEXT);
if ($action === 'delete') {
    $sesskey = required_param('sesskey', PARAM_TEXT);
    $tokenid = required_param('tokenid', PARAM_INT);
    if (confirm_sesskey($sesskey)) {
        $oldrecord = $DB->get_record('auth_oidc_token', ['id' => $tokenid]);
        $response = $DB->delete_records('auth_oidc_token', ['id' => $tokenid]);
        echo \html_writer::div('Deleted a token for '.$oldrecord->username, 'alert alert-success');
    } else {
        print_error('invalidsesskey');
    }
}

$mform = new filter_form();
$filters = array();
//Form processing and displaying is done here
if ($mform->is_cancelled()) {
} else if ($fromform = $mform->get_data()) {
    $mform->set_data($fromform);
    $mform->display();
    if ($fromform->username != '') {
        $filters['username'] = $fromform->username;
    }
    if ($fromform->oidcusername != '') {
        $filters['oidcusername'] = $fromform->oidcusername;
    }
} else {
    $mform->set_data($toform);
    $mform->display();
}
$table = new removetoken_table('uniqueid');
if (isset($filters['username'])) {
    $where = 'lower(username)=lower("'.$filters['username'].'")';
}
if (isset($filters['oidcusername'])) {
    if ($where == '') {
        $where = 'lower(oidcusername)=lower("'.$filters['oidcusername'].'")';
    } else {
        $where .= 'lower(oidcusername)=lower("'.$filters['oidcusername'].'")';
    }
}
if ($where == '') {
    $where = '1';
}
$table->set_sql('*', "{auth_oidc_token}", $where);
$table->define_baseurl("$CFG->wwwroot/local/oidc/removetoken.php");
$card = $table->out(10, true);
echo \html_writer::div($card, 'container');
echo $OUTPUT->footer();