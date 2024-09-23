<?php
/*
========================================================================
|контактные данные : почта - art-ti3@yandex.ru , telegram - @manclassic|
========================================================================
*/
namespace konkord;
include_once(__DIR__ . "/config.php");



class SocialNetworkAPI
{
    private $parameters;

    function __construct($params = [])
    {
        $this->parameters = $params;
    }

    // Установка параметров
    public function setParams($params)
    {
        $this->parameters = array_merge($this->parameters, $params);
    }

    // Сортировка сообщений
    public function sortMessage()
    {
        $this->handleTelegram();
        $this->handleWhatsApp();
        $this->handleVk();
    }

    // Обработка сообщений для Telegram
    private function handleTelegram()
    {
        $idTelegram = $this->parameters['id_telegram'] ?? null;
        if (!$idTelegram) return;

        $message = $this->parameters['message'] ?? '';
        $urlImg = $this->parameters['url_img'] ?? null;
        $urlFile = $this->parameters['url_file'] ?? null;
        $urlVideo = $this->parameters['url_video'] ?? null;
        $replyMarkup = $this->parameters['reply_markup'] ?? null;

        if ($urlImg) {
            $this->sendTelegramMedia('sendPhoto', $urlImg, $idTelegram, $message);
        } elseif ($urlFile) {
            $this->sendTelegramMedia('sendDocument', $urlFile, $idTelegram, $message);
        } elseif ($urlVideo) {
            $this->sendTelegramMedia('sendVideo', $urlVideo, $idTelegram, $message);
        } elseif ($replyMarkup) {
            $this->sendTelegramMessage($message, $idTelegram, $replyMarkup);
        } else {
            $this->sendTelegramMessage($message, $idTelegram);
        }
    }

    // Обработка сообщений для WhatsApp
    private function handleWhatsApp()
    {
        $idWhatsApp = $this->parameters['id_whatsapp'] ?? null;
        if (!$idWhatsApp) return;

        $message = $this->parameters['message'] ?? '';
        $urlImg = $this->parameters['url_img'] ?? null;
        $urlFile = $this->parameters['url_file'] ?? null;

        if ($urlImg) {
            $this->sendWhatsAppMedia($urlImg, $idWhatsApp, $message);
        } elseif ($urlFile) {
            $this->sendWhatsAppFile($urlFile, $idWhatsApp, $message);
        } else {
            $this->sendWhatsAppMessage($message, $idWhatsApp);
        }
    }

    // Обработка сообщений для ВКонтакте
    private function handleVk()
    {
        $idVk = $this->parameters['id_vk'] ?? null;
        if (!$idVk) return;

        $message = $this->parameters['message'] ?? '';
        $urlImg = $this->parameters['url_img'] ?? null;
        $urlFile = $this->parameters['url_file'] ?? null;

        if ($urlImg) {
            $this->sendVkMedia($urlImg, $idVk, $message);
        } elseif ($urlFile) {
            $this->sendVkFile($urlFile, $idVk, $message);
        } else {
            $this->sendVkMessage($message, $idVk);
        }
    }

    // Отправка сообщения в Telegram
    private function sendTelegramMessage($text, $user_id, $replyMarkup = null)
    {
        $data = [
            'chat_id' => $user_id,
            'text' => $text
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }

        $this->sendRequest('https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/sendMessage', $data);
    }

    // Отправка медиа в Telegram
    private function sendTelegramMedia($type, $url, $user_id, $caption = '')
    {
        $data = [
            'chat_id' => $user_id,
            $this->getMediaTypeField($type) => $url,
            'caption' => $caption
        ];

        if ($type === 'sendVideo') {
            $data['supports_streaming'] = true;
        }

        $this->sendRequest('https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/' . $type, $data);
    }

    // Отправка сообщения в WhatsApp
    private function sendWhatsAppMessage($text, $id_number)
    {
        $this->sendRequest('https://api.green-api.com/waInstance' . ID_INSTANCE . '/SendMessage/' . WHATSAPP_TOKEN, [
            'chatId' => $id_number,
            'message' => $text
        ], true);
    }

    // Отправка файла в WhatsApp
    private function sendWhatsAppFile($url_file, $id_number, $text)
    {
        $this->sendWhatsAppMedia($url_file, $id_number, $text, 'SendFileByUrl');
    }

    // Отправка медиа в WhatsApp
    private function sendWhatsAppMedia($url_img, $id_number, $text)
    {
        $this->sendRequest('https://api.green-api.com/waInstance' . ID_INSTANCE . '/SendFileByUrl/' . WHATSAPP_TOKEN, [
            'chatId' => $id_number,
            'caption' => $text,
            'urlFile' => $url_img,
            'fileName' => basename($url_img)
        ], true);
    }

