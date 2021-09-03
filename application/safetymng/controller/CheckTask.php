<?php
namespace app\safetymng\controller;

use think\db;
use think\controller;
use think\Session;
use think\Request;

class CheckTask extends FDZCRoot{
    public $CheckTaskStatus_Arr = array('CheckListUndefined'=>'检查单未制定',
                                        'CheckListIsDefined'=>'检查单已制定',
                                        'CheckIsStarted'    =>'检查已开始',
                                        'CheckIsFinished'   =>'检查已结束');

    public $CheckTaskIntStatus_Arr = array( '检查单未制定'=>1,
                                            '检查单已制定'=>2,
                                            '检查已开始'  =>3,
                                            '检查已结束'  =>4);

    private  $CorpMng = NULL;
    private  $SysCnf = NULL;
    public function __construct(Request $request = null)
    {
        $this->CorpMng = new CorpMng();
        $this->SysCnf = new SysConf();
        parent::__construct($request);
    }

    public function index(){
        $UserList = $this->IsSuperCorp()?$this->CorpMng->GetGroupCorpUserList($this->GetGroupCorp()):$this->CorpMng->GetCorpUserList($this->GetCorp());
        $this->assign('UserList',$UserList);
        $this->assign('QuestionSource',db('QuestionSource')->select());
        $this->assign('Today',date('Y-m-d'));
        $this->assign('CorpList',$this->GetCorpList());
        return view('index');
    }



    public function CreateCheckTask(){
        $data['CheckName']    = input('CheckName');
        $data['CheckSource']  = input('CheckSource');
        $data['ScheduleDate'] = input('ScheduleDate');
        $data['CheckSource']  = input('CheckSource');
        $data['DutyCorp']     = input('Corp');

        foreach ($data as $v){
            if(empty($v)){
                $this->assign("Warning",'请填写所有要素.');
                goto OUT1;
            }
        }

        $CodePre = db()->query("SELECT CodePre FROM QuestionSource WHERE SourceName = ? ",array($data['CheckSource']))[0]['CodePre'];
        if(empty($CodePre)){
            $this->assign("Warning",$data['CheckSource']."检查来源不存在!");
            goto OUT1;
        }

        $C = substr($CodePre,-1,1);

        if( $C[0]!="1"){
            $CodePre.='-';
        }

        $data['CheckCode'] = $CodePre .'JX-'.date('YmdHis');
        $data['TaskID'] = 0;
        $data['Status'] = $this->CheckTaskStatus_Arr['CheckListUndefined'];
        $data['AddTime'] = date('Y-m-d H:i:s');
        $id = db('CheckList')->insertGetId($data);
        if(empty($id)){
            $this->assign("Warning",'创建检查任务失败!');
            goto OUT1;
        }

        $data = array();
        $data['TaskType'] = TaskCore::ONLINE_CheckTask;
        $data['TaskName'] = input('CheckName');
        $data['ReciveCorp'] = TaskCore::MULT_CORP;
        $data['RelateID'] = $id;
        $data['CreateTime'] = date('Y-m-d H:i:s');
        $data['ParentID'] = 0;
        $data['CreatorName'] = session('Name');
        $data['TaskSource'] = $data['CheckSource'];
        $Ret = TaskCore::CreateTask($data);
        if(empty($Ret['ID'])){
            $this->assign("Warning",'创建检查任务失败:'.$Ret['Ret']);
            goto OUT1;
        }else{
            //更新检查者一列和任务ID
            $Checkers = input('ManagerSelect');
            $GroupDealer = input('post.GroupDealer/a');
            if(!empty($GroupDealer)){
                foreach ($GroupDealer as $v){
                    $Checkers.= ' '.$v;
                }
            }

            db('CheckList')->where(array('id'=>$id))->update(array('Checkers'=>$Checkers,'TaskID'=>$Ret['ID']));
        }

        $CTask  = new TaskCore();
        $Ret = $CTask->TaskAlign($Ret['ID'],'YES');
        dump($Ret);
        if($Ret!='任务分配成功！'){
            $this->assign("Warning",$Ret);

        }

        OUT:
            $this->redirect('/SafetyMng/CheckTask/showCheckListMng/CheckListID/'.$id);

        OUT1:
            return $this->index();
    }

