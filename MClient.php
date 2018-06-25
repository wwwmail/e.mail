<?php
 
namespace api\components\MailClient;

use api\models\TaggedMessage;
use api\components\MailService\EmailMessage;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use yii;
use DateTime;
use DateTimeZone;

class MClient {

    private static $_instance = null;
    private $_connectionsParams = [];
    private $_streams = [];
    private $_outputParams = null;
    private $_defaultConnectionId = false;
    ////////////////////////////////////
    private $_connections = false;
    private $_userData = [];
    private $oMailClient;
    private $oConfig;
    private $_connectionId;
    private $_connectionsData = [];

    public function __construct($user) 
    {
        $this->oMailClient = null;
        if (!is_array($user->connections) || empty($user->default_connection_id)) {
            throw new \Exception('no connections given');
        }

        $this->MailClient()->Connect($user->connections[0]->server, (int) $user->connections[0]->port)
                ->Login($user->connections[0]->email, $user->connections[0]->password);

        $this->_connectionsData[$user->connections[0]->id] = new \stdClass();
        $this->_connectionsData[$user->connections[0]->id]->server = $user->connections[0]->server;
        $this->_connectionsData[$user->connections[0]->id]->serverName = "{" . $user->connections[0]->server . ":" . $user->connections[0]->port . "/imap/novalidate-cert}INBOX";
        $this->_connectionsData[$user->connections[0]->id]->port = $user->connections[0]->port;
        $this->_connectionsData[$user->connections[0]->id]->email = $user->connections[0]->email;
        $this->_connectionsData[$user->connections[0]->id]->password = $user->connections[0]->password;


        $this->_connectionId = $user->connections[0]->id;
    }

    public static function start($user) 
    {
        if (null === self::$_instance) {
            self::$_instance = new self($user);
        }

        return self::$_instance;
    }

    private function explodeSubject($sSubject) 
    {
        $aResult = array('', '');
        if (0 < \strlen($sSubject)) {
            $bDrop = false;
            $aPrefix = array();
            $aSuffix = array();

            $aParts = \explode(':', $sSubject);
            foreach ($aParts as $sPart) {
                if (!$bDrop &&
                        (\preg_match('/^(RE|FWD)$/i', \trim($sPart)) || \preg_match('/^(RE|FWD)[\[\(][\d]+[\]\)]$/i', \trim($sPart)))) {
                    $aPrefix[] = $sPart;
                } else {
                    $aSuffix[] = $sPart;
                    $bDrop = true;
                }
            }

            if (0 < \count($aPrefix)) {
                $aResult[0] = \rtrim(\trim(\implode(':', $aPrefix)), ':') . ': ';
            }

            if (0 < \count($aSuffix)) {
                $aResult[1] = \trim(\implode(':', $aSuffix));
            }

            if (0 === \strlen($aResult[1])) {
                $aResult = array('', $sSubject);
            }
        }

        return $aResult;
    }

    public function MailClient() 
    {
        if (null === $this->oMailClient) {
            $this->oMailClient = \MailSo\Mail\MailClient::NewInstance();
        }

        return $this->oMailClient;
    }

