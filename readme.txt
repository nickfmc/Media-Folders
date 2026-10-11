=== Apex Folders ===
Contributors: nickfmc
Tags: media, folders, attachment, organize, media library
Requires at least: 5.3
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize your WordPress Media Library with folders, making it easy to manage thousands of media files.

== Description ==

Apex Folders is a powerful yet lightweight plugin that adds folder organization to your WordPress Media Library. It allows you to create a hierarchical folder structure to organize your images, videos, documents, and other media files, making them much easier to find and manage.

### Key Features

* **Hierarchical Folders**: Create unlimited folders and subfolders to organize your media
* **Drag & Drop Interface**: Easily move media files between folders with intuitive drag and drop functionality
* **Upload to Folders**: Directly upload new media to specific folders
* **Works with Default Media Library**: Seamlessly integrates with WordPress's built-in media management
* **Automatic Unassigned Folder**: Media without a folder is automatically placed in an "Unassigned" folder
* **No Media Duplication**: Files are organized virtually without duplicating them on your server
* **Lightweight**: Optimized for performance with minimal impact on your site's speed

### Perfect For

* **Content Creators**: Easily find and reuse media assets across your site
* **Agencies & Developers**: Organize client media by project, category or purpose
* **Online Stores**: Keep product images organized by category, season, or brand
* **Large Media Libraries**: Manage thousands of files without the frustration of endless scrolling

### How It Works

Apex Folders creates a custom taxonomy for your media attachments and provides an intuitive user interface for managing them. The plugin adds folder functionality directly to your existing WordPress Media Library interface, making it feel like a natural part of WordPress.

### Part of Apex

Apex Folders is free, and part of [Apex by Mountain Air Web](https://apex.mountainairweb.com/): a family of small, focused tools for web agencies, including Apex Proof for client feedback, Apex Launch for site checks and Apex Scout for sizing up a prospect's site.

== Installation ==

1. Upload the `apex-folders` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to your Media Library to start creating folders and organizing your media files
4. To access plugin settings, go to Settings > Media and find the "Apex Folders Settings" section

== Frequently Asked Questions ==

= Does this plugin change my file structure on the server? =

No, Apex Folders organizes your media files virtually using WordPress taxonomies. Your actual file structure on the server remains unchanged, and no files are duplicated.

= Can I move multiple files at once? =

Yes, you can select multiple files in the Media Library and move them to a folder using the bulk actions menu or by dragging and dropping.

= Does this work with the block editor (Gutenberg)? =

Yes, Apex Folders is fully compatible with the block editor and its media selection interface.

= What happens if I deactivate or uninstall the plugin? =

If you deactivate the plugin, your folders will be hidden but the data is preserved. If you choose to uninstall the plugin, you have the option to remove all folder data or keep it in case you reinstall the plugin later.

= Can I limit which user roles can manage folders? =

By default, users who can upload media can also manage folders. The folder functionality respects WordPress's built-in permission system for media management.

= Does this plugin work with other media library enhancer plugins? =

Apex Folders is designed to be compatible with most media library plugins. However, conflicts may occur with other plugins that modify the media library's core functionality. If you encounter issues, please contact us for support.

== Screenshots ==

1. Media Library with folder organization
2. Creating a new folder
3. Drag and drop interface for moving files
4. Uploading directly to a folder
5. Admin settings panel

== Changelog ==

= 1.1.0 =
* Fixed: uploading several files at once no longer reloads the page after the first file. Files now appear in the folder as they finish uploading.
* Fixed: uploads go to the folder you are viewing by default, and the folder chosen in the uploader is sent with every file.
* Fixed: the upload folder no longer "sticks" to a folder picked earlier on another page.
* Fixed: moving a file from its details panel or by drag and drop removes it from the old folder view and updates the counts.
* Fixed: drag and drop works on the first attempt in current browsers.
* Fixed: folder counts stay accurate without repeated recounts, and the folder filter shows live counts.
* Fixed: the Unassigned folder is found reliably on translated sites and can no longer be deleted or renamed.
* Changed: folders can now have numeric names, such as "2024".
* Security: folder names are always output as text in dialogs and notices.
* Security: removed an upload hook that allowed folder changes without a permission check; debug views now require a nonce.
* All interface text is now translatable.
* New look: the folder sidebar, uploader and filters follow the Apex design, with folder icons, count badges and hover actions.
* Bulk moves now use WordPress's own Bulk select: select files, then drag any of them onto a folder. The separate "Bulk Drag and Drop" button is gone.
* The folder filter in the editor's media modal no longer breaks the toolbar layout.

= 1.0.0 =
* Initial public release

== Upgrade Notice ==

= 1.1.0 =
Fixes multi-file uploads, drag and drop, and folder counts. Recommended for all users.

= 1.0.0 =
Initial release of Apex Folders. Start organizing your media library with folders!

== Additional Information ==

= Folder Hierarchy =

Apex Folders supports unlimited folder nesting, allowing you to create as deep a hierarchy as you need for your media organization.

= Admin Tools =

Administrators have access to helpful tools for managing folders, including:
* Rebuild Unassigned Folder - ensures all media has proper folder assignments
* Flush Term Cache - resolves issues with folder counts
* Debug Tools - helps troubleshoot folder-related issues

= Performance Considerations =

The plugin is optimized for performance but managing folders with thousands of files may experience slight delays. We recommend using a good caching solution for sites with very large media libraries.