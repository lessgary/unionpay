<?php
/**
 * 用户使用微信扫描二维码以完成支付
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


class Wechat extends Ali {
    protected function __construct() {
        parent::__construct();
    }
}