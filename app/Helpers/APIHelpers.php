<?php
    namespace App\Helpers;
    class APIHelpers {

        // format the response for the api
        public static function createApiResponse($is_error , $code , $message_en , $message_ar , $content , $lang){
          if($lang == 'en'){
            $message = $message_en;
          }else{
            $message = $message_ar;
          }
          $result = [];
          if($is_error){
              $result['success'] = false;
              $result['code'] = $code;
              $result['message'] = $message;
          }else{
              $result['success'] = true;
              $result['code'] = $code;
              if($content == null){
                  $result['message'] = $message;
                  $result['data'] = [];
              }else{
                  $result['data'] = $content;
              }
          }
          return $result;
      }

        // calculate the distance
       public static function distance($lat1, $lon1, $lat2, $lon2, $unit) {
            if (($lat1 == $lat2) && ($lon1 == $lon2)) {
              return 0;
            }
            else {
              $theta = $lon1 - $lon2;
              $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
              $dist = acos($dist);
              $dist = rad2deg($dist);
              $miles = $dist * 60 * 1.1515;
              $unit = strtoupper($unit);

              if ($unit == "K") {
                return ($miles * 1.609344);
              } else if ($unit == "N") {
                return ($miles * 0.8684);
              } else {
                return $miles;
              }
            }
          }

          // send fcm notification
          public static function send_notification($title , $body , $image , $data , $token){

            $message= $body;
            $title= $title;
            $image = $image;
            $path_to_fcm='https://fcm.googleapis.com/fcm/send';
            $server_key="AAAAcsW3O_o:APA91bHkh4tLV7R7a1GoOqqB_jDo12xwkVxvi58WRUegWOQ005lafaIGDzUHT4SB2WeFQTA9wBM6QoVUlte0pOHyS1EayjTc9cML-UN46vPN82vHfLjmFHbVe048bMIbMBrRj3ItKP_H";

            $headers = array(
                'Authorization:key=' .$server_key,
                'Content-Type:application/json'
            );

            $fields =array('registration_ids'=>$token,
                            'notification'=>array('title'=>$title,'body'=>$message , 'image'=>$image));

            $payload =json_encode($fields);
            $curl_session =curl_init();
            curl_setopt($curl_session,CURLOPT_URL, $path_to_fcm);
            curl_setopt($curl_session,CURLOPT_POST, true);
            curl_setopt($curl_session,CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl_session,CURLOPT_RETURNTRANSFER,true);
            curl_setopt($curl_session,CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_session,CURLOPT_IPRESOLVE, CURLOPT_IPRESOLVE);
            curl_setopt($curl_session,CURLOPT_POSTFIELDS, $payload);
            $result=curl_exec($curl_session);
            curl_close($curl_session);
            return $result;
          }

        public static function send_chat_notification($tokens, $title = "hello", $msg = "helo msg", $type = 1, $chat = null, $jobs = null)
        {
            $key = 'AAAAcsW3O_o:APA91bHkh4tLV7R7a1GoOqqB_jDo12xwkVxvi58WRUegWOQ005lafaIGDzUHT4SB2WeFQTA9wBM6QoVUlte0pOHyS1EayjTc9cML-UN46vPN82vHfLjmFHbVe048bMIbMBrRj3ItKP_H';
            $fields = array
            (
                "registration_ids" => (array)$tokens,  //array of user token whom notification sent two
//            "registration_ids" => (array)'diLndYfZRFyxw8nOjU5yt0:APA91bGYE5TyP2VjgUHHEuCP5-dMEoY8K4AgEl_JuWYjcFyJxS1MamBtJhmp4y-q-lhYWF6AXvy9OpgOJJsJyJ5qSNCHFvSR3iWODWVb84NkbnpZYcuNL0mkforreA89wcwrHuntJdaG',
                "priority" => 10,
                'data' => [ // android developer
                    'title' => $title,
                    'body' => $msg,
                    'chat' => $chat,
                    'type' => $type,
                    'icon' => 'myIcon',
                    'sound' => 'mySound',
                    'jobs' => $jobs
                ],
                'notification' => [  // ios developer
                    'title' => $title,
                    'body' => $msg,
                    'chat' => $chat,
                    'type' => 3,
                    'icon' => 'myIcon',
                    'sound' => 'mySound',
                    'jobs' => $jobs
                ],
                'vibrate' => 1,
                'sound' => 1
            );
            $headers = array
            (
                'accept: application/json',
                'Content-Type: application/json',
                'Authorization: key=' . $key
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            $result = curl_exec($ch);
            if ($result === FALSE) {
                die('Curl failed: ' . curl_error($ch));
            }
            curl_close($ch);
            return $result;
        }
    }
?>
