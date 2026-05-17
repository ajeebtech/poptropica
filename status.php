<?php
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

// Execute tailscale status --json
$output = shell_exec('tailscale status --json 2>/dev/null');
if ($output) {
    $data = json_decode($output, true);
    if (is_array($data) && isset($data['Peer']) && is_array($data['Peer'])) {
        foreach ($data['Peer'] as $peerKey => $peer) {
            if (isset($peer['Online']) && $peer['Online'] === true) {
                $onlineCount++;
            }
        }
        $success = true;
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
    // If we failed to query tailscale, see if we can serve the stale cache
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
    } else {
        // Absolute fallback: server is online (since this PHP script is executing), but we don't know the user count.
        echo json_encode([
            'status' => 'online',
            'online_users' => 0,
            'last_updated' => time(),
            'note' => 'could not query tailscale status'
        ]);
    }
}
?>
