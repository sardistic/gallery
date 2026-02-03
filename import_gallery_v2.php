<?php
// import_gallery.php
// Run with: sudo /opt/bitnami/wp-cli/bin/wp eval-file import_gallery.php --path=/opt/bitnami/wordpress --allow-root

$php_bin = '/opt/bitnami/php/bin/php';
$wp_cli_phar = '/opt/bitnami/wp-cli/bin/wp-cli.phar';

$metadata_file = '/bitnami/wordpress/gallery_metadata.json';
$images_dir = '/bitnami/wordpress/wp-content/uploads/deviantart-gallery';

if (!file_exists($metadata_file)) {
    die("Error: Metadata file not found at $metadata_file\n");
}

$json_content = file_get_contents($metadata_file);
$data = json_decode($json_content, true);

if (!$data) {
    die("Error: Failed to decode JSON metadata.\n");
}

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

echo "Found " . count($items) . " items in metadata.\n";

$processed_ids = [];
$count = 0;

$skip_list = ['198000021']; // Sky-highrise

foreach ($items as $item) {
    if (!isset($item['title'])) {
        continue;
    }

    $id = '';
    if (isset($item['url']) && preg_match('/-(\d+)$/', $item['url'], $m)) {
        $id = $m[1];
    } elseif (isset($item['deviationid']) && is_numeric($item['deviationid'])) {
        $id = $item['deviationid'];
    } elseif (isset($item['target']) && isset($item['target']['deviationId']) && is_numeric($item['target']['deviationId'])) {
        $id = $item['target']['deviationId'];
    } elseif (isset($item['id']) && is_numeric($item['id'])) {
        $id = $item['id'];
    }

    if (empty($id)) {
        continue;
    }

    // Skip List
    if (in_array($id, $skip_list)) {
        continue;
    }

    // INTERNAL DEDUPLICATION
    if (in_array($id, $processed_ids)) {
        continue;
    }
    $processed_ids[] = $id;

    $title = $item['title'];

    // Check if ALREADY IMPORTED
    $check_cmd = "$php_bin $wp_cli_phar post list --post_type=attachment --field=ID --s=\"deviantart_{$id}_\" --path=/opt/bitnami/wordpress --allow-root 2>&1";
    $existing_id = shell_exec($check_cmd);
    $existing_id = trim($existing_id);

    if (is_numeric($existing_id) && $existing_id > 0) {
        echo "[SKIPPING] ID $id already exists as Attachment $existing_id\n";
        continue;
    }

    // THROTTLE: Sleep 3 seconds to let server DB/CPU recover
    sleep(3);

    $raw_date = isset($item['published_time']) ? $item['published_time'] : time();
    $date = date('Y-m-d H:i:s');

    if (is_numeric($raw_date)) {
        $date = date('Y-m-d H:i:s', (int) $raw_date);
    } else {
        $ts = strtotime($raw_date);
        if ($ts) {
            $date = date('Y-m-d H:i:s', $ts);
        }
    }

    $description = isset($item['description']) ? $item['description'] : '';
    $tags = isset($item['tags']) ? (is_array($item['tags']) ? $item['tags'] : []) : [];

    $files = glob("$images_dir/*_{$id}_*.*");

    if (empty($files)) {
        continue;
    }

    $file_path = $files[0];
    echo "[IMPORTING] $title (ID: $id) Date: $date\n";

    $cmd_title = escapeshellarg($title);
    $cmd_caption = escapeshellarg($description);
    $cmd_file = escapeshellarg($file_path);

    $import_cmd = "$php_bin $wp_cli_phar media import $cmd_file --title=$cmd_title --caption=$cmd_caption --porcelain --path=/opt/bitnami/wordpress --allow-root 2>&1";

    $attachment_id = shell_exec($import_cmd);
    $attachment_id = trim($attachment_id);

    $lines = explode("\n", $attachment_id);
    $last_line = trim(end($lines));

    if (!is_numeric($last_line)) {
        echo "  [ERROR] Output: $attachment_id\n";
        continue;
    } else {
        $attachment_id = $last_line;
    }

    echo "  -> Imported ID: $attachment_id\n";

    $cmd_date = escapeshellarg($date);
    $date_cmd = "$php_bin $wp_cli_phar post update $attachment_id --post_date=$cmd_date --path=/opt/bitnami/wordpress --allow-root 2>&1";
    shell_exec($date_cmd);

    if (!empty($tags)) {
        $tags_csv = implode(',', $tags);
        $cmd_tags = escapeshellarg($tags_csv);
        $tag_cmd = "$php_bin $wp_cli_phar post term set $attachment_id post_tag $cmd_tags --by=name --path=/opt/bitnami/wordpress --allow-root 2>&1";
        shell_exec($tag_cmd);
    }

    $count++;
}

echo "Done! Processed $count items.\n";
?>