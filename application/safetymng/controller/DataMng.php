<?php
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;


class DataMng extends  PublicController{
    private  $CorpMng = NULL;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->CorpMng = new CorpMng();
    }
    public function index(){
       return '';
    }

    public function GetDataTypeList(){
        $Ret =  db('SysConf',[],false)->where(array(
            'KeyType' => 'DataType',
            'CorpGroup' => $this->GetGroupCorp(),
        ))->order('KeyName')->select();
        return $Ret;
    }

    public function DataUpload(){
        $IsValid_Arr =array('有效'=>'YES','失效'=>'NO');
        $data['DataType'] = input('DataType');
        $data['DataName'] = input('DataName');
        $data['DataCode'] = input('DataCode');
        $data['DataVer'] = input('DataVer');
        $data['Content']  = htmlspecialchars(input('content'));
        $data['AdderName'] = session('Name');
        $data['AddTime'] = date('Y-m-d H:i:s');
        $data['IsValid'] = $IsValid_Arr[input('IsValid')];
        if(empty($data['DataCode'])){
            $data['DataCode'] = 'Data-'.date('YmdHis').rand(1,100);
        }

        foreach ($data as $k=>$v){
            if(empty($v)){
                $Warning = '请完整填写所有要素';
                goto OUT1;
            }
        }

        $bExist = db('Data')->where(array('DataCode'=>$data['DataCode'],'isDeleted'=>'NO'))->find();
        if(!empty($bExist)){
            $Warning = '该文件编号已经存在!';
            goto OUT1;
        }

        $id = db('Data')->insertGetId($data);
        if(!empty($id)){
            $Warning = '资料上传成功^_^ ^_^ ^_^ ^_^ ^_^';
            goto OUT;
        }else{
            $Warning = '资料上传失败!!';
            goto OUT1;
        }

        OUT1:
            $this->assign('FailedData',$data);

        OUT:
            return $this->redirect(url('DataMng/showDataUpload',['Warning'=>$Warning]));
    }

    public function DataQuery(){

        $where['DataType'] = array('like','%'.input('qDataType').'%');
        $where['DataName'] = array('like','%'.input('qDataName').'%');
        $where['DataCode'] = array('like','%'.input('qDataCode').'%');
        $where['DataVer']  = array('like','%'.input('qDataVer').'%');
        $where['AdderName']  = array('like','%'.input('AdderName').'%');
        $where['IsDeleted']  = 'NO';
        $SDate = input('SDate');
        $EDate = input('EDate');
        if(!empty($SDate)){
            $where['AddTime'] = array('EGT',$SDate);
        }
        if(!empty($EDate)){
            $where['AddTime'] = array('ELT',$EDate);
        }


        $Ret = db('Data',[],false)->where($where)->select();
        //dump(db()->getLastSql());
        $this->assign('DataRet',$Ret);
        return $this->showDataQuery(NULL);

    }

    public function showDataPage($id = NULL){
        if(empty($id))
            return;
        $Ret = db('Data',[],false)->where(array('id'=>$id))->select()[0];
        $this->assign('dataRow',$Ret);
        return view('DataPage');
    }

    public function showDelData($id=NULL){
        if(empty($id))
            return;
        $Ret = db('Data',[],false)->where(array('id'=>$id))->select()[0];
        $this->assign('dataRow',$Ret);
        $this->assign('id',$id);
        return view('DelPage');
    }

    public function DelData($id = NULL){
        if(!$this->IsSuperCorp()){
            return '您没有删除权限!';
        }
        if(empty($id)){
            return 'id不可为空!';
        }

        $Ret = db('Data')->where(array('id'=>$id))->update(array('isDeleted'=>'YES'));
        if(empty($Ret)){
            return '删除失败!';
        }else{
            return '删除成功';
        }

    }

    public function showDataQuery($Warning=NULL){
        $this->assign('DataTypeList',$this->GetDataTypeList());
        $this->assign('Warning',$Warning);
        $this->assign('IsSuperCorp',$this->IsSuperCorp());
        return view('DataQuery');
    }

    public function showDataUpload($Warning=NULL){
        $this->assign('DataTypeList',$this->GetDataTypeList());
        $this->assign('Warning',$Warning);
        return view('DataUpload');
    }

    public function showDataRoom($DataType=NULL){

       $DataTypeList =  db()->query("SELECT SysConf.KeyName,count(Data.id) as Cnt FROM SysConf LEFT Join Data ON SysConf.KeyName = Data.DataType 
                 WHERE SysConf.KeyType = 'DataType' AND ( Data.isDeleted = 'NO' OR Data.isDeleted IS NULL ) GROUP by(KeyName) ORDER  BY  KeyName");

        $this->assign('DataTypeList',$DataTypeList);
        $this->assign('DataType',$DataType);
        if(!empty($DataType)){
            $Ret = db('Data',[],false)->where(array(
                'DataType'=>$DataType,
                'IsDeleted'=>'NO'))->order('DataName ASC,AddTime DESC')->select();
            $this->assign('DataRet',$Ret);
        }

        return view('DataRoom');
    }

    public function showDataMdf($id=NULL,$Warning=NULL){
        if(!$this->IsSuperCorp()){
            return '您没有修改权限';
        }
        $this->assign('DataTypeList',$this->GetDataTypeList());
        $dataRow = db('Data')->where(array('id'=>$id))->find();
        $this->assign('FailedData',$dataRow);
        $this->assign('Warning',$Warning);
        $this->assign('DataID',$id);
        return view('DataMdf');
    }

    public function DataMdf(){
        $IsValid_Arr =array('有效'=>'YES','失效'=>'NO','参考资料'=>'CK');
        $id = input('DataID');

        $row = db('Data')->where('id',$id)->find();
        if(empty($row)){
            return '要修订的资料不存在!';
        }

        $data['DataType'] = input('DataType');
        $data['DataName'] = input('DataName');
        $data['DataCode'] = input('DataCode');
        $data['DataVer'] = input('DataVer');
        $data['Content']  = htmlspecialchars(input('content'));
        $data['AdderName'] = session('Name');
        $data['AddTime'] = date('Y-m-d H:i:s');
        $data['IsValid'] = $IsValid_Arr[input('IsValid')];
        if(empty($data['DataCode'])){
            $data['DataCode'] = 'Data-'.date('YmdHis').rand(1,100);
        }

        foreach ($data as $k=>$v){
            if(empty($v)){
                $Warning = '请完整填写所有要素';
                goto OUT1;
            }
        }

        $bExist = db('Data')->where(array('DataCode'=>$data['DataCode'],'isDeleted'=>'NO','id'=>array('NEQ',$id)))->find();
        if(!empty($bExist)){
            $Warning = '该文件编号已经存在!';
            goto OUT1;
        }

        $id = db('Data')->where(array('id'=>$id))->update($data);
        if(!empty($id)){
            $Warning = '资料修订成功^_^ ^_^ ^_^ ^_^ ^_^';
            goto OUT;
        }else{
            $Warning = '资料上传失败!!';
            goto OUT1;
        }

        OUT1:
        $this->assign('FailedData',$data);

        OUT:
        return $this->redirect(url('DataMng/showDataMdf',['Warning'=>$Warning]));
    }


}