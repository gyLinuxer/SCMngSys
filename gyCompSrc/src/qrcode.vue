<template>
        <div style="text-align: center">
            {{textContent}}
        <div style="width:284px;height: 235px;border:1px dashed blue">
            <video
                    ref="video"
                    id="video"
                    width="280"
                    height="200"
            >
            </video>
            <div style="text-align: center">
                <van-button size="small" type="warning" @click="ChangeCamera">切换</van-button>
                <van-button size="small" type="primary" @click="StartScan">扫描</van-button>
            </div>
        </div>

        </div>
</template>

<script>
    // eslint-disable-next-line no-unused-vars
    //import adapter from 'webrtc-adapter';
    // WebRTC适配器 只需要引入就ok
    import { BrowserMultiFormatReader } from '@zxing/library';
    /**
     * zxing demo
     */
    export default {
        data: () => ({
            codeReader: new BrowserMultiFormatReader(),
            textContent: '',
            cameraList:[],
            curDevIndex:0,
        }),
        created () {
            this.CameraInit();
        },
        methods: {
            CameraInit(){
                var _this = this;
                this.codeReader = new BrowserMultiFormatReader();
                this.codeReader.getVideoInputDevices()
                    .then((videoInputDevices) => {
                        console.log(videoInputDevices);
                        _this.cameraList = videoInputDevices;
                        console.log(_this.cameraList);
                        for (var i = 0; i < _this.cameraList.length; i++) {
                            if (_this.cameraList[i].label.indexOf("back") > 0) {
                                _this.curDevIndex = i;
                                break;
                            }
                        }
                        _this.StartScan();
                    })
                    .catch((err) => {
                        console.error(err);
                    });
            },
            StopScan(){
                this.codeReader.reset();
                this.codeReader.stopContinuousDecode();
            },
            StartScan(){
                var _this = this;
                if(this.cameraList.length===0){
                    alert("没有找到摄像头!");
                    return;
                }
                _this.codeReader.stopContinuousDecode();
                _this.codeReader.decodeFromVideoDevice(_this.cameraList[_this.curDevIndex].deviceId, 'video', (result, err) => {
                    if (result) {
                        console.log(result);
                        _this.textContent = result.text;
                        this.$emit("oncodescaned",_this.textContent);
                    }else(err && !(err))
                    {
                        //alert("错误:"+err);
                    }
                });
            },
            ChangeCamera(){
                this.curDevIndex = (this.curDevIndex+1) % (this.cameraList.length);
                this.StartScan();
            }
        }
    };
</script>

<style lang="scss" scoped>
</style>
