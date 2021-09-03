<template>
  <div>
    <el-row>
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="分类:">
          <el-select size="medium" v-model="appItemCode" placeholder="请选择">
             <el-option
               v-for="item in InvClassList"
               :key="item.value"
               :label="item.cInvCCode+'-'+item.cInvCName"
               :value="item.cInvCCode">
             </el-option>
           </el-select>
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
      </el-form>
    </el-row>
      <template>
        <el-table
              v-loading="loading"
              :data="tableData"
              max-height="550px"
              stripe
              border
              style="width: 140%;"
               @selection-change="handleSelectionChange"
              >
              <el-table-column
                type="selection"
                width="55">
              </el-table-column>
              <el-table-column
                prop="cInvCode"
                label="件号"
                sortable
                width="180">
              </el-table-column>
              <el-table-column
                prop="cInvName"
                label="存货名称"
                width="180">
              </el-table-column>
              <el-table-column
                prop="cComunitName"
                label="单位">
              </el-table-column>
              <el-table-column
                prop="cInvStd"
                width="180"
                label="规格">
              </el-table-column>
              <el-table-column
                prop="bSerial"
                label="序列号管理"
                sortable
                width="180"
                :fomatter="formatterS">
              </el-table-column>
              <el-table-column
                prop="bInvBatch"
                sortable
                label="批次管理">
              </el-table-column>
            </el-table>
</template>

    </el-row>
  </div>


</template>

<script>
import axios from 'axios';
export default {
  props:['needtoupdate'],
  name:'applisttable',
  data () {
    return {
      appItemCode:'',
      appInvCode:'',
      appInvName:'',
      tableData:[],
      AppSelection:[],
      InvClassList:[],
      loading:false,
    }
  },
  created(){
    this.getInvClassList();
  },
  methods:{
    getInvClassList(){
      this.gyAjaxPost("/system/inventoryClass/query","",
        function (_this,response) {
            _this.InvClassList = response.data.body;
        },null);
    },
    updateData(){
      if(this.appItemCode=='' && this.appInvCode=='' && this.appInvName==''){
         this.$message('请至少输入一项搜索条件，防止数据量过大而崩溃!');
         return;
      }
      this.loading = true;
      this.gyAjaxPost("/system/inventory/queryInv"
      ,"cInvCCode="+this.appItemCode+"&cInvCode="+this.appInvCode
      +"&cInvName="+this.appInvName
      ,
        function (_this,response) {
            _this.tableData = response.data.body;
            _this.loading = false;
        },function(_this,res){
           _this.loading = false;
        });
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
     formatterS(row, column,cellValue, index) {
        if(!row.bSerial){
          return '否';
        }else{
          return '是';
        }
        console.log(column);
        console.log(cellValue);
    },

  }
}
</script>

<style>
.example {
  color: red;
}
</style>
