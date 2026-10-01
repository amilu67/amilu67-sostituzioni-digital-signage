=== amilu67 Sostituzioni Digital Signage ===
Contributors: amilu67
Tags: school, teachers, substitutions, timetable, digital signage
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later

Manage teacher timetables, absences, availability and substitutions, with a large-screen digital signage view for schools.

== Description ==

amilu67 Sostituzioni Digital Signage provides a WordPress administration interface for school staff replacement management. Developed by amilu67.

Features include:

* teacher management;
* weekly timetable editing with a table-based editor from Monday to Saturday;
* individual and bulk CSV timetable import;
* teacher availability hours;
* teacher absence registration with automatic generation of lessons that need coverage;
* absent class registration, automatically making the affected teachers available;
* substitute suggestions based on declared availability and teachers freed by absent classes;
* a large-screen digital signage board with automatic refresh;
* isolated styles designed to coexist with the Design Scuole Italia WordPress theme without loading a second copy of Bootstrap Italia.

The administration interface remains in Italian because the plugin is intended for Italian schools. This readme is in English to comply with the WordPress.org plugin directory readme requirements.

== Installation ==

1. Upload the ZIP file from Plugins > Add New Plugin > Upload Plugin.
2. Activate the plugin, or replace the previous version if already installed.
3. Open amilu67 Sostituzioni Digital Signage in the WordPress administration menu.
4. Add teachers and fill in the weekly timetable using the table editor or CSV import.
5. Configure the large-screen display under amilu67 Sostituzioni Digital Signage > Impostazioni schermo.

== Changelog ==

= 1.2.0 =
* Renamed the plugin to `amilu67 Sostituzioni Digital Signage` to make the directory name distinctive.
* Changed the requested WordPress.org slug and Text Domain to `amilu67-sostituzioni-digital-signage`.
* Replaced the previous short internal prefix with the unique `amilu67_sds_` prefix for classes, constants, stored data, actions, menu slugs, shortcode, query vars, asset handles and REST namespace.
* Added automatic migration of pre-release settings and custom-table data to the new prefixed storage.
* Changed the plugin Author field to `amilu67` for a clear, non-affiliating directory identity.

= 1.1.5 =
* Prepared a pre-release package for the initially assigned directory slug.
* Kept compatibility with data created by earlier test builds.

= 1.1.4 =
* Updated plugin author metadata.
* Added standalone signage interface icons for clearer large-screen reading.
* Standalone signage now fills the entire browser viewport automatically.
* Added a fullscreen toggle control for browser fullscreen mode; browser security policies require a user gesture for true fullscreen.
* Improved kiosk-mode sizing with dynamic viewport units.

= 1.1.3 =
* Documented intentional direct access to the plugin's dedicated custom tables for WordPress Coding Standards / Plugin Check.
* Documented intentional no-cache reads for live substitution and signage data, preventing stale operational information.
* No database schema or user data changes.

= 1.1.2 =
* Compatibilità Plugin Check: query SQL convertite al pattern canonico `$wpdb->prepare()` riconosciuto dagli standard WordPress.
* Nessuna modifica allo schema dati o alle funzionalità esistenti.

= 1.1.1 =
* Improved compliance with WordPress Plugin Check.
* Database queries now use prepared identifier placeholders for custom table names.
* Added explicit nonce verification in administrative actions.
* Improved sanitization of request and upload data.
* CSV imports now read files through the WordPress Filesystem API.
* Standalone signage assets are enqueued through the WordPress enqueue APIs.
* Added the Tested up to readme header and updated readme metadata.

= 1.1.0 =
* Renamed the pre-release plugin display name.
* Added the weekly table-based timetable editor.
* Added Monday-Saturday tabs with up to 12 periods per day.
* Added quick controls for copying time ranges and clearing a day.
* Improved responsive administration graphics.
* Recalculates substitutions after manual timetable updates.
* Automatically cleans obsolete substitutions after timetable changes.

= 1.0.2 =
* Recalculates the substitution board when it is opened and after timetable imports.
* Added diagnostics when an absence does not match any lesson.
* Shows the number of generated replacement periods after an absence is recorded.

= 1.0.1 =
* Improved teacher absence history display.
* Added the absent-teacher count to the dashboard.

= 1.0.0 =
* First release.
