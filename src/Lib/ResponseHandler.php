<?php
namespace UnionPay\Lib;

/**
 * 后台应答类
 * ============================================================================
 * api说明：
 * getKey()/setKey(),获取/设置密钥
 * getContent() / setContent(), 获取/设置原始内容
 * getParameter()/setParameter(),获取/设置参数值
 * getAllParameters(),获取所有参数
 * isTenpaySign(),是否签名,true:是 false:否
 * getDebugInfo(),获取debug信息
 * 
 * ============================================================================
 *
 */

class ResponseHandler  {

    private static $instance;

	/** 密钥 */
    private static $key = '';
	
	/** 应答的参数 */
    private static $parameters = [];
	
	/** debug信息 */
    private static $debugInfo = '';
	
	//原始内容
    private static $content = '';
	

    private function __construct() {
    }

    public static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }
		
	/**
	*获取密钥
	*/
    public static function getKey() {
		return self::$key;
	}
	
	/**
	*设置密钥
	*/
    public static function setKey($key) {
        self::$key = $key;
	}
	
	//设置原始内容
    public static function setContent($content) {
        self::$content = $content;
        foreach ($content as $k=>$v){
            self::setParameter($k, $v);
        }
	}
	
	//获取原始内容
    public static function getContent() {
		return self::$content;
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
     *设置debug信息
     */
    private static function setDebugInfo($debugInfo) {
        self::$debugInfo = $debugInfo;
    }

    /**
     *获取debug信息
     */
    public static function getDebugInfo() {
        self::$debugInfo;
    }

    /**
     * 验证签名是否正确
     * @param $data
     * @return bool
     */
    public static function checkSign(RequestHandler $request) {
        //返回的sign
        $returnSign = self::getParameter('sign');
        //返回参数生成sign
        $sign = $request->createSign(self::getParameter(),$request->getParameter('signType')?$request->getParameter('signType'):'md5');
       
        return $returnSign == $sign;
    }


}


?>