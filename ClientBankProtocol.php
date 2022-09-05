<?php
/**
 * Created by PhpStorm.
 * User: zenkin
 * Date: 02.03.20
 * Time: 10:26
 */

namespace VladlinkMVC\Classes\Cash;

use Vladlink\DB\DBConnection;
use VladlinkMVC\Classes\PublicM\UserCardHelper;

/**
 * Вся функциональность для работы с загруженным протоколом
 * Class ClientBankProtocol
 * @package VladlinkMVC\Classes\Cash
 */
class ClientBankProtocol
{
    /**
     * Подключение к пгхост
     * @var bool|resource
     */
    protected $db;

    /**
     * ClientBankProtocol constructor.
     */
    public function __construct()
    {
        $this->db = DBConnection::pghost();
    }

    /**
     * Сохранение протокола в БД
     * @param array $data - массив данных
     * @param string|null $uniq - cash.pays_from_csv.uniq
     * @return bool|string|null
     */
    public function saveProtocol(array $data, string $uniq = null)
    {
        pg_query($this->db, 'begin');

        if ($uniq) {
            $sql = "DELETE FROM cash.pays_from_csv WHERE uniq = $1";
            pg_query_params($this->db, $sql, [$uniq]);
        }
        else {
            $uniq = md5(time());
        }

        $sql = "SELECT MAX(id) FROM cash.pays_from_csv";
        $res = pg_query($this->db, $sql);
        list($currentId) = pg_fetch_row($res);
        $toInsert = [];
        $unique = [];
        array_walk($data['allData'], function ($row) use (&$toInsert, $uniq, &$unique, &$currentId) {
            $date = \DateTime::createFromFormat('d.m.Y',$row['date_pp']);
            $dateFormatted = $date->format('Y-m-d 0:0:0');

            if (!count($row['forkdata'])) {
                $toInsertOne = [
                    ++$currentId,
                    'null',
                    $row['amount_pp'],
                    "'" .$dateFormatted . "'",
                    $row['num_pp'],
                    'null',
                    "'" .'автоматически' . "'",
                    'NOW()',
                    'false',
                    "'" . $uniq . "'",
                    "'" . $row['customer'] . "'",
                    "'" . $row['inn'] . "'",
                    "'" . $row['purpose'] . "'"
                ];
                $toInsert[] = "(" . implode(', ', $toInsertOne) . ")";
            }
            else {
                array_walk($row['forkdata'], function ($oneUid) use (&$toInsert, $row, $uniq, &$currentId, $dateFormatted) {
                    $toInsertOne = [
                        ++$currentId,
                        $oneUid['uid'],
                        $oneUid['amount_pp'] ?? 0,
                        "'" .$dateFormatted . "'",
                        $oneUid['num_pp'] ?? $row['num_pp'],
                        "'". $oneUid['cname'] . "'",
                        "'" .'автоматически' . "'",
                        'NOW()',
                        'false',
                        "'" . $uniq . "'",
                        "'" . $row['customer'] . "'",
                        "'" . $row['inn'] . "'",
                        "'" . $row['purpose'] . "'"
                    ];
                    $toInsert[] = "(" . implode(', ', $toInsertOne) . ")";
                });
            }
        });

        $insertStr = implode(', ', $toInsert);

        $sql = "INSERT INTO cash.pays_from_csv 
          (id, public_uid, summa, date_pp, num_pp, company, comment, date_upload, inserted, uniq, customer_by_file, inn_by_file, payment_purpose)
        VALUES $insertStr";
        $res = pg_query(DBConnection::pghost(), $sql);

        if ($res) {
            pg_query($this->db, 'commit');
            return $uniq;
        }
        else {
            pg_query($this->db, 'rollback');
            return false;
        }

    }

    /**
     * Поиск платежей в файле которые уже были проведены
     * @param string $uniq - cash.pays_from_csv
     * @return array
     */
    public function findDoubles(string $uniq)
    {
        $sql = "
            WITH uniqs AS (
                SELECT * FROM cash.pays_from_csv
                WHERE uniq=$1
            )
            SELECT 
                uniqs.public_uid, uniqs.summa, 
                to_char(uniqs.date_pp, 'DD.MM.YYYY') date_pp, 
                uniqs.num_pp, csv.doubles from uniqs
            LEFT JOIN (
                SELECT summa, date_pp, num_pp, count(*) AS doubles
                FROM cash.pays_from_csv
                WHERE inserted
                GROUP BY 1,2,3
            ) csv ON csv.summa=uniqs.summa AND csv.date_pp=uniqs.date_pp AND csv.num_pp=uniqs.num_pp
            WHERE doubles IS NOT NULL
        ";
        $res = pg_query_params($this->db, $sql, [$uniq]);

        return pg_num_rows($res) ? pg_fetch_all($res) : [];
    }

