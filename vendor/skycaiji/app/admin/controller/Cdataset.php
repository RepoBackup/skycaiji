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
use skycaiji\common\model\DatasetTable;

/*采集器：数据集采集*/
class Cdataset extends CollectorController {
    public function datasetAction(){
        $objid='';
        $dsId='';
        if(request()->isPost()){
            $objid=input('objid','');
            $dsId=input('ds_id/d',0);
        }
        $mds=model('Dataset');
        
        $dsNames=array();
        $dsFields=array();
        $datasetList=$mds->order('sort desc')->column('*','id');
        init_array($datasetList);
        foreach ($datasetList as $k=>$v){
            $dsNames[$k]=$v['name'];
            $dsFields[$k]=$mds->getFieldNames($v,false,true);
        }
        $this->assign('dsNames',$dsNames);
        $this->assign('dsFields',$dsFields);
        $this->assign('dsId',$dsId);
        $this->assign('dsObjid',$objid);
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
        
        $eCdataset=new \skycaiji\admin\event\Cdataset();
        $eCdataset->init($collData);
        $dataId=input('data_id/d',0);
        if($this->request->isPost()){
            if($dataId<=0){
                $this->error('请输入'.lang('cdataset').'中的数据ID');
            }
            $mds=model('Dataset');
            $diData=$mds->indexDb()->where('id',$dataId)->find();
            if(empty($diData)){
                $this->error(lang('cdataset').'中不存在数据ID：'.$dataId);
            }
            
            $dataInfo=$mds->field_names_vals($diData['ds_id'],$diData['dt_id']);
            if(!$dataInfo['success']){
                $this->error($dataInfo['msg']);
            }
            $dataInfo=$dataInfo['data'];
            
            
            $notInBind='';
            $dataset_ids=$eCdataset->get_config('dataset_ids');
            if($dataset_ids){
                $notInBind=$mds->indexDb()->where('id',$dataId)->where('ds_id','in',$dataset_ids)->count();
                $notInBind=$notInBind<=0?('注意：ID '.$dataId.' 不是绑定'.lang('cdataset').'中的数据'):'';
            }
            
            
            $contUrl=$eCdataset->cd_cur_cont_url($diData['ds_id'],$diData['dt_id']);
            $val_list=$eCdataset->getFields($contUrl,$dataInfo,$diData['url']);
            $is_loop=false;
            if(empty($eCdataset->first_loop_field)){
                
                $val_list=array($val_list);
            }else{
                $is_loop=true;
            }
            
            $md5Url=md5($contUrl?:'');
            $msg='';
            if(isset($eCdataset->exclude_cont_urls[$md5Url])){
                if(empty($eCdataset->first_loop_field)){
                    
                    $msg=reset($eCdataset->exclude_cont_urls[$md5Url]);
                    $msg=$eCdataset->exclude_url_msg($msg);
                    $this->error('中断采集 &gt; '.$msg);
                }else{
                    
                    $num=0;
                    foreach ($eCdataset->exclude_cont_urls[$md5Url] as $k=>$v){
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
                    'url' =>url('cdataset/test?coll_id=' . $coll_id),
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
        
        $eCdataset=new \skycaiji\admin\event\Cdataset();
        $eCdataset->init($collData);
        
        $mds=model('Dataset');
        $dataset_ids=$eCdataset->get_config('dataset_ids');
        if($dataset_ids){
            $orderBy=$eCdataset->get_config('dataset','sort_new');
            $orderBy=$orderBy?'id desc':'id asc';
            $list=$mds->indexDb()->where('ds_id','in',$dataset_ids)->order($orderBy)->paginate(10,false,paginate_auto_config());
            $pagenav=$list->render();
            $list=$list->all();
            if(empty($list)){
                $this->error('绑定的数据集没有数据！可在其他任务的“发布设置”中使用“数据集”功能导入数据');
            }
            foreach ($list as $k=>$v){
                if(is_array($v)&&$v){
                    $list[$k]=array('id'=>$v['id'],'ds_id'=>$v['ds_id'],'dt_id'=>$v['dt_id'],'data'=>'');
                    $dst=DatasetTable::getInstance($v['ds_id']);
                    $dtData=$dst->db()->where('id',$v['dt_id'])->find();
                    $v=$dtData?\util\Funcs::implode_arr2str($dtData):'';
                    $list[$k]['data']=$v;
                }else{
                    unset($list[$k]);
                }
            }
            
            $this->assign('list',$list);
            $this->assign('pagenav',$pagenav);
        }else{
            $this->error('请先绑定数据集！');
        }
        $this->assign('coll_id',$coll_id);
        return $this->fetch();
    }
    
    public function add_defaultAction(){
        $dsIds=input('post.dataset_ids/a',array());
        init_array($dsIds);
        $dfields=model('Dataset')->getFieldsByIds($dsIds);
        if($dfields){
            foreach ($dfields as $k=>$v){
                $dfields[$k]=array('name'=>$v,'module'=>'dvalue','dsource'=>'dfield:'.$v);
            }
            $this->success('','',$dfields);
        }else{
            $this->error('没有字段，请先绑定'.lang('cdataset').'！');
        }
    }
}