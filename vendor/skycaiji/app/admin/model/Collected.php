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

namespace skycaiji\admin\model;
/*采集到的数据库*/
class Collected extends \skycaiji\common\model\BaseModel{
    public function __construct($data=[]){
        try {
            parent::__construct($data);
            $this->getPk();
        }catch (\Exception $ex){
            
            $this->create_table();
            parent::__construct($data);
        }
    }
    public function collected_info_tname(){
        return config('database.prefix').'collected_info';
    }
    public function getInfoDatas($collectedList){
        init_array($collectedList);
        $ids=array();
        foreach ($collectedList as $k=>$v){
            $ids[$v['id']]=$v['id'];
            if($v&&!is_array($v)){
                $v=$v->toArray();
                $collectedList[$k]=$v;
            }
        }
        if($ids){
            $infoList=db()->table($this->collected_info_tname())->where('id','in',$ids)->column('*','id');
            foreach ($collectedList as $k=>$v){
                $info=$infoList[$v['id']];
                if(is_array($info)&&$info){
                    $v=array_merge($v,$info);
                    $v['target']=$this->convertTarget($v['release'],$v['target']);
                    $collectedList[$k]=$v;
                }
            }
        }
        return $collectedList;
    }
    
    public function convertTarget($release,$target){
        if($target){
            if($release=='dataset'){
                
                if(preg_match('/\@(\d+)\:(\d+)/',$target,$mid)){
                    $target=sprintf('<a href="%s" target="_blank">%s</a>',url('dataset/info?ds_id='.$mid[1].'&dt_id='.$mid[2]),$target);
                }
            }elseif($release=='datahub'){
                
                if(preg_match('/\@(\d+)/',$target,$mid)){
                    $target=sprintf('<a href="%s" target="_blank">%s</a>',url('datahub/info?id='.$mid[1]),$target);
                }
            }
        }
        return $target;
    }
    public function addInfo($infoData){
        init_array($infoData);
        if($infoData){
            db()->table($this->collected_info_tname())->insert($infoData);
        }
    }
    
    public function deleteById($id,$clearData=false){
        $result=return_result('',false,array('clear_datahub'=>false,'clear_dataset'=>false));
        if($id){
            if(!$clearData){
                $cond=array();
                if(is_array($id)){
                    $cond=array('id'=>array('in',$id));
                }else{
                    $cond=array('id'=>$id);
                }
                db()->table($this->collected_info_tname())->where($cond)->delete();
                $this->where($cond)->delete();
                $result['success']=true;
            }else{
                
                if(!is_array($id)){
                    $id=array($id);
                }
                $mdh=model('Datahub');
                $mds=model('Dataset');
                foreach ($id as $v){
                    $data=$this->where('id',$v)->find();
                    $info=db()->table($this->collected_info_tname())->where('id',$v)->find();
                    if($data&&$info){
                        $release=$data['release'];
                        $target=$info['target'];
                        if($release=='datahub'){
                            if(preg_match('/^\@(\d+)$/',$target,$match)){
                                $mdh->deleteById($match[1]);
                                $result['clear_datahub']=true;
                            }
                        }elseif($release=='dataset'){
                            if(preg_match('/^\@(\d+)\:(\d+)$/',$target,$match)){
                                $mds->deleteById(0,$match[1],$match[2]);
                                $result['clear_dataset']=true;
                            }
                        }
                    }
                    db()->table($this->collected_info_tname())->where('id',$v)->delete();
                    $this->where('id',$v)->delete();
                }
                $result['success']=true;
                $msg=array();
                if($result['clear_datahub']){
                    $msg[]=lang('cdatahub');
                }
                if($result['clear_dataset']){
                    $msg[]=lang('cdataset');
                }
                if($msg){
                    $msg=implode(',', $msg);
                    $msg='已清空相应'.$msg.'的数据';
                    $result['msg']=$msg;
                }
            }
        }
        return $result;
    }
    
