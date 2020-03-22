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

            if($option['type']=='callback'){

//                $str = file_get_contents('php://input');
//                parse_str($str, $back_data);
//                $back_data['billPayment'] = json_decode($back_data['billPayment'] ,true);
//
//                if(!isset($back_data['billPayment']['targetSys'])){
//                    throw new Exception('参数交易类型为空');
//                }else{
//                    if(strpos($back_data['billPayment']['targetSys'],'WXPay') !==false){
                        //$class = 'UnionPay\\Driver\\Wechat';
                        $class = 'UnionPay\\Driver\\Ali';
//                    }else if(strpos($back_data['billPayment']['targetSys'],'Alipay') !==false){
//                        $class = 'UnionPay\\Driver\\Ali';
//                    }
//                }

            } else if($option['type']=='callback2'){
                $class = 'UnionPay\\Driver\\H5';
            }else{
                $class = 'UnionPay\\Driver\\'.ucwords(strtolower($option['type']));
            }

            try {
                self::$instance = $class::make();
            } catch (Exception $e) {
                echo $e->getMessage();
                exit;
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