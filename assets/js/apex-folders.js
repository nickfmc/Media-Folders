/**
 * Media Folders Filter for the WordPress media library (grid view and media modal)
 */

// IIFE to avoid global scope pollution
(function() {
    if (!window.wp || !wp.media || !wp.media.view || !wp.media.model) {
        return;
    }

    var folders = window.mediaFolders || [];
    var l10n = window.apexfoFoldersMedia || {};

    /**
     * Resolve a folder query value (slug, or ID from older links) to a folder ID
     */
    function getFolderId(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return 0;
        }

        var folder = _.find(folders, function(candidate) {
            return candidate.slug === String(value);
        });

        if (folder) {
            return parseInt(folder.id, 10);
        }

        return /^\d+$/.test(String(value)) ? parseInt(value, 10) : 0;
    }

    /**
     * Text shown for a folder in the filter dropdown
     */
    function getFolderLabel(folder) {
        return folder.name + ' (' + folder.count + ')';
    }

    /**
     * Apply fresh counts to the folder data and to every filter dropdown on the page
     */
    function applyFolderCounts(counts) {
        _.each(folders, function(folder) {
            if (!counts[folder.id]) {
                return;
            }

            folder.count = counts[folder.id].count;
            jQuery('select#media-attachment-folder-filter option[value="folder-' + folder.id + '"]').text(getFolderLabel(folder));
        });
    }

    // The media library sidebar fetches counts itself and announces them
    jQuery(document).on('apex-folders-counts', function(event, counts) {
        applyFolderCounts(counts);
    });

    /**
     * Fetch fresh counts after uploads or moves, unless the sidebar script is doing it
     */
    var refreshFolderCounts = _.debounce(function() {
        if (typeof window.updateFolderCounts === 'function' || !l10n.ajaxUrl) {
            return;
        }

        jQuery.post(l10n.ajaxUrl, {
            action: 'apex_folders_get_folder_counts',
            nonce: l10n.nonce
        }).done(function(response) {
            if (response && response.success && response.data) {
                applyFolderCounts(response.data);
            }
        });
    }, 150);

    // Store the original wp.media.view.AttachmentsBrowser for later use
    var originalAttachmentsBrowser = wp.media.view.AttachmentsBrowser;

    // Extend AttachmentsBrowser to include our custom filter
    wp.media.view.AttachmentsBrowser = originalAttachmentsBrowser.extend({
        createToolbar: function() {
            // Call the original method to create the toolbar with default filters
            originalAttachmentsBrowser.prototype.createToolbar.apply(this, arguments);

            // Create our custom dropdown filter
            var folderFilter = this.createFolderFilter();

            // Add it to the toolbar
            if (folderFilter) {
                this.toolbar.set('folderFilter', folderFilter);
            }
        },

        // Create our custom folder filter
        createFolderFilter: function() {
            if (!folders.length) {
                return;
            }

            // Create filter dropdown
            var FolderFilter = wp.media.view.AttachmentFilters.extend({
                id: 'media-attachment-folder-filter',
                className: 'attachment-filters',

                createFilters: function() {
                    var filters = {};

                    // Add "All Folders" option
                    filters.all = {
                        text: l10n.allFolders || 'All Folders',
                        props: {
                            apex_folder: null
                        },
                        priority: 10
                    };

                    // Add each folder as an option
                    folders.forEach(function(folder) {
                        var filterName = 'folder-' + folder.id;
                        filters[filterName] = {
                            text: getFolderLabel(folder),
                            props: {
                                apex_folder: folder.slug
                            },
                            priority: 20
                        };
                    });

                    this.filters = filters;
                },

                // Keep the filter visible in every media modal toolbar
                render: function() {
                    var result = wp.media.view.AttachmentFilters.prototype.render.apply(this, arguments);

                    this.$el.attr('style', 'display: inline-block !important; visibility: visible !important; margin-right: 8px;');

                    return result;
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

    // Keep folder views in step with each attachment's folder: an attachment
    // only belongs in a collection filtered by folder if it is in that folder.
    // This is what lets new uploads show up in (only) the right folder view,
    // and removes a file from the view when it is moved to another folder.
    var Attachments = wp.media.model.Attachments;
    var originalValidator = Attachments.prototype.validator;

    Attachments.prototype.validator = function(attachment) {
        var folderId = getFolderId(this.props.get('apex_folder'));

        if (folderId && attachment.has('apexFolder') && parseInt(attachment.get('apexFolder'), 10) !== folderId) {
            return false;
        }

        return originalValidator.apply(this, arguments);
    };

    // WordPress only shows files as they upload in views it knows how to
    // filter. Folder views can be filtered now, so watch the upload queue too.
    var Query = wp.media.model.Query;
    var originalQueryInitialize = Query.prototype.initialize;
    // _wpnonce comes along from the folder links in the sidebar and does not affect the results
    var observableArgs = ['s', 'order', 'orderby', 'posts_per_page', 'post_mime_type', 'post_parent', 'author', 'apex_folder', '_wpnonce'];
    var folderQueries = [];

    Query.prototype.initialize = function() {
        originalQueryInitialize.apply(this, arguments);

        if (!this.args || !getFolderId(this.args.apex_folder)) {
            return;
        }

        folderQueries.push(this);

        if (wp.Uploader && !_.difference(_.keys(this.args), observableArgs).length) {
            this.observe(wp.Uploader.queue);
        }
    };

    // When a file changes folder, add it to or remove it from the loaded folder views
    Attachments.all.on('change:apexFolder', function(attachment) {
        // Ignore attachments that are only now being loaded or uploaded
        if (attachment.get('uploading') || typeof attachment.previous('apexFolder') === 'undefined') {
            return;
        }

        _.each(folderQueries, function(query) {
            query.validate(attachment);
        });

        refreshFolderCounts();
    });

    // Counts change once an upload batch has finished
    if (wp.Uploader && wp.Uploader.queue) {
        wp.Uploader.queue.on('reset', refreshFolderCounts);
    }

})();
