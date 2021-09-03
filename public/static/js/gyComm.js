/**
 * Created by gylinuxer on 16-10-1.
 */

//从数组中删除一个val的元素
function arr_rm(arr,val) {
        var i =0 ;

        for(i=0;i<arr.length;i++){
            if(arr[i]==val){
                arr.splice(i,1);
                return true;
            }
        }
        return false;
}
//添加一个唯一的val到数组
function arr_add(arr,val) {
    var i =0 ;

    for(i=0;i<arr.length;i++){
        if(arr[i]==val){
            return true;
        }
    }

    arr.push(val);
    return true;
}

function MyAjax(url,jsondat, SuccessFun, ErrorFun,isJson) {

    $.ajax(
        {
            url: url,
            type: "POST",
            contentType: "application/json",
            data:   JSON.stringify(jsondat),
            dataType: "json",
            success: function (data) {
                SuccessFun(data);
            },
            error: function (xhr, s, e) {
                if (ErrorFun != undefined && ErrorFun != null) {
                    ErrorFun(xhr, s, e);
                } else {
                   alert('数据加载错误');
                }
            }
        });
}
/*函数功能：通过制定url验证制定信息是否正确，然后修改对应input的样式
 * id分布在needCKForm=id needCKInput needCKSpan
 */
function CKinfoAndChangStyle(url,json_data,id)
{
    MyAjax(url,json_data,function (data) {
            if(data.status==-1) {//错误,多半是子类没有设置正确的表名
                $("div[needCKForm='"+id+"']").attr("class","form-group has-error has-feedback");
                $("input[needCKInput='"+id+"']").attr("data-original-title","数据验重错误!");
                $("span[needCKSpan='"+id+"']").attr("class","glyphicon glyphicon-remove form-control-feedback");
            }else if (data.status==0){//不存在
                $("div[needCKForm='"+id+"']").attr("class","form-group has-success has-feedback");
                $("input[needCKInput='"+id+"']").attr("data-original-title","验证通过!");
                $("span[needCKSpan='"+id+"']").attr("class","glyphicon glyphicon-ok form-control-feedback");
            }else if(data.status==1){//存在　
                $("div[needCKForm='"+id+"']").attr("class","form-group has-warning has-feedback");
                $("input[needCKInput='"+id+"']").attr("data-original-title","已存在!");
                $("span[needCKSpan='"+id+"']").attr("class","glyphicon glyphicon-warning-sign form-control-feedback");
            }
    })
    $("[data-toggle='tooltip']").tooltip();
}

function AjaxAddCR(url,CRID) {
    o = {};
    o.crid = CRID;
    MyAjax(url,o,function (data) {
           if(data.status!='success'){
               alert('操作失败');
           }
    })
}


