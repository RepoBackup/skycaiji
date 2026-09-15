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
class Cdataset extends CdataBase{
    public $cur_c_module=array('dataset'=>true);
    /*处理post配置*/
    public function setConfig($config){
        init_array($config['dataset_ids']);
        $config['dataset_ids']=array_values($config['dataset_ids']);
        init_array($config['dataset']);
        $config['dataset']['sort_new']=intval($config['dataset']['sort_new']);
        $config['dataset']['delete']=intval($config['dataset']['delete']);
        $config=$this->set_config_common($config);
        return $config;
    }
    
    
    public function collect($num=10){
        parent::collect($num);
        $dsIds=$this->get_config('dataset_ids');
        if(empty($dsIds)){
            $this->echo_error('没有绑定数据集');
        }
        
        $orderBy=$this->get_config('dataset','sort_new');
        $orderBy=$orderBy?'di.id desc':'di.id asc';
        
        $mds=model('Dataset');
        $dsDatas=$mds->getByIds($dsIds);
        if(empty($dsDatas)){
            $this->echo_error('绑定的数据集不存在');
        }
        $dsIds=array_keys($dsDatas);
        
        $condDsId=array();
        if(count($dsIds)>1){
            $condDsId['di.ds_id']=array('in',$dsIds);
        }else{
            $condDsId['di.ds_id']=$dsIds[0];
        }
        
        $queryColl='select 1 from `'.model('Collected')->get_table_name().'` c where c.urlMd5=di.c_url_md5';
        if(g_sc_c('caiji','same_url')){
            
            $queryColl.=' and c.task_id='.intval($this->task_id);
        }
        
        $diList=$mds->indexDb()->field('di.*')->alias('di')->where($condDsId)->whereNotExists($queryColl)->order($orderBy)->limit($num)->select();
        
        if(empty($diList)){
            $this->echo_error(lang('cdataset').'没有可用的数据');
        }else{
            $mcollected=model('Collected');
            foreach ($diList as $diData){
                $cont_url=$this->cd_cur_cont_url($diData['ds_id'],$diData['dt_id']);
                $skycaiji2url=\util\Tools::skycaiji2url($cont_url);
                $md5_cont_url=md5($cont_url);
                if(empty($diData['c_url_md5'])){
                    
                    $mds->indexDb()->where('id',$diData['id'])->update(array('c_url_md5'=>$md5_cont_url));
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
                                $this->echo_msg(array('已采集过%s：<a href="%s" target="_blank">%s</a>',lang('cdataset'),$skycaiji2url,$cont_url),'black');
                                $this->used_cont_urls[$md5_cont_url]=1;
                                continue;
                            }
                        }
                    }
                    if(\skycaiji\admin\model\Collector::cont_url_exists($md5_cont_url)){
                        
                        $this->used_cont_urls[$md5_cont_url]=1;
                        $this->echo_msg(array('其他任务正在采集%s：<a href="%s" target="_blank">%s</a>',lang('cdataset'),$skycaiji2url,$cont_url),'black');
                        continue;
                    }
                    
                    \skycaiji\admin\model\Collector::cont_url_collect($md5_cont_url);
                
                    $dataInfo=$mds->field_names_vals($diData['ds_id'],$diData['dt_id'],$dsDatas[$diData['ds_id']]);
                    if(!$dataInfo['success']){
                        $this->echo_msg($dataInfo['msg']);
                        continue;
                    }
                    
                    $this->echo_msg(array('采集%s：<a href="%s" target="_blank">%s</a>',lang('cdataset'),$skycaiji2url,$cont_url),'black');
                    
                    $field_vals_list=$this->getFields($cont_url,$dataInfo['data'],$diData['url']);
                    
                    
                    $this->collect_stopped($this->task_id);
                    
                    $this->collect_fields_vals('', $cont_url, $md5_cont_url, $field_vals_list, false);
                }else{
                    
                    $this->echo_msg(array('已采集过%s：<a href="%s" target="_blank">%s</a>',lang('cdataset'),$skycaiji2url,$cont_url),'black');
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
            $this->echo_error('请输入'.lang('cdataset').'中的数据ID');
        }
        $mds=model('Dataset');
        $diData=$mds->indexDb()->where('id',$dataId)->find();
        if(empty($diData)){
            $this->echo_error(lang('cdataset').'中不存在数据ID：'.$dataId);
        }
        $dataset_ids=$this->get_config('dataset_ids');
        if($dataset_ids&&!in_array($diData['ds_id'], $dataset_ids)){
            $this->echo_error('ID '.$dataId.' 不是绑定'.lang('cdataset').'中的数据');
        }
        $contUrl=$this->cd_cur_cont_url($diData['ds_id'],$diData['dt_id']);
        
        $mcollected=model('Collected');
        $isCollected=$mcollected->collGetNumByUrl($contUrl,null,$this->task_id,g_sc_c('caiji','same_url'))>0?true:false;
        if($singleConfig['always']||!$isCollected){
            
            $dataInfo=$mds->field_names_vals($diData['ds_id'],$diData['dt_id']);
            if(!$dataInfo['success']){
                $this->echo_error($dataInfo['msg']);
            }
            $dataInfo=$dataInfo['data'];
            $field_vals_list=$this->getFields($contUrl,$dataInfo,$diData['url']);
            if(!$isCollected){
                
                $this->collect_fields_vals('', $contUrl, md5($contUrl), $field_vals_list, false);
            }else{
                if(empty($this->first_loop_field)){
                    
                    $field_vals_list=array($field_vals_list);
                }
            }
        }else{
            
            $this->echo_msg(array('已采集过%s：'.\util\Tools::skycaiji2url($contUrl,true),lang('cdataset')),'black');
        }
        
        return array('data'=>$field_vals_list,'collected'=>$this->collected_field_list);
    }
}