    // Отправка сообщения в VK
    private function sendVkMessage($message, $id_number)
    {
        $this->sendRequest('https://api.vk.com/method/messages.send', [
            'message' => $message,
            'peer_id' => $id_number,
            'access_token' => VK_TOKEN,
            'v' => '5.131'
        ]);
    }

   // Отправка медиа (фото/видео) в VK
    private function sendVkMedia($url_media, $id_number, $text)
    {
        // Получаем URL сервера для загрузки фото
        $uploadServerResponse = $this->sendRequest('https://api.vk.com/method/photos.getMessagesUploadServer', [
            'access_token' => VK_TOKEN,
            'v' => '5.131',
            'peer_id' => $id_number
        ]);

        if (isset($uploadServerResponse['response']['upload_url'])) {
            $uploadUrl = $uploadServerResponse['response']['upload_url'];

            // Загружаем медиа файл на полученный сервер
            $uploadedMediaResponse = $this->uploadFileToVk($uploadUrl, $url_media);

            if (isset($uploadedMediaResponse['photo'])) {
                // Сохраняем загруженное фото в альбом VK
                $saveMediaResponse = $this->sendRequest('https://api.vk.com/method/photos.saveMessagesPhoto', [
                    'access_token' => VK_TOKEN,
                    'v' => '5.131',
                    'photo' => $uploadedMediaResponse['photo'],
                    'server' => $uploadedMediaResponse['server'],
                    'hash' => $uploadedMediaResponse['hash']
                ]);

                if (isset($saveMediaResponse['response'][0]['id'])) {
                    $photoId = $saveMediaResponse['response'][0]['id'];

                    // Отправляем фото в сообщении
                    $this->sendVkMessageWithAttachment($id_number, $text, 'photo' . $saveMediaResponse['response'][0]['owner_id'] . '_' . $photoId);
                }
            }
        }
    }

    // Отправка файла в VK
    private function sendVkFile($url_file, $id_number, $text)
    {
        // Получаем сервер для загрузки документа
        $uploadServerResponse = $this->sendRequest('https://api.vk.com/method/docs.getMessagesUploadServer', [
            'access_token' => VK_TOKEN,
            'v' => '5.131',
            'peer_id' => $id_number,
            'type' => 'doc'  // Указываем, что загружаем документ
        ]);

        if (isset($uploadServerResponse['response']['upload_url'])) {
            $uploadUrl = $uploadServerResponse['response']['upload_url'];

            // Загружаем файл на полученный сервер
            $uploadedFileResponse = $this->uploadFileToVk($uploadUrl, $url_file);

            if (isset($uploadedFileResponse['file'])) {
                // Сохраняем документ
                $saveDocResponse = $this->sendRequest('https://api.vk.com/method/docs.save', [
                    'access_token' => VK_TOKEN,
                    'v' => '5.131',
                    'file' => $uploadedFileResponse['file']
                ]);

                if (isset($saveDocResponse['response']['doc']['id'])) {
                    $docId = $saveDocResponse['response']['doc']['id'];

                    // Отправляем документ в сообщении
                    $this->sendVkMessageWithAttachment($id_number, $text, 'doc' . $saveDocResponse['response']['doc']['owner_id'] . '_' . $docId);
                }
            }
        }
    }

    // Вспомогательный метод для отправки сообщения с вложением в VK
    private function sendVkMessageWithAttachment($id_number, $message, $attachment)
    {
        $this->sendRequest('https://api.vk.com/method/messages.send', [
            'message' => $message,
            'peer_id' => $id_number,
            'attachment' => $attachment,  // Вложение (фото или документ)
            'access_token' => VK_TOKEN,
            'v' => '5.131'
        ]);
    }

    // Вспомогательный метод для загрузки файла на VK сервер
    private function uploadFileToVk($uploadUrl, $filePath)
    {
        $curl = curl_init();
        $fileData = new \CURLFile($filePath); // Создаем объект файла для загрузки

        curl_setopt_array($curl, [
            CURLOPT_URL => $uploadUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => ['file' => $fileData],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    // Универсальный метод отправки запросов
    private function sendRequest($url, $postData, $isJson = false)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => $isJson ? json_encode($postData) : $postData,
            CURLOPT_HTTPHEADER => $isJson ? ['Content-Type: application/json'] : []
        ]);
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result, true);
    }

    // Возвращает соответствующее поле для медиа-типа
    private function getMediaTypeField($type)
    {
        switch ($type) {
            case 'sendPhoto':
                return 'photo';
            case 'sendDocument':
                return 'document';
            case 'sendVideo':
                return 'video';
            default:
                return '';
        }
    }
}

