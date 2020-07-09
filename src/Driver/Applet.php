<?php
/**
 * 微信小程序支付
 * Created by LeiCanMing
 * User: WanDeHua
 * Email:271920545@qq.com
 * Date: 2020/3/24
 * Time: 9:45
 */

namespace UnionPay\Driver;

use UnionPay\Lib\ResponseHandler;
use UnionPay\Lib\RequestHandler;
use UnionPay\Lib\PayHttp;
use UnionPay\Lib\AccessToken;
use UnionPay\Config\Config;


class Applet implements IData {

    private static $instance;

    private static $resHandler = null;
    private static $reqHandler = null;
    private static $pay = null;
    private static $cfg = null;
    private static $token = null;

    private static $error= [
        'status'=>'',
        'message'=>'',
        'data'=>[],
    ];

    private static $success = [];

    private function __construct() {
        self::$resHandler = ResponseHandler::make();
        self::$reqHandler = RequestHandler::make();
        self::$pay = PayHttp::make();
        self::$cfg = Config::make();
        self::$token = AccessToken::make();
    }

    public static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

    public static function handle(){

    }

    private static function setError($status,$message){
        self::$error['status'] = $status;
        self::$error['message'] = $message;
    }

    public static function getError(){
        return self::$error;
    }

    private static function setSuccess($value=[]){
        self::$success = $value;
    }

    public static function getSuccess(){
        return self::$success;
    }


    /**
     * 提交订单信息
     */
    public static function submit($post){
        self::$cfg->setAttr('mid',$post['mid']);
        self::$cfg->setAttr('key',$post['key']);
        self::$cfg->setAttr('tid',$post['tid']);
        self::$cfg->setAttr('appId',$post['appid']);
        self::$cfg->setAttr('notify_url',$post['notifyUrl']);
        self::$cfg->setAttr('return_url',$post['returnUrl']);
        self::$cfg->setAttr('seller_url',$post['sellerUrl']);
        self::$cfg->setAttr('access_token_url', $post['accessTokenUrl']);

        self::$reqHandler->setAttr('gateUrl',self::$cfg->getAttr('applet_url'));
        self::$reqHandler->setAttr('key',self::$cfg->getAttr('key'));
        self::$reqHandler->setAttr('md5key',$post['md5Key']);

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);
        if($access_token === false){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }
        self::$reqHandler->setParameter('requestTimestamp',date('Y-m-d H:i:s',strtotime($post['time_start'])));
        self::$reqHandler->setParameter('mid',$post['mid']);
        self::$reqHandler->setParameter('tid',$post['tid']);
        self::$reqHandler->setParameter('instMid','MINIDEFAULT');
        self::$reqHandler->setParameter('tradeType','MINI');
        self::$reqHandler->setParameter('merOrderId',$post['out_trade_no']);
        self::$reqHandler->setParameter('expireTime',date('Y-m-d H:i:s',strtotime($post['time_expire'])));
        self::$reqHandler->setParameter('subOpenId',$post['openid']);
        self::$reqHandler->setParameter('orderDesc','新预约系统订单');
        self::$reqHandler->setParameter('notifyUrl',$post['notifyUrl']);
        self::$reqHandler->setParameter('returnUrl',$post['returnUrl']);
        self::$reqHandler->setParameter('totalAmount',round($post['total_fee'], 0));

        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'),self::$reqHandler->getParameter());
        self::$pay->setAccessToken($access_token);
        self::$pay->setTimeOut(10);
        self::$pay->call();

        self::$resHandler->setContent(self::$pay->getResContent());
        if(!empty(self::$resHandler->getParameter('errCode'))&&self::$resHandler->getParameter('errCode')!=='SUCCESS'){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }else{
            $data=self::$pay->getResContent();
            self::setSuccess(
                [
                    'data'=>$data['miniPayRequest']
                    //'time_end'=>date('YmdHis',(time()+3600)),
                   // 'transaction_id'=>'',
                ]
            );
            return true;
        }

    }

    /**
     * 查询订单
     */
    //
    public static function queryOrder($post){
        self::$cfg->setAttr('mid',$post['mid']);
        self::$cfg->setAttr('key',$post['key']);
        self::$cfg->setAttr('tid',$post['tid']);
        self::$cfg->setAttr('appId',$post['appid']);
        self::$cfg->setAttr('access_token_url', $post['accessTokenUrl']);

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if($access_token === false){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setAttr('gateUrl',self::$cfg->getAttr('applet_query_url'));

        self::$reqHandler->setParameter('requestTimestamp',date('Y-m-d H:i:s'));
        self::$reqHandler->setParameter('mid',$post['mid']);
        self::$reqHandler->setParameter('tid',$post['tid']);
        self::$reqHandler->setParameter('instMid ','QRPAYDEFAULT');
        self::$reqHandler->setParameter('merOrderId',$post['out_trade_no']);

        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'),self::$reqHandler->getParameter());
        self::$pay->setAccessToken($access_token);
        self::$pay->setTimeOut(2);
        if (self::$pay->call()) {
            self::$resHandler->setContent(self::$pay->getResContent());
            if (self::$resHandler->getParameter('errCode') == 'SUCCESS'&&self::$resHandler->getParameter('status')==='TRADE_SUCCESS') {
                $res = self::$resHandler->getParameter();
                $res['out_trade_no'] = $post['out_trade_no'];
                $res['total_fee'] = $res['receiptAmount'];
                $res['out_transaction_id'] = $res['orderId'];
                $res['trade_type'] =$res['targetSys'];
                $res['attach'] = $post['attach'];

                //file_put_contents('pay/scan_13.txt',json_encode($res));
                self::setSuccess(
                    [
                        'status'=>'SUCCESS',
                        'message'=>'支付成功',
                        'data'=> $res,
                    ]
                );
                return true;

            }else{
                self::setError('NOTPAY','订单未支付');
                return false;
            }
        }else{
            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }
    }
    public static function callback($post)
    {
        $str = file_get_contents('php://input');
//        file_put_contents('pay/'.time().'-get.txt',json_encode($str));
        parse_str($str, $back_data);
        //$back_data['billPayment'] = json_decode($back_data['billPayment'] ,true);
         // file_put_contents('pay/'.time().'-2288.txt',json_encode($back_data));
        self::$resHandler->setContent($back_data);
        self::$cfg->setAttr('mid',$post['mid']);
        self::$cfg->setAttr('key',$post['key']);
        self::$cfg->setAttr('tid',$post['tid']);

        self::$reqHandler->setParameter('signType',$post['signType']);
        self::$reqHandler->setParameter('msgSrcId',$post['msgSrcID']);
        self::$reqHandler->setParameter('msgSrc',$post['msgSrc']);

        self::$reqHandler->setAttr('key',self::$cfg->getAttr('key'));

        if (self::$resHandler->checkSign(self::$reqHandler)) {
            if (self::$resHandler->getParameter('status')== 'TRADE_SUCCESS') {

                self::$resHandler->setParameter('out_trade_no', $post['out_trade_no']);
                self::$resHandler->setParameter('total_fee', self::$resHandler->getParameter('buyerPayAmount'));
                return self::$resHandler->getParameter();
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

}