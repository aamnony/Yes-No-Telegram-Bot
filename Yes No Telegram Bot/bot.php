<?php

// -------------------------------------------
// DEFINE BOT INFORMATION
// -------------------------------------------
define("SCRIPT_BASE_URL"    , "https://while1.co.il/asaf/w1_yes_no_bot/");
define("BOT_TOKEN"          , "******"); // Telegram token.
define("BOT_NAME"           , "w1_yes_no_bot"); // Telegram bot name.
define("WEBHOOK_SECRET"     , "******"); // Password to access the webhook (can be anything)
define("WEBHOOK_URL"        , SCRIPT_BASE_URL . basename(__FILE__) . "?secret=" . WEBHOOK_SECRET);

define("MAX_INLINE_QUERY_RESULTS_COUNT" , 50); // see https://core.telegram.org/bots/api#answerinlinequery
define("INLINE_QUERY_CACHE_TIME"        , 5); // In seconds.


// Prevent access without a secret key.
if (!isset($_GET['secret']) || $_GET['secret'] != WEBHOOK_SECRET)
{
    header('HTTP/1.0 403 Forbidden');
    exit();
}

// Initiate bot class.
require_once "../class.telegram.bot.php";
$bot = new TelegramBot(BOT_TOKEN , BOT_NAME);
$bot->debug(true);
$bot->setSavePath(__DIR__);

// Set/remove webhook
if (isset($_GET['webhook']))
{
    print_r( ($_GET['webhook'] == "remove")? $bot->unsetWebhook() : $bot->setWebhook(WEBHOOK_URL) );
    exit();
}

// Check for new update.
if (!$bot->receiveNewUpdate())
{
    exit();
}

// Initiate API class.
require_once "class.yesno.wtf.api.php";

// User wants to know!
if ($bot->has("inline_query" , $query))
{
    $q = preg_replace('/\s+/', '', $query['query']); // Trim ALL whitespaces.
    
    switch (strtolower($q))
    {
        case "":
        case "yn":
        case "r":
        case "rnd":
        case "rand":
        case "random":
            $json = YesNoWtfApi::random();
            break;
            
        case "y":
        case "yes":
            $json = YesNoWtfApi::yes();
            break;
            
        case "n":
        case "no":
            $json = YesNoWtfApi::no();
            break;
            
        case "m":
        case "maybe":
            $json = YesNoWtfApi::maybe();
            break;
    }

    if ($json != false)
    {
        $results = [ createResultGif($json['answer'], $json['image']) ];
        
        $bot->method("answerInlineQuery", [
            "inline_query_id" => $query['id'] . $json['answer'],
            "is_personal"     => true,
            "cache_time"      => INLINE_QUERY_CACHE_TIME,
            "results"         => json_encode($results),
        ]);
    }
}

function createResultGif ($id, $gif_url, $caption = "", $thumb_url = "")
{
    $thumb_url = ($thumb_url == "") ? $gif_url : $thumb_url;

    return
    [
        'type'      => 'gif',
        'id'        => $id,
        'gif_url'   => $gif_url,
        'thumb_url' => $thumb_url,
        'caption'   => $caption,
    ];
}

?>