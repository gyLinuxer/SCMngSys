<?php
namespace app\safetymng\controller;

use think\Controller;
use think\Db;
use think\Request;

class DataView extends PublicController{
    public function index(){
        return view('index');
    }

}
