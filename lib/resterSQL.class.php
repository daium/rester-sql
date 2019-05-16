<?php

/**
 * Class resterSQL
 */
class resterSQL extends rester
{
    /**
     * @var string
     */
    protected $path_proc_sql;

    /**
     * rester constructor.
     *
     * @param string $module
     * @param string $proc
     * @param array  $request_data
     *
     * @throws Exception
     */
    public function __construct($module, $proc, $request_data=[])
    {
        parent::__construct($module,$proc,'post',$request_data);
    }

    /**
     * @param string $module
     * @param string $proc
     * @param string $method
     *
     * @return bool|string
     * @throws Exception
     */
    protected function path_proc($module, $proc, $method)
    {
        $base_path = dirname(__FILE__).'/../'.self::path_module;

        // 프로시저 경로 설정
        $path_proc = false;
        $path = implode('/',array( $base_path, $module, $proc.'.php' ));
        if(is_file($path))
        {
            $path_proc = $path;
        }

        // sql 프로시저 경로 설정
        $this->path_proc_sql = false;
        $path = implode('/',array( $base_path, $module, $proc.'.sql' ));
        if(is_file($path))
        {
            $this->path_proc_sql = $path;
        }

        // 프로시저 파일 체크
        if(!$this->path_proc_sql && !$path_proc)
        {
            throw new Exception("Not found procedure. Module: {$module}, Procedure: {$proc} ", rester_response::code_not_found);
        }
        return $path_proc;
    }

    /**
     * @return bool|string
     */
    protected function path_verify()
    {
        $path = implode('/',array(
            dirname(__FILE__).'/../'.rester::path_module,
            $this->module,
            $this->proc.'.ini'
        ));

        if(is_file($path)) return $path;
        return false;
    }

    /**
     * @return bool
     */
    protected function path_verify_user_func()
    {
        $path = implode('/',array(
            dirname(__FILE__).'/../'.rester::path_module,
            $this->module,
            $this->proc.'.verify.php'
        ));

        if(is_file($path)) return $path;
    }

    /**
     * run rester
     *
     * @param rester $caller
     *
     * @return array|bool|mixed
     * @throws Exception
     */
    public function run($caller=null)
    {
        // check access level [public]
        $this->check_access_level($caller);

        // check auth
        if($this->check_auth) { session::get(cfg::token()); }

        $response_data = false;

        // get cached data
        if($this->cache_timeout)
        {
            $response_data = json_decode($this->redis->get($this->cache_key),true);
        }

        // include procedure
        if(!$response_data)
        {
            if($this->path_proc_sql)
            {
                $response_data = $this->execute_sql($this->path_proc_sql);
            }
            elseif($this->path_proc)
            {
                $response_data = include $this->path_proc;
            }

            // cached body
            if($this->cache_timeout)
            {
                $this->redis->set($this->cache_key,json_encode($response_data),$this->cache_timeout);
            }
        }
        return $response_data;
    }

    /**
     * @param string $path
     *
     * @return array
     * @throws Exception
     */
    public function execute_sql($path)
    {
        $pdo = db::get($this->cfg->database());
        $query = file_get_contents($path);

        // 필터링 된 파라미터를 받아옴
        // 영문숫자_-로 조합된 키워드 추출
        $params = [];
        preg_match_all('/:[a-zA-z0-9_-]+/', $query, $matches);
        $matches = $matches[0];

        foreach($matches as $bind_param)
        {
            foreach ($this->verify->param() as $k=>$v)
            {
                if(strpos($k,':')!==0) $k = ':'.$k;
                if($bind_param==$k) $params[$bind_param] = $v;
            }
            if(!isset($params[$bind_param]))
                throw new Exception("There is no parameter for bind. [{$bind_param}]");
        }

        $response_data = [];
        $stmt = $pdo->prepare($query,[PDO::ATTR_CURSOR, PDO::CURSOR_FWDONLY]);
        $stmt->execute($params);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($res as $row)
        {
            $response_data[] = $row;
        }

        return $response_data;
    }
}