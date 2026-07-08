<?php
session_start();

$queueFile = __DIR__ . '/queue.json';
$packagesFile = __DIR__ . '/packages.json';
$configPath = __DIR__ . '/frames/config.json';
$settingsFile = __DIR__ . '/settings.json';

// Resolve session ID and remote mode status
$sessionId = $_GET['session_id'] ?? '';
$isRemoteMode = !empty($sessionId);

// Load settings to check active printer types
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}
$printerType = isset($settings['printer_type']) ? $settings['printer_type'] : 'AUTO';
$colorActive = ($printerType === 'COLOR' || $printerType === 'AUTO' || $printerType === 'NONE');
$thermalActive = ($printerType === 'THERMAL' || $printerType === 'AUTO' || $printerType === 'NONE');

// Helper to get state
function getQueueState($file) {
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return [
        "active_queue_number" => 0,
        "active_session_id" => "",
        "queue_list" => []
    ];
}

function saveQueueState($file, $state) {
    file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT));
}

// Helper to check if event rental duration is currently active
function isRentalActive($evt) {
    if (!isset($evt['billing_type']) || $evt['billing_type'] !== 'RENTAL_DURATION') {
        return false;
    }
    $start = $evt['rental_start_time'] ?? '';
    $end = $evt['rental_end_time'] ?? '';
    if (!$start || !$end) {
        return false;
    }
    $now = time();
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    return ($now >= $startTime && $now <= $endTime);
}

// Load configurations first
$packages = [];
if (file_exists($packagesFile)) {
    $packages = json_decode(file_get_contents($packagesFile), true);
}

$config = ['events' => [], 'frames' => []];
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
}

// Resolve active/unlocked event from session or GET
$eventCode = '';
$eventId = '';
$resolvedEvent = null;
$isFreeActive = false;

if (isset($_GET['event_code']) && !empty($_GET['event_code'])) {
    $eventCode = strtoupper(trim($_GET['event_code']));
    $_SESSION['event_code'] = $eventCode;
} elseif (isset($_SESSION['event_code'])) {
    $eventCode = $_SESSION['event_code'];
}

if (!empty($eventCode)) {
    if (isset($config['events'])) {
        foreach ($config['events'] as $evt) {
            if (isset($evt['code']) && strtoupper($evt['code']) === strtoupper($eventCode)) {
                $resolvedEvent = $evt;
                $eventId = $evt['id'];
                $_SESSION['event_id'] = $eventId;
                break;
            }
        }
    }
}

if ($resolvedEvent) {
    $isFreeActive = isRentalActive($resolvedEvent);
}

