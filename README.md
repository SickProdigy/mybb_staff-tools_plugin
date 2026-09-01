# MyBB Staff Tools

Modular quality-of-life tools for MyBB administrators and moderators.

## Features

- **ModCP Recent Posts:** a paginated review feed with configurable user/group exclusions and links to posts, profiles, and warning tools.
- **AdminCP View Members:** shortcuts on the user-group list that open MyBB's existing user search filtered to the selected group.

## Installation

Requires MyBB 1.8.x and PHP 7.4 or newer.

1. Copy `inc/plugins/staff_tools.php` to the same path under your forum root.
2. Open **AdminCP → Configuration → Plugins**.
3. Install and activate **Staff Tools**.
4. Review **Configuration → Settings → Staff Tools**.

Test this initial release on staging before production use. Uninstalling removes only this plugin's settings and template.

## License

MIT
