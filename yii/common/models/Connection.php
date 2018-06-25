<?php

namespace common\models;

use Yii;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "{{%connection}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $email
 * @property string $password
 * @property string $login
 * @property string $server
 * @property string $port
 * @property integer $enable
 * @property integer $is_send
 *
 * @property User $user
 * @property Sign[] $signs
 * @property Template[] $templates
 * @property User[] $users
 */
class Connection extends ActiveRecord
{
    public function afterFind()
    {
        parent::afterFind();

        if (!$this->isNewRecord) {
            $key = sha1($this->email);
            $p = $this->password;

            try {
                $b = hex2bin($this->password);
                $this->password = \Yii::$app->security->decryptByKey($b, $key);
                if (!$this->password) {
                    $this->password = $p;
                }
            } catch (\Exception $e) {
                $this->password = $p;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%connection}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id'], 'integer'],
            [['enable', 'is_send'], 'boolean'],
            [['email', 'password', 'login', 'server', 'port'], 'safe'],
            [['email', 'password', 'login', 'server', 'port'], 'string', 'max' => 255],
            [['sign'], 'string'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'user_id' => Yii::t('app', 'User ID'),
            'email' => Yii::t('app', 'Email'),
            'password' => Yii::t('app', 'Password'),
            'login' => Yii::t('app', 'Login'),
            'server' => Yii::t('app', 'Server'),
            'port' => Yii::t('app', 'Port'),
            'enable' => Yii::t('app', 'Enable'),
            'is_send' => Yii::t('app', 'Send enabled'),
            'sign' => Yii::t('app', 'Sign'),
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        if (!$this->user_id) {
            $this->user_id = \Yii::$app->user->identity->getId();
        }
        $this->is_send = strpos($this->email, Yii::$app->params['domain']) !== false;

        return parent::beforeValidate();
    }

    /**
     * @param type $insert
     * @return type
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->enable = true;
        } else {
            if (!$this->enable && $this->user->selected_connection_id == $this->id) {
                $this->user->selected_connection_id = $this->user->default_connection_id;
                $this->user->save();
            }
        }

        $key = sha1($this->email);
        $b = \Yii::$app->security->encryptByKey($this->password, $key);
        $this->password = bin2hex($b);

        return parent::beforeSave($insert);
    }


    /**
     * @param bool $insert
     * @param array $changedAttributes
     * @return type
     */
    public function afterSave($insert, $changedAttributes)
    {
        $key = sha1($this->email);
        $b = hex2bin($this->password);
        $this->password = \Yii::$app->security->decryptByKey($b, $key);

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     */
    public function fields()
    {
        return ArrayHelper::merge(
            parent::fields(),
            [
                'default',
            ]
        );
    }

    /**
     * @return bool
     */
    public function getDefault() {
        return $this->user->default_connection_id == $this->id;
    }

    /**
     * @return ActiveQuery
     */
    public function getSigns()
    {
        return $this->hasMany(Sign::className(), ['connection_id' => 'id']);
    }

    /**
     * @return ActiveQuery
     */
    public function getTemplates()
    {
        return $this->hasMany(Template::className(), ['connection_id' => 'id']);
    }


    /**     WORKING WITH IMAP VIA MASTER_PASSWORD   **/

    public function getImapEmail() : string
    {
        $email = $this->email;
        if ($this->imapUseMasterPassword() && !$this->isThirdPartyConnection()) {
            $email .= \Yii::$app->params['imap.master_user'];
        }

        return $email;
    }

    public function getImapPassword()
    {
        if ($this->imapUseMasterPassword()) {
            $password = \Yii::$app->params['imap.master_password'];
        } else {
//            if ($this->isThirdPartyConnection()) {
                $password = $this->password;
//            } else {
//                $user = clone $this->user;
//                unset($this->user);
//                $password = \Yii::$app->passStore->getPassword($user) ?: $this->password;
//            }

            if (!$password) {
                \Yii::$app->user->logout();
            }
        }

        return $password;
    }

    /**
     * @return bool
     */
    private function imapUseMasterPassword() : bool
    {
        return ArrayHelper::getValue(\Yii::$app->params, 'imap.use_master_password', false);
    }

    /**
     * @return bool
     */
    private function isThirdPartyConnection() : bool
    {
        return $this->user->email != $this->email;
    }
}
