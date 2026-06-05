# DIG-YOURLS-linklist

A YOURLS plugin and public page for managing and displaying a curated list of short links.

## Features
- Per-link status control (active, archived, trashed) via admin backend
- Display toggle to show/hide individual links on the public page
- Public page showing active links with full short URL, copy button, and pagination
- Styled footer bar
- No core YOURLS files modified

## Files
- `user/plugins/linkstats/plugin.php` — Link Status Manager plugin
- `user/plugins/linkstats/uninstall.php` — Cleanup script on deactivation
- `user/pages/linkslist.inc.php` — Public-facing links page

## Installation
1. Copy `user/plugins/linkstats/` into your YOURLS `user/plugins/` directory
2. Copy `user/pages/linkslist.inc.php` into your YOURLS `user/pages/` directory
3. Update your site root `index.php` to include `linkslist.inc.php`
4. Activate the **Link Status Manager** plugin in the YOURLS admin
5. The plugin will create the required database table automatically on activation

## Requirements
- YOURLS 1.7.3 or higher
- PHP 8.0 or higher
- MySQL 5.7 or higher

## License
MIT
