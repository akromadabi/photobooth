<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$queueFile = __DIR__ . '/queue.json';

require_once __DIR__ . '/queue_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_queue_stats':
        $state = getQueueState($queueFile);
        $waitingCount = 0;
        foreach ($state['queue_list'] as $item) {
            if ($item['status'] === 'WAITING') {
                $waitingCount++;
            }
        }
        echo json_encode([
            'success' => true,
            'active_queue_number' => $state['active_queue_number'],
            'total_waiting' => $waitingCount
        ]);
        break;

    case 'check_queue':
        $sessionId = isset($_GET['session_id']) ? $_GET['session_id'] : '';
        if (!$sessionId) {
            echo json_encode(['success' => false, 'message' => 'Session ID required']);
            break;
        }
        
        $state = getQueueState($queueFile);
        $foundItem = null;
        
        foreach ($state['queue_list'] as $item) {
            if ($item['session_id'] === $sessionId) {
                $foundItem = $item;
                break;
            }
        }
        
        if (!$foundItem) {
            echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
            break;
        }
        
        // Calculate queue position
        $waitingCount = 0;
        foreach ($state['queue_list'] as $item) {
            if ($item['status'] === 'WAITING' && $item['queue_number'] < $foundItem['queue_number']) {
                $waitingCount++;
            }
        }
        
        // If it's ACTIVE but the Kiosk hasn't grabbed it, it's still waiting count 0
        echo json_encode([
            'success' => true,
            'status' => $foundItem['status'],
            'queue_number' => $foundItem['queue_number'],
            'active_queue_number' => $state['active_queue_number'],
            'total_waiting' => $waitingCount,
            'command' => isset($foundItem['command']) ? $foundItem['command'] : '',
            'frame_id' => isset($foundItem['frame_id']) ? $foundItem['frame_id'] : '',
            'layout' => isset($foundItem['layout']) ? $foundItem['layout'] : '',
            'package_id' => $foundItem['package_id']
        ]);
        break;
        
    case 'send_command':
        $sessionId = isset($_POST['session_id']) ? $_POST['session_id'] : '';
        $command = isset($_POST['command']) ? $_POST['command'] : '';
        $frameId = isset($_POST['frame_id']) ? $_POST['frame_id'] : '';
        $layout = isset($_POST['layout']) ? $_POST['layout'] : '';
        
        if (!$sessionId || !$command) {
            echo json_encode(['success' => false, 'message' => 'Session ID and Command required']);
            break;
        }
        
        $state = getQueueState($queueFile);
        $updated = false;
        
        foreach ($state['queue_list'] as &$item) {
            if ($item['session_id'] === $sessionId) {
                if ($item['status'] !== 'ACTIVE') {
                    echo json_encode(['success' => false, 'message' => 'Sesi Anda belum aktif atau sudah selesai']);
                    exit;
                }
                
                $item['command'] = $command;
                if ($frameId) $item['frame_id'] = $frameId;
                if ($layout) $item['layout'] = $layout;
                
                if ($command === 'START_CAPTURE') {
                    $item['status'] = 'CAPTURING';
                }
                
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            saveQueueState($queueFile, $state);
            echo json_encode(['success' => true, 'message' => 'Perintah berhasil dikirim ke Kiosk']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sesi gagal diperbarui']);
        }
        break;
        
    case 'get_command':
        // Polled by Kiosk Android
        $state = getQueueState($queueFile);
        
        if ($state['active_session_id']) {
            $activeSession = null;
            foreach ($state['queue_list'] as $item) {
                if ($item['session_id'] === $state['active_session_id']) {
                    $activeSession = $item;
                    break;
                }
            }
            
            if ($activeSession && in_array($activeSession['status'], ['ACTIVE', 'CAPTURING'])) {
                echo json_encode([
                    'success' => true,
                    'active' => true,
                    'session_id' => $activeSession['session_id'],
                    'queue_number' => $activeSession['queue_number'],
                    'status' => $activeSession['status'],
                    'command' => isset($activeSession['command']) ? $activeSession['command'] : '',
                    'frame_id' => isset($activeSession['frame_id']) ? $activeSession['frame_id'] : '',
                    'layout' => isset($activeSession['layout']) ? $activeSession['layout'] : '',
                    'package_id' => $activeSession['package_id']
                ]);
                break;
            }
        }
        
        echo json_encode(['success' => true, 'active' => false, 'message' => 'Tidak ada sesi remote aktif']);
        break;
        
    case 'complete_session':
        // Polled by Android Kiosk or Phone when capture completed
        $sessionId = isset($_POST['session_id']) ? $_POST['session_id'] : '';
        if (!$sessionId) {
            echo json_encode(['success' => false, 'message' => 'Session ID required']);
            break;
        }
        
        if (completeSessionQueue($sessionId)) {
            echo json_encode(['success' => true, 'message' => 'Sesi selesai, bergeser ke antrean berikutnya']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan atau sudah diselesaikan']);
        }
        break;

    case 'kiosk_reset':
        // Reset state entirely
        $state = [
            "active_queue_number" => 0,
            "active_session_id" => "",
            "queue_list" => []
        ];
        saveQueueState($queueFile, $state);
        echo json_encode(['success' => true, 'message' => 'Antrean berhasil direset total']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
