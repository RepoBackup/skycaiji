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

/*采集器通用操作*/
class CollectorController extends BaseController {
    public $cur_c_module=array();
    
    protected function _initialize(){
        parent::_initialize();
        
        $coll=request()->controller();
        $coll=strtolower($coll);
        
        set_g_sc('coll_collector', $coll);
        $coll=substr($coll,1);
        $this->cur_c_module[$coll]=true;
        set_g_sc('coll_module', $coll);
    }
    
    
    public function fieldAction(){
        if(request()->isPost()&&input('is_submit')){
            $objid=input('post.objid');
            $field=input('post.field/a',array(),'trim');
            if(empty($field['name'])){
                $this->error('请输入字段名称');
            }
            $this->_check_name($field['name'],'字段名称');
            
            $field['module']=strtolower($field['module']);
            
            if(empty($field['module'])){
                $this->error('请选择获取方式');
            }
            
            if($this->cur_c_module['datahub']||$this->cur_c_module['dataset']){
                if(in_array($field['module'], array('dvalue','rule','xpath','json'))){
                    
                    if(empty($field['dsource'])){
                        $this->error('请选择数据来源');
                    }
                }
            }
            
            
            switch ($field['module']){
                case 'rule':if(empty($field['rule']))$this->error('规则不能为空！');break;
                case 'auto':if(empty($field['auto']))$this->error('请选择自动获取的类型');break;
                case 'xpath':if(empty($field['xpath']))$this->error('XPath规则不能为空！');break;
                case 'json':if(empty($field['json']))$this->error('提取规则不能为空！');break;
                case 'num':
                    $field['num_start']=intval($field['num_start']);
                    $field['num_end']=intval($field['num_end']);
                    $field['num_end'] = max ( $field['num_start'], $field ['num_end'] );
                    break;
                case 'no':
                    $field['no_start']=intval($field['no_start']);
                    $field['no_inc']=intval($field['no_inc']);
                    $field['no_len']=intval($field['no_len']);
                    break;
                    
                case 'list':if(empty($field['list']))$this->error('列表数据不能为空！');break;
                case 'extract':if(empty($field['extract']))$this->error('请选择字段！');break;
                
                case 'sign':if(empty($field['sign']))$this->error('请输入'.lang('field_module_sign'));break;
            }
            
            
            $modules=array();
            if($this->cur_c_module['pattern']){
                $modules=array(
                    'auto'=>'auto',
                    'variable'=>'variable',
                    'sign'=>'sign'
                );
            }elseif($this->cur_c_module['datahub']||$this->cur_c_module['dataset']){
                $modules=array('dsource'=>'dsource');
            }
            $modules['rule']=array('rule','rule_multi','rule_multi_type','rule_multi_str','rule_merge');
            $modules['xpath']=array('xpath','xpath_multi','xpath_multi_type','xpath_multi_str','xpath_attr','xpath_attr_custom');
            $modules['json']=array('json','json_merge_data','json_arr','json_arr_implode','json_loop');
            $modules['words']='words';
            $modules['num']=array('num_start','num_end');
            $modules['no']=array('no_start','no_inc','no_len');
            $modules['time']=array ('time_format','time_start','time_end','time_stamp');
            $modules['list']=array('list','list_type');
            $modules['extract']=array('extract','extract_module','extract_rule','extract_rule_merge','extract_rule_multi','extract_rule_multi_type','extract_rule_multi_str','extract_xpath','extract_xpath_attr','extract_xpath_attr_custom','extract_xpath_multi','extract_xpath_multi_type','extract_xpath_multi_str','extract_json','extract_json_merge_data','extract_json_arr','extract_json_arr_implode','extract_json_loop');
            $modules['merge']='merge';
            
            $returnField=array('name'=>$field['name'],'desc'=>$field['desc'],'module'=>$field['module']);
            
            if($this->cur_c_module['pattern']){
                $returnField['source']=$field['source'];
            }elseif($this->cur_c_module['datahub']||$this->cur_c_module['dataset']){
                $returnField['dsource']=$field['dsource'];
            }
            
            
            if(is_array($modules[$field['module']])){
                foreach($modules[$field['module']] as $mparam){
                    $returnField[$mparam]=$field[$mparam];
                }
            }else{
                $returnField[$modules[$field['module']]]=$field[$modules[$field['module']]];
            }
            $this->success('',null,array('field'=>$returnField,'objid'=>$objid));
        }else{
            $field=input('field','','url_b64decode');
            $objid=input('objid');
            $field=$field?json_decode($field,true):array();
            if(!is_array($field)){
                $field=array();
            }
            $field['time_format']=$field['time_format']?$field['time_format']:'[年]/[月]/[日] [时]:[分]';
            $field['num_start']=isset($field['num_start'])?intval($field['num_start']):1;
            $field['num_end']=isset($field['num_end'])?intval($field['num_end']):100;
            
            
            $sortField=array();
            foreach(array('source','dsource','module') as $k){
                if(isset($field[$k])){
                    $sortField[$k]=$field[$k];
                    unset($field[$k]);
                }
            }
            
            foreach ($field as $k=>$v){
                $sortField[$k]=$v;
            }
            $field=$sortField;
            
            $this->assign('field',$field);
            $this->assign('objid',$objid);
            
            
            $mtask=model('Task');
            if($this->cur_c_module['datahub']||$this->cur_c_module['dataset']){
                $dfields=array();
                if($this->cur_c_module['datahub']){
                    $dhTids=input('post.datahub_tids/a',array());
                    $dfields=model('Collector')->datahub_fields($dhTids);
                }elseif($this->cur_c_module['dataset']){
                    $dsIds=input('post.dataset_ids/a',array());
                    $dfields=model('Dataset')->getFieldsByIds($dsIds);
                }
                $this->assign('dfields',$dfields);
            }
            
            return $this->fetch('collector:c_field');
        }
    }
    
