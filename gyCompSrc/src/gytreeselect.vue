å<!-- Vue SFC -->
<template>
    <div>
        <treeselect
                v-model="value"
                :options="treeData"
                :disable-branch-nodes="leafonly"
                :show-count="true"
                :placeholder="placeholder"
                @select="onSelect"
                @deselect="onDeSelect"
                @close="onClose"
                :multiple="multi"
        />
    </div>
</template>

<script>
    import axios from 'axios';
    // import the component
    import Treeselect from '@riophae/vue-treeselect'
    // import the styles
    import '@riophae/vue-treeselect/dist/vue-treeselect.css'

    export default {
        props:{
            'sysname':String,
            'placeholder':String,
            leafonly:Boolean,
            multi:{
                default:false,
                type:Boolean
             }
        },
        name:'gytreeselect',
        // register the component
        components: { Treeselect },
        created(){
            this.loadTree();
        },
        data(){
           return{
               value:null,
               treeData:[]
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
                        onError(_this,error);
                });
            },
            loadTree(){
                this.gyAjaxPost(
                    "/TreeMng/getTreeJsonbySysName",
                    "SysName="+this.sysname,
                    function(_this,response){
                        _this.treeData = [];
                        _this.treeData.push(response.data)
                    },function(_this,response){

                    }
                );
            },
            onSelect(node, instanceId){
                console.log("VALUE:===>");
                console.log(this.value);
                this.$emit("ongyselect",node);

            },
            onDeSelect(node,instanceId){
                this.$emit("ongydeselect",node);
            },onClose(v,i){
                console.log(v);
            },
            getVal(){
                var ret = '';
                if(this.value==null || this.value==undefined){
                    if(this.multi){
                        return [];
                    }else{
                        return ''
                    }
                }else{
                    return this.value;
                }
            },
            setVal(v){
                    if(this.multi){
                        if(v instanceof  Array){
                            this.value=v;
                        }else{
                            this.value = [v];
                        }
                    }else{
                        this.value=v;
                    }
            }
        }
    }
</script>