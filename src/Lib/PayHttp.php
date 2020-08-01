<?php
namespace UnionPay\Lib;
/**
 * http、https通信类
 * ============================================================================
 * api说明：
 * setReqContent($reqContent),设置请求内容，无论post和get，都用get方式提供
 * getResContent(), 获取应答内容
 * setMethod($method),设置请求方法,post或者get
 * getErrInfo(),获取错误信息
 * setCertInfo($certFile, $certPasswd, $certType="PEM"),设置证书，双向https时需要使用
 * setCaInfo($caFile), 设置CA，格式未pem，不设置则不检查
 * setTimeOut($timeOut)， 设置超时时间，单位秒
 * getResponseCode(), 取返回的http状态码
 * call(),真正调用接口
 * 
 * ============================================================================
 *
 */

class PayHttp {

    private static $instance;

	//请求内容，无论post和get，都用get方式提供
    private static $reqContent = [
	    'url'=>'',
	    'data'=>'',
    ];

    private static $debug = FALSE;

    private static $access_token = '';

	//应答内容
    private static $resContent;
	
	//错误信息
    private static $errInfo = '';
	
	//超时时间
    private static $timeOut = 120;
	
	//http状态码
    private static $responseCode = 0;

    private function __construct() {
    }

    public static function make(){
        if(!self::$instance){
            self::$instance = new static();
        }
        return self::$instance;
    }

	
	//设置请求内容
    public static function setReqContent($url,$data) {
		self::$reqContent['url']=$url;
		self::$reqContent['data']=$data;
	}
	
	//获取结果内容
    public static function getResContent() {
		return self::$resContent;
	}
	
	//获取错误信息
    public static function setErrInfo($value) {
		self::$errInfo = $value;
	}

    //获取错误信息
    public static function getErrInfo() {
        return self::$errInfo;
    }
	//设置超时时间,单位秒
    public static function setTimeOut($timeOut) {
        self::$timeOut = $timeOut;
	}

    public static function getResponseCode() {
        return self::$responseCode;
    }

    public static function setDebug(){
        self::$debug = true;
    }

    public static function setAccessToken($access_token){
        self::$access_token = $access_token;
    }

	//执行http调用
    public static function call() {
        self::$resContent = '';
		//启动一个CURL会话
		$ch = curl_init();

		// 设置curl允许执行的最长秒数

		curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeOut);

        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
		// 获取的信息以文件流的形式返回，而不是直接输出。
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		
        //发送一个常规的POST请求。
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, self::$reqContent['url']);
        //要传送的所有数据
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(self::$reqContent['data']));

        $header[] = "Content-type: application/json";

        if ( !empty(self::$access_token) )
            $header[] = "Authorization: OPEN-ACCESS-TOKEN AccessToken= ".self::$access_token;

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		// 执行操作
		$res = curl_exec($ch);
        self::$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if (self::$debug) {
            echo "=====url======\r\n";
            var_dump(self::$reqContent['url']);

            echo "=====post data======\r\n";
            var_dump(self::$reqContent['data']);
            var_dump(json_encode(self::$reqContent['data']));

            echo "=====headers======\r\n";
            print_r($header);

            echo '=====request info====='."\r\n";
            print_r( curl_getinfo($ch) );

            echo '=====response====='."\r\n";
            print_r( json_decode($res,true) );
        }

        $res = json_decode($res,true);

        if(isset($res['errCode']) && $res['errCode'] !=='0000' && $res['errCode'] !=='00' && $res['errCode'] !=='SUCCESS'){
            self::$resContent = $res;
            self::setErrInfo("请求错误 errCode=" . $res['errCode'] ."，errMsg=" . (isset($res['errInfo'])?$res['errInfo']:'') .", 请联系管理员解决");
            curl_close($ch);
            return false;
        }

        if (curl_errno($ch)) {

		    self::setErrInfo("请求地址错误 :" . curl_errno($ch) . " - " . curl_error($ch) . " - " . self::$responseCode. "，请联系管理员解决");
		    curl_close($ch);
		    return false;
		} else if(self::$responseCode  != "200") {
            self::setErrInfo("请求错误 httpcode=" . self::$responseCode ."，请联系管理员解决");
			curl_close($ch);
			return false;
		}
		
		curl_close($ch);

        self::$resContent = $res;
		return true;
	}


	
}
?>