<?php
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;

class FDZCRoot extends PublicController{
    private $CorpMng;

    public function __construct(Request $request = null)
    {
        $this->CorpMng  = new CorpMng();
        parent::__construct($request);
    }
    //根据选定日期及适用部门，获取所有未被检查的条款或者未列入检查计划的条款列表
    public function Get_unCheckAndunPlaned_CheckItemList_AfterStartDate_ByCorp($BaseDBID,$StartDate,$ParentCorp,$ChildCorp){
        //$ParentCorp
        //$ChildGroup为空或者'全部' 则代表获取$ParentCorp下所有子孙部门
        $CorpInStr = '';
        if(empty($ChildCorp) || $ChildCorp =='全部'){
            $CorpInStr = $this->CorpMng->GetCorpAllLevelChildrenRows_InSqlStr($ParentCorp);
        }else{
            $RR  = db('CorpList')->where(['Corp'=>$ChildCorp])->find();
            if(!empty($RR)){
                $CorpInStr = "('".$RR['Corp']."')";
            }
        }

        $AllItem = db()->query('SELECT DISTINCT A.Code2 FROM SecondHalfCheckTB A JOIN SecondHalfCheckTB_Corp_Corss B
                    ON A.Code2 = B.Code2 AND A.BaseDBID = B.BaseDBID WHERE A.BaseDBID = ? AND Corp IN '.$CorpInStr,[$BaseDBID]);

        $CheckedItem = db()->query("SELECT DISTINCT A.Code2 FROM CheckListDetail A JOIN CheckList B ON A.CheckListID = B.id
                    WHERE A.CheckDBID = ? AND  B.DutyCorp IN ".$CorpInStr." AND A.isOK IS NOT NULL  AND A.EndTime >= ? AND A.Code2 IN ".$this->BuildInSqlFromArr($AllItem,'Code2'),
            [$BaseDBID,$StartDate]);

        $PlanedItem = db()->query("SELECT  DISTINCT A.Code2 FROM CheckListDetail A JOIN CheckList B ON A.CheckListID = B.id
                    WHERE B.DutyCorp IN ".$CorpInStr." AND A.CheckDBID = ? AND B.ScheduleDate >= ? AND A.Code2 IN  ".$this->BuildInSqlFromArr($AllItem,'Code2'),
            [$BaseDBID,$StartDate]);

        return  [
            'AllCode2'          =>  array_column($AllItem,      'Code2'),
            'CheckedCode2'      =>  array_column($CheckedItem,  'Code2'),
            'PlanedCode2'       =>  array_column($PlanedItem,   'Code2')
        ];
    }

    public function FDZCCorpCheckInfoQry(){
        $DutyCorp1 = input('DutyCorp1');
        $DutyCorp2 = input('DutyCorp2');
        $DBName    = input('DBName');
        $StartDate = input('StartDate');

        $DBRow = db('CheckBaseDB')->where(['BaseName'=>$DBName])->find();


        if(empty($DBRow) || empty($StartDate)){
            goto OUT;
        }

        if(empty($DutyCorp2) || $DutyCorp2 =='全部'){
            $CorpArr = CorpMng::GetCorpAllLevelChildrenRows($DutyCorp1);
        }else{
            $CorpArr = [$DutyCorp2];
        }

        $CheckInfoArr = [];

        foreach ($CorpArr as $v){
            $Arr  = $this->Get_unCheckAndunPlaned_CheckItemList_AfterStartDate_ByCorp($DBRow['id'],$StartDate,$v,$v);
            $unCheckCodeArr = array_diff($Arr['AllCode2'],$Arr['CheckedCode2']);
            $CheckInfoArr[] = [
                'Corp'                              => $v,
                'AllCode2Cnt'                       => count($Arr['AllCode2']),
                'unCheckedCode2Cnt'                 => count($unCheckCodeArr),
                'unCheckedButPlanedCode2Cnt'        => count(array_intersect($unCheckCodeArr,$Arr['PlanedCode2'])),
                'unCheckedAndunPlanedCode2Cnt'      => count(array_diff($unCheckCodeArr,$Arr['PlanedCode2'])),
                'CheckedCode2Cnt'                   => count($Arr['CheckedCode2'])
            ];
        }

        //dump($CheckInfoArr);

        $this->assign('CheckInfo',$CheckInfoArr);

        OUT:
            return $this->showFDZCCorpCheckInfoView();

    }

    public function showFDZCCorpCheckInfoView(){
        $this->assign('CorpList',$this->CorpMng->GetAllParentCorp());
        $this->assign('DBList',db('CheckBaseDB')->select());
        return view('FDZCCorpCheckInfoView');
    }
}