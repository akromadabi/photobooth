<?php
session_start();
$configPath = __DIR__ . '/frames/config.json';
$configData = ['events' => [], 'frames' => []];
if (file_exists($configPath)) {
    $configData = json_decode(file_get_contents($configPath), true);
}

$packagesFile = __DIR__ . '/packages.json';
$packagesList = [];
if (file_exists($packagesFile)) {
    $packagesList = json_decode(file_get_contents($packagesFile), true);
}

// Load settings for theme configuration
$settingsFile = __DIR__ . '/settings.json';
$settings = [
    "app_theme" => "NEON_RED"
];
if (file_exists($settingsFile)) {
    $settingsData = json_decode(file_get_contents($settingsFile), true);
    if (is_array($settingsData)) {
        $settings = array_merge($settings, $settingsData);
    }
}
$appTheme = isset($settings['app_theme']) ? $settings['app_theme'] : 'NEON_RED';

// Resolve general brand details in PHP for initial HTML load
$neutralName = 'Jeprat Jepret';
$neutralSlogan = 'All You need is special';

$brandName = $neutralName;
$brandSlogan = $neutralSlogan;

if (!empty($configData['events'])) {
    foreach ($configData['events'] as $evt) {
        if ($evt['id'] === 'general' && !empty($evt['name']) && $evt['name'] !== 'Umum (Default)') {
            $brandName = $evt['name'];
        }
    }
}

