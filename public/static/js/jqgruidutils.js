var map = {};
map['true'] = "是";
map['false'] = "否";
map['1'] = "是";
map['0'] = "否";

//选染jqgruid
function fetchGridData(data, jqGrid, col) {
    //现有行id
    var ids = jqGrid.getDataIDs();
    jqGrid[0].grid.beginReq();
    $.each(ids, function (i, obj) {
        jqGrid.delRowData(obj);
    });
    //初始化行数
    for (i = 1; i <= col; i++) {
        jqGrid.addRowData(i, {}, i);
    }
    // 将数据setGrid
    if (data != null && data.length > 0) {
        jqGrid.jqGrid('setGridParam', {rowNum: data.length});
    }
    jqGrid.jqGrid('setGridParam', {data: data});
    // hide the show message
    jqGrid[0].grid.endReq();
    // refresh the grid
    jqGrid.trigger('reloadGrid');
}

//选染jqgruid
function fetchGridData0(data, jqGrid, col) {
    //现有行id
    var ids = jqGrid.getDataIDs();
    jqGrid[0].grid.beginReq();
    $.each(ids, function (i, obj) {
        jqGrid.delRowData(obj);
    });
    //初始化行数
    for (i = 1; i <= col; i++) {
        if (data[i - 1] != null) {
            jqGrid.addRowData(i, data[i - 1], i);
        } else {
            jqGrid.addRowData(i, {}, i);
        }
    }
    // 将数据setGrid
    //jqGrid.jqGrid('setGridParam', {data: data});
    // hide the show message
    jqGrid[0].grid.endReq();
    // refresh the grid
    jqGrid.trigger('reloadGrid');
}

//,url查询地址 req查询条件 xPagth dom元素 Page当前页 pageSize当前页行数 jqgruid dom元素
function pageres(url, req, xPagth, Page, pageSize, jqgruid) {
    if(req == null || req==undefined ||req==''){
        req = [];
    }
    req["page"] = Page;
    req["pageSize"] = pageSize;
    var res;
    $.ajax({
        url: url,
        type: "post",
        data: req,//需携带page当前页和总行数和查询信息
        dataType: 'JSON',
        success: function (data) {
            jqgruid.jqGrid("clearGridData");
            if(data.body==null){
                return;
            }
            res = data.body.records;
            $.each(res, function (k, v) {
                v.bInvBatch = map[v.bInvBatch];
            });
            $.each(res, function (k, v) {
                if (v.bSerial == "1" || v.bSerial == "0") {
                    v.bSerial = map[v.bSerial];
                }
            });
            $.each(res, function (k, v) {
                v.isBZQ = map[v.isBZQ];
            });
            $.each(res, function (k, v) {
                v.bPosEnd = map[v.bPosEnd];
            });
            $.each(res, function (k, v) {
                v.bclose = map[v.bclose];
            });
            $.each(res, function (k, v) {
                if (v.dhstatus == "1") {
                    v.dhstatus = "已到货";
                } else {
                    v.dhstatus = "未到货";
                }
            });
            //调用分页
            Pag(data.body.current, data.body.pages, url, req, xPagth, pageSize, jqgruid);
            fetchGridData(res, jqgruid, 0);
        }
    });
}

function Pag(currentPage, totalPages, url, req, xPagth, pageSize, jqgruid) {
    if (totalPages == 0) {
        totalPages = 1
    }
    xPagth.bootstrapPaginator({
        bootstrapMajorVersion: 3,//bootstrap版本
        currentPage: currentPage,//当前页码
        totalPages: totalPages,//总页数（后台传过来的数据）
        numberOfPages: 5,//一页显示几个按钮
        itemTexts: function (type, page, current) {
            switch (type) {
                case "first":
                    return "首页";
                case "prev":
                    return "上一页";
                case "next":
                    return "下一页";
                case "last":
                    return "末页";
                case "page":
                    return page;
            }
        },//改写分页按钮字样
        onPageClicked: function (event, originalEvent, type, page) {
            delete req.Page;
            delete req.pageSize;
            jqgruid.jqGrid("clearGridData");
            fetchGridData(pageres(url, req, xPagth, page, pageSize, jqgruid), jqgruid, rowidcs);
        }
    });
}