    public function deleteByCond($cond){
        init_array($cond);
        if($cond){
            $idSql=$this->db()->fetchSql(true)->field('id')->where($cond)->select();
            db()->execute('delete from '.$this->collected_info_tname().' where id in ('.$idSql.')');
            $this->where($cond)->delete();
        }
    }
	/*采集时获取的数据*/
	public function collGetNumByUrl($urls,$status=null,$taskId=null,$sameUrl=false){
	    $cond=array();
	    if(is_array($urls)){
	        $cond['urlMd5']=array('in',array_map('md5', $urls));
	    }else{
	        
	        $url=preg_replace_callback('/^\w+\:\/\//', function($match){
	            $match=strtolower($match[0]);
	            if($match=='http://'){
	                $match='https://';
	            }elseif($match=='https://'){
	                $match='http://';
	            }
	            return $match;
	        }, $urls);
	        $md5Urls=md5($urls);
	        $md5Url=md5($url);
	        if($md5Urls!=$md5Url){
	            
	            $cond['urlMd5']=array(array('eq',$md5Urls),array('eq',$md5Url),'or');
	        }else{
	            $cond['urlMd5']=$md5Urls;
	        }
	    }
	    if($sameUrl){
	        
	        $cond=$this->_coll_cond_set_tid($cond,$taskId);
	    }
	    if(isset($status)){
	        
	        $cond['status']=$status?1:0;
	    }
	    return $this->where($cond)->count();
	}
	public function collGetNumByTitle($title,$taskId=null,$sameTitle=false){
		if(empty($title)){
			return 0;
		}
		$title=md5($title);
		$cond=array('titleMd5'=>$title);
		if($sameTitle){
		    
		    $cond=$this->_coll_cond_set_tid($cond,$taskId);
		}
		return $this->where($cond)->count();
	}
	public function collGetNumByContent($content,$taskId=null,$sameContent=false){
	    if(empty($content)){
	        return 0;
	    }
	    $content=md5($content);
	    $cond=array('contentMd5'=>$content);
	    if($sameContent){
	        
	        $cond=$this->_coll_cond_set_tid($cond,$taskId);
	    }
	    return $this->where($cond)->count();
	}
	public function collGetUrlByUrl($urls,$taskId=null,$sameUrl=false){
	    init_array($urls);
		$urls=array_filter($urls);
		$dbUrls=array();
		if($urls){
		    $urls1=array();
		    foreach ($urls as $k=>$v){
		        $urls1[md5($v)]=$v;
		        unset($urls[$k]);
		    }
		    $urls=$urls1;
		    unset($urls1);
		    $cond=array('urlMd5'=>array('in',array_keys($urls)));
		    if($sameUrl){
		        
		        $cond=$this->_coll_cond_set_tid($cond,$taskId);
		    }
		    $dbUrls=$this->field('`id`,`urlMd5`')->where($cond)->column('urlMd5','id');
		    if(!empty($dbUrls)){
		        foreach ($dbUrls as $k=>$v){
		            $v=$urls[$v];
		            if($v){
		                $dbUrls[$k]=$v;
		            }else{
		                unset($dbUrls[$k]);
		            }
		        }
		    }
		}
		return $dbUrls;
	}
	private function _coll_cond_set_tid($cond,$taskId){
	    $cond=is_array($cond)?$cond:array();
	    if(!empty($taskId)){
	        $cond['task_id']=$taskId;
	    }
	    return $cond;
	}
	/**
	 * 创建表
	 * @return boolean
	 */
	public function create_table(){
		$tname=$this->get_table_name();
		$exists=db()->query("show tables like '{$tname}'");
		if(empty($exists)){
			
$table=<<<EOT
CREATE TABLE `{$tname}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `urlMd5` varchar(32) NOT NULL DEFAULT '',
  `release` varchar(10) NOT NULL DEFAULT '',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `addtime` bigint(20) NOT NULL DEFAULT '0',
  `titleMd5` varchar(32) NOT NULL DEFAULT '',
  `contentMd5` varchar(32) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ix_taskid` (`task_id`),
  KEY `ix_addtime` (`addtime`),
  KEY `ix_titlemd5` (`titleMd5`),
  KEY `ix_contentmd5` (`contentMd5`),
  KEY `ix_status` (`status`),
  KEY `ix_u5_tid` (`urlMd5`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOT;
			db()->execute($table);
		}
		
		$tname=$this->collected_info_tname();
		$exists=db()->query("show tables like '{$tname}'");
		if(empty($exists)){
		    
$table=<<<EOT
CREATE TABLE `{$tname}` (
  `id` bigint(20) NOT NULL DEFAULT '0',
  `url` text,
  `target` text,
  `desc` text,
  `error` text,
  KEY `ix_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOT;
			db()->execute($table);
		}
	}
}

?>