<template>
  <div>
    <el-row>
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="申请单位:">
          <el-input v-model="appDepName" style="width:150px;" size="medium"></el-input>
        </el-form-item>
        <el-form-item label="申请人:">
          <el-input v-model="appUserName" style="width:150px;" size="medium" ></el-input>
        </el-form-item>
        <el-form-item label="件号:">
          <el-input v-model="appInvCode" style="width:150px;" size="medium" ></el-input>
        </el-form-item>
        <el-form-item label="航材名:">
          <el-input v-model="appInvName" style="width:150px;" size="medium"> </el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="onSubmit" >查询</el-button>
        </el-form-item>
        <el-form-item>
          <el-button type="success" @click="onSelectOK" >确定</el-button>
        </el-form-item>
          <el-form-item>
              <el-button type="danger" @click="OnCloseApp" >关闭申请</el-button>
          </el-form-item>
      </el-form>
    </el-row>
      <el-row>
      <template>
        <el-table
              :data="tableData"
              max-height="450px"
              stripe
              border
              style="width: 140%;margin-bottom:10px;"
               @selection-change="handleSelectionChange"
              :default-sort = "{prop: 'dnmaketime', order: 'descending'}"
              >
              <el-table-column
                type="selection"
                width="55">
              </el-table-column>
              <el-table-column
                prop="cCode"
                label="单据号"
                sortable="true"
                width="180">
              </el-table-column>
              <el-table-column
                prop="cDepName"
                label="部门"
                sortable="true"
                width="180">
              </el-table-column>
              <el-table-column
                prop="cPersonName"
                sortable="true"
                label="申请人">
              </el-table-column>
              <el-table-column
                prop="cItemCode"
                label="机号"
                sortable="true"
                width="180">
              </el-table-column>
              <el-table-column
                prop="cInvCode"
                width="180"
                sortable="true"
                label="件号">
              </el-table-column>
              <el-table-column
                prop="cInvName"
                label="存货名称"
                sortable="true"
                width="180">
              </el-table-column>
              <el-table-column
                prop="iQuantity"
                sortable="true"
                label="需求数量">
              </el-table-column>
              <el-table-column
                prop="dDueDate"
                label="需求日期"
                sortable="true"
                width="180">
              </el-table-column>
              <el-table-column
                prop="dnmaketime"
                width="180"
                sortable="true"
                label="制单时间">
              </el-table-column>
            </el-table>
</template>

    </el-row>
  </div>


</template>

<script>
import axios from 'axios';
export default {
  name:'applisttable',
  data () {
    return {
      appDepName:'',
      appUserName:'',
      appInvCode:'',
      appInvName:'',
      tableData:[],
      AppSelection:[],
    }
  },
  created(){

  },
  methods:{
    updateData(){
      this.gyAjaxPost("/ApplicationForm/selectMaterialAppList"
      ,"cDepName="+this.appDepName+"&cPersonName="+this.appUserName
      +"&cInvCode="+this.appInvCode+"&cInvName="+this.appInvName
      ,
        function (_this,response) {
            _this.tableData = response.data.body;
        },null);
    },
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
                 onError(_this,error);
            });
     },
     onSubmit(){
        this.updateData();
     },
     handleSelectionChange(selArr){
        this.AppSelection = selArr;
     },
     onSelectOK(){
         this.$emit("onsendselection",this.AppSelection);
     },
     OnCloseApp(){
        var _this = this;
         this.$confirm('确定关闭所选行的申请?', '提示', {
             confirmButtonText: '确定',
             cancelButtonText: '取消',
             type: 'warning'
         }).then(() => {
             console.log("确定");
             console.log(JSON.stringify(_this.AppSelection));
             var Ids = '',i=0;
             for(i=0;i<_this.AppSelection.length;i++){
                Ids+=(Ids==''?_this.AppSelection[i].autoid:','+_this.AppSelection[i].autoid);
             }
             console.log("确定");
             _this.gyAjaxPost("/ApplicationForm/CloseApps"
                 ,"AppIds="+Ids,
                 function (_this,response) {
                     if(response.data.body=="OK"){
                         _this.$message({
                             type: 'success',
                             message: '关闭成功!'
                         });
                         _this.updateData();
                     }else{
                         _this.$message({
                             type: 'success',
                             message: '关闭失败,原因:'+response.data.body
                         });
                     }
                 },null);
             console.log("确定");
         });
     }
  }
}
</script>

<style>
.example {
  color: red;
}
</style>
