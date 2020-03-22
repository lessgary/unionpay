<?php
/**
 * 用户使用支付宝扫描二维码以完成支付
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


class Csb implements IData
{

    protected static $instance;
    protected static $reqHandler = null;
    protected static $resHandler = null;
    protected static $pay = null;
    protected static $cfg = null;
    protected static $token = null;

    //支付参数,如有需要自行添加
    public static $params = [
        'msgId' => '',    //消息id
        'msgSrc' => '',    //消息来源
        'billNo' => '',    //账单号
        'billDate' => '',    //账单日期：yyyy-MM-dd
        'billDesc' => '',    //账单描述
        'totalAmount' => 0,    //支付总金额
        'requestTimestamp' => '',    //报文请求时间:yyyy-MM-dd HH:mm:ss
        'expireTime' => '',    //过期时间
        'qrCodeId' => '',    //二维码ID
        'systemId' => '',    //系统ID
        'secureTransaction' => '',    //担保交易标识
        'walletOption' => '',    //钱包选项
        'name' => '',    //实名认证姓名
        'mobile' => '',    //实名认证手机号
        'certType' => '',    //实名认证证件类型
        'certNo' => '',    //实名认证证件号
        'fixBuyer' => '',    //是否需要实名认证
        'limitCreditCard' => '',    //是否需要限制信用卡支付
        'sign' => '',    //签名
    ];

    protected static $error = [
        'status' => '',
        'message' => '',
        'data' => [],
    ];

    protected static $success = [];

    protected function __construct()
    {
        self::$pay = PayHttp::make();
        self::$cfg = Config::make();
        self::$resHandler = ResponseHandler::make();
        self::$reqHandler = RequestHandler::make();
        self::$token = AccessToken::make();
    }

    public static function make()
    {

        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    protected static function setError($status, $message)
    {
        self::$error['status'] = $status;
        self::$error['message'] = $message;
    }

    public static function getError()
    {
        return self::$error;
    }

    protected static function setSuccess($value = [])
    {
        self::$success = $value;
    }

    public static function getSuccess()
    {
        return self::$success;
    }

    public static function submit($post)
    {

        self::$cfg->setAttr('mid', $post['mid']);
        self::$cfg->setAttr('key', $post['key']);
        self::$cfg->setAttr('tid', $post['tid']);
        self::$cfg->setAttr('appId', $post['appid']);
        self::$cfg->setAttr('notify_url', $post['notifyUrl']);
        self::$cfg->setAttr('return_url', $post['returnUrl']);
        self::$cfg->setAttr('seller_url', $post['sellerUrl']);

        self::$reqHandler->setParameter('instMid', 'QRPAYDEFAULT');
        self::$reqHandler->setParameter('signType', $post['signType']);
        self::$reqHandler->setParameter('msgSrcId', $post['msgSrcID']);
        self::$reqHandler->setParameter('msgSrc', $post['msgSrc']);

        self::$reqHandler->setAttr('gateUrl', self::$cfg->getAttr('grcode_get_url'));
        self::$reqHandler->setAttr('key', self::$cfg->getAttr('key'));
        self::$reqHandler->setAttr('md5key', $post['md5Key']);

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if ($access_token === false) {
            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setParameter('msgType', 'bills.getQRCode');
        self::$reqHandler->setParameter('msgId', '');    //消息id
        self::$reqHandler->setParameter('billNo', self::$reqHandler->getParameter('msgSrcId') . $post['out_trade_no']);    //账单号
        self::$reqHandler->setParameter('billDate', substr($post['time_start'], 0, 4) . '-' . substr($post['time_start'], 4, 2) . '-' . substr($post['time_start'], 6, 2));    //账单日期：yyyy-MM-dd
        self::$reqHandler->setParameter('billDesc', sprintf('订单%s', $post['out_trade_no']));    //账单描述
        self::$reqHandler->setParameter('totalAmount', round($post['total_fee'], 0));    //支付总金额(单位：分)
        self::$reqHandler->setParameter('requestTimestamp', date('Y-m-d H:i:s', time()));    //报文请求时间:yyyy-MM-dd HH:mm:ss
        self::$reqHandler->setParameter('expireTime', '');    //过期时间
        self::$reqHandler->setParameter('qrCodeId', '');    //二维码ID
        self::$reqHandler->setParameter('systemId', '');    //系统ID
        self::$reqHandler->setParameter('secureTransaction', '');    //担保交易标识
        self::$reqHandler->setParameter('walletOption', '');    //钱包选项
        self::$reqHandler->setParameter('name', '');    //实名认证姓名
        self::$reqHandler->setParameter('mobile', '');    //实名认证手机号
        self::$reqHandler->setParameter('certType', '');    //实名认证证件类型
        self::$reqHandler->setParameter('certNo', '');    //实名认证证件号
        self::$reqHandler->setParameter('fixBuyer', '');    //是否需要实名认证
        self::$reqHandler->setParameter('limitCreditCard', '');    //是否需要限制信用卡支付
        self::$reqHandler->setParameter('sign', '');    //签名

        self::$reqHandler->setParameter('mid', self::$cfg->getAttr('mid'));    //商户号
        self::$reqHandler->setParameter('tid', self::$cfg->getAttr('tid'));    //终端号
        self::$reqHandler->setParameter('notifyUrl', self::$cfg->getAttr('notify_url'));    //异步回调通知地址
        self::$reqHandler->setParameter('return_url', self::$cfg->getAttr('return_url'));    //支付跳转地址
        self::$reqHandler->createSign(self::$reqHandler->getParameter(), self::$reqHandler->getParameter('signType'));//创建签名

        self::$pay->setAccessToken($access_token);
        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'), self::$reqHandler->getParameter());
//        self::$pay->setDebug();

        if (self::$pay->call()) {
            self::$resHandler->setContent(self::$pay->getResContent());

            //当返回状态与业务结果都为0时才返回支付二维码，其它结果请查看接口文档
            if (self::$resHandler->getParameter('errCode') == 'SUCCESS') {

                $payUrl = self::$resHandler->getParameter('billQRCode');

                $time = date('YmdHis', time());
                $fileName = 'unionpay_qr/' . self::$resHandler->getParameter('billNo') . "_" . md5($time) . '.png';
                \UnionPay\Lib\QRcode::png($payUrl, $fileName, 3, 4, 3, true);

                self::setSuccess(
                    [
                        'code_img_url' => self::$cfg->getAttr('seller_url') . '/' . $fileName,
                        'code_url' => self::$cfg->getAttr('seller_url') . '/' . $fileName,
                    ]
                );
                return true;
            } else {
                self::setError(self::$resHandler->getParameter('err_code'), self::$resHandler->getParameter('err_msg'));
                return false;
            }

        } else {
            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }

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

        self::$reqHandler->setParameter('instMid', 'QRPAYDEFAULT');
        self::$reqHandler->setParameter('signType', $post['signType']);
        self::$reqHandler->setParameter('msgSrcId', $post['msgSrcID']);
        self::$reqHandler->setParameter('msgSrc', $post['msgSrc']);

        self::$reqHandler->setAttr('gateUrl', self::$cfg->getAttr('grcode_query_url'));
        self::$reqHandler->setAttr('key', self::$cfg->getAttr('key'));

        $access_token = self::$token->getToken(self::$reqHandler, self::$pay, self::$cfg, $post);

        if ($access_token === false) {
            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }

        self::$reqHandler->setParameter('msgType', 'bills.query');
        self::$reqHandler->setParameter('billNo', self::$reqHandler->getParameter('msgSrcId') . $post['out_trade_no']);    //账单号
        self::$reqHandler->setParameter('billDate', date('Y-m-d'));    //账单日期：yyyy-MM-dd
        self::$reqHandler->setParameter('requestTimestamp', date('Y-m-d H:i:s'));    //报文请求时间:yyyy-MM-dd HH:mm:ss
        self::$reqHandler->setParameter('sign', '');    //签名
        self::$reqHandler->setParameter('mid', self::$cfg->getAttr('mid'));    //商户号
        self::$reqHandler->setParameter('tid', self::$cfg->getAttr('tid'));    //终端号
        self::$reqHandler->createSign(self::$reqHandler->getParameter(), self::$reqHandler->getParameter('signType'));//创建签名
        self::$resHandler->setParameter('sign', self::$reqHandler->getParameter('sign'));

        self::$pay->setAccessToken($access_token);
        self::$pay->setReqContent(self::$reqHandler->getAttr('gateUrl'), self::$reqHandler->getParameter());

        if (self::$pay->call()) {

            self::$resHandler->setContent(self::$pay->getResContent());
            // if(self::$resHandler->checkSign(self::$reqHandler)){
            if (self::$resHandler->getParameter('errCode') == 'SUCCESS') {
                $res = self::$resHandler->getParameter();
                unset($res['msgType'], $res['msgSrc'], $res['tid'], $res['billNo'], $res['billQRCode'], $res['instMid']);
                $res['out_trade_no'] = $post['out_trade_no'];
                $res['thirdPartyBuyerId'] = $res['billPayment']['buyerId'] ? $res['billPayment']['buyerId'] : $res['billPayment']['paySeqId']; //支付宝有可以没有buyerId
                $res['total_fee'] = $res['billPayment']['totalAmount'];
                $res['mch_id'] = $res['mid'];
                $res['out_transaction_id'] = $post['out_trade_no'];
                $res['trade_type'] = 'pay.alipay.native';
                $res['attach'] = $post['attach'];
                if (isset($res['billPayment']) && $res['billPayment']['status'] == 'TRADE_SUCCESS') {

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
        parse_str($str, $back_data);
        //$back_data['billPayment'] = json_decode($back_data['billPayment'] ,true);
//       file_put_contents('pay/'.time().'-2231.txt',json_encode($back_data));
        self::$resHandler->setContent($back_data);
        self::$cfg->setAttr('mid', $post['mid']);
        self::$cfg->setAttr('key', $post['key']);
        self::$cfg->setAttr('tid', $post['tid']);

        self::$reqHandler->setParameter('signType', $post['signType']);
        self::$reqHandler->setParameter('msgSrcId', $post['msgSrcID']);
        self::$reqHandler->setParameter('msgSrc', $post['msgSrc']);

        self::$reqHandler->setAttr('gateUrl', self::$cfg->getAttr('url'));
        self::$reqHandler->setAttr('key', self::$cfg->getAttr('key'));

//        file_put_contents('pay/'.time().'-get.txt',json_encode(self::$reqHandler->getParameter()));
//        file_put_contents('pay/'.time().'-token.txt',json_encode(self::$resHandler->checkSign(self::$reqHandler)));
        if (self::$resHandler->checkSign(self::$reqHandler)) {
            $xml['billPayment'] = json_decode(self::$resHandler->getParameter('billPayment'), true);
            if ($xml['billPayment']['status'] == 'TRADE_SUCCESS') {

                self::$resHandler->setParameter('billPayment', $xml['billPayment']);
                self::$resHandler->setParameter('out_trade_no', $post['out_trade_no']);
                self::$resHandler->setParameter('total_fee', self::$resHandler->getParameter('totalAmount'));
                self::$resHandler->setParameter('out_transaction_id', self::$resHandler->getParameter('billNo'));
                self::$resHandler->setParameter('mch_id', self::$resHandler->getParameter('mid'));
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