<?php

namespace App\Http\Controllers;
use App\Exceptions\ResponseException;
use App\Exceptions\ShowableException;
use App\Schemas\SchemaValidator;
use App\Utils\Utils;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function index(Request $request){
        $fullPath = $request->getPathInfo();
        $path = explode("/", substr($fullPath, 1));
        $version = $path[0];
        $method = strtolower($request->getMethod());
        $action = null;
        $actionRaw = "index";
        $controller = "\\App\\Controllers\\{$version}";
        $controllerFound = false;
        $controllerName = "";

        foreach($path as $key => $val){
            if(preg_match("/^v([0-9]{1,1})$/i", $val) == false){
                if(!$controllerFound){
                    $controllerName = "\\".ucfirst($val)."Controller";
                    if(class_exists($controller.$controllerName)){
                        $controller .= $controllerName;
                        $controllerFound = true;
                        $controllerName = $val;
                    }else{
                        $controller .= "\\{$val}";
                    }
                }

                if($action == null){
                    $action = "{$method}_{$val}";
                    $actionRaw = $val;
                    if(!method_exists($controller, $action)){
                        $action = null;
                        $actionRaw = "index";
                    }
                }
            }
        }

        if($action == null)
            $action = "{$method}_index";

        if(!class_exists($controller))
            throw new ShowableException(400, "This controller name doesn't exists. (\\{$controllerName})");
        if(!method_exists($controller, $action))
            throw new ShowableException(400, "This method name doesn't exists. \\{$controllerName}::{$method}_{$action}()");

//        $params = (object)$request->all();
        $params = Utils::toObject($request->all());
        $schemaPathRequest = app("path")."/Schemas/request/{$version}/{$controllerName}".($actionRaw != "index" ? ".".$actionRaw : "").".{$method}.schema.json";
        $schemaRequest = (file_exists($schemaPathRequest)) ? file_get_contents($schemaPathRequest) : null;
        if($schemaRequest) SchemaValidator::validateJson($params, $schemaRequest);

        $data = (object)[
            "version" => $version,
            "method" => $method,
            "controllerName" => ucfirst($controllerName)."Controller",
            "name" => $controllerName,
            "action" => $actionRaw,
            "controller" => "{$controller}::{$action}"
        ];

        $response = (object)[$actionRaw == "index" ? $controllerName : $actionRaw => Utils::toObject($controller::{$action}($params, $data))];
        
        $schemaPathResponse = app("path")."/Schemas/response/{$version}/{$controllerName}".($actionRaw != "index" ? ".".$actionRaw : "").".{$method}.schema.json";
        $schemaResponse = (file_exists($schemaPathResponse)) ? file_get_contents($schemaPathResponse) : null;
        if($schemaResponse) SchemaValidator::validateJson($response, $schemaResponse);
        
        return (array)$response;
    }

    protected function validatePermissions($controller, $action = NULL){
        $controller = "{$controller}/{$action}";
        return true;
    }
}
