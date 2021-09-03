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
class ExcelMng extends PublicController {

    private $Help;
    private $phpExcel;
    public function __construct(Request $request = null)
    {
        Loader::import('PHPExcel.PHPExcel');
        Loader::import('PHPExcel.PHPExcel.IOFactory.PHPExcel_IOFactory');
        Loader::import('PHPExcel.PHPExcel.Reader.Excel5');
        Loader::import('PHPExcel.PHPExcel.Reader.Excel2007');
        $this->Help = new Help();
        $this->phpExcel = new \PHPExcel();
        parent::__construct($request);
    }

    public function TransArrToExcelDownload($Arr,$fName=''){
        $this->phpExcel->getSheet(0)->fromArray($Arr);
        $objWriter = new \PHPExcel_Writer_Excel5($this->phpExcel);
        $objWriter->save(str_replace('.php', '.xls', __FILE__));
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header("Content-Disposition:attachment;filename=".$fName);
        header("Content-Transfer-Encoding:binary");
        $objWriter->save("php://output");
    }

    public function uploadExcelSheetToArr($SheetIndex=0){
        $SubFile = $this->Help->uploadFile();
        $file = ROOT_PATH.'public'.$SubFile;
        if(empty($SubFile)){
            return '';
        }
        $objReader =  \PHPExcel_IOFactory::createReader ('Excel2007');
        if(strtolower(substr($file,-3))=='xls'){
            $objReader =  \PHPExcel_IOFactory::createReader ('Excel5');
        }
        $Reader = $objReader->load ($file );
        $Arr=$Reader->setActiveSheetIndex($SheetIndex)->toArray();
        return $Arr;
    }

    public function index(){

    }
}