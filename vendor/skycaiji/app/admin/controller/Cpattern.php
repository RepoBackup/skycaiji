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

/*采集器：规则采集*/
class Cpattern extends CollectorController {
	/**
	 * 起始页网址
	 */
    public function sourceAction(){
        if(request()->isPost()&&input('is_submit')){
            $source=input('source/a',array(),'trim');
            if($source['type']=='custom'){
                
                if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$source['urls'],$urls)){
                    $urls=array_unique($urls[0]);
                }else{
                    $this->error('请输入正确的网址');
                }
            }elseif($source['type']=='batch'){
                if(!preg_match('/^\w+\:\/\/[^\r\n]+$/i',$source['url'])){
                    $this->error('请输入正确的网址格式');
                }
                
                if(stripos($source['url'],coll_sign('match'))===false){
                    $this->error('请在网址格式中添加 '.coll_sign('match').' 才能批量生成网址！');
                }
                if(empty($source['param'])){
                    $this->error('请选择参数类型');
                }
                $urls=array();
                $urlFmt=$source['url'];
                if($source['param']=='num'){
                    
                    $urls=\util\Funcs::increase_nums($source['param_num_start'],$source['param_num_end'],$source['param_num_inc'],$source['param_num_desc'],$source['param_num_len']);
                    foreach ($urls as $k=>$v){
                        $urls[$k]=str_replace(coll_sign('match'), $v, $source['url']);
                    }
                    $urlParamNum="{$source['param_num_start']}\t{$source['param_num_end']}\t{$source['param_num_inc']}\t{$source['param_num_desc']}\t{$source['param_num_len']}";
                    $urlParamNum=trim($urlParamNum);
                    $urlFmt=str_replace(coll_sign('match'),"{param:num,{$urlParamNum}}",$urlFmt);
                }elseif($source['param']=='letter'){
                    
                    $letter_start=ord($source['param_letter_start']);
                    $letter_end=ord($source['param_letter_end']);
                    $letter_end=max($letter_start,$letter_end);
                    $source['param_letter_desc']=$source['param_letter_desc']?1:0;
                    
                    if($source['param_letter_desc']){
                        
                        for($i=$letter_end;$i>=$letter_start;$i--) {
                            $urls[]=str_replace(coll_sign('match'), chr($i), $source['url']);
                        }
                    }else{
                        for($i=$letter_start;$i<=$letter_end;$i++) {
                            $urls[]=str_replace(coll_sign('match'), chr($i), $source['url']);
                        }
                    }
                    $urlFmt=str_replace(coll_sign('match'),"{param:letter,{$source['param_letter_start']}\t{$source['param_letter_end']}\t{$source['param_letter_desc']}}",$urlFmt);
                }elseif($source['param']=='custom'){
                    
                    if(preg_match_all('/[^\r\n]+/', $source['param_custom'],$cusParams)){
                        $cusParams=array_unique($cusParams[0]);
                        foreach ($cusParams as $cusParam){
                            $urls[]=str_replace(coll_sign('match'), $cusParam, $source['url']);
                        }
                        $urlFmt=str_replace(coll_sign('match'),"{param:custom,".implode("\t", $cusParams)."}",$urlFmt);
                    }
                }
            }elseif($source['type']=='large'){
                
                if(preg_match_all('/^\w+\:\/\/[^\r\n]+/im',$source['large_urls'],$urls)){
                    $urls=array_unique($urls[0]);
                }else{
                    $this->error('请输入正确的网址');
                }
            }elseif($source['type']=='api'){
                
                if(!preg_match('/^\w+\:\/\//i',$source['api'])){
                    $this->error('请输入正确的api网址');
                }
                $urlFmt=$source['api'].'{json:'.$source['api_json'].'}';
            }
            
