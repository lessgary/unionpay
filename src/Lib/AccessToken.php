<?php
namespace UnionPay\Lib;

use UnionPay\Help\Utils;

class AccessToken {

    private static $instance;
    private static $access_token = [];

    private function __construct() {
    }

    public static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function build($req,$pay,$cfg){

        $parameter = [];
        $parameter['appId'] = $cfg->getAttr('appId');
        $parameter['timestamp'] = date('YmdHis',time());
        $parameter['nonce'] = $req->getNonceStr();
        $parameter['appKey'] = $cfg->getAttr('key');
        $parameter['signature'] = self::signature($parameter);
        $pay->setReqContent($cfg->getAttr('access_token_url'), $parameter);
//        $pay->setDebug();

        if(!$pay->call()){
            return false;
        }

        $res = $pay->getResContent();

        self::$access_token['accessToken'] = $res['accessToken'];
        self::$access_token['expires'] = time() + $res['expiresIn'];

        Utils::make()->set_cache($parameter['appId'],self::$access_token);

        return self::$access_token;

    }

    public function getToken(RequestHandler $req, PayHttp $pay, \UnionPay\Config\Config $cfg, $data){

        if(!isset($data['appid']) || empty($data['appid'])){
            return false;
        }

        self::build($req, $pay,  $cfg);
        return self::$access_token['accessToken'];


//        self::$access_token = Utils::make()->get_cache($cfg->getAttr('appId'));
//
//        //前1分钟就失效
//        if( !self::$access_token || time() > (self::$access_token['expires'] - 60) ){
//            self::build($req, $pay,  $cfg);
//        }
//
//        return self::$access_token['accessToken'];

    }

    public static function signature($parameter){
        $str='';
        foreach ($parameter as $value){
            $str.=$value;
        }
        return sha1($str);
    }


	
}
?>