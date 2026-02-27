<?php
$token=getenv('BTOKEN');
define('BOT_TOKEN', $token);
define('CHANNEL_ID', '625278344');
define('MEDIA_GROUP_TIMEOUT', 5); // ثانیه
define('DATA_FILE', __DIR__ . '/groups.json');
function sendRequest6($method, $data = null) 
     {         
         $headers       = array(  
			 'Content-Type: application/json',
             'charset: utf-8' 
         );
         $fields_string = ""; 
         //if (!is_null($data)) { 
         //    $fields_string = http_build_query($data); 
        // } 
         $ch = curl_init(); 
         curl_setopt($ch, CURLOPT_URL,"https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method); 
         curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
         curl_setopt($ch, CURLOPT_POST, true); 
         curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string); 
          
         $response     = curl_exec($ch); 
         $code         = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
         $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); 
         $curl_errno   = curl_errno($ch); 
         $curl_error   = curl_error($ch); 
         if ($curl_errno) { 
				$inf=curl_getinfo($ch);
				file_put_contents('ch920.txt',curl_errno($ch).':::'.curl_error($ch).print_r($inf,true)); 
        } 
         $json_response = json_decode($response); 
         curl_close($ch);
    return $curl_error ? null : json_decode($response, true);
          
     }
function sendRequest($method, $parameters = []) {
	$parameters=[
	'chat_id'=>'625278344',
	'text'=>'hii d'
	];
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    //curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
    //curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
    $response = curl_exec($ch);
				if(!$response){
				$inf=curl_getinfo($ch);
				file_put_contents('ch920.txt',curl_errno($ch).':::'.curl_error($ch).print_r($inf,true));
			}
    $error = curl_error($ch);
    curl_close($ch);
    return $error ? null : json_decode($response, true);
}

function detectMessageType($msg) {
    if (isset($msg['text'])) return 'text';
    if (isset($msg['photo'])) return 'photo';
    if (isset($msg['video'])) return 'video';
    return 'unknown';
}


function sendSingleToChannel($msg) {
    $type = detectMessageType($msg);
    $chat_id = CHANNEL_ID;
    $params = ['chat_id' => $chat_id];

    switch ($type) {
        case 'text':
            $params['text'] = $msg['text'];
            return sendRequest('sendMessage', $params);
        case 'photo':
            $params['photo'] = end($msg['photo'])['file_id'];
            if (!empty($msg['caption'])) $params['caption'] = $msg['caption'];
            return sendRequest('sendPhoto', $params);
        case 'video':
            $params['video'] = $msg['video']['file_id'];
            if (!empty($msg['caption'])) $params['caption'] = $msg['caption'];
            return sendRequest('sendVideo', $params);
        default:
            return null;
    }
}

// -------------------- مدیریت آلبوم‌ها --------------------
function loadGroups() {
    if (!file_exists(DATA_FILE)) return [];
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true) ?: [];
}

function saveGroups($groups) {
    file_put_contents(DATA_FILE, json_encode($groups, JSON_PRETTY_PRINT), LOCK_EX);
}

function processExpiredGroups() {
    $groups = loadGroups();
    $now = time();
    foreach ($groups as $id => &$group) {
        if ($now - $group['last_update'] >= MEDIA_GROUP_TIMEOUT) {

            usort($group['messages'], fn($a, $b) => $a['message_id'] <=> $b['message_id']);
            $media = [];
            foreach ($group['messages'] as $msg) {
                $type = detectMessageType($msg);
                $item = [
                    'type' => $type,
                    'media' => $type == 'photo' ? end($msg['photo'])['file_id'] : $msg['video']['file_id'],
                ];
                if (!empty($msg['caption'])) $item['caption'] = $msg['caption'];
                $media[] = $item;
            }
            $result = sendRequest('sendMediaGroup', ['chat_id' => CHANNEL_ID, 'media' => $media]);
            if ($result && ($result['ok'] ?? false)) {
                unset($groups[$id]);
            }
        }
    }
    saveGroups($groups);
}


$update = json_decode(file_get_contents('php://input'), true);file_put_contents('up.txt',print_r($update,true));
if (!isset($update['message'])) {
    http_response_code(200);
    exit;
}

$msg = $update['message'];
if (!isset($msg['from']) || isset($msg['forward_date'])) {
    http_response_code(200);
    exit;
}

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (isset($msg['media_group_id'])) {
    $groupId = $msg['media_group_id'];
    $groups = loadGroups();
    if (!isset($groups[$groupId])) {
        $groups[$groupId] = ['messages' => [], 'last_update' => time()];
    }
    $groups[$groupId]['messages'][] = $msg;
    $groups[$groupId]['last_update'] = time();
    saveGroups($groups);
    processExpiredGroups();
} else {
    sendSingleToChannel($msg);
}

http_response_code(200);