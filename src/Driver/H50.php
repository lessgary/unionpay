<?php
/**
 * 全民付移动支付 银联h5支付+服务窗支付
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


class H50 implements IData
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
        $appKey = $post['md5Key'];
        $content = [
            'msgSrc'=>$post['msgSrc'],
            'msgType'=>'trade.h5Pay',
            'instMid' => isset($post['instMid'])?$post['instMid']:'YUEDANDEFAULT',
            'mid' => $post['mid'],
            'tid' => $post['tid'],
            'totalAmount' => round($post['total_fee'], 0),
            'notifyUrl' => $post['notifyUrl'],
            'returnUrl' => $post['returnUrl'],
            'merOrderId' => $post['msgSrcID'] . $post['out_trade_no'],
            'requestTimestamp' => date('Y-m-d H:i:s'),
            'attachedData' => $post['attachedData'],
            'orderDesc' => $post['orderDesc'],
            'merAppId'=>$post['merAppId'],
            'expireTime'=>date('Y-m-d H:i:s',strtotime($post['time_expire'])),
            'signType'=>'md5'
        ];
        ksort($content);
        $sign = self::generateSign($content,$appKey,$content['signType']);
        $content['sign']=$sign;
        $paramurl = http_build_query($content, '', '&');
        $paramurl = self::$cfg->getAttr('h50_url') . '?' . $paramurl;
        self::setSuccess(
            [
                'url' => $paramurl
            ]
        );
        return true;

    }
    public static function generateSign($params, $key,$signType = 'md5') {
        return self::sign(self::getSignContent($params,$key), $signType);
    }

    /**
     * 生成signString
     * @param $params
     * @return string
     */
    static function getSignContent($params,$key) {
        //sign不参与计算
        $params['sign'] = '';
        //排序
        ksort($params);

        $paramsToBeSigned = [];
        foreach ($params as $k=>$v) {
            if(is_array($params[$k])){
                $v = json_encode($v,JSON_UNESCAPED_UNICODE);
            }else if(trim($v) == ""){
                continue;
            }
            $paramsToBeSigned[] = $k.'='. $v;
        }
        unset ($k, $v);
        //签名字符串
        $stringToBeSigned = (implode('&', $paramsToBeSigned));
        //str_replace('¬','&not',$stringToBeSigned);
        $stringToBeSigned .= $key;
        return $stringToBeSigned;
    }
    /**
     * 生成签名
     * @param $data
     * @param string $signType
     * @return string
     */
    protected static function sign($data, $signType = "md5") {
        $sign = md5(trim($data));
        return strtoupper($sign);
    }
    /**
     * 查询订单
     */
    //
    public static function queryOrder($post)
    {
        $appKey = $post['key'];
        self::$reqHandler->setParameter('msgSrc', $post['msgSrc']);
        self::$reqHandler->setParameter('requestTimestamp', date('Y-m-d H:i:s'));
        self::$reqHandler->setParameter('msgType', 'query');
        self::$reqHandler->setParameter('instMid', isset($post['instMid'])?$post['instMid']:'YUEDANDEFAULT');
        self::$reqHandler->setParameter('mid', $post['mid']);
        self::$reqHandler->setParameter('tid', $post['tid']);
        self::$reqHandler->setParameter('merOrderId', $post['msgSrcID'] . $post['out_trade_no']);
        self::$reqHandler->setParameter('signType','md5');
        $params=self::$reqHandler->getParameter();
        ksort($params);
        $sign = self::generateSign($params,$appKey,self::$reqHandler->getParameter('signType'));
        self::$reqHandler->setParameter('signType','md5');
        self::$reqHandler->setParameter('sign',$sign);
        self::$pay->setReqContent(self::$cfg->getAttr('h50_query_url'), self::$reqHandler->getParameter());
        if (self::$pay->call()) {
            self::$resHandler->setContent(self::$pay->getResContent());
            if (self::$resHandler->getParameter('errCode') == 'SUCCESS'&&self::$resHandler->getParameter('status')==='TRADE_SUCCESS') {
                $res = self::$resHandler->getParameter();
                $res['out_trade_no'] = $post['out_trade_no'];
                //客户支付的总费用
                $res['total_fee'] = $res['receiptAmount'];
                //第三方订单号
                $res['out_transaction_id'] = $res['targetOrderId'];
                //支付类型
                $res['trade_type'] =$res['targetSys'];
                //买家ID
                $res['subBuyerId']=$res['subBuyerId'];
                $res['attach'] = $post['attach'];
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
        } else {

            self::setError(self::$pay->getResponseCode(), self::$pay->getErrInfo());
            return false;
        }
    }

    public static function callback($post)
    {
        $str = file_get_contents('php://input');
        //file_put_contents('pay/'.time().'77h50-get.txt',json_encode($str));
        parse_str($str, $back_data);
        self::$resHandler->setContent($back_data);
        $params=self::$resHandler->getParameter();
        $before_sign=self::$resHandler->getParameter('sign');
        ksort($params);
       // file_put_contents('pay/77before_h50-get'.time().'.txt',$before_sign);
        //file_put_contents('pay/'.time().'77before_h51-get.txt',$post['key'].':'.$post['signType']);

        $sign = self::generateSign($params,$post['md5Key'],$post['signType']);
        //file_put_contents('pay/'.time().'after_h50-get.txt',$sign);
        if ($sign==$before_sign) {
            if (self::$resHandler->getParameter('status')== 'TRADE_SUCCESS') {
                self::$resHandler->setParameter('out_trade_no', $post['out_trade_no']);
                self::$resHandler->setParameter('total_fee', self::$resHandler->getParameter('buyerPayAmount'));
                self::$resHandler->setParameter('out_transaction_id', self::$resHandler->getParameter('targetOrderId'));
                self::$resHandler->setParameter('trade_type', self::$resHandler->getParameter('targetSys'));
                self::$resHandler->setParameter('subBuyerId', self::$resHandler->getParameter('subBuyerId'));
                return self::$resHandler->getParameter();
            } else {
                return false;
            }
        } else {
            return false;
        }

    }


}