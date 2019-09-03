<?php

if ($modx->event->name != 'OnPageNotFound') {
    return false;
}
$alias = $modx->context->getOption('request_param_alias', 'q');
if (!isset($_REQUEST[$alias])) {
    return false;
}

$request = $_REQUEST[$alias];
$tmp = explode('/', $request);
$yamarket_token = '86000001A26006D9';
//https://api.partner.market.yandex.ru/v2/campaigns/21437917/orders/3414291/status.json?oauth_token=AQAAAAAg2iZcAATBsgYLBcLzzEeTnKbZ3C5u-pM&oauth_client_id=01c5217c84a54d7da59a5511eb32473b
//$yamarket_token = $modx->getOption('yamarket_token');
if ($tmp[0] == 'yamarketapiv2' && $_REQUEST['auth-token'] == $yamarket_token) {

    switch ($tmp[1]) {
        case 'cart': {

            $json = file_get_contents("php://input");
            if (!$json) {
                header('HTTP/1.0 404 Not Found');
                echo '<h1>NO post was made</h1>';
                exit;
            } else {
                $data = array();
                $data = json_decode($json);
                $payments = array();
                $carriers = array();
                $item = array();
                $items = array();
                $ms2curr = $modx->getOption('ms2ym_main_currency');
                //file_put_contents('/var/www/avanta-premium/data/www/avanta-premium.ru/test_curr.txt', $data, FILE_APPEND | LOCK_EX);
                //$modx->log(1, print_r($data,1));

                $sum = array_reduce($data->cart->items, function ($i, $countobj) {
                    return $i += $countobj->count;
                });
                foreach ($data->cart->items as $item) {
                    $add = true;
                    $id_array = explode('c', $item->offerId);
                    $id_product = $id_array[0];
                    $price_option = 0;
                    $product = $modx->getObject('msProduct', $id_product);
                    $product_info = $product->get('id');
                    //$modx->log(1, "foreach_productID: ".$product_info);

                    $items[] = array(
                        'count' => $item->count,
                        'delivery' => true,
                        'feedId' => $item->feedId,
                        'offerId' => $item->offerId,
                        'price' => $product->get('price'),
                        'vat' => 'VAT_18',
                    );
                }

                //$modx->log(1, "array_sum: ".var_dump($items));
                $deliverydate = date('d-m-Y', strtotime(date('d-m-Y')) + 2 * 24 * 3600);
                //file_put_contents('/var/www/shehrozsru/data/www/shehrozs.ru/test_curr.txt', $deliverydate, FILE_APPEND | LOCK_EX);
                $array = array(
                    'cart' => array(
                        'deliveryCurrency' => 'RUR',
                        'taxSystem' => 'OSN',
                        'deliveryOptions' =>
                            array(
                                0 =>
                                    array(
                                        'price' => 0,
                                        'serviceName' => 'Собственная служба доставки',
                                        'type' => 'DELIVERY',
                                        'vat' => 'NO_VAT',
                                        'dates' =>
                                            array(
                                                'fromDate' => $deliverydate,
                                            ),
                                    ),
                            ),
                        'items' => $items,
                        'paymentMethods' =>
                            array(
                                0 => 'CARD_ON_DELIVERY',
                                1 => 'CASH_ON_DELIVERY',
                                2 => 'YANDEX',
                            ),
                    ),
                );

                header('Content-Type: application/json; charset=utf-8');
                $arrayjson = json_encode($array);
                echo $arrayjson;
                //$modx->log(1, $arrayjson);
                //file_put_contents('/var/www/avanta-premium/data/www/avanta-premium.ru/test_curr.txt', $arrayjson, FILE_APPEND | LOCK_EX);

            }


        }
        case 'order': {
            if ($tmp[2] == 'accept') {

                $json = file_get_contents("php://input");
                if (!$json) {
                    header('HTTP/1.0 404 Not Found');
                    echo '<h1>No post was made!</h1>';
                    exit;
                } else {
                    $scriptProperties = array(
                        'json_response' => true, // возвращать ответы в JSON
                        'allow_deleted' => false, // не добавлять в корзину товары с deleted = 1
                        'allow_unpublished' => false, // не добавлять в корзину товары с published = 0
                    );
                    $miniShop2 = $modx->getService('minishop2', 'miniShop2', MODX_CORE_PATH . 'components/minishop2/model/minishop2/', $scriptProperties);
                    if (!($miniShop2 instanceof miniShop2)) return;
                    // Инициализируем класс в текущий контекст
                    $miniShop2->initialize($modx->context->key, $scriptProperties);

                    $data = json_decode($json);
                    $order_data = array();
                    $miniShop2->cart->clean();
                    $miniShop2->order->clean();


                    header('Content-Type: application/json; charset=utf-8');
                        $miniShop2->order->add('delivery',2); //1 - самовывоз, 2 - доставка
                        foreach ($data->order->items as $item) {
                            //Добавляем товары в корзину
                            $miniShop2->cart->add($item->offerId, $item->count); //(id товара, кол-во товара)
                        }
                        if (!$data->order->fake) {
                            $miniShop2->order->add('email', 'yandexmarket@avanta-premium.ru');
                        }
                        else {
                            $miniShop2->order->add('email', 'testyandexmarket@avanta-premium.ru');
                        }

                        $response = $miniShop2->order->submit();
                        $res = json_decode($response, true);
                        $resultat = $res['success'];
                    //file_put_contents('/var/www/shehrozsru/data/www/shehrozs.ru/test_res1pon1se.txt', $response, LOCK_EX);
                    $msordertable = $modx->getTableName('msOrder');
                    $resdatamsorder = $res['data']['msorder'];
                    $yandexmarketid = $data->order->id;
                    $sql = ("UPDATE $msordertable  SET `yandexmarketid` = $yandexmarketid WHERE id=$resdatamsorder");
                    $resultatdb = $modx->query($sql);
                    /*file_put_contents('/var/www/shehrozsru/data/www/shehrozs.ru/test_res.txt', var_dump($sql), LOCK_EX);
                    if (!is_object($resultatdb)) {
                        file_put_contents('/var/www/shehrozsru/data/www/shehrozs.ru/test_res1.txt', "no result", LOCK_EX);
                    // ДОБАВИТЬ ЛОГ В MODX в случае ОШИБКИ
                    }
                    else {
                        $row = $resultatdb->fetch(PDO::FETCH_ASSOC);
                        file_put_contents('/var/www/shehrozsru/data/www/shehrozs.ru/test_res2.txt', "Result:".print_r($row,true), LOCK_EX);
                    }
                    */
                    if ($resultat) {
                        $array = array(
                            'order' => array(
                                'accepted' => true,
                                'id' => strval($res['data']['msorder']),
                            )
                        );
                    } else {
                        $array = array(
                            'order' => array(
                                'accepted' => false,
                                'reason' => 'OUT_OF_DATE'
                            )
                        );
                    }
                    $arrayjson = json_encode($array);
                    echo $arrayjson;
                }
                break;
            } else if ($tmp[2] == 'items') {

                header('HTTP/1.1 200 OK');
                break;
            } else if ($tmp[2] == 'status') {
                $json = file_get_contents("php://input");
                if (!$json) {
                    header('HTTP/1.0 404 Not Found');
                    echo '<h1>No post was made!</h1>';
                    exit;
                } else {
                    /*
                     {
   "order":{
      "id":3414146,
      "fake":true,
      "currency":"RUR",
      "paymentType":"POSTPAID",
      "paymentMethod":"CASH_ON_DELIVERY",
      "status":"PROCESSING",
      "creationDate":"15-01-2018 09:00:27",
      "itemsTotal":107400,
      "total":107400,
      "delivery":{
         "type":"DELIVERY",
         "price":0,
         "serviceName":"Собственная служба доставки",
         "deliveryServiceId":99,
         "deliveryPartnerType":"SHOP",
         "dates":{
            "fromDate":"17-01-2018",
            "toDate":"17-01-2018"
         },
         "region":{
            "id":213,
            "name":"Москва",
            "type":"CITY",
            "parent":{
               "id":1,
               "name":"Москва и Московская область",
               "type":"SUBJECT_FEDERATION",
               "parent":{
                  "id":3,
                  "name":"Центральный федеральный округ",
                  "type":"COUNTRY_DISTRICT",
                  "parent":{
                     "id":225,
                     "name":"Россия",
                     "type":"COUNTRY"
                  }
               }
            }
         },
         "address":{
            "country":"Россия",
            "city":"Москва",
            "subway":"комсомольская",
            "street":"название улицы",
            "house":"21",
            "entrance":"подъезд",
            "entryphone":"домофон",
            "floor":"этаж",
            "recipient":"Иван Иванов",
            "phone":"+71111111111"
         }
      },
      "buyer":{
         "id":"fm2vMBNEC/XlWK3KrVZFrA==",
         "lastName":"Иванов",
         "firstName":"Иван",
         "phone":"+71111111111",
         "email":"avanta-premium@yandex.ru"
      },
      "items":[
         {
            "id":3550445,
            "feedId":483323,
            "offerId":"11",
            "feedCategoryId":"4",
            "offerName":"KUBIK",
            "price":48700,
            "buyer-price":48700,
            "count":1,
            "params":""
         },
         {
            "id":3550446,
            "feedId":483323,
            "offerId":"19",
            "feedCategoryId":"4",
            "offerName":"ADVANCE",
            "price":58700,
            "buyer-price":58700,
            "count":1,
            "params":""
         }
      ],
      "notes":"примечание"
   }
}
                    */
                    $data = json_decode($json);
                    $count = $modx->getCount('modUserProfile', array('email' => $data->order->buyer->email));
                    if($count > 0){
                        // такой пользователь существует!
                    }
                    else {
                        //создать юзера;
                        $miniShop2->changeOrderStatus($order_id, 2);
                         $user->set('username', $data->order->buyer->email);
                            $user->set('password', '1234567890');
                            // сохраняем
                            $user->save();
                       // TODO: отправка писем юзеру на почту о новом заказе и его данных для входа
                        // TODO: $address - ФИО полный адрес с метро, телефон и тд
                        $profile = $modx->newObject('modUserProfile');
                        $profile->set('fullname', 'Фамилия Имя');
                        $profile->set('email', $data->order->buyer->email);
                        // добавляем профиль к пользователю
                        $user->addOne($profile);

                        // сохраняем
                        $profile->save();
                        $user->save();
                    }
                    /*Смена с маркета
                    $miniShop2->changeOrderStatus($order->get('id'), 2);
                    echo $modx->toJSON(array(
                        'success' => true
                        'message' => '',
                        'data'    => array(),
                    ));
                    exit;
                    */
                }

                echo "order status";
                break;
            } else if ($tmp[2] == 'shipment' && $tmp[3] == 'status') {
                header('HTTP/1.1 200 OK');
                break;
            }
        }
    }
    exit;
} else {
    header('HTTP/1.0 403 Forbidden');
    echo "Invalid token";
    exit;
}