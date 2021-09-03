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

class  Login extends Controller{

    function IS_Mobile(){
        $regex_match="/(nokia|iphone|ipad|micromsg|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
        $regex_match.="htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
        $regex_match.="blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
        $regex_match.="symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
        $regex_match.="jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220";
        $regex_match.=")/i";
        return isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE']) or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']));
    }

    public function index()
    {
        $Name = session("Name");
        if(!empty($Name)){
            if($this->IS_Mobile()){
                $this->redirect(url("/SafetyMng/MyRelatedQuestion"));
            }
            $this->redirect(url("/SafetyMng/TaskList"));
            return;
        }
        if($this->IS_Mobile()){
            return view('index');
        }
        return view('login-3');
    }
    public  function Login()
    {
        $UserName = input('aU');
        $Pwd = input('bP');
        $Ret = db("UserList")->where(array(
            "UserName"=>$UserName,
            "Pwd"=>$Pwd
        ))->select();
        if(empty($Ret)){
            $this->assign("Warning","用户名或者密码错误！");
        }else{
            session("Corp",$Ret[0]["Corp"]);
            session("Name",$Ret[0]["Name"]);
            session("CorpRole",$Ret[0]["CorpRole"]);
            if($this->IS_Mobile()){
                $this->redirect(url("/SafetyMng/MyRelatedQuestion"));
            }
            $this->redirect(url("/SafetyMng/TaskList"));
        }
        return $this->index();
    }

    public function ExitSYS()
    {
        session("Corp",NULL);
        session("Name",NULL);
        session("CorpRole",NULL);
        return $this->index();
    }
}
