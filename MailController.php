<?php

namespace api\controllers;

use api\models\Contact;
use api\models\User;
use Ddeboer\Imap\Message;
use MailSo\Imap\Exceptions\Exception;
use yii;
use api\components\CustomAuthException;
use yii\data\ArrayDataProvider;
use yii\helpers\ArrayHelper;
use api\components\BaseControllerWOReg;
use api\models\ScheduledSend;
use api\components\MailService\MailService as MService;
use api\models\TaggedMessage;
use api\models\Tag;
use api\models\FlagsMessages;
use api\components\MailClient\MClient;
use DateTime;
use DateTimeZone;

/**
 * Site controller
 */
class MailController extends BaseControllerWOReg {

    /**
     * @return ArrayDataProvider
     */
    public function actionIndex() {

        $mbox = trim(urldecode(Yii::$app->request->get('mbox', false)));
        
       
        
        $tag_id = Yii::$app->request->get('tag_id', false);
        $search_tag_id = Yii::$app->request->get('search_tag_id', false);
        $filter = Yii::$app->request->get('filter', false);
        $search = Yii::$app->request->get('search', false);
        $searchPart = Yii::$app->request->get('search_part', false);
        $searchDateStart = Yii::$app->request->get('search_start', false);
        $searchDateEnd = Yii::$app->request->get('search_end', false);
        $sort = Yii::$app->request->get('sort', false);
        $sortReverse = Yii::$app->request->get('sortReverse', true);


        $offset = Yii::$app->request->get('offset', 0);


        $user = $this->getUser();

        $imapSort = SORTDATE;
        $sortings = [
            'date' => SORTDATE,
            'size' => SORTSIZE,
            'from' => SORTFROM,
            'subject' => SORTSUBJECT,
            'unread' => 'UNSEEN'
        ];

        if (isset($sortings[$sort])) {
            $imapSort = $sortings[$sort];
        }

        $outputParams = [
            'per-page' => \Yii::$app->request->get('per-page', 20),
            'page' => \Yii::$app->request->get('page', 1),
            'part' => \Yii::$app->request->get('part', 'all'),
            'len' => \Yii::$app->request->get('len', false),
            'smbox' => \trim(urldecode(Yii::$app->request->get('smbox', false))),
        ];

        if ($tag_id !== false) {
            $tag = Tag::findOne(['user_id' => $user->id, 'id' => $tag_id]);
            if (!$tag) {
                $tag_id = false;
            }
        }

        if ($search_tag_id !== false) {
            $tag = Tag::findOne(['user_id' => $user->id, 'id' => $search_tag_id]);
            if (!$tag) {
                $search_tag_id = false;
            }
        }



        if (false && !$tag_id && !$search_tag_id && !$search && !$searchPart && !$searchDateEnd && !$searchDateStart && !$filter) {
            $messagesInfo = MClient::start($user)->getMessagesHeadersList($mbox, '0', $outputParams, $search, $filter, $searchPart, $searchDateStart, $searchDateEnd); //->getLoginUserImap($user)->Message('INBOX', 43);//->getMessagesHeadersList();////MessageList('INBOX', 0,  20);
            $messages = $messagesInfo['@Collection'];
            
//            echo 1; die;
//            
//            $messages = MService::start($user)->setOutputParams($outputParams)->getPaginatedMessagesNoSearch($mbox);
        } else if ($tag_id !== false) {

             $messagesInfo  = MClient::start($user)->getMessagesHeadersListByTag($tag_id,$outputParams, $offset);
             $messages_data = $messagesInfo['items'];
        } else {

            //echo $offset; die;
            $messagesInfo = MClient::start($user)->getMessagesHeadersList($mbox, $offset, $outputParams, $search, $filter, $searchPart, $searchDateStart, $searchDateEnd); //->getLoginUserImap($user)->Message('INBOX', 43);//->getMessagesHeadersList();////MessageList('INBOX', 0,  20);
           // var_dump($messagesInfo["_links"]); die;
           
            
            $messages_data = $messagesInfo['items']["@Collection"];
            $messages_data['unseenCount'] = $messagesInfo['items']['MessageUnseenCount'];
             
             //var_dump($messages_data); die;
         //   echo 2; die;
//                
//                $messages = MService::start($user)
//                ->setOutputParams($outputParams)
//                ->getPaginatedMessagesByInboxAndSearch($search, $mbox, $search_tag_id, $searchPart, $filter, $searchDateStart, $searchDateEnd, $imapSort, $sortReverse);
        }

//        
//         var_dump($messages);
//        exit();
//        foreach ($messages as $key => $message) {
//            if ($message['date']) {
//                try {
//                    $message['date']->setTimezone(new \DateTimeZone(Yii::$app->user->identity->timezone));
//                } catch (\Exception $e) {
//
//                }
//            }
//        }

/*
 foreach ($messages_data as $key => $message) {
            
  
            if ($message['date']) {
                try {
                    $message['date']['date'] =new DateTime($message['date']["date"], new DateTimeZone(Yii::$app->user->identity->timezone)); //->setTimezone(new \DateTimeZone(Yii::$app->user->identity->timezone));
                } catch (\Exception $e) {

                }
            }
        } */



        $data = ArrayHelper::map($messages_data, 'fromAddress', 'from');
        if (\Yii::$app->user->identity) {
            Contact::addContact($data);
        }

        foreach ($messages_data as $key => $message) {
            try {
                $json = yii\helpers\Json::encode($message);
            } catch (\Exception $e) {
                $message['body'] = utf8_encode($message['body']);

                $messages[$key] = $message;
            }
        }

        $messages_data = array_map([$this, 'fixEmptyAttachment'], $messages_data);
        //$messages_data = array_map([$this, 'addMessageSenderAvatar'], $messages_data);

//        $dataProvider = new ArrayDataProvider([
//            'allModels' => $messages,
//        ]);
        
        $data_messages['items'] = $messages_data;
        $data_messages['unseenCount'] = $messages_data['unseenCount'];
        $data_messages['_links'] = $messagesInfo["_links"];
             $data_messages['__meta'] = $messagesInfo["__meta"];
        
//        echo '<pre>';
//        
//        var_dump($dataProvider); die;
//        
//        $dataProvider['_link']['next1']= 'url some';
//        $next = $outputParams['page']+1;
//        $data_message['_links']['next'] = array('href' =>   Yii::$app->params['api_url'].'/mail?len=200&mbox=INBOX&part=bodytext&page='.$next);
//        $data_message['__meta'] = array('currentPage' => $outputParams['page'], 'pageCount' => 10, 'perPage'=> 20, 'totalCount'=>200);
//        $data_message['items'] = $messages; 
        return $data_messages;
    }

