<?php
ini_set('display_errors', true);
error_reporting(E_ALL);
$root = $root = dirname(__FILE__);
$root = str_replace(DIRECTORY_SEPARATOR . "commands", "", $root);

Yii::import('application.extensions.components.KCliColor');
Yii::import('application.vendor.rapidapi');

$root = str_replace(DIRECTORY_SEPARATOR . "commands", "", $root);
require_once($root . '/app/lib/vendor/rapidapi/rapidapi-connect/src/RapidApi/RapidApiConnect.php');
require_once($root . '/app/lib/vendor/rapidapi/rapidapi-connect/src/RapidApi/Utils/HttpInstance.php');

use RapidApi\RapidApiConnect;

//docker exec -t mbst_php72 php /home/www/mobsted/boiler/yiic insales checktoken
class ShopifyCommand extends CConsoleCommand {

    private $token = '';
    private $login = 'Mobsted';
    private $secret = 'blabla';

    /**
     * @var string
     */
    private $_tenant1 = 'bigbrothers';

    /**
     * @var null
     */
    private $Api8 = null;
    private $apiBase = null;
    private $ShopifyController = null;
    private $access_token = null;
    private $shop = null;

    /**
     * @var null
     */
    private $ApiInsales = null;

    /**
     * @var null
     */
    private $base_fields = [
        'server' => 1,
        'tenant' => 1,
    ];

    /**
     * @var array
     */
    private $_servers = [
        'docker.mobsted.ru' => [
            'db' => 'postgres',
            'name' => 'mobsted.ru',
            'app' => 1,
        ],
        'docker.mobsted.com' => [
            'db' => 'postgres',
            'name' => 'mobsted.com',
            'app' => 1,
        ],
        'docker.logintap.com' => [
            'db' => 'postgres',
            'name' => 'logintap.com',
            'app' => 2,
        ],
    ];


    public function init()
    {
        $this->apiBase = new ApiBase();
        $this->Api8 = new Restapi8Controller("");
        $this->ShopifyController = new ShopifyController("");
    }


    /**
     * Function to get custom connection, for use on catalog server only.
     *
     * @return EDbConnection
     */
    private function getConn($server, $database)
    {

        if ($server == '') {
            $server = 'mbst_pgbouncer';
        }
        $connectionString = 'pgsql:host=' . $server . ';port=6432;dbname=' . $database;
        $username = 'postgres';
        $password = 'postgres';

        $connection = new EDbConnection($connectionString, $username, $password);
        $connection->tablePrefix = $database . '.';
        $connection->charset = 'utf-8';
        $connection->emulatePrepare = true;
        // $lastId = $connection->createCommand('SELECT last_value as "id" FROM objects_id_seq')->queryRow()['id'];
        // $rand1 = rand(1,$lastId)+10000;
        // $this->_left = $rand1-10000;
        // $this->_right = $rand1+10000;
        return $connection;
    }

