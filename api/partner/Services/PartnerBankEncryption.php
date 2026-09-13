<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;

final class PartnerBankEncryption
{
    public const CURRENT_BANK_ENCRYPTION_VERSION=1;
    private string $key;
    public function __construct(?string $secret=null)
    {
        if(!function_exists('openssl_encrypt')||!function_exists('openssl_decrypt'))$this->unavailable();
        $secret=$secret??getenv('PARTNER_BANK_ENCRYPTION_KEY')?:'';
        if(strlen($secret)<32)$this->unavailable();
        $this->key=hash('sha256',$secret,true);
    }
    public function encrypt(string $account):string
    {
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($account,'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$iv,$tag,'',16);
        if($cipher===false||strlen($tag)!==16)$this->unavailable();
        return chr(self::CURRENT_BANK_ENCRYPTION_VERSION).$iv.$tag.$cipher;
    }
    public function decrypt(string $envelope,int $version):string
    {
        if($version!==self::CURRENT_BANK_ENCRYPTION_VERSION||strlen($envelope)<30||ord($envelope[0])!==$version)$this->invalid();
        $plain=openssl_decrypt(substr($envelope,29),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,substr($envelope,1,12),substr($envelope,13,16),'');
        if($plain===false)$this->invalid();return$plain;
    }
    public function mask(string $last4):string{return'••••••••'.$last4;}
    private function unavailable():never{throw new ApiException(503,'BANK_ENCRYPTION_UNAVAILABLE','Bank encryption is not configured safely.','Bank details are temporarily unavailable.',[],true);}
    private function invalid():never{throw new ApiException(503,'BANK_CIPHERTEXT_INVALID','Stored Bank credentials could not be authenticated.','Bank details are temporarily unavailable.',[],true);}
}