    public function clone_fieldAction(){
        if(request()->isPost()){
            $field=input('field','','url_b64decode');
            $field=$field?json_decode($field,true):array();
            $process=input('process','','url_b64decode');
            $process=$process?json_decode($process,true):'';
            
            $this->success('',null,array('field'=>$field,'process'=>$process));
        }else{
            $this->error('复制失败');
        }
    }
    
    public function _check_name($name,$nameStr=''){
        if(!preg_match('/^[\x{4e00}-\x{9fa5}\w\-]+$/u', $name)){
            $this->error(($nameStr?$nameStr:'名称').'只能由汉字、字母、数字和下划线组成');
            return false;
        }elseif(mb_strlen($name,'utf-8')>50){
            $this->error(($nameStr?$nameStr:'名称').'长度50字以内');
            return false;
        }else{
            return true;
        }
    }
    
    
    public function reset_field_noAction(){
        if(request()->isPost()){
            $taskId=input('task_id/d',0);
            $fieldName=input('field_name','');
            $ckey='taskFNo_'.$taskId.'_'.$fieldName;
            CacheModel::getInstance()->deleteCache($ckey);
            $this->success('已重置');
        }else{
            $this->error('操作失败');
        }
    }
    
    
    
    public function processAction(){
        $type=input('type');
        
        $this->assign('type',$type);
        $op=input('op');
        
        $taskId=input('task_id/d',0);
        
        $downImgUrl='';
        $downFileUrl='';
        if(is_empty(g_sc_c('download_img','download_img'))){
            $downImgUrl=url('setting/download_img');
        }
        if(is_empty(g_sc_c('download_file','download_file'))){
            $downFileUrl=url('setting/download_file');
        }
        
        $transUrl='';
        if(is_empty(g_sc_c('translate','open'))){
            $transUrl=url('setting/translate');
        }
        
        $transApiLangs=\util\Translator::get_api_langs(g_sc_c('translate','api'));
        init_array($transApiLangs);
        $this->assign('transApiLangs',$transApiLangs);
        
        
        if($taskId>0){
            $taskData=model('Task')->getById($taskId);
            model('Task')->loadConfig($taskData);
            
            if(is_empty(g_sc_c('download_img','download_img'))){
                if(!empty($taskData['config']['download_img'])){
                    $downImgUrl=url('task/set?id='.$taskId);
                }
            }else{
                $downImgUrl='';
            }
            if(is_empty(g_sc_c('download_file','download_file'))){
                if(!empty($taskData['config']['download_file'])){
                    $downFileUrl=url('task/set?id='.$taskId);
                }
            }else{
                $downFileUrl='';
            }
            
            if(is_empty(g_sc_c('translate','open'))){
                if(!empty($taskData['config']['translate'])){
                    $transUrl=url('task/set?id='.$taskId);
                }
            }else{
                $transUrl='';
            }
        }
        
        $this->assign('downImgUrl',$downImgUrl);
        $this->assign('downFileUrl',$downFileUrl);
        $this->assign('transUrl',$transUrl);
        
        if(empty($type)){
            
            if(empty($op)){
                $field=input('field','');
                $objid=input('objid');
                $process=input('process','','url_b64decode');
                $process=$process?json_decode($process,true):'';
                $this->assign('field',$field);
                $this->assign('objid',$objid);
                $this->assign('process',$process);
                return $this->fetch('collector:c_process');
            }elseif($op=='sub'){
                
                $process=trim_input_process('process/a');
                if(empty($process)){
                    $process='';
                }else{
                    $process=controller('admin/CollectCommon','event')->set_process($process);
                }
                $objid=input('objid','');
                $this->success('',null,array('process'=>$process,'objid'=>$objid));
            }
        }elseif('common'==$type){
            
            if(empty($op)){
                return $this->fetch('collector:c_process');
            }elseif($op=='load'){
                
                $process=trim_input_process('process/a');
                $this->assign('process',$process);
                return $this->fetch('collector:c_process_load');
            }
        }
    }
    
    
    