// 1. Handle Order creation (Form POST)
if (isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $packageId = isset($_POST['package_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['package_id']) : '';
    
    if ($packageId) {
        $state = getQueueState($queueFile);
        
        // Resolve next queue number
        $maxQueue = 0;
        foreach ($state['queue_list'] as $item) {
            if ($item['queue_number'] > $maxQueue) {
                $maxQueue = $item['queue_number'];
            }
        }
        $nextQueue = $maxQueue + 1;
        $sessionId = bin2hex(random_bytes(8));
        
        $selectedEventId = $_POST['event_id'] ?? ($eventId ?? '');
        $matchedEvt = null;
        if ($selectedEventId && isset($config['events'])) {
            foreach ($config['events'] as $evt) {
                if ($evt['id'] === $selectedEventId) {
                    $matchedEvt = $evt;
                    break;
                }
            }
        }
        
        $isFree = $matchedEvt && isRentalActive($matchedEvt);
        $status = $isFree ? 'WAITING' : 'UNPAID';
        
        $newQueueItem = [
            "queue_number" => $nextQueue,
            "session_id" => $sessionId,
            "status" => $status,
            "package_id" => $packageId,
            "timestamp" => time()
        ];
        
        if ($selectedEventId) {
            $newQueueItem['event_id'] = $selectedEventId;
        }
        
        if ($isFree) {
            $activeExists = false;
            foreach ($state['queue_list'] as $q) {
                if ($q['status'] === 'ACTIVE' || $q['status'] === 'CAPTURING') {
                    $activeExists = true;
                    break;
                }
            }
            
            if (!$activeExists) {
                $newQueueItem['status'] = 'ACTIVE';
                $state['active_queue_number'] = $nextQueue;
                $state['active_session_id'] = $sessionId;
            }
        }
        
        $state['queue_list'][] = $newQueueItem;
        saveQueueState($queueFile, $state);
        
        if ($isFree) {
            header("Location: order.php?session_id=$sessionId");
            exit;
        } else {
            header("Location: payment_gateway.php?order_id=$sessionId&package_id=$packageId");
            exit;
        }
    }
}

$activePrintFlow = '';
if ($isRemoteMode) {
    // Save session to cookie/PHP session if it is currently active
    $state = getQueueState($queueFile);
    $sessionExists = false;
    $sessionStatus = '';
    foreach ($state['queue_list'] as $item) {
        if ($item['session_id'] === $sessionId) {
            $sessionExists = true;
            $sessionStatus = $item['status'];
            $itemPackageId = $item['package_id'] ?? '';
            foreach ($packages as $pkg) {
                if ($pkg['id'] === $itemPackageId) {
                    $activePrintFlow = $pkg['print_flow'] ?? '';
                    break;
                }
            }
            break;
        }
    }
    
    if ($sessionExists) {
        if (in_array($sessionStatus, ['WAITING', 'ACTIVE', 'CAPTURING'])) {
            $_SESSION['active_session_id'] = $sessionId;
            setcookie('active_session_id', $sessionId, time() + 7200, '/');
        } elseif ($sessionStatus === 'FINISHED') {
            // Clear session indicators once session is complete
            unset($_SESSION['active_session_id']);
            setcookie('active_session_id', '', time() - 3600, '/');
        }
    }
} else {
    // If on ordering/catalog page, check if there's an ongoing session
    $savedSessionId = '';
    if (isset($_SESSION['active_session_id']) && !empty($_SESSION['active_session_id'])) {
        $savedSessionId = $_SESSION['active_session_id'];
    } elseif (isset($_COOKIE['active_session_id']) && !empty($_COOKIE['active_session_id'])) {
        $savedSessionId = $_COOKIE['active_session_id'];
    }
    
    if (!empty($savedSessionId)) {
        $state = getQueueState($queueFile);
        $isActive = false;
        foreach ($state['queue_list'] as $item) {
            if ($item['session_id'] === $savedSessionId) {
                if (in_array($item['status'], ['WAITING', 'ACTIVE', 'CAPTURING'])) {
                    $isActive = true;
                }
                break;
            }
        }
        
        if ($isActive) {
            // Redirect back to active session
            header("Location: order.php?session_id=" . urlencode($savedSessionId));
            exit;
        } else {
            // Clear expired/finished session from memory
            unset($_SESSION['active_session_id']);
            setcookie('active_session_id', '', time() - 3600, '/');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isRemoteMode ? 'Remote Controller' : 'Pesan Paket Photobooth'; ?> - Jeprat-jepret</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #0c0c0f;
            --card-bg: #14141a;
            --primary-red: #e63946;
            --primary-accent: #ff4b4b;
            --text-main: #f8f9fa;
            --text-muted: #8d8d9f;
            --border-color: #22222a;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
        }

        .header {
            width: 100%;
            max-width: 480px;
            text-align: center;
            margin-bottom: 28px;
            margin-top: 15px;
        }

        .logo {
            font-weight: 800;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
        }
        .logo span { color: var(--primary-red); }

        .subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 6px;
        }

        /* CATALOG SCREEN STYLES */
        .catalog-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .catalog-container form {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
        }

        /* ─── PACKAGE SELECTOR ROW ─── */
        .package-selector-row {
            display: flex;
            gap: 12px;
            width: 100%;
        }

        .package-select-card {
            flex: 1;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 24px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .package-select-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top right, rgba(255, 75, 75, 0.12), transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .package-select-card::after {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.95rem;
            color: var(--primary-accent);
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .package-select-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 75, 75, 0.3);
            background: rgba(255, 255, 255, 0.04);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .package-select-card.active {
            border-color: var(--primary-accent);
            background: rgba(255, 77, 77, 0.06);
            box-shadow: 0 0 20px rgba(255, 75, 75, 0.2), inset 0 0 10px rgba(255, 75, 75, 0.15);
            transform: scale(1.02);
        }
        
        .package-select-card.active::before,
        .package-select-card.active::after {
            opacity: 1;
        }

        .package-select-icon {
            font-size: 2.2rem;
            color: var(--text-muted);
            opacity: 0.7;
            margin-bottom: 4px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .package-select-card.active .package-select-icon {
            color: var(--primary-accent);
            opacity: 1;
            transform: scale(1.15) rotate(-3deg);
            filter: drop-shadow(0 0 8px rgba(255, 75, 75, 0.4));
        }

        .package-select-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-muted);
            line-height: 1.2;
            transition: color 0.25s ease;
        }
        
        .package-select-card.active .package-select-name {
            color: var(--text-main);
            font-weight: 700;
        }

        .package-select-price {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            opacity: 0.8;
            transition: all 0.25s ease;
        }
        
        .package-select-card.active .package-select-price {
            color: var(--primary-accent);
            opacity: 1;
            font-weight: 800;
        }

        /* ─── DETAILS PANEL ─── */
        .details-panel {
            background: rgba(20, 20, 26, 0.7);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            border-radius: 18px;
            padding: 24px 20px;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 100%;
        }

        .details-placeholder {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-align: center;
            font-style: italic;
        }

        .package-details-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
            animation: fadeInDetails 0.3s ease forwards;
        }

        @keyframes fadeInDetails {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .details-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 8px;
            text-align: left;
            margin-bottom: 8px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 8px;
        }
        
        @media(min-width: 380px) {
            .details-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px 16px;
            }
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 4px 0;
        }

        .detail-item.active {
            color: #10b981;
        }

        .detail-item.inactive {
            color: rgba(255,255,255,0.15);
            text-decoration: line-through;
        }

        .detail-icon {
            font-size: 1.15rem;
            font-weight: 800;
        }

        /* ─── BTN ORDER ─── */
        .btn-order {
            background: linear-gradient(135deg, var(--primary-accent), var(--primary-red));
            color: white;
            font-size: 1.2rem;
            font-weight: 800;
            padding: 18px;
            border-radius: 16px;
            border: none;
            cursor: pointer;
            width: 100%;
            font-family: inherit;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(255, 75, 75, 0.25);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-order:hover:not(:disabled) {
            box-shadow: 0 6px 20px rgba(255, 75, 75, 0.4);
            transform: translateY(-1px);
        }

        .package-card.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        .package-card.disabled:hover {
            transform: none;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        .disabled-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* REMOTE SCREEN STYLES */
        .remote-container {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .glass-card {
            background-color: rgba(20, 20, 26, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 24px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            transition: all 0.3s ease;
        }

        .queue-badge {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary-red);
            background-color: rgba(230,57,70,0.08);
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(230,57,70,0.2);
            box-shadow: 0 0 20px rgba(230,57,70,0.1);
            transition: all 0.3s ease;
        }

        .status-text-container {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
            width: 100%;
            transition: all 0.3s ease;
        }

        /* Compact Status Card when Active/Capturing */
        .glass-card.compact {
            flex-direction: row;
            text-align: left;
            padding: 12px 18px;
            gap: 16px;
            align-items: center;
            border-radius: 18px;
        }

        .glass-card.compact .queue-badge {
            width: 54px;
            height: 54px;
            font-size: 1.6rem;
            flex-shrink: 0;
            box-shadow: 0 0 10px rgba(230,57,70,0.1);
        }

        .glass-card.compact .status-text-container {
            align-items: flex-start;
            gap: 2px;
        }

        .glass-card.compact .waiting-status {
            font-size: 1rem;
            text-align: left;
        }

        .glass-card.compact .waiting-desc {
            font-size: 0.75rem;
            line-height: 1.3;
            text-align: left;
        }

        .waiting-status {
            font-size: 1.15rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
        }

        .waiting-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
            transition: all 0.3s ease;
        }

        /* Layout & Frame Selection UI */
        .selector-box {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .selector-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Layout Selection Cards */
        .layout-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            width: 100%;
            margin-top: 10px;
        }

        .layout-card-item {
            background-color: #0c0c0f;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 14px 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .layout-card-item:hover {
            border-color: var(--primary-red);
            background-color: rgba(230, 57, 70, 0.04);
            transform: translateY(-2px);
        }

        .layout-card-icon {
            width: 48px;
            height: 48px;
            background-color: #14141a;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
            transition: all 0.2s;
        }

        .layout-card-item:hover .layout-card-icon {
            border-color: var(--primary-red);
        }

        /* Strip Icon Preview */
        .strip-preview {
            flex-direction: column;
            gap: 3px;
        }
        .strip-preview .line {
            width: 22px;
            height: 5px;
            background-color: var(--text-muted);
            border-radius: 1px;
            opacity: 0.6;
        }
        .layout-card-item:hover .strip-preview .line {
            background-color: var(--primary-red);
            opacity: 1;
        }

        /* Grid Icon Preview */
        .grid-preview {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
        }
        .grid-preview .box {
            width: 14px;
            height: 14px;
            background-color: var(--text-muted);
            border-radius: 2px;
            opacity: 0.6;
        }
        .layout-card-item:hover .grid-preview .box {
            background-color: var(--primary-red);
            opacity: 1;
        }

        /* Postcard Icon Preview */
        .postcard-preview {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .postcard-preview .full-box {
            width: 32px;
            height: 22px;
            background-color: var(--text-muted);
            border-radius: 2px;
            opacity: 0.6;
        }
        .layout-card-item:hover .postcard-preview .full-box {
            background-color: var(--primary-red);
            opacity: 1;
        }

        .layout-card-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Step Header */
        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 4px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }

        .btn-back-step {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-back-step:hover {
            border-color: var(--text-main);
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .selected-layout-badge {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .selected-layout-badge span {
            color: var(--primary-accent);
            background-color: rgba(255, 77, 77, 0.1);
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid rgba(255, 77, 77, 0.2);
        }

        .frame-scroll-select {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding: 12px 6px 20px 6px;
            width: 100%;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .frame-scroll-select::-webkit-scrollbar {
            height: 6px;
        }
        .frame-scroll-select::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        .frame-scroll-select::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }
        .frame-scroll-select::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 77, 77, 0.5);
        }

        .frame-item-card {
            background-color: #0c0c0f;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
            overflow: visible;
        }

        .frame-item-card.layout-strip {
            width: 120px;
            height: 280px;
        }

        .frame-item-card.layout-grid {
            width: 150px;
            height: 240px;
        }

        .frame-item-card.layout-postcard {
            width: 180px;
            height: 200px;
        }

        .frame-item-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .frame-item-card.active {
            border-color: var(--primary-accent);
            background-color: rgba(255, 77, 77, 0.04);
            box-shadow: 0 8px 24px rgba(255, 77, 77, 0.15);
            transform: scale(1.02);
        }

        .frame-item-preview {
            flex: 1;
            width: 100%;
            height: 0;
            min-height: 0;
            overflow: visible;
            background-color: transparent;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .frame-item-card.active .frame-item-preview {
            transform: translateY(-2px);
        }
        
        
        .frame-item-preview img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            position: relative;
            z-index: 2;
            transition: all 0.25s ease;
            filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.4));
        }

        .frame-item-name {
            font-size: 0.75rem;
            text-align: center;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-muted);
            width: 100%;
            padding: 2px 0 0 0;
            transition: all 0.2s;
        }

        .frame-item-card:hover .frame-item-name {
            color: var(--text-main);
        }

        .frame-item-card.active .frame-item-name {
            color: var(--primary-accent);
            font-weight: 700;
        }

        .frame-item-card.active:hover .frame-item-name {
            color: var(--primary-accent);
        }

        /* Glowing red capture button */
        .btn-capture-glowing {
            background: linear-gradient(135deg, var(--primary-red), var(--primary-accent));
            color: white;
            font-size: 1.25rem;
            font-weight: 900;
            padding: 20px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0.4);
            animation: pulseCapture 2s infinite cubic-bezier(0.66, 0, 0, 1);
            width: 100%;
            margin-top: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        @keyframes pulseCapture {
            0% { transform: scale(0.98); box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0.4); }
            70% { transform: scale(1.02); box-shadow: 0 15px 35px rgba(230, 57, 70, 0.5), 0 0 0 15px rgba(230, 57, 70, 0); }
            100% { transform: scale(0.98); box-shadow: 0 10px 25px rgba(230, 57, 70, 0.4), 0 0 0 0px rgba(230, 57, 70, 0); }
        }

        .btn-capture-glowing:disabled {
            background: #2a2a35;
            box-shadow: none;
            animation: none;
            color: var(--text-muted);
            cursor: not-allowed;
        }
        .category-tab-container {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 4px 0 12px 0;
            width: 100%;
            margin-bottom: 12px;
            scrollbar-width: none;
        }
        .category-tab-container::-webkit-scrollbar {
            display: none;
        }
        .category-tab-btn {
            padding: 8px 16px;
            background: #15151e;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .category-tab-btn.active {
            background: var(--primary-accent);
            color: #fff;
            border-color: var(--primary-accent);
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">Creative<span>Studio</span></div>
        <div class="subtitle">Kiosk Self-Service Portal</div>
    </div>

    <?php if (!$isRemoteMode): ?>
        <!-- CATALOG SCREEN -->
        <div class="catalog-container">
            

            <div class="section-title" style="margin-top: 10px;">Pilih Paket Foto:</div>
            
            <form action="order.php" method="POST">
                <input type="hidden" name="action" value="create_order">
                <input type="hidden" id="selectedPackageInput" name="package_id" value="">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($eventId); ?>">
                
                <!-- Package Selector Row -->
                <div class="package-selector-row">
                    <?php foreach ($packages as $pkg): 
                        $flow = isset($pkg['print_flow']) ? $pkg['print_flow'] : '';
                        $isAvailable = true;
                        if (($flow === 'COLOR_PRINT' || $flow === 'ID_CARD') && !$colorActive) {
                            $isAvailable = false;
                        } elseif ($flow === 'RECEIPT' && !$thermalActive) {
                            $isAvailable = false;
                        }
                        
                        // Filter by event allowed_packages
                        if ($resolvedEvent && isset($resolvedEvent['allowed_packages']) && is_array($resolvedEvent['allowed_packages']) && !empty($resolvedEvent['allowed_packages'])) {
                            if (!in_array($pkg['id'], $resolvedEvent['allowed_packages'])) {
                                $isAvailable = false;
                            }
                        }
                        
                        if (!$isAvailable) {
                            continue;
                        }
                    ?>
                        <div class="package-select-card" data-package-id="<?php echo htmlspecialchars($pkg['id']); ?>" onclick="selectPackage('<?php echo htmlspecialchars($pkg['id']); ?>')">
                            <div class="package-select-icon">
                                <?php 
                                    if ($flow === 'RECEIPT') echo '<i class="fa-solid fa-print"></i>';
                                    elseif ($flow === 'COLOR_PRINT') echo '<i class="fa-solid fa-palette"></i>';
                                    else echo '<i class="fa-solid fa-id-card"></i>'; 
                                ?>
                            </div>
                            <div class="package-select-name"><?php echo htmlspecialchars($pkg['name']); ?></div>
                            <div class="package-select-price">
                                <?php if ($isFreeActive): ?>
                                    <span style="color: #10b981;">GRATIS</span>
                                <?php else: ?>
                                    Rp <?php echo number_format($pkg['price'], 0, ',', '.'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dynamic Details Panel -->
                <div class="details-panel" id="detailsPanel">
                    <div class="details-placeholder" id="detailsPlaceholder">
                        Silakan pilih paket foto di atas untuk melihat detail fitur.
                    </div>
                    
                    <?php foreach ($packages as $pkg): 
                        $flow = isset($pkg['print_flow']) ? $pkg['print_flow'] : '';
                        $isAvailable = true;
                        if (($flow === 'COLOR_PRINT' || $flow === 'ID_CARD') && !$colorActive) {
                            $isAvailable = false;
                        } elseif ($flow === 'RECEIPT' && !$thermalActive) {
                            $isAvailable = false;
                        }
                        
                        // Filter by event allowed_packages
                        if ($resolvedEvent && isset($resolvedEvent['allowed_packages']) && is_array($resolvedEvent['allowed_packages']) && !empty($resolvedEvent['allowed_packages'])) {
                            if (!in_array($pkg['id'], $resolvedEvent['allowed_packages'])) {
                                $isAvailable = false;
                            }
                        }
                        
                        if (!$isAvailable) {
                            continue;
                        }
                    ?>
                        <div class="package-details-content" id="details-<?php echo htmlspecialchars($pkg['id']); ?>" style="display: none;">
                            <div class="details-title">Fitur Paket <?php echo htmlspecialchars($pkg['name']); ?>:</div>
                            <div class="details-grid">
                                <div class="detail-item <?php echo $pkg['features']['print']?'active':'inactive'; ?>">
                                    <span class="detail-icon">
                                        <?php echo $pkg['features']['print'] ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>'; ?>
                                    </span>
                                    <span>Cetak Struk Fisik</span>
                                </div>
                                <div class="detail-item <?php echo $pkg['features']['download']?'active':'inactive'; ?>">
                                    <span class="detail-icon">
                                        <?php echo $pkg['features']['download'] ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>'; ?>
                                    </span>
                                    <span>Download Foto Strip</span>
                                </div>
                                <div class="detail-item <?php echo $pkg['features']['gif']?'active':'inactive'; ?>">
                                    <span class="detail-icon">
                                        <?php echo $pkg['features']['gif'] ? '<i class="fa-solid fa-circle-check"></i>' : '<i class="fa-solid fa-circle-xmark"></i>'; ?>
                                    </span>
                                    <span>Live Animated GIF</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="submit" class="btn-order" id="btnOrderSubmit" disabled>PESAN SEKARANG</button>
            </form>
        </div>

        <script>
            function selectPackage(id) {
                // Update hidden input
                document.getElementById('selectedPackageInput').value = id;
                
                // Toggle active card styling
                document.querySelectorAll('.package-select-card').forEach(card => {
                    if (card.getAttribute('data-package-id') === id) {
                        card.classList.add('active');
                    } else {
                        card.classList.remove('active');
                    }
                });
                
                // Hide placeholder
                const placeholder = document.getElementById('detailsPlaceholder');
                if (placeholder) placeholder.style.display = 'none';
                
                // Toggle details content visibility
                document.querySelectorAll('.package-details-content').forEach(detail => {
                    if (detail.id === 'details-' + id) {
                        detail.style.display = 'flex';
                    } else {
                        detail.style.display = 'none';
                    }
                });
                
                // Enable button
                document.getElementById('btnOrderSubmit').disabled = false;
            }

            // Auto-select first package on load
            window.addEventListener('DOMContentLoaded', () => {
                const firstCard = document.querySelector('.package-select-card');
                if (firstCard) {
                    firstCard.click();
                }
            });
        </script>

    <?php else: ?>
        <!-- REMOTE CONTROLLER & QUEUE SCREEN -->
        <div class="remote-container">
            
            <!-- Dynamic state block populated by JS polling -->
            <div class="glass-card" id="remoteStatusCard">
                <div class="queue-badge" id="queueNumBadge">-</div>
                <div class="status-text-container">
                    <div class="waiting-status" id="queueStatusTitle">Memuat status antrean...</div>
                    <div class="waiting-desc" id="queueStatusDesc">Menghubungkan ke sistem antrean Kiosk. Silakan tunggu sebentar.</div>
                </div>
            </div>

            <!-- Controller Selection Box (Visible only when ACTIVE) -->
            <div class="glass-card" id="remoteControllerBox" style="display:none;">
                <!-- DIRECT FRAME SELECTION -->
                <div class="selector-box" id="frameStepContainer" style="display:none; width: 100%;">
                    <span class="selector-label" style="margin-bottom: 12px;">Pilih Bingkai Foto</span>
                    
                    <!-- Kategori Tab Container -->
                    <div class="category-tab-container" id="remoteCategoryTabs">
                        <!-- Loaded dynamically via JS -->
                    </div>
                    
                    <div class="frame-scroll-select" id="remoteFrameList">
                        <!-- Loaded dynamically via JS -->
                    </div>
                    
                    <button class="btn-capture-glowing" id="btnCaptureStart" onclick="sendCaptureCommand()">
                        MULAI FOTO
                    </button>
                </div>
            </div>

        </div>

        <script>
            const sessionId = '<?php echo $sessionId; ?>';
            const serverConfig = <?php echo json_encode($config); ?>;
            const activeEventId = '<?php echo htmlspecialchars($eventId ?? ""); ?>';
            const activePrintFlow = '<?php echo $activePrintFlow; ?>';
            
            let currentStatus = 'WAITING';
            let selectedLayout = 'strip';
            let selectedFrameId = '';
            
            // TTS countdown assistant offline Indonesian
            function speakAssistiveCue(text) {
                if ('speechSynthesis' in window) {
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'id-ID';
                    window.speechSynthesis.speak(utterance);
                }
            }

            // Start polling status
            function pollQueueStatus() {
                fetch('kiosk_control.php?action=check_queue&session_id=' + sessionId)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            currentStatus = data.status;
                            document.getElementById('queueNumBadge').innerText = '#' + data.queue_number;
                            
                            const title = document.getElementById('queueStatusTitle');
                            const desc = document.getElementById('queueStatusDesc');
                            const controller = document.getElementById('remoteControllerBox');
                            const statusCard = document.getElementById('remoteStatusCard');
                            
                            const btnCapture = document.getElementById('btnCaptureStart');

                            if (data.status === 'WAITING') {
                                title.innerText = 'Menunggu Giliran Anda...';
                                desc.innerHTML = `Antrean Aktif Kiosk saat ini: <b>#${data.active_queue_number}</b>.<br>Ada <b>${data.total_waiting} orang</b> lagi di depan Anda.`;
                                controller.style.display = 'flex';
                                statusCard.classList.add('compact');
                                
                                if (btnCapture) {
                                    btnCapture.disabled = true;
                                    btnCapture.innerText = 'MENUNGGU GILIRAN ANDA...';
                                }
                                
                                if (document.getElementById('frameStepContainer').style.display !== 'block') {
                                    document.getElementById('frameStepContainer').style.display = 'block';
                                    loadFramesList();
                                }
                            } 
                            else if (data.status === 'ACTIVE') {
                                title.innerText = 'GILIRAN ANDA AKTIF';
                                desc.innerHTML = 'Silakan pilih bingkai foto Anda untuk memulai!';
                                controller.style.display = 'flex';
                                statusCard.classList.add('compact');
                                
                                if (btnCapture) {
                                    btnCapture.disabled = false;
                                    btnCapture.innerText = 'MULAI FOTO';
                                }
                                
                                if (document.getElementById('frameStepContainer').style.display !== 'block') {
                                    document.getElementById('frameStepContainer').style.display = 'block';
                                    loadFramesList();
                                }
                            }
                            else if (data.status === 'CAPTURING') {
                                title.innerText = 'PROSES MEMOTRET...';
                                desc.innerHTML = 'Kamera depan tablet sedang aktif mengambil pose Anda. Bersiaplah berpose di depan Kiosk!';
                                controller.style.display = 'none';
                                statusCard.classList.add('compact');
                            }
                            else if (data.status === 'FINISHED') {
                                title.innerText = 'SESI FOTO SELESAI';
                                desc.innerHTML = 'Foto Anda sedang diproses. Menuju halaman portal unduhan...';
                                controller.style.display = 'none';
                                statusCard.classList.remove('compact');
                                
                                speakAssistiveCue("Sesi foto selesai. Terima kasih!");
                                
                                // Redirect to index.php
                                setTimeout(() => {
                                    window.location.href = 'index.php?id=' + sessionId;
                                }, 2000);
                            }
                        }
                    })
                    .catch(err => console.error("Polling error", err));
            }

            let selectedCategory = 'Semua';

            function renderCategoryTabs(frames) {
                const container = document.getElementById('remoteCategoryTabs');
                if (!container) return;
                
                const categories = ['Semua'];
                frames.forEach(f => {
                    const cat = f.category || 'Classic';
                    if (!categories.includes(cat)) {
                        categories.push(cat);
                    }
                });
                
                container.innerHTML = '';
                if (categories.length <= 1) {
                    container.style.display = 'none';
                    return;
                }
                container.style.display = 'flex';
                
                categories.forEach(cat => {
                    const btn = document.createElement('button');
                    btn.className = 'category-tab-btn' + (cat === selectedCategory ? ' active' : '');
                    btn.innerText = cat;
                    btn.onclick = () => {
                        selectedCategory = cat;
                        document.querySelectorAll('.category-tab-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        renderFramesGrid(frames);
                    };
                    container.appendChild(btn);
                });
            }

            function getCompatibleFrames() {
                if (!serverConfig.frames) return [];
                
                return serverConfig.frames.filter(f => {
                    let pf = f.print_flows || [];
                    if (pf.length === 0) {
                        if (f.type === 'strip') pf = ['RECEIPT', 'COLOR_PRINT'];
                        else if (f.type === 'grid' || f.type === 'postcard') pf = ['COLOR_PRINT'];
                    }
                    
                    if (activePrintFlow) {
                        return pf.includes(activePrintFlow);
                    }
                    return true;
                });
            }

            // Load frames matching active event ID or general fallback
            function loadFramesList() {
                const compatible = getCompatibleFrames();
                
                // Load frames based on allowed_frames or fallback to event matching / general
                let filtered = [];
                let activeEvtObj = serverConfig.events ? serverConfig.events.find(e => e.id === activeEventId) : null;
                if (activeEvtObj && activeEvtObj.allowed_frames && Array.isArray(activeEvtObj.allowed_frames)) {
                    filtered = compatible.filter(f => activeEvtObj.allowed_frames.includes(f.id));
                } else {
                    filtered = compatible.filter(f => f.event_id === activeEventId);
                    if (filtered.length === 0) {
                        filtered = compatible.filter(f => f.event_id === 'general' || !f.event_id);
                    }
                }
                
                renderCategoryTabs(filtered);
                renderFramesGrid(filtered);
            }

            function renderFramesGrid(frames) {
                const list = document.getElementById('remoteFrameList');
                list.innerHTML = "";
                
                let toRender = frames;
                if (selectedCategory !== 'Semua') {
                    toRender = frames.filter(f => (f.category || 'Classic') === selectedCategory);
                }
                
                if (toRender.length === 0) {
                    list.innerHTML = "<div style='color: var(--text-muted); padding: 20px; text-align: center; width: 100%;'>Tidak ada bingkai dalam kategori ini.</div>";
                    return;
                }
                
                toRender.forEach((f, idx) => {
                    const card = document.createElement('div');
                    card.className = 'frame-item-card layout-' + f.type + ' ' + (idx === 0 ? 'active' : '');
                    if (idx === 0) {
                        selectedFrameId = f.id;
                        selectedLayout = f.type;
                    }
                    
                    card.onclick = () => {
                        document.querySelectorAll('.frame-item-card').forEach(c => c.classList.remove('active'));
                        card.classList.add('active');
                        selectedFrameId = f.id;
                        selectedLayout = f.type;
                    };
                    
                    card.innerHTML = `
                        <div class="frame-item-preview layout-${f.type}">
                            <img src="../${f.image_url}" onerror="this.src='https://placehold.co/50x120/121212/ffffff?text=${encodeURIComponent(f.name)}'">
                        </div>
                        <div class="frame-item-name">${f.name}</div>
                    `;
                    list.appendChild(card);
                });
            }

            function sendCaptureCommand() {
                if (!selectedFrameId) return;
                
                document.getElementById('btnCaptureStart').disabled = true;
                document.getElementById('btnCaptureStart').innerText = "MENYIAPKAN KAMERA...";
                
                const formData = new FormData();
                formData.append('session_id', sessionId);
                formData.append('command', 'START_CAPTURE');
                formData.append('frame_id', selectedFrameId);
                formData.append('layout', selectedLayout);
                
                fetch('kiosk_control.php?action=send_command', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Play local sync countdown audio cue simulation
                        speakAssistiveCue("Mempersiapkan kamera. Bersiaplah untuk pose pertama.");
                        setTimeout(() => { playSyncCountdown(); }, 2500);
                    } else {
                        alert("Gagal: " + data.message);
                        document.getElementById('btnCaptureStart').disabled = false;
                        document.getElementById('btnCaptureStart').innerText = "MULAI FOTO";
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('btnCaptureStart').disabled = false;
                });
            }

            function playSyncCountdown() {
                let slotsCount = selectedLayout === 'postcard' ? 1 : 4;
                let pose = 1;
                
                function runVoicePrompt() {
                    if (pose > slotsCount) return;
                    
                    speakAssistiveCue("Pose ke " + pose);
                    setTimeout(() => { speakAssistiveCue("Tiga"); }, 1500);
                    setTimeout(() => { speakAssistiveCue("Dua"); }, 2500);
                    setTimeout(() => { speakAssistiveCue("Satu"); }, 3500);
                    setTimeout(() => { speakAssistiveCue("Senyum"); }, 4500);
                    
                    // Trigger next pose sync offset (~6s total loop)
                    setTimeout(() => {
                        pose++;
                        runVoicePrompt();
                    }, 6500);
                }
                
                runVoicePrompt();
            }

            // Poll every 1s
            setInterval(pollQueueStatus, 1000);
            pollQueueStatus();
        </script>
    <?php endif; ?>
</body>
</html>
