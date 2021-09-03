<?php
namespace app\SafetyMng\controller;
use think\db;
use think\controller;
use think\Session;

class TaskCore extends PublicController{
    public static $TaskTypes = [];
    //问题默认接收部门
    const QUESTION_DEFAULT_RECEIVECORP = '质检科';
    const QUESTION_SUBMITED = '问题-待处理';
    //整改主任务状态
    const QUESTION_REFORM = '问题-整改';
    //整改子任务状态
    const REFORM_UNDEFINED_ACTION  = '整改-待制定措施';
    const REFORM_ACTION_ISNOTOK = '整改-措施不通过';
    const REFORM_ACTION_ISOK = '整改-措施通过';
    const REFORM_UNDEFINE_PROOF = '整改-待整证据上传';
    const REFORM_PROOF_ISNOTOK  = '整改-整改效果不通过';
    const REFORM_PROOF_ISOK  = '整改-整改效果通过';

    const DEFAULT_RECEIVECORP   = '质检科';

    public  function index()
    {
        $data = [];
        echo is_null($data['55'])."-->".empty($data['55']).'-->'.dump(isset($data['55']));
    }

    static  public function FindReformOrQuestionByTaskID($TaskID){
        $TaskData = db()->query("SELECT * FROM TaskList WHERE ID= ?",array($TaskID));
        $TaskType = $TaskData[0]["TaskType"];
        $Data_Ret = array('Ret'=>'','Type'=>'');
        if(strpos($TaskType, TaskCore::QUESTION_SUBMITED) === 0
            || strpos($TaskType, '问题-整改') === 0){//问题处理中的整改分支，则RelatedID为QuesitonID
            $Question = db()->query("SELECT * FROM QuestionList WHERE ID=?",array($TaskData[0]["RelateID"]))[0];
            $Data_Ret['Type'] = 'Question';
            $Data_Ret['Ret']  = $Question;
        }else if(strpos($TaskType, '整改')===0){//整改子任务,则RelatedID为ReFormID
            $Reform = db()->query("SELECT * FROM ReformList WHERE ID = ? ",array($TaskData[0]["RelateID"]))[0];
            $Data_Ret['Type'] = 'Reform';
            $Data_Ret['Ret']  = $Reform;
        }
        return $Data_Ret;
    }

    static  public  function CreateTask($TaskData){

        $Ret_Data = array("Ret"=>"","ID"=>0);

        $MustFilled = ['TaskType','TaskName','ReciveCorp','RelateID','CreateTime','ParentID'];
        foreach ($MustFilled as $Must){
            if(!isset($TaskData[$Must])){
                $Ret_Data['Ret'] = $Must.'-->任务要素不完整!';
                goto OUT;
            }
        }

        $INSRET_ID =  db('tasklist')->data($TaskData)->insert();
        $Ret_Data['SQL'] = db()->getLastSql();

        if($INSRET_ID>0){
            $Ret_Data['ID'] =db('tasklist')->getLastInsID();
        }else{
            $Ret_Data['Ret'] = '任务创建失败!';
        }

        OUT:
            return $Ret_Data;
    }

    static function isTaskCreated($TaskType,$RelatedID)
    {
        $Ret =  db('tasklist')->field('id')->where(array("TaskType"=>$TaskType,'RelateID'=>$RelatedID))->select();
        if(empty($Ret)){
            return '';
        }else{
            return $Ret[0]['id'];
        }
    }

    function showTaskAlign($TaskID)
    {
        $Ret =  db('tasklist')->field('DealGroupID')->where(array("id"=>$TaskID))->select();
        $this->assign("TaskID",$TaskID);
        if(empty($Ret)){
            return '该编号任务不存在!';
        }elseif(!empty($Ret[0]['DealGroupID'])){
            return '任务已分配!';
        }
        $Ret = db('userlist')->where(array("Corp"=>session("Corp")))->select();
        $this->assign("PersonList",$Ret);
        return view('TaskAlign');
    }
    public  function GetUniqueGroupID()
    {
        return date('YmdHis').rand(100,999);
    }
    function TaskAlign(){
        $TaskID = input("TaskID");
        $Manager = input('ManagerSelect');
        $GroupDealer = input('post.GroupDealer/a');
        $Msg = trim(input('TaskMsg'));
       if(empty($TaskID) || empty($Manager)) {
           return "任务ID与被任务组长不可为空!";
       }
       //检查权限
       $Role = session("CorpRole");
       if(empty($Role) || $Role!='领导'){
            return "权限不足!";
       }
       $Ret =  db('tasklist')->field('DealGroupID')->where(array("id"=>$TaskID))->select();
       if(empty($Ret)){
           return '该编号任务不存在!';
       }
       if(empty($Ret[0]['DealGroupID'])){//任务未分配
           //写入组长
           $DealGroupID = $this->GetUniqueGroupID();
           $data = array();
           $data["TaskID"]= $TaskID;
           $data["GroupID"]= $DealGroupID;
           $data["Name"]= $Manager;
           $data["Role"]= '组长';
           $data["Corp"]= session("Corp");
           $data["AddTime"]= date("Y-m-d H:i:s");
           db('taskdealergroup')->insert($data);
           //写入组员
           $GroupMember = $Manager.' ';
           foreach ($GroupDealer as $Dealer){
               if($Dealer != $Manager){
                   $data["Role"]= '组员';
                   $data["Name"]= $Dealer;
                   $GroupMember.= $Dealer.' ';
                   db('taskdealergroup')->insert($data);
               }
           }

           db('tasklist')->where(array("id"=>$TaskID))->update(array("DealGroupID"=>$DealGroupID,'GroupMember'=>$GroupMember));
           if(!empty($Msg)){
                //发送任务消息
               db('taskmsg')->insert(array("TaskID"=>$TaskID,
                                                   "SenderName"=>session("Name"),
                                                    "ReceiveGroup"=>$DealGroupID,
                                                    "Msg"=>$Msg,
                                                    "CreateTime"=>date("Y-m-d H:i:s"),
                                                    "State"=>0));
           }
       }else{//任务已分配给别人
            return "任务已分配";
       }
       return "任务分配成功！";
    }
    function showMsg($TaskID)
    {
        $this->assign("MsgList",db("taskmsg")->where(array("TaskID"=>$TaskID))->order("CreateTime DESC")->select());
        return view('showMsg');
    }
}