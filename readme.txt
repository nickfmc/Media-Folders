=== Apex Folders ===
Contributors: mountainairweb
Tags: media library, folders, media folders, organize media
Requires at least: 5.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Reach the apex of media library organization.

== Description ==

Apex Folders adds folders to the WordPress media library.

* Create, rename and delete folders, with one level of subfolders.
* Choose the folder files are uploaded to. Uploads go to the folder you are viewing by default.
* Move files between folders by drag and drop, in bulk, or from the attachment details panel.
* Filter by folder in the media library and in the media modal used by the editor.
* Files that are not in a folder are kept in an "Unassigned" folder.

Folders are stored as a taxonomy, so files are never moved on disk and their URLs do not change.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from Plugins > Add New > Upload Plugin.
2. Activate the plugin from the Plugins screen.
3. Go to Media > Library to create folders and organize your files.

== Frequently Asked Questions ==

= Does moving a file to a folder change its URL? =

No. Folders only organize the media library; files stay where they are on the server.

= What happens to my files when I delete a folder? =

They are moved to the Unassigned folder. No files are deleted.

= What happens to my folders when I uninstall the plugin? =

By default they are kept. To remove all folder data on uninstall, enable "Remove all folder data when plugin is uninstalled" under Settings > Media.

== Changelog ==

= 0.9.9 =
* Initial release.
