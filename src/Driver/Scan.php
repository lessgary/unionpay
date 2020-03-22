<?php
/**
 * 商家主动扫码用户微信/支付宝二维码以完成支付
 * Created by WanDeHua.
 * User: WanDeHua 
 * Email:271920545@qq.com
 * Date: 2017/12/8
 * Time: 9:45
 */

namespace UnionPay\Driver;

use UnionPay\Lib\ResponseHandler;
use UnionPay\Lib\RequestHandler;
use UnionPay\Lib\PayHttp;
use UnionPay\Lib\AccessToken;
use UnionPay\Config\Config;


class Scan implements IData {

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

        self::$reqHandler->setAttr('gateUrl',self::$cfg->getAttr('scan_url'));
        self::$reqHandler->setAttr('key',self::$cfg->getAttr('key'));
        self::$reqHandler->setAttr('md5key',$post['md5Key']);       

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if($access_token === false){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setParameter('payMode','CODE_SCAN');
        self::$reqHandler->setParameter('transactionCurrencyCode',156);
        self::$reqHandler->setParameter('msgSrcId',$post['msgSrcID']);
        self::$reqHandler->setParameter('merchantCode',$post['mid']);
        self::$reqHandler->setParameter('terminalCode',$post['tid']);
        self::$reqHandler->setParameter('transactionAmount',round($post['total_fee'],0));
        self::$reqHandler->setParameter('merchantOrderId',self::$reqHandler->getParameter('msgSrcId').$post['out_trade_no']);
        self::$reqHandler->setParameter('merchantRemark',sprintf('订单%s', $post['out_trade_no']));
        self::$reqHandler->setParameter('payCode',$post['auth_code']);

        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'),self::$reqHandler->getParameter());
        self::$pay->setAccessToken($access_token);
        self::$pay->setTimeOut(2);
//        if(session('current_user.id_user')==117){
//           self::$pay->setDebug();
//        }

        //银联扫码 是异步响应的，如果请求成功，刚是没有errCode参数的
        self::$pay->call();

        self::$resHandler->setContent(self::$pay->getResContent());

        if(!empty(self::$resHandler->getParameter('errCode'))){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }else{

            self::setSuccess(
                [
                    'time_end'=>date('YmdHis',(time()+3600)),
                    'transaction_id'=>'',
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

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if($access_token === false){
            self::setError(self::$pay->getResponseCode(),self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setAttr('gateUrl',self::$cfg->getAttr('scan_query_url'));
        self::$reqHandler->setAttr('key',self::$cfg->getAttr('key'));

        self::$reqHandler->setParameter('msgSrcId',$post['msgSrcID']);
        self::$reqHandler->setParameter('merchantCode',$post['mid']);
        self::$reqHandler->setParameter('terminalCode',$post['tid']);
        self::$reqHandler->setParameter('merchantOrderId',  self::$reqHandler->getParameter('msgSrcId').$post['out_trade_no']);    //账单号

        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'),self::$reqHandler->getParameter());
        self::$pay->setAccessToken($access_token);
        self::$pay->setTimeOut(2);
        //self::$pay->setDebug();

        //{"errCode":"00",
        //"errInfo":"00000成功响应码",
        //"originalTransactionTime":"20180530103225",
        //"queryResCode":"0",
        //"queryResDesc":"成功",
        //"originalPayCode":"134601952390095542",
        //"originalBatchNo":"000001",
        //"originalSystemTraceNum":"262701",
        //"origialRetrievalRefNum":"00005486194W",
        //"originalTransactionAmount":1,
        //"amount":1,
        //"thirdPartyDiscountInstruction":"现金支付0.01元。",
        //"thirdPartyDiscountInstrution":"现金支付0.01元。",
        //"thirdPartyName":"微信钱包",
        //"thirdPartyOrderId":"4200000109201805302086292440",
        //"thirdPartyPayInformation":"现金:1",
        //"orderId":"20180530103132000005486194"}
        self::$pay->call();
        self::$resHandler->setContent(self::$pay->getResContent());

        //银联扫码 不是同步响应的，如果超时还没有结果的话 就没有errCode的值，直接默认订单未支付
        if(empty(self::$resHandler->getParameter('errCode'))){
            self::setError('NOTPAY','订单未支付');
            return false;
        }else{

            if(self::$resHandler->getParameter('queryResCode') === '0'){

                $res = self::$resHandler->getParameter();
                $res['out_trade_no'] = $post['out_trade_no'];
                $res['total_fee'] = $res['originalTransactionAmount'];
                $res['out_transaction_id'] = $res['orderId'];
                $res['trade_type'] = $res['thirdPartyName'] == '微信钱包'  ? 'pay.weixin.micropay': 'pay.alipay.micropay'; // 支付宝钱包、微信钱包、银联二维码
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
        }
    }

    /**
     * 后台异步通知处理 (此接口没有异步通知)
     */
    public static function callback($post){
        echo 'success';
        exit();
    }

}