    /**
     * Провести платежи
     * @param string $uniq
     * @return array
     */
    public function pay(string $uniq)
    {
        $sql = "
		SELECT cp.id cp_id, cp.public_uid, cp.summa, cp.date_pp, cp.num_pp, wc.id company_id,cp.comment, ulogin,
		to_char(cp.date_pp, 'DD.MM.YYYY') date_pp_cut
		FROM cash.pays_from_csv cp
		join public.users u on u.id=cp.public_uid
		join warehouse.companies wc on replace(wc.cname,'&quot;','')=replace(cp.company,'\"','')
		WHERE NOT inserted and cp.date_pp is not null AND cp.num_pp IS NOT NULL
		and uniq ='$uniq' and summa > 0 and cp.public_uid IS NOT NULL";
        $res= pg_query($this->db, $sql);
        
        $toBePaid = pg_num_rows ($res) ? pg_fetch_all($res) : [];

        $resultMessage = [];

        pg_query($this->db, 'begin');

        foreach ($toBePaid as $payment) {
            $responseVueMessage = "Абонент {$payment['public_uid']}. Сумма оплаты ({$payment['summa']}) № П/П {$payment['num_pp']} от {$payment['date_pp_cut']}";
            $comment = $payment['comment'] . "Платеж безналом ЮЛ № ПП <b>{$payment['num_pp']}</b>";

            $sqlData = [
                $payment['ulogin'],
                $payment['summa'],
                $_SESSION['login'],
                'Сashless payment',
                $comment,
                $payment['public_uid'],
                -6,
                $_SESSION['man_id'],
                $payment['company_id'],
                $payment['date_pp']
            ];
            $sql = "INSERT INTO bills_history
                      (ulogin, qnt, who, what, comments, uid, ofid, added_by, comp_id, real_pay_date)
                    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,unix_timestamp($10)) RETURNING id";
            $res = pg_query_params($this->db, $sql, $sqlData);
            list ($billsId) = pg_fetch_row($res);
            if ($billsId) {
                $sql = "UPDATE cash.pays_from_csv SET inserted=true, bh_id=$1 WHERE id=$2 and uniq=$3";
                $res = pg_query_params($this->db, $sql, [$billsId, $payment['cp_id'], $uniq]);
                if ($res) {
                    $userCardHelper = new UserCardHelper($payment['public_uid']);
                    $userCardHelper->addComment($_SESSION['man_id'], $comment, 10);
                    $sql = "UPDATE users SET bill=bill + $1 WHERE id = $2";
                    pg_query_params($this->db, $sql, [$payment['summa'], $payment['public_uid']]);
                    $logText = "<b>Внесен платеж:</b><br>$comment";
                    $userCardHelper->addUserCardLog($_SESSION['man_id'], 1, $logText);
                    $resultMessage['success'][] = "Оплата прошла успешно: " . $responseVueMessage;
                }
                else
                    $resultMessage['fail'][] = "Оплата не прошла: " . $responseVueMessage ;
            } else {
                $resultMessage['fail'][] = "Оплата не прошла: " . $responseVueMessage ;
            }
        }

