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

class CollectCommon extends CollectBase{
    public $cur_c_module=array();
    
    public $config_params;
    public $collector;
    public $release;
    public $config;
    public $task_id;
    public $collect_num=0;
    public $collected_field_list=array();
    public $show_opened_tools=false;
    public $first_loop_field=null;
    public $exclude_cont_urls=array();
    public $field_val_list=array();
    public $render_pn_sockets=array();
    public $cur_cont_url='';
    public $cur_cont_source_url='';
    public $collect_opened_tools=array();
    public $used_cont_urls=array();
    protected $field_stop_process=false;
    protected $field_url_complete=true;
    protected $field_down_img=true;
    /*必须存在方法*/
    public function setConfig($config){}
    
    public function init($collData){
        if(!is_array($collData['config'])){
            $collData['config']=safe_unserialize($collData['config']);
        }
        $this->collector=$collData;
        $this->release=model('Release')->cacheByTaskId($collData['task_id']);
        $this->task_id=$collData['task_id'];
        set_g_sc('collect_task_id',$this->task_id);
        
        $keyConfig='collector_config_'.$collData['id'];
        $cacheConfig=cache($keyConfig);
        if(empty($cacheConfig)||$cacheConfig['update_time']!=$collData['uptime']){
            
            $config=$this->initConfig($collData['config']);
            cache($keyConfig,array('update_time'=>$collData['uptime'],'config'=>$config));
        }else{
            $config=$cacheConfig['config'];
        }
        $this->config=is_array($config)?$config:array();
        
        return $collData;
    }
    
    public function initConfig($config){
        $config=$this->init_config_common($config,$this->cur_c_module['pattern']?true:false);
        return $config;
    }
    
    public function collect($num=10){
        \util\Param::set_collector_collecting();
        if(!$this->show_opened_tools){
            $opened_tools=array();
            if(g_sc('task_datahub','open')){
                $opened_tools[]=lang('cdatahub');
            }
            if(g_sc_c('caiji','robots')){
                $opened_tools[]='遵守robots协议';
            }
            if(g_sc_c('download_img','download_img')){
                $opened_tools[]='图片本地化';
            }
            if(g_sc_c('download_file','download_file')){
                $opened_tools[]='文件本地化';
            }
            if(g_sc_c('proxy','open')){
                $opened_tools[]='代理';
            }
            if(g_sc_c('translate','open')){
                $opened_tools[]='翻译';
            }
            if($this->collect_opened_tools&&is_array($this->collect_opened_tools)){
                $opened_tools=array_merge($opened_tools,$this->collect_opened_tools);
            }
            if(!empty($opened_tools)){
                $this->echo_msg(array('已开启功能：%s',implode(' / ', $opened_tools)),'black');
            }
            if($num>0){
                $this->echo_msg(array('预计采集%s条数据',$num),'black');
            }
            $this->show_opened_tools=true;
        }
        $this->collect_num=$num;
        $this->collected_field_list=array();
    }
    /********/
    
    public function destruct_clear(){
        
        $usedContUrls=array();
        if(!empty($this->used_cont_urls)){
            $usedContUrls=$this->used_cont_urls;
            init_array($usedContUrls);
        }
        if($this->cur_cont_url){
            $usedContUrls[md5($this->cur_cont_url)]=1;
        }
        if(!empty($usedContUrls)){
            $usedContUrls=array_keys($usedContUrls);
            \skycaiji\admin\model\Collector::cont_url_remove($usedContUrls,true);
        }
    }
    
    
    public function echo_error($msg = '', $url = null, $data = array(), $wait = 3, array $header = []){
        if($this->is_collecting(true)){
            
            $this->echo_msg($msg,'red');
            $this->collect_stopped($this->task_id,3);
            return null;
        }else{
            $url=$url?$url:'';
            $msg=$this->_echo_msg_str($msg,'red');
            $txt=g_sc('collect_echo_msg_txt');
            $txt=$txt?($txt."\r\n".$msg):$msg;
            if(\util\Param::is_collector_single()){
                
                $txt=strip_tags($txt);
                $this->jsonSend($txt);
            }else{
                
                parent::error($txt,$url,$data,$wait,$header);
            }
        }
    }
    
    
    public function clear_tags($tags){
        if(!is_array($tags)){
            $tags = preg_replace('/[\s\,\x{ff0c}]+/u', ',', $tags);
            $tags=explode(',', $tags);
        }
        if(!empty($tags)&&is_array($tags)){
            
            $tags=array_filter($tags);
            $tags=array_unique($tags);
            $tags=array_values($tags);
        }else{
            $tags=array();
        }
        return $tags;
    }
    
    
    public function set_config_common($config){
        
        if(!empty($config['field_list'])){
            
            foreach ($config['field_list'] as $k=>$v){
                $config['field_list'][$k]=json_decode(url_b64decode($v),true);
            }
        }
        if(!empty($config['field_process'])){
            
            foreach ($config['field_process'] as $k=>$v){
                $config['field_process'][$k]=json_decode(url_b64decode($v),true);
                $config['field_process'][$k]=$this->set_process($config['field_process'][$k]);
            }
        }
        $config['common_process']=\util\UnmaxPost::val('process/a',array(),null);
        $config['common_process']=$this->set_process($config['common_process']);
        return $config;
    }
    
    
    public function set_process($processList){
        if(is_array($processList)){
            $processList=trim_input_process(null,$processList);
            foreach ($processList as $k=>$v){
                init_array($v);
                $v['module']=strtolower($v['module']);
                if(!empty($v['title'])){
                    $v['title']=str_replace(array("'",'"'),'',strip_tags($v['title']));
                }
                if('html'==$v['module']){
                    $v['html_allow']=$this->clear_tags($v['html_allow']);
                    $v['html_allow']=implode(',', $v['html_allow']);
                    $v['html_filter']=$this->clear_tags($v['html_filter']);
                    $v['html_filter']=implode(',', $v['html_filter']);
                }elseif('filter'==$v['module']){
                    if(preg_match_all('/[^\r\n]+/', $v['filter_list'],$filterList)){
                        $filterList=array_filter(array_unique($filterList[0]));
                        $v['filter_list']=implode("\r\n",$filterList);
                    }
                    $v['filter_list']=trim($v['filter_list']);
                }elseif('api'==$v['module']){
                    
                    init_array($v['api_params']);
                    \util\Funcs::filter_key_val_list3($v['api_params']['name'],$v['api_params']['val'],$v['api_params']['addon']);
                    
                    init_array($v['api_headers']);
                    \util\Funcs::filter_key_val_list3($v['api_headers']['name'],$v['api_headers']['val'],$v['api_headers']['addon']);
                }elseif('tool'==$v['module']){
                    init_array($v['tool_list']);
                }elseif('if'==$v['module']){
                    init_array($v['if_addon']);
                    \util\Funcs::filter_key_val_list5($v['if_cond'],$v['if_logic'],$v['if_val'],$v['if_addon']['func'],$v['if_addon']['turn']);
                }elseif('download'==$v['module']){
                    $v['download_file_tag']=\skycaiji\admin\model\Config::process_tag_attr($v['download_file_tag']);
                }
                $processList[$k]=$v;
            }
            $processList=array_values($processList);
        }
        init_array($processList);
        return $processList;
    }
    
    
    public function init_config_common($config,$isPattern=false){
        
        if(!empty($config['field_list'])){
            foreach ($config['field_list'] as $fk=>$fv){
                if('rule'==$fv['module']){
                    
                    $fv=$this->convert_rule_module_config($fv);
                }elseif('extract'==$fv['module']){
                    
                    if(!empty($fv['extract_rule'])){
                        
                        $fv=$this->convert_rule_module_config($fv,'extract_');
                    }
                }
                $config['field_list'][$fk]=$fv;
            }
        }
        
        if(!empty($config['field_process'])){
            foreach ($config['field_process'] as $k=>$v){
                $config['field_process'][$k]=$this->init_process($v);
            }
        }
        
        if(!empty($config['common_process'])){
            $config['common_process']=$this->init_process($config['common_process']);
        }
        
        
        $module_normal_fields=array();
        $module_extract_fields=array();
        $module_merge_fields=array();
        if(!empty($config['field_list'])){
            foreach ($config['field_list'] as $fk=>$fv){
                $fieldModule=strtolower($fv['module']);
                $fieldConfig=array('field'=>$fv,'process'=>$config['field_process'][$fk]);
                if('extract'==$fieldModule){
                    
                    $module_extract_fields[$fv['name']]=$fieldConfig;
                }elseif('merge'==$fieldModule){
                    
                    $module_merge_fields[$fv['name']]=$fieldConfig;
                }else{
                    
                    $module_normal_fields[$fv['name']]=$fieldConfig;
                }
            }
        }
        
        $config['new_field_list']=\util\Funcs::array_key_merge($module_normal_fields, $module_extract_fields);
        $config['new_field_list']=\util\Funcs::array_key_merge($config['new_field_list'], $module_merge_fields);
        
        
        if($isPattern){
            
            if(!empty($config['pagination'])&&is_array($config['pagination']['fields'])){
                
                $new_pn_fields=array(
                    'normal'=>array(),
                    'extract'=>array(),
                    'merge'=>array(),
                );
                $pnFields=array();
                foreach ($config['pagination']['fields'] as $pnField){
                    
                    $pnFields[$pnField['field']]=$pnField;
                }
                if(!empty($pnFields['::all'])){
                    
                    $fieldAllParams=$pnFields['::all'];
                    unset($pnFields['::all']);
                    foreach ($config['new_field_list'] as $k=>$v){
                        
                        if(empty($pnFields[$k])){
                            
                            $fieldAllParams['field']=$k;
                            $pnFields[$k]=$fieldAllParams;
                        }
                    }
                }
                $config['pagination']['fields']=$pnFields;
                unset($pnFields);
                
                foreach ($config['pagination']['fields'] as $pfk=>$pnField){
                    $pnField['delimiter']=str_replace(array('\r','\n'), array("\r","\n"), $pnField['delimiter']);
                    $config['pagination']['fields'][$pfk]=$pnField;
                    if(!empty($module_normal_fields[$pnField['field']])){
                        
                        $new_pn_fields['normal'][$pnField['field']]=$pnField;
                    }elseif(!empty($module_extract_fields[$pnField['field']])){
                        
                        $new_pn_fields['extract'][$pnField['field']]=$pnField;
                    }elseif(!empty($module_merge_fields[$pnField['field']])){
                        
                        $new_pn_fields['merge'][$pnField['field']]=$pnField;
                    }
                }
                
                $config['pagination']['new_fields']=\util\Funcs::array_key_merge($new_pn_fields['normal'],$new_pn_fields['extract']);
                $config['pagination']['new_fields']=\util\Funcs::array_key_merge($config['pagination']['new_fields'],$new_pn_fields['merge']);
            }
        }
        
        return $config;
    }
    
    /*初始化数据处理，初始化config时使用*/
    public function init_process($processList){
        if(!empty($processList)){
            $processList=$this->set_process($processList);
            foreach ($processList as $k=>$v){
                if('replace'==$v['module']){
                    $v['replace_from']=$this->correct_reg_pattern($v['replace_from']);
                }elseif('download'==$v['module']){
                    $v['download_url_img_match']=$this->correct_reg_pattern($v['download_url_img_match']);
                    $index=0;
                    
                    $v['download_url_img_match']=preg_replace_callback('/\[\x{56fe}\x{7247}\x{94fe}\x{63a5}\]/u',function($match)use(&$index){
                        $index++;
                        return '(?P<url_img_'.$index.'>\bhttp[s]{0,1}.+?)';
                    }, $v['download_url_img_match']);
                        
                        $v['download_url_img_must']=$this->correct_reg_pattern($v['download_url_img_must']);
                        $v['download_url_img_ban']=$this->correct_reg_pattern($v['download_url_img_ban']);
                        
                        $v['download_url_file_match']=$this->correct_reg_pattern($v['download_url_file_match']);
                        $index=0;
                        
                        $v['download_url_file_match']=preg_replace_callback('/\[\x{6587}\x{4ef6}\x{94fe}\x{63a5}\]/u',function($match)use(&$index){
                            $index++;
                            return '(?P<url_file_'.$index.'>\bhttp[s]{0,1}.+?)';
                        }, $v['download_url_file_match']);
                            
                            $v['download_url_file_must']=$this->correct_reg_pattern($v['download_url_file_must']);
                            $v['download_url_file_ban']=$this->correct_reg_pattern($v['download_url_file_ban']);
                            
                            $v['download_file_must']=$this->correct_reg_pattern($v['download_file_must']);
                            $v['download_file_ban']=$this->correct_reg_pattern($v['download_file_ban']);
                }
                $processList[$k]=$v;
            }
        }
        return $processList;
    }
    
    /*转换配置中的正则规则*/
    public function convert_rule_module_config($ruleConfig,$prefix=''){
        $ruleConfig['reg_'.$prefix.'rule']=$this->convert_sign_match($ruleConfig[$prefix.'rule']);
        $ruleConfig['reg_'.$prefix.'rule']=$this->correct_reg_pattern($ruleConfig['reg_'.$prefix.'rule']);
        
        $ruleConfig['reg_'.$prefix.'rule_merge']=$this->set_merge_default($ruleConfig['reg_'.$prefix.'rule'], $ruleConfig[$prefix.'rule_merge']);
        if(empty($ruleConfig['reg_'.$prefix.'rule_merge'])){
            
            $ruleConfig['reg_'.$prefix.'rule_merge']=coll_sign('match');
        }
        return $ruleConfig;
    }
    
