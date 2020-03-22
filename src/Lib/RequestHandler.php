<?php
namespace UnionPay\lib;
/**
 * 请求类
 * ============================================================================
 * api说明：
 * setAttr()/getAttr(),获取/设置配置项
 * getParameter()/setParameter(),获取/设置参数值
 * getAllParameters(),获取所有参数
 * getRequestURL(),获取带参数的请求URL
 * getDebugInfo(),获取debug信息
 * 
 * ============================================================================
 *
 */
class RequestHandler {

    private static $instance;

    /** 的参数 */
    private static $config=[
        'gateUrl'=>'', //网关url地址
        'key'=>'',//密钥
        'md5key'=>'',//密钥
    ];

    /** 请求的参数 */
    private static $parameters=[];

    private function __construct() {
	}

    static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     *设置config信息
     */
    public static function setAttr($name,$value){
        isset(self::$config[$name]) && self::$config[$name] = $value;
    }
    /**
     *获取config信息
     */
    public static function getAttr($name=''){
        return empty($name) ? self::$config : (isset(self::$config[$name])?self::$config[$name]:'');
    }

    /**
     *获取参数值
     */
    public static function getParameter($name='') {
        return empty($name) ? self::$parameters : (isset(self::$parameters[$name])?self::$parameters[$name]:'');
    }

    /**
     *设置参数值
     */
    public static function setParameter($name,$value){
        self::$parameters[$name] = $value;
    }

    /**
     * 一次性设置参数
     */
    public static function setReqParams($post,$filterField=null){
        if($filterField !== null){
            foreach($filterField as $k=>$v){
                unset($post[$v]);
            }
        }

        //判断是否存在空值，空值不提交
        foreach($post as $k=>$v){
            if(empty($v)){
                unset($post[$k]);
            }
        }

        self::$parameters = $post;
    }

	/**
	*获取带参数的请求URL
	*/
	public static function getRequestURL() {
	
		self::createSign();
		
		$reqPar = "";
		ksort(self::$parameters);
		foreach(self::$parameters as $k => $v) {
			$reqPar .= $k . "=" . urlencode($v) . "&";
		}
		
		//去掉最后一个&
		$reqPar = substr($reqPar, 0, strlen($reqPar)-1);
		
		$requestURL = self::getAttr('gateUrl') . "?" . $reqPar;
		
		return $requestURL;
		
	}
	/**
	*创建md5摘要
	*/
	public static function createSign($params, $signType = 'md5'){
        return self::sign(self::getSignContent($params), $signType);
	}
    /**
     * 生成signString
     * @param $params
     * @return string
     */
    public static function getSignContent($params) {
        //sign不参与计算
        $params['sign'] = '';

        //去除空值
        $params = array_filter($params,function($val){
            if ($val === '' || $val === false) {
                return false;;
            } else {
                return true;
            }
        });

        //排序
        ksort($params);

        $paramsToBeSigned = [];
        foreach ($params as $k=>$v) {
            $paramsToBeSigned[] = $k.'='.$v;
        }
        unset ($k, $v);

        //签名字符串
        $stringToBeSigned = implode('&', $paramsToBeSigned);
        $stringToBeSigned .= self::getAttr('md5key');
        
        return $stringToBeSigned;
    }
    /**
     * 生成签名
     * @param $data
     * @param string $signType
     * @return string
     */
    protected static function sign($data, $signType = "md5") {
        $sign = strtoupper(hash($signType, $data));
        self::setParameter('sign',$sign);
        return $sign;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
}

?>