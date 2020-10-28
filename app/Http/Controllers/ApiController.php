<?php

namespace App\Http\Controllers;

use App\Exceptions\ResponseException;
use App\Exceptions\ShowableException;
use App\Schemas\SchemaValidator;
use App\Utils\Utils;
use Illuminate\Http\Request;

class ApiController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    public function index(Request $request) {
        $fullPath = $request->getPathInfo();
        $fullPathNoVersion = preg_replace("/\/v([0-9]+)/", "", $fullPath);
        $path = explode("/", substr($fullPath, 1));
        $version = $path[0] == getenv("APP_PREFIX") ? $path[1] : $path[0];
        $method = strtolower($request->getMethod());
        $action = null;
        $actionRaw = "index";
        $actionExist = false;
        $controller = "\\App\\Controllers\\{$version}";
        $controllerFound = false;
        $controllerName = "";
        $uriParams = [];

        $path = array_filter($path, function($a){
            if($a != getenv("APP_PREFIX")) return $a;
        });

        foreach ($path as $key => $val) {
            if (preg_match("/^v([0-9]{1,1})$/i", $val) == false) {
                if (!$controllerFound) {
                    $controllerName = "\\" . ucfirst($val) . "Controller";
                    if (class_exists($controller . $controllerName)) {
                        $controller .= $controllerName;
                        $controllerFound = true;
                        $controllerName = $val;
                    } else {
                        $controller .= "\\{$val}";
                    }
                    continue;
                }

                $actin = "{$method}_{$val}";
                $actionRaw = $val;
                if (!method_exists($controller, $actin)) {
                    if (!$actionExist) {
                        $action = null;
                        $actionRaw = "index";
                    }

                    if (!empty($val))
                        $uriParams[] = $val;
                } else {
                    $action = $actin;
                    $actionExist = true;
                }
            }
        }

        if ($actionExist == null)
            $action = "{$method}_index";

        if (!class_exists($controller))
            throw new ShowableException(400, "This controller name doesn't exists. (\\{$controllerName})");
        if (!method_exists($controller, $action))
            throw new ShowableException(400, "This method name doesn't exists. \\{$controllerName}::{$action}()");

        $body = Utils::toObject($request->all());
        $explodeCtrlr = explode($version, $controller);
        $explodeCtrlr = str_replace("Controller", "", $explodeCtrlr[1]);
        $explodeCtrlr = str_replace("\\", ".", $explodeCtrlr);
        $explodeCtrlr = substr(strtolower($explodeCtrlr), 1);
        $schemaPathRequest = app("path") . "/Schemas/request/{$version}/{$explodeCtrlr}" . ($actionRaw != "index" ? "." . $actionRaw : (!empty($uriParams[0]) ? "." . implode(".", $uriParams) : "")) . ".{$method}.schema.json";
        $schemaRequest = (file_exists($schemaPathRequest)) ? file_get_contents($schemaPathRequest) : null;
        if ($schemaRequest)
            SchemaValidator::validateJson($body, $schemaRequest);

        $data = (object) [
                    "version" => $version,
                    "method" => $method,
                    "controllerName" => ucfirst($controllerName) . "Controller",
                    "name" => $controllerName,
                    "action" => $actionRaw,
                    "controller" => "{$controller}::{$action}"
        ];

        $fn = new $controller;
        array_push($uriParams, (object) $body, (object) $data);
        $response = call_user_func_array([$fn, $action], $uriParams);
        $response = Utils::toObject($response);

        $schemaPathResponse = str_replace("Schemas/request", "Schemas/response", $schemaPathRequest);
        $schemaResponse = (file_exists($schemaPathResponse)) ? file_get_contents($schemaPathResponse) : null;
        if ($schemaResponse)
            SchemaValidator::validateJson($response, $schemaResponse);

        return (array) $response;
    }

    protected function validatePermissions($controller, $action = NULL) {
        $controller = "{$controller}/{$action}";
        return true;
    }

}
