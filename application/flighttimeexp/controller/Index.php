<?php
namespace app\flighttimeexp\controller;
use think\Controller;
use think\Db;
use think\Loader;
use think\Log;
class Index  extends Controller
{
    private  $TB_PRE = '[172.16.65.149].jwb.dbo.';
    public function index()
    {
        Log::write('访问FlightTimeExp'.date('Y-m-d H:i:s'),'zk2000');
        $this->GetAllModelPlanes();
        $this->lgyQuery();
        OUT:
         return view('index');
    }

    private function AddFHTime($Time1,$Time2){

        floatval($Time1)<0?$Time1=0:0;
        floatval($Time2)<0?$Time2=0:0;

        $ZS1 = intval($Time1);
        $XS1 = (floatval($Time1)-intval($Time1))/0.6;

        $ZS2 = intval($Time2);
        $XS2 = (floatval($Time2)-intval($Time2))/0.6;

        $Add = $ZS1 + $XS1 + $ZS2 + $XS2 ;
        $Add = ($Add - intval($Add)) * 0.6 + intval($Add);
        return $Add;
    }

    public function TranslateFHShow($IN){
        //6.30 - > 6.5
        $ZS = intval($IN);
        $XS = $IN - $ZS;
        //$XS = $XS / 0.6;
        return $ZS + $XS;
    }

    public function  TranArrToJHKey($Arr_IN,$KeyName='JH')
    {
           $data =array();
           if(!empty($Arr_IN)) {
               foreach ($Arr_IN as $v){
                   $data[$v[$KeyName]] = $v;
               }
           }
           return $data;
    }

    public function NumFormat($in,$len=2)
    {
        return number_format($in,$len,'.','').' ';
    }

    public function FakeJH($JH){
        if(trim($JH) =='8888')
            return '10AT';
        return $JH;
    }

    public function FakeJX($JX){
        if(trim($JX)=='PA44')
            return 'PA-44-180';

        if(trim($JX) =='172R')
            return 'CESSNA 172R';

        return $JX;
    }

