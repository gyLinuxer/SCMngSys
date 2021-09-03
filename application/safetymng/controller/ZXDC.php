<?php
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;

class ZXDC extends PublicController{
    public function index($id=NULL){
        if(!$this->IsSuperCorp()){
            return '您没有权限查看本页面!';
        }
        if(empty($id)){
            return;
        }
        $row = db('ZXDC')->where(array('id'=>$id))->select()[0];
        if(empty($row)){
            return '不存在!';
        }
        $SQL = base64_decode($row['DCSqlBase64']);
        $Ret = db()->query($SQL);
        $this->assign('DCName',$row['DCName']);
        $this->assign('ZXDCRetList',$Ret);
        return view('ZXDC');
    }
}