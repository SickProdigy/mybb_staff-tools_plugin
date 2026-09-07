<?php
/**
 * MyBB Staff Tools
 * Copyright (c) 2026 SickProdigy
 * SPDX-License-Identifier: GPL-3.0-only
 */
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('modcp_nav', 'staff_tools_modcp_nav');
$plugins->add_hook('modcp_start', 'staff_tools_modcp');
$plugins->add_hook('modcp_end', 'staff_tools_modcp_home');

function staff_tools_info()
{
    return array(
        'name' => 'Staff Tools',
        'description' => 'ModCP review feeds, duplicate-account checks, staff tasks, and action digests.',
        'website' => 'https://gitea.rcs1.top/sickprodigy/mybb_staff-tools_plugin',
        'author' => 'SickProdigy',
        'version' => '1.0.0',
        'compatibility' => '18*',
        'codename' => 'staff_tools',
        'license' => 'GPL-3.0-only'
    );
}

function staff_tools_is_installed()
{
    global $db;
    $query = $db->simple_select('settinggroups', 'gid', "name='staff_tools'");
    return (bool) $db->fetch_field($query, 'gid');
}

function staff_tools_settings()
{
    return array(
        array('name' => 'staff_tools_recentposts', 'title' => 'Modules: Recent Posts', 'description' => 'Show the Recent Posts review page in ModCP.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 10),
        array('name' => 'staff_tools_newmembers', 'title' => 'Modules: New Member Watchlist', 'description' => 'Show the New Member Watchlist in ModCP.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 20),
        array('name' => 'staff_tools_duplicates', 'title' => 'Modules: Duplicate Account Finder', 'description' => 'Show the Duplicate Account Finder in ModCP. Users who can use IP Search can see the matching IP values.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 30),
        array('name' => 'staff_tools_tasks', 'title' => 'Modules: Staff Task Queue', 'description' => 'Show the Staff Task Queue in ModCP.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 40),
        array('name' => 'staff_tools_digest', 'title' => 'Modules: Staff Action Digest', 'description' => 'Show the Staff Action Digest in ModCP.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 50),
        array('name' => 'staff_tools_snapshot', 'title' => 'Modules: ModCP Snapshot Panel', 'description' => 'Show the Staff Tools Snapshot panel on the ModCP home page.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 60),
        array('name' => 'staff_tools_perpage', 'title' => 'Review Feeds: Items Per Page', 'description' => 'Number of posts or results shown per page on review feeds. Allowed range: 10-100.', 'optionscode' => 'numeric', 'value' => '25', 'disporder' => 110),
        array('name' => 'staff_tools_excluded_users', 'title' => 'Review Feeds: Excluded User IDs', 'description' => 'Comma-separated user IDs to hide from review feeds, such as bot or service accounts.', 'optionscode' => 'text', 'value' => '', 'disporder' => 120),
        array('name' => 'staff_tools_excluded_groups', 'title' => 'Review Feeds: Excluded Group IDs', 'description' => 'Comma-separated group IDs to hide from review feeds. Checks primary and additional groups.', 'optionscode' => 'text', 'value' => '3,4,6', 'disporder' => 130),
        array('name' => 'staff_tools_newmember_days', 'title' => 'New Member Watchlist: Registration Age Window', 'description' => 'Include posts from users registered within this many days.', 'optionscode' => 'numeric', 'value' => '14', 'disporder' => 210),
        array('name' => 'staff_tools_newmember_posts', 'title' => 'New Member Watchlist: Low Post-Count Threshold', 'description' => 'Include posts from users with this many posts or fewer.', 'optionscode' => 'numeric', 'value' => '10', 'disporder' => 220),
        array('name' => 'staff_tools_task_groups', 'title' => 'Staff Task Queue: Assignable Group IDs', 'description' => 'Comma-separated group IDs whose members appear in the Staff Task assignment dropdown.', 'optionscode' => 'text', 'value' => '3,4,6', 'disporder' => 310),
        array('name' => 'staff_tools_task_pms', 'title' => 'Staff Task Queue: Assignment PMs', 'description' => 'Send a private message when a task is assigned or reassigned to another staff member.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 320),
        array('name' => 'staff_tools_digest_days', 'title' => 'Staff Action Digest: Lookback Days', 'description' => 'Number of days included in the Staff Action Digest.', 'optionscode' => 'numeric', 'value' => '7', 'disporder' => 410)
    );
}

function staff_tools_install()
{
    staff_tools_uninstall();
    staff_tools_ensure_settings();
    staff_tools_ensure_tasks_table();
    staff_tools_ensure_templates();
    rebuild_settings();
}

function staff_tools_uninstall()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='staff_tools'");
    if ($gid = (int) $db->fetch_field($query, 'gid')) {
        $db->delete_query('settings', "gid={$gid}");
        $db->delete_query('settinggroups', "gid={$gid}");
    }

    $db->delete_query('templates', "title IN ('staff_tools_recentposts', 'staff_tools_page', 'staff_tools_recentposts_panel', 'staff_tools_home_panel')");
    if ($db->table_exists('staff_tools_tasks')) {
        $db->drop_table('staff_tools_tasks');
    }
    rebuild_settings();
}

function staff_tools_activate()
{
    staff_tools_ensure_settings();
    staff_tools_ensure_tasks_table();
    staff_tools_ensure_templates();
    rebuild_settings();
}

function staff_tools_deactivate() {}

function staff_tools_ensure_settings()
{
    global $db;

    $query = $db->simple_select('settinggroups', 'gid', "name='staff_tools'", array('limit' => 1));
    $gid = (int) $db->fetch_field($query, 'gid');
    if (!$gid) {
        $gid = $db->insert_query('settinggroups', array(
            'name' => 'staff_tools',
            'title' => 'Staff Tools',
            'description' => 'Settings for Staff Tools.',
            'disporder' => 50,
            'isdefault' => 0
        ));
    }

    foreach (staff_tools_settings() as $setting) {
        $escapedName = $db->escape_string($setting['name']);
        $query = $db->simple_select('settings', 'sid', "name='{$escapedName}'", array('limit' => 1));
        $sid = (int) $db->fetch_field($query, 'sid');
        if ($sid) {
            $db->update_query('settings', array(
                'gid' => $gid,
                'title' => $db->escape_string($setting['title']),
                'description' => $db->escape_string($setting['description']),
                'optionscode' => $db->escape_string($setting['optionscode']),
                'disporder' => (int) $setting['disporder']
            ), "sid={$sid}");
            continue;
        }
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }
}

function staff_tools_ensure_tasks_table($repairColumns = true)
{
    global $db;

    if ($db->table_exists('staff_tools_tasks')) {
        if ($repairColumns) {
            staff_tools_ensure_task_columns();
        }
        return;
    }

    $collation = $db->build_create_table_collation();
    $db->write_query("CREATE TABLE ".TABLE_PREFIX."staff_tools_tasks (
        tid int unsigned NOT NULL auto_increment,
        title varchar(255) NOT NULL default '',
        description text NOT NULL,
        status varchar(20) NOT NULL default 'open',
        priority varchar(20) NOT NULL default 'normal',
        assigned_uid int unsigned NOT NULL default 0,
        created_uid int unsigned NOT NULL default 0,
        completed_uid int unsigned NOT NULL default 0,
        dateline int unsigned NOT NULL default 0,
        duedate int unsigned NOT NULL default 0,
        completed_date int unsigned NOT NULL default 0,
        completion_note text NULL,
        PRIMARY KEY (tid),
        KEY status (status),
        KEY assigned_uid (assigned_uid),
        KEY dateline (dateline)
    ) ENGINE=MyISAM{$collation};");

    if ($repairColumns) {
        staff_tools_ensure_task_columns();
    }
}

function staff_tools_ensure_task_columns()
{
    global $db;

    if (!method_exists($db, 'field_exists')) {
        return;
    }

    if (!$db->field_exists('completion_note', 'staff_tools_tasks')) {
        $db->write_query("ALTER TABLE ".TABLE_PREFIX."staff_tools_tasks ADD completion_note text NULL AFTER completed_date");
    } else {
        $db->write_query("ALTER TABLE ".TABLE_PREFIX."staff_tools_tasks MODIFY completion_note text NULL");
    }
}

function staff_tools_templates()
{
    $page = <<<'HTML'
<html>
<head>
<title>{$mybb->settings['bbname']} - {$staff_tools_page_title}</title>
{$headerinclude}
<style type="text/css">
.staff-tools-table td,
.staff-tools-table th {
    border-left: 0 !important;
    border-right: 0 !important;
}
.staff-tools-actions form {
    display: inline;
}
.staff-tools-actions .button {
    margin-right: 4px;
}
.staff-tools-done-form {
    display: block !important;
    margin: 8px 0;
}
</style>
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
{$staff_tools_content}
</td>
</tr>
</table>
{$footer}
</body>
</html>
HTML;

    $recentpostsPanel = <<<'HTML'
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr><td class="thead"><strong>Recent Posts Review</strong></td></tr>
{$staff_tools_rows}
<tr><td class="tfoot" align="right"><a href="modcp.php?action=recentposts">View all recent posts</a></td></tr>
</table>
<br />
HTML;

    $homePanel = <<<'HTML'
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr><td class="thead"><strong>Staff Tools Snapshot</strong></td></tr>
{$staff_tools_home_rows}
</table>
<br />
HTML;

    return array(
        'staff_tools_page' => $page,
        'staff_tools_recentposts_panel' => $recentpostsPanel,
        'staff_tools_home_panel' => $homePanel
    );
}

function staff_tools_ensure_templates()
{
    global $db;

    foreach (staff_tools_templates() as $title => $template) {
        $escapedTitle = $db->escape_string($title);
        $templateData = array(
            'template' => $db->escape_string($template),
            'sid' => -2,
            'version' => '',
            'dateline' => TIME_NOW
        );
        $query = $db->simple_select('templates', 'tid', "title='{$escapedTitle}'", array('limit' => 1));
        if ($tid = (int) $db->fetch_field($query, 'tid')) {
            $db->update_query('templates', $templateData, "tid={$tid}");
        } else {
            $templateData['title'] = $title;
            $db->insert_query('templates', $templateData);
        }
    }
}

function staff_tools_csv_ids($value)
{
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $value)))));
}

function staff_tools_setting_enabled($name)
{
    global $mybb;
    return !empty($mybb->settings[$name]);
}

function staff_tools_per_page()
{
    global $mybb;
    return max(10, min(100, (int) $mybb->settings['staff_tools_perpage']));
}

function staff_tools_excluded_user_where($userAlias = 'u', $postAlias = '')
{
    global $mybb;

    $excludedUsers = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_users']);
    $excludedGroups = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_groups']);
    $where = array();
    $uidColumn = $postAlias ? "{$postAlias}.uid" : "{$userAlias}.uid";

    if ($excludedUsers) {
        $where[] = "{$uidColumn} NOT IN (".implode(',', $excludedUsers).")";
    }
    if ($excludedGroups) {
        $where[] = "({$userAlias}.uid IS NULL OR {$userAlias}.usergroup NOT IN (".implode(',', $excludedGroups)."))";
        foreach ($excludedGroups as $gid) {
            $where[] = "({$userAlias}.additionalgroups IS NULL OR {$userAlias}.additionalgroups='' OR NOT FIND_IN_SET({$gid}, {$userAlias}.additionalgroups))";
        }
    }

    return $where;
}

function staff_tools_modcp_nav()
{
    global $nav_modlogs;

    $links = array();
    if (staff_tools_setting_enabled('staff_tools_recentposts')) {
        $links[] = array('recentposts', 'Recent Posts');
    }
    if (staff_tools_setting_enabled('staff_tools_newmembers')) {
        $links[] = array('newmembers', 'New Member Watchlist');
    }
    if (staff_tools_setting_enabled('staff_tools_duplicates')) {
        $links[] = array('duplicates', 'Duplicate Finder');
    }
    if (staff_tools_setting_enabled('staff_tools_tasks')) {
        $links[] = array('stafftasks', 'Staff Tasks');
    }
    if (staff_tools_setting_enabled('staff_tools_digest')) {
        $links[] = array('staffdigest', 'Action Digest');
    }

    if (!$links) {
        return;
    }

    $nav_modlogs .= '<tr><td class="tcat smalltext"><strong>Staff Tools</strong></td></tr>';
    foreach ($links as $link) {
        $class = htmlspecialchars_uni('modcp_nav_staff_tools_'.$link[0]);
        $label = htmlspecialchars_uni($link[1]);
        $nav_modlogs .= '<tr><td class="trow1 smalltext"><a href="modcp.php?action='.$link[0].'" class="modcp_nav_item '.$class.'">'.$label.'</a></td></tr>';
    }
}

function staff_tools_excerpt($message, $length = 260)
{
    $message = strip_tags((string) $message);
    $message = preg_replace('#\[(?:/)?(?:b|i|u|s|url|img|quote|code|php|list|li|\*|color|size|font|align|email)[^\]]*\]#i', '', $message);
    $message = trim(preg_replace('/\s+/', ' ', $message));

    if ($message === '') {
        return '<em>No preview text.</em>';
    }

    if (my_strlen($message) > $length) {
        $message = my_substr($message, 0, $length).'...';
    }

    return htmlspecialchars_uni($message);
}

function staff_tools_post_rows($query)
{
    global $db;

    $rows = '';
    while ($post = $db->fetch_array($query)) {
        $subject = htmlspecialchars_uni($post['threadsubject'] ?: $post['subject']);
        $author = htmlspecialchars_uni($post['username']);
        $date = my_date('relative', $post['dateline']);
        $excerpt = staff_tools_excerpt($post['message']);
        $postUrl = get_post_link($post['pid'], $post['tid']).'#pid'.$post['pid'];
        $editUrl = 'editpost.php?pid='.(int) $post['pid'];
        $authorLink = $post['uid'] ? build_profile_link($author, $post['uid']) : $author;
        $warnLink = $post['uid'] ? ' &middot; <a href="warnings.php?action=warn&amp;uid='.(int) $post['uid'].'&amp;pid='.(int) $post['pid'].'">Warn user</a>' : '';
        $meta = $authorLink.' &middot; '.$date.' &middot; <a href="'.$postUrl.'">View post</a> &middot; <a href="'.$editUrl.'">Edit post</a>'.$warnLink;

        if (isset($post['regdate'])) {
            $meta .= ' &middot; Registered '.my_date('relative', $post['regdate']).' &middot; '.(int) $post['postnum'].' posts';
        }

        $rows .= '<tr><td class="trow1"><strong><a href="'.$postUrl.'">'.$subject.'</a></strong><br /><span class="smalltext">'.$meta.'</span><br /><span class="smalltext">'.$excerpt.'</span></td></tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td class="trow1">No matching posts were found.</td></tr>';
    }

    return $rows;
}

function staff_tools_recent_posts_query($limit, $start = 0, &$total = null)
{
    global $db;

    $limit = max(1, (int) $limit);
    $start = max(0, (int) $start);
    $where = array('p.visible=1', 't.visible=1');
    $where = array_merge($where, staff_tools_excluded_user_where('u', 'p'));
    $unviewable = get_unviewable_forums(true);
    if ($unviewable) {
        $where[] = 'p.fid NOT IN ('.$unviewable.')';
    }
    $whereSql = implode(' AND ', $where);

    if ($total !== null) {
        $countQuery = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql}");
        $total = (int) $db->fetch_field($countQuery, 'total');
    }

    return $db->query("SELECT p.pid,p.tid,p.uid,p.username,p.subject,p.message,p.dateline,t.subject AS threadsubject,u.additionalgroups FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql} ORDER BY p.dateline DESC LIMIT {$start},{$limit}");
}

