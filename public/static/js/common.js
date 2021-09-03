var dwmap = {};
dwmap['01'] = '件';
dwmap['02'] = '英尺';
dwmap['03'] = '米';
dwmap['04'] = '公斤';
dwmap['05'] = 'KG';
dwmap['06'] = '把';
dwmap['07'] = '个';
dwmap['08'] = '根';
dwmap['09'] = '罐';
dwmap['10'] = '盒';
dwmap['11'] = '卷';
dwmap['12'] = '块';
dwmap['13'] = '双';
dwmap['14'] = '套';
dwmap['15'] = '张';
dwmap['16'] = '只';
dwmap['17'] = '包';
dwmap['18'] = '副';
dwmap['19'] = '片';
dwmap['20'] = '打';
dwmap['21'] = '批';
dwmap['22'] = 'LB';
dwmap['23'] = 'FT';
dwmap['24'] = 'KT';
dwmap['25'] = '桶';
dwmap['26'] = '台';
dwmap['27'] = '瓶';
dwmap['28'] = '次';
dwmap['29'] = '本';
dwmap['30'] = '毫升';
dwmap['31'] = '升';
dwmap['32'] = '斤';

var lbName = {};
lbName['0101'] = '总院下发入库';
lbName['0102'] = '采购入库';
lbName['0103'] = '调拨入库';
lbName['0104'] = '待修入库';
lbName['0105'] = '报废入库';
lbName['0106'] = '学院维修入库';
lbName['0107'] = '修理厂维修入库';
lbName['0108'] = '交换入库';
lbName['0109'] = '索赔入库';
lbName['0110'] = '退料入库';
lbName['0111'] = '租赁入库';
lbName['0112'] = '盘盈入库';
lbName['0113'] = '其他入库';
lbName['0114'] = '组装入库';
lbName['0115'] = '拆卸入库';
lbName['0116'] = '形态转换入库';
lbName['0117'] = '修理入库';

function showMsg(msg) {
    /*$("#errModal .errmsg").html(msg);
    $("#errModal").modal();*/
    layer.msg(msg);
}

function showConfirmMsg(msg, confrimCallback) {
    $("#confirmModal .errmsg").html(msg);
    $("#confirmModal").modal();

    $("#confirmModal_confirm").unbind("click");
    $("#confirmModal_confirm").click(function () {
        if (confrimCallback != undefined) {
            confrimCallback();
            $("#confirmModal").modal("hide");
        }
    });
}


//同步加载数据
function postRequestSync(url, data, callback, errCallback) {
    $.ajax({
        type: 'POST',
        url: url,
        timeout: 40000,
        cache: false,
        async: false,
        data: data,
        dataType: 'json',
        headers: {
            requestType: "ajax"
        },
        success: function (ret) {
            callback(ret);
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            var responseText = XMLHttpRequest.responseText;
            console.log(responseText);
            if ("notLogin" == responseText) {
                var yes = confirm("由于您长时间没有操作, session已过期, 请重新登录.");
                if (yes) {
                    window.location = '/bcair/login';
                }
            }
            if (errCallback != undefined) {
                errCallback();
            } else {
                if ("notLogin" == responseText) {
                    layer.msg("用户已过期");
                } else {
                    layer.msg("获取数据异常");
                }
            }
        }
    });
}

//同步加载数据
function postFormRequestSync(url, data, callback, errCallback) {
    $.ajax({
        type: 'POST',
        url: url,
        timeout: 40000,
        cache: false,
        async: false,
        data: data,
        dataType: 'json',
        processData: false,
        contentType: false,
        headers: {
            requestType: "ajax"
        },
        success: function (ret) {
            if (callback != undefined) {
                callback(ret);
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            var responseText = XMLHttpRequest.responseText;
            if ("notLogin" == responseText) {
                var yes = confirm("由于您长时间没有操作, session已过期, 请重新登录.");
                if (yes) {
                    window.location = '/bcair/login';
                }
            }
            if (errCallback != undefined) {
                errCallback();
            } else {
                if ("notLogin" == responseText) {
                    layer.msg("用户已过期");
                } else {
                    layer.msg("获取数据异常");
                }
            }
        }
    });
}

/*仓库*/
function WarehouseList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/warehouse/query", "", function (data) {
        var html = "";
        $.each(data['body'],
            function (k, v) {
                if (k == 0) {
                    html += "<option value='' selected>请选择</option>";
                    html += "<option value=\"" + v['cWhCode'] + "\">" + v['cWhCode'] + "-" + v['cWhName'] + "</option>";
                } else {
                    html += "<option value=\"" + v['cWhCode'] + "\" >" + v['cWhCode'] + "-" + v['cWhName'] + "</option>";
                }
            });
        $(fied).append(html);
    })
}