    /*转换[内容]标签*/
    public function convert_sign_match($str){
        $str=isset($str)?$str:'';
        if($str){
            $str=preg_replace('/\(\?<(content|match|nr)/i', '(?P<match', $str);
            $sign_match=$this->sign_addslashes(coll_sign('match',':id'));
            $str=preg_replace_callback('/(\={0,1})(\s*)([\'\"]{0,1})'.$sign_match.'\3/', function($matches){
                $ruleStr=$matches[1].$matches[2].$matches[3].'(?P<match'.$matches['id'].'>';
                if(!empty($matches[1])&&!empty($matches[3])){
                    
                    $ruleStr.='[^\<\>]*?)';
                }else{
                    $ruleStr.='[\s\S]*?)';
                }
                $ruleStr.=$matches[3];
                return $ruleStr;
            }, $str);
        }
        return $str;
    }
    /*修正规则中的正则表达式*/
    public function correct_reg_pattern($str){
        if(isset($str)){
            $str=preg_replace('/\\\*([\'\/])/', "\\\\$1",$str);
            $str=$this->convert_sign_wildcard($str);
        }else{
            $str='';
        }
        return $str;
    }
    /*转换(*)通配符*/
    public function convert_sign_wildcard($str){
        return str_replace(lang('sign_wildcard'), '[\s\S]*?', $str);
    }
    
    public function sign_addslashes($str){
        $str=str_replace(array('[',']'), array('\[','\]'), $str);
        return $str;
    }
    
    /**
     * 拼接默认设置
     * @param string $reg 规则
     * @param string $merge 拼接字符串
     */
    public function set_merge_default($reg,$merge){
        if(empty($merge)){
            $merge='';
            if(!empty($reg)){
                
                $merge=$this->rule_str_signs($reg);
                $merge=implode('', $merge);
            }
        }
        return $merge;
    }
    
    /*获取正则规则里的标签列表*/
    public function rule_str_signs($rule,$returnIds=false){
        $ruleSigns=array();
        if(!empty($rule)){
            static $rule_signs_list=array();
            $key=md5($rule);
            $ruleSigns=$rule_signs_list[$key];
            if(!isset($ruleSigns)){
                if(preg_match_all('/\<match(?P<id>\w*)\>/i', $rule, $ruleSigns)){
                    
                    foreach ($ruleSigns['id'] as $k=>$v){
                        $ruleSigns[0][$k]=coll_sign('match',$v);
                    }
                    $rule_signs_list[$key]=$ruleSigns;
                }else{
                    $rule_signs_list[$key]=array();
                }
            }
        }
        
        if(!$returnIds){
            
            if(is_array($ruleSigns[0])){
                $ruleSigns=$ruleSigns[0];
                $ruleSigns=array_unique($ruleSigns);
                $ruleSigns=array_values($ruleSigns);
            }else{
                $ruleSigns=array();
            }
            return $ruleSigns;
        }else{
            
            return $ruleSigns;
        }
        return $ruleSigns;
    }
    
    
    public function match_url_info($url,$html,$cacheKey=false){
        static $cacheList=array();
        $cacheMd5=null;
        $info=array();
        if($cacheKey){
            
            init_array($cacheList[$cacheKey]);
            $cacheMd5=md5($url);
            $info=$cacheList[$cacheKey][$cacheMd5];
        }
        if(empty($info)){
            
            $info=array('cur_url'=>$url,'url_no_name'=>$this->config['url_no_name']);
            $baseInfo=\util\Tools::match_base_url($url,$html,true);
            $info=array_merge($info,$baseInfo);
            $info['domain_url']=\util\Tools::match_domain_url($url);
            if($cacheKey){
                
                $cacheList[$cacheKey][$cacheMd5]=$info;
            }
        }
        init_array($info);
        return $info;
    }
    /*过滤html标签*/
    public function filter_html_tags($content,$tags){
        $tags=$this->clear_tags($tags);
        $arr1=$arr2=array();
        foreach ($tags as $tag){
            $tag=strtolower($tag);
            if($tag=='script'||$tag=='style'||$tag=='object'){
                $arr1[$tag]=$tag;
            }else{
                $arr2[$tag]=$tag;
            }
        }
        
        if($arr1){
            $content=preg_replace('/<('.implode('|', $arr1).')[^<>]*>[\s\S]*?<\/\1>/i', '', $content);
        }
        
        if($arr2){
            $content=preg_replace('/<[\/]*('.implode('|', $arr2).')[^<>]*>/i', '', $content);
        }
        return $content;
    }
    /*拼接替换标签*/
    public function merge_match_signs($matches,$merge){
        if(!is_array($matches)){
            
            $matches=array();
        }
        $val='';
        if(!empty($merge)){
            
            $mergeSigns=$this->merge_str_signs($merge,true);
            if(!empty($mergeSigns)){
                
                $signVals=array();
                foreach($mergeSigns['id'] as $k=>$v){
                    $signVals[$k]=isset($matches['match'.$v])?$matches['match'.$v]:'';
                }
                $val=str_replace($mergeSigns[0], $signVals, $merge);
            }else{
                
                $val=$merge;
            }
        }else{
            
            if(isset($merge)){
                
                $val=$merge;
            }
        }
        return $val;
    }
    
    /*获取拼接字符串中的标签*/
    public function merge_str_signs($merge,$returnIds=false){
        $mergeSigns=array();
        if(!empty($merge)){
            static $merge_signs_list=array();
            $key=md5($merge);
            $mergeSigns=$merge_signs_list[$key];
            if(!isset($mergeSigns)){
                
                $signMatch=$this->sign_addslashes(coll_sign('match',':id'));
                if(preg_match_all('/'.$signMatch.'/i',$merge,$mergeSigns)){
                    
                    $merge_signs_list[$key]=$mergeSigns;
                }else{
                    $merge_signs_list[$key]=array();
                }
            }
        }
        if(!$returnIds){
            
            if(is_array($mergeSigns[0])){
                $mergeSigns=$mergeSigns[0];
                $mergeSigns=array_unique($mergeSigns);
                $mergeSigns=array_values($mergeSigns);
            }else{
                $mergeSigns=array();
            }
            return $mergeSigns;
        }else{
            
            return $mergeSigns;
        }
    }
    
    
    /*正则规则匹配数据*/
    public function get_rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        if(!is_array($configParams)){
            $configParams=array();
        }
        $configParams['rule_flags']=$this->config['reg_regexp_flags'];
        
