/**
 * Apex Folders - Attachment Tracking Script
 * Handles tracking attachment changes and updates folder views
 */
jQuery(document).ready(function($) {
    // Initialize a tracker for our current folder
    var currentFolder = apexFolderData.currentFolder;
    
    // Track all attachment edits globally
    var attachmentEditTracking = {};
    
    // Handle attachment fields save
    $(document).on('click', '.attachment-save-submit', function() {
        var $this = $(this);
        var $form = $this.closest('form');
        var attachmentId = $form.find('input[name="attachment_id"]').val();
        var $folderSelect = $form.find('select[name^="attachments"][name$="[media_folder]"]');
        var selectedFolderId = $folderSelect.val();
        var originalValue = $folderSelect.find('option:selected').data('original-value');
        
        // Store in global tracking object instead of on the button
        if (attachmentId) {
            attachmentEditTracking[attachmentId] = {
                id: attachmentId,
                newFolderId: selectedFolderId,
                originalFolder: originalValue
            };
            
            console.log('Tracking attachment move:', attachmentEditTracking[attachmentId]);
        }
    });
    
    // Add data attribute to track original folder
    $(document).on('focus', 'select[name^="attachments"][name$="[media_folder]"]', function() {
        var $select = $(this);
        // Only set original value once when first focused
        if (!$select.data('original-tracked')) {
            $select.find('option:selected').attr('data-original-value', $select.val());
            $select.data('original-tracked', true);
        }
    });
    
    // Listen for successful attachment updates
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Check if this is a save-attachment AJAX request
        if (settings.data && settings.data.indexOf('action=save-attachment') !== -1) {
            // Extract attachment ID from the AJAX request
            var matches = settings.data.match(/attachment_id=(\d+)/i);
            var attachmentId = matches ? matches[1] : null;
            
            console.log('AJAX Complete - Attachment ID from URL:', attachmentId);
            
            if (!attachmentId || !attachmentEditTracking[attachmentId]) {
                console.log('No tracking data found');
                return;
            }
            
            var trackingData = attachmentEditTracking[attachmentId];
            
            // Get the term slug for the selected folder
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_folder_slug',
                    folder_id: trackingData.newFolderId,
                    nonce: apexFolderData.slugNonce
                },
                success: function(response) {
                    console.log('Folder slug response:', response);
                    
                    if (response.success && response.data && response.data.slug) {
                        var newFolderSlug = response.data.slug;
                        
                        console.log('New folder slug:', newFolderSlug, 'Current folder:', currentFolder);
                        
                        // If we're viewing a specific folder
                        if (currentFolder) {
                            if (newFolderSlug !== currentFolder) {
                                // CASE 1: File moved OUT OF current folder - remove it
                                console.log('Removing attachment from view:', attachmentId);
                                
                                // APPROACH 1: Force immediate refresh of the current view
                                if (wp.media && wp.media.frame) {
                                    try {
                                        // Try to refresh the media library directly
                                        if (wp.media.frame.content.get() && wp.media.frame.content.get().collection) {
                                            // Force collection refresh by changing a dummy property and refreshing
                                            wp.media.frame.content.get().collection.props.set({
                                                '__refresh': new Date().getTime()
                                            });
                                            wp.media.frame.content.get().collection.reset(wp.media.frame.content.get().collection.models);
                                        }
                                    } catch(e) {
                                        console.error('Error refreshing collection:', e);
                                    }
                                }
                                
                                // APPROACH 2: Direct DOM manipulation with robust selectors
                                setTimeout(function() {
                                    var $items = $('#post-' + attachmentId + ', ' + 
                                                'tr#post-' + attachmentId + ', ' +
                                                'li.attachment[data-id="' + attachmentId + '"], ' +
                                                'div.attachment[data-id="' + attachmentId + '"], ' +
                                                'tr.attachment[data-id="' + attachmentId + '"]');
                                                
                                    console.log('Found ' + $items.length + ' DOM elements to remove');
                                    
                                    $items.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                    
                                    // APPROACH 3: Last resort direct page refresh
                                    setTimeout(function() {
                                        // If we still see the attachment, force page reload
                                        var stillExists = $('#post-' + attachmentId + ', ' + 
                                                        'li.attachment[data-id="' + attachmentId + '"], ' +
                                                        'div.attachment[data-id="' + attachmentId + '"]').length > 0;
                                        
                                        if (stillExists) {
                                            console.log('Item still in DOM, reloading page');
                                            window.location.reload();
                                        }
                                    }, 1000);
                                }, 300);
                                
                                // Update folder counts
                                updateFolderCounts();
                            } else {
                                // Just update counts if file is in the same folder
                                updateFolderCounts();
                            }
                        } else {
                            // We're on the "All Files" view
                            updateFolderCounts();
                        }
                        
                        // Clean up tracking
                        delete attachmentEditTracking[attachmentId];
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error getting folder slug:', error);
                }
            });
        }
    });
});