function staff_tools_recent_posts_rows($limit, $start = 0, &$total = null)
{
    return staff_tools_post_rows(staff_tools_recent_posts_query($limit, $start, $total));
}

function staff_tools_new_member_posts_query($limit, $start = 0, &$total = null)
{
    global $mybb, $db;

    $limit = max(1, (int) $limit);
    $start = max(0, (int) $start);
    $days = max(1, (int) $mybb->settings['staff_tools_newmember_days']);
    $postThreshold = max(0, (int) $mybb->settings['staff_tools_newmember_posts']);
    $cutoff = TIME_NOW - ($days * 86400);
    $where = array('p.visible=1', 't.visible=1', 'p.uid>0', "(u.regdate>={$cutoff} OR u.postnum<={$postThreshold})");
    $where = array_merge($where, staff_tools_excluded_user_where('u', 'p'));
    $unviewable = get_unviewable_forums(true);
    if ($unviewable) {
        $where[] = 'p.fid NOT IN ('.$unviewable.')';
    }
    $whereSql = implode(' AND ', $where);

    if ($total !== null) {
        $countQuery = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql}");
        $total = (int) $db->fetch_field($countQuery, 'total');
    }

    return $db->query("SELECT p.pid,p.tid,p.uid,p.username,p.subject,p.message,p.dateline,t.subject AS threadsubject,u.regdate,u.postnum,u.additionalgroups FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) INNER JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql} ORDER BY p.dateline DESC LIMIT {$start},{$limit}");
}

