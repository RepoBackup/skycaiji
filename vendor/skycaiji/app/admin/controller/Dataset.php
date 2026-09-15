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

use skycaiji\common\model\DatasetTable;
use skycaiji\admin\model\CacheModel;

class Dataset extends BaseController {
    public function indexAction(){
        $page=input('p/d',1);
        $page=max(1,$page);
        
        $mds=model('Dataset');
        
        $cond=array();
        $search=array(
            'id'=>input('id/d',0),
            'num'=>input('num/d'),
            'name'=>input('name',''),
            'url'=>input('url','','trim'),
            'sort'=>input('sort','')
        );
        if(!in_array($search['sort'],array('id','addtime','uptime'))){
            $search['sort']='';
        }
        
        $mcache=CacheModel::getInstance();
        $search['num']=\util\Tools::action_search_num('dataset');
        
        $isSearch=false;
        $cond=array();
        if($search['id']>0){
            $cond['id']=$search['id'];
            $isSearch=true;
        }else{
            unset($search['id']);
        }
        if($search['name']){
            $cond['ds_id']=$mds->where('name','like','%'.$search['name'].'%')->column('id');
            $cond['ds_id']=$cond['ds_id']?array('in',$cond['ds_id']):0;
            $isSearch=true;
        }
        
        $dsId=input('ds_id/d',0);
        if($dsId>0){
            $dsData=$mds->where('id',$dsId)->find();
            if($dsData){
                $cond['ds_id']=$dsData['id'];
                $search['name']=$dsData['name'];
                $isSearch=true;
            }
        }
        
        if($search['url']){
            $cond['url']=$search['url'];
            $isSearch=true;
        }
        
        $diList=$mds->indexDb()->where($cond)->order(($search['sort']?:'id').' desc')->paginate($search['num'],false,paginate_auto_config());
        $pagenav=$diList->render();
        $diList=$diList->all();
        
        $dsNames=array();
        $dsDatas=array();
        
        $collUrls=array();
        $collUrlNums=array();
        
        foreach ($diList as $v){
            $dsNames[$v['ds_id']]=$v['ds_id'];
            $dsDatas[$v['ds_id']][$v['dt_id']]=$v['dt_id'];
        }
        if($dsNames){
            $dsNames=$mds->where('id','in',$dsNames)->column('name','id');
        }
        if($dsDatas){
            foreach ($dsDatas as $dsId=>$dtIds){
                $mdt=DatasetTable::getInstance($dsId);
                if($dtIds){
                    $dtDatas=$mdt->db()->where('id','in',$dtIds)->column('*','id');
                    foreach ($dtDatas as $k=>$v){
                        $collUrl=\util\Tools::create_skycaiji_url('dataset',$dsId,$v['id']);
                        $collUrls[md5($collUrl)]=$collUrl;
                        $v=\util\Funcs::implode_arr2str($v);
                        $dtDatas[$k]=$v;
                    }
                    $dsDatas[$dsId]=$dtDatas;
                }
            }
        }
        
        
        if($collUrls){
            $collUrlNums=model('Collected')->where('urlMd5','in',array_keys($collUrls))->group('urlMd5')->column('count(urlMd5)','urlMd5');
        }
        
        
        $this->assign('search',$search);
        $this->assign('diList',$diList);
        $this->assign('dsNames',$dsNames);
        $this->assign('dsDatas',$dsDatas);
        $this->assign('pagenav',$pagenav);
        $this->assign('collUrls',$collUrls);
        $this->assign('collUrlNums',$collUrlNums);
        
        $navTips=$isSearch?'搜索数据':'数据列表';
        $this->set_html_tags(
            '数据集:'.$navTips,
            '数据集：'.$navTips,
            breadcrumb(array(array('url'=>url('dataset/list'),'title'=>'数据集'),array('url'=>url('dataset/index'),'title'=>$navTips)))
        );
        
        return $this->fetch();
    }
    
