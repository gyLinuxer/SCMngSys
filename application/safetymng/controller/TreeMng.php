<?php
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2019/4/30
 * Time: 07:06
 */
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;
class TreeMng extends PublicController{
    public function index(){

        $this->assign('TreeList',db()->query("SELECT * FROM Trees WHERE  ParentNodeCode = '0' AND IsDeleted = '否'"));
        return view('index');
    }

    public function  GetValidNodeCode($ParentNodeCode){
        if($ParentNodeCode==''){
            return '';
        }
        //检查父节点是否存在
        if($ParentNodeCode!='0'){//不是添加根节点
            $Ret = db('Trees')->where(array('NodeCode'=>$ParentNodeCode))->select();
            if(empty($Ret)){
                return '';
            }
        }

        //查看该父节点下子节点个数
        $Ret =  db('Trees')->where(array('ParentNodeCode'=>$ParentNodeCode
        ))->select();

        $Cnt = count($Ret);
        do{//检查节点编号重复
            $Code = ($ParentNodeCode=='0'?'':$ParentNodeCode).sprintf("%04d",$Cnt);
            $Ret = db('Trees')->where(array('NodeCode'=>$Code))->select();
            $Cnt++;
        }while(!empty($Ret));

        return $Code;
    }
    //增加子节点
    public function AddTreeNode(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $ParentCode = $PostData_Arr['ParentCode'];
        $NodeName = $PostData_Arr['NewNodeName'];
        $Ret['Ret'] = 'Failed';

        if(empty($NodeName)){
            $Ret['msg']  = '节点名称不可为空!';
            goto OUT;
        }

        $db_Ret = db()->query('SELECT * FROM Trees WHERE NodeCode = ? ',array($ParentCode));
        if(empty($db_Ret)){
            $Ret['msg']  = '父节点不存在!';
            goto OUT;
        }

        $ParentNode_Ret = db()->query('SELECT * FROM Trees WHERE NodeCode = ? ',array($ParentCode));
        $db_Ret = db()->query('SELECT * FROM Trees WHERE ParentNodeCode = ? AND NodeName=? ',array($ParentCode,$NodeName));
        if(!empty($db_Ret)){//如果有同名节点，则看其是否应被删除
            if($db_Ret[0]['isDeleted']=='是'){//重新启用被删除掉的节点
                //反删除
                db()->query("UPDATE Trees SET UsedCnt=UsedCnt+1,isDeleted ='否',AdderName=?,AddTime=? WHERE NodeCode = ?",
                    array(session('Name'),date('Y-m-d H:i:s'),$db_Ret[0]['NodeCode']));
                $Ret['Ret'] = 'success';
                $Ret['NodeCode'] = $db_Ret[0]['NodeCode'];
                $Ret['ParentNodeCode'] = $db_Ret[0]['ParentNodeCode'];
                $Ret['NodeName'] = $db_Ret[0]['NodeName'];

            }else{//节点名称重复
                $Ret['msg']  = '该节点名已经存在!';
                goto OUT;
            }
        }else{//节点名不存在
            if(empty($ParentNode_Ret)){//父节点不存在!
                $Ret['msg']  = '父节点不存在!';
                goto  OUT;
            }
            $NewNodeCode = $this->GetValidNodeCode($ParentCode);
            $data =array();
            $data['NodeName'] = $NodeName;
            $data['NodeCode'] = $NewNodeCode;
            $data['ParentNodeCode'] = $ParentCode;
            $data['TreeCode'] = $ParentNode_Ret[0]['TreeCode'];
            $data['AddTime']  = date('Y-m-d H:i:s');
            $data['AdderName']= session('Name');
            $data['isDeleted']= '否';

            db('Trees')->insert($data);
            $Ret['Ret'] = 'success';
            $Ret['NodeCode'] = $NewNodeCode;
            $Ret['ParentNodeCode'] = $ParentCode;
            $Ret['NodeName'] = $NodeName;
        }

        OUT:
            return json($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function DelTreeNode(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $NodeCode = $PostData_Arr['NodeCode'];
        $Ret['Ret'] = 'Failed';
        //先看结点是否存在
        $Node_Ret = db('Trees')->where(array('NodeCode'=>$NodeCode,'isDeleted'=>'否'))->select()[0];
        if(empty($Node_Ret)){
            $Ret['msg'] = '要删除的节点已经不存在了!';
            goto OUT;
        }

        //查看该节点是否是父节点
        $db_Ret = db()->query("SELECT * FROM Trees WHERE ParentNodeCode = ? AND isDeleted = '否'",array($NodeCode));
        if(!empty($db_Ret)){
            $Ret['msg'] = '请删除所有子节点后再删除本节点!';
            goto OUT;
        }

        db('Trees')->where(array('NodeCode'=>$NodeCode))->delete();
        //删除子节点后，所有贴着该子节点的项目，将其标签转贴为父节点
        db('LabelCrossIndex')->query('UPDATE LabelCrossIndex SET NodeCode = ? WHERE NodeCode = ?',array($Node_Ret['ParentNodeCode'],$NodeCode));
        $Ret['Ret'] = 'success';

        OUT:
            return json($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function renameTreeNode(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $NodeCode = $PostData_Arr['NodeCode'];
        $NewName = trim($PostData_Arr['NewNodeName']);
        $Ret['Ret'] = 'Failed';

        if(empty($NewName)){
            $Ret['msg'] = '节点名不可为空!';
            goto OUT;
        }

        //先看节点存在不，再看名字是否改变，以及是否和未删除节点名称重复
        $db_Ret = db('Trees')->where(array('NodeCode'=>$NodeCode,'isDeleted'=>'否'))->select();
        if(empty($db_Ret)){
            $Ret['Ret'] = '节点不存在!';
            goto OUT;
        }

        $db_Ret = db('Trees')->where(array('ParentNodeCode'=>$db_Ret[0]['ParentNodeCode'],'NodeName'=>$NewName,'isDeleted'=>'否'))->select();
        if(!empty($db_Ret)){
            if($db_Ret[0]['NodeCode']==$NodeCode && count($db_Ret)==1){//名字没变
                $Ret['Ret'] = 'success';
                goto OUT;
            }else{
                $Ret['msg'] = '该名称已经存在!';
                goto OUT;
            }
        }


        //开始重新命名
        $data['NodeName']= $NewName;
        $data['AddTime'] = date('Y-m-d H:i:s');
        $data['AdderName'] = session('Name');
        db('Trees')->where(array('NodeCode'=>$NodeCode))->update($data);
        $Ret['Ret'] = 'success';

        OUT:
            return json($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function AddTree(){
        $TreeName = trim(input('TreeName'));

        if(empty($TreeName)){
            $this->assign('Warning','树名称不可为空!');
            goto OUT;
        }

        //查看根节点是否已经存在
        $Ret= db('Trees')->where(array('NodeName'=>$TreeName,
                                              'IsDeleted'=>'否'))->select();
        if(!empty($Ret)){
            $this->assign('Warning','该标签树已经存在!');
            goto OUT;
        }

        $data['NodeName'] = $TreeName;
        $data['ParentNodeCode'] = '0';
        $data['AdderName'] = session('Name');
        $data['AddTime'] = date('Y-m-d H:i:s');
        $data['isDeleted'] = '否';
        $data['NodeCode'] = $this->GetValidNodeCode('0');

        if($data['NodeCode']==''){
            $this->assign('Warning','获取节点代码失败!');
            goto OUT;
        }

        $data['TreeCode'] = $data['NodeCode'];


        db('Trees')->insert($data);


        OUT:
            return $this->index();
    }

    public function GetTreeNodeJsonData(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $TreeRootCode = $PostData_Arr['TreeCode'];
        $SubjectType = $PostData_Arr['SubjectType'];
        $SubjectID   = $PostData_Arr['SubjectID'];
        $TreeName = $PostData_Arr['TreeName'];
        $Ret['Ret'] = 'Failed';
        $Ret['RootCode'] = $TreeRootCode;
        $Ret['Ret'] = 'Success';

        if(!empty($SubjectID) && !empty($SubjectType)){
            //要关联项目，预选节点
            $Ret_Arr = $this->GetRealLabelTypeAndID($SubjectType,$SubjectID);
            if(!empty($TreeRootCode)){
                $Data_Ret = db()->query("SELECT Trees.NodeCode as id ,ParentNodeCode as pId,NodeName  as name,Trees.TreeCode, (case   when LabelCrossIndex.NodeCode IS NOT NULL THEN 'true' else 'false' END) as checked FROM Trees LEFT JOIN LabelCrossIndex ON Trees.NodeCode = LabelCrossIndex.NodeCode AND LabelCrossIndex.SubjectType =? AND LabelCrossIndex.SubjectID = ?  AND LabelCrossIndex.IsValid='YES' WHERE 
                                          Trees.TreeCode = ? AND isDeleted = '否' ",array($Ret_Arr['RealSubjectType'],$Ret_Arr['RealSubjectID'],$TreeRootCode));
            }else{
                $Data_Ret = db()->query("SELECT Trees.NodeCode as id ,ParentNodeCode as pId,NodeName  as name,Trees.TreeCode, (case   when LabelCrossIndex.NodeCode IS NOT NULL THEN 'true' else 'false' END) as checked   FROM Trees LEFT JOIN LabelCrossIndex ON Trees.NodeCode = LabelCrossIndex.NodeCode AND LabelCrossIndex.SubjectType =? AND LabelCrossIndex.SubjectID = ? AND LabelCrossIndex.IsValid='YES' WHERE 
                                          Trees.TreeCode = (SELECT TreeCode FROM Trees WHERE NodeName = ? ) AND isDeleted = '否' ",array($Ret_Arr['RealSubjectType'],$Ret_Arr['RealSubjectID'],$TreeName));
                $TreeRootCode = $Data_Ret[0]['TreeCode'];
            }
        }else{
            if(!empty($TreeRootCode)){
                $Data_Ret = db()->query("SELECT NodeCode as id ,ParentNodeCode as pId,NodeName  as name,TreeCode FROM Trees WHERE 
                                          Trees.TreeCode = ? AND isDeleted = '否' ",array($TreeRootCode));
            }else{
                $Data_Ret = db()->query("SELECT NodeCode as id ,ParentNodeCode as pId,NodeName  as name,TreeCode  FROM Trees WHERE 
                                          Trees.TreeCode = (SELECT TreeCode FROM Trees WHERE NodeName = ? ) AND isDeleted = '否' ",array($TreeName));
                $TreeRootCode = $Data_Ret[0]['TreeCode'];
            }
        }

         $Ret['Sql'] = db()->getLastSql();
         $Ret['TreeName'] = $TreeName;
         $Ret['TreeCode'] = $TreeRootCode;
         $Ret['data'] = $Data_Ret;

        return json($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function showLabelSubject($Type='',$SubjectID=NULL){

        if(empty($Type) || empty($SubjectID)){
            return '请选择您要粘贴标签的任务、问题或整改通知书!';
        }
        $this->assign('Type',$Type);
        $this->assign('SubjectID',$SubjectID);
        $this->assign('TreeList',db()->query("SELECT * FROM Trees WHERE  ParentNodeCode = '0' AND IsDeleted = '否'"));
        return view('LabelSubject');
    }

    public function GetRealLabelTypeAndID($SubjectType = '',$SubjectID = NULL){//返回值中$Ret['RealSubjectID']=0证明出错
        $Ret = array();
        $Arr = array('Task'=>'TaskList','Qs'=>'QuestionList','RF'=>'ReformList');
        $RealType_Tab = $Arr[$SubjectType];
        if(empty($RealType_Tab)){
            goto OUT;
        }

        $Ret['RealSubjectType'] = $SubjectType;
        $Ret['RealSubjectID']   = $SubjectID;
        $Ret['RealSubject_Tab']   = $RealType_Tab;

        if($SubjectType  == 'Task'){
            $db_Ret = db('TaskList')->where(array('id'=>$SubjectID))->select()[0];
            if(empty($db_Ret)){
                $Ret['RealSubjectID'] = 0;
                goto OUT;
            }
            switch ($db_Ret['TaskType']){
                case TaskCore::QUESTION_SUBMITED:
                case TaskCore::QUESTION_REFORM:{
                    $Ret['RealSubjectType'] = 'Qs';
                    $Ret['RealSubject_Tab'] = $Arr[$Ret['RealSubjectType']];
                    break;
                }
                case TaskCore::REFORM_SUBTASK:
                case TaskCore::QUESTION_FAST_REFORM:{
                    $Ret['RealSubjectType'] = 'RF';
                    $Ret['RealSubject_Tab'] = $Arr[$Ret['RealSubjectType']];
                    break;
                }
            }

            $Ret['RealSubjectID'] = $db_Ret['RelateID'];
            $t_Ret = db($Ret['RealSubject_Tab'])->where(array('id'=>$Ret['RealSubjectID']))->select()[0];
            if(empty($t_Ret)){
                $Ret['RealSubjectID'] = 0;
                goto OUT;
            }
        }

        OUT:
            return $Ret;

    }

    public function AJAX_LabelSubject(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $SubjectId = $PostData_Arr['id'];
        $SubjectType = $PostData_Arr['SubjectType'];
        $NodeCode = $PostData_Arr['NodeCode'];
        $Checked = $PostData_Arr['Checked'];

        $Ret['Ret'] = 'Failed';
        if(empty($SubjectType) || empty($SubjectId)){
            $Ret['msg'] = '要贴标签的项目为空!';
            goto OUT;
        }

        if(empty($NodeCode)){
            $Ret['msg'] = '未选择标签或者选择状态丢失!';
            goto OUT;
        }


        $Ret_Arr = $this->GetRealLabelTypeAndID($SubjectType,$SubjectId);
        $RealType = $Ret_Arr['RealSubjectType'];
        $RealSubjectID = $Ret_Arr['RealSubjectID'];

        if(empty($RealSubjectID) || empty($RealType)){
            $Ret['msg'] = '要粘贴的项目不存在!';
            goto OUT;
        }

        //到这了，项目肯定存在，开始粘贴标签
        $NodeData  = db('Trees')->where(array('NodeCode'=>$NodeCode))->select()[0];
        if(empty($NodeData) || $NodeData['isDeleted']=='是'){
            $Ret['msg'] = '标签不存在!';
            goto OUT;
        }

        $LabeledData = db('LabelCrossIndex')->where(array('NodeCode'=>$NodeCode,
                                                                'TreeCode'=>$NodeData['TreeCode'],
                                                                'SubjectID'=>$RealSubjectID,
                                                                'SubjectType'=>$RealType))->select()[0];


        if($Checked==true){//粘贴
            if(!empty($LabeledData) ){
                if($LabeledData['IsValid']=='YES'){
                    $Ret['msg'] = '标签已存在!';
                    goto OUT;
                }else{//标签存在，但是已被标记为删除
                    $data['AdderName'] = session('Name');
                    $data['AddTime'] = date('Y-m-d H:i:s');
                    $data['IsValid'] = 'YES';
                }
                db('LabelCrossIndex')->where(array('SubjectID'=>$RealSubjectID,
                    'SubjectType'=>$RealType,'NodeCode'=>$NodeCode,'TreeCode'=>$NodeData['TreeCode']))->update($data);
            }else{//标签不存在!
                $data['SubjectType'] = $RealType;
                $data['SubjectID'] = $RealSubjectID;
                $data['TreeCode'] = $NodeData['TreeCode'];
                $data['NodeCode'] = $NodeCode;
                $data['AdderName'] = session('Name');
                $data['AddTime'] = date('Y-m-d H:i:s');
                $data['IsValid'] = 'YES';
                db('LabelCrossIndex')->insert($data);
            }
            $Ret['AdderName'] = $data['AdderName'];
            $Ret['AddTime'] = $data['AddTime'];
            $Ret['Ret'] = 'success';
            $Ret['msg'] = '标签粘贴成功!';
            $Ret['opt'] = 'add';

        }else{//撕掉

            if(empty($LabeledData)) {
                $Ret['msg'] = '要撕毁的标签不存在!';
                goto OUT;
            }else if($LabeledData['IsValid'] !='YES'){
                $Ret['msg'] = '标签已经被撕毁!';
                goto OUT;
            }

            $data['IsValid'] = 'NO';
            $data['DelerName'] = session('Name');
            $data['DelTime'] = date('Y-m-d H:i:s');

            db('LabelCrossIndex')->where(array('SubjectID'=>$RealSubjectID,
                                                     'SubjectType'=>$RealType,
                                                      'NodeCode'=>$NodeCode,
                                                      'TreeCode'=>$NodeData['TreeCode']))->update($data);
            $Ret['Ret'] = 'success';
            $Ret['msg'] = '标签撕毁成功!';
            $Ret['opt'] = 'rm';
        }


        OUT:
            return json($Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function AJAX_GetSubjectLabeledTreeNodes(){
        $PostData_Arr = json_decode(file_get_contents('php://input'),true);
        $SubjectId   = $PostData_Arr['id'];
        $SubjectType = $PostData_Arr['SubjectType'];
        $TreeCode = $PostData_Arr['TreeCode'];
        $Json_Ret['Ret'] = 'Failed';

        if(empty($SubjectId)||empty($SubjectType)){
            $Json_Ret['msg']  = '项目类型和项目id为空';
            goto OUT;
        }


        $RealSubjectID = $SubjectId;
        $RealType = $SubjectType;
        //如果是任务，则要找出任务关联的整改通知书或者问题粘贴标签
        if($RealType  == 'Task'){
            $db_Ret = db('TaskList')->where(array('id'=>$RealSubjectID))->select()[0];
            if(empty($db_Ret)){
                $Ret['msg'] = '项目不存在!';
                goto OUT;
            }
            switch ($db_Ret['TaskType']){
                case TaskCore::QUESTION_SUBMITED:
                case TaskCore::QUESTION_REFORM:{
                    $RealType = 'Qs';
                    break;
                }
                case TaskCore::REFORM_SUBTASK:
                case TaskCore::QUESTION_FAST_REFORM:{
                    $RealType = 'RF';
                    break;
                }
            }

            $RealSubjectID = $db_Ret['RelateID'];
        }

        if(empty($TreeCode)){
            $Nodes = db()->query("SELECT Trees.NodeName,LabelCrossIndex.*,LabelCrossIndex.AdderName,LabelCrossIndex.AddTime,FTree.NodeName as TreeName FROM LabelCrossIndex JOIN Trees ON Trees.NodeCode = LabelCrossIndex.NodeCode 
                        JOIN Trees as FTree on LabelCrossIndex.TreeCode=FTree.NodeCode   WHERE LabelCrossIndex.IsValid ='YES' AND SubjectID = ? AND SubjectType = ? ORDER  BY LabelCrossIndex.TreeCode",array($RealSubjectID,$RealType));
        }else{
            $Nodes = db()->query("SELECT Trees.NodeName,LabelCrossIndex.*,LabelCrossIndex.AdderName,LabelCrossIndex.AddTime,FTree.NodeName as TreeName FROM LabelCrossIndex JOIN Trees ON Trees.NodeCode = LabelCrossIndex.NodeCode 
                        JOIN Trees as FTree on LabelCrossIndex.TreeCode=FTree.NodeCode   WHERE LabelCrossIndex.IsValid ='YES' AND SubjectID = ? AND SubjectType = ? AND LabelCrossIndex.TreeCode = ? ORDER  BY LabelCrossIndex.TreeCode",array($RealSubjectID,$RealType,$TreeCode));
        }
        $Json_Ret['Ret'] = 'success';
        $Json_Ret['data'] = $Nodes;
        OUT:
            return json($Json_Ret,JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    }

    public function GetSubjectLabels($SubjectType,$SubjectID,$isJson = 'NO'){
        $Arr = $this->GetRealLabelTypeAndID($SubjectType,$SubjectID);
        $Ret = '';
        $RealSubjectType = $Arr['RealSubjectType'];
        $RealSubjectID = $Arr['RealSubjectID'];
        if(empty($RealSubjectType) || empty($RealSubjectID)){
            goto OUT;
        }
        $Ret = db()->query("SELECT NodeName FROM Trees JOIN LabelCrossIndex ON Trees.NodeCode = LabelCrossIndex.NodeCode 
          AND LabelCrossIndex.SubjectType = ? AND LabelCrossIndex.SubjectID = ? AND LabelCrossIndex.IsValid = 'YES' ",array($RealSubjectType,$RealSubjectID));

        OUT:
            if($isJson=='NO'){
                return $Ret;
            }else{
                return json($Ret);
            }
    }

    function showLabelSelectForCalc($CurLabelForCalcListJsVarName = NULL){
        if(empty($CurLabelForCalcListJsVarName)){
            return '请为CurLabelForCalcListJsVarName参数赋值';
        }
        $this->assign('TreeList',db()->query("SELECT * FROM Trees WHERE  ParentNodeCode = '0' AND IsDeleted = '否'"));
        $this->assign('CurLabelForCalcListJsVarName',$CurLabelForCalcListJsVarName);
        return view('LabelSelectForCalc');
    }




}