function staff_tools_new_member_rows($limit, $start = 0, &$total = null)
{
    return staff_tools_post_rows(staff_tools_new_member_posts_query($limit, $start, $total));
}

function staff_tools_page($title, $body)
{
    global $mybb, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav, $staff_tools_page_title, $staff_tools_content;

    $staff_tools_page_title = htmlspecialchars_uni($title);
    $staff_tools_content = $body;
    eval('$pageOutput = "'.$templates->get('staff_tools_page').'";');
    output_page($pageOutput);
    exit;
}

function staff_tools_table($title, $rows, $footer = '', $columns = 1)
{
    global $theme;

    $columns = max(1, (int) $columns);
    $html = '<table border="0" cellspacing="'.$theme['borderwidth'].'" cellpadding="'.$theme['tablespace'].'" class="tborder staff-tools-table">';
    $html .= '<tr><td class="thead" colspan="'.$columns.'"><strong>'.htmlspecialchars_uni($title).'</strong></td></tr>';
    $html .= $rows;
    if ($footer !== '') {
        $html .= '<tr><td class="tfoot" colspan="'.$columns.'">'.$footer.'</td></tr>';
    }
    $html .= '</table>';

    return $html;
}

function staff_tools_duplicate_finder()
{
    global $mybb, $db;

    $lookup = trim($mybb->get_input('lookup'));
    $lookupHtml = htmlspecialchars_uni($lookup);
    $form = '<form action="modcp.php" method="get"><input type="hidden" name="action" value="duplicates" /><table border="0" cellspacing="0" cellpadding="4" width="100%"><tr><td class="trow1"><strong>User ID or username:</strong> <input type="text" class="textbox" name="lookup" value="'.$lookupHtml.'" /> <input type="submit" class="button" value="Find Matches" /></td></tr></table></form><br />';

    if ($lookup === '') {
        return $form.staff_tools_table('Duplicate Account Finder', '<tr><td class="trow1">Enter a user ID or username to find accounts sharing registration or last-known IP addresses.</td></tr>');
    }

    if (ctype_digit($lookup)) {
        $where = 'uid='.(int) $lookup;
    } else {
        $where = "username='".$db->escape_string($lookup)."'";
    }

    $query = $db->simple_select('users', 'uid,username,regip,lastip,regdate,lastactive,postnum', $where, array('limit' => 1));
    $target = $db->fetch_array($query);
    if (!$target) {
        return $form.staff_tools_table('Duplicate Account Finder', '<tr><td class="trow1">No user matched that lookup.</td></tr>');
    }

    $conditions = array();
    if ($target['regip'] !== '') {
        $conditions[] = "regip=".$db->escape_binary($target['regip']);
        $conditions[] = "lastip=".$db->escape_binary($target['regip']);
    }
    if ($target['lastip'] !== '') {
        $conditions[] = "regip=".$db->escape_binary($target['lastip']);
        $conditions[] = "lastip=".$db->escape_binary($target['lastip']);
    }
    if (!$conditions) {
        return $form.staff_tools_table('Duplicate Account Finder', '<tr><td class="trow1">That user does not have stored IP data to compare.</td></tr>');
    }

    $whereParts = array('u.uid!='.(int) $target['uid'].' AND ('.implode(' OR ', $conditions).')');
    $whereParts = array_merge($whereParts, staff_tools_excluded_user_where('u'));
    $query = $db->query("SELECT u.uid,u.username,u.regip,u.lastip,u.regdate,u.lastactive,u.postnum FROM ".TABLE_PREFIX."users u WHERE ".implode(' AND ', $whereParts)." ORDER BY u.lastactive DESC LIMIT 100");

    $canViewIps = !empty($mybb->usergroup['canuseipsearch']) || !empty($mybb->usergroup['cancp']);
    $matchHeader = $canViewIps ? 'Match / IP' : 'Match';
    $rows = '<tr><td class="tcat"><strong>Account</strong></td><td class="tcat"><strong>'.$matchHeader.'</strong></td><td class="tcat"><strong>Registered</strong></td><td class="tcat"><strong>Last Active</strong></td><td class="tcat"><strong>Posts</strong></td></tr>';
    $found = false;
    while ($user = $db->fetch_array($query)) {
        $found = true;
        $matches = array();
        if ($target['regip'] !== '' && ($user['regip'] === $target['regip'] || $user['lastip'] === $target['regip'])) {
            $matches[] = 'Shared target registration IP'.($canViewIps ? ': '.staff_tools_format_ip($target['regip']) : '');
        }
        if ($target['lastip'] !== '' && ($user['regip'] === $target['lastip'] || $user['lastip'] === $target['lastip'])) {
            $matches[] = 'Shared target last IP'.($canViewIps ? ': '.staff_tools_format_ip($target['lastip']) : '');
        }
        $account = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']);
        $rows .= '<tr><td class="trow1">'.$account.'</td><td class="trow1">'.implode('<br />', array_unique($matches)).'</td><td class="trow1">'.my_date('relative', $user['regdate']).'</td><td class="trow1">'.my_date('relative', $user['lastactive']).'</td><td class="trow1">'.(int) $user['postnum'].'</td></tr>';
    }
    if (!$found) {
        $rows .= '<tr><td class="trow1" colspan="5">No matching accounts were found.</td></tr>';
    }

    $targetLabel = build_profile_link(htmlspecialchars_uni($target['username']), $target['uid']);
    $targetIps = '';
    if ($canViewIps) {
        $targetIpParts = array();
        if ($target['regip'] !== '') {
            $targetIpParts[] = 'Registration IP: '.staff_tools_format_ip($target['regip']);
        }
        if ($target['lastip'] !== '') {
            $targetIpParts[] = 'Last IP: '.staff_tools_format_ip($target['lastip']);
        }
        if ($targetIpParts) {
            $targetIps = ' &middot; '.implode(' &middot; ', $targetIpParts);
        }
    }
    $summary = '<tr><td class="trow2" colspan="5"><strong>Target:</strong> '.$targetLabel.' &middot; Registered '.my_date('relative', $target['regdate']).' &middot; '.(int) $target['postnum'].' posts'.$targetIps.'</td></tr>';
    return $form.staff_tools_table('Duplicate Account Finder', $summary.$rows, '', 5);
}

