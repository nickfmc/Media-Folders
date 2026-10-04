/**
 * Media Folders - Uploader Integration
 * Handles folder selection in media uploader
 */
(function($) {
    "use strict";

    // Populated by localized data from PHP
    var data = window.MediaFolderUploaderData || {};

    var MediaFolderUploader = {
        // Folder picked in the dropdown; null until the user picks one
        selectedFolder: null,

        // Folder new uploads go to: the picked folder, else the folder being viewed, else Unassigned
        getTargetFolder: function() {
            return this.selectedFolder ||
                parseInt(data.currentFolderId, 10) ||
                parseInt(data.unassignedId, 10) ||
                0;
        },

        // Make every folder dropdown show the target folder
        syncDropdowns: function($context) {
            var $dropdowns = $context ? $context.find(".apex-folder-select") : $(".apex-folder-select");
            $dropdowns.val(String(this.getTargetFolder()));
        }
    };

    $(document).on("change", ".apex-folder-select", function() {
        MediaFolderUploader.selectedFolder = parseInt($(this).val(), 10) || null;
        MediaFolderUploader.syncDropdowns();
    });

    // This runs as soon as the script loads rather than on document ready: the
    // media grid creates its uploader on document ready, and an uploader only
    // picks up the hook below if it is created after the hook is in place.
    if (window.wp && wp.Uploader) {
        // Tag each file with its folder when it is queued, so every file in a
        // batch keeps the folder that was selected when it was dropped
        wp.Uploader.queue.on("add", function(attachment) {
            if (!attachment.get("apexFolder")) {
                attachment.set("apexFolder", MediaFolderUploader.getTargetFolder());
            }
        });

        var originalInit = wp.Uploader.prototype.init;

        wp.Uploader.prototype.init = function() {
            originalInit.apply(this, arguments);

            // Send the folder along with each file
            this.uploader.bind("BeforeUpload", function(up, file) {
                var folderId = (file.attachment && file.attachment.get("apexFolder")) ||
                    MediaFolderUploader.getTargetFolder();

                up.settings.multipart_params = up.settings.multipart_params || {};
                up.settings.multipart_params.apex_folder_id = folderId;
            });
        };
    }

    // The dropdown is part of the uploader template, so it is recreated on every render
    if (window.wp && wp.media && wp.media.view && wp.media.view.UploaderInline) {
        var originalRender = wp.media.view.UploaderInline.prototype.render;

        wp.media.view.UploaderInline.prototype.render = function() {
            var result = originalRender.apply(this, arguments);
            // The view may not be attached to the page yet, so look inside it
            MediaFolderUploader.syncDropdowns(this.$el);
            return result;
        };
    }

    $(document).ready(function() {
        MediaFolderUploader.syncDropdowns();

        // Standalone upload screen (media-new.php) uses a plain plupload instance
        if (!(window.wp && wp.Uploader) && window.uploader && typeof window.uploader.bind === "function") {
            window.uploader.bind("BeforeUpload", function(up) {
                up.settings.multipart_params = up.settings.multipart_params || {};
                up.settings.multipart_params.apex_folder_id = MediaFolderUploader.getTargetFolder();
            });
        }
    });

})(jQuery);
