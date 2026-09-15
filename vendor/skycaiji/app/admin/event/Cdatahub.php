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

class Cdatahub extends CdataBase{
    public $cur_c_module=array('datahub'=>true);
    /*处理post配置*/
    public function setConfig($config){
        init_array($config['datahub_tids']);
        $config['datahub_tids']=array_values($config['datahub_tids']);
        init_array($config['datahub']);
        $config['datahub']['sort_new']=intval($config['datahub']['sort_new']);
        $config['datahub']['delete']=intval($config['datahub']['delete']);
        $config=$this->set_config_common($config);
        return $config;
    }
    
    
    public function collect($num=10){
        parent::collect($num);
        $dhTids=$this->config['datahub_tids'];
        if(empty($dhTids)){
            $this->echo_error('没有绑定'.lang('cdatahub'));
        }
        
        $orderBy=$this->get_config('datahub','sort_new');
        $orderBy=$orderBy?'dh.id desc':'dh.id asc';
        
        $condDhTid=array();
        if(count($dhTids)>1){
            $condDhTid['dh.task_id']=array('in',$dhTids);
        }else{
            $dhTids=array_values($dhTids);
            $condDhTid['dh.task_id']=$dhTids[0];
        }
        
        $queryColl='select 1 from `'.model('Collected')->get_table_name().'` c where c.urlMd5=dh.c_url_md5';
        if(g_sc_c('caiji','same_url')){
            
            $queryColl.=' and c.task_id='.intval($this->task_id);
        }
        
        $mdh=model('Datahub');
        $dhList=$mdh->db()->field('dh.id as dhid,dh.url as dhurl,dh.c_url_md5')->alias('dh')->where($condDhTid)->whereNotExists($queryColl)->order($orderBy)->limit($num)->select();
        
        if(empty($dhList)){
            $this->echo_error(lang('cdatahub').'没有可用的数据');
        }else{
            $mcollected=model('Collected');
            foreach ($dhList as $dhData){
                $cont_source_url=$dhData['dhurl'];
                $dhid=$dhData['dhid'];
                $cont_url=$this->cd_cur_cont_url($dhid);
                $skycaiji2url=\util\Tools::skycaiji2url($cont_url);
                $md5_cont_url=md5($cont_url);
                if(empty($dhData['c_url_md5'])){
                    
                    $mdh->db()->where('id',$dhid)->update(array('c_url_md5'=>$md5_cont_url));
                    $this->echo_msg(array('数据错误：<a href="%s" target="_blank">%s</a>',$skycaiji2url,$cont_url));
                    continue;
                }
                if(array_key_exists($md5_cont_url,$this->used_cont_urls)){
                    
                    continue;
                }
                if($mcollected->collGetNumByUrl($cont_url,null,$this->task_id,g_sc_c('caiji','same_url'))<=0){
                    
                    if(!empty($this->collected_field_list)){
                        
                        $millisecond=g_sc_c('caiji','interval_html');
                        if($millisecond>0){
                            $this->collect_sleep($this->task_id,$millisecond,true,true);
                            
                            if($mcollected->collGetNumByUrl($cont_url,null,$this->task_id,g_sc_c('caiji','same_url'))>0){
                                $this->echo_msg(array('已采集过%s：<a href="%s" target="_blank">%s</a>',lang('cdatahub'),$skycaiji2url,$cont_url),'black');
                                $this->used_cont_urls[$md5_cont_url]=1;
                                continue;
                            }
                        }
                    }
                    if(\skycaiji\admin\model\Collector::cont_url_exists($md5_cont_url)){
                        
                        $this->used_cont_urls[$md5_cont_url]=1;
                        $this->echo_msg(array('其他任务正在采集%s：<a href="%s" target="_blank">%s</a>',lang('cdatahub'),$skycaiji2url,$cont_url),'black');
                        continue;
                    }
                    
                    \skycaiji\admin\model\Collector::cont_url_collect($md5_cont_url);
                    
                    $this->echo_msg(array('采集%s：<a href="%s" target="_blank">%s</a>',lang('cdatahub'),$skycaiji2url,$cont_url),'black');
                    
                    $cont_data=$mdh->infoGetById($dhid);
                    $field_vals_list=$this->getFields($cont_url,$cont_data,$cont_source_url);
                    
                    
                    $this->collect_stopped($this->task_id);
                    
                    $this->collect_fields_vals('', $cont_url, $md5_cont_url, $field_vals_list, false);
                }else{
                    
                    $this->echo_msg(array('已采集过%s：<a href="%s" target="_blank">%s</a>',lang('cdatahub'),$skycaiji2url,$cont_url),'black');
                }
            } 
        }
        return 'completed';
    }
    
    /*获取字段列表，这里是入口*/
    public function getFields($cont_url,$cont_data=array(),$cont_source_url=''){
        $this->field_val_list=array();
        $this->first_loop_field=null;
        $this->cur_cont_url=$cont_url;
        $this->cur_cont_source_url=$cont_source_url;
        
        if(!empty($cont_url)&&!preg_match('/^\w+\:\/\//',$cont_url)){
            return $this->echo_error(htmlspecialchars($cont_url).'网址不完整');
        }
        if(empty($this->config['new_field_list'])){
            return $this->echo_error('未设置字段');
        }
        
        foreach($this->config['new_field_list'] as $field_config){
            $dfield_val='';
            if($field_config['field']&&$field_config['field']['dsource']){
                $dfield_val=$cont_data[str_replace('dfield:','',$field_config['field']['dsource'])];
            }
            $this->setField($field_config,$cont_url,$dfield_val,$cont_url);
        }
        
        return $this->get_field_vals();
    }
    
    /*设置字段值*/
    public function setField($field_config,$cur_url,$dfield_val,$cont_url){
        $field_name=$field_config['field']['name'];
        if(!isset($this->field_val_list[$field_name])){
            
            $this->field_val_list[$field_name]=array('values'=>array(),'imgs'=>array(),'files'=>array());
        }
        
        $this->set_field_val($field_config, $cur_url, $dfield_val, $cont_url, null);
    }
    
    
    
    public function collectSingle($singleConfig){
        $this->set_single_collecting();
        $dataId=input('data_id/d',0);
        if($dataId<=0){
            $this->echo_error('请输入'.lang('cdatahub').'中的数据ID');
        }
        $mdh=model('Datahub');
        $data=$mdh->getById($dataId);
        if(empty($data)){
            $this->echo_error(lang('cdatahub').'中不存在数据ID：'.$dataId);
        }
        $dataInfo=$mdh->infoGetById($dataId);
        if(empty($dataInfo)){
            $this->echo_error('ID '.$dataId.' 没有数据');
        }
        
        $contUrl=$this->cd_cur_cont_url($data['id']);
        
        $mcollected=model('Collected');
        $isCollected=$mcollected->collGetNumByUrl($contUrl,null,$this->task_id,g_sc_c('caiji','same_url'))>0?true:false;
        if($singleConfig['always']||!$isCollected){
            
            $field_vals_list=$this->getFields($contUrl,$dataInfo,$data['url']);
            if(!$isCollected){
                
                $this->collect_fields_vals('', $contUrl, md5($contUrl), $field_vals_list, false);
            }else{
                if(empty($this->first_loop_field)){
                    
                    $field_vals_list=array($field_vals_list);
                }
            }
        }else{
            
            $this->echo_msg(array('已采集过%s：'.\util\Tools::skycaiji2url($contUrl,true),lang('cdatahub')),'black');
        }
        
        return array('data'=>$field_vals_list,'collected'=>$this->collected_field_list);
        
    }
}