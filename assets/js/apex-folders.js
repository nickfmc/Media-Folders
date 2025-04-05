/**
 * Media Folders Filter for WordPress Block Editor
 */

window.updateFolderCounts = function() {
    console.log('Updating folder counts...');

    // We'll make 3 attempts to update counts, with increasing delays
    // This helps when WordPress is slow to update term counts
    var attempts = [1000, 3000, 6000]; // 1, 3, and 6 second delays

    attempts.forEach(function(delay) {
        setTimeout(function() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'theme_get_folder_counts'
        },
        success: function(response) {
            if (response.success && response.data) {
                console.log('Received updated folder counts after ' + delay + 'ms:', response.data);
                
                // Update each folder count in the sidebar
                jQuery('.apex-folder-list li[data-folder-id]').each(function() {
                    var $item = jQuery(this);
                    var folderId = $item.data('folder-id');
                    
                    if (response.data[folderId]) {
                        var folder = response.data[folderId];
                        var newCount = folder.count;
                        var $link = $item.find('a').first();
                        var currentText = $link.text();
                        
                        // Extract the name part (everything before the last parenthesis)
                        var folderName = currentText.substring(0, currentText.lastIndexOf('(')).trim();
                        var currentCount = currentText.match(/\((\d+)\)$/);
                        currentCount = currentCount ? parseInt(currentCount[1], 10) : 0;
                        
                        // Only update if count changed
                        if (currentCount !== newCount) {
                            console.log('Updating count for ' + folderName + ' from ' + currentCount + ' to ' + newCount);
                            $link.text(folderName + ' (' + newCount + ')');
                            
                            // Add highlighting class
                            $item.addClass('count-updated');
                            setTimeout(function() {
                                $item.removeClass('count-updated');
                            }, 2000);
                        } else {
                            console.log('Count unchanged for ' + folderName + ': ' + currentCount);
                        }
                    }
                });
            }
        }
    });
}, delay);
});
}; 

