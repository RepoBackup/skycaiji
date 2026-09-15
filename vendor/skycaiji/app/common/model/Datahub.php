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
class Datahub extends BaseModel {
    public function fieldDb(){
        return db('datahub_field');
    }
    public function infoDb(){
        return db('datahub_info');
    }
    public function deleteById($id){
        $id=intval($id);
        if($id>0){
            $this->where('id',$id)->delete();
            $this->infoDb()->where('id',$id)->delete();
        }
    }
    
    public function fieldGetIds($fieldNames,$insert=false){
        init_array($fieldNames);
        $fieldIds=array();
        if($fieldNames){
            foreach ($fieldNames as $k=>$v){
                unset($fieldNames[$k]);
                $fieldNames[md5($v)]=$v;
            }
            $fieldIds=$this->fieldDb()->where('name_md5','in',array_keys($fieldNames))->column('id','name');
            if($insert){
                foreach ($fieldNames as $k=>$v){
                    if(!isset($fieldIds[$v])){
                        $dhFid=$this->fieldDb()->insert(array('name'=>$v,'name_md5'=>$k),false,true);
                        if($dhFid>0){
                            $fieldIds[$v]=$dhFid;
                        }
                    }
                }
            }
        }
        return $fieldIds;
    }
    
    public function infoGetById($id){
        $fields=$this->infoDb()->where('id',$id)->column('content','field_id');
        if($fields){
            $fnames=$this->fieldDb()->where('id','in',array_keys($fields))->column('name','id');
            foreach ($fields as $k=>$v){
                unset($fields[$k]);
                $fields[$fnames[$k]]=$v;
            }
        }
        init_array($fields);
        return $fields;
    }
    
    public function infoAdd($id,$taskId,$fields){
        init_array($fields);
        if($fields){
            
            $dhFields=$this->fieldGetIds(array_keys($fields),true);
            
            $addFields=array();
            foreach ($fields as $k=>$v){
                $addFields[]=array(
                    'id'=>$id,
                    'task_id'=>$taskId,
                    'field_id'=>$dhFields[$k]?:0,
                    'content'=>$v
                );
            }
            $this->infoDb()->insertAll($addFields);
        }
    }
    
    public function addData($url,$taskId,$fields){
        $result=return_result('',false,array('id'=>0));
        $taskId=intval($taskId);
        
        $addData=array(
            'task_id' => $taskId,
            'url' => $url,
            'c_url_md5' => '',
            'addtime' => time(),
            'uptime' => 0
        );
        $dataId=$this->db()->insert($addData, false, true);
        if ($dataId > 0) {
            
            $this->db()->where('id',$dataId)->update(array('c_url_md5'=>md5(\util\Tools::create_skycaiji_url('datahub',$dataId))));
            $this->infoAdd($dataId, $taskId, $fields);
            $result['success']=true;
            $result['id']=$dataId;
        }else{
            $result['msg']='添加失败';
        }
        return $result;
    }
    
    
    public function allTaskIds(){
        $taskIds=$this->cache('datahubAllTaskIds',20)->field('task_id')->group('task_id')->column('task_id');
        return $taskIds;
    }
}
?>