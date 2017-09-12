<?
namespace Grithin\Api;

use \Grithin\Http;
use \Grithin\Conform;
use \Grithin\Arrays;
use \Exception;


class Tools{
	# using some method and input, call that instance method (which is potentially deep)
	function call($api_instance, $request){
		if(!$request->method){
			throw new Exception('No api method used');
		}

		try{
			#+ since can't use protected/public/private to indicate what, in an API class is available as an API method, and since it is convenient to have non-api methods within the class which is also the Api class, use variables to indicate {
			if($api_instance->api_method_included){
				if(!in_array($request->method,$api_instance->api_method_included)){
					throw new Exception('Api method not allowed "'.$request->method.'"');
				}
			}
			if($api_instance->api_method_excluded){
				if(in_array($request->method,$api_instance->api_method_excluded)){
					throw new Exception('Api method not allowed "'.$request->method.'"');
				}
			}
			#+ }


			$fn = Arrays::got($api_instance, $request->method);
		}catch(Exception $e){
			throw new Exception('Api method not found "'.$request->method.'"');
		}

		return $fn($request->input);
	}

	/* Philosophy behind wrapping
	There is no particular need for the api instance to have untouchable attributes, so might as well just use attributes as a standard
	*/
	function wrap($api_instance, $request=null){
		if($request === null){
			$request = Conform::post();
		}
		#+ create standard wrapped tools {
		if(!$api_instance->request){
			$request = (object)$request; # to pass by reference
			$api_instance->request = $request;
		}
		if(!$api_instance->conform){
			$conform = Conform::standard_instance($api_instance->request->input);
			$api_instance->conform = $conform;
		}
		if(!$api_instance->response_maker){
			$response_maker = new ResponseMaker($api_instance->conform);
			$api_instance->response_maker = $response_maker;
		}
		#+ }

		#+ provide opportunity for api instance to adjust wrapped tools after wrapping {
		if(method_exists($api_instance, 'api_initialise')){
			$api_instance->api_initialise();
		}
		#+ }
	}
	/*
	The api_instance method result is not plainly passed, but rather, interpretted with `ResponseMaker::interpretted_result_once` for special handling of `false`, `true`.  To respond with an actual {data:true}, do: `$this->response_maker->result(true);` within the method
	*/
	function wrapped_call($api_instance, $request=null){
		try{
			self::wrap($api_instance, $request);
		}catch(\Grithin\ConformException $e){ # allow any level to throw a Conform exception which create a standard response
			return self::conform_exception_handle($e, $api_instance);
		}catch(Exception $e){
			return self::exception_handle($e, $api_instance);
		}
		try{
			$method_return = self::call($api_instance, $api_instance->request);
			return $api_instance->response_maker->interpretted_result_once($method_return);
		}catch(\Grithin\ConformException $e){ # allow any level to throw a Conform exception which create a standard response
			return self::conform_exception_handle($e, $api_instance);
		}catch(Exception $e){
			return self::exception_handle($e, $api_instance);
		}
	}
	function conform_exception_handle($e, $api_instance){
		$conform = $e->details;
		return $api_instance->response_maker->result_once();
	}
	function exception_handle($e, $api_instance){
		$api_instance->response_maker->add_error_message($e->getMessage());
		return $api_instance->response_maker->result_once();
	}
	# `wrapped_call`, but without the catches
	function wrapped_call_debug($api_instance, $request=null){
		self::wrap($api_instance, $request);
		$method_return = self::call($api_instance, $api_instance->request);
		return $api_instance->response_maker->interpretted_result_once($method_return);
	}
	# end process with json response
	function wrapped_call_response($api_instance, $request=null){
		Http::endJson(self::wrapped_call($api_instance, $request));
	}
	function wrapped_call_response_debug($api_instance, $request=null){
		ppe(self::wrapped_call_debug($api_instance, $request));
	}

	# end process with minimized json response
	function minimized_wrapped_call_response($api_instance, $request=null){
		Http::endJson(ResponseMaker::minimize(self::wrapped_call($api_instance, $request)));
	}
	# create an API instance from a function rather than an API class
	function pseudo_api_instance($fn, $input=null){
		if($input === null){
			$input = Conform::post();
		}
		$api_instance = new \StdClass;
		$api_instance->method = \Closure::bind($fn, $api_instance);
		$api_instance->request = (object)['method'=>'method', 'input'=>$input];
		return $api_instance;
	}
}
