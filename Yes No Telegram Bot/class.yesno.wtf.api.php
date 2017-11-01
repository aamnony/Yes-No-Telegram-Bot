<?php

class YesNoWtfApi
{
    const API_DOMAIN = 'https://yesno.wtf/api/';
    
    public static function yes ()
    {
        return self::get_json(self::API_DOMAIN . '?' . http_build_query(['force' => 'yes']));
    }
    
    public static function no ()
    {
        return self::get_json(self::API_DOMAIN . '?' . http_build_query(['force' => 'no']));
    }
    
    public static function maybe ()
    {
        return self::get_json(self::API_DOMAIN . '?' . http_build_query(['force' => 'maybe']));
    }
    
    public static function random ()
    {
        return self::get_json(self::API_DOMAIN);
    }
    
    private static function get_json ($url)
    {
        $result = file_get_contents($url);
        if (is_string($result))
        {
            return json_decode($result, true);
        }
        return false;
    }
}

?>