<?php
$queueFile = __DIR__ . '/queue.json';

// Safe lock reading
if (!function_exists('getQueueState')) {
    function getQueueState($file) {
        if (!file_exists($file)) {
            return [
                "active_queue_number" => 0,
                "active_session_id" => "",
                "queue_list" => []
            ];
        }
        
        $fp = fopen($file, 'r');
        if (!$fp) {
            return [
                "active_queue_number" => 0,
                "active_session_id" => "",
                "queue_list" => []
            ];
        }
        
        flock($fp, LOCK_SH);
        $content = file_get_contents($file);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        $data = json_decode($content, true);
        return $data ? $data : [
            "active_queue_number" => 0,
            "active_session_id" => "",
            "queue_list" => []
        ];
    }
}

// Safe lock writing
if (!function_exists('saveQueueState')) {
    function saveQueueState($file, $state) {
        $fp = fopen($file, 'w');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, json_encode($state, JSON_PRETTY_PRINT));
                fflush($fp);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
    }
}

if (!function_exists('completeSessionQueue')) {
    function completeSessionQueue($sessionId) {
        global $queueFile;
        if (empty($sessionId)) return false;
        
        $state = getQueueState($queueFile);
        $completed = false;
        
        foreach ($state['queue_list'] as &$item) {
            if ($item['session_id'] === $sessionId) {
                if ($item['status'] !== 'FINISHED') {
                    $item['status'] = 'FINISHED';
                    $completed = true;
                }
                break;
            }
        }
        unset($item);
        
        if ($completed) {
            // Find next queue in list that is WAITING
            $nextSession = null;
            usort($state['queue_list'], function($a, $b) {
                return $a['queue_number'] - $b['queue_number'];
            });
            
            foreach ($state['queue_list'] as &$item) {
                if ($item['status'] === 'WAITING') {
                    $item['status'] = 'ACTIVE';
                    $nextSession = $item;
                    break;
                }
            }
            unset($item);
            
            if ($nextSession) {
                $state['active_queue_number'] = $nextSession['queue_number'];
                $state['active_session_id'] = $nextSession['session_id'];
            } else {
                $state['active_queue_number'] = 0;
                $state['active_session_id'] = "";
            }
            
            saveQueueState($queueFile, $state);
            return true;
        }
        return false;
    }
}
