<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;

class JFUserViewMng extends PublicController{
    public function index(){
        return view('index');
    }

    public function AddJFUserView(){
        $SourceName  = input('SourceName');
        $UserID = input('UserID');
        $Source = db('QuestionSource')->where(['SourceName'=>$SourceName])->select();
        $User = db('UserList')->where(['id'=>$UserID])->select();
        if(empty($Source) || empty($User)){
            return '用户不存在或者问题源不存在';
        }

        $Data = [];
        $Data['SourceID']       = $Source[0]['id'];
        $Data['SourceName']     = $Source[0]['SourceName'];
        $Data['UserID']         = $User[0]['id'];
        $Data['UserName']       = $User[0]['Name'];
        $Data['AddTime']        = date('Y-m-d h:i:s');
        $R = db('JFViewSource_User_Cross')->where(['SourceID'=>$Data['SourceID'],"UserID"=>$Data['UserID']])->select();
        if(!empty($R)){
            return '该视图范围已在该用户视图中存在!';
        }

        $R = db('JFViewSource_User_Cross')->insertGetId($Data);
        if(!empty($R)){
            return 'OK';
        }else{
            return '增加视图失败!';
        }
    }

    public function GetJFUserViewList(){
        $R = db('JFViewSource_User_Cross')->select();
        return json_encode($R,JSON_UNESCAPED_UNICODE);
    }

    public function DelJFUserViewById(){
        $id = input('id');
        db('JFViewSource_User_Cross')->where(["id"=>$id])->delete();
    }
}