    public function showCheckListMng($CheckListID){
        $CheckInfoRow = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];
        $SQL = "SELECT CheckBaseDB.BaseName,FirstHalfCheckTB.ProfessionName,FirstHalfCheckTB.BusinessName,FirstHalfCheckTB.CheckSubject,
        FirstHalfCheckTB.Code1,SecondHalfCheckTB.Code2,FirstHalfCheckTB.CheckContent,FirstHalfCheckTB.CheckStandard,
        SecondHalfCheckTB.ComplianceStandard,SecondHalfCheckTB.CheckMethods,SecondHalfCheckTB.BasisName,SecondHalfCheckTB.InnerManual,
        SecondHalfCheckTB.BasisTerm,SecondHalfCheckTB.CheckFrequency,CheckListDetail.RelatedTaskID,CheckListDetail.DealType,CheckListDetail.id as CheckListRowId,CheckListDetail.Checker,CheckListDetail.IsOk,CheckListDetail.DealType,
        TIMESTAMPDIFF(SECOND,CheckListDetail.StartTime,CheckListDetail.EndTime) as CostSecond,CheckListDetail.FeedBack,CheckProof.id as ProofID,CheckListDetail.id as CheckListDetailID,CheckListDetail.CheckListID
        
        FROM FirstHalfCheckTB JOIN SecondHalfCheckTB JOIN CheckListDetail JOIN CheckBaseDB ON CheckBaseDB.id=FirstHalfCheckTB.BaseDBID AND SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.id AND 
              SecondHalfCheckTB.IsValid ='YES' AND FirstHalfCheckTB.IsValid = 'YES' AND CheckListDetail.CheckDBID = FirstHalfCheckTB.BaseDBID 
              AND CheckListDetail.FirstHalfTBID = FirstHalfCheckTB.id AND CheckListDetail.SecondHalfTBID = SecondHalfCheckTB.id LEFT JOIN CheckProof ON CheckProof.CheckListDetailID = CheckListDetail.id AND CheckProof.isDeleted='NO' WHERE CheckListDetail.CheckListID=? ORDER BY CheckListDetail.SecondHalfTBID ASC ";
        $Ret = db()->query($SQL,array($CheckListID));