    public function listAction(){
        $page=input('p/d',1);
        $page=max(1,$page);
        
        $mds=model('Dataset');
        
        
        $search=array(
            'id'=>input('id/d',0),
            'name'=>input('name','','trim')
        );
        if($search['id']<=0){
            unset($search['id']);
        }
        
        $cond=array();
        if($search['id']){
            $cond['id']=$search['id'];
        }
        if($search['name']){
            $cond['name']=array('like','%'.$search['name'].'%');
        }
        
        $limit=50;
        $dsList=$mds->where($cond)->order('sort desc')->paginate($limit,false,paginate_auto_config());
        $pagenav=$dsList->render();
        $dsList=$dsList->all();
        
        $dsFields=array();
        if($dsList){
            foreach ($dsList as $v){
                $dsFields[$v['id']]=$mds->getFieldNames($v,', ');
            }
        }
        
        $this->assign('search',$search);
        $this->assign('dsList',$dsList);
        $this->assign('dsFields',$dsFields);
        $this->assign('pagenav',$pagenav);
        
        $this->set_html_tags(
            '数据集',
            '数据集',
            breadcrumb(array(array('url'=>url('dataset/list'),'title'=>'数据集'),array('url'=>url('dataset/list'),'title'=>'列表')))
        );
        
        return $this->fetch();
    }
    
    
    public function selectAction(){
        $page=input('p/d',1);
        $page=max(1,$page);
        
        $search=array(
            'id'=>input('id/d',0),
            'name'=>input('name','','trim')
        );
        if($search['id']<=0){
            unset($search['id']);
        }
        
        $mds=model('Dataset');
        $cond=array();
        if($search['id']){
            $cond['id']=$search['id'];
        }
        if($search['name']){
            $cond['name']=array('like','%'.$search['name'].'%');
        }
        
        $dsList=array();
        $limit=20;
        $dsList=$mds->where($cond)->order('sort desc')->paginate($limit,false,paginate_auto_config());
        $pagenav=$dsList->render();
        $dsList=$dsList->all();
        
        $this->assign('search',$search);
        $this->assign('dsList',$dsList);
        $this->assign('pagenav',$pagenav);
        $this->assign('from',input('from',''));
        return $this->fetch();
    }
    public function opAction(){
        $op=input('op','');
        $mds=model('Dataset');
        if($this->request->isPost()){
            if(empty($op)){
                
                $newsort=input('newsort/a',array(),'intval');
                foreach ($newsort as $id=>$sort){
                    $mds->where('id',$id)->update(array('sort'=>$sort));
                }
                $this->success('操作成功','dataset/list');
            }elseif($op=='delete'){
                $this->ajax_check_userpwd();
                $id=input('id/d',0);
                $mds->where('id',$id)->delete();
                DatasetTable::getInstance($id)->drop_table();
                $this->success('已删除','');
            }
        }else{
            $this->error('无效操作','');
        }
    }
    
    public function op_indexAction(){
        $op=input('op','');
        $mds=model('Dataset');
        if($this->request->isPost()){
            if($op=='delete'){
                $id=input('id/d',0);
                $mds->deleteById($id);
                $this->success('已删除','');
            }elseif($op=='deleteall'){
                $ids=input('ids/a',array());
                if($ids){
                    foreach ($ids as $id){
                        $mds->deleteById($id);
                    }
                }
                $this->success('已删除','');
            }
            
        }else{
            $this->error('无效操作','');
        }
    }
    