    /**
     * Fices empty attachments, fii name as 'noname'
     *
     * @param type $message
     * @return type
     */
    private function fixEmptyAttachment($message) {
        if (isset($message['attachmentsData'])) {
            $attachments = $message['attachmentsData'];
            $message['attachmentsData'] = [];

            foreach ($attachments as $k => $a) {
                if (!strlen($a['title']) && !strlen($a['fileName'])) {
                    $fname = $title = 'noname';
                    if (isset($a['mime'])) {
                        $parts = explode('/', $a['mime']);
                        $ext = end($parts);

                        if ($ext) {
                            $fname .= ".{$ext}";
                        }
                    }

//                    $message['attachmentsData'][$k]['title'] = $title;
//                    $message['attachmentsData'][$k]['fileName'] = $fname;
                } else {
                    $message['attachmentsData'][] = $a;
                }
            }
        }

        return $message;
    }

    /**
     * Adds message sender avatar link for mail.group users
     *
     * @param array $message
     * @return array
     */
    private function addMessageSenderAvatar($message) {
        $message['avatar'] = null;

        if (isset($message['fromAddress'])) {
            $addr = $message['fromAddress'];
            $isMailGroupAddr = false;

            foreach (\Yii::$app->params['all_mailgroup_domains'] as $domain) {
                if (substr($addr, -strlen($domain)) == $domain) {
                    $isMailGroupAddr = true;
                    break;
                }
            }

            if ($isMailGroupAddr) {
                $message['avatar'] = \Yii::$app->params['auth_api_url'] . '/avatar/image?email=' . $addr;
            }
        }

        return $message;
    }

