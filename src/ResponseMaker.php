<?
namespace Grithin\Api;

use \Grithin\Conform;

class ResponseMaker{
	public $conform;
	public $response = ['status'=>'', 'errors'=>[], 'data'=>null];
	public $resulted = false; # whether result() has already been called

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
	/* Notes
	-	will merge conform errors into response errors
	-	will set status to fail if errors present
	*/
	function result($data=null){
		$this->resulted = true;

		# optionally  set data
		if($data !== null){
			$this->response['data'] = $data;
		}

		# if conform instance has errors, merge into response errors
		if($this->conform->errors){
			if(!$this->response['errors']){
				$this->response['errors'] = [];
			}
			$this->response['errors'] = array_merge($this->response['errors'], $this->conform->get_errors());
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
	function set_success(){
		$this->response['status'] = 'success';
		return $this;
	}
	function set_fail(){
		$this->response['status'] = 'fail';
		return $this;
	}
	function error($error){
		$this->response['errors'] = [['message'=>$error]];
	}


	# call result if not previously called
	function result_once($data=null){
		if(!$this->resulted){
			return $this->result($data);
		}
		return $this->response;
	}

	/*
	In addition to the handling of the method result in self::result(), this adds an interpretation of the passed data:
	-	`false` = fail
	-	`true` = success
	-	other = default

	The effort here is to reduce unnecessary compoonents of the response, or unnecessary comonents of the Api instance, changing `{"status":"success","data":true}` to `{"status":"success"}`
	*/
	function interpretted_result_once($data=null){
		$data = $this->data_interpret($data);
		return $this->result_once($data);
	}
	# special handling of `true` and `false`, interpretted as status indication, and removed from `data`
	function data_interpret($data=null){
		if($data === false){
			$this->set_fail();
			$data = null;
		}elseif($data === true){
			$this->set_success();
			$data = null;
		}
		return $data;
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

	public function error_message_add($message){
		$this->response['errors'][] = ['message'=>$message];
		return $this;
	}
	# deprecated
	public function add_error_message(){
		$renamed = 'error_message_add';
		return call_user_func_array([$this, $renamed], func_get_args());
	}
	public function errors_add($errors){
		$this->response['errors'] = array_merge($this->response['errors'], $errors);
		return $this;
	}
	public function minimized(){
		return self::minimize($this->result_once());
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
