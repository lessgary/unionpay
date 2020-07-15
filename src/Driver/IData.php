<?php
/**
 * Created by WanDeHua.
 * User: WanDeHua 
 * Email:271920545@qq.com
 * Date: 2017/12/8
 * Time: 18:32
 */

namespace UnionPay\Driver;

Interface IData {
    public static function submit($post);
    public static function callback($post);
    public static function queryOrder($post);
//    public static function notifyurl($post);
//    public static function refundOrder($post);
}