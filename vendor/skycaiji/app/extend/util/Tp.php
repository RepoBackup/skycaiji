<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */


namespace util;
class Tp{
    
    public static function filter_log_msg(&$logMsg){
        static $msgList=array();
        static $passList=array(
            '未定义','Undefined array key','Undefined variable','Undefined index',
            'A session had already been started','DOMDocument::loadHTML',
            'MySQL server has gone away',"Error reading result set's header",
            'The /e modifier is deprecated',
            'Invalid argument supplied for foreach',
            'CURLOPT_FOLLOWLOCATION',
            'open_basedir restriction in effect',
            ']unlink(',']rmdir(',
            '[exception_exit_collect]',
            'Passing null to parameter',
            'Using null as an array offset is deprecated'
        );
        static $passListLower=null;
        if(!isset($passListLower)){
            $passListLower=$passList;
            if(IS_CLI){
                $passListLower[]='session_start()';
            }
            $passListLower=array_map('strtolower', $passListLower);
        }
        if($logMsg){
            $msg=$logMsg;
            $msgKey=md5($msg);
            if(isset($msgList[$msgKey])){
                
                if($msgList[$msgKey]){
                    $msg='';
                }
            }else{
                $msgList[$msgKey]=false;
                $msg=strtolower($msg);
                foreach ($passListLower as $passStr){
                    
                    if($passStr&&strpos($msg, $passStr)!==false){
                        
                        $msg='';
                        $msgList[$msgKey]=true;
                        break;
                    }
                }
            }
            
            if($msg){
                
                if(g_sc('coll_execute_func_error')){
                    
                    $taskName=g_sc('collect_task_name');
                    $logMsg=g_sc('coll_execute_func_error').$logMsg;
                    if($taskName){
                        $logMsg='【任务：'.$taskName.'】'.$logMsg;
                    }
                }
            }
        }
        return $msg?false:true;
    }
}
?>