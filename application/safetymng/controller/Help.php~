<?php
namespace app\SafetyMng\controller;
/**
 * Created by PhpStorm.
 * User: liguangyao
 * Date: 2018/12/8
 * Time: 20:40
 */
use think\controller;
class Help extends controller
{
    public function uploadFile(){
        //dump(request()->file());
        $file = request()->file('file');
       // dump(request()->file());
        // 移动到框架应用根目录/public/upload/ 目录下
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'upload');
            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                //echo $info->getExtension();
                // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                echo '/upload/'.$info->getSaveName();
                // 输出 42a79759f284b767dfcb2a0197904287.jpg
                //echo $info->getFilename();
            }else{
                // 上传失败获取错误信息
                echo $file->getError();
            }
        }
    }
}