<?php
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2018/12/4
 * Time: 20:31
 */
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Log;
use think\Request;

class  Login extends Controller{

    private  $userMainPage;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->userMainPage['普通用户']['PC']           = '/SafetyMng/DataView/index';
        $this->userMainPage['普通用户']['Mobile']       = '/SafetyMng/MyRelatedQuestion';
        $this->userMainPage['管理用户']['PC']           = '/SafetyMng/JFUserViewMng/index';
        $this->userMainPage['管理用户']['Mobile']       = '/SafetyMng/index/blank';
        $this->userMainPage['局方用户']['PC']           = '/SafetyMng/TaskList/showJFView';
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

    public function  GetSysNameByServerIP(){
        $Ret = db('SysConf')->where(array('KeyType'=>'SysName','KeyName'=>$_SERVER['SERVER_ADDR']))->select()[0];
        if(empty($Ret)){
            return '安全管理系统';
        }else{
            return $Ret['KeyValue'];
        }
    }

    private function JumpUrl(){
        $platform = 'PC';
        if($this->IS_Mobile()){
            $platform = 'Mobile';
        }
        $this->redirect($this->userMainPage[session('UserType')][$platform]);
    }

    public function index()
    {
        $Name = session("Name");
        if(!empty($Name)){
            $this->JumpUrl();
            return;
        }
        if($this->IS_Mobile()){
            return view('index1');
        }
        return view('index');
    }
    public  function Login()
    {
        $UserName = input('aU');
        $Pwd = input('bP');
        if(empty($UserName) || empty($Pwd)){
            //$this->assign('Warning','')；
            goto OUT;
        }
        $Ret = db()->query("SELECT * FROM UserList WHERE (LOWER(UserName) = ? OR Name = ? ) AND LOWER(Pwd) = ?",array(strtolower($UserName),strtolower($UserName),strtolower($Pwd)));

        if(empty($Ret)){
            $this->assign("Warning","用户名或者密码错误！");
            Log::write('登陆失败:'.$UserName.'---->'.$Pwd."-->".date('Y-m-d H:i:s'),'zk2000');
        }else{
            $CorpMng = new CorpMng();
            $CorpInfo = $CorpMng->GetCorpInfo($Ret[0]["Corp"]);
            if(empty($CorpInfo)){
                $this->assign("Warning","获取部门相关信息失败!");
                Log::write('登陆失败:'.$UserName.$Ret[0]["Corp"].'获取部门相关信息失败!','zk2000');
                goto OUT;
            }
            session("CorpInfo",$CorpInfo);
            session("Corp",$Ret[0]["Corp"]);
            session("Name",$Ret[0]["Name"]);
            session("CorpRole",$Ret[0]["CorpRole"]);
            session("UserID",$Ret[0]["id"]);
            session("UserType",$Ret[0]["UserType"]);
            Log::write('登陆成功:'.$Ret[0]["Name"].':'.date('Y-m-d H:i:s'),'zk2000');

        }
        OUT :
            return $this->index();
    }

    public  function newLogin()
    {
        $UserName = input('aU');
        $Pwd = input('bP');
        $R=[];
        $R["url"]='';
        $R["msg"]='';
        if(empty($UserName) || empty($Pwd)){
            $R['msg'] = '请输入用户名和密码!';
            goto OUT;
        }

        $Ret = db()->query("SELECT * FROM UserList WHERE (LOWER(UserName) = ? OR Name = ? ) AND LOWER(Pwd) = ?"
            ,array(strtolower($UserName),strtolower($UserName),strtolower($Pwd)));
        if(empty($Ret)){
            $R['msg'] = '用户名或者密码错误';
            Log::write('登陆失败:'.$UserName.'---->'.$Pwd."-->".date('Y-m-d H:i:s'),'zk2000');
        }else{

            if($Ret[0]['Status']=='已禁用'){
                $R['msg'] = '您的账户已经被禁用!';
                goto OUT;
            }

            $CorpMng = new CorpMng();
            $CorpInfo = $CorpMng->GetCorpInfo($Ret[0]["Corp"]);
            if(empty($CorpInfo)){
                $R['msg'] = '获取部门相关信息失败!';
                Log::write('登陆失败:'.$UserName.$Ret[0]["Corp"].'获取部门相关信息失败!','zk2000');
                goto OUT;
            }
            session("CorpInfo",$CorpInfo);
            session("Corp",$Ret[0]["Corp"]);
            session("Name",$Ret[0]["Name"]);
            session("UserID",$Ret[0]["id"]);
            session("CorpRole",$Ret[0]["CorpRole"]);
            session("UserType",$Ret[0]["UserType"]);
            $R['msg'] = 'OK';
            Log::write('登陆成功:'.$Ret[0]["Name"].':'.date('Y-m-d H:i:s'),'zk2000');

            $platform = 'PC';
            if($this->IS_Mobile()){
                $platform = 'Mobile';
            }
            $R['url'] = $this->userMainPage[session('UserType')][$platform];
        }
        OUT :
            return json_encode($R, JSON_UNESCAPED_UNICODE) ;
    }

    public function ExitSYS()
    {
        session("Corp",NULL);
        session("Name",NULL);
        session("UserID",NULL);
        session("CorpRole",NULL);
        return $this->index();
    }
}