    /**
     * @param $id
     * @return array|bool|null|string|yii\console\Response|yii\web\Response
     * @throws CustomAuthException
     */
    public function actionView($id) {

        $mbox = trim(urldecode(Yii::$app->request->get('mbox', 'INBOX')));
        $part = Yii::$app->request->get('part', 'all');
        $filename = Yii::$app->request->get('filename', false);
        $len = Yii::$app->request->get('len', false);
        $connection_id = Yii::$app->request->get('connection_id', false);
        $download = !Yii::$app->request->get('screen', false);
        $getNeighbours = Yii::$app->request->get('neighbours', false);
        $preview = Yii::$app->request->get('preview', false);
        $emlPreview = Yii::$app->request->get('emlPreview', false);
        $foreignImages = Yii::$app->request->get('foreignImages', false);

        $user = $this->getUser();

        if (!$connection_id) {
            $connection_id = $user->default_connection_id;
        }

        if (!$this->ownsConnection($user, $connection_id)) {
            throw new CustomAuthException("Not authorized", 403);
        }

        $outputParams = [
            'per-page' => \Yii::$app->request->get('per-page', 20),
            'page' => \Yii::$app->request->get('page', 1),
            'part' => $part,
            'len' => $len
        ];

        if ($part == 'attach') {
            \Yii::$app->response->headers->add('Last-Modified', gmdate('D, d M Y H:i:s', time()) . ' GMT');
            \Yii::$app->response->headers->add('Cache-Control', 'public, max-age=315360000');
            if ($filename == 'zip') {
                $attach = MService::start($this->getUser())->outputAllAttachZipped($connection_id, $mbox, $id);
                return Yii::$app->response->sendContentAsFile($attach, time() . '.zip');
            } else {
                $attach = MService::start($this->getUser())->outputAttach($connection_id, $mbox, $id, $filename);
            }

            if ($download) {
                if ($attach) {
                    $parts = explode('.', $attach->getFilename());
                    $ext = end($parts);

                    if ($ext == 'eml' && $emlPreview) {
                        $tmpPath = realpath(__DIR__ . '/../web/uploads/') . '/tmp';
                        if (!file_exists($tmpPath)) {
                            mkdir($tmpPath);
                        }

                        $eml = new \api\models\eml\Message;
                        $tmpFile = $tmpPath . '/eml_' . \Yii::$app->user->id . '_' . $filename;
                        file_put_contents($tmpFile, $attach->getDecodedContent());

                        $eml->fromFile($tmpFile);
                        return $eml;
                    }


                    if ($preview) {
                        if (strtolower($ext) == 'pdf') {
                            return Yii::$app->response->sendContentAsFile($this->getPdfPreview($attach), $attach->getFilename() . '.png');
                        }
                    } else {
                        return Yii::$app->response->sendContentAsFile($attach->getDecodedContent(), $attach->getFilename());
                    }
                } else
                    return Yii::$app->response->sendContentAsFile('', $filename);
            } else {

                if ($attach) {
                    \Yii::$app->response->format = yii\web\Response::FORMAT_RAW;
                    \Yii::$app->response->headers->add('content-type', $attach->getType() . '/' . $attach->getSubtype());
                    \Yii::$app->response->content = $attach->getDecodedContent();
                } else {
                    \Yii::$app->response->content = '';
                }

                return \Yii::$app->response;
            }
        }
        if ($getNeighbours !== false) {
            $data = MService::start($this->getUser())->setOutputParams($outputParams)->getNeighbours($connection_id, $mbox, $id);
            if (isset($data['prev'])) {
                $data['prev'] = $this->addMessageSenderAvatar($data['prev']);
            }
            if (isset($data['next'])) {
                $data['next'] = $this->addMessageSenderAvatar($data['next']);
            }

            return $data;
        }

        $showReceiptDialog = false;
        if ($mbox === 'INBOX') {
            //get message as unseen to process receipt headers
            $rawMessage = MService::start($this->getUser())->setOutputParams($outputParams)->getRawMessage($connection_id, $mbox, $id);
            $stream = MService::start($this->getUser())->getStream(null, $mbox);
            $showReceiptDialog = \api\components\MailService\ReceiptResponder::processMessage($rawMessage, $stream);
        }


        $message = MClient::start($this->getUser())->getMessage($mbox, $id, $foreignImages); //->getMessagesHeadersList('INBOX','0', '20');//->getLoginUserImap($user)->Message('INBOX', 43);//->getMessagesHeadersList();////MessageList('INBOX', 0,  20);
        //$message = MService::start($this->getUser())->setOutputParams($outputParams)->getSingleMessage($connection_id, $mbox, $id, false, $foreignImages);
        // $message = $this->fixEmptyAttachment($message);
        $message = $this->addMessageSenderAvatar($message);

       /* if ($message['date']) {
            $message['date']['time_zone'] = new \DateTimeZone(Yii::$app->user->identity->timezone ?: date_default_timezone_get());
        } */
	
	if(isset($message['fromAddress'])){
        $from = $message['fromAddress'];
        $senderIsConfirmed = \Yii::$app->user->identity->checkSenderIsConfirmed($from);
        if ($from && $foreignImages && !$senderIsConfirmed) {
            $confSender = new \api\models\ConfirmedSender;
            $confSender->user_id = \Yii::$app->user->id;
            $confSender->email = $from;
            $confSender->save();
        }
        }
        if ($message['attachmentsCount'] == $message['countForigenImages']) {
            $message['attachmentsData'] = false;
        }


        $message['showForeignImages'] = (bool) $foreignImages || $senderIsConfirmed;
        $message['showReceiptDialog'] = $showReceiptDialog;

        return $message;
    }

    private function getPdfPreview($attach) {
        $web = realpath(__DIR__ . '/../web/');
        $uid = Yii::$app->user->id;
        $previewPath = $web . '/uploads/attach-preview/' . $uid . '/' . $attach->getFilename() . 'x150.png';
        $previewFolder = $web . '/uploads/attach-preview/' . $uid;
        if (!file_exists($previewPath)) {
            if (!file_exists($previewFolder)) {
                mkdir($previewFolder);
            }

            //download file and create preview
            if (!file_exists($previewFolder . '/tmp')) {
                mkdir($previewFolder . '/tmp');
            }

            $tmpFilePath = $previewFolder . '/tmp/' . $attach->getFilename();
            file_put_contents($tmpFilePath, $attach->getDecodedContent());
            \tpmanc\imagick\Imagick::open($tmpFilePath . '[0]')->thumb(150, 150)->saveTo($previewPath);
            unlink($tmpFilePath);
        }

        return file_get_contents($previewPath);
    }

