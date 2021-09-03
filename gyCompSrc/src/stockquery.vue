<template>
  <div>
    <el-row>
      <el-form :inline="true" class="demo-form-inline">
        <el-form-item label="仓库:">
          <el-select allow-create size="medium" v-model="curcWhCode" placeholder="请选择">
             <el-option
               v-for="item in WareHouseList"
               :key="item.cWhCode"
               :label="item.cWhCode+'-'+item.cWhName"
               :value="item.cWhCode">
             </el-option>
           </el-select>
        </el-form-item>
        <el-form-item label="货位:">
          <el-input v-model="curPosCode" style="width:150px;" size="medium" ></el-input>
        </el-form-item>
        <el-form-item label="件号:">
          <el-input v-model="cinvcodeqry" style="width:150px;" size="medium" :disabled="invcodelock"> </el-input>
        </el-form-item>
        <el-form-item label="名称:">
          <el-input v-model="cInvName" style="width:150px;" size="medium" > </el-input>
        </el-form-item>
          <el-form-item label="批次号:">
              <el-input v-model="curcBatch" style="width:150px;" size="medium"> </el-input>
          </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="onSubmit" >查询</el-button>
        </el-form-item>
        <el-form-item>
          <span><span style="color:red;">*</span>只显示洛阳可用库存</span>
        </el-form-item>
      </el-form>
    </el-row>
      <el-row>
        <template>
            <el-table
              v-loading="loading"
              :data="tableData"
              :max-height="calcMaxHeight"
              @sort-change="onsort"
              stripe
              border
              style="width: 140%;"
              >
                <el-table-column
                        prop="cWhName"
                        label="仓库"
                        sortable
                        width="180">
                </el-table-column>
                <el-table-column
                        prop="cPosCode"
                        label="货位"
                        sortable
                        width="180">
                </el-table-column>
              <el-table-column
                      prop="cInvCode"
                      label="件号"
                      sortable
                      width="180">
              </el-table-column>
              <el-table-column
                      prop="cInvName"
                      label="名称"
                      sortable
                      width="180">
              </el-table-column>
              <el-table-column
                      prop="bSerial"
                      label="序列号管理"
                      sortable
                      width="180">
                <template slot-scope="scope">
                  <el-tag effect="dark" size="mini"  :type="scope.row.bSerial?'success':'info'">{{scope.row.bSerial?'是':'否'}}</el-tag>
                </template>
              </el-table-column>
              <el-table-column
                prop="cFree1"
                label="状态"
                sortable
                width="180">
              </el-table-column>
              <el-table-column
                      prop="cBatch"
                      label="批次"
                      sortable
                      width="180">
              </el-table-column>
              <el-table-column
                prop="iQuantity"
                label="可用量">
                sortable
                <template slot-scope="scope">
                  <el-popover
                          placement="right"
                          width="150"
                          trigger="click"
                          :key="scope.$index"
                  >
                    <el-checkbox-group v-model="SNSelArr" v-show="scope.row.bSerial==true">
                      <el-row>
                        <el-checkbox v-for="item in curSNListArr" :label="item.cInvSN" :key="item.cInvSN">{{item.cInvSN}}</el-checkbox>
                      </el-row>
                    </el-checkbox-group>
                    <el-row v-show="scope.row.bSerial==false">
                      <el-form>
                        <el-form-item label="出库数量:">
                          <el-input type="number" v-model="ckiQuantity"></el-input>
                        </el-form-item>
                      </el-form>
                    </el-row>
                    <el-link icon="el-icon-edit" slot="reference" @click="iQuantityClk(scope.row)">{{scope.row.iQuantity}}</el-link>
                  </el-popover>

                </template>
              </el-table-column>
              <el-table-column
                      prop="dMakeDate"
                      width="180"
                      sortable
                      label="生产日期">
              </el-table-column>
              <el-table-column
                prop="dMassDateEen"
                width="180"
                label="到寿日期">
              </el-table-column>
            </el-table>
        </template>
      </el-row>
    <el-row>
      <el-pagination
              @size-change="handleSizeChange"
              @current-change="handleCurrentChange"
              :current-page="currentPage"
              :page-sizes="[100, 200, 300,400,500]"
              :page-size="pagesize"
              layout="total, sizes, prev, pager, next, jumper"
              :total=parseInt(total)>
      </el-pagination>
    </el-row>
    <el-row  style="margin-top: 15px;" v-show="this.cinvcodeqry!=undefined&&curClkRow.cPosCode!=null &&curClkRow.cPosCode!=undefined ">
      <el-tag type="primary" effect="dark">{{curClkRow.cWhName}}</el-tag>   <el-tag type="success" effect="dark">货位:{{curClkRow.cPosCode}}</el-tag>   <el-tag type="warning" effect="dark">状态:{{curClkRow.cFree1}}</el-tag>     <el-tag type="info" effect="dark">{{curClkRow.cBatch}}</el-tag>  <el-tag type="danger" v-show="this.sqiquantity!=null&&this.sqiquantity!=undefined">{{this.sqiquantity!=null&&this.sqiquantity!=undefined?'申请数量:'+this.sqiquantity:''}}</el-tag>   <el-tag type="danger" effect="dark">出库数量:{{!curClkRow.bSerial?ckiQuantity:SNSelArr.length}}</el-tag>   {{curClkRow.bSerial?JSON.stringify(SNSelArr):''}}
      <el-button type="success" @click="onSelectOK" >确定</el-button>
    </el-row>

  </div>


