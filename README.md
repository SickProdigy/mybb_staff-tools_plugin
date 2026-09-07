# MyBB Staff Tools

Modular quality-of-life tools for MyBB administrators and moderators.

## Features

- **ModCP Recent Posts:** a paginated review feed and home-panel preview with configurable user/group exclusions and links to posts, edit tools, profiles, and warning tools.
- **New Member Watchlist:** review recent posts from accounts inside a configurable registration-age window or below a configurable post-count threshold.
- **Duplicate Account Finder:** look up a username or user ID and surface accounts sharing registration or last-known IP data. Staff with IP Search permission can see the matching IP values.
- **Staff Task Queue:** create shared moderation tasks, assign them to staff by username from configured groups, notify assigned staff by PM, let staff claim tasks for themselves, let creators edit open tasks, view full task details, prioritize them, and mark claimed tasks done with an optional completion note. Admins can remove tasks without deleting the saved record.
- **Staff Action Digest:** summarize recent moderator-log activity over a configurable lookback window.
- **ModCP Snapshot:** adds a lightweight Staff Tools summary panel to the ModCP home page.
- **AdminCP Toggles:** enable or disable each Staff Tools module, the snapshot panel, and task assignment PMs from **Configuration → Settings → Staff Tools**.

## Installation

Requires MyBB 1.8.x and PHP 7.4 or newer.

1. Copy the contents of `Upload/` to your forum root.
2. Open **AdminCP → Configuration → Plugins**.
3. Install and activate **Staff Tools**.
4. Review **Configuration → Settings → Staff Tools**.

Test this release on staging before production use. Uninstalling removes this plugin's settings, templates, and staff-task table.

## Settings

Staff Tools settings live in **AdminCP -> Configuration -> Settings -> Staff Tools**.

| Setting | Purpose | Default |
| --- | --- | --- |
| Modules: Recent Posts | Shows the Recent Posts review page in ModCP. | Yes |
| Modules: New Member Watchlist | Shows the New Member Watchlist in ModCP. | Yes |
| Modules: Duplicate Account Finder | Shows the Duplicate Account Finder in ModCP. Staff with IP Search permission can see matching IP values. | Yes |
| Modules: Staff Task Queue | Shows the Staff Task Queue in ModCP. | Yes |
| Modules: Staff Action Digest | Shows the Staff Action Digest in ModCP. | Yes |
| Modules: ModCP Snapshot Panel | Shows the Staff Tools Snapshot panel on the ModCP home page. | Yes |
| Review Feeds: Items Per Page | Controls the number of posts or results shown per page on review feeds. Values are clamped from 10 to 100. | 25 |
| Review Feeds: Excluded User IDs | Comma-separated user IDs hidden from review feeds, such as bot or service accounts. | Empty |
| Review Feeds: Excluded Group IDs | Comma-separated group IDs hidden from review feeds. Checks primary and additional groups. | 3,4,6 |
| New Member Watchlist: Registration Age Window | Includes posts from users registered within this many days. | 14 |
| New Member Watchlist: Low Post-Count Threshold | Includes posts from users with this many posts or fewer. | 10 |
| Staff Task Queue: Assignable Group IDs | Comma-separated group IDs whose members appear in the task assignment dropdown. | 3,4,6 |
| Staff Task Queue: Assignment PMs | Sends a private message when a task is assigned or reassigned to another staff member. | Yes |
| Staff Action Digest: Lookback Days | Controls how many days of moderator-log activity are summarized. | 7 |

## Upgrade Notes

Version 1.0.0 adds new module toggles, assignable staff group settings, templates, and a `staff_tools_tasks` table. Activating the plugin backfills missing release assets, task columns, and setting labels for existing installs without resetting saved setting values.

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
