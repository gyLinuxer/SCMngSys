//监听属性发生变化
var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;//浏览器兼容
var config = {attributes: true, childList: true}//配置对象
function activiDom(_dom,_domActivi,falg) {
    _dom.each(function () {
        var _this = $(this);
        var observer = new MutationObserver(function (mutations) {//构造函数回调
            mutations.forEach(function (record) {
                if (record.type == "attributes") {//监听属性
                    if (_dom.hasClass("active")) {
                        //判断是否需要active
                        if (falg){
                            _domActivi.addClass("active");
                            _dom.removeClass("active");
                        }
                    }
                }
                if (record.type == 'childList') {//监听结构发生变化
                }
            });
        });
        observer.observe(_this[0], config);
    });
}