    public function lgyQuery(){
        $PlaneHid = input('PlaneHid');
        $PlaneHid = explode('|',$PlaneHid);
        $INCase = implode(',',$PlaneHid);
        $StartDate = input('StartDate');
        $EndDate   = input('EndDate');
        $TypeSel = input('typeSel');

        $this->assign('PlaneSel',implode(',',$PlaneHid));

        if(strtotime($EndDate)<strtotime($StartDate)){
            $this->assign('Waring','结束日期小于开始日期');
            goto OUT;
        }

        $ParamArr = array();
        $ParamArr1 = array();
        $JWC_N = date('Y');//年
        $JWC_Y = date('m');//月
        if(!empty($EndDate)){
            $rqSql1 = ' AND FT_TB.日期 <=? ';
            $ParamArr[] = $EndDate;
            $ParamArr1[] = $EndDate;
            $JWC_N = date('Y',strtotime($EndDate));
            $JWC_Y = date('m',strtotime($EndDate));
        }

        if(!empty($StartDate)){
            $rqSql2 = ' AND FT_TB.日期 >=? ';
            $ParamArr1[] = $StartDate;
         }

        if(empty($INCase)){
            goto OUT;
        }else{
            $INCase = '('.$INCase.')';
        }

        $this->assign('SelType',$TypeSel);

        Log::write('访问FlightTimeExp\r\n类型:'.$TypeSel.'\r\n范围:'.$INCase.'\r\n起始日期:'.$StartDate.'\r\n结束日期:'.$EndDate.'\r\n时间-->'.date('Y-m-d H:i:s').'\r\nip:'.request()->ip(),'zk2000');

        Loader::import('PHPExcel.PHPExcel');
        Loader::import('PHPExcel.PHPExcel.IOFactory.PHPExcel_IOFactory');
        Loader::import('PHPExcel.PHPExcel.Reader.Excel2007');
        $phpExcel = new \PHPExcel();
        $objReader = \PHPExcel_IOFactory::createReader ( 'Excel5' );

        $MHJ_Plane_MB_Reader   = $objReader->load ("./MHJ_Plane_MB.xls" );
        $MHJ_Eng_MB_Reader     = $objReader->load ("./MHJ_Eng_MB.xls" );
        $JWC_Plane_MB_Reader   = $objReader->load ("./JWC_Plane_MB.xls" );
        $JWC_Eng_MB_Reader     = $objReader->load ("./JWC_Eng_MB.xls" );

       if($TypeSel =='Plane') {
        //翻修后时间等
        $Ret1 = db()->query("SELECT
          ltrim(rtrim(FT_TB.[机型])) as JX,
          FT_TB.[机号] as JH,
          sum(case  when 连续起落>=0 AND 复飞起落 >=0 AND 正常起落 >=0 then  连续起落*0.5 + 复飞起落*0.2+正常起落 end) as FC_TSO,
          (round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1) + sum(round(空中时间,0,1)))+(sum(([空中时间]-round([空中时间],0,1))/0.6) - round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1))*0.6 as FH_TSO,
          (round(sum((地面时间-round(地面时间,0,1))/0.6),0,1) + sum(round(地面时间,0,1)))+(sum((地面时间-round(地面时间,0,1))/0.6) - round(sum((地面时间-round(地面时间,0,1))/0.6),0,1))*0.6 as DMH_TSO,
          sum(正常起落) as ZCQL_TSO
      FROM ".$this->TB_PRE."[flight] as FT_TB join ".$this->TB_PRE."plane as PL_TB on FT_TB.机号 = PL_TB.机号 where FT_TB.机号 in ".$INCase.$rqSql1." AND 日期 >=上次翻修日期   group by FT_TB.[机型],FT_TB.机号,上次翻修日期,自开始空地时间,
          自开始空中时间,
          自开始起落次数
           order by FT_TB.机号",$ParamArr);

        if(!empty($Ret1)){
            foreach ($Ret1 as $k =>$v){
                $Ret1[$k]['FGH_TSO']=$this->AddFHTime($v['DMH_TSO'],$Ret1[$k]['FH_TSO']);
            }
            $Ret1 = $this->TranArrToJHKey($Ret1);
        }

        //查询区间内

        $Ret2 = db()->query("SELECT
          ltrim(rtrim(FT_TB.[机型])) as JX,
          FT_TB.[机号] as JH,
          自开始空中时间 as FH_ZD,
          自开始空地时间 as FGH_ZD,
          自开始起落次数 as QL_ZD,
          sum(case when 连续起落>=0 AND 复飞起落 >=0 AND 正常起落 >=0 then  连续起落*0.5 + 复飞起落*0.2+正常起落 end) as FC_INQR,
          (round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1) + sum(round(空中时间,0,1)))+(sum(([空中时间]-round([空中时间],0,1))/0.6) - round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1))*0.6 as FH_INTB,
          (round(sum((地面时间-round(地面时间,0,1))/0.6),0,1) + sum(round(地面时间,0,1)))+(sum((地面时间-round(地面时间,0,1))/0.6) - round(sum((地面时间-round(地面时间,0,1))/0.6),0,1))*0.6 as DMH_INTB,
          sum(正常起落) as ZCQL_INTB
      FROM ".$this->TB_PRE."[flight] as FT_TB join ".$this->TB_PRE."plane as PL_TB on FT_TB.机号 = PL_TB.机号 where FT_TB.机号 in ".$INCase.$rqSql1.$rqSql2."   group by FT_TB.[机型],FT_TB.机号,上次翻修日期,自开始空地时间,
          自开始空中时间,
          自开始起落次数 order by FT_TB.机号",$ParamArr1);
            //dump($Ret2);
        if(!empty($Ret2)){
            foreach ($Ret2 as $k =>$v){
                //将FH_ZD自带飞行小时与FH_INTB相加，得到真正的空中时间
                $Ret2[$k]['FH_INQR'] = $v['FH_INTB'];
                //将FGH_ZD自带飞行小时与自新飞行小时相加，得到真正的空地时间
                $Ret2[$k]['FGH_INQR']=$this->AddFHTime($v['DMH_INTB'],$v['FH_INTB']);
               // dump($Ret2[$k]['FGH_INQR']);
                //将QL_ZD自带起落次数与ZCQL_INTB相加
                $Ret2[$k]['QL_INQR']= $v['ZCQL_INTB'];
            }
            $Ret2 = $this->TranArrToJHKey($Ret2);
        }

