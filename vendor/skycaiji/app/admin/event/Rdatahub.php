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
class Rdatahub extends Release{
    /**
     * 设置页面post过来的config
     * @param unknown $config
     */
    public function setConfig($config){
        return $config;
    }
    /*导出数据*/
    public function export($collFieldsList,$options=null){
        $addedNum=0;
        
        $mdh=model('Datahub');
        foreach ($collFieldsList as $collFieldsKey=>$collFields){
            $contTitle=$collFields['title'];
            $contContent=$collFields['content'];
            $contUrl=$collFields['url'];
            $contSourceUrl=isset($collFields['data_module_source_url'])?$collFields['data_module_source_url']:$contUrl;
            $collFields=$collFields['fields'];
            $this->init_download_config($this->task,$collFields);
            foreach ($collFields as $k=>$v){
                $v=$this->get_field_val($v);
                $collFields[$k]=$v;
            }
            
            $returnData=array('id'=>'','target'=>'','desc'=>'','error'=>'');
            try{
                $result=$mdh->addData($contSourceUrl,$this->task['id'],$collFields);
                if($result['success']){
                    $returnData['id']=$result['id'];
                }else{
                    $returnData['error']=$result['msg'];
                }
                if($returnData['id']>0){
                    $addedNum++;
                    $returnData['target']=sprintf('@%d',$returnData['id']);
                }
            }catch (\Exception $ex){
                $returnData['error']=$ex->getMessage();
            }
            
            $this->record_collected($contUrl,$returnData,$this->release,array('title'=>$contTitle,'content'=>$contContent));
            
            $this->exportEnd($returnData,$collFieldsList[$collFieldsKey],true);
        }
        
        return $addedNum;
    }
}
?>