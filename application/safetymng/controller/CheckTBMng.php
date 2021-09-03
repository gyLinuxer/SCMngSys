<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;

class CheckTBMng extends FDZCRoot {
    private $PRE = 'lgy19891115';
    private $CorpMng;

    public function __construct(Request $request = null)
    {
        $this->CorpMng  = new CorpMng();
        parent::__construct($request);
    }

    public  function index(){
        $this->assign('CheckDB',db('CheckBaseDB')->select());
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        return view('index');
    }

    public function RMInputPre($Str){
        if(strpos($Str,$this->PRE)===0){
            return substr($Str,strlen($this->PRE)+1);
        }
        return $Str;
    }

    public function getCheckDBName($CheckDBID){
        return db('CheckBaseDB')->where(array('id'=>$CheckDBID))->select()[0]['BaseName'];
    }

    public function getCheckStandard($id){
        if(!is_numeric($id)){
            return $id;
        }
        return db('FirstHalfCheckTB')->where(array('id'=>$id,'IsValid'=>'YES'))->select()[0]['CheckStandard'];
    }

    public function FirstHalfCheckRowMng($opType = 'Add'){

        $data['BaseDBID']       = $this->RMInputPre(input('CheckDB'));
        $data['ProfessionName'] = $this->RMInputPre(input('ProfessionName'));
        $data['BusinessName']   = $this->RMInputPre(input('BusinessName'));
        $data['Code1']          = $this->RMInputPre(input('Code1'));
        $data['CheckSource']    = $this->RMInputPre(input('CheckSource'));
        $data['CheckSubject']   = $this->RMInputPre(input('CheckSubject'));
        $data['CheckContent']   = $this->RMInputPre(input('CheckContent'));
        $data['CheckStandard']  = $this->RMInputPre(input('CheckStandardEdit'));
        $data['AdderName']      = session('Name');
        $data['AddTime']        = date('Y-m-d H:i:s');
        $data['IsValid']        = 'YES';
        $MustNotBeEmptyKeys = ['ProfessionName','BusinessName','CheckStandard'];
        foreach ($MustNotBeEmptyKeys as $k){
            if(empty($data[$k])){
                $this->assign('Warning',$k.'不可为空!');
                goto OUT;
            }
        }


        if($opType=='Add'){
            $Ret = db('FirstHalfCheckTB')->where($data)->select();
            if(!empty($Ret)){
                $this->assign('Warning','该条款已经存在!') ;
            }else{
                $data['OldID']        = 0;
                $id = db('FirstHalfCheckTB')->insertGetId($data);
                if($id>0){
                    $t_data['StandardID'] = $id;
                    db('FirstHalfCheckTB')->where(['id'=>$id])->update($t_data);
                    $this->assign('Warning','添加成功！') ;
                }else{
                    $this->assign('Warning','添加失败！!') ;
                }
            }
        }else{//Mdf修改条款
            $oldId = input("rowId");
            if(empty($oldId)){
                $this->assign('Warning','未选择条款');
                goto OUT;
            }

            //检查修改过的条款是否已经存在
            $Ret = db('FirstHalfCheckTB')->where($data)->select();

            if(!empty($Ret)){
                $this->assign('Warning','该条款已经存在!') ;
                goto OUT;
            }
            $oldRow = db('FirstHalfCheckTB')->where(['StandardID'=>$oldId,"IsValid"=>"YES"])->find();
            $Ret = db('FirstHalfCheckTB')->where(['StandardID'=>$oldId,"IsValid"=>"YES"])->setField('IsValid', 'NO');
            if(empty($Ret)){
                $this->assign('Warning','删除旧条款失败');
                goto OUT;
            }else{
                $data['OldID']        = $oldId;
                $data['StandardID']   = $oldRow['StandardID'];
                $id = db('FirstHalfCheckTB')->insertGetId($data);
                $this->assign('Warning','条款修改成功!');
                goto OUT;
            }

        }

        OUT:
            return $this->showFirstHalfCheckRowMng();
    }

