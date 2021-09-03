<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link href="__PUBLIC__/css/bootstrap-table.css" rel="stylesheet">
  <link rel="stylesheet" href="__PUBLIC__/css/el-ui.css">
  <script src="__PUBLIC__/js/vue.js"></script>
  <script src="__PUBLIC__/js/axios.min.js"></script>
  <script src="__PUBLIC__/js/el-index.js"></script>
  <script src="__PUBLIC__/js/vue-router.js"></script>
  <script src="__PUBLIC__/js/gyComp/mainstorelist.js"></script>
  <script src="__PUBLIC__/js/jquery.js"></script>
</head>
<body>
<div id="app">
  <div>
    <el-row>
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="场馆位置:">
          <el-select allow-create size="medium" v-model="formData.StoreAddr" placeholder="请选择">
            <!--el-option
                    v-for="item in WareHouseList"
                    :key="item.cWhCode"
                    :label="item.cWhCode+'-'+item.cWhName"
                    :value="item.cWhCode">
            </el-option-->
          </el-select>
        </el-form-item>
        <el-form-item label="商铺代码:">
          <el-input v-model="formData.StoreCode" style="width:150px;" size="medium" ></el-input>
        </el-form-item>
        <el-form-item label="店铺名称:">
          <el-input v-model="formData.StoreName" style="width:150px;" size="medium" > </el-input>
        </el-form-item>
        <el-form-item label="商户名:">
          <el-input v-model="formData.StoreOwner" style="width:150px;" size="medium" > </el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="onSubmit" >查询</el-button>
        </el-form-item>
        <el-form-item>
          <a href="#"  style="margin-left: 10px;" @click="GJSearchDiagShow = true">高级....</a>
        </el-form-item>
      </el-form>
    </el-row>

    <el-dialog title="高级搜索条件"
               :visible="GJSearchDiagShow"
               width="45%"
               @close="GJSearchDiagShow=false"
    >
      <el-form :inline="false">
        <el-form-item label="合同编号:" label-width="100px">
          <el-row>
            <el-col :span="10">
              <el-input v-model="formData.ContactCode"></el-input>
            </el-col>
          </el-row>
        </el-form-item>
        <el-form-item label="房租到期:" label-width="100px">
          <el-row>
            <el-col :span="10">
              <el-date-picker
                      v-model="formData.FZDQStart"
                      type="date"
                      placeholder="开始日期">
              </el-date-picker>
            </el-col>
            <el-col :span="10" :offset="4">
              <el-date-picker
                      v-model="formData.FZDQEnd"
                      type="date"
                      placeholder="结束日期">
              </el-date-picker>
            </el-col>
          </el-row>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button  @click="GJSearchDiagShow = false">关闭</el-button>
      </div>
    </el-dialog>
    <el-row>
        <el-table
                v-loading="loading"
                :data="tableData"
                :height="tableHeight"
                @sort-change="onsort"
                stripe
                border
                style="width: 140%;"
        >
          <el-table-column
                  type="index"
                  label="序号"
                  width="50">
          </el-table-column>

          <el-table-column
                  prop="StoreCode"
                  label="店铺代码"
                  sortable
                  width="180">
          </el-table-column>
          <el-table-column
                  prop="StoreName"
                  label="店铺名称"
                  sortable
                  width="180">
            <template slot-scope="scope">
              <el-tag type="info" effect="dark" size="mini">{{scope.row.StoreName}}</el-tag>
            </template>
          </el-table-column>
          <el-table-column
                  prop="StoreOwner"
                  label="商户"
                  sortable
                  width="180">
          </el-table-column>
          <el-table-column
                  prop="Tel"
                  label="联系方式"
                  sortable
                  width="180">
          </el-table-column>
          <el-table-column
                  prop="StoreArea"
                  label="面积"
                  sortable
                  width="180">
          </el-table-column>
          <el-table-column
                  prop="FZDeadDate"
                  label="房租到期"
                  sortable
                  width="180">
          </el-table-column>
          <el-table-column
                  prop="ZJLeftDays"
                  label="房租剩余天数"
                  sortable
                  width="150">
            <template slot-scope="scope">
              <el-tag :type="CalcFZTagType(scope.row.ZJLeftDays)" effect="dark" size="mini">{{scope.row.ZJLeftDays}}天</el-tag>
            </template>
          </el-table-column>
          <el-table-column
                  prop="YJ"
                  width="180"
                  sortable
                  label="押金">
          </el-table-column>
          <el-table-column
                  prop="QK"
                  width="180"
                  label="欠款">
          </el-table-column>
          <el-table-column
                  prop="Contact"
                  width="180"
                  label="合同编号">
          </el-table-column>
        </el-table>
    </el-row>
    <el-row>
    </el-row>
  </div>


</div>



<script>

    export default {
        props:{

        },
        name:'mainstorelist',
        data () {
            return {
                GJSearchDiagShow:false,
                formData:{
                    StoreAddr : '',
                    StoreCode : '',
                    StoreName : '',
                    StoreOwner: '',
                    ContactCode:'',
                    FZDQStart:'',
                    FZDQEnd:'',
                },
                SysConf:{
                    GreenDeadDay:30,
                    YellowDeadDay:15,
                    OrentalT:3,
                    DFPrice:1.2,
                    WYFPrice:1.5
                },
                currentPage:0,
                tableHeight:300,
                loading:false,
                tableData:[],
                total:0,
            }
        },
        created(){
            this.tableHeight = window.innerHeight  - 100;
            this.GetSysConf();
            this.GetStoreList();
        },
        computed:{
            calcMaxHeight(){
                if(this.cinvcodeqry!=undefined && this.cinvcodeqry!=null && this.cinvcodeqry!=''){
                    console.log('550px');
                    return '550px';
                }else{
                    return '';
                }
            }
        },
        methods:{
            gyAjaxPost(url,data,onSuccess,onError){
                var _this = this;
                axios({
                    method: 'post',
                    url: url,
                    data: data
                }).then(function(response){
                    onSuccess(_this,response);
                }).catch(function (error) {
                    if(error!=null)
                        if(onError!=null){
                            onError(_this,error);
                        }
                });
            },
            GetStoreList(){
                this.loading = true;
                this.gyAjaxPost("/Index/MainShowList/StoreQry",this.formData,
                    function (_this,response) {
                        console.log(JSON.stringify(response.data));
                        _this.tableData = response.data;
                        _this.loading = false;
                    },null);
            },
            GetSysConf(){
                this.gyAjaxPost("/Index/SysConf/GetSysConf",this.formData,
                    function (_this,response) {
                       _this.SysConf = response.data;
                    },null);
            },
            CalcFZTagType(leftDays){
              if(leftDays>=this.SysConf.GreenDeadDay){
                  return "success";
              }else if(leftDays>=this.SysConf.YellowDeadDay){
                  return "warning";
              }else{
                  return "danger";
              }
            },
            onSubmit(){
                this.GetStoreList();
            },
            onsort(){

            }
        }
    }
</script>
</body>
</html>