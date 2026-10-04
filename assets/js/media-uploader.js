/**
 * Media Folders - Uploader Integration
 * Handles folder selection in media uploader
 */
(function($) {
    "use strict";
    
    // Will be populated by localized data from PHP
    var MediaFolderUploader = {
        currentFolder: apexfoMediaFolderUploaderData.currentFolder || null,
        dropdownHtml: apexfoMediaFolderUploaderData.dropdownHtml || '',
        folderNonce: apexfoMediaFolderUploaderData.folderNonce || '',
        unassignedId: apexfoMediaFolderUploaderData.unassignedId || 0,
        
        init: function() {
            
            // Run on page load
            this.addFolderDropdownToUploader();
            
            // Handle dynamic uploader initialization
            $(document).on("click", ".media-modal .upload-files, .insert-media, .add_media", function() {
                setTimeout(function() {
                    MediaFolderUploader.addFolderDropdownToUploader();
                }, 200);
            });
            
            // Extra check with a longer delay to catch late-initializing uploaders
            $(document).on("DOMNodeInserted", ".media-modal", function() {
                setTimeout(function() {
                    MediaFolderUploader.addFolderDropdownToUploader();
                }, 500);
            });
            
            // Final fallback - periodically check for upload UI that might appear later
            var checkCount = 0;
            var checkInterval = setInterval(function() {
                if ($(".upload-ui").length && !$(".upload-ui").next(".apex-folder-select-container").length) {
                    MediaFolderUploader.addFolderDropdownToUploader();
                }
                
                checkCount++;
                if (checkCount > 10) clearInterval(checkInterval);
            }, 1000);
            
            // CRITICAL: Add global event listener to track dropdown changes
            $(document).on('change', '#apex-folder-select', function() {
                var selectedFolderId = $(this).val();
                // Store in session storage for immediate access
                try {
                    window.sessionStorage.setItem('current_apex_folder', selectedFolderId);
                } catch(e) {
                    console.error("Could not store in sessionStorage:", e);
                }
                // Also set cookie with longer lifespan
                document.cookie = "apex_folder_upload_id=" + selectedFolderId + "; path=/; max-age=3600";
                
                // Update any folder filter on the page to match
                $('.attachment-folder-filter select').val('folder_' + selectedFolderId).trigger('change');
            });
        },
        
        // Function to add folder dropdown to uploader
        addFolderDropdownToUploader: function() {
    
            // Guard against potential errors
            if (typeof $ === 'undefined') {
                console.error('jQuery not available');
                return;
            }
            
            // Simplify targeting - look for common upload interface elements
            var $uploadUI = $(".upload-ui");
            if ($uploadUI.length && !$uploadUI.next(".apex-folder-select-container").length) {
                $uploadUI.after(this.dropdownHtml);
            }
            
            // Also try for media modal uploader
            var $modalUploadUI = $(".media-modal .uploader-inline-content .upload-ui");
            if ($modalUploadUI.length && !$modalUploadUI.next(".apex-folder-select-container").length) {
                $modalUploadUI.after(this.dropdownHtml);
            }

            // Important: We're no longer adding the dropdown to the media toolbar
            // This prevents duplicate dropdowns since WordPress already shows our filter in the view tab

            // Improvement: Auto-select folder in dropdown if apex_folder is in URL (runs every time dropdown is added)
            var urlParams = new URLSearchParams(window.location.search);
            var apexFolderParam = urlParams.get('apex_folder');
            
            if (apexFolderParam) {
                // Get the term_id from the slug
                setTimeout(function() {
                    var $dropdown = $('#apex-folder-select');
                    
                    if ($dropdown.length) {
                        // If apexFolderParam is a slug, we need to look for the matching folder in the dropdown
                        var folderFound = false;
                        
                        // First try direct match with ID (if numeric)
                        if (!isNaN(apexFolderParam)) {
                            $dropdown.val(apexFolderParam).trigger('change');
                            folderFound = true;
                        } else {
                            // Try to find folder by name (case insensitive)
                            $dropdown.find('option').each(function() {
                                var $option = $(this);
                                var optionText = $option.text().toLowerCase();
                                if (optionText === apexFolderParam.toLowerCase()) {
                                    $dropdown.val($option.val()).trigger('change');
                                    folderFound = true;
                                    return false; // break the loop
                                }
                            });
                        }
                        
                        if (folderFound) {
                            var selectedFolderId = $dropdown.val();
                            try {
                                window.sessionStorage.setItem('current_apex_folder', selectedFolderId);
                            } catch(e) {}
                            document.cookie = "apex_folder_upload_id=" + selectedFolderId + "; path=/; max-age=3600";
                        }
                    }
                }, 100); // Small delay to ensure the dropdown is fully initialized
            }
            
            // Hook into the uploader to capture the folder selection
            if (typeof wp !== "undefined" && wp.Uploader && wp.Uploader.prototype && !window.mediaFolderHooked) {
                // Silently hook the uploader without alerts
                var originalInit = wp.Uploader.prototype.init;
                
                wp.Uploader.prototype.init = function() {
                    originalInit.apply(this, arguments);
                    
                    this.uploader.bind("BeforeUpload", function(up, file) {
                        // Find ALL possible folder dropdowns in the DOM and choose the one that's visible
                        var folder_id = null;
                        
                        // Try multiple sources in order of preference
                        
                        // 1. First check sessionStorage (most reliable, set by dropdown change event)
                        try {
                            var storedId = window.sessionStorage.getItem('current_apex_folder');
                            if (storedId) {
                                folder_id = storedId;
                            }
                        } catch(e) {
                            // Silent catch - sessionStorage access failed
                        }
                        
                        // 2. If no sessionStorage, check visible dropdown
                        if (!folder_id) {
                            var $visibleDropdown = $(".media-modal:visible, .wrap:visible").find("#apex-folder-select");
                            if ($visibleDropdown.length) {
                                folder_id = $visibleDropdown.val();
                            }
                        }
                        
                        // 3. Try any dropdown as fallback
                        if (!folder_id) {
                            var $anyDropdown = $("#apex-folder-select");
                            if ($anyDropdown.length) {
                                folder_id = $anyDropdown.val();
                            }
                        }
                        
                        // 4. Try cookie as another fallback
                        if (!folder_id) {
                            var cookieMatch = document.cookie.match(/apex_folder_upload_id=(\d+)/);
                            if (cookieMatch && cookieMatch[1]) {
                                folder_id = cookieMatch[1];
                            }
                        }
                        
                        // Final fallback to unassigned
                        if (!folder_id) {
                            folder_id = MediaFolderUploader.unassignedId;
                        }
                        
                        
                        // Set in multiple places to ensure it's captured
                        up.settings.multipart_params = up.settings.multipart_params || {};
                        up.settings.multipart_params.apex_folder_id = folder_id;
                        up.settings.multipart_params['apex-folder-select'] = folder_id;
                        
                        // Set global vars that WordPress might check
                        window.wpMediaFolderId = folder_id;
                        
                        // Also set as a URL parameter that will be included 
                        var uploadAction = up.settings.url || '';
                        if (uploadAction.indexOf('?') === -1) {
                            up.settings.url = uploadAction + '?apex_folder_id=' + folder_id;
                        } else {
                            up.settings.url = uploadAction + '&apex_folder_id=' + folder_id;
                        }
                        // Set cookie again for redundancy
                        document.cookie = "apex_folder_upload_id=" + folder_id + "; path=/; max-age=3600";
                        
                    });

                    // Improvement 2: Live refresh media library after upload completes
                    this.uploader.bind("FileUploaded", function(up, file, response) {
                        // Only refresh if on media library page
                        if (window.location.pathname.indexOf('/upload.php') !== -1) {
                            // Update folder counts
                            if (typeof window.updateFolderCounts === 'function') {
                                window.updateFolderCounts();
                            }
                            
                            // Add a visible message to the page
                            var statusMessage = document.createElement('div');
                            statusMessage.style.position = 'fixed';
                            statusMessage.style.top = '50px';
                            statusMessage.style.left = '50%';
                            statusMessage.style.transform = 'translateX(-50%)';
                            statusMessage.style.background = '#0073aa';
                            statusMessage.style.color = 'white';
                            statusMessage.style.padding = '10px 20px';
                            statusMessage.style.borderRadius = '5px';
                            statusMessage.style.zIndex = '999999';
                            statusMessage.textContent = 'Refreshing media library...';
                            document.body.appendChild(statusMessage);
                            
                            // SIMPLIFIED APPROACH: Force reload of the top page after a short delay
                            setTimeout(function() {
                                try {
                                    // Try to force refresh the top level window
                                    window.top.location.reload(true);
                                } catch(e) {
                                    // Fallback to normal reload
                                    window.location.reload(true);
                                }
                            }, 800);
                        }
                    });
                };
                
                window.mediaFolderHooked = true;
            }
        },

        // Create a folder filter dropdown for the media toolbar
        createFolderFilterDropdown: function() {
            if (!window.mediaFolders || !window.mediaFolders.length) {
                return '';
            }
            
            var dropdownHtml = '<div class="apex-folder-filter-dropdown" style="margin-right: 15px; display: inline-block;">';
            dropdownHtml += '<label for="apex-folder-filter" style="margin-right: 5px;">Folder:</label>';
            dropdownHtml += '<select id="apex-folder-filter" name="apex-folder-filter">';
            dropdownHtml += '<option value="' + this.unassignedId + '">Unassigned</option>';
            
            // Add all available folders
            if (window.mediaFolders) {
                window.mediaFolders.forEach(function(folder) {
                    // Skip the Unassigned folder since we already added it
                    if (folder.slug !== 'unassigned') {
                        dropdownHtml += '<option value="' + folder.id + '">' + folder.name + '</option>';
                    }
                });
            }
            
            dropdownHtml += '</select>';
            dropdownHtml += '</div>';
            
            return dropdownHtml;
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MediaFolderUploader.init();
    });
    
    // Backup initialization for cases where document ready might not fire correctly
    window.addEventListener('load', function() {
        if (!window.mediaFolderHooked) {
            MediaFolderUploader.init();
        }
    });
    
})(jQuery);
