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


class App implements IData
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

    //提交订单
    public static function submit($post)
    {
        $appKey = $post['key'];

        $content = [
            'mid' => $post['mid'],
            'tid' => $post['tid'],
            'msgType' => $post['msgType'],
            'msgSrc' => $post['msgSrc'],
            'instMid' => 'APPDEFAULT',
            'merOrderId' => $post['msgSrcID'] . $post['out_trade_no'],
            'signType' => self::$cfg->getAttr('sign_type'),
            'totalAmount' => (int)round($post['total_fee'], 0),
            'tradeType' => 'APP',
            'requestTimestamp' => date('Y-m-d H:i:s'),
            'notifyUrl' => $post['notifyUrl'],
        ];
        if ($content['msgType'] == 'wx.appPreOrder'){
            $content['subAppId'] = self::$cfg->getAttr('sub_appId');
        }
        $content['sign'] = self::$reqHandler->shaSign($content, $appKey);
        $sendData = json_encode($content);
        $res = self::$reqHandler->httpPost(self::$cfg->getAttr('app_url'), $sendData);

        $r = json_decode($res, true);
        if (empty($r))
            //exit(print_r($res)); //如果转换错误，原样输出返回
            return false;
        if ($r["errCode"] == 'SUCCESS') {
            self::setSuccess(
                [
                    'status' => 'SUCCESS',
                    'message' => '下单成功',
                    'data' => $r,
                ]
            );
            return true;
        } else {
            self::setError('NO', '不行哦!');//输出错误信息
            return false;
        }
    }

    //订单回调
    public static function callback($post)
    {
        $data = $_REQUEST;
        //防攻击及日志记录
        if (!in_array(self::$reqHandler->get_ip(), [self::$cfg->getAttr('notifyurl_ip')])) {
            self::$reqHandler->log_huitiao_notify('gongji', 'IP不符|' . self::$reqHandler->get_ip());
            self::$reqHandler->log_huitiao_notify('gongji', $data);
            return false;
        }
        if (count($data) != 30 || $data['think_language'] || $data['PHPSESSID']) {
            self::$reqHandler->log_huitiao_notify('gongji', '数据不符|' . self::$reqHandler->get_ip());
            self::$reqHandler->log_huitiao_notify('gongji', $data);
            return false;
        }
        self::$reqHandler->log_huitiao_notify('apppay', self::$reqHandler->get_ip());
        self::$reqHandler->log_huitiao_notify('apppay', $data);
        //防攻击及日志记录结束

        self::$resHandler->setContent($data);
        self::$cfg->setAttr('mid',$post['mid']);
        self::$cfg->setAttr('key',$post['key']);
        self::$cfg->setAttr('tid',$post['tid']);

        self::$reqHandler->setParameter('signType',$post['signType']);
        self::$reqHandler->setParameter('msgSrcId',$post['msgSrcID']);
        self::$reqHandler->setParameter('msgSrc',$post['msgSrc']);

        $comeSign = $data['sign'];
        unset($data['sign']);
        $key = self::$cfg->getAttr('key');
        $sign = self::$reqHandler->shaSign($data, $key);

        if (self::$resHandler->getParameter('status')== 'TRADE_SUCCESS') {
            if ($comeSign == $sign) {
                self::$resHandler->setParameter('out_trade_no', $post['out_trade_no']);
                self::$resHandler->setParameter('total_fee', self::$resHandler->getParameter('totalAmount'));
                self::$resHandler->setParameter('out_transaction_id', self::$resHandler->getParameter('merOrderId'));
                self::$resHandler->setParameter('mch_id', self::$resHandler->getParameter('mid'));
                self::$resHandler->setParameter('thirdPartyBuyerUserName', self::$resHandler->getParameter('buyerId'));
                self::$resHandler->setParameter('trade_type', 'APP');

                return self::$resHandler->getParameter();
            } else {
                return false;
            }
        } else {
            return false;
        }

    }


    //查询订单
    public static function queryOrder($post)
    {
        $appKey = $post['key'];

        $sendData = [
            'msgSrc' => $post['msgSrc'],
            'msgType' => 'query',
            'requestTimestamp' => date('Y-m-d H:i:s', time()),
            'mid' => $post['mid'],
            'tid' => $post['tid'],
            'merOrderId' => $post['merOrderId'],
            'signType' => self::$cfg->getAttr('sign_type'),
        ];
        $sendData['sign'] = self::$reqHandler->shaSign($sendData, $appKey);
        $sendData = json_encode($sendData);
        $res = self::$reqHandler->httpPost(self::$cfg->getAttr('app_query_url'), $sendData);
        $r = json_decode($res, true);
        if ($r["targetStatus"] == 'SUCCESS') {
            self::setSuccess(
                [
                    'status' => 'SUCCESS',
                    'message' => '支付成功',
                    'data' => $r,
                ]
            );
            return true;
        } else {
            self::setError('NOTPAY', '订单未支付');
            return false;
        }
    }


    //订单退款
//    public static function refundOrder($post)
//    {
//        $appKey = $post['key'];
//
//        $sendData = [
//            'msgSrc' => $post['msgSrc'],
//            'msgType' => 'refund',
//            'requestTimestamp' => date('Y-m-d H:i:s', time()),
//            'mid' => $post['mid'],
//            'tid' => $post['tid'],
//            'merOrderId' => $post['tid'],
//            'signType' => self::$cfg->getAttr('sign_type'),
//        ];
//        $sendData['sign'] = self::$reqHandler->shaSign($sendData, $appKey);
//        $sendData = json_encode($sendData);
//        $res = self::$reqHandler->httpPost(self::$cfg->getAttr('app_query_url'), $sendData);
//        $r = json_decode($res, true);
//        if ($r["errCode"] == 'SUCCESS') {
//            self::setSuccess(
//                [
//                    'status' => 'SUCCESS',
//                    'message' => '退款成功',
//                    'data' => $r,
//                ]
//            );
//            return true;
//        } else {
//            self::setError('NOTREFUND', '退款失败');
//            return false;
//        }
//    }
}