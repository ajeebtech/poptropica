<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$cacheFile = 'status.json';
$cacheTime = 30; // Cache duration in seconds

// Check if we have a fresh cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

$onlineCount = 0;
$success = false;

// Attempt 1: Try executing headscale CLI nodes list (requires www-data in headscale group)
$output = shell_exec('headscale nodes list -o json 2>/dev/null');
if ($output) {
    $nodes = json_decode($output, true);
    if (is_array($nodes)) {
        foreach ($nodes as $node) {
            if (isset($node['online']) && $node['online'] === true) {
                $onlineCount++;
            }
        }
        $success = true;
    }
}

// Attempt 2: Try executing headscale CLI machines list (older headscale versions)
if (!$success) {
    $output = shell_exec('headscale machines list -o json 2>/dev/null');
    if ($output) {
        $nodes = json_decode($output, true);
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                if (isset($node['online']) && $node['online'] === true) {
                    $onlineCount++;
                }
            }
            $success = true;
        }
    }
}

// Prepare result
if ($success) {
    $result = [
        'status' => 'online',
        'online_users' => $onlineCount,
        'last_updated' => time()
    ];
    file_put_contents($cacheFile, json_encode($result));
    echo json_encode($result);
} else {
    // If we failed to query headscale, see if we can serve the stale cache
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        // Absolute fallback: server is online (since this PHP script is executing), but we don't know the user count.
        echo json_encode([
            'status' => 'online',
            'online_users' => 0,
            'last_updated' => time(),
            'note' => 'could not query headscale directly from web server'
        ]);
    }
}
?>
