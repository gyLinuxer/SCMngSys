<?php
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2021/6/9
 * Time: 22:01
 */

namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;

class FXMng extends PublicController
{
    private  $CM;
    private  $CorpMng;
    private $FXTypes = ['危险源','安全隐患','一般信息'];
    public function __construct(Request $request = null)
    {
        $this->CM = new CodeMachine();
        $this->CorpMng = new CorpMng();
        parent::__construct($request);
    }

    public function showMatrix(){
        return view('matrix');
    }

    public function AddFX(){
        $Warning = '';

        $QuestionID = input('QuestionID');
        $FXMS = input('FXMS');
        $WHMS = input('WHMS');
        $CorpSelList = json_decode(input('CorpSelList'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);


        if(empty($QuestionID)){
            $Warning.= '风险所关联的问题ID不能为空!';
            goto OUT;
        }

        if(empty($FXMS)){
            $Warning.= '风险描述不能为空!';
            goto OUT;
        }

        if(empty($WHMS)){
            $Warning.= '危害描述不能为空';
            goto OUT;
        }

        if(empty($CorpSelList)){
            $Warning.= '危害对应部门不能为空!';
            goto OUT;
        }

        $r = db('FXTab')->where([
            'FXMS'=>$FXMS,
            'RelatedID'=>$QuestionID,
            'RelatedIDType'=>'问题'
        ])->select();

        if(!empty($r)){
            $Warning.= '该风险描述已存在!';
            goto OUT;
        }

        $data['FXCode'] = $this->CM->GetAndIncCurCode('FX');
        $data['RelatedID'] = $QuestionID;
        $data['RelatedIDType'] = '问题';
        $data['FXMS'] = $FXMS;
        $data['WHMS'] = $WHMS;
        $data['CreaterName'] = session('Name');
        $data['CreateTime'] = date('Y-m-d h:i:s');

        $r = db('FXTab')->insert($data);
        if(!empty($r)){
            $FXCode = $data['FXCode'];
            foreach ($CorpSelList as $v) {
                $dd['Corp'] = $v;
                $dd['FXCode'] = $FXCode;
                db('FX_Corp_Cross')->insert($dd);
            }
            $Warning = 'OK';
        }else{
            $Warning = '添加风险失败!';
        }

        OUT:
            return $Warning;
    }

    public function GetFXListByQuestionID(){
        $QuestionID = input('QuestionID');
        if(empty($QuestionID)){
            return '';
        }

        $r = db('FXTab')->where([
            'RelatedID'=>$QuestionID,
            'RelatedIDType'=>'问题'
        ])->select();

        for($i=0;$i<count($r);$i++){
            $r[$i]['Corps'] = db('FX_Corp_Cross')->where(['FXCode'=>$r[$i]['FXCode']])->select();
        }

        return json_encode($r,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }


    public function GetUnJudgedFXListByQuestion(){
        $QuestionID = input('QuestionID');
        if(empty($QuestionID)){
            return '';
        }
        $r = db()->query("SELECT * FROM FXTab WHERE 
                RelatedID= ? AND RelatedIDType = '问题' AND (FXJudgeType IS NULL OR FXJudgeType = '') "
        ,[$QuestionID]);
        return json_encode($r,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function GetFXbyCode(){
        $FXCode = input('FXCode');
        $R = db('FXTab')->where(['FXCode'=>$FXCode])->select();
        $Corps = db('FX_Corp_Cross')->where(['FXCode'=>$FXCode])->select();
        if(!empty($R)){
            $R[0]['DutyCorps'] = $Corps;
        }
        return json_encode($R,JSON_UNESCAPED_UNICODE);
    }

    public function GetFXJudgeTab(){
        $r = db('FXJudgLogic')->order('id')->select();
        return json_encode($r,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public  function JudgeFXType(){
        $FXCode = input('FXCode');
        $Type = input('Type');
        $JudgeSteps = input('JudgeSteps');
        $JudgeArr = json_decode($JudgeSteps,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

        $Warning = '';
        if(empty($FXCode)
            || empty($Type)
            || empty($JudgeArr)
            || !in_array($Type,$this->FXTypes)
        ){
            $Warning = '输入要素不全:要求风险代码\判定类别\风险判定过程数组';
            goto OUT;
        }

        $FX = db('FXTab')->where([
            'FXCode'=>$FXCode
        ])->select();

        if(empty($FX)){
            $Warning = '风险不存在!';
            goto OUT;
        }
        $FX = $FX[0];

        $Question = db()->query('SELECT * FROM QuestionList WHERE id = ?',[
            $FX['RelatedID']
        ]);


        if(empty($Question)){
            $Warning = '风险所关联问题不存在!';
            goto OUT;
        }
        $Question = $Question[0];

        if($FX['FXJudgeType']!=''){
            $Warning = '风险类型已被判定为:'.$FX['FXJudgeType'];
            goto OUT;
        }

        $FX['FXJudgeType'] = $Type;
        $FX['JudgePerson'] = session('Name');
        $FX['JudgeTime']   = date('Y-m-d h:i:s');

        $rr = db('FXTab')->where([
            'FXCode'=>$FXCode
        ])->update($FX);

        if(empty($rr)){
            $Warning = '风险表更新失败!';
            goto OUT;
        }

        $Corps = db()->query('SELECT * FROM FX_Corp_Cross WHERE FXCode = ?',[
            $FXCode
        ]);
        //根据风险类型
        if($Type=='危险源'){
            $SMSCoreCode = $this->CM->GetAndIncCurCode('SMSCore');
            foreach ($Corps as $Corp){
                $SMSData = [];
                $SMSData['SMSCoreCode'] = $SMSCoreCode;
                $SMSData['FXCode'] = $FXCode;
                $SMSData['QuestionID'] = $Question['id'];
                $SMSData['SMSCode'] = $this->CM->GetAndIncCurCode('SMS');
                $SMSData['SMSSource'] = $Question['QuestionSource'];
                $SMSData['DutyCorp'] = $Corp['Corp'];
                $SMSData['FXMS'] = $FX['FXMS'];
                $SMSData['WHMS'] = $FX['WHMS'];
                $SMSData['ClaimCorp']   = session('Corp');
                $SMSData['Status'] = '风险评级中';
                $SMSData['AddTime'] = date('Y-m-d h:i:s');
                db('SMSTab')->insert($SMSData);
            }

        }else if($Type=='安全隐患'){
            $YHCoreCode = $this->CM->GetAndIncCurCode('YHCore');
            foreach ($Corps as $Corp){
                $AQYH = [];
                $AQYH['YHCoreCode'] = $YHCoreCode;
                $AQYH['FXCode'] = $FXCode;
                $AQYH['ClaimCorp'] = session('Corp');
                $AQYH['QuestionID'] = $Question['id'];
                $AQYH['YHCode'] = $this->CM->GetAndIncCurCode('AQYH');
                $AQYH['YHSource'] = $Question['QuestionSource'];
                $AQYH['DutyCorp'] = $Corp['Corp'];
                $AQYH['YHMS'] = $FX['FXMS'];
                $AQYH['YHWH'] = $FX['WHMS'];
                $AQYH['Status'] = '隐患评级中';
                $AQYH['AddTime'] = date('Y-m-d h:i:s');
                db('AQYHTB')->insert($AQYH);
            }
        }



        foreach ($JudgeArr as $v){
            $data = [];
            $data['FXCode'] = $FXCode;
            $data['JudgeId'] = $v['id'];
            $data['NodeTitle'] = $v['NodeTitle'];
            $data['Choice'] = $v['Choice'];
            $data['JudgeTime'] = $v['TIMESTAMP'];
            $data['JudgePerson'] = session('Name');
            db('FXJudgeLog')->insert($data);
        }

        $Warning = 'OK';

        OUT:
            return $Warning;
    }

    public function SaveFXMatrix(){
        $FXSetArr = json_decode(input('FXSetArr'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        $YZXList = json_decode(input('YZXList'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        $KNXList = json_decode(input('KNXList'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        $FXTDList = json_decode(input('FXTDList'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

        dump($FXSetArr);
        dump($YZXList);

        $Warning = '';

        if(count($FXSetArr)<3){
            $Warning = '风险等级级数必须大于3!';
            goto OUT;
        }

        if(count($YZXList) <3 || count($KNXList) <3 ){
            $Warning = '严重性及可能性级数必须大于3!';
            goto OUT;
        }

        if(count($FXTDList) < count($YZXList) * count($KNXList) ){
            $Warning = '风险矩阵单元格数量小于严重性及可能性数量之乘积!';
            goto OUT;
        }

        $TDCnt = count($YZXList) * count($KNXList);
        for($i=0;$i<$TDCnt;$i++){
            if(empty($FXTDList[$i]['DX'])){
                $Warning = '请为风险矩阵表格中所有单元格设定风险级别!';
                goto OUT;
            }
        }

        db()->query('DELETE FROM FXDJTB');
        db()->query('DELETE FROM FXYZXTB');
        db()->query('DELETE FROM FXKNXTB');
        db()->query('DELETE FROM FXMatrixTDTB');

        foreach ($FXSetArr as $v){
            $data = [];
            $data['FXDJDX'] = $v['DX'];
            $data['Color'] = $v['color']['background-color'];
            db('FXDJTB')->insert($data);
        }

        $index = 0 ;
        foreach ($YZXList as $v){
            $data = [];
            $data['mIndex'] = $index++;
            $data['YZXName'] = $v['Name'];
            $data['Min'] = $v['Min'];
            $data['Max'] = $v['Max'];
            db('FXYZXTB')->insert($data);
        }

        $index = 0 ;
        foreach ($KNXList as $v){
            $data = [];
            $data['mIndex'] = $index++;
            $data['KNXName'] = $v['Name'];
            $data['Min'] = $v['Min'];
            $data['Max'] = $v['Max'];
            db('FXKNXTB')->insert($data);
        }

        $Cnt_YZX = count($YZXList);
        $Cnt_KNX = count($KNXList);

        $TDCnt = count($YZXList) * count($KNXList);
        for($i=0;$i<$TDCnt;$i++){
            $data = [];
            $data['TDX'] = intval($i / $Cnt_YZX);
            $data['TDY'] = $i % $Cnt_YZX;
            $data['FXDJ'] = $FXTDList[$i]['FXDJ'];
            db('FXMatrixTDTB')->insert($data);
        }

        $Warning = 'OK';

        OUT:
            return $Warning;

    }


    public function GetFXMatrix(){
        $FXDJ = db('FXDJTB')->select();
        $FXKNXTB = db('FXKNXTB')->select();
        $FXYZXTB = db('FXYZXTB')->select();
        $FXMatrixTDTB= db('FXMatrixTDTB')->select();
        return json_encode(
            [
                'FXDJTB'=>$FXDJ,
                'FXKNXTB'=>$FXKNXTB,
                'FXYZXTB'=>$FXYZXTB,
                'FXMatrixTDTB'=>$FXMatrixTDTB
            ]
            ,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK
        );
    }


    public function showFXMatrix(){
        return view('index');
    }

    private function CalcSMSFS($SMSCode){//计算SMS分值，并返回结果

        $FXDJ_Arr = ['I','II','III','IV','V','VI'];

        $Rows = db()->query('SELECT * FROM SMSEval WHERE SMSCode = ? ',[
            $SMSCode
        ]);

        $CNT_All = count($Rows);
        $KNX1_Avg = 0;
        $YZX1_Avg = 0;
        $KNX2_Avg = 0;
        $YZX2_Avg = 0;
        $CNT1 = 0;
        $CNT2 = 0;
        foreach ($Rows as $r){
            if(!empty($r['EvalTime1'])){
                $KNX1_Avg += floatval($r['KNX1']);
                $YZX1_Avg += floatval($r['YZX1']);
                $CNT1++;
            }
            if(!empty($r['EvalTime2'])){
                $KNX2_Avg += floatval($r['KNX2']);
                $YZX2_Avg += floatval($r['YZX2']);
                $CNT2++;
            }
        }

        $KNX1_Avg /= ($CNT1==0?1:$CNT1);
        $YZX1_Avg /= ($CNT1==0?1:$CNT1);
        $KNX2_Avg /= ($CNT2==0?1:$CNT2);
        $YZX2_Avg /= ($CNT2==0?1:$CNT2);

        $X1 = -1;$Y1 = -1;
        $X2 = -1;$Y2 = -1;

        $R = db()->query('SELECT mIndex FROM FXKNXTB WHERE Min <? AND Max >= ?',[$KNX1_Avg,$KNX1_Avg]);
        if(!empty($R)){
            $X1 = floatval($R[0]['mIndex']);
        }

        $R = db()->query('SELECT mIndex FROM FXKNXTB WHERE Min <? AND Max >= ?',[$KNX2_Avg,$KNX2_Avg]);
        if(!empty($R)){
            $X2 = floatval($R[0]['mIndex']);
        }

        $R = db()->query('SELECT mIndex FROM FXYZXTB WHERE Min <? AND Max >= ?',[$YZX1_Avg,$YZX1_Avg]);
        if(!empty($R)){
            $Y1 = floatval($R[0]['mIndex']);
        }

        $R = db()->query('SELECT mIndex FROM FXYZXTB WHERE Min <? AND Max >= ?',[$YZX2_Avg,$YZX2_Avg]);
        if(!empty($R)){
            $Y2 = floatval($R[0]['mIndex']);
        }

        $FXDJ1 = '';
        $FXDJ2 = '';

        $RR = db()->query('SELECT FXDJ FROM FXMatrixTDTB WHERE TDX=? AND TDY=?',[$X1,$Y1]);
        if(!empty($RR)){
            $FXDJ1 = floatval($RR[0]['FXDJ']);
        }

        $RR = db()->query('SELECT FXDJ FROM FXMatrixTDTB WHERE TDX=? AND TDY=?',[$X2,$Y2]);
        if(!empty($RR)){
            $FXDJ2 = floatval($RR[0]['FXDJ']);
        }

        $FXDJ_Color1 = '';
        $FXDJ_Color2 = '';

        $RR = db('FXDJTB')->where([
            'FXDJDX'=>$FXDJ_Arr[$FXDJ1]
        ])->select();

        if(!empty($RR)){
            $FXDJ_Color1 = 'background-color:'.$RR[0]['Color'];
        }

        $RR = db('FXDJTB')->where([
            'FXDJDX'=>$FXDJ_Arr[$FXDJ2]
        ])->select();

        if(!empty($RR)){
            $FXDJ_Color2 = 'background-color:'.$RR[0]['Color'];
        }

        return
        [
            'KNX1_Avg' => $KNX1_Avg,
            'YZX1_Avg' => $YZX1_Avg,
            'FXDJ1'    => $FXDJ_Arr[$FXDJ1],
            'FXDJ1_Color' => $FXDJ_Color1,
            'CNT_ALL'  => $CNT_All,
            'CNT1'     => $CNT1,
            'KNX2_Avg' => $KNX2_Avg,
            'YZX2_Avg' => $YZX2_Avg,
            'FXDJ2'    => $FXDJ_Arr[$FXDJ2],
            'FXDJ2_Color' => $FXDJ_Color2,
            'CNT2'     => $CNT2,
        ];

    }

    private function RichSMSList($SMSList){

        for($i=0;$i<count($SMSList);$i++){
            $EvalRow =  db()->query('SELECT * FROM SMSEval WHERE SMSCode = ? AND Evaler = ?',[
                $SMSList[$i]['SMSCode'],
                session('Name')
            ]);

            if(!empty($EvalRow)){
                $SMSList[$i]['isEvaler'] = 1;
                $SMSList[$i]['MyEvalRet'] = $EvalRow[0];
            }else{
                $SMSList[$i]['isEvaler'] = 0;
                $SMSList[$i]['MyEvalRet'] = [
                    'KNX1'=>'','KNX2'=>'','YZX1'=>'','YZX2'=>''
                ];
            }

            $SMSList[$i]['FSAvg'] = $this->CalcSMSFS($SMSList[$i]['SMSCode']);

            $SMSList[$i]['CorpType'] = '00';// 左位 执法部门  右位 责任单位

            if($SMSList[$i]['ClaimCorp']
                ==session('Corp')){
                $SMSList[$i]['CorpType'][0] ='1';//我是执法部门
            }

            if($SMSList[$i]['DutyCorp']
                ==session('Corp')){
                $SMSList[$i]['CorpType'][1] ='1';//我是责任单位
            }
        }
        return $SMSList;
    }

    public function GetSMSListByQuestionID(){
        $QuestionID = input('QuestionID');
        $InCaseStr = '';
        $Sql = "SELECT *, (case ClaimCorp WHEN '".session('Corp')."' THEN 1 ELSE 0 END) as CanSet  FROM SMSTab WHERE QuestionID = ? ";
        $pArr = [$QuestionID];
        if($this->IsSuperCorp()){
            $InCaseStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr(session('CorpInfo')['SuperCorpArea']);
            $Sql= 'SELECT *,1 as  CanSet FROM SMSTab WHERE QuestionID = ? AND DutyCorp IN '.$InCaseStr;
        }
        $SMSList = db()->query($Sql,$pArr);
        $SMSList = $this->RichSMSList($SMSList);
        return json_encode($SMSList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function GetYHListByQuestionID(){
        $QuestionID = input('QuestionID');
        $InCaseStr = '';
        $Sql = "SELECT *, (case ClaimCorp WHEN '".session('Corp')."' THEN 1 ELSE 0 END) as CanSet  FROM AQYHTB WHERE QuestionID = ? ";
        $pArr = [$QuestionID];
        if($this->IsSuperCorp()){
            $InCaseStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr(session('CorpInfo')['SuperCorpArea']);
            $Sql= 'SELECT *,1 as  CanSet FROM AQYHTB WHERE QuestionID = ? AND DutyCorp IN '.$InCaseStr;
        }
        $YHList = db()->query($Sql,$pArr);
        return json_encode($YHList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function AddSMSEvaler(){
        $SMSCode = input('SMSCode');
        $Evaler = input('Evaler');

        if(empty($SMSCode) || empty($Evaler)){
            return  'SMS代码或者评分人不能为空!';
        }

        $SMS = db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->select();
        if(empty($SMS)){
            return 'SMS危险源不存在!';
        }

        $SMS = $SMS[0];

        $row = db('SMSEval')->where([
            'SMSCode' =>$SMSCode,
            'Evaler' =>$Evaler
        ])->select();

        if(!empty($row)){
            return  '当前评分人已存在!';
        }

        $data = [];
        $data['SMSCode'] = $SMSCode;
        $data['SMSCoreCode'] = $SMSCode;
        $data['Evaler'] = $Evaler;
        $data['Status'] = '未结束';
        db('SMSEval')->insert($data);

        return 'OK';
    }

    public function GetSMSEvalerList(){
        $SMSCode = input('SMSCode');
        $rows = db('SMSEval')->where([
            'SMSCode' => $SMSCode
        ])->select();
        return json_encode($rows,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function DelSMSEvaler(){
        $SMSCode = input('SMSCode');
        $Evaler = input('Evaler');

        $row = db()->query('SELECT * FROM SMSEval WHERE SMSCode = ? AND Evaler = ? AND (EvalTime1 is NOT  NULL OR EvalTime2 IS NOT NULL) ',[$SMSCode,$Evaler]);

        if(!empty($row)){
            return '该用户已有评分记录，不允许删除!';
        }

        db()->query('DELETE FROM SMSEval WHERE Evaler = ? AND SMSCode = ?',[
            $Evaler , $SMSCode
        ]);

        return 'OK';
    }

    public function GetDFFSBySMSCode(){
        $SMSCode = input('SMSCode');
        $Name = session('Name');

        $Ret =[
            'Status'=>'OK',
            'YZX'=>'0',
            'KNX'=>'0',
            'DFType'=>'风险定级打分',
            'KNXMin'=>'',
            'KNXMax'=>'',
            'YZXMin'=>'',
            'YZXMax'=>''
        ];

        $Warning  = '';
        if(empty($SMSCode)){
            $Ret['Status'] = 'SMS编号不能为空';
            goto OUT;
        }

        $row = db('SMSTab')->where([
              'SMSCode' => $SMSCode
        ])->select();

        if(empty($row)){
            $Ret['Status'] = 'SMS不存在!';
            goto OUT;
        }

        $FXJZFW =  $this->GetFXMatrixBJ();
        if($FXJZFW['Status']!='OK'){
            $Warning = $FXJZFW['Status'];
            goto OUT;
        }

        $Ret['KNXMin'] = $FXJZFW['KNX']['Min'];
        $Ret['KNXMax'] = $FXJZFW['KNX']['Max'];
        $Ret['YZXMin'] = $FXJZFW['YZX']['Min'];
        $Ret['YZXMax'] = $FXJZFW['YZX']['Max'];



        $DFRow = db('SMSEval')->where([
            'Evaler'=>$Name,
            'SMSCode'=>$SMSCode
        ])->select();

        if(empty($DFRow)){
            $Ret['Status'] = '您不是打分人!';
        }

        if($row[0]['Status'] =='风险评级中'){
            $Ret['YZX'] = $DFRow[0]['YZX1'];
            $Ret['KNX'] = $DFRow[0]['KNX1'];
            $Ret['DFType'] = '风险定级打分';
        }else if($row[0]['Status'] =='措施评级中'){
            $Ret['YZX1'] = $DFRow[0]['YZX2'];
            $Ret['KNX1'] = $DFRow[0]['KNX2'];
            $Ret['DFType'] = '措施定级打分';
        }

        OUT:
            return json_encode($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    private function GetFXMatrixBJ(){//获取风险矩阵的可能性及严重性的分数范围
        $YZX_row = db()->query('SELECT min(Min) as Low,Max(Max) High FROM FXYZXTB');
        $KNX_row = db()->query('SELECT min(Min) as Low,Max(Max) High FROM FXKNXTB');
        $R = [
          'Status'=>'OK',
          'YZX'=>[
              'Min'=>0,
              'Max'=>0,
          ],
            'KNX'=>[
                'Min'=>0,
                'Max'=>0,
            ],
        ];

        if(empty($YZX_row)
            || empty($KNX_row)){
            $R['Status'] = '风险矩阵不存在或者不规范';
            goto OUT;
        }

        $R['YZX']['Min'] = $YZX_row[0]['Low'];
        $R['YZX']['Max'] = $YZX_row[0]['High'];
        $R['KNX']['Min'] = $KNX_row[0]['Low'];
        $R['KNX']['Max'] = $KNX_row[0]['High'];

        OUT:
            return $R;
    }

    public function SMSDF(){//SMS打分，自动根据SMS状态来打分
        $SMSCode = input('SMSCode');
        $Name = session('Name');
        $YZX = floatval(input('YZX'));
        $KNX = floatval(input('KNX'));

        $Row = db('SMSEval')->where([
            'SMSCode'=>$SMSCode,
            'Evaler'=>$Name
        ])->select();

        $Warning = 'OK';
        if(empty($Row)){
            $Warning ='SMS不存在或者您不是打分人!';
            goto OUT;
        }

        $FXJZFW =  $this->GetFXMatrixBJ();
        if($FXJZFW['Status']!='OK'){
            $Warning = $FXJZFW['Status'];
            goto OUT;
        }

        $isOK = ($YZX > floatval($FXJZFW['YZX']['Min'])
                    && $YZX <= floatval($FXJZFW['YZX']['Max'])
                    && $KNX > floatval($FXJZFW['KNX']['Min'])
                    && $KNX <= floatval($FXJZFW['KNX']['Max'])
                );

        if(!$isOK){
            $Warning = '您所打的分数不在有效范围内!';
            goto OUT;
        }

        $row = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();

        $data =[];
        if($row[0]['Status'] =='风险评级中'){
            $data['KNX1'] = $KNX;
            $data['YZX1'] = $YZX;
            $data['EvalTime1'] = date('Y-m-d h:i:s');
        }else if($row[0]['Status'] =='措施评级中'){
            $data['KNX2'] = $KNX;
            $data['YZX2'] = $YZX;
            $data['EvalTime2'] = date('Y-m-d h:i:s');
        }else{
            $Warning = '当前SMS已结束打分';
            goto OUT;
        }

        db('SMSEval')->where([
            'SMSCode'=>$SMSCode,
            'Evaler'=>session('Name')
        ])->update($data);


        OUT:
            return $Warning;
    }

    public function CSMakerQR(){
        $SMSCode = input('SMSCode');
        $WhoMakeCS = input('WhoMakeCS');


        $Warning = 'OK';
        if(empty($SMSCode)
            || empty($WhoMakeCS)
        ){
            $Warning = 'SMS代码或者措施制定人不能为空!';
            goto OUT;
        }

        $row = db()->query('SELECT * FROM SMSTab WHERE SMSCode = ?',[$SMSCode]);

        if(empty($row)){
            $Warning = 'SMS不存在!';
            goto OUT;
        }

        if(!empty($row[0]['YFCS'])){
            $Warning = '措施已制定不允许修改措施制定人规则！';
            goto OUT;
        }


        if($WhoMakeCS!='责任单位' && $WhoMakeCS!='执法部门'){
            $Warning = '措施制定人只能是执法部门或者责任单位!';
            goto OUT;
        }

        $data['WhoMakeCS'] = $WhoMakeCS;
        db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->update($data);



        OUT:
            return $Warning;
    }

    public function GetSMSRow(){
        $SMSCode = input('SMSCode');
        $row = db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->select();

        $Warning = '';
        if(empty($row)){
            return '';
        }

        return json_encode($row[0],JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function SMS_isMECSMaker($SMSCode){
        $row = db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->select();

        $Ret = [
            'Status'=>false,
            'Msg'=>''
        ];

        if(empty($row)){
            $Ret['Status'] = false;
            $Ret['Msg'] = 'SMS行不存在!';
            goto OUT;
        }

        if(empty($row[0]['WhoMakerCS'])){
            $Ret['Status'] = false;
            $Ret['Msg'] = '措施制定人尚未指定!';
            goto OUT;
        }

        if($row[0]['WhoMakerCS']=='责任单位'
            && $row[0]['DutyCorp']==session('Corp')){
            return true;
        }

        if($row[0]['WhoMakerCS']=='执法单位'
            && $row[0]['ClaimCorp']==session('Corp')){
            return true;
        }

        OUT:
            return $Ret;

    }

    public function SMSYYCSMake(){  //SMS的原因和措施制定
        $SMSCode = input('SMSCode');
        $ZJYY = input('ZJYY');
        $GBYY = input('GBYY');
        $JZCS = input('JZCS');
        $YFCS = input('YFCS');
        $JZQX = input('JZQX');
        $YFQX = input('YFQX');


        $Warning = 'OK';
        if(empty($ZJYY)
            || empty($GBYY)
            || empty($JZCS)
            || empty($YFCS)){
            $Warning = '直接原因、根本原因、纠正措施、预防措施不能为空!';
            goto OUT;
        }

        if(empty($SMSCode)){
            $Warning = 'SMS代码不能为空!';
            goto OUT;
        }

        $SMSRow = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();
        if(empty($SMSRow)){
            $Warning = 'SMS不存在!';
            goto OUT;
        }

        if(empty($JZQX)
            ||empty($YFQX)){
            $Warning = '纠正措施和预防措施的期限不能为空!';
            goto OUT;
        }

        if($SMSRow[0]['Status']!='措施制定中'){
            $Warning = '当前SMS状态并非措施制定环节!';
            goto OUT;
        }


        $CR = !$this->SMS_isMECSMaker($SMSCode);
        if($CR['Status']){
            $Warning = $CR['Msg'];
            goto OUT;
        }

        $data['ZJYY'] = $ZJYY;
        $data['GBYY'] = $GBYY;
        $data['JZCS'] = $JZCS;
        $data['YFCS'] = $YFCS;
        $data['JZQX'] = $JZQX;
        $data['YFQX'] = $YFQX;
        $data['CSCreater'] = session('Name');
        $data['CSCreateTime'] = date('Y-m-d h:i:s');
        $data['Status'] = '措施评级中';

        $r = db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->update($data);

        if(empty($r)){
            $Warning = '措施更新失败!';
            goto OUT;
        }

        OUT:
            return $Warning;

    }


    public function FXDJQR(){
        $SMSCode = input('SMSCode');
        $row  = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();
        $Warning = 'OK';

        if(empty($row)){
            $Warning = 'SMS不存在！';
            goto OUT;
        }

        if($row[0]['Status']!='风险评级中'){
            $Warning = '风险评级已结束!';
            goto OUT;
        }

        $FSRet = $this->CalcSMSFS($SMSCode);

        if($FSRet['FXDJ1']==null){
            $Warning = '没有任何人打分，不允许结束风险等级评定!';
            goto OUT;
        }

        $data['KNXAvg1'] = $FSRet['KNX1_Avg'];
        $data['YZXAvg1'] = $FSRet['YZX1_Avg'];
        $data['FXDJ1']   = $FSRet['FXDJ1'];
        $data['Status']   = '措施制定中';

        db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->update($data);


        OUT:
            return $Warning;
    }

    public function SMSCSCXZD(){//SMS措施重新制定
        $SMSCode = input('SMSCode');
        $row  = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();
        $Warning = 'OK';

        if(empty($row)){
            $Warning = 'SMS不存在！';
            goto OUT;
        }

        if($row[0]['Status']!='措施评级中'){
            $Warning = '已不允许再重新制定措施!';
            goto OUT;
        }

        $data['Status'] = '措施制定中';
        db('SMSTab')->where(['SMSCode'=>$SMSCode])->update($data);

        OUT:
            return $Warning;
    }

    public function SMSWCCSPJ(){//SMS 完成措施评价，并下发整改通知书
        $SMSCode = input('SMSCode');
        $row  = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();
        $Warning = 'OK';

        if(empty($row)){
            $Warning = 'SMS不存在！';
            goto OUT;
        }

        if($row[0]['Status']!='措施评级中'){
            $Warning = '措施评级已经结束!';
            goto OUT;
        }

        $FSRet = $this->CalcSMSFS($SMSCode);

        if($FSRet['FXDJ2']==null){
            $Warning = '没有任何人打分，不允许结束风险等级评定!';
            goto OUT;
        }

        $ReformRet = $this->CreateReformFromSMS($SMSCode);

        if($ReformRet['Status']!='OK'){
            $Warning = $ReformRet['Status'];
            goto OUT;
        }

        $data['KNXAvg2'] = $FSRet['KNX2_Avg'];
        $data['YZXAvg2'] = $FSRet['YZX2_Avg'];
        $data['FXDJ2']   = $FSRet['FXDJ2'];
        $data['RelatedReformCode']  = $ReformRet['ReformCode'];
        $data['Status']   = '整改中';

        db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->update($data);

        OUT:
            return $Warning;
    }

    public function CreateReformFromSMS($SMSCode){

        $Warning = 'OK';

        $SMSRow = db('SMSTab')->where([
            'SMSCode'=>$SMSCode
        ])->select();

        if(empty($SMSRow)){
             $Warning = 'SMS不存在';
             goto OUT;
        }

        $Reform = [];

        $SMSRow = $SMSRow[0];

        if(!empty($SMSRow['RelatedReformID'])){
            $Warning = '该SMS已下发整改通知书!';
            goto OUT;
        }

        $QuestionID = $SMSRow['QuestionID'];
        $Question = db('QuestionList')->where([
            'id'=>$QuestionID
        ])->select();
        $Question = $Question[0];

        $QuestionSource = $Question['QuestionSource'];
        $CodePreRow = db('QuestionSource')->where([
            'SourceName'=>$QuestionSource
        ])->select();

        $ReformCode = '';
        if(!empty($CodePreRow)){
            $ReformCode = $CodePreRow[0]['CodePre'].'-'.date('Ymdhis');
        }else{
            $ReformCode = $this->CM->GetAndIncCurCode('ZGTZS');
        }

        $Reform['RelatedQuestionID'] = $QuestionID;
        $Reform['Code'] = $ReformCode;
        $Reform['QuestionSourceName'] = $QuestionSource;
        $Reform['CheckDate'] = $Question['DateFound'];
        $Reform['Inspectors'] = $Question['Finder'];
        $Reform['IssueDate'] = date('Y-m-d');
        $Reform['RequestFeedBackDate'] = date('Y-m-d');
        $Reform['QuestionTitle'] = $Question['QuestionTitle'];
        $Reform['ReformTitle'] = $SMSRow['FXMS'];
        $Reform['NonConfirmDesc'] =
            '问题描述:'.'<br/>'.$Question['QuestionInfo'].'<br/>'.'危害描述:'.'<br/>'.$SMSRow['WHMS'].'<br/>'.'风险描述:'.$SMSRow['FXMS'];

        $Reform['Basis'] = '';
        $Reform['IssueCorp'] = $SMSRow['ClaimCorp'];
        $Reform['DutyCorp'] = $SMSRow['DutyCorp'];
        $Reform['CurDealCorp'] = $SMSRow['DutyCorp'];
        $Reform['ReformRequirement'] = '';
        $Reform['RequireDefineCause'] = $SMSRow['WhoMakeCS']=='责任单位'?'NO':'YES';
        $Reform['RequireDefineAction'] = $SMSRow['WhoMakeCS']=='责任单位'?'NO':'YES';
        $Reform['DirectCause'] = $SMSRow['ZJYY'];
        $Reform['RootCause'] = $SMSRow['GBYY'];
        $Reform['CauseEvalerName'] = 'SMS评估小组';
        $Reform['CauseEvalTime'] = date('Y-m-d h:i:s');
        $Reform['CorrectiveAction'] = $SMSRow['JZCS'];
        $Reform['CorrectiveDeadline'] = $SMSRow['JZQX'];

        $Reform['PrecautionAction'] = $SMSRow['YFCS'];
        $Reform['PrecautionDeadline'] = $SMSRow['YFQX'];

        $Reform['ActionMakerName'] = $SMSRow['CSCreater'];
        $Reform['ActionMakeTime'] = $SMSRow['CSCreateTime'];
        $Reform['ActionIsOK'] = 'YES';
        $Reform['ActionEval'] = 'SMS评估小组评估通过,评级为:'.$SMSRow['FXDJ2'].'级';
        $Reform['ActionEvalerName'] = 'SMS评估小组';
        $Reform['ActionEvalTime'] = date('Y-m-d h:i:s');
        $Reform['ReformStatus'] = '措施审核通过执行中';
        $Reform['ReformType'] = 'SMS整改';
        $Reform['RelateID'] = $SMSRow['id'];
        $Reform['RelateCode'] = $SMSRow['SMSCode'];

        $RId =  db('ReformList')->insertGetId($Reform);

        if(empty($RId)){
            $Warning = '整改通知书下发失败!';
            goto OUT;
        }


        $TaskData = array();
        $TaskData["TaskType"] = '整改通知书';
        $TaskData["TaskInnerStatus"] = '措施审核通过执行中';
        $TaskData['TaskName'] = $Reform["ReformTitle"];
        $TaskData['DeadLine'] = $Reform['RequestFeedBackDate'];
        $TaskData['SenderName'] = session("Name");
        $TaskData['TaskSource'] = $Reform["QuestionSourceName"];
        $TaskData['SenderCorp'] = $Reform["IssueCorp"];
        $TaskData['ReciveCorp'] = $Reform['DutyCorp'];
        $TaskData['RelateID'] = $RId;
        $TaskData['CreateTime'] = date("Y-m-d H:i:s");
        $TaskData['CreatorName'] = session("Name");
        $TaskData['ParentID'] = $Question['TaskID'];
        $TaskData['Status'] = '待接收';
        $Ret = TaskCore::CreateTask($TaskData);
        if(!empty($Ret['Ret'])){
            $Warning = "任务创建失败->".$Ret['Ret'];
            goto OUT;
        }else{
            db()->query("UPDATE ReformList SET ChildTaskID = ?,ParentTaskID = ?,CurDealCorp = DutyCorp WHERE id = ?",array($Ret['ID'],$Question['TaskID'],$RId));
        }

        $Cross_Data["Type"] = TaskCore::SMS_REFORM;
        $Cross_Data["FromID"] = $Question['id'];
        $Cross_Data["ToID"] = $RId;
        db('IDCrossIndex')->insert($Cross_Data);

        OUT:
            $ReformRet = [
                'Status'=>$Warning,
                'ReformCode'=>$ReformCode
            ];

        return $ReformRet;

    }

    public function SetSMSStatusToOK($SMSId){
        db()->query("UPDATE SMSTab SET Status = '已关闭' WHERE id = ?",[$SMSId]);
    }

    public function GetYHByYHCode(){
        $YHCode = input('YHCode');
        $row = db('AQYHTB')->where(['YHCode'=>$YHCode])->select();
        if(empty($row)){
            $row = [];
        }else{
            $row = $row[0];
        }

        return json_encode($row,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function YHPD(){//隐患评定，一般隐患或者重大隐患，但是尚未指定措施制定人时
        $YHCode = input('YHCode');
        $YHDJ = input('YHDJ');
        $Warning = 'OK';

        if(!in_array($YHDJ,[
            '一般隐患','重大隐患'
        ])){
            $Warning = '隐患等级设置错误!';
            goto OUT;
        }

        $YHRow = db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->select();

        if(empty($YHRow)){
            $Warning = '隐患不存在!';
            goto OUT;
        }

        $YHRow = $YHRow[0];
        if($YHRow['Status']!='隐患评级中'){
            $Warning = '隐患评级已结束';
            goto OUT;
        }

        $data['YHDJ'] = $YHDJ;
        $data['YHDJClaimer'] = session('Name');
        $data['YHDJClaimTime'] = date('Y-m-d h:i:s');

        if(!empty($data['WhoMakeCS'])){
            $data['Status'] = '措施制定中';
        }

        db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->update($data);


        OUT:
            return $Warning;
    }


    public function YHCSMakerQR(){//隐患措施制定人指定
        $YHCode = input('YHCode');
        $CSMaker = input('CSMaker');
        $Warning = 'OK';

        if(!in_array($CSMaker,[
            '责任单位','执法部门'
        ])){
            $Warning = '措施制定单位必须为责任单位或者执法部门!';
            goto OUT;
        }

        $YHRow = db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->select();

        if(empty($YHRow)){
            $Warning = '隐患不存在!';
            goto OUT;
        }

        $YHRow = $YHRow[0];
        if(!empty($YHRow['CSMaker'])){
            $Warning = '措置已制定禁止更改措施制定单位!';
            goto OUT;
        }

        if(!in_array($YHRow['Status'],[
            '隐患评级中',
            '措施制定中'
        ])){
            $Warning = '当前隐患状态已不允许更改措施制定单位!';
            goto OUT;
        }

        if(!empty($YHRow['YHDJ'])
            && $YHRow['Status'] =='隐患评级中'){
            $data['Status'] = '措施制定中';
        }

        $data['WhoMakeCS'] = $CSMaker;

        db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->update($data);


        OUT:
            return $Warning;
    }

    private function YH_isMECSMaker($YHCode){
        $row = db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->select();

        $Ret = [
            'Status'=>false,
            'Msg'=>''
        ];

        if(empty($row)){
            $Ret['Status'] = false;
            $Ret['Msg'] = '隐患行不存在!';
            goto OUT;
        }

        if(empty($row[0]['WhoMakerCS'])){
            $Ret['Status'] = false;
            $Ret['Msg'] = '措施制定人尚未指定!';
            goto OUT;
        }

        if($row[0]['WhoMakerCS']=='责任单位'
            && $row[0]['DutyCorp']==session('Corp')){
            return true;
        }

        if($row[0]['WhoMakerCS']=='执法单位'
            && $row[0]['ClaimCorp']==session('Corp')){
            return true;
        }

        OUT:
            return $Ret;

    }

    public function YHCSMake(){
        $YHCode = input('YHCode');
        $ZJYY = input('ZJYY');
        $GBYY = input('GBYY');
        $JZCS = input('JZCS');
        $YFCS = input('YFCS');
        $JZQX = input('JZQX');
        $YFQX = input('YFQX');


        dump(input());

        $Warning = 'OK';
        if(empty($ZJYY)
            || empty($GBYY)
            || empty($JZCS)
            || empty($YFCS)){
            $Warning = '直接原因、根本原因、纠正措施、预防措施不能为空!';
            goto OUT;
        }

        if(empty($YHCode)){
            $Warning = '隐患代码不能为空!';
            goto OUT;
        }

        $YHRow= db('AQYHTB')->where(['YHCode'=>$YHCode])->select();

        if(empty($YHRow)){
            $Warning = 'SMS不存在!';
            goto OUT;
        }

        if(empty($JZQX)
            ||empty($YFQX)){
            $Warning = '纠正措施和预防措施的期限不能为空!';
            goto OUT;
        }

        if($YHRow[0]['Status']!='措施制定中'){
            $Warning = '当前隐患状态并非措施制定环节!';
            goto OUT;
        }


        $CR = !$this->YH_isMECSMaker($YHCode);
        if($CR['Status']){
            $Warning = $CR['Msg'];
            goto OUT;
        }

        $data['ZJYY'] = $ZJYY;
        $data['GBYY'] = $GBYY;
        $data['JZCS'] = $JZCS;
        $data['YFCS'] = $YFCS;
        $data['JZQX'] = $JZQX;
        $data['YFQX'] = $YFQX;
        $data['CSEvalIsOK'] = '';
        $data['CSCreater'] = session('Name');
        $data['CSCreateTime'] = date('Y-m-d h:i:s');


        $r = db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->update($data);

        if(empty($r)){
            $Warning = '措施更新失败!';
            goto OUT;
        }

        OUT:
            return $Warning;

    }

    public function YHCSEval(){
        $YHCode = input('YHCode');
        $CSIsOK = input('CSIsOK');
        $CSNotOKCause = input('CSNotOKCause');
        $Warning = 'OK';

        $YHRow = db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->select();

        if(empty($YHRow)){
            $Warning = '隐患不存在!';
            goto OUT;
        }

        if(session('Corp')!=$YHRow[0]['ClaimCorp']){
            $Warning = '您非本隐患的执法部门，无权审核措施。';
            goto OUT;
        }

        if($YHRow[0]['Status'] !='措施制定中'){
            $Warning = '隐患当前状态不允许评估措施。';
            goto OUT;
        }

        if(!in_array($CSIsOK,['YES','NO'])){
            $Warning = '措施是否可行?请给答案';
            goto OUT;
        }

        $data = [];
        if($CSIsOK=='NO' && empty($CSNotOKCause)){
            $Warning = '请给出措施不通过的理由.';
            goto OUT;
        }else{
            $data['CSNotOKCause'] = $CSNotOKCause;
        }

        //CSEvalerName  CSEvalerTime  CSEvalIsOK  CSNotOKCause
        $data['CSEvalerName'] = session('Name');
        $data['CSEvalerTime'] = date('Y-m-d h:i:s');
        $data['CSEvalIsOK'] = $CSIsOK;

        if($CSIsOK=='YES'){
            $Ret = $this->CreateReformFromAQYH($YHCode);
            if($Ret['Status']!='OK'){
                $Warning = $Ret['Status'];
                goto OUT;
            }
            db()->query('UPDATE AQYHTB SET RelatedReformCode = ? WHERE YHCode = ?',[
                $Ret['ReformCode'],$YHCode
            ]);
            $data['Status'] = '整改中';
        }

        db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->update($data);

        OUT:
            return $Warning;
    }


    public function CreateReformFromAQYH($YHCode){

        $Warning = 'OK';

        $YHRow= db('AQYHTB')->where([
            'YHCode'=>$YHCode
        ])->select();

        if(empty($YHRow)){
            $Warning = '隐患不存在';
            goto OUT;
        }

        $Reform = [];

        $YHRow = $YHRow[0];

        if(!empty($YHRow['RelatedReformID'])){
            $Warning = '该安全隐患已下发整改通知书!';
            goto OUT;
        }

        $QuestionID = $YHRow['QuestionID'];
        $Question = db('QuestionList')->where([
            'id'=>$QuestionID
        ])->select();
        $Question = $Question[0];

        $QuestionSource = $Question['QuestionSource'];
        $CodePreRow = db('QuestionSource')->where([
            'SourceName'=>$QuestionSource
        ])->select();

        $ReformCode = '';
        if(!empty($CodePreRow)){
            $ReformCode = $CodePreRow[0]['CodePre'].'-'.date('Ymdhis');
        }else{
            $ReformCode = $this->CM->GetAndIncCurCode('ZGTZS');
        }

        $Reform['RelatedQuestionID'] = $QuestionID;
        $Reform['Code'] = $ReformCode;
        $Reform['QuestionSourceName'] = $QuestionSource;
        $Reform['CheckDate'] = $Question['DateFound'];
        $Reform['Inspectors'] = $Question['Finder'];
        $Reform['IssueDate'] = date('Y-m-d');
        $Reform['RequestFeedBackDate'] = date('Y-m-d');
        $Reform['QuestionTitle'] = $Question['QuestionTitle'];
        $Reform['ReformTitle'] = $YHRow['YHMS'];
        $Reform['NonConfirmDesc'] =
            '问题描述:'.'<br/>'.$Question['QuestionInfo'].'<br/>'.'危害描述:'.'<br/>'.$YHRow['YHWH'].'<br/>'.'风险描述:'.$YHRow['YHMS'];

        $Reform['Basis'] = '';
        $Reform['IssueCorp'] = $YHRow['ClaimCorp'];
        $Reform['DutyCorp'] = $YHRow['DutyCorp'];
        $Reform['CurDealCorp'] = $YHRow['DutyCorp'];
        $Reform['ReformRequirement'] = '';
        $Reform['RequireDefineCause'] = $YHRow['WhoMakeCS']=='责任单位'?'NO':'YES';
        $Reform['RequireDefineAction'] = $YHRow['WhoMakeCS']=='责任单位'?'NO':'YES';
        $Reform['DirectCause'] = $YHRow['ZJYY'];
        $Reform['RootCause'] = $YHRow['GBYY'];
        $Reform['CauseEvalerName'] = $YHRow['CSEvalerName'];
        $Reform['CauseEvalTime'] = date('Y-m-d h:i:s');
        $Reform['CorrectiveAction'] = $YHRow['JZCS'];
        $Reform['CorrectiveDeadline'] = $YHRow['JZQX'];

        $Reform['PrecautionAction'] = $YHRow['YFCS'];
        $Reform['PrecautionDeadline'] = $YHRow['YFQX'];

        $Reform['ActionMakerName'] = $YHRow['CSCreater'];
        $Reform['ActionMakeTime'] = $YHRow['CSCreateTime'];
        $Reform['ActionIsOK'] = 'YES';
        $Reform['ActionEval'] = '';
        $Reform['ActionEvalerName'] = $YHRow['CSEvalerName'];
        $Reform['ActionEvalTime'] = date('Y-m-d h:i:s');
        $Reform['ReformStatus'] = '措施审核通过执行中';
        $Reform['ReformType'] = '隐患整改';
        $Reform['RelateID'] = $YHRow['id'];
        $Reform['RelateCode'] = $YHRow['YHCode'];

        $RId =  db('ReformList')->insertGetId($Reform);

        if(empty($RId)){
            $Warning = '整改通知书下发失败!';
            goto OUT;
        }


        $TaskData = array();
        $TaskData["TaskType"] = '整改通知书';
        $TaskData["TaskInnerStatus"] = '措施审核通过执行中';
        $TaskData['TaskName'] = $Reform["ReformTitle"];
        $TaskData['DeadLine'] = $Reform['RequestFeedBackDate'];
        $TaskData['SenderName'] = session("Name");
        $TaskData['TaskSource'] = $Reform["QuestionSourceName"];
        $TaskData['SenderCorp'] = $Reform["IssueCorp"];
        $TaskData['ReciveCorp'] = $Reform['DutyCorp'];
        $TaskData['RelateID'] = $RId;
        $TaskData['CreateTime'] = date("Y-m-d H:i:s");
        $TaskData['CreatorName'] = session("Name");
        $TaskData['ParentID'] = $Question['TaskID'];
        $TaskData['Status'] = '待接收';
        $Ret = TaskCore::CreateTask($TaskData);
        if(!empty($Ret['Ret'])){
            $Warning = "任务创建失败->".$Ret['Ret'];
            goto OUT;
        }else{
            db()->query("UPDATE ReformList SET ChildTaskID = ?,ParentTaskID = ?,CurDealCorp = DutyCorp WHERE id = ?",array($Ret['ID'],$Question['TaskID'],$RId));
        }

        $Cross_Data["Type"] = TaskCore::AQYH_REFORM;
        $Cross_Data["FromID"] = $Question['id'];
        $Cross_Data["ToID"] = $RId;
        db('IDCrossIndex')->insert($Cross_Data);

        OUT:
        $ReformRet = [
            'Status'=>$Warning,
            'ReformCode'=>$ReformCode
        ];

        return $ReformRet;

    }

    public function SetAQYHStatusToOK($RelatedId){
        db('AQYHTB')->where([
            'id'=>$RelatedId
        ])->update(
            [
                'Status'=>'已关闭'
            ]
        );
    }

    public function showMyWXYList(){
        $this->assign('ISMeSuperCorp',session('CorpInfo')['IsSuperCorp']);
        $this->assign('MyCorp',session('Corp'));
        $this->assign('MyCorpRole',session('CorpRole'));
        return view('MyWXYList');
    }

    //1、超级部门的所有人员可以看到CorpGroup下所有部门的危险源
    public function JCY_GetAllWXYListByCorpGroup(){

        if(session(['CorpInfo'])['IsSuperCorp']=='NO'){
            return '';
        }

        if(session(['CorpInfo'])['IsSuperCorp']=='NO'){
            return '';
        }
        $InCaseStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr(session('CorpInfo')['SuperCorpArea']);
        $Sql= "SELECT *,1 as  CanSet FROM SMSTab WHERE Status <>'已关闭'  AND DutyCorp IN ".$InCaseStr;
        $WXYList =  db()->query($Sql);
        $WXYList = $this->RichSMSList($WXYList);
        return json_encode($WXYList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

        //2、非超级部门的所有人可以看到本部门的所有危险源
    public function GetCorpWXYList(){
        $WXYList = db()->query("SELECT *,(case ClaimCorp WHEN '".session('Corp')."' THEN 1 ELSE 0 END) as CanSet  FROM SMSTab WHERE DutyCorp = ? AND Status  <> '已关闭' ",[session('Corp')]);
        $WXYList = $this->RichSMSList($WXYList);
        return json_encode($WXYList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }
        //2、需要打分的危险源要单列
    public function GetNeedMeEvalSMSList(){
        $WXYList = db()->query("SELECT *,0 as CanSet FROM SMSTab WHERE SMSCode IN (
          SELECT SMSCode FROM SMSEval WHERE Evaler = ?
        )  AND Status <>'已关闭' ",[session('Name')]);
        $WXYList = $this->RichSMSList($WXYList);
        return json_encode($WXYList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function showMyAQYHList(){
        $this->assign('ISMeSuperCorp',session('CorpInfo')['IsSuperCorp']);
        $this->assign('MyCorp',session('Corp'));
        $this->assign('MyCorpRole',session('CorpRole'));
        return view('MyAQYHList');
    }

    public function JCY_GetAllAQYHList(){
        if(session(['CorpInfo'])['IsSuperCorp']=='NO'){
            return '';
        }
        $InCaseStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr(session('CorpInfo')['SuperCorpArea']);
        $Sql= "SELECT *,1 as  CanSet FROM AQYHTB WHERE Status <>'已关闭'  AND DutyCorp IN ".$InCaseStr;
        $YHList =  db()->query($Sql);
        return json_encode($YHList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function GetCorpAQYHList(){
        $YHList =  db()->query("SELECT *,(case ClaimCorp WHEN '".session('Corp')."' THEN 1 ELSE 0 END) as CanSet FROM AQYHTB WHERE Status <> '已关闭' AND DutyCorp = ? ",[
            session('Corp')
        ]);
        return json_encode($YHList,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function GetGroupCorpUnClosedAQYHCnt()
    {
        $GroupCorp = session('CorpInfo')['GroupCorp'];
        if($this->IsSuperCorp()){
            $GroupCorp = session('CorpInfo')['SuperCorpArea'];
        }
        $MyAllLevelChildren = $this->CorpMng->GetCorpAllLevelChildrenRows($GroupCorp);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }
        $InCaseStr[strlen($InCaseStr)-1] = ')';

        $YHCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.YHCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as YHCnt,cLevel FROM AQYHTB RIGHT JOIN CorpList ON AQYHTB.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND Status <> '已关闭' 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
        );

        $Corp_YHCnt_Cross = [];

        $MaxLevel = 0;
        $MinLevel = 1000;
        foreach ($YHCntRows as $item) {
            $Corp_YHCnt_Cross[$item['Corp']]['ZSYHCnt'] = empty($item['YHCnt'])?0:$item['YHCnt'];//自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['QBYHCnt'] = empty($item['YHCnt'])?0:$item['YHCnt'];//部门一共，初始化为自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['ParentCorp'] = $item['ParentCorp'];
            $Corp_YHCnt_Cross[$item['Corp']]['cLevel'] = $item['cLevel'];
            $Corp_YHCnt_Cross[$item['Corp']]['Corp'] = $item['Corp'];

            if($MaxLevel < $item['cLevel']){
                $MaxLevel = $item['cLevel'];
            }
            if($MinLevel > $item['cLevel']){
                $MinLevel = $item['cLevel'];
            }
        }

        for($Level = $MaxLevel;
            $Level > $MinLevel;
            $Level--){
            foreach ($Corp_YHCnt_Cross as $v){
                if($v['cLevel'] == $Level){
                    $Corp_YHCnt_Cross[$v['ParentCorp']]['QBYHCnt'] += $v['QBYHCnt'];
                }
            }
        }

        $Ret = [];
        foreach ($Corp_YHCnt_Cross as $v){
            $Ret[] = $v;
        }

        return json_encode($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

    }

    public function GetGroupCorpUnClosedSMSCnt()
    {
        $GroupCorp = session('CorpInfo')['GroupCorp'];
        if($this->IsSuperCorp()){
            $GroupCorp = session('CorpInfo')['SuperCorpArea'];
        }
        $MyAllLevelChildren = $this->CorpMng->GetCorpAllLevelChildrenRows($GroupCorp);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }
        $InCaseStr[strlen($InCaseStr)-1] = ')';

        $YHCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.SMSCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as SMSCnt,cLevel FROM SMSTab RIGHT JOIN CorpList ON SMSTab.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND Status <> '已关闭' 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
        );

        $Corp_YHCnt_Cross = [];

        $MaxLevel = 0;
        $MinLevel = 1000;
        foreach ($YHCntRows as $item) {
            $Corp_YHCnt_Cross[$item['Corp']]['ZSSMSCnt'] = empty($item['SMSCnt'])?0:$item['SMSCnt'];//自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['QBSMSCnt'] = empty($item['SMSCnt'])?0:$item['SMSCnt'];//部门一共，初始化为自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['ParentCorp'] = $item['ParentCorp'];
            $Corp_YHCnt_Cross[$item['Corp']]['cLevel'] = $item['cLevel'];
            $Corp_YHCnt_Cross[$item['Corp']]['Corp'] = $item['Corp'];

            if($MaxLevel < $item['cLevel']){
                $MaxLevel = $item['cLevel'];
            }
            if($MinLevel > $item['cLevel']){
                $MinLevel = $item['cLevel'];
            }
        }

        for($Level = $MaxLevel;
            $Level > $MinLevel;
            $Level--){
            foreach ($Corp_YHCnt_Cross as $v){
                if($v['cLevel'] == $Level){
                    $Corp_YHCnt_Cross[$v['ParentCorp']]['QBSMSCnt'] += $v['QBSMSCnt'];
                }
            }
        }

        $Ret = [];
        foreach ($Corp_YHCnt_Cross as $v){
            $Ret[] = $v;
        }

        return json_encode($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK );

    }

    public function GetGroupCorpUnClosedRFCnt()//整改通知书
    {
        $GroupCorp = session('CorpInfo')['GroupCorp'];
        if($this->IsSuperCorp()){
            $GroupCorp = session('CorpInfo')['SuperCorpArea'];
        }
        $MyAllLevelChildren = $this->CorpMng->GetCorpAllLevelChildrenRows($GroupCorp);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }
        $InCaseStr[strlen($InCaseStr)-1] = ')';

        $YHCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.RFCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as RFCnt,cLevel FROM ReformList RIGHT JOIN CorpList ON ReformList.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND ReformStatus <> '整改效果审核通过' 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
        );

        $Corp_YHCnt_Cross = [];

        $MaxLevel = 0;
        $MinLevel = 1000;
        foreach ($YHCntRows as $item) {
            $Corp_YHCnt_Cross[$item['Corp']]['ZSRFCnt'] = empty($item['RFCnt'])?0:$item['RFCnt'];//自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['QBRFCnt'] = empty($item['RFCnt'])?0:$item['RFCnt'];//部门一共，初始化为自身隐患个数
            $Corp_YHCnt_Cross[$item['Corp']]['ParentCorp'] = $item['ParentCorp'];
            $Corp_YHCnt_Cross[$item['Corp']]['cLevel'] = $item['cLevel'];
            $Corp_YHCnt_Cross[$item['Corp']]['Corp'] = $item['Corp'];

            if($MaxLevel < $item['cLevel']){
                $MaxLevel = $item['cLevel'];
            }
            if($MinLevel > $item['cLevel']){
                $MinLevel = $item['cLevel'];
            }
        }

        for($Level = $MaxLevel;
            $Level > $MinLevel;
            $Level--){
            foreach ($Corp_YHCnt_Cross as $v){
                if($v['cLevel'] == $Level){
                    $Corp_YHCnt_Cross[$v['ParentCorp']]['QBRFCnt'] += $v['QBRFCnt'];
                }
            }
        }

        $Ret = [];
        foreach ($Corp_YHCnt_Cross as $v){
            $Ret[] = $v;
        }

        return json_encode($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK );

    }

    public function GetAQYH_SMS_RF_AddCntByDateSection(){
        $StartDate = input('StartDate');
        $DayLen = input('DayLen');

        $EndDate = date('Y-m-d 23:59:59',strtotime('+'.$DayLen.' day',strtotime($StartDate)));
        $StartDate = date('Y-m-d 00:00:00',strtotime($StartDate));

        //dump($StartDate);
       // dump($EndDate);

        $GroupCorp = session('CorpInfo')['GroupCorp'];
        $MyAllLevelChildren = $this->CorpMng->GetCorpAllLevelChildrenRows($GroupCorp);
        $InCaseStr = '(';
        foreach ($MyAllLevelChildren as $v){
            $InCaseStr .= "'".$v."'".',';
        }
        $InCaseStr[strlen($InCaseStr)-1] = ')';

        $YHCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.YHCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as YHCnt,cLevel FROM AQYHTB RIGHT JOIN CorpList ON AQYHTB.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND AddTime >=? AND AddTime <=? 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
            ,[$StartDate,$EndDate]
        );

        $SMSCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.SMSCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as SMSCnt,cLevel FROM SMSTab RIGHT JOIN CorpList ON SMSTab.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND AddTime >=? AND AddTime <=? 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
            ,[$StartDate,$EndDate]
        );

        $RFCntRows = db()->query(
            "select B.Corp,B.ParentCorp,A.RFCnt,B.cLevel from 
                  (SELECT DutyCorp,ParentCorp,count(CorpList.id) as RFCnt,cLevel FROM ReformList RIGHT JOIN CorpList ON ReformList.DutyCorp = CorpList.Corp WHERE DutyCorp IN ".$InCaseStr." 
                   AND IssueDate >=? AND IssueDate <=? 
                  Group BY DutyCorp) A 
                RIGHT JOIN (SELECT * FROM CorpList WHERE  Corp  IN ".$InCaseStr.") B ON A.DutyCorp = B.Corp"
            ,[$StartDate,$EndDate]
        );


        $AQYH_SMS_RF_Arr_t = [];
        $AQYH_SMS_RF_Arr = [];
        foreach ($YHCntRows as $v){
            $AQYH_SMS_RF_Arr_t[$v['Corp']]['YHCnt'] = empty($v['YHCnt'])?0:$v['YHCnt'];
        }

        foreach ($SMSCntRows as $v){
            $AQYH_SMS_RF_Arr_t[$v['Corp']]['SMSCnt'] = empty($v['SMSCnt'])?0:$v['SMSCnt'];
        }

        foreach ($RFCntRows as $v){
            $AQYH_SMS_RF_Arr_t[$v['Corp']]['RFCnt'] = empty($v['RFCnt'])?0:$v['RFCnt'];
        }

        foreach ($AQYH_SMS_RF_Arr_t as $k=>$v){
            $AQYH_SMS_RF_Arr[] = [
                "RFCnt"=>$v['RFCnt'],
                "SMSCnt"=>$v['SMSCnt'],
                "AQYHCnt"=>$v['YHCnt'],
                "Corp"=>$k
            ];
        }


        return json_encode($AQYH_SMS_RF_Arr,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

    }

    public function DelFX(){
        $FXCode = input('FXCode');
        $FXRow = db('FXTab')->where(['FXCode'=>$FXCode])->select();
        if(empty($FXRow)){
            return '该风险不存在';
        }

        $FXRow = $FXRow[0];

        $R = db('SMSTab')->where(['FXCode'=>$FXCode])->select();
        if(!empty($R)){
            return '该风险还有关联的危险源，不能删除!';
        }

        $R = db('AQYHTB')->where(['FXCode'=>$FXCode])->select();
        if(!empty($R)){
            return '该风险还有关联的隐患，不能删除!';
        }

        if($FXRow['CreaterName']!=session('Name')){
            return '该风险是'.$FXRow['CreaterName'].'所添加，非本人不可删除!';
        }

        $R = db('FXTab')->where(['FXCode'=>$FXCode])->delete();
        if(!empty($R)){
            db('FX_Corp_Cross')->where(['FXCode'=>$FXCode])->delete();
            return 'OK';
        }else{
            return '风险删除失败！';
        }
    }

    public function showSMSList_Html($Codes/*SMS-1|SMS-3...*/,$CodeType='SMSCode' /*FXCode,id*/){
        $SMSCode_Arr = [];
        $SMSid_Arr = [];

        if($CodeType=='SMSCode'){
            $SMSCode_Arr = explode(' ',$Codes);
            $SMSRows = db('SMSTab')->field('SMSTab.*,B.Color as Color1,C.Color as Color2')
                ->join('FXDJTB B','SMSTab.FXDJ1 = B.FXDJDX','LEFT')
                ->join('FXDJTB C','SMSTab.FXDJ2 = C.FXDJDX','LEFT')
                ->whereIn('SMSCode',$SMSCode_Arr)->select();
        }else if($CodeType=='id'){
            $SMSid_Arr   = explode(' ',$Codes);
            $SMSRows = db('SMSTab')->whereIn('id',$SMSid_Arr)->select();
        }

       for($i=0;$i>count($SMSRows);$i++){
            $SMSRows[$i]['EvalLog1'] = db('SMSEval')->where(['SMSCode'=>$SMSRows[$i]['SMSCode']])->select();
        }
        $this->assign('SMSList',$SMSRows);

        return view('SMSList');

    }

    public function showAQYHList_html($Codes/*YH-1|YH-2...*/,$CodeType='YHCode' /*FXCode,id*/){
        $YHCode_Arr = [];
        $YHid_Arr = [];
        $AQYHRows = [];

        if($CodeType=='YHCode'){
            $YHCode_Arr = explode(' ',$Codes);
            $AQYHRows = db('AQYHTB')->whereIn('YHCode',$YHCode_Arr)->select();
        }else if($CodeType=='id'){
            $YHid_Arr   = explode(' ',$Codes);
            $AQYHRows = db('AQYHTB')->whereIn('id',$YHid_Arr)->select();
        }

        $this->assign('AQYHList',$AQYHRows);

        return view('AQYHList');

    }

    public function DelSMS($SMSCode = ''){
        $SMSCode = input('Code');
        $SMSRow = db('SMSTab')->where(['SMSCode'=>$SMSCode])->select();
        $Pwd = input('Pwd');
        if(empty($SMSRow)){
            return 'SMS不存在!';
        }

        $SMSRow = $SMSRow[0];

        if(!$this->CheckDelPwd($Pwd)){
            return '删除密码不正确!';
        }

        /*
         *  已处于关闭状态的危险源不可删除
         *
         *  SMS删除的步骤
            1、查看自己关联风险，若该风险没有关联其他危险源，则连同风险一起删除。
            2、删除自己所关联的整改通知书及相关任务数据
            3、删除自身
        */

        if($SMSRow['Status']=='已关闭'){
            return '该SMS已关闭，不允许删除!';
        }

        $R = db()->query('SELECT id FROM SMSTab WHERE FXCode = ? AND SMSCode <> ? ',
            [$SMSRow['FXCode'],$SMSRow['SMSCode']]);

        if(empty($R)){//没有其他SMS关联该风险了
            db('FXTab')->where(['FXCode'=>$SMSRow['FXCode']])->delete();
            db('FX_Corp_Cross')->where(['FXCode'=>$SMSRow['FXCode']])->delete();
        }else{
            db('FX_Corp_Cross')->where(
                [
                    'FXCode'=>$SMSRow['FXCode'],
                    'Corp'=>$SMSRow['DutyCorp']
                ]
            )->delete();
        }

        db('SMSTab')->where(['SMSCode'=>$SMSRow['SMSCode']])->delete();

        //开始删除整改通知书及所关联的子任务 并清空任务和 IDCrossIndex toID = RFID 的行
        if(!empty($SMSRow['RelatedReformCode'])){
            $RFRow = db('ReformList')->where(['Code'=>$SMSRow['RelatedReformCode']])->select();
            if(!empty($RFRow)){
                $RFRow = $RFRow[0];
                $ChildTaskID = $RFRow['ChildTaskID'];
                $RFID = $RFRow['id'];
                db('IDCrossIndex')->where(['toID'=>$RFID,'Type'=>TaskCore::SMS_REFORM])->delete();
                db('TaskList')->where(['id'=>$ChildTaskID])->delete();
                db('ReformList')->where(['id'=>$RFID])->delete();
            }
        }

        $this->updateDelPwd();

        return 'OK';

    }


    public function DelAQYH($YHCode = ''){
        $YHCode = input('Code');
        $YHRow = db('AQYHTB')->where(['YHCode'=>$YHCode])->select();
        $Pwd = input('Pwd');

        if(empty($YHRow)){
            return '隐患不存在!';
        }

        $YHRow = $YHRow[0];

        if(!$this->CheckDelPwd($Pwd)){
            return '删除密码不正确!';
        }

            /*
             *  已处于关闭状态的隐患不可删除
             *
             *  隐患删除的步骤
                1、查看自己关联风险，若该风险没有关联其他危险源，则连同风险一起删除。
                2、删除自己所关联的整改通知书及相关任务数据
                3、删除自身
            */

        if($YHRow['Status']=='已关闭'){
            return '该隐患已关闭，不允许删除!';
        }

        $R = db()->query('SELECT id FROM AQYHTB WHERE FXCode = ? AND YHCode <> ? ',
            [$YHRow['FXCode'],$YHRow['YHCode']]);

        if(empty($R)){//没有其他SMS关联该风险了
            db('FXTab')->where(['FXCode'=>$YHRow['FXCode']])->delete();
            db('FX_Corp_Cross')->where(['FXCode'=>$YHRow['FXCode']])->delete();
        }else{
            db('FX_Corp_Cross')->where(
                [
                    'FXCode'=>$YHRow['FXCode'],
                    'Corp'=>$YHRow['DutyCorp']
                ]
            )->delete();
        }

        db('AQYHTB')->where(['YHCode'=>$YHRow['YHCode']])->delete();
        //开始删除整改通知书及所关联的子任务 并清空任务和 IDCrossIndex toID = RFID 的行

        if(!empty($SMSRow['RelatedReformCode'])){
            $RFRow = db('ReformList')->where(['Code'=>$YHRow['RelatedReformCode']])->select();
            if(!empty($RFRow)){
                $RFRow = $RFRow[0];
                $ChildTaskID = $RFRow['ChildTaskID'];
                $RFID = $RFRow['id'];
                db('IDCrossIndex')->where(['toID'=>$RFID,'Type'=>TaskCore::AQYH_REFORM])->delete();
                db('TaskList')->where(['id'=>$ChildTaskID])->delete();
                db('ReformList')->where(['id'=>$RFID])->delete();
            }
        }

        $this->updateDelPwd();

        return 'OK';

    }


}