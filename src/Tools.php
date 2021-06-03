<?
namespace Grithin\Api;

use \Grithin\Http;
use \Grithin\Conform;
use \Grithin\Arrays;
use \Exception;
use \Error;


class Tools{
	# using some method and input, call that instance method (which is potentially deep)
	function call($api_instance, $request){
		if(!$request->method){
			throw new \Grithin\Api\Exception('No api method used');
		}

		try{
			#+ since can't use protected/public/private to indicate what, in an API class is available as an API method, and since it is convenient to have non-api methods within the class which is also the Api class, use variables to indicate {
			if($api_instance->api_method_included){
				if(!in_array($request->method,$api_instance->api_method_included)){
					throw new \Grithin\Api\Exception('Api method not allowed "'.$request->method.'"');
				}
			}
			if($api_instance->api_method_excluded){
				if(in_array($request->method,$api_instance->api_method_excluded)){
					throw new \Grithin\Api\Exception('Api method not allowed "'.$request->method.'"');
				}
			}
			#+ }


			$fn = Arrays::got($api_instance, $request->method);
		}catch(Exception $e){
			throw new \Grithin\Api\Exception('Api method not found "'.$request->method.'"');
		}

		return $fn($request->input);
	}

	static function request_resolve($request){
		if($request === null){
			$request = Conform::post();
			#+ handle the case of no method specified, use request method to determine method
			if(empty($request['method']) && empty($request['requests'])){ # this is not foolproof, since the input can include a key `method` which would upset this fallback
				$request['input'] = $request;
				$map = ['DELETE'=>'delete','PUT'=>'create_update','POST'=>'create','PATCH'=>'update','GET'=>'read'];
				$request['method'] = $map[$_SERVER['REQUEST_METHOD']];
			}
		}
		#+ create standard wrapped tools {
		return (object)$request; # to pass by reference
	}

	/* Philosophy behind wrapping
	There is no particular need for the api instance to have untouchable attributes, so might as well just use attributes as a standard
	*/
	static function wrap($api_instance, $request=null){
		$api_instance->request = self::request_resolve($request == null ? $api_instance->request : $request);

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
			$api_instance->api_initialise($api_instance->request->input);
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
		}catch(Error $e){
			return self::exception_handle($e, $api_instance);
		}
		try{
			$method_return = self::call($api_instance, $api_instance->request);
			return $api_instance->response_maker->interpretted_result_once($method_return);
		}catch(\Grithin\ConformException $e){ # allow any level to throw a Conform exception which create a standard response
			return self::conform_exception_handle($e, $api_instance);
		}catch(\Grithin\Api\Exception $e){
			$api_instance->response_maker->response['errors'][] = ['message'=>$e->getMessage()];
			return $api_instance->response_maker->result_once();
		}catch(Exception $e){
			return self::exception_handle($e, $api_instance);
		}
		catch(Error $e){
			return self::exception_handle($e, $api_instance);
		}
	}

	function conform_exception_handle($e, $api_instance){
		if($api_instance->response_maker){
			$conform = $e->details; # ConformException has `details` as the Conform instance
			$api_instance->response_maker->response['errors'] = array_merge($api_instance->response_maker->response['errors'], $conform->get_errors());
			$conform->remove_errors(); # so as to not duplicate in `result_once` call
			return $api_instance->response_maker->result_once();
		}else{
			throw $e;
		}
	}
	function exception_handle($e, $api_instance){
		if($api_instance->exception_handler  || method_exists($api_instance, 'exception_handler')){ # api instance has custom exception handler, use it
			$api_instance->exception_handler($e);
		}elseif($api_instance->response_maker){ # since there is a response_maker, some code is expecting a response, so, package the error into a response
			if($current_exception_handler = \Grithin\Debug::current_exception_handler()){ # run the current exception handler - so the error might be logged for further detail
				$api_instance->response_maker->add_error_message($e->getMessage());
				$current_exception_handler($e);
			}
			return $api_instance->response_maker->result_once(); # put the error into the response
		}else{
			throw $e;
		}
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
	static function minimized_wrapped_call_response($api_instance, $request=null, $options=[]){
		if($options['allow_multiple']){
			/*
			if `allow_multiple`, expect an array of request objects with normal `input` and `method` keys.  Collect the results from each call and return
			*/
			$request = self::request_resolve($request);
			if($request->requests){
				$requests = Arrays::from($request->requests);
				$results = [];
				foreach($requests as $request){
					$results[] = ResponseMaker::minimize(self::wrapped_call(clone $api_instance, $request));
				}
				$return_results = function() use ($results){
					return $results;
				};
				Http::endJson(ResponseMaker::minimize(self::wrapped_call(self::pseudo_api_instance($return_results))));
			}

		}
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

	# remake extra path parts into standard `method` + `input` based on the $Route object
	function path_to_method($Route=null, &$input=null){
		# get the unparsed tokens to form them into a part1.part2 style method
		$method = implode('.', $Route->unparsedTokens);

		# only overwrite the input if there are extra path parts
		if($method){
			if($input === null){
				$input = &$_POST;
			}

			$input = array_merge($input, [
				'method'=>$method,
				'input'=>Conform::input()
			]);
		}
	}
}

