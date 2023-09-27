<?php
namespace zrpc;

use Exception;

/*
 Simple RPC using MSGPACK for data serialization over HTTP

 Require php-msgpack
 
 Quick server usage:
 <?php
 require_once('includes/zrpc.php');
 class MyRPCServer extends zphprpc\Server{
   function rpc_myfx($a, $b){
     return $a + $b;
   }
 }
 new MyRPCServer();


 Quick client usage:
 <?php
 require_once('includes/zrpc.php');
 $rpc_client = new zphprpc/Client('https://myserver/rpc/myscript.php');
 echo $rpc_client->myfx(4, 5);
 */

const VERSION = 0.1;

class Server{
    function __construct(string $key=null){
        $this->__send_header();
        $error = false;
        ob_start();
        $result = false;
        if (!isset($_POST['key']) || $_POST['key'] != $key){
            $error = 'Bad key.';
        }else if (!isset($_POST['key']) || $_POST['type'] != 'fx_call'){
            $error = 'Bad type.';
        }else if(!isset($_POST['fx_name']) || !method_exists($this, 'rpc_'.$_POST['fx_name'])){
            $error = 'Unknown function.';
        }else if(!isset($_POST['arguments'])){
            $error = 'No argument provided.';
        }else{
            $arguments = msgpack_unpack($_POST['arguments']);
            if (is_array($arguments)){
                $rpc_fx_name = 'rpc_'.$_POST['fx_name'];
                try{
                    $result = call_user_func_array(array($this, $rpc_fx_name), $arguments);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                unset($arguments);
            }else{
                $error = 'No valid argument provided.';
            }
        }
        $content = ob_get_contents();
        ob_end_clean();
        echo msgpack_pack(['result'=>$result,'error'=>$error,'txt'=>$content]);
        unset($result);
        unset($error);
        unset($content);
        exit();
    }

    private function __send_header(){
        header("HTTP/1.1 200 OK");
        header("Content-Type: application/octet-stream"); // octet/stream
        header("X-Powered-By: ZPHPRPC Server/".VERSION);
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    protected function get_rpc_methods(){
        $methods = get_class_methods($this);
        $rpc_methods = [];
        foreach ($methods as $method){
            if (substr($method, 0, 4) == 'rpc_'){
                $rpc_methods[] = substr($method, 4);
            }
        }
        return $rpc_methods;
    }
}

class Client{
    private string $__url;
    private string $__key;
    function __construct(string $url, string $key = null){
        $this->__url = $url;
        $this->__key = $key;
    }

    function __call(string $fx_name, array $fx_arguments){
        $ch = curl_init($this->__url);
        $payload = msgpack_pack($fx_arguments);
        //curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['key'=>$this->__key, 'type'=>'fx_call', 'fx_name'=>$fx_name,'arguments'=>$payload]);
        $result = curl_exec($ch);
        unset($ch);
        unset($payload);
        unset($fx_name);
        $r = msgpack_unpack($result);
        if (!is_array($r)){
            echo $result;
            throw new Exception("zrpc Error\nCan not parse answer.");
        }
        unset($result);
        if ($r['error'] !== false){
            echo '<pre>'.$r['txt'].'</pre>';
            throw new Exception('zrpc Error\n'.$r['error']);
        }
        return $r['result'];
    }
}
