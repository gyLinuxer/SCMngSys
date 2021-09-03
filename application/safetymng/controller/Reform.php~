<?php
namespace app\safetymng\controller;
use think\Db;
use think\Request;

class Reform extends PublicController{
    public $ReformStatus = array('NonIssued'=>"未下发",
                                'ActionisNotDefined'=>"未分析原因制定措施",
                                'ActionIsMaked'=>"措施已制定待审核",
                                'ActionIsOk'=>"措施审核通过执行中",
                                'ActionIsNotOk'=>"措施审核不通过",
                                'ProofIsUploaded' =>"整改证据已上传待审核",
                                'ProofIsOk'=>"整改效果审核通过",
                                'ProofIsNotOk'=>"整改效果审核不通过");
    /*
     * 1、未下发
     * 2、未分析原因制定措施
     * 3、措施已制订待审核
     * 4、措施审核通过执行中
     * 5、措施审核不通过
     * 6、整改证据已上传待审核
     * 7、整改效果审核通过
     * 8、整改效果审核不通过
     * 20、非当前部门
     * */
    public $ReformStatus_AssginArr = array('未下发'=>1,
                                            '未分析原因制定措施'=>2,
                                            '措施已制定待审核'=>3,
                                            '措施审核通过执行中'=>4,
                                            '措施审核不通过'=>5,
                                            '整改证据已上传待审核'=>6,
                                            '整改效果审核通过'=>7,
                                            '整改效果审核不通过'=>8);
    public function index($TaskID=NULL,$ReformID =NULL,$opType='New')//opType :New Mdf
    {
        if(empty($TaskID)){
            return "任务ID不可为空!";
        }
        $Question = '';
        $Reform = '';
        //若有ReformID，则优先找到Reform，通过Reform来填充各个项目
        //若没有ReformID，则通过TaskID找到Question

        $Data = TaskCore::FindReformOrQuestionByTaskID($TaskID);
        ///dump($Data);
        if(empty($Data['Ret'])){
            return "任务ID错误!找不到关联问题和整改通知";
        }else{
            if($Data['Type']=='Question'){
                $Question = $Data['Ret'];
            }else if($Data['Type']=='Reform'){
                if($opType=='Mdf'){
                    $Reform = $Data["Ret"];
                }
            }
        }

        if($opType=='Mdf'){
            if(!empty($ReformID)){
                $Reform = db()->query("SELECT * FROM ReformList WHERE id=?",array($ReformID));
            }else{
                if(empty($Reform)){
                    return "整改通知书不存在!";
                }else{
                    $this->assign("Reform",$Reform[0]);
                }
            }
        }else{
            $this->assign("showSaveBtn","YES");
        }


        if(empty($Question) && empty($Reform)){
            return "关联问题不可为空";
        }else if(!empty($Reform) && $opType=='Mdf'){
            if(!empty($Reform)){
                $this->assign("NonConfirmDesc",htmlspecialchars_decode($Reform["NonConfirmDesc"]));
                $this->assign("QuestionTitle" ,$Reform[0]["QuestionTitle"]);
                $this->assign("ReformInfo",$Reform[0]);
                $this->assign("Reform",$Reform[0]);
                if($Reform[0]['ReformStatus']!= $this->ReformStatus['NonIssued']){
                    if($Reform[0]['CurDealCorp'] == session('Corp')){
                        $this->assign("showSaveBtn","YES");
                    }
                }else{
                    $this->assign("showSaveBtn","YES");
                }

            }else{
                return "整改通知单ID不存在!";
            }
        }else if(!empty($Question)){
            if(!empty($Question)){
                $this->assign("QuestionTitle",$Question["QuestionTitle"]);
                $this->assign("NonConfirmDesc" ,htmlspecialchars_decode($Question["QuestionInfo"]));
            }else{
                return "问题ID不存在!";
            }
        }

        $this->assign("opType",$opType);
        $this->assign("QuestionID",empty($Question)?0:$Question["id"]);
        $this->assign("TaskID",$TaskID);
        $this->assign("ReformID",empty($Reform)?0:$Reform[0]["id"]);
        $this->assign("ReformStatus",empty($Reform)?'':$Reform[0]["ReformStatus"]);
        $this->assign("ReformIntStatus",empty($Reform)?1:$this->ReformStatus_AssginArr[$Reform[0]["ReformStatus"]]);
        //dump($this->ReformStatus_AssginArr[$Reform[0]["ReformStatus"]]);
        $this->assign("Today",date("Y-m-d"));
        $this->assign("CorpList",db()->query("SELECT Distinct Corp FROM UserList"));
        $this->assign("QuestionSourceList",db('questionsource')->select());
        $this->assign("SuperCorp",$this->SuperCorp);

        return view('index');
    }

