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
        if(session('Corp')==$this->SuperCorp){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC");
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' ORDER BY CheckName ",array(TaskCore::ONLINE_CheckTask));
        }else{

            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND DutyCorp= ? AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC",array(session('Corp')));
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' AND   TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?) ORDER BY CheckName",array(TaskCore::ONLINE_CheckTask,session('Name')));
        }


        $FDZC2019Ret = db()->query("SELECT  FirstHalfCheckTB.CheckSubject,
            CheckList.DutyCorp,
            QuestionList.QuestionTitle,
            QuestionList.Basis,
            QuestionList.Finder,
            ReformList.ReformTitle,
            ReformList.CorrectiveAction,
            ReformList.PrecautionAction,
            ReformList.CorrectiveDeadline,
            ReformList.PrecautionDeadline,
            ReformList.ReformStatus,
            QuestionList.id as FromID,
            CheckListDetail.RelatedTaskID
            FROM  
            CheckListDetail JOIN FirstHalfCheckTB ON CheckListDetail.FirstHalfTBID = FirstHalfCheckTB.id JOIN CheckList ON CheckListDetail.CheckListID = CheckList.id  
            JOIN `TaskList` ON  CheckListDetail.RelatedTaskID = TaskList.id  
            JOIN QuestionList ON TaskList.RelateID = QuestionList.id 
            LEFT JOIN IDCrossIndex ON QuestionList.id = IDCrossIndex.FromID 
            JOIN ReformList ON IDCrossIndex.ToID = ReformList.id 
            WHERE CheckListDetail.RelatedTaskID IS NOT NULL AND QuestionList.QuestionSource = '2019年维修单位法定自查' ");

        $ZXDC201905RetList = db()->query("SELECT  FirstHalfCheckTB.CheckSubject,
            CheckList.DutyCorp,
            QuestionList.QuestionTitle,
            QuestionList.Basis,
            QuestionList.Finder,
            ReformList.ReformTitle,
            ReformList.CorrectiveAction,
            ReformList.PrecautionAction,
            ReformList.CorrectiveDeadline,
            ReformList.PrecautionDeadline,
            ReformList.ReformStatus,
            QuestionList.id as FromID,
            CheckListDetail.RelatedTaskID
            FROM  
            CheckListDetail JOIN FirstHalfCheckTB ON CheckListDetail.FirstHalfTBID = FirstHalfCheckTB.id JOIN CheckList ON CheckListDetail.CheckListID = CheckList.id  
            JOIN `TaskList` ON  CheckListDetail.RelatedTaskID = TaskList.id  
            JOIN QuestionList ON TaskList.RelateID = QuestionList.id 
            LEFT JOIN IDCrossIndex ON QuestionList.id = IDCrossIndex.FromID 
            JOIN ReformList ON IDCrossIndex.ToID = ReformList.id 
            WHERE CheckListDetail.RelatedTaskID IS NOT NULL AND QuestionList.QuestionSource = '201905工程系统专项督察' ");

        $this->assign('FDZC2019RetList',$FDZC2019Ret);
        $this->assign('FDZCQsCnt',count($FDZC2019Ret));
        $this->assign('ZXDC201905RetList',$ZXDC201905RetList);
        $this->assign('ZXDC201905Cnt',count($ZXDC201905RetList));
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

    public function showQuestionList(){
        //超级部门领导可以看到本GroupCorp的所有部门收到的尚未完成的问题，超级部门的成员可以看到领导分配给自己的任务列表
        //其他部门领导只可以看到本部门的收到的所有问题，超级部门的成员可以看到领导分配给自己的任务列表
        $IsSuperCorp = $this->IsSuperCorp();
        $CorpRole = session('CorpRole');
        $RecvCorpSEL = input('RecvCorpSEL');
        $QsType = input('QsType');
        $TaskSource= input('TaskSource');
        $TaskName = input('TaskName');

        if($TaskSource=='全部'){
            $TaskSource ='';
        }

        if($QsType=='全部'){
            $QsType = '';
        }
        if($RecvCorpSEL=='全部'){
            $RecvCorpSEL = '';
        }

        if($IsSuperCorp){
            $MyDealTaskIDList = db('TaskDealerGroup')->join('TaskList',' TaskDealerGroup.TaskID = TaskList.id')->where(
                                                                                                    array('Name'=>session('Name'),
                                                                                                            'TaskType'=>array('IN',array(TaskCore::REFORM_SUBTASK,
                                                                                                            TaskCore::QUESTION_REFORM,
                                                                                                            TaskCore::QUESTION_SUBMITED,
                                                                                                            TaskCore::QUESTION_FAST_REFORM))))->order('ReciveCorp,TaskName')->select();
            $MyDealTaskIDList = array_column($MyDealTaskIDList,'TaskID');

            if($CorpRole=='领导'){//超级部门的领导

                $whereAnd = array('isDeleted'=>'否',
                    'TaskType'=>array('IN',empty($QsType)?array(TaskCore::REFORM_SUBTASK,
                        TaskCore::QUESTION_REFORM,
                        TaskCore::QUESTION_SUBMITED,
                        TaskCore::QUESTION_FAST_REFORM):array($QsType)),
                    'TaskList.Status'=>array('neq','已完成'),
                    'ReciveCorp'=>array("IN",empty($RecvCorpSEL)?array_column($this->CorpMng->GetAllCorpsInGroupCorp($this->GetGroupCorp()),'Corp'):array($RecvCorpSEL)));

                $WhereOr = ((empty($QsType)||($QsType=='我参与的任务'))
                                    &&  (empty($RecvCorpSEL)||$RecvCorpSEL==session('Corp')))?
                                            array('id'=>['IN',$MyDealTaskIDList],"TaskList.Status"=>['neq','已完成']):"1>2";

                $W['And'] = $whereAnd;
                $W['Or']  = $WhereOr;

                $QTaskList = db('TaskList')->where(function ($query)use($W){
                    $query->where($W['And'])->whereOr(function ($query)use($W){
                        $query->where( $W['Or']);
                    });
                })->where(function($query)use ($TaskSource) {
                    if(!empty($TaskSource)){
                        $query->where(array('TaskSource'=>$TaskSource));
                    }

                })->where(function($query)use ($TaskName) {
                    if(!empty($TaskName)){
                        $query->where(array('TaskName'=>array('LIKE','%'.$TaskName.'%')));
                    }

                })->order('ReciveCorp,TaskName')->select();

            }else{//超级部门的其他成员
                $QTaskList = db('TaskDealerGroup')->field('DISTINCT TaskID,TaskList.*')->join('TaskList','TaskDealerGroup.TaskID = TaskList.id')->where(
                                array('Name'=>session('Name'),
                                    'TaskList.Status'=>array('neq','已完成'),
                                    'TaskType'=>array('IN',array(TaskCore::REFORM_SUBTASK,
                                        TaskCore::QUESTION_REFORM,
                                        TaskCore::QUESTION_SUBMITED,
                                        TaskCore::QUESTION_FAST_REFORM))))
                                    ->where(function($query)use ($TaskSource) {
                                        if(!empty($TaskSource)){
                                            $query->where(array('TaskSource'=>$TaskSource));
                                        }})->where(function($query)use ($TaskName) {
                                            if(!empty($TaskName)){
                                                $query->where(array('TaskName'=>array('LIKE','%'.$TaskName.'%')));
                                            }})
                                        ->order('ReciveCorp,TaskName')->select();
            }

        }else{//非超级部门
            $RecvCorpSEL = session('Corp');

            $MyDealTaskIDList = db('TaskDealerGroup')->join('TaskList',' TaskDealerGroup.TaskID = TaskList.id')->where(
                array('Name'=>session('Name'),
                    'TaskSource'=>array('LIKE',$TaskSource),
                    'TaskType'=>array('IN',array(TaskCore::REFORM_SUBTASK,
                        TaskCore::QUESTION_REFORM,
                        TaskCore::QUESTION_SUBMITED,
                        TaskCore::QUESTION_FAST_REFORM))))->select();

            $MyDealTaskIDList = array_column($MyDealTaskIDList,'TaskID');

            if($CorpRole=='领导'){//可以看到本部门以及子孙部门的任务

                $WhereOr =((empty($QsType)||($QsType=='我参与的任务')) &&  (empty($RecvCorpSEL)||$RecvCorpSEL==session('Corp')))?
                    array('id'=>['IN',$MyDealTaskIDList],"TaskList.Status"=>['neq','已完成']):"1>2";

                $WhereAnd = array('isDeleted'=>'否',
                    'TaskType'=>array('IN',empty($QsType)?array(TaskCore::REFORM_SUBTASK,
                        TaskCore::QUESTION_REFORM,
                        TaskCore::QUESTION_SUBMITED,
                        TaskCore::QUESTION_FAST_REFORM):array($QsType)),
                    'TaskList.Status'=>array('neq','已完成'),
                    'ReciveCorp'=>array("IN",$this->CorpMng->GetChildrenCorps($this->GetCorp())));

                $W['And'] = $WhereAnd;
                $W['Or'] = $WhereOr;
                $QTaskList = db('TaskList')->where(
                                function ($query)use($W){
                                    $query->where($W['And'])->whereOr(function ($query)use($W){
                                        $query->where( $W['Or']);
                                    });
                            })
                            ->where(function($query)use ($TaskSource) {
                                if(!empty($TaskSource)){
                                    $query->where(array('TaskSource'=>$TaskSource));
                                }})
                            ->where(function($query)use ($TaskName) {
                                if(!empty($TaskName)){
                                    $query->where(array('TaskName'=>array('LIKE','%'.$TaskName.'%')));
                                }})->order('ReciveCorp,TaskName')->select();


            }else{//非超级部门的成员
                $QTaskList = db('TaskDealerGroup')->field('DISTINCT TaskID,TaskList.*')->join('TaskList','TaskDealerGroup.TaskID = TaskList.id')->where(
                    array('Name'=>session('Name'),
                        'TaskList.Status'=>array('neq','已完成'),
                        'TaskType'=>array('IN',array(TaskCore::REFORM_SUBTASK,
                            TaskCore::QUESTION_REFORM,
                            TaskCore::QUESTION_SUBMITED,
                            TaskCore::QUESTION_FAST_REFORM))))->where(function($query)use ($TaskSource) {
                        if(!empty($TaskSource)){
                            $query->where(array('TaskSource'=>$TaskSource));
                        }})->where(function($query)use ($TaskName) {
                        if(!empty($TaskName)){
                            $query->where(array('TaskName'=>array('LIKE','%'.$TaskName.'%')));
                        }

                    })->order('ReciveCorp,TaskName')->select();
            }
        }
        $this->assign('IsSuperCorp',$IsSuperCorp);
        $this->assign('SourceNameList',db('QuestionSource')->order('SourceName ASC')->select());
        $this->assign('CorpList',$IsSuperCorp?$this->CorpMng->GetAllCorpsInGroupCorp($this->GetGroupCorp()):NULL);
        $this->assign("QsTaskList",$QTaskList);
        $this->assign("Cnt",1);
        return view('QsList');
    }

    public function showRFList(){
        $ReformList = '';
        if($this->IsSuperCorp()){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC");
         }else{
            $ReformList = db()->query("SELECT * FROM ReformList WHERE ReformStatus<>'整改效果审核通过' AND DutyCorp= ? AND isDeleted ='否' Order BY DutyCorp,IssueDate ASC",array(session('Corp')));
        }
        $this->assign("ReformList",$ReformList);
        $this->assign('RFCnt',1);
        return view('RFList');
    }

    public function showOCList(){
        if($this->IsSuperCorp()){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' ORDER BY CheckName ",array(TaskCore::ONLINE_CheckTask));
        }else{

            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' AND   TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?) ORDER BY CheckName",array(TaskCore::ONLINE_CheckTask,session('Name')));
        }

        $this->assign("OCTaskList",$OCTaskList);
        $this->assign('OCCnt',1);
        return view('OCList');

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
}
