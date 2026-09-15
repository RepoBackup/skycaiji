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

class Datahub extends BaseController {
    public function listAction(){
        $taskName=input('task_name');
        $page=input('p/d',1);
        $page=max(1,$page);
        
        $mdh=model('Datahub'); 
        $mtask=model('Task');
        
        $cond=array();
        $search=array();
        
        
        $search['id']=input('id/d',0);
        $isSearch=false;
        if($search['id']>0){
            $cond['id']=input('id/d');
            $isSearch=true;
        }else{
            unset($search['id']);
        }
        
        $search['url']=input('url','','trim');
        if(!empty($search['url'])){
            $cond['url']=$search['url'];
            $isSearch=true;
        }
        
        if(!empty($taskName)){
            $isSearch=true;
            $search['task_name']=$taskName;
            
            $searchTasks=$mtask->field('`id`,`name`')->where(array('name'=>array('like',"%{$taskName}%")))->column('name','id');
            if(!empty($searchTasks)){
                $cond['task_id']=array('in',array_keys($searchTasks));
            }
        }else{
            
            $taskId=input('task_id');
            if(!is_empty($taskId,true)){
                $cond['task_id']=$taskId;
                $taskData=$mtask->where('id',$taskId)->find();
                if(!empty($taskData)){
                    $search['task_name']=$taskData['name'];
                    $isSearch=true;
                }
            }
        }
        
        $mcache=CacheModel::getInstance();
        
        $search['num']=\util\Tools::action_search_num('datahub');
        
        $search['sort']=input('sort');
        if(!in_array($search['sort'],array('id','addtime','uptime'))){
            $search['sort']='';
        }
        
        $dataList=array();
        $limit=$search['num'];
        $dataList=$mdh->where($cond)->order(($search['sort']?$search['sort']:'id').' desc')->paginate($limit,false,paginate_auto_config());
        $pagenav=$dataList->render();
        $dataList=$dataList->all();
        
        $taskNames=array();
        $dataIds=array();
        foreach ($dataList as $k=>$v){
            $taskNames[$v['task_id']]=$v['task_id'];
            $dataIds[$v['id']]=$v['id'];
        }
        if($taskNames){
            $taskNames=model('Task')->where('id','in',$taskNames)->column('name','id');
        }
        $taskNames[0]='无';
        $dataFields=array();
        $collUrls=array();
        $collUrlNums=array();
        if($dataIds){
            
            $dataFieldsDb=$mdh->infoDb()->field('*')->where('id','in',$dataIds)->select();
            if($dataFieldsDb){
                foreach ($dataFieldsDb as $k=>$v){
                    if(!is_array($dataFields[$v['id']])){
                        $dataFields[$v['id']]=array();
                    }
                    $dataFields[$v['id']][$v['field_id']]=$v['content'];
                    unset($dataFieldsDb[$k]);
                }
            }
            if($dataFields){
                foreach ($dataFields as $k=>$v){
                    $v=\util\funcs::implode_arr2str($v);
                    $dataFields[$k]=$v;
                }
            }
            
            
            foreach ($dataIds as $v){
                $v=\util\Tools::create_skycaiji_url('datahub',$v);
                $collUrls[md5($v)]=$v;
            }
            if($collUrls){
                $collUrlNums=model('Collected')->where('urlMd5','in',array_keys($collUrls))->group('urlMd5')->column('count(urlMd5)','urlMd5');
            }
        }
        
        $navTips=$isSearch?'搜索数据':'数据列表';
        
        $this->set_html_tags(
            lang('cdatahub').':'.$navTips,
            lang('cdatahub').'：'.$navTips,
            breadcrumb(array(array('url'=>url('datahub/list'),'title'=>lang('cdatahub')),array('url'=>url('datahub/list'),'title'=>$navTips)))
        );
        
        $this->assign('search',$search);
        $this->assign('dataList',$dataList);
        $this->assign('pagenav',$pagenav);
        $this->assign('taskNames',$taskNames);
        $this->assign('dataFields',$dataFields);
        $this->assign('collUrls',$collUrls);
        $this->assign('collUrlNums',$collUrlNums);
        return $this->fetch();
    }
    
