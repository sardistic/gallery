<?php
// cleanup_duplicates.php
// Run with: sudo /opt/bitnami/wp-cli/bin/wp eval-file cleanup_duplicates.php --path=/opt/bitnami/wordpress --allow-root

// Get all attachments that look like our imports
// We can select all images.
$args = [
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
    'fields' => 'ids',
    's' => 'deviantart' // Search for 'deviantart' in title/content (if set) OR we can filter later
];

// Note: 's' might not catch filenames in normal GetPosts. 
// Better to get ALL attachments and filter by Title manually or GUID.
$query = new WP_Query([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => -1,
]);

$posts = $query->get_posts();

echo "Scanning " . count($posts) . " attachments...\n";

$grouped = [];

foreach ($posts as $p) {
    // Check if it's one of ours. 
    // The Import Script used the original Title from DA.
    // The filename contains "deviantart_".

    $file = get_attached_file($p->ID);
    if (strpos(basename($file), 'deviantart_') === false) {
        continue;
    }

    $title = $p->post_title;
    if (!isset($grouped[$title])) {
        $grouped[$title] = [];
    }
    $grouped[$title][] = $p->ID;
}

$deleted_count = 0;

foreach ($grouped as $title => $ids) {
    if (count($ids) > 1) {
        // Sort IDs DESC (Highest first)
        rsort($ids);

        $keep = $ids[0]; // Keep the NEWEST ID (Correct Import)
        $trash = array_slice($ids, 1);

        echo "Duplicate: '$title' -> Keeping ID $keep, Deleting: " . implode(', ', $trash) . "\n";

        foreach ($trash as $del_id) {
            wp_delete_attachment($del_id, true); // Force delete
            $deleted_count++;
        }
    }
}

echo "Cleanup Complete. Deleted $deleted_count duplicate attachments.\n";
?>