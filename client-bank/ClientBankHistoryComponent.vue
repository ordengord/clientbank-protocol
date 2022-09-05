<template>
    <div>
        <h4 style="margin:20px;">История</h4>
        <div class="row">
            <div style="margin:30px;">
                <label for="from">Начало периода</label>
                <b-form-datepicker id="from" v-model="from" class="mb-2" locale="ru-Ru"></b-form-datepicker> <br />

                <label for="to">Конец периода</label>
                <b-form-datepicker id="to" v-model="to" class="mb-2" locale="ru-Ru"></b-form-datepicker> <br />

                <button class="btn btn-brand" @click="getHistory"> Показать</button>
            </div>
        </div>
        <div v-if="history.length">
            <b-list-group>
                <div v-for="(protocol) in history" style="margin:5px;">
                    <b-list-group-item href="#"  @click="uniq = protocol.uniq; protocol.clicked=!(protocol.clicked);">
                        Протокол от {{protocol.uploaded_at}} {{protocol.uploaded_by}}
                    </b-list-group-item>
                    <div v-if="protocol.clicked">
                        <client-bank-protocol-component :uniq="protocol.uniq" :editable="false"></client-bank-protocol-component>
                    </div>
                </div>
            </b-list-group>
        </div>
    </div>
</template>

<script>
    import axios from 'axios';
    import axiosConfig from "../axios-config";
    import ClientBankProtocolComponent from './ClientBankProtocolComponent';

    export default {
        name: "ClientBankHistoryComponent",
        components: {ClientBankProtocolComponent},
        data: function () {
            return {
                from: null,
                to: null,
                history: [],
                uniq: null
            }
        },
        methods: {
            getHistory: function () {
                axios({
                    method: 'GET',
                    url: '/rest/v1/clientbank/protocol/history?from='+this.from + '&to='+this.to,
                    headers: axiosConfig.getAxiosHeaders()
                })
                    .then(response => {
                        let history = response.data.data;
                        for (let protocol in history) {
                            history[protocol].clicked = false;
                        }
                        this.history = history;

                    })
                    .catch(error => {
                        console.log(error);
                    });
            }
        }
    }
</script>

<style scoped>

</style>