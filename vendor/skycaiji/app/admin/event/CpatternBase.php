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

class CpatternBase extends CollectCommon{
    public $cur_c_module=array('pattern'=>true);
    
    public function merge_convert_variables($data){
        if($data){
            if(is_array($data)){
                foreach ($data as $k=>$v){
                    $data[$k]=$this->merge_convert_variables($v);
                }
            }elseif(is_string($data)&&!is_numeric($data)){
                
                if(strpos($data, '[变量')!==false){
                    $varVals=g_sc('task_full_variables');
                    init_array($varVals);
                    
                    $vars=array();
                    $vals=array();
                    
                    if(preg_match_all('/\[\x{53d8}\x{91cf}.+?\]/u', $data, $mvars)){
                        $vars=$mvars[0];
                        foreach ($vars as $vk=>$vv){
                            $vals[$vk]=$varVals[$vv];
                        }
                    }
                    $data=str_replace($vars, $vals, $data);
                }
            }
        }
        return $data;
    }
    
    
    public function echo_url_msg($strArgs,$url,$opened='',$color='black'){
        init_array($strArgs);
        if($opened){
            
            $strArgs[0].='：%s';
            $strArgs[]=$opened.$url;
        }else{
            
            $strArgs[0].='：<a href="%s" target="_blank">%s</a>';
            $strArgs[]=$url;
            $strArgs[]=$url;
        }
        
        if(!\util\Param::is_task_close_echo()){
            
            $urlMsgLink=\util\Tools::echo_url_msg_link($url,true);
            if($urlMsgLink&&is_array($urlMsgLink)){
                $strArgs[0].=$urlMsgLink[0];
                $strArgs[]=$urlMsgLink[1];
            }
        }
        
        $this->echo_msg($strArgs,$color);
    }
    
    public function sign_addslashes($str){
        $str=str_replace(array('[',']'), array('\[','\]'), $str);
        return $str;
    }