    public function showFirstHalfCheckRowMng($opType = 'Add',$id=0){

        if($opType=='Mdf'){//通知前端
            $this->assign('NeedInitAllSelect','YES');
        }else{
            $this->assign('NeedInitAllSelect','NO');
        }

        $this->assign('id',$id);
        $this->assign('opType',$opType);
        $this->assign('CheckDB',db('CheckBaseDB')->select());
        return view('FirstHalfCheckRowMng');
    }

    public function SecondHalfCheckRowMng($opType = 'Add',$CheckStandardID=0,$id=0){

        $RelatedCorps_Arr = input('post.RelatedCorps/a');


        $CheckMethods_Arr = input('post.CheckMethods/a');
        $CheckMethods = '';
        if(!empty($CheckMethods_Arr)){
            foreach ($CheckMethods_Arr as $v){
                if(empty($CheckMethods)){
                    $CheckMethods = $v;
                }else{
                    $CheckMethods.='|'.$v;
                }
            }
        }

        $Ret = db('FirstHalfCheckTB')->where(array('StandardID'=>$CheckStandardID,'IsValid'=>'YES'))->select();
        if(empty($Ret)){
            $this->assign('Warning','检查标准不存在!');
            goto OUT;
        }

        $CKStdRow = $Ret[0];

        $data['CheckStandardID'] = input('CheckStandardID');
        $data['ComplianceStandard'] = input('ComplianceStandard');
        $data['BasisName'] = input('BasisName');
        $data['BasisTerm'] = input('BasisTerm');
        $data['CheckMethods'] = $CheckMethods;
        $data['CheckFrequency'] = input('CheckFrequency');
        $data['InnerManual'] = input('InnerManual');
        $data['AdderName'] = session('Name');
        $data['AddTime'] = date('Y-m-d H:i:s');
        $data['IsValid'] = 'YES';
        $data['Code2'] = input('Code2');
        $data['BaseDBID'] = $CKStdRow['BaseDBID'];

        $MustNotBeEmptyKeys = ['Code2','ComplianceStandard','CheckStandardID'];

        foreach ($MustNotBeEmptyKeys as $k){
            if(empty($data[$k])){
                $this->assign('Warning',$k.'不可为空!');
                goto OUT;
            }
        }

        if($opType=='Add'){
            $RR = db('SecondHalfCheckTB')->where([
                'Code2'=>$data['Code2'],
                'isValid'=>'YES'
            ])->find();
            if(!empty($RR)){
                $this->assign('Warning','该判定标准编号已经存在!');
                goto OUT;
            }

            $id = db('SecondHalfCheckTB')->insertGetId($data);
            $t_data['ComplianceID'] = $id;
            db('SecondHalfCheckTB')->where(['id'=>$id])->update($t_data);
            if(empty($id)){
                $this->assign('Warning','添加失败!');
                goto OUT;
            }else{

                db('SecondHalfCheckTB_Corp_Corss')->where([
                    'Code2'      =>$data['Code2'],
                    'BaseDBID'   =>$CKStdRow['BaseDBID']
                ])->delete();

                foreach ($RelatedCorps_Arr as $v){
                    db('SecondHalfCheckTB_Corp_Corss')->insert(
                        [
                            'BaseDBID'      =>$CKStdRow['BaseDBID'],
                            'Code2'         =>$data['Code2'],
                            'Corp'          =>$v,
                            'Adder'         =>session('Name'),
                            'AdderTime'     =>date('Y-m-d h:i:s')
                        ]);
                }

                $this->assign('Warning','添加成功!');
                goto OUT;
            }
        }else if($opType=='Mdf'){

            $RR = db('SecondHalfCheckTB')->where([
                'Code2'=>$data['Code2'],
                'isValid'=>'YES'
            ])->where('id','neq',$id)->find();

            if(!empty($RR)){
                $this->assign('Warning','该判定标准编号已经存在!');
                goto OUT;
            }

            $SecondTBRow  = db('SecondHalfCheckTB')->where(array('id'=>$id,'IsValid'=>'YES'))->find();
            $Cnt = db('SecondHalfCheckTB')->where(array('id'=>$id,'IsValid'=>'YES'))->setField('IsValid','NO');
            if(empty($Cnt)){
                $this->assign('Warning','符合性判定标准不存在!');
                goto OUT;
            }
            $data['OldID'] = $id;
            $data['ComplianceID'] = $SecondTBRow['ComplianceID'];
            $id  =  db('SecondHalfCheckTB')->insertGetId($data);
            if(empty($id)){
                $this->assign('Warning','添加符合性判定标准失败!');
                goto OUT;
            }else{

                db('SecondHalfCheckTB_Corp_Corss')->where([
                    'Code2'      =>$data['Code2'],
                    'BaseDBID'   =>$CKStdRow['BaseDBID']
                ])->delete();

                foreach ($RelatedCorps_Arr as $v){
                    db('SecondHalfCheckTB_Corp_Corss')->insert(
                        [
                            'BaseDBID'      =>$CKStdRow['BaseDBID'],
                            'Code2'         =>$data['Code2'],
                            'Corp'          =>$v,
                            'Adder'         =>session('Name'),
                            'AdderTime'     =>date('Y-m-d h:i:s')
                        ]);
                }

                $this->assign('Warning','修改成功!');
            }
        }

        OUT:
            return $this->showSecondHalfCheckRowMng($opType,$CheckStandardID,$id);

    }

