<?php
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2021/8/27
 * Time: 09:32
 */
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Loader;
use think\Session;

class FDZCExcelMng extends ExcelMng{

    private  $FDZCTitleRowNeedCols;
    private  $CorpMng;
    public function __construct(Request $request = null)
    {
        $this->CorpMng = new CorpMng();
        $this->FDZCTitleRowNeedCols = [
            '专业名称'          =>'ProfessionName',
            '业务名称'          =>'BusinessName',
            '检查项目'          =>'CheckSubject',
            '检查来源'          =>'CheckSource',
            '检查标准编号'       =>'Code1',
            '判定标准编号'       =>'Code2' ,
            '检查内容'          =>'CheckContent',
            '检查标准'          =>'CheckStandard',
            '符合性判定标准'     =>'ComplianceStandard',
            '检查方式'          =>'CheckMethods',
            '依据名称'          =>'BasisName',
            '依据条款'          =>'BasisTerm',
            '单位手册'          =>'InnerManual',
            '检查频次'          =>'CheckFrequency',
            '操作类型'          =>'opType',
            '导入结果'          =>'opRet'
        ];
        parent::__construct($request);
    }

    public function index()
    {
        $this->assign('DBList',db('CheckBaseDB')->select());
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        return view('index');
    }

    public function updateFDZCSJK(){

        $DBName = input('DBName');
        $TitleRowNum = intval(input('TitleRowNum'));
        $TitleRowNum = $TitleRowNum -1 > 0 ? $TitleRowNum -1 : 0;

        $ExcelArr =  $this->uploadExcelSheetToArr(0);

        if(empty($ExcelArr)){
            return 'Excel内容为空!';
        }

        $TitleRow  = $ExcelArr[$TitleRowNum];

        if(empty($TitleRow)){
            return 'Excel标题行为空!';
        }

        $NeedCols = '';
        $ColIndexArr =[];
        $FakeCorps = [];
        $Index = 0;

        foreach ($this->FDZCTitleRowNeedCols as $k=>$v){
            $ColIndexArr[$v] = -1;
        }

        for ($i=0;$i<count($TitleRow);$i++){
            $k = $this->FDZCTitleRowNeedCols[$TitleRow[$i]];
            if(!empty($k)){
                $ColIndexArr[$k] = $i;
            }else{
                if($i >=2 ){//默认认为第3列开始才有部门名字
                    $FakeCorps[$i] = $TitleRow[$i];
                }
            }
        }

        foreach ($ColIndexArr as $k=>$v){
            if($v==-1){
                $name  = array_search($k,$this->FDZCTitleRowNeedCols);
                $NeedCols.= ' '.$name;
            }
        }

        if(!empty($NeedCols)){
            return '标题行缺少这些必要列:'.$NeedCols;
        }

        //查找法定自查数据库是否存在
        $DBRow = db('CheckBaseDB')->where(['BaseName'=>$DBName])->find();
        if(empty($DBRow)){
            return '法定自查数据库名不存在!';
        }

        $DBID = $DBRow['id'];

        for($j=$TitleRowNum+1;$j<count($ExcelArr);$j++){
            $FirstTBData = [];
            $SecondTBData = [];
            $SecondHalfCheckTB_Corp_Corss_Data = [];
            $opMsg  = '';

            $opType = $ExcelArr[$j][$ColIndexArr['opType']];

            $FirstTBData['BaseDBID']            = $DBID;
            $FirstTBData['ProfessionName']      = $ExcelArr[$j][$ColIndexArr['ProfessionName']];
            $FirstTBData['BusinessName']        = $ExcelArr[$j][$ColIndexArr['BusinessName']];
            $FirstTBData['CheckSource']         = $ExcelArr[$j][$ColIndexArr['CheckSource']];
            $FirstTBData['CheckSubject']        = $ExcelArr[$j][$ColIndexArr['CheckSubject']];
            $FirstTBData['Code1']               = $ExcelArr[$j][$ColIndexArr['Code1']];
            $FirstTBData['CheckContent']        = $ExcelArr[$j][$ColIndexArr['CheckContent']];
            $FirstTBData['CheckStandard']       = $ExcelArr[$j][$ColIndexArr['CheckStandard']];
            $FirstTBData['IsValid']             = 'YES';

            $SecondTBData['BaseDBID']            = $DBID;
            $SecondTBData['Code2']              = $ExcelArr[$j][$ColIndexArr['Code2']];
            $SecondTBData['ComplianceStandard'] = $ExcelArr[$j][$ColIndexArr['ComplianceStandard']];
            $SecondTBData['CheckMethods']       = $ExcelArr[$j][$ColIndexArr['CheckMethods']];
            $SecondTBData['BasisName']          = $ExcelArr[$j][$ColIndexArr['BasisName']];
            $SecondTBData['BasisTerm']          = $ExcelArr[$j][$ColIndexArr['BasisTerm']];
            //$SecondTBData['RelatedCorps']       = $ExcelArr[$j][$ColIndexArr['RelatedCorps']];
            $SecondTBData['InnerManual']        = $ExcelArr[$j][$ColIndexArr['InnerManual']];
            $SecondTBData['CheckFrequency']     = $ExcelArr[$j][$ColIndexArr['CheckFrequency']];
            $SecondTBData['IsValid']            = 'YES';

            if(in_array($opType,['新增','修改','修订','删除'])){
                if(empty($FirstTBData['Code1'])){
                    $opMsg .= '检查标准编号不可为空！';
                }

                if(empty($SecondTBData['Code2'])){
                    $opMsg.= '符合性判定标准编号不可为空！';
                }

                if(!empty($opMsg)){
                    goto CT;
                }

                if($opType=='新增'){
                    //开始新增检查标准

                    //先看看该标准是否已经实质性存在?
                    $R = db('FirstHalfCheckTB')->where([
                            'Code1'        =>$FirstTBData['Code1'],
                            'IsValid'      =>'YES',
                            'BaseDBID'     =>$DBID
                        ]
                    )->find();
                    if(!empty($R)){
                        $opMsg .= ' 该检查标准已存在!';
                    }else{
                        $FirstTBData['AdderName']           = session('Name');
                        $FirstTBData['AddTime']             = date('Y-m-d- h:i:s');
                        $FirstTBData['OldID']               = 0;
                        $StandardID = db('FirstHalfCheckTB')->insertGetId($FirstTBData);
                        db('FirstHalfCheckTB')
                            ->where(['id'=>$StandardID])
                            ->update(['StandardID'=>$StandardID]);
                    }

                    //开始新增加判定标准
                    //先看看判定标准是否已经实质性存在?
                    $R = db('SecondHalfCheckTB')->where([
                        'Code2'        =>$SecondTBData['Code2'],
                        'IsValid'      =>'YES',
                        'BaseDBID'     =>$DBID
                    ])->find();
                    if(!empty($R)){
                        $opMsg .= ' 该判定标准已存在!';
                    }else{
                        $SecondTBData['AdderName']      = session('Name');
                        $SecondTBData['AddTime']        = date('Y-m-d h:i:s');
                        $SecondTBData['OldID']          = 0;
                        $ComplianceID = db('SecondHalfCheckTB')->insertGetId($SecondTBData);
                        db('SecondHalfCheckTB')
                            ->where(['id'=>$ComplianceID])
                            ->update([
                                'ComplianceID'      =>$ComplianceID,
                                'CheckStandardID'   =>$StandardID
                            ]);
                    }

                    db('SecondHalfCheckTB_Corp_Corss')->where([
                        'Code2'=>$SecondTBData['Code2'],
                        'BaseDBID'     =>$DBID
                    ])->delete();
                    foreach ($FakeCorps as $k=>$v){
                        if(intval($ExcelArr[$j][$k]) > 0 ){
                            db('SecondHalfCheckTB_Corp_Corss')->insert(
                                [
                                    'BaseDBID'     =>$DBID,
                                    'Code2'         =>$SecondTBData['Code2'],
                                    'Corp'          =>$v,
                                    'Adder'         =>session('Name'),
                                    'AdderTime'     =>date('Y-m-d h:i:s')
                                ]);
                        }
                    }

                }elseif($opType=='修改' || $opType=='修订'){
                    //开始修改检查标准
                    $FirstTBData['AdderName']           = session('Name');
                    $FirstTBData['AddTime']             = date('Y-m-d- h:i:s');
                    $FirstTBData['OldID']               = 0;
                    $Affected = db('FirstHalfCheckTB')->where([
                        'Code1'        =>$FirstTBData['Code1'],
                        'IsValid'      =>'YES'
                    ])->update($FirstTBData);
                    if(empty($Affected)){
                        $opMsg .= ' 修改检查标准失败!';
                    }

                    //开始判定标准
                    $SecondTBData['AdderName']      = session('Name');
                    $SecondTBData['AddTime']        = date('Y-m-d h:i:s');
                    $SecondTBData['OldID']          = 0;
                    $Affected = db('SecondHalfCheckTB')->where([
                        'Code2'        =>$SecondTBData['Code2'],
                        'IsValid'      =>'YES',
                        'BaseDBID'     =>$DBID
                    ])->update($SecondTBData);
                    if(empty($Affected)){
                        $opMsg .= ' 修改判定标准失败!';
                    }

                    db('SecondHalfCheckTB_Corp_Corss')->where([
                        'Code2'=>$SecondTBData['Code2'],
                        'BaseDBID'     =>$DBID
                    ])->delete();
                    foreach ($FakeCorps as $k=>$v){
                        if(intval($ExcelArr[$j][$k]) > 0 ){
                            db('SecondHalfCheckTB_Corp_Corss')->insert(
                                [
                                    'BaseDBID'     =>$DBID,
                                    'Code2'         =>$SecondTBData['Code2'],
                                    'Corp'          =>$v,
                                    'Adder'         =>session('Name'),
                                    'AdderTime'     =>date('Y-m-d h:i:s')
                                ]);
                        }
                    }

                }elseif($opType=='删除'){

                    db('SecondHalfCheckTB')->where([
                        'Code2'        =>$SecondTBData['Code2'],
                        'IsValid'      =>'YES',
                        'BaseDBID'     =>$DBID
                    ])->delete();
                    db('SecondHalfCheckTB_Corp_Corss')->where([
                            'Code2'=>$SecondTBData['Code2'],
                            'BaseDBID'     =>$DBID]
                    )->delete();

                    $RR = db()->query("SELECT * FROM SecondHalfCheckTB WHERE CheckStandardID IN 
                          (SELECT StandardID FROM FirstHalfCheckTB WHERE Code1 = ? AND IsValid = 'YES' AND BaseDBID = ?) ",[$FirstTBData['Code1'],$DBID]);
                    if(empty($RR)){
                        db('FirstHalfCheckTB')->where([
                            'Code1'        =>$FirstTBData['Code1'],
                            'IsValid'      =>'YES',
                            'BaseDBID'     =>$DBID
                        ])->delete();
                    }else{
                        $opMsg.='检查标准仍被引用，';
                    }
                }

                CT:
                    $ExcelArr[$j][$ColIndexArr['opRet']] = ($opMsg==''?'OK':$opMsg);

            }



        }

        $this->TransArrToExcelDownload($ExcelArr,'导入结果'.date("Ymd-His").".xls");

    }

    public function exportFDZCSJK(){
        $DBName    = input('DBNameOut');
        $DutyCorp1 = input('DutyCorp1'); //父部门
        $DutyCorp2 = input('DutyCorp2'); //子部门
        $Param_Arr = [];
        $In_Sql = '';


        //查找法定自查数据库是否存在
        $DBRow = db('CheckBaseDB')->where(['BaseName'=>$DBName])->find();
        if(empty($DBRow)){
            return '法定自查数据库名不存在!';
        }

        $Param_Arr[] = $DBRow['id'];

        if(empty($DutyCorp2) || $DutyCorp2=='全部'){
            if(!empty($DutyCorp1)){
                //获取全部子部门的
                $In_Sql = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($DutyCorp1);
                $In_Sql = ' IN '.$In_Sql;
            }
        }else{
            $In_Sql = ' = ?';
            $Param_Arr[] = $DutyCorp2;
        }

        $TitleRow[0] = [
            '序号'            =>'序号',
            '操作类型'         =>'操作类型',
            '导入结果'         =>'导入结果',
            '专业名称'         =>'专业名称',
            '业务名称'         =>'业务名称',
            '检查项目'         =>'检查项目',
            '检查来源'         =>'检查来源',
            '检查标准编号'     =>'检查标准编号',
            '判定标准编号'     =>'判定标准编号',
            '检查内容'         =>'检查内容',
            '检查标准'         =>'检查标准',
            '符合性判定标准'    =>'符合性判定标准',
            '检查方式'         =>'检查方式',
            '依据名称'         =>'依据名称',
            '依据条款'         =>'依据条款',
            '单位手册'         =>'单位手册',
            '检查频次'         =>'检查频次',
        ];

        $SQL  = "SELECT                              ''      as '序号',
                                                     ''      as '操作类型',
                                                     ''      as '导入结果',
                        FirstHalfCheckTB.ProfessionName      as '专业名称',
                        FirstHalfCheckTB.BusinessName        as '业务名称',
                        FirstHalfCheckTB.CheckSubject        as '检查项目',
                        FirstHalfCheckTB.CheckSource         as '检查来源',
                        FirstHalfCheckTB.Code1               as '检查标准编号',
                        SecondHalfCheckTB.Code2              as '判定标准编号',
                        FirstHalfCheckTB.CheckContent        as '检查内容',                    
                        FirstHalfCheckTB.CheckStandard       as '检查标准',
                        SecondHalfCheckTB.ComplianceStandard as '符合性判定标准',
                        SecondHalfCheckTB.CheckMethods       as '检查方式',
                        SecondHalfCheckTB.BasisName          as '依据名称',
                        SecondHalfCheckTB.BasisTerm          as '依据条款',
                        SecondHalfCheckTB.InnerManual        as '单位手册',
                        SecondHalfCheckTB.CheckFrequency     as '检查频次'
                        FROM FirstHalfCheckTB LEFT JOIN SecondHalfCheckTB ON 
                        SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.StandardID JOIN CheckBaseDB on CheckBaseDB.id=FirstHalfCheckTB.BaseDBID 
                        WHERE FirstHalfCheckTB.BaseDBID = ? AND  SecondHalfCheckTB.Code2 IN (SELECT Code2 FROM SecondHalfCheckTB_Corp_Corss WHERE Corp ".$In_Sql.")";
        
        $R = db()->query($SQL,$Param_Arr);

        if(empty($R)) goto OUT;

        $SQL1 = "SELECT SecondHalfCheckTB.Code2 FROM FirstHalfCheckTB LEFT JOIN SecondHalfCheckTB ON 
                        SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.StandardID JOIN CheckBaseDB on CheckBaseDB.id=FirstHalfCheckTB.BaseDBID 
                        WHERE FirstHalfCheckTB.BaseDBID = ? AND  SecondHalfCheckTB.Code2 IN (SELECT Code2 FROM SecondHalfCheckTB_Corp_Corss WHERE Corp ".$In_Sql.")";

        $Code2_Arr = db()->query($SQL1,$Param_Arr);
        $Code2_Arr = array_column($Code2_Arr,'Code2');
        $Corp_Arr = db('SecondHalfCheckTB_Corp_Corss')->field('DISTINCT Corp')->whereIn('Code2',$Code2_Arr)->select();
        $Corp_Arr = array_column($Corp_Arr,'Corp');

        $Corp_Index_Arr = [];
        $i = 0;
        foreach ($Corp_Arr as $v){
            $TitleRow[0][] = $v;
            $Corp_Index_Arr[$v] = $i++;
        }

        for ($j=0;$j<count($R);$j++){
            $Code2 = $R[$j]['判定标准编号'];
            $CorpArr = array_column(
                db('SecondHalfCheckTB_Corp_Corss')->where(['BaseDBID'=>$DBRow['id'],'Code2'=>$Code2])->select()
                ,'Corp'
            );
            $R[$j]['序号'] = $j+1;
            foreach ($Corp_Index_Arr as $k => $v){
                $R[$j][$v] = in_array($k,$CorpArr)?1:NULL;
            }
        }

        $R = array_merge($TitleRow,$R);

        OUT:

            $this->TransArrToExcelDownload($R,'导出结果'.date("Ymd-His").".xls");

    }
}