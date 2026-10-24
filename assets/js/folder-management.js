/**
 * Apex Folders - Folder Management Script
 * Handles folder creation, editing, deletion and interaction
 */
jQuery(document).ready(function($) {
    var l10n = window.apexFoldersL10n || {};

    /**
     * Send a folder request, then reload on success or report the error
     */
    function folderRequest(data, errorPrefix) {
        data.nonce = apexFolderData.nonce;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    var message = response.data && response.data.message ? response.data.message : l10n.unknownError;
                    alert(errorPrefix + ' ' + message);
                }
            },
            error: function() {
                alert(l10n.networkError);
            }
        });
    }

    /**
     * Build a labelled dialog field. Text is always set as text, never as HTML,
     * because folder names are user input.
     */
    function dialogField(id, labelText, $input) {
        return $('<p></p>').append(
            $('<label></label>').attr('for', id).text(labelText),
            $input.attr('id', id).addClass('widefat')
        );
    }

    /**
     * Open a folder dialog with Cancel and confirm buttons
     */
    function openDialog(id, title, $content, confirmLabel, onConfirm, onOpen) {
        $('<div></div>').attr('id', id).append($content).dialog({
            title: title,
            dialogClass: 'wp-dialog',
            modal: true,
            resizable: false,
            width: 400,
            buttons: [
                {
                    text: l10n.cancel,
                    click: function() {
                        $(this).dialog('close');
                    }
                },
                {
                    text: confirmLabel,
                    click: function() {
                        onConfirm();
                        $(this).dialog('close');
                    }
                }
            ],
            open: onOpen,
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });
    }

    /**
     * Collapsible parent folders. The choice is remembered in this browser;
     * the folder being viewed always starts open.
     */
    var collapsedKey = 'apexfo_collapsed';
    var collapsed = {};

    try {
        collapsed = JSON.parse(localStorage.getItem(collapsedKey)) || {};
    } catch (e) {
        collapsed = {};
    }

    function setExpanded($parent, expanded) {
        var folderId = $parent.data('folder-id');

        $parent.toggleClass('apexfo-collapsed', !expanded);
        $parent.find('.apexfo-toggle').attr('aria-expanded', expanded ? 'true' : 'false');
        $('.apex-folder-list li.child-folder[data-parent-id="' + folderId + '"]').toggleClass('apexfo-hidden', !expanded);
    }

    // Apply remembered choices, except where the current folder lives
    $('.apex-folder-list li.parent-folder.has-children').each(function() {
        var $parent = $(this);
        var folderId = $parent.data('folder-id');
        var holdsCurrent = $parent.hasClass('current') ||
            $('.apex-folder-list li.child-folder.current[data-parent-id="' + folderId + '"]').length > 0;

        if (!holdsCurrent && typeof collapsed[folderId] === 'boolean') {
            setExpanded($parent, !collapsed[folderId]);
        }
    });

    $('.apex-folder-list').on('click', '.apexfo-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $parent = $(this).closest('li');
        var expanded = $parent.hasClass('apexfo-collapsed');

        setExpanded($parent, expanded);

        collapsed[$parent.data('folder-id')] = !expanded;
        try {
            localStorage.setItem(collapsedKey, JSON.stringify(collapsed));
        } catch (e) {
            // Remembering the choice is a convenience only
        }
    });

    // Add subfolder handler
    $('.add-subfolder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var parentId = $(this).data('parent-id');
        var parentName = String($(this).data('parent-name'));

        // Display dialog to create subfolder
        var $content = $('<div class="folder-creation-dialog"></div>').append(
            dialogField('new-subfolder-name', l10n.subfolderName, $('<input type="text" />')),
            $('<p></p>').text(l10n.parentFolder + ' ').append($('<strong></strong>').text(parentName))
        );

        openDialog('create-subfolder-dialog', l10n.createSubfolderTitle, $content, l10n.createSubfolder, function() {
            var folderName = $('#new-subfolder-name').val();

            if (folderName) {
                folderRequest({
                    action: 'apex_folders_add_apex_folder',
                    folder_name: folderName,
                    parent_id: parentId
                }, l10n.errorCreatingSubfolder);
            }
        }, function() {
            $('#new-subfolder-name').focus();
        });
    });

    // Add new folder
    $('.add-new-folder').on('click', function(e) {
        e.preventDefault();

        var $parentSelect = $('<select></select>').append(
            $('<option></option>').val('0').text(l10n.noParent)
        );

        // Add regular folders as potential parents
        $.each(apexFolderData.parentFolders, function(index, folder) {
            $parentSelect.append($('<option></option>').val(folder.term_id).text(folder.name));
        });

        // Create modal for folder creation
        var $content = $('<div class="folder-creation-dialog"></div>').append(
            dialogField('new-folder-name', l10n.folderName, $('<input type="text" />')),
            dialogField('new-folder-parent', l10n.parentFolderOptional, $parentSelect)
        );

        openDialog('create-folder-dialog', l10n.createFolderTitle, $content, l10n.createFolder, function() {
            var folderName = $('#new-folder-name').val();
            var parentId = $('#new-folder-parent').val();

            if (folderName) {
                folderRequest({
                    action: 'apex_folders_add_apex_folder',
                    folder_name: folderName,
                    parent_id: parentId
                }, l10n.errorCreatingFolder);
            }
        }, function() {
            // Focus the folder name input
            $('#new-folder-name').focus();
        });
    });

    // Edit folder
    $('.edit-folder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var folderId = $(this).data('folder-id');
        var currentName = String($(this).data('folder-name'));

        // Display dialog to rename folder
        var $content = $('<div class="folder-creation-dialog"></div>').append(
            dialogField('edit-folder-name', l10n.newName, $('<input type="text" />').val(currentName))
        );

        openDialog('edit-folder-dialog', l10n.renameFolderTitle, $content, l10n.save, function() {
            var newName = $('#edit-folder-name').val();

            if (newName && newName !== currentName) {
                folderRequest({
                    action: 'apex_folders_rename_apex_folder',
                    folder_id: folderId,
                    new_name: newName
                }, l10n.errorRenamingFolder);
            }
        }, function() {
            $('#edit-folder-name').focus().select();
        });
    });

    // Delete folder
    $('.delete-folder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var folderId = $(this).data('folder-id');
        var folderName = String($(this).data('folder-name'));

        if (confirm(l10n.confirmDelete.replace('%s', folderName) + '\n\n' + l10n.confirmDeleteNote)) {
            folderRequest({
                action: 'apex_folders_delete_apex_folder',
                folder_id: folderId
            }, l10n.errorDeletingFolder);
        }
    });
});
