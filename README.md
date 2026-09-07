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

## Upgrade Notes

Version 1.0.0 adds new module toggles, assignable staff group settings, templates, and a `staff_tools_tasks` table. Activating the plugin backfills missing release assets, task columns, and setting labels for existing installs without resetting saved setting values.

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
