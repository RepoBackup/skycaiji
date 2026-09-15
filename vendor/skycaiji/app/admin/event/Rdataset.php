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
use skycaiji\common\model\DatasetTable;
class Rdataset extends Release{
    protected $dataset_db=array();
    protected $dataset_fields=array();
    /**
     * 设置页面post过来的config
     * @param unknown $config
     */
    public function setConfig($config){
        $dataset=\util\UnmaxPost::val('dataset/a',array(),null);
        $dataset['dataset_id']=intval($dataset['dataset_id']);
        
        if($dataset['auto_create']){
            
            $mtask=model('Task');
            $mds=model('Dataset');
            $taskId=\util\UnmaxPost::val('task_id/d');
            $taskData=$mtask->getById($taskId);
            if(empty($taskData)){
                $this->error('任务不存在');
            }
            $dsFields=array();
            $dsName=$taskData['name'];
            do{
                
                $hasName=$mds->where('name',$dsName)->count()>0?true:false;
                if($hasName){
                    $dsName.='_1';
                }
            }while($hasName);
            
            $collFields=$this->get_coll_fields($taskData['id'],$taskData['module']);
            $md5Fields=array();
            foreach ($collFields as $collField){
                $checkResult=$mds->check_field_name($collField);
                if(!$checkResult['success']){
                    $md5Fields[md5($collField)]=$collField;
                    $collField=md5($collField);
                }
                $dsFields[$collField]=array('name'=>$collField,'type'=>'mediumtext');
            }
            $dsFields=$mds->filter_fields($dsFields);
            $dsData=array('name'=>$dsName,'desc'=>'','sort'=>0,'config'=>serialize(array('fields'=>$dsFields)));
            $dataset['dataset_id']=$mds->strict(false)->insert($dsData,false,true);
            if($dataset['dataset_id']>0){
                
                $dataset['fields']=array();
                foreach ($dsFields as $k=>$v){
                    $dataset['fields'][$k]='[采集字段:'.($md5Fields[$v['name']]?:$v['name']).']';
                }
            }
        }
        if($dataset['dataset_id']<=0){
            $this->error('请设置数据集');
        }
        $config['dataset']=$dataset;
        return $config;
    }
    /*导出数据*/
    public function export($collFieldsList,$options=null){
        $addedNum=0;
        $dsConfig=$this->config['dataset'];
        init_array($dsConfig['fields']);
        $dsId=intval($dsConfig['dataset_id']);
        $mds=model('Dataset');
        if(empty($this->dataset_fields[$dsId])){
            $dsData=$mds->getById($dsId);
            if(empty($dsData)){
                $this->echo_msg('数据集id'.$dsId.'不存在');
                return $addedNum;
            }
            $this->dataset_fields[$dsId]=$dsData['config']['fields'];
        }
        if(empty($this->dataset_db[$dsId])){
            $this->dataset_db[$dsId]=DatasetTable::getInstance($dsId)->db();
        }
        $dst=DatasetTable::getInstance($dsId);
        $dsFields=$this->dataset_fields[$dsId];
        $db=$this->dataset_db[$dsId];
        foreach ($collFieldsList as $collFieldsKey=>$collFields){
            $contTitle=$collFields['title'];
            $contContent=$collFields['content'];
            $contUrl=$collFields['url'];
            $contSourceUrl=isset($collFields['data_module_source_url'])?$collFields['data_module_source_url']:$contUrl;
            $collFields=$collFields['fields'];
            $this->init_download_config($this->task,$collFields);
            $db->startTrans();
            $returnData=array('id'=>'','target'=>'','desc'=>'','error'=>'');
            try{
                $dbData=$this->txt_replace_fields($dsConfig['fields'],$collFields);
                $returnData['id']=$dst->setData(0,$dbData,$dsFields,$contSourceUrl,false);
                if($returnData['id']>0){
                    $addedNum++;
                    $returnData['target']=sprintf('@%d:%d',$dsId,$returnData['id']);
                }else{
                    $returnData['error']='发布时数据为空';
                }
            }catch (\Exception $ex){
                $returnData['error']=$ex->getMessage();
            }
            if(empty($returnData['error'])){
                $db->commit();
            }else{
                $db->rollback();
            }
            $this->record_collected($contUrl,$returnData,$this->release,array('title'=>$contTitle,'content'=>$contContent));
            
            $this->exportEnd($returnData,$collFieldsList[$collFieldsKey]);
        }
        return $addedNum;
    }
}
?>