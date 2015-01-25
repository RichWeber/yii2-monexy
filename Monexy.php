<?php
/**
 * @copyright Copyright &copy; Roman Bahatyi, richweber.net, 2015
 * @package yii2-monexy
 * @version 1.0.0
 */

namespace richweber\monexy;

use stdClass;
use SimpleXMLElement;
use richweber\monexy\ApiException;

/**
 * Розширення Yii Framework 2 для роботи з Monexy API
 *
 * Приклад конфігурації:
 * ~~~
 * 'components' => [
 *       ...
 *       'monexy' => [
 *           'class' => 'richweber\monexy\Monexy',
 *           'apiName' => 'testAPI',
 *           'apiPassword' => 'password',
 *           'enableCrypt' => false,
 *           'requestType' => 'POST',
 *           'contentType' => 'JSON',
 *       ],
 *       ...
 *   ],
 * ~~~
 *
 * @link https://www.monexy.ua/ua/api
 * @author Roman Bahatyi <rbagatyi@gmail.com>
 * @since 1.0
 */
class Monexy
{
    /**
     * API-ім’я
     * @var string
     */
    public $apiName;

    /**
     * Пароль до API-імені
     * @var string
     */
    public $apiPassword;

    /**
     * Тип запиту
     * (JSON або XML)
     * @var string
     */
    public $contentType = 'JSON';

    /**
     * Спосіб передачі даних
     * (POST або GET)
     * @var string
     */
    public $requestType = 'POST';

    /**
     * Версія класа
     * @var float
     */
    public $version = 2.1;

    /**
     * Включити/виключити перевірку
     * сертифіката SSL
     * @var boolean
     */
    public $verifySSL = true;

    /**
     * Включити/виключити перевірку
     * доменого імені
     * @var boolean
     */
    public $verifyHost = true;

    /**
     * Включити/виключити шифрування запиту
     * @var boolean
     */
    public $enableCrypt = false;

    const CONTENT_TYPE_JSON = 'JSON';
    const CONTENT_TYPE_XML = 'XML';

    const REQUEST_TYPE_POST = 'POST';
    const REQUEST_TYPE_GET = 'GET';

    /**
     * Адреса API-сервера
     * @var string
     */
    private $_url = 'https://www.monexy.ua/api/server';

    /**
     * Номер запиту
     * @var string
     */
    private $requestNumber;

    /**
     * Тіло запиту у вигляді рядка
     * @var string
     */
    private $_requestString;

    /**
     * Об’єкт сформований з тіла запиту
     * @var object
     */
    private $_requestObject;

    /**
     * Дані для побудови запиту
     * @var array
     */
    protected $_requestData = [];

    /**
     * Інформація про запит до сервера (cURL)
     * @var array
     */
    private $_operationInfo = [];

    /**
     * Статус відповіді сервера
     * @var integer
     */
    private $_responseStatusCode;

    /**
     * Заголовки відповіді
     * @var array
     */
    private $_responseHeader;

    /**
     * Тіло відповіді
     * @var string
     */
    private $_responseBody;

    /**
     * Об’єкт відповіді
     * @var object
     */
    private $_responseObject;

