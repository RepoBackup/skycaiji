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
class Clocalfile extends CdataBase{
    public $cur_c_module=array('localfile'=>true);
    /*处理post配置*/
    public function setConfig($config){
        init_array($config['localfiles']);
        $config['localfiles']=array_unique($config['localfiles']);
        $config['localfiles']=array_values($config['localfiles']);
        $config=$this->set_config_common($config);
        return $config;
    }
    
    
    public function collect($num=10){
        parent::collect($num);
        
        return 'completed';
    }
    
    /*获取字段列表，这里是入口*/
    public function getFields($cont_url,$cont_data=array(),$cont_source_url=''){
        
    }
    
    /*设置字段值*/
    public function setField($field_config,$cur_url,$dfield_val,$cont_url){
        $field_name=$field_config['field']['name'];
        if(!isset($this->field_val_list[$field_name])){
            
            $this->field_val_list[$field_name]=array('values'=>array(),'imgs'=>array(),'files'=>array());
        }
        
        $this->set_field_val($field_config, $cur_url, $dfield_val, $cont_url, null);
    }
}