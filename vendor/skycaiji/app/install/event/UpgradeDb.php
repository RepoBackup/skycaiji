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

namespace skycaiji\install\event;
use think\db\Query;
/*数据库升级操作*/
class UpgradeDb extends UpgradeDbVers{
	/*后台更新时升级入口：当所有更新文件下载替换完毕，最后需要升级数据库*/
	public function run(){
	    \util\Tools::clear_runtime_dir();
		$url=url('install/upgrade/admin',null,true,true);
		header('Location:'.$url);
		exit();
	}
	/*正常的升级入口*/
	public function upgrade(){
		set_time_limit(0);
		\util\Tools::clear_runtime_dir();
		\util\Tools::load_data_config();
		$result=$this->execute_upgrade();
		if($result['success']){
			$mconfig=model('common/Config');
			$mconfig->setVersion($this->get_skycaiji_version());
		}
		return $result;
	}
	/*获取版本号*/
	public function get_skycaiji_version(){
		$newProgramConfig=file_get_contents(config('app_path').'/common.php');
		if(preg_match('/[\'\"]SKYCAIJI_VERSION[\'\"]\s*,\s*[\'\"](?P<v>[\d\.]+?)[\'\"]/i', $newProgramConfig,$programVersion)){
			$programVersion=$programVersion['v'];
		}else{
			$programVersion='';
		}
		return $programVersion;
	}
	
	public function execute_upgrade(){
		$mconfig=model('common/Config');
		$dbVersion=$mconfig->getVersion();
		$fileVersion=$this->get_skycaiji_version();
		
		if(empty($dbVersion)){
		    return return_result('未获取到数据库中的版本号');
		}
		if(empty($fileVersion)){
		    return return_result('未获取到项目文件的版本号');
		}
		
		if(version_compare($dbVersion,$fileVersion)>=0){
		    
		    return return_result('数据库已是最新版本，无需更新',true);
		}
		/*找出更新函数*/
		$methods=get_class_methods($this);
		$upgradeDbMethods=array();
		foreach ($methods as $method){
			if(preg_match('/^upgrade_db_to(?P<ver>(\_\d+)+)$/',$method,$toVer)){
				
				$toVer=str_replace('_', '.', trim($toVer['ver'],'_'));
				if(version_compare($toVer,$dbVersion)>=1){
					
					if(version_compare($toVer,$fileVersion)<=0){
						
						$upgradeDbMethods[$toVer]=$method;
					}
				}
			}
		}
		if(empty($upgradeDbMethods)){
		    return return_result('暂无更新',true);
		}
		ksort($upgradeDbMethods);
		foreach ($upgradeDbMethods as $newVer=>$upMethod){
			try {
				$this->$upMethod();
				
				$mconfig->setVersion($newVer);
			}catch (\Exception $ex){
			    return return_result($ex->getMessage());
			}
		}
		
		\util\Tools::clear_runtime_dir();
		
		return return_result('升级完毕',true);
	}
	
	
	