    /**
     * @return array
     * @throws CustomAuthException
     */
    public function actionCreate() {
//        $user = $this->getUser();
//        
//        $a = MClient::start($user)->DoSendMessage();
//        
//        
//        var_dump($a);
//        die;
        $cmd = Yii::$app->request->post('cmd', false); // logic

        $mbox = trim(urldecode(Yii::$app->request->post('mbox', \Yii::$app->params['draftsFolder']))); //folder where to create a draft
        $mboxfrom = trim(urldecode(Yii::$app->request->post('mboxfrom', \Yii::$app->params['draftsFolder']))); //folder where to create a draft

        $connection_id = Yii::$app->request->post('connection_id', false); //mail account of the original message
        $mboxfrom = trim(urldecode(Yii::$app->request->post('mboxfrom', 'INBOX'))); //mail box of the original message
        $id = Yii::$app->request->post('id', false); //mail number of the original message

        $user = $this->getUser();

        if ($cmd == 'reply' && $connection_id !== false && $id !== false) {

            if (!$this->ownsConnection($user, $connection_id)) {
                throw new CustomAuthException("Not authorized", 403);
            }

            $part = 'headnhtml';
            $len = false;
            $outputParams = [
                'per-page' => 20,
                'page' => 1,
                'part' => $part,
                'len' => $len
            ];

            $newMessageNum = $this->getImapMessageAll($mboxfrom, $mbox, $id, $connection_id, true);

//            var_dump($newMessageNum); die;

            $flag = FlagsMessages::FLAG_CREATED_TO_REPLY;
            $drafts_connection_id = $connection_id;
            $this->createFlagMessage($connection_id, $mboxfrom, $id, $drafts_connection_id, $mbox, $newMessageNum, $flag);

            return ['success' => 'true', 'id' => $newMessageNum, 'mbox' => $mbox];
//            $imapMessage = $this->getImapMessage($mboxfrom, $id, $connection_id);
//            $swiftMessage = $this->createSwiftMessageFromImap($imapMessage);
//            $newMessageNum = $this->cmdUpdate($mbox, $swiftMessage);
//            $flag = FlagsMessages::FLAG_CREATED_TO_REPLY;
//            $drafts_connection_id = $user->default_connection_id;
//            $this->createFlagMessage($connection_id, $mboxfrom, $id, $drafts_connection_id, $mbox, $newMessageNum, $flag);
//            return ['success' => 'true', 'id' => $newMessageNum, 'mbox' => $mbox];
        } else if ($cmd == 'forward' && $connection_id !== false && $id !== false) {
            if (!$this->ownsConnection($user, $connection_id)) {
                throw new CustomAuthException("Not authorized", 403);
            }

//            $imapMessage = $this->getImapMessage($mboxfrom, $id, $connection_id);
//            $swiftMessage = $this->createSwiftMessageFromImap($imapMessage);
//            $swiftMessage = $this->addAttachemntsFromImap($swiftMessage, $imapMessage, 'all');
//            $newMessageNum = $this->cmdUpdate($mbox, $swiftMessage);
//            $result = [
//                MService::start($user)->getId($id, $connection_id, $mbox),
//                MService::start($user)->getUid($id, $connection_id, $mbox),
//            ];
//
//            return $result;

            $part = 'headnhtml';
            $len = false;
            $outputParams = [
                'per-page' => 20,
                'page' => 1,
                'part' => $part,
                'len' => $len
            ];

            $newMessageNum = $this->getImapMessageAll($mboxfrom, $mbox, $id, $connection_id);

            $flag = FlagsMessages::FLAG_CREATED_TO_FORWARD;
            $drafts_connection_id = $connection_id;
            $this->createFlagMessage($connection_id, $mboxfrom, $id, $drafts_connection_id, $mbox, $newMessageNum, $flag);

            return ['success' => 'true', 'id' => $newMessageNum, 'mbox' => $mbox];
        } else {
            $swiftMessage = $this->createSwiftMessageFromPost();

            if ($cmd == 'send') {

                if ($this->cmdSend($swiftMessage)) {

                    if ($mbox != \Yii::$app->params['templatesFolder']) {
                        return true;
                    }
                }

                if ($mbox != \Yii::$app->params['templatesFolder']) {
                    return ['success' => 'false', 'message' => 'Error sending message'];
                }
            }

            $mboxNew = $mbox;
            $scheduledSendDate = Yii::$app->request->post('sdate', false);
            if ($scheduledSendDate !== false && $mbox != \Yii::$app->params['outboxFolder']) {
                $mboxNew = \Yii::$app->params['outboxFolder'];
            } elseif ($scheduledSendDate === false && $mbox == \Yii::$app->params['outboxFolder']) {
                $mboxNew = \Yii::$app->params['draftsFolder'];
            }

            $newMessageNum = $this->cmdUpdate($mboxNew, $swiftMessage);

            if ($scheduledSendDate != false) {
                $this->createScheduled($mboxNew, $newMessageNum, $scheduledSendDate);
            }

            return ['success' => 'true', 'id' => $newMessageNum, 'mbox' => $mboxNew];
        }
    }

