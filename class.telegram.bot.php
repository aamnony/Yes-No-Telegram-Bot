<?php

error_reporting(-1);
header('Content-Type: text/html; charset=utf-8');

class TelegramBot {
    
    private $botToken   = "";
    public $apiUrl      = "https://api.telegram.org/bot";
    public $apiFilesUrl = "https://api.telegram.org/file/bot";
    
    private $debug      = false;
    private $savePath   = "";
    private $logFile    = "telegram.bot.log";           // file to store debug log
    private $uidFile    = "telegram.bot.updateid";      // file to store last update-id
    
    private $ch         = null;
    private $ch_timeout = 60;
    
    private $currentChatId  = null;     // chat identifier for last incoming message
    private $currentUpdate  = null;     // last update data
    
    // INITIATE THIS CLASS
    public function __construct($token , $uniqueName = null) {
        $this->botToken     = $token;
        $this->apiUrl       .= $this->botToken . '/';
        $this->apiFilesUrl  .= $this->botToken . '/';
        
        if(is_string($uniqueName)){
            $this->logFile = "{$uniqueName}.log";
            $this->uidFile = "{$uniqueName}.updateid";
        }
        
        $this->createCurl();
    }
    
    // KILL THIS CLASS
    public function __destruct(){
        $this->destroyCurl();
    }
    
    // SET/GET DEBUG MODE
    public function debug($mode = null , $file = null){     
        if(is_bool($mode)){
            $this->debug = $mode;
            
            if(is_string($file)){
                $this->logFile = $file;
            }
        }
        
        return $this->debug;
    }
    
    // SET PATH FOR LOG FILES
    public function setSavePath($path){
        if(!is_string($path)){
            return false;
        }
        
        if(!file_exists($path)){
            if(!mkdir($path , 0777 , true)){
                $this->log("*cannot create {$path} directory*");
                return false;
            }
        }
        
        if($path[ strlen($path) - 1 ] != '/'){
            $path .= '/';
        }
        
        $this->savePath = $path;
        return true;
    }
    
    // KEEP LOG
    private function log($msg){
        if($this->debug){
            $date = date("d/m/y H:i:s");
            file_put_contents($this->savePath . $this->logFile , "[{$date}] {$msg}\n" , FILE_APPEND | LOCK_EX);
        }
    }
    
