<?php
/**
 * MyBB Staff Tools
 * Copyright (c) 2026 SickProdigy
 * SPDX-License-Identifier: GPL-3.0-only
 */
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

$plugins->add_hook('modcp_start', 'staff_tools_modcp');

function staff_tools_info()
{
    return array(
        'name' => 'Staff Tools',
        'description' => 'ModCP recent-post review tools.',
        'website' => 'https://gitea.rcs1.top/sickprodigy/mybb_staff-tools_plugin',
        'author' => 'SickProdigy',
        'version' => '0.3.0',
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

    $template = <<<'HTML'
<html><head><title>{$mybb->settings['bbname']} - Recent Posts</title>{$headerinclude}</head>
<body>{$header}<table width="100%" border="0" align="center"><tr>
<td valign="top" width="200">{$modcp_nav}</td><td valign="top">
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
<tr><td class="thead" colspan="2"><strong>Recent Posts Review</strong></td></tr>
{$staff_tools_rows}</table>{$multipage}</td></tr></table>{$footer}</body></html>
HTML;
    $db->insert_query('templates', array(
        'title' => 'staff_tools_recentposts',
        'template' => $db->escape_string($template),
        'sid' => -2,
        'version' => '',
        'dateline' => TIME_NOW
    ));
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
    $db->delete_query('templates', "title='staff_tools_recentposts'");
    rebuild_settings();
}

function staff_tools_activate() {}
function staff_tools_deactivate() {}

function staff_tools_csv_ids($value)
{
    return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $value)))));
}

function staff_tools_modcp()
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $modcp_nav;

    if (empty($mybb->settings['staff_tools_recentposts'])) {
        return;
    }
    if (isset($templates->cache['modcp_nav']) && strpos($templates->cache['modcp_nav'], 'action=recentposts') === false) {
        $link = '<tr><td class="trow1 smalltext"><a href="modcp.php?action=recentposts">Recent Posts</a></td></tr>';
        $templates->cache['modcp_nav'] = str_replace('</table>', $link.'</table>', $templates->cache['modcp_nav']);
    }
    if ($mybb->get_input('action') !== 'recentposts') {
        return;
    }

    add_breadcrumb('Recent Posts', 'modcp.php?action=recentposts');
    $perPage = max(10, min(100, (int) $mybb->settings['staff_tools_perpage']));
    $page = max(1, $mybb->get_input('page', MyBB::INPUT_INT));
    $start = ($page - 1) * $perPage;
    $excludedUsers = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_users']);
    $excludedGroups = staff_tools_csv_ids($mybb->settings['staff_tools_excluded_groups']);
    $where = array('p.visible=1', 't.visible=1');

    if ($excludedUsers) {
        $where[] = 'p.uid NOT IN ('.implode(',', $excludedUsers).')';
    }
    if ($excludedGroups) {
        $where[] = 'u.usergroup NOT IN ('.implode(',', $excludedGroups).')';
    }
    $unviewable = get_unviewable_forums(true);
    if ($unviewable) {
        $where[] = 'p.fid NOT IN ('.$unviewable.')';
    }
    $whereSql = implode(' AND ', $where);

    $countQuery = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql}");
    $total = (int) $db->fetch_field($countQuery, 'total');
    $query = $db->query("SELECT p.pid,p.tid,p.uid,p.username,p.subject,p.message,p.dateline,t.subject AS threadsubject,u.additionalgroups FROM ".TABLE_PREFIX."posts p INNER JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid) LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$whereSql} ORDER BY p.dateline DESC LIMIT {$start},{$perPage}");

    $staff_tools_rows = '';
    while ($post = $db->fetch_array($query)) {
        if ($excludedGroups && array_intersect($excludedGroups, staff_tools_csv_ids($post['additionalgroups']))) {
            continue;
        }
        $subject = htmlspecialchars_uni($post['threadsubject'] ?: $post['subject']);
        $author = htmlspecialchars_uni($post['username']);
        $date = my_date('relative', $post['dateline']);
        $excerpt = htmlspecialchars_uni(my_substr(strip_tags($post['message']), 0, 240));
        $postUrl = get_post_link($post['pid'], $post['tid']).'#pid'.$post['pid'];
        $authorLink = $post['uid'] ? build_profile_link($author, $post['uid']) : $author;
        $warnLink = $post['uid'] ? ' &middot; <a href="warnings.php?action=warn&amp;uid='.(int) $post['uid'].'&amp;pid='.(int) $post['pid'].'">Warn user</a>' : '';
        $staff_tools_rows .= '<tr><td class="trow1"><strong><a href="'.$postUrl.'">'.$subject.'</a></strong><br /><span class="smalltext">'.$authorLink.' &middot; '.$date.$warnLink.'</span></td><td class="trow1 smalltext">'.$excerpt.'</td></tr>';
    }
    if ($staff_tools_rows === '') {
        $staff_tools_rows = '<tr><td class="trow1" colspan="2">No matching posts were found.</td></tr>';
    }
    $multipage = multipage($total, $perPage, $page, 'modcp.php?action=recentposts');
    eval('$pageOutput = "'.$templates->get('staff_tools_recentposts').'";');
    output_page($pageOutput);
    exit;
}
