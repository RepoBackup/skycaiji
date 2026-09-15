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

/*采集器：本地文件采集*/
class Clocalfile extends CollectorController {
    public function localfile11(){
        if(request()->isPost()){
            $files=input('files/a',array(),'trim');
            $infos=array();
            foreach ($files as $k=>$v){
                $v1=array('is_dir'=>false,'is_none'=>false);
                if(file_exists($v)){
                    if(is_dir($v)){
                        $v1['is_dir']=true;
                    }
                }else{
                    $v1['is_none']=true;
                }
                $infos[$k]=$v1;
            }
            if(empty($files)){
                $this->error('未选中文件');
            }else{
                $this->success('已添加','',array('files'=>$files,'infos'=>$infos));
            }
        }else{
            $filePath=input('file','','trim');
            $filePath=str_replace('/', DIRECTORY_SEPARATOR, $filePath);
            $filePath=realpath($filePath);
            
            if($filePath&&!is_dir($filePath)){
                $this->error('不是文件夹');
            }
            if(empty($filePath)){
                
                $filePath=config('root_path').DIRECTORY_SEPARATOR.'data';
            }
            $filePath=realpath($filePath);
            $files=array();
            if(is_dir($filePath)){
                
                $fileList=scandir($filePath);
                foreach ($fileList as $v){
                    if($v=='.'||$v=='..'){
                        
                        continue;
                    }
                    $fileInfo=array('dir'=>'','date'=>'');
                    $curFile=$filePath.DIRECTORY_SEPARATOR.$v;
                    if(is_dir($curFile)){
                        $fileInfo['dir']='1';
                    }
                    $curFile=realpath($curFile);
                    $fileInfo['file']=$curFile;
                    $fileInfo['date']=date("Y-m-d H:i:s",filemtime($curFile));
                    $files[$v]=$fileInfo;
                }
            }
            
            $this->assign('files',$files);
            $this->assign('filePath',$filePath);
            
            return $this->fetch();
        }
    }
    public function testAction(){
    }
    
    
    public function test_dataAction(){
    }
    public function add_defaultAction(){
        
    }
}