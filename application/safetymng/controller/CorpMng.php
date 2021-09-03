<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;
//此模块应该加入禁止访问序列？？
class CorpMng  {
    public function index(){

    }

    public function GetCorpInfo($Corp=NULL){
        if(empty($Corp)){
            return NULL;
        }
        $CorpInfo = db('CorpList')->where(array('Corp'=>$Corp))->select()[0];
        return $CorpInfo;
    }

    public function GetAllCorpsInGroupCorp($GroupCorp = NULL){
        if(empty($GroupCorp)){
            return NULL;
        }

        $Ret = db('CorpList')->where(array('GroupCorp'=>$GroupCorp))->select();
        return $Ret;
    }

    public function GetChildrenCorps($Corp){
        $Children= array($Corp);
        $Ret = array($Corp);
        do{
            $t = db('CorpList')->field('Corp')->where(array('ParentCorp'=>array('IN',$Children)))->select();
            $Children = array_column($t,'Corp');
            $Ret = array_merge($Ret,$Children);
        }while(!empty($Children));
        return $Ret;
    }

    public function  GetCorpUserList($Corp){//可以看到子孙部门的所有问题
       $Ret = db('UserList')->join('CorpList','UserList.Corp = CorpList.Corp')->order('UserList.Corp,UserList.Name')->where(array('UserList.Corp'=>array('IN',$this->GetChildrenCorps($Corp))))->select();
       return $Ret;
    }

    public function GetGroupCorpUserList($GroupCorp){
        $Ret = db('UserList')->join('CorpList','UserList.Corp = CorpList.Corp')->where(array('UserList.Corp'=>array('IN',$this->GetChildrenCorps($GroupCorp))))->order('UserList.Corp,Name')->select();
       return $Ret;
    }

    public function GetMyCanControlCorps(){//根据session中的人员部门，若为超级部门，则可以控制所有部门
        $Corp = session('Corp');
        if(session('CorpInfo')['IsSuperCorp'] == 'YES'){
            $Corp = session('CorpInfo')['GroupCorp'];
        }
        return json_encode($this->GetCorpAllLevelChildrenRows($Corp),JSON_UNESCAPED_UNICODE);
    }



    public function GetMyCanControlUsers(){
        $Corp = session('Corp');

        $UserList = [];
        $Corps = [];
        if(session('CorpInfo')['IsSuperCorp'] == 'YES'){
            $Corps = CorpMng::GetCorpAllLevelChildrenRows(session('CorpInfo')['GroupCorp']);
        }else{
            $Corps = CorpMng::GetCorpAllLevelChildrenRows($Corp);
        }

        $UserList = db('UserList')->whereIn('Corp',$Corps)->order('Name')->select();

        return json_encode($UserList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

    }


    //可以提交给本级部门、大部门的执法部门、以及上级、上上级...的执法部门
    public function GetQsDefaultRecvCorpList(){
        $GroupCorp = session('CorpInfo')['GroupCorp'];
        $GroupCorpList = [];
        $Ret = $GroupCorp;


        while(!empty($Ret)){
            $GroupCorpList[] = $Ret;
            $row = db('CorpList')->where(['Corp'=>$Ret])->select();
            if(!empty($row)){
                $Ret = $row[0]['ParentCorp'];
            }
        }

        $GroupCorpList[] = session('Corp');

        return $GroupCorpList;
    }

    static public function GetCorpAllLevelChildrenRows($Corp){

        if(empty($Corp)){
            $Ret = db('CorpList')->select();
            $tRet = array_column($Ret,'Corp');
            $Ret = array_unique($tRet);
        }else{
            $Ret = [];
            $CorpRow = db('CorpList')->field('Corp')->where(['Corp'=>$Corp])->select();
            $CorpRow = array_column($CorpRow,'Corp');
            if(empty($CorpRow)){
                return [];
            }

            $tRet = db('CorpList')->field('Corp')->whereIn('ParentCorp',$Corp)->select();
            $Ret = array_merge($Ret,$CorpRow);
            $tRet = array_column($tRet,'Corp');
            $Ret = array_merge($Ret,$tRet);
            $Ret = array_unique($Ret);

            while(!empty($tRet)){
                $tRet = db('CorpList')->field('Corp')->whereIn('ParentCorp',$tRet)->select();
                $tRet = array_column($tRet,'Corp');
                $Ret = array_unique(array_merge($Ret,$tRet));
            }
        }


        return $Ret;
    }


    //返回 ('部门1','部门2',....)
    public  function  GetCorpAllLevelChildrenRows_InSqlStr($ParentCorp) {
        $MyAllLevelChildren = CorpMng::GetCorpAllLevelChildrenRows($ParentCorp);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }

        if(strlen($InCaseStr)==1){
            $InCaseStr.='0)';
        }else{
            $InCaseStr[strlen($InCaseStr)-1]= ')';
        }
        return $InCaseStr;
    }

    public function GetCorpList(){
        $CorpList = db('CorpList')->select();
        $CorpList[]=["Corp"=>'全部'];
        return json_encode($CorpList,JSON_UNESCAPED_UNICODE);
    }

    public function GetAllParentCorp(){
        $List = db()->query("SELECT DISTINCT ParentCorp as Corp FROM CorpList WHERE ParentCorp IS NOT NULL  AND ParentCorp <> '' ");
        return $List;
    }

    public static function GetCorpAllLevelChildrenRows_JSON($ParentCorp=''){
        $List = CorpMng::GetCorpAllLevelChildrenRows($ParentCorp);
        return json_encode($List,JSON_UNESCAPED_UNICODE);
    }

}