        return $this->rule_module_rule_data($configParams,$html,$parentMatches,$whole,$returnMatch);
    }
    
    public function rule_module_xpath_data($configParams,$html){
        $vals=array();
        $xpathMulti=$configParams['xpath_multi']?true:false;
        if(!empty($configParams['xpath'])){
            $html=$this->filter_html_tags($html,array('script'));
            $dom=new \DOMDocument;
            $libxml_previous_state = libxml_use_internal_errors(true);
            @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html;charset=utf-8">'.$html);
            
            $dom->normalize();
            
            $xPath = new \DOMXPath($dom);
            
            $xpath_attr=strtolower($configParams['xpath_attr']);
            $xpath_attr='custom'==$xpath_attr?strtolower($configParams['xpath_attr_custom']):$xpath_attr;
            
            $normal_attr=true;
            if(in_array($xpath_attr,array('innerhtml','outerhtml','text'))){
                
                $normal_attr=false;
            }
            $xpath_q=trim($configParams['xpath']);
            if(!empty($xpath_attr)){
                
                if(preg_match('/\/\@[\w\-]+$/', $xpath_q)){
                    
                    $xpath_q=preg_replace('/\@[\w\-]+$/', '', $xpath_q);
                }
                if($normal_attr){
                    
                    $xpath_q=$xpath_q.(preg_match('/\/$/', $xpath_q)?'':'/').'@'.$xpath_attr;
                }
            }else{
                
                if(!preg_match('/\/\@[\w\-]+$/', $xpath_q)){
                    
                    $xpath_attr='innerhtml';
                    $normal_attr=false;
                }
            }
            
            $nodes = $xPath->query($xpath_q);
            
            foreach ($nodes as $node){
                $val='';
                if($normal_attr){
                    
                    $val.=$node->nodeValue;
                }else{
                    
                    switch ($xpath_attr){
                        case 'innerhtml':
                            $nchilds  = $node->childNodes;
                            foreach ($nchilds as $nchild){
                                $val .= $nchild->ownerDocument->saveHTML($nchild);
                            }
                            break;
                        case 'outerhtml':$val.=$node->ownerDocument->saveHTML($node);break;
                        case 'text':
                            
                            
                            $nchilds  = $node->childNodes;
                            foreach ($nchilds as $nchild){
                                $val .= $nchild->ownerDocument->saveHTML($nchild);
                            }
                            $val=$this->filter_html_tags($val, array('style','script','object'));
                            $val=strip_tags($val);
                            break;
                    }
                }
                
                if($xpathMulti){
                    
                    $vals[]=$val;
                }else{
                    
                    $vals=$val;
                    break;
                }
            }
            
            libxml_clear_errors();
            
        }
        
        if($xpathMulti){
            
            init_array($vals);
            if($configParams['xpath_multi_type']!='loop'){
                
                if($configParams['xpath_multi_type']=='list'){
                    
                    $vals=json_encode($vals);
                }else{
                    
                    $multiStr=$configParams['xpath_multi_str'];
                    if(!empty($multiStr)){
                        $multiStr=str_replace(array('\r','\n'), array("\r","\n"), $multiStr);
                    }
                    $vals=implode($multiStr, $vals);
                }
            }
        }else{
            
            if(is_array($vals)){
                $vals=is_empty($vals[0],true)?'':$vals[0];
            }
        }
        return $vals;
    }
    
    public function rule_module_json_data($configParams,$jsonArrOrStr,$isSub=false,&$mergeData=null){
        $jsonArr=array();
        if(is_array($jsonArrOrStr)){
            $jsonArr=&$jsonArrOrStr;
        }else{
            
            $jsonArr=\util\Funcs::convert_html2json($jsonArrOrStr);
            unset($jsonArrOrStr);
        }
        
        if(!$isSub){
            
            $mergeData=array();
        }
        
        $val='';
        if(!empty($jsonArr)){
            if(!empty($configParams['json'])){
                
                $jsonFmt=str_replace(array('"',"'",'[',' '), '', $configParams['json']);
                $jsonFmt=str_replace(']','.',$jsonFmt);
                $jsonFmt=trim($jsonFmt,'.');
                $jsonFmt=explode('.', $jsonFmt);
                $jsonFmt=array_values($jsonFmt);
                if(!empty($jsonFmt)){
                    
                    $val=$jsonArr;
                    $prevKey='';
                    foreach ($jsonFmt as $i=>$key){
                        if($prevKey=='*'){
                            
                            $newConfigParams=$configParams;
                            $newConfigParams['json']=array_slice($jsonFmt, $i);
                            $newConfigParams['json']=implode('.', $newConfigParams['json']);
                            init_array($val);
                            foreach ($val as $vk=>$vv){
                                
                                $val[$vk]=$this->rule_module_json_data($newConfigParams,$vv,true,$mergeData);
                            }
                            break;
                        }else{
                            if($key!='*'){
                                
                                if(!is_array($val)){
                                    
                                    $val=\util\Funcs::convert_html2json($val);
                                }
                                $val=is_array($val)?$val[$key]:'';
                            }
                            if(!empty($configParams['json_merge_data'])){
                                
                                if(!isset($jsonFmt[$i+1])){
                                    
                                    if($configParams['json_url_merge_data']){
                                        
                                        if(is_array($val)){
                                            
                                            foreach ($val as $vv){
                                                $mergeData[]=$vv;
                                            }
                                        }else{
                                            $mergeData[]=$val;
                                        }
                                    }else{
                                        $mergeData[]=$val;
                                    }
                                }
                            }
                        }
                        
                        $prevKey=$key;
                    }
                }
            }
        }
        if($isSub){
            
            return $val;
        }else{
            if(!empty($configParams['json_merge_data'])){
                
                $val=$mergeData;
            }
            return $this->rule_module_json_data_convert($val, $configParams);
        }
    }
    public function rule_module_json_data_convert($val,$configParams){
        if(is_array($val)){
            
            $json_arr=strtolower($configParams['json_arr']);
            if(empty($json_arr)){
                $json_arr='implode';
            }
            switch ($json_arr){
                case 'implode':$arrImplode=str_replace(array('\r','\n'), array("\r","\n"), $configParams['json_arr_implode']);$val=\util\Funcs::array_implode($arrImplode,$val);break;
                case 'jsonencode':$val=json_encode($val);break;
                case 'serialize':$val=serialize($val);break;
                case '_original_': break;
            }
        }
        return $val;
    }
    /*正则规则匹配数据*/
    public function rule_module_rule_data($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        $val=null;
        $matches=array();
        if(!is_array($parentMatches)){
            $parentMatches=array();
        }
        if(!empty($configParams['rule'])&&!empty($configParams['rule_merge'])){
            
            if(empty($configParams['rule_flags'])){
                $configParams['rule_flags']='';
            }
            
            $ruleSigns=$this->rule_str_signs($configParams['rule']);
            
            if(!empty($configParams['rule_multi'])){
                
                if(preg_match_all('/'.$configParams['rule'].'/'.$configParams['rule_flags'],$html,$matchConts,PREG_SET_ORDER)){
                    if(empty($ruleSigns)){
                        
                        if($whole){
                            
                            foreach ($matchConts as $k=>$v){
                                $v['match']=$v[0];
                                $matchConts[$k]=$v;
                            }
                        }else{
                            
                            $matchConts=array();
                        }
                    }
                    foreach ($matchConts as $k=>$v){
                        
                        foreach ($v as $vk=>$vv){
                            if(stripos($vk,'match')!==0){
                                unset($v[$vk]);
                            }
                        }
                        if($returnMatch){
                            
                            $matches[$k]=$v;
                        }
                        if(!empty($parentMatches)){
                            
                            $v=array_merge($parentMatches,$v);
                        }
                        $matchConts[$k]=$this->merge_match_signs($v,$configParams['rule_merge']);
                    }
                    if($configParams['rule_multi_type']=='loop'){
                        
                        $val=$matchConts;
                    }elseif($configParams['rule_multi_type']=='list'){
                        
                        $val=json_encode($matchConts);
                    }else{
                        
                        $multiStr=$configParams['rule_multi_str'];
                        if(!empty($multiStr)){
                            $multiStr=str_replace(array('\r','\n'), array("\r","\n"), $multiStr);
                        }
                        $val=implode($multiStr, $matchConts);
                    }
                }
                if($configParams['rule_multi_type']=='loop'){
                    
                    init_array($val);
                }
                
            }else{
                
                if(preg_match('/'.$configParams['rule'].'/'.$configParams['rule_flags'],$html,$matchCont)){
                    if(empty($ruleSigns)){
                        
                        if($whole){
                            
                            
                            $matchCont['match']=$matchCont[0];
                        }else{
                            
                            $matchCont=array();
                        }
                    }
                    if(!empty($matchCont)){
                        
                        if(!empty($parentMatches)){
                            
                            
                            foreach ($matchCont as $k=>$v){
                                if(stripos($k,'match')!==0){
                                    unset($matchCont[$k]);
                                }
                            }
                            $parentMatches=array_merge($parentMatches,$matchCont);
                            $val=$this->merge_match_signs($parentMatches,$configParams['rule_merge']);
                        }else{
                            $val=$this->merge_match_signs($matchCont,$configParams['rule_merge']);
                        }
                    }
                }else{
                    
                    $matchCont=array();
                }
                if($returnMatch){
                    
                    $matches=$matchCont;
                }
            }
        }
        if($returnMatch){
            return array('val'=>$val,'matches'=>$matches);
        }else{
            return $val;
        }
    }
    
    public function rule_module_rule_data_get($configParams,$html,$parentMatches=array(),$whole=false,$returnMatch=false){
        
        init_array($configParams);
        $rule=$this->convert_sign_match($configParams['rule']);
        $rule=$this->correct_reg_pattern($rule);
        
        $ruleMerge=$this->set_merge_default($rule, $configParams['rule_merge']);
        if(empty($ruleMerge)){
            
            $ruleMerge=coll_sign('match');
        }
        $configParams['rule']=$rule;
        $configParams['rule_merge']=$ruleMerge;
        
        return $this->rule_module_rule_data($configParams,$html,$parentMatches,$whole,$returnMatch);
    }
    
    
    public function field_module_merge($field_params,$val_list){
        $val='';
        
        if(preg_match_all('/\[\x{5b57}\x{6bb5}\:(.+?)\]/u', $field_params['merge'],$match_fields)){
            $val=$field_params['merge'];
            
            for($i=0;$i<count($match_fields[0]);$i++){
                $field=$match_fields[1][$i];
                if(is_array($val_list[$field])&&isset($val_list[$field]['value'])){
                    $val=str_replace($match_fields[0][$i],$val_list[$field]['value'],$val);
                }
            }
        }
        return $val;
    }
    /*字段提取内容*/
    public function field_module_extract($field_params,$extract_field_val,$url_info){
        $field_html=$extract_field_val['value'];
        if(empty($field_html)){
            return '';
        }
        $val='';
        $extract_module=strtolower($field_params['extract_module']);
        switch ($extract_module){
            case 'cover':
                
                if(!empty($extract_field_val['img'])){
                    $val=reset($extract_field_val['img']);
                }else{
                    if($url_info){
                        if(preg_match('/<img\b[^<>]*\bsrc\s*=\s*[\'\"](?P<url>[^\'\"]+?)[\'\"]/i',$field_html,$cover)){
                            $cover=$cover['url'];
                            $cover=\util\Tools::create_complete_url($cover, $url_info);
                            $val=$cover;
                        }
                    }
                }
                break;
            case 'phone':
                $field_html=$this->filter_html_tags($field_html,'style,script,object');
                $field_html=strip_tags($field_html);
                if(preg_match('/\d{11}/', $field_html,$phone)){
                    $val=$phone[0];
                }
                break;
            case 'email':
                $field_html=$this->filter_html_tags($field_html,'style,script,object');
                $field_html=strip_tags($field_html);
                if(preg_match('/[\w\-]+\@[\w\-\.]+/i', $field_html,$email)){
                    $val=$email[0];
                }
                break;
            case 'rule':
                
                $field_params['reg_rule']=$field_params['reg_extract_rule'];
                $field_params['reg_rule_merge']=$field_params['reg_extract_rule_merge'];
                $field_params['rule_multi']=$field_params['extract_rule_multi'];
                $field_params['rule_multi_str']=$field_params['extract_rule_multi_str'];
                $field_params['rule_multi_type']=$field_params['extract_rule_multi_type'];
                $val = $this->field_module_rule($field_params, $field_html);
                break;
            case 'xpath':
                $field_params['xpath']=$field_params['extract_xpath'];
                $field_params['xpath_attr']=$field_params['extract_xpath_attr'];
                $field_params['xpath_attr_custom']=$field_params['extract_xpath_attr_custom'];
                $field_params['xpath_multi']=$field_params['extract_xpath_multi'];
                $field_params['xpath_multi_str']=$field_params['extract_xpath_multi_str'];
                $field_params['xpath_multi_type']=$field_params['extract_xpath_multi_type'];
                $val = $this->field_module_xpath($field_params, $field_html);
                break;
            case 'json':
                $field_params['json']=$field_params['extract_json'];
                $field_params['json_merge_data']=$field_params['extract_json_merge_data'];
                $field_params['json_loop']=$field_params['extract_json_loop'];
                $field_params['json_arr']=$field_params['extract_json_arr'];
                $field_params['json_arr_implode']=$field_params['extract_json_arr_implode'];
                $val = $this->field_module_json($field_params, $field_html);
                break;
        }
        return $val;
    }
    /**
    * 规则匹配，$field_params传入规则参数
    * @param array $field_params
    * @param string $html
    * @return string
    */
    public function field_module_rule($field_params,$html){
        if(!empty($field_params['rule_multi'])&&'loop'==$field_params['rule_multi_type']){
            
            if(empty($this->first_loop_field)){
                
                $this->first_loop_field=$field_params['name'];
            }
        }
        
        $val = $this->get_rule_module_rule_data(array(
            'rule' => $field_params['reg_rule'],
            'rule_merge' => $field_params['reg_rule_merge'],
            'rule_multi' => $field_params['rule_multi'],
            'rule_multi_str' => $field_params['rule_multi_str'],
            'rule_multi_type' => $field_params['rule_multi_type']
        ), $html,array(),true);
        
        $val=is_array($val)?array_values($val):$val;
        return $val;
    }
    /**
     * xpath规则，$field_params传入规则参数
     * @param array $field_params
     * @param string $html
     * @return string
     */
    public function field_module_xpath($field_params,$html){
        if(!empty($field_params['xpath_multi'])){
            
            if('loop'==$field_params['xpath_multi_type']){
                
                if(empty($this->first_loop_field)){
                    
                    $this->first_loop_field=$field_params['name'];
                }
            }
        }
        $val=$this->rule_module_xpath_data($field_params,$html);
        $val=is_array($val)?array_values($val):$val;
        return $val;
    }
    /**
     * json提取，$field_params传入规则参数
     * @param array $field_params
     * @param string $html
     * @return string
     */
    private $cache_json_list=array();
    public function field_module_json($field_params,$html,$cur_url=''){
        $jsonKey=!empty($cur_url)?md5($cur_url):md5($html);
        if(!isset($this->cache_json_list[$jsonKey])){
            $this->cache_json_list[$jsonKey]=\util\Funcs::convert_html2json($html);
        }
        $jsonArrType=$field_params['json_arr'];
        if($field_params['json_loop']){
            
            $field_params['json_arr']='_original_';
        }
        $val=$this->rule_module_json_data($field_params,$this->cache_json_list[$jsonKey]);
        if($field_params['json_loop']){
            
            if(is_array($val)){
                $field_params['json_arr']=$jsonArrType;
                foreach ($val as $k=>$v){
                    $val[$k]=$this->rule_module_json_data_convert($v,$field_params);
                }
                
                if(empty($this->first_loop_field)){
                    
                    $this->first_loop_field=$field_params['name'];
                }
            }
        }
        
        $val=is_array($val)?array_values($val):$val;
        return $val;
    }
    public function field_module_words($field_params){
        
        return $field_params['words'];
    }
    public function field_module_num($field_params){
        
        $start=intval($field_params['num_start']);
        $end=intval($field_params['num_end']);
        return rand($start, $end);
    }
    private $f_m_no_tid;
    public function field_module_no($field_params){
        
        static $num=0;
        $mcache=\skycaiji\admin\model\CacheModel::getInstance();
        $ckey='taskFNo_';
        if(empty($this->f_m_no_tid)||$this->f_m_no_tid!=$this->task_id){
            
            $this->f_m_no_tid=$this->task_id;
            $ckey.=$this->f_m_no_tid.'_'.$field_params['name'];
            $cacheData=$mcache->getCache($ckey,'data');
            if($cacheData){
                
                $num=intval($cacheData);
            }else{
                $num=intval($field_params['no_start']);
            }
        }else{
            $ckey.=$this->f_m_no_tid.'_'.$field_params['name'];
        }
        
        $num=$num?:1;
        
        $numStr=$num;
        
        $field_params['no_len']=intval($field_params['no_len']);
        if($field_params['no_len']>0){
            
            $numStr=abs($num).'';
            $numStr=str_pad($numStr,$field_params['no_len'],'0',STR_PAD_LEFT);
            $numStr=($num>=0?'':'-').$numStr;
        }
        
        
        $field_params['no_inc']=intval($field_params['no_inc']);
        $num+=$field_params['no_inc']?:1;
        
        if($this->is_collecting()){
            
            $mcache->setCache($ckey,$num);
        }
        
        return $numStr;
    }
    public function field_module_time($field_params){
        $val='';
        $nowTime=time();
        $start=empty($field_params['time_start'])?$nowTime:strtotime($field_params['time_start']);
        $end=empty($field_params['time_end'])?$nowTime:strtotime($field_params['time_end']);
        $time=rand($start, $end);
        if(empty($field_params['time_stamp'])){
            
            $fmt=empty($field_params['time_format'])?'Y-m-d H:i':
            str_replace(array('[年]','[月]','[日]','[时]','[分]','[秒]'), array('Y','m','d','H','i','s'), $field_params['time_format']);
            $val=date($fmt,$time);
        }else{
            $val=$time;
        }
        return $val;
    }
    public function field_module_list($field_params){
        static $list=array();
        $key=md5($field_params['list']);
        if(!isset($list[$key])){
            
            if(preg_match_all('/[^\r\n]+/', $field_params['list'],$strList)){
                $strList=$strList[0];
            }
            init_array($strList);
            $list[$key]=$strList;
        }
        $strList=$list[$key];
        $val='';
        if(!empty($strList)){
            if(empty($field_params['list_type'])){
                
                $randi=array_rand($strList,1);
                $val=$strList[$randi];
            }else{
                static $keyIndexs=array();
                $isAsc=$field_params['list_type']=='asc'?true:false;
                $endIndex=count($strList)-1;
                
                if(isset($keyIndexs[$key])){
                    
                    $curIndex=intval($keyIndexs[$key]);
                }else{
                    
                    $curIndex=$isAsc?0:$endIndex;
                }
                if($isAsc){
                    
                    if($curIndex>$endIndex){
                        
                        $curIndex=0;
                    }
                    $val=$strList[$curIndex];
                    $curIndex++;
                }else{
                    
                    if($curIndex<0){
                        
                        $curIndex=$endIndex;
                    }
                    $val=$strList[$curIndex];
                    $curIndex--;
                }
                $keyIndexs[$key]=$curIndex;
            }
        }
        return $val;
    }
    
    private function _replace_insert_fields($paramsStr,$defaultVal,$curUrlMd5,$loopIndex){
        $fieldRule='/\[\x{5b57}\x{6bb5}\:(.+?)\]/u';
        $fieldVals=$this->_get_insert_fields($paramsStr, $curUrlMd5, $loopIndex);
        return \util\Funcs::txt_replace_params(false, false, $paramsStr, $defaultVal, $fieldRule, $fieldVals);
    }
    
    private function _get_insert_fields($paramsStr,$curUrlMd5,$loopIndex){
        $fieldRule='/\[\x{5b57}\x{6bb5}\:(.+?)\]/u';
        $fields=array();
        if($paramsStr){
            
            $fields=\util\Funcs::txt_match_params($paramsStr,$fieldRule,1);
        }
        init_array($fields);
        $fieldVals=array();
        if(!empty($fields)){
            if(empty($this->first_loop_field)){
                
                foreach ($fields as $field){
                    if(is_array($this->field_val_list[$field])){
                        $fieldVals['[字段:'.$field.']']=$this->field_val_list[$field]['values'][$curUrlMd5];
                    }
                }
            }else{
                
                foreach ($fields as $field){
                    $fieldVal=$this->field_val_list[$field];
                    if(is_array($fieldVal)){
                        $fieldVals['[字段:'.$field.']']=is_array($fieldVal['values'][$curUrlMd5])?$fieldVal['values'][$curUrlMd5][$loopIndex]:$fieldVal['values'][$curUrlMd5];
                    }
                }
            }
        }
        return $fieldVals;
    }
    
    private function _execute_translate($q,$from,$to,$fieldParams){
        static $retryCur=0;
        $transConf=g_sc_c('translate');
        init_array($transConf);
        $transConf['interval']=intval($transConf['interval']);
        $transConf['wait']=intval($transConf['wait']);
        $transConf['retry']=intval($transConf['retry']);
        
        $retryMax=$transConf['retry'];
        $retryParams=null;
        if($retryMax>0){
            
            $retryParams=array(0=>$q,1=>$from,2=>$to,3=>$fieldParams);
        }
        
        $result=\util\Translator::translate($q, $from, $to,true);
        
        if(is_array($result)){
            
            
            $this->collect_sleep($this->task_id,$transConf['interval'],true);
            
            if(!empty($result['success'])){
                
                $retryCur=0;
                $result=$result['data'];
            }else{
                
                $tips=($result['error']?('：'.$result['error']):'');
                
                $this->retry_first_echo($retryCur,'数据处理»翻译失败'.$tips);
                
                $this->collect_sleep($this->task_id,$transConf['wait']);
                
                $doStatus=$this->retry_do_func($retryCur,$retryMax,'翻译无效');
                if($doStatus){
                    
                    return $this->_execute_translate($retryParams[0],$retryParams[1],$retryParams[2],$retryParams[3]);
                }else{
                    
                    if($doStatus===0){
                        $this->echo_error('翻译无效');
                    }
                    
                    $this->set_exclude_cont_url($fieldParams['ct'], $fieldParams['cr'], $fieldParams['li'], array('field'=>$fieldParams['f'],'type'=>'translate','msg'=>'数据处理»翻译失败'));
                }
                
                $result='';
            }
        }
        return $result;
    }
    
    public function get_config($key1,$key2=null,$key3=null){
        $keys=array($key1);
        if(isset($key2)){
            $keys[]=$key2;
            if(isset($key3)){
                $keys[]=$key3;
            }
        }
        return \util\Funcs::array_get($this->config, $keys);
    }
    /*起始页设为了内容页*/
    public function source_is_url(){
        return $this->get_config('source_is_url')?true:false;
    }
    /*获取页面配置*/
    public function get_page_config($pageType,$pageName='',$prop=null){
        $pageName=$pageName?$pageName:'';
        if($pageType=='source_url'){
            
            if($this->source_is_url()){
                $pageType='url';
            }
        }
        $key1=null;
        $key2=null;
        $key3=null;
        switch ($pageType){
            case 'front_url':$key1='new_front_urls';$key2=$pageName;$key3=$prop;break;
            case 'source_url':$key1='source_config';$key2=$prop;$key3=null;break;
            case 'url':
                if(!isset($prop)){
                    
                    return $this->config;
                }else{
                    $key1=$prop;
                    $key2=null;
                    $key3=null;
                }
                break;
            case 'level_url':$key1='new_level_urls';$key2=$pageName;$key3=$prop;break;
            case 'relation_url':$key1='new_relation_urls';$key2=$pageName;$key3=$prop;break;
            default:return null;break;
        }
        return $this->get_config($key1,$key2,$key3);
    }
    
    public function pagination_renderer_opened($paginationConfig){
        
        $opened=null;
        if(!empty($paginationConfig)&&is_array($paginationConfig)&&is_array($paginationConfig['renderer'])&&$paginationConfig['renderer']['open_pn']){
            
            $opened=$this->get_config('page_render');
            if($paginationConfig['renderer']['open']){
                
                $opened=$paginationConfig['renderer']['open']=='y'?true:false;
            }
        }
        return $opened;
    }
    
    public function renderer_is_open($pageType,$pageName='',$rendererConfig=null,$paginationConfig=null,$onlyUseRenderer=false){
        $opened=$this->get_config('page_render');
        if($pageType){
            
            $rendererConfig=$this->get_page_config($pageType,$pageName,'renderer');
            if($paginationConfig){
                
                $paginationConfig=$this->get_page_config($pageType,$pageName,'pagination');
            }
        }
        
        if(!empty($paginationConfig)&&is_array($paginationConfig)&&$paginationConfig['use_renderer']){
            
            $opened=$paginationConfig['use_renderer']=='y'?true:false;
        }else{
            if(!empty($rendererConfig)&&is_array($rendererConfig)&&$rendererConfig['open']){
                
                $opened=$rendererConfig['open']=='y'?true:false;
            }
        }
        if(!$onlyUseRenderer){
            
            $pnOpened=$this->pagination_renderer_opened($paginationConfig);
            if(isset($pnOpened)){
                
                $opened=$pnOpened;
            }
        }
        return $opened;
    }
    
    
    /**
     * 获取源码
     * @param string $url 网址
     * @param bool|array $postData post数据
     * @param array $headers 请求头信息
     * @param string $charset 网页编码
     * @param array $otherConfig 其他配置
     * @param string $returnInfo 返回数据信息
     * @return string|array
     */
    public function get_html($url,$postData=false,$headers=array(),$charset=null,$otherConfig=array(),$returnInfo=false){
        static $retryCur=0;
        $retryMax=intval(g_sc_c('caiji','retry'));
        $retryParams=null;
        if($retryMax>0){
            
            $retryParams=array(0=>$url,1=>$postData,2=>$headers,3=>$charset,4=>$otherConfig,5=>$returnInfo);
        }
        
        if(!\util\Funcs::is_right_url($url)){
            $this->echo_error('网址缺少http(s)前缀：'.htmlspecialchars($url));
            return null;
        }
        
        $pageOpened='';
        if(isset($postData)&&$postData!==false){
            
            $pageOpened.='[post] ';
        }
        
        if(empty($charset)){
            
            $charset=$this->config['charset'];
        }
        $pageRenderTool=null;
        if($this->renderer_is_open(null,null,$otherConfig['renderer'])){
            $pageRenderTool=g_sc_c('page_render','tool');
            if(empty($pageRenderTool)){
                
                $this->echo_error('页面渲染未设置，请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>','setting/page_render');
                return null;
            }
            $pageOpened.='[渲染] ';
        }
        $htmlInfo=array();
        $html=null;
        $options=array();
        
        if(empty($headers)||!is_array($headers)){
            $headers=array();
        }else{
            $hdUseragent=\util\Funcs::array_val_in_keys($headers,array('useragent','user-agent'),true);
            if($hdUseragent){
                $options['useragent']=$hdUseragent;
            }
            $hdCookie=\util\Funcs::array_val_in_keys($headers,array('cookie'),true);
            if(isset($hdCookie)){
                $headers['cookie']=$hdCookie;
            }
        }
        $mproxy=model('ProxyIp');
        $proxyDbIp=null;
        if(!is_empty(g_sc_c('proxy','open'))){
            
            $proxyDbIp=$mproxy->get_usable_ip();
            $proxyIp=$mproxy->to_proxy_ip($proxyDbIp);
            if(empty($proxyIp)){
                
                $this->echo_error('没有可用的代理IP');
                return null;
            }else{
                $options['proxy']=$proxyIp;
            }
        }
        
        if(!is_empty(g_sc_c('caiji','robots'))){
            
            if(!$this->abide_by_robots($url,$options)){
                $this->echo_error('robots拒绝访问的网址：'.htmlspecialchars($url));
                return null;
            }
        }
        
        if($pageRenderTool){
            
            if($pageRenderTool=='chrome'){
                try {
                    $options['renderer']=$otherConfig['renderer'];
                    
                    $chromeSocket=null;
                    if($otherConfig['render_pn_page_source']&&$this->render_pn_sockets[$otherConfig['render_pn_page_source']]){
                        
                        $chromeSocket=$this->render_pn_sockets[$otherConfig['render_pn_page_source']];
                        if($chromeSocket->hasTab($chromeSocket->getTabId())){
                            
                            $options['render_pn_renderer']=true;
                        }else{
                            
                            $chromeSocket->newTab($options['proxy']);
                        }
                    }else{
                        $chromeConfig=g_sc_c('page_render','chrome');
                        init_array($chromeConfig);
                        $chromeSocket=new \util\ChromeSocket($chromeConfig['host'],$chromeConfig['port'],g_sc_c('page_render','timeout'),$chromeConfig['filename'],$chromeConfig);
                        $chromeSocket->newTab($options['proxy']);
                        $chromeSocket->websocket(null);
                        if($otherConfig['render_pn_page_source']){
                            
                            $this->render_pn_sockets[$otherConfig['render_pn_page_source']]=$chromeSocket;
                        }
                    }
                    $htmlInfo=$chromeSocket->getRenderHtml($url,$headers,$options,$charset,$postData,true);
                }catch (\Exception $ex){
                    $ex='页面渲染失败：'.$ex->getMessage().' 请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>';
                    if(!is_empty(g_sc_c('proxy','open'))){
                        
                        $ex.=' <a href="'.(is_empty(g_sc('c_original','proxy','open'))?url('admin/task/set?id='.$this->task_id):url('setting/proxy')).'" target="_blank">代理设置</a>';
                    }
                    $this->echo_error($ex);
                    return null;
                }
            }else{
                $this->echo_error('渲染工具不可用，请检查<a href="'.url('setting/page_render').'" target="_blank">渲染设置</a>','setting/page_render');
                return null;
            }
        }else{
            $options['curlopts']=$otherConfig['curlopts'];
            if(isset($otherConfig['return_head'])){
                $options['return_head']=$otherConfig['return_head'];
            }
            if(isset($otherConfig['return_info'])){
                $options['return_info']=$otherConfig['return_info'];
            }
            init_array($options['curlopts']);
            
            $options['max_redirs']=g_sc_c('caiji','max_redirs');
            $htmlInfo=get_html($url,$headers,$options,$charset,$postData,true);
        }
        init_array($htmlInfo);
        $html=$htmlInfo['html'];
        if((empty($html)&&empty($options['return_head']))||!$htmlInfo['ok']){
            
            if(!empty($proxyDbIp)){
                $this->echo_msg(array('代理IP：%s',$proxyDbIp['ip']),'black',true,'','display:inline;margin-right:5px;');
            }
            
            $this->retry_first_echo($retryCur,'访问网址失败',$url,$htmlInfo);
            
            
            if(!empty($proxyDbIp)){
                if($htmlInfo['code']!=404){
                    
                    $mproxy->set_ip_failed($proxyDbIp);
                }
            }
            
            $caijiWait=g_sc_c('caiji','wait');
            if($caijiWait){
                $this->collect_sleep($this->task_id,$caijiWait);
            }else{
                $this->collect_stopped($this->task_id,10);
            }
            
            if($this->retry_do_func($retryCur,$retryMax,'网址无效')){
                return $this->get_html($retryParams[0],$retryParams[1],$retryParams[2],$retryParams[3],$retryParams[4],$retryParams[5]);
            }
            
            return $returnInfo?$htmlInfo:null;
        }
        $retryCur=0;
        
        if($this->config['url_complete']&&$html){
            
            $url_info=$this->match_url_info($url,$html);
            
            $html=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                
                $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                return $matche[1].$matche[2].$matche[3];
            },$html);
                $html=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                    $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                    return $matche[1].$matche[2].$matche[3];
                },$html);
        }
        if($returnInfo){
            $htmlInfo['html']=$html;
            $htmlInfo['cookie']='';
            $htmlInfo['cookie_data']=\util\Funcs::get_cookies_from_header('cookie:'.$headers['cookie']."\r\n".$htmlInfo['header']);
            if($htmlInfo['cookie_data']){
                foreach ($htmlInfo['cookie_data'] as $k=>$v){
                    $htmlInfo['cookie'].=$k.'='.$v.';';
                }
            }
            return $htmlInfo;
        }else{
            return $html;
        }
    }
    
    
    /**
     * 执行数据处理»接口函数
     * @param string $module 模块
     * @param string $appName 接口app
     * @param string $fieldVal 字段值
     * @param string $appConfig 接口配置
     * @param array $paramValList 需要替换的数据列表
     * @param string $errorTips 错误提示信息
     * @param bool $returnAll 返回所有信息
     */
    public function execute_plugin_apiapp($module,$appName,$fieldVal,$appConfig,$paramValList=null,$errorTips=null,$returnAll=false){
        $return=model('ApiApp')->execute_app($module,$appName,$fieldVal,$appConfig,$paramValList,false,$errorTips);
        if(empty($return['success'])&&!empty($return['msg'])){
            
            $errorTips=$errorTips?$errorTips:'';
            $return['msg']=htmlspecialchars($return['msg'].$errorTips);
            $this->echo_error($return['msg']);
        }
        if($returnAll){
            return $return;
        }else{
            return $return['data'];
        }
    }
    /**
     * 执行数据处理»使用函数
     * @param string $module 模块
     * @param string $funcName 函数/方法
     * @param string $defaultVal 默认值
     * @param string $paramsStr 输入的参数（有换行符）
     * @param array $paramValList 需要替换的数据列表
     * @param string $errorTips 错误提示信息
     */
    public function execute_plugin_func($module,$funcName,$defaultVal,$paramsStr,$paramValList=null,$errorTips=null,$returnAll=false){
        $return=model('FuncApp')->execute_func($module,$funcName,$defaultVal,$paramsStr,$paramValList,$errorTips);
        if(empty($return['success'])&&!empty($return['msg'])){
            
            $errorTips=$errorTips?$errorTips:'';
            $return['msg']=htmlspecialchars($return['msg'].$errorTips);
            $this->echo_error($return['msg']);
        }
        if($returnAll){
            return $return;
        }else{
            return $return['data'];
        }
    }
    
    
    public function set_exclude_cont_url($contUrlMd5,$curUrlMd5,$loopIndex,$excludeData){
        if(!isset($this->exclude_cont_urls[$contUrlMd5])){
            $this->exclude_cont_urls[$contUrlMd5]=array();
        }
        init_array($excludeData);
        $excludeData=json_encode($excludeData);
        if(empty($this->first_loop_field)){
            
            $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=$excludeData;
        }else{
            
            if(!isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
                $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5]=array();
            }
            $this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex]=$excludeData;
        }
    }
    
    /*排除内容网址的提示信息*/
    public function exclude_url_msg($val){
        try{
            $val=json_decode($val,true);
        }catch (\Exception $ex){
            $val=array();
        }
        if(!is_array($val)){
            $val=array();
        }
        $type=$val['type'];
        $msg='排除网址';
        if($type=='filter'){
            
            if(empty($val['filter'])){
                $msg='字段:'.$val['field'].'»关键词过滤:未检测到关键词';
            }else{
                $msg='字段:'.$val['field'].'»关键词过滤:'.$val['filter'];
            }
        }elseif($type=='if'){
            $msg='字段:'.$val['field'].'»条件';
            
            switch ($val['if']){
                case '1':$msg.='假';break;
                case '2':$msg.='真';break;
                case '3':$msg.='假';break;
                case '4':$msg.='真';break;
            }
            $msg.='(';
            if(lang('?p_m_if_'.$val['if'])){
                $msg.=lang('p_m_if_'.$val['if']);
            }
            if(!empty($val['cond'])){
                $msg.='»'.$val['cond'];
            }
            $msg.=')';
        }elseif(in_array($type,array('func','api','apiapp','translate'))){
            $msg='[字段:'.$val['field'].'] '.$val['msg'];
        }
        return $msg;
    }
    
    /*数据处理*/
    public function process_field($fieldName,$fieldVal,$process,$curUrlMd5,$loopIndex,$contUrlMd5,$urlInfo){
        if(empty($process)){
            return $fieldVal;
        }
        static $conds=array('filter','if','func','api','insert','apiapp','translate');
        static $fnConds=array('tool');
        static $urlConds=array('download');
        foreach ($process as $params){
            
            if($params['close']){
                continue;
            }
            
            if(empty($this->first_loop_field)){
                
                if(isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5])){
                    return $fieldVal;
                }
            }else{
                
                if(isset($this->exclude_cont_urls[$contUrlMd5][$curUrlMd5][$loopIndex])){
                    return $fieldVal;
                }
            }
            if($this->field_stop_process){
                break;
            }
            
            $funcName='process_f_'.$params['module'];
            if(method_exists($this, $funcName)){
                if(in_array($params['module'],$conds)){
                    $fieldVal=$this->$funcName($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName);
                }elseif(in_array($params['module'],$urlConds)){
                    $fieldVal=$this->$funcName($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName,$urlInfo);
                }elseif(in_array($params['module'],$fnConds)){
                    $fieldVal=$this->$funcName($fieldVal,$params,$fieldName);
                }else{
                    $fieldVal=$this->$funcName($fieldVal,$params);
                }
            }
        }
        return $fieldVal;
    }
    
    
    /*数据处理方法*/
    public function process_f_html($fieldVal,$params){
        $htmlAllow=array_filter(explode(',',$params['html_allow']));
        $htmlFilter=array_filter(explode(',',$params['html_filter']));
        if(!empty($htmlAllow)){
            
            $delTags=array('script','style','object');
            foreach ($delTags as $k=>$v){
                if(in_array($v, $htmlAllow)){
                    
                    unset($delTags[$k]);
                }
            }
            if($delTags){
                $fieldVal=$this->filter_html_tags($fieldVal, $delTags);
            }
            
            $htmlAllowStr='';
            foreach ($htmlAllow as $v){
                $htmlAllowStr.='<'.$v.'>';
            }
            $fieldVal=strip_tags($fieldVal,$htmlAllowStr);
        }
        if(!empty($htmlFilter)){
            
            if(in_array('all', $htmlFilter)){
                
                $fieldVal=$this->filter_html_tags($fieldVal, array('style','script','object'));
                $fieldVal=strip_tags($fieldVal);
            }else{
                $fieldVal=$this->filter_html_tags($fieldVal, $htmlFilter);
            }
        }
        return $fieldVal;
    }
    public function process_f_insert($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        $txt=$params['insert_txt'];
        $txt=$this->_replace_insert_fields($txt,$fieldVal,$curUrlMd5,$loopIndex);
        
        if(empty($params['insert_loc'])){
            $fieldVal.=$txt;
        }elseif($params['insert_loc']=='head'){
            $fieldVal=$txt.$fieldVal;
        }elseif($params['insert_loc']=='rand'){
            $pattern='/<(?:p|br)[^<>]*>/i';
            if(preg_match_all($pattern,$fieldVal,$matches)){
                $count=count($matches[0]);
                $rand=rand(0,$count-1);
                $index=0;
                $fieldVal=preg_replace_callback($pattern, function($match)use($txt,$rand,&$index){
                    $val=$match[0];
                    if($index==$rand){
                        
                        $val.=$txt;
                    }
                    $index++;
                    return $val;
                }, $fieldVal);
            }else{
                $rand=rand(0,1);
                if($rand){
                    
                    $fieldVal=$txt.$fieldVal;
                }else{
                    $fieldVal.=$txt;
                }
            }
        }
        return $fieldVal;
    }
    public function process_f_replace($fieldVal,$params){
        
        return preg_replace('/'.$params['replace_from'].'/ui',$params['replace_to'], $fieldVal);
    }
    public function process_f_tool($fieldVal,$params,$fieldName=''){
        
        if(in_array('format', $params['tool_list'])){
            
            $fieldVal=$this->filter_html_tags($fieldVal,array('style','script'));
            $fieldVal=preg_replace('/\b(id|class|style|width|height|align)\s*=\s*([\'\"])[^\<\>\'\"]+?\\2(?=\s|$|\/|>)/i', ' ', $fieldVal);
        }
        if(in_array('trim', $params['tool_list'])){
            
            $fieldVal=trim($fieldVal);
        }
        if(in_array('url_not_complete', $params['tool_list'])){
            
            $this->field_url_complete=false;
        }
        
        $headers=null;
        if(in_array('vedio_url', $params['tool_list'])||in_array('url_real', $params['tool_list'])){
            $headers=$this->config_params['headers']['page'];
            init_array($headers);
            $useCookie=\util\Param::get_gsc_use_cookie('',true);
            if(!empty($useCookie)){
                
                unset($headers['cookie']);
                $headers['cookie']=$useCookie;
            }
        }
        
        if(in_array('vedio_url', $params['tool_list'])){
            
            $urls=$this->_process_f_tool_vdourl($fieldVal);
            if(empty($urls)){
                
                if(preg_match_all('/<[i]{0,1}frame\b[^<>]*\bsrc\s*=[\'\"\s]*([^\'\"\s]+)[\'\"\s]*/',$fieldVal,$mfurls)){
                    $mfurls=\util\Tools::clear_src_urls($mfurls[1]);
                    $this->echo_msg(array('正在数据处理：%s » 工具箱：提取音视频网址',$fieldName),'black');
                    foreach ($mfurls as $furl){
                        $fhtml=$this->get_html($furl,false,$headers);
                        $fvurls=$this->_process_f_tool_vdourl($fhtml);
                        if($fvurls){
                            $urls=array_merge($urls,$fvurls);
                        }
                    }
                }
            }
            $fieldVal=$urls?implode("\r\n",$urls):'';
        }
        if(in_array('url_real', $params['tool_list'])){
            
            $msgEchoed=false;
            $fieldVal=preg_replace_callback('/\bhttp[s]{0,1}\:\/\/[^\'\"\s]+/i',function($murl)use($headers,$fieldName,&$msgEchoed){
                if(!$msgEchoed){
                    $msgEchoed=true;
                    $this->echo_msg(array('正在数据处理：%s » 工具箱：网址真实地址',$fieldName),'black');
                }
                $murl=$murl[0];
                $urlInfo=$this->get_html($murl,false,$headers,null,array('return_head'=>1,'return_info'=>1),true);
                if(is_array($urlInfo)&&is_array($urlInfo['info'])&&$urlInfo['info']['url']){
                    $murl=$urlInfo['info']['url'];
                }
                return $murl;
            },$fieldVal);
        }
        if(in_array('img_tag', $params['tool_list'])){
            $fieldVal=preg_replace_callback('/(?<![\'\"])(\bhttp[s]{0,1}\:\/\/[^\s\'\"\<\>]+)(?![\'\"])/i',function($match){
                return '<img src="'.$match[1].'" />';
            },$fieldVal);
        }
        return $fieldVal;
    }
    
    private function _process_f_tool_vdourl($str){
        $urls=array();
        if($str&&preg_match_all('/<(video|object|embed|source)\b[^<>]+>/i',$str,$murls)){
            foreach ($murls[0] as $k=>$v){
                $tag=strtolower($murls[1][$k]);
                if(preg_match('/\b'.($tag=='object'?'data':'src').'\s*=[\'\"\s]*([^\'\"\s]+)[\'\"\s]*/i',$v,$murl)){
                    $urls[]=\util\Tools::clear_src_urls($murl[1]);
                }
            }
            $urls=array_unique($urls);
            $urls=array_filter($urls);
            $urls=array_values($urls);
        }
        return $urls;
    }
    
    public function process_f_download($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName,$urlInfo){
        if($params['download_op']=='is_img'||$params['download_op']=='url_img'){
            
            if(!is_empty(g_sc_c('download_img','download_img'))&&!empty($fieldVal)){
                
                $valImgs=array();
                if($params['download_op']=='is_img'){
                    
                    if(preg_match_all('/(?<![\'\"])(\bhttp[s]{0,1}\:\/\/[^\s\'\"\<\>]+)(?![\'\"])/i',$fieldVal,$murls)){
                        $valImgs=$murls[1];
                    }
                }elseif($params['download_op']=='url_img'){
                    if(preg_match_all('/'.$params['download_url_img_match'].'/ui',$fieldVal,$murls)){
                        foreach ($murls as $k=>$v){
                            if(strpos($k, 'url_img_')===0&&is_array($v)){
                                $valImgs=array_merge($valImgs,$v);
                            }
                        }
                    }
                    if($params['download_url_img_must']||$params['download_url_img_ban']){
                        foreach ($valImgs as $k=>$v){
                            if(!empty($params['download_url_img_must'])){
                                
                                if(!preg_match('/'.$params['download_url_img_must'].'/ui', $v)){
                                    $v='';
                                }
                            }
                            if(!empty($params['download_url_img_ban'])){
                                
                                if(preg_match('/'.$params['download_url_img_ban'].'/ui', $v)){
                                    $v='';
                                }
                            }
                            if(empty($v)){
                                unset($valImgs[$k]);
                            }
                        }
                        $valImgs=array_values($valImgs);
                    }
                }
                
                if(!empty($valImgs)){
                    $fieldImgs=array();
                    if(empty($this->first_loop_field)){
                        
                        $fieldImgs=$this->field_val_list[$fieldName]['imgs'][$curUrlMd5];
                    }else{
                        $fieldImgs=$this->field_val_list[$fieldName]['imgs'][$curUrlMd5][$loopIndex];
                    }
                    init_array($fieldImgs);
                    $fieldImgs=array_merge($fieldImgs,$valImgs);
                    $fieldImgs=array_unique($fieldImgs);
                    $fieldImgs=array_values($fieldImgs);
                    if(empty($this->first_loop_field)){
                        $this->field_val_list[$fieldName]['imgs'][$curUrlMd5]=$fieldImgs;
                    }else{
                        $this->field_val_list[$fieldName]['imgs'][$curUrlMd5][$loopIndex]=$fieldImgs;
                    }
                }
            }
        }elseif($params['download_op']=='no_img'){
            
            $this->field_down_img=false;
            
            if(empty($this->first_loop_field)){
                $this->field_val_list[$fieldName]['imgs'][$curUrlMd5]=array();
            }else{
                $this->field_val_list[$fieldName]['imgs'][$curUrlMd5][$loopIndex]=array();
            }
        }elseif($params['download_op']=='is_file'||$params['download_op']=='url_file'||$params['download_op']=='file'){
            
            if(!is_empty(g_sc_c('download_file','download_file'))&&!empty($fieldVal)){
                
                $valFiles=array();
                if($params['download_op']=='is_file'){
                    
                    if(preg_match_all('/(?<![\'\"])(\bhttp[s]{0,1}\:\/\/[^\s\'\"\<\>]+)(?![\'\"])/i',$fieldVal,$murls)){
                        $valFiles=$murls[1];
                    }
                }elseif($params['download_op']=='url_file'){
                    if(preg_match_all('/'.$params['download_url_file_match'].'/ui',$fieldVal,$murls)){
                        foreach ($murls as $k=>$v){
                            if(strpos($k, 'url_file_')===0&&is_array($v)){
                                $valFiles=array_merge($valFiles,$v);
                            }
                        }
                    }
                    if($params['download_url_file_must']||$params['download_url_file_ban']){
                        foreach ($valFiles as $k=>$v){
                            if(!empty($params['download_url_file_must'])){
                                
                                if(!preg_match('/'.$params['download_url_file_must'].'/ui', $v)){
                                    $v='';
                                }
                            }
                            if(!empty($params['download_url_file_ban'])){
                                
                                if(preg_match('/'.$params['download_url_file_ban'].'/ui', $v)){
                                    $v='';
                                }
                            }
                            if(empty($v)){
                                unset($valFiles[$k]);
                            }
                        }
                        $valFiles=array_values($valFiles);
                    }
                }elseif($params['download_op']=='file'){
                    
                    $tags=\skycaiji\admin\model\Config::process_tag_attr($params['download_file_tag'],true);
                    if(is_array($tags)&&!empty($tags[0])){
                        
                        for($i=0;$i<count($tags[0]);$i++){
                            $fieldVal=preg_replace_callback('/(<'.$tags[1][$i].'\b[^<>]*\b'.$tags[2][$i].'\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use (&$valFiles,$params,$urlInfo){
                                if($urlInfo){
                                    $matche[2]=\util\Tools::create_complete_url($matche[2], $urlInfo);
                                }
                                $fileUrl=$matche[2];
                                if(!empty($params['download_file_must'])){
                                    
                                    if(!preg_match('/'.$params['download_file_must'].'/ui', $fileUrl)){
                                        $fileUrl='';
                                    }
                                }
                                if(!empty($params['download_file_ban'])){
                                    
                                    if(preg_match('/'.$params['download_file_ban'].'/ui', $fileUrl)){
                                        $fileUrl='';
                                    }
                                }
                                if($fileUrl){
                                    $valFiles[]=$fileUrl;
                                }
                                return $matche[1].$matche[2].$matche[3];
                            },$fieldVal);
                        }
                    }
                }
                if(!empty($valFiles)){
                    $fieldFiles=array();
                    if(empty($this->first_loop_field)){
                        
                        $fieldFiles=$this->field_val_list[$fieldName]['files'][$curUrlMd5];
                    }else{
                        $fieldFiles=$this->field_val_list[$fieldName]['files'][$curUrlMd5][$loopIndex];
                    }
                    init_array($fieldFiles);
                    $fieldFiles=array_merge($fieldFiles,$valFiles);
                    $fieldFiles=array_unique($fieldFiles);
                    $fieldFiles=array_values($fieldFiles);
                    if(empty($this->first_loop_field)){
                        $this->field_val_list[$fieldName]['files'][$curUrlMd5]=$fieldFiles;
                    }else{
                        $this->field_val_list[$fieldName]['files'][$curUrlMd5][$loopIndex]=$fieldFiles;
                    }
                }
            }
        }
        return $fieldVal;
    }
    
    public function process_f_translate($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        
        $fieldParams=array('cr'=>$curUrlMd5,'li'=>$loopIndex,'ct'=>$contUrlMd5,'f'=>$fieldName);
        
        static $regEmpty='/^([\s\r\n]|\&nbsp\;)*$/';
        if(!is_empty(g_sc_c('translate'))&&!is_empty(g_sc_c('translate','open'))&&!empty($fieldVal)){
            
            $this->echo_msg(array('正在翻译：%s',$fieldName),'black',true,'','display:inline;margin-right:5px;');
            
            $langFrom=$params['translate_from']=='custom'?$params['translate_from_custom']:$params['translate_from'];
            $langTo=$params['translate_to']=='custom'?$params['translate_to_custom']:$params['translate_to'];
            
            if(!is_empty(g_sc_c('translate','pass_html'))){
                
                $htmlMd5List=array();
                $txtMd5List=array();
                
                
                static $tagRegs=array('/<\![\s\S]*?>/','/<(script|style)[^\r\n]*?>[\s\S]*?<\/\1>/i','/<[\/]*\w+\b[^\r\n]*?>/');
                foreach($tagRegs as $tagReg){
                    $fieldVal=preg_replace_callback($tagReg,function($mhtml)use(&$htmlMd5List){
                        $key='{'.md5($mhtml[0]).'}';
                        $htmlMd5List[$key]=$mhtml[0];
                        return $key;
                    },$fieldVal);
                }
                
                if(empty($htmlMd5List)){
                    
                    if(!empty($fieldVal)&&!preg_match($regEmpty, $fieldVal)){
                        
                        $fieldVal=$this->_execute_translate($fieldVal, $langFrom, $langTo, $fieldParams);
                    }
                }else{
                    
                    
                    $fieldVal=preg_replace_callback('/([\s\S]*?)(\{[a-zA-Z0-9]{32}\})/i',function($mtxt)use(&$txtMd5List){
                        $key='['.md5($mtxt[1]).']';
                        $txtMd5List[$key]=$mtxt[1];
                        return $key.$mtxt[2];
                    },$fieldVal);
                        
                        foreach ($txtMd5List as $k=>$v){
                            
                            if(!empty($v)&&!preg_match($regEmpty, $v)){
                                
                                $txtMd5List[$k]=$this->_execute_translate($v, $langFrom, $langTo, $fieldParams);
                            }
                        }
                        
                        if(!empty($txtMd5List)){
                            $fieldVal=str_replace(array_keys($txtMd5List), $txtMd5List, $fieldVal);
                        }
                        
                        if(!empty($htmlMd5List)){
                            $fieldVal=str_replace(array_keys($htmlMd5List), $htmlMd5List, $fieldVal);
                        }
                }
            }else{
                
                if(!empty($fieldVal)&&!preg_match($regEmpty, $fieldVal)){
                    
                    $fieldVal=$this->_execute_translate($fieldVal, $langFrom, $langTo, $fieldParams);
                }
            }
        }
        return $fieldVal;
    }
    
    public function process_f_batch($fieldVal,$params){
        
        static $batch_list=array();
        if(!empty($params['batch_list'])){
            $listMd5=md5($params['batch_list']);
            if(!isset($batch_list[$listMd5])){
                
                if(preg_match_all('/[^\r\n]+/', $params['batch_list'],$mlist)){
                    unset($params['batch_list']);
                    $mlist=$mlist[0];
                    $sign=empty($params['batch_sign'])?'=':$params['batch_sign'];
                    $batch_re=array();
                    $batch_to=array();
                    foreach ($mlist as $k=>$v){
                        $v=explode($sign,$v,2);
                        if(is_array($v)&&count($v)==2&&!is_empty($v[0],true)){
                            
                            $batch_re[]=$v[0];
                            $batch_to[]=$v[1];
                        }
                        unset($mlist[$k]);
                    }
                    $batch_list[$listMd5]=array($batch_re,$batch_to);
                }
            }else{
                $batch_re=$batch_list[$listMd5][0];
                $batch_to=$batch_list[$listMd5][1];
            }
            $batch_re=is_array($batch_re)?$batch_re:array();
            $batch_to=is_array($batch_to)?$batch_to:array();
            if(!empty($batch_re)&&count($batch_re)==count($batch_to)){
                
                $fieldVal=str_replace($batch_re, $batch_to, $fieldVal);
            }
        }
        return $fieldVal;
    }
    public function process_f_substr($fieldVal,$params){
        $params['substr_len']=intval($params['substr_len']);
        if($params['substr_len']>0){
            if(mb_strlen($fieldVal,'utf-8')>$params['substr_len']){
                
                $fieldVal=mb_substr($fieldVal,0,$params['substr_len'],'utf-8').$params['substr_end'];
            }
        }
        return $fieldVal;
    }
    public function process_f_func($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        
        if($params&&$params['func_name']){
            $result=$this->execute_plugin_func('process', $params['func_name'], $fieldVal, $params['func_param'], $this->_get_insert_fields($params['func_param'], $curUrlMd5, $loopIndex),'【字段：'.$fieldName.'】',true);
            if(empty($result['success'])){
                
                $this->echo_msg_exit('');
            }
            if(isset($result['data'])){
                $fieldVal=$result['data'];
            }
        }
        return $fieldVal;
    }
    public function process_f_apiapp($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        
        if($params&&$params['apiapp_app']){
            init_array($params['apiapp_config']);
            $result=$this->execute_plugin_apiapp('process', $params['apiapp_app'], $fieldVal, $params['apiapp_config'], $this->_get_insert_fields(implode("\r\n", $params['apiapp_config']), $curUrlMd5, $loopIndex),'【字段：'.$fieldName.'】',true);
            if(empty($result['success'])){
                
                if($result['exclude_cont_url']){
                    
                    $this->set_exclude_cont_url($contUrlMd5, $curUrlMd5, $loopIndex, array('field'=>$fieldName,'type'=>'apiapp','msg'=>$result['msg']));
                }else{
                    $this->echo_msg_exit('');
                }
            }
            if(isset($result['data'])){
                $fieldVal=$result['data'];
            }
        }
        return $fieldVal;
    }
    public function process_f_filter($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        static $key_list=array();
        if(!empty($params['filter_list'])){
            $listMd5=md5($params['filter_list']);
            if(!isset($key_list[$listMd5])){
                $filterList=explode("\r\n", $params['filter_list']);
                $filterList=array_filter($filterList);
                $key_list[$listMd5]=$filterList;
            }else{
                $filterList=$key_list[$listMd5];
            }
            $filterList=is_array($filterList)?$filterList:array();
            
            
            if(!empty($params['filter_pass'])){
                if($params['filter_pass']=='1'){
                    
                    foreach ($filterList as $filterStr){
                        if(stripos($fieldVal,$filterStr)!==false){
                            
                            $fieldVal='';
                            break;
                        }
                    }
                }elseif($params['filter_pass']=='2'){
                    
                    foreach ($filterList as $filterStr){
                        if(stripos($fieldVal,$filterStr)!==false){
                            
                            $this->set_exclude_cont_url($contUrlMd5, $curUrlMd5, $loopIndex, array('field'=>$fieldName,'type'=>'filter','filter'=>$filterStr));
                            break;
                        }
                    }
                }elseif($params['filter_pass']=='3'){
                    
                    $hasKey=false;
                    foreach ($filterList as $filterStr){
                        if(stripos($fieldVal,$filterStr)!==false){
                            
                            $hasKey=true;
                            break;
                        }
                    }
                    if(!$hasKey){
                        $fieldVal='';
                    }
                }elseif($params['filter_pass']=='4'){
                    
                    $hasKey=false;
                    foreach ($filterList as $filterStr){
                        if(stripos($fieldVal,$filterStr)!==false){
                            
                            $hasKey=true;
                            break;
                        }
                    }
                    if(!$hasKey){
                        
                        $this->set_exclude_cont_url($contUrlMd5, $curUrlMd5, $loopIndex, array('field'=>$fieldName,'type'=>'filter','filter'=>''));
                    }
                }
            }else{
                
                $fieldVal=str_ireplace($filterList, $params['filter_replace'], $fieldVal);
            }
        }
        return $fieldVal;
    }
    public function process_f_if($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        static $func_list=array();
        
        if(is_array($params['if_logic'])&&!empty($params['if_logic'])){
            
            $ifOrList=array();
            $ifAndList=array();
            
            foreach($params['if_logic'] as $ifk=>$iflv){
                if('or'==$iflv){
                    if(!empty($ifAndList)){
                        
                        $ifOrList[]=$ifAndList;
                    }
                    $ifAndList=array();
                    $ifAndList[]=$ifk;
                }elseif('and'==$iflv){
                    
                    $ifAndList[]=$ifk;
                }
            }
            if(!empty($ifAndList)){
                
                $ifOrList[]=$ifAndList;
            }
            if(is_array($ifOrList)&&!empty($ifOrList)){
                $isTrue=false;
                $breakCond='';
                
                foreach ($ifOrList as $ifAndList){
                    $ifAndResult=true;
                    foreach ($ifAndList as $ifIndex){
                        $ifLogic=$params['if_logic'][$ifIndex];
                        $ifCond=$params['if_cond'][$ifIndex];
                        if(empty($ifLogic)||empty($ifCond)){
                            
                            continue;
                        }
                        $ifVal=$params['if_val'][$ifIndex];
                        $result=false;
                        $breakCond=lang('p_m_if_c_'.$ifCond).':'.$ifVal;
                        switch($ifCond){
                            case 'regexp':
                                if(preg_match('/'.$ifVal.'/'.$this->config['reg_regexp_flags'], $fieldVal)){
                                    $result=true;
                                }
                                break;
                            case 'func':
                                $funcName=$params['if_addon']['func'][$ifIndex];
                                $isTurn=$params['if_addon']['turn'][$ifIndex];
                                $isTurn=$isTurn?true:false;
                                
                                $result=$this->execute_plugin_func('processIf', $funcName, $fieldVal, $ifVal, $this->_get_insert_fields($ifVal, $curUrlMd5, $loopIndex),'【字段：'.$fieldName.'】',true);
                                if(empty($result['success'])&&$funcName){
                                    
                                    $this->echo_msg_exit('');
                                    return $fieldVal;
                                }
                                $result=$result['data'];
                                $result=$result?true:false;
                                if($isTurn){
                                    $result=$result?false:true;
                                }
                                $breakCond=lang('p_m_if_c_'.$ifCond).':'.$funcName.($isTurn?'取反':'');
                                break;
                            case 'has':$result=stripos($fieldVal,$ifVal)!==false?true:false;break;
                            case 'nhas':$result=stripos($fieldVal,$ifVal)===false?true:false;break;
                            case 'eq':$result=$fieldVal==$ifVal?true:false;break;
                            case 'neq':$result=$fieldVal!=$ifVal?true:false;break;
                            case 'heq':$result=$fieldVal===$ifVal?true:false;break;
                            case 'nheq':$result=$fieldVal!==$ifVal?true:false;break;
                            case 'gt':$result=$fieldVal>$ifVal?true:false;break;
                            case 'egt':$result=$fieldVal>=$ifVal?true:false;break;
                            case 'lt':$result=$fieldVal<$ifVal?true:false;break;
                            case 'elt':$result=$fieldVal<=$ifVal?true:false;break;
                            case 'time_eq':
                            case 'time_egt':
                            case 'time_elt':
                                $fieldTime=is_numeric($fieldVal)?$fieldVal:strtotime($fieldVal);
                                $valTime=is_numeric($ifVal)?$ifVal:strtotime($ifVal);
                                if($ifCond=='time_eq'){
                                    
                                    $result=$fieldTime==$valTime?true:false;
                                }elseif($ifCond=='time_egt'){
                                    
                                    $result=$fieldTime>=$valTime?true:false;
                                }elseif($ifCond=='time_elt'){
                                    
                                    $result=$fieldTime<=$valTime?true:false;
                                }
                                break;
                        }
                        if(!$result){
                            
                            $ifAndResult=false;
                            break;
                        }
                    }
                    
                    if($ifAndResult){
                        
                        $isTrue=true;
                        break;
                    }
                }
                
                $exclude=null;
                
                switch ($params['if_type']){
                    case '1':$exclude=$isTrue?null:array('if'=>'1');break;
                    case '2':$exclude=$isTrue?array('if'=>'2'):null;break;
                    case '3':$exclude=!$isTrue?null:array('if'=>'3');break;
                    case '4':$exclude=!$isTrue?array('if'=>'4'):null;break;
                }
                
                if(!empty($exclude)){
                    $exclude['type']='if';
                    $exclude['field']=$fieldName;
                    $exclude['cond']=$breakCond;
                    
                    if($params['if_stop']=='collect'){
                        
                        $msg=$this->exclude_url_msg(json_encode($exclude));
                        $msg=$msg.'»仍采集但跳出处理';
                        $this->echo_msg($msg,'orange');
                        $exclude=null;
                        $this->field_stop_process=true;
                    }
                    
                    if(!empty($exclude)){
                        
                        $this->set_exclude_cont_url($contUrlMd5, $curUrlMd5, $loopIndex, $exclude);
                    }
                }
            }
        }
        return $fieldVal;
    }
    /*调用接口*/
    public function process_f_api($fieldVal,$params,$curUrlMd5,$loopIndex,$contUrlMd5,$fieldName=''){
        static $retryCur=0;
        $retryMax=intval($params['api_retry']);
        $retryParams=null;
        if($retryMax>0){
            
            $retryParams=array(0=>$fieldVal,1=>$params,2=>$curUrlMd5,3=>$loopIndex,4=>$contUrlMd5,5=>$fieldName);
        }
        
        $url=$params['api_url'];
        $htmlInfo=null;
        if(!empty($url)){
            if(strpos($url, '/')===0){
                
                $url=config('root_website').$url;
            }
            if(\util\Funcs::is_right_url($url)){
                
                
                $charset=$params['api_charset'];
                if($charset=='custom'){
                    $charset=$params['api_charset_custom'];
                }
                if(empty($charset)){
                    $charset='utf-8';
                }
                $curlopts=array();
                
                $encode=$params['api_encode'];
                if($encode=='custom'){
                    $encode=$params['api_encode_custom'];
                }
                if($encode){
                    $curlopts[CURLOPT_ENCODING]=$encode;
                }
                
                $url=$this->_replace_insert_fields($url,$fieldVal,$curUrlMd5,$loopIndex);
                $url=\util\Funcs::url_auto_encode($url, $charset);
                
                
                $postData=array();
                if(is_array($params['api_params'])){
                    init_array($params['api_params']['name']);
                    init_array($params['api_params']['val']);
                    init_array($params['api_params']['addon']);
                    foreach ($params['api_params']['name'] as $k=>$v){
                        if(empty($v)){
                            continue;
                        }
                        $val=$params['api_params']['val'][$k];
                        $addon=$params['api_params']['addon'][$k];
                        switch ($val){
                            case 'field':$val=$fieldVal;break;
                            case 'timestamp':$val=time();break;
                            case 'time':$addon=$addon?$addon:'Y-m-d H:i:s';$val=date($addon,time());break;
                            case 'custom':$val=$this->_replace_insert_fields($addon,$fieldVal,$curUrlMd5,$loopIndex);break;
                        }
                        $postData[$v]=$val;
                    }
                }
                
                $headers=array();
                if(is_array($params['api_headers'])){
                    init_array($params['api_headers']['name']);
                    init_array($params['api_headers']['val']);
                    init_array($params['api_headers']['addon']);
                    foreach ($params['api_headers']['name'] as $k=>$v){
                        if(empty($v)){
                            continue;
                        }
                        $val=$params['api_headers']['val'][$k];
                        $addon=$params['api_headers']['addon'][$k];
                        switch ($val){
                            case 'field':$val=$fieldVal;break;
                            case 'timestamp':$val=time();break;
                            case 'time':$addon=$addon?$addon:'Y-m-d H:i:s';$val=date($addon,time());break;
                            case 'custom':$val=$this->_replace_insert_fields($addon,$fieldVal,$curUrlMd5,$loopIndex);break;
                        }
                        $headers[$v]=$val;
                    }
                }
                
                
                if($params['api_content_type']){
                    $headers['content-type']=$params['api_content_type'];
                }
                
                if($params['api_type']=='post'){
                    
                    $postData=empty($postData)?true:$postData;
                }else{
                    
                    $url=\util\Funcs::url_params_charset($url,$postData,$charset);
                    $postData=null;
                }
                if($retryCur<=0){
                    $this->echo_msg(array('正在数据处理：%s » 调用接口',$fieldName),'black');
                }
                $htmlInfo=get_html($url,$headers,array('timeout'=>60,'curlopts'=>$curlopts),$charset,$postData,true);
                $this->collect_sleep($this->task_id,$params['api_interval'],true);
                if(!empty($htmlInfo['ok'])){
                    
                    $retryCur=0;
                    
                    if(empty($params['api_rule_module'])){
                        
                        $fieldVal=$this->rule_module_json_data(array(
                            'json' => $params['api_json'],
                            'json_merge_data' => $params['api_json_merge_data'],
                            'json_arr' => $params['api_json_arr'],
                            'json_arr_implode' => $params['api_json_arr_implode']
                        ),$htmlInfo['html']);
                    }elseif('xpath'==$params['api_rule_module']){
                        
                        $fieldVal=$this->rule_module_xpath_data(array(
                            'xpath' => $params['api_xpath'],
                            'xpath_attr' => $params['api_xpath_attr'],
                            'xpath_multi' => $params['api_xpath_multi'],
                            'xpath_multi_str' => $params['api_xpath_multi_str'],
                        ),$htmlInfo['html']);
                    }elseif('rule'==$params['api_rule_module']){
                        
                        $fieldVal=$this->rule_module_rule_data_get(array(
                            'rule' => $params['api_rule'],
                            'rule_merge' => $params['api_rule_merge'],
                            'rule_multi' => $params['api_rule_multi'],
                            'rule_multi_str' => $params['api_rule_multi_str'],
                            'rule_flags'=>'iu',
                        ),$htmlInfo['html'],array(),true);
                    }
                }else{
                    $this->retry_first_echo($retryCur,'数据处理»调用接口失败',$url,$htmlInfo);
                    
                    $this->collect_sleep($this->task_id,$params['api_wait']);
                    
                    $doStatus=$this->retry_do_func($retryCur,$retryMax,'接口无效');
                    if($doStatus){
                        return $this->process_f_api($retryParams[0],$retryParams[1],$retryParams[2],$retryParams[3],$retryParams[4],$retryParams[5]);
                    }else{
                        
                        if($doStatus===0){
                            $this->echo_error('接口无效');
                        }
                        
                        $this->set_exclude_cont_url($contUrlMd5, $curUrlMd5, $loopIndex, array('field'=>$fieldName,'type'=>'api','msg'=>'数据处理»调用接口失败'));
                    }
                }
            }
        }
        return $fieldVal;
    }
    
    
    public function process_f_extract($fieldVal,$params){
        if('rule'==$params['extract_module']){
            
            $fieldVal=$this->rule_module_rule_data_get(array(
                'rule' => $params['extract_rule'],
                'rule_merge' => $params['extract_rule_merge'],
                'rule_multi' => $params['extract_rule_multi'],
                'rule_multi_str' => $params['extract_rule_multi_str'],
                'rule_flags'=>'iu',
            ),$fieldVal,array(),true);
        }elseif('xpath'==$params['extract_module']){
            
            $fieldVal=$this->rule_module_xpath_data(array(
                'xpath' => $params['extract_xpath'],
                'xpath_attr' => $params['extract_xpath_attr'],
                'xpath_multi' => $params['extract_xpath_multi'],
                'xpath_multi_str' => $params['extract_xpath_multi_str'],
            ),$fieldVal);
        }elseif('json'==$params['extract_module']){
            
            $fieldVal=$this->rule_module_json_data(array(
                'json' => $params['extract_json'],
                'json_merge_data' => $params['extract_json_merge_data'],
                'json_arr' => $params['extract_json_arr'],
                'json_arr_implode' => $params['extract_json_arr_implode']
            ),$fieldVal);
        }
        return $fieldVal;
    }
    
    
    public function set_field_val($field_config,$cur_url,$htmlInfoOrData,$cont_url,$url_info){
        $htmlInfo=array();
        $html='';
        if($this->cur_c_module['pattern']){
            
            init_array($htmlInfoOrData);
            $htmlInfo=&$htmlInfoOrData;
            $html=$htmlInfo['html'];
        }else{
            
            $htmlInfoOrData=is_array($htmlInfoOrData)?'':$htmlInfoOrData;
            $html=$htmlInfoOrData;
        }
        
        $cur_url_md5=md5($cur_url);
        
        $field_process=$field_config['process'];
        $field_params=$field_config['field'];
        $module=strtolower($field_params['module']);
        
        $field_name=$field_params['name'];
        
        static $fieldArr=array('words','num','no','time','list');
        
        $val='';
        $field_func='field_module_'.$module;
        $is_list_data=false;
        $list_multi_str='';
        if($module=='dvalue'){
            
            $val=$html;
        }elseif(method_exists($this, $field_func)){
            
            if('extract'==$module){
                
                
                if(is_array($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5])){
                    
                    $val=array();
                    foreach ($this->field_val_list[$field_params['extract']]['values'][$cur_url_md5] as $k=>$v){
                        $extract_field_val=array(
                            'value'=>$v,
                            'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5][$k],
                            'file'=>$this->field_val_list[$field_params['extract']]['files'][$cur_url_md5][$k],
                        );
                        $val[$k]=$this->field_module_extract($field_params, $extract_field_val, $url_info);
                    }
                }else{
                    
                    $extract_field_val=array(
                        'value'=>$this->field_val_list[$field_params['extract']]['values'][$cur_url_md5],
                        'img'=>$this->field_val_list[$field_params['extract']]['imgs'][$cur_url_md5],
                        'file'=>$this->field_val_list[$field_params['extract']]['files'][$cur_url_md5],
                    );
                    $val=$this->field_module_extract($field_params, $extract_field_val, $url_info);
                }
                
                if($field_params['extract_module']=='rule'){
                    if($field_params['extract_rule_multi']&&$field_params['extract_rule_multi_type']=='list'){
                        $is_list_data=true;
                        $list_multi_str=$field_params['extract_rule_multi_str'];
                    }
                }elseif($field_params['extract_module']=='xpath'){
                    if($field_params['extract_xpath_multi']&&$field_params['extract_xpath_multi_type']=='list'){
                        $is_list_data=true;
                        $list_multi_str=$field_params['extract_xpath_multi_str'];
                    }
                }
            }elseif('merge'==$module){
                
                if(empty($this->first_loop_field)){
                    
                    $cur_field_val_list=array();
                    foreach ($this->field_val_list as $k=>$v){
                        $cur_field_val_list[$k]=array(
                            'value'=>$v['values'][$cur_url_md5],
                            'img'=>$v['imgs'][$cur_url_md5],
                            'file'=>$v['files'][$cur_url_md5]
                        );
                    }
                    $val=$this->field_module_merge($field_params,$cur_field_val_list);
                }else{
                    
                    $val=array();
                    
                    if(is_array($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5])){
                        foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
                            $cur_field_val_list=array();
                            foreach ($this->field_val_list as $k=>$v){
                                $cur_field_val_list[$k]=array(
                                    'value'=>(is_array($v['values'][$cur_url_md5])?$v['values'][$cur_url_md5][$v_k]:$v['values'][$cur_url_md5]),
                                    'img'=>((is_array($v['imgs'][$cur_url_md5])&&is_array($v['imgs'][$cur_url_md5][$v_k]))?$v['imgs'][$cur_url_md5][$v_k]:$v['imgs'][$cur_url_md5]),
                                    'file'=>((is_array($v['files'][$cur_url_md5])&&is_array($v['files'][$cur_url_md5][$v_k]))?$v['files'][$cur_url_md5][$v_k]:$v['files'][$cur_url_md5])
                                );
                            }
                            $val[$v_k]=$this->field_module_merge($field_params,$cur_field_val_list);
                        }
                    }
                }
            }elseif(in_array($module,$fieldArr)){
                
                if(empty($this->first_loop_field)){
                    
                    $val=$this->$field_func($field_params);
                }else{
                    
                    $val=array();
                    
                    if(is_array($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5])){
                        foreach ($this->field_val_list[$this->first_loop_field]['values'][$cur_url_md5] as $v_k=>$v_v){
                            $val[$v_k]=$this->$field_func($field_params);
                        }
                    }
                }
            }elseif($module=='json'){
                $val=$this->$field_func($field_params,$html,$cur_url);
            }elseif($module=='auto'){
                $val=$this->$field_func($field_params,$htmlInfo,$cur_url);
            }elseif($module=='sign'){
                
                $val=$this->$field_func($field_params,empty($cont_url)?$cur_url:$cont_url);
            }else{
                $val=$this->$field_func($field_params,$html);
                
                if($module=='rule'){
                    if($field_params['rule_multi']&&$field_params['rule_multi_type']=='list'){
                        $is_list_data=true;
                        $list_multi_str=$field_params['rule_multi_str'];
                    }
                }elseif($module=='xpath'){
                    if($field_params['xpath_multi']&&$field_params['xpath_multi_type']=='list'){
                        $is_list_data=true;
                        $list_multi_str=$field_params['xpath_multi_str'];
                    }
                }
            }
        }
        
        if(!empty($list_multi_str)){
            $list_multi_str=str_replace(array('\r','\n'), array("\r","\n"), $list_multi_str);
        }
        
        $vals=null;
        if(is_array($val)){
            
            $is_loop=true;
            $vals=$val;
            unset($val);
        }else{
            
            $is_loop=false;
            $vals=array($val);
        }
        
        $cont_url_md5=empty($cont_url)?$cur_url_md5:md5($cont_url);
        
        foreach ($vals as $v_k=>$val){
            $val=isset($val)?$val:'';
            $loopIndex=$is_loop?$v_k:-1;
            $this->field_url_complete=$this->cur_c_module['pattern']?true:false;
            $this->field_down_img=$this->cur_c_module['pattern']?true:false;
            $this->field_stop_process=false;
            
            if($is_loop){
                
                if(!isset($this->field_val_list[$field_name]['values'][$cur_url_md5])){
                    $this->field_val_list[$field_name]['values'][$cur_url_md5]=array();
                    $this->field_val_list[$field_name]['imgs'][$cur_url_md5]=array();
                    $this->field_val_list[$field_name]['files'][$cur_url_md5]=array();
                }
            }
            
            if($is_list_data){
                
                $val=$val?json_decode($val,true):array();
                init_array($val);
                foreach ($val as $v_vk=>$v_vv){
                    if(!empty($field_process)){
                        
                        $v_vv=$this->process_field($field_name,$v_vv,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5,$url_info);
                    }
                    if(!empty($this->config['common_process'])){
                        
                        $v_vv=$this->process_field($field_name,$v_vv,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5,$url_info);
                    }
                    $val[$v_vk]=$v_vv;
                }
                $val=implode($list_multi_str, $val);
            }else{
                if(!empty($field_process)){
                    
                    $val=$this->process_field($field_name,$val,$field_process,$cur_url_md5,$loopIndex,$cont_url_md5,$url_info);
                }
                if(!empty($this->config['common_process'])){
                    
                    $val=$this->process_field($field_name,$val,$this->config['common_process'],$cur_url_md5,$loopIndex,$cont_url_md5,$url_info);
                }
            }
            
            if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5])){
                
                if(empty($this->first_loop_field)){
                    
                    foreach ($this->field_val_list as $f_k=>$f_v){
                        
                        unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
                        unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
                        unset($this->field_val_list[$f_k]['files'][$cur_url_md5]);
                    }
                    return;
                }else{
                    
                    if(isset($this->exclude_cont_urls[$cont_url_md5][$cur_url_md5][$loopIndex])){
                        
                        if(!$is_loop){
                            
                            foreach ($this->field_val_list as $f_k=>$f_v){
                                
                                unset($this->field_val_list[$f_k]['values'][$cur_url_md5]);
                                unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5]);
                                unset($this->field_val_list[$f_k]['files'][$cur_url_md5]);
                            }
                            return;
                        }else{
                            
                            foreach ($this->field_val_list as $f_k=>$f_v){
                                
                                if(is_array($this->field_val_list[$f_k]['values'][$cur_url_md5])){
                                    
                                    unset($this->field_val_list[$f_k]['values'][$cur_url_md5][$v_k]);
                                }
                                if(is_array($this->field_val_list[$f_k]['imgs'][$cur_url_md5])){
                                    
                                    unset($this->field_val_list[$f_k]['imgs'][$cur_url_md5][$v_k]);
                                }
                                if(is_array($this->field_val_list[$f_k]['files'][$cur_url_md5])){
                                    
                                    unset($this->field_val_list[$f_k]['files'][$cur_url_md5][$v_k]);
                                }
                            }
                            continue;
                        }
                    }
                }
            }
            
            if($this->field_url_complete){
                
                $val=preg_replace_callback('/(\bhref\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                    
                    $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                    return $matche[1].$matche[2].$matche[3];
                },$val);
                $val=preg_replace_callback('/(\bsrc\s*=\s*[\'\"])([^\'\"]*)([\'\"])/i',function($matche) use ($url_info){
                    $matche[2]=\util\Tools::create_complete_url($matche[2], $url_info);
                    return $matche[1].$matche[2].$matche[3];
                },$val);
            }
            
            if($is_loop){
                
                if(!isset($this->field_val_list[$field_name]['values'][$cur_url_md5])){
                    $this->field_val_list[$field_name]['values'][$cur_url_md5]=array();
                    $this->field_val_list[$field_name]['imgs'][$cur_url_md5]=array();
                    $this->field_val_list[$field_name]['files'][$cur_url_md5]=array();
                }
                $this->field_val_list[$field_name]['values'][$cur_url_md5][$v_k]=$val;
            }else{
                
                $this->field_val_list[$field_name]['values'][$cur_url_md5]=$val;
            }
            if(!is_empty(g_sc_c('download_img','download_img'))&&!empty($val)&&$this->field_down_img){
                
                $valImgs=array();
                if(preg_match_all('/<img\b[^<>]*\bsrc\s*=\s*[\'\"](\w+\:[^\'\"]+?)[\'\"]/i',$val,$imgUrls)){
                    
                    $valImgs=is_array($imgUrls[1])?$imgUrls[1]:array();
                }
                if('extract'==$module&&'cover'==$field_params['extract_module']){
                    
                    $valImgs=array_merge($valImgs,array($val));
                }
                if(!empty($valImgs)){
                    $fieldImgs=array();
                    if($is_loop){
                        
                        $fieldImgs=$this->field_val_list[$field_name]['imgs'][$cur_url_md5][$v_k];
                    }else{
                        
                        $fieldImgs=$this->field_val_list[$field_name]['imgs'][$cur_url_md5];
                    }
                    init_array($fieldImgs);
                    $fieldImgs=array_merge($fieldImgs,$valImgs);
                    $fieldImgs=array_unique($fieldImgs);
                    $fieldImgs=array_values($fieldImgs);
                    if($is_loop){
                        $this->field_val_list[$field_name]['imgs'][$cur_url_md5][$v_k]=$fieldImgs;
                    }else{
                        $this->field_val_list[$field_name]['imgs'][$cur_url_md5]=$fieldImgs;
                    }
                }
            }
        }
    }
    
    
    public function get_field_vals(){
        $val_list=array();
        if(!empty($this->field_val_list)){
            if(empty($this->first_loop_field)){
                
                foreach ($this->field_val_list as $fieldName=>$fieldVal){
                    $val_values='';
                    if(!empty($fieldVal['values'])){
                        $val_values=\util\Funcs::array_filter_keep0($fieldVal['values']);
                        $valDelimiter='';
                        if($this->cur_c_module['pattern']){
                            $pnField=$this->get_config('pagination','new_fields',$fieldName);
                            if(is_array($pnField)){
                                $valDelimiter=$pnField['delimiter'];
                            }
                        }
                        $val_values=implode($valDelimiter, $val_values);
                    }
                    
                    $val_imgs=array();
                    if(!empty($fieldVal['imgs'])){
                        foreach ($fieldVal['imgs'] as $v){
                            if(!empty($v)){
                                if(is_array($v)){
                                    $val_imgs=array_merge($val_imgs,$v);
                                }else{
                                    $val_imgs[]=$v;
                                }
                            }
                        }
                        if(!empty($val_imgs)){
                            $val_imgs=array_unique($val_imgs);
                            $val_imgs=array_filter($val_imgs);
                            $val_imgs=array_values($val_imgs);
                        }
                    }
                    
                    $val_files=array();
                    if(!empty($fieldVal['files'])){
                        foreach ($fieldVal['files'] as $v){
                            if(!empty($v)){
                                if(is_array($v)){
                                    $val_files=array_merge($val_files,$v);
                                }else{
                                    $val_files[]=$v;
                                }
                            }
                        }
                        if(!empty($val_files)){
                            $val_files=array_unique($val_files);
                            $val_files=array_filter($val_files);
                            $val_files=array_values($val_files);
                        }
                    }
                    
                    $val_list[$fieldName]=array('name'=>$fieldName,'value'=>$val_values,'img'=>$val_imgs,'file'=>$val_files);
                }
            }else{
                
                
                foreach ($this->field_val_list[$this->first_loop_field]['values'] as $page_key=>$page_vals){
                    
                    if(empty($page_vals)){
                        
                        continue;
                    }
                    foreach ($page_vals as $loop_index=>$loop_val){
                        
                        $vals=array();
                        foreach ($this->field_val_list as $fieldName=>$fieldVals){
                            if(is_array($fieldVals['values'][$page_key])){
                                
                                $val_values=$fieldVals['values'][$page_key][$loop_index];
                                $val_imgs=$fieldVals['imgs'][$page_key][$loop_index];
                                $val_files=$fieldVals['files'][$page_key][$loop_index];
                            }else{
                                
                                $val_values=$fieldVals['values'][$page_key];
                                $val_imgs=$fieldVals['imgs'][$page_key];
                                $val_files=$fieldVals['files'][$page_key];
                            }
                            if(!empty($val_imgs)){
                                $val_imgs=array_unique($val_imgs);
                                $val_imgs=array_filter($val_imgs);
                                $val_imgs=array_values($val_imgs);
                            }
                            if(!empty($val_files)){
                                $val_files=array_unique($val_files);
                                $val_files=array_filter($val_files);
                                $val_files=array_values($val_files);
                            }
                            $vals[$fieldName]=array('name'=>$fieldName,'value'=>$val_values,'img'=>$val_imgs,'file'=>$val_files);
                        }
                        $val_list[]=$vals;
                    }
                }
            }
        }
        return $val_list?$val_list:array();
    }
    
    
    public function collect_fields_vals($echo_str,$cont_url,$md5_cont_url,&$field_vals_list,$url_repeat){
        $is_loop=empty($this->first_loop_field)?false:true;
        $loopExcludeNum=0;
        if($is_loop){
            
            if(isset($this->exclude_cont_urls[$md5_cont_url])){
                
                $loopExcludeNum=0;
                foreach($this->exclude_cont_urls[$md5_cont_url] as $k=>$v){
                    
                    $loopExcludeNum+=count((array)$v);
                }
                $this->echo_msg(array('%s通过数据处理筛除了%s条数据',$echo_str,$loopExcludeNum),'black');
            }
        }
        $mcollected=model('Collected');
        if(!empty($field_vals_list)){
            if(!$is_loop){
                
                $field_vals_list=array($field_vals_list);
            }else{
                
                
                $loop_cont_urls=array();
                foreach ($field_vals_list as $k=>$field_vals){
                    $loop_cont_urls[$k]=$cont_url.'#'.md5(serialize($field_vals));
                }
                if(!empty($loop_cont_urls)){
                    $loop_exists_urls=$mcollected->collGetUrlByUrl($loop_cont_urls,$this->task_id,g_sc_c('caiji','same_url'));
                    if(!empty($loop_exists_urls)){
                        
                        $loop_exists_urls=array_flip($loop_exists_urls);
                        foreach ($loop_cont_urls as $k=>$loop_cont_url){
                            if(isset($loop_exists_urls[$loop_cont_url])){
                                
                                unset($field_vals_list[$k]);
                            }
                        }
                        $this->echo_msg(array('%s已过滤%s条重复数据',$echo_str,count((array)$loop_exists_urls)),'black');
                    }
                }
                $field_vals_list=array_values($field_vals_list);
            }
            
            foreach ($field_vals_list as $field_vals){
                $collected_error='';
                $collected_data=array('url'=>$cont_url,'fields'=>$field_vals);
                if($this->cur_c_module['datahub']||$this->cur_c_module['dataset']){
                    
                    $collected_data['data_module_source_url']=$this->cur_cont_source_url;
                }
                if($is_loop){
                    
                    $collected_data['url'].='#'.md5(serialize($field_vals));
                }else{
                    
                    if(isset($this->exclude_cont_urls[$md5_cont_url])){
                        
                        $collected_error=reset($this->exclude_cont_urls[$md5_cont_url]);
                        $collected_error=$this->exclude_url_msg($collected_error);
                    }
                }
                if(empty($collected_error)){
                    if(!empty($this->config['field_title'])){
                        
                        $collected_data['title']=$field_vals[$this->config['field_title']]['value'];
                        if(!empty($collected_data['title'])){
                            
                            if($mcollected->collGetNumByTitle($collected_data['title'],$this->task_id,g_sc_c('caiji','same_title'))>0){
                                
                                $collected_error='标题重复：'.mb_substr($collected_data['title'],0,300,'utf-8');
                            }
                        }
                    }
                }
                if(empty($collected_error)){
                    if(!empty($this->config['field_content'])){
                        
                        $collected_data['content']=array();
                        foreach($this->config['field_content'] as $fcField){
                            $collected_data['content'][$fcField]=$field_vals[$fcField]['value'];
                        }
                        if(!empty($collected_data['content'])){
                            
                            ksort($collected_data['content']);
                            $collected_data['content']=implode("\r\n", $collected_data['content']);
                            if($mcollected->collGetNumByContent($collected_data['content'],$this->task_id,g_sc_c('caiji','same_content'))>0){
                                
                                $collected_error='内容重复';
                            }
                        }else{
                            $collected_data['content']='';
                        }
                    }
                }
                if(empty($collected_error)){
                    
                    if(!is_empty(g_sc_c('caiji','real_time'))){
                        
                        
                        $rtRele=g_sc('real_time_release');
                        if($rtRele){
                            $rtRele->doExport(array($collected_data));
                            unset($collected_data['fields']);
                            unset($collected_data['title']);
                        }
                    }
                    
                    $this->collected_field_list[]=$collected_data;
                }else{
                    
                    \util\Tools::controller('ReleaseBase','event')->record_collected(
                        $collected_data['url'],
                        array('id'=>0,'error'=>$collected_error),array('task_id'=>$this->task_id,'module'=>$this->release['module'])
                    );
                }
            }
        }
        
        if($is_loop){
            
            
            \util\Tools::controller('ReleaseBase','event')->record_collected(
                $cont_url,array('id'=>1,'target'=>'','desc'=>'循环入库'.($loopExcludeNum>0?('，数据处理筛除了'.$loopExcludeNum.'条数据'):'')),array('task_id'=>$this->task_id,'module'=>$this->release['module']),null,false
            );
        }
    }
    
}