    public function getMessagesHeadersList($folder = 'INBOX', $ofsset = 0, $data=array(), $search='', $filter='', $searchPart='', $searchStart='', $searchEnd='') 
    {
        $additional_string = '';
        switch ($filter) {
            case 'attach':
                $searchString = 'has:file';
                $additional_string = "&filter={$filter}";
                break;
            case 'unseen':
                $searchString = 'is:unseen';
                $additional_string = "&filter={$filter}";
                break;
            case 'flagged':
                $searchString = 'is:flagged';
                $additional_string = "&filter={$filter}";
                break;
             default:
                 $searchString = '';
        }

        
        
        if(!empty($search)){
            
            $searchString .= ' '. $search;
        }
        
        if(!empty($searchPart) && $searchPart !='text'){
            
           $searchString = $searchPart.':"'.$search.'"';
            
            
        }
//        else{
//            $searchString = $search;
//        }
        
        
        if(!empty($searchStart) && !empty($searchEnd)){
            
            $searchStart = gmdate("Y.m.d", $searchStart);
            
            $searchEnd = gmdate("Y.m.d", $searchEnd);
            
           
             $searchString .= " since:{$searchStart}{before:}{$searchEnd}";
            
        }
        

        
        $limit = $data['per-page'];
        
//        
//        
//        $ofsset = 0;
        if($data['page'] != 1){
            $limit = 20;
            $ofsset = $limit * ($data['page'] - 1);
        }
        if(empty($folder)){
        $folder = $data['smbox'];
        
        if(!$folder){
	  $folder = 'INBOX';
        }
            
        }
        
//        var_dump($folder);
//        var_dump($ofsset);
//        var_dump($searchString); die;
        $dataMessages = $this->MailClient()->MessageList($folder, (int) $ofsset, (int) $limit, $searchString);
        
        $messages['items'] = $this->responseObject($dataMessages);
        
        
        //$additional_string = '';
        
        $next = $data['page']+1;
        $messages['_links']['next'] = array('href' =>   Yii::$app->params['api_url'].'/mail?len=200&mbox='.$folder.$additional_string.'&part=bodytext&page='.$next);
        $messages['__meta'] = array('currentPage' => $data['page'], 'pageCount' => 10, 'perPage'=> 20, 'totalCount'=>200);
        
     //   $count = count($messages["@Collection"]) + 1;
        
        
       // array_push($messages["@Collection"], array("@Object"=>"apple"),  array("@Object"=>"apple"), array("@Object"=>"apple"));
//        echo $count; die;
//        $messages["@Collection"];
        
        //var_dump($messages); die;
        return $messages;
       // return $this->responseObject($dataMessages);
    }
    
    
    public function getMessagesHeadersListByTag($tag_id, $data)
    {
        $limit = 20;
        if($data['page'] != 1){
            
            $limit = 20;
            $offset = $limit * ($data['page'] - 1);
        }else{
            $offset = 0;
        }
        
        $taggedMessages = $this->getMessagesByTag($tag_id, $offset);

        $messageArr  = array();   
        
       // var_dump($taggedMessages); die;
          
        if(!empty($taggedMessages)){
        foreach ($taggedMessages as $item){
           $messageArr[] =  $this->responseObject($this->MailClient()->MessageListSimple($item['mbox'], array($item['message_num']))[0]);
        }
        }
        
       // var_dump($messageArr); die;
        
        $messages['items'] = $messageArr;
        $next = $data['page']+1;
        if(count($messageArr) > 0 ){
            $messages['_links']['next'] = array('href' =>   Yii::$app->params['api_url'].'/mail?len=200&part=bodytext&page='.$next.'&tag_id='.$tag_id);
        }
        else{
            $messages['_links']['self'] = array();
        }
       // $messages['_links']['next'] = array('href' =>   Yii::$app->params['api_url'].'/mail?len=200&part=bodytext&page='.$next.'&tag_id='.$tag_id);
        $messages['__meta'] = array('currentPage' => $data['page'], 'pageCount' => 10, 'perPage'=> 20, 'totalCount'=>200);
        
        return $messages;

        
        
        
    }
    
    
    private function getMessagesByTag($tag_id, $offset=0) 
    {
        return TaggedMessage::find()
                ->where(['tag_id' => (int)$tag_id])
                ->offset($offset)
                ->limit(20)
                ->asArray()
                ->orderBy('connection_id, mbox')
                ->all();
    }

    

    public function getMessagesHeadersListByFilter( $search = 'has:attachment', $folder = 'INBOX', $ofsset = 0, $limit = 20) 
    {
        
        if($search == 'attach'){
            $filter = 'has:file';
        }elseif ($search == 'unseen') {
            
            $filter = 'is:unseen';
        }elseif ($search == 'flagged') {
            $filter = 'is:flagged';
        }
        
        
        
       // echo $filter; die;
        $dataMessages = $this->MailClient()->MessageList($folder, (int) $ofsset, (int) $limit, $filter);

        return $this->responseObject($dataMessages);
    }

    public function getMessage($folder = 'INBOX', $index = 43, $forigenImages = null, $indexIsUid = true, $cacher = null, $bodyTextLimit = null) 
    {
        $this->MailClient()->MessageSetSeen($folder, (array) $index, (int) $index, true);

        $dataMessage = $this->MailClient()->Message($folder, (int) $index);
        
        return $message = $this->responseObject($dataMessage, 'Message', array(), $forigenImages);
    }

    private function is_html($string) 
    {
        return preg_match("/<[^<]+>/", $string, $m) != 0;
    }

