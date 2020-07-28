<?php
/**
 * 适配类
 * Created by WanDeHua.
 * User: WanDeHua 
 * Email:271920545@qq.com
 * Date: 2017/12/8
 * Time: 18:37
 */

namespace UnionPay;

use Exception;

class Pay {

    private static $instance;

    private function __construct() {
    }

    public static function make($option=[]){

        if(!isset($option['type'])){
            throw new Exception('必传参数[type]为空');
        }

        if(!self::$instance) {

            $type = ucwords(strtolower($option['type']));
            if ($type == 'Ali' || $type == 'Wechat') {
                $type = 'Csb';
            }

            if ($type == 'Scan') {
                $type = 'Bsc';
            }

            $class = 'UnionPay\\Driver\\' . $type;

            try {
                self::$instance = $class::make();
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function __callStatic($method, $arguments)
    {

        if(method_exists(self::$instance,$method)){
            return call_user_func_array([self::$instance,$method],$arguments);
        }else{
            throw new Exception('方法不存在');
        }

    }

}