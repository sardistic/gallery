<?php
// import_gallery.php
// Run with: sudo /opt/bitnami/wp-cli/bin/wp eval-file import_gallery.php --path=/bitnami/wordpress

$metadata_file = '/home/coldh/gallery_metadata.json';
$images_dir = '/bitnami/wordpress/wp-content/uploads/deviantart-gallery';

if (!file_exists($metadata_file)) {
    die("Error: Metadata file not found at $metadata_file\n");
}

$json_content = file_get_contents($metadata_file);
$data = json_decode($json_content, true);

if (!$data) {
    die("Error: Failed to decode JSON metadata.\n");
}

// Flatten structure if it's nested (gallery-dl often outputs [ [item, item], [item] ])
$items = [];
if (isset($data[0]) && is_array($data[0])) {
    foreach ($data as $group) {
        if (is_array($group)) {
            $items = array_merge($items, $group);
        }
    }
} else {
    $items = $data;
}

echo "Found " . count($items) . " items to process.\n";

$count = 0;
foreach ($items as $item) {
    // Basic validation
    if (!isset($item['id']) || !isset($item['title'])) {
        echo "Skipping item without ID or Title.\n";
        continue;
    }

    $id = $item['id']; // DeviantArt ID
    $title = $item['title'];
    $date = isset($item['published_time']) ? $item['published_time'] : date('Y-m-d H:i:s');
    // Format date for WP (ISO 8601 is usually fine, but ensure it's valid)
    $date = date('Y-m-d H:i:s', strtotime($date));
    
    $description = isset($item['description']) ? $item['description'] : '';
    // Tags: defined as 'tags' array in DA metadata
    $tags = isset($item['tags']) ? (is_array($item['tags']) ? $item['tags'] : []) : [];

    // Find the file
    // Codebase files are named: "deviantart_<id>_<title_slug>.jpg"
    // We match by "*_<id>_*"
    $files = glob("$images_dir/*_{$id}_*.*");
    
    if (empty($files)) {
        echo "[MISSING] No file found for ID: $id ($title)\n";
        continue;
    }
    
    $file_path = $files[0];
    echo "[IMPORTING] $title (ID: $id) from " . basename($file_path) . "\n";

    // Build WP-CLI command
    // Escape arguments for shell safety
    $cmd_title = escapeshellarg($title);
    $cmd_caption = escapeshellarg($description);
    $cmd_date = escapeshellarg($date);
    $cmd_file = escapeshellarg($file_path);

    // 1. Import media
    $import_cmd = "sudo -u bitnami /opt/bitnami/wp-cli/bin/wp media import $cmd_file --title=$cmd_title --caption=$cmd_caption --date=$cmd_date --porcelain --path=/bitnami/wordpress";
    $attachment_id = shell_exec($import_cmd);
    $attachment_id = trim($attachment_id);

    if (!is_numeric($attachment_id)) {
        echo "  [ERROR] Failed to import. Output: $attachment_id\n";
        continue;
    }

    echo "  -> Imported as Attachment ID: $attachment_id\n";

    // 2. Set Tags (if any)
    if (!empty($tags)) {
        $tags_csv = implode(',', $tags);
        $cmd_tags = escapeshellarg($tags_csv);
        // 'media_tag' is simpler if using a plugin like 'Media Library Assistant', 
        // but core WP doesn't have tags for attachments by default. 
        // Standard 'post_tag' works if the theme supports it, or generic terms.
        // We'll try assigning to 'post_tag' (standard tags) -- usually only for posts, but let's try.
        // Or if you strictly mean "metadata tags", that implies a taxonomy.
        // Let's assume standard 'post_tag' for now, or just logging them if taxonomies aren't registered for identifiers.
        // Actually, often easier to just store them in description or Alt text if no taxonomy exists.
        // Let's try `wp post term set` for `post_tag`.
        $tag_cmd = "sudo -u bitnami /opt/bitnami/wp-cli/bin/wp post term set $attachment_id post_tag $cmd_tags --by=name --path=/bitnami/wordpress 2>&1";
        shell_exec($tag_cmd);
        echo "  -> Tags set: " . implode(', ', array_slice($tags, 0, 5)) . "...\n";
    }
    
    $count++;
}

echo "Done! Processed $count items.\n";
?>
