<?php
/**
 * Created by PhpStorm.
 * User: zenkin
 * Date: 27.02.20
 * Time: 8:23
 */

namespace VladlinkMVC\Classes\Cash;

use PHPExcel_Settings;
use PHPExcel_IOFactory;
use Vladlink\DB\DBConnection;

/**
 * Парсер для txt/xlsx
 * Для csv - Не сделано, т.к. нарушена последовательность
 * столбцов в самом файле
 * Class ClientBankProtocolParser
 * @package VladlinkMVC\Classes\Cash
 */
class ClientBankProtocolParser
{
    const WINDOWS_SEPARATOR = "\r\n";
    const LINUX_SEPARATOR = "\n";

    const TXT_BLOCK_INDICATOR = "СекцияДокумент=Платежное поручение";

    const TXT_VALUE_NOT_EMPTY='ДатаПоступило';

    CONST XLSX_INN_LOCATION = "C4";


    /**
     * Искомые ключи в файле txt
     * @var array
     */
    protected static $txtKeys = [
        'num_pp' => 'Номер',
        'date_pp' => 'Дата',
        'amount_pp' => 'Сумма',
        'customer' => 'Плательщик',
        'inn' => 'ПлательщикИНН',
        'our_company_inn' => 'ПолучательИНН',
        'purpose' => 'НазначениеПлатежа'
    ];

    /**
     * Исклмые ключи в файле xlsx
     * @var array
     */
    protected static $xlsxKeys = [
        'num_pp' => [2 => 'Номер документа'],
        'date_pp' => [1 => 'Дата операции'],
        'amount_pp' => [4 => 'Кредит'],
        'customer' => [5 => 'Контрагент'],
        'inn' => [6 => 'ИНН'], //ИНН в подстроке, по нему не сверяю
        'purpose' => [10 => 'Назначение платежа']
    ];


    /**
     * Парсинг протокола txt
     * @param $file - файл
     * @return array
     */
    public static function txt($file)
    {
        $contents = file_get_contents($file['tmp_name']);

        if (stripos($contents, '=Windows')) {
            $contents = iconv('Windows-1251', 'UTF-8', $contents);
            $contents = str_replace (self::WINDOWS_SEPARATOR, self::LINUX_SEPARATOR, $contents);
        }

        $separator = self::LINUX_SEPARATOR;

        $bySections = explode($separator . $separator, $contents);
        foreach ($bySections as $key => $section) {
            $rows = explode($separator, $section);
            $bySections[$key] = $rows;
        }

        $paymentsData = array_filter ($bySections, function ($section) {
            return trim(reset($section)) == self::TXT_BLOCK_INDICATOR;
        });

        $result = [];
        foreach ($paymentsData as $payment) {
            $onlyNeeded = [];
            $toInsert = true;
            foreach($payment as $detail) {
                if (stripos($detail, '=')) {
                    list ($key, $value) = explode ('=', $detail);
                    if ($key == self::TXT_VALUE_NOT_EMPTY && empty($value)) {
                        $toInsert = false;
                        break;
                    }
                    if (in_array($key, self::$txtKeys)) {
                        $onlyNeeded[array_search($key, self::$txtKeys)] = $value;
                    }
                }
            }
            if ($toInsert)
                $result[] = $onlyNeeded;
        }

        return $result;
    }

    /**
     * Парсим xlsx
     * @param $file - файл
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public static function xlsx($file)
    {
        include_once __DIR__ . '/../../../../includes/additional_libraries/PHPExcel/Classes/PHPExcel.php';
        include_once __DIR__ . '/../../../../includes/additional_libraries/PHPExcel/Classes/PHPExcel/IOFactory.php';
        include_once __DIR__ . '/../../../../includes/additional_libraries/PHPExcel/Classes/PHPExcel/Settings.php';

        PHPExcel_Settings::setLocale('ru');
        $objPHPExcel = PHPExcel_IOFactory::load($file['tmp_name']);

        $ourCompanyInn = null;
        $objPHPExcel->setActiveSheetIndex(0);
        $aSheet = $objPHPExcel->getActiveSheet();
        $excelData = [];
        foreach ($aSheet->getRowIterator() as $key => $row) {
            $cellIterator = $row->getCellIterator();
            foreach ($cellIterator as $cell) {
                $cellIndex = $cell->getColumn() . $cell->getRow();
                if ($cellIndex == self::XLSX_INN_LOCATION)
                    $ourCompanyInn = $cell->getCalculatedValue();
                $excelData[$key][] = $cell->getCalculatedValue();
            }
        }
        $key = 1;
        while ($excelData[$key][1] != self::$xlsxKeys['date'][1]) {
            unset($excelData[$key]);
            $key++;
        }
        array_shift($excelData);
        if (reset($excelData)[6] == self::$xlsxKeys['inn'][6]) {
            array_shift($excelData);
        }
        $excelData = array_filter($excelData, function ($payment) {
            return !empty($payment[4]);
        });


        $result = [];

        array_walk($excelData, function ($paymentRow) use (&$result, $ourCompanyInn) {
            $data = [];
            foreach (self::$xlsxKeys as $key => $value) {
                $data[$key] = $paymentRow[key($value)];
            }
            $data['our_company_inn'] = $ourCompanyInn;
             $result[] = $data;
        });

        return $result;

    }
}