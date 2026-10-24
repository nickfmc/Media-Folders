/**
 * Apex Folders - Folder Counts Script
 * Keeps the folder counts in the media library sidebar up to date
 */
jQuery(document).ready(function($) {
    var l10n = window.apexFoldersL10n || {};

    // Pending refresh, so several changes in a row cause a single request
    var refreshTimer = null;

    /**
     * Count including subfolders, for a parent folder
     */
    function getTotalCount($item, counts) {
        var folderId = $item.data('folder-id');
        var total = counts[folderId].count;
        var hasChildren = false;

        $('.apex-folder-list li.child-folder[data-parent-id="' + folderId + '"]').each(function() {
            var childData = counts[$(this).data('folder-id')];
            hasChildren = true;
            if (childData) {
                total += childData.count;
            }
        });

        return hasChildren ? total : null;
    }

    /**
     * Show a count in a sidebar entry, flashing it if it changed
     */
    function showCount($item, count, title, skipHighlight) {
        var $count = $item.find('.apexfo-count').first();
        var previous = $item.data('apex-folder-count');

        $count.text(count);
        if (title) {
            $count.attr('title', title);
        }
        $item.data('apex-folder-count', count);

        // Only highlight counts that changed since the last update
        if (!skipHighlight && typeof previous !== 'undefined' && previous !== count) {
            $item.addClass('count-updated');
            setTimeout(function() {
                $item.removeClass('count-updated');
            }, 1600);
        }
    }

    /**
     * Main function to update folder counts
     * Uses AJAX to get the latest counts and updates the UI
     *
     * @param {boolean} skipHighlight - Whether to skip highlighting counts that changed
     */
    window.updateFolderCounts = function(skipHighlight) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'apex_folders_get_folder_counts',
                nonce: apexFolderData.nonce
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    return;
                }

                var totalFiles = 0;
                $.each(response.data, function(id, folderData) {
                    totalFiles += folderData.count;
                });

                // Update all folder counts in the sidebar
                $('.apex-folder-list li[data-folder-id]').each(function() {
                    var $item = $(this);
                    var folderData = response.data[$item.data('folder-id')];

                    if (!folderData) {
                        return;
                    }

                    var total = getTotalCount($item, response.data);
                    var title = total === null ? '' : (l10n.countWithSubfolders || '%1$d in this folder, %2$d including subfolders')
                        .replace('%1$d', folderData.count)
                        .replace('%2$d', total);

                    // A parent folder shows everything inside it, subfolders included
                    showCount($item, total === null ? folderData.count : total, title, skipHighlight);
                });

                // Every file is in exactly one folder, so the folder counts add up to the library
                showCount($('.apex-folder-list li.all-files'), totalFiles, '', skipHighlight);

                // Let other folder UI (such as the folder filter dropdown) use the same counts
                $(document).trigger('apex-folders-counts', [response.data]);
            }
        });
    };

    /**
     * Refresh the counts shortly, however many changes arrive in the meantime
     */
    function scheduleCountUpdate() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function() {
            window.updateFolderCounts();
        }, 150);
    }

    // Refresh counts once every file in an upload batch has finished
    if (window.wp && wp.Uploader && wp.Uploader.queue) {
        wp.Uploader.queue.on('reset', scheduleCountUpdate);
    }

    // Refresh counts when files are moved to another folder
    if (window.wp && wp.media && wp.media.model && wp.media.model.Attachments) {
        wp.media.model.Attachments.all.on('change:apexFolder', function(attachment) {
            // Ignore attachments that are only now being loaded or uploaded
            if (!attachment.get('uploading') && typeof attachment.previous('apexFolder') !== 'undefined') {
                scheduleCountUpdate();
            }
        });
    }

    // Initialize folder counts on page load - explicitly skip highlighting
    window.updateFolderCounts(true);
});
