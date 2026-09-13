<?php
declare(strict_types=1);

namespace Essivery\Partner\Middleware;

use Essivery\Api\Core\ApiException;
use Essivery\Api\Core\Request;

final class DocumentUploadRequestMiddleware
{
    public function __construct(array $container) {}
    public function handle(Request $request,callable $next):mixed
    {
        $unknown=array_values(array_diff(array_keys($request->body),['documentType']));
        $unknownFiles=array_values(array_diff(array_keys($request->files),['file']));
        if($unknown||$unknownFiles)throw new ApiException(422,'VALIDATION_ERROR','Document upload contains unsupported or protected fields.','Remove unsupported upload fields.',['body'=>'Only documentType and file are accepted.']);
        if(!isset($request->files['file'])||!is_array($request->files['file']))throw new ApiException(422,'VALIDATION_ERROR','A document file is required.','Choose a document to upload.',['file'=>'Choose a file.']);
        return$next($request);
    }
}