function staff_tools_format_ip($packedIp)
{
    global $db;

    if ($packedIp === '') {
        return '<em>Unknown</em>';
    }

    return htmlspecialchars_uni(my_inet_ntop($db->unescape_binary($packedIp)));
}

function staff_tools_handle_task_post()
{
    global $mybb, $db;

    if ($mybb->request_method !== 'post') {
        return;
    }
    verify_post_check($mybb->get_input('my_post_key'));

    $do = $mybb->get_input('do');
    if ($do === 'add_task') {
        $title = trim($mybb->get_input('title'));
        if ($title !== '') {
            $priority = $mybb->get_input('priority');
            if (!in_array($priority, array('low', 'normal', 'high', 'urgent'), true)) {
                $priority = 'normal';
            }
            $assignedUid = staff_tools_assignable_uid($mybb->get_input('assigned_uid', MyBB::INPUT_INT));
            $insert = array(
                'title' => $db->escape_string($title),
                'description' => $db->escape_string(trim($mybb->get_input('description'))),
                'status' => 'open',
                'priority' => $db->escape_string($priority),
                'assigned_uid' => $assignedUid,
                'created_uid' => (int) $mybb->user['uid'],
                'dateline' => TIME_NOW,
                'duedate' => staff_tools_parse_due_date($mybb->get_input('duedate'))
            );
            if (staff_tools_task_column_exists('completion_note')) {
                $insert['completion_note'] = '';
            }
            $taskId = (int) $db->insert_query('staff_tools_tasks', $insert);
            if ($taskId > 0 && $assignedUid > 0) {
                staff_tools_send_assignment_pm($assignedUid, $title, $taskId, true);
            }
        }
        redirect('modcp.php?action=stafftasks');
    }

    if ($do === 'update_task') {
        $taskId = $mybb->get_input('task_id', MyBB::INPUT_INT);
        $status = $mybb->get_input('status');
        if ($taskId > 0 && in_array($status, array('open', 'done', 'removed'), true)) {
            if ($status === 'done' && !staff_tools_task_assigned_to_current_user($taskId)) {
                redirect(staff_tools_task_return_url($taskId));
            }
            if ($status === 'removed' && !staff_tools_can_remove_tasks()) {
                redirect(staff_tools_task_return_url($taskId));
            }

            $update = array('status' => $db->escape_string($status));
            if ($status === 'done') {
                $update['completed_uid'] = (int) $mybb->user['uid'];
                $update['completed_date'] = TIME_NOW;
                if (staff_tools_task_column_exists('completion_note')) {
                    $update['completion_note'] = $db->escape_string(trim($mybb->get_input('completion_note')));
                }
            } else {
                $update['completed_uid'] = 0;
                $update['completed_date'] = 0;
                if (staff_tools_task_column_exists('completion_note')) {
                    $update['completion_note'] = '';
                }
            }
            $db->update_query('staff_tools_tasks', $update, 'tid='.(int) $taskId);
        }
        redirect(staff_tools_task_return_url($taskId));
    }

    if ($do === 'edit_task') {
        $taskId = $mybb->get_input('task_id', MyBB::INPUT_INT);
        if ($taskId > 0 && staff_tools_task_editable_by_current_user($taskId)) {
            $title = trim($mybb->get_input('title'));
            if ($title === '') {
                redirect(staff_tools_task_return_url($taskId));
            }
            $priority = $mybb->get_input('priority');
            if (!in_array($priority, array('low', 'normal', 'high', 'urgent'), true)) {
                $priority = 'normal';
            }
            $oldQuery = $db->simple_select('staff_tools_tasks', 'assigned_uid', 'tid='.(int) $taskId, array('limit' => 1));
            $oldAssignedUid = (int) $db->fetch_field($oldQuery, 'assigned_uid');
            $assignedUid = staff_tools_assignable_uid($mybb->get_input('assigned_uid', MyBB::INPUT_INT));
            $db->update_query('staff_tools_tasks', array(
                'title' => $db->escape_string($title),
                'description' => $db->escape_string(trim($mybb->get_input('description'))),
                'priority' => $db->escape_string($priority),
                'assigned_uid' => $assignedUid,
                'duedate' => staff_tools_parse_due_date($mybb->get_input('duedate'))
            ), 'tid='.(int) $taskId);
            if ($assignedUid > 0 && $assignedUid !== $oldAssignedUid) {
                staff_tools_send_assignment_pm($assignedUid, $title, $taskId, false);
            }
        }
        redirect(staff_tools_task_return_url($taskId));
    }

    if ($do === 'claim_task') {
        $taskId = $mybb->get_input('task_id', MyBB::INPUT_INT);
        if ($taskId > 0) {
            $update = array(
                'assigned_uid' => (int) $mybb->user['uid'],
                'status' => 'open',
                'completed_uid' => 0,
                'completed_date' => 0
            );
            if (staff_tools_task_column_exists('completion_note')) {
                $update['completion_note'] = '';
            }
            $db->update_query('staff_tools_tasks', $update, 'tid='.(int) $taskId);
        }
        redirect(staff_tools_task_return_url($taskId));
    }
}

