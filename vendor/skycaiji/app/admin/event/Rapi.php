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
use skycaiji\admin\model\CacheModel;
class Rapi extends Release{
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
	    $api=\util\UnmaxPost::val('api/a',array());
		$api['url']=trim($api['url'],'\/\\');
		$api['cache_time']=intval($api['cache_time']);
		$api['hide_fields']=is_array($api['hide_fields'])?$api['hide_fields']:array();
		if(empty($api['url'])){
			$this->error('请输入api地址');
		}
		if(!preg_match('/^[a-zA-Z0-9\-\_]+$/i', $api['url'])){
			$this->error('api地址只能由字母、数字、下划线组成');
		}
		$config['api']=$api;
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
		if(!is_array($collFieldsList)){
			$collFieldsList=array();
		}
		
		$contUrls=array();
		foreach ($collFieldsList as $v){
			$contUrls[]=md5($v['url']);
		}
		if(!empty($contUrls)){
			$mcacheCont=CacheModel::getInstance('cont_url');
			$mcacheCont->deleteCache($contUrls);
		}
		
		
		foreach ($collFieldsList as $collFieldsKey=>$collFields){
		    $this->hide_coll_fields($this->config['api']['hide_fields'],$collFields);
		    $collFieldsList[$collFieldsKey]=$collFields;
		}
		
		$this->set_cache_fields($collFieldsList);
		
		$this->json_exit($collFieldsList);
	}

	/*设置缓存数据*/
	public function set_cache_fields($collFieldsList){
		if(!empty($this->config['api']['cache_time'])){
			
			$mcache=CacheModel::getInstance('task_api');
			if($mcache->expire($this->release['task_id'],$this->config['api']['cache_time']*60)){
				
				$mcache->setCache($this->release['task_id'], $collFieldsList);
			}
		}
	}
	/*获取缓存数据*/
	public function get_cache_fields(){
		if(!empty($this->config['api']['cache_time'])){
			
			$mcache=CacheModel::getInstance('task_api');
			if(!$mcache->expire($this->release['task_id'],$this->config['api']['cache_time']*60)){
				
				$data=$mcache->getCache($this->release['task_id'],'data');
				return empty($data)?array():$data;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	
	/*输出json数据*/
	public function json_exit($collFieldsList){
	    if(\util\Param::is_task_api_response()){
	        
	        json($collFieldsList)->send();
	    }else{
	        $html='<form id="win_form_preview" method="post" target="_blank" action="'.url('tool/preview_data').'">'.html_usertoken()
	           .'<p>生成API返回的数据 <a href="javascript:;" onclick="document.getElementById(\'win_form_preview\').submit();">预览</a></p>'
	           .'<textarea name="data" style="width:100%;margin:5px 0;" rows="20">'.htmlspecialchars(json_encode($collFieldsList)).'</textarea></form>';
	        $this->echo_msg($html,'black');
	        $this->echo_msg_end();
	    }
	    exit();
	}
}
?>