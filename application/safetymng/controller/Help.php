<?php
namespace app\safetymng\controller;
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2018/12/8
 * Time: 20:40
 */
use think\Controller;
use think\Log;
class Help extends Controller
{
    public function uploadFile(){
        //dump(request()->file());
        $R = '';
        $file = request()->file('file');
       // dump(request()->file());
        // 移动到框架应用根目录/public/upload/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'upload');
            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                //echo $info->getExtension();
                // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                $R =  '/upload/'.$info->getSaveName();
                // 输出 42a79759f284b767dfcb2a0197904287.jpg
                //echo $info->getFilename();
            }else{
                // 上传失败获取错误信息
                $R =  $file->getError();
            }
        }

        return $R;
    }

    public function  Ajax_SelectLinkage(){

        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $EventSel = $PostData_Arr['EventSel'];
        $NeedFakeID = $PostData_Arr['FakeID'];
        $NeedFakeCheckStandardID = $PostData_Arr['FakeCheckStandardID'];
        $Data_Arr = $PostData_Arr['data'];

        $SelNameList = array('CheckDB'=>'ProfessionName',
                            'ProfessionName'=>'BusinessName',
                            'BusinessName'=>'CheckSource',
                            'CheckSource'=>'CheckSubject',
                            'CheckSubject'=>'Code1',
                            'Code1'=>'CheckContent',
                            'CheckContent'=>'CheckStandard',
                            'CheckStandard'=>'');

        $SelNameIndex = array('CheckDB'=>0,
                            'ProfessionName'=>1,
                            'BusinessName'=>2,
                            'CheckSource'=>3,
                            'CheckSubject'=>4,
                            'Code1'=>5,
                            'CheckContent'=>6,
                            'CheckStandard'=>7);

        if(!array_key_exists($EventSel,$SelNameList)){
            return json([]);
        }

        $SelText = $Data_Arr[$SelNameIndex[$EventSel]]['SelText'];
        $SelVal  = $Data_Arr[$SelNameIndex[$EventSel]]['SelVal'];
        if(!empty($NeedFakeID)){
            $FakeID = "CONCAT('lgy19891115-',".$SelNameList[$EventSel].") AS ";
        }else{
            $FakeID = '';
        }

        $FakeCheckStandardID = '';
        if(!empty($NeedFakeCheckStandardID)){
            $FakeCheckStandardID = "CONCAT('lgy19891115-',".$SelNameList[$EventSel].") AS ";
        }



        switch ($EventSel){
            case 'CheckDB':{
               return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                                ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                               ->where(array('BaseName'=>$SelText))
                               ->select()));
               break;
            }
            case 'ProfessionName':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                    ->where(array('BaseName'=>$BaseDBName,
                                    'IsValid'=>'YES',
                                    'ProfessionName'=>$SelText))
                    ->select()));
                break;
            }
            case 'BusinessName':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                $ProfessionName =  $Data_Arr[$SelNameIndex['ProfessionName']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                    ->where(array('BaseName'=>$BaseDBName,
                        'IsValid'=>'YES',
                        'ProfessionName'=>$ProfessionName,
                        'BusinessName'=>$SelText))
                    ->select()));
                break;
            }
            case 'CheckSource':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                $ProfessionName =  $Data_Arr[$SelNameIndex['ProfessionName']]['SelText'];
                $BusinessName   =  $Data_Arr[$SelNameIndex['BusinessName']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                    ->where(array('BaseName'=>$BaseDBName,
                        'ProfessionName'=>$ProfessionName,
                        'IsValid'=>'YES',
                        'BusinessName'=>$BusinessName,
                        'CheckSource'=>$SelText))
                    ->select()));
                break;
            }
            case 'CheckSubject':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                $ProfessionName =  $Data_Arr[$SelNameIndex['ProfessionName']]['SelText'];
                $BusinessName   =  $Data_Arr[$SelNameIndex['BusinessName']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                    ->where(array('BaseName'=>$BaseDBName,
                        'ProfessionName'=>$ProfessionName,
                        'IsValid'=>'YES',
                        'BusinessName'=>$BusinessName,
                        'CheckSubject'=>$SelText))
                    ->select()));
                break;
            }
            case 'Code1':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                $ProfessionName =  $Data_Arr[$SelNameIndex['ProfessionName']]['SelText'];
                $BusinessName   =  $Data_Arr[$SelNameIndex['BusinessName']]['SelText'];
                $CheckSubject =  $Data_Arr[$SelNameIndex['CheckSubject']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text,'.$FakeID.' id')
                    ->where(array('BaseName'=>$BaseDBName,
                        'ProfessionName'=>$ProfessionName,
                        'IsValid'=>'YES',
                        'BusinessName'=>$BusinessName,
                        'CheckSubject'=>$CheckSubject,
                        'Code1'=>$SelText))
                    ->select()));
                break;
            }
            case 'CheckContent':{
                $BaseDBName =  $Data_Arr[$SelNameIndex['CheckDB']]['SelText'];
                $ProfessionName =  $Data_Arr[$SelNameIndex['ProfessionName']]['SelText'];
                $BusinessName   =  $Data_Arr[$SelNameIndex['BusinessName']]['SelText'];
                $Code1 =  $Data_Arr[$SelNameIndex['Code1']]['SelText'];
                $Code2 =  $Data_Arr[$SelNameIndex['Code2']]['SelText'];
                $CheckSubject =  $Data_Arr[$SelNameIndex['CheckSubject']]['SelText'];
                return json(array('TargetSel'=>$SelNameList[$EventSel],'data'=>db('FirstHalfCheckTB')->join('CheckBaseDB ', 'CheckBaseDB.id=FirstHalfCheckTB.BaseDBID')
                    ->field('distinct '.$SelNameList[$EventSel].' as text, '.$FakeCheckStandardID.' FirstHalfCheckTB.id')
                    ->where(array('BaseName'=>$BaseDBName,
                        'ProfessionName'=>$ProfessionName,
                        'BusinessName'=>$BusinessName,
                        'CheckSubject'=>$CheckSubject,
                        'IsValid'=>'YES',
                        'Code1'=>$Code1,
                        'CheckContent'=>$SelText))
                    ->select()));
                break;
        }
        }

    }

    private function  GetOneRowFromTable($TBName,$id){
       return db($TBName)->where(array('id'=>$id))->select()[0];
    }

    public function  GetFirstHalfCheckTBRowById($id=0){
        return json(db()->query("SELECT FirstHalfCheckTB.*,CheckBaseDB.id as CheckDBId,CheckBaseDB.BaseName as BaseName 
              FROM FirstHalfCheckTB JOIN CheckBaseDB ON FirstHalfCheckTB.BaseDBID = CheckBaseDB.id WHERE FirstHalfCheckTB.StandardID = ? AND IsValid='YES' ",array($id))[0]);
    }

    public function Ajax_AddRowToCheckList(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $Ret['Ret'] = 'Failed';
        $FailedCks_Arr = array();
        if(empty($PostData_Arr)){
            $Ret['Msg'] = '提交数据为空';
            goto OUT;
        }

        $CKListId = intval($PostData_Arr[0]['CheckListId']);
        $CKStatus = db('CheckList')->where(array('id'=>$CKListId))->select()[0]['Status'];
        if(empty($CKStatus)){
            $Ret['Msg'] = '编号为'.$CKListId.'的检查任务不不存在!';
            goto OUT;
        }
        $CkTask = new CheckTask();
        $CKIntStatus  = $CkTask->CheckTaskIntStatus_Arr[$CKStatus];
        if($CKIntStatus>=$CkTask->CheckTaskIntStatus_Arr[$CkTask->CheckTaskStatus_Arr['CheckListIsDefined']]){
            //检查单已经制定好了
            $Ret['Ret'] = 'Failed';
            $Ret['Msg'] = '检查单已经制定好了';
            goto OUT;
        }

        foreach ($PostData_Arr as $v){
            $FHID = intval($v['FHID']);
            $SHID = intval($v['SHID']);
            $BaseDBID  = intval($v['BaseDBID']);
            $CKListId = intval($v['CheckListId']);
            $CkId = intval($v['CkId']);

            //先看CheckListDetail中有没有这个条款
            $dbREt = db('CheckListDetail')->where(array('CheckDBID'=>$BaseDBID,'CheckListID'=>$CKListId,'FirstHalfTBID'=>$FHID,'SecondHalfTBID'=>$SHID))->select();
            if(empty($dbREt)){
                //开始添加
                //1、检查正确性
                $dbREt =  db()->query("SELECT SecondHalfCheckTB.id FROM SecondHalfCheckTB JOIN FirstHalfCheckTB ON SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.id AND 
              SecondHalfCheckTB.IsValid ='YES' AND FirstHalfCheckTB.IsValid = 'YES' WHERE SecondHalfCheckTB.CheckStandardID = ? AND SecondHalfCheckTB.id = ? AND FirstHalfCheckTB.BaseDBID = ?",
                    array($FHID,$SHID,$BaseDBID));
                if(empty($dbREt)){//提交的检查项目不合法
                    $FailedCks_Arr[] = $CkId;
                }else{
                    $RR = db()->query("INSERT INTO 
                    CheckListDetail(
                    CheckDBID,
                    CheckListID,
                    FirstHalfTBID,
                    SecondHalfTBID,
                    CheckStandSnap,
                    ComplianceStandard,
                    AddTime,
                    ProfessionName,
                    BusinessName,
                    CheckSubject,
                    Code1,
                    Code2,
                    CheckContent,
                    CheckMethods,
                    BasisName,
                    BasisTerm,
                    InnerManual,
                    CheckFrequency)
                    SELECT FirstHalfCheckTB.BaseDBID as CheckDBID,
                            ? as CheckListID,
                            FirstHalfCheckTB.id as FirstHalfTBID,
                            SecondHalfCheckTB.id as SecondHalfTBID,
                            FirstHalfCheckTB.CheckStandard as CheckStandSnap,
                            SecondHalfCheckTB.ComplianceStandard as ComplianceStandard,
                            ? as AddTime,
                            FirstHalfCheckTB.ProfessionName,
                            FirstHalfCheckTB.BusinessName,
                            FirstHalfCheckTB.CheckSubject,
                            FirstHalfCheckTB.Code1,
                            SecondHalfCheckTB.Code2,
                            FirstHalfCheckTB.CheckContent,
                            SecondHalfCheckTB.CheckMethods,
                            SecondHalfCheckTB.BasisName,
                            SecondHalfCheckTB.BasisTerm,
                            SecondHalfCheckTB.InnerManual,
                            SecondHalfCheckTB.CheckFrequency
                     FROM FirstHalfCheckTB JOIN SecondHalfCheckTB  ON SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.id AND 
                          SecondHalfCheckTB.IsValid ='YES' AND FirstHalfCheckTB.IsValid = 'YES' 
                    WHERE SecondHalfCheckTB.CheckStandardID = ? AND SecondHalfCheckTB.id = ? AND FirstHalfCheckTB.BaseDBID = ?",
                    array($CKListId,date('Y-m-d H:i:s'),$FHID,$SHID,$BaseDBID)
                    );
                    if(empty($RR)){
                        $FailedCks_Arr[] = $CkId;
                    }
                }
            }
            //dump(db()->getLastSql());


            if(empty($FailedCks_Arr)){
                $Ret['Ret'] = 'Success';
            }else{
                $Ret['Ret'] = 'PartFailed';
            }
            $Ret['FailedCkIds'] = $FailedCks_Arr;

        }

        OUT:
            return json($Ret);
    }

    public function Ajax_DelCheckListRow(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $Ret['Ret'] = 'Failed';
        $FailedCks_Arr = array();

        if(empty($PostData_Arr)){
            $Ret['Msg'] = '提交数据为空';
            goto OUT;
        }


        $CKListId = intval($PostData_Arr[0]['CheckListId']);
        $CKStatus = db('CheckList')->where(array('id'=>$CKListId))->select()[0]['Status'];
        if(empty($CKStatus)){
            $Ret['Msg'] = '编号为'.$CKListId.'的检查任务不不存在!';
            goto OUT;
        }
        $CkTask = new CheckTask();
        $CKIntStatus  = $CkTask->CheckTaskIntStatus_Arr[$CKStatus];
        if($CKIntStatus>=$CkTask->CheckTaskIntStatus_Arr[$CkTask->CheckTaskStatus_Arr['CheckListIsDefined']]){
            //检查单已经制定好了
            $Ret['Ret'] = 'Failed';
            $Ret['Msg'] = '检查单已经制定好了';
            goto OUT;
        }

        foreach ($PostData_Arr as $v){
            $CheckListRowId = $v['CheckListRowId'];
            $CkId = $v['CkId'];
            $CheckListId = $v['CheckListId'];
            $Cnt = db('CheckListDetail')->where(array('id'=>$CheckListRowId,'CheckListID'=>$CheckListId))->delete();
            if($Cnt==0){
                $FailedCks_Arr[] = $CkId;
            }
        }

        if(empty($FailedCks_Arr)){
            $Ret['Ret'] = 'Success';
        }else{
            $Ret['Ret'] = 'PartFailed';
        }

    OUT:

        return json($Ret);
    }

    public function Ajax_SetCheckTaskToCheckListIsDefined(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $Ret['Ret'] = 'Failed';


        $CKListId = intval($PostData_Arr['CheckListId']);
        $CKStatus = db('CheckList')->where(array('id'=>$CKListId))->select()[0]['Status'];
        if(empty($CKStatus)){
            $Ret['Msg'] = '编号为'.$CKListId.'的检查任务不不存在!';
            goto OUT;
        }
        $CkTask = new CheckTask();
        $CKIntStatus  = $CkTask->CheckTaskIntStatus_Arr[$CKStatus];
        if($CKIntStatus>=$CkTask->CheckTaskIntStatus_Arr[$CkTask->CheckTaskStatus_Arr['CheckListIsDefined']]){
            //检查单已经制定好了
            $Ret['Msg'] = '检查单已经制定好了';
            goto OUT;
        }
        $CkRowCnt = db('CheckListDetail')->field('count(id) as Cnt')->where(array('CheckListID'=>$CKListId))->select()[0]["Cnt"];
        $Cnt_Ret  = db('CheckList')->where(array('id'=>$CKListId))->update(
                                    array('Status'=>$CkTask->CheckTaskStatus_Arr['CheckListIsDefined'],
                                          'CheckRowCnt'=>$CkRowCnt));
        if($Cnt_Ret<1){
            $Ret['Ret'] = 'Failed';
        }else{
            $Ret['Ret'] = 'Success';
        }


        OUT:
            return json($Ret);

    }

    public function Ajax_GetCheckListCompleteStatus(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $Ret['Ret'] = 'Failed';

        $CKListId = intval($PostData_Arr['CheckListId']);
        $Ret = db()->query("SELECT id,CheckSubject, case IsOk WHEN 'YES' THEN 'success'
                                     WHEN 'NO' THEN 'danger'  ELSE ' ' END Status
                                    FROM CheckListDetail WHERE CheckListID = ? ORDER BY SecondHalfTBID",array($CKListId));
        $Cnt_CPT = intval(db()->query('SELECT count(id) as cnt FROM CheckListDetail WHERE IsOk IS NOT NULL  AND CheckListID = ?',array($CKListId))[0]['cnt']);
        $Ret_Arr = array('CPT'=>$Cnt_CPT/count($Ret),'Detail'=>$Ret);
        return json($Ret_Arr);

    }

    public function UpdateCheckDetailCheckStand(){
        $Ret = db('CheckListDetail')->select();
        foreach ($Ret as $v) {
            $CheckRowID = $v['id'];
            db()->query('UPDATE CheckListDetail SET CheckStandSnap  = 
                    (SELECT CheckStandard FROM FirstHalfCheckTB WHERE id = ?) WHERE id = ?',array($v['FirstHalfTBID'],$v['id']));
        }
        echo "UpdateCheckDetailCheckStand-->OK!";
    }

    public function UpdateCheckDetailComplianceStandard(){
        $Ret = db('CheckListDetail')->select();
        foreach ($Ret as $v) {
            $CheckRowID = $v['id'];
            db()->query('UPDATE CheckListDetail SET ComplianceStandard  = 
                    (SELECT ComplianceStandard FROM SecondHalfCheckTB WHERE id = ?) WHERE id = ?',array($v['SecondHalfTBID'],$v['id']));
        }
        echo "OK!";
    }

    public function gyFillQuestionSubInfo(){
        $Ret =db()->query("SELECT * FROM CheckListDetail JOIN CheckList ON CheckListDetail.CheckListID = CheckList.id JOIN SecondHalfCheckTB  ON 
   CheckListDetail.SecondHalfTBID = SecondHalfCheckTB.id WHERE DealType ='立即整改'");
        if(!empty($Ret)){
            foreach ($Ret as $v){
               $QsID =  db('TaskList')->field('RelateID')->where(array('id'=>$v['RelatedTaskID']))->select()[0]['RelateID'];
               db('QuestionList')->where(array('id'=>$QsID))->update(array(
                   'QuestionSource'=>$v['CheckSource'],
                   'RelatedCorp'=>$v['DutyCorp'],
                   'Basis'=>$v['Basis'],
                   'Finder'=>$v['Checker'],
                   'DateFound'=>$v['StartTime'],
                   'Basis'=>$v['BasisTerm'].$v['ComplianceStandard']
               ));
               echo 'QsID:'.$QsID.'</br>';
            }
        }
    }

    public function show2019FDZCQsInfo(){
        $Ret = db()->query('SELECT  FirstHalfCheckTB.CheckSubject,
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
            WHERE CheckListDetail.RelatedTaskID AND CheckListDetail.CheckListID = 30 IS NOT NULL');
        $this->assign('InfoList',$Ret);
        return view('index');
    }

    public  function test(){
        return view('miniui');
    }

    public function  UpDateCheckListDetail($CheckListID = NULL){
        $CkRows = db('CheckListDetail')->where(['CheckListID'=>$CheckListID])->select();
        foreach ($CkRows as $v){
            $SecondHalfTBID = $v['SecondHalfTBID'];
            echo "SecondHalfTBID:".$SecondHalfTBID."   FirstHalfTBID：".$v['FirstHalfTBID'].'</br>';
            $Ret =db()->query("SELECT * FROM FirstHalfCheckTB JOIN SecondHalfCheckTB on SecondHalfCheckTB.CheckStandardID=FirstHalfCheckTB.StandardID 
                              WHERE SecondHalfCheckTB.ComplianceID = ? AND SecondHalfCheckTB.isValid = 'YES' AND FirstHalfCheckTB.isValid = 'YES' ",array($v['SecondHalfTBID']))[0];
            if(empty($Ret)){
                echo "未找到条款!";
                continue;
            }
            db()->query('UPDATE CheckListDetail SET ProfessionName=?,BusinessName=?,CheckSubject=?,
                                    Code1=?,Code2=?,CheckContent=?,CheckMethods=?,BasisName=?,BasisTerm=?,
                                     RelatedCorps=?,InnerManual=?,CheckFrequency=?,CheckStandSnap=?,ComplianceStandard=? WHERE id = ?',array(
                $Ret['ProfessionName'], $Ret['BusinessName'],$Ret['CheckSubject'],$Ret['Code1'],$Ret['Code2'],
                $Ret['CheckContent'],$Ret['CheckMethods'],$Ret['BasisName'],$Ret['BasisTerm'],$Ret['RelatedCorps'],
                $Ret['InnerManual'],$Ret['CheckFrequency'], $Ret['CheckStandard'], $Ret['ComplianceStandard'],$v['id']));

        }
    }



    function UpDateTaskSouce()
    {
        $TaskList = db('TaskList')->select();
        foreach ($TaskList as $T) {
            echo '<pre>';
            switch ($T['TaskType']) {
                case TaskCore::QUESTION_REFORM:
                case TaskCore::QUESTION_FAST_REFORM:
                    {//问题-整改
                        //先看关联的问题是否有Source
                        $RelateID = $T['RelateID'];
                        $Qs = db('QuestionList')->where(array('id' => $RelateID))->select()[0];
                        if (!empty($Qs)) {
                            $RFs = db()->query('SELECT * FROM ReformList WHERE id IN (SELECT ToID FROM IDCrossIndex WHERE FromID = ?)', array($RelateID));
                            if (empty($Qs['QuestionSource'])) {//从整改通知书中找到
                                $Q_Data = array();
                                if (!empty($RFs)) {
                                    $Q_Data['Finder'] = $RFs[0]['Inspectors'];
                                    $Q_Data['DateFound'] = $RFs[0]['CheckDate'];
                                    $Q_Data['Basis'] = $RFs[0]['Basis'];
                                    $Q_Data['QuestionSource'] = $RFs[0]['QuestionSourceName'];
                                    $Corps = '';
                                    foreach ($RFs as $F) {
                                        if (empty($Corps)) {
                                            $Corps = $F;
                                        } else {
                                            $Corps .= '|' . $F;
                                        }
                                    }
                                    $Q_Data['RelatedCorp'] = $Corps;
                                    db('QuestionList')->where(array('id' => $RelateID))->update($Q_Data);
                                    $T_Data = [];
                                    $T_Data['TaskSource'] = $Q_Data['QuestionSource'];
                                    db('TaskList')->where(array('id' => $T['id']))->update($T_Data);
                                } else {
                                    echo $T['TaskType'] . '-->TaskID' . $T['id'] . ' ' . $T['TaskName'] . ' ' . '找不到通知书 且问题中来源为空' . $RelateID;
                                }
                            } else {
                                if (!empty($RFs)) {
                                    $T_Data = [];
                                    $T_Data['TaskSource'] = $RFs[0]['QuestionSourceName'];
                                    db('TaskList')->where(array('id' => $T['id']))->update($T_Data);
                                } else {
                                    echo $T['TaskType'] . '-->TaskID' . $T['id'] . ' ' . $T['TaskName'] . ' ' . '找不到通知书 找不到问题' . $RelateID;
                                }

                            }
                        } else {
                            echo $T['TaskType'] . '-->TaskID' . $T['id'] . ' ' . $T['TaskName'] . ' ' . '关联问题不存在:' . $RelateID;
                        }
                        break;
                    }
                case TaskCore::REFORM_SUBTASK:
                    {
                        $RelateID = $T['RelateID'];
                        $RF = db('ReformList')->where(array('id' => $RelateID))->select()[0];
                        if (!empty($RF)) {
                            $T_Data = [];
                            $T_Data['TaskSource'] = $RF['QuestionSourceName'];
                            db('TaskList')->where(array('id' => $T['id']))->update($T_Data);
                            echo $T_Data['TaskSource'];
                        } else {
                            echo $T['TaskType'] . '-->TaskID' . $T['id'] . ' ' . $T['TaskName'] . ' ' . '找不到整改通知书:' . $RelateID;
                        }
                        break;
                    }
                case TaskCore::ONLINE_CheckTask:
                    {
                        $RelateID = $T['RelateID'];
                        $CK = db('CheckList')->where(array('id' => $RelateID))->find();
                        if (!empty($CK)) {
                            $T_Data = [];
                            $T_Data['TaskSource'] = $CK['CheckSource'];
                            db('TaskList')->where(array('id' => $T['id']))->update($T_Data);
                        } else {
                            echo $T['TaskType'] . '-->TaskID' . $T['id'] . ' ' . $T['TaskName'] . ' ' . '检查单不存在';
                        }
                        break;
                    }
                    echo '<br/><br/><br/>';
                //UPDATE `QuestionList` SET RelatedCorp = NULL,QuestionSource=NULL,Finder=NULL,DateFound=NULL,Basis=NULL
            }
        }
    }

        public  function showui(){
            return view('miniui');
        }


}