    /**
     * @param $id
     * @return array
     * @throws Exception
     */
    public function actionUpdate($id) {
        $to = Yii::$app->request->getBodyParam('to', []);
        $toCopy = Yii::$app->request->getBodyParam('toCopy', []);
        $toCopyHidden = Yii::$app->request->getBodyParam('toCopyHidden', []);

        $emails = ArrayHelper::merge($to, $toCopy);
        $emails = ArrayHelper::merge($emails, $toCopyHidden);
        Contact::addEmail($emails);

        $user = $this->getUser();
        $mbox = trim(urldecode(Yii::$app->request->getBodyParam('mbox', false)));
        $cmd = Yii::$app->request->getBodyParam('cmd', false);
        $attaches = Yii::$app->request->getBodyParam('attaches', false);
        $swiftMessage = $this->createSwiftMessageFromPost();
        $imapMessage = $this->getImapMessage($mbox, $id);

        if (!$imapMessage) {
            throw new Exception('No such message');
        }

        $swiftMessage = $this->addAttachemntsFromImap($swiftMessage, $imapMessage, $attaches);
        $this->deleteScheduled($mbox, $id);

        if ($cmd == 'send') {
            if ($this->cmdSend($swiftMessage) && $mbox != \Yii::$app->params['templatesFolder']) {
                MService::start($user)->deleteMessage($user->default_connection_id, $mbox, $id);
                MService::start($user)->expunge($user->default_connection_id, $mbox);

                $sysFlag = FlagsMessages::find()
                                ->where([
                                    'draft_connection_id' => $user->default_connection_id,
                                    'draft_mbox' => $mbox,
                                    'draft_message_num' => $id,
                                ])->one();

                if ($sysFlag) {
                    if ($sysFlag->flag_id == FlagsMessages::FLAG_CREATED_TO_FORWARD) {
                        $sysFlag->flag_id = FlagsMessages::FLAG_FORWARDED;
                        $sysFlag->save();
                    } else if ($sysFlag->flag_id == FlagsMessages::FLAG_CREATED_TO_REPLY) {
                        $sysFlag->flag_id = FlagsMessages::FLAG_REPLIED;
                        $sysFlag->save();
                        MService::start($user)->setFlag($sysFlag->original_connection_id, $sysFlag->original_mbox, $sysFlag->original_message_num, 'Answered');
                    }

                    $cache = \Yii::$app->cache;
                    $cacheHash = md5($sysFlag->original_connection_id . $sysFlag->original_mbox . $sysFlag->original_message_num);
                    $cache->delete($cacheHash);
                }

                return true;
            }
            if ($mbox != \Yii::$app->params['templatesFolder']) {
                return ['success' => 'false', 'message' => 'Error sending message'];
            }
        }

        $mboxNew = $mbox;
        $scheduledSendDate = Yii::$app->request->post('sdate', false);
        if ($scheduledSendDate !== false && $mbox != \Yii::$app->params['outboxFolder']) {
            $mboxNew = \Yii::$app->params['outboxFolder'];
        } elseif ($scheduledSendDate === false && $mbox == \Yii::$app->params['outboxFolder']) {
            $mboxNew = \Yii::$app->params['draftsFolder'];
        }

        $newMessageNum = $this->cmdUpdate($mboxNew, $swiftMessage, $id);

        if ($scheduledSendDate != false) {
            $this->createScheduled($mboxNew, $newMessageNum, $scheduledSendDate);
        }

        $addedTags = TaggedMessage::find()
                ->joinWith('tag', true)
                ->where([
                    TaggedMessage::tableName() . '.connection_id' => $user->default_connection_id,
                    TaggedMessage::tableName() . '.mbox' => $mbox,
                    TaggedMessage::tableName() . '.message_num' => $id])
                ->all();

        foreach ($addedTags as $item) {
            $item->message_num = $newMessageNum;
            $item->mbox = $mboxNew;
            $item->save();
        }

        $sysFlag = FlagsMessages::find()
                        ->where([
                            'draft_connection_id' => $user->default_connection_id,
                            'draft_mbox' => $mbox,
                            'draft_message_num' => $id,
                        ])->one();

        if ($sysFlag) {
            $sysFlag->draft_mbox = $mboxNew;
            $sysFlag->draft_message_num = $newMessageNum;
            $sysFlag->save();
        }

        MService::start($user)->deleteMessage($user->default_connection_id, $mbox, $id);
        MService::start($user)->expunge($user->default_connection_id, $mbox);

        return ['success' => 'true', 'id' => $newMessageNum, 'mbox' => $mboxNew];
    }

