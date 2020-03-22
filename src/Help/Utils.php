<?php
namespace UnionPay\Help;

define('HELP_CACHE_DIR', dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/Cache/');

class Utils{

    private static $instance;

    private static $cache_dir = '';

    private function __construct() {
    }

    public static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function getCacheDir() {
        return empty(self::$cache_dir) ? HELP_CACHE_DIR : self::$cache_dir;
    }

    public static function setCacheDir($dir){
        self::$cache_dir = $dir;
    }

    public static function set_cache($name,$data){
        $dir = self::getCacheDir();
        $filename = $dir . $name . '.php';

        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        if(false === file_put_contents($filename,serialize($data))){
            return false;
        }else{
            return true;
        }
    }

    public static function get_cache($name){
        $dir = self::getCacheDir();
        $filename = $dir . $name . '.php';

        if(!is_file($filename)) return false;

        return unserialize(file_get_contents($filename));

    }

}
?>