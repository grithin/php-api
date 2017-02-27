<?
/* About
Method mapping on to an API instance, passing in the standard input.  The Api instance gets an attribute `rm` and `response_maker` set as the ResponseMaker instance - although the Api instance could instantiate its own ResponseMaker.
*/

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

		$conform = Conform::standard_instance($this->request['input']);
		$this->api->response_maker = $this->api->rm = new ResponseMaker($conform);

		return $fn($this->request['input']);
	}
}
