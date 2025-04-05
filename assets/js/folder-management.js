/**
 * Apex Folders - Folder Management Script
 * Handles folder creation, editing, deletion and interaction
 */
jQuery(document).ready(function($) {
    // Add subfolder handler
    $('.add-subfolder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var parentId = $(this).data('parent-id');
        var parentName = $(this).data('parent-name');
        
        // Display dialog to create subfolder
        var dialogContent = '<div class="folder-creation-dialog">' +
            '<p><label for="new-subfolder-name">Subfolder Name:</label>' +
            '<input type="text" id="new-subfolder-name" class="widefat" /></p>' +
            '<p>Parent folder: <strong>' + parentName + '</strong></p>' +
            '</div>';
        
        $('<div id="create-subfolder-dialog"></div>').html(dialogContent).dialog({
            title: 'Create New Subfolder',
            dialogClass: 'wp-dialog',
            modal: true,
            resizable: false,
            width: 400,
            buttons: {
                'Cancel': function() {
                    $(this).dialog('close');
                },
                'Create Subfolder': function() {
                    var folderName = $('#new-subfolder-name').val();
                    
                    if (folderName) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'apex_folders_add_apex_folder',
                                folder_name: folderName,
                                parent_id: parentId,
                                nonce: apexFolderData.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Error creating subfolder: ' + (response.data?.message || 'Unknown error'));
                                }
                            }
                        });
                    }
                    
                    $(this).dialog('close');
                }
                
            },
            open: function() {
                $('#new-subfolder-name').focus();
            },
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });
    });

    // Add new folder
    $('.add-new-folder').on('click', function(e) {
        e.preventDefault();
        
        // Create modal for folder creation
        var dialogContent = '<div class="folder-creation-dialog">' +
            '<p><label for="new-folder-name">Folder Name:</label>' +
            '<input type="text" id="new-folder-name" class="widefat" /></p>' +
            '<p><label for="new-folder-parent">Parent Folder (optional):</label>' +
            '<select id="new-folder-parent" class="widefat">' +
            '<option value="0">None (top level)</option>';
            
        // Add regular folders as potential parents
        $.each(apexFolderData.parentFolders, function(index, folder) {
            dialogContent += '<option value="' + folder.term_id + '">' + folder.name + '</option>';
        });
        
        dialogContent += '</select></p>' +
            '</div>';
        
        // Create dialog
        $('<div id="create-folder-dialog"></div>').html(dialogContent).dialog({
            title: 'Create New Folder',
            dialogClass: 'wp-dialog',
            modal: true,
            resizable: false,
            width: 400,
            buttons: {
                'Cancel': function() {
                    $(this).dialog('close');
                },
                'Create Folder': function() {
                    var folderName = $('#new-folder-name').val();
                    var parentId = $('#new-folder-parent').val();
                    
                    if (folderName) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'apex_folders_add_apex_folder',
                                folder_name: folderName,
                                parent_id: parentId,
                                nonce: apexFolderData.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Error creating folder: ' + (response.data?.message || 'Unknown error'));
                                }
                            }
                        });
                    }
                    
                    $(this).dialog('close');
                }
                
            },
            open: function() {
                // Focus the folder name input
                $('#new-folder-name').focus();
            },
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });
    });

    // Edit folder
    $('.edit-folder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var folderId = $(this).data('folder-id');
        var currentName = $(this).data('folder-name');
        
        // Display dialog to rename folder
        var dialogContent = '<div class="folder-creation-dialog">' +
            '<p><label for="edit-folder-name">New Name:</label>' +
            '<input type="text" id="edit-folder-name" class="widefat" value="' + currentName + '" /></p>' +
            '</div>';
        
        $('<div id="edit-folder-dialog"></div>').html(dialogContent).dialog({
            title: 'Rename Folder',
            dialogClass: 'wp-dialog',
            modal: true,
            resizable: false,
            width: 400,
            buttons: {
                'Cancel': function() {
                    $(this).dialog('close');
                },
                'Save': function() {
                    var newName = $('#edit-folder-name').val();
                    
                    if (newName && newName !== currentName) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'apex_folders_rename_apex_folder',
                                folder_id: folderId,
                                new_name: newName,
                                nonce: apexFolderData.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert('Error renaming folder: ' + (response.data?.message || 'Unknown error'));
                                }
                            }
                        });
                    }
                    
                    $(this).dialog('close');
                }
            },
            open: function() {
                $('#edit-folder-name').focus().select();
            },
            close: function() {
                $(this).dialog('destroy').remove();
            }
        });
    });
    
    // Delete folder
    $('.delete-folder').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var folderId = $(this).data('folder-id');
        var folderName = $(this).data('folder-name');
        
        if (confirm('Are you sure you want to delete the folder "' + folderName + '"?\n\nFiles in this folder will be moved to the Unassigned folder.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'apex_folders_delete_apex_folder',
                    folder_id: folderId,
                    nonce: apexFolderData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Error deleting folder';
                        alert(errorMsg);
                    }
                },
                error: function() {
                    alert('Network error when trying to delete folder');
                }
            });
        }
    });
});