    public function clone_processAction(){
        $op=input('op','');
        if(empty($op)||$op=='copy'){
            
            if(request()->isPost()){
                
                $process=trim_input_process('process/a');
                if(is_array($process)){
                    
                    $process=reset($process);
                }else{
                    $process=array();
                }
                
                $msg='';
                if($op=='copy'){
                    
                    cache('collector_clone_process_data',$process);
                    $msg='已拷贝，可在任意数据处理中粘贴';
                }else{
                    $msg='已复制';
                }
                
                $this->success($msg,null,$process);
            }else{
                $this->error('无效的操作');
            }
        }elseif($op=='paste'){
            
            
            $process=cache('collector_clone_process_data');
            
            if(!empty($process)){
                $this->success('已粘贴',null,$process);
            }else{
                $this->error('请先拷贝一个处理内容');
            }
        }else{
            $this->error('无效的操作');
        }
    }
    
    
    public function element_replace_fieldAction(){
        if($this->request->isPost()){
            $vals=input('vals','','url_b64decode');
            $names=input('names','','url_b64decode');
            $vals=$vals?json_decode($vals,true):array();
            $names=$names?json_decode($names,true):array();
            init_array($vals);
            init_array($names);
            
            $originalName=input('originalName','','trim');
            $newName=input('newName','','trim');
            
            $fmtOriginalName='[字段:'.$originalName.']';
            $fmtNewName='[字段:'.$newName.']';
            
            $updated=array();
            
            foreach ($vals as $k=>$v){
                $eleName=$names[$k];
                if(empty($eleName)&&is_numeric($eleName)){
                    continue;
                }
                if($eleName=='config[field_list][]'||$eleName=='config[field_process][]'){
                    try{
                        $vDecode=url_b64decode($v);
                        if($vDecode){
                            $vDecode=json_decode($vDecode,true);
                            if(!empty($vDecode)&&is_array($vDecode)){
                                
                                if($eleName=='config[field_list][]'){
                                    
                                    if($vDecode['module']=='extract'||$vDecode['module']=='merge'){
                                        if($vDecode['extract']&&$vDecode['extract']==$originalName){
                                            $vDecode['extract']=$newName;
                                        }
                                        if($vDecode['merge']){
                                            $vDecode['merge']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vDecode['merge']);
                                        }
                                        $updated[$k]=true;
                                    }
                                }elseif($eleName=='config[field_process][]'){
                                    
                                    $isUpdated=false;
                                    foreach ($vDecode as $vk=>$vv){
                                        if($vv&&is_array($vv)){
                                            if($vv['module']=='insert'){
                                                $isUpdated=true;
                                                $vv['insert_txt']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vv['insert_txt']);
                                            }elseif($vv['module']=='if'){
                                                $isUpdated=true;
                                                if($vv['if_val']&&is_array($vv['if_val'])){
                                                    foreach ($vv['if_val'] as $vvk=>$vvv){
                                                        $vv['if_val'][$vvk]=$this->_replace_str($fmtOriginalName, $fmtNewName, $vvv);
                                                    }
                                                }
                                            }elseif($vv['module']=='api'){
                                                $isUpdated=true;
                                                if($vv['api_url']){
                                                    $vv['api_url']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vv['api_url']);
                                                }
                                                if($vv['api_params']&&is_array($vv['api_params'])){
                                                    if($vv['api_params']['addon']&&is_array($vv['api_params']['addon'])){
                                                        foreach ($vv['api_params']['addon'] as $vvk=>$vvv){
                                                            $vv['api_params']['addon'][$vvk]=$this->_replace_str($fmtOriginalName, $fmtNewName, $vvv);
                                                        }
                                                    }
                                                }
                                                if($vv['api_headers']&&is_array($vv['api_headers'])){
                                                    if($vv['api_headers']['addon']&&is_array($vv['api_headers']['addon'])){
                                                        foreach ($vv['api_headers']['addon'] as $vvk=>$vvv){
                                                            $vv['api_headers']['addon'][$vvk]=$this->_replace_str($fmtOriginalName, $fmtNewName, $vvv);
                                                        }
                                                    }
                                                }
                                            }elseif($vv['module']=='apiapp'){
                                                $isUpdated=true;
                                                if($vv['apiapp_config']&&is_array($vv['apiapp_config'])){
                                                    foreach ($vv['apiapp_config'] as $vvk=>$vvv){
                                                        $vv['apiapp_config'][$vvk]=$this->_replace_str($fmtOriginalName, $fmtNewName, $vvv);
                                                    }
                                                }
                                            }elseif($vv['module']=='func'){
                                                $isUpdated=true;
                                                $vv['func_param']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vv['func_param']);
                                            }
                                            $vDecode[$vk]=$vv;
                                        }
                                    }
                                    if($isUpdated){
                                        $updated[$k]=true;
                                    }
                                }
                                
                                
                                $v=url_b64encode(json_encode($vDecode));
                                $vals[$k]=$v;
                            }
                        }
                    }catch (\Exception $ex){
                        
                    }
                }
            }
            
            $this->success('已同步修改','',array('vals'=>$vals,'updated'=>$updated));
        }
        $this->error('同步修改失败');
    }
    
    
    public function _replace_str($from,$to,$str){
        if($str&&is_string($str)&&!is_numeric($str)){
            $str=str_replace($from, $to, $str);
        }
        return $str;
    }
    
    
    public function test_loop_tableAction(){
        $collId=input('coll_id/d',0);
        $op=input('op');
        
        $cname=($this->cur_c_module['pattern']?'cp':'cd').'_test_loop_tb_'.$collId;
        
        $mcache=CacheModel::getInstance();
        $data=$mcache->getCache($cname,'data');
        if(empty($data)&&!is_array($data)){
            $data=array();
        }
        
        $field=input('field','');
        $width=input('width/d',0);
        $data[$field]=array('width'=>$width);
        
        $mcache->setCache($cname,$data);
        $this->success();
    }
}