    /**
     * 操作
     */
    public function opAction(){
        $id=input('id/d',0);
        $op=input('op');
        $ops=array('item'=>array('delete'),'list'=>array('deleteall'));
        if(!in_array($op,$ops['item'])&&!in_array($op,$ops['list'])){
            
            $this->error(lang('invalid_op'));
        }
        
        $mdh=model('Datahub');
        if(in_array($op,$ops['item'])){
            
            $dhData=$mdh->getById($id);
            if(empty($dhData)){
                $this->error(lang('empty_data'));
            }
        }
        if($op=='delete'){
            
            $mdh->deleteById($id);
            $this->success(lang('delete_success'));
        }elseif($op=='deleteall'){
            
            $ids=input('ids/a',array(),'intval');
            if(is_array($ids)&&count($ids)>0){
                foreach ($ids as $id){
                    $mdh->deleteById($id);
                }
            }
            $this->success(lang('op_success'),'list');
        }
    }
    
    public function infoAction(){
        $id=input('id/d',0);
        $mdh=model('Datahub');
        
        $item=array();
        $itemInfo=array();
        if($id>0){
            $item=$mdh->where('id',$id)->find();
            if(empty($item)){
                $this->error(lang('cdatahub').'：数据'.$id.'不存在','datahub/list');
            }
            $itemInfo=$mdh->infoGetById($id);
        }
        
        if(request()->isPost()){
            $taskId=input('task_id/d',0);
            $url=input('url','','trim');
            if(!empty($url)&&!\util\Funcs::is_right_url($url)){
                $this->error('源网址格式错误！请以http://或者https://开头');
            }
            $fieldNames=input('field_names/a',array(),'trim');
            $fieldVals=input('field_values/a',array(),null);
            $fields=array();
            if($fieldNames){
                foreach ($fieldNames as $k=>$v){
                    if($v){
                        $fields[$v]=$fieldVals[$k];
                    }
                }
            }
            
            if(empty($fields)){
                $this->error('请设置字段的值');
            }
            
            if($item){
                
                $mdh->where('id',$id)->update(array(
                    'task_id'=>$taskId,
                    'url'=>$url,
                    'c_url_md5'=>md5(\util\Tools::create_skycaiji_url('datahub', $id)),
                    'uptime'=>time()
                ));
                $mdh->infoDb()->where('id',$id)->delete();
                $mdh->infoAdd($id,$taskId,$fields);
                $taskName='';
                if($taskId){
                    $taskData=model('Task')->getById($taskId);
                    if($taskData){
                        $taskName=$taskData['name'];
                    }else{
                        $taskName='id:'.$taskId;
                    }
                }
                $taskName=$taskName?$taskName:'无';
                
                $fields=$mdh->infoDb()->where('id',$id)->column('content','field_id');
                if($fields){
                    $fields=\util\Funcs::implode_arr2str($fields);
                }else{
                    $fields='';
                }
                $this->success('已修改','',array('id'=>$item['id'],'url'=>$url,'fields'=>$fields,'task'=>array('id'=>$taskId,'name'=>$taskName),'uptime'=>date('Y-m-d H:i:s')));
            }else{
                
                $result=$mdh->addData($url,$taskId,$fields);
                if(!$result['success']){
                    $this->error($result['msg']);
                }
                $this->success('已添加',$id>0?('datahub/info?id='.$id):'datahub/list');
            }
        }else{
            $tasks=model('Task')->order('sort desc,id asc')->column('name','id');
            $taskFields=array();
            $listUrl=array('url'=>url('datahub/list'),'title'=>lang('cdatahub'));
            if($item){
                
                if($tasks[$item['task_id']]){
                    $listUrl['url']=url('datahub/list?task_id='.$item['task_id']);
                    $listUrl['title']=lang('cdatahub').'：'.$tasks[$item['task_id']];
                    $taskFields=model('Collector')->datahub_fields($item['task_id']);
                    $item['_fields']=$taskFields;
                }
                $item['_info']=$itemInfo;
            }
            $this->set_html_tags(
                lang('cdatahub').'数据:'.$id,
                lang('cdatahub').'数据：ID '.$id,
                breadcrumb(array($listUrl,array('url'=>url('datahub/info?id='.$id),'title'=>'数据:'.$id)))
            );
            
            $this->assign('tasks',$tasks);
            $this->assign('item',$item);
            
            if($this->request->isAjax()){
                return $this->fetch('info_ajax');
            }else{
                return $this->fetch();
            }
        }
    }
    
    public function fieldsAction(){
        $taskId=input('task_id/d',0);
        $fields=array();
        if($taskId>0){
            $fields=model('Collector')->datahub_fields($taskId);
            if(empty($fields)){
                $this->error('没有字段');
            }else{
                $this->success('已添加','',$fields);
            }
        }else{
            $this->error('请选择任务');
        }
    }
}