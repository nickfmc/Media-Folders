/**
 * Media Folders Filter for WordPress Block Editor
 */

window.updateFolderCounts = function() {
   

    // We'll make 3 attempts to update counts, with increasing delays
    // This helps when WordPress is slow to update term counts
    var attempts = [1000, 3000, 6000]; // 1, 3, and 6 second delays

    attempts.forEach(function(delay) {
        setTimeout(function() {
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'apex_folders_get_folder_counts',
            nonce: apexFolderNonce.nonce
        },
        success: function(response) {
            if (response.success && response.data) {                
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
                            $link.text(folderName + ' (' + newCount + ')');
                            
                            // Add highlighting class
                            $item.addClass('count-updated');
                            setTimeout(function() {
                                $item.removeClass('count-updated');
                            }, 2000);
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
    // Add sidebar view for folder navigation
    if (typeof wp !== 'undefined' && wp.media && wp.media.view) {
        // Create a custom view for folder browser in the sidebar
        wp.media.view.MediaFolderBrowser = wp.media.View.extend({
            tagName: 'div',
            className: 'media-folder-browser',
            
            initialize: function() {
                // Call parent initialize
                wp.media.View.prototype.initialize.apply(this, arguments);
                
                // Listen for changes to the folder filter
                if (this.model) {
                    this.model.on('change:apex_folder', this.updateSelection, this);
                }
            },
            
            updateSelection: function() {
                var folderId = this.model.get('apex_folder');
                
                // Update the selected folder in the UI
                this.$el.find('.media-folder-item').removeClass('selected');
                this.$el.find('[data-folder-id="' + folderId + '"]').addClass('selected');
            },
            
            render: function() {
                // Clear the element
                this.$el.empty();
                
                // Add header
                this.$el.append('<h2>' + (apexFoldersL10n.folders || 'Folders') + '</h2>');
                
                // Create folder list container
                var $folderList = jQuery('<ul class="media-folder-list"></ul>');
                
                // Add "All Folders" option at the top
                $folderList.append(
                    '<li class="media-folder-item all-folders">' +
                    '<a href="#" data-folder-id="">' + (apexFoldersL10n.allMedia || 'All Media') + '</a>' +
                    '</li>'
                );
                
                if (window.mediaFolders && window.mediaFolders.length) {
                    // Organize folders by hierarchy
                    var unassigned = null;
                    var parents = [];
                    var children = {};
                    
                    // First pass: categorize folders
                    window.mediaFolders.forEach(function(folder) {
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
                    
                    // Add unassigned folder first
                    if (unassigned) {
                        $folderList.append(
                            '<li class="media-folder-item unassigned-folder">' +
                            '<a href="#" data-folder-id="' + unassigned.id + '">' + 
                            unassigned.name + ' (' + unassigned.count + ')' +
                            '</a></li>'
                        );
                    }
                    
                    // Add parent folders
                    parents.forEach(function(folder) {
                        var $folderItem = jQuery(
                            '<li class="media-folder-item parent-folder">' +
                            '<a href="#" data-folder-id="' + folder.id + '">' + 
                            folder.name + ' (' + folder.count + ')' +
                            '</a></li>'
                        );
                        
                        // Add children if any exist
                        if (children[folder.id] && children[folder.id].length) {
                            var $childList = jQuery('<ul class="child-folder-list"></ul>');
                            
                            children[folder.id].forEach(function(child) {
                                $childList.append(
                                    '<li class="media-folder-item child-folder">' +
                                    '<a href="#" data-folder-id="' + child.id + '">' + 
                                    child.name + ' (' + child.count + ')' +
                                    '</a></li>'
                                );
                            });
                            
                            $folderItem.append($childList);
                        }
                        
                        $folderList.append($folderItem);
                    });
                }
                
                // Add folder list to the view
                this.$el.append($folderList);
                
                // Add event listeners for folder selection
                this.$el.on('click', '.media-folder-item a', this.selectFolder.bind(this));
                
                // Add styles for folder list
                this.addStyles();
                
                return this;
            },
            
            selectFolder: function(e) {
                e.preventDefault();
                
                // Get the folder ID from data attribute
                var folderId = jQuery(e.currentTarget).data('folder-id');
                
                // Update the model with the selected folder
                this.model.set('apex_folder', folderId);
                
                // Update UI selection
                this.$el.find('.media-folder-item').removeClass('selected');
                jQuery(e.currentTarget).parent().addClass('selected');
                
                // Ensure this doesn't prevent media selection
                if (wp.media.frame && wp.media.frame.state) {
                    var state = wp.media.frame.state();
                    var selection = state.get('selection');
                    
                    if (selection) {
                        // Force an update of the selection - this helps ensure the Select button is enabled
                        if (selection.length > 0) {
                            selection.reset(selection.models);
                            
                            // Force enable the select button in Gutenberg modal
                            setTimeout(function() {
                                var $selectButton = jQuery('.media-toolbar-primary .media-button-select');
                                if ($selectButton.prop('disabled')) {
                                    $selectButton.prop('disabled', false);
                                }
                            }, 100);
                        }
                    }
                }
                
                // Sync the folder dropdown in the media toolbar
                jQuery('#media-attachment-folder-filter').val('folder-' + folderId).trigger('change');
            },
            
            addStyles: function() {
                // Only add styles once
                if (jQuery('#apex-folder-sidebar-styles').length === 0) {
                    jQuery('head').append(
                        '<style id="apex-folder-sidebar-styles">' +
                        '.media-folder-browser { padding: 0 16px 16px; }' +
                        '.media-folder-browser h2 { margin-bottom: 8px; }' +
                        '.media-folder-list { margin: 0; }' +
                        '.media-folder-item { margin-bottom: 2px; }' +
                        '.media-folder-item a { display: block; padding: 8px; text-decoration: none; color: #2c3338; border-radius: 2px; }' +
                        '.media-folder-item a:hover { background: rgba(0,0,0,0.04); }' +
                        '.media-folder-item.selected a { background: #0073aa; color: #fff; font-weight: 500; }' +
                        '.child-folder-list { margin-left: 16px; }' +
                        '</style>'
                    );
                }
            }
        });
    }

    // Store the original wp.media.view.AttachmentsBrowser for later use
    var originalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;
    
    // Extend AttachmentsBrowser to include our custom filter
    wp.media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend({
        createToolbar: function() {
            // Call the original method to create the toolbar with default filters
            originalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);
            
            // Create our custom dropdown filter if mediaFolders exists
            if (window.mediaFolders && window.mediaFolders.length) {
                // Create a custom filter for folders
                var FolderFilter = wp.media.view.AttachmentFilters.extend({
                    id: 'media-attachment-folder-filter',
                    className: 'attachment-filters',
                    
                    createFilters: function() {
                        var filters = {};
                        
                        // Add "All Folders" option
                        filters.all = {
                            text: apexFoldersL10n.allMedia || 'All Folders',
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
                    },
                    
                    // Override the render method to ensure visibility
                    render: function() {
                        var result = wp.media.view.AttachmentFilters.prototype.render.apply(this, arguments);
                        
                        // Force the element to be visible via inline style
                        this.$el.css({
                            'display': 'inline-block !important',
                            'visibility': 'visible !important',
                            'float': 'none',
                            'margin-right': '8px'
                        });
                        
                        return result;
                    }
                });
                
                // Add the filter to the toolbar with a specific priority
                this.toolbar.set('folderFilter', new FolderFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -75
                }).render());
                
                // Force the folder filter to be visible
                setTimeout(function() {
                    jQuery('#media-attachment-folder-filter').attr('style', 'display: inline-block !important; visibility: visible !important; margin-right: 8px;');
                }, 100);
            }
        },
        
        // The original createFolderFilter is no longer needed as we've moved its logic directly into createToolbar
        createFolderFilter: function() {
            // This method is kept for backward compatibility but is no longer used
            return null;
            
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
            
            addFolderFilter: function() {
                // Check if sidebar exists before trying to use it
                if (!this.sidebar) {
                    
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
                        text: apexFoldersL10n.allMedia || 'All Folders',
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
            
            // Add the filter to Media Frame Post, Image, Audio and Video frames too
            var frameList = ['Post', 'Select', 'Image', 'Audio', 'Video'];
            frameList.forEach(function(frame) {
                if (wp.media.view.MediaFrame[frame]) {
                    var originalSelect = wp.media.view.MediaFrame[frame];
                    
                    wp.media.view.MediaFrame[frame] = originalSelect.extend({
                        initialize: function() {
                            // Call original initialize
                            originalSelect.prototype.initialize.apply(this, arguments);
                            
                            // Add custom sidebar if not already bound
                            if (!this._hasCustomFolderFilter) {
                                this._hasCustomFolderFilter = true;
                                this.on('content:render:browse', this.addFolderFilter, this);
                            }
                        },
                        
                        addFolderFilter: function() {
                            // Check if sidebar exists before trying to use it
                            if (!this.sidebar) {
                                return;
                            }
                            
                            // Create the folder browser for sidebar
                            var folderBrowser = new wp.media.view.MediaFolderBrowser({
                                controller: this,
                                model: this.state().get('library')
                            });
                            
                            // Add it to the sidebar
                            this.sidebar.set('folder', folderBrowser);
                            
                            // Make sure the collection in the content area has a filter for apex_folder
                            var browser = this.content.get();
                            if (browser && browser.toolbar) {
                                // Check if the folder filter is already added
                                if (!browser.toolbar.get('folderFilter')) {
                                    var filter = new wp.media.view.AttachmentFilters.FolderFilter({
                                        controller: this,
                                        model: this.state().get('library'),
                                        priority: -75
                                    }).render();
                                    
                                    browser.toolbar.set('folderFilter', filter);
                                    
                                    // For Gutenberg, force folder filter to be visible with extra inline style
                                    setTimeout(function() {
                                        var $folderFilter = jQuery('#media-attachment-folder-filter');
                                        if ($folderFilter.length && !$folderFilter.is(':visible')) {
                                            $folderFilter.attr('style', 'display: inline-block !important; visibility: visible !important;');
                                            $folderFilter.closest('.attachment-folder-filter').attr('style', 'display: inline-block !important; visibility: visible !important;');
                                        }
                                    }, 100);
                                }
                            }
                        }
                    });
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
            
            // If we're filtering by folder, update the UI accordingly
            if (folder) {
                // Find the folder name for display purposes
                var folderName = '';
                if (window.mediaFolders) {
                    for (var i = 0; i < window.mediaFolders.length; i++) {
                        if (window.mediaFolders[i].id == folder) {
                            folderName = window.mediaFolders[i].name;
                            break;
                        }
                    }
                }
                
                // Add active filtering indicator
                if (jQuery('.media-toolbar-secondary .attachment-filters').length && !jQuery('.media-toolbar-secondary .apex-folder-indicator').length) {
                    jQuery('.media-toolbar-secondary').append('<span class="apex-folder-indicator">Filtering by folder: <strong>' + folderName + '</strong></span>');
                }
            } else {
                // Remove the indicator when not filtering
                jQuery('.apex-folder-indicator').remove();
            }
            
            // Ensure we don't break WordPress media selection
            if (wp.media.frame && wp.media.frame.state()) {
                var state = wp.media.frame.state();
                
                // Restore selection state to ensure the Select button works
                var selection = state.get('selection');
                if (selection && selection.length > 0) {
                    // This triggers a selection update which enables the buttons
                    selection.reset(selection.models);
                    
                    // Force the button to be enabled in Gutenberg
                    setTimeout(function() {
                        var $selectButton = jQuery('.media-toolbar-primary .media-button-select');
                        if ($selectButton.prop('disabled')) {
                            $selectButton.prop('disabled', false);
                        }
                    }, 100);
                }
            }
        }
    });
    
})();





// Trigger count updates on various events
function setupFolderCountUpdates() {
    // After file upload completes
    if (wp.Uploader && wp.Uploader.queue) {
        wp.Uploader.queue.on('reset', function() {
            setTimeout(updateFolderCounts, 1000);
        });
    }
    
    // When media folder is changed via dropdown
    jQuery(document).on('change', 'select[id^="attachments-"][id$="-apex_folder"]', function() {
        setTimeout(updateFolderCounts, 1000);
    });
    
    // When media modal is saved
    jQuery(document).on('click', '.media-modal .button.media-button-select', function() {
        setTimeout(updateFolderCounts, 1000);
    });
    
    // When inline edit is saved
    jQuery(document).on('click', '.button.save', function() {
        if (jQuery(this).closest('.compat-item').length) {
            setTimeout(updateFolderCounts, 1000);
        }
    });
    
    // When clicking on a folder in the media modal sidebar
    jQuery(document).on('click', '.media-folder-browser .media-folder-item a', function() {
        setTimeout(updateFolderCounts, 1000);
    });
}

// Run setup when document is ready
jQuery(document).ready(function() {
    setupFolderCountUpdates();
});