    public function dbCountAction(){
        $counts=array('db'=>array(),'da'=>array());
        if($this->request->isPost()){
            $ids=input('ids/a',array(),'intval');
            init_array($ids);
            $mda=model('Dataapi');
            foreach ($ids as $id){
                try{
                    $dst=DatasetTable::getInstance($id);
                    $counts['db'][$id]=$dst->db()->count();
                    $counts['da'][$id]=$mda->where('ds_id',$id)->count();
                }catch(\Exception $ex){}
            }
        }
        $this->success('','',$counts);
    }
    public function setAction(){
        $id=input('id/d');
        $dsData=array();
        $mds=model('Dataset');
        if($id){
            $dsData=$mds->getById($id);
        }
        if($this->request->isPost()){
            $newData=array(
                'name'=>input('name'),
                'desc'=>input('desc'),
                'sort'=>input('sort/d',0)
            );
            if(empty($newData['name'])){
                $this->error('请输入名称');
            }
            if(mb_strlen($newData['name'])>50){
                $this->error('名称长度不能超过50个字符');
            }
            
            $checkName=true;
            if(!empty($dsData)&&$newData['name']==$dsData['name']){
                
                $checkName=false;
            }
            if($checkName){
                if($mds->where('name',$newData['name'])->count()>0){
                    $this->error('名称已存在');
                }
            }
            
            $fields=input('fields/a',array(),'url_b64decode');
            if($fields){
                foreach ($fields as $k=>$v){
                    $fields[$k]=json_decode($v,true);
                }
                $fields=$mds->filter_fields($fields);
            }
            init_array($fields);
            if(empty($fields)){
                $this->error('请添加字段');
            }
            $newData['config']=array(
                'fields'=>$fields
            );
            $newData['config']=serialize($newData['config']);
            if(empty($dsData)){
                
                $id=$mds->strict(false)->insert($newData,false,true);
            }else{
                
                
                $dsFields=$dsData['config']['fields'];
                init_array($dsFields);
                $newFields=$fields;
                init_array($newFields);
                $upFields=false;
                foreach ($newFields as $k=>$v){
                    $dsField=$dsFields[$k];
                    if(empty($dsField)){
                        
                        $upFields=true;
                        break;
                    }
                    if($v['name']!=$dsField['name']||$v['type']!=$dsField['type']||$v['len']!=$dsField['len']){
                        
                        $upFields=true;
                        break;
                    }
                }
                foreach ($dsFields as $k=>$v){
                    if(empty($newFields[$k])){
                        
                        $upFields=true;
                        break;
                    }
                }
                if($upFields){
                    
                    $this->ajax_check_userpwd();
                }
                $mds->strict(false)->where(array('id'=>$id))->update($newData);
            }
            $dsTable=DatasetTable::getInstance($id);
            $dsTable->db();
            
            $error='';
            try{
                $dsTable->alertTableFields($fields,$dsData);
            }catch(\Exception $ex){
                $error=$ex->getMessage();
                $error=$dsTable->convertErrorColumn($error,$fields);
            }
            
            
            $dsData=$mds->getById($id);
            if($dsData){
                $fields=$dsData['config']['fields'];
                init_array($fields);
                if($fields){
                    $dbColumns=$dsTable->dbColumns();
                    foreach ($fields as $fk=>$fv){
                        if(empty($dbColumns[$fk])){
                            
                            $fv=null;
                        }else{
                            
                            $dbType=$dbColumns[$fk]['type'];
                            $dbLen='';
                            if(preg_match('/^(.+)\((\d+)\)\s*$/',$dbType,$mtype)){
                                $dbType=trim($mtype[1]);
                                $dbLen=intval($mtype[2]);
                            }
                            $dbType=strtolower($dbType);
                            $checkType=$mds->check_field_type($dbType);
                            if(!$checkType['success']){
                                
                                $fv=null;
                            }else{
                                $fv['len']='';
                                if($dbType!=$fv['type']){
                                    
                                    $fv['type']=$dbType;
                                }elseif($dbType=='varchar'){
                                    
                                    $fv['len']=$dbLen;
                                }
                            }
                        }
                        if(is_null($fv)){
                            unset($fields[$fk]);
                        }else{
                            unset($fv['name_original']);
                            unset($fv['name_dbname']);
                            $fields[$fk]=$fv;
                        }
                    }
                    $dsData['config']['fields']=$fields;
                    $dsData['config']=serialize($dsData['config']);
                    $mds->strict(false)->where(array('id'=>$id))->update(array('config'=>$dsData['config']));
                }
            }
            
            if($error){
                $this->error($error,'');
            }else{
                $this->success('操作成功','dataset/set?id='.$id);
            }
        }else{
            $title=$dsData?'编辑':'添加';
            $this->set_html_tags(
                $title.'数据集'.($dsData?(':'.$dsData['name']):''),
                $title.'数据集'.($dsData?('：'.$dsData['name'].'（id:'.$id.'）'):''),
                breadcrumb(array(array('url'=>url('dataset/list'),'title'=>'数据集'),array('url'=>url('dataset/set?id='.($dsData?$dsData['id']:'')),'title'=>($dsData?$dsData['name']:$title))))
            );
            $indexes=array();
            if($dsData){
                $dsData['name']=htmlspecialchars_decode($dsData['name'],ENT_QUOTES);
                $dsData['desc']=htmlspecialchars_decode($dsData['desc'],ENT_QUOTES);
                
                $fields=$dsData['config']['fields'];
                init_array($fields);
                foreach ($fields as $k=>$v){
                    $v['name_dbname']=$mds->field_db_name($v['name']);
                    $fields[$k]=$v;
                }
                $dsData['config']['fields']=$fields;
                
                $dsTable=DatasetTable::getInstance($id);
                $dbIndexes=db()->query('SHOW INDEX FROM `'.$dsTable->fullTableName().'`');
                foreach ($dbIndexes as $dbIndex){
                    $dbIndex=\util\Funcs::array_keys_to_lower($dbIndex);
                    $dbIxKey=$dbIndex['key_name'];
                    if($dbIxKey=='PRIMARY'){
                        
                        continue;
                    }
                    if(!isset($indexes[$dbIxKey])){
                        $indexes[$dbIxKey]=array('fields'=>array());
                        if(empty($dbIndex['non_unique'])){
                            $indexes[$dbIxKey]['type']='唯一索引';
                        }else{
                            $indexes[$dbIxKey]['type']=strtolower($dbIndex['index_type'])=='fulltext'?'全文索引':'普通索引';
                        }
                    }
                    $indexes[$dbIxKey]['fields'][$dbIndex['column_name']]=($fields[$dbIndex['column_name']]?$fields[$dbIndex['column_name']]['name']:$dbIndex['column_name']).($dbIndex['sub_part']?('('.$dbIndex['sub_part'].')'):'');
                }
            }
            $this->assign('indexes',$indexes);
            $this->assign('dsData',$dsData);
            return $this->fetch();
        }
    }
    public function fieldAction(){
        if($this->request->isPost()&&input('is_submit')){
            $objid=input('objid','');
            $field=array(
                'name'=>input('name'),
                'desc'=>input('desc','',null),
                'type'=>input('type'),
                'len'=>input('len/d',0),
                'name_original'=>input('name_original'),
            );
            $field['len']=max(0,$field['len']);
            $mds=model('Dataset');
            $result=$mds->check_field_name($field['name']);
            if(!$result['success']){
                $this->error($result['msg']);
            }
            $result=$mds->check_field_type($field['type']);
            if(!$result['success']){
                $this->error($result['msg']);
            }
            if($field['type']=='varchar'){
                $field['len']=min($field['len'],16383);
                if($field['len']<=0){
                    $field['len']=500;
                }
            }
            $field['name_dbname']=$mds->field_db_name($field['name']);
            $this->success('','',array('field'=>$field,'objid'=>$objid));
        }else{
            $field=input('field','','url_b64decode');
            $objid=input('objid','');
            $field=$field?json_decode($field,true):array();
            $this->assign('field',$field);
            $this->assign('objid',$objid);
            return $this->fetch();
        }
    }
    