        // dump($Ret2);
        //自新的
        $Ret = db()->query("SELECT
          ltrim(rtrim(FT_TB.[机型])) as JX,
          FT_TB.[机号] as JH,
          自开始空中时间 as FH_ZD,
          自开始空地时间 as FGH_ZD,
          自开始起落次数 as QL_ZD,
          sum(case when 连续起落>=0 AND 复飞起落 >=0 AND 正常起落 >=0 then  连续起落*0.5 + 复飞起落*0.2+正常起落 end) as FC_INTB,
          (round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1) + sum(round(空中时间,0,1)))+(sum(([空中时间]-round([空中时间],0,1))/0.6) - round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1))*0.6 as FH_INTB,
          (round(sum((地面时间-round(地面时间,0,1))/0.6),0,1) + sum(round(地面时间,0,1)))+(sum((地面时间-round(地面时间,0,1))/0.6) - round(sum((地面时间-round(地面时间,0,1))/0.6),0,1))*0.6 as DMH_INTB,
          sum(正常起落) as ZCQL_INTB
      FROM ".$this->TB_PRE."[flight] as FT_TB join ".$this->TB_PRE."plane as PL_TB on FT_TB.机号 = PL_TB.机号 where FT_TB.机号 in ".$INCase.$rqSql1."   group by FT_TB.[机型],FT_TB.机号,上次翻修日期,自开始空地时间,
          自开始空中时间,
          自开始起落次数 order by FT_TB.机号",$ParamArr);



        if(!empty($Ret)){
            $MHJ_i = 1 ;
            $JWC_j = 1 ;
            $MHJ_Plane_Arr  = $MHJ_Plane_MB_Reader->setActiveSheetIndex(0)->toArray();
            $JWC_Plane_Arr  = $JWC_Plane_MB_Reader->setActiveSheetIndex(0)->toArray();
            foreach ($Ret as $k =>$v){
                $JH = $v['JH'];
                //将FH_ZD自带飞行小时与FH_INTB相加，得到真正的空中时间
                $Ret[$k]['FH_TSN'] =$this->AddFHTime($v['FH_INTB'],$v['FH_ZD']);
                //将FGH_ZD自带飞行小时与自新飞行小时相加，得到真正的空地时间
                $Ret[$k]['FGH_TSN']=$this->AddFHTime($v['DMH_INTB'],$Ret[$k]['FH_INTB']);
                $Ret[$k]['FGH_TSN']=$this->AddFHTime($v['FGH_ZD'],$Ret[$k]['FGH_TSN']);
                //将QL_ZD自带起落次数与ZCQL_INTB相加
                $Ret[$k]['QL_TSN']=$this->AddFHTime($v['ZCQL_INTB'],$v['QL_ZD']);
                //将循环次数与QL_ZD相加，得到自新循环次数
                $Ret[$k]['FC_TSN']=$this->AddFHTime($v['FC_INTB'],$v['QL_ZD']);

                $Ret[$k]['FH_TSO'] = $Ret1[$JH]['FH_TSO'];
                $Ret[$k]['FGH_TSO'] = $Ret1[$JH]['FGH_TSO'];
                $Ret[$k]['QL_TSO'] = $Ret1[$JH]['ZCQL_TSO'];
                $Ret[$k]['FC_TSO'] = $Ret1[$JH]['FC_TSO'];

                $Ret[$k]['FH_INQR'] = $Ret2[$JH]['FH_INQR'];
                $Ret[$k]['FGH_INQR'] = $Ret2[$JH]['FGH_INQR'];
                $Ret[$k]['QL_INQR'] = $Ret2[$JH]['QL_INQR'];
                $Ret[$k]['FC_INQR'] = $Ret2[$JH]['FC_INQR'];


                //民航局报表数据
                $MHJ_Plane_Arr[$MHJ_i][0] ='B-'.$this->FakeJH($v['JH']) ;//机号
                $MHJ_Plane_Arr[$MHJ_i][1] = $this->FakeJX($v['JX']) ;//机型
                $MHJ_Plane_Arr[$MHJ_i][2] = '中国民用航空飞行学院';//运营人
                $MHJ_Plane_Arr[$MHJ_i][3] = '91';//运行种类
                $MHJ_Plane_Arr[$MHJ_i][4] = '0 ';//运营本月飞行时间
                $MHJ_Plane_Arr[$MHJ_i][5] = '0 ';//运营本月自修理飞行时间
                $MHJ_Plane_Arr[$MHJ_i][6] = '0 ';//运营本月飞行总时间
                $MHJ_Plane_Arr[$MHJ_i][7] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_INQR']));//空中飞行本月时间
                $MHJ_Plane_Arr[$MHJ_i][8] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_TSO']));//空中飞行自修理时间
                $MHJ_Plane_Arr[$MHJ_i][9] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_TSN']));//空中飞行总时间
                $MHJ_Plane_Arr[$MHJ_i][10] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_INQR']));//空地飞行本月时间
                $MHJ_Plane_Arr[$MHJ_i][11] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_TSO']));//空地飞行自修理时间
                $MHJ_Plane_Arr[$MHJ_i][12] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_TSN']));//空地飞行总时间
                $MHJ_Plane_Arr[$MHJ_i][13] = '0 ';//运营本月次数
                $MHJ_Plane_Arr[$MHJ_i][14] = '0 ';//运营本月自修理次数
                $MHJ_Plane_Arr[$MHJ_i][15] = '0 ';//运营总次数
                $MHJ_Plane_Arr[$MHJ_i][16] = $this->NumFormat($Ret[$k]['QL_INQR'],0);//正常起落本月次数
                $MHJ_Plane_Arr[$MHJ_i][17] = $this->NumFormat($Ret[$k]['QL_TSO'],0);//正常起落自修理次数
                $MHJ_Plane_Arr[$MHJ_i][18] = $this->NumFormat($Ret[$k]['QL_TSN'],0);//正常起落总次数

                $ii =0;
                for($ii=19;$ii<=62;$ii++){
                    $MHJ_Plane_Arr[$MHJ_i][$ii] = '0 ';
                }
                $MHJ_Plane_Arr[$MHJ_i][56] = '否';
                $MHJ_Plane_Arr[$MHJ_i][63] = 'XXX';
                $MHJ_Plane_Arr[$MHJ_i][64] = '0';


                $MHJ_i++;


                //机务处报表数据
                $JWC_Plane_Arr[$JWC_j][0] = $JWC_N ;//年
                $JWC_Plane_Arr[$JWC_j][1] = $JWC_Y ;//月
                $JWC_Plane_Arr[$JWC_j][2] = 'B-'.$this->FakeJH($v['JH']);//机号
                $JWC_Plane_Arr[$JWC_j][3] = $this->FakeJX($v['JX']);//机型
                $JWC_Plane_Arr[$JWC_j][4] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_INQR']));//空中飞行时间
                $JWC_Plane_Arr[$JWC_j][5] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_TSO']));//自修理空中时间
                $JWC_Plane_Arr[$JWC_j][6] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FH_TSN']));//自新空中时间
                $JWC_Plane_Arr[$JWC_j][7] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_INQR']));//空地飞行时间
                $JWC_Plane_Arr[$JWC_j][8] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_TSO']));//自修理空地时间
                $JWC_Plane_Arr[$JWC_j][9] = $this->NumFormat($this->TranslateFHShow($Ret[$k]['FGH_TSN']));//自新空地时间
                $JWC_Plane_Arr[$JWC_j][10] = $this->NumFormat($Ret[$k]['QL_INQR'],0);//正常起落次数
                $JWC_Plane_Arr[$JWC_j][11] = $this->NumFormat($Ret[$k]['QL_TSO'],0);//自修理后起落次数
                $JWC_Plane_Arr[$JWC_j][12] = $this->NumFormat($Ret[$k]['QL_TSN'],0);//自新起落次数
                $JWC_j++;
            }

            $MHJ_Plane_Arr[$MHJ_i][0] = 'END';

            $MHJ_File = "FHOUT/".date('YmdHis').rand(100,999).".xls";
            $JWC_File = "FHOUT/".date('YmdHis').rand(100,999).".xls";

            $MHJ_Plane_MB_Reader->getActiveSheet()->fromArray($MHJ_Plane_Arr);
            $objWriter = new \PHPExcel_Writer_Excel5($MHJ_Plane_MB_Reader);
            $MHJ_File = "FHOUT/民航局格式_飞机_".rand(100,999).".xls";
            $objWriter->save($MHJ_File);

            $JWC_Plane_MB_Reader->getActiveSheet()->fromArray($JWC_Plane_Arr);
            $objWriter = new \PHPExcel_Writer_Excel5($JWC_Plane_MB_Reader);
            $JWC_File = "FHOUT/机务处格式_飞机_".rand(100,999).".xls";
            $objWriter->save($JWC_File);

        }

        $this->assign('PlaneInfoList',$Ret);



        }else {//发动机
            //发动机安装后
            $MHJ_i = 1 ;
            $JWC_j = 1 ;
            $MHJ_Eng_Arr  = $MHJ_Eng_MB_Reader->setActiveSheetIndex(0)->toArray();
            $JWC_Eng_Arr  = $JWC_Eng_MB_Reader->setActiveSheetIndex(0)->toArray();
         $Ret_INQR =  db()->query("SELECT 
                  FT_TB.[机号],
                  型号,
                  序号 as EngXH,
                  安装日期,
                  安装位置,
                  自开始空中时间,
                   翻修后空中时间,
                  自开始热循环次数,
                  sum(case  when 连续起落>=0 AND 正常起落>=0 then 连续起落+正常起落 end ) as FC_INQR,
                  (round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1) + sum(round(空中时间,0,1)))+(sum(([空中时间]-round([空中时间],0,1))/0.6) - round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1))*0.6 as FH_INQR 
                  FROM ".$this->TB_PRE."[flight] as FT_TB join ".$this->TB_PRE."onengine as Eng_TB on FT_TB.机号 = Eng_TB.机号 where  FT_TB.日期 >=Eng_TB.安装日期 AND FT_TB.机号 in ".$INCase.$rqSql1.$rqSql2." group by 
                  FT_TB.机号, 
                  型号,
                  序号,
                   安装日期,
                  安装位置,
                  自开始空中时间,
                   翻修后空中时间,
                  自开始热循环次数
                   order by FT_TB.机号",$ParamArr1);

            if(!empty($Ret_INQR)){
                $Ret_INQR = $this->TranArrToJHKey($Ret_INQR,'EngXH');
            }


            $Ret_TSI =  db()->query("SELECT 
                  FT_TB.[机号] as JH,
                  FT_TB.[机型] as JX,
                  型号 as EngPN,
                  序号 as EngXH,
                  安装日期 as INS_Date,
                  安装位置 as INS_POS,
                  自开始空中时间 ,
                   翻修后空中时间,
                  自开始热循环次数,
                  翻修后热循环次数,
                  sum(case  when 连续起落>=0 AND 正常起落>=0 then 连续起落+正常起落 end ) as FC_INTB,
                  (round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1) + sum(round(空中时间,0,1)))+(sum(([空中时间]-round([空中时间],0,1))/0.6) - round(sum(([空中时间]-round([空中时间],0,1))/0.6),0,1))*0.6 as FH_INTB 
                  FROM ".$this->TB_PRE."[flight] as FT_TB join ".$this->TB_PRE."onengine as Eng_TB on FT_TB.机号 = Eng_TB.机号 where  FT_TB.日期 >=Eng_TB.安装日期 AND FT_TB.机号 in ".$INCase.$rqSql1." group by 
                  FT_TB.机号,
                  FT_TB.机型,
                  型号,
                  序号, 
                   安装日期,
                  安装位置,
                  自开始空中时间,
                   翻修后空中时间,
                  自开始热循环次数,
                  翻修后热循环次数
                   order by FT_TB.机号",$ParamArr);
            //dump($Ret_TSI);
            if(!empty($Ret_TSI)){
                foreach ($Ret_TSI as $k=>$v){
                    $EngXH = $v['EngXH'];
                    //自新FH
                    $Ret_TSI[$k]['FH_TSN'] = $this->AddFHTime($v['FH_INTB'], $v['自开始空中时间']);
                    //自新循环
                    $Ret_TSI[$k]['FC_TSN'] = intval($v['FC_INTB']) + (intval($v['自开始热循环次数'])<0?0:intval($v['自开始热循环次数']));
                    //查询区间内FC
                    $Ret_TSI[$k]['FC_INQR'] = $Ret_INQR[$EngXH]['FC_INQR'];
                    //查询区间内FH
                    $Ret_TSI[$k]['FH_INQR'] = $Ret_INQR[$EngXH]['FH_INQR'];
                    //翻修后FH
                    $Ret_TSI[$k]['FH_TSO'] = $this->AddFHTime($v['FH_INTB'], $v['翻修后空中时间']);
                    //翻修后FC
                    $Ret_TSI[$k]['FC_TSO'] = intval($v['FC_INTB'])+(intval($v['翻修后热循环次数'])<0?0:intval($v['翻修后热循环次数']));

                    $Ret_TSI[$k]['INS_Date'] = date('Y-m-d',strtotime($v['INS_Date']));


                    //民航局报表数据
                    $MHJ_Eng_Arr[$MHJ_i][0] = $v['EngXH'] ;//发动机序号
                    $MHJ_Eng_Arr[$MHJ_i][1] = '装机' ;//装机
                    $MHJ_Eng_Arr[$MHJ_i][2] = '中国民用航空飞行学院' ;
                    $MHJ_Eng_Arr[$MHJ_i][3] = '91' ;



                    $MHJ_Eng_Arr[$MHJ_i][4] = 'B-'.$this->FakeJH($v['JH']) ;//装机机号
                    $MHJ_Eng_Arr[$MHJ_i][5] = trim($v['INS_POS'])=='右'?'2':'1';//装机位置
                    $MHJ_Eng_Arr[$MHJ_i][6] = '无拆换';
                    $MHJ_Eng_Arr[$MHJ_i][7] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_INQR']));//本月空中时间
                    $MHJ_Eng_Arr[$MHJ_i][8] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_TSO']));//自修理空中时间
                    $MHJ_Eng_Arr[$MHJ_i][9] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_TSN']));//自开始空中时间
                    $MHJ_Eng_Arr[$MHJ_i][10] = $this->NumFormat($Ret_TSI[$k]['FC_INQR'],0);//本月次数
                    $MHJ_Eng_Arr[$MHJ_i][11] = $this->NumFormat($Ret_TSI[$k]['FC_TSO'],0);//自修理次数
                    $MHJ_Eng_Arr[$MHJ_i][12] = $this->NumFormat($Ret_TSI[$k]['FC_TSN'],0);//自开始次数
                    $ii =0;
                    for($ii=13;$ii<=25;$ii++){
                        $MHJ_Eng_Arr[$MHJ_i][$ii] = '0 ';
                    }
                    $MHJ_Eng_Arr[$MHJ_i][26] ='否';
                    $MHJ_Eng_Arr[$MHJ_i][27] ='0 ';
                    $MHJ_Eng_Arr[$MHJ_i][28] ='0 ';
                    $MHJ_Eng_Arr[$MHJ_i][29] ='XXXXX';


                    $MHJ_i++;

                    //机务处报表数据
                    $JWC_Eng_Arr[$JWC_j][0] = $v['EngXH'];//序号
                    $JWC_Eng_Arr[$JWC_j][1] = $JWC_N ;//年
                    $JWC_Eng_Arr[$JWC_j][2] = $JWC_Y;//月
                    $JWC_Eng_Arr[$JWC_j][3] = $this->FakeJX($v['JX']);//机型
                    $JWC_Eng_Arr[$JWC_j][4] = $Ret_TSI[$k]['EngPN'];//发动机型号
                    $JWC_Eng_Arr[$JWC_j][5] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_INQR']));//当月时间
                    $JWC_Eng_Arr[$JWC_j][6] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_TSN']));//自开始时间
                    $JWC_Eng_Arr[$JWC_j][7] = $this->NumFormat($this->TranslateFHShow($Ret_TSI[$k]['FH_TSO']));//自修理时间
                    $JWC_Eng_Arr[$JWC_j][8] = $this->NumFormat($Ret_TSI[$k]['FC_INQR'],0);//当月循环
                    $JWC_Eng_Arr[$JWC_j][9] = $this->NumFormat($Ret_TSI[$k]['FC_TSN'],0);//自开始循环
                    $JWC_Eng_Arr[$JWC_j][10] =$this->NumFormat($Ret_TSI[$k]['FC_TSO'],0);//自修理循环
                    $JWC_Eng_Arr[$JWC_j][11] = 'B-'.$this->FakeJH($v['JH']);//装机机号
                    $JWC_Eng_Arr[$JWC_j][12] = $Ret_TSI[$k]['INS_POS'];//装机位置
                    $JWC_Eng_Arr[$JWC_j][13] = '装机';//状态
                    $JWC_j++;

                }
            }
            $this->assign('EngInfo',$Ret_TSI);
            $MHJ_Eng_Arr[$MHJ_i][0] = 'END';
            $MHJ_File = "FHOUT/".date('YmdHis').rand(100,999).".xls";
            $JWC_File = "FHOUT/".date('YmdHis').rand(100,999).".xls";

            $MHJ_Eng_MB_Reader->getActiveSheet()->fromArray($MHJ_Eng_Arr);
            $objWriter = new \PHPExcel_Writer_Excel5($MHJ_Eng_MB_Reader);
            $MHJ_File = "FHOUT/民航局格式_发动机_".rand(100,999).".xls";
            $objWriter->save($MHJ_File);

            $JWC_Eng_MB_Reader->getActiveSheet()->fromArray($JWC_Eng_Arr);
            $objWriter = new \PHPExcel_Writer_Excel5($JWC_Eng_MB_Reader);
            $JWC_File = "FHOUT/机务处格式_发动机_".rand(100,999).".xls";
            $objWriter->save($JWC_File);
        }
        $this->assign("showDowload","YES");
        $this->assign('MHJ_FILE','/'.$MHJ_File);
        $this->assign('JWC_FILE','/'.$JWC_File);

        OUT:

    }

    public function GetAirPlaneFHInfo($RegNOs){//获取飞机的FH修后FH等数据
        $AirPlane =  db()->query('SELECT * FROM [172.16.65.149].jwb.dbo.plane WHERE 机号=?',array($RegNOs))[0];
    }

    public function GetAllModelPlanes(){

        $this->assign("SR20List" ,db()->query("SELECT 机型 ,机号 as JH FROM [172.16.65.149].jwb.dbo.Plane WHERE 机型 = 'SR20'")) ;
        $this->assign("LE500List" ,db()->query("SELECT 机型,机号 as JH FROM [172.16.65.149].jwb.dbo.Plane WHERE 机型 = 'LE500'")) ;
        $this->assign("C172List" ,db()->query("SELECT 机型,机号 as JH FROM [172.16.65.149].jwb.dbo.Plane WHERE 机型 = '172R'")) ;
        $this->assign("PA44List" ,db()->query("SELECT 机型,机号 as JH FROM [172.16.65.149].jwb.dbo.Plane WHERE 机型 = 'PA44'")) ;
        $this->assign("MA600List" ,db()->query("SELECT 机型,机号 as JH FROM [172.16.65.149].jwb.dbo.Plane WHERE 机型 = 'MA600'")) ;

        $this->assign('S20Cnt',1);
        $this->assign('LE500Cnt',1);
        $this->assign('PA44Cnt',1);
        $this->assign('C172Cnt',1);
        $this->assign('MA600Cnt',1);

    }
}
