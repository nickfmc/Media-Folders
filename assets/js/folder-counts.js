/**
 * Apex Folders - Folder Counts Script
 * Keeps the folder counts in the media library sidebar up to date
 */
jQuery(document).ready(function($) {
    var l10n = window.apexFoldersL10n || {};

    // Pending refresh, so several changes in a row cause a single request
    var refreshTimer = null;

    /**
     * Build the sidebar label for a folder
     * Parent folders with subfolders also show the total including subfolders
     */
    function getFolderLabel($item, counts) {
        var folderId = $item.data('folder-id');
        var folderData = counts[folderId];
        var label = folderData.name + ' (' + folderData.count + ')';
        var $children = $('.apex-folder-list li.child-folder[data-parent-id="' + folderId + '"]');

        if ($children.length) {
            var totalCount = folderData.count;

            $children.each(function() {
                var childData = counts[$(this).data('folder-id')];
                if (childData) {
                    totalCount += childData.count;
                }
            });

            label += ' - ' + (l10n.total || 'total') + ' (' + totalCount + ')';
        }

        return label;
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

                // Update all folder counts in the sidebar
                $('.apex-folder-list li[data-folder-id]').each(function() {
                    var $item = $(this);

                    if (!response.data[$item.data('folder-id')]) {
                        return;
                    }

                    var label = getFolderLabel($item, response.data);
                    var previousLabel = $item.data('apex-folder-label');

                    $item.children('a').text(label);
                    $item.data('apex-folder-label', label);

                    // Only highlight counts that changed since the last update
                    if (!skipHighlight && previousLabel && previousLabel !== label) {
                        $item.addClass('count-updated');
                        setTimeout(function() {
                            $item.removeClass('count-updated');
                        }, 2000);
                    }
                });

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