            if($urls||$urlFmt){
                $urls=$urls?array_values($urls):'';
                $this->success('',null,array('objid'=>$source['objid'],'url'=>$urlFmt,'urls'=>$urls));
            }else{
                $this->error('未生成网址！');
            }
        }else{
            $sourceUrl=input('source_url','','trim');
            $source=array();
            if($sourceUrl){
                $source['objid']=input('objid','');
                
                if(preg_match('/\{param\:(\w+)\,([^\}]*)\}/i',$sourceUrl,$param)){
                    
                    $source['url']= preg_replace('/\{param\:(\w+)\,([^\}]*)\}/i', coll_sign('match'), $sourceUrl);
                    $source['type']='batch';
                    $source['param']=strtolower($param[1]);
                    $param_val=explode("\t", $param[2]);
                    if($source['param']=='num'){
                        $source['param_num_start']=intval($param_val[0]);
                        $source['param_num_end']=intval($param_val[1]);
                        $source['param_num_inc']=intval($param_val[2]);
                        $source['param_num_desc']=intval($param_val[3]);
                        $source['param_num_len']=intval($param_val[4]);
                    }elseif($source['param']=='letter'){
                        $source['param_letter_start']=strtolower($param_val[0]);
                        $source['param_letter_end']=strtolower($param_val[1]);
                        $source['param_letter_desc']=intval($param_val[2]);
                    }elseif($source['param']=='custom'){
                        
                        $source['param_custom']=implode("\r\n", $param_val);
                    }
                }elseif(preg_match('/\{json\:([^\}]*)\}/i',$sourceUrl,$json)){
                    
                    $source['type']='api';
                    $source['api']=preg_replace('/\{json\:([^\}]*)\}/i','',$sourceUrl);
                    $source['api_json']=$json[1];
                }elseif(preg_match('/[\r\n]/', $sourceUrl)){
                    
                    $source['type']='large';
                    $source['large_urls']=$sourceUrl;
                }else{
                    
                    $source['type']='custom';
                    $source['urls']=$sourceUrl;
                }
            }
            $this->assign('source',$source);
            return $this->fetch();
        }
    }
    
    /**
     * 内容分页
     * 添加分页字段
     */
    public function pagination_fieldAction(){
    	if(request()->isPost()){
    		$objid=input('post.objid');
    		$pnField=trim_input_array('post.pagination_field');
    		if(empty($pnField['field'])){
    			$this->error('请选择字段');
    		}
    		$this->success('',null,array('pagination_field'=>$pnField,'objid'=>$objid));
    	}else{
    		$pnField=input('pagination_field','','url_b64decode');
    		$objid=input('objid');
    		$pnField=$pnField?json_decode($pnField,true):'';
    		$this->assign('pnField',$pnField);
    		$this->assign('objid',$objid);
    		$this->assign('isLoop',input('is_loop'));
    		return $this->fetch();
    	}
    }
    
    /*前置页规则*/
    public function front_urlAction(){
        if(request()->isPost()&&input('is_submit')){
            $objid=input('post.objid');
            $front_url=trim_input_array('post.front_url');
            if(empty($front_url['name'])){
                $this->error('请输入名称');
            }
            $this->_check_name($front_url['name'],'前置页名称');
            
            if(empty($front_url['url'])){
                $this->error('请输入网址');
            }
            
            $front_url['use_cookie']=intval($front_url['use_cookie']);
            $front_url['use_cookie_img']=intval($front_url['use_cookie_img']);
            $front_url['use_cookie_file']=intval($front_url['use_cookie_file']);
            
            
            $front_url=controller('admin/Cpattern','event')->page_set_config('front_url',$front_url);
            
            $this->success('',null,array('front_url'=>$front_url,'objid'=>$objid));
        }else{
            $front_url=input('front_url','','url_b64decode');
            $objid=input('objid');
            $front_url=$front_url?json_decode($front_url,true):array();
            $this->assign('front_url',$front_url);
            $this->assign('objid',$objid);
            return $this->fetch();
        }
    }
    /*复制前置页*/
    public function clone_front_urlAction(){
        if(request()->isPost()){
            $frontUrl=input('front_url','','url_b64decode');
            $frontUrl=$frontUrl?json_decode($frontUrl,true):array();
            
            $this->success('',null,array('front_url'=>$frontUrl));
        }else{
            $this->error('复制失败');
        }
    }
    /*多级网址规则*/
    public function level_urlAction(){
    	if(request()->isPost()&&input('is_submit')){
    		$objid=input('post.objid');
    		$level_url=trim_input_array('post.level_url');
    		if(empty($level_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($level_url['name'],'多级页名称');
    		
    		
    		$level_url=controller('admin/Cpattern','event')->page_set_config('level_url',$level_url);
    		
    		$this->success('',null,array('level_url'=>$level_url,'objid'=>$objid));
    	}else{
    	    $level_url=input('level_url','','url_b64decode');
    		$objid=input('objid');
    		$level_url=$level_url?json_decode($level_url,true):array();
    		$this->assign('level_url',$level_url);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /*复制多级页*/
    public function clone_level_urlAction(){
        if(request()->isPost()){
            $levelUrl=input('level_url','','url_b64decode');
            $levelUrl=$levelUrl?json_decode($levelUrl,true):array();
            
            $this->success('',null,array('level_url'=>$levelUrl));
        }else{
            $this->error('复制失败');
        }
    }
    /*关联网址规则*/
    public function relation_urlAction(){
        if(request()->isPost()&&input('is_submit')){
    		$objid=input('post.objid');
    		$relation_url=trim_input_array('post.relation_url');
    		if(empty($relation_url['name'])){
    			$this->error('请输入名称');
    		}
    		$this->_check_name($relation_url['name'],'关联页名称');
    		
    		if(empty($relation_url['url_rule'])){
    			$this->error('请输入提取网址规则');
    		}
    		
    		
    		$relation_url=controller('admin/Cpattern','event')->page_set_config('relation_url',$relation_url);
    		
    		$this->success('',null,array('relation_url'=>$relation_url,'objid'=>$objid));
    	}else{
    		$relation_url=input('relation_url','','url_b64decode');
    		$objid=input('objid');
    		$relation_url=$relation_url?json_decode($relation_url,true):array();
    		$this->assign('relation_url',$relation_url);
    		$this->assign('objid',$objid);
    		return $this->fetch();
    	}
    }
    /*复制关联页*/
    public function clone_relation_urlAction(){
        if(request()->isPost()){
            $relationUrl=input('relation_url','','url_b64decode');
            $relationUrl=$relationUrl?json_decode($relationUrl,true):array();
            
            $this->success('',null,array('relation_url'=>$relationUrl));
        }else{
            $this->error('复制失败');
        }
    }
    
    /*编辑内容标签*/
    public function content_signAction(){
        if(request()->isPost()&&input('is_submit')){
            $objid=input('post.objid');
            $contentSign=trim_input_array('post.content_sign');
            if(empty($contentSign['identity'])){
                $this->error('请输入标识名');
            }elseif(!preg_match('/^[a-z0-9\_]+$/i', $contentSign['identity'])){
                $this->error('标识名只能由数字、字母和下划线组成');
            }elseif(mb_strlen($contentSign['identity'],'utf-8')>50){
                $this->error('标识名长度50字以内');
            }
            switch ($contentSign['module']){
                case 'rule':if(empty($contentSign['rule']))$this->error('规则不能为空！');break;
                case 'xpath':if(empty($contentSign['xpath']))$this->error('xpath规则不能为空！');break;
                case 'json':if(empty($contentSign['json']))$this->error('json提取规则不能为空！');break;
            }
            if(is_array($contentSign['funcs'])){
                $contentSign['funcs']=array_values($contentSign['funcs']);
            }
            $this->success('',null,array('content_sign'=>$contentSign,'objid'=>$objid));
        }else{
            $objid=input('objid');
            $contentSign=input('content_sign','','url_b64decode');
            $contentSign=$contentSign?json_decode($contentSign,true):array();
            $contentSign=is_array($contentSign)?$contentSign:array();
            
            $page_type=input('page_type','');
            $page_config=input('page_config','','trim');
            if($page_config){
                
                $pageConfig=array();
                parse_str($page_config,$pageConfig);
                
                $pageTypeConfig=is_array($pageConfig[$page_type])?$pageConfig[$page_type]:array();
                $pageTypeConfig['content_signs']=is_array($pageTypeConfig['content_signs'])?$pageTypeConfig['content_signs']:array();
                foreach ($pageTypeConfig['content_signs'] as $k=>$v){
                    $pageTypeConfig['content_signs'][$k]=json_decode(url_b64decode($v),true);
                }
                $pageConfig[$page_type]=$pageTypeConfig;
                $page_config=array('objid'=>$pageConfig['objid'],$page_type=>$pageConfig[$page_type]);
            }
            $page_config=is_array($page_config)?$page_config:array();
            
            
            $this->assign('objid',$objid);
            $this->assign('content_sign',$contentSign);
            $this->assign('page_type',$page_type);
            $this->assign('page_config',$page_config);
            return $this->fetch();
        }
    }
    
    /*编辑规则：简单模式*/
    public function easymodeAction(){
    	$taskId=input('task_id/d',0);
    	
    	$mcoll=model('Collector');
    	
    	$taskData=model('Task')->getById($taskId);
    	
    	$collData=$mcoll->getByTaskData($taskData);
    	$collId=$collData['id'];
    	
    	$eCpattern=controller('admin/Cpattern','event');
    	$eCpattern->init($collData);
    	
    	$resizeWidth=CacheModel::getInstance()->getCache('cpattern_easymode_resize','data');
    	init_array($resizeWidth);
    	$resizeWidth=intval($resizeWidth['width']);
    	
    	$this->set_html_tags('任务:'.$taskData['name'].'_引导模式');
    	
    	$this->assign('taskId',$taskId);
    	$this->assign('collId',$collId);
    	$this->assign('resizeWidth',$resizeWidth);
    	return $this->fetch();
    }
    
    public function easymode_resizeAction(){
        $width=input('width/d',0);
        $cname='cpattern_easymode_resize';
        $mcache=CacheModel::getInstance();
        $data=$mcache->getCache($cname,'data');
        if(empty($data)&&!is_array($data)){
            $data=array();
        }
        $data['width']=$width;
        $mcache->setCache($cname,$data);
        $this->success();
    }
    
    
    public function page_signs_sortAction(){
        $mcache=CacheModel::getInstance();
        $key='cpattern_page_signs_sort';
        $sort=$mcache->getCache($key,'data');
        $sort=$sort=='asc'?'desc':'asc';
        $mcache->setCache($key,$sort);
        $this->success('已将页面设为'.($sort=='asc'?'升序':'降序').'排列');
    }
    /*获取父级页面的标签列表*/
    public function page_signsAction(){
        if(request()->isPost()){
            $front_urls=input('front_urls/a',array(),'url_b64decode');
            $level_urls=input('level_urls/a',array(),'url_b64decode');
            $relation_urls=input('relation_urls/a',array(),'url_b64decode');
            $sourceConfig=input('source_config/a',array(),'trim');
            $urlConfig=input('url_config/a',array(),'trim');
            $pageConfig=input('page_config/a',array(),'trim');
            $mergeType=input('merge_type','');
            $sourceIsUrl=input('source_is_url/d',0);
            $isPagination=input('is_pagination/d',0);
            $pageType=input('page_type','','trim');
            
            if($sourceIsUrl){
                
                $level_urls=array();
                $urlConfig['area']='';
                $urlConfig['url_rule']='';
                $sourceConfig=$urlConfig;
                if($pageType=='source_url'){
                    $pageType='url';
                }
            }
            
            $pnSigns=null;
            if($isPagination){
                if($pageType=='source_url'){
                    $pnSigns=array('name'=>'当前起始页 - 分页','signs'=>array('area'=>$this->_get_rule_signs($sourceConfig['pagination']['area']),'url'=>$mergeType=='area'?'':$this->_get_rule_signs($sourceConfig['pagination']['url_rule'])),'cur'=>true);
                }elseif($pageType=='level_url'){
                    $pnSigns=array('name'=>'当前多级页 - 分页','signs'=>array('area'=>$this->_get_rule_signs($pageConfig['pagination']['area']),'url'=>$mergeType=='area'?'':$this->_get_rule_signs($pageConfig['pagination']['url_rule'])),'cur'=>true);
                }elseif($pageType=='url'){
                    $pnSigns=array('name'=>'当前内容页 - 分页','signs'=>array('area'=>$this->_get_rule_signs($urlConfig['pagination']['area']),'url'=>$mergeType=='area'?'':$this->_get_rule_signs($urlConfig['pagination']['url_rule'])),'cur'=>true);
                }
                
                $mergeType='content_sign';
            }

            $mergeCsIdentity='';
            if(strpos($mergeType,'content_sign:')===0){
                
                $mergeCsIdentity=str_replace('content_sign:', '', $mergeType);
                $mergeCsIdentity=coll_sign('match',$mergeCsIdentity);
            }
            
            $eCpattern=controller('admin/Cpattern','event');
            
            $frontSigns=array();
            $levelSigns=array();
            $relationSigns=array();
            $sourceSigns=array();
            $urlSigns=array();
            $pageSigns=array();
            $relationUrls=array();
            
            foreach ($front_urls as $k=>$v){
                $v=$v?json_decode($v,true):array();
                $frontSigns[$v['name']]=array(
                    'area'=>'',
                    'url'=>'',
                    'content'=>$this->_get_content_signs($v['content_signs'])
                );
            }
            
            if($pageType!='front_url'){
                $sourceSigns=array('area'=>'','url'=>'');
                if($pageType!='source_url'||($mergeType!='area'&&$mergeType!='url')){
                    
                    $sourceSigns['content']=$this->_get_content_signs($sourceConfig['content_signs']);
                }
            }
            
            if($pageType!='front_url'&&$pageType!='source_url'){
                foreach ($level_urls as $k=>$v){
                    $v=$v?json_decode($v,true):array();
                    $levelSigns[$v['name']]=array(
                        'area'=>$this->_get_rule_signs($v['area']),
                        'url'=>$this->_get_rule_signs($v['url_rule']),
                        'content'=>$this->_get_content_signs($v['content_signs'])
                    );
                }
            }
            
            if($pageType=='url'||$pageType=='relation_url'){
                
                $urlSigns=array('area'=>$sourceIsUrl?'':$this->_get_rule_signs($urlConfig['area']));
                if($pageType!='url'||$mergeType!='area'){
                    
                    $urlSigns['url']=$sourceIsUrl?'':$this->_get_rule_signs($urlConfig['url_rule']);
                }
                if($pageType!='url'||($mergeType!='area'&&$mergeType!='url')){
                    
                    $urlSigns['content']=$this->_get_content_signs($urlConfig['content_signs']);
                }
            }
            
            if($pageType=='relation_url'){
                foreach ($relation_urls as $k=>$v){
                    $v=$v?json_decode($v,true):array();
                    $relationSigns[$v['name']]=array(
                        'area'=>$this->_get_rule_signs($v['area']),
                        'url'=>$this->_get_rule_signs($v['url_rule']),
                        'content'=>$this->_get_content_signs($v['content_signs'])
                    );
                    $relationUrls[$v['name']]=$v;
                }
            }
            
            if($pageType=='front_url'){
                
                $pageSigns=array('area'=>'','url'=>'');
            }else{
                $pageSigns=array('area'=>$this->_get_rule_signs($pageConfig['area']));
                if($mergeType!='area'){
                    
                    $pageSigns['url']=$this->_get_rule_signs($pageConfig['url_rule']);
                }
            }
            if($mergeType!='area'&&$mergeType!='url'){
                
                $pageSigns['content']=$this->_get_content_signs($pageConfig['content_signs']);
            }
            
            if($pageType=='front_url'){
                
                if($pageConfig['name']&&isset($frontSigns[$pageConfig['name']])){
                    
                    $newFrontSigns=array();
                    foreach($frontSigns as $k=>$v){
                        if($pageConfig['name']==$k){
                            
                            $newFrontSigns['_cur_']=$pageSigns;
                            break;
                        }else{
                            $newFrontSigns[$k]=$v;
                        }
                    }
                    $frontSigns=$newFrontSigns;
                }else{
                    
                    $frontSigns['_cur_']=$pageSigns;
                }
            }elseif($pageType=='level_url'){
                
                if($pageConfig['name']&&isset($levelSigns[$pageConfig['name']])){
                    
                    $newLevelSigns=array();
                    foreach($levelSigns as $k=>$v){
                        if($pageConfig['name']==$k){
                            
                            $newLevelSigns['_cur_']=$pageSigns;
                            break;
                        }else{
                            $newLevelSigns[$k]=$v;
                        }
                    }
                    $levelSigns=$newLevelSigns;
                }else{
                    
                    $levelSigns['_cur_']=$pageSigns;
                }
            }elseif($pageType=='relation_url'){
                
                $newRelationSigns=array();
                $newRelationSigns['_cur_']=$pageSigns;
                $relationUrls[$pageConfig['name']]=$pageConfig;
                $relationParentPages=$eCpattern->relation_parent_pages($pageConfig['name'],$relationUrls);
                foreach ($relationParentPages as $relationParentPage){
                    
                    $newRelationSigns[$relationParentPage]=$relationSigns[$relationParentPage];
                }
                $relationSigns=$newRelationSigns;
            }
            
            $frontSigns=array_reverse($frontSigns,true);
            $levelSigns=array_reverse($levelSigns,true);
            
            $allSigns=array();
            
            
            if($isPagination&&$pnSigns){
                
                $allSigns[]=$pnSigns;
            }
            
            foreach ($relationSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前关联页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('relation_url',$k),'signs'=>$v);
                }
            }
            if($pageType=='relation_url'||$pageType=='url'){
                $allSigns[]=array('name'=>($pageType=='url'?'当前':'').'内容页','signs'=>$urlSigns,'cur'=>($pageType=='url'?true:false));
            }
            foreach ($levelSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前多级页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('level_url',$k),'signs'=>$v);
                }
            }
            if($pageType!='front_url'&&!$sourceIsUrl){
                $allSigns[]=array('name'=>($pageType=='source_url'?'当前':'').'起始页','signs'=>$sourceSigns,'cur'=>($pageType=='source_url'?true:false));
            }
            foreach ($frontSigns as $k=>$v){
                if($k=='_cur_'){
                    $allSigns[]=array('name'=>'当前前置页','signs'=>$v,'cur'=>true);
                }else{
                    $allSigns[]=array('name'=>$eCpattern->page_source_name('front_url',$k),'signs'=>$v);
                }
            }
            
            
            foreach ($allSigns as $ask=>$asv){
                if($asv['cur']){
                    if($mergeCsIdentity&&is_array($asv['signs'])&&is_array($asv['signs']['content'])){
                        $curContSigns=array();
                        foreach ($asv['signs']['content'] as $k=>$v){
                            if($v==$mergeCsIdentity){
                                break;
                            }
                            $curContSigns[]=$v;
                        }
                        $asv['signs']['content']=$curContSigns;
                    }
                    $allSigns[$ask]=$asv;
                }
            }
            
            $existSigns=array();
            
            foreach ($allSigns as $ask=>$asv){
                $signs=array('area'=>array(),'url'=>array(),'content'=>array(),'area_global'=>array(),'url_global'=>array(),'content_global'=>array());
                
                $asv=is_array($asv)?$asv:array();
                $asv['signs']=is_array($asv['signs'])?$asv['signs']:array();
                $signs['area']=is_array($asv['signs']['area'])?$asv['signs']['area']:array();
                $signs['url']=is_array($asv['signs']['url'])?$asv['signs']['url']:array();
                $signs['content']=is_array($asv['signs']['content'])?$asv['signs']['content']:array();
                
                
                foreach (array('content','url','area') as $k){
                    foreach ($signs[$k] as $v){
                        if(!in_array($v, $existSigns)){
                            
                            $existSigns[]=$v;
                            $signs[$k.'_global'][]=$v;
                        }
                    }
                }
                
                $asv['signs']=$signs;
                
                $allSigns[$ask]=$asv;
            }
            
            $mcache=CacheModel::getInstance();
            $sort=$mcache->getCache('cpattern_page_signs_sort','data');
            $sort=$sort?$sort:'desc';
            
            if($sort=='asc'){
                
                krsort($allSigns);
                $allSigns=array_values($allSigns);
            }
            
            
            
            $this->success('',null,array('sort'=>$sort,'signs'=>$allSigns));
        }else{
            $this->error();
        }
    }
    
    
    private function _get_content_signs($contentSigns){
        if(is_array($contentSigns)){
            $csSigns=array();
            foreach ($contentSigns as $v){
                if(is_array($v)&&$v['identity']){
                    $csSigns[$v['identity']]=coll_sign('match',$v['identity']);
                }
            }
            $contentSigns=array_values($csSigns);
        }else{
            $contentSigns=array();
        }
        return $contentSigns;
    }
    
    
    private function _get_rule_signs($rule){
        $eCpattern=controller('admin/Cpattern','event');
        $rule=$rule?$rule:'';
        $rule=$eCpattern->convert_sign_match($rule);
        $signs=$eCpattern->rule_str_signs($rule);
        if(empty($signs)){
            $signs=array(coll_sign('match'));
        }
        return $signs;
    }
    
    
    public function variableAction(){
        if($this->request->isPost()&&input('is_submit')){
            $objid=input('objid');
            $var=input('variable/a',array(),'trim');
            init_array($var);
            
            if(empty($var['name'])){
                $this->error('变量名称不能为空！');
            }elseif(!preg_match('/^[a-z0-9\_]+$/i', $var['name'])){
                $this->error('变量名称只能由数字、字母和下划线组成');
            }elseif(mb_strlen($var['name'],'utf-8')>50){
                $this->error('变量名称长度50字以内');
            }
            $var['allow_url']=intval($var['allow_url']);
            if(is_array($var['funcs'])){
                $var['funcs']=array_values($var['funcs']);
            }
            $this->success('','',array('objid'=>$objid,'variable'=>$var));
        }else{
            $objid=input('objid');
            $var=input('variable','','url_b64decode');
            $var=$var?json_decode($var,true):array();
            $params=array('objid'=>$objid,'variable'=>$var);
            $this->assign('params',$params);
            return $this->fetch();
        }
    }
    
    
    public function element_replace_variableAction(){
        if($this->request->isPost()){
            $vals=input('vals','','url_b64decode');
            $names=input('names','','url_b64decode');
            $vals=$vals?json_decode($vals,true):array();
            $names=$names?json_decode($names,true):array();
            init_array($vals);
            init_array($names);
            
            $originalName=input('originalName','','trim');
            $newName=input('newName','','trim');
            
            $fmtOriginalName='[变量'.$originalName.']';
            $fmtNewName='[变量'.$newName.']';
            
            
            $pageEleNames=array('config[front_urls][]','config[level_urls][]','config[relation_urls][]');
            $encodeEleNames=array('config[variables][]','config[field_list][]');
            
            foreach ($vals as $k=>$v){
                $eleName=$names[$k];
                if(empty($eleName)&&is_numeric($eleName)){
                    continue;
                }
                if(in_array($eleName,$pageEleNames)||in_array($eleName,$encodeEleNames)||preg_match('/\[content_signs\]\[\]$/',$eleName)){
                    try{
                        $vDecode=url_b64decode($v);
                        if($vDecode){
                            $vDecode=json_decode($vDecode,true);
                            if(!empty($vDecode)&&is_array($vDecode)){
                                
                                
                                if(in_array($eleName,$pageEleNames)){
                                    
                                    $vDecode=$this->_page_replace_labels($eleName, $vDecode, $fmtOriginalName, $fmtNewName);
                                }elseif($eleName=='config[variables][]'){
                                    if($vDecode['funcs']&&is_array($vDecode['funcs'])){
                                        
                                        foreach ($vDecode['funcs'] as $vk=>$vv){
                                            if(is_array($vv)&&!empty($vv['func_param'])){
                                                $vv['func_param']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vv['func_param']);
                                            }
                                            $vDecode['funcs'][$vk]=$vv;
                                        }
                                    }
                                }elseif($eleName=='config[field_list][]'){
                                    
                                    if($vDecode['module']=='variable'){
                                        $vDecode['variable']=$this->_replace_str($fmtOriginalName, $fmtNewName, $vDecode['variable']);
                                    }
                                }
                                $v=url_b64encode(json_encode($vDecode));
                                $vals[$k]=$v;
                            }
                        }
                    }catch (\Exception $ex){
                        
                    }
                }else{
                    
                    $replaceEleNames = array(
                        'config[request_headers][custom_vals][]',
                        'config[request_headers][img_vals][]',
                        'config[request_headers][file_vals][]',
                        'config[source_url][]',
                        '[area_merge]',
                        '[url_merge]',
                        '[url_web][form_vals][]',
                        '[url_web][header_vals][]',
                        '[renderer][contents][]',
                        '[pagination][number][start]',
                        '[pagination][number][end]',
                    );
                    $doReplace=false;
                    foreach ($replaceEleNames as $replaceEleName){
                        if(stripos($eleName, $replaceEleName)!==false){
                            
                            $doReplace=true;
                            break;
                        }
                    }
                    if($doReplace){
                        $v=$this->_replace_str($fmtOriginalName, $fmtNewName, $v);
                        $vals[$k]=$v;
                    }
                }
            }
            $this->success('已同步修改','',array('vals'=>$vals));
        }
        $this->error('同步修改失败');
    }
    
    private function _page_replace_labels($eleName,$arr,$originalName,$newName){
        init_array($arr);
        if($eleName=='config[front_urls][]'){
            
            if(!empty($arr['url'])){
                $arr['url']=$this->_replace_str($originalName, $newName, $arr['url']);
            }
        }
        
        if(!empty($arr['area_merge'])){
            $arr['area_merge']=$this->_replace_str($originalName, $newName, $arr['area_merge']);
        }
        if(!empty($arr['url_merge'])){
            $arr['url_merge']=$this->_replace_str($originalName, $newName, $arr['url_merge']);
        }
        if(is_array($arr['url_web'])){
            
            if(is_array($arr['url_web']['form_vals'])){
                foreach ($arr['url_web']['form_vals'] as $vk=>$vv){
                    $arr['url_web']['form_vals'][$vk]=$this->_replace_str($originalName, $newName, $vv);
                }
            }
            
            if(is_array($arr['url_web']['header_vals'])){
                foreach ($arr['url_web']['header_vals'] as $vk=>$vv){
                    $arr['url_web']['header_vals'][$vk]=$this->_replace_str($originalName, $newName, $vv);
                }
            }
        }
        if(is_array($arr['renderer'])){
            
            if(is_array($arr['renderer']['contents'])){
                foreach ($arr['renderer']['contents'] as $vk=>$vv){
                    $arr['renderer']['contents'][$vk]=$this->_replace_str($originalName, $newName, $vv);
                }
            }
        }
        if($arr['pagination']&&is_array($arr['pagination'])){
            
            if(is_array($arr['pagination']['number'])){
                if(!empty($arr['pagination']['number']['start'])){
                    $arr['pagination']['number']['start']=$this->_replace_str($originalName, $newName, $arr['pagination']['number']['start']);
                }
                if(!empty($arr['pagination']['number']['end'])){
                    $arr['pagination']['number']['end']=$this->_replace_str($originalName, $newName, $arr['pagination']['number']['end']);
                }
            }
            $arr['pagination']=$this->_page_replace_labels('', $arr['pagination'], $originalName, $newName);
        }
        
        return $arr;
    }
    
    public function page_replace_nameAction(){
        if($this->request->isPost()){
            $pageType=input('type');
            $vals=input('vals','','url_b64decode');
            $names=input('names','','url_b64decode');
            $vals=$vals?json_decode($vals,true):array();
            $names=$names?json_decode($names,true):array();
            init_array($vals);
            init_array($names);
            
            $originalName=input('originalName','','trim');
            $newName=input('newName','','trim');
            
            $updated=array();
            
            foreach ($vals as $k=>$v){
                $eleName=$names[$k];
                if(empty($eleName)&&is_numeric($eleName)){
                    continue;
                }
                if($eleName=='config[relation_urls][]'||$eleName=='config[field_list][]'){
                    try{
                        $vDecode=url_b64decode($v);
                        if($vDecode){
                            $vDecode=json_decode($vDecode,true);
                            if(!empty($vDecode)&&is_array($vDecode)){
                                
                                if($pageType=='relation_url'){
                                    
                                    if($eleName=='config[relation_urls][]'){
                                        
                                        if($vDecode['page']&&$vDecode['page']==$originalName){
                                            $vDecode['page']=$newName;
                                            $updated[$k]=true;
                                        }
                                    }
                                }
                                if($eleName=='config[field_list][]'){
                                    
                                    if($vDecode['source']&&$vDecode['source']==$pageType.':'.$originalName){
                                        $vDecode['source']=$pageType.':'.$newName;
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
}