    /*保存页面配置时处理数据*/
    public function page_set_config($pageType,$pageConfig){
        if(!is_array($pageConfig)){
            $pageConfig=array();
        }
        $pageConfig['url_web']=$this->_page_set_config_url_web($pageConfig['url_web']);
        
        if(!is_array($pageConfig['content_signs'])){
            $pageConfig['content_signs']=array();
        }
        $contentSigns=array();
        foreach ($pageConfig['content_signs'] as $v){
            if(is_string($v)){
                
                $v=json_decode(url_b64decode($v),true);
            }
            if(is_array($v)&&$v['identity']){
                $contentSigns[$v['identity']]=$v;
            }
        }
        $pageConfig['content_signs']=array_values($contentSigns);
    
        
        if($this->page_has_pagination($pageType)){
            $pnConfig=is_array($pageConfig['pagination'])?$pageConfig['pagination']:array();
            if($pageType=='url'){
                
                if(!empty($pnConfig['fields'])){
                    foreach ($pnConfig['fields'] as $k=>$v){
                        if(!is_array($v)){
                            $v=json_decode(url_b64decode($v),true);
                        }
                        $pnConfig['fields'][$k]=$v;
                    }
                }
            }
            $pnConfig['open']=intval($pnConfig['open']);
            $pnConfig['max']=intval($pnConfig['max']);
            
            $pnConfig['url_web']=$this->_page_set_config_url_web($pnConfig['url_web']);
            $pnConfig['renderer']=$this->_page_set_config_renderer($pnConfig['renderer']);
            
            $pageConfig['pagination']=$pnConfig;
        }
        $pageConfig['renderer']=$this->_page_set_config_renderer($pageConfig['renderer']);
        return $pageConfig;
    }
    private function _page_set_config_url_web($urlWebConfig){
        init_array($urlWebConfig);
        $urlWebConfig['open']=intval($urlWebConfig['open']);
        $urlWebConfig['form_method']=empty($urlWebConfig['form_method'])?'':strtolower($urlWebConfig['form_method']);
        $urlWebConfig['content_type']=empty($urlWebConfig['content_type'])?'':strtolower($urlWebConfig['content_type']);
        $urlWebConfig['header_global']=empty($urlWebConfig['header_global'])?'':strtolower($urlWebConfig['header_global']);
        
        \util\Funcs::filter_key_val_list($urlWebConfig['form_names'],$urlWebConfig['form_vals']);
        \util\Funcs::filter_key_val_list($urlWebConfig['header_names'], $urlWebConfig['header_vals']);
        
        return $urlWebConfig;
    }
    private function _page_set_config_renderer($renderer){
        init_array($renderer);
        \util\Funcs::filter_key_val_list3($renderer['types'], $renderer['elements'], $renderer['contents']);
        foreach ($renderer['types'] as $k=>$v){
            if(!$this->renderer_type_has_option($v, 'element')){
                
                $renderer['elements'][$k]='';
            }
            if(!$this->renderer_type_has_option($v, 'content')){
                
                $renderer['contents'][$k]='';
            }
        }
        return $renderer;
    }
    
    
    protected function _page_init_rule($pageType,$pageConfig,$isPagination){
        $urlRequired=$pageType=='relation_url'?true:false;
        if($isPagination){
            $urlRequired=true;
        }
        
        if(empty($pageConfig['area_module'])){
            
            $pageConfig['reg_area']=$this->convert_sign_match($pageConfig['area']);
            $pageConfig['reg_area']=$this->correct_reg_pattern($pageConfig['reg_area']);
            
            $pageConfig['reg_area_merge']=$this->set_merge_default($pageConfig['reg_area'], $pageConfig['area_merge']);
            if(empty($pageConfig['reg_area_merge'])){
                
                $pageConfig['reg_area_merge']=coll_sign('match');
            }
        }else{
            
            $pageConfig['reg_area']=$pageConfig['area'];
            
            $pageConfig['reg_area_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['area_merge']);
        }
        $pageConfig['reg_area_module']=$pageConfig['area_module'];
        
        if($isPagination){
            
            init_array($pageConfig['number']);
            foreach ($pageConfig['number'] as $k=>$v){
                if($k=='start'||$k=='end'||$k=='url_mode'){
                    
                    continue;
                }
                $pageConfig['number'][$k]=intval($v);
            }
            $pageConfig['number']['inc']=max(1,intval($pageConfig['number']['inc']));
            
            if($urlRequired){
                
                if(empty($pageConfig['url_rule'])){
                    $pageConfig['url_rule_module']='';
                    $pageConfig['url_rule']='^.{0}';
                }
            }
        }
        
        
        if(empty($pageConfig['url_rule_module'])){
            
            if($urlRequired){
                
                $pageConfig['reg_url']=$this->convert_sign_match($pageConfig['url_rule']);
                $pageConfig['reg_url']=$this->correct_reg_pattern($pageConfig['reg_url']);
                
                $pageConfig['reg_url_merge']=$this->set_merge_default($pageConfig['reg_url'], $pageConfig['url_merge']);
            }else{
                
                if(!empty($pageConfig['url_rule'])){
                    $pageConfig['reg_url']=$this->convert_sign_match($pageConfig['url_rule']);
                    $pageConfig['reg_url']=$this->correct_reg_pattern($pageConfig['reg_url']);
                }else{
                    
                    $pageConfig['reg_url']='\bhref\s*=\s*[\'\"](?P<match>[^\'\"]*)[\'\"]';
                }
                
                $pageConfig['reg_url_merge']=$this->set_merge_default($pageConfig['reg_url'], $pageConfig['url_merge']);
            }
            if(empty($pageConfig['reg_url_merge'])){
                
                $pageConfig['reg_url_merge']=coll_sign('match');
            }
        }elseif('xpath'==$pageConfig['url_rule_module']){
            if($urlRequired){
                
                $pageConfig['reg_url']=$pageConfig['url_rule'];
            }else{
                
                if(!empty($pageConfig['url_rule'])){
                    $pageConfig['reg_url']=$pageConfig['url_rule'];
                }else{
                    
                    $pageConfig['reg_url']='//a';
                }
            }
            
            $pageConfig['reg_url_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['url_merge']);
        }elseif('json'==$pageConfig['url_rule_module']){
            $pageConfig['reg_url']=$pageConfig['url_rule'];
            
            $pageConfig['reg_url_merge']=$this->set_merge_default('(?P<match>.+)', $pageConfig['url_merge']);
        }
        $pageConfig['reg_url_module']=$pageConfig['url_rule_module'];
        
        
        if(!empty($pageConfig['url_must'])){
            
            $pageConfig['url_must']=$this->correct_reg_pattern($pageConfig['url_must']);
        }
        
        
        if(!empty($pageConfig['url_ban'])){
            
            $pageConfig['url_ban']=$this->correct_reg_pattern($pageConfig['url_ban']);
        }
        
        return $pageConfig;
    }
    
   
    
    /*获取关联页的父级页面名称*/
    public function relation_parent_pages($curName,$configList,$high2lowSort=false){
        $parentPages=array();
        if(!is_array($configList)){
            $configList=array();
        }
        
        $pageName=$curName;
        
        $depth=0;
        
        do{
            $pageConfig=$configList[$pageName];
            if(empty($pageConfig)){
                
                break;
            }else{
                $parentPage=$pageConfig['page'];
                if($parentPage==$pageName||in_array($parentPage,$parentPages)){
                    
                    break;
                }else{
                    
                    if(!empty($parentPage)){
                        
                        $parentPages[$depth]=$parentPage;
                    }
                    $pageName=$parentPage;
                }
            }
            $depth++;
        }while(!empty($pageName));
        
        if($high2lowSort){
            
            krsort($parentPages);
        }
        
        return array_values($parentPages);
    }
    
    
    
    /*不在规则中的标签列表*/
    public function signs_not_in_rule($ruleStr,$mergeStr,$whole,$keyIsMatch=false,$returnFound=false){
        $ruleSignsIds=$this->rule_str_signs($ruleStr,true);
        $ruleSignsIds=$ruleSignsIds['id'];
        
        $mergeSignsIds=$this->merge_str_signs($mergeStr,true);
        $mergeSignsIds=$mergeSignsIds['id'];
        
        $unknownSigns=array();
        $foundSigns=array();
        if(!empty($mergeSignsIds)){
            
            if(empty($ruleSignsIds)){
                
                if($whole){
                    
                    foreach ($mergeSignsIds as $v){
                        $sign=$keyIsMatch?('match'.$v):coll_sign('match',$v);
                        if($v!=''){
                            
                            $unknownSigns[$sign]=$sign;
                        }else{
                            if($returnFound){
                                
                                $foundSigns[$sign]=$sign;
                            }
                        }
                    }
                }else{
                    
                    foreach ($mergeSignsIds as $v){
                        $sign=$keyIsMatch?('match'.$v):coll_sign('match',$v);
                        $unknownSigns[$sign]=$sign;
                    }
                }
            }else{
                
                foreach ($mergeSignsIds as $v){
                    $sign=$keyIsMatch?('match'.$v):coll_sign('match',$v);
                    if(!in_array($v, $ruleSignsIds)){
                        
                        $unknownSigns[$sign]=$sign;
                    }else{
                        if($returnFound){
                            
                            $foundSigns[$sign]=$sign;
                        }
                    }
                }
            }
        }
        if($returnFound){
            
            return array('unknown'=>$unknownSigns,'found'=>$foundSigns);
        }else{
            
            return $unknownSigns;
        }
    }
    
    public function page_is_list($pageType){
        if($pageType=='front_url'||$pageType=='level_url'||$pageType=='relation_url'){
            return true;
        }else{
            return false;
        }
    }
    
    public function page_has_pagination($pageType){
        static $types=array('source_url','level_url','url');
        if(in_array($pageType,$types)){
            return true;
        }else{
            return false;
        }
    }
    /*转换成数据源*/
    public function page_source_merge($pageType,$pageName){
        $pageSource=$pageType;
        if($this->page_is_list($pageSource)){
            $pageSource.=':'.$pageName;
        }
        return $pageSource;
    }
    /*数据源名称*/
    public function page_source_name($pageType,$pageName){
        $langKey='page_'.$pageType;
        $name=lang($langKey);
        if($name===$langKey){
            
            $name=$pageType;
        }
        if($this->page_is_list($pageType)){
            $name.='：'.$pageName;
        }
        return $name;
    }
    /*分解数据源为type和name*/
    public function page_source_split($pageSource){
        $type='';
        $name='';
        if($pageSource){
            if(preg_match('/^(\w+)\:(.*)$/',$pageSource,$mpage)){
                $type=$mpage[1];
                $name=$mpage[2];
            }else{
                $type=$pageSource;
                $name='';
            }
        }
        if($this->page_is_list($type)){
            $name=$name?$name:'';
        }else{
            $name='';
        }
        return array($type,$name);
    }
    
    public function renderer_type_has_option($type,$checkOption){
        $types=array(
            'wait_time'=>array('content'=>true),
            'scroll_top'=>array('content'=>true),
            'click'=>array('element'=>true),
            'val'=>array('element'=>true,'content'=>true),
        );
        $options=$types[$type];
        init_array($options);
        return $options[$checkOption]?true:false;
    }
    /*多个数组合并成键值对*/
    public function arrays_to_key_val($arr1,$arr2){
        if(!is_array($arr1)){
            $arr1=array();
        }
        if(!is_array($arr2)){
            $arr2=array();
        }
        
        static $list=array();
        $key=md5(serialize($arr1).' '.serialize($arr2));
        
        $data=$list[$key];
        if(!isset($data)){
            $data=array();
            foreach ($arr1 as $k=>$v){
                if(!\util\Funcs::is_null($v)){
                    
                    $data[$v]=$arr2[$k];
                }
            }
            $list[$key]=$data;
        }
        
        return is_array($data)?$data:array();
    }
}
?>