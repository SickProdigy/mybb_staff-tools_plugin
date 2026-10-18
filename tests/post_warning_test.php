<?php
define('IN_MYBB', 1);
define('TIME_NOW', 2000000000);
define('TABLE_PREFIX', 'mybb_');

class StaffToolsTestPlugins
{
    public function add_hook($hook, $callback) {}
}

class StaffToolsTestDB
{
    public $queries = 0;
    public $warning = array(
        'wid' => 7,
        'uid' => 4,
        'tid' => 0,
        'pid' => 12,
        'title' => '<script>',
        'type_title' => '',
        'points' => 2,
        'dateline' => 1999990000,
        'issuedby' => 2,
        'expires' => 0,
        'expired' => 0,
        'issuedby_username' => 'Moderator',
        'current_warning_points' => 4
    );

    public function table_exists($table) { return $table === 'warnings'; }
    public function field_exists($field, $table) { return $field === 'pid' && $table === 'warnings'; }
    public function query($sql)
    {
        $this->queries++;
        if (strpos($sql, 'w.pid=12 AND w.daterevoked=0') === false) {
            throw new Exception('Warning lookup was not scoped to the post and non-revoked warnings.');
        }
        return $sql;
    }
    public function fetch_array($query) { return $this->warning; }
}

class StaffToolsTestTemplates
{
    public function get($name)
    {
        if ($name !== 'staff_tools_post_warning') {
            throw new Exception('Unexpected template.');
        }
        return '<div class=\"staff-tools-post-warning\"><span>{$warning_title}</span><span>{$warning_points_label}</span><span>{$warning_level}%</span><span>{$warning_date} {$warning_issued_by}</span><a href=\"{$warning_url}\">View warning</a></div>';
    }
}

function htmlspecialchars_uni($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function my_date($format, $timestamp) { return 'yesterday'; }
function build_profile_link($username, $uid) { return '<a href="member.php?action=profile&amp;uid='.$uid.'">'.$username.'</a>'; }

$plugins = new StaffToolsTestPlugins();
require dirname(__DIR__).'/Upload/inc/plugins/staff_tools.php';

function staff_tools_test_assert($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

$mybb = (object) array(
    'user' => array('uid' => 2),
    'usergroup' => array('canwarnusers' => 1),
    'settings' => array('staff_tools_post_warning_notice' => 1, 'maxwarningpoints' => 10),
    'asset_url' => '/assets'
);
$db = new StaffToolsTestDB();
$templates = new StaffToolsTestTemplates();

staff_tools_test_assert(staff_tools_can_view_post_warning(), 'A custom staff group with warning permission should be allowed.');
$mybb->usergroup['canwarnusers'] = 0;
staff_tools_test_assert(!staff_tools_can_view_post_warning(), 'A user without warning permission should be denied.');
$mybb->usergroup['canwarnusers'] = 1;

$post = array('pid' => 12, 'uid' => 4, 'fid' => 5, 'user_details' => 'Author', 'signature' => '<div class="signature">Signature</div>');
staff_tools_postbit_warning_notice($post);
staff_tools_test_assert($post['user_details'] === 'Author', 'Notice should not be added to the author details.');
staff_tools_test_assert(strpos($post['signature'], '&lt;script&gt;') !== false, 'Warning reason should be escaped.');
staff_tools_test_assert(strpos($post['signature'], 'Custom warning &mdash;') !== false, 'Custom warnings should be identified and show their entered reason.');
staff_tools_test_assert(staff_tools_warning_display_title(array('tid' => 3, 'title' => '', 'type_title' => 'Spam')) === 'Spam', 'Configured warnings should show their warning type title.');
staff_tools_test_assert(strpos($post['signature'], 'wid=7') !== false, 'Notice should link to the exact warning.');
staff_tools_test_assert(strpos($post['signature'], '2 points') !== false, 'Notice should show the warning point value.');
staff_tools_test_assert(strpos($post['signature'], '40%') !== false, 'Notice should show the current warning level.');
staff_tools_test_assert(strpos($post['signature'], 'Moderator') !== false && strpos($post['signature'], 'yesterday') !== false, 'Notice should show who issued the warning and when.');
staff_tools_test_assert(strpos($post['signature'], 'staff-tools-post-warning') < strpos($post['signature'], 'class="signature"'), 'Notice should appear above the signature.');
staff_tools_test_assert($db->queries === 1, 'Post warning should be queried once.');

$page = '<html><head></head><body></body></html>';
staff_tools_post_warning_styles($page);
staff_tools_test_assert(strpos($page, '/assets/jscripts/staff-tools/post-warning.css') !== false, 'Notice stylesheet should be injected.');

$mybb->usergroup['canwarnusers'] = 0;
$post = array('pid' => 13, 'uid' => 4, 'fid' => 5, 'user_details' => 'Author', 'signature' => '');
$before = $db->queries;
staff_tools_postbit_warning_notice($post);
staff_tools_test_assert($post['user_details'] === 'Author' && $post['signature'] === '' && $db->queries === $before, 'Unauthorized viewers should see no notice and cause no query.');

echo "Staff Tools post warning tests passed.\n";
