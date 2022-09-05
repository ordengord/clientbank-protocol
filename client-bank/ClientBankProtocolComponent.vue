<template>

    <div v-if="filedata">
        <h4 style="margin:20px;">Протокол загрузки</h4>
        <table class="table table-bordered">
            <thead>
            <tr>
                <th v-if="isEditable">Вкл</th>
                <th>
                    Плательщик
                </th>
                <th>
                    ИНН
                </th>
                <th>
                    Сумма
                </th>
                <th>
                    Дата
                </th>
                <th>
                    №
                </th>
                <th>
                    ID
                </th>
                <th>
                    Название
                </th>
                <th>
                    Статус абонента
                </th>
                <th style="min-width: 150pt;">
                    Получатель
                </th>
            </tr>
            </thead>
            <tbody v-for="(payment) in filedata">
            <tr :class="getClass(payment, 0)">
                <td v-if="isEditable">
                    <div v-if="payment.forkdata.length">
                        <label class="m-checkbox">
                            <input type='checkbox' v-model="payment.forkdata[0].amount_pp != 0"
                                @change="cancelPayment(payment)"
                            />
                            <span></span>
                        </label>
                    </div>
                    <div v-else>
                        <label class="m-checkbox">
                            <input type='checkbox' v-model="payment.amount_pp != 0" @change="cancelPaymentEntirely(payment)"
                            />
                            <span></span>
                        </label>
                    </div>
                </td>
                <td>
                    <span :title="payment.purpose"> {{payment.customer}}</span>
                </td>
                <td>
                    {{payment.inn}}
                </td>
                <td>
                    <div v-if="isEditable">
                        <span v-if="payment.forkdata[0]">
                            <b-form-input type='number' v-model="payment.forkdata[0].amount_pp"
                                 readonly>
                            </b-form-input>
                        </span>
                        <span v-else>
                            <b-form-input type='number' :value="payment.amount_pp" readonly></b-form-input>
                        </span>
                    </div>
                    <span v-else>{{payment.forkdata[0] ? payment.forkdata[0].amount_pp : 0}}</span>
                </td>
                <td>
                    {{payment.date_pp}}
                </td>
                <td>
                    {{payment.num_pp}}
                </td>
                <td>
                    <a v-if="isUidFound(payment.forkdata[0])" v-bind:href="'/?action=view_uc&id='+payment.forkdata[0].uid" target="_blank">
                        {{payment.forkdata[0].uid}}</a>
                    <span v-else>Н/Д</span>
                </td>
                <td>
                    {{payment.forkdata[0] ? payment.forkdata[0].full_name : ''}}
                </td>
                <td>
                    <span :class="getStatusColor(payment.forkdata[0])">{{payment.forkdata[0] ? payment.forkdata[0].status : ''}}</span>
                </td>
                <td>
                    <div v-if="isEditable">
                        <div v-if="payment.forkdata[0]">
                            <select class="form-control" v-model="payment.forkdata[0].cname" @change = "notChangedAfterSave = false;">
                                <option v-for="(company) in companies" :value="company">
                                    {{company}}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div v-else>
                        {{payment.forkdata[0] ? payment.forkdata[0].cname : ''}}
                    </div>
                </td>
            </tr>
            <tr :class="getClass(payment, index)" v-for="(userData, index) in payment.forkdata" v-if="index>0">
                <td v-if="isEditable">
                </td>
                <td>
                </td>
                <td>
                </td>
                <td>
                    <b-form-input type='number' v-if="isEditable" v-model="userData.amount_pp"
                        @change="changeAmount(payment, userData, userData.amount_pp)"
                        @click="userData.amount_pp = removeZeroIfNeeded(userData.amount_pp)"
                        @blur = "userData.amount_pp=insertZeroIfNeeded(userData.amount_pp)"
                        :readonly="userData.disable_all === true">
                    </b-form-input>
                    <span v-else>{{userData.amount_pp}}</span>
                </td>
                <td>
                    {{userData.date_pp}}
                </td>
                <td>
                    {{userData.num_pp}}
                </td>
                <td v-if="index > 0">
                    <a v-if="isUidFound(userData)" v-bind:href="'/?action=view_uc&id='+userData.uid" target="_blank">
                        {{userData.uid}}</a>
                    <span v-else>Н/Д</span>
                </td>
                <td v-if="index > 0">
                    {{userData.full_name}}
                </td>
                <td v-if="index > 0">
                    <span :class="getStatusColor(userData)">{{userData.status}}</span>
                </td>
                <td v-if="index > 0">
                    <select class="form-control" v-model="userData.cname" v-if="isEditable" @change = "notChangedAfterSave = false;">
                        <option v-for="(company) in companies" :value="company">
                            {{company}}
                        </option>
                    </select>
                    <span v-else>{{userData.cname}}</span>
                </td>
            </tr>
            </tbody>
        </table>
        <div v-if="isEditable">
            <h5>Управление протоколом </h5>
            <button class="btn btn-brand" @click="check" type="button" v-if="currentUniq && notChangedAfterSave && !completed" title="Найдет id (если ранее не найден) абонента и изменит значение в столбце ИНН">Проверить ИНН</button>
            <button class="btn btn-brand" @click="findDoubles" type="button" v-if="currentUniq && notChangedAfterSave && !completed" title="Предварительно сохраните изменения">
                Найти дубли
            </button>

            <button class="btn btn-brand" @click="saveChanges" title="Сохранить изменения. Иначе при перезагрузке все изменения будут утеряны">Сохранить</button>
            <button class="btn btn-brand" @click="deleteProtocol" type="button" v-if="currentUniq && !completed">
                Удалить протокол
            </button>
            <button class="btn btn-brand" @click="pay" type="button" :disabled="!notChangedAfterSave || completed">Провести платежи</button>
            <button class="btn btn-brand" type="button" :hidden="!completed" @click="$parent.activeComponent = null; $parent.uniq = null;">К выбору файла</button>
            <button class="btn btn-brand" v-if="!completed" type="button" :hidden="!currentUniq || notChangedAfterSave"
                    @click="getProtocolByUniq(currentUniq)">
                К последнему сохранению
            </button>
            <button class="btn btn-brand" type="button" v-if="!currentUniq" @click="$parent.activeComponent=null;">
                Отмена
            </button>


        </div>
        <div>
            <p class="message_log">
                <span v-for="message in logMessage">
                    {{message}} <br />
                </span>
            </p>
        </div>
    </div>
