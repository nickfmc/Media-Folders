=== Apex Folders – Media Library Folders with AI Auto-Organize, 100% Free ===
Contributors: nickfmc
Tags: media library, folders, ai, media folders, organize media
Requires at least: 5.3
Tested up to: 7.1
Stable tag: 1.2.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Media Library folders with drag and drop, subfolders, upload-to-folder and one-click AI auto-organize via WordPress's own AI connectors. 100% free.

== Description ==

Apex Folders adds folders to the WordPress Media Library so you can find files in seconds instead of scrolling through thousands of them. Drag files into folders, upload straight into a folder, filter by folder inside the editor's media picker, and when the pile is too big to sort by hand, **let AI sort it for you in one click**.

**Everything is free.** There is no Pro version, no locked features and no upgrade nags. Folders, subfolders, drag and drop, bulk moves and AI auto-organize are all included, with no limits on how many folders or files you organize.

### One click, and your library is in folders

Apex Folders is the first Media Library folder plugin built on the **AI connectors that ship with WordPress 7.0**. Connect OpenAI, Anthropic or Google once under Settings → Connectors, then click **Auto-organize with AI** in the Media Library sidebar:

1. **It reads your files.** Filenames, titles, alt text, captions, what each file is attached to, and a small thumbnail of every image, so even `IMG_4821.jpg` ends up in the right place.
2. **It proposes a plan.** Files are sorted into the folders you already have, and new folders are suggested only where a clear group of files needs one.
3. **You stay in charge.** Review the plan with thumbnails, skip any group, type a change like "put every PDF in Documents" and re-plan, then apply. One click undoes the whole thing.
4. **You know the cost first.** A token and cost estimate appears before anything runs, and the real usage and model show live while it works. Your provider bills you directly; Apex Folders adds nothing.

No account, no API key pasted into the plugin, nothing sent anywhere until you click. It works with whichever provider WordPress is connected to, today and as new ones are added.

### Built for AI agents too

Apex Folders registers its folder operations with the WordPress Abilities API. Claude, ChatGPT, or any MCP client connected to your site can list folders, find unassigned files, create folders and move files, so "tidy up my media library" can be an instruction to your assistant.

### Features

* **Auto-organize with AI**: one click, a reviewable plan, an adjustment box, a cost estimate, and undo. Uses the provider connected in WordPress 7.0's Settings → Connectors
* **Works with AI agents**: folder abilities through the WordPress Abilities API, so an AI assistant or MCP client connected to your site can organize the library
* **Folders and subfolders** in a sidebar next to your Media Library, with live file counts
* **Drag and drop**: drop any file onto a folder to move it
* **Bulk moves**: use the Media Library's own Bulk select, then drag the whole selection at once
* **Upload to a folder**: pick the folder in the uploader, or just upload while viewing a folder
* **Folder filter in the editor**: filter by folder in the media modal used by the block editor and classic editor
* **Works in grid and list view**, and with the folder shown in each file's details panel
* **Unassigned folder**: anything without a folder is kept together, never lost
* **Nothing moves on disk**: folders are virtual, so file URLs never change and nothing breaks
* **Translation ready** and built on WordPress's own taxonomy system, with no extra database tables

### Why Apex Folders instead of a "freemium" folder plugin?

Most Media Library folder plugins give you a taste for free and charge for subfolders, bulk moves or removing the folder limit. Apex Folders is the whole tool. It is made by a web agency that needed it on its own client sites, and it is free as a thank-you to the WordPress community.

### Part of Apex

