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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk App Web Simulator - Creative Studio</title>
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
            height: 380px;
            background-color: var(--sidebar-bg);
            border: 2px solid var(--border-color);
            border-radius: 14px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s;
            flex-shrink: 0;
            position: relative;
        }

        .frame-card:hover {
            border-color: var(--primary-red);
            transform: translateY(-4px);
        }

        .frame-card-preview {
            flex: 1;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            position: relative;
            background-color: #0c0c0f;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .frame-card-preview img {
            height: 100%;
            width: auto;
            object-fit: contain;
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

        /* 4. Retro Arcade (8-Bit Vaporwave) */
        .theme-retro_arcade .screen-home {
            background-color: #0c001a !important;
            background-image: 
                linear-gradient(rgba(223, 0, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(223, 0, 255, 0.1) 1px, transparent 1px) !important;
            background-size: 25px 25px !important;
            border: 4px solid #df00ff !important;
            border-radius: 18px !important;
        }
        .theme-retro_arcade .screen-home::before {
            content: "🍒" !important;
            position: absolute !important;
            top: 40px !important;
            right: 180px !important;
            font-size: 1.8rem !important;
            animation: floatText 2s infinite ease-in-out !important;
            z-index: 8 !important;
        }
        .theme-retro_arcade .screen-home::after {
            content: "👾" !important;
            position: absolute !important;
            bottom: 35px !important;
            left: 40px !important;
            font-size: 1.8rem !important;
            animation: floatText 3s infinite ease-in-out !important;
            z-index: 8 !important;
        }
        .theme-retro_arcade .home-logo-part1 {
            font-family: 'Press Start 2P', monospace !important;
            font-size: 1.25rem !important;
            color: #00f0ff !important;
            text-shadow: 0 0 10px #00f0ff, 0 0 20px #00f0ff !important;
        }
        .theme-retro_arcade .home-logo-part2 {
            font-family: 'Press Start 2P', monospace !important;
            font-size: 1.25rem !important;
            color: #df00ff !important;
            text-shadow: 0 0 10px #df00ff, 0 0 20px #df00ff !important;
            margin-top: 6px !important;
        }
        .theme-retro_arcade .btn-start {
            background-color: black !important;
            color: #00f0ff !important;
            border: 4px solid #df00ff !important;
            border-radius: 0 !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 1rem !important;
            box-shadow: 0 0 15px #df00ff !important;
            letter-spacing: 0px !important;
            animation: pulseArcadeBtn 1s infinite alternate !important;
            opacity: 1 !important;
        }
        @keyframes pulseArcadeBtn {
            0% { border-color: #df00ff; box-shadow: 0 0 10px #df00ff; color: #00f0ff; }
            100% { border-color: #00f0ff; box-shadow: 0 0 20px #00f0ff; color: #df00ff; }
        }
        .theme-retro_arcade .home-slogan {
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.55rem !important;
            color: #00f0ff !important;
            text-shadow: 0 0 5px #00f0ff !important;
            text-transform: uppercase !important;
            letter-spacing: 0px !important;
        }
        .theme-retro_arcade .home-strip-container {
            border: 4px solid #df00ff !important;
            box-shadow: 0 0 20px rgba(223, 0, 255, 0.4) !important;
            background-color: black !important;
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
        /* RETRO ARCADE OVERRIDES FOR ALL SCREENS */
        /* ------------------------------------------------------------- */
        .theme-retro_arcade {
            background-color: #0c001a !important;
            font-family: 'Press Start 2P', monospace !important;
        }
        .theme-retro_arcade .kiosk-screen {
            background-color: #0c001a !important;
            background-image: 
                linear-gradient(rgba(223, 0, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(223, 0, 255, 0.1) 1px, transparent 1px) !important;
            background-size: 25px 25px !important;
        }
        .theme-retro_arcade .screen-header-back {
            background-color: black !important;
            border-bottom: 4px solid #df00ff !important;
        }
        .theme-retro_arcade .screen-title {
            color: #00f0ff !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.75rem !important;
            text-shadow: 0 0 5px #00f0ff !important;
        }
        .theme-retro_arcade .btn-back {
            color: #df00ff !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.6rem !important;
            text-shadow: 0 0 5px #df00ff !important;
        }
        .theme-retro_arcade .layout-card, 
        .theme-retro_arcade .frame-card {
            background-color: black !important;
            border: 3px solid #df00ff !important;
            border-radius: 0px !important;
            box-shadow: 0 0 10px rgba(223, 0, 255, 0.3) !important;
            color: #00f0ff !important;
        }
        .theme-retro_arcade .layout-card:hover,
        .theme-retro_arcade .frame-card:hover {
            border-color: #00f0ff !important;
            box-shadow: 0 0 15px #00f0ff !important;
            transform: scale(1.02) !important;
        }
        .theme-retro_arcade .layout-icon {
            background-color: transparent !important;
            border: 2px solid #00f0ff !important;
            color: #df00ff !important;
            border-radius: 0px !important;
        }
        .theme-retro_arcade .layout-desc {
            color: white !important;
            font-size: 0.55rem !important;
            line-height: 1.6 !important;
        }
        .theme-retro_arcade .frame-card-preview {
            background-color: black !important;
            border: 2px solid #df00ff !important;
            border-radius: 0px !important;
        }
        .theme-retro_arcade .frame-card-name {
            color: #00f0ff !important;
            font-size: 0.6rem !important;
        }
        .theme-retro_arcade .camera-slots-bar {
            background-color: black !important;
            border-left: 4px solid #df00ff !important;
        }
        .theme-retro_arcade .camera-rec-badge {
            background-color: black !important;
            color: #ff0055 !important;
            border: 2px solid #ff0055 !important;
            border-radius: 0px !important;
            text-shadow: 0 0 5px #ff0055 !important;
        }
        .theme-retro_arcade .capture-slot-box {
            background-color: black !important;
            border: 2px dashed #df00ff !important;
            border-radius: 0px !important;
        }
        .theme-retro_arcade .capture-slot-box.active {
            border-color: #00f0ff !important;
            border-style: solid !important;
            box-shadow: 0 0 10px #00f0ff !important;
        }
        .theme-retro_arcade .capture-slot-box.captured {
            border-color: #df00ff !important;
            border-style: solid !important;
        }
        .theme-retro_arcade .camera-trigger-btn,
        .theme-retro_arcade .camera-trigger-btn-floating {
            background-color: black !important;
            color: #00f0ff !important;
            border: 3px solid #df00ff !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.65rem !important;
            box-shadow: 0 0 10px #df00ff !important;
        }
        .theme-retro_arcade .camera-trigger-btn:hover {
            color: #df00ff !important;
            border-color: #00f0ff !important;
            box-shadow: 0 0 15px #00f0ff !important;
        }
        .theme-retro_arcade .section-title {
            color: #df00ff !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.6rem !important;
            text-shadow: 0 0 5px #df00ff !important;
        }
        .theme-retro_arcade .preview-controllers {
            background-color: black !important;
            border-left: 4px solid #df00ff !important;
        }
        .theme-retro_arcade .btn-confirm {
            background-color: black !important;
            color: #39ff14 !important;
            border: 3px solid #39ff14 !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.6rem !important;
            box-shadow: 0 0 10px #39ff14 !important;
        }
        .theme-retro_arcade .btn-retake {
            background-color: black !important;
            color: #ff0055 !important;
            border: 3px solid #ff0055 !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.6rem !important;
            box-shadow: 0 0 10px #ff0055 !important;
        }
        .theme-retro_arcade .btn-confirm:hover,
        .theme-retro_arcade .btn-retake:hover {
            transform: scale(1.02) !important;
        }
        .theme-retro_arcade .btn-clear-canvas {
            background-color: black !important;
            color: #df00ff !important;
            border: 2px solid #df00ff !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.5rem !important;
        }
        .theme-retro_arcade .stitched-canvas-container {
            border: 4px solid #df00ff !important;
            box-shadow: 0 0 20px rgba(223, 0, 255, 0.4) !important;
        }
        .theme-retro_arcade .share-info-panel {
            background-color: black !important;
            border-left: 4px solid #df00ff !important;
            color: #00f0ff !important;
        }
        .theme-retro_arcade .share-info-panel h2 {
            color: #00f0ff !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.75rem !important;
            text-shadow: 0 0 5px #00f0ff !important;
        }
        .theme-retro_arcade .share-qr-container {
            background-color: white !important;
            border: 4px solid #df00ff !important;
            border-radius: 0px !important;
            box-shadow: 0 0 15px rgba(223, 0, 255, 0.3) !important;
        }
        .theme-retro_arcade .btn-open-portal {
            background-color: black !important;
            color: #df00ff !important;
            border: 3px solid #df00ff !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.65rem !important;
            box-shadow: 0 0 10px #df00ff !important;
        }
        .theme-retro_arcade .btn-finish {
            background-color: black !important;
            color: #00f0ff !important;
            border: 3px solid #00f0ff !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.65rem !important;
            box-shadow: 0 0 10px #00f0ff !important;
        }
        .theme-retro_arcade .sim-dialog {
            background-color: black !important;
            border: 4px solid #df00ff !important;
            border-radius: 0px !important;
            color: #00f0ff !important;
        }
        .theme-retro_arcade .sim-dialog-title {
            color: #df00ff !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.75rem !important;
            text-shadow: 0 0 5px #df00ff !important;
        }
        .theme-retro_arcade .sim-dialog-desc {
            color: white !important;
            font-size: 0.55rem !important;
            line-height: 1.6 !important;
        }
        .theme-retro_arcade .sim-dialog-input {
            background-color: #0c001a !important;
            border: 2px solid #00f0ff !important;
            color: white !important;
            border-radius: 0px !important;
            font-family: 'Press Start 2P', monospace !important;
            font-size: 0.6rem !important;
        }
        .theme-retro_arcade .btn-dialog-confirm {
            background-color: black !important;
            color: #00f0ff !important;
            border: 2px solid #00f0ff !important;
            border-radius: 0px !important;
        }
        .theme-retro_arcade .btn-dialog-cancel {
            background-color: black !important;
            color: #ff0055 !important;
            border: 2px solid #ff0055 !important;
            border-radius: 0px !important;
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

                    <!-- SCREEN 1: HOME SCREEN -->
                    <div class="kiosk-screen screen-home active" id="screenHome">
                        <div class="home-logo-box" onclick="handleLogoTaps()">
                            <div class="home-logo-part1" id="logoPart1">Creative</div>
                            <div class="home-logo-part2" id="logoPart2">Studio</div>
                        </div>

                        <!-- Center section -->
                        <div class="home-center">
                            <button class="btn-start" onclick="startSession()">START</button>
                            <div class="home-slogan" id="homeSlogan">All You need is special</div>
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
        
        // Local variables
        let kioskMode = 'MULTI_EVENT'; // 'MULTI_EVENT' or 'DEDICATED'
        let activeEventId = 'general'; // Resolved Event ID
        let currentSessionEventId = 'general'; // Session event mapping
        
        let activePackageId = packagesList.length > 0 ? packagesList[0].id : '';
        
        function onPackageSelectChange() {
            activePackageId = document.getElementById('kioskPackageSelect').value;
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
            // Update clock
            setInterval(() => {
                const now = new Date();
                document.getElementById('statusClock').innerText = now.toTimeString().split(' ')[0].substring(0, 5);
            }, 1000);
            
            // Set up drawing canvas events
            setupCanvasDrawing();
            
            // Initial logo rendering
            updateHomeScreenBranding();
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
                updateHomeScreenBranding();
            }
        }

        // Dynamic branding changes on HomeScreen logo texts
        function updateHomeScreenBranding() {
            let eventName = "";
            let targetId = kioskMode === 'DEDICATED' ? activeEventId : currentSessionEventId;
            
            if (targetId && targetId !== 'general' && serverConfig.events) {
                const match = serverConfig.events.find(e => e.id === targetId);
                if (match) eventName = match.name;
            }
            
            const p1 = document.getElementById('logoPart1');
            const p2 = document.getElementById('logoPart2');
            const slogan = document.getElementById('homeSlogan');
            
            if (eventName) {
                // Split custom event name dynamically over two lines
                const words = eventName.split(' ');
                const half = Math.ceil(words.length / 2);
                p1.innerText = words.slice(0, half).join(' ');
                p2.innerText = words.slice(half).join(' ');
                slogan.innerText = "⭐ Sesi Acara Eksklusif ⭐";
            } else {
                p1.innerText = "Creative";
                p2.innerText = "Studio";
                slogan.innerText = "All You need is special";
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
            let filtered = matchingFrames.filter(f => f.event_id === eventIdFilter);
            if (filtered.length === 0) {
                filtered = matchingFrames.filter(f => f.event_id === 'general' || !f.event_id);
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
                        bgCtx.drawImage(img, slot.x, slot.y, slot.width, slot.height);
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
            };
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

            // Header Brand
            ctx.fillStyle = '#ffffff';
            ctx.font = '900 28px Outfit, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('CREATIVE STUDIO', 319, 60);

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
            ctx.fillText('Verified AI generated character by Creative Studio Kiosk', 319, 740);

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

                        document.getElementById('btnOpenWebPortal').href = data.download_url;
                        
                        console.log("Uploaded successfully!", data);

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
                            printCanvas.width = printWidth * 4;
                            printCanvas.height = printHeight * 4;
                            const printCtx = printCanvas.getContext('2d');
                            
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