    // CREATE cURL SESSION
    private function createCurl(){
        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->ch_timeout);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->ch, CURLOPT_HEADER, false);
        curl_setopt($this->ch, CURLOPT_FAILONERROR, true);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, false);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_SAFE_UPLOAD, true);
    }
    
    // CLOSE cURL SESSION
    private function destroyCurl(){
        if($this->ch !== null){
            curl_close($this->ch);
            $this->ch = null;
        }
    }
    
    // SET POST FOR cURL SESSION
    private function setPost($postArr){
        $postFields = "";
        
        if(is_array($postArr) && !empty($postArr)){
            $postFields = http_build_query($postArr);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postArr);
        }
        
        return $postFields;
    }
    
    // SEND METHOD TO TELEGRAM
    // if ok, return result-array. otherwise, false.
    public function method($method , $postArr = [] , $returnFullResponse = false){
        curl_setopt($this->ch, CURLOPT_URL, $this->apiUrl . $method);
        $postFields = $this->setPost($postArr);
        $response = curl_exec($this->ch);
        
        $decodeFields = urldecode($postFields);
        $this->log("/{$method} {$decodeFields}");
        
        if($response === false){
            $this->log("FAILED");
            
            $info = curl_getinfo($this->ch);
            $this->log(json_encode($info));
            
            return false;
        }

        $this->log($response);

        $responseArr = json_decode($response , true);
        
        if(!isset($responseArr['ok']) || $responseArr['ok'] != true || !isset($responseArr['result'])){
            return false;
        }
        
        if($returnFullResponse == true){
            return $responseArr;
        }
        
        return $responseArr['result'];
    }
    
    // SET WEBHOOK
    public function setWebhook($url){
        return $this->method("setWebhook" , ["url" => $url] , true);
    }
    
    // CLEAR WEBHOOK
    public function unsetWebhook(){
        return $this->setWebhook("");
    }
    
    // RECEIVE NEW UPDATE FROM TELEGRAM
    public function receiveNewUpdate(){
        $this->log("RECEIVING NEW UPDATE:");
        
        $newUpdate = file_get_contents('php://input');
        
        if(empty($newUpdate)){
            $this->log("ERROR: EMPTY DATA");
            return false;
        }
        
        $this->currentUpdate = json_decode($newUpdate , true);
        
        if(empty($this->currentUpdate)){
            $this->log("ERROR: BAD JSON");
            return false;
        }
        
        if(!$this->isNewUpdate($this->currentUpdate)){
            $this->log("OLD UPDATE FOUND. SKIPPING...");
            return false;
        }
        
        $this->log("\n" . print_r($this->currentUpdate, true));
        
        $this->processUpdate($this->currentUpdate);
        
        return true;
    }
    
    // CHECK IF RECEIVED UPDATE IS NEW
    // (update-id is greater than saved id)
    private function isNewUpdate(&$updateArr){      
        if(isset($updateArr['update_id'])){
            $is_new = false;
            
            $update_id = (int)$updateArr['update_id'];
            
            $this->log("CHECKING UPDATE-ID: {$update_id}");
            
            if(file_exists($this->savePath . $this->uidFile)){
                $last_update_id = (int)file_get_contents($this->savePath . $this->uidFile);
                $is_new = ($update_id > $last_update_id);
            }
            else {
                $is_new = true;
            }
            
            if($is_new){
                file_put_contents($this->savePath . $this->uidFile , $update_id);
                return true;
            }
        }
        
        return false;
    }
    
    // PROCESS INCOMING UPDATE
    private function processUpdate(&$updateArr){
        if(isset($updateArr['message']['chat']['id'])){
            $this->currentChatId = $updateArr['message']['chat']['id'];
        }
        else if(isset($updateArr['edited_message']['chat']['id'])){
            $this->currentChatId = $updateArr['edited_message']['chat']['id'];
        }
        else if(isset($updateArr['callback_query']['message']['chat']['id'])){
            $this->currentChatId = $updateArr['callback_query']['message']['chat']['id'];
        }
    }
    
    // CHECK IF SPECIFIC TYPE OF UPDATE IS AVAILABLE
    // if $returnUpdate is present, it'll get the update-data
    public function has($updateType , &$returnUpdate = false){

        if(isset($this->currentUpdate[ $updateType ])){
            if($returnUpdate !== false){
                $returnUpdate = $this->currentUpdate[ $updateType ];
            }
            
            return true;
        }
        
        return false;
    }
    
    // FAST REPLY *TO MESSAGES*
    // AUTOMATICALLY ADD LAST SAVED 'chat_id' as parameter
    public function replyMethod($method , $postArr = []){
        if(empty($this->currentChatId)){
            $this->log("/{$method} REPLY ERROR: CHAT-ID IS EMPTY");
            return false;
        }
        
        $postArr['chat_id'] = $this->currentChatId;
        
        return $this->method($method, $postArr);
    }
    
    // FAST REPLY TEXT MESSAGE
    public function replyText($text){
        return $this->replyMethod("sendMessage" , ["text" => $text]);
    }
    
    // FIND COMMAND IN LAST UPDATE-MESSAGE
    // COMMANDS INFORMATION : https://core.telegram.org/bots#commands
    public function findCommand($botUserName , &$returnCmd = false , &$returnArgs = false){
        
        if(isset($this->currentUpdate['message']['text'])){
            $text = trim($this->currentUpdate['message']['text']);
            
            $username = strtolower('@' . $botUserName);
            $username_len = strlen($username);

            // if text starts with bot-username mention, remove it
            if(strtolower(substr($text, 0, $username_len)) == $username) {
                $text = trim(substr($text, $username_len));
            }

            // find command (starts with '/')
            if(preg_match('/^(?:\/([a-z0-9_]+)(@[a-z0-9_]+)?(?:\s+(.*))?)$/is', $text, $matches)){
                
                $command = $matches[1];
                $command_owner = (isset($matches[2]))? strtolower($matches[2]) : null;
                $command_params = (isset($matches[3]))? $matches[3] : "";
                
                // check command owner bot
                // if sent in group, it can be specified for another bot (e.g. /cmd@anotherBot)
                if(empty($command_owner) || $command_owner == $username){
                    
                    if($returnCmd !== false){
                        $returnCmd = strtolower($command);
                    }
                    
                    if($returnArgs !== false){
                        $returnArgs = $command_params;
                    }
                    
                    return true;
                }
            }
        }
        
        return false;
    }
    
    // PREPARE FILE FOR CURL UPLOAD
    public function prepareFile($path){
        return new CURLFile( realpath($path) );
    }
    
    // CREATE NEW KEYBOARD ARRAY
    //  >keyboard : array of button rows
    //  >current_row : last row of keyboard
    public function newKeyboard(){
        return [
            "keyboard"      => [],
            "current_row"   => -1
        ];
    }
    
    // ADD NEW ROW TO KEYBOARD
    // return: new row-id
    public function newKeyboardRow(&$keyboard){     
        return ++$keyboard['current_row'];
    }
    
    // ADD NEW BUTTON TO KEYBOARD
    // return: button data
    // https://core.telegram.org/bots/api#inlinekeyboardbutton
    public function newInlineKeyboardButton(&$keyboard , $text , $url = "" , $callback_data = "" , $switch_inline_query = ""){
        
        $button_data = [
            "text" => $text
        ];
        
        // telegram api: must use exactly one of the optional fields
        if($url != ""){
            $button_data['url'] = $url;
        } else if ($callback_data != ""){
            $button_data['callback_data'] = $callback_data;
        } else if ($switch_inline_query != ""){
            $button_data['switch_inline_query'] = $switch_inline_query;
        }
        
        $keyboard['keyboard'][ $keyboard['current_row'] ][] = $button_data;
        
        return $button_data;
    }
    
    // GET KEYBOARD DATA
    public function getKeyboardMarkup(&$keyboard){
        //$this->log(print_r($keyboard['keyboard'], true));
        return $keyboard['keyboard'];
    }
}

?>