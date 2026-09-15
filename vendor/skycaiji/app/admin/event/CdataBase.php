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
class CdataBase extends CollectCommon{
    
    public function __destruct(){
        $this->destruct_clear();
    }
    
    
    public function cd_cur_cont_url($str1,$str2=''){
        $module='';
        if($this->cur_c_module['datahub']){
            $module='datahub';
        }elseif($this->cur_c_module['dataset']){
            $module='dataset';
        }
        $url=\util\Tools::create_skycaiji_url($module,$str1,$str2);
        return $url;
    }
    
    
    public function field_module_durl(){
        
        return $this->cur_cont_source_url;
    }
    
    
    public function cd_init_process($process){
        if(is_array($process)){
            if($process['module']=='tool'){
                if(is_array($process['tool_list'])){
                    foreach ($process['tool_list'] as $k=>$v){
                        if(in_array($v, array('url_not_complete','url_real'))){
                            
                            unset($process['tool_list'][$k]);
                        }
                    }
                    $process['tool_list']=array_values($process['tool_list']);
                }
            }elseif($process['module']=='download'){
                
                unset($process['download_op']);
            }
        }
        return $process;
    }
    
    
    
    public function initConfig($config){
        if($this->cur_c_module['datahub']){
            init_array($config['datahub_tids']);
        }elseif($this->cur_c_module['dataset']){
            init_array($config['dataset_ids']);
        }elseif($this->cur_c_module['localfile']){
            init_array($config['localfiles']);
        }
        
        $config['charset']='auto';
        $config['url_complete']=0;
        $config['url_no_name']=0;
        $config['regexp_flags']=array('unicode');
        $config['reg_regexp_flags']='iu';
        
        if(is_array($config['field_process'])){
            foreach ($config['field_process'] as $k=>$v){
                if(is_array($v)){
                    foreach ($v as $vk=>$vv){
                        $v[$vk]=$this->cd_init_process($vv);
                    }
                }
                $config['field_process'][$k]=$v;
            }
        }
        
        if(is_array($config['common_process'])){
            foreach ($config['common_process'] as $k=>$v){
                $config['common_process'][$k]=$this->cd_init_process($v);
            }
        }
        
        $config=parent::initConfig($config);
        return $config;
    }
}