$brandNameWords = explode(' ', $brandName);
$dagoText1 = $brandNameWords[0] ?? $neutralName;
$dagoText2 = implode(' ', array_slice($brandNameWords, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk App Web Simulator - Jeprat-jepret</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&family=Fredoka:wght@400;600;700&family=Playfair+Display:ital,wght@0,600;0,800;1,400&family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        :root {
            --bg-color: #0c0c0f;
            --sidebar-bg: #14141a;
            --tablet-bezel: #222228;
            --primary-red: #e63946;
            --primary-gold: #f7b801;
            --text-main: #f8f9fa;
            --text-muted: #8d8d9f;
            --border-color: #2a2a35;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Layout Grid */
        .wrapper {
            display: flex;
            width: 100vw;
            height: 100vh;
        }

        /* Sidebar Control Panel */
        .control-panel {
            width: 320px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow-y: auto;
            flex-shrink: 0;
        }

        .panel-title {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }
        .panel-title span { color: var(--primary-red); }

        .section-box {
            background-color: #0c0c0f;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        select, input, button.btn-panel {
            width: 100%;
            padding: 10px 12px;
            background-color: #14141a;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: white;
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.25s;
        }

        select:focus, input:focus {
            border-color: var(--primary-red);
        }

        button.btn-panel {
            background-color: var(--primary-red);
            border: none;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(230, 57, 70, 0.2);
        }

        button.btn-panel:hover {
            background-color: #d62d3a;
            transform: translateY(-1px);
        }

        .kiosk-mode-selector {
            display: flex;
            gap: 10px;
        }

        .mode-btn {
            flex: 1;
            padding: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: #14141a;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .mode-btn.active {
            background-color: var(--primary-red);
            border-color: var(--primary-red);
            color: white;
        }

        /* Simulator Container - Visual Tablet Bezel */
        .simulator-area {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #08080a;
            position: relative;
            padding: 20px;
        }

        .tablet-device {
            width: 1024px;
            height: 600px;
            background-color: var(--tablet-bezel);
            border-radius: 36px;
            padding: 20px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.8), inset 0 2px 8px rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            border: 4px solid #33333d;
            position: relative;
        }

        /* Camera lens indicator inside tablet bezel */
        .tablet-lens {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background-color: #111;
            border-radius: 50%;
            border: 1px solid #444;
        }

        .screen-container {
            flex: 1;
            background-color: #0f0f12;
            border-radius: 18px;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            border: 1px solid #1a1a24;
        }

        /* Simulated Status Bar */
        .status-bar {
            height: 24px;
            background-color: rgba(0,0,0,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 16px;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.03);
            pointer-events: none;
        }

        .status-bar-icons {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* General Kiosk Screen Layouts */
        .kiosk-screen {
            flex: 1;
            position: relative;
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .kiosk-screen.active {
            display: flex;
            flex-direction: column;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        /* 1. HOME SCREEN */
        .screen-home {
            background-color: var(--primary-red);
            padding: 40px;
            position: relative;
            justify-content: space-between;
        }

        .home-logo-box {
            display: flex;
            flex-direction: column;
            gap: 2px;
            z-index: 10;
        }

        .home-logo-part1 {
            font-size: 2.2rem;
            font-weight: 900;
            line-height: 1;
            color: white;
            opacity: 0;
        }

        .home-logo-part2 {
            font-size: 2.2rem;
            font-weight: 300;
            line-height: 1;
            color: white;
            opacity: 0;
        }

        /* 1.1 Entrance Animations when active */
        .screen-home.active .home-logo-part1 {
            animation: revealLogoPart1 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .screen-home.active .home-logo-part2 {
            animation: revealLogoPart2 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            animation-delay: 0.12s;
        }

        @keyframes revealLogoPart1 {
            0% { transform: translateY(40px) scale(0.95); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        @keyframes revealLogoPart2 {
            0% { transform: translateY(40px) scale(0.95); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        .home-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            z-index: 10;
            align-self: center;
            margin-top: 40px;
        }

        .btn-start {
            background-color: white;
            color: var(--primary-red);
            font-size: 1.5rem;
            font-weight: 900;
            padding: 16px 48px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            letter-spacing: 1px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            opacity: 0;
        }

        .screen-home.active .btn-start {
            animation: revealStartBtn 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards, pulseBtn 2s infinite ease-in-out 1.2s;
            animation-delay: 0.35s, 1.35s;
        }

        .btn-start:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.35);
        }

        .btn-start:active {
            transform: scale(0.95);
        }

        @keyframes revealStartBtn {
            0% { transform: scale(0.7) translateY(30px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        @keyframes pulseBtn {
            0% { transform: scale(0.96); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
            50% { transform: scale(1.04); box-shadow: 0 15px 40px rgba(0,0,0,0.35); }
            100% { transform: scale(0.96); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        }

        .home-slogan {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0;
        }

        .screen-home.active .home-slogan {
            animation: revealSlogan 1s cubic-bezier(0.16, 1, 0.3, 1) forwards, floatText 4s infinite ease-in-out 1.4s;
            animation-delay: 0.5s, 1.5s;
        }

        @keyframes revealSlogan {
            0% { transform: translateY(20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        @keyframes floatText {
            0% { transform: translateY(-3px); }
            50% { transform: translateY(3px); }
            100% { transform: translateY(-3px); }
        }

        /* Tilted Scrolling History Strip (Home Screen) */
        .home-strip-container {
            position: absolute;
            right: -15px;
            top: -280px;
            width: 140px;
            height: 1500px;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.45);
            transform: rotate(-24deg);
            z-index: 5;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 8px;
            gap: 12px;
            opacity: 0;
        }

        .screen-home.active .home-strip-container {
            animation: revealStrip 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            animation-delay: 0.15s;
        }

        @keyframes revealStrip {
            0% { transform: translate(120px, -120px) rotate(-24deg); opacity: 0; }
            100% { transform: translate(0, 0) rotate(-24deg); opacity: 1; }
        }

        .scrolling-strip {
            display: flex;
            flex-direction: column;
            gap: 12px;
            animation: scrollStrip 25s linear infinite;
        }

        .strip-pic {
            width: 100%;
            height: 396px;
            border-radius: 8px;
            background-color: white;
            background-image: 
                linear-gradient(to bottom, #dcdcdc 0%, #dcdcdc 100%),
                linear-gradient(to bottom, #dcdcdc 0%, #dcdcdc 100%),
                linear-gradient(to bottom, #dcdcdc 0%, #dcdcdc 100%),
                linear-gradient(to bottom, #dcdcdc 0%, #dcdcdc 100%);
            background-size: 
                calc(100% - 20px) 76px,
                calc(100% - 20px) 76px,
                calc(100% - 20px) 76px,
                calc(100% - 20px) 76px;
            background-position: 
                10px 12px,
                10px 98px,
                10px 184px,
                10px 270px;
            background-repeat: no-repeat;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        @keyframes scrollStrip {
            0% { transform: translateY(0); }
            100% { transform: translateY(-50%); }
        }

        /* Floating Ticket Launcher (Home Screen Scenario B) */
        .ticket-launcher {
            position: absolute;
            left: 30px;
            bottom: 30px;
            background-color: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            transition: all 0.25s;
            opacity: 0;
        }

        .screen-home.active .ticket-launcher {
            animation: revealTicketLauncher 1s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            animation-delay: 0.7s;
        }

        @keyframes revealTicketLauncher {
            0% { transform: scale(0) rotate(-180deg); opacity: 0; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
        }

        .ticket-launcher:hover {
            transform: scale(1.1) translateY(-2px);
            background-color: rgba(255,255,255,0.25);
        }

        .ticket-icon { font-size: 1.3rem; }

        /* 2. LAYOUT SELECT SCREEN */
        .screen-header-back {
            height: 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid var(--border-color);
            background-color: #14141a;
        }

        .btn-back {
            background: none;
            border: none;
            color: var(--primary-red);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .screen-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .layout-options {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            gap: 30px;
        }

        .layout-card {
            flex: 1;
            max-width: 220px;
            background-color: var(--sidebar-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            transition: all 0.25s;
        }

        .layout-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(230,57,70,0.1);
        }

        .layout-icon {
            font-size: 2.2rem;
            background-color: rgba(230,57,70,0.08);
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-red);
        }

        .layout-name {
            font-size: 1rem;
            font-weight: 700;
        }

        .layout-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.4;
        }

        /* 3. FRAME SELECT SCREEN */
        .frame-selector-scroll {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .frame-card {
            width: 140px;
            height: 85%;
            max-height: 380px;
            background-color: transparent;
            border: none;
            border-radius: 14px;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            flex-shrink: 0;
            position: relative;
            overflow: visible;
        }

        .frame-card:hover {
            transform: scale(1.05) translateY(-4px);
        }

        .frame-card-preview {
            flex: 1;
            width: 100%;
            height: 0;
            min-height: 0;
            border-radius: 8px;
            overflow: visible;
            display: flex;
            justify-content: center;
            position: relative;
            background-color: transparent;
            border: none;
        }

        .frame-card-preview img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.4));
            transition: transform 0.25s ease;
        }

        .frame-card-name {
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-event-tag {
            position: absolute;
            top: 14px;
            right: 14px;
            background-color: var(--primary-gold);
            color: black;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* 4. CAMERA CAPTURE SCREEN */
        .camera-screen-layout {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 200px;
        }

        .camera-preview-container {
            background-color: black;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .camera-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Mirror camera */
        }

        .mock-camera-placeholder {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-muted);
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Circular blinking camera rec icon */
        .camera-rec-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background-color: rgba(0,0,0,0.6);
            padding: 6px 12px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .rec-dot {
            width: 8px;
            height: 8px;
            background-color: var(--primary-red);
            border-radius: 50%;
            animation: blinkRec 1s infinite alternate;
        }

        @keyframes blinkRec {
            from { opacity: 0.3; }
            to { opacity: 1; }
        }

        /* Rental timer badge floating globally in the corner */
        .rental-timer-badge {
            position: absolute;
            bottom: 12px;
            right: 12px;
            z-index: 999;
            background: rgba(15, 15, 20, 0.75);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            max-width: fit-content;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            pointer-events: none;
            font-family: 'Outfit', sans-serif;
            transition: all 0.3s ease;
        }
        
        .rental-timer-badge i {
            color: #ff3366;
            animation: pulse-timer-spin 2.5s infinite linear;
        }

        @keyframes pulse-timer-spin {
            0% { transform: scale(1) rotate(0deg); opacity: 0.8; }
            50% { transform: scale(1.15) rotate(180deg); opacity: 1; }
            100% { transform: scale(1) rotate(360deg); opacity: 0.8; }
        }

        /* Capture overlay countdown */
        .countdown-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.2);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 30;
        }

        .countdown-number {
            font-size: 8rem;
            font-weight: 900;
            color: white;
            text-shadow: 0 10px 40px rgba(0,0,0,0.8);
            animation: popCountdown 1s infinite ease-out;
        }

        @keyframes popCountdown {
            0% { transform: scale(0.6); opacity: 0; }
            30% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(0.9); opacity: 1; }
        }

        /* Camera Capture Slots Bar (Right side panel) */
        .camera-slots-bar {
            background-color: var(--sidebar-bg);
            border-left: 1px solid var(--border-color);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
            align-items: center;
        }

        .capture-slot-box {
            width: 130px;
            height: 97px;
            border-radius: 10px;
            border: 2px dashed var(--border-color);
            background-color: #0c0c0f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            overflow: hidden;
            position: relative;
        }

        .capture-slot-box.active {
            border-color: var(--primary-red);
        }

        .capture-slot-box.captured {
            border-style: solid;
            border-color: var(--border-color);
        }

        .capture-slot-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }

        /* Camera control trigger button */
        .camera-trigger-btn {
            background-color: var(--primary-red);
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 12px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* 5. PREVIEW SCREEN WITH DRAWING CANVAS */
        .preview-screen-layout {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 240px;
            height: 100%;
            overflow: hidden;
        }

        .canvas-area {
            background-color: #08080a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px;
            position: relative;
        }

        .stitched-canvas-container {
            height: 480px;
            aspect-ratio: 0.3;
            background-color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        #drawCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            cursor: crosshair;
            touch-action: none;
        }

        #bgCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
        }

        /* Preview Sidebar Controllers */
        .preview-controllers {
            background-color: var(--sidebar-bg);
            border-left: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .doodle-tools {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .color-palette {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }

        .color-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }

        .color-circle.active {
            border-color: white;
            transform: scale(1.1);
        }

        .btn-clear-canvas {
            padding: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            background-color: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
        }

        .preview-footer-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn-confirm {
            background-color: var(--primary-gold);
            color: black;
            font-size: 0.9rem;
            font-weight: 800;
            padding: 14px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
        }
        .btn-confirm:hover { background-color: #e5ab01; }

        .btn-retake {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
        }

        /* 6. SHARE AND PRINT SCREEN */
        .screen-share {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }

        .share-strip-view {
            height: 460px;
            aspect-ratio: 0.3;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin: 0 auto;
            overflow: hidden;
        }

        .share-strip-view img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .share-info-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
            align-items: center;
            text-align: center;
        }

        .share-qr-container {
            background-color: white;
            padding: 12px;
            border-radius: 16px;
            width: 180px;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        .btn-open-portal {
            background: linear-gradient(135deg, var(--primary-red), #ff758c);
            color: white;
            font-weight: 800;
            font-size: 0.95rem;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 30px;
            box-shadow: 0 8px 20px rgba(230,57,70,0.3);
            transition: all 0.25s;
        }
        .btn-open-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(230,57,70,0.5);
        }

        .btn-finish {
            background-color: #1a1a24;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.85rem;
        }

        /* 7. GLASSMORPHIC MODAL DIALOGS */
        .sim-dialog-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            z-index: 500;
            display: none;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .sim-dialog {
            background-color: #181822;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            width: 380px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .sim-dialog-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: white;
            text-align: center;
        }

        .sim-dialog-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.4;
        }

        .sim-dialog-input {
            width: 100%;
            padding: 14px;
            background-color: #0c0c0e;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: white;
            text-align: center;
            font-size: 1.2rem;
            letter-spacing: 2px;
            font-weight: 700;
        }

        .dialog-actions {
            display: flex;
            gap: 12px;
        }

        .btn-dialog {
            flex: 1;
            padding: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .btn-dialog-cancel {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .btn-dialog-confirm {
            background-color: var(--primary-red);
            color: white;
        }

        /* Success Unlock Gold Dialog (Scenario B) */
        .gold-border {
            border: 2px solid var(--primary-gold) !important;
            box-shadow: 0 20px 40px rgba(247, 184, 1, 0.15) !important;
        }

        .gold-title {
            color: var(--primary-gold);
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        .btn-dialog-gold {
            background-color: var(--primary-gold);
            color: black;
            font-weight: 900;
        }

        /* Illustrated Print Simulation */
        .printer-sim-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .printer-mock {
            width: 120px;
            height: 90px;
            background-color: #22222a;
            border: 2px solid var(--border-color);
            border-radius: 14px;
            position: relative;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
        }

        .printer-led {
            position: absolute;
            top: 10px;
            right: 12px;
            width: 6px;
            height: 6px;
            background-color: #52b788;
            border-radius: 50%;
            box-shadow: 0 0 8px #52b788;
            animation: blinkLed 1s infinite alternate;
        }

        @keyframes blinkLed {
            from { opacity: 0.4; }
            to { opacity: 1; }
        }

        .printer-slot {
            position: absolute;
            bottom: 20px;
            left: 10px;
            width: 100px;
            height: 4px;
            background-color: black;
            border-radius: 2px;
        }

        .printing-paper {
            position: absolute;
            top: 68px;
            left: 20px;
            width: 80px;
            height: 0px;
            background-color: white;
            border: 1px solid #ccc;
            border-top: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            animation: feedPaper 4s linear forwards;
            overflow: hidden;
            display: flex;
            justify-content: center;
        }

        .printing-paper img {
            width: 100%;
            height: auto;
            object-fit: cover;
        }

        @keyframes feedPaper {
            0% { height: 0px; }
            100% { height: 120px; }
        }

        /* ------------------------------------------------------------- */
        /* SPECIFICITY BUG FIX FOR KIOSK SCREENS DISPLAY OVERLAPPING */
        /* ------------------------------------------------------------- */
        .kiosk-screen {
            flex: 1;
            position: relative;
            display: none !important;
            animation: fadeIn 0.4s ease;
        }

        .kiosk-screen.active {
            display: flex !important;
            flex-direction: column;
        }

        .kiosk-screen.active.screen-share {
            display: grid !important;
        }

        /* ------------------------------------------------------------- */
        /* PORTRAIT PHONE MODE (OVERBOARD OVERRIDES FOR MOBILE DEVICE SIM) */
        /* ------------------------------------------------------------- */
        .tablet-device.phone-mode {
            width: 380px !important;
            height: 720px !important;
            padding: 24px 16px !important;
            border-radius: 44px !important;
            border: 4px solid #33333d !important;
        }

        .tablet-device.phone-mode .tablet-lens {
            left: 50% !important;
            top: 12px !important;
            transform: translateX(-50%) !important;
        }

        .tablet-device.phone-mode .screen-home {
            padding: 24px !important;
            justify-content: space-around !important;
            align-items: center !important;
            flex-direction: column !important;
        }

        .tablet-device.phone-mode .home-logo-box {
            align-items: center !important;
            text-align: center !important;
            margin-top: 10px !important;
        }

        .tablet-device.phone-mode .home-logo-part1, 
        .tablet-device.phone-mode .home-logo-part2 {
            font-size: 1.8rem !important;
        }

        .tablet-device.phone-mode .home-center {
            margin-top: 0 !important;
            width: 100% !important;
        }

        .tablet-device.phone-mode .btn-start {
            font-size: 1.3rem !important;
            padding: 14px 40px !important;
        }

        .tablet-device.phone-mode .home-slogan {
            font-size: 0.8rem !important;
        }

        .tablet-device.phone-mode .home-strip-container {
            position: relative !important;
            right: auto !important;
            top: auto !important;
            width: 280px !important;
            height: 120px !important;
            transform: none !important;
            overflow: hidden !important;
            margin-top: 16px !important;
            background-color: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
            display: flex !important;
            flex-direction: row !important;
            gap: 8px !important;
            border-radius: 0 !important;
        }

        .tablet-device.phone-mode .scrolling-strip {
            flex-direction: row !important;
            animation: scrollStripHorizontal 12s linear infinite !important;
            width: max-content !important;
            height: 100% !important;
            gap: 8px !important;
        }

        .tablet-device.phone-mode .strip-pic {
            width: 110px !important;
            height: 100% !important;
            flex-shrink: 0 !important;
        }

        @keyframes scrollStripHorizontal {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .tablet-device.phone-mode .ticket-launcher {
            position: absolute !important;
            left: 20px !important;
            bottom: 20px !important;
            width: 44px !important;
            height: 44px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .tablet-device.phone-mode .layout-options {
            flex-direction: column !important;
            gap: 12px !important;
            padding: 20px 16px !important;
            justify-content: flex-start !important;
            overflow-y: auto !important;
        }

        .tablet-device.phone-mode .layout-card {
            max-width: 100% !important;
            width: 100% !important;
            flex-direction: row !important;
            padding: 12px !important;
            gap: 12px !important;
            align-items: center !important;
            justify-content: flex-start !important;
            text-align: left !important;
            border-radius: 12px !important;
        }

        .tablet-device.phone-mode .layout-icon {
            width: 46px !important;
            height: 46px !important;
            font-size: 1.3rem !important;
            border-radius: 10px !important;
        }

        .tablet-device.phone-mode .layout-desc {
            text-align: left !important;
            font-size: 0.7rem !important;
        }

        .tablet-device.phone-mode .frame-selector-scroll {
            flex-wrap: wrap !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            justify-content: center !important;
            gap: 16px !important;
            padding: 16px !important;
        }

        .tablet-device.phone-mode .frame-card {
            width: 95px !important;
            height: 250px !important;
            padding: 6px !important;
            border-radius: 10px !important;
        }

        .tablet-device.phone-mode .frame-card-name {
            font-size: 0.65rem !important;
        }

        .tablet-device.phone-mode .badge-event-tag {
            top: 8px !important;
            right: 8px !important;
            font-size: 0.55rem !important;
            padding: 1px 4px !important;
        }

        .tablet-device.phone-mode .camera-screen-layout {
            grid-template-columns: none !important;
            grid-template-rows: 1fr 110px !important;
            height: 100% !important;
        }

        .tablet-device.phone-mode .camera-slots-bar {
            border-left: none !important;
            border-top: 1px solid var(--border-color) !important;
            flex-direction: row !important;
            height: 110px !important;
            width: 100% !important;
            padding: 8px !important;
            gap: 10px !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            align-items: center !important;
            justify-content: flex-start !important;
        }

        .tablet-device.phone-mode .camera-slots-bar .section-title {
            display: none !important;
        }

        .tablet-device.phone-mode .camera-slots-bar .camera-trigger-btn {
            display: none !important;
        }

        .tablet-device.phone-mode .capture-slot-box {
            width: 76px !important;
            height: 57px !important;
            font-size: 0.6rem !important;
            flex-shrink: 0 !important;
        }

        .tablet-device.phone-mode .camera-preview-container {
            flex: 1 !important;
            position: relative !important;
        }

        .tablet-device.phone-mode .camera-trigger-btn-floating {
            display: block !important;
        }

        .tablet-device.phone-mode .preview-screen-layout {
            grid-template-columns: none !important;
            grid-template-rows: 1fr 130px !important;
            height: 100% !important;
        }

        .tablet-device.phone-mode .canvas-area {
            padding: 6px !important;
            flex: 1 !important;
        }

        .tablet-device.phone-mode .stitched-canvas-container {
            height: 380px !important;
        }

        .tablet-device.phone-mode .preview-controllers {
            border-left: none !important;
            border-top: 1px solid var(--border-color) !important;
            flex-direction: row !important;
            height: 130px !important;
            padding: 12px !important;
            gap: 12px !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        .tablet-device.phone-mode .doodle-tools {
            flex: 1 !important;
            gap: 8px !important;
            flex-direction: column !important;
            align-items: flex-start !important;
        }

        .tablet-device.phone-mode .doodle-tools .section-title, 
        .tablet-device.phone-mode .doodle-tools p {
            display: none !important;
        }

        .tablet-device.phone-mode .color-palette {
            margin-top: 0 !important;
        }

        .tablet-device.phone-mode .color-circle {
            width: 24px !important;
            height: 24px !important;
        }

        .tablet-device.phone-mode .btn-clear-canvas {
            padding: 6px 12px !important;
            font-size: 0.7rem !important;
            width: auto !important;
        }

        .tablet-device.phone-mode .preview-footer-actions {
            width: 110px !important;
            gap: 8px !important;
            flex-direction: column !important;
        }

        .tablet-device.phone-mode .btn-confirm, 
        .tablet-device.phone-mode .btn-retake {
            padding: 8px !important;
            font-size: 0.75rem !important;
            width: 100% !important;
            text-align: center !important;
        }

        .tablet-device.phone-mode .kiosk-screen.active.screen-share {
            display: flex !important;
            flex-direction: column !important;
            overflow-y: auto !important;
            padding: 16px !important;
            gap: 16px !important;
            justify-content: flex-start !important;
            align-items: center !important;
        }

        .tablet-device.phone-mode .share-strip-view {
            height: 280px !important;
            flex-shrink: 0 !important;
        }

        .tablet-device.phone-mode .share-info-panel {
            gap: 10px !important;
            width: 100% !important;
        }

        .tablet-device.phone-mode .share-info-panel h2 {
            font-size: 0.95rem !important;
        }

        .tablet-device.phone-mode .share-info-panel p {
            font-size: 0.7rem !important;
            max-width: 100% !important;
        }

        .tablet-device.phone-mode .share-qr-container {
            width: 110px !important;
            height: 110px !important;
            padding: 6px !important;
        }

        .tablet-device.phone-mode .btn-open-portal, 
        .tablet-device.phone-mode .btn-finish {
            padding: 10px 20px !important;
            font-size: 0.75rem !important;
            width: 100% !important;
            max-width: 220px !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }

        /* ------------------------------------------------------------- */
        /* DYNAMIC KIOSK SIMULATOR THEMES */
        /* ------------------------------------------------------------- */
        
        /* 1. Neon Red (Modern - Default) */
        .theme-neon_red .screen-home {
            background-color: var(--primary-red) !important;
        }
        .theme-neon_red .home-logo-part1, 
        .theme-neon_red .home-logo-part2 {
            font-family: 'Outfit', sans-serif !important;
            color: white !important;
        }
        .theme-neon_red .btn-start {
            font-family: 'Outfit', sans-serif !important;
            background-color: white !important;
            color: var(--primary-red) !important;
            border-radius: 50px !important;
            opacity: 1 !important;
        }
        .theme-neon_red .home-slogan {
            font-family: 'Outfit', sans-serif !important;
            color: rgba(255,255,255,0.85) !important;
        }

        /* 2. Cute Pastel (Wood Illustration) */
        .theme-cute_pastel .screen-home {
            background-color: #fcf8f2 !important;
            background-image: radial-gradient(#e5dec9 1.5px, transparent 1.5px) !important;
            background-size: 20px 20px !important;
            border: 8px solid #4e3629 !important;
            border-radius: 18px !important;
            box-shadow: inset 0 0 40px rgba(78, 54, 41, 0.05) !important;
            padding: 30px !important;
        }
        .theme-cute_pastel .home-logo-part1 {
            font-family: 'Fredoka', sans-serif !important;
            color: #4e3629 !important;
            font-weight: 700 !important;
            text-shadow: none !important;
        }
        .theme-cute_pastel .home-logo-part2 {
            font-family: 'Fredoka', sans-serif !important;
            color: #e57c5d !important;
            font-weight: 400 !important;
            text-shadow: none !important;
        }
        .theme-cute_pastel .btn-start {
            background-color: #f7d070 !important;
            color: #4e3629 !important;
            border: 4px solid #4e3629 !important;
            border-radius: 24px !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
            box-shadow: 0 6px 0 #4e3629 !important;
            text-shadow: none !important;
            letter-spacing: 0.5px !important;
            transition: all 0.2s !important;
            opacity: 1 !important;
        }
        .theme-cute_pastel .btn-start:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 0 #4e3629 !important;
        }
        .theme-cute_pastel .btn-start:active {
            transform: translateY(4px) !important;
            box-shadow: 0 2px 0 #4e3629 !important;
        }
        .theme-cute_pastel .home-slogan {
            font-family: 'Fredoka', sans-serif !important;
            color: #8b6851 !important;
            font-weight: 600 !important;
            text-transform: none !important;
            letter-spacing: 0.5px !important;
        }
        .theme-cute_pastel .screen-home::before {
            content: "⭐" !important;
            position: absolute !important;
            top: 40px !important;
            right: 180px !important;
            font-size: 2rem !important;
            animation: floatText 3s infinite ease-in-out !important;
            z-index: 8 !important;
        }
        .theme-cute_pastel .screen-home::after {
            content: "🌸" !important;
            position: absolute !important;
            bottom: 30px !important;
            left: 40px !important;
            font-size: 2rem !important;
            animation: floatText 4s infinite ease-in-out !important;
            z-index: 8 !important;
        }
        .theme-cute_pastel .home-strip-container {
            border: 6px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 6px 6px 0 rgba(78, 54, 41, 0.15) !important;
        }

        /* 2b. Cute Nara (Pinky Flower) */
        .theme-cute_nara .screen-home {
            background-color: #fff0f5 !important;
            background-image: 
                radial-gradient(#ffb3c6 1.5px, transparent 1.5px),
                radial-gradient(#ffb3c6 1.5px, #fff0f5 1.5px) !important;
            background-size: 30px 30px !important;
            background-position: 0 0, 15px 15px !important;
            padding: 30px !important;
            position: relative !important;
            overflow: hidden !important;
        }
        .theme-cute_nara .home-logo-box {
            background: #ff7597 !important;
            border: 4px solid #fff !important;
            border-radius: 20px !important;
            box-shadow: 0 6px 0 #4a1525 !important;
            transform: rotate(-3deg) !important;
            padding: 10px 30px !important;
            display: inline-block !important;
            cursor: pointer !important;
        }
        .theme-cute_nara .home-logo-part1 {
            font-family: 'Fredoka', sans-serif !important;
            color: #fff !important;
            font-weight: 900 !important;
            text-shadow: 2px 2px 0 #4a1525 !important;
        }
        .theme-cute_nara .home-logo-part2 {
            font-family: 'Fredoka', sans-serif !important;
            color: #ffb3c6 !important;
            font-weight: 700 !important;
            text-shadow: 2px 2px 0 #4a1525 !important;
        }
        .theme-cute_nara .btn-start {
            background-color: #ff7597 !important;
            color: #fff !important;
            border: 4px solid #fff !important;
            border-radius: 50px !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 900 !important;
            box-shadow: 0 6px 0 #4a1525 !important;
            text-shadow: none !important;
            letter-spacing: 0.5px !important;
            transition: all 0.2s !important;
            opacity: 1 !important;
            animation: pulseBtnCute 1.5s infinite alternate !important;
        }
        @keyframes pulseBtnCute {
            0% { transform: scale(0.96); }
            100% { transform: scale(1.04); }
        }
        .theme-cute_nara .btn-start:hover {
            transform: scale(1.06) !important;
            box-shadow: 0 8px 0 #4a1525 !important;
        }
        .theme-cute_nara .home-slogan {
            font-family: 'Fredoka', sans-serif !important;
            color: #2d6a4f !important;
            background-color: #95d5b2 !important;
            border: 2px solid #fff !important;
            box-shadow: 0 3px 0 #2d6a4f !important;
            border-radius: 20px !important;
            padding: 6px 18px !important;
            display: inline-block !important;
            font-weight: 700 !important;
            margin-top: 15px !important;
            text-transform: none !important;
        }
        /* Floating flowers/stars */
        .theme-cute_nara .screen-home::before {
            content: "🌸" !important;
            position: absolute !important;
            top: 40px !important;
            left: 180px !important;
            font-size: 2.2rem !important;
            animation: rotateFlower 8s infinite linear !important;
            z-index: 8 !important;
        }
        .theme-cute_nara .screen-home::after {
            content: "⭐" !important;
            position: absolute !important;
            bottom: 120px !important;
            right: 250px !important;
            font-size: 1.8rem !important;
            animation: floatText 3s infinite ease-in-out !important;
            z-index: 8 !important;
        }
        @keyframes rotateFlower {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .theme-cute_nara .home-strip-container {
            border: 6px solid #ff7597 !important;
            border-radius: 16px !important;
            background-color: #fff !important;
            box-shadow: 6px 6px 0 rgba(74, 21, 37, 0.1) !important;
        }

        /* 3. Luxury Gold (Elegant Wedding) */
        .theme-luxury_gold .screen-home {
            background-color: #0b132b !important;
            background-image: radial-gradient(#d4af37 0.5px, transparent 0.5px) !important;
            background-size: 15px 15px !important;
            border: 1px solid rgba(212, 175, 55, 0.3) !important;
            border-radius: 18px !important;
        }
        .theme-luxury_gold .screen-home::before {
            content: "" !important;
            position: absolute !important;
            top: 15px !important;
            left: 15px !important;
            right: 15px !important;
            bottom: 15px !important;
            border: 2px solid #d4af37 !important;
            pointer-events: none !important;
            z-index: 9 !important;
            opacity: 0.8 !important;
        }
        .theme-luxury_gold .screen-home::after {
            content: "" !important;
            position: absolute !important;
            top: 20px !important;
            left: 20px !important;
            right: 20px !important;
            bottom: 20px !important;
            border: 1px solid #d4af37 !important;
            pointer-events: none !important;
            z-index: 9 !important;
            opacity: 0.5 !important;
        }
        .theme-luxury_gold .home-logo-part1 {
            font-family: 'Playfair Display', serif !important;
            color: #d4af37 !important;
            font-weight: 700 !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important;
            letter-spacing: 2px !important;
        }
        .theme-luxury_gold .home-logo-part2 {
            font-family: 'Playfair Display', serif !important;
            color: #f4e0a5 !important;
            font-weight: 400 !important;
            font-style: italic !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important;
            letter-spacing: 2px !important;
        }
        .theme-luxury_gold .btn-start {
            background: linear-gradient(135deg, #d4af37 0%, #f4e0a5 50%, #d4af37 100%) !important;
            color: #0b132b !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4) !important;
            letter-spacing: 2px !important;
            text-transform: uppercase !important;
            opacity: 1 !important;
        }
        .theme-luxury_gold .home-slogan {
            font-family: 'Playfair Display', serif !important;
            color: #f4e0a5 !important;
            font-weight: 400 !important;
            letter-spacing: 3px !important;
            font-style: italic !important;
        }
        .theme-luxury_gold .home-strip-container {
            border: 2px solid #d4af37 !important;
            background-color: #0b132b !important;
        }
        .theme-luxury_gold .strip-pic {
            border: 1px solid rgba(212,175,55,0.2) !important;
            background-color: #0b132b !important;
        }

        /* 4. Minimal Modern (Clean & Creative) */
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');

        .theme-minimal_modern .screen-home {
            background-color: #05050a !important;
            overflow: hidden !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            border-radius: 18px !important;
            display: flex !important;
            flex-direction: row-reverse !important;
            align-items: center !important;
            justify-content: space-around !important;
            padding: 40px !important;
        }
        /* Floating background blobs */
        .theme-minimal_modern .screen-home::before {
            content: "" !important;
            position: absolute !important;
            width: 300px !important;
            height: 300px !important;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.15) 0%, transparent 70%) !important;
            top: -50px !important;
            left: -50px !important;
            filter: blur(50px) !important;
            animation: blobFloat1 18s infinite alternate ease-in-out !important;
            z-index: 1 !important;
            pointer-events: none !important;
        }
        .theme-minimal_modern .screen-home::after {
            content: "" !important;
            position: absolute !important;
            width: 350px !important;
            height: 350px !important;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%) !important;
            bottom: -80px !important;
            right: -80px !important;
            filter: blur(60px) !important;
            animation: blobFloat2 22s infinite alternate ease-in-out !important;
            z-index: 1 !important;
            pointer-events: none !important;
        }
        @keyframes blobFloat1 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(120px, 80px) scale(1.15); }
            100% { transform: translate(0, 0) scale(1); }
        }
        @keyframes blobFloat2 {
            0% { transform: translate(0, 0) scale(1.1); }
            50% { transform: translate(-100px, -120px) scale(0.9); }
            100% { transform: translate(0, 0) scale(1.1); }
        }

        .theme-minimal_modern .home-logo-box {
            position: absolute !important;
            top: 40px !important;
            left: 50px !important;
            display: flex !important;
            flex-direction: row !important;
            gap: 8px !important;
            align-items: center !important;
            z-index: 10 !important;
        }
        .theme-minimal_modern .home-logo-part1 {
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.35rem !important;
            font-weight: 900 !important;
            color: #ffffff !important;
            letter-spacing: 2px !important;
            text-transform: uppercase !important;
            opacity: 1 !important;
            animation: none !important;
        }
        .theme-minimal_modern .home-logo-part2 {
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.35rem !important;
            font-weight: 300 !important;
            color: #06b6d4 !important;
            letter-spacing: 2px !important;
            text-transform: uppercase !important;
            margin-top: 0 !important;
            opacity: 1 !important;
            animation: none !important;
        }

        .theme-minimal_modern .home-center {
            position: absolute !important;
            left: 50px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            align-items: flex-start !important;
            margin-top: 0 !important;
            gap: 24px !important;
            z-index: 10 !important;
        }
        .theme-minimal_modern .btn-start {
            background: linear-gradient(135deg, #6366f1, #06b6d4) !important;
            color: white !important;
            border: 1px solid rgba(255,255,255,0.15) !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.25rem !important;
            font-weight: 700 !important;
            padding: 16px 48px !important;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.35) !important;
            letter-spacing: 2px !important;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1) !important;
            opacity: 1 !important;
            position: relative !important;
            overflow: hidden !important;
            animation: shineBtnStart 4s infinite !important;
        }
        @keyframes shineBtnStart {
            0% { box-shadow: 0 10px 30px rgba(99, 102, 241, 0.35); }
            50% { box-shadow: 0 10px 35px rgba(6, 182, 212, 0.55); }
            100% { box-shadow: 0 10px 30px rgba(99, 102, 241, 0.35); }
        }
        .theme-minimal_modern .btn-start::after {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), transparent) !important;
            animation: shineEffect 3s infinite ease-in-out !important;
        }
        @keyframes shineEffect {
            0% { left: -100%; }
            15% { left: 100%; }
            100% { left: 100%; }
        }
        .theme-minimal_modern .btn-start:hover {
            transform: translateY(-4px) scale(1.05) !important;
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.6) !important;
        }
        .theme-minimal_modern .home-slogan {
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 400 !important;
            color: rgba(255, 255, 255, 0.6) !important;
            letter-spacing: 3px !important;
            text-transform: uppercase !important;
            text-shadow: none !important;
            animation: none !important;
            opacity: 1 !important;
        }

        .theme-minimal_modern .home-strip-container {
            position: absolute !important;
            right: 60px !important;
            top: 10% !important;
            width: 220px !important;
            height: 520px !important;
            background: rgba(255, 255, 255, 0.04) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 24px !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4) !important;
            transform: rotate(4deg) !important;
            z-index: 5 !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            padding: 12px !important;
            gap: 12px !important;
            opacity: 1 !important;
            animation: floatStripSim 6s infinite ease-in-out !important;
        }
        @keyframes floatStripSim {
            0% { transform: translateY(0px) rotate(4deg); }
            50% { transform: translateY(-15px) rotate(2deg); }
            100% { transform: translateY(0px) rotate(4deg); }
        }
        .theme-minimal_modern .strip-pic {
            border-radius: 16px !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
        }

        /* ------------------------------------------------------------- */
        /* CUTE PASTEL OVERRIDES FOR ALL SCREENS */
        /* ------------------------------------------------------------- */
        .theme-cute_pastel {
            background-color: #fcf8f2 !important;
            font-family: 'Fredoka', sans-serif !important;
        }
        .theme-cute_pastel .kiosk-screen {
            background-color: #fcf8f2 !important;
            background-image: radial-gradient(#e5dec9 1.5px, transparent 1.5px) !important;
            background-size: 20px 20px !important;
        }
        .theme-cute_pastel .screen-header-back {
            background-color: #f5eedc !important;
            border-bottom: 4px solid #4e3629 !important;
        }
        .theme-cute_pastel .screen-title {
            color: #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .btn-back {
            color: #e57c5d !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .layout-card, 
        .theme-cute_pastel .frame-card {
            background-color: #fcf8f2 !important;
            border: 4px solid #4e3629 !important;
            border-radius: 20px !important;
            box-shadow: 4px 4px 0 #4e3629 !important;
            color: #4e3629 !important;
        }
        .theme-cute_pastel .layout-card:hover,
        .theme-cute_pastel .frame-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 6px 6px 0 #4e3629 !important;
            border-color: #e57c5d !important;
        }
        .theme-cute_pastel .layout-icon {
            background-color: #f5eedc !important;
            border: 3px solid #4e3629 !important;
            color: #e57c5d !important;
            border-radius: 12px !important;
        }
        .theme-cute_pastel .layout-desc {
            color: #8b6851 !important;
        }
        .theme-cute_pastel .frame-card-preview {
            background-color: #f5eedc !important;
            border: 2px solid #4e3629 !important;
            border-radius: 8px !important;
        }
        .theme-cute_pastel .frame-card-name {
            color: #4e3629 !important;
        }
        .theme-cute_pastel .camera-slots-bar {
            background-color: #f5eedc !important;
            border-left: 4px solid #4e3629 !important;
        }
        .theme-cute_pastel .camera-rec-badge {
            background-color: #4e3629 !important;
            color: #fcf8f2 !important;
            border: 2px solid #fcf8f2 !important;
        }
        .theme-cute_pastel .capture-slot-box {
            background-color: #fcf8f2 !important;
            border: 3px dashed #4e3629 !important;
            border-radius: 12px !important;
        }
        .theme-cute_pastel .capture-slot-box.active {
            border-color: #e57c5d !important;
            border-style: solid !important;
        }
        .theme-cute_pastel .capture-slot-box.captured {
            border-color: #4e3629 !important;
            border-style: solid !important;
        }
        .theme-cute_pastel .camera-trigger-btn,
        .theme-cute_pastel .camera-trigger-btn-floating {
            background-color: #e57c5d !important;
            color: white !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .camera-trigger-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 4px 4px 0 #4e3629 !important;
        }
        .theme-cute_pastel .section-title {
            color: #8b6851 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .preview-controllers {
            background-color: #f5eedc !important;
            border-left: 4px solid #4e3629 !important;
        }
        .theme-cute_pastel .btn-confirm {
            background-color: #e57c5d !important;
            color: white !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .btn-retake {
            background-color: #fcf8f2 !important;
            color: #4e3629 !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .btn-confirm:hover,
        .theme-cute_pastel .btn-retake:hover {
            transform: translateY(-1px) !important;
            box-shadow: 4px 4px 0 #4e3629 !important;
        }
        .theme-cute_pastel .btn-clear-canvas {
            background-color: #fcf8f2 !important;
            color: #4e3629 !important;
            border: 2px solid #4e3629 !important;
            border-radius: 10px !important;
            font-family: 'Fredoka', sans-serif !important;
        }
        .theme-cute_pastel .stitched-canvas-container {
            border: 8px solid #4e3629 !important;
            border-radius: 16px !important;
            box-shadow: 6px 6px 0 rgba(78, 54, 41, 0.1) !important;
        }
        .theme-cute_pastel .share-info-panel {
            background-color: #f5eedc !important;
            border-left: 4px solid #4e3629 !important;
            color: #4e3629 !important;
        }
        .theme-cute_pastel .share-info-panel h2 {
            color: #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .share-qr-container {
            background-color: white !important;
            border: 4px solid #4e3629 !important;
            border-radius: 16px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
        }
        .theme-cute_pastel .btn-open-portal {
            background-color: #f7d070 !important;
            color: #4e3629 !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .btn-finish {
            background-color: #e57c5d !important;
            color: white !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
            box-shadow: 3px 3px 0 #4e3629 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .sim-dialog {
            background-color: #fcf8f2 !important;
            border: 6px solid #4e3629 !important;
            border-radius: 24px !important;
            color: #4e3629 !important;
        }
        .theme-cute_pastel .sim-dialog-title {
            color: #e57c5d !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_pastel .sim-dialog-desc {
            color: #8b6851 !important;
        }
        .theme-cute_pastel .sim-dialog-input {
            background-color: #f5eedc !important;
            border: 3px solid #4e3629 !important;
            color: #4e3629 !important;
            border-radius: 12px !important;
        }
        .theme-cute_pastel .btn-dialog-confirm {
            background-color: #e57c5d !important;
            color: white !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
        }
        .theme-cute_pastel .btn-dialog-cancel {
            background-color: #f5eedc !important;
            color: #4e3629 !important;
            border: 3px solid #4e3629 !important;
            border-radius: 12px !important;
        }

        /* ------------------------------------------------------------- */
        /* CUTE NARA OVERRIDES FOR ALL SCREENS */
        /* ------------------------------------------------------------- */
        .theme-cute_nara {
            background-color: #fff0f5 !important;
            font-family: 'Fredoka', sans-serif !important;
        }
        .theme-cute_nara .kiosk-screen {
            background-color: #fff0f5 !important;
            background-image: radial-gradient(#ffb3c6 1.5px, transparent 1.5px) !important;
            background-size: 25px 25px !important;
        }
        .theme-cute_nara .screen-header-back {
            background-color: #ffe5ec !important;
            border-bottom: 4px solid #ff7597 !important;
        }
        .theme-cute_nara .screen-title {
            color: #4a1525 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_nara .btn-back {
            color: #ff7597 !important;
            font-family: 'Fredoka', sans-serif !important;
            font-weight: 700 !important;
        }
        .theme-cute_nara .layout-card, 
        .theme-cute_nara .frame-card {
            background-color: #fff9fa !important;
            border: 4px solid #ff7597 !important;
            border-radius: 20px !important;
            box-shadow: 4px 4px 0 #4a1525 !important;
            color: #4a1525 !important;
        }
        .theme-cute_nara .layout-card:hover,
        .theme-cute_nara .frame-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 6px 6px 0 #4a1525 !important;
            border-color: #ffb3c6 !important;
        }
        .theme-cute_nara .layout-icon {
            background-color: #ffe5ec !important;
            border: 3px solid #ff7597 !important;
            color: #ff7597 !important;
            border-radius: 12px !important;
        }
        .theme-cute_nara .layout-desc {
            color: #8b5d6c !important;
        }
        .theme-cute_nara .frame-card-preview {
            background-color: #ffe5ec !important;
            border: 2px solid #ff7597 !important;
            border-radius: 8px !important;
        }
        .theme-cute_nara .frame-card-name {
            color: #4a1525 !important;
        }
        .theme-cute_nara .camera-slots-bar {
            background-color: #ffe5ec !important;
            border-left: 4px solid #ff7597 !important;
        }
        .theme-cute_nara .camera-rec-badge {
            background-color: #ff7597 !important;
            color: white !important;
            border: 2px solid white !important;
        }
        .theme-cute_nara .capture-slot-box {
            border: 3px solid #ff7597 !important;
            background-color: #fff9fa !important;
        }
        .theme-cute_nara .capture-slot-box.active {
            border-color: #ff7597 !important;
            box-shadow: 0 0 12px #ff7597 !important;
        }
        .theme-cute_nara .capture-slot-box.captured {
            border-color: #95d5b2 !important;
        }
        .theme-cute_nara .camera-trigger-btn,
        .theme-cute_nara .camera-trigger-btn-floating {
            background-color: #ff7597 !important;
            color: white !important;
            border: 4px solid white !important;
            box-shadow: 0 6px 0 #4a1525 !important;
        }
        .theme-cute_nara .camera-trigger-btn:hover {
            box-shadow: 0 8px 0 #4a1525 !important;
        }
        .theme-cute_nara .section-title {
            color: #4a1525 !important;
            border-bottom: 2px solid #ff7597 !important;
        }
        .theme-cute_nara .preview-controllers {
            background-color: #ffe5ec !important;
            border-top: 4px solid #ff7597 !important;
        }
        .theme-cute_nara .btn-confirm {
            background-color: #ff7597 !important;
            color: white !important;
            border: 3px solid white !important;
            box-shadow: 0 4px 0 #4a1525 !important;
        }
        .theme-cute_nara .btn-retake {
            background-color: #ffe5ec !important;
            color: #ff7597 !important;
            border: 3px solid #ff7597 !important;
            box-shadow: 0 4px 0 #4a1525 !important;
        }
        .theme-cute_nara .btn-confirm:hover,
        .theme-cute_nara .btn-retake:hover {
            transform: translateY(-2px) !important;
        }
        .theme-cute_nara .btn-clear-canvas {
            background-color: #ffe5ec !important;
            color: #ff7597 !important;
            border: 2px solid #ff7597 !important;
        }
        .theme-cute_nara .stitched-canvas-container {
            background-color: #fff9fa !important;
            border: 6px double #ff7597 !important;
        }
        .theme-cute_nara .share-info-panel {
            background-color: #fff9fa !important;
            border: 4px solid #ff7597 !important;
            box-shadow: 6px 6px 0 #4a1525 !important;
        }
        .theme-cute_nara .share-info-panel h2 {
            color: #4a1525 !important;
        }
        .theme-cute_nara .share-qr-container {
            background-color: white !important;
            border: 3px solid #ff7597 !important;
        }
        .theme-cute_nara .btn-open-portal {
            background-color: #95d5b2 !important;
            color: #2d6a4f !important;
            border: 3px solid #2d6a4f !important;
        }
        .theme-cute_nara .btn-finish {
            background-color: #ff7597 !important;
            color: white !important;
            border: 3px solid white !important;
            box-shadow: 0 4px 0 #4a1525 !important;
        }
        .theme-cute_nara .sim-dialog {
            background-color: #fff9fa !important;
            border: 4px solid #ff7597 !important;
            border-radius: 20px !important;
            box-shadow: 0 10px 0 rgba(74,21,37,0.15) !important;
        }
        .theme-cute_nara .sim-dialog-title {
            color: #4a1525 !important;
        }
        .theme-cute_nara .sim-dialog-desc {
            color: #8b5d6c !important;
        }
        .theme-cute_nara .sim-dialog-input {
            background-color: #ffe5ec !important;
            border: 3px solid #ff7597 !important;
            color: #4a1525 !important;
            border-radius: 12px !important;
        }
        .theme-cute_nara .btn-dialog-confirm {
            background-color: #ff7597 !important;
            color: white !important;
            border: 3px solid white !important;
            border-radius: 12px !important;
        }
        .theme-cute_nara .btn-dialog-cancel {
            background-color: #ffe5ec !important;
            color: #ff7597 !important;
            border: 3px solid #ff7597 !important;
            border-radius: 12px !important;
        }

        /* ------------------------------------------------------------- */
        /* LUXURY GOLD OVERRIDES FOR ALL SCREENS */
        /* ------------------------------------------------------------- */
        .theme-luxury_gold {
            background-color: #0b132b !important;
            font-family: 'Playfair Display', serif !important;
        }
        .theme-luxury_gold .kiosk-screen {
            background-color: #0b132b !important;
            background-image: radial-gradient(#d4af37 0.5px, transparent 0.5px) !important;
            background-size: 15px 15px !important;
        }
        .theme-luxury_gold .screen-header-back {
            background-color: #0d1b3e !important;
            border-bottom: 2px solid #d4af37 !important;
        }
        .theme-luxury_gold .screen-title {
            color: #d4af37 !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
        }
        .theme-luxury_gold .btn-back {
            color: #f4e0a5 !important;
            font-family: 'Playfair Display', serif !important;
        }
        .theme-luxury_gold .layout-card, 
        .theme-luxury_gold .frame-card {
            background-color: #0d1b3e !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3) !important;
            color: #f4e0a5 !important;
        }
        .theme-luxury_gold .layout-card:hover,
        .theme-luxury_gold .frame-card:hover {
            border-color: #f4e0a5 !important;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.4) !important;
            transform: translateY(-2px) !important;
        }
        .theme-luxury_gold .layout-icon {
            background-color: rgba(212, 175, 55, 0.1) !important;
            border: 1px solid #d4af37 !important;
            color: #d4af37 !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .layout-desc {
            color: rgba(244, 224, 165, 0.7) !important;
        }
        .theme-luxury_gold .frame-card-preview {
            background-color: #0b132b !important;
            border: 1px solid rgba(212, 175, 55, 0.3) !important;
            border-radius: 2px !important;
        }
        .theme-luxury_gold .frame-card-name {
            color: #d4af37 !important;
            font-weight: 600 !important;
        }
        .theme-luxury_gold .camera-slots-bar {
            background-color: #0d1b3e !important;
            border-left: 2px solid #d4af37 !important;
        }
        .theme-luxury_gold .camera-rec-badge {
            background-color: #0b132b !important;
            color: #d4af37 !important;
            border: 1px solid #d4af37 !important;
        }
        .theme-luxury_gold .capture-slot-box {
            background-color: #0b132b !important;
            border: 1px dashed #d4af37 !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .capture-slot-box.active {
            border-color: #f4e0a5 !important;
            border-style: solid !important;
            box-shadow: 0 0 8px #d4af37 !important;
        }
        .theme-luxury_gold .capture-slot-box.captured {
            border-color: #d4af37 !important;
            border-style: solid !important;
        }
        .theme-luxury_gold .camera-trigger-btn,
        .theme-luxury_gold .camera-trigger-btn-floating {
            background: linear-gradient(135deg, #d4af37 0%, #f4e0a5 50%, #d4af37 100%) !important;
            color: #0b132b !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3) !important;
        }
        .theme-luxury_gold .section-title {
            color: #d4af37 !important;
            font-family: 'Playfair Display', serif !important;
            letter-spacing: 1px !important;
        }
        .theme-luxury_gold .preview-controllers {
            background-color: #0d1b3e !important;
            border-left: 2px solid #d4af37 !important;
        }
        .theme-luxury_gold .btn-confirm {
            background: linear-gradient(135deg, #d4af37 0%, #f4e0a5 50%, #d4af37 100%) !important;
            color: #0b132b !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3) !important;
        }
        .theme-luxury_gold .btn-retake {
            background-color: transparent !important;
            color: #f4e0a5 !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            font-family: 'Playfair Display', serif !important;
        }
        .theme-luxury_gold .btn-clear-canvas {
            background-color: transparent !important;
            color: #d4af37 !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .stitched-canvas-container {
            border: 3px solid #d4af37 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        }
        .theme-luxury_gold .share-info-panel {
            background-color: #0d1b3e !important;
            border-left: 2px solid #d4af37 !important;
            color: #f4e0a5 !important;
        }
        .theme-luxury_gold .share-info-panel h2 {
            color: #d4af37 !important;
            font-family: 'Playfair Display', serif !important;
        }
        .theme-luxury_gold .share-qr-container {
            background-color: white !important;
            border: 2px solid #d4af37 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.2) !important;
        }
        .theme-luxury_gold .btn-open-portal {
            background-color: transparent !important;
            color: #f4e0a5 !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .btn-finish {
            background: linear-gradient(135deg, #d4af37 0%, #f4e0a5 50%, #d4af37 100%) !important;
            color: #0b132b !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
        }
        .theme-luxury_gold .sim-dialog {
            background-color: #0b132b !important;
            border: 2px solid #d4af37 !important;
            border-radius: 8px !important;
            color: #f4e0a5 !important;
        }
        .theme-luxury_gold .sim-dialog-title {
            color: #d4af37 !important;
            font-family: 'Playfair Display', serif !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
        }
        .theme-luxury_gold .sim-dialog-desc {
            color: rgba(244, 224, 165, 0.7) !important;
        }
        .theme-luxury_gold .sim-dialog-input {
            background-color: #0d1b3e !important;
            border: 1px solid #d4af37 !important;
            color: white !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .btn-dialog-confirm,
        .theme-luxury_gold .btn-dialog-gold {
            background: linear-gradient(135deg, #d4af37 0%, #f4e0a5 50%, #d4af37 100%) !important;
            color: #0b132b !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
        }
        .theme-luxury_gold .btn-dialog-cancel {
            background-color: transparent !important;
            color: #f4e0a5 !important;
            border: 1px solid #d4af37 !important;
            border-radius: 4px !important;
        }

        /* ------------------------------------------------------------- */
        /* MINIMAL MODERN OVERRIDES FOR ALL SCREENS */
        /* ------------------------------------------------------------- */
        .theme-minimal_modern {
            background-color: #05050a !important;
            font-family: 'Outfit', sans-serif !important;
        }
        .theme-minimal_modern .kiosk-screen {
            background-color: #05050a !important;
            background-image: none !important;
        }
        .theme-minimal_modern .screen-header-back {
            background-color: rgba(15, 23, 42, 0.75) !important;
            backdrop-filter: blur(12px) !important;
            -webkit-backdrop-filter: blur(12px) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .theme-minimal_modern .screen-title {
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.15rem !important;
            font-weight: 700 !important;
        }
        .theme-minimal_modern .btn-back {
            color: #06b6d4 !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
        }
        .theme-minimal_modern .layout-card, 
        .theme-minimal_modern .frame-card {
            background-color: rgba(15, 23, 42, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            border-radius: 16px !important;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            color: #ffffff !important;
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) !important;
        }
        .theme-minimal_modern .layout-card:hover,
        .theme-minimal_modern .frame-card:hover {
            border-color: #6366f1 !important;
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.25) !important;
            transform: translateY(-4px) !important;
        }
        .theme-minimal_modern .layout-icon {
            background-color: rgba(99, 102, 241, 0.1) !important;
            border: none !important;
            color: #6366f1 !important;
            border-radius: 12px !important;
        }
        .theme-minimal_modern .layout-desc {
            color: rgba(255, 255, 255, 0.5) !important;
            font-size: 0.75rem !important;
            line-height: 1.6 !important;
        }
        .theme-minimal_modern .frame-card-preview {
            background-color: rgba(5, 5, 10, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            border-radius: 12px !important;
        }
        .theme-minimal_modern .frame-card-name {
            color: #ffffff !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
        }
        .theme-minimal_modern .camera-slots-bar {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .theme-minimal_modern .camera-rec-badge {
            background-color: rgba(239, 68, 68, 0.15) !important;
            color: #ef4444 !important;
            border: 1px solid #ef4444 !important;
            border-radius: 20px !important;
            font-weight: 600 !important;
        }
        .theme-minimal_modern .capture-slot-box {
            background-color: rgba(255, 255, 255, 0.02) !important;
            border: 1px dashed rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px !important;
        }
        .theme-minimal_modern .capture-slot-box.active {
            border-color: #06b6d4 !important;
            border-style: solid !important;
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.3) !important;
        }
        .theme-minimal_modern .capture-slot-box.captured {
            border-color: #6366f1 !important;
            border-style: solid !important;
        }
        .theme-minimal_modern .camera-trigger-btn,
        .theme-minimal_modern .camera-trigger-btn-floating {
            background: linear-gradient(135deg, #6366f1, #06b6d4) !important;
            color: white !important;
            border: none !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3) !important;
            transition: all 0.3s ease !important;
        }
        .theme-minimal_modern .camera-trigger-btn:hover {
            box-shadow: 0 12px 25px rgba(99, 102, 241, 0.5) !important;
            transform: translateY(-2px) !important;
        }
        .theme-minimal_modern .section-title {
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            letter-spacing: 1px !important;
            text-transform: uppercase !important;
        }
        .theme-minimal_modern .preview-controllers {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .theme-minimal_modern .btn-confirm {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
            border: none !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25) !important;
        }
        .theme-minimal_modern .btn-retake {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: white !important;
            border: none !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25) !important;
        }
        .theme-minimal_modern .btn-confirm:hover,
        .theme-minimal_modern .btn-retake:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 12px 25px rgba(0,0,0,0.3) !important;
        }
        .theme-minimal_modern .btn-clear-canvas {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: rgba(255, 255, 255, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 8px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.75rem !important;
        }
        .theme-minimal_modern .stitched-canvas-container {
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35) !important;
        }
        .theme-minimal_modern .share-info-panel {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-left: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }
        .theme-minimal_modern .share-info-panel h2 {
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.25rem !important;
            font-weight: 700 !important;
        }
        .theme-minimal_modern .share-qr-container {
            background-color: white !important;
            border: 6px solid white !important;
            border-radius: 16px !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2) !important;
        }
        .theme-minimal_modern .btn-open-portal {
            background: rgba(255, 255, 255, 0.05) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            font-weight: 600 !important;
        }
        .theme-minimal_modern .btn-finish {
            background: linear-gradient(135deg, #6366f1, #06b6d4) !important;
            color: white !important;
            border: none !important;
            border-radius: 30px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3) !important;
        }
        .theme-minimal_modern .sim-dialog {
            background-color: rgba(15, 23, 42, 0.95) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 20px !important;
            color: #ffffff !important;
        }
        .theme-minimal_modern .sim-dialog-title {
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.1rem !important;
            font-weight: 700 !important;
        }
        .theme-minimal_modern .sim-dialog-desc {
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 0.85rem !important;
            line-height: 1.6 !important;
        }
        .theme-minimal_modern .sim-dialog-input {
            background-color: rgba(5, 5, 10, 0.5) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: white !important;
            border-radius: 10px !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.9rem !important;
            padding: 10px 14px !important;
        }
        .theme-minimal_modern .btn-dialog-confirm {
            background: linear-gradient(135deg, #6366f1, #06b6d4) !important;
            color: white !important;
            border: none !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }
        .theme-minimal_modern .btn-dialog-cancel {
            background-color: transparent !important;
            color: rgba(255, 255, 255, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
        }

        /* ------------------------------------------------------------- */
        /* COMIC POP-ART THEME (COMIC_POP) OVERRIDES */
        /* ------------------------------------------------------------- */
        @import url('https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Bangers&display=swap');

        /* Theme Base Styling */
        .theme-comic_pop {
            background-color: #f7eedb !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
        }

        .theme-comic_pop .kiosk-screen {
            background-color: #f7eedb !important;
            background-image: radial-gradient(rgba(0,0,0,0.12) 15%, transparent 16%) !important;
            background-size: 16px 16px !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
        }

        /* Home Screen Backdrop */
        .theme-comic_pop .screen-home {
            border: 8px solid #000 !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            background-image:
                radial-gradient(rgba(0,0,0,0.15) 15%, transparent 16%),
                linear-gradient(to right, transparent calc(33.33% - 4px), #000 calc(33.33% - 4px), #000 calc(33.33% + 4px), transparent calc(33.33% + 4px), transparent calc(66.66% - 4px), #000 calc(66.66% - 4px), #000 calc(66.66% + 4px), transparent calc(66.66% + 4px)),
                repeating-conic-gradient(from 0deg at 50% 50%, #e03f15 0deg 12deg, #f0542c 12deg 24deg),
                repeating-conic-gradient(from 0deg at 50% 50%, #026ebd 0deg 12deg, #0183e2 12deg 24deg),
                repeating-conic-gradient(from 0deg at 50% 50%, #f5cb03 0deg 12deg, #ffd814 12deg 24deg) !important;
            background-size:
                12px 12px,
                100% 100%,
                33.33% 100%,
                33.34% 100%,
                33.33% 100% !important;
            background-position:
                left top,
                left top,
                left top,
                center top,
                right top !important;
            background-repeat:
                repeat,
                no-repeat,
                no-repeat,
                no-repeat,
                no-repeat !important;
        }

        /* Puffy Comic Clouds */
        .theme-comic_pop .screen-home::before {
            content: "" !important;
            position: absolute !important;
            top: -15px !important;
            left: 0 !important;
            width: 100% !important;
            height: 110px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'><path d='M-10,0 L1210,0 L1210,50 C1180,30 1140,25 1110,40 C1080,20 1030,20 1000,40 C970,25 920,25 890,45 C860,20 810,20 780,45 C750,25 700,25 670,45 C640,20 590,20 560,45 C530,25 480,25 450,45 C420,20 370,20 340,45 C310,25 260,25 230,45 C200,20 150,20 120,40 C90,25 50,30 20,50 C5,55 -5,55 -10,50 Z' fill='white' stroke='black' stroke-width='6'/></svg>") !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            z-index: 2 !important;
            pointer-events: none !important;
        }

        .theme-comic_pop .screen-home::after {
            content: "" !important;
            position: absolute !important;
            bottom: -15px !important;
            left: 0 !important;
            width: 100% !important;
            height: 110px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'><path d='M-10,120 L1210,120 L1210,70 C1180,90 1140,95 1110,80 C1080,100 1030,100 1000,80 C970,95 920,95 890,75 C860,100 810,100 780,75 C750,95 700,95 670,75 C640,100 590,100 560,75 C530,95 480,95 450,75 C420,100 370,100 340,75 C310,95 260,95 230,75 C200,100 150,100 120,80 C90,95 50,90 20,70 C5,65 -5,65 -10,70 Z' fill='white' stroke='black' stroke-width='6'/></svg>") !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            z-index: 2 !important;
            pointer-events: none !important;
        }

        /* Comic Header Branding */
        .theme-comic_pop .home-logo-box {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
            width: 100% !important;
            margin-top: 40px !important;
            z-index: 10 !important;
            transform: rotate(-1.5deg) !important;
            animation: comicWobbleHeader 4s ease-in-out infinite alternate !important;
            cursor: pointer;
        }

        @keyframes comicWobbleHeader {
            0% { transform: rotate(-2.5deg) scale(0.98); }
            100% { transform: rotate(1.5deg) scale(1.02); }
        }

        .theme-comic_pop .home-logo-part1,
        .theme-comic_pop .home-logo-part2 {
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: clamp(2.2rem, 5.5vw, 4.2rem) !important;
            letter-spacing: 2px !important;
            line-height: 1.1 !important;
            text-align: center !important;
            display: block !important;
            width: 100% !important;
        }

        .theme-comic_pop .home-logo-part1 {
            color: #fff300 !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
        }

        .theme-comic_pop .home-logo-part2 {
            color: #ff2a85 !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
        }

        .theme-comic_pop .txt-pink {
            color: #ff2a85 !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
        }

        .theme-comic_pop .txt-blue {
            color: #00bfff !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
            margin-left: 10px !important;
        }

        .theme-comic_pop .txt-yellow {
            color: #fff300 !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
            margin-left: 10px !important;
        }

        .theme-comic_pop .txt-white {
            color: #ffffff !important;
            -webkit-text-stroke: 3px #000 !important;
            text-shadow: 5px 5px 0 #000 !important;
            margin-left: 10px !important;
        }

        /* Comic Decorations */
        .comic-star-decor {
            position: absolute !important;
            width: 65px !important;
            height: 65px !important;
            z-index: 3 !important;
            pointer-events: none !important;
            animation: comicSpin 10s linear infinite !important;
        }
        .decor-star1 {
            top: 25% !important;
            left: 28% !important;
        }
        .decor-star2 {
            top: 30% !important;
            right: 28% !important;
        }
        @keyframes comicSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .comic-bubble-pow {
            position: absolute !important;
            top: 25% !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: 110px !important;
            height: 90px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 80'><polygon points='50,5 65,25 85,15 80,35 98,35 83,52 95,70 73,63 70,83 55,70 45,83 38,68 20,78 23,58 3,60 18,45 5,25 28,30 28,10 43,23' fill='%23ff00ff' stroke='black' stroke-width='4'/></svg>") !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            z-index: 3 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            animation: comicPowPulse 1.8s ease-in-out infinite alternate !important;
            pointer-events: none !important;
        }
        .comic-bubble-pow::after {
            content: "POW!" !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #fff !important;
            font-size: 1.4rem !important;
            -webkit-text-stroke: 1.2px #000 !important;
            text-shadow: 2px 2px 0 #000 !important;
            transform: rotate(-10deg) !important;
        }
        @keyframes comicPowPulse {
            0% { transform: translateX(-50%) scale(0.9) rotate(-10deg); }
            100% { transform: translateX(-50%) scale(1.1) rotate(10deg); }
        }

        /* Slogan (PHOTOBOOTH!!!) */
        .theme-comic_pop .home-slogan {
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #ffffff !important;
            font-size: 2.2rem !important;
            -webkit-text-stroke: 1.5px #000 !important;
            text-shadow: 4px 4px 0 #000 !important;
            letter-spacing: 2px !important;
            margin-top: 15px !important;
            transform: rotate(-2deg) !important;
            background-color: #000 !important;
            padding: 4px 20px !important;
            border-radius: 4px !important;
            border: 3px solid #fff !important;
            display: inline-block !important;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.5) !important;
            z-index: 4 !important;
        }

        /* Center start button styling */
        .theme-comic_pop .home-center {
            z-index: 4 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .theme-comic_pop .btn-start {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            width: auto !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            animation: comicStartPulse 2s ease-in-out infinite !important;
            transition: transform 0.2s !important;
            cursor: pointer;
            opacity: 1 !important;
        }

        .theme-comic_pop .btn-start:hover {
            transform: scale(1.1) !important;
        }

        .theme-comic_pop .btn-start:active {
            transform: scale(0.95) !important;
        }

        @keyframes comicStartPulse {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.06) rotate(2deg); }
            100% { transform: scale(1) rotate(0deg); }
        }

        .theme-comic_pop .comic-start-inner {
            position: relative !important;
            background-color: #000 !important;
            padding: 4px !important;
            clip-path: polygon(50% 0%, 63% 13%, 81% 6%, 83% 25%, 99% 23%, 92% 41%, 100% 57%, 87% 68%, 90% 87%, 72% 86%, 66% 100%, 50% 89%, 34% 100%, 28% 86%, 10% 87%, 13% 68%, 0% 57%, 8% 41%, 1% 23%, 17% 25%, 19% 6%, 37% 13%) !important;
            width: 140px !important;
            height: 140px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-shadow: 0 10px 0 #000 !important;
        }

        .theme-comic_pop .comic-start-inner::after {
            content: "" !important;
            position: absolute !important;
            top: 4px !important; left: 4px !important; right: 4px !important; bottom: 4px !important;
            background-color: #ff2a5f !important;
            clip-path: polygon(50% 0%, 63% 13%, 81% 6%, 83% 25%, 99% 23%, 92% 41%, 100% 57%, 87% 68%, 90% 87%, 72% 86%, 66% 100%, 50% 89%, 34% 100%, 28% 86%, 10% 87%, 13% 68%, 0% 57%, 8% 41%, 1% 23%, 17% 25%, 19% 6%, 37% 13%) !important;
            z-index: 1 !important;
        }

        .theme-comic_pop .comic-start-inner i {
            position: relative !important;
            z-index: 2 !important;
            color: #fff !important;
            font-size: 3.2rem !important;
            text-shadow: 2.5px 2.5px 0 #000 !important;
        }

        .theme-comic_pop .comic-start-text {
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #fff !important;
            font-size: 2.5rem !important;
            -webkit-text-stroke: 1.5px #000 !important;
            text-shadow: 4px 4px 0 #000 !important;
            margin-top: 10px !important;
        }

        /* Comic Popups */
        @keyframes comicFloat {
            0% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-8px) rotate(3deg); }
            100% { transform: translateY(0) rotate(-3deg); }
        }

        .comic-bubble-left {
            position: absolute !important;
            top: 55% !important;
            left: 5% !important;
            width: 140px !important;
            height: 110px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 80'><polygon points='50,5 60,20 78,10 75,30 95,30 82,48 95,65 75,60 78,78 60,68 50,80 40,68 22,78 25,60 5,65 18,48 5,30 25,30 22,10 40,20' fill='%2300ffcc' stroke='black' stroke-width='4'/></svg>") !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            z-index: 3 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            animation: comicFloat 3s ease-in-out infinite !important;
            cursor: pointer;
        }

        .comic-bubble-left::after {
            content: "YEAH!" !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #fff !important;
            font-size: 1.8rem !important;
            -webkit-text-stroke: 1.5px #000 !important;
            text-shadow: 2px 2px 0 #000 !important;
            transform: rotate(-5deg) !important;
        }

        .comic-bubble-right {
            position: absolute !important;
            top: 52% !important;
            right: 5% !important;
            width: 150px !important;
            height: 120px !important;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 80'><polygon points='50,5 62,22 80,12 77,32 97,32 84,50 97,68 77,63 80,81 62,71 50,83 38,71 20,81 23,63 3,68 16,50 3,32 23,32 20,12 38,22' fill='%23ff3300' stroke='black' stroke-width='4'/></svg>") !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            z-index: 3 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            animation: comicFloat 2.6s ease-in-out infinite alternate !important;
            cursor: pointer;
        }

        .comic-bubble-right::after {
            content: "BANG!" !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #fff !important;
            font-size: 1.9rem !important;
            -webkit-text-stroke: 1.5px #000 !important;
            text-shadow: 2px 2px 0 #000 !important;
            transform: rotate(5deg) !important;
        }

        /* Floating Ticket Launcher override */
        .theme-comic_pop .ticket-launcher {
            background-color: #fff300 !important;
            border: 4px solid #000 !important;
            color: #000 !important;
            box-shadow: 4px 4px 0 #000 !important;
            font-size: 1.6rem !important;
        }
        .theme-comic_pop .ticket-launcher:hover {
            transform: scale(1.1) !important;
        }

        /* Photo strip overlay styling */
        .theme-comic_pop .home-strip-container {
            background-color: #fff !important;
            border: 5px solid #000 !important;
            box-shadow: 6px 6px 0 #000 !important;
            border-radius: 4px !important;
        }

        .theme-comic_pop .strip-pic {
            border: 4px solid #000 !important;
            border-radius: 2px !important;
            background-color: #f7eedb !important;
            box-shadow: 4px 4px 0 #000 !important;
        }

        /* ------------------------------------------------------------- */
        /* GENERAL SCREEN OVERRIDES FOR COMIC POP-ART */
        /* ------------------------------------------------------------- */

        .theme-comic_pop .screen-header-back {
            background-color: #fff300 !important;
            border-bottom: 5px solid #000 !important;
            padding: 15px !important;
        }

        .theme-comic_pop .screen-title {
            color: #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.8rem !important;
            -webkit-text-stroke: 1.2px #000 !important;
            text-shadow: 2px 2px 0 #fff !important;
            letter-spacing: 1.5px !important;
        }

        .theme-comic_pop .btn-back {
            color: #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.3rem !important;
            background-color: #fff !important;
            border: 3px solid #000 !important;
            border-radius: 6px !important;
            padding: 5px 12px !important;
            box-shadow: 3px 3px 0 #000 !important;
        }
        .theme-comic_pop .btn-back:hover {
            transform: translateY(-2px) !important;
            box-shadow: 5px 5px 0 #000 !important;
        }

        /* Cards Layout */
        .theme-comic_pop .layout-card, 
        .theme-comic_pop .frame-card {
            background-color: #fff !important;
            border: 4px solid #000 !important;
            border-radius: 12px !important;
            box-shadow: 6px 6px 0 #000 !important;
            color: #000 !important;
            transition: all 0.2s ease !important;
        }

        .theme-comic_pop .layout-card:hover,
        .theme-comic_pop .frame-card:hover {
            transform: translate(-3px, -3px) !important;
            box-shadow: 9px 9px 0 #000 !important;
        }

        .theme-comic_pop .layout-icon {
            background-color: #00ffcc !important;
            border: 3px solid #000 !important;
            color: #000 !important;
            border-radius: 8px !important;
        }

        .theme-comic_pop .layout-desc {
            color: #555 !important;
            font-family: Arial, sans-serif !important;
            font-weight: bold !important;
            font-size: 0.85rem !important;
        }

        .theme-comic_pop .frame-card-preview {
            background-color: #f7eedb !important;
            border: 3px solid #000 !important;
            border-radius: 6px !important;
        }

        .theme-comic_pop .frame-card-name {
            color: #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.1rem !important;
        }

        /* Camera Screen */
        .theme-comic_pop .camera-slots-bar {
            background-color: #fff300 !important;
            background-image: radial-gradient(rgba(0,0,0,0.15) 15%, transparent 16%) !important;
            background-size: 10px 10px !important;
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            padding: 10px !important;
        }

        .theme-comic_pop .camera-rec-badge {
            background-color: #ff3300 !important;
            color: white !important;
            border: 3px solid #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .capture-slot-box {
            border: 3px solid #000 !important;
            background-color: #fff !important;
            border-radius: 4px !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .capture-slot-box.active {
            border-color: #ff007f !important;
            box-shadow: 0 0 15px #ff007f, 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .capture-slot-box.captured {
            border-color: #00ffcc !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .camera-trigger-btn,
        .theme-comic_pop .camera-trigger-btn-floating {
            background-color: #ff2a5f !important;
            color: white !important;
            border: 4px solid #000 !important;
            border-radius: 50% !important;
            box-shadow: 0 6px 0 #000 !important;
            transition: all 0.1s ease !important;
        }

        .theme-comic_pop .camera-trigger-btn:hover {
            transform: scale(1.05) !important;
            box-shadow: 0 8px 0 #000 !important;
        }

        .theme-comic_pop .camera-trigger-btn:active {
            transform: translateY(4px) !important;
            box-shadow: 0 2px 0 #000 !important;
        }

        .theme-comic_pop .section-title {
            color: #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            border-bottom: 3px solid #000 !important;
            font-size: 1.3rem !important;
        }

        /* Preview Screen & Canvas Doodle */
        .theme-comic_pop .preview-controllers {
            background-color: #fff300 !important;
            border-top: 5px solid #000 !important;
            padding: 15px !important;
        }

        .theme-comic_pop .btn-confirm {
            background-color: #00ffcc !important;
            color: #000 !important;
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            box-shadow: 0 5px 0 #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.3rem !important;
            letter-spacing: 1px !important;
        }

        .theme-comic_pop .btn-retake {
            background-color: #ff3300 !important;
            color: white !important;
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            box-shadow: 0 5px 0 #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.3rem !important;
            letter-spacing: 1px !important;
        }

        .theme-comic_pop .btn-confirm:hover,
        .theme-comic_pop .btn-retake:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 7px 0 #000 !important;
        }

        .theme-comic_pop .btn-confirm:active,
        .theme-comic_pop .btn-retake:active {
            transform: translateY(3px) !important;
            box-shadow: 0 2px 0 #000 !important;
        }

        .theme-comic_pop .btn-clear-canvas {
            background-color: #fff !important;
            color: #000 !important;
            border: 3px solid #000 !important;
            border-radius: 6px !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .stitched-canvas-container {
            border: 5px solid #000 !important;
            box-shadow: 8px 8px 0 #000 !important;
            border-radius: 8px !important;
            background-color: #fff !important;
        }

        /* Share Screen */
        .theme-comic_pop .share-info-panel {
            background-color: #fff !important;
            border: 5px solid #000 !important;
            border-radius: 12px !important;
            box-shadow: 8px 8px 0 #000 !important;
            color: #000 !important;
        }

        .theme-comic_pop .share-info-panel h2 {
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            color: #000 !important;
            font-size: 1.8rem !important;
            -webkit-text-stroke: 1px #000 !important;
        }

        .theme-comic_pop .share-qr-container {
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            background-color: #fff !important;
            padding: 10px !important;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.15) !important;
        }

        .theme-comic_pop .btn-open-portal {
            background-color: #00bfff !important;
            color: white !important;
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            box-shadow: 0 5px 0 #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.2rem !important;
        }

        .theme-comic_pop .btn-finish {
            background-color: #ff2a5f !important;
            color: white !important;
            border: 4px solid #000 !important;
            border-radius: 8px !important;
            box-shadow: 0 5px 0 #000 !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.2rem !important;
        }

        .theme-comic_pop .btn-open-portal:hover,
        .theme-comic_pop .btn-finish:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 7px 0 #000 !important;
        }

        .theme-comic_pop .btn-open-portal:active,
        .theme-comic_pop .btn-finish:active {
            transform: translateY(3px) !important;
            box-shadow: 0 2px 0 #000 !important;
        }

        /* Dialogs / Modals */
        .theme-comic_pop .sim-dialog {
            background-color: #fff !important;
            border: 5px solid #000 !important;
            border-radius: 12px !important;
            box-shadow: 10px 10px 0 #000 !important;
            color: #000 !important;
        }

        .theme-comic_pop .sim-dialog-title {
            color: #ff2a5f !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            font-size: 1.4rem !important;
            -webkit-text-stroke: 1px #000 !important;
            text-shadow: 2px 2px 0 #000 !important;
        }

        .theme-comic_pop .sim-dialog-desc {
            color: #444 !important;
            font-family: Arial, sans-serif !important;
            font-weight: bold !important;
        }

        .theme-comic_pop .sim-dialog-input {
            border: 3px solid #000 !important;
            background-color: #f7eedb !important;
            color: #000 !important;
            border-radius: 6px !important;
            font-family: Arial, sans-serif !important;
            font-weight: bold !important;
        }

        .theme-comic_pop .btn-dialog-confirm {
            background-color: #00ffcc !important;
            color: #000 !important;
            border: 3px solid #000 !important;
            border-radius: 6px !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        .theme-comic_pop .btn-dialog-cancel {
            background-color: #ff3300 !important;
            color: white !important;
            border: 3px solid #000 !important;
            border-radius: 6px !important;
            font-family: 'Luckiest Guy', 'Bangers', sans-serif !important;
            box-shadow: 3px 3px 0 #000 !important;
        }

        /* ------------------------------------------------------------- */
        /* DAGO ORANGE PREMIUM THEME (DAGO_ORANGE) OVERRIDES */
        /* ------------------------------------------------------------- */
        @import url('https://fonts.googleapis.com/css2?family=Caveat:wght@700&family=Montserrat:wght@700;800;900&display=swap');

        /* Theme Base Styling */
        .theme-dago_orange {
            background: linear-gradient(135deg, #FF6F00 0%, #FF3D00 100%) !important;
            background-color: #FF5E00 !important;
            font-family: 'Montserrat', 'Outfit', sans-serif !important;
        }

        .theme-dago_orange .kiosk-screen {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
            background-image: radial-gradient(rgba(0,0,0,0.12) 15%, transparent 16%) !important;
            background-size: 20px 20px !important;
            color: #ffffff !important;
            position: relative;
            overflow: hidden;
        }

        /* Screen Header Back overrides */
        .theme-dago_orange .screen-header-back {
            background-color: rgba(0,0,0,0.2) !important;
            border-bottom: 2px solid #000000 !important;
            padding: 12px 20px !important;
        }

        .theme-dago_orange .screen-title {
            font-weight: 900 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            text-shadow: 2px 2px 0px rgba(0,0,0,0.3) !important;
            font-size: 1.3rem !important;
        }

        .theme-dago_orange .btn-back {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 8px !important;
            font-weight: 800 !important;
            box-shadow: 2px 2px 0px #000000 !important;
        }

        .theme-dago_orange .btn-back:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0px #000000 !important;
        }

        /* Layout & Frame Selection Overrides */
        .theme-dago_orange .layout-card, 
        .theme-dago_orange .frame-card {
            background-color: #ffffff !important;
            color: #111111 !important;
            border: 3px solid #000000 !important;
            border-radius: 16px !important;
            box-shadow: 5px 5px 0px #000000 !important;
            transition: all 0.2s ease !important;
        }

        .theme-dago_orange .layout-card:hover,
        .theme-dago_orange .frame-card:hover {
            transform: translate(-3px, -3px) !important;
            box-shadow: 8px 8px 0px #000000 !important;
        }

        .theme-dago_orange .layout-icon {
            color: #ea580c !important;
            font-size: 2.2rem !important;
        }

        .theme-dago_orange .layout-name,
        .theme-dago_orange .frame-card-name {
            font-weight: 800 !important;
            color: #000000 !important;
        }

        .theme-dago_orange .layout-desc {
            color: #555555 !important;
            font-size: 0.8rem !important;
        }

        /* Camera Screen Overrides */
        .theme-dago_orange .camera-slots-bar {
            background-color: #ffffff !important;
            color: #111111 !important;
            border-left: 3px solid #000000 !important;
        }
        
        .tablet-device.phone-mode .theme-dago_orange .camera-slots-bar {
            border-left: none !important;
            border-top: 3px solid #000000 !important;
        }

        .theme-dago_orange .camera-slots-bar .section-title {
            color: #000000 !important;
            font-weight: 800 !important;
        }

        .theme-dago_orange .camera-rec-badge {
            background-color: #e11d48 !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 800 !important;
        }

        .theme-dago_orange .capture-slot-box {
            border: 2px solid #000000 !important;
            box-shadow: 3px 3px 0px #000000 !important;
            background-color: #f3f4f6 !important;
        }

        .theme-dago_orange .capture-slot-box.active {
            border-color: #ea580c !important;
            box-shadow: 3px 3px 0px #ea580c !important;
        }

        .theme-dago_orange .camera-trigger-btn,
        .theme-dago_orange .camera-trigger-btn-floating {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 3px solid #000000 !important;
            border-radius: 12px !important;
            font-weight: 900 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
        }

        .theme-dago_orange .camera-trigger-btn:hover,
        .theme-dago_orange .camera-trigger-btn-floating:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }

        /* Preview / Doodle Screen Overrides */
        .theme-dago_orange .preview-controllers {
            background-color: #ffffff !important;
            color: #111111 !important;
            border-left: 3px solid #000000 !important;
        }
        
        .tablet-device.phone-mode .theme-dago_orange .preview-controllers {
            border-left: none !important;
            border-top: 3px solid #000000 !important;
        }

        .theme-dago_orange .doodle-tools .section-title {
            color: #000000 !important;
            font-weight: 800 !important;
        }

        .theme-dago_orange .color-circle {
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
        }

        .theme-dago_orange .color-circle.active {
            transform: scale(1.15) !important;
            box-shadow: 0 0 10px rgba(0,0,0,0.5) !important;
            border-width: 3px !important;
        }

        .theme-dago_orange .btn-confirm {
            background-color: #0000FF !important;
            color: #ffffff !important;
            border: 3px solid #000000 !important;
            border-radius: 12px !important;
            font-weight: 900 !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }

        .theme-dago_orange .btn-confirm:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }

        .theme-dago_orange .btn-retake {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 3px solid #000000 !important;
            border-radius: 12px !important;
            font-weight: 900 !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }

        .theme-dago_orange .btn-retake:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }

        .theme-dago_orange .btn-clear-canvas {
            background-color: #f3f4f6 !important;
            color: #111111 !important;
            border: 2px solid #000000 !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
        }

        /* Home Screen Overrides */
        .theme-dago_orange .screen-home {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 40px !important;
            gap: 20px !important;
        }

        .theme-dago_orange .home-logo-box {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            gap: 5px !important;
            margin-top: 20px !important;
            animation: dagoLogoFloat 4s ease-in-out infinite alternate !important;
            cursor: pointer;
        }

        @keyframes dagoLogoFloat {
            0% { transform: translateY(0px) rotate(-1deg); }
            50% { transform: translateY(-8px) rotate(1deg); }
            100% { transform: translateY(0px) rotate(-1deg); }
        }

        .theme-dago_orange .dago-logo-white {
            font-family: 'Montserrat', sans-serif !important;
            font-size: clamp(3.2rem, 7vw, 5.5rem) !important;
            font-weight: 900 !important;
            color: #ffffff !important;
            letter-spacing: -2px !important;
            text-shadow: 4px 4px 0px #000000 !important;
        }

        .theme-dago_orange .dago-logo-sub {
            font-family: 'Montserrat', sans-serif !important;
            font-size: clamp(0.9rem, 2vw, 1.4rem) !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: 3px !important;
            text-transform: uppercase !important;
            background-color: #000000 !important;
            padding: 4px 12px !important;
            border-radius: 4px !important;
        }

        .theme-dago_orange .dago-logo-sub2 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: clamp(0.75rem, 1.5vw, 1rem) !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: 2px !important;
            opacity: 0.9;
        }

        .theme-dago_orange .btn-start {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 4px solid #000000 !important;
            border-radius: 50px !important;
            font-size: 2.2rem !important;
            font-weight: 900 !important;
            padding: 16px 60px !important;
            box-shadow: 6px 6px 0px #000000 !important;
            animation: dagoPulse 2s infinite !important;
            letter-spacing: 1px !important;
            opacity: 1 !important;
        }

        .theme-dago_orange .btn-start:hover {
            transform: translate(-3px, -3px) !important;
            box-shadow: 9px 9px 0px #000000 !important;
            animation-play-state: paused !important;
        }

        @keyframes dagoPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.04); }
            100% { transform: scale(1); }
        }

        .theme-dago_orange .home-slogan {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border: 2px solid #ffffff !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            padding: 6px 16px !important;
            border-radius: 20px !important;
            font-size: 0.9rem !important;
            margin-top: 10px !important;
            text-transform: uppercase !important;
        }

        .theme-dago_orange .home-strip-container {
            border: 3px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            background-color: #ffffff !important;
        }

        /* ------------------------------------------------------------- */
        /* CUSTOM DAGO SHARE SCREEN LAYOUT CSS */
        /* ------------------------------------------------------------- */
        .dago-share-layout {
            display: none;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
        }

        .theme-dago_orange .default-share-layout {
            display: none !important;
        }

        .theme-dago_orange .dago-share-layout {
            display: grid !important;
            grid-template-columns: 1.15fr 1fr;
            grid-template-rows: auto auto auto;
            align-items: center;
            justify-items: center;
            width: 100%;
            height: 100%;
            padding: 10px 30px;
            gap: 10px;
        }

        /* Emojis */
        .dago-emojis {
            position: absolute;
            top: 25px;
            left: 25px;
            font-size: 1.8rem;
            animation: dagoEmojiBounce 2s infinite alternate;
            z-index: 10;
        }

        @keyframes dagoEmojiBounce {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-5px) scale(1.1); }
        }

        /* Splat close button */
        .dago-splat-top {
            position: absolute;
            top: 15px;
            right: 25px;
            width: 55px;
            height: 55px;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dago-splat-top svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(2px 2px 0px #000);
            transition: transform 0.2s;
        }

        .dago-splat-top:hover svg {
            transform: scale(1.1) rotate(15deg);
        }

        .dago-splat-close {
            position: absolute;
            font-size: 1.8rem;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
            margin-top: -2px;
        }

        /* Brand Header */
        .dago-brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 30px !important;
            margin-bottom: 0;
            text-align: center;
            z-index: 2;
            grid-column: 2;
            grid-row: 1;
            align-self: start !important;
        }

        .dago-brand-title-box {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dago-brand-main {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 2.8rem;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -1.5px;
            text-shadow: 3px 3px 0px #000;
        }

        .dago-brand-sub {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1;
            background-color: #000;
            padding: 4px 6px;
            border-radius: 4px;
            border: 1px solid #fff;
        }

        .dago-brand-sub1 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.6rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
        }

        .dago-brand-sub2 {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.55rem;
            font-weight: 700;
            color: #ff9100;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .dago-brand-handwriting {
            font-family: 'Caveat', cursive !important;
            font-size: 2rem;
            color: #000000;
            transform: rotate(-3deg);
            margin-top: -8px;
            animation: dagoHandwritingWiggle 3s ease-in-out infinite alternate;
        }

        @keyframes dagoHandwritingWiggle {
            0% { transform: rotate(-5deg); }
            100% { transform: rotate(-1deg); }
        }

        /* Printer Slot & Receipt Viewport */
        .dago-printer-slot-wrapper {
            position: relative;
            width: 290px;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 5;
            grid-column: 1;
            grid-row: 1 / span 3;
            justify-self: center;
            height: 380px;
            align-self: start !important;
            margin-top: 40px !important;
        }

        .dago-printer-slot {
            width: 100%;
            height: 28px;
            background: linear-gradient(to bottom, #eaeaea 0%, #c5c5c5 30%, #9e9e9e 70%, #707070 100%) !important;
            border-radius: 6px;
            box-shadow: inset 0 2px 5px rgba(255,255,255,0.8), 0 6px 12px rgba(0,0,0,0.4);
            border: 3px solid #5a5a5a;
            position: relative;
            z-index: 10;
        }

        .dago-printer-slot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 15px;
            right: 15px;
            height: 6px;
            background-color: #1a1a1a;
            border-radius: 3px;
            transform: translateY(-50%);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.8);
        }

        .dago-printer-led {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background-color: #22c55e;
            border-radius: 50%;
            box-shadow: 0 0 8px #22c55e;
            animation: dagoLedPulse 1s infinite alternate;
            z-index: 12;
        }

        @keyframes dagoLedPulse {
            0% { opacity: 0.3; }
            100% { opacity: 1; }
        }

        /* Receipt Viewport & Paper */
        .dago-receipt-viewport {
            width: 260px;
            height: 350px;
            overflow: hidden;
            position: relative;
            margin-top: -12px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            z-index: 5;
        }

        .dago-receipt-paper {
            width: 100%;
            background-color: #ffffff;
            color: #111111;
            padding: 16px 16px 25px 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
            border-left: 2px solid #000;
            border-right: 2px solid #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            transform: translateY(0);
            position: relative;
        }

        /* Slide Down Print Animation */
        .dago-receipt-paper.printing {
            animation: dagoPrintSlideDown 4s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
        }

        @keyframes dagoPrintSlideDown {
            0% { transform: translateY(-88%); opacity: 0.6; }
            15% { transform: translateY(-60%); opacity: 1; }
            100% { transform: translateY(0); opacity: 1; }
        }

        /* Receipt Paper Details */
        .dago-receipt-paper-header {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 2px dashed #000000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .dago-receipt-paper-logo {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 1.4rem;
            font-weight: 900;
            color: #000;
            letter-spacing: -0.5px;
        }

        .dago-receipt-paper-title {
            font-family: 'Playfair Display', serif !important;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 1px;
            margin-top: 2px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            width: 100%;
            text-align: center;
            padding: 2px 0;
        }

        .dago-receipt-paper-date {
            font-size: 0.65rem;
            font-weight: 600;
            color: #444;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .dago-receipt-paper-content {
            width: 140px;
            height: 200px;
            background-color: #f3f4f6;
            border: 2px solid #000;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 6px 0;
            box-shadow: inset 0 0 5px rgba(0,0,0,0.15);
        }

        .dago-receipt-paper-content img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dago-receipt-paper-footer {
            font-size: 0.55rem;
            color: #333;
            text-align: center;
            line-height: 1.3;
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 8px;
        }

        .dago-receipt-zigzag {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 10px;
            background-image: linear-gradient(-45deg, transparent 5px, #ffffff 5px), linear-gradient(45deg, transparent 5px, #ffffff 5px);
            background-size: 10px 10px;
            background-repeat: repeat-x;
            z-index: 10;
        }

        /* Download Toast overlay */
        .dago-download-toast {
            font-family: 'Caveat', cursive !important;
            font-size: 1.4rem;
            color: #ffffff;
            margin-top: 0;
            margin-bottom: 0;
            text-shadow: 2px 2px 0px #000;
            animation: dagoToastPulse 1.5s infinite alternate;
            grid-column: 2;
            grid-row: 2;
            align-self: start !important;
        }

        @keyframes dagoToastPulse {
            0% { transform: scale(0.96); opacity: 0.9; }
            100% { transform: scale(1.04); opacity: 1; }
        }

        /* Bottom Controls Row */
        .dago-bottom-controls {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            width: 100%;
            max-width: 420px;
            z-index: 2;
            margin-top: 10px !important;
            grid-column: 2;
            grid-row: 3;
            align-self: start !important;
        }

        .dago-actions-column {
            display: flex;
            flex-direction: column;
            gap: 10px;
            justify-content: center;
        }

        .dago-qr-column {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dago-qr-card {
            background-color: #ffffff;
            border: 3px solid #000000;
            border-radius: 16px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            box-shadow: 4px 4px 0px #000000;
            width: 130px;
        }

        .dago-qr-card canvas {
            width: 100px !important;
            height: 100px !important;
        }

        .dago-qr-label {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.65rem;
            font-weight: 900;
            color: #000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Dago Buttons */
        .dago-btn {
            font-family: 'Montserrat', sans-serif !important;
            font-size: 0.85rem;
            font-weight: 800;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: 100%;
            box-sizing: border-box;
        }

        .dago-btn-print {
            background-color: #ffffff;
            color: #000000;
            border: 3px solid #000000;
            box-shadow: 3px 3px 0px #000000;
        }

        .dago-btn-print:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px #000000;
        }

        .dago-btn-retake {
            background-color: #ffffff;
            color: #000000;
            border: 3px solid #000000;
            box-shadow: 3px 3px 0px #000000;
        }

        .dago-btn-retake:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px #000000;
        }

        .dago-btn-finish {
            background-color: #0000FF;
            color: #ffffff;
            border: 3px solid #000000;
            box-shadow: 3px 3px 0px #000000;
            font-weight: 900;
        }

        .dago-btn-finish:hover {
            background-color: #1a1aff;
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0px #000000;
        }

        .dago-demo-btn {
            display: inline-block !important;
            margin-top: 15px !important;
            background-color: #000000 !important;
            color: #ffffff !important;
            border: 2px solid #ffffff !important;
            border-radius: 20px !important;
            font-size: 0.85rem !important;
            font-weight: 800 !important;
            padding: 8px 24px !important;
            cursor: pointer !important;
            box-shadow: 2px 2px 0px #000000 !important;
            transition: all 0.2s !important;
            font-family: 'Outfit', sans-serif !important;
            z-index: 20;
            position: relative;
        }
        .dago-demo-btn:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 3px 3px 0px #000000 !important;
            background-color: #111111 !important;
        }

        /* Responsiveness in Phone Mode (Tall Bezel) */
        .tablet-device.phone-mode .dago-share-layout {
            padding: 10px 10px !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            overflow-y: auto !important;
            gap: 10px !important;
        }

        .tablet-device.phone-mode .dago-emojis {
            font-size: 1.4rem;
            top: 15px;
            left: 15px;
        }

        .tablet-device.phone-mode .dago-splat-top {
            top: 5px;
            right: 15px;
            width: 45px;
            height: 45px;
        }

        .tablet-device.phone-mode .dago-brand-header {
            grid-column: unset !important;
            grid-row: unset !important;
            order: 1 !important;
            margin-bottom: 5px !important;
        }

        .tablet-device.phone-mode .dago-brand-main {
            font-size: 2.2rem;
        }

        .tablet-device.phone-mode .dago-brand-handwriting {
            font-size: 1.6rem;
            margin-top: -6px;
        }

        .tablet-device.phone-mode .dago-printer-slot-wrapper {
            grid-column: unset !important;
            grid-row: unset !important;
            width: 250px !important;
            height: auto !important;
            order: 2 !important;
        }

        .tablet-device.phone-mode .dago-receipt-viewport {
            width: 230px;
            height: 250px !important;
        }

        .tablet-device.phone-mode .dago-receipt-paper {
            padding: 12px 12px 20px 12px;
        }

        .tablet-device.phone-mode .dago-receipt-paper-logo {
            font-size: 1.1rem;
        }

        .tablet-device.phone-mode .dago-receipt-paper-title {
            font-size: 1rem;
        }

        .tablet-device.phone-mode .dago-download-toast {
            grid-column: unset !important;
            grid-row: unset !important;
            order: 3 !important;
        }

        .tablet-device.phone-mode .dago-bottom-controls {
            grid-column: unset !important;
            grid-row: unset !important;
            order: 4 !important;
            grid-template-columns: 1fr;
            gap: 15px;
            max-width: 260px;
            margin-top: 10px;
        }

        .tablet-device.phone-mode .dago-qr-column {
            order: -1; /* QR Code on top in mobile */
        }

        .tablet-device.phone-mode .dago-qr-card {
            width: 100%;
            flex-direction: row;
            justify-content: center;
            gap: 15px;
            padding: 8px;
        }

        .tablet-device.phone-mode .dago-qr-card canvas {
            width: 70px !important;
            height: 70px !important;
        }

        /* ------------------------------------------------------------- */
        /* CREATIVE DYNAMIC THEME (CREATIVE_DYNAMIC) OVERRIDES */
        /* ------------------------------------------------------------- */
        .theme-creative_dynamic {
            background: linear-gradient(135deg, #0f0b1e, #241442, #1b072b, #0d061a) !important;
            background-size: 400% 400% !important;
            animation: creativeBgShift 15s ease infinite !important;
            position: relative;
            overflow: hidden;
        }
        @keyframes creativeBgShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .theme-creative_dynamic .kiosk-screen {
            background: transparent !important;
            box-shadow: none !important;
            position: relative;
        }

        .creative-particles {
            display: none;
        }
        .theme-creative_dynamic .creative-particles {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }
        .theme-creative_dynamic .particle {
            position: absolute;
            color: rgba(255,255,255,0.4);
            font-size: 1.5rem;
            animation: creativeDrift 8s infinite ease-in-out;
        }
        .theme-creative_dynamic .particle:nth-child(1) { top: 15%; left: 15%; animation-duration: 8s; animation-delay: 0s; }
        .theme-creative_dynamic .particle:nth-child(2) { top: 25%; right: 20%; animation-duration: 11s; animation-delay: -2s; font-size: 1.8rem; }
        .theme-creative_dynamic .particle:nth-child(3) { bottom: 30%; left: 20%; animation-duration: 9s; animation-delay: -4s; }
        .theme-creative_dynamic .particle:nth-child(4) { bottom: 15%; right: 15%; animation-duration: 13s; animation-delay: -1s; font-size: 2rem; }
        .theme-creative_dynamic .particle:nth-child(5) { top: 50%; left: 50%; animation-duration: 10s; animation-delay: -3s; }
        @keyframes creativeDrift {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0.3; }
            50% { transform: translate(15px, -25px) rotate(180deg); opacity: 0.7; }
            100% { transform: translate(0, 0) rotate(360deg); opacity: 0.3; }
        }

        .theme-creative_dynamic .logo-char {
            display: inline-block;
            animation: creativeWaveBounce 1.5s infinite ease-in-out;
            animation-delay: calc(var(--char-idx) * 0.06s);
            background: linear-gradient(45deg, #a855f7, #ec4899, #d946ef);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 10px rgba(217, 70, 239, 0.4);
        }
        @keyframes creativeWaveBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        .theme-creative_dynamic .home-logo-box {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            margin-top: 40px !important;
            z-index: 10 !important;
        }
        .theme-creative_dynamic .home-slogan {
            background: #1e1b4b !important;
            border: 2px solid #a855f7 !important;
            border-radius: 50px !important;
            padding: 8px 24px !important;
            color: #e9d5ff !important;
            font-weight: 700 !important;
            font-size: 0.9rem !important;
            letter-spacing: 1px !important;
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.3) !important;
            margin-top: 15px !important;
            display: inline-block !important;
            animation: creativeSloganPulse 3s infinite ease-in-out !important;
        }
        @keyframes creativeSloganPulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.03); opacity: 1; }
        }
        .theme-creative_dynamic .btn-start {
            background: #a855f7 !important;
            color: #ffffff !important;
            border: 3px solid #d946ef !important;
            border-radius: 50px !important;
            font-size: 1.8rem !important;
            font-weight: 900 !important;
            padding: 15px 45px !important;
            box-shadow: 0 0 20px rgba(217, 70, 239, 0.5) !important;
            transition: all 0.3s ease !important;
            position: relative;
            overflow: visible;
            cursor: pointer;
            opacity: 1 !important;
        }
        .theme-creative_dynamic .btn-start::after {
            content: '';
            position: absolute;
            top: -6px; left: -6px; right: -6px; bottom: -6px;
            border: 3px solid #ec4899;
            border-radius: 50px;
            opacity: 0;
            animation: creativeBtnRing 2s infinite ease-out;
            pointer-events: none;
        }
        @keyframes creativeBtnRing {
            0% { transform: scale(0.95); opacity: 0.8; }
            100% { transform: scale(1.25); opacity: 0; }
        }
        .theme-creative_dynamic .btn-start:hover {
            transform: translateY(-3px) scale(1.05) !important;
            box-shadow: 0 0 30px rgba(217, 70, 239, 0.8) !important;
        }

        .theme-creative_dynamic .screen-header-back {
            background: #18122b !important;
            border-bottom: 2px solid #d946ef !important;
        }
        .theme-creative_dynamic .screen-title {
            color: #f1e9ff !important;
            text-shadow: 0 0 10px rgba(168, 85, 247, 0.5) !important;
        }
        .theme-creative_dynamic .btn-back {
            background: #ec4899 !important;
            color: #ffffff !important;
            border: 2px solid #ffffff !important;
            border-radius: 50% !important;
        }
        .theme-creative_dynamic .layout-card,
        .theme-creative_dynamic .frame-card {
            background: #18122b !important;
            border: 2px solid #241442 !important;
            color: #f1e9ff !important;
            transition: all 0.3s ease !important;
        }
        .theme-creative_dynamic .layout-card:hover,
        .theme-creative_dynamic .frame-card:hover {
            border-color: #d946ef !important;
            box-shadow: 0 0 15px rgba(217, 70, 239, 0.4) !important;
            transform: translateY(-5px) !important;
        }
        .theme-creative_dynamic .layout-icon {
            color: #a855f7 !important;
        }
        .theme-creative_dynamic .camera-slots-bar {
            background: #18122b !important;
            border: 2px solid #d946ef !important;
        }
        .theme-creative_dynamic .capture-slot-box {
            background: #0d061a !important;
            border: 2px solid #241442 !important;
        }
        .theme-creative_dynamic .capture-slot-box.active {
            border-color: #a855f7 !important;
            box-shadow: 0 0 10px rgba(168, 85, 247, 0.6) !important;
        }
        .theme-creative_dynamic .capture-slot-box.captured {
            border-color: #ec4899 !important;
        }
        .theme-creative_dynamic .camera-trigger-btn {
            background: radial-gradient(circle, #ec4899 0%, #a855f7 100%) !important;
            border: 4px solid #ffffff !important;
            box-shadow: 0 0 20px rgba(217, 70, 239, 0.6) !important;
        }
        .theme-creative_dynamic .camera-trigger-btn:active {
            transform: scale(0.9) !important;
        }
        .theme-creative_dynamic .btn-confirm {
            background: #a855f7 !important;
            color: #ffffff !important;
            border-radius: 12px !important;
            box-shadow: 0 0 10px rgba(168, 85, 247, 0.4) !important;
        }
        .theme-creative_dynamic .btn-retake {
            background: #18122b !important;
            color: #f1e9ff !important;
            border: 2px solid #d946ef !important;
            border-radius: 12px !important;
        }
        .theme-creative_dynamic .share-info-panel {
            background: #18122b !important;
            border: 2px solid #d946ef !important;
            color: #f1e9ff !important;
        }
        .theme-creative_dynamic .btn-open-portal {
            background: #ec4899 !important;
            color: white !important;
            border-radius: 12px !important;
        }
        .theme-creative_dynamic .btn-finish {
            background: #a855f7 !important;
            color: white !important;
            border-radius: 12px !important;
        }
        .theme-creative_dynamic .sim-dialog {
            background: #18122b !important;
            border: 2px solid #d946ef !important;
            color: #f1e9ff !important;
        }
        .theme-creative_dynamic .sim-dialog-input {
            background: #0d061a !important;
            border: 1px solid #241442 !important;
            color: #f1e9ff !important;
        }
        .theme-creative_dynamic .btn-dialog-confirm {
            background: #a855f7 !important;
            color: white !important;
        }
        .theme-creative_dynamic .btn-dialog-cancel {
            background: #18122b !important;
            color: #f1e9ff !important;
            border: 1px solid #241442 !important;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <!-- Sidebar Control Panel -->
        <div class="control-panel">
            <div class="panel-title">Kiosk <span>Simulator</span></div>
            
            <!-- Setup Kiosk Mode -->
            <div class="section-box">
                <div class="section-title">Mode Kiosk (Setelan Admin)</div>
                <div class="kiosk-mode-selector">
                    <button class="mode-btn active" id="modeMultiEvent" onclick="setKioskMode('MULTI_EVENT')">Multi-Event (Kode)</button>
                    <button class="mode-btn" id="modeDedicated" onclick="setKioskMode('DEDICATED')">Satu Event Terkunci</button>
                </div>
            </div>

            <!-- Setup Device Orientation -->
            <div class="section-box">
                <div class="section-title">Orientasi Simulator</div>
                <div class="kiosk-mode-selector">
                    <button class="mode-btn active" id="btnDeviceTablet" onclick="setDeviceMode('TABLET')"><i class="fa-solid fa-tablet-screen-button"></i> &nbsp;Tablet</button>
                    <button class="mode-btn" id="btnDevicePhone" onclick="setDeviceMode('PHONE')"><i class="fa-solid fa-mobile-screen-button"></i> &nbsp;HP Potret</button>
                </div>
            </div>

            <!-- Active Event Configuration -->
            <div class="section-box" id="eventSelectBox" style="display:none;">
                <div class="section-title">Pilih Event Terkunci (Scenario A)</div>
                <select id="kioskEventSelect" onchange="onDedicatedEventChange()">
                    <?php
                    if (!empty($configData['events'])) {
                        foreach ($configData['events'] as $evt) {
                            if ($evt['id'] === 'general') continue;
                            echo '<option value="' . htmlspecialchars($evt['id']) . '">' . htmlspecialchars($evt['name']) . '</option>';
                        }
                    } else {
                        echo '<option value="">Tidak ada event kustom</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Active Theme Configuration -->
            <div class="section-box">
                <div class="section-title">Pilih Tema Kiosk (Real-time)</div>
                <select id="kioskThemeSelect" onchange="onThemeSelectChange()">
                    <option value="NEON_RED" <?php echo ($appTheme === 'NEON_RED') ? 'selected' : ''; ?>>Neon Red</option>
                    <option value="CUTE_PASTEL" <?php echo ($appTheme === 'CUTE_PASTEL') ? 'selected' : ''; ?>>Cute Pastel</option>
                    <option value="CUTE_NARA" <?php echo ($appTheme === 'CUTE_NARA') ? 'selected' : ''; ?>>Cute Nara (Pinky Flower)</option>
                    <option value="LUXURY_GOLD" <?php echo ($appTheme === 'LUXURY_GOLD') ? 'selected' : ''; ?>>Luxury Gold</option>
                    <option value="MINIMAL_MODERN" <?php echo ($appTheme === 'MINIMAL_MODERN') ? 'selected' : ''; ?>>Minimal Modern</option>
                    <option value="COMIC_POP" <?php echo ($appTheme === 'COMIC_POP') ? 'selected' : ''; ?>>Comic Pop-Art</option>
                    <option value="DAGO_ORANGE" <?php echo ($appTheme === 'DAGO_ORANGE') ? 'selected' : ''; ?>>Dago Orange Kiosk</option>
                    <option value="CREATIVE_DYNAMIC" <?php echo ($appTheme === 'CREATIVE_DYNAMIC') ? 'selected' : ''; ?>>Creative Dynamic (Glowing)</option>
                </select>
            </div>

            <!-- Active Package Configuration -->
            <div class="section-box">
                <div class="section-title">Pilih Paket Simulasi</div>
                <select id="kioskPackageSelect" onchange="onPackageSelectChange()">
                    <?php
                    if (!empty($packagesList)) {
                        foreach ($packagesList as $pkg) {
                            echo '<option value="' . htmlspecialchars($pkg['id']) . '">' . htmlspecialchars($pkg['name']) . '</option>';
                        }
                    } else {
                        echo '<option value="">Tidak ada paket kustom</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Admin credentials -->
            <div class="section-box">
                <div class="section-title">Kredensial / Sinkronisasi</div>
                <label>PIN Admin Kiosk (5 Ketukan Logo)</label>
                <input type="password" id="simAdminPin" value="1234" maxlength="4" style="text-align: center; letter-spacing: 2px;">
                <button class="btn-panel" style="margin-top: 6px;" onclick="syncServerConfig()">Sinkronisasi Ulang Server</button>
            </div>

            <!-- Simulator Instructions -->
            <div class="section-box" style="flex: 1; justify-content: flex-end; opacity: 0.85;">
                <div class="section-title">Cara Pengujian</div>
                <p style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.5;">
                    1. Atur mode Kiosk di panel ini.<br>
                    2. Tekan <b>START</b> di layar tablet.<br>
                    3. Ambil foto menggunakan webcam PC Anda.<br>
                    4. Beri tanda tangan doodle neon.<br>
                    5. Simpan/Unggah dan scan QR Code atau buka portal pelanggan untuk melihat template event dinamis di browser!
                </p>
            </div>
        </div>

        <!-- Simulator Device Area -->
        <div class="simulator-area">
            <div class="tablet-device">
                <div class="tablet-lens"></div>
                <div class="screen-container theme-<?php echo strtolower($appTheme); ?>">
                    
                    <!-- Status Bar -->
                    <div class="status-bar">
                        <span id="statusBarMode">Kiosk Mode: Multi-Event</span>
                        <div class="status-bar-icons">
                            <span id="statusClock">12:00</span>
                            <span>📶 🔋 100%</span>
                        </div>
                    </div>

                    <!-- Floating Rental Timer Badge -->
                    <div id="rentalTimerBadge" class="rental-timer-badge" style="display: none;">
                        <i class="fa-solid fa-hourglass-half"></i> <span id="rentalTimerText">00:00:00</span>
                    </div>

                    <!-- SCREEN 1: HOME SCREEN -->
                    <div class="kiosk-screen screen-home active" id="screenHome">
                        <!-- Floating Particles for CREATIVE_DYNAMIC -->
                        <div class="creative-particles">
                            <span class="particle">✨</span>
                            <span class="particle">🌟</span>
                            <span class="particle">💫</span>
                            <span class="particle">✨</span>
                            <span class="particle">🌟</span>
                        </div>
                        <div class="home-logo-box" onclick="handleLogoTaps()">
                            <div class="home-logo-part1" id="logoPart1">Jeprat</div>
                            <div class="home-logo-part2" id="logoPart2">Jepret</div>
                        </div>

                        <div class="home-center">
                            <button class="btn-start" onclick="startSession()">START</button>
                            <div class="home-slogan" id="homeSlogan">All You need is special</div>
                            <button class="dago-demo-btn" onclick="testPrinterAnimation()">⚡ Test Printer Animation</button>
                        </div>

                        <!-- Scrolling Tilted History Strip -->
                        <div class="home-strip-container">
                            <div class="scrolling-strip" id="historyStrip">
                                <!-- Repeated 4 times to ensure seamless vertical loop over 700px viewport -->
                                <!-- Set 1 -->
                                <div class="strip-pic"></div>
                                <div class="strip-pic" style="filter: hue-rotate(90deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(180deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(270deg);"></div>
                                <!-- Set 2 -->
                                <div class="strip-pic"></div>
                                <div class="strip-pic" style="filter: hue-rotate(90deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(180deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(270deg);"></div>
                                <!-- Set 3 -->
                                <div class="strip-pic"></div>
                                <div class="strip-pic" style="filter: hue-rotate(90deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(180deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(270deg);"></div>
                                <!-- Set 4 -->
                                <div class="strip-pic"></div>
                                <div class="strip-pic" style="filter: hue-rotate(90deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(180deg);"></div>
                                <div class="strip-pic" style="filter: hue-rotate(270deg);"></div>
                            </div>
                        </div>

                        <!-- Floating Ticket Launcher (Scenario B) -->
                        <div class="ticket-launcher" id="ticketLauncher" onclick="openTicketModal()">
                            <span class="ticket-icon"><i class="fa-solid fa-ticket"></i></span>
                        </div>
                    </div>

                    <!-- SCREEN 2: LAYOUT SELECT SCREEN -->
                    <div class="kiosk-screen" id="screenLayout">
                        <div class="screen-header-back">
                            <button class="btn-back" onclick="navigateBack('home')">◀ Kiosk Utama</button>
                            <span class="screen-title">Pilih Layout Foto</span>
                            <div style="width: 80px;"></div>
                        </div>
                        <div class="layout-options">
                            <div class="layout-card" onclick="selectLayout('strip')">
                                <div class="layout-icon"><i class="fa-solid fa-film"></i></div>
                                <div class="layout-name">Strip (4x1)</div>
                                <div class="layout-desc">4 pose memanjang vertikal yang klasik</div>
                            </div>
                            <div class="layout-card" onclick="selectLayout('grid')">
                                <div class="layout-icon"><i class="fa-solid fa-table-cells-large"></i></div>
                                <div class="layout-name">Grid 2x2</div>
                                <div class="layout-desc">4 pose kotak sejajar simetris</div>
                            </div>
                            <div class="layout-card" onclick="selectLayout('postcard')">
                                <div class="layout-icon"><i class="fa-solid fa-image"></i></div>
                                <div class="layout-name">Postcard</div>
                                <div class="layout-desc">1 pose utama besar lanskap premium</div>
                            </div>
                        </div>
                    </div>

                    <!-- SCREEN 3: FRAME SELECT SCREEN -->
                    <div class="kiosk-screen" id="screenFrame">
                        <div class="screen-header-back">
                            <button class="btn-back" onclick="navigateBack('layout')">◀ Layout</button>
                            <span class="screen-title">Pilih Bingkai Kreatif</span>
                            <div style="width: 80px;"></div>
                        </div>
                        <div class="frame-selector-scroll" id="frameContainer">
                            <!-- Frame cards will be loaded dynamically by JS -->
                        </div>
                    </div>

                    <!-- SCREEN 4: CAMERA CAPTURE SCREEN -->
                    <div class="kiosk-screen" id="screenCamera">
                        <div class="camera-screen-layout">
                            <div class="camera-preview-container">
                                <div class="camera-rec-badge">
                                    <div class="rec-dot"></div>
                                    <span>REC PHOTO <span id="currentSlotNum">1</span>/4</span>
                                </div>
                                <video class="camera-video" id="videoPreview" autoplay playsinline muted></video>
                                <div class="countdown-overlay" id="countdownOverlay">
                                    <div class="countdown-number" id="countdownNum">3</div>
                                </div>
                                <div class="mock-camera-placeholder" id="mockCameraPlaceholder" style="display:none;">
                                    <span>📷 Silakan Izinkan Akses Kamera Browser</span>
                                    <span style="font-size:0.75rem; font-weight:normal;">(Simulasi capture otomatis jika kamera tidak diizinkan)</span>
                                </div>
                                <button class="camera-trigger-btn-floating" id="cameraTriggerBtnFloating" style="display:none;" onclick="triggerWebcamCaptureSequence()">Ambil Foto</button>
                            </div>
                            <div class="camera-slots-bar">
                                <div class="section-title">Hasil Jepretan</div>
                                <div id="captureSlotsContainer">
                                    <!-- Capture boxes will be added by JS -->
                                </div>
                                <button class="camera-trigger-btn" id="cameraTriggerBtn" onclick="triggerWebcamCaptureSequence()">Ambil Foto</button>
                            </div>
                        </div>
                    </div>

                    <!-- SCREEN 5: PREVIEW AND DOODLE SCREEN -->
                    <div class="kiosk-screen" id="screenPreview">
                        <div class="preview-screen-layout">
                            <div class="canvas-area">
                                <div class="stitched-canvas-container" id="stitchedCanvasContainer">
                                    <canvas id="bgCanvas"></canvas>
                                    <canvas id="drawCanvas"></canvas>
                                </div>
                            </div>
                            <div class="preview-controllers">
                                <div class="doodle-tools">
                                    <div class="section-title"><i class="fa-solid fa-pen-nib"></i> &nbsp;Gambar Tanda Tangan</div>
                                    <p style="font-size:0.7rem; color:var(--text-muted); line-height:1.4;">Goreskan kuas tanda tangan digital Anda di atas foto strip</p>
                                    
                                    <label>Warna Neon Kuas</label>
                                    <div class="color-palette">
                                        <div class="color-circle active" style="background-color: #ff3366;" onclick="setDoodleColor('#ff3366', this)"></div>
                                        <div class="color-circle" style="background-color: #00ffff;" onclick="setDoodleColor('#00ffff', this)"></div>
                                        <div class="color-circle" style="background-color: #f7b801;" onclick="setDoodleColor('#f7b801', this)"></div>
                                        <div class="color-circle" style="background-color: #39ff14;" onclick="setDoodleColor('#39ff14', this)"></div>
                                    </div>
                                    
                                    <button class="btn-clear-canvas" onclick="clearDoodleCanvas()">Bersihkan Coreotan</button>
                                </div>

                                <div class="preview-footer-actions">
                                    <button class="btn-confirm" onclick="finishAndUploadSession()">Simpan & Unggah</button>
                                    <button class="btn-retake" onclick="retakeSession()">◀ Ambil Ulang</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SCREEN 6: SHARE AND PRINT SCREEN -->
                    <div class="kiosk-screen screen-share" id="screenShare">
                        <!-- Default Share Layout (for other themes) -->
                        <div class="default-share-layout">
                            <div class="share-strip-view">
                                <img id="finalStitchedImage" src="" alt="Stitched Photo Strip">
                            </div>
                            <div class="share-info-panel">
                                <h2 style="font-weight: 800; font-size: 1.1rem; color: white;">🎉 FOTO ANDA SIAP DIUNDUH!</h2>
                                <p style="font-size:0.75rem; color:var(--text-muted); line-height:1.4; max-width: 240px;">Pindai QR code di bawah ini menggunakan ponsel Anda untuk mengunduh foto dan video timelapse langsung.</p>
                                
                                <div class="share-qr-container">
                                    <canvas id="shareQrCanvas"></canvas>
                                </div>

                                <a href="" target="_blank" class="btn-open-portal" id="btnOpenWebPortal">Buka Portal Download</a>
                                <button class="btn-finish" onclick="resetToHomeScreen()">Selesai & Sesi Baru ↺</button>
                            </div>
                        </div>

                        <!-- Dago Kiosk Share Layout (for theme-dago_orange) -->
                        <div class="dago-share-layout">
                            <!-- Emojis top-left -->
                            <div class="dago-emojis">🤩🤩🤩</div>
                            <!-- Splat top-right -->
                            <div class="dago-splat-top">
                                <svg viewBox="0 0 100 100">
                                    <path d="M50 0 C60 20, 80 10, 75 35 C95 30, 90 55, 75 60 C85 80, 65 80, 50 100 C35 80, 15 80, 25 60 C10 55, 5 30, 25 35 C20 10, 40 20, 50 0 Z" fill="#0b1cc4"></path>
                                </svg>
                                <span class="dago-splat-close" onclick="resetToHomeScreen()">×</span>
                            </div>

                            <!-- Brand Header -->
                            <div class="dago-brand-header">
                                <div class="dago-brand-title-box">
                                    <img id="dagoBrandLogoImg" src="" alt="Logo" style="display: none; max-height: 45px; width: auto; object-fit: contain; margin-bottom: 5px;">
                                    <span class="dago-brand-main" id="dagoBrandMainText"><?php echo htmlspecialchars($dagoText1) . (substr(strtolower($dagoText1), -1) === '.' ? '' : '.'); ?></span>
                                    <div class="dago-brand-sub" id="dagoBrandSubWrapper">
                                        <span class="dago-brand-sub1"><?php echo htmlspecialchars(strtoupper($dagoText2 ? $dagoText2 : 'RECEIPT PHOTOBOOTH')); ?></span>
                                        <span class="dago-brand-sub2">EST. 2026</span>
                                    </div>
                                </div>
                                <div class="dago-brand-handwriting">This is your Receipt</div>
                            </div>

                            <!-- Printer Slot and Receipt Paper Viewport -->
                            <div class="dago-printer-slot-wrapper">
                                <div class="dago-printer-slot">
                                    <div class="dago-printer-led"></div>
                                </div>
                                <div class="dago-receipt-viewport">
                                    <div class="dago-receipt-paper" id="dagoReceiptPaper">
                                        <div class="dago-receipt-paper-header">
                                            <img id="dagoReceiptLogoImg" src="" alt="Logo" style="display: none; max-height: 35px; width: auto; object-fit: contain; filter: grayscale(1) contrast(2) brightness(0.9); margin: 0 auto 5px auto;">
                                            <div class="dago-receipt-paper-logo" id="dagoReceiptPaperLogoText"><?php echo htmlspecialchars($dagoText1); ?></div>
                                            <div class="dago-receipt-paper-title"><?php echo htmlspecialchars(strtoupper($dagoText2 ? $dagoText2 : 'DAILY NEWS')); ?></div>
                                            <div class="dago-receipt-paper-date" id="dagoReceiptDate">Sunday, 31 May 2026</div>
                                        </div>
                                        <div class="dago-receipt-paper-content">
                                            <img id="dagoReceiptImage" src="" alt="Dynamic Strip Photo">
                                        </div>
                                        <div class="dago-receipt-paper-footer">
                                            Step into a charming receipt-themed photobooth and create timeless memories with your loved ones. Whether you're with your partner, friends, or family, every photo becomes a unique keepsake worth saving.
                                        </div>
                                        <div class="dago-receipt-zigzag"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Subtext: soft file can be downloaded -->
                            <div class="dago-download-toast">soft filenya bisa di download 😉</div>

                            <!-- Bottom Controls & QR Code Row -->
                            <div class="dago-bottom-controls">
                                <div class="dago-actions-column">
                                    <button class="dago-btn dago-btn-print" onclick="retriggerDagoPrint()">
                                        <i class="fa-solid fa-print"></i> PRINT RECEIPT
                                    </button>
                                    <button class="dago-btn dago-btn-retake" onclick="retakeSession()">
                                        <i class="fa-solid fa-camera-rotate"></i> RETAKE!
                                    </button>
                                    <button class="dago-btn dago-btn-finish" onclick="resetToHomeScreen()">
                                        FINISH SESSION
                                    </button>
                                </div>
                                <div class="dago-qr-column">
                                    <div class="dago-qr-card">
                                        <canvas id="dagoShareQrCanvas"></canvas>
                                        <div class="dago-qr-label">SCAN HERE!</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DIALOGS & OVERLAYS -->
                    <!-- 1. Ticket Code Input Modal (Scenario B) -->
                    <div class="sim-dialog-overlay" id="ticketModal">
                        <div class="sim-dialog">
                            <div class="sim-dialog-title"><i class="fa-solid fa-ticket"></i> &nbsp;Verifikasi Tiket Event</div>
                            <div class="sim-dialog-desc">Masukkan kode event khusus (misal: RIANANI26 atau BUDI17) untuk meng-unlock bingkai dan filter acara tersebut.</div>
                            <input type="text" class="sim-dialog-input" id="ticketCodeInput" placeholder="KODE EVENT">
                            <div class="dialog-actions">
                                <button class="btn-dialog btn-dialog-cancel" onclick="closeTicketModal()">Batal</button>
                                <button class="btn-dialog btn-dialog-confirm" onclick="verifyTicketCode()">Verifikasi</button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Ticket Unlock Gold Success Modal (Scenario B) -->
                    <div class="sim-dialog-overlay" id="ticketSuccessModal">
                        <div class="sim-dialog gold-border">
                            <div class="sim-dialog-title gold-title"><i class="fa-solid fa-wand-magic-sparkles"></i> &nbsp;EVENT UNLOCKED&nbsp; <i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <div style="font-size: 3rem; text-align: center; color: var(--primary-gold); margin-bottom: 10px;"><i class="fa-solid fa-lock-open"></i></div>
                            <div class="sim-dialog-desc" id="ticketSuccessDesc" style="color:white; font-weight:700; font-size:0.9rem;">
                                Selamat Datang di Acara Pernikahan Rian & Ani!
                            </div>
                            <div class="sim-dialog-desc">Sesi foto eksklusif Anda telah siap. Semua jepretan Anda akan masuk dalam galeri album acara ini.</div>
                            <div class="dialog-actions" style="justify-content: center; width: 100%;">
                                <button class="btn-dialog btn-dialog-gold" style="width: 100%;" onclick="startUnlockedSession()">MULAI SESI FOTO</button>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Admin Security PIN Modal -->
                    <div class="sim-dialog-overlay" id="adminPinModal">
                        <div class="sim-dialog">
                            <div class="sim-dialog-title"><i class="fa-solid fa-shield-halved"></i> &nbsp;Kiosk Security Admin Gateway</div>
                            <div class="sim-dialog-desc">Masukkan PIN keamanan administrator untuk mengakses setelan atau keluar ke Windows.</div>
                            <input type="password" class="sim-dialog-input" id="adminPinInput" placeholder="PIN" maxlength="4">
                            <div class="dialog-actions">
                                <button class="btn-dialog btn-dialog-cancel" onclick="closeAdminPinModal()">Batal</button>
                                <button class="btn-dialog btn-dialog-confirm" onclick="verifyAdminPin()">Verifikasi</button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Thermal Printer Feeding simulation modal -->
                    <div class="sim-dialog-overlay" id="printerModal">
                        <div class="sim-dialog" style="width: 320px;">
                            <div class="sim-dialog-title"><i class="fa-solid fa-print"></i> &nbsp;Mencetak Struk Foto Kiosk</div>
                            <div class="sim-dialog-desc">Kertas termal sedang dipotong dan dikeluarkan dari slot printer...</div>
                            <div class="printer-sim-container">
                                <div class="printer-mock">
                                    <div class="printer-led"></div>
                                    <div class="printer-slot"></div>
                                    <div class="printing-paper" id="printingPaper">
                                        <img id="printingPaperImg" src="" alt="Printing strip">
                                    </div>
                                </div>
                            </div>
                            <button class="btn-dialog btn-dialog-cancel" style="margin-top:60px;" onclick="closePrinterModal()">Tutup (Selesai)</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Server Configuration Injected from PHP
        const serverConfig = <?php echo json_encode($configData); ?>;
        const packagesList = <?php echo json_encode($packagesList); ?>;
        let appTheme = '<?php echo $appTheme; ?>';
        
        // Local variables
        let kioskMode = 'MULTI_EVENT'; // 'MULTI_EVENT' or 'DEDICATED'
        let activeEventId = 'general'; // Resolved Event ID
        let currentSessionEventId = 'general'; // Session event mapping
        
        let activePackageId = packagesList.length > 0 ? packagesList[0].id : '';
        
        function onPackageSelectChange() {
            activePackageId = document.getElementById('kioskPackageSelect').value;
        }

        // Dynamic Packages Select update inside Simulator sidebar
        function updateSimulatorPackageSelect() {
            const selectEl = document.getElementById('kioskPackageSelect');
            if (!selectEl) return;
            
            let targetId = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
            let activeEvent = null;
            if (serverConfig && serverConfig.events) {
                activeEvent = serverConfig.events.find(e => e.id === targetId) || null;
            }
            
            selectEl.innerHTML = '';
            
            let filteredPackages = packagesList;
            if (activeEvent && activeEvent.billing_type === 'PAY_PER_SESSION' && activeEvent.allowed_packages && Array.isArray(activeEvent.allowed_packages) && activeEvent.allowed_packages.length > 0) {
                filteredPackages = packagesList.filter(p => activeEvent.allowed_packages.includes(p.id));
            }
            
            if (filteredPackages.length > 0) {
                filteredPackages.forEach(pkg => {
                    const opt = document.createElement('option');
                    opt.value = pkg.id;
                    opt.innerText = pkg.name;
                    selectEl.appendChild(opt);
                });
                if (!filteredPackages.some(p => p.id === activePackageId)) {
                    activePackageId = filteredPackages[0].id;
                }
                selectEl.value = activePackageId;
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.innerText = 'Tidak ada paket kustom';
                selectEl.appendChild(opt);
                activePackageId = '';
            }
        }
        
        let logoTaps = 0;
        let logoTapsTimeout;

        let selectedLayout = 'strip'; // strip, grid, postcard
        let selectedFrame = null;
        let cameraStream = null;
        let capturedPhotos = [];
        let captureInterval = null;
        let isDrawing = false;
        let doodleColor = '#ff3366';
        let doodleThickness = 4;
        let drawPoints = [];

        // Elements caching
        const video = document.getElementById('videoPreview');
        const bgCanvas = document.getElementById('bgCanvas');
        const drawCanvas = document.getElementById('drawCanvas');
        const drawCtx = drawCanvas.getContext('2d');
        const bgCtx = bgCanvas.getContext('2d');

        // Initialization
        window.addEventListener('load', () => {
            // Update clock and rental duration timer
            setInterval(() => {
                const now = new Date();
                document.getElementById('statusClock').innerText = now.toTimeString().split(' ')[0].substring(0, 5);
                updateRentalTimerCountdown();
            }, 1000);
            
            // Set up drawing canvas events
            setupCanvasDrawing();
            
            // Initial logo rendering
            updateHomeScreenBranding();

            // Apply theme accessories
            applyThemeUIAccessories(appTheme);
            
            // Start rental timer if dedicated mode is active on load
            if (kioskMode === 'DEDICATED') {
                startRentalTimerIfNeeded(activeEventId);
            }
        });

        // Set Kiosk Mode
        function setKioskMode(mode) {
            kioskMode = mode;
            document.getElementById('modeMultiEvent').classList.toggle('active', mode === 'MULTI_EVENT');
            document.getElementById('modeDedicated').classList.toggle('active', mode === 'DEDICATED');
            
            document.getElementById('eventSelectBox').style.display = mode === 'DEDICATED' ? 'block' : 'none';
            document.getElementById('ticketLauncher').style.display = mode === 'MULTI_EVENT' ? 'flex' : 'none';
            
            document.getElementById('statusBarMode').innerText = `Kiosk Mode: ${mode === 'DEDICATED' ? 'Dedicated' : 'Multi-Event'}`;
            
            if (mode === 'DEDICATED') {
                const sel = document.getElementById('kioskEventSelect');
                activeEventId = sel.value || 'general';
                currentSessionEventId = activeEventId;
                startRentalTimerIfNeeded(activeEventId);
            } else {
                activeEventId = 'general';
                currentSessionEventId = 'general';
            }
            
            updateHomeScreenBranding();
        }

        // Dedicated event selection change
        function onDedicatedEventChange() {
            if (kioskMode === 'DEDICATED') {
                activeEventId = document.getElementById('kioskEventSelect').value;
                currentSessionEventId = activeEventId;
                startRentalTimerIfNeeded(activeEventId);
                updateHomeScreenBranding();
            }
        }

        // Dynamic branding changes on HomeScreen logo texts
        function updateHomeScreenBranding() {
            // ── 1. Resolve data source ────────────────────────────────────────
            // Priority: specific active event > general service profile > neutral fallback
            let targetId = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;

            let brandSource = null;
            let specificEvent = null;
            if (serverConfig && serverConfig.events) {
                // Try active specific event first
                if (targetId && targetId !== 'general') {
                    specificEvent = serverConfig.events.find(e => e.id === targetId) || null;
                    brandSource = specificEvent;
                }
                // Fall back to general service profile
                if (!brandSource) {
                    brandSource = serverConfig.events.find(e => e.id === 'general') || null;
                }
            }

            // Update package selections in simulator panel
            updateSimulatorPackageSelect();

            // Handle countdown for RENTAL_DURATION
            if (specificEvent && specificEvent.billing_type === 'RENTAL_DURATION') {
                startEventCountdown(specificEvent);
            } else {
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                }
                const badge = document.getElementById('eventTimerBadge');
                if (badge) badge.style.display = 'none';
            }

            // ── 2. Extract branding fields ──────────────────────────────────
            const NEUTRAL_NAME   = 'Jeprat Jepret';
            const NEUTRAL_SLOGAN = 'All You need is special';

            const hasRealName = brandSource && brandSource.name
                && brandSource.name !== 'Umum (Default)'
                && brandSource.name !== NEUTRAL_NAME;

            const rawName    = hasRealName ? brandSource.name : NEUTRAL_NAME;
            const sloganText = (brandSource && brandSource.subtitle) ? brandSource.subtitle : NEUTRAL_SLOGAN;
            const hashtagText   = (brandSource && brandSource.hashtag)       ? brandSource.hashtag       : '';
            const dateText      = (brandSource && brandSource.event_date)    ? brandSource.event_date    : '';
            const locationText  = (brandSource && brandSource.event_location)? brandSource.event_location: '';
            const logoUrl       = (brandSource && brandSource.logo_url)      ? brandSource.logo_url      : '';

            // Split name into two lines for text logo rendering (theme-aware)
            const nameWords = rawName.split(' ');
            let text1, text2;
            if (appTheme === 'DAGO_ORANGE') {
                // DAGO style: first word = big title, rest = sub-bar
                text1 = nameWords[0] || NEUTRAL_NAME;
                text2 = nameWords.slice(1).join(' ') || '';
            } else {
                const half = Math.ceil(nameWords.length / 2);
                text1 = nameWords.slice(0, half).join(' ');
                text2 = nameWords.slice(half).join(' ');
            }

            // ── 3. Get DOM elements ──────────────────────────────────────────
            const logoImg        = document.getElementById('homeEventLogoImg');
            const logoTextWrapper= document.getElementById('homeLogoTextWrapper');
            const p1             = document.getElementById('logoPart1');
            const p2             = document.getElementById('logoPart2');
            const slogan         = document.getElementById('homeSlogan');
            const detailsBox     = document.getElementById('homeEventDetailsBox');
            const hashtagEl      = document.getElementById('homeEventHashtag');
            const dateEl         = document.getElementById('homeEventDate');
            const locationEl     = document.getElementById('homeEventLocation');
            const metaDot        = document.getElementById('homeEventMetaDot');

            // ── 4. Render: Logo image OR text logo ──────────────────────────
            if (logoUrl) {
                // Show logo image, FORCE-HIDE text wrapper
                // (use setProperty 'important' to beat any CSS animation or theme override)
                logoImg.src = logoUrl;
                logoImg.style.display = 'block';
                logoTextWrapper.style.setProperty('display',    'none',   'important');
                logoTextWrapper.style.setProperty('visibility', 'hidden', 'important');
                logoTextWrapper.style.setProperty('opacity',    '0',      'important');
                // Render slogan normally below the logo
                slogan.innerText = sloganText;
            } else {
                // No logo image — show styled text logo
                logoImg.style.display = 'none';
                logoImg.src = '';   // clear stale src
                logoTextWrapper.style.removeProperty('display');
                logoTextWrapper.style.removeProperty('visibility');
                logoTextWrapper.style.removeProperty('opacity');
                logoTextWrapper.style.display = 'block';

                if (appTheme === 'DAGO_ORANGE') {
                    p1.innerHTML = `<span class="dago-logo-white">${text1}</span>`;
                    p2.innerHTML = text2
                        ? `<span class="dago-logo-sub">${text2}</span>`
                        : '';
                    slogan.innerHTML = `<span class="dago-logo-sub2">${sloganText}</span>`;
                } else if (appTheme === 'COMIC_POP') {
                    const wrapComic = (txt, startIdx = 0) => {
                        if (!txt) return '';
                        const colors = ['txt-pink', 'txt-blue', 'txt-yellow', 'txt-white'];
                        return txt.split(' ').map((word, idx) => {
                            const cls = colors[(idx + startIdx) % colors.length];
                            return `<span class="${cls}">${word}</span>`;
                        }).join(' ');
                    };
                    p1.innerHTML = wrapComic(text1, 0);
                    p2.innerHTML = wrapComic(text2, 2);
                    slogan.innerText = sloganText;
                } else if (appTheme === 'CREATIVE_DYNAMIC') {
                    const wrapChars = (txt, startIdx = 0) => {
                        if (!txt) return '';
                        return txt.split('').map((char, idx) => {
                            if (char === ' ') return '&nbsp;';
                            return `<span class="logo-char" style="--char-idx: ${idx + startIdx}">${char}</span>`;
                        }).join('');
                    };
                    p1.innerHTML = wrapChars(text1, 0);
                    p2.innerHTML = wrapChars(text2, text1.length);
                    slogan.innerText = sloganText;
                } else {
                    p1.innerText = text1;
                    p2.innerText = text2;
                    slogan.innerText = sloganText;
                }
            }

            // ── 5. Render event details row (hashtag / date / location) ───────
            if (hashtagText) {
                hashtagEl.innerText = hashtagText;
                hashtagEl.style.display = 'block';
            } else {
                hashtagEl.style.display = 'none';
            }

            if (dateText) {
                dateEl.innerText = dateText;
                dateEl.style.display = 'inline';
            } else {
                dateEl.style.display = 'none';
            }

            if (locationText) {
                locationEl.innerText = locationText;
                locationEl.style.display = 'inline';
            } else {
                locationEl.style.display = 'none';
            }

            metaDot.style.display = (dateText && locationText) ? 'inline' : 'none';
            detailsBox.style.display = (hashtagText || dateText || locationText) ? 'flex' : 'none';

            // ── 5.5 Update Dago Receipt Elements dynamically ───
            const dagoBrandLogoImg        = document.getElementById('dagoBrandLogoImg');
            const dagoBrandMainText       = document.getElementById('dagoBrandMainText');
            const dagoBrandSubWrapper     = document.getElementById('dagoBrandSubWrapper');
            const dagoBrandSub1           = document.querySelector('.dago-brand-sub1');
            const dagoBrandSub2           = document.querySelector('.dago-brand-sub2');
            
            const dagoReceiptLogoImg      = document.getElementById('dagoReceiptLogoImg');
            const dagoReceiptPaperLogoText= document.getElementById('dagoReceiptPaperLogoText');
            const dagoReceiptPaperTitle   = document.querySelector('.dago-receipt-paper-title');
            const dagoReceiptPaperFooter  = document.querySelector('.dago-receipt-paper-footer');

            if (logoUrl) {
                // Update brand header logo
                if (dagoBrandLogoImg) {
                    dagoBrandLogoImg.src = logoUrl;
                    dagoBrandLogoImg.style.display = 'block';
                }
                if (dagoBrandMainText) dagoBrandMainText.style.display = 'none';
                if (dagoBrandSubWrapper) dagoBrandSubWrapper.style.display = 'none';

                // Update receipt paper logo
                if (dagoReceiptLogoImg) {
                    dagoReceiptLogoImg.src = logoUrl;
                    dagoReceiptLogoImg.style.display = 'block';
                }
                if (dagoReceiptPaperLogoText) dagoReceiptPaperLogoText.style.display = 'none';
            } else {
                // Falls back to text logo
                if (dagoBrandLogoImg) {
                    dagoBrandLogoImg.style.display = 'none';
                    dagoBrandLogoImg.src = '';
                }
                if (dagoBrandMainText) {
                    dagoBrandMainText.style.display = 'inline';
                    dagoBrandMainText.innerText = text1 + (text1.toLowerCase().endsWith('.') ? '' : '.');
                }
                if (dagoBrandSubWrapper) dagoBrandSubWrapper.style.display = 'block';
                if (dagoBrandSub1) dagoBrandSub1.innerText = (text2 || sloganText || 'RECEIPT PHOTOBOOTH').toUpperCase();
                if (dagoBrandSub2) dagoBrandSub2.innerText = hashtagText || 'EST. 2026';

                if (dagoReceiptLogoImg) {
                    dagoReceiptLogoImg.style.display = 'none';
                    dagoReceiptLogoImg.src = '';
                }
                if (dagoReceiptPaperLogoText) {
                    dagoReceiptPaperLogoText.style.display = 'block';
                    dagoReceiptPaperLogoText.innerText = text1;
                }
            }

            if (dagoReceiptPaperTitle) {
                dagoReceiptPaperTitle.innerText = (text2 || sloganText || 'DAILY NEWS').toUpperCase();
            }
            if (dagoReceiptPaperFooter) {
                if (hashtagText || locationText) {
                    dagoReceiptPaperFooter.innerText = `Step into a charming receipt-themed photobooth and create timeless memories with us. ${hashtagText ? 'Use hashtag ' + hashtagText + '.' : ''} ${locationText ? 'Location: ' + locationText + '.' : ''}`;
                } else {
                    dagoReceiptPaperFooter.innerText = `Step into a charming receipt-themed photobooth and create timeless memories with your loved ones. Every photo becomes a unique keepsake worth saving.`;
                }
            }
            
            // ── 6. Inject Dynamic Color Styles (event primary/secondary color overrides) ───
            let styleEl = document.getElementById('dynamicEventThemeStyles');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'dynamicEventThemeStyles';
                document.head.appendChild(styleEl);
            }

            if (brandSource && brandSource.primary_color) {
                const primary   = brandSource.primary_color;
                const secondary = brandSource.secondary_color || '#ffffff';
                styleEl.innerHTML = `
                    .screen-container.theme-neon_red .screen-home {
                        background-color: ${primary} !important;
                    }
                    .screen-container.theme-neon_red .btn-start {
                        color: ${primary} !important;
                    }
                    .screen-container.theme-neon_red .status-bar {
                        background-color: ${primary}df !important;
                    }
                    .screen-container.theme-neon_red .screen-header-back {
                        background-color: ${primary} !important;
                    }
                    .screen-container.theme-cute_pastel .screen-home {
                        border-color: ${primary} !important;
                    }
                    .screen-container.theme-cute_pastel .home-logo-part2 {
                        color: ${primary} !important;
                    }
                    .screen-container.theme-cute_pastel .btn-start {
                        background-color: ${secondary} !important;
                        border-color: ${primary} !important;
                        box-shadow: 0 6px 0 ${primary} !important;
                        color: ${primary} !important;
                    }
                    .screen-container.theme-cute_nara .screen-home {
                        background-color: ${secondary}f0 !important;
                        border-color: ${primary} !important;
                    }
                    .screen-container.theme-cute_nara .btn-start {
                        background-color: ${primary} !important;
                        box-shadow: 0 4px 15px ${primary}60 !important;
                    }
                    .screen-container.theme-cute_nara .home-logo-part1 {
                        color: ${primary} !important;
                    }
                    .screen-container.theme-luxury_gold .btn-start {
                        background: linear-gradient(135deg, ${primary}, ${secondary}) !important;
                        box-shadow: 0 4px 20px ${primary}50 !important;
                    }
                    .screen-container.theme-minimal_modern .btn-start {
                        border-color: ${primary} !important;
                        background: ${primary} !important;
                    }
                    .screen-container.theme-minimal_modern .home-logo-part1 {
                        color: ${primary} !important;
                    }
                    .screen-container.theme-comic_pop .screen-home {
                        background-color: ${secondary} !important;
                    }
                    .screen-container.theme-comic_pop .home-logo-box {
                        background: ${primary} !important;
                    }
                    .screen-container.theme-comic_pop .btn-start {
                        background-color: ${primary} !important;
                    }
                    .screen-container.theme-dago_orange {
                        background: linear-gradient(135deg, ${primary}, ${secondary}) !important;
                    }
                    .screen-container.theme-dago_orange .btn-start {
                        background: ${primary} !important;
                        border-color: ${secondary} !important;
                    }
                    .screen-container.theme-creative_dynamic {
                        background: linear-gradient(135deg, ${primary}, ${secondary}) !important;
                    }
                    .screen-container.theme-creative_dynamic .btn-start {
                        background: ${primary} !important;
                        box-shadow: 0 0 20px ${primary}80 !important;
                    }
                `;
            } else {
                styleEl.innerHTML = '';
            }
        }

        // Tap logo 5 times to open admin settings
        function handleLogoTaps() {
            logoTaps++;
            clearTimeout(logoTapsTimeout);
            logoTapsTimeout = setTimeout(() => { logoTaps = 0; }, 3000);
            
            if (logoTaps >= 5) {
                logoTaps = 0;
                document.getElementById('adminPinInput').value = "";
                document.getElementById('adminPinModal').style.display = 'flex';
            }
        }

        function closeAdminPinModal() {
            document.getElementById('adminPinModal').style.display = 'none';
        }

        function verifyAdminPin() {
            const inputPin = document.getElementById('adminPinInput').value;
            const configPin = document.getElementById('simAdminPin').value || '1234';
            
            if (inputPin === configPin) {
                closeAdminPinModal();
                alert("⚙️ Gerbang Admin Terbuka! Setelan Kiosk di panel kiri siap diubah.");
            } else {
                alert("❌ PIN Keamanan Admin salah!");
            }
        }

        // Scenario B Event Ticket modal opening
        function openTicketModal() {
            document.getElementById('ticketCodeInput').value = "";
            document.getElementById('ticketModal').style.display = 'flex';
            document.getElementById('ticketCodeInput').focus();
        }

        function closeTicketModal() {
            document.getElementById('ticketModal').style.display = 'none';
        }

        // Verify Event code (e.g. RIANANI26)
        function verifyTicketCode() {
            const code = document.getElementById('ticketCodeInput').value.trim().toUpperCase();
            if (!code) return;
            
            let matchedEvent = null;
            if (serverConfig.events) {
                matchedEvent = serverConfig.events.find(e => e.code.toUpperCase() === code);
            }
            
            if (matchedEvent) {
                closeTicketModal();
                currentSessionEventId = matchedEvent.id;
                
                // Start rental timer on server if needed
                startRentalTimerIfNeeded(matchedEvent.id);
                
                // Show Golden successful verification dialog
                document.getElementById('ticketSuccessDesc').innerHTML = `Selamat Datang di<br>✨ ${matchedEvent.name} ✨`;
                document.getElementById('ticketSuccessModal').style.display = 'flex';
                
                // Voice Speech Synthesis simulation for unlocked gold event Assistive Cue
                speakAssistiveCue("Kode terverifikasi! Selamat datang di " + matchedEvent.name);
            } else {
                alert("❌ Kode Event salah / tidak terdaftar di server!");
            }
        }

        function startUnlockedSession() {
            document.getElementById('ticketSuccessModal').style.display = 'none';
            updateHomeScreenBranding();
            startSession();
        }

        // TTS synthesis
        function speakAssistiveCue(text) {
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'id-ID';
                window.speechSynthesis.speak(utterance);
            }
        }

        function startRentalTimerIfNeeded(eventId) {
            if (!eventId || eventId === 'general') return;
            if (!serverConfig || !serverConfig.events) return;
            const event = serverConfig.events.find(e => e.id === eventId);
            if (event && event.billing_type === 'RENTAL_DURATION') {
                // Call backend API to activate rental
                const formData = new FormData();
                formData.append('action', 'start_event_rental');
                formData.append('event_id', eventId);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the local event object with start/end time
                        event.rental_start_time = data.rental_start_time;
                        event.rental_end_time = data.rental_end_time;
                    }
                })
                .catch(err => console.error("Error starting rental:", err));
            }
        }

        function updateRentalTimerCountdown() {
            let targetId = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
            const badge = document.getElementById('rentalTimerBadge');
            const txt = document.getElementById('rentalTimerText');
            
            if (targetId && targetId !== 'general') {
                if (serverConfig && serverConfig.events) {
                    const event = serverConfig.events.find(e => e.id === targetId);
                    if (event && event.billing_type === 'RENTAL_DURATION') {
                        if (event.rental_start_time && event.rental_end_time) {
                            const endTime = new Date(event.rental_end_time.replace(' ', 'T')).getTime();
                            const now = Date.now();
                            const diff = endTime - now;
                            
                            if (diff <= 0) {
                                // Expired!
                                if (badge) badge.style.display = 'none';
                                speakAssistiveCue("Durasi sewa telah habis. Kiosk kembali ke mode normal.");
                                alert("⌛ Durasi Sewa Event telah habis! Kiosk otomatis kembali ke mode normal / keluar dari event.");
                                
                                // Exit event mode
                                if (kioskMode === 'DEDICATED') {
                                    kioskMode = 'MULTI_EVENT';
                                    document.getElementById('modeMultiEvent').classList.add('active');
                                    document.getElementById('modeDedicated').classList.remove('active');
                                    document.getElementById('eventSelectBox').style.display = 'none';
                                    document.getElementById('ticketLauncher').style.display = 'flex';
                                    document.getElementById('statusBarMode').innerText = `Kiosk Mode: Multi-Event`;
                                    activeEventId = 'general';
                                }
                                currentSessionEventId = 'general';
                                updateHomeScreenBranding();
                            } else {
                                // Calculate remaining
                                const hours = Math.floor(diff / (3600 * 1000));
                                const minutes = Math.floor((diff % (3600 * 1000)) / (60 * 1000));
                                const seconds = Math.floor((diff % (60 * 1000)) / 1000);
                                
                                const pad = (num) => String(num).padStart(2, '0');
                                const timeFormatted = hours > 0 
                                    ? `${pad(hours)}:${pad(minutes)}:${pad(seconds)}` 
                                    : `${pad(minutes)}:${pad(seconds)}`;
                                
                                if (txt) txt.innerText = timeFormatted;
                                if (badge) {
                                    badge.style.display = 'inline-flex';
                                    // Make border/color dynamic for critical time (less than 5 mins)
                                    if (diff < 5 * 60 * 1000) {
                                        badge.style.border = "1.5px solid rgba(255, 51, 102, 0.6)";
                                        badge.style.background = "rgba(255, 51, 102, 0.15)";
                                        txt.style.color = "#ff3366";
                                    } else {
                                        badge.style.border = "1px solid rgba(255, 255, 255, 0.1)";
                                        badge.style.background = "rgba(15, 15, 20, 0.75)";
                                        txt.style.color = "#ffffff";
                                    }
                                }
                            }
                        } else {
                            if (txt) txt.innerText = "Mulai...";
                            if (badge) badge.style.display = 'inline-flex';
                        }
                        return;
                    }
                }
            }
            if (badge) badge.style.display = 'none';
        }

        // Start session navigation
        function startSession() {
            navigateScreen('screenLayout');
        }

        function selectLayout(layout) {
            selectedLayout = layout;
            loadFramesForLayout();
            navigateScreen('screenFrame');
        }

        // Filters frames dynamically from config.json based on active event ID
        function loadFramesForLayout() {
            const container = document.getElementById('frameContainer');
            container.innerHTML = "";
            
            let eventIdFilter = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
            let matchingFrames = [];
            
            if (serverConfig.frames) {
                matchingFrames = serverConfig.frames.filter(f => f.type.toLowerCase() === selectedLayout.toLowerCase());
            }
            
            // Filtering frames based on Event ID or falls back to 'general'
            let filtered = [];
            let activeEvtObj = serverConfig.events ? serverConfig.events.find(e => e.id === eventIdFilter) : null;
            if (activeEvtObj && activeEvtObj.allowed_frames && Array.isArray(activeEvtObj.allowed_frames)) {
                filtered = matchingFrames.filter(f => activeEvtObj.allowed_frames.includes(f.id));
            } else {
                filtered = matchingFrames.filter(f => f.event_id === eventIdFilter);
                if (filtered.length === 0) {
                    filtered = matchingFrames.filter(f => f.event_id === 'general' || !f.event_id);
                }
            }
            
            filtered.forEach(f => {
                const card = document.createElement('div');
                card.className = 'frame-card';
                card.onclick = () => selectFrame(f);
                
                let isEventCustom = f.event_id && f.event_id !== 'general';
                let tagText = isEventCustom ? '<span class="badge-event-tag">Acara</span>' : '';
                
                card.innerHTML = `
                    ${tagText}
                    <div class="frame-card-preview" style="background-color: ${f.background_color || '#121212'};">
                        <img src="../${f.image_url}" onerror="this.src='https://placehold.co/100x300/121212/ffffff?text=${encodeURIComponent(f.name)}'">
                    </div>
                    <div class="frame-card-name">${f.name}</div>
                `;
                container.appendChild(card);
            });
        }

        // Selected frame triggers camera screen
        function selectFrame(frame) {
            selectedFrame = frame;
            startCameraPreview();
        }

        // Webcam stream setup
        function startCameraPreview() {
            navigateScreen('screenCamera');
            
            // Build slots preview in sidebar
            const slotsContainer = document.getElementById('captureSlotsContainer');
            slotsContainer.innerHTML = "";
            capturedPhotos = [];
            
            const numSlots = selectedFrame.slots ? selectedFrame.slots.length : 4;
            for(let i=0; i<numSlots; i++) {
                const slot = document.createElement('div');
                slot.className = `capture-slot-box ${i===0?'active':''}`;
                slot.id = `slot-${i}`;
                slot.innerText = `Pose ${i+1}`;
                slotsContainer.appendChild(slot);
            }
            
            document.getElementById('currentSlotNum').innerText = "1";
            const isPhone = document.querySelector('.tablet-device').classList.contains('phone-mode');
            document.getElementById('cameraTriggerBtn').style.display = isPhone ? 'none' : 'block';
            document.getElementById('cameraTriggerBtnFloating').style.display = isPhone ? 'block' : 'none';

            navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } })
                .then(stream => {
                    cameraStream = stream;
                    video.srcObject = stream;
                    video.style.display = 'block';
                    document.getElementById('mockCameraPlaceholder').style.display = 'none';
                })
                .catch(err => {
                    console.error("Camera access failed", err);
                    video.style.display = 'none';
                    document.getElementById('mockCameraPlaceholder').style.display = 'flex';
                });
        }

        function stopCameraPreview() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
        }

        // Sequences camera shots matching slots
        function triggerWebcamCaptureSequence() {
            document.getElementById('cameraTriggerBtn').style.display = 'none';
            document.getElementById('cameraTriggerBtnFloating').style.display = 'none';
            const numSlots = selectedFrame.slots ? selectedFrame.slots.length : 4;
            
            let currentIdx = 0;
            
            function captureNext() {
                if (currentIdx >= numSlots) {
                    // Complete capture, go to preview
                    stopCameraPreview();
                    showStitchedPreview();
                    return;
                }
                
                document.getElementById('currentSlotNum').innerText = (currentIdx + 1);
                
                // Show countdown overlay
                const countdown = document.getElementById('countdownOverlay');
                const countNum = document.getElementById('countdownNum');
                countdown.style.display = 'flex';
                
                let counter = 3;
                countNum.innerText = counter;
                
                // Assistive audio count download voice assistance offline Indonesian
                speakAssistiveCue(counter.toString());

                let cdInterval = setInterval(() => {
                    counter--;
                    if (counter > 0) {
                        countNum.innerText = counter;
                        speakAssistiveCue(counter.toString());
                    } else if (counter === 0) {
                        countNum.innerText = "SENYUM! 📸";
                        speakAssistiveCue("Senyum");
                    } else {
                        clearInterval(cdInterval);
                        countdown.style.display = 'none';
                        
                        // Perform bitmap snap capture
                        snapPhoto(currentIdx);
                        currentIdx++;
                        
                        // Wait 1s and trigger next slot
                        setTimeout(captureNext, 1000);
                    }
                }, 1000);
            }
            
            captureNext();
        }

        // Capture canvas shot image
        function snapPhoto(slotIdx) {
            const canvas = document.createElement('canvas');
            canvas.width = 640;
            canvas.height = 480;
            const ctx = canvas.getContext('2d');
            
            // Mirror picture logic
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
            
            if (cameraStream) {
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            } else {
                // Mock generator background if webcam block
                ctx.fillStyle = `#${Math.floor(Math.random()*16777215).toString(16)}`;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.scale(-1, 1); // Reset scale
                ctx.translate(-canvas.width, 0);
                ctx.fillStyle = 'white';
                ctx.font = '24px sans-serif';
                ctx.fillText(`Pose Simulasi ${slotIdx+1}`, 150, 240);
            }
            
            // Apply Orton Soft-Glow and Cheek Blush filters procedurally inside local simulated canvas
            applyProceduralFilters(ctx, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/png');
            capturedPhotos.push(dataUrl);
            
            // Update slots bar
            const slotBox = document.getElementById(`slot-${slotIdx}`);
            slotBox.className = "capture-slot-box captured";
            slotBox.innerHTML = `<img src="${dataUrl}">`;
            
            // Set next slot active
            if (document.getElementById(`slot-${slotIdx+1}`)) {
                document.getElementById(`slot-${slotIdx+1}`).className = "capture-slot-box active";
            }
        }

        // Procedural Orton Soft-Glow & Cheek Blush effect
        function applyProceduralFilters(ctx, w, h) {
            // 1. Orton glow blending: overlay blurred overlay
            ctx.save();
            ctx.globalAlpha = 0.25; // 25% glow blend
            ctx.filter = 'blur(10px) brightness(1.1)';
            ctx.drawImage(ctx.canvas, 0, 0);
            ctx.restore();
            
            // 2. Procedural cheek blush circle overlay
            ctx.save();
            ctx.globalAlpha = 0.15; // Soft opacity
            
            // Left cheek
            let gradLeft = ctx.createRadialGradient(w * 0.42, h * 0.54, 0, w * 0.42, h * 0.54, w * 0.08);
            gradLeft.addColorStop(0, '#ff3366');
            gradLeft.addColorStop(1, 'transparent');
            ctx.fillStyle = gradLeft;
            ctx.beginPath();
            ctx.arc(w * 0.42, h * 0.54, w * 0.08, 0, Math.PI * 2);
            ctx.fill();

            // Right cheek
            let gradRight = ctx.createRadialGradient(w * 0.58, h * 0.54, 0, w * 0.58, h * 0.54, w * 0.08);
            gradRight.addColorStop(0, '#ff3366');
            gradRight.addColorStop(1, 'transparent');
            ctx.fillStyle = gradRight;
            ctx.beginPath();
            ctx.arc(w * 0.58, h * 0.54, w * 0.08, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.restore();
        }

        // Stitches photos together onto the final frame
        function showStitchedPreview() {
            navigateScreen('screenPreview');
            
            const frameW = selectedFrame.width || 600;
            const frameH = selectedFrame.height || 2000;
            
            bgCanvas.width = frameW;
            bgCanvas.height = frameH;
            
            drawCanvas.width = frameW;
            drawCanvas.height = frameH;
            drawCtx.clearRect(0, 0, frameW, drawCanvas.height);
            
            // Draw background frame color
            bgCtx.fillStyle = selectedFrame.background_color || '#121212';
            bgCtx.fillRect(0,0, frameW, frameH);
            
            // Stitches photos onto frame slots
            let loadedPhotosCount = 0;
            const slots = selectedFrame.slots || [];
            
            slots.forEach((slot, index) => {
                if (capturedPhotos[index]) {
                    const img = new Image();
                    img.src = capturedPhotos[index];
                    img.onload = () => {
                        bgCtx.save();
                        if (slot.rotation) {
                            const centerX = slot.x + slot.width / 2;
                            const centerY = slot.y + slot.height / 2;
                            bgCtx.translate(centerX, centerY);
                            bgCtx.rotate(slot.rotation * Math.PI / 180);
                            bgCtx.drawImage(img, -slot.width / 2, -slot.height / 2, slot.width, slot.height);
                        } else {
                            bgCtx.drawImage(img, slot.x, slot.y, slot.width, slot.height);
                        }
                        bgCtx.restore();
                        
                        loadedPhotosCount++;
                        if (loadedPhotosCount === slots.length) {
                            loadFrameTemplateOverlay();
                        }
                    };
                }
            });
            
            if (slots.length === 0) {
                loadFrameTemplateOverlay();
            }
        }

        function loadFrameTemplateOverlay() {
            const frameW = selectedFrame.width || 600;
            const frameH = selectedFrame.height || 2000;
            
            const imgOverlay = new Image();
            imgOverlay.src = `../${selectedFrame.image_url}`;
            imgOverlay.onload = () => {
                bgCtx.drawImage(imgOverlay, 0, 0, frameW, frameH);
                
                // Draw dynamic elements if any
                if (selectedFrame.is_dynamic) {
                    drawDynamicElements();
                }
            };
        }

        function drawDynamicElements() {
            let eventIdValue = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
            let activeEvent = serverConfig.events ? serverConfig.events.find(e => e.id === eventIdValue) : null;
            if (!activeEvent) return;

            const de = selectedFrame.dynamic_elements;
            if (!de) return;

            // Helper to draw texts
            function drawTexts() {
                if (de.texts) {
                    de.texts.forEach(textConfig => {
                        let textStr = "";
                        if (textConfig.type === 'event_name') {
                            textStr = activeEvent.name || "Creative Photo Studio";
                        } else if (textConfig.type === 'event_subtitle') {
                            textStr = activeEvent.subtitle || "Sweet Memories";
                        } else if (textConfig.type === 'event_hashtag') {
                            textStr = activeEvent.hashtag || "#photobooth";
                        }

                        if (textStr) {
                            bgCtx.save();
                            bgCtx.fillStyle = textConfig.color || '#000000';
                            let fontStyle = textConfig.font_style || 'normal';
                            let fontStyleStr = "";
                            if (fontStyle === 'bold') fontStyleStr = 'bold ';
                            else if (fontStyle === 'italic') fontStyleStr = 'italic ';
                            else if (fontStyle === 'bold_italic') fontStyleStr = 'bold italic ';
                            
                            bgCtx.font = `${fontStyleStr}${textConfig.font_size}px Outfit, sans-serif`;
                            bgCtx.textAlign = textConfig.align || 'center';
                            bgCtx.textBaseline = 'top';

                            let xPos = textConfig.x;
                            let yPos = textConfig.y;
                            
                            // Multiline split support
                            let lines = textStr.split('\n');
                            let leading = textConfig.font_size * 1.25;
                            lines.forEach((line, lineIdx) => {
                                bgCtx.fillText(line, xPos, yPos + (lineIdx * leading));
                            });
                            bgCtx.restore();
                        }
                    });
                }
            }

            // Draw logo first if enabled
            if (de.logo && activeEvent.logo_url) {
                const imgLogo = new Image();
                imgLogo.src = `../${activeEvent.logo_url}`;
                imgLogo.onload = () => {
                    bgCtx.drawImage(imgLogo, de.logo.x, de.logo.y, de.logo.width, de.logo.height);
                    drawTexts();
                };
                imgLogo.onerror = () => {
                    drawTexts();
                };
            } else {
                drawTexts();
            }
        }

        // Draw doodle signature canvas mouse/touch event listeners
        function setupCanvasDrawing() {
            let drawing = false;
            
            // Helper coordinates mapper
            function getCoords(e) {
                const rect = drawCanvas.getBoundingClientRect();
                const scaleX = drawCanvas.width / rect.width;
                const scaleY = drawCanvas.height / rect.height;
                
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                
                return {
                    x: (clientX - rect.left) * scaleX,
                    y: (clientY - rect.top) * scaleY
                };
            }

            drawCanvas.addEventListener('mousedown', (e) => {
                drawing = true;
                const c = getCoords(e);
                drawCtx.beginPath();
                drawCtx.moveTo(c.x, c.y);
                drawCtx.strokeStyle = doodleColor;
                drawCtx.lineWidth = doodleThickness;
                drawCtx.lineCap = 'round';
                drawCtx.lineJoin = 'round';
                drawCtx.shadowColor = doodleColor;
                drawCtx.shadowBlur = 8;
            });

            drawCanvas.addEventListener('mousemove', (e) => {
                if (!drawing) return;
                const c = getCoords(e);
                drawCtx.lineTo(c.x, c.y);
                drawCtx.stroke();
            });

            window.addEventListener('mouseup', () => { drawing = false; });
            
            // Mobile support
            drawCanvas.addEventListener('touchstart', (e) => {
                drawing = true;
                const c = getCoords(e);
                drawCtx.beginPath();
                drawCtx.moveTo(c.x, c.y);
                drawCtx.strokeStyle = doodleColor;
                drawCtx.lineWidth = doodleThickness;
                drawCtx.lineCap = 'round';
            });
            drawCanvas.addEventListener('touchmove', (e) => {
                if (!drawing) return;
                const c = getCoords(e);
                drawCtx.lineTo(c.x, c.y);
                drawCtx.stroke();
            });
            drawCanvas.addEventListener('touchend', () => { drawing = false; });
        }

        function setDoodleColor(color, element) {
            doodleColor = color;
            document.querySelectorAll('.color-circle').forEach(c => c.classList.remove('active'));
            element.classList.add('active');
        }

        function clearDoodleCanvas() {
            drawCtx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
        }

        function retakeSession() {
            startCameraPreview();
        }

        // Generate ID Card Canvas with character image, name, license ID, and license page QR code
        function generateIdCardCanvas(photoImg, sessionId, callback) {
            const cardCanvas = document.createElement('canvas');
            cardCanvas.width = 638;
            cardCanvas.height = 1011;
            const ctx = cardCanvas.getContext('2d');

            // 1. Draw elegant background gradient
            const grad = ctx.createLinearGradient(0, 0, 0, 1011);
            grad.addColorStop(0, '#13131c');
            grad.addColorStop(0.5, '#0c0c10');
            grad.addColorStop(1, '#050508');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, 638, 1011);

            // Neon glowing border
            ctx.strokeStyle = '#e63946';
            ctx.lineWidth = 6;
            ctx.strokeRect(3, 3, 632, 1005);
            ctx.strokeStyle = '#f7b801';
            ctx.lineWidth = 2;
            ctx.strokeRect(8, 8, 622, 995);

            // Header Brand — use service name from config
            const brandName = (function() {
                if (serverConfig && serverConfig.events) {
                    const g = serverConfig.events.find(e => e.id === 'general');
                    if (g && g.name && g.name !== 'Umum (Default)') return g.name.toUpperCase();
                }
                return 'JEPRAT JEPRET';
            })();
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 28px Outfit, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(brandName, 319, 60);

            ctx.fillStyle = '#f7b801';
            ctx.font = '700 14px Outfit, sans-serif';
            ctx.fillText('OFFICIAL CHARACTER CARD', 319, 85);

            // Photo Frame background
            ctx.fillStyle = '#1e1e26';
            ctx.fillRect(119, 130, 400, 500);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 4;
            ctx.strokeRect(119, 130, 400, 500);

            // Draw photo inside frame (clip to round corners optionally, or draw direct)
            ctx.drawImage(photoImg, 121, 132, 396, 496);

            // Name & Description
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 32px Outfit, sans-serif';
            ctx.textAlign = 'center';
            const charName = selectedFrame ? selectedFrame.name : 'Custom Character';
            ctx.fillText(charName.toUpperCase(), 319, 680);

            // License Key
            ctx.fillStyle = '#f7b801';
            ctx.font = 'bold 18px monospace';
            const hashPart = md5 ? md5(sessionId).substring(0, 4).toUpperCase() : sessionId.substring(4, 8).toUpperCase();
            const licKey = 'LIC-' + sessionId.substring(0,4).toUpperCase() + '-' + hashPart;
            ctx.fillText(licKey, 319, 715);

            // Verification text
            ctx.fillStyle = '#8d8d9f';
            ctx.font = '400 13px Outfit, sans-serif';
            ctx.fillText('Verified AI generated character by ' + brandName + ' Kiosk', 319, 740);

            // Load and Draw QR Code linking to verification page
            const protocol = window.location.protocol;
            const host = window.location.host;
            const pathParts = window.location.pathname.split('/');
            pathParts.pop(); // remove kiosk_sim.php
            const baseDir = pathParts.join('/');
            const verifyUrl = `${protocol}//${host}${baseDir}/license.php?id=${sessionId}`;

            const tempQr = new QRious({
                value: verifyUrl,
                size: 140,
                background: 'white',
                foreground: '#0c0c0f',
                level: 'H'
            });

            const qrImg = new Image();
            qrImg.src = tempQr.toDataURL();
            qrImg.onload = () => {
                // Draw QR on bottom center
                ctx.drawImage(qrImg, 249, 775, 140, 140);

                // Add verified badge text
                ctx.fillStyle = '#39ff14';
                ctx.font = '900 14px Outfit, sans-serif';
                ctx.fillText('✓ VERIFIED LICENSE', 319, 945);
                
                callback(cardCanvas.toDataURL('image/png'), verifyUrl);
            };
        }

        // MD5 mock fallback helper in case not loaded
        function md5(string) {
            return string; // Simplified for basic fallback, but QRious and simple hash is standard
        }

        // Uploads final session combined photo strip to local upload.php
        function finishAndUploadSession() {
            // Stitch drawing canvas layers into one unified bitmap image
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = bgCanvas.width;
            finalCanvas.height = bgCanvas.height;
            const finalCtx = finalCanvas.getContext('2d');
            
            finalCtx.drawImage(bgCanvas, 0, 0);
            finalCtx.drawImage(drawCanvas, 0, 0);
            
            const dataUrl = finalCanvas.toDataURL('image/png');

            // Resolve active package
            const activePackage = packagesList.find(p => p.id === activePackageId) || (packagesList.length > 0 ? packagesList[0] : null);
            
            // Prepare multipart files
            finalCanvas.toBlob((blob) => {
                const formData = new FormData();
                formData.append('photo', blob, 'photo.png');
                
                // Add query parameter bindings for dynamic events metadata resolving
                let eventIdValue = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
                let uploadUrl = `upload.php?frame_id=${selectedFrame.id}&event_id=${eventIdValue}&package_id=${activePackage ? activePackage.id : ''}`;

                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Render dynamically generated QR code
                        new QRious({
                            element: document.getElementById('shareQrCanvas'),
                            value: data.download_url,
                            size: 160
                        });

                        new QRious({
                            element: document.getElementById('dagoShareQrCanvas'),
                            value: data.download_url,
                            size: 120
                        });

                        document.getElementById('btnOpenWebPortal').href = data.download_url;
                        
                        console.log("Uploaded successfully!", data);

                        if (appTheme === 'DAGO_ORANGE') {
                            // Dago Orange Print Flow (inline animation + audio whirr, bypasses printerModal)
                            document.getElementById('dagoReceiptImage').src = dataUrl;
                            
                            // Format date dynamically
                            const dateOpt = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
                            document.getElementById('dagoReceiptDate').innerText = new Date().toLocaleDateString('en-GB', dateOpt);
                            
                            navigateScreen('screenShare');
                            triggerDagoPrintingAnimation();
                            playPrinterSound();
                        } else {
                            // Handle printing simulation based on print flow setting
                            if (activePackage && activePackage.print_flow === 'ID_CARD') {
                                const photoImg = new Image();
                                photoImg.src = dataUrl;
                                photoImg.onload = () => {
                                    generateIdCardCanvas(photoImg, data.session_id, (idCardDataUrl, verifyUrl) => {
                                        document.getElementById('printingPaperImg').src = idCardDataUrl;
                                        document.getElementById('finalStitchedImage').src = idCardDataUrl;
                                        document.getElementById('printerModal').style.display = 'flex';
                                        
                                        // Reset paper animation height
                                        const paper = document.getElementById('printingPaper');
                                        paper.style.animation = 'none';
                                        paper.offsetHeight; // trigger reflow
                                        paper.style.animation = null;
                                    });
                                };
                            } else {
                                // Standard Receipt or Color Print sizing mapping
                                let printWidth = activePackage ? activePackage.print_width_mm : 58;
                                let printHeight = activePackage ? activePackage.print_height_mm : 200;
                                
                                const printCanvas = document.createElement('canvas');
                                printCanvas.width = Math.round(printWidth * 8);   // ~8px/mm ≈ 203 DPI — lebih tajam dari sebelumnya (4px/mm)
                                printCanvas.height = Math.round(printHeight * 8);
                                const printCtx = printCanvas.getContext('2d');
                                // Aktifkan interpolasi bicubic agar foto tidak buram saat di-scale ke canvas
                                printCtx.imageSmoothingEnabled = true;
                                printCtx.imageSmoothingQuality = 'high';
                                
                                const photoImg = new Image();
                                photoImg.src = dataUrl;
                                photoImg.onload = () => {
                                    const ratio = printCanvas.width / photoImg.width;
                                    const drawH = photoImg.height * ratio;
                                    printCtx.fillStyle = '#ffffff';
                                    printCtx.fillRect(0, 0, printCanvas.width, printCanvas.height);
                                    printCtx.drawImage(photoImg, 0, 0, printCanvas.width, drawH);
                                    
                                    const printDataUrl = printCanvas.toDataURL('image/png');
                                    
                                    document.getElementById('printingPaperImg').src = printDataUrl;
                                    document.getElementById('finalStitchedImage').src = printDataUrl;
                                    document.getElementById('printerModal').style.display = 'flex';
                                    
                                    // Reset paper animation height
                                    const paper = document.getElementById('printingPaper');
                                    paper.style.animation = 'none';
                                    paper.offsetHeight; // trigger reflow
                                    paper.style.animation = null;
                                };
                            }
                        }
                    } else {
                        alert("❌ Gagal mengunggah foto ke server: " + data.message);
                    }
                })
                .catch(err => {
                    console.error("Upload error", err);
                    alert("❌ Koneksi ke backend upload.php gagal!");
                });
            }, 'image/png');
        }

        function closePrinterModal() {
            document.getElementById('printerModal').style.display = 'none';
            navigateScreen('screenShare');
        }

        function resetToHomeScreen() {
            if (kioskMode === 'MULTI_EVENT') {
                currentSessionEventId = 'general';
            }
            updateHomeScreenBranding();
            navigateScreen('screenHome');
        }

        function triggerDagoPrintingAnimation() {
            const paper = document.getElementById('dagoReceiptPaper');
            if (paper) {
                paper.classList.remove('printing');
                paper.offsetHeight; // trigger reflow
                paper.classList.add('printing');
            }
        }

        function playPrinterSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const bufferSize = audioCtx.sampleRate * 3.5; // 3.5 seconds
                const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                
                // Synthesize white noise for paper sliding
                for (let i = 0; i < bufferSize; i++) {
                    data[i] = Math.random() * 2 - 1;
                }
                
                const noise = audioCtx.createBufferSource();
                noise.buffer = buffer;
                
                // Filter noise to small whirr range
                const filter = audioCtx.createBiquadFilter();
                filter.type = 'bandpass';
                filter.frequency.setValueAtTime(950, audioCtx.currentTime);
                filter.Q.setValueAtTime(2.2, audioCtx.currentTime);
                
                // Low-frequency oscillator to simulate step motor gear whirr
                const gearMotor = audioCtx.createOscillator();
                gearMotor.type = 'sawtooth';
                gearMotor.frequency.setValueAtTime(55, audioCtx.currentTime); // Hz
                
                const gearGain = audioCtx.createGain();
                gearGain.gain.setValueAtTime(0.015, audioCtx.currentTime);
                
                // Modulate filter frequency with gearMotor to create step-like resonance
                const modGain = audioCtx.createGain();
                modGain.gain.setValueAtTime(250, audioCtx.currentTime);
                gearMotor.connect(modGain);
                modGain.connect(filter.frequency);
                
                // Volume envelope
                const gainNode = audioCtx.createGain();
                gainNode.gain.setValueAtTime(0.06, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 3.4);
                
                noise.connect(filter);
                filter.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                gearMotor.connect(gearGain);
                gearGain.connect(audioCtx.destination);
                
                gearMotor.start();
                noise.start();
                
                setTimeout(() => {
                    try {
                        noise.stop();
                        gearMotor.stop();
                        audioCtx.close();
                    } catch(e){}
                }, 3500);
            } catch (e) {
                console.error("Synthesizer error", e);
            }
        }

        function retriggerDagoPrint() {
            triggerDagoPrintingAnimation();
            playPrinterSound();
        }

        function testPrinterAnimation() {
            // Format date dynamically
            const dateOpt = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            const formattedDate = new Date().toLocaleDateString('en-GB', dateOpt);
            
            if (appTheme === 'DAGO_ORANGE') {
                // Dago Orange Print Flow (inline animation + audio whirr, bypasses printerModal)
                document.getElementById('dagoReceiptImage').src = '../logo.png';
                document.getElementById('dagoReceiptDate').innerText = formattedDate;
                
                new QRious({
                    element: document.getElementById('dagoShareQrCanvas'),
                    value: window.location.href,
                    size: 120
                });
                
                navigateScreen('screenShare');
                triggerDagoPrintingAnimation();
                playPrinterSound();
            } else {
                // Standard Theme Print Flow (opens printerModal, plays audio whirr, then shows screenShare)
                document.getElementById('printingPaperImg').src = '../logo.png';
                document.getElementById('finalStitchedImage').src = '../logo.png';
                
                new QRious({
                    element: document.getElementById('shareQrCanvas'),
                    value: window.location.href,
                    size: 160
                });
                
                document.getElementById('btnOpenWebPortal').href = window.location.href;
                
                // Open printer modal
                document.getElementById('printerModal').style.display = 'flex';
                
                // Reset paper animation height
                const paper = document.getElementById('printingPaper');
                if (paper) {
                    paper.style.animation = 'none';
                    paper.offsetHeight; // trigger reflow
                    paper.style.animation = null;
                }
                
                // Play whirring sound
                playPrinterSound();
            }
        }

        function applyThemeUIAccessories(theme) {
            const home = document.getElementById('screenHome');
            if (!home) return;

            // 1. Remove existing comic pop decorations
            const oldComics = home.querySelectorAll('.comic-bubble-left, .comic-bubble-right, .comic-star-decor, .comic-bubble-pow');
            oldComics.forEach(el => el.remove());

            // 2. Reset or configure start button
            const btnStart = document.querySelector('.btn-start');
            if (btnStart) {
                if (theme === 'COMIC_POP') {
                    btnStart.innerHTML = '<div class="comic-start-inner"><i class="fa-solid fa-camera"></i></div><span class="comic-start-text">Photo</span>';
                } else {
                    btnStart.innerHTML = 'START';
                }
            }

            // 3. Add Comic Pop decorations if active
            if (theme === 'COMIC_POP') {
                const bubbleLeft = document.createElement('div');
                bubbleLeft.className = 'comic-bubble-left';
                bubbleLeft.onclick = () => { alert("KABOOM! Ayo foto bareng teman-temanmu!"); };
                home.appendChild(bubbleLeft);
                
                const bubbleRight = document.createElement('div');
                bubbleRight.className = 'comic-bubble-right';
                bubbleRight.onclick = () => { alert("POW! Abadikan momen serumu di sini!"); };
                home.appendChild(bubbleRight);
                
                const starLeft = document.createElement('div');
                starLeft.className = 'comic-star-decor decor-star1';
                starLeft.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><path d='M50 0 L60 40 L100 50 L60 60 L50 100 L40 60 L0 50 L40 40 Z' fill='#fff300' stroke='#000' stroke-width='6'/></svg>`;
                home.appendChild(starLeft);
                
                const starRight = document.createElement('div');
                starRight.className = 'comic-star-decor decor-star2';
                starRight.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><path d='M50 0 L60 40 L100 50 L60 60 L50 100 L40 60 L0 50 L40 40 Z' fill='#00ffff' stroke='#000' stroke-width='6'/></svg>`;
                home.appendChild(starRight);
                
                const powBubble = document.createElement('div');
                powBubble.className = 'comic-bubble-pow';
                home.appendChild(powBubble);
            }
        }

        function onThemeSelectChange() {
            const select = document.getElementById('kioskThemeSelect');
            if (!select) return;
            const theme = select.value;
            
            // 1. Update appTheme globally
            appTheme = theme;
            
            // 2. Remove all theme-xxx classes from the screen-container using browser-safe split/filter
            const container = document.querySelector('.screen-container');
            if (container) {
                const classes = container.className.split(' ');
                const cleanClasses = classes.filter(cls => !cls.startsWith('theme-'));
                cleanClasses.push('theme-' + theme.toLowerCase());
                container.className = cleanClasses.join(' ');
            }
            
            // 3. Update home screen branding
            updateHomeScreenBranding();
            
            // 4. Reload frames for layout if currently on screenFrame
            if (document.getElementById('screenFrame').classList.contains('active')) {
                loadFramesForLayout();
            }
            
            // 5. Apply dynamic theme-specific accessories
            applyThemeUIAccessories(theme);
        }

        // Kiosk general screen navigator helper
        function navigateScreen(screenId) {
            document.querySelectorAll('.kiosk-screen').forEach(s => s.classList.remove('active'));
            document.getElementById(screenId).classList.add('active');
        }

        function navigateBack(screenId) {
            stopCameraPreview();
            navigateScreen(screenId === 'home' ? 'screenHome' : (screenId === 'layout' ? 'screenLayout' : 'screenFrame'));
        }

        // Config Sync Simulator
        function syncServerConfig() {
            alert("✓ Sinkronisasi bingkai dari config.json server selesai!");
            location.reload();
        }

        // Toggle Device View Mode (Tablet Landscape vs Phone Portrait)
        function setDeviceMode(mode) {
            const device = document.querySelector('.tablet-device');
            const isPhone = mode === 'PHONE';
            
            device.classList.toggle('phone-mode', isPhone);
            document.getElementById('btnDeviceTablet').classList.toggle('active', !isPhone);
            document.getElementById('btnDevicePhone').classList.toggle('active', isPhone);
            
            // Re-render frame list if we are currently on the frame selection screen to adapt to grid view
            if (document.getElementById('screenFrame').classList.contains('active')) {
                loadFramesForLayout();
            }
        }
    </script>
</body>
</html>