    public function indexesAction(){
        $dsId=input('ds_id/d',0);
        $mds=model('Dataset');
        $dsData=$dsId>0?$mds->getById($dsId):null;
        if(empty($dsData)){
            $this->error('数据集不存在');
        }
        
        $fields=$dsData['config']['fields'];
        init_array($fields);
        
        $dsTable=DatasetTable::getInstance($dsId);
        $dbColumns=$dsTable->dbColumns();
        $dbIndexes1=db()->query('SHOW INDEX FROM `'.$dsTable->fullTableName().'`');
        $dbIndexes=array();
        foreach ($dbIndexes1 as $dbIndex){
            $dbIndex=\util\Funcs::array_keys_to_lower($dbIndex);
            $dbIxKey=$dbIndex['key_name'];
            if(strcasecmp($dbIxKey,'PRIMARY')===0){
                
                continue;
            }
            if(!isset($dbIndexes[$dbIxKey])){
                $dbIndexes[$dbIxKey]=array('name'=>$dbIxKey,'fields'=>array());
                if(empty($dbIndex['non_unique'])){
                    $dbIndexes[$dbIxKey]['type']='unique';
                }else{
                    $dbIndexes[$dbIxKey]['type']=strtolower($dbIndex['index_type'])=='fulltext'?'fulltext':'index';
                }
            }
            $dbIndexes[$dbIxKey]['fields'][$dbIndex['column_name']]=$dbIndex['column_name'];
        }
        if($this->request->isPost()){
            $postIndexes=trim_input_array('indexes');
            $indexes=array();
            foreach ($postIndexes as $k=>$v){
                init_array($v['fields']);
                foreach ($v['fields'] as $fk=>$fv){
                    if(empty($fv)||$fv=='-1'){
                        
                        unset($v['fields'][$fk]);
                    }
                }
                $v['fields']=array_unique($v['fields']);
                $v['fields']=array_filter($v['fields']);
                $v['fields']=array_values($v['fields']);
                
                $vFields=array();
                foreach ($v['fields'] as $fv){
                    $vFields[$fv]=$fv;
                }
                $v['fields']=$vFields;
                
                $indexes[md5(serialize($vFields))]=$v;
            }
            $dbIndexes1=array();
            foreach ($dbIndexes as $k=>$v){
                init_array($v['fields']);
                $dbIndexes1[md5(serialize($v['fields']))]=$v;
            }
            $dbIndexes=$dbIndexes1;
            
            foreach ($indexes as $k=>$v){
                if($dbIndexes[$k]){
                    
                    if($v['type']==$dbIndexes[$k]['type']){
                        
                        unset($indexes[$k]);
                        unset($dbIndexes[$k]);
                        continue;
                    }
                }
            }
            if($dbIndexes){
                
                foreach ($dbIndexes as $k=>$v){
                    if($v['name']){
                        db()->execute('ALTER TABLE `'.$dsTable->fullTableName().'` DROP INDEX `'.$v['name'].'`');
                    }
                }
            }
            $error='';
            if($indexes){
                
                $engineIsMyisam=\util\Db::table_engine($dsTable->fullTableName());
                $engineIsMyisam=$engineIsMyisam=='myisam'?true:false;
                $maxIxLen=$engineIsMyisam?250:191;
                
                $allowTypes=array('bigint'=>array('index','unique'),'double'=>array('index','unique'),'mediumtext'=>array('fulltext'),'datetime'=>array('index','unique'));
                foreach ($indexes as $k=>$v){
                    if(!in_array($v['type'], array('index','unique','fulltext'))){
                        continue;
                    }
                    init_array($v['fields']);
                    if(empty($v['fields'])){
                        continue;
                    }
                    $ixType=$v['type'];
                    $ixName=array();
                    $ixFields=array();
                    
                    $varcharLens=array();
                    $errorType=array();
                    foreach ($v['fields'] as $fname){
                        $ixName[]=$fname;
                        $ixLen='';
                        if($dbColumns[$fname]&&preg_match('/\bvarchar\s*\((\d+)\)/',$dbColumns[$fname]['type'],$mlen)){
                            
                            $ixLen=intval($mlen[1]);
                            $ixLen=$ixLen>$maxIxLen?$maxIxLen:$ixLen;
                        }
                        if($ixLen){
                            $varcharLens[$fname]=$ixLen;
                            $ixFields[$fname]=$fname.'('.$ixLen.')';
                        }else{
                            $ixFields[$fname]=$fname;
                        }
                        $allowType=$allowTypes[$fields[$fname]['type']];
                        if($allowType&&!in_array($ixType,$allowType)){
                            
                            $errorType[]=$fields[$fname]['name'].' '.lang('ds_ix_type_'.$ixType);
                        }
                    }
                    if($errorType){
                        $errorType=implode(',', $errorType);
                        $error.='错误的索引：'.$errorType.'<br>';
                        continue;
                    }
                    $ixName=count($ixName)>1?('i'.substr(md5(serialize($ixName)),8,16)):$ixName[0];
                    if($engineIsMyisam){
                        
                        $lenElse=250-count($ixFields)*2+count($varcharLens)*2;
                        if(array_sum($varcharLens)>$lenElse){
                            asort($varcharLens);
                            foreach ($varcharLens as $vlf=>$vlv){
                                $varcharLen=intval($lenElse/(count($varcharLens)));
                                if($vlv>$varcharLen){
                                    $vlv=$varcharLen;
                                }
                                $ixFields[$vlf]=$vlf.'('.$vlv.')';
                                $lenElse=$lenElse-$vlv;
                                unset($varcharLens[$vlf]);
                            }
                        }
                    }
                    $ixFields=implode(',',$ixFields);
                    try{
                        db()->execute('ALTER TABLE `'.$dsTable->fullTableName().'` ADD '.$ixType.' `'.$ixName.'`('.$ixFields.')');
                    }catch(\Exception $ex){
                        $exMsg=$ex->getMessage();
                        $exMsg=$dsTable->convertErrorColumn($exMsg,$fields);
                        $error.=$exMsg.'<br>';
                    };
                }
            }
            if($error){
                $this->error($error,'');
            }else{
                $this->success('操作成功','dataset/set?id='.$dsId);
            }
        }else{
            $this->assign('fields',$fields);
            $this->assign('dbIndexes',$dbIndexes);
            $this->assign('dsData',$dsData);
            return $this->fetch();
        }
    }
    
