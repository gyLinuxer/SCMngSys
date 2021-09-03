<template>
    <van-tabbar v-model="TBactive">
        <van-tabbar-item name="1" url="/QPSys/MBController/showXQSQ" icon="home-o">需求申请</van-tabbar-item>
        <van-tabbar-item name="2" url="/QPSys/MBController/showXQSP" icon="home-o">需求审批</van-tabbar-item>
        <van-tabbar-item name="3" url="/QPSys/MBController/showXQList" icon="home-o">需求列表</van-tabbar-item>
        <van-tabbar-item name="4" url="/QPSys/MBController/showACQry" icon="search">飞机查询</van-tabbar-item>
        <van-tabbar-item name="5" url="/QPSys/MBController/showCJJH" icon="friends-o">出机计划</van-tabbar-item>
        <van-tabbar-item name="6" url="/QPSys/MBController/AppList" icon="setting-o">个人中心</van-tabbar-item>
    </van-tabbar>
</template>

<script>
    export default {
        props:["act"],
        name: "mbnavbar",
        data() {
            return {
                TBactive: 0,
            };
        },
        methods: {},
        //通过路由跳转判断选中的样式
        created() {
            this.TBactive = this.act;
        }
    };
</script>

<style>
</style>
