<?php
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;

class lgyQuery extends PublicController{

    private  $CorpMng = NULL;
    private  $RF = NULL;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->CorpMng = new CorpMng();
        $this->RF = new Reform();
    }

    public function index (){
        $this->assign('QsSourceList',db()->query('SELECT SourceName,CodePre From QuestionSource ORDER BY SourceName'));
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('UserList',db()->query('SELECT Name,Corp FROM UserList ORDER BY Corp,Name'));
        return view('index');
    }

    public function QsQuery(){
        $SQL = "SELECT id FROM QuestionList  WHERE  1 = 1 ";

        $QsTitle = input('QsTitle');
        $QsSource = input('QsSource');
        $QsInfo = input('QsInfo');
        $Finder = input('Finder');
        $SDate = input('SDate');
        $EDate = input('EDate');
        $DutyCorp1 = input('DutyCorp1');
        $DutyCorp2 = input('DutyCorp2');
        $NodeList = json_decode(input('QsLabelCalc'),true);
        $Ids = $this->GetSubjectIDsByNodeListAndSubjectTypes($NodeList,'Qs');

        if(!empty($NodeList) && empty($Ids)){
            //有标签筛选条件,但是筛选结果为空
            goto OUT;
        }

        $Param_Arr = [];

        if(!empty($QsTitle)){
            $SQL .= ' AND QuestionTitle Like ?';
            $Param_Arr[] = '%'.$QsTitle.'%';
        }

        if(!empty($QsSource)){
            $SQL .= ' AND QuestionSource Like ?';
            $Param_Arr[] = '%'.$QsSource.'%';
        }

        if(!empty($QsInfo)){
            $SQL .= ' AND QuestionInfo Like ?';
            $Param_Arr[] = '%'.$QsInfo.'%';
        }

        if(!empty($Finder)){
            $SQL .= ' AND Finder Like ?';
            $Param_Arr[] = '%'.$Finder.'%';
        }


        if(!empty($SDate)){
            $SQL .= ' AND DateFound >= ?';
            $Param_Arr[] = $SDate;
        }

        if(!empty($EDate)){
            $SQL .= ' AND DateFound <= ?';
            $Param_Arr[] = $EDate;
        }

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $In_Sql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
                $SQL .= ' AND RelatedCorp IN '.$In_Sql;
            }
        }else{
            $SQL .= ' AND RelatedCorp = ?';
            $Param_Arr[] = $DutyCorp2;
        }

        $Qs_Ret = db()->query($SQL,$Param_Arr);
        if(empty($Qs_Ret)){
            $Qs_Ret = [];
        }
        $Qs_Ret = array_column($Qs_Ret,'id');
        if(!empty($NodeList)){
            $Qs_Ret = array_intersect($Ids,$Qs_Ret);
        }

        $Ret = db('QuestionList')->where(array('id'=>array('IN',$Qs_Ret)))->select();
        $this->assign('QsLabelCalc',json_decode(json_encode(input('QsLabelCalc'))));
        $this->assign('Qs_Ret',$Ret);

        OUT:
            return $this->index();
    }
    //NodeList--->[{NodeCode:'',NodeName:'',MatchType:0/1,CalcType:0/1,TreeCode:,TreeName:}]
    function splitNodeCode($NodeCode){
        $len = strlen($NodeCode);

        if( $len % 4 != 0 ){//长度不是4的整数倍，有问题。
           return "('')";
        }

        $Ret =  array();
        for($i=0;$i<$len/4;$i++){
            $Ret[] = substr($NodeCode,0,$len-$i*4);
        }

        return $Ret;
    }
    function GetSubjectIDsByNodeListAndSubjectTypes($NodeList,$SubjectTypes){
        if(empty($NodeList)){
            return '';
        }

        $ORNodeCodeList = array();
        $AndMHNodeCodes = array();
        $AndJQNodeCnt = 0;
        $AndJQNodes = array();

        foreach ($NodeList as $k=>$v){
            $NodeCode = $v['NodeCode'];
            //dump($NodeCode);
           if($v['CalcType']==1){//或
               if($v['MatchType']==1){
                   $ORNodeCodeList[] = $NodeCode;
               }else{
                   $ORNodeCodeList = array_merge($ORNodeCodeList,$this->splitNodeCode($NodeCode));
               }
           }else{//且
               if($v['MatchType']==1){//精确匹配
                   $AndJQNodes[] = $NodeCode;
                   $AndJQNodeCnt++;
               }else{//模糊匹配，几下所有NodeCode;
                   $AndMHNodeCodes[] = $this->splitNodeCode($NodeCode);
               }
           }
        }

        $ANDJQRet = db('LabelCrossIndex')->field('SubjectID')->where(
            array('SubjectType'=>array('IN',$SubjectTypes),
                  'NodeCode'=>array('IN',$AndJQNodes),
                  'IsValid'=>'YES'
            ))->group('SubjectID')->having('count(distinct NodeCode)='.$AndJQNodeCnt)->select();
        $ANDJQRet = array_column($ANDJQRet,'SubjectID');

        $ORRet = db('LabelCrossIndex')->field('SubjectID')->where(
            array('SubjectType'=>array('in',$SubjectTypes),
                'NodeCode'=>array('IN',$ORNodeCodeList),
                'IsValid'=>'YES'))->select();
        $ORRet = array_column($ORRet,'SubjectID');

        $ANDMHRet = NULL;
        $bStart = false;
        if(!empty($AndMHNodeCodes)){
            foreach ($AndMHNodeCodes as $k=>$v) {
                if(!$bStart){
                    $t_Ret = db('LabelCrossIndex')->field('SubjectID')->where(
                        array('SubjectType'=>array('IN',$SubjectTypes),
                            'NodeCode'=>array('IN',$v),
                            'IsValid'=>'YES'))->select();
                    $ANDMHRet = array_column($t_Ret,'SubjectID');
                    $bStart = true;
                   // dump(db('LabelCrossIndex')->getLastSql());
                }else{
                    if(empty($ANDMHRet)){//已经没有符合条件的SubjectID了
                        break;
                    }
                    $t_Ret = db('LabelCrossIndex')->field('SubjectID')->where(
                        array('SubjectType'=>array('IN',$SubjectTypes),
                            'NodeCode'=>array('IN',$v),
                            'SubjectID'=>array('IN',$ANDMHRet),
                            'IsValid'=>'YES'))->select();
                    $ANDMHRet = array_column($t_Ret,'SubjectID');
                   // dump(db('LabelCrossIndex')->getLastSql());
                }
            }
        }

        //dump($ANDMHRet);

        $AndRet  = array_intersect(empty($ANDMHRet)?$ANDJQRet:$ANDMHRet,empty($ANDJQRet)?$ANDMHRet:$ANDJQRet);
        $Ret = array_merge($AndRet,$ORRet);
       // dump("GetSubjectIDsByNodeListAndSubjectTypes".'Ret');
       // dump($Ret);
        return $Ret;

    }

    public function showRFQuery(){
        $this->assign('RFSourceList',db()->query('SELECT SourceName,CodePre From QuestionSource ORDER BY SourceName'));
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('UserList',$this->CorpMng->GetGroupCorpUserList($this->GetGroupCorp()));
        $this->assign('RFStatusList',array_keys($this->RF->ReformStatus_AssginArr));
        return view('RFQuery');
    }

    public function RFQuery(){

     // dump(input());

        $IN_Keys =array('RFSource'=>                    ['eq','QuestionSourceName'],
                        'DutyCorp'=>                    ['eq','DutyCorp'],
                        'RFTitle'=>                     ['like','ReformTitle'],
                        'RFStatus'=>                    ['eq','ReformStatus'],
                        'RFCode'=>                      ['like','Code'],
                        'Inspector'=>                   ['like','Inspectors'],
                        'NonConfirmDesc'=>              ['like','NonConfirmDesc'],
                        'Basis'=>                       ['like','Basis'],
                        'IssueCorp'=>                   ['eq','IssueCorp'],
                        'CurDealCorp'=>                 ['eq','CurDealCorp'],
                        'RFRequire'=>                   ['like','ReformRequirement'],
                        'CorrectAction'=>               ['like','CorrectiveAction'],
                        'PrecautionAction'=>            ['like','PrecautionAction'],
                        'ActionMaker'=>                 ['eq','ActionMakerName'],
                        'ActionEvalerName'=>                ['eq','ActionEvalerName'],
                        'ActionIsOK'=>                  ['eq_any','ActionIsOK'],
                        'CorrectActionProofIsOK'=>      ['eq_any','CorrectiveActionProofEvalIsOK'],
                        'CorrectActionEvaler'=>         ['eq','CorrectiveActionProofEvalerName'],
                        'PrecautionActionProofIsOK'=>   ['eq_any','PrecautionActionProofEvalIsOK'],
                        'PrecautionActionEvaler'=>      ['eq','PrecautionActionProofEvalerName'],
                        'CheckStartDate'=>              ['egt','CheckDate'],
                        'CheckEndDate'=>                ['elt','CheckDate'],
                        'FeedBackBeyond'=>              ['exp','ActionMakeTime',' >= RequestFeedBackDate '],
                        'CorrectBeyond'=>               ['exp','CorrectiveActionProofUploadTime',' >=CorrectiveDeadline '],
                        'PrecautionBeyond'=>            ['exp','PrecautionActionProofUploadTime',' >=PrecautionDeadline '],
                        'WhichRoleMakeAction'=>         input('WhichRoleMakeAction')=='ANY'?NULL:['eq','RequireDefineAction'],
                        'ActionIsMaked'=>               input('ActionIsMaked')=='ANY'? NULL: (input('ActionIsMaked')=='YES'? ['exp','CorrectiveAction',' IS NOT NULL']:['exp','CorrectiveAction',' IS NULL']),
                        'CorrectDeadLineStartDate'=>    ['egt','CorrectiveDeadline'],
                        'CorrectDeadLineEndDate'=>      ['elt','CorrectiveDeadline'],
                        'PrecautionDeadLineStartDate'=> ['egt','CorrectiveDeadline'],
                        'PrecautionDeadLineEndDate'=>   ['elt','CorrectiveDeadline'],
                        'ActionMakeStartDate'=>         ['egt','ActionMakeTime'],
                        'ActionMakeEndDate'=>           ['elt','ActionMakeTime'],
                        'ActionEvalStartDate'=>         ['glt','ActionEvalTime'],
                        'ActionEvalEndDate'=>           ['elt','ActionEvalTime'],
                        'RequireFeedBackStartDate'=>    ['egt','RequestFeedBackDate'],
                        'RequireFeedBackEndDate'=>      ['elt','RequestFeedBackDate'],
                        'CorrectActionProofIsUpload'=>              input('CorrectActionProofIsUpload')=='ANY'? NULL: (input('CorrectActionProofIsUpload')=='YES'? ['exp','CorrectiveActionProof',' IS NOT NULL']:['exp','CorrectiveActionProof',' IS NULL']),
                        'CorrectActionProofUploadStartDate'=>       ['egt','CorrectiveActionProofUploadTime'],
                        'CorrectActionProofUploadEndDate'=>         ['elt','CorrectiveActionProofUploadTime'],
                        'CorrectActionProofEvalStartDate'=>         ['egt','CorrectiveActionProofEvalTime'],
                        'CorrectActionProofEvalEndDate'=>           ['elt','CorrectiveActionProofEvalTime'],
                        'PrecautionActionProofIsUpload'=>           input('PrecautionActionProofIsUpload')=='ANY'? NULL: (input('PrecautionActionProofIsUpload')=='YES'? ['exp','PrecautionActionProof',' IS NOT NULL']:['exp','PrecautionActionProof',' IS NULL']),
                        'PrecautionActionUploadStartDate'=>         ['egt','PrecautionActionProofUploadTime'],
                        'PrecautionActionUploadEndDate'=>           ['elt','PrecautionActionProofUploadTime'],
                        'PrecautionActionProofEvalStartDate'=>      ['egt','PrecautionActionProofEvalTime'],
                        'PrecautionActionProofEvalEndDate'=>        ['elt','PrecautionActionProofEvalTime'],
        );

        $ActionEvalDateCP = trim(input('ActionEvalDateCP'));
        $ActionEvalDays   = trim(input('ActionEvalDays'));
        if(!empty($ActionEvalDateCP) && !empty($ActionEvalDays)){
            $IN_Keys['ActionEvalDateCP']= ['exp',''," TIMESTAMPDIFF(DAY,ActionMakeTime,ActionEvalTime)".($ActionEvalDateCP=='LT'?"<=":">=").intval($ActionEvalDays)];
        }

        $CorrectActionProofEvalCP = trim(input('CorrectActionProofEvalCP'));
        $CorrectActionProofEvalDays   = trim(input('CorrectActionProofEvalDays'));
        if(!empty($CorrectActionProofEvalCP) && !empty($CorrectActionProofEvalDays)){
            $IN_Keys['CorrectActionProofEvalCP']= ['exp',''," TIMESTAMPDIFF(DAY,CorrectiveActionProofUploadTime,CorrectiveActionProofEvalTime)".($CorrectActionProofEvalCP=='LT'?"<=":">=").intval($CorrectActionProofEvalDays)];
        }


        $PrecautionActionProofEvalCP = trim(input('PrecautionActionProofEvalCP'));
        $PrecautionActionProofEvalDays  = trim(input('PrecautionActionProofEvalDays'));
        if(!empty($PrecautionActionProofEvalCP) && !empty($PrecautionActionProofEvalDays)){
            $IN_Keys['PrecautionActionProofEvalCP']= ['exp',''," TIMESTAMPDIFF(DAY,PrecautionActionProofUploadTime,PrecautionActionProofEvalTime)".($PrecautionActionProofEvalCP=='LT'?"<=":">=").intval($PrecautionActionProofEvalDays)];
        }

        /*
         *        ->eq 相等
         *        ->like 相似
         *        ->eq_any 如果值为any则忽略,否则等同于eq
         *        ->egt  字段 >= 输入
         *        ->elt  字段 >= 输入
         *        raw_arr->数组作为查询输入
         *        exp->sql表达式
         * */
        $where['isDeleted'] = '否';
        foreach ($IN_Keys as $k =>$v){
            $T = trim(input($k));
            if(!empty($T)){
                    switch ($v[0]){
                        case 'eq':{
                            $where[$v[1]]=['eq',$T];
                            break;
                        }
                        case 'like':{
                            $where[$v[1]]=['like','%'.$T.'%'];
                            break;
                        }
                        case 'eq_any':{
                            if($T!='ANY'){
                                if($T=='UNDO'){//未审核
                                    $where[$v[1]]=['exp',Db::raw(' IS NULL OR  '.$v[1]." = '' ")];
                                }else{
                                    $where[$v[1]]=['eq',$T];
                                }

                            }
                            break;
                        }
                        case 'egt':{
                            if($T!='ANY'){
                                $where[$v[1]]=['egt',$T];
                            }
                            break;
                        }
                        case 'elt':{
                            if($T!='ANY'){
                                $where[$v[1]]=['elt',$T];
                            }
                            break;
                        }
                        case 'raw_arr':{
                            $where[$v[1]]=$v;
                            break;
                        }
                        case 'exp':{
                            $where[$v[1]]=['exp',Db::raw($v[2])];
                            break;
                        }
                }}
        }

        $DutyCorp1                  = input('DutyCorp1');
        $DutyCorp2                  = input('DutyCorp2');

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $Rows  = $this->CorpMng->GetCorpAllLevelChildrenRows($DutyCorp1);
                $Ret = db('ReformList')->where($where)->whereIn('DutyCorp',$Rows)->select();
            }
        }else{
            $Ret = db('ReformList')->where($where)->where(['DutyCorp'=>$DutyCorp2])->select();
        }

       // dump(db()->getLastSql());
       $this->assign('ReformList',$Ret);

        return $this->showRFQuery();
    }

    public function showFXQry(){
        $this->assign('QsSourceList',db()->query('SELECT SourceName,CodePre From QuestionSource ORDER BY SourceName'));
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('UserList',db()->query('SELECT Name,Corp FROM UserList ORDER BY Corp,Name'));
        return view('FXQry');
    }

    public function FXQry(){

        $SQL = "SELECT A.*,B.Corp,C.QuestionSource as FXSource FROM FXTab A 
            JOIN FX_Corp_Cross B ON A.FXCode = B.FXCode 
            LEFT JOIN QuestionList C ON A.RelatedID = C.id AND A.RelatedIDType = '问题' WHERE  1 = 1 ";

        $Param_Arr          = [];
        $QsSource           = input('QsSource');
        $FXCode             = input('FXCode');
        $JudgeType          = input('JudgeType');
        $WHMS               = input('WHMS');
        $FXMS               = input('FXMS');
        $DutyCorp1          = input('DutyCorp1');
        $DutyCorp2          = input('DutyCorp2');
        $CreaterName        = input('CreaterName');
        $Judger             = input('Judger');
        $SDate1             = input('SDate1');//识别日期
        $EDate1             = input('EDate1');
        $SDate2             = input('SDate2');//判定日期
        $EDate2             = input('EDate2');

        $inputArr = [
            ['F'=>'FXSource',             'V'=>$QsSource,         'FH'=>'=',        'I_V'=>$QsSource],
            ['F'=>'A.FXCode',             'V'=>$FXCode,           'FH'=>'=',        'I_V'=>$FXCode],
            ['F'=>'A.WHMS',               'V'=>'%'.$WHMS.'%',     'FH'=>'LIKE',     'I_V'=>$WHMS],
            ['F'=>'A.FXMS',               'V'=>'%'.$FXMS.'%',     'FH'=>'LIKE',     'I_V'=>$FXMS],
            ['F'=>'A.CreaterName',        'V'=>$CreaterName,      'FH'=>'=',        'I_V'=>$CreaterName],
            ['F'=>'A.JudgePerson',        'V'=>$Judger,           'FH'=>'=',        'I_V'=>$Judger],
            ['F'=>'A.SDate1',             'V'=>$SDate1,           'FH'=>'>=',       'I_V'=>$SDate1],
            ['F'=>'A.EDate1',             'V'=>$EDate1,           'FH'=>'<=',       'I_V'=>$EDate1],
            ['F'=>'A.SDate2',             'V'=>$SDate2,           'FH'=>'>=',       'I_V'=>$SDate2],
            ['F'=>'A.EDate2',             'V'=>$EDate2,           'FH'=>'<=',       'I_V'=>$EDate2],
        ];

        $R = $this->CreateSubQrySQL($SQL,$Param_Arr,$inputArr);
        $SQL = $R['MainSql'];
        $Param_Arr = $R['ParamArr'];

        if(!empty($JudgeType)){
            if($JudgeType=='危险源'
                || $JudgeType=='安全隐患'
                || $JudgeType=='安全信息'){
                $SQL .= ' AND FXJudgeType = ?';
                $Param_Arr[] = $JudgeType;
            }else if($JudgeType=='全部'){

            }else if($JudgeType=='尚未判定'){
                $SQL .= ' AND FXJudgeType = ?';
                $Param_Arr[] = '';
            }
        }

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $In_Sql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
                $SQL .= ' AND B.Corp IN '.$In_Sql;
            }
        }else{
            $SQL .= ' AND B.Corp = ?';
            $Param_Arr[] = $DutyCorp2;
        }

        $FXRows= db()->query($SQL,$Param_Arr);

        $this->assign('FXList',$FXRows);
        OUT:
            return $this->showFXQry();
    }



    public function showSMSQry(){
        $this->assign('QsSourceList',db()->query('SELECT SourceName,CodePre From QuestionSource ORDER BY SourceName'));
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('UserList',db()->query('SELECT Name,Corp FROM UserList ORDER BY Corp,Name'));
        return view('SMSQry');
    }




    public function SMSQry(){

        $SQL = "SELECT *,B.Color as Color1 ,C.Color as Color2,D.JudgePerson FROM SMSTab A 
              LEFT JOIN FXDJTB B ON A.FXDJ1 = B.FXDJDX 
              LEFT JOIN FXDJTB C ON A.FXDJ2 = C.FXDJDX
              LEFT JOIN FXTab  D ON A.FXCode = D.FXCode
              WHERE 1 =1 ";

        $Param_Arr                  = [];
        $QsSource                   = input('QsSource');
        $SMSCode                    = input('SMSCode');
        $FXMS                       = input('FXMS');
        $WHMS                       = input('WHMS');
        $ClaimCorp                  = input('ClaimCorp');
        $DutyCorp1                  = input('DutyCorp1');
        $DutyCorp2                  = input('DutyCorp2');
        $Judger                     = input('Judger');
        $SDate1                     = input('SDate1');//判定日期  -->SMSTab.AddTime
        $EDate1                     = input('EDate1');
        $FXCode                     = input('FXCode');
        $FXDJ1                      = input('FXDJ1');
        $FXDJ2                      = input('FXDJ2');
        $ZJYY                       = input('ZJYY');
        $GBYY                       = input('GBYY');
        $JZCS                       = input('JZCS');
        $YFCS                       = input('YFCS');
        $YFQX1                      = input('YFQX1');
        $YFQX2                      = input('YFQX2');
        $JZQX1                      = input('JZQX1');
        $JZQX2                      = input('JZQX2');
        $CSCreater                  = input('CSCreater');
        $Status                     = input('Status');

        $inputArr = [
            ['F'=>'A.SMSSource',          'V'=>$QsSource,         'FH'=>'=',        'I_V'=>$QsSource],
            ['F'=>'A.SMSCode',            'V'=>$SMSCode,          'FH'=>'=',        'I_V'=>$SMSCode],
            ['F'=>'A.FXMS',               'V'=>'%'.$FXMS.'%',     'FH'=>'LIKE',     'I_V'=>$FXMS],
            ['F'=>'A.WHMS',               'V'=>'%'.$WHMS.'%',     'FH'=>'LIKE',     'I_V'=>$WHMS],
            ['F'=>'A.ClaimCorp',          'V'=>$ClaimCorp,        'FH'=>'=',        'I_V'=>$ClaimCorp],
            ['F'=>'D.JudgePerson',        'V'=>$Judger,           'FH'=>'=',        'I_V'=>$Judger],
            ['F'=>'A.AddTime',            'V'=>$SDate1,           'FH'=>'>=',       'I_V'=>$SDate1],
            ['F'=>'A.AddTime',            'V'=>$EDate1.' 23:59:59','FH'=>'<=',      'I_V'=>$EDate1],
            ['F'=>'A.FXCode',             'V'=>$FXCode,           'FH'=>'=',        'I_V'=>$FXCode],
            ['F'=>'A.FXDJ1',              'V'=>$FXDJ1,            'FH'=>'=',        'I_V'=>$FXDJ1],
            ['F'=>'A.FXDJ2',              'V'=>$FXDJ2,            'FH'=>'=',        'I_V'=>$FXDJ2],
            ['F'=>'A.ZJYY',               'V'=>$ZJYY,             'FH'=>'LIKE',     'I_V'=>$ZJYY],
            ['F'=>'A.GBYY',               'V'=>$GBYY,             'FH'=>'LIKE',     'I_V'=>$GBYY],
            ['F'=>'A.JZCS',               'V'=>$JZCS,             'FH'=>'LIKE',     'I_V'=>$JZCS],
            ['F'=>'A.YFCS',               'V'=>$YFCS,             'FH'=>'LIKE',     'I_V'=>$YFCS],
            ['F'=>'A.YFQX',               'V'=>$YFQX1,            'FH'=>'>=',       'I_V'=>$YFQX1],
            ['F'=>'A.YFQX',               'V'=>$YFQX2.' 23:59:59','FH'=>'<=',       'I_V'=>$YFQX2],
            ['F'=>'A.JZQX',               'V'=>$JZQX1,            'FH'=>'>=',       'I_V'=>$JZQX1],
            ['F'=>'A.JZQX',               'V'=>$JZQX2.' 23:59:59','FH'=>'<=',       'I_V'=>$JZQX2],
            ['F'=>'A.CSCreater',          'V'=>$CSCreater,        'FH'=>'=',        'I_V'=>$CSCreater],
        ];

        $R = $this->CreateSubQrySQL($SQL,$Param_Arr,$inputArr);
        $SQL = $R['MainSql'];
        $ParamArr = $R['ParamArr'];

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $In_Sql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
                $SQL .= ' AND DutyCorp IN '.$In_Sql;
            }
        }else{
            $SQL .= ' AND DutyCorp = ?';
            $ParamArr[] = $DutyCorp2;
        }

        if(!empty($Status)){
            if($Status=='已关闭'){
                $SQL .= ' AND Status = ?';
                $ParamArr[] = '已关闭';
            }else if($Status=='未关闭'){
                $SQL .= ' AND Status  <> ?';
                $ParamArr[] = '已关闭';
            }else if($Status=='所有'){

            }
        }

        $SMSList = db()->query($SQL,$ParamArr);

        $this->assign('SMSList',$SMSList);

        OUT:
            return $this->showSMSQry();
    }




    public function showYHQry(){
        $this->assign('QsSourceList',db()->query('SELECT SourceName,CodePre From QuestionSource ORDER BY SourceName'));
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('UserList',db()->query('SELECT Name,Corp FROM UserList ORDER BY Corp,Name'));
        return view('YHQry');
    }


    public function YHQry(){

        $SQL = "SELECT *,B.JudgePerson FROM AQYHTB A 
              LEFT JOIN FXTab  B ON A.FXCode = B.FXCode
              WHERE 1 =1 ";

        $Param_Arr                  = [];
        $QsSource                   = input('QsSource');
        $YHCode                     = input('YHCode');
        $YHMS                       = input('FXMS');
        $YHWH                       = input('YHWH');
        $ClaimCorp                  = input('ClaimCorp');
        $DutyCorp1                  = input('DutyCorp1');
        $DutyCorp2                  = input('DutyCorp2');
        $Judger                     = input('Judger');
        $SDate1                     = input('SDate1');//判定日期  -->SMSTab.AddTime
        $EDate1                     = input('EDate1');
        $FXCode                     = input('FXCode');
        $ZJYY                       = input('ZJYY');
        $GBYY                       = input('GBYY');
        $JZCS                       = input('JZCS');
        $YFCS                       = input('YFCS');
        $YFQX1                      = input('YFQX1');
        $YFQX2                      = input('YFQX2');
        $JZQX1                      = input('JZQX1');
        $JZQX2                      = input('JZQX2');
        $CSCreater                  = input('CSCreater');
        $Status                     = input('Status');
        $YHDJ                       = input('YHDJ');

        $inputArr = [
            ['F'=>'A.SMSSource',          'V'=>$QsSource,         'FH'=>'=',        'I_V'=>$QsSource],
            ['F'=>'A.YHCode',             'V'=>$YHCode,          'FH'=>'=',         'I_V'=>$YHCode],
            ['F'=>'A.YHMS',               'V'=>'%'.$YHMS.'%',     'FH'=>'LIKE',     'I_V'=>$YHMS],
            ['F'=>'A.YHWH',               'V'=>'%'.$YHWH.'%',     'FH'=>'LIKE',     'I_V'=>$YHWH],
            ['F'=>'A.ClaimCorp',          'V'=>$ClaimCorp,        'FH'=>'=',        'I_V'=>$ClaimCorp],
            ['F'=>'B.JudgePerson',        'V'=>$Judger,           'FH'=>'=',        'I_V'=>$Judger],
            ['F'=>'A.AddTime',            'V'=>$SDate1,           'FH'=>'>=',       'I_V'=>$SDate1],
            ['F'=>'A.AddTime',            'V'=>$EDate1.' 23:59:59','FH'=>'<=',      'I_V'=>$EDate1],
            ['F'=>'A.FXCode',             'V'=>$FXCode,           'FH'=>'=',        'I_V'=>$FXCode],
            ['F'=>'A.ZJYY',               'V'=>$ZJYY,             'FH'=>'LIKE',     'I_V'=>$ZJYY],
            ['F'=>'A.GBYY',               'V'=>$GBYY,             'FH'=>'LIKE',     'I_V'=>$GBYY],
            ['F'=>'A.JZCS',               'V'=>$JZCS,             'FH'=>'LIKE',     'I_V'=>$JZCS],
            ['F'=>'A.YFCS',               'V'=>$YFCS,             'FH'=>'LIKE',     'I_V'=>$YFCS],
            ['F'=>'A.YFQX',               'V'=>$YFQX1,            'FH'=>'>=',       'I_V'=>$YFQX1],
            ['F'=>'A.YFQX',               'V'=>$YFQX2.' 23:59:59','FH'=>'<=',       'I_V'=>$YFQX2],
            ['F'=>'A.JZQX',               'V'=>$JZQX1,            'FH'=>'>=',       'I_V'=>$JZQX1],
            ['F'=>'A.JZQX',               'V'=>$JZQX2.' 23:59:59','FH'=>'<=',       'I_V'=>$JZQX2],
            ['F'=>'A.CSCreater',          'V'=>$CSCreater,        'FH'=>'=',        'I_V'=>$CSCreater],
        ];

        $R = $this->CreateSubQrySQL($SQL,$Param_Arr,$inputArr);
        $SQL = $R['MainSql'];
        $ParamArr = $R['ParamArr'];

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $In_Sql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
                $SQL .= ' AND DutyCorp IN '.$In_Sql;
            }
        }else{
            $SQL .= ' AND DutyCorp = ?';
            $ParamArr[] = $DutyCorp2;
        }


        if(!empty($Status)){
            if($Status=='已关闭'){
                $SQL .= ' AND Status = ?';
                $ParamArr[] = '已关闭';
            }else if($Status=='未关闭'){
                $SQL .= ' AND Status  <> ?';
                $ParamArr[] = '已关闭';
            }else if($Status=='所有'){

            }
        }

        if(!empty($YHDJ)){
            if($YHDJ=='所有'){

            }else {
                $SQL .= ' AND YHDJ = ?';
                $ParamArr[] = $YHDJ;
            }
        }

        $YHList = db()->query($SQL,$ParamArr);
       // dump(db()->getLastSql());
       // dump($Param_Arr);
        $this->assign('YHList',$YHList);

        OUT:
        return $this->showYHQry();
    }


}