<?php
$token=getenv('BTOKEN');$chid=getenv('CHID');$tri=getenv('url');echo '1';
define('BOT_TOKEN', $token);
define('CHANNEL_ID',$chid);
define('WAIT_TIME', 3); 

function sendRequest($method, $parameters = []) {
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
function extractMedia($msg){

    if(isset($msg["photo"]))
        return [
            "type"=>"photo",
            "media"=>end($msg["photo"])["file_id"],
            "caption"=>$msg["caption"] ?? ""
        ];

    if(isset($msg["video"]))
        return [
            "type"=>"video",
            "media"=>$msg["video"]["file_id"],
            "caption"=>$msg["caption"] ?? ""
        ];

    if(isset($msg["document"]))
        return [
            "type"=>"document",
            "media"=>$msg["document"]["file_id"],
            "caption"=>$msg["caption"] ?? ""
        ];

    return null;
}
function selfTrigger($tri){
    $ch=curl_init($tri."?check=1");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>2,
        CURLOPT_NOSIGNAL=>1
    ]);
    curl_exec($ch);
    curl_close($ch);
}
$update = json_decode(file_get_contents('php://input'), true);
if (!isset($update['message'])) {
    http_response_code(200);
    exit;
}

$msg = $update['message'];

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (isset($msg['media_group_id'])) {

} else {
    sendSingleToChannel($msg);
}

http_response_code(200);
