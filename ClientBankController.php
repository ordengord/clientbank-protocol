<?php
/**
 * Created by PhpStorm.
 * User: zenkin
 * Date: 27.02.20
 * Time: 17:42
 */

namespace Vladlink\Rest\Controllers\Cash;

use Vladlink\Rest\Controllers\RestController;
use Vladlink\Rest\Response\DataRestResponse;
use Vladlink\Rest\RestRequest;
use VladlinkMVC\Classes\Cash\ClientBankProtocol;

/**
 * Контроллер загрузки и работы с протоколом
 * клиентбанка
 * Class ClientBankController
 * @package Vladlink\Rest\Controllers\Cash
 */
class ClientBankController extends RestController
{
    /**
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function check(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['allData' => 'required']
        );

        $clientBank = new ClientBankProtocol();

        $result = $clientBank->prepareData($data['allData'], false);

        return DataRestResponse::make ($result);
    }

    /**
     * Сохранение всех изменений протокола
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function save(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['allData' => 'required', 'uniq' => 'string|nullable']
        );
        $clientBank = new ClientBankProtocol();
        $result = $clientBank->saveProtocol($data, $data['uniq']);

        return DataRestResponse::make (['uniq' => $result]);
    }

    /**
     * Найти задублированные (уже оплаченные) платежи
     * Либо по uniq текущего протокола, либо за полгода в зависимости от отправителя запроса
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function findDoubles(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['uniq' => 'string|nullable']
        );
        $clientBank = new ClientBankProtocol();

        $doubles = $data['uniq'] ? $clientBank->findDoublesByUniq($data['uniq']) : $clientBank->findDoublesWithinHalfYear();

        return DataRestResponse::make (['doubles' => $doubles]);
    }

    /**
     * Провести платеж
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function pay(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['uniq' => 'string']
        );

        $clientBank = new ClientBankProtocol();
        $resultMessage = $clientBank->pay($data['uniq']);
        return DataRestResponse::make($resultMessage);
    }

    /**
     * Получаем информацию о платежах по cash.pays_from_csv.uniq
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function getProtocolByUniq(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['uniq' => 'string']
        );

        $clientBank = new ClientBankProtocol();
        $result = $clientBank->getProtocolByUniq($data['uniq']);
        return DataRestResponse::make ($result);
    }

    /**
     * Получить историю протоколов за период
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function getHistory(RestRequest $request)
    {
        $data = $this->make(
            $request->all(),
            ['from' => 'required', 'to' => 'required']
        );
        $clientBank = new ClientBankProtocol();
        $historyForPeriod = $clientBank->getHistory($data['from'], $data['to']);
        return DataRestResponse::make($historyForPeriod);
    }

    /**
     * Удалить протокол (по cash.pays_from_csv.uniq)
     * @param RestRequest $request
     * @return DataRestResponse
     */
    public function deleteProtocol(RestRequest $request)
    {
        $data = $this->make (
            $request->all(),
            ['uniq' => 'string']
        );
        $clientBank = new ClientBankProtocol();
        $result = $clientBank->deleteProtocol($data['uniq']);
        return DataRestResponse::make($result);
    }
}