    /**
     * try sync data with token
     *
     * @param string $objectId
     * return void
     */
    public function actionChecktoken($action = '')
    {
        $time_start = microtime(true);
        //        $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        //        socket_bind($sock, '127.0.0.1',5000);
        //        socket_listen($sock,1);



        $connStatTenant = Tenants::getTenantDbConnection($this->_tenant1);
        $tenantIterator =
            new ETenantsSQLDataProviderIterator(' "Name" like \'shopify%\'  ');

        foreach ($tenantIterator as $tenantDatabase) {

            dump($tenantDatabase->Name);
            $connectionItem = Tenants::getTenantDbConnection($tenantDatabase->Name);
            Yii::app()
                ->setComponent('db', $connectionItem);
            Yii::app()->db->setActive(false);
            Yii::app()->db->setActive(true);
            Yii::app()->params->customShortlinkTable = 'short_links';
            Yii::app()->params['client_portal'] = $tenantDatabase->Name;
            $id = 0;
            $res = $this->showAccInfo($connectionItem, $id);
            $tokenStatus = 1;
            if (!$res) {
                dump('fail');
                $tokenStatus = 0;
            }

//            $connStatTenant->createCommand(
//                'update objects
//                                  set "TokenStatus" = :TokenStatus
//                                  where tenant = :tenant ')
//                ->execute(
//                    [
//                        ':TokenStatus' => $tokenStatus,
//                        ':tenant' => $tenantDatabase->Name,
//                    ]);


            if ($res and ($action == 'resetupScripts')) {   // or $tenantDatabase->Name == 'ins925006'
                $applicationId=1;
                $this->ShopifyController->reinstallScripts($connectionItem, $applicationId, $tenantDatabase->Name );
            }

            if ($res and ($action == 'showScripts')) {   // or $tenantDatabase->Name == 'ins925006'
                $applicationId=1;
                $this->ShopifyController->showScripts($connectionItem, $applicationId, $tenantDatabase->Name );
            }

            dump('');
            dump('');
            dump('');
            dump('');
        }

        $t = microtime(true) - $time_start;
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));

        print $d->format("Y-m-d H:i:s.u"); // note at point on "u"

    }


    public
    function tableExists($connection,$table)
    {
        return ($connection->getSchema()->getTable('{{' . $table . '}}', true) !== null);
    }

    /**
     * try account info show
     *
     * @param EDbConnection $connection
     * @param int $idIns
     * return bool
     *
     */
    private function showAccInfo($connection, &$idIns = 0)
    {
        $token = null;
        $shop = null;
        $tableName = 'list1_config';
        if(!$this->tableExists($connection,$tableName)){
            return false;
        }
        $row = $connection->createCommand('select * from '.$tableName)
            ->queryRow(true);
        if (!$row) {
            dump('list1 not exist');
            return false;
        }
        $shop = null;
        $this->access_token = $tokenApp = $row['tokenApp'] ?? null;
        if ($info = json_decode($row['appData'], true)) {
            $shop = $info['myshopify_domain'] ?? null;
        }
        if($shop){
            $this->shop = $shop;
        }
        if (!$tokenApp) {
            dump('token not exist');
            return false;
        }
        $this->token = $token;
        dump($this->access_token, $this->shop);
        $reg = $this->ShopifyController->shopify_call($this->access_token, $this->shop, '/admin/api/2022-01/shop.json', [], 'GET');
        dump(json_encode($reg));
        if (!$reg or !is_array($reg) or !isset($reg['response'])) {
            dump('response wrong');
            return false;
        }
        return true;
    }

    /**
     * The function for get account data
     *
     * @param str $url
     *
     * @return array
     */
    public function getReq($url)
    {

        try {

            $str = $this->token . $this->secret;
            $md5 = md5($str);
            $lp = $this->login . ':' . $md5;
            $base64 = base64_encode($lp);
            //$url = 'https://' . $lp . '@' . $url;
            $url = 'https://' . $url;

            //dd($this->token , $this->secret,$str, $this->login . ':' . $md5, $base64);
            $curl = curl_init($url);
            curl_setopt(
                $curl, CURLOPT_HTTPHEADER, [
                "Authorization: Basic {$base64}",
                "Content-Type: application/json",
            ]);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $result1 = curl_exec($curl);
            $result = json_decode($result1, true);
            if (!isset($result['id'])) {

                $err = curl_error($curl);
                //dump($response);
                //dump($err);
                $info = curl_getinfo($curl);
                dump(
                    json_encode(
                        [
                            $result,
                            $err,
                            $info,
                            $info["http_code"],
                        ]), 'error');
                dump($result1, $err, 'error');

                Yii::log('expected id in response 01 ' . $url. ' '.json_encode(
                        [
                            $result,
                            $err,
//                            $info,
                            $info["http_code"] ?? '',
                        ]), 'error');

                if ($info["http_code"] == 401) {
                    dump(
                        'HTTP Basic: Access denied from insales', 'error');
                }
            }
            curl_close($curl);
        } catch (Throwable $t) {
            dump($t->getMessage(), 'error');
        }

        //        Yii::log(
        //            json_encode(
        //                [
        //                    $result,
        //                    'получил новый токен ' . $this->token,
        //                    'секрет из настроек ' . $this->secret,
        //                    'токен и секрет ' . $str,
        //                    "md5 от токена и секрета " . $md5,
        //                    'логин и мд5 ' . $this->login . ':' . $md5,
        //                    'итоговый урл ' . $url,
        //                    "Authorization: Basic {$base64}",
        //                    'результат ' . json_encode($result),
        //
        //                ]), 'error');

        return $result;
    }

    /**
     * The function for make curl request
     *
     * @param $clientDomain
     *
     * @return array
     */
    public function validateurl($url) : array
    {
        //        $url = 'https://insales-admin.comx.su/api/v8/nginx/validate';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        //        dump($response);
        //        dump($err);
        //        dd(1);
        $retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        curl_close($ch);

        //        dd($retcode, $body);
        Yii::log(
            json_encode(
                [
                    'response validate url tenant stats',
                    [
                        $retcode,
                        $body,
                    ],
                ]), 'error');

        return [
            $retcode,
            $body,
        ];
    }

    /**
     * Create new columns in objects if it necessary
     *
     * @param EDbConnection $connection
     * @param array $fields
     * @param int $appId
     * return void
     *
     */
    private function checkAndCreateColumns($connection, $fields, $appId = null)
    {

        if (!$appId) {
            $appId = $this->_appId1;
        }

        $strInvoicesIdsForSync = implode('\',\'', array_keys($fields));
        $where = '(\'' . $strInvoicesIdsForSync . '\')';

        $sql = 'select string_agg("ColumnName",\'","\') 
        from objectcolumns where "AppId" = ' . $appId . ' and "ColumnName" in ' . $where;
        $res = $connection->createCommand($sql)
            ->queryScalar();
        eval("\$existColls = [\"$res\"];");

        foreach ($fields as $customerFieldName => $customerFieldValue) {
            if (!in_array($customerFieldName, $existColls)) {
                //                dump($customerFieldName);
                $objectColumn = [
                    'AppId' => $appId,
                    'ColumnName' => $customerFieldName,
                    'Visible' => 1,
                    'SortOrder' => 9999,
                    'SystemColumn' => 1,
                    //'ColumnType' => 'text',
                    'ColumnType' => 'integer',

                ];
                if ($customerFieldName == 'bcLastLogin' or $customerFieldName == 'server' or
                    $customerFieldName == 'tenant' or $customerFieldName == 'Email' or $customerFieldName == 'url' or
                    $customerFieldName == 'tenentCreated' or $customerFieldName == 'Phone' or
                    $customerFieldName == 'localTenant' or $customerFieldName == 'name' or
                    $customerFieldName == 'surname' or $customerFieldName == 'site' or
                    $customerFieldName == 'decision' or $customerFieldName == 'from' or
                    $customerFieldName == 'appName' or $customerFieldName == 'appWebSiteUrl' or
                    $customerFieldName == 'appParams'
                ) {
                    $objectColumn['ColumnType'] = 'text';
                }
                $this->addObjectColumns($connection, $objectColumn, $objectColumn['ColumnType']);
            }
        }

    }

    /**
     * add column to object
     *
     * @param EDbConnection $connection
     * @param array $params
     * @param str $dataType
     * return void
     */
    private function addObjectColumns($connection, $params, $dataType = 'text')
    {
        $columnName = $params['ColumnName'];
        $table = 'objects';
        if ($dataType == 'integer') {
            $dataType = 'bigint';
        }
        try {
            $exist = $connection->createCommand(
                'select column_name from information_schema.columns
                        where table_name = \'' . $table . '\' and column_name = :column_name')
                ->queryRow(
                    true, [
                    ':column_name' => $columnName,
                ]);

            if (empty($exist)) {
                $connection->createCommand(
                    'ALTER TABLE ' . $table . ' ADD COLUMN "' . $columnName . '" ' . $dataType . ';')
                    ->execute();

            }
            try {
                $insert = $this->Api8->genInsertMultipart($params);
            } catch (Throwable $exception) {
                Yii::log($exception->getMessage(), 'error');
                dump($exception->getMessage());

            }
            $connection->createCommand(
                'insert into objectcolumns ' . $insert['fields'] . ' ' . $insert['values'])
                ->execute($insert['params']);

            $params2 = [
                'AppId' => $params['AppId'],
                'ColumnName' => 'objects->' . $params['ColumnName'],
                'Visible' => $params['Visible'],
                'SortOrder' => $params['SortOrder'],
                'ColumnType' => $params['ColumnType'],
                'filter' => '<input placeholder="Filter by ' . $params['ColumnName'] . '" name="Objects[' .
                    $params['ColumnName'] . ']" id="Objects_url' . $params['ColumnName'] .
                    '" type="text" maxlength="20" />',
            ];
            try {
                $insert = $this->Api8->genInsertMultipart($params2);
            } catch (Throwable $exception) {
                Yii::log($exception->getMessage(), 'error');
                dump($exception->getMessage());

            }
            $connection->createCommand(
                'insert into backendcolumns ' . $insert['fields'] . ' ' . $insert['values'])
                ->execute($insert['params']);

        } catch (Throwable $exception) {
            Yii::log($exception->getMessage(), 'error');
            dump($exception->getMessage());

        }
    }








    /**
     * try sync data with token
     *
     * @param string $objectId
     * return void
     */
    public function actionGetOrders()
    {
        $time_start = microtime(true);
        //        $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        //        socket_bind($sock, '127.0.0.1',5000);
        //        socket_listen($sock,1);

        $this->Api8 = new Restapi8Controller("");

        $connStatTenant = Tenants::getTenantDbConnection($this->_tenant1);

        $fields['ordersInsales'] = 'text';
        $this->checkAndCreateColumns($connStatTenant, $fields, 3);

        $tenantIterator =
            new ETenantsSQLDataProviderIterator(' "Name" like \'ins%\' and "Name" not like \'%insales%\' ');

        foreach ($tenantIterator as $tenantDatabase) {

            $affected1 = $connStatTenant->createCommand(
                'select * from objects where tenant = :tenant')
                ->queryAll(true, [
                    ':tenant' => $tenantDatabase->Name,
                ]);
            if (isset($affected1['ordersInsales'])) {
                if (strlen($affected1['ordersInsales']) > 10) {
                    continue;
                }
            }

            dump($tenantDatabase->Name);
            $connectionItem = Tenants::getTenantDbConnection($tenantDatabase->Name);
            Yii::app()
                ->setComponent('db', $connectionItem);
            Yii::app()->db->setActive(false);
            Yii::app()->db->setActive(true);
            Yii::app()->params->customShortlinkTable = 'short_links';
            Yii::app()->params['client_portal'] = $tenantDatabase->Name;
            $id = 0;
            $res = $this->showAccOrders($connectionItem);
            dd($res);
            if (!$res) {
                continue;
            }

            $connStatTenant->createCommand(
                'update objects
                                  set "ordersInsales" = :TokenStatus
                                  where tenant = :tenant ')
                ->execute(
                    [
                        ':TokenStatus' => json_encode($res),
                        ':tenant' => $tenantDatabase->Name,
                    ]);

            dump($res);

            dump('');
            dump('');
            dump('');
            dump('');
        }

        $t = microtime(true) - $time_start;
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));

        print $d->format("Y-m-d H:i:s.u"); // note at point on "u"

    }



    /**
     * try account info show
     *
     * @param EDbConnection $connection
     * @param int $idIns
     * return bool
     *
     */
    private function showAccOrders($connection)
    {
        $token = null;
        $shop = null;
        $affected1 = $connection->createCommand('select * from list12_insales')
            ->queryAll(true);
        if (!$affected1) {
            dump('list12 not exist');
            return false;
        }
        $shop = null;
        foreach ($affected1 as $row) {
            if ($row['type'] == 'tk') {
                $token = $row['value'];
                if ($info = json_decode($row['info'], true)) {
                    $shop = $info['shop'] ?? null;
                }
            }
        }
        if (!$token) {
            dump('token not exist');
            return false;
        }
        $this->token = $token;
        $reg = $this->getReq($shop . '/admin/orders.json?fulfillment_status[]=delivered');
        dd($reg);
        dump(json_encode($reg));
        if (!$reg or !is_array($reg) or !isset($reg['id'])) {
            dump('response wrong');
            return false;
        }
        return true;
    }








    /**
     * try set payment paln
     *
     * @param string $objectId
     * return void
     */
    public function actionSetPaymentPlan($tenantName = '')
    {                           //docker exec -t mbst_php72 php /home/www/mobsted/boiler/yiic insales SetPaymentPlan --tenantName=ins925006
        $time_start = microtime(true);
        //        $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('tcp'));
        //        socket_bind($sock, '127.0.0.1',5000);
        //        socket_listen($sock,1);

        $this->Api8 = new Restapi8Controller("");

        $connStatTenant = Tenants::getTenantDbConnection($this->_tenant1);

        $fields['ordersInsales'] = 'text';
        $this->checkAndCreateColumns($connStatTenant, $fields, 3);
        if ($tenantName != '') {
            $tenantIterator = new ETenantsSQLDataProviderIterator('"Name" = \'' . $tenantName . '\'');
            Yii::trace('Имя тенанта задано');
        } else {
            dd('require tenantName');
            $tenantIterator =
                new ETenantsSQLDataProviderIterator(' "Name" like \'ins%\' and "Name" not like \'%insales%\' ');
        }
        foreach ($tenantIterator as $tenantDatabase) {


            dump($tenantDatabase->Name);
            $connectionItem = Tenants::getTenantDbConnection($tenantDatabase->Name);
            Yii::app()
                ->setComponent('db', $connectionItem);
            Yii::app()->db->setActive(false);
            Yii::app()->db->setActive(true);
            Yii::app()->params->customShortlinkTable = 'short_links';
            Yii::app()->params['client_portal'] = $tenantDatabase->Name;
            $id = 0;
            $res = $this->SetPaymentPaln($connectionItem);

            if (!$res) {
                continue;
            }

            dump($res);

            dump('');
            dump('');
            dump('');
            dump('');
        }

        $t = microtime(true) - $time_start;
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));

        print $d->format("Y-m-d H:i:s.u"); // note at point on "u"

    }

    /**
     * try set payment
     *
     * @param EDbConnection $connection
     * @param int $idIns
     * return bool
     *
     */
    private function SetPaymentPaln($connection)
    {
        $token = null;
        $shop = null;
        $affected1 = $connection->createCommand('select * from list12_insales')
            ->queryAll(true);
        if (!$affected1) {
            dump('list12 not exist');

            return false;
        }
        $shop = null;
        foreach ($affected1 as $row) {
            if ($row['type'] == 'tk') {
                $token = $row['value'];
                if ($info = json_decode($row['info'], true)) {
                    $shop = $info['shop'] ?? null;
                }
            }
        }
        if (!$token) {
            dump('token not exist');

            return false;
        }

        $ApiInsales = new InsalesController("");

        $ApiInsales->token = $this->token = $token;
        $ApiInsales->login = $this->login;
        $ApiInsales->secret = $this->secret;

        $del = $ApiInsales->delReq([], $shop . '/admin/recurring_application_charge.json');
        dump(json_encode($del));
        $date = strtotime("+30 day");
        $param = [
            'recurring_application_charge' => [
                'monthly' => 200,
                'trial_expired_at' => date('Y-M-d ', $date),
            ],
        ];

        $reg = $ApiInsales->postReq($param, $shop . '/admin/recurring_application_charge.json');
        dump(json_encode($reg), $param);
        if (!$reg or !is_array($reg) or !isset($reg['external_id']) or !isset($reg['monthly'])) {
            dump('response wrong');

            return false;
        }
        dump(urldecode($reg['external_id'][0] ?? ''));
        $get = $this->getReq($shop . '/admin/recurring_application_charge.json');
        dump(json_encode($get));

        return true;
    }



}
