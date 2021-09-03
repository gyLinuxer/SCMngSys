<?php
namespace app\safetymng\controller;
use think\Db;
use think\Request;
use think\Log;

class Reform extends PublicController{

    private $FXMng;
    private $SysCnf;

    public function __construct(Request $request = null)
    {
        $this->FXMng = new FXMng();
        $this->SysCnf = new SysConf();
        parent::__construct($request);
    }

    public $ReformStatus = array('NonIssued'=>"未下发",
                                'ActionIsNotDefined'=>"未分析原因制定措施",
                                'ActionIsMaked'=>"措施已制定待审核",
                                'ActionIsNotOk'=>"措施审核不通过",
                                'ActionIsOk'=>"措施审核通过执行中",
                                'ProofIsUploaded' =>"整改证据已上传待审核",//等待废除
                                'ProofIsOk'=>"整改效果审核通过");
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
                                            '整改效果审核通过'=>7);
    public function index($TaskID=NULL,$ReformID =NULL,$opType='New',$Platform='PC',$Warning='')//opType :New Mdf
    {
        if(empty($TaskID)){
            return "任务ID不可为空!";
        }
        $Question = '';
        $Reform = '';
        //若有ReformID，则优先找到Reform，通过Reform来填充各个项目
        //若没有ReformID，则通过TaskID找到Question

        $Data = TaskCore::FindReformOrQuestionByTaskID($TaskID);
        if(empty($Data['Ret'])){
            return "任务ID错误!找不到关联问题和整改通知";
        }else{
            if($Data['Type']=='Question'){
                $Question = $Data['Ret'];
            }else if($Data['Type']=='Reform'){
                    $opType='Mdf';
                    $Reform = $Data["Ret"];
            }
        }

        $Role = TaskCore::JudgeUserRoleByTaskID($TaskID);

        if($opType=='Mdf'){
            if(!empty($ReformID)){
                $Reform = db()->query("SELECT * FROM ReformList WHERE id=?",array($ReformID));
            }else{
                if(empty($Reform)){
                    return "整改通知书不存在!";
                }else{
                    $this->assign("Reform/Reform",$Reform[0]);
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
                    $ReformStatus = $Reform[0]['ReformStatus'];
                    $Reform_IntStatus = $this->ReformStatus_AssginArr[$ReformStatus];

                    //前提是必须是本任务的处理人员
                    $isThisTaskDealer = $Role==''?'NO':'YES';
                     //必须是责任部门或者下发部门才能编辑
                    $SubCanEdit1 = false;
                    if($Reform_IntStatus==4) {//措施审核通过后双发都可编辑
                        if((session('Corp')== $Reform[0]['DutyCorp']) || $Role == 'JCY' ){
                            $SubCanEdit1 = true;
                        }
                    }else if($Reform_IntStatus!=7){//非措施审核通过状态下,且不是整改完毕,只有当前部门可以编辑
                        if($Reform[0]['CurDealCorp']==session('Corp')){
                            $SubCanEdit1 = true;
                        }
                    }
                    if($isThisTaskDealer && $SubCanEdit1){
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
        $this->assign("TaskRole",$Role);
        $this->assign("Question",$Question);
        $this->assign("QuestionID",empty($Question)?0:$Question["id"]);
        $this->assign("TaskID",$TaskID);
        $this->assign("ReformID",empty($Reform)?0:$Reform[0]["id"]);
        $this->assign("ReformStatus",empty($Reform)?'':$Reform[0]["ReformStatus"]);
        $this->assign("ReformIntStatus",empty($Reform)?1:$this->ReformStatus_AssginArr[$Reform[0]["ReformStatus"]]);
        //dump($this->ReformStatus_AssginArr[$Reform[0]["ReformStatus"]]);
        $this->assign("Today",date("Y-m-d"));
        $this->assign("CorpList",$this->GetCorpList());
        $this->assign("QuestionSourceList",$this->SysCnf->GetQuestionSourceList());
        $this->assign("SuperCorp",$this->SuperCorp);

        if($Platform=='Mobile'){
            $this->assign('FormAction',url('SafetyMng/Reform/SaveReform'));
            return view('Reform/mbReformIndex');
        }else{
            return view('Reform/index');
        }


    }

    public function showFastReformIndex($TaskID=0,$ReformID=0,$AddFastReform='YES',$ReformSelData = NULL)
    {
        $Role = TaskCore::JudgeUserRoleByTaskID($TaskID,$ReformID);

        if(empty($TaskID) && empty($ReformID)){//下发立即整改
            $Ret = db('UserList')->where(array('Name'=>session('Name')))->select();
            if(empty($Ret)){
                $this->assign('Warning','您不是审核员，无法下发整改通知书!');
                goto OUT;
            }
            if($AddFastReform=='YES'){
                $this->assign('FormAction',url('SafetyMng/Reform/AddFastReform'));
            }else{
                $this->assign('FormAction',url('SafetyMng/Reform/SaveReform'));
            }

            $this->assign("opType","New");
        }else{//填写整改通知书
            if(empty($Role)){
                return '您无访问本整改通知书的权限';
            }
            $this->assign('FormAction',url('SafetyMng/Reform/SaveReform'));
            $this->assign("opType","Mdf");
        }

        $Reform = db('ReformList')->where(array('id'=>$ReformID))->select()[0];
        $this->assign('ReformSelData',$ReformSelData);
        $this->assign("CurPlatform","Mobile");
        $this->assign("ReformID",$ReformID);
        $this->assign("TaskID",$TaskID);
        $this->assign("Reform",$Reform);
        if($Role=='JCY' || $Role =='CLRY' ||
            ($Reform['ReformStatus']==$this->ReformStatus['NonIssued']&&$Reform['IssueCorp']==session('Corp'))){//通知书当前处理部门为本部门或者通知书未下发且下发部门为本部门
            if($Reform['ReformStatus']!=$this->ReformStatus['ProofIsOk']){
                $this->assign('showSaveBtn','YES');
            }
        }

        $this->assign("CorpList",$this->GetCorpList());
        $this->assign("Today",date('Y-m-d'));
        $this->assign("QuestionSourceList",$this->SysCnf->GetQuestionSourceList());
        $this->assign("ReformIntStatus",empty($Reform)?1:$this->ReformStatus_AssginArr[$Reform["ReformStatus"]]);
        $this->assign('AddFastReform',$AddFastReform);

        OUT:
            return view('Reform/mbReformIndex');
    }

    public function AddFastReform()
    {

        // 检查权限，必须是审核员
        $Ret = db('UserList')->where(array('Name'=>session('Name')))->select();
        if(empty($Ret)){
            $this->assign('Warning','您不是审核员，无法下发整改通知书!');
            goto OUT1;
        }

        $RequireDefineCauseAndAction = 'NO';
        if('on' == input("ActionAndCauseRequired")){//on代表需要责任单位自己分析原因并制定措施
            $RequireDefineCauseAndAction = 'YES';
        }

        $data["QuestionSourceName"] = input("QuestionSourceName");
        $data["CheckDate"] = date('Y-m-d');
        $data["RequestFeedBackDate"]  = input("RequestFeedBackDate");
        $data["ReformTitle"]     = input('ReformTitle');
        $data["QuestionTitle"]     =  $data["ReformTitle"];
        $data["NonConfirmDesc"] = htmlspecialchars(input("NonConfirmDesc"));
        $data["Basis"] = input("Basis");
        $data["IssueCorp"] = session("Corp");
        $data["ReformRequirement"]   = input("ReformRequirement");
        $data["RequireDefineCause"]  = $RequireDefineCauseAndAction;
        $data["RequireDefineAction"] = $RequireDefineCauseAndAction;

        $DutyCorps = input("post.DutyCorps/a");

        foreach ($data as $k=>$v){
            if(empty($v)){
                $this->assign("Warning",$k."不可为空!");
                goto OUT1;
            }
        }
        $TaskInnerStatus = '';
        if($RequireDefineCauseAndAction=='NO'){//检查人员自己制定措施并分析原因
            $data["DirectCause"] = input("DirectCause");
            $data["RootCause"]   = input("RootCause");
            $data["CorrectiveAction"] = input("CorrectiveAction");
            $data["CorrectiveDeadline"] = input("CorrectiveDeadline");
            $data["PrecautionAction"] = input("PrecautionAction");
            $data["PrecautionDeadline"] = input("PrecautionDeadline");
            $data["ReformStatus"] = $this->ReformStatus['ActionIsOk'];
            $data["ActionMakerName"] = session('Name');
            $data["ActionMakeTime"] = date('Y-m-d H:i:s');
            $data["ActionIsOK"] = 'YES';
            $data["ActionEval"] = input('ActionEval');
            $data["ActionEvalerName"] = session('Name');
            $data["ActionEvalTime"] = date('Y-m-d H:i:s');

            $TaskInnerStatus = $this->ReformStatus['ActionIsOk'];
            if( empty($data["CorrectiveAction"]) || empty( $data["CorrectiveDeadline"])){
                $this->assign('Warning','纠正措施及完成时限不可为空');
                goto OUT1;
            }
        }else{//由责任单位自己制定措施分析原因
            $data["ReformStatus"] = $this->ReformStatus['ActionIsNotDefined'];
            $TaskInnerStatus = $this->ReformStatus['ActionIsNotDefined'];
        }
        //首先检查立即整改通知书的要素是否完整，以便于下一步自动生成问题、生成立即整改父任务、整改子任务。
        //1.将不符合项变成问题，写入QuestionList
        $Q_Data['DealType']      = '立即整改';
        $Q_Data['QuestionTitle'] = $data["ReformTitle"];
        $Q_Data['QuestionInfo']  = $data["NonConfirmDesc"];
        $Q_Data['CreatorName']   = session('Name');
        $Q_Data['Basis']   = $data['Basis'];
        $Q_Data["QuestionSource"] = $data['QuestionSourceName'];

        $Corps = '';
        if(!empty($DutyCorps)){
            foreach ($DutyCorps as $v){
                $Corps.=' '.$v;
            }
            $Q_Data["RelatedCorp"] = $Corps;
        }
        $Q_Data['Finder'] = session('Name');


        $Q_Data['DateFound'] = date('Y-m-d H:i:s');
        //
        $Q_Data['CreateTime']   = date('Y-m-d H:i:s');
        $Q_ID = db('QuestionList')->insertGetId($Q_Data);


        //2.创建'问题-立即整改'父任务
        $T_Data['TaskType'] = TaskCore::QUESTION_FAST_REFORM;
        $T_Data['TaskName'] = $data["ReformTitle"];
        $T_Data['ReciveCorp'] = session('Corp');
        $T_Data['RelateID'] = $Q_ID;
        $T_Data['CreateTime'] = date('Y-m-d H:i:s');
        $T_Data['ParentID'] = 0;
        $T_Data['Status'] = TaskCore::STATUS_UNRECV;
        $T_Ret = TaskCore::CreateTask($T_Data);
        //dump($T_Ret);
        if(empty($T_Ret['ID']))
        {
            $this->assign('Warning','创建任务失败!'.$T_Data['Ret']);
            goto OUT1;
        }
        db('QuestionList')->where(array('id'=>$Q_ID))->update(array('TaskID'=>$T_Ret['ID']));


        $data["ParentTaskID"] = $T_Ret['ID'];
        $data["RelatedQuestionID"] = $Q_ID;//关联问题ID*/

        //3.分配任务
        $TaskGroupID = TaskCore::GetUniqueGroupID();
        $TG_Data['TaskID']  = $T_Ret['ID'];
        $TG_Data['GroupID'] = $TaskGroupID;
        $TG_Data['Name']  = session('Name');
        $TG_Data['Corp']  = session('Corp');
        $TG_Data['Role']  = '组长';
        $TG_Data['AddTime']  = date('Y-m-d H:i:s');
        db('TaskDealerGroup')->insert($TG_Data);
        db('TaskList')->where(array('id'=>$T_Data['ID']))->update(array('GroupMember'=>session('Name'),'DealGroupID'=>$TaskGroupID));//更新父任务分配状态


        //4.下发整改通知书,并生成子任务。
        $data["Inspectors"] = session('Name');
        $data["IssueDate"] = date("Y-m-d");
        $IDs = array();

        $CodePre = db()->query("SELECT CodePre FROM QuestionSource WHERE SourceName = ? ",array($data["QuestionSourceName"]));
        if(empty($CodePre)){
            $this->assign("Warning",$data["QuestionSourceName"]."问题来源不存在!");
            goto OUT1;
        }
        $CodePre = $CodePre[0]["CodePre"];
        $LastReformID  = 0 ;
        foreach ($DutyCorps as $DutyCorp){
            $data["Code"] = $CodePre."-".date("YmdHis").rand(100,999);
            $data["DutyCorp"] =  $DutyCorp;
            $data["CurDealCorp"] =  $DutyCorp;
            $IDs[$DutyCorp] = db("ReformList")->insertGetId($data);
            $LastReformID = $IDs[$DutyCorp];
            if(empty($IDs[$DutyCorp])){
                $this->assign("Warning",'增加整改通知书失败!');
                goto OUT1;
            }
            $Cross_Data["Type"] = TaskCore::QUESTION_REFORM;
            $Cross_Data["FromID"] = $Q_ID;
            $Cross_Data["ToID"] = $IDs[$DutyCorp];
            db('IDCrossIndex')->insert($Cross_Data);

            $TaskData = array();
            $TaskData["TaskType"] = '整改通知书';
            $TaskData["TaskInnerStatus"] = $TaskInnerStatus;
            $TaskData['TaskName'] = $data["ReformTitle"];
            $TaskData['DeadLine'] = $data["RequestFeedBackDate"];
            $TaskData['SenderName'] = session("Name");
            $TaskData['SenderCorp'] = session('Corp');
            $TaskData['ReciveCorp'] = $DutyCorp;
            $TaskData['RelateID'] = $IDs[$DutyCorp];
            $TaskData['CreateTime'] = date("Y-m-d H:i:s");
            $TaskData['CreatorName'] = session("Name");
            $TaskData['ParentID'] = $T_Ret['ID'];
            $TaskData['Status'] = TaskCore::STATUS_UNRECV;
            $Ret = TaskCore::CreateTask($TaskData);
            if(!empty($Ret['Ret'])){
                $this->assign("Warning","任务创建失败->".$Ret['Ret']);
                goto OUT1;
            }else{
                db()->query("UPDATE ReformList SET ChildTaskID = ?,CurDealCorp=? WHERE id = ?",array($Ret['ID'],$data['DutyCorp'],$IDs[$DutyCorp]));
            }
        }



            $this->assign('Warning','整改通知书下发成功!!');
        OUT1:
            $CallBackURL = input('CallBackURL');
            if(!empty($CallBackURL)){
                $this->redirect($CallBackURL.$LastReformID);
            }else{
                return $this->showFastReformIndex();
            }
    }

    public function GetReformListByTaskID($TaskID)
    {

        $TaskRole = TaskCore::JudgeUserRoleByTaskID($TaskID);
        if (empty($TaskRole)) {
            return '任务不存在或者尚未分配给您处理!';
        }
        $Task = db()->query("SELECT * FROM TaskList WHERE id = ?", array($TaskID));
       // dump($Task);
        if ($TaskRole != '') {//显示本任务中所有整改通知单
            if($Task[0]['TaskType']==TaskCore::REFORM_SUBTASK){
                $ReformID = $Task[0]["RelateID"];
                $ReformList = db()->query("SELECT * FROM ReformList WHERE isDeleted = '否' AND id = ? ", array($ReformID));
            }else{
                $QuestionID = $Task[0]["RelateID"];
                $ReformList = db()->query("SELECT * FROM ReformList WHERE isDeleted = '否' AND id in (SELECT ToID FROM IDCrossIndex WHERE FromID = ?)", array($QuestionID));
            }
         }else {
            $ReformList = array();
        }
        //dump($TaskRole);
        ///dump($ReformList);
        return $ReformList;
    }

    public function showReformList($TaskID)
    {
        $ReformList = $this->GetReformListByTaskID($TaskID);
        $Role = TaskCore::JudgeUserRoleByTaskID($TaskID);
        if($Role=='JCY'){
            $this->assign('showZJBtn','YES');
        }

        $this->assign("ReformList", $ReformList);
        $this->assign("ReformCount", is_array($ReformList)?count($ReformList):0);
        $this->assign("Count", 1);
        $this->assign("TaskID", $TaskID);
        return view('Reform/ReformList');
    }


    public function SaveReformData($FunName = '',$TaskID=0,$ReformID=0,$opType='New',$Warning=''){

        if(empty($FunName)){
            return '保存错整改通知书出错!';
        }

        if(!empty($Warning)){
            return $Warning;
        }

        switch ($FunName){
            case 'Index':{
                ///return $this->index($TaskID,$ReformID,$opType);
                $this->redirect(url('Reform/index','TaskID='.$TaskID.'&ReformID='.$ReformID.'&opType='.$opType.'&Warning='.$Warning));
            }
            case 'showFastReformIndex':{
                $this->redirect(url('Reform/showFastReformIndex','TaskID='.$TaskID.'&ReformID='.$ReformID.'&AddFastReform=NO'.'&Warning='.$Warning));
            }
        }
        return '';
    }


    public function SaveReform(){//New Fill
        //先看看当前操作者在该问题中是什么角色，然后结合整改通知书当前状态，决定他该干什么，能干什么
        $TaskID = input("TaskIDHid");
        $ReformID = input("ReformIDHid");
        $opType= input('opType');
        $Platform = input('Platform');
        $Warning = '';
        $ParentTaskType  = '';
        $FunName = '';
        if($Platform == 'Mobile'){
            $FunName = 'showFastReformIndex';
        }else{
            $FunName = 'Index';
        }

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
        $Role = TaskCore::JudgeUserRoleByTaskID($TaskID);
        if(empty($Role)){
            return "越权访问:您可能不是本任务的处理人员，请让领导将任务分配给你!";
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
            $Reform_IntStatus = $this->ReformStatus_AssginArr[$ReformStatus];
            if(($Reform['CurDealCorp']!=session('Corp')&&$Reform_IntStatus!=4) &&
                !($Reform['ReformStatus']=='未下发'&&$Reform['IssueCorp']==session('Corp'))){
                return '您所在部门暂时无法修改该通知书!';
            }
        }

        if($opType=='Mdf'){//修改整改通知书
            if(empty($Reform)){
                $Warning = '整改通知书不存在!';
                goto OUT;
            }
            switch ($ReformStatus)
            {
                case $this->ReformStatus['ActionIsNotDefined']://未分析原因或制订措施，此时应该是通知书刚刚下发，等待责任部门指定措施
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
                        $data["CauseEvalerName"] = session("Name");
                        $data["CauseEvalTime"] = date("Y-m-d H:i:s");

                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $Warning .=  $k."不可为空!";
                                goto OUT2;
                            }
                        }
                        $Ret =  db('ReformList')->where(array('id'=>$Reform['id']))->update($data);
                        goto OUT1;
                    }
                    break;
                case $this->ReformStatus['ActionIsMaked']://措施已提交等待审核
                    {
                        $data = array();

                        $data["ActionIsOK"] = input("ActionIsOK");
                        $data["ActionEval"] = input("ActionEval");
                        $data["ActionEvalerName"] = session('Name');
                        $data["ActionEvalTime"] = date("Y-m-d H:i:s");
                        foreach ($data as $k=>$v){
                            if(empty($v)){
                                $Warning .= $k."不可为空!";
                                goto OUT2;
                            }
                        }
                        $Ret =  db('ReformList')->where(array('id'=>$Reform['id']))->update($data);
                        goto OUT1;
                    }
                    break;
                case $this->ReformStatus['ActionIsOk']:{

                    /*
                       1、如果是下发部门，则其只能保存纠正与预防措施的审核结果,同时判断是否都是YES,然后把ProofIsOK设置为YES
                       2、如果是责任部门，则其只能保存纠正与预防措施的证据
                    */



                    $data = array();

                    $PrecautionActionProofEvalIsOK = $Reform['PrecautionActionProofEvalIsOK'];
                    $CorrectiveActionProofEvalIsOK = $Reform['CorrectiveActionProofEvalIsOK'];
                    $IN_PrecautionActionProof = input("PrecautionActionProof");
                    $IN_CorrectiveActionProof = input("CorrectiveActionProof");

                    //前提是改用户是本任务的CLRY或者JCY 1、如果任务为整改父任务，则其来审核。 2、若任务为整改子任务，则其来上传证据。
                    if(!empty($Role)){
                        if($TaskID == $Reform['ChildTaskID']){
                            Log::write(session('Corp').'责任部门来上传证据'.date('Y-m-d H:i:s'),'zk2000');
                            //当某项证据为空或者审核不通过时才可上传。
                            if((empty($Reform['CorrectiveActionProof']) || $CorrectiveActionProofEvalIsOK=='NO')  && !empty($IN_CorrectiveActionProof)){
                                $data["CorrectiveActionProof"] = htmlspecialchars(input("CorrectiveActionProof"));
                                $data["CorrectiveActionProofUploaderName"]  = session('Name');
                                $data["CorrectiveActionProofUploadTime"]    =  date("Y-m-d H:i:s");
                            }

                            if((empty($Reform['PrecautionActionProof']) || $PrecautionActionProofEvalIsOK=='NO') && !empty($IN_PrecautionActionProof)){
                                $data["PrecautionActionProof"] = htmlspecialchars(input("PrecautionActionProof"));
                                $data["PrecautionActionProofUploaderName"]  = session('Name');
                                $data["PrecautionActionProofUploadTime"]    =  date("Y-m-d H:i:s");
                            }

                            if(empty($data)){
                                $Warning .= "证据上传:未捕获到输入数据!";
                                goto OUT2;
                            }else{
                                foreach ($data as $k=>$v){
                                    if(empty($v)){
                                        $Warning .= $k."不可为空!";
                                        goto OUT2;
                                    }
                                }
                            }

                            if(empty($Reform['CorrectiveActionProof']) || $CorrectiveActionProofEvalIsOK=='NO'){
                                $data["CorrectiveActionProofEvalIsOK"]      =  '';
                            }

                            if(empty($Reform['PrecautionActionProof']) || $PrecautionActionProofEvalIsOK=='NO'){
                                $data["PrecautionActionProofEvalIsOK"]      =  '';
                            }

                            $Ret =  db('ReformList')->where(array('id'=>$Reform['id']))->update($data);

                            goto OUT1;
                        }else if($TaskID == $Reform['ParentTaskID']){
                            $IN_CorrectiveActionProofEvalIsOK = input('CorrectiveActionProofEvalIsOK');
                            $IN_PrecautionActionProofEvalIsOK = input('PrecautionActionProofEvalIsOK');
                            $AllActionProofIsOK = 'NO';
                            if(!empty($Reform['CorrectiveActionProof']) && $CorrectiveActionProofEvalIsOK=='' && !empty($IN_CorrectiveActionProofEvalIsOK )){
                                $data["CorrectiveActionProofEvalIsOK"]   = input("CorrectiveActionProofEvalIsOK");
                                $data["CorrectiveActionProofEvalMemo"]   = input("CorrectiveActionProofEvalMemo");
                                $data["CorrectiveActionProofEvalerName"] = session('Name');
                                $data["CorrectiveActionProofEvalTime"]   =  date("Y-m-d H:i:s");
                            }


                            if(!empty($Reform['PrecautionActionProof']) && $PrecautionActionProofEvalIsOK=='' && !empty($IN_PrecautionActionProofEvalIsOK)){
                                $data["PrecautionActionProofEvalIsOK"]   = input("PrecautionActionProofEvalIsOK");
                                $data["PrecautionActionProofEvalMemo"]   = input("PrecautionActionProofEvalMemo");
                                $data["PrecautionActionProofEvalerName"] = session('Name');
                                $data["PrecautionActionProofEvalTime"]   =  date("Y-m-d H:i:s");
                            }

                            if(($CorrectiveActionProofEvalIsOK=='YES' || $IN_CorrectiveActionProofEvalIsOK =='YES') &&(
                                    $PrecautionActionProofEvalIsOK=='YES' || $IN_PrecautionActionProofEvalIsOK == 'YES'
                                )){
                                $AllActionProofIsOK = 'YES';
                            }

                            if($AllActionProofIsOK=='YES'){
                                $data["ProofEvalIsOK"] = 'YES';
                                $data["ReformStatus"] = $this->ReformStatus['ProofIsOk'];
                                //设置子任务状态为整改效果可接受
                                db()->query("UPDATE TaskList SET TaskInnerStatus = ? WHERE id = (SELECT ChildTaskID FROM ReformList WHERE id=?)",array(TaskCore::REFORM_PROOF_ISOK,$Reform['id']));

                                if($Reform['ReformType']=='SMS整改'){
                                    $this->FXMng->SetSMSStatusToOK($Reform['RelateID']);
                                }else if($Reform['ReformType']=='隐患整改'){
                                    $this->FXMng->SetAQYHStatusToOK($Reform['RelateID']);
                                }

                            }
                            if(empty($data)){
                                $Warning .= "证据审核:未捕获到输入数据!";
                                goto OUT2;
                            }else{
                                foreach ($data as $k=>$v){
                                    if(empty($v)){
                                        $Warning .= $k."不可为空!";
                                        goto OUT2;
                                    }
                                }
                            }

                            $Ret =  db('ReformList')->where(array('id'=>$Reform['id']))->update($data);
                            goto OUT1;
                        }
                    }


                   /* if(session('Corp')==$Reform['DutyCorp'] && ($Role =='CLRY'||$Role=='JCY')){//责任部门,来上传证据


                    }elseif(session('Corp')==$Reform['IssueCorp'] && $Role =='JCY'){//下发部门,保存审核结果


                    }*/
                }



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
            $data["IssueCorp"] = session('Corp');
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
                    $Warning .= $k."不可为空!";
                    goto OUT;
                }
            }
            $DutyCorps = input("post.DutyCorps/a");
            unset($data["DutyCorps"]);

            if($data["RequireDefineCause"] =='NO'){//不需要责任单位分析原因,则整改通知书下发单位需要分析原因
                $b_NeedJCYDefineCause = 'YES';
                if(empty($data["DirectCause"])||empty($data["RootCause"])){
                    $Warning = "没有分析直接原因与根本原因!";
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
                    $Warning ="没有制定措施及明确措施预计完成时限!";
                    goto  OUT;
                }
                if(empty($data["ActionIsOK"])|| empty($data["ActionEval"])){
                    $Warning = "没有措施评估!";
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
                $Warning = "不允许已制定措施，又让责任单位分析原因!";
                goto OUT;
            }

            //保存整改通知书

            $CodePre = db()->query("SELECT CodePre FROM QuestionSource WHERE SourceName = ? ",array($data["QuestionSourceName"]));
            if(empty($CodePre)){
                $Warning = $data["QuestionSourceName"]."问题来源不存在!";
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
               $IDs[$DutyCorp] = db("ReformList")->insertGetId($data);
               //dump($IDs[$DutyCorp] );
               if(empty($IDs[$DutyCorp])){
                   $Warning = '增加整改通知书失败!';
                   goto OUT;
               }else{
                   if($data["RequireDefineAction"] == 'YES'){
                       $ChildTaskStatus =TaskCore::REFORM_UNDEFINED_ACTION;
                       $ReformNewStatus = $this->ReformStatus['ActionIsNotDefined'];
                   }else{
                       $ChildTaskStatus =TaskCore::REFORM_UNDEFINE_PROOF;
                       $ReformNewStatus = $this->ReformStatus['ActionIsOk'];
                   }

                   $TaskData = array();
                   $TaskData["TaskType"] = '整改通知书';
                   $TaskData["TaskInnerStatus"] = $ChildTaskStatus;
                   $TaskData['TaskName'] = $data["ReformTitle"];
                   $TaskData['DeadLine'] = $data["RequestFeedBackDate"];
                   $TaskData['SenderName'] = session("Name");
                   $TaskData['TaskSource'] = $data["QuestionSourceName"];
                   $TaskData['SenderCorp'] = $data["IssueCorp"];
                   $TaskData['ReciveCorp'] = $data['DutyCorp'];
                   $TaskData['RelateID'] = $IDs[$DutyCorp];
                   $TaskData['CreateTime'] = date("Y-m-d H:i:s");
                   $TaskData['CreatorName'] = session("Name");
                   $TaskData['ParentID'] = $data['ParentTaskID'];
                   $TaskData['Status'] = TaskCore::STATUS_UNRECV;
                   $Ret = TaskCore::CreateTask($TaskData);
                   if(!empty($Ret['Ret'])){
                       $Warning = "任务创建失败->".$Ret['Ret'];
                       goto OUT;
                   }else{
                       db()->query("UPDATE ReformList SET ChildTaskID = ?,ReformStatus=?,CurDealCorp = DutyCorp WHERE id = ?",array($Ret['ID'],$ReformNewStatus,$IDs[$DutyCorp]));
                   }
               }
               $Cross_Data["Type"] = TaskCore::QUESTION_REFORM;
               $Cross_Data["FromID"] = $Question['id'];
               $Cross_Data["ToID"] = $IDs[$DutyCorp];
               db('IDCrossIndex')->insert($Cross_Data);
            }
        }

        OUT:
            return $this->SaveReformData($FunName,$TaskID,0,'New',$Warning);

        OUT1:
            $this->SendReform($TaskID,$ReformID,$Platform,false);

        OUT2:
            return $this->SaveReformData($FunName,$TaskID,$Reform['id'],'Mdf',$Warning);

    }

    public function InnerSendReform($TaskID,$ReformID,$Platform='PC'){
        if($Platform == 'PC'){
            return $this->showReformList($TaskID);
        }else if($Platform=='Mobile'){
            return $this->showFastReformIndex($TaskID,$ReformID,'NO');
        }else{
            $Platform = str_replace('_','/',$Platform);
            $this->redirect(url($Platform));
        }
    }

    public function SendReform($TaskID,$ReformID,$Platform='PC',$bNeedInnerSend=true){
       $Role =  TaskCore::JudgeUserRoleByTaskID($TaskID);
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
                $ReformNewStatus = $this->ReformStatus['ActionIsNotDefined'];
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
            $TaskData['Status'] = TaskCore::STATUS_UNRECV;
            $Ret = TaskCore::CreateTask($TaskData);
            if(!empty($Ret['Ret'])){
                $this->assign("Warning","任务创建失败->".$Ret['Ret']);
                goto OUT;
            }else{
                db()->query("UPDATE ReformList SET ChildTaskID = ?,ReformStatus=?,CurDealCorp = DutyCorp WHERE id = ?",array($Ret['ID'],$ReformNewStatus,$ReformID));
            }
        }

        if($Reform['CurDealCorp']!=session('Corp') && $ReformStatus !='未下发' ){//除了草稿以外，CurDealCorp必须等于本部门
            $this->assign("Warning",'整改通知书非本部门,越权操作!');
            goto OUT;
        }

        if($ReformStatus == $this->ReformStatus['ActionIsNotDefined'] || $ReformStatus == $this->ReformStatus['ActionIsNotOk']){//当前状态为未指定措施，不过现在已经指定措施了!或者措施已提交等待审核，或者措施审核不通过
            $NotEmpyArrKeys = array('DirectCause','RootCause','CorrectiveAction','CorrectiveDeadline','PrecautionAction','PrecautionDeadline');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            db()->query("UPDATE ReformList SET CurDealCorp=IssueCorp,ReformStatus=? WHERE id = ?",array($this->ReformStatus['ActionIsMaked'],$ReformID));
            db()->query("UPDATE TaskList SET TaskInnerStatus = ? WHERE id = (SELECT ChildTaskID FROM ReformList WHERE id=?)",array(TaskCore::REFORM_ACTION_MAKED,$ReformID));
        }else if($ReformStatus == $this->ReformStatus['ActionIsMaked'] ){
            $NotEmpyArrKeys = array('ActionIsOK','ActionEval','ActionEvalerName','ActionEvalTime');
            foreach ($NotEmpyArrKeys as  $k){
                if(empty($Reform[$k])){
                    $this->assign("Warning",$k.'不能为空!');
                    goto OUT;
                }
            }
            $NewStatus  = '';
            $TaskInnerStatus = '';
            if($Reform['ActionIsOK']=='YES'){
                $NewStatus = $this->ReformStatus['ActionIsOk'];
                $TaskInnerStatus = TaskCore::REFORM_ACTION_ISOK;
            }else{
                $NewStatus = $this->ReformStatus['ActionIsNotOk'];
                $TaskInnerStatus = TaskCore::REFORM_ACTION_ISNOTOK;
            }
            db()->query("UPDATE ReformList SET CurDealCorp=DutyCorp,ReformStatus=? WHERE id = ?",array($NewStatus,$ReformID));
            db()->query("UPDATE TaskList SET TaskInnerStatus = ? WHERE id = (SELECT ChildTaskID FROM ReformList WHERE id=?)",array($TaskInnerStatus,$ReformID));
        }

       OUT:
            if($bNeedInnerSend){
                return $this->InnerSendReform($TaskID,$ReformID,$Platform);
            }
    }

    public function showDeleteReform($ReformID){
        $this->assign("ReformID",$ReformID);
        return view('DelReform');
    }

    public function DelReform(){
        $ReformID = input('ReformID');
        $Reform = db('ReformList')->where(array("id"=>$ReformID,'isDeleted'=>'否'))->select();
        if(empty($Reform)){
            $this->assign("Warning","你要删除的通知书不存在!");
            goto OUT;
        }
        $Pwd = input('Pwd');

        if(!$this->CheckDelPwd($Pwd)){
            $this->assign('Warning','删除密码错误!');
            goto OUT;
        }

        if($Reform[0]['ReformType'] != '问题整改'){
            $this->assign('Warning','本整改通知书属于'.$Reform[0]['ReformType'].'，请删除对应的隐患或者SMS.');
            goto OUT;
        }

        db('TaskList')->where(['id'=>$Reform[0]['ChildTaskID']])->delete();
        db('ReformList')->where(['id'=>$ReformID])->delete();
        db('IDCrossIndex')->where(['ToID'=>$ReformID,'Type'=>TaskCore::QUESTION_REFORM])->delete();

        $this->updateDelPwd();

        SuccessOUT:

            $this->assign('Warning','删除成功!');

        OUT:
        return view('DelReform');
    }

    public function GetReformStatusColor($ReformStatus){
            $Color = array(1=>'default',2=>'default',3=>'warning',4=>'info',5=>'danger',6=>'warning',7=>'success',8=>'danger');
            return $Color[$this->ReformStatus_AssginArr[$ReformStatus]];
    }

    public function GetReformDeadLineColor($DeadLineType,$Reform){
        $LabelType = '';
        if(empty($DeadLineType)||empty($Reform)){
            return 'kong';
        }

        $ReformIntStatus = $this->ReformStatus_AssginArr[$Reform['ReformStatus']];

        if($DeadLineType =='RequestFeedBackDate'){//要求的反馈日期
            $CPDate1 = strtotime($Reform['RequestFeedBackDate']);
            if($ReformIntStatus>=3){//措施已经制定
                $CPDate2 = strtotime($Reform['ActionMakeTime']);
            }else if($ReformIntStatus<3){
                $CPDate2 = strtotime(date('Y-m-d'));
            }
            $diff =  intval(($CPDate1 - $CPDate2) / 86400);
            if($diff>3){
                $LabelType = 'success';
            }else if($diff>1){
                $LabelType = 'warning';
            }else if($diff<1){
                $LabelType = 'danger';
            }
        }else if($DeadLineType =='CorrectiveDeadline' || $DeadLineType =='PrecautionDeadline'){
            $CPDate1 = strtotime($Reform[$DeadLineType]);
            if($ReformIntStatus>=6){
                $CPDate2 = strtotime($Reform['ProofUploadTime']);
            }else if($ReformIntStatus<6 && $ReformIntStatus>=4){
                $CPDate2 = strtotime(date('Y-m-d'));
            }
            $diff =  intval(($CPDate1 - $CPDate2) / 86400);
            if($diff>3){
                $LabelType = 'success';
            }else if($diff>1){
                $LabelType = 'warning';
            }else if($diff<1){
                $LabelType = 'danger';
            }
        }

        return $LabelType;
    }


    private function GetDiffInfo($CPDate1,$CPDate2){
        $diff = intval(($CPDate1 - $CPDate2) / 86400);
        if($diff>0){
            return '超期'.$diff.'天';
        }else{
            return '剩余'.-$diff.'天';
        }
    }

    private function GetDiffDayColor($CPDate1,$CPDate2){
        $diff = intval(($CPDate1 - $CPDate2) / 86400);
        if($diff>0){
            return 'danger';
        }else if($diff >=-3){
            return 'warning';
        }else{
            return 'success';
        }
    }

    public function GetReformMultiStatus($ReformID,$ReformData=NULL){//获取整改通知书的多个状态 反馈状态  预防措施状态  纠正措施状态
        if(empty($ReformData)){
            $Reform = db('ReformList')->where(array('id'=>$ReformID))->select()[0];
            if(empty($Reform)){
                return '';
            }
        }else{
            $Reform = $ReformData;
        }

        $CurDate = strtotime(date('Y-m-d'));
        $RequireFeedBackDate = strtotime($Reform['RequestFeedBackDate']);
        $FeedBackDate = strtotime(date('Y-m-d',strtotime($Reform['ActionMakeTime'])));
        $CorrectiveDeadline  = strtotime($Reform['CorrectiveDeadline']);
        $PrecautionDeadline  = strtotime($Reform['PrecautionDeadline']);
        $ActionIsOK = $Reform['ActionIsOK'];

        $isFeedBacked = empty($Reform['CorrectiveAction'])?false:true;

        $isCorrectiveActionUploaded = empty($Reform['CorrectiveActionProof'])?false:true;
        $CorrectiveActionProofEvalIsOK = $Reform['CorrectiveActionProofEvalIsOK'];
        if($isCorrectiveActionUploaded){
            $CorrectiveActionProofUploadTime = strtotime(date('Y-m-d',strtotime($Reform['CorrectiveActionProofUploadTime'])));
        }

        $PrecautionActionProofEvalIsOK = $Reform['PrecautionActionProofEvalIsOK'];
        $isPrecautionActionUploaded = empty($Reform['PrecautionActionProof'])?false:true;
        if($isPrecautionActionUploaded){
            $PrecautionActionProofUploadTime = strtotime(date('Y-m-d',strtotime($Reform['PrecautionActionProofUploadTime'])));
        }

        if($ActionIsOK=='YES'){
            $data['FeedBackInfo'] = '已通过';
            $data['FeedBackInfoColor'] = 'success';
            $data['FeedBackLeftDays'] = $this->GetDiffInfo($FeedBackDate,$RequireFeedBackDate);
            $data['FeedBackLeftDaysColor'] = $this->GetDiffDayColor($FeedBackDate,$RequireFeedBackDate);
        }else if($ActionIsOK=='NO'){
            $data['FeedBackInfo'] = '不通过';
            $data['FeedBackInfoColor'] = 'danger';
            $data['FeedBackLeftDays'] = $this->GetDiffInfo($FeedBackDate,$RequireFeedBackDate);
            $data['FeedBackLeftDaysColor'] = $this->GetDiffDayColor($FeedBackDate,$RequireFeedBackDate);
        }
        else if($isFeedBacked){
            $data['FeedBackInfo'] = '待审核';
            $data['FeedBackInfoColor'] = 'warning';
            $data['FeedBackLeftDays'] = $this->GetDiffInfo($FeedBackDate,$RequireFeedBackDate);
            $data['FeedBackLeftDaysColor'] = $this->GetDiffDayColor($FeedBackDate,$RequireFeedBackDate);
        }else{
            $data['FeedBackInfo'] = '未反馈';
            $data['FeedBackInfoColor'] = 'default';
            $data['FeedBackLeftDays'] = $this->GetDiffInfo($CurDate,$RequireFeedBackDate);
            $data['FeedBackLeftDaysColor'] = $this->GetDiffDayColor($CurDate,$RequireFeedBackDate);
        }

        if($ActionIsOK!='YES'){
            return $data;
        }

        if($CorrectiveActionProofEvalIsOK=='YES'){
            $data['CorrectiveInfo'] = '已通过';
            $data['CorrectiveInfoColor'] = 'success';
            $data['CorrectiveLeftDays'] = $this->GetDiffInfo($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
            $data['CorrectiveLeftDaysColor'] = $this->GetDiffDayColor($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
        }else if($CorrectiveActionProofEvalIsOK=='NO'){
            $data['CorrectiveInfo'] = '不通过';
            $data['CorrectiveInfoColor'] = 'danger';
            $data['CorrectiveLeftDays'] = $this->GetDiffInfo($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
            $data['CorrectiveLeftDaysColor'] = $this->GetDiffDayColor($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
        }else if($isCorrectiveActionUploaded){
            $data['CorrectiveInfo'] = '待审核';
            $data['CorrectiveInfoColor'] = 'warning';
            $data['CorrectiveLeftDays'] = $this->GetDiffInfo($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
            $data['CorrectiveLeftDaysColor'] = $this->GetDiffDayColor($CorrectiveActionProofUploadTime,$CorrectiveDeadline);
        }else{
            $data['CorrectiveInfo'] = '未提交';
            $data['CorrectiveInfoColor'] = 'default';
            if($CorrectiveDeadline){
                $data['CorrectiveLeftDays'] = $this->GetDiffInfo($CurDate,$CorrectiveDeadline);
                $data['CorrectiveLeftDaysColor'] = $this->GetDiffDayColor($CurDate,$CorrectiveDeadline);
            }
        }

        if($PrecautionActionProofEvalIsOK=='YES'){
            $data['PrecautionInfo'] = '已通过';
            $data['PrecautionInfoColor'] = 'success';
            $data['PrecautionLeftDays'] = $this->GetDiffInfo($PrecautionActionProofUploadTime,$PrecautionDeadline);
            $data['PrecautionLeftDaysColor'] = $this->GetDiffDayColor($PrecautionActionProofUploadTime,$PrecautionDeadline);
        }else if($PrecautionActionProofEvalIsOK=='NO'){
            $data['PrecautionInfo'] = '不通过';
            $data['PrecautionInfoColor'] = 'danger';
            $data['PrecautionLeftDays'] = $this->GetDiffInfo($PrecautionActionProofUploadTime,$PrecautionDeadline);
            $data['PrecautionLeftDaysColor'] = $this->GetDiffDayColor($PrecautionActionProofUploadTime,$PrecautionDeadline);
        }else if($isPrecautionActionUploaded){
            $data['PrecautionInfo'] = '待审核';
            $data['PrecautionInfoColor'] = 'warning';
            $data['PrecautionLeftDays'] = $this->GetDiffInfo($PrecautionActionProofUploadTime,$PrecautionDeadline);
            $data['PrecautionLeftDaysColor'] = $this->GetDiffDayColor($PrecautionActionProofUploadTime,$PrecautionDeadline);
        }else{
            $data['PrecautionInfo'] = '未提交';
            $data['PrecautionInfoColor'] = 'default';
            if($PrecautionDeadline){
                $data['PrecautionLeftDays'] = $this->GetDiffInfo($CurDate,$PrecautionDeadline);
                $data['PrecautionLeftDaysColor'] = $this->GetDiffDayColor($CurDate,$PrecautionDeadline);
            }
        }

        return $data;
    }

    public  function GetReformListByQuestionID($QsID=NULL){
        if(empty($QsID)){
            return '';
        }
        return db()->query('SELECT ReformList.* FROM QuestionList 
                        JOIN IDCrossIndex ON QuestionList.id =  IDCrossIndex.FromID AND QuestionList.id = ? 
                        JOIN ReformList ON IDCrossIndex.ToID = ReformList.id',array($QsID));
    }

    public function GetReformByCode(){
        $ReformCode = input('ReformCode');
        $Reform = db('ReformList')->where(['Code'=>$ReformCode])->select();
        if(!empty($Reform)){
            $Reform = $Reform[0];
        }

        return json_encode($Reform,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }


    public function GetReformDisplayPage($Code='',$Type='id'/*id Code*/){
        if(!in_array($Type,['id','Code'])){
            return '类型错误,id或Code选其一';
        }
        $Reform  = db('ReformList')->where([
            $Type=>$Code
        ])->select();

        if(empty($Reform)){
            return '整改通知书不存在!';
        }

        return $this->index($Reform[0]['ParentTaskID'],$Reform[0]['id'],'Mdf');
    }

}
