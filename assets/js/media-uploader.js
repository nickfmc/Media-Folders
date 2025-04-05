/**
 * Media Folders - Uploader Integration
 * Handles folder selection in media uploader
 */
(function($) {
    "use strict";
    
    // Will be populated by localized data from PHP
    var MediaFolderUploader = {
        currentFolder: MediaFolderUploaderData.currentFolder || null,
        dropdownHtml: MediaFolderUploaderData.dropdownHtml || '',
        folderNonce: MediaFolderUploaderData.folderNonce || '',
        unassignedId: MediaFolderUploaderData.unassignedId || 0,
        
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
            
            // Hook into the uploader to capture the folder selection
            if (typeof wp !== "undefined" && wp.Uploader && wp.Uploader.prototype && !window.mediaFolderHooked) {
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
                };
                
                window.mediaFolderHooked = true;
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MediaFolderUploader.init();
    });
    
})(jQuery);