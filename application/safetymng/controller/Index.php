<?php
namespace app\safetymng\controller;

use think\db;

class Index extends PublicController
{
    public function index()
    {
		if($this->IS_Mobile()){
            $this->redirect(url("/SafetyMng/MyRelatedQuestion"));
        }else{
            $this->redirect(url("/SafetyMng/TaskList/showQuestionList"));
        }
    }
    public function blank(){
        return view('index');
    }
}