    /**
     * Отримання балансу
     * @return object Об’єкт відповіді
     */
    public function balance()
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'balance',
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Отримуємо історію операцій по гаманцю
     * @param  integer $cardId        Номер гаманця
     * @param  string  $periodFrom    Початок періоду в форматі Y-m-d H:i:s
     * @param  string  $periodTo      Кінець періоду в форматі Y-m-d H:i:s
     * @param  integer $page          Номер сторінки
     * @param  integer $perPage       Кількість записів на сторінку
     * @param  boolean $payType       Фільтр по типу операцій
     * @param  boolean $correspondent Фільтр по кореспонденту
     * @return object                 Об’єкт відповіді
     */
    public function cardHistory(
        $cardId,
        $periodFrom = '',
        $periodTo = '',
        $page = 1,
        $perPage = 5,
        $payType = false,
        $correspondent = false
    )
    {
        $periodFrom = empty($periodFrom)
            ? date('Y-m-d', strtotime(date('Y-m-d') . ' -1 month'))
            : date('Y-m-d H:i:s', strtotime($periodFrom));
        $periodTo = empty($periodTo)
            ? date('Y-m-d H:i:s')
            : date('Y-m-d H:i:s', strtotime($periodTo));

        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'card-history',
                    'card' => intval($cardId),
                    'periodFrom' => $periodFrom,
                    'periodTo' => $periodTo,
                    'page' => intval($page),
                    'perPage' => intval($perPage),
                ],
            ],
        ];

        if ($payType !== false) {
            $resultData['request']['body']['payType'] = $payType;
        }

        if ($correspondent !== false) {
            $resultData['request']['body']['correspondent'] = $correspondent;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Отримуєм баланс гаманця
     * @param  integer $cardId Номер гаманця
     * @return object          Об’єкт відповіді
     */
    public function cardBalance($cardId)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'card-balance',
                    'card' => $cardId,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Перевіряємо статус платежа
     * @param  string $orderId Номер платежа
     * @return object          Об’єкт відповіді
     */
    public function paymentStatus($orderId)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'payment-status',
                    'orderId' => $orderId,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Перевіряємо можливість переказу
     * між Користувачем і Бізнес-клієнтом
     * @param  float   $amount         Сума операції
     * @param  string  $orderId        Номер замовлення
     * @param  string  $orderDesc      Призначення платежу
     * @param  string  $payerPhone     Номер телефону платника
     * @param  integer $recipientCard  Гаманець отримувача (ID мерчанта)
     * @param  string  $currency       Валюта операції
     * @param  string  $payerPrepaidId Номер Prepaid-картки
     * @return object                  Об’єкт відповіді
     */
    public function checkPaymentC2B(
        $amount,
        $orderId,
        $orderDesc,
        $payerPhone = '',
        $recipientCard,
        $currency = 'UAH',
        $payerPrepaidId = ''
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'check-payment-c2b',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'recipientCard' => $recipientCard,
                ],
            ],
        ];

        if (!empty($payerPrepaidId)) {
            $resultData['request']['body']['payerPrepaidId'] = $payerPrepaidId;
        }
        if (!empty($payerPhone)) {
            $resultData['request']['body']['payerPhone'] = $payerPhone;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Переказ між Користувачем і Бізнес-клієнтом
     * @param  float   $amount         Сума операції
     * @param  string  $orderId        Номер замовлення
     * @param  string  $orderDesc      Призначення платежу
     * @param  string  $payerPhone     Номер телефону платника
     * @param  integer $recipientCard  Гаманець отримувача (ID мерчанта)
     * @param  string  $currency       Валюта операції
     * @param  string  $payerPrepaidId Номер Prepaid-картки
     * @return object                  Об’єкт відповіді
     */
    public function paymentC2B(
        $amount,
        $orderId,
        $orderDesc,
        $payerPhone = '',
        $recipientCard,
        $currency = 'UAH',
        $payerPrepaidId = ''
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'payment-c2b',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'recipientCard' => $recipientCard,
                ],
            ],
        ];

        if (!empty($payerPrepaidId)) {
            $resultData['request']['body']['payerPrepaidId'] = $payerPrepaidId;
        }
        if (!empty($payerPhone)) {
            $resultData['request']['body']['payerPhone'] = $payerPhone;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Підтверджуємо переказ
     * між Користувачем і Бізнес-клієнтом
     * @param  integer $paymentId Ідентифікатор переказу
     * @param  integer $smsCode   SMS-код
     * @return object             Об’єкт відповіді
     */
    public function confirmPaymentC2B($paymentId, $smsCode)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'confirm-payment-c2b',
                    'paymentId' => $paymentId,
                    'smsCode' => $smsCode,
                ],
            ],
        ];

        return $this->getResponse();
    }


    /**
     * Перевірка можливості переказу між Користувачами
     * @param  float   $amount         Сума операції
     * @param  integer $payerPhone     Номер телефона платника
     * @param  integer $recipientPhone Номер телефона отримувача
     * @param  string  $orderDesc      Призначення платежу
     * @param  string  $orderId        Номер замовлення
     * @param  string  $currency       Валюта операції
     * @return object                  Об’єкт відповіді
     */
    public function checkPaymentP2P(
        $amount,
        $payerPhone,
        $recipientPhone,
        $orderDesc,
        $orderId = false,
        $currency = 'UAH'
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'check-payment-p2p',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderDesc' => $orderDesc,
                    'payerPhone' => $payerPhone,
                    'recipientPhone' => $recipientPhone,
                ],
            ],
        ];

        if ($orderId) {
            $resultData['request']['body']['orderId'] = $orderId;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Переказ між Користувачами
     * @param  float   $amount         Сума операції
     * @param  integer $payerPhone     Номер телефона платника
     * @param  integer $recipientPhone Номер телефона отримувача
     * @param  string  $orderDesc      Призначення платежу
     * @param  string  $orderId        Номер замовлення
     * @param  string  $currency       Валюта операції
     * @return object                  Об’єкт відповіді
     */
    public function paymentP2P(
        $amount,
        $payerPhone,
        $recipientPhone,
        $orderDesc,
        $orderId = false,
        $currency = 'UAH'
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'payment-p2p',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderDesc' => $orderDesc,
                    'payerPhone' => $payerPhone,
                    'recipientPhone' => $recipientPhone,
                ],
            ],
        ];

        if ($orderId) {
            $resultData['request']['body']['orderId'] = $orderId;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Підтверджуємо переказ між Користувачами
     * @param  integer $paymentId Ідентифікатор переказу
     * @param  integer $smsCode   SMS-код
     * @return object             Об’єкт відповіді
     */
    public function confirmPaymentP2P($paymentId, $smsCode)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'confirm-payment-p2p',
                    'paymentId' => $paymentId,
                    'smsCode' => $smsCode,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Перевірка можливості переказу
     * між Бізнес-клієнотом і Користувачем
     * @param  float   $amount           Сума операції
     * @param  string  $orderId          Номер замовлення
     * @param  string  $orderDesc        Призначення платежу
     * @param  integer $payerCard        Гаманець платника (ID мерчанта)
     * @param  integer $recipientPhone   Номер телефону отримувача
     * @param  string  $currency         Валюта операції
     * @param  string  $generateVouchers Відмітка генерації ваучерів
     * @param  string  $operationType    Тип операції
     * @return object                    Об’єкт відповіді
     */
    public function checkPaymentB2C(
        $amount,
        $orderId,
        $orderDesc,
        $payerCard,
        $recipientPhone,
        $currency = 'UAH',
        $generateVouchers = '',
        $operationType = ''
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'check-payment-b2c',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'payerCard' => $payerCard,
                    'recipientPhone' => $recipientPhone,
                ],
            ],
        ];

        if (!empty($generateVouchers)) {
            $resultData['request']['body']['generateVouchers'] = (bool)$generateVouchers;
        }
        if (!empty($operationType)) {
            $resultData['request']['body']['operationType'] = $operationType;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Переказ між Бізнес-клієнотом і Користувачем
     * @param  float    $amount           Сума операції
     * @param  string   $orderId          Номер замовлення
     * @param  string   $orderDesc        Призначення платежу
     * @param  integer  $payerCard        Гаманець платника (ID мерчанта)
     * @param  integer  $recipientPhone   Номер телефону отримувача
     * @param  string   $currency         Валюта операції
     * @param  boolean  $forceSendSMS     Примусове надсилання SMS повідомлення
     * @param  string   $generateVouchers Відмітка генерації ваучерів
     * @param  string   $operationType    Тип операції
     * @return object                     Об’єкт відповіді
     */
    public function paymentB2C(
        $amount,
        $orderId,
        $orderDesc,
        $payerCard,
        $recipientPhone,
        $currency = 'UAH',
        $forceSendSMS = false,
        $generateVouchers = '',
        $operationType = ''
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'payment-b2c',
                    'amount' => $amount,
                    'currency' => $currency,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'payerCard' => $payerCard,
                    'recipientPhone' => $recipientPhone,
                ],
            ],
        ];

        if ($forceSendSMS) {
            $resultData['request']['body']['forceSendSMS'] = (bool)$forceSendSMS;
        }
        if (!empty($generateVouchers)) {
            $resultData['request']['body']['generateVouchers'] = (bool)$generateVouchers;
        }
        if (!empty($operationType)) {
            $resultData['request']['body']['operationType'] = $operationType;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Відміна операції
     * @param  integer $transId ID транзакції
     * @return object           Об’єкт відповіді
     */
    public function cancelPayment($transId)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'cancel-payment',
                    'transId' => $transId,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Перевірка статусу відміни операції
     * @param  integer $transId ID транзакції
     * @return object           Об’єкт відповіді
     */
    public function checkCancelStatus($transId)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'check-cancel-status',
                    'transId' => $transId,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Створення ваучера
     * @param  integer $payerCard Гаманець платника
     * @param  float   $amount    Сума операції
     * @param  string  $orderId   Номер замовлення
     * @param  string  $orderDesc Призначення платежу
     * @param  boolean $isTest    Відмітка тестового запиту
     * @param  string  $currency  Валюта операції
     * @return object             Об’єкт відповіді
     */
    public function createVoucher(
        $payerCard,
        $amount,
        $orderId,
        $orderDesc,
        $isTest = null,
        $currency = 'UAH'
    )
    {
        $resultData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'create-voucher',
                    'payerCard' => $payerCard,
                    'amount' => $amount,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                ],
            ],
        ];

        if (!empty($isTest)) {
            $resultData['request']['body']['isTest'] = $isTest;
        }
        if (!empty($currency)) {
            $resultData['request']['body']['currency'] = $currency;
        }

        $this->_requestData = $resultData;

        return $this->getResponse();
    }

    /**
     * Отримання балансу ваучера
     * @param  string  $number Номер ваучера
     * @param  integer $pin    Пін-код ваучера
     * @return object          Об’єкт відповіді
     */
    public function voucherBalance($number, $pin)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'voucher-balance',
                    'number' => $number,
                    'pin' => $pin,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Активація ваучера на телефон Користувача
     * @param  string  $number         Номер ваучера
     * @param  integer $pin            Пін-код ваучера
     * @param  integer $recipientPhone Номер телефона отримувача
     * @param  string  $orderId        Номер замовлення
     * @param  string  $orderDesc      Призначення платежу
     * @param  boolean $isTest         Відмітка тестового запиту
     * @return object                  Об’єкт відповіді
     */
    public function activateVoucherByPhone(
        $number,
        $pin,
        $recipientPhone,
        $orderId,
        $orderDesc,
        $isTest = false
    )
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'activate-voucher-by-phone',
                    'number' => $number,
                    'pin' => $pin,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'recipientPhone' => $recipientPhone,
                    'isTest' => $isTest,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Активація ваучера на номер гаманця
     * @param  string   $number         Номер ваучера
     * @param  integer  $pin            Пін-код ваучера
     * @param  integer  $recipientCard  Номер гаманця
     * @param  string   $orderId        Номер замовлення
     * @param  string   $orderDesc      Призначення платежу
     * @param  boolean  $isTest         Відмітка тестового запиту
     * @return object                   Об’єкт відповіді
     */
    public function activateVoucherByCard(
        $number,
        $pin,
        $recipientCard,
        $orderId,
        $orderDesc,
        $isTest = true
    )
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'activate-voucher-by-card',
                    'number' => $number,
                    'pin' => $pin,
                    'orderId' => $orderId,
                    'orderDesc' => $orderDesc,
                    'recipientCard' => $recipientCard,
                    'isTest' => $isTest,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Отримання посилання для
     * автоматичного входу Користувача
     * @param  integer $phone Номер телефона Користувача
     * @return object         Об’єкт відповіді
     */
    public function clientAutologinLink($phone)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'client-autologin-link',
                    'phone' => $phone,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Підтвердження SMS-кодом посилання
     * для автоматичного входу Користувача
     * @param  integer $phone   Номер телефона Користувача
     * @param  string  $smsCode SMS-код
     * @return object           Об’єкт відповіді
     */
    public function clientAutologinVerify($phone, $smsCode)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'client-autologin-verify',
                    'phone' => $phone,
                    'smsCode' => $smsCode,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Отримання балансу Користувача
     * @param  integer $phone   Номер телефона Користувача
     * @return object           Об’єкт відповіді
     */
    public function clientBalance($phone)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'client-balance',
                    'phone' => $phone,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Підтвердження отримання балансу Користувача SMS-кодом
     * @param  integer $phone   Номер телефона Користувача
     * @param  string  $smsCode SMS-код
     * @return object           Об’єкт відповіді
     */
    public function clientBalanceConfirm($phone, $smsCode)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'client-balance-confirm',
                    'phone' => $phone,
                    'smsCode' => $smsCode,
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Переразування коштів на розрахунковий рахунок
     * @param  integer $merchantId Номер мерчанта
     * @param  float   $amount     Сума операції
     * @param  string  $orderId    Номер замовлення
     * @return object              Об’єкт відповіді
     */
    public function cashOut($merchantId, $amount, $orderId)
    {
        $this->_requestData = [
            'request' => [
                'apiName' => $this->apiName,
                'requestNumber' => $this->getRequestNumber(),
                'body' => [
                    'method' => 'cash-out',
                    'merchantId' => $merchantId,
                    'amount' => $amount,
                    'orderId' => $orderId
                ],
            ],
        ];

        return $this->getResponse();
    }

    /**
     * Отримання типу HTTP запиту
     * (POST або GET)
     * @return string
     */
    public function getRequestType()
    {
        return $this->requestType;
    }

    /**
     * Отримання типу переданих даних
     * (JSON або XML)
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Отримання тіла відповіді
     * @return string
     */
    public function getResponseBody()
    {
        return $this->_responseBody;
    }

    /**
     * Отримання статусу відповіді
     * @return integer
     */
    public function getResponseStatusCode()
    {
        return $this->_responseStatusCode;
    }

    /**
     * Отримання заголовку Content-Type
     * в залежності від типу запиту
     * @param  string $contentType Тип запиту
     * @return string              Рядок заголовка
     */
    public function getContentTypeString($contentType)
    {
        if (strtoupper($contentType) == self::CONTENT_TYPE_XML) {
            return 'Content-Type: application/xml';
        } else {
            return 'Content-Type: application/json';
        }
    }

    /**
     * Отримання інформації з запиту (cURL)
     * @param boolean $format Відмітка чи форматувати вивід даних
     * @return array|string
     */
    public function getOperationInfo($format = false)
    {
        if ($format) {
            return '<pre>' . htmlspecialchars(var_export($this->_operationInfo, true)) . '</pre>';
        } else {
            return $this->_operationInfo;
        }
    }

    /**
     * Конвертація рядка запиту в об’єкт запиту
     * @return string|SimpleXMLElement Запит (JSON або XML)
     */
    public function getRequestObject()
    {
        $requestString = $this->prepareRequestString();

        if ($this->contentType == self::CONTENT_TYPE_XML) {
            return simplexml_load_string($requestString);
        } else {
            return json_decode($requestString);
        }
    }

    /**
     * Отримання об’єкта відповіді
     * @return string|stdClass Об’єкт (JSON або XML)
     */
    public function getResponseObject()
    {
        if ($this->contentType === self::CONTENT_TYPE_XML) {
            $return = new stdClass;

            if (strpos($this->_responseBody, '<?xml') !== false) {
                $return->response = simplexml_load_string($this->_responseBody);
            }

            return $return;
        } else {
            return json_decode($this->_responseBody);
        }
    }

    /**
     * Формування рядка запиту
     * @return string
     */
    public function getRequestString()
    {
        $this->prepareRequestString();

        return $this->_requestString;
    }

    /**
     * Шифруємо дані
     * @param  string $string        Рядок для шифрування
     * @param  string $algorithm     Алгоритм шифрування
     * @param  string $algorithmMode Режим шифрування
     * @return string                Закодований рядок
     * @link   https://php.net/manual/mcrypt.ciphers.php
     * @link   https://php.net/manual/ru/mcrypt.constants.php
     */
    private function encrypt(
        $string,
        $algorithm = MCRYPT_BLOWFISH,
        $algorithmMode = MCRYPT_MODE_CBC
    )
    {
        if (!extension_loaded('mcrypt')) {
            throw new ApiException(
                'To work properly you need to install the MCrypt extension - https://php.net/mcrypt'
            );
        }

        $cipher = mcrypt_module_open($algorithm, '', $algorithmMode, '');
        srand();
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($cipher), MCRYPT_RAND);
        mcrypt_generic_init($cipher, md5($this->apiPassword), $iv);

        $encryptedString = $iv . mcrypt_generic($cipher, $string);

        mcrypt_generic_deinit($cipher);
        mcrypt_module_close($cipher);

        return $encryptedString;
    }

    /**
     * Декодуємо зашифровані дані
     * @param  string $encryptedString Закодований рядок
     * @param  string $algorithm       Алгоритм шифрування
     * @param  string $algorithmMode   Режим шифрування
     * @return string                  Декодований рядок
     */
    public function decrypt(
        $encryptedString,
        $algorithm = MCRYPT_BLOWFISH,
        $algorithmMode = MCRYPT_MODE_CBC
    )
    {
        if (!extension_loaded('mcrypt')) {
            throw new ApiException(
                'To work properly you need to install the MCrypt extension - https://php.net/mcrypt'
            );
        }

        $cipher = mcrypt_module_open($algorithm, '', $algorithmMode, '');
        $ivSize = mcrypt_enc_get_iv_size($cipher);
        $iv = substr($encryptedString, 0, $ivSize);
        mcrypt_generic_init($cipher, md5($this->apiPassword), $iv);

        $decryptedString = mdecrypt_generic($cipher, substr($encryptedString, $ivSize, strlen($encryptedString)));

        mcrypt_generic_deinit($cipher);
        mcrypt_module_close($cipher);

        return rtrim($decryptedString, "\0");
    }

    /**
     * Отримуємо масив заголовків відповіді
     * @return array
     */
    public function getResponseHeader()
    {
        return $this->_responseHeader;
    }

    /**
     * Конвертація масива
     * @param array $data Масив даних
     * @return array
     */
    private static function toSingleArray($data)
    {
        $array = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $array = array_merge($array, self::toSingleArray($value));
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * Формуємо підпис запиту
     * @return string Рядок підпису
     */
    private function signRequest()
    {
        if (!$this->enableCrypt) {
            $this->_requestData['request']['sign'] = sha1(
                $this->apiName . ':'
                . $this->requestNumber . ':'
                . $this->apiPassword
            );
        }
    }

    /**
     * Генеруємо унікальний номер запиту
     * @return string Номер запиту
     */
    public function getRequestNumber()
    {
        $time = microtime();
        $int = substr($time, 11);
        $flo = substr($time, 2, 5);

        return $this->requestNumber = $int . $flo;
    }

    /**
     * Підготовка рядка запиту перед відправленням
     * @return string
     */
    private function prepareRequestString()
    {
        if ($this->enableCrypt) {
            $requestBodyForEncrypt = '';

            if ($this->contentType === self::CONTENT_TYPE_JSON) {
                $requestBodyForEncrypt = json_encode($this->_requestData['request']['body']);
            } elseif ($this->contentType == self::CONTENT_TYPE_XML) {
                $requestBodyObject = new simpleXMLElement('<body></body>');
                $this->arrayToXML($this->_requestData['request']['body'], $requestBodyObject);
                $requestBodyForEncrypt = $this->formatXML($requestBodyObject);
            }

            $this->_requestData['request']['body'] = base64_encode($this->encrypt($requestBodyForEncrypt));
        }

        if ($this->contentType == self::CONTENT_TYPE_JSON) {
            $this->_requestString = json_encode($this->_requestData);
        } elseif ($this->contentType == self::CONTENT_TYPE_XML) {
            $this->_requestObject = new simpleXMLElement('<request></request>');
            if (array_key_exists('request', $this->_requestData)) {
                $this->arrayToXML($this->_requestData['request'], $this->_requestObject);
            }

            $this->_requestString = $this->formatXML($this->_requestObject);
        }

        return $this->_requestString;
    }

    /**
     * Форматування XML-об’єкта
     * @param SimpleXMLElement $object
     * @return string
     */
    private function formatXML($object)
    {
        $dom = dom_import_simplexml($object)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    /**
     * Конвертація масива в об’єкт simpleXMLElement
     * @param array  $array            Масив даних
     * @param object &$simpleXMLObject Об’єкт
     * @return object
     */
    private function arrayToXML($array, &$simpleXMLObject)
    {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_array($value)) {
                    if (!is_numeric($key)) {
                        $subNode = $simpleXMLObject->addChild("$key");
                        $this->arrayToXML($value, $subNode);
                    } else {
                        $subNode = $simpleXMLObject->addChild("item$key");
                        $this->arrayToXML($value, $subNode);
                    }
                } else {
                    $simpleXMLObject->addChild((string)$key, (string)$value);
                }
            }
        }
    }

    /**
     * Надсилаємо запит
     * і отримуємо відповідь
     * @return object Об’єкт відповіді
     */
    protected function getResponse()
    {
        $this->sendRequest();
        return $this->getResponseObject();
    }

    /**
     * Відправляємо запит
     * і розбираємо відповідь
     */
    public function sendRequest()
    {
        if (!function_exists('curl_version')) {
            throw new ApiException(
                'To work correctly, you need to install cURL library - https://php.net/curl'
            );
        }

        $this->signRequest();

        $ch = curl_init();
        $cUrlInfo = curl_version();

        if ($this->requestType == self::REQUEST_TYPE_POST) {
            curl_setopt($ch, CURLOPT_URL, $this->_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,
                ['request' => urlencode($this->prepareRequestString($this->_requestData))]);
        } else {
            curl_setopt($ch, CURLOPT_URL,
                $this->_url . '?request=' . urlencode($this->prepareRequestString($this->_requestData)));
        }

        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,
            'monexy.api.php class ' . $this->version
            . ' cURL - ' . $cUrlInfo['version'] . ' ('
            . $this->requestType . '/'
            . $this->contentType . ')');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifyHost);

        $response = curl_exec($ch);

        $this->_operationInfo = curl_getinfo($ch);
        $this->_responseStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($this->_operationInfo['header_size'] > 0) {
            $this->_responseHeader = explode("\n", trim($this->_operationInfo['request_header']));

            if ($this->_operationInfo['download_content_length']) {
                $this->_responseBody = substr($response, $this->_operationInfo['header_size']);

                if ($this->enableCrypt && $this->_responseStatusCode === 200) {
                    if ($this->contentType === self::CONTENT_TYPE_JSON) {
                        $this->_responseObject = json_decode($this->_responseBody);
                        $decryptedBody = $this->decrypt(base64_decode($this->_responseObject->response->body));
                        $this->_responseObject->response->body = json_decode($decryptedBody);

                        $this->_responseBody = json_encode($this->_responseObject);
                    } elseif ($this->contentType === self::CONTENT_TYPE_XML) {
                        libxml_use_internal_errors(true);

                        if (simplexml_load_string($this->_responseBody)) {
                            $this->_responseObject = simplexml_load_string($this->_responseBody);

                            $decryptedBody = $this->decrypt(base64_decode($this->_responseObject->body));
                            $dom = dom_import_simplexml($this->_responseObject->body);
                            $import = $dom->ownerDocument->importNode(dom_import_simplexml(simplexml_load_string($decryptedBody)), true);
                            $dom->parentNode->replaceChild($import, $dom);

                            $this->_responseBody = $this->formatXML($this->_responseObject);
                        } else {
                            throw new ApiException(
                                'Bad response: <br>' . htmlspecialchars($this->_responseBody)
                            );
                        }
                    }
                }
            }
        }

        $errorCode = curl_errno($ch);
        $errorDescription = curl_error($ch);

        curl_close($ch);

        if ($errorCode) {
            throw new ApiException(
                'cURL error #' . $errorCode . ' - ' . $errorDescription
            );
        }
    }
}