/*仓库input调用*/
function WarehouseLists(fied) {
    $(fied).children().remove();
    postRequestSync("/system/warehouse/query", "", function (data) {
        var html = "";
        $.each(data['body'],
            function (k, v) {
                if (k == 0) {
                    html += "<option value='' selected>请选择</option>";
                    html += "<option>" + v['cWhCode'] + "-" + v['cWhName'] + "</option>";
                } else {
                    html += "<option>" + v['cWhCode'] + "-" + v['cWhName'] + "</option>";
                }
            });
        $(fied).append(html);
    })
}

/*部门*/
function DepartmentList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/department/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['cDepCode'] + "\">" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cDepCode'] + "\" >" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*部门input调用*/
function DepartmentLists(fied) {
    $(fied).children().remove();
    postRequestSync("/system/department/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option >" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            } else {
                html += "<option >" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*来料单位*/
function DepartmentRootList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/department/DepartmentRoot", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option >" + v['cDepName'] + "</option>";
            } else {
                html += "<option >" + v['cDepName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

//图片类型
function ImgTypeList(fied) {
    $(fied).children().remove();
    postRequestSync("/img/getImgType", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value='" + v + "'>" + v + "</option>";
            } else {
                html += "<option value='" + v + "'>" + v + "</option>";
            }
        });
        $(fied).append(html);
    })
}

//机型List
function ItemCodeList(fied) {
    $(fied).children().remove();
    postRequestSync("/OrderGoods/selectitemCode", {"cInvCCode": "01"}, function (data) {
        var html = "";
        html += "<option value='' selected>请选择</option>";
        $.each(data['body'], function (k, v) {
            html += "<option value='" + v.cInvCName + "'>" + v.cInvCCode + "</option>";
        });
        $(fied).append(html);
    })
}

// 部门号更改后，立即生成入库单号
function createdEnterLibFormNo(v) {
    if (v.trim() != "") {
        var count = "";
        if ($("#count").val().toString().length == 1) {
            count += ("000" + $("#count").val().toString());
        } else if ($("#count").val().toString().length == 2) {
            count += ("00" + $("#count").val().toString());
        } else if ($("#count").val().toString().length == 3) {
            count += ("0" + $("#count").val().toString());
        } else {
            count += $("#count").val().toString();
        }
        var randomStr = v.substr(0, 2) + $("#dateNow").val() + count;
        vm.form.cCode = randomStr;
        vm.form.cDepCode = v;
    } else {
        vm.form.cCode = "";
        vm.form.cDepCode = "";
    }
}

