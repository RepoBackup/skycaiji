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

namespace skycaiji\admin\event; 
abstract class Release extends ReleaseBase{
	public $release;
	public $config;
	public $task;
	public $collector;
	/*发布时初始化*/
	public function init($release){
	    $release['config']=model('Release')->compatible_config($release['config']);
		$this->release=$release;
		$this->config=$release['config'];
		$this->task=model('Task')->cacheById($this->release['task_id'],true);
		$this->collector=model('Collector')->cacheByTaskData($this->task,true);
		if(empty($this->task)){
			$this->error(lang('task_error_empty_task'));
		}
	}
	public function doExport($collFieldsList,$options=null){
	    $addedNum=0;
	    try{
	        $addedNum=$this->export($collFieldsList,$options);
	    }catch (\Exception $ex){
	        
	        $this->echo_msg(array('%s',$ex->getMessage()));
	    }
	    return $addedNum;
	}
	/**
	 * 优化设置页面post过来的config
	 * @param unknown $config 页面配置
	 */
	public abstract function setConfig($config);
	/**
	 * 导出数据
	 * @param unknown $collFieldsList 采集到的字段数据列表
	 * @param unknown $options 选项
	 */
	public abstract function export($collFieldsList,$options=null);
	
	protected function exportEnd($releResult,&$collFields,$noDatahub=false){
	    if(!$noDatahub&&g_sc('task_datahub','open')){
	        
	        if($releResult['id']>0){
	            
    	        if($collFields&&is_array($collFields['fields'])){
    	            $mdh=model('Datahub');
    	            foreach ($collFields['fields'] as $k=>$v){
    	                $v=$this->get_field_val($v);
    	                $collFields['fields'][$k]=$v;
    	            }
    	            $contSourceUrl=isset($collFields['data_module_source_url'])?$collFields['data_module_source_url']:$collFields['url'];
    	            $result=$mdh->addData($contSourceUrl,$this->task['id'],$collFields['fields']);
    	            if($result['success']){
    	                $this->echo_msg('已将内容存入'.lang('cdatahub').'：<a href="'.url('datahub/info?id='.$result['id']).'" target="_blank">ID:'.$result['id'].'</a>','green');
    	            }
    	        }
	        }
	    }
	    if($releResult['id']>0){
	        
	        if($this->task['module']=='datahub'||$this->task['module']=='dataset'){
	            
	            if($this->collector&&$this->collector['config']&&$this->collector['config'][$this->task['module']]&&$this->collector['config'][$this->task['module']]['delete']){
	                
	                $parseUrl=\util\Tools::parse_skycaiji_url($collFields['url']);
	                if($parseUrl['module']){
	                    
	                    if($parseUrl['module']=='datahub'){
	                        model('Datahub')->deleteById($parseUrl['param1']);
	                    }elseif($parseUrl['module']=='dataset'){
	                        model('Dataset')->deleteById(0,$parseUrl['param1'],$parseUrl['param2']);
	                    }
	                    $this->echo_msg('已执行发布后删除内容','black');
	                }
	            }
	        }
	    }
	    
	    if($collFields){
	        unset($collFields['fields']);
	    }
	}
}
?>