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
        
        init: function() {
            console.log("Media folder uploader script loaded");
            
            // Run on page load
            this.addFolderDropdownToUploader();
            
            // Handle dynamic uploader initialization
            $(document).on("click", ".media-modal .upload-files, .insert-media, .add_media", function() {
                console.log("Media upload button clicked");
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
                if ($(".upload-ui").length && !$(".upload-ui").next(".media-folder-select-container").length) {
                    MediaFolderUploader.addFolderDropdownToUploader();
                }
                
                checkCount++;
                if (checkCount > 10) clearInterval(checkInterval);
            }, 1000);
        },
        
        // Function to add folder dropdown to uploader
        addFolderDropdownToUploader: function() {
            console.log("Attempting to add folder dropdown");
    
            // Guard against potential errors
            if (typeof $ === 'undefined') {
                console.error('jQuery not available');
                return;
            }
            
            // Simplify targeting - look for common upload interface elements
            var $uploadUI = $(".upload-ui");
            if ($uploadUI.length && !$uploadUI.next(".media-folder-select-container").length) {
                console.log("Found upload UI, adding dropdown");
                $uploadUI.after(this.dropdownHtml);
            }
            
            // Also try for media modal uploader
            var $modalUploadUI = $(".media-modal .uploader-inline-content .upload-ui");
            if ($modalUploadUI.length && !$modalUploadUI.next(".media-folder-select-container").length) {
                console.log("Found modal upload UI, adding dropdown");
                $modalUploadUI.after(this.dropdownHtml);
            }
            
            // Hook into the uploader to capture the folder selection
            if (typeof wp !== "undefined" && wp.Uploader && wp.Uploader.prototype && !window.mediaFolderHooked) {
                var originalInit = wp.Uploader.prototype.init;
                
                wp.Uploader.prototype.init = function() {
                    originalInit.apply(this, arguments);
                    
                    this.uploader.bind("BeforeUpload", function(up, file) {
                        var folder_id = $("#media-folder-select").val();
                        console.log("Setting upload folder to: " + folder_id);
                        up.settings.multipart_params.media_folder_id = folder_id;
                    });
                    
                    // For updating folder counts after upload
                    this.uploader.bind("FileUploaded", function(up, file, response) {
                        console.log("File uploaded, will update counts soon");
                        setTimeout(function() {
                            if (typeof window.updateFolderCounts === "function") {
                                window.updateFolderCounts();
                            } else {
                                console.error("updateFolderCounts function not found in global scope!");
                                // Fallback: just reload the folder data via AJAX
                                $.post(ajaxurl, {
                                    action: "theme_get_folder_counts",
                                    nonce: MediaFolderUploader.folderNonce
                                });
                            }
                        }, 1000);
                    });
                };
                
                window.mediaFolderHooked = true;
                console.log("Successfully hooked into wp.Uploader");
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MediaFolderUploader.init();
    });
    
})(jQuery);