    private function formatGetHeadNBodyHtml($html, $text, $uid = null, $from = null, $resource = null, $foreignImages = false) 
    {
        
        $countMathes = 0;
        if (empty($html) && !empty($text)) {
               
                
                $html = nl2br($text);

                $r = '#(")?(?:https?://)?[a-zA-Z0-9\-\.^@]+[\.]{1}[a-zA-Z]{2,5}((\?|/)[\w\d/\?=\-.]*)?/?#';
                $html = preg_replace_callback($r, function($matches) {
                    
                    if (strpos($matches[0], '"') === 0) {
                        return $matches[0];
                    } else {
                        return "<a href=\"{$matches[0]}\" target=\"_blank\">{$matches[0]}</a>";
                    }
                }, $html);

        }

        $messageBody = preg_replace('/class=".*?"/', '', $html);

        preg_match_all('/src="cid:(.*)"/Uims', $messageBody, $matches);
        if (count($matches[1])) {
            $resource = imap_open($this->_connectionsData[$this->_connectionId]->serverName, $this->_connectionsData[$this->_connectionId]->email, $this->_connectionsData[$this->_connectionId]->password);

            if ($resource && $uid) {
                $emailMessage = new EmailMessage($resource, $uid);

                if ($emailMessage->fetch()) {
                    $search = [];
                    $replace = [];

                    foreach ($matches[1] as $match) {
                        ++$countMathes;
                        $uploadsFolder = 'uploads/attachments/';
                        try {
                            $apiUrl = \Yii::$app->params['api_url'];
                            $uploadsFolder .= md5($emailMessage->attachments[$match]['data']) . '.png';

                            if (!file_exists($uploadsFolder)) {
                                file_put_contents($uploadsFolder, $emailMessage->attachments[$match]['data']);
                            }

                            $search[] = "src=\"cid:$match\"";
                            $replace[] = "src=\"{$apiUrl}/" . $uploadsFolder . "\"";
                        } catch (\Exception $exception) {
                            throw new Exception('Can not write attachment to filesystem');
                        }
                    }
                    $messageBody = str_replace($search, $replace, $messageBody);
                }
            }
        }


        if (preg_match('/<style[^<]+<\/style>/', $messageBody)) {
            try {
                $cssToInlineStyles = new CssToInlineStyles();
                $messageBody = $cssToInlineStyles->convert($messageBody);

                $html = \Sunra\PhpSimple\HtmlDomParser::str_get_html($messageBody);

                foreach ($html->find('style') as $st) {
                    $st->innertext = '';
                }

                $messageBody = $html->save();
            } catch (\Exception $exception) {
                
            }
        }

        $hasForeignImages = false;
        $html = \Sunra\PhpSimple\HtmlDomParser::str_get_html($messageBody);

        if ($html) {
            $host = \Yii::$app->params['api_url'];
            foreach ($html->find('img') as $img) {
                $hasForeignImages = true;
                $img->setAttribute('src', "{$host}/uploads/no-image.png");
            }
            $hasForeignImages = $hasForeignImages || $this->filterForeignBgs($html->find('*'));

            $from = $from;
            $senderIsConfirmed = \Yii::$app->user->identity->checkSenderIsConfirmed($from);
            if (!$foreignImages && !$senderIsConfirmed) {
                $messageBody = $html->save();
            }
        }

        $info = [];
        $info['messageBody'] = $messageBody;
        $info['countMathesForigenImages'] = $countMathes;
        $info['hasForigenImages'] = $hasForeignImages;
        return $info;
    }

    private function filterForeignBgs($elements) 
    {
        $hasForeignBgs = false;
        foreach ($elements as $element) {
            $style = $element->getAttribute('style');
            if ($style) {
                $styleValues = $this->parseInlineCss($style);

                $results = '';
                $host = \Yii::$app->params['api_url'];
                foreach ($styleValues as $k => $v) {
                    if (in_array($k, ['background', 'background-image'])) {
                        $r = '#(?:https?://)?[a-zA-Z0-9\-\.]+[\.]{1}[a-zA-Z]{2,5}((\?|/)[\w\d/\?=\-.]*)?/?#';
                        $v = preg_replace_callback($r, function($matches) use ($hasForeignBgs) {
                            $hasForeignBgs = true;
                            return "{$host}/uploads/no-image-bg.png";
                        }, $v);
                    }

                    $results .= $k . ': ' . $v . ';';
                }

                $element->setAttribute('style', $results);
            }

            if ($element->children()) {
                $this->filterForeignBgs($element->children());
            }
        }

        return $hasForeignBgs;
    }

