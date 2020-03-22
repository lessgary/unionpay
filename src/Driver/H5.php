<?php
/**
 * 全民付移动支付 公众号+服务窗支付
 * Created by WanDeHua.
 * User: WanDeHua
 * Email:271920545@qq.com
 * Date: 2017/12/8
 * Time: 9:45
 */

namespace UnionPay\Driver;

use UnionPay\Lib\AccessToken;
use UnionPay\Lib\ResponseHandler;
use UnionPay\Lib\RequestHandler;
use UnionPay\Lib\PayHttp;
use UnionPay\Config\Config;


class H5 implements IData
{

    protected static $instance;
    protected static $reqHandler = null;
    protected static $resHandler = null;
    protected static $pay = null;
    protected static $cfg = null;
    protected static $token = null;

    //支付参数,如有需要自行添加
    public static $params = [

    ];

    protected static $error= [
        'status'=>'',
        'message'=>'',
        'data'=>[],
    ];

    protected static $success = [];

    protected function __construct() {
        self::$pay = PayHttp::make();
        self::$cfg = Config::make();
        self::$resHandler = ResponseHandler::make();
        self::$reqHandler = RequestHandler::make();
        self::$token = AccessToken::make();
    }

    public static function make(){

        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

    protected static function setError($status,$message){
        self::$error['status'] = $status;
        self::$error['message'] = $message;
    }

    public static function getError(){
        return self::$error;
    }

    protected static function setSuccess($value=[]){
        self::$success = $value;
    }

    public static function getSuccess(){
        return self::$success;
    }

    public static function submit($post)
    {
        $appId = $post['appid'];
        $appKey = $post['key'];
        $timestamp = date('YmdHis');
        $nonce = self::$reqHandler->getNonceStr();

        $content = [
            'msgId' => $post['msgId'],
            'instMid' => 'YUEDANDEFAULT',
            'mid' => $post['mid'],
            'tid' => $post['tid'],
            'totalAmount' => round($post['total_fee'], 0),
            'notifyUrl' => $post['notifyUrl'],
            'returnUrl' => $post['returnUrl'],
            'merOrderId' => $post['msgSrcID'] . $post['out_trade_no'],
            'requestTimestamp' => date('Y-m-d H:i:s'),
            'attachedData' => $post['attachedData'],
            'orderDesc' => $post['orderDesc'],
        ];

        $content_hash = hash('sha256', json_encode($content, JSON_UNESCAPED_SLASHES), false);
        $data = $appId . $timestamp . $nonce . $content_hash;
        $signature = base64_encode(hash_hmac('sha256', $data, $appKey, true));

        $param = [
            'authorization' => 'OPEN-FORM-PARAM',
            'appId' => $appId,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'content' => json_encode($content, JSON_UNESCAPED_SLASHES),
            'signature' => $signature,
        ];

        $paramurl = http_build_query($param, '', '&');
        $paramurl = self::$cfg->getAttr('h5_url') . '?' . $paramurl;
        self::setSuccess(
            [
                'url' => $paramurl
            ]
        );
        return true;

    }
    /**
     * 查询订单
     */
    //
    public static function queryOrder($post)
    {
        self::$cfg->setAttr('mid', $post['mid']);
        self::$cfg->setAttr('key', $post['key']);
        self::$cfg->setAttr('tid', $post['tid']);
        self::$cfg->setAttr('appId', $post['appid']);

        self::$reqHandler->setAttr('gateUrl', self::$cfg->getAttr('h5_query_url'));
        self::$reqHandler->setAttr('key', self::$cfg->getAttr('key'));

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if ($access_token === false) {
            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setParameter('instMid', 'YUEDANDEFAULT');
        self::$reqHandler->setParameter('merOrderId', $post['msgSrcID'] . $post['out_trade_no']);    //账单号

        self::$reqHandler->setParameter('requestTimestamp', date('Y-m-d H:i:s', time()));    //报文请求时间:yyyy-MM-dd HH:mm:ss
        self::$reqHandler->setParameter('sign', '');    //签名

        self::$reqHandler->setParameter('mid', self::$cfg->getAttr('mid'));    //商户号
        self::$reqHandler->setParameter('tid', self::$cfg->getAttr('tid'));    //终端号

        self::$reqHandler->createSign(self::$reqHandler->getParameter(), self::$reqHandler->getParameter('signType'));//创建签名

        self::$pay->setAccessToken($access_token);
        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'), self::$reqHandler->getParameter());
//        if(session('current_user.id_user')==117){
//            self::$pay->setDebug();
//        }
        if (self::$pay->call()) {
//            dd(self::$pay->getResContent(),0);
            self::$resHandler->setContent(self::$pay->getResContent());
            // if(self::$resHandler->checkSign(self::$reqHandler)){
            if (self::$resHandler->getParameter('errCode') == 'SUCCESS') {
                $res = self::$resHandler->getParameter();
                unset($res['msgType'], $res['msgSrc'], $res['tid'], $res['billNo'], $res['billQRCode'], $res['instMid']);
                $res['out_trade_no'] = $post['out_trade_no'];
                $res['thirdPartyBuyerId'] = $res['buyerId']; //支付宝有可以没有buyerId
                $res['total_fee'] = $res['totalAmount'];
                $res['mch_id'] = $res['mid'];
                $res['out_transaction_id'] = $res['targetOrderId'];
                $res['trade_type'] = 'pay.alipay.native';
                $res['attach'] = $post['attach'];

//                dd($res);
                if ( $res['status'] == 'TRADE_SUCCESS') {

                    // file_put_contents('pay/ali_13.txt',json_encode($res));
                    self::setSuccess(
                        [
                            'status' => 'SUCCESS',
                            'message' => '支付成功',
                            'data' => $res,
                        ]
                    );
                    return true;

                } else {
                    self::setError('NOTPAY', '订单未支付');
                    return false;
                }

            } else {
                self::setError(self::$resHandler->getParameter('err_code'), self::$resHandler->getParameter('err_msg'));
                return false;
            }

//            }else{
//                self::setError('4003','签名错误');
//                return false;
//            }
        } else {

            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }
    }

    public static function callback($post)
    {
        $str = file_get_contents('php://input');
//        file_put_contents('pay/'.time().'-get.txt',json_encode($str));
        parse_str($str, $back_data);

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
                self::$resHandler->setParameter('total_fee', self::$resHandler->getParameter('totalAmount'));
                self::$resHandler->setParameter('out_transaction_id', self::$resHandler->getParameter('merOrderId'));
                self::$resHandler->setParameter('mch_id', self::$resHandler->getParameter('mid'));
                self::$resHandler->setParameter('thirdPartyBuyerUserName', self::$resHandler->getParameter('buyerId'));
                self::$resHandler->setParameter('trade_type', 'pay.alipay.native');

                return self::$resHandler->getParameter();
            } else {
                return false;
            }
        } else {
            return false;
        }

    }


}