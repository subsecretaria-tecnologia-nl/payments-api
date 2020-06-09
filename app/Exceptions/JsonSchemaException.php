<?php 

namespace app\ep\exceptions\jsonExceptions;
use App\ep\exceptions\LoggableException;

class JsonSchemaException extends LoggableException {
	protected $logMsg = "JSON Schema not found or not valid";
}