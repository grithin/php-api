<?
namespace Grithin\Api;

use \Grithin\Conform;
use \Grithin\Arrays;
use \Exception;

class RequestMapper{
	public $instance;
	public $request;
	function __construct($api_instance, $request=null){
		if($request === null){
			$request = Conform::post();
		}
		$this->api = $api_instance;
		$this->request = $request;
	}
	function call(){
		if(!$this->request['method']){
			throw new Exception('No api method used');
		}

		try{
			$fn = Arrays::got($this->api, $this->request['method']);
		}catch(Exception $e){
			throw new Exception('Api method not found "'.$this->request['method'].'"');
		}
		return $fn($this->request['input']);
	}
}
