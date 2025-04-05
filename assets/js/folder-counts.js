/**
 * Apex Folders - Folder Counts Script
 * Handles updating folder counts and preserving count formats in the media library
 */
jQuery(document).ready(function($) {
    // Flag to track first load to prevent unwanted highlighting
    var isFirstLoad = true;
    
    // Storage for folder count formats to persist between page loads
    var folderFormats = {};
    
    // Cache for folder counts to avoid unnecessary recounts
    var folderCountsCache = {};
    
    // Track last update time to prevent too frequent updates
    var lastFullCountUpdate = 0;
    
    // Try to load saved formats from localStorage if available
    try {
        var savedFormats = localStorage.getItem('apex_folder_formats');
        if (savedFormats) {
            folderFormats = JSON.parse(savedFormats);
        }
    } catch(e) {
        // Silently fail if localStorage isn't available
    }
    
    /**
     * Restores folder formats without triggering highlighting
     * Used when the DOM is rebuilt by WordPress
     */
    function restoreFormatsOnly() {
        jQuery('.apex-folder-list li').each(function() {
            var $this = jQuery(this);
            var folderId = $this.data('folder-id');
            
            if (folderId && folderFormats[folderId] === 'total' && folderCountsCache[folderId]) {
                var $link = $this.find('a');
                var linkText = $link.text();
                var countData = folderCountsCache[folderId];
                
                // Only update if format is missing - check for the new format
                if (!linkText.includes(') - total (')) {
                    // Extract base name without any count format
                    var baseName = linkText.replace(/\s*\(\d+\)(?:\s*-\s*total\s*\(\d+\))?$|\s*\(\d+(?:\s*\/\s*\d+\s*total)?\)$/g, '');
                    // Apply the new format WITHOUT adding count-updated class
                    $link.text(baseName + ' (' + countData.own + ') - total (' + countData.total + ')');
                    
                    // Important: Remove any existing highlight that may have been applied
                    $this.removeClass('count-updated');
                }
            }
        });
    }
    
    /**
     * MutationObserver to detect DOM changes and restore formats
     * This is critical for preserving format when WordPress rebuilds the folder list
     */
    var observer = new MutationObserver(function(mutations) {
        var needsRestoration = false;
        
        mutations.forEach(function(mutation) {
            // Check if the mutation is in the folder list area
            if (mutation.target.classList && 
                (mutation.target.classList.contains('apex-folder-list') || 
                 mutation.target.closest('.apex-folder-list'))) {
                needsRestoration = true;
            }
            
            // Check added nodes for folder items
            if (mutation.addedNodes && mutation.addedNodes.length) {
                for (var i = 0; i < mutation.addedNodes.length; i++) {
                    var node = mutation.addedNodes[i];
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && 
                            (node.classList.contains('apex-folder-list') || 
                             node.querySelector('.apex-folder-list'))) {
                            needsRestoration = true;
                            break;
                        }
                    }
                }
            }
        });
        
        if (needsRestoration) {
            // Always restore without highlighting
            restoreFormatsOnly();
            
            // Multiple attempts to ensure format persistence
            setTimeout(restoreFormatsOnly, 50);
            setTimeout(restoreFormatsOnly, 100);
        }
    });
    
    // Configure observer to watch for relevant DOM changes
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true, // Also watch for text content changes
        attributes: true     // Watch for attribute changes
    });
    
    /**
     * Main function to update folder counts
     * Uses AJAX to get the latest counts and updates the UI
     * 
     * @param {boolean} skipHighlight - Whether to skip highlighting counts that changed
     */
    window.updateFolderCounts = function(skipHighlight) {
        // Always skip highlighting on first load
        if (isFirstLoad) {
            skipHighlight = true;
            isFirstLoad = false;
        }
        
        // Make an AJAX request to get updated folder information
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'apex_folders_get_folder_counts',
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
                    
                    // Record which folders should use total format
                    jQuery('.apex-folder-list li.parent-folder').each(function() {
                        var folderId = jQuery(this).data('folder-id');
                        if (folderId && folderChildren[folderId] && folderChildren[folderId].length > 0) {
                            folderFormats[folderId] = 'total';
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
                            
                            // Extract current count from link text
                            var currentOwnCount = 0;
                            var currentTotalCount = 0;
                            var countMatch = linkText.match(/\((\d+)( \/ (\d+) total)?\)$/);
                            if (countMatch) {
                                currentOwnCount = parseInt(countMatch[1], 10);
                                currentTotalCount = countMatch[3] ? parseInt(countMatch[3], 10) : currentOwnCount;
                            }
                            
                            // Check if this folder should use total format
                            var useTotalFormat = folderFormats[folderId] === 'total' || 
                                                ($this.hasClass('parent-folder') && 
                                                 folderChildren[folderId] && 
                                                 folderChildren[folderId].length > 0);
                            
                            if (useTotalFormat) {
                                // Calculate total count
                                var totalCount = folderData.count;
                                jQuery.each(folderChildren[folderId] || [], function(_, childId) {
                                    if (response.data[childId]) {
                                        totalCount += response.data[childId].count;
                                    }
                                });

                                    // Extract current count from link text - update regex to handle both old and new formats
                                    var currentOwnCount = 0;
                                    var currentTotalCount = 0;
                                    var countMatch = linkText.match(/\((\d+)\)(?:\s*-\s*total\s*\((\d+)\))?$/) || 
                                                    linkText.match(/\((\d+)(?:\s*\/\s*(\d+)\s*total)?\)$/);
                                    
                                    if (countMatch) {
                                        currentOwnCount = parseInt(countMatch[1], 10);
                                        currentTotalCount = countMatch[2] ? parseInt(countMatch[2], 10) : currentOwnCount;
                                    }
                                
                                // Stricter check for count changes
                                var countsChanged = (
                                    // Only consider it changed if we have previous values AND they're different
                                    (countMatch && currentOwnCount !== folderData.count) || 
                                    (countMatch && currentTotalCount !== totalCount)
                                );
                                
                                // Update with total format
                                var newText = linkText.replace(/\s*\(\d+\)(?:\s*-\s*total\s*\(\d+\))?$|\s*\(\d+(?:\s*\/\s*\d+\s*total)?\)$/g, '') + 
                                ' (' + folderData.count + ') - total (' + totalCount + ')';
                  $link.text(newText);
                                
                                // Store format for persistence
                                folderFormats[folderId] = 'total';
                                
                                // Cache the counts for quick restoration
                                folderCountsCache[folderId] = {
                                    own: folderData.count,
                                    total: totalCount
                                };
                                
                                // Only highlight if counts changed AND we're not skipping highlights
                                if (!skipHighlight && countsChanged && countMatch) {
                                    $this.addClass('count-updated');
                                    setTimeout(function() {
                                        $this.removeClass('count-updated');
                                    }, 2000);
                                } else {
                                    // Ensure no highlighting occurs when skipping
                                    $this.removeClass('count-updated');
                                }
                            } else {
                                // Regular format
                                var countsChanged = (countMatch && currentOwnCount !== folderData.count);
                                
                                // Update with regular format
                                var newText = linkText.replace(/\s*\(\d+(?:\s*\/\s*\d+\s*total)?\)$/, '') + 
                                              ' (' + folderData.count + ')';
                                $link.text(newText);
                                
                                // Only highlight if count changed and we're not skipping highlights
                                if (!skipHighlight && countsChanged && countMatch) {
                                    $this.addClass('count-updated');
                                    setTimeout(function() {
                                        $this.removeClass('count-updated');
                                    }, 2000);
                                } else {
                                    // Ensure no highlighting occurs when skipping
                                    $this.removeClass('count-updated');
                                }
                            }
                        }
                    });
                    
                    // Save formats to localStorage
                    try {
                        localStorage.setItem('apex_folder_formats', JSON.stringify(folderFormats));
                    } catch(e) {
                        // Silently fail if localStorage isn't available
                    }
                }
            }
        });
    };

    /**
     * Handle filter clicks in the media library
     * Prevents highlighting when filters are clicked
     */
    $(document).on('click', '.media-frame .attachments-browser .media-toolbar .filter-items a', function() {
        // Remove any highlights when filters are clicked
        $('.apex-folder-list li').removeClass('count-updated');
        
        // Force skip highlighting on filter clicks
        setTimeout(function() {
            window.updateFolderCounts(true);
        }, 500);
    });
    
    /**
     * Handle folder clicks to preserve count formats
     * Prevents highlighting when navigating between folders
     */
    $(document).on('click', '.apex-folder-list li a', function() {
        // Always remove any existing highlight class before proceeding
        $('.apex-folder-list li').removeClass('count-updated');
        
        // Store which folder was clicked
        var clickedFolderId = $(this).closest('li').data('folder-id');
        
        // Create a rapid sequence of restoration attempts
        var restoreTimes = [10, 50, 100, 200, 400, 800];
        restoreTimes.forEach(function(delay) {
            setTimeout(restoreFormatsOnly, delay);
        });
        
        // For the clicked folder specifically, ensure format stays visible
        if (clickedFolderId && folderFormats[clickedFolderId] === 'total') {
            var highlightRestoration = function() {
                var $clickedFolder = $('.apex-folder-list li[data-folder-id="' + clickedFolderId + '"]');
                if ($clickedFolder.length) {
                    var $link = $clickedFolder.find('a');
                    var linkText = $link.text();
                    var countData = folderCountsCache[clickedFolderId];
                    
                    if (countData && !linkText.includes(' / ' + countData.total + ' total)')) {
                        var baseName = linkText.replace(/\s*\(\d+(?:\s*\/\s*\d+\s*total)?\)$/, '');
                        $link.text(baseName + ' (' + countData.own + ' / ' + countData.total + ' total)');
                        
                        // Remove highlight to ensure no flash occurs during restoration
                        $clickedFolder.removeClass('count-updated');
                    }
                }
            };
            
            // Target the clicked folder specifically with more restoration attempts
            setTimeout(highlightRestoration, 50);
            setTimeout(highlightRestoration, 150);
            setTimeout(highlightRestoration, 300);
        }
        
        // Less frequent full updates for performance
        var now = new Date().getTime();
        if (!lastFullCountUpdate || now - lastFullCountUpdate > 5000) {
            setTimeout(function() {
                // Important: Use true to skip highlighting during these maintenance updates
                window.updateFolderCounts(true);
                lastFullCountUpdate = now;
            }, 500);
        }
    });
    
    // Initialize folder counts on page load - explicitly skip highlighting
    window.updateFolderCounts(true);
});