function staff_tools_task_return_url($taskId)
{
    global $mybb;

    if ($mybb->get_input('action') === 'stafftask') {
        return 'modcp.php?action=stafftask&task_id='.(int) $taskId;
    }

    return 'modcp.php?action=stafftasks';
}

function staff_tools_task_assigned_to_current_user($taskId)
{
    global $mybb, $db;

    $query = $db->simple_select('staff_tools_tasks', 'assigned_uid', 'tid='.(int) $taskId, array('limit' => 1));
    return (int) $db->fetch_field($query, 'assigned_uid') === (int) $mybb->user['uid'];
}

function staff_tools_task_editable_by_current_user($taskId)
{
    global $mybb, $db;

    $query = $db->simple_select('staff_tools_tasks', 'created_uid,status', 'tid='.(int) $taskId, array('limit' => 1));
    $task = $db->fetch_array($query);
    return $task && (int) $task['created_uid'] === (int) $mybb->user['uid'] && $task['status'] === 'open';
}

function staff_tools_send_assignment_pm($assignedUid, $title, $taskId, $isNewTask = true)
{
    global $mybb, $session;

    $assignedUid = (int) $assignedUid;
    if (isset($mybb->settings['staff_tools_task_pms']) && empty($mybb->settings['staff_tools_task_pms'])) {
        return false;
    }
    if ($assignedUid <= 0 || $assignedUid === (int) $mybb->user['uid']) {
        return false;
    }

    $pmFile = MYBB_ROOT.'inc/datahandlers/pm.php';
    if (!file_exists($pmFile)) {
        return false;
    }
    require_once $pmFile;
    if (!class_exists('PMDataHandler')) {
        return false;
    }

    $verb = $isNewTask ? 'assigned you a staff task' : 'reassigned a staff task to you';
    $subject = my_substr('Staff task assigned: '.$title, 0, 85);
    $message = $mybb->user['username'].' '.$verb.":\n\n".$title."\n\nView task: ".$mybb->settings['bburl'].'/modcp.php?action=stafftask&task_id='.(int) $taskId;
    $pmhandler = new PMDataHandler();
    $pmhandler->admin_override = true;
    $pmhandler->set_data(array(
        'subject' => $subject,
        'message' => $message,
        'fromid' => (int) $mybb->user['uid'],
        'do' => '',
        'pmid' => 0,
        'saveasdraft' => 0,
        'ipaddress' => isset($session->packedip) ? $session->packedip : '',
        'toid' => array($assignedUid),
        'bccid' => array(),
        'icon' => -1,
        'options' => array(
            'signature' => 0,
            'disablesmilies' => 0,
            'savecopy' => 0,
            'readreceipt' => 0
        )
    ));

    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();
        return true;
    }

    return false;
}

function staff_tools_can_remove_tasks()
{
    global $mybb;

    if (!empty($mybb->usergroup['cancp'])) {
        return true;
    }

    return function_exists('is_super_admin') && is_super_admin((int) $mybb->user['uid']);
}

function staff_tools_task_column_exists($column)
{
    global $db;

    return method_exists($db, 'field_exists') && $db->field_exists($column, 'staff_tools_tasks');
}

function staff_tools_user_link($uid, $fallback = 'Unknown')
{
    global $db;

    $uid = (int) $uid;
    if ($uid <= 0) {
        return '<em>'.htmlspecialchars_uni($fallback).'</em>';
    }

    $query = $db->simple_select('users', 'uid,username', 'uid='.$uid, array('limit' => 1));
    $user = $db->fetch_array($query);
    $username = $user ? $user['username'] : 'UID '.$uid;

    return build_profile_link(htmlspecialchars_uni($username), $uid);
}

function staff_tools_user_label($uid, $fallback = 'Unknown')
{
    global $db;

    $uid = (int) $uid;
    if ($uid <= 0) {
        return '<em>'.htmlspecialchars_uni($fallback).'</em>';
    }

    $query = $db->simple_select('users', 'username', 'uid='.$uid, array('limit' => 1));
    $username = $db->fetch_field($query, 'username');
    if (!$username) {
        return 'UID '.$uid;
    }

    return htmlspecialchars_uni($username);
}

function staff_tools_assignable_group_where($userAlias = 'u')
{
    global $mybb;

    $groups = staff_tools_csv_ids(isset($mybb->settings['staff_tools_task_groups']) ? $mybb->settings['staff_tools_task_groups'] : '');
    if (!$groups) {
        return '';
    }

    $where = array("{$userAlias}.usergroup IN (".implode(',', $groups).")");
    foreach ($groups as $gid) {
        $where[] = "FIND_IN_SET({$gid}, {$userAlias}.additionalgroups)";
    }

    return '('.implode(' OR ', $where).')';
}

function staff_tools_assignable_uid($uid)
{
    global $db;

    $uid = (int) $uid;
    if ($uid <= 0) {
        return 0;
    }

    $groupWhere = staff_tools_assignable_group_where('u');
    if ($groupWhere === '') {
        return 0;
    }

    $query = $db->query("SELECT u.uid FROM ".TABLE_PREFIX."users u WHERE u.uid={$uid} AND {$groupWhere} LIMIT 1");
    return (int) $db->fetch_field($query, 'uid');
}

function staff_tools_assignable_user_options($selectedUid = 0)
{
    global $db;

    $selectedUid = (int) $selectedUid;
    $options = '<option value="0"'.($selectedUid === 0 ? ' selected="selected"' : '').'>Unassigned</option>';
    $groupWhere = staff_tools_assignable_group_where('u');
    if ($groupWhere === '') {
        return $options;
    }

    $query = $db->query("SELECT u.uid,u.username FROM ".TABLE_PREFIX."users u WHERE {$groupWhere} ORDER BY u.username ASC LIMIT 500");
    if ($query) {
        while ($user = $db->fetch_array($query)) {
            $selected = (int) $user['uid'] === $selectedUid ? ' selected="selected"' : '';
            $options .= '<option value="'.(int) $user['uid'].'"'.$selected.'>'.htmlspecialchars_uni($user['username']).'</option>';
        }
    }

    return $options;
}

function staff_tools_priority_options($selectedPriority = 'normal')
{
    $priorities = array('low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent');
    $options = '';
    foreach ($priorities as $value => $label) {
        $selected = $value === $selectedPriority ? ' selected="selected"' : '';
        $options .= '<option value="'.$value.'"'.$selected.'>'.$label.'</option>';
    }

    return $options;
}