// IIFE to avoid global scope pollution
(function() {
    // Store the original wp.media.view.AttachmentsBrowser for later use
    var originalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;
    
    console.log('Media folders: Extending WordPress media framework');
    
    // Extend AttachmentsBrowser to include our custom filter
    wp.media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend({
        createToolbar: function() {
            // Call the original method to create the toolbar with default filters
            originalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);
            
            console.log('Creating media toolbar with folder filter');
            
            // Create our custom dropdown filter
            var folderFilter = this.createFolderFilter();
            
            // Add it to the toolbar
            if (folderFilter) {
                this.toolbar.set('folderFilter', folderFilter);
                console.log('Folder filter added to toolbar');
            }
        },
        
        // Create our custom folder filter
        createFolderFilter: function() {
            if (!window.mediaFolders || !window.mediaFolders.length) {
                console.warn('No media folders available');
                return;
            }
            
            console.log('Creating folder filter with folders:', window.mediaFolders);
            
            // Create filter dropdown
            var FolderFilter = wp.media.view.AttachmentFilters.extend({
                id: 'media-attachment-folder-filter',
                className: 'attachment-filters',
                
                createFilters: function() {
                    var filters = {};
                    
                    // Add "All Folders" option
                    filters.all = {
                        text: 'All Folders',
                        props: {
                            apex_folder: ''
                        },
                        priority: 10
                    };
                    
                    // Add each folder as an option
                    window.mediaFolders.forEach(function(folder) {
                        var filterName = 'folder-' + folder.id;
                        filters[filterName] = {
                            text: folder.name + ' (' + folder.count + ')',
                            props: {
                                apex_folder: folder.id
                            },
                            priority: 20
                        };
                    });
                    
                    this.filters = filters;
                }
            });
            
            // Create an instance of our filter
            var filter = new FolderFilter({
                controller: this.controller,
                model: this.collection.props,
                priority: -75
            }).render();
            
            return filter;
        }
    });

    if (wp.media) {
        // Original attachments browser
        var oldMediaFrameSelect = wp.media.view.MediaFrame.Select;
        
        // Extend the original with our custom behavior
        wp.media.view.MediaFrame.Select = oldMediaFrameSelect.extend({
            initialize: function() {
                // Call the original initialize
                oldMediaFrameSelect.prototype.initialize.apply(this, arguments);
                
                // Add our custom sidebar
                this.on('content:render:browse', this.addFolderFilter, this);
            },
            
                      // Find the addFolderFilter function (around line 156) and replace it with this version:
            
                        addFolderFilter: function() {
                // Check if sidebar exists before trying to use it
                if (!this.sidebar) {
                    console.log('Media library sidebar not yet initialized');
                    
                    // Set up a retry mechanism with a limit to prevent infinite loops
                    if (!this._retryCount) {
                        this._retryCount = 0;
                    }
                    
                    // Limit retries to avoid infinite loops
                    if (this._retryCount < 10) {
                        this._retryCount++;
                        
                        // Try again after a short delay
                        var self = this;
                        setTimeout(function() {
                            self.addFolderFilter();
                        }, 500);
                    } else {
                        console.warn('Maximum retry attempts reached for addFolderFilter');
                    }
                    return;
                }
                
                // Reset retry counter if we successfully reach this point
                this._retryCount = 0;
                
                // Create our folder browser/filter component for the sidebar
                var folderBrowser = new wp.media.view.MediaFolderBrowser({
                    controller: this,
                    model: this.state().get('library')
                });
                
                // Add it to the sidebar
                this.sidebar.set('folder', folderBrowser);
            }
        });
        
        // Create custom filter view
        if (!wp.media.view.AttachmentFilters.FolderFilter) {
            wp.media.view.AttachmentFilters.FolderFilter = wp.media.view.AttachmentFilters.extend({
                id: 'media-attachment-folder-filter',
                className: 'attachment-filters attachment-folder-filter',
                
                createFilters: function() {
                    var filters = {};
                    
                    // All Folders option
                    filters.all = {
                        text: 'All Folders',
                        props: {
                            apex_folder: ''
                        },
                        priority: 10
                    };
                    
                    // Get folders from our global variable
                    if (window.mediaFolders) {
                        // Group folders by hierarchy
                        var unassigned = null;
                        var parents = [];
                        var children = {};
                        
                        // Organize folders
                        window.mediaFolders.forEach(function(folder) {
                            // We can check parent property to determine hierarchy
                            if (folder.slug === 'unassigned') {
                                unassigned = folder;
                            } else if (!folder.parent) {
                                parents.push(folder);
                            } else {
                                if (!children[folder.parent]) {
                                    children[folder.parent] = [];
                                }
                                children[folder.parent].push(folder);
                            }
                        });
                        
                        // Add Unassigned first with priority 20
                        if (unassigned) {
                            filters['folder_' + unassigned.id] = {
                                text: unassigned.name,
                                props: {
                                    apex_folder: unassigned.id
                                },
                                priority: 20
                            };
                        }
                        
                        // Add parent folders starting with priority 30
                        var priority = 30;
                        parents.forEach(function(folder) {
                            filters['folder_' + folder.id] = {
                                text: folder.name,
                                props: {
                                    apex_folder: folder.id
                                },
                                priority: priority++
                            };
                            
                            // Add children if any
                            if (children[folder.id]) {
                                children[folder.id].forEach(function(child) {
                                    filters['folder_' + child.id] = {
                                        text: '-- ' + child.name,
                                        props: {
                                            apex_folder: child.id
                                        },
                                        priority: priority++
                                    };
                                });
                            }
                        });
                    }
                    
                    this.filters = filters;
                }
            });
        }
    }
    
    
    // Listen for changes to the apex_folder property
    var originalMediaQuery = wp.media.model.Query;
    wp.media.model.Query = originalMediaQuery.extend({
        initialize: function() {
            originalMediaQuery.prototype.initialize.apply(this, arguments);
            
            // Listen for changes to the apex_folder property
            this.on('change:apex_folder', this.mediaFolderChanged);
        },
        
        // When the apex_folder property changes, reload the library
        mediaFolderChanged: function() {
            var folder = this.get('apex_folder');
            console.log('Media folder changed to:', folder);
        }
    });
    
    console.log('Media folders: WordPress media framework extended successfully');
})();





// Trigger count updates on various events
function setupFolderCountUpdates() {
    // After file upload completes
    if (wp.Uploader && wp.Uploader.queue) {
        wp.Uploader.queue.on('reset', function() {
            console.log('Upload queue reset, updating counts');
            setTimeout(updateFolderCounts, 1000);
        });
    }
    
    // When media folder is changed via dropdown
    jQuery(document).on('change', 'select[id^="attachments-"][id$="-apex_folder"]', function() {
        console.log('Media folder changed via dropdown');
        setTimeout(updateFolderCounts, 1000);
    });
    
    // When media modal is saved
    jQuery(document).on('click', '.media-modal .button.media-button-select', function() {
        console.log('Media modal saved');
        setTimeout(updateFolderCounts, 1000);
    });
    
    // When inline edit is saved
    jQuery(document).on('click', '.button.save', function() {
        if (jQuery(this).closest('.compat-item').length) {
            console.log('Inline edit saved');
            setTimeout(updateFolderCounts, 1000);
        }
    });
}

// Run setup when document is ready
jQuery(document).ready(function() {
    setupFolderCountUpdates();

    
});




