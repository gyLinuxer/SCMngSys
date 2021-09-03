<?php
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2018/12/4
 * Time: 20:31
 */
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;


class TaskList extends PublicController
{
    private  $CorpMng = NULL;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->CorpMng = new CorpMng();
    }

    public function Index($ActiveLi = 'QuestionMng')
    {
        $CorpRole = session('CorpRole');
        $QTaskList = ''; //问题列表
        $OCTaskList = '';//在线检查任务列表
        $ReformList = '' ;//整改通知书列表
        if($CorpRole=='领导'){
            //部门领导可以看到任务接收部门为本部门的所有任务，以及任务处理人员名单里面有他的任务
            $QTaskList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND TaskType <> ?   AND TaskList.Status <> '已完成' AND  (ReciveCorp = ? OR TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?))",array(TaskCore::ONLINE_CheckTask,session("Corp"),session('Name')));

        }else{
            //普通成员可以看到任务处理人员名单里面有他的任务
            $QTaskList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND TaskType <> ? AND TaskList.Status <> '已完成' AND  TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?)",array(TaskCore::ONLINE_CheckTask,session('Name')));

        }
        //整改通知书列表

        if(session('CorpInfo')['IsSuperCorp']=='YES'){

        }

        if(session('Corp')==$this->SuperCorp){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND isDeleted ='否' AND DutyCorp IN (SELECT Corp FROM CorpList WHERE GroupCorp = ?) Order BY DutyCorp,IssueDate ASC",[session('GroupCorp')]);
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID AND TaskList.ReciveCorp  IN (SELECT Corp FROM CorpList WHERE GroupCorp = ?) WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' ORDER BY CheckName ",[session('Corp'),TaskCore::ONLINE_CheckTask]);
        }else{
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND DutyCorp= ? AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC",array(session('Corp')));
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' AND   TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?) ORDER BY CheckName",array(TaskCore::ONLINE_CheckTask,session('Name')));
        }

        $this->assign('ActiveLI',$ActiveLi);
        $this->assign('QCnt',count($QTaskList));
        $this->assign('RFCnt',count($ReformList));
        $this->assign('OCCnt',count($OCTaskList));
        $this->assign("ReformList",$ReformList);
        $this->assign("QTaskList",$QTaskList);
        $this->assign("OCTaskList",$OCTaskList);
        $this->assign("Cnt",1);
        $this->assign("Count",1);
        return view('index');
    }


    public function showTaskList(){
        /*                           待处理                                执法任务                                                整改任务
         * 1、超级部门领导        本部门接收到的未接收任务         超级部门所在超级区域下所有下发的执法任务(分配权)                                       我收到的整改通知书或者立即整改
         * 2、超级部门成员        本部门接收到的未接收任务         超级部门所在超级区域下所有下发的执法任务(分配权)                                       我收到的整改通知书或者立即整改
         * 3、普通部门领导        本部门接收到的未接收任务         我参与的或者我作为执法者下发的(分配权) 或者本部门下发的自我执法任务    我收到的整改通知书或者立即整改
         * 4、普通部门成员        无                            我参与的或者我作为执法者下发的 (分配权)                              我参与的整改通知书或者立即整改
         * */
        $IsSuperCorp = $this->IsSuperCorp();
        $CorpRole = session('CorpRole');
        $SuperCorpArea = session('CorpInfo')['SuperCorpArea'];
        $Name = session('Name');
        $Corp = session('Corp');
        $unRecvList = [];
        $ZFRWList = [];
        $ZGRWList = [];

        /*
         *  const SMS_REFORM = 'SMS-整改';
            const AQYH_REFORM = '安全隐患-整改';
            const QUESTION_REFORM = '问题-整改';
            const QUESTION_SUBMITED = '问题-待处理';
            const REFORM_SUBTASK = '整改通知书';
            const QUESTION_FAST_REFORM = '问题-立即整改';
            const ONLINE_CheckTask = '在线检查任务';
         * */

        $ZFRWInSql = "('".TaskCore::QUESTION_SUBMITED."','".TaskCore::QUESTION_REFORM."','".TaskCore::QUESTION_FAST_REFORM."')";//执法任务类型集合

        /*$MyAllLevelChildren = $this->CorpMng->GetCorpAllLevelChildrenRows($SuperCorpArea);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }

        if(strlen($InCaseStr)==1){
            $InCaseStr.='0)';
        }else{
            $InCaseStr[strlen($InCaseStr)-1]= ')';
        }*/

        $InCaseStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($SuperCorpArea);

        $MyDealTaskIDList = db()->query("
                SELECT TaskID FROM  TaskDealerGroup WHERE Name = ? ",
                [session('Name')]);

        $MyDealTaskIDList = array_column($MyDealTaskIDList,'TaskID');
        $InCaseStr2 = '(';
        foreach ($MyDealTaskIDList as $v){
            $InCaseStr2 .= $v.',';
        }

        if(strlen($InCaseStr2)==1){
            $InCaseStr2.='0)';
        }else{
            $InCaseStr2[strlen($InCaseStr2)-1]= ')';
        }

        if($IsSuperCorp){//超级部门人员

            $InCaseStr[strlen($InCaseStr)-1]= ',';
            $InCaseStr.= "'".$Corp."')";

            $ZFRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND TaskType IN ".$ZFRWInSql." AND
                   ((  ReciveCorp IN ".$InCaseStr .") OR (id in ".$InCaseStr2.")) ");

            $unRecvList = db()->query(
                "SELECT * FROM TaskList WHERE isDeleted = '否' AND  (ReciveCorp = ? OR ReciveCorp = ?)  AND Status = ? ",
                [$SuperCorpArea,$Corp,TaskCore::STATUS_UNRECV]
            );

            $ZGRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND TaskType =?  AND 
                   (( ReciveCorp = ? ) OR (TaskType =?  AND id in ".$InCaseStr2.")) ",[TaskCore::REFORM_SUBTASK,$Corp,TaskCore::REFORM_SUBTASK]);
        }else{
            if($CorpRole == '领导'){
                $ZFRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND
                   (( TaskType IN ".$ZFRWInSql." AND SenderCorp = ? AND SenderName = ?) OR (id in ".$InCaseStr2.")) ",[$Corp,$Name]);

                $unRecvList = db()->query(
                    "SELECT * FROM TaskList WHERE isDeleted = '否' AND  ReciveCorp = ?  AND Status = ? ",
                    [$Corp,TaskCore::STATUS_UNRECV]
                );

                $ZGRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND TaskType =?  AND
                   ((  ReciveCorp = ? ) OR (id in ".$InCaseStr2.")) ",[TaskCore::REFORM_SUBTASK,$Corp]);
            }else{
                $ZFRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND TaskType IN ".$ZFRWInSql." AND
                   ((  SenderCorp = ? AND SenderName = ?) OR (id in ".$InCaseStr2.")) ",[$Corp,$Name]);
                $ZGRWList = db()->query("SELECT * FROM TaskList WHERE isDeleted = '否' AND  Status = '".TaskCore::STATUS_DEALING."' AND
                   ( TaskType =? AND id in ".$InCaseStr2.") ",[TaskCore::REFORM_SUBTASK]);
            }
        }

        //dump($unRecvList);
        //dump($ZFRWList);
        //dump($ZGRWList);

        $this->assign('ZFRWList',$ZFRWList);
        $this->assign('unRecvList',$unRecvList);
        $this->assign('ZGRWList',$ZGRWList);
        return view('QsList');
    }

    public function showRFList(){
        $ReformList = '';
        if($this->IsSuperCorp()){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $InCaseSql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr(session('CorpInfo')['SuperCorpArea']);
            $ReformList = db()->query("SELECT * FROM ReformList WHERE DutyCorp IN ".$InCaseSql." AND ReformStatus<>'整改效果审核通过' AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC");
         }else{
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND DutyCorp= ? AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC",array(session('Corp')));
        }
        $this->assign("ReformList",$ReformList);
        $this->assign('RFCnt',1);
        return view('RFList');
    }



    public function showMBTaskDetail($TaskID = 0,$ReformID = 0){
        if(empty($TaskID)){
            return '任务ID不可为0';
        }

        $TaskDataRow =  db('TaskList')->where(array('id'=>$TaskID))->select()[0];

        if(empty($TaskDataRow)){
            return '任务不存在!';
        }

        $Ret = TaskCore::FindReformOrQuestionByTaskID($TaskID);
        if(empty($Ret['Ret'])){
            return "关联问题不存在!";
        }else if($Ret['Type']=='Reform'){
            $QuestionID  = $Ret['Ret']["RelatedQuestionID"];
        }else if($Ret['Type']=='Question'){
            $QuestionID  = $Ret['Ret']["id"];
        }

        $dataRow  = db('QuestionList')->where(array("id"=>$QuestionID))->select()[0];

        $ReformC = new Reform();
        $ReformList = $ReformC->GetReformListByTaskID($TaskID);
        //选择了单个整改通知书
        $bFind = false;
        $CurReform = array();
        if(!empty($ReformID)){
            foreach($ReformList as $R){
                if($R['id']==$ReformID){
                    $bFind = true;
                    $CurReform = $R;
                }
            }
        }

        $TaskType = $TaskDataRow['TaskType'];
        if(strpos($TaskType,TaskCore::QUESTION_REFORM)===0 ||
            strpos($TaskType,TaskCore::QUESTION_FAST_REFORM)===0||
            strpos($TaskType,TaskCore::REFORM_SUBTASK)===0){
            $this->assign('showReformList','YES');
        }

        if($bFind){
            $this->assign('ActiveLI','LiReformList');
        }
        $this->assign('CurReform',$CurReform);
        $this->assign('ReformID',$ReformID);
        $this->assign("ReformList",$ReformList);
        $this->assign('TaskDataRow',db('TaskList')->where(array('id'=>$TaskID))->select()[0]);
        $this->assign('QuestionData',$dataRow);
        $this->assign("CurPlatform","Mobile");
        $this->assign("TaskID",$TaskID);
        return view('MBTaskDetail');
    }

    public function showJFView(){
        $UserType = session('UserType');
        $UserID = session('UserID');
        $SourceName = input('SourceName');
        $ZFRWInSql = "('".TaskCore::QUESTION_SUBMITED."','".TaskCore::QUESTION_REFORM."','".TaskCore::QUESTION_FAST_REFORM."')";//执法任务
        $pArr = [$UserID];

        $qySql = "SELECT * FROM TaskList 
                WHERE TaskType IN ".$ZFRWInSql." AND isDeleted <>'是' AND  TaskSource IN 
                (SELECT SourceName FROM  JFViewSource_User_Cross WHERE UserID = ?";

        if(!empty($SourceName) && $SourceName!='全部'){
            $qySql .= ' AND SourceName = ? )';
            $pArr[] =  $SourceName;
        }else{
            $qySql.=")";
        }


        if($UserType!='局方用户'){
            return '您并非局方用户';
        }

        $rows  = db()->query($qySql,$pArr);
        $SourceList = db('JFViewSource_User_Cross')->where(['UserID'=>$UserID])->select();
        $this->assign('SourceList',$SourceList);
        $this->assign('rows',$rows);
        return view('JFUserView');
    }
}