/*业务员*/
function personList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/person/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['cPersonCode'] + "\">" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cPersonCode'] + "\" >" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*业务员*/
function personLists(fied) {
    $(fied).children().remove();
    postRequestSync("/system/person/query", {"cPersonCode": "4L"}, function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option>" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            } else {
                html += "<option>" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*业务员*/
function personNameList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/person/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['cPersonName'] + "\">" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cPersonName'] + "\" >" + v['cPersonCode'] + "-" + v['cPersonName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*有权限审核的业务员*/
function personListsByper(fied, oper) {
    $(fied).children().remove();
    postRequestSync("/system/bcuser/getPerUser", {"oper": oper}, function (data) {
        var html = "";
        html += "<option value='' selected>请选择</option>";
        $.each(data['body'], function (k, v) {
            html += "<option>" + v['id'] + "-" + v['realName'] +"</option>";
        });
        $(fied).append(html);
    })
}

/*来料单位*/
function LLDWList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/department/LLDW", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['cDepCode'] + "\">" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cDepCode'] + "\" >" + v['cDepCode'] + "-" + v['cDepName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*计量单位*/
function ComputationUnitList(fied) {
    $(fied).children().remove();
    postRequestSync("/system/computationUnit/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value=\"" + v['cComUnitName'] + "\">" + v['cComunitCode'] + "-" + v['cComUnitName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cComUnitName'] + "\" >" + v['cComunitCode'] + "-" + v['cComUnitName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/*存货分类*/
function inventoryClassList(fied) {
    //LGY 避免反复的无用查询
    if($(fied).children().length>0){
        return;
    }
    $(fied).children().remove();
    postRequestSync("/system/inventoryClass/query", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['cInvCCode'] + "\">" + v['cInvCCode'] + "-" + v['cInvCName'] + "</option>";
            } else {
                html += "<option value=\"" + v['cInvCCode'] + "\" >" + v['cInvCCode'] + "-" + v['cInvCName'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

function selectPosition() {
    var data = {};
    if($("#cPosCode").val()!==''){
        data['cPosCode'] = $("#cPosCode").val();
    }
    if ($("#hwcWhCode").val() != null && $("#hwcWhCode").val() != "") {
        data['cWhCode'] = $("#hwcWhCode").val().split("-")[0];
        if(curcInvCode!=undefined && curcInvCode!=''){
            data['cInvCode'] = curcInvCode;
        }
    }
    hwxxjqGruid();
    fetchGridData(pageres("/system/position/queryPos", data, $('#hwxxPaginatorId'), 1, 20, $("#hwxx")), $("#hwxx"), 20);
}

/**
 * 条码打印机IP
 */
function tmIP(fied) {
    postRequestSync("/PdaLog/queryTmIP", "", function (data) {
        var html = "";
        $.each(data['body'], function (k, v) {
            if (k == 0) {
                html += "<option value='' selected>请选择</option>";
                html += "<option value=\"" + v['tmdyjQdIp'] + "\">" + v['tmdyjQdName'] + "-" + v['tmdyjQdIp'] + "</option>";
            } else {
                html += "<option value=\"" + v['tmdyjQdIp'] + "\" >" + v['tmdyjQdName'] + "-" + v['tmdyjQdIp'] + "</option>";
            }
        });
        $(fied).append(html);
    })
}

/**
 * 校验输入框内容为非0的正整数
 * 用于input的onKeyUp事件
 * 例如：<input onKeyUp="ckInt(this)">
 * @author ZhaoDX
 * @param dom
 */
function ckInt(dom) {
    /*var v = $(dom).val();
    if (v != null && v != undefined && v.trim() != '') {
        var reg = /^[1-9]\d*$/;
        if (!reg.test(v)) {
            $(dom).val('');
            $(dom).attr("placeholder", "不合法");
        }
    }*/
    var price = parseInt($(dom).val());
    if (!isNaN(price)) {
        $(dom).val(price);
    } else {
        $(dom).val("");
        $(dom).attr("placeholder", "不合法");
    }
}

/**
 * 校验输入框内容为小数，且小数点位数后两位
 * 用于input的onblur事件
 * 例如：<input onblur="ckFloat(this)">
 * @author ZhaoDX
 * @param dom
 */
function ckFloat(dom) {
    /*var v = $(dom).val();
    if (v != null && v != undefined && v.trim() != '') {
        var reg = /^\d+(\.\d{1,2})?$/;
        if (!reg.test(v)) {
            $(dom).val('');
            $(dom).attr("placeholder", "不合法");
        }
    }*/
    var price = parseFloat($(dom).val());
    if (!isNaN(price)) {
        $(dom).val(price);
    } else {
        $(dom).val("");
        $(dom).attr("placeholder", "不合法");
    }
}


// 打印预览
function printView() {
    // 隐藏非打印内容
    var disabledPrint1 = $('.disabled-print');
    disabledPrint1.css('display', 'none')
    // 切换足部打印内容
    /*var foot2 = $('.box-footer2')
    foot2.css('display', 'block')*/
    // 隐藏table第一列
//        var firstColTh = $('#reportTable th[data-field="select"]');
//        firstColTh.css('display', 'none');
//        var tds = [];
//        var firstColTds = document.querySelectorAll('#reportTable ');
    /*for (var i = 0; i < firstColTds.length; i++) {
        var td = firstColTds[i].getElementsByTagName('td')[0]
        td.style.display= 'none'
    }*/
    // 局部打印
    var bodyHtml = window.document.body.innerHTML;
    var sprintstr = "<!--startprint-->";
    var eprintstr = "<!--endprint-->";
    var printhtml = bodyHtml.substr(bodyHtml.indexOf(sprintstr) + 17);
    printhtml = printhtml.substring(0, printhtml.indexOf(eprintstr));
    window.document.body.innerHTML = printhtml;
    window.print();
}


//-------------------------------------------Start 件号弹框业务-------------------------------------------//
//件号选取弹出层
function tcc(formData) {
    if (formData == undefined || formData == null || formData == "") {
        formData={};
        formData['cInvCCode'] = $("#common_cInvCCode").val();
        formData['cInvCode'] = $("#common_cInvCode").val();
        formData['cInvName'] = $("#common_cInvName").val();
    }
    layer.open({
        title: '信息查询',
        type: 1,
        content: $('#common_jhcx'),
        area: ['85%', '85%'],
    });
    inventoryClassList("#common_cxck");
    hcxxjqGruid();
    fetchGridData(pageres("/system/inventory/queryPage", formData, $('#common_hcxxPaginatorId'), 1, 20, $("#common_hcxx")), $("#common_hcxx"), 20);

}

//件号gruid
function hcxxjqGruid() {
    calcHeight = $(".layui-layer").height()*0.80;
    $("#common_hcxx").jqGrid({
        datatype: 'local',
        colModel: [
            {label: '件号', key: true, name: 'cInvCode'},
            {label: '存货名称', name: 'cInvName'},
            {label: '单位', name: 'cComUnitCode'},
            {label: '质保期', name: 'zBQ'},
            {label: '规格型号', name: 'cInvStd'},
            {label: '是否序列号管理', name: 'bSerial'},
            {label: '是否批次号管理', name: 'bInvBatch'},
            {label: '是否质保期管理', name: 'isBZQ'},
            {label: '是否适航证管理', name: 'isSHZ'},
            {label: '是否其他管理', name: 'isQT'},
            {label: 'cInvDefine9', name: 'cInvDefine9', hidden: true},
            {label: 'cInvDefine7', name: 'cInvDefine7', hidden: true},
            {label: 'cInvDefine8', name: 'cInvDefine8', hidden: true},
            {label: 'cInvDefine10', name: 'cInvDefine10', hidden: true}
        ],
        width:$('#common_jhcx').width()-20,
        height:calcHeight,
        shrinkToFit: false,
        autoScroll: true,          //shrinkToFit: false,autoScroll: true,这两个属性产生水平滚动条
        autowidth: false,          //必须要,否则没有水平滚动条
        viewrecords: true,
        offset:'auto',
        loadonce: true,
        ondblClickRow: function (rowid, iRow, iCol, e) {
            jhqr($("#common_hcxx"));
        },
        rownumbers: true,
        rownumWidth: 35
    })
}

function selectInventory() {
    var data = {};
    data['cInvCCode'] = $("#common_cInvCCode").val();
    data['cInvCode'] = $("#common_cInvCode").val();
    data['cInvName'] = $("#common_cInvName").val();
    hcxxjqGruid();
    fetchGridData(pageres("/system/inventory/queryPage", data, $('#common_hcxxPaginatorId'), 1, 20, $("#common_hcxx")), $("#common_hcxx"), 20);
}

//-------------------------------------------End 件号弹框业务-------------------------------------------//

/**
 * 判断元素是否在数组中
 * @param arr
 * @param value
 */
function isInArray(arr, value) {
    for (var i = 0; i < arr.length; i++) {
        if (value === arr[i]) {
            return true;
        }
    }
    return false;
}



function SetElementStockPopoverBycInvCode(cInvCode,ElementId) {
    postRequestSync("/CurrentStock/GetCurStockBycInvCode",{cInvCode:cInvCode},
        function (data) {
            Arr = data.body;

            html = '<table class="MyTable" cellspacing="0" cellpadding="0"><tr><td class="alt">仓库号</td>' +
                '<td class="alt">仓库</td><td class="alt">库存量</td></tr>';
            for(i=0;i<Arr.length;i++){
                html += '<tr><td class="">'+Arr[i].cWhCode+'</td>'+
                    '<td class="left">'+Arr[i].cWhName+'</td>'+
                    '<td class="right">'+Arr[i].iQuantity+'</td></tr>';
            }
            html += '</table>';

            $(ElementId).attr("data-toggle","popover");
            $(ElementId).attr("data-trigger","hover");
            $(ElementId).attr("data-container","body");
            $(ElementId).attr("data-content",html);
            $("[data-toggle='popover']").popover({html:true});
        },
        function () {

        });
}

function GetDate() {
    var date = new Date();
    var month = date.getMonth() + 1;
    var strdate = date.getDate();
    if (month >= 1 && month <= 9) {
        month = "0" + month;
    }
    if (strdate >= 1 && strdate <= 9) {
        strdate = "0" + strdate;
    }
    return date.getFullYear() + "-" + month + "-" + strdate;
}
