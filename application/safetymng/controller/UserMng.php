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

class UserMng extends  PublicController{
    public function showUserInfoChg(){
        return view('UserInfoChg');
    }

    public function GetUserInfo(){
        $row = db()->query("SELECT id,Name,UserName,Corp,CorpRole FROM UserList WHERE id = ?"
            ,[session('UserID')]);
        return json_encode($row[0],JSON_UNESCAPED_UNICODE);
    }

    public function ChgUserNameAndPwd(){
        $UserName = input('UserName');
        $Pwd = input('Pwd');
        $Warning = '';

        if(empty($UserName) || empty($Pwd)){
            $Warning = '用户名或者密码不能为空!';
            goto OUT;
        }

        $Exist = db()->query('SELECT * FROM UserList WHERE id <> ? AND UserName = ? '
            ,[session('UserID'),$UserName]);

        if(!empty($Exist)){
            return '该用户已被使用!';
            goto OUT;
        }

        db()->query('UPDATE UserList SET UserName = ? , Pwd = ? WHERE id = ?',[
            $UserName,$Pwd,session('UserID')
        ]);


        $Warning = 'OK';

        OUT:
            return $Warning;
    }

    public function showUserMng(){
        return view('UserMng');
    }

    public function getUserListByCorp(){
        $Corp = input('Corp');
        $pArr = [];
        $Sql = 'SELECT * FROM UserList WHERE 1=1 ';
        if(!($Corp=='全部' || empty($Corp))){
            $Sql .= ' AND Corp = ?';
            $pArr[] = $Corp;
        }
        $UserList = db()->query($Sql,$pArr);
        return json_encode($UserList,JSON_UNESCAPED_UNICODE);
    }

    public function SaveUser(){
        $type = input('type');
        $Name = input('Name');
        $UserName = input('UserName');
        $UserType = input('UserType');
        $Pwd = input('Pwd');
        $CorpRole = input('CorpRole');
        $Status = input('Status');
        $ISQSInspector = input('ISQSInspector');
        $Corp = input('Corp');
        $id = input('id');
        $Warning = '';

        if(empty($UserName) || empty($CorpRole) || empty($Status) || empty($ISQSInspector) || empty($Corp) || empty($Name)){
            $Warning = '输入要素不完整';
            goto OUT;
        }

        if($type=='Mdf'){
           $row = db('UserList')->where(['id'=>$id])->select();
           if(empty($row)){
               $Warning = '要修改的用户不存在!';
               goto OUT;
           }

           $row = $row[0];

           $row['CorpRole'] = $CorpRole;
           $row['Status'] = $Status;
           $row['ISQSInspector'] = $ISQSInspector;
           $row['Corp'] = $Corp;
           $row['UserName'] = $UserName;
           $row['UserType'] = $UserType;

           if(!empty($Pwd)){
               $row['Pwd'] = $Pwd;
           }

          if(db()->query('SELECT id FROM UserList WHERE id <> ? AND (Name = ? OR UserName = ?) AND Corp = ?',[$row['id'],$Name,$UserName,$row['Corp']])){
               $Warning = '该姓名已经在该部门存在!';
               goto OUT;
          }

           $R = db('UserList')->where(['id'=>$id])->update($row);
           if(!empty($R)){
               $Warning = '修改成功!';
               goto OUT;
           }

        }else if($type='Add'){

            if(empty($Pwd)){
                $Warning = '请输入用户密码';
                goto OUT;
            }

            if(db()->query('SELECT id FROM UserList WHERE id <> ? AND (Name = ? OR UserName = ?) AND Corp = ?',[$id,$Name,$UserName,$Corp])){
                $Warning = '该姓名或者用户名已经在该部门存在!';
                goto OUT;
            }


            if(db()->query('SELECT id FROM UserList WHERE UserName = ?',[$UserName])){
                $Warning = '该用户名已经存在!';
                goto OUT;
            }

            $row = [];

            $row['CorpRole'] = $CorpRole;
            $row['Status'] = $Status;
            $row['ISQSInspector'] = $ISQSInspector;
            $row['Corp'] = $Corp;
            $row['Name'] = $Name;
            $row['UserName'] = $UserName;
            $row['Pwd'] = $Pwd;
            $row['UserType'] = $UserType;

            $R = db('UserList')->insert($row);
            if(!empty($R)){
                $Warning = '新增用户成功!';
                goto OUT;
            }
        }

        OUT:
            return $Warning;
    }

    public function GetUserListByType(){
        $UserType = input('UserType');
        $uList = db('UserList')->where(['UserType'=>$UserType])->select();
        return json_encode($uList,JSON_UNESCAPED_UNICODE);
    }
}