    public function JudgeUserRoleByTaskID($TaskID){
        //根据角色和任务分配
        //1、超级部门的领导和部领导在所有任务上都等同于监察员
        //2、其它部门的领导在其所有任务上等同于该任务的处理人
        //四种角色：超级部门的领导 本任务监察员 责任部门领导 责任部门本任务处理人员
        $CorpRole = session("CorpRole");//领导与成员
        $Corp = session('Corp');

        $Task = db()->query("SELECT * FROM TaskList WHERE id = ?",array($TaskID));
        $GroupInfo = db()->query("SELECT * FROM TaskDealerGroup WHERE TaskID = ? AND Name = ? ",array($TaskID,session('Name')));
        $TaskRole = '';
        if(empty($Task)){
            return "";
        }

        $TaskType = $Task[0]["TaskType"];
        $ReciveCorp = $Task[0]["ReciveCorp"];
        if(strpos($TaskType,'问题-整改')===0){//问题整改父任务
            if($CorpRole=='领导' && $Corp == $this->SuperCorp){
                //监察部门领导
                $TaskRole = 'JCY';
            }else if (!empty($GroupInfo)){//是监察员
                $TaskRole = 'JCY';
            }else {
                $TaskRole = '';//其它人员
            }
        }else if(strpos($TaskType,'整改')===0){//问题整改子任务
            if($CorpRole =='领导' &&$ReciveCorp == $Corp){
                $TaskRole = 'CLRY';//处理人员
            }else if(!empty($GroupInfo)){
                $TaskRole = 'CLRY';
            }else{
                $TaskRole = '';
            }
        }
        return $TaskRole;
    }

    public function showReformList($TaskID)
    {
        $TaskRole = $this->JudgeUserRoleByTaskID($TaskID);
        if (empty($TaskRole)) {
           return;
        }
        $Task = db()->query("SELECT * FROM TaskList WHERE id = ?", array($TaskID));
        if ($TaskRole == 'JCY') {//现实本任务中所有整改通知单
            $QuestionID = $Task[0]["RelateID"];
            $ReformList = db()->query("SELECT * FROM ReformList WHERE isDeleted = '否' AND id in (SELECT ToID FROM IDCrossIndex WHERE FromID = ?)", array($QuestionID));
        } else if ($TaskRole == 'CLRY') {//现实本任务管理的整改通知单
            $ReformList = db()->query("SELECT * FROM ReformList WHERE isDeleted = '否' AND  id = ? ", array($Task[0]["RelateID"]));
        } else {
            $ReformList = array();
        }
        $this->assign("ReformList", $ReformList);
        $this->assign("ReformCount", count($ReformList));
        $this->assign("Count", 1);
        $this->assign("TaskID", $TaskID);
        return view('ReformList');
    }