    protected function responseObject($mResponse, $sParent = 'Message', $aParameters = array(), $forigenImages = null) 
    {
        $mResult = $mResponse;
        if (\is_object($mResponse)) {

            $bHook = true;
            $self = $this;
            $sClassName = \get_class($mResponse);

            $bHasSimpleJsonFunc = \method_exists($mResponse, 'ToSimpleJSON');

            $bThumb = '';
            $oAccountCache = null;
            $fGetAccount = function () use ($self, &$oAccountCache) {
                if (null === $oAccountCache) {
                    $oAccount = '';

                    $oAccountCache = $oAccount;
                }

                return $oAccountCache;
            };

            $aCheckableFoldersCache = null;
            $fGetCheckableFolder = function () use ($self, &$aCheckableFoldersCache) {
                if (null === $aCheckableFoldersCache) {
                    $oAccount = $this->MailClient();

                    //$oSettingsLocal = $self->SettingsProvider(true)->Load($oAccount);
                    $sCheckable = array('CheckableFolder');//$oSettingsLocal->GetConf('CheckableFolder', '[]');
                    $aCheckable = @\json_decode($sCheckable);
                    if (!\is_array($aCheckable)) {
                        $aCheckable = array();
                    }

                    $aCheckableFoldersCache = $aCheckable;
                }

                return $aCheckableFoldersCache;
            };

            if ($bHasSimpleJsonFunc) {
                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), $mResponse->ToSimpleJSON(true));
            } else if ('MailSo\Mail\Message' === $sClassName) {

                $oAccount = \call_user_func($fGetAccount);

                $iDateTimeStampInUTC = $mResponse->HeaderTimeStampInUTC();

                if (0 === $iDateTimeStampInUTC) {
                    $iDateTimeStampInUTC = $mResponse->HeaderTimeStampInUTC();
                    if (0 === $iDateTimeStampInUTC) {
                        $iDateTimeStampInUTC = $mResponse->InternalTimeStampInUTC();
                    }
                }

                $addedTags = TaggedMessage::find()
                        ->joinWith('tag', true)
                        ->where([
                            TaggedMessage::tableName() . '.connection_id' => $this->_connectionId,
                            TaggedMessage::tableName() . '.mbox' => $mResponse->Folder(),
                            TaggedMessage::tableName() . '.message_num' => $mResponse->Uid()])
                        ->all();

                $tags = [];
                foreach ($addedTags as $item) {
                    $tags[] = $item->tag;
                }



                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'connection_id' => $this->_connectionId,
                    'mbox' => $mResponse->Folder(),
                    'date' => [
                       // 'date' => gmdate("Y-m-d H:i:s", $iDateTimeStampInUTC),
                       'date' => $mResponse->HeaderDate()
                    ],
                    'Subject' => \trim(\MailSo\Base\Utils::Utf8Clear($mResponse->Subject())),
                    'from' => $this->responseObject($mResponse->From(), $sParent, $aParameters)[0]['name'],
                    'fromAddress' => $this->responseObject($mResponse->From(), $sParent, $aParameters)[0]['address'],
                    'ReplyTo' => $this->responseObject($mResponse->ReplyTo(), $sParent, $aParameters),
                    'to' => $this->responseObject($mResponse->To(), $sParent, $aParameters),
                    'messageId' => $mResponse->MessageId(),
                    'number' => (int) $mResponse->Uid(),
                    'cc' => $this->responseObject($mResponse->Cc(), $sParent, $aParameters),
                    'bcc' => $this->responseObject($mResponse->Bcc(), $sParent, $aParameters),
                    'size' => $mResponse->Size(),
                    'Sender' => $this->responseObject($mResponse->Sender(), $sParent, $aParameters),
                    'Priority' => $mResponse->Priority(),
                    'Threads' => $mResponse->Threads(),
                    'Sensitivity' => $mResponse->Sensitivity(),
                    'ExternalProxy' => false,
                    'ReadReceipt' => '',
                    'avatar' => null,
                    'tags' => $this->responseObject($tags),
                ));
                
                
                
                if(Yii::$app->user->identity->timezone){
                $mResult['date'] = new DateTime($mResult['date']["date"]);
                $mResult['date']->setTimezone(new \DateTimeZone(Yii::$app->user->identity->timezone));
                }else{
                $mResult['date'] = new DateTime($mResult['date']["date"]);
                $mResult['date']->setTimezone((new \DateTimeZone('Europe/Belgrade')));
                
                //$mResult['date'] = $mResult['date']["date"];
		}
                if($mResult['to'] == null){
                    $mResult['to'] = array();
                }

                $mResult['SubjectParts'] = $this->explodeSubject($mResult['Subject']);

                $oAttachments = $mResponse->Attachments();
                $iAttachmentsCount = $oAttachments ? $oAttachments->Count() : 0;

                $mResult['attachments'] = 0 < $iAttachmentsCount;
                $mResult['attachmentsCount'] = $iAttachmentsCount;
                $mResult['attachmentsData'] = $mResult['attachments'] ? $oAttachments->SpecData() : array();
                //new info for attachments
                // $mResult['Attachments'] = $this->responseObject($oAttachments, $sParent, \array_merge($aParameters, array(
                //'FoundedCIDs' => $mFoundedCIDs,
                //'FoundedContentLocationUrls' => $mFoundedContentLocationUrls
                //	)));
                // var_dump( $mResult['Attachments']); die;
                //end new info
                $sSubject = $mResult['Subject'];

                $aFlags = $mResponse->FlagsLowerCase();
                