    public function dbAction(){
        $dsId=input('ds_id/d',0);
        $mds=model('Dataset');
        $dsData=$dsId>0?$mds->getById($dsId):null;
        if(empty($dsData)){
            $this->error('数据集不存在');
        }
        $fields=$dsData['config']['fields'];
        init_array($fields);
        $dst=DatasetTable::getInstance($dsId);
        
        $mcache=\skycaiji\admin\model\CacheModel::getInstance();
        $cond=array();
        $isSearch=false;
        $search=array('id'=>input('id/d',0));
        if($search['id']<=0){
            unset($search['id']);
        }else{
            $cond['id']=$search['id'];
            $isSearch=true;
        }
        foreach ($fields as $k=>$v){
            $searchK=input($k,'');
            if(!is_empty($searchK,true)){
                $cond[$k]=array('like','%'.addslashes($searchK).'%');
                $search[$k]=$searchK;
                $isSearch=true;
            }
        }
        $search['num']=input('num/d',0);
        if($search['num']<=0){
            $search['num']=$mcache->getCache('dataset_db_list_num','data');
        }
        $search['num']=max(30,intval($search['num']));
        $mcache->setCache('dataset_db_list_num',$search['num']);
        
        $list=$dst->db()->field('id')->where($cond)->order('id desc')->paginate($search['num'],false,paginate_auto_config());
        $pagenav=$list->render();
        $list=$list->all();
        
        $diIds=array();
        if($list){
            $dids=array();
            foreach ($list as $k=>$v){
                $dids[$k]=$v['id'];
            }
            $list1=$dst->db()->where('id','in',$dids)->select();
            $list=array();
            foreach ($list1 as $v){
                $list[$v['id']]=$v;
            }
            $list1=$list;
            $list=array();
            
            foreach ($dids as $did){
                $list[]=$list1[$did];
                unset($list1[$did]);
            }
            $diIds=$mds->indexDb()->where('dt_id','in',$dids)->where('ds_id',$dsId)->column('id','dt_id');
        }else{
            $list=array();
        }
        
        foreach ($list as $k=>$v){
            init_array($v);
            foreach ($v as $vk=>$vv){
                if($fields[$vk]){
                    if($fields[$vk]['type']=='datetime'){
                        $vv=$dst->convert_date($vv);
                        $v[$vk]=$vv;
                    }elseif($fields[$vk]['type']=='mediumtext'){
                        
                        if(strpos($vv,'<')!==false&&preg_match('/<\w+/', $vv)){
                            $v['_code_'.$vk]=true;
                        }
                    }
                }
            }
            if($diIds[$v['id']]){
                $v['_di_id']=$diIds[$v['id']];
            }
            $list[$k]=$v;
        }
        
        $navTips=$isSearch?'搜索数据':'数据列表';
        $this->set_html_tags(
            '数据集:'.$dsData['name'].' » '.$navTips,
            '数据集：'.$dsData['name'].' » '.$navTips,
            breadcrumb(array(array('url'=>url('dataset/set?id='.$dsId),'title'=>'数据集：'.$dsData['name']),array('url'=>url('dataset/db?ds_id='.$dsId),'title'=>$navTips)))
        );
        $this->assign('search',$search);
        $this->assign('dsData',$dsData);
        $this->assign('list',$list);
        $this->assign('fields',$fields);
        $this->assign('pagenav',$pagenav);
        $this->assign('diIds',$diIds);
        return $this->fetch();
    }
    public function dbSetAction(){
        $dsId=input('ds_id/d',0);
        $mds=model('Dataset');
        $dsData=$dsId>0?$mds->getById($dsId):null;
        if(empty($dsData)){
            $this->error('数据集不存在');
        }
        $cfields=$dsData['config']['fields'];
        init_array($cfields);
        $dst=DatasetTable::getInstance($dsId);
        if($this->request->isPost()){
            $ids=input('ids/a',array(),'intval');
            $postData=array();
            foreach ($cfields as $k=>$v){
                $fieldData=input($k.'/a',array(),null);
                $postData[$k]=$fieldData;
            }
            $upData=array();
            $newData=array();
            foreach ($ids as $ik=>$iv){
                $newPostData=array();
                foreach ($cfields as $fk=>$fv){
                    $newPostData[$fk]=$postData[$fk][$iv];
                }
                if($iv>0){
                    
                    $upData[$iv]=$newPostData;
                }else{
                    
                    $newData[]=$newPostData;
                }
            }
            
            $error='';
            try{
                if($upData){
                    foreach ($upData as $k=>$v){
                        $dst->setData($k,$v,$cfields,false);
                    }
                }
                if($newData){
                    foreach ($newData as $k=>$v){
                        $dst->setData(0,$v,$cfields,false);
                    }
                }
            }catch(\Exception $ex){
                $error=$ex->getMessage();
                $error=$dst->convertErrorColumn($error,$cfields);
            }
            if($error){
                $this->error($error,'');
            }else{
                $this->success('操作成功',$newData?('dataset/db?ds_id='.$dsId):'');
            }
        }else{
            $this->error('操作失败','');
        }
    }
    public function dbDeleteAction(){
        $dsId=input('ds_id/d',0);
        $mds=model('Dataset');
        $dsData=$dsId>0?$mds->getById($dsId):null;
        if(empty($dsData)){
            $this->error('数据集不存在');
        }
        $dst=DatasetTable::getInstance($dsId);
        if($this->request->isPost()){
            $ids=input('ids/a',array(),'intval');
            init_array($ids);
            foreach ($ids as $id){
                $mds->deleteById(0,$dsId,$id);
            }
            $this->success('删除成功','',$ids);
        }else{
            $this->error('删除失败','');
        }
    }
    
