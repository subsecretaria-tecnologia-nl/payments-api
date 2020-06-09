<?php
namespace App\Schemas;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\InvalidValue;
use App\Exceptions\JsonSchemaException;

class SchemaValidator{
	public static function validateJson(&$json, &$schema){
		if(is_string($schema)){
			$schemaVal = Schema::import(json_decode($schema));
		}
		else if($schema instanceof Schema){
			$schemaVal = $schema;
		}
		else{
			throw new JsonSchemaException(NULL, "Unknown schema format. Should be json string or Swaggest\JsonSchema\Schema");
		}

		$schemaVal->in($json);
		# TODO: try catch function and use ep defined exceptions
	}
}