<?php
namespace app\safetymng\controller;
use think\db;
use think\controller;

class QuestionMng extends PublicController
{
    public function index()
    {

    }
    public function showQuestionMng($TaskID=NULL)
    {
        //首先根据TaskID找出问题或者整改
        $Ret = TaskCore::FindReformOrQuestionByTaskID($TaskID);
        if(empty($Ret['Ret'])){
            return "关联问题不存在!";
        }else if($Ret['Type']=='Reform'){
            $QuestionID  = $Ret['Ret']["RelatedQuestionID"];
        }else if($Ret['Type']=='Question'){
            $QuestionID  = $Ret['Ret']["id"];
        }

        $this->FillSessionFakeCorpAndRole($QuestionID);
        $dataRow  = db('QuestionList')->where(array("id"=>$QuestionID))->select()[0];
        if(empty($dataRow["DealType"])){
            $this->assign("showMng","YES");
        }else if($dataRow["DealType"]=='整改'){
            $this->assign("showReformList","YES");
        }
        $this->assign("TaskID",$TaskID);
        $this->assign("dataRow",$dataRow);
        return view('index');
    }
    static public function FillSessionFakeCorpAndRole($QuestionID){
        $Question = db()->query("SELECT TaskID From QuestionList WHERE ID = ? ",array($QuestionID));
        if(empty($Question)){
            return ;
        }
        $TaskID = $Question[0]["TaskID"];
        $Ret = db()->query("SELECT * FROM TaskDealerGroup WHERE TaskID = ? ",array($TaskID));
        if(!empty($Ret)){
            $FakeRoleArr = session("FakeRoleArr");
            if(empty($FakeRoleArr)){
                $FakeRoleArr = array($TaskID=>$Ret[0]);
            }else{
                $FakeRoleArr[$TaskID] = $Ret[0];
            }
            session("FakeRoleArr",$FakeRoleArr);
        }
        /*结构：
        session["FakeRoleArr"] => {TaskID=>{
                                          Name,Corp,Role,GroupID
        }}*/
    }
    public function setQuestionDealType($TaskID,$Type){//0:整改 1:SMS 2:安全隐患
        $Type_Arr = array(0=>'整改',1=>'SMS',2=>'安全隐患');
        $Question = db()->query("SELECT * FROM QuestionList WHERE id in (SELECT RelateID FROM TaskList WHERE ID = ?)",array($TaskID));
        if(empty($Question)){
            $this->assign("Warning","关联问题不存在");
        }else if(empty($Question[0]['DealType'])){//没有设置关联类型
           db()->query("UPDATE QuestionList SET DealType = ? WHERE id = ?",array($Type_Arr[$Type],$Question[0]["id"]));
           db()->query("UPDATE TaskList SET TaskType = ? WHERE id = ?",array(TaskCore::QUESTION_REFORM,$TaskID));
        }
        return $this->showQuestionMng($TaskID);
    }
}