    public function showSecondHalfCheckRowMng($opType = 'Add',$CheckStandardID=0,$id=0){
        if(empty($CheckStandardID)){
            $this->assign('Warning','没有选择检查标准!');
            goto OUT;
        }

        $Ret = db('FirstHalfCheckTB')->where(array('StandardID'=>$CheckStandardID,'IsValid'=>'YES'))->select()[0];
        if(empty($Ret)){
            $this->assign('Warning','检查标准不存在!');
            goto OUT;
        }else{
            $this->assign('CheckStandard',$Ret['CheckStandard']);
        }

        if(!empty($id)){
            $Ret = db()->query("SELECT * FROM SecondHalfCheckTB  WHERE id=? AND IsValid='YES' ",array($id));
            $this->assign('ComplianceStandardRow',$Ret[0]);
            $this->assign('CheckMethods',json_encode(explode('|',$Ret[0]['CheckMethods'],JSON_UNESCAPED_UNICODE)));
            $this->assign('RelatedCorps',json_encode(array_column(
                db('SecondHalfCheckTB_Corp_Corss')->where([
                'Code2'     =>$Ret[0]['Code2'],
                'BaseDBID'  =>$Ret[0]['BaseDBID']
            ])->select(),'Corp'),JSON_UNESCAPED_UNICODE));
        }

        $this->assign('CheckStandardID',$CheckStandardID);
        $this->assign('opType',$opType);
        $this->assign('id',$id);
        $this->assign('CorpList',$this->GetCorpList());

        OUT:
            return view('SecondHalfCheckRowMng');
    }

    public function showCheckRowQuery(){
        $rowData = $this->CheckRowQuery();
        $this->assign('SecondCheckRowList',$rowData);
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        return $this->index();
    }