    /**
     * @param $id
     * @return array|mixed
     */
    public function actionDelete($id) {
        $messages = Yii::$app->request->post('messages', false);

        if (!$messages || !is_array($messages)) {
            return ['success' => 'false', 'message' => 'no input data'];
        }
        $user = $this->getUser();

        foreach ($messages as $k => $message) {

            if (!$this->ownsConnection($user, $message['connection_id'])) {
                continue;
            }

            $result = [];
            try {
                $result[] = Mservice::start($user)->setFlag($message['connection_id'], $message['mbox'], $message['number'], 'Deleted');
                $result[] = Mservice::start($user)->move($message['connection_id'], $message['mbox'], $message['number'], 'Trash');
                $sysFlag = FlagsMessages::find()
                                ->where([
                                    'or', [
                                        'draft_connection_id' => $message['connection_id'],
                                        'draft_mbox' => $message['mbox'],
                                        'draft_message_num' => $message['number']],
                                    [
                                        'original_connection_id' => $message['connection_id'],
                                        'original_mbox' => $message['mbox'],
                                        'original_message_num' => $message['number']],
                                ])->one();

                if ($sysFlag) {
                    $sysFlag->delete();
                }
            } catch (Exception $ex) {
                
            }

            $messages[$k]['result'] = implode("; ", $result);
        }

        return $messages;
    }

    /**
     * Send confirmation about reading email message
     */
    public function actionConfirmReading() {
        $mbox = 'INBOX';
        $connection_id = Yii::$app->request->post('connection_id', false);
        $id = Yii::$app->request->post('number', false);
        $allow = Yii::$app->request->post('allow', false);
        $remember = Yii::$app->request->post('remember', false);

        $message = MService::start($this->getUser())->getRawMessage($connection_id, $mbox, $id);
        if ($allow) {
            \api\components\MailService\ReceiptResponder::sendResponse($message, MService::start($this->getUser())->getStream());
        }

        if ($remember) {
            $autoResponse = \api\models\ReceiptAutoResponse::findOne(['user_id' => \Yii::$app->user->id, \api\components\MailService\ReceiptResponder::getReceiptResponseTo()]);
            if ($autoResponse) {
                if ((bool) $autoResponse->allow != (bool) $allow) {
                    $autoResponse->allow = (bool) $allow;
                    $autoResponse->save();
                }
            } else {
                $autoResponse = new \api\models\ReceiptAutoResponse();
                $autoResponse->user_id = \Yii::$app->user->id;
                $autoResponse->from = $message->getFrom()->getAddress();
                $autoResponse->allow = (bool) $allow;
                $autoResponse->save();
            }
        }

        return true;
    }

    /**
     * 
     *  private methods
     * 
     *  TODO: transfer most of these to MailService lib
     * 
     */

    /**
     * @param $connection_id
     * @param $mboxfrom
     * @param $id
     * @param $drafts_connection_id
     * @param $mbox
     * @param $newMessageNum
     * @param $flag
     */
    private function createFlagMessage($connection_id, $mboxfrom, $id, $drafts_connection_id, $mbox, $newMessageNum, $flag) {
        $sysFlag = new FlagsMessages();
        $sysFlag->original_connection_id = $connection_id;
        $sysFlag->original_mbox = $mboxfrom;
        $sysFlag->original_message_num = $id;
        $sysFlag->draft_connection_id = $drafts_connection_id;
        $sysFlag->draft_mbox = $mbox;
        $sysFlag->draft_message_num = $newMessageNum;
        $sysFlag->flag_id = $flag;

        try {
            $sysFlag->validate();
            $sysFlag->save();
        } catch (Exception $ex) {
            // ignore errors
        }
    }

    /**
     * @param $user
     * @param $connection_id
     * @return bool
     */
    private function ownsConnection($user, $connection_id) {
        $allConnections = [];
        foreach ($user->connections as $connection) {
            $allConnections[] = $connection->id;
        }

        if (!in_array($connection_id, $allConnections)) {
            return false;
        }

        return true;
    }

    /**
     * @param $user
     * @param $connection_id
     * @return bool
     */
    private function getMailAddressByConnection($user, $connection_id) {
        $allConnections = [];
        foreach ($user->connections as $connection) {
            if ($connection->id == $connection_id) {
                return $connection->email;
            }
        }

        return false;
    }

    /**
     * @param $mbox
     * @param $num
     */
    private function deleteScheduled($mbox, $num) {
        $sc = ScheduledSend::findOne([
                    'connection_id' => \Yii::$app->user->identity->getId(),
                    'mbox' => $mbox,
                    'number' => $num,
        ]);

        if ($sc) {
            $sc->delete();
        }
    }

    /**
     * @param $mbox
     * @param $num
     * @param $date
     * @return bool
     */
    private function createScheduled($mbox, $num, $date) {
        $user = $this->getUser();
        $model = new ScheduledSend();
        $model->connection_id = $user->default_connection_id;
        $model->mbox = $mbox;
        $model->number = $num;
        $model->message_id = (string) $num;
        $model->send_date = $date;
        $model->created_at = time();
        $model->updated_at = time();

        return ($model->save());
    }

    /**
     * @param $mbox
     * @param $swiftMessage
     * @return bool
     * @throws CustomAuthException
     */
    private function cmdUpdate($mbox, $swiftMessage, $oldId = null) {
        $uniqueIdentifier = $this->addIdentifierToSwiftMessage($swiftMessage);

        //create new message
        if (!$this->addSwiftMessageToImap($mbox, $swiftMessage)) {
            throw new CustomAuthException(401, "error updating message");
        }

        $user = $this->getUser();

        //and remove old one
        if ($oldId) {
            MService::start($user)->deleteMessage($user->default_connection_id, $mbox, $oldId);
        }

        return MService::start($user)->getMessageByHeader($user->default_connection_id, $mbox, $uniqueIdentifier);
    }