        pg_query($this->db, 'commit');
        $sql = "
            SELECT summa, public_uid, num_pp, 
            to_char(date_pp, 'DD.MM.YYYY') date_pp
            FROM cash.pays_from_csv 
            WHERE uniq=$1 AND summa > 0 AND public_uid IS NOT NULL AND (bh_id IS NULL OR NOT inserted)";
        $res = pg_query_params($this->db, $sql, [$uniq]);
        $fails = pg_num_rows($res) ? pg_fetch_all($res) : [];
        foreach ($fails as $fail) {
            $message = "Ненулевая оплата не прошла: Абонент {$fail['public_uid']}. Сумма оплаты ({$fail['summa']}) № П/П {$fail['num_pp']} от {$fail['date_pp_cut']}";
            $resultMessage['fail'][] = $message;
        }
        return array_merge($resultMessage['success'] ?? [], $resultMessage['fail'] ?? []);
    }

    /**
     * Парсит файл txt/xlsx
     * @param $file
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function parseFile($file)
    {
        $parsedData = [];

        switch ($file['type']) {
            case 'text/plain':
                $parsedData = ClientBankProtocolParser::txt($file);
                break;
            default:
                $parsedData = ClientBankProtocolParser::xlsx($file);
        }

        return $parsedData;
    }

    /**
     * Функция приводит данные после загрузки файла к формату для представления в vue
     * Если найдено несколько абонентов по одному инн - вся сумма закидывается на первого
     * Остальные - 0, но они тоже мониторятся
     * @param $parsedData
     * @param bool $isInitial
     * @return mixed
     */
    public function prepareData($parsedData, $isInitial = true)
    {
        $inns = array_column($parsedData, 'inn');
        array_walk($inns, function (&$inn) {
            $inn = "'" . $inn . "'";
        });
        $innsList = implode (',', $inns);


        $sql = "SELECT replace(cname, '&quot;', '') cname, jur_inn FROM warehouse.companies";
        $res = pg_query($this->db, $sql);
        $companies = [];
        while (list ($cname, $ourInn) = pg_fetch_array($res)) {
            $companies[$ourInn] = $cname;
        }
        array_walk($preparedData, function (&$payment) use (&$companies) {
            $payment['receiver'] = $companies[$payment['our_company_inn']];
        });

        $sql = "
            SELECT s.company_inn, u.id uid, replace(u.full_name, '&quot;', '\"') full_name, us.name status 
            FROM account.subscribers s
            INNER JOIN users u ON u.id=s.public_uid
            LEFT JOIN user_statuses us ON us.id=u.status
            WHERE s.public_uid IS NOT NULL AND s.public_uid > 0 AND
            s.company_inn IN ($innsList)
            ORDER BY u.id
        ";
        $res = pg_query($this->db, $sql);

        $sqlResult = pg_fetch_all($res);

        array_walk($parsedData,function (&$payment) use ($sqlResult, $isInitial, $companies) {
            if (!$isInitial && count($payment['forkdata']))
                return;
            $filtered = array_filter($sqlResult, function ($sql) use ($payment, $companies) {
                return $sql['company_inn'] == $payment['inn'];
            });
            $filtered = array_values($filtered) ?? [];
            if (count($filtered)) {
                $filtered[0]['amount_pp'] = $payment['amount_pp'];
                $filtered[0]['date_pp'] = $payment['date_pp'];
                $filtered[0]['num_pp'] = $payment['num_pp'];
                $filtered[0]['cname'] = $companies[$payment['our_company_inn']];
                for ($i = 1; $i <count($filtered); ++$i) {
                    $filtered[$i]['cname'] = $companies[$payment['our_company_inn']];
                    $filtered[$i]['amount_pp'] = 0;
                    $filtered[$i]['date_pp'] = null;
                    $filtered[$i]['num_pp'] = null;
                }
            }
            $payment['forkdata'] = $filtered;
        });

        return $parsedData;
    }

    /**
     * Получаем историю протоколов за период между
     * @param $from - начало
     * @param $to - конец
     * @return array
     */
    public function getHistory($from, $to)
    {
        $sql = "
             SELECT DISTINCT(uniq), to_char(date_upload, 'YYYY-MM-DD') AS uploaded_at, au.name AS uploaded_by
            FROM cash.pays_from_csv pfc
            INNER JOIN bills_history bh ON bh.id=pfc.bh_id
            INNER JOIN account.users au ON au.id=bh.added_by
            WHERE date_upload >= $1 AND date_upload <= $2 AND inserted
            order by uploaded_at
            ";
        $res = pg_query_params($this->db, $sql, [$from, $to]);

        return pg_fetch_all($res) ?? [];
    }

    /** Получаем данные протокола из БД и конвертируем к виду
     * идентичному в prepareData
     * @see prepareData
     * @param string $uniq
     * @return array
     */
    public function getProtocolByUniq(string $uniq)
    {
        $sql = "
            SELECT u.id uid, pfc.summa AS amount_pp, 
             to_char(pfc.date_pp, 'DD.MM.YYYY') date_pp, pfc.num_pp,
             pfc.company AS cname, replace(u.full_name, '&quot;', '') full_name,
             COALESCE(pfc.customer_by_file, u.full_name) AS customer, 
             to_char(pfc.date_upload, 'YYYY-MM-DD') date_upload, pfc.inserted,
             COALESCE(pfc.inn_by_file, s.company_inn) AS inn, us.name AS status,
             pfc.payment_purpose AS purpose
            FROM cash.pays_from_csv pfc
            LEFT JOIN public.users u ON pfc.public_uid = u.id
            LEFT JOIN public.user_statuses us ON us.id=u.status
            LEFT JOIN account.subscribers s ON s.public_uid=u.id
            WHERE pfc.uniq=$1
            ORDER BY pfc.id, pfc.date_pp, pfc.num_pp, pfc.public_uid
        ";
        $res = pg_query_params ($this->db, $sql, [$uniq]);

        $payments = pg_fetch_all($res) ?? [];

        $processedPayments = [];
        $deletedKeys = [];

        foreach ($payments as $key => $checkedPayment) {
            if (in_array($key, $deletedKeys)) {
                unset($payments[$key]);
                continue;
            }
            if (!$checkedPayment['uid']) {
                $payments[$key]['forkdata'] = [];
                $processedPayments[] = $payments[$key];
                unset($payments[$key]);
                continue;
            }
            $others = array_filter($payments, function($payment) use ($checkedPayment) {
                return $payment['num_pp'] == $checkedPayment['num_pp']
                    && $payment['date_pp'] == $checkedPayment['date_pp']
                    && $payment['inn'] == $checkedPayment['inn']
                    && $payment['uid'] != $checkedPayment['uid'];

            });
            if (!count($others)) {
                $forkData['cname'] = $checkedPayment['cname'];
                $forkData['uid'] = $checkedPayment['uid'];
                $forkData['amount_pp'] = $checkedPayment['amount_pp'];
                $forkData['num_pp'] = $checkedPayment['num_pp'];
                $forkData['full_name'] = $checkedPayment['full_name'];
                $forkData['status'] = $checkedPayment['status'];
                $forkData['date_pp'] = $checkedPayment['date_pp'];
                $payments[$key]['forkdata'][] = $forkData;
            }
            else {
                $keys = array_keys($others);
                $deletedKeys = array_merge($deletedKeys, $keys);
                array_push($others, $checkedPayment);
                $checkedPayments = array_values($others);
                usort($checkedPayments, function ($a, $b) {
                    return ((int)$a['amount_pp'] < (int) $b['amount_pp']);
                });
                $forkData = [];
                array_walk($checkedPayments, function (&$oneUidPayment) use (&$forkData) {
                    $forkDataItem = [];
                    $forkDataItem['cname'] = $oneUidPayment['cname'];
                    $forkDataItem['uid'] = $oneUidPayment['uid'];
                    $forkDataItem['amount_pp'] = $oneUidPayment['amount_pp'];
                    $forkDataItem['full_name'] = $oneUidPayment['full_name'];
                    $forkDataItem['status'] = $oneUidPayment['status'];
                    
                    if ($oneUidPayment['amount_pp']) {
                        $forkDataItem['num_pp'] = $oneUidPayment['num_pp'];
                        $forkDataItem['date_pp'] = $oneUidPayment['date_pp'];
                    } else {
                        $forkDataItem['date_pp'] = null;
                        $forkDataItem['num_pp'] = null;
                    }
                    $forkData[] = $forkDataItem;
                });
                $payments[$key]['forkdata'] = $forkData;
                // Чтобы не потерять сумму при разнесенных платежах
                $payments[$key]['amount_pp'] = array_reduce($forkData, function ($carry, $item) {
                    $carry += $item['amount_pp'];
                    return $carry;
                });
                
            }
            $processedPayments[] = $payments[$key];
            unset($payments[$key]);
        }

        return $processedPayments;
    }

    /**
     * Удаляем все записи о протоколе из таблицы
     * @param string $uniq
     * @return bool
     */
    public function deleteProtocol(string $uniq)
    {
        $sql = "DELETE FROM cash.pays_from_csv WHERE uniq=$1";
        $res = pg_query_params($this->db, $sql, [$uniq]);
        return $res ? true : false;
    }

}