#!/usr/bin/php
<?php

define('PID', getmypid());

chdir(__DIR__);

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}
include 'madeline.php';

include_once 'functions.php';

require 'colors/vendor/autoload.php';
$c = new Colors\Color();

$MadelineProto = null;

$update = null;

$data = [];

class LemonzPlugin {
    public function onUpdate($update){
        // SILENCE
    }
    
    public function onStart(){
        // SILENCE
    }
}

class Lemonz {
    public $settings = null;
    public $strings = null;
    public $me = null;
    
    public function __construct() {
        require_once 'settings.php';
        
        if (isset($GLOBALS['argv'][1]) and $GLOBALS['argv'][1] != 'background') {
            $settings['session'] = $GLOBALS['argv'][1];
        }
        
        $strings = json_decode(file_get_contents('strings_it.json'), 1);
        
        if (!file_exists('sessions')) {
            mkdir('sessions');
        }
        
        $this->settings = $settings;
        $this->strings = $strings;
        
        return true;
    }
    
    public function sbackground(){
        shell_exec('screen -d -m php '.escapeshellarg(__FILE__).' '.$this->settings['session']);
        echo PHP_EOL.$this->strings['background'].PHP_EOL;
        exit;
    }
    
    public function start(){
        global $MadelineProto;
        $MadelineProto = new \danog\MadelineProto\API($this->settings['session'], $this->settings['madeline']);
        echo ' '.$GLOBALS['c']('OK')->white->bold->bg_green.PHP_EOL;
        
        try {
            $me = $MadelineProto->get_self();
        } catch (Exception $e) {
            $me = false;
        }
        if ($me === false) {
            echo $this->strings['ask_phone_number'];
            $phoneNumber = trim(fgets(STDIN));
            $sentCode = $MadelineProto->phone_login($phoneNumber);
            echo $this->strings['ask_login_code'];
            $code = trim(fgets(STDIN, (isset($sentCode['type']['length']) ? $sentCode['type']['length'] : 5) + 1));
            $authorization = $MadelineProto->complete_phone_login($code);
            if ($authorization['_'] === 'account.password') {
                echo $this->strings['ask_2fa_password'];
                $password = trim(fgets(STDIN));
                if ($password == '') {
                    $password = trim(fgets(STDIN));
                }
                $authorization = $MadelineProto->complete_2fa_login($password);
            }
            $MadelineProto->session = $this->settings['session'];
            $MadelineProto->serialize($this->settings['session']);
            $me = $MadelineProto->get_self();
        }
        $this->me = $me;
		
        $MadelineProto->setEventHandler('\LemonzEventHandler');
        echo $GLOBALS['c']($this->strings['session_loaded'])->white->bold->bg_green.PHP_EOL;
        $MadelineProto->loop();
    }
    
    public function parse_update($update){
        global $MadelineProto;
        $result = ['chatid' => null, 'userid' => null, 'msgid' => null, 'type' => null, 'name' => null, 'username' => null, 'chatusername' => null, 'title' => null, 'msg' => null, 'cronjob' => null, 'info' => null, 'update' => $update];
        
        try {
            if (isset($update['message'])) {
                if (isset($update['message']['from_id'])) {
                    $result['userid'] = $update['message']['from_id'];
                }
                if (isset($update['message']['id'])) {
                    $result['msgid'] = $update['message']['id'];
                }
                if (isset($update['message']['message'])) {
                    $result['msg'] = $update['message']['message'];
                }
                if (isset($update['message']['to_id'])) {
                    $result['info']['to'] = $MadelineProto->get_info($update['message']['to_id']);
                }
                if (isset($result['info']['to']['bot_api_id'])) {
                    $result['chatid'] = $result['info']['to']['bot_api_id'];
                }
                if (isset($result['info']['to']['type'])) {
                    $result['type'] = $result['info']['to']['type'];
                }
                if (isset($result['userid'])) {
                    $result['info']['from'] = $MadelineProto->get_info($result['userid']);
                }
                if (isset($result['info']['to']['User']['self']) and isset($result['userid']) and $result['info']['to']['User']['self']) {
                    $result['chatid'] = $result['userid'];
                }
                if (isset($result['type']) and $result['type'] == 'chat') {
                    $result['type'] = 'group';
                }
                if (isset($result['info']['from']['User']['first_name'])) {
                    $result['name'] = $result['info']['from']['User']['first_name'];
                }
                if (isset($result['info']['to']['Chat']['title'])) {
                    $result['title'] = $result['info']['to']['Chat']['title'];
                }
                if (isset($result['info']['from']['User']['username'])) {
                    $result['username'] = $result['info']['from']['User']['username'];
                }
                if (isset($result['info']['to']['Chat']['username'])) {
                    $result['chatusername'] = $result['info']['to']['Chat']['username'];
                }
            }
        } catch (Exception $e) {
            $this->error($e);
        }
        
        return $result;
    }
    
    public function mUpdate($TGupdate){
        global $MadelineProto;
        global $update;
        $update = $TGupdate;
        
        try {
            foreach ($update as $varname => $var) {
                if ($varname !== 'update') {
                    $$varname = $var;
                }
            }
            if (isset($msg) and isset($chatid) and isset($type) and $msg) {
                if ($type == 'user') {
                    echo $name.' ('.$userid.') >>> '.$GLOBALS['c']($msg)->bold.PHP_EOL;
                } elseif ($type == 'channel') {
                    echo $title.' ('.$chatid.') >>> '.$GLOBALS['c']($msg)->bold.PHP_EOL;
                } else {
                    echo $name.' ('.$userid.') -> '.$title.' ('.$chatid.') >>> '.$GLOBALS['c']($msg)->bold.PHP_EOL;
                }
            }
            if ($this->settings['readmsg'] and isset($chatid) and isset($msgid) and $msgid and isset($type)) {
                try {
                    if (in_array($type, ['user', 'bot', 'group'])) {
                        $MadelineProto->messages->readHistory(['peer' => $chatid, 'max_id' => $msgid]);
                    } elseif (in_array($type, ['channel', 'supergroup'])) {
                        $MadelineProto->channels->readHistory(['channel' => $chatid, 'max_id' => $msgid]);
                    }
                } catch (Exception $e) {
                }
            }
            
            include 'commands.php';
        } catch (Exception $e) {
            $this->error($e);
        }
    }
    
    public function error($e){
        global $update;
        global $MadelineProto;
        echo $GLOBALS['c']($this->strings['error'].$e)->white->bold->bg_red.PHP_EOL;
        if (isset($update['chatid']) and $this->settings['send_errors']) {
            try {
                $MadelineProto->messages->sendMessage(['peer' => 43269287, 'message' => '<b>'.$this->strings['error'].'</b> <code>'.$e->getMessage().'</code>', 'parse_mode' => 'HTML']);
            } catch (Exception $e) {
            }
        }
    }
}

class LemonzEventHandler extends \danog\MadelineProto\EventHandler {
    public function onAny($update){
        $GLOBALS['Lemonz']->mUpdate($GLOBALS['Lemonz']->parse_update($update));
    }
    
    public function onLoop(){
      
    }
}

$Lemonz = new Lemonz();

if (isset($argv[1]) and $argv[1] == 'background') {
    $Lemonz->sbackground();
}

if (isset($argv[2]) and $argv[2] == 'background') {
    $Lemonz->sbackground();
}

echo $Lemonz->strings['loading'];

$Lemonz->start();