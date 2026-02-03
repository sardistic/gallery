<?php
/**
 * update_meta_from_json.php
 * Usage: sudo /opt/bitnami/wp-cli/bin/wp eval-file update_meta_from_json.php --path=/opt/bitnami/wordpress --allow-root
 */

$json_dir = '/bitnami/wordpress/metadata_dump';
$files = glob("$json_dir/*.json");

if (empty($files)) {
    die("No JSON files found in $json_dir.\n");
}

echo "Found " . count($files) . " JSON files to process.\n";

foreach ($files as $file) {
    $id = basename($file, '.json'); // e.g. 19994893
    // echo "Processing ID $id... ";

    // Use WP Query to find attachment safely
    $args = [
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'meta_query' => [
            [
                'key' => '_wp_attached_file',
                'value' => "deviantart_{$id}_",
                'compare' => 'LIKE'
            ]
        ],
        'posts_per_page' => 1,
        'fields' => 'ids'
    ];

    $posts = get_posts($args);

    if (empty($posts)) {
        // echo "Attachment not found.\n";
        continue;
    }

    $wp_id = $posts[0];
    echo "ID $id -> WP $wp_id. ";

    // Read JSON
    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (!$data)
        continue;

    $camera = '';
    $lens = '';
    $iso = '';
    $shutter = '';
    $aperture = '';

    // Top-level
    if (isset($data['camera']))
        $camera = $data['camera'];
    if (isset($data['lens']))
        $lens = $data['lens'];
    if (isset($data['iso']))
        $iso = $data['iso'];
    if (isset($data['shutter_speed']))
        $shutter = $data['shutter_speed'];
    if (isset($data['aperture']))
        $aperture = $data['aperture'];

    // Extended
    if (isset($data['extended'])) {
        if (empty($camera) && isset($data['extended']['camera']))
            $camera = $data['extended']['camera'];
        if (empty($lens) && isset($data['extended']['lens']))
            $lens = $data['extended']['lens'];
        if (empty($iso) && isset($data['extended']['iso']))
            $iso = $data['extended']['iso'];
        if (empty($shutter) && isset($data['extended']['shutter_speed']))
            $shutter = $data['extended']['shutter_speed'];
        if (empty($aperture) && isset($data['extended']['aperture']))
            $aperture = $data['extended']['aperture'];
    }

    // Update
    $updated = false;
    if ($camera) {
        update_post_meta($wp_id, '_da_camera', $camera);
        $updated = true;
    }
    if ($lens) {
        update_post_meta($wp_id, '_da_lens', $lens);
        $updated = true;
    }
    if ($iso) {
        update_post_meta($wp_id, '_da_iso', $iso);
        $updated = true;
    }
    if ($shutter) {
        update_post_meta($wp_id, '_da_shutter', $shutter);
        $updated = true;
    }
    if ($aperture) {
        update_post_meta($wp_id, '_da_aperture', $aperture);
        $updated = true;
    }

    if ($updated) {
        echo "Updated (Cam: $camera)\n";
    } else {
        echo "No data in JSON\n";
    }
}
echo "Done.\n";
?>