</template>

<script>
    import axios from 'axios';
    import axiosConfig from "../axios-config";

    export default {
        name: "ClientBankProtocolComponent",
        props: ['editable'],
        data: function () {
            return {
                editedFileData: null,
                filedata: [],
                currentUniq: null,
                isEditable: true,
                notChangedAfterSave: true,
                logMessage: [],
                completed: false,
                companies: [
                    "ООО Владлинк",
                    "ООО Владлинк Регион",
                    "ООО Владлинк Бизнес",
                    "ООО Владивостокская сеть",
                    "ООО Владлинк телеком",
                    "ООО Владлинк Восток",
                    "ИП Бараков Алексей Сергеевич"
                ]
            }
        },
        methods: {
            getClass: function (payment, index) {
                let colorStyle = '';
                if (payment.forkdata.length > 1)
                    colorStyle = "green-text";
                if (payment.forkdata.length == 0) {
                    colorStyle = "red-text purple_bg";
                    return colorStyle;
                }

                if (!payment.amount_pp) {
                    colorStyle += " purple_bg";
                }
                if (payment.forkdata[index]) {
                    if (payment.forkdata[index].amount_pp == 0) {
                        colorStyle += " purple_bg";
                    }
                }

                return colorStyle;
            },

            getProtocolByUniq: function (uniq) {
                axios({
                    method: 'GET',
                    url: '/rest/v1/clientbank/protocol/load?uniq=' + uniq,
                    headers: axiosConfig.getAxiosHeaders()
                })
                    .then(response => {
                        this.filedata = response.data.data;
                        this.notChangedAfterSave = true;
                    })
                    .catch(error => {
                        console.log(error);
                    });
            },

            pay: function () {
                axios({
                    method: 'PUT',
                    url: '/rest/v1/clientbank/protocol/pay',
                    data: {
                        data:
                            {
                                uniq: this.currentUniq
                            }
                    },
                    headers: axiosConfig.getAxiosHeaders()
                }).then(response => {
                    this.logMessage = [];
                    if (response.data.data.length)
                        this.logMessage = response.data.data;
                    else
                        this.logMessage.push('Платежей не проведено');

                    this.completed = true;
                });
            },

            isUidFound: function (parsedPayment) {
                if (parsedPayment) {
                    if (parsedPayment.uid) {
                        return true;
                    }
                }

                return false;
            },

            changeAmount: function (source, element, newAmount) {
                newAmount = parseFloat(newAmount);

                if (newAmount <= source.amount_pp && newAmount > 0 && parseFloat(source.forkdata[0].amount_pp) >= newAmount) {
                    source.forkdata[0].amount_pp -= newAmount;
                    element.amount_pp = newAmount;
                    element.date_pp = source.date_pp;
                    element.num_pp = source.num_pp;
                } else {
                    for (let index in source.forkdata) {
                        source.forkdata[index].amount_pp = 0;
                        source.forkdata[index].date_pp = null;
                        source.forkdata[index].num_pp = null;
                    }
                    source.forkdata[0].amount_pp = source.amount_pp;
                }
                if (this.currentUniq)
                    this.notChangedAfterSave = false;
            },

            saveChanges: function () {
                axios({
                    method: 'POST',
                    url: '/rest/v1/clientbank/protocol/save',
                    data: {
                        data:
                            {
                                allData: this.filedata,
                                uniq: this.currentUniq
                            }
                    },
                    headers: axiosConfig.getAxiosHeaders()
                }).then(response => {
                    this.currentUniq = response.data.data.uniq;
                    this.notChangedAfterSave = true;
                    this.getProtocolByUniq(this.currentUniq);
                });
            },

            findDoubles: function () {
                axios({
                    method: 'GET',
                    url: '/rest/v1/clientbank/protocol/doubles?uniq=' + this.currentUniq,
                    headers: axiosConfig.getAxiosHeaders()
                })
                    .then(response => {
                        this.logMessage = [];
                        let doubles = response.data.data['doubles'];
                        console.log(doubles);

                        let forkdata = null;
                        for (let index in this.filedata) {
                            for (let num in this.filedata[index].forkdata) {
                                forkdata = this.filedata[index].forkdata[num];
                                let entry = doubles.filter((x) => {
                                    return x.summa == forkdata.amount_pp
                                        && x.date_pp == forkdata.date_pp
                                        && x.num_pp == forkdata.num_pp;
                                });
                                let message = '';
                                if (entry.length) {
                                    message = "Дублирование: Абонент " + forkdata.uid + ". Платеж № "
                                        + forkdata.num_pp + " суммой: " + forkdata.amount_pp + " р. от " + forkdata.date_pp;
                                    this.filedata[index].forkdata[num].amount_pp = 0;
                                    this.filedata[index].forkdata[num].num_pp = null;
                                    this.filedata[index].forkdata[num].date_pp = null;
                                    this.notChangedAfterSave = false;
                                }
                                if (message.length)
                                    this.logMessage.push(message);
                            }
                        }
                        if (this.logMessage.length)
                            this.logMessage.push('Значение в столбце \"Сумма\" у задублированных платежей изменено на 0.');
                        else
                            this.logMessage.push('Дублирования не обнаружено');

                    })
                    .catch(error => {
                        console.log(error);
                    });
            },

            check: function () {
                axios({
                    method: 'POST',
                    url: '/rest/v1/clientbank/protocol/check',
                    data: {
                        data:
                            {
                                allData: this.filedata
                            }
                    },
                    headers: axiosConfig.getAxiosHeaders()
                })
                    .then(response => {
                        this.filedata = response.data.data;
                        if (this.currentUniq)
                            this.notChangedAfterSave = false;
                    });
            },

            cancelPaymentEntirely : function (payment) {
                if (payment.amount_pp_save) {
                    payment.amount_pp = payment.amount_pp_save;
                    payment.amount_pp_save = undefined;
                }
                else {
                    payment.amount_pp_save = payment.amount_pp;
                    payment.amount_pp = 0;
                }
                if (this.currentUniq)
                    this.notChangedAfterSave = false;
            },

            cancelPayment: function (payment) {
                if (payment.amount_pp_save == undefined) {
                    for (let index in payment.forkdata) {
                        payment.forkdata[index].amount_pp = 0;
                        payment.forkdata[index].disable_all = true;
                    }
                    payment.amount_pp_save = payment.amount_pp;
                    payment.amount_pp = 0;
                }
                else {
                    payment.amount_pp = payment.amount_pp_save;
                    for (let i=0; i < payment.forkdata.length; i++) {
                        if (i == 0) {
                            payment.forkdata[i].amount_pp = payment.amount_pp;
                        }
                        else {
                            payment.forkdata[i].amount_pp = 0;
                            payment.forkdata[i].disable_all = false;

                        }
                    }
                    payment.amount_pp_save = undefined;
                }

                if (this.currentUniq)
                    this.notChangedAfterSave = false;
            },

            deleteProtocol: function () {
                axios({
                    method: 'DELETE',
                    url: '/rest/v1/clientbank/protocol/delete',
                    data: {
                        data:
                            {
                                uniq: this.currentUniq
                            }
                    },
                    headers: axiosConfig.getAxiosHeaders()
                })
                    .then(response => {
                        let result = response.data.data;
                        if (result)
                            this.$parent.activeComponent = null;
                        else{
                            this.logMessage = [];
                            this.logMessage.push("Не удалось удалить протокол");
                        }
                    });
            },

            removeZeroIfNeeded: function (amount) {
                if (amount == 0) {
                    amount = ''
                }
                return amount;
            },

            insertZeroIfNeeded: function (amount) {
                if (amount == '') {
                    amount = 0
                }
                return amount;
            },

            getStatusColor: function (payment) {
                if (payment == undefined)
                    return "";
                if (payment.status=='Действующий абонент')
                    return "blue-text";
            }
        },
        created: function () {
            this.isEditable = this.editable === false ? false : true;
            if (this.$parent.uniq) {
                this.notChangedAfterSave = true;
                this.currentUniq = this.$parent.uniq;
                this.filedata = this.getProtocolByUniq(this.currentUniq);
            } else if (this.$parent.editedFileData) {
                this.filedata = this.$parent.editedFileData;
                this.notChangedAfterSave = false;
            }
        }
    }
</script>

<style scoped>
    .green-text {
        color:green;
    }
    .red-text {
        color: red;
    }
    .blue-text {
        color:blue;
    }
    .purple_bg {
        background-color: lightblue;
    }

    .message_log {
        font: 16px solid serif;
    }

    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
    }

</style>