/**
 * Media Folders - Drag and Drop Functionality
 *
 * Enables drag and drop file organization in the WordPress media library.
 */

(function($) {
    'use strict';

    // Store state variables
    var MediaFolderDragDrop = {
        isDragging: false,
        draggedItems: [],
        dragHelper: null,
        currentFolderSlug: null,
        currentFolderId: null
    };

    // Translated strings from PHP
    var i18n = (window.apexfoMediaFolderSettings && apexfoMediaFolderSettings.i18n) || {};

    /**
     * Fill the placeholders of a translated string, in order
     */
    function format(text) {
        var values = Array.prototype.slice.call(arguments, 1);
        var index = 0;

        return String(text).replace(/%(\d+\$)?[ds]/g, function(match, position) {
            var value = position ? values[parseInt(position, 10) - 1] : values[index++];
            return typeof value === 'undefined' ? match : value;
        });
    }

    /**
     * Initialize drag and drop functionality
     */
    function initDragDrop() {
        // Get current folder information
        getCurrentFolderInfo();

        // Make attachments draggable
        initDraggableAttachments();

        // Make folders droppable
        initDroppableFolders();

        // Create the drag helper element
        createDragHelper();
    }

    /**
     * Get current folder information
     */
    function getCurrentFolderInfo() {
        // Get the current folder from URL or data attributes
        var urlParams = new URLSearchParams(window.location.search);
        MediaFolderDragDrop.currentFolderSlug = urlParams.get('apex_folder') || '';

        // Find the current folder ID from the active folder item
        if (MediaFolderDragDrop.currentFolderSlug) {
            var $currentFolder = $('.apex-folder-list li.current');
            if ($currentFolder.length) {
                MediaFolderDragDrop.currentFolderId = $currentFolder.data('folder-id');
            }
        }
    }

    /**
     * Make attachments draggable
     */
    function initDraggableAttachments() {
        // Use event delegation for attachments (they can be loaded dynamically).
        // Set up on mouseenter so the attachment is already draggable by the
        // time the mouse button goes down on it.
        $(document).on('mouseenter', '.attachments-browser .attachment:not(.ui-draggable)', function() {
            var $attachment = $(this);

            // Initialize draggable on this attachment
            $attachment.draggable({
                helper: 'clone',
                appendTo: 'body',
                cursor: 'grabbing',
                cursorAt: { top: 25, left: 25 },
                distance: 10,
                revert: 'invalid',
                revertDuration: 200,
                zIndex: 999999,
                containment: 'window',
                start: function(event, ui) {
                    handleDragStart(event, ui, $attachment);
                },
                stop: function(event, ui) {
                    handleDragStop(event, ui, $attachment);
                },
                drag: function(event, ui) {
                    updateDragHelper(event, ui);
                }
            });
        });
    }

    /**
     * Handle drag start for an attachment
     */
    function handleDragStart(event, ui, $attachment) {
        MediaFolderDragDrop.isDragging = true;

        // Add dragging class to body to prevent text selection
        $('body').addClass('apex-folders-dragging');
        // Add dragging class to the original element
        $attachment.addClass('is-dragging');

        // Style the helper
        ui.helper.css({
            'width': '50px',
            'height': '50px',
            'z-index': 999999,
            'opacity': 0.8,
            'transform': 'scale(0.8)',
            'box-shadow': '0 5px 10px rgba(0,0,0,0.2)',
            'border-radius': '3px'
        });

        var title = $attachment.find('.attachment-preview').attr('title') || i18n.item;

        // Dragging one of the files selected with WordPress's Bulk select moves the whole selection
        var $selectedItems = $attachment.hasClass('selected') ? $('.attachments-browser .attachment.selected') : $();

        if ($selectedItems.length > 1) {
            // Collect IDs of all selected items
            MediaFolderDragDrop.draggedItems = [];
            $selectedItems.each(function() {
                var id = $(this).data('id');
                if (id) {
                    MediaFolderDragDrop.draggedItems.push(id);
                }
            });

            // If the dragged item isn't in the selection, add it
            var draggedId = $attachment.data('id');
            if (draggedId && MediaFolderDragDrop.draggedItems.indexOf(draggedId) === -1) {
                MediaFolderDragDrop.draggedItems.push(draggedId);
            }

            // Update helper text to show multiple items
            showDragHelper(format(i18n.movingCount, MediaFolderDragDrop.draggedItems.length));
        } else {
            // Single item drag mode, or no items selected: just drag the current item
            MediaFolderDragDrop.draggedItems = [$attachment.data('id')];
            showDragHelper(format(i18n.movingOne, title));
        }

        // Show all droppable folders
        $('.apex-folder-list li.droppable').addClass('active-drag');
    }

    /**
     * Handle drag stop
     */
    function handleDragStop(event, ui, $attachment) {
        MediaFolderDragDrop.isDragging = false;
        // Remove the dragging class from body
        $('body').removeClass('apex-folders-dragging');
        // Remove dragging class
        $attachment.removeClass('is-dragging');

        // Hide drag helper
        hideDragHelper();

        // Reset droppable folders
        $('.apex-folder-list li.droppable').removeClass('active-drag drag-over');
    }

    /**
     * Create and show the drag helper element
     */
    function createDragHelper() {
        // Create the helper if it doesn't exist
        if (!$('.apex-folder-drag-helper').length) {
            $('body').append('<div class="apex-folder-drag-helper" style="display:none;"></div>');
        }

        // Store reference to the helper
        MediaFolderDragDrop.dragHelper = $('.apex-folder-drag-helper');
    }

    /**
     * Show the drag helper with text
     */
    function showDragHelper(text) {
        if (MediaFolderDragDrop.dragHelper) {
            MediaFolderDragDrop.dragHelper
                .text(text)
                .css({
                    'position': 'fixed',
                    'padding': '8px 12px',
                    'background': 'rgba(0, 0, 0, 0.8)',
                    'color': 'white',
                    'border-radius': '4px',
                    'font-size': '13px',
                    'box-shadow': '0 2px 8px rgba(0,0,0,0.3)',
                    'z-index': 999999
                })
                .show();
        }
    }

    /**
     * Hide the drag helper
     */
    function hideDragHelper() {
        if (MediaFolderDragDrop.dragHelper) {
            MediaFolderDragDrop.dragHelper.hide();
        }
    }

    /**
     * Update drag helper position during drag
     */
    function updateDragHelper(event, ui) {
        if (MediaFolderDragDrop.dragHelper && MediaFolderDragDrop.dragHelper.is(':visible')) {
            // The helper is position: fixed, so use viewport coordinates
            MediaFolderDragDrop.dragHelper.css({
                'left': event.clientX + 15,
                'top': event.clientY + 15
            });
        }
    }

    /**
     * Make folders droppable
     */
    function initDroppableFolders() {
        // Add droppable class to all folders except the current folder
        $('.apex-folder-list li').each(function() {
            var $folder = $(this);
            var folderId = $folder.data('folder-id');

            // Skip the current folder and non-folder items (like separators)
            if (!folderId || (MediaFolderDragDrop.currentFolderId && folderId === MediaFolderDragDrop.currentFolderId)) {
                return;
            }

            // Add droppable class
            $folder.addClass('droppable');

            // Initialize droppable
            $folder.droppable({
                accept: '.attachment',
                tolerance: 'pointer',
                hoverClass: 'drag-over',
                over: function(event, ui) {
                    // Highlight this folder
                    $folder.addClass('drag-over');
                },
                out: function(event, ui) {
                    // Remove highlight
                    $folder.removeClass('drag-over');
                },
                drop: function(event, ui) {
                    handleDrop(event, ui, $folder);
                }
            });
        });
    }

    /**
     * Handle drop on a folder
     */
    function handleDrop(event, ui, $folder) {
        // Get folder info
        var targetFolderId = $folder.data('folder-id');
        var targetFolderName = ($folder.find('.apexfo-name').first().text() || $folder.find('a').text().split('(')[0]).trim();

        // Remember what is being moved; another drag may start before the request returns
        var movedItems = MediaFolderDragDrop.draggedItems.slice();
        var itemCount = movedItems.length;

        // Remove highlight
        $folder.removeClass('drag-over');

        // Show processing animation
        $folder.addClass('folder-updating');

        // Show a brief notification. The folder name is user input, so it is
        // only ever inserted as text.
        var message = format(itemCount === 1 ? i18n.movingItemTo : i18n.movingItemsTo, itemCount, targetFolderName);
        var $noticeText = $('<span></span>').text(message);
        var $spinner = $('<span class="spinner is-active" style="float:none;margin:0 0 0 5px;"></span>');
        var $notice = $('<div class="notice notice-info is-dismissible" style="position:fixed; top:32px; right:20px; z-index:9999; width:300px;"></div>')
            .append($('<p></p>').append($noticeText, ' ', $spinner));

        $('body').append($notice);

        // Call AJAX to update folder assignments
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'apex_folder_move_items',
                attachment_ids: movedItems,
                folder_id: targetFolderId,
                nonce: apexfoMediaFolderSettings.nonce
            },
            success: function(response) {
                $spinner.remove();

                if (response.success) {
                    // Show success animation on folder
                    $folder.removeClass('folder-updating').addClass('folder-updated');
                    setTimeout(function() {
                        $folder.removeClass('folder-updated');
                    }, 2000);

                    // Tell the media library which folder each file is in now. It
                    // removes the files from a folder view they no longer belong
                    // to and refreshes the folder counts.
                    var processedIds = (response.data && response.data.processed_ids) || movedItems;

                    if (window.wp && wp.media && wp.media.model && wp.media.model.Attachment) {
                        processedIds.forEach(function(id) {
                            wp.media.model.Attachment.get(id).set('apexFolder', parseInt(targetFolderId, 10));
                        });
                    } else if (typeof window.updateFolderCounts === 'function') {
                        window.updateFolderCounts();
                    }

                    // Update notice
                    $noticeText.text(format(i18n.movedTo, targetFolderName));
                    $notice.removeClass('notice-info').addClass('notice-success');

                    // Auto dismiss after 3 seconds
                    setTimeout(function() {
                        $notice.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // Show error
                    $folder.removeClass('folder-updating');
                    $notice.removeClass('notice-info').addClass('notice-error');
                    $noticeText.text(i18n.errorPrefix + ' ' + (response.data && response.data.message ? response.data.message : i18n.couldNotMove));
                }
            },
            error: function() {
                // Handle network error
                $spinner.remove();
                $folder.removeClass('folder-updating');
                $notice.removeClass('notice-info').addClass('notice-error');
                $noticeText.text(i18n.networkError);
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only initialize on media upload pages
        if ($('.wp-list-table.media').length || $('.media-frame').length) {
            initDragDrop();
        }
    });

})(jQuery);