function staff_tools_task_status_form($taskId, $status, $label)
{
    global $mybb;

    $returnAction = $mybb->get_input('action') === 'stafftask' ? 'stafftask&amp;task_id='.(int) $taskId : 'stafftasks';
    return '<form action="modcp.php?action='.$returnAction.'" method="post" style="display:inline"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="do" value="update_task" /><input type="hidden" name="task_id" value="'.(int) $taskId.'" /><input type="hidden" name="status" value="'.htmlspecialchars_uni($status).'" /><input type="submit" class="button" value="'.htmlspecialchars_uni($label).'" /></form>';
}

function staff_tools_task_done_form($taskId)
{
    global $mybb;

    $returnAction = $mybb->get_input('action') === 'stafftask' ? 'stafftask&amp;task_id='.(int) $taskId : 'stafftasks';
    if ($mybb->get_input('action') !== 'stafftask') {
        return staff_tools_task_status_form($taskId, 'done', 'Mark Done');
    }

    return '<form action="modcp.php?action='.$returnAction.'" method="post" class="staff-tools-done-form"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="do" value="update_task" /><input type="hidden" name="task_id" value="'.(int) $taskId.'" /><input type="hidden" name="status" value="done" /><textarea name="completion_note" rows="3" cols="60" style="width: 98%" placeholder="Completion note"></textarea><br /><input type="submit" class="button" value="Mark Done" /></form>';
}

function staff_tools_task_claim_form($taskId)
{
    global $mybb;

    $returnAction = $mybb->get_input('action') === 'stafftask' ? 'stafftask&amp;task_id='.(int) $taskId : 'stafftasks';
    return '<form action="modcp.php?action='.$returnAction.'" method="post" style="display:inline"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="do" value="claim_task" /><input type="hidden" name="task_id" value="'.(int) $taskId.'" /><input type="submit" class="button" value="Claim" /></form>';
}

function staff_tools_button_link($url, $label)
{
    $parts = parse_url(html_entity_decode($url));
    $action = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : 'modcp.php';
    $params = array();
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $params);
    }

    $html = '<form action="'.htmlspecialchars_uni($action).'" method="get" style="display:inline">';
    foreach ($params as $name => $value) {
        $html .= '<input type="hidden" name="'.htmlspecialchars_uni($name).'" value="'.htmlspecialchars_uni($value).'" />';
    }
    $html .= '<input type="submit" class="button" value="'.htmlspecialchars_uni($label).'" /></form>';

    return $html;
}

function staff_tools_parse_due_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    if (ctype_digit($value)) {
        return max(0, (int) $value);
    }
    $timestamp = strtotime($value);
    return $timestamp ? (int) $timestamp : 0;
}

function staff_tools_task_queue()
{
    global $mybb, $db;

    staff_tools_ensure_tasks_table(true);
    staff_tools_handle_task_post();

    $priorityOptions = staff_tools_priority_options();

    $assigneeOptions = staff_tools_assignable_user_options();
    $addForm = '<form action="modcp.php?action=stafftasks" method="post"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="do" value="add_task" /><table border="0" cellspacing="0" cellpadding="4" width="100%" class="staff-tools-table"><tr><td class="trow1"><strong>Title</strong><br /><input type="text" class="textbox" name="title" style="width: 98%" /></td></tr><tr><td class="trow1"><strong>Description</strong><br /><textarea name="description" rows="4" cols="60" style="width: 98%"></textarea></td></tr><tr><td class="trow1"><strong>Assign To</strong> <select name="assigned_uid">'.$assigneeOptions.'</select> <strong>Due date</strong> <input type="date" class="textbox" name="duedate" /> <strong>Priority</strong> <select name="priority">'.$priorityOptions.'</select> <input type="submit" class="button" value="Add Task" /></td></tr></table></form>';

    $rows = '<tr><td class="tcat"><strong>Task</strong></td><td class="tcat"><strong>Status</strong></td><td class="tcat"><strong>Assigned</strong></td><td class="tcat"><strong>Created</strong></td><td class="tcat"><strong>Actions</strong></td></tr>';
    $query = $db->simple_select('staff_tools_tasks', '*', "status NOT IN ('closed', 'removed')", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 100));
    if (!$query) {
        $rows .= '<tr><td class="trow1" colspan="5">The staff task table could not be read.</td></tr>';
        return staff_tools_table('Add Staff Task', '<tr><td class="trow1">'.$addForm.'</td></tr>').'<br />'.staff_tools_table('Staff Task Queue', $rows, '', 5);
    }
    $found = false;
    while ($task = $db->fetch_array($query)) {
        $found = true;
        $assigned = staff_tools_user_label($task['assigned_uid'], 'Unassigned');
        $creator = staff_tools_user_label($task['created_uid'], 'Unknown');
        $taskUrl = 'modcp.php?action=stafftask&amp;task_id='.(int) $task['tid'];
        $details = '<strong><a href="'.$taskUrl.'">'.htmlspecialchars_uni($task['title']).'</a></strong> <span class="smalltext">('.htmlspecialchars_uni($task['priority']).')</span>';
        if ($task['description'] !== '') {
            $details .= '<br /><span class="smalltext">'.htmlspecialchars_uni(my_substr($task['description'], 0, 160)).'</span>';
        }
        if ((int) $task['duedate'] > 0) {
            $details .= '<br /><span class="smalltext">Due '.my_date('normal', $task['duedate']).'</span>';
        }
        $actions = '';
        if ((int) $task['assigned_uid'] !== (int) $mybb->user['uid']) {
            $actions .= staff_tools_task_claim_form($task['tid']).' ';
        }
        $actions .= '<form action="modcp.php" method="get" style="display:inline"><input type="hidden" name="action" value="stafftask" /><input type="hidden" name="task_id" value="'.(int) $task['tid'].'" /><input type="submit" class="button" value="View" /></form> ';
        if ($task['status'] === 'open' && (int) $task['assigned_uid'] === (int) $mybb->user['uid']) {
            $actions .= staff_tools_task_status_form($task['tid'], 'done', 'Mark Done').' ';
        } elseif ($task['status'] !== 'open') {
            $actions .= staff_tools_task_status_form($task['tid'], 'open', 'Reopen').' ';
        }
        if (staff_tools_can_remove_tasks()) {
            $actions .= staff_tools_task_status_form($task['tid'], 'removed', 'Remove');
        }
        $rows .= '<tr><td class="trow1">'.$details.'</td><td class="trow1">'.htmlspecialchars_uni($task['status']).'</td><td class="trow1">'.$assigned.'</td><td class="trow1">'.$creator.'<br /><span class="smalltext">'.my_date('relative', $task['dateline']).'</span></td><td class="trow1 staff-tools-actions">'.$actions.'</td></tr>';
    }
    if (!$found) {
        $rows .= '<tr><td class="trow1" colspan="5">No open staff tasks.</td></tr>';
    }

    return staff_tools_table('Add Staff Task', '<tr><td class="trow1">'.$addForm.'</td></tr>').'<br />'.staff_tools_table('Staff Task Queue', $rows, '', 5);
}

