<?php
namespace App\Utils;

class Utils{
	public static function curlSendRequest ($method, $endpoint, $data, $headers = [], $timeout = null) {
		if(!$timeout) $timeout = env("WS_TIMEOUT");
		$req = curl_init();
        $data = json_encode($data);
		curl_setopt($req, CURLOPT_URL, $endpoint);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($req, CURLOPT_POSTFIELDS, $data);
        curl_setopt($req, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($req, CURLOPT_TIMEOUT, $timeout * 1000);
        $response = curl_exec($req);
        curl_close($req);
        return json_decode($response);
	}

	public static function soapSendRequest ($endpoint, $action, $data = [], $timeout = null) {
		if(!$timeout) $timeout = env("WS_TIMEOUT");
		$client = new \SoapClient($endpoint, [
			"connection_timeout" => $timeout
		]);
		$response = $client->__soapCall($action, [$data]);
		return $response;
	}

	public static function getSchemas ($schemaName, $version, $action = null, $method = null, $type = null){
		$schemas = [];
		$filesData = [
			"request" => scandir(app("path")."/Schemas/request/{$version}"),
			"response" => scandir(app("path")."/Schemas/response/{$version}")
		];
		if($type) $filesData = scandir(app("path")."/Schemas/{$type}/{$version}");
		if($action) $schemaName .= ".{$action}";

		foreach ($filesData as $type => $dataFiles) {
			if(!isset($schemas[$type."Schemas"])) $schemas[$type."Schemas"] = [];
			foreach ($dataFiles as $filename) {
				if(preg_match("/(^[\.]{1,2}$)/", $filename) > 0) continue;
				if(preg_match("/(^{$schemaName})/", $filename) == 0) continue;
				preg_match("/^{$schemaName}([\.a-z]+)?\.schema\.json/", $filename, $matches, PREG_OFFSET_CAPTURE);
				if(isset($matches[1])) $name = substr($matches[1][0], 1);
				
				$schemaPath = app("path")."/Schemas/{$type}/{$version}/{$filename}";
				$schemas[$type."Schemas"][$name ?? "index"] = json_decode(file_get_contents($schemaPath));
			}
		}
		return $schemas;
	}

	public static function toObject ($arr){
		return json_decode(json_encode($arr));
	}
}