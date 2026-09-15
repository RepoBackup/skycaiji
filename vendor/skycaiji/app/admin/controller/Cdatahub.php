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

namespace skycaiji\admin\controller;

use skycaiji\admin\model\CacheModel;

/*采集器：数据仓库采集*/
class Cdatahub extends CollectorController {
    public function datahubAction(){
        $objid='';
        $taskId='';
        if(request()->isPost()){
            $objid=input('objid','');
            $taskId=input('task_id/d',0);
        }
        $md=model('Datahub');
        $mtask=model('Task');
        
        $taskIds=$md->allTaskIds();
        $taskNames=array();
        $taskFields=array();
        if($taskIds){
            $taskNames=$mtask->where('id','in',$taskIds)->column('name','id');
            if($taskNames){
                foreach ($taskNames as $k=>$v){
                    $taskFields[$k]=model('Collector')->datahub_fields($k,true);
                }
            }
        }
        $this->assign('taskNames',$taskNames);
        $this->assign('taskFields',$taskFields);
        $this->assign('dhTaskId',$taskId);
        $this->assign('dhObjid',$objid);
        return $this->fetch();
    }
    public function testAction(){
        $coll_id=input('coll_id/d',0);
        $collData=model('Collector')->where(array('id'=>$coll_id))->find();
        if(empty($collData)){
            $this->error(lang('coll_error_empty_coll'));
        }
        if(!in_array($collData['module'],config('allow_coll_modules'))){
            $this->error(lang('coll_error_invalid_module'));
        }
        
        $taskData=model('Task')->getById($collData['task_id']);
        model('Task')->loadConfig($taskData);
        
        $eCdatahub=new \skycaiji\admin\event\Cdatahub();
        $eCdatahub->init($collData);
        
        $dataId=input('data_id/d',0);
        if($this->request->isPost()){
            if($dataId<=0){
                $this->error('请输入'.lang('cdatahub').'中的数据ID');
            }
            
            $mdh=model('Datahub');
            $data=$mdh->getById($dataId);
            if(empty($data)){
                $this->error(lang('cdatahub').'中不存在数据ID：'.$dataId);
            }
            $dataInfo=$mdh->infoGetById($dataId);
            if(empty($dataInfo)){
                $this->error('没有数据');
            }
            
            $notInBind='';
            $datahub_tids=$eCdatahub->get_config('datahub_tids');
            if($datahub_tids){
                $notInBind=$mdh->where('id',$dataId)->where('task_id','in',$datahub_tids)->count();
                $notInBind=$notInBind<=0?('注意：ID '.$dataId.' 不是绑定'.lang('cdatahub').'中的数据'):'';
            }
            
            $contUrl=$eCdatahub->cd_cur_cont_url($data['id']);
            $val_list=$eCdatahub->getFields($contUrl,$dataInfo,$data['url']);
            $is_loop=false;
            if(empty($eCdatahub->first_loop_field)){
                
                $val_list=array($val_list);
            }else{
                $is_loop=true;
            }
            
            $md5Url=md5($contUrl?:'');
            $msg='';
            if(isset($eCdatahub->exclude_cont_urls[$md5Url])){
                if(empty($eCdatahub->first_loop_field)){
                    
                    $msg=reset($eCdatahub->exclude_cont_urls[$md5Url]);
                    $msg=$eCdatahub->exclude_url_msg($msg);
                    $this->error('中断采集 &gt; '.$msg);
                }else{
                    
                    $num=0;
                    foreach ($eCdatahub->exclude_cont_urls[$md5Url] as $k=>$v){
                        $num+=count((array)$v);
                    }
                    $msg='通过数据处理筛除了'.$num.'条数据';
                }
            }
            
            $returnData=array('val_list'=>$val_list,'is_loop'=>$is_loop,'not_in_bind'=>$notInBind);
            
            if(count($val_list)>1){
                
                $mcache=CacheModel::getInstance();
                $returnData['loop_table']=$mcache->getCache('cd_test_loop_tb_'.$collData['id'],'data');
                $returnData['loop_table']=empty($returnData['loop_table'])?null:$returnData['loop_table'];
            }
            $this->success($msg,null,$returnData);
        }else{
            $this->assign('dataId',$dataId);
            $this->assign('collData',$collData);
            
            $taskData=model('Task')->getById($collData['task_id']);
            
            $this->set_html_tags('测试抓取','测试抓取',breadcrumb(array(
                array(
                    'url' => url('collector/set?task_id=' . $taskData['id']),
                    'title' => lang('task') . lang('separator') . $taskData['name']
                ),
                array(
                    'url' =>url('cdatahub/test?coll_id=' . $coll_id),
                    'title' => '测试'
                )
            )));
            return $this->fetch();
        }
    }
    
    
    public function test_dataAction(){
        $coll_id=input('coll_id/d',0);
        $collData=model('Collector')->where(array('id'=>$coll_id))->find();
        if(empty($collData)){
            $this->error(lang('coll_error_empty_coll'));
        }
        if(!in_array($collData['module'],config('allow_coll_modules'))){
            $this->error(lang('coll_error_invalid_module'));
        }
        
        $taskData=model('Task')->getById($collData['task_id']);
        model('Task')->loadConfig($taskData);
        
        $eCdatahub=new \skycaiji\admin\event\Cdatahub();
        $eCdatahub->init($collData);
        
        $mdh=model('Datahub');
        $datahub_tids=$eCdatahub->get_config('datahub_tids');
        if($datahub_tids){
            $orderBy=$eCdatahub->get_config('datahub','sort_new');
            $orderBy=$orderBy?'id desc':'id asc';
            $list=$mdh->where('task_id','in',$datahub_tids)->order($orderBy)->paginate(10,false,paginate_auto_config());
            $pagenav=$list->render();
            $list=$list->all();
            if(empty($list)){
                $this->error('绑定的'.lang('cdatahub').'没有数据！可在其他任务的“任务设置”中开启“'.lang('cdatahub').'”功能导入数据');
            }
            foreach ($list as $k=>$v){
                $vId=$v['id'];
                $v=$mdh->infoDb()->where('id',$vId)->column('content','field_id');
                $v=\util\Funcs::implode_arr2str($v);
                $list[$k]=array('id'=>$vId,'data'=>$v);
            }
            $this->assign('list',$list);
            $this->assign('pagenav',$pagenav);
        }else{
            $this->error('请先绑定'.lang('cdatahub').'！');
        }
        $this->assign('coll_id',$coll_id);
        return $this->fetch();
    }
    
    public function add_defaultAction(){
        $dhTids=input('post.datahub_tids/a',array());
        init_array($dhTids);
        $dfields=model('Collector')->datahub_fields($dhTids);
        if($dfields){
            foreach ($dfields as $k=>$v){
                $dfields[$k]=array('name'=>$v,'module'=>'dvalue','dsource'=>'dfield:'.$v);
            }
            $this->success('','',$dfields);
        }else{
            $this->error('没有字段，请先绑定'.lang('cdatahub').'！');
        }
    }
}