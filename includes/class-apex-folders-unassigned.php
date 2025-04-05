<?php
/**
 * Apex Folders Unassigned Handler
 *
 * Handles the Unassigned folder functionality.
 *
 * @package apex-folders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for handling the Unassigned folder
 */
class APEX_FOLDERS_Unassigned {
    
    /**
     * Get the Unassigned folder ID
     * 
     * @return int Unassigned folder ID
     */
    public static function get_id() {
        $unassigned = term_exists('Unassigned', 'media_folder');
        if (!$unassigned) {
            // Create it if it doesn't exist
            $unassigned = wp_insert_term(
                'Unassigned', 
                'media_folder',
                array(
                    'description' => 'Default folder for media items not assigned to any other folder',
                    'slug' => 'unassigned'
                )
            );
        }
        
        return is_array($unassigned) ? $unassigned['term_id'] : $unassigned;
    }
    
    /**
     * Ensure all attachments have a folder assignment
     * 
     * @param int $post_id The attachment ID
     * @return void
     */
    public static function ensure_attachment_has_folder($post_id) {
        // Only proceed for attachments
        if (get_post_type($post_id) !== 'attachment') {
            return;
        }
        
        // Check if the attachment already has a folder
        $terms = wp_get_object_terms($post_id, 'media_folder');
        
        // If it doesn't have any folder, assign to Unassigned
        if (empty($terms) || is_wp_error($terms)) {
            $unassigned_id = self::get_id();
            wp_set_object_terms($post_id, array($unassigned_id), 'media_folder', false);
        }
    }
    
    /**
     * Assign all unassigned media to the Unassigned folder
     * 
     * @return int Number of items assigned
     */
    public static function ensure_all_assigned() {
        global $wpdb;
        
        // Get the unassigned folder ID
        $unassigned_id = self::get_id();
        
        // Get the term taxonomy ID
        $tt_id = $wpdb->get_var($wpdb->prepare(
            "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy 
             WHERE term_id = %d AND taxonomy = 'media_folder'",
            $unassigned_id
        ));
        
        if (!$tt_id) {
            error_log("Error: Could not find term_taxonomy_id for Unassigned folder (ID: $unassigned_id)");
            return 0;
        }
        
        // Find all attachments that don't have ANY folder assignment
        $unassigned_attachments = $wpdb->get_col(
            "SELECT p.ID FROM $wpdb->posts p
             WHERE p.post_type = 'attachment'
             AND NOT EXISTS (
                 SELECT 1 FROM $wpdb->term_relationships tr
                 JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = 'media_folder' AND tr.object_id = p.ID
             )"
        );
        
        $count = 0;
        
        // Log what we found
        error_log("Found " . count($unassigned_attachments) . " attachments with no folder assignment");
        
        // Process in batches to avoid timeouts
        foreach ($unassigned_attachments as $attachment_id) {
            // Direct SQL approach to ensure reliability
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->term_relationships 
                 WHERE object_id = %d AND term_taxonomy_id = %d",
                $attachment_id, $tt_id
            ));
            
            if (!$exists) {
                // Insert the relationship directly
                $wpdb->insert(
                    $wpdb->term_relationships,
                    array(
                        'object_id' => $attachment_id,
                        'term_taxonomy_id' => $tt_id,
                        'term_order' => 0
                    )
                );
                
                if ($wpdb->insert_id || $wpdb->rows_affected) {
                    $count++;
                }
            }
        }
        
        // Update term count
        if ($count > 0) {
            // Update the count in the database directly
            $wpdb->query($wpdb->prepare(
                "UPDATE $wpdb->term_taxonomy 
                 SET count = count + %d
                 WHERE term_taxonomy_id = %d",
                $count, $tt_id
            ));
            
            // Clear cache
            clean_term_cache($unassigned_id, 'media_folder');
        }
        
        // Force update all term counts to be sure
        if (function_exists('theme_update_media_folder_counts')) {
            theme_update_media_folder_counts();
        }
        
        return $count;
    }
}