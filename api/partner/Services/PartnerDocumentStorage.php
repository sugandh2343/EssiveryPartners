<?php
declare(strict_types=1);

namespace Essivery\Partner\Services;

use Essivery\Api\Core\ApiException;

final class PartnerDocumentStorage
{
    public function __construct(private array$config){}
    public function store(array$file,string$ownerKind,string$ownerPublicId):array
    {
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);if($error!==UPLOAD_ERR_OK)$this->uploadError($error);
        $size=(int)($file['size']??0);if($size<1)throw$this->invalid('file','The uploaded file is empty.');if($size>(int)$this->config['max_bytes'])throw new ApiException(413,'DOCUMENT_TOO_LARGE','Document exceeds the five megabyte limit.','File is too large.',['file'=>'Maximum file size is 5 MB.']);
        $temporary=(string)($file['tmp_name']??'');if($temporary===''||!is_file($temporary))throw$this->invalid('file','The uploaded file could not be read.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temporary)?:'';$extension=$this->config['mime_types'][$mime]??null;if(!$extension)throw$this->invalid('file','This file type is not supported.');
        if(str_starts_with($mime,'image/')&&@getimagesize($temporary)===false)throw$this->invalid('file','The uploaded image is not valid.');
        if($mime==='application/pdf'&&file_get_contents($temporary,false,null,0,5)!=='%PDF-')throw$this->invalid('file','The uploaded PDF is not valid.');
        if(!preg_match('/^(partners|delivery-partners)$/',$ownerKind)||!preg_match('/^[A-Za-z0-9_-]{8,80}$/',$ownerPublicId))throw new ApiException(500,'DOCUMENT_STORAGE_ERROR','Document storage owner is invalid.','We could not store this document.',[],true);
        $relative=$ownerKind.'/'.$ownerPublicId.'/'.bin2hex(random_bytes(24)).'.'.$extension;$target=$this->path($relative);$directory=dirname($target);
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new ApiException(503,'DOCUMENT_STORAGE_UNAVAILABLE','Private document storage is not writable.','Document storage is temporarily unavailable.',[],true);
        $moved=move_uploaded_file($temporary,$target);if(!$moved&&PHP_SAPI==='cli')$moved=@copy($temporary,$target);if(!$moved)throw new ApiException(503,'DOCUMENT_STORAGE_UNAVAILABLE','Document file could not be moved to private storage.','We could not upload this document. Please try again.',[],true);
        @chmod($target,0600);return['reference'=>$relative,'mime'=>$mime,'family'=>$mime==='application/pdf'?'pdf':'image'];
    }
    public function path(string$reference):string
    {
        if($reference===''||str_contains($reference,'..')||str_starts_with($reference,'/')||str_starts_with($reference,'\\')||!preg_match('#^(partners|delivery-partners)/[A-Za-z0-9_-]{8,80}/[a-f0-9]{48}\.(jpg|png|webp|pdf)$#',$reference))throw new ApiException(404,'DOCUMENT_FILE_NOT_FOUND','Document storage reference is invalid.','Document file was not found.');
        return rtrim((string)$this->config['root'],'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$reference);
    }
    public function remove(string$reference):void{try{$path=$this->path($reference);if(is_file($path))@unlink($path);}catch(ApiException){} }
    private function uploadError(int$error):never{$message=match($error){UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE=>'File is too large.',UPLOAD_ERR_PARTIAL=>'The upload was interrupted. Please try again.',UPLOAD_ERR_NO_FILE=>'Choose a document to upload.',default=>'We could not upload this document. Please try again.'};throw new ApiException($error===UPLOAD_ERR_INI_SIZE||$error===UPLOAD_ERR_FORM_SIZE?413:422,'DOCUMENT_UPLOAD_FAILED','PHP reported a document upload failure.',$message,['file'=>$message]);}
    private function invalid(string$field,string$message):ApiException{return new ApiException(422,'VALIDATION_ERROR','Document validation failed.',$message,[$field=>$message]);}
}
