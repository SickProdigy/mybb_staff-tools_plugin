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
        'description' => 'ModCP recent-post review tools.',
        'website' => 'https://gitea.rcs1.top/sickprodigy/mybb_staff-tools_plugin',
        'author' => 'SickProdigy',
        'version' => '0.3.2',
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

function staff_tools_install()
{
    global $db;
    staff_tools_uninstall();

    $gid = $db->insert_query('settinggroups', array(
        'name' => 'staff_tools',
        'title' => 'Staff Tools',
        'description' => 'Settings for Staff Tools.',
        'disporder' => 50,
        'isdefault' => 0
    ));
    $settings = array(
        array('name' => 'staff_tools_recentposts', 'title' => 'Enable ModCP recent posts', 'description' => 'Show the review page to users with ModCP access.', 'optionscode' => 'yesno', 'value' => '1', 'disporder' => 1),
        array('name' => 'staff_tools_perpage', 'title' => 'Posts per page', 'description' => 'Posts displayed per review page (10-100).', 'optionscode' => 'numeric', 'value' => '25', 'disporder' => 2),
        array('name' => 'staff_tools_excluded_users', 'title' => 'Excluded user IDs', 'description' => 'Comma-separated IDs, such as bot accounts.', 'optionscode' => 'text', 'value' => '', 'disporder' => 3),
        array('name' => 'staff_tools_excluded_groups', 'title' => 'Excluded group IDs', 'description' => 'Comma-separated primary or additional group IDs.', 'optionscode' => 'text', 'value' => '3,4,6', 'disporder' => 4)
    );
    foreach ($settings as $setting) {
        $setting['gid'] = $gid;
        $db->insert_query('settings', $setting);
    }

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
    $db->delete_query('templates', "title IN ('staff_tools_recentposts', 'staff_tools_recentposts_panel')");
    rebuild_settings();
}

function staff_tools_activate()
{
    staff_tools_ensure_templates();
}

function staff_tools_deactivate() {}

function staff_tools_templates()
{
    $recentposts = <<<'HTML'
<html>
<head>
<title>{$mybb->settings['bbname']} - Recent Posts</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr><td class="thead"><strong>Recent Posts Review</strong></td></tr>
{$staff_tools_rows}
</table>
{$multipage}
</td>
</tr>
</table>
{$footer}
</body>
</html>
HTML;

    $panel = <<<'HTML'
<br />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr><td class="thead"><strong>Recent Posts Review</strong></td></tr>
{$staff_tools_rows}
<tr><td class="tfoot" align="right"><a href="modcp.php?action=recentposts">View all recent posts</a></td></tr>
</table>
HTML;

    return array(
        'staff_tools_recentposts' => $recentposts,
        'staff_tools_recentposts_panel' => $panel
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

function staff_tools_modcp_nav()
{
    global $mybb, $nav_modlogs;

    if (empty($mybb->settings['staff_tools_recentposts'])) {
        return;
    }

    $nav_modlogs .= '<tr><td class="trow1 smalltext"><a href="modcp.php?action=recentposts" class="modcp_nav_item modcp_nav_recentposts">Recent Posts</a></td></tr>';
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

function staff_tools_recent_posts_query($limit, $start = 0, &$total = null)
{
    global $mybb, $db;

    $limit = max(1, (int) $limit);
    $start = max(0, (int) $start);
    $excludedUsers = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_users']);
    $excludedGroups = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_groups']);
    $where = array('p.visible=1', 't.visible=1');

    if ($excludedUsers) {
        $where[] = 'p.uid NOT IN ('.implode(',', $excludedUsers).')';
    }
    if ($excludedGroups) {
        $where[] = '(u.uid IS NULL OR u.usergroup NOT IN ('.implode(',', $excludedGroups).'))';
        foreach ($excludedGroups as $gid) {
            $where[] = "(u.additionalgroups IS NULL OR u.additionalgroups='' OR NOT FIND_IN_SET({$gid}, u.additionalgroups))";
        }
    }
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
    global $db;

    $query = staff_tools_recent_posts_query($limit, $start, $total);
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
        $actions = '<a href="'.$postUrl.'">View post</a> &middot; <a href="'.$editUrl.'">Edit post</a>'.$warnLink;

        $rows .= '<tr><td class="trow1"><strong><a href="'.$postUrl.'">'.$subject.'</a></strong><br /><span class="smalltext">'.$authorLink.' &middot; '.$date.' &middot; '.$actions.'</span><br /><span class="smalltext">'.$excerpt.'</span></td></tr>';
    }

    if ($rows === '') {
        $rows = '<tr><td class="trow1">No matching posts were found.</td></tr>';
    }

    return $rows;
}

function staff_tools_modcp_home()
{
    global $mybb, $templates, $staff_tools_rows, $staff_tools_home_recentposts;

    if (empty($mybb->settings['staff_tools_recentposts']) || $mybb->get_input('action') !== '') {
        return;
    }

    $staff_tools_rows = staff_tools_recent_posts_rows(5);
    eval('$staff_tools_home_recentposts = "'.$templates->get('staff_tools_recentposts_panel').'";');
    if (isset($templates->cache['modcp']) && strpos($templates->cache['modcp'], '{$staff_tools_home_recentposts}') === false) {
        $templates->cache['modcp'] = str_replace('</form>', '</form>{$staff_tools_home_recentposts}', $templates->cache['modcp']);
    }
}

function staff_tools_modcp()
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;

    if (empty($mybb->settings['staff_tools_recentposts'])) {
        return;
    }
    if ($mybb->get_input('action') !== 'recentposts') {
        return;
    }

    add_breadcrumb('Recent Posts', 'modcp.php?action=recentposts');
    $perPage = max(10, min(100, (int) $mybb->settings['staff_tools_perpage']));
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perPage;
    $total = 0;
    $staff_tools_rows = staff_tools_recent_posts_rows($perPage, $start, $total);
    $multipage = multipage($total, $perPage, $page, 'modcp.php?action=recentposts');
    eval('$pageOutput = "'.$templates->get('staff_tools_recentposts').'";');
    output_page($pageOutput);
    exit;
}
