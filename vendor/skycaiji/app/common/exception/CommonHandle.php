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

namespace skycaiji\common\exception;

class CommonHandle extends \think\exception\Handle {
    
    public function renderForConsole(\think\console\Output $output, \Exception $e)
    {
        $this->_set_exception($e);
        if(\util\Param::is_collector_collecting()&&IS_CLI){
            
            $this->_collect_output($e);
        }else{
            parent::renderForConsole($output,$e);
        }
    }
    
    protected function renderHttpException(\think\exception\HttpException $e){
        if(\util\Param::is_collector_collecting()){
            
            $this->_collect_output($e);
        }else{
            return parent::renderHttpException($e);
        }
    }
    protected function convertExceptionToResponse(\Exception $e){
        $this->_set_exception($e);
        if(\util\Param::is_collector_collecting()){
            
            $this->_collect_output($e);
        }else{
            return parent::convertExceptionToResponse($e);
        }
    }
    private function _set_exception(\Exception &$e){
        if($e){
            $msg=$e->getMessage();
            if($msg){
                if(g_sc('coll_execute_func_error')){
                    
                    $e=new \Exception(g_sc('coll_execute_func_error').$msg,$e->getCode(),$e);
                }elseif(strpos($msg,'SQLSTATE[HY000] [2054]')!==false){
                    
                    if(version_compare(constant('PHP_VERSION'),'7.4','<')){
                        $e=new \Exception('请将PHP切换至7.4以上版本才能使用MySql8及更高版本数据库',$e->getCode(),$e);
                    }
                }
            }
        }
    }
    private function _collect_output($exception){
        $msg=$exception?strip_tags($exception->getMessage()):'';
        if(strpos($msg,'[exception_exit_collect]')===false){
            
            \util\Tools::collect_output($msg);
        }
    }
}
?>