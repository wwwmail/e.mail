<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

use \AdamStipak\Webpay\PaymentRequest;
use \AdamStipak\Webpay\PaymentResponse;
use \AdamStipak\Webpay\Exception;
/**
 * This is the model class for table "{{%paypal_payment}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property integer $created_at
 * @property integer $updated_at
 * @property integer $tariffId
 * @property string $intent
 * @property string $payerID
 * @property string $paymentID
 * @property string $paymentToken
 * @property string $returnUrl
 * @property integer $status
 *
 * @property Tariff $tariff
 * @property User $user
 */
class GPWebpay 
{
    const STATUS_NEW    = 0;
    const STATUS_PAID   = 1;
    const STATUS_ERROR  = 2;
    const PAY_EUR = '978';
    const DEPOSIT_FLAG = 0;
    

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTariff($id)
    {
        return Tariff::find()->where(['id'=> $id])->one();
        return $this->hasOne(Tariff::className(), ['id' => 'tariffId']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return User::find()->where(['id'=> $id])->one();
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * @return bool
     */
    public function process() {
        if ($this->status !== self::STATUS_NEW) {
            return false;
        }

        if ($this->user->addQuota($this->tariff->quota)) {
            $this->status = self::STATUS_PAID;
            $this->save();
            return true;
        }

        $this->status = self::STATUS_ERROR;
        $this->save();
        return false;
    }
    
    public function addQuota($user_id, $quota)
    {
      $user = $this->getUser($user_id);
        
      $user->addQuota($quota);
    }

    


    /**
     * @return string
     */
    public function getPayUrl($order_number , $user_id, $tarif_id)
    {
       $signer = new \AdamStipak\Webpay\Signer(
                __DIR__ . Yii::$app->params['path_to_private_key'],  // Path of private key.
                          Yii::$app->params['pass_for_private_key'], // Password for private key.
                __DIR__ . Yii::$app->params['path_to_public_key']    // Path of public key. /var/www/html/mail.dev/backend/key
        );

        $api = new \AdamStipak\Webpay\Api(
                Yii::$app->params['merchant_number'], // Merchant number.
                Yii::$app->params['payment_url'],     // URL of webpay. 
                $signer                               // instance of \AdamStipak\Webpay\Signer.
        );
        
        $tarif= $this->getTariff($tarif_id);
              
       
        
        
        $request = new PaymentRequest($order_number, $tarif->price, self::PAY_EUR, self::DEPOSIT_FLAG, Yii::$app->params['api_url'].Yii::$app->params['return_pay_url']);
        
        
        
        $url = $api->createPaymentRequestUrl($request); 
        
        return $url;
    }

    
}
