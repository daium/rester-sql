<?php if(!defined('__RESTER__')) exit;

use rester\sql\cfg;
use rester\sql\rester;
use rester\sql\rester_response;

/**
 * 사용중인 rester instance
 */
$current_rester = null;

/**
 * return analyzed parameter
 *
 * @param null|string $key
 * @return bool|mixed
 */
function request_param($key=null)
{
    global $current_rester;
    return $current_rester->request_param($key);
}

/**
 * @param string $module
 * @param string $proc
 * @param array  $query
 *
 * @return mixed
 */
function request_module($module, $proc, $query=[])
{
    global $current_rester;
    $old_rester = $current_rester;
    $res = false;

    try
    {
        $current_rester = new rester($module, $proc, $query);
        $res = $current_rester->run($old_rester);
    }
    catch (Exception $e)
    {
        rester_response::error($e->getMessage());
    }

    $current_rester = $old_rester;
    return $res;
}

/**
 * @param string $proc
 * @param array  $query
 *
 * @return mixed
 */
function request_procedure($proc, $query=[])
{
    global $current_rester;
    $old_rester = $current_rester;
    $res = false;

    try
    {
        $current_rester = new rester($current_rester->module(), $proc, $query);
        $res = $current_rester->run($old_rester);
    }
    catch (Exception $e)
    {
        rester_response::error($e->getMessage());
    }

    $current_rester = $old_rester;
    return $res;
}

/**
 * @param string $name
 * @param string $module
 * @param string $proc
 * @param array  $param
 *
 * @return bool|array
 */
function request($name, $module, $proc, $param=[])
{
    try
    {
        $cfg = cfg::request($name);
        if(!$cfg || !$cfg[cfg::request_host] || !$cfg[cfg::request_port]) throw new Exception("There is no config.({$name})");
        if(!$module) throw new Exception("\$module is a required input.");
        if(!$proc) throw new Exception("\$proc is a required input.");
        $url = implode('/',array( $cfg['host'].':'.$cfg['port'], 'v1', $module, $proc ));

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($param),
        ));

        $response_body = curl_exec($ch);
        curl_close($ch);
        return json_decode($response_body,true);
    }
    catch (Exception $e)
    {
        rester_response::error($e->getMessage());
        return false;
    }
}

function gen_secret_key()
{

}

/**
 * @param string $module
 * @param string $proc
 * @param array $query
 * @return bool|string
 */
function url_module($module, $proc, $query=[])
{
    if(!$module || !$proc) return false;
    $http_host = cfg::Get('default','http_host');
    $_query = [];
    foreach ($query as $k=>$v) { $_query[] = $k.'='.$v; }
    $_query = trim(implode('&',$_query));
    $_query = $_query?'?'.$_query:'';
    return  $http_host."/v1/{$module}/{$proc}{$_query}";
}

/**
 * @param string $proc
 * @param array $query
 * @return string|bool
 */
function url_proc($proc, $query=[])
{
    if(!$proc) return false;
    $http_host = cfg::Get('default','http_host');
    $module = cfg::module();
    $_query = [];
    foreach ($query as $k=>$v) { $_query[] = $k.'='.$v; }
    $_query = trim(implode('&',$_query));
    $_query = $_query?'?'.$_query:'';
    return  $http_host."/v1/{$module}/{$proc}{$_query}";
}