Apex Folders is part of [Apex by Mountain Air Web](https://apex.mountainairweb.com/): a family of small, focused tools for web agencies, including Apex Proof for client feedback, Apex Launch for site checks and Apex Scout for sizing up a prospect's site.

== Installation ==

1. Upload the `apex-folders` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to your Media Library to start creating folders and organizing your media files
4. To access plugin settings, go to Settings > Media and find the "Apex Folders Settings" section

== Frequently Asked Questions ==

= Does this plugin change my file structure on the server? =

No, Apex Folders organizes your media files virtually using WordPress taxonomies. Your actual file structure on the server remains unchanged, and no files are duplicated.

= Is there a Pro version? =

No. Every feature is in this free plugin, and it will stay that way.

= Can I move multiple files at once? =

Yes. Click Bulk select in the Media Library, select the files, then drag any one of them onto a folder.

= Can I upload straight into a folder? =

Yes. Open a folder and upload, or pick a folder in the uploader's "Upload to folder" dropdown. Every file in the batch goes to that folder.

= How does Auto-organize with AI work? =

On WordPress 7.0 or newer, connect a provider (OpenAI, Anthropic or Google) under Settings → Connectors. Then click Auto-organize with AI in the Media Library sidebar. The plugin sends each file's name, title, alt text, caption and a small thumbnail to that provider and shows you the proposed folders before anything changes. You can type an adjustment and re-plan, skip groups, apply, and undo. Nothing is sent anywhere until you click, and the plugin never sees your API key.

= What does the AI feature cost? =

Your AI provider bills you for the tokens used; Apex Folders itself is free. The dialog shows an estimate before you start and the real usage as it runs. A 300px thumbnail per image keeps it small: a few hundred files typically costs cents.

= Does this work with the block editor (Gutenberg)? =

Yes, Apex Folders is fully compatible with the block editor and its media selection interface.

= What happens if I deactivate or uninstall the plugin? =

If you deactivate the plugin, your folders will be hidden but the data is preserved. If you choose to uninstall the plugin, you have the option to remove all folder data or keep it in case you reinstall the plugin later.

= Can I limit which user roles can manage folders? =

By default, users who can upload media can also manage folders. The folder functionality respects WordPress's built-in permission system for media management.

= Does this plugin work with other media library enhancer plugins? =

Apex Folders is designed to be compatible with most media library plugins. However, conflicts may occur with other plugins that modify the media library's core functionality. If you encounter issues, please contact us for support.

== Screenshots ==

1. Folders in the Media Library sidebar, with live counts
2. Upload straight into a folder
3. Filter by folder in the editor's media picker
4. Auto-organize with AI: review the proposed folders, ask for changes, then apply

== Changelog ==

= 1.2.0 =
* New: Auto-organize with AI. One click asks the provider connected in WordPress 7.0's Settings → Connectors for a folder plan, with a cost estimate, a box to ask for changes, review before apply, and undo.
* New: folder abilities for the WordPress Abilities API (list folders, get unassigned files, create folder, move files), so AI agents and MCP clients can organize the library.
* Folder creation and moves share one code path across the sidebar, drag and drop, the AI and the abilities.

= 1.1.2 =
* Parent folders now show their subfolders' files too, in the Media Library and in the editor's media picker, and their counts include subfolders.
* Parent folders can be collapsed. The folder you are viewing always starts open, and your choice is remembered.
* Hovering a collapsed folder while dragging opens it, so you can drop onto a subfolder.

= 1.1.1 =
* New look: the folder sidebar, uploader and filters follow the Apex design, with folder icons, count badges and hover actions.
* Bulk moves use WordPress's own Bulk select: select files, then drag any of them onto a folder. The separate "Bulk Drag and Drop" button is gone.
* Fixed: the folder filter was hidden in the block editor's media modal.
* Fixed: the sidebar could flash unstyled for a moment in list view.

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

= 1.0.0 =
* Initial public release

== Upgrade Notice ==

= 1.2.0 =
Auto-organize your library with the AI provider connected to WordPress 7.0, and let AI agents manage folders through the Abilities API.

= 1.1.2 =
Parent folders include their subfolders' files and counts, and can be collapsed.

= 1.1.1 =
Fresh Apex design, simpler bulk moves and a fix for the folder filter in the block editor.

= 1.1.0 =
Fixes multi-file uploads, drag and drop, and folder counts. Recommended for all users.

= 1.0.0 =
Initial release of Apex Folders. Start organizing your media library with folders!

== Additional Information ==

= Folder Hierarchy =

Create as many folders as you like, each with its own subfolders. Keeping the hierarchy to one level of subfolders keeps the sidebar quick to scan.

= Admin Tools =

Administrators have access to helpful tools for managing folders, including:
* Rebuild Unassigned Folder - ensures all media has proper folder assignments
* Flush Term Cache - resolves issues with folder counts
* Debug Tools - helps troubleshoot folder-related issues

= Performance Considerations =

The plugin is optimized for performance but managing folders with thousands of files may experience slight delays. We recommend using a good caching solution for sites with very large media libraries.