    /**
     * @param $swiftMessage
     * @return bool
     * @throws CustomAuthException
     */
    private function cmdSend($swiftMessage) {
        if (!$this->sendMessage($swiftMessage)) {
            throw new CustomAuthException(400, "can't send message");
        }

        return $this->addSwiftMessageToImap(\Yii::$app->params['sentFolder'], $swiftMessage);
    }

//    private function addAttach($mbox, $id)
//    {
//        
//        if (!empty($_FILES) && isset($_FILES['file']['tmp_name'])) {
//        
//            $attach = file_get_contents($_FILES['file']['tmp_name']);
//        
//            $imapMessage = $this->getImapMessage($mbox, $id);
//        
//            $swiftMessage = $this->createSwiftMessageFromImap($imapMessage);
//        
//            $swiftMessage = $this->addAttachemntsFromImap($swiftMessage, $imapMessage);
//        
//            $swiftMessage->attachContent($attach, ['fileName' => $_FILES['file']['name'], 'contentType' => $_FILES['file']['type']]);
//            
//            $uniqueIdentifier = $this->addIdentifierToSwiftMessage($swiftMessage);
//            
//            if (!$this->addSwiftMessageToImap($mbox, $swiftMessage)) {
//                throw new CustomAuthException("cant't save attach");
//            }
//            
//            $imapMessage->delete();
//            MService::start($user)->expunge($user->default_connection_id, $mbox);
//            
//            return $this->getMessageNumberByUniqueId($mbox, $uniqueIdentifier);
//        
//        } else {
//            throw new CustomAuthException("no attach given");
//        }
//    }

    /**
     * @param $mbox
     * @param $swiftMessage
     * @return bool
     */
    private function addSwiftMessageToImap($mbox, $swiftMessage) {
        $user = $this->getUser();

        return MService::start($user)->addMessage($user->default_connection_id, $mbox, $swiftMessage->toString());
    }

    /**
     * @param $swiftMessage
     * @return string
     */
    private function addIdentifierToSwiftMessage($swiftMessage) {

        $uniqueIdentifier = md5($this->getUser()->email . time() . rand(0, time()));

        $swiftMessage->setHeader(Yii::$app->params['imap_unique_header'], $uniqueIdentifier);

        return $uniqueIdentifier;
    }

    /**
     * @param $swiftMessage
     * @return bool
     */
    private function sendMessage($swiftMessage) {
        $streamOptions = [];
        $streamOptions['ssl'] = [
            'allow_self_signed' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ];

        /* @var $connection \api\models\Connection */
        $connection = $this->getUser()->defaultConnection ?: $this->getUser()->selectedConnection;
        $transport = new \Swift_SmtpTransport(\Yii::$app->params['smtp_host'], \Yii::$app->params['smtp_port'], 'tls');
        $transport
                ->setStreamOptions($streamOptions)
                ->setUsername($connection->getImapEmail())
                ->setPassword($connection->getImapPassword());

        $mailer = new \yii\swiftmailer\Mailer();
        $mailer->setTransport($transport);

        if (!$mailer->send($swiftMessage)) {
            return false;
        }

        return true;
    }

    /**
     * @param $mbox
     * @param $id
     * @param bool $connection_id
     * @return bool|\Ddeboer\Imap\Message
     */
    private function getImapMessage($mbox, $id, $connection_id = false) {
        $user = $this->getUser();

        if (!$connection_id) {
            $connection_id = $user->default_connection_id;
        }

        return MService::start($user)->getRawMessage($connection_id, $mbox, $id);
    }

    /**
     * @param $mbox
     * @param $id
     * @param bool $connection_id
     * @return bool|\Ddeboer\Imap\Message
     */
    private function getImapMessageAll($mbox, $mboxto, $id, $connection_id = false, $reply = false) {
        $user = $this->getUser();

        if (!$connection_id) {
            $connection_id = $user->default_connection_id;
        }

        $outputParams = [
            'per-page' => 20,
            'page' => 1,
            'part' => 'headnhtml',
            'len' => false,
        ];
        $message = MService::start($this->getUser())->setOutputParams($outputParams)->getSingleMessage($connection_id, $mbox, $id);
//        yii\helpers\VarDumper::dump($message);
//        die();
        $rawMessage = MService::start($user)->getRawMessage($connection_id, $mbox, $id);

        /** @var yii\swiftmailer\Message $swift */
        $swift = $this->createSwiftMessageFromImap($rawMessage, $message['body'], $reply);
        $swift = $this->addAttachemntsFromImap($swift, $rawMessage, 'all');
//            ->setHtmlBody($message['body'])
//            ->setTextBody($message['body']);
//        $swift->getSwiftMessage()->setBody($message['body']);


        $newMessageNum = $this->cmdUpdate($mboxto, $swift);
        //var_dump($swift); die;

        return $newMessageNum;

        $rawMessage = MService::start($user)->getRawMessage($connection_id, $mbox, $newMessageNum);

        return $rawMessage;
        return MService::start($user)->getRawMessage($connection_id, $mbox, $id);
    }