</template>

<script>
import axios from 'axios';
export default {
  props:{
    'cinvcodeqry':String,
    'invcodelock':Boolean,
    'sqiquantity':Number,
    'curprowindex':Number,
  },
  name:'stockquery',
  data () {
    return {
        orderyField:'',
        orderASC:true,
        currentPage:1,
        pagesize:100,
        total:1,
        lastInvCode:'',
        cInvName:'',
        SNSelArr:[],
        curSNListArr:[],
        curcWhCode:'',
        curPosCode:'',
        curcBatch:'',
        WareHouseList:[],
        tableData:[],
        loading:false,
        ckiQuantity:0,
        curClkRow:{},
        LYOnly:true,
    }
  },
  created(){
    this.getWareHouseList();
    if(this.cinvcodeqry==undefined){
        this.cinvcodeqry = '';
    }
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
    getWareHouseList(){
      this.gyAjaxPost("/system/warehouse/query","",
        function (_this,response) {
            _this.WareHouseList = response.data.body;
        },null);
    },
    updateData(){
      if(this.curWareHouse=='' && this.curPosCode=='' && this.cinvcodeqry==''&& this.curcBatch==''){
         this.$message('请至少输入一项搜索条件，防止数据量过大而崩溃!');
         return;
      }
      this.loading = true;
      this.gyAjaxPost("/system/invPositionSum/JQStockQuery"
              ,"cInvCode="+this.cinvcodeqry+"&cWhCode="+this.curcWhCode
              +"&cPosCode="+this.curPosCode+"&cBatch="+this.curcBatch
              +"&cInvName="+this.cInvName+"&Page="
              +this.currentPage+"&PageSize="+this.pagesize+"&LYOnly="+this.LYOnly
              +(this.orderyField==""?"":"&Orderby="+this.orderyField+"&orderASC="+this.orderASC)
      ,
        function (_this,response) {
            _this.tableData = response.data.body.records;
            _this.loading = false;
            _this.curClkRow = {};
            _this.SNSelArr = [];
            if((_this.currentPage-1)*_this.pagesize >= _this.total){
              _this.currentPage = 1;
            }
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
       var row = this.curClkRow;

       if(this.cinvcodeqry==undefined){
          return;
       }
        if(row.bSerial==true){
            this.ckiQuantity = this.SNSelArr.length;
        }

        if(this.ckiQuantity<=0){
            this.$message('没有选择要出库的航材!');
            return;
        }

        if(this.ckiQuantity>this.curClkRow.iQuantity){
            this.$message('航材出库量大于可用量!');
            return;
        }

        if(this.sqiquantity!=null && this.sqiquantity!=undefined && this.sqiquantity>0 && this.ckiQuantity>this.sqiquantity){
            this.$message('航材的出库数量大于申请数量!');
            return;
        }


        row.ckiQuantity= this.ckiQuantity;
        row.SNSelArr   = this.SNSelArr;
        row.curPRowIndex = this.curprowindex;
        row.curbSerial  = this.curbSerial;
       this.$emit("onsendselection",row);
     },
     formatterS(row, column,cellValue, index) {
        if(!row.bSerial){
          return '否';
        }else {
          return '是';
        }
    }
    ,
    iQuantityClk(row){
            this.curClkRow = row;
            this.SNSelArr = [];

            if(row.bSerial==true){
              this.gyAjaxPost("/system/sTSNState/getSNListByStockQueryClass",
                      "cInvCode="+row.cInvCode+"&cWhCode="+row.cWhCode
                      +"&cPosition="+row.cPosCode+"&cBatch="+row.cBatch+"&cFree1="+row.cFree1,
                      function (_this,response) {
                        _this.curSNListArr = response.data.body;
                      },
                      function(_this,data){

                      });
            }

          },
    handleSizeChange(val) {
      this.pagesize = val;
      this.updateData();
    },
    handleCurrentChange(val) {
      this.currentPage = val;
      this.updateData();
    },
    onsort(a){
      this.orderyField = a.prop;
      if(a.order=='descending'){
        this.orderASC = false;
      }else{
        this.orderASC = true;
      }
      this.updateData();
    }
  }
}
</script>

<style>

</style>