function staff_tools_task_actions($task)
{
    global $mybb;

    $actions = '<div class="staff-tools-actions">'.staff_tools_button_link('modcp.php?action=stafftasks', 'Back to task queue').' ';
    if (staff_tools_task_editable($task) && $mybb->get_input('mode') !== 'edit') {
        $actions .= staff_tools_button_link('modcp.php?action=stafftask&amp;task_id='.(int) $task['tid'].'&amp;mode=edit', 'Edit').' ';
    }
    if ((int) $task['assigned_uid'] !== (int) $mybb->user['uid']) {
        $actions .= staff_tools_task_claim_form($task['tid']).' ';
    }
    if ($task['status'] === 'open' && (int) $task['assigned_uid'] === (int) $mybb->user['uid']) {
        $actions .= staff_tools_task_done_form($task['tid']).' ';
    } elseif ($task['status'] !== 'open') {
        $actions .= staff_tools_task_status_form($task['tid'], 'open', 'Reopen').' ';
    }
    if (staff_tools_can_remove_tasks()) {
        $actions .= staff_tools_task_status_form($task['tid'], 'removed', 'Remove');
    }

    return $actions.'</div>';
}

function staff_tools_task_editable($task)
{
    global $mybb;

    return (int) $task['created_uid'] === (int) $mybb->user['uid'] && $task['status'] === 'open';
}

function staff_tools_task_edit_form($task)
{
    global $mybb;

    if (!staff_tools_task_editable($task) || $mybb->get_input('mode') !== 'edit') {
        return '';
    }

    $assigneeOptions = staff_tools_assignable_user_options($task['assigned_uid']);
    $priorityOptions = staff_tools_priority_options($task['priority']);
    $dueDate = (int) $task['duedate'] > 0 ? date('Y-m-d', $task['duedate']) : '';

    $form = '<br /><form action="modcp.php?action=stafftask&amp;task_id='.(int) $task['tid'].'" method="post"><input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" /><input type="hidden" name="do" value="edit_task" /><input type="hidden" name="task_id" value="'.(int) $task['tid'].'" />';
    $form .= '<table border="0" cellspacing="0" cellpadding="4" width="100%" class="staff-tools-table">';
    $form .= '<tr><td class="trow1"><strong>Title</strong><br /><input type="text" class="textbox" name="title" value="'.htmlspecialchars_uni($task['title']).'" style="width: 98%" /></td></tr>';
    $form .= '<tr><td class="trow1"><strong>Description</strong><br /><textarea name="description" rows="4" cols="60" style="width: 98%">'.htmlspecialchars_uni($task['description']).'</textarea></td></tr>';
    $form .= '<tr><td class="trow1"><strong>Assign To</strong> <select name="assigned_uid">'.$assigneeOptions.'</select> <strong>Due date</strong> <input type="date" class="textbox" name="duedate" value="'.htmlspecialchars_uni($dueDate).'" /> <strong>Priority</strong> <select name="priority">'.$priorityOptions.'</select> <input type="submit" class="button" value="Save Task" /> <a href="modcp.php?action=stafftask&amp;task_id='.(int) $task['tid'].'" class="button">Cancel</a></td></tr>';
    $form .= '</table></form>';

    return $form;
}

function staff_tools_task_detail()
{
    global $mybb, $db;

    staff_tools_ensure_tasks_table(true);
    staff_tools_handle_task_post();

    $taskId = $mybb->get_input('task_id', MyBB::INPUT_INT);
    if ($taskId <= 0) {
        return staff_tools_table('Staff Task', '<tr><td class="trow1">No task was selected.</td></tr>', '<a href="modcp.php?action=stafftasks">Back to task queue</a>');
    }

    $query = $db->query("SELECT st.*, cu.username AS creator, au.username AS assigned_user, du.username AS completed_user FROM ".TABLE_PREFIX."staff_tools_tasks st LEFT JOIN ".TABLE_PREFIX."users cu ON (cu.uid=st.created_uid) LEFT JOIN ".TABLE_PREFIX."users au ON (au.uid=st.assigned_uid) LEFT JOIN ".TABLE_PREFIX."users du ON (du.uid=st.completed_uid) WHERE st.tid=".(int) $taskId." LIMIT 1");
    $task = $db->fetch_array($query);
    if (!$task) {
        return staff_tools_table('Staff Task', '<tr><td class="trow1">That task could not be found.</td></tr>', '<a href="modcp.php?action=stafftasks">Back to task queue</a>');
    }

    $assigned = $task['assigned_uid'] ? build_profile_link(htmlspecialchars_uni($task['assigned_user'] ?: 'UID '.$task['assigned_uid']), $task['assigned_uid']) : '<em>Unassigned</em>';
    $creator = $task['created_uid'] ? build_profile_link(htmlspecialchars_uni($task['creator'] ?: 'UID '.$task['created_uid']), $task['created_uid']) : '<em>Unknown</em>';
    $completed = $task['completed_uid'] ? build_profile_link(htmlspecialchars_uni($task['completed_user'] ?: 'UID '.$task['completed_uid']), $task['completed_uid']) : '<em>Not completed</em>';
    $completedDate = (int) $task['completed_date'] > 0 ? my_date('normal', $task['completed_date']) : '<em>Not completed</em>';
    $dueDate = (int) $task['duedate'] > 0 ? my_date('normal', $task['duedate']) : '<em>No due date</em>';
    $description = $task['description'] !== '' ? nl2br(htmlspecialchars_uni($task['description'])) : '<em>No description.</em>';

    $rows = '<tr><td class="tcat" colspan="2"><strong>'.htmlspecialchars_uni($task['title']).'</strong></td></tr>';
    $rows .= '<tr><td class="trow1" width="20%"><strong>Status</strong></td><td class="trow1">'.htmlspecialchars_uni($task['status']).'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Priority</strong></td><td class="trow1">'.htmlspecialchars_uni($task['priority']).'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Assigned To</strong></td><td class="trow1">'.$assigned.'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Created By</strong></td><td class="trow1">'.$creator.' on '.my_date('normal', $task['dateline']).'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Due Date</strong></td><td class="trow1">'.$dueDate.'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Completed By</strong></td><td class="trow1">'.$completed.'</td></tr>';
    $rows .= '<tr><td class="trow1"><strong>Completed Date</strong></td><td class="trow1">'.$completedDate.'</td></tr>';
    if (staff_tools_task_column_exists('completion_note') && !empty($task['completion_note'])) {
        $rows .= '<tr><td class="trow1"><strong>Completion Note</strong></td><td class="trow1">'.nl2br(htmlspecialchars_uni($task['completion_note'])).'</td></tr>';
    }
    $rows .= '<tr><td class="trow1" colspan="2">'.$description.'</td></tr>';

    return staff_tools_table('Staff Task', $rows, staff_tools_task_actions($task), 2).staff_tools_task_edit_form($task);
}