	public function upgrade_db_to_3_1(){
	    $db_prefix=config('database.prefix');
	    
	    $table=$db_prefix.'datahub';
	    $exists=db()->query("show tables like '{$table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$table}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL DEFAULT '0',
  `c_url_md5` varchar(32) NOT NULL DEFAULT '',
  `url` text,
  `addtime` bigint(20) NOT NULL DEFAULT '0',
  `uptime` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `tid` (`task_id`,`id`),
  KEY `c_url_md5` (`c_url_md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    $table=$db_prefix.'datahub_field';
	    $exists=db()->query("show tables like '{$table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$table}` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name_md5` varchar(32) NOT NULL DEFAULT '',
  `name` text,
  PRIMARY KEY (`id`),
  KEY `name_md5` (`name_md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    $table=$db_prefix.'datahub_info';
	    $exists=db()->query("show tables like '{$table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$table}` (
  `id` bigint(20) NOT NULL DEFAULT '0',
  `task_id` int(11) NOT NULL DEFAULT '0',
  `field_id` int(11) NOT NULL DEFAULT '0',
  `content` MEDIUMTEXT,
  KEY `id` (`id`),
  KEY `t_f_c` (`task_id`,`field_id`,`content`(5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
PARTITION BY HASH(id)
PARTITIONS 128;
EOF;
	        db()->execute($addTable);
	    }

	    
	    $timeFields = array(
	        'api_app' => array('addtime','uptime'),
	        'app' => array('addtime','uptime'),
	        'cache' => array('dateline'),
	        'collector' => array('addtime','uptime'),
	        'config' => array('dateline'),
	        'func_app' => array('addtime','uptime'),
	        'proxy_ip' => array('addtime'),
	        'release' => array('addtime'),
	        'release_app' => array('addtime','uptime'),
	        'rule' => array('addtime','uptime'),
	        'task' => array('addtime','caijitime'),
	        'user' => array('regtime'),
	        'collected' => array('addtime'),
	    );
	    foreach ($timeFields as $k=>$v){
	        $table=$db_prefix.$k;
	        $exists=db()->query("show tables like '{$table}'");
	        if($exists){
	            $columns=db()->query("SHOW COLUMNS FROM `{$table}`");
	            foreach ($v as $vv){
	                $this->modify_field_type($vv, 'bigint(20)', "alter table `{$table}` modify column `{$vv}` bigint NOT NULL DEFAULT 0", $columns);
	            }
	        }
	    }
	    
	    $dbTables=db()->getConnection()->getTables(config('database.database'));
	    init_array($dbTables);
	    foreach ($dbTables as $v){
	        $v=strtolower($v);
	        if(stripos($v,$db_prefix.'cache_')===0){
	            
	            $columns=db()->query("SHOW COLUMNS FROM `{$v}`");
	            $this->modify_field_type('dateline', 'bigint(20)', "alter table `{$v}` modify column `dateline` bigint NOT NULL DEFAULT 0", $columns);
	        }
	    }
	    
	    $columns=db()->query("SHOW COLUMNS FROM `{$db_prefix}collected`");
	    $this->modify_field_type('id', 'bigint(20)', "alter table `{$db_prefix}collected` modify column `id` bigint(20) NOT NULL AUTO_INCREMENT", $columns);
	    
	    $columns=db()->query("SHOW COLUMNS FROM `{$db_prefix}collected_info`");
	    $this->modify_field_type('id', 'bigint(20)', "alter table `{$db_prefix}collected_info` modify column `id` bigint NOT NULL DEFAULT 0", $columns);
	    
	    $this->table_add_indexes('collected', array('ix_u5_tid'=>"`urlMd5`,`task_id`"));
	    $indexes_collected=db()->query("SHOW INDEX FROM `{$db_prefix}collected`");
	    if($this->check_exists_index('ix_urlmd5', $indexes_collected)){
	        
	        db()->execute("ALTER TABLE `{$db_prefix}collected` DROP INDEX ix_urlmd5");
	    }
	    
	    
	    $table=$db_prefix.'dataset_index';
	    $exists=db()->query("show tables like '{$table}'");
	    if(empty($exists)){
	        
$addTable=<<<EOF
CREATE TABLE `{$table}` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ds_id` int(11) NOT NULL DEFAULT '0',
  `dt_id` int(11) NOT NULL DEFAULT '0',
  `c_url_md5` varchar(32) NOT NULL DEFAULT '',
  `url` text,
  `addtime` bigint(20) NOT NULL DEFAULT '0',
  `uptime` bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `c_url_md5` (`c_url_md5`),
  KEY `ds_id` (`ds_id`,`id`),
  KEY `dt_id` (`dt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
EOF;
	        db()->execute($addTable);
	    }
	    
	    $mds=model('Dataset');
	    $dsIds=$mds->column('id');
	    if($dsIds){
	        foreach ($dsIds as $dsId){
	            $mdt=\skycaiji\common\model\DatasetTable::getInstance($dsId);
	            $dtCount=$mdt->db()->count();
	            if($dtCount>0){
	                
	                $dtLimit=100;
	                $dtPage=ceil($dtCount/$dtLimit);
	                for($i=0;$i<$dtPage;$i++){
	                    $dtIds=$mdt->db()->order('id asc')->limit($dtLimit*$i,$dtLimit)->column('id');
	                    $cUrlMd5s=array();
	                    $diDatas=array();
	                    foreach ($dtIds as $dtId){
	                        $diData=array('ds_id'=>$dsId,'dt_id'=>$dtId,'url'=>'','addtime'=>0,'uptime'=>0);
	                        $diData['c_url_md5']=\util\Tools::create_skycaiji_url('dataset',$dsId,$dtId);
	                        $diData['c_url_md5']=md5($diData['c_url_md5']);
	                        $cUrlMd5s[$diData['c_url_md5']]=$diData['c_url_md5'];
	                        $diDatas[]=$diData;
	                    }
	                    
	                    $existDis=$mds->indexDb()->where('c_url_md5','in',$cUrlMd5s)->column('id','c_url_md5');
	                    if($existDis){
	                        foreach ($diDatas as $k=>$v){
	                            if(isset($existDis[$v['c_url_md5']])){
	                                unset($diDatas[$k]);
	                            }
	                        }
	                    }
	                    if($diDatas){
	                        $diDatas=array_values($diDatas);
	                        $mds->indexDb()->strict(false)->insertAll($diDatas);
	                    }
	                }
	            }
	        }
	    }
	}
}
?>