    /**
     * @param Message $imapMessage
     * @param bool $body
     * @param bool $reply
     * @return yii\swiftmailer\Message
     */
    private function createSwiftMessageFromImap($imapMessage, $body = false, $reply = false) {
        $swiftMessage = new yii\swiftmailer\Message();

        //$from = $this->getUser()->email;
        $from = $imapMessage->getFrom()->getAddress();
        $fromName = $imapMessage->getFrom()->getAddress();
        if (empty($fromName)) {
            $fromFull = $from;
        } else {
            $fromFull = [$from => $fromName];
        }
        $to = [];
        foreach ($imapMessage->getTo() as $tt) {
            $to = $tt->getAddress();
        }
        $cc = [];
        foreach ($imapMessage->getCc() as $tt) {
            $cc = $tt->getAddress();
        }
        $mBcc = [];
        $bcc = $imapMessage->getHeaders()->get('bcc');
        if ($bcc) {
            foreach ($bcc as $cc) {
                $mBcc[] = [
                    'fullAddress' => $bcc->getFullAddress(),
                ];
            }
        }

//         $html = $imapMessage->getBodyHtml();
//         $text = $imapMessage->getBodyText();
//
//         if (empty($html) && !empty($text)) {
//             $html = str_replace('\\r\\n', '<br>', $text);
//         }

        if ($body == false) {
            $body = $imapMessage->getBodyHtml();
        }

        $swiftMessage->setFrom($fromFull);
        if ($reply == true) {
            $swiftMessage->setTo($to)
                    ->setCc($cc)
                    ->setBcc($mBcc);
        }
        $swiftMessage->setCharset('utf-8')
                ->setSubject($imapMessage->getSubject())
                ->setTextBody($imapMessage->getBodyText())
                ->setHtmlBody($body);

        return $swiftMessage;
    }

    /**
     * @return yii\swiftmailer\Message
     * @throws CustomAuthException
     */
    private function createSwiftMessageFromPost() {
        $user = $this->getUser();
        $fromName = $this->getUser()->user_name;
        $from_connection = Yii::$app->request->getBodyParam('from_connection', false);

        if ($from_connection !== false && !$this->ownsConnection($user, $from_connection)) {
            throw new CustomAuthException("Not authorized", 403);
        }

        $from = $this->getMailAddressByConnection($user, $from_connection);

        if (empty($fromName)) {
            $fromFull = $from;
        } else {
            $fromFull = [$from => $fromName];
        }

        if (!$from) {
            $from = $user->email;
        }

        $to = Yii::$app->request->getBodyParam('to');
        $cc = Yii::$app->request->getBodyParam('toCopy');
        $bcc = Yii::$app->request->getBodyParam('toCopyHidden');
        $subject = Yii::$app->request->getBodyParam('subject');
        $body = Yii::$app->request->getBodyParam('body', "\r\n");

        $confirmReading = \Yii::$app->request->getBodyParam('confirmReading');

        $swiftMessage = new yii\swiftmailer\Message();
        $swiftMessage->setFrom($fromFull)
                ->setTo($to)
                ->setCc($cc)
                ->setBcc($bcc)
                ->setCharset('utf-8')
                ->setSubject($subject)
                ->setHtmlBody($body);

        if ($confirmReading) {
            $swiftMessage->setHeader('X-Confirm-Reading-To', "<{$from}>");
            $swiftMessage->setHeader('Disposition-Notification-To', "<{$from}>");
        }

        return $swiftMessage;
    }

    /**
     * @param $swiftMessage
     * @param $imapMessage
     * @param bool $attaches
     * @return mixed
     */
    private function addAttachemntsFromImap($swiftMessage, $imapMessage, $attaches = false) {

        if ($imapMessage->hasAttachments()) {
            if (!is_array($attaches)) {
                $attaches = [$attaches];
            }

            $attaches = array_map('urldecode', $attaches);

            $oldAttaches = $imapMessage->getAttachments();
            $body = $swiftMessage->getSwiftMessage()->getBody();
            if (empty($body)) {
                $body = ' ';
            }
            foreach ($oldAttaches as $attachment) {
                if (in_array($attachment->getFilename(), $attaches) || ( isset($attaches[0]) && $attaches[0] == 'all')) {
                    $swiftMessage->attachContent(
                            $attachment->getDecodedContent(), ['fileName' => $attachment->getFilename(), 'contentType' => $attachment->getType() . "/" . strtolower($attachment->getSubtype())]);
                }
            }
            $swiftMessage->getSwiftMessage()->setBody($body);
        } 

        return $swiftMessage;
    }

    /**
     * @return null|yii\web\IdentityInterface|User
     */
    private function getUser() {
        //return new User();
        return Yii::$app->user->identity;
    }

}
