<?php
namespace rester\sql;
use Exception;

/**
 *	@class		cfg
 *	@author	    Kevin Park (kevinpark@webace.co.kr)
 *	@version	1.0
 *	@brief		기본 설정 정보
 *	@date	    2018.04.25 - 생성
 */
class cfg
{
    const query_version = 'v';
    const query_module = 'm';
    const query_proc = 'proc';
    private static $name = 'rester.ini';  // 파일명
    private static $data;	// 설정정보

    protected static $default = array(

        'access_control'=>array(
            'allows_origin'=>'*'
        ),

        'default'=>array(
            'debug_mode'=>false,
            'timezone'=>'Asia/Seoul'
        )

    );

    /**
     * @return string
     */
    public static function module() { return self::Get('module'); }

    /**
     * @param string $module
     * @return string
     */
    public static function change_module($module)
    {
        $old = self::module();
        self::$data['module'] = $module;
        return $old;
    }

    /**
     * @return string
     */
    public static function proc() { return self::Get('proc'); }

    /**
     * @param string $proc
     * @return string
     */
    public static function change_proc($proc)
    {
        $old = self::proc();
        self::$data['proc'] = $proc;
        return $old;
    }

    /**
     * @return array
     * @throws Exception
     */
    public static function parameter() { return self::Get('request-body'); }

    /**
     * @return string
     * @throws Exception
     */
    public static function token() { return self::Get('request-body','token'); }

    /**
     * @return array
     * @throws Exception
     */
    public static function cache() { return self::Get('cache'); }

    /**
     * extract body parameter from json body, POST and GET
     *
     * json 으로 데이터가 넘어왔을 경우 (php://input)
     * php://input 가 unset이 되지 않아 call_module 함수를 호출할 때에 파라미터 변경이 되지 않는 문게가 있었음
     * get < json < post 순서로 덮어 씌우는 방식으로 해결함
     */
    public static function init_parameter()
    {
        // Extract request parameter
        // Json, POST, GET
        $json = json_decode(file_get_contents('php://input'),true);
        if(!$json) $json = [];

        if(!is_array($_POST)) $_POST = [];
        if(!is_array($_GET)) $_GET = [];

        self::$data['request-body'] = $_GET;

        foreach($json as $k=>$v)
        {
            self::$data['request-body'][$k] = $v;
        }

        foreach($_POST as $k=>$v)
        {
            self::$data['request-body'][$k] = $v;
        }

        unset($_POST);
        unset($_GET);
    }

    /**
     * Initialize default config
     *
     * @throws Exception
     */
    public static function init()
    {
        // Load config
        $path = dirname(__FILE__).'/../../cfg/'.self::$name;
        if(is_file($path)) $cfg = parse_ini_file($path,true, INI_SCANNER_TYPED);
        else throw new Exception("There is no config file.(rester.ini)");

        // Set default value
        foreach (self::$default as $k=>$v)
        {
            foreach ($v as $kk => $vv)
            {
                if (!isset($cfg[$k][$kk])) $cfg[$k][$kk] = $vv;
            }
        }

        // Extract access control
        if($cfg['access_control']['allows_origin']!='*') $cfg['access_control']['allows_origin'] = explode(',', $cfg['access_control']['allows_origin']);
        array_walk_recursive($cfg, function(&$v) { $v = trim($v); });

        // extract version
        if(preg_match('/^[0-9][0-9.]*$/i',$_GET[self::query_version],$matches))
        {
            $cfg['version'] = $matches[0];
        }
        else
        {
            if($_GET[self::query_version]=='') throw new Exception("Access denied.(root directory)");
            else throw new Exception("Invalid version name.");
        }
        unset($_GET[self::query_version]);

        // Check module name
        if(preg_match('/^[a-z0-9-_]*$/i',strtolower($_GET[self::query_module]),$matches)) $cfg['module'] = $matches[0];
        else throw new Exception("Invalid module name.");
        unset($_GET[self::query_module]);

        // Check procedure name
        if(preg_match('/^[a-z0-9-_]*$/i',strtolower($_GET[self::query_proc]),$matches)) $cfg['proc'] = $matches[0];
        else throw new Exception("Invalid procedure name.");
        unset($_GET[self::query_proc]);

        // Check method
        if($_SERVER['REQUEST_METHOD']=='POST') $cfg['method'] = $_SERVER['REQUEST_METHOD'];
        else throw new Exception("Invalid request METHOD.(Allowed POST)");

        // Check allows ip address
        // Check ip from share internet
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $access_ip=$_SERVER['HTTP_CLIENT_IP'];
        }
        //to check ip is pass from proxy
        else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $access_ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $access_ip=$_SERVER['REMOTE_ADDR'];
        }

        if($cfg['access_control']['allows_origin']!='*')
        {
            if(!is_array($cfg['access_control']['allows_origin'])) $cfg['access_control']['allows_origin'] = array($cfg['access_control']['allows_origin']);
            if(!in_array($access_ip,$cfg['access_control']['allows_origin'])) throw new Exception("Access denied.(Not allowed ip address:{$access_ip})");
        }
        self::$data = $cfg;

        self::init_parameter();
    }

    /**
     * return config
     *
     * @param string $section
     * @param string $key
     *
     * @return array|string
     */
    public static function get($section='', $key='')
    {
        if($section==='') return self::$data;
        if($section && $key) return self::$data[$section][$key];
        return self::$data[$section];
    }

    /**
     * @param $section
     * @param $key
     * @param $value
     */
    public static function set($section, $key, $value)
    {
        self::$data[$section][$key] = $value;
    }

    /**
     * @param $section
     * @param $values
     */
    public static function set_section($section, $values)
    {
        self::$data[$section] = $values;
    }

    /**
     * @param $name
     * @param $info
     */
    public static function set_database_info($name, $info)
    {
        if($name && is_array($info))
        {
            self::$data['database'][$name] = $info;
        }
    }
}
