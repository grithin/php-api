<?
namespace Grithin\Api;

use \Grithin\Conform;

class ResponseMaker{
	public $conform;
	public $response = ['status'=>'', 'errors'=>[], 'data'=>null];

	function __construct($conform=null){
		if($conform){
			$this->conform = $conform;
		}else{
			$this->conform = Conform::standard_instance();
		}
	}
	# set response data
	function data($data){
		$this->response['data'] = $data;
	}
	# determine and set  status, and optionally set data
	function result($data=null){
		# optionally  set data
		if($data !== null){
			$this->response['data'] = $data;
		}

		# if conform instance has errors, merge into response errors
		if($this->conform->errors){
			if(!$this->response['errors']){
				$this->response['errors'] = [];
			}
			$this->response['errors'] = array_merge($this->response['errors'], $this->conform->errors);
		}

		# determine the status if it has not already been set
		if(!$this->response['status']){
			if($this->response['errors'] || $this->response['data'] === null){
				$this->response['status'] = 'fail';
			}else{
				$this->response['status'] = 'success';
			}
		}
		return $this->response;
	}
	/*
	Set errors into response and return false if errors, otherwise return conformed input
	*/
	function validate($rules){
		if(!$this->conform->valid($rules)){
			$this->response['errors'] = array_merge($this->response['errors'], $this->conform->get_errors());
			$this->conform->clear();
			return false;
		}
		return $this->conform->output;
	}
	function add_error_message($error){
		$this->response['errors'][] = ['message'=>$error];
	}

	static function minimize($result){
		if(!$result['errors']){
			unset($result['errors']);
		}else{
			foreach($result['errors'] as &$error){
				unset($error['rule']);
				unset($error['type']);
				unset($error['params']);
			}unset($error);

		}
		if($result['data'] === null){
			unset($result['data']);
		}

		return $result;
	}
}
