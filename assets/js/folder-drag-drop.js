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
        
        // Check if we're in selection mode - if so, collect all selected items
        if (MediaFolderDragDrop.bulkModeActive) {
            var $selectedItems = $('.attachments-browser .attachment.selected');
            
            if ($selectedItems.length > 0) {
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
                showDragHelper('Moving ' + MediaFolderDragDrop.draggedItems.length + ' items');
            } else {
                // No items selected, just drag the current item
                MediaFolderDragDrop.draggedItems = [$attachment.data('id')];
                var title = $attachment.find('.attachment-preview').attr('title') || 'item';
                showDragHelper('Moving: ' + title);
            }
        } else {
            // Single item drag mode
            MediaFolderDragDrop.draggedItems = [$attachment.data('id')];
            var title = $attachment.find('.attachment-preview').attr('title') || 'item';
            showDragHelper('Moving: ' + title);
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
                action: 'apex_folder_move_items',
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
        if (!$('.apex-folder-bulk-select').length) {
            $('.media-toolbar-secondary').prepend(
                '<button type="button" class="button select-mode-toggle-button apex-folder-bulk-select">' +
                '<span class="screen-reader-text">Select multiple files for organization</span>' +
                'Bulk Drag and Drop</button>'
            );
        }

                // Force refresh the view to ensure normal behavior returns
        setTimeout(function() {
            // Trigger a slight scroll to refresh handlers
            $(window).scrollTop($(window).scrollTop() + 1);
        }, 100);
        
        
        
                   // Handle bulk selection button click
                $(document).on('click', '.apex-folder-bulk-select', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
         
                    
                    if ($('body').hasClass('selecting-mode')) {
                        // Turn off selection mode

                        $('body').removeClass('selecting-mode');
                        $(this).text('Bulk Drag and Drop');
                        MediaFolderDragDrop.bulkModeActive = false;
                        
                        // Remove our overlay that prevents clicks
                        $('.apex-folders-selection-overlay').remove();
                        
                        // Remove the floating exit button 
                        $('.apex-folders-exit-selection').remove();
                        
                        // Deselect all items
                        $('.attachment.selected').removeClass('selected');
                        $('.attachment').css('box-shadow', '');
                        $('.selection-indicator').remove();
                        
                        // Force refresh the view to ensure normal behavior returns
                        setTimeout(function() {
                            $(window).scrollTop($(window).scrollTop() + 1);
                        }, 100);
                        
                    } else {
                        // Turn on selection mode
                        $('body').addClass('selecting-mode');
                        // $(this).text('Exit Selection Mode');
                        MediaFolderDragDrop.bulkModeActive = true;
                        
                        // Create overlay FIRST before adding buttons
                        $('<div class="apex-folders-selection-overlay"></div>')
                            .css({
                                'position': 'absolute',
                                'top': 0,
                                'left': 0,
                                'right': 0, 
                                'bottom': 0,
                                'z-index': 99999,
                                'background': 'rgba(0,0,0,0.01)', // Barely visible but ensures clicks are caught
                                'cursor': 'pointer'
                            })
                            .appendTo('.attachments-browser .attachments');
                        
                        // Add a floating Exit button that's always accessible
                        $('<button type="button" class="apex-folders-exit-selection button button-primary">Exit Selection Mode</button>')
                            .css({
                                'position': 'fixed',
                                'bottom': '60px',           
                                'right': '40%',         
                                'z-index': '9999999',    
                                'pointer-events': 'auto',
                                'padding': '5px 15px',   
                                'font-weight': 'bold',   
                                'box-shadow': '0 2px 5px rgba(0,0,0,0.3)'
                            })
                            .appendTo('body')
                            .on('click', function(e) {
                                e.stopPropagation(); 
                                $('.apex-folder-bulk-select').trigger('click');
                                return false;
                            });
                        
                        // Show helper message
                        var $notice = $('<div class="notice notice-info" style="position:fixed; bottom:20px; right:20px; z-index:9999; width:300px;">' +
                            '<p>🔍 <strong>Selection Mode:</strong> Click on items to select multiple files, then drag any selected item to a folder.</p>' +
                            '</div>');
                        $('body').append($notice);
                        setTimeout(function() {
                            $notice.fadeOut(300, function() { $(this).remove(); });
                        }, 5000);
                        
                        // Add selection indicators to each attachment
                        $('.attachment').append('<div class="selection-indicator"></div>');
                    }
                });
        
                 // Handle clicks on the selection overlay
            $(document).on('click', '.apex-folders-selection-overlay', function(e) {
                // Prevent the default WordPress behavior
                e.preventDefault();
                e.stopPropagation();
                
                // Temporarily hide the overlay to find what's underneath
                $(this).hide();
                
                // Find the element at the click position
                var elementBelow = document.elementFromPoint(e.clientX, e.clientY);
                
                // Show the overlay again
                $(this).show();
                
                // Find the attachment that was clicked
                var $attachment = $(elementBelow).closest('.attachment');
                
                if ($attachment.length) {
                    // Toggle the selected class
                    $attachment.toggleClass('selected');
                    
                    // Show visual feedback
                    if ($attachment.hasClass('selected')) {
                        $attachment.css('box-shadow', '0 0 0 3px #0073aa');
                        // Make sure the indicator shows the selected state
                        $attachment.find('.selection-indicator').css('background', '#0073aa');
                    } else {
                        $attachment.css('box-shadow', '');
                        // Reset the indicator
                        $attachment.find('.selection-indicator').css('background', '#f0f0f0');
                    }
                }
                
                return false; // Important to prevent default behavior
            });

                       // Allow dragging to start through the overlay
                $(document).on('mousedown', '.apex-folders-selection-overlay', function(e) {
                    // Temporarily hide the overlay
                    $(this).hide();
                    
                    // Find the element at the click position
                    var elementBelow = document.elementFromPoint(e.clientX, e.clientY);
                    
                    // Find the attachment that was clicked
                    var $attachment = $(elementBelow).closest('.attachment');
                    
                    // Show the overlay again with pointer-events disabled
                    $(this).css('pointer-events', 'none').show();
                    
                    if ($attachment.length && $attachment.hasClass('selected')) {
                        
                        // Create and dispatch a new mousedown event
                        var mouseEvent = new MouseEvent('mousedown', {
                            'bubbles': true,
                            'cancelable': true,
                            'view': window,
                            'clientX': e.clientX,
                            'clientY': e.clientY
                        });
                        
                        $attachment[0].dispatchEvent(mouseEvent);
                        
                        // Set a timeout to restore pointer events
                        setTimeout(function() {
                            $('.apex-folders-selection-overlay').css('pointer-events', 'auto');
                        }, 500); // Longer timeout to ensure drag starts
                    } else {
                        // If not a selected attachment, restore pointer events immediately
                        $(this).css('pointer-events', 'auto');
                    }
                });
        
        // Also enable attachment selection when in selection mode
        $(document).on('click', '.attachments-browser .attachment', function(e) {
            if ($('body').hasClass('selecting-mode')) {
                $(this).toggleClass('selected');
                e.preventDefault();
                e.stopPropagation();
            }
        });
        
        // Listen for escape key to exit selection mode
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && MediaFolderDragDrop.bulkModeActive) {
                $('.apex-folder-bulk-select').trigger('click');
            }
        });
        
        
               $('<style>')
            .text(
                                '.apex-folders-exit-selection { font-weight: bold !important; box-shadow: 0 2px 5px rgba(0,0,0,0.3) !important; }' +
                '.apex-folders-selection-overlay { pointer-events: auto !important; }' +
                                '.apex-folders-dragging { cursor: grabbing !important; }' +
                '.apex-folders-dragging * { user-select: none !important; -webkit-user-select: none !important; }' +
                '.apex-folder-list { z-index: 101 !important; }' + // Make sure folders are above the overlay
                '.selecting-mode .attachment:hover { cursor: pointer !important; }' +
                '.selecting-mode .attachment .check { display: none !important; }' +
                '.selecting-mode .attachment.selected { outline: 3px solid #0073aa !important; background-color: rgba(0, 115, 170, 0.1) !important; }' +
                '.selecting-mode .attachment .selection-indicator { display: block !important; position: absolute; top: 5px; right: 5px; width: 20px; height: 20px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 3px rgba(0,0,0,0.3); background: #f0f0f0; z-index: 100; pointer-events: none; }' +
                '.selecting-mode .attachment.selected .selection-indicator { background: #0073aa !important; }' +
                '.selecting-mode .attachments-browser .attachment .thumbnail::after { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.1); opacity: 0; }' +
                '.selecting-mode .attachments-browser .attachment:hover .thumbnail::after { opacity: 1; }' +
                '.apex-folders-selection-overlay { position: absolute !important; top: 0; left: 0; right: 0; bottom: 0; z-index: 100 !important; background: transparent; cursor: pointer; border: 2px dashed #ccc;}' +
                '.apex-folder-bulk-select { position: relative; z-index: 100001 !important; }' // Ensure button is above overlay
            )
            .appendTo('head');
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