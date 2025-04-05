/**
 * Apex Folders - Folder Counts Script
 * Handles updating folder counts and refreshing the media library
 */
jQuery(document).ready(function($) {
    // Global function to handle visual updates when folder assignments change
    window.updateFolderCounts = function() {
        
        // Make an AJAX request to get updated folder information
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'theme_get_folder_counts',
                nonce: apexFolderData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                  
                    // Create a mapping of parent folders to their children
                    var folderChildren = {};
                    
                    // First pass - identify parent-child relationships
                    jQuery('.apex-folder-list li.child-folder').each(function() {
                        var $child = jQuery(this);
                        var childId = $child.data('folder-id');
                        var parentId = $child.data('parent-id');
                        
                        if (parentId) {
                            if (!folderChildren[parentId]) {
                                folderChildren[parentId] = [];
                            }
                            folderChildren[parentId].push(childId);
                        }
                    });
                    
                    // Update all folder counts in the sidebar
                    jQuery('.apex-folder-list li').each(function() {
                        var $this = jQuery(this);
                        var folderId = $this.data('folder-id');
                        
                        if (folderId && response.data[folderId]) {
                            var folderData = response.data[folderId];
                            var $link = $this.find('a');
                            var linkText = $link.text();
                            var isParentWithChildren = $this.hasClass('parent-folder') && 
                                                    folderChildren[folderId] && 
                                                    folderChildren[folderId].length > 0;
                            
                            // Calculate total count for parent folders with children
                            if (isParentWithChildren) {
                                var totalCount = folderData.count;
                                // Add counts from all children
                                jQuery.each(folderChildren[folderId], function(_, childId) {
                                    if (response.data[childId]) {
                                        totalCount += response.data[childId].count;
                                    }
                                });
                                
                                // Replace the count portion with both counts
                                var newText = linkText.replace(/\(\d+( \/ \d+ total)?\)$/, '(' + folderData.count + ' / ' + totalCount + ' total)');
                                $link.text(newText);
                            } else {
                                // Regular folders - just update the count
                                var newText = linkText.replace(/\(\d+\)$/, '(' + folderData.count + ')');
                                $link.text(newText);
                            }
                            
                            // Briefly highlight updated counts
                            if (linkText !== $link.text()) {
                                $this.addClass('count-updated');
                                setTimeout(function() {
                                    $this.removeClass('count-updated');
                                }, 2000);
                            }
                        }
                    });
                    
                    // Check if we need to update the current view
                    // If we're in the "All Files" view, we should refresh the page after a delay
                    if (!apexFolderData.currentFolder) {
                        // Only show the refresh notification if we're actually viewing the media library grid
                        if (jQuery('.wp-list-table.media').length) {
                            // Create visual notification
                            var $notice = jQuery('<div class="notice notice-info is-dismissible" style="position:fixed; top:32px; right:20px; z-index:9999; width:300px;">' +
                                '<p>Media has been reorganized. <a href="#" class="reload-page">Refresh</a> to update the view.</p>' +
                                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                                '</div>');
                            
                            jQuery('body').append($notice);
                            
                            // Handle refresh link click
                            $notice.find('.reload-page').on('click', function(e) {
                                e.preventDefault();
                                location.reload();
                            });
                            
                            // Handle dismiss button
                            $notice.find('.notice-dismiss').on('click', function() {
                                $notice.fadeOut(300, function() { jQuery(this).remove(); });
                            });
                            
                            // Auto-dismiss after 10 seconds
                            setTimeout(function() {
                                $notice.fadeOut(300, function() { jQuery(this).remove(); });
                            }, 10000);
                        }
                    }
                }
            }
        });
    };

    // Handle media library refreshes
    function refreshMediaLibrary() {
        // Refresh grid view
        if ($('.wp-list-table.media').length) {
            // List view
            location.reload();
        } else {
            // Grid view
            if (wp.media.frame && wp.media.frame.library && wp.media.frame.library.props) {
                try {
                    wp.media.frame.library.props.set({ignore: (+ new Date())});
                    // Remove the trigger call that's causing the error
                    // wp.media.frame.library.props.trigger('change');
                } catch(e) {
                   // Silently fail - media refresh is non-critical
                }
            }
            
            // Trigger scroll to refresh lazy-loaded images
            $('.attachments-browser .attachments').trigger('scroll');
        }
    }

    // Listen for attachment updates
    $(document).on('change', 'select[name^="attachments"][name$="[apex_folder]"]', function() {
        var $select = $(this);
        var newValue = $select.val();
        var originalValue = $select.find('option:selected').data('original-value');
        
        if (newValue !== originalValue) {
            // Schedule a refresh after the save completes
            setTimeout(function() {
                refreshMediaLibrary();
            }, 500);
        }
    });

    // Hook into the media frame to catch saves
    if (wp.media && wp.media.frame) {
        wp.media.frame.on('edit:attachment', function() {
            setTimeout(function() {
                refreshMediaLibrary();
            }, 500);
        });
    }
    
    // Also refresh when closing the edit modal
    $(document).on('click', '.media-modal-close, .media-modal-backdrop', function() {
        setTimeout(function() {
            refreshMediaLibrary();
        }, 500);
    });
});