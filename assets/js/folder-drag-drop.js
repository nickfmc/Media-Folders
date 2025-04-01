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
        currentFolderId: null,
        bulkModeActive: false
    };

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
        
        // Add bulk selection support
        addBulkSelectionSupport();
        
        // Listen for media library refreshes
        listenForLibraryRefresh();
    }

    /**
     * Get current folder information
     */
    function getCurrentFolderInfo() {
        // Get the current folder from URL or data attributes
        var urlParams = new URLSearchParams(window.location.search);
        MediaFolderDragDrop.currentFolderSlug = urlParams.get('media_folder') || '';
        
        // Find the current folder ID from the active folder item
        if (MediaFolderDragDrop.currentFolderSlug) {
            var $currentFolder = $('.media-folder-list li.current');
            if ($currentFolder.length) {
                MediaFolderDragDrop.currentFolderId = $currentFolder.data('folder-id');
            }
        }
        
        console.log('Current folder:', {
            slug: MediaFolderDragDrop.currentFolderSlug,
            id: MediaFolderDragDrop.currentFolderId
        });
    }

    /**
     * Make attachments draggable
     */
    function initDraggableAttachments() {
        // Use event delegation for attachments (they can be loaded dynamically)
        $(document).on('mousedown', '.attachments-browser .attachment:not(.ui-draggable)', function() {
            var $attachment = $(this);
            
            // Check if we're in grid view
            if (!$attachment.closest('.attachments-browser').length) {
                return;
            }
            
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
            
            // Trigger the draggable on this element if not already done
            if (!$attachment.data('ui-draggable')) {
                $attachment.trigger('mousedown');
            }
        });
    }

    /**
     * Handle drag start for an attachment
     */
    function handleDragStart(event, ui, $attachment) {
        MediaFolderDragDrop.isDragging = true;
        
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
        
        // Check if we're in selection mode - if so, collect all selected items
        if ($('body').hasClass('selecting-mode')) {
            var $selectedItems = $('.attachments-browser .attachment.selected');
            if ($selectedItems.length > 1) {
                MediaFolderDragDrop.draggedItems = [];
                $selectedItems.each(function() {
                    MediaFolderDragDrop.draggedItems.push($(this).data('id'));
                });
                
                // Update helper text to show multiple items
                showDragHelper('Moving ' + MediaFolderDragDrop.draggedItems.length + ' items');
            } else {
                // Single item
                MediaFolderDragDrop.draggedItems = [$attachment.data('id')];
                
                // Get attachment title
                var title = $attachment.find('.attachment-preview').attr('title') || 'item';
                showDragHelper('Moving: ' + title);
            }
        } else {
            // Single item drag mode
            MediaFolderDragDrop.draggedItems = [$attachment.data('id')];
            
            // Get attachment title
            var title = $attachment.find('.attachment-preview').attr('title') || 'item';
            showDragHelper('Moving: ' + title);
        }
        
        // Show all droppable folders
        $('.media-folder-list li.droppable').addClass('active-drag');
    }

    /**
     * Handle drag stop
     */
    function handleDragStop(event, ui, $attachment) {
        MediaFolderDragDrop.isDragging = false;
        
        // Remove dragging class
        $attachment.removeClass('is-dragging');
        
        // Hide drag helper
        hideDragHelper();
        
        // Reset droppable folders
        $('.media-folder-list li.droppable').removeClass('active-drag drag-over');
    }

    /**
     * Create and show the drag helper element
     */
    function createDragHelper() {
        // Create the helper if it doesn't exist
        if (!$('.media-folder-drag-helper').length) {
            $('body').append('<div class="media-folder-drag-helper" style="display:none;"></div>');
        }
        
        // Store reference to the helper
        MediaFolderDragDrop.dragHelper = $('.media-folder-drag-helper');
    }

    /**
     * Show the drag helper with text
     */
    function showDragHelper(text) {
        if (MediaFolderDragDrop.dragHelper) {
            MediaFolderDragDrop.dragHelper.text(text).show();
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
            MediaFolderDragDrop.dragHelper.css({
                'left': event.pageX + 15,
                'top': event.pageY + 15
            });
        }
    }

    /**
     * Make folders droppable
     */
    function initDroppableFolders() {
        // Add droppable class to all folders except the current folder
        $('.media-folder-list li').each(function() {
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
        var targetFolderName = $folder.find('a').text().split('(')[0].trim();
        
        // Remove highlight
        $folder.removeClass('drag-over');
        
        // Show processing animation
        $folder.addClass('folder-updating');
        
        // Show a brief notification
        var itemCount = MediaFolderDragDrop.draggedItems.length;
        var message = 'Moving ' + itemCount + ' ' + 
                      (itemCount === 1 ? 'item' : 'items') + 
                      ' to "' + targetFolderName + '"';
        
        // Create a notification
        var $notice = $('<div class="notice notice-info is-dismissible" style="position:fixed; top:32px; right:20px; z-index:9999; width:300px;">' +
            '<p>' + message + ' <span class="spinner is-active" style="float:none;margin:0 0 0 5px;"></span></p>' +
            '</div>');
        
        $('body').append($notice);
        
        // Call AJAX to update folder assignments
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'media_folder_move_items',
                attachment_ids: MediaFolderDragDrop.draggedItems,
                folder_id: targetFolderId,
                nonce: mediaFolderSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success animation on folder
                    $folder.removeClass('folder-updating').addClass('folder-updated');
                    setTimeout(function() {
                        $folder.removeClass('folder-updated');
                    }, 2000);
                    
                    // If we're in a folder view, remove the moved items from view
                    if (MediaFolderDragDrop.currentFolderSlug) {
                        MediaFolderDragDrop.draggedItems.forEach(function(id) {
                            var $item = $('.attachment[data-id="' + id + '"]');
                            $item.fadeOut(300, function() {
                                $(this).remove();
                            });
                        });
                    }
                    
                    // Update folder counts
                    if (typeof window.updateFolderCounts === 'function') {
                        window.updateFolderCounts();
                    }
                    
                    // Update notice
                    $notice.find('p').html('Successfully moved files to "' + targetFolderName + '"');
                    $notice.removeClass('notice-info').addClass('notice-success');
                    $notice.find('.spinner').remove();
                    
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
                    $notice.find('p').text('Error: ' + (response.data ? response.data.message : 'Could not move files'));
                    $notice.find('.spinner').remove();
                }
            },
            error: function() {
                // Handle network error
                $folder.removeClass('folder-updating');
                $notice.removeClass('notice-info').addClass('notice-error');
                $notice.find('p').text('Network error: Could not connect to server');
                $notice.find('.spinner').remove();
            }
        });
    }

    /**
     * Add support for bulk selection mode
     */
    function addBulkSelectionSupport() {
        // Add a custom selection mode toggle button if it doesn't exist
        if (!$('.media-folder-bulk-select').length) {
            $('.media-toolbar-secondary').prepend(
                '<button type="button" class="button select-mode-toggle-button media-folder-bulk-select">' +
                '<span class="screen-reader-text">Select multiple files for organization</span>' +
                'Select Files to Move</button>'
            );
        }
        
        // Handle bulk selection button click
        $(document).on('click', '.media-folder-bulk-select', function(e) {
            e.preventDefault();
            
            // Toggle media frame selection mode
            if (wp.media.frame) {
                if ($('body').hasClass('selecting-mode')) {
                    // Turn off selection mode
                    wp.media.frame.trigger('escape');
                    $(this).text('Select Files to Move');
                    MediaFolderDragDrop.bulkModeActive = false;
                } else {
                    // Turn on selection mode
                    $('body').addClass('selecting-mode');
                    $(this).text('Exit Selection Mode');
                    MediaFolderDragDrop.bulkModeActive = true;
                }
            }
        });
        
        // Listen for escape key to exit selection mode
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && MediaFolderDragDrop.bulkModeActive) {
                $('.media-folder-bulk-select').text('Select Files to Move');
                MediaFolderDragDrop.bulkModeActive = false;
            }
        });
    }

    /**
     * Listen for media library refreshes to reinitialize drag and drop
     */
    function listenForLibraryRefresh() {
        // When attachments are appended, make them draggable
        $(document).on('DOMNodeInserted', '.attachments-browser .attachments .attachment', function() {
            var $attachment = $(this);
            
            // Check if this attachment is already draggable
            if (!$attachment.hasClass('ui-draggable')) {
                setTimeout(function() {
                    $attachment.trigger('mousedown');
                }, 500);
            }
        });
        
        // Listen for view changes
        $(document).on('click', '.media-frame-router .media-router .media-menu-item', function() {
            // Re-initialize after a short delay
            setTimeout(initDragDrop, 500);
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