        $CKIntStatus  = $this->CheckTaskIntStatus_Arr[$CheckInfoRow['Status']];
        $this->assign('NeedShowCheckRowMngBtn',$CKIntStatus>=$this->CheckTaskIntStatus_Arr[$this->CheckTaskStatus_Arr['CheckListIsDefined']]?0:1);
        $this->assign('CheckInfoRow',$CheckInfoRow);
        $this->assign('CheckListID',$CheckListID);
        $this->assign('CheckList',$Ret);
        return view('CheckList');
    }

    public function CheckRowQuery($CheckListID=NULL){

        $CTBMng = new CheckTBMng();

        $data['BaseDBID']       = $CTBMng->RMInputPre(input('CheckDB'));
        $data['ProfessionName'] = "%".$CTBMng->RMInputPre(input('ProfessionName'))."%";
        $data['BusinessName']   = "%".$CTBMng->RMInputPre(input('BusinessName'))."%";
        $data['Code1']          = "%".$CTBMng->RMInputPre(input('Code1'))."%";
        $data['Code2']          = "%".$CTBMng->RMInputPre(input('Code2'))."%";
        $data['CheckSubject']   = "%".$CTBMng->RMInputPre(input('CheckSubject'))."%";
        $data['CheckContent']   = "%".$CTBMng->RMInputPre(input('CheckContent'))."%";
        $data['CheckStandard']  = "%".$CTBMng->RMInputPre(input('CheckStandard'))."%";


        $ChildCorp              = input('DutyCorp2');
        $ItemCheckType          = input('ItemCheckType');
        $StartDate              = input('StartDate');

        $Code2InStr = "";
        if(!empty($ItemCheckType) || !empty($StartDate) || !empty($ChildCorp)){
            $ItemCheckInfo_Arr = $this->Get_unCheckAndunPlaned_CheckItemList_AfterStartDate_ByCorp($data['BaseDBID'],$StartDate,$ChildCorp,$ChildCorp);
            $AllItem_Arr = $ItemCheckInfo_Arr['AllCode2'];
            $CheckedItem_Arr = $ItemCheckInfo_Arr['CheckedCode2'];
            $unCheckedArr = array_diff($AllItem_Arr,$CheckedItem_Arr);
            if($ItemCheckType == '未被检查的条款'){
                $Code2InStr = "  SecondHalfCheckTB.Code2 IN ".$this->BuildInSqlFromArr(
                        $unCheckedArr
                    )." AND ";
            }else if($ItemCheckType == '尚未被检查但已列入检查计划的条款'){
                $Code2InStr = "  SecondHalfCheckTB.Code2 IN ".$this->BuildInSqlFromArr(
                        array_intersect($unCheckedArr,$ItemCheckInfo_Arr['PlanedCode2'])
                    )." AND ";
            }else if($ItemCheckType == '尚未被检查且未列入检查计划的条款'){
                $Code2InStr = "  SecondHalfCheckTB.Code2 IN ".$this->BuildInSqlFromArr(
                        array_diff($unCheckedArr,$ItemCheckInfo_Arr['PlanedCode2'])
                    )." AND ";
            }else if($ItemCheckType == '已被检查的条款'){
                $Code2InStr = "  SecondHalfCheckTB.Code2 IN ".$this->BuildInSqlFromArr(
                        $ItemCheckInfo_Arr['CheckedCode2']
                    )." AND ";
            }
        }

        $SQL =  "SELECT SecondHalfCheckTB.*,
                        FirstHalfCheckTB.id as FHId,
                        FirstHalfCheckTB.BaseDBID,
                        FirstHalfCheckTB.ProfessionName,
                        FirstHalfCheckTB.BusinessName,
                        FirstHalfCheckTB.Code1,
                        SecondHalfCheckTB.Code2,
                        FirstHalfCheckTB.CheckSubject,
                        FirstHalfCheckTB.CheckStandard,
                        CheckListDetail.id as CheckListRowId FROM SecondHalfCheckTB JOIN FirstHalfCheckTB ON 
                        SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.StandardID
                         JOIN SecondHalfCheckTB_Corp_Corss ON SecondHalfCheckTB_Corp_Corss.Code2 = SecondHalfCheckTB.Code2 
                         AND SecondHalfCheckTB_Corp_Corss.BaseDBID = SecondHalfCheckTB.BaseDBID
                         AND  SecondHalfCheckTB_Corp_Corss.Corp = ?
                         LEFT JOIN CheckListDetail  ON   CheckListDetail.Code2 = SecondHalfCheckTB.Code2 
                         AND CheckListDetail.CheckDBID = SecondHalfCheckTB.BaseDBID AND CheckListDetail.CheckListID = ?
                         WHERE
                         ".$Code2InStr." 
                        FirstHalfCheckTB.BaseDBID= ? AND 
                        FirstHalfCheckTB.ProfessionName like ? AND 
                        FirstHalfCheckTB.BusinessName LIKE ? AND 
                        FirstHalfCheckTB.Code1 LIKE ? AND 
                        SecondHalfCheckTB.Code2 LIKE ? AND 
                        FirstHalfCheckTB.CheckSubject LIKE ? AND 
                        FirstHalfCheckTB.CheckContent LIKE ? AND 
                        FirstHalfCheckTB.CheckStandard LIKE ? AND 
                        FirstHalfCheckTB.IsValid = 'YES' AND 
                        SecondHalfCheckTB.IsValid = 'YES' 
                        ";

        $this->assign('RelatedCorps',json_encode(input('RelatedCorps/a'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));

        $CheckRowListData =  db()->query($SQL,
            [
                $ChildCorp,
                $CheckListID,
                $data['BaseDBID'],
                $data['ProfessionName'],
                $data['BusinessName'],
                $data['Code1'],
                $data['Code2'],
                $data['CheckSubject'],
                $data['CheckContent'],
                $data['CheckStandard']
            ]);

        for($j=0;$j<count($CheckRowListData);$j++){
            $Code2 = $CheckRowListData[$j]['Code2'];
            if(!empty($Code2)){
                $CheckRowListData[$j]['Corps'] = db('SecondHalfCheckTB_Corp_Corss')->where([
                    'Code2'         =>$Code2,
                    'BaseDBID'      =>$data['BaseDBID']
                ])->select();
            }
        }


        $this->assign('CheckRowList',$CheckRowListData);
        return $this->showCheckSelectRow($CheckListID);
    }

    public function showCheckSelectRow($CheckListID=NULL)
    {
        if(empty($CheckListID)){
            return "检查单ID不可为空";
        }
        $this->assign('CorpList',$this->GetCorpList());
        $this->assign('CheckDB',db('CheckBaseDB')->select());
        $this->assign('CheckListID',$CheckListID);
        $this->assign('CheckListInfo',db('CheckList')->where(array('id'=>$CheckListID))->select()[0]);
        return view('CheckRowSelect');
    }

    public function showOnlineCheckIndex($CheckListID=NULL){
        if(empty($CheckListID)){
            return '检查单编号为空!';
        }

        $CKListData = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];
        $this->assign('CheckInfoRow',$CKListData);
        return view('OnlineCheckIndex');
    }

    public function showQuestionInputByCheckRow($CheckRowID){
        $Ret = db()->query('SELECT * FROM CheckListDetail JOIN CheckList ON CheckList.id = CheckListDetail.CheckListID AND CheckListDetail.id = ? 
                        JOIN FirstHalfCheckTB ON FirstHalfCheckTB.id = CheckListDetail.FirstHalfTBID JOIN SecondHalfCheckTB ON SecondHalfCheckTB.id = CheckListDetail.SecondHalfTBID',array($CheckRowID))[0];
        if(empty($Ret)){
            return '条款不存在！';
        }

        $data['QuestionSourceName'] = $Ret['CheckSource'];
        $data['Inspectors'] = session('Name');
        $data['Basis'] = $Ret['BasisTerm'];
        $data['DutyCorp'] = $Ret['DutyCorp'];
        $data['CallBackURL'] = '/SafetyMng/CheckTask/CHGCheckRowDataStatus/CheckRowID/'.$CheckRowID.'/DealType/1'.'/RelatedID/';
        $data['CheckSubject'] = $Ret['CheckSubject'];

        $QsIN = new QuestionInput();
        return $QsIN->index($data,'Mobile');
    }

    public function showFastReformByCheckRow($CheckRowID)
    {
        $Ret = db()->query('SELECT * FROM CheckListDetail JOIN CheckList ON CheckList.id = CheckListDetail.CheckListID AND CheckListDetail.id = ? 
                        JOIN FirstHalfCheckTB ON FirstHalfCheckTB.id = CheckListDetail.FirstHalfTBID JOIN SecondHalfCheckTB ON SecondHalfCheckTB.id = CheckListDetail.SecondHalfTBID',array($CheckRowID))[0];
        if(empty($Ret)){
            return '条款不存在！';
        }

        $data['QuestionSourceName'] = $Ret['CheckSource'];
        $data['Inspectors'] = session('Name');
        $data['Basis'] = $Ret['BasisTerm']. $Ret['ComplianceStandard'];
        $data['DutyCorp'] = $Ret['DutyCorp'];
        $data['CallBackURL'] = '/SafetyMng/CheckTask/CHGCheckRowDataStatus/CheckRowID/'.$CheckRowID.'/DealType/2'.'/RelatedID/';
        $data['CheckSubject'] = $Ret['CheckSubject'];

        $RF = new Reform();
        return $RF->showFastReformIndex(0,0,'YES',$data);
    }

    public function saveCheckRowResult(){

        $CheckRowID = input('CheckRowID');
        $CheckResult = input('CheckResult');
        $CurOrderID = intval(input('CurOrderID'));
        $CheckListID = input('CheckListID');

        if(empty($CheckRowID)){
            return '条款ID及顺序ID不能为空';
        }

        if(empty($CheckResult)){
            $this->assign('Warning','没有选择检查结果!');
            goto OUT1;
        }

        $CheckRowData = db('CheckListDetail')->where(array('id'=>$CheckRowID))->select()[0];

        if(empty($CheckRowData)){
            $this->assign('Warning','检查条款不存在!');
            goto OUT1;
        }

        $CurResult = $CheckRowData['IsOk'];
        if(empty($CurResult) && $CurResult!='NO'){//说明没有设定结果，并且结果为YES
            db('CheckListDetail')->where(array('id'=>$CheckRowID))->update(
                        array('Checker'=>session('Name'),
                               'EndTime'=>date('Y-m-d H:i:s'),
                                'IsOk'=>$CheckResult));
        }

        $CheckRows = db('CheckListDetail')->order('SecondHalfTBID ASC')->where(array('CheckListID'=>$CheckListID))->select();
        $CheckInfo = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];
        //检查是不是所有条款都检查完毕了
        $unCPTRow  = db()->query('SELECT id FROM CheckListDetail WHERE IsOk IS NULL AND CheckListID = ?',array($CheckListID));
        if(count($unCPTRow)==0 && empty($CheckInfo['EndTime'])){//本检查单已经全部完成了
            if($CheckInfo['Status'] == $this->CheckTaskStatus_Arr['CheckIsStarted']){
                db('CheckList')->where(array('id'=>$CheckListID))->update(array(
                    'Status'=>$this->CheckTaskStatus_Arr['CheckIsFinished'],
                    'EndTime'=>date('Y-m-d H:i:s'),
                    'TotalSecondCosted'=>$this->GetCheckCostTime($CheckListID),
                    'OkRowCnt'=>$this->GetCheckOKRowCnt($CheckListID)));
            }
        }

        $RowCnt = count($CheckRows);
        $NextOrderID = intval($CurOrderID)+1;
        if($NextOrderID>$RowCnt){
            $NextOrderID =1;
        }


        OUT:
            $this->redirect('/SafetyMng/CheckTask/showOnlineCheckPage/CheckListID/'.$CheckListID.'/CurOrderID/'.$NextOrderID);

        OUT1:
            return $this->showOnlineCheckPage($CheckListID,$CurOrderID);
    }

    public function GetCheckCostTime($CheckListID){
        $TotalSecond = db()->query('SELECT SUM(TIMESTAMPDIFF(SECOND,StartTime,EndTime)) as TotalSecond,CheckListID FROM `CheckListDetail` WHERE 
                                    StartTime IS NOT NULL AND EndTime IS NOT NULL  AND CheckListID = ? GROUP  BY CheckListID',array($CheckListID))[0]['TotalSecond'];
        return  $TotalSecond;
    }

    public function GetCheckListCompleteProgress($CheckListID){
        $RowCPT = db()->query('SELECT COUNT(id) as RowCPT FROM CheckListDetail WHERE CheckListID = ? AND  IsOk IS NOT NULL',array($CheckListID))[0]['RowCPT'];
        $RowCnt = db()->query('SELECT CheckRowCnt FROM CheckList WHERE id= ?' ,array($CheckListID))[0]['CheckRowCnt'];
        $RowCnt = intval($RowCnt);
        if($RowCnt==0){
            return '0%';
        }else{
            $Ret = $RowCPT/ $RowCnt * 100;
            return substr($Ret.'',0,5).'%';
        }
    }

    public function GetCheckOKRowCnt($CheckListID){
        $OKRowCnt = db('CheckListDetail')->field('count(id) as CNT')->where(array('CheckListID'=>$CheckListID,'IsOk'=>'YES'))->select()[0]['CNT'];
        return $OKRowCnt;
    }

    public function GetCheckunOKRowCnt($CheckListID){
        $OKunRowCnt = db('CheckListDetail')->field('count(id) as CNT')->where(array('CheckListID'=>$CheckListID,'IsOk'=>'NO'))->select()[0]['CNT'];
        return $OKunRowCnt;
    }

    public function UpdateCheckListOKRowCnt($CheckListID){
        $OkRowCnt = $this->GetCheckOKRowCnt($CheckListID);
        db('CheckList')->where(array('id'=>$CheckListID))->update(array('OkRowCnt'=>$OkRowCnt));
        return "CheckListID:".$CheckListID." OKRowCnt:".$OkRowCnt;
    }

    public function UpdateAllCheckListOKRowCnt(){
        $CkIds = db('CheckList')->field('id,CheckName')->select();
        if(!empty($CkIds)){
            foreach ($CkIds as $v){
                $OkRowCnt = $this->GetCheckOKRowCnt($v['id']);
                db('CheckList')->where(array('id'=>$v['id']))->update(array('OkRowCnt'=>$OkRowCnt));
                echo $v['CheckName']."==>".'符合项:'.$OkRowCnt.'<br/>';
            }

        }

    }

    public function showOnlineCheckPage($CheckListID=NULL,$CurOrderID=0){
        $CurOrderID = intval($CurOrderID);
        if(empty($CheckListID)){
            return "检查单ID不可为空";
        }

        $CKListData = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];

        if(empty($CKListData)){
            return '检查单不存在!';
        }

        $CheckRows = db('CheckListDetail')->order('SecondHalfTBID ASC')->where(array('CheckListID'=>$CheckListID))->select();

        if(empty($CheckRows)){
            return '检查条款不存在!';
        }

        $CheckRowData = array();

        if($CurOrderID<1){//如果没有制定条款号，则自动跳到第一个未检查项目
            $CheckRowData = db()->query('SELECT * FROM CheckListDetail WHERE CheckListID=? AND IsOK IS NULL ORDER BY SecondHalfTBID LIMIT 1',array($CheckListID))[0];
            if(empty($CheckRowData)){
                $CheckRowData = db()->query('SELECT * FROM CheckListDetail WHERE CheckListID=?  ORDER BY SecondHalfTBID ASC LIMIT 1',array($CheckListID))[0];
            }else{//更新CurOrderID为第一个未检查项目的顺序号
                $CurOrderID = db()->query('SELECT Count(id) as Cnt FROM CheckListDetail WHERE CheckListID=?  AND SecondHalfTBID <=?',
                    array($CheckListID,$CheckRowData['SecondHalfTBID']))[0]['Cnt'];
            }
        }else{//按照$CurOrderID
            if($CurOrderID<=count($CheckRows)){
                $RealID = $CheckRows[$CurOrderID-1]['id'];
            }else{//如果大于条款总数量，则跳转到最后一个条款
                $RealID = $CheckRows[0]['id'];
                $CurOrderID = 1;
            }

            $CheckRowData = db()->query('SELECT * FROM CheckListDetail WHERE id = ?',array($RealID))[0];
        }

        if(empty($CheckRowData['IsOk'])){//还没有开始检查
            db('CheckListDetail')->where(array('id'=>$CheckRowData['id']))->update(array('StartTime'=>date('Y-m-d H:i:s')));
        }

        $this->assign('CheckRowData',$CheckRowData);
        $this->assign('CurOrderID',$CurOrderID);
        $this->assign('CheckInfoRow',$CKListData);
        $this->assign('CheckListID',$CheckListID);
        return view('OnlineCheckPage');
    }


    public function StartCheck($CheckListID= NULL ){
        if(empty($CheckListID)){
            return '检查单ID不可为空!';
        }

       $Ret = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];
        if(empty($Ret)){
            return '检查单不存在!';
        }

        if(empty($Ret['StartTime'])){
            db('CheckList')->where(array('id'=>$CheckListID))->update(array('StartTime'=>date('Y-m-d H:i:s')));
        }

        if($Ret['Status']==$this->CheckTaskStatus_Arr['CheckListIsDefined']){//检查单已经制定
            db('CheckList')->where(array('id'=>$CheckListID))->update(array('Status'=>$this->CheckTaskStatus_Arr['CheckIsStarted']));
        }else if($Ret['Status']==$this->CheckTaskStatus_Arr['CheckIsStarted']){//检查已经开始

        }else{
            //return '检查单当前的状态为:'.$Ret['Status'];
        }

        OUT:
            return $this->showOnlineCheckPage($CheckListID);
    }



    public function CHGCheckRowDataStatus($CheckRowID=0,$DealType=1/*1:问题提交,2:立即整改*/,$RelatedID=0){
        $DealType_Arr  = array(1=>'问题提交',2=>'立即整改');
        $Title = '';
        $Content = '';

        if(empty($CheckRowID) || empty($RelatedID) || !array_key_exists($DealType,$DealType_Arr)){
            $Title =  $DealType_Arr[$DealType].'失败!!';
            $Content = '请您关闭本页面再操作一次试试:检查条款ID或关联ID为空，或者处理类型不存在!';
            goto OUT;
        }

        switch ($DealType){
            case 1:{//问题提交
                  $DataRow =   db('QuestionList')->where(array('id'=>$RelatedID))->select()[0];
                  $NonConfirmDesc = $DataRow['QuestionInfo'];
                break;
            }
            case 2:{//立即整改
                  $DataRow =   db('ReformList')->where(array('id'=>$RelatedID))->select()[0];
                  $NonConfirmDesc = $DataRow['NonConfirmDesc'];
                break;
            }
        }

        $DutyCorp = db()->query('SELECT DutyCorp FROM CheckList JOIN  CheckListDetail ON CheckListDetail.id = ? AND 
                              CheckList.id = CheckListDetail.CheckListID',array($CheckRowID))[0]['DutyCorp'];

        if(empty($DataRow) || empty($DutyCorp)){
            $Title =  $DealType_Arr[$DealType].'失败!!';
            $Content = '请您关闭本页面再操作一次试试:问题或者整改找不到或者责任单位为空!';
            goto OUT;
        }



        $Cnt =  db('CheckListDetail')->where(array('id'=>$CheckRowID))->
                    update(array('RelatedTaskID'=>empty($DataRow['TaskID'])?$DataRow['ParentTaskID']:$DataRow['TaskID'],
                                 'RelatedID'=>$RelatedID,
                                 'DealType'=>$DealType_Arr[$DealType],
                                 'NonConfirmDesc'=>$NonConfirmDesc,
                                  'IsOk'=>'NO',
                                  'Checker'=>session('Name'),
                                  'EndTime'=>date('Y-m-d H:i:s'),
                                  'NonConfirmDutyCorp'=>$DutyCorp));

        $Title = $DealType_Arr[$DealType].($Cnt==1?'操作成功!':'操作失败!');
        $Content = '请您关闭本页面，'.($Cnt==1?'继续检查。':'再操作一次试试。');
        OUT:

        $this->assign('Title',$Title);
        $this->assign('Content',$Content);
        return view('CheckRowDealTypeCHGStatus');
    }

    function showCheckIsFinished($CheckListID){
        if(empty($CheckListID)){
            return '检查单ID不可为空!';
        }

        $Ret = db('CheckList')->where(array('id'=>$CheckListID))->select()[0];
        if(empty($Ret)){
            return '检查单不存在!';
        }

        return $this->showOnlineCheckIndex($CheckListID);

    }

    function GetCheckTimeCostStr($Second){
        $Second = intval($Second);
        return intval($Second/3600).'小时'.intval(($Second%3600)/60).'分'.  ($Second%60).'秒';
    }

    function showFeedBackPage($CheckRowID=0){
        if(empty($CheckRowID)){
            return '条款ID不能为空!';
        }

        $this->assign('CheckRowID',$CheckRowID);
        $this->assign('FeedBack',db('CheckListDetail')->where(array('id'=>$CheckRowID))->select()[0]['FeedBack']);
        return view('CheckDetailFeedBack');
    }

    function saveCheckDetailFeedBack($CheckRowID=0){
        if(empty($CheckRowID)){
            return '条款ID不能为空!';
        }
        $FeedBack = input('FeedBack');
        db('CheckListDetail')->where(array('id'=>$CheckRowID))->update(
            array('FeedBack'=>$FeedBack,
                'FeedBackTime'=>date('Y-m-d H:i:s'),
                'FeedBacker'=>session('Name')
            ));
        return $this->showFeedBackPage($CheckRowID);
    }

    function showLookCkProof($CheckListID=NULL,$CheckListDetailID=NULL){

        if(empty($CheckListID)||empty($CheckListDetailID)){
            return '输入参数不全';
        }

        $this->assign('CheckListID',$CheckListID);
        $this->assign('CheckListDetailID',$CheckListDetailID);
        $Ck = db('CheckList')->where(array('id'=>$CheckListID))->find();
        $this->assign('CheckInfoRow',$Ck);
        $this->assign('CkProofList',db('CheckProof')->where(
            array('CheckListID'=>$CheckListID,
                'CheckListDetailID'=>$CheckListDetailID,
                'IsDeleted'=>'NO'
            ))->select());
        return view('showCkProof');
    }

    function AddCkProof(){
        $CheckListID    = input('CheckListID');
        $CheckListDetailID  = input('CheckListDetailID');
        $CkProofEdit    = input('CkProofEdit');

        if(empty($CheckListID) || empty($CheckListDetailID)){
            return '输入参数不全';
        }

        $Ck = db('CheckList')->where(array('id'=>$CheckListID))->find();
        if(empty($Ck) || $Ck['Status']==$this->CheckTaskStatus_Arr['CheckIsFinished']){
            return '检查不存在或者已经结束!';
        }

        $Ret = db('CheckListDetail')->where(
            array('CheckListID'=>$CheckListID,
                'id'=>$CheckListDetailID)
        )->select();



        if(empty($Ret)){
            return '检查条款不存在!';
        }

        $data['CheckListID'] = $CheckListID;
        $data['CheckListDetailID'] = $CheckListDetailID;
        $data['CkProof'] = htmlspecialchars($CkProofEdit);
        $data['AdderName'] = session('Name');
        $data['AddTime'] = date('Y-m-d H:i:s');
        $id = db('CheckProof')->insertGetId($data);
        if(!empty($id)){
            return $this->showLookCkProof($CheckListID,$CheckListDetailID);
        }else{
            return '正向证据添加失败!联系李光耀吧.';
        }
    }

    public function DelProof($ProofID=NULL){
        if(empty($ProofID)){
            return '输入参数不全!';
        }

        $Ret = db('CheckProof')->where(array('id'=>$ProofID))->find();
        if(empty($Ret)){
            return '证据不存在!';
        }

        $CheckListID = $Ret['CheckListID'];
        $CheckListDetailID = $Ret['CheckListDetailID'];

        if($Ret['AdderName']!=session('Name')){
            return '您不是证据添加人,不允许删除!';
        }

        $Ck = db('CheckList')->where(array('id'=>$CheckListID))->find();
        if(empty($Ck) || $Ck['Status']==$this->CheckTaskStatus_Arr['CheckIsFinished']){
            return '检查不存在或者已经结束!';
        }

        db('CheckProof')->where(array('id'=>$ProofID))->update(array('IsDeleted'=>'YES'));
        return $this->showLookCkProof($CheckListID,$CheckListDetailID);

    }

    public function  GetCheckProofCnt($CheckListID=NULL,$CheckListDetailID=NULL){
        $Ret = db('CheckProof')->where(
            array('CheckListID'=>$CheckListID,
                'CheckListDetailID'=>$CheckListDetailID,
                'IsDeleted'=>'NO',
            ))->select();
        return json(['ProofCnt'=>empty($Ret)?0:count($Ret)]);
    }

    public function CheckListQry(){
        $CheckName      = input('CheckName');
        $DutyCorp1      = input('DutyCorp1');
        $DutyCorp2      = input('DutyCorp2');
        $PlanStartDate  = input('PlanStartDate');
        $PlanEndDate    = input('PlanEndDate');
        $MeIn           = input('MeIn');
        $CheckSource    = input('CheckSource');
        $CheckStatus    = input('CheckStatus');

        $CorpInStr = "";
        if(empty($DutyCorp2) || $DutyCorp2 =='全部'){
            $CorpInStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
        }else{
            $RR  = db('CorpList')->where(['Corp'=>$DutyCorp2])->find();
            if(!empty($RR)){
                $CorpInStr = "('".$RR['Corp']."')";
            }
        }

        $TaskIDInSql = "";
        if($MeIn =='on'){
            $TaskID_Arr  = db()->query("SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?",[session('Name')]);
            $TaskIDInSql = $this->BuildInSqlFromArr($TaskID_Arr,'TaskID');
        }

        $Sql = "SELECT *,B.id as CheckListID FROM TaskList A JOIN CheckList B ON B.id = A.RelateID WHERE A.isDeleted = '否' AND A.TaskType = ?";
        $ParamArr =[] ;
        $ParamArr[] = TaskCore::ONLINE_CheckTask;

        $inputArr = [
            ['F'=>'B.CheckName',          'V'=>'%'.$CheckName.'%',          'FH'=>'LIKE',           'I_V'=>$CheckName],
            ['F'=>'B.ScheduleDate',       'V'=>$PlanStartDate,              'FH'=>'>=',             'I_V'=>$PlanStartDate],
            ['F'=>'B.ScheduleDate',       'V'=>$PlanEndDate,                'FH'=>'<=',             'I_V'=>$PlanEndDate],
            ["F"=>"B.DutyCorp",           "V"=>$CorpInStr,                  'FH'=>"IN_CONNECT",      "I_V"=>$CorpInStr],
            ['F'=>'A.TaskSource',         'V'=>$CheckSource,                'FH'=>'=',              'I_V'=>$CheckSource],
            ['F'=>'A.Status',             'V'=>$CheckStatus,                'FH'=>'=',              'I_V'=>$CheckStatus],
            ['F'=>'A.id',                 'V'=>$TaskIDInSql,                'FH'=>'IN_CONNECT',      'I_V'=>$TaskIDInSql]
        ];

        $R = $this->CreateSubQrySQL($Sql,$ParamArr,$inputArr);
        $SQL = $R['MainSql'];
        $ParamArr = $R['ParamArr'];

        $OCList = db()->query($SQL,$ParamArr);

        $this->assign("OCTaskList",$OCList);

        OUT:
            return $this->showOCList();

    }


    public function showOCList(){
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('QuestionSource',$this->SysCnf->GetQuestionSourceList());
        /*if($this->IsSuperCorp()){
            //超级部门的所有成员都可以看到所有整改通知书及所有检查任务
            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' ORDER BY CheckName ",array(TaskCore::ONLINE_CheckTask));
        }else{

            $OCTaskList = db()->query("SELECT *,CheckList.id as CheckListID FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID WHERE isDeleted = '否' AND TaskType = ?   AND TaskList.Status <> '已完成' AND   TaskList.id in 
                                (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?) ORDER BY CheckName",array(TaskCore::ONLINE_CheckTask,session('Name')));
        }

        $this->assign("OCTaskList",$OCTaskList);*/
        $this->assign('OCCnt',1);
        return view('OCList');
    }

    public function DeleteCheckTask(){
        $Msg            = '';
        $TaskID         = input('TaskID');
        $Pwd            = input('Pwd');

        if(empty($TaskID) || empty($Pwd)){
            $Msg = '没选择要删除的任务或者密码为空!';
            goto OUT;
        }

        if(!$this->CheckDelPwd($Pwd)){
            $Msg = '删除密码错误!';
            goto OUT;
        }

        $TaskRow = db('TaskList')->where(['id'=>$TaskID])->find();

        if(empty($TaskRow)){
            $Msg = '任务不存在.';
            goto OUT;
        }

        $CkListRow = db('CheckList')->where(['id'=>$TaskRow['RelateID']])->find();
        if(empty($TaskRow)){
            $Msg = '检查单不存在.';
            goto OUT;
        }

        if($TaskRow['Status'] =='检查已完成'){
            $Msg = '检查已完成,不允许删除.';
            goto OUT;
        }

        $RR = db()->query("SELECT * FROM CheckListDetail WHERE RelatedID is NOT NULL AND CheckListID = ? ",[$CkListRow['id']]);
        if($RR){
            $Msg = '该检查已有问题提交或者整改通知书下发，不允许删除.';
            goto OUT;
        }


        db('CheckListDetail')->where(['CheckListID'=>$CkListRow['id']])->delete();
        db('CheckList')->where(['id'=>$CkListRow['id']])->delete();
        db('TaskList')->where(['id'=>$TaskID])->delete();
        $this->updateDelPwd();
        $Msg = 'OK';

        OUT:
            return $Msg;
    }

}