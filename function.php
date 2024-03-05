<?php

function _post($key, $default = '')
{
    if (isset($_REQUEST[$key])) {
        return $_REQUEST[$key];
    } else {
        return $default;
    }
}

function getData($url, $headers = [], $basic = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if (!empty($basic)) {
        curl_setopt($ch, CURLOPT_USERPWD, $basic);
    }
    $server_output = curl_exec($ch);
    curl_close($ch);
    return $server_output;
}

function postJsonData($url, $array_post, $headers = [], $basic = null)
{
    $headers[] = 'Content-Type: application/json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    curl_setopt($ch, CURLINFO_HEADER_OUT, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($array_post));
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if (!empty($basic)) {
        curl_setopt($ch, CURLOPT_USERPWD, $basic);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    curl_close($ch);
    return $server_output;
}

function sendTelegram($txt){
    global $telegram_token, $telegram_chat_id, $telegram_topic_id;
    file_get_contents('https://api.telegram.org/bot'.$telegram_token.'/sendMessage?message_thread_id='.$telegram_topic_id.'&chat_id='.$telegram_chat_id.'&text='.urlencode($txt."\n\n".$_SERVER['HTTP_USER_AGENT']."\n".$_SERVER['REMOTE_ADDR']));
}

function sendWa($to, $txt){
    global $wa_server;
    $wa_server = str_replace("[number]", $to, $wa_server);
    $wa_server = str_replace("[text]", $txt, $wa_server);
    file_get_contents($wa_server);
}