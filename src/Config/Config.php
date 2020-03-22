<?php

namespace UnionPay\Config;

class Config
{

    private static $instance;

    private static $config = [
        'url' => '',
        'scan_url' => 'https://api-mop.chinaums.com/v2/poslink/transaction/pay',    //B扫C 提交
        //'scan_url'=>'http://58.247.0.18:29015/v2/poslink/transaction/pay',
        'scan_query_url' => 'https://api-mop.chinaums.com/v2/poslink/transaction/query',    //B扫C 查询
        //'scan_query_url'=>'http://58.247.0.18:29015/v2/poslink/transaction/query',
        'grcode_get_url' => 'https://api-mop.chinaums.com/v1/netpay/bills/get-qrcode', //C扫B 生成二维码
        //'grcode_get_url'=>'http://58.247.0.18:29015/v1/netpay/bills/get-qrcode',
        'grcode_query_url' => 'https://api-mop.chinaums.com/v1/netpay/bills/query',  //C扫B 查询二维码
        //'grcode_query_url'=>'http://58.247.0.18:29015/v1/netpay/bills/query',
        'access_token_url' => 'https://api-mop.chinaums.com/v1/token/access', //生成access_token
//        'access_token_url'=>'http://58.247.0.18:29015/v1/token/access',
        'h5_url' => 'https://api-mop.chinaums.com/v1/netpay/webpay/pay',
//        'h5_url'=>'http://58.247.0.18:29015/v1/netpay/webpay/pay',
        'h5_query_url' => 'https://api-mop.chinaums.com/v1/netpay/query',
//        'h5_query_url'=>'http://58.247.0.18:29015/v1/netpay/query',
        'mid' => '',//商户号
        'key' => '',//密钥
        'tid' => '',//终端号
        'appId' => '',//产品ID
        'version' => '2.0',
        'notify_url' => '',//异步回调通知地址
        'return_url' => '',//支付跳转地址
        'seller_url' => 'n',//二维码保存地址
    ];

    private function __construct()
    {
    }

    public static function make()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function setAttr($name, $value)
    {
        isset(self::$config[$name]) && self::$config[$name] = $value;
    }

    public static function getAttr($name = '')
    {
        return empty($name) ? self::$config : (isset(self::$config[$name]) ? self::$config[$name] : '');
    }


}