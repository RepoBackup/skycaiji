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

namespace skycaiji\common\model;

class Dataset extends BaseModel{
    
    public function indexDb(){
        return db('dataset_index');
    }
    public function getById($id){
        $data=$this->where('id',$id)->find();
        $data=$this->convert_data($data);
        return $data;
    }
    public function getByIds($ids){
        init_array($ids);
        $list=array();
        foreach ($ids as $id){
            $data=$this->getById($id);
            if($data){
                $list[$id]=$data;
            }
        }
        return $list;
    }
    
    public function deleteById($diId,$dsId=0,$dtId=0){
        $diId=intval($diId);
        $dsId=intval($dsId);
        $dtId=intval($dtId);
        $diData=array();
        if($diId>0){
            
            $diData=$this->indexDb()->where('id',$diId)->find();
            if($diData){
                $dsId=$diData['ds_id'];
                $dtId=$diData['dt_id'];
            }else{
                $dsId=0;
                $dtId=0;
            }
        }elseif($dsId&&$dtId){
            
            $diData=$this->indexDb()->where('dt_id',$dtId)->where('ds_id',$dsId)->find();
            $diId=$diData?$diData['id']:0;
        }
        if($dsId&&$dtId){
            
            DatasetTable::getInstance($dsId)->db()->where('id',$dtId)->delete();
        }
        if($diId){
            $this->indexDb()->where('id',$diId)->delete();
        }
    }
    
    public function getFieldNames($data,$implodeStr=false,$arrValues=false){
        if(is_object($data)||is_array($data)){
            $data=$this->convert_data($data);
        }else{
            $data=$this->getById($data);
        }
        $fields=array();
        if($data['config']&&$data['config']['fields']){
            foreach ($data['config']['fields'] as $k=>$v){
                $fields[$k]=$v['name'];
            }
        }
        if($arrValues){
            
            $fields=array_values($fields);
        }
        if($implodeStr){
            $fields=implode($implodeStr,$fields);
        }
        return $fields;
    }
    
    public function getFieldsByIds($dsIds){
        init_array($dsIds);
        $fields=array();
        foreach ($dsIds as $dsId){
            $fields1=$this->getFieldNames($dsId);
            init_array($fields1);
            if($fields1){
                $fields=array_merge($fields,$fields1);
            }
        }
        if($fields){
            $fields=array_unique($fields);
            $fields=array_values($fields);
        }
        return $fields;
    }
    
    public function convert_data($dsData){
        if(is_object($dsData)){
            $dsData=$dsData->toArray();
            init_array($dsData);
        }
        if($dsData&&is_array($dsData)){
            if(!is_array($dsData['config'])){
                $dsData['config']=safe_unserialize($dsData['config']);
                init_array($dsData['config']);
            }
        }else{
            $dsData=array();
        }
        return $dsData;
    }
    
    public function get_config_field($dsData,$fkey,$getName=false){
        $field=array();
        $dsData=$this->convert_data($dsData);
        if($dsData['config']&&$dsData['config']['fields']){
            $field=$dsData['config']['fields'][$fkey];
        }
        init_array($field);
        return $getName?$field['name']:$field;
    }
    
    
    public function field_names_vals($dsId,$dtId,$dsData=null){
        $result=return_result('');
        if(empty($dsData)||$dsData['id']!=$dsId){
            
            $dsData=$this->getById($dsId);
            if(empty($dsData)){
                $result['msg']='数据集'.$dsId.'不存在';
                return $result;
            }
        }
        $dsData=$this->convert_data($dsData);
        $mdt=DatasetTable::getInstance($dsId);
        $dtData=$mdt->db()->where('id',$dtId)->find();
        if(empty($dtData)){
            $result['msg']='数据集数据'.$dtId.'不存在';
            return $result;
        }
        unset($dtData['id']);
        foreach ($dtData as $k=>$v){
            unset($dtData[$k]);
            $k=$this->get_config_field($dsData,$k,true);
            $dtData[$k]=$v;
        }
        $result['success']=true;
        $result['data']=$dtData;
        return $result;
    }
    
    
    public function check_field_name($name){
        $result=return_result('');
        if(empty($name)){
            $result['msg']='字段名称不能为空！';
        }elseif(strcasecmp($name,'id')===0){
            $result['msg']='字段名称不能设为id';
        }elseif(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
            $result['msg']='字段名称只能由汉字、字母、数字和下划线组成';
        }elseif(strlen($name)<=1){
            $result['msg']='字段名称最少2个字符';
        }else{
            $result['success']=true;
        }
        return $result;
    }
    
    public function check_field_type($type){
        $result=return_result('');
        static $types=array('bigint','double','varchar','mediumtext','datetime');
        if(!in_array($type, $types)){
            $result['msg']='请选择数据类型';
        }else{
            $result['success']=true;
        }
        return $result;
    }
    
    public function filter_fields($fields){
        init_array($fields);
        $newFields=array();
        foreach ($fields as $v){
            init_array($v);
            $check=$this->check_field_name($v['name']);
            if($check['success']){
                $check=$this->check_field_type($v['type']);
                if($check['success']){
                    $key=$this->field_db_name($v['name']);
                    $newFields[$key]=$v;
                }
            }
        }
        return $newFields;
    }
    
    public static function field_db_name($name){
        $name=strtolower($name);
        if(!preg_match('/^[a-z]\w{0,30}$/i',$name)){
            
            $md5=md5($name);
            $name='';
            if(preg_match('/[a-z]/i',$md5,$mfirst)){
                $name=$mfirst[0];
            }
            $name=$name?:'a';
            $name.=substr($md5,-4,4);
        }
        $name=strtolower($name);
        return $name;
    }
}
?>