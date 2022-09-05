<template>
    <div>
        <div class="row">
            <div class="col-lg-4">
                <div v-if="!activeComponent && !uniq">
                    <form id="upload_form" method="POST" action="/?action=clientbank_protocol" enctype="multipart/form-data">
                        <b-form-file
                                v-model="file"
                                :state="Boolean(file)"
                                placeholder="Выберите файл"
                                drop-placeholder="Drop file here..."
                                accept=".xlsx, .txt"
                                ref="fileInput"
                                browse-text="Выбрать"
                                form="upload_form"
                                name="uploaded"
                        ></b-form-file>
                        <div class="mt-3">
                             {{ file ? 'Выбран файл: ' + file.name : '' }}
                            <a href="#" v-if="file && !activeComponent" @click="cancelFile()">
                                <sup class="delete-file-cross">x</sup>
                            </a>
                            <br />
                            <button class="btn btn-brand" v-if="file && !activeComponent" >Загрузить</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-4">
                <button class="btn btn-brand pull-right" type="button" @click="getHistory()"
                        v-if="!isHistory">
                    История
                </button>
                <button type="button" class="btn btn-brand pull-right"
                        @click="backToMain" v-if="isHistory === true">
                    Назад
                </button>
            </div>

            <hr class='my-4' />


        </div>
        <div class="row">
            <component :is="activeComponent"></component>
        </div>

    </div>
</template>

<script>
    import ClientBankHistoryComponent from './ClientBankHistoryComponent';
    import ClientBankProtocolComponent from './ClientBankProtocolComponent';

    export default {
        name: "ClientBankMainComponent",
        props: [
            'filedata', 'accountUid', 'uniq'
        ],
        data: function () {
            return {
                activeComponent: null,
                file: null,
                filePath: '',
                editedFileData: [],
                properties: null,
                isHistory: undefined
            }
        },
        components: {
            ClientBankHistoryComponent, ClientBankProtocolComponent
        },
        methods: {
            cancelFile: function () {
                this.$refs.fileInput.reset();
                this.file = null;
            },

            getStyle: function (payment) {
                let colorStyle = '';
                if (payment.forkdata.length > 1)
                    colorStyle = "green-text";
                if (payment.forkdata.length === 0)
                    colorStyle = "red-text";

                return colorStyle;
            },
            getHistory: function () {
                this.isHistory = true;
                this.activeComponent = ClientBankHistoryComponent;
                this.properties = this.editedFileData;
            },
            backToMain: function () {
                this.isHistory = undefined;
                this.activeComponent = this.uniq ? ClientBankProtocolComponent : null;
            }
        },
        created: function () {
            if (this.filedata) {
                this.editedFileData = this.filedata;
                this.activeComponent = ClientBankProtocolComponent;
            }

            if (this.uniq) {
                this.activeComponent = ClientBankProtocolComponent;
            }
        }

    }
</script>

<style scoped>
    .delete-file-cross {
        color:red;
        font-size: 10pt;
    }

    .orange_line {
        color:orange;
        line-height: 10px;
    }
</style>