function staff_tools_action_digest()
{
    global $mybb, $db;

    $days = max(1, (int) $mybb->settings['staff_tools_digest_days']);
    $cutoff = TIME_NOW - ($days * 86400);
    $rows = '<tr><td class="tcat"><strong>Action</strong></td><td class="tcat"><strong>Count</strong></td><td class="tcat"><strong>Last Seen</strong></td><td class="tcat" colspan="2"><strong>Most Recent Staff</strong></td></tr>';
    $query = $db->query("SELECT action, COUNT(*) AS total, MAX(dateline) AS lastdate FROM ".TABLE_PREFIX."moderatorlog WHERE dateline>={$cutoff} GROUP BY action ORDER BY total DESC, lastdate DESC LIMIT 50");
    $found = false;
    while ($log = $db->fetch_array($query)) {
        $found = true;
        $action = htmlspecialchars_uni($log['action']);
        $staffQuery = $db->query("SELECT ml.uid,u.username FROM ".TABLE_PREFIX."moderatorlog ml LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=ml.uid) WHERE ml.dateline>={$cutoff} AND ml.action='".$db->escape_string($log['action'])."' ORDER BY ml.dateline DESC LIMIT 1");
        $staff = $db->fetch_array($staffQuery);
        $staffName = !empty($staff['username']) ? $staff['username'] : 'UID '.$staff['uid'];
        $staffLink = !empty($staff['uid']) ? build_profile_link(htmlspecialchars_uni($staffName), $staff['uid']) : '<em>Unknown</em>';
        $rows .= '<tr><td class="trow1">'.$action.'</td><td class="trow1">'.(int) $log['total'].'</td><td class="trow1">'.my_date('relative', $log['lastdate']).'</td><td class="trow1" colspan="2">'.$staffLink.'</td></tr>';
    }
    if (!$found) {
        $rows .= '<tr><td class="trow1" colspan="5">No moderator-log activity in the selected window.</td></tr>';
    }

    $footer = 'Showing the last '.(int) $days.' day(s). Full detail remains available in Moderator Logs.';
    return staff_tools_table('Staff Action Digest', $rows, $footer, 5);
}

function staff_tools_insert_modcp_home_panel($placeholder, $needles, $before = true)
{
    global $templates;

    if (!isset($templates->cache['modcp']) || strpos($templates->cache['modcp'], $placeholder) !== false) {
        return false;
    }

    foreach ((array) $needles as $needle) {
        if (strpos($templates->cache['modcp'], $needle) === false) {
            continue;
        }

        $replacement = $before ? $placeholder.$needle : $needle.$placeholder;
        $templates->cache['modcp'] = preg_replace('/'.preg_quote($needle, '/').'/', $replacement, $templates->cache['modcp'], 1);
        return true;
    }

    return false;
}

function staff_tools_modcp_home()
{
    global $mybb, $templates, $staff_tools_rows, $staff_tools_home_recentposts, $staff_tools_home_rows, $staff_tools_home_panel, $db;

    if ($mybb->get_input('action') !== '') {
        return;
    }

    if (staff_tools_setting_enabled('staff_tools_recentposts')) {
        $staff_tools_rows = staff_tools_recent_posts_rows(5);
        eval('$staff_tools_home_recentposts = "'.$templates->get('staff_tools_recentposts_panel').'";');
        staff_tools_insert_modcp_home_panel('{$staff_tools_home_recentposts}', array(
            '<form action="modcp.php" method="post">'
        ));
    }

    if (!staff_tools_setting_enabled('staff_tools_snapshot')) {
        return;
    }

    $snapshotRows = '';
    if (staff_tools_setting_enabled('staff_tools_newmembers')) {
        $total = 0;
        staff_tools_new_member_posts_query(1, 0, $total);
        $snapshotRows .= '<tr><td class="trow1"><strong>New member review:</strong> <a href="modcp.php?action=newmembers">'.(int) $total.' matching recent posts</a></td></tr>';
    }
    if (staff_tools_setting_enabled('staff_tools_tasks') && $db->table_exists('staff_tools_tasks')) {
        $query = $db->simple_select('staff_tools_tasks', 'COUNT(*) AS total', "status='open'");
        $total = (int) $db->fetch_field($query, 'total');
        $snapshotRows .= '<tr><td class="trow1"><strong>Open staff tasks:</strong> <a href="modcp.php?action=stafftasks">'.$total.' task(s)</a></td></tr>';
    }
    if (staff_tools_setting_enabled('staff_tools_digest')) {
        $snapshotRows .= '<tr><td class="trow1"><strong>Action digest:</strong> <a href="modcp.php?action=staffdigest">View recent staff activity summary</a></td></tr>';
    }

    if ($snapshotRows !== '') {
        $staff_tools_home_rows = $snapshotRows;
        eval('$staff_tools_home_panel = "'.$templates->get('staff_tools_home_panel').'";');
        if (!staff_tools_insert_modcp_home_panel('{$staff_tools_home_panel}', '{$awaitingmoderation}')) {
            staff_tools_insert_modcp_home_panel('{$staff_tools_home_panel}', '<td valign="top">', false);
        }
    }
}

function staff_tools_modcp()
{
    global $mybb;

    $action = $mybb->get_input('action');
    if ($action === 'recentposts' && staff_tools_setting_enabled('staff_tools_recentposts')) {
        add_breadcrumb('Recent Posts', 'modcp.php?action=recentposts');
        $perPage = staff_tools_per_page();
        $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
        $start = ($page - 1) * $perPage;
        $total = 0;
        $rows = staff_tools_recent_posts_rows($perPage, $start, $total);
        $multipage = multipage($total, $perPage, $page, 'modcp.php?action=recentposts');
        staff_tools_page('Recent Posts', staff_tools_table('Recent Posts Review', $rows).$multipage);
    }

    if ($action === 'newmembers' && staff_tools_setting_enabled('staff_tools_newmembers')) {
        add_breadcrumb('New Member Watchlist', 'modcp.php?action=newmembers');
        $perPage = staff_tools_per_page();
        $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
        $start = ($page - 1) * $perPage;
        $total = 0;
        $rows = staff_tools_new_member_rows($perPage, $start, $total);
        $multipage = multipage($total, $perPage, $page, 'modcp.php?action=newmembers');
        staff_tools_page('New Member Watchlist', staff_tools_table('New Member Watchlist', $rows).$multipage);
    }

    if ($action === 'duplicates' && staff_tools_setting_enabled('staff_tools_duplicates')) {
        add_breadcrumb('Duplicate Finder', 'modcp.php?action=duplicates');
        staff_tools_page('Duplicate Finder', staff_tools_duplicate_finder());
    }

    if ($action === 'stafftasks' && staff_tools_setting_enabled('staff_tools_tasks')) {
        add_breadcrumb('Staff Tasks', 'modcp.php?action=stafftasks');
        staff_tools_page('Staff Tasks', staff_tools_task_queue());
    }

    if ($action === 'stafftask' && staff_tools_setting_enabled('staff_tools_tasks')) {
        add_breadcrumb('Staff Tasks', 'modcp.php?action=stafftasks');
        add_breadcrumb('View Task', 'modcp.php?action=stafftask');
        staff_tools_page('View Task', staff_tools_task_detail());
    }

    if ($action === 'staffdigest' && staff_tools_setting_enabled('staff_tools_digest')) {
        add_breadcrumb('Action Digest', 'modcp.php?action=staffdigest');
        staff_tools_page('Action Digest', staff_tools_action_digest());
    }
}
