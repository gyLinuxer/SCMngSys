<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;

class PublicController extends  Controller{

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        if (is_null(session("UserID"))) {
            $this->redirect('/SafetyMng/Login/Index');
        } else {
            $this->assign('IsSuperCorp', $this->IsSuperCorp());
            $this->assign('CorpRole', session('CorpRole'));
        }

    }

       function IS_Mobile(){
            $regex_match="/(nokia|iphone|ipad|micromsg|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|";
            $regex_match.="htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|";
            $regex_match.="blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|bunjalloo|maui|";
            $regex_match.="symbian|smartphone|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|pantech|gionee|^sie\-|portalmmm|";
            $regex_match.="jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|320x320|240x320|176x220";
            $regex_match.=")/i";
            $IS =  isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE']) or preg_match($regex_match, strtolower($_SERVER['HTTP_USER_AGENT']));
            if($IS){
                $this->assign("CurPlatform","Mobile");
            }
            return $IS;
        }
        function GetCorpList(){
            return db('CorpList')->select();
        }

        function GetGroupCorp(){
            return session('CorpInfo')['GroupCorp'];
        }
        function IsSuperCorp(){
            return session('CorpInfo')['IsSuperCorp']=='YES';
        }
        function GetCorp(){
            return session('Corp');
        }


        public function GenNewReformDePWD()
        {
            $PWD = '12345678';
            for($i=0;$i<8;$i++){
                $isZM = rand(0,1);
                $PWD[$i] = $isZM===0?chr(65+rand(0,25)):rand(0,9);
            }
            return $PWD;
        }

        public function CheckDelPwd($Pwd=''){
            $Ret = db('SysConf')->where(array('KeyName'=>'DeletePwd','KeyValue'=>$Pwd))->select();
            if(empty($Ret)){
                return false;
            }else{
                return true;
            }
        }

        public function updateDelPwd(){
            db()->query("UPDATE SysConf SET KeyValue = ? WHERE KeyName = ? "
                ,array($this->GenNewReformDePWD(),'DeletePwd'));
        }

        public function BuildInSqlFromArr($Arr,$Col=""){
            $InSql = "(";
            if(empty($Arr)){
                return "('')";
            }

            if(empty($Col)){//一纬数组
                foreach ($Arr as $v){
                    $InSql.= "'".$v."',";
                }
            }else{
                foreach ($Arr as $v){
                    if(isset($v[$Col])){
                        $InSql.= "'".$v[$Col]."',";
                    }
                }
            }
            if(strlen($InSql)>2){
                $InSql[strlen($InSql)-1] = ')';
            }else{
                $InSql = "('')";
            }

            return $InSql;

        }

        public function CreateSubQrySQL($MainSql,$ParamArr,$input_Arr){
            /*$input_Arr --> [
            //                  [
            //                      'F'=>字段名,
                                    'V'=>查询值
                                    'FH'=>运算符(=、>=、<=、LIKE)等,
            //                  ]
            //              ]
            */
            foreach ($input_Arr as $v){
                if(!empty($v['I_V'])){
                    if($v['FH']=='IN_CONNECT'){
                        $MainSql .= ' AND '.$v['F'].' IN '.$v['V'];
                    }else{
                        $MainSql .= ' AND '.$v['F'].' '.$v['FH'].' ?';
                        $ParamArr[] = $v['V'];
                    }
                }
            }
            return [
                'MainSql'=>$MainSql,
                'ParamArr'=>$ParamArr
            ];
        }

}
