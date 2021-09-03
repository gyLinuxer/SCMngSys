<?php
namespace app\safetymng\controller;
use think\Controller;
use think\Db;
use think\Request;

class MyRelatedQuestion extends PublicController{
    public function index()
    {
        $OCTaskList = db()->query("SELECT TaskList.*,CheckList.id as CheckListID 
                        FROM TaskList JOIN CheckList ON CheckList.id = TaskList.RelateID 
                        WHERE isDeleted = '否' AND TaskType = ?  AND TaskList.Status <> '已完成' AND TaskList.id in 
                        (SELECT DISTINCT TaskID FROM TaskDealerGroup WHERE Name=?) 
                        ORDER BY CheckName",array(TaskCore::ONLINE_CheckTask,session('Name')));
        $this->assign("TaskList",$OCTaskList);
        $this->assign("ASC1",0);
        $this->assign("ASC2",0);
        $this->assign("CurPlatform","Mobile");
        return view('index');
    }
}