    public function SaveReform(){//New Fill
        //先看看当前操作者在该问题中是什么角色，然后结合整改通知书当前状态，决定他该干什么，能干什么
        $TaskID = input("TaskIDHid");
        $ReformID = input("ReformIDHid");
        $opType= input('opType');
        $ParentTaskType  = '';

        $Reform = '';
        $Question = '';
        if(empty($TaskID)){
            return "任务ID不可为空!";
        }
        if(empty($ReformID)){//如果$ReformID为空，父任务只会找到关联的Question，子任务才能找到对应的Reform
            $Ret_Data = TaskCore::FindReformOrQuestionByTaskID($TaskID);
            if($Ret_Data['Type'] == 'Question'){
                $Question = $Ret_Data['Ret'];
            }else if($Ret_Data['Type'] == 'Reform'){
                $Reform = $Ret_Data['Ret'];
            }
        }else{
            $Reform  = db()->query("SELECT * FROM ReformList WHERE id=?",array($ReformID))[0];
            if(empty($Reform)){
                return "整改通知书不存在!";
            }
        }

        $isJCY = 'NO';
        $Role = $this->JudgeUserRoleByTaskID($TaskID);
        if(empty($Role)){
            return "越权访问!";
        }elseif ($Role=='JCY'){
            $isJCY = 'YES';
        }elseif($Role=='CLRY'){
            $isJCY = 'NO';
        }

        $Inspectors = '';

        $b_NeedJCYDefineCause  = 'NO';
        $b_NeedJCYDefineAction = 'NO';

        $ReformStatus = '';
        if(!empty($Reform)){
            $ReformStatus = $Reform['ReformStatus'];
            if($Reform['CurDealCorp']!=session('Corp')){
                return '您所在部门暂时无法修改该通知书!';
            }
        }


        if($opType=='Mdf'){//修改整改通知书
            if(empty($Reform)){
                $this->assign("Warning",'整改通知书不存在!');
                goto OUT;
            }
            switch ($ReformStatus)
            {
                case $this->ReformStatus['ActionisNotDefined']://未分析原因或制订措施，此时应该是通知书刚刚下发，等待责任部门指定措施
                case $this->ReformStatus['ActionIsNotOk']://或者措施审核不通过
                    {
                        $data = array();
                        if($Reform['RequireDefineCause'] == 'YES'){//要求责任单位分析原因
                            $data["DirectCause"] = input("DirectCause");
                            $data["RootCause"]   = input("RootCause");
                        }
                        $data["CorrectiveAction"] = input("CorrectiveAction");
                        $data["CorrectiveDeadline"] = input("CorrectiveDeadline");
                        $data["PrecautionAction"] = input("PrecautionAction");
                        $data["PrecautionDeadline"] = input("PrecautionDeadline");
                        $data["ActionMakerName"] = session("Name");
                        $data["ActionMakeTime"] = date("Y-m-d H:i:s");

                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $this->assign("Warning",$k."不可为空!");
                                goto OUT;
                            }
                        }
                        $Ret =  db('reformlist')->where(array('id'=>$Reform['id']))->update($data);
                        return $this->index($TaskID,$Reform['id'],'Mdf');
                    }
                    break;
                case $this->ReformStatus['ActionIsMaked']://措施已提交等待审核
                    {
                        $data["ActionIsOK"] = input("ActionIsOK");
                        $data["ActionEval"] = input("ActionEval");
                        $data["ActionEvalerName"] = session('Name');
                        $data["ActionEvalTime"] = date("Y-m-d H:i:s");
                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $this->assign("Warning",$k."不可为空!");
                                goto OUT;
                            }
                        }
                        $Ret =  db('reformlist')->where(array('id'=>$Reform['id']))->update($data);
                        return $this->index($TaskID,$Reform['id'],'Mdf');
                    }
                    break;
                case $this->ReformStatus['ActionIsOk']://措施已经审核通过，现在正在提交整改证据
                case $this->ReformStatus['ProofIsNotOk']://或者之前提交的整改证据不通过
                    {
                        $data["Proof"] = htmlspecialchars(input("Proof"));
                        $data["ProofUploaderName"] = session('Name');
                        $data["ProofUploadTime"] =  date("Y-m-d H:i:s");
                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $this->assign("Warning",$k."不可为空!");
                                goto OUT;
                            }
                        }
                        $Ret =  db('reformlist')->where(array('id'=>$Reform['id']))->update($data);
                        return $this->index($TaskID,$Reform['id'],'Mdf');
                    }
                    break;
                case $this->ReformStatus['ProofIsUploaded']://证据已上传，待审核
                    {
                        $data["ProofEvalIsOK"] = input("ProofEvalIsOK");
                        $data["ProofEvalMemo"] = input("ProofEvalMemo");
                        $data["ProofEvalerName"] = session('Name');
                        $data["ProofEvalTime"] =  date("Y-m-d H:i:s");
                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $this->assign("Warning",$k."不可为空!");
                                goto OUT;
                            }
                        }
                        $Ret =  db('reformlist')->where(array('id'=>$Reform['id']))->update($data);
                        return $this->index($TaskID,$Reform['id'],'Mdf');
                    }
                    break;

            }
        }


        //dump(input());

        if($opType=='New'){//增加整改通知书
            if($isJCY!='YES'){
                return "您权限不足!";
            }
            if(empty($Question)){
                return "关联问题不存在!";
            }
            $data = array();
            $data["QuestionSourceName"] = input("QuestionSourceName");
            $data["DutyCorps"] = input("post.DutyCorps/a");
            $data["ParentTaskID"] = $TaskID;
            $data["CheckDate"] = input("CheckDate");
            $data["RequestFeedBackDate"]  = input("RequestFeedBackDate");
            $data["RelatedQuestionID"] = !empty($Question)?$Question['id']:'';
            $data["QuestionTitle"]     = $Question['QuestionTitle'];
            $data["ReformTitle"]     = input('ReformTitle');
            $data["NonConfirmDesc"] = htmlspecialchars(input("NonConfirmDesc"));
            $data["Basis"] = input("Basis");
            $data["ReformRequirement"]   = input("ReformRequirement");
            $data["RequireDefineCause"]  = input("RequireDefineCause")=='on'?'YES':'NO';
            $data["RequireDefineAction"] = input("RequireDefineAction")=='on'?'YES':'NO';
            $data["DirectCause"] = input("DirectCause");
            $data["RootCause"]   = input("RootCause");
            $data["CorrectiveAction"] = input("CorrectiveAction");
            $data["CorrectiveDeadline"] = input("CorrectiveDeadline");
            $data["PrecautionAction"] = input("PrecautionAction");
            $data["PrecautionDeadline"] = input("PrecautionDeadline");
            $data["ReformStatus"] = '未下发';
            $data["ActionIsOK"] = input("ActionIsOK");
            $data["ActionEval"] = input("ActionEval");

           $Dealers =  db()->query("SELECT * FROM TaskDealerGroup WHERE TaskID=?",array($TaskID));
           $Inspectors = '';
           foreach ($Dealers as $dealer) {
               $Inspectors.= $dealer['Name'].' ';
           }
           if(empty($Inspectors)){
               $Inspectors = session('Name');
           }
            $data["Inspectors"] = $Inspectors;

            $MustNotBeEmptyKeys = array("QuestionSourceName","DutyCorps","CheckDate","RequestFeedBackDate",'RequireDefineCause'
                                        ,'RequireDefineAction',"RelatedQuestionID","NonConfirmDesc","Basis"
                                        ,"ReformRequirement",'ReformTitle');

            foreach ($MustNotBeEmptyKeys as $k){
                if(empty($data[$k])){
                    $this->assign("Warning",$k."不可为空!");
                    goto OUT;
                }
            }
            $DutyCorps = input("post.DutyCorps/a");
            unset($data["DutyCorps"]);

            if($data["RequireDefineCause"] =='NO'){//不需要责任单位分析原因,则整改通知书下发单位需要分析原因
                $b_NeedJCYDefineCause = 'YES';
                if(empty($data["DirectCause"])||empty($data["RootCause"])){
                    $this->assign("Warning","没有分析直接原因与根本原因!");
                    goto  OUT;
                }
                $data["CauseEvalerName"] = session("Name");
                $data["CauseEvalTime"]   = date("Y-m-d H:i:s");
            }else{// 不需要监察员分析原因
                $b_NeedJCYDefineCause = 'NO';
                unset($data["DirectCause"]);
                unset($data["RootCause"]);
            }

            if($data["RequireDefineAction"]=='NO'){//不要责任单位指定措施,则整改通知书下发单位需要指定措施并明确期限
                $b_NeedJCYDefineAction = 'YES';
                if(empty($data["CorrectiveAction"]) || empty($data["CorrectiveDeadline"]) || empty($data["PrecautionAction"]) || empty($data["PrecautionDeadline"])){
                    $this->assign("Warning","没有制定措施及明确措施预计完成时限!");
                    goto  OUT;
                }
                if(empty($data["ActionIsOK"])|| empty($data["ActionEval"])){
                    $this->assign("Warning","没有措施评估!");
                    goto  OUT;
                }
                $data["ActionMakerName"] = session("Name");
                $data["ActionMakeTime"]  = date("Y-m-d H:i:s");
                $data["ActionEvalerName"] = session("Name");
                $data["ActionEvalTime"]   = date("Y-m-d H:i:s");
             }else{ // 不需要监察员指定措施
                $b_NeedJCYDefineAction = 'NO';
                unset($data["CorrectiveAction"]);
                unset($data["CorrectiveDeadline"]);
                unset($data["PrecautionAction"]);
                unset($data["PrecautionDeadline"]);
            }


            if($b_NeedJCYDefineAction =='YES' && $b_NeedJCYDefineCause== 'NO'){//已经指定措施，又让责任单位指定分析原因，不允许
                $this->assign("Warning","不允许已制定措施，又让责任单位分析原因!");
                goto OUT;
            }

            //保存整改通知书

            $CodePre = db()->query("SELECT CodePre FROM QuestionSource WHERE SourceName = ? ",array($data["QuestionSourceName"]));
            if(empty($CodePre)){
                $this->assign("Warning",$data["QuestionSourceName"]."问题来源不存在!");
                goto OUT;
            }
            $CodePre = $CodePre[0]["CodePre"];

            $data["Inspectors"] = $Inspectors;
            $data["IssueDate"] = date("Y-m-d");

            $IDs = array();
            foreach ($DutyCorps as $DutyCorp){
               $data["Code"] = $CodePre."-".date("YmdHis").rand(100,999);
               $data["DutyCorp"] =  $DutyCorp;
               $data["CurDealCorp"] =  $DutyCorp;
               $IDs[$DutyCorp] = db("reformlist")->insertGetId($data);
               //dump($IDs[$DutyCorp] );
               if(empty($IDs[$DutyCorp])){
                   $this->assign("Warning",'增加整改通知书失败!');
                   goto OUT;
               }
               $Cross_Data["Type"] = "问题-整改";
               $Cross_Data["FromID"] = $Question['id'];
               $Cross_Data["ToID"] = $IDs[$DutyCorp];
               db('idcrossindex')->insert($Cross_Data);
            }

        }



        OUT:
            return $this->index($TaskID);
    }

    public function SendReform($TaskID,$ReformID){
       $Role =  $this->JudgeUserRoleByTaskID($TaskID);
       if(empty($Role)){
           $this->assign("Warning","越权访问!");
           goto OUT;
       }

        $Reform = db()->query("SELECT * FROM ReformList WHERE id = ?",array($ReformID))[0];
        if(empty($Reform)){
            $this->assign("Warning","该整改通知书不存在!");
            goto OUT;
        }

        $ReformStatus = $Reform['ReformStatus'];
        if($ReformStatus=='未下发'){
            if($Role!='JCY'){
                $this->assign("Warning","您不具备下发整改通知书的权限!");
                goto OUT;
            }
            $ChildTaskStatus  = '' ;
            $ReformNewStatus  = '' ;
            $DeadLine = '';
            if($Reform["RequireDefineAction"] == 'YES'){
                $ChildTaskStatus =TaskCore::REFORM_UNDEFINED_ACTION;
                $ReformNewStatus = $this->ReformStatus['ActionisNotDefined'];
            }else{
                $ChildTaskStatus =TaskCore::REFORM_UNDEFINE_PROOF;
                $ReformNewStatus = $this->ReformStatus['ActionIsOk'];
            }

            $TaskData = array();
            $TaskData["TaskType"] = '整改通知书';
            $TaskData["TaskInnerStatus"] = $ChildTaskStatus;
            $TaskData['TaskName'] = $Reform["ReformTitle"];
            $TaskData['DeadLine'] = $Reform["RequestFeedBackDate"];
            $TaskData['SenderName'] = session("Name");
            $TaskData['SenderCorp'] = $this->SuperCorp;
            $TaskData['ReciveCorp'] = $Reform['DutyCorp'];
            $TaskData['RelateID'] = $ReformID;
            $TaskData['CreateTime'] = date("Y-m-d H:i:s");
            $TaskData['CreatorName'] = session("Name");
            $TaskData['ParentID'] = $TaskID;
            $Ret = TaskCore::CreateTask($TaskData);
            if(!empty($Ret['Ret'])){
                $this->assign("Warning","任务创建失败->".$Ret['Ret']);
                goto OUT;
            }else{
                db()->query("UPDATE ReformList SET ChildTaskID = ?,ReformStatus=?,CurDealCorp=? WHERE id = ?",array($Ret['ID'],$ReformNewStatus,$Reform['DutyCorp'],$ReformID));
            }
        }

        if($Reform['CurDealCorp']!=session('Corp')){//除了草稿以外，CurDealCorp必须等于本部门
            //$this->assign("Warning",'整改通知书非本部门,越权操作!');
            goto OUT;
        }

        if($ReformStatus == $this->ReformStatus['ActionisNotDefined'] || $ReformStatus == $this->ReformStatus['ActionIsNotOk']){//当前状态为未指定措施，不过现在已经指定措施了!或者措施已提交等待审核，或者措施审核不通过
            $NotEmpyArrKeys = array('DirectCause','RootCause','CorrectiveAction','CorrectiveDeadline','PrecautionAction','PrecautionDeadline');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            db()->query("UPDATE ReformList SET CurDealCorp=?,ReformStatus=? WHERE id = ?",array($this->SuperCorp,$this->ReformStatus['ActionIsMaked'],$ReformID));
        }else if($ReformStatus == $this->ReformStatus['ActionIsMaked'] ){
            $NotEmpyArrKeys = array('ActionIsOK','ActionEval','ActionEvalerName','ActionEvalTime');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            $NewStatus  = '';
            if($Reform['ActionIsOK']=='YES'){
                $NewStatus = $this->ReformStatus['ActionIsOk'];
            }else{
                $NewStatus = $this->ReformStatus['ActionIsNotOk'];
            }
            db()->query("UPDATE ReformList SET CurDealCorp=?,ReformStatus=? WHERE id = ?",array($Reform['DutyCorp'],$NewStatus,$ReformID));
        }else if($ReformStatus == $this->ReformStatus['ActionIsOk'] || $ReformStatus == $this->ReformStatus['ProofIsNotOk'] ){//现在整改证据已经提交等待审核，或者整改证据之前审核不通过
            $NotEmpyArrKeys = array('Proof','ProofUploaderName','ProofUploadTime');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            db()->query("UPDATE ReformList SET CurDealCorp=?,ReformStatus=? WHERE id = ?",array($this->SuperCorp,$this->ReformStatus['ProofIsUploaded'],$ReformID));
        }else if($ReformStatus == $this->ReformStatus['ProofIsUploaded'] ){//现在整改证据已经提交并且审核完毕，下发审核结果
            $NotEmpyArrKeys = array('ProofEvalIsOK','ProofEvalerName','ProofEvalTime','ProofEvalMemo');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            $NewStatus = '';
            if($Reform['ProofEvalIsOK']=='YES'){
                $NewStatus = $this->ReformStatus['ProofIsOk'];
            }else{
                $NewStatus = $this->ReformStatus['ProofIsNotOk'];
            }
            db()->query("UPDATE ReformList SET CurDealCorp=?,ReformStatus=? WHERE id = ?",array($Reform['DutyCorp'],$NewStatus,$ReformID));
        }


       OUT:
            return $this->showReformList($TaskID);
    }

    public function showDeleteReform($ReformID){
        $this->assign("ReformID",$ReformID);
        return view('DelReform');
    }

    public function GenNewReformDePWD()
    {
        $PWD = '12345678';
        for($i=0;$i<8;$i++){
            $isZM = rand(0,1);
            $PWD[$i] = $isZM===0?chr(65+rand(0,25)):rand(0,9);
        }
        return $PWD;
    }

    public function DelReform(){
        $ReformID = input('ReformID');
        $Reform = db('reformlist')->where(array("id"=>$ReformID,'isDeleted'=>'否'))->select();
        if(empty($Reform)){
            $this->assign("Warning","你要删除的通知书不存在!");
            goto OUT;
        }
        $Pwd = input('Pwd');
        $DeleteMemo = input('DelMemo');

        if(empty($DeleteMemo)){
            $this->assign("Warning",'原因必须填写！');
            goto OUT;
        }


        $Ret = db('sysconf')->where(array('KeyName'=>'ReformDeletePwd','KeyValue'=>$Pwd))->select();
        if(empty($Ret)){
            $this->assign('Warning','密码错误!');
            goto OUT;
        }


        db()->query("UPDATE TaskList SET isDeleted = ?,DeleterName=?,DeleteTime=? WHERE id=?",array('是',
            session('Name'),
            date("Y-m-d H:i:s"),
            $Reform[0]['ChildTaskID']));

        db()->query("UPDATE ReformList SET isDeleted = ?,DeleterName=?,DeleteTime=?,DeleteMemo=? WHERE id=?",array('是',
                                                                                                          session('Name'),
                                                                                                           date("Y-m-d H:i:s"),
                                                                                                            $DeleteMemo,
                                                                                                            $ReformID));

        db()->query("DELETE FROM IDCrossIndex WHERE ToID = ? AND Type ='问题-整改'",array($ReformID));

        db()->query("UPDATE SysConf SET KeyValue = ? WHERE KeyName = ? ",array($this->GenNewReformDePWD(),'ReformDeletePwd'));
       // $NewPWD =

        SuccessOUT:
            return '删除成功!';

        OUT:
        return view('DelReform');
    }
}