//                $a = $this->MailClient()->Message($mResult['mbox'], (int) $mResult['number'], true, true, 4);
//                var_dump($a); die;
                $mResult['deleted'] = \in_array('\\deleted', $aFlags);
                //$mResult['flagged'] = \in_array('\\flagged', $aFlags);
                $mResult['important'] = \in_array('\\flagged', $aFlags);
                $mResult['seen'] = \in_array('\\seen', $aFlags);
                $mResult['new'] = \in_array('\\new', $aFlags);
                $mResult['answered'] = \in_array('\\answered', $aFlags);
                

                $sForwardedFlag = '';
                $sReadReceiptFlag = '';
                $mResult['IsForwarded'] = 0 < \strlen($sForwardedFlag) && \in_array(\strtolower($sForwardedFlag), $aFlags);
                $mResult['IsReadReceipt'] = 0 < \strlen($sReadReceiptFlag) && \in_array(\strtolower($sReadReceiptFlag), $aFlags);

                $mResult['TextPartIsTrimmed'] = false;


                if ('Message' === $sParent) {
//                                
                    $oAttachments = /* @var \MailSo\Mail\AttachmentCollection */ $mResponse->Attachments();

                    $bHasExternals = false;
                    $mFoundedCIDs = array();
                    $aContentLocationUrls = array();
                    $mFoundedContentLocationUrls = array();

                    if ($oAttachments && 0 < $oAttachments->Count()) {
                        $aList = & $oAttachments->GetAsArray();
                        foreach ($aList as /* @var \MailSo\Mail\Attachment */ $oAttachment) {
                            if ($oAttachment) {
                                $sContentLocation = $oAttachment->ContentLocation();
                                if ($sContentLocation && 0 < \strlen($sContentLocation)) {
                                    $aContentLocationUrls[] = $oAttachment->ContentLocation();
                                }
                            }
                        }
                    }


                    $sPlain = '';
                    $sHtml = \trim($mResponse->Html());

                    if (0 === \strlen($sHtml)) {
                        $sPlain = \trim($mResponse->Plain());
                    }

                    $mResult['DraftInfo'] = $mResponse->DraftInfo();
                    $mResult['InReplyTo'] = $mResponse->InReplyTo();

                    $mResult['References'] = $mResponse->References();

                    $fAdditionalExternalFilter = null;

                    $mResult['ExternalProxy'] = null !== $fAdditionalExternalFilter;

                    $body = '';


                    $format_messages = $this->formatGetHeadNBodyHtml($sHtml, $sPlain, $mResponse->Uid(), $this->responseObject($mResponse->From(), $sParent, $aParameters)[0]['address'], null, $forigenImages);

                    if (isset($sPlain) && !empty($sPlain)) {
                        $body = $format_messages['messageBody'];
                    } elseif (isset($sHtml) && !empty($sHtml)) {

                        $body = $format_messages['messageBody'];
                    }
                    
                   // var_dump($body); die;
                    
                    $mResult['body'] = $body;
                    $mResult['countForigenImages'] = $format_messages['countMathesForigenImages'];
                    $mResult['hasForeignImages'] = $format_messages['hasForigenImages'];

                    unset($sHtml, $sPlain, $body);


                    $mResult['ReadReceipt'] = '';
                    if (0 < \strlen($mResult['ReadReceipt']) && !$mResult['IsReadReceipt']) {
                        if (0 < \strlen($mResult['ReadReceipt'])) {
                            try {
                                $oReadReceipt = \MailSo\Mime\Email::Parse($mResult['ReadReceipt']);
                                if (!$oReadReceipt) {
                                    $mResult['ReadReceipt'] = '';
                                }
                            } catch (\Exception $oException) {
                                unset($oException);
                            }
                        }
                    }
                }
            } else if ('MailSo\Mime\Email' === $sClassName) {
                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'name' => \MailSo\Base\Utils::Utf8Clear($mResponse->GetDisplayName()),
                    'address' => \MailSo\Base\Utils::Utf8Clear($mResponse->GetEmail(true)),
                ));
            } else if ('MailSo\Mail\Attachment' === $sClassName) {
                $oAccount = ''; // $this->getAccountFromToken(false);

                $mFoundedCIDs = isset($aParameters['FoundedCIDs']) && \is_array($aParameters['FoundedCIDs']) &&
                        0 < \count($aParameters['FoundedCIDs']) ?
                        $aParameters['FoundedCIDs'] : null;

                $mFoundedContentLocationUrls = isset($aParameters['FoundedContentLocationUrls']) &&
                        \is_array($aParameters['FoundedContentLocationUrls']) &&
                        0 < \count($aParameters['FoundedContentLocationUrls']) ?
                        $aParameters['FoundedContentLocationUrls'] : null;

                if ($mFoundedCIDs || $mFoundedContentLocationUrls) {
                    $mFoundedCIDs = \array_merge($mFoundedCIDs ? $mFoundedCIDs : array(), $mFoundedContentLocationUrls ? $mFoundedContentLocationUrls : array());

                    $mFoundedCIDs = 0 < \count($mFoundedCIDs) ? $mFoundedCIDs : null;
                }

                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'Folder' => $mResponse->Folder(),
                    'Uid' => (string) $mResponse->Uid(),
                    'Framed' => false,
                    'MimeIndex' => (string) $mResponse->MimeIndex(),
                    'MimeType' => $mResponse->MimeType(),
                    'EstimatedSize' => $mResponse->EstimatedSize(),
                    'CID' => $mResponse->Cid(),
                    'ContentLocation' => $mResponse->ContentLocation(),
                    'IsInline' => $mResponse->IsInline(),
                    'IsThumbnail' => $bThumb,
                    'IsLinked' => ($mFoundedCIDs && \in_array(\trim(\trim($mResponse->Cid()), '<>'), $mFoundedCIDs)) ||
                    ($mFoundedContentLocationUrls && \in_array(\trim($mResponse->ContentLocation()), $mFoundedContentLocationUrls))
                ));


                if ($mResult['IsThumbnail']) {
                    $mResult['IsThumbnail'] = $this->isFileHasThumbnail($mResult['FileName']);
                }
            } else if ('MailSo\Mail\Folder' === $sClassName) {
                $aExtended = null;


                $aCheckableFolder = \call_user_func($fGetCheckableFolder);
                if (!\is_array($aCheckableFolder)) {
                    $aCheckableFolder = array();
                }

                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'Name' => $mResponse->Name(),
                    'FullName' => $mResponse->FullName(),
                    'FullNameRaw' => $mResponse->FullNameRaw(),
                    'FullNameHash' => $this->hashFolderFullName($mResponse->FullNameRaw(), $mResponse->FullName()),
                    'Delimiter' => (string) $mResponse->Delimiter(),
                    'HasVisibleSubFolders' => $mResponse->HasVisibleSubFolders(),
                    'IsSubscribed' => $mResponse->IsSubscribed(),
                   // 'IsExists' => $mResponse->IsExists(),
                    'IsSelectable' => $mResponse->IsSelectable(),
                    'Flags' => $mResponse->FlagsLowerCase(),
                    'Checkable' => \in_array($mResponse->FullNameRaw(), $aCheckableFolder),
                    'Extended' => $aExtended,
                    'SubFolders' => $this->responseObject($mResponse->SubFolders(), $sParent, $aParameters)
                ));
            } else if ('MailSo\Mail\MessageCollection' === $sClassName) {
                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'MessageCount' => $mResponse->MessageCount,
                    'MessageUnseenCount' => $mResponse->MessageUnseenCount,
                    'MessageResultCount' => $mResponse->MessageResultCount,
                    'Folder' => $mResponse->FolderName,
                    'FolderHash' => $mResponse->FolderHash,
                    //'UidNext' => $mResponse->UidNext,
                    //'ThreadUid' => $mResponse->ThreadUid,
                    'NewMessages' => $this->responseObject($mResponse->NewMessages),
                    //'Filtered' => $mResponse->Filtered,
                    'Offset' => $mResponse->Offset,
                    'Limit' => $mResponse->Limit,
                    'Search' => $mResponse->Search
                ));
            } else if ('MailSo\Mail\AttachmentCollection' === $sClassName) {
                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'InlineCount' => $mResponse->InlineCount()
                ));
            } else if ('MailSo\Mail\FolderCollection' === $sClassName) {
                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'Namespace' => $mResponse->GetNamespace(),
                    'FoldersHash' => isset($mResponse->FoldersHash) ? $mResponse->FoldersHash : '',
                    'IsThreadsSupported' => $mResponse->IsThreadsSupported,
                  //  'Optimized' => $mResponse->Optimized,
                 //   'CountRec' => $mResponse->CountRec(),
                    'SystemFolders' => isset($mResponse->SystemFolders) && \is_array($mResponse->SystemFolders) ?
                    $mResponse->SystemFolders : array()
                ));
            } else if ($mResponse instanceof \MailSo\Base\Collection) {
                $aList = & $mResponse->GetAsArray();
                if (100 < \count($aList) && $mResponse instanceof \MailSo\Mime\EmailCollection) {
                    $aList = \array_slice($aList, 0, 100);
                }

                $mResult = $this->responseObject($aList, $sParent, $aParameters);
                $bHook = false;
            } else if ('api\models\Tag' === $sClassName) {


                $mResult = \array_merge($this->objectData($mResponse, $sParent, $aParameters), array(
                    'user_id' => $mResponse->user_id,
                    'color' => $mResponse->color,
                    'tag_name' => $mResponse->tag_name,
                    'id' => $mResponse->id,
                    'bgcolor' => $mResponse->bgcolor,
                ));
            } else {
                $mResult = '["' . \get_class($mResponse) . '"]';
                $bHook = false;
            }
        } else if (\is_array($mResponse)) {
            foreach ($mResponse as $iKey => $oItem) {
                $mResponse[$iKey] = $this->responseObject($oItem, $sParent, $aParameters);
            }

            $mResult = $mResponse;
        }

        unset($mResponse);

        return $mResult;
    }

    private function objectData($oData, $sParent = '', $aParameters = array()) 
    {
        $mResult = false;
        if (is_object($oData)) {
            $aNames = explode('\\', get_class($oData));
            $mResult = array(
                '@Object' => end($aNames)
            );

            if ($oData instanceof \MailSo\Base\Collection) {
                $mResult['@Object'] = 'Collection/' . $mResult['@Object'];
                $mResult['@Count'] = $oData->Count();
                $mResult['@Collection'] = $this->responseObject($oData->GetAsArray(), $sParent, $aParameters);
            } else {
                $mResult['@Object'] = 'Object/' . $mResult['@Object'];
            }
        }

        return $mResult;
    }

    private function parseInlineCss($style) 
    {
        $results = [];
        $matches = [];
        preg_match_all("/([\w-]+)\s*:\s*([^;]+)\s*;?/", $style, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $results[$match[1]] = $match[2];
        }

        return $results;
    }
    
    
    public function getFoldersList() 
    {
        $oAccount = $this->MailClient();

        $oFolderCollection = null;

        $bUseFolders = null;
        if (null === $oFolderCollection) {
            $oFolderCollection = $this->MailClient()->Folders('', "*", true, 200
            );
        }


        if ($oFolderCollection instanceof \MailSo\Mail\FolderCollection) {

            $aSystemFolders = array();
            $this->recFoldersTypes($oAccount, $oFolderCollection, $aSystemFolders);
            $oFolderCollection->SystemFolders = $aSystemFolders;

            $bDoItAgain = false;

            $sNamespace = $oFolderCollection->GetNamespace();
            $sParent = empty($sNamespace) ? '' : \substr($sNamespace, 0, -1);

            $sDelimiter = '/';
            $aList = array();
            $aMap = $this->systemFoldersNames($oAccount);

            $aList[] = \MailSo\Imap\Enumerations\FolderType::SENT;

            $aList[] = \MailSo\Imap\Enumerations\FolderType::DRAFTS;

            $aList[] = \MailSo\Imap\Enumerations\FolderType::JUNK;

            $aList[] = \MailSo\Imap\Enumerations\FolderType::TRASH;

            $aList[] = \MailSo\Imap\Enumerations\FolderType::ALL;



            foreach ($aList as $iType) {
                if (!isset($aSystemFolders[$iType])) {
                    $mFolderNameToCreate = \array_search($iType, $aMap);
                    if (!empty($mFolderNameToCreate)) {
                        $iPos = \strrpos($mFolderNameToCreate, $sDelimiter);
                        if (false !== $iPos) {
                            $mNewParent = \substr($mFolderNameToCreate, 0, $iPos);
                            $mNewFolderNameToCreate = \substr($mFolderNameToCreate, $iPos + 1);
                            if (0 < \strlen($mNewFolderNameToCreate)) {
                                $mFolderNameToCreate = $mNewFolderNameToCreate;
                            }

                            if (0 < \strlen($mNewParent)) {
                                $sParent = 0 < \strlen($sParent) ? $sParent . $sDelimiter . $mNewParent : $mNewParent;
                            }
                        }

                        $sFullNameToCheck = \MailSo\Base\Utils::ConvertEncoding($mFolderNameToCreate, \MailSo\Base\Enumerations\Charset::UTF_8, \MailSo\Base\Enumerations\Charset::UTF_7_IMAP);

                        if (0 < \strlen(\trim($sParent))) {
                            $sFullNameToCheck = $sParent . $sDelimiter . $sFullNameToCheck;
                        }

                        if (!$oFolderCollection->GetByFullNameRaw($sFullNameToCheck)) {
                            try {
                                if ($this->MailClient()->FolderCreate($mFolderNameToCreate, $sParent, true, $sDelimiter)) {
                                    $bDoItAgain = true;
                                }
                            } catch (\Exception $oException) {
                                $this->Logger()->WriteException($oException);
                            }
                        }
                    }
                }
            }

            if ($bDoItAgain) {
                $oFolderCollection = $this->MailClient()->Folders('', '*', !!$this->Config()->Get('labs', 'use_imap_list_subscribe', true), (int) $this->Config()->Get('labs', 'imap_folder_list_limit', 200)
                );

                if ($oFolderCollection) {
                    $aSystemFolders = array();
                    $this->recFoldersTypes($oAccount, $oFolderCollection, $aSystemFolders);
                    $oFolderCollection->SystemFolders = $aSystemFolders;
                }
            }


            if ($oFolderCollection) {
                $oFolderCollection->FoldersHash = \md5(\implode("\x0", $this->recFoldersNames($oFolderCollection)));
            }
        }

        return $this->responseObject($oFolderCollection);
    }

    private function recFoldersTypes($oAccount, $oFolders, &$aResult, $bListFolderTypes = true) 
    {
        if ($oFolders) {
            $aFolders = & $oFolders->GetAsArray();
            if (\is_array($aFolders) && 0 < \count($aFolders)) {
                if ($bListFolderTypes) {
                    foreach ($aFolders as $oFolder) {
                        $iFolderListType = $oFolder->GetFolderListType();
                        if (!isset($aResult[$iFolderListType]) && \in_array($iFolderListType, array(
                                    \MailSo\Imap\Enumerations\FolderType::SENT,
                                    \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                                    \MailSo\Imap\Enumerations\FolderType::JUNK,
                                    \MailSo\Imap\Enumerations\FolderType::TRASH,
                                    \MailSo\Imap\Enumerations\FolderType::ALL
                                ))) {
                            $aResult[$iFolderListType] = $oFolder->FullNameRaw();
                        }
                    }

                    foreach ($aFolders as $oFolder) {
                        $oSub = $oFolder->SubFolders();
                        if ($oSub && 0 < $oSub->Count()) {
                            $this->recFoldersTypes($oAccount, $oSub, $aResult, true);
                        }
                    }
                }

                $aMap = $this->systemFoldersNames($oAccount);
                foreach ($aFolders as $oFolder) {
                    $sName = $oFolder->Name();
                    $sFullName = $oFolder->FullName();

                    if (isset($aMap[$sName]) || isset($aMap[$sFullName])) {
                        $iFolderType = isset($aMap[$sName]) ? $aMap[$sName] : $aMap[$sFullName];
                        if (!isset($aResult[$iFolderType]) && \in_array($iFolderType, array(
                                    \MailSo\Imap\Enumerations\FolderType::SENT,
                                    \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                                    \MailSo\Imap\Enumerations\FolderType::JUNK,
                                    \MailSo\Imap\Enumerations\FolderType::TRASH,
                                    \MailSo\Imap\Enumerations\FolderType::ALL
                                ))) {
                            $aResult[$iFolderType] = $oFolder->FullNameRaw();
                        }
                    }
                }

                foreach ($aFolders as $oFolder) {
                    $oSub = $oFolder->SubFolders();
                    if ($oSub && 0 < $oSub->Count()) {
                        $this->recFoldersTypes($oAccount, $oSub, $aResult, false);
                    }
                }
            }
        }
    }

    private function systemFoldersNames($oAccount)
    {
        static $aCache = null;
        if (null === $aCache)
        {
                $aCache = array(

                        'Sent' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Send' => \MailSo\Imap\Enumerations\FolderType::SENT,

                        'Outbox' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Out box' => \MailSo\Imap\Enumerations\FolderType::SENT,

                        'Sent Item' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Sent Items' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Send Item' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Send Items' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Sent Mail' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Sent Mails' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Send Mail' => \MailSo\Imap\Enumerations\FolderType::SENT,
                        'Send Mails' => \MailSo\Imap\Enumerations\FolderType::SENT,

                        'Drafts' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,

                        'Draft' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                        'Draft Mail' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                        'Draft Mails' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                        'Drafts Mail' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,
                        'Drafts Mails' => \MailSo\Imap\Enumerations\FolderType::DRAFTS,

                        'Spam' => \MailSo\Imap\Enumerations\FolderType::JUNK,
                        'Spams' => \MailSo\Imap\Enumerations\FolderType::JUNK,

                        'Junk' => \MailSo\Imap\Enumerations\FolderType::JUNK,
                        'Bulk Mail' => \MailSo\Imap\Enumerations\FolderType::JUNK,
                        'Bulk Mails' => \MailSo\Imap\Enumerations\FolderType::JUNK,

                        'Trash' => \MailSo\Imap\Enumerations\FolderType::TRASH,
                        'Deleted' => \MailSo\Imap\Enumerations\FolderType::TRASH,
                        'Bin' => \MailSo\Imap\Enumerations\FolderType::TRASH,

                        'Archive' => \MailSo\Imap\Enumerations\FolderType::ALL,
                        'Archives' => \MailSo\Imap\Enumerations\FolderType::ALL,

                        'All' => \MailSo\Imap\Enumerations\FolderType::ALL,
                        'All Mail' => \MailSo\Imap\Enumerations\FolderType::ALL,
                        'All Mails' => \MailSo\Imap\Enumerations\FolderType::ALL,
                );

                $aNewCache = array();
                foreach ($aCache as $sKey => $iType)
                {
                        $aNewCache[$sKey] = $iType;
                        $aNewCache[\str_replace(' ', '', $sKey)] = $iType;
                }

                $aCache = $aNewCache;

                //$this->Plugins()->RunHook('filter.system-folders-names', array($oAccount, &$aCache));
        }

        return $aCache;
    }
        
        
        
    private function recFoldersNames($oFolders) 
    {
        $result = array();
        if ($oFolders) {
            $aFolders = & $oFolders->GetAsArray();

            foreach ($aFolders as $oFolder) {
                $result[] = $oFolder->FullNameRaw() . "|" .
                        implode("|", $oFolder->Flags()) . ($oFolder->IsSubscribed() ? '1' : '0');

                $oSub = $oFolder->SubFolders();
                if ($oSub && 0 < $oSub->Count()) {
                    $result = \array_merge($result, $this->recFoldersNames($oSub));
                }
            }
        }

        return $result;
    }

    public function getFolderInformation()
	{
		$result = [];
                
		$aFolders = $this->getFoldersList();

                $folders = [];
                foreach ($aFolders["@Collection"] as $folder){
                   
                    $folders[] = $folder['Name'];
                }
                $aFolders = $folders;
 
		if (\is_array($aFolders))
		{
			$this->MailClient();

			$aFolders = \array_unique($aFolders);
			foreach ($aFolders as $sFolder)
			{
				if (0 < \strlen(\trim($sFolder)))
				{
					try
					{
						$aInboxInformation = $this->MailClient()->FolderInformation($sFolder, '', array());
                                                
						if (\is_array($aInboxInformation) && isset($aInboxInformation['Folder']))
						{
							$result[] = $aInboxInformation;
						}
					}
					catch (\Exception $oException)
					{
                                            //$this->Logger()->WriteException($oException);
					}
				}
			}
		}
                
                return $result;

	}
        
        private function hashFolderFullName($sFolderFullName)
	{
		return \in_array(\strtolower($sFolderFullName), array('inbox', 'sent', 'send', 'drafts',
			'spam', 'junk', 'bin', 'trash', 'archive', 'allmail', 'all')) ?
				$sFolderFullName : \md5($sFolderFullName);
	}

}
