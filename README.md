
# Philosophy
The API may not be accessed through HTTP, and consequently, the API should not rely on HTTP methods.  Instead, a standard JSON exchange interface is used.

# RequestMapper
RequestMapper maps a request dictionary to an API instance.  The request dictionary should be in the form
```json
{"method":"inquiry","input":{"bob":"sue"}}
```
This request is defaulted to `\Grithin\Conform::post()`, which handles content-type json requests.

The method is mapped to the api instance methods using `\Grithin\Arrays::got`, which allows sub-methods in both object and array form (ex `bob.bill.sue` can map to `bob->bill->sue`, `bob['bill']['sue']`)

# ResponseMaker
ResponseMaker handles logic involved in creating a standard response structure.  It integrates with Conform to apply validation rules and generate standard-strutured errors if any are present.  An example long form response structure:
```json
{
	"status":"fail",
	"errors":[
		{"fields":["name"],"message":"v.filled","rule":{"flags":{"break":true},"params":[],"fn_path":"v.filled"},"type":"v.filled","params":[]}
	],
	"data":null
}
```
`status` is either `fail` or `success`.  In standard form, `errors` is always an array, and `data` is always present.  In minimized standard form, if `errors` is an empty array, it is excluded from the response, and if `data` is null, it is excluded from the response.


# Use

## The Control

```php
<?
use \Grithin\Http;

use \Grithin\Api\RequestMapper;
use \Grithin\Api\ResponseMaker;

# \MyApi\V1 is the api instance on to which the mapper maps the request
$mapper = new RequestMapper(new \MyApi\V1);

try{
	# well call the mapper, and minimize the standard response, which is expected from the api return.  We then return the result as a HTTP JSON response since this is a HTTP API.
	Http::endJson(ResponseMaker::minimize($mapper->call()));
}catch(Exception $e){
	# In the event either the mapper threw an error or the api instance threw an error, catch it, create a standard minimized response for it, and return a HTTP JSON response with it.
	$rm = new ResponseMaker();
	$rm->add_error_message($e->getMessage());
	Http::endJson(ResponseMaker::minimize($rm->result()));
}
```
## Api Instance
The class from which to create an API instance.

`\Grithin\Api\Standard` trait is used for convenient integration with Conform and ResponseMaker.

```php
namespace MyApi;
class V1{
	use \Grithin\Api\Standard;

	function get_123($input){
		$rm = $this->response_maker($input);

		$validation = [
			'user_id'=>'!v.filled'
		];

		if($conformed = $rm->validate($validation)){
			return $rm->result(123);
		}
		return $rm->result(); # this result will have the validation error
	}
}
```

## Full Example
```php
class MyApiV1{
	use \Grithin\Api\Standard;

	function get_123($input){
		$rm = $this->response_maker($input);

		$validation = [
			'user_id'=>'!v.filled'
		];

		if($conformed = $rm->validate($validation)){
			return $rm->result(123);
		}
		return $rm->result(); # this result will have the validation error
	}
}


$_POST = ['method'=>'get_123', 'input'=>['user_id'=>'bob']];


use \Grithin\Http;

use \Grithin\Api\RequestMapper;
use \Grithin\Api\ResponseMaker;

# \MyApi\V1 is the api instance on to which the mapper maps the request
$mapper = new RequestMapper(new \MyApiV1);

try{
	# well call the mapper, and minimize the standard response, which is expected from the api return.  We then return the result as a HTTP JSON response since this is a HTTP API.
	Http::endJson(ResponseMaker::minimize($mapper->call()));
}catch(Exception $e){
	# In the event either the mapper threw an error or the api instance threw an error, catch it, create a standard minimized response for it, and return a HTTP JSON response with it.
	$rm = new ResponseMaker();
	$rm->add_error_message($e->getMessage());
	Http::endJson(ResponseMaker::minimize($rm->result()));
}


```

## Testing
```php
$assert = function($ought, $is){
	if($ought != $is){
		throw new Exception('ought is not is : '.\Grithin\Debug::pretty([$ought, $is]));
	}
};



class MyApiV1{
	use \Grithin\Api\Standard;

	function get_123($input){
		$rm = $this->response_maker($input);

		$validation = [
			'user_id'=>'!v.filled'
		];

		if($conformed = $rm->validate($validation)){
			return $rm->result(123);
		}
		return $rm->result(); # this result will have the validation error
	}
}



use \Grithin\Api\RequestMapper;
use \Grithin\Api\ResponseMaker;

$run_api = function(){
	# \MyApi\V1 is the api instance on to which the mapper maps the request
	$mapper = new RequestMapper(new \MyApiV1);

	try{
		# well call the mapper, and minimize the standard response, which is expected from the api return.  We then return the result as a HTTP JSON response since this is a HTTP API.
		return \Grithin\Tool::json_encode(ResponseMaker::minimize($mapper->call()));
	}catch(Exception $e){
		# In the event either the mapper threw an error or the api instance threw an error, catch it, create a standard minimized response for it, and return a HTTP JSON response with it.
		$rm = new ResponseMaker();
		$rm->add_error_message($e->getMessage());
		return \Grithin\Tool::json_encode(ResponseMaker::minimize($rm->result()));
	}
};


$_POST = ['method'=>'get_123', 'input'=>['user_id'=>'bob']];
$assert('{"status":"success","data":123}', $run_api());

$_POST = ['method'=>'get_123', 'input'=>[]];
$assert('{"status":"fail","errors":[{"fields":["user_id"],"message":"v.filled"}]}', $run_api());

$_POST = ['method'=>'bad_method'];
$assert('{"status":"fail","errors":[{"message":"Api method not found \"bad_method\""}]}', $run_api());

$_POST = [];
$assert('{"status":"fail","errors":[{"message":"No api method used"}]}', $run_api());
```

# Notes
A `status` can be `success` even with errors.  This, however, requires the `status` is set to `success` prior to `ResponseMaker::result()`