    public function CheckRowQuery()
    {
        $data['BaseDBName']       =  input('CheckDB');
        $data['ProfessionName'] = "%".$this->RMInputPre(input('ProfessionName'))."%";
        $data['BusinessName']   = "%".$this->RMInputPre(input('BusinessName'))."%";
        $data['CheckSource']    = "%".$this->RMInputPre(input('CheckSource'))."%";
        $data['Code1']          = "%".$this->RMInputPre(input('Code1'))."%";
        $data['Code2']          = "%".$this->RMInputPre(input('Code2'))."%";
        $data['CheckSubject']   = "%".$this->RMInputPre(input('CheckSubject'))."%";
        $data['CheckContent']   = "%".$this->RMInputPre(input('CheckContent'))."%";
        $data['CheckStandard']  = "%".$this->RMInputPre(input('CheckStandard'))."%";
        $data['CheckContent']   = "%".$this->RMInputPre(input('CheckContent'))."%";

        $ParentCorp             = input('DutyCorp1');
        $ChildCorp              = input('DutyCorp2');
        $ItemCheckType          = input('ItemCheckType');
        $StartDate              = input('StartDate');

        $DBRow = db('CheckBaseDB')->where(['BaseName'=>$data['BaseDBName']])->find();
        if(empty($DBRow)){
            return [];
        }

        $Code2InStr = "";
        if(!empty($ItemCheckType) || !empty($StartDate) || !empty($ParentCorp)){
            $ItemCheckInfo_Arr = $this->Get_unCheckAndunPlaned_CheckItemList_AfterStartDate_ByCorp($DBRow['id'],$StartDate,$ParentCorp,$ChildCorp);
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
                        FirstHalfCheckTB.BaseDBID,
                        FirstHalfCheckTB.ProfessionName,
                        FirstHalfCheckTB.BusinessName,
                        FirstHalfCheckTB.Code1,
                        SecondHalfCheckTB.Code2,
                        FirstHalfCheckTB.CheckSubject,
                        FirstHalfCheckTB.CheckContent,
                        FirstHalfCheckTB.StandardID,
                        FirstHalfCheckTB.CheckSource,
                        FirstHalfCheckTB.id as CheckStandardRowID,
                        FirstHalfCheckTB.CheckStandard FROM FirstHalfCheckTB LEFT JOIN SecondHalfCheckTB ON 
                        SecondHalfCheckTB.CheckStandardID = FirstHalfCheckTB.StandardID JOIN CheckBaseDB on CheckBaseDB.id=FirstHalfCheckTB.BaseDBID WHERE
                         ".$Code2InStr." 
                        (CheckBaseDB.BaseName = ?) AND 
                        FirstHalfCheckTB.ProfessionName like ? AND 
                        FirstHalfCheckTB.BusinessName LIKE ? AND 
                        FirstHalfCheckTB.CheckSource LIKE ? AND 
                        FirstHalfCheckTB.Code1 LIKE ? AND 
                        (SecondHalfCheckTB.Code2 LIKE ? OR SecondHalfCheckTB.Code2 IS NULL) AND 
                        FirstHalfCheckTB.CheckSubject LIKE ? AND 
                        FirstHalfCheckTB.CheckContent LIKE ? AND 
                        FirstHalfCheckTB.CheckStandard LIKE ? AND 
                        FirstHalfCheckTB.IsValid = 'YES' AND 
                       (SecondHalfCheckTB.IsValid = 'YES' OR SecondHalfCheckTB.IsValid IS NULL ) ORDER BY FirstHalfCheckTB.BaseDBID,ProfessionName,CheckSubject,Code1,Code2,CheckContent,FirstHalfCheckTB.CheckStandard
                        ";

        $this->assign('RelatedCorps',json_encode(input('RelatedCorps/a'),JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));


        $RR =   db()->query($SQL,array($data['BaseDBName'],$data['ProfessionName'],
                                      $data['BusinessName'],$data['CheckSource'],$data['Code1'],
                                      $data['Code2'],$data['CheckSubject'],
                                      $data['CheckContent'],$data['CheckStandard']));

        for($j=0;$j<count($RR);$j++){
            $Code2 = $RR[$j]['Code2'];
            if(!empty($Code2)){
                $RR[$j]['Corps'] = db('SecondHalfCheckTB_Corp_Corss')->where([
                    'Code2'         =>$Code2,
                    'BaseDBID'      =>$DBRow['id']
                ])->select();
            }
        }



        return $RR;
    }


    public function AddCheckDB(){
        $DBName = input('DBName');
        if(empty($DBName)){
            return '数据库名称不能为空';
        }

        $R = db('CheckBaseDB')->where(['BaseName'=>$DBName])->select();

        if(!empty($R)){
            return '该数据库名称已存在!';
        }

        $Data['BaseName']       =  $DBName;
        $Data['AdderID']        =  session('UserID');
        $Data['AdderName']      =  session('Name');
        $Data['AddTime']        =  date('Y-m-d h:i:s');

        $R = db('CheckBaseDB')->insertGetId($Data);

        if(!empty($R)){
            return 'OK';
        }else{
            return '增加数据库失败!';
        }

    }


}