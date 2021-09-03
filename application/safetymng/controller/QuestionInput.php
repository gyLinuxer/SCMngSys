<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;
class QuestionInput extends Controller
{
    private  $CorpMng = NULL;
    private  $SysCnf  = NULL;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->CorpMng = new CorpMng();
        $this->SysCnf  = new SysConf();
    }

    function IS_Mobile(){
        $regex_match="/(nokia|iphone|ipad|micromsg|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
        $regex_match.="htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
        $regex_match.="blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
        $regex_match.="symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
        $regex_match.="jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220";
        $regex_match.=")/i";
        return isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE']) or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']));
    }
    public function index($QsSel=NULL,$Platform='PC'){
        $this->assign('UserList',$this->CorpMng->GetGroupCorpUserList(session('CorpInfo')['GroupCorp']));
        $this->assign('QuestionSource',$this->SysCnf->GetQuestionSourceList());
        $this->assign('CorpList',$this->CorpMng->GetAllCorpsInGroupCorp(session('CorpInfo')['GroupCorp']));
        $this->assign('Today',date('Y-m-d'));
        $this->assign('ZFCorpList',$this->CorpMng->GetQsDefaultRecvCorpList());
        $this->assign('QsSel',$QsSel);
        if($this->IS_Mobile() ||$Platform =='Mobile'){
            $this->assign("CurPlatform","Mobile");
            return view('QuestionInput/mbindex');
        }
        return view('index');
    }
    function QuestionInput($DefaultQsRecvCorp=NULL)
    {
        $Title = input('QuestionTitle');
       $Content = input('content');

       if(empty($Title) || empty($Content)){
            $this->assign("Warning","请完善问题标题与描述!");
            goto OUT;
       }

       $data["QuestionTitle"] = $Title;
       $data["QuestionInfo"]  = htmlspecialchars($Content);
       $data["CreatorName"] = is_null(session("Name"))?'':session("Name");
       $data["CreateTime"] = date("Y-m-d H:i:s");
       $data["RelatedCorp"]  = input('RelatedCorp');
       $data["QuestionSource"]  = input('QuestionSourceName');
       $data["DateFound"]  = input('DateFound');
       $data["DealType"]  ='整改';

       if(empty($DefaultQsRecvCorp)){
           $DefaultQsRecvCorp = input('RecvCorp');
       }

       $data["Basis"]  = input('Basis');
       $data["Finder"]  = input('Finder');

       foreach ($data as $k=>$v){
           if(empty($v)){
               $this->assign("Warning",$k.'不可为空!');
               goto OUT;
           }
       }

       if(empty($DefaultQsRecvCorp)){
           $this->assign("Warning",'问题接收部门不能为空!');
           goto OUT;
       }

       $id = db("QuestionList")->insertGetId($data);
       if($id<=0){
           $this->assign("Warning","输入问题失败!");
           goto OUT;
       }

        $Ret = TaskCore::isTaskCreated(TaskCore::QUESTION_SUBMITED,$id);

        if(empty($Ret)){//没有创建任务
            $TaskData['TaskType'] = TaskCore::QUESTION_SUBMITED;
            $TaskData['TaskName'] = $Title;
            $TaskData['SenderName'] = $data["CreatorName"];
            $TaskData['SenderCorp'] = session("Corp");
            $TaskData['TaskSource'] = $data["QuestionSource"];
            $TaskData['ReciveCorp'] = $DefaultQsRecvCorp;
            $TaskData['RelateID'] = $id;
            $TaskData['CreateTime'] = date('Y-m-d H:i:s');
            $TaskData['CreatorName'] = session("Name");
            $TaskData['ParentID'] = 0;
            $TaskData['TaskType'] = TaskCore::QUESTION_REFORM;//20210723为了废除进入整改分支按钮，生成的整改任务直接为问题-整改
            $TaskData['Status'] = TaskCore::STATUS_UNRECV;
            $CT_Ret =  TaskCore::CreateTask($TaskData);
            if(empty($CT_Ret["ID"])){
                $this->assign("Warning","创建任务失败，原因为:".$CT_Ret["Ret"]);
            }else{
                db('QuestionList')->where(['id'=>$id])->update([
                    'TaskID'=>$CT_Ret["ID"]
                ]);
            }
        }

        $CallBackURL = input('CallBackURL');
        if(!empty($CallBackURL)){
            $this->redirect($CallBackURL.$id);
        }else {
            return $this->showQuestionInfo($id);
        };

       OUT:
            return $this->index();


    }
    public function showQuestionInfo($id = NULL)
    {
        if(!$id){
            return;
        }
        $this->assign("dataRow",db('QuestionList')->where(array("id"=>$id))->select()[0]);
        if($this->IS_Mobile()){
            $this->assign("CurPlatform","Mobile");
            return view('mbFeedBack');
        }
        return view('FeedBack');
    }
    public function showQuestionInfoWithoutLayout($id = NULL){
        if(!$id){
            return;
        }
        $this->assign("dataRow",db('QuestionList')->where(array("id"=>$id))->select()[0]);
        return view('DiscretePCshowQs');
    }
}