    public function infoAction(){
        $diId=input('di_id/d',0);
        $isIndex=$diId>0?true:false;
        
        $dsId=0;
        $dtId=0;
        $mds=model('Dataset');
        $diData=array();
        if($diId>0){
            
            $diData=$mds->indexDb()->where('id',$diId)->find();
            if($diData){
                $dsId=$diData['ds_id'];
                $dtId=$diData['dt_id'];
            }else{
                $this->error(lang('cdataset').'：数据'.$diId.'不存在','dataset/index');
            }
        }else{
            $dsId=input('ds_id/d',0);
            $dtId=input('dt_id/d',0);
            if($dsId<=0||$dtId<=0){
                $this->error('没有数据ID','');
            }
        }
        $dsData=$dsId>0?$mds->getById($dsId):null;
        if(empty($dsData)){
            $this->error('数据集'.$dsId.'不存在','dataset/list');
        }
        $mdt=DatasetTable::getInstance($dsId);
        $dtData=array();
        if($dtId>0){
            $dtData=$mdt->db()->where('id',$dtId)->find();
            if(empty($dtData)){
                $this->error(sprintf('"%s" %s：序号%s不存在',$dsData['name'],lang('cdataset'),$dtId),'dataset/db?ds_id='.$dsId);
            }
        }
        if($this->request->isPost()){
            $url=input('url','','trim');
            if(!empty($url)&&!\util\Funcs::is_right_url($url)){
                $this->error('源网址格式错误！请以http://或者https://开头');
            }
            
            $fieldVals=input('field_vals/a',array(),null);
            $id=$mdt->setData($dtId,$fieldVals,$dsData['config']['fields'],$url);
            
            $fieldVals=$mdt->db()->where('id',$id)->find();
            unset($fieldVals['id']);
            
            $return=array();
            if($isIndex){
                
                if($fieldVals){
                    $fieldVals=\util\Funcs::implode_arr2str($fieldVals);
                }else{
                    $fieldVals='';
                }
                $return=array('is_index'=>true,'id'=>$diId,'url'=>$url,'fields'=>$fieldVals);
                if($diId>0){
                    
                    $diData=$mds->indexDb()->where('id',$diId)->find();
                    $return['addtime']=date('Y-m-d H:i:s',$diData['addtime']);
                    $return['uptime']=date('Y-m-d H:i:s',$diData['uptime']);
                }
            }else{
                
                $return=array('id'=>$id,'fields'=>$fieldVals);
            }
            
            $this->success($dtData?'已修改':'已添加',$dtData?'':('dataset/info?ds_id='.$dsId.'&dt_id='.$dtId),$return);
        }else{
            if(empty($diData)){
                $diData=$mds->indexDb()->where('ds_id',$dsId)->where('dt_id',$dtId)->find();
            }
            
            $dbData=array(
                'ds_id'=>$dsId,
                'dt_id'=>$dtId,
            );
            $curDiId=0;
            if($diData){
                if($isIndex){
                    
                    $dbData['di_id']=$diData['id'];
                }
                $dbData['url']=$diData['url'];
                $curDiId=$diData['id'];
            }
            
            $fields=$dsData['config']['fields'];
            init_array($fields);
            if($fields){
                foreach ($fields as $k=>$v){
                    $v['val']=$dtData[$k];
                    if(strpos($v['val'],'<')!==false&&preg_match('/<\w+/', $v['val'])){
                        $v['_code']=true;
                    }
                    $fields[$k]=$v;
                }
            }
            
            
            $this->assign('dbData',$dbData);
            $this->assign('fields',$fields);
            $this->assign('hasData',$dtData?true:false);
            if($this->request->isAjax()){
                return $this->fetch('info_ajax');
            }else{
                $this->set_html_tags(
                    '数据集数据:'.$curDiId,
                    '数据集数据：ID '.$curDiId.'（序号 '.$dtId.'）',
                    breadcrumb(array(array('url'=>url('dataset/db?ds_id='.$dsId),'title'=>'数据集：'.$dsData['name']),array('url'=>url('dataset/info?ds_id='.$dsId.'&dt_id='.$dtId),'title'=>'序号:'.$dtId)))
                );
                
                